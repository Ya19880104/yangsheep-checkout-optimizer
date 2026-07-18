<?php
/**
 * Plugin Name:     YANGSHEEP 結帳強化
 * Plugin URI:      https://yangsheep.com.tw
 * Description:     強化 WooCommerce 結帳頁面、我的帳號、訂單頁面；包含自訂佈局、TWzipcode 台灣郵遞區號、後台可調色和圓角、物流卡片選擇、第三方物流相容（綠界 ECPay / PayNow 超取）。
 * Version:           1.7.1
 * Author:          羊羊數位科技有限公司
 * Author URI:      https://yangsheep.com.tw
 * Text Domain:     yangsheep-checkout-optimization
 * Domain Path:     /languages
 */

if ( ! defined( 'ABSPATH' ) ) exit;

define( 'YANGSHEEP_CHECKOUT_OPTIMIZATION_VERSION', '1.7.1' );
define( 'YANGSHEEP_CHECKOUT_OPTIMIZATION_DIR', plugin_dir_path( __FILE__ ) );
define( 'YANGSHEEP_CHECKOUT_OPTIMIZATION_URL', plugin_dir_url( __FILE__ ) );
define( 'YANGSHEEP_CHECKOUT_OPTIMIZATION_FILE', __FILE__ );

// 定義常數供其他類別使用
define( 'YANGSHEEP_CHECKOUT_URL', YANGSHEEP_CHECKOUT_OPTIMIZATION_URL );
define( 'YANGSHEEP_CHECKOUT_VERSION', YANGSHEEP_CHECKOUT_OPTIMIZATION_VERSION );

/**
 * Return a cache-busting asset version tied to the deployed file.
 *
 * @param string $relative_path Plugin-relative asset path.
 * @return string
 */
function yangsheep_checkout_asset_version( $relative_path ) {
    $path  = YANGSHEEP_CHECKOUT_OPTIMIZATION_DIR . ltrim( (string) $relative_path, '/\\' );
    $mtime = is_file( $path ) ? filemtime( $path ) : false;

    return false === $mtime
        ? YANGSHEEP_CHECKOUT_OPTIMIZATION_VERSION
        : YANGSHEEP_CHECKOUT_OPTIMIZATION_VERSION . '.' . (string) $mtime;
}

// WooCommerce 未啟用提示
function yangsheep_checkout_optimizer_wc_missing_notice() {
    echo '<div class="notice notice-error"><p>';
    echo esc_html__( 'YANGSHEEP 結帳強化外掛需要 WooCommerce 才能運作。請先安裝並啟用 WooCommerce。', 'yangsheep-checkout-optimization' );
    echo '</p></div>';
}

// Composer 自動載入（載入 hub-client）
if ( file_exists( YANGSHEEP_CHECKOUT_OPTIMIZATION_DIR . 'vendor/autoload.php' ) ) {
    require_once YANGSHEEP_CHECKOUT_OPTIMIZATION_DIR . 'vendor/autoload.php';
}

// 永遠註冊自身 namespace 的 Fallback PSR-4（vendor/autoload.php 不含自身 namespace）
spl_autoload_register( function ( $class ) {
    $prefix   = 'YangSheep\\CheckoutOptimizer\\';
    $base_dir = YANGSHEEP_CHECKOUT_OPTIMIZATION_DIR . 'src/';

    $len = strlen( $prefix );
    if ( strncmp( $prefix, $class, $len ) !== 0 ) {
        return;
    }

    $relative_class = substr( $class, $len );
    $file = $base_dir . str_replace( '\\', '/', $relative_class ) . '.php';

    if ( file_exists( $file ) ) {
        require_once $file;
    }
} );

// 註冊到 YS Plugin Hub Client — 延遲到 plugins_loaded（hub-client 可能在本外掛之後載入）
add_action( 'plugins_loaded', function () {
    if ( class_exists( '\YangSheep\PluginHubClient\YSPluginHubClient' ) ) {
        \YangSheep\PluginHubClient\YSPluginHubClient::register( array(
            'slug'        => 'yangsheep-checkout-optimizer',
            'version'     => YANGSHEEP_CHECKOUT_OPTIMIZATION_VERSION,
            'plugin_file' => __FILE__,
            'name'        => 'YANGSHEEP 結帳強化',
        ) );
    }
}, 5 ); // priority 5 — 在 hub-client boot (10) 之前

