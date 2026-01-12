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

if ( ! defined( 'ABSPATH' ) ) exit;

class YANGSHEEP_Shipping_Cards {
    
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
        
        // 註冊 AJAX Fragment
        add_filter( 'woocommerce_update_order_review_fragments', [ $this, 'register_fragment' ] );
        
        // 移除原訂單表格中的物流 HTML（關鍵：避免 radio name 衝突）
        // 已改用 JS 方式處理，不再使用 label filter
        
        // 確保物流 hooks 在自訂區塊執行
        add_action( 'yangsheep_before_shipping_cards', [ $this, 'do_before_shipping_hooks' ] );
        add_action( 'yangsheep_after_shipping_cards', [ $this, 'do_after_shipping_hooks' ] );
        
        // 替換原訂單表格中的 shipping 方法輸出為空（關鍵：避免 radio name 衝突）
        add_filter( 'woocommerce_shipping_show_shipping_calculator', '__return_false' );
        add_action( 'wp_footer', [ $this, 'add_hide_shipping_script' ] );
    }
    
    /**
     * 添加 JS 腳本隱藏/移除原始 shipping radios
     */
    public function add_hide_shipping_script() {
        if ( ! is_checkout() || is_wc_endpoint_url() ) {
            return;
        }
        ?>
        <script>
        jQuery(function($) {
            // 處理原訂單表格中的 shipping 區塊
            function processOriginalShipping() {
                // 選取原訂單表格中的 shipping 行
                var $shippingRows = $('#order_review tr.shipping, .woocommerce-checkout-review-order-table tr.shipping, tr.woocommerce-shipping-totals');
                
                console.log('[YS Shipping] Found shipping rows:', $shippingRows.length);
                
                $shippingRows.each(function() {
                    var $row = $(this);
                    var $ul = $row.find('ul#shipping_method, ul.woocommerce-shipping-methods');
                    var $thLabel = $row.find('th');
                    var $tdValue = $row.find('td[data-title], td:last');
                    
                    console.log('[YS Shipping] Processing row, ul found:', $ul.length);
                    
                    if ($ul.length) {
                        // 找到選中的物流方式 - 從我們的卡片區塊讀取
                        var $ourCardRadio = $('.yangsheep-shipping-cards input.shipping_method:checked');
                        var shippingName = '';
                        var shippingCost = '';
                        
                        if ($ourCardRadio.length) {
                            // 從我們的卡片讀取名稱
                            var $card = $ourCardRadio.closest('.yangsheep-shipping-card');
                            shippingName = $card.find('.yangsheep-shipping-label').text().trim();
                            shippingCost = $card.find('.yangsheep-shipping-price').text().trim();
                            
                            // 移除 label 中的價格部分
                            shippingName = shippingName.replace(/NT\$[\d,]+/g, '').replace(/\$[\d,]+/g, '').trim();
                            // 移除尾部冒號
                            shippingName = shippingName.replace(/:$/, '').trim();
                        } else {
                            // 備用：從原始 ul 讀取
                            var $checkedInput = $ul.find('input.shipping_method:checked');
                            if ($checkedInput.length) {
                                var $label = $ul.find('label[for="' + $checkedInput.attr('id') + '"]');
                                if ($label.length) {
                                    var $clone = $label.clone();
                                    $clone.find('.woocommerce-Price-amount, .amount, bdi, span').remove();
                                    shippingName = $clone.text().trim().replace(/:$/, '');
                                }
                            }
                        }
                        
                        console.log('[YS Shipping] Shipping name:', shippingName, 'Cost:', shippingCost);
                        
                        // 隱藏原始 ul
                        $ul.hide();
                        
                        // 移除原本的 radios name 避免衝突
                        $ul.find('input.shipping_method').removeAttr('name').prop('disabled', true);
                        
                        // 清空 td 並顯示運費與名稱
                        var $display = $tdValue.find('.yangsheep-selected-shipping-display');
                        if (!$display.length) {
                            $tdValue.html('<span class="yangsheep-selected-shipping-display"></span>');
                            $display = $tdValue.find('.yangsheep-selected-shipping-display');
                        }
                        
                        // 顯示格式：物流名稱 + 運費（右對齊）
                        if (shippingName && shippingCost) {
                            $display.html(shippingName + ' <span style="float:right;">' + shippingCost + '</span>');
                        } else if (shippingName) {
                            $display.text(shippingName);
                        }
                    }
                });
            }
            
            // 頁面載入時執行
            setTimeout(processOriginalShipping, 200);
            
            // AJAX 更新後重新執行
            $(document.body).on('updated_checkout', function() {
                setTimeout(processOriginalShipping, 200);

                // 內容為空就隱藏
                $('.yangsheep-shipping-cards-wrapper').toggle($('.yangsheep-shipping-cards-container').children().length > 0);
                $('.woocommerce-shipping-fields').toggle($('.woocommerce-shipping-fields').children().length > 0);
            });
        });
        </script>
        <?php
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
     * 執行物流前的標準 hooks
     * 確保第三方外掛相容性
     */
    public function do_before_shipping_hooks() {
        do_action( 'woocommerce_review_order_before_shipping' );
    }
    
    /**
     * 執行物流後的標準 hooks
     * 確保第三方外掛相容性
     */
    public function do_after_shipping_hooks() {
        do_action( 'woocommerce_review_order_after_shipping' );
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
