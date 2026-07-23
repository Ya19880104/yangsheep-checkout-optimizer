<?php
/**
 * 第三方物流外掛相容性處理類別
 *
 * 支援外掛：
 * - 好用版 RY Tools for WooCommerce (綠界 ECPay CVS 超取)
 * - 好用版 PayNow Shipping (PayNow 超取)
 *
 * 功能說明：
 * 1. 綠界 CVS 欄位（CVSStoreName, CVSAddress, CVSTelephone）
 *    - 僅當選擇綠界超商物流時顯示
 *    - 欄位設為 2 欄排版（桌機與手機）
 *
 * 2. PayNow 超取欄位（paynow_storename, paynow_storeid, paynow_storeaddress）
 *    - 保留原生欄位作為提交資料源
 *    - 增強成功後以不可編輯的門市摘要取代 input 外觀
 *
 * 3. 樣式調整
 *    - PayNow 選店列：標題、狀態、白底虛線面板與全寬按鈕
 *    - 「超商門市」標題（綠界）：margin-top 20px、置中、粗體
 *    - 修正電話欄位位置被第三方外掛影響的問題
 *
 * @package YANGSHEEP_Checkout_Optimization
 * @version 1.7.2
 * @since 2026-01-12
 *
 * 實作說明：
 * - 使用 JavaScript 監聽物流選擇變更事件
 * - 根據選擇的物流方式動態顯示/隱藏對應的 CVS 欄位
 * - 透過 CSS 控制欄位排版與樣式
 * - 使用高優先級確保樣式覆蓋第三方外掛
 */

namespace YangSheep\CheckoutOptimizer\Compat;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use YangSheep\CheckoutOptimizer\Settings\YSSettingsManager;

class YSThirdPartyShippingCompat {

    /**
     * 單例實例
     * @var YSThirdPartyShippingCompat|null
     */
    private static $instance = null;

    /**
     * 綠界 CVS 物流方法前綴
     * @var array
     */
    private $ecpay_cvs_methods = array(
        'ry_ecpay_shipping_cvs_711',      // 7-11
        'ry_ecpay_shipping_cvs_hilife',   // 萊爾富
        'ry_ecpay_shipping_cvs_family',   // 全家
        'ry_ecpay_shipping_cvs_okmart',   // OK超商
    );

    /**
     * PayNow CVS 物流方法前綴
     * @var array
     */
    private $paynow_cvs_methods = array(
        'paynow_shipping_c2c_711',        // 7-11 交貨便
        'paynow_shipping_c2c_family',     // 全家 店到店
        'paynow_shipping_b2c_711',        // 7-11 大智通
        'paynow_shipping_b2c_family',     // 全家 B2C
        'paynow_shipping_b2c_hilife',     // 萊爾富 B2C
        'paynow_shipping_b2c_okmart',     // OK B2C
    );

    /**
     * 取得單例實例
     *
     * @return YSThirdPartyShippingCompat
     */
    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * 建構函式
     * 註冊所有必要的 hooks
     * 只有在啟用對應的第三方物流外掛時才載入
     */
    private function __construct() {
        // woomp 3.5.14 enqueues WooCommerce cart.js on checkout. Both cart.js
        // and checkout.js bind coupon removal, causing duplicate requests.
        add_action( 'wp_enqueue_scripts', array( $this, 'dequeue_cart_script_on_checkout' ), 100 );
        // 檢查是否有任一支援的物流外掛啟用
        if ( ! self::is_ecpay_shipping_active() && ! self::is_paynow_shipping_active() ) {
            return; // 沒有啟用任何支援的物流外掛，不載入
        }

        // 載入前端腳本與樣式
        add_action( 'wp_footer', array( $this, 'add_cvs_toggle_script' ) );
        add_action( 'wp_head', array( $this, 'add_cvs_styles' ) );

        // 修正電話欄位位置（最高優先級，在第三方外掛之後執行）
        add_filter( 'woocommerce_checkout_fields', array( $this, 'fix_shipping_phone_priority' ), 99999 );

        // woomp PayNow and WPBR PAYUNi both register PNCVS with incompatible
        // placeholders. Use a provider-specific key after both plugins run.
        add_filter( 'woocommerce_order_formatted_shipping_address', array( $this, 'reconcile_paynow_order_address' ), 999, 2 );
        add_filter( 'woocommerce_localisation_address_formats', array( $this, 'add_paynow_address_format' ), 999 );
        add_filter( 'woocommerce_formatted_address_replacements', array( $this, 'add_paynow_address_replacements' ), 999, 2 );
    }

