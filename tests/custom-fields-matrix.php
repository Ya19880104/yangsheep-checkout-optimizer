<?php
/**
 * 結帳自訂欄位與同意事項（v1.8.0）：輸出、驗證、寫入訂單與訂單顯示。
 * Run: php tests/custom-fields-matrix.php
 *
 * 以可控制的 WordPress／WooCommerce 替身載入實際的 src 類別；不連資料庫、不建立訂單。
 */

declare(strict_types=1);

namespace YangSheep\CheckoutOptimizer\Settings {
    final class YSSettingsManager {
        public static array $values = array();
        public static function get( string $key, $default = null ) {
            return self::$values[ $key ] ?? $default;
        }
    }
}

namespace {
    define( 'ABSPATH', __DIR__ . '/' );

    $GLOBALS['ys_hooks'] = array();
    function add_action( $hook, $callback, $priority = 10, $args = 1 ) {
        $GLOBALS['ys_hooks'][] = compact( 'hook', 'callback', 'priority', 'args' );
    }
    function __( $text, $domain = '' ) { return $text; }
    function esc_html( $text ) { return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' ); }
    function esc_attr( $text ) { return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' ); }
    function esc_textarea( $text ) { return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' ); }
    function esc_html__( $text, $domain = '' ) { return esc_html( $text ); }
    function esc_html_e( $text, $domain = '' ) { echo esc_html( $text ); }
    function esc_attr_e( $text, $domain = '' ) { echo esc_attr( $text ); }
    function checked( $a, $b, $echo = true ) {
        $out = (string) $a === (string) $b ? " checked='checked'" : '';
        if ( $echo ) { echo $out; }
        return $out;
    }
    function wp_unslash( $value ) { return is_string( $value ) ? stripslashes( $value ) : $value; }
    function wp_strip_all_tags( $text ) { return strip_tags( (string) $text ); }
    function wp_kses_post( $html ) { return preg_replace( '#<script\b[^>]*>.*?</script>#is', '', (string) $html ); }
    function wpautop( $text ) { return '<p>' . $text . '</p>'; }
    function sanitize_text_field( $text ) { return trim( preg_replace( '/\s+/', ' ', strip_tags( (string) $text ) ) ); }
    function sanitize_textarea_field( $text ) { return trim( strip_tags( (string) $text ) ); }
    function is_wc_endpoint_url( $endpoint = false ) { return $GLOBALS['ys_endpoint'] ?? false; }

    class WP_Error {
        public array $errors = array();
        public array $error_data = array();
        public function add( $code, $message, $data = '' ) {
            $this->errors[ $code ][] = $message;
            $this->error_data[ $code ] = $data;
        }
    }
    final class FakeCart {
        public bool $needs_address = true;
        public function needs_shipping_address(): bool { return $this->needs_address; }
    }
    final class FakeOrder {
        public array $meta = array();
        public function update_meta_data( $key, $value ) { $this->meta[ $key ] = $value; }
        public function get_meta( $key, $single = true ) { return $this->meta[ $key ] ?? ''; }
    }
    $GLOBALS['ys_wc'] = (object) array( 'cart' => new FakeCart() );
    function WC() { return $GLOBALS['ys_wc']; }

    $root = dirname( __DIR__ );
    require $root . '/src/CustomFields/YSCustomFieldDefinitions.php';
    require $root . '/src/CustomFields/YSCustomFieldsCheckout.php';
    require $root . '/src/CustomFields/YSCustomFieldsOrderDisplay.php';

    use YangSheep\CheckoutOptimizer\CustomFields\YSCustomFieldDefinitions;
    use YangSheep\CheckoutOptimizer\CustomFields\YSCustomFieldsCheckout;
    use YangSheep\CheckoutOptimizer\CustomFields\YSCustomFieldsOrderDisplay;
    use YangSheep\CheckoutOptimizer\Settings\YSSettingsManager;

    $total  = 0;
    $failed = 0;
    function check( bool $condition, string $message ): void {
        global $total, $failed;
        ++$total;
        if ( ! $condition ) {
            ++$failed;
        }
        echo ( $condition ? 'PASS ' : 'FAIL ' ) . $message . "\n";
    }

    function field( array $overrides ): array {
        return array_merge(
            array(
                'id'            => 'cf_consent1',
                'type'          => 'checkbox',
                'label'         => '我已閱讀並同意{popup}，並了解出貨時間',
                'popup_link'    => '購買須知',
                'popup_content' => "出貨前會確認庫存。\n<script>alert(1)</script>",
                'placeholder'   => '',
                'position'      => 'before_submit',
                'required'      => 'yes',
                'enabled'       => 'yes',
            ),
            $overrides
        );
    }

    YSSettingsManager::$values['yangsheep_checkout_custom_fields'] = array(
        field( array() ),
        field( array( 'id' => 'cf_taxid1', 'type' => 'text', 'label' => '統一編號 & "抬頭"', 'popup_link' => '', 'popup_content' => '', 'placeholder' => '8 碼數字', 'required' => 'no' ) ),
        field( array( 'id' => 'cf_note001', 'type' => 'textarea', 'label' => '送禮卡片內容', 'popup_link' => '說明', 'popup_content' => '', 'position' => 'after_notes', 'required' => 'yes' ) ),
        field( array( 'id' => 'cf_ship001', 'type' => 'text', 'label' => '管理室代收', 'popup_link' => '', 'popup_content' => '', 'position' => 'after_shipping', 'required' => 'yes' ) ),
        field( array( 'id' => 'cf_off0001', 'label' => '停用的欄位', 'enabled' => 'no' ) ),
    );

    $checkout = YSCustomFieldsCheckout::get_instance();
    $display  = YSCustomFieldsOrderDisplay::get_instance();

    $hooks = array();
    foreach ( $GLOBALS['ys_hooks'] as $hook ) {
        $hooks[ $hook['hook'] ][] = $hook;
    }
    check(
        isset( $hooks['woocommerce_review_order_before_submit'], $hooks['woocommerce_after_checkout_billing_form'], $hooks['woocommerce_after_checkout_shipping_form'], $hooks['woocommerce_before_order_notes'], $hooks['woocommerce_after_order_notes'] )
        && 20 === $hooks['woocommerce_review_order_before_submit'][0]['priority'],
        'every position is attached to its classic checkout template hook'
    );
    check(
        2 === ( $hooks['woocommerce_after_checkout_validation'][0]['args'] ?? 0 )
        && 2 === ( $hooks['woocommerce_checkout_create_order'][0]['args'] ?? 0 ),
        'validation and order creation receive the posted data'
    );
    check(
        isset( $hooks['woocommerce_admin_order_data_after_order_details'], $hooks['woocommerce_email_order_meta'], $hooks['woocommerce_order_details_after_order_table'] ),
        'order display covers the admin order page, emails and the customer order view'
    );

    $reset = function () use ( $checkout ) {
        $property = new ReflectionProperty( YSCustomFieldsCheckout::class, 'posted_form' );
        $property->setValue( $checkout, null );
    };
    $render = function ( string $position ) use ( $checkout, $reset ): string {
        $reset();
        ob_start();
        $checkout->render_position( $position );
        return (string) ob_get_clean();
    };

    // ===== 輸出 =====
    $_POST = array();
    $html = $render( 'before_submit' );
    check( str_contains( $html, 'class="ys-cf-group ys-cf-position-before_submit"' ), 'before-submit fields render in their own group' );
    check(
        str_contains( $html, '我已閱讀並同意<a href="#ys_cf_consent1_dialog" class="ys-cf-popup-link" role="button" aria-haspopup="dialog" aria-controls="ys_cf_consent1_dialog">購買須知</a>，並了解出貨時間' ),
        'the popup link replaces the {popup} token inside the consent text'
    );
    check(
        str_contains( $html, 'name="ys_cf_consent1" id="ys_cf_consent1" value="1"' )
        && str_contains( $html, 'validate-required' )
        && str_contains( $html, 'aria-required="true"' )
        && ! str_contains( $html, " checked='checked'" ),
        'a required consent checkbox renders unchecked with WooCommerce required hooks'
    );
    check(
        str_contains( $html, '<dialog id="ys_cf_consent1_dialog" class="ys-cf-dialog" aria-labelledby="ys_cf_consent1_dialog_title">' )
        && str_contains( $html, 'data-ys-cf-agree="ys_cf_consent1"' )
        && str_contains( $html, '<p>出貨前會確認庫存。' )
        && ! str_contains( $html, '<script' ),
        'the dialog renders sanitized content and an agree action bound to its checkbox'
    );
    check(
        str_contains( $html, '統一編號 &amp; &quot;抬頭&quot;' )
        && str_contains( $html, 'placeholder="8 碼數字"' )
        && ! str_contains( $html, 'ys_cf_taxid1_dialog' ),
        'text labels are escaped and fields without popup content get no dialog'
    );
    check( ! str_contains( $html, 'ys_cf_off0001' ) && ! str_contains( $html, 'ys_cf_note001' ), 'disabled fields and other positions are not rendered here' );

    $notes = $render( 'after_notes' );
    check(
        str_contains( $notes, '<textarea class="input-text ys-cf-input" name="ys_cf_note001"' )
        && str_contains( $notes, '送禮卡片內容說明' )
        && ! str_contains( $notes, 'ys-cf-popup-link' ),
        'a link text without popup content is shown as plain text'
    );
    check( '' === $render( 'after_billing' ), 'positions without enabled fields output nothing' );

    $_POST = array( 'post_data' => http_build_query( array( 'ys_cf_consent1' => '1', 'ys_cf_taxid1' => '12345678' ) ) );
    $redraw = $render( 'before_submit' );
    check(
        str_contains( $redraw, " checked='checked'" ) && str_contains( $redraw, 'value="12345678"' ),
        'payment-area fields keep the shopper input when update_order_review redraws them'
    );
    $_POST = array();

    $GLOBALS['ys_endpoint'] = true;
    check( '' === $render( 'before_submit' ), 'nothing renders on order-pay or order-received endpoints' );
    $GLOBALS['ys_endpoint'] = false;

    // ===== 驗證 =====
    $validate = function ( array $post, array $data = array( 'ship_to_different_address' => 1 ) ) use ( $checkout ): WP_Error {
        $_POST  = $post;
        $errors = new WP_Error();
        $checkout->validate( $data, $errors );
        return $errors;
    };

    $errors = $validate( array( 'ys_cf_note001' => '卡片', 'ys_cf_ship001' => '可' ) );
    check(
        array( '請勾選「我已閱讀並同意購買須知，並了解出貨時間」。' ) === ( $errors->errors['ys_cf_cf_consent1'] ?? null )
        && array( 'id' => 'ys_cf_consent1' ) === ( $errors->error_data['ys_cf_cf_consent1'] ?? null ),
        'an unchecked required consent blocks checkout and points at its checkbox'
    );
    check( ! isset( $errors->errors['ys_cf_cf_taxid1'] ), 'an empty optional text field passes' );
    check( ! isset( $errors->errors['ys_cf_cf_off0001'] ), 'disabled required fields are never enforced' );

    $errors = $validate( array( 'ys_cf_consent1' => '1', 'ys_cf_note001' => '   ', 'ys_cf_ship001' => '可' ) );
    check(
        ! isset( $errors->errors['ys_cf_cf_consent1'] )
        && array( '請填寫「送禮卡片內容說明」。' ) === ( $errors->errors['ys_cf_cf_note001'] ?? null ),
        'a checked consent passes and a whitespace-only required textarea fails'
    );

    $errors = $validate( array( 'ys_cf_consent1' => '1', 'ys_cf_note001' => '卡片', 'ys_cf_ship001' => '可', 'ys_cf_taxid1' => str_repeat( '字', 201 ) ) );
    check( isset( $errors->errors['ys_cf_cf_taxid1'] ) && 1 === count( $errors->errors ), 'single-line text is limited to 200 characters' );

    $errors = $validate( array( 'ys_cf_consent1' => '1', 'ys_cf_note001' => '卡片' ), array( 'ship_to_different_address' => 0 ) );
    check( array() === $errors->errors, 'recipient-section fields are skipped when the recipient section is not submitted' );

    WC()->cart->needs_address = false;
    $errors = $validate( array( 'ys_cf_consent1' => '1', 'ys_cf_note001' => '卡片' ) );
    check( array() === $errors->errors, 'recipient-section fields are skipped when the cart needs no shipping address' );
    WC()->cart->needs_address = true;

    $errors = $validate( array( 'ys_cf_consent1' => '1', 'ys_cf_note001' => '卡片' ) );
    check( array( '請填寫「管理室代收」。' ) === ( $errors->errors['ys_cf_cf_ship001'] ?? null ), 'recipient-section required fields are enforced when that section is submitted' );

    $errors = $validate( array( 'ys_cf_consent1' => array( '1' ), 'ys_cf_note001' => '卡片', 'ys_cf_ship001' => '可' ) );
    check( isset( $errors->errors['ys_cf_cf_consent1'] ), 'array-shaped input cannot satisfy a required consent' );

    // ===== 寫入訂單 =====
    $order = new FakeOrder();
    $_POST = array( 'ys_cf_consent1' => '1', 'ys_cf_taxid1' => " <b>1234</b> \t", 'ys_cf_note001' => "第一行\n第二行", 'ys_cf_ship001' => '' );
    $checkout->save_to_order( $order, array( 'ship_to_different_address' => 1 ) );
    $saved = $order->meta[ YSCustomFieldsCheckout::ORDER_META_KEY ] ?? null;
    check(
        array(
            array( 'id' => 'cf_consent1', 'type' => 'checkbox', 'label' => '我已閱讀並同意購買須知，並了解出貨時間', 'value' => 'yes' ),
            array( 'id' => 'cf_taxid1', 'type' => 'text', 'label' => '統一編號 & "抬頭"', 'value' => '1234' ),
            array( 'id' => 'cf_note001', 'type' => 'textarea', 'label' => '送禮卡片內容說明', 'value' => "第一行\n第二行" ),
        ) === $saved,
        'the order keeps a sanitized snapshot of the displayed fields and skips empty optional text'
    );

    $order = new FakeOrder();
    $_POST = array();
    $checkout->save_to_order( $order, array( 'ship_to_different_address' => 0 ) );
    check(
        array( array( 'id' => 'cf_consent1', 'type' => 'checkbox', 'label' => '我已閱讀並同意購買須知，並了解出貨時間', 'value' => 'no' ) ) === ( $order->meta[ YSCustomFieldsCheckout::ORDER_META_KEY ] ?? null ),
        'an unchecked optional-or-blocked consent is recorded as no'
    );
    $_POST = array();

    // ===== 訂單顯示 =====
    $shown = new FakeOrder();
    $shown->meta[ YSCustomFieldsCheckout::ORDER_META_KEY ] = array_merge(
        $saved,
        array(
            array( 'id' => 'cf_bad', 'type' => 'select', 'label' => '不支援', 'value' => 'x' ),
            'not-an-entry',
            array( 'id' => 'cf_xss', 'type' => 'text', 'label' => '<img src=x onerror=alert(1)>', 'value' => '<b>v</b>' ),
        )
    );
    check( 4 === count( YSCustomFieldsOrderDisplay::get_entries( $shown ) ), 'order display ignores malformed entries' );

    ob_start();
    $display->render_customer( $shown );
    $customer = (string) ob_get_clean();
    check(
        str_contains( $customer, '已勾選' )
        && str_contains( $customer, "第一行<br />\n第二行" )
        && str_contains( $customer, '&lt;img src=x onerror=alert(1)&gt;' )
        && ! str_contains( $customer, '<b>v</b>' ),
        'the customer order view escapes stored text and formats consent and multi-line values'
    );

    ob_start();
    $display->render_email( $shown, false, true );
    $plain = (string) ob_get_clean();
    check(
        str_contains( $plain, "其他資訊\n" ) && str_contains( $plain, '我已閱讀並同意購買須知，並了解出貨時間：已勾選' ),
        'plain-text customer emails list each field as label and value'
    );

    ob_start();
    $display->render_admin( new FakeOrder() );
    check( '' === (string) ob_get_clean(), 'orders without custom fields render nothing in the admin' );

    // ===== 定義 =====
    check( '我已閱讀並同意購買須知，並了解出貨時間' === YSCustomFieldDefinitions::plain_label( field( array() ) ), 'plain labels substitute the popup token' );
    check( '請閱讀須知' === YSCustomFieldDefinitions::plain_label( field( array( 'label' => '請閱讀', 'popup_link' => '須知' ) ) ), 'plain labels append the link text without a token' );
    $generated = YSCustomFieldDefinitions::normalize_field( array_merge( field( array() ), array( 'id' => '' ) ) );
    check( null !== $generated && 1 === preg_match( '/^cf_[a-z0-9]{10}$/', $generated['id'] ), 'a field saved without an id receives a generated id' );

    echo "SUMMARY total={$total} failed={$failed}\n";
    exit( $failed ? 1 : 0 );
}
