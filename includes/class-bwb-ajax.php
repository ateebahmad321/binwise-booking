<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class BWB_Ajax {

    public static function init() {
        add_action( 'wp_ajax_bwb_add_to_cart',        [ __CLASS__, 'add_to_cart' ] );
        add_action( 'wp_ajax_nopriv_bwb_add_to_cart', [ __CLASS__, 'add_to_cart' ] );

        // Heartbeat endpoint to refresh nonces for long-idle sessions
        add_action( 'wp_ajax_bwb_refresh_nonce',        [ __CLASS__, 'refresh_nonce' ] );
        add_action( 'wp_ajax_nopriv_bwb_refresh_nonce', [ __CLASS__, 'refresh_nonce' ] );
    }

    /* ══════════════════════════════════════════════════════════════
       NONCE REFRESH — called by JS when a nonce expires
    ══════════════════════════════════════════════════════════════ */

    public static function refresh_nonce() {
        // Always return 200 — no nonce needed to get a new nonce
        wp_send_json_success( [ 'nonce' => wp_create_nonce( 'bwb_nonce' ) ] );
    }

    /* ══════════════════════════════════════════════════════════════
       ADD TO CART
    ══════════════════════════════════════════════════════════════ */

    public static function add_to_cart() {

        bwb_log( 'add_to_cart called', [
            'request_method' => $_SERVER['REQUEST_METHOD'] ?? 'unknown',
            'has_post_data'  => ! empty( $_POST ),
            'user_agent'     => substr( $_SERVER['HTTP_USER_AGENT'] ?? '', 0, 80 ),
        ] );

        // ── 1. Bootstrap WC session BEFORE nonce check ────────────────────────
        // Cold-traffic visitors (ad clicks, direct links) have no prior session.
        // WC nonces rely on the session cookie; without initialising first the
        // nonce check will always fail for first-time visitors.
        try {
            if ( ! WC()->session ) {
                WC()->initialize_session();
                bwb_log( 'WC session initialised' );
            }
            if ( ! WC()->session->has_session() ) {
                WC()->session->set_customer_session_cookie( true );
                bwb_log( 'WC session cookie set' );
            }
            if ( ! WC()->cart ) {
                WC()->initialize_cart();
                bwb_log( 'WC cart initialised' );
            }
        } catch ( Exception $e ) {
            bwb_log( 'WC session init exception', $e->getMessage() );
            // Non-fatal — continue; WC might still work
        }

        // ── 2. Nonce check ────────────────────────────────────────────────────
        // Return HTTP 200 with error JSON so jQuery's success: callback fires,
        // NOT the error: callback. A 403/500 status code is what causes
        // "Network error. Please try again." in the browser.
        $nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';

        if ( empty( $nonce ) ) {
            bwb_log( 'Nonce missing entirely' );
            wp_send_json_success( [   // intentional success HTTP so JS can show the message
                '__bwb_error' => true,
                'message'     => 'Security token missing. Please refresh the page and try again.',
                'code'        => 'nonce_missing',
            ] );
        }

        if ( ! wp_verify_nonce( $nonce, 'bwb_nonce' ) ) {
            bwb_log( 'Nonce invalid', [ 'nonce_received' => substr( $nonce, 0, 8 ) . '…' ] );

            // Try to generate a fresh nonce so JS can auto-retry
            $fresh_nonce = wp_create_nonce( 'bwb_nonce' );

            wp_send_json_success( [
                '__bwb_error'   => true,
                'message'       => 'Your session expired. The page will refresh automatically — please try again.',
                'code'          => 'nonce_expired',
                'fresh_nonce'   => $fresh_nonce,
            ] );
        }

        bwb_log( 'Nonce verified OK' );

        // ── 3. Sanitise & validate ────────────────────────────────────────────
        $data   = self::sanitize_booking( $_POST );
        $errors = self::validate_booking( $data );

        bwb_log( 'Booking data sanitised', [
            'bin_id'        => $data['bin_id'],
            'delivery_date' => $data['delivery_date'],
            'duration'      => $data['duration'],
            'zone'          => $data['delivery_zone'],
            'address_city'  => $data['address_city'],
        ] );

        if ( ! empty( $errors ) ) {
            bwb_log( 'Validation errors', $errors );
            wp_send_json_error( [ 'message' => implode( '<br>', $errors ) ] );
        }

        // ── 4. Calculate total ────────────────────────────────────────────────
        $total               = BWB_Products::calculate_total( $data );
        $data['total_price'] = $total;
        bwb_log( 'Calculated total', $total );

        // ── 5. Ensure hidden WC product exists ────────────────────────────────
        $product_id = (int) get_option( 'bwb_product_id' );
        if ( ! $product_id || ! get_post( $product_id ) ) {
            bwb_log( 'Hidden product missing — recreating' );
            BWB_Install::maybe_create_booking_product();
            $product_id = (int) get_option( 'bwb_product_id' );
        }
        if ( ! $product_id ) {
            bwb_log( 'ERROR: could not create hidden product' );
            wp_send_json_error( [ 'message' => 'Booking product not found. Please contact us directly.' ] );
        }

        bwb_log( 'Using product ID', $product_id );

        // ── 6. Clear any stale cart items, add new one ────────────────────────
        try {
            WC()->cart->empty_cart();

            $cart_item_key = WC()->cart->add_to_cart(
                $product_id, 1, 0, [],
                [ 'bwb_booking' => $data ]
            );
        } catch ( Exception $e ) {
            bwb_log( 'Cart exception', $e->getMessage() );
            wp_send_json_error( [ 'message' => 'Could not add booking to cart: ' . $e->getMessage() ] );
        }

        if ( ! $cart_item_key ) {
            $wc_notices = wc_get_notices( 'error' );
            $notice_msg = ! empty( $wc_notices ) ? wp_strip_all_tags( $wc_notices[0]['notice'] ) : 'Unknown WooCommerce error';
            bwb_log( 'add_to_cart returned false', $notice_msg );
            wp_send_json_error( [ 'message' => 'Could not add booking to cart. Please try again. (' . $notice_msg . ')' ] );
        }

        bwb_log( 'Cart item added', $cart_item_key );

        // ── 7. Store in session as backup for order pipeline ──────────────────
        WC()->session->set( 'bwb_booking', $data );

        // ── 8. Respond ────────────────────────────────────────────────────────
        bwb_log( 'Sending success response, redirecting to checkout' );

        wp_send_json_success( [
            'redirect'  => wc_get_checkout_url(),
            'total'     => $total,
            'total_fmt' => '$' . number_format( $total, 2 ),
        ] );
    }

    /* ══════════════════════════════════════════════════════════════
       SANITISE
    ══════════════════════════════════════════════════════════════ */

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

    /* ══════════════════════════════════════════════════════════════
       VALIDATE
    ══════════════════════════════════════════════════════════════ */

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
                $errors[] = 'Delivery date must be today or in the future.';
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