    /**
     * Keep woomp PayNow order addresses independent from PAYUNi's PNCVS key.
     *
     * @param array    $raw_address Raw shipping address.
     * @param WC_Order $order       Order instance.
     * @return array
     */
    public function reconcile_paynow_order_address( $raw_address, $order ) {
        if (
            ! is_object( $order )
            || ! method_exists( $order, 'get_shipping_methods' )
            || ! method_exists( $order, 'get_meta' )
        ) {
            return $raw_address;
        }

        $store_id = (string) $order->get_meta( '_shipping_paynow_storeid' );
        if ( '' === $store_id || ! $this->order_uses_woomp_paynow_cvs( $order ) ) {
            return $raw_address;
        }

        $raw_address['paynow_storeid']      = $store_id;
        $raw_address['paynow_storename']    = (string) $order->get_meta( '_shipping_paynow_storename' );
        $raw_address['paynow_storeaddress'] = (string) $order->get_meta( '_shipping_paynow_storeaddress' );
        $shipping_phone = method_exists( $order, 'get_shipping_phone' )
            ? (string) $order->get_shipping_phone()
            : '';
        if ( '' !== $shipping_phone || ! isset( $raw_address['phone'] ) ) {
            $raw_address['phone'] = $shipping_phone;
        }
        $raw_address['country']             = 'YS_PAYNOW_CVS';

        return $raw_address;
    }

    /**
     * Register the provider-unique formatted address contract.
     *
     * @param array $formats WooCommerce address formats.
     * @return array
     */
    public function add_paynow_address_format( $formats ) {
        $format = "{paynow_storename} ({paynow_storeid})\n{paynow_storeaddress}\n{last_name} {first_name}";
        if ( ! is_admin() ) {
            $format .= "\n" . '<p class="woocommerce-customer-details--phone">{phone}</p>';
        }

        $formats['YS_PAYNOW_CVS'] = $format;
        return $formats;
    }

    /**
     * Fill placeholders for the provider-unique PayNow address format.
     *
     * @param array $replacements Existing replacements.
     * @param array $args         Raw address arguments.
     * @return array
     */
    public function add_paynow_address_replacements( $replacements, $args ) {
        foreach ( array( 'paynow_storeid', 'paynow_storename', 'paynow_storeaddress', 'phone' ) as $key ) {
            if ( isset( $args[ $key ] ) ) {
                $replacements[ '{' . $key . '}' ] = $args[ $key ];
            }
        }

        return $replacements;
    }

    /**
     * Check whether an order uses the woomp PayNow CVS family.
     *
     * @param WC_Order $order Order instance.
     * @return bool
     */
    private function order_uses_woomp_paynow_cvs( $order ) {
        foreach ( $order->get_shipping_methods() as $shipping_item ) {
            $method_id = strtolower( (string) $shipping_item->get_method_id() );
            if (
                0 === strpos( $method_id, 'paynow_shipping_c2c_' )
                || 0 === strpos( $method_id, 'paynow_shipping_b2c_' )
                || 0 === strpos( $method_id, 'woomp_paynow_shipping_c2c_' )
            ) {
                return true;
            }
        }

        return false;
    }

    /**
     * Keep cart.js off classic checkout; checkout.js owns coupon actions.
     */
    public function dequeue_cart_script_on_checkout() {
        if (
            function_exists( 'is_checkout' )
            && is_checkout()
            && ! is_wc_endpoint_url()
            && ( ! function_exists( 'is_cart' ) || ! is_cart() )
        ) {
            wp_dequeue_script( 'wc-cart' );
        }
    }

