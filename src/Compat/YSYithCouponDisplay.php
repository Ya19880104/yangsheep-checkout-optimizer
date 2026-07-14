<?php
/**
 * YITH 折扣代碼顯示相容
 *
 * v1.6.28：YITH Points & Rewards 產生的折扣代碼（如 ywpar_discount_1-）
 * 直接顯示 raw code 不友善，此類別攔截 WC 相關 filter 換成中文名稱。
 *
 * 涵蓋 YITH prefix：
 *   - ywpar_discount_*   YITH Points & Rewards 折抵
 *   - ywpar_earn_*       YITH Points & Rewards 賺點
 *   - ywsbs_*            YITH Subscriptions
 *   - yith_ywgc_*        YITH Gift Cards
 *   - yith_wcac_*        YITH Abandoned Cart
 *   - yith_ywraq_*       YITH Request a Quote
 *
 * @package YangSheep\CheckoutOptimizer\Compat
 * @since 1.6.28
 */

namespace YangSheep\CheckoutOptimizer\Compat;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use YangSheep\CheckoutOptimizer\Settings\YSSettingsManager;

class YSYithCouponDisplay {

    private static $instance = null;

    /**
     * YITH prefix → 中文顯示名稱對照
     * key = prefix（不含後續 code），value = 顯示名稱
     */
    private $label_map = array(
        'ywpar_discount_' => '購物金折抵',
        'ywpar_earn_'     => 'YITH 賺取點數',
        'ywsbs_'          => 'YITH 訂閱折扣',
        'yith_ywgc_'      => '禮物卡折抵',
        'yith_wcac_'      => '購物車回訪優惠',
        'yith_ywraq_'     => '報價折扣',
    );

    public static function get_instance() {
        if ( is_null( self::$instance ) ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        // 依後台開關決定是否啟用（預設 yes）
        if ( YSSettingsManager::get( 'yangsheep_yith_coupon_friendly_label', 'yes' ) !== 'yes' ) {
            return;
        }

        // 允許篩選對照表（第三方可自訂 prefix）
        $this->label_map = apply_filters( 'yangsheep_yith_coupon_label_map', $this->label_map );

        // 攔截 WC coupon 顯示 label（購物車、結帳、訂單摘要都會用到）
        add_filter( 'woocommerce_cart_totals_coupon_label', array( $this, 'friendly_coupon_label' ), 10, 2 );
    }

    /**
     * 把 raw coupon code 映射為友善名稱
     *
     * @param string    $label   原始 label（通常是 "Coupon: {code}"）
     * @param \WC_Coupon $coupon
     * @return string
     */
    public function friendly_coupon_label( $label, $coupon ) {
        if ( ! ( $coupon instanceof \WC_Coupon ) ) {
            return $label;
        }

        $code = strtolower( $coupon->get_code() );
        $friendly = $this->resolve_friendly_name( $code );
        if ( '' === $friendly ) {
            return $label; // 非 YITH，維持原樣
        }

        // WC 原本 label 是「優惠券: xxx」— 換成友善名
        return $friendly;
    }

    /**
     * 依 code prefix 對照出中文名稱；不匹配回空字串
     *
     * @param string $code lowercased coupon code
     * @return string
     */
    private function resolve_friendly_name( $code ) {
        foreach ( $this->label_map as $prefix => $name ) {
            if ( 0 === strpos( $code, $prefix ) ) {
                return $name;
            }
        }
        return '';
    }
}
