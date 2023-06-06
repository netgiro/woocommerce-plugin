<?php /**
	   * Netgiro template
	   *
	   * @package WooCommerce-netgiro-plugin
	   */

/**
 * Template for Netgiro plugin
 */
class Netgiro_Template {

	/**
	 * Template for Netgiro plugin
	 *
	 * @var      Netgiro    $payment_gateway_reference
	 */
	protected $payment_gateway_reference;

	/**
	 * Construct for all files
	 *
	 * @param string $payment_gateway_reference Reference for payment Gateway.
	 */
	public function __construct( &$payment_gateway_reference ) {
		$this->payment_gateway_reference = $payment_gateway_reference;

	}
}
