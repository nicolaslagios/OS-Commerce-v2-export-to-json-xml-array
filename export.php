<?php

/**
 * OS Commerce 2 Custom Products API v.1.2.01 (export your products)
 * 
 * Script version 1.2.1 (29/5/2024)
 * 
 * Author: Nicolas Lagios
 * Website: https://nicolaslagios.com
 * 
 * This script connects to the OS Commerce database to fetch product, category, 
 * and product attribute information. It formats the data and outputs it in 
 * various formats (array, JSON, XML). The script includes options for debugging,
 * adjusting PHP limits, and adding extra pricing in EUR.
 *
 * Configuration variables:
 * - $debugging: Enable or disable debugging.
 * - $increaselimits: Enable or disable increased PHP limits.
 * - $format: Output format (array, JSON, XML).
 * - $protocol: HTTP or HTTPS.
 * - $domainName: Domain name for building URLs.
 * - $addextraprice: Add extra price in EUR.
 * - $api_key: API key for currency conversion.
 * 
 * Note: Do not modify the code below the configuration section unless you know 
 *       what you are doing.
 * 
 * Note2: You can directly call this script with https://domain.com/export.php?auth_key=kqMlgfJ5574i&format=xml or json or array 
 *        if you want to bypass the $format variable configuration
 * 
 * Note3 You can also add parameters like start=0 , end=100 etc on the url call.
 *       Example https://domain.com/export.php?auth_key=kqMlgfJ5574i&format=json&start=0&end=1000
 *       That means that each iteration have a custom limit.
 *       In this case, it starts at product 1 (ie 0) and ends at product 99 (ie 100).
 *       Next time, you can put 101 - 200, which means from product 100 to product 199 etc.
 * 
 * Note4 You can add the language parameter in order to get the next landuage.
 *       eg if you have more than one languages, you can add the parameter "lang" + the number of the language
 *       example: https://domain.com/export.php?auth_key=kqMlgfJ5574i&format=json&lang=1 for english or https://domain.com/export.php?auth_key=kqMlgfJ5574i&format=json&lang=2 etc
 *
 * Note5 As of 29/5/2024 we added Transliteration support for the Greek language in order to create the correct product and category urls
 *       If your extra language is different, you have to make the necessary changes on the transliterate function (line 279), read comments there.
 */


 // Define the authorization
$predefined_auth_key = "kqMlgfJ5574i"; // add your authorization key here and don't forget to include it in your url parameter

// Check for authorization
if (!isset($_GET['auth_key']) || $_GET['auth_key'] !== $predefined_auth_key) {
    header("HTTP/1.1 401 Unauthorized");
    die("Unauthorized access.");
}

require_once 'includes/configure.php'; // OS Commerce 2+ configuration file loading

// Configuration variables
$debugging = false; // Enable or disable debugging
$increaselimits = true; //Enable or disable increased php limits
$format = "json"; // Print the results in 3 formats: array, json, xml. This variable can be bypassed by using ?format parameter in url
$language = 1; //change the number in order to choose the language you want. This variable can be bypassed by using ?lang parameter in url
$addextraprice = true; // If true, adds an extra price in EUR; otherwise, make it false and ignore the api_key line
$api_key = "ADD-YOUR-API-KEY-HERE"; // Add your API key from freecurrencyapi.com


//domain name configuration
$autodomain = false; //there is some situation where the domain cannot automatically retrieved, like containers etc
if ($autodomain) {
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https" : "http"; // Don't change anything on this line
    $domainName = $_SERVER['HTTP_HOST']; // Don't change anything on this line
} else {
    $protocol = "https"; // If you set $autodomain to false, then Change the protocol here, http or https
    $domainName = "ADD-YOUR-CUSTOM-DOMAIN.com"; // If you set $autodomain to false, then Change the domain with or without www
}
$domain = $protocol . "://" . $domainName; // Don't change anything on this line


//attributes price and weight configuration (new feature since 1.2.0 - 28/5/2024)
$sum_price_and_weight = true; //true if you want to summarize the attribute price with parent price and attributes weight with parent weight
                              //this is because OS Commerce gives as attribute price and weight, only the difference and not the actual value.


/* ----------------- Don't mess with anything from here on out unless you know what you're doing ----------------- */

if ($debugging) {
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);
}

if ($increaselimits) {
    ini_set('max_execution_time', 0);
    ini_set('memory_limit', '-1');
}

if (isset($_GET['format'])) {
    $format = $_GET['format'];
}

if (isset($_GET['lang']) && is_numeric($_GET['lang'])) {
    $language = $_GET['lang'];
}

