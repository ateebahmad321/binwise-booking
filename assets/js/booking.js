/**
 * BinWise Booking — Single-page form JS
 */
(function ($) {
    'use strict';

    var state = {
        bin_id: '', delivery_date: '', duration: '', delivery_time: '',
        driveway_pads: false, mattresses: false, mattress_qty: 0,
        bin_contents: [], bin_contents_other: '', bin_location: '',
        bin_location_other: '', cancellation: false,
        address_line1: '', address_city: '', address_province: 'AB',
        address_postal: '', delivery_zone: '', zone_fee: 0,
        same_billing: 'yes', billing_line1: '', billing_city: '',
        billing_province: 'AB', billing_postal: '', additional_note: '',
        agreed_terms: false,
    };

    $(function () {
        buildBinCards();
        buildDurations();
        buildDeliveryTimes();
        buildMattressQty();
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

    function buildDeliveryTimes() {
        var $c = $('#bwb-times').empty();
        $.each(BWB.times, function (key, t) {
            var badge = t.price > 0
                ? '<span class="bwb-choice__price">+$' + fmt(t.price) + '</span>'
                : '<span class="bwb-choice__price" style="color:#2e8657">Included</span>';
            $c.append(radio('delivery_time', key, esc(t.label), badge));
        });
    }

    function buildMattressQty() {
        var $c = $('#bwb-mattress-qty').empty();
        $.each(BWB.mattress_prices, function (qty, price) {
            $c.append(radio('mattress_qty', qty,
                qty + ' mattress' + (qty > 1 ? 'es' : ''),
                '<span class="bwb-choice__price">+$' + fmt(price) + '</span>'));
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
            if (name === 'duration')      state.duration = val;
            if (name === 'delivery_time') state.delivery_time = val;
            if (name === 'mattress_qty')  state.mattress_qty = parseInt(val, 10);
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
            $('#bwb-contents-other-wrap').toggle(state.bin_contents.indexOf('other') > -1);
            if (state.bin_contents.indexOf('other') === -1) state.bin_contents_other = '';
        });
        $(document).on('click', '.bwb-choice-list--checkbox .bwb-choice', function (e) {
            if (!$(e.target).is('input')) {
                var $cb = $(this).find('input[type=checkbox]');
                $cb.prop('checked', !$cb.prop('checked')).trigger('change');
            }
        });

        // Segmented
        $(document).on('change', '.bwb-segmented input[type=radio]', function () {
            var name = $(this).attr('name'), val = $(this).val();
            if (name === 'driveway_pads') state.driveway_pads = (val === 'yes');
            if (name === 'cancellation')  state.cancellation  = (val === 'yes');
            if (name === 'mattresses') {
                state.mattresses = (val === 'yes');
                $('#bwb-mattress-qty-wrap').toggle(val === 'yes');
                if (val !== 'yes') { state.mattress_qty = 0; $('#bwb-mattress-qty .bwb-choice').removeClass('is-selected'); }
            }
            if (name === 'same_billing') {
                state.same_billing = val;
                $('#bwb-billing-address-wrap').toggle(val === 'no');
            }
            updateTotal();
        });

        // Date
        $(document).on('change', '#bwb-delivery-date', function () { state.delivery_date = $(this).val(); });

        // Text inputs
        $('#bwb-addr-line1').on('input',    function () { state.address_line1    = $(this).val(); });
        $('#bwb-addr-city').on('input',     function () { state.address_city     = $(this).val(); });
        $('#bwb-addr-province').on('input', function () { state.address_province = $(this).val(); });
        $('#bwb-addr-postal').on('input',   function () { state.address_postal   = $(this).val(); });
        $('#bwb-contents-other').on('input',function () { state.bin_contents_other = $(this).val(); });
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
        if (BWB.durations[state.duration])     t += BWB.durations[state.duration].price;
        if (BWB.times[state.delivery_time])    t += BWB.times[state.delivery_time].price;
        if (state.driveway_pads)               t += 20;
        if (state.mattress_qty > 0 && BWB.mattress_prices[state.mattress_qty])
            t += BWB.mattress_prices[state.mattress_qty];
        if (state.cancellation)                t += 5;
        t += parseFloat(state.zone_fee) || 0;
        return t;
    }

    function updateTotal() {
        var s = '$' + fmt(calcTotal());
        $('#bwb-running-total, #bwb-submit-total').text(s);
    }

    /* ── Google Maps ──────────────────────────────────────────── */
    function initMapsAutocomplete() {
        if (typeof google === 'undefined' || !google.maps || !google.maps.places) return;
        var el = document.getElementById('bwb-address-search');
        if (!el) return;
        var ac = new google.maps.places.Autocomplete(el, {
            types: ['address'], componentRestrictions: { country: 'ca' }
        });
        ac.addListener('place_changed', function () {
            var place = ac.getPlace();
            if (!place || !place.address_components) return;
            var sn='', rt='', city='', prov='AB', postal='';
            place.address_components.forEach(function(c){
                var t=c.types;
                if(t.indexOf('street_number')>-1) sn=c.long_name;
                if(t.indexOf('route')>-1) rt=c.long_name;
                if(t.indexOf('locality')>-1) city=c.long_name;
                if(t.indexOf('administrative_area_level_1')>-1) prov=c.short_name;
                if(t.indexOf('postal_code')>-1) postal=c.long_name;
            });
            var line1=(sn+' '+rt).trim();
            $('#bwb-addr-line1').val(line1); state.address_line1=line1;
            $('#bwb-addr-city').val(city);   state.address_city=city;
            $('#bwb-addr-province').val(prov);state.address_province=prov;
            $('#bwb-addr-postal').val(postal);state.address_postal=postal;
            if(place.geometry) detectZone(place.geometry.location.lat(), place.geometry.location.lng());
            $('#bwb-map-status').text('✓ '+line1+', '+city);
        });
    }

    function detectZone(lat,lng){
        var zones=[
            {label:'Blue', fee:0,  pts:[[53.7155,-113.5539],[53.6888,-113.5048],[53.6888,-113.3659],[53.6168,-113.2199],[53.4384,-113.2199],[53.3961,-113.3301],[53.3959,-113.6458],[53.4532,-113.6891],[53.4532,-113.7656],[53.5302,-113.7649],[53.5318,-113.9548],[53.5901,-113.9537],[53.6313,-113.7539]]},
            {label:'Green',fee:50, pts:[[53.7499,-113.6570],[53.7503,-113.4349],[53.7513,-113.3162],[53.7249,-113.2064],[53.6416,-113.1240],[53.4039,-113.1227],[53.3577,-113.2457],[53.3389,-113.3112],[53.3389,-113.4324],[53.3391,-113.5488],[53.3387,-113.6428],[53.3387,-113.6847],[53.3376,-113.7368],[53.4915,-113.8657],[53.4932,-114.0265],[53.6090,-114.0262],[53.6696,-113.8174]]},
            {label:'Red',  fee:75, pts:[[53.6432,-114.1224],[53.8157,-113.6577],[53.8418,-113.4961],[53.8416,-113.3237],[53.7835,-113.1379],[53.6742,-113.0130],[53.3848,-113.0065],[53.2486,-113.2883],[53.2497,-113.4887],[53.2336,-113.4890],[53.2342,-113.6188],[53.2570,-113.6184],[53.2575,-113.8340],[53.3981,-113.9182],[53.4554,-114.1216]]},
        ];
        for(var i=0;i<zones.length;i++){
            if(pip(lat,lng,zones[i].pts)){
                state.delivery_zone=zones[i].label;
                state.zone_fee=zones[i].fee;
                var f=zones[i].fee===0?'Included':'+$'+zones[i].fee;
                $('#bwb-map-status').text('📍 Zone: '+zones[i].label+' — '+f);
                updateTotal(); return;
            }
        }
        state.delivery_zone='Outside Standard Area'; state.zone_fee=300;
        var ph=BWB.contact_phone||'587-405-7545';
        $('#bwb-map-status').html('⚠️ Outside standard area. Call <a href="tel:'+ph.replace(/\D/g,'')+'" style="color:var(--bwb-primary-dark)">'+ph+'</a>');
        updateTotal();
    }

    function pip(lat,lng,poly){
        var inside=false,n=poly.length,j=n-1;
        for(var i=0;i<n;i++){
            var xi=poly[i][0],yi=poly[i][1],xj=poly[j][0],yj=poly[j][1];
            if(((yi>lng)!==(yj>lng))&&(lat<(xj-xi)*(lng-yi)/(yj-yi)+xi)) inside=!inside;
            j=i;
        } return inside;
    }

    function waitForMaps(cb,n){
        n=n||0;
        if(typeof google!=='undefined'&&google.maps&&google.maps.places){cb();}
        else if(n<40){setTimeout(function(){waitForMaps(cb,n+1);},300);}
    }

    /* ── Validation ───────────────────────────────────────────── */
    function validate(){
        var e=[];
        if(!state.bin_id) e.push('Please select a bin size.');
        if(!state.delivery_date) e.push('Please select a delivery date.');
        else {
            var ts=new Date(state.delivery_date+'T00:00:00'),now=new Date();
            now.setHours(0,0,0,0);
            if(ts<now) e.push('Delivery date must be in the future.');
            if(BWB.disable_sun==='1'&&ts.getDay()===0) e.push('We do not deliver on Sundays.');
        }
        var bin=BWB.bins[state.bin_id];
        if(bin&&!bin.no_duration&&!state.duration) e.push('Please select how many days you need the bin.');
        if(!state.delivery_time)   e.push('Please select a delivery time window.');
        if(!state.bin_location)    e.push('Please select where you want the bin placed.');
        if(!state.address_line1)   e.push('Please enter your street address.');
        if(!state.address_city)    e.push('Please enter your city.');
        if(!state.agreed_terms)    e.push('Please agree to the Rental Agreement.');
        return e;
    }

    /* ── Submit ───────────────────────────────────────────────── */
    function submitBooking(){
        clearNotice();
        // Sync any un-listened fields
        state.additional_note = $('#bwb-additional-note').val();
        state.agreed_terms    = $('#bwb-agreed-terms').is(':checked');

        var errs=validate();
        if(errs.length){ showNotice(errs.join('<br>'),'error'); $('html,body').animate({scrollTop:$('#bwb-notices').offset().top-20},300); return; }

        var $btn=$('#bwb-submit');
        $btn.addClass('is-loading').prop('disabled',true);
        $btn.find('.bwb-submit-text').text('Processing…');

        $.ajax({
            url: BWB.ajax_url, method: 'POST', traditional: true,
            data: {
                action:'bwb_add_to_cart', nonce:BWB.nonce,
                bin_id:state.bin_id, delivery_date:state.delivery_date,
                duration:state.duration, delivery_time:state.delivery_time,
                driveway_pads:state.driveway_pads?'yes':'no',
                mattresses:state.mattresses?'yes':'no',
                mattress_qty:state.mattress_qty,
                'bin_contents[]':state.bin_contents,
                bin_contents_other:state.bin_contents_other,
                bin_location:state.bin_location,
                bin_location_other:state.bin_location_other,
                cancellation:state.cancellation?'yes':'no',
                address_line1:state.address_line1, address_city:state.address_city,
                address_province:state.address_province, address_postal:state.address_postal,
                delivery_zone:state.delivery_zone, zone_fee:state.zone_fee,
                same_billing:state.same_billing,
                billing_line1:state.billing_line1, billing_city:state.billing_city,
                billing_province:state.billing_province, billing_postal:state.billing_postal,
                additional_note:state.additional_note,
                agreed_terms:state.agreed_terms?'1':'',
            },
            success:function(res){
                if(res.success){ window.location.href=res.data.redirect; }
                else {
                    showNotice(res.data.message||'An error occurred. Please try again.','error');
                    $btn.removeClass('is-loading').prop('disabled',false);
                    $btn.find('.bwb-submit-text').text('Continue to Checkout');
                }
            },
            error:function(){
                showNotice('Network error. Please try again.','error');
                $btn.removeClass('is-loading').prop('disabled',false);
                $btn.find('.bwb-submit-text').text('Continue to Checkout');
            }
        });
    }

    /* ── Utilities ────────────────────────────────────────────── */
    function setMinDate(){ var d=new Date(); d.setDate(d.getDate()+1); $('#bwb-delivery-date').attr('min',d.toISOString().slice(0,10)); }
    function showNotice(msg,type){ $('#bwb-notices').html('<div class="bwb-notice bwb-notice--'+type+'">'+msg+'</div>'); }
    function clearNotice(){ $('#bwb-notices').empty(); }
    function fmt(n){ return parseFloat(n).toFixed(2); }
    function esc(s){ return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }

})(jQuery);
