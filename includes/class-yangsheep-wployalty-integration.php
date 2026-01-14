<?php
/**
 * WPLoyalty (WooCommerce Loyalty Rewards) 整合類別
 *
 * 整合 WPLoyalty 外掛的購物金顯示，提供更現代化的結帳頁面體驗
 *
 * @package YANGSHEEP_Checkout_Optimizer
 * @since 1.3.33
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * WPLoyalty 整合類別
 */
class YANGSHEEP_WPLoyalty_Integration {

    /**
     * 單例實例
     *
     * @var YANGSHEEP_WPLoyalty_Integration
     */
    private static $instance = null;

    /**
     * 設定選項名稱
     *
     * @var string
     */
    const OPTION_NAME = 'yangsheep_wployalty_settings';

    /**
     * 取得單例實例
     *
     * @return YANGSHEEP_WPLoyalty_Integration
     */
    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * 建構函式
     */
    private function __construct() {
        // 只在結帳頁面且啟用整合時載入
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
    }

    /**
     * 檢查是否啟用整合
     *
     * @return bool
     */
    public static function is_enabled() {
        // 優先使用新的選項名稱（與 Settings API 整合）
        $enabled = get_option( 'yangsheep_wployalty_enable', 'no' );
        if ( $enabled === 'yes' ) {
            return true;
        }

        // 向下相容：檢查舊的設定格式
        $settings = get_option( self::OPTION_NAME, array() );
        return ! empty( $settings['enable_checkout_integration'] );
    }

    /**
     * 檢查 WPLoyalty 外掛是否啟用
     *
     * @return bool
     */
    public static function is_wployalty_active() {
        // 檢查各種可能的 WPLoyalty 類別和常數
        if ( class_exists( 'Starter' )
            || class_exists( 'WLR\App\Starter' )
            || class_exists( 'Wlr\App\Starter' )
            || defined( 'WLR_STARTER' )
            || defined( 'WLR_PLUGIN_FILE' )
            || defined( 'WLR_PLUGIN_VERSION' ) ) {
            return true;
        }

        // 後台檢查：使用 is_plugin_active
        if ( is_admin() && function_exists( 'is_plugin_active' ) ) {
            if ( is_plugin_active( 'wp-loyalty-rules/starter.php' )
                || is_plugin_active( 'wployalty/starter.php' )
                || is_plugin_active( 'wp-loyalty-rules-starter/starter.php' ) ) {
                return true;
            }
        }

        return false;
    }

    /**
     * 取得設定值
     *
     * @param string $key     設定鍵名
     * @param mixed  $default 預設值
     * @return mixed
     */
    public static function get_setting( $key, $default = '' ) {
        $settings = get_option( self::OPTION_NAME, array() );
        return isset( $settings[ $key ] ) ? $settings[ $key ] : $default;
    }

    /**
     * 載入前端腳本
     */
    public function enqueue_scripts() {
        if ( ! is_checkout() || ! self::is_enabled() || ! self::is_wployalty_active() ) {
            return;
        }

        // 載入整合 JS
        wp_enqueue_script(
            'yangsheep-wployalty-integration',
            YANGSHEEP_CHECKOUT_URL . 'assets/js/yangsheep-wployalty.js',
            array( 'jquery' ),
            YANGSHEEP_CHECKOUT_VERSION,
            true
        );

        // 傳遞設定到前端
        wp_localize_script(
            'yangsheep-wployalty-integration',
            'yangsheep_wployalty',
            array(
                'enabled'           => self::is_enabled(),
                'points_label'      => self::get_setting( 'points_label', '購物金' ),
                'button_text'       => self::get_setting( 'button_text', '點此兌換折扣' ),
                'available_text'    => self::get_setting( 'available_text', '目前有 {points} {label} 可用' ),
                'i18n'              => array(
                    'points'        => __( '購物金', 'yangsheep-checkout-optimization' ),
                    'redeem'        => __( '點此兌換折扣', 'yangsheep-checkout-optimization' ),
                    'available'     => __( '目前有', 'yangsheep-checkout-optimization' ),
                    'can_use'       => __( '可用', 'yangsheep-checkout-optimization' ),
                    'hint'          => __( '按下兌換按鈕，於彈出視窗中兌換', 'yangsheep-checkout-optimization' ),
                ),
            )
        );

        // 載入整合 CSS
        wp_enqueue_style(
            'yangsheep-wployalty-integration',
            YANGSHEEP_CHECKOUT_URL . 'assets/css/yangsheep-wployalty.css',
            array(),
            YANGSHEEP_CHECKOUT_VERSION
        );
    }

    /**
     * 儲存設定
     *
     * @param array $settings 設定陣列
     */
    public static function save_settings( $settings ) {
        update_option( self::OPTION_NAME, $settings );
    }

    /**
     * 取得預設設定
     *
     * @return array
     */
    public static function get_default_settings() {
        return array(
            'enable_checkout_integration' => false,
            'points_label'                => '購物金',
            'button_text'                 => '點此兌換折扣',
            'available_text'              => '目前有 {points} {label} 可用',
        );
    }
}

// 初始化
YANGSHEEP_WPLoyalty_Integration::get_instance();
