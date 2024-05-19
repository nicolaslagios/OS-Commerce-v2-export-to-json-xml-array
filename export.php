<?php

/**
 * Script for export all products data from OS Commerce v2+.
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
 * Note2: You can directly call this script with https://domain.com/export.php?format=xml or json or array 
 *        if you want to bypass the $format variable configuration
 */

require_once 'includes/configure.php'; // OS Commerce 2+ configuration file

// Configuration variables
$debugging = false; // Enable or disable debugging
$increaselimits = true; //Enable or disable increased php limits
$format = "json"; // Print the results in 3 formats: array, json, xml. This variable can be bypassed by using ?format parameter in url
$addextraprice = true; // If true, adds an extra price in EUR; otherwise, make it false and ignore the api_key line
$api_key = "ADD-YOUR-KEY-HERE"; // Add your API key from freecurrencyapi.com

//domain name configuration
$autodomain = false; //there is some situation where the domain cannot automatically retrieved, like containers etc
if ($autodomain) {
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https" : "http"; // Don't change anything on this line
    $domainName = $_SERVER['HTTP_HOST']; // Don't change anything on this line
} else {
    $protocol = "https"; // If you set $autodomain to false, then Change the protocol here, http or https
    $domainName = "domain.com"; // If you set $autodomain to false, then Change the domain with or without www
}
$domain = $protocol . "://" . $domainName; // Don't change anything on this line









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

// Create a connection to the database
$connection = new mysqli(DB_SERVER, DB_SERVER_USERNAME, DB_SERVER_PASSWORD, DB_DATABASE);
if ($connection->connect_error) {
    die("Connection failed: " . $connection->connect_error);
}

// MySQL Query 1
if (isset($_GET['start']) && isset($_GET['end']) && is_numeric($_GET['start']) && is_numeric($_GET['end'])) {
    $start = $_GET['start'];
    $end = $_GET['end'];
    $limit = $end - $start + 1;
    $offset = $start - 1;
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
        WHERE pd.language_id = 1
        GROUP BY p.products_id;
        LIMIT ".$limit." OFFSET ".$offset.";
    ";
} else {
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
        WHERE pd.language_id = 1
        GROUP BY p.products_id;
    ";
}
$productResult = $connection->query($query);

// MySQL Query 2 (for categories)
$categoryQuery = "
    SELECT 
        c.categories_id,
        c.parent_id,
        cd.categories_name
    FROM categories c
    JOIN categories_description cd ON c.categories_id = cd.categories_id
    WHERE cd.language_id = 1;
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
        products_options.language_id = 1
        AND products_options_values.language_id = 1;
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
    //$exchangeRate = fetchExchangeRate($api_key); //this is the one time builded variable for the eur price
    $exchangeRate = 0.80;
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

        if (isset($attributes[$row['product_id']])) {
            $row['attributes'] = $attributes[$row['product_id']]; // Add attributes if available
        }

        $products[] = $row; // Add to the products array
    }
    $productResult->free();
} else {
    echo "Error: " . $connection->error;
}

// Close the connection
$connection->close();

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

// Function to generate product URL
function generateProductURL($domain, $productName, $productId) {
    $url = $domain . '/' . strtolower(str_replace(' ', '-', $productName)) . '-p-' . $productId . '.html';
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
    $categoryName = strtolower(str_replace(' ', '-', $categories[end($ids)]['name']));
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
