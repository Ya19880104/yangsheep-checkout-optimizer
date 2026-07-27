<?php

declare(strict_types=1);

$root   = dirname(__DIR__);
$host   = file_get_contents($root . '/yangsheep-checkout-optimization.php');
$loader = file_get_contents($root . '/vendor/yangsheep/ys-plugin-hub-client/ys-plugin-hub-client.php');
$failed = 0;

function ys_checkout_hpos_assert(bool $condition, string $message, int &$failed): void
{
    if (!$condition) {
        ++$failed;
        fwrite(STDERR, "FAIL: {$message}\n");
        return;
    }

    echo "PASS: {$message}\n";
}

ys_checkout_hpos_assert(
    str_contains($host, "add_action( 'before_woocommerce_init'")
        && str_contains($host, 'FeaturesUtil::declare_compatibility')
        && str_contains($host, "'custom_order_tables'")
        && str_contains($host, 'YANGSHEEP_CHECKOUT_OPTIMIZATION_FILE')
        && str_contains($host, 'true'),
    'checkout optimizer declares HPOS compatibility from its registered host file',
    $failed
);

ys_checkout_hpos_assert(
    str_contains($loader, 'Version:     2.0.5')
        && str_contains($loader, "YS_HUB_CLIENT_VERSION', '2.0.5'")
        && !str_contains($loader, 'FeaturesUtil::declare_compatibility')
        && !str_contains($loader, "'custom_order_tables'"),
    'vendored Hub 2.0.5 does not claim host HPOS compatibility',
    $failed
);

exit($failed > 0 ? 1 : 0);
