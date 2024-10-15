<?php declare(strict_types=1);

/**
 * Copyright © 2024 PayU Financial Services. All rights reserved.
 * See COPYING.txt for license details.
 */

abstract class PayU_Payment_Base {
    /**
	 * @var bool
	 */
	private bool $log_enable = false;

    /**
     * @var WC_Logger
     */
	protected ?WC_Logger $logger = null;

	/**
	 * @var string
	 */
	private string $log_level = WC_Log_Levels::DEBUG;
	
	/**
	 * @var SoapClient
	 */
	public SoapClient $soap_client_instance;
	
	/**
	 * PayUPayment constructor
	 *
	 * @param array $config
	 */
	public function __construct(array $config = [])
	{
		if (isset($config['log_enable']) && ($config['log_enable'] === true) ) {
			$this->log_enable = true;

            if (empty($this->logger)) {
                $this->logger = wc_get_logger();
            }
		}
		
		if ((!empty($config['log_level'])) && (is_numeric($config['log_level']))) {
			$this->log_level = $config['log_level'];
		}
	}
	
	/**
	* Do the logging of various given strings and an optional instruction
	*
	* @param ?string $string_to_log String to log
	* @param array $instruction_to_log Instruction to log the string against e.g. a soap function called
	*
	* @return void
	*/
	protected function log(?string $string_to_log = null, ?string $instruction_to_log = null)
	{
		if ($this->log_enable === true) {
			if (empty($string_to_log)) {
				throw new EmptyLogStringException("Please specify a value to log");
			}
			
			$string_to_log = "'" . date('Y-m-d H:i:s') . "','" . $string_to_log . "'";

			if (!empty($instruction_to_log)) {
				$string_to_log .= ",'" . $instruction_to_log . "'";
			}

			$string_to_log .= PHP_EOL;
			
			$this->logger->log($this->log_level, $string_to_log, ['source' => 'payu-rpp']);
		}
	}
}
