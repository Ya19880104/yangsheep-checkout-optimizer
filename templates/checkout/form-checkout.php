<?php
/**
 * Template Override: form-checkout.php
 * 
 * 區塊順序（符合 CSS order）：
 * 1. login-block（layout 外）
 * 2. checkout-country
 * 3. review-wrapper
 * 4. smart-coupon
 * 5. coupon-block
 * 6. shipping-cards-wrapper
 * 7. customer-details
 * 8. sidebar-wrapper（桌機版移至 sidebar-column）
 * 9. payment
 * 
 * @version 3.5.0
 */
if ( ! defined( 'ABSPATH' ) ) exit;
if ( function_exists('WC') && version_compare(WC()->version,'3.5.0','<') ) wc_print_notices();
do_action('woocommerce_before_checkout_form',$checkout);
if ( ! $checkout->is_registration_enabled() && $checkout->is_registration_required() && ! is_user_logged_in()){
    echo esc_html(apply_filters('woocommerce_checkout_must_be_logged_in_message',__('You must be logged in to checkout.','woocommerce')));
    return;
}
$checkout_url=apply_filters('woocommerce_get_checkout_url',wc_get_checkout_url());
?>
<div class="yangsheep-design-checkout-page" style="margin-top:20px;">
  <!-- 1. 登入區塊 -->
  <div class="yangsheep-login-block"><?php do_action('yangsheep_loginform');?></div>
  
  <!-- 桌機版容器：sidebar + form -->
  <div class="yangsheep-checkout-layout">
    <!-- 側邊欄容器（桌機版 JS 會移入 sidebar-wrapper） -->
    <div class="yangsheep-sidebar-column" id="yangsheep-sidebar-column"></div>
    
    <!-- 表單區域 -->
    <form name="checkout" method="post" class="checkout woocommerce-checkout yangsheep-form-column" action="<?php echo esc_url($checkout_url);?>" enctype="multipart/form-data">
      
      <!-- 2. 國家選擇 -->
      <div class="yangsheep-checkout-country">
        <h3 id="order_country_heading"><?php esc_html_e('請選擇商品運送國家','yangsheep-checkout-optimization');?></h3>
      </div>
      
      <!-- 3. 商品明細 -->
      <div class="yangsheep-review-wrapper">
        <div class="yangsheep-order-review">
          <h3 id="order_review_heading"><?php esc_html_e('商品明細','yangsheep-checkout-optimization');?></h3>
          <!-- 使用自訂 action 繞過主題 -->
          <div id="yangsheep_order_items" class="yangsheep-order-items-container">
            <?php do_action('yangsheep_order_items');?>
          </div>
          <!-- 保留 WooCommerce 的訂單摘要（運費、總計等）但用隱藏 div 包裹 -->
          <div class="yangsheep-order-totals">
            <?php do_action('yangsheep_order_totals');?>
          </div>
        </div>
      </div>
      
      <!-- 4. Smart Coupon -->
      <div class="yangsheep-smart-coupon"></div>
      
      <!-- 5. 折扣代碼 -->
      <div class="yangsheep-coupon-block">
        <div class="yangsheep-coupon">
          <h3 class="yangsheep-h3-title"><?php esc_html_e('折扣代碼','yangsheep-checkout-optimization');?></h3>
          <div class="yangsheep-coupon-text" style="font-size:15px;"><?php esc_html_e('若您有折扣代碼，請直接輸入代碼折抵。','yangsheep-checkout-optimization');?></div>
          <?php do_action('yangsheep_coupon');?>
        </div>
      </div>
      
      <!-- 6. 物流選擇 -->
      <div class="yangsheep-shipping-cards-wrapper">
        <div class="yangsheep-shipping-cards-container">
          <?php do_action('yangsheep_shipping_cards');?>
        </div>
      </div>
      
      <!-- 7. 客戶資料 -->
      <div class="yangsheep-customer-details">
        <?php if($checkout->get_checkout_fields()): 
          do_action('woocommerce_checkout_before_customer_details');?>
          <div id="customer_details">
            <?php do_action('woocommerce_checkout_billing');?>
            <?php do_action('woocommerce_checkout_shipping');?>
          </div>
        <?php do_action('woocommerce_checkout_after_customer_details'); 
        endif; ?>
      </div>
      
      <!-- 8. 側邊欄（桌機版 JS 移至 sidebar-column） -->
      <div class="yangsheep-checkout-sidebar-wrapper">
        <?php do_action('yangsheep_checkout_sidebar');?>
      </div>
      
      <!-- 9. 付款 -->
      <div class="yangsheep-payment">
        <h3 class="yangsheep-h3-title"><?php esc_html_e('選擇支付方式', 'yangsheep-checkout-optimization'); ?></h3>
        <div class="yangsheep-payment-block">
          <?php do_action('yangsheep_payment');?>
        </div>
      </div>
      
    </form>
  </div>
</div>
<?php do_action('woocommerce_after_checkout_form',$checkout);?>