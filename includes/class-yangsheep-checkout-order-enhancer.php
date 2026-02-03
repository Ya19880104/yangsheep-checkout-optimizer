<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use YangSheep\CheckoutOptimizer\Settings\YSSettingsManager;

/**
 * Class YANGSHEEP_Checkout_Order_Enhancer
 *
 * Handles enhanced order list UI and logistics status integration.
 */
class YANGSHEEP_Checkout_Order_Enhancer {

    private static $instance = null;

    public static function get_instance() {
        if ( is_null( self::$instance ) ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        // Only initialize if enabled
        if ( YSSettingsManager::get( 'yangsheep_enable_order_enhancement', 'no' ) !== 'yes' ) {
            return;
        }

        // Frontend Hooks
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
        add_filter( 'woocommerce_my_account_my_orders_columns', array( $this, 'add_status_column' ) );
        add_action( 'woocommerce_my_account_my_orders_column_yangsheep_shipping_status', array( $this, 'render_status_column' ) );

        // AJAX
        add_action( 'wp_ajax_yangsheep_get_order_logistics', array( $this, 'ajax_get_logistics_details' ) );
        add_action( 'wp_ajax_nopriv_yangsheep_get_order_logistics', array( $this, 'ajax_get_logistics_details' ) );

        // Admin (Manual Tracking) - Default enabled to ensure visibility
        if ( YSSettingsManager::get( 'yangsheep_enable_manual_tracking', 'yes' ) === 'yes' ) {
            add_action( 'add_meta_boxes', array( $this, 'add_manual_tracking_metabox' ) );
            // HPOS 相容: 同時 hook 傳統 save_post 和 HPOS woocommerce_process_shop_order_meta
            add_action( 'save_post_shop_order', array( $this, 'save_manual_tracking_data' ) );
            add_action( 'woocommerce_process_shop_order_meta', array( $this, 'save_manual_tracking_data' ) );

            // AJAX for adding/removing manual tracking entries
            add_action( 'wp_ajax_yangsheep_add_manual_tracking', array( $this, 'ajax_add_manual_tracking' ) );
            add_action( 'wp_ajax_yangsheep_remove_manual_tracking', array( $this, 'ajax_remove_manual_tracking' ) );

            // Admin order list column
            add_filter( 'manage_edit-shop_order_columns', array( $this, 'add_admin_order_column' ), 20 );
            add_filter( 'manage_woocommerce_page_wc-orders_columns', array( $this, 'add_admin_order_column' ), 20 );
            add_action( 'manage_shop_order_posts_custom_column', array( $this, 'render_admin_order_column' ), 20, 2 );
            add_action( 'manage_woocommerce_page_wc-orders_custom_column', array( $this, 'render_admin_order_column_hpos' ), 20, 2 );

            // Admin order list CSS
            add_action( 'admin_head', array( $this, 'enqueue_admin_styles' ) );
        }
    }

    /**
     * Enqueue Assets
     */
    public function enqueue_scripts() {
        if ( ! is_account_page() ) {
            return;
        }

        wp_enqueue_style(
            'yangsheep-order-enhancer',
            plugins_url( '../assets/css/yangsheep-order-enhancer.css', __FILE__ ),
            array(),
            YANGSHEEP_CHECKOUT_OPTIMIZATION_VERSION
        );

        wp_enqueue_script(
            'yangsheep-order-enhancer',
            plugins_url( '../assets/js/yangsheep-order-enhancer.js', __FILE__ ),
            array( 'jquery' ),
            YANGSHEEP_CHECKOUT_OPTIMIZATION_VERSION,
            true
        );

        wp_localize_script( 'yangsheep-order-enhancer', 'yangsheep_enhancer_params', array(
            'ajax_url' => admin_url( 'admin-ajax.php' ),
            'nonce'    => wp_create_nonce( 'yangsheep_enhancer_nonce' )
        ));
    }

    /**
     * Add "Delivery Status" Column
     */
    public function add_status_column( $columns ) {
        $new_columns = array();
        foreach ( $columns as $key => $value ) {
            $new_columns[ $key ] = $value;
            if ( 'order-status' === $key ) {
                $new_columns['yangsheep_shipping_status'] = __( '配送狀態', 'yangsheep-checkout-optimization' );
            }
        }
        return $new_columns;
    }

    /**
     * Render Column Content - 支援多筆運輸
     */
    public function render_status_column( $order ) {
        $all_logistics = $this->get_all_logistics_data( $order );

        echo '<div class="ys-shipping-status-cell">';

        if ( empty( $all_logistics ) ) {
            // 無物流資訊
            $service_name = $order->get_shipping_method();
            if ( empty( $service_name ) ) $service_name = __( '配送', 'yangsheep-checkout-optimization' );
            echo '<div class="ys-status-service-name">' . esc_html( $service_name ) . '</div>';
            echo '<div class="ys-status-row">';
            echo '<span class="ys-status-badge ys-status-default">' . esc_html__( '訂單成立', 'yangsheep-checkout-optimization' ) . '</span>';
            echo '</div>';
        } else {
            // 有物流資訊 - 可能有多筆
            foreach ( $all_logistics as $index => $data ) {
                if ( $index > 0 ) {
                    echo '<div class="ys-logistics-divider"></div>';
                }

                // Line 1: Service Name
                $service_name = ! empty( $data['service_name'] ) ? $data['service_name'] : __( '配送', 'yangsheep-checkout-optimization' );
                echo '<div class="ys-status-service-name">' . esc_html( $service_name ) . '</div>';

                // Line 2: Status Badge + Toggle
                echo '<div class="ys-status-row">';

                $status_class = $this->get_status_class( $data['status_text'] );
                echo '<span class="ys-status-badge ' . esc_attr( $status_class ) . '">' . esc_html( $data['status_text'] ) . '</span>';

                // Toggle Button Logic
                $show_button = false;

                if ( $data['provider'] === 'paynow' || $data['provider'] === 'payuni' ) {
                    $show_button = true;
                } elseif ( $data['provider'] === 'manual' ) {
                    if ( ! empty( $data['tracking_number'] ) ) {
                        $show_button = true;
                    }
                }

                if ( $show_button ) {
                    echo ' <button type="button" class="ys-expand-toggle" data-order-id="' . esc_attr( $order->get_id() ) . '" data-logistics-index="' . esc_attr( $index ) . '" title="' . esc_attr__( '查看詳情', 'yangsheep-checkout-optimization' ) . '">';
                    echo '<span class="ys-toggle-triangle">▼</span>';
                    echo '</button>';
                }

                echo '</div>'; // End line 2

                // 若為手動物流，直接顯示物流單號
                if ( $data['provider'] === 'manual' && ! empty( $data['tracking_number'] ) ) {
                    echo '<div class="ys-tracking-number-row">';
                    echo '<span class="ys-tracking-label">' . esc_html__( '物流單號', 'yangsheep-checkout-optimization' ) . '：</span>';
                    echo '<span class="ys-tracking-number">' . esc_html( $data['tracking_number'] ) . '</span>';
                    echo '</div>';
                }
            }
        }

        echo '</div>';
    }

    /**
     * Get Status Class
     */
    private function get_status_class( $status ) {
        if ( strpos( $status, '運送' ) !== false || strpos( $status, '出貨' ) !== false ) return 'ys-status-shipping';
        if ( strpos( $status, '到店' ) !== false || strpos( $status, '取貨' ) !== false || strpos( $status, '配達' ) !== false ) return 'ys-status-arrived';
        if ( strpos( $status, '完成' ) !== false ) return 'ys-status-completed';
        if ( strpos( $status, '準備' ) !== false ) return 'ys-status-preparing';
        return 'ys-status-default';
    }

    /**
     * AJAX: Get Logistics Details
     */
    public function ajax_get_logistics_details() {
        check_ajax_referer( 'yangsheep_enhancer_nonce', 'nonce' );

        $order_id = isset( $_POST['order_id'] ) ? intval( $_POST['order_id'] ) : 0;
        $logistics_index = isset( $_POST['logistics_index'] ) ? intval( $_POST['logistics_index'] ) : 0;

        if ( ! $order_id ) {
            wp_send_json_error( '無效的訂單編號' );
        }

        $order = wc_get_order( $order_id );
        if ( ! $order ) {
            wp_send_json_error( '訂單不存在' );
        }

        // Permission Check: Owner or Admin
        if ( $order->get_user_id() != get_current_user_id() && ! current_user_can( 'manage_woocommerce' ) ) {
            wp_send_json_error( '無權限查看此訂單' );
        }

        $all_logistics = $this->get_all_logistics_data( $order );

        if ( isset( $all_logistics[ $logistics_index ] ) ) {
            wp_send_json_success( $all_logistics[ $logistics_index ] );
        } else {
            wp_send_json_error( '找不到物流資料' );
        }
    }

    /**
     * Get All Logistics Data - 統一獲取所有物流資訊（自動+手動）
     */
    private function get_all_logistics_data( $order ) {
        $all_logistics = array();

        // Detect Provider using Meta or Method ID
        $shipping_methods = $order->get_shipping_methods();
        $shipping_method_id = '';
        $service_name = $order->get_shipping_method();

        if ( ! empty( $shipping_methods ) ) {
            $first_method = reset( $shipping_methods );
            $shipping_method_id = $first_method->get_method_id();
        }

        // 1. PayNow Detection
        // 支援兩種 meta key: _ys_paynow_logistic_service_id (新版) 和 _ys_logistic_service_id (舊版/WOOMP)
        if ( $order->get_meta( '_ys_paynow_logistic_service_id' ) || $order->get_meta( '_ys_logistic_service_id' ) || strpos( $shipping_method_id, 'ys_paynow' ) !== false ) {
            $all_logistics[] = $this->get_paynow_logistics_data( $order, $service_name );
        }
        // 2. PayUni Detection
        elseif ( $order->get_meta( '_payuni_shipping_ship_type' ) || strpos( $shipping_method_id, 'payuni' ) !== false ) {
            $all_logistics[] = $this->get_payuni_logistics_data( $order, $service_name );
        }

        // 3. Manual Tracking Entries - 追加多筆手動物流
        if ( YSSettingsManager::get( 'yangsheep_enable_manual_tracking', 'yes' ) === 'yes' ) {
            $manual_entries = $order->get_meta( '_yangsheep_manual_tracking_entries' );

            if ( ! empty( $manual_entries ) && is_array( $manual_entries ) ) {
                foreach ( $manual_entries as $entry ) {
                    if ( ! empty( $entry['carrier'] ) || ! empty( $entry['tracking_no'] ) ) {
                        $all_logistics[] = array(
                            'provider'        => 'manual',
                            'service_name'    => ! empty( $entry['carrier'] ) ? $entry['carrier'] : __( '廠商出貨', 'yangsheep-checkout-optimization' ),
                            'tracking_number' => isset( $entry['tracking_no'] ) ? $entry['tracking_no'] : '',
                            'status_text'     => ! empty( $entry['tracking_no'] ) ? __( '廠商出貨', 'yangsheep-checkout-optimization' ) : __( '訂單成立', 'yangsheep-checkout-optimization' ),
                            'current_step'    => ! empty( $entry['tracking_no'] ) ? 2 : 1,
                            'store_name'      => '',
                            'update_time'     => '',
                            'flow_type'       => 'manual'
                        );
                    }
                }
            }

            // 相容舊格式 - 單筆手動物流
            $legacy_carrier = $order->get_meta( '_yangsheep_manual_carrier' );
            $legacy_tracking = $order->get_meta( '_yangsheep_manual_tracking_no' );

            if ( ( ! empty( $legacy_carrier ) || ! empty( $legacy_tracking ) ) && empty( $manual_entries ) ) {
                $all_logistics[] = array(
                    'provider'        => 'manual',
                    'service_name'    => ! empty( $legacy_carrier ) ? $legacy_carrier : __( '廠商出貨', 'yangsheep-checkout-optimization' ),
                    'tracking_number' => $legacy_tracking,
                    'status_text'     => ! empty( $legacy_tracking ) ? __( '廠商出貨', 'yangsheep-checkout-optimization' ) : __( '訂單成立', 'yangsheep-checkout-optimization' ),
                    'current_step'    => ! empty( $legacy_tracking ) ? 2 : 1,
                    'store_name'      => '',
                    'update_time'     => '',
                    'flow_type'       => 'manual'
                );
            }
        }

        return $all_logistics;
    }

    /**
     * Get PayUni Logistics Data
     */
    private function get_payuni_logistics_data( $order, $service_name ) {
        $ship_type = $order->get_meta( '_payuni_shipping_ship_type' );

        if ( empty( $service_name ) ) {
            $service_name = ( $ship_type == '1' ) ? '7-11 超商取貨 (PayUni)' : '黑貓宅急便 (PayUni)';
        }

        $flow_type = ( $ship_type == '2' ) ? 'home' : 'cvs';

        $tracking_number = $order->get_meta( '_payuni_shipping_sno' );

        if ( empty( $tracking_number ) ) {
             $odno = $order->get_meta( '_payuni_shipping_odno' );
             if ( ! empty( $odno ) ) {
                 $val_no = $order->get_meta( '_payuni_shipping_validation_no' );
                 $tracking_number = $odno . $val_no;
             }
        }
        if ( empty( $tracking_number ) ) {
            $tracking_number = $order->get_meta( '_payuni_shipping_tracking_no' );
        }

        $status_desc = $order->get_meta( '_payuni_shipping_ship_status_desc' );
        $store_name = '';

        if ( $flow_type === 'cvs' ) {
            $store_name = $order->get_meta( '_shipping_payuni_storename' );
        }

        $status_text = __( '訂單成立', 'yangsheep-checkout-optimization' );

        if ( ! empty( $tracking_number ) ) {
            $status_text = __( '商品準備中', 'yangsheep-checkout-optimization' );
        }

        if ( ! empty( $status_desc ) && strpos( $status_desc, '成立' ) === false ) {
             $status_text = $status_desc;
        }

        return array(
            'provider'        => 'payuni',
            'service_name'    => $service_name,
            'tracking_number' => $tracking_number,
            'status_text'     => $status_text,
            'current_step'    => $this->calculate_step( $status_text, $flow_type ),
            'store_name'      => $store_name,
            'update_time'     => '',
            'flow_type'       => $flow_type
        );
    }

    /**
     * Get PayNow Logistics Data (Dedicated Logic)
     */
    private function get_paynow_logistics_data( $order, $service_name ) {
        $store_name = '';
        $flow_type = 'cvs';

        // 支援兩種 meta key: _ys_paynow_logistic_service_id (新版) 和 _ys_logistic_service_id (舊版/WOOMP)
        $service_id = $order->get_meta( '_ys_paynow_logistic_service_id' );
        if ( empty( $service_id ) ) {
            $service_id = $order->get_meta( '_ys_logistic_service_id' );
        }

        if ( in_array( $service_id, array( '06', '36' ) ) || strpos( $service_name, '宅配' ) !== false || strpos( $service_name, '黑貓' ) !== false || strpos( $service_name, '郵寄' ) !== false ) {
            $flow_type = 'home';
            $store_name = '';
        } else {
            $flow_type = 'cvs';
            $store_name = $order->get_meta( '_ys_paynow_store_name' );
        }

        $tracking_number = $order->get_meta( '_ys_paynow_logistic_number' );
        $raw_status = $order->get_meta( '_ys_paynow_delivery_status' );

        $is_printed = $order->get_meta( '_ys_paynow_label_printed' ) === 'yes' || $order->get_meta( '_ys_label_printed' ) === 'yes';
        $update_time = $order->get_meta( '_ys_paynow_status_update_at' );

        $status_text = __( '訂單成立', 'yangsheep-checkout-optimization' );

        if ( $is_printed ) {
            $status_text = __( '商品準備中', 'yangsheep-checkout-optimization' );
        }

        if ( ! empty( $raw_status ) ) {
            if ( mb_strpos( $raw_status, '成立' ) === false ) {
                $status_text = $raw_status;
            }
        }

        return array(
            'provider'        => 'paynow',
            'service_name'    => $service_name,
            'tracking_number' => $tracking_number,
            'status_text'     => $status_text,
            'current_step'    => $this->calculate_step( $status_text, $flow_type ),
            'store_name'      => $store_name,
            'update_time'     => $update_time,
            'flow_type'       => $flow_type
        );
    }

    private function calculate_step( $status, $flow_type ) {
        if ( mb_strpos( $status, '完成' ) !== false || mb_strpos( $status, '取貨' ) !== false || mb_strpos( $status, '已取' ) !== false ) return 4;
        if ( mb_strpos( $status, '到店' ) !== false || mb_strpos( $status, '待取' ) !== false || mb_strpos( $status, '配達' ) !== false ) return 3;
        if ( mb_strpos( $status, '運送' ) !== false || mb_strpos( $status, '出貨' ) !== false || mb_strpos( $status, '離店' ) !== false || mb_strpos( $status, '等待' ) !== false || mb_strpos( $status, '準備' ) !== false ) return 2;
        return 1;
    }

    /**
     * Add Manual Tracking Metabox
     *
     * 只有在訂單不是使用 PayUni / YS PayNow 自動串接物流時才顯示
     *
     * 隱藏條件：
     * - PayUni 物流（payuni_ 前綴）
     * - YS PayNow 物流（ys_paynow_shipping_ 前綴）
     * - 有 _ys_logistic_service_id 或 _payuni_shipping_ship_type meta
     *
     * 顯示條件：
     * - 好用版 PayNow (paynow_shipping_ 前綴，非 ys_)
     * - 綠界 ECPay (ry_ecpay_ 前綴)
     * - 其他物流方式
     */
    public function add_manual_tracking_metabox( $post_type, $post_or_order = null ) {
        // 取得訂單物件
        $order = null;
        if ( $post_or_order instanceof \WC_Order ) {
            $order = $post_or_order;
        } elseif ( is_object( $post_or_order ) && isset( $post_or_order->ID ) ) {
            $order = wc_get_order( $post_or_order->ID );
        } elseif ( isset( $_GET['id'] ) ) {
            // HPOS 模式下從 URL 取得 order ID
            $order = wc_get_order( absint( $_GET['id'] ) );
        }

        // 檢查是否為 PayUni / YS PayNow 自動串接物流 - 如果是，不顯示 metabox
        if ( $order ) {
            // 方法1：檢查訂單 meta（YS PayNow / PayUni 會設定這些 meta）
            $ys_logistic_id = $order->get_meta( '_ys_logistic_service_id' );
            $payuni_ship_type = $order->get_meta( '_payuni_shipping_ship_type' );

            if ( $ys_logistic_id || $payuni_ship_type ) {
                return; // 使用 YS PayNow / PayUni 自動物流，不顯示手動配送 metabox
            }

            // 方法2：檢查訂單的物流方式 method_id
            $shipping_methods = $order->get_shipping_methods();
            foreach ( $shipping_methods as $shipping_method ) {
                $method_id = $shipping_method->get_method_id();

                // PayUni 物流（所有 payuni_ 前綴，包含超取、宅配、冷凍等）
                if ( strpos( $method_id, 'payuni_' ) === 0 ) {
                    return;
                }

                // YS PayNow 物流（ys_paynow_shipping_ 前綴，包含超取、宅配、冷凍等）
                if ( strpos( $method_id, 'ys_paynow_shipping_' ) === 0 ) {
                    return;
                }
            }
        }

        $screen = 'shop_order';
        if ( class_exists( '\Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableController' ) ) {
            $controller = wc_get_container()->get( \Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableController::class );
            if ( $controller && $controller->custom_orders_table_usage_is_enabled() ) {
                $screen = wc_get_page_screen_id( 'shop-order' );
            }
        }

        add_meta_box(
            'yangsheep_manual_tracking',
            __( '手動配送資訊 (YangSheep)', 'yangsheep-checkout-optimization' ),
            array( $this, 'render_manual_tracking_metabox' ),
            $screen,
            'side',
            'default'
        );
    }

    /**
     * Manual Tracking Admin Metabox Render - 支援多筆新增
     */
    public function render_manual_tracking_metabox( $post_or_order ) {
        $order = ( $post_or_order instanceof \WC_Order ) ? $post_or_order : wc_get_order( $post_or_order->ID );

        if ( ! $order ) {
            echo '<p>' . __( '無法載入訂單資料', 'yangsheep-checkout-optimization' ) . '</p>';
            return;
        }

        // 獲取現有的多筆手動物流資料
        $entries = $order->get_meta( '_yangsheep_manual_tracking_entries' );
        if ( ! is_array( $entries ) ) {
            $entries = array();
        }

        // 相容舊格式
        $legacy_carrier = $order->get_meta( '_yangsheep_manual_carrier' );
        $legacy_tracking = $order->get_meta( '_yangsheep_manual_tracking_no' );
        if ( empty( $entries ) && ( ! empty( $legacy_carrier ) || ! empty( $legacy_tracking ) ) ) {
            $entries[] = array(
                'carrier'     => $legacy_carrier,
                'tracking_no' => $legacy_tracking
            );
        }

        $carriers = array(
            '宅配通', '黑貓宅配', '新竹物流', '順豐速運', '郵寄掛號', '大榮貨運', '嘉里大榮', '台灣宅配通', '其他'
        );

        echo '<div id="ys-manual-tracking-container">';

        // 顯示現有記錄
        if ( ! empty( $entries ) ) {
            foreach ( $entries as $index => $entry ) {
                $this->render_tracking_entry_row( $index, $entry, $carriers );
            }
        }

        echo '</div>';

        // 新增按鈕
        echo '<div style="margin-top: 10px;">';
        echo '<button type="button" id="ys-add-tracking-entry" class="button button-secondary" style="width: 100%;">';
        echo '<span class="dashicons dashicons-plus-alt2" style="vertical-align: middle;"></span> ';
        echo __( '新增配送資訊', 'yangsheep-checkout-optimization' );
        echo '</button>';
        echo '</div>';

        wp_nonce_field( 'yangsheep_save_manual_tracking', 'yangsheep_manual_tracking_nonce' );

        // Inline JS/CSS
        ?>
        <style>
        .ys-tracking-entry {
            border: 1px solid #ddd;
            border-radius: 4px;
            padding: 10px;
            margin-bottom: 10px;
            background: #f9f9f9;
            position: relative;
        }
        .ys-tracking-entry:last-child {
            margin-bottom: 0;
        }
        .ys-tracking-entry .ys-entry-number {
            font-weight: bold;
            margin-bottom: 8px;
            color: #2271b1;
            font-size: 12px;
        }
        .ys-tracking-entry .ys-remove-entry {
            position: absolute;
            top: 5px;
            right: 5px;
            background: #dc3232;
            color: #fff;
            border: none;
            border-radius: 3px;
            width: 20px;
            height: 20px;
            line-height: 18px;
            text-align: center;
            cursor: pointer;
            font-size: 14px;
            padding: 0;
        }
        .ys-tracking-entry .ys-remove-entry:hover {
            background: #a00;
        }
        .ys-tracking-entry select,
        .ys-tracking-entry input[type="text"] {
            width: 100%;
            margin-bottom: 5px;
        }
        .ys-tracking-entry label {
            display: block;
            font-size: 11px;
            color: #666;
            margin-bottom: 2px;
        }
        #ys-manual-tracking-container:empty + div button {
            background: #2271b1;
            color: #fff;
            border-color: #2271b1;
        }
        </style>
        <script>
        jQuery(document).ready(function($) {
            var entryIndex = <?php echo count( $entries ); ?>;
            var carriers = <?php echo json_encode( $carriers ); ?>;

            // 新增記錄
            $('#ys-add-tracking-entry').on('click', function() {
                var html = generateEntryHtml(entryIndex, '', '');
                $('#ys-manual-tracking-container').append(html);
                entryIndex++;
            });

            // 移除記錄
            $(document).on('click', '.ys-remove-entry', function() {
                $(this).closest('.ys-tracking-entry').remove();
                reindexEntries();
            });

            // 物流商選擇變更
            $(document).on('change', '.ys-carrier-select', function() {
                var $input = $(this).siblings('.ys-carrier-input');
                if ($(this).val() === '其他') {
                    $input.show().val('').focus();
                } else {
                    $input.hide().val($(this).val());
                }
            });

            function generateEntryHtml(index, carrier, trackingNo) {
                var isOther = carrier && carriers.indexOf(carrier) === -1;
                var selectedVal = isOther ? '其他' : carrier;

                var html = '<div class="ys-tracking-entry" data-index="' + index + '">';
                html += '<span class="ys-entry-number">#' + (index + 1) + '</span>';
                html += '<button type="button" class="ys-remove-entry" title="移除">&times;</button>';

                html += '<label><?php echo esc_js( __( '物流商', 'yangsheep-checkout-optimization' ) ); ?></label>';
                html += '<select name="ys_tracking_entries[' + index + '][carrier_select]" class="ys-carrier-select">';
                html += '<option value=""><?php echo esc_js( __( '請選擇', 'yangsheep-checkout-optimization' ) ); ?></option>';

                for (var i = 0; i < carriers.length; i++) {
                    var selected = (carriers[i] === selectedVal) ? ' selected' : '';
                    html += '<option value="' + carriers[i] + '"' + selected + '>' + carriers[i] + '</option>';
                }
                html += '</select>';

                var inputStyle = (isOther || selectedVal === '其他') ? '' : 'display:none;';
                var inputVal = isOther ? carrier : (carrier || '');
                html += '<input type="text" name="ys_tracking_entries[' + index + '][carrier]" class="ys-carrier-input" value="' + inputVal + '" placeholder="<?php echo esc_js( __( '輸入物流商名稱', 'yangsheep-checkout-optimization' ) ); ?>" style="' + inputStyle + '" />';

                html += '<label><?php echo esc_js( __( '物流單號', 'yangsheep-checkout-optimization' ) ); ?></label>';
                html += '<input type="text" name="ys_tracking_entries[' + index + '][tracking_no]" value="' + (trackingNo || '') + '" placeholder="<?php echo esc_js( __( '輸入物流單號', 'yangsheep-checkout-optimization' ) ); ?>" />';

                html += '</div>';
                return html;
            }

            function reindexEntries() {
                $('#ys-manual-tracking-container .ys-tracking-entry').each(function(i) {
                    $(this).attr('data-index', i);
                    $(this).find('.ys-entry-number').text('#' + (i + 1));
                    $(this).find('select, input').each(function() {
                        var name = $(this).attr('name');
                        if (name) {
                            $(this).attr('name', name.replace(/\[\d+\]/, '[' + i + ']'));
                        }
                    });
                });
                entryIndex = $('#ys-manual-tracking-container .ys-tracking-entry').length;
            }

            // 初始化已存在的選擇器
            $('.ys-carrier-select').each(function() {
                var val = $(this).val();
                var $input = $(this).siblings('.ys-carrier-input');
                if (val !== '其他' && val !== '') {
                    $input.val(val);
                }
            });
        });
        </script>
        <?php
    }

