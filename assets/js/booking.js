/**
 * BinWise Booking — Single-page form JS
 */
(function ($) {
    'use strict';

    var state = {
        bin_id: '', delivery_date: '', duration: '',
        bin_contents: [], bin_contents_other: '', bin_location: '',
        bin_location_other: '',
        address_line1: '', address_city: '', address_province: 'AB',
        address_postal: '', delivery_zone: '', zone_fee: 0,
        same_billing: 'yes', billing_line1: '', billing_city: '',
        billing_province: 'AB', billing_postal: '', additional_note: '',
        agreed_terms: false,
    };

    $(function () {
        buildBinCards();
        buildDurations();
        buildBinContents();
        buildBinLocations();
        setMinDate();
        bindEvents();
        updateTotal();
        waitForMaps(initMapsAutocomplete);
    });

    /* ── Builders ─────────────────────────────────────────────── */
    function buildBinCards() {
        var $c = $('#bwb-bins').empty();
        $.each(BWB.bins, function (id, bin) {
            var oldPrice = bin.old_price ? '<del>$' + fmt(bin.old_price) + '</del>' : '';
            var details  = '';
            if (bin.notes && bin.notes.length) {
                details = '<div class="bwb-bin-card__details"><ul>' +
                    bin.notes.map(function (n) { return '<li>' + esc(n) + '</li>'; }).join('') +
                    '</ul></div>';
            } else {
                details = '<div class="bwb-bin-card__details">' +
                    '<p>' + esc(bin.description) + '</p>' +
                    (bin.uses ? '<p><em>' + esc(bin.uses) + '</em></p>' : '') +
                    '</div>';
            }
            var img = bin.image
                ? '<img src="' + esc(bin.image) + '" alt="' + esc(bin.name) + '" loading="lazy">'
                : '<span style="font-size:26px;">🗑️</span>';

            $c.append(
                '<label class="bwb-bin-card" data-bin-id="' + esc(id) + '">' +
                '<input type="radio" name="bin_id" value="' + esc(id) + '">' +
                '<div class="bwb-bin-card__left">' +
                  '<div class="bwb-bin-card__thumb">' + img + '</div>' +
                  '<div class="bwb-bin-card__text">' +
                    '<div class="bwb-bin-card__name">' + esc(bin.name) + '</div>' +
                    '<div class="bwb-bin-card__dim">' + esc(bin.dimensions) + '</div>' +
                    (bin.tonnage ? '<div class="bwb-bin-card__tonnage">' + esc(bin.tonnage) + '</div>' : '') +
                    details +
                  '</div>' +
                '</div>' +
                '<div class="bwb-bin-card__right">' +
                  '<div class="bwb-bin-card__price">' + oldPrice + '$' + fmt(bin.price) + '</div>' +
                '</div>' +
                '</label>'
            );
        });
    }

    function buildDurations() {
        var $c = $('#bwb-durations').empty();
        $.each(BWB.durations, function (key, d) {
            var badge = d.price > 0
                ? '<span class="bwb-choice__price">+$' + fmt(d.price) + '</span>'
                : '<span class="bwb-choice__price" style="color:#2e8657">Included</span>';
            $c.append(radio('duration', key, esc(d.label), badge));
        });
    }

    function buildBinContents() {
        var $c = $('#bwb-contents').empty();
        $.each(BWB.contents, function (key, label) {
            $c.append(
                '<label class="bwb-choice">' +
                '<input type="checkbox" name="bin_contents[]" value="' + esc(key) + '">' +
                '<span class="bwb-choice__name">' + esc(label) + '</span>' +
                '</label>'
            );
        });
    }

    function buildBinLocations() {
        var $c = $('#bwb-location').empty();
        $.each(BWB.locations, function (key, label) {
            $c.append(radio('bin_location', key, esc(label), ''));
        });
    }

    function radio(name, value, label, extra) {
        return '<label class="bwb-choice">' +
            '<input type="radio" name="' + esc(name) + '" value="' + esc(value) + '">' +
            '<span class="bwb-choice__name">' + label + '</span>' +
            (extra || '') + '</label>';
    }

    /* ── Events ───────────────────────────────────────────────── */
    function bindEvents() {

        // Bin card
        $(document).on('click', '.bwb-bin-card', function () {
            var id = $(this).data('bin-id');
            state.bin_id = id;
            $(this).find('input[type=radio]').prop('checked', true);
            $('.bwb-bin-card').removeClass('is-selected');
            $(this).addClass('is-selected');
            var bin = BWB.bins[id];
            if (bin && bin.no_duration) {
                $('#bwb-duration-section').hide();
                state.duration = 'flat';
            } else {
                $('#bwb-duration-section').show();
                if (state.duration === 'flat') state.duration = '';
            }
            updateTotal();
        });

        // Radio choice rows
        $(document).on('click', '.bwb-choice-list:not(.bwb-choice-list--checkbox) .bwb-choice', function () {
            var $list = $(this).closest('.bwb-choice-list');
            $list.find('.bwb-choice').removeClass('is-selected');
            $(this).addClass('is-selected');
            var $r = $(this).find('input[type=radio]').prop('checked', true);
            var name = $r.attr('name'), val = $r.val();
            if (name === 'duration')     state.duration = val;
            if (name === 'bin_location') {
                state.bin_location = val;
                $('#bwb-location-other-wrap').toggle(val === 'other');
                if (val !== 'other') state.bin_location_other = '';
            }
            updateTotal();
        });

        // Checkboxes
        $(document).on('change', '.bwb-choice-list--checkbox input[type=checkbox]', function () {
            var $cb = $(this);
            $cb.closest('.bwb-choice').toggleClass('is-selected', $cb.prop('checked'));
            state.bin_contents = [];
            $('.bwb-choice-list--checkbox input:checked').each(function () {
                state.bin_contents.push($(this).val());
            });
        });
        $(document).on('click', '.bwb-choice-list--checkbox .bwb-choice', function (e) {
            if (!$(e.target).is('input')) {
                var $cb = $(this).find('input[type=checkbox]');
                $cb.prop('checked', !$cb.prop('checked')).trigger('change');
            }
        });

        // Segmented — billing only
        $(document).on('change', '.bwb-segmented input[type=radio]', function () {
            var name = $(this).attr('name'), val = $(this).val();
            if (name === 'same_billing') {
                state.same_billing = val;
                $('#bwb-billing-address-wrap').toggle(val === 'no');
            }
            updateTotal();
        });

        // Date
        $(document).on('change', '#bwb-delivery-date', function () { state.delivery_date = $(this).val(); });

        // ── Town / City dropdown ──────────────────────────────────
        $(document).on('change', '#bwb-town-select', function () {
            var raw = $(this).val();
            if (!raw) {
                state.delivery_zone = '';
                state.zone_fee      = 0;
                $('#bwb-zone-badge').hide().empty();
                updateTotal();
                return;
            }

            var parts    = raw.split('|');
            var townName = parts[0] || '';
            var zone     = parts[1] || '';
            var fee      = parseFloat(parts[2]) || 0;

            state.delivery_zone = zone;
            state.zone_fee      = fee;

            if (townName) {
                $('#bwb-addr-city').val(townName);
                state.address_city = townName;
            }

            var badgeText, badgeStyle;
            if (fee === 0) {
                badgeText  = '✓ Included in price — no delivery surcharge';
                badgeStyle = 'background:#d1e7dd;color:#0f5132;border-left:4px solid #22c55e;';
            } else {
                badgeText  = '📍 Delivery surcharge: +$' + fee.toFixed(2) + ' (' + zone + ' zone)';
                badgeStyle = fee === 50
                    ? 'background:#fff3cd;color:#856404;border-left:4px solid #ffc107;'
                    : 'background:#fde8e8;color:#7f1d1d;border-left:4px solid #ef4444;';
            }

            $('#bwb-zone-badge')
                .attr('style', badgeStyle + 'padding:8px 12px;border-radius:4px;font-size:13px;font-weight:600;')
                .text(badgeText)
                .show();

            $('#bwb-map-status').empty();
            updateTotal();
        });

        // Text inputs
        $('#bwb-addr-line1').on('input',    function () { state.address_line1    = $(this).val(); });
        $('#bwb-addr-city').on('input',     function () {
            state.address_city = $(this).val();
            if ($('#bwb-town-select').val()) {
                $('#bwb-town-select').val('');
                state.delivery_zone = '';
                state.zone_fee      = 0;
                $('#bwb-zone-badge').hide().empty();
                updateTotal();
            }
        });
        $('#bwb-addr-province').on('input', function () { state.address_province = $(this).val(); });
        $('#bwb-addr-postal').on('input',   function () { state.address_postal   = $(this).val(); });
        $('#bwb-location-other').on('input',function () { state.bin_location_other = $(this).val(); });
        $('#bwb-additional-note').on('input',function(){ state.additional_note   = $(this).val(); });
        $('#bwb-bill-line1').on('input',    function () { state.billing_line1    = $(this).val(); });
        $('#bwb-bill-city').on('input',     function () { state.billing_city     = $(this).val(); });
        $('#bwb-bill-province').on('change',function () { state.billing_province = $(this).val(); });
        $('#bwb-bill-postal').on('input',   function () { state.billing_postal   = $(this).val(); });

        // Terms
        $(document).on('change', '#bwb-agreed-terms', function () {
            state.agreed_terms = $(this).is(':checked');
            $(this).closest('.bwb-choice').toggleClass('is-selected', state.agreed_terms);
        });

        // Submit
        $('#bwb-submit').on('click', submitBooking);
    }

    /* ── Price ────────────────────────────────────────────────── */
    function calcTotal() {
        var bin = BWB.bins[state.bin_id]; if (!bin) return 0;
        var t = bin.price;
        if (BWB.durations[state.duration]) t += BWB.durations[state.duration].price;
        t += parseFloat(state.zone_fee) || 0;
        return t;
    }

    function updateTotal() {
        var s = '$' + fmt(calcTotal());
        $('#bwb-running-total, #bwb-submit-total').text(s);
    }

    /* ── Google Maps ──────────────────────────────────────────── */

    /**
     * Uses the new PlaceAutocompleteElement (replaces deprecated Autocomplete).
     * Falls back gracefully if the API isn't loaded.
     */
    function initMapsAutocomplete() {
        if (typeof google === 'undefined' || !google.maps || !google.maps.places) return;

        var searchWrap = document.getElementById('bwb-address-search');
        if (!searchWrap) return;

        // Replace the plain <input> with a PlaceAutocompleteElement widget.
        // The widget renders its own input inside a shadow DOM; we hide the
        // original <input> and insert the element right after it.
        var placeAuto;
        try {
            placeAuto = new google.maps.places.PlaceAutocompleteElement({
                componentRestrictions: { country: 'ca' },
                types: ['address'],
            });
        } catch (e) {
            // API version doesn't support PlaceAutocompleteElement — fall back silently.
            initMapsAutocompleteLegacy();
            return;
        }

        // Style the widget to match our inputs
        placeAuto.style.cssText = [
            'display:block',
            'width:100%',
            'border:1px solid #e6e6e6',
            'border-radius:6px',
            'font-size:14px',
            'color:#222',
            '--gmp-mat-color-surface:#fff',
        ].join(';');

        // Insert widget after the original search input and hide the original
        searchWrap.style.display = 'none';
        searchWrap.parentNode.insertBefore(placeAuto, searchWrap.nextSibling);

        placeAuto.addEventListener('gmp-placeselect', function (evt) {
            var place = evt.place;
            if (!place) return;

            // Fetch the fields we need
            place.fetchFields({ fields: ['addressComponents', 'formattedAddress'] }).then(function () {
                applyPlaceComponents(place.addressComponents || []);
            }).catch(function () {
                // If fetchFields fails, try using whatever is available
                applyPlaceComponents(place.addressComponents || []);
            });
        });
    }

    /** Kept as fallback for environments where PlaceAutocompleteElement isn't available */
    function initMapsAutocompleteLegacy() {
        var el = document.getElementById('bwb-address-search');
        if (!el) return;
        /* global google */
        var ac = new google.maps.places.Autocomplete(el, {
            types: ['address'], componentRestrictions: { country: 'ca' }
        });
        ac.addListener('place_changed', function () {
            var place = ac.getPlace();
            if (!place || !place.address_components) return;
            applyPlaceComponents(place.address_components);
        });
    }

    /** Shared helper: read address_components and populate form fields */
    function applyPlaceComponents(components) {
        var sn = '', rt = '', city = '', prov = 'AB', postal = '';

        components.forEach(function (c) {
            var types = c.types || [];
            var long  = c.longText  || c.long_name  || '';
            var short = c.shortText || c.short_name || '';

            if (types.indexOf('street_number') > -1)              sn     = long;
            if (types.indexOf('route') > -1)                      rt     = long;
            if (types.indexOf('locality') > -1)                   city   = long;
            if (types.indexOf('administrative_area_level_1') > -1) prov  = short;
            if (types.indexOf('postal_code') > -1)                postal = long;
        });

        var line1 = (sn + ' ' + rt).trim();
        $('#bwb-addr-line1').val(line1);   state.address_line1   = line1;
        $('#bwb-addr-city').val(city);     state.address_city    = city;
        $('#bwb-addr-province').val(prov); state.address_province = prov;
        $('#bwb-addr-postal').val(postal); state.address_postal  = postal;

        var matched = matchTownDropdown(city);
        if (!matched) {
            // No polygon zone detection needed without lat/lng from new API;
            // clear zone so user selects from dropdown.
            $('#bwb-map-status').text('✓ Address filled — please confirm your city/town in the dropdown above.');
        } else {
            $('#bwb-map-status').text('✓ ' + line1 + ', ' + city);
        }
    }

    function matchTownDropdown(city) {
        if (!city) return false;
        var cityLower = city.toLowerCase().trim();
        var matched   = false;
        $('#bwb-town-select option').each(function () {
            var val = $(this).val();
            if (!val) return;
            var parts    = val.split('|');
            var townName = (parts[0] || '').toLowerCase().trim();
            if (townName === cityLower) {
                $('#bwb-town-select').val(val).trigger('change');
                matched = true;
                return false; // break $.each
            }
        });
        return matched;
    }

    function waitForMaps(cb, n) {
        n = n || 0;
        if (typeof google !== 'undefined' && google.maps && google.maps.places) {
            cb();
        } else if (n < 40) {
            setTimeout(function () { waitForMaps(cb, n + 1); }, 300);
        }
    }

    /* ── Validation ───────────────────────────────────────────── */
    function validate() {
        var e = [];
        if (!state.bin_id) e.push('Please select a bin size.');
        if (!state.delivery_date) e.push('Please select a delivery date.');
        else {
            var ts  = new Date(state.delivery_date + 'T00:00:00');
            var now = new Date();
            now.setHours(0, 0, 0, 0);
            if (ts < now) e.push('Delivery date must be in the future.');
        }
        var bin = BWB.bins[state.bin_id];
        if (bin && !bin.no_duration && !state.duration) e.push('Please select how many days you need the bin.');
        if (!state.bin_location)  e.push('Please select where you want the bin placed.');
        if (!state.address_line1) e.push('Please enter your street address.');
        if (!state.address_city)  e.push('Please enter your city.');
        if (!state.delivery_zone) e.push('Please select your city / town from the dropdown so we can confirm your delivery area.');
        if (!state.agreed_terms)  e.push('Please agree to the Rental Agreement.');
        return e;
    }

    /* ── Submit ───────────────────────────────────────────────── */
    function submitBooking() {
        clearNotice();
        state.additional_note = $('#bwb-additional-note').val();
        state.agreed_terms    = $('#bwb-agreed-terms').is(':checked');

        var errs = validate();
        if (errs.length) {
            showNotice(errs.join('<br>'), 'error');
            $('html,body').animate({ scrollTop: $('#bwb-notices').offset().top - 20 }, 300);
            return;
        }

        var $btn = $('#bwb-submit');
        $btn.addClass('is-loading').prop('disabled', true);
        $btn.find('.bwb-submit-text').text('Processing…');

        $.ajax({
            url: BWB.ajax_url, method: 'POST', traditional: true,
            data: {
                action:              'bwb_add_to_cart',
                nonce:               BWB.nonce,
                bin_id:              state.bin_id,
                delivery_date:       state.delivery_date,
                duration:            state.duration,
                'bin_contents[]':    state.bin_contents,
                bin_contents_other:  state.bin_contents_other,
                bin_location:        state.bin_location,
                bin_location_other:  state.bin_location_other,
                address_line1:       state.address_line1,
                address_city:        state.address_city,
                address_province:    state.address_province,
                address_postal:      state.address_postal,
                delivery_zone:       state.delivery_zone,
                zone_fee:            state.zone_fee,
                same_billing:        state.same_billing,
                billing_line1:       state.billing_line1,
                billing_city:        state.billing_city,
                billing_province:    state.billing_province,
                billing_postal:      state.billing_postal,
                additional_note:     state.additional_note,
                agreed_terms:        state.agreed_terms ? '1' : '',
            },
            success: function (res) {
                if (res.success) {
                    window.location.href = res.data.redirect;
                } else {
                    showNotice(res.data.message || 'An error occurred. Please try again.', 'error');
                    $btn.removeClass('is-loading').prop('disabled', false);
                    $btn.find('.bwb-submit-text').text('Continue to Checkout');
                }
            },
            error: function () {
                showNotice('Network error. Please try again.', 'error');
                $btn.removeClass('is-loading').prop('disabled', false);
                $btn.find('.bwb-submit-text').text('Continue to Checkout');
            }
        });
    }

    /* ── Utilities ────────────────────────────────────────────── */
    function setMinDate() {
        var d = new Date();
        d.setDate(d.getDate() + 1);
        $('#bwb-delivery-date').attr('min', d.toISOString().slice(0, 10));
    }
    function showNotice(msg, type) { $('#bwb-notices').html('<div class="bwb-notice bwb-notice--' + type + '">' + msg + '</div>'); }
    function clearNotice()         { $('#bwb-notices').empty(); }
    function fmt(n)  { return parseFloat(n).toFixed(2); }
    function esc(s)  { return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }

})(jQuery);