// assets/js/yangsheep-checkout.js
// 版本: 2.5.0 - 修正付款方式選中狀態判斷
// 動態控制地址欄位顯示
jQuery(function ($) {
    'use strict';

    console.log('[YS Checkout] v2.1.0 初始化');

    // ===== 1. DOM 初始化移動 =====
    if ($("#order_country_heading").length && $("#shipping_country_field").length) {
        $("#shipping_country_field").insertAfter($("#order_country_heading"));
    }
    if ($("#account_password").length && $("#nsl-custom-login-form-6").length) {
        $("#nsl-custom-login-form-6").insertAfter($("#account_password"));
    }
    if ($("#coupons_list").length && $(".yangsheep-smart-coupon").length) {
        $("#coupons_list").detach().appendTo('.yangsheep-smart-coupon');
    }

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
    function initPointRedeemBlock() {
        // 如果 WPLoyalty 整合已啟用，完全交由 yangsheep-wployalty.js 處理
        if (typeof yangsheep_wployalty !== 'undefined' && yangsheep_wployalty.enabled) {
            console.log('[YS Checkout] WPLoyalty integration enabled, skipping point block management');
            return;
        }

        var $pointBlock = $('.yangsheep-coupon-point');
        var $couponBlock = $('.yangsheep-coupon-block');

        // 只偵測 WLR 購物金訊息 class
        var $wlrMessage = $('.wlr_point_redeem_message').not('.yangsheep-coupon-point *');

        // 如果有 WLR 購物金訊息且不在購物金區塊內，移入
        if ($wlrMessage.length && $pointBlock.length) {
            $wlrMessage.each(function() {
                if (!$(this).closest('.yangsheep-coupon-point').length) {
                    $(this).detach().appendTo($pointBlock);
                }
            });
        }

        // 根據購物金區塊是否有內容決定顯示/隱藏
        if ($pointBlock.length) {
            // 檢查是否有實際內容（排除空白和註解）
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

    // YITH Points and Rewards 同步
    $('input[name="ywpar_input_points"]').on('change', function () {
        $('#yith-par-message-reward-cart input[name="ywpar_input_points"]').val(this.value || 0);
    });

    // ===== 4. 移除 coupon 時強制 reload =====
    $('.woocommerce-checkout').on('click', '.woocommerce-remove-coupon', function () {
        $(document).one('ajaxSuccess', function (event, xhr, settings) {
            if (settings.url && settings.url.includes('wc-ajax=remove_coupon')) {
                location.reload();
            }
        });
    });

    // ===== 5. 建立帳號 checkbox =====
    $('#createaccount').on('change', function () {
        if (this.checked) {
            $('.yangsheep-account-fields').slideDown(200);
        } else {
            $('.yangsheep-account-fields').slideUp(200);
        }
    });

    // ===== 6. 訂單備註 checkbox =====
    $('#yangsheep_show_order_notes').on('change', function () {
        if (this.checked) {
            $('.woocommerce-additional-fields__field-wrapper').slideDown(200);
        } else {
            $('.woocommerce-additional-fields__field-wrapper').slideUp(200);
        }
    });

    // 初始狀態
    if ($('#yangsheep_show_order_notes').length && !$('#yangsheep_show_order_notes').is(':checked')) {
        $('.woocommerce-additional-fields__field-wrapper').hide();
    }

    // ===== 7. 台灣地址 Twzipcode =====
    // 只處理 twzipcode 初始化，不干擾其他欄位顯示
    var postcodeTimer = null;
    var twzipcodeInitialized = false;

    function initTwzipcode() {
        if (typeof $.fn.twzipcode !== 'function') return;

        var country = $('#shipping_country').val();
        if (country !== 'TW') {
            destroyTwzipcode();
            return;
        }

        // 使用 flag 和 class 來檢查是否已初始化
        if (twzipcodeInitialized && $('.yangsheep-twzipcode-element').length > 0) return;

        console.log('[YS Checkout] 初始化 twzipcode');

        var initState = $('#shipping_state').val() || '';
        var initCity = $('#shipping_city').val() || '';
        var initZipcode = $('#shipping_postcode').val() || '';

        // 建立暫時容器（會在初始化完成後移除）
        var $cont = $('<div id="shipping-zipcode-fields-temp"></div>').appendTo('body').hide();

        function syncInputs() {
            // 使用移動後的 select 元素來同步
            var county = $('select[name="shipping_state_tw"]').val() || '';
            var district = $('select[name="shipping_city_tw"]').val() || '';
            var zipcode = $('input[name="shipping_postcode_tw"]').val() || '';

            $('#shipping_state').val(county);
            $('#shipping_city').val(district);
            $('#shipping_postcode').val(zipcode);
        }

        function onDistrictSelect() {
            syncInputs();
            clearTimeout(postcodeTimer);
            postcodeTimer = setTimeout(function () {
                $(document.body).trigger('update_checkout');
            }, 300);
        }

        $cont.twzipcode({
            countyName: 'shipping_state_tw',
            districtName: 'shipping_city_tw',
            zipcodeName: 'shipping_postcode_tw',
            readonly: true,
            detect: false,
            onCountySelect: syncInputs,
            onDistrictSelect: onDistrictSelect
        });

        $cont.find('select, input').addClass('yangsheep-twzipcode-element');

        // 只隱藏原生的 state/city/postcode 欄位內容
        $('#shipping_state_field .woocommerce-input-wrapper').hide();
        $('#shipping_city_field .woocommerce-input-wrapper').hide();
        $('#shipping_postcode_field .woocommerce-input-wrapper').hide();

        // 移動 twzipcode 元素到各自的 field 內
        $('#shipping_state_field').append($cont.find('select[name="shipping_state_tw"]'));
        $('#shipping_city_field').append($cont.find('select[name="shipping_city_tw"]'));
        $('#shipping_postcode_field').append($cont.find('input[name="shipping_postcode_tw"]').addClass('input-text'));

        // 設定初始值（在元素移動後）
        if (initState || initCity || initZipcode) {
            $('select[name="shipping_state_tw"]').val(initState).trigger('change');
            setTimeout(function() {
                $('select[name="shipping_city_tw"]').val(initCity).trigger('change');
                $('input[name="shipping_postcode_tw"]').val(initZipcode);
            }, 100);
        }

        // 移除暫時容器
        $cont.remove();

        twzipcodeInitialized = true;
    }

    function destroyTwzipcode() {
        if (!twzipcodeInitialized && $('.yangsheep-twzipcode-element').length === 0) return;

        console.log('[YS Checkout] 銷毀 twzipcode');

        $('.yangsheep-twzipcode-element').remove();
        $('#shipping-zipcode-fields-temp').remove();

        $('#shipping_state_field .woocommerce-input-wrapper').show();
        $('#shipping_city_field .woocommerce-input-wrapper').show();
        $('#shipping_postcode_field .woocommerce-input-wrapper').show();

        twzipcodeInitialized = false;
    }

    $(document.body).on('change', '#shipping_country', function () {
        var country = $(this).val();
        console.log('[YS Checkout] 國家變更:', country);

        if (country === 'TW') {
            setTimeout(initTwzipcode, 100);
        } else {
            destroyTwzipcode();
        }
    });

    // updated_checkout 時重新初始化 twzipcode（因為 WC 可能重置 DOM）
    $(document.body).on('updated_checkout', function () {
        console.log('[YS Checkout] updated_checkout');
        var country = $('#shipping_country').val();
        // 檢查 twzipcode 元素是否存在，若不存在則重新初始化
        if (country === 'TW' && $('.yangsheep-twzipcode-element').length === 0) {
            twzipcodeInitialized = false;
            setTimeout(initTwzipcode, 150);
        }
    });

    // 初次執行
    setTimeout(function () {
        var country = $('#shipping_country').val();
        if (country === 'TW') {
            initTwzipcode();
        }
    }, 300);

    // ===== 8. 商品數量控制 =====
    var qtyUpdateTimer = null;
    var QTY_DEBOUNCE_MS = 1500;

    function updateCartQuantity(cartKey, quantity) {
        if (!window.wc_checkout_params) return;

        $.ajax({
            url: wc_checkout_params.ajax_url,
            type: 'POST',
            data: {
                action: 'yangsheep_update_cart_qty',
                cart_item_key: cartKey,
                quantity: quantity
            },
            success: function (response) {
                if (response.success) {
                    $(document.body).trigger('update_checkout');
                }
            }
        });
    }

    function removeCartItem(cartKey) {
        if (!window.wc_checkout_params) return;

        $.ajax({
            url: wc_checkout_params.ajax_url,
            type: 'POST',
            data: {
                action: 'yangsheep_remove_cart_item',
                cart_item_key: cartKey
            },
            success: function (response) {
                if (response.success) {
                    $(document.body).trigger('update_checkout');
                }
            }
        });
    }

    $(document).on('click', '.yangsheep-qty-minus', function () {
        var $control = $(this).closest('.yangsheep-quantity-control');
        var $value = $control.find('.yangsheep-qty-value');
        var currentVal = parseInt($value.text(), 10) || 1;
        var cartKey = $control.data('cart-key');

        if (currentVal > 1) {
            var newVal = currentVal - 1;
            $value.text(newVal);
            clearTimeout(qtyUpdateTimer);
            qtyUpdateTimer = setTimeout(function () {
                updateCartQuantity(cartKey, newVal);
            }, QTY_DEBOUNCE_MS);
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
            clearTimeout(qtyUpdateTimer);
            qtyUpdateTimer = setTimeout(function () {
                updateCartQuantity(cartKey, newVal);
            }, QTY_DEBOUNCE_MS);
        }
    });

    $(document).on('click', '.yangsheep-remove-item', function () {
        var cartKey = $(this).data('cart-key');
        var $row = $(this).closest('tr');
        $row.css('opacity', '0.5');
        removeCartItem(cartKey);
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

    // 取得後台設定的超取物流方式清單
    var cvsShippingMethods = (typeof yangsheep_checkout_params !== 'undefined' && yangsheep_checkout_params.cvs_shipping_methods)
        ? yangsheep_checkout_params.cvs_shipping_methods
        : [];

    function updateShippingFieldsVisibility(forceUpdate) {
        // 取得選中的運送方式
        var $shippingMethod = $('input[name^="shipping_method"]:checked');
        var methodId = $shippingMethod.val() || '';

        // 如果運送方式沒變，且不是強制更新，則跳過
        if (!forceUpdate && methodId === lastShippingMethod) {
            return;
        }

        lastShippingMethod = methodId;
        console.log('[YS Checkout] 運送方式:', methodId);

        var isCVS = false;

        // 判斷是否為超取
        if (cvsShippingMethods.length > 0) {
            // 使用後台設定的物流清單
            // methodId 格式可能是 "flat_rate:12" 或 "ys_paynow_711:1"
            // 需要檢查是否在清單中（包含 rate_id 和 instance_id 的比對）
            isCVS = cvsShippingMethods.some(function(cvsMethod) {
                // 精確比對
                if (methodId === cvsMethod) return true;
                // 比對 method_id 部分（忽略 instance_id）
                var methodBase = methodId.split(':')[0];
                var cvsBase = cvsMethod.split(':')[0];
                return methodBase === cvsBase && methodId.indexOf(cvsMethod) === 0;
            });
            console.log('[YS Checkout] 使用後台設定判斷超取:', isCVS, '清單:', cvsShippingMethods);
        } else {
            // 未設定則使用自動偵測（PayUni、PayNow、ECPay 等）
            // ys_paynow_shipping_711*, ys_paynow_shipping_family*, ys_paynow_shipping_hilife 為超取
            // ys_paynow_shipping_tcat_* 為宅配（需排除）
            isCVS = /payuni.*(711|fami|hilife)|ecpay.*cvs|ys_paynow_shipping_(711|family|hilife)/i.test(methodId);
            // 排除黑貓宅配
            if (/ys_paynow_shipping_tcat/i.test(methodId)) {
                isCVS = false;
            }
            console.log('[YS Checkout] 使用自動偵測判斷超取:', isCVS);
        }

        // 使用 body class 控制（不會被 AJAX 覆蓋）
        if (isCVS) {
            console.log('[YS Checkout] 超取模式：隱藏地址欄位');
            $('body').addClass('yangsheep-cvs-mode');
        } else {
            console.log('[YS Checkout] 宅配模式：顯示地址欄位');
            $('body').removeClass('yangsheep-cvs-mode');
        }
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

