<?php

/**
 * Netgíró API Integration Class
 *
 * Manages all HTTP interactions with the Netgíró v1 and Partner APIs,
 * implementing improved structure, logging, and error handling.
 * Refund success is determined by a 200 OK HTTP status.
 *
 * @package Netgiro\Payments
 * @version 5.1.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Netgiro_API Class
 *
 * Handles communication with Netgíró APIs. This class is responsible for
 * all API requests, response processing, error handling, and logging for
 * both the Netgíró v1 API and the Partner API. It implements methods for
 * payment processing, refunds, and transaction status checks.
 */
class Netgiro_API {




	/**
	 * Logger instance.
	 * Initialized if logging is enabled.
	 * @var WC_Logger|null
	 */
	private $logger;

	/**
	 * Base URL for v1 endpoints (Confirm/Cancel).
	 * Includes trailing slash.
	 * @var string
	 */
	protected $api_url;

	/**
	 * Base URL for Partner endpoints (Refund/Transaction).
	 * Includes trailing slash.
	 * @var string
	 */
	protected $partner_api_url;

	/**
	 * Netgíró Secret Key.
	 * Used for v1 signature generation and as the token for Partner API.
	 * @var string
	 */
	protected $secret_key;

	/**
	 * Netgíró App Key.
	 * Used in 'netgiro_appkey' header for v1 endpoints.
	 * @var string
	 */
	protected $app_key;

	/**
	 * HTTP request timeout in seconds.
	 * @var int
	 */
	protected $timeout = 25;


	/**
	 * Constructor
	 *
	 * Sets up API URLs based on test mode and stores credentials.
	 * Initializes the logger if WooCommerce logging is available and enabled (implicitly).
	 *
	 * @param bool   $is_test_mode True if sandbox/test mode is active.
	 * @param string $secret_key   Netgíró Secret key (used for signature and token).
	 * @param string $app_key      Netgíró App key (used in netgiro_appkey header).
	 */
	public function __construct( bool $is_test_mode, string $secret_key, string $app_key ) {
		$this->secret_key = $secret_key;
		$this->app_key    = $app_key;

		if ( $is_test_mode ) {
			$this->api_url         = 'https://api.test.netgiro.is/v1/';
			$this->partner_api_url = 'https://partner-api.test.netgiro.is/';
		} else {
			$this->api_url         = 'https://api.netgiro.is/v1/';
			$this->partner_api_url = 'https://api.netgiro.is/partner/';
		}

		if ( function_exists( 'wc_get_logger' ) ) {
			$this->logger = wc_get_logger();
		}
	}

	/**
	 * Helper method to log messages if logger is available.
	 *
	 * @param string $level   Log level (e.g., 'info', 'debug', 'error', 'warning').
	 * @param string $message Log message.
	 * @param array  $context Optional context array.
	 */
	private function log( string $level, string $message, array $context = array() ): void {
		if ( $this->logger ) {
			$context['source'] = 'netgiro-api';
			$this->logger->log( $level, $message, $context );
		}
	}

	/**
	 * Confirm a Netgíró payment using the v1 API.
	 *
	 * @param string $transaction_id Netgíró transaction ID.
	 * @return array Standard response format: ['success' => bool, 'message' => string, 'data' => array|null, 'wp_error' => WP_Error|null].
	 */
	public function confirm_cart( string $transaction_id ): array {
		$endpoint = 'checkout/ConfirmCart';
		$url      = $this->api_url . $endpoint;
		$payload  = array(
			'transactionId' => $transaction_id,
		);

		$args = $this->build_v1_request_args( $url, $payload );
		if ( is_wp_error( $args ) ) {
			return $this->format_error_response( $args->get_error_message(), null, $args );
		}

		$response = $this->send_request( 'POST', $url, $args );

		return $this->handle_api_response( $response, $endpoint );
	}



