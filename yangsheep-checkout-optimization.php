<?php
/**
 * Plugin Name:     YANGSHEEP 結帳強化
 * Plugin URI:      https://yangsheep.art
 * Description:     強化 WooCommerce 結帳頁面、我的帳號、訂單頁面；包含自訂佈局、TWzipcode 台灣郵遞區號、後台可調色和圓角、物流卡片選擇、第三方物流相容（綠界 ECPay / PayNow 超取）。
 * Version:         1.4.0
 * Author:          羊羊數位科技有限公司
 * Author URI:      https://yangsheep.art
 * Text Domain:     yangsheep-checkout-optimization
 * Domain Path:     /languages
 */

if ( ! defined( 'ABSPATH' ) ) exit;

define( 'YANGSHEEP_CHECKOUT_OPTIMIZATION_VERSION', '1.4.0' );
define( 'YANGSHEEP_CHECKOUT_OPTIMIZATION_DIR', plugin_dir_path( __FILE__ ) );
define( 'YANGSHEEP_CHECKOUT_OPTIMIZATION_URL', plugin_dir_url( __FILE__ ) );
define( 'YANGSHEEP_CHECKOUT_OPTIMIZATION_FILE', __FILE__ );

// 定義常數供其他類別使用
define( 'YANGSHEEP_CHECKOUT_URL', YANGSHEEP_CHECKOUT_OPTIMIZATION_URL );
define( 'YANGSHEEP_CHECKOUT_VERSION', YANGSHEEP_CHECKOUT_OPTIMIZATION_VERSION );

// 載入 Composer autoload（如果存在）
$autoload_file = YANGSHEEP_CHECKOUT_OPTIMIZATION_DIR . 'vendor/autoload.php';
if ( file_exists( $autoload_file ) ) {
    require_once $autoload_file;
} else {
    // 手動載入設定類別（當 vendor 不存在時）
    require_once YANGSHEEP_CHECKOUT_OPTIMIZATION_DIR . 'src/Settings/YSSettingsTableMaker.php';
    require_once YANGSHEEP_CHECKOUT_OPTIMIZATION_DIR . 'src/Settings/YSSettingsRepository.php';
    require_once YANGSHEEP_CHECKOUT_OPTIMIZATION_DIR . 'src/Settings/YSSettingsManager.php';
    require_once YANGSHEEP_CHECKOUT_OPTIMIZATION_DIR . 'src/Settings/YSSettingsMigrator.php';
}

use YangSheep\CheckoutOptimizer\Settings\YSSettingsManager;
use YangSheep\CheckoutOptimizer\Settings\YSSettingsTableMaker;
use YangSheep\CheckoutOptimizer\Settings\YSSettingsMigrator;

// 外掛啟用時建立資料表
register_activation_hook( __FILE__, 'yangsheep_checkout_optimizer_activate' );
function yangsheep_checkout_optimizer_activate() {
    $table_maker = YSSettingsTableMaker::instance();
    $table_maker->create_table();

    // 自動遷移
    $migrator = YSSettingsMigrator::instance();
    if ( $migrator->migration_required() ) {
        $migrator->migrate();
    }
}

// 載入核心
add_action( 'plugins_loaded', function(){
    // WPLoyalty 整合類別需要在設定類別之前載入（因為設定頁面會引用它）
    require_once YANGSHEEP_CHECKOUT_OPTIMIZATION_DIR . 'includes/class-yangsheep-wployalty-integration.php';
    require_once YANGSHEEP_CHECKOUT_OPTIMIZATION_DIR . 'includes/class-yangsheep-checkout-settings.php';
    require_once YANGSHEEP_CHECKOUT_OPTIMIZATION_DIR . 'includes/class-yangsheep-checkout-customizer.php';
    require_once YANGSHEEP_CHECKOUT_OPTIMIZATION_DIR . 'includes/class-yangsheep-shipping-cards.php';
    require_once YANGSHEEP_CHECKOUT_OPTIMIZATION_DIR . 'includes/class-yangsheep-checkout-sidebar.php';
    require_once YANGSHEEP_CHECKOUT_OPTIMIZATION_DIR . 'includes/class-yangsheep-checkout-fields.php';
    require_once YANGSHEEP_CHECKOUT_OPTIMIZATION_DIR . 'includes/class-yangsheep-checkout-order-enhancer.php';
    require_once YANGSHEEP_CHECKOUT_OPTIMIZATION_DIR . 'includes/class-yangsheep-third-party-shipping-compat.php';
});

