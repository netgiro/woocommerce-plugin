<?php /**
	   * Plugin Name: Netgíró Payment gateway for Woocommerce
	   * Plugin URI: http://www.netgiro.is
	   * Description: Netgíró Payment gateway for Woocommerce
	   * Version: 4.2.0
	   * Author: Netgíró
	   * Author URI: http://www.netgiro.is
	   *
	   * @package WooCommerce-netgiro-plugin
	   */

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
	function render_view( $view_name, $var = array() ) {
		require_once plugin_dir_path( ( __FILE__ ) ) . 'assets/view/' . $view_name . '.php';
	}

}

add_action( 'plugins_loaded', 'woocommerce_netgiro_init', 0 );
