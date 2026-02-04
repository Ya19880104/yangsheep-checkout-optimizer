/**
 * YangSheep My Account Address TWzipcode Module
 * 我的帳號地址編輯頁面的台灣縣市下拉選單
 *
 * @version 1.1.0
 * @since 2026-02-04
 */
jQuery(function($) {
    'use strict';

    console.log('[YS MyAccount Address] 初始化');

    /**
     * WooCommerce 台灣 State Code 對應表
     * WooCommerce 使用英文代碼，TWzipcode 使用中文名稱
     */
    var WC_TW_STATE_MAP = {
        'TPE': '臺北市',
        'TPH': '新北市',
        'KLU': '基隆市',
        'TYC': '桃園市',
        'HSH': '新竹市',
        'HSC': '新竹縣',
        'MAL': '苗栗縣',
        'TXG': '臺中市',
        'CWH': '彰化縣',
        'NTO': '南投縣',
        'YLH': '雲林縣',
        'CHY': '嘉義市',
        'CYI': '嘉義縣',
        'TNN': '臺南市',
        'KHH': '高雄市',
        'IUH': '屏東縣',
        'TTT': '臺東縣',
        'HWA': '花蓮縣',
        'ILN': '宜蘭縣',
        'PEH': '澎湖縣',
        'KMN': '金門縣',
        'LNN': '連江縣'
    };

    /**
     * 地址 TWzipcode 模組
     */
    var YSAddressTWzipcode = {
        config: {
            debug: true,
            initDelay: 100,
            cityLoadDelay: 150
        },

        /**
         * 日誌輸出
         */
        log: function() {
            if (this.config.debug) {
                var args = Array.prototype.slice.call(arguments);
                args.unshift('[YS MyAccount Address]');
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
         * 將 WooCommerce state code 轉換為 TWzipcode 中文名稱
         * @param {string} stateCode - WooCommerce state code 或中文名稱
         * @return {string} 中文縣市名稱
         */
        convertStateCode: function(stateCode) {
            if (!stateCode) return '';

            // 如果是 WooCommerce state code，轉換為中文
            if (WC_TW_STATE_MAP[stateCode]) {
                this.log('State code 轉換:', stateCode, '->', WC_TW_STATE_MAP[stateCode]);
                return WC_TW_STATE_MAP[stateCode];
            }

            // 處理「台」和「臺」的差異
            var normalized = stateCode.replace(/台/g, '臺');
            if (normalized !== stateCode) {
                this.log('縣市名稱正規化:', stateCode, '->', normalized);
                return normalized;
            }

            return stateCode;
        },

        /**
         * 初始化單一地址區塊的 TWzipcode
         * @param {string} type - 地址類型 (billing 或 shipping)
         */
        initAddressBlock: function(type) {
            var self = this;

            // 選擇器
            var selectors = {
                stateField: '#' + type + '_state_field',
                cityField: '#' + type + '_city_field',
                postcodeField: '#' + type + '_postcode_field',
                countryField: '#' + type + '_country',
                stateInput: '#' + type + '_state',
                cityInput: '#' + type + '_city',
                postcodeInput: '#' + type + '_postcode'
            };

            // 檢查國家是否為台灣
            var $country = $(selectors.countryField);
            if (!$country.length || $country.val() !== 'TW') {
                this.log(type + ': 非台灣，跳過 TWzipcode');
                return;
            }

            // 檢查欄位是否存在
            var $stateField = $(selectors.stateField);
            var $cityField = $(selectors.cityField);
            var $postcodeField = $(selectors.postcodeField);

            if (!$stateField.length || !$cityField.length || !$postcodeField.length) {
                this.log(type + ': 欄位不存在，跳過');
                return;
            }

            // 檢查是否已初始化
            if ($stateField.find('select[name="' + type + '_state_tw"]').length > 0) {
                this.log(type + ': 已初始化，跳過');
                return;
            }

            // 取得原始值（可能是 WooCommerce state code 或中文）
            var rawState = $(selectors.stateInput).val() || '';
            var rawCity = $(selectors.cityInput).val() || '';
            var initPostcode = $(selectors.postcodeInput).val() || '';

            // 轉換 state code 為中文名稱
            var initState = this.convertStateCode(rawState);
            var initCity = rawCity; // city 通常已經是中文

            this.log(type + ': 原始值', { state: rawState, city: rawCity, postcode: initPostcode });
            this.log(type + ': 轉換後', { state: initState, city: initCity, postcode: initPostcode });

            // 建立暫時容器
            var containerId = type + '-zipcode-temp';
            var $cont = $('<div id="' + containerId + '"></div>').appendTo('body').hide();

            // 初始化 twzipcode
            $cont.twzipcode({
                countyName: type + '_state_tw',
                districtName: type + '_city_tw',
                zipcodeName: type + '_postcode_tw',
                readonly: true,
                detect: false,
                onCountySelect: function() {
                    self.syncToHidden(type);
                },
                onDistrictSelect: function() {
                    self.syncToHidden(type);
                }
            });

            // 標記元素
            var elementClass = 'yangsheep-twzipcode-' + type;
            $cont.find('select, input').addClass(elementClass);

            // 隱藏原生欄位的輸入元素
            $stateField.find('.woocommerce-input-wrapper').hide();
            $stateField.find('select, input').not('.' + elementClass).hide();
            $cityField.find('.woocommerce-input-wrapper').hide();
            $cityField.find('input').hide();
            $postcodeField.find('.woocommerce-input-wrapper').hide();
            $postcodeField.find('input').not('.' + elementClass).hide();

            // 移動 twzipcode 元素到對應欄位
            $stateField.append($cont.find('select[name="' + type + '_state_tw"]'));
            $cityField.append($cont.find('select[name="' + type + '_city_tw"]'));
            $postcodeField.append($cont.find('input[name="' + type + '_postcode_tw"]').addClass('input-text'));

            // 移除暫時容器
            $cont.remove();

            this.log(type + ': TWzipcode 已建立');

            // 設定初始值
            setTimeout(function() {
                self.setInitialValues(type, initState, initCity, initPostcode);
            }, self.config.initDelay);
        },

        /**
         * 嘗試尋找並設定縣市/鄉鎮市區值
         * 會嘗試「台」和「臺」兩種寫法
         */
        trySetSelectValue: function($select, value) {
            if (!value || !$select.length) return false;

            // 直接嘗試
            if (this.isValidOption($select, value)) {
                $select.val(value).trigger('change');
                return true;
            }

            // 嘗試「台」<->「臺」轉換
            var altValue = value.indexOf('台') !== -1
                ? value.replace(/台/g, '臺')
                : value.replace(/臺/g, '台');

            if (this.isValidOption($select, altValue)) {
                $select.val(altValue).trigger('change');
                this.log('使用替代值:', value, '->', altValue);
                return true;
            }

            return false;
        },

        /**
         * 設定初始值
         */
        setInitialValues: function(type, initState, initCity, initPostcode) {
            var self = this;
            var $state = $('select[name="' + type + '_state_tw"]');
            var $city = $('select[name="' + type + '_city_tw"]');
            var $postcode = $('input[name="' + type + '_postcode_tw"]');

            // 1. 設定縣市
            if (initState && this.trySetSelectValue($state, initState)) {
                this.log(type + ': 縣市已設定', $state.val());
            } else {
                $state.val('').trigger('change');
                if (initState) {
                    this.log(type + ': 縣市無效，已重置', initState);
                }
                return;
            }

            // 2. 延遲設定鄉鎮市區
            setTimeout(function() {
                if (initCity && self.trySetSelectValue($city, initCity)) {
                    self.log(type + ': 鄉鎮市區已設定', $city.val());
                } else {
                    $city.val('').trigger('change');
                    if (initCity) {
                        self.log(type + ': 鄉鎮市區無效，已重置', initCity);
                    }
                }
                // 同步到隱藏欄位
                self.syncToHidden(type);
            }, self.config.cityLoadDelay);
        },

        /**
         * 同步 twzipcode 值到原始隱藏欄位
         */
        syncToHidden: function(type) {
            var state = $('select[name="' + type + '_state_tw"]').val() || '';
            var city = $('select[name="' + type + '_city_tw"]').val() || '';
            var postcode = $('input[name="' + type + '_postcode_tw"]').val() || '';

            $('#' + type + '_state').val(state);
            $('#' + type + '_city').val(city);
            $('#' + type + '_postcode').val(postcode);

            this.log(type + ': 同步到隱藏欄位', { state: state, city: city, postcode: postcode });
        },

        /**
         * 銷毀單一地址區塊的 TWzipcode
         */
        destroyAddressBlock: function(type) {
            var elementClass = 'yangsheep-twzipcode-' + type;
            var $elements = $('.' + elementClass);

            if (!$elements.length) {
                return;
            }

            this.log(type + ': 銷毀 TWzipcode');

            $elements.remove();

            // 顯示原生欄位
            $('#' + type + '_state_field .woocommerce-input-wrapper').show();
            $('#' + type + '_state_field select, #' + type + '_state_field input').show();
            $('#' + type + '_city_field .woocommerce-input-wrapper').show();
            $('#' + type + '_city_field input').show();
            $('#' + type + '_postcode_field .woocommerce-input-wrapper').show();
            $('#' + type + '_postcode_field input').show();
        },

        /**
         * 偵測當前頁面的地址類型
         */
        detectAddressType: function() {
            // 從 URL 取得地址類型
            var url = window.location.href;
            if (url.indexOf('edit-address/billing') !== -1) {
                return 'billing';
            } else if (url.indexOf('edit-address/shipping') !== -1) {
                return 'shipping';
            }
            return null;
        },

        /**
         * 綁定國家變更事件
         */
        bindCountryChange: function(type) {
            var self = this;
            var $country = $('#' + type + '_country');

            $country.on('change', function() {
                var country = $(this).val();
                self.log(type + ': 國家變更', country);

                if (country === 'TW') {
                    setTimeout(function() {
                        self.initAddressBlock(type);
                    }, 100);
                } else {
                    self.destroyAddressBlock(type);
                }
            });
        },

        /**
         * 啟動模組
         */
        start: function() {
            var self = this;

            // 檢查 twzipcode 函式庫
            if (typeof $.fn.twzipcode !== 'function') {
                this.log('twzipcode 函式庫未載入');
                return;
            }

            // 偵測地址類型
            var addressType = this.detectAddressType();

            if (addressType) {
                this.log('偵測到地址類型:', addressType);

                // 初始化
                setTimeout(function() {
                    self.initAddressBlock(addressType);
                    self.bindCountryChange(addressType);
                }, 300);
            } else {
                this.log('未偵測到地址編輯頁面');
            }

            this.log('模組啟動完成');
        }
    };

    // 啟動模組
    YSAddressTWzipcode.start();

    // 暴露到全域供除錯使用
    window.YSAddressTWzipcode = YSAddressTWzipcode;
});
