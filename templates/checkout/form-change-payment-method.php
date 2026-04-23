<?php
/**
 * Template Override: checkout/form-change-payment-method.php
 *
 * WooCommerce Subscriptions 變更付款方式表單。
 * 套用與 order-pay 頁面（form-pay.php）相同的 .yangsheep-design-pay-page 視覺。
 *
 * 參考：woocommerce-subscriptions/templates/checkout/form-change-payment-method.php v1.0.0
 *
 * @version 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) exit;

$totals = $subscription->get_order_item_totals();

// 避免在摘要重複顯示「付款方式」（下方 radio 就是選擇區）
if ( isset( $totals['payment_method'] ) ) {
    unset( $totals['payment_method'] );
}

$final_total_keys = array( 'order_total' );

// 決定送出按鈕文字（沿用 WCS 邏輯）
if ( $subscription->has_payment_gateway() ) {
    $pay_order_button_text = _x( 'Change payment method', 'text on button on checkout page', 'woocommerce-subscriptions' );
} else {
    $pay_order_button_text = _x( 'Add payment method', 'text on button on checkout page', 'woocommerce-subscriptions' );
}
$pay_order_button_text     = apply_filters( 'woocommerce_change_payment_button_text', $pay_order_button_text );
$customer_subscription_ids = WCS_Customer_Store::instance()->get_users_subscription_ids( $subscription->get_customer_id() );
$payment_gateways_handler  = WC_Subscriptions_Core_Plugin::instance()->get_gateways_handler_class();
$available_gateways        = WC()->payment_gateways->get_available_payment_gateways();
?>
<div class="yangsheep-design-checkout-page yangsheep-design-pay-page yangsheep-design-change-payment-page" style="margin-top:20px;">

  <!-- 通知訊息 -->
  <div class="woocommerce-notices-wrapper"><?php wc_print_notices(); ?></div>

  <form id="order_review" method="post">

    <!-- 1. 訂閱摘要區塊 -->
    <section class="yangsheep-review-wrapper yangsheep-pay-summary">
      <header class="yangsheep-pay-summary__header">
        <h3 class="yangsheep-h3-title"><?php esc_html_e( '訂閱明細', 'yangsheep-checkout-optimization' ); ?></h3>
        <span class="yangsheep-pay-summary__order-no">
          <?php echo esc_html( sprintf( __( '訂閱編號 #%s', 'yangsheep-checkout-optimization' ), $subscription->get_order_number() ) ); ?>
        </span>
      </header>

      <!-- 商品列表 -->
      <ul class="yangsheep-pay-items" role="list">
        <?php foreach ( $subscription->get_items() as $item_id => $item ) :
          $_product  = is_callable( array( $item, 'get_product' ) ) ? $item->get_product() : null;
          $quantity  = is_callable( array( $item, 'get_quantity' ) ) ? $item->get_quantity() : ( isset( $item['qty'] ) ? (int) $item['qty'] : 1 );
          $thumbnail = $_product ? $_product->get_image( array( 60, 60 ) ) : '';
          $name      = is_callable( array( $item, 'get_name' ) ) ? $item->get_name() : ( isset( $item['name'] ) ? $item['name'] : '' );
          $permalink = $_product ? $_product->get_permalink() : '';
          $subtotal  = $subscription->get_formatted_line_subtotal( $item );

          $unit_price = '';
          if ( $_product && is_callable( array( $item, 'get_subtotal' ) ) && $quantity > 0 ) {
              $unit_price = wc_price( (float) $item->get_subtotal() / $quantity, array( 'currency' => $subscription->get_currency() ) );
          }
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
              <?php if ( is_callable( array( $item, 'get_meta_data' ) ) ) : ?>
                <div class="yangsheep-pay-item__meta">
                  <?php
                  do_action( 'woocommerce_order_item_meta_start', $item_id, $item, $subscription, false );
                  wc_display_item_meta( $item );
                  do_action( 'woocommerce_order_item_meta_end', $item_id, $item, $subscription, false );
                  ?>
                </div>
              <?php endif; ?>
              <?php if ( $unit_price !== '' ) : ?>
                <div class="yangsheep-pay-item__price-row">
                  <span class="yangsheep-pay-item__unit"><?php echo wp_kses_post( $unit_price ); ?></span>
                  <span class="yangsheep-pay-item__sep" aria-hidden="true">×</span>
                  <span class="yangsheep-pay-item__qty"><?php echo esc_html( $quantity ); ?></span>
                </div>
              <?php endif; ?>
            </div>
            <div class="yangsheep-pay-item__subtotal">
              <?php echo wp_kses_post( $subtotal ); ?>
            </div>
          </li>
        <?php endforeach; ?>
      </ul>

      <!-- 金額摘要 + 最終總計 -->
      <?php if ( ! empty( $totals ) ) : ?>
        <div class="yangsheep-pay-totals">
          <?php foreach ( $totals as $key => $total ) :
            $is_final  = in_array( $key, $final_total_keys, true );
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

      <!-- 下次付款提示（保留 WC Subscriptions 原本訊息在 woocommerce_before_checkout_form 輸出） -->
    </section>

    <!-- 2. 選擇付款方式區塊 -->
    <section class="yangsheep-payment">
      <h3 class="yangsheep-h3-title"><?php esc_html_e( '選擇新付款方式', 'yangsheep-checkout-optimization' ); ?></h3>
      <div class="yangsheep-payment-block">
        <div id="payment">
          <?php if ( $available_gateways ) : ?>
            <ul class="wc_payment_methods payment_methods methods">
              <?php
              if ( count( $available_gateways ) ) {
                  current( $available_gateways )->set_current();
              }

              foreach ( $available_gateways as $gateway ) :
                  $supports_payment_method_changes = WC_Subscriptions_Change_Payment_Gateway::can_update_all_subscription_payment_methods( $gateway, $subscription );
              ?>
                <li class="wc_payment_method payment_method_<?php echo esc_attr( $gateway->id ); ?>">
                  <input id="payment_method_<?php echo esc_attr( $gateway->id ); ?>" type="radio" class="input-radio <?php echo $supports_payment_method_changes ? 'supports-payment-method-changes' : ''; ?>" name="payment_method" value="<?php echo esc_attr( $gateway->id ); ?>" <?php checked( $gateway->chosen, true ); ?> data-order_button_text="<?php echo esc_attr( apply_filters( 'wcs_gateway_change_payment_button_text', $pay_order_button_text, $gateway ) ); ?>" />
                  <label for="payment_method_<?php echo esc_attr( $gateway->id ); ?>">
                    <?php echo esc_html( $gateway->get_title() ); ?>
                    <?php echo wp_kses_post( $gateway->get_icon() ); ?>
                  </label>
                  <?php if ( $gateway->has_fields() || $gateway->get_description() ) : ?>
                    <div class="payment_box payment_method_<?php echo esc_attr( $gateway->id ); ?>" style="display:none;">
                      <?php $gateway->payment_fields(); ?>
                    </div>
                  <?php endif; ?>
                </li>
              <?php endforeach; ?>
            </ul>
          <?php else : ?>
            <div class="woocommerce-error">
              <p><?php echo esc_html( apply_filters( 'woocommerce_no_available_payment_methods_message', __( 'Sorry, it seems no payment gateways support changing the recurring payment method. Please contact us if you require assistance or to make alternate arrangements.', 'woocommerce-subscriptions' ) ) ); ?></p>
            </div>
          <?php endif; ?>

          <?php if ( $available_gateways ) : ?>
            <?php if ( count( $customer_subscription_ids ) > 1 && $payment_gateways_handler::one_gateway_supports( 'subscription_payment_method_change_admin' ) ) : ?>
              <div class="yangsheep-pay-update-all">
                <span class="update-all-subscriptions-payment-method-wrap">
                  <?php
                  $label = sprintf(
                      /* translators: $1: opening <strong> tag, $2: closing </strong> tag */
                      esc_html__( 'Use this payment method for %1$sall%2$s of my current subscriptions', 'woocommerce-subscriptions' ),
                      '<strong>',
                      '</strong>'
                  );
                  woocommerce_form_field(
                      'update_all_subscriptions_payment_method',
                      array(
                          'type'     => 'checkbox',
                          'class'    => array( 'form-row-wide' ),
                          'label'    => $label,
                          'required' => true,
                          'default'  => apply_filters( 'wcs_update_all_subscriptions_payment_method_checked', true ),
                      )
                  );
                  ?>
                </span>
              </div>
            <?php endif; ?>

            <div class="form-row yangsheep-pay-submit">
              <?php wp_nonce_field( 'wcs_change_payment_method', '_wcsnonce', true, true ); ?>

              <?php do_action( 'woocommerce_subscriptions_change_payment_before_submit' ); ?>

              <?php
              echo wp_kses(
                  apply_filters(
                      'woocommerce_change_payment_button_html',
                      '<input type="submit" class="button alt' . esc_attr( wc_wp_theme_get_element_class_name( 'button' ) ? ' ' . wc_wp_theme_get_element_class_name( 'button' ) : '' ) . '" id="place_order" value="' . esc_attr( $pay_order_button_text ) . '" data-value="' . esc_attr( $pay_order_button_text ) . '" />'
                  ),
                  array(
                      'input' => array(
                          'type'       => array(),
                          'class'      => array(),
                          'id'         => array(),
                          'value'      => array(),
                          'data-value' => array(),
                      ),
                  )
              );
              ?>

              <?php do_action( 'woocommerce_subscriptions_change_payment_after_submit' ); ?>

              <input type="hidden" name="woocommerce_change_payment" value="<?php echo esc_attr( $subscription->get_id() ); ?>" />
            </div>
          <?php endif; ?>
        </div>
      </div>
    </section>

  </form>
</div>
