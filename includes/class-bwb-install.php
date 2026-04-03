<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class BWB_Install {

    public static function activate() {
        self::create_table();
        self::maybe_create_booking_product();
        update_option( 'bwb_db_version', '1.0.0' );
    }

    /** Run on every page load via plugins_loaded to catch missed activations */
    public static function ensure_table() {
        // Only re-run if version is missing or mismatched
        if ( get_option( 'bwb_db_version' ) === '1.0.0' ) return;
        self::create_table();
        update_option( 'bwb_db_version', '1.0.0' );
    }

    public static function create_table() {
        global $wpdb;
        $table   = $wpdb->prefix . 'bwb_bookings';
        $charset = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS {$table} (
            id               BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            order_id         BIGINT(20) UNSIGNED DEFAULT NULL,
            bin_size         VARCHAR(60)   NOT NULL DEFAULT '',
            bin_label        VARCHAR(120)  NOT NULL DEFAULT '',
            delivery_date    DATE          NOT NULL,
            duration         VARCHAR(60)   NOT NULL DEFAULT '',
            delivery_time    VARCHAR(60)   NOT NULL DEFAULT '',
            driveway_pads    TINYINT(1)    NOT NULL DEFAULT 0,
            mattresses       TINYINT(1)    NOT NULL DEFAULT 0,
            mattress_qty     TINYINT(3)    NOT NULL DEFAULT 0,
            bin_contents     TEXT,
            bin_location     VARCHAR(100)  DEFAULT '',
            cancellation     TINYINT(1)    NOT NULL DEFAULT 0,
            address_line1    VARCHAR(255)  DEFAULT '',
            address_city     VARCHAR(100)  DEFAULT '',
            address_province VARCHAR(10)   DEFAULT '',
            address_postal   VARCHAR(20)   DEFAULT '',
            delivery_zone    VARCHAR(60)   DEFAULT '',
            zone_fee         DECIMAL(10,2) NOT NULL DEFAULT 0,
            base_price       DECIMAL(10,2) NOT NULL DEFAULT 0,
            total_price      DECIMAL(10,2) NOT NULL DEFAULT 0,
            customer_name    VARCHAR(200)  DEFAULT '',
            customer_email   VARCHAR(200)  DEFAULT '',
            customer_phone   VARCHAR(50)   DEFAULT '',
            additional_note  TEXT,
            status           VARCHAR(50)   NOT NULL DEFAULT 'pending',
            created_at       DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY order_id (order_id),
            KEY delivery_date (delivery_date)
        ) {$charset};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );
    }

    public static function maybe_create_booking_product() {
        if ( get_option( 'bwb_product_id' ) ) {
            $pid = (int) get_option( 'bwb_product_id' );
            if ( $pid && get_post( $pid ) ) return;
            delete_option( 'bwb_product_id' );
        }

        if ( ! class_exists( 'WC_Product_Simple' ) ) return;

        $product = new WC_Product_Simple();
        $product->set_name( 'Bin Rental Booking' );
        $product->set_status( 'publish' );
        $product->set_catalog_visibility( 'hidden' );
        $product->set_price( 0 );
        $product->set_regular_price( 0 );
        $product->set_virtual( true );
        $product->set_sold_individually( true );
        $product->save();

        update_option( 'bwb_product_id', $product->get_id() );
    }
}
