<?php
/**
 * Settings migration behavior matrix.
 *
 * Run with:
 *   php tests/settings-migration-matrix.php
 */

define( 'ABSPATH', __DIR__ );

$GLOBALS['ys_test_options'] = array();

function get_option( $key, $default = false ) {
    return array_key_exists( $key, $GLOBALS['ys_test_options'] )
        ? $GLOBALS['ys_test_options'][ $key ]
        : $default;
}

function update_option( $key, $value ) {
    $GLOBALS['ys_test_options'][ $key ] = $value;
    return true;
}

function delete_option( $key ) {
    if ( ! array_key_exists( $key, $GLOBALS['ys_test_options'] ) ) {
        return false;
    }
    unset( $GLOBALS['ys_test_options'][ $key ] );
    return true;
}

require_once dirname( __DIR__ ) . '/src/Settings/YSSettingsManager.php';
require_once dirname( __DIR__ ) . '/src/Settings/YSSettingsMigrator.php';

use YangSheep\CheckoutOptimizer\Settings\YSSettingsMigrator;

final class YSTestTableMaker {
    public function table_exists() {
        return true;
    }

    public function create_table() {
        return true;
    }
}

final class YSTestRepository {
    public $values = array();
    public $set_calls = array();
    public $fail_set = false;
    public $fail_delete = false;

    public function set( $key, $value ) {
        $this->set_calls[] = array( $key, $value );
        if ( $this->fail_set ) {
            return false;
        }
        $this->values[ $key ] = $value;
        return true;
    }

    public function delete_many( array $keys ) {
        if ( $this->fail_delete ) {
            return false;
        }
        foreach ( $keys as $key ) {
            unset( $this->values[ $key ] );
        }
        return true;
    }
}

function make_migrator( YSTestRepository $repository ) {
    $reflection = new ReflectionClass( YSSettingsMigrator::class );
    $migrator = $reflection->newInstanceWithoutConstructor();

    foreach ( array(
        'table_maker' => new YSTestTableMaker(),
        'repository'  => $repository,
    ) as $property_name => $value ) {
        $property = $reflection->getProperty( $property_name );
        $property->setValue( $migrator, $value );
    }

    return $migrator;
}

$total = 0;
$failed = 0;

function check_case( $condition, $label ) {
    global $total, $failed;
    $total++;
    if ( $condition ) {
        echo "PASS {$label}\n";
        return;
    }
    $failed++;
    echo "FAIL {$label}\n";
}

$retired_key = YSSettingsMigrator::RETIRED_SETTING_KEYS[0];
$current_key = 'yangsheep_checkout_section_bg_color';

// v1 -> v2: custom table is authoritative. Stale wp_options must not replay.
$GLOBALS['ys_test_options'] = array(
    YSSettingsMigrator::MIGRATION_VERSION_KEY => 1,
    $current_key => '#stale-option',
    $retired_key => 'retired-option',
);
$repository = new YSTestRepository();
$repository->values = array(
    $current_key => '#current-table',
    $retired_key => 'retired-table',
);
$result = make_migrator( $repository )->migrate();

check_case( true === $result['success'], 'v1 to v2 cleanup succeeds' );
check_case( array() === $repository->set_calls, 'v2 does not replay current wp_options' );
check_case( '#current-table' === $repository->values[ $current_key ], 'v2 preserves current custom-table value' );
check_case( ! isset( $repository->values[ $retired_key ] ), 'v2 removes retired custom-table row' );
check_case( ! isset( $GLOBALS['ys_test_options'][ $retired_key ] ), 'v2 removes retired wp_option' );
check_case(
    2 === $GLOBALS['ys_test_options'][ YSSettingsMigrator::MIGRATION_VERSION_KEY ],
    'v2 records completion after successful cleanup'
);

// Failed v2 cleanup must remain retryable and preserve the custom-table row.
$GLOBALS['ys_test_options'] = array(
    YSSettingsMigrator::MIGRATION_VERSION_KEY => 1,
    $retired_key => 'retired-option',
);
$repository = new YSTestRepository();
$repository->values[ $retired_key ] = 'retired-table';
$repository->fail_delete = true;
$result = make_migrator( $repository )->migrate();

check_case( false === $result['success'], 'failed v2 cleanup reports failure' );
check_case(
    1 === $GLOBALS['ys_test_options'][ YSSettingsMigrator::MIGRATION_VERSION_KEY ],
    'failed v2 cleanup does not advance version'
);
check_case(
    'retired-table' === $repository->values[ $retired_key ],
    'failed v2 cleanup preserves the table row for retry'
);

// v0 -> current: legacy options still migrate once, then retired values are removed.
$GLOBALS['ys_test_options'] = array(
    YSSettingsMigrator::MIGRATION_VERSION_KEY => 0,
    $current_key => '#legacy-option',
    $retired_key => 'retired-option',
);
$repository = new YSTestRepository();
$repository->values[ $retired_key ] = 'retired-table';
$result = make_migrator( $repository )->migrate();

check_case( true === $result['success'], 'fresh legacy migration succeeds' );
check_case( '#legacy-option' === $repository->values[ $current_key ], 'v0 migrates current legacy option once' );
check_case( ! isset( $repository->values[ $retired_key ] ), 'v0 never retains retired setting' );

// Failed writes must not advance the migration version; the next request can retry.
$GLOBALS['ys_test_options'] = array(
    YSSettingsMigrator::MIGRATION_VERSION_KEY => 0,
    $current_key => '#legacy-option',
);
$repository = new YSTestRepository();
$repository->fail_set = true;
$result = make_migrator( $repository )->migrate();

check_case( false === $result['success'], 'failed legacy write reports failure' );
check_case(
    0 === $GLOBALS['ys_test_options'][ YSSettingsMigrator::MIGRATION_VERSION_KEY ],
    'failed migration does not advance version'
);

echo "SUMMARY total={$total} failed={$failed}\n";
exit( $failed > 0 ? 1 : 0 );
