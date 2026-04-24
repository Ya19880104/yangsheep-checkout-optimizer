<?php
/**
 * Template Override: myaccount/view-subscription.php
 *
 * 我的帳號 > 訂閱內頁重設計。
 * 套用與感謝頁 / view-order 相同的視覺語言，並加入訂閱狀態 badge。
 *
 * 保留 WC Subscriptions 所有 action：
 * - `woocommerce_subscription_details_table` → subscription-details.php
 * - `woocommerce_subscription_totals_table` → subscription-totals.php
 * - `woocommerce_subscription_details_after_subscription_table` → related orders / subscriptions
 *
 * 參考：WooCommerce Subscriptions view-subscription.php v1.0.0
 *
 * @version 1.0.0
 *
 * @var WC_Subscription $subscription
 */

if ( ! defined( 'ABSPATH' ) ) exit;

wc_print_notices();

$status_raw   = $subscription->get_status();
$status_label = function_exists( 'wcs_get_subscription_status_name' ) ? wcs_get_subscription_status_name( $status_raw ) : $status_raw;
$start_date   = $subscription->get_date_to_display( 'start_date' );
?>
<div class="yangsheep-design-checkout-page yangsheep-design-thankyou-page yangsheep-design-myaccount-view-page yangsheep-design-view-subscription-page">

  <!-- Hero -->
  <section class="yangsheep-view-hero">
    <div class="yangsheep-view-hero__row">
      <h2 class="yangsheep-view-hero__title">
        <?php echo esc_html( sprintf( __( '訂閱 #%s', 'yangsheep-checkout-optimization' ), $subscription->get_order_number() ) ); ?>
      </h2>
      <span class="yangsheep-status-badge yangsheep-status-badge--subscription yangsheep-status-badge--<?php echo esc_attr( sanitize_html_class( $status_raw ) ); ?>">
        <?php echo esc_html( $status_label ); ?>
      </span>
    </div>
    <?php if ( $start_date ) : ?>
      <p class="yangsheep-view-hero__sub">
        <?php echo esc_html( sprintf( __( '開始日期：%s', 'yangsheep-checkout-optimization' ), $start_date ) ); ?>
      </p>
    <?php endif; ?>
  </section>

  <!-- 訂閱詳情（狀態/日期/付款/動作）+ 總計 + 相關訂單：全部走 WC Subscriptions 原生 action -->
  <div class="yangsheep-thankyou-details yangsheep-view-subscription-details">
    <?php
    do_action( 'woocommerce_subscription_details_table', $subscription );
    do_action( 'woocommerce_subscription_totals_table', $subscription );
    do_action( 'woocommerce_subscription_details_after_subscription_table', $subscription );

    // 帳單地址
    wc_get_template( 'order/order-details-customer.php', array( 'order' => $subscription ) );
    ?>
  </div>

  <div class="clear"></div>
</div>
