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

    private const CHECKOUT_FIELD_SESSION_KEY = 'yangsheep_yith_checkout_fields';
    private const CHECKOUT_FIELD_MAX_AGE     = 120;

    private static $instance = null;
    private $checkout_field_snapshot_exposed = false;

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
        add_action( 'wp_loaded', array( $this, 'capture_checkout_fields' ), 20 );
        add_filter( 'woocommerce_checkout_get_value', array( $this, 'restore_checkout_field' ), 9999, 2 );
        add_action( 'wp_footer', array( $this, 'clear_checkout_field_snapshot' ), 999 );

    }

    /**
     * Capture checkout values mirrored into a non-AJAX YITH redemption POST.
     */
    public function capture_checkout_fields() {
        if (
            empty( $_POST['ys_yith_checkout_field_names'] )
            || empty( $_POST['ywpar_input_points_nonce'] )
            || empty( $_POST['ywpar_input_points_check'] )
            || ! function_exists( 'WC' )
            || ! WC()->session
        ) {
            return;
        }

        $nonce = sanitize_text_field( wp_unslash( $_POST['ywpar_input_points_nonce'] ) );
        if ( ! wp_verify_nonce( $nonce, 'ywpar_apply_discounts' ) ) {
            return;
        }

        $posted_names = wc_clean( wp_unslash( (array) $_POST['ys_yith_checkout_field_names'] ) );
        $fields       = array();

        foreach ( array_unique( $posted_names ) as $posted_name ) {
            $posted_name = preg_replace( '/\[\]$/', '', (string) $posted_name );
            if ( ! $posted_name || ! preg_match( '/^[A-Za-z0-9_-]+$/', $posted_name ) ) {
                continue;
            }
            if ( ! array_key_exists( $posted_name, $_POST ) ) {
                continue;
            }

            $fields[ $posted_name ] = wc_clean( wp_unslash( $_POST[ $posted_name ] ) );
        }

        if ( $fields ) {
            WC()->session->set(
                self::CHECKOUT_FIELD_SESSION_KEY,
                array(
                    'created_at' => time(),
                    'fields'     => $fields,
                )
            );
        }
    }

    /**
     * Restore captured values after YITH redirects back to checkout.
     *
     * @param mixed  $value Current checkout value.
     * @param string $input Checkout field name.
     * @return mixed
     */
    public function restore_checkout_field( $value, $input ) {
        $snapshot = $this->get_checkout_field_snapshot();
        if ( ! $snapshot ) {
            return $value;
        }

        return array_key_exists( $input, $snapshot['fields'] )
            ? $snapshot['fields'][ $input ]
            : $value;
    }

    /**
     * Get a valid short-lived checkout snapshot from the Woo session.
     *
     * @return array|null
     */
    private function get_checkout_field_snapshot() {
        if ( ! function_exists( 'WC' ) || ! WC()->session ) {
            return null;
        }

        $snapshot = WC()->session->get( self::CHECKOUT_FIELD_SESSION_KEY );
        if ( ! is_array( $snapshot ) || empty( $snapshot['fields'] ) ) {
            return null;
        }

        if ( time() - (int) ( $snapshot['created_at'] ?? 0 ) > self::CHECKOUT_FIELD_MAX_AGE ) {
            WC()->session->set( self::CHECKOUT_FIELD_SESSION_KEY, null );
            return null;
        }

        return $snapshot;
    }

    /**
     * Delete the one-page snapshot after the restored checkout has rendered.
     */
    public function clear_checkout_field_snapshot() {
        if ( ! $this->checkout_field_snapshot_exposed ) {
            return;
        }

        if ( function_exists( 'WC' ) && WC()->session ) {
            WC()->session->set( self::CHECKOUT_FIELD_SESSION_KEY, null );
        }
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

    public static function get_detected_version() {
        if ( defined( 'YITH_YWPAR_VERSION' ) ) {
            return (string) YITH_YWPAR_VERSION;
        }

        return self::is_yith_par_active() ? __( '已偵測，版本未知', 'yangsheep-checkout-optimization' ) : '';
    }

    public static function get_selectors() {
        return apply_filters(
            'yangsheep_yith_points_selectors',
            array(
                '#yith-par-message-cart',
                '#yith-par-message-reward-cart',
            )
        );
    }

    /**
     * Whether YITH's global points redemption switch is enabled.
     *
     * @return bool|null Null means the option API is unavailable.
     */
    public static function is_rewards_redemption_enabled() {
        if ( ! function_exists( 'ywpar_get_option' ) ) {
            return null;
        }

        return 'yes' === ywpar_get_option( 'enable_rewards_points' );
    }

    /**
     * Count published redeeming rules that YITH marks active.
     *
     * @return int
     */
    public static function get_active_redeeming_rule_count() {
        if ( ! self::is_yith_par_active() ) {
            return 0;
        }

        $rule_ids = get_posts(
            array(
                'post_type'      => 'ywpar-redeeming-rule',
                'post_status'    => 'publish',
                'posts_per_page' => -1,
                'fields'         => 'ids',
                'no_found_rows'  => true,
                'meta_query'     => array(
                    array(
                        'key'     => '_status',
                        'value'   => 'on',
                        'compare' => '=',
                    ),
                ),
            )
        );

        return is_array( $rule_ids ) ? count( $rule_ids ) : 0;
    }

    /**
     * 傳 flag 給前端 JS（掛在 yangsheep-checkout-custom handle）
     */
    public function localize_flag() {
        if ( ! function_exists( 'is_checkout' ) || ! is_checkout() || is_wc_endpoint_url() ) {
            return;
        }
        if ( ! wp_script_is( 'yangsheep-checkout-custom', 'enqueued' ) ) {
            return;
        }
        $snapshot = $this->get_checkout_field_snapshot();
        if ( $snapshot ) {
            $this->checkout_field_snapshot_exposed = true;
        }

        wp_localize_script(
            'yangsheep-checkout-custom',
            'yangsheep_yith_points',
            array(
                'enabled'         => true,
                'selectors'       => self::get_selectors(),
                'preservedFields' => $snapshot ? $snapshot['fields'] : array(),
            )
        );
    }

}
