<?php

declare(strict_types=1);

$root = dirname(__DIR__);

function source(string $path): string
{
    $contents = file_get_contents($path);
    if ($contents === false) {
        throw new RuntimeException("Unable to read {$path}");
    }
    return $contents;
}

function executable_php(string $source): string
{
    $result = '';
    foreach (token_get_all($source) as $token) {
        if (is_array($token) && in_array($token[0], [T_COMMENT, T_DOC_COMMENT], true)) {
            continue;
        }
        $result .= is_array($token) ? $token[1] : $token;
    }
    return $result;
}

function check(bool $condition, string $message): void
{
    static $failed = 0;
    static $total = 0;

    $total++;
    if ($condition) {
        echo "PASS {$message}\n";
    } else {
        $failed++;
        echo "FAIL {$message}\n";
    }

    $GLOBALS['ys_contract_total'] = $total;
    $GLOBALS['ys_contract_failed'] = $failed;
}

$bootstrap = source($root . '/yangsheep-checkout-optimization.php');
$layout = is_file($root . '/src/Checkout/YSCheckoutLayout.php')
    ? source($root . '/src/Checkout/YSCheckoutLayout.php')
    : '';
$checkoutFields = source($root . '/src/Checkout/YSCheckoutFields.php');
$shippingPhp = source($root . '/src/Checkout/YSShippingCards.php');
$shippingTemplate = source($root . '/templates/checkout/shipping-cards.php');
$shippingJs = source($root . '/assets/js/yangsheep-shipping-cards.js');
$checkoutJs = source($root . '/assets/js/yangsheep-checkout.js');
$compatCss = source($root . '/assets/css/yangsheep-compatibility.css');
$sidebarCss = source($root . '/assets/css/yangsheep-sidebar.css');
$checkoutCss = source($root . '/assets/css/yangsheep-checkout.css');
$wployaltyCss = source($root . '/assets/css/yangsheep-wployalty.css');
$wployaltyJs = source($root . '/assets/js/yangsheep-wployalty.js');
$wployaltyCompat = source($root . '/src/Compat/YSWPLoyaltyIntegration.php');
$shippingCompat = source($root . '/src/Compat/YSThirdPartyShippingCompat.php');
$settings = source($root . '/src/Admin/YSCheckoutSettings.php');
$yithCompat = source($root . '/src/Compat/YSYithPointsIntegration.php');
$readme = source($root . '/README.md');
$readmeTree = strstr($readme, '## 核心類別說明', true);

$templateFiles = glob($root . '/templates/{checkout,myaccount,order}/*.php', GLOB_BRACE) ?: [];
$templateFiles = array_map(
    static fn(string $path): string => str_replace('\\', '/', substr($path, strlen($root) + 1)),
    $templateFiles
);
sort($templateFiles);

