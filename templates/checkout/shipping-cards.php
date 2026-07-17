<?php
/**
 * 物流選擇卡片模板
 * 
 * 顯示卡片式物流選項，取代原本訂單表格中的物流區塊
 * 
 * @package YangSheep\CheckoutOptimizer
 * @version 1.4.17
 * @since 2026-01-07
 */

if ( ! defined( 'ABSPATH' ) ) exit;

use YangSheep\CheckoutOptimizer\Checkout\YSShippingCards;

// 取得物流包裹
$packages = WC()->shipping()->get_packages();

if ( empty( $packages ) ) {
    return;
}

?>

<div class="yangsheep-shipping-cards" id="yangsheep-shipping-cards">
    <h3 class="yangsheep-shipping-title"><?php esc_html_e( '選擇運送方式', 'yangsheep-checkout-optimization' ); ?></h3>
    
    <?php foreach ( $packages as $i => $package ) : 
        // 取得可用物流方式
        $available_methods = $package['rates'];
        
        // 取得已選擇的物流
        $chosen_method = YSShippingCards::get_chosen_shipping_method( $i );
        
        // 如果沒有可用物流
        if ( empty( $available_methods ) ) : ?>
            <div class="yangsheep-no-shipping-message">
                <?php echo wp_kses_post( apply_filters( 
                    'woocommerce_no_shipping_available_html', 
                    __( '目前沒有可用的配送方式，請確認地址是否正確。', 'yangsheep-checkout-optimization' ) 
                ) ); ?>
            </div>
        <?php else : ?>
            
            <div class="yangsheep-shipping-options" data-package-index="<?php echo esc_attr( $i ); ?>">
                <?php foreach ( $available_methods as $method ) : 
                    $method_id = $method->get_id();
                    $is_selected = ( $method_id === $chosen_method );
                    $card_class = 'yangsheep-shipping-card';
                    if ( $is_selected ) {
                        $card_class .= ' selected';
                    }
                ?>
                    <button type="button"
                            class="<?php echo esc_attr( $card_class ); ?>"
                            role="radio"
                            aria-checked="<?php echo $is_selected ? 'true' : 'false'; ?>"
                            data-package-index="<?php echo esc_attr( $i ); ?>"
                            data-method-id="<?php echo esc_attr( $method_id ); ?>">
                        <span class="yangsheep-shipping-card-inner">
                            <span class="yangsheep-shipping-radio-indicator"></span>
                            <span class="yangsheep-shipping-info">
                                <span class="yangsheep-shipping-label">
                                    <?php echo esc_html( $method->get_label() ); ?>
                                </span>
                            </span>
                            <span class="yangsheep-shipping-price">
                                <?php echo wp_kses_post( YSShippingCards::format_shipping_cost( $method ) ); ?>
                            </span>
                        </span>
                    </button>
                <?php endforeach; ?>
            </div>
            
        <?php endif; ?>
    <?php endforeach; ?>
</div>
