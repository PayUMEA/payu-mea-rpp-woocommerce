# WooCommerce - PayU MEA Payment Gateway (Redirect)
* Contributors:  PayU Technical & Integration Support 
* Requires at least: 3.0.1
* Tested up to: WordPress 5.2.4 | WooCommerce 3.7.0
* Stable tag: 4.3
* License: GPLv2 or later
* License URI: http://www.gnu.org/licenses/gpl-2.0.html

Enables WooCommerce customers to do payments using PayU MEA (Middle East and Africa) as a payment gateway.

## Fix for WooCommerce 2.4+
The original plugin has BOM characters within the PHP files, which are breaking the JSON response.  From WooCommerce 2.4+, WooCommerce requires a valid JSON response.  By re-encoding the PHP files as UTF8 without BOM characters, the redirect is successful. 

## Installation

1. Upload `woocommerce-payu-mea-payment-gateway` directory to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Navigate to the WooCommerce Payment gateway settings and configure with PayU MEA API details