    /**
     * Render single tracking entry row
     */
    private function render_tracking_entry_row( $index, $entry, $carriers ) {
        $carrier = isset( $entry['carrier'] ) ? $entry['carrier'] : '';
        $tracking_no = isset( $entry['tracking_no'] ) ? $entry['tracking_no'] : '';

        $is_other = ! empty( $carrier ) && ! in_array( $carrier, $carriers );
        $selected_val = $is_other ? '其他' : $carrier;

        echo '<div class="ys-tracking-entry" data-index="' . esc_attr( $index ) . '">';
        echo '<span class="ys-entry-number">#' . ( $index + 1 ) . '</span>';
        echo '<button type="button" class="ys-remove-entry" title="' . esc_attr__( '移除', 'yangsheep-checkout-optimization' ) . '">&times;</button>';

        echo '<label>' . __( '物流商', 'yangsheep-checkout-optimization' ) . '</label>';
        echo '<select name="ys_tracking_entries[' . $index . '][carrier_select]" class="ys-carrier-select">';
        echo '<option value="">' . __( '請選擇', 'yangsheep-checkout-optimization' ) . '</option>';

        foreach ( $carriers as $c ) {
            $selected = selected( $selected_val, $c, false );
            echo '<option value="' . esc_attr( $c ) . '"' . $selected . '>' . esc_html( $c ) . '</option>';
        }
        echo '</select>';

        $style = ( $is_other || $selected_val === '其他' ) ? '' : 'display:none;';
        echo '<input type="text" name="ys_tracking_entries[' . $index . '][carrier]" class="ys-carrier-input" value="' . esc_attr( $carrier ) . '" placeholder="' . esc_attr__( '輸入物流商名稱', 'yangsheep-checkout-optimization' ) . '" style="' . $style . '" />';

        echo '<label>' . __( '物流單號', 'yangsheep-checkout-optimization' ) . '</label>';
        echo '<input type="text" name="ys_tracking_entries[' . $index . '][tracking_no]" value="' . esc_attr( $tracking_no ) . '" placeholder="' . esc_attr__( '輸入物流單號', 'yangsheep-checkout-optimization' ) . '" />';

        echo '</div>';
    }

