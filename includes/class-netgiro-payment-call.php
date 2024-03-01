<?php 
/**
 * Netgiro payment call
 *
 * @package WooCommerce-netgiro-plugin
 */

defined( 'ABSPATH' ) || exit;


/**
 * Netgiro Payment Call
 * Provides a Netgíró Payment Call for WooCommerce
 *
 * @class       Netgiro_Payment_Call
 * @extends     Netgiro_Template
 */
class Netgiro_Payment_Call extends Netgiro_Template {


	/**
	 * Process the Netgiro call.
	 *
	 * @param bool $do_redirect Whether to redirect after handling the call.
	 * @return void
	 */
	public function handle_netgiro_call( bool $do_redirect ) {
		global $woocommerce;
		$logger = wc_get_logger();

		$ng_netgiro_signature = isset( $_GET['ng_netgiroSignature'] ) ? sanitize_text_field( wp_unslash( $_GET['ng_netgiroSignature'] ) ) : false;
		$ng_orderid           = isset( $_GET['ng_orderid'] ) ? sanitize_text_field( wp_unslash( $_GET['ng_orderid'] ) ) : false;
		$ng_transactionid     = isset( $_GET['ng_transactionid'] ) ? sanitize_text_field( wp_unslash( $_GET['ng_transactionid'] ) ) : false;
		$ng_signature         = isset( $_GET['ng_signature'] ) ? sanitize_text_field( wp_unslash( $_GET['ng_signature'] ) ) : false;

		if ( $ng_netgiro_signature && $ng_orderid && $ng_transactionid && $ng_signature ) {

			$order          = new WC_Order( $ng_orderid );
			$secret_key     = sanitize_text_field( $this->payment_gateway_reference->secretkey );
			$invoice_number = isset( $_REQUEST['ng_invoiceNumber'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['ng_invoiceNumber'] ) ) : '';
			$total_amount   = isset( $_REQUEST['ng_totalAmount'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['ng_totalAmount'] ) ) : '';
			$status         = isset( $_REQUEST['ng_status'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['ng_status'] ) ) : '';

			$str  = $secret_key . $ng_orderid . $ng_transactionid . $invoice_number . $total_amount . $status;
			$hash = hash( 'sha256', $str );

			// correct signature and order is success.
			if ( $hash === $ng_netgiro_signature && is_numeric( $invoice_number ) ) {
				$order->payment_complete();
				$order->add_order_note( 'Netgíró greiðsla tókst<br/>Tilvísunarnúmer frá Netgíró: ' . $invoice_number );
				$order->set_transaction_id( sanitize_text_field( $ng_transactionid ) );
				$order->save();
				$woocommerce->cart->empty_cart();
			} else {
				$failed_message = 'Netgiro payment failed. Woocommerce order id: ' . $ng_orderid . ' and Netgiro reference no.: ' . $invoice_number . ' does relate to signature: ' . $ng_netgiro_signature;

				// Set order status to failed.
				if ( is_bool( $order ) === false ) {
					$logger->debug( $failed_message, array( 'source' => 'netgiro_response' ) );
					$order->update_status( 'failed' );
					$order->add_order_note( $failed_message );
				} else {
					$logger->debug( 'error netgiro_response - order not found: ' . $ng_orderid, array( 'source' => 'netgiro_response' ) );
				}

				wc_add_notice( 'Ekki tókst að staðfesta Netgíró greiðslu! Vinsamlega hafðu samband við verslun og athugað stöðuna á pöntun þinni nr. ' . $ng_orderid, 'error' );
			}

			if ( true === $do_redirect ) {
				wp_safe_redirect( $this->payment_gateway_reference->get_return_url( $order ) );
			}

			exit;
		}
	}
}
