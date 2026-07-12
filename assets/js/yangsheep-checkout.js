// assets/js/yangsheep-checkout.js
// 版本: 2.7.0 - 修正建立帳號密碼欄位顯隱、國家區塊動態隱藏
// 動態控制地址欄位顯示
jQuery(function ($) {
    'use strict';

    // runtime build 探針：部署迭代間 ver 參數不變時，瀏覽器 memory cache 可能黏著舊版，
    // 驗證前先比對此值可即刻判定 runtime 實際載入的版本
    window.__ysCheckoutOptimizerBuild = '1.6.26';
    console.log('[YS Checkout] build ' + window.__ysCheckoutOptimizerBuild + ' 初始化');

    var ysCheckoutNonce = (typeof yangsheep_checkout_params !== 'undefined' && yangsheep_checkout_params.nonce)
        ? yangsheep_checkout_params.nonce
        : '';

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
    // 直接對密碼/帳號欄位 toggle .ys-show class（不依賴容器嵌套）
    function syncAccountFields() {
        var $checkbox = $('#createaccount');
        // 沒有 checkbox = 強制註冊，密碼必須顯示
        var show = !$checkbox.length || $checkbox.is(':checked');
        var $targets = $('.yangsheep-account-fields, #account_password_field, #account_username_field');
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

