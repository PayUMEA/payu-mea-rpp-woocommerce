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

/**
 * This file contains the class for doign PayuRedirectPaymentPage transactions
 * Date:
 * 
 * @version 1.0
 * 
 * 
 */

//Requiring the PayU base class
require_once('class.PayuBase.php');

class PayuRedirectPaymentPage extends PayuBase {
	
	private $payuUrlArray = array( 'staging' => 'https://staging.payu.co.za' , 'production' => 'https://secure.payu.co.za'  );
	private $payuBaseUrlToUse = "";
	private $soapWdslUrl = "";    
	private $soapAuthHeader = "";
	private $extendedDebugEnable = false;
	
	private $merchantSoapUsername = "";
	private $merchantSoapPassword = "";
	private $safeKey = "";
	private $soapApiVersion = "ONE_ZERO";
	
	public $soapClientInstance;
	
	
	public function __construct($constructorArray = array()) {
		
		//instantiate parent
		parent::__construct($constructorArray);
		
		//Setting the base url
		$this->payuBaseUrlToUse = $this->payuUrlArray['staging'];
		
		//overriding several initialisation values (id specified)
		if(isset($constructorArray['production']) && ($constructorArray['production'] !== false) ) {
			$this->payuBaseUrlToUse = $this->payuUrlArray['production'];
		}        
		if(isset($constructorArray['username']) && (!empty($constructorArray['username'])) ) {
			$this->merchantSoapUsername = $constructorArray['username'];
		}        
		if(isset($constructorArray['password']) && (!empty($constructorArray['password'])) ) {
			$this->merchantSoapPassword = $constructorArray['password'];
		}
		if(isset($constructorArray['extendedDebugEnable']) && ($constructorArray['extendedDebugEnable'] === true) ) {
			$this->extendedDebugEnable = true;
		}
		if(isset($constructorArray['safeKey']) && (!empty($constructorArray['safeKey'])) ) {
			$this->safeKey = $constructorArray['safeKey'];
		}        

		$this->extendedDebugEnable = false;
		//$this->extendedDebugEnable = true;
		
		//Setting the neccesary variables used in the class
		$this->setSoapWdslUrl();         
	}
	
	/**    
	*
	* Do the get transaction soap call against the PayU API and returns a url containing the RPP url with reference
	*    
	* @param array soapDataArray The array containing the data to
	*
	* @return array Returns the get transaction response details
	*/
	public function doGetTransactionSoapCall( $soapDataArray = array() ) {
		
		$returnData = $this->doSoapCallToApi('getTransaction',$soapDataArray);    
		
		$returnArray = array();
		$returnArray['soapResponse'] = $returnData['return'];
		$returnArray['redirectPaymentPageUrl'] = $this->getTransactionRedirectPageUrl($returnData['return']['payUReference']);
		
		return $returnArray;
	}
	
	
	/**    
	*
	* Do the set transaction soap call against the PayU API and returns a url containing the RPP url with reference
	*
	* @param string soapFunctionToCall The Soap function the needs to be called
	* @param array soapDataArray The array containing the data to
	*
	* @return array Returns the set transaction response details
	*/
	public function doSetTransactionSoapCall($soapDataArray = array()) 
	{        
		$returnData = $this->doSoapCallToApi('setTransaction',$soapDataArray);        
		
		
		//If succesfull then pass back the payUreference  with return URL
		if( isset($returnData['return']['successful']) && ($returnData['return']['successful'] === true) ) {
			$tempArray = array();
			$tempArray['soapResponse'] = $returnData['return'];
			$tempArray['redirectPaymentPageUrl'] = $this->getTransactionRedirectPageUrl($returnData['return']['payUReference']);
			
			return $tempArray;
		}
		else {
			$returnData['soapResponse'] = $returnData['return'];

			$errorMessage = "Unspecified error. please contact merchant";
			if($this->extendedDebugEnable === true) {
				$errorMessage = $returnData['soapResponse']['displayMessage'].", Details: ".$errorMessage = $returnData['soapResponse']['resultMessage'];
			}
			elseif(isset($returnData['soapResponse']['displayMessage']) && !empty($returnData['soapResponse']['displayMessage']) ) {
				$errorMessage = $returnData['soapResponse']['displayMessage'];
			}
			elseif( isset($returnData['soapResponse']['resultMessage']) && !empty($returnData['soapResponse']['resultMessage']) ) {
				$errorMessage = $returnData['soapResponse']['resultMessage'];
			}             
			throw new exception($errorMessage);
		}
		
	}
	