    /**
     * 修正 shipping_phone 欄位的 priority
     *
     * 第三方外掛（如綠界）可能會修改電話欄位的 priority，
     * 導致欄位跑到錯誤位置。這裡強制設定正確的 priority。
     *
     * @param array $fields 結帳欄位
     * @return array
     */
    public function fix_shipping_phone_priority( $fields ) {
        if ( isset( $fields['shipping']['shipping_phone'] ) ) {
            // 設定 priority 為 15，確保在 first_name(5)、last_name(10) 之後
            // 但在 postcode(40) 之前
            $fields['shipping']['shipping_phone']['priority'] = 15;

            // 移除可能被第三方外掛加入的 cvs-info class
            if ( isset( $fields['shipping']['shipping_phone']['class'] ) ) {
                $classes = $fields['shipping']['shipping_phone']['class'];
                if ( is_array( $classes ) ) {
                    $classes = array_filter( $classes, function( $class ) {
                        return strpos( $class, 'cvs' ) === false;
                    });
                    // 確保有 form-row 和正確的寬度 class
                    if ( ! in_array( 'form-row', $classes ) ) {
                        $classes[] = 'form-row';
                    }
                    $fields['shipping']['shipping_phone']['class'] = array_values( $classes );
                }
            }
        }

        return $fields;
    }

