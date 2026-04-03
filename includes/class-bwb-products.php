<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class BWB_Products {

    public static function get_bins() {
        $img = BWB_URL . 'assets/images/';
        return [
            'load_go_12' => [
                'id'          => 'load_go_12',
                'name'        => '9 YARD CONCRETE BIN',
                'size'        => '9',
                'price'       => 379,
                'old_price'   => null,
                'dimensions'  => "9 Cubic Yards 2.5' H / 7' W / 14' L",
                 'tonnage'     => 'Unlimited Tonnage, Clean Concrete Recycling',
                'description' => 'Concrete Only',
                'uses'        => 'Same Day Service Avaliable',
                'image'       => $img . 'bin-12.png',
                'type'        => 'general',
            ],
            'happy_medium_16' => [
                'id'          => 'happy_medium_16',
                'name'        => '12 YARD BIN $279+GST',
                'size'        => '16',
                'price'       => 279,
                'old_price'   => null,
                'dimensions'  => "12 Cubic Yards 4.5' H / 6' W / 12' L",
                'tonnage'     => 'Includes 1 metric tonne, additional tonnage pro-rated at $85/metric tonne',
                'description' => 'Perfect landscaping projects.',
                'uses'        => 'Same Day Service Avaliable',
                'image'       => $img . 'bin-16.png',
                'type'        => 'general',
            ],
            'serious_cleanout_20' => [
                'id'          => 'serious_cleanout_20',
                'name'        => '20 YARD BIN',
                'size'        => '20',
                'price'       => 339,
                'old_price'   => null,
                'dimensions'  => "20 Cubic Yards 5.5' H / 7/8' W / 13/14' L",
                'tonnage'     => 'Includes 1.5 metric tonnes, additional tonnage pro-rated at $85/metric tonne',
                'description' => 'Perfect for cleanups & junk disposal',
                'uses'        => 'Light Residential Use',
                'image'       => $img . 'bin-20.png',
                'type'        => 'general',
            ],
            'clean_sod_dirt_4' => [
                'id'          => 'clean_sod_dirt_4',
                'name'        => '30 YARD BIN',
                'size'        => '30',
                'price'       => 479,
                'old_price'   => null,
                'dimensions'  => "30 Cubic Yards 7' H / 7.5' W / 16' L ",
                'tonnage'     => 'Includes 2 metric tonnes, additional tonnage pro-rated at $85/metric tonne',
                'description' => 'Perfect for small to medium projects',
                'uses'        => 'Home Remodel & Clean Outs',
                'image'       => $img . 'bin-sod.png',
                'type'        => 'special',
                'notes'       => [
                    'Perfect for small to medium projects',
                    'Home Remodel & Clean Outs',
                ],
                'no_duration' => true,
            ],
            'clean_concrete_4' => [
                'id'          => 'clean_concrete_4',
                'name'        => '40 YARD BIN',
                'size'        => '40',
                'price'       => 539,
                'old_price'   => null,
                'dimensions'  => '40 Cubic Yards',
                'tonnage'     => 'Includes 3 metric tonnes, additional tonnage pro-rated at $85/metric tonne',
                'description' => 'Perfect for large scale jobs site cleanupsum',
                'image'       => $img . 'bin-concrete.png',
                'type'        => 'special',
                'notes'       => [
                    'Perfect for large scale jobs site cleanupsum',
                    'Larger Job Site Cleanups',
                ],
                'no_duration' => true,
            ],
        ];
    }

    public static function get_durations() {
        return [
            '24_hours'    => [ 'label' => '24 Hours – Included (Weekdays Only)', 'price' => 0 ],
            '3_days'      => [ 'label' => '3 Days',                              'price' => 10 ],
            '7_days'      => [ 'label' => '7 Days',                              'price' => 40 ],
            'long_term_7' => [ 'label' => 'Long Term (first 7 days + $10/additional day)', 'price' => 40 ],
        ];
    }

    public static function get_delivery_times() {
        return [
            '6am_7pm'  => [ 'label' => '6am – 7pm (included)', 'price' => 0  ],
            '6am_9am'  => [ 'label' => '6am – 9am',            'price' => 30 ],
            '9am_12pm' => [ 'label' => '9am – 12pm',           'price' => 20 ],
            '12pm_3pm' => [ 'label' => '12pm – 3pm',           'price' => 15 ],
        ];
    }

    public static function get_mattress_prices() {
        return [ 1 => 25, 2 => 50, 3 => 75, 4 => 100, 5 => 125 ];
    }

    public static function get_bin_contents() {
        return [
            'shingles'       => 'Shingles',
            'flooring'       => 'Flooring',
            'drywall'        => 'Drywall',
            'trees_branches' => 'Trees and Branches',
            'household_junk' => 'Household Junk',
            'other'          => 'Other',
        ];
    }

    public static function get_bin_locations() {
        return [
            'left'  => 'Left side of Driveway',
            'right' => 'Right side of Driveway',
            'back'  => 'Back Alley',
            'front' => 'Front Lawn',
            'other' => 'Other',
        ];
    }

    public static function get_zones() {
        return [
            'zone_blue'  => [ 'label' => 'Blue',  'color' => '#065c9d', 'desc' => 'Edmonton, St. Albert, Sherwood Park, Spruce Grove, Lancaster Park', 'price' => 0  ],
            'zone_green' => [ 'label' => 'Green', 'color' => '#2e8657', 'desc' => 'Ardrossan, Namao, Villeneuve, Stony Plain, Nisku, Beaumont',         'price' => 50 ],
            'zone_red'   => [ 'label' => 'Red',   'color' => '#b34e3d', 'desc' => 'Morinville, Bon Accord, Gibbons, Fort Sask., Cooking Lake, Leduc',   'price' => 75 ],
        ];
    }

    /**
     * Towns available in the dropdown, grouped by zone.
     * 'free' towns are included at no extra charge.
     * 'green' towns add $50, 'red' towns add $75.
     */
    public static function get_town_zones() {
        return [
            // Free / Blue zone
            'Edmonton'       => [ 'zone' => 'Blue',  'fee' => 0  ],
            'St. Albert'     => [ 'zone' => 'Blue',  'fee' => 0  ],
            'Sherwood Park'  => [ 'zone' => 'Blue',  'fee' => 0  ],
            'Spruce Grove'   => [ 'zone' => 'Blue',  'fee' => 0  ],
            'Lancaster Park' => [ 'zone' => 'Blue',  'fee' => 0  ],
            // Green zone (+$50)
            'Ardrossan'      => [ 'zone' => 'Green', 'fee' => 50 ],
            'Namao'          => [ 'zone' => 'Green', 'fee' => 50 ],
            'Villeneuve'     => [ 'zone' => 'Green', 'fee' => 50 ],
            'Stony Plain'    => [ 'zone' => 'Green', 'fee' => 50 ],
            'Nisku'          => [ 'zone' => 'Green', 'fee' => 50 ],
            'Beaumont'       => [ 'zone' => 'Green', 'fee' => 50 ],
            // Red zone (+$75)
            'Morinville'     => [ 'zone' => 'Red',   'fee' => 75 ],
            'Bon Accord'     => [ 'zone' => 'Red',   'fee' => 75 ],
            'Gibbons'        => [ 'zone' => 'Red',   'fee' => 75 ],
            'Fort Saskatchewan' => [ 'zone' => 'Red', 'fee' => 75 ],
            'Cooking Lake'   => [ 'zone' => 'Red',   'fee' => 75 ],
            'Leduc'          => [ 'zone' => 'Red',   'fee' => 75 ],
        ];
    }

    public static function calculate_total( $data ) {
        $bins = self::get_bins();
        $bin  = $bins[ $data['bin_id'] ?? '' ] ?? null;
        if ( ! $bin ) return 0;

        $total = $bin['price'];

        if ( empty($bin['no_duration']) ) {
            $dur = self::get_durations()[ $data['duration'] ?? '' ] ?? null;
            if ( $dur ) $total += $dur['price'];
        }

        $time = self::get_delivery_times()[ $data['delivery_time'] ?? '' ] ?? null;
        if ( $time ) $total += $time['price'];

        if ( ! empty($data['driveway_pads']) ) $total += 20;

        $mp  = self::get_mattress_prices();
        $qty = intval( $data['mattress_qty'] ?? 0 );
        if ( $qty > 0 && isset($mp[$qty]) ) $total += $mp[$qty];

        if ( ! empty($data['cancellation']) ) $total += 5;

        $total += floatval( $data['zone_fee'] ?? 0 );

        return $total;
    }
}