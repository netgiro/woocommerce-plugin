<?php 
/**
 * Netgiro payment refund
 *
 * @package WooCommerce-netgiro-plugin
 */

defined( 'ABSPATH' ) || exit;

/**
 * Netgiro Refund
 * Provides a refund functionality for Netgíró Payment Gateway for WooCommerce. 
 *
 * @class       Netgiro_Refund
 * @extends     Netgiro_Template
 */
class Netgiro_Refund extends Netgiro_Template {


	/**
	 * Perform a refund request.
	 *
	 * @param string $transaction_id The transaction ID.
	 * @param float  $amount        The refund amount.
	 * @param int    $order_id        The Order ID.
	 * @param string $reason        The refund reason (optional).
	 * @return array                An array with 'refunded' and 'message' keys.
	 */
	public function post_refund( $transaction_id, $amount, $order_id, $reason = '' ) {
		$url             = $this->payment_gateway_reference->payment_gateway_api_url . 'refund';
		$idempotency_key = $this->makeidempotencykey( $transaction_id, $order_id );
		$body            = wp_json_encode(
			array(
				'transactionId'  => $transaction_id,
				'refundAmount'   => (int) $amount,
				// 'description'=> description.
				'idempotencyKey' => $idempotency_key,
			)
		);
			$response    = wp_remote_post(
				$url,
				array(
					'method'  => 'POST',
					'timeout' => 30,
					'headers' => array(
						'Content-Type' => 'application/json',
						'token'        => $this->payment_gateway_reference->settings['secretkey'],

					),
					'body'    => $body,
				)
			);

		$resp_body = json_decode( $response['body'] );

		if ( 200 === $response['response']['code'] ) {
			return array(
				'refunded' => true,
				// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
				'message'  => $resp_body->Message,
			);
		} else {
			return array(
				'refunded' => false,
				// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
				'message'  => $resp_body->Message,
			);
		}
	}

	/**
	 * Generate an idempotency key for a transaction.
	 *
	 * An idempotency key is a unique identifier that ensures a transaction can be safely retried without
	 * causing duplicate or conflicting operations. This function generates an idempotency key by combining
	 * the provided transaction ID with the number of refunds associated with the order.
	 *
	 * @param string $transaction_id The ID of the transaction.
	 * @param int    $order_id       The ID of the order.
	 * @return string The generated idempotency key.
	 */
	private function makeidempotencykey( $transaction_id, $order_id ) {
		global $wpdb;
		$order_id = (int) $order_id;
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$refunds_rows = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(ID) FROM {$wpdb->prefix}posts WHERE post_parent = %d AND post_type = %s",
				$order_id,
				'shop_order_refund'
			)
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		if ( $wpdb->last_error ) {
			$refunds_rows = 0;
		}
		return $transaction_id . '_' . $refunds_rows;
	}

	/**
	 * Get the transaction ID for a Netgiro payment.
	 *
	 * @param WC_Order $order The WooCommerce order object.
	 * @return string|null    The transaction ID if found, otherwise null.
	 */
	public function get_transaction( $order ) {
		if ( empty( $order ) || empty( $order->id ) ) {
			return null;
		}
		$order_id       = $order->id;
		$payment_method = $order->get_payment_method();
		if ( 'netgiro' !== $payment_method ) {
			return null;
		}
		$value = $order->get_transaction_id();
		// Backwards compatibility.
		if ( empty( $value ) ) {
			$order_notes = wc_get_order_notes(
				array(
					'order_id' => $order_id,
					'type'     => 'order',
				)
			);
			foreach ( $order_notes as $note ) {
				if ( str_contains( $note->content, 'Netgíró greiðsla tókst' ) ) {
					$value = str_replace( 'Netgíró greiðsla tókst<br/>Tilvísunarnúmer frá Netgíró: ', '', $note->content );
				}
			}
		}
		return $value;
	}
}
