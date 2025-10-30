<?php

/**
 * Netgíró Payment Method Block Support Class
 *
 * Integrates the Netgíró payment method with WooCommerce Blocks (Checkout).
 * Registers the payment method type and provides necessary data to the frontend script.
 *
 * @package Netgiro\Payments
 * @version 5.1.0
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType' ) ) {
	return;
}

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;

// Make sure the Netgiro_Settings class is available
if ( ! class_exists( 'Netgiro_Settings' ) ) {
	require_once 'class-netgiro-settings.php';
}

/**
 * Netgiro_Block_Support Class
 *
 * Provides support for WooCommerce Blocks integration with Netgíró payment gateway.
 * This class extends AbstractPaymentMethodType to implement the necessary
 * functionality for Netgíró payments in the block-based checkout.
 */
class Netgiro_Block_Support extends AbstractPaymentMethodType {

	/**
	 * The gateway settings
	 *
	 * @var array
	 */
	protected $settings;

	/**
	 * The gateway form fields
	 *
	 * @var array
	 */
	protected $form_fields;

	/**
	 * Constructor. Initialize the gateway settings.
	 */
	public function __construct() {
		$this->settings    = get_option( 'woocommerce_netgiro_settings', array() );
		$this->form_fields = Netgiro_Settings::get_form_fields();
	}

	/**
	 * Hook into WooCommerce Blocks for payment method registration.
	 */
	public static function init(): void {
		add_action(
			'woocommerce_blocks_payment_method_type_registration',
			array( __CLASS__, 'register_payment_method_type' )
		);
	}

	/**
	 * Register an instance of this payment method into the registry.
	 *
	 * @param \Automattic\WooCommerce\Blocks\Payments\PaymentMethodRegistry $registry
	 */
	public static function register_payment_method_type( $registry ): void {
		$instance = new self();
		$registry->register( $instance );
	}

	/**
	 * @inheritDoc
	 */
	public function initialize() {}

	/**
	 * Unique name for this payment method (must match the JavaScript registration).
	 */
	public function get_name(): string {
		return 'netgiro';
	}

	/**
	 * Provide the payment method title (shown in block checkout).
	 * @return string
	 */
	public function get_payment_method_title(): string {
		// Get the title from settings, or use default from form fields definition
		$default = isset( $this->form_fields['title']['default'] ) ? $this->form_fields['title']['default'] : __( 'Netgíró', 'netgiro-payment-gateway-for-woocommerce' );
		return ! empty( $this->settings['title'] ) ? $this->settings['title'] : $default;
	}

	/**
	 * Provide the payment method description.
	 * @return string
	 */
	public function get_payment_method_description(): string {
		$default = isset( $this->form_fields['description']['default'] ) ? $this->form_fields['description']['default'] : __( 'Pay with Netgíró.', 'netgiro-payment-gateway-for-woocommerce' );
		return ! empty( $this->settings['description'] ) ? $this->settings['description'] : $default;
	}

	/**
	 * Register & return the script handle used by this payment method type.
	 * Must match the handle used in netgiro_enqueue_block_settings() inline script.
	 */
	public function get_payment_method_script_handles(): array {
		wp_register_script(
			'netgiro-block-checkout',
			plugins_url( '/assets/js/netgiro-checkout.js', __DIR__ ),
			array( 'wc-blocks-registry', 'wc-settings', 'wp-element', 'wp-html-entities', 'wp-i18n' ),
			NETGIRO_PLUGIN_VERSION,
			true
		);
		return array( 'netgiro-block-checkout' );
	}

	/**
	 * Return arbitrary payment method data passed to the script,
	 * accessible via wcSettings in JS.
	 */
	public function get_payment_method_data(): array {
		return array(
			'title'       => $this->get_payment_method_title(),
			'description' => $this->get_payment_method_description(),
			'supports'    => array( 'products', 'refunds' ),
		);
	}

	/**
	 * Determines if the gateway is active. If false, blocks will hide it.
	 * Here we align with the Netgíró Gateway settings or your own logic.
	 */
	public function is_active(): bool {
		return isset( $this->settings['enabled'] ) && 'yes' === $this->settings['enabled'];
	}
}
