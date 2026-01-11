<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

class YANGSHEEP_Checkout_Customizer {

    private static $instance = null;

    public static function get_instance() {
        if ( is_null( self::$instance ) ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_color_picker' ) );
    }

    public function enqueue_color_picker( $hook_suffix ) {
        if ( $hook_suffix === 'toplevel_page_yangsheep_checkout_optimization' ) {
            wp_enqueue_style( 'wp-color-picker' );
            wp_enqueue_script( 'wp-color-picker' );
            wp_enqueue_script(
                'yangsheep-color-picker-init',
                YANGSHEEP_CHECKOUT_OPTIMIZATION_URL . 'assets/js/color-picker-init.js',
                array( 'jquery', 'wp-color-picker' ),
                YANGSHEEP_CHECKOUT_OPTIMIZATION_VERSION,
                true
            );
        }
    }
}
