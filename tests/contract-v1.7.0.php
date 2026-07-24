<?php

declare(strict_types=1);

$root = dirname(__DIR__);

function source(string $path): string
{
    $contents = file_get_contents($path);
    if ($contents === false) {
        throw new RuntimeException("Unable to read {$path}");
    }
    // 跨平台：Windows checkout（core.autocrlf=true）工作樹為 CRLF，
    // 固定字串斷言以 LF 撰寫 — 讀檔時統一正規化，讓全部斷言
    // 在 LF/CRLF 工作樹皆可重現（v1.7.1 P1 release-gate 修正）
    return str_replace("\r\n", "\n", $contents);
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
$settingsManager = source($root . '/src/Settings/YSSettingsManager.php');
$settingsMigrator = source($root . '/src/Settings/YSSettingsMigrator.php');
$yithCompat = source($root . '/src/Compat/YSYithPointsIntegration.php');
$myAccountCss = source($root . '/assets/css/yangsheep-myaccount.css');
$orderCss = source($root . '/assets/css/yangsheep-order.css');
$orderEnhancerCss = source($root . '/assets/css/yangsheep-order-enhancer.css');
$readme = source($root . '/README.md');
$readmeTree = strstr($readme, '## 核心類別說明', true);

$templateFiles = glob($root . '/templates/{checkout,myaccount,order}/*.php', GLOB_BRACE) ?: [];
$templateFiles = array_map(
    static fn(string $path): string => str_replace('\\', '/', substr($path, strlen($root) + 1)),
    $templateFiles
);
sort($templateFiles);

check(
    !str_contains( $bootstrap, 'woocommerce_locate_template' ),
    'checkout, myaccount and order templates always belong to WooCommerce or the active theme'
);
check($templateFiles === [
    'templates/checkout/shipping-cards.php',
], 'only the directly included shipping-card visual partial remains');
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
check(
    str_contains($checkoutJs, 'yangsheep-checkout-notice-host')
    && str_contains($checkoutJs, 'function syncCheckoutNotices()')
    && str_contains($checkoutJs, 'function handleCheckoutError()')
    && str_contains($checkoutJs, '.woocommerce-NoticeGroup-checkout')
    && str_contains($checkoutJs, 'Math.max(0, $host.offset().top - 100)')
    && str_contains($checkoutJs, "on('checkout_error', handleCheckoutError)"),
    'checkout notices are synchronized and re-focused inside a dedicated main-column host'
);
check(
    str_contains($bootstrap, "\$('.yangsheep-checkout-notice-host').first()")
    && !str_contains($bootstrap, "\$('.woocommerce-notices-wrapper').html(r)"),
    'coupon AJAX writes once to the enhanced notice host instead of every page wrapper'
);
check(
    str_contains($bootstrap, 'Version:           1.7.4')
    && str_contains($bootstrap, "YANGSHEEP_CHECKOUT_OPTIMIZATION_VERSION', '1.7.4'")
    && str_contains($checkoutJs, "__ysCheckoutOptimizerBuild = '1.7.4'")
    && str_contains($readme, '**當前版本**：1.7.4')
    && str_contains($readme, '### v1.7.4 (2026-07-24)'),
    'v1.7.4 candidate version markers stay synchronized'
);
check(
    str_contains($bootstrap, "add_filter( 'body_class', 'yangsheep_checkout_pending_body_class' )")
    && str_contains($bootstrap, "'ys-checkout-pending'")
    && str_contains($bootstrap, 'body.woocommerce-checkout.ys-checkout-pending form.checkout.woocommerce-checkout')
    && str_contains($bootstrap, 'visibility:hidden')
    && str_contains($bootstrap, '@keyframes ys-checkout-preflight-fallback')
    && str_contains($bootstrap, 'animation:ys-checkout-preflight-fallback 0s 2s forwards')
    && str_contains($bootstrap, '<noscript>')
    && str_contains($bootstrap, 'window.setTimeout')
    && str_contains($bootstrap, '2000'),
    'a bounded critical preflight cloak prevents native checkout first-paint without breaking no-JS fallback'
);
check(
    str_contains($checkoutJs, 'function releaseCheckoutPresentation(mode)')
    && str_contains($checkoutJs, "window.__ysCheckoutRelease(mode)")
    && str_contains($checkoutJs, "releaseCheckoutPresentation('enhanced')")
    && str_contains($checkoutJs, "releaseCheckoutPresentation('native')"),
    'checkout enhancement releases the preflight cloak on success and final load failure'
);
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
    str_contains($shippingCompat, "\$('input.shipping_method')")
    && str_contains($shippingCompat, "this.type === 'hidden' || this.checked")
    && str_contains($shippingCompat, 'selectedMethods.some(function(selectedValue)')
    && str_contains($shippingCompat, "selectedValue.split(':')[0] === methodList[i]"),
    'third-party CVS controls inspect every selected package including hidden rates with exact base-id matching'
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
    !str_contains($shippingCompat, 'body.ys-checkout-enhanced #paynow_storename_field,')
    && !str_contains($shippingCompat, 'body.ys-checkout-enhanced #paynow_storeid_field,')
    && !str_contains($shippingCompat, 'body.ys-checkout-enhanced #paynow_storeaddress_field {'),
    'legacy inline CSS no longer forces PayNow native source fields into narrow grid columns'
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
check(
    str_contains($settings, '完整 rate ID')
    && str_contains($settings, '混合包裹')
    && str_contains($settings, '非輸入式門市摘要')
    && !str_contains($settings, '所有超取欄位自動設為 2 欄排版'),
    'admin CVS guidance documents exact instances, mixed-package safety, and the non-input PayNow summary'
);
check(
    str_contains($settings, 'array_filter( $methods, \'strlen\' )')
    && str_contains($settings, 'array_unique( $methods )')
    && str_contains($settings, 'in_array( $method_id, $saved_methods, true )'),
    'admin CVS settings discard empty duplicates and compare saved full rate ids strictly'
);
check(!str_contains($compatCss, '.yangsheep-shipping-cards-container button[type="button"]:not(.yangsheep-qty-btn)'), 'third-party button CSS is scoped to known store selectors');
check(
    preg_match('/body\.ys-checkout-enhanced\s+tr\.ys-paynow-store-selector\s*\{[^}]*display:\s*block\s*!important;[^}]*width:\s*100%\s*!important;/s', $compatCss) === 1
    && preg_match('/tr\.ys-paynow-store-selector\s*>\s*td\.ys-cvs-store-panel\s*\{[^}]*display:\s*block\s*!important;[^}]*width:\s*100%\s*!important;/s', $compatCss) === 1
    && !str_contains($compatCss, 'body.ys-checkout-enhanced tr.choose_cvs,'),
    'enhanced PayNow store row gives its full-width button a full-width table cell'
);
check(
    substr_count($compatCss, '!important') <= 9
    && preg_match('/ys-cvs-store-title[^}]*border-top:[^;]+!important;/s', $compatCss) === 1
    && preg_match('/ys-cvs-store-panel[^}]*border:[^;]+!important;[^}]*background:[^;]+!important;/s', $compatCss) === 1
    && str_contains($shippingCompat, 'tr.choose_cvs:not(.ys-paynow-store-selector)')
    && str_contains($shippingCompat, 'tr.ys-cvs-choose-row:not(.ys-paynow-store-selector)'),
    'PayNow compatibility forces only measured table-contract properties and excludes legacy generic row styling'
);
check(
    str_contains($shippingCompat, 'function enhancePaynowStoreSelector')
    && str_contains($shippingCompat, "addClass('ys-paynow-store-selector')")
    && str_contains($shippingCompat, "addClass('ys-cvs-source-field').hide()")
    && str_contains($shippingCompat, 'ys-cvs-store-status')
    && !str_contains($shippingCompat, "prop('disabled', true)"),
    'PayNow keeps native store values submit-capable but replaces readonly input boxes with a status surface'
);
check(
    preg_match('/tr\.ys-paynow-store-selector\s*>\s*td\.ys-cvs-store-panel\s*\{[^}]*width:\s*100%\s*!important;[^}]*border:\s*1px\s+dashed/s', $compatCss) === 1
    && str_contains($compatCss, '.ys-cvs-store-status')
    && str_contains($compatCss, 'tr.ys-paynow-store-selector #choose-cvs-btn'),
    'PayNow chooser uses the same full-width title, status, and dashed-panel visual contract as other CVS providers'
);
check(
    str_contains($shippingCompat, "'woocommerce_order_formatted_shipping_address'")
    && str_contains($shippingCompat, "'woocommerce_localisation_address_formats'")
    && str_contains($shippingCompat, "'woocommerce_formatted_address_replacements'")
    && str_contains($shippingCompat, "'YS_PAYNOW_CVS'")
    && str_contains($shippingCompat, 'reconcile_paynow_order_address'),
    'PayNow order addresses use a provider-unique format key that cannot be overwritten by PAYUNi PNCVS'
);
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
    !str_contains($bootstrap, "strpos( \$template_name, 'order/' )")
    && !is_file($root . '/templates/order/order-details.php')
    && !is_file($root . '/templates/order/order-details-customer.php'),
    'order details always use the installed WooCommerce core templates'
);
// v1.7.1：coupon 輸入樣式與唯一 id 同步（v1.7.0 改 id 後 CSS 曾成死碼）
check(
    str_contains($checkoutCss, '.yangsheep_checkout_coupon #ys_coupon_code')
    && !str_contains($checkoutCss, '.yangsheep_checkout_coupon #coupon_code'),
    'custom coupon input styling targets the unique ys_coupon_code id'
);
// v1.7.2：超取隱藏地址欄位不受漸進增強 gate 影響（回歸修復）
$cvsModeCss = is_file($root . '/assets/css/yangsheep-cvs-mode.css')
    ? source($root . '/assets/css/yangsheep-cvs-mode.css')
    : '';
