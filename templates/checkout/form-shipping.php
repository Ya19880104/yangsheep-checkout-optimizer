<?php
defined('ABSPATH') || exit;
?>
<div class="woocommerce-shipping-fields">
    <?php if ( WC()->cart->needs_shipping_address() ): ?>
        <h3 class="yangsheep-h3-title">收件人</h3>

        <!-- 同訂購人姓名電話 Checkbox -->
        <label class="yangsheep-same-as-billing" id="yangsheep_copy_billing_field" for="yangsheep_copy_billing">
            <input type="checkbox" class="woocommerce-form__input woocommerce-form__input-checkbox"
                   id="yangsheep_copy_billing" name="yangsheep_copy_billing" value="1" />
            <span><?php esc_html_e('同訂購人姓名電話', 'yangsheep-checkout-optimization'); ?></span>
        </label>
        
        <!-- 隱藏原本的 ship-to-different-address（但保留功能）-->
        <!-- 重要：必須使用 checkbox 類型且勾選，WooCommerce 使用 :checked 判斷是否使用 shipping 地址 -->
        <h3 id="ship-to-different-address" style="display:none !important;">
            <input type="checkbox" class="woocommerce-form__input woocommerce-form__input-checkbox" 
                   id="ship-to-different-address-checkbox" name="ship_to_different_address" value="1" checked="checked" />
        </h3>
        
        <div class="shipping_address">
            <?php do_action('woocommerce_before_checkout_shipping_form',$checkout); ?>
            <div class="woocommerce-shipping-fields__field-wrapper">
                <?php
                $fields = $checkout->get_checkout_fields('shipping');
                foreach($fields as $key=>$field) {
                    woocommerce_form_field($key,$field,$checkout->get_value($key));
                }
                ?>
            </div>
            <?php do_action('woocommerce_after_checkout_shipping_form',$checkout); ?>
        </div>
    <?php endif; ?>
</div>
<?php
// 取得 order 欄位
$order_fields = $checkout->get_checkout_fields('order');

// 檢查外掛的「訂單備註開關」設定
$order_notes_optional = get_option( 'yangsheep_checkout_order_note', 'no' ) === 'yes';

// 檢查 WooCommerce 原生設定
$wc_order_notes_enabled = apply_filters( 'woocommerce_enable_order_notes_field', 'yes' === get_option( 'woocommerce_enable_order_comments', 'yes' ) );

// 分離出 order_comments 和其他欄位
$order_comments_field = isset( $order_fields['order_comments'] ) ? $order_fields['order_comments'] : null;
$other_order_fields = array();
if ( ! empty( $order_fields ) ) {
    foreach ( $order_fields as $key => $field ) {
        if ( $key !== 'order_comments' ) {
            $other_order_fields[ $key ] = $field;
        }
    }
}

// 判斷是否需要顯示「其他內容」區塊
$has_order_comments = $order_comments_field && ( $order_notes_optional || $wc_order_notes_enabled );
$has_other_fields = ! empty( $other_order_fields );

// 只有有內容時才顯示區塊
if ( $has_order_comments || $has_other_fields ) :
?>
<div class="woocommerce-additional-fields">
    <h3 class="yangsheep-h3-title"><?php esc_html_e('其他內容', 'yangsheep-checkout-optimization'); ?></h3>
    <?php do_action('woocommerce_before_order_notes',$checkout); ?>

    <?php
    // 先渲染其他欄位（如身分證字號等），這些欄位不受備註開關控制
    if ( $has_other_fields ) :
    ?>
        <div class="woocommerce-additional-fields__other-wrapper">
            <?php
            foreach ( $other_order_fields as $key => $field ) :
                woocommerce_form_field( $key, $field, $checkout->get_value( $key ) );
            endforeach;
            ?>
        </div>
    <?php endif; ?>

    <?php
    // 渲染訂單備註欄位（受備註開關控制）
    if ( $has_order_comments ) :
    ?>
        <?php if ( $order_notes_optional ): ?>
            <!-- 訂單備註 Checkbox：用戶勾選才顯示備註欄位 -->
            <label class="yangsheep-order-notes-toggle" for="yangsheep_show_order_notes">
                <input type="checkbox" class="woocommerce-form__input woocommerce-form__input-checkbox"
                       id="yangsheep_show_order_notes" name="yangsheep_show_order_notes" value="1" />
                <span><?php esc_html_e('我需要填寫訂單備註', 'yangsheep-checkout-optimization'); ?></span>
            </label>
        <?php endif; ?>

        <div class="woocommerce-additional-fields__field-wrapper yangsheep-order-comments-wrapper" <?php if ( $order_notes_optional ) echo 'style="display:none;"'; ?>>
            <?php
            if ( $order_comments_field ) :
                woocommerce_form_field( 'order_comments', $order_comments_field, $checkout->get_value( 'order_comments' ) );
            else:
                // 如果沒有 order_comments 欄位，手動加入訂單備註
                woocommerce_form_field( 'order_comments', array(
                    'type'        => 'textarea',
                    'class'       => array( 'notes' ),
                    'label'       => __( '訂單備註', 'woocommerce' ),
                    'placeholder' => _x( '關於您的訂單的備註，例如：特別的配送須知', 'placeholder', 'woocommerce' ),
                ), $checkout->get_value( 'order_comments' ) );
            endif;
            ?>
        </div>
    <?php endif; ?>

    <?php do_action('woocommerce_after_order_notes',$checkout); ?>
</div>
<?php endif; ?>