// Create a connection to the database
$connection = new mysqli(DB_SERVER, DB_SERVER_USERNAME, DB_SERVER_PASSWORD, DB_DATABASE);
if ($connection->connect_error) {
    die("Connection failed: " . $connection->connect_error);
}

// MySQL Query 1
$query = "
    SELECT 
        p.products_id AS product_id,
        p.products_model AS sku,
        pd.products_name AS name,
        pd.products_description AS description,
        ptc.categories_id AS category_id,
        p.products_quantity AS QTY,
        p.products_weight AS weight,
        p.products_price AS price,
        CONCAT('".$domain."/images/', REPLACE(p.products_image, ' ', '%20')) AS main_image,
        GROUP_CONCAT(CONCAT('".$domain."/images/', REPLACE(pi.image, ' ', '%20'))) AS additional_images
    FROM products p
    JOIN products_description pd ON p.products_id = pd.products_id
    JOIN products_to_categories ptc ON p.products_id = ptc.products_id
    LEFT JOIN products_images pi ON p.products_id = pi.products_id
    WHERE pd.language_id = ".$language."
    GROUP BY p.products_id;
";
$productResult = $connection->query($query);

// MySQL Query 2 (for categories)
$categoryQuery = "
    SELECT 
        c.categories_id,
        c.parent_id,
        cd.categories_name
    FROM categories c
    JOIN categories_description cd ON c.categories_id = cd.categories_id
    WHERE cd.language_id = ".$language.";
";
$categoryResult = $connection->query($categoryQuery);

// MySQL Query 3 (for product attributes)
$attributeQuery = "
    SELECT 
        products_attributes.products_id,
        products_options.products_options_name as attribute,
        products_options_values.products_options_values_name as term,
        products_attributes.options_values_price as term_price,
        products_attributes.options_values_weight as term_weight
    FROM
        products_attributes
    JOIN products_options ON products_attributes.options_id = products_options.products_options_id
    JOIN products_options_values ON products_attributes.options_values_id = products_options_values.products_options_values_id
    WHERE
        products_options.language_id = ".$language."
        AND products_options_values.language_id = ".$language.";
";
$attributeResult = $connection->query($attributeQuery);

// Building category map
$categories = [];
while ($category = $categoryResult->fetch_assoc()) {
    $categories[$category['categories_id']] = [
        'name' => $category['categories_name'],
        'parent_id' => $category['parent_id']
    ];
}

// Building attribute map
$attributes = [];
while ($attribute = $attributeResult->fetch_assoc()) {
    $productId = $attribute['products_id'];
    if (!isset($attributes[$productId])) {
        $attributes[$productId] = [];
    }
    $attributes[$productId][] = [
        'attribute' => $attribute['attribute'],
        'term' => $attribute['term'],
        'term_price' => $attribute['term_price'],
        'term_weight' => $attribute['term_weight']
    ];
}

// Process products and append category tree
$products = [];

if ($addextraprice) {
    $exchangeRate = fetchExchangeRate($api_key); //this is the one time builded variable for the eur price
    //$exchangeRate = 0.80; //uncoment this and comment the above line if you want to test without calling the freecurrencyapi.com api
}

if ($productResult) {
    while ($row = $productResult->fetch_assoc()) {
        
        if ($row['QTY'] < 0) { // Handle negative quantities
            $row['QTY'] = 0;
        }

        $row['price_usd'] = round($row['price'], 2); // Round USD price

        if ($addextraprice) {
            $row['price_eur'] = convertUsdToEur($row['price_usd'], $exchangeRate); // Build EUR price
        }

        $row['category_path'] = buildCategoryPath($row['category_id'], $categories); // Build and append category tree

        $row['additional_images'] = explode(',', $row['additional_images']); // Convert additional images to array

        $row['product_url'] = str_replace("&", "", generateProductURL($domain, $row['name'], $row['product_id'])); // Generate product URL

        $row['category_url'] = str_replace("&", "", generateCategoryURL($domain, $row['category_id'], $categories)); // Generate category URL

        if (isset($attributes[$row['product_id']])) { // Add attributes if available
            if ($sum_price_and_weight) {
                $parent_price = $row['price'];
                $parent_weight = $row['weight'];
                foreach ($attributes[$row['product_id']] as $index => $attribute) {
                    if ($index == 0) {
                        $attribute['term_price'] = $parent_price;
                        $attribute['term_weight'] = $parent_weight;
                    } else {
                        $attribute['term_price'] = $parent_price + $attribute['term_price'];
                        $attribute['term_weight'] = $parent_weight + $attribute['term_weight'];
                    }
                    $attributes[$row['product_id']][$index] = $attribute;
                }
            }
            $row['attributes'] = $attributes[$row['product_id']];
        }

        $products[] = $row; // Add to the products array
    }
    $productResult->free();
} else {
    echo "Error: " . $connection->error;
}

