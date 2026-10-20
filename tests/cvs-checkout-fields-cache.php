<?php
/** Run: php tests/cvs-checkout-fields-cache.php */
declare(strict_types=1);

namespace {
    define( 'ABSPATH', __DIR__ . '/' );
}

namespace YangSheep\CheckoutOptimizer\Settings {
    final class YSSettingsManager {
        public static array $values = array();
        public static function get( $key, $default = null ) { return self::$values[$key] ?? $default; }
    }
}

namespace {
    use YangSheep\CheckoutOptimizer\Checkout\YSCheckoutFields;
    use YangSheep\CheckoutOptimizer\Settings\YSSettingsManager;

    $hooks = array();
    function add_filter( $name, $callback, $priority = 10, $args = 1 ) {
        $GLOBALS['hooks'][$name][$priority][] = $callback;
    }
    function add_action( $name, $callback, $priority = 10, $args = 1 ) { add_filter( $name, $callback, $priority, $args ); }
    function apply_filters( $name, $value ) {
        $callbacks = $GLOBALS['hooks'][$name] ?? array();
        ksort( $callbacks );
        foreach ( $callbacks as $group ) {
            foreach ( $group as $callback ) { $value = $callback( $value ); }
        }
        return $value;
    }
    function wc_clean( $value ) { return $value; }
    function wp_unslash( $value ) { return $value; }
    function __( $value, $domain = '' ) { return $value; }
    function _x( $value, $context, $domain = '' ) { return $value; }

    function fixture_fields(): array {
        $required = array( 'required' => true );
        $shipping = array();
        foreach ( array( 'first_name', 'phone', 'postcode', 'state', 'city', 'address_1' ) as $key ) {
            $shipping['shipping_' . $key] = $required;
        }
        $shipping['provider_metadata'] = array( 'required' => true, 'custom' => 'preserved' );
        return array( 'billing' => array( 'billing_phone' => $required, 'billing_tax_id' => $required ), 'shipping' => $shipping );
    }

    // WC_Checkout::get_checkout_fields() caches once; its legacy setter replaces that cache.
    final class CachedCheckout {
        private ?array $fields = null;
        public function get_checkout_fields(): array {
            return $this->fields ??= apply_filters( 'woocommerce_checkout_fields', fixture_fields() );
        }
        public function __set( $key, $value ): void {
            if ( 'checkout_fields' !== $key ) { throw new \LogicException( $key ); }
            $this->fields = $value;
        }
        public function seed_before_optimizer_loaded(): void {
            $this->fields = fixture_fields();
            // RY may mark these optional before checkout_process has run.
            $this->fields['shipping']['shipping_phone']['required'] = false;
            $this->fields['billing']['billing_phone']['required'] = false;
        }
    }
    final class FakeSession {
        public array $chosen = array();
        public function get( $key, $default = array() ) { return 'chosen_shipping_methods' === $key ? $this->chosen : $default; }
    }
    final class FakeWoo {
        public FakeSession $session;
        public CachedCheckout $checkout;
        public function __construct() { $this->session = new FakeSession(); $this->checkout = new CachedCheckout(); }
        public function checkout(): CachedCheckout { return $this->checkout; }
    }
    $GLOBALS['wc'] = new FakeWoo();
    function WC(): FakeWoo { return $GLOBALS['wc']; }

    require dirname( __DIR__ ) . '/src/Checkout/YSCheckoutFields.php';
    YSCheckoutFields::get_instance();
    // ThemeHigh's saved field definitions run after the old priority-999 CVS filter.
    add_filter( 'woocommerce_checkout_fields', static function ( $fields ) {
        foreach ( array( 'postcode', 'state', 'city', 'address_1' ) as $key ) {
            $fields['shipping']['shipping_' . $key]['required'] = true;
        }
        return $fields;
    }, 1000 );

    $total = 0;
    $failed = 0;
    function check( $condition, $message ): void {
        global $total, $failed;
        ++$total;
        if ( ! $condition ) { ++$failed; }
        echo ( $condition ? 'PASS ' : 'FAIL ' ) . $message . "\n";
    }
    function address_required( $fields, $expected ): bool {
        foreach ( array( 'postcode', 'state', 'city', 'address_1' ) as $key ) {
            if ( $expected !== $fields['shipping']['shipping_' . $key]['required'] ) { return false; }
        }
        return true;
    }
    function late_refresh(): void {
        $callbacks = $GLOBALS['hooks']['woocommerce_checkout_process'] ?? array();
        ksort( $callbacks );
        foreach ( $callbacks as $group ) {
            foreach ( $group as $callback ) {
                // Phone validators are orthogonal; normal Woo validation remains responsible for them.
                if ( 'refresh_cvs_checkout_fields' === $callback[1] ) { $callback(); }
            }
        }
    }

    foreach ( array( 'no', 'yes' ) as $compatibility ) {
        YSSettingsManager::$values = array( 'yangsheep_checkout_field_compatibility' => $compatibility );
        foreach ( array( 'ry_ecpay_shipping_cvs_family:4', 'ry_ecpay_shipping_cvs_711:5', 'paynow_shipping_c2c_family:8', 'paynow_shipping_c2c_711:7' ) as $rate ) {
            $_POST = array( 'shipping_method' => array( $rate ) );
            WC()->session->chosen = array( 'flat_rate:1' );
            WC()->checkout = new CachedCheckout();
            check( address_required( WC()->checkout()->get_checkout_fields(), false ), "{$compatibility} {$rate}: final CVS requirements survive field editor" );

            WC()->checkout = new CachedCheckout();
            WC()->checkout()->seed_before_optimizer_loaded();
            late_refresh();
            $fields = WC()->checkout()->get_checkout_fields();
            check( address_required( $fields, false ), "{$compatibility} {$rate}: repair cache populated before optimizer" );
            check( true === $fields['shipping']['shipping_first_name']['required'] && true === $fields['shipping']['shipping_phone']['required'] && true === $fields['billing']['billing_tax_id']['required'] && 'preserved' === $fields['shipping']['provider_metadata']['custom'], "{$compatibility} {$rate}: preserve identity and provider fields" );
            check( true === $fields['billing']['billing_phone']['required'], "{$compatibility} {$rate}: late cache retains billing phone contract" );
            $before = $fields;
            late_refresh();
            check( $before === WC()->checkout()->get_checkout_fields(), "{$compatibility} {$rate}: refresh is idempotent" );
        }
        foreach ( array( array( 'flat_rate:1' ), array( 'ry_ecpay_shipping_cvs_family:4', 'flat_rate:1' ), array( '' ), array( 'unknown:5' ) ) as $rates ) {
            $_POST = array( 'shipping_method' => $rates );
            WC()->session->chosen = array( 'ry_ecpay_shipping_cvs_family:4' );
            WC()->checkout = new CachedCheckout();
            late_refresh();
            check( address_required( WC()->checkout()->get_checkout_fields(), true ), "{$compatibility} " . implode( '+', $rates ) . ': submitted postal/mixed/unknown choice retains requirements' );
        }
    }
    $_POST = array( 'shipping_method' => 'ry_ecpay_shipping_cvs_family:4' );
    WC()->checkout = new CachedCheckout();
    late_refresh();
    check( address_required( WC()->checkout()->get_checkout_fields(), true ), 'malformed shipping choice cannot relax the address' );
    echo "SUMMARY total={$total} failed={$failed}\n";
    exit( $failed ? 1 : 0 );
}