check(
    $cvsModeCss !== ''
    && str_contains($cvsModeCss, 'body.yangsheep-cvs-mode #shipping_address_1_field')
    && preg_match('/body\.yangsheep-cvs-mode[^{]*#shipping_address_1_field[^}]*display:\s*none\s*!important/s', $cvsModeCss) === 1,
    'CVS-mode address hiding lives in the dedicated always-on stylesheet'
);
check(
    preg_match("/wp_enqueue_style\(\s*'yangsheep-cvs-mode',(?:(?!\)\s*;).)*\)\s*;/s", $bootstrap) === 1
    && preg_match("/wp_enqueue_style\(\s*'yangsheep-cvs-mode',(?:(?!\)\s*;).)*'not all'(?:(?!\)\s*;).)*\)\s*;/s", $bootstrap) !== 1,
    'CVS-mode stylesheet is enqueued without the not-all enhancement gate'
);
check(
    preg_match('/body\.yangsheep-cvs-mode\s+#shipping_address_1_field[^}]*display:\s*none/s', $checkoutCss) !== 1,
    'gated main stylesheet no longer owns the CVS address-hiding rules'
);
// v1.7.2 P1：完整 rate id（含 ":"）只能完整相等，避免 flat_rate:1 誤中 flat_rate:10
check(
    str_contains($checkoutFields, 'public static function method_matches_cvs_list')
    && str_contains($checkoutFields, "false !== strpos( \$cvs_method, ':' )")
    && str_contains($checkoutFields, 'self::method_matches_cvs_list( $method, $cvs_methods )')
    && !str_contains($checkoutFields, "strpos( \$method, \$cvs_method ) === 0"),
    'PHP CVS matcher requires exact match for full rate ids (no prefix false-positive)'
);
check(
    str_contains($checkoutJs, "if (cvsMethod.indexOf(':') !== -1) return false;")
    && !str_contains($checkoutJs, 'methodId.indexOf(cvsMethod) === 0'),
    'JS CVS matcher requires exact match for full rate ids (no prefix false-positive)'
);
// v1.7.2 P1：多包裹全域地址免必填 = 「所有包裹都超商」（CVS+宅配 → 保留地址）
check(
    str_contains($checkoutFields, 'public static function all_methods_cvs')
    && str_contains($checkoutFields, 'public static function is_single_method_cvs')
    && str_contains($checkoutFields, 'self::all_methods_cvs( $shipping_methods, $cvs_methods )')
    && preg_match('/if \(\s*!\s*self::is_single_method_cvs\([^)]*\)\s*\)\s*\{\s*return false;/s', $checkoutFields) === 1,
    'PHP global address hides only when every selected package is CVS (multi-package fail-safe)'
);
check(
    str_contains($checkoutJs, "$('#order_review input.shipping_method').filter(")
    && str_contains($checkoutJs, "this.type === 'hidden' || this.checked")
    && str_contains($checkoutJs, 'methodIds.length > 0 && methodIds.every(isSingleMethodCvs)')
    && str_contains($checkoutJs, 'var signature = methodIds.join(')
    // 不得再用 :checked-only 收集（會漏 WooCommerce 單一物流的 hidden input）
    && !str_contains($checkoutJs, "input[name^=\"shipping_method\"]:checked').map("),
    'JS collects every package incl. single-method hidden input, requires all-CVS via every()'
);
// v1.7.2 P1: PAYUNi only reads package 0 and can leave inline display:none on a
// mixed checkout. Reconcile after all synchronous shipping handlers, but only
// when the current all-package signature is still mixed PAYUNi CVS + delivery.
check(
    str_contains($checkoutJs, 'function schedulePayuniMixedAddressRestore')
    && str_contains($checkoutJs, 'methodIds.some(isPayuniCvsMethod)')
    && str_contains($checkoutJs, 'methodIds.some(function(methodId)')
    && str_contains($checkoutJs, '!isSingleMethodCvs(methodId)')
    && str_contains($checkoutJs, 'window.PayuniStoreSelector.showAddressFields()')
    && str_contains($checkoutJs, "this.style.removeProperty('display')")
    && !str_contains($checkoutJs, ".removeAttr('style')"),
    'mixed PAYUNi packages restore shipping address through the provider public API'
);
check(
    str_contains($checkoutJs, 'payuniAddressRestoreTimer = setTimeout(function ()')
    && str_contains($checkoutJs, 'collectSelectedShippingMethodIds().join(')
    && str_contains($checkoutJs, 'currentSignature !== expectedSignature')
    && str_contains($checkoutJs, "$('body').hasClass('yangsheep-cvs-mode')"),
    'PAYUNi reconciliation is delayed, signature-guarded, and cannot override all-CVS state'
);
// v1.7.2 P2：PHP/JS 自動偵測 allowlist 規則一致（base、小寫、payuni/ecpay-cvs/ys_paynow）
check(
    str_contains($checkoutFields, "false !== strpos( \$base, 'payuni' )")
    && str_contains($checkoutFields, "false !== strpos( \$base, 'ecpay' ) && false !== strpos( \$base, 'cvs' )")
    && str_contains($checkoutFields, "false !== strpos( \$base, 'ys_paynow_shipping' )")
    && str_contains($checkoutFields, "0 === strpos( \$base, 'paynow_shipping_c2c_' )")
    && str_contains($checkoutFields, "0 === strpos( \$base, 'woomp_paynow_shipping_c2c_' )")
    && !str_contains($checkoutFields, "strpos( \$method_id, 'ys_paynow_shipping_' ) === 0 && strpos( \$method_id, 'tcat' ) === false"),
    'PHP auto-detect uses the shared CVS allowlist (aligned with JS, no broad ys_paynow-except-tcat)'
);
check(
    str_contains($checkoutJs, "base.indexOf('payuni') !== -1")
    && str_contains($checkoutJs, "base.indexOf('ecpay') !== -1 && base.indexOf('cvs') !== -1")
    && str_contains($checkoutJs, "base.indexOf('ys_paynow_shipping') !== -1")
    && str_contains($checkoutJs, "base.indexOf('paynow_shipping_c2c_') === 0")
    && str_contains($checkoutJs, "base.indexOf('woomp_paynow_shipping_c2c_') === 0"),
    'JS auto-detect uses the shared CVS allowlist (aligned with PHP)'
);
// v1.7.1：YITH proxy 版面只作用於 YS 自有結構，不硬改第三方 class
check(
    str_contains($checkoutJs, 'ys-yith-proxy-form')
    && str_contains($checkoutCss, '.ys-yith-proxy .ys-yith-proxy-form')
    && str_contains($checkoutCss, '.ys-yith-proxy .ys-yith-proxy-points')
    && !preg_match('/\.yangsheep-coupon-point\s+\.ywpar_apply_discounts\s*\{/', $checkoutCss),
    'YITH proxy layout styles its own structure without forcing third-party classes'
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
    && str_contains($checkoutCss, 'body.ys-checkout-enhanced h3#ship-to-different-address')
    && preg_match('/body\.ys-checkout-enhanced\s+h3#ship-to-different-address\s*\{[^}]*display:\s*none\s*!important/s', $checkoutCss) === 1
    && !preg_match('/#payment\s+li\s+img\s*\{\s*display:\s*none/s', $checkoutCss),
    'progressive styles preserve native controls except the enhanced legacy shipping-address toggle'
);
check(
    str_contains($settingsManager, "'yangsheep_checkout_field_compatibility'")
    && preg_match(
        "/'yangsheep_checkout_field_compatibility'\s*=>\s*'no'/",
        $settingsManager
    ) === 1
    && str_contains($settings, "'yangsheep_checkout_field_compatibility'")
    && str_contains($settings, '結帳欄位外掛相容強制模式'),
    'checkout-field compatibility mode is an explicit opt-in backend setting'
);
check(
    str_contains(
        $checkoutFields,
        "add_filter( 'woocommerce_checkout_fields', array( \$this, 'enforce_checkout_field_compatibility' ), PHP_INT_MAX )"
    )
    && str_contains(
        $checkoutFields,
        "YSSettingsManager::get( 'yangsheep_checkout_field_compatibility', 'no' )"
    )
    && str_contains($checkoutFields, '$this->customize_checkout_fields( $fields )')
    && str_contains($checkoutFields, '$this->maybe_remove_address_required_for_cvs( $fields )')
    && str_contains($checkoutFields, '$this->force_phone_fields( $fields )'),
    'compatibility mode reapplies YS field rules after priority-9999 field editors'
);
check(
    str_contains(
        $checkoutFields,
        "add_filter( 'woocommerce_ship_to_different_address_checked', array( \$this, 'force_separate_shipping_address' ), 999 )"
    )
    && str_contains($checkoutFields, 'public function force_separate_shipping_address'),
    'enhanced checkout preserves the legacy separate-recipient data model'
);
check(
    !str_contains($checkoutCss, '.yangsheep-order-totals .woocommerce-checkout-review-order')
    && !str_contains($checkoutJs, 'ys-yith-points-mounted')
    && !str_contains($bootstrap, 'yangsheep_order_totals'),
    'retired template selectors and write-only mount markers are removed'
);
$retiredSettingKeys = [
    'yangsheep_checkout_login_welcome_text',
    'yangsheep_checkout_login_text_padding',
    'yangsheep_checkout_login_text_color',
    'yangsheep_checkout_login_text_bg',
    'yangsheep_checkout_link_color',
    'yangsheep_checkout_order_review_bg_color',
];
$retiredSettingsAbsent = true;
foreach ($retiredSettingKeys as $retiredSettingKey) {
    if (
        str_contains($settingsManager, "'{$retiredSettingKey}'")
        || str_contains($settings, "'{$retiredSettingKey}'")
        || str_contains($bootstrap, "YSSettingsManager::get('{$retiredSettingKey}'")
    ) {
        $retiredSettingsAbsent = false;
        break;
    }
}
check(
    $retiredSettingsAbsent
    && str_contains($settingsMigrator, 'RETIRED_SETTING_KEYS')
    && str_contains($settingsMigrator, '$this->repository->delete_many')
    && str_contains($settingsMigrator, 'delete_option( $key )'),
    'six no-op settings are retired from UI/runtime/defaults and cleaned from both storage backends'
);
check(
    str_contains($settingsMigrator, '$from_version < 1')
    && str_contains($settingsMigrator, '$from_version < 2')
    && str_contains($settingsMigrator, "if ( empty( \$result['errors'] ) )")
    && substr_count($bootstrap, 'migration_required()') >= 2,
    'upgrade path runs v2 cleanup without replaying stale wp_options and failed migrations remain retryable'
);
check(
    !str_contains($settings, 'private static $default_colors')
    && str_contains($settings, 'YSSettingsManager::get_default')
    && str_contains($settingsManager, "self::get( \$key, self::get_default( \$key ) )")
    && preg_match(
        "/'yangsheep_checkout_block_border_radius'\s*=>\s*'12px'/",
        $settingsManager
    ) === 1
    && preg_match(
        "/'yangsheep_checkout_section_bg_color'\s*=>\s*'#f5f8fa'/",
        $settingsManager
    ) === 1
    && preg_match(
        "/'yangsheep_checkout_form_field_bg_color'\s*=>\s*'#ffffff'/",
        $settingsManager
    ) === 1,
    'admin, runtime fallback, reset and custom-table writes share one canonical default map'
);