    /**
     * Save Manual Tracking Data - 支援多筆
     */
    public function save_manual_tracking_data( $post_id ) {
        if ( ! isset( $_POST['yangsheep_manual_tracking_nonce'] ) || ! wp_verify_nonce( $_POST['yangsheep_manual_tracking_nonce'], 'yangsheep_save_manual_tracking' ) ) {
            return;
        }

        $order = wc_get_order( $post_id );
        if ( ! $order ) {
            return;
        }

        // 處理多筆記錄
        $entries = array();

        if ( isset( $_POST['ys_tracking_entries'] ) && is_array( $_POST['ys_tracking_entries'] ) ) {
            foreach ( $_POST['ys_tracking_entries'] as $entry ) {
                $carrier = '';
                $tracking_no = '';

                // 物流商：優先使用輸入框的值，否則使用選擇器的值
                if ( ! empty( $entry['carrier'] ) ) {
                    $carrier = sanitize_text_field( $entry['carrier'] );
                } elseif ( ! empty( $entry['carrier_select'] ) && $entry['carrier_select'] !== '其他' ) {
                    $carrier = sanitize_text_field( $entry['carrier_select'] );
                }

                if ( ! empty( $entry['tracking_no'] ) ) {
                    $tracking_no = sanitize_text_field( $entry['tracking_no'] );
                }

                // 只保存有內容的記錄
                if ( ! empty( $carrier ) || ! empty( $tracking_no ) ) {
                    $entries[] = array(
                        'carrier'     => $carrier,
                        'tracking_no' => $tracking_no
                    );
                }
            }
        }

        $order->update_meta_data( '_yangsheep_manual_tracking_entries', $entries );

        // 清理舊格式（可選）
        // $order->delete_meta_data( '_yangsheep_manual_carrier' );
        // $order->delete_meta_data( '_yangsheep_manual_tracking_no' );

        $order->save();
    }

