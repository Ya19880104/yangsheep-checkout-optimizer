<?php
/**
 * Verify HPOS declaration ownership after the Hub Client 2.0.5 cohort update.
 *
 * Usage:
 *   wp eval-file /tmp/dev-checkout-hpos-cohort.php
 */

declare(strict_types=1);

use Automattic\WooCommerce\Utilities\FeaturesUtil;
use Automattic\WooCommerce\Utilities\OrderUtil;
use Automattic\WooCommerce\Utilities\PluginUtil;

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	throw new RuntimeException( 'This probe must run under WP-CLI.' );
}

$passes   = 0;
$failures = array();
$check    = static function ( string $label, bool $condition ) use ( &$passes, &$failures ): void {
	if ( $condition ) {
		++$passes;
		WP_CLI::log( 'PASS | ' . $label );
		return;
	}

	$failures[] = $label;
	WP_CLI::warning( 'FAIL | ' . $label );
};

/** @var PluginUtil $plugin_util */
$plugin_util = wc_get_container()->get( PluginUtil::class );
$hosts       = array(
	'yangsheep-checkout-optimizer/yangsheep-checkout-optimization.php' => '1.7.9',
	'ys-paynow-shipping/ys-paynow-shipping.php'                         => '1.5.11',
	'ys-raq-addons/ys-raq-addons.php'                                   => '2.3.20',
	'ys-shopline-via-woocommerce/ys-shopline-via-woocommerce.php'       => '3.6.4',
	'ys-webp-tools/ys-webp-tools.php'                                   => '1.2.3',
);

$check( 'loaded Hub Client is 2.0.5', defined( 'YS_HUB_CLIENT_VERSION' ) && '2.0.5' === YS_HUB_CLIENT_VERSION );
$check( 'vendored Hub path is not a registered plugin ID', false === $plugin_util->get_wp_plugin_id( YS_HUB_CLIENT_FILE ) );
$check( 'HPOS remains enabled', OrderUtil::custom_orders_table_usage_is_enabled() );

foreach ( $hosts as $plugin_id => $expected_version ) {
	$plugin_file = WP_PLUGIN_DIR . '/' . $plugin_id;
	$headers     = get_file_data( $plugin_file, array( 'version' => 'Version' ), 'plugin' );
	$features    = FeaturesUtil::get_compatible_features_for_plugin( $plugin_id );

	$check(
		$plugin_id . ' version is ' . $expected_version,
		$expected_version === ( $headers['version'] ?? '' )
	);
	$check(
		$plugin_id . ' owns an HPOS-compatible declaration',
		in_array( 'custom_order_tables', $features['compatible'] ?? array(), true )
	);
}

WP_CLI::log(
	wp_json_encode(
		array(
			'pass' => $passes,
			'fail' => count( $failures ),
		)
	)
);

if ( $failures ) {
	throw new RuntimeException( 'Integration failures: ' . implode( ', ', $failures ) );
}
