<?php

/**
 * Netgíró WooCommerce Payment Gateway
 *
 * @package Netgiro\Payments
 * @version 5.0.0
 */

defined('ABSPATH') || exit;

/**
 * Netgiro_Gateway Class
 *
 * Handles the integration between WooCommerce and Netgíró payment services.
 * This class extends WC_Payment_Gateway to implement the Netgíró payment gateway
 * functionality in WooCommerce checkout.
 *
 * @property string $application_id
 * @property string $secretkey
 * @property string $cancel_page_id
 * @property string $payment_gateway_url
 * @property Netgiro_API $api
 */
class Netgiro_Gateway extends WC_Payment_Gateway
{



	const META_KEY_NETGIRO_STATUS = '_netgiro_payment_status';

	const FLAG_AUTHORIZED = 'NETGIRO_AUTHORIZED';
	const FLAG_CONFIRMED  = 'NETGIRO_CONFIRMED';
	const FLAG_REFUNDED   = 'NETGIRO_REFUNDED';


	/** @var Netgiro_API */
	public $api;

	/** @var string */
	public $application_id;

	/** @var string */
	public $secretkey;

	/** @var string */
	public $cancel_page_id;

	/** @var string */
	public $payment_gateway_url;
	/**
	 * Netgiro Payment Gateway
	 *
	 * @property string $id
	 * @property string $method_title
	 * @property string $method_description
	 * @property bool $has_fields
	 * @property array $supports
	 * @property string $title
	 * @property string $description
	 * @property string $application_id
	 * @property string $secretkey
	 * @property string $cancel_page_id
	 * @property string $payment_gateway_url
	 * @property Netgiro_API $api
	 */
	public function __construct()
	{
		$this->id                 = 'netgiro';
		$this->method_title       = __('Netgíró', 'netgiro-payment-gateway-for-woocommerce');
		$this->method_description = __('Accept payments via Netgíró.', 'netgiro-payment-gateway-for-woocommerce');
		$this->has_fields         = false;
		$this->icon               = plugin_dir_url(__FILE__) . '../assets/images/logo_x25_netgiro.png';

		$this->supports = array('products', 'refunds');

		// Load settings.
		$this->init_form_fields();
		$this->init_settings();

		$is_test_mode = ('yes' === $this->get_option('test', 'no'));

		$this->title               = $this->get_option('title');
		$this->description         = $this->get_option('description');
		$this->application_id      = $this->get_option('application_id');
		$this->secretkey           = $this->get_option('secretkey');
		$this->cancel_page_id      = $this->get_option('cancel_page_id');
		$this->payment_gateway_url = $is_test_mode
			? 'https://securepay.test.netgiro.is/'
			: 'https://securepay.netgiro.is/v1/';

		$this->api = new Netgiro_API($is_test_mode, $this->secretkey, $this->application_id);

		add_action('woocommerce_receipt_' . $this->id, array($this, 'generate_payment_form'));
		add_action('woocommerce_api_wc_' . $this->id, array($this, 'process_netgiro_return'));
		add_action('woocommerce_order_status_on-hold_to_processing', array($this, 'maybe_confirm_payment_on_status_change'), 10, 1);
		add_action('woocommerce_order_status_on-hold_to_completed', array($this, 'maybe_confirm_payment_on_status_change'), 10, 1);
		add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
	}

	/**
	 * Initialize form fields.
	 */
	public function init_form_fields(): void
	{
		$this->form_fields = Netgiro_Settings::get_form_fields();
	}

	/**
	 * Process payment in WooCommerce, returning instructions for redirect.
	 *
	 * @param int $order_id The WooCommerce order ID.
	 * @return array
	 */
	public function process_payment($order_id)
	{
		$order = wc_get_order($order_id);

		return array(
			'result'   => 'success',
			'redirect' => $order->get_checkout_payment_url(true),
		);
	}

	/**
	 * Generate the payment form and redirect to Netgíró.
	 *
	 * @param int $order_id The WooCommerce order ID.
	 */
	public function generate_payment_form(int $order_id): void
	{
		$payment_form = new Netgiro_Payment_Form($this);
		$payment_form->generate_netgiro_form($order_id);
	}

