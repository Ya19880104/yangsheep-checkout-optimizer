<?php
/**
 * 結帳欄位設置類別
 */

namespace YangSheep\CheckoutOptimizer\Checkout;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use YangSheep\CheckoutOptimizer\Settings\YSSettingsManager;

class YSCheckoutFields {

    private static $instance = null;

    public static function get_instance() {
        if ( is_null( self::$instance ) ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_filter( 'woocommerce_checkout_fields', array( $this, 'customize_checkout_fields' ), 100 );
        add_filter( 'woocommerce_default_address_fields', array( $this, 'customize_address_fields' ), 100 );
        add_filter( 'woocommerce_shipping_fields', array( $this, 'add_shipping_phone' ), 20 );
        add_filter( 'woocommerce_customer_meta_fields', array( $this, 'add_customer_meta_fields' ), 20 );
        add_filter( 'woocommerce_address_to_edit', array( $this, 'filter_address_to_edit' ), 20, 2 );
        add_filter( 'woocommerce_checkout_fields', array( $this, 'maybe_remove_address_required_for_cvs' ), 999 );
        add_filter( 'woocommerce_checkout_fields', array( $this, 'force_phone_fields' ), 9999 );
        add_filter( 'woocommerce_billing_fields', array( $this, 'force_billing_phone' ), 9999 );
        add_action( 'woocommerce_checkout_process', array( $this, 'validate_shipping_phone' ) );
    }

    public function maybe_remove_address_required_for_cvs( $fields ) {
        $is_cvs_shipping = $this->is_cvs_shipping_selected();
        if ( $is_cvs_shipping ) {
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

    private function is_cvs_shipping_selected() {
        // phpcs:disable WordPress.Security.NonceVerification.Missing
        if ( isset( $_POST['shipping_method'] ) ) {
            $shipping_methods = wc_clean( wp_unslash( $_POST['shipping_method'] ) );
        } else {
            $shipping_methods = WC()->session ? WC()->session->get( 'chosen_shipping_methods', array() ) : array();
        }
        // phpcs:enable WordPress.Security.NonceVerification.Missing

        if ( empty( $shipping_methods ) || ! is_array( $shipping_methods ) ) {
            return false;
        }

        $cvs_methods = YSSettingsManager::get( 'yangsheep_cvs_shipping_methods', array() );
        $use_manual_settings = ! empty( $cvs_methods ) && is_array( $cvs_methods );

        foreach ( $shipping_methods as $method ) {
            if ( empty( $method ) ) {
                continue;
            }
            if ( $use_manual_settings ) {
                foreach ( $cvs_methods as $cvs_method ) {
                    if ( $method === $cvs_method ) {
                        return true;
                    }
                    $method_base = strstr( $method, ':', true ) ?: $method;
                    $cvs_base = strstr( $cvs_method, ':', true ) ?: $cvs_method;
                    if ( $method_base === $cvs_base && strpos( $method, $cvs_method ) === 0 ) {
                        return true;
                    }
                }
            } else {
                $method_id = strstr( $method, ':', true ) ?: $method;
                if ( strpos( $method_id, 'payuni_' ) === 0 &&
                     ( strpos( $method_id, '711' ) !== false ||
                       strpos( $method_id, 'fami' ) !== false ||
                       strpos( $method_id, 'hilife' ) !== false ) ) {
                    return true;
                }
                if ( strpos( $method_id, 'ecpay' ) !== false && strpos( $method_id, 'cvs' ) !== false ) {
                    return true;
                }
                if ( strpos( $method_id, 'ys_paynow_shipping_' ) === 0 && strpos( $method_id, 'tcat' ) === false ) {
                    return true;
                }
            }
        }
        return false;
    }

    public function customize_checkout_fields( $fields ) {
        if ( YSSettingsManager::get( 'yangsheep_checkout_close_lname', 'no' ) === 'yes' ) {
            if ( isset( $fields['billing']['billing_last_name'] ) ) {
                unset( $fields['billing']['billing_last_name'] );
            }
            if ( isset( $fields['shipping']['shipping_last_name'] ) ) {
                unset( $fields['shipping']['shipping_last_name'] );
            }
            if ( isset( $fields['billing']['billing_first_name'] ) ) {
                $fields['billing']['billing_first_name']['label'] = __( '姓名', 'yangsheep-checkout-optimization' );
                $fields['billing']['billing_first_name']['class'] = array( 'form-row-wide' );
                $fields['billing']['billing_first_name']['priority'] = 10;
            }
            if ( isset( $fields['shipping']['shipping_first_name'] ) ) {
                $fields['shipping']['shipping_first_name']['label'] = __( '姓名', 'yangsheep-checkout-optimization' );
                $fields['shipping']['shipping_first_name']['class'] = array( 'form-row-first' );
                $fields['shipping']['shipping_first_name']['priority'] = 10;
            }
        }

        if ( YSSettingsManager::get( 'yangsheep_checkout_tw_fields', 'no' ) === 'yes' ) {
            $billing_keep = array( 'billing_first_name', 'billing_phone', 'billing_email' );
            if ( YSSettingsManager::get( 'yangsheep_checkout_close_lname', 'no' ) !== 'yes' ) {
                $billing_keep[] = 'billing_last_name';
            }
            foreach ( $fields['billing'] as $key => $field ) {
                if ( ! in_array( $key, $billing_keep, true ) ) {
                    unset( $fields['billing'][ $key ] );
                }
            }

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

            $shipping_country = WC()->customer ? WC()->customer->get_shipping_country() : '';
            if ( empty( $shipping_country ) ) {
                $shipping_country = WC()->countries->get_base_country();
            }

            if ( $shipping_country === 'TW' ) {
                $shipping_priorities = array(
                    'shipping_first_name' => 5,
                    'shipping_last_name'  => 10,
                    'shipping_phone'      => 20,
                    'shipping_country'    => 30,
                    'shipping_postcode'   => 40,
                    'shipping_state'      => 50,
                    'shipping_city'       => 60,
                    'shipping_address_1'  => 70,
                );

                if ( isset( $fields['shipping']['shipping_city'] ) ) {
                    $fields['shipping']['shipping_city']['label'] = __( '鄉鎮市區', 'yangsheep-checkout-optimization' );
                }
                if ( isset( $fields['shipping']['shipping_address_1'] ) ) {
                    $fields['shipping']['shipping_address_1']['label'] = __( '詳細地址', 'yangsheep-checkout-optimization' );
                }
                if ( isset( $fields['shipping']['shipping_postcode'] ) ) {
                    $fields['shipping']['shipping_postcode']['class'] = array( 'form-row', 'yangsheep-tw-third' );
                }
                if ( isset( $fields['shipping']['shipping_state'] ) ) {
                    $fields['shipping']['shipping_state']['class'] = array( 'form-row', 'yangsheep-tw-third' );
                }
                if ( isset( $fields['shipping']['shipping_city'] ) ) {
                    $fields['shipping']['shipping_city']['class'] = array( 'form-row', 'yangsheep-tw-third' );
                }
                if ( isset( $fields['shipping']['shipping_address_1'] ) ) {
                    $fields['shipping']['shipping_address_1']['class'] = array( 'form-row', 'yangsheep-tw-full' );
                }
                if ( isset( $fields['shipping']['shipping_address_2'] ) ) {
                    unset( $fields['shipping']['shipping_address_2'] );
                }
            } else {
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

        if ( isset( $fields['shipping']['shipping_company'] ) ) {
            unset( $fields['shipping']['shipping_company'] );
        }

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

    public function customize_address_fields( $fields ) {
        if ( YSSettingsManager::get( 'yangsheep_checkout_close_lname', 'no' ) === 'yes' ) {
            if ( isset( $fields['last_name'] ) ) {
                unset( $fields['last_name'] );
            }
            if ( isset( $fields['first_name'] ) ) {
                $fields['first_name']['label'] = __( '姓名', 'yangsheep-checkout-optimization' );
                $fields['first_name']['class'] = array( 'form-row-wide' );
            }
        }

        if ( YSSettingsManager::get( 'yangsheep_checkout_tw_fields', 'no' ) !== 'yes' ) {
            return $fields;
        }

        if ( isset( $fields['address_2'] ) ) {
            unset( $fields['address_2'] );
        }
        if ( isset( $fields['company'] ) ) {
            unset( $fields['company'] );
        }
        if ( isset( $fields['city'] ) ) {
            $fields['city']['label'] = __( '鄉鎮市區', 'yangsheep-checkout-optimization' );
        }
        if ( isset( $fields['address_1'] ) ) {
            $fields['address_1']['label'] = __( '詳細地址', 'yangsheep-checkout-optimization' );
        }

        $country = '';
        if ( WC()->customer ) {
            if ( is_wc_endpoint_url( 'edit-address' ) ) {
                global $wp;
                $address_type = isset( $wp->query_vars['edit-address'] ) ? $wp->query_vars['edit-address'] : 'billing';
                $country = $address_type === 'shipping'
                    ? WC()->customer->get_shipping_country()
                    : WC()->customer->get_billing_country();
            } else {
                $country = WC()->customer->get_shipping_country();
            }
        }
        if ( empty( $country ) ) {
            $country = WC()->countries->get_base_country();
        }

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
        }

        return $fields;
    }

    public function add_shipping_phone( $fields ) {
        if ( ! isset( $fields['shipping_phone'] ) ) {
            $fields['shipping_phone'] = array(
                'label'       => __( '收件人電話', 'yangsheep-checkout-optimization' ),
                'required'    => true,
                'class'       => array( 'form-row-last' ),
                'priority'    => 25,
                'type'        => 'tel',
                'validate'    => array( 'phone' ),
            );
        } else {
            $fields['shipping_phone']['required'] = true;
        }
        if ( isset( $fields['shipping_company'] ) ) {
            unset( $fields['shipping_company'] );
        }
        return $fields;
    }

    public function add_customer_meta_fields( $fields ) {
        if ( ! isset( $fields['shipping']['fields']['shipping_phone'] ) ) {
            $fields['shipping']['fields']['shipping_phone'] = array(
                'label'       => __( '收件人電話', 'yangsheep-checkout-optimization' ),
                'description' => '',
            );
        }
        if ( YSSettingsManager::get( 'yangsheep_checkout_close_lname', 'no' ) === 'yes' ) {
            if ( isset( $fields['billing']['fields']['billing_last_name'] ) ) {
                unset( $fields['billing']['fields']['billing_last_name'] );
            }
            if ( isset( $fields['shipping']['fields']['shipping_last_name'] ) ) {
                unset( $fields['shipping']['fields']['shipping_last_name'] );
            }
            if ( isset( $fields['billing']['fields']['billing_first_name'] ) ) {
                $fields['billing']['fields']['billing_first_name']['label'] = __( '姓名', 'yangsheep-checkout-optimization' );
            }
            if ( isset( $fields['shipping']['fields']['shipping_first_name'] ) ) {
                $fields['shipping']['fields']['shipping_first_name']['label'] = __( '收件人姓名', 'yangsheep-checkout-optimization' );
            }
        }
        if ( YSSettingsManager::get( 'yangsheep_checkout_tw_fields', 'no' ) === 'yes' ) {
            if ( isset( $fields['billing']['fields']['billing_company'] ) ) {
                unset( $fields['billing']['fields']['billing_company'] );
            }
            if ( isset( $fields['billing']['fields']['billing_address_2'] ) ) {
                unset( $fields['billing']['fields']['billing_address_2'] );
            }
            if ( isset( $fields['shipping']['fields']['shipping_company'] ) ) {
                unset( $fields['shipping']['fields']['shipping_company'] );
            }
            if ( isset( $fields['shipping']['fields']['shipping_address_2'] ) ) {
                unset( $fields['shipping']['fields']['shipping_address_2'] );
            }
            if ( isset( $fields['billing']['fields']['billing_city'] ) ) {
                $fields['billing']['fields']['billing_city']['label'] = __( '鄉鎮市區', 'yangsheep-checkout-optimization' );
            }
            if ( isset( $fields['billing']['fields']['billing_address_1'] ) ) {
                $fields['billing']['fields']['billing_address_1']['label'] = __( '詳細地址', 'yangsheep-checkout-optimization' );
            }
            if ( isset( $fields['shipping']['fields']['shipping_city'] ) ) {
                $fields['shipping']['fields']['shipping_city']['label'] = __( '鄉鎮市區', 'yangsheep-checkout-optimization' );
            }
            if ( isset( $fields['shipping']['fields']['shipping_address_1'] ) ) {
                $fields['shipping']['fields']['shipping_address_1']['label'] = __( '詳細地址', 'yangsheep-checkout-optimization' );
            }
        }
        return $fields;
    }

    public function filter_address_to_edit( $address, $load_address ) {
        if ( YSSettingsManager::get( 'yangsheep_checkout_close_lname', 'no' ) === 'yes' ) {
            $last_name_key = $load_address . '_last_name';
            $first_name_key = $load_address . '_first_name';
            if ( isset( $address[ $last_name_key ] ) ) {
                unset( $address[ $last_name_key ] );
            }
            if ( isset( $address[ $first_name_key ] ) ) {
                $address[ $first_name_key ]['label'] = __( '姓名', 'yangsheep-checkout-optimization' );
                $address[ $first_name_key ]['class'] = array( 'form-row-wide' );
            }
        }
        if ( YSSettingsManager::get( 'yangsheep_checkout_tw_fields', 'no' ) !== 'yes' ) {
            return $address;
        }
        if ( 'billing' === $load_address ) {
            $fields_to_remove = array( 'billing_company', 'billing_country', 'billing_postcode', 'billing_state', 'billing_city', 'billing_address_1', 'billing_address_2' );
            foreach ( $fields_to_remove as $field_key ) {
                if ( isset( $address[ $field_key ] ) ) {
                    unset( $address[ $field_key ] );
                }
            }
            if ( isset( $address['billing_phone'] ) ) {
                $address['billing_phone']['class'] = array( 'form-row-wide' );
            }
            return $address;
        }
        $company_key = $load_address . '_company';
        if ( isset( $address[ $company_key ] ) ) {
            unset( $address[ $company_key ] );
        }
        $address_2_key = $load_address . '_address_2';
        if ( isset( $address[ $address_2_key ] ) ) {
            unset( $address[ $address_2_key ] );
        }
        $phone_key = $load_address . '_phone';
        if ( isset( $address[ $phone_key ] ) ) {
            $address[ $phone_key ]['class'] = array( 'form-row-wide' );
        }
        $city_key = $load_address . '_city';
        if ( isset( $address[ $city_key ] ) ) {
            $address[ $city_key ]['label'] = __( '鄉鎮市區', 'yangsheep-checkout-optimization' );
        }
        $address_1_key = $load_address . '_address_1';
        if ( isset( $address[ $address_1_key ] ) ) {
            $address[ $address_1_key ]['label'] = __( '詳細地址', 'yangsheep-checkout-optimization' );
        }
        return $address;
    }

    public function force_phone_fields( $fields ) {
        if ( ! isset( $fields['billing']['billing_phone'] ) ) {
            $fields['billing']['billing_phone'] = array(
                'label' => __( '電話', 'woocommerce' ), 'required' => true, 'type' => 'tel',
                'class' => array( 'form-row-wide' ), 'validate' => array( 'phone' ), 'priority' => 100,
            );
        } else {
            $fields['billing']['billing_phone']['required'] = true;
        }
        if ( ! isset( $fields['shipping']['shipping_phone'] ) ) {
            $fields['shipping']['shipping_phone'] = array(
                'label' => __( '收件人電話', 'yangsheep-checkout-optimization' ), 'required' => true,
                'class' => array( 'form-row' ), 'priority' => 15, 'type' => 'tel', 'validate' => array( 'phone' ),
            );
        } else {
            $fields['shipping']['shipping_phone']['required'] = true;
            // 強制覆蓋 priority，防止第三方外掛（如 WPBR）改動排序
            $fields['shipping']['shipping_phone']['priority'] = 15;
            // 清除第三方外掛加入的錯誤 class（如 form-row-wide、wpbc-*）
            $classes = isset( $fields['shipping']['shipping_phone']['class'] ) ? $fields['shipping']['shipping_phone']['class'] : array();
            if ( is_array( $classes ) ) {
                $classes = array_filter( $classes, function( $class ) {
                    return ! in_array( $class, array( 'form-row-wide', 'form-row-first', 'form-row-last' ), true )
                        && strpos( $class, 'wpbc' ) === false
                        && strpos( $class, 'cvs' ) === false;
                });
            }
            $classes[] = 'form-row';
            $fields['shipping']['shipping_phone']['class'] = array_values( array_unique( $classes ) );
        }
        return $fields;
    }

    public function force_billing_phone( $fields ) {
        if ( ! isset( $fields['billing_phone'] ) ) {
            $fields['billing_phone'] = array(
                'label' => __( '電話', 'woocommerce' ), 'required' => true, 'type' => 'tel',
                'class' => array( 'form-row-wide' ), 'validate' => array( 'phone' ), 'priority' => 100,
            );
        } else {
            $fields['billing_phone']['required'] = true;
        }
        return $fields;
    }

    public function validate_shipping_phone() {
        // phpcs:disable WordPress.Security.NonceVerification.Missing
        $ship_to_different = isset( $_POST['ship_to_different_address'] ) ? wc_clean( wp_unslash( $_POST['ship_to_different_address'] ) ) : '';
        $shipping_phone    = isset( $_POST['shipping_phone'] ) ? wc_clean( wp_unslash( $_POST['shipping_phone'] ) ) : '';
        // phpcs:enable WordPress.Security.NonceVerification.Missing
        if ( empty( $ship_to_different ) || empty( $shipping_phone ) ) {
            return;
        }
        $phone_numeric = preg_replace( '/\D/', '', $shipping_phone );
        if ( ! preg_match( '/^09\d{8}$/', $phone_numeric ) ) {
            if ( substr( $phone_numeric, 0, 2 ) !== '09' ) {
                wc_add_notice( __( '收件人電話必須為 09 開頭的手機號碼', 'yangsheep-checkout-optimization' ), 'error' );
            } elseif ( strlen( $phone_numeric ) !== 10 ) {
                wc_add_notice( __( '收件人電話必須為 10 位數字', 'yangsheep-checkout-optimization' ), 'error' );
            } else {
                wc_add_notice( __( '請輸入有效的收件人手機號碼', 'yangsheep-checkout-optimization' ), 'error' );
            }
        }
    }
}
