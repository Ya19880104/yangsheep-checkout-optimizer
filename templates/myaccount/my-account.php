<?php
/**
 * My Account page
 * @version 3.5.0
 */

defined( 'ABSPATH' ) || exit;

do_action( 'woocommerce_account_navigation' );
?>

<div class="woocommerce-MyAccount-content">
    <?php do_action( 'woocommerce_account_content' ); ?>
</div>
