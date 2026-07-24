<?php
/**
 * Versioned import/export for every checkout optimizer setting.
 *
 * @package YangSheep\CheckoutOptimizer\Settings
 */

namespace YangSheep\CheckoutOptimizer\Settings;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Validates a complete settings package before applying any writes.
 *
 * Runtime storage spans the YS settings manager and a small WPLoyalty option.
 * A compensating rollback keeps both stores consistent if either write path
 * fails.
 */
class YSSettingsTransfer {

    public const FORMAT = 'yangsheep-checkout-optimizer-settings';
    public const SCHEMA_VERSION = 1;
    public const MAX_JSON_BYTES = 1048576;
    public const LOCK_OPTION = 'ys_checkout_settings_write_lock';
    public const LOCK_TTL = 300;

    public const WPLOYALTY_DEFAULTS = array(
        'points_label'   => '購物金',
        'button_text'    => '點此兌換折扣',
        'available_text' => '目前有 {points} {label} 可用',
    );

    /** @var callable */
    private $setting_reader;

    /** @var callable */
    private $setting_writer;

    /** @var callable */
    private $integration_reader;

    /** @var callable */
    private $integration_writer;

    /** @var callable */
    private $refresh;

    /**
     * @param callable|null $setting_reader      fn(string $key): mixed
     * @param callable|null $setting_writer      fn(string $key, mixed $value): bool
     * @param callable|null $integration_reader  fn(): array
     * @param callable|null $integration_writer  fn(array $settings): bool
     * @param callable|null $refresh             fn(): void
     */
    public function __construct(
        ?callable $setting_reader = null,
        ?callable $setting_writer = null,
        ?callable $integration_reader = null,
        ?callable $integration_writer = null,
        ?callable $refresh = null
    ) {
        $this->setting_reader = $setting_reader ?? static function ( string $key ) {
            return YSSettingsManager::get( $key, YSSettingsManager::get_default( $key ) );
        };
        $this->setting_writer = $setting_writer ?? static function ( string $key, $value ): bool {
            return YSSettingsManager::set( $key, $value );
        };
        $this->integration_reader = $integration_reader ?? static function (): array {
            $stored = get_option( 'yangsheep_wployalty_settings', array() );
            return is_array( $stored ) ? $stored : array();
        };
        $this->integration_writer = $integration_writer ?? static function ( array $settings ): bool {
            return update_option( 'yangsheep_wployalty_settings', $settings );
        };
        $this->refresh = $refresh ?? static function (): void {
            YSSettingsManager::refresh();
        };
    }

    /**
     * Build a deterministic, complete export package.
     *
     * @param string|null $plugin_version Plugin version override for tests.
     * @param string|null $exported_at    ISO 8601 timestamp override for tests.
     * @return array
     */
    public function export_package( ?string $plugin_version = null, ?string $exported_at = null ): array {
        $settings = array();
        foreach ( YSSettingsManager::ALL_SETTING_KEYS as $key ) {
            $value      = call_user_func( $this->setting_reader, $key );
            $normalized = self::normalize_setting_value( $key, $value );
            $settings[ $key ] = $normalized['valid'] ? $normalized['value'] : YSSettingsManager::get_default( $key );
        }

        $stored_wployalty = call_user_func( $this->integration_reader );
        $stored_wployalty = is_array( $stored_wployalty ) ? $stored_wployalty : array();
        $stored_wployalty = array_intersect_key(
            array_merge( self::WPLOYALTY_DEFAULTS, $stored_wployalty ),
            self::WPLOYALTY_DEFAULTS
        );
        $wployalty = array();
        foreach ( self::WPLOYALTY_DEFAULTS as $key => $default ) {
            $normalized = self::normalize_label( $stored_wployalty[ $key ], $key );
            $wployalty[ $key ] = null === $normalized ? $default : $normalized;
        }

        return array(
            'format'         => self::FORMAT,
            'schema_version' => self::SCHEMA_VERSION,
            'plugin_version' => $plugin_version ?? ( defined( 'YANGSHEEP_CHECKOUT_OPTIMIZATION_VERSION' ) ? YANGSHEEP_CHECKOUT_OPTIMIZATION_VERSION : 'unknown' ),
            'exported_at'    => $exported_at ?? gmdate( DATE_ATOM ),
            'settings'       => $settings,
            'integrations'   => array(
                'wployalty' => $wployalty,
            ),
        );
    }