	/**
	 * Handle Netgíró return (PaymentSuccessfulURL).
	 *
	 * Validates ng_ parameters, signature, updates order status, and sets initial Netgíró status flag.
	 */
	public function process_netgiro_return(): void
	{
		$logger = wc_get_logger();

		$logger->info('Netgíró return process started.', array('source' => 'netgiro-payment-gateway-for-woocommerce'));
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Processing callback data from Netgíró payment gateway
		$reference_number  = isset($_GET['ng_referenceNumber']) ? sanitize_text_field(wp_unslash($_GET['ng_referenceNumber'])) : '';
		$transaction_id    = isset($_GET['ng_transactionid'])    ? sanitize_text_field(wp_unslash($_GET['ng_transactionid']))    : '';
		$invoice_number    = isset($_GET['ng_invoiceNumber'])    ? sanitize_text_field(wp_unslash($_GET['ng_invoiceNumber']))    : '';
		$total_amount      = isset($_GET['ng_totalAmount'])      ? sanitize_text_field(wp_unslash($_GET['ng_totalAmount']))      : '';
		$status            = isset($_GET['ng_status'])           ? sanitize_text_field(wp_unslash($_GET['ng_status']))           : '';
		$netgiro_signature = isset($_GET['ng_netgiroSignature']) ? sanitize_text_field(wp_unslash($_GET['ng_netgiroSignature'])) : '';
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Processing callback data from Netgíró payment gateway

		if (empty($reference_number) || empty($transaction_id) || empty($invoice_number) || empty($netgiro_signature)) {
			$logger->error('Missing required parameters on return. Redirecting to checkout.', array('source' => 'netgiro-payment-gateway-for-woocommerce'));
			wp_safe_redirect(wc_get_checkout_url());
			exit;
		}

		$order_id = absint($reference_number);
		$order    = wc_get_order($order_id);

		if (! $order instanceof WC_Order) {
			$logger->error("Order with ID {$order_id} not found on return. Redirecting.", array('source' => 'netgiro-payment-gateway-for-woocommerce'));
			wp_safe_redirect(wc_get_checkout_url());
			exit;
		}

		$secret_key     = $this->secretkey;
		$signature_data = $secret_key . $reference_number . $transaction_id . $invoice_number . $total_amount . $status;
		$expected_hash  = hash('sha256', $signature_data);

		if (! hash_equals($expected_hash, $netgiro_signature)) {
			$logger->error(
				'Signature mismatch on return. Marking order as failed.',
				array(
					'source'   => 'netgiro-payment-gateway-for-woocommerce',
					'order_id' => $order_id,
				)
			);
			$order->update_status('failed', __('Netgíró signature mismatch.', 'netgiro-payment-gateway-for-woocommerce'));

			wp_safe_redirect(wc_get_checkout_url());
			exit;
		}

		$order->set_transaction_id($transaction_id);
		$order->save();

		$logger->info(
			sprintf(
				'Return Validated => OrderID: %d, TransactionId: %s, InvoiceNumber: %s, Amount: %s, Status: %s',
				$order_id,
				$transaction_id,
				$invoice_number,
				$total_amount,
				$status
			),
			array('source' => 'netgiro-payment-gateway-for-woocommerce')
		);

		$confirmation_type = $this->get_option('confirmation_type');

		if ('2' === $confirmation_type) {
			$order->update_status(
				'on-hold',
				sprintf(
					// Translators: %1$s is the transaction ID, %2$s is additional transaction information.
					__('Netgíró payment authorized. Please confirm this order by setting its status to Processing or Complete. Transaction ID: %1$s, %2$s', 'netgiro-payment-gateway-for-woocommerce'),
					$transaction_id,
					wc_price(floatval($total_amount))
				)
			);

			update_post_meta($order_id, self::META_KEY_NETGIRO_STATUS, self::FLAG_AUTHORIZED);
			$logger->info(sprintf('Order %d set to on-hold, Netgíró status set to %s.', $order_id, self::FLAG_AUTHORIZED), array('source' => 'netgiro-payment-gateway-for-woocommerce'));
		} else {
			$order->payment_complete($transaction_id);
			$order->add_order_note(
				sprintf(
					// Translators: %1$s is the transaction ID, %2$s is amount
					__('Netgíró payment authorized and has been confirmed. Transaction ID: %1$s, Amount: %2$s', 'netgiro-payment-gateway-for-woocommerce'),
					$transaction_id,
					wc_price(floatval($total_amount))
				)
			);
			update_post_meta($order_id, self::META_KEY_NETGIRO_STATUS, self::FLAG_CONFIRMED);
			$logger->info(sprintf('Order %d payment complete, Netgíró status set to %s.', $order_id, self::FLAG_CONFIRMED), array('source' => 'netgiro-payment-gateway-for-woocommerce'));
		}

		WC()->cart->empty_cart();

		$redirect_url = $this->get_return_url($order);
		$logger->info("Payment update successful. Redirecting user to: {$redirect_url}", array('source' => 'netgiro-payment-gateway-for-woocommerce'));
		wp_safe_redirect($redirect_url);
		exit;
	}

