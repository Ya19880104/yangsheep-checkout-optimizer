<?php
/**
 * 結帳欄位設置類別
 * 
 * 處理結帳欄位的自訂設定，包括：
 * - WooCommerce 運送設定檢查
 * - First Name 關閉選項
 * - 台灣化欄位設置
 * - 欄位排序與寬度
 * - 訂單備註設置
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class YANGSHEEP_Checkout_Fields {

    private static $instance = null;

    public static function get_instance() {
        if ( is_null( self::$instance ) ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        // 欄位過濾（設定註冊已移至 class-yangsheep-checkout-settings.php）
        // 使用較高的 priority (100) 確保在其他外掛之後執行
        add_filter( 'woocommerce_checkout_fields', array( $this, 'customize_checkout_fields' ), 100 );
        add_filter( 'woocommerce_default_address_fields', array( $this, 'customize_address_fields' ), 100 );

        // Shipping Phone
        add_filter( 'woocommerce_shipping_fields', array( $this, 'add_shipping_phone' ), 20 );

        // 我的帳號欄位
        add_filter( 'woocommerce_customer_meta_fields', array( $this, 'add_customer_meta_fields' ), 20 );

        // 超取時修改必填欄位（在 checkout_fields 過濾器中處理，確保 AJAX 更新時也生效）
        add_filter( 'woocommerce_checkout_fields', array( $this, 'maybe_remove_address_required_for_cvs' ), 999 );

    }

    /**
     * 處理超取時的必填欄位驗證
     * 在 woocommerce_checkout_fields 過濾器中運行，確保 AJAX 更新時也生效
     * 
     * @param array $fields 結帳欄位
     * @return array
     */
    public function maybe_remove_address_required_for_cvs( $fields ) {
        // 嘗試從 POST 或 WC Session 取得運送方式
        $is_cvs_shipping = $this->is_cvs_shipping_selected();
        
        if ( $is_cvs_shipping ) {
            // 移除地址欄位的必填
            if ( isset( $fields['shipping']['shipping_address_1'] ) ) {
                $fields['shipping']['shipping_address_1']['required'] = false;
            }
            if ( isset( $fields['shipping']['shipping_address_2'] ) ) {
                $fields['shipping']['shipping_address_2']['required'] = false;
            }
            if ( isset( $fields['shipping']['shipping_city'] ) ) {
                $fields['shipping']['shipping_city']['required'] = false;
            }
            if ( isset( $fields['shipping']['shipping_state'] ) ) {
                $fields['shipping']['shipping_state']['required'] = false;
            }
            if ( isset( $fields['shipping']['shipping_postcode'] ) ) {
                $fields['shipping']['shipping_postcode']['required'] = false;
            }
        }
        
        return $fields;
    }
    
    /**
     * 檢查當前選擇的運送方式是否為超取
     * 
     * @return bool
     */
    private function is_cvs_shipping_selected() {
        // 優先從 POST 取得（AJAX 更新時）
        // phpcs:disable WordPress.Security.NonceVerification.Missing
        if ( isset( $_POST['shipping_method'] ) ) {
            $shipping_methods = wc_clean( wp_unslash( $_POST['shipping_method'] ) );
        } else {
            // 從 WC Session 取得
            $shipping_methods = WC()->session ? WC()->session->get( 'chosen_shipping_methods', array() ) : array();
        }
        // phpcs:enable WordPress.Security.NonceVerification.Missing
        
        if ( empty( $shipping_methods ) || ! is_array( $shipping_methods ) ) {
            return false;
        }
        
        foreach ( $shipping_methods as $method ) {
            if ( empty( $method ) ) {
                continue;
            }
            
            $method_id = strstr( $method, ':', true ) ?: $method;
            

            
            // PayUni 超取
            if ( strpos( $method_id, 'payuni_' ) === 0 && 
                 ( strpos( $method_id, '711' ) !== false || 
                   strpos( $method_id, 'fami' ) !== false || 
                   strpos( $method_id, 'hilife' ) !== false ) ) {
                return true;
            }
            
            // ECPay 超取
            if ( strpos( $method_id, 'ecpay' ) !== false && strpos( $method_id, 'cvs' ) !== false ) {
                return true;
            }
        }
        
        return false;
    }
    


    /**
     * 自訂結帳欄位
     *
     * 設定選項說明（已整合至 class-yangsheep-checkout-settings.php）：
     * - yangsheep_checkout_close_lname: 關閉 Last Name，保留 First Name 作為「姓名」
     * - yangsheep_checkout_tw_fields: 台灣化欄位（帳單只保留姓名、電話、電子郵件）
     * - yangsheep_checkout_order_note: 訂單備註開關
     */
    public function customize_checkout_fields( $fields ) {
        // 關閉 Last Name，保留 First Name 作為「姓名」
        // 使用新的 option 名稱: yangsheep_checkout_close_lname
        if ( get_option( 'yangsheep_checkout_close_lname', 'no' ) === 'yes' ) {
            // 隱藏 billing last name
            if ( isset( $fields['billing']['billing_last_name'] ) ) {
                unset( $fields['billing']['billing_last_name'] );
            }
            // 隱藏 shipping last name
            if ( isset( $fields['shipping']['shipping_last_name'] ) ) {
                unset( $fields['shipping']['shipping_last_name'] );
            }
            // 修改 first name 標籤為「姓名」並調整樣式
            if ( isset( $fields['billing']['billing_first_name'] ) ) {
                $fields['billing']['billing_first_name']['label'] = __( '姓名', 'yangsheep-checkout-optimization' );
                $fields['billing']['billing_first_name']['class'] = array( 'form-row-wide' );
                $fields['billing']['billing_first_name']['priority'] = 10;
            }
            if ( isset( $fields['shipping']['shipping_first_name'] ) ) {
                $fields['shipping']['shipping_first_name']['label'] = __( '姓名', 'yangsheep-checkout-optimization' );
                $fields['shipping']['shipping_first_name']['class'] = array( 'form-row-first' );  // 與電話並排顯示
                $fields['shipping']['shipping_first_name']['priority'] = 10;
            }
        }

        // 台灣化欄位
        // 使用新的 option 名稱: yangsheep_checkout_tw_fields
        if ( get_option( 'yangsheep_checkout_tw_fields', 'no' ) === 'yes' ) {
            // 帳單只保留姓名、電話、電子郵件（優先使用 first_name）
            $billing_keep = array( 'billing_first_name', 'billing_phone', 'billing_email' );
            // 如果沒有停用 last_name，也保留它
            if ( get_option( 'yangsheep_checkout_close_lname', 'no' ) !== 'yes' ) {
                $billing_keep[] = 'billing_last_name';
            }
            foreach ( $fields['billing'] as $key => $field ) {
                if ( ! in_array( $key, $billing_keep, true ) ) {
                    unset( $fields['billing'][ $key ] );
                }
            }

            // 調整帳單欄位順序
            if ( isset( $fields['billing']['billing_first_name'] ) ) {
                $fields['billing']['billing_first_name']['priority'] = 5;
            }
            if ( isset( $fields['billing']['billing_last_name'] ) ) {
                $fields['billing']['billing_last_name']['priority'] = 10;
            }
            if ( isset( $fields['billing']['billing_phone'] ) ) {
                $fields['billing']['billing_phone']['priority'] = 20;
            }
            if ( isset( $fields['billing']['billing_email'] ) ) {
                $fields['billing']['billing_email']['priority'] = 30;
            }

            // 檢測運送國家
            $shipping_country = WC()->customer ? WC()->customer->get_shipping_country() : '';
            if ( empty( $shipping_country ) ) {
                $shipping_country = WC()->countries->get_base_country();
            }

            // 根據國家決定運送欄位順序
            if ( $shipping_country === 'TW' ) {
                // 台灣格式：郵遞區號 → 縣市 → 鄉鎮市區 → 地址，隱藏 address_2
                $shipping_priorities = array(
                    'shipping_first_name' => 5,
                    'shipping_last_name'  => 10,
                    'shipping_phone'      => 20,
                    'shipping_country'    => 30,
                    'shipping_postcode'   => 40,   // 郵遞區號
                    'shipping_state'      => 50,   // 縣市
                    'shipping_city'       => 60,   // 鄉鎮市區
                    'shipping_address_1'  => 70,   // 地址
                );

                // 台灣欄位標籤
                if ( isset( $fields['shipping']['shipping_city'] ) ) {
                    $fields['shipping']['shipping_city']['label'] = __( '鄉鎮市區', 'yangsheep-checkout-optimization' );
                }
                if ( isset( $fields['shipping']['shipping_address_1'] ) ) {
                    $fields['shipping']['shipping_address_1']['label'] = __( '詳細地址', 'yangsheep-checkout-optimization' );
                }

                // 台灣布局 class
                // 姓名、電話：2 欄（使用預設 grid span 1）
                // 郵遞區號、縣市、鄉鎮市區：3 欄
                if ( isset( $fields['shipping']['shipping_postcode'] ) ) {
                    $fields['shipping']['shipping_postcode']['class'] = array( 'form-row', 'yangsheep-tw-third' );
                }
                if ( isset( $fields['shipping']['shipping_state'] ) ) {
                    $fields['shipping']['shipping_state']['class'] = array( 'form-row', 'yangsheep-tw-third' );
                }
                if ( isset( $fields['shipping']['shipping_city'] ) ) {
                    $fields['shipping']['shipping_city']['class'] = array( 'form-row', 'yangsheep-tw-third' );
                }
                // 地址：全寬
                if ( isset( $fields['shipping']['shipping_address_1'] ) ) {
                    $fields['shipping']['shipping_address_1']['class'] = array( 'form-row', 'yangsheep-tw-full' );
                }

                // 台灣隱藏 address_2
                if ( isset( $fields['shipping']['shipping_address_2'] ) ) {
                    unset( $fields['shipping']['shipping_address_2'] );
                }
            } else {
                // 其他國家：維持標準順序
                $shipping_priorities = array(
                    'shipping_first_name' => 5,
                    'shipping_last_name'  => 10,
                    'shipping_phone'      => 20,
                    'shipping_country'    => 30,
                    'shipping_address_1'  => 40,
                    'shipping_address_2'  => 50,
                    'shipping_city'       => 60,
                    'shipping_state'      => 70,
                    'shipping_postcode'   => 80,
                );
            }

            foreach ( $shipping_priorities as $key => $priority ) {
                if ( isset( $fields['shipping'][ $key ] ) ) {
                    $fields['shipping'][ $key ]['priority'] = $priority;
                }
            }
        }
        
        // 移除公司欄位（收件人不需要）
        if ( isset( $fields['shipping']['shipping_company'] ) ) {
            unset( $fields['shipping']['shipping_company'] );
        }

        // 確保 order_comments 欄位存在（某些外掛可能移除它）
        if ( ! isset( $fields['order'] ) || empty( $fields['order'] ) ) {
            $fields['order'] = array();
        }
        if ( ! isset( $fields['order']['order_comments'] ) ) {
            $fields['order']['order_comments'] = array(
                'type'        => 'textarea',
                'class'       => array( 'notes' ),
                'label'       => __( '訂單備註', 'woocommerce' ),
                'placeholder' => _x( '關於您的訂單的備註，例如：特別的配送須知', 'placeholder', 'woocommerce' ),
            );
        }

        return $fields;
    }

    /**
     * 自訂地址欄位（woocommerce_default_address_fields）
     * 這會影響所有地址欄位的基礎設定
     */
    public function customize_address_fields( $fields ) {
        // 只在啟用台灣化欄位時調整
        if ( get_option( 'yangsheep_checkout_tw_fields', 'no' ) !== 'yes' ) {
            return $fields;
        }

        // 檢測國家
        $country = WC()->customer ? WC()->customer->get_shipping_country() : '';
        if ( empty( $country ) ) {
            $country = WC()->countries->get_base_country();
        }

        // 台灣格式：郵遞區號 → 縣市 → 鄉鎮市區 → 地址
        if ( $country === 'TW' ) {
            if ( isset( $fields['postcode'] ) ) {
                $fields['postcode']['priority'] = 40;
            }
            if ( isset( $fields['state'] ) ) {
                $fields['state']['priority'] = 50;
            }
            if ( isset( $fields['city'] ) ) {
                $fields['city']['priority'] = 60;
            }
            if ( isset( $fields['address_1'] ) ) {
                $fields['address_1']['priority'] = 70;
            }
            // 隱藏 address_2
            if ( isset( $fields['address_2'] ) ) {
                $fields['address_2']['required'] = false;
                $fields['address_2']['hidden'] = true;
                $fields['address_2']['class'] = array( 'hidden' );
            }
        }

        return $fields;
    }

    /**
     * 自訂 Shipping Phone 欄位並移除公司欄位
     * 作用於結帳頁面和我的帳號編輯地址頁面
     */
    public function add_shipping_phone( $fields ) {
        // 加入收件人電話
        if ( ! isset( $fields['shipping_phone'] ) ) {
            $fields['shipping_phone'] = array(
                'label'       => __( '收件人電話', 'yangsheep-checkout-optimization' ),
                'required'    => false,
                'class'       => array( 'form-row-last' ),  // 改為 form-row-last 以便與姓名並排顯示
                'priority'    => 25,
                'type'        => 'tel',
                'validate'    => array( 'phone' ),
            );
        }
        
        // 移除公司欄位（結帳頁面和我的帳號都生效）
        if ( isset( $fields['shipping_company'] ) ) {
            unset( $fields['shipping_company'] );
        }
        
        return $fields;
    }

    /**
     * 加入客戶 Meta 欄位（我的帳號）
     */
    public function add_customer_meta_fields( $fields ) {
        // 加入 shipping_phone 到客戶資料
        if ( ! isset( $fields['shipping']['fields']['shipping_phone'] ) ) {
            $fields['shipping']['fields']['shipping_phone'] = array(
                'label'       => __( '收件人電話', 'yangsheep-checkout-optimization' ),
                'description' => '',
            );
        }
        
        // 如果關閉 Last Name（保留 First Name 作為「姓名」）
        // 使用新的 option 名稱: yangsheep_checkout_close_lname
        if ( get_option( 'yangsheep_checkout_close_lname', 'no' ) === 'yes' ) {
            // 隱藏 last_name
            if ( isset( $fields['billing']['fields']['billing_last_name'] ) ) {
                unset( $fields['billing']['fields']['billing_last_name'] );
            }
            if ( isset( $fields['shipping']['fields']['shipping_last_name'] ) ) {
                unset( $fields['shipping']['fields']['shipping_last_name'] );
            }
            // 修改 first_name 標籤為「姓名」
            if ( isset( $fields['billing']['fields']['billing_first_name'] ) ) {
                $fields['billing']['fields']['billing_first_name']['label'] = __( '姓名', 'yangsheep-checkout-optimization' );
            }
            if ( isset( $fields['shipping']['fields']['shipping_first_name'] ) ) {
                $fields['shipping']['fields']['shipping_first_name']['label'] = __( '收件人姓名', 'yangsheep-checkout-optimization' );
            }
        }
        
        return $fields;
    }
}

// 初始化
YANGSHEEP_Checkout_Fields::get_instance();
