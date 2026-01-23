<?php
/**
 * YSSettingsMigrator - 設定資料遷移類別
 *
 * @package YangSheep\CheckoutOptimizer\Settings
 */

namespace YangSheep\CheckoutOptimizer\Settings;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * 負責從 wp_options 遷移設定到自訂資料表
 */
class YSSettingsMigrator {

    /**
     * 遷移版本 option key
     *
     * @var string
     */
    const MIGRATION_VERSION_KEY = 'ys_checkout_settings_migration_version';

    /**
     * 當前遷移版本
     *
     * @var int
     */
    const CURRENT_MIGRATION_VERSION = 1;

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
     * Repository 實例
     *
     * @var YSSettingsRepository
     */
    private $repository;

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
        $this->repository  = YSSettingsRepository::instance();
    }

    /**
     * 檢查是否需要遷移
     *
     * @return bool
     */
    public function migration_required(): bool {
        $current_version = get_option( self::MIGRATION_VERSION_KEY, 0 );
        return (int) $current_version < self::CURRENT_MIGRATION_VERSION;
    }

    /**
     * 取得已遷移版本
     *
     * @return int
     */
    public function get_migration_version(): int {
        return (int) get_option( self::MIGRATION_VERSION_KEY, 0 );
    }

    /**
     * 執行遷移
     *
     * @return array 遷移結果
     */
    public function migrate(): array {
        $result = array(
            'success'  => false,
            'migrated' => 0,
            'skipped'  => 0,
            'errors'   => array(),
        );

        // 確保資料表存在
        if ( ! $this->table_maker->table_exists() ) {
            $this->table_maker->create_table();
        }

        if ( ! $this->table_maker->table_exists() ) {
            $result['errors'][] = '無法建立資料表';
            return $result;
        }

        // 遷移所有設定
        foreach ( YSSettingsManager::ALL_SETTING_KEYS as $key ) {
            $value = get_option( $key, null );

            // 如果 wp_options 中有值，遷移到新表
            if ( null !== $value && false !== $value ) {
                $success = $this->repository->set( $key, $value );
                if ( $success ) {
                    $result['migrated']++;
                } else {
                    $result['errors'][] = "遷移 {$key} 失敗";
                }
            } else {
                $result['skipped']++;
            }
        }

        // 標記遷移完成
        $this->mark_migration_complete();

        $result['success'] = empty( $result['errors'] );
        return $result;
    }

    /**
     * 標記遷移完成
     *
     * @return void
     */
    private function mark_migration_complete(): void {
        update_option( self::MIGRATION_VERSION_KEY, self::CURRENT_MIGRATION_VERSION );
    }

    /**
     * 回滾遷移（從自訂表複製回 wp_options）
     *
     * @return array 回滾結果
     */
    public function rollback(): array {
        $result = array(
            'success'   => false,
            'rolled_back' => 0,
            'errors'    => array(),
        );

        if ( ! $this->table_maker->table_exists() ) {
            $result['errors'][] = '資料表不存在';
            return $result;
        }

        $settings = $this->repository->get_all( false );

        foreach ( $settings as $key => $value ) {
            if ( update_option( $key, $value ) ) {
                $result['rolled_back']++;
            } else {
                $result['errors'][] = "回滾 {$key} 失敗";
            }
        }

        $result['success'] = empty( $result['errors'] );
        return $result;
    }

    /**
     * 清理 wp_options 中的舊設定
     *
     * @return int 已刪除的設定數量
     */
    public function cleanup_wp_options(): int {
        $deleted = 0;

        foreach ( YSSettingsManager::ALL_SETTING_KEYS as $key ) {
            if ( delete_option( $key ) ) {
                $deleted++;
            }
        }

        return $deleted;
    }

    /**
     * 統計 wp_options 中的設定數量
     *
     * @return int
     */
    public function count_wp_options(): int {
        $count = 0;

        foreach ( YSSettingsManager::ALL_SETTING_KEYS as $key ) {
            if ( false !== get_option( $key, false ) ) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * 取得遷移狀態資訊
     *
     * @return array
     */
    public function get_status(): array {
        $table_exists = $this->table_maker->table_exists();

        return array(
            'table_exists'       => $table_exists,
            'table_name'         => $this->table_maker->get_table_name(),
            'schema_version'     => $this->table_maker->get_schema_version(),
            'installed_schema'   => $this->table_maker->get_installed_schema_version(),
            'migration_version'  => $this->get_migration_version(),
            'migration_required' => $this->migration_required(),
            'settings_in_table'  => $table_exists ? $this->repository->count() : 0,
            'settings_in_options' => $this->count_wp_options(),
            'total_setting_keys' => count( YSSettingsManager::ALL_SETTING_KEYS ),
        );
    }

    /**
     * 完整重置（刪除資料表和遷移標記）
     *
     * @return bool
     */
    public function reset(): bool {
        // 先回滾資料
        $this->rollback();

        // 刪除資料表
        $this->table_maker->drop_table();

        // 刪除遷移標記
        delete_option( self::MIGRATION_VERSION_KEY );

        // 重新整理 Manager 快取
        YSSettingsManager::refresh();

        return true;
    }
}
