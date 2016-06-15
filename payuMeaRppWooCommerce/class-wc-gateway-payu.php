<?php
	/**
	 * class-wc-gateway-payu.php
	 *
	 * Copyright (coffee) 2012-2013 PayU MEA (Pty) Ltd
	 * 
	 * LICENSE:
	 * 
	 * This payment module is free software; you can redistribute it and/or modify
	 * it under the terms of the GNU Lesser General Public License as published
	 * by the Free Software Foundation; either version 3 of the License, or (at
	 * your option) any later version.
	 * 
	 * This payment module is distributed in the hope that it will be useful, but
	 * WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY
	 * or FITNESS FOR A PARTICULAR PURPOSE. See the GNU Lesser General Public
	 * License for more details.
	 * 
	 * @author     Warren Roman/Ramiz Mohamed
	 * @copyright  2011-2013 PayU Payment Solutions (Pty) Ltd	 	 	 
	 */	
	

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/**
Plugin Name: WooCommerce - PayU MEA Payment Gateway (Redirect)
Plugin URI: http://help.payu.co.za/display/developers/WooCommerce
Description: Enables WooCommerce customers to do payments using PayU MEA (Middle East and Africa) as a payment gateway
Version: 1.3
Author: PayU MEA
Author URI: http://www.payu.co.za
*/

add_action( 'plugins_loaded', 'init_your_gateway_class',0 );

