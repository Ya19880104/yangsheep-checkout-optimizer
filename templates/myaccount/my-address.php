<?php
/**
 * My Addresses
 * @version 2.6.0
 */

defined( 'ABSPATH' ) || exit;

$customer_id = get_current_user_id();

if ( ! wc_ship_to_billing_address_only() && wc_shipping_enabled() ) {
    $get_addresses = apply_filters(
        'woocommerce_my_account_get_addresses',
        array(
            'billing'  => __( '訂購人資訊', 'woocommerce' ),
            'shipping' => __( '收件人資訊', 'woocommerce' ),
        ),
        $customer_id
    );
} else {
    $get_addresses = apply_filters(
        'woocommerce_my_account_get_addresses',
        array(
            'billing' => __( '訂購人資訊', 'woocommerce' ),
        ),
        $customer_id
    );
}

$col = 1;
?>

<p><?php echo apply_filters(
    'woocommerce_my_account_my_address_description',
    esc_html__( 'The following addresses will be used on the checkout page by default.', 'woocommerce' )
); ?></p>

<?php if ( ! wc_ship_to_billing_address_only() && wc_shipping_enabled() ) : ?>
    <div class="u-columns woocommerce-Addresses col2-set addresses">
<?php endif; ?>

<?php foreach ( $get_addresses as $name => $address_title ) : ?>
    <?php $address = wc_get_account_formatted_address( $name ); ?>

    <div class="u-column<?php echo ( $col * -1 ) < 0 ? 1 : 2; ?> woocommerce-Address">
        <header class="woocommerce-Address-title title">
            <h3><?php echo esc_html( $address_title ); ?></h3>
            <a href="<?php echo esc_url( wc_get_endpoint_url( 'edit-address', $name ) ); ?>" class="edit">
                <?php echo $address ? esc_html__( 'Edit', 'woocommerce' ) : esc_html__( 'Add', 'woocommerce' ); ?>
            </a>
        </header>
        <address>
            <?php echo $address
                ? wp_kses_post( $address )
                : esc_html_e( 'You have not set up this type of address yet.', 'woocommerce' ); ?>
        </address>
    </div>
    <?php $col = -$col; ?>
<?php endforeach; ?>

<?php if ( ! wc_ship_to_billing_address_only() && wc_shipping_enabled() ) : ?>
    </div>
<?php endif; ?>
