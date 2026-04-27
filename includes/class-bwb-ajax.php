<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class BWB_Ajax {

    public static function init() {
        add_action( 'wp_ajax_bwb_add_to_cart',        [ __CLASS__, 'add_to_cart' ] );
        add_action( 'wp_ajax_nopriv_bwb_add_to_cart', [ __CLASS__, 'add_to_cart' ] );
    }

    public static function add_to_cart() {

        // ── Nonce check ───────────────────────────────────────────────
        // wp_verify_nonce() is more forgiving than check_ajax_referer()
        // for cold-traffic visitors (ad clicks) who have no prior session.
        $nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
        if ( ! wp_verify_nonce( $nonce, 'bwb_nonce' ) ) {
            wp_send_json_error( [
                'message' => 'Security check failed. Please refresh the page and try again.',
                'code'    => 'bad_nonce',
            ], 403 );
        }

        // ── Bootstrap WC session early (critical for nopriv / ad traffic) ──
        // Without this, WC()->cart can be null on the very first page visit.
        if ( ! WC()->session ) {
            WC()->initialize_session();
        }
        if ( ! WC()->session->has_session() ) {
            WC()->session->set_customer_session_cookie( true );
        }
        if ( ! WC()->cart ) {
            WC()->initialize_cart();
        }

        $data   = self::sanitize_booking( $_POST );
        $errors = self::validate_booking( $data );

        if ( ! empty( $errors ) ) {
            wp_send_json_error( [ 'message' => implode( '<br>', $errors ) ] );
        }

        $total               = BWB_Products::calculate_total( $data );
        $data['total_price'] = $total;

        // ── Ensure hidden product exists ──────────────────────────────
        $product_id = (int) get_option( 'bwb_product_id' );
        if ( ! $product_id || ! get_post( $product_id ) ) {
            BWB_Install::maybe_create_booking_product();
            $product_id = (int) get_option( 'bwb_product_id' );
        }
        if ( ! $product_id ) {
            wp_send_json_error( [ 'message' => 'Booking product not found. Please contact us.' ] );
        }

        WC()->cart->empty_cart();

        $cart_item_key = WC()->cart->add_to_cart(
            $product_id, 1, 0, [],
            [ 'bwb_booking' => $data ]
        );

        if ( ! $cart_item_key ) {
            wp_send_json_error( [ 'message' => 'Could not add booking to cart. Please try again.' ] );
        }

        // Store in session as backup
        WC()->session->set( 'bwb_booking', $data );

        wp_send_json_success( [
            'redirect'  => wc_get_checkout_url(),
            'total'     => $total,
            'total_fmt' => '$' . number_format( $total, 2 ),
        ] );
    }

    private static function sanitize_booking( $post ) {
        return [
            'bin_id'             => sanitize_key( $post['bin_id'] ?? '' ),
            'delivery_date'      => sanitize_text_field( $post['delivery_date'] ?? '' ),
            'duration'           => sanitize_key( $post['duration'] ?? '' ),
            'bin_contents'       => array_map( 'sanitize_key', (array) ( $post['bin_contents'] ?? [] ) ),
            'bin_contents_other' => sanitize_text_field( $post['bin_contents_other'] ?? '' ),
            'bin_location'       => sanitize_key( $post['bin_location'] ?? '' ),
            'bin_location_other' => sanitize_text_field( $post['bin_location_other'] ?? '' ),
            'address_line1'      => sanitize_text_field( $post['address_line1'] ?? '' ),
            'address_city'       => sanitize_text_field( $post['address_city'] ?? '' ),
            'address_province'   => sanitize_text_field( $post['address_province'] ?? 'AB' ),
            'address_postal'     => sanitize_text_field( $post['address_postal'] ?? '' ),
            'delivery_zone'      => sanitize_text_field( $post['delivery_zone'] ?? '' ),
            'zone_fee'           => floatval( $post['zone_fee'] ?? 0 ),
            'same_billing'       => sanitize_key( $post['same_billing'] ?? 'yes' ),
            'billing_line1'      => sanitize_text_field( $post['billing_line1'] ?? '' ),
            'billing_city'       => sanitize_text_field( $post['billing_city'] ?? '' ),
            'billing_province'   => sanitize_text_field( $post['billing_province'] ?? 'AB' ),
            'billing_postal'     => sanitize_text_field( $post['billing_postal'] ?? '' ),
            'additional_note'    => sanitize_textarea_field( $post['additional_note'] ?? '' ),
            'agreed_terms'       => ! empty( $post['agreed_terms'] ),
        ];
    }

    private static function validate_booking( $data ) {
        $errors = [];
        $bins   = BWB_Products::get_bins();

        if ( empty( $data['bin_id'] ) || ! isset( $bins[ $data['bin_id'] ] ) ) {
            $errors[] = 'Please select a bin size.';
        }

        if ( empty( $data['delivery_date'] ) ) {
            $errors[] = 'Please select a delivery date.';
        } else {
            $ts = strtotime( $data['delivery_date'] );
            if ( ! $ts || $ts < strtotime( 'today' ) ) {
                $errors[] = 'Delivery date must be in the future.';
            }
        }

        $bin = $bins[ $data['bin_id'] ] ?? null;
        if ( $bin && empty( $bin['no_duration'] ) && empty( $data['duration'] ) ) {
            $errors[] = 'Please select how many days you need the bin.';
        }

        if ( empty( $data['bin_location'] ) )  $errors[] = 'Please select where you want the bin placed.';
        if ( empty( $data['address_line1'] ) ) $errors[] = 'Please enter your street address.';
        if ( empty( $data['address_city'] ) )  $errors[] = 'Please enter your city.';
        if ( empty( $data['agreed_terms'] ) )  $errors[] = 'Please agree to the Rental Agreement.';

        return $errors;
    }
}