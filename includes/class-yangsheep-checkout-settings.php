<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use YangSheep\CheckoutOptimizer\Settings\YSSettingsManager;
use YangSheep\CheckoutOptimizer\Settings\YSSettingsMigrator;

class YANGSHEEP_Checkout_Settings {

    private static $instance = null;

    // 莫蘭迪淡藍色系預設值（藍、白、灰，無綠色）
    private static $default_colors = array(
        // 結帳按鈕 - 主色淡藍
        'yangsheep_checkout_button_bg_color'        => '#8fa8b8',
        'yangsheep_checkout_button_text_color'      => '#ffffff',
        'yangsheep_checkout_button_hover_bg'        => '#7a95a6',
        'yangsheep_checkout_button_hover_text'      => '#ffffff',
        // 區塊樣式
        'yangsheep_checkout_section_border_color'   => '#c5d1d8',
        'yangsheep_checkout_section_bg_color'       => '#f5f8fa',
        'yangsheep_checkout_block_border_radius'    => '12px',
        // 表單欄位
        'yangsheep_checkout_form_field_bg_color'    => '#ffffff',
        'yangsheep_checkout_form_field_border_color'=> '#c5d1d8',
        // 連結 - 主色淡藍
        'yangsheep_checkout_link_color'             => '#6b8a9a',
        // 登入區塊
        'yangsheep_checkout_login_text_color'       => '#5a7080',
        'yangsheep_checkout_login_text_bg'          => '#e8eff3',
        'yangsheep_checkout_login_text_padding'     => '20px',
        // 付款區塊 - 淡藍背景（移除綠色）
        'yangsheep_checkout_payment_bg_color'       => '#e8eff5',
        // 付款方式卡片 - 主色淡藍
        'yangsheep_payment_method_bg'               => '#ffffff',
        'yangsheep_payment_method_bg_active'        => '#e8eff5',
        'yangsheep_payment_method_border'           => '#c5d1d8',
        'yangsheep_payment_method_border_active'    => '#8fa8b8',
        'yangsheep_payment_method_desc_bg'          => '#f5f8fa',
        // 商品明細
        'yangsheep_checkout_order_items_bg_color'   => '#f5f8fa',
        // 折扣代碼
        'yangsheep_checkout_coupon_block_bg_color'  => '#f5f8fa',
        // 訂單總覽
        'yangsheep_checkout_order_review_bg_color'  => '#f5f8fa',
        // 物流卡片 - 主色淡藍
        'yangsheep_shipping_card_radio_color'       => '#8fa8b8',
        'yangsheep_shipping_card_border_active'     => '#8fa8b8',
        'yangsheep_shipping_card_bg_color'          => '#ffffff',
        'yangsheep_shipping_card_bg_active'         => '#e8eff5',
        // 側邊欄
        'yangsheep_sidebar_bg_color'                => '#ffffff',
        // 我的帳號 - 主色淡藍
        'yangsheep_myaccount_button_bg_color'       => '#8fa8b8',
        'yangsheep_myaccount_button_text_color'     => '#ffffff',
        'yangsheep_nav_button_hover_color'          => '#7a95a6',
        'yangsheep_nav_button_active_color'         => '#8fa8b8',
        'yangsheep_myaccount_link_color'            => '#6b8a9a',
        'yangsheep_myaccount_link_hover_color'      => '#4a6a7a',
        // 訂單狀態標籤 - 統一藍灰色系
        'yangsheep_status_pending_bg'               => '#f0f4f7',
        'yangsheep_status_pending_text'             => '#7a8b95',
        'yangsheep_status_preparing_bg'             => '#e8eff5',
        'yangsheep_status_preparing_text'           => '#6b8a9a',
        'yangsheep_status_shipping_bg'              => '#fef6e8',
        'yangsheep_status_shipping_text'            => '#b8860b',
        'yangsheep_status_arrived_bg'               => '#f3e5f5',
        'yangsheep_status_arrived_text'             => '#7b1fa2',
        'yangsheep_status_completed_bg'             => '#e8eff5',
        'yangsheep_status_completed_text'           => '#6b8a9a',
    );

