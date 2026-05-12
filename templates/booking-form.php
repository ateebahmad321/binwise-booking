<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>

<div id="bwb-wrap" class="bwb-wrap">

    <div class="bwb-notices" id="bwb-notices" role="alert" aria-live="polite"></div>

    <form id="bwb-form" novalidate>

        <!-- ── SECTION 1: Bin Size ───────────────────────────────── -->
        <div class="bwb-section">
            <div class="bwb-section__title">What Size Bin Do You Need? <span class="bwb-req">*</span></div>
            <div class="bwb-section__sub">Click on the bin you would like</div>
            <div class="bwb-bins" id="bwb-bins" role="radiogroup" aria-label="Bin sizes">
                <!-- Populated by JS -->
            </div>
        </div>

        <!-- ── SECTION 2: Delivery Date ─────────────────────────── -->
        <div class="bwb-section">
            <div class="bwb-section__title">What Day Would You Like The Bin Delivered? <span class="bwb-req">*</span></div>
            <input type="date" id="bwb-delivery-date" name="delivery_date" class="bwb-input" required>
        </div>

        <!-- ── SECTION 3: Duration ───────────────────────────────── -->
        <div class="bwb-section" id="bwb-duration-section">
            <div class="bwb-section__title">How Many Days Do You Need The Bin For? <span class="bwb-req">*</span></div>
            <div class="bwb-choice-list" id="bwb-durations" role="radiogroup" aria-label="Rental duration">
                <!-- Populated by JS -->
            </div>
        </div>

        <!-- ── SECTION 4: Contents ───────────────────────────────── -->
        <div class="bwb-section">
            <div class="bwb-section__title">What Will Be Going In The Bin? <span class="bwb-req">*</span></div>
            <div class="bwb-choice-list bwb-choice-list--checkbox" id="bwb-contents" role="group" aria-label="Bin contents">
                <!-- Populated by JS -->
            </div>
        </div>

        <!-- ── SECTION 5: Bin Placement ─────────────────────────── -->
        <div class="bwb-section">
            <div class="bwb-section__title">Where Do You Want Your Bin? <span class="bwb-req">*</span></div>
            <div class="bwb-choice-list" id="bwb-location" role="radiogroup" aria-label="Bin placement">
                <!-- Populated by JS -->
            </div>
            <div id="bwb-location-other-wrap" style="display:none; margin-top:12px;">
                <label class="bwb-label" for="bwb-location-other">Describe other location <span class="bwb-req">*</span></label>
                <input type="text" id="bwb-location-other" name="bin_location_other" class="bwb-input">
            </div>
        </div>

        <!-- ── SECTION 6: Delivery Address ─────────────────────── -->
        <div class="bwb-section">
            <div class="bwb-section__title">Delivery Address <span class="bwb-req">*</span></div>

            <!-- City / Town Dropdown -->
            <div style="margin-bottom:18px;">
                <label class="bwb-label" for="bwb-town-select">Select Your City / Town <span class="bwb-req">*</span></label>
                <select id="bwb-town-select" name="town_select" class="bwb-input">
                    <option value="">— Select your city or town —</option>
                    <optgroup label="Included in Price">
                        <option value="Edmonton|Blue|0">Edmonton</option>
                        <option value="St. Albert|Blue|0">St. Albert</option>
                        <option value="Sherwood Park|Blue|0">Sherwood Park</option>
                        <option value="Spruce Grove|Blue|0">Spruce Grove</option>
                        <option value="Lancaster Park|Blue|0">Lancaster Park</option>
                    </optgroup>
                    <optgroup label="Outside Region (+$50)">
                        <option value="Ardrossan|Green|50">Ardrossan (+$50)</option>
                        <option value="Namao|Green|50">Namao (+$50)</option>
                        <option value="Villeneuve|Green|50">Villeneuve (+$50)</option>
                        <option value="Stony Plain|Green|50">Stony Plain (+$50)</option>
                        <option value="Nisku|Green|50">Nisku (+$50)</option>
                        <option value="Beaumont|Green|50">Beaumont (+$50)</option>
                    </optgroup>
                    <optgroup label="Outside Region (+$75)">
                        <option value="Morinville|Red|75">Morinville (+$75)</option>
                        <option value="Bon Accord|Red|75">Bon Accord (+$75)</option>
                        <option value="Gibbons|Red|75">Gibbons (+$75)</option>
                        <option value="Fort Saskatchewan|Red|75">Fort Saskatchewan (+$75)</option>
                        <option value="Cooking Lake|Red|75">Cooking Lake (+$75)</option>
                        <option value="Leduc|Red|75">Leduc (+$75)</option>
                    </optgroup>
                </select>

                <!-- Zone fee badge -->
                <div id="bwb-zone-badge" style="display:none; margin-top:8px;" aria-live="polite"></div>

                <p class="bwb-hint" style="margin-top:6px;">
                    Don't see your city?
                    <a href="tel:<?php echo esc_attr( preg_replace( '/\D/', '', get_option( 'bwb_contact_phone', '587-405-7545' ) ) ); ?>" style="color:var(--bwb-primary-dark);font-weight:600;">Call us</a>
                    or <a href="mailto:<?php echo esc_attr( get_option( 'bwb_contact_email', '' ) ); ?>" style="color:var(--bwb-primary-dark);font-weight:600;">email us</a> — we may still be able to help.
                </p>
            </div>

            <!-- Manual address fields -->
            <div class="bwb-grid">
                <div style="grid-column: 1 / -1;">
                    <label class="bwb-label" for="bwb-addr-line1">Street Address <span class="bwb-req">*</span></label>
                    <input type="text" id="bwb-addr-line1" name="address_line1" class="bwb-input" placeholder="e.g. 123 Main Street" required autocomplete="street-address">
                </div>
                <div>
                    <label class="bwb-label" for="bwb-addr-city">City <span class="bwb-req">*</span></label>
                    <input type="text" id="bwb-addr-city" name="address_city" class="bwb-input" placeholder="e.g. Edmonton" required autocomplete="address-level2">
                </div>
                <div>
                    <label class="bwb-label" for="bwb-addr-postal">Postal Code</label>
                    <input type="text" id="bwb-addr-postal" name="address_postal" class="bwb-input" placeholder="e.g. T5A 0A1" autocomplete="postal-code">
                </div>
                <div>
                    <label class="bwb-label" for="bwb-addr-province">Province</label>
                    <input type="text" id="bwb-addr-province" name="address_province" class="bwb-input" value="AB" autocomplete="address-level1">
                </div>
            </div>

            <!-- Billing address toggle -->
            <div style="margin-top:22px;">
                <div class="bwb-section__title" style="margin-bottom:10px;">Is the Delivery Address the Same as Billing Address? <span class="bwb-req">*</span></div>
                <div class="bwb-segmented" role="group">
                    <label class="bwb-seg-btn">
                        <input type="radio" name="same_billing" value="yes" checked>
                        <span>YES</span>
                    </label>
                    <label class="bwb-seg-btn">
                        <input type="radio" name="same_billing" value="no">
                        <span>NO</span>
                    </label>
                </div>
            </div>

            <div id="bwb-billing-address-wrap" style="display:none; margin-top:16px;">
                <div class="bwb-section__title" style="margin-bottom:10px;">Billing Address</div>
                <div class="bwb-grid">
                    <div style="grid-column: 1 / -1;">
                        <label class="bwb-label" for="bwb-bill-line1">Street Address</label>
                        <input type="text" id="bwb-bill-line1" name="billing_line1" class="bwb-input" autocomplete="billing street-address">
                    </div>
                    <div>
                        <label class="bwb-label" for="bwb-bill-city">City</label>
                        <input type="text" id="bwb-bill-city" name="billing_city" class="bwb-input" autocomplete="billing address-level2">
                    </div>
                    <div>
                        <label class="bwb-label" for="bwb-bill-postal">Postal Code</label>
                        <input type="text" id="bwb-bill-postal" name="billing_postal" class="bwb-input" autocomplete="billing postal-code">
                    </div>
                    <div>
                        <label class="bwb-label" for="bwb-bill-province">Province</label>
                        <select id="bwb-bill-province" name="billing_province" class="bwb-input" autocomplete="billing address-level1">
                            <option value="AB" selected>Alberta</option>
                            <option value="BC">British Columbia</option>
                            <option value="MB">Manitoba</option>
                            <option value="ON">Ontario</option>
                            <option value="SK">Saskatchewan</option>
                        </select>
                    </div>
                </div>
            </div>
        </div>

        <!-- ── SECTION 7: Additional Notes ─────────────────────── -->
        <div class="bwb-section">
            <div class="bwb-section__title">Additional Information</div>
            <input type="text" id="bwb-additional-note" name="additional_note" class="bwb-input" placeholder="Gate codes, special instructions, access notes…">
        </div>

        <!-- ── SECTION 8: Terms + Submit ────────────────────────── -->
        <div class="bwb-section">
            <div class="bwb-choice-list" style="margin-bottom:18px;">
                <label class="bwb-choice bwb-terms">
                    <input type="checkbox" id="bwb-agreed-terms" name="agreed_terms" value="1" required>
                    <span>I have read and agree to the <a href="/refund_returns" target="_blank">Rental Agreement</a> and <a href="/privacy-policy/" target="_blank">Privacy Policy</a></span>
                </label>
            </div>

            <button type="button" id="bwb-submit" class="bwb-submit-btn">
                <span class="bwb-submit-text">Continue to Checkout</span>
                <span class="bwb-submit-sep"> — </span>
                <span class="bwb-submit-total" id="bwb-submit-total">$0.00</span>
            </button>
        </div>

    </form>

    <div class="bwb-total-bar" id="bwb-total-bar">
        <span class="bwb-total-bar__label">Estimated Total</span>
        <span class="bwb-total-bar__amount" id="bwb-running-total">$0.00</span>
    </div>

</div>
