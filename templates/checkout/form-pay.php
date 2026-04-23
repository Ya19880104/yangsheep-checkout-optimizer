<?php
/**
 * Template Override: checkout/form-pay.php
 *
 * 重新付款頁（order-pay endpoint）區塊式設計，沿用結帳頁 CSS 變數。
 * 參考 WooCommerce core form-pay.php v8.2.0。
 *
 * @version 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) exit;

$totals = $order->get_order_item_totals();
?>
<div class="yangsheep-design-checkout-page yangsheep-design-pay-page" style="margin-top:20px;">

  <!-- 通知訊息 -->
  <div class="woocommerce-notices-wrapper"><?php wc_print_notices(); ?></div>

  <form id="order_review" method="post">

    <!-- 1. 訂單商品明細區塊 -->
    <div class="yangsheep-review-wrapper">
      <div class="yangsheep-order-review">
        <h3 class="yangsheep-h3-title"><?php esc_html_e( '訂單明細', 'yangsheep-checkout-optimization' ); ?></h3>

        <div class="yangsheep-pay-order-items-container">
          <table class="shop_table yangsheep-pay-items-table">
            <thead>
              <tr>
                <th class="product-name"><?php esc_html_e( '商品', 'yangsheep-checkout-optimization' ); ?></th>
                <th class="product-quantity"><?php esc_html_e( '數量', 'yangsheep-checkout-optimization' ); ?></th>
                <th class="product-total"><?php esc_html_e( '小計', 'yangsheep-checkout-optimization' ); ?></th>
              </tr>
            </thead>
            <tbody>
              <?php if ( count( $order->get_items() ) > 0 ) : ?>
                <?php foreach ( $order->get_items() as $item_id => $item ) : ?>
                  <?php if ( ! apply_filters( 'woocommerce_order_item_visible', true, $item ) ) { continue; } ?>
                  <tr class="<?php echo esc_attr( apply_filters( 'woocommerce_order_item_class', 'order_item', $item, $order ) ); ?>">
                    <td class="product-name">
                      <?php
                      echo wp_kses_post( apply_filters( 'woocommerce_order_item_name', $item->get_name(), $item, false ) );
                      do_action( 'woocommerce_order_item_meta_start', $item_id, $item, $order, false );
                      wc_display_item_meta( $item );
                      do_action( 'woocommerce_order_item_meta_end', $item_id, $item, $order, false );
                      ?>
                    </td>
                    <td class="product-quantity">
                      <?php echo apply_filters( 'woocommerce_order_item_quantity_html', ' <strong class="product-quantity">' . sprintf( '&times;&nbsp;%s', esc_html( $item->get_quantity() ) ) . '</strong>', $item ); ?>
                    </td>
                    <td class="product-subtotal"><?php echo wp_kses_post( $order->get_formatted_line_subtotal( $item ) ); ?></td>
                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
            <tfoot>
              <?php if ( $totals ) : ?>
                <?php foreach ( $totals as $total ) : ?>
                  <tr>
                    <th scope="row" colspan="2"><?php echo wp_kses_post( $total['label'] ); ?></th>
                    <td class="product-total"><?php echo wp_kses_post( $total['value'] ); ?></td>
                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tfoot>
          </table>
        </div>
      </div>
    </div>

    <?php
    /**
     * 付款區塊前的 WooCommerce core hook
     */
    do_action( 'woocommerce_pay_order_before_payment' );
    ?>

    <!-- 2. 付款區塊 -->
    <div class="yangsheep-payment">
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
    </div>

  </form>
</div>
