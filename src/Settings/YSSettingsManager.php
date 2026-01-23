<?php
/**
 * YSSettingsManager - 設定管理門面類別
 *
 * @package YangSheep\CheckoutOptimizer\Settings
 */

namespace YangSheep\CheckoutOptimizer\Settings;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * 統一的設定存取介面，自動處理 fallback
 */
class YSSettingsManager {

    /**
     * 單例實例
     *
     * @var self|null
     */
    private static $instance = null;

    /**
     * Repository 實例
     *
     * @var YSSettingsRepository
     */
    private $repository;

    /**
     * Table Maker 實例
     *
     * @var YSSettingsTableMaker
     */
    private $table_maker;

    /**
     * 是否使用自訂資料表
     *
     * @var bool|null
     */
    private $use_custom_table = null;

    /**
     * 所有設定 keys（用於遷移和清理）
     *
     * @var array
     */
    public const ALL_SETTING_KEYS = array(
        // Checkbox 設定
        'yangsheep_enable_order_enhancement',
        'yangsheep_enable_manual_tracking',
        'yangsheep_checkout_close_lname',
        'yangsheep_checkout_tw_fields',
        'yangsheep_checkout_order_note',
        'yangsheep_myaccount_visual',
        'yangsheep_wployalty_enable',

        // 文字設定
        'yangsheep_checkout_login_welcome_text',
        'yangsheep_checkout_login_text_padding',
        'yangsheep_checkout_block_border_radius',

        // 登入區塊樣式
        'yangsheep_checkout_login_text_color',
        'yangsheep_checkout_login_text_bg',

        // 結帳按鈕樣式
        'yangsheep_checkout_button_bg_color',
        'yangsheep_checkout_button_text_color',
        'yangsheep_checkout_button_hover_bg',
        'yangsheep_checkout_button_hover_text',

        // 區塊樣式
        'yangsheep_checkout_section_border_color',
        'yangsheep_checkout_section_bg_color',
        'yangsheep_checkout_form_field_bg_color',
        'yangsheep_checkout_form_field_border_color',
        'yangsheep_checkout_link_color',
        'yangsheep_checkout_coupon_block_bg_color',
        'yangsheep_checkout_order_review_bg_color',
        'yangsheep_checkout_order_items_bg_color',

        // 付款區塊樣式
        'yangsheep_checkout_payment_bg_color',
        'yangsheep_payment_method_bg',
        'yangsheep_payment_method_bg_active',
        'yangsheep_payment_method_border',
        'yangsheep_payment_method_border_active',
        'yangsheep_payment_method_desc_bg',

        // 物流卡片樣式
        'yangsheep_shipping_card_radio_color',
        'yangsheep_shipping_card_border_active',
        'yangsheep_shipping_card_bg_color',
        'yangsheep_shipping_card_bg_active',
        'yangsheep_sidebar_bg_color',

        // 我的帳號樣式
        'yangsheep_myaccount_button_bg_color',
        'yangsheep_myaccount_button_text_color',
        'yangsheep_nav_button_hover_color',
        'yangsheep_nav_button_active_color',
        'yangsheep_myaccount_link_color',
        'yangsheep_myaccount_link_hover_color',

        // 訂單狀態標籤
        'yangsheep_status_pending_bg',
        'yangsheep_status_pending_text',
        'yangsheep_status_preparing_bg',
        'yangsheep_status_preparing_text',
        'yangsheep_status_shipping_bg',
        'yangsheep_status_shipping_text',
        'yangsheep_status_arrived_bg',
        'yangsheep_status_arrived_text',
        'yangsheep_status_completed_bg',
        'yangsheep_status_completed_text',

        // 陣列設定
        'yangsheep_cvs_shipping_methods',
    );

