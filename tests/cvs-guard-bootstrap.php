<?php
/**
 * Bootstrap wiring regression; provider validation has its own test matrix.
 * Run: php tests/cvs-guard-bootstrap.php
 */
declare(strict_types=1);

namespace {
    define('ABSPATH', __DIR__ . '/');
    // Skip the vendored updater bootstrap; it is unrelated to checkout guards.
    define('YS_HUB_CLIENT_VERSION', 'bootstrap-test');
}

namespace YangSheep\PluginHubClient\Admin {
    final class YSToolboxMenuNormalizer {
        public static function register(): void {}
    }
}

namespace YangSheep\CheckoutOptimizer\Settings {
    final class YSSettingsManager {
        public static function get($key, $default = null) { return $default; }
    }
}

namespace YangSheep\CheckoutOptimizer\Compat {
    /** Contract producer spy: this test checks transport, not provider rules. */
    final class YSCvsCheckoutGuard {
        public static int $instances = 0;
        public static array $methods = array();
        public static ?array $override = null;
        public static function get_instance(): void { ++self::$instances; }
        public static function get_store_contracts(array $methods): array {
            self::$methods = $methods;
            if (null !== self::$override) { return self::$override; }
            $contracts = array();
            foreach ($methods as $method) {
                if (0 !== strpos($method, 'ry_ecpay_shipping_cvs_')) { continue; }
                $contracts[] = array('rate' => $method, 'provider' => 'ry_ecpay');
            }
            return $contracts;
        }
    }
}

namespace {
    use YangSheep\CheckoutOptimizer\Compat\YSCvsCheckoutGuard;

    // All non-guard init collaborators are inert. Their own bootstrap must not run.
    final class BootstrapCollaborator {
        public static function get_instance(): void {}
    }
    foreach (array(
        'Admin\\YSCheckoutSettings', 'Checkout\\YSCheckoutCustomizer',
        'Checkout\\YSCheckoutFields', 'Checkout\\YSShippingCards',
        'Checkout\\YSCheckoutLayout', 'Checkout\\YSCheckoutSidebar',
        'Order\\YSOrderEnhancer', 'Compat\\YSThirdPartyShippingCompat',
        'Compat\\YSWPLoyaltyIntegration', 'Compat\\YSYithCouponDisplay',
        'Compat\\YSYithPointsIntegration',
    ) as $class) {
        class_alias(BootstrapCollaborator::class, 'YangSheep\\CheckoutOptimizer\\' . $class);
    }
    final class WooCommerce {}
    final class BootstrapRate {
        private string $id;
        public function __construct(string $id) { $this->id = $id; }
        public function get_id(): string { return $this->id; }
    }
    final class BootstrapShipping {
        public array $packages = array();
        public function get_packages(): array { return $this->packages; }
    }
    final class BootstrapSession {
        public $chosen = array();
        public function get($key, $default = null) { return 'chosen_shipping_methods' === $key ? $this->chosen : $default; }
    }
    final class BootstrapCart {
        public function get_cart(): array { return array(); }
    }
    final class BootstrapWoo {
        public ?BootstrapShipping $shipping;
        public ?BootstrapSession $session;
        public BootstrapCart $cart;
        public function __construct() {
            $this->shipping = new BootstrapShipping();
            $this->session = new BootstrapSession();
            $this->cart = new BootstrapCart();
        }
        public function shipping(): ?BootstrapShipping { return $this->shipping; }
    }

    $GLOBALS['bootstrap_wc'] = new BootstrapWoo();
    $GLOBALS['bootstrap_hooks'] = array();
    $GLOBALS['bootstrap_page'] = array('checkout' => true, 'endpoint' => false, 'cart' => false, 'account' => false);
    $GLOBALS['bootstrap_scripts'] = array();
    $GLOBALS['bootstrap_localized'] = array();

