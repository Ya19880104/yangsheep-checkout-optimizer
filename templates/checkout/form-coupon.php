<?php
defined('ABSPATH') || exit;
if ( ! wc_coupons_enabled() ) return;
?>
<form class="yangsheep_checkout_coupon woocommerce-form-coupon" method="post">
    <div class="yangsheep-inputform form-row">
        <input type="text" name="coupon_code" class="input-text"
               placeholder="<?php esc_attr_e('Coupon code','woocommerce'); ?>"
               id="coupon_code" value="" />
    </div>
    <div class="yangsheep-coupon-button form-row">
        <button type="submit" class="button" name="apply_coupon"
                value="<?php esc_attr_e('Apply coupon','woocommerce'); ?>">
            <?php esc_html_e('Apply coupon','woocommerce'); ?>
        </button>
    </div>
    <div class="clear"></div>
</form>
