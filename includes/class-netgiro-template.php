<?php /**
	   * Plugin Name: Netgíró Payment gateway for Woocommerce
	   * Plugin URI: http://www.netgiro.is
	   * Description: Netgíró Payment gateway for Woocommerce
	   * Author: Netgíró
	   * Author URI: http://www.netgiro.is
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