// v1.7.0 最終形：checkout 永遠用 Woo 核心模板；只保留「我的帳號視覺」這組
// 設定 gated 的 myaccount/order 模板（模板與 myaccount/order CSS 是配對系統）。
$locateFilterPos = strpos( $bootstrap, "add_filter( 'woocommerce_locate_template'" );
check(
    $locateFilterPos !== false
    && str_contains( $bootstrap, "strpos( \$template_name, 'myaccount/' ) !== 0 && strpos( \$template_name, 'order/' ) !== 0" )
    && str_contains( $bootstrap, "YSSettingsManager::get( 'yangsheep_myaccount_visual', 'no' ) !== 'yes'" ),
    'template interception is limited to the opt-in my-account visual (never checkout)'
);
check($templateFiles === [
    'templates/checkout/shipping-cards.php',
    'templates/myaccount/form-edit-address.php',
    'templates/myaccount/form-login.php',
    'templates/myaccount/my-account.php',
    'templates/myaccount/my-address.php',
    'templates/myaccount/view-order.php',
    'templates/myaccount/view-subscription.php',
    'templates/order/order-details-customer.php',
    'templates/order/order-details.php',
], 'shipping-card partial plus the gated my-account visual templates remain');
check(str_contains($layout, 'woocommerce_checkout_before_customer_details'), 'enhancement blocks use a standard checkout hook');
check(
    str_contains($layout, 'woocommerce_before_checkout_shipping_form')
    && str_contains($layout, 'yangsheep_copy_billing'),
    'same-as-billing convenience control is restored through a standard Woo hook'
);
check(
    str_contains($layout, 'woocommerce_before_order_notes')
    && str_contains($layout, 'yangsheep_show_order_notes')
    && str_contains($layout, 'yangsheep_checkout_order_note'),
    'optional order-note control is restored through a standard Woo hook and setting'
);
check(!str_contains($bootstrap, "remove_action( 'woocommerce_checkout_order_review', 'woocommerce_checkout_payment'"), 'core payment remains on the standard order-review hook');
check(str_contains($checkoutJs, 'ys-checkout-enhanced'), 'layout is gated by a successful JavaScript enhancement class');
check(!str_contains($shippingPhp, "removeAttr('name')") && !str_contains($shippingPhp, "prop('disabled', true)"), 'native shipping radios are never disabled or stripped');
check(!str_contains($shippingTemplate, 'name="shipping_method['), 'shipping cards do not submit duplicate shipping radios');
check(str_contains($shippingJs, 'input.shipping_method') && str_contains($shippingJs, ".trigger('change')"), 'shipping cards proxy selection to native Woo radios');
check(!str_contains($shippingJs, "trigger('update_checkout')"), 'shipping cards do not trigger a second checkout update');
check(
    !str_contains($shippingCompat, '.yangsheep-shipping-cards input.shipping_method'),
    'shipping compatibility code does not listen for removed card radios'
);
check(
    str_contains(
        executable_php($shippingPhp),
        "add_filter( 'woocommerce_update_order_review_fragments'"
    ),
    'shipping-card fragment filter is registered as executable PHP'
);
check(
    str_contains($shippingCompat, "wp_dequeue_script( 'wc-cart' )")
    && str_contains($shippingCompat, "add_action( 'wp_enqueue_scripts', array( \$this, 'dequeue_cart_script_on_checkout' ), 100 )"),
    'checkout removes the conflicting cart handler that woomp enqueues'
);
check(
    str_contains($shippingCompat, "\$('#choose-cvs-btn').closest('tr.choose_cvs')")
    && !str_contains($shippingCompat, ".choose_cvs:not(.ys-cvs-shown)")
    && !str_contains($shippingCompat, "tr.choose_cvs:not(.ys-cvs-shown)"),
    'CVS compatibility scopes PayNow ownership and keeps third-party store rows fail-open'
);
check(
    str_contains($shippingCompat, 'function isEnhancedCheckout()')
    && str_contains($shippingCompat, 'if (!isEnhancedCheckout())')
    && str_contains($shippingCompat, 'body.ys-checkout-enhanced #CVSStoreName_field')
    && str_contains($shippingCompat, 'body.ys-checkout-enhanced #paynow_reservedno_field')
    && str_contains($shippingCompat, 'body.ys-checkout-enhanced .cvs-info th:nth-child'),
    'third-party logistics behavior and CSS only run after YS enhancement succeeds'
);
check(
    str_contains($shippingCompat, "YSSettingsManager::get( 'yangsheep_validate_phone_shipping', 'yes' )")
    && str_contains($shippingCompat, 'var validateShippingPhone =')
    && str_contains($shippingCompat, 'if (!validateShippingPhone)'),
    'client-side shipping phone validation respects the admin switch'
);
check(
    str_contains(
        $checkoutFields,
        "YSSettingsManager::get( 'yangsheep_checkout_tw_fields', 'no' ) === 'yes'\n            && isset( \$fields['shipping']['shipping_company'] )"
    ),
    'shipping company removal is limited to the Taiwan-fields mode'
);
check(!str_contains($checkoutJs, 'location.reload()'), 'coupon removal never reloads the checkout page');
check(
    str_contains($checkoutJs, 'mirrorCheckoutFieldsIntoYithForm')
    && str_contains($checkoutJs, '.ys-yith-checkout-field-mirror')
    && str_contains($checkoutJs, '#customer_details :input, .woocommerce-additional-fields :input')
    && str_contains($checkoutJs, ".closest('.woocommerce-checkout-payment')")
    && !str_contains($checkoutJs, 'sessionStorage'),
    'non-AJAX YITH redemption mirrors checkout fields without browser storage or payment controls'
);
check(str_contains($checkoutJs, 'selectVisibleYithMessage'), 'YITH relocation chooses one visible redeem surface');
check(str_contains($settings, 'yith_points_diagnostics_callback'), 'admin exposes YITH integration diagnostics');
check(!str_contains($compatCss, '.yangsheep-shipping-cards-container button[type="button"]:not(.yangsheep-qty-btn)'), 'third-party button CSS is scoped to known store selectors');
check(
    str_contains($bootstrap, "wp_enqueue_style( 'yangsheep-checkout-optimization'")
    && substr_count($bootstrap, "'not all' );") >= 4
    && !str_contains($bootstrap, 'wp_style_add_data'),
    'main checkout CSS is enqueued with a real not-all media argument'
);
check(str_contains($checkoutJs, 'enableEnhancedStyles'), 'successful layout explicitly enables deferred styles');
check(
    str_contains($checkoutJs, '$form.find(\'.yangsheep-enhancement-regions\')')
    && str_contains($checkoutJs, '$reviewHost'),
    'layout supports standard theme wrappers around checkout hook output'
);
check(
    str_contains($checkoutJs, '$reviewHost.children().appendTo($mainColumn);'),
    'theme wrapper content is preserved when order review is moved'
);
// v1.7.0 原始設計版面：折扣區「視覺」在主欄，但 YITH 兌換介面走 proxy —
// P0：form.checkout 內的巢狀 <form> 會被 fragment parser 丟棄，兌換鈕變成
// checkout 提交鈕（誤觸下單/付款）。原生介面留在 form 外 = 提交事實源。
check(!str_contains($checkoutJs, '$form.before($smartCoupon, $couponBlock)'), 'coupon region is no longer floated above the checkout form');
check(
    str_contains($checkoutJs, 'function buildYithProxy')
    && str_contains($checkoutJs, 'ys-yith-proxy-apply')
    && str_contains($checkoutJs, 'ys-yith-points-proxied'),
    'YITH redeem UI renders as a sanitized visual proxy; native form stays outside form.checkout'
);
check(
    !str_contains($checkoutJs, '$yithMessage.detach().appendTo($pointBlock)'),
    'the form-bearing YITH node is never moved inside form.checkout'
);
check(
    preg_match('/\.removeAttr\(\'name\'\)/', $checkoutJs) === 1
    && str_contains($checkoutJs, "find('input, button, select, textarea').removeAttr('name')")
    && str_contains($checkoutJs, "find('[id]').addBack('[id]').removeAttr('id')"),
    'proxy nodes are stripped of name/id so they can never join a checkout submission'
);
check(
    str_contains($checkoutJs, "\$wlrMessage.is('form') || \$wlrMessage.find('form').length"),
    'form-bearing WPLoyalty blocks are refused entry into the checkout form'
);
check(
    str_contains($checkoutJs, "if (!\$('form.checkout').hasClass('ys-checkout-enhanced')) {")
    && strpos($checkoutJs, "if (!\$('form.checkout').hasClass('ys-checkout-enhanced')) {") < strpos($checkoutJs, 'wployaltyEnabled'),
    'point-block relocation is gated behind successful enhancement (fail-open)'
);
check(
    str_contains($layout, 'yangsheep-checkout-country')
    && strpos($layout, 'yangsheep-checkout-country') < strpos($layout, 'yangsheep-review-wrapper')
    && strpos($layout, 'yangsheep-review-wrapper') < strpos($layout, 'yangsheep-coupon-block')
    && strpos($layout, 'yangsheep-coupon-block') < strpos($layout, 'yangsheep-shipping-cards-wrapper'),
    'region order matches the reference design: country, products, coupon, shipping'
);
check(
    str_contains($layout, "add_action( 'woocommerce_checkout_after_customer_details', array( \$this, 'render_payment_region' )"),
    'payment region renders after customer details per the reference design'
);
check(
    str_contains($checkoutJs, '$orderReview.detach().appendTo($shippingWrapper)'),
    'persistent #order_review relocates inside the shipping section container'
);
check(
    str_contains($checkoutCss, '.ys-checkout-enhanced .yangsheep-shipping-cards-wrapper > #order_review'),
    'relocated review block is styled as part of the shipping section'
);
check(
    !str_contains($checkoutJs, 'yangsheep-native-cart-contents')
    && !str_contains($checkoutJs, 'ys-native-order-review'),
    'no custom markers are placed on fragment-replaced review table nodes'
);
check(
    str_contains($checkoutCss, '.ys-checkout-enhanced #order_review .woocommerce-checkout-review-order-table tr.order-total')
    && str_contains($checkoutCss, '.ys-checkout-enhanced #order_review_heading'),
    'core review rows hide via rules anchored on the persistent #order_review wrapper'
);
check(!str_contains($sidebarCss, 'tr.woocommerce-shipping-totals.shipping {'), 'third-party shipping hook content is not hidden with the native row');
check(!str_contains($shippingTemplate, 'yangsheep_before_shipping_cards') && !str_contains($shippingTemplate, 'yangsheep_after_shipping_cards'), 'standard shipping hooks are not replayed by the visual proxy');
$sidebarPhp = is_file($root . '/src/Checkout/YSCheckoutSidebar.php')
    ? source($root . '/src/Checkout/YSCheckoutSidebar.php')
    : '';
