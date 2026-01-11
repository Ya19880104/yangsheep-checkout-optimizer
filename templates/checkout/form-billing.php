<?php
defined('ABSPATH') || exit;
?>
<div class="woocommerce-billing-fields">
    <h3 class="yangsheep-h3-title">訂購人</h3>
    <?php if ( wc_ship_to_billing_address_only() && WC()->cart->needs_shipping() ): ?>
        <h3><?php esc_html_e('Billing &amp; Shipping','woocommerce'); ?></h3>
    <?php endif; ?>
    <?php do_action('woocommerce_before_checkout_billing_form',$checkout); ?>
    <div class="woocommerce-billing-fields__field-wrapper">
        <?php
        $fields = $checkout->get_checkout_fields('billing');
        foreach($fields as $key=>$field) {
            woocommerce_form_field($key,$field,$checkout->get_value($key));
        }
        ?>
        <div class="yangsheep-create-account" style="width:100%;">
        <?php if ( ! is_user_logged_in() && $checkout->is_registration_enabled() ): ?>
            <div class="woocommerce-account-fields">
            <?php if ( ! $checkout->is_registration_required() ): ?>
                <!-- 非強制註冊：顯示 checkbox -->
                <p class="form-row form-row-wide create-account">
                    <label class="woocommerce-form__label">
                        <input type="checkbox" class="input-checkbox"
                               id="createaccount" name="createaccount" value="1"
                               <?php checked($checkout->get_value('createaccount'),true); ?> />
                        <span><?php esc_html_e('建立帳號？','yangsheep-checkout-optimization'); ?></span>
                    </label>
                </p>
            <?php endif; ?>
            <?php do_action('woocommerce_before_checkout_registration_form',$checkout); ?>
            <?php if ( $checkout->get_checkout_fields('account') ): ?>
                <div class="create-account yangsheep-account-fields" <?php if ( ! $checkout->is_registration_required() ) echo 'style="display:none;"'; ?>>
                    <p class="yangsheep-account-note"><?php esc_html_e('註冊成為會員後，帳號為您的電子郵件地址。','yangsheep-checkout-optimization'); ?></p>
                <?php foreach($checkout->get_checkout_fields('account') as $key=>$field): ?>
                    <?php woocommerce_form_field($key,$field,$checkout->get_value($key)); ?>
                <?php endforeach; ?>
                <div class="clear"></div>
                </div>
            <?php endif; ?>
            <?php do_action('woocommerce_after_checkout_registration_form',$checkout); ?>
            </div>
        <?php endif; ?>
        </div>
    </div>
    <?php do_action('woocommerce_after_checkout_billing_form',$checkout); ?>
</div>