preg_match_all(
    "/'(?<key>yangsheep_[a-z0-9_]+)'\s*=>/",
    $settingsManager,
    $defaultSettingMatches
);
$runtimeSettingSources = $bootstrap;
$runtimeSettingFiles = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($root . '/src', FilesystemIterator::SKIP_DOTS)
);
foreach ($runtimeSettingFiles as $runtimeSettingFile) {
    if (
        !$runtimeSettingFile->isFile()
        || strtolower($runtimeSettingFile->getExtension()) !== 'php'
        || in_array(
            $runtimeSettingFile->getFilename(),
            ['YSSettingsManager.php', 'YSSettingsMigrator.php'],
            true
        )
    ) {
        continue;
    }
    $runtimeSettingSources .= "\n" . source($runtimeSettingFile->getPathname());
}
$settingsWithoutConsumers = [];
foreach (array_unique($defaultSettingMatches['key'] ?? []) as $settingKey) {
    if (
        preg_match(
            "/YSSettingsManager::get\(\s*'" . preg_quote($settingKey, '/') . "'/",
            $runtimeSettingSources
        ) !== 1
    ) {
        $settingsWithoutConsumers[] = $settingKey;
    }
}
check(
    $settingsWithoutConsumers === [],
    'every canonical setting has an explicit runtime consumer'
);

