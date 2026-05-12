<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class BWB_WooCommerce {

    /** @var array|null In-memory booking cache for the current PHP request */
    private static $booking_cache = null;

    /* ══════════════════════════════════════════════════════════════
       BOOT
    ══════════════════════════════════════════════════════════════ */

    public static function init() {

        // ── Cart price override ────────────────────────────────────
        add_action( 'woocommerce_before_calculate_totals',
            [ __CLASS__, 'set_cart_item_price' ], 20, 1 );

        // Keep bwb_booking data alive across page loads from the session
        add_filter( 'woocommerce_get_cart_item_from_session',
            [ __CLASS__, 'restore_cart_item_from_session' ], 10, 2 );

        // Show booking details in cart / mini-cart
        add_filter( 'woocommerce_get_item_data',
            [ __CLASS__, 'display_cart_item_meta' ], 10, 2 );

        // ── CRITICAL: persist price on the order line item ─────────
        // Without this the order stores $0 even though the cart showed
        // the correct price, because WC re-reads the product's stored
        // price (which is 0) when creating the order line item.
        add_action( 'woocommerce_checkout_create_order_line_item',
            [ __CLASS__, 'set_line_item_price' ], 10, 4 );

        // ── Booking data pipeline ──────────────────────────────────
        add_action( 'woocommerce_checkout_create_order_line_item',
            [ __CLASS__, 'cache_booking_from_line_item' ], 10, 4 );

        add_action( 'woocommerce_checkout_order_processed',
            [ __CLASS__, 'save_on_order_processed' ], 20, 3 );

        add_action( 'woocommerce_thankyou',
            [ __CLASS__, 'save_on_thankyou' ], 10, 1 );

        // ── Status sync ────────────────────────────────────────────
        add_action( 'woocommerce_payment_complete',
            [ __CLASS__, 'on_payment_complete' ], 10, 1 );

        add_action( 'woocommerce_order_status_changed',
            [ __CLASS__, 'sync_status' ], 10, 3 );

        // ── Display ────────────────────────────────────────────────
        add_action( 'woocommerce_order_details_after_order_table',
            [ __CLASS__, 'display_order_booking' ], 10, 1 );

        add_action( 'woocommerce_email_after_order_table',
            [ __CLASS__, 'display_order_booking' ], 10, 1 );

        add_action( 'woocommerce_admin_order_data_after_shipping_address',
            [ __CLASS__, 'admin_order_details' ], 10, 1 );

        // ── Checkout pre-fill ──────────────────────────────────────
        add_filter( 'woocommerce_checkout_get_value',
            [ __CLASS__, 'prefill_checkout_fields' ], 10, 2 );
    }

    /* ══════════════════════════════════════════════════════════════
       CART PRICE OVERRIDE
       Called on every cart/checkout totals recalc.
    ══════════════════════════════════════════════════════════════ */

    public static function set_cart_item_price( $cart ) {
        if ( is_admin() && ! defined( 'DOING_AJAX' ) ) return;
        if ( did_action( 'woocommerce_before_calculate_totals' ) >= 2 ) return;

        foreach ( $cart->get_cart() as $item ) {
            if ( isset( $item['bwb_booking'] ) && isset( $item['data'] ) ) {
                $price = floatval( $item['bwb_booking']['total_price'] ?? 0 );
                $item['data']->set_price( $price );
                bwb_log( 'Cart price set', $price );
            }
        }
    }

    /** Keep bwb_booking alive when WC rebuilds the cart from the session cookie */
    public static function restore_cart_item_from_session( $cart_item, $session_values ) {
        if ( ! empty( $session_values['bwb_booking'] ) ) {
            $cart_item['bwb_booking'] = $session_values['bwb_booking'];
        }
        return $cart_item;
    }

    public static function display_cart_item_meta( $item_data, $cart_item ) {
        if ( empty( $cart_item['bwb_booking'] ) ) return $item_data;
        $b    = $cart_item['bwb_booking'];
        $bins = BWB_Products::get_bins();
        $bin  = $bins[ $b['bin_id'] ?? '' ] ?? null;

        if ( $bin )
            $item_data[] = [ 'name' => 'Bin',           'value' => $bin['name'] ];
        if ( ! empty( $b['delivery_date'] ) )
            $item_data[] = [ 'name' => 'Delivery Date', 'value' => date( 'F j, Y', strtotime( $b['delivery_date'] ) ) ];
        if ( ! empty( $b['address_line1'] ) )
            $item_data[] = [ 'name' => 'Address',       'value' => $b['address_line1'] . ', ' . ( $b['address_city'] ?? '' ) ];
        if ( ! empty( $b['delivery_zone'] ) )
            $item_data[] = [ 'name' => 'Zone',          'value' => $b['delivery_zone'] ];

        return $item_data;
    }

    /* ══════════════════════════════════════════════════════════════
       FIX: SET LINE ITEM PRICE ON ORDER
       This is the critical fix for "cards saved but not charged".
       WC reads the product price (0) when building order line items;
       we override it here with the real booking total.
    ══════════════════════════════════════════════════════════════ */

    public static function set_line_item_price( $item, $cart_item_key, $values, $order ) {
        if ( empty( $values['bwb_booking'] ) ) return;

        $price = floatval( $values['bwb_booking']['total_price'] ?? 0 );

        if ( $price <= 0 ) {
            bwb_log( 'WARNING: line item price is 0 — booking total_price missing?', $values['bwb_booking'] );
            return;
        }

        // Override the unit price and subtotals on the line item
        $item->set_subtotal( $price );
        $item->set_total( $price );

        bwb_log( 'Line item price set on order', [
            'price'    => $price,
            'order_id' => $order ? $order->get_id() : 'unknown',
        ] );
    }

    /* ══════════════════════════════════════════════════════════════
       BOOKING DATA PIPELINE — STEP A
       Cache booking data and write to order meta during checkout.
    ══════════════════════════════════════════════════════════════ */

    public static function cache_booking_from_line_item( $item, $cart_item_key, $values, $order ) {
        if ( empty( $values['bwb_booking'] ) ) return;

        self::$booking_cache = $values['bwb_booking'];
        $order->update_meta_data( '_bwb_booking', $values['bwb_booking'] );

        bwb_log( 'Booking cached from line item for order', $order ? $order->get_id() : 'unknown' );
    }

    /* ══════════════════════════════════════════════════════════════
       BOOKING DATA PIPELINE — STEP B
    ══════════════════════════════════════════════════════════════ */

    public static function save_on_order_processed( $order_id, $posted_data, $order ) {
        if ( ! $order_id ) return;
        if ( self::already_saved( $order_id ) ) {
            bwb_log( 'save_on_order_processed: already saved for order', $order_id );
            return;
        }

        $booking = self::resolve_booking( $order );

        if ( empty( $booking ) ) {
            bwb_log( 'save_on_order_processed: no booking data for order', $order_id );
            return;
        }

        self::write_to_db( $order_id, $booking, $order );
    }

    /* ══════════════════════════════════════════════════════════════
       BOOKING DATA PIPELINE — STEP C (fallback)
    ══════════════════════════════════════════════════════════════ */

    public static function save_on_thankyou( $order_id ) {
        if ( ! $order_id ) return;
        if ( self::already_saved( $order_id ) ) return;

        $order = wc_get_order( $order_id );
        if ( ! $order ) return;

        $booking = self::resolve_booking( $order );
        if ( empty( $booking ) ) {
            bwb_log( 'save_on_thankyou: no booking data for order', $order_id );
            return;
        }

        self::write_to_db( $order_id, $booking, $order );
    }

    /* ══════════════════════════════════════════════════════════════
       BOOKING RESOLUTION — 4 layers, most reliable first
    ══════════════════════════════════════════════════════════════ */

    private static function resolve_booking( $order ) {

        // 1. In-memory static cache (same PHP request as checkout)
        if ( ! empty( self::$booking_cache ) && is_array( self::$booking_cache ) ) {
            bwb_log( 'resolve_booking: using in-memory cache' );
            return self::$booking_cache;
        }

        // 2. Order meta (written in cache_booking_from_line_item)
        $booking = $order->get_meta( '_bwb_booking', true );
        if ( ! empty( $booking ) && is_array( $booking ) ) {
            bwb_log( 'resolve_booking: using order meta' );
            return $booking;
        }

        // 3. WC session
        if ( WC()->session ) {
            $booking = WC()->session->get( 'bwb_booking' );
            if ( ! empty( $booking ) && is_array( $booking ) ) {
                bwb_log( 'resolve_booking: using WC session' );
                return $booking;
            }
        }

        // 4. Live cart items
        if ( WC()->cart ) {
            foreach ( WC()->cart->get_cart() as $ci ) {
                if ( ! empty( $ci['bwb_booking'] ) && is_array( $ci['bwb_booking'] ) ) {
                    bwb_log( 'resolve_booking: using live cart item' );
                    return $ci['bwb_booking'];
                }
            }
        }

        bwb_log( 'resolve_booking: no booking data found anywhere' );
        return null;
    }

    /* ══════════════════════════════════════════════════════════════
       DATABASE WRITE
    ══════════════════════════════════════════════════════════════ */

    public static function write_to_db( $order_id, array $booking, $order = null ) {
        if ( self::already_saved( $order_id ) ) {
            bwb_log( 'write_to_db: skipping, already saved', $order_id );
            return;
        }

        BWB_Install::ensure_table();

        if ( ! $order ) {
            $order = wc_get_order( $order_id );
        }

        global $wpdb;
        $table = $wpdb->prefix . 'bwb_bookings';
        $bins  = BWB_Products::get_bins();
        $bin   = $bins[ $booking['bin_id'] ?? '' ] ?? null;

        $contents = implode( ', ', array_filter( (array) ( $booking['bin_contents'] ?? [] ) ) );
        if ( ! empty( $booking['bin_contents_other'] ) ) {
            $contents .= ( $contents ? ', ' : '' ) . $booking['bin_contents_other'];
        }

        $delivery_date = $booking['delivery_date'] ?? '';
        if ( empty( $delivery_date ) || ! strtotime( $delivery_date ) ) {
            $delivery_date = current_time( 'Y-m-d' );
        }

        $data = [
            'order_id'         => (int) $order_id,
            'bin_size'         => $booking['bin_id']           ?? '',
            'bin_label'        => $bin ? $bin['name']          : ( $booking['bin_id'] ?? '' ),
            'delivery_date'    => $delivery_date,
            'duration'         => $booking['duration']         ?? '',
            'delivery_time'    => '',
            'driveway_pads'    => 0,
            'mattresses'       => 0,
            'mattress_qty'     => 0,
            'bin_contents'     => $contents,
            'bin_location'     => $booking['bin_location']     ?? '',
            'cancellation'     => 0,
            'address_line1'    => $booking['address_line1']    ?? '',
            'address_city'     => $booking['address_city']     ?? '',
            'address_province' => $booking['address_province'] ?? '',
            'address_postal'   => $booking['address_postal']   ?? '',
            'delivery_zone'    => $booking['delivery_zone']    ?? '',
            'zone_fee'         => floatval( $booking['zone_fee']    ?? 0 ),
            'base_price'       => floatval( $bin['price']           ?? 0 ),
            'total_price'      => floatval( $booking['total_price'] ?? 0 ),
            'customer_name'    => $order ? $order->get_formatted_billing_full_name() : '',
            'customer_email'   => $order ? $order->get_billing_email()               : '',
            'customer_phone'   => $order ? $order->get_billing_phone()               : '',
            'additional_note'  => $booking['additional_note']  ?? '',
            'status'           => 'pending',
        ];

        $formats = [
            '%d','%s','%s','%s','%s','%s',
            '%d','%d','%d','%s','%s','%d',
            '%s','%s','%s','%s','%s',
            '%f','%f','%f',
            '%s','%s','%s','%s','%s',
        ];

        $result = $wpdb->insert( $table, $data, $formats );

        if ( $result === false ) {
            bwb_log( 'DB insert FAILED for order #' . $order_id, $wpdb->last_error );
        } else {
            $row_id = $wpdb->insert_id;
            bwb_log( 'Booking saved to DB row #' . $row_id . ' for order #' . $order_id );

            update_post_meta( $order_id, '_bwb_db_saved',  1 );
            update_post_meta( $order_id, '_bwb_db_row_id', $row_id );
            update_post_meta( $order_id, '_bwb_booking',   $booking );

            if ( $order ) {
                $order->update_meta_data( '_bwb_booking',  $booking );
                $order->update_meta_data( '_bwb_db_saved', 1 );
                $order->save();
            }

            self::$booking_cache = null;
            if ( WC()->session ) {
                WC()->session->set( 'bwb_booking', null );
            }
        }
    }

    private static function already_saved( $order_id ) {
        return (bool) get_post_meta( $order_id, '_bwb_db_saved', true );
    }

    /* ══════════════════════════════════════════════════════════════
       STATUS SYNC
    ══════════════════════════════════════════════════════════════ */

    public static function on_payment_complete( $order_id ) {
        bwb_log( 'Payment complete for order', $order_id );
        self::set_db_status( $order_id, 'confirmed' );
    }

    public static function sync_status( $order_id, $old_status, $new_status ) {
        $map = [
            'completed'  => 'confirmed',
            'processing' => 'confirmed',
            'on-hold'    => 'pending',
            'pending'    => 'pending',
            'cancelled'  => 'cancelled',
            'refunded'   => 'cancelled',
            'failed'     => 'cancelled',
        ];
        if ( isset( $map[ $new_status ] ) ) {
            bwb_log( "Status sync order #{$order_id}: {$old_status} → {$new_status} → {$map[$new_status]}" );
            self::set_db_status( $order_id, $map[ $new_status ] );
        }
    }

    private static function set_db_status( $order_id, $status ) {
        global $wpdb;
        $wpdb->update(
            $wpdb->prefix . 'bwb_bookings',
            [ 'status'   => sanitize_key( $status ) ],
            [ 'order_id' => (int) $order_id ],
            [ '%s' ],
            [ '%d' ]
        );
    }

    /* ══════════════════════════════════════════════════════════════
       DISPLAY — order confirmation, emails, admin
    ══════════════════════════════════════════════════════════════ */

    public static function display_order_booking( $order ) {
        $booking = $order->get_meta( '_bwb_booking', true );
        if ( empty( $booking ) ) {
            $booking = get_post_meta( $order->get_id(), '_bwb_booking', true );
        }
        if ( empty( $booking ) || ! is_array( $booking ) ) return;

        $rows         = self::build_display_rows( $booking );
        $pricing_rows = self::build_pricing_rows( $booking );
        if ( empty( $rows ) ) return;

        echo '<h2 style="margin-top:32px;margin-bottom:12px;font-size:1.1rem;font-weight:700;">Bin Rental Details</h2>';
        echo '<table style="width:100%;border-collapse:collapse;font-size:14px;">';
        $i = 0;
        foreach ( $rows as $label => $value ) {
            $bg = ( $i % 2 === 0 ) ? '#fafafa' : '#ffffff';
            echo '<tr style="background:' . $bg . ';">';
            echo '<th style="text-align:left;padding:9px 16px 9px 12px;border-bottom:1px solid #e6e6e6;width:220px;font-weight:600;color:#444;vertical-align:top;">' . esc_html( $label ) . '</th>';
            echo '<td style="padding:9px 12px;border-bottom:1px solid #e6e6e6;color:#222;vertical-align:top;">' . esc_html( $value ) . '</td>';
            echo '</tr>';
            $i++;
        }
        echo '</table>';

        if ( ! empty( $pricing_rows ) ) {
            echo '<h2 style="margin-top:28px;margin-bottom:12px;font-size:1.1rem;font-weight:700;">Pricing Breakdown</h2>';
            echo '<table style="width:100%;border-collapse:collapse;font-size:14px;">';
            $i = 0;
            foreach ( $pricing_rows as $label => $value ) {
                $is_total = ( $label === 'Order Total' );
                $bg       = $is_total ? '#fffbea' : ( $i % 2 === 0 ? '#fafafa' : '#ffffff' );
                $fw_label = $is_total ? '700' : '600';
                $fw_value = $is_total ? '800' : '400';
                $border   = $is_total ? '2px solid #FCCC0A' : '1px solid #e6e6e6';
                echo '<tr style="background:' . $bg . ';">';
                echo '<th style="text-align:left;padding:9px 16px 9px 12px;border-bottom:' . $border . ';width:220px;font-weight:' . $fw_label . ';color:#444;vertical-align:top;">' . esc_html( $label ) . '</th>';
                echo '<td style="padding:9px 12px;border-bottom:' . $border . ';color:#222;font-weight:' . $fw_value . ';vertical-align:top;text-align:right;">' . esc_html( $value ) . '</td>';
                echo '</tr>';
                $i++;
            }
            echo '</table>';
        }
    }

    public static function admin_order_details( $order ) {
        $booking = get_post_meta( $order->get_id(), '_bwb_booking', true );
        if ( empty( $booking ) || ! is_array( $booking ) ) return;

        $rows         = self::build_display_rows( $booking );
        $pricing_rows = self::build_pricing_rows( $booking );
        if ( empty( $rows ) ) return;

        echo '<div class="address" style="margin-top:20px;">';
        echo '<p><strong>📦 Bin Rental Details</strong></p>';
        foreach ( $rows as $label => $value ) {
            echo '<p style="margin:4px 0;"><strong>' . esc_html( $label ) . ':</strong> ' . esc_html( $value ) . '</p>';
        }
        if ( ! empty( $pricing_rows ) ) {
            echo '<p style="margin-top:12px;margin-bottom:4px;"><strong>💰 Pricing Breakdown</strong></p>';
            foreach ( $pricing_rows as $label => $value ) {
                $is_total = ( $label === 'Order Total' );
                $style    = $is_total ? 'margin:6px 0;padding:4px 0;border-top:2px solid #FCCC0A;font-size:1.05em;' : 'margin:4px 0;';
                echo '<p style="' . $style . '"><strong>' . esc_html( $label ) . ':</strong> ' . esc_html( $value ) . '</p>';
            }
        }
        echo '</div>';
    }

    private static function build_display_rows( array $booking ) {
        $bins      = BWB_Products::get_bins();
        $bin       = $bins[ $booking['bin_id'] ?? '' ] ?? null;
        $durations = BWB_Products::get_durations();
        $rows      = [];

        $rows['Bin'] = $bin ? $bin['name'] : ( $booking['bin_id'] ?? '—' );

        if ( ! empty( $booking['delivery_date'] ) && strtotime( $booking['delivery_date'] ) ) {
            $rows['Delivery Date'] = date( 'F j, Y', strtotime( $booking['delivery_date'] ) );
        }

        if ( ! empty( $booking['duration'] ) && $booking['duration'] !== 'flat' ) {
            $dur = $durations[ $booking['duration'] ] ?? null;
            if ( $dur ) $rows['Rental Duration'] = $dur['label'];
        }

        $addr = implode( ', ', array_filter( [
            $booking['address_line1']    ?? '',
            $booking['address_city']     ?? '',
            $booking['address_province'] ?? '',
            $booking['address_postal']   ?? '',
        ] ) );
        if ( $addr ) $rows['Delivery Address'] = $addr;

        if ( ! empty( $booking['delivery_zone'] ) ) {
            $rows['Delivery Zone'] = $booking['delivery_zone'];
        }

        $locs = BWB_Products::get_bin_locations();
        if ( ! empty( $booking['bin_location'] ) ) {
            $loc = $locs[ $booking['bin_location'] ] ?? $booking['bin_location'];
            if ( $booking['bin_location'] === 'other' && ! empty( $booking['bin_location_other'] ) ) {
                $loc = $booking['bin_location_other'];
            }
            $rows['Bin Placement'] = $loc;
        }

        $content_labels = BWB_Products::get_bin_contents();
        $contents = array_map(
            function ( $k ) use ( $content_labels ) { return $content_labels[ $k ] ?? $k; },
            (array) ( $booking['bin_contents'] ?? [] )
        );
        if ( ! empty( $booking['bin_contents_other'] ) ) $contents[] = $booking['bin_contents_other'];
        $rows['Bin Contents'] = ! empty( $contents ) ? implode( ', ', $contents ) : 'Not specified';

        if ( ! empty( $booking['additional_note'] ) ) {
            $rows['Additional Notes'] = $booking['additional_note'];
        }

        return $rows;
    }

    private static function build_pricing_rows( array $booking ) {
        $bins      = BWB_Products::get_bins();
        $bin       = $bins[ $booking['bin_id'] ?? '' ] ?? null;
        $durations = BWB_Products::get_durations();
        if ( ! $bin ) return [];

        $rows    = [];
        $running = 0.0;

        $base = floatval( $bin['price'] );
        $rows[ $bin['name'] . ' (base price)' ] = '$' . number_format( $base, 2 );
        $running += $base;

        if ( ! empty( $booking['duration'] ) && $booking['duration'] !== 'flat' ) {
            $dur = $durations[ $booking['duration'] ] ?? null;
            if ( $dur ) {
                if ( $dur['price'] > 0 ) {
                    $rows[ 'Rental Duration — ' . $dur['label'] ] = '+$' . number_format( $dur['price'], 2 );
                    $running += $dur['price'];
                } else {
                    $rows[ 'Rental Duration — ' . $dur['label'] ] = 'Included';
                }
            }
        }

        $zone_fee = floatval( $booking['zone_fee'] ?? 0 );
        $zone     = $booking['delivery_zone'] ?? '';
        if ( $zone_fee > 0 ) {
            $rows[ 'Delivery Zone Fee — ' . $zone ] = '+$' . number_format( $zone_fee, 2 );
            $running += $zone_fee;
        } else {
            $rows[ 'Delivery Zone — ' . ( $zone ?: 'Standard' ) ] = 'Included';
        }

        $stored_total       = floatval( $booking['total_price'] ?? 0 );
        $grand_total        = $stored_total > 0 ? $stored_total : $running;
        $rows['Order Total'] = '$' . number_format( $grand_total, 2 );

        return $rows;
    }

    /* ══════════════════════════════════════════════════════════════
       CHECKOUT PRE-FILL
    ══════════════════════════════════════════════════════════════ */

    public static function prefill_checkout_fields( $value, $input ) {
        if ( ! WC()->session ) return $value;
        $booking = WC()->session->get( 'bwb_booking' );
        if ( empty( $booking ) ) return $value;

        $use_delivery = ( ( $booking['same_billing'] ?? 'yes' ) === 'yes' );
        $map = [
            'billing_address_1' => $use_delivery ? ( $booking['address_line1']    ?? '' ) : ( $booking['billing_line1']    ?? '' ),
            'billing_city'      => $use_delivery ? ( $booking['address_city']     ?? '' ) : ( $booking['billing_city']     ?? '' ),
            'billing_state'     => $use_delivery ? ( $booking['address_province'] ?? '' ) : ( $booking['billing_province'] ?? '' ),
            'billing_postcode'  => $use_delivery ? ( $booking['address_postal']   ?? '' ) : ( $booking['billing_postal']   ?? '' ),
            'billing_country'   => 'CA',
        ];
        return $map[ $input ] ?? $value;
    }
}
