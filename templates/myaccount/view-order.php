<?php
/**
 * Template Override: myaccount/view-order.php
 *
 * 我的帳號 > 訂單內頁重設計。
 * 套用與感謝頁一致的 .yangsheep-design-pay-page 視覺語言，
 * 加上 .yangsheep-design-myaccount-view-page 作為 scope 讓 CSS 針對此情境套用。
 *
 * 保留 WC 原生 hook `woocommerce_view_order`（觸發 order-details + addresses）。
 *
 * 參考：WooCommerce core view-order.php v10.1.0
 *
 * @version 1.0.0
 *
 * @var WC_Order $order
 * @var int $order_id
 */

defined( 'ABSPATH' ) || exit;

$notes = $order->get_customer_order_notes();

$status_raw   = $order->get_status();
$status_label = wc_get_order_status_name( $status_raw );
$order_date   = wc_format_datetime( $order->get_date_created() );
?>
<div class="yangsheep-design-checkout-page yangsheep-design-thankyou-page yangsheep-design-myaccount-view-page yangsheep-design-view-order-page">

  <!-- Hero 簡潔版 -->
  <section class="yangsheep-view-hero">
    <div class="yangsheep-view-hero__row">
      <h2 class="yangsheep-view-hero__title">
        <?php echo esc_html( sprintf( __( '訂單 #%s', 'yangsheep-checkout-optimization' ), $order->get_order_number() ) ); ?>
      </h2>
      <span class="yangsheep-status-badge yangsheep-status-badge--<?php echo esc_attr( sanitize_html_class( $status_raw ) ); ?>">
        <?php echo esc_html( $status_label ); ?>
      </span>
    </div>
    <p class="yangsheep-view-hero__sub">
      <?php
      /* translators: %s: order placed date */
      echo esc_html( sprintf( __( '訂購日期：%s', 'yangsheep-checkout-optimization' ), $order_date ) );
      ?>
    </p>
  </section>

  <?php if ( $notes ) : ?>
    <section class="yangsheep-view-notes">
      <h3 class="yangsheep-h3-title"><?php esc_html_e( '訂單更新紀錄', 'yangsheep-checkout-optimization' ); ?></h3>
      <ol class="woocommerce-OrderUpdates commentlist notes">
        <?php foreach ( $notes as $note ) : ?>
          <li class="woocommerce-OrderUpdate comment note">
            <div class="woocommerce-OrderUpdate-inner comment_container">
              <div class="woocommerce-OrderUpdate-text comment-text">
                <p class="woocommerce-OrderUpdate-meta meta">
                  <?php echo esc_html( date_i18n( __( 'l jS \o\f F Y, h:ia', 'woocommerce' ), strtotime( $note->comment_date ) ) ); ?>
                </p>
                <div class="woocommerce-OrderUpdate-description description">
                  <?php echo wp_kses_post( wpautop( wptexturize( $note->comment_content ) ) ); ?>
                </div>
              </div>
            </div>
          </li>
        <?php endforeach; ?>
      </ol>
    </section>
  <?php endif; ?>

  <!-- 訂單明細 + 地址（WC 原生渲染） -->
  <div class="yangsheep-thankyou-details">
    <?php do_action( 'woocommerce_view_order', $order_id ); ?>
  </div>

</div>