// Close the connection
$connection->close();

//if start and end parameters are set
if (isset($_GET['start']) && isset($_GET['end']) && is_numeric($_GET['start']) && is_numeric($_GET['end'])) {
    $start = $_GET['start'];
    $end = $_GET['end'];
    $products = array_slice($products, $start, $end - $start);
}

// Printing
if ($format == "array") {
    echo '<pre>';
        print_r($products);
    echo '</pre>';
} else if ($format == "json") {
    header('Content-Type: application/json');
    echo json_encode($products);
} else if ($format == "xml") {
    header('Content-Type: application/xml');
    echo arrayToXml($products, new SimpleXMLElement('<products/>'))->asXML();
} else {
    header("HTTP/1.1 400 Bad Request");
    echo "bad format: please add array, json or xml in $ format variable";
}

/* ----------------------------- Functions ----------------------------- */

// Function to build the category path
function buildCategoryPath($categoryId, $categories) {
    $path = [];
    while ($categoryId != 0 && isset($categories[$categoryId])) {
        array_unshift($path, $categories[$categoryId]['name']);
        $categoryId = $categories[$categoryId]['parent_id'];
    }
    return implode(' > ', $path);
}

// Function to transliterate Greek characters to Latin characters
function transliterate($text) {
    //$chinesse = array(); //this is an example for extra language
    $greek = array('α', 'β', 'γ', 'δ', 'ε', 'ζ', 'η', 'θ', 'ι', 'κ', 'λ', 'μ', 'ν', 'ξ', 'ο', 'π', 'ρ', 'σ', 'τ', 'υ', 'φ', 'χ', 'ψ', 'ω', 'ά', 'έ', 'ή', 'ί', 'ό', 'ύ', 'ώ', 'ς', 'ϊ', 'ΰ', 'ϋ', 'ΐ');
    $latin = array('a', 'b', 'g', 'd', 'e', 'z', 'h', 'th', 'i', 'k', 'l', 'm', 'n', 'x', 'o', 'p', 'r', 's', 't', 'y', 'f', 'ch', 'ps', 'o', 'a', 'e', 'i', 'i', 'o', 'y', 'o', 's', 'i', 'y', 'y', 'i');

    $firstreplacement = str_replace($greek, $latin, mb_strtolower($text));
    //$secondreplacement = str_replace($chinesse, $latin, mb_strtolower($text)); //this is an example for extra language
    
    $final = $firstreplacement;
    //$final = $firstreplacement.$secondreplacement; //this is an example for extra language

    return $final;
}

// Function to generate product URL
function generateProductURL($domain, $productName, $productId) {
    $transliteratedProductName = transliterate(str_replace(' ', '-', $productName));
    $url = $domain . '/' . $transliteratedProductName . '-p-' . $productId . '.html';
    return $url;
}

// Function to generate category URL
function generateCategoryURL($domain, $categoryId, $categories) {
    $ids = [];
    while ($categoryId != 0 && isset($categories[$categoryId])) {
        array_unshift($ids, $categoryId);
        $categoryId = $categories[$categoryId]['parent_id'];
    }
    $ids_string = implode('_', $ids);
    $categoryName = transliterate(str_replace(' ', '-', $categories[end($ids)]['name']));
    $url = $domain . '/' . $categoryName . '-c-' . $ids_string . '.html';
    return $url;
}

// Function to fetch the current exchange rate from USD to EUR
function fetchExchangeRate($api_key) {
    $api_url = "https://api.freecurrencyapi.com/v1/latest?apikey=" . $api_key;
    $response_json = file_get_contents($api_url);
    if ($response_json !== false) {
        $response_data = json_decode($response_json, true);
        if (isset($response_data['data'])) {
            $usd_to_eur_rate = $response_data['data']['EUR'];
            return $usd_to_eur_rate;
        } else {
            return "Error: Data key not found in API response";
        }
    } else {
        return "Error fetching data from API";
    }
}

// Function to convert USD to EUR
function convertUsdToEur($usd, $exchangeRate) {
    return round($usd * $exchangeRate, 2);
}

// Function to convert array to XML
function arrayToXml($data, &$xml) {
    foreach ($data as $key => $value) {
        if (is_array($value)) {
            if (is_numeric($key)) {
                $key = 'product';
            }
            $subnode = $xml->addChild($key);
            arrayToXml($value, $subnode);
        } else {
            $xml->addChild("$key", htmlspecialchars("$value"));
        }
    }
    return $xml;
}

?>