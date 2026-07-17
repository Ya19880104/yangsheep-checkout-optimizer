<?php
/**
 * Progressive checkout layout enhancement.
 *
 * WooCommerce owns the checkout form, order review, payment markup and standard
 * hooks. This class only adds optional YS regions. JavaScript reveals and
 * rearranges them after all required native nodes are present; otherwise the
 * native checkout remains visible and usable.
 *
 * @package YangSheep\CheckoutOptimizer
 */

namespace YangSheep\CheckoutOptimizer\Checkout;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use YangSheep\CheckoutOptimizer\Settings\YSSettingsManager;

class YSCheckoutLayout {

    /** @var self|null */
    private static $instance = null;

    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    private function __construct() {
        add_action( 'woocommerce_checkout_before_customer_details', array( $this, 'render_enhancement_regions' ), 5 );
        // 參考設計：付款區在帳單資訊（#customer_details）之後、頁尾之前
        add_action( 'woocommerce_checkout_after_customer_details', array( $this, 'render_payment_region' ), 5 );
        add_action( 'woocommerce_checkout_before_order_review_heading', array( $this, 'render_sidebar_target' ), 5 );
        add_action( 'woocommerce_before_checkout_shipping_form', array( $this, 'render_same_as_billing_control' ), 5 );
        add_action( 'woocommerce_before_order_notes', array( $this, 'render_order_notes_control' ), 5 );
    }

    private function is_main_checkout() {
        return function_exists( 'is_checkout' ) && is_checkout() && ! is_wc_endpoint_url();
    }

    public function render_enhancement_regions() {
        if ( ! $this->is_main_checkout() ) {
            return;
        }
        ?>
        <div class="yangsheep-enhancement-regions" hidden>
            <?php // 區塊順序 = 原始 CYBERBIZ 參考設計：國家 → 商品明細 → 折扣 → 運送方式 ?>
            <section class="yangsheep-checkout-country" aria-labelledby="order_country_heading">
                <h3 id="order_country_heading"><?php esc_html_e( '請選擇商品運送國家', 'yangsheep-checkout-optimization' ); ?></h3>
            </section>

            <section class="yangsheep-review-wrapper" aria-labelledby="yangsheep-products-heading">
                <div class="yangsheep-order-review">
                    <h3 id="yangsheep-products-heading"><?php esc_html_e( '商品明細', 'yangsheep-checkout-optimization' ); ?></h3>
                    <div id="yangsheep_order_items" class="yangsheep-order-items-container">
                        <?php do_action( 'yangsheep_order_items' ); ?>
                    </div>
                </div>
            </section>

            <div class="yangsheep-smart-coupon"></div>

            <?php
            // 折扣區留在 checkout <form> 內（視覺位置=參考設計）。
            // ⚠ P0 防線：含 <form> 的第三方兌換介面（YITH 等）「絕不」移入本區 —
            // Woo/YITH fragment 以 HTML 字串重繪時，form.checkout 內的巢狀 <form>
            // 會被 parser 丟棄，兌換按鈕會變成 checkout 提交鈕（誤觸下單/付款）。
            // YITH 介面留在 form 外原位置作為提交事實源，.yangsheep-coupon-point
            // 內只放 JS 淨化後的「視覺 proxy」（無 name/id/form，不參與提交）；
            // 非 AJAX POST 的欄位遺失由 YSYithPointsIntegration 快照機制還原。
            ?>
            <section class="yangsheep-coupon-block" aria-labelledby="yangsheep-coupon-heading">
                <div class="yangsheep-coupon">
                    <h3 id="yangsheep-coupon-heading" class="yangsheep-h3-title"><?php esc_html_e( '折扣代碼', 'yangsheep-checkout-optimization' ); ?></h3>
                    <p class="yangsheep-coupon-text"><?php esc_html_e( '若您有折扣代碼，請直接輸入代碼折抵。', 'yangsheep-checkout-optimization' ); ?></p>
                    <?php do_action( 'yangsheep_coupon' ); ?>
                </div>
                <div class="yangsheep-coupon-point" aria-live="polite"></div>
            </section>

            <section class="yangsheep-shipping-cards-wrapper">
                <div class="yangsheep-shipping-cards-container">
                    <?php do_action( 'yangsheep_shipping_cards' ); ?>
                </div>
            </section>
        </div>
        <?php
    }

    /**
     * 付款區容器（帳單資訊之後；JS 成功後把原生 #payment 搬入）
     */
    public function render_payment_region() {
        if ( ! $this->is_main_checkout() ) {
            return;
        }
        ?>
        <section class="yangsheep-payment" aria-labelledby="yangsheep-payment-heading" hidden>
            <h3 id="yangsheep-payment-heading" class="yangsheep-h3-title"><?php esc_html_e( '選擇支付方式', 'yangsheep-checkout-optimization' ); ?></h3>
            <div class="yangsheep-payment-block"></div>
        </section>
        <?php
    }

    public function render_sidebar_target() {
        if ( ! $this->is_main_checkout() ) {
            return;
        }
        ?>
        <aside class="yangsheep-checkout-sidebar-wrapper" aria-label="<?php esc_attr_e( '訂單摘要', 'yangsheep-checkout-optimization' ); ?>" hidden>
            <?php
            // 原始設計三盒：結帳金額 / 運輸方式 / 購物車內容（YSCheckoutSidebar 渲染，
            // 內容以 #id fragment 隨每次 update_checkout 由伺服器重繪 — 不依賴
            // 被 Woo fragment 替換洗掉的 DOM 標記）
            do_action( 'yangsheep_checkout_sidebar' );
            ?>
        </aside>
        <?php
    }

    public function render_same_as_billing_control() {
        if ( ! $this->is_main_checkout() ) {
            return;
        }
        ?>
        <label class="yangsheep-same-as-billing" for="yangsheep_copy_billing" hidden>
            <input type="checkbox" id="yangsheep_copy_billing" name="yangsheep_copy_billing" value="1">
            <span><?php esc_html_e( '同訂購人姓名電話', 'yangsheep-checkout-optimization' ); ?></span>
        </label>
        <?php
    }

    public function render_order_notes_control( $checkout ) {
        if (
            ! $this->is_main_checkout()
            || YSSettingsManager::get( 'yangsheep_checkout_order_note', 'no' ) !== 'yes'
            || ! apply_filters( 'woocommerce_enable_order_notes_field', 'yes' === get_option( 'woocommerce_enable_order_comments', 'yes' ) )
        ) {
            return;
        }

        $order_fields = $checkout->get_checkout_fields( 'order' );
        if ( empty( $order_fields['order_comments'] ) ) {
            return;
        }
        ?>
        <label class="yangsheep-order-notes-toggle" for="yangsheep_show_order_notes" hidden>
            <input type="checkbox" id="yangsheep_show_order_notes" name="yangsheep_show_order_notes" value="1">
            <span><?php esc_html_e( '我需要填寫訂單備註', 'yangsheep-checkout-optimization' ); ?></span>
        </label>
        <?php
    }
}
