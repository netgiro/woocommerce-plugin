/**
 * Plugin Name: Netgíró Payment gateway for Woocommerce
 * Plugin URI: http://www.netgiro.is
 * Description: Netgíró Payment gateway for Woocommerce
 * Author: Netgíró
 * Author URI: http://www.netgiro.is
 *
 * @package WooCommerce-netgiro-plugin
 */

jQuery(
	function () {
		jQuery( ".method_reikningur" ).click(
			function () {
				jQuery( this ).find( "input" ).prop( "checked", true );
			}
		);
	}
);
