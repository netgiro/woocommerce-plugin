<?php

/**
 * Plugin Name: Netgíró Payment Gateway for WooCommerce
 * Plugin URI: https://www.netgiro.is
 * Description: Official Netgíró Payment Gateway integration for WooCommerce.
 * Version: 5.1.0
 * Author: Netgíró
 * Text Domain: netgiro-payment-gateway-for-woocommerce
 * Domain Path: /languages
 * Author URI: https://www.netgiro.is
 * Requires Plugins: woocommerce
 * WC requires at least: 8.1.0
 * WC tested up to: 10.3.3
 * WC Payment Gateway: yes
 * WC Blocks Support: yes
 * WC-HPOS: true
 * Requires PHP: 7.4
 *
 * License:     GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html

 */

defined( 'ABSPATH' ) || exit;

define( 'NETGIRO_PLUGIN_VERSION', '5.1.0' );
define( 'NETGIRO_PLUGIN_FILE', __FILE__ );
define( 'NETGIRO_PLUGIN_PATH', plugin_dir_path( __FILE__ ) );
define( 'NETGIRO_PLUGIN_URL', plugin_dir_url( __FILE__ ) );


/**
 * Show an admin notice if WooCommerce is not active.
 */
function netgiro_admin_notice_woocommerce_missing(): void {
	if ( ! current_user_can( 'activate_plugins' ) ) {
		return;
	}
	echo '<div class="error"><p><strong>';
	echo esc_html__(
		'Netgíró Payment Gateway for WooCommerce requires WooCommerce to be installed and active.',
		'netgiro-payment-gateway-for-woocommerce'
	);
	echo '</strong></p></div>';
}

/**
 * Show an admin notice if currency is not ISK.
 */
function netgiro_admin_notice_currency_invalid(): void {
	if ( ! current_user_can( 'activate_plugins' ) ) {
		return;
	}
	echo '<div class="error"><p><strong>';
	echo esc_html__(
		'Netgíró Payment Gateway requires WooCommerce currency to be set to ISK.',
		'netgiro-payment-gateway-for-woocommerce'
	);
	echo '</strong></p></div>';
}

/**
 * Declare compatibility for HPOS and Cart/Checkout Blocks.
 */
add_action(
	'before_woocommerce_init',
	function (): void {
		if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
				'custom_order_tables',
				NETGIRO_PLUGIN_FILE,
				true
			);
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
				'cart_checkout_blocks',
				NETGIRO_PLUGIN_FILE,
				true
			);
		}
	},
	10
);

/**
 * Initialize the Netgíró plugin.
 */
function netgiro_payments_init(): void {
	// Check if WooCommerce is active
	if ( ! class_exists( 'WooCommerce' ) ) {
		add_action( 'admin_notices', 'netgiro_admin_notice_woocommerce_missing' );
		return;
	}

	// Check if currency is ISK
	if ( get_woocommerce_currency() !== 'ISK' ) {
		add_action( 'admin_notices', 'netgiro_admin_notice_currency_invalid' );
		return;
	}

	
	// Include required files
	require_once NETGIRO_PLUGIN_PATH . 'includes/class-netgiro-settings.php';
	require_once NETGIRO_PLUGIN_PATH . 'includes/class-netgiro-api.php';
	require_once NETGIRO_PLUGIN_PATH . 'includes/class-netgiro-payment-form.php';
	require_once NETGIRO_PLUGIN_PATH . 'includes/class-netgiro-gateway.php';
	require_once NETGIRO_PLUGIN_PATH . 'includes/class-netgiro-block-support.php';
	require_once NETGIRO_PLUGIN_PATH . 'includes/class-netgiro-actions.php';

	// Register block-based checkout integration if available.
	if ( class_exists( 'Netgiro_Block_Support' ) ) {
		Netgiro_Block_Support::init();
	}

	add_filter( 'woocommerce_payment_gateways', 'netgiro_add_gateway_method' );
	// Instantiate the Actions class to register its hooks
	if ( class_exists( 'Netgiro_Actions' ) ) {
		new Netgiro_Actions();
	}
	add_action( 'wp_enqueue_scripts', 'netgiro_enqueue_block_settings' );
}

/**
 * Hook to initialize plugin after all plugins are loaded,
 * ensuring WooCommerce is ready.
 */
add_action( 'plugins_loaded', 'netgiro_payments_init' );

/**
 * Add Netgíró gateway to WooCommerce Payment Gateways.
 *
 * @param array $gateways
 * @return array
 */
function netgiro_add_gateway_method( array $gateways ): array {
	$gateways[] = 'Netgiro_Gateway';
	return $gateways;
}


function netgiro_enqueue_block_settings(): void {
	if ( class_exists( 'WC_Payment_Gateways' ) && function_exists( 'wp_add_inline_script' ) ) {
		$gateways = WC()->payment_gateways->get_available_payment_gateways();
		if ( isset( $gateways['netgiro'] ) ) {
			$gateway = $gateways['netgiro'];
			$data    = array(
				'title'       => $gateway->title,
				'description' => $gateway->description,
				'supports'    => $gateway->supports,
			);

			wp_add_inline_script(
				'netgiro-block-checkout',
				'window.wc.wcSettings.setSetting("netgiro_data", ' . wp_json_encode( $data ) . ');',
				'before'
			);
		}
	}
}