    /**
     * 添加 CVS 欄位顯示/隱藏的 JavaScript
     *
     * 功能：
     * - 監聽物流選擇變更
     * - 根據選擇的物流動態顯示/隱藏 CVS 欄位
     * - 初始載入時也執行一次判斷
     */
    public function add_cvs_toggle_script() {
        // 僅在結帳頁面載入
        if ( ! is_checkout() || is_wc_endpoint_url() ) {
            return;
        }

        // 將 PHP 陣列轉為 JSON 供 JavaScript 使用
        $ecpay_methods_json = wp_json_encode( $this->ecpay_cvs_methods );
        $paynow_methods_json = wp_json_encode( $this->paynow_cvs_methods );
        $validate_shipping_phone_json = wp_json_encode(
            YSSettingsManager::get( 'yangsheep_validate_phone_shipping', 'yes' ) === 'yes'
        );
        ?>
        <script>
        /**
         * YANGSHEEP 第三方物流相容性腳本
         *
         * 處理綠界與 PayNow CVS 欄位的顯示/隱藏邏輯
         */
        jQuery(function($) {
            'use strict';

            // 綠界 CVS 物流方法列表
            var ecpayCvsMethods = <?php echo $ecpay_methods_json; ?>;

            // PayNow CVS 物流方法列表
            var paynowCvsMethods = <?php echo $paynow_methods_json; ?>;
            var validateShippingPhone = <?php echo $validate_shipping_phone_json; ?>;

            function isEnhancedCheckout() {
                return $('form.checkout').hasClass('ys-checkout-enhanced');
            }

            /**
             * 檢查當前選擇的物流是否為指定類型
             *
             * @param {Array} methodList - 物流方法列表
             * @return {boolean}
             */
            function isMethodSelected(methodList) {
                var selectedMethods = $('input.shipping_method')
                    .filter(function() {
                        return this.type === 'hidden' || this.checked;
                    })
                    .map(function() {
                        return $.trim(String(this.value || '')).toLowerCase();
                    })
                    .get()
                    .filter(function(selectedValue) {
                        return selectedValue !== '';
                    });

                return selectedMethods.some(function(selectedValue) {
                    for (var i = 0; i < methodList.length; i++) {
                        if (selectedValue.split(':')[0] === methodList[i]) {
                            return true;
                        }
                    }
                    return false;
                });
            }

            /**
             * 動態偵測並樣式化「選擇超商」「超商門市」等文字
             * 這些文字元素沒有 class/id，需要透過文字內容偵測
             */
            function styleCvsLabels() {
                // 偵測包含「選擇超商」「超商門市」「選擇門市」等文字的元素
                var cvsKeywords = ['選擇超商', '超商門市', '選擇門市', '選擇取貨門市'];

                // 搜尋 shipping fields 區域內的文字節點
                $('.woocommerce-shipping-fields, .woocommerce-shipping-fields__field-wrapper').find('*').each(function() {
                    var $el = $(this);
                    var text = $el.text().trim();

                    // 跳過已處理的元素
                    if ($el.hasClass('ys-cvs-label-styled')) {
                        return;
                    }

                    // 檢查是否包含關鍵字
                    for (var i = 0; i < cvsKeywords.length; i++) {
                        if (text === cvsKeywords[i] || text.indexOf(cvsKeywords[i]) === 0) {
                            // 標記已處理並加入樣式 class
                            $el.addClass('ys-cvs-label-styled ys-cvs-choose-label');
                            console.log('[YS Compat] Styled CVS label:', text);
                            break;
                        }
                    }
                });

                // 標記物流外掛的通用 tr.choose_cvs 表格行；
                // PayNow 完成 DOM 契約後會再加專屬 class 並排除舊樣式。
                $('tr.choose_cvs').addClass('ys-cvs-label-styled ys-cvs-choose-row');

                // 特別處理 PayNow 選擇超商按鈕
                $('#choose-cvs-btn').addClass('ys-cvs-label-styled ys-cvs-choose-btn');
            }

            /**
             * 切換綠界 CVS 欄位顯示狀態
             *
             * 欄位：CVSStoreName, CVSAddress, CVSTelephone
             */
            function toggleEcpayCvsFields() {
                var show = isMethodSelected(ecpayCvsMethods);

                // 綠界 CVS 欄位容器
                var $ecpayFields = $('#CVSStoreName_field, #CVSAddress_field, #CVSTelephone_field, #CVSStoreID_field');

                // 綠界選擇超商按鈕/連結（各種可能的選擇器）
                var $ecpayChooseCvs = $('.choose_cvs, a.choose_cvs, button.choose_cvs, tr.choose_cvs');

                // 綠界「超商門市」標籤文字
                var $ecpayLabels = $('.ys-cvs-choose-label');

                if (show) {
                    // v1.6.29 修：只 show 內部真的有 input/textarea/select 的 field
                    // woomp / RY Tools 新版可能只註冊 <p> label wrapper（沒 input），
                    // 這種空 wrapper 硬 show 會顯示 3 個永遠空的「門市名稱/地址/電話」label
                    // 造成使用者誤以為超取沒回傳（實際門市顯示在 .show_choose_cvs_name span）
                    $ecpayFields.each(function() {
                        var $p = $(this);
                        if ($p.find('input, textarea, select').length > 0) {
                            $p.addClass('ys-cvs-shown').show();
                        } else {
                            $p.removeClass('ys-cvs-shown').hide();
                        }
                    });
                    $ecpayChooseCvs.addClass('ys-cvs-shown').show();
                    $ecpayLabels.filter(function() {
                        // 只顯示在 ECPay 區域內的標籤
                        return $(this).closest('#CVSStoreName_field, #CVSAddress_field, .woocommerce-shipping-fields').length > 0;
                    }).addClass('ys-cvs-shown').show();
                    console.log('[YS Compat] ECPay CVS fields shown (only fields with real input)');
                } else {
                    $ecpayFields.removeClass('ys-cvs-shown').hide();
                    $ecpayChooseCvs.removeClass('ys-cvs-shown').hide();
                    console.log('[YS Compat] ECPay CVS fields hidden');
                }
            }

            /**
             * Replace PayNow's readonly text boxes with a non-interactive store summary.
             * Native inputs remain enabled and in the form as the provider's submit source.
             *
             * @return {boolean} Whether the enhanced selector mounted successfully.
             */
            function enhancePaynowStoreSelector() {
                var $button = $('#choose-cvs-btn');
                var $row = $button.closest('tr.choose_cvs');

                if (!$button.length || !$row.length) {
                    return false;
                }

                var $title = $row.children('th');
                var $panel = $row.children('td');
                if (!$title.length || !$panel.length) {
                    return false;
                }

                $row.addClass('ys-paynow-store-selector');
                $title.addClass('ys-cvs-store-title').text('\u8d85\u5546\u9580\u5e02');
                $panel.addClass('ys-cvs-store-panel');

                var $status = $panel.children('.ys-cvs-store-status');
                if (!$status.length) {
                    $status = $('<div class="ys-cvs-store-status" role="status" aria-live="polite"></div>');
                    $button.before($status);
                }

                var storeName = $.trim($('#paynow_storename').val() || '');
                var storeId = $.trim($('#paynow_storeid').val() || '');
                var storeAddress = $.trim($('#paynow_storeaddress').val() || '');
                var hasStore = !!(storeName || storeId || storeAddress);

                $status.empty().toggleClass('has-store', hasStore);
                if (hasStore) {
                    var $primary = $('<span class="ys-cvs-store-primary"></span>');
                    $primary.text(storeName || '\u5df2\u9078\u64c7\u53d6\u8ca8\u9580\u5e02');
                    $status.append($primary);

                    if (storeId) {
                        $('<span class="ys-cvs-store-meta"></span>')
                            .text('\u9580\u5e02\u7de8\u865f ' + storeId)
                            .appendTo($status);
                    }
                    if (storeAddress) {
                        $('<span class="ys-cvs-store-address"></span>')
                            .text(storeAddress)
                            .appendTo($status);
                    }
                    $button.text('\u91cd\u65b0\u9078\u64c7\u9580\u5e02');
                } else {
                    $('<span class="ys-cvs-store-empty"></span>')
                        .text('\u5c1a\u672a\u9078\u64c7\u53d6\u8ca8\u9580\u5e02')
                        .appendTo($status);
                    $button.text('\u9078\u64c7\u9580\u5e02');
                }

                return $row.is(':visible') && $panel.is(':visible') && $status.is(':visible');
            }

            /**
             * 切換 PayNow CVS 欄位顯示狀態
             *
             * 欄位：paynow_storename, paynow_storeid, paynow_storeaddress
             */
            function togglePaynowCvsFields() {
                var show = isMethodSelected(paynowCvsMethods);

                // PayNow CVS 欄位容器
                var $paynowFields = $('#paynow_storename_field, #paynow_storeid_field, #paynow_storeaddress_field');

                // PayNow 選擇超商按鈕
                var $paynowChooseCvs = $('#choose-cvs-btn, #choose-cvs-btn-field, .paynow-choose-cvs, .paynow-choose-cvs-wrapper');
                $paynowChooseCvs = $paynowChooseCvs.add($('#choose-cvs-btn').closest('tr.choose_cvs'));

                // PayNow 服務類型欄位
                var $paynowService = $('#paynow_service_field');

                if (show) {
                    $paynowChooseCvs.addClass('ys-cvs-shown').show();
                    $paynowService.addClass('ys-cvs-shown').show();
                    if (enhancePaynowStoreSelector()) {
                        $paynowFields.removeClass('ys-cvs-shown').addClass('ys-cvs-source-field').hide();
                        console.log('[YS Compat] PayNow store selector enhanced; native value fields preserved');
                    } else {
                        // Fail open when the provider changes its chooser markup.
                        $paynowFields.removeClass('ys-cvs-source-field').addClass('ys-cvs-shown').show();
                        console.warn('[YS Compat] PayNow selector contract missing; native fields remain visible');
                    }
                } else {
                    $paynowFields.removeClass('ys-cvs-shown ys-cvs-source-field').hide();
                    $paynowChooseCvs.removeClass('ys-cvs-shown').hide();
                    $paynowService.removeClass('ys-cvs-shown').hide();
                    console.log('[YS Compat] PayNow CVS fields hidden');
                }
            }

            /**
             * 主要更新函式
             * 同時處理綠界與 PayNow
             */
            function updateCvsFieldsVisibility() {
                if (!isEnhancedCheckout()) {
                    return;
                }

                // 先樣式化 CVS 標籤
                styleCvsLabels();
                // 再切換顯示狀態
                toggleEcpayCvsFields();
                togglePaynowCvsFields();
            }

            // ===== 事件綁定 =====

            // 初始載入時執行
            $(document).ready(function() {
                // 延遲執行確保 DOM 已完整載入
                setTimeout(updateCvsFieldsVisibility, 300);
            });

            // 原生物流選擇變更時執行；YS 卡片只代理這個 change。
            $(document.body).on('change', 'input.shipping_method', function() {
                updateCvsFieldsVisibility();
            });

            // WooCommerce AJAX 更新後執行
            $(document.body).on('updated_checkout', function() {
                setTimeout(updateCvsFieldsVisibility, 300);
            });

            // ===== 收件人電話驗證（台灣手機：09 開頭，10 位數字） =====

            /**
             * 驗證手機號碼格式
             *
             * @param {jQuery} $input - 輸入欄位
             * @param {boolean} showError - 是否顯示錯誤訊息
             * @return {boolean} - 是否驗證通過
             */
            function validateMobilePhone($input, showError) {
                if (!validateShippingPhone) return true;
                if (!$input || !$input.length) return true;

                showError = showError || false;
                var value = $input.val() ? $input.val().trim() : '';
                var $field = $input.closest('.form-row');
                var fieldId = $input.attr('id') || 'shipping_phone';

                // 移除舊的錯誤訊息
                $field.find('.ys-phone-error').remove();
                $field.removeClass('woocommerce-invalid woocommerce-invalid-phone ys-invalid-phone');
                $input.removeClass('ys-input-error');

                // 如果欄位為空且不是必填，跳過驗證
                if (!value) {
                    if ($input.prop('required') || $field.hasClass('validate-required')) {
                        if (showError) {
                            showPhoneError($input, $field, '請輸入手機號碼');
                        }
                        return false;
                    }
                    return true;
                }

                // 只允許數字
                var numericValue = value.replace(/\D/g, '');
                if (numericValue !== value) {
                    // 自動移除非數字字元
                    $input.val(numericValue);
                }

                // 驗證格式：必須是 09 開頭的 10 位數字
                var isValid = /^09\d{8}$/.test(numericValue);

                if (!isValid && showError) {
                    var errorMsg = '請輸入有效的手機號碼';
                    if (numericValue.length > 0 && numericValue.substring(0, 2) !== '09') {
                        errorMsg = '手機號碼必須為 09 開頭';
                    } else if (numericValue.length !== 10) {
                        errorMsg = '手機號碼必須為 10 位數字';
                    }
                    showPhoneError($input, $field, errorMsg);
                }

                return isValid;
            }

            /**
             * 顯示電話驗證錯誤訊息
             */
            function showPhoneError($input, $field, message) {
                $field.addClass('woocommerce-invalid woocommerce-invalid-phone ys-invalid-phone');
                $input.addClass('ys-input-error');

                var $error = $('<span class="ys-phone-error" role="alert" style="color:#e2401c;font-size:12px;display:block;margin-top:5px;">' + message + '</span>');
                var $wrapper = $input.closest('.woocommerce-input-wrapper');
                if ($wrapper.length) {
                    $wrapper.append($error);
                } else {
                    $input.after($error);
                }
            }

            // 綁定 input 事件（即時驗證）
            $(document).on('input', '#shipping_phone', function() {
                validateMobilePhone($(this), false);
            });

            // 綁定 blur 事件（失焦時驗證）
            $(document).on('blur', '#shipping_phone', function() {
                validateMobilePhone($(this), true);
            });

            // 綁定表單提交事件
            $(document).on('submit', 'form.checkout, form#order_review', function(e) {
                var $phoneField = $('#shipping_phone');
                // 只有在「運送到不同地址」被勾選時才驗證
                var $shipToDifferent = $('#ship-to-different-address-checkbox');
                if ($phoneField.length && $phoneField.is(':visible') && $shipToDifferent.is(':checked')) {
                    if (!validateMobilePhone($phoneField, true)) {
                        e.preventDefault();
                        $phoneField.focus();
                        return false;
                    }
                }
            });

            // 綁定 WooCommerce checkout 驗證事件
            $(document).on('checkout_error', function() {
                var $phoneField = $('#shipping_phone');
                if ($phoneField.length) {
                    validateMobilePhone($phoneField, true);
                }
            });

            console.log('[YS Compat] Mobile phone validation initialized');
        });
        </script>
        <?php
    }

