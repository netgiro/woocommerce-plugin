<?php

/*
Plugin Name: Netgíró Payment gateway for Woocommerce
Plugin URI: http://www.netgiro.is
Description: Netgíró Payment gateway for Woocommerce
Version: 4.1.1
Author: Netgíró
Author URI: http://www.netgiro.is
WC requires at least: 4.6.0
WC tested up to: 7.6.1
*/

add_action( 'plugins_loaded', 'woocommerce_netgiro_init', 0 );

function woocommerce_netgiro_init() {
	if ( ! class_exists( 'WC_Payment_Gateway' ) ) {
		return;
	}

	require_once plugin_dir_path( __FILE__ ) . 'includes/class-wc-netgiro.php';

	function woocommerce_add_netgiro_gateway( $methods ) {
		$methods[] = 'WC_netgiro';
		return $methods;
	}

	add_filter( 'woocommerce_payment_gateways', 'woocommerce_add_netgiro_gateway' );
}
