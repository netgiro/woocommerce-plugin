<?php /**
	   * Plugin Name: Netgíró Payment gateway for Woocommerce
	   * Plugin URI: http://www.netgiro.is
	   * Description: Netgíró Payment gateway for Woocommerce
	   * Author: Netgíró
	   * Author URI: http://www.netgiro.is
	   *
	   * @package WooCommerce-netgiro-plugin
	   */

?>
<h3>Netgíró Payment Gateway</h3>
<p>Verslaðu á netinu með Netgíró á einfaldan hátt.</p>
<?php if ( esc_html( $var['woocommerce_currency'] ) !== 'ISK' ) { ?>
		<div class="">&#9888;
			This payment method only works with <strong>ISK</strong>but default currency is <strong> <?php esc_html( $var['woocommerce_currency'] ); ?></strong>
		</div>
<?php } ?>
<table class="form-table">
	<?php
	echo wp_kses( $var['settings_html'], $var['allowed_html'] );
	?>
</table>