    /**
     * 預設值對照表
     *
     * @var array
     */
    public const DEFAULT_VALUES = array(
        // Checkbox 設定
        'yangsheep_enable_order_enhancement'  => 'no',
        'yangsheep_enable_manual_tracking'    => 'yes',
        'yangsheep_checkout_close_lname'      => 'no',
        'yangsheep_checkout_tw_fields'        => 'no',
        'yangsheep_checkout_order_note'       => 'yes',
        'yangsheep_myaccount_visual'          => 'no',
        'yangsheep_wployalty_enable'          => 'no',

        // 文字設定
        'yangsheep_checkout_login_welcome_text' => '',
        'yangsheep_checkout_login_text_padding' => '20px',
        'yangsheep_checkout_block_border_radius' => '8px',

        // 登入區塊樣式
        'yangsheep_checkout_login_text_color' => '#5a7080',
        'yangsheep_checkout_login_text_bg'    => '#e8eff3',

        // 結帳按鈕樣式
        'yangsheep_checkout_button_bg_color'    => '#8fa8b8',
        'yangsheep_checkout_button_text_color'  => '#ffffff',
        'yangsheep_checkout_button_hover_bg'    => '#7a95a6',
        'yangsheep_checkout_button_hover_text'  => '#ffffff',

        // 區塊樣式
        'yangsheep_checkout_section_border_color'   => '#c5d1d8',
        'yangsheep_checkout_section_bg_color'       => '#ffffff',
        'yangsheep_checkout_form_field_bg_color'    => '#f5f7f9',
        'yangsheep_checkout_form_field_border_color' => '#c5d1d8',
        'yangsheep_checkout_link_color'             => '#7a95a6',
        'yangsheep_checkout_coupon_block_bg_color'  => '#f5f8fa',
        'yangsheep_checkout_order_review_bg_color'  => '#f5f8fa',
        'yangsheep_checkout_order_items_bg_color'   => '#f5f8fa',

        // 付款區塊樣式
        'yangsheep_checkout_payment_bg_color'    => '#e8eff5',
        'yangsheep_payment_method_bg'            => '#ffffff',
        'yangsheep_payment_method_bg_active'     => '#e8eff5',
        'yangsheep_payment_method_border'        => '#c5d1d8',
        'yangsheep_payment_method_border_active' => '#8fa8b8',
        'yangsheep_payment_method_desc_bg'       => '#f5f8fa',

        // 物流卡片樣式
        'yangsheep_shipping_card_radio_color'   => '#8fa8b8',
        'yangsheep_shipping_card_border_active' => '#8fa8b8',
        'yangsheep_shipping_card_bg_color'      => '#ffffff',
        'yangsheep_shipping_card_bg_active'     => '#e8eff5',
        'yangsheep_sidebar_bg_color'            => '#ffffff',

        // 我的帳號樣式
        'yangsheep_myaccount_button_bg_color'   => '#8fa8b8',
        'yangsheep_myaccount_button_text_color' => '#ffffff',
        'yangsheep_nav_button_hover_color'      => '#7a95a6',
        'yangsheep_nav_button_active_color'     => '#8fa8b8',
        'yangsheep_myaccount_link_color'        => '#6b8a9a',
        'yangsheep_myaccount_link_hover_color'  => '#4a6a7a',

        // 訂單狀態標籤
        'yangsheep_status_pending_bg'     => '#f0f4f7',
        'yangsheep_status_pending_text'   => '#7a8b95',
        'yangsheep_status_preparing_bg'   => '#e8eff5',
        'yangsheep_status_preparing_text' => '#6b8a9a',
        'yangsheep_status_shipping_bg'    => '#fef6e8',
        'yangsheep_status_shipping_text'  => '#b8860b',
        'yangsheep_status_arrived_bg'     => '#f3e5f5',
        'yangsheep_status_arrived_text'   => '#7b1fa2',
        'yangsheep_status_completed_bg'   => '#e8eff5',
        'yangsheep_status_completed_text' => '#6b8a9a',

        // 陣列設定
        'yangsheep_cvs_shipping_methods' => array(),
    );

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
     * 取得設定值（靜態方法）
     *
     * @param string $key     設定 key
     * @param mixed  $default 預設值（若未提供則使用內建預設值）
     * @return mixed
     */
    public static function get( string $key, $default = null ) {
        $instance = self::instance();

        // 如果沒有傳入預設值，使用內建預設值
        if ( null === $default && isset( self::DEFAULT_VALUES[ $key ] ) ) {
            $default = self::DEFAULT_VALUES[ $key ];
        }

        // 優先使用自訂資料表
        if ( $instance->should_use_custom_table() ) {
            $value = $instance->repository->get( $key, null );
            if ( null !== $value ) {
                return $value;
            }
        }

        // Fallback 到 wp_options
        return get_option( $key, $default );
    }

