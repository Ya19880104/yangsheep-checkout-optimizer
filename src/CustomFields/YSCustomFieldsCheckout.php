<?php
/**
 * 前台：在 WooCommerce 傳統結帳的指定位置輸出自訂欄位與同意事項，
 * 送單時驗證必填，並把顧客填寫的內容寫入訂單。
 *
 * 只處理傳統結帳（form.checkout）；Checkout Block／Store API 不受影響。
 * 欄位不經過 woocommerce_checkout_fields，欄位編輯器外掛不會改動它們。
 *
 * @package YangSheep\CheckoutOptimizer\CustomFields
 */

namespace YangSheep\CheckoutOptimizer\CustomFields;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class YSCustomFieldsCheckout {

    public const ORDER_META_KEY = '_ys_checkout_custom_fields';

    /** 位置 → WooCommerce 傳統結帳範本的掛鉤。 */
    private const POSITION_HOOKS = array(
        'before_submit'  => 'woocommerce_review_order_before_submit',
        'after_billing'  => 'woocommerce_after_checkout_billing_form',
        'after_shipping' => 'woocommerce_after_checkout_shipping_form',
        'before_notes'   => 'woocommerce_before_order_notes',
        'after_notes'    => 'woocommerce_after_order_notes',
    );

    /** @var self|null */
    private static $instance = null;

    /** @var array<string,mixed>|null update_order_review 送來的表單內容（每個請求只解析一次） */
    private $posted_form = null;

    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    private function __construct() {
        foreach ( self::POSITION_HOOKS as $position => $hook ) {
            add_action(
                $hook,
                function () use ( $position ) {
                    $this->render_position( $position );
                },
                20
            );
        }
        add_action( 'woocommerce_after_checkout_validation', array( $this, 'validate' ), 20, 2 );
        add_action( 'woocommerce_checkout_create_order', array( $this, 'save_to_order' ), 20, 2 );
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
    }

    public function enqueue_assets() {
        if (
            ! function_exists( 'is_checkout' )
            || ! is_checkout()
            || ( function_exists( 'is_wc_endpoint_url' ) && is_wc_endpoint_url() )
            || ! YSCustomFieldDefinitions::get_enabled_fields()
        ) {
            return;
        }

        wp_enqueue_style(
            'yangsheep-custom-fields',
            YANGSHEEP_CHECKOUT_OPTIMIZATION_URL . 'assets/css/yangsheep-custom-fields.css',
            array(),
            yangsheep_checkout_asset_version( 'assets/css/yangsheep-custom-fields.css' )
        );
        wp_enqueue_script(
            'yangsheep-custom-fields',
            YANGSHEEP_CHECKOUT_OPTIMIZATION_URL . 'assets/js/yangsheep-custom-fields.js',
            array(),
            yangsheep_checkout_asset_version( 'assets/js/yangsheep-custom-fields.js' ),
            true
        );
    }

    /**
     * 輸出某個位置的欄位群組。
     */
    public function render_position( string $position ): void {
        if ( function_exists( 'is_wc_endpoint_url' ) && is_wc_endpoint_url() ) {
            return;
        }

        $fields = array_filter(
            YSCustomFieldDefinitions::get_enabled_fields(),
            static function ( array $field ) use ( $position ): bool {
                return $field['position'] === $position;
            }
        );
        if ( ! $fields ) {
            return;
        }

        echo '<div class="ys-cf-group ys-cf-position-' . esc_attr( $position ) . '">';
        foreach ( $fields as $field ) {
            $this->render_field( $field );
        }
        echo '</div>';
    }

