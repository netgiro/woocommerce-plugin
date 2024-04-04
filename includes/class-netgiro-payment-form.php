<?php 
/**
 * Netgiro payment form
 *
 * @package WooCommerce-netgiro-plugin
 */

defined( 'ABSPATH' ) || exit;


/**
 * Netgiro Payment Form
 * Provides a Netgíró Payment Form for WooCommerce.
 *
 * @class       Netgiro_Payment_Form
 * @extends     Netgiro_Template
 */
class Netgiro_Payment_Form extends Netgiro_Template {

		/**
		 * Generate netgiro button link
		 *
		 * @param string $order_id The Order ID.
		 *
		 * @return string
		 */
	public function generate_netgiro_form( $order_id ) {

		global $woocommerce;

		if ( empty( $order_id ) ) {
			return $this->get_error_message();
		}

		$order_id = sanitize_text_field( $order_id );
		$order    = new WC_Order( $order_id );
		$txnid    = $order_id . '_' . gmdate( 'ymds' );

		if ( ! is_numeric( $order->get_total() ) ) {
			return $this->get_error_message();
		}

		$round_numbers          = $this->payment_gateway_reference->round_numbers;
		$payment_cancelled_url  = ( '' === $this->payment_gateway_reference->cancel_page_id ||
									 0 === $this->payment_gateway_reference->cancel_page_id )
										? get_site_url() . '/' :
										get_permalink( $this->payment_gateway_reference->cancel_page_id );
		$payment_confirmed_url  = add_query_arg( 'wc-api', 'WC_netgiro_callback', home_url( '/' ) );
		$payment_successful_url = add_query_arg( 'wc-api', 'WC_netgiro', home_url( '/' ) );

		$total = round( number_format( $order->get_total(), 0, '', '' ) );

		if ( 'yes' === $round_numbers ) {
			$total = round( $total );
		}

		$str       = $this->payment_gateway_reference->secretkey . $order_id . $total . $this->payment_gateway_reference->application_id;
		$signature = hash( 'sha256', $str );

		if ( ! function_exists( 'get_plugin_data' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		$plugin_data    = get_plugin_data( __FILE__ );
		$plugin_version = "4.3.1";

		// Netgiro arguments.
		$netgiro_args = array(
			'ApplicationID'        => $this->payment_gateway_reference->application_id,
			'Iframe'               => 'false',
			'PaymentSuccessfulURL' => $payment_successful_url,
			'PaymentCancelledURL'  => $payment_cancelled_url,
			'PaymentConfirmedURL'  => $payment_confirmed_url,
			'ConfirmationType'     => '0',
			'ReferenceNumber'      => $order_id,
			'TotalAmount'          => $total,
			'Signature'            => $signature,
			'PrefixUrlParameters'  => 'true',
			'ClientInfo'           => 'System: Woocommerce ' . $plugin_version,
		);

		if ( $order->get_shipping_total() > 0 && is_numeric( $order->get_shipping_total() ) ) {
			$netgiro_args['ShippingAmount'] = ceil( $order->get_shipping_total() );
		}

		if ( $order->get_total_discount() > 0 && is_numeric( $order->get_total_discount() ) ) {
			$netgiro_args['DiscountAmount'] = ceil( $order->get_total_discount() );
		}

		// Woocommerce -> Netgiro Items.
		foreach ( $order->get_items() as $item ) {
			$validation_pass = $this->validate_item_array( $item );

			if ( ! $validation_pass ) {
				return $this->get_error_message();
			}

			$unit_price = $order->get_item_subtotal( $item, true, 'yes' === $round_numbers );
			$amount     = $order->get_line_subtotal( $item, true, 'yes' === $round_numbers );

			if ( 'yes' === $round_numbers ) {
				$unit_price = round( $unit_price );
				$amount     = round( $amount );
			}

			$items[] = array(
				'ProductNo' => $item['product_id'],
				'Name'      => $item['name'],
				'UnitPrice' => $unit_price,
				'Amount'    => $amount,
				'Quantity'  => $item['qty'] * 1000,
			);
		}

		if ( ! wp_http_validate_url( $this->payment_gateway_reference->gateway_url ) && ! wp_http_validate_url( $order->get_cancel_order_url() ) ) {
			return $this->get_error_message();
		}
		render_view(
			'netgiro-payment-form-view',
			array(
				'gateway_url'      => $this->payment_gateway_reference->gateway_url,
				'netgiro_args'     => $netgiro_args,
				'no_of_items'      => count( $items ),
				'items'            => $items,
				'cancel_order_url' => $order->get_cancel_order_url(),
			)
		);
	}

	/**
	 * Retrieves the error message to display when a problem occurs.
	 *
	 * @return string The error message in Icelandic language.
	 */
	public function get_error_message() {
		return 'Villa kom upp við vinnslu beiðni þinnar. Vinsamlega reyndu aftur eða hafðu samband við þjónustuver Netgíró með tölvupósti á netgiro@netgiro.is';
	}

	/**
	 * Validates the item array.
	 *
	 * @param array $item Item data.
	 *
	 * @return bool True if item data is valid, false otherwise.
	 */
	public function validate_item_array( $item ) {
		if ( empty( $item['line_total'] ) ) {
			$item['line_total'] = 0;
		}

		if (
			empty( $item['product_id'] )
			|| empty( $item['name'] )
			|| empty( $item['qty'] )
		) {
			return false;
		}

		if (
			! is_string( $item['name'] )
			|| ! is_numeric( $item['line_total'] )
			|| ! is_numeric( $item['qty'] )
		) {
			return false;
		}

		return true;
	}
}
