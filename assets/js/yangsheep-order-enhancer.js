/**
 * YangSheep Checkout Optimizer - Order Enhancer JS
 * Handles Order List Expansion and Logistics Status Fetching
 */

(function ($) {
    'use strict';

    var YSOrderEnhancer = {
        init: function () {
            $(document).on('click', '.ys-expand-toggle', this.handleToggle);
            $(document).on('click', '.ys-panel-refresh', this.handlePanelRefresh);
        },

        handleToggle: function (e) {
            e.preventDefault();
            var $btn = $(this);
            var orderId = $btn.data('order-id');
            var $row = $btn.closest('tr'); // The order row
            var $existingPanel = $row.next('.ys-expanded-row');

            // If already expanded, collapse and remove
            if ($btn.hasClass('expanded')) {
                $btn.removeClass('expanded');
                $row.removeClass('has-expanded-panel');
                $existingPanel.slideUp(200, function () {
                    $(this).remove();
                });
                return;
            }

            // Mark as expanded
            $btn.addClass('expanded');
            $row.addClass('has-expanded-panel');

            // Create expanded row
            // Check colspan count
            var colSpan = $row.find('td').length;

            var $expandedRow = $('<tr class="ys-expanded-row"><td colspan="' + colSpan + '"><div class="ys-order-expanded-panel"><div class="ys-panel-loading">載入中...</div></div></td></tr>');

            $row.after($expandedRow);
            $expandedRow.hide().slideDown(200);

            // Fetch Details
            YSOrderEnhancer.loadDetails(orderId, $expandedRow.find('.ys-order-expanded-panel'));
        },

        handlePanelRefresh: function (e) {
            e.preventDefault();
            var $btn = $(this);
            var orderId = $btn.data('order-id');
            var $panel = $btn.closest('.ys-order-expanded-panel');

            $panel.html('<div class="ys-panel-loading">更新中...</div>');
            YSOrderEnhancer.loadDetails(orderId, $panel);
        },

        loadDetails: function (orderId, $panel) {
            $.ajax({
                url: yangsheep_enhancer_params.ajax_url,
                type: 'POST',
                data: {
                    action: 'yangsheep_get_order_logistics',
                    nonce: yangsheep_enhancer_params.nonce,
                    order_id: orderId
                },
                success: function (response) {
                    if (response.success) {
                        var d = response.data;
                        var stepHtml = YSOrderEnhancer.buildProgressSteps(d.current_step, d.flow_type, d.status_text);

                        // Default labels
                        var storeLabel = '取貨門市';
                        var storeValue = d.store_name || '-';

                        // Adjust label if home delivery (no store name usually, maybe address?)
                        // If store_name is empty, we can hide the row or show address if available in future

                        var detailHtml = '';
                        if (d.store_name) {
                            detailHtml += '<div class="detail-item"><div class="detail-label">取貨門市</div><div class="detail-value">' + d.store_name + '</div></div>';
                        }

                        detailHtml += '<div class="detail-item"><div class="detail-label">物流單號</div><div class="detail-value">' + (d.tracking_number || '-') + '</div></div>';

                        var html = '<div class="ys-panel-header">' +
                            '<h4 class="ys-panel-title">' + (d.service_name || '物流詳情') + '</h4>' +
                            '<button type="button" class="ys-panel-refresh" data-order-id="' + orderId + '">🔄 更新貨態</button>' +
                            '</div>' +
                            '<div class="ys-status-display">' +
                            '<div class="ys-status-text">' + d.status_text + '</div>' +
                            '<div class="ys-status-time">' + (d.update_time || '') + '</div>' +
                            '</div>' +
                            stepHtml +
                            '<div class="ys-panel-details">' + detailHtml + '</div>';

                        $panel.html(html);
                    } else {
                        $panel.html('<div class="ys-panel-error">' + (response.data || '無法載入物流資訊') + '</div>');
                    }
                },
                error: function () {
                    $panel.html('<div class="ys-panel-error">連線失敗，請稍後再試</div>');
                }
            });
        },

        buildProgressSteps: function (currentStep, flowType, statusText) {
            var steps = [];
            var activeIndex = 0; // 0-based index

            // Helper to determine if Step 2 is Preparing or Shipping based on text
            var isPreparing = function (text) {
                return text && (text.indexOf('準備') !== -1 || text.indexOf('列印') !== -1 || text.indexOf('等待') !== -1);
            };

            if (flowType === 'home') {
                // Home: Created, Preparing, Shipping, Completed
                // Visual Steps: 訂單成立 -> 商品準備中 -> 運送中 -> 配送完成
                steps = ['訂單成立', '商品準備中', '運送中', '配送完成'];

                if (currentStep === 4) activeIndex = 3; // Completed
                else if (currentStep === 3) activeIndex = 2; // Shipping (Arrived at station)
                else if (currentStep === 2) {
                    // Step 2 in PHP covers both Preparing and Shipping keywords
                    if (isPreparing(statusText)) activeIndex = 1;
                    else activeIndex = 2;
                }
                else {
                    // Step 1
                    if (isPreparing(statusText)) activeIndex = 1;
                    else activeIndex = 0;
                }

            } else if (flowType === 'manual') {
                steps = ['訂單成立', '已出貨'];
                if (currentStep >= 2) activeIndex = 1;
                else activeIndex = 0;
            } else {
                // CVS: Created, Preparing, Shipping, Arrived, Completed
                // Visual Steps: 訂單成立 -> 商品準備中 -> 運送中 -> 已到店 -> 已取貨
                steps = ['訂單成立', '商品準備中', '運送中', '已到店', '已取貨'];

                if (currentStep === 4) activeIndex = 4; // Completed (Picked Up)
                else if (currentStep === 3) activeIndex = 3; // Arrived
                else if (currentStep === 2) {
                    // Step 2 in PHP covers both Preparing and Shipping keywords
                    if (isPreparing(statusText)) activeIndex = 1; // Preparing
                    else activeIndex = 2; // Shipping
                }
                else {
                    // Step 1
                    if (isPreparing(statusText)) activeIndex = 1;
                    else activeIndex = 0;
                }
            }

            // 計算進度條寬度百分比
            var progressWidth = 0;
            if (steps.length > 1 && activeIndex > 0) {
                progressWidth = (activeIndex / (steps.length - 1)) * 90; // 90% 是因為 padding 5% 兩邊
            }

            var html = '<div class="ys-mini-progress" style="--progress-width: ' + progressWidth + '%;">';
            for (var i = 0; i < steps.length; i++) {
                var activeClass = (i <= activeIndex) ? ' active' : '';

                html += '<div class="ys-mini-step' + activeClass + '">' +
                    '<div class="step-dot"></div>' +
                    '<div class="step-label">' + steps[i] + '</div>' +
                    '</div>';
            }
            html += '</div>';
            return html;
        }
    };

    $(document).ready(function () {
        YSOrderEnhancer.init();
    });

})(jQuery);