    /**
     * Encode the current package for download.
     *
     * @return string
     */
    public function export_json(): string {
        $flags = JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE;
        $json  = json_encode( $this->export_package(), $flags );

        return false === $json ? '' : $json;
    }

    /**
     * Acquire the shared settings write lock.
     *
     * @return string|null Lock token, or null when another write is active.
     */
    public static function acquire_lock(): ?string {
        $current = get_option( self::LOCK_OPTION, null );
        if (
            is_array( $current )
            && isset( $current['created_at'] )
            && (int) $current['created_at'] < time() - self::LOCK_TTL
        ) {
            delete_option( self::LOCK_OPTION );
            $current = null;
        }
        if ( null !== $current && false !== $current ) {
            return null;
        }

        try {
            $token = function_exists( 'wp_generate_uuid4' ) ? wp_generate_uuid4() : bin2hex( random_bytes( 16 ) );
        } catch ( \Throwable $exception ) {
            return null;
        }

        $created = add_option(
            self::LOCK_OPTION,
            array(
                'token'      => $token,
                'created_at' => time(),
            ),
            '',
            false
        );

        return $created ? $token : null;
    }

    /**
     * Whether another settings mutation currently owns the lock.
     */
    public static function is_locked(): bool {
        return null !== get_option( self::LOCK_OPTION, null );
    }

    /**
     * Release only the caller's own lock.
     */
    public static function release_lock( string $token ): void {
        $current = get_option( self::LOCK_OPTION, null );
        if ( is_array( $current ) && isset( $current['token'] ) && hash_equals( (string) $current['token'], $token ) ) {
            delete_option( self::LOCK_OPTION );
        }
    }

    /**
     * Validate and normalize a complete package without writing storage.
     *
     * @param array $package Parsed package.
     * @return array{valid:bool,errors:array,data:?array}
     */
    public static function validate_package( array $package ): array {
        $errors = array();
        $root_keys = array( 'format', 'schema_version', 'plugin_version', 'exported_at', 'settings', 'integrations' );
        self::validate_exact_keys( $package, $root_keys, 'package', $errors );

        if ( ( $package['format'] ?? null ) !== self::FORMAT ) {
            $errors[] = 'Invalid package format.';
        }
        if ( ( $package['schema_version'] ?? null ) !== self::SCHEMA_VERSION ) {
            $errors[] = 'Unsupported schema version.';
        }
        if ( ! isset( $package['plugin_version'] ) || ! is_string( $package['plugin_version'] ) || '' === trim( $package['plugin_version'] ) ) {
            $errors[] = 'Invalid plugin version.';
        }
        if ( ! isset( $package['exported_at'] ) || ! is_string( $package['exported_at'] ) || false === strtotime( $package['exported_at'] ) ) {
            $errors[] = 'Invalid export timestamp.';
        }

        $normalized_settings = array();
        if ( ! isset( $package['settings'] ) || ! is_array( $package['settings'] ) ) {
            $errors[] = 'Settings must be an object.';
        } else {
            self::validate_exact_keys( $package['settings'], YSSettingsManager::ALL_SETTING_KEYS, 'settings', $errors );
            foreach ( YSSettingsManager::ALL_SETTING_KEYS as $key ) {
                if ( ! array_key_exists( $key, $package['settings'] ) ) {
                    continue;
                }
                $normalized = self::normalize_setting_value( $key, $package['settings'][ $key ] );
                if ( ! $normalized['valid'] ) {
                    $errors[] = sprintf( 'Invalid setting value: %s.', $key );
                    continue;
                }
                $normalized_settings[ $key ] = $normalized['value'];
            }
        }

        $normalized_wployalty = array();
        if ( ! isset( $package['integrations'] ) || ! is_array( $package['integrations'] ) ) {
            $errors[] = 'Integrations must be an object.';
        } else {
            self::validate_exact_keys( $package['integrations'], array( 'wployalty' ), 'integrations', $errors );
            $wployalty = $package['integrations']['wployalty'] ?? null;
            if ( ! is_array( $wployalty ) ) {
                $errors[] = 'WPLoyalty integration settings must be an object.';
            } else {
                self::validate_exact_keys( $wployalty, array_keys( self::WPLOYALTY_DEFAULTS ), 'integrations.wployalty', $errors );
                foreach ( self::WPLOYALTY_DEFAULTS as $key => $default ) {
                    if ( ! array_key_exists( $key, $wployalty ) ) {
                        continue;
                    }
                    $value = self::normalize_label( $wployalty[ $key ], $key );
                    if ( null === $value ) {
                        $errors[] = sprintf( 'Invalid WPLoyalty setting value: %s.', $key );
                        continue;
                    }
                    $normalized_wployalty[ $key ] = $value;
                }
            }
        }

        if ( ! empty( $errors ) ) {
            return array(
                'valid'  => false,
                'errors' => array_values( array_unique( $errors ) ),
                'data'   => null,
            );
        }

        return array(
            'valid'  => true,
            'errors' => array(),
            'data'   => array(
                'settings'     => $normalized_settings,
                'integrations' => array(
                    'wployalty' => $normalized_wployalty,
                ),
            ),
        );
    }