check(
    $sidebarPhp !== ''
    && str_contains($sidebarPhp, "\$fragments['#yangsheep-order-summary']")
    && str_contains($sidebarPhp, "\$fragments['#yangsheep-shipping-display']")
    && str_contains($sidebarPhp, "\$fragments['#yangsheep-cart-contents']")
    && str_contains($layout, "do_action( 'yangsheep_checkout_sidebar' )"),
    'reference-design sidebar boxes render server-side and refresh via id fragments'
);
check(
    str_contains($sidebarPhp, 'ys-shipping-display-empty'),
    'shipping-display fragment root persists even when no shipping is chosen'
);
check(
    str_contains($sidebarPhp, 'button type="button" class="yangsheep-collapsible"')
    && str_contains($sidebarPhp, 'aria-expanded="true"')
    && str_contains($sidebarPhp, 'aria-controls="yangsheep-cart-items"')
    && str_contains($checkoutJs, "attr('aria-expanded', expanded ? 'false' : 'true')"),
    'cart-contents collapsible is a keyboard-operable button with aria state'
);
check(
    str_contains($bootstrap, "strpos( \$template_name, 'order/' ) === 0")
    && str_contains($bootstrap, 'is_account_page()'),
    'order/* template override is limited to My Account pages (order-received stays core)'
);
// 第三輪 P2：自訂 coupon 輸入不得重複 Woo 原生 #coupon_code
check(
    str_contains($bootstrap, 'id="ys_coupon_code"')
    && !str_contains($bootstrap, 'id="coupon_code"')
    && str_contains($bootstrap, "\$scope=\$('.yangsheep_checkout_coupon')"),
    'custom coupon input uses a unique id and scope-limited selectors'
);
// 第三輪 P2：外框在 wrapper，卡片+選店同一視覺區塊
$shippingCardsCss = source($root . '/assets/css/yangsheep-shipping-cards.css');
$wrapperBlock = substr($shippingCardsCss, strpos($shippingCardsCss, '.yangsheep-shipping-cards-wrapper {'), 400);
check(
    str_contains($wrapperBlock, 'border: 2px solid')
    && !preg_match('/\.yangsheep-shipping-cards-container \{[^}]*border:/s', $shippingCardsCss),
    'shipping section frame wraps both cards and relocated store-selector rows'
);
check(!is_file($root . '/assets/js/yangsheep-sidebar.js'), 'obsolete separate sidebar mover script stays removed');
check(
    str_contains($checkoutJs, "\$(document).on('click', '.yangsheep-collapsible'"),
    'sidebar cart-contents collapsible uses a delegated handler that survives fragment refresh'
);
$enhancePos = strpos($checkoutJs, 'function ensureCheckoutLayout');
check(
    $enhancePos !== false
    && strpos($checkoutJs, "\$('#shipping_country_field').insertAfter") > $enhancePos
    && strpos($checkoutJs, "\$('#coupons_list').detach().appendTo") > $enhancePos,
    'country field and smart-coupon moves happen only after successful enhancement'
);
check(
    str_contains($checkoutJs, 'link[rel="stylesheet"][href*='),
    'deferred style activation falls back to href lookup when style ids are rewritten'
);
check(
    substr_count($checkoutJs, 'ensureCheckoutLayout();') >= 2,
    'enhancement retries on updated_checkout when the form renders late'
);
check(
    str_contains($checkoutJs, 'function syncSidebarPlacement')
    && str_contains($checkoutJs, "\$(window).on('resize', syncSidebarPlacement)"),
    'sidebar repositions for mobile viewports and restores for desktop grid'
);
check(!str_contains($bootstrap, "remove_action( 'woocommerce_before_checkout_form', 'woocommerce_checkout_coupon_form'"), 'native coupon form remains available when enhancement JavaScript fails');
check(
    str_contains($bootstrap, 'yangsheep_checkout_native_coupon_fallback')
    && str_contains($bootstrap, "has_action( 'woocommerce_before_checkout_form', 'woocommerce_checkout_coupon_form' )"),
    'theme removal of the core coupon callback gets a native server-side fallback'
);
check(str_contains($checkoutJs, 'ys-native-coupon-superseded'), 'successful enhancement suppresses the duplicate native coupon form');
check(
    str_contains($checkoutJs, "var \$field  = \$('#order_comments_field')")
    && str_contains($checkoutJs, '$field.slideDown')
    && str_contains($checkoutJs, '$field.slideUp')
    && !str_contains($checkoutJs, "$('.woocommerce-additional-fields__field-wrapper').slide"),
    'order-note toggle only controls the native order-comments field'
);
check(
    str_contains($checkoutJs, 'var $redeemSurfaces')
    && str_contains($checkoutJs, 'ys-yith-points-duplicate'),
    'successful YITH mount deduplicates interactive redeem surfaces without consuming informational messages'
);
check(
    preg_match('/\.yangsheep-coupon-point\s+\.ys-yith-points-duplicate\s*\{\s*display:\s*none\s*!important;/s', $checkoutCss) === 1,
    'YITH duplicate class overrides plugin important display rules only after JavaScript marks it'
);
check(
    preg_match('/\.wlr_point_redeem_message\s*\{\s*display:\s*none\s*!important;/s', $wployaltyCss) !== 1,
    'WPLoyalty native UI is not hidden by CSS before integration succeeds'
);
check(
    strpos($wployaltyJs, '$couponPoint.append($customBlock);') < strpos($wployaltyJs, '$wlrMessage.hide();'),
    'WPLoyalty native UI is hidden only after the replacement is mounted'
);
// 第三輪 P1（故障注入）：主結帳 JS 被擋時 WPL 不得建替代/藏原生
check(
    str_contains($wployaltyJs, "if (!\$('form.checkout').hasClass('ys-checkout-enhanced')) {")
    && str_contains($wployaltyJs, "removeClass('ys-wployalty-source-mounted').show()"),
    'WPLoyalty relocation is gated behind checkout enhancement and restores native on failure'
);
check(
    str_contains($wployaltyJs, "if (\$customBlock.is(':visible')) {")
    && strpos($wployaltyJs, "if (\$customBlock.is(':visible')) {") < strpos($wployaltyJs, '$wlrMessage.hide();'),
    'WPLoyalty hides native only after the replacement is actually visible'
);
// 第三輪 P1（故障注入）：核心 CSS 未載入不得重排 DOM
$readyPos = strpos($checkoutJs, 'if (!enhancedStylesheetsReady()) {');
check(
    str_contains($checkoutJs, 'function enhancedStylesheetsReady()')
    && str_contains($checkoutJs, 'sheet.cssRules.length > 0')
    && $readyPos !== false
    && $readyPos < strpos($checkoutJs, '$payment.detach().appendTo($paymentTarget)'),
    'stylesheet readiness is verified atomically before any native DOM mutation'
);
check(
    str_contains($checkoutJs, "\$(window).on('load', function () {"),
    'enhancement retries at window.load once stylesheets are final'
);
check(str_contains($yithCompat, 'get_active_redeeming_rule_count') && str_contains($yithCompat, "'enable_rewards_points'"), 'YITH diagnostics inspect the global reward switch and active redeeming rules');
check(
    str_contains($yithCompat, "add_action( 'wp_loaded', array( \$this, 'capture_checkout_fields' ), 20 )")
    && str_contains($yithCompat, "add_filter( 'woocommerce_checkout_get_value', array( \$this, 'restore_checkout_field' ), 9999, 2 )")
    && str_contains($yithCompat, 'yangsheep_yith_checkout_fields')
    && str_contains($yithCompat, "'preservedFields'")
    && str_contains($checkoutJs, 'restoreYithCheckoutFields')
    && str_contains($checkoutJs, 'yithCheckoutFieldRestoreCompleted')
    && str_contains($checkoutJs, "updated_checkout.ysYithFieldRestore")
    && str_contains($checkoutJs, 'preservedFields')
    && str_contains($checkoutJs, 'ys_yith_checkout_field_names[]'),
    'non-AJAX YITH POST values are restored through a short-lived Woo session snapshot before custom defaults'
);
check(
    !str_contains($checkoutCss, '.yangsheep-design-pay-page')
    && !str_contains($checkoutCss, '.yangsheep-design-thankyou-page')
    && !str_contains($checkoutCss, '.yangsheep-view-subscription-details'),
    'retired endpoint template CSS is removed from the main checkout payload'
);
check(
    !preg_match('/#billing_country_field\s*\{\s*display:\s*none/s', $checkoutCss)
    && !preg_match('/h3#ship-to-different-address\s*\{\s*display:\s*none/s', $checkoutCss)
    && !preg_match('/#payment\s+li\s+img\s*\{\s*display:\s*none/s', $checkoutCss),
    'progressive styles do not hide native country, shipping-address, or gateway controls'
);
check(
    !str_contains($checkoutCss, '.yangsheep-order-totals .woocommerce-checkout-review-order')
    && !str_contains($checkoutJs, 'ys-yith-points-mounted')
    && !str_contains($bootstrap, 'yangsheep_order_totals'),
    'retired template selectors and write-only mount markers are removed'
);
check(
    is_string($readmeTree)
    && !str_contains($readmeTree, 'yangsheep-sidebar.js')
    && !str_contains($readmeTree, 'form-checkout.php'),
    'README current file tree matches the progressive-enhancement architecture'
);
check(
    str_contains($bootstrap, 'function yangsheep_checkout_asset_version')
    && str_contains($bootstrap, "yangsheep_checkout_asset_version( 'assets/js/yangsheep-checkout.js' )")
    && str_contains($wployaltyCompat, "yangsheep_checkout_asset_version( 'assets/js/yangsheep-wployalty.js' )"),
    'changed checkout assets use file-based cache-busting versions'
);

$total = (int) ($GLOBALS['ys_contract_total'] ?? 0);
$failed = (int) ($GLOBALS['ys_contract_failed'] ?? 0);
echo "SUMMARY total={$total} failed={$failed}\n";
exit($failed === 0 ? 0 : 1);
