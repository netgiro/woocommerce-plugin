<?php 
/**
 * Netgiro payment gateway
 *
 * @package WooCommerce-netgiro-plugin
 */

defined( 'ABSPATH' ) || exit;

/**
 * Netgiro Payment Gateway
 * Provides a Netgíró Payment Gateway for WooCommerce.
 *
 * @class       Netgiro
 * @extends     WC_Payment_Gateway
 */
class Netgiro extends WC_Payment_Gateway {

	public $round_numbers = 'yes';
	/**
	 * Protected varible
	 *
	 * @var      Netgiro_Admin    $admin
	 */
	protected $admin;

	/**
	 * Protected varible
	 *
	 * @var      Netgiro_Refund    $refund
	 */
	protected $refund;

	/**
	 * Protected varible
	 *
	 * @var      Netgiro_Payment_Call    $payment_call
	 */
	protected $payment_call;

	/**
	 * Protected varible
	 *
	 * @var      Netgiro_Payment_Form    $payment_form
	 */
	protected $payment_form;

	/**
	 * Constructs a WC_netgiro instance.
	 */
	public function __construct() {
		require_once plugin_dir_path( __DIR__ ) . 'includes/class-netgiro-admin.php';
		require_once plugin_dir_path( __DIR__ ) . 'includes/class-netgiro-refund.php';
		require_once plugin_dir_path( __DIR__ ) . 'includes/class-netgiro-payment-call.php';
		require_once plugin_dir_path( __DIR__ ) . 'includes/class-netgiro-payment-form.php';
		$this->admin        = new Netgiro_Admin( $this );
		$this->refund       = new Netgiro_Refund( $this );
		$this->payment_call = new Netgiro_Payment_Call( $this );
		$this->payment_form = new Netgiro_Payment_Form( $this );

		$this->id                 = 'netgiro';
		$this->medthod_title      = 'Netgíró';
		$this->method_description = 'Plugin for accepting Netgiro payments with Woocommerce web shop.';
		$this->has_fields         = false;
		$this->icon               = plugins_url( '/assets/images/logo_x25_netgiro.png', dirname( __DIR__ ) . '/WooCommerce-netgiro-plugin.php' );

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
		$this->form_fields = $this->admin->get_form_fields();
	}

	/**
	 *  Options for the admin interface
	 **/
	public function admin_options() {
		render_view(
			'netgiro-admin-view',
			array(
				'woocommerce_currency' => get_woocommerce_currency(),
				'settings_html'        => $this->generate_settings_html( array(), false ),
				'allowed_html'         => $this->allowed_html_tags(),
			)
		);
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
		$this->payment_form->generate_netgiro_form( $order );
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
		$this->payment_call->handle_netgiro_call( true );
	}

	/**
	 * Handle the Netgiro callback.
	 *
	 * @return void
	 */
	public function netgiro_callback() {
		$this->payment_call->handle_netgiro_call( false );
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
		$transaction_id = $this->refund->get_transaction( $order );
		$response       = $this->refund->post_refund( $transaction_id, $amount, $order_id, $reason );

		if ( ! $response['refunded'] ) {
			$order->add_order_note( 'Refund not successful, reason : ' . $response['message'] );
			throw new Exception( esc_html( __( 'Refund not successful, reason: ', 'woocommerce' ) ) . esc_html( $response['message'] ) );
		} else {
			$order->add_order_note( 'Refund successful ' . $response['message'] );
			return true;
		}
	}
	/**
	 * List of html tags allowed for netgiro-admin-view
	 *
	 * @return array Array with all html tags allowed
	 */
	public function allowed_html_tags() {
		$allowed_html = array(
			'tr'       => array(),
			'th'       => array(
				'scope' => array(),
				'class' => array(),
			),
			'label'    => array(
				'for' => array(),
			),
			'td'       => array(
				'class' => array(),
			),
			'fieldset' => array(),
			'legend'   => array(
				'class' => array(),
			),
			'span'     => array(
				'selected' => array(),
				'disabled' => array(),
				'value'    => array(),
			),
			'input'    => array(
				'class'       => array(),
				'type'        => array(),
				'name'        => array(),
				'id'          => array(),
				'placeholder' => array(),
				'value'       => array(),
				'checked'     => array(),
			),
			'br'       => array(),
			'p'        => array(
				'class' => array(),
			),
			'textarea' => array(
				'rows'        => array(),
				'cols'        => array(),
				'class'       => array(),
				'type'        => array(),
				'name'        => array(),
				'id'          => array(),
				'placeholder' => array(),
			),
			'select'   => array(
				'id'    => array(),
				'name'  => array(),
				'class' => array(),
			),
			'option'   => array(
				'selected' => array(),
				'disabled' => array(),
				'value'    => array(),
			),
		);
		return $allowed_html;
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
		if ( get_woocommerce_currency() !== 'ISK' ) {
			$gateway_id = 'netgiro';
			if ( isset( $available_gateways[ $gateway_id ] ) ) {
				unset( $available_gateways[ $gateway_id ] );
			}
		}
		return $available_gateways;
	}
}
