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
class WC_Netgiro extends WC_Payment_Gateway {

	/**
	 * Constructs a WC_netgiro instance.
	 */
	public function __construct() {
		$this->id                 = 'netgiro';
		$this->medthod_title      = 'Netgíró';
		$this->method_description = 'Plugin for accepting Netgiro payments with Woocommerce web shop.';
		$this->has_fields         = false;
		$this->icon               = plugins_url( '/assets/images/logo_x25.png', dirname( __DIR__ ) . '/WooCommerce-netgiro-plugin.php' );

		$this->supports = array(
			'products',
			'refunds',
		);

		$this->init_form_fields();
		$this->init_settings();

		$this->payment_gateway_url     = 'yes' === $this->settings['test'] ? 'https://test.netgiro.is/securepay/' : 'https://securepay.netgiro.is/v1/';
		$this->payment_gateway_api_url = 'yes' === $this->settings['test'] ? 'https://test.netgiro.is/partnerapi/' : 'https://api.netgiro.is/partner/';

		$this->title          = sanitize_text_field( $this->settings['title'] );
		$this->description    = $this->settings['description'];
		$this->gateway_url    = sanitize_text_field( $this->payment_gateway_url );
		$this->application_id = sanitize_text_field( $this->settings['application_id'] );
		$this->secretkey      = $this->settings['secretkey'];
		if ( isset( $this->settings['redirect_page_id'] ) ) {
			$this->redirect_page_id = sanitize_text_field( $this->settings['redirect_page_id'] );
		}
		$this->cancel_page_id = sanitize_text_field( $this->settings['cancel_page_id'] );

		$this->round_numbers = 'yes';

		add_action( 'woocommerce_receipt_' . $this->id, array( $this, 'receipt_page' ) );
		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( &$this, 'process_admin_options' ) );
		add_action( 'woocommerce_api_wc_' . $this->id, array( $this, 'netgiro_response' ) );
		add_action( 'woocommerce_api_wc_' . $this->id . '_callback', array( $this, 'netgiro_callback' ) );
		add_filter( 'woocommerce_available_payment_gateways', array( $this, 'hide_payment_gateway' ) );

	}

	/**
	 * Initializes form fields.
	 */
	public function init_form_fields() {

		$this->form_fields = array(
			'enabled'        => array(
				'title'   => esc_html__( 'Enable/Disable', 'netgiro' ),
				'type'    => 'checkbox',
				'label'   => esc_html__( 'Enable Netgíró Payment Module.', 'netgiro' ),
				'default' => 'no',
			),
			'title'          => array(
				'title'       => esc_html__( 'Title', 'netgiro' ),
				'type'        => 'text',
				'description' => esc_html__( 'Title of payment method on checkout page', 'netgiro' ),
				'default'     => esc_html__( 'Netgíró', 'netgiro' ),
			),
			'description'    => array(
				'title'       => esc_html__( 'Lýsing', 'netgiro' ),
				'type'        => 'textarea',
				'description' => esc_html__( 'Description of payment method on checkout page.', 'netgiro' ),
				'default'     => esc_html__( 'Borgaðu með Netgíró.', 'netgiro' ),
			),
			'test'           => array(
				'title'       => esc_html__( 'Prófunarumhverfi', 'netgiro_valitor' ),
				'type'        => 'checkbox',
				'label'       => esc_html__( 'Senda á prófunarumhverfi Netgíró', 'netgiro' ),
				'description' => esc_html__( 'If selected, you need to provide Application ID and Secret Key. Not the production keys for the merchant' ),
				'default'     => 'option_is_enabled',
			),
			'application_id' => array(
				'title'       => esc_html__( 'Application ID', 'netgiro' ),
				'type'        => 'text',
				'default'     => '881E674F-7891-4C20-AFD8-56FE2624C4B5',
				'description' => esc_html__( 'Available from https://partner.netgiro.is or provided by Netgíró' ),
			),
			'secretkey'      => array(
				'title'       => esc_html__( 'Secret Key', 'netgiro' ),
				'type'        => 'textarea',
				'description' => esc_html__( 'Available from https://partner.netgiro.is or provided by Netgíró', 'netgiro' ),
				'default'     => 'YCFd6hiA8lUjZejVcIf/LhRXO4wTDxY0JhOXvQZwnMSiNynSxmNIMjMf1HHwdV6cMN48NX3ZipA9q9hLPb9C1ZIzMH5dvELPAHceiu7LbZzmIAGeOf/OUaDrk2Zq2dbGacIAzU6yyk4KmOXRaSLi8KW8t3krdQSX7Ecm8Qunc/A=',
			),
			'cancel_page_id' => array(
				'title'       => esc_html__( 'Cancel Page' ),
				'type'        => 'select',
				'options'     => $this->get_pages( 'Select Page' ),
				'description' => 'URL if payment cancelled',
			),
		);
	}

	/**
	 *  Options for the admin interface
	 **/
	public function admin_options() {
		echo '<h3>' . esc_html__( 'Netgíró Payment Gateway', 'netgiro' ) . '</h3>';
		echo '<p>' . esc_html__( 'Verslaðu á netinu með Netgíró á einfaldan hátt.' ) . '</p>';
		if ( esc_html( get_woocommerce_currency() ) !== 'ISK' ) {
			echo '<div class="">&#9888; ' .
			esc_html__( 'This payment method only works with', 'netgiro' ) .
			' <strong>ISK</strong> ' .
			esc_html__( 'but default currency is', 'netgiro' ) .
			' <strong>' . esc_html( get_woocommerce_currency() ) .
			'</strong></div>';
		}
		echo '<table class="form-table">';
		$this->generate_settings_html();
		echo '</table>';
	}

	/**
	 *  There are no payment fields for netgiro, but we want to show the description if set.
	 **/
	public function payment_fields() {
		if ( $this->description ) {
			echo wp_kses_post( wpautop( wptexturize( $this->description ) ) );
		}
	}

	/**
	 * Receipt Page.
	 *
	 * @param int $order Order number.
	 */
	public function receipt_page( $order ) {

		$allowed_html = array(
			'style'  => array(),
			'form'   => array(
				'action' => true,
				'method' => true,
				'id'     => true,
			),
			'input'  => array(
				'type'  => true,
				'name'  => true,
				'class' => true,
				'id'    => true,
				'value' => true,
			),
			'a'      => array(
				'class' => true,
				'href'  => true,
			),
			'p'      => array(),
			'strong' => array(),
			'li'     => array(
				'style' => true,
				'class' => true,
			),
			'img'    => array(
				'src' => true,
				'alt' => true,
			),
		);
		$output       = $this->generate_netgiro_form( $order );
		echo wp_kses( $output, $allowed_html );
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

		$round_numbers          = $this->round_numbers;
		$payment_cancelled_url  = ( '' === $this->cancel_page_id || 0 === $this->cancel_page_id ) ? get_site_url() . '/' : get_permalink( $this->cancel_page_id );
		$payment_confirmed_url  = add_query_arg( 'wc-api', 'WC_netgiro_callback', home_url( '/' ) );
		$payment_successful_url = add_query_arg( 'wc-api', 'WC_netgiro', home_url( '/' ) );
		$order_dump             = '';

		$total = round( number_format( $order->get_total(), 0, '', '' ) );

		if ( 'yes' === $round_numbers ) {
			$total = round( $total );
		}

		$str       = $this->secretkey . $order_id . $total . $this->application_id;
		$signature = hash( 'sha256', $str );

		if ( ! function_exists( 'get_plugin_data' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		$plugin_data    = get_plugin_data( __FILE__ );
		$plugin_version = $plugin_data['Version'];

		// Netgiro arguments.
		$netgiro_args = array(
			'ApplicationID'        => $this->application_id,
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

		$netgiro_args_array = array();
		foreach ( $netgiro_args as $key => $value ) {
			$netgiro_args_array[] = "<input type='hidden' name='$key' value='$value'/>";
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

		// Create Items.
		$no_of_items = count( $items );
		for ( $i = 0; $i <= $no_of_items - 1; $i++ ) {
			foreach ( $items[ $i ] as $key => $value ) {
				$netgiro_items_array[] = "<input type='hidden' name='Items[$i].$key' value='$value'/>";
			}
		}

		if ( ! wp_http_validate_url( $this->gateway_url ) && ! wp_http_validate_url( $order->get_cancel_order_url() ) ) {
			return $this->get_error_message();
		}

		return '
    <form action="' . $this->gateway_url . '" method="post" id="netgiro_payment_form">
        ' . implode( '', $netgiro_args_array ) . '
        ' . implode( '', $netgiro_items_array ) . '
        ' . $order_dump . '

        <p align="right">
        <input type="submit" class="button alt" id="submit_netgiro_payment_form" value="' . __( 'Greiða með Netgíró', 'netgiro' ) . '" /> 
        <a class="button cancel" href="' . $order->get_cancel_order_url() . '">' . __( 'Hætta við greiðslu', 'netgiro' ) . '</a>
        </p>

    </form>
        ';
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
	 * Process the payment and return the result.
	 *
	 * @param int $order_id The ID of the order to process.
	 * @return array The result of the payment processing and the URL to redirect to.
	 */
	public function process_payment( $order_id ) {
		$order = new WC_Order( $order_id );

		return array(
			'result'   => 'success',
			'redirect' => $order->get_checkout_payment_url( true ),
		);
	}

	/**
	 * Handle the Netgiro response.
	 *
	 * @return void
	 */
	public function netgiro_response() {
		$this->handle_netgiro_call( true );
	}

	/**
	 * Handle the Netgiro callback.
	 *
	 * @return void
	 */
	public function netgiro_callback() {
		$this->handle_netgiro_call( false );
	}

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
			$secret_key     = sanitize_text_field( $this->secretkey );
			$invoice_number = isset( $_REQUEST['ng_invoiceNumber'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['ng_invoiceNumber'] ) ) : '';
			$total_amount   = isset( $_REQUEST['ng_totalAmount'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['ng_totalAmount'] ) ) : '';
			$status         = isset( $_REQUEST['ng_status'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['ng_status'] ) ) : '';

			$str  = $secret_key . $ng_orderid . $ng_transactionid . $invoice_number . $total_amount . $status;
			$hash = hash( 'sha256', $str );

			// correct signature and order is success.
			if ( $hash == $ng_netgiro_signature && is_numeric( $invoice_number ) ) {
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
				wp_redirect( $this->get_return_url( $order ) );
			}

			exit;
		}
	}


	/**
	 * Get all pages for admin options.
	 *
	 * @param bool $title   Whether to include a title in the page list.
	 * @param bool $indent  Whether to show indented child pages.
	 *
	 * @return array        The page list.
	 */
	public function get_pages( $title = false, $indent = true ) {
		$wp_pages  = get_pages( 'sort_column=menu_order' );
		$page_list = array();
		if ( $title ) {
			$page_list[] = $title;
		}
		foreach ( $wp_pages as $page ) {
			$prefix = '';
			// show indented child pages?
			if ( $indent ) {
				$has_parent = $page->post_parent;
				while ( $has_parent ) {
					$prefix    .= ' - ';
					$next_page  = get_page( $has_parent );
					$has_parent = $next_page->post_parent;
				}
			}
			// add to page list array array.
			$page_list[ $page->ID ] = $prefix . $page->post_title;
		}
		return $page_list;
	}

	/**
	 * Process a refund if supported.
	 *
	 * @param  int    $order_id Order ID.
	 * @param  float  $amount Refund amount.
	 * @param  string $reason Refund reason.
	 * @return bool|WP_Error True or false based on success, or a WP_Error object.
	 * @throws Exception      If the refund is not successful.
	 */
	public function process_refund( $order_id, $amount = null, $reason = '' ) {
		$order          = wc_get_order( $order_id );
		$transaction_id = $this->get_transaction( $order );
		$response       = $this->post_refund( $transaction_id, $amount, $reason );

		if ( ! $response['refunded'] ) {
			$order->add_order_note( 'Refund not successful, reason : ' . $response['message'] );
			throw new Exception( __( 'Refund not successful, reason: ', 'woocommerce' ) . $response['message'] );
		} else {
			$order->add_order_note( 'Refund successful ' . $response['message'] );
			return true;
		}
	}

	/**
	 * Perform a refund request.
	 *
	 * @param string $transaction_id The transaction ID.
	 * @param float  $amount        The refund amount.
	 * @param string $reason        The refund reason (optional).
	 * @return array                An array with 'refunded' and 'message' keys.
	 */
	public function post_refund( $transaction_id, $amount, $reason = '' ) {
		$url          = $this->payment_gateway_api_url . 'refund';
		$body         = json_encode(
			array(
				'transactionId'      => $transaction_id,
				'refundAmount'       => (int) $amount,
				// 'description'=> $reason, TODO þetta er lýsing á vöruni, ætti að fá nafn vörunar frá woo
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
						'token'        => $this->settings['secretkey'],
					),
					'body'    => $body,
				)
			);

		$resp_body = json_decode( $response['body'] );

		if ( 200 == $response['response']['code'] ) {
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

	/**
	 * Hide the Netgiro payment gateway if the currency is not ISK.
	 *
	 * @param array $available_gateways The available payment gateways.
	 * @return array                   The modified available payment gateways.
	 */
	public function hide_payment_gateway( $available_gateways ) {
		if ( is_admin() ) {
			return $available_gateways;
		}
		if ( get_woocommerce_currency() != 'ISK' ) {
			$gateway_id = 'netgiro';
			if ( isset( $available_gateways[ $gateway_id ] ) ) {
				unset( $available_gateways[ $gateway_id ] );
			}
		}
		return $available_gateways;
	}
}