    /**
     * 添加 CVS 欄位相關樣式
     *
     * 樣式包含：
     * 1. 綠界欄位 Grid 排版與選店標題
     * 2. 電話欄位位置修正
     * 3. 物流內部欄位隱藏
     *
     * PayNow 摘要面板與按鈕寬度由 yangsheep-compatibility.css
     * 的 provider-scoped 規則單一負責，這裡不再修改其原生資料欄位寬度。
     */
    public function add_cvs_styles() {
        // 僅在結帳頁面載入
        if ( ! is_checkout() || is_wc_endpoint_url() ) {
            return;
        }
        ?>
        <style>
        /**
         * YANGSHEEP 第三方物流相容性樣式
         *
         * @version 1.7.2
         * @since 2026-01-12
         *
         * 重要：shipping 欄位使用 Grid 排版，必須用 grid-column 控制寬度
         */

        /* ===== 1. 綠界 CVS 欄位 - Grid 2 欄排版 ===== */

        /* 綠界 CVS 欄位 - 使用 grid-column: span 1 讓每個欄位佔一格 */
        body.ys-checkout-enhanced #CVSStoreName_field,
        body.ys-checkout-enhanced #CVSAddress_field,
        body.ys-checkout-enhanced #CVSTelephone_field {
            grid-column: span 1 !important;
        }

