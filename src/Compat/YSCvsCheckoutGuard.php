<?php
/**
 * Validate the store data consumed by supported classic-checkout providers.
 *
 * This guard does not depend on checkout-field required flags: those may have
 * been cached before a shipping provider applies its checkout-process filters.
 */

namespace YangSheep\CheckoutOptimizer\Compat;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class YSCvsCheckoutGuard {
    private static $instance = null;

    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action( 'woocommerce_after_checkout_validation', array( $this, 'validate_checkout' ), PHP_INT_MAX, 2 );
    }

    /**
     * Export each recognized full rate's native store contract for the browser.
     *
     * Unknown/custom providers remain responsible for their own validation.
     * Values are derived from loaded provider classes; no merchant secrets or
     * selected-store/customer data are exported.
     *
     * @param array $shipping_methods Full chosen or available WooCommerce rate IDs.
     * @return array
     */
    public static function get_store_contracts( $shipping_methods ) {
        if ( ! is_array( $shipping_methods ) ) {
            return array();
        }

        $contracts = array();
        foreach ( $shipping_methods as $rate ) {
            if ( ! is_string( $rate ) || '' === trim( $rate ) ) {
                continue;
            }
            $rate = trim( $rate );
            $method = explode( ':', $rate, 2 )[0];
            $contract = self::ecpay_contract( $method );
            if ( null === $contract ) {
                $contract = self::paynow_contract( $method );
            }
            if ( null !== $contract ) {
                $contract['rate'] = $rate;
                $contracts[] = $contract;
            }
        }
        return $contracts;
    }

    /**
     * Check every CVS package, including a CVS + delivery mixed checkout.
     *
     * The providers have one store record per provider, rather than one record
     * per package. Incompatible brands sharing those fields cannot satisfy all
     * contracts and are rejected; compatible packages can share the same store.
     */
    public function validate_checkout( $data, $errors ) {
        if ( ! is_array( $data ) || ! $errors instanceof \WP_Error ) {
            return;
        }
        $wc = function_exists( 'WC' ) ? WC() : null;
        if ( $wc && $wc->cart && ! $wc->cart->needs_shipping() ) {
            return;
        }

        $methods = $data['shipping_method'] ?? array();
        foreach ( self::get_store_contracts( $methods ) as $contract ) {
            if ( 'ry_ecpay' === $contract['provider'] ) {
                $valid = self::valid_ry_session( $contract, $methods );
            } else {
                // Legacy ECPay and PayNow create-order callbacks consume $data.
                // Raw POST is not a safe fallback if a field editor removed a
                // field from WooCommerce's normalized/order-consumed fieldset.
                $valid = self::valid_posted_store( $contract, $data );
            }
            if ( ! $valid ) {
                $errors->add(
                    'yangsheep_cvs_store_required',
                    __( '尚未選擇超商取貨門市，請先選擇門市。', 'yangsheep-checkout-optimization' ),
                    array( 'id' => $contract['fields']['id'] )
                );
                return;
            }
        }
    }

    private static function ecpay_contract( $method ) {
        if ( 0 !== strpos( $method, 'ry_ecpay_shipping_cvs_' ) ) {
            return null;
        }

        $modern = class_exists( 'RY_WT_WC_ECPay_Shipping' );
        if ( $modern ) {
            $map = \RY_WT_WC_ECPay_Shipping::$support_methods;
        } elseif ( class_exists( 'RY_ECPay_Shipping' ) ) {
            $map = \RY_ECPay_Shipping::$support_methods;
        } else {
            return null;
        }
        $class = $map[ $method ] ?? null;
        if ( ! is_string( $class ) || ! class_exists( $class ) ) {
            return null;
        }

        if ( $modern ) {
            if ( ! defined( $class . '::Shipping_Sub_Type' ) || ! is_callable( array( 'RY\\WooCommerce\\Main', 'get_option' ) ) ) {
                return null;
            }
            $subtype = constant( $class . '::Shipping_Sub_Type' );
            $mode = \RY\WooCommerce\Main::get_option( 'ecpay_shipping_cvs_type', 'C2C' );
        } else {
            $properties = get_class_vars( $class );
            if ( ! isset( $properties['LogisticsSubType'] ) || ! is_callable( array( 'RY_WT', 'get_option' ) ) ) {
                return null;
            }
            $subtype = $properties['LogisticsSubType'];
            $mode = \RY_WT::get_option( 'ecpay_shipping_cvs_type', 'C2C' );
        }
        if ( ! is_string( $subtype ) || '' === $subtype ) {
            return null;
        }
        if ( 'C2C' === $mode ) {
            $subtype .= 'C2C';
        } elseif ( $modern && 'B2C' === $mode && 'UNIMART' === $subtype && self::ry_seven_frozen( $class ) ) {
            // The modern provider requests UNIMARTFREEZE for frozen B2C carts.
            $subtype .= 'FREEZE';
        }

        return array(
            'provider' => $modern ? 'ry_ecpay' : 'woomp_ecpay',
            'fields' => array(
                'id' => $modern ? 'RY_CVSStoreID' : 'CVSStoreID',
                'name' => $modern ? 'RY_CVSStoreName' : 'CVSStoreName',
                'address' => $modern ? 'RY_CVSAddress' : 'CVSAddress',
                'service' => $modern ? 'RY_LogisticsSubType' : 'LogisticsSubType',
            ),
            'expected' => $subtype,
            'extra_fields' => array(),
        );
    }

    /** Mirror the provider's B2C 7-ELEVEN temperature selection. */
    private static function ry_seven_frozen( $method_class ) {
        $wc = function_exists( 'WC' ) ? WC() : null;
        if ( ! $wc || ! $wc->cart || ! is_callable( array( $method_class, 'get_support_temp' ) ) ) {
            return false;
        }
        foreach ( $wc->cart->get_cart() as $item ) {
            $product = $item['data'] ?? null;
            if ( ! is_object( $product ) || ! is_callable( array( $product, 'get_meta' ) ) ) {
                continue;
            }
            $temperature = $product->get_meta( '_ry_shipping_temp', true );
            if ( empty( $temperature ) && 'variation' === $product->get_type() ) {
                $parent = wc_get_product( $product->get_parent_id() );
                $temperature = $parent ? $parent->get_meta( '_ry_shipping_temp', true ) : '';
            }
            if ( in_array( $temperature, $method_class::get_support_temp() ) && '3' === (string) $temperature ) {
                return true;
            }
        }
        return false;
    }

    private static function paynow_contract( $method ) {
        if ( ! class_exists( 'PayNow_Shipping' ) || ! is_callable( array( 'PayNow_Shipping_Logistic_Service', 'get_service_id' ) ) ) {
            return null;
        }
        // These are the native public PayNow/Woomp families observed in their
        // service helpers. A custom method with a similar name is not guessed.
        $known = array(
            'paynow_shipping_c2c_711', 'paynow_shipping_c2c_family', 'paynow_shipping_c2c_hilife',
            'paynow_shipping_c2c_711_frozen', 'paynow_shipping_c2c_family_frozen',
            'paynow_shipping_b2c_711', 'paynow_shipping_b2c_family',
            'paynow_shipping_b2c_711_frozen', 'paynow_shipping_b2c_family_frozen',
            'woomp_paynow_shipping_c2c_711_frozen', 'woomp_paynow_shipping_c2c_family_frozen',
        );
        if ( ! in_array( $method, $known, true ) ) {
            return null;
        }
        $service = \PayNow_Shipping_Logistic_Service::get_service_id( $method );
        if ( ! is_string( $service ) || '' === $service || '06' === $service ) {
            return null;
        }
        return array(
            'provider' => 'paynow',
            'fields' => array(
                'id' => 'paynow_storeid',
                'name' => 'paynow_storename',
                'address' => 'paynow_storeaddress',
                'service' => 'paynow_service',
            ),
            'expected' => $service,
            'extra_fields' => in_array( $service, array( '23', '24' ), true )
                ? array( 'paynow_reservedno', 'paynow_shipdate' ) : array(),
        );
    }

    private static function valid_posted_store( $contract, $data ) {
        foreach ( array_merge( array_values( $contract['fields'] ), $contract['extra_fields'] ) as $name ) {
            if ( null === self::nonblank_value( $data[ $name ] ?? null ) ) {
                return false;
            }
        }
        return $contract['expected'] === self::nonblank_value( $data[ $contract['fields']['service'] ] );
    }

    private static function valid_ry_session( $contract, $methods ) {
        $wc = function_exists( 'WC' ) ? WC() : null;
        if ( ! $wc || ! $wc->session ) {
            return false;
        }
        $store = $wc->session->get( 'ry_ecpay_cvs_info', array() );
        if ( ! is_array( $store ) ) {
            return false;
        }
        $selected = self::normalize_methods( $methods );
        if ( null === $selected
            || $selected !== self::normalize_methods( $wc->session->get( 'chosen_shipping_methods', array() ) )
            || $selected !== self::normalize_methods( $store['shipping_methods'] ?? null ) ) {
            return false;
        }
        foreach ( array( 'CVSStoreID', 'CVSStoreName', 'CVSAddress', 'LogisticsSubType' ) as $name ) {
            if ( null === self::nonblank_value( $store[ $name ] ?? null ) ) {
                return false;
            }
        }
        return $contract['expected'] === self::nonblank_value( $store['LogisticsSubType'] );
    }

    /** Preserve package keys: moving a store to a different package is stale. */
    private static function normalize_methods( $methods ) {
        if ( ! is_array( $methods ) || empty( $methods ) ) {
            return null;
        }
        foreach ( $methods as $key => $method ) {
            if ( ! is_string( $method ) || '' === trim( $method ) ) {
                return null;
            }
            $methods[ $key ] = trim( $method );
        }
        ksort( $methods );
        return $methods;
    }

    private static function nonblank_value( $value ) {
        if ( ! is_string( $value ) && ! is_int( $value ) ) {
            return null;
        }
        $value = trim( (string) $value );
        return '' !== $value && 1 === preg_match( '/[^\s\p{Z}\x{200B}\x{FEFF}]/u', $value ) ? $value : null;
    }
}
