<?php

/**
 * Netgíró Gateway Settings Class
 *
 * Defines the configuration fields for the Netgíró payment gateway
 * displayed in the WooCommerce settings area.
 *
 * @package Netgiro\Payments
 * @version 5.1.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class Netgiro_Settings
 * Provides the structure for the gateway's admin settings fields.
 */
class Netgiro_Settings {


	/**
	 * Returns the array structure for the Netgíró payment gateway settings form fields.
	 * Used by the WC_Settings_API to render the settings page.
	 *
	 * @return array Form fields definition.
	 */
	public static function get_form_fields(): array {
		return array(
			'enabled'                 => array(
				'title'   => __( 'Enable/Disable', 'netgiro-payment-gateway-for-woocommerce' ),
				'type'    => 'checkbox',
				'label'   => __( 'Enable Netgíró Payment Gateway', 'netgiro-payment-gateway-for-woocommerce' ),
				'default' => 'no',
			),
			'title'                   => array(
				'title'       => __( 'Title', 'netgiro-payment-gateway-for-woocommerce' ),
				'type'        => 'text',
				'description' => __( 'This controls the title which the user sees during checkout.', 'netgiro-payment-gateway-for-woocommerce' ),
				'default'     => __( 'Netgíró', 'netgiro-payment-gateway-for-woocommerce' ),
				'desc_tip'    => true,
			),
			'description'             => array(
				'title'       => __( 'Description', 'netgiro-payment-gateway-for-woocommerce' ),
				'type'        => 'textarea',
				'description' => __( 'This controls the description which the user sees during checkout.', 'netgiro-payment-gateway-for-woocommerce' ),
				'default'     => __( 'Pay securely using Netgíró. Choose instant payment, split payments, or invoice.', 'netgiro-payment-gateway-for-woocommerce' ),
				'desc_tip'    => true,
			),
			'api_credentials_heading' => array(
				'title'       => __( 'API Credentials', 'netgiro-payment-gateway-for-woocommerce' ),
				'type'        => 'title',
				'description' => __( 'Enter your Netgíró API details obtained from partner.netgiro.is.', 'netgiro-payment-gateway-for-woocommerce' ),
			),
			'test'                    => array(
				'title'       => __( 'Test Mode', 'netgiro-payment-gateway-for-woocommerce' ),
				'type'        => 'checkbox',
				'label'       => __( 'Enable Netgíró Test Environment', 'netgiro-payment-gateway-for-woocommerce' ),
				'description' => __( 'Use the Netgíró sandbox environment for testing. Requires separate test credentials.', 'netgiro-payment-gateway-for-woocommerce' ),
				'default'     => 'no',
				'desc_tip'    => true,
			),
			'application_id'          => array(
				'title'       => __( 'Application ID', 'netgiro-payment-gateway-for-woocommerce' ),
				'type'        => 'text',
				'description' => __( 'Enter your Netgíró Application ID.', 'netgiro-payment-gateway-for-woocommerce' ),
				'default'     => '',
				'desc_tip'    => true,
			),
			'secretkey'               => array(
				'title'       => __( 'Secret Key', 'netgiro-payment-gateway-for-woocommerce' ),
				'type'        => 'password',
				'description' => __( 'Enter your Netgíró Secret Key.', 'netgiro-payment-gateway-for-woocommerce' ),
				'default'     => '',
				'desc_tip'    => true,
			),
			'payment_options_heading' => array(
				'title' => __( 'Payment Options', 'netgiro-payment-gateway-for-woocommerce' ),
				'type'  => 'title',
			),
			'confirmation_type'       => array(
				'title'       => __( 'Payment Confirmation Type', 'netgiro-payment-gateway-for-woocommerce' ),
				'type'        => 'select',
				'description' => __( 'Choose how payments are confirmed after authorization. Server Callback (recommended): Netgíró validates with your server before confirming. Automatic: Confirms immediately. Manual: Requires changing order status from On Hold to Processing/Completed.', 'netgiro-payment-gateway-for-woocommerce' ),
				'default'     => '1',
				'options'     => array(
					'0' => __( 'Automatic Confirmation (Capture immediately)', 'netgiro-payment-gateway-for-woocommerce' ),
					'1' => __( 'Server Callback (Recommended - Server-side validation)', 'netgiro-payment-gateway-for-woocommerce' ),
					'2' => __( 'Manual Confirmation (Authorize now, Capture later)', 'netgiro-payment-gateway-for-woocommerce' ),
				),
				'desc_tip'    => true,
			),
			'send_order_items'        => array(
				'title'       => __( 'Send Order Item Details', 'netgiro-payment-gateway-for-woocommerce' ),
				'type'        => 'checkbox',
				'label'       => __( 'Send individual item details (product name, price, quantity) to Netgíró.', 'netgiro-payment-gateway-for-woocommerce' ),
				'description' => __( 'Recommended. If unchecked, only the order total and a generic description will be sent.', 'netgiro-payment-gateway-for-woocommerce' ),
				'default'     => 'yes',
				'desc_tip'    => true,
			),
			'page_redirects_heading'  => array(
				'title' => __( 'Page Redirects', 'netgiro-payment-gateway-for-woocommerce' ),
				'type'  => 'title',
			),
			'cancel_page_id'          => array(
				'title'       => __( 'Cancel Page', 'netgiro-payment-gateway-for-woocommerce' ),
				'type'        => 'select',
				'options'     => self::get_pages_list( __( 'Default (Checkout Page)', 'netgiro-payment-gateway-for-woocommerce' ) ),
				'description' => __( 'Page where customers are redirected if they cancel the payment at Netgíró. If unset, defaults to the checkout page.', 'netgiro-payment-gateway-for-woocommerce' ),
				'default'     => '',
				'desc_tip'    => true,
			),

		);
	}

	/**
	 * Returns a key-value array of published WordPress pages for select fields.
	 * Includes an option for 'Default'.
	 *
	 * @param string $default_text Text for the default/empty option.
	 * @return array Array of Page ID => Page Title.
	 */
	protected static function get_pages_list( string $default_text = '' ): array {
		$pages_list   = get_pages( array( 'post_status' => 'publish' ) );
		$default_text = $default_text ? $default_text : __( 'Select a page', 'netgiro-payment-gateway-for-woocommerce' );

		$options = array( '' => $default_text );

		if ( $pages_list ) {
			foreach ( $pages_list as $page ) {
				$options[ $page->ID ] = esc_html( $page->post_title );
			}
		}

		return $options;
	}
}
