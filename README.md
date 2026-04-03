# BinWise Booking System — WordPress Plugin

A complete multi-step bin rental booking form that feeds directly into WooCommerce for payment processing.

---

## Requirements

- WordPress 6.0+
- WooCommerce 8.0+
- PHP 7.4+
- Google Maps API key (for address autocomplete + zone detection)

---

## Installation

1. Upload the `binwise-booking` folder to `/wp-content/plugins/`
2. Activate via **Plugins → Installed Plugins**
3. WooCommerce must be active — you'll see an error notice if it isn't
4. Go to **BinWise Booking → Settings** and enter your Google Maps API key
5. Create a WordPress page (e.g. "Book a Bin") and add the shortcode:

```
[binwise_booking]
```

---

## How It Works

### Multi-Step Form Flow

| Step | What the customer does |
|------|------------------------|
| 1 | Selects bin size |
| 2 | Picks delivery date, rental duration, and delivery time window |
| 3 | Chooses add-ons (driveway pads, mattresses, bin contents, bin placement, cancellation protection) |
| 4 | Enters delivery address — zone auto-detected via Google Maps |
| 5 | Reviews full booking summary and agrees to rental terms |

Clicking **Continue to Checkout** adds the booking to the WooCommerce cart at the calculated price and redirects the customer to the WooCommerce checkout page.

### Pricing Logic

| Item | Price |
|------|-------|
| 12 Yard bin | $269 |
| 16 Yard bin | $309 |
| 20 Yard bin | $339 |
| Sod & Dirt 4 Yard (flat) | $375 |
| Concrete 4 Yard (flat) | $350 |
| Extra 3 days | +$10 |
| Extra 7 days | +$40 |
| Long-term (7 days base) | +$40 |
| Delivery 6am–9am | +$30 |
| Delivery 9am–12pm | +$20 |
| Delivery 12pm–3pm | +$15 |
| Driveway protection pads | +$20 |
| Mattresses (1–5) | +$25–$125 |
| Cancellation protection | +$5 |
| Blue zone (Edmonton core) | Included |
| Green zone | +$50 |
| Red zone | +$75 |

### WooCommerce Integration

- A hidden "Bin Rental Booking" product is created automatically on activation (product ID stored in `bwb_product_id` option)
- The cart item price is overridden to the calculated booking total via `woocommerce_before_calculate_totals`
- Booking details are saved as order meta (`_bwb_booking`) and in a custom `wp_bwb_bookings` database table
- Billing fields are pre-filled from the booking address
- Booking details appear in:
  - WooCommerce cart and checkout item meta
  - Order confirmation page
  - Order emails
  - WooCommerce admin order screen

---

## Google Maps API Setup

1. Go to [Google Cloud Console](https://console.cloud.google.com/)
2. Create a project or select an existing one
3. Enable **Maps JavaScript API** and **Places API**
4. Create an API key and restrict it to your domain
5. Paste the key in **BinWise Booking → Settings**

Without an API key, address autocomplete is disabled but customers can still type their address manually.

---

## Delivery Zone Detection

Zones are detected automatically based on lat/lng from Google Maps:

- **Blue** — Edmonton, St. Albert, Sherwood Park, Spruce Grove (Included)
- **Green** — Ardrossan, Namao, Villeneuve, Stony Plain, Beaumont (+$50)
- **Red** — Morinville, Bon Accord, Gibbons, Fort Sask., Cooking Lake, Leduc (+$75)
- Outside all zones — customer is shown a call-us message

---

## Admin

- **BinWise Booking → All Bookings** — view all bookings with order link, customer info, bin, date, zone, total, and status
- **BinWise Booking → Settings** — Google Maps key, contact phone, Sunday disable toggle, product re-creation

---

## Customization

### Change bin prices or names
Edit `includes/class-bwb-products.php` — the `get_bins()` method.

### Add/remove add-ons
Edit `get_durations()`, `get_delivery_times()`, etc. in the same file.

### Style changes
Edit `assets/css/booking.css` — uses CSS custom properties (variables) at the top for easy color/radius changes.

### Change the zone polygons
Edit the `zoneDefs` object in `assets/js/booking.js` (and the corresponding PHP in `class-bwb-products.php`).

---

## Shortcode

```
[binwise_booking]
```

Place this on any page or post. One booking form per page.
