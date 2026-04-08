<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class BWB_Admin {

    public static function init() {
        add_action( 'admin_menu', [ __CLASS__, 'add_menu' ] );
        add_action( 'admin_init', [ __CLASS__, 'save_settings' ] );
    }

    public static function add_menu() {
        add_menu_page(
            'YellowBins Booking',
            'YellowBins Booking',
            'manage_options',
            'bwb-bookings',
            [ __CLASS__, 'render_bookings_page' ],
            'dashicons-calendar-alt',
            56
        );
        add_submenu_page(
            'bwb-bookings',
            'Bookings',
            'All Bookings',
            'manage_options',
            'bwb-bookings',
            [ __CLASS__, 'render_bookings_page' ]
        );
        add_submenu_page(
            'bwb-bookings',
            'Settings',
            'Settings',
            'manage_options',
            'bwb-settings',
            [ __CLASS__, 'render_settings_page' ]
        );
    }

    /* ── Bookings list ──────────────────────────────────────────── */
    public static function render_bookings_page() {
        global $wpdb;
        $table = $wpdb->prefix . 'bwb_bookings';
        $rows  = $wpdb->get_results( "SELECT * FROM {$table} ORDER BY created_at DESC LIMIT 200" );
        ?>
        <div class="wrap">
            <h1>YellowBins Booking</h1>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Order</th>
                        <th>Customer</th>
                        <th>Bin</th>
                        <th>Delivery Date</th>
                        <th>Address</th>
                        <th>Zone</th>
                        <th>Total</th>
                        <th>Status</th>
                        <th>Created</th>
                    </tr>
                </thead>
                <tbody>
                <?php if ( empty( $rows ) ) : ?>
                    <tr><td colspan="10">No bookings yet.</td></tr>
                <?php else : foreach ( $rows as $r ) : ?>
                    <tr>
                        <td><?php echo esc_html( $r->id ); ?></td>
                        <td>
                            <?php if ( $r->order_id ) :
                                $url = admin_url( 'post.php?post=' . $r->order_id . '&action=edit' );
                                echo '<a href="' . esc_url( $url ) . '">#' . esc_html( $r->order_id ) . '</a>';
                            else: echo '—'; endif; ?>
                        </td>
                        <td>
                            <?php echo esc_html( $r->customer_name ); ?><br>
                            <small><?php echo esc_html( $r->customer_email ); ?></small>
                        </td>
                        <td><?php echo esc_html( $r->bin_label ); ?></td>
                        <td><?php echo esc_html( date( 'M j, Y', strtotime( $r->delivery_date ) ) ); ?></td>
                        <td><?php echo esc_html( $r->address_line1 . ', ' . $r->address_city ); ?></td>
                        <td><?php echo esc_html( $r->delivery_zone ); ?></td>
                        <td>$<?php echo number_format( $r->total_price, 2 ); ?></td>
                        <td><span class="bwb-status bwb-status--<?php echo esc_attr( $r->status ); ?>"><?php echo esc_html( $r->status ); ?></span></td>
                        <td><?php echo esc_html( date( 'M j, Y g:ia', strtotime( $r->created_at ) ) ); ?></td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
        <style>
            .bwb-status { padding:2px 8px; border-radius:3px; font-size:12px; text-transform:capitalize; }
            .bwb-status--pending   { background:#fff3cd; color:#856404; }
            .bwb-status--confirmed { background:#d1e7dd; color:#0f5132; }
            .bwb-status--cancelled { background:#f8d7da; color:#842029; }
        </style>
        <?php
    }

    /* ── Settings page ──────────────────────────────────────────── */
    public static function render_settings_page() {
        $shortcode = '[binwise_booking]';
        $product_id = get_option( 'bwb_product_id', 'Not created yet' );
        ?>
        <div class="wrap">
            <h1>YellowBins Booking — Settings</h1>
            <?php settings_errors( 'bwb_settings' ); ?>

            <h2>Setup Instructions</h2>
            <ol>
                <li>Create a WordPress page (e.g. "Book a Bin") and add the shortcode: <code><?php echo esc_html( $shortcode ); ?></code></li>
                <li>Make sure WooCommerce is installed, active, and has at least one payment method enabled.</li>
                <li>The hidden booking product ID is: <strong><?php echo esc_html( $product_id ); ?></strong></li>
                <li>Google Maps is used for address lookup. Enter your API key below.</li>
            </ol>

            <form method="post" action="">
                <?php wp_nonce_field( 'bwb_save_settings' ); ?>
                <table class="form-table">
                    <tr>
                        <th><label for="bwb_google_maps_key">Google Maps API Key</label></th>
                        <td>
                            <input type="text" id="bwb_google_maps_key" name="bwb_google_maps_key"
                                   value="<?php echo esc_attr( get_option( 'bwb_google_maps_key', '' ) ); ?>"
                                   class="regular-text">
                            <p class="description">Required for address autocomplete and zone detection. Get one from <a href="https://console.cloud.google.com/" target="_blank">Google Cloud Console</a>. Enable Maps JavaScript API and Places API.</p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="bwb_contact_phone">Contact Phone</label></th>
                        <td>
                            <input type="text" id="bwb_contact_phone" name="bwb_contact_phone"
                                   value="<?php echo esc_attr( get_option( 'bwb_contact_phone', '587-405-7545' ) ); ?>"
                                   class="regular-text">
                        </td>
                    </tr>
                    
                </table>
                <?php submit_button( 'Save Settings' ); ?>
            </form>

            <hr>
            <h2>Re-create Hidden WC Product</h2>
            <p>If the hidden WooCommerce product was deleted, click below to re-create it.</p>
            <form method="post">
                <?php wp_nonce_field( 'bwb_recreate_product' ); ?>
                <input type="hidden" name="bwb_action" value="recreate_product">
                <?php submit_button( 'Re-create Booking Product', 'secondary' ); ?>
            </form>
        </div>
        <?php
    }

    public static function save_settings() {
        if ( ! isset( $_POST['_wpnonce'] ) ) return;

        // Settings form
        if ( wp_verify_nonce( $_POST['_wpnonce'], 'bwb_save_settings' ) ) {
            update_option( 'bwb_google_maps_key', sanitize_text_field( $_POST['bwb_google_maps_key'] ?? '' ) );
            update_option( 'bwb_contact_phone',   sanitize_text_field( $_POST['bwb_contact_phone'] ?? '587-405-7545' ) );
            
            add_settings_error( 'bwb_settings', 'saved', 'Settings saved.', 'success' );
        }

        // Recreate product
        if ( wp_verify_nonce( $_POST['_wpnonce'], 'bwb_recreate_product' ) && ( $_POST['bwb_action'] ?? '' ) === 'recreate_product' ) {
            delete_option( 'bwb_product_id' );
            BWB_Install::maybe_create_booking_product();
            add_settings_error( 'bwb_settings', 'recreated', 'Booking product re-created. ID: ' . get_option( 'bwb_product_id' ), 'success' );
        }
    }
}
