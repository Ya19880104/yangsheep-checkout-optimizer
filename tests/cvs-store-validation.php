<?php
/**
 * Provider-aware CVS validation, independent of cached checkout required flags.
 * Run: php tests/cvs-store-validation.php
 */

declare(strict_types=1);

namespace {
    $provider = null;
    foreach ( $argv as $argument ) {
        if ( 0 === strpos( $argument, '--provider=' ) ) {
            $provider = substr( $argument, strlen( '--provider=' ) );
        }
    }
    if ( null === $provider ) {
        $total = 0;
        $failed = 0;
        foreach ( array( 'modern', 'legacy', 'paynow', 'none' ) as $worker ) {
            $pipes = array();
            $process = proc_open(
                array( PHP_BINARY, __FILE__, '--provider=' . $worker ),
                array( 0 => array( 'pipe', 'r' ), 1 => array( 'pipe', 'w' ), 2 => array( 'pipe', 'w' ) ),
                $pipes
            );
            fclose( $pipes[0] );
            $stdout = stream_get_contents( $pipes[1] );
            $stderr = stream_get_contents( $pipes[2] );
            fclose( $pipes[1] );
            fclose( $pipes[2] );
            $status = proc_close( $process );
            $result = json_decode( $stdout, true );
            if ( ! is_array( $result ) ) {
                ++$failed;
                echo "FAIL {$worker} worker: {$stdout}{$stderr}\n";
                continue;
            }
            foreach ( $result as $case ) {
                ++$total;
                if ( ! $case['pass'] ) {
                    ++$failed;
                }
                echo ( $case['pass'] ? 'PASS ' : 'FAIL ' ) . $worker . ': ' . $case['name'] . "\n";
            }
            if ( 0 !== $status && empty( $result ) ) {
                ++$failed;
            }
        }
        echo "SUMMARY total={$total} failed={$failed}\n";
        exit( $failed > 0 ? 1 : 0 );
    }
    define( 'ABSPATH', __DIR__ . '/' );
    $GLOBALS['test_provider'] = $provider;
    $GLOBALS['test_mode'] = 'C2C';
    $GLOBALS['test_hooks'] = array();
}

namespace RY\WooCommerce {
    final class Main {
        public static function get_option( $key, $default = null ) {
            return 'ecpay_shipping_cvs_type' === $key ? $GLOBALS['test_mode'] : $default;
        }
    }
}

namespace {
    use YangSheep\CheckoutOptimizer\Compat\YSCvsCheckoutGuard;

    function __( $text, $domain = '' ) { return $text; }
    function add_action( $hook, $callback, $priority = 10, $accepted_args = 1 ) {
        $GLOBALS['test_hooks'][] = compact( 'hook', 'callback', 'priority', 'accepted_args' );
    }

    class WP_Error {
        public array $errors = array();
        public array $error_data = array();
        public function add( $code, $message, $data = null ): void {
            $this->errors[ $code ][] = $message;
            if ( null !== $data ) $this->error_data[ $code ] = $data;
        }
    }
    final class FakeSession {
        public array $values = array();
        public function get( $key, $default = null ) { return $this->values[ $key ] ?? $default; }
    }
    final class FakeCart {
        public bool $physical = true;
        public array $products = array();
        public function needs_shipping(): bool { return $this->physical; }
        public function get_cart(): array { return $this->products; }
    }
    final class FakeProduct {
        public function __construct( private string $temperature, private string $type = 'simple' ) {}
        public function get_meta( $key, $single = true ): string { return $this->temperature; }
        public function get_type(): string { return $this->type; }
        public function get_parent_id(): int { return 99; }
    }
    function wc_get_product( $id ) { return $GLOBALS['test_parent_product'] ?? false; }
    $GLOBALS['test_wc'] = (object) array( 'session' => new FakeSession(), 'cart' => new FakeCart() );
    function WC() { return $GLOBALS['test_wc']; }

