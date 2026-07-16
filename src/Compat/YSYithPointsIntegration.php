<?php
/**
 * YITH Points and Rewards 結帳頁整合
 *
 * v1.6.29：把散落在 yangsheep-checkout.js（line 122/163）
 * 和 yangsheep-checkout.css（line 609-650）的舊 YITH 相容碎片
 * 整理成正式 Compat 模組。
 *
 * v1.6.32：不同 YITH 設定可能輸出 `#yith-par-message-reward-cart` 與
 * `#yith-par-message-cart`，因此 default selectors 同時支援兩者。
 *
 * v1.6.34：YITH 搬移改為 fail-open。PHP 只提供 selector 與 enabled flag，
 * 不再於伺服器端加 body class 或以 CSS 強制控制 YITH display。JS 成功搬入
 * 目標容器後才標記 mounted；若 JS、selector 或目標容器失敗，原生 YITH
 * 留在原位置。
 *
 * 職責：
 * 1. 檢測 YITH Points & Rewards 外掛啟用
 * 2. 提供設定開關（yangsheep_yith_points_integration，預設 yes）
 * 3. wp_localize_script 傳 flag → JS 端擴充 `initPointRedeemBlock`
 *    讓它搬移 YITH 的 `#yith-par-message-cart` 或
 *    `#yith-par-message-reward-cart` 到 `.yangsheep-coupon-point`
 *    區塊；WPLoyalty `.wlr_point_redeem_message` 走 yangsheep-wployalty.js
 *    的獨立流程
 * 4. 提供 apply_filters( 'yangsheep_yith_points_selectors', $arr ) 供
 *    第三方擴充/加回其他 selector
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
                // v1.6.32：YITH Premium 不同版本會把可見 UI 放在不同 wrapper。
                // wecoware 實測 reward-cart 是可見折抵表單，所以兩者都要支援。
                'selectors' => apply_filters( 'yangsheep_yith_points_selectors', array(
                    '#yith-par-message-cart',
                    '#yith-par-message-reward-cart',
                ) ),
            )
        );
    }

}