        /* ===== 2. 「選擇超商」「超商門市」標籤樣式 ===== */

        /* 綠界選擇超商按鈕/連結 - 全寬置中 */
        body.ys-checkout-enhanced .choose_cvs,
        body.ys-checkout-enhanced a.choose_cvs,
        body.ys-checkout-enhanced button.choose_cvs {
            display: block !important;
            text-align: center !important;
            font-weight: bold !important;
            margin-top: 20px !important;
        }

        /* 舊版綠界/其他通用選店列；PayNow 由專屬面板樣式負責 */
        body.ys-checkout-enhanced tr.choose_cvs:not(.ys-paynow-store-selector),
        body.ys-checkout-enhanced tr.ys-cvs-choose-row:not(.ys-paynow-store-selector) {
            margin-top: 20px !important;
        }

        body.ys-checkout-enhanced tr.choose_cvs:not(.ys-paynow-store-selector) th,
        body.ys-checkout-enhanced tr.choose_cvs:not(.ys-paynow-store-selector) td,
        body.ys-checkout-enhanced tr.ys-cvs-choose-row:not(.ys-paynow-store-selector) th,
        body.ys-checkout-enhanced tr.ys-cvs-choose-row:not(.ys-paynow-store-selector) td {
            text-align: center !important;
            font-weight: bold !important;
            padding-top: 20px !important;
        }