$ownedCssSources = '';
foreach (glob($root . '/assets/css/*.css') ?: [] as $cssPath) {
    $ownedCssSources .= "\n" . source($cssPath);
}
preg_match_all(
    '/[.#](?<name>(?:yangsheep[-_]|ys[-_])[a-zA-Z0-9_-]+)/',
    $ownedCssSources,
    $ownedSelectorMatches
);
$ownedSelectorProducers = $bootstrap;
foreach ([
    $root . '/src',
    $root . '/assets/js',
    $root . '/templates',
] as $producerRoot) {
    $producerFiles = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($producerRoot, FilesystemIterator::SKIP_DOTS)
    );
    foreach ($producerFiles as $producerFile) {
        if (
            !$producerFile->isFile()
            || !in_array(strtolower($producerFile->getExtension()), ['php', 'js'], true)
        ) {
            continue;
        }
        $ownedSelectorProducers .= "\n" . source($producerFile->getPathname());
    }
}
$ownedSelectorsWithoutProducers = [];
foreach (array_unique($ownedSelectorMatches['name'] ?? []) as $selectorName) {
    if (!str_contains($ownedSelectorProducers, $selectorName)) {
        $ownedSelectorsWithoutProducers[] = $selectorName;
    }
}
check(
    $ownedSelectorsWithoutProducers === [],
    'every owned CSS selector has a current PHP, JavaScript or template producer'
);

