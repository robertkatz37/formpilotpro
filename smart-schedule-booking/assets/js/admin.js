jQuery(document).ready(function($){

    // ── Color pickers ──────────────────────────────────────────
    $('.ssb-color-picker').wpColorPicker({
        change: function(){ setTimeout(updatePreview, 50); },
        clear:  function(){ setTimeout(updatePreview, 50); }
    });

    function updatePreview() {
        var p  = $('[name="ssb_primary_color"]').val()     || '#667eea';
        var s  = $('[name="ssb_secondary_color"]').val()   || '#764ba2';
        var bt = $('[name="ssb_button_text_color"]').val() || '#ffffff';
        var br = $('[name="ssb_border_radius"]').val()     || '12';
        $('#ssb-preview-btn').css({
            background:   'linear-gradient(135deg,' + p + ',' + s + ')',
            color:        bt,
            borderRadius: br + 'px'
        });
    }

    $('[name="ssb_border_radius"]').on('input', updatePreview);

    // ── Port quick-select buttons ──────────────────────────────
    $('.ssb-port-btn').on('click', function(){
        $('#ssb_smtp_port').val($(this).data('port'));
        $('#ssb_smtp_encryption').val($(this).data('enc'));
    });

    // ── Namecheap auto-fill ────────────────────────────────────
    $('#ssb-autofill-namecheap').on('click', function(){
        $('#ssb_smtp_host').val('mail.privateemail.com');
        $('#ssb_smtp_port').val('587');
        $('#ssb_smtp_encryption').val('tls');
        $('[name="ssb_smtp_enabled"]').prop('checked', true);
        $(this).text('Filled! Now enter your email + password below, then Save.').prop('disabled', true);
    });

    // ── Shared result display helper ──────────────────────────
    function showResult($el, type, msg) {
        var isOk   = type === 'success';
        var bg     = isOk ? '#ecfdf5' : '#fef2f2';
        var border = isOk ? '#059669' : '#dc2626';
        var color  = isOk ? '#065f46' : '#991b1b';
        $el.addClass('ssb-result-banner').css({
            display:       'block',
            background:    bg,
            borderLeft:    '3px solid ' + border,
            color:         color
        }).html(msg);
    }

    // ── SMTP test email ────────────────────────────────────────
    $('#ssb-test-smtp').on('click', function(){
        var $btn    = $(this);
        var $result = $('#ssb-test-result');
        var toAddr  = $('#ssb-test-email-addr').val().trim();

        if (!toAddr) {
            showResult($result, 'error', 'Please enter an email address to send the test to.');
            return;
        }

        $btn.prop('disabled', true).text('Sending…');
        $result.hide();

        $.post(ssbAdmin.ajaxUrl, {
            action: 'ssb_test_smtp',
            nonce:  ssbAdmin.nonce,
            to:     toAddr
        })
        .done(function(resp){
            $btn.prop('disabled', false).html('Send Test Email');
            if (resp.success) {
                showResult($result, 'success', resp.data.message);
            } else {
                var msg = (resp.data && resp.data.message) ? resp.data.message : 'Unknown error.';
                showResult($result, 'error', msg);
            }
        })
        .fail(function(xhr){
            $btn.prop('disabled', false).html('Send Test Email');
            showResult($result, 'error', 'Network error (HTTP ' + xhr.status + '). Make sure you saved settings first, then try again.');
        });
    });

    // ── Google Calendar connection test ────────────────────────
    $('#ssb-test-google').on('click', function(){
        var $btn    = $(this);
        var $result = $('#ssb-google-test-result');

        $btn.prop('disabled', true).text('Testing…');
        $result.hide();

        $.post(ssbAdmin.ajaxUrl, {
            action: 'ssb_test_google',
            nonce:  ssbAdmin.nonce
        })
        .done(function(resp){
            $btn.prop('disabled', false).html('Test Connection');
            if (resp.success) {
                showResult($result, 'success', resp.data.message);
            } else {
                var msg = (resp.data && resp.data.message) ? resp.data.message : 'Unknown error.';
                showResult($result, 'error', msg);
            }
        })
        .fail(function(xhr){
            $btn.prop('disabled', false).html('Test Connection');
            showResult($result, 'error', 'Network error (HTTP ' + xhr.status + ').');
        });
    });


    // ── FormPilot instant license actions ─────────────────────
    function renderLicenseState(data, message, ok) {
        if (!data) return;
        $('#ssb-license-domain').text(data.domain || '—');
        $('#ssb-license-plan').text(data.plan || '—');
        $('#ssb-license-expires').text(data.expires_at || 'Never');
        $('#ssb-license-last-check').text(data.last_check || '—');
        $('#ssb-license-state-text').text(data.active ? 'License active' : 'License not active');
        $('#ssb-license-state-message').text(message || (data.active ? 'Your license is active on this website.' : 'Activate a license to unlock Pro features.'));
        $('#ssb-license-state-card').toggleClass('is-active', !!data.active).toggleClass('is-inactive', !data.active);
        var $features = $('#ssb-license-features').empty();
        $.each(data.features || {}, function(feature, enabled){
            if (enabled) $('<span/>').text(feature.replace(/_/g,' ').replace(/\b\w/g,function(m){return m.toUpperCase();})).appendTo($features);
        });
        var $status = $('.ssb-license-status');
        $status.toggleClass('active', !!data.active).toggleClass('inactive', !data.active).text(data.active ? '● Active' : '● Not active');
        if ($('#ssb-license-deactivate').length) $('#ssb-license-deactivate').toggle(!!data.has_key);
    }
    function licenseAjax(action, extra, $button) {
        var $indicator = $('#ssb-license-live-indicator');
        $button && $button.prop('disabled', true);
        $indicator.removeClass('is-ok is-error').addClass('is-busy').text('Updating…');
        var payload = $.extend({action:'ssb_license_action', license_action:action, nonce:ssbAdmin.nonce}, extra || {});
        return $.post(ssbAdmin.ajaxUrl, payload).done(function(resp){
            var data = resp.data || {};
            if (resp.success) {
                renderLicenseState(data.state || data, data.message || 'License updated.', true);
                $indicator.removeClass('is-busy').addClass('is-ok').text('Updated just now');
                var $notice = $('.ssb-license-page > .notice').first();
                if (!$notice.length) $notice = $('<div class="notice is-dismissible"><p></p></div>').insertAfter('.ssb-license-hero');
                $notice.removeClass('notice-error notice-warning').addClass('notice-success').show().find('p').text(data.message || 'License updated.');
            } else {
                var msg = data.message || 'License action failed.';
                renderLicenseState(data.state || {}, msg, false);
                $indicator.removeClass('is-busy').addClass('is-error').text('Update failed');
                var $notice = $('.ssb-license-page > .notice').first();
                if (!$notice.length) $notice = $('<div class="notice is-dismissible"><p></p></div>').insertAfter('.ssb-license-hero');
                $notice.removeClass('notice-success notice-warning').addClass('notice-error').show().find('p').text(msg);
            }
        }).fail(function(xhr){
            var msg='The license request failed. Please try again.';
            try { if(xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) msg=xhr.responseJSON.data.message; } catch(e){}
            $indicator.removeClass('is-busy').addClass('is-error').text('Update failed');
            var $notice=$('.ssb-license-page > .notice').first();
            if(!$notice.length)$notice=$('<div class="notice is-dismissible"><p></p></div>').insertAfter('.ssb-license-hero');
            $notice.removeClass('notice-success notice-warning').addClass('notice-error').show().find('p').text(msg);
        }).always(function(){ $button && $button.prop('disabled', false); });
    }
    $(document).on('submit', '#ssb-license-activate-form', function(e){
        e.preventDefault();
        licenseAjax('activate', {license_key:$('#ssb-license-key').val()}, $('#ssb-license-activate'));
    });
    $(document).on('click', '#ssb-license-check', function(){ licenseAjax('check', {}, $(this)); });
    $(document).on('click', '#ssb-license-deactivate', function(){
        if(!window.confirm('Deactivate the FormPilot license for this website? Your saved license key will remain available so you can activate it again later.')) return;
        licenseAjax('deactivate', {}, $(this));
    });

    // Keep the visible license status fresh while the License screen is open.
    if ($('.ssb-license-page').length) {
        setInterval(function(){
            if (document.hidden || $('#ssb-license-activate').is(':disabled')) return;
            licenseAjax('check', {}, null);
        }, 60000);
    }

    // ── Settings tabs: switch without a full WordPress admin reload ──
    $(document).on('click', '.ssb-license-page ~ * .nav-tab-wrapper a, .ssb-settings-page .nav-tab-wrapper a', function(e){
        // Kept as a safe hook for future settings containers.
    });
    $(document).on('click', '.ssb-settings-page .nav-tab-wrapper a', function(e){
        e.preventDefault();
        var url=this.href, $link=$(this), $panel=$('.ssb-settings-page .ssb-panel');
        if(!$panel.length) { window.location.href=url; return; }
        $link.addClass('is-loading'); $panel.css('opacity','.55');
        $.get(url).done(function(html){
            var doc=new DOMParser().parseFromString(html,'text/html');
            var incoming=doc.querySelector('.ssb-settings-page .ssb-panel');
            var incomingNav=doc.querySelector('.ssb-settings-page .nav-tab-wrapper');
            if(!incoming || !incomingNav) { window.location.href=url; return; }
            $panel.html(incoming.innerHTML);
            $('.ssb-settings-page .nav-tab-wrapper').html(incomingNav.innerHTML);
            window.history.pushState({formpilotSettingsTab:true},'',url);
            $(document).trigger('formpilot:settings-tab-loaded');
            if(window.jQuery && $.fn.wpColorPicker) $('.ssb-color-picker').wpColorPicker();
        }).fail(function(){ window.location.href=url; }).always(function(){ $link.removeClass('is-loading'); $panel.css('opacity','1'); });
    });

});