        /* 動態偵測到的「選擇超商」「超商門市」標籤 */
        body.ys-checkout-enhanced .ys-cvs-choose-label {
            display: block !important;
            text-align: center !important;
            font-weight: bold !important;
            margin-top: 20px !important;
            grid-column: 1 / -1 !important;
        }

        /* 綠界 CVS 區塊標題 */
        body.ys-checkout-enhanced .cvs-info-title,
        body.ys-checkout-enhanced .ecpay-cvs-title,
        body.ys-checkout-enhanced .woocommerce-shipping-fields .cvs-info h3,
        body.ys-checkout-enhanced .woocommerce-shipping-fields .cvs-info h4 {
            margin-top: 20px !important;
            text-align: center !important;
            font-weight: bold !important;
        }

        /* ===== 3. 電話欄位位置修正 ===== */

        /*
         * 綠界外掛會在電話欄位加上 cvs-info class，
         * 這裡移除可能干擾 Grid 排版的設定
         * 實際的 span 值由主 CSS 控制（2欄Grid用span 1，6欄Grid用span 3）
         */
        body.ys-checkout-enhanced #shipping_phone_field.cvs-info {
            /* 移除綠界可能加上的樣式 */
            width: auto !important;
            float: none !important;
            display: block !important;
        }

        /* ===== 4. 內部欄位強制隱藏（Reserved NO, Ship Date, LogisticsSubType 等）===== */

        /*
         * 這些欄位是物流外掛內部使用（冷藏配送等），用戶不需要看到
         * 使用 CSS 強制隱藏，不依賴 JS
         *
         * PayNow 欄位 ID（無底線）：
         * - paynow_reservedno_field
         * - paynow_shipdate_field
         *
         * 綠界欄位 ID：
         * - LogisticsSubType_field
         */

        /* PayNow 內部欄位 - 強制隱藏 */
        body.ys-checkout-enhanced #paynow_reservedno_field,
        body.ys-checkout-enhanced #paynow_shipdate_field,
        body.ys-checkout-enhanced p#paynow_reservedno_field,
        body.ys-checkout-enhanced p#paynow_shipdate_field,
        body.ys-checkout-enhanced [id="paynow_reservedno_field"],
        body.ys-checkout-enhanced [id="paynow_shipdate_field"] {
            display: none !important;
            position: absolute !important;
            visibility: hidden !important;
            width: 0 !important;
            height: 0 !important;
            overflow: hidden !important;
            padding: 0 !important;
            margin: 0 !important;
            border: 0 !important;
            pointer-events: none !important;
        }

        /* 綠界內部欄位 - 強制隱藏 */
        body.ys-checkout-enhanced #LogisticsSubType_field,
        body.ys-checkout-enhanced p#LogisticsSubType_field,
        body.ys-checkout-enhanced [id="LogisticsSubType_field"],
        body.ys-checkout-enhanced #CVSStoreID_field,
        body.ys-checkout-enhanced p#CVSStoreID_field,
        body.ys-checkout-enhanced [id="CVSStoreID_field"] {
            display: none !important;
            position: absolute !important;
            visibility: hidden !important;
            width: 0 !important;
            height: 0 !important;
            overflow: hidden !important;
            padding: 0 !important;
            margin: 0 !important;
            border: 0 !important;
            pointer-events: none !important;
        }

        /* ===== 5. 隱藏綠界 CVS 表格中的特定欄位（Reserved NO, Ship Date）===== */

        /*
         * 綠界動態載入的表格使用 class="cvs-info"
         * 表格內有：門市名稱、門市地址、門市電話、Reserved NO、Ship Date
         * 需要隱藏 Reserved NO 和 Ship Date 這兩欄（第4、5欄）
         */
        body.ys-checkout-enhanced .cvs-info th:nth-child(n+4),
        body.ys-checkout-enhanced .cvs-info td:nth-child(n+4),
        body.ys-checkout-enhanced table.cvs-info th:nth-child(n+4),
        body.ys-checkout-enhanced table.cvs-info td:nth-child(n+4),
        body.ys-checkout-enhanced .woocommerce-shipping-fields .cvs-info th:nth-child(n+4),
        body.ys-checkout-enhanced .woocommerce-shipping-fields .cvs-info td:nth-child(n+4) {
            display: none !important;
        }
        </style>
        <?php
    }

    /**
     * 檢查綠界物流外掛是否啟用
     *
     * @return bool
     */
    public static function is_ecpay_shipping_active() {
        // 檢查 RY Tools for WooCommerce 外掛
        return class_exists( 'RY_WT' ) || class_exists( 'RY_ECPay_Shipping' );
    }

    /**
     * 檢查 PayNow 物流外掛是否啟用
     *
     * @return bool
     */
    public static function is_paynow_shipping_active() {
        // 檢查 PayNow Shipping 外掛
        return class_exists( 'PayNow_Shipping' ) || function_exists( 'paynow_shipping_get_cvs_code' );
    }
}
