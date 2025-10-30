<?php

/**
 * Netgíró Payment Form Class
 *
 * Generates a payment form that auto-submits customers to Netgíró checkout.
 *
 * @package Netgiro\Payments
 * @version 5.1.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Netgiro_Payment_Form Class
 *
 * Handles the creation and processing of payment forms for Netgíró integration.
 * This class is responsible for generating the HTML form that submits customer
 * payment details to Netgíró's payment processing system.
 */
class Netgiro_Payment_Form {


	/**
	 * Gateway reference.
	 *
	 * @var WC_Payment_Gateway
	 */
	protected $gateway;

	/**
	 * Logger instance.
	 *
	 * @var WC_Logger_Interface
	 */
	protected $logger;

	/**
	 * Application ID.
	 *
	 * @var string
	 */
	protected $application_id;

	/**
	 * Secret key.
	 *
	 * @var string
	 */
	protected $secretkey;

	/**
	 * Cancel page ID.
	 *
	 * @var int
	 */
	protected $cancel_page_id;

	/**
	 * Payment gateway URL.
	 *
	 * @var string
	 */
	protected $payment_gateway_url;

	/**
	 * Constructor.
	 *
	 * @param WC_Payment_Gateway $gateway Payment gateway instance.
	 */
	public function __construct( WC_Payment_Gateway $gateway ) {
		$this->gateway = $gateway;
		$this->logger  = wc_get_logger();

		$this->application_id      = $gateway->application_id ?? '';
		$this->secretkey           = $gateway->secretkey ?? '';
		$this->cancel_page_id      = $gateway->cancel_page_id ?? 0;
		$this->payment_gateway_url = $gateway->payment_gateway_url ?? '';
	}

	/**
	 * Log message to WooCommerce logs if WP_DEBUG is enabled.
	 *
	 * @param string $message The message to log.
	 * @param string $level   Logging level (default 'info').
	 */
	protected function log( $message, $level = 'info' ): void {
		if ( ! defined( 'WP_DEBUG' ) || ! WP_DEBUG ) {
			return;
		}

		$this->logger->log(
			$level,
			$message,
			array( 'source' => 'netgiro-api' )
		);
	}


	/**
	 * Generate and auto-submit Netgíró payment form.
	 *
	 * @param int $order_id WooCommerce order ID.
	 */
	public function generate_netgiro_form( int $order_id ): void {
		$order = wc_get_order( $order_id );
		if ( ! $order instanceof WC_Order ) {
			wc_add_notice( __( 'Invalid order.', 'netgiro-payment-gateway-for-woocommerce' ), 'error' );
			$this->log( 'Invalid order: ' . $order_id, 'error' );
			return;
		}

		$confirmation_type = $this->gateway->get_option( 'confirmation_type', '0' );
		$send_items        = ( 'yes' === $this->gateway->get_option( 'send_order_items', 'yes' ) );
		$application_id    = $this->gateway->application_id;
		$secretkey         = $this->gateway->secretkey;

		$cancel_url = ( $this->gateway->cancel_page_id ) ?
			get_permalink( (int) $this->gateway->cancel_page_id ) : home_url( '/' );

		$success_url  = add_query_arg( 'wc-api', 'WC_netgiro', home_url( '/' ) );
		$callback_url = add_query_arg( 'wc-api', 'WC_netgiro_callback', home_url( '/' ) );

		$total_amount = (int) round( $order->get_total() );

		$signature = hash( 'sha256', $secretkey . $order_id . $total_amount . $application_id );

		$netgiro_args = array(
			'ApplicationID'        => $application_id,
			'Iframe'               => 'false',
			'PaymentSuccessfulURL' => $success_url,
			'PaymentCancelledURL'  => $cancel_url,
			'ConfirmationType'     => $confirmation_type,
			'ReferenceNumber'      => (string) $order_id,
			'TotalAmount'          => $total_amount,
			'Signature'            => $signature,
			'PrefixUrlParameters'  => 'true',
			'ClientInfo'           => 'WooCommerce 5.0.0',
		);

		// Add PaymentConfirmedURL for server callback validation (ConfirmationType = 1)
		if ( '1' === $confirmation_type ) {
			$netgiro_args['PaymentConfirmedURL'] = $callback_url;
		}

		$items = array();
		if ( $send_items ) {
			foreach ( $order->get_items( 'line_item' ) as $item ) {
				$product    = $item->get_product();
				$item_price = (int) round( $order->get_item_subtotal( $item, true, false ) );
				$line_total = (int) round( $order->get_line_subtotal( $item, true, false ) );

				$items[] = array(
					'ProductNo' => (string) $product->get_id(),
					'Name'      => $product->get_name(),
					'UnitPrice' => $item_price,
					'Amount'    => $line_total,
					'Quantity'  => (int) ( $item->get_quantity() ),
				);
			}
		} else {
			/* translators: %1$s is the order ID, %2$s is the shop name. */
			$netgiro_args['Description'] = sprintf( __( 'Order #%1$s from %2$s', 'netgiro-payment-gateway-for-woocommerce' ), $order_id, get_bloginfo( 'name' ) );
		}

		$gateway_url = $this->gateway->payment_gateway_url;

		// Log request details clearly
		$this->log(
			sprintf(
				'Redirecting customer to Netgíró payment form. Gateway URL: %s, Parameters: %s, Items: %s',
				$gateway_url,
				wp_json_encode( $netgiro_args ),
				$send_items ? wp_json_encode( $items ) : 'Not Sent'
			)
		);

		echo '<form action="' . esc_url( $gateway_url ) . '" method="post" id="netgiro_payment_form">';

		foreach ( $netgiro_args as $key => $value ) {
			echo '<input type="hidden" name="' . esc_attr( $key ) . '" value="' . esc_attr( $value ) . '"/>';
		}

		if ( $send_items && ! empty( $items ) ) {
			foreach ( $items as $index => $single_item ) {
				foreach ( $single_item as $item_key => $val ) {
					echo '<input type="hidden" name="Items[' . esc_attr( $index ) . '].' . esc_attr( $item_key ) . '" value="' . esc_attr( $val ) . '"/>';
				}
			}
		}

		echo '<noscript>';
		echo '<p>' . esc_html__( 'Please click the button below to proceed to Netgíró.', 'netgiro-payment-gateway-for-woocommerce' ) . '</p>';
		echo '<button type="submit" class="button alt">' . esc_html__( 'Proceed to Netgíró', 'netgiro-payment-gateway-for-woocommerce' ) . '</button>';
		echo '</noscript>';
		echo '</form>';

		wc_enqueue_js( 'document.getElementById("netgiro_payment_form").submit();' );
	}
}