    /**
     * Add Admin Order List Column
     */
    public function add_admin_order_column( $columns ) {
        $new_columns = array();
        foreach ( $columns as $key => $value ) {
            $new_columns[ $key ] = $value;
            if ( 'order_status' === $key ) {
                $new_columns['ys_shipping_status'] = __( '配送狀態', 'yangsheep-checkout-optimization' );
            }
        }
        return $new_columns;
    }

    /**
     * Render Admin Order Column (Traditional)
     */
    public function render_admin_order_column( $column, $post_id ) {
        if ( 'ys_shipping_status' !== $column ) {
            return;
        }

        $order = wc_get_order( $post_id );
        if ( ! $order ) {
            return;
        }

        $this->output_admin_shipping_status( $order );
    }

    /**
     * Render Admin Order Column (HPOS)
     */
    public function render_admin_order_column_hpos( $column, $order ) {
        if ( 'ys_shipping_status' !== $column ) {
            return;
        }

        $this->output_admin_shipping_status( $order );
    }

    /**
     * Output Admin Shipping Status - 比照 PayNow 樣式設計
     */
    private function output_admin_shipping_status( $order ) {
        $all_logistics = $this->get_all_logistics_data( $order );

        if ( empty( $all_logistics ) ) {
            echo '<span class="na">–</span>';
            return;
        }

        foreach ( $all_logistics as $index => $data ) {
            if ( $index > 0 ) {
                echo '<div style="margin-top: 8px; padding-top: 8px; border-top: 1px dashed #ddd;"></div>';
            }

            echo '<div class="ys-paynow-shipping-info" style="line-height: 1.5;">';

            // 服務名稱
            echo '<strong>' . esc_html( $data['service_name'] ) . '</strong><br>';

            // 物流單號
            if ( ! empty( $data['tracking_number'] ) ) {
                echo '<code style="font-size: 11px; background: #f0f0f1; padding: 2px 4px;">' . esc_html( $data['tracking_number'] ) . '</code><br>';

                // 狀態標籤
                $status_class = $this->get_admin_status_class( $data['status_text'] );
                echo '<span class="ys-status-badge ' . esc_attr( $status_class ) . '">' . esc_html( $data['status_text'] ) . '</span>';

                // 已列印標記（僅 PayNow）
                if ( $data['provider'] === 'paynow' ) {
                    $is_printed = $order->get_meta( '_ys_label_printed' ) === 'yes' || $order->get_meta( '_ys_paynow_label_printed' ) === 'yes';
                    if ( $is_printed ) {
                        echo '<span class="ys-printed-badge" style="display:inline-block; margin-left:4px; font-size:10px; color:#007cba;">' . esc_html__( '(已列印)', 'yangsheep-checkout-optimization' ) . '</span>';
                    }
                }
            } else {
                // 無單號時顯示待建立
                echo '<span class="ys-status-badge ys-status-pending">' . esc_html__( '待建立', 'yangsheep-checkout-optimization' ) . '</span>';
            }

            echo '</div>';
        }
    }

