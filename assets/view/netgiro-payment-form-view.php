<?php
/**
 * Netgiro payment form view
 *
 * @package WooCommerce-netgiro-plugin
*/
?>
<form action="<?php echo esc_url( $var['gateway_url'] ); ?>" method="post" id="netgiro_payment_form">
		<?php
		foreach ( $var['netgiro_args'] as $key => $value ) {
			?>
				<input type='hidden' name='<?php echo esc_html( $key ); ?>' value='<?php echo esc_html( $value ); ?>'/>
		<?php } ?>
		<?php
		for ( $i = 0; $i <= $var['no_of_items'] - 1; $i++ ) {
			foreach ( $var['items'][ $i ] as $key => $value ) {
				?>
					<input type='hidden' name='Items[<?php echo esc_html( $i ); ?>].<?php echo esc_html( $key ); ?>' value='<?php echo esc_html( $value ); ?>'/>
				<?php
			}
		}
		?>
		
	<p>
	<input type="submit" class="button alt" id="submit_netgiro_payment_form" value="Greiða með Netgíró" /> 
	<a class="button cancel" href="<?php echo esc_url( $var['cancel_order_url'] ); ?>">Hætta við greiðslu</a>
	</p>

</form>