    if ( 'modern' === $provider ) {
        class RY_WT_WC_ECPay_Shipping {
            public static array $support_methods = array(
                'ry_ecpay_shipping_cvs_family' => 'RY_ECPay_Shipping_CVS_Family',
                'ry_ecpay_shipping_cvs_711' => 'RY_ECPay_Shipping_CVS_711',
                'ry_ecpay_shipping_cvs_hilife' => 'RY_ECPay_Shipping_CVS_Hilife',
                'ry_ecpay_shipping_home_tcat' => 'RY_ECPay_Shipping_Home_Tcat',
            );
        }
        class RY_ECPay_Shipping_CVS_Family { const Shipping_Sub_Type = 'FAMI'; }
        class RY_ECPay_Shipping_CVS_711 {
            const Shipping_Sub_Type = 'UNIMART';
            public static function get_support_temp(): array { return array( '1', '3' ); }
        }
        class RY_ECPay_Shipping_CVS_Hilife { const Shipping_Sub_Type = 'HILIFE'; }
    } elseif ( 'legacy' === $provider ) {
        class RY_WT {
            public static function get_option( $key, $default = null ) {
                return 'ecpay_shipping_cvs_type' === $key ? $GLOBALS['test_mode'] : $default;
            }
        }
        class RY_ECPay_Shipping {
            public static array $support_methods = array(
                'ry_ecpay_shipping_cvs_family' => 'RY_ECPay_Shipping_CVS_Family',
                'ry_ecpay_shipping_cvs_711' => 'RY_ECPay_Shipping_CVS_711',
                'ry_ecpay_shipping_cvs_711_freeze' => 'RY_ECPay_Shipping_CVS_711_Freeze',
                'ry_ecpay_shipping_cvs_okmart' => 'RY_ECPay_Shipping_CVS_OKMart',
            );
        }
        class RY_ECPay_Shipping_CVS_Family { public static string $LogisticsSubType = 'FAMI'; }
        class RY_ECPay_Shipping_CVS_711 { public static string $LogisticsSubType = 'UNIMART'; }
        class RY_ECPay_Shipping_CVS_711_Freeze { public static string $LogisticsSubType = 'UNIMARTFREEZE'; }
        class RY_ECPay_Shipping_CVS_OKMart { public static string $LogisticsSubType = 'OKMART'; }
    } elseif ( 'paynow' === $provider ) {
        class PayNow_Shipping {}
        // The published provider service IDs, not YS's own shipping contract.
        class PayNow_Shipping_Logistic_Service {
            public static function get_service_id( $method ) {
                return array(
                    'paynow_shipping_c2c_711' => '01',
                    'paynow_shipping_c2c_family' => '03',
                    'paynow_shipping_c2c_hilife' => '05',
                    'paynow_shipping_b2c_711' => '02',
                    'paynow_shipping_b2c_family' => '04',
                    'paynow_shipping_c2c_family_frozen' => '23',
                    'paynow_shipping_b2c_family_frozen' => '24',
                    'woomp_paynow_shipping_c2c_family_frozen' => '23',
                    'woomp_paynow_shipping_c2c_711_frozen' => '21',
                    'paynow_shipping_hd_tcat' => '06',
                )[ $method ] ?? '';
            }
        }
    }

    $results = array();
    function check( bool $pass, string $name ): void {
        $GLOBALS['results'][] = compact( 'name', 'pass' );
    }
    $file = dirname( __DIR__ ) . '/src/Compat/YSCvsCheckoutGuard.php';
    if ( ! is_file( $file ) ) {
        check( false, 'provider-aware validation class exists' );
        echo json_encode( $results );
        exit( 1 );
    }
    require_once $file;
    $guard = YSCvsCheckoutGuard::get_instance();
    check(
        count( $GLOBALS['test_hooks'] ) === 1
        && 'woocommerce_after_checkout_validation' === $GLOBALS['test_hooks'][0]['hook']
        && PHP_INT_MAX === $GLOBALS['test_hooks'][0]['priority']
        && 2 === $GLOBALS['test_hooks'][0]['accepted_args'],
        'validation runs after provider and field-editor validation'
    );
    check( array() === YSCvsCheckoutGuard::get_store_contracts( array() ), 'empty methods need no store' );
    check( array() === YSCvsCheckoutGuard::get_store_contracts( null ), 'invalid method shape is delegated to Woo validation' );
    check( array() === YSCvsCheckoutGuard::get_store_contracts( array( 'flat_rate:1', 'custom_cvs:8' ) ), 'normal and custom providers remain native' );