    /**
     * Decode and import a JSON payload.
     *
     * @param string $json JSON package.
     * @return array
     */
    public function import_json( string $json ): array {
        if ( '' === trim( $json ) || strlen( $json ) > self::MAX_JSON_BYTES ) {
            return self::failure( array( 'Settings file is empty or too large.' ) );
        }

        try {
            $package = json_decode( $json, true, 64, JSON_THROW_ON_ERROR );
        } catch ( \JsonException $exception ) {
            return self::failure( array( 'Settings file is not valid JSON.' ) );
        }

        if ( ! is_array( $package ) ) {
            return self::failure( array( 'Settings package must be a JSON object.' ) );
        }

        return $this->import_package( $package );
    }

    /**
     * Apply a validated package and compensate every successful prior write if a
     * later write fails.
     *
     * @param array $package Parsed package.
     * @return array
     */
    public function import_package( array $package ): array {
        $validation = self::validate_package( $package );
        if ( ! $validation['valid'] ) {
            return self::failure( $validation['errors'] );
        }

        $data = $validation['data'];
        $snapshot_settings = array();
        foreach ( YSSettingsManager::ALL_SETTING_KEYS as $key ) {
            $snapshot_settings[ $key ] = call_user_func( $this->setting_reader, $key );
        }
        $snapshot_wployalty = call_user_func( $this->integration_reader );
        $snapshot_wployalty = is_array( $snapshot_wployalty ) ? $snapshot_wployalty : array();
        $snapshot_wployalty = array_intersect_key(
            array_merge( self::WPLOYALTY_DEFAULTS, $snapshot_wployalty ),
            self::WPLOYALTY_DEFAULTS
        );

        $changed_keys = array();
        foreach ( YSSettingsManager::ALL_SETTING_KEYS as $key ) {
            $next = $data['settings'][ $key ];
            if ( $snapshot_settings[ $key ] === $next ) {
                continue;
            }
            if ( ! call_user_func( $this->setting_writer, $key, $next ) ) {
                $rolled_back = $this->rollback( $snapshot_settings, $snapshot_wployalty, $changed_keys, false );
                return self::failure( array( sprintf( 'Failed to write setting: %s.', $key ) ), $rolled_back );
            }
            $changed_keys[] = $key;
        }

        $next_wployalty = $data['integrations']['wployalty'];
        $integration_changed = $snapshot_wployalty !== $next_wployalty;
        if ( $integration_changed && ! call_user_func( $this->integration_writer, $next_wployalty ) ) {
            $rolled_back = $this->rollback( $snapshot_settings, $snapshot_wployalty, $changed_keys, true );
            return self::failure( array( 'Failed to write WPLoyalty integration settings.' ), $rolled_back );
        }

        call_user_func( $this->refresh );

        return array(
            'success'     => true,
            'rolled_back' => false,
            'errors'      => array(),
            'updated'     => count( $changed_keys ) + ( $integration_changed ? 1 : 0 ),
        );
    }

    /**
     * @param array $snapshot_settings
     * @param array $snapshot_wployalty
     * @param array $changed_keys
     * @param bool  $restore_integration
     * @return bool
     */
    private function rollback(
        array $snapshot_settings,
        array $snapshot_wployalty,
        array $changed_keys,
        bool $restore_integration
    ): bool {
        $success = true;
        foreach ( array_reverse( $changed_keys ) as $key ) {
            if ( ! call_user_func( $this->setting_writer, $key, $snapshot_settings[ $key ] ) ) {
                $success = false;
            }
        }
        if ( $restore_integration && ! call_user_func( $this->integration_writer, $snapshot_wployalty ) ) {
            $success = false;
        }
        call_user_func( $this->refresh );

        return $success;
    }

