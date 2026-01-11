<?php
defined('ABSPATH') || exit;
if ( is_user_logged_in() || 'no' === get_option('woocommerce_enable_checkout_login_reminder') ) return;
$login_welcome_text = get_option('yangsheep_checkout_login_welcome_text', '');
?>
<div class="yangsheep-login">
    <h3 class="yangsheep-h3-title"><?php esc_html_e('登入會員','yangsheep-checkout-optimization'); ?></h3>
    <div class="yangsheep-login-text">
        <?php echo wp_kses_post($login_welcome_text); ?>
    </div>
    <?php woocommerce_login_form(array('redirect'=>wc_get_checkout_url())); ?>
    <div class="yangsheep-logintext-footer">
        <i><!-- SVG Icon --></i>
        <p style="margin-bottom:0!important;">
            <?php esc_html_e('如您為非會員，可於下方填寫訂購人資訊中設定您會員密碼，結帳完成時將同步建立會員帳號，您的使用者名稱將會是訂購人信箱。','yangsheep-checkout-optimization'); ?>
        </p>
    </div>
</div>
