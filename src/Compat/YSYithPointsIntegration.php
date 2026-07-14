<?php
/**
 * YITH Points and Rewards 結帳頁整合
 *
 * v1.6.29：把散落在 yangsheep-checkout.js（line 122/163）
 * 和 yangsheep-checkout.css（line 609-650）的舊 YITH 相容碎片
 * 整理成正式 Compat 模組。
 *
 * 職責：
 * 1. 檢測 YITH Points & Rewards 外掛啟用
 * 2. 提供設定開關（yangsheep_yith_points_integration，預設 yes）
 * 3. wp_localize_script 傳 flag → JS 端擴充 `initPointRedeemBlock`
 *    讓它同時搬移 WPLoyalty 的 `.wlr_point_redeem_message` 與
 *    YITH 的 `#yith-par-message-cart` / `#yith-par-message-reward-cart`
 *    到 `.yangsheep-coupon-point` 區塊
 * 4. 提供 add_filter 讓 JS side 知道要抓 YITH selector
 *
 * @package YangSheep\CheckoutOptimizer\Compat
 * @since 1.6.29
 */

namespace YangSheep\CheckoutOptimizer\Compat;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use YangSheep\CheckoutOptimizer\Settings\YSSettingsManager;

class YSYithPointsIntegration {

    private static $instance = null;

    public static function get_instance() {
        if ( is_null( self::$instance ) ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        // 只有 YITH Points 外掛啟用時才啟動整合
        if ( ! self::is_yith_par_active() ) {
            return;
        }

        // 設定關閉時不動作
        if ( YSSettingsManager::get( 'yangsheep_yith_points_integration', 'yes' ) !== 'yes' ) {
            return;
        }

        // 傳 flag 到前端 JS（yangsheep-checkout.js 讀 `yangsheep_yith_points.enabled`
        // 決定是否把 YITH `#yith-par-message-cart` 一併搬進 `.yangsheep-coupon-point`）
        add_action( 'wp_enqueue_scripts', array( $this, 'localize_flag' ), 20 );
    }

    /**
     * 偵測 YITH Points and Rewards（免費版 / Premium 皆包含）
     *
     * @return bool
     */
    public static function is_yith_par_active() {
        return defined( 'YITH_YWPAR_VERSION' )
            || defined( 'YITH_YWPAR_PREMIUM' )
            || class_exists( 'YITH_YWPAR' )
            || function_exists( 'YITH_YWPAR' );
    }

    /**
     * 傳 flag 給前端 JS（掛在 yangsheep-checkout-custom handle）
     */
    public function localize_flag() {
        if ( ! function_exists( 'is_checkout' ) || ! is_checkout() ) {
            return;
        }
        if ( ! wp_script_is( 'yangsheep-checkout-custom', 'enqueued' ) ) {
            return;
        }
        wp_localize_script(
            'yangsheep-checkout-custom',
            'yangsheep_yith_points',
            array(
                'enabled'  => true,
                // v1.6.30：拿掉 #yith-par-message-reward-cart。它是 YITH 的 hidden
                // submit target（內含 ywpar_input_points 供表單送出），且外掛內部 CSS
                // 用 display:none !important 強制隱藏 —— 搬到 .yangsheep-coupon-point
                // 只會複製一份不可見的元素，沒視覺效果反而有 duplicate DOM 風險。
                // 真正供顯示的訊息在 #yith-par-message-cart。
                'selectors' => apply_filters( 'yangsheep_yith_points_selectors', array(
                    '#yith-par-message-cart',
                ) ),
            )
        );
    }
}
