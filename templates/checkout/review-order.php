<?php
/**
 * Review order - 商品明細
 *
 * 使用現代 div + flexbox 佈局取代老舊 table
 * 
 * @package YANGSHEEP_Checkout_Optimization
 * @version 1.3.1
 * @since 2026-01-08
 */

defined( 'ABSPATH' ) || exit;
?>

<div class="yangsheep-order-items">
	<!-- 表頭 -->
	<div class="yangsheep-order-header">
		<div class="col-product"><?php esc_html_e( '商品', 'yangsheep-checkout-optimization' ); ?></div>
		<div class="col-price"><?php esc_html_e( '單價', 'yangsheep-checkout-optimization' ); ?></div>
		<div class="col-qty"><?php esc_html_e( '數量', 'yangsheep-checkout-optimization' ); ?></div>
		<div class="col-subtotal"><?php esc_html_e( '小計', 'yangsheep-checkout-optimization' ); ?></div>
		<div class="col-remove"></div>
	</div>
	
	<!-- 商品列表 -->
	<div class="yangsheep-order-body">
		<?php
		do_action( 'woocommerce_review_order_before_cart_contents' );

		foreach ( WC()->cart->get_cart() as $cart_item_key => $cart_item ) {
			$_product = apply_filters( 'woocommerce_cart_item_product', $cart_item['data'], $cart_item, $cart_item_key );
			
			if ( $_product && $_product->exists() && $cart_item['quantity'] > 0 && apply_filters( 'woocommerce_checkout_cart_item_visible', true, $cart_item, $cart_item_key ) ) {
				$product_name = apply_filters( 'woocommerce_cart_item_name', $_product->get_name(), $cart_item, $cart_item_key );
				$thumbnail    = apply_filters( 'woocommerce_cart_item_thumbnail', $_product->get_image( 'woocommerce_thumbnail' ), $cart_item, $cart_item_key );
				$product_price = apply_filters( 'woocommerce_cart_item_price', WC()->cart->get_product_price( $_product ), $cart_item, $cart_item_key );
				$product_subtotal = apply_filters( 'woocommerce_cart_item_subtotal', WC()->cart->get_product_subtotal( $_product, $cart_item['quantity'] ), $cart_item, $cart_item_key );
				$quantity     = $cart_item['quantity'];
				$max_quantity = $_product->get_max_purchase_quantity();
				?>
				<div class="yangsheep-order-item <?php echo esc_attr( apply_filters( 'woocommerce_cart_item_class', 'cart_item', $cart_item, $cart_item_key ) ); ?>" data-cart-item-key="<?php echo esc_attr( $cart_item_key ); ?>">
					<!-- 商品資訊 -->
					<div class="col-product">
						<div class="product-image">
							<?php echo $thumbnail; // phpcs:ignore ?>
						</div>
						<div class="product-info">
							<div class="product-name"><?php echo wp_kses_post( $product_name ); ?></div>
							<?php echo wc_get_formatted_cart_item_data( $cart_item ); // phpcs:ignore ?>
						</div>
					</div>
					
					<!-- 單價 -->
					<div class="col-price" data-label="<?php esc_attr_e( '單價', 'yangsheep-checkout-optimization' ); ?>">
						<?php echo $product_price; // phpcs:ignore ?>
					</div>
					
					<!-- 數量控制 -->
					<div class="col-qty" data-label="<?php esc_attr_e( '數量', 'yangsheep-checkout-optimization' ); ?>">
						<div class="yangsheep-quantity-control" data-cart-key="<?php echo esc_attr( $cart_item_key ); ?>" data-max="<?php echo esc_attr( $max_quantity > 0 ? $max_quantity : '' ); ?>">
							<button type="button" class="yangsheep-qty-btn yangsheep-qty-minus" aria-label="<?php esc_attr_e( '減少數量', 'yangsheep-checkout-optimization' ); ?>">−</button>
							<span class="yangsheep-qty-value"><?php echo esc_html( $quantity ); ?></span>
							<button type="button" class="yangsheep-qty-btn yangsheep-qty-plus" aria-label="<?php esc_attr_e( '增加數量', 'yangsheep-checkout-optimization' ); ?>">+</button>
						</div>
					</div>
					
					<!-- 小計 -->
					<div class="col-subtotal" data-label="<?php esc_attr_e( '小計', 'yangsheep-checkout-optimization' ); ?>">
						<?php echo $product_subtotal; // phpcs:ignore ?>
					</div>
					
					<!-- 刪除 -->
					<div class="col-remove">
						<button type="button" class="yangsheep-remove-item" data-cart-key="<?php echo esc_attr( $cart_item_key ); ?>" aria-label="<?php esc_attr_e( '移除商品', 'yangsheep-checkout-optimization' ); ?>">×</button>
					</div>
				</div>
				<?php
			}
		}

		do_action( 'woocommerce_review_order_after_cart_contents' );
		?>
	</div>
</div>

<!--
	注意：shipping hooks (woocommerce_review_order_before/after_shipping)
	已經在 shipping-cards.php 透過 yangsheep_before/after_shipping_cards 執行，
	不要在這裡重複執行，否則會導致超商選擇器渲染到錯誤位置。
-->
<!-- 保留其他 HOOK，供第三方外掛使用（如費用相關）但不顯示 -->
<div class="yangsheep-order-hooks" style="display:none;">
	<?php foreach ( WC()->cart->get_fees() as $fee ) : ?>
		<?php do_action( 'woocommerce_review_order_before_fee', $fee ); ?>
		<?php do_action( 'woocommerce_review_order_after_fee', $fee ); ?>
	<?php endforeach; ?>
	<?php do_action( 'woocommerce_review_order_before_order_total' ); ?>
	<?php do_action( 'woocommerce_review_order_after_order_total' ); ?>
</div>
