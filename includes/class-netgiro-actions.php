<?php

/**
 * Handles Netgíró specific WooCommerce order actions.
 * Adds an admin action to check transaction status via the Netgíró API.
 *
 * @package Netgiro\Payments
 * @version 5.0.0
 */

defined( 'ABSPATH' ) || exit;
/**
 * Class Netgiro_Actions
 * Registers and processes custom WooCommerce order actions for Netgíró.
 */
class Netgiro_Actions {


	/**
	 * Constructor. Hooks into WordPress/WooCommerce.
	 */
	public function __construct() {
		// Add the custom action to the order actions dropdown.
		add_filter( 'woocommerce_order_actions', array( $this, 'add_check_status_order_action' ) );

		// Hook the processing function to the action handler.
		add_action( 'woocommerce_order_action_netgiro_check_status', array( $this, 'process_check_status_action' ) );
	}

	/**
	 * Add "Check Netgíró Status" to the WooCommerce order actions dropdown.
	 * Only adds the action if the order's payment method is Netgíró.
	 *
	 * @param array $actions Existing order actions.
	 * @return array Modified order actions.
	 */
	public function add_check_status_order_action( array $actions ): array {
		global $theorder;

		$show_action = false;

		if ( $theorder instanceof WC_Order && $theorder->get_payment_method() === 'netgiro' ) {
			$show_action = true;
		} elseif ( isset( $_REQUEST['post'] ) && is_admin() ) {
			// Verify nonce for post edit screen
			if ( isset( $_REQUEST['_wpnonce'] ) && wp_verify_nonce( sanitize_key( $_REQUEST['_wpnonce'] ), 'update-post_' . absint( $_REQUEST['post'] ) ) ) {
				$order_id = absint( wp_unslash( $_REQUEST['post'] ) );
				if ( $order_id > 0 ) {
					$order = wc_get_order( $order_id );
					if ( $order && $order->get_payment_method() === 'netgiro' ) {
						$show_action = true;
					}
				}
			}
		} elseif ( isset( $_REQUEST['order_id'] ) && is_admin() ) {
			// Verify nonce for AJAX actions
			if ( isset( $_REQUEST['security'] ) && wp_verify_nonce( sanitize_key( $_REQUEST['security'] ), 'woocommerce-order-actions' ) ) {
				$order_id = absint( wp_unslash( $_REQUEST['order_id'] ) );
				if ( $order_id > 0 ) {
					$order = wc_get_order( $order_id );
					if ( $order && $order->get_payment_method() === 'netgiro' ) {
						$show_action = true;
					}
				}
			}
		}

		if ( $show_action ) {
			$actions['netgiro_check_status'] = __( 'Check Netgíró status', 'netgiro-payment-gateway-for-woocommerce' );
		}

		return $actions;
	}

	/**
	 * Process the "Check Netgíró Status" action.
	 * Queries the Netgíró API for the transaction status and adds an order note.
	 *
	 * @param WC_Order $order The order object.
	 */
	public function process_check_status_action( WC_Order $order ): void {

		$order_id       = $order->get_id();
		$transaction_id = $order->get_transaction_id();

		if ( ! $transaction_id ) {
			$order->add_order_note( __( 'No Netgíró transaction ID found for this order.', 'netgiro-payment-gateway-for-woocommerce' ) );
			return;
		}
		if ( $order->get_payment_method() !== 'netgiro' ) {
			$order->add_order_note( __( 'Order was not paid using Netgíró. Status check aborted.', 'netgiro-payment-gateway-for-woocommerce' ) );
			return;
		}

		// Retrieve the Netgíró gateway instance to read its settings.
		$gateways = WC()->payment_gateways()->payment_gateways();
		if ( empty( $gateways['netgiro'] ) || ! $gateways['netgiro'] instanceof Netgiro_Gateway ) {
			$order->add_order_note( __( 'Netgíró gateway instance not found or not active.', 'netgiro-payment-gateway-for-woocommerce' ) );
			return;
		}

		/** @var Netgiro_Gateway $netgiro_gateway */
		$netgiro_gateway = $gateways['netgiro'];

		// Use the gateway's settings properties directly.
		$secret_key = $netgiro_gateway->secretkey;
		$app_key    = $netgiro_gateway->application_id;
		$test_mode  = ( 'yes' === $netgiro_gateway->get_option( 'test' ) );

		if ( empty( $secret_key ) || empty( $app_key ) ) {
			$order->add_order_note( __( 'Netgíró API credentials (Secret Key or Application ID) are not configured in WooCommerce settings.', 'netgiro-payment-gateway-for-woocommerce' ) );
			return;
		}

		if ( ! class_exists( 'Netgiro_API' ) ) {
			$order->add_order_note( __( 'Netgíró API class not found. Plugin files may be missing or corrupted.', 'netgiro-payment-gateway-for-woocommerce' ) );
			return;
		}
		$api = new Netgiro_API(
			$test_mode,
			$secret_key,
			$app_key
		);

		$response = $api->get_transaction_status( $transaction_id );

		if ( ! $response['success'] ) {
			$note = sprintf(
				/* translators: %1$s: Error message from API, %2$s: Netgiro Transaction ID. */
				__( 'Netgíró status check failed: %1$s (Transaction ID: %2$s)', 'netgiro-payment-gateway-for-woocommerce' ),
				esc_html( $response['message'] ),
				esc_html( $transaction_id )
			);
			$order->add_order_note( $note );
			return;
		}

		$status_info = isset( $response['data']['status'] ) ? esc_html( ucfirst( $response['data']['status'] ) ) : __( 'Status not provided', 'netgiro-payment-gateway-for-woocommerce' );

		$refundable_info = isset( $response['data']['isRefundable'] ) && $response['data']['isRefundable'] ? __( 'Refundable', 'netgiro-payment-gateway-for-woocommerce' ) : __( 'Not Refundable', 'netgiro-payment-gateway-for-woocommerce' );
		// Translators: %s is the formatted settlement date.
		$settlement_date_info = isset( $response['data']['settlementDate'] ) ? sprintf( __( 'Settlement expected on: %s', 'netgiro-payment-gateway-for-woocommerce' ), wc_format_datetime( new WC_DateTime( $response['data']['settlementDate'] ) ) ) : __( 'Settlement date not available', 'netgiro-payment-gateway-for-woocommerce' );

		$note = sprintf(
			/* translators: %1$s: Netgiro Transaction ID, %2$s: Transaction status, %3$s: Refundable status, %4$s: Expected settlement date. */
			__( 'Netgíró status updated: Transaction ID: %1$s. Status: %2$s. Refundable: %3$s. %4$s', 'netgiro-payment-gateway-for-woocommerce' ),
			esc_html( $transaction_id ),
			$status_info,
			$refundable_info,
			$settlement_date_info
		);
		$order->add_order_note( $note );
	}
}
