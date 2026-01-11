/**
 * 結帳側邊欄互動腳本
 * 
 * 功能：
 * - 桌機版：移動 sidebar-wrapper 到 sidebar-column
 * - sticky 效果：觸底時切換為 absolute bottom
 * - 可折疊區塊
 * 
 * @package YANGSHEEP_Checkout_Optimization
 * @version 1.3.1
 * @since 2026-01-08
 */

jQuery(function ($) {
    'use strict';

    /**
     * 側邊欄管理器
     */
    var YangsheepSidebar = {

        $sidebarWrapper: null,
        $sidebarColumn: null,
        $form: null,
        isDesktop: false,
        sidebarMoved: false,

        init: function () {
            var self = this;

            this.$sidebarWrapper = $('.yangsheep-checkout-sidebar-wrapper');
            this.$sidebarColumn = $('#yangsheep-sidebar-column');
            this.$form = $('form.checkout.woocommerce-checkout');

            this.checkViewport();
            this.bindEvents();

            // 初始化時執行
            this.handleResize();
        },

        checkViewport: function () {
            this.isDesktop = window.innerWidth >= 1000;
        },

        bindEvents: function () {
            var self = this;

            // 可折疊區塊
            $(document).on('click', '.yangsheep-collapsible', function () {
                var $title = $(this);
                var targetId = $title.data('target');
                var $content = $('#' + targetId);

                $title.toggleClass('collapsed');
                $content.slideToggle(200);
            });

            // 視窗大小變化
            $(window).on('resize', function () {
                self.handleResize();
            });

            // 滾動事件（桌機版）
            $(window).on('scroll', function () {
                if (self.isDesktop && self.sidebarMoved) {
                    self.handleScroll();
                }
            });
        },

        handleResize: function () {
            this.checkViewport();

            if (this.isDesktop) {
                this.moveSidebarToColumn();
            } else {
                this.moveSidebarToForm();
            }
        },

        /**
         * 桌機版：移動 sidebar 到 sidebar-column
         */
        moveSidebarToColumn: function () {
            if (this.sidebarMoved) return;
            if (!this.$sidebarWrapper.length || !this.$sidebarColumn.length) return;

            this.$sidebarWrapper.appendTo(this.$sidebarColumn);
            this.sidebarMoved = true;
        },

        /**
         * 手機版：將 sidebar 移回 form 內
         */
        moveSidebarToForm: function () {
            if (!this.sidebarMoved) return;
            if (!this.$sidebarWrapper.length || !this.$form.length) return;

            // 移到 payment 之前
            var $payment = this.$form.find('.yangsheep-payment');
            if ($payment.length) {
                this.$sidebarWrapper.insertBefore($payment);
            } else {
                this.$sidebarWrapper.appendTo(this.$form);
            }

            this.$sidebarWrapper.removeClass('is-bottom');
            this.sidebarMoved = false;
        },

        /**
         * 處理滾動：觸底時切換 position
         */
        handleScroll: function () {
            if (!this.$sidebarColumn.length || !this.$sidebarWrapper.length) return;

            var columnTop = this.$sidebarColumn.offset().top;
            var columnHeight = this.$sidebarColumn.outerHeight();
            var sidebarHeight = this.$sidebarWrapper.outerHeight();
            var scrollTop = $(window).scrollTop();
            var windowHeight = $(window).height();
            var stickyTop = 120; // 與 CSS top 一致

            // sidebar 底部位置
            var sidebarBottom = scrollTop + stickyTop + sidebarHeight;
            // column 底部位置
            var columnBottom = columnTop + columnHeight;

            if (sidebarBottom >= columnBottom) {
                // 觸底
                this.$sidebarWrapper.addClass('is-bottom');
            } else {
                // 正常 sticky
                this.$sidebarWrapper.removeClass('is-bottom');
            }
        }
    };

    // 頁面載入後初始化
    YangsheepSidebar.init();

    // AJAX 更新後重新初始化
    $(document.body).on('updated_checkout', function () {
        YangsheepSidebar.handleResize();
    });
});
