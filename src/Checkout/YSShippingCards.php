<?php
/**
 * 物流選擇卡片化處理類別
 *
 * 功能：
 * - 將物流選項從訂單明細表分離
 * - 以卡片式 Radio 呈現物流選項
 * - 處理 AJAX Fragment 更新
 *
 * @package YANGSHEEP_Checkout_Optimization
 * @version 1.3.0
 * @since 2026-01-07
 */

namespace YangSheep\CheckoutOptimizer\Checkout;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class YSShippingCards {

    /**
     * 單例實例
     */
    private static $instance = null;

    /**
     * 取得單例實例
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
        // 註冊自訂 action hook
        add_action( 'yangsheep_shipping_cards', [ $this, 'render_shipping_cards' ] );

        // 早於常見物流外掛的 priority 10 fragment filters 註冊視覺卡片 fragment。
        // Woo 核心 review-order 仍負責標準 shipping hooks 與原生 radio fragment。
        add_filter( 'woocommerce_update_order_review_fragments', [ $this, 'register_fragment' ], 5 );
    }

    /**
     * 渲染物流選擇卡片
     */
    public function render_shipping_cards() {
        // 檢查是否需要物流
        if ( ! WC()->cart || ! WC()->cart->needs_shipping() ) {
            return;
        }

        // 取得物流包裹
        $packages = WC()->shipping()->get_packages();

        if ( empty( $packages ) ) {
            return;
        }

        // 載入模板
        $template_path = YANGSHEEP_CHECKOUT_OPTIMIZATION_DIR . 'templates/checkout/shipping-cards.php';

        if ( file_exists( $template_path ) ) {
            include $template_path;
        }
    }

    /**
     * 註冊 AJAX Fragment
     *
     * 當結帳頁面地址變更時，WooCommerce 會觸發 AJAX 更新
     * 此 filter 讓我們的物流卡片區塊也能被更新
     */
    public function register_fragment( $fragments ) {
        // 開始輸出緩衝
        ob_start();

        echo '<div class="yangsheep-shipping-cards-container">';
        $this->render_shipping_cards();
        echo '</div>';

        // 將輸出存入 fragments
        $fragments['.yangsheep-shipping-cards-container'] = ob_get_clean();

        return $fragments;
    }

    /**
     * 取得已選擇的物流方式
     */
    public static function get_chosen_shipping_method( $package_index = 0 ) {
        $chosen_methods = WC()->session->get( 'chosen_shipping_methods' );
        return isset( $chosen_methods[ $package_index ] ) ? $chosen_methods[ $package_index ] : '';
    }

    /**
     * 格式化物流價格顯示
     */
    public static function format_shipping_cost( $rate ) {
        $cost = $rate->get_cost();

        if ( 0 == $cost ) {
            return '<span class="yangsheep-shipping-free">' . __( '免運費', 'yangsheep-checkout-optimization' ) . '</span>';
        }

        // 計算含稅價格
        $price = $cost;
        if ( is_numeric( $rate->get_shipping_tax() ) ) {
            $price += $rate->get_shipping_tax();
        }

        return '<span class="yangsheep-shipping-cost">' . wc_price( $price ) . '</span>';
    }
}
