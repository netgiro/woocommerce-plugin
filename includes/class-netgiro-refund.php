<?php
/**
 * Plugin Name: Netgíró Payment gateway for Woocommerce
 * Plugin URI: http://www.netgiro.is
 * Description: Netgíró Payment gateway for Woocommerce
 * Version: 4.1.1
 * Author: Netgíró
 * Author URI: http://www.netgiro.is
 * WC requires at least: 4.6.0
 * WC tested up to: 7.6.1
 *
 * @package WooCommerce-netgiro-plugin
 */

/**
 * WC_netgiro Payment Gateway
 *
 * Provides a Netgíró Payment Gateway for WooCommerce.
 *
 * @class       WC_netgiro
 * @extends     WC_Payment_Gateway
 * @version     4.1.1
 * @package     WooCommerce-netgiro-plugin
 */
class Netgiro_Refund extends Netgiro_Template {


	/**
	 * Perform a refund request.
	 *
	 * @param string $transaction_id The transaction ID.
	 * @param float  $amount        The refund amount.
	 * @param string $reason        The refund reason (optional).
	 * @return array                An array with 'refunded' and 'message' keys.
	 */
	public function post_refund( $transaction_id, $amount, $reason = '' ) {
		$url          = $this->payment_gateway_reference->payment_gateway_api_url . 'refund';
		$body         = wp_json_encode(
			array(
				'transactionId'  => $transaction_id,
				'refundAmount'   => (int) $amount,
				// 'description'=> description.
				'idempotencyKey' => $transaction_id,
			)
		);
			$response = wp_remote_post(
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