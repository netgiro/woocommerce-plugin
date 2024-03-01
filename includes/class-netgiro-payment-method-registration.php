<?php 
/**
 * Netgiro payment method registration
 *
 * @package WooCommerce-netgiro-plugin
 */

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;

defined( 'ABSPATH' ) || exit;


/**
 * Netgiro Payment Method Registration
 * Provides a registration for the Netgíró Payment Method for WooCommerce Blocks.
 *
 * @class       Netgiro_Payment_Method_Registration
 * @extends     AbstractPaymentMethodType
 */
final class Netgiro_Payment_Method_Registration extends AbstractPaymentMethodType {

	/**
	 * The gateway for the payment method.
	 *
	 * @var mixed
	 */
	private $gateway;

	/**
	 * The name of the payment method.
	 *
	 * @var string
	 */
	protected $name = 'netgiro';

	/**
	 * The settings for the payment method.
	 *
	 * @var array
	 */
	protected $settings = array();

	/**
	 * Initializes the payment method.
	 *
	 * This function is called when the payment method is being initialized.
	 */
	public function initialize() {
		$this->settings = get_option( 'woocommerce_netgiro_settings', array() );
		$this->gateway  = new Netgiro();
	}
	/**
	 * Checks if the payment method is active.
	 *
	 * This function determines if the payment method is active by checking if the gateway is available.
	 *
	 * @return bool Returns true if the payment method is active, false otherwise.
	 */
	public function is_active() {
		return $this->gateway->is_available();
	}

	/**
	 * Retrieves the script handles for the payment method.
	 *
	 * This function retrieves the script handles that are required for the payment method to function properly.
	 *
	 * @return array Returns an array of script handles.
	 */
	public function get_payment_method_script_handles() {

		wp_register_script(
			'netgiro-checkout-block',
			plugin_dir_url( __FILE__ ) . 'blocks/checkout.js',
			array(
				'wc-blocks-registry',
				'wc-settings',
				'wp-element',
				'wp-html-entities',
				'wp-i18n',
			),
			filemtime( plugin_dir_path( __FILE__ ) . 'blocks/checkout.js' ),
			true
		);
		return array( 'netgiro-checkout-block' );
	}



	/**
	 * Retrieves the data for the payment method.
	 * This function retrieves the data for the payment method, including the title, description, supported products, and icon.
	 *
	 * @return array Returns an array containing the payment method data.
	 */
	public function get_payment_method_data() {
		return array(
			'title' => $this->gateway->title,
			'description' => $this->gateway->description,
			'supports'    => array( 'products' ),
			'icon' => $this->gateway->icon,
		);
	}
}
