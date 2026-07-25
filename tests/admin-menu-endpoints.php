<?php

declare(strict_types=1);

define('ABSPATH', __DIR__);
define('YANGSHEEP_CHECKOUT_URL', 'https://example.test/plugin/');

$GLOBALS['ys_admin_menu_calls'] = [];
$GLOBALS['ys_admin_submenu_calls'] = [];
$GLOBALS['ys_enqueued_scripts'] = [];

function __(string $text, ?string $domain = null): string
{
    return $text;
}

function add_menu_page(
    string $page_title,
    string $menu_title,
    string $capability,
    string $menu_slug,
    ?callable $callback = null,
    string $icon_url = '',
    $position = null
): string {
    $GLOBALS['ys_admin_menu_calls'][] = func_get_args();
    return 'toplevel_page_' . $menu_slug;
}

function add_submenu_page(
    string $parent_slug,
    string $page_title,
    string $menu_title,
    string $capability,
    string $menu_slug,
    ?callable $callback = null
): string {
    $GLOBALS['ys_admin_submenu_calls'][] = func_get_args();
    return 'ys-plugin_page_' . $menu_slug;
}

function wp_enqueue_style(string $handle): void
{
}

function add_action(string $hook_name, callable $callback, int $priority = 10, int $accepted_args = 1): bool
{
    return true;
}

function wp_enqueue_script(string $handle): void
{
    $GLOBALS['ys_enqueued_scripts'][] = $handle;
}

function wp_localize_script(string $handle, string $object_name, array $data): void
{
}

function admin_url(string $path = ''): string
{
    return 'https://example.test/wp-admin/' . ltrim($path, '/');
}

function wp_create_nonce(string $action): string
{
    return 'nonce';
}

function yangsheep_checkout_asset_version(string $relative_path): string
{
    return 'test';
}

function wp_unslash($value)
{
    return $value;
}

function sanitize_key(string $value): string
{
    return strtolower((string) preg_replace('/[^a-z0-9_\-]/', '', $value));
}

function check_result(bool $condition, string $message): void
{
    static $total = 0;
    static $failed = 0;

    $total++;
    if ($condition) {
        echo "PASS {$message}\n";
    } else {
        $failed++;
        echo "FAIL {$message}\n";
    }

    $GLOBALS['ys_admin_test_total'] = $total;
    $GLOBALS['ys_admin_test_failed'] = $failed;
}

require dirname(__DIR__) . '/src/Admin/YSCheckoutSettings.php';

$reflection = new ReflectionClass(\YangSheep\CheckoutOptimizer\Admin\YSCheckoutSettings::class);
$settings = $reflection->newInstanceWithoutConstructor();

$GLOBALS['menu'] = [
    ['YS Plugin', 'manage_options', 'ys-toolbox'],
];

$settings->add_admin_menu();

$standalone = array_values(array_filter(
    $GLOBALS['ys_admin_menu_calls'],
    static fn(array $call): bool => ($call[3] ?? '') === 'yangsheep_checkout_optimization'
));
$toolbox = array_values(array_filter(
    $GLOBALS['ys_admin_submenu_calls'],
    static fn(array $call): bool => ($call[0] ?? '') === 'ys-toolbox'
        && ($call[4] ?? '') === 'ys-checkout-optimizer'
));

check_result(count($standalone) === 1, 'historical standalone endpoint is registered once');
check_result(count($toolbox) === 1, 'toolbox child endpoint remains registered once');
check_result(
    isset($standalone[0][4], $toolbox[0][5])
        && $standalone[0][4] === $toolbox[0][5],
    'both endpoints use the same settings callback'
);

$GLOBALS['ys_enqueued_scripts'] = [];
$settings->enqueue_admin_scripts('toplevel_page_yangsheep_checkout_optimization');
check_result(
    in_array('yangsheep-admin-settings', $GLOBALS['ys_enqueued_scripts'], true),
    'standalone endpoint loads the complete settings script'
);

$GLOBALS['ys_enqueued_scripts'] = [];
$settings->enqueue_admin_scripts('ys-plugin_page_ys-checkout-optimizer');
check_result(
    in_array('yangsheep-admin-settings', $GLOBALS['ys_enqueued_scripts'], true),
    'toolbox endpoint loads the complete settings script'
);

$resolveMethodExists = method_exists($settings, 'resolve_settings_page_slug');
check_result($resolveMethodExists, 'settings endpoint resolver exists');

if ($resolveMethodExists) {
    $resolver = new ReflectionMethod($settings, 'resolve_settings_page_slug');
    if (PHP_VERSION_ID < 80100) {
        $resolver->setAccessible(true);
    }

    $_GET = ['page' => 'yangsheep_checkout_optimization'];
    $_POST = [];
    check_result(
        $resolver->invoke($settings) === 'yangsheep_checkout_optimization',
        'standalone GET route remains standalone'
    );

    $_GET = ['page' => 'ys-checkout-optimizer'];
    $_POST = ['ys_settings_page' => 'ys-checkout-optimizer'];
    check_result(
        $resolver->invoke($settings) === 'ys-checkout-optimizer',
        'toolbox POST route remains in the toolbox'
    );

    $_GET = ['page' => 'arbitrary-admin-page'];
    $_POST = ['ys_settings_page' => 'arbitrary-admin-page'];
    check_result(
        $resolver->invoke($settings) === 'ys-checkout-optimizer',
        'unknown route falls back to the canonical toolbox endpoint'
    );
}

$failed = $GLOBALS['ys_admin_test_failed'] ?? 0;
$total = $GLOBALS['ys_admin_test_total'] ?? 0;
echo "\n{$total} checks, {$failed} failures\n";
exit($failed === 0 ? 0 : 1);