    /**
     * Get Admin Status Class - 比照 PayNow 樣式
     */
    private function get_admin_status_class( $status ) {
        if ( strpos( $status, '等待' ) !== false || strpos( $status, '待' ) !== false ) {
            return 'ys-status-waiting';
        } elseif ( strpos( $status, '已建立' ) !== false || strpos( $status, '建立' ) !== false || strpos( $status, '成立' ) !== false ) {
            return 'ys-status-created';
        } elseif ( strpos( $status, '配送中' ) !== false || strpos( $status, '寄件' ) !== false || strpos( $status, '出貨' ) !== false || strpos( $status, '運送' ) !== false ) {
            return 'ys-status-shipping';
        } elseif ( strpos( $status, '到店' ) !== false || strpos( $status, '取貨' ) !== false || strpos( $status, '配達' ) !== false ) {
            return 'ys-status-arrived';
        } elseif ( strpos( $status, '完成' ) !== false || strpos( $status, '已取' ) !== false ) {
            return 'ys-status-completed';
        } elseif ( strpos( $status, '退' ) !== false || strpos( $status, '取消' ) !== false ) {
            return 'ys-status-cancelled';
        }
        return 'ys-status-default';
    }

    /**
     * 載入後台管理 CSS 樣式
     */
    public function enqueue_admin_styles() {
        $screen = get_current_screen();

        // 僅在訂單列表頁面載入
        $allowed_screens = array( 'edit-shop_order', 'woocommerce_page_wc-orders' );
        if ( ! $screen || ! in_array( $screen->id, $allowed_screens, true ) ) {
            return;
        }

        // 輸出與 PayNow 一致的狀態標籤樣式（莫蘭迪藍灰色系）
        echo '<style>
        .ys-paynow-shipping-info { line-height: 1.5; }
        .ys-paynow-shipping-info small { color: #666; }
        .ys-status-badge {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 3px;
            font-size: 11px;
            font-weight: 500;
            line-height: 1.4;
            white-space: nowrap;
        }
        .ys-status-pending { background: #f0f4f7; color: #7a8b95; }   /* 待建立 - 灰 */
        .ys-status-waiting { background: #e8eff5; color: #6b8a9a; }   /* 等待寄件 - 淡藍 */
        .ys-status-created { background: #e1f0ff; color: #0073aa; }   /* 已建立 - 藍 */
        .ys-status-shipping { background: #fef6e8; color: #b8860b; }  /* 配送中 - 橘黃 */
        .ys-status-arrived { background: #f3e5f5; color: #7b1fa2; }   /* 到店待取 - 紫 */
        .ys-status-completed { background: #e8f5e9; color: #2e7d32; } /* 已完成 - 深綠 */
        .ys-status-cancelled { background: #ffebee; color: #c62828; } /* 已取消/退貨 - 紅 */
        .ys-status-default { background: #f0f4f7; color: #7a8b95; }   /* 預設 - 灰 */
        </style>';
    }

}
