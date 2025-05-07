<?php declare(strict_types=1);

/*
 * Plugin Name: WooCommerce PayU Gateway
 * Plugin URI: https://wordpress.org/plugins/woocommerce-gateway-payu/
 * Description: Accept payments using PayU
 * Author: PayU MEA
 * Author URI: https://southafrica.payu.com/
 * Version: 1.0.0
 * Requires at least: 6.5
 * Tested up to: 6.6
 * Requires PHP: 8.0
 * Requires PHP Architecture: 64 bits
 * Requires Plugins: woocommerce
 * WC requires at least: 7.4
 * WC tested up to: 9.3
 * Text Domain: woocommerce-gateway-payu
 * Domain Path: /plugins
 */

if ( ! defined( 'ABSPATH' ) ) exit;

add_action( 'plugins_loaded', 'load_payu_rpp_gateway_class', 0 );

function load_payu_rpp_gateway_class() {

    if (!class_exists( 'WC_Payment_Gateway' ) ) {
        return;
    }

    class WC_Gateway_PayU_RPP extends WC_Payment_Gateway
    {
        /**
         * @var ?stdClass
         */
        protected ?stdClass $txn_data = null;

        /**
         * WC_Gateway_PayU_RPP constructor.
         *
         * @access public
         * @return void
         */
        public function __construct(
            protected ?WC_Logger $log = null,
            protected string $debug = 'no',
            protected string $prod_url = 'https://secure.payu.co.za',
            protected string $staging_url = 'https://staging.payu.co.za',
            protected string $notify_url = '',
            protected string $safekey = '',
            protected string $username = '',
            protected string $password = '',
            protected string $testmode = 'yes',
            protected string $enable_logging = '',
            protected string $extended_debug = '',
            protected string $payment_method = 'CREDITCARD',
            protected string $transaction_type = 'PAYMENT',
            protected string $debit_order_enabled = 'no',
            protected string $debit_order_type = 'PAYMENT'
        ) {
            $this->init();
            
            // Setup general properties.
            $this->setup_properties();

            // Load the settings.
            $this->init_form_fields();
            $this->init_settings();

            // Define user variables
            $this->get_settings();

            // Logs
            if ('yes' === $this->debug && function_exists('wc_get_logger')) {
                $this->log = wc_get_logger();
            }

            // Actions
            add_action('woocommerce_update_options_payment_gateways_' . $this->id, [$this, 'process_admin_options']);

            // Payment listener/API hook
            add_action('woocommerce_api_wc_gateway_payu_rpp', [$this, 'process_payu_callback']);

            if (!$this->is_valid_for_use()) {
                $this->enabled = false;
            }
        }

        private function setup_properties()
        {
            $this->id = 'payu';
            $this->icon = WP_PLUGIN_URL . DIRECTORY_SEPARATOR . plugin_basename(dirname(__FILE__)) . '/images/payu_mea_logo.png';
            $this->method_title = __('PayU MEA (Redirect)', 'woocommerce-gateway-payu');
            $this->method_description = __('Accept payments with credit/debit cards, EFT, Discovery miles, eBucks and many more');
            $this->has_fields = true;
        }

        private function get_settings()
        {
            $this->debug = $this->get_option('debug');
            $this->title = $this->get_option('title');
            $this->description = $this->get_option('description');
            $this->safekey = $this->get_option('safekey');
            $this->username = $this->get_option('username');
            $this->password = $this->get_option('password');
            $this->testmode	= $this->get_option('testmode');
            $this->payment_method = $this->get_option('payment_method');
            $this->transaction_type = $this->get_option('transaction_type');
            $this->debit_order_enabled = $this->get_option('debit_order_enabled');
            $this->debit_order_type = $this->get_option('debit_order_type');
            $this->enable_logging = $this->get_option('enable_logging');
            $this->extended_debug = $this->get_option('extended_debug');
            $this->notify_url = add_query_arg('wc-api', 'WC_Gateway_PayU_RPP', home_url());
        }

        private function init() {
            require_once dirname( __FILE__ ) . '/includes/exceptions/empty-log-string-exception.php';

            require_once dirname( __FILE__ ) . '/includes/classes/class-payu-payment-base.php';
            require_once dirname( __FILE__ ) . '/includes/classes/class-payu-payment-redirect.php';
        }

        /**
         * Check if this gateway is enabled and available in the user's country
         *
         * @access public
         * @return bool
         */
        private function is_valid_for_use()
        {
            if (!in_array(
                get_woocommerce_currency(),
                apply_filters(
                    'woocommerce_paypal_supported_currencies',
                    [
                        'AUD', 'BRL', 'CAD', 'MXN', 'NZD', 'HKD', 'SGD', 'USD',
                        'EUR', 'JPY', 'TRY', 'NOK', 'CZK', 'DKK', 'HUF', 'ILS',
                        'MYR', 'NGN', 'PHP', 'PLN', 'SEK', 'CHF', 'TWD', 'THB', 'GBP',
                        'RMB','ZAR','KES','RWF','TZS','UGX'
                    ]
                )
            )) {
                return false;
            }

            return true;
        }

        public function log($message, $level = 'debug')
        {
            if ($this->enable_logging) {
                if (empty($this->log)) {
                    $this->log = wc_get_logger();
                }

                $this->log->log($level, $message, ['source' => 'payu']);
            }
        }

        public function process_admin_options()
        {
            $saved = parent::process_admin_options();
    
            // Maybe clear logs.
            if ('yes' !== $this->get_option('debug', 'no')) {
                if (empty($this->log)) {
                    $this->log = wc_get_logger();
                }

                $this->log->clear('payu');
            }
    
            return $saved;
        }

        /**
         * Admin Panel Options
         *
         * @since 1.0.0
         */
        public function admin_options()
        {
            ?>
            <h3><?php _e('PayU MEA (Redirect)', 'woocommerce-gateway-payu'); ?></h3>
            <p><?php _e('PayU Redirect Payment works by sending the user to PayU to enter their payment information.', 'woocommerce-gateway-payu'); ?></p>

            <?php if ($this->is_valid_for_use()) : ?>
                <table class="form-table">
                    <?php
                    // Generate the HTML For the settings form.
                    $this->generate_settings_html();
                    ?>
                </table>
            <?php else : ?>
                <div class="inline error"><p><strong><?php _e('Gateway Disabled', 'woocommerce-gateway-payu'); ?></strong>: <?php _e('PayU does not support your store currency.', 'woocommerce-gateway-payu'); ?></p></div>
                <?php
            endif;
        }

        /**
         * Initialise Gateway Settings Form Fields
         *
         * @access public
         * @return void
         */
        function init_form_fields()
        {
            $order_tx_type = [
                'PAYMENT' => __('PAYMENT', 'woocommerce-gateway-payu'),
                'RESERVE' => __('RESERVE', 'woocommerce-gateway-payu'),
            ];

            $this->form_fields = [
                'enabled' => [
                    'title' => __('Enable payment gateway.', 'woocommerce-gateway-payu'),
                    'type' => 'checkbox',
                    'label' => __('', 'woocommerce-gateway-payu'),
                    'default' => 'no',
                ],
                'testmode' => [
                    'title' => __('Environment', 'woocommerce-gateway-payu'),
                    'type' => 'checkbox',
                    'label' => __('(ticked = staging/sandbox, unticked = production)', 'woocommerce-gateway-payu'),
                    'default' => 'yes',
                    'description' => __('Which PayU environment to use for transactions.', 'woocommerce-gateway-payu'),
                ],
                'title' => [
                    'title' => __('Payment Title:', 'woocommerce-gateway-payu'),
                    'type' => 'text',
                    'description' => __('This controls the title which the user sees during checkout.', 'woocommerce-gateway-payu'),
                    'default' => __('PayU', 'woocommerce-gateway-payu'),
                    'desc_tip' => true,
                ],
                'credit_card_subtitle' => [
                    'title' => __('Default Method Title:', 'woocommerce-gateway-payu'),
                    'type' => 'text',
                    'description' => __('This controls the title for default payment method which the user sees during checkout.', 'woocommerce-gateway-payu'),
                    'default' => __('Credit/Debit Card, eBucks and EFT Pro', 'woocommerce-gateway-payu'),
                    'desc_tip' => true,
                ],
                'description' => [
                    'title' => __('Description:', 'woocommerce-gateway-payu'),
                    'type' => 'text',
                    'description' => __('The description of available payment methods which the user sees during checkout.', 'woocommerce-gateway-payu'),
                    'default' => __('Payment method(s)', 'woocommerce-gateway-payu'),
                ],
                'safekey' => [
                    'title' => __('SafeKey', 'woocommerce-gateway-payu'),
                    'type' => 'text',
                    'description' => __('Given to Merchant by PayU', 'woocommerce-gateway-payu'),
                ],
                'username' => [
                    'title' => __('SOAP Username', 'woocommerce-gateway-payu'),
                    'type' => 'text',
                    'description' => __('Given to Merchant by PayU', 'woocommerce-gateway-payu'),
                ],
                'password' => [
                    'title' => __('SOAP Password', 'woocommerce-gateway-payu'),
                    'type' => 'text',
                    'description' => __('Given to Merchant by PayU', 'woocommerce-gateway-payu'),
                ],
                'dm_safekey' => [
                    'title' => __('Discovery Miles SafeKey', 'woocommerce-gateway-payu'),
                    'type' => 'text',
                    'description' => __('Given to Merchant by PayU', 'woocommerce-gateway-payu'),
                ],
                'dm_username' => [
                    'title' => __('Discovery Miles Username', 'woocommerce-gateway-payu'),
                    'type' => 'text',
                    'description' => __('Given to Merchant by PayU', 'woocommerce-gateway-payu'),
                ],
                'dm_password' => [
                    'title' => __('Discovery Miles Password', 'woocommerce-gateway-payu'),
                    'type' => 'text',
                    'description' => __('Given to Merchant by PayU', 'woocommerce-gateway-payu'),
                ],
                'currency' => [
                    'title' => __('Currency', 'woocommerce-gateway-payu'),
                    'type' => 'text',
                    'description' =>  __('Supported Currencies', 'woocommerce-gateway-payu'),
                    'default' => __('ZAR', 'woocommerce-gateway-payu'),
                ],
                'payment_method' => [
                    'title' => __('Payment Method', 'woocommerce-gateway-payu'),
                    'type' => 'text',
                    'description' =>  __('Supported Payment Methods', 'woocommerce-gateway-payu'),
                    'default' => __('CREDITCARD', 'woocommerce-gateway-payu'),
                ],
                'transaction_type' => [
                    'title' => __('Transaction Type', 'woocommerce-gateway-payu'),
                    'type' => 'select',
                    'description' =>  __('Supported Transaction Types', 'woocommerce-gateway-payu'),
                    'options' => $order_tx_type,
                ],
                'woocommerce_gateway_order' => [
                    'title' => __('Sort Order', 'woocommerce-gateway-payu'),
                    'type' => 'number',
                    'description' =>  __('The order to display payment method in store front', 'woocommerce-gateway-payu'),
                ],
                'debit_order_enabled' => [
                    'title' => __('Discovery Miles', 'woocommerce-gateway-payu'),
                    'type' => 'checkbox',
                    'label' => __('Enable separate configuration for Discovery Miles.', 'woocommerce-gateway-payu'),
                    'default' => 'no',
                ],
                'debit_order_type' => [
                    'title' => __('Discovery Miles Transaction Type', 'woocommerce-gateway-payu'),
                    'type' => 'select',
                    'options' => $order_tx_type,
                    'description' => __( 'Select the Discovery Miles Transaction Type.', 'woocommerce-gateway-payu' ),
                ],
                'recurring_subtitle' => [
                    'title' => __('Discovery Miles Payment Title:', 'woocommerce-gateway-payu'),
                    'type' => 'text',
                    'description' => __('This controls the Discovery Miles title which the user sees during checkout.', 'woocommerce-gateway-payu'),
                    'default' => __('Pay with Discovery Miles', 'woocommerce-gateway-payu'),
                    'desc_tip' => true,
                ],
                'debug' => [
                    'title' => __('Debug Log', 'woocommerce-gateway-payu'),
                    'type' => 'checkbox',
                    'label' => __('Enable logging', 'woocommerce-gateway-payu'),
                    'default' => 'no',
                    'description' => __('Default logging stored inside <code>woocommerce/logs/payu-%s.txt</code>', 'woocommerce-gateway-payu')
                ],
                'enable_logging' => [
                    'title' => __('PayU Logging', 'woocommerce-gateway-payu'),
                    'type' => 'checkbox',
                    'label' => __('Enable PayU logging', 'woocommerce-gateway-payu'),
                    'default' => 'no',
                    'description' => __('Log plugin events, such as IPN requests from PayU servers', 'woocommerce-gateway-payu')
                ],
                'extended_debug' => [
                    'title' => __('Extended Debug Enable', 'woocommerce-gateway-payu'),
                    'type' => 'checkbox',
                    'label' => __('Enable PayU extended logging', 'woocommerce-gateway-payu'),
                    'default' => 'no',
                    'description' => __('Log SOAP request, response and headers', 'woocommerce-gateway-payu')
                ]
            ];
        }

        /**
         *  There are no payment fields for payu, but we want to show the description if set.
         *  As this is an RPP implementation
         **/
        function payment_fields()
        {
            if ($this->description) {
                echo wpautop(wptexturize($this->description));
            }

            ?>
            <input type="radio" name="payu_transaction_type" id="payu_transaction_type" value="default" checked><?php print $this->settings['credit_card_subtitle'];?><br>
            <?php if ($this->debit_order_enabled == 'yes' && !empty($this->debit_order_type)) :?>
                <input type="radio" name="payu_transaction_type" id="payu_transaction_type" value="recurring" ><?php print $this->settings['recurring_subtitle'];?><br>
            <?php endif;
        }

        /**
         * Process the payment and return the result
         */
        public function process_payment($order_id)
        {
            $transaction_type_selection = $_POST['payu_transaction_type'];

            try {
                $order = new WC_Order($order_id);
                $safekey = $this->settings['safekey'];

                // Discovery Miles separate login credentials prefix
                $payu_credentials_prefix = '';

                /** If Discovery Miles selected */
                if (isset($_POST["payu_transaction_type"]) &&
                    ('recurring' == $_POST["payu_transaction_type"]) &&
                    !empty($this->settings['dm_safekey'])
                ) {
                    $payu_credentials_prefix = 'dm';
                    $safekey = $this->settings['dm_safekey'];
                }

                $order->update_meta_data('payu_credentials_prefix', $payu_credentials_prefix);

                if ( ( 'dm' != $payu_credentials_prefix) && !empty($this->settings['dm_username'] ) ) {
                    $payment_methods = explode(',', $this->payment_method);

                    if ( in_array( 'DISCOVERYMILES', $payment_methods ) ) {
                        $index = array_search('DISCOVERYMILES', $payment_methods);
                        unset($payment_methods[$index]);
                    }

                    $this->payment_method = trim(implode(',', $payment_methods), ",");
                }

                // Config for SOAP client instantiation
                $config = $this->get_configuration();

                $txn_data = $this->get_transaction_data($config, $order);
                $txn_data['Safekey'] = $safekey;

                if ($transaction_type_selection == "default") {
                    $txn_data['TransactionType'] = $this->transaction_type;
                } else {
                    $this->transaction_type = $this->debit_order_type;
                    $txn_data['TransactionType'] = $this->transaction_type;
                }

                // Do setTransaction
                $pr_payment = new PayU_Payment_Redirect($config);
                $response = $pr_payment->do_set_transaction($txn_data);

                // Retrieve setTransaction response
                if (isset($response->redirect_payment_url) &&
                    isset($response->payUReference)
                ) {
                    $pay_u_reference = $response->payUReference;
                    $set_transaction_notes = 'PayU Reference: ' . $pay_u_reference;
                    $set_transaction_notes .= ' Allowed Methods: ' . $this->payment_method;
                    $order->add_order_note(__('Redirecting to PayU, ' . $set_transaction_notes, 'woocommerce-gateway-payu'));
                    // Processing Payment
                    $order->update_status('pending', '', true);
                }
            } catch(Exception $e) {
                $message = $e->getMessage();
                $error_message = ' - ' . $message . "<br /><br />";
                $this->log($error_message, 'critical');

                return [
                    'result' => 'failure',
                    'redirect' => $order->get_checkout_payment_url()
                ];
            }

            return [
                'result' => 'success',
                'redirect' => $response->redirect_payment_url
            ];
        }

        /**
         * Process payu server response
         **/
        public function process_payu_callback()
        {
            if (!empty($_GET['action']) && $_GET['action'] === 'cancelled' && isset($_GET['order_id'])) {
                $this->process_cancel();
            } elseif(!empty($_GET['PayUReference'])) {
                $this->process_capture();
            } else {
                $this->process_ipn();
            }

            $this->txn_data = null;
        }

        private function process_capture()
        {
            global $woocommerce;

            $order_id = 0;
            $get_txn_data = [];
            $pay_u_reference = $_GET['PayUReference'];

            try {
                /** @var WC_Order $order */
                $order = new WC_Order($_GET['order_id']);
                
                //Discovery Miles separate login credentials prefix
                $payu_credentials_prefix = $order->get_meta('payu_credentials_prefix', true);
                $config = $this->get_configuration($payu_credentials_prefix);

                $safekey = $this->settings['safekey'];

                if ( !empty( $payu_credentials_prefix ) && !empty( $this->settings[$payu_credentials_prefix.'_safekey'] ) ) {
                    $safekey = $this->settings[$payu_credentials_prefix.'_safekey'];
                }

                $get_txn_data['Safekey'] =  $safekey;
                $get_txn_data['AdditionalInformation']['payUReference'] = $pay_u_reference;

                $rpp_instance = new PayU_Payment_Redirect($config);
                $this->txn_data = $rpp_instance->do_get_transaction($get_txn_data);

                if ( !empty( $this->get_merchant_reference() ) ) {
                    $order_id = $this->get_merchant_reference();
                }

                $order = new WC_Order($order_id);

                // Check order not already completed
                if ($order->get_status() === 'processing' || $order->get_status() === 'completed') {
                    if ('yes' === $this->debug) {
                        $this->log->add('PayU', 'Aborting, Order #' . $order->get_id() . ' is already complete.');
                    }

                    wc_add_notice(__('Order is already completed', 'woocommerce-gateway-payu' ), 'success');
                    wp_redirect($this->get_return_url($order));
                    exit;
                }

                if ($this->is_payment_successful()) {
                    $transaction_notes = "PayU Reference: " . $this->get_tranx_id() . "<br /> ";

                    $transaction_notes .= $this->get_payment_method_details($transaction_notes);

                    if ($this->get_recurring_details() != null && is_array($this->get_recurring_details())) {
                        $transaction_notes .= "<br /><br />Recurring Details:";

                        foreach ($this->get_recurring_details() as $key => $value) {
                            $transaction_notes .= "<br />&nbsp;&nbsp;- " . $key . ":" . $value . ", ";
                        }
                    }

                    // Payment completed
                    $order->add_order_note(__("<strong>Payment completed: </strong><br />", 'woocommerce-gateway-payu' ) . $transaction_notes);
                    $order->payment_complete();
                    $woocommerce->cart->empty_cart();

                    if ('yes' === $this->debug) {
                        $this->log->add('PayU', 'Payment complete.');
                        wc_add_notice(__('Payment completed: <br />', 'woocommerce-gateway-payu' ) . $transaction_notes, 'success');
                    }
                    
                    wp_redirect($this->get_return_url($order));
                    exit;
                } else {
                    $reason = $this->get_display_message();
                    $transaction_notes = "PayU Reference: " . $this->get_tranx_id() . "<br />";
                    $transaction_notes .= "Error: " . addslashes($reason) . "<br />";
                    $transaction_notes .= "Point Of Failure: " . $this->get_point_of_failure() . "<br />";
                    $transaction_notes .= "Result Code: " . $this->get_result_code();

                    // Check for existence of new notification api (WooCommerce >= 2.1)
                    if (function_exists('wc_add_notice')) {
                        wc_add_notice(__('Payment Failed:', 'woocommerce-gateway-payu') . $reason, 'error');
                    } else {
                        $woocommerce->add_error(__('Payment Failed:', 'woocommerce-gateway-payu') . $reason);
                    }

                    $order->add_order_note(__( "<strong>Payment unsuccessful: </strong><br />". $transaction_notes, 'woocommerce-gateway-payu'));

                    if ('yes' == $this->debug) {
                        $this->log->add( 'PayU', 'Payment Failed.' );
                    }

                    wp_redirect($order->get_checkout_payment_url());
                    exit;
                }
            } catch(Exception $e) {
                $errorMessage = $e->getMessage();
                wp_die($errorMessage);
            }
        }

        private function process_ipn()
        {
            $postData  = file_get_contents("php://input");
            $sxe = simplexml_load_string($postData);

            if (empty($sxe)) {
                return;
            }

            $return_data = $this->xml_to_torray($sxe);

            if (empty($return_data)){
                return;
            }

            $order_id = (int)$return_data['MerchantReference'];
            $pay_u_reference = $return_data['PayUReference'];

            if (isset($order_id) && !empty($order_id) && is_numeric($order_id)) {
                $order = new WC_Order($order_id);
            }

            if ($order->get_status() === 'processing' || $order->get_status() === 'completed') {
                if ('yes' === $this->debug) {
                    $this->log->add('payu', 'Aborting, Order #' . $order->get_id() . ' is already completed.');
                }
                
                return;
            }

            try {
                //Creating get transaction soap data array
                $get_txn_data = [];
                $get_txn_data['Safekey'] = $this->settings['safekey'];
                $get_txn_data['AdditionalInformation']['payUReference'] = $pay_u_reference;

                //Discovery Miles separate login credentials prefix
                $payu_credentials_prefix = $order->get_meta('payu_credentials_prefix', true);
                $config = $this->get_configuration($payu_credentials_prefix);

                $rpp_instance = new PayU_Payment_Redirect($config);
                $this->txn_data = $rpp_instance->do_get_transaction($get_txn_data);

                //Checking IPN is valid
                if (
                    !in_array($this->get_result_code(), array('POO5', 'EFTPRO_003', '999', '305')) &&
                    $this->is_payment_successful()
                ) {
                    $amount_basket = $this->get_total_due();
                    $amount_paid = $this->total_captured();

                    $transaction_notes = "";
                    $transaction_notes .= "<strong>-----PAYU IPN RECIEVED---</strong><br />";
                    $transaction_notes .= "Order Amount: " . $amount_basket . "<br />";
                    $transaction_notes .= "Amount Paid: " . $amount_paid . "<br />";
                    $transaction_notes .= "Merchant Reference : " . $this->get_merchant_reference() . "<br />";
                    $transaction_notes .= "PayU Reference: " . $this->get_tranx_id() . "<br />";
                    $transaction_notes .= "PayU Payment Status: " . $this->get_transaction_state() . "<br /><br />";
                    $transaction_notes .= $this->get_payment_method_details($transaction_notes);

                    // Validate amount

                    // Payment completed
                    $order->add_order_note(__($transaction_notes, 'woocommerce-gateway-payu'));
                    $order->payment_complete();

                    if ('yes' === $this->debug) {
                        $this->log->add('payu', 'Payment complete.');
                    }
                } else {
                    $transaction_notes = 'PayU Reference: ' . $this->get_tranx_id() . '<br />';
                    $transaction_notes .= 'Point Of Failure: ' . $this->get_point_of_failure() . '<br />';
                    $transaction_notes .= 'Result Code: ' . $this->get_result_code();

                    $order->add_order_note(__('<strong>Payment unsuccessful: </strong><br />', 'woocommerce') . $transaction_notes);

                    if ('yes' === $this->debug) {
                        $this->log->add('payu', 'Payment Failed.');
                    }
                }
            } catch(Exception $e) {
                $errorMessage = $e->getMessage();
                $this->log->add('payu', $errorMessage);
            }
        }

        private function process_cancel()
        {
            $order = new WC_Order($_GET['order_id']);
            $transactionNotes = "Payment cancelled by user";
            wc_add_notice(__('', 'woocommerce-gateway-payu') . $transactionNotes, 'error');
            $order->add_order_note( __($transactionNotes, 'woocommerce-gateway-payu' ));

            if ('yes' == $this->debug) {
                $this->log->add( 'payu', 'Payment cancelled.' );
            }

            wp_redirect($order->get_checkout_payment_url());
        }

        private function xml_to_torray($xml) {
            if ( empty( $xml ) ) {
                return false;
            }

            $data = [];
            $data[$xml['Stage']->getName()] = $xml['Stage']->__toString();

            foreach ($xml as $element) {
                if ($element->children()) {
                    foreach ($element as $child) {
                        if ($child->attributes()) {
                            foreach ($child->attributes() as $key => $value) {
                                $data[$element->getName()][$child->getName()][$key] = $value->__toString();
                            }
                        } else {
                            $data[$element->getName()][$child->getName()] = $child->__toString();
                        }
                    }
                } else {
                    $data[$element->getName()] = $element->__toString();
                }
            }

            return $data;
        }

        private function get_configuration(?string $payu_credentials_prefix = null): array
        {
            $soap_username = $this->settings['username'];
            $soap_password = $this->settings['password'];

            if (!empty($payu_credentials_prefix)) {
                if (!empty($this->settings[$payu_credentials_prefix.'_username'])) {
                    $soap_username = $this->settings[$payu_credentials_prefix.'_username'];
                }

                if (!empty($this->settings[$payu_credentials_prefix.'_password'])) {
                    $soap_password = $this->settings[$payu_credentials_prefix.'_password'];
                }
            }

            /** If Discovery Miles selected */
            if (isset($_POST["payu_transaction_type"]) && ('recurring' == $_POST["payu_transaction_type"])) {
                if (!empty($this->settings['dm_username'])) {
                    $soap_username = $this->settings['dm_username'];
                }

                if (!empty($this->settings['dm_password'])) {
                    $soap_password = $this->settings['dm_password'];
                }
            }

            $config = [];
            $config['username'] = $soap_username;
            $config['password'] = $soap_password;
            $config['log_enable'] = $this->enable_logging;
            $config['extended_debug_enable'] = $this->extended_debug;

            if(strtolower($config['log_enable']) === 'yes') {
                $config['log_enable'] = true;
            } else {
                $config['log_enable'] = false;
            }

            if(strtolower($config['extended_debug_enable']) === 'yes') {
                $config['extended_debug_enable'] = true;
            } else {
                $config['extended_debug_enable'] = false;
            }

            $config['production'] = false;

            if ($this->settings['testmode'] === 'no') {
                $config['production'] = true;
            }

            return $config;
        }

        private function get_transaction_data(array $config, WC_Order $order)
        {
            $txn_data = [];

            // Customer data
            $customer = [];

            if (empty($order->get_shipping_first_name())) {
                $customer['firstName'] = $order->get_billing_first_name();
            } else {
                $customer['firstName'] = $order->get_shipping_first_name();
            }

            if (empty($order->get_shipping_last_name())) {
                $customer['lastName'] = $order->get_billing_last_name();
            } else {
                $customer['lastName'] = $order->get_shipping_last_name();
            }

            $customer['mobile'] = $order->get_billing_phone();
            $customer['email'] = $order->get_billing_email();


            if (empty($order->get_shipping_country())) {
                $country_code = $order->get_billing_country();
            } else {
                $country_code = $order->get_shipping_country();
            }

            $customer['countryCode'] = WC()->countries->get_country_calling_code($country_code);
            $customer['countryCode'] = str_replace('+', '', $customer['countryCode']);
            $customer['regionalId'] = $customer['countryCode'];

            if (is_user_logged_in()) {
                $current_user = wp_get_current_user();
                $customer['merchantUserId'] = $current_user->ID;
            }

            //Add Customer
            $txn_data = array_merge($txn_data, ['Customer' => $customer]);
            unset($customer);

            $order_id = $order->get_id();

            //Cart data
            $basket = [];
            $woocommerce_format = $order->get_total();
            $float_amount = $woocommerce_format * 100;
            $basket['amountInCents'] = (int) $float_amount;
            $basket['description'] = 'Order No:' . (string)$order_id;
            $basket['currencyCode'] = $this->settings['currency'];

            //Add Basket
            $txn_data = array_merge($txn_data, ['Basket' => $basket]);
            unset($basket);

            // Additional Information
            $additional_info = [];
            $additional_info['supportedPaymentMethods'] = $this->payment_method;
            $additional_info['cancelUrl'] = $this->notify_url . '&order_id=' . $order_id . '&action=cancelled';
            $additional_info['notificationUrl'] = $this->notify_url;
            $additional_info['returnUrl'] = $this->notify_url . '&order_id=' . $order_id;
            $additional_info['merchantReference'] = (string)$order_id;

            if (!$config['production']) {
                $additional_info['demoMode'] = 'true';
            }

            if (!is_user_logged_in()) {
                $additional_info['callCenterRepId'] = 'Unknown';
            }

            // Add Additionnal Information
            $txn_data = array_merge($txn_data, ['AdditionalInformation' => $additional_info]);
            unset($additional_info);

            // Transaction record array
            if ($this->debit_order_enabled == 'yes' &&
                $this->debit_order_type != '' &&
                $this->transaction_type != 'PAYMENT' &&
                $this->transaction_type != 'RESERVE'
            ) {
                $transaction_record = [];
                $transaction_record['statementDescription'] = $txn_data['Basket']['description'];
                $transaction_record['managedBy'] = 'MERCHANT';

                if (is_user_logged_in()) {
                    $transaction_record['anonymousUser'] = 'false';
                } else {
                    $transaction_record['anonymousUser'] = 'true';
                }

                // Add Transaction Record
                $txn_data = array_merge($txn_data, ['TransactionRecord' => $transaction_record]);
                $transaction_record = null;
                unset($transaction_record);
            }

            return $txn_data;
        }

        private function is_payment_new(): bool
        {
            return $this->txn_data->successful
                && $this->get_transaction_state() === 'NEW';
        }

        private function is_payment_successful(): bool
        {
            return $this->txn_data->successful
                && $this->get_transaction_state() === 'SUCCESSFUL';
        }

        /**
         * @return bool
         */
        private function is_payment_pending(): bool
        {
            return $this->txn_data->successful
                && $this->get_transaction_state() === 'AWAITING_PAYMENT';
        }

        /**
         * @return bool
         */
        private function is_payment_processing(): bool
        {
            return ($this->txn_data->successful === true || $this->txn_data->successful === false)
                && $this->get_transaction_state() === 'PROCESSING';
        }

        /**
         * @return bool
         */
        private function is_payment_failed(): bool
        {
            return ($this->txn_data->successful === true || $this->txn_data->successful === false)
                && in_array(
                    $this->get_transaction_state(),
                    ['FAILED', 'EXPIRED', 'TIMEOUT']
                );
        }

        private function get_tranx_id(): string
        {
            return $this->txn_data->payUReference ?? '';
        }

        private function get_merchant_reference(): string
        {
            return $this->txn_data->merchantReference ?? '';
        }

        /**
         * @return bool
         */
        private function has_payment_method(): bool
        {
            return property_exists($this->txn_data, 'paymentMethodsUsed');
        }

        private function get_payment_method(): stdClass|array
        {
            return $this->has_payment_method() ? $this->txn_data->paymentMethodsUsed : null;
        }

        /**
         * @return bool
         */
        private function is_payment_method_cc(): bool
        {
            return $this->has_payment_method() && $this->check_payment_method_cc();
        }

        private function check_payment_method_cc(): bool
        {
            $payment_methods = $this->get_payment_method();

            if (is_array($payment_methods)) {
                foreach ($payment_methods as $method) {
                    if (property_exists($method, 'gatewayReference')) {
                        return true;
                    }
                }
            } else {
                if (property_exists($payment_methods, 'gatewayReference')) {
                    return true;
                }
            }

            return false;
        }

        /**
         * @return string
         */
        private function get_gateway_reference(): string
        {
            $gateway_reference = 'N/A';
            $payment_methods = $this->get_payment_method();

            if (is_array($payment_methods)) {
                foreach ($payment_methods as $method) {
                    if (property_exists($method, 'gatewayReference')) {
                        $gateway_reference = $method->gatewayReference;
                    }
                }
            } else {
                if (property_exists($payment_methods, 'gatewayReference')) {
                    $gateway_reference = $payment_methods->gatewayReference;
                }
            }

            return $gateway_reference;
        }

        /**
         * @return string
         */
        private function get_cc_number(): string
        {
            $card_number = 'N/A';
            $has_cc_number = $this->has_payment_method() && $this->is_payment_method_cc();

            if ($has_cc_number) {
                $payment_methods = $this->get_payment_method();

                if (is_array($payment_methods)) {
                    foreach ($payment_methods as $method) {
                        if (property_exists($method, 'cardNumber')) {
                            $card_number = $method->cardNumber;
                        }
                    }
                } else {
                    if (property_exists($payment_methods, 'cardNumber')) {
                        $card_number = $payment_methods->cardNumber;
                    }
                }
            }

            return $card_number;
        }

        private function total_captured(): float|int
        {
            $total = 0;

            if ($this->is_payment_new()) {
                return $total;
            }

            $payment_methods = $this->get_payment_method();

            if (!$payment_methods) {
                return $total;
            }

            if (is_a($payment_methods, stdClass::class, true) &&
                !property_exists($payment_methods, 'amountInCents')
            ) {
                return $total;
            }

            if (is_a($payment_methods, stdClass::class, true) &&
                property_exists($payment_methods, 'amountInCents')
            ) {
                return $payment_methods->amountInCents / 100;
            }

            foreach ($payment_methods as $payment_method) {
                $total += $payment_method->amountInCents;
            }

            // Prevent division by zero
            return max($total, 1) / 100;
        }

        private function get_transaction_state(): string
        {
            return isset($this->txn_data->transactionState) ?
                $this->txn_data->transactionState :
                '';
        }

        private function get_point_of_failure(): string
        {
            return $this->txn_data->pointOfFailure ?? '';
        }

        private function get_recurring_details(): ?array
        {
            return isset($this->txn_data->recurringDetails) ? $this->txn_data->recurringDetails : null;
        }

        private function get_total_due(): int
        {
            return isset($this->txn_data->basket) ? (int)$this->txn_data->basket->amountInCents : 0;
        }

        private function get_result_code(): string
        {
            return $this->txn_data->resultCode ?? '';
        }

        private function get_display_message(): string
        {
            return $this->txn_data->displayMessage ?? '';
        }

        private function get_payment_method_details(string $transaction_notes): string
        {
            if ($this->has_payment_method()) {
                $transaction_notes .= "<br /><br />Payment Method Details:";
                $payment_methods = $this->get_payment_method();

                if (!is_array($payment_methods)) {
                    $payment_methods = [$payment_methods];
                }

                foreach ($payment_methods as $type => $payment_method) {
                    $transaction_notes .= "<br />=== Method " . $type . "===";
                    foreach ($payment_method as $key => $value) {
                        $transaction_notes .= "<br />&nbsp;&nbsp;=> " . $key . ": " . $value;
                    }
                    $transaction_notes .= '<br />';
                }
            }

            return $transaction_notes;
        }
    }

    /**
     * Add the Gateway to WooCommerce
     *
     */
    function woocommerce_add_payu_rpp($methods) {
        $methods[] = 'WC_Gateway_PayU_RPP';
        return $methods;
    }

    // add the payment gateway to WooCommerce using filter woocommerce_payment_gateways
    add_filter('woocommerce_payment_gateways', 'woocommerce_add_payu_rpp');
}
