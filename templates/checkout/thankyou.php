<?php
/**
 * Template Override: checkout/thankyou.php
 *
 * 感謝頁重設計：
 *   - Hero 感謝區（icon + 標題 + 副標）
 *   - Order overview 卡片式 4 欄（編號 / 日期 / Email / 付款方式）
 *   - 總計 banner（主色強調）
 *   - 原生 woocommerce_thankyou hook 輸出訂單明細 + 地址（維持 WC 標準區塊）
 *
 * max-width 1280px，水平 padding 交給父層 .ct-container 處理。
 *
 * 參考：WooCommerce core thankyou.php v8.1.0
 *
 * @version 1.0.0
 *
 * @var WC_Order|false $order
 */

defined( 'ABSPATH' ) || exit;
?>

<div class="yangsheep-design-checkout-page yangsheep-design-thankyou-page woocommerce-order">

  <?php if ( $order ) :
    do_action( 'woocommerce_before_thankyou', $order->get_id() );
    $is_failed = $order->has_status( 'failed' );
  ?>

    <?php if ( $is_failed ) : ?>

      <!-- 失敗 Hero -->
      <section class="yangsheep-thankyou-hero yangsheep-thankyou-hero--failed" aria-live="polite">
        <div class="yangsheep-thankyou-hero__icon" aria-hidden="true">
          <svg xmlns="http://www.w3.org/2000/svg" width="56" height="56" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <circle cx="12" cy="12" r="10"></circle>
            <line x1="15" y1="9" x2="9" y2="15"></line>
            <line x1="9" y1="9" x2="15" y2="15"></line>
          </svg>
        </div>
        <h1 class="yangsheep-thankyou-hero__title"><?php esc_html_e( '付款未完成', 'yangsheep-checkout-optimization' ); ?></h1>
        <p class="yangsheep-thankyou-hero__subtitle">
          <?php esc_html_e( '很抱歉，您的付款被銀行或支付機構拒絕。您可以重新嘗試付款或選擇其他付款方式。', 'yangsheep-checkout-optimization' ); ?>
        </p>
        <div class="yangsheep-thankyou-hero__actions">
          <a href="<?php echo esc_url( $order->get_checkout_payment_url() ); ?>" class="button yangsheep-btn yangsheep-btn--primary">
            <?php esc_html_e( '重新付款', 'yangsheep-checkout-optimization' ); ?>
          </a>
          <?php if ( is_user_logged_in() ) : ?>
            <a href="<?php echo esc_url( wc_get_page_permalink( 'myaccount' ) ); ?>" class="button yangsheep-btn yangsheep-btn--ghost">
              <?php esc_html_e( '前往我的帳號', 'yangsheep-checkout-optimization' ); ?>
            </a>
          <?php endif; ?>
        </div>
      </section>

    <?php else : ?>

      <!-- 成功 Hero -->
      <section class="yangsheep-thankyou-hero yangsheep-thankyou-hero--success">
        <div class="yangsheep-thankyou-hero__icon" aria-hidden="true">
          <svg xmlns="http://www.w3.org/2000/svg" width="56" height="56" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <circle cx="12" cy="12" r="10"></circle>
            <polyline points="9 12 11 14 15 10"></polyline>
          </svg>
        </div>
        <h1 class="yangsheep-thankyou-hero__title">
          <?php
          /* translators: %s: customer first name */
          $greeting_name = $order->get_billing_first_name();
          if ( $greeting_name ) {
              echo esc_html( sprintf( __( '感謝您的訂購，%s！', 'yangsheep-checkout-optimization' ), $greeting_name ) );
          } else {
              esc_html_e( '感謝您的訂購！', 'yangsheep-checkout-optimization' );
          }
          ?>
        </h1>
        <p class="yangsheep-thankyou-hero__subtitle">
          <?php esc_html_e( '您的訂單已成功建立，我們會盡快為您處理。訂單詳情已寄送至您的 Email 信箱。', 'yangsheep-checkout-optimization' ); ?>
        </p>
      </section>

      <!-- Order Overview：4 欄卡片（編號 / 日期 / Email / 付款方式） -->
      <section class="yangsheep-thankyou-overview" aria-label="<?php esc_attr_e( '訂單概覽', 'yangsheep-checkout-optimization' ); ?>">
        <div class="yangsheep-thankyou-overview__grid">

          <div class="yangsheep-thankyou-overview__cell">
            <div class="yangsheep-thankyou-overview__label"><?php esc_html_e( '訂單編號', 'yangsheep-checkout-optimization' ); ?></div>
            <div class="yangsheep-thankyou-overview__value">#<?php echo esc_html( $order->get_order_number() ); ?></div>
          </div>

          <div class="yangsheep-thankyou-overview__cell">
            <div class="yangsheep-thankyou-overview__label"><?php esc_html_e( '訂購日期', 'yangsheep-checkout-optimization' ); ?></div>
            <div class="yangsheep-thankyou-overview__value"><?php echo esc_html( wc_format_datetime( $order->get_date_created() ) ); ?></div>
          </div>

          <?php if ( is_user_logged_in() && $order->get_user_id() === get_current_user_id() && $order->get_billing_email() ) : ?>
            <div class="yangsheep-thankyou-overview__cell yangsheep-thankyou-overview__cell--email">
              <div class="yangsheep-thankyou-overview__label"><?php esc_html_e( 'Email', 'yangsheep-checkout-optimization' ); ?></div>
              <div class="yangsheep-thankyou-overview__value"><?php echo esc_html( $order->get_billing_email() ); ?></div>
            </div>
          <?php endif; ?>

          <?php if ( $order->get_payment_method_title() ) : ?>
            <div class="yangsheep-thankyou-overview__cell">
              <div class="yangsheep-thankyou-overview__label"><?php esc_html_e( '付款方式', 'yangsheep-checkout-optimization' ); ?></div>
              <div class="yangsheep-thankyou-overview__value"><?php echo wp_kses_post( $order->get_payment_method_title() ); ?></div>
            </div>
          <?php endif; ?>

        </div>

        <!-- 總計 banner -->
        <div class="yangsheep-thankyou-total">
          <span class="yangsheep-thankyou-total__label"><?php esc_html_e( '訂單總計', 'yangsheep-checkout-optimization' ); ?></span>
          <span class="yangsheep-thankyou-total__value"><?php echo wp_kses_post( $order->get_formatted_order_total() ); ?></span>
        </div>
      </section>

    <?php endif; ?>

    <?php
    /**
     * 維持 WC 標準 hook：
     * - `woocommerce_thankyou_{gateway_id}` — 讓各 gateway 輸出額外訊息（例如 ATM 轉帳指示）
     * - `woocommerce_thankyou` — 觸發 WC_Shortcode_Checkout::order_details
     *    會渲染 order-details.php（訂單明細表）+ address.php（帳單/運送地址）
     */
    ?>
    <div class="yangsheep-thankyou-gateway-notice">
      <?php do_action( 'woocommerce_thankyou_' . $order->get_payment_method(), $order->get_id() ); ?>
    </div>

    <div class="yangsheep-thankyou-details">
      <?php do_action( 'woocommerce_thankyou', $order->get_id() ); ?>
    </div>

  <?php else : ?>

    <section class="yangsheep-thankyou-hero yangsheep-thankyou-hero--empty">
      <?php wc_get_template( 'checkout/order-received.php', array( 'order' => false ) ); ?>
    </section>

  <?php endif; ?>

</div>
