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
 * This file contains the base class doing Payu transactions. 
 * Within this file various common functionality is specified
 * Date:
 * 
 * @version 1.0
 * 
 * 
 */
abstract class PayuBase {
	
	private $logEnable = false;
	private $logLocation = "./../logs/";
	private $logLevel = 1;
	
	public $soapClientInstance;
	
	
	public function __construct($constructorArray = array()) {
		
		if(isset($constructorArray['logEnable']) && ($constructorArray['logEnable'] === true) ) {
			$this->logEnable = true;            
		}        
		if(isset($constructorArray['logLocation']) && (!empty($constructorArray['logLocation'])) ) {        
			$this->logLocation = $constructorArray['logLocation'];            
		}
		else {
			$this->logLocation = dirname(__FILE__)."/".$this->logLocation;
		}
		
		if(isset($constructorArray['logLocation']) && (!empty($constructorArray['logLocation'])) && (is_numeric($constructorArray['logLocation'])) ) {        
			$this->logLevel = $constructorArray['logLocation'];            
		}
	}
	
	/**    
	*
	* Do the logging of various given strings and an optional instruction
	*
	* @param string $stringToLog This is the string to log to the log file
	* @param array $instructionToLog The instruction to log the string against e.g. a sop function called
	*
	* @return void
	*/
	protected function log( $stringToLog = null, $instructionToLog = null ) {
		
		if($this->logEnable === true) {
			if(empty($stringToLog)) {
				throw new Exception("Please specify a value to log");
			}
			elseif(empty($this->logLocation)) {
				throw new Exception("Please specify a log file directory location");
			}
			else {
				if(!is_file($this->logLocation) && !is_dir($this->logLocation)) {
					mkdir($this->logLocation,0777);
				}
				
				if(!file_exists($this->logLocation)) {
					throw new Exception("Could not create the log file directory location:".$this->logLocation);                    
				}
			}
			
			$logFile = $this->logLocation."/payuRedirectPaymentPage.".date('Y-m-d').".log";
			
			$stringToLog = "'".date('Y-m-d H:i:s')."','".$stringToLog."'";
			if(!empty($instructionToLog)) {
				$stringToLog .= ",'".$instructionToLog."'";    
			}
			$stringToLog .= "\r\n";            
			
			//file_put_contents($logFile, $stringToLog, FILE_APPEND | LOCK_EX);
		}        
	}
}

