<?php
/**
 * YSSettingsRepository - 設定 CRUD 操作類別
 *
 * @package YangSheep\CheckoutOptimizer\Settings
 */

namespace YangSheep\CheckoutOptimizer\Settings;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * 負責設定的 CRUD 操作和快取管理
 */
class YSSettingsRepository {

    /**
     * 設定快取
     *
     * @var array|null
     */
    private static $cache = null;

    /**
     * 快取是否已載入
     *
     * @var bool
     */
    private static $cache_loaded = false;

    /**
     * 單例實例
     *
     * @var self|null
     */
    private static $instance = null;

    /**
     * Table Maker 實例
     *
     * @var YSSettingsTableMaker
     */
    private $table_maker;

    /**
     * 取得單例實例
     *
     * @return self
     */
    public static function instance(): self {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * 私有建構子
     */
    private function __construct() {
        $this->table_maker = YSSettingsTableMaker::instance();
    }

    /**
     * 取得資料表名稱
     *
     * @return string
     */
    private function get_table_name(): string {
        return $this->table_maker->get_table_name();
    }

    /**
     * 檢查資料表是否存在
     *
     * @return bool
     */
    public function table_exists(): bool {
        return $this->table_maker->table_exists();
    }

    /**
     * 取得設定值
     *
     * @param string $key     設定 key
     * @param mixed  $default 預設值
     * @return mixed
     */
    public function get( string $key, $default = false ) {
        $this->prime_cache();

        if ( isset( self::$cache[ $key ] ) ) {
            return self::$cache[ $key ];
        }

        return $default;
    }

    /**
     * 設定值
     *
     * @param string $key      設定 key
     * @param mixed  $value    設定值
     * @param string $autoload 是否自動載入 ('yes' 或 'no')
     * @return bool
     */
    public function set( string $key, $value, string $autoload = 'yes' ): bool {
        global $wpdb;

        if ( ! $this->table_exists() ) {
            return false;
        }

        $table_name    = $this->get_table_name();
        $serialized    = maybe_serialize( $value );
        $key_exists    = $this->key_exists_in_db( $key );

        if ( $key_exists ) {
            // 更新現有設定
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $result = $wpdb->update(
                $table_name,
                array(
                    'setting_value' => $serialized,
                    'autoload'      => $autoload,
                ),
                array( 'setting_key' => $key ),
                array( '%s', '%s' ),
                array( '%s' )
            );
        } else {
            // 新增設定
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
            $result = $wpdb->insert(
                $table_name,
                array(
                    'setting_key'   => $key,
                    'setting_value' => $serialized,
                    'autoload'      => $autoload,
                ),
                array( '%s', '%s', '%s' )
            );
        }

        // 更新快取
        if ( false !== $result ) {
            self::$cache[ $key ] = $value;
            return true;
        }

        return false;
    }

    /**
     * 刪除設定
     *
     * @param string $key 設定 key
     * @return bool
     */
    public function delete( string $key ): bool {
        global $wpdb;

        if ( ! $this->table_exists() ) {
            return false;
        }

        $table_name = $this->get_table_name();

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $result = $wpdb->delete(
            $table_name,
            array( 'setting_key' => $key ),
            array( '%s' )
        );

        if ( false !== $result ) {
            unset( self::$cache[ $key ] );
            return true;
        }

        return false;
    }

    /**
     * 取得所有設定
     *
     * @param bool $only_autoload 是否只取 autoload='yes' 的設定
     * @return array
     */
    public function get_all( bool $only_autoload = true ): array {
        global $wpdb;

        if ( ! $this->table_exists() ) {
            return array();
        }

        $table_name = $this->get_table_name();

        if ( $only_autoload ) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $results = $wpdb->get_results(
                "SELECT setting_key, setting_value FROM {$table_name} WHERE autoload = 'yes'",
                ARRAY_A
            );
        } else {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $results = $wpdb->get_results(
                "SELECT setting_key, setting_value FROM {$table_name}",
                ARRAY_A
            );
        }

        if ( empty( $results ) ) {
            return array();
        }

        $settings = array();
        foreach ( $results as $row ) {
            $settings[ $row['setting_key'] ] = maybe_unserialize( $row['setting_value'] );
        }

        return $settings;
    }

    /**
     * 批次設定多個值
     *
     * @param array  $settings key => value 陣列
     * @param string $autoload 是否自動載入
     * @return bool
     */
    public function set_many( array $settings, string $autoload = 'yes' ): bool {
        if ( empty( $settings ) ) {
            return true;
        }

        $success = true;
        foreach ( $settings as $key => $value ) {
            if ( ! $this->set( $key, $value, $autoload ) ) {
                $success = false;
            }
        }

        return $success;
    }

    /**
     * 批次刪除多個設定
     *
     * @param array $keys 要刪除的 keys
     * @return bool
     */
    public function delete_many( array $keys ): bool {
        if ( empty( $keys ) ) {
            return true;
        }

        $success = true;
        foreach ( $keys as $key ) {
            if ( ! $this->delete( $key ) ) {
                $success = false;
            }
        }

        return $success;
    }

    /**
     * 預先載入快取
     *
     * @return void
     */
    public function prime_cache(): void {
        if ( self::$cache_loaded ) {
            return;
        }

        self::$cache        = $this->get_all( true );
        self::$cache_loaded = true;
    }

    /**
     * 清除快取
     *
     * @return void
     */
    public function flush_cache(): void {
        self::$cache        = null;
        self::$cache_loaded = false;
    }

    /**
     * 檢查資料庫中是否存在指定的 key（直接查詢，不使用快取）
     *
     * @param string $key 設定 key
     * @return bool
     */
    private function key_exists_in_db( string $key ): bool {
        global $wpdb;

        if ( ! $this->table_exists() ) {
            return false;
        }

        $table_name = $this->get_table_name();

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $count = $wpdb->get_var(
            $wpdb->prepare(
                // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                "SELECT COUNT(*) FROM {$table_name} WHERE setting_key = %s",
                $key
            )
        );

        return (int) $count > 0;
    }

    /**
     * 取得原始資料庫值（不經過快取）
     *
     * @param string $key 設定 key
     * @return mixed|false 找不到時回傳 false
     */
    private function get_raw( string $key ) {
        global $wpdb;

        if ( ! $this->table_exists() ) {
            return false;
        }

        $table_name = $this->get_table_name();

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $row = $wpdb->get_row(
            $wpdb->prepare(
                // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                "SELECT setting_value FROM {$table_name} WHERE setting_key = %s",
                $key
            ),
            ARRAY_A
        );

        // 使用 get_row 來區分「找不到」和「值為 NULL」的情況
        if ( null === $row ) {
            return false;
        }

        return maybe_unserialize( $row['setting_value'] );
    }

    /**
     * 檢查設定是否存在
     *
     * @param string $key 設定 key
     * @return bool
     */
    public function exists( string $key ): bool {
        return false !== $this->get_raw( $key );
    }

    /**
     * 取得設定數量
     *
     * @return int
     */
    public function count(): int {
        global $wpdb;

        if ( ! $this->table_exists() ) {
            return 0;
        }

        $table_name = $this->get_table_name();

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table_name}" );
    }
}
