<?php
/**
 * Plugin Name: Netgíró Payment gateway for Woocommerce
 * Plugin URI: http://www.netgiro.is
 * Description: Netgíró Payment gateway for Woocommerce
 * Version: 4.2.0
 * Author: Netgíró
 * Author URI: http://www.netgiro.is
 * WC requires at least: 4.6.0
 * WC tested up to: 7.7.0
 *
 * @package WooCommerce-netgiro-plugin
 */

add_action( 'plugins_loaded', 'woocommerce_netgiro_init', 0 );

/**
 * Initialize the Netgiro payment gateway.
 */
function woocommerce_netgiro_init() {
	if ( ! class_exists( 'WC_Payment_Gateway' ) ) {
		return;
	}

	require_once plugin_dir_path( __FILE__ ) . 'includes/class-wc-netgiro.php';

	/**
	 * Add the Netgiro gateway to WooCommerce.
	 *
	 * @param array $methods Existing payment methods.
	 * @return array Filtered payment methods.
	 */
	function woocommerce_add_netgiro_gateway( $methods ) {
		$methods[] = 'WC_Netgiro';
		return $methods;
	}

	add_filter( 'woocommerce_payment_gateways', 'woocommerce_add_netgiro_gateway' );


	/**
	 * Hide the Netgiro payment gateway if the currency is not ISK.
	 *
	 * @param array $available_gateways The available payment gateways.
	 * @return array                   The modified available payment gateways.
	 */
	function hide_payment_gateway( $available_gateways ) {
		if ( is_admin() ) {
			return $available_gateways;
		}
		if ( get_woocommerce_currency() !== 'ISK' ) {
			$gateway_id = 'netgiro';
			if ( isset( $available_gateways[ $gateway_id ] ) ) {
				unset( $available_gateways[ $gateway_id ] );
			}
		}
		return $available_gateways;
	}

//	add_filter( 'woocommerce_available_payment_gateways', 'hide_payment_gateway' );

	/**
	 * Enqueue Netgiro script.
	 */
	function netgiro_enqueue_scripts() {
		$script_path = plugins_url( 'assets/js/script.js', __FILE__ );
		$style_path  = plugins_url( 'assets/css/style.css', __FILE__ );

		wp_enqueue_script( 'netgiro-script', $script_path, array(), '1.0.0', true );
		wp_enqueue_style( 'netgiro-style', $style_path, array(), '1.0.0', 'all' );
	}
	add_action( 'wp_enqueue_scripts', 'netgiro_enqueue_scripts' );


	function renderView($viewName, $var = array()) {
 	   require_once plugin_dir_path( dirname( __FILE__ ) ) . 'WooCommerce-netgiro-plugin/assets/view/'. $viewName . '.php';
	}

	class Netgiro_Template {

		/**
		 *
		 * @since    4.2.0
		 * @access   protected
		 * @var      WC_Netgiro    $payment_gateway_reference
		 */
		protected $payment_gateway_reference;

		public function __construct( &$payment_gateway_reference ) {
			$this->payment_gateway_reference = $payment_gateway_reference;

		}
	}
}