// use 語句放在檔案頂層仍然安全（PSR-4 autoloader 已註冊，class 只在實際使用時載入）
use YangSheep\CheckoutOptimizer\Settings\YSSettingsManager;
use YangSheep\CheckoutOptimizer\Settings\YSSettingsTableMaker;
use YangSheep\CheckoutOptimizer\Settings\YSSettingsMigrator;
use YangSheep\CheckoutOptimizer\Admin\YSCheckoutSettings;
use YangSheep\CheckoutOptimizer\Checkout\YSCheckoutCustomizer;
use YangSheep\CheckoutOptimizer\Checkout\YSCheckoutFields;
use YangSheep\CheckoutOptimizer\Checkout\YSCheckoutLayout;
use YangSheep\CheckoutOptimizer\Checkout\YSCheckoutSidebar;
use YangSheep\CheckoutOptimizer\Checkout\YSShippingCards;
use YangSheep\CheckoutOptimizer\Order\YSOrderEnhancer;
use YangSheep\CheckoutOptimizer\Compat\YSThirdPartyShippingCompat;
use YangSheep\CheckoutOptimizer\Compat\YSWPLoyaltyIntegration;
use YangSheep\CheckoutOptimizer\Compat\YSYithCouponDisplay;
use YangSheep\CheckoutOptimizer\Compat\YSYithPointsIntegration;

// 外掛啟用時建立資料表
register_activation_hook( __FILE__, 'yangsheep_checkout_optimizer_activate' );
function yangsheep_checkout_optimizer_activate() {
    // 建立設定資料表（不依賴 WooCommerce）
    try {
        $table_maker = YSSettingsTableMaker::instance();
        $table_maker->create_table();
    } catch ( \Throwable $e ) {
        // 靜默處理 — 表可能已存在或 WC 尚未載入
    }

    // 建立 Hub Client 資料表
    if ( class_exists( '\YangSheep\PluginHubClient\Database\YSHubClientTableMaker' ) ) {
        $hub_table = \YangSheep\PluginHubClient\Database\YSHubClientTableMaker::instance();
        if ( $hub_table->schema_update_required() ) {
            $hub_table->create_table();
        }
    }

    // 自動遷移（需要 WooCommerce）
    if ( class_exists( 'WooCommerce' ) ) {
        $migrator = YSSettingsMigrator::instance();
        if ( $migrator->migration_required() ) {
            $migrator->migrate();
        }
    }
}

// 載入核心（PSR-4 自動載入，不需要手動 require）
add_action( 'plugins_loaded', function(){
    if ( ! class_exists( 'WooCommerce' ) ) {
        add_action( 'admin_notices', 'yangsheep_checkout_optimizer_wc_missing_notice' );
        return;
    }
});

// 啟動設定與自訂器
add_action( 'init', function(){
    if ( ! class_exists( 'WooCommerce' ) ) {
        return;
    }

    YSCheckoutSettings::get_instance();
    YSCheckoutCustomizer::get_instance();
    YSCheckoutFields::get_instance();
    YSShippingCards::get_instance();
    YSCheckoutLayout::get_instance();
    YSCheckoutSidebar::get_instance();
    YSOrderEnhancer::get_instance();
    YSThirdPartyShippingCompat::get_instance();
    YSWPLoyaltyIntegration::get_instance();
    YSYithCouponDisplay::get_instance();
    YSYithPointsIntegration::get_instance();
});

