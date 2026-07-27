// assets/js/yangsheep-checkout.js
// 版本: 2.7.0 - 修正建立帳號密碼欄位顯隱、國家區塊動態隱藏
// 動態控制地址欄位顯示
jQuery(function ($) {
    'use strict';

    // runtime build 探針：部署迭代間 ver 參數不變時，瀏覽器 memory cache 可能黏著舊版，
    // 驗證前先比對此值可即刻判定 runtime 實際載入的版本
    window.__ysCheckoutOptimizerBuild = '1.7.9';
    console.log('[YS Checkout] build ' + window.__ysCheckoutOptimizerBuild + ' 初始化');

    var ysCheckoutNonce = (typeof yangsheep_checkout_params !== 'undefined' && yangsheep_checkout_params.nonce)
        ? yangsheep_checkout_params.nonce
        : '';

    // ===== 0. Progressive layout enhancement =====
    // WooCommerce remains the source of truth. Only rearrange the checkout after
    // every required native node and YS target exists; otherwise leave the native
    // form untouched and fully usable.
    var ENHANCED_STYLESHEETS = [
        ['yangsheep-checkout-optimization-css', 'yangsheep-checkout.css'],
        ['yangsheep-shipping-cards-css', 'yangsheep-shipping-cards.css'],
        ['yangsheep-sidebar-css', 'yangsheep-sidebar.css'],
        ['yangsheep-compatibility-css', 'yangsheep-compatibility.css']
    ];

    // 主要用 WP 預設 style id；若被 CSS 優化外掛改寫 id，退回以 href 尋找
    function findEnhancedStylesheet(pair) {
        return document.getElementById(pair[0]) ||
            document.querySelector('link[rel="stylesheet"][href*="' + pair[1] + '"]');
    }

    // P1 fail-open：在搬動任何原生節點「之前」原子化確認四支核心樣式表
    // 都已載入且可讀（link.sheet 存在且有規則）。任何一支失敗（被優化外掛
    // 合併移除、網路失敗、CSP 擋掉、尚未載完）→ 本次放棄增強、原生結帳不動；
    // updated_checkout / window.load 會再重試。
    function enhancedStylesheetsReady() {
        return ENHANCED_STYLESHEETS.every(function (pair) {
            var link = findEnhancedStylesheet(pair);
            if (!link) {
                return false;
            }
            try {
                var sheet = link.sheet;
                return !!sheet && !!sheet.cssRules && sheet.cssRules.length > 0;
            } catch (e) {
                return false;
            }
        });
    }

    function enableEnhancedStyles() {
        ENHANCED_STYLESHEETS.forEach(function (pair) {
            var stylesheet = findEnhancedStylesheet(pair);
            if (stylesheet) {
                stylesheet.media = 'all';
            }
        });
    }

    function releaseCheckoutPresentation(mode) {
        if (typeof window.__ysCheckoutRelease === 'function') {
            window.__ysCheckoutRelease(mode);
            return;
        }

        $('body').removeClass('ys-checkout-pending');
    }

    function syncCheckoutNotices() {
        var $form = $('form.checkout.woocommerce-checkout.ys-checkout-enhanced').first();
        var $host = $form.find('.yangsheep-form-column > .yangsheep-checkout-notice-host');

        if (!$form.length || $host.length !== 1) {
            return false;
        }

        var $checkoutScope = $form.closest('.woocommerce');
        var $externalWrappers = $checkoutScope.find('.woocommerce-notices-wrapper').filter(function () {
            return !$.contains($form[0], this);
        });
        var $formWrappers = $form.children('.woocommerce-notices-wrapper');
        var $checkoutGroups = $form.children('.woocommerce-NoticeGroup-checkout');

        $externalWrappers.add($formWrappers).each(function () {
            $(this).contents().detach().appendTo($host);
        });
        $checkoutGroups.detach().appendTo($host);

        return true;
    }

    function handleCheckoutError() {
        if (!syncCheckoutNotices()) {
            return;
        }

        var $host = $('.yangsheep-checkout-notice-host').first();
        if (!$host.length || !$host.children().length) {
            return;
        }

        $('html, body').stop(true).animate({
            scrollTop: Math.max(0, $host.offset().top - 100)
        }, 350);
    }

    function ensureCheckoutLayout() {
        var $form = $('form.checkout.woocommerce-checkout').first();

        if (!$form.length || $form.hasClass('ys-checkout-enhanced')) {
            return $form.hasClass('ys-checkout-enhanced');
        }

        // P1 fail-open：樣式表沒到位就「完全不動」原生 DOM。
        // （media 翻轉在最後；但搬移/隱藏若先做、CSS 又載入失敗，
        // 頁面會停在無樣式的重排結構 — 故障注入實證。）
        if (!enhancedStylesheetsReady()) {
            console.warn('[YS Checkout] enhanced stylesheets unavailable; using WooCommerce checkout');
            return false;
        }

        var $regions = $form.find('.yangsheep-enhancement-regions');
        var $sidebarWrapper = $form.find('.yangsheep-checkout-sidebar-wrapper');
        var $sidebar = $sidebarWrapper.children('.yangsheep-checkout-sidebar');
        var $reviewHeading = $form.find('#order_review_heading');
        var $orderReview = $form.find('#order_review');
        var $shippingWrapper = $regions.children('.yangsheep-shipping-cards-wrapper');
        var $paymentSection = $form.find('.yangsheep-payment');
        var $paymentTarget = $paymentSection.find('.yangsheep-payment-block');
        var $payment = $orderReview.find('#payment');

        if ($regions.length !== 1 || $sidebarWrapper.length !== 1 || $sidebar.length !== 1 ||
            $reviewHeading.length !== 1 || $orderReview.length !== 1 ||
            $shippingWrapper.length !== 1 || $paymentSection.length !== 1 ||
            $paymentTarget.length !== 1 || $payment.length !== 1) {
            console.warn('[YS Checkout] native layout contract incomplete; using WooCommerce checkout');
            return false;
        }

        var $reviewHost = $reviewHeading.parent();
        var reviewHostIsForm = $reviewHost.is($form);

        // 原生 #payment 移入帳單資訊後方的付款區
        $payment.detach().appendTo($paymentTarget);

        // #order_review（持久 wrapper）移入「選擇運送方式」容器內、卡片之後：
        // 核心列（商品/小計/運費/總計）由 gated CSS 隱藏 — YS 卡片與側邊欄取代其顯示；
        // 第三方掛在標準 review hooks 的內容（超商選店等）就顯示在物流區塊內，
        // 與原始設計一致（選店 UI 屬於運送方式區，不是獨立區塊）。
        // Woo AJAX 只替換 wrapper 內的 table/payment fragment，
        // wrapper 本身與其位置不受影響 — 不依賴任何會被 fragment 洗掉的標記。
        $orderReview.detach().appendTo($shippingWrapper);

        $sidebarWrapper.detach();

        var $mainColumn = $('<div class="yangsheep-form-column"></div>');
        var $noticeHost = $('<div class="yangsheep-checkout-notice-host" aria-live="polite"></div>');
        $form.children().each(function () {
            if (!reviewHostIsForm && this === $reviewHost[0]) {
                $reviewHost.children().appendTo($mainColumn);
                return;
            }
            $(this).appendTo($mainColumn);
        });
        $mainColumn.prepend($noticeHost);
        if (!reviewHostIsForm) {
            $reviewHost.remove();
        }
        $form.append($mainColumn, $sidebarWrapper);

        if (!$form.parent().hasClass('yangsheep-design-checkout-page')) {
            $form.wrap('<div class="yangsheep-design-checkout-page"></div>');
        }

        // Woo 原生折扣入口是 no-JS/fail-open 後備。只有 YS layout 已完整
        // 建立時才隱藏，避免與自訂 AJAX 折扣入口重複。
        $('.woocommerce-form-coupon-toggle, form.checkout_coupon')
            .addClass('ys-native-coupon-superseded')
            .attr('aria-hidden', 'true')
            .hide();

        // 增強成功後才把國家欄位 / 智慧折扣券移入 YS 區塊 —
        // 未增強時這些區塊是 hidden 的，移入會讓原生控制項憑空消失（fail-open 違規）
        if ($('#order_country_heading').length && $('#shipping_country_field').length) {
            $('#shipping_country_field').insertAfter($('#order_country_heading'));
        }
        if ($('#coupons_list').length && $regions.children('.yangsheep-smart-coupon').length) {
            $('#coupons_list').detach().appendTo($regions.children('.yangsheep-smart-coupon'));
        }

        $regions.removeAttr('hidden');
        $sidebarWrapper.removeAttr('hidden');
        $paymentSection.removeAttr('hidden');
        $form.addClass('yangsheep-checkout-layout ys-checkout-enhanced');
        $('body').addClass('ys-checkout-enhanced');
        $form.find('.yangsheep-same-as-billing, .yangsheep-order-notes-toggle')
            .removeAttr('hidden');
        enableEnhancedStyles();
        syncCheckoutNotices();
        syncSidebarPlacement();
        releaseCheckoutPresentation('enhanced');

        return true;
    }

    function syncCorePaymentPlacement() {
        var $form = $('form.checkout.woocommerce-checkout.ys-checkout-enhanced');
        var $paymentTarget = $form.find('.yangsheep-payment-block');
        var $payment = $form.find('#payment');

        if ($paymentTarget.length === 1 && $payment.length === 1 && !$payment.parent().is($paymentTarget)) {
            $payment.detach().appendTo($paymentTarget);
        }
    }

    // 手機版（<1000px）側邊欄移到付款區前；桌機版移回 form 直屬子元素供 grid 佈局
    function syncSidebarPlacement() {
        var $form = $('form.checkout.woocommerce-checkout.ys-checkout-enhanced');
        var $sidebarWrapper = $form.find('.yangsheep-checkout-sidebar-wrapper');
        var $paymentSection = $form.find('.yangsheep-payment');

        if (!$sidebarWrapper.length || !$paymentSection.length) {
            return;
        }

        if (window.innerWidth < 1000) {
            if (!$sidebarWrapper.next().is($paymentSection)) {
                $sidebarWrapper.detach().insertBefore($paymentSection);
            }
        } else if (!$sidebarWrapper.parent().is($form)) {
            $sidebarWrapper.detach().appendTo($form);
        }
    }

    ensureCheckoutLayout();

    // CSS 於 DOM ready 可能尚未載完（readiness 會擋下）→ window.load 時
    // 所有樣式表已定案，做最終重試；已增強時直接 early-return
    $(window).on('load', function () {
        var enhanced = ensureCheckoutLayout();
        syncCheckoutNotices();
        if (!enhanced) {
            releaseCheckoutPresentation('native');
        }
    });

    $(document.body).on('updated_checkout', function () {
        // 首次載入若 form 尚未就緒（主題延後渲染），在 AJAX 更新後重試；
        // 已增強時 ensureCheckoutLayout 會直接 early-return
        ensureCheckoutLayout();
        syncCorePaymentPlacement();
        syncCheckoutNotices();
        syncSidebarPlacement();
    });

    $(document.body).on('checkout_error', handleCheckoutError);

    $(window).on('resize', syncSidebarPlacement);

    // 側邊欄「購物車內容」摺疊（委派事件 — fragment 重繪後依然有效；
    // 重繪後回到預設展開狀態，與 v1.6.x 行為一致；button + aria-expanded 供鍵盤/AT）
    $(document).on('click', '.yangsheep-collapsible', function () {
        var $toggle = $(this);
        var $content = $('#' + $toggle.data('target'));
        var expanded = $toggle.attr('aria-expanded') !== 'false';
        $toggle.toggleClass('collapsed', expanded)
            .attr('aria-expanded', expanded ? 'false' : 'true');
        $content.slideToggle(200);
    });

    // ===== 1. DOM 初始化移動 =====
    if ($("#account_password").length && $("#nsl-custom-login-form-6").length) {
        $("#nsl-custom-login-form-6").insertAfter($("#account_password"));
    }

    // ===== 1.1 國家選擇區塊：無國家欄位時隱藏 =====
    function toggleCountryBlock() {
        var $countryBlock = $('.yangsheep-checkout-country');
        if (!$countryBlock.length) return;
        // 檢查區塊內是否有 shipping_country_field（已被移入或原本存在）
        if (!$countryBlock.find('#shipping_country_field').length && !$('#shipping_country_field').length) {
            $countryBlock.hide();
        } else {
            $countryBlock.show();
        }
    }
    toggleCountryBlock();

    // WooCommerce AJAX 更新後重新檢查
    $(document.body).on('updated_checkout', function () {
        // DOM 移動可能在 updated_checkout 後執行，延遲檢查
        setTimeout(toggleCountryBlock, 100);
    });

    // ===== 1.5 付款方式選中狀態 =====
    // 使用 class 取代 CSS :has() 選擇器，確保跨瀏覽器相容性
    function updatePaymentMethodSelection() {
        var $methods = $('.wc_payment_methods li.wc_payment_method');

        // 如果沒有付款方式，跳過
        if (!$methods.length) {
            return;
        }

        // 先移除所有選中狀態
        $methods.removeClass('ys-payment-selected');

        // 找到真正被選中的 radio
        var $checkedInput = $('.wc_payment_methods input[type="radio"][name="payment_method"]:checked');

        if ($checkedInput.length) {
            // 只對真正選中的項目加上 class
            $checkedInput.closest('li.wc_payment_method').addClass('ys-payment-selected');
            console.log('[YS Checkout] 付款方式選中:', $checkedInput.val());
        }
    }

    // 初始化時延遲執行（確保 DOM 完全載入）
    setTimeout(updatePaymentMethodSelection, 200);

    // 監聽付款方式切換
    $(document.body).on('change', '.wc_payment_methods input[type="radio"][name="payment_method"]', function() {
        updatePaymentMethodSelection();
    });

    // WooCommerce 結帳更新後重新執行
    $(document.body).on('updated_checkout payment_method_selected', function() {
        setTimeout(updatePaymentMethodSelection, 100);
    });

    // ===== 2. 同訂購人姓名電話複製 =====
    function syncShippingFromBilling() {
        if ($('#yangsheep_copy_billing').is(':checked')) {
            $('#shipping_first_name').val($('#billing_first_name').val());
            $('#shipping_last_name').val($('#billing_last_name').val());
            $('#shipping_phone').val($('#billing_phone').val());
        }
    }

    $('#yangsheep_copy_billing').on('change', function () {
        if (this.checked) {
            syncShippingFromBilling();
        } else {
            $('#shipping_first_name, #shipping_last_name, #shipping_phone').val('');
        }
    });

    $('#billing_first_name, #billing_last_name, #billing_phone').on('input change', function () {
        syncShippingFromBilling();
    });

    if ($('#yangsheep_copy_billing').is(':checked')) {
        syncShippingFromBilling();
    }

    // ===== 3. 購物金相關 =====
    // 移動 WooCommerce Loyalty Rewards (WLR) 購物金訊息到購物金區塊
    // 注意：如果啟用了 WPLoyalty 整合（yangsheep_wployalty 變數存在且 enabled），
    //       會完全交由 yangsheep-wployalty.js 處理，這裡不再干預
    function isYithIntegrationEnabled() {
        if (typeof yangsheep_yith_points === 'undefined') {
            return false;
        }

        // wp_localize_script() serializes booleans as "1" / "" on current
        // WordPress versions. Accept the explicit enabled forms only; arbitrary
        // truthy strings must not activate a disabled integration.
        var enabled = yangsheep_yith_points.enabled;
        return enabled === true || enabled === 1 || enabled === '1';
    }

    function eachYithPreservableCheckoutField(callback) {
        $('form.checkout')
            .first()
            .find('#customer_details :input, .woocommerce-additional-fields :input')
            .each(function () {
                var $field = $(this);
                var name   = $field.attr('name');
                var type   = String($field.attr('type') || '').toLowerCase();

                if (
                    !name
                    || $field.prop('disabled')
                    || $field.closest('.woocommerce-checkout-payment').length
                    || ['button', 'file', 'hidden', 'password', 'reset', 'submit'].indexOf(type) !== -1
                ) {
                    return;
                }

                callback($field, name, type);
            });
    }

    /**
     * YITH can intentionally use a normal POST instead of its AJAX endpoint.
     * Mirror customer-entered checkout values into that POST so WooCommerce can
     * repopulate them through its native checkout value handling after reload.
     * Payment controls and hidden credentials/nonces are deliberately excluded.
     */
    function mirrorCheckoutFieldsIntoYithForm(redeemForm) {
        if (!isYithIntegrationEnabled()) {
            return;
        }

        var $redeemForm  = $(redeemForm);
        var $checkoutForm = $('form.checkout').first();

        if (!$redeemForm.length || !$checkoutForm.length) {
            return;
        }

        $redeemForm.find('.ys-yith-checkout-field-mirror').remove();
        eachYithPreservableCheckoutField(function ($field, name, type) {
            if ((type === 'checkbox' || type === 'radio') && !$field.prop('checked')) {
                return;
            }

            $('<input>', {
                type: 'hidden',
                name: 'ys_yith_checkout_field_names[]',
                value: name,
                class: 'ys-yith-checkout-field-mirror'
            }).appendTo($redeemForm);

            var values = $field.val();
            if (!Array.isArray(values)) {
                values = [values];
            }

            values.forEach(function (value) {
                $('<input>', {
                    type: 'hidden',
                    name: name,
                    value: value == null ? '' : value,
                    class: 'ys-yith-checkout-field-mirror'
                }).appendTo($redeemForm);
            });
        });
    }

    $(document).on('submit.ysCheckoutYithPreserve', 'form.ywpar_apply_discounts', function () {
        mirrorCheckoutFieldsIntoYithForm(this);
    });

    // Capture the click before YITH's handler decides between AJAX and form POST.
    document.addEventListener('click', function (event) {
        var button = event.target.closest && event.target.closest('#ywpar_apply_discounts');
        var form   = button && button.closest('form.ywpar_apply_discounts');

        if (form) {
            mirrorCheckoutFieldsIntoYithForm(form);
        }
    }, true);

    var yithCheckoutFieldRestoreTimer = null;
    var yithCheckoutFieldRestoreCompleted = false;
    var yithCheckoutFieldRestoreStartedAt = Date.now();
    var YITH_FIELD_RESTORE_WINDOW_MS = 6000;
    var YITH_FIELD_RESTORE_INTERVAL_MS = 500;

    function restoreYithCheckoutFields() {
        if (yithCheckoutFieldRestoreCompleted) {
            return;
        }
        if (
            typeof yangsheep_yith_points === 'undefined'
            || !yangsheep_yith_points.preservedFields
            || typeof yangsheep_yith_points.preservedFields !== 'object'
        ) {
            yithCheckoutFieldRestoreCompleted = true;
            return;
        }

        var preservedFieldNames = Object.keys(yangsheep_yith_points.preservedFields);
        if (!preservedFieldNames.length) {
            yithCheckoutFieldRestoreCompleted = true;
            return;
        }

        var $fields = $('form.checkout')
            .first()
            .find('#customer_details :input, .woocommerce-additional-fields :input');
        preservedFieldNames.forEach(function (name) {
            var value = yangsheep_yith_points.preservedFields[name];
            var normalizedName = String(name).replace(/\[\]$/, '');
            var $matching = $fields.filter(function () {
                return String($(this).attr('name') || '').replace(/\[\]$/, '') === normalizedName;
            });

            if (!$matching.length || $matching.closest('.woocommerce-checkout-payment').length) {
                return;
            }

            var type = String($matching.first().attr('type') || '').toLowerCase();
            var expectedValues = Array.isArray(value)
                ? value.map(function (item) { return String(item); })
                : [String(value)];
            if (type === 'radio') {
                $matching.prop('checked', false).filter(function () {
                    return expectedValues.indexOf(String($(this).val())) !== -1;
                }).prop('checked', true);
            } else if (type === 'checkbox') {
                if (Array.isArray(value) || $matching.length > 1) {
                    $matching.prop('checked', false).filter(function () {
                        return expectedValues.indexOf(String($(this).val())) !== -1;
                    }).prop('checked', true);
                } else {
                    $matching.prop('checked', expectedValues[0] !== '' && expectedValues[0] !== '0');
                }
            } else {
                $matching.val(value);
            }
        });

        if (Date.now() - yithCheckoutFieldRestoreStartedAt >= YITH_FIELD_RESTORE_WINDOW_MS) {
            yithCheckoutFieldRestoreCompleted = true;
            return;
        }

        clearTimeout(yithCheckoutFieldRestoreTimer);
        yithCheckoutFieldRestoreTimer = setTimeout(restoreYithCheckoutFields, YITH_FIELD_RESTORE_INTERVAL_MS);
    }

    function scheduleYithCheckoutFieldRestore(delay) {
        if (yithCheckoutFieldRestoreCompleted) {
            return;
        }
        clearTimeout(yithCheckoutFieldRestoreTimer);
        yithCheckoutFieldRestoreTimer = setTimeout(restoreYithCheckoutFields, delay);
    }

    scheduleYithCheckoutFieldRestore(250);
    $(document.body).on('updated_checkout.ysYithFieldRestore', function () {
        scheduleYithCheckoutFieldRestore(300);
    });

    function selectVisibleYithMessage(selectors) {
        var $candidates = $();

        (selectors || []).forEach(function (selector) {
            $candidates = $candidates.add($(selector));
        });

        // AJAX 後重新評估。只還原先前由 YS 標記的 duplicate，不碰
        // YITH 自己原本隱藏的 submit target。
        $candidates.filter('.ys-yith-points-duplicate').each(function () {
            $(this)
                .removeClass('ys-yith-points-duplicate')
                .removeAttr('aria-hidden')
                .css('display', '');
        });

        var $visible = $candidates.filter(function () {
            var $message = $(this);
            var hasUsefulContent = $.trim($message.text()).length > 0;

            return $message.is(':visible') && hasUsefulContent;
        });

        // `#yith-par-message-cart` 可能只是「本次可賺取點數」提示；只把
        // 真正含可見互動控制的兌換 surface 納入搬移與去重。
        var $redeemSurfaces = $visible.filter(function () {
            return $(this).find('input:visible:not([type="hidden"]), button:visible, a:visible, select:visible, textarea:visible').length > 0;
        });

        var $selected = $redeemSurfaces.first();

        return {
            selected: $selected,
            duplicates: $redeemSurfaces.not($selected)
        };
    }

    /**
     * P0 防線：任何含 <form> 的第三方節點都「不得」被移入 form.checkout。
     * Woo/YITH fragment 以 HTML 字串重繪節點時，若節點位於 form.checkout 內，
     * HTML fragment parser 會因外層已有 form 而丟棄內層 <form> 標籤 —
     * 兌換按鈕的 form owner 變成 checkout form，按「套用折抵」會直接觸發
     * checkout_place_order / 建立付款（實測證實）。
     *
     * 因此 YITH 兌換介面改走「視覺 proxy」（與物流卡片同一模式）：
     * 原生介面留在 form 外原位置（= 提交事實源，fragment 重繪保有 <form>），
     * coupon 區內放淨化後的視覺代理；代理按鈕只同步點數值並觸發原生按鈕。
     */
    function disposeYithProxy($proxy) {
        $proxy.each(function () {
            var $item = $(this);
            var observer = $item.data('ysYithDiscountObserver');
            if (observer && typeof observer.disconnect === 'function') {
                observer.disconnect();
            }
            clearTimeout($item.data('ysYithConversionFallbackTimer'));
        });
        $proxy.remove();
    }

    function buildYithProxy($source, $pointBlock) {
        var $old = $pointBlock.children('.ys-yith-proxy');
        var typedValue = $old.find('.ys-yith-proxy-points').val();
        disposeYithProxy($old);

        var i18n = (typeof yangsheep_yith_points !== 'undefined' && yangsheep_yith_points.i18n) || {};
        var $realInput = $source.find('input[name="ywpar_input_points"]').first();
        var $realAction = $source.find(
            'button[name="ywpar_apply_discounts"], input[type="submit"][name="ywpar_apply_discounts"], #ywpar_apply_discounts'
        ).first();
        var $realForm = $realInput.closest('form.ywpar_apply_discounts');
        var $realDiscount = $source.find('.woocommerce-Price-amount').first();

        // 完整 provider contract 不存在就不建立 proxy、不隱藏原生。
        if (!$realInput.length || !$realAction.length || !$realForm.length) {
            console.warn('[YS Checkout] YITH native redeem contract incomplete; keeping native UI');
            return $();
        }
        var parsePoints = function(value) {
            var parsed = parseInt(String(value || '').replace(/[^\d]/g, ''), 10);
            return Number.isFinite(parsed) ? parsed : 0;
        };
        var minimum = Math.max(1, parsePoints($realInput.attr('min')) || 1);
        var maximum = parsePoints($realInput.attr('max')) || parsePoints($realInput.val());
        var current = parsePoints(typedValue);
        if (!current) {
            current = maximum || minimum;
        }

        // 只建立 YS 自有、無 name/id/form 的視覺代理。原生 YITH form 永遠留在
        // checkout form 外並持有真正提交資料，避免代理按鈕誤觸 Woo 下單。
        var $proxy = $('<div class="ys-yith-proxy ys-loyalty-provider ys-loyalty-provider--yith" role="group"></div>')
            .attr('aria-label', i18n.title || '購物金折抵');
        var $title = $('<h3 class="yangsheep-h3-title ys-loyalty-title"></h3>')
            .text(i18n.title || '購物金折抵');
        var $row = $('<div class="ys-loyalty-redeem-row"></div>');
        var $pointsInput = $('<input>', {
            type: 'number',
            class: 'ys-yith-proxy-points',
            min: minimum,
            step: 1,
            inputmode: 'numeric',
            value: current,
            'aria-label': i18n.points_label || '折抵點數'
        });
        if (maximum > 0) {
            $pointsInput.attr('max', maximum);
        }
        var $applyBtn = $('<button>', {
            type: 'button',
            class: 'button ys-yith-proxy-apply'
        }).text(i18n.apply || '套用折抵');
        var $useAll = $('<button>', {
            type: 'button',
            class: 'button ys-yith-use-all'
        }).text(i18n.use_all || '全部使用');
        var $conversion = $('<p class="ys-yith-conversion" role="status" aria-live="polite"></p>');
        var $limit = $('<p class="ys-yith-limit"></p>');
        if (maximum > 0) {
            $limit
                .append(document.createTextNode((i18n.maximum_prefix || '本次最多可使用') + ' '))
                .append($('<strong></strong>').text(maximum.toLocaleString()))
                .append(document.createTextNode(' ' + (i18n.points_unit || '點')));
        }
        var $feedback = $('<p class="ys-yith-feedback" role="alert" aria-live="polite"></p>');

        // 版面（2026-07-25 使用者指定）：標題列＝標題靠左＋「本次最多可使用」
        // 靠右垂直置中；輸入框靠左與「套用折抵」「全部使用」同列；不顯示文字 label
        //（aria-label 保留於輸入框供無障礙）。
        var $header = $('<div class="ys-loyalty-header"></div>').append($title);
        if (maximum > 0) {
            $header.append($limit);
        }
        $row.append($pointsInput, $applyBtn);
        if (maximum > 0) {
            $row.append($useAll);
        }
        $proxy.append($header, $row);
        if (maximum > 0) {
            $proxy.append($conversion);
        }
        $proxy.append($feedback);
        $pointBlock.append($proxy);
        $proxy.data('ysYithMaximum', maximum);

        var maximumDiscountText = $.trim($realDiscount.text());
        var renderConversion = function(points, discountText, state) {
            $conversion
                .empty()
                .toggleClass('is-calculating', state === 'calculating')
                .attr('aria-busy', state === 'calculating' ? 'true' : 'false');

            if (state === 'calculating') {
                $conversion.text(i18n.conversion_calculating || '正在換算折抵金額…');
                return;
            }
            if (!discountText) {
                $conversion.text(
                    state === 'invalid'
                        ? (i18n.conversion_invalid || '請輸入有效點數以查看折抵金額。')
                        : (i18n.conversion_unavailable || '折抵金額將於套用時由 YITH 確認。')
                );
                return;
            }

            $conversion
                .append(document.createTextNode((i18n.conversion_prefix || '目前輸入') + ' '))
                .append($('<strong class="ys-yith-conversion-points"></strong>').text(points.toLocaleString()))
                .append(document.createTextNode(' ' + (i18n.points_unit || '點') + '，' + (i18n.conversion_discount || '可折抵') + ' '))
                .append($('<strong class="ys-yith-conversion-amount"></strong>').text(discountText));
        };

        $proxy
            .data('ysYithRealInput', $realInput)
            .data('ysYithMaximumDiscountText', maximumDiscountText)
            .data('ysYithRenderConversion', renderConversion);

        if (maximum > 0 && current === maximum && maximumDiscountText) {
            renderConversion(current, maximumDiscountText, 'ready');
        } else if (current !== maximum) {
            renderConversion(current, '', 'calculating');
        } else {
            renderConversion(current, '', 'unavailable');
        }

        if (window.MutationObserver && $realDiscount.length) {
            var discountObserver = new MutationObserver(function () {
                var discountText = $.trim($realDiscount.text());
                if (!discountText || !$proxy.closest('html').length) {
                    return;
                }
                clearTimeout($proxy.data('ysYithConversionFallbackTimer'));
                var pendingPoints = parseInt($proxy.data('ysYithPendingPoints'), 10) || maximum || current;
                renderConversion(pendingPoints, discountText, 'ready');
            });
            discountObserver.observe($realDiscount[0], {
                childList: true,
                subtree: true,
                characterData: true
            });
            $proxy.data('ysYithDiscountObserver', discountObserver);
        }

        if (current !== maximum) {
            setTimeout(function () {
                $pointsInput.trigger('input');
            }, 0);
        }

        return $proxy;
    }

    $(document).on('input', '.ys-yith-proxy-points', function () {
        var $input = $(this);
        var $proxy = $input.closest('.ys-yith-proxy');
        var rawValue = String($input.val() || '').trim();
        var points = parseInt(rawValue, 10);
        var minimum = parseInt($input.attr('min'), 10) || 1;
        var maximum = parseInt($input.attr('max'), 10) || 0;
        var renderConversion = $proxy.data('ysYithRenderConversion');
        var $realInput = $proxy.data('ysYithRealInput');

        clearTimeout($proxy.data('ysYithConversionFallbackTimer'));
        if (!/^\d+$/.test(rawValue) || !Number.isFinite(points) || points < minimum || (maximum > 0 && points > maximum)) {
            if (typeof renderConversion === 'function') {
                renderConversion(Number.isFinite(points) ? points : 0, '', 'invalid');
            }
            return;
        }

        var maximumDiscountText = $proxy.data('ysYithMaximumDiscountText');
        if (maximum > 0 && points === maximum && maximumDiscountText) {
            if (typeof renderConversion === 'function') {
                renderConversion(points, maximumDiscountText, 'ready');
            }
            return;
        }

        if (!$realInput || !$realInput.length || typeof renderConversion !== 'function') {
            return;
        }

        $proxy.data('ysYithPendingPoints', points);
        $realInput.val(points).trigger('keyup');
        renderConversion(points, '', 'calculating');
        $proxy.data(
            'ysYithConversionFallbackTimer',
            setTimeout(function () {
                renderConversion(points, '', 'unavailable');
            }, 2500)
        );
    });

    $(document).on('click', '.ys-yith-use-all', function () {
        var $proxy = $(this).closest('.ys-yith-proxy');
        var maximum = parseInt($proxy.data('ysYithMaximum'), 10) || 0;
        if (maximum > 0) {
            $proxy.find('.ys-yith-proxy-points')
                .val(maximum)
                .attr('aria-invalid', 'false')
                .trigger('input');
            $proxy.find('.ys-yith-feedback').empty();
        }
    });

    $(document).on('keydown', '.ys-yith-proxy-points', function (event) {
        if (event.key !== 'Enter') {
            return;
        }
        event.preventDefault();
        event.stopPropagation();
        $(this).closest('.ys-yith-proxy').find('.ys-yith-proxy-apply').trigger('click');
    });

    // 代理按鈕 → 同步點數值到原生輸入 → 觸發原生按鈕（document capture 的
    // mirrorCheckoutFieldsIntoYithForm 會先執行，欄位快照照常運作）
    $(document).on('click', '.ys-yith-proxy-apply', function () {
        var $proxy = $(this).closest('.ys-yith-proxy');
        var $source = $('.ys-yith-points-proxied').first();
        if (!$source.length && typeof yangsheep_yith_points !== 'undefined' && Array.isArray(yangsheep_yith_points.selectors)) {
            // fragment 剛替換、rebuild 尚未跑的空窗：直接找含原生 form 的候選
            $source = $(yangsheep_yith_points.selectors.join(', ')).filter(function () {
                return $(this).find('form.ywpar_apply_discounts').length > 0;
            }).first();
        }
        if (!$source.length) {
            console.warn('[YS Checkout] YITH proxy source missing');
            return;
        }
        var $realInput = $source.find('input[name="ywpar_input_points"]');
        var $realBtn = $source.find(
            'button[name="ywpar_apply_discounts"], input[type="submit"][name="ywpar_apply_discounts"], #ywpar_apply_discounts'
        ).first();
        var $realForm = $source.find('form.ywpar_apply_discounts').first();
        var $proxyInput = $proxy.find('.ys-yith-proxy-points');
        var rawValue = String($proxyInput.val() || '').trim();
        var val = parseInt(rawValue, 10);
        var minimum = parseInt($proxyInput.attr('min'), 10) || 0;
        var maximum = parseInt($proxyInput.attr('max'), 10) || 0;
        var i18n = (typeof yangsheep_yith_points !== 'undefined' && yangsheep_yith_points.i18n) || {};
        var $feedback = $proxy.find('.ys-yith-feedback');

        if (!/^\d+$/.test(rawValue) || !Number.isFinite(val) || val < minimum) {
            $proxyInput.attr('aria-invalid', 'true').trigger('focus');
            $feedback.text(i18n.invalid || '請輸入有效的整數點數。');
            return;
        }
        if (maximum > 0 && val > maximum) {
            $proxyInput.attr('aria-invalid', 'true').trigger('focus');
            $feedback.text(i18n.over_limit || '輸入點數不可超過本次可使用上限。');
            return;
        }

        $proxyInput.attr('aria-invalid', 'false');
        $feedback.empty();
        if ($realInput.length) {
            $realInput.val(val);
        }

        // Do not rely on YITH dispatching a native submit event. Some versions
        // call form.submit() from their click handler, which bypasses both the
        // jQuery submit listener and the browser submit event. Mirror the
        // checkout fields deterministically before invoking the provider.
        if ($realForm.length) {
            mirrorCheckoutFieldsIntoYithForm($realForm[0]);
        }

        if ($realBtn.length) {
            $realBtn[0].click();
        } else {
            var realForm = $realForm[0];
            if (realForm && realForm.requestSubmit) {
                realForm.requestSubmit();
            }
        }
    });

    function initPointRedeemBlock() {
        // fail-open gate：未增強時 coupon 區是 hidden 的，任何搬移都會讓
        // 原生購物金介面憑空消失 — 增強成功前一律不動第三方節點
        if (!$('form.checkout').hasClass('ys-checkout-enhanced')) {
            return;
        }

        // v1.6.30：拿掉「WPLoyalty enabled 就整個 return」的行為
        //   - WPLoyalty 整合啟用 → 交由 yangsheep-wployalty.js 處理 WLR
        //   - YITH Points 整合啟用 → 這裡以 proxy 呈現 YITH selector
        var wployaltyEnabled = (typeof yangsheep_wployalty !== 'undefined' && yangsheep_wployalty.enabled);
        var yithEnabled      = (typeof yangsheep_yith_points !== 'undefined' && yangsheep_yith_points.enabled);

        if (wployaltyEnabled && !yithEnabled) {
            console.log('[YS Checkout] no source selectors, skip point block management');
            return;
        }

        var $pointBlock  = $('.yangsheep-coupon-point');
        var $couponBlock = $('.yangsheep-coupon-block');

        if (!$pointBlock.length) {
            return;
        }

        if (yithEnabled && Array.isArray(yangsheep_yith_points.selectors)) {
            var yithMessages = selectVisibleYithMessage(yangsheep_yith_points.selectors);
            var $yithMessage = yithMessages.selected;
            if ($yithMessage.length) {
                // YITH 介面含 <form>：留在原位（事實源），coupon 區放視覺 proxy
                $pointBlock.addClass('has-content').css('display', 'block');
                $couponBlock.addClass('has-point');
                var $yithProxy = buildYithProxy($yithMessage, $pointBlock);

                // 替代介面確實掛載且可見後才隱藏原生；任何樣式或容器故障
                // 都撤回 proxy 並保留原生介面（fail-open）。
                if ($yithProxy.length && $yithProxy.is(':visible')) {
                    $yithMessage.addClass('ys-yith-points-proxied').attr('aria-hidden', 'true').hide();
                    $yithMessage.data('ysYithProxied', true);
                    yithMessages.duplicates
                        .addClass('ys-yith-points-duplicate')
                        .attr('aria-hidden', 'true')
                        .hide();
                } else {
                    disposeYithProxy($yithProxy);
                    $yithMessage.removeClass('ys-yith-points-proxied').removeAttr('aria-hidden').show();
                }
            } else if (!$('.ys-yith-points-proxied').length) {
                // Provider fragment no longer exposes a redeem surface (for
                // example after points are exhausted): remove stale proxy UI.
                disposeYithProxy($pointBlock.children('.ys-yith-proxy'));
            }
        }

        // 根據購物金區塊是否有內容決定顯示/隱藏
        var hasContent = $pointBlock.children().length > 0;

        if (hasContent) {
            $pointBlock.addClass('has-content').css('display', 'block');
            $couponBlock.addClass('has-point');
        } else {
            $pointBlock.removeClass('has-content').css('display', 'none');
            $couponBlock.removeClass('has-point');
        }

        console.log('[YS Checkout] Point block initialized, hasContent:', hasContent);
    }

    // 初始執行（延遲確保 DOM 載入完成）
    setTimeout(initPointRedeemBlock, 500);

    // AJAX 更新後重新檢查
    $(document.body).on('updated_checkout', function() {
        setTimeout(initPointRedeemBlock, 300);
    });

    // 頁面載入完成後再次檢查
    $(window).on('load', function() {
        setTimeout(initPointRedeemBlock, 100);
    });

    // YITH renders or replaces its redeem form through a provider-specific
    // wc-ajax call that does not consistently emit WooCommerce's
    // updated_checkout event. Re-evaluate only after that known response so an
    // initially late surface and the post-removal surface are both enhanced.
    $(document).on('ajaxComplete.ysYithRedeemSurface', function (event, xhr, settings) {
        if (
            settings
            && typeof settings.url === 'string'
            && settings.url.indexOf('ywpar_update_cart_rewards_messages') !== -1
        ) {
            setTimeout(initPointRedeemBlock, 50);
            scheduleYithCheckoutFieldRestore(50);
        }
    });

    // YITH Points and Rewards 同步
    $(document).on('change', 'input[name="ywpar_input_points"]', function () {
        if (!isYithIntegrationEnabled()) {
            return;
        }
        $('#yith-par-message-reward-cart input[name="ywpar_input_points"]').val(this.value || 0);
    });

    // ===== 5. 建立帳號 checkbox =====
    // 直接對密碼/帳號欄位 toggle .ys-show class（不依賴容器嵌套）
    function syncAccountFields() {
        var $checkbox = $('#createaccount');
        // 沒有 checkbox = 強制註冊，密碼必須顯示
        var show = !$checkbox.length || $checkbox.is(':checked');
        var $targets = $('#account_password_field, #account_username_field');
        if (show) {
            $targets.addClass('ys-show');
        } else {
            $targets.removeClass('ys-show');
        }
    }

    $('#createaccount').on('change', syncAccountFields);
    syncAccountFields(); // 初始狀態

    // WooCommerce AJAX 更新後重新同步
    $(document.body).on('updated_checkout', syncAccountFields);

    // ===== 6. 訂單備註 checkbox =====
    function syncOrderCommentsVisibility(animate) {
        var $toggle = $('#yangsheep_show_order_notes');
        var $field  = $('#order_comments_field');

        if (
            !$('form.checkout').hasClass('ys-checkout-enhanced')
            || !$toggle.length
            || !$field.length
        ) {
            return;
        }

        $field.stop(true, true);
        if ($toggle.is(':checked')) {
            animate ? $field.slideDown(200) : $field.show();
        } else {
            animate ? $field.slideUp(200) : $field.hide();
        }
    }

    $(document).on('change', '#yangsheep_show_order_notes', function () {
        syncOrderCommentsVisibility(true);
    });
    syncOrderCommentsVisibility(false);

    // ===== 7. 台灣地址 Twzipcode 模組 =====
    /**
     * YS Taiwan Address Module
     * 統一處理台灣縣市、鄉鎮市區、郵遞區號下拉選單
     *
     * 功能：
     * 1. 將 WooCommerce 原生輸入欄位轉換為 twzipcode 下拉選單
     * 2. 自動驗證並同步初始值（縣市、鄉鎮市區、郵遞區號）
     * 3. 處理無效值時自動重置為「請選擇」
     *
     * @version 2.6.0
     */
    var YSTaiwanAddress = {
        // 設定
        config: {
            debug: true,
            updateDelay: 300,
            initDelay: 100,
            cityLoadDelay: 150
        },

        // 狀態
        state: {
            initialized: false,
            postcodeTimer: null
        },

        // 選擇器
        selectors: {
            stateSelect: 'select[name="shipping_state_tw"]',
            citySelect: 'select[name="shipping_city_tw"]',
            postcodeInput: 'input[name="shipping_postcode_tw"]',
            stateHidden: '#shipping_state',
            cityHidden: '#shipping_city',
            postcodeHidden: '#shipping_postcode',
            countrySelect: '#shipping_country',
            elementClass: 'yangsheep-twzipcode-element'
        },

        /**
         * 日誌輸出
         */
        log: function() {
            if (this.config.debug) {
                var args = Array.prototype.slice.call(arguments);
                args.unshift('[YS Address]');
                console.log.apply(console, args);
            }
        },

        /**
         * 檢查值是否為 select 的有效選項
         */
        isValidOption: function($select, value) {
            if (!value || !$select.length) return false;
            return $select.find('option').filter(function() {
                return $(this).val() === value;
            }).length > 0;
        },

        /**
         * 取得目前的 twzipcode 元素
         */
        getElements: function() {
            return {
                $state: $(this.selectors.stateSelect),
                $city: $(this.selectors.citySelect),
                $postcode: $(this.selectors.postcodeInput),
                $stateHidden: $(this.selectors.stateHidden),
                $cityHidden: $(this.selectors.cityHidden),
                $postcodeHidden: $(this.selectors.postcodeHidden)
            };
        },

        /**
         * 同步 twzipcode 值到隱藏欄位
         */
        syncToHidden: function() {
            var els = this.getElements();
            var state = els.$state.val() || '';
            var city = els.$city.val() || '';
            var postcode = els.$postcode.val() || '';

            els.$stateHidden.val(state);
            els.$cityHidden.val(city);
            els.$postcodeHidden.val(postcode);

            this.log('同步到隱藏欄位:', { state: state, city: city, postcode: postcode });
        },

        /**
         * 清空所有欄位值
         */
        clearAll: function() {
            var els = this.getElements();
            els.$state.val('');
            els.$city.val('');
            els.$postcode.val('');
            els.$stateHidden.val('');
            els.$cityHidden.val('');
            els.$postcodeHidden.val('');
            this.log('已清空所有欄位');
        },

        /**
         * 驗證並設定初始值
         * @param {string} initState - 初始縣市值
         * @param {string} initCity - 初始鄉鎮市區值
         * @param {string} initPostcode - 初始郵遞區號值
         */
        validateAndSetInitialValues: function(initState, initCity, initPostcode) {
            var self = this;
            var els = this.getElements();

            this.log('驗證初始值:', { state: initState, city: initCity, postcode: initPostcode });

            // 1. 驗證並設定縣市
            if (initState && this.isValidOption(els.$state, initState)) {
                els.$state.val(initState).trigger('change');
                this.log('縣市有效，已設定:', initState);
            } else {
                // 縣市無效，全部重置
                els.$state.val('').trigger('change');
                this.clearAll();
                if (initState) {
                    this.log('縣市無效，已重置:', initState);
                }
                return; // 縣市無效就不用繼續了
            }

            // 2. 延遲驗證鄉鎮市區（等待縣市 change 觸發區選項載入）
            setTimeout(function() {
                if (initCity && self.isValidOption(els.$city, initCity)) {
                    els.$city.val(initCity).trigger('change');
                    self.log('鄉鎮市區有效，已設定:', initCity);

                    // 3. 驗證郵遞區號（再延遲一下確保 twzipcode 更新完成）
                    setTimeout(function() {
                        var currentPostcode = els.$postcode.val();
                        // 如果 twzipcode 自動填入的郵遞區號與初始值不同，以 twzipcode 為準
                        if (currentPostcode && currentPostcode !== initPostcode) {
                            self.log('郵遞區號已由 twzipcode 自動更新:', currentPostcode);
                        } else if (!currentPostcode) {
                            self.log('郵遞區號為空，可能需要檢查');
                        }
                        self.syncToHidden();
                    }, 50);
                } else {
                    // 鄉鎮市區無效，清空區和郵遞區號
                    els.$city.val('').trigger('change');
                    els.$cityHidden.val('');
                    els.$postcodeHidden.val('');
                    if (initCity) {
                        self.log('鄉鎮市區無效，已重置:', initCity);
                    }
                }
            }, self.config.cityLoadDelay);
        },

        /**
         * 區選擇時的處理
         */
        onDistrictSelect: function() {
            var self = this;
            this.syncToHidden();

            clearTimeout(this.state.postcodeTimer);
            this.state.postcodeTimer = setTimeout(function() {
                $(document.body).trigger('update_checkout');
            }, this.config.updateDelay);
        },

        /**
         * 初始化 twzipcode
         */
        init: function() {
            var self = this;

            // 檢查 twzipcode 函式庫
            if (typeof $.fn.twzipcode !== 'function') {
                this.log('twzipcode 函式庫未載入');
                return;
            }

            // 檢查國家
            var country = $(this.selectors.countrySelect).val();
            if (country !== 'TW') {
                this.destroy();
                return;
            }

            // 已初始化且元素存在則跳過
            if (this.state.initialized && $('.' + this.selectors.elementClass).length > 0) {
                return;
            }

            this.log('開始初始化');

            // 取得初始值（在建立 twzipcode 之前）
            var initState = $(this.selectors.stateHidden).val() || '';
            var initCity = $(this.selectors.cityHidden).val() || '';
            var initPostcode = $(this.selectors.postcodeHidden).val() || '';

            // 建立暫時容器
            var $cont = $('<div id="shipping-zipcode-fields-temp"></div>').appendTo('body').hide();

            // 初始化 twzipcode
            $cont.twzipcode({
                countyName: 'shipping_state_tw',
                districtName: 'shipping_city_tw',
                zipcodeName: 'shipping_postcode_tw',
                readonly: true,
                detect: false,
                onCountySelect: function() { self.syncToHidden(); },
                onDistrictSelect: function() { self.onDistrictSelect(); }
            });

            // 標記元素
            $cont.find('select, input').addClass(this.selectors.elementClass);

            // 隱藏原生欄位
            $('#shipping_state_field .woocommerce-input-wrapper').hide();
            $('#shipping_city_field .woocommerce-input-wrapper').hide();
            $('#shipping_postcode_field .woocommerce-input-wrapper').hide();

            // 移動元素到對應位置
            $('#shipping_state_field').append($cont.find(this.selectors.stateSelect));
            $('#shipping_city_field').append($cont.find(this.selectors.citySelect));
            $('#shipping_postcode_field').append($cont.find(this.selectors.postcodeInput).addClass('input-text'));

            // 移除暫時容器
            $cont.remove();

            this.state.initialized = true;
            this.log('twzipcode 元素已建立');

            // 驗證並設定初始值
            setTimeout(function() {
                self.validateAndSetInitialValues(initState, initCity, initPostcode);
            }, self.config.initDelay);
        },

        /**
         * 銷毀 twzipcode
         */
        destroy: function() {
            if (!this.state.initialized && $('.' + this.selectors.elementClass).length === 0) {
                return;
            }

            this.log('銷毀 twzipcode');

            $('.' + this.selectors.elementClass).remove();
            $('#shipping-zipcode-fields-temp').remove();

            $('#shipping_state_field .woocommerce-input-wrapper').show();
            $('#shipping_city_field .woocommerce-input-wrapper').show();
            $('#shipping_postcode_field .woocommerce-input-wrapper').show();

            this.state.initialized = false;
        },

        /**
         * 檢查並重新初始化（用於 AJAX 更新後）
         */
        checkAndReinit: function() {
            var country = $(this.selectors.countrySelect).val();
            if (country === 'TW' && $('.' + this.selectors.elementClass).length === 0) {
                this.state.initialized = false;
                var self = this;
                setTimeout(function() { self.init(); }, 150);
            }
        },

        /**
         * 綁定事件
         */
        bindEvents: function() {
            var self = this;

            // 國家變更
            $(document.body).on('change', this.selectors.countrySelect, function() {
                var country = $(this).val();
                self.log('國家變更:', country);

                if (country === 'TW') {
                    setTimeout(function() { self.init(); }, 100);
                } else {
                    self.destroy();
                }
            });

            // WooCommerce 結帳更新
            $(document.body).on('updated_checkout', function() {
                self.log('updated_checkout 事件');
                self.checkAndReinit();
            });
        },

        /**
         * 啟動模組
         */
        start: function() {
            var self = this;
            this.bindEvents();

            // 初次執行
            setTimeout(function() {
                var country = $(self.selectors.countrySelect).val();
                if (country === 'TW') {
                    self.init();
                }
            }, 300);

            this.log('模組啟動完成');
        }
    };

    // 啟動台灣地址模組
    YSTaiwanAddress.start();

    // 暴露到全域供除錯使用
    window.YSTaiwanAddress = YSTaiwanAddress;

    // ===== 8. 商品數量控制 =====
    // v1.6.26: debounce timer 改為每商品獨立（cartKey → timer）。
    // 共用單一 timer 時，第二個商品的點擊會 clearTimeout 掉第一個商品尚未送出的更新，
    // 造成第一項數量遺失（實測：兩商品各 +1 → 只有第二項生效）。
    var qtyTimers = {};
    var QTY_DEBOUNCE_MS = 1500;

    function qtyTimersPending() {
        var k;
        for (k in qtyTimers) {
            if (Object.prototype.hasOwnProperty.call(qtyTimers, k)) return true;
        }
        return false;
    }

    /**
     * v1.6.26: 購物車突變鎖（實體在途證據模型）
     *
     * 點擊 +/-/移除 到 updated_checkout 重繪完成之間，畫面數量與伺服器 cart 不一致
     * （debounce 1.5s + AJAX + update_order_review ≈ 3 秒）。此期間若送出結帳，
     * 訂單會以「舊 cart」建單（實測產生數量/金額錯誤訂單）。
     *
     * 鎖定條件 = 三種在途證據任一存在（每個事件點重算，不用單一 boolean）：
     *   1. qty debounce timer 在途（qtyTimers，每商品獨立）
     *   2. cart 突變 AJAX 在飛（mutationXhrs 集合，readyState 過濾自癒）
     *   3. cart 已寫入、等待重繪（redrawNeeded＝佇列進行中累積 / awaitingRedraw＝佇列清空後的單次重繪在途）
     *
     * 關鍵性質：
     * - 無關來源（配送方式/地址）觸發的 updated_checkout 只有在「awaitingRedraw 已設
     *   （＝我們已 trigger）且當下無 update_order_review 在飛」時才會結算證據 3；
     *   證據 1/2 仍在（debounce 未到期、AJAX 在飛）就維持鎖定——
     *   前一輪或別人的完成事件不會提前解鎖下一輪突變。
     * - fail-closed 看門狗：只處理「證據 3 卡死」（updated_checkout 丟失）。檢查點上
     *   若 update_order_review XHR 仍在飛（readyState < 4）＝慢而未壞 → 續等；
     *   只有確認無任何在途請求才視為丟失並結算（此時 cart 已由伺服器定案）。
     *   證據 1/2 天然自癒（timer 必到期、XHR 必 complete），不設逾時開閘。
     */
    var mutationXhrs = [];       // cart 突變 AJAX 的 jqXHR 集合
    var awaitingRedraw = false;  // 本 generation 的 cart 已全部寫入、等待重繪落地（單一旗標，非計數）
    var awaitingOwnXhrStart = false; // 已 trigger、但本代的 update_order_review 尚未發出
                                     //（WC 原生在 update_checkout 後延遲 5ms 才發請求——這段空窗內
                                     //  舊請求完成觸發的 updated_checkout 不得結算本代）
    var ownRedrawXhr = null;         // 本代實際綁定的 update_order_review jqXHR（generation token）
    var redrawWatchdog = null;
    var REDRAW_WATCHDOG_MS = 10000;
    var trackedUpdateXhr = null; // 最近的 update_order_review jqXHR（看門狗判「慢 vs 丟失」用）

    $(document).ajaxSend(function (e, xhr, settings) {
        if (settings && typeof settings.url === 'string' && settings.url.indexOf('wc-ajax=update_order_review') !== -1) {
            trackedUpdateXhr = xhr;
            if (awaitingOwnXhrStart) {
                // 本代 trigger 後的第一個 update_order_review ＝ 本代的重繪請求
                awaitingOwnXhrStart = false;
                ownRedrawXhr = xhr;
            } else if (awaitingRedraw && ownRedrawXhr && ownRedrawXhr.readyState === 0) {
                // 本代請求被 wc-checkout abort（別的觸發合併重發）→ 繼任請求同樣載有本代
                // 已寫入的 cart 變更，改綁繼任者
                ownRedrawXhr = xhr;
            }
        }
    });

    /**
     * v1.6.26: cart 突變串行佇列（generation 化）。
     *
     * 兩條 qty/remove AJAX 併發時，PHP 端各自載入 WC session cart 再寫回，
     * 後完成者會以舊快照覆蓋先完成者（last-writer-wins）——改為一次只飛一條，
     * 前一條 complete（含 abort）才發下一條。
     *
     * 重繪也 generation 化：各筆 mutation 成功只標記 redrawNeeded，
     * 「佇列全部清空」才觸發**單次** update_checkout（中途不觸發 → 不與
     * update_order_review 交錯、wc-checkout 不會 abort 我們的重算）。
     */
    var mutationQueue = [];
    var mutationActive = false;
    var redrawNeeded = false;

    function drainMutationQueue() {
        // 上一代重繪尚未落地時暫停：新一代的 cart AJAX 與上一代的 update_order_review
        // 併發會對 WC session 再現 last-writer-wins——結算/看門狗處會恢復 drain
        if (mutationActive || awaitingRedraw || mutationQueue.length === 0) return;
        mutationActive = true;
        var job = mutationQueue.shift();
        job(function () {
            mutationActive = false;
            if (mutationQueue.length > 0) {
                drainMutationQueue();
            } else if (redrawNeeded) {
                // 本 generation 的 cart 變更已全部寫入伺服器 → 單次重繪
                redrawNeeded = false;
                awaitingRedraw = true;
                awaitingOwnXhrStart = true; // 本代 XHR 尚未發出（WC 5ms pre-send 空窗起點）
                ownRedrawXhr = null;
                $(document.body).trigger('update_checkout');
                armRedrawWatchdog();
            }
            refreshMutationLock();
        });
    }

    function enqueueCartMutation(job) {
        mutationQueue.push(job);
        refreshMutationLock();
        drainMutationQueue();
    }

    function mutationInFlight() {
        // readyState 4 = 完成、0 = 已 abort（abort 後永遠停在 0，不能視為在途）
        mutationXhrs = mutationXhrs.filter(function (x) { return x && x.readyState !== 4 && x.readyState !== 0; });
        return qtyTimersPending() || mutationActive || mutationQueue.length > 0 || mutationXhrs.length > 0 || redrawNeeded || awaitingRedraw;
    }

    /**
     * v1.6.26: 追蹤突變 XHR——.always() 於 success/error/abort 全路徑移除，
     * readyState 過濾為第二道保險（兩者皆備，abort 不會留下永久在途的殭屍）。
     */
    function trackMutationXhr(xhr) {
        mutationXhrs.push(xhr);
        xhr.always(function () {
            var i = mutationXhrs.indexOf(xhr);
            if (i !== -1) mutationXhrs.splice(i, 1);
            refreshMutationLock();
        });
    }

    /**
     * v1.6.26: 下單按鈕共用鎖（window 級引用計數 registry）。
     *
     * 「各自記取得前狀態」在雙持有情境仍會互解（A 持鎖期間 B 取鎖，A 先釋放
     * 會把按鈕 enable 掉 B 的鎖）→ 改為 window.__ysPlaceOrderLocks 引用計數：
     * YS 系外掛（本外掛 + ys-shopline defer）共用同一協定，count 歸零才考慮
     * enable；第一位取鎖者記下「非參與者是否已 disable」，歸零時尊重之。
     * registry 掛在 window，不受 fragment 替換按鈕節點影響；
     * 持有期間每次 refresh 對新節點 re-assert disabled。
     */
    function ysPlaceOrderLockRegistry() {
        if (!window.__ysPlaceOrderLocks) {
            window.__ysPlaceOrderLocks = { count: 0, externalDisabled: false };
        }
        return window.__ysPlaceOrderLocks;
    }

    function ysAcquirePlaceOrderLock() {
        var reg = ysPlaceOrderLockRegistry();
        var $btn = $('#place_order');
        if (reg.count === 0) {
            reg.externalDisabled = $btn.length ? $btn.prop('disabled') === true : false;
        }
        reg.count++;
        $btn.prop('disabled', true).attr('aria-busy', 'true');
    }

    function ysReleasePlaceOrderLock() {
        var reg = ysPlaceOrderLockRegistry();
        if (reg.count === 0) return;
        reg.count--;
        if (reg.count === 0) {
            var $btn = $('#place_order');
            $btn.removeAttr('aria-busy');
            if (!reg.externalDisabled) {
                $btn.prop('disabled', false);
            }
        }
    }

    var mutationLockHeld = false;

    function refreshMutationLock() {
        var busy = mutationInFlight();
        var $btn = $('#place_order');
        if ($btn.length) {
            if (busy) {
                if (!mutationLockHeld) {
                    mutationLockHeld = true;
                    ysAcquirePlaceOrderLock();
                } else {
                    // re-assert：fragment 替換後的新按鈕節點不帶 disabled，重新套用
                    $btn.prop('disabled', true).attr('aria-busy', 'true');
                }
            } else if (mutationLockHeld) {
                mutationLockHeld = false;
                ysReleasePlaceOrderLock();
            }
            // busy=false 且未持鎖：完全不碰按鈕（別人的鎖不是我們能解的）
        }
        if (awaitingRedraw) {
            armRedrawWatchdog();
        } else {
            clearTimeout(redrawWatchdog);
            redrawWatchdog = null;
        }
    }

    function armRedrawWatchdog() {
        clearTimeout(redrawWatchdog);
        redrawWatchdog = setTimeout(function () {
            if (!awaitingRedraw) return;
            // 本代 XHR 仍在飛（readyState 1-3；0=已 abort 不算）→ fail-closed：續等下一個檢查點
            if (ownRedrawXhr && ownRedrawXhr.readyState !== 4 && ownRedrawXhr.readyState !== 0) {
                armRedrawWatchdog();
                return;
            }
            // 本代尚未認領 XHR，但場上有 update 在飛（可能即為本代、ajaxSend 綁定競態）→ 保守續等
            if (awaitingOwnXhrStart && trackedUpdateXhr && trackedUpdateXhr.readyState !== 4 && trackedUpdateXhr.readyState !== 0) {
                armRedrawWatchdog();
                return;
            }
            // 無任何在途證據 → 本代重繪確認丟失；cart 已由伺服器定案，結算並恢復佇列
            awaitingRedraw = false;
            awaitingOwnXhrStart = false;
            ownRedrawXhr = null;
            refreshMutationLock();
            drainMutationQueue();
        }, REDRAW_WATCHDOG_MS);
    }

    $(document.body).on('updated_checkout', function () {
        // 只結算「本 generation 自己的」重繪（generation token＝ownRedrawXhr）：
        // - awaitingOwnXhrStart（WC 5ms pre-send 空窗）期間收到的一律是舊事件 → 不結算
        // - 只有本代綁定的 XHR 確實完成（readyState 4）才結算；
        //   無關來源（配送/地址）的完成事件、或本代請求仍在飛時，一概不動
        if (awaitingRedraw && !awaitingOwnXhrStart && ownRedrawXhr && ownRedrawXhr.readyState === 4) {
            awaitingRedraw = false;
            ownRedrawXhr = null;
            drainMutationQueue(); // 上一代已落地 → 恢復被暫停的下一代佇列
        }
        refreshMutationLock();
    });

    // 唯讀診斷快照（支援/除錯用，不影響任何行為）
    window.__ysMutationLockDebug = function () {
        return {
            queue: mutationQueue.length,
            active: mutationActive,
            redrawNeeded: redrawNeeded,
            awaitingRedraw: awaitingRedraw,
            awaitingOwnXhrStart: awaitingOwnXhrStart,
            ownXhrState: ownRedrawXhr ? ownRedrawXhr.readyState : null,
            qtyTimers: Object.keys(qtyTimers).length,
            qtyTimerKeys: Object.keys(qtyTimers).map(function (k) { return String(k).substring(0, 8); }),
            xhrs: mutationXhrs.length,
            lockHeld: mutationLockHeld,
            registry: window.__ysPlaceOrderLocks ? { count: window.__ysPlaceOrderLocks.count, ext: window.__ysPlaceOrderLocks.externalDisabled } : null
        };
    };

    function updateCartQuantity(cartKey, quantity, done) {
        if (!window.wc_checkout_params) {
            if (done) done();
            return;
        }

        var xhr = $.ajax({
            url: wc_checkout_params.ajax_url,
            type: 'POST',
            data: {
                action: 'yangsheep_update_cart_qty',
                cart_item_key: cartKey,
                quantity: quantity,
                nonce: ysCheckoutNonce
            },
            success: function (response) {
                if (response.success) {
                    // cart 已寫入伺服器 → 標記需要重繪；重繪由佇列清空時統一觸發一次
                    redrawNeeded = true;
                }
                // response 異常：cart 未變，XHR complete 後證據 2 自然消失
                refreshMutationLock();
            },
            error: function () {
                refreshMutationLock();
            }
        });
        trackMutationXhr(xhr);
        if (done) xhr.always(done); // 串行佇列：complete（含 abort）才放行下一條
        refreshMutationLock();
    }

    function removeCartItem(cartKey, done) {
        if (!window.wc_checkout_params) {
            if (done) done();
            return;
        }

        var xhr = $.ajax({
            url: wc_checkout_params.ajax_url,
            type: 'POST',
            data: {
                action: 'yangsheep_remove_cart_item',
                cart_item_key: cartKey,
                nonce: ysCheckoutNonce
            },
            success: function (response) {
                if (response.success) {
                    // cart 已寫入伺服器 → 標記需要重繪；重繪由佇列清空時統一觸發一次
                    redrawNeeded = true;
                }
                refreshMutationLock();
            },
            error: function () {
                refreshMutationLock();
            }
        });
        trackMutationXhr(xhr);
        if (done) xhr.always(done);
        refreshMutationLock();
    }

    $(document).on('click', '.yangsheep-qty-minus', function () {
        var $control = $(this).closest('.yangsheep-quantity-control');
        var $value = $control.find('.yangsheep-qty-value');
        var currentVal = parseInt($value.text(), 10) || 1;
        var cartKey = $control.data('cart-key');

        if (currentVal > 1) {
            var newVal = currentVal - 1;
            $value.text(newVal);
            if (qtyTimers[cartKey]) clearTimeout(qtyTimers[cartKey]); // 同商品連按重排；不影響其他商品
            qtyTimers[cartKey] = setTimeout(function () {
                delete qtyTimers[cartKey];
                enqueueCartMutation(function (done) {
                    updateCartQuantity(cartKey, newVal, done);
                });
            }, QTY_DEBOUNCE_MS);
            refreshMutationLock(); // 證據 1：per-item debounce 在途
        }
    });

    $(document).on('click', '.yangsheep-qty-plus', function () {
        var $control = $(this).closest('.yangsheep-quantity-control');
        var $value = $control.find('.yangsheep-qty-value');
        var currentVal = parseInt($value.text(), 10) || 1;
        var maxVal = $control.data('max');
        var cartKey = $control.data('cart-key');

        if (!maxVal || currentVal < maxVal) {
            var newVal = currentVal + 1;
            $value.text(newVal);
            if (qtyTimers[cartKey]) clearTimeout(qtyTimers[cartKey]); // 同商品連按重排；不影響其他商品
            qtyTimers[cartKey] = setTimeout(function () {
                delete qtyTimers[cartKey];
                enqueueCartMutation(function (done) {
                    updateCartQuantity(cartKey, newVal, done);
                });
            }, QTY_DEBOUNCE_MS);
            refreshMutationLock(); // 證據 1：per-item debounce 在途
        }
    });

    $(document).on('click', '.yangsheep-remove-item', function () {
        var cartKey = $(this).data('cart-key');
        var $row = $(this).closest('tr');
        $row.css('opacity', '0.5');
        enqueueCartMutation(function (done) {
            removeCartItem(cartKey, done); // 內部 push mutationXhrs（證據 2）並刷新鎖
        });
    });

    // ===== 9. 超取/宅配欄位切換 =====
    /**
     * 根據運送方式切換地址欄位顯示
     * - 超取時：隱藏地址欄位（郵遞區號、縣市、區、地址）
     * - 宅配時：顯示地址欄位
     *
     * 使用 CSS class 控制，避免被 WooCommerce AJAX 覆蓋
     * 優先使用後台設定的超取物流清單，若未設定則使用自動偵測
     *
     * @version 2.4.0
     * @since 2026-01-12
     */
    var lastShippingMethod = null;
    var shippingFieldsTimer = null;
    var payuniAddressRestoreTimer = null;

    // 取得後台設定的超取物流方式清單
    var cvsShippingMethods = (typeof yangsheep_checkout_params !== 'undefined' && yangsheep_checkout_params.cvs_shipping_methods)
        ? yangsheep_checkout_params.cvs_shipping_methods
        : [];

    // 單一 package 的物流是否為超商（後台指定優先，否則自動偵測）。
    // 規則需與 PHP YSCheckoutFields::is_single_method_cvs() 完全一致。
    function isSingleMethodCvs(methodId) {
        methodId = String(methodId || '');
        if (!methodId) return false;

        if (cvsShippingMethods.length > 0) {
            return cvsShippingMethods.some(function(cvsMethod) {
                cvsMethod = String(cvsMethod);
                if (methodId === cvsMethod) return true;
                // 含 ":" = 完整 rate id，只能完整相等，避免 flat_rate:1 前綴誤中 flat_rate:10
                if (cvsMethod.indexOf(':') !== -1) return false;
                // 不含 ":" = 舊版 base id，允許 method base 相等
                return methodId.split(':')[0] === cvsMethod;
            });
        }

        // 自動偵測 allowlist（規則須與 PHP is_single_method_cvs() 完全一致；
        // 比對 base = 去掉 :instance_id 的部分，小寫）
        var base = methodId.split(':')[0].toLowerCase();
        // PayUni 超取
        if (base.indexOf('payuni') !== -1 &&
            (base.indexOf('711') !== -1 || base.indexOf('fami') !== -1 || base.indexOf('hilife') !== -1)) {
            return true;
        }
        // 綠界 ECPay 超取
        if (base.indexOf('ecpay') !== -1 && base.indexOf('cvs') !== -1) {
            return true;
        }
        // YS PayNow 超取（711 / family / hilife；黑貓宅配 tcat 不在清單）
        if (base.indexOf('ys_paynow_shipping') !== -1 &&
            (base.indexOf('711') !== -1 || base.indexOf('family') !== -1 || base.indexOf('hilife') !== -1)) {
            return true;
        }
        // 好用版（woomp）PayNow 已知 C2C 超商方法。限定明確 prefix，
        // 避免把 B2C、宅配或其他未知方法誤判為超商而移除地址要求。
        if ((base.indexOf('paynow_shipping_c2c_') === 0 ||
             base.indexOf('woomp_paynow_shipping_c2c_') === 0) &&
            (base.indexOf('711') !== -1 || base.indexOf('family') !== -1 || base.indexOf('hilife') !== -1)) {
            return true;
        }
        return false;
    }

    function collectSelectedShippingMethodIds() {
        return $('#order_review input.shipping_method').filter(function() {
            return this.type === 'hidden' || this.checked;
        }).map(function() {
            return this.value;
        }).get();
    }

    function isPayuniCvsMethod(methodId) {
        var base = String(methodId || '').split(':')[0].toLowerCase();
        return base.indexOf('payuni') !== -1 &&
            (base.indexOf('711') !== -1 || base.indexOf('fami') !== -1 || base.indexOf('hilife') !== -1);
    }

    /**
     * PAYUNi Store Selector reads only shipping_method[0]. In a mixed-package
     * checkout it can therefore hide address fields after YS correctly decides
     * that at least one package still needs delivery. Reconcile after all
     * synchronous handlers and PAYUNi's delayed initialization have settled.
     */
    function schedulePayuniMixedAddressRestore(methodIds, allMethodsCvs) {
        clearTimeout(payuniAddressRestoreTimer);
        payuniAddressRestoreTimer = null;

        var hasPayuniCvs = methodIds.some(isPayuniCvsMethod);
        var hasNonCvs = methodIds.some(function(methodId) {
            return !isSingleMethodCvs(methodId);
        });

        if (allMethodsCvs || !hasPayuniCvs || !hasNonCvs) {
            return;
        }

        var expectedSignature = methodIds.join('|');
        payuniAddressRestoreTimer = setTimeout(function () {
            payuniAddressRestoreTimer = null;
            var currentSignature = collectSelectedShippingMethodIds().join('|');

            if (currentSignature !== expectedSignature || $('body').hasClass('yangsheep-cvs-mode')) {
                return;
            }

            if (window.PayuniStoreSelector && typeof window.PayuniStoreSelector.showAddressFields === 'function') {
                window.PayuniStoreSelector.showAddressFields();

                if (typeof window.PayuniStoreSelector.showBillingAddressFields === 'function' &&
                    typeof window.payuni_store_selector !== 'undefined' &&
                    window.payuni_store_selector.hide_billing_address_fields) {
                    window.PayuniStoreSelector.showBillingAddressFields();
                }
                return;
            }

            // Compatibility fallback for older PAYUNi versions without the
            // public object. It is deliberately limited to a detected PAYUNi
            // mixed checkout and to fields that PAYUNi itself hides.
            if (typeof window.payuni_store_selector === 'undefined') {
                return;
            }

            var shippingFields = [
                '#shipping_country_field',
                '#shipping_postcode_field',
                '#shipping_state_field',
                '#shipping_city_field',
                '#shipping_address_1_field',
                '#shipping_address_2_field',
                '#shipping_company_field',
                '#shipping_first_name_field',
                '#shipping_last_name_field',
                '#shipping_phone_field'
            ].join(',');

            $(shippingFields).removeClass('payuni-cvs-hide').each(function () {
                this.style.removeProperty('display');
            }).show();
        }, 300);
    }

    function updateShippingFieldsVisibility(forceUpdate) {
        // 🔴 多包裹：收集「所有」package 的物流（shipping_method[0]、[1]…）。
        // WooCommerce 在「單一可用物流」時渲染 <input type="hidden">（不符 :checked），
        // 故須同時納入 hidden 與 checked（與 shipping-cards.js 的 source-of-truth 一致）。
        // 全域地址只在「全部包裹都是超商」時隱藏；任一宅配包裹 → 保留地址（fail-safe）。
        var methodIds = collectSelectedShippingMethodIds();

        // signature 作為 cache key（涵蓋所有 package，避免只比第一個）
        var signature = methodIds.join('|');
        if (!forceUpdate && signature === lastShippingMethod) {
            return;
        }
        lastShippingMethod = signature;
        console.log('[YS Checkout] 運送方式(全部 package):', methodIds);

        var isCVS = methodIds.length > 0 && methodIds.every(isSingleMethodCvs);
        console.log('[YS Checkout] 全部包裹皆超取:', isCVS);

        // 使用 body class 控制（不會被 AJAX 覆蓋）
        if (isCVS) {
            console.log('[YS Checkout] 超取模式：隱藏地址欄位');
            $('body').addClass('yangsheep-cvs-mode');
        } else {
            console.log('[YS Checkout] 宅配/混合模式：顯示地址欄位');
            $('body').removeClass('yangsheep-cvs-mode');
        }

        schedulePayuniMixedAddressRestore(methodIds, isCVS);
    }

    // Debounced 版本
    function debouncedUpdateShippingFields(forceUpdate) {
        clearTimeout(shippingFieldsTimer);
        shippingFieldsTimer = setTimeout(function () {
            updateShippingFieldsVisibility(forceUpdate);
        }, 50);
    }

    // 運送方式變更時更新欄位
    $(document.body).on('change', 'input[name^="shipping_method"]', function () {
        console.log('[YS Checkout] 運送方式變更');
        debouncedUpdateShippingFields(true);
    });

    // 結帳頁面更新後（國家變更、運費計算等）
    $(document.body).on('updated_checkout', function () {
        debouncedUpdateShippingFields(true);
    });

    // 初次執行（不需等待太久）
    $(document).ready(function () {
        setTimeout(function () {
            updateShippingFieldsVisibility(true);
        }, 100);
    });

    console.log('[YS Checkout] 初始化完成');
});
