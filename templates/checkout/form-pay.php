<?php
/**
 * Template Override: checkout/form-pay.php
 *
 * 重新付款頁（order-pay endpoint）區塊式設計，沿用結帳頁 CSS 變數。
 * 參考 WooCommerce core form-pay.php v8.2.0。
 *
 * 視覺設計：
 *   ┌──────────────────────────────────┐
 *   │ 訂單明細                          │
 *   ├──────────────────────────────────┤
 *   │ [縮圖] 商品名      單價 × 數量 小計 │
 *   ├──────────────────────────────────┤
 *   │ 小計                      NT$xxx  │
 *   │ 運送方式  HELLO           NT$xxx  │
 *   ├──────────────────────────────────┤
 *   │ 總計                      NT$xxx  │（放大加強）
 *   └──────────────────────────────────┘
 *
 * @version 1.1.0
 */

if ( ! defined( 'ABSPATH' ) ) exit;

$totals = $order->get_order_item_totals();

// 付款方式不在訂單摘要重複顯示（下方有選擇支付方式區塊）
if ( isset( $totals['payment_method'] ) ) {
    unset( $totals['payment_method'] );
}

// 區分主要總計列（放大強調）
$final_total_keys = array( 'order_total' );
?>
<div class="yangsheep-design-checkout-page yangsheep-design-pay-page" style="margin-top:20px;">

  <!-- 通知訊息 -->
  <div class="woocommerce-notices-wrapper"><?php wc_print_notices(); ?></div>

  <form id="order_review" method="post">

    <!-- 1. 訂單明細區塊 -->
    <section class="yangsheep-review-wrapper yangsheep-pay-summary">
      <header class="yangsheep-pay-summary__header">
        <h3 class="yangsheep-h3-title"><?php esc_html_e( '訂單明細', 'yangsheep-checkout-optimization' ); ?></h3>
        <span class="yangsheep-pay-summary__order-no">
          <?php echo esc_html( sprintf( __( '訂單編號 #%s', 'yangsheep-checkout-optimization' ), $order->get_order_number() ) ); ?>
        </span>
      </header>

      <!-- 商品列表 -->
      <ul class="yangsheep-pay-items" role="list">
        <?php foreach ( $order->get_items() as $item_id => $item ) :
          if ( ! apply_filters( 'woocommerce_order_item_visible', true, $item ) ) continue;

          $_product  = $item->get_product();
          $quantity  = $item->get_quantity();
          $thumbnail = $_product ? $_product->get_image( array( 60, 60 ) ) : '';
          $name      = apply_filters( 'woocommerce_order_item_name', $item->get_name(), $item, false );
          $permalink = $_product ? $_product->get_permalink() : '';
          $subtotal  = $order->get_formatted_line_subtotal( $item );
          $unit_price = $_product ? wc_price( (float) $item->get_subtotal() / max( 1, $quantity ), array( 'currency' => $order->get_currency() ) ) : '';
        ?>
          <li class="yangsheep-pay-item">
            <div class="yangsheep-pay-item__media">
              <?php if ( $thumbnail ) : ?>
                <?php if ( $permalink ) : ?>
                  <a href="<?php echo esc_url( $permalink ); ?>" target="_blank" rel="noopener"><?php echo $thumbnail; ?></a>
                <?php else : ?>
                  <?php echo $thumbnail; ?>
                <?php endif; ?>
              <?php else : ?>
                <span class="yangsheep-pay-item__media-placeholder" aria-hidden="true"></span>
              <?php endif; ?>
            </div>
            <div class="yangsheep-pay-item__body">
              <div class="yangsheep-pay-item__name">
                <?php echo wp_kses_post( $name ); ?>
              </div>
              <div class="yangsheep-pay-item__meta">
                <?php
                do_action( 'woocommerce_order_item_meta_start', $item_id, $item, $order, false );
                wc_display_item_meta( $item );
                do_action( 'woocommerce_order_item_meta_end', $item_id, $item, $order, false );
                ?>
              </div>
              <div class="yangsheep-pay-item__price-row">
                <span class="yangsheep-pay-item__unit"><?php echo wp_kses_post( $unit_price ); ?></span>
                <span class="yangsheep-pay-item__sep" aria-hidden="true">×</span>
                <span class="yangsheep-pay-item__qty"><?php echo esc_html( $quantity ); ?></span>
              </div>
            </div>
            <div class="yangsheep-pay-item__subtotal">
              <?php echo wp_kses_post( $subtotal ); ?>
            </div>
          </li>
        <?php endforeach; ?>
      </ul>

      <!-- 金額摘要（小計 / 運送 / 稅金...）+ 最終總計 -->
      <?php if ( ! empty( $totals ) ) : ?>
        <div class="yangsheep-pay-totals">
          <?php foreach ( $totals as $key => $total ) :
            $is_final = in_array( $key, $final_total_keys, true );
            $row_class = 'yangsheep-pay-totals__row' . ( $is_final ? ' is-final' : '' );
            $row_class .= ' yangsheep-pay-totals__row--' . sanitize_html_class( $key );
          ?>
            <div class="<?php echo esc_attr( $row_class ); ?>">
              <span class="yangsheep-pay-totals__label"><?php echo wp_kses_post( $total['label'] ); ?></span>
              <span class="yangsheep-pay-totals__value"><?php echo wp_kses_post( $total['value'] ); ?></span>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </section>

    <?php
    /**
     * 付款區塊前的 WooCommerce core hook
     */
    do_action( 'woocommerce_pay_order_before_payment' );
    ?>

    <!-- 2. 付款區塊 -->
    <section class="yangsheep-payment">
      <h3 class="yangsheep-h3-title"><?php esc_html_e( '選擇支付方式', 'yangsheep-checkout-optimization' ); ?></h3>
      <div class="yangsheep-payment-block">
        <div id="payment">
          <?php if ( $order->needs_payment() ) : ?>
            <ul class="wc_payment_methods payment_methods methods">
              <?php
              if ( ! empty( $available_gateways ) ) {
                  foreach ( $available_gateways as $gateway ) {
                      wc_get_template( 'checkout/payment-method.php', array( 'gateway' => $gateway ) );
                  }
              } else {
                  echo '<li>';
                  wc_print_notice( apply_filters( 'woocommerce_no_available_payment_methods_message', esc_html__( 'Sorry, it seems that there are no available payment methods for your location. Please contact us if you require assistance or wish to make alternate arrangements.', 'woocommerce' ) ), 'notice' );
                  echo '</li>';
              }
              ?>
            </ul>
          <?php endif; ?>

          <div class="form-row yangsheep-pay-submit">
            <input type="hidden" name="woocommerce_pay" value="1" />

            <?php wc_get_template( 'checkout/terms.php' ); ?>

            <?php do_action( 'woocommerce_pay_order_before_submit' ); ?>

            <?php echo apply_filters( 'woocommerce_pay_order_button_html', '<button type="submit" class="button alt' . esc_attr( wc_wp_theme_get_element_class_name( 'button' ) ? ' ' . wc_wp_theme_get_element_class_name( 'button' ) : '' ) . '" id="place_order" value="' . esc_attr( $order_button_text ) . '" data-value="' . esc_attr( $order_button_text ) . '">' . esc_html( $order_button_text ) . '</button>' ); ?>

            <?php do_action( 'woocommerce_pay_order_after_submit' ); ?>

            <?php wp_nonce_field( 'woocommerce-pay', 'woocommerce-pay-nonce' ); ?>
          </div>
        </div>
      </div>
    </section>

  </form>
</div>
