<?php 
/**
 * Plugin Name: Netgíró Payment gateway for Woocommerce
 * Plugin URI: http://www.netgiro.is
 * Description: Netgíró Payment gateway for Woocommerce
 * Version: 4.3.0
 * Author: Netgíró
 * Author URI: http://www.netgiro.is
 *
 * @package WooCommerce-netgiro-plugin
 */

defined( 'ABSPATH' ) || exit;

/**
 * Initialize the Netgiro payment gateway.
 */
function woocommerce_netgiro_init() {
	if ( ! class_exists( 'WC_Payment_Gateway' ) ) {
		return;
	}

	require_once plugin_dir_path( __FILE__ ) . 'includes/class-netgiro-template.php';
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-netgiro.php';

	/**
	 * Add the Netgiro gateway to WooCommerce.
	 *
	 * @param array $methods Existing payment methods.
	 * @return array Filtered payment methods.
	 */
	function woocommerce_add_netgiro_gateway( $methods ) {
		$methods[] = 'Netgiro';
		return $methods;
	}

	add_filter( 'woocommerce_payment_gateways', 'woocommerce_add_netgiro_gateway' );

	/**
	 * Enqueue Netgiro script.
	 * disabled since not in use
	 */
	function netgiro_enqueue_scripts() {
		$script_path = plugins_url( 'assets/js/script.js', __FILE__ );
		wp_enqueue_script( 'netgiro-script', $script_path, array(), '1.0.0', true );
	}
	add_action( 'wp_enqueue_scripts', 'netgiro_enqueue_scripts' );

	/**
	 * Render view files
	 *
	 * @param string $view_name Name of view file.
	 * @param array  $var Array with variables.
	 */
// phpcs:ignore Universal.NamingConventions.NoReservedKeywordParameterNames.varFound,Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
	function render_view( $view_name, $var = array() ) {
		require_once plugin_dir_path( ( __FILE__ ) ) . 'assets/view/' . $view_name . '.php';
	}
}

add_action( 'plugins_loaded', 'woocommerce_netgiro_init', 0 );


/**
 * Custom function to declare compatibility with cart_checkout_blocks feature
 */
function declare_cart_checkout_blocks_compatibility() {
	if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', __FILE__, true );
	}
}

add_action( 'before_woocommerce_init', 'declare_cart_checkout_blocks_compatibility' );



add_action( 'woocommerce_blocks_loaded', 'netgiro_woocommerce_blocks_support' );
/**
 * Enable support for WooCommerce blocks.
 */
function netgiro_woocommerce_blocks_support() {
	if ( class_exists( 'Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType' ) ) {

		require_once plugin_dir_path( __FILE__ ) . 'includes/class-netgiro-payment-method-registration.php';
		add_action(
			'woocommerce_blocks_payment_method_type_registration',
			function ( Automattic\WooCommerce\Blocks\Payments\PaymentMethodRegistry $payment_method_registry ) {
				$payment_method_registry->register( new Netgiro_Payment_Method_Registration() );
			}
		);
	}
}
