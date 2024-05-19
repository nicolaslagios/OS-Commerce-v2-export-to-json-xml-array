# OS-Commerce v2+ Export Script

This script exports all product data from an OS Commerce v2+ database in various formats (array, JSON, XML). 

If you find this project useful and want to support its development, please consider making a donation. Every contribution helps maintain and improve the plugin.

[![Donate](https://dev.maxservices.gr/pp.png)](https://www.paypal.com/donate/?hosted_button_id=HWYEPHKQ9D8F6)

Your generosity is greatly appreciated! 🙏

## Features

- Fetches product, category, and product attribute information.
- Outputs data in array, JSON, or XML format.
- Configurable options for debugging, PHP limits, and additional pricing in EUR.
- Ability to call the script directly with URL parameters for output format.

## Configuration

### Variables

- **$debugging**: Enable or disable debugging (default: `false`).
- **$increaselimits**: Enable or disable increased PHP limits (default: `true`).
- **$format**: Output format (`array`, `json`, `xml`). This can be overridden by using the `?format` parameter in the URL (default: `json`).
- **$addextraprice**: Add an extra price in EUR (default: `true`).
- **$api_key**: API key for currency conversion from [freecurrencyapi.com](https://freecurrencyapi.com) (default: `your_api_key`).

### Domain Configuration

- **$autodomain**: Automatically detect domain (default: `false`).
- **$protocol**: Protocol to use if `autodomain` is false (`http` or `https`) (default: `https`).
- **$domainName**: Domain name if `autodomain` is false (default: `nioras.com`).

## Usage

1. Set up the configuration variables as needed.
2. Place the script in your OS Commerce root directory.
3. Call the script directly via URL: https://yourdomain.com/export.php?format=json

Supported formats: `json`, `xml`, `array`.

### Optional URL Parameters

- **format**: Output format (overrides `$format` variable).
- **start** and **end**: Limits the range of products exported (useful for large datasets).

Example: https://yourdomain.com/export.php?format=xml&start=1&end=100

## Script Details

### MySQL Queries

- **Product Data**: Fetches product details including ID, SKU, name, description, category, quantity, weight, price, main image, and additional images.
- **Category Data**: Fetches category details including ID, parent ID, and name.
- **Product Attributes**: Fetches product attributes including attribute name, term, term price, and term weight.

### Functions

- **buildCategoryPath($categoryId, $categories)**: Builds the category path.
- **generateProductURL($domain, $productName, $productId)**: Generates the product URL.
- **generateCategoryURL($domain, $categoryId, $categories)**: Generates the category URL.
- **fetchExchangeRate($api_key)**: Fetches the current exchange rate from USD to EUR.
- **convertUsdToEur($usd, $exchangeRate)**: Converts USD to EUR.
- **arrayToXml($data, &$xml)**: Converts an array to XML.

### Error Handling

- Connection errors and query errors are displayed if debugging is enabled.
- If an invalid format is specified, a `400 Bad Request` error is returned.

## License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

## Author

**Nicolas Lagios** - [Website](https://nicolaslagios.com)
