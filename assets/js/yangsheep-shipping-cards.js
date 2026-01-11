/**
 * 物流選擇卡片互動腳本
 * 
 * @package YANGSHEEP_Checkout_Optimization
 * @version 1.3.1
 * @since 2026-01-07
 */

jQuery(function ($) {
    'use strict';

    /**
     * 物流卡片管理器
     */
    var YangsheepShippingCards = {

        /**
         * 初始化
         */
        init: function () {
            console.log('[YS Shipping Cards] 初始化');
            this.bindEvents();
            this.updateSelectedState();
        },

        /**
         * 綁定事件
         */
        bindEvents: function () {
            var self = this;

            // 卡片點擊事件 - 使用 label 的 for 屬性自動觸發 radio
            // 但為了確保，我們也手動處理
            $(document).on('click', '.yangsheep-shipping-card', function (e) {
                // 如果點擊的是 radio 本身，讓瀏覽器原生處理
                if ($(e.target).is('input[type="radio"]')) {
                    return;
                }
                self.handleCardClick($(this), e);
            });

            // Radio 變更事件
            $(document).on('change', '.yangsheep-shipping-cards input.shipping_method', function () {
                console.log('[YS Shipping Cards] Radio changed:', $(this).val());
                self.handleRadioChange($(this));
            });

            // 結帳頁面更新後重新設定狀態
            $(document.body).on('updated_checkout', function () {
                console.log('[YS Shipping Cards] Checkout updated, refreshing state');
                self.updateSelectedState();
            });
        },

        /**
         * 處理卡片點擊
         */
        handleCardClick: function ($card, e) {
            var $radio = $card.find('input[type="radio"]');

            console.log('[YS Shipping Cards] Card clicked, radio value:', $radio.val(), 'checked:', $radio.is(':checked'));

            // 如果已經選中，不做任何事
            if ($radio.is(':checked')) {
                return;
            }

            // 選取 radio - 使用多種方式確保選中
            $radio.prop('checked', true);
            $radio.attr('checked', 'checked');

            // 移除其他 radio 的 checked
            $card.siblings('.yangsheep-shipping-card').find('input[type="radio"]').prop('checked', false).removeAttr('checked');

            // 更新視覺狀態
            $card.addClass('selected').siblings('.yangsheep-shipping-card').removeClass('selected');

            // 觸發原生 change 事件
            $radio[0].dispatchEvent(new Event('change', { bubbles: true }));

            console.log('[YS Shipping Cards] After click, radio checked:', $radio.is(':checked'));
        },

        /**
         * 處理 Radio 變更
         */
        handleRadioChange: function ($radio) {
            var self = this;
            var $container = $radio.closest('.yangsheep-shipping-options');

            // 更新選中狀態視覺
            $container.find('.yangsheep-shipping-card').removeClass('selected');
            $radio.closest('.yangsheep-shipping-card').addClass('selected');

            // 觸發 WooCommerce 結帳更新
            clearTimeout(self.updateTimer);
            self.updateTimer = setTimeout(function () {
                console.log('[YS Shipping Cards] Triggering update_checkout');
                $(document.body).trigger('update_checkout');
            }, 100);
        },

        /**
         * 更新選中狀態視覺
         */
        updateSelectedState: function () {
            $('.yangsheep-shipping-options').each(function () {
                var $options = $(this);
                var $selected = $options.find('input[type="radio"]:checked');

                console.log('[YS Shipping Cards] updateSelectedState, found checked:', $selected.length);

                // 移除所有選中狀態
                $options.find('.yangsheep-shipping-card').removeClass('selected');

                // 設定選中狀態
                if ($selected.length) {
                    $selected.closest('.yangsheep-shipping-card').addClass('selected');
                } else {
                    // 如果沒有選中項目，預設選第一個
                    var $first = $options.find('input[type="radio"]').first();
                    if ($first.length) {
                        $first.prop('checked', true);
                        $first.closest('.yangsheep-shipping-card').addClass('selected');
                        // 觸發 change 讓 WooCommerce 知道
                        $first.trigger('change');
                    }
                }
            });
        },

        /**
         * 更新計時器（用於 debounce）
         */
        updateTimer: null
    };

    // 初始化
    YangsheepShippingCards.init();
});