    /**
     * 設定值（靜態方法）
     *
     * @param string $key   設定 key
     * @param mixed  $value 設定值
     * @return bool
     */
    public static function set( string $key, $value ): bool {
        $instance = self::instance();

        // 如果自訂資料表可用，同時更新兩邊
        if ( $instance->should_use_custom_table() ) {
            $instance->repository->set( $key, $value );
        }

        // 總是更新 wp_options（保持向後相容）
        return update_option( $key, $value );
    }

    /**
     * 刪除設定（靜態方法）
     *
     * @param string $key 設定 key
     * @return bool
     */
    public static function delete( string $key ): bool {
        $instance = self::instance();

        // 如果自訂資料表可用，同時刪除兩邊
        if ( $instance->should_use_custom_table() ) {
            $instance->repository->delete( $key );
        }

        // 總是刪除 wp_options
        return delete_option( $key );
    }

    /**
     * 檢查是否應使用自訂資料表
     *
     * @return bool
     */
    private function should_use_custom_table(): bool {
        if ( null === $this->use_custom_table ) {
            $this->use_custom_table = $this->table_maker->table_exists();
        }
        return $this->use_custom_table;
    }

    /**
     * 重新整理快取（強制重新檢查資料表狀態）
     *
     * @return void
     */
    public static function refresh(): void {
        $instance                    = self::instance();
        $instance->use_custom_table  = null;
        $instance->repository->flush_cache();
    }

    /**
     * 取得所有顏色設定
     *
     * @return array
     */
    public static function get_colors(): array {
        $colors = array();
        foreach ( self::DEFAULT_VALUES as $key => $default ) {
            // 顏色設定的 key 包含 'color'、'bg'、'text'、'border' 等
            if ( strpos( $key, 'color' ) !== false ||
                 strpos( $key, '_bg' ) !== false ||
                 strpos( $key, '_text' ) !== false ||
                 strpos( $key, 'border' ) !== false ) {
                if ( is_string( $default ) && strpos( $default, '#' ) === 0 ) {
                    $colors[ $key ] = self::get( $key, $default );
                }
            }
        }
        return $colors;
    }

    /**
     * 取得所有 Checkbox 設定
     *
     * @return array
     */
    public static function get_checkboxes(): array {
        $checkboxes = array(
            'yangsheep_enable_order_enhancement',
            'yangsheep_enable_manual_tracking',
            'yangsheep_checkout_close_lname',
            'yangsheep_checkout_tw_fields',
            'yangsheep_checkout_order_note',
            'yangsheep_myaccount_visual',
            'yangsheep_wployalty_enable',
        );

        $result = array();
        foreach ( $checkboxes as $key ) {
            $result[ $key ] = self::get( $key, 'no' );
        }
        return $result;
    }

    /**
     * 取得 Table Maker 實例
     *
     * @return YSSettingsTableMaker
     */
    public static function get_table_maker(): YSSettingsTableMaker {
        return self::instance()->table_maker;
    }

    /**
     * 取得 Repository 實例
     *
     * @return YSSettingsRepository
     */
    public static function get_repository(): YSSettingsRepository {
        return self::instance()->repository;
    }

    /**
     * 取得預設值
     *
     * @param string $key 設定 key
     * @return mixed
     */
    public static function get_default( string $key ) {
        return self::DEFAULT_VALUES[ $key ] ?? false;
    }
}