// 啟動設定與自訂器
add_action( 'init', function(){
    YANGSHEEP_Checkout_Settings::get_instance();
    YANGSHEEP_Checkout_Customizer::get_instance();
    YANGSHEEP_Shipping_Cards::get_instance();
    YANGSHEEP_Checkout_Sidebar::get_instance();
    YANGSHEEP_Checkout_Order_Enhancer::get_instance();
    YANGSHEEP_Third_Party_Shipping_Compat::get_instance();
});

// 前端 CSS/JS
add_action( 'wp_enqueue_scripts', function(){
    if ( is_checkout() || is_account_page() ) {
        wp_enqueue_style( 'yangsheep-checkout-optimization', YANGSHEEP_CHECKOUT_OPTIMIZATION_URL . 'assets/css/yangsheep-checkout.css', [], YANGSHEEP_CHECKOUT_OPTIMIZATION_VERSION );
        wp_enqueue_script( 'jquery-twzipcode', YANGSHEEP_CHECKOUT_OPTIMIZATION_URL . 'assets/js/jquery.twzipcode.min.js', [ 'jquery' ], '1.7.12', true );
        wp_enqueue_script( 'yangsheep-checkout-custom', YANGSHEEP_CHECKOUT_OPTIMIZATION_URL . 'assets/js/yangsheep-checkout.js', [ 'jquery', 'jquery-twzipcode' ], YANGSHEEP_CHECKOUT_OPTIMIZATION_VERSION, true );

        // 傳遞超取物流方式清單到前端
        $cvs_methods = YSSettingsManager::get( 'yangsheep_cvs_shipping_methods', array() );
        wp_localize_script( 'yangsheep-checkout-custom', 'yangsheep_checkout_params', array(
            'cvs_shipping_methods' => is_array( $cvs_methods ) ? $cvs_methods : array(),
        ) );
    }
    // 物流卡片 CSS/JS（僅結帳頁）
    if ( is_checkout() && ! is_wc_endpoint_url() ) {
        wp_enqueue_style( 'yangsheep-shipping-cards', YANGSHEEP_CHECKOUT_OPTIMIZATION_URL . 'assets/css/yangsheep-shipping-cards.css', [ 'yangsheep-checkout-optimization' ], YANGSHEEP_CHECKOUT_OPTIMIZATION_VERSION );
        wp_enqueue_script( 'yangsheep-shipping-cards', YANGSHEEP_CHECKOUT_OPTIMIZATION_URL . 'assets/js/yangsheep-shipping-cards.js', [ 'jquery' ], YANGSHEEP_CHECKOUT_OPTIMIZATION_VERSION, true );

        // 側邊欄 CSS/JS
        wp_enqueue_style( 'yangsheep-sidebar', YANGSHEEP_CHECKOUT_OPTIMIZATION_URL . 'assets/css/yangsheep-sidebar.css', [ 'yangsheep-checkout-optimization' ], YANGSHEEP_CHECKOUT_OPTIMIZATION_VERSION );
        wp_enqueue_script( 'yangsheep-sidebar', YANGSHEEP_CHECKOUT_OPTIMIZATION_URL . 'assets/js/yangsheep-sidebar.js', [ 'jquery' ], YANGSHEEP_CHECKOUT_OPTIMIZATION_VERSION, true );

        // 第三方外掛相容 CSS
        wp_enqueue_style( 'yangsheep-compatibility', YANGSHEEP_CHECKOUT_OPTIMIZATION_URL . 'assets/css/yangsheep-compatibility.css', [ 'yangsheep-shipping-cards' ], YANGSHEEP_CHECKOUT_OPTIMIZATION_VERSION );

    }
    // 我的帳號頁面及訂單明細（根據設定決定是否載入視覺樣式）
    if ( is_account_page() ) {
        // 只有啟用「我的帳號視覺」時才載入樣式
        if ( YSSettingsManager::get( 'yangsheep_myaccount_visual', 'no' ) === 'yes' ) {
            wp_enqueue_style( 'yangsheep-myaccount',  YANGSHEEP_CHECKOUT_OPTIMIZATION_URL . 'assets/css/yangsheep-myaccount.css', [], YANGSHEEP_CHECKOUT_OPTIMIZATION_VERSION );
            wp_enqueue_style( 'yangsheep-order',      YANGSHEEP_CHECKOUT_OPTIMIZATION_URL . 'assets/css/yangsheep-order.css', [], YANGSHEEP_CHECKOUT_OPTIMIZATION_VERSION );
        }
    }
});