    function errors_for( array $data ): WP_Error {
        $errors = new WP_Error();
        $errors->add( 'billing_phone_required', 'Keep native phone error', array( 'id' => 'billing_phone' ) );
        $errors->add( 'shipping', 'Keep native shipping error', array( 'id' => 'shipping_method' ) );
        YSCvsCheckoutGuard::get_instance()->validate_checkout( $data, $errors );
        check(
            array( 'Keep native phone error' ) === $errors->errors['billing_phone_required']
            && array( 'id' => 'billing_phone' ) === $errors->error_data['billing_phone_required']
            && array( 'Keep native shipping error' ) === $errors->errors['shipping'],
            'native errors and data survive validation'
        );
        return $errors;
    }
    function blocked( array $data ): bool {
        return isset( errors_for( $data )->errors['yangsheep_cvs_store_required'] );
    }
    check( ! blocked( array( 'shipping_method' => array( 'flat_rate:1' ) ) ), 'delivery needs no CVS fields' );

    if ( 'none' === $provider ) {
        check( array() === YSCvsCheckoutGuard::get_store_contracts( array( 'ry_ecpay_shipping_cvs_family:6', 'paynow_shipping_c2c_711:4' ) ), 'unloaded providers do not create a guessed contract' );
    } else {
        $family = 'paynow' === $provider ? 'paynow_shipping_c2c_family:6' : 'ry_ecpay_shipping_cvs_family:6';
        $seven = 'paynow' === $provider ? 'paynow_shipping_c2c_711:4' : 'ry_ecpay_shipping_cvs_711:4';
        $contracts = YSCvsCheckoutGuard::get_store_contracts( array( $family, $seven ) );
        check( count( $contracts ) === 2 && $contracts[0]['rate'] === $family && $contracts[1]['rate'] === $seven, 'contracts retain complete rate identity for every known package' );
        $contract = $contracts[0] ?? array( 'fields' => array( 'id' => 'missing_id', 'name' => 'missing_name', 'address' => 'missing_address', 'service' => 'missing_service' ), 'expected' => 'MISSING', 'extra_fields' => array() );
        check( ( 'paynow' === $provider ? '03' : 'FAMIC2C' ) === $contract['expected'], 'provider-native family service/subtype is exported' );
        $data = array( 'shipping_method' => array( $family ) );
        $values = array( 'id' => '123456', 'name' => '測試門市', 'address' => '測試地址', 'service' => $contract['expected'] );
        foreach ( $contract['fields'] as $role => $name ) $data[ $name ] = $values[ $role ];
        $session = array( 'CVSStoreID' => '123456', 'CVSStoreName' => '測試門市', 'CVSAddress' => '測試地址', 'LogisticsSubType' => $contract['expected'], 'shipping_methods' => array( $family ) );
        WC()->session->values = array( 'chosen_shipping_methods' => array( $family ), 'ry_ecpay_cvs_info' => $session );
        check( ! blocked( $data ), 'complete matching store is accepted' );

        foreach ( array( 'id', 'name', 'address', 'service' ) as $role ) {
            $test = $data;
            if ( 'modern' === $provider ) {
                $session_name = array( 'id' => 'CVSStoreID', 'name' => 'CVSStoreName', 'address' => 'CVSAddress', 'service' => 'LogisticsSubType' )[ $role ];
                WC()->session->values['ry_ecpay_cvs_info'] = $session;
                unset( WC()->session->values['ry_ecpay_cvs_info'][ $session_name ] );
            } else {
                unset( $test[ $contract['fields'][ $role ] ] );
            }
            check( blocked( $test ), 'missing ' . $role . ' is blocked' );
        }
        WC()->session->values['ry_ecpay_cvs_info'] = $session;
        $test = $data;
        if ( 'modern' === $provider ) WC()->session->values['ry_ecpay_cvs_info']['CVSStoreName'] = " \t\r\n ";
        else $test[ $contract['fields']['name'] ] = " \t\r\n ";
        check( blocked( $test ), 'whitespace-only store data is blocked' );
        WC()->session->values['ry_ecpay_cvs_info'] = $session;
        $test = $data;
        if ( 'modern' === $provider ) WC()->session->values['ry_ecpay_cvs_info']['CVSAddress'] = "\u{00A0}\u{200B}\u{FEFF}";
        else $test[ $contract['fields']['address'] ] = "\u{00A0}\u{200B}\u{FEFF}";
        check( blocked( $test ), 'Unicode whitespace cannot satisfy a store address' );
        WC()->session->values['ry_ecpay_cvs_info'] = $session;
        $test = $data;
        if ( 'modern' === $provider ) WC()->session->values['ry_ecpay_cvs_info']['CVSStoreID'] = array( '123456' );
        else $test[ $contract['fields']['id'] ] = array( '123456' );
        check( blocked( $test ), 'array-shaped store data is blocked' );
        WC()->session->values['ry_ecpay_cvs_info'] = $session;

        $wrong = $data;
        $wrong['shipping_method'] = array( $seven );
        WC()->session->values['chosen_shipping_methods'] = array( $seven );
        WC()->session->values['ry_ecpay_cvs_info']['shipping_methods'] = array( $seven );
        check( blocked( $wrong ), 'family store cannot satisfy seven-eleven selection' );
        WC()->session->values = array( 'chosen_shipping_methods' => array( $family ), 'ry_ecpay_cvs_info' => $session );

        $mixed = $data;
        $mixed['shipping_method'][] = 'flat_rate:1';
        $missing = $mixed;
        if ( 'modern' === $provider ) {
            WC()->session->values['chosen_shipping_methods'] = $mixed['shipping_method'];
            WC()->session->values['ry_ecpay_cvs_info']['shipping_methods'] = $mixed['shipping_method'];
            WC()->session->values['ry_ecpay_cvs_info']['CVSStoreID'] = '';
        } else unset( $missing[ $contract['fields']['id'] ] );
        check( blocked( $missing ), 'mixed CVS and delivery still validates the CVS package' );
        WC()->session->values['ry_ecpay_cvs_info'] = $session;
        WC()->session->values['chosen_shipping_methods'] = $mixed['shipping_method'];
        WC()->session->values['ry_ecpay_cvs_info']['shipping_methods'] = $mixed['shipping_method'];
        check( ! blocked( $mixed ), 'mixed CVS and delivery with complete store remains accepted' );
        WC()->session->values = array( 'chosen_shipping_methods' => array( $family ), 'ry_ecpay_cvs_info' => $session );

        $shared = $data;
        $shared['shipping_method'] = array( 0 => $family, 2 => $family );
        WC()->session->values['chosen_shipping_methods'] = $shared['shipping_method'];
        WC()->session->values['ry_ecpay_cvs_info']['shipping_methods'] = $shared['shipping_method'];
        check( ! blocked( $shared ), 'compatible packages can share the same native store record' );
        $shared['shipping_method'] = array( $family, $seven );
        WC()->session->values['chosen_shipping_methods'] = $shared['shipping_method'];
        WC()->session->values['ry_ecpay_cvs_info']['shipping_methods'] = $shared['shipping_method'];
        $shared_errors = errors_for( $shared );
        check( count( $shared_errors->errors['yangsheep_cvs_store_required'] ?? array() ) === 1, 'incompatible brands sharing fields fail safely with one store error' );
        WC()->session->values = array( 'chosen_shipping_methods' => array( $family ), 'ry_ecpay_cvs_info' => $session );

        if ( 'modern' === $provider ) {
            WC()->session->values['ry_ecpay_cvs_info'] = array();
            check( blocked( $data ), 'posted modern fields cannot replace missing authoritative session' );
            WC()->session->values['ry_ecpay_cvs_info'] = $session;
            WC()->session->values['ry_ecpay_cvs_info']['shipping_methods'] = array( 'ry_ecpay_shipping_cvs_family:60' );
            check( blocked( $data ), 'same brand different instance is stale and blocked' );
            WC()->session->values['ry_ecpay_cvs_info'] = $session;
            WC()->session->values['ry_ecpay_cvs_info']['shipping_methods'] = array( 2 => $family );
            check( blocked( $data ), 'same rate bound to another package is stale and blocked' );
            WC()->session->values['ry_ecpay_cvs_info'] = $session;
            WC()->session->values['chosen_shipping_methods'] = array( 'ry_ecpay_shipping_cvs_family:60' );
            check( blocked( $data ), 'current server selection must agree with submitted methods' );
            WC()->session->values = array( 'chosen_shipping_methods' => array( $family ), 'ry_ecpay_cvs_info' => $session );
            check( ! blocked( array( 'shipping_method' => array( $family ) ) ), 'modern provider consumes session metadata, not guessed posted fields' );
        } else {
            $_POST = $data;
            check( blocked( array( 'shipping_method' => array( $family ) ) ), 'raw POST cannot replace missing fields in the order-consumed data array' );
            $_POST = array();
        }

        if ( 'paynow' === $provider ) {
            $frozen = $data;
            $frozen['shipping_method'] = array( 'paynow_shipping_c2c_family_frozen:9' );
            $frozen['paynow_service'] = '23';
            check( blocked( $frozen ), 'family frozen reservation and shipping date remain required' );
            $frozen['paynow_reservedno'] = 'RESERVATION';
            check( blocked( $frozen ), 'family frozen reservation alone is incomplete' );
            $frozen['paynow_shipdate'] = '2099-01-01';
            check( ! blocked( $frozen ), 'complete family frozen store contract is accepted' );
            $frozen_contract = YSCvsCheckoutGuard::get_store_contracts( $frozen['shipping_method'] );
            check( array( 'paynow_reservedno', 'paynow_shipdate' ) === ( $frozen_contract[0]['extra_fields'] ?? null ), 'front end receives native frozen extra field requirements' );
            check( array() === YSCvsCheckoutGuard::get_store_contracts( array( 'paynow_shipping_c2c_custom:9' ) ), 'unknown PayNow extension is not guessed' );
        } else {
            $GLOBALS['test_mode'] = 'B2C';
            $b2c = YSCvsCheckoutGuard::get_store_contracts( array( $family ) );
            check( 'FAMI' === ( $b2c[0]['expected'] ?? null ), 'B2C contract uses native no-C2C suffix' );
            $test = $data;
            if ( 'modern' === $provider ) WC()->session->values['ry_ecpay_cvs_info']['LogisticsSubType'] = 'FAMI';
            else $test[ $contract['fields']['service'] ] = 'FAMI';
            check( ! blocked( $test ), 'complete B2C selection remains accepted' );
            if ( 'modern' === $provider ) WC()->session->values['ry_ecpay_cvs_info']['LogisticsSubType'] = 'FAMIC2C';
            else $test[ $contract['fields']['service'] ] = 'FAMIC2C';
            check( blocked( $test ), 'stale C2C selection is rejected after switching to B2C' );
            if ( 'modern' === $provider ) {
                WC()->cart->products = array( array( 'data' => new FakeProduct( '3' ) ) );
                $frozen_contract = YSCvsCheckoutGuard::get_store_contracts( array( $seven ) );
                check( 'UNIMARTFREEZE' === ( $frozen_contract[0]['expected'] ?? null ), 'B2C seven-eleven follows native frozen-cart subtype' );
                $frozen_session = $session;
                $frozen_session['shipping_methods'] = array( $seven );
                $frozen_session['LogisticsSubType'] = 'UNIMARTFREEZE';
                WC()->session->values = array( 'chosen_shipping_methods' => array( $seven ), 'ry_ecpay_cvs_info' => $frozen_session );
                check( ! blocked( array( 'shipping_method' => array( $seven ) ) ), 'native frozen B2C store session is accepted' );
                WC()->session->values['ry_ecpay_cvs_info']['LogisticsSubType'] = 'UNIMART';
                check( blocked( array( 'shipping_method' => array( $seven ) ) ), 'ordinary B2C store cannot satisfy a frozen B2C cart' );
                WC()->cart->products = array( array( 'data' => new FakeProduct( '', 'variation' ) ) );
                $GLOBALS['test_parent_product'] = new FakeProduct( '3' );
                $inherited = YSCvsCheckoutGuard::get_store_contracts( array( $seven ) );
                check( 'UNIMARTFREEZE' === ( $inherited[0]['expected'] ?? null ), 'variant inherits native frozen temperature from its parent' );
                $GLOBALS['test_mode'] = 'C2C';
                $c2c_frozen = YSCvsCheckoutGuard::get_store_contracts( array( $seven ) );
                check( 'UNIMARTC2C' === ( $c2c_frozen[0]['expected'] ?? null ), 'C2C subtype follows native mode despite product temperature' );
            }
        }
        WC()->cart->physical = false;
        check( ! blocked( array( 'shipping_method' => array( $family ) ) ), 'virtual carts are not affected by stale shipping form data' );
    }
    echo json_encode( $results, JSON_UNESCAPED_UNICODE );
    exit( count( array_filter( $results, static fn( $case ) => ! $case['pass'] ) ) > 0 ? 1 : 0 );
}