	/**
	 * Refund a Netgíró transaction using the Partner API.
	 * Success is determined by receiving a 200 OK HTTP status.
	 *
	 * @param string $transaction_id Netgíró transaction ID.
	 * @param float  $amount         Amount to refund (will be rounded to nearest integer ISK).
	 * @param string $reason         Optional reason for refund.
	 * @return array Standard response format: ['success' => bool, 'message' => string, 'data' => array|null, 'wp_error' => WP_Error|null].
	 */
	public function refund_payment( string $transaction_id, float $amount, string $reason = '' ): array {
		$endpoint = 'refund';
		$url      = $this->partner_api_url . $endpoint;

		$idempotency_key = $this->generate_idempotency_key( $transaction_id . '_' . $amount );

		$payload = array(
			'transactionId'  => $transaction_id,
			'refundAmount'   => (int) round( $amount ),
			'idempotencyKey' => $idempotency_key,
		);

		if ( ! empty( $reason ) ) {
			$payload['reason'] = mb_substr( $reason, 0, 100 );
		}

		$args = $this->build_partner_post_request_args( $payload );
		if ( is_wp_error( $args ) ) {
			return $this->format_error_response( $args->get_error_message(), null, $args );
		}

		$response = $this->send_request( 'POST', $url, $args );

		return $this->handle_refund_response( $response, $endpoint );
	}

	/**
	 * Retrieve transaction details from Netgíró's Partner API (GET).
	 *
	 * @param string $transaction_id Netgíró transaction ID.
	 * @return array Standard response format: ['success' => bool, 'message' => string, 'data' => array|null, 'wp_error' => WP_Error|null].
	 */
	public function get_transaction_status( string $transaction_id ): array {
		$endpoint = 'transaction/' . $transaction_id;
		$url      = $this->partner_api_url . $endpoint;

		$args = $this->build_partner_get_request_args();

		$response = $this->send_request( 'GET', $url, $args );

		if ( is_wp_error( $response ) ) {
			return $this->format_error_response( $response->get_error_message(), null, $response );
		}

		$status_code = wp_remote_retrieve_response_code( $response );
		$raw_body    = wp_remote_retrieve_body( $response );
		$data        = json_decode( $raw_body, true );

		if ( JSON_ERROR_NONE !== json_last_error() ) {
			$json_error = json_last_error_msg();
			$this->log( 'error', "Netgíró GET Invalid JSON Response [$endpoint]: $json_error", array( 'body' => $raw_body ) );
			return $this->format_error_response( 'Invalid JSON response from Netgíró.', null );
		}

		if ( 200 !== $status_code ) {
			$error_message = $data['Message'] ?? $data['message'] ?? wp_remote_retrieve_response_message( $response );
			if ( empty( $error_message ) ) {
				$error_message = "HTTP $status_code received from Netgíró.";
			}
			$this->log(
				'error',
				"Netgíró GET Error [$endpoint]: $error_message",
				array(
					'status'        => $status_code,
					'response_data' => $data,
				)
			);
			return $this->format_error_response( $error_message, $data );
		}

		if ( isset( $data['Success'] ) && false === $data['Success'] ) {
			$error_message = $data['Message'] ?? 'Netgíró returned Success=false.';
			$this->log( 'warning', "Netgíró GET indicated failure [$endpoint]: $error_message", array( 'response_data' => $data ) );
			return $this->format_error_response( $error_message, $data, null, false );
		}

		$this->log( 'info', "Netgíró GET Success [$endpoint]", array( 'response_data' => $data ) );
		return $this->format_success_response( 'Transaction status retrieved successfully.', $data );
	}

	/**
	 * Builds the arguments array for wp_remote_request for Netgíró v1 API calls (POST).
	 *
	 * @param string $url     The full request URL.
	 * @param array  $payload The request body data.
	 * @return array|WP_Error Arguments array for wp_remote_request or WP_Error on failure.
	 */
	private function build_v1_request_args( string $url, array $payload ) {
		$json_body = wp_json_encode( $payload );
		if ( ! $json_body ) {
			$this->log( 'error', 'Failed to JSON-encode v1 request payload.', array( 'payload' => $payload ) );
			return new WP_Error( 'json_encode_failed', 'Failed to JSON-encode request payload.' );
		}

		$nonce          = (string) time();
		$signature_data = $this->secret_key . $nonce . $url . $json_body;
		$signature      = hash( 'sha256', $signature_data );

		$headers = array(
			'Content-Type'        => 'application/json; charset=utf-8',
			'Accept'              => 'application/json',
			'netgiro_appkey'      => $this->app_key,
			'netgiro_nonce'       => $nonce,
			'netgiro_signature'   => $signature,
			'netgiro-api-request' => 'true',
		);

		return array(
			'method'      => 'POST',
			'headers'     => $headers,
			'body'        => $json_body,
			'timeout'     => $this->timeout,
			'data_format' => 'body',
		);
	}