check(
    !str_contains($checkoutCss, '.ct-order-review')
    && !str_contains($bootstrap, '.ct-order-review')
    && !str_contains($checkoutCss, '.yangsheep-login')
    && !str_contains($checkoutCss, '.yangsheep-wide50')
    && !str_contains($checkoutCss, '.yangsheep-wide100')
    && !str_contains($checkoutCss, '.yangsheep-account-note')
    && !str_contains($checkoutCss, '.yangsheep-create-account')
    && !str_contains($checkoutCss, '.yangsheep-account-fields')
    && !str_contains($checkoutJs, '.yangsheep-account-fields')
    && !str_contains($checkoutCss, '.yangsheep-h3-point-title')
    && !str_contains($checkoutCss, '.yangsheep-copy-billing')
    && !str_contains($shippingCardsCss, '.yangsheep-shipping-meta')
    && !str_contains($checkoutCss, '#yangsheep_copy_field')
    && !str_contains($checkoutCss, '#yangsheep_copy_billing_field')
    && !str_contains($orderCss, 'woocommerce-customer-details'),
    'selectors without a current markup producer and obsolete order-customer layout overrides are removed'
);
check(
    str_contains($myAccountCss, '.woocommerce-account .woocommerce-MyAccount-navigation ul {')
    && str_contains($myAccountCss, 'display: block !important;')
    && str_contains($myAccountCss, 'overflow-x: auto;')
    && !str_contains($myAccountCss, '.woocommerce-account .ct-woo-account'),
    'My Account visual mode respects the theme desktop columns and uses bounded mobile navigation'
);
preg_match_all("/\\\$vars\\['(?<property>--[^']+)'\\]/", $settings, $cssVariableMatches);
$unconsumedCssVariables = [];
foreach (array_unique($cssVariableMatches['property'] ?? []) as $cssVariable) {
    if (
        !str_contains($myAccountCss, "var({$cssVariable}")
        && !str_contains($orderEnhancerCss, "var({$cssVariable}")
    ) {
        $unconsumedCssVariables[] = $cssVariable;
    }
}
check(
    $unconsumedCssVariables === [],
    'every backend-emitted My Account and order-status CSS variable has a loaded stylesheet consumer'
);
check(
    str_contains($shippingCompat, "'paynow_shipping_b2c_711'")
    && str_contains($settings, 'B2C、OK 或自訂方法請在「超取物流方式設定」以完整 rate ID 手動勾選')
    && str_contains($checkoutFields, 'paynow_shipping_c2c_')
    && !str_contains($checkoutFields, "strpos( \$base, 'paynow_shipping_b2c_'"),
    'PayNow B2C keeps selector compatibility but address relaxation remains explicit manual opt-in'
);
check(
    is_string($readmeTree)
    && !str_contains($readmeTree, 'yangsheep-sidebar.js')
    && !str_contains($readmeTree, 'form-checkout.php')
    // v1.7.2 P3：檔案樹須列出實際存在的新 CSS
    && str_contains($readmeTree, 'yangsheep-cvs-mode.css'),
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
