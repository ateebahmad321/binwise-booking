<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class BWB_Shortcode {

    public static function init() {
        add_shortcode( 'binwise_booking', [ __CLASS__, 'render' ] );
    }

    public static function render( $atts ) {

        wp_enqueue_style(
            'bwb-style',
            BWB_URL . 'assets/css/booking.css',
            [],
            BWB_VERSION
        );

        wp_enqueue_script(
            'bwb-booking',
            BWB_URL . 'assets/js/booking.js',
            [ 'jquery' ],
            BWB_VERSION,
            true
        );

        wp_localize_script( 'bwb-booking', 'BWB', [
            'ajax_url'        => admin_url( 'admin-ajax.php' ),
            'nonce'           => wp_create_nonce( 'bwb_nonce' ),
            'checkout_url'    => wc_get_checkout_url(),
            'contact_phone'   => get_option( 'bwb_contact_phone', '587-405-7545' ),
            'bins'            => BWB_Products::get_bins(),
            'durations'       => BWB_Products::get_durations(),
            'times'           => BWB_Products::get_delivery_times(),
            'mattress_prices' => BWB_Products::get_mattress_prices(),
            'contents'        => BWB_Products::get_bin_contents(),
            'locations'       => BWB_Products::get_bin_locations(),
            'zones'           => BWB_Products::get_zones(),
            'town_zones'      => BWB_Products::get_town_zones(),
        ]);

        ob_start();
        include BWB_PATH . 'templates/booking-form.php';
        return ob_get_clean();
    }
}