	/**
	 * Check if Netgíró payment needs confirmation when WC order status changes.
	 * This function is triggered when the order status changes from 'on-hold'.
	 * It checks the Netgíró status flag before attempting the API call.
	 *
	 * @param int $order_id
	 */
	public function maybe_confirm_payment_on_status_change(int $order_id): void
	{
		$logger = wc_get_logger();
		$order  = wc_get_order($order_id);

		if (! $order) {
			$logger->warning(sprintf('Order %d not found in maybe_confirm_payment_on_status_change.', $order_id), array('source' => 'netgiro-payment-gateway-for-woocommerce'));
			return;
		}

		if ($order->get_payment_method() !== $this->id) {
			return;
		}

		$netgiro_status = get_post_meta($order_id, self::META_KEY_NETGIRO_STATUS, true);

		$logger->info(sprintf('Status change detected for Order %d (on-hold to %s). Current Netgíró status: %s.', $order_id, $order->get_status(), $netgiro_status ?: 'Not Set'), array('source' => 'netgiro-payment-gateway-for-woocommerce'));

		if (self::FLAG_AUTHORIZED === $netgiro_status) {
			$logger->info(sprintf('Attempting Netgíró confirmation for Order %d as status is %s.', $order_id, self::FLAG_AUTHORIZED), array('source' => 'netgiro-payment-gateway-for-woocommerce'));
			$this->perform_netgiro_confirmation($order);
		} else {
			$logger->info(sprintf('Skipping Netgíró confirmation for Order %d. Status is %s (expected %s).', $order_id, $netgiro_status, self::FLAG_AUTHORIZED), array('source' => 'netgiro-payment-gateway-for-woocommerce'));
		}
	}

	/**
	 * Internal function to perform the actual Netgíró confirmation API call.
	 * Updates the Netgíró status flag on success.
	 *
	 * @param WC_Order $order
	 * @return bool True on success, false on failure.
	 */
	protected function perform_netgiro_confirmation(WC_Order $order): bool
	{
		$order_id       = $order->get_id();
		$logger         = wc_get_logger();
		$transaction_id = $order->get_transaction_id();

		if (! $transaction_id) {
			$note = __('Could not attempt Netgíró confirmation: No transaction ID found.', 'netgiro-payment-gateway-for-woocommerce');
			$order->add_order_note($note);
			$logger->error(sprintf('Order %d: %s', $order_id, $note), array('source' => 'netgiro-payment-gateway-for-woocommerce'));
			return false;
		}

		$logger->info(sprintf('Calling confirm_cart API for Order %d, Transaction ID: %s', $order_id, $transaction_id), array('source' => 'netgiro-payment-gateway-for-woocommerce'));
		$response = $this->api->confirm_cart($transaction_id);

		if (isset($response['success']) && true === $response['success']) {

			$note = sprintf(__('Netgíró payment confirmed successfully.', 'netgiro-payment-gateway-for-woocommerce'), $transaction_id);
			$order->add_order_note($note);
			update_post_meta($order_id, self::META_KEY_NETGIRO_STATUS, self::FLAG_CONFIRMED);

			$logger->info(sprintf('Order %d: %s. Netgíró status updated to %s.', $order_id, $note, self::FLAG_CONFIRMED), array('source' => 'netgiro-payment-gateway-for-woocommerce'));
			return true;
		} else {
			$error_message = $response['message'] ?? __('Unknown error', 'netgiro-payment-gateway-for-woocommerce');
			// Translators: %s error reason
			$note = sprintf(__('Netgíró manual confirmation failed. Reason: %s', 'netgiro-payment-gateway-for-woocommerce'), $error_message);
			$order->add_order_note($note);
			$logger->error(sprintf('Order %d: %s', $order_id, $note), array('source' => 'netgiro-payment-gateway-for-woocommerce'));
			return false;
		}
	}

