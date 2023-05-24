<?php
/**
 * Plugin Name: Netgíró Payment gateway for Woocommerce
 * Plugin URI: http://www.netgiro.is
 * Description: Netgíró Payment gateway for Woocommerce
 * Version: 4.1.1
 * Author: Netgíró
 * Author URI: http://www.netgiro.is
 * WC requires at least: 4.6.0
 * WC tested up to: 7.6.1
 *
 * @package WooCommerce-netgiro-plugin
 */

/**
 * WC_netgiro Payment Gateway
 *
 * Provides a Netgíró Payment Gateway for WooCommerce.
 *
 * @class       WC_netgiro
 * @extends     WC_Payment_Gateway
 * @version     4.1.1
 * @package     WooCommerce-netgiro-plugin
 */
class Netgiro_Template {

    /**
	 *
	 * @since    4.2.0
	 * @access   protected
	 * @var      WC_Netgiro    $payment_gateway_reference
	 */
    protected $payment_gateway_reference;

    public function __construct(&$payment_gateway_reference)
    {
		$this->payment_gateway_reference = $payment_gateway_reference;
    }
}