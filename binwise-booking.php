<?php
/**
 * Plugin Name: Yellow Bins Digitize
 * Description: Multi-step bin rental booking form that feeds into WooCommerce checkout.
 * Version: 1.1.0
 * Author: Ateeb Azhar Digitize Media
 * Text Domain: binwise-booking
 * Requires Plugins: woocommerce
 */

if ( ! defined( 'ABSPATH' ) ) exit;

define( 'BWB_PATH',    plugin_dir_path( __FILE__ ) );
define( 'BWB_URL',     plugin_dir_url( __FILE__ ) );
define( 'BWB_VERSION', '1.1.0' );

// ── Debug logging helper ──────────────────────────────────────────────────────
if ( ! function_exists( 'bwb_log' ) ) {
    function bwb_log( $message, $data = null ) {
        if ( ! defined( 'WP_DEBUG' ) || ! WP_DEBUG ) return;
        $entry = '[BWB ' . current_time( 'Y-m-d H:i:s' ) . '] ' . $message;
        if ( $data !== null ) {
            $entry .= ' | ' . ( is_string( $data ) ? $data : wp_json_encode( $data ) );
        }
        error_log( $entry );
    }
}

require_once BWB_PATH . 'includes/class-bwb-install.php';
require_once BWB_PATH . 'includes/class-bwb-products.php';
require_once BWB_PATH . 'includes/class-bwb-ajax.php';
require_once BWB_PATH . 'includes/class-bwb-woocommerce.php';
require_once BWB_PATH . 'includes/class-bwb-admin.php';
require_once BWB_PATH . 'includes/class-bwb-shortcode.php';

register_activation_hook( __FILE__, [ 'BWB_Install', 'activate' ] );

add_action( 'plugins_loaded', function () {
    if ( ! class_exists( 'WooCommerce' ) ) {
        add_action( 'admin_notices', function () {
            echo '<div class="notice notice-error"><p><strong>BinWise Booking:</strong> WooCommerce must be installed and active.</p></div>';
        });
        return;
    }

    BWB_Install::ensure_table();
    BWB_Install::maybe_create_booking_product();

    BWB_Admin::init();
    BWB_Ajax::init();
    BWB_WooCommerce::init();
    BWB_Shortcode::init();
});