    function WC(): BootstrapWoo { return $GLOBALS['bootstrap_wc']; }
    function plugin_dir_path($file): string { return dirname($file) . '/'; }
    function plugin_dir_url($file): string { return 'https://checkout-fixture.invalid/plugin/'; }
    function register_activation_hook($file, $callback): void {}
    function add_filter($name, $callback, $priority = 10, $accepted_args = 1): void {
        $GLOBALS['bootstrap_hooks'][$name][$priority][] = array($callback, $accepted_args);
    }
    function add_action($name, $callback, $priority = 10, $accepted_args = 1): void { add_filter($name, $callback, $priority, $accepted_args); }
    function do_action($name, ...$args): void {
        $callbacks = $GLOBALS['bootstrap_hooks'][$name] ?? array();
        ksort($callbacks);
        foreach ($callbacks as $group) {
            foreach ($group as [$callback, $accepted_args]) { $callback(...array_slice($args, 0, $accepted_args)); }
        }
    }
    function apply_filters($name, $value) {
        $callbacks = $GLOBALS['bootstrap_hooks'][$name] ?? array();
        ksort($callbacks);
        foreach ($callbacks as $group) {
            foreach ($group as [$callback]) { $value = $callback($value); }
        }
        return $value;
    }
    function is_checkout(): bool { return $GLOBALS['bootstrap_page']['checkout']; }
    function is_wc_endpoint_url($endpoint = ''): bool { return $GLOBALS['bootstrap_page']['endpoint']; }
    function is_cart(): bool { return $GLOBALS['bootstrap_page']['cart']; }
    function is_account_page(): bool { return $GLOBALS['bootstrap_page']['account']; }
    function wp_enqueue_style(...$args): void {}
    function wp_enqueue_script($handle, $src = '', $deps = array(), $version = false, $footer = false): void {
        $GLOBALS['bootstrap_scripts'][$handle] = compact('src', 'deps', 'version', 'footer');
    }
    function wp_localize_script($handle, $name, $value): void { $GLOBALS['bootstrap_localized'][$handle] = array('name' => $name, 'value' => $value); }
    function wp_create_nonce($action): string { return 'fixture-nonce'; }
    function __($text, $domain = ''): string { return $text; }
    function esc_html__($text, $domain = ''): string { return htmlspecialchars($text, ENT_QUOTES, 'UTF-8'); }
    function esc_attr($value): string { return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8'); }
    function wp_json_encode($value): string { return json_encode($value, JSON_THROW_ON_ERROR); }

    require dirname(__DIR__) . '/yangsheep-checkout-optimization.php';

    $total = 0;
    $failed = 0;
    function check_bootstrap(bool $condition, string $message): void {
        global $total, $failed;
        ++$total;
        if (!$condition) { ++$failed; }
        echo ($condition ? 'PASS ' : 'FAIL ') . $message . "\n";
    }
    function package_rates(array $rates): array {
        return array('rates' => array_map(static fn($rate) => new BootstrapRate($rate), $rates));
    }
    function markup_contracts(string $markup): array {
        if (!preg_match('/data-contracts="([^"]*)"/', $markup, $match)) { throw new \RuntimeException('Contract attribute missing'); }
        return json_decode(html_entity_decode($match[1], ENT_QUOTES, 'UTF-8'), true, 512, JSON_THROW_ON_ERROR);
    }
    function reset_enqueues(): void {
        $GLOBALS['bootstrap_scripts'] = array();
        $GLOBALS['bootstrap_localized'] = array();
    }

    check_bootstrap(!class_exists('RY_WooCommerce') && !class_exists('RY_WT'), 'fixture has no legacy RY bootstrap class');
    do_action('init');
    check_bootstrap(1 === YSCvsCheckoutGuard::$instances, 'guard is registered without a legacy RY class gate');

    $family = 'ry_ecpay_shipping_cvs_family:4';
    $seven = 'ry_ecpay_shipping_cvs_711:5';
    $fallback = 'ry_ecpay_shipping_cvs_family:14';
    WC()->shipping->packages = array(package_rates(array($family, $seven, 'flat_rate:1')), package_rates(array($family)));
    WC()->session->chosen = array($family, $fallback, array('malformed'));
    $contracts = yangsheep_checkout_cvs_store_contracts();
    check_bootstrap(array($family, $seven, 'flat_rate:1', $fallback) === YSCvsCheckoutGuard::$methods, 'all available complete rate IDs plus selected fallback are deduplicated');
    check_bootstrap(array($family, $seven, $fallback) === array_column($contracts, 'rate'), 'bootstrap transports the contract producer result unchanged');

    do_action('wp_enqueue_scripts');
    $script = $GLOBALS['bootstrap_scripts']['yangsheep-cvs-checkout-guard'] ?? array();
    $localized = $GLOBALS['bootstrap_localized']['yangsheep-cvs-checkout-guard'] ?? array();
    check_bootstrap(array('jquery', 'wc-checkout') === ($script['deps'] ?? null) && true === ($script['footer'] ?? null), 'main checkout enqueues guard after WooCommerce checkout dependencies');
    check_bootstrap('ysCvsCheckoutGuard' === ($localized['name'] ?? null) && $contracts === ($localized['value']['contracts'] ?? null), 'localized guard receives contracts for alternative carriers too');
    check_bootstrap(is_string($localized['value']['message'] ?? null) && '' !== $localized['value']['message'], 'localized missing-store message is present');

    ob_start();
    do_action('woocommerce_checkout_after_order_review');
    $initial_markup = ob_get_clean();
    check_bootstrap($contracts === markup_contracts($initial_markup) && str_contains($initial_markup, 'id="ys-cvs-checkout-contracts" hidden'), 'initial form hook emits an inert refreshable contract container');

    $zone_two = 'ry_ecpay_shipping_cvs_711:25';
    WC()->shipping->packages = array(package_rates(array($zone_two, 'flat_rate:20')));
    WC()->session->chosen = array($zone_two);
    $fragments = apply_filters('woocommerce_update_order_review_fragments', array('existing-fragment' => 'preserved'));
    $new_contracts = markup_contracts($fragments['#ys-cvs-checkout-contracts']);
    check_bootstrap(array($zone_two) === array_column($new_contracts, 'rate') && $initial_markup !== $fragments['#ys-cvs-checkout-contracts'], 'new-zone fragments replace the initial carrier contracts');
    check_bootstrap('preserved' === $fragments['existing-fragment'], 'contract fragment preserves unrelated existing fragments');
    WC()->shipping->packages = array(package_rates(array('flat_rate:30')));
    WC()->session->chosen = array('flat_rate:30');
    $postal_fragments = apply_filters('woocommerce_update_order_review_fragments', $fragments);
    check_bootstrap(array() === markup_contracts($postal_fragments['#ys-cvs-checkout-contracts']), 'postal-only zone clears stale CVS contracts');

    $payload = array(array('rate' => '"><img src=x onerror=alert(1)>&\'雪', 'provider' => 'ry_ecpay'));
    YSCvsCheckoutGuard::$override = $payload;
    $escaped_markup = yangsheep_checkout_cvs_contracts_markup();
    check_bootstrap($payload === markup_contracts($escaped_markup), 'attribute JSON round-trips quotes, apostrophes, angle brackets and Unicode');
    check_bootstrap(!str_contains($escaped_markup, '<img') && str_contains($escaped_markup, '&quot;') && str_contains($escaped_markup, '&lt;'), 'contract attribute prevents data from becoming HTML markup');
    YSCvsCheckoutGuard::$override = null;

    foreach (array('order-received', 'order-pay', 'other-checkout-endpoint') as $endpoint) {
        reset_enqueues();
        $GLOBALS['bootstrap_page']['endpoint'] = true;
        do_action('wp_enqueue_scripts');
        check_bootstrap(!isset($GLOBALS['bootstrap_scripts']['yangsheep-cvs-checkout-guard']), $endpoint . ' does not enqueue checkout guard');
    }
    reset_enqueues();
    $GLOBALS['bootstrap_page'] = array('checkout' => false, 'endpoint' => false, 'cart' => true, 'account' => false);
    do_action('wp_enqueue_scripts');
    check_bootstrap(!isset($GLOBALS['bootstrap_scripts']['yangsheep-cvs-checkout-guard']), 'cart page does not enqueue checkout guard');
    reset_enqueues();
    $GLOBALS['bootstrap_page'] = array('checkout' => false, 'endpoint' => false, 'cart' => false, 'account' => true);
    do_action('wp_enqueue_scripts');
    check_bootstrap(!isset($GLOBALS['bootstrap_scripts']['yangsheep-cvs-checkout-guard']), 'account page does not enqueue checkout guard');

    WC()->shipping = null;
    WC()->session = null;
    check_bootstrap(array() === yangsheep_checkout_cvs_store_contracts(), 'missing shipping/session state yields no frontend contracts');
    WC()->session = new BootstrapSession();
    WC()->session->chosen = $family;
    check_bootstrap(array() === yangsheep_checkout_cvs_store_contracts(), 'malformed scalar chosen state does not become a contract');

    echo "SUMMARY total={$total} failed={$failed}\n";
    exit($failed ? 1 : 0);
}
