<form action="<?=$var['gateway_url']?>" method="post" id="netgiro_payment_form">
        <?php
		foreach ( $var['netgiro_args'] as $key => $value ) {
            ?>
			    <input type='hidden' name='<?=$key?>' value='<?=$value?>'/>
		<?php } ?>
        <?php
        for ( $i = 0; $i <= $var['no_of_items'] - 1; $i++ ) {
            foreach ( $var['items'][ $i ] as $key => $value ) {
                ?>
                    <input type='hidden' name='Items[<?=$i?>].<?=$key?>' value='<?=$value?>'/>
                <?php
            }
		}
        ?>
        
    <p align="right">
    <input type="submit" class="button alt" id="submit_netgiro_payment_form" value="Greiða með Netgíró" /> 
    <a class="button cancel" href="<?=$var['cancel_order_url']?>">Hætta við greiðslu</a>
    </p>

</form>