    public static function get_instance() {
        if ( is_null( self::$instance ) ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public static function get_default_colors() {
        return self::$default_colors;
    }

    private function __construct() {
        add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
        add_action( 'admin_init', array( $this, 'settings_init' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_scripts' ) );
        add_action( 'wp_ajax_yangsheep_reset_colors', array( $this, 'ajax_reset_colors' ) );
        add_action( 'wp_ajax_yangsheep_migrate_settings', array( $this, 'ajax_migrate_settings' ) );
        add_action( 'wp_ajax_yangsheep_cleanup_options', array( $this, 'ajax_cleanup_options' ) );
    }

    public function add_admin_menu() {
        add_menu_page(
            __( '結帳強化設定', 'yangsheep-checkout-optimization' ),
            __( '結帳強化',      'yangsheep-checkout-optimization' ),
            'manage_options',
            'yangsheep_checkout_optimization',
            array( $this, 'settings_page' ),
            'dashicons-cart',
            60
        );
    }

    public function enqueue_admin_scripts( $hook ) {
        if ( 'toplevel_page_yangsheep_checkout_optimization' !== $hook ) {
            return;
        }
        wp_enqueue_style( 'wp-color-picker' );
        wp_enqueue_script( 'wp-color-picker' );
    }

    // Checkbox 選項清單 (用於處理未勾選情況)
    private $checkbox_options = array(
        'yangsheep_enable_order_enhancement',
        'yangsheep_enable_manual_tracking',
        'yangsheep_checkout_close_lname',
        'yangsheep_checkout_tw_fields',
        'yangsheep_checkout_order_note',
        'yangsheep_myaccount_visual',
        'yangsheep_wployalty_enable',
    );

    public function settings_init() {
        // 註冊所有原有設定選項 (保持 Group 不變以相容)
        $options = array(
            // General / Logic
            'yangsheep_enable_order_enhancement',
            'yangsheep_enable_manual_tracking',

            // Checkout UI
            'yangsheep_checkout_login_welcome_text',
            'yangsheep_checkout_login_text_color',
            'yangsheep_checkout_login_text_bg',
            'yangsheep_checkout_login_text_padding',
            'yangsheep_checkout_payment_bg_color',
            'yangsheep_payment_method_bg',
            'yangsheep_payment_method_bg_active',
            'yangsheep_payment_method_border',
            'yangsheep_payment_method_border_active',
            'yangsheep_payment_method_desc_bg',
            'yangsheep_checkout_button_bg_color',
            'yangsheep_checkout_button_text_color',
            'yangsheep_checkout_button_hover_bg',
            'yangsheep_checkout_button_hover_text',
            'yangsheep_checkout_section_border_color',
            'yangsheep_checkout_section_bg_color',
            'yangsheep_checkout_form_field_bg_color',
            'yangsheep_checkout_form_field_border_color',
            'yangsheep_checkout_link_color',
            'yangsheep_checkout_coupon_block_bg_color',
            'yangsheep_checkout_order_review_bg_color',
            'yangsheep_checkout_order_items_bg_color',
            'yangsheep_checkout_block_border_radius',
            'yangsheep_shipping_card_radio_color',
            'yangsheep_shipping_card_border_active',
            'yangsheep_shipping_card_bg_color',
            'yangsheep_shipping_card_bg_active',
            'yangsheep_sidebar_bg_color',
            // 超商區域配色已移至 PayNow 物流外掛

            // Checkout Fields
            'yangsheep_checkout_close_lname',
            'yangsheep_checkout_tw_fields',
            'yangsheep_checkout_order_note',

            // My Account UI
            'yangsheep_myaccount_visual',
            'yangsheep_myaccount_button_bg_color',
            'yangsheep_myaccount_button_text_color',
            'yangsheep_myaccount_link_color',
            'yangsheep_myaccount_link_hover_color',
            'yangsheep_nav_button_hover_color',
            'yangsheep_nav_button_active_color',

            // CVS Shipping Methods
            'yangsheep_cvs_shipping_methods',

            // Order Status Badge Colors
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

            // WPLoyalty Integration
            'yangsheep_wployalty_enable'
        );

        foreach ( $options as $opt ) {
            if ( in_array( $opt, $this->checkbox_options ) ) {
                register_setting( 'yangsheep_checkout_optimization_group', $opt, array(
                    'sanitize_callback' => array( $this, 'sanitize_checkbox' ),
                ) );
            } elseif ( $opt === 'yangsheep_cvs_shipping_methods' ) {
                register_setting( 'yangsheep_checkout_optimization_group', $opt, array(
                    'sanitize_callback' => array( $this, 'sanitize_cvs_shipping_methods' ),
                ) );
            } else {
                register_setting( 'yangsheep_checkout_optimization_group', $opt );
            }
        }

        add_action( 'pre_update_option', array( $this, 'handle_checkbox_save' ), 10, 3 );

        // --- Tab 1: 一般設定 (邏輯功能) ---
        add_settings_section( 'ys_general_logic_section', '', array( $this, 'general_section_header' ), 'yangsheep_tab_general' );

        $this->add_checkbox_field( 'yangsheep_enable_order_enhancement', __( '啟用訂單配送狀態強化', 'yangsheep-checkout-optimization' ), __( '支援 YS PayNow、PayUni 物流外掛，於前台顯示詳細物流進度與圓角卡片樣式。', 'yangsheep-checkout-optimization' ), 'yangsheep_tab_general', 'ys_general_logic_section' );
        $this->add_checkbox_field( 'yangsheep_enable_manual_tracking', __( '啟用手動配送單號輸入', 'yangsheep-checkout-optimization' ), __( '若訂單非串接物流，可於後台手動輸入物流商與單號並顯示狀態。', 'yangsheep-checkout-optimization' ), 'yangsheep_tab_general', 'ys_general_logic_section' );


        // --- Tab 2: 結帳頁面樣式 ---

        // 欄位設定
        add_settings_section( 'ys_checkout_fields_section', '', array( $this, 'checkout_fields_section_header' ), 'yangsheep_tab_checkout' );
        add_settings_field( 'ys_checkout_shipping_check', __( 'WooCommerce 運送設定', 'yangsheep-checkout-optimization' ), array( $this, 'shipping_setting_check_callback' ), 'yangsheep_tab_checkout', 'ys_checkout_fields_section' );
        $this->add_checkbox_field( 'yangsheep_checkout_close_lname', __( '關閉 Last Name', 'yangsheep-checkout-optimization' ), __( '啟用後只顯示「姓名」欄位（使用 First Name）', 'yangsheep-checkout-optimization' ), 'yangsheep_tab_checkout', 'ys_checkout_fields_section' );
        $this->add_checkbox_field( 'yangsheep_checkout_tw_fields', __( '台灣化欄位', 'yangsheep-checkout-optimization' ), __( '帳單只保留：姓名、電話、電子郵件；運送欄位調整為台灣格式', 'yangsheep-checkout-optimization' ), 'yangsheep_tab_checkout', 'ys_checkout_fields_section' );
        $this->add_checkbox_field( 'yangsheep_checkout_order_note', __( '訂單備註開關', 'yangsheep-checkout-optimization' ), __( '用戶勾選才顯示備註欄位', 'yangsheep-checkout-optimization' ), 'yangsheep_tab_checkout', 'ys_checkout_fields_section' );

        // 超取物流方式設定
        add_settings_section( 'ys_cvs_shipping_section', '', array( $this, 'cvs_shipping_section_header' ), 'yangsheep_tab_checkout' );
        add_settings_field( 'yangsheep_cvs_shipping_methods', __( '超取物流方式', 'yangsheep-checkout-optimization' ), array( $this, 'cvs_shipping_methods_callback' ), 'yangsheep_tab_checkout', 'ys_cvs_shipping_section' );

        // 登入區塊
        add_settings_section( 'ys_checkout_login_section', '', array( $this, 'login_section_header' ), 'yangsheep_tab_checkout' );
        add_settings_field( 'login_welcome_text', __( '登入歡迎文字', 'yangsheep-checkout-optimization' ), array( $this, 'login_welcome_text_callback' ), 'yangsheep_tab_checkout', 'ys_checkout_login_section' );
        $this->add_color_field( 'yangsheep_checkout_login_text_color', __( '文字顏色', 'yangsheep-checkout-optimization' ), self::$default_colors['yangsheep_checkout_login_text_color'], 'yangsheep_tab_checkout', 'ys_checkout_login_section' );
        $this->add_color_field( 'yangsheep_checkout_login_text_bg', __( '背景顏色', 'yangsheep-checkout-optimization' ), self::$default_colors['yangsheep_checkout_login_text_bg'], 'yangsheep_tab_checkout', 'ys_checkout_login_section' );
        $this->add_text_field( 'yangsheep_checkout_login_text_padding', __( 'Padding', 'yangsheep-checkout-optimization' ), '例如：20px', 'yangsheep_tab_checkout', 'ys_checkout_login_section' );

        // 付款區塊（新增）
        add_settings_section( 'ys_checkout_payment_section', '', array( $this, 'payment_section_header' ), 'yangsheep_tab_checkout' );
        $this->add_color_field( 'yangsheep_checkout_payment_bg_color', __( '付款區塊背景色', 'yangsheep-checkout-optimization' ), self::$default_colors['yangsheep_checkout_payment_bg_color'], 'yangsheep_tab_checkout', 'ys_checkout_payment_section' );
        $this->add_color_field( 'yangsheep_payment_method_bg', __( '付款方式背景', 'yangsheep-checkout-optimization' ), self::$default_colors['yangsheep_payment_method_bg'], 'yangsheep_tab_checkout', 'ys_checkout_payment_section' );
        $this->add_color_field( 'yangsheep_payment_method_bg_active', __( '選中時背景', 'yangsheep-checkout-optimization' ), self::$default_colors['yangsheep_payment_method_bg_active'], 'yangsheep_tab_checkout', 'ys_checkout_payment_section' );
        $this->add_color_field( 'yangsheep_payment_method_border', __( '付款方式邊框', 'yangsheep-checkout-optimization' ), self::$default_colors['yangsheep_payment_method_border'], 'yangsheep_tab_checkout', 'ys_checkout_payment_section' );
        $this->add_color_field( 'yangsheep_payment_method_border_active', __( '選中時邊框', 'yangsheep-checkout-optimization' ), self::$default_colors['yangsheep_payment_method_border_active'], 'yangsheep_tab_checkout', 'ys_checkout_payment_section' );
        $this->add_color_field( 'yangsheep_payment_method_desc_bg', __( '描述區域背景', 'yangsheep-checkout-optimization' ), self::$default_colors['yangsheep_payment_method_desc_bg'], 'yangsheep_tab_checkout', 'ys_checkout_payment_section' );

        // 按鈕樣式
        add_settings_section( 'ys_checkout_button_section', '', array( $this, 'button_section_header' ), 'yangsheep_tab_checkout' );
        $this->add_color_field( 'yangsheep_checkout_button_bg_color', __( '背景顏色', 'yangsheep-checkout-optimization' ), self::$default_colors['yangsheep_checkout_button_bg_color'], 'yangsheep_tab_checkout', 'ys_checkout_button_section' );
        $this->add_color_field( 'yangsheep_checkout_button_text_color', __( '文字顏色', 'yangsheep-checkout-optimization' ), self::$default_colors['yangsheep_checkout_button_text_color'], 'yangsheep_tab_checkout', 'ys_checkout_button_section' );
        $this->add_color_field( 'yangsheep_checkout_button_hover_bg', __( 'Hover 背景', 'yangsheep-checkout-optimization' ), self::$default_colors['yangsheep_checkout_button_hover_bg'], 'yangsheep_tab_checkout', 'ys_checkout_button_section' );
        $this->add_color_field( 'yangsheep_checkout_button_hover_text', __( 'Hover 文字', 'yangsheep-checkout-optimization' ), self::$default_colors['yangsheep_checkout_button_hover_text'], 'yangsheep_tab_checkout', 'ys_checkout_button_section' );

        // 區塊樣式
        add_settings_section( 'ys_checkout_block_section', '', array( $this, 'block_section_header' ), 'yangsheep_tab_checkout' );
        $this->add_color_field( 'yangsheep_checkout_section_border_color', __( '邊框顏色', 'yangsheep-checkout-optimization' ), self::$default_colors['yangsheep_checkout_section_border_color'], 'yangsheep_tab_checkout', 'ys_checkout_block_section' );
        $this->add_color_field( 'yangsheep_checkout_section_bg_color', __( '背景顏色', 'yangsheep-checkout-optimization' ), self::$default_colors['yangsheep_checkout_section_bg_color'], 'yangsheep_tab_checkout', 'ys_checkout_block_section' );
        $this->add_text_field( 'yangsheep_checkout_block_border_radius', __( '圓角大小', 'yangsheep-checkout-optimization' ), '例如：12px', 'yangsheep_tab_checkout', 'ys_checkout_block_section' );
        $this->add_color_field( 'yangsheep_checkout_order_items_bg_color', __( '商品明細背景', 'yangsheep-checkout-optimization' ), self::$default_colors['yangsheep_checkout_order_items_bg_color'], 'yangsheep_tab_checkout', 'ys_checkout_block_section' );
        $this->add_color_field( 'yangsheep_checkout_coupon_block_bg_color', __( '折扣區塊背景', 'yangsheep-checkout-optimization' ), self::$default_colors['yangsheep_checkout_coupon_block_bg_color'], 'yangsheep_tab_checkout', 'ys_checkout_block_section' );
        $this->add_color_field( 'yangsheep_checkout_order_review_bg_color', __( '訂單總覽背景', 'yangsheep-checkout-optimization' ), self::$default_colors['yangsheep_checkout_order_review_bg_color'], 'yangsheep_tab_checkout', 'ys_checkout_block_section' );
        $this->add_color_field( 'yangsheep_sidebar_bg_color', __( '側邊欄背景', 'yangsheep-checkout-optimization' ), self::$default_colors['yangsheep_sidebar_bg_color'], 'yangsheep_tab_checkout', 'ys_checkout_block_section' );

        // 表單欄位
        add_settings_section( 'ys_checkout_form_section', '', array( $this, 'form_section_header' ), 'yangsheep_tab_checkout' );
        $this->add_color_field( 'yangsheep_checkout_form_field_bg_color', __( '欄位背景', 'yangsheep-checkout-optimization' ), self::$default_colors['yangsheep_checkout_form_field_bg_color'], 'yangsheep_tab_checkout', 'ys_checkout_form_section' );
        $this->add_color_field( 'yangsheep_checkout_form_field_border_color', __( '欄位邊框', 'yangsheep-checkout-optimization' ), self::$default_colors['yangsheep_checkout_form_field_border_color'], 'yangsheep_tab_checkout', 'ys_checkout_form_section' );
        $this->add_color_field( 'yangsheep_checkout_link_color', __( '連結顏色', 'yangsheep-checkout-optimization' ), self::$default_colors['yangsheep_checkout_link_color'], 'yangsheep_tab_checkout', 'ys_checkout_form_section' );

        // 物流卡片（新增背景色設定）
        add_settings_section( 'ys_checkout_shipping_section', '', array( $this, 'shipping_section_header' ), 'yangsheep_tab_checkout' );
        $this->add_color_field( 'yangsheep_shipping_card_bg_color', __( '卡片背景色', 'yangsheep-checkout-optimization' ), self::$default_colors['yangsheep_shipping_card_bg_color'], 'yangsheep_tab_checkout', 'ys_checkout_shipping_section' );
        $this->add_color_field( 'yangsheep_shipping_card_bg_active', __( '選中時背景色', 'yangsheep-checkout-optimization' ), self::$default_colors['yangsheep_shipping_card_bg_active'], 'yangsheep_tab_checkout', 'ys_checkout_shipping_section' );
        $this->add_color_field( 'yangsheep_shipping_card_radio_color', __( '選中標示色', 'yangsheep-checkout-optimization' ), self::$default_colors['yangsheep_shipping_card_radio_color'], 'yangsheep_tab_checkout', 'ys_checkout_shipping_section' );
        $this->add_color_field( 'yangsheep_shipping_card_border_active', __( '選中邊框色', 'yangsheep-checkout-optimization' ), self::$default_colors['yangsheep_shipping_card_border_active'], 'yangsheep_tab_checkout', 'ys_checkout_shipping_section' );

        // 超商區域設定已移至 PayNow 物流外掛


        // --- Tab 3: 我的帳號樣式 ---
        add_settings_section( 'ys_account_style_section', '', array( $this, 'account_section_header' ), 'yangsheep_tab_account' );

        $this->add_checkbox_field( 'yangsheep_myaccount_visual', __( '啟用我的帳號頁面強化設計 (BETA)', 'yangsheep-checkout-optimization' ), __( '套用自訂色彩與強化樣式至「我的帳號」頁面', 'yangsheep-checkout-optimization' ), 'yangsheep_tab_account', 'ys_account_style_section' );
        $this->add_color_field( 'yangsheep_myaccount_button_bg_color', __( '導航按鈕背景', 'yangsheep-checkout-optimization' ), self::$default_colors['yangsheep_myaccount_button_bg_color'], 'yangsheep_tab_account', 'ys_account_style_section' );
        $this->add_color_field( 'yangsheep_myaccount_button_text_color', __( '導航按鈕文字', 'yangsheep-checkout-optimization' ), self::$default_colors['yangsheep_myaccount_button_text_color'], 'yangsheep_tab_account', 'ys_account_style_section' );
        $this->add_color_field( 'yangsheep_nav_button_hover_color', __( '導航 Hover 背景', 'yangsheep-checkout-optimization' ), self::$default_colors['yangsheep_nav_button_hover_color'], 'yangsheep_tab_account', 'ys_account_style_section' );
        $this->add_color_field( 'yangsheep_nav_button_active_color', __( '導航 Active 背景', 'yangsheep-checkout-optimization' ), self::$default_colors['yangsheep_nav_button_active_color'], 'yangsheep_tab_account', 'ys_account_style_section' );
        $this->add_color_field( 'yangsheep_myaccount_link_color', __( '連結顏色', 'yangsheep-checkout-optimization' ), self::$default_colors['yangsheep_myaccount_link_color'], 'yangsheep_tab_account', 'ys_account_style_section' );
        $this->add_color_field( 'yangsheep_myaccount_link_hover_color', __( '連結 Hover', 'yangsheep-checkout-optimization' ), self::$default_colors['yangsheep_myaccount_link_hover_color'], 'yangsheep_tab_account', 'ys_account_style_section' );


        // --- Tab 4: 訂單狀態強化 ---
        add_settings_section( 'ys_order_status_colors_section', '', array( $this, 'status_section_header' ), 'yangsheep_tab_order_status' );
        $this->add_color_field( 'yangsheep_status_pending_bg', __( '待處理 - 背景', 'yangsheep-checkout-optimization' ), self::$default_colors['yangsheep_status_pending_bg'], 'yangsheep_tab_order_status', 'ys_order_status_colors_section' );
        $this->add_color_field( 'yangsheep_status_pending_text', __( '待處理 - 文字', 'yangsheep-checkout-optimization' ), self::$default_colors['yangsheep_status_pending_text'], 'yangsheep_tab_order_status', 'ys_order_status_colors_section' );
        $this->add_color_field( 'yangsheep_status_preparing_bg', __( '備貨中 - 背景', 'yangsheep-checkout-optimization' ), self::$default_colors['yangsheep_status_preparing_bg'], 'yangsheep_tab_order_status', 'ys_order_status_colors_section' );
        $this->add_color_field( 'yangsheep_status_preparing_text', __( '備貨中 - 文字', 'yangsheep-checkout-optimization' ), self::$default_colors['yangsheep_status_preparing_text'], 'yangsheep_tab_order_status', 'ys_order_status_colors_section' );
        $this->add_color_field( 'yangsheep_status_shipping_bg', __( '運送中 - 背景', 'yangsheep-checkout-optimization' ), self::$default_colors['yangsheep_status_shipping_bg'], 'yangsheep_tab_order_status', 'ys_order_status_colors_section' );
        $this->add_color_field( 'yangsheep_status_shipping_text', __( '運送中 - 文字', 'yangsheep-checkout-optimization' ), self::$default_colors['yangsheep_status_shipping_text'], 'yangsheep_tab_order_status', 'ys_order_status_colors_section' );
        $this->add_color_field( 'yangsheep_status_arrived_bg', __( '已到店 - 背景', 'yangsheep-checkout-optimization' ), self::$default_colors['yangsheep_status_arrived_bg'], 'yangsheep_tab_order_status', 'ys_order_status_colors_section' );
        $this->add_color_field( 'yangsheep_status_arrived_text', __( '已到店 - 文字', 'yangsheep-checkout-optimization' ), self::$default_colors['yangsheep_status_arrived_text'], 'yangsheep_tab_order_status', 'ys_order_status_colors_section' );
        $this->add_color_field( 'yangsheep_status_completed_bg', __( '已完成 - 背景', 'yangsheep-checkout-optimization' ), self::$default_colors['yangsheep_status_completed_bg'], 'yangsheep_tab_order_status', 'ys_order_status_colors_section' );
        $this->add_color_field( 'yangsheep_status_completed_text', __( '已完成 - 文字', 'yangsheep-checkout-optimization' ), self::$default_colors['yangsheep_status_completed_text'], 'yangsheep_tab_order_status', 'ys_order_status_colors_section' );

        // --- Tab 5: 購物金整合 ---
        add_settings_section( 'ys_loyalty_section', '', array( $this, 'loyalty_section_header' ), 'yangsheep_tab_loyalty' );
        add_settings_field( 'yangsheep_wployalty_enable', __( '啟用結帳頁面整合', 'yangsheep-checkout-optimization' ), array( $this, 'loyalty_enable_callback' ), 'yangsheep_tab_loyalty', 'ys_loyalty_section' );
        add_settings_field( 'yangsheep_wployalty_info', '', array( $this, 'loyalty_info_callback' ), 'yangsheep_tab_loyalty', 'ys_loyalty_section' );
    }

    // Section Headers
    public function general_section_header() {
        echo '<div class="ys-section-card"><h3 class="ys-section-title"><span class="dashicons dashicons-admin-settings"></span> 功能設定</h3>';
    }
    public function checkout_fields_section_header() {
        echo '<div class="ys-section-card"><h3 class="ys-section-title"><span class="dashicons dashicons-forms"></span> 結帳欄位設置</h3>';
    }
    public function login_section_header() {
        echo '</div><div class="ys-section-card"><h3 class="ys-section-title"><span class="dashicons dashicons-admin-users"></span> 登入區塊</h3>';
    }
    public function payment_section_header() {
        echo '</div><div class="ys-section-card"><h3 class="ys-section-title"><span class="dashicons dashicons-money-alt"></span> 付款區塊</h3>';
    }
    public function button_section_header() {
        echo '</div><div class="ys-section-card"><h3 class="ys-section-title"><span class="dashicons dashicons-button"></span> 按鈕樣式</h3>';
    }
    public function block_section_header() {
        echo '</div><div class="ys-section-card"><h3 class="ys-section-title"><span class="dashicons dashicons-layout"></span> 區塊樣式</h3>';
    }
    public function form_section_header() {
        echo '</div><div class="ys-section-card"><h3 class="ys-section-title"><span class="dashicons dashicons-edit"></span> 表單欄位</h3>';
    }
    public function shipping_section_header() {
        echo '</div><div class="ys-section-card"><h3 class="ys-section-title"><span class="dashicons dashicons-car"></span> 物流卡片</h3>';
    }
    public function cvs_shipping_section_header() {
        echo '</div><div class="ys-section-card"><h3 class="ys-section-title"><span class="dashicons dashicons-store"></span> 超取物流方式設定</h3>';
        echo '<p class="ys-section-desc" style="margin:0 0 15px 0;color:#666;">選擇哪些物流方式為「超商取貨」，選中的物流方式將隱藏地址欄位。若未勾選任何項目，系統將使用自動偵測。</p>';
    }
    public function account_section_header() {
        echo '<div class="ys-section-card"><h3 class="ys-section-title"><span class="dashicons dashicons-id-alt"></span> 我的帳號頁面</h3>';
    }
    public function status_section_header() {
        echo '<div class="ys-section-card"><h3 class="ys-section-title"><span class="dashicons dashicons-tag"></span> 訂單狀態標籤顏色</h3>';
    }

    // Helper: Add Color Field
    private function add_color_field( $opt_name, $label, $default, $page, $section ) {
        add_settings_field(
            $opt_name,
            $label,
            function() use ( $opt_name, $default ) {
                $val = get_option( $opt_name, $default );
                echo '<input type="text" name="'.esc_attr($opt_name).'" value="'.esc_attr($val).'" class="yangsheep-color-picker" data-default-color="'.esc_attr($default).'" />';
            },
            $page,
            $section
        );
    }

    // Helper: Add Text Field
    private function add_text_field( $opt_name, $label, $placeholder, $page, $section ) {
        add_settings_field(
            $opt_name,
            $label,
            function() use ( $opt_name, $placeholder ) {
                $val = get_option( $opt_name, '' );
                echo '<input type="text" name="'.esc_attr($opt_name).'" value="'.esc_attr($val).'" placeholder="'.esc_attr($placeholder).'" class="regular-text" />';
            },
            $page,
            $section
        );
    }

    // Helper: Add Checkbox Field
    private function add_checkbox_field( $opt_name, $label, $desc, $page, $section ) {
        add_settings_field(
            $opt_name,
            $label,
            function() use ( $opt_name, $desc ) {
                $val = get_option( $opt_name, 'no' );
                echo '<input type="hidden" name="'.esc_attr($opt_name).'_submitted" value="1" />';
                echo '<label class="ys-toggle-switch">';
                echo '<input type="checkbox" name="'.esc_attr($opt_name).'" value="yes" '.checked( $val, 'yes', false ).' />';
                echo '<span class="ys-toggle-slider"></span>';
                echo '</label>';
                echo '<span class="ys-toggle-desc">' . wp_kses_post( $desc ) . '</span>';
            },
            $page,
            $section
        );
    }

    public function sanitize_checkbox( $value ) {
        return ( $value === 'yes' ) ? 'yes' : 'no';
    }

    public function sanitize_cvs_shipping_methods( $value ) {
        if ( ! is_array( $value ) ) {
            return array();
        }
        return array_map( 'sanitize_text_field', $value );
    }

    public function handle_checkbox_save( $value, $option, $old_value ) {
        if ( in_array( $option, $this->checkbox_options ) ) {
            if ( isset( $_POST[ $option . '_submitted' ] ) && $_POST[ $option . '_submitted' ] === '1' ) {
                if ( ! isset( $_POST[ $option ] ) || $_POST[ $option ] !== 'yes' ) {
                    return 'no';
                }
            }
        }
        return $value;
    }

    public function shipping_setting_check_callback() {
        $shipping_dest = get_option( 'woocommerce_ship_to_destination' );
        $url = admin_url( 'admin.php?page=wc-settings&tab=shipping&section=options' );

        if ( $shipping_dest === 'shipping' ) {
            echo '<div class="ys-status-badge ys-status-success">';
            echo '<span class="dashicons dashicons-yes-alt"></span> ' . __( '已正確設定為「預設的客戶運送地址」', 'yangsheep-checkout-optimization' );
            echo '</div>';
        } else {
            echo '<div class="ys-status-badge ys-status-warning">';
            echo '<span class="dashicons dashicons-warning"></span> ' . __( '建議設定為「預設的客戶運送地址」', 'yangsheep-checkout-optimization' );
            echo '</div>';
            echo '<a href="' . esc_url( $url ) . '" class="button button-secondary" target="_blank" style="margin-top:8px;">' . __( '前往設置', 'yangsheep-checkout-optimization' ) . '</a>';
        }
    }

    public function login_welcome_text_callback() {
        $val = get_option( 'yangsheep_checkout_login_welcome_text', '' );
        echo '<textarea name="yangsheep_checkout_login_welcome_text" class="large-text" rows="3" placeholder="輸入歡迎文字...">' . esc_textarea( $val ) . '</textarea>';
    }

    /**
     * 超取物流方式多選 callback
     */
    public function cvs_shipping_methods_callback() {
        $saved_methods = get_option( 'yangsheep_cvs_shipping_methods', array() );
        if ( ! is_array( $saved_methods ) ) {
            $saved_methods = array();
        }

        // 取得所有運送區域及其物流方式
        $all_methods = $this->get_all_shipping_methods_with_zones();

        if ( empty( $all_methods ) ) {
            echo '<p style="color:#666;">' . __( '尚未設定任何物流方式', 'yangsheep-checkout-optimization' ) . '</p>';
            return;
        }

        echo '<div class="ys-cvs-methods-list" style="max-height:300px;overflow-y:auto;border:1px solid #c5d1d8;border-radius:6px;padding:10px;background:#fff;">';

        foreach ( $all_methods as $zone_name => $methods ) {
            echo '<div class="ys-zone-group" style="margin-bottom:15px;">';
            echo '<div class="ys-zone-title" style="font-weight:600;color:#5a7080;margin-bottom:8px;padding-bottom:5px;border-bottom:1px solid #eee;">' . esc_html( $zone_name ) . '</div>';

            foreach ( $methods as $method ) {
                $method_id = $method['rate_id'];
                $method_title = $method['title'];
                $checked = in_array( $method_id, $saved_methods ) ? 'checked' : '';

                echo '<label style="display:block;margin:6px 0;cursor:pointer;">';
                echo '<input type="checkbox" name="yangsheep_cvs_shipping_methods[]" value="' . esc_attr( $method_id ) . '" ' . $checked . ' style="margin-right:8px;" />';
                echo '<span>' . esc_html( $method_title ) . '</span>';
                echo '<code style="margin-left:8px;font-size:11px;color:#888;background:#f0f0f1;padding:2px 6px;border-radius:3px;">' . esc_html( $method_id ) . '</code>';
                echo '</label>';
            }

            echo '</div>';
        }

        echo '</div>';
        echo '<p class="description" style="margin-top:10px;">' . __( '勾選的物流方式將被視為「超商取貨」，會隱藏地址相關欄位。', 'yangsheep-checkout-optimization' ) . '</p>';
    }

    /**
     * 取得所有物流方式（依運送區域分組）
     */
    private function get_all_shipping_methods_with_zones() {
        if ( ! class_exists( 'WC_Shipping_Zones' ) ) {
            return array();
        }

        $result = array();

        // 取得所有運送區域
        $zones = WC_Shipping_Zones::get_zones();

        foreach ( $zones as $zone_data ) {
            $zone = new WC_Shipping_Zone( $zone_data['zone_id'] );
            $zone_name = $zone->get_zone_name();
            $methods = $zone->get_shipping_methods( true ); // true = only enabled

            if ( empty( $methods ) ) {
                continue;
            }

            $result[ $zone_name ] = array();

            foreach ( $methods as $method ) {
                $result[ $zone_name ][] = array(
                    'rate_id' => $method->get_rate_id(),
                    'title'   => $method->get_title() ? $method->get_title() : $method->get_method_title(),
                );
            }
        }

        // 加入「其他地點」區域（zone_id = 0）
        $zone_rest = new WC_Shipping_Zone( 0 );
        $methods_rest = $zone_rest->get_shipping_methods( true );

        if ( ! empty( $methods_rest ) ) {
            $result[ __( '其他地點', 'yangsheep-checkout-optimization' ) ] = array();

            foreach ( $methods_rest as $method ) {
                $result[ __( '其他地點', 'yangsheep-checkout-optimization' ) ][] = array(
                    'rate_id' => $method->get_rate_id(),
                    'title'   => $method->get_title() ? $method->get_title() : $method->get_method_title(),
                );
            }
        }

        return $result;
    }

    // AJAX: Reset Colors
    public function ajax_reset_colors() {
        check_ajax_referer( 'yangsheep_reset_colors', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( '權限不足' );
        }

        foreach ( self::$default_colors as $opt => $default ) {
            YSSettingsManager::set( $opt, $default );
        }

        wp_send_json_success( '已恢復預設配色' );
    }

    // AJAX: 遷移設定到自訂資料表
    public function ajax_migrate_settings() {
        check_ajax_referer( 'yangsheep_migrate_settings', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( '權限不足' );
        }

        $migrator = YSSettingsMigrator::instance();
        $result = $migrator->migrate();

        if ( $result['success'] ) {
            wp_send_json_success( sprintf( '遷移完成！已遷移 %d 個設定。', $result['migrated'] ) );
        } else {
            wp_send_json_error( '遷移失敗：' . implode( ', ', $result['errors'] ) );
        }
    }

    // AJAX: 清理 wp_options 中的舊設定
    public function ajax_cleanup_options() {
        check_ajax_referer( 'yangsheep_cleanup_options', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( '權限不足' );
        }

        $migrator = YSSettingsMigrator::instance();
        $deleted = $migrator->cleanup_wp_options();

        wp_send_json_success( sprintf( '已清理 %d 個舊設定。', $deleted ) );
    }

    /**
     * 購物金整合 Section Header
     */
    public function loyalty_section_header() {
        $is_wployalty_active = YANGSHEEP_WPLoyalty_Integration::is_wployalty_active();
        echo '<div class="ys-section-card"><h3 class="ys-section-title"><span class="dashicons dashicons-star-filled"></span> WPLoyalty 整合</h3>';

        if ( ! $is_wployalty_active ) {
            echo '<div class="notice notice-warning inline" style="margin:15px 0;">';
            echo '<p><span class="dashicons dashicons-warning" style="color:#dba617;"></span>';
            echo '<strong>' . __( '未偵測到 WPLoyalty 外掛', 'yangsheep-checkout-optimization' ) . '</strong> - ';
            echo __( '請先安裝並啟用 WooCommerce Loyalty Rewards (WPLoyalty) 外掛才能使用此功能。', 'yangsheep-checkout-optimization' ) . '</p>';
            echo '</div>';
        }
    }

    /**
     * 購物金啟用選項 Callback
     */
    public function loyalty_enable_callback() {
        $val = get_option( 'yangsheep_wployalty_enable', 'no' );
        $is_wployalty_active = YANGSHEEP_WPLoyalty_Integration::is_wployalty_active();

        echo '<input type="hidden" name="yangsheep_wployalty_enable_submitted" value="1" />';
        echo '<label class="ys-toggle-switch">';
        echo '<input type="checkbox" name="yangsheep_wployalty_enable" value="yes" ' . checked( $val, 'yes', false );
        if ( ! $is_wployalty_active ) {
            echo ' disabled';
        }
        echo ' />';
        echo '<span class="ys-toggle-slider"></span>';
        echo '</label>';
        echo '<span class="ys-toggle-desc">' . __( '啟用後，系統將自動偵測並美化 WPLoyalty 的購物金兌換訊息', 'yangsheep-checkout-optimization' ) . '</span>';
    }

    /**
     * 購物金說明區塊 Callback
     */
    public function loyalty_info_callback() {
        ?>
        <div class="ys-notice-box" style="background:#fff8e5;border-left:4px solid #ffb900;padding:12px 15px;margin:10px 0 20px;border-radius:4px;">
            <p style="margin:0;"><strong><span class="dashicons dashicons-info" style="color:#ffb900;"></span> <?php _e( '重要設定說明', 'yangsheep-checkout-optimization' ); ?></strong></p>
            <p style="margin:10px 0 0;">
                <?php _e( '請至 WPLoyalty 後台設定 > 結帳頁面 > 「兌換訊息 Redeem Message」保持以下原始文字：', 'yangsheep-checkout-optimization' ); ?>
            </p>
            <code style="display:block;background:#f5f5f5;padding:10px;margin:10px 0;border-radius:4px;font-size:12px;">You have {wlr_redeem_cart_points} {wlr_points_label} earned choose your rewards {wlr_reward_link}</code>
            <p style="margin:0;color:#666;font-size:13px;">
                <?php _e( '系統將會自動偵測並顯示為中文，與結帳頁面視覺整合。', 'yangsheep-checkout-optimization' ); ?>
            </p>
        </div>

        <div class="ys-preview-section" style="background:#f9f9f9;border:1px solid #e0e0e0;border-radius:8px;padding:20px;margin:20px 0;">
            <h4 style="margin:0 0 15px;color:#5a7080;"><span class="dashicons dashicons-visibility"></span> <?php _e( '預覽效果', 'yangsheep-checkout-optimization' ); ?></h4>
            <div style="background:#fff;border:2px solid var(--theme-section-border-color, #c5d1d8);border-radius:8px;padding:20px;">
                <div style="display:flex;gap:20px;flex-wrap:wrap;">
                    <div style="flex:1;min-width:200px;">
                        <h5 style="margin:0 0 10px;font-size:16px;color:#3d4852;"><?php _e( '折扣代碼', 'yangsheep-checkout-optimization' ); ?></h5>
                        <p style="margin:0 0 10px;font-size:13px;color:#666;"><?php _e( '若您有折扣代碼，請直接輸入代碼折抵。', 'yangsheep-checkout-optimization' ); ?></p>
                        <input type="text" placeholder="Coupon" style="width:100%;padding:8px 12px;border:1px solid #c5d1d8;border-radius:4px;margin-bottom:10px;" disabled />
                        <button type="button" style="width:100%;padding:10px;background:var(--theme-button-background-initial-color, #8fa8b8);color:#fff;border:none;border-radius:20px;cursor:default;"><?php _e( '使用折扣代碼', 'yangsheep-checkout-optimization' ); ?></button>
                    </div>
                    <div style="flex:1;min-width:200px;border-left:1px solid #e0e0e0;padding-left:20px;">
                        <h5 style="margin:0 0 10px;font-size:16px;color:#3d4852;"><?php _e( '購物金', 'yangsheep-checkout-optimization' ); ?></h5>
                        <p style="margin:0 0 8px;font-size:13px;color:#666;"><?php _e( '目前有', 'yangsheep-checkout-optimization' ); ?> <strong style="color:var(--theme-button-background-initial-color, #8fa8b8);">500 Points</strong> <?php _e( '可用', 'yangsheep-checkout-optimization' ); ?></p>
                        <p style="margin:0 0 15px;font-size:12px;color:#718096;"><?php _e( '按下兌換按鈕，於彈出視窗中兌換', 'yangsheep-checkout-optimization' ); ?></p>
                        <button type="button" style="width:100%;padding:10px;background:var(--theme-button-background-initial-color, #8fa8b8);color:#fff;border:none;border-radius:20px;cursor:default;"><?php _e( '點此兌換折扣', 'yangsheep-checkout-optimization' ); ?></button>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * 渲染資料庫管理分頁
     */
    public function render_database_management_tab() {
        $migrator = YSSettingsMigrator::instance();
        $status = $migrator->get_status();
        ?>
        <div class="ys-section-card">
            <h3 class="ys-section-title"><span class="dashicons dashicons-database"></span> 設定儲存狀態</h3>

            <table class="widefat" style="margin-bottom:20px;">
                <tbody>
                    <tr>
                        <td style="width:200px;"><strong>自訂資料表</strong></td>
                        <td>
                            <?php if ( $status['table_exists'] ) : ?>
                                <span class="ys-status-badge ys-status-success"><span class="dashicons dashicons-yes"></span> 已建立</span>
                                <code style="margin-left:10px;"><?php echo esc_html( $status['table_name'] ); ?></code>
                            <?php else : ?>
                                <span class="ys-status-badge ys-status-warning"><span class="dashicons dashicons-warning"></span> 未建立</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <td><strong>Schema 版本</strong></td>
                        <td><?php echo esc_html( $status['installed_schema'] ); ?> / <?php echo esc_html( $status['schema_version'] ); ?></td>
                    </tr>
                    <tr>
                        <td><strong>自訂表中的設定數</strong></td>
                        <td><?php echo esc_html( $status['settings_in_table'] ); ?> 個</td>
                    </tr>
                    <tr>
                        <td><strong>wp_options 中的設定數</strong></td>
                        <td><?php echo esc_html( $status['settings_in_options'] ); ?> 個</td>
                    </tr>
                    <tr>
                        <td><strong>總設定項目</strong></td>
                        <td><?php echo esc_html( $status['total_setting_keys'] ); ?> 個</td>
                    </tr>
                </tbody>
            </table>

            <div class="ys-db-actions" style="display:flex;gap:10px;flex-wrap:wrap;">
                <?php if ( $status['migration_required'] || $status['settings_in_table'] < $status['settings_in_options'] ) : ?>
                    <button type="button" id="ys-migrate-settings" class="button button-primary">
                        <span class="dashicons dashicons-database-import"></span> 遷移設定到自訂資料表
                    </button>
                <?php endif; ?>

                <?php if ( $status['settings_in_options'] > 0 && $status['settings_in_table'] > 0 ) : ?>
                    <button type="button" id="ys-cleanup-options" class="button">
                        <span class="dashicons dashicons-trash"></span> 清理 wp_options 舊設定
                    </button>
                <?php endif; ?>
            </div>

            <div id="ys-db-message" style="margin-top:15px;"></div>
        </div>

        <div class="ys-section-card">
            <h3 class="ys-section-title"><span class="dashicons dashicons-info"></span> 說明</h3>
            <p>本外掛使用自訂資料表 <code><?php echo esc_html( $status['table_name'] ); ?></code> 儲存設定，減少對 <code>wp_options</code> 的佔用。</p>
            <ul style="margin-left:20px;">
                <li><strong>遷移設定</strong>：將 wp_options 中的設定複製到自訂資料表</li>
                <li><strong>清理舊設定</strong>：刪除 wp_options 中的舊設定（遷移完成後才可執行）</li>
                <li><strong>向後相容</strong>：如果自訂資料表不存在，系統會自動使用 wp_options</li>
            </ul>
        </div>

        <script type="text/javascript">
        jQuery(document).ready(function($) {
            $('#ys-migrate-settings').on('click', function() {
                var $btn = $(this);
                $btn.prop('disabled', true).text('遷移中...');

                $.post(ajaxurl, {
                    action: 'yangsheep_migrate_settings',
                    nonce: '<?php echo wp_create_nonce( 'yangsheep_migrate_settings' ); ?>'
                }, function(response) {
                    if (response.success) {
                        $('#ys-db-message').html('<div class="notice notice-success inline"><p>' + response.data + '</p></div>');
                        setTimeout(function() { location.reload(); }, 1500);
                    } else {
                        $('#ys-db-message').html('<div class="notice notice-error inline"><p>' + response.data + '</p></div>');
                        $btn.prop('disabled', false).html('<span class="dashicons dashicons-database-import"></span> 遷移設定到自訂資料表');
                    }
                });
            });

            $('#ys-cleanup-options').on('click', function() {
                if (!confirm('確定要刪除 wp_options 中的舊設定嗎？此操作無法復原。')) {
                    return;
                }

                var $btn = $(this);
                $btn.prop('disabled', true).text('清理中...');

                $.post(ajaxurl, {
                    action: 'yangsheep_cleanup_options',
                    nonce: '<?php echo wp_create_nonce( 'yangsheep_cleanup_options' ); ?>'
                }, function(response) {
                    if (response.success) {
                        $('#ys-db-message').html('<div class="notice notice-success inline"><p>' + response.data + '</p></div>');
                        setTimeout(function() { location.reload(); }, 1500);
                    } else {
                        $('#ys-db-message').html('<div class="notice notice-error inline"><p>' + response.data + '</p></div>');
                        $btn.prop('disabled', false).html('<span class="dashicons dashicons-trash"></span> 清理 wp_options 舊設定');
                    }
                });
            });
        });
        </script>
        <?php
    }

    public function settings_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $active_tab = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'general';
        ?>
        <div class="wrap">
            <h1 class="wp-heading-inline" style="display:none;"><?php echo esc_html( get_admin_page_title() ); ?></h1>
            <?php // WordPress 會把通知插入到 .wrap > h1 之後，這裡用隱藏的 h1 接收通知 ?>
        </div>

        <div class="ys-settings-wrap">
            <div class="ys-settings-header">
                <h2><span class="dashicons dashicons-cart"></span> <?php echo esc_html( get_admin_page_title() ); ?></h2>
                <p class="ys-settings-desc">強化 WooCommerce 結帳流程與訂單管理體驗</p>
            </div>

            <div class="ys-settings-actions">
                <button type="button" id="ys-reset-colors" class="button">
                    <span class="dashicons dashicons-image-rotate"></span> 一鍵恢復預設配色
                </button>
            </div>

            <nav class="nav-tab-wrapper ys-settings-tabs">
                <a href="#" class="nav-tab ys-tab-link <?php echo $active_tab == 'general' ? 'nav-tab-active' : ''; ?>" data-tab="general">
                    <span class="dashicons dashicons-admin-generic"></span> <?php _e( '基本設定', 'yangsheep-checkout-optimization' ); ?>
                </a>
                <a href="#" class="nav-tab ys-tab-link <?php echo $active_tab == 'checkout' ? 'nav-tab-active' : ''; ?>" data-tab="checkout">
                    <span class="dashicons dashicons-cart"></span> <?php _e( '結帳頁面', 'yangsheep-checkout-optimization' ); ?>
                </a>
                <a href="#" class="nav-tab ys-tab-link <?php echo $active_tab == 'account' ? 'nav-tab-active' : ''; ?>" data-tab="account">
                    <span class="dashicons dashicons-id-alt"></span> <?php _e( '我的帳號', 'yangsheep-checkout-optimization' ); ?>
                </a>
                <a href="#" class="nav-tab ys-tab-link <?php echo $active_tab == 'order_status' ? 'nav-tab-active' : ''; ?>" data-tab="order_status">
                    <span class="dashicons dashicons-tag"></span> <?php _e( '訂單狀態', 'yangsheep-checkout-optimization' ); ?>
                </a>
                <a href="#" class="nav-tab ys-tab-link <?php echo $active_tab == 'loyalty' ? 'nav-tab-active' : ''; ?>" data-tab="loyalty">
                    <span class="dashicons dashicons-star-filled"></span> <?php _e( '購物金整合', 'yangsheep-checkout-optimization' ); ?>
                </a>
                <a href="#" class="nav-tab ys-tab-link <?php echo $active_tab == 'docs' ? 'nav-tab-active' : ''; ?>" data-tab="docs">
                    <span class="dashicons dashicons-media-document"></span> <?php _e( '說明文件', 'yangsheep-checkout-optimization' ); ?>
                </a>
                <a href="#" class="nav-tab ys-tab-link <?php echo $active_tab == 'database' ? 'nav-tab-active' : ''; ?>" data-tab="database">
                    <span class="dashicons dashicons-database"></span> <?php _e( '資料庫管理', 'yangsheep-checkout-optimization' ); ?>
                </a>
            </nav>

            <form action="options.php" method="post" class="ys-settings-form">
                <?php settings_fields( 'yangsheep_checkout_optimization_group' ); ?>

                <div class="ys-tab-content" id="ys-tab-general" style="<?php echo $active_tab !== 'general' ? 'display:none;' : ''; ?>">
                    <?php do_settings_sections( 'yangsheep_tab_general' ); ?>
                    </div><!-- close ys-section-card -->
                </div>

                <div class="ys-tab-content" id="ys-tab-checkout" style="<?php echo $active_tab !== 'checkout' ? 'display:none;' : ''; ?>">
                    <?php do_settings_sections( 'yangsheep_tab_checkout' ); ?>
                    </div><!-- close ys-section-card -->
                </div>

                <div class="ys-tab-content" id="ys-tab-account" style="<?php echo $active_tab !== 'account' ? 'display:none;' : ''; ?>">
                    <?php do_settings_sections( 'yangsheep_tab_account' ); ?>
                    </div><!-- close ys-section-card -->
                </div>

                <div class="ys-tab-content" id="ys-tab-order_status" style="<?php echo $active_tab !== 'order_status' ? 'display:none;' : ''; ?>">
                    <?php do_settings_sections( 'yangsheep_tab_order_status' ); ?>
                    </div><!-- close ys-section-card -->
                </div>

                <div class="ys-tab-content" id="ys-tab-docs" style="<?php echo $active_tab !== 'docs' ? 'display:none;' : ''; ?>">
                    <div class="ys-docs-card">
                        <h2><span class="dashicons dashicons-book"></span> 結帳強化功能說明</h2>
                        <p>本外掛旨在強化 WooCommerce 結帳流程與訂單管理體驗，整合了 PayNow 與 PayUni 物流系統的進階顯示功能。</p>

                        <div class="ys-docs-section">
                            <h3><span class="dashicons dashicons-list-view"></span> 1. 訂單列表強化</h3>
                            <p>啟用「訂單配送狀態強化」後，前台「我的帳號 > 訂單」列表將升級為卡片式設計，並支援：</p>
                            <ul>
                                <li><strong>PayNow / PayUni 物流</strong>：顯示即時物流進度條</li>
                                <li><strong>手動物流單</strong>：後台輸入手動單號顯示進度</li>
                                <li><strong>UI 強化</strong>：圓角卡片設計、狀態顏色區分</li>
                            </ul>
                        </div>

                        <div class="ys-docs-section">
                            <h3><span class="dashicons dashicons-info"></span> 2. 狀態邏輯說明</h3>
                            <ul>
                                <li><strong>訂單成立</strong>：訂單建立但尚未有物流動作</li>
                                <li><strong>商品準備中</strong>：PayNow 列印標籤 / PayUni 取得物流單號</li>
                                <li><strong>運送中/已到店/已取貨</strong>：物流商回傳實際貨態</li>
                            </ul>
                        </div>

                        <div class="ys-docs-section">
                            <h3><span class="dashicons dashicons-admin-appearance"></span> 3. 配色說明</h3>
                            <p>本外掛預設使用<strong>莫蘭迪綠藍色系</strong>，營造柔和舒適的視覺體驗。您可以點擊「一鍵恢復預設配色」按鈕隨時恢復預設值。</p>
                        </div>

                        <div class="ys-docs-section">
                            <h3><span class="dashicons dashicons-store"></span> 4. 第三方物流相容性</h3>
                            <p>本外掛內建支援以下第三方物流外掛的超商取貨功能：</p>
                            <ul>
                                <li><strong>好用版 RY Tools for WooCommerce（綠界 ECPay）</strong>
                                    <ul>
                                        <li>支援 7-11、全家、萊爾富、OK 超商</li>
                                        <li>CVS 欄位（門市名稱、地址、電話）僅在選擇綠界超商物流時顯示</li>
                                        <li>「超商門市」選擇按鈕自動置中加粗</li>
                                    </ul>
                                </li>
                                <li><strong>好用版 PayNow Shipping（PayNow 超取）</strong>
                                    <ul>
                                        <li>支援 7-11、全家、萊爾富、OK 超商（C2C/B2C）</li>
                                        <li>超取欄位（門市名稱、門市代號、地址）僅在選擇 PayNow 超取時顯示</li>
                                        <li>「選擇超商」按鈕自動置中加粗</li>
                                    </ul>
                                </li>
                            </ul>
                            <p><strong>欄位排版：</strong>所有超取欄位自動設為 2 欄排版（桌機與手機）。</p>
                            <p><strong>自動偵測：</strong>系統會根據選擇的物流方式自動顯示/隱藏對應的 CVS 欄位，無需額外設定。</p>
                        </div>

                        <div class="ys-docs-section">
                            <h3><span class="dashicons dashicons-forms"></span> 5. 結帳欄位說明</h3>
                            <ul>
                                <li><strong>關閉 Last Name</strong>：啟用後只顯示「姓名」欄位（使用 First Name），適合台灣使用習慣</li>
                                <li><strong>台灣化欄位</strong>：
                                    <ul>
                                        <li>帳單欄位簡化為：姓名、電話、電子郵件</li>
                                        <li>運送欄位調整為台灣格式（郵遞區號、縣市、鄉鎮市區、地址）</li>
                                        <li>整合 TWzipcode 郵遞區號自動帶入</li>
                                    </ul>
                                </li>
                                <li><strong>訂單備註開關</strong>：用戶需勾選「我需要填寫訂單備註」才顯示備註欄位，其他欄位（如身分證字號）不受影響</li>
                                <li><strong>收件人電話</strong>：收件人區塊的電話欄位預設為必填</li>
                            </ul>
                        </div>

                        <div class="ys-docs-section">
                            <h3><span class="dashicons dashicons-car"></span> 6. 物流卡片功能</h3>
                            <ul>
                                <li>物流選項以卡片式呈現，提升使用者體驗</li>
                                <li>虛擬商品自動隱藏物流選擇區塊與收件人欄位</li>
                                <li>AJAX 即時更新，地址變更時自動重新計算可用物流</li>
                            </ul>
                        </div>
                    </div>
                </div>

                <div class="ys-tab-content" id="ys-tab-loyalty" style="<?php echo $active_tab !== 'loyalty' ? 'display:none;' : ''; ?>">
                    <?php do_settings_sections( 'yangsheep_tab_loyalty' ); ?>
                    </div><!-- close ys-section-card -->
                </div>

                <div class="ys-tab-content" id="ys-tab-database" style="<?php echo $active_tab !== 'database' ? 'display:none;' : ''; ?>">
                    <?php $this->render_database_management_tab(); ?>
                </div>

                <div class="ys-submit-wrap" id="ys-submit-button" style="<?php echo ( $active_tab === 'docs' || $active_tab === 'database' ) ? 'display:none;' : ''; ?>">
                    <?php submit_button( __( '儲存設定', 'yangsheep-checkout-optimization' ), 'primary large', 'submit', false ); ?>
                </div>
            </form>
        </div>

        <style>
        /* ===== YangSheep Settings Styles ===== */
        /* WordPress 通知區域 - 讓 .wrap 只負責顯示通知 */
        .wrap {
            max-width: 1200px;
            margin: 10px 20px 0 0;
        }
        .wrap .notice,
        .wrap .error,
        .wrap .updated {
            margin: 5px 0 15px 0;
        }
        .ys-settings-wrap {
            max-width: 1200px;
            margin: 20px 20px 20px 0;
        }
        .ys-settings-header {
            background: linear-gradient(135deg, #8fa8b8 0%, #7a95a6 100%);
            color: #fff;
            padding: 30px;
            border-radius: 12px;
            margin-bottom: 20px;
            box-shadow: 0 4px 15px rgba(143, 168, 184, 0.3);
        }
        .ys-settings-header h2 {
            color: #fff;
            margin: 0 0 8px 0;
            font-size: 28px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .ys-settings-header h2 .dashicons {
            font-size: 32px;
            width: 32px;
            height: 32px;
        }
        .ys-settings-desc {
            margin: 0;
            opacity: 0.9;
            font-size: 15px;
        }
        .ys-settings-actions {
            margin-bottom: 15px;
            text-align: right;
        }
        .ys-settings-actions .button {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 8px 16px;
            height: auto;
        }
        .ys-settings-tabs {
            border-bottom: 2px solid #c5d1d8;
            margin-bottom: 0;
            padding-bottom: 0;
        }
        .ys-settings-tabs .nav-tab {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 12px 20px;
            border: none;
            background: #f0f5f8;
            margin-right: 4px;
            border-radius: 8px 8px 0 0;
            color: #6b8a9a;
            font-weight: 500;
            transition: all 0.2s;
        }
        .ys-settings-tabs .nav-tab:hover {
            background: #e5eef3;
            color: #4a6a7a;
        }
        .ys-settings-tabs .nav-tab-active {
            background: #fff;
            color: #8fa8b8;
            box-shadow: 0 -2px 10px rgba(0,0,0,0.05);
        }
        .ys-settings-form {
            background: #fff;
            padding: 25px;
            border-radius: 0 0 12px 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        .ys-section-card {
            background: #f8fafb;
            border: 1px solid #c5d1d8;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
        }
        .ys-section-title {
            display: flex;
            align-items: center;
            gap: 8px;
            margin: 0 0 15px 0;
            padding-bottom: 12px;
            border-bottom: 1px solid #c5d1d8;
            color: #5a7080;
            font-size: 16px;
        }
        .ys-section-title .dashicons {
            color: #8fa8b8;
        }
        .ys-settings-form .form-table {
            margin: 0;
        }
        .ys-settings-form .form-table th {
            padding: 12px 10px 12px 0;
            width: 180px;
            color: #5a7080;
            font-weight: 500;
        }
        .ys-settings-form .form-table td {
            padding: 12px 0;
        }
        /* Toggle Switch */
        .ys-toggle-switch {
            position: relative;
            display: inline-block;
            width: 50px;
            height: 26px;
            vertical-align: middle;
        }
        .ys-toggle-switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }
        .ys-toggle-slider {
            position: absolute;
            cursor: pointer;
            top: 0; left: 0; right: 0; bottom: 0;
            background-color: #c5d1d8;
            transition: 0.3s;
            border-radius: 26px;
        }
        .ys-toggle-slider:before {
            position: absolute;
            content: "";
            height: 20px;
            width: 20px;
            left: 3px;
            bottom: 3px;
            background-color: white;
            transition: 0.3s;
            border-radius: 50%;
            box-shadow: 0 2px 4px rgba(0,0,0,0.2);
        }
        .ys-toggle-switch input:checked + .ys-toggle-slider {
            background-color: #8fa8b8;
        }
        .ys-toggle-switch input:checked + .ys-toggle-slider:before {
            transform: translateX(24px);
        }
        .ys-toggle-desc {
            margin-left: 12px;
            color: #666;
            vertical-align: middle;
        }
        /* Status Badge */
        .ys-status-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 8px 14px;
            border-radius: 6px;
            font-weight: 500;
        }
        .ys-status-success {
            background: #e8f5e9;
            color: #2e7d32;
        }
        .ys-status-warning {
            background: #fff3e0;
            color: #e65100;
        }
        /* Docs */
        .ys-docs-card {
            background: #f8fafb;
            border: 1px solid #c5d1d8;
            border-radius: 10px;
            padding: 30px;
        }
        .ys-docs-card h2 {
            display: flex;
            align-items: center;
            gap: 10px;
            color: #5a7080;
            margin-top: 0;
        }
        .ys-docs-section {
            margin-top: 25px;
            padding-top: 20px;
            border-top: 1px solid #c5d1d8;
        }
        .ys-docs-section h3 {
            display: flex;
            align-items: center;
            gap: 8px;
            color: #6b8a9a;
        }
        .ys-docs-section ul {
            margin-left: 25px;
        }
        .ys-docs-section li {
            margin-bottom: 8px;
            color: #555;
        }
        /* Submit */
        .ys-submit-wrap {
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid #c5d1d8;
        }
        .ys-submit-wrap .button-primary {
            background: #8fa8b8;
            border-color: #7a95a6;
            padding: 8px 30px;
            height: auto;
            font-size: 15px;
        }
        .ys-submit-wrap .button-primary:hover {
            background: #7a95a6;
            border-color: #6a8596;
        }
        /* Color Picker */
        .yangsheep-color-picker {
            width: 100px;
        }
        </style>

        <script type="text/javascript">
        jQuery(document).ready(function($) {
            // Initialize color picker
            $('.yangsheep-color-picker').wpColorPicker();

            // Tab switching
            $('.ys-tab-link').on('click', function(e) {
                e.preventDefault();
                var tab = $(this).data('tab');

                $('.ys-tab-link').removeClass('nav-tab-active');
                $(this).addClass('nav-tab-active');

                $('.ys-tab-content').hide();
                $('#ys-tab-' + tab).show();

                if (tab === 'docs' || tab === 'database') {
                    $('#ys-submit-button').hide();
                } else {
                    $('#ys-submit-button').show();
                }

                if (history.pushState) {
                    var url = new URL(window.location);
                    url.searchParams.set('tab', tab);
                    history.pushState({}, '', url);
                }
            });

            // Reset colors
            $('#ys-reset-colors').on('click', function() {
                if (!confirm('確定要恢復所有配色為預設值嗎？')) {
                    return;
                }

                var $btn = $(this);
                $btn.prop('disabled', true).text('處理中...');

                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'yangsheep_reset_colors',
                        nonce: '<?php echo wp_create_nonce( 'yangsheep_reset_colors' ); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            alert('已恢復預設配色，頁面將重新載入。');
                            location.reload();
                        } else {
                            alert('錯誤：' + response.data);
                        }
                    },
                    error: function() {
                        alert('發生錯誤，請稍後再試。');
                    },
                    complete: function() {
                        $btn.prop('disabled', false).html('<span class="dashicons dashicons-image-rotate"></span> 一鍵恢復預設配色');
                    }
                });
            });
        });
        </script>
        <?php
    }
}

add_action( 'init', array( 'YANGSHEEP_Checkout_Settings', 'get_instance' ) );

// 前端注入動態 MyAccount CSS 變數
add_action( 'wp_head', function() {
    if ( ! function_exists( 'is_account_page' ) || ! is_account_page() ) {
        return;
    }

    $myaccount_visual_enabled = YSSettingsManager::get( 'yangsheep_myaccount_visual', 'no' ) === 'yes';
    $order_enhancement_enabled = YSSettingsManager::get( 'yangsheep_enable_order_enhancement', 'no' ) === 'yes';

    if ( ! $myaccount_visual_enabled && ! $order_enhancement_enabled ) {
        return;
    }

    $vars = array();

    if ( $myaccount_visual_enabled ) {
        $vars['--nav-btn-bg']       = YSSettingsManager::get( 'yangsheep_myaccount_button_bg_color' );
        $vars['--nav-btn-txt']      = YSSettingsManager::get( 'yangsheep_myaccount_button_text_color' );
        $vars['--nav-btn-hover']    = YSSettingsManager::get( 'yangsheep_nav_button_hover_color' );
        $vars['--nav-btn-active']   = YSSettingsManager::get( 'yangsheep_nav_button_active_color' );
        $vars['--myacc-link']       = YSSettingsManager::get( 'yangsheep_myaccount_link_color' );
        $vars['--myacc-link-h']     = YSSettingsManager::get( 'yangsheep_myaccount_link_hover_color' );
    }

    if ( $order_enhancement_enabled ) {
        $vars['--ys-status-pending-bg']     = YSSettingsManager::get( 'yangsheep_status_pending_bg' );
        $vars['--ys-status-pending-text']   = YSSettingsManager::get( 'yangsheep_status_pending_text' );
        $vars['--ys-status-preparing-bg']   = YSSettingsManager::get( 'yangsheep_status_preparing_bg' );
        $vars['--ys-status-preparing-text'] = YSSettingsManager::get( 'yangsheep_status_preparing_text' );
        $vars['--ys-status-shipping-bg']    = YSSettingsManager::get( 'yangsheep_status_shipping_bg' );
        $vars['--ys-status-shipping-text']  = YSSettingsManager::get( 'yangsheep_status_shipping_text' );
        $vars['--ys-status-arrived-bg']     = YSSettingsManager::get( 'yangsheep_status_arrived_bg' );
        $vars['--ys-status-arrived-text']   = YSSettingsManager::get( 'yangsheep_status_arrived_text' );
        $vars['--ys-status-completed-bg']   = YSSettingsManager::get( 'yangsheep_status_completed_bg' );
        $vars['--ys-status-completed-text'] = YSSettingsManager::get( 'yangsheep_status_completed_text' );
    }

    if ( ! empty( $vars ) ) {
        $css_vars = ':root {';
        foreach ( $vars as $key => $val ) {
            $css_vars .= "{$key}: {$val};";
        }
        $css_vars .= '}';
        echo "<style>{$css_vars}
            .ct-account-user-box a { color: var(--myacc-link)!important; }
            .ct-account-user-box a:hover { color: var(--myacc-link-h)!important; }
        </style>";
    }
}, 99 );
