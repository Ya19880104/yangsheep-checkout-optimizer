/**
 * YANGSHEEP WPLoyalty Integration
 *
 * 整合 WPLoyalty (WooCommerce Loyalty Rewards) 外掛
 * 偵測並美化購物金兌換訊息
 *
 * @version 1.0.0
 * @since 2026-01-12
 */
jQuery(function($) {
    'use strict';

    console.log('[YS WPLoyalty] Initializing...');

    var WPLoyaltyIntegration = {
        // 設定
        settings: typeof yangsheep_wployalty !== 'undefined' ? yangsheep_wployalty : {},

        // 選擇器
        selectors: {
            wlrMessage: '.wlr_point_redeem_message',
            couponPoint: '.yangsheep-coupon-point',
            couponBlock: '.yangsheep-coupon-block'
        },

        // 狀態
        state: {
            initialized: false,
            pointsData: null
        },

        /**
         * 初始化
         */
        init: function() {
            if (!this.settings.enabled) {
                console.log('[YS WPLoyalty] Integration disabled');
                return;
            }

            this.bindEvents();
            this.processWLRMessage();
            this.state.initialized = true;

            console.log('[YS WPLoyalty] Initialized');
        },

        /**
         * 綁定事件
         */
        bindEvents: function() {
            var self = this;

            // WooCommerce 結帳更新後
            $(document.body).on('updated_checkout', function() {
                setTimeout(function() {
                    self.processWLRMessage();
                }, 300);
            });

            // 監聽 DOM 變化（WLR 可能動態插入訊息）
            this.observeDOM();
        },

        /**
         * 監聽 DOM 變化
         */
        observeDOM: function() {
            var self = this;

            if (typeof MutationObserver === 'undefined') {
                // Fallback: 定期檢查
                setInterval(function() {
                    self.processWLRMessage();
                }, 2000);
                return;
            }

            var observer = new MutationObserver(function(mutations) {
                var shouldProcess = false;

                mutations.forEach(function(mutation) {
                    // 檢查是否有新增 WLR 訊息
                    if (mutation.addedNodes.length) {
                        mutation.addedNodes.forEach(function(node) {
                            if (node.nodeType === 1) {
                                if ($(node).hasClass('wlr_point_redeem_message') ||
                                    $(node).find('.wlr_point_redeem_message').length) {
                                    shouldProcess = true;
                                }
                            }
                        });
                    }
                });

                if (shouldProcess) {
                    setTimeout(function() {
                        self.processWLRMessage();
                    }, 100);
                }
            });

            observer.observe(document.body, {
                childList: true,
                subtree: true
            });
        },

        /**
         * 處理 WLR 訊息
         */
        processWLRMessage: function() {
            var self = this;
            var $wlrMessage = $(this.selectors.wlrMessage);
            var $couponPoint = $(this.selectors.couponPoint);
            var $couponBlock = $(this.selectors.couponBlock);

            console.log('[YS WPLoyalty] Processing, found WLR messages:', $wlrMessage.length);

            // 如果沒有目標容器，直接返回
            if (!$couponPoint.length) {
                return;
            }

            // 如果沒有 WLR 訊息
            if (!$wlrMessage.length) {
                // 檢查是否已經有我們建立的自訂區塊（避免重複隱藏）
                if ($couponPoint.find('.ys-wployalty-block').length) {
                    // 已有自訂區塊，保持顯示
                    return;
                }
                // 沒有 WLR 訊息也沒有自訂區塊，隱藏購物金區塊
                $couponPoint.removeClass('has-content').hide();
                $couponBlock.removeClass('has-point');
                return;
            }

            // 解析 WLR 訊息內容
            var pointsData = this.parseWLRMessage($wlrMessage);

            if (!pointsData) {
                console.log('[YS WPLoyalty] Could not parse WLR message');
                return;
            }

            this.state.pointsData = pointsData;

            // 隱藏原始 WLR 訊息
            $wlrMessage.hide();

            // 建立美化的購物金區塊
            var $customBlock = this.createCustomPointsBlock(pointsData);

            // 清空並填入自訂區塊
            $couponPoint.empty().append($customBlock);

            // 顯示購物金區塊
            $couponPoint.addClass('has-content').show();
            $couponBlock.addClass('has-point');

            console.log('[YS WPLoyalty] Custom points block created');
        },

        /**
         * 解析 WLR 訊息
         *
         * 原始格式: "You have 500 points earned choose your rewards Click Here"
         * 或其他用戶自訂格式
         */
        parseWLRMessage: function($element) {
            var text = $element.text().trim();
            var $link = $element.find('a#wlr-reward-link, a[href*="void"]');

            console.log('[YS WPLoyalty] Parsing message:', text);

            // 嘗試提取數字（購物金點數）
            var pointsMatch = text.match(/(\d+[\d,]*)\s*(points?|點|購物金)?/i);
            var points = pointsMatch ? pointsMatch[1].replace(/,/g, '') : '0';

            // 嘗試提取 label（points / 點 / 購物金 等）
            var labelMatch = text.match(/\d+[\d,]*\s*(points?|點|購物金)/i);
            var label = labelMatch ? labelMatch[1] : (this.settings.i18n.points || 'Points');

            // 取得連結的 onclick 或 href
            var linkAction = null;
            if ($link.length) {
                linkAction = $link.attr('onclick') || "jQuery('#wlr-reward-link').click()";
            }

            return {
                points: points,
                label: label,
                linkAction: linkAction,
                originalElement: $element
            };
        },

        /**
         * 建立美化的購物金區塊
         */
        createCustomPointsBlock: function(data) {
            var i18n = this.settings.i18n || {};

            var $block = $('<div class="ys-wployalty-block"></div>');

            // 標題
            var $title = $('<h3 class="yangsheep-h3-title">' + (i18n.points || '購物金') + '</h3>');

            // 可用點數文字
            var availableText = (i18n.available || '目前有') + ' <strong class="ys-points-value">' +
                                data.points + ' ' + data.label + '</strong> ' +
                                (i18n.can_use || '可用');
            var $available = $('<p class="ys-points-available">' + availableText + '</p>');

            // 說明文字
            var $hint = $('<p class="ys-points-hint">' + (i18n.hint || '按下兌換按鈕，於彈出視窗中兌換') + '</p>');

            // 兌換按鈕（加上 button class 以繼承佈景主題按鈕樣式）
            var $button = $('<button type="button" class="button ys-wployalty-button">' +
                           (i18n.redeem || '點此兌換折扣') + '</button>');

            // 綁定按鈕事件
            $button.on('click', function(e) {
                e.preventDefault();

                // 觸發原始 WLR 連結
                var $originalLink = data.originalElement.find('a#wlr-reward-link, a[href*="void"]');
                if ($originalLink.length) {
                    $originalLink[0].click();
                } else if (data.linkAction) {
                    try {
                        eval(data.linkAction);
                    } catch (err) {
                        console.error('[YS WPLoyalty] Error triggering link action:', err);
                    }
                }
            });

            $block.append($title).append($available).append($hint).append($button);

            return $block;
        }
    };

    // 初始化
    setTimeout(function() {
        WPLoyaltyIntegration.init();
    }, 500);

    // 頁面完全載入後再次處理
    $(window).on('load', function() {
        setTimeout(function() {
            WPLoyaltyIntegration.processWLRMessage();
        }, 1000);
    });
});