// 前端 CSS/JS
add_action( 'wp_enqueue_scripts', function(){
    if ( ! function_exists( 'is_checkout' ) ) {
        return;
    }

    if ( function_exists( 'is_cart' ) && is_cart() ) {
        wp_enqueue_style( 'yangsheep-cart', YANGSHEEP_CHECKOUT_OPTIMIZATION_URL . 'assets/css/yangsheep-cart.css', [], YANGSHEEP_CHECKOUT_OPTIMIZATION_VERSION );
    }

    // 結帳頁面專用 CSS/JS
    if ( is_checkout() && ! is_wc_endpoint_url() ) {
        wp_enqueue_style( 'yangsheep-checkout-optimization', YANGSHEEP_CHECKOUT_OPTIMIZATION_URL . 'assets/css/yangsheep-checkout.css', [], yangsheep_checkout_asset_version( 'assets/css/yangsheep-checkout.css' ), 'not all' );
        wp_enqueue_script( 'jquery-twzipcode', YANGSHEEP_CHECKOUT_OPTIMIZATION_URL . 'assets/js/jquery.twzipcode.min.js', [ 'jquery' ], '1.7.12', true );
        wp_enqueue_script( 'yangsheep-checkout-custom', YANGSHEEP_CHECKOUT_OPTIMIZATION_URL . 'assets/js/yangsheep-checkout.js', [ 'jquery', 'jquery-twzipcode', 'wc-checkout' ], yangsheep_checkout_asset_version( 'assets/js/yangsheep-checkout.js' ), true );

        // 傳遞超取物流方式清單到前端
        $cvs_methods = YSSettingsManager::get( 'yangsheep_cvs_shipping_methods', array() );
        wp_localize_script( 'yangsheep-checkout-custom', 'yangsheep_checkout_params', array(
            'cvs_shipping_methods' => is_array( $cvs_methods ) ? $cvs_methods : array(),
            'nonce'                => wp_create_nonce( 'yangsheep_checkout_nonce' ),
        ) );

        // 物流卡片 CSS/JS（僅結帳頁主表單）
        wp_enqueue_style( 'yangsheep-shipping-cards', YANGSHEEP_CHECKOUT_OPTIMIZATION_URL . 'assets/css/yangsheep-shipping-cards.css', [ 'yangsheep-checkout-optimization' ], yangsheep_checkout_asset_version( 'assets/css/yangsheep-shipping-cards.css' ), 'not all' );
        wp_enqueue_script( 'yangsheep-shipping-cards', YANGSHEEP_CHECKOUT_OPTIMIZATION_URL . 'assets/js/yangsheep-shipping-cards.js', [ 'jquery' ], yangsheep_checkout_asset_version( 'assets/js/yangsheep-shipping-cards.js' ), true );

        // 側邊欄樣式（版面重排由主要 checkout 腳本負責）
        wp_enqueue_style( 'yangsheep-sidebar', YANGSHEEP_CHECKOUT_OPTIMIZATION_URL . 'assets/css/yangsheep-sidebar.css', [ 'yangsheep-checkout-optimization' ], yangsheep_checkout_asset_version( 'assets/css/yangsheep-sidebar.css' ), 'not all' );

        // 第三方外掛相容 CSS
        wp_enqueue_style( 'yangsheep-compatibility', YANGSHEEP_CHECKOUT_OPTIMIZATION_URL . 'assets/css/yangsheep-compatibility.css', [ 'yangsheep-shipping-cards' ], yangsheep_checkout_asset_version( 'assets/css/yangsheep-compatibility.css' ), 'not all' );
    }

    // 我的帳號頁面及訂單明細（根據設定決定是否載入視覺樣式）
    if ( function_exists( 'is_account_page' ) && is_account_page() ) {
        // 只有啟用「我的帳號視覺」時才載入樣式
        if ( YSSettingsManager::get( 'yangsheep_myaccount_visual', 'no' ) === 'yes' ) {
            wp_enqueue_style( 'yangsheep-myaccount',  YANGSHEEP_CHECKOUT_OPTIMIZATION_URL . 'assets/css/yangsheep-myaccount.css', [], YANGSHEEP_CHECKOUT_OPTIMIZATION_VERSION );
            wp_enqueue_style( 'yangsheep-order',      YANGSHEEP_CHECKOUT_OPTIMIZATION_URL . 'assets/css/yangsheep-order.css', [], YANGSHEEP_CHECKOUT_OPTIMIZATION_VERSION );
        }

        // 地址編輯頁面載入 TWzipcode（台灣化欄位啟用時）
        if ( is_wc_endpoint_url( 'edit-address' ) && YSSettingsManager::get( 'yangsheep_checkout_tw_fields', 'no' ) === 'yes' ) {
            wp_enqueue_script( 'jquery-twzipcode', YANGSHEEP_CHECKOUT_OPTIMIZATION_URL . 'assets/js/jquery.twzipcode.min.js', [ 'jquery' ], '1.7.12', true );
            wp_enqueue_script( 'yangsheep-myaccount-address', YANGSHEEP_CHECKOUT_OPTIMIZATION_URL . 'assets/js/yangsheep-myaccount-address.js', [ 'jquery', 'jquery-twzipcode' ], YANGSHEEP_CHECKOUT_OPTIMIZATION_VERSION, true );
        }
    }
});

