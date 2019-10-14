<?php

/*
Plugin Name: WooCommerce Netgíró Payment Gateway
Plugin URI: http://www.netheimur.is
Description: Netgíró Payment gateway for Woocommerce
Version: 1.0
Author: Netheimur
Author URI: http://www.netheimur.is
*/

add_action('plugins_loaded', 'woocommerce_netheimur_netgiro_init', 0);

function woocommerce_netheimur_netgiro_init(){
  if(!class_exists('WC_Payment_Gateway')) return;

  class WC_netheimur_netgiro extends WC_Payment_Gateway{
    public function __construct(){
      $this -> id = 'netgiro';
      $this -> medthod_title = 'Netgíró';
      $this -> has_fields = false;
      $this->icon = plugins_url('/logo_x25.png', __FILE__ );

      $this -> init_form_fields();
      $this -> init_settings();

      $this -> title = $this -> settings['title'];
      $this -> description = $this -> settings['description'];
      $this -> gateway_url = $this -> settings['gateway_url'];
      $this -> application_id = $this -> settings['application_id'];
      $this -> secretkey = $this -> settings['secretkey'];
      $this -> redirect_page_id = $this -> settings['redirect_page_id'];
      $this -> cancel_page_id = $this-> settings['cancel_page_id'];

      $this->fourteen_days = $this->settings['fourteen_days'];
      $this->partial_payments = $this->settings['partial_payments'];
      $this->partial_payments_without_interests = $this->settings['partial_payments_without_interests'];

      // Process payment
      $this->init_process_payment();

      if ( version_compare( WOOCOMMERCE_VERSION, '2.0.0', '>=' ) ) {
                add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( &$this, 'process_admin_options' ) );
      } else {
                add_action( 'woocommerce_update_options_payment_gateways', array( &$this, 'process_admin_options' ) );
      }
      add_action('woocommerce_receipt_netgiro', array(&$this, 'receipt_page'));
   }

    function init_form_fields(){

       $this -> form_fields = array(
                'enabled' => array(
                    'title' => __('Enable/Disable', 'netheimur_netgiro'),
                    'type' => 'checkbox',
                    'label' => __('Enable Netgíró Payment Module.', 'netheimur_netgiro'),
                    'default' => 'no'),
                'title' => array(
                    'title' => __('Title', 'netheimur_netgiro'),
                    'type'=> 'text',
                    'description' => __('Title of payment method on checkout page', 'netheimur_netgiro'),
                    'default' => __('Netgíró', 'netheimur_netgiro')),
                'description' => array(
                    'title' => __('Lýsing', 'netheimur_netgiro'),
                    'type' => 'textarea',
                    'description' => __('Description of payment method on checkout page.', 'netheimur_netgiro'),
                    'default' => __('Reikningur sendur í netbanka, greiða þarf innan 14 daga eða Netgíró raðgreiðslur.', 'netheimur_netgiro')),
                'gateway_url' => array(
                    'title' => __('Netgiro gateway URL', 'netheimur_netgiro'),
                    'type'=> 'text',
                    'description' => __('URL for the Netgíró gateway', 'netheimur_netgiro'),
                    'default' => __('https://test.netgiro.is/securepay', 'netheimur_netgiro')),
                'fourteen_days' => array(
                    'title' => __('14 days', 'netheimur_netgiro'),
                    'type' => 'checkbox',
                    'description' => __('Default payment option.', 'netheimur_netgiro'),
                    'default' => 'yes'),
                'partial_payments' => array(
                    'title' => __('Partial payments', 'netheimur_netgiro'),
                    'type' => 'checkbox',
                    'description' => __('Offer partial payments.', 'netheimur_netgiro'),
                    'default' => 'no'),
                'partial_payments_without_interests' => array(
                    'title' => __('Partial payments without interest', 'netheimur_netgiro'),
                    'type' => 'checkbox',
                    'description' => __('Offer partial payments without interests.', 'netheimur_netgiro'),
                    'default' => 'no'),
                'application_id' => array(
                    'title' => __('Application ID', 'netheimur_netgiro'),
                    'type' => 'text',
                    'default'=>'881E674F-7891-4C20-AFD8-56FE2624C4B5',
                    'description' => __('This ApplicationID is available at netgiro.is"')),
                'secretkey' => array(
                    'title' => __('Secret Key', 'netheimur_netgiro'),
                    'type' => 'textarea',
                    'default'=>'YCFd6hiA8lUjZejVcIf/LhRXO4wTDxY0JhOXvQZwnMSiNynSxmNIMjMf1HHwdV6cMN48NX3ZipA9q9hLPb9C1ZIzMH5dvELPAHceiu7LbZzmIAGeOf/OUaDrk2Zq2dbGacIAzU6yyk4KmOXRaSLi8KW8t3krdQSX7Ecm8Qunc/A=',
                    'description' =>  __('Given to Merchant by Netgíró', 'netheimur_netgiro')),
                'redirect_page_id' => array(
                    'title' => __('Return Page'),
                    'type' => 'select',
                    'options' => $this -> get_pages('Select Page'),
                    'description' => "URL of success page"),
                'cancel_page_id' => array(
                    'title' => __('Cancel Page'),
                    'type' => 'select',
                    'options' => $this -> get_pages('Select Page'),
                    'description' => "URL if payment cancelled")
            );
    }

    /**
     *  Options for the admin interface
     **/
    public function admin_options(){
      echo '<h3>'.__('Netgíró Payment Gateway', 'netheimur_netgiro').'</h3>';
      echo '<p>'.__('Verslaðu á netinu með Netgíró á einfaldan hátt.').'</p>';
      echo '<table class="form-table">';
      $this -> generate_settings_html();
      echo '</table>';
    }

    /**
     *  There are no payment fields for netgiro, but we want to show the description if set.
     **/
    function payment_fields(){
        if($this -> description) echo wpautop(wptexturize($this -> description));
    }

    /**
     * Receipt
     **/
    function receipt_page($order){
        echo'<h4>Veldu greiðslumöguleika</h4>';
        echo $this -> generate_netgiro_form($order);
    }

    /**
     * Generate netgiro button link
     **/
    public function generate_netgiro_form($order_id){

      global $woocommerce;
    	$order = new WC_Order( $order_id );
      $txnid = $order_id.'_'.date("ymds");

      $redirect_url = ($this -> redirect_page_id=="" || $this -> redirect_page_id==0)?get_site_url() . "/":get_permalink($this -> redirect_page_id);
      $cancel_url = ($this -> cancel_page_id=="" || $this -> cancel_page_id==0)?get_site_url() . "/":get_permalink($this -> cancel_page_id);

      $total = number_format($order->order_total, 0, '', '');

      $str = $this->secretkey . $order_id . $total . $this->application_id;
      $Signature = hash('sha256', $str);

      // Netgiro arguments
      $netgiro_args = array(
        'ApplicationID'=>$this->application_id,
        'Iframe'=>'false',
        'PaymentSuccessfulURL'=> $redirect_url,
        'PaymentCancelledURL'=>$cancel_url,
        'ReturnCustomerInfo'=>'false',
        'OrderId' => $order_id,
        'TotalAmount'=>$total,
        'Signature' => $Signature,
        'PrefixUrlParameters' => 'true'
        );

      if($order->order_shipping>0) {
        $netgiro_args['ShippingAmount'] = ceil($order->order_shipping);
      }

      if($order->order_discount>0) {
        $netgiro_args['DiscountAmount'] = number_format($order->order_discount,0,',','.');
      }

      $netgiro_args_array = array();
      foreach($netgiro_args as $key => $value){
        $netgiro_args_array[] = "<input type='hidden' name='$key' value='$value'/>";
      }

      // Woocommerce -> Netgiro Items
      foreach ($order->get_items() as $item) {
        $items[] = array(
        'ProductNo'=>$item['product_id'],
        'Name'=> $item['name'],
        'UnitPrice'=>$item['line_total'] / $item['qty'],
        'Amount'=>$item['line_total'],
        'Quantity'=>$item['qty'] * 1000
      );
      }

      // Create Items
      for ($i=0; $i <= count($items)-1; $i++) { 
        foreach($items[$i] as $key => $value){
          $netgiro_items_array[] = "<input type='hidden' name='Items[$i].$key' value='$value'/>";
        }          
      }

      if($this->fourteen_days=='yes') {
        $methods .= '<li class="method_reikningur" id="netgiro-branding-p1"><div class="logo" id="netgiro-branding-p1-logo"><img id="netgiro-branding-p1-image" src="'.plugins_url('netgiro_reikningur.png',__FILE__).'" /></div>
                <input type="radio" name="PaymentOption" selected value="1" value="false" /> <strong id="netgiro-branding-p1-title">Netgíró reikningur</strong><p id="netgiro-branding-p1-text"> </p></li>';
      }
      if($this->partial_payments=='yes') {
        $methods .= '<li class="method_reikningur" id="netgiro-branding-p2"><div class="logo" id="netgiro-branding-p2-logo"><img id="netgiro-branding-p2-image" src="'.plugins_url('netgiro_radgreidslur.png',__FILE__).'" /></div>
                <input type="radio" name="PaymentOption" value="2" value="true" /> <strong id="netgiro-branding-p2-title">Netgíró raðgreiðslur</strong><p id="netgiro-branding-p2-text"> </p></li>';
      }
      if($this->partial_payments_without_interests=='yes') {
        $methods .= '<li class="method_reikningur" id="netgiro-branding-p3"><div class="logo" id="netgiro-branding-p3-logo"><img id="netgiro-branding-p3-image" src="'.plugins_url('netgiro_vaxtalausar-radgreidslur.png',__FILE__).'" /></div>
                <input type="radio" name="PaymentOption" value="3" value="true" /> <strong id="netgiro-branding-p3-title">Netgíró vaxtalausar raðgreiðslur</strong><p id="netgiro-branding-p3-text"> </p></li>';
      }

      $methods_init = 'netgiro.branding.options = { ';
      if($this->fourteen_days=='yes') {
        $methods_init .= 'showP1: true,';
      }
      if($this->partial_payments=='yes') {
        $methods_init .= 'showP2: true,';
      }
      if($this->partial_payments_without_interests=='yes') {
        $methods_init .= 'showP3: true';
      }
      $methods_init .= ' } ';

      return '
      <style>
        #netgiro_methods { padding: 0px; margin: 0xp; }
        #netgiro_methods li { margin: 0px; margin-bottom: -1px; background: #f3f3f3; border: solid 1px #ccc; padding: 15px; color: #676968; list-style:none; }
        #netgiro_methods li .logo { float: right; width: 250px; }
        #netgiro_methods li .logo img { border: none; box-shadow: none;}
        #netgiro_methods strong { font-size: 1.7em; font-weight: bold; }
        #netgiro_methods p { margin: 0px; }
      </style>

      <form action="'.$this -> gateway_url.'" method="post" id="netheimur_payment_form">
          ' . implode('', $netgiro_args_array) . '
          ' . implode('', $netgiro_items_array) . '
          ' . $order_dump . '
  

          <div id="netgiro-branding-container">
            <ul id="netgiro_methods">
                '.$methods.'
            </ul>
          </div>

          <p align="right">
          <input type="submit" class="button alt" id="submit_netgiro_payment_form" value="'.__('Greiða með Netgíró', 'netheimur_netgiro').'" /> 
          <a class="button cancel" href="'.$order->get_cancel_order_url().'">'.__('Cancel order &amp; restore cart', 'netheimur_netgiro').'</a>
          </p>

          <script src="//api.netgiro.is/scripts/netgiro.api.js" type="text/javascript"></script>

          <script>
              jQuery(document).ready(function () {
                  '.$methods_init.'
                  netgiro.branding.init("881E674F-7891-4C20-AFD8-56FE2624C4B5");
              });
          </script>

          <script>
            jQuery(function() {
                jQuery(\'.method_reikningur\').click(function() {
                  jQuery(this).find(\'input\').prop(\'checked\', true);
                });
            });
          </script>
          ';

    }
    /**
     * Process the payment and return the result
     **/
    function process_payment($order_id){
      global $woocommerce;
    	$order = new WC_Order( $order_id );
      return array('result' => 'success', 'redirect' => add_query_arg('order',
          $order->id, add_query_arg('key', $order->order_key, get_permalink(get_option('woocommerce_pay_page_id'))))
      );
    }

    /**
     * Check for valid netgiro server callback
     **/
    function init_process_payment() {
      global $woocommerce;

      if($_GET['ng_signature'] && $_GET['ng_orderid'] && $_GET['ng_confirmationCode'] ) {
          // Get order
          $order = new WC_Order( $_GET['ng_orderid'] );

          // Create hash
          $str = $this->secretkey . $_GET['ng_orderid'];
          $hash = hash('sha256', $str);

          // Check if signature is OK
          if($hash==$_GET['ng_signature']) {

            // Complete order
            $order -> payment_complete();
            $order -> add_order_note('Netgíró payment successful<br/>InvoiceNumber from Netgíró: '.$_REQUEST['ng_invoiceNumber']);
            $order -> add_order_note($this->msg['message']);
            $woocommerce -> cart -> empty_cart();

          } else {

            // Set order status to failed
            $order -> update_status('failed');
            $order -> add_order_note('Failed');
            $order -> add_order_note($this->msg['message']);

          }

        }

    }

    // Get all pages for admin options
    function get_pages($title = false, $indent = true) {
        $wp_pages = get_pages('sort_column=menu_order');
        $page_list = array();
        if ($title) $page_list[] = $title;
        foreach ($wp_pages as $page) {
            $prefix = '';
            // show indented child pages?
            if ($indent) {
                $has_parent = $page->post_parent;
                while($has_parent) {
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
}
   /**
     * Add the Gateway to WooCommerce
     **/
    function woocommerce_add_netheimur_netgiro_gateway($methods) {
        $methods[] = 'WC_netheimur_netgiro';
        return $methods;
    }

    add_filter('woocommerce_payment_gateways', 'woocommerce_add_netheimur_netgiro_gateway' );
}
