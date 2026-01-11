// assets/js/yangsheep-checkout.js
// 版本: 2.4.0 - 移除 PayNow 支援，專注 PayUni
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
    $(".wlr_point_redeem_message").detach().appendTo('.yangsheep-coupon-point');
    var cp = document.querySelector(".yangsheep-coupon-point");
    if (cp && !cp.innerHTML.trim()) cp.style.display = 'none';

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

    function initTwzipcode() {
        if (typeof $.fn.twzipcode !== 'function') return;

        var country = $('#shipping_country').val();
        if (country !== 'TW') {
            destroyTwzipcode();
            return;
        }

        if ($('#shipping-zipcode-fields').length > 0) return;

        console.log('[YS Checkout] 初始化 twzipcode');

        var initState = $('#shipping_state').val() || '';
        var initCity = $('#shipping_city').val() || '';
        var initZipcode = $('#shipping_postcode').val() || '';

        var $cont = $('<div id="shipping-zipcode-fields"></div>').insertBefore('#shipping_address_1_field');

        function syncInputs() {
            $cont.twzipcode('get', function (county, district, zipcode) {
                $('#shipping_state').val(county);
                $('#shipping_city').val(district);
                $('#shipping_postcode').val(zipcode);
            });
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

        $('#shipping_state_field').append($cont.find('select[name="shipping_state_tw"]'));
        $('#shipping_city_field').append($cont.find('select[name="shipping_city_tw"]'));
        $('#shipping_postcode_field').append($cont.find('input[name="shipping_postcode_tw"]').addClass('input-text'));

        if (initState || initCity || initZipcode) {
            $cont.twzipcode('set', {
                county: initState,
                district: initCity,
                zipcode: initZipcode
            });
        }
    }

    function destroyTwzipcode() {
        if ($('#shipping-zipcode-fields').length === 0) return;

        console.log('[YS Checkout] 銷毀 twzipcode');

        $('#shipping-zipcode-fields').remove();
        $('.yangsheep-twzipcode-element').remove();

        $('#shipping_state_field .woocommerce-input-wrapper').show();
        $('#shipping_city_field .woocommerce-input-wrapper').show();
        $('#shipping_postcode_field .woocommerce-input-wrapper').show();
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
        if (country === 'TW' && $('#shipping-zipcode-fields').length === 0) {
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
     * 
     * @version 2.3.0
     * @since 2026-01-08
     */
    var lastShippingMethod = null;
    var shippingFieldsTimer = null;

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

        // 判斷是否為超取（PayUni、PayNow、ECPay 等）
        // ys_paynow_711, ys_paynow_family, ys_paynow_hilife 為超取
        // ys_paynow_tcat 為宅配
        var isCVS = /payuni.*(711|fami|hilife)|ecpay.*cvs|ys_paynow_(711|family|hilife)/i.test(methodId);

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