	/**    
	*
	* Do the soap call against the PayU API
	*
	* @param string soapFunctionToCall The Soap function the needs to be called
	* @param array soapDataArray The array containing the data to
	*
	* @return array Returns the soap result in array format
	*/
	public function doSoapCallToApi( $soapFunctionToCall = null , $soapDataArray = array() ) {
		
		// A couple of validation business ruless before doing the soap call
		if(empty($soapDataArray)) {
			throw new Exception("Please provide data to be used on the soap call");
		}
		elseif(empty($soapFunctionToCall)) {
			throw new Exception("Please provide a soap function to call");
		}

		//Setting the soap header if not already set
		if(empty($this->soapAuthHeader)) {
			$this->setSoapHeader();
		}
		
		//Setting the soap header if not already set
		if(!empty($this->safeKey)) {            
			$soapDataArray['Safekey'] = $this->safeKey;
		}
		
		//log an entry indicating that a soap call is about to happen
		$this->log("------------------   SOAP CALL TRANSACTION ABOUT TO START: ".$soapFunctionToCall."   -----------------------------\r\n");
		
		
		//Make new instance of the PHP Soap client
		$this->soapClientInstance = new SoapClient($this->soapWdslUrl, array("trace" => 1, "exception" => 0)); 

		//Set the Headers of soap client. 
		$this->soapClientInstance->__setSoapHeaders($this->soapAuthHeader); 

		//Adding the api version to the soap data packet array
		$soapDataArray = array_merge($soapDataArray, array('Api' => $this->soapApiVersion ));
		
		
		//Do Soap call
		try{
			$soapCallResult = $this->soapClientInstance->$soapFunctionToCall($soapDataArray); 
			//var_dump($this->soapClientInstance->__getLastResponse());
			if(is_object($this->soapClientInstance)) {
				$this->log("SOAP CALL REQUEST HEADERS: ".$soapFunctionToCall, "\r\n".$this->soapClientInstance->__getLastRequestHeaders());
				$this->log("SOAP CALL REQUEST: ".$soapFunctionToCall, "\r\n".$this->soapClientInstance->__getLastRequest());        
				$this->log("SOAP CALL RESPONSE HEADERS: ".$soapFunctionToCall, "\r\n".$this->soapClientInstance->__getLastResponseHeaders());
				$this->log("SOAP CALL RESPONSE: ".$soapFunctionToCall, "\r\n".$this->soapClientInstance->__getLastResponse());        
			}
		}
		catch(Exception $e) {

			//var_dump($this->soapClientInstance->__getLastRequest());
			if(is_object($this->soapClientInstance)) {
				$this->log("SOAP CALL REQUEST HEADERS: ".$soapFunctionToCall, "\r\n".$this->soapClientInstance->__getLastRequestHeaders());
				$this->log("SOAP CALL REQUEST: ".$soapFunctionToCall, "\r\n".$this->soapClientInstance->__getLastRequest());        
				$this->log("SOAP CALL RESPONSE HEADERS: ".$soapFunctionToCall, "\r\n".$this->soapClientInstance->__getLastResponseHeaders());
				$this->log("SOAP CALL RESPONSE: ".$soapFunctionToCall, "\r\n".$this->soapClientInstance->__getLastResponse());        
			}
			throw new Exception($e->getMessage(),null,$e);
		}        

		//die();

		// Decode the Soap Call Result for returning
		$returnData = json_decode(json_encode($soapCallResult),true);

		return $returnData;
	}
	
	/**    
	 * Set the soap header string used to call in the Soap to PayU API
	 */        
	private function setSoapHeader() {
		
		if(empty($this->merchantSoapUsername)) {
			throw new exception('Please specify a merchant username for soap trasaction');
		}
		elseif(empty($this->merchantSoapPassword)) {
			throw new exception('Please specify a merchant password for soap trasaction');
		}
		
		//Creating a soap xml
		$headerXml = '<wsse:Security SOAP-ENV:mustUnderstand="1" xmlns:wsse="http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-secext-1.0.xsd">';
		$headerXml .= '<wsse:UsernameToken wsu:Id="UsernameToken-9" xmlns:wsu="http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-utility-1.0.xsd">';
		$headerXml .= '<wsse:Username>'.$this->merchantSoapUsername.'</wsse:Username>';
		$headerXml .= '<wsse:Password Type="http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-username-token-profile-1.0#PasswordText">'.$this->merchantSoapPassword.'</wsse:Password>';
		$headerXml .= '</wsse:UsernameToken>';
		$headerXml .= '</wsse:Security>';
		$headerbody = new SoapVar($headerXml, XSD_ANYXML, null, null, null);  
		
		$ns = 'http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-secext-1.0.xsd'; //Namespace of the WS.         
		$this->soapAuthHeader = new SOAPHeader($ns, 'Security', $headerbody, true);        
	}
	
	
	/*     
	 * Set the Base RPP Url to use      
	 */        
	private function getTransactionRedirectPageUrl($payuReference = null) {
		if(empty($payuReference)) {
			throw new Exception('Please specify a valid payU Reference number');
		}
		return $this->payuBaseUrlToUse.'/rpp.do?PayUReference='.$payuReference;
	}

	/**    
	 * Set the PayU soap WDSL URL for use in soap
	 */        
	private function setSoapWdslUrl() {
		$this->soapWdslUrl = $this->payuBaseUrlToUse.'/service/PayUAPI?wsdl';        
	}

}