// 覆寫 WooCommerce 模板，支持 checkout, myaccount, order
add_filter( 'woocommerce_locate_template', function( $template, $template_name, $template_path ){
    // 插件模板根目錄
    $plugin_path = YANGSHEEP_CHECKOUT_OPTIMIZATION_DIR . 'templates/';
    // 若插件有該檔案，直接使用
    if ( file_exists( $plugin_path . $template_name ) ) {
        return $plugin_path . $template_name;
    }
    return $template;
}, 10, 3 );

// 主要 Hook 移動
remove_action( 'woocommerce_checkout_order_review', 'woocommerce_checkout_payment', 20 );
add_action( 'yangsheep_payment', 'woocommerce_checkout_payment', 20 );

// 不再覆寫 order review，使用 woocommerce_cart_item_name filter 注入控制項
remove_action( 'woocommerce_before_checkout_form', 'woocommerce_checkout_login_form', 10 );
add_action( 'yangsheep_loginform', 'woocommerce_checkout_login_form', 10 );
remove_action( 'woocommerce_before_checkout_form', 'woocommerce_checkout_coupon_form', 10 );
add_action( 'yangsheep_coupon', 'yangsheep_checkout_coupon_form_custom' );
function yangsheep_checkout_coupon_form_custom(){
    echo '<div class="yangsheep_checkout_coupon coupon-form">'
       . '<div class="yangsheep-inputform form-row woocommerce-validated">'
       . '<input type="text" name="coupon_code" class="input-text" placeholder="'.esc_attr__('Coupon','yangsheep-checkout-optimization').'" id="coupon_code" value="">'
       . '</div>'
       . '<div class="yangsheep-coupon-button form-row">'
       . '<button type="button" class="button" name="apply_coupon" value="'.esc_attr__('使用折扣代碼','yangsheep-checkout-optimization').'">'.esc_html__('使用折扣代碼','yangsheep-checkout-optimization').'</button>'
       . '</div><div class="clear"></div></div>';
}
// Ajax 優惠券
add_action( 'wp_footer', function(){ if(is_checkout()&&!is_wc_endpoint_url()){ ?>
<script>jQuery(function($){if(!window.wc_checkout_params)return;var cc='';$('input[name=coupon_code]').on('input',function(){cc=$(this).val();});$('button[name=apply_coupon]').click(function(){$.post(wc_checkout_params.ajax_url,{action:'apply_checkout_coupon',coupon_code:cc},function(r){$(document.body).trigger('update_checkout');$('.woocommerce-error,.woocommerce-message').remove();$('input[name=coupon_code]').val('');$('form.checkout').before(r);});});});</script>
<?php }} );
add_action('wp_ajax_apply_checkout_coupon','yangsheep_apply_checkout_coupon_ajax');
add_action('wp_ajax_nopriv_apply_checkout_coupon','yangsheep_apply_checkout_coupon_ajax');
function yangsheep_apply_checkout_coupon_ajax(){ if(!empty($_POST['coupon_code']))WC()->cart->add_discount(wc_format_coupon_code(wp_unslash($_POST['coupon_code'])));else wc_add_notice(WC_Coupon::get_generic_coupon_error(WC_Coupon::E_WC_COUPON_PLEASE_ENTER),'error');wc_print_notices();wp_die(); }

// AJAX: 更新購物車數量
add_action('wp_ajax_yangsheep_update_cart_qty', 'yangsheep_update_cart_qty_ajax');
add_action('wp_ajax_nopriv_yangsheep_update_cart_qty', 'yangsheep_update_cart_qty_ajax');
function yangsheep_update_cart_qty_ajax() {
    $cart_item_key = isset($_POST['cart_item_key']) ? sanitize_text_field($_POST['cart_item_key']) : '';
    $quantity = isset($_POST['quantity']) ? absint($_POST['quantity']) : 0;
    
    if ($cart_item_key && $quantity > 0) {
        WC()->cart->set_quantity($cart_item_key, $quantity, true);
        wp_send_json_success(array('message' => '數量已更新'));
    } else {
        wp_send_json_error(array('message' => '無效的請求'));
    }
}