function init_your_gateway_class() {

	if ( !class_exists( 'WC_Payment_Gateway' ) ) return;
	
	class WC_Gateway_PayU extends WC_Payment_Gateway {
	
		/**
		 * Constructor for the gateway.
		 *
		 * @access public
		 * @return void
		 */	
		public function __construct(){
		global $woocommerce;		

		$this->id = 'payu';
		//$this->icon = apply_filters( 'woocommerce_payu_icon', $woocommerce->plugin_url() . '/assets/images/icons/payu_mea_logo.png' );	
		$this->icon = WP_PLUGIN_URL . "/" . plugin_basename( dirname(__FILE__)) . '/images/payu_mea_logo.png';
		$this->has_fields = true;
		$this->produrl = 'https://secure.payu.co.za';
		$this->stagingurl = 'https://staging.payu.co.za';	
		$this->method_title = __( 'PayU MEA (Redirect)', 'woocommerce' );
		$this->notify_url = str_replace( 'https:', 'http:', add_query_arg( 'wc-api', 'WC_Gateway_PayU', home_url( '/' ) ) );
	
		// Load the settings.	
		$this-> init_form_fields();
		$this-> init_settings();
	
		// Define user set variables
		$this->title = $this->get_option( 'title' );
		$this->description = $this->get_option('description');
		$this->safekey = $this->get_option('safekey'); 
		$this->username = $this->get_option('username'); 
		$this->password = $this->get_option('password'); 
		$this->testmode	= $this->get_option( 'testmode' );
		$this->payment_method = $this->get_option( 'payment_method' );
		$this->transaction_type = $this->get_option( 'transaction_type' );
		$this->debit_order_enabled = $this->get_option( 'debit_order_enabled' );
		$this->debit_order_type = $this->get_option('debit_order_type');
		$this->enable_logging = $this->get_option('enable_logging');
		$this->extended_debug = $this->get_option('extended_debug');
		$this->debug = $this->get_option( 'debug' );
		//$this->currency = $this->get_option( 'currency' );
	
		// Logs
		if ( 'yes' == $this->debug )
			$this->log = $woocommerce->logger();	
	
		// Add Actions
		// create admin panel for settings.
		if ( version_compare( WOOCOMMERCE_VERSION, '2.0.0', '>=' ) ) {
			add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( &$this, 'process_admin_options' ) );
		} else {
			add_action( 'woocommerce_update_options_payment_gateways', array( &$this, 'process_admin_options' ) );
		}
	
		// Payment listener/API hook
		add_action('woocommerce_api_wc_gateway_payu', array(&$this, 'check_payu_response'));	
	
		if ( !$this->is_valid_for_use() ) $this->enabled = false;				
		
		}

		/**
		 * Check if this gateway is enabled and available in the user's country
		 *
		 * @access public
		 * @return bool
		 */
		function is_valid_for_use() {
				if ( ! in_array( get_woocommerce_currency(), apply_filters( 'woocommerce_paypal_supported_currencies', array( 'AUD', 'BRL', 'CAD', 'MXN', 'NZD', 'HKD', 'SGD', 'USD', 'EUR', 'JPY', 'TRY', 'NOK', 'CZK', 'DKK', 'HUF', 'ILS', 'MYR', 'PHP', 'PLN', 'SEK', 'CHF', 'TWD', 'THB', 'GBP', 'RMB','ZAR', 'NGN' ) ) ) ) return false;

			return true;
		}
	
		/**
		 * Admin Panel Options
		 * - Options for bits like 'title' and availability on a country-by-country basis
		 *
		 * @since 1.0.0
		 */	
	
		public function admin_options(){
		?>
		<h3><?php _e( 'PayU MEA (Redirect)', 'woocommerce' ); ?></h3>
		<p><?php _e( 'PayU Redirect Payment Page works by sending the user to PayU to enter their payment information.', 'woocommerce' ); ?></p>

		<?php if ( $this->is_valid_for_use() ) : ?>

			<table class="form-table">
			<?php
    			// Generate the HTML For the settings form.
    			$this->generate_settings_html();
			?>
			</table><!--/.form-table-->

		<?php else : ?>
			<div class="inline error"><p><strong><?php _e( 'Gateway Disabled', 'woocommerce' ); ?></strong>: <?php _e( 'PayU does not support your store currency.', 'woocommerce' ); ?></p></div>
		<?php
			endif;
		}	

		/**
		 * Initialise Gateway Settings Form Fields
		 *
		 * @access public
		 * @return void
		 */
		function init_form_fields(){
		
           // debit order transaction options
           $dorder_tx_options = array(	'DEBIT_ORDER' => __('DEBIT_ORDER', 'woocommerce'), 
										'ONCE_OFF_PAYMENT_AND_DEBIT_ORDER' => __('ONCE_OFF_PAYMENT_AND_DEBIT_ORDER', 'woocommerce'),
										'ONCE_OFF_RESERVE_AND_DEBIT_ORDER' => __('ONCE_OFF_RESERVE_AND_DEBIT_ORDER', 'woocommerce'));		

		   $this -> form_fields = array(
					'enabled' => array(
							'title' => __('Enable payment gateway option.', 'woocommerce'),
							'type' => 'checkbox',
							'label' => __('', 'woocommerce'),
							'default' => 'no'),
					'testmode' => array(
							'title' => __( 'Use staging/sandbox environment', 'woocommerce' ),
							'type' => 'checkbox',
							'label' => __( '(ticked = staging/sandbox, unticked = production)', 'woocommerce' ),
							'default' => 'yes',
							'description' => __( 'Which PayU environment to use for transactions.', 'woocommerce' )
					),
					'title' => array(
							'title' => __('Title:', 'woocommerce'),
							'type'=> 'text',
							'description' => __('This controls the title which the user sees during checkout.', 'woocommerce'),
							'default' => __('PayU', 'woocommerce'),
							'desc_tip'      => true,
					),
					'credit_card_subtitle'   => array(
						'title' => __('Credit Card Payment Option Title:', 'woocommerce'),
						'type'=> 'text',
						'description' => __('This controls the credit title which the user sees during checkout.', 'woocommerce'),
						'default' => __('Credit Card', 'woocommerce'),
						'desc_tip'      => true,
					),
					'description' => array(
							'title' => __('Description:', 'woocommerce'),
							'type' => 'textarea',
							'description' => __('This controls the description which the user sees during checkout.', 'woocommerce'),
							'default' => __('PayU Credit Card.', 'PayU')
					),
					'safekey' => array(
							'title' => __('SafeKey', 'woocommerce'),
							'type' => 'text',
							'description' =>  __('Given to Merchant by PayU', 'woocommerce')
					),
					'username' => array(
							'title' => __('SOAP Username', 'PayU'),
							'type' => 'text',
							'description' =>  __('Given to Merchant by PayU', 'woocommerce')
					),
					'password' => array(
							'title' => __('SOAP Password', 'PayU'),
							'type' => 'text',
							'description' =>  __('Given to Merchant by PayU', 'woocommerce')
					),
					'currency' => array(
							'title' => __('Currency', 'PayU'),
							'type' => 'text',
							'description' =>  __('Supported Currencies', 'woocommerce'),	
							'default' => __('ZAR', 'PayU')
					),
					'payment_method' => array(
							'title' => __('Payment Method', 'woocommerce'),
							'type' => 'text',
							'description' =>  __('Supported Payment Methods', 'woocommerce'),	
							'default' => __('CREDITCARD', 'PayU')
					),
					'transaction_type' => array(
							'title' => __('Transaction Type', 'woocommerce'),
							'type' => 'text',
							'description' =>  __('Supported Transaction Types', 'woocommerce'),	
							'default' => __('PAYMENT', 'PayU')
					),
					'debit_order_enabled' => array(
							'title' => __('Enable Credit Card Debit Order Payments (recurring)', 'woocommerce'),
							'type' => 'checkbox',
							'label' => __('Enable Debit Order Payments.', 'woocommerce'),
							'description' =>  __('If enabled, select the appropriate type below', 'woocommerce'),
							'default' => 'no'),					
					'debit_order_type'   => array(
									'title' => __('Credit Card Debit Order Transaction Type', 'woocommerce'),
									'type' => 'select',
									'options' => $dorder_tx_options,
									'description' => __( 'Select the Debit Order Transaction Type.', 'woocommerce' )
								),					
					'recurring_subtitle'   => array(
						'title' => __('Credit Card Debit Order Payment Option Title:', 'woocommerce'),
						'type'=> 'text',
						'description' => __('This controls the recurring title which the user sees during checkout.', 'woocommerce'),
						'default' => __('Credit Card Debit Order', 'woocommerce'),
						'desc_tip'      => true,
					),
					
					
					/*'debug' => array(
							'title' => __( 'Debug Log', 'woocommerce' ),
							'type' => 'checkbox',
							'label' => __( 'Enable logging', 'woocommerce' ),
							'default' => 'no',
							'description' => __( 'Log PayU events, such as IPN requests, inside <code>woocommerce/logs/payu-%s.txt</code>', 'woocommerce' )
					),	
					'enable_logging' => array(
							'title' => __( 'Payu Logging', 'woocommerce' ),
							'type' => 'checkbox',
							'label' => __( 'Enable PayU logging', 'woocommerce' ),
							'default' => 'no',
							'description' => __( 'Log PayU events, such as IPN requests at PayU servers', 'woocommerce' )
					),	
					'extended_debug' => array(
							'title' => __( 'Extended Debug Enable', 'woocommerce' ),
							'type' => 'checkbox',
							'label' => __( 'Enable PayU extended logging', 'woocommerce' ),
							'default' => 'no',
							'description' => __( 'Log PayU events, such as IPN requests at PayU servers', 'woocommerce' )
					)*/																											
				);
		}
	


		/**
		 *  There are no payment fields for payu, but we want to show the description if set.
		 *  As this is an RPP impementation
		 **/
		function payment_fields(){
		if($this -> description) echo wpautop(wptexturize($this -> description));
		?>			
			<input type="radio" name="payu_transaction_type" id="payu_transaction_type" value="default" checked><?php print $this->settings['credit_card_subtitle'];?><br>
			<?php		if ( $this->debit_order_enabled == 'yes' && $this->debit_order_type != '') :?>
			<input type="radio" name="payu_transaction_type" id="payu_transaction_type" value="recurring" ><?php print $this->settings['recurring_subtitle'];?><br>
			<?php endif;
			
		}
		
		
		/**
		 * Process the payment and return the result
		 */
		function process_payment($order_id){
			global $woocommerce;
			//require('library.payu/classes/class.PayuRedirectPaymentPage.php');
			require(WP_PLUGIN_DIR . "/" . plugin_basename( dirname(__FILE__)) . '/library.payu/classes/class.PayuRedirectPaymentPage.php');
			$redirectapi = '/rpp.do?PayUReference=';
			
			//$this->params[$paymentType] = $_POST['payu_transaction_type']; 
			$transactionTypeSelection = $_POST['payu_transaction_type'];
			//$debit_order_type_select = "";
			//$debit_order_type_select .= "<option value='" . $month . "' " . $select . ">" . $month . "</option>\n";
			
			try {	
				// get order data	
				$order = new WC_Order($order_id);
		
				//if ( $this->testmode == 'yes' ):
				//	$payu_adr = $this->stagingurl;
				//else :
				//	$payu_adr = $this->produrl;
				//endif;	
				
				//var_dump($this->settings['testmode']);
				//die();
			
				
				if($this->settings['testmode'] == "no") {		
					$prod = 1;
					$payu_adr = $this->produrl;
					
					//$soapUsername = $this->settings['username'];
					//$soapPassword = $this->settings['password'];
					//$safeKey = $this->settings['safekey'];
				}			
				else {
					$payu_adr = $this->stagingurl;
					
					//require_once(WP_PLUGIN_DIR . "/" . plugin_basename( dirname(__FILE__)) . '/library.payu/inc.demo/config.demo.php');
					//$soapUsername = $rpp['username'];
					//$soapPassword = $rpp['password'];
					//$safeKey = $rpp['Safekey'];										
					
					//require_once('library.payu/inc.demo/config.demo.php');						
					//$rpp['username'] = 'Staging Integration Store 1';
					//$rpp['password'] = '78cXrW1W';
					//$rpp['Safekey'] = '{45D5C765-16D2-45A4-8C41-8D6F84042F8C}';					
				}	

				$soapUsername = $this->settings['username'];
				$soapPassword = $this->settings['password'];
				$safeKey = $this->settings['safekey'];				
				
				$setTransactionSoapDataArray = array();
				$setTransactionSoapDataArray['Safekey'] = $safeKey;
				
				if ($transactionTypeSelection == "default") {          
					$setTransactionSoapDataArray['TransactionType'] = $this->transaction_type;	      
				}
				else {
					$this->transaction_type = $this->debit_order_type;
					$setTransactionSoapDataArray['TransactionType'] = $this->transaction_type;	
				}
				
				//$setTransactionSoapDataArray['TransactionType'] = $this->transaction_type;			
				
				// Create Customer array
				$customerSubmitArray = array();
				if(isset($customerDataArray['billFirstName'])) {
					$customerSubmitArray['firstName'] = $order-> billing_first_name;
				}
				else {
					$customerSubmitArray['firstName'] = $order-> shipping_first_name;
				}
        
				if(isset($customerDataArray['billLastName'])) {
					$customerSubmitArray['lastName'] = $order-> billing_last_name;
				}
				else {
					$customerSubmitArray['lastName'] = $order-> shipping_last_name;
				}
        
				$customerSubmitArray['mobile'] = $order-> billing_phone;

        
				$customerSubmitArray['email'] = $order-> billing_email;
				
				if (!is_user_logged_in() ) {
					$current_user = wp_get_current_user();
					$customerSubmitArray['merchantUserId'] = $current_user->ID;					
				}
				
				
				// Add Customer Array to Soap Data Array
				$setTransactionSoapDataArray = array_merge($setTransactionSoapDataArray, array('Customer' => $customerSubmitArray ));
				$customerSubmitArray = null; unset($customerSubmitArray);		
				
				// Create Order array
		
				// Create Basket Array
				$basketArray = array();
				$woocommerceFormat = $order->get_total();
				$floatAmount = $woocommerceFormat * 100;
				$basketArray['amountInCents'] = (int) $floatAmount;
				$basketArray['description'] = "Order No:".(string)$order_id;               
				//$basketArray['currencyCode'] = get_woocommerce_currency();
				$basketArray['currencyCode'] = $safeKey = $this->settings['currency'];
				
				

				$setTransactionSoapDataArray = array_merge($setTransactionSoapDataArray, array('Basket' => $basketArray ));
				$basketArray = null; unset($basketArray);			
		
				// Additional Info array
				$additionalInformationArray = array();
				$additionalInformationArray['supportedPaymentMethods'] = $this->payment_method;
				//$additionalInformationArray['cancelUrl'] = $order->get_cancel_order_url();
				$additionalInformationArray['cancelUrl'] = $this->notify_url."&order_id=".$order_id;
				//$additionalInformationArray['notificationUrl'] = get_option('payuRedirect_notificationURL' );
				$additionalInformationArray['returnUrl'] = $this->notify_url."&order_id=".$order_id;
				$additionalInformationArray['merchantReference'] = (string)$order_id;

				if (!is_user_logged_in() ) {										
					$additionalInformationArray['callCenterRepId'] = "Unknown";
				}
				
				// Add Additionnal Info Array to Soap Data Array
				$setTransactionSoapDataArray = array_merge($setTransactionSoapDataArray, array('AdditionalInformation' => $additionalInformationArray ));
				$additionalInformationArray = null; unset($additionalInformationArray);		
								
				// Transaction record array
				if ( $this->debit_order_enabled == 'yes' && $this->debit_order_type != ''){			
					if ($this->transaction_type != 'PAYMENT'){	
						if ($this->transaction_type != 'RESERVE'){
							$transactionRecordArray = array();
							$transactionRecordArray ['statementDescription'] = $setTransactionSoapDataArray['Basket']['description'];
							$transactionRecordArray ['managedBy'] = 'MERCHANT';
							if ( is_user_logged_in() ) {
								$transactionRecordArray ['anonymousUser'] = 'false' ;
							}
							else {
								$transactionRecordArray ['anonymousUser'] = 'true' ;
							}
							
							// Add Transaction Record Array to Soap Data Array
							$setTransactionSoapDataArray = array_merge($setTransactionSoapDataArray, array('TransactionRecord' => $transactionRecordArray ));
							$transactionRecordArray = null; unset($transactionRecordArray);	
						}
					}
				}
				
				// Creating a constructor array for RPP instantiation
				$constructorArray = array();
				$constructorArray['username'] = $soapUsername;
				$constructorArray['password'] = $soapPassword;
				$constructorArray['logEnable'] = $this->enable_logging;
				$constructorArray['extendedDebugEnable'] = $this->extended_debug;	
				
				//var_dump($constructorArray);
				//var_dump($setTransactionSoapDataArray);
				//die();
				
				

				if(isset($prod)) {
					$constructorArray['production'] = true;
				}
				
				if(strtolower($constructorArray['logEnable']) == "yes") {
					$constructorArray['logEnable'] = true;
				} 
				else {
					$constructorArray['logEnable'] = false;
				}
				if(strtolower($constructorArray['extendedDebugEnable']) == "yes") {
					$constructorArray['extendedDebugEnable'] = true;
				}
				else {
					$constructorArray['extendedDebugEnable'] = false;
				}							
			
				//var_dump($setTransactionSoapDataArray);
			
				// Do setTransaction
				$payuRppInstance = new PayuRedirectPaymentPage($constructorArray);
				$setTransactionResponse = $payuRppInstance->doSetTransactionSoapCall($setTransactionSoapDataArray);	

				//var_dump($setTransactionResponse);
			
				// Retrieve setTransaction response 			
				if(isset($setTransactionResponse['redirectPaymentPageUrl'])) {
            
					if( isset($setTransactionResponse['soapResponse']['payUReference'])) {
						$payUReference = $setTransactionResponse['soapResponse']['payUReference'];
						$setTransactionNotes = "PayU Reference: ".$setTransactionResponse['soapResponse']['payUReference'];
						$order->add_order_note( __( 'Redirecting to PayU, '. $setTransactionNotes, 'woocommerce' ) );
						// Processing Payment
						$order->update_status( 'pending', '', 'woocommerce' );	
						//$order->update_status( 'processing', 'Processing payment:'. $setTransactionNotes, 'woocommerce' );                
					}
				}
			}	
			catch(Exception $e) {
				//Place error code here 
				
				var_dump($e);
				die();
				$exceptionErrorString = $e->getMessage(); 
				if(!empty($exceptionErrorString)) {
					$errorMessage = ' - '.$exceptionErrorString."<br /><br />";
					wp_die($errorMessage);
				}
			}  	
			
			return array(
					'result' 	=> 'success',
					'redirect'	=> $setTransactionResponse['redirectPaymentPageUrl']
					);	
			/*
			return array(
					'result' 	=> 'success',
					'redirect'	=> $payu_adr . $redirectapi . $payUReference
					);	
			*/
			}
	
		/**
		 * Check for valid payu server callback
		 **/
		function check_payu_response(){
			global $woocommerce;

			$payment_page = get_permalink( woocommerce_get_page_id( 'pay' ) ); 
			// make ssl if needed
			if ( get_option( 'woocommerce_force_ssl_checkout' ) == 'yes' ) {
				$payment_page = str_replace( 'http:', 'https:', $payment_page );
			}
			
			if(isset($_GET['cancelled']) && !empty($_GET['cancelled']) && isset($_GET['order_id'])){
				
				$order = new WC_Order($_GET['order_id']);								
				
				$transactionNotes = "Payment Cancelled";
				//$woocommerce->add_error(__('', 'woothemes') . $transactionNotes);	
				wc_add_notice(__('', 'woothemes') . $transactionNotes, 'error');			
				$order->add_order_note( __( 'Payment cancelled:'. $transactionNotes, 'woocommerce' ) );								
				if ( 'yes' == $this->debug ) {
					$this->log->add( 'PayU', 'Payment cancelled.' );
				}
				wp_redirect( $payment_page );
				die();
			}
			elseif(isset($_GET['PayUReference']) && !empty($_GET['PayUReference'])){
				//die('sadasdasd');
				$payUReference = $_GET['PayUReference'];
          
				//require('library.payu/classes/class.PayuRedirectPaymentPage.php');
				require(WP_PLUGIN_DIR . "/" . plugin_basename( dirname(__FILE__)) . '/library.payu/classes/class.PayuRedirectPaymentPage.php');
			
				//Setting a default failed trasaction state for this trasaction
				$transactionState = "failure";
				try {
					//Creating get transaction soap data array
					$getTransactionSoapDataArray = array();
				
					if($this->settings['testmode'] == "no") {		
						$prod = 1;
						$payu_adr = $this->produrl;
						
						//$soapUsername = $this->settings['username'];
						//$soapPassword = $this->settings['password'];
						//$safeKey = $this->settings['safekey'];
					}
					else {
						require_once('library.payu/inc.demo/config.demo.php');	
						$payu_adr = $this->stagingurl;		
						
						//require_once(WP_PLUGIN_DIR . "/" . plugin_basename( dirname(__FILE__)) . '/library.payu/inc.demo/config.demo.php');
						//require_once('library.payu/inc.demo/config.demo.php');	
						
						//$rpp['username'] = 'Staging Integration Store 1';
						//$rpp['password'] = '78cXrW1W';
						//$rpp['Safekey'] = '{45D5C765-16D2-45A4-8C41-8D6F84042F8C}';						
						//$soapUsername = $rpp['username'];
						//$soapPassword = $rpp['password'];
						//$safeKey = $rpp['Safekey'];			
					}
					
					$soapUsername = $this->settings['username'];
					$soapPassword = $this->settings['password'];
					$safeKey = $this->settings['safekey'];
								
					$getTransactionSoapDataArray['Safekey'] =  $safeKey;
					$getTransactionSoapDataArray['AdditionalInformation']['payUReference'] = $payUReference;        

					//Creating constructor array for the payURedirect and instantiating 
					$constructorArray = array();
					$constructorArray['username'] = $soapUsername;
					$constructorArray['password'] = $soapPassword;    
					$constructorArray['logEnable'] = $this->enable_logging;
					$constructorArray['extendedDebugEnable'] = $this->extended_debug;
					if(strtolower($constructorArray['logEnable']) == "yes") {
						$constructorArray['logEnable'] = true;
					} 
					else {
						$constructorArray['logEnable'] = false;
					}
					if(strtolower($constructorArray['extendedDebugEnable']) == "yes") {
						$constructorArray['extendedDebugEnable'] = true;
					} 
					else {
						$constructorArray['extendedDebugEnable'] = false;
					}
					
					
				    
					if(isset($prod)) {
						$constructorArray['production'] = true;
					}
					
					$payuRppInstance = new PayuRedirectPaymentPage($constructorArray);
					$getTransactionResponse = $payuRppInstance->doGetTransactionSoapCall($getTransactionSoapDataArray); 
            
					//Set merchant reference
					if( isset($getTransactionResponse['soapResponse']['merchantReference']) && !empty($getTransactionResponse['soapResponse']['merchantReference']) ) {
						$order_id = $getTransactionResponse['soapResponse']['merchantReference'];
					}
				
					$order = new WC_Order($order_id);
					
					//if ( ! isset( $order->id ) ) {
						// We have an invalid $order_id, probably because invoice_prefix has changed
					//	$order_id 	= woocommerce_get_order_id_by_order_key( $order_key );
					//	$order 		= new WC_Order( $order_id );
					//}				
					
					
				
					//Checking the response from the SOAP call to see if successfull
					if(isset($getTransactionResponse['soapResponse']['successful']) && ($getTransactionResponse['soapResponse']['successful']  === true)) {

						//if(isset($getTransactionResponse['soapResponse']['transactionType']) && (strtolower($getTransactionResponse['soapResponse']['transactionType']) == 'reserve') ) {
							if(isset($getTransactionResponse['soapResponse']['transactionState']) && (strtolower($getTransactionResponse['soapResponse']['transactionState']) == 'successful') ) {                    
								//$transactionState = "reserve"; //funds reserved need to finalize in the admin box    
							
								$transactionNotes = "PayU Reference: ".$getTransactionResponse['soapResponse']['payUReference']."<br /> ";
								if(isset($getTransactionResponse['soapResponse']['paymentMethodsUsed'])) {
									if(is_array($getTransactionResponse['soapResponse']['paymentMethodsUsed'])) {										
										$transactionNotes .= "<br /><br />Payment Method Details:";
										foreach($getTransactionResponse['soapResponse']['paymentMethodsUsed'] as $key => $value) {
											$transactionNotes .= "<br />&nbsp;&nbsp;- ".$key.":".$value." , ";
										}
									}
								}
								
								if(isset($getTransactionResponse['soapResponse']['recurringDetails'])) {
									if(is_array($getTransactionResponse['soapResponse']['recurringDetails'])) {
										$transactionNotes .= "<br /><br />Recurring Details:";
										foreach($getTransactionResponse['soapResponse']['recurringDetails'] as $key => $value) {
											$transactionNotes .= "<br />&nbsp;&nbsp;- ".$key.":".$value." , ";
										}
									}
								}								
								
								
								//var_dump($transactionNotes);
								//die();
								/*
								if ( $this->debit_order_enabled == 'yes' && $this->debit_order_type != ''){
									if ($getTransactionResponse['soapResponse']['paymentType'] != 'PAYMENT'){	
										if ($getTransactionResponse['soapResponse']['paymentType'] != 'RESERVE'){								
											$transactionNotes = $transactionNotes. ", PmId: ".$getTransactionResponse['soapResponse']['pmId'];
										}
									}	
								}	    
								*/
								
								// Check order not already completed
								if ( $order->status == 'completed' ) {
										if ( 'yes' == $this->debug )
											$this->log->add( 'PayU', 'Aborting, Order #' . $order->id . ' is already complete.' );									
										exit;
								}
							
								// Validate amount	
							
								// Payment completed
								$order->add_order_note( __( 'Payment completed: <br />'. $transactionNotes, 'woocommerce' ) );
								$order->payment_complete();		
								$woocommerce->cart->empty_cart();	
								if ( 'yes' == $this->debug )
									$this->log->add( 'PayU', 'Payment complete.' );										

								wc_add_notice( __( 'Payment completed: <br />'. $transactionNotes, 'woocommerce' ), 'success');						
								wp_redirect( $this->get_return_url( $order ) );
								exit;												                
							}            
						//}
						/*if(isset($getTransactionResponse['soapResponse']['transactionType']) && (strtolower($getTransactionResponse['soapResponse']['transactionType']) == 'payment') ) {                    
							if(isset($getTransactionResponse['soapResponse']['transactionState']) && (strtolower($getTransactionResponse['soapResponse']['transactionState']) == 'successful') ) {                    
								$transactionState = "paymentSuccessfull"; //funds reserved need to finalize in the admin box

								//Now doing updates for the orders 
								//if ($transactionState == "paymentSuccessfull")
								//{
									//Payment Successful
									$transactionNotes = "PayU Reference: ".$getTransactionResponse['soapResponse']['payUReference'].", GatewayReference: ".$getTransactionResponse['soapResponse']['paymentMethodsUsed']['gatewayReference'];              
	            				
									// Check order not already completed
									if ( $order->status == 'completed' ) {
	            							if ( 'yes' == $this->debug )
	            		 						$this->log->add( 'PayU', 'Aborting, Order #' . $order->id . ' is already complete.' );									
	            							exit;
									}
								
									// Validate amount	
								
	            					// Payment completed
									$order->add_order_note( __( 'Payment completed:'. $transactionNotes, 'woocommerce' ) );
									$order->payment_complete();		
									$woocommerce -> cart -> empty_cart();	
									if ( 'yes' == $this->debug )
	                					$this->log->add( 'PayU', 'Payment complete.' );																
  									wp_redirect( $this->get_return_url( $order ) );
  									exit;							
							}
							/*else if(isset($getTransactionResponse['soapResponse']['transactionState']) && (strtolower($getTransactionResponse['soapResponse']['transactionState']) == 'failure'))
							{
								$transactionState == "failure";

								//Payment Failed            
								$transactionNotes = "PayU Reference: ".$getTransactionResponse['soapResponse']['payUReference'].", Error: ".addslashes($errorMessage).", Point Of Failure: ".$getTransactionResponse['soapResponse']['pointOfFailure'].", Result Code:".$getTransactionResponse['soapResponse']['resultCode'] ;            
								
								$order->add_order_note( __( 'Payment unsuccessful:'. $transactionNotes, 'woocommerce' ) );
								// Order failed
								$order->update_status( 'failed', 'Payment unsuccessful:'. $transactionNotes, 'woocommerce' );
								if ( 'yes' == $this->debug )
	                				$this->log->add( 'PayU', 'Payment failed.' );																
  								wp_redirect( $this->get_return_url( $order ) );
  								exit;								
							}*/							            
						//}
						else {
							/*						
							$order->add_order_note( __( 'Payment unsuccessful:'. $transactionNotes, 'woocommerce' ) );
							// Order failed
							$order->update_status( 'failed', 'Payment unsuccessful:'. $transactionNotes, 'woocommerce' );
							if ( 'yes' == $this->debug )
								$this->log->add( 'PayU', 'Payment failed.' );																
							wp_redirect( $this->get_return_url( $order ) );
							*/
							
							$reason = $getTransactionResponse['soapResponse']['displayMessage'];
							$transactionNotes = "PayU Reference: ".$getTransactionResponse['soapResponse']['payUReference'].", Error: ".addslashes($errorMessage).", Point Of Failure: ".$getTransactionResponse['soapResponse']['pointOfFailure'].", Result Code:".$getTransactionResponse['soapResponse']['resultCode'] ;            						
							//$woocommerce->add_error(__('Payment Failed:', 'woothemes') . $reason);	
							wc_add_notice(__('Payment Failed:', 'woothemes') . $reason, 'error');			
							$order->add_order_note( __( 'Payment unsuccessful:'. $transactionNotes, 'woocommerce' ) );								
							if ( 'yes' == $this->debug ) {
								$this->log->add( 'PayU', 'Payment Failed.' );
							}
							wp_redirect( $payment_page );						
							exit;					
						}						
					}
					else {
					
						
						/*						
						$order->add_order_note( __( 'Payment unsuccessful:'. $transactionNotes, 'woocommerce' ) );
						// Order failed
						$order->update_status( 'failed', 'Payment unsuccessful:'. $transactionNotes, 'woocommerce' );
						if ( 'yes' == $this->debug )
	                		$this->log->add( 'PayU', 'Payment failed.' );																
  						wp_redirect( $this->get_return_url( $order ) );
						*/
						
						$reason = $getTransactionResponse['soapResponse']['displayMessage'];
						$transactionNotes = "PayU Reference: ".$getTransactionResponse['soapResponse']['payUReference'].", Error: ".addslashes($errorMessage).", Point Of Failure: ".$getTransactionResponse['soapResponse']['pointOfFailure'].", Result Code:".$getTransactionResponse['soapResponse']['resultCode'] ;            						
						//$woocommerce->add_error(__('Payment Failed:', 'woothemes') . $reason);	
						wc_add_notice(__('Payment Failed:', 'woothemes') . $reason, 'error');			
						$order->add_order_note( __( 'Payment unsuccessful:'. $transactionNotes, 'woocommerce' ) );								
						if ( 'yes' == $this->debug ) {
							$this->log->add( 'PayU', 'Payment Failed.' );
						}
						wp_redirect( $payment_page );						
  						exit;					
					}
				}
				catch(Exception $e) {
					$errorMessage = $e->getMessage();  
					wp_die($errorMessage);
				}
			}
			else {
				
				/*
				$checkout_url = $woocommerce->cart->get_checkout_url();
				wp_redirect( $this->get_return_url( $order ) );				
				$transactionNotes = "Dunno what happened, please contact merchant;";
				$order->add_order_note( __( 'Payment unsuccessful:'. $transactionNotes, 'woocommerce' ) );
				// Order failed
				$order->update_status( 'failed', $transactionNotes );
				if ( 'yes' == $this->debug )
					$this->log->add( 'PayU', $transactionNotes );																
				wp_redirect( $this->get_return_url( $order ) );
				*/
				
				//global $woocommerce;				
				$checkout_url = $woocommerce->cart->get_checkout_url();
				wp_redirect($checkout_url);				
				exit;	
			}
		}
	}
		
   /**
     * Add the Gateway to WooCommerce
     **/
    function woocommerce_add_payu_rpp($methods) {
       $methods[] = 'WC_Gateway_PayU';
       return $methods;
    }
	
	// add the payment gateway to WooCommerce using filter woocommerce_payment_gateways
    add_filter('woocommerce_payment_gateways', 'woocommerce_add_payu_rpp' );
}
