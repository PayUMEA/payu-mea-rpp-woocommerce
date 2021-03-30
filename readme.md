# WooCommerce - PayU MEA Payment Gateway (Redirect)
* Contributors:  PayU Technical & Integration Support 
* Requires at least: 3.0.1
* Tested up to: WordPress 5.2.4 | WooCommerce 3.7.0
* Plugin version: 1.2
* License: GPLv2 or later
* License URI: http://www.gnu.org/licenses/gpl-2.0.html

Enables WooCommerce customers to do payments using PayU MEA (Middle East and Africa) as a payment gateway.

## Installation

1. Upload `woocommerce-payu-mea-payment-gateway` directory to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Navigate to the WooCommerce Payment gateway settings and configure with PayU MEA API details

## Configuration for Discovery Miles

If you have separate credentials for Discovery Miles, then:
1. Fill the credentials for "Discovery Miles Store" (all three)
2. put DISCOVERYMILES as *last (required!)* of methods in "Payment method" field (comma separated list)
3. Check the checkbox "Discovery Miles"
4. Set Discovery Miles "Transaction Type" na payment option title