    /**
     * @param array<string,string> $field
     */
    private function render_field( array $field ): void {
        $name      = YSCustomFieldDefinitions::input_name( $field['id'] );
        $required  = 'yes' === $field['required'];
        $has_popup = YSCustomFieldDefinitions::has_popup( $field );
        $dialog_id = $name . '_dialog';
        $value     = $this->redisplay_value( $name );

        $link_html = '';
        if ( $has_popup ) {
            $link_html = sprintf(
                '<a href="#%1$s" class="ys-cf-popup-link" role="button" aria-haspopup="dialog" aria-controls="%1$s">%2$s</a>',
                esc_attr( $dialog_id ),
                esc_html( $field['popup_link'] )
            );
        } elseif ( '' !== $field['popup_link'] ) {
            $link_html = esc_html( $field['popup_link'] );
        }
        $label_html = self::label_html( $field['label'], $link_html );

        $required_html = $required
            ? '&nbsp;<span class="required" aria-hidden="true">*</span>'
            : '';
        $aria_required = $required ? ' aria-required="true"' : '';

        $row_classes = array( 'form-row', 'form-row-wide', 'ys-cf-field', 'ys-cf-type-' . $field['type'] );
        if ( $required ) {
            $row_classes[] = 'validate-required';
        }

        printf(
            '<p class="%1$s" id="%2$s_field" data-ys-cf="%3$s">',
            esc_attr( implode( ' ', $row_classes ) ),
            esc_attr( $name ),
            esc_attr( $field['id'] )
        );

        if ( 'checkbox' === $field['type'] ) {
            printf(
                '<label class="ys-cf-checkbox-label checkbox" for="%1$s"><input type="checkbox" class="input-checkbox ys-cf-input" name="%1$s" id="%1$s" value="1"%2$s%3$s /> <span class="ys-cf-label-text">%4$s</span>%5$s</label>',
                esc_attr( $name ),
                checked( '1', $value, false ),
                $aria_required, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- fixed attribute string.
                $label_html, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in label_html().
                $required_html // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- fixed markup.
            );
        } else {
            printf(
                '<label for="%1$s"><span class="ys-cf-label-text">%2$s</span>%3$s</label><span class="woocommerce-input-wrapper">',
                esc_attr( $name ),
                $label_html, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in label_html().
                $required_html // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- fixed markup.
            );
            if ( 'textarea' === $field['type'] ) {
                printf(
                    '<textarea class="input-text ys-cf-input" name="%1$s" id="%1$s" rows="3" maxlength="%2$d" placeholder="%3$s"%4$s>%5$s</textarea>',
                    esc_attr( $name ),
                    (int) YSCustomFieldDefinitions::MAX_TEXTAREA_VALUE,
                    esc_attr( $field['placeholder'] ),
                    $aria_required, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- fixed attribute string.
                    esc_textarea( $value )
                );
            } else {
                printf(
                    '<input type="text" class="input-text ys-cf-input" name="%1$s" id="%1$s" value="%2$s" maxlength="%3$d" placeholder="%4$s"%5$s />',
                    esc_attr( $name ),
                    esc_attr( $value ),
                    (int) YSCustomFieldDefinitions::MAX_TEXT_VALUE,
                    esc_attr( $field['placeholder'] ),
                    $aria_required // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- fixed attribute string.
                );
            }
            echo '</span>';
        }
        echo '</p>';

        if ( $has_popup ) {
            $this->render_dialog( $field, $dialog_id, 'checkbox' === $field['type'] ? $name : '' );
        }
    }

    /**
     * 彈出視窗：原生 <dialog>，由前台腳本以 showModal() 開啟。
     *
     * @param array<string,string> $field
     */
    private function render_dialog( array $field, string $dialog_id, string $checkbox_id ): void {
        $content = function_exists( 'wpautop' ) ? wpautop( $field['popup_content'] ) : $field['popup_content'];
        ?>
        <dialog id="<?php echo esc_attr( $dialog_id ); ?>" class="ys-cf-dialog" aria-labelledby="<?php echo esc_attr( $dialog_id ); ?>_title">
            <div class="ys-cf-dialog__panel">
                <div class="ys-cf-dialog__header">
                    <h2 class="ys-cf-dialog__title" id="<?php echo esc_attr( $dialog_id ); ?>_title"><?php echo esc_html( $field['popup_link'] ); ?></h2>
                    <button type="button" class="ys-cf-dialog__close" data-ys-cf-close aria-label="<?php esc_attr_e( '關閉', 'yangsheep-checkout-optimization' ); ?>">&times;</button>
                </div>
                <div class="ys-cf-dialog__body"><?php echo wp_kses_post( $content ); ?></div>
                <div class="ys-cf-dialog__footer">
                    <?php if ( '' !== $checkbox_id ) : ?>
                        <button type="button" class="button ys-cf-dialog__agree" data-ys-cf-close data-ys-cf-agree="<?php echo esc_attr( $checkbox_id ); ?>"><?php esc_html_e( '已閱讀並同意', 'yangsheep-checkout-optimization' ); ?></button>
                    <?php else : ?>
                        <button type="button" class="button ys-cf-dialog__agree" data-ys-cf-close><?php esc_html_e( '關閉', 'yangsheep-checkout-optimization' ); ?></button>
                    <?php endif; ?>
                </div>
            </div>
        </dialog>
        <?php
    }

    /**
     * 欄位文字：每段都先跳脫，再把彈出視窗連結放在記號位置（沒有記號就接在最後）。
     */
    private static function label_html( string $label, string $link_html ): string {
        $parts = explode( YSCustomFieldDefinitions::POPUP_TOKEN, $label, 2 );
        if ( 2 === count( $parts ) ) {
            return esc_html( $parts[0] ) . $link_html . esc_html( $parts[1] );
        }

        return esc_html( $label ) . $link_html;
    }

    /**
     * 重新輸出時要帶回顧客剛輸入的值：付款區（下單按鈕上方）會隨每次
     * update_order_review 由伺服器重繪，值在 post_data；一般送單則在 $_POST。
     */
    private function redisplay_value( string $name ): string {
        // phpcs:disable WordPress.Security.NonceVerification.Missing -- 只把顧客自己的輸入重新顯示，不做任何寫入。
        if ( null === $this->posted_form ) {
            $this->posted_form = array();
            if ( isset( $_POST['post_data'] ) && is_string( $_POST['post_data'] ) ) {
                parse_str( wp_unslash( $_POST['post_data'] ), $this->posted_form );
            }
        }
        $raw = $this->posted_form[ $name ] ?? ( isset( $_POST[ $name ] ) ? wp_unslash( $_POST[ $name ] ) : '' );
        // phpcs:enable WordPress.Security.NonceVerification.Missing

        return is_string( $raw ) ? $raw : '';
    }