// AJAX: 刪除購物車商品
add_action('wp_ajax_yangsheep_remove_cart_item', 'yangsheep_remove_cart_item_ajax');
add_action('wp_ajax_nopriv_yangsheep_remove_cart_item', 'yangsheep_remove_cart_item_ajax');
function yangsheep_remove_cart_item_ajax() {
    $cart_item_key = isset($_POST['cart_item_key']) ? sanitize_text_field($_POST['cart_item_key']) : '';
    
    if ($cart_item_key) {
        WC()->cart->remove_cart_item($cart_item_key);
        wp_send_json_success(array('message' => '商品已移除'));
    } else {
        wp_send_json_error(array('message' => '無效的請求'));
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
        $product_name = $_product->get_name();
        $price = WC()->cart->get_product_price($_product);
        $subtotal = WC()->cart->get_product_subtotal($_product, $quantity);
        ?>
        <div class="yangsheep-order-item" data-cart-key="<?php echo esc_attr($cart_item_key); ?>">
            <button type="button" class="yangsheep-remove-item" data-cart-key="<?php echo esc_attr($cart_item_key); ?>" aria-label="<?php esc_attr_e('移除商品', 'yangsheep-checkout-optimization'); ?>">×</button>
            <div class="yangsheep-item-content">
                <div class="yangsheep-item-image"><?php echo $thumbnail; ?></div>
                <div class="yangsheep-item-info">
                    <div class="yangsheep-item-name"><a href="<?php echo esc_url($_product->get_permalink()); ?>"><?php echo esc_html($product_name); ?></a></div>
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

// 自訂訂單總計輸出（不再呼叫 woocommerce_order_review 避免重複渲染商品項目）
add_action('yangsheep_order_totals', 'yangsheep_render_order_totals');
function yangsheep_render_order_totals() {
    // 直接輸出必要的 totals，不再使用 woocommerce_order_review()
    // 這樣可以避免主題 hook 插入額外的商品項目
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
    echo ':root{';
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

    // Checkbox 容器 - 簡潔樣式（無外框、無背景）
    echo '.yangsheep-design-checkout-page .yangsheep-order-notes-toggle,';
    echo '.yangsheep-design-checkout-page .yangsheep-same-as-billing {';
    echo 'display:flex;align-items:center;gap:10px;';
    echo 'cursor:pointer;padding:8px 0;margin-bottom:5px;}';

    // 內層 label 隱藏
    echo '.yangsheep-design-checkout-page .yangsheep-order-notes-toggle label,';
    echo '.yangsheep-design-checkout-page .yangsheep-same-as-billing label {';
    echo 'display:none;}';

    // 自訂 checkbox 樣式 - 與運送方式卡片一致 (22x22px)
    echo '.yangsheep-design-checkout-page .yangsheep-order-notes-toggle input[type=\"checkbox\"],';
    echo '.yangsheep-design-checkout-page .yangsheep-same-as-billing input[type=\"checkbox\"] {';
    echo 'appearance:none;-webkit-appearance:none;';
    echo 'width:22px;height:22px;min-width:22px;';
    echo 'border:2px solid var(--theme-form-field-border-initial-color, #d0d0d0);border-radius:4px;';
    echo 'background:#fff;cursor:pointer;';
    echo 'position:relative;transition:all 0.2s ease;';
    echo 'margin:0;flex-shrink:0;}';

    echo '.yangsheep-design-checkout-page .yangsheep-order-notes-toggle input[type=\"checkbox\"]:checked,';
    echo '.yangsheep-design-checkout-page .yangsheep-same-as-billing input[type=\"checkbox\"]:checked {';
    echo "background:{$btn_bg};border-color:{$btn_bg};}";

    // Checkbox 打勾圖示 - 調整位置配合 22px（與運送方式卡片一致）
    echo '.yangsheep-design-checkout-page .yangsheep-order-notes-toggle input[type=\"checkbox\"]:checked::after,';
    echo '.yangsheep-design-checkout-page .yangsheep-same-as-billing input[type=\"checkbox\"]:checked::after {';
    echo 'content:\"\";position:absolute;';
    echo 'left:6px;top:3px;width:6px;height:11px;';
    echo 'border:solid #fff;border-width:0 2px 2px 0;';
    echo 'transform:rotate(45deg);}';

    // Checkbox 文字樣式 - 與 checkbox 垂直置中
    echo '.yangsheep-design-checkout-page .yangsheep-order-notes-toggle > span,';
    echo '.yangsheep-design-checkout-page .yangsheep-same-as-billing > span {';
    echo 'font-size:14px;color:#333;line-height:22px;';
    echo 'cursor:pointer;}';

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