	/**
	 * Process refunds via Netgíró API.
	 * Checks the Netgíró status flag before attempting refund.
	 * Updates the Netgíró status flag on success.
	 *
	 * @param int    $order_id
	 * @param float  $amount
	 * @param string $reason
	 * @return bool|WP_Error True on success, WP_Error on failure.
	 */
	public function process_refund($order_id, $amount = null, $reason = '')
	{
		$order  = wc_get_order($order_id);
		$logger = wc_get_logger();

		if (! $order) {
			return new WP_Error('netgiro_refund_failed', __('Order not found.', 'netgiro-payment-gateway-for-woocommerce'));
		}

		$transaction_id = $order->get_transaction_id();
		if (empty($transaction_id)) {
			return new WP_Error('netgiro_refund_failed', __('Cannot refund: Missing Netgíró transaction ID.', 'netgiro-payment-gateway-for-woocommerce'));
		}

		$netgiro_status = get_post_meta($order_id, self::META_KEY_NETGIRO_STATUS, true);

		$logger->info(sprintf('Attempting refund for Order %d (Amount: %s). Current Netgíró status: %s.', $order_id, $amount, $netgiro_status ?: 'Not Set'), array('source' => 'netgiro-payment-gateway-for-woocommerce'));

		if (! in_array($netgiro_status, array(self::FLAG_AUTHORIZED, self::FLAG_CONFIRMED))) {
			// Translators: %s is the current payment state from Netgíró.
			$error_msg = sprintf(__('Cannot refund: Payment is not in an authorized or confirmed state (Current state: %s).', 'netgiro-payment-gateway-for-woocommerce'), $netgiro_status ?: 'N/A');
			$logger->warning(sprintf('Order %d: %s', $order_id, $error_msg), array('source' => 'netgiro-payment-gateway-for-woocommerce'));
			return new WP_Error('netgiro_refund_invalid_state', $error_msg);
		}

		$logger->info(sprintf('Calling refund_payment API for Order %d, Transaction ID: %s, Amount: %s', $order_id, $transaction_id, $amount), array('source' => 'netgiro-payment-gateway-for-woocommerce'));
		$response = $this->api->refund_payment($transaction_id, $amount, $reason);

		if (isset($response['success']) && $response['success']) {
			$note = sprintf(
				// Translators: %1$s is the refunded amount.
				__('Netgíró refund successful. Amount: %1$s', 'netgiro-payment-gateway-for-woocommerce'),
				wc_price($amount)
			);
			$order->add_order_note($note);

			update_post_meta($order_id, self::META_KEY_NETGIRO_STATUS, self::FLAG_REFUNDED);

			$logger->info(sprintf('Order %d: %s. Netgíró status updated to %s.', $order_id, $note, self::FLAG_REFUNDED), array('source' => 'netgiro-payment-gateway-for-woocommerce'));
			return true;
		} else {
			$error_message = $response['message'] ?? __('Unknown error during refund.', 'netgiro-payment-gateway-for-woocommerce');
			$logger->error(sprintf('Order %d: Netgíró refund API call failed. Reason: %s', $order_id, $error_message), array('source' => 'netgiro-payment-gateway-for-woocommerce'));
			// Translators: %s is the refunded failure reason.
			$order->add_order_note(sprintf(__('Netgíró refund failed. Reason: %s', 'netgiro-payment-gateway-for-woocommerce'), $error_message));
			// Return WP_Error to WooCommerce
			return new WP_Error('netgiro_refund_api_failed', $error_message);
		}
	}
}
