<?php

/*
Plugin Name: Netgíró Payment gateway for Woocommerce
Plugin URI: http://www.netgiro.is
Description: Netgíró Payment gateway for Woocommerce
Version: 4.0.2
Author: Netgíró
Author URI: http://www.netgiro.is
WC requires at least: 4.6.0
WC tested up to: 7.2.2
*/

add_action('plugins_loaded', 'woocommerce_netgiro_init', 0);

function woocommerce_netgiro_init()
{
  if (!class_exists('WC_Payment_Gateway')) return;

  function woocommerce_add_netgiro_gateway($methods)
  {
    $methods[] = 'WC_netgiro';
    return $methods;
  }

  add_filter('woocommerce_payment_gateways', 'woocommerce_add_netgiro_gateway');

  class WC_netgiro extends WC_Payment_Gateway
  {
    public function __construct()
    {
      $this->id = 'netgiro';
      $this->medthod_title = 'Netgíró';
      $this->method_description = 'Plugin for accepting Netgiro payments with Woocommerce web shop.';
      $this->has_fields = false;
      $this->icon = plugins_url('/logo_x25.png', __FILE__);

      $this->supports = array(
        'products',
        'refunds'
    );

      $this->init_form_fields();
      $this->init_settings();

      $this->payment_gateway_url = $this->settings['test'] == 'yes' ? 'https://test.netgiro.is/securepay/' : 'https://securepay.netgiro.is/v1/';
      $this->payment_gateway_api_url = $this->settings['test'] == 'yes' ? 'https://test.netgiro.is/api/' : 'https://api.netgiro.is/v1/';

      $this->title = sanitize_text_field($this->settings['title']);
      $this->description = $this->settings['description'];
      $this->gateway_url = sanitize_text_field($this->payment_gateway_url);
      $this->application_id = sanitize_text_field($this->settings['application_id']);
      $this->secretkey = $this->settings['secretkey'];
      if (isset($this->settings['redirect_page_id'])) {
        $this->redirect_page_id = sanitize_text_field($this->settings['redirect_page_id']);
      }
      $this->cancel_page_id = sanitize_text_field($this->settings['cancel_page_id']);

      $this->round_numbers = 'yes';

      add_action('woocommerce_receipt_' . $this->id, array($this, 'receipt_page'));
      add_action('woocommerce_update_options_payment_gateways_' . $this->id, array(&$this, 'process_admin_options'));
      add_action('woocommerce_api_wc_' . $this->id, array($this, 'netgiro_response'));
      add_action('woocommerce_api_wc_' . $this->id . "_callback", array($this, 'netgiro_callback'));
      add_action('woocommerce_order_status_refunded', array( $this, 'process_refund' ));

    }

    function init_form_fields()
    {

      $this->form_fields = array(
        'enabled' => array(
          'title'       => __('Enable/Disable', 'netgiro'),
          'type'        => 'checkbox',
          'label'       => __('Enable Netgíró Payment Module.', 'netgiro'),
          'default'     => 'no'
        ),
        'title' => array(
          'title'       => __('Title', 'netgiro'),
          'type'        => 'text',
          'description' => __('Title of payment method on checkout page', 'netgiro'),
          'default'     => __('Netgíró', 'netgiro')
        ),
        'description'   => array(
          'title'       => __('Lýsing', 'netgiro'),
          'type'        => 'textarea',
          'description' => __('Description of payment method on checkout page.', 'netgiro'),
          'default'     => __('Borgaðu með Netgíró.', 'netgiro')
        ),
        'test' => array(
          'title'       => __('Prófunarumhverfi', 'netgiro_valitor'),
          'type'        => 'checkbox',
          'label'       => __('Senda á prófunarumhverfi Netgíró', 'netgiro'),
          'description' => __('If selected, you need to provide Application ID and Secret Key. Not the production keys for the merchant'),
          'default'     => 'option_is_enabled'
        ),
        'application_id' => array(
          'title'       => __('Application ID', 'netgiro'),
          'type'        => 'text',
          'default'     => '881E674F-7891-4C20-AFD8-56FE2624C4B5',
          'description' => __('Available from https://partner.netgiro.is or provided by Netgíró')
        ),
        'secretkey' => array(
          'title'       => __('Secret Key', 'netgiro'),
          'type'        => 'textarea',
          'description' =>  __('Available from https://partner.netgiro.is or provided by Netgíró', 'netgiro'),
          'default'     => 'YCFd6hiA8lUjZejVcIf/LhRXO4wTDxY0JhOXvQZwnMSiNynSxmNIMjMf1HHwdV6cMN48NX3ZipA9q9hLPb9C1ZIzMH5dvELPAHceiu7LbZzmIAGeOf/OUaDrk2Zq2dbGacIAzU6yyk4KmOXRaSLi8KW8t3krdQSX7Ecm8Qunc/A='
        ),
        'cancel_page_id' => array(
          'title'       => __('Cancel Page'),
          'type'        => 'select',
          'options'     => $this->get_pages('Select Page'),
          'description' => "URL if payment cancelled"
        )
      );
    }

    /**
     *  Options for the admin interface
     **/
    public function admin_options()
    {
      echo '<h3>' . __('Netgíró Payment Gateway', 'netgiro') . '</h3>';
      echo '<p>' . __('Verslaðu á netinu með Netgíró á einfaldan hátt.') . '</p>';
      echo '<table class="form-table">';
      $this->generate_settings_html();
      echo '</table>';
    }

    /**
     *  There are no payment fields for netgiro, but we want to show the description if set.
     **/
    function payment_fields()
    {
      if ($this->description) echo wpautop(wptexturize($this->description));
    }

    /**
     * Receipt
     **/
    function receipt_page($order)
    {
      echo $this->generate_netgiro_form($order);
    }

    function validateItemArray($item)
    {
      if (empty($item['line_total'])) {
        $item['line_total'] = 0;
      }

      if (
        empty($item['product_id'])
        || empty($item['name'])
        || empty($item['qty'])
      ) {
        return FALSE;
      }

      if (
        !is_string($item['name'])
        || !is_numeric($item['line_total'])
        || !is_numeric($item['qty'])
      ) {
        return FALSE;
      }

      return TRUE;
    }

    /**
     * Generate netgiro button link
     **/
    public function generate_netgiro_form($order_id)
    {

      global $woocommerce;

      if (empty($order_id)) {
        return $this->get_error_message();
      }

      $order_id = sanitize_text_field($order_id);
      $order = new WC_Order($order_id);
      $txnid = $order_id . '_' . date("ymds");

      if (!is_numeric($order->get_total())) {
        return $this->get_error_message();
      }

      $round_numbers = $this->round_numbers;
      $payment_Cancelled_url = ($this->cancel_page_id == "" || $this->cancel_page_id == 0) ? get_site_url() . "/" : get_permalink($this->cancel_page_id);
      $payment_Confirmed_url = add_query_arg('wc-api', 'WC_netgiro_callback', home_url('/'));
      $payment_Successful_url = add_query_arg('wc-api', 'WC_netgiro', home_url('/'));
      $order_dump = '';

      $total = round(number_format($order->get_total(), 0, '', ''));

      if ($round_numbers == 'yes') {
        $total = round($total);
      }

      $str = $this->secretkey . $order_id . $total . $this->application_id;
      $Signature = hash('sha256', $str);

      if (!function_exists('get_plugin_data')) {
        require_once(ABSPATH . 'wp-admin/includes/plugin.php');
      }
      $plugin_data = get_plugin_data(__FILE__);
      $plugin_version = $plugin_data['Version'];

      // Netgiro arguments
      $netgiro_args = array(
        'ApplicationID'        => $this->application_id,
        'Iframe'               => 'false',
        'PaymentSuccessfulURL' => $payment_Successful_url,
        'PaymentCancelledURL'  => $payment_Cancelled_url,
        'PaymentConfirmedURL'  => $payment_Confirmed_url,
        'ConfirmationType'     => '0',
        'ReferenceNumber'      => $order_id,
        'TotalAmount'          => $total,
        'Signature'            => $Signature,
        'PrefixUrlParameters'  => 'true',
        'ClientInfo'           => 'System: Woocommerce ' . $plugin_version
      );

      if ($order->get_shipping_total() > 0 && is_numeric($order->get_shipping_total())) {
        $netgiro_args['ShippingAmount'] = ceil($order->get_shipping_total());
      }

      if ($order->get_total_discount() > 0 && is_numeric($order->get_total_discount())) {
        $netgiro_args['DiscountAmount'] = ceil($order->get_total_discount());
      }

      $netgiro_args_array = array();
      foreach ($netgiro_args as $key => $value) {
        $netgiro_args_array[] = "<input type='hidden' name='$key' value='$value'/>";
      }

      // Woocommerce -> Netgiro Items
      foreach ($order->get_items() as $item) {
        $validationPass = $this->validateItemArray($item);

        if (!$validationPass) {
          return $this->get_error_message();
        }

        $unitPrice = $order->get_item_subtotal($item, true, $round_numbers == 'yes');
        $amount = $order->get_line_subtotal($item, true, $round_numbers == 'yes');

        if ($round_numbers == 'yes') {
          $unitPrice = round($unitPrice);
          $amount = round($amount);
        }

        $items[] = array(
          'ProductNo' => $item['product_id'],
          'Name' => $item['name'],
          'UnitPrice' => $unitPrice,
          'Amount' => $amount,
          'Quantity' => $item['qty'] * 1000
        );
      }

      // Create Items
      for ($i = 0; $i <= count($items) - 1; $i++) {
        foreach ($items[$i] as $key => $value) {
          $netgiro_items_array[] = "<input type='hidden' name='Items[$i].$key' value='$value'/>";
        }
      }

      if (!wp_http_validate_url($this->gateway_url) && !wp_http_validate_url($order->get_cancel_order_url())) {
        return $this->get_error_message();
      }

      return '
      <style>
        #netgiro_methods { padding: 0px; margin: 0px; }
        #netgiro_methods li { margin: 0px; margin-bottom: -1px; background: #f3f3f3; border: solid 1px #ccc; padding: 15px; color: #676968; list-style:none; }
        #netgiro_methods li .logo { float: right; width: 250px; }
        #netgiro_methods li .logo img { border: none; box-shadow: none;}
        #netgiro_methods strong { font-size: 1.7em; font-weight: bold; }
        #netgiro_methods p { margin: 0px; }
      </style>

      <form action="' . $this->gateway_url . '" method="post" id="netgiro_payment_form">
          ' . implode('', $netgiro_args_array) . '
          ' . implode('', $netgiro_items_array) . '
          ' . $order_dump . '

          <p align="right">
          <input type="submit" class="button alt" id="submit_netgiro_payment_form" value="' . __('Greiða með Netgíró', 'netgiro') . '" /> 
          <a class="button cancel" href="' . $order->get_cancel_order_url() . '">' . __('Hætta við greiðslu', 'netgiro') . '</a>
          </p>

          <script>
            jQuery(function() {
                jQuery(\'.method_reikningur\').click(function() {
                  jQuery(this).find(\'input\').prop(\'checked\', true);
                });
            });
          </script>

      </form>
          ';
    }

    function get_error_message()
    {
      return 'Villa kom upp við vinnslu beiðni þinnar. Vinsamlega reyndu aftur eða hafðu samband við þjónustuver Netgíró með tölvupósti á netgiro@netgiro.is';
    }

    /**
     * Process the payment and return the result
     **/
    function process_payment($order_id)
    {
      $order = new WC_Order($order_id);

      return array(
        'result'   => 'success',
        'redirect' => $order->get_checkout_payment_url(true)
      );
    }

    function netgiro_response()
    {
      $this->handleNetgiroCall(true);
    }

    function netgiro_callback()
    {
      $this->handleNetgiroCall(false);
    }

    function handleNetgiroCall(bool $doRedirect)
    {
      global $woocommerce;

      $logger = wc_get_logger();

      if ((isset($_GET['ng_netgiroSignature']) && $_GET['ng_netgiroSignature'])
        && $_GET['ng_orderid'] && $_GET['ng_transactionid'] && $_GET['ng_signature']
      ) {

        $signature = sanitize_text_field($_GET['ng_netgiroSignature']);
        $orderId = sanitize_text_field($_GET['ng_orderid']);
        $order = new WC_Order($orderId);
        $secret_key = sanitize_text_field($this->secretkey);
        $invoice_number = sanitize_text_field($_REQUEST['ng_invoiceNumber']);
        $transactionId = sanitize_text_field($_REQUEST['ng_transactionid']);
        $totalAmount = sanitize_text_field($_REQUEST['ng_totalAmount']);
        $status = sanitize_text_field($_REQUEST['ng_status']);

        $str = $secret_key . $orderId . $transactionId . $invoice_number . $totalAmount . $status;
        $hash = hash('sha256', $str);
        
        // correct signature and order is success
        if ($hash == $signature && is_numeric($invoice_number)) {
          $order->payment_complete();
          $order->add_order_note('Netgíró greiðsla tókst<br/>Tilvísunarnúmer frá Netgíró: ' . $invoice_number);
          $woocommerce->cart->empty_cart();
        }
        else {
          $failed_message = 'Netgiro payment failed. Woocommerce order id: ' . $orderId . ' and Netgiro reference no.: ' . $invoice_number . ' does relate to signature: ' . $signature;

          // Set order status to failed
          if (is_bool($order) === false) {
            $logger->debug($failed_message, array('source' => 'netgiro_response'));
            $order->update_status('failed');
            $order->add_order_note($failed_message);
          } else {
            $logger->debug('error netgiro_response - order not found: ' . $orderId, array('source' => 'netgiro_response'));
          }

          wc_add_notice("Ekki tókst að staðfesta Netgíró greiðslu! Vinsamlega hafðu samband við verslun og athugað stöðuna á pöntun þinni nr. " . $orderId, 'error');
        }

        if($doRedirect === true)
        {
          wp_redirect($this->get_return_url($order));
        }

        exit;
      }
    }


    // Get all pages for admin options
    function get_pages($title = false, $indent = true)
    {
      $wp_pages = get_pages('sort_column=menu_order');
      $page_list = array();
      if ($title) $page_list[] = $title;
      foreach ($wp_pages as $page) {
        $prefix = '';
        // show indented child pages?
        if ($indent) {
          $has_parent = $page->post_parent;
          while ($has_parent) {
            $prefix .=  ' - ';
            $next_page = get_page($has_parent);
            $has_parent = $next_page->post_parent;
          }
        }
        // add to page list array array
        $page_list[$page->ID] = $prefix . $page->post_title;
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
     */
    function process_refund( $order_id, $amount = null, $reason = '' ){
      $order = wc_get_order($order_id);
      $totalOrderAmount = $order->get_total(); 
      $totalRefunded = $order->get_total_refunded();
      $newPriceTotal = $totalOrderAmount - $totalRefunded;


      $transactionId = $reason; //TODO  Hér vantar FÆRSLUNÚMER eða transactionId 
      
      if($newPriceTotal == 0){
        $respMsg = $this->postRefundCancel($transactionId, $reason);
      } else {
        $respMsg = $this->postRefundChange($transactionId, $newPriceTotal, $reason); 
      }

      if ($respMsg !== ''){
        throw new Exception(__( $respMsg, 'woocommerce' ));
      }
      return true;
    }

    function postRefundChange($transactionId, $amount, $reason = ""){
      $url = $this->payment_gateway_api_url . 'payment/change';

      $body = json_encode(
        [
          "transactionId" => $transactionId,
          "message"=> "Order Changed in Magento2 store",
          //"referenceNumber"=> "38",
          "totalAmount"=> $amount,
          //"shippingAmount"=> 0,
          //"handlingAmount"=> 0,
          //"discountAmount"=> 0,
          "items"=> [
            [
              //"productNo"=> "",
              //"name"=> "",
              //"description"=> "",
              "amount" => $amount,
              "quantity"=> 1000, 
              "unitPrice" => $amount
            ]
          ],
          //"currentTimeUtc"=> "YYYY-mm-ddThh:mm:ss.mmmZ",
          //"validToTimeUtc"=> "YYYY-mm-ddThh:mm:ss.mmmZ",
          //"description"=> "",
          //"ipAddress"=> ""
        ]);
      
        $nonce = (string) microtime(true) * 10000000;
        $response = wp_remote_post($url, [
          'method' => 'POST',
          'timeout' => 30,
          'headers' => [
            'Content-Type' => 'application/json',
            'netgiro_appkey' => $this->settings['application_id'], 
            'netgiro_nonce' => $nonce,
            'netgiro_signature' => $this->generateSignature([
              $this->settings['secretkey'],
              $nonce,
              $url,
              $body
            ])
          ],
          'body' => $body,
      ]);

      $respBody = json_decode($response['body']);

      if ($respBody->ResultCode == 200) {
        return "";
      } else {
        return $respBody->Message;
      }
    }
    function postRefundCancel($transactionId, $reason = ""){
      $url = $this->payment_gateway_api_url . 'payment/cancel';
      $body = json_encode([
          'transactionId' => $transactionId,
          'description' => $reason,
          'cancelationFeeAmount' => 0
          ]
      );
      $nonce = (string) microtime(true) * 10000000; 
      
      $response = wp_remote_post($url, [
          'method' => 'POST',
          'timeout' => 30,
          'headers' => [
            'Content-Type' => 'application/json',
            'netgiro_appkey' => $this->settings['application_id'], 
            'netgiro_nonce' => $nonce,
            'netgiro_signature' => $this->generateSignature([
              $this->settings['secretkey'],
              $nonce,
              $url,
              $body
            ])
          ],
          'body' => $body,
      ]);


      $respBody = json_decode($body['body']);

      if ($respBody->ResultCode == 200) {
        return "";
      } else {
        return $respBody->Message;
      }
    }

    function generateSignature($hashValues = []){
      $hasString = "";
      foreach ($hashValues as $hashValue) {
        $hasString .= $hashValue;
      }
      return hash('sha256', $hasString);
    }

  }
}