	/**
	 * Builds the arguments array for wp_remote_request for Netgíró Partner API POST calls.
	 *
	 * @param array $payload The request body data.
	 * @return array|WP_Error Arguments array for wp_remote_request or WP_Error on failure.
	 */
	private function build_partner_post_request_args( array $payload ) {
		$json_body = wp_json_encode( $payload );
		if ( ! $json_body ) {
			$this->log( 'error', 'Failed to JSON-encode Partner POST request payload.', array( 'payload' => $payload ) );
			return new WP_Error( 'json_encode_failed', 'Failed to JSON-encode request payload.' );
		}

		$headers = array(
			'Content-Type' => 'application/json; charset=utf-8',
			'Accept'       => 'application/json',
			'token'        => $this->secret_key,
		);

		return array(
			'method'      => 'POST',
			'headers'     => $headers,
			'body'        => $json_body,
			'timeout'     => $this->timeout,
			'data_format' => 'body',
		);
	}

	/**
	 * Builds the arguments array for wp_remote_request for Netgíró Partner API GET calls.
	 *
	 * @return array Arguments array for wp_remote_request.
	 */
	private function build_partner_get_request_args(): array {
		$headers = array(
			'Accept' => 'application/json',
			'token'  => $this->secret_key,
		);

		return array(
			'method'  => 'GET',
			'headers' => $headers,
			'timeout' => $this->timeout,
		);
	}

	/**
	 * Sends the API request using wp_remote_request.
	 * Logs request and response details
	 *
	 * @param string $method HTTP method ('GET', 'POST').
	 * @param string $url    Full request URL.
	 * @param array  $args   Arguments array for wp_remote_request (from build_* methods).
	 *
	 * @return array|WP_Error The raw response array from wp_remote_request on success, WP_Error on failure.
	 */
	private function send_request( string $method, string $url, array $args ) {
		$this->log_request( $method, $url, $args );
		$response = wp_remote_request( $url, $args );
		$this->log_response( $url, $response ); // Log raw response regardless of type

		if ( is_wp_error( $response ) ) {
			$this->log(
				'error',
				'Netgíró WP HTTP Error',
				array(
					'url'           => $url,
					'error_code'    => $response->get_error_code(),
					'error_message' => $response->get_error_message(),
				)
			);
			return $response;
		}

		if ( ! is_array( $response ) || ! isset( $response['response']['code'] ) ) {
			$this->log(
				'error',
				'Invalid response format received from WP HTTP API.',
				array(
					'url'          => $url,
					'response_raw' => $response,
				)
			);
			return new WP_Error( 'invalid_http_response_format', __( 'Invalid response format from API server.', 'netgiro-payment-gateway-for-woocommerce' ) );
		}

		return $response;
	}

	/**
	 * Handles the raw WP HTTP response for standard API calls (Confirm/Cancel).
	 * Parses JSON, checks 'Success' field and HTTP status.
	 *
	 * @param array|WP_Error $response The raw response from send_request().
	 * @param string         $endpoint The API endpoint slug used for context in logs/errors.
	 * @return array Standard response format.
	 */
	private function handle_api_response( $response, string $endpoint ): array {
		if ( is_wp_error( $response ) ) {
			return $this->format_error_response( $response->get_error_message(), null, $response );
		}

		$status_code = wp_remote_retrieve_response_code( $response );
		$raw_body    = wp_remote_retrieve_body( $response );
		$data        = json_decode( $raw_body, true );

		if ( JSON_ERROR_NONE !== json_last_error() ) {
			$json_error = json_last_error_msg();
			$this->log( 'error', "Netgíró Invalid JSON Response [$endpoint]: $json_error", array( 'body' => $raw_body ) );
			return $this->format_error_response( 'Invalid JSON response from Netgíró.', null );
		}

		$is_api_success  = ( isset( $data['Success'] ) && true === $data['Success'] );
		$is_http_success = ( $status_code >= 200 && $status_code < 300 );

		if ( ! $is_http_success || ! $is_api_success ) {
			$error_message = $data['Message'] ?? $data['message'] ?? 'API request failed.';
			if ( ! $is_http_success && ( 'API request failed.' === $error_message ) ) {
				$http_message  = wp_remote_retrieve_response_message( $response );
				$error_message = empty( $http_message ) ? "HTTP $status_code error." : $http_message;
			}
			$this->log(
				'error',
				"Netgíró API Error [$endpoint]: $error_message",
				array(
					'status'            => $status_code,
					'api_success_field' => $data['Success'] ?? null,
					'response_data'     => $data,
				)
			);
			return $this->format_error_response( $error_message, $data, null, $is_api_success );
		}

		$success_message = $data['Message'] ?? 'Operation successful.';
		$this->log( 'info', "Netgíró API Success [$endpoint]: $success_message", array( 'response_data' => $data ) );
		return $this->format_success_response( $success_message, $data );
	}

