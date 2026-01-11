<?php
/**
 * 結帳側邊欄處理類別
 * 
 * 功能：
 * - 獨立的結帳金額摘要（商品小計、運費、總金額）
 * - 購物車內容簡化列表
 * - AJAX 自動更新
 * 
 * @package YANGSHEEP_Checkout_Optimization
 * @version 1.3.0
 * @since 2026-01-07
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class YANGSHEEP_Checkout_Sidebar {
    
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
        // 註冊自訂 action hooks
        add_action( 'yangsheep_checkout_sidebar', [ $this, 'render_sidebar' ] );
        add_action( 'yangsheep_order_summary', [ $this, 'render_order_summary' ] );
        add_action( 'yangsheep_cart_contents', [ $this, 'render_cart_contents' ] );
        
        // 註冊 AJAX Fragments
        add_filter( 'woocommerce_update_order_review_fragments', [ $this, 'register_fragments' ] );
    }
    
    /**
     * 渲染完整側邊欄
     */
    public function render_sidebar() {
        ?>
        <div class="yangsheep-checkout-sidebar" id="yangsheep-checkout-sidebar">
            <?php $this->render_order_summary(); ?>
            <?php $this->render_shipping_display(); ?>
            <?php $this->render_cart_contents(); ?>
        </div>
        <?php
    }
    
    /**
     * 渲染運輸方式顯示
     */
    public function render_shipping_display() {
        if ( ! WC()->cart || ! WC()->cart->needs_shipping() ) {
            return;
        }
        
        $shipping_name = $this->get_chosen_shipping_name();
        if ( empty( $shipping_name ) ) {
            return;
        }
        ?>
        <div class="yangsheep-shipping-display" id="yangsheep-shipping-display">
            <span class="yangsheep-shipping-display-label"><?php esc_html_e( '運輸方式', 'yangsheep-checkout-optimization' ); ?></span>
            <span class="yangsheep-shipping-display-name"><?php echo esc_html( $shipping_name ); ?></span>
        </div>
        <?php
    }
    
    /**
     * 取得選中的運輸方式名稱
     */
    private function get_chosen_shipping_name() {
        $packages = WC()->shipping()->get_packages();
        $chosen_methods = WC()->session->get( 'chosen_shipping_methods' );
        
        if ( empty( $packages ) || empty( $chosen_methods ) ) {
            return '';
        }
        
        foreach ( $packages as $i => $package ) {
            if ( isset( $chosen_methods[ $i ], $package['rates'][ $chosen_methods[ $i ] ] ) ) {
                $rate = $package['rates'][ $chosen_methods[ $i ] ];
                return $rate->get_label();
            }
        }
        
        return '';
    }
    
    /**
     * 渲染訂單金額摘要
     */
    public function render_order_summary() {
        if ( ! WC()->cart ) {
            return;
        }
        
        $cart = WC()->cart;
        ?>
        <div class="yangsheep-order-summary" id="yangsheep-order-summary">
            <h3 class="yangsheep-sidebar-title">
                <?php esc_html_e( '結帳金額', 'yangsheep-checkout-optimization' ); ?>
            </h3>
            <div class="yangsheep-summary-content">
                <div class="yangsheep-summary-row">
                    <span class="yangsheep-summary-label"><?php esc_html_e( '商品小計', 'yangsheep-checkout-optimization' ); ?></span>
                    <span class="yangsheep-summary-value"><?php echo $cart->get_cart_subtotal(); ?></span>
                </div>
                
                <?php if ( $cart->needs_shipping() && WC()->session->get( 'chosen_shipping_methods' ) ) : ?>
                <div class="yangsheep-summary-row">
                    <span class="yangsheep-summary-label"><?php esc_html_e( '運費', 'yangsheep-checkout-optimization' ); ?></span>
                    <span class="yangsheep-summary-value"><?php echo $this->get_shipping_total(); ?></span>
                </div>
                <?php endif; ?>
                
                <?php if ( $cart->get_discount_total() > 0 ) : ?>
                <div class="yangsheep-summary-row yangsheep-discount">
                    <span class="yangsheep-summary-label"><?php esc_html_e( '折扣', 'yangsheep-checkout-optimization' ); ?></span>
                    <span class="yangsheep-summary-value">-<?php echo wc_price( $cart->get_discount_total() ); ?></span>
                </div>
                <?php endif; ?>
                
                <div class="yangsheep-summary-row yangsheep-total">
                    <span class="yangsheep-summary-label"><?php esc_html_e( '應付總額', 'yangsheep-checkout-optimization' ); ?></span>
                    <span class="yangsheep-summary-value yangsheep-total-amount">
                        <span class="yangsheep-currency"><?php echo get_woocommerce_currency_symbol(); ?></span>
                        <span class="yangsheep-amount"><?php echo number_format( (float) $cart->get_total( 'edit' ), 0 ); ?></span>
                    </span>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * 渲染購物車內容
     */
    public function render_cart_contents() {
        if ( ! WC()->cart ) {
            return;
        }
        
        $cart_items = WC()->cart->get_cart();
        $item_count = WC()->cart->get_cart_contents_count();
        ?>
        <div class="yangsheep-cart-contents" id="yangsheep-cart-contents">
            <h3 class="yangsheep-sidebar-title yangsheep-collapsible" data-target="yangsheep-cart-items">
                <?php esc_html_e( '購物車內容', 'yangsheep-checkout-optimization' ); ?>
                <span class="yangsheep-toggle-icon">▼</span>
            </h3>
            <div class="yangsheep-cart-items" id="yangsheep-cart-items">
                <?php foreach ( $cart_items as $cart_item_key => $cart_item ) : 
                    $product = $cart_item['data'];
                    $product_name = $product->get_name();
                    $quantity = $cart_item['quantity'];
                ?>
                <div class="yangsheep-cart-item">
                    <span class="yangsheep-item-name"><?php echo esc_html( $product_name ); ?></span>
                    <span class="yangsheep-item-qty"><?php printf( esc_html__( '數量：%d', 'yangsheep-checkout-optimization' ), $quantity ); ?></span>
                </div>
                <?php endforeach; ?>
                
                <div class="yangsheep-cart-summary">
                    <?php printf( esc_html__( '合計有 %d 項商品', 'yangsheep-checkout-optimization' ), $item_count ); ?>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * 取得運費總計
     */
    private function get_shipping_total() {
        $packages = WC()->shipping()->get_packages();
        $chosen_methods = WC()->session->get( 'chosen_shipping_methods' );
        $total = 0;
        
        foreach ( $packages as $i => $package ) {
            if ( isset( $chosen_methods[ $i ], $package['rates'][ $chosen_methods[ $i ] ] ) ) {
                $rate = $package['rates'][ $chosen_methods[ $i ] ];
                $total += (float) $rate->get_cost();
                if ( is_numeric( $rate->get_shipping_tax() ) ) {
                    $total += (float) $rate->get_shipping_tax();
                }
            }
        }
        
        if ( $total == 0 ) {
            return '<span class="yangsheep-free-shipping">' . __( '免運費', 'yangsheep-checkout-optimization' ) . '</span>';
        }
        
        return wc_price( $total );
    }
    
    /**
     * 註冊 AJAX Fragments
     */
    public function register_fragments( $fragments ) {
        // 訂單金額摘要
        ob_start();
        $this->render_order_summary();
        $fragments['#yangsheep-order-summary'] = ob_get_clean();
        
        // 運輸方式顯示
        ob_start();
        $this->render_shipping_display();
        $fragments['#yangsheep-shipping-display'] = ob_get_clean();
        
        // 購物車內容
        ob_start();
        $this->render_cart_contents();
        $fragments['#yangsheep-cart-contents'] = ob_get_clean();
        
        return $fragments;
    }
}
