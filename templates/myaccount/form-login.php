<?php
/**
 * Login Form
 * @version 4.1.0
 */

if ( ! defined( 'ABSPATH' ) ) exit;

do_action( 'woocommerce_before_customer_login_form' );
?>

<?php if ( 'yes' === get_option( 'woocommerce_enable_myaccount_registration' ) ) : ?>
<div class="u-columns col2-set" id="customer_login">
    <div class="u-column1 col-1">
<?php endif; ?>

    <h2><?php esc_html_e( 'Login', 'woocommerce' ); ?></h2>
    <form class="woocommerce-form woocommerce-form-login login" method="post">
        <?php do_action( 'woocommerce_login_form_start' ); ?>

        <p class="form-row form-row-wide">
            <label for="username"><?php esc_html_e( 'Username or email address', 'woocommerce' ); ?>&nbsp;<span class="required">*</span></label>
            <input type="text" name="username" id="username" autocomplete="username" class="input-text" value="<?php echo esc_attr( wp_unslash( $_POST['username'] ?? '' ) ); ?>" />
        </p>
        <p class="form-row form-row-wide">
            <label for="password"><?php esc_html_e( 'Password', 'woocommerce' ); ?>&nbsp;<span class="required">*</span></label>
            <input type="password" name="password" id="password" autocomplete="current-password" class="input-text" />
        </p>

        <?php do_action( 'woocommerce_login_form' ); ?>

        <p class="form-row">
            <label class="woocommerce-form__label woocommerce-form__label-for-checkbox">
                <input name="rememberme" type="checkbox" id="rememberme" value="forever" class="input-checkbox" />
                <span><?php esc_html_e( 'Remember me', 'woocommerce' ); ?></span>
            </label>
            <?php wp_nonce_field( 'woocommerce-login', 'woocommerce-login-nonce' ); ?>
            <button type="submit" class="woocommerce-button button" name="login">
                <?php esc_html_e( 'Log in', 'woocommerce' ); ?>
            </button>
        </p>

        <p class="woocommerce-LostPassword lost_password">
            <a href="<?php echo esc_url( wp_lostpassword_url() ); ?>">
                <?php esc_html_e( 'Lost your password?', 'woocommerce' ); ?>
            </a>
        </p>

        <div class="yangsheep-nx-login">
            <?php if ( class_exists('NextendSocialLogin') ) {
                NextendSocialLogin::renderButtonsWithContainer();
            } ?>
        </div>

        <?php do_action( 'woocommerce_login_form_end' ); ?>
    </form>

<?php if ( 'yes' === get_option( 'woocommerce_enable_myaccount_registration' ) ) : ?>
    </div>
    <div class="u-column2 col-2">
        <?php wc_get_template( 'myaccount/form-login.php' ); ?>
    </div>
</div>
<?php endif; ?>

<?php do_action( 'woocommerce_after_customer_login_form' ); ?>