// 「我的帳號視覺」模板（v1.7.0 保留的唯一模板覆寫，且必須設定開啟才生效）。
// checkout/* 一律走 Woo 核心模板 — 不做任何無條件覆寫。
// myaccount/* 與 order/* 是與 yangsheep-myaccount.css / yangsheep-order.css 配對的
// 選用視覺系統；沒有模板只掛 CSS 會讓原生 markup 大跑版，因此兩者同開同關。
add_filter( 'woocommerce_locate_template', function( $template, $template_name, $template_path ) {
    if ( strpos( $template_name, 'myaccount/' ) !== 0 && strpos( $template_name, 'order/' ) !== 0 ) {
        return $template; // checkout 等其他模板永遠用 Woo 核心
    }

    if ( YSSettingsManager::get( 'yangsheep_myaccount_visual', 'no' ) !== 'yes' ) {
        return $template;
    }

    // order/* 只在「我的帳號」頁面覆寫（view-order 等，與 yangsheep-order.css 配對載入）。
    // order-received / order-pay 等結帳端點不是帳號頁：用 Woo 核心模板，
    // 避免陳舊模板 + 無配對 CSS 的裸樣式（CODEX P1：真單 #12148 實證）。
    if (
        strpos( $template_name, 'order/' ) === 0
        && ( ! function_exists( 'is_account_page' ) || ! is_account_page() )
    ) {
        return $template;
    }

    $plugin_template = YANGSHEEP_CHECKOUT_OPTIMIZATION_DIR . 'templates/' . $template_name;
    return file_exists( $plugin_template ) ? $plugin_template : $template;
}, 10, 3 );

// 保留 WooCommerce 原生模板、標準 checkout hooks、付款與折扣入口。
// YS 只有在前端 layout 初始化成功後才隱藏原生折扣入口；JS 失敗時仍可使用 Woo 原生流程。
add_action( 'woocommerce_before_checkout_form', 'yangsheep_checkout_native_coupon_fallback', 9 );
function yangsheep_checkout_native_coupon_fallback() {
    if ( ! function_exists( 'woocommerce_checkout_coupon_form' ) ) {
        return;
    }

    // 部分主題會移除 Woo 的 callback。此時補回同一個核心表單；
    // callback 仍存在時不輸出，避免與 Woo priority 10 的原生入口重複。
    if ( false === has_action( 'woocommerce_before_checkout_form', 'woocommerce_checkout_coupon_form' ) ) {
        woocommerce_checkout_coupon_form();
    }
}

add_action( 'yangsheep_coupon', 'yangsheep_checkout_coupon_form_custom' );
function yangsheep_checkout_coupon_form_custom(){
    // id/name 用 ys_ 前綴：Woo 原生 coupon 表單（隱藏但保留在 DOM 作 fail-open 後備）
    // 已占用 #coupon_code / name=coupon_code，重複 id 會影響 label 與第三方 selector
    echo '<div class="yangsheep_checkout_coupon coupon-form">'
       . '<div class="yangsheep-inputform form-row woocommerce-validated">'
       . '<label class="screen-reader-text" for="ys_coupon_code">'.esc_html__('Coupon','yangsheep-checkout-optimization').'</label>'
       . '<input type="text" name="ys_coupon_code" class="input-text" placeholder="'.esc_attr__('Coupon','yangsheep-checkout-optimization').'" id="ys_coupon_code" value="">'
       . '</div>'
       . '<div class="yangsheep-coupon-button form-row">'
       . '<button type="button" class="button" name="apply_coupon" value="'.esc_attr__('使用折扣代碼','yangsheep-checkout-optimization').'">'.esc_html__('使用折扣代碼','yangsheep-checkout-optimization').'</button>'
       . '</div><div class="clear"></div></div>';
}
// Ajax 優惠券（selector 全部限定在 .yangsheep_checkout_coupon 範圍內）
add_action( 'wp_footer', function(){
    if ( ! function_exists( 'is_checkout' ) ) {
        return;
    }
    if ( is_checkout() && ! is_wc_endpoint_url() ) { ?>
<script>jQuery(function($){if(!window.wc_checkout_params)return;var cc='';var ysNonce=(window.yangsheep_checkout_params&&yangsheep_checkout_params.nonce)?yangsheep_checkout_params.nonce:'';var $scope=$('.yangsheep_checkout_coupon');$scope.on('input','#ys_coupon_code',function(){cc=$(this).val();});$scope.on('click','button[name=apply_coupon]',function(){$.post(wc_checkout_params.ajax_url,{action:'apply_checkout_coupon',coupon_code:cc,nonce:ysNonce},function(r){$(document.body).trigger('update_checkout');$('.woocommerce-error,.woocommerce-message').remove();$scope.find('#ys_coupon_code').val('');cc='';$('.woocommerce-notices-wrapper').html(r);});});});</script>
<?php } } );
add_action('wp_ajax_apply_checkout_coupon','yangsheep_apply_checkout_coupon_ajax');
add_action('wp_ajax_nopriv_apply_checkout_coupon','yangsheep_apply_checkout_coupon_ajax');
function yangsheep_apply_checkout_coupon_ajax(){
    check_ajax_referer( 'yangsheep_checkout_nonce', 'nonce' );
    if ( ! WC()->cart ) {
        wp_send_json_error( array( 'message' => 'Cart not available' ) );
    }
    if ( ! empty( $_POST['coupon_code'] ) ) {
        $coupon_code = wc_format_coupon_code( wp_unslash( $_POST['coupon_code'] ) );
        WC()->cart->apply_coupon( $coupon_code );
    } else {
        wc_add_notice( WC_Coupon::get_generic_coupon_error( WC_Coupon::E_WC_COUPON_PLEASE_ENTER ), 'error' );
    }
    wc_print_notices();
    wp_die();
}

// AJAX: 更新購物車數量
add_action('wp_ajax_yangsheep_update_cart_qty', 'yangsheep_update_cart_qty_ajax');
add_action('wp_ajax_nopriv_yangsheep_update_cart_qty', 'yangsheep_update_cart_qty_ajax');
function yangsheep_update_cart_qty_ajax() {
    check_ajax_referer( 'yangsheep_checkout_nonce', 'nonce' );
    if ( ! WC()->cart ) {
        wp_send_json_error( array( 'message' => '購物車不可用' ) );
    }
    $cart_item_key = isset($_POST['cart_item_key']) ? sanitize_text_field($_POST['cart_item_key']) : '';
    $quantity = isset($_POST['quantity']) ? absint($_POST['quantity']) : 0;

    if ( $cart_item_key && $quantity > 0 ) {
        $result = WC()->cart->set_quantity( $cart_item_key, $quantity, true );
        if ( false === $result ) {
            wp_send_json_error( array( 'message' => '更新數量失敗' ) );
        }
        wp_send_json_success( array( 'message' => '數量已更新' ) );
    } else {
        wp_send_json_error( array( 'message' => '無效的請求' ) );
    }
}

// AJAX: 刪除購物車商品
add_action('wp_ajax_yangsheep_remove_cart_item', 'yangsheep_remove_cart_item_ajax');
add_action('wp_ajax_nopriv_yangsheep_remove_cart_item', 'yangsheep_remove_cart_item_ajax');
function yangsheep_remove_cart_item_ajax() {
    check_ajax_referer( 'yangsheep_checkout_nonce', 'nonce' );
    if ( ! WC()->cart ) {
        wp_send_json_error( array( 'message' => '購物車不可用' ) );
    }
    $cart_item_key = isset($_POST['cart_item_key']) ? sanitize_text_field($_POST['cart_item_key']) : '';

    if ( $cart_item_key ) {
        $result = WC()->cart->remove_cart_item( $cart_item_key );
        if ( false === $result ) {
            wp_send_json_error( array( 'message' => '移除商品失敗' ) );
        }
        wp_send_json_success( array( 'message' => '商品已移除' ) );
    } else {
        wp_send_json_error( array( 'message' => '無效的請求' ) );
    }
}

// ===== 自訂商品項目輸出（繞過主題） =====
add_action('yangsheep_order_items', 'yangsheep_render_order_items');
function yangsheep_render_order_items() {
    $cart = WC()->cart->get_cart();
    
    if (empty($cart)) {
        echo '<p class="yangsheep-empty-cart">' . esc_html__('購物車是空的', 'yangsheep-checkout-optimization') . '</p>';
        return;
    }
    
    echo '<div class="yangsheep-order-items">';
    
    foreach ($cart as $cart_item_key => $cart_item) {
        $_product = $cart_item['data'];
        $quantity = $cart_item['quantity'];
        $max_qty = $_product->get_max_purchase_quantity();
        $thumbnail = $_product->get_image(array(50, 50));
        // v1.6.26：走 WC 標準 filter，允許變體屬性 <span> 等安全 HTML 正確渲染
        $product_name = apply_filters( 'woocommerce_cart_item_name', $_product->get_name(), $cart_item, $cart_item_key );
        $price = WC()->cart->get_product_price($_product);
        $subtotal = WC()->cart->get_product_subtotal($_product, $quantity);
        ?>
        <div class="yangsheep-order-item" data-cart-key="<?php echo esc_attr($cart_item_key); ?>">
            <button type="button" class="yangsheep-remove-item" data-cart-key="<?php echo esc_attr($cart_item_key); ?>" aria-label="<?php esc_attr_e('移除商品', 'yangsheep-checkout-optimization'); ?>">×</button>
            <div class="yangsheep-item-content">
                <div class="yangsheep-item-image"><?php echo $thumbnail; ?></div>
                <div class="yangsheep-item-info">
                    <div class="yangsheep-item-name"><a href="<?php echo esc_url($_product->get_permalink()); ?>"><?php echo wp_kses_post($product_name); ?></a></div>
                    <div class="yangsheep-item-price"><?php echo $price; ?></div>
                </div>
                <div class="yangsheep-item-qty">
                    <?php if ($_product->is_sold_individually()) : ?>
                        <span class="yangsheep-qty-value">1</span>
                    <?php else : ?>
                        <div class="yangsheep-quantity-control" data-cart-key="<?php echo esc_attr($cart_item_key); ?>" data-max="<?php echo esc_attr($max_qty > 0 ? $max_qty : ''); ?>">
                            <button type="button" class="yangsheep-qty-btn yangsheep-qty-minus">−</button>
                            <span class="yangsheep-qty-value"><?php echo esc_html($quantity); ?></span>
                            <button type="button" class="yangsheep-qty-btn yangsheep-qty-plus">+</button>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="yangsheep-item-subtotal"><?php echo $subtotal; ?></div>
            </div>
        </div>
        <?php
    }
    
    echo '</div>';
}

