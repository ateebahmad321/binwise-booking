/**
 * BinWise Booking — Single-page form JS
 * v1.1.0 — No Google Maps, auto nonce refresh, robust error handling
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

    // Live nonce — refreshed automatically when expired
    var currentNonce = BWB.nonce;

    $(function () {
        buildBinCards();
        buildDurations();
        buildBinContents();
        buildBinLocations();
        setMinDate();
        bindEvents();
        updateTotal();
    });

    /* ══════════════════════════════════════════════════════════════
       BUILDERS
    ══════════════════════════════════════════════════════════════ */

    function buildBinCards() {
        var $c = $('#bwb-bins').empty();
        $.each(BWB.bins, function (id, bin) {
            var oldPrice = bin.old_price ? '<del>$' + fmt(bin.old_price) + '</del>' : '';
            var details;
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

    /* ══════════════════════════════════════════════════════════════
       EVENTS
    ══════════════════════════════════════════════════════════════ */

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

        // Billing same/different toggle
        $(document).on('change', '.bwb-segmented input[type=radio]', function () {
            var name = $(this).attr('name'), val = $(this).val();
            if (name === 'same_billing') {
                state.same_billing = val;
                $('#bwb-billing-address-wrap').toggle(val === 'no');
            }
            updateTotal();
        });

        // Date
        $(document).on('change', '#bwb-delivery-date', function () {
            state.delivery_date = $(this).val();
        });

        // Town / City dropdown
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

            // Auto-fill city field when town is selected
            if (townName && !$('#bwb-addr-city').val()) {
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

            updateTotal();
        });

        // Address text inputs
        $('#bwb-addr-line1').on('input',     function () { state.address_line1    = $(this).val(); });
        $('#bwb-addr-city').on('input',      function () { state.address_city     = $(this).val(); });
        $('#bwb-addr-province').on('input',  function () { state.address_province = $(this).val(); });
        $('#bwb-addr-postal').on('input',    function () { state.address_postal   = $(this).val(); });
        $('#bwb-location-other').on('input', function () { state.bin_location_other = $(this).val(); });
        $('#bwb-additional-note').on('input',function () { state.additional_note  = $(this).val(); });

        // Billing text inputs
        $('#bwb-bill-line1').on('input',     function () { state.billing_line1    = $(this).val(); });
        $('#bwb-bill-city').on('input',      function () { state.billing_city     = $(this).val(); });
        $('#bwb-bill-province').on('change', function () { state.billing_province = $(this).val(); });
        $('#bwb-bill-postal').on('input',    function () { state.billing_postal   = $(this).val(); });

        // Terms
        $(document).on('change', '#bwb-agreed-terms', function () {
            state.agreed_terms = $(this).is(':checked');
            $(this).closest('.bwb-choice').toggleClass('is-selected', state.agreed_terms);
        });

        // Submit
        $('#bwb-submit').on('click', submitBooking);
    }

    /* ══════════════════════════════════════════════════════════════
       PRICE
    ══════════════════════════════════════════════════════════════ */

    function calcTotal() {
        var bin = BWB.bins[state.bin_id];
        if (!bin) return 0;
        var t = bin.price;
        if (BWB.durations[state.duration]) t += BWB.durations[state.duration].price;
        t += parseFloat(state.zone_fee) || 0;
        return t;
    }

    function updateTotal() {
        var s = '$' + fmt(calcTotal());
        $('#bwb-running-total, #bwb-submit-total').text(s);
    }

    /* ══════════════════════════════════════════════════════════════
       VALIDATION
    ══════════════════════════════════════════════════════════════ */

    function validate() {
        var e = [];
        if (!state.bin_id) e.push('Please select a bin size.');
        if (!state.delivery_date) {
            e.push('Please select a delivery date.');
        } else {
            var ts  = new Date(state.delivery_date + 'T00:00:00');
            var now = new Date();
            now.setHours(0, 0, 0, 0);
            if (ts < now) e.push('Delivery date must be today or in the future.');
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

    /* ══════════════════════════════════════════════════════════════
       NONCE REFRESH
       Called when the server tells us the nonce expired.
       Fetches a fresh nonce silently, then retries the submission.
    ══════════════════════════════════════════════════════════════ */

    function refreshNonceAndRetry( $btn, retryCount ) {
        retryCount = retryCount || 0;
        if ( retryCount >= 2 ) {
            // Give up after 2 refresh attempts
            showNotice('Session error. Please refresh the page and try again.', 'error');
            resetBtn($btn);
            return;
        }

        $.ajax({
            url:    BWB.ajax_url,
            method: 'POST',
            data:   { action: 'bwb_refresh_nonce' },
            success: function (res) {
                if (res && res.success && res.data && res.data.nonce) {
                    currentNonce = res.data.nonce;
                    // Retry the submission with the fresh nonce
                    doSubmitAjax($btn, retryCount + 1);
                } else {
                    showNotice('Could not refresh your session. Please reload the page.', 'error');
                    resetBtn($btn);
                }
            },
            error: function () {
                showNotice('Network error refreshing session. Please reload the page.', 'error');
                resetBtn($btn);
            }
        });
    }

    /* ══════════════════════════════════════════════════════════════
       SUBMIT
    ══════════════════════════════════════════════════════════════ */

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

        doSubmitAjax($btn, 0);
    }

    function doSubmitAjax($btn, retryCount) {
        $.ajax({
            url:         BWB.ajax_url,
            method:      'POST',
            traditional: true,
            timeout:     30000,   // 30 s — prevents "hung" requests looking like network errors
            data: {
                action:              'bwb_add_to_cart',
                nonce:               currentNonce,
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
                // Guard against HTML / non-JSON responses (PHP warnings, caching, etc.)
                if (typeof res !== 'object' || res === null) {
                    showNotice('Unexpected server response. Please try again or call us directly.', 'error');
                    resetBtn($btn);
                    return;
                }

                // ── Nonce expired: auto-refresh and retry ──────────────
                if (res.success && res.data && res.data.__bwb_error) {
                    var code = res.data.code || '';

                    if (code === 'nonce_expired' || code === 'nonce_missing') {
                        // Server may have already given us a fresh nonce
                        if (res.data.fresh_nonce) {
                            currentNonce = res.data.fresh_nonce;
                            doSubmitAjax($btn, retryCount + 1);
                        } else {
                            refreshNonceAndRetry($btn, retryCount);
                        }
                        return;
                    }

                    // Other logical error from the server
                    showNotice(res.data.message || 'An error occurred. Please try again.', 'error');
                    resetBtn($btn);
                    return;
                }

                // ── Normal WC error (validation, product missing, etc.) ─
                if (!res.success) {
                    showNotice((res.data && res.data.message) || 'An error occurred. Please try again.', 'error');
                    resetBtn($btn);
                    return;
                }

                // ── Success ────────────────────────────────────────────
                window.location.href = res.data.redirect;
            },

            error: function (xhr, status, err) {
                // This fires for real network/server failures (timeout, 500, etc.)
                var msg = 'Network error. Please try again.';

                if (status === 'timeout') {
                    msg = 'The request timed out. Please check your connection and try again.';
                } else if (xhr.status === 500) {
                    msg = 'Server error (500). Please try again in a moment or call us directly.';
                } else if (xhr.status === 503) {
                    msg = 'The server is temporarily unavailable. Please try again shortly.';
                } else if (xhr.status === 0) {
                    msg = 'No connection to server. Please check your internet and try again.';
                }

                showNotice(msg, 'error');
                resetBtn($btn);
            }
        });
    }

    /* ══════════════════════════════════════════════════════════════
       UTILITIES
    ══════════════════════════════════════════════════════════════ */

    function setMinDate() {
        var d = new Date();
        // Allow today as a delivery date
        $('#bwb-delivery-date').attr('min', d.toISOString().slice(0, 10));
    }

    function resetBtn($btn) {
        $btn.removeClass('is-loading').prop('disabled', false);
        $btn.find('.bwb-submit-text').text('Continue to Checkout');
    }

    function showNotice(msg, type) {
        $('#bwb-notices').html('<div class="bwb-notice bwb-notice--' + type + '">' + msg + '</div>');
    }

    function clearNotice() {
        $('#bwb-notices').empty();
    }

    function fmt(n)  { return parseFloat(n || 0).toFixed(2); }
    function esc(s)  {
        return String(s)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

})(jQuery);
