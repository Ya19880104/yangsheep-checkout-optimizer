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

    function escHtml(str) {
        if (!str) return '';
        return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#39;');
    }

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
            var $allWlrMessages = $(this.selectors.wlrMessage);
            var $mountedSource = $allWlrMessages.filter('.ys-wployalty-source-mounted').first();
            var $wlrMessage = $mountedSource.length ? $mountedSource : $allWlrMessages.filter(function() {
                var $message = $(this);
                return $message.is(':visible') &&
                    $message.find('a#wlr-reward-link, a[href*="void"]').length > 0;
            }).first();
            var $couponPoint = $(this.selectors.couponPoint);
            var $couponBlock = $(this.selectors.couponBlock);

            console.log('[YS WPLoyalty] Processing, found valid WLR source:', $wlrMessage.length);

            // P1 fail-open gate：主結帳 JS 未成功增強（被擋/失敗/尚未執行）時，
            // YS 容器仍是 hidden — 不得建立替代介面、更不得隱藏原生 WLR。
            // 若先前已建過替代介面（增強後又失效的邊緣情境），還原原生顯示。
            if (!$('form.checkout').hasClass('ys-checkout-enhanced')) {
                $couponPoint.find('.ys-wployalty-block').remove();
                $allWlrMessages.filter('.ys-wployalty-source-mounted')
                    .removeClass('ys-wployalty-source-mounted')
                    .show();
                console.log('[YS WPLoyalty] checkout not enhanced; native WLR message stays visible');
                return;
            }

            // 如果沒有目標容器，直接返回
            if (!$couponPoint.length) {
                return;
            }

            // 如果沒有 WLR 訊息
            if (!$wlrMessage.length) {
                // v1.6.31：先移除自己既有的 .ys-wployalty-block（若之前建過）
                $couponPoint.find('.ys-wployalty-block').remove();
                $allWlrMessages.filter('.ys-wployalty-source-mounted')
                    .removeClass('ys-wployalty-source-mounted')
                    .show();

                // 檢查是否還有其他外掛（例如 YITH Points）掛在 couponPoint 內的內容
                var $othersRemaining = $couponPoint.children().not('.ys-wployalty-block, script, style');
                if ($othersRemaining.length > 0) {
                    // 還有其他外掛內容 → 保持容器顯示（不 hide）
                    $couponPoint.addClass('has-content').show();
                    $couponBlock.addClass('has-point');
                    return;
                }

                // 完全沒內容才隱藏購物金區塊
                $couponPoint.removeClass('has-content').hide();
                $couponBlock.removeClass('has-point');
                return;
            }

            // 解析 WLR 訊息內容
            var pointsData = this.parseWLRMessage($wlrMessage);

            if (!pointsData) {
                console.log('[YS WPLoyalty] Could not parse WLR message');
                $couponPoint.find('.ys-wployalty-block').remove();
                $allWlrMessages.filter('.ys-wployalty-source-mounted')
                    .removeClass('ys-wployalty-source-mounted')
                    .show();
                $wlrMessage.show();

                var $otherProviderContent = $couponPoint.children().not('.ys-wployalty-block, script, style');
                if ($otherProviderContent.length > 0) {
                    $couponPoint.addClass('has-content').show();
                    $couponBlock.addClass('has-point');
                } else {
                    $couponPoint.removeClass('has-content').hide();
                    $couponBlock.removeClass('has-point');
                }
                return;
            }

            this.state.pointsData = pointsData;

            // 建立美化的購物金區塊
            var $customBlock = this.createCustomPointsBlock(pointsData);

            // v1.6.31：改為只移除自己既有的 .ys-wployalty-block 再 append
            // 不用 .empty() 避免清掉 YITH Points 或其他外掛加的元素
            // （initPointRedeemBlock 可能同時把 YITH #yith-par-message-cart 搬進來）
            $couponPoint.find('.ys-wployalty-block').remove();
            $couponPoint.append($customBlock);

            // 先顯示容器，再實際確認替代介面可見，最後才隱藏原生（fail-open）
            $couponPoint.addClass('has-content').show();
            $couponBlock.addClass('has-point');

            if ($customBlock.is(':visible')) {
                $wlrMessage.addClass('ys-wployalty-source-mounted');
                $wlrMessage.hide();
                console.log('[YS WPLoyalty] Custom points block created (additive, preserves 3rd-party children)');
            } else {
                // 替代介面不可見（容器被外部隱藏等）→ 撤回替代、保留原生
                $customBlock.remove();
                $wlrMessage.removeClass('ys-wployalty-source-mounted').show();
                console.warn('[YS WPLoyalty] replacement not visible; keeping native WLR message');
            }
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
            if (!pointsMatch || !$link.length) {
                return null;
            }
            var points = pointsMatch[1].replace(/,/g, '');

            // 嘗試提取 label（points / 點 / 購物金 等）
            var labelMatch = text.match(/\d+[\d,]*\s*(points?|點|購物金)/i);
            var label = labelMatch ? labelMatch[1] : (this.settings.i18n.points || 'Points');

            // 保存原始連結元素的參考（不存字串，避免動態執行）
            var $linkRef = $link.length ? $link : null;

            return {
                points: points,
                label: label,
                $linkRef: $linkRef,
                originalElement: $element
            };
        },

        /**
         * 建立美化的購物金區塊
         */
        createCustomPointsBlock: function(data) {
            var i18n = this.settings.i18n || {};

            var $block = $('<div class="ys-wployalty-block ys-loyalty-provider ys-loyalty-provider--wployalty"></div>');

            // 標題
            var pointsLabel = this.settings.points_label || i18n.points || '購物金';
            var $title = $('<h3 class="yangsheep-h3-title ys-loyalty-title">' + escHtml(pointsLabel) + '</h3>');

            // 可用點數文字
            var availableTemplate = this.settings.available_text || '目前有 {points} {label} 可用';
            var availableText = availableTemplate
                .replace('{points}', data.points)
                .replace('{label}', data.label || pointsLabel);
            var $available = $('<p class="ys-points-available"></p>').text(availableText);

            // 說明文字
            var $hint = $('<p class="ys-points-hint">' + escHtml(i18n.hint || '按下兌換按鈕，於彈出視窗中兌換') + '</p>');

            // 兌換按鈕（加上 button class 以繼承佈景主題按鈕樣式）
            var buttonText = this.settings.button_text || i18n.redeem || '點此兌換折扣';
            var $button = $('<button type="button" class="button ys-wployalty-button">' +
                           escHtml(buttonText) + '</button>');

            // 綁定按鈕事件
            $button.on('click', function(e) {
                e.preventDefault();

                // 觸發原始 WLR 連結（優先從 DOM 查找，備援用解析時保存的參考）
                var $originalLink = data.originalElement.find('a#wlr-reward-link, a[href*="void"]');
                if ($originalLink.length) {
                    $originalLink[0].click();
                } else if (data.$linkRef && data.$linkRef.length) {
                    data.$linkRef[0].click();
                } else {
                    // 最終備援：全域搜尋 WPLoyalty 連結
                    var $globalLink = $('a#wlr-reward-link');
                    if ($globalLink.length) {
                        $globalLink[0].click();
                    } else {
                        console.warn('[YS WPLoyalty] 找不到兌換連結');
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