// 註冊 AJAX Fragment 更新自訂商品項目
add_filter('woocommerce_update_order_review_fragments', 'yangsheep_order_items_fragment');
function yangsheep_order_items_fragment($fragments) {
    ob_start();
    ?>
    <div id="yangsheep_order_items" class="yangsheep-order-items-container">
        <?php yangsheep_render_order_items(); ?>
    </div>
    <?php
    $fragments['#yangsheep_order_items'] = ob_get_clean();
    return $fragments;
}

// 動態 CSS 變數與套用
add_action('wp_head',function(){
    if ( ! function_exists( 'is_checkout' ) || ! is_checkout() || is_wc_endpoint_url() ) {
        return;
    }
    /**
     * 莫蘭迪配色方案
     * 主色：淡藍色 #8fa8b8 (按鈕、選中狀態)
     * 輔色：淡綠色 #9db4a0 (背景、高亮)
     * 背景淡藍：#e8eef2
     * 背景淡綠：#e8f0ea
     * 邊框色：#c5d1d8
     */

    // 讀取所有設定值 - 莫蘭迪淡藍主色 + 淡綠輔色
    $btn_bg = YSSettingsManager::get('yangsheep_checkout_button_bg_color');           // 主色：莫蘭迪淡藍
    $btn_txt = YSSettingsManager::get('yangsheep_checkout_button_text_color');
    $btn_hover_bg = YSSettingsManager::get('yangsheep_checkout_button_hover_bg');     // 主色深
    $btn_hover_txt = YSSettingsManager::get('yangsheep_checkout_button_hover_text');
    $sec_bd = YSSettingsManager::get('yangsheep_checkout_section_border_color');      // 邊框淡藍灰
    $sec_bg = YSSettingsManager::get('yangsheep_checkout_section_bg_color');
    $fld_bg = YSSettingsManager::get('yangsheep_checkout_form_field_bg_color');       // 欄位背景淡藍
    $fld_bd = YSSettingsManager::get('yangsheep_checkout_form_field_border_color');
    $link = YSSettingsManager::get('yangsheep_checkout_link_color');                  // 連結色
    $cp_bg = YSSettingsManager::get('yangsheep_checkout_coupon_block_bg_color');      // 折扣區淡藍
    $or_bg = YSSettingsManager::get('yangsheep_checkout_order_review_bg_color');      // 訂單區淡藍
    $rad = YSSettingsManager::get('yangsheep_checkout_block_border_radius');
    $ship_radio = YSSettingsManager::get('yangsheep_shipping_card_radio_color');      // 主色
    $ship_border = YSSettingsManager::get('yangsheep_shipping_card_border_active');   // 主色
    $sidebar_bg = YSSettingsManager::get('yangsheep_sidebar_bg_color');

    // 新增配色設定 - 統一淡藍色系
    $payment_bg = YSSettingsManager::get('yangsheep_checkout_payment_bg_color');      // 淡藍背景
    $order_items_bg = YSSettingsManager::get('yangsheep_checkout_order_items_bg_color'); // 淡藍背景
    $ship_card_bg = YSSettingsManager::get('yangsheep_shipping_card_bg_color');
    $ship_card_active = YSSettingsManager::get('yangsheep_shipping_card_bg_active');  // 選中淡藍

    // 付款方式卡片設定
    $pm_bg = YSSettingsManager::get('yangsheep_payment_method_bg');
    $pm_bg_active = YSSettingsManager::get('yangsheep_payment_method_bg_active');
    $pm_border = YSSettingsManager::get('yangsheep_payment_method_border');
    $pm_border_active = YSSettingsManager::get('yangsheep_payment_method_border_active');
    $pm_desc_bg = YSSettingsManager::get('yangsheep_payment_method_desc_bg');

    echo '<style>';
    // CSS 變數定義
    echo 'body.ys-checkout-enhanced{';
    echo "--theme-button-background-initial-color:{$btn_bg};";
    echo "--theme-button-text-initial-color:{$btn_txt};";
    echo "--theme-button-hover-bg:{$btn_hover_bg};";
    echo "--theme-button-hover-text:{$btn_hover_txt};";
    echo "--theme-section-border-color:{$sec_bd};";
    echo "--section-bg-color:{$sec_bg};";
    echo "--form-field-bg-color:{$fld_bg};";
    echo "--theme-form-field-border-initial-color:{$fld_bd};";
    echo "--theme-link-color:{$link};";
    echo "--block-border-radius:{$rad};";
    echo "--yangsheep-shipping-radio-color:{$ship_radio};";
    echo "--yangsheep-shipping-border-active:{$ship_border};";
    echo "--yangsheep-sidebar-bg:{$sidebar_bg};";
    // 新增 CSS 變數
    echo "--yangsheep-payment-bg:{$payment_bg};";
    echo "--yangsheep-order-items-bg:{$order_items_bg};";
    echo "--yangsheep-shipping-card-bg:{$ship_card_bg};";
    echo "--yangsheep-shipping-card-bg-active:{$ship_card_active};";
    echo '}';

    // Order Review 區塊（使用頁面前綴提高優先級）
    echo ".yangsheep-design-checkout-page .ct-order-review {";
    echo "background-color:{$or_bg};";
    echo "border-radius:{$rad};";
    echo "border:2px solid {$sec_bd};";
    echo "padding:20px;";
    echo "margin-bottom:0;";
    echo '}';

    // 折扣代碼區塊
    echo ".yangsheep-design-checkout-page .yangsheep-coupon-block {";
    echo "background-color:{$cp_bg};";
    echo "border-radius:{$rad};";
    echo "border:2px solid {$sec_bd};";
    echo "padding:20px;";
    echo '}';

    // 付款區塊 - 外層容器 (.yangsheep-payment)
    echo ".yangsheep-design-checkout-page .yangsheep-payment {";
    echo "background-color:{$payment_bg};";
    echo "border-radius:{$rad};";
    echo "padding:20px;";
    echo '}';
    // 付款區塊 - 內層重設
    echo ".yangsheep-design-checkout-page .yangsheep-payment #payment,";
    echo ".yangsheep-design-checkout-page .yangsheep-payment .woocommerce-checkout-payment {";
    echo "background:transparent;";
    echo "padding:0;margin:0;";
    echo '}';

    // 付款方式列表 - ul 增加 gap（使用 #payment ID 提高優先級以覆蓋 WooCommerce）
    echo ".yangsheep-design-checkout-page #payment .wc_payment_methods.payment_methods {";
    echo "display:flex;flex-direction:column;gap:8px;";
    echo "list-style:none;padding:0;margin:0;";
    echo '}';

    // 付款方式卡片 - li 樣式（類似物流卡片）
    // 使用 !important 確保覆蓋 WooCommerce 和佈景主題樣式
    echo ".yangsheep-design-checkout-page #payment ul.payment_methods>li.wc_payment_method {";
    echo "background-color:{$pm_bg} !important;";
    echo "border:2px solid {$pm_border} !important;";
    echo "border-radius:{$rad} !important;";
    echo "padding:12px 15px !important;margin:0 !important;";
    echo "transition:all 0.2s ease;";
    echo '}';

    // 付款方式卡片 - 選中狀態（使用 JS 加上的 .ys-payment-selected class）
    echo ".yangsheep-design-checkout-page #payment ul.payment_methods>li.wc_payment_method.ys-payment-selected {";
    echo "background-color:{$pm_bg_active} !important;";
    echo "border-color:{$pm_border_active} !important;";
    echo '}';

    // 付款方式描述區域
    echo ".yangsheep-design-checkout-page #payment .wc_payment_methods .payment_box {";
    echo "background-color:{$pm_desc_bg} !important;";
    echo "border-radius:6px !important;";
    echo "padding:12px !important;margin-top:10px !important;";
    echo "border:none !important;";
    echo '}';
    echo ".yangsheep-design-checkout-page #payment .wc_payment_methods .payment_box::before {display:none !important;}";

    // 商品明細區塊
    echo ".yangsheep-design-checkout-page .yangsheep-order-items-container,";
    echo ".yangsheep-design-checkout-page .yangsheep-review-wrapper {";
    echo "background-color:{$order_items_bg};";
    echo "border-radius:{$rad};";
    echo '}';

    // 物流卡片內層背景色（外層 .yangsheep-shipping-card 保持透明）
    echo ".yangsheep-design-checkout-page .yangsheep-shipping-card-inner {";
    echo "background-color:{$ship_card_bg};";
    echo '}';
    echo ".yangsheep-design-checkout-page .yangsheep-shipping-card.selected .yangsheep-shipping-card-inner,";
    echo ".yangsheep-design-checkout-page .yangsheep-shipping-card.active .yangsheep-shipping-card-inner {";
    echo "background-color:{$ship_card_active};";
    echo '}';

    // 統一結帳頁面按鈕樣式
    echo '.yangsheep-design-checkout-page button.button,';
    echo '.yangsheep-design-checkout-page input[type="submit"],';
    echo '.yangsheep-design-checkout-page .button,';
    echo '.yangsheep-design-checkout-page .yangsheep-coupon-button .button,';
    echo '.yangsheep-design-checkout-page #place_order {';
    echo "background-color:{$btn_bg};";
    echo "color:{$btn_txt};";
    echo "border-radius:{$rad};";
    echo 'border:none;transition:all 0.2s ease;}';

    echo '.yangsheep-design-checkout-page button.button:hover,';
    echo '.yangsheep-design-checkout-page input[type="submit"]:hover,';
    echo '.yangsheep-design-checkout-page .button:hover,';
    echo '.yangsheep-design-checkout-page .yangsheep-coupon-button .button:hover,';
    echo '.yangsheep-design-checkout-page #place_order:hover {';
    echo "background-color:{$btn_hover_bg};";
    echo "color:{$btn_hover_txt};";
    echo '}';

    // 台灣化欄位：隱藏 address_2（需要 !important 覆蓋 WooCommerce 內建樣式）
    echo '.yangsheep-design-checkout-page .woocommerce-shipping-fields .hidden,';
    echo '.yangsheep-design-checkout-page .woocommerce-shipping-fields #shipping_address_2_field.hidden,';
    echo '.yangsheep-design-checkout-page #shipping_address_2_field.hidden {display:none !important;}';

    // 台灣布局：姓名電話 2 欄、郵遞區號縣市區 3 欄、地址 1 欄（電腦版 1000px 以上）
    // 使用 6 欄 Grid（可被 2 和 3 整除），加 !important 確保覆蓋靜態 CSS
    echo '@media (min-width:1000px) {';
    echo '.yangsheep-design-checkout-page .woocommerce-shipping-fields__field-wrapper:has(.yangsheep-tw-third) {';
    echo 'grid-template-columns: repeat(6, 1fr) !important;gap:15px !important;grid-auto-rows:min-content !important;}';
    // 姓名、電話各佔 3 格（= 50%）
    echo '.yangsheep-design-checkout-page .woocommerce-shipping-fields__field-wrapper:has(.yangsheep-tw-third) #shipping_first_name_field,';
    echo '.yangsheep-design-checkout-page .woocommerce-shipping-fields__field-wrapper:has(.yangsheep-tw-third) #shipping_last_name_field,';
    echo '.yangsheep-design-checkout-page .woocommerce-shipping-fields__field-wrapper:has(.yangsheep-tw-third) #shipping_phone_field {';
    echo 'grid-column: span 3 !important;width:auto !important;}';
    // 郵遞區號、縣市、鄉鎮市區各佔 2 格（= 33.33%）
    echo '.yangsheep-design-checkout-page .woocommerce-shipping-fields__field-wrapper:has(.yangsheep-tw-third) .yangsheep-tw-third {';
    echo 'grid-column: span 2 !important;}';
    // 地址全寬
    echo '.yangsheep-design-checkout-page .woocommerce-shipping-fields__field-wrapper:has(.yangsheep-tw-third) .yangsheep-tw-full,';
    echo '.yangsheep-design-checkout-page .woocommerce-shipping-fields__field-wrapper:has(.yangsheep-tw-third) #shipping_address_1_field {';
    echo 'grid-column: 1 / -1 !important;}';
    // 國家欄位：被移到上方區塊，在這裡隱藏避免佔空間
    echo '.yangsheep-design-checkout-page .woocommerce-shipping-fields__field-wrapper #shipping_country_field {';
    echo 'display:none !important;}';
    echo '}';

    echo '</style>';
});