	/**
	 * Handles the raw WP HTTP response specifically for the REFUND endpoint.
	 * Success is determined solely by HTTP 200 OK status. Body is ignored on success.
	 *
	 * @param array|WP_Error $response The raw response from send_request().
	 * @param string         $endpoint The API endpoint slug ('refund').
	 * @return array Standard response format.
	 */
	private function handle_refund_response( $response, string $endpoint ): array {
		if ( is_wp_error( $response ) ) {
			return $this->format_error_response( $response->get_error_message(), null, $response );
		}

		$status_code = wp_remote_retrieve_response_code( $response );

		if ( 200 === $status_code ) {
			$success_message = 'Refund processed successfully (HTTP 200 OK received).';
			$this->log( 'info', "Netgíró API Success [$endpoint]: $success_message", array( 'status' => $status_code ) );
			return $this->format_success_response( $success_message, null );
		}

		$raw_body      = wp_remote_retrieve_body( $response );
		$data          = json_decode( $raw_body, true );
		$error_message = '';

		if ( JSON_ERROR_NONE === json_last_error() && isset( $data['Message'] ) ) {
			$error_message = $data['Message'];
		} elseif ( JSON_ERROR_NONE === json_last_error() && isset( $data['message'] ) ) {
			$error_message = $data['message'];
		} else {
			// Fallback to HTTP status message if body doesn't contain a clear error message
			$http_message  = wp_remote_retrieve_response_message( $response );
			$error_message = empty( $http_message ) ? "Refund failed with HTTP status $status_code." : $http_message;
		}

		$this->log(
			'error',
			"Netgíró API Error [$endpoint]: $error_message",
			array(
				'status'        => $status_code,
				'response_body' => $raw_body,
			) // Log raw body on error
		);

		// Return error format, include decoded data if available
		return $this->format_error_response( $error_message, is_array( $data ) ? $data : null );
	}



	private function log_request( string $method, string $url, array $args ): void {
		$log_context = array(
			'url'     => $url,
			'method'  => $method,
			'headers' => $args['headers'] ?? array(),
			'body'    => $args['body'] ?? null,
			'timeout' => $args['timeout'] ?? $this->timeout,
		);
		$this->log( 'debug', 'Netgíró API Request Sent', $log_context );
	}

	private function log_response( string $url, $response ): void {
		if ( is_wp_Error( $response ) ) {
			return;
		}
		$status_code = wp_remote_retrieve_response_code( $response );
		$headers     = wp_remote_retrieve_headers( $response );
		$body        = wp_remote_retrieve_body( $response );
		$log_context = array(
			'url'           => $url,
			'status_code'   => $status_code,
			'headers'       => $headers->getAll(),
			'response_body' => $body,
		);
		$level       = ( $status_code >= 400 ) ? 'error' : 'debug';
		$this->log( $level, 'Netgíró API Response Received', $log_context );
	}


	private function format_success_response( string $message, ?array $data ): array {
		return array(
			'success'  => true,
			'message'  => $message,
			'data'     => $data,
			'wp_error' => null,
		);
	}

	private function format_error_response( string $message, ?array $data, ?WP_Error $wp_error = null, bool $api_level_success = true ): array {
		return array(
			'success'           => false,
			'message'           => $message,
			'data'              => $data,
			'wp_error'          => $wp_error,
			'api_level_success' => $api_level_success,

		);
	}

	private function generate_idempotency_key( string $seed ): string {
		return substr( hash( 'sha256', uniqid( $seed . '_', true ) ), 0, 40 );
	}
}