    /**
     * Reject missing and unknown keys.
     *
     * @param array  $value
     * @param array  $expected
     * @param string $path
     * @param array  $errors
     * @return void
     */
    private static function validate_exact_keys( array $value, array $expected, string $path, array &$errors ): void {
        $unknown = array_values( array_diff( array_keys( $value ), $expected ) );
        $missing = array_values( array_diff( $expected, array_keys( $value ) ) );
        if ( ! empty( $unknown ) ) {
            $errors[] = sprintf( 'Unknown keys at %s: %s.', $path, implode( ', ', $unknown ) );
        }
        if ( ! empty( $missing ) ) {
            $errors[] = sprintf( 'Missing keys at %s: %s.', $path, implode( ', ', $missing ) );
        }
    }

    /**
     * @param string $key
     * @param mixed  $value
     * @return array{valid:bool,value:mixed}
     */
    public static function normalize_setting_value( string $key, $value ): array {
        $default = YSSettingsManager::get_default( $key );

        if ( is_array( $default ) ) {
            if ( ! is_array( $value ) ) {
                return array( 'valid' => false, 'value' => null );
            }
            $methods = array();
            foreach ( $value as $method ) {
                if ( ! is_string( $method ) ) {
                    return array( 'valid' => false, 'value' => null );
                }
                $method = trim( $method );
                if ( '' === $method || strlen( $method ) > 191 || ! preg_match( '/^[a-z0-9_.:-]+$/i', $method ) ) {
                    return array( 'valid' => false, 'value' => null );
                }
                $methods[] = $method;
            }
            return array(
                'valid' => true,
                'value' => array_values( array_unique( $methods ) ),
            );
        }

        if ( in_array( $default, array( 'yes', 'no' ), true ) ) {
            return array(
                'valid' => is_string( $value ) && in_array( $value, array( 'yes', 'no' ), true ),
                'value' => $value,
            );
        }

        if ( is_string( $default ) && preg_match( '/^#[0-9a-f]{6}$/i', $default ) ) {
            return array(
                'valid' => is_string( $value ) && 1 === preg_match( '/^#[0-9a-f]{6}$/i', $value ),
                'value' => is_string( $value ) ? strtolower( $value ) : $value,
            );
        }

        if ( 'yangsheep_checkout_block_border_radius' === $key ) {
            $valid = is_string( $value )
                && 1 === preg_match( '/^(?:0|(?:\d{1,2}(?:\.\d{1,2})?))(?:px|rem|em|%)$/', $value );
            return array( 'valid' => $valid, 'value' => $value );
        }

        return array( 'valid' => false, 'value' => null );
    }

    /**
     * @param mixed $value
     * @return string|null
     */
    private static function normalize_label( $value, string $key ): ?string {
        if ( ! is_string( $value ) ) {
            return null;
        }
        if ( strip_tags( $value ) !== $value ) {
            return null;
        }
        $normalized = trim( $value );
        $normalized = preg_replace( '/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $normalized );
        if ( ! is_string( $normalized ) || '' === $normalized || strlen( $normalized ) > 240 ) {
            return null;
        }
        preg_match_all( '/\{[^}]+\}/', $normalized, $matches );
        $placeholders = array_values( array_unique( $matches[0] ?? array() ) );
        if ( 'available_text' === $key ) {
            if (
                array_diff( $placeholders, array( '{points}', '{label}' ) )
                || ! str_contains( $normalized, '{points}' )
                || ! str_contains( $normalized, '{label}' )
            ) {
                return null;
            }
        } elseif ( ! empty( $placeholders ) ) {
            return null;
        }

        return $normalized;
    }

    /**
     * @param array $errors
     * @param bool  $rolled_back
     * @return array
     */
    private static function failure( array $errors, bool $rolled_back = false ): array {
        return array(
            'success'     => false,
            'rolled_back' => $rolled_back,
            'errors'      => array_values( $errors ),
            'updated'     => 0,
        );
    }
}