    /**
     * 送單時讀取並清理顧客輸入（WooCommerce 已在 process_checkout 驗過 nonce）。
     *
     * @param array<string,string> $field
     */
    private function submitted_value( array $field ): string {
        $name = YSCustomFieldDefinitions::input_name( $field['id'] );
        // phpcs:disable WordPress.Security.NonceVerification.Missing
        $raw = isset( $_POST[ $name ] ) ? wp_unslash( $_POST[ $name ] ) : '';
        // phpcs:enable WordPress.Security.NonceVerification.Missing
        if ( ! is_string( $raw ) ) {
            return '';
        }
        if ( 'checkbox' === $field['type'] ) {
            return '' !== $raw ? '1' : '';
        }
        if ( 'textarea' === $field['type'] ) {
            return trim( sanitize_textarea_field( $raw ) );
        }

        return trim( sanitize_text_field( $raw ) );
    }

    /**
     * 只驗證這次結帳畫面上真的會出現的欄位：收件資訊下方的欄位只在需要
     * 運送地址且顧客填寫收件資訊時輸出；其他位置的掛鉤每次結帳都會執行。
     *
     * @param array<string,string> $field
     * @param array<string,mixed>  $data WooCommerce 已整理的結帳資料。
     */
    private function is_displayed( array $field, array $data ): bool {
        if ( 'after_shipping' !== $field['position'] ) {
            return true;
        }
        $cart = function_exists( 'WC' ) && WC() ? WC()->cart : null;

        return $cart && $cart->needs_shipping_address() && ! empty( $data['ship_to_different_address'] );
    }

    /**
     * @param array<string,mixed> $data
     * @param \WP_Error           $errors
     */
    public function validate( $data, $errors ): void {
        if ( ! is_array( $data ) || ! $errors instanceof \WP_Error ) {
            return;
        }

        foreach ( YSCustomFieldDefinitions::get_enabled_fields() as $field ) {
            if ( ! $this->is_displayed( $field, $data ) ) {
                continue;
            }
            $name  = YSCustomFieldDefinitions::input_name( $field['id'] );
            $label = YSCustomFieldDefinitions::plain_label( $field );
            $value = $this->submitted_value( $field );

            if ( 'checkbox' === $field['type'] ) {
                if ( 'yes' === $field['required'] && '1' !== $value ) {
                    /* translators: %s: consent text */
                    $errors->add( 'ys_cf_' . $field['id'], sprintf( __( '請勾選「%s」。', 'yangsheep-checkout-optimization' ), $label ), array( 'id' => $name ) );
                }
                continue;
            }

            if ( '' === $value ) {
                if ( 'yes' === $field['required'] ) {
                    /* translators: %s: field label */
                    $errors->add( 'ys_cf_' . $field['id'], sprintf( __( '請填寫「%s」。', 'yangsheep-checkout-optimization' ), $label ), array( 'id' => $name ) );
                }
                continue;
            }

            $max = 'textarea' === $field['type']
                ? YSCustomFieldDefinitions::MAX_TEXTAREA_VALUE
                : YSCustomFieldDefinitions::MAX_TEXT_VALUE;
            if ( YSCustomFieldDefinitions::length( $value ) > $max ) {
                /* translators: 1: field label, 2: maximum characters */
                $errors->add( 'ys_cf_' . $field['id'], sprintf( __( '「%1$s」最多 %2$d 個字。', 'yangsheep-checkout-optimization' ), $label, $max ), array( 'id' => $name ) );
            }
        }
    }

    /**
     * 把這次結帳顯示過的欄位寫入訂單（含當時的欄位文字，日後改設定不影響舊訂單）。
     *
     * @param \WC_Order           $order
     * @param array<string,mixed> $data
     */
    public function save_to_order( $order, $data ): void {
        if ( ! is_object( $order ) || ! is_callable( array( $order, 'update_meta_data' ) ) || ! is_array( $data ) ) {
            return;
        }

        $entries = array();
        foreach ( YSCustomFieldDefinitions::get_enabled_fields() as $field ) {
            if ( ! $this->is_displayed( $field, $data ) ) {
                continue;
            }
            $value = $this->submitted_value( $field );
            if ( 'checkbox' !== $field['type'] && '' === $value ) {
                continue;
            }
            $entries[] = array(
                'id'    => $field['id'],
                'type'  => $field['type'],
                'label' => YSCustomFieldDefinitions::plain_label( $field ),
                'value' => 'checkbox' === $field['type'] ? ( '1' === $value ? 'yes' : 'no' ) : $value,
            );
        }

        if ( $entries ) {
            $order->update_meta_data( self::ORDER_META_KEY, $entries );
        }
    }
}
