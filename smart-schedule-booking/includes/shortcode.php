<?php
if ( ! defined( 'ABSPATH' ) ) exit;

add_shortcode( 'smart_schedule_booking', 'ssb_render_form' );

function ssb_render_form( $atts = [] ) {
    $primary     = get_option('ssb_primary_color',      '#0a0a0a');
    $secondary   = get_option('ssb_secondary_color',    '#1a1a2e');
    $accent      = get_option('ssb_accent_color',       '#6366f1');
    $btn_text    = get_option('ssb_button_text_color',  '#ffffff');
    $radius      = get_option('ssb_border_radius',       '12');
    $font        = get_option('ssb_font_family',        'inherit');
    $logo_ltr    = get_option('ssb_logo_letter',        'P');
    $team_lbl    = get_option('ssb_team_label',         'Team');
    $_label      = trim( get_option('ssb_event_label',      'Strategy Session') );
    $_brand      = trim( get_option('ssb_event_brand_name', get_bloginfo('name')) );
    $evt_title   = $_label . ' — ' . $_brand;
    $evt_dur     = get_option('ssb_event_duration',     '30 Minutes');
    $evt_desc    = get_option('ssb_event_description',  'Unlock your practice\'s full potential with this 30-minute strategy session.');
    $ajax_url    = admin_url('admin-ajax.php');
    $pay_enabled = get_option('ssb_payment_enabled', '0') === '1';
    $price       = get_option('ssb_booking_price', '0');
    $currency    = get_option('ssb_booking_currency', 'USD');
    $stripe_pk   = get_option('ssb_stripe_publishable_key', '');
    $custom_fields = get_option('ssb_smart_booking_custom_fields', []);
    if (!is_array($custom_fields)) $custom_fields = [];
    $smart_ui = get_option('ssb_smart_booking_editor_settings', []);
    if (!is_array($smart_ui)) $smart_ui = [];
    $smart_defaults = ['display_title'=>'Strategy Session','description'=>'Choose a time, then enter your details to confirm the booking.','first_label'=>'First Name','last_label'=>'Last Name','email_label'=>'Email','phone_label'=>'Phone','practice_label'=>'Practice Type','practice_options'=>['Full-time','Part-time'],'timezone_label'=>'Your Timezone','times_label'=>'Available Times','submit_label'=>'Confirm Booking','success_title'=>"You're all set!",'success_message'=>"Your session has been confirmed. We've sent a confirmation email with all the details."];
    $smart_ui = array_merge($smart_defaults,$smart_ui);
    $smart_style=['style_primary'=>'#0a0a0a','style_accent'=>'#6366f1','style_bg'=>'#ffffff','style_text'=>'#111827','style_input_radius'=>'10','style_button_radius'=>'10']; $smart_ui=array_merge($smart_style,$smart_ui);

    ob_start();
    ?>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:ital,wght@0,400;0,500;0,600;0,700;0,800;1,400&display=swap" rel="stylesheet">

<style>
:root {
    --ssb-primary:   <?php echo esc_attr($primary); ?>;
    --ssb-secondary: <?php echo esc_attr($secondary); ?>;
    --ssb-accent:    <?php echo esc_attr($accent); ?>;
    --ssb-btn-text:  <?php echo esc_attr($btn_text); ?>;
    --ssb-radius:    <?php echo esc_attr($radius); ?>px;
    --ssb-font:      'Plus Jakarta Sans', <?php echo esc_attr($font); ?>;
    --ssb-smart-primary: <?php echo esc_attr($smart_ui['style_primary']); ?>;
    --ssb-smart-accent: <?php echo esc_attr($smart_ui['style_accent']); ?>;
    --ssb-smart-bg: <?php echo esc_attr($smart_ui['style_bg']); ?>;
    --ssb-smart-text: <?php echo esc_attr($smart_ui['style_text']); ?>;
    --ssb-smart-input-radius: <?php echo max(0,min(30,(int)$smart_ui['style_input_radius'])); ?>px;
    --ssb-smart-button-radius: <?php echo max(0,min(30,(int)$smart_ui['style_button_radius'])); ?>px;
    --ssb-display:   'Plus Jakarta Sans', Georgia, serif;
}

.ssb-wrap { font-family: var(--ssb-font); width: 100%; color:var(--ssb-smart-text); }
.ssb-wrap .ssb-input,.ssb-wrap .ssb-select,.ssb-wrap .ssb-textarea{border-radius:var(--ssb-smart-input-radius);}
.ssb-wrap .ssb-btn{border-radius:var(--ssb-smart-button-radius);background:var(--ssb-smart-accent);}
.ssb-wrap .ssb-radio-card.is-selected,.ssb-wrap .ssb-radio-card:hover{border-color:var(--ssb-smart-accent);}
.ssb-wrap .ssb-left{background:linear-gradient(160deg,var(--ssb-smart-primary) 0%,var(--ssb-secondary) 100%);}
.ssb-wrap .ssb-success-ring{border-color:var(--ssb-smart-accent);}
.ssb-wrap * { box-sizing: border-box; }

/* ── Outer Shell ── */
.ssb-shell {
    display: flex;
    max-width: 1060px;
    margin: 2.5rem auto;
    background: #ffffff;
    border-radius: 24px;
    box-shadow: 0 8px 48px rgba(0,0,0,0.1), 0 1px 3px rgba(0,0,0,0.08);
    overflow: hidden;
    min-height: 600px;
}

/* ── Left Panel ── */
.ssb-left {
    width: 300px;
    min-width: 300px;
    background: linear-gradient(160deg, var(--ssb-primary) 0%, var(--ssb-secondary) 100%);
    padding: 2.5rem 2rem;
    color: #fff;
    display: flex;
    flex-direction: column;
    position: relative;
    overflow: hidden;
}
.ssb-left::before {
    content: '';
    position: absolute;
    top: -80px; right: -80px;
    width: 220px; height: 220px;
    background: rgba(255,255,255,0.04);
    border-radius: 50%;
}
.ssb-left::after {
    content: '';
    position: absolute;
    bottom: -60px; left: -40px;
    width: 180px; height: 180px;
    background: rgba(255,255,255,0.03);
    border-radius: 50%;
}
.ssb-logo {
    width: 48px; height: 48px;
    background: rgba(255,255,255,0.15);
    border-radius: 14px;
    display: flex; align-items: center; justify-content: center;
    font-weight: 700; font-size: 1.4rem;
    margin-bottom: 1.5rem;
    border: 1px solid rgba(255,255,255,0.2);
    position: relative; z-index: 1;
}
.ssb-team-tag {
    font-size: 0.7rem;
    text-transform: uppercase;
    letter-spacing: 2px;
    opacity: 0.6;
    font-weight: 600;
    position: relative; z-index: 1;
}
.ssb-left-title {
    font-family: var(--ssb-display);
    font-size: 1.45rem;
    font-weight: 600;
    line-height: 1.35;
    margin: 0.6rem 0 1.75rem;
    position: relative; z-index: 1;
}
.ssb-meta-row {
    display: flex; align-items: flex-start; gap: 0.75rem;
    background: rgba(255,255,255,0.1);
    border: 1px solid rgba(255,255,255,0.12);
    border-radius: 10px;
    padding: 0.75rem 0.875rem;
    margin-bottom: 0.65rem;
    font-size: 0.82rem;
    line-height: 1.5;
    position: relative; z-index: 1;
    backdrop-filter: blur(4px);
}
.ssb-meta-icon { flex-shrink: 0; width: 15px; height: 15px; margin-top: 2px; opacity: 0.85; }
.ssb-meta-text { word-break: break-word; }

.ssb-price-badge {
    display: inline-flex; align-items: center; gap: 6px;
    background: var(--ssb-accent);
    color: #fff;
    font-size: 0.85rem; font-weight: 700;
    padding: 0.4rem 0.9rem;
    border-radius: 100px;
    margin-top: 0.5rem;
    position: relative; z-index: 1;
}

.ssb-divider {
    height: 1px; background: rgba(255,255,255,0.15);
    margin: 1.25rem 0;
    position: relative; z-index: 1;
}
.ssb-desc {
    font-size: 0.82rem; line-height: 1.75;
    opacity: 0.8;
    position: relative; z-index: 1;
}
.ssb-desc p { margin: 0 0 0.75em; }
.ssb-desc p:last-child { margin-bottom: 0; }

/* ── Right Panel ── */
.ssb-right {
    flex: 1;
    padding: 2.5rem 2.5rem;
    display: flex;
    flex-direction: column;
    min-width: 0;
    background: #fafafa;
}

/* ── Step headings ── */
.ssb-step-head {
    font-family: var(--ssb-display);
    font-size: 1.4rem; font-weight: 600;
    color: #0f0f0f;
    margin: 0 0 1.75rem;
}
.ssb-step-sub {
    font-size: 0.85rem; color: #6b7280;
    margin-top: -1.3rem; margin-bottom: 1.75rem;
}

/* ── Timezone ── */
.ssb-tz-block {
    background: #fff;
    border: 1.5px solid #e5e7eb;
    border-radius: var(--ssb-radius);
    padding: 1rem 1.1rem;
    margin-bottom: 1.5rem;
}
.ssb-tz-label {
    font-size: 0.78rem; font-weight: 700;
    text-transform: uppercase; letter-spacing: 0.8px;
    color: #374151; margin-bottom: 0.5rem; display: block;
}
.ssb-select {
    width: 100%; border: none; background: transparent;
    font-size: 0.92rem; font-family: var(--ssb-font);
    color: #0f0f0f; cursor: pointer; outline: none;
    padding: 0.2rem 0;
}

/* ── Calendar ── */
.ssb-cal-nav {
    display: flex; justify-content: space-between; align-items: center;
    margin-bottom: 1.1rem;
}
.ssb-month-lbl { font-weight: 700; font-size: 1rem; color: #0f0f0f; }
.ssb-nav {
    width: 34px; height: 34px;
    border: 1.5px solid #e5e7eb; background: #fff;
    border-radius: 8px; cursor: pointer;
    display: flex; align-items: center; justify-content: center;
    font-size: 1rem; transition: all 0.15s;
    color: #374151; font-weight: 700;
}
.ssb-nav:hover { background: #0f0f0f; border-color: #0f0f0f; color: #fff; }

.ssb-cal {
    display: grid; grid-template-columns: repeat(7, 1fr);
    gap: 4px; margin-bottom: 1.5rem;
}
.ssb-wh {
    text-align: center; font-size: 0.67rem; font-weight: 700;
    color: #9ca3af; padding: 0.3rem; text-transform: uppercase;
    letter-spacing: 0.5px;
}
.ssb-day {
    aspect-ratio: 1; display: flex; align-items: center; justify-content: center;
    border-radius: 8px; cursor: pointer; font-size: 0.82rem; font-weight: 500;
    transition: all 0.15s; border: 1.5px solid transparent;
    color: #374151; background: transparent;
}
.ssb-day.has-slots { background: #fff; border-color: #e5e7eb; }
.ssb-day.has-slots:hover { border-color: var(--ssb-accent); background: rgba(99,102,241,0.06); color: var(--ssb-accent); }
.ssb-day.is-today { border-color: var(--ssb-accent); color: var(--ssb-accent); font-weight: 700; }
.ssb-day.is-past { color: #d1d5db !important; cursor: not-allowed; background: transparent; border-color: transparent; }
.ssb-day.is-weekend { color: #d1d5db !important; cursor: not-allowed; background: transparent; border-color: transparent; }
.ssb-day.is-weekend:hover { background: transparent; border-color: transparent; color: #d1d5db !important; }
.ssb-day.is-selected {
    background: #0f0f0f !important;
    border-color: #0f0f0f !important;
    color: #fff !important;
    font-weight: 700;
    box-shadow: 0 4px 12px rgba(0,0,0,0.2);
}

/* ── Time slots ── */
.ssb-times-wrap { display: none; }
.ssb-times-wrap.visible { display: block; }
.ssb-times-lbl {
    font-size: 0.78rem; font-weight: 700;
    text-transform: uppercase; letter-spacing: 0.8px;
    color: #9ca3af; margin-bottom: 0.75rem;
}
.ssb-times-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(110px, 1fr));
    gap: 0.5rem;
}
.ssb-slot {
    padding: 0.65rem 0.5rem;
    border: 1.5px solid #e5e7eb;
    border-radius: 8px; text-align: center;
    cursor: pointer; font-weight: 600; font-size: 0.82rem;
    background: #fff; color: #374151;
    transition: all 0.15s; position: relative;
    font-family: var(--ssb-font);
}
.ssb-slot:hover:not(.taken):not(.past) {
    border-color: var(--ssb-accent); background: rgba(99,102,241,0.06); color: var(--ssb-accent);
}
.ssb-slot.selected {
    background: #0f0f0f; border-color: #0f0f0f; color: #fff;
    box-shadow: 0 4px 12px rgba(0,0,0,0.18);
}
.ssb-slot.taken, .ssb-slot.past {
    background: #fff5f5; border-color: #ef4444; color: #991b1b; cursor: not-allowed; border-width: 2px;
}
.ssb-slot-tag {
    display: block; font-size: 0.6rem; font-weight: 800;
    text-transform: uppercase; letter-spacing: 0.8px;
    margin-top: 3px; color: #dc2626;
}

/* ── Steps visibility ── */
.ssb-step { display: none; }
.ssb-step.active { display: flex; flex-direction: column; flex: 1; }

/* ── Back button ── */
.ssb-back {
    display: inline-flex; align-items: center; gap: 6px;
    background: none; border: none; cursor: pointer;
    font-size: 0.82rem; font-weight: 600; color: #6b7280;
    padding: 0; margin-bottom: 1.5rem; font-family: var(--ssb-font);
    transition: color 0.15s;
}
.ssb-back:hover { color: #0f0f0f; }

/* ── Form ── */
.ssb-fields { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
.ssb-field-full { grid-column: 1 / -1; }
.ssb-field {}
.ssb-label {
    display: block; font-size: 0.78rem; font-weight: 700;
    text-transform: uppercase; letter-spacing: 0.6px;
    color: #374151; margin-bottom: 0.45rem;
}
.ssb-req { color: var(--ssb-accent); }
.ssb-input, .ssb-textarea {
    width: 100%; padding: 0.85rem 1rem;
    border: 1.5px solid #e5e7eb; border-radius: 10px;
    font-size: 0.9rem; font-family: var(--ssb-font);
    background: #fff; color: #0f0f0f;
    transition: border-color 0.15s, box-shadow 0.15s;
    outline: none;
}
.ssb-input:focus, .ssb-textarea:focus {
    border-color: var(--ssb-accent);
    box-shadow: 0 0 0 3px rgba(99,102,241,0.1);
}
.ssb-textarea { resize: vertical; min-height: 90px; }

/* ── Radio group ── */
.ssb-radios { display: flex; flex-wrap: wrap; gap: 0.5rem; margin-top: 0.35rem; }
.ssb-radio-card {
    display: flex; align-items: center; gap: 8px;
    background: #fff; border: 1.5px solid #e5e7eb;
    border-radius: 8px; padding: 0.6rem 0.85rem;
    cursor: pointer; font-size: 0.85rem; font-weight: 500; color: #374151;
    transition: all 0.15s;
}
.ssb-radio-card input { display: none; }
.ssb-radio-card:hover { border-color: var(--ssb-accent); }
.ssb-radio-card.chosen { border-color: var(--ssb-accent); background: rgba(99,102,241,0.06); color: var(--ssb-accent); font-weight: 600; }
.ssb-radio-dot {
    width: 14px; height: 14px;
    border: 2px solid currentColor; border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    flex-shrink: 0;
}
.ssb-radio-dot::after {
    content: ''; width: 6px; height: 6px;
    background: var(--ssb-accent); border-radius: 50%;
    display: none;
}
.ssb-radio-card.chosen .ssb-radio-dot::after { display: block; }

/* ── Payment card section ── */
.ssb-payment-block {
    background: #fff; border: 1.5px solid #e5e7eb;
    border-radius: 12px; padding: 1.25rem 1.25rem;
    margin-top: 1.25rem;
}
.ssb-payment-title {
    font-size: 0.78rem; font-weight: 700;
    text-transform: uppercase; letter-spacing: 0.6px;
    color: #374151; margin-bottom: 1rem;
    display: flex; align-items: center; gap: 8px;
}
.ssb-payment-lock {
    width: 14px; height: 14px; color: #6b7280;
}
#ssb-card-element {
    padding: 0.85rem 1rem;
    border: 1.5px solid #e5e7eb; border-radius: 10px;
    background: #fff; transition: border-color 0.15s;
}
#ssb-card-element.StripeElement--focus {
    border-color: var(--ssb-accent);
    box-shadow: 0 0 0 3px rgba(99,102,241,0.1);
}
.ssb-card-errors {
    color: #ef4444; font-size: 0.8rem; margin-top: 0.5rem; min-height: 1.2em;
}
.ssb-payment-total {
    display: flex; justify-content: space-between; align-items: center;
    padding: 0.75rem 0; border-top: 1px solid #f3f4f6; margin-top: 1rem;
    font-size: 0.9rem;
}
.ssb-payment-total strong { font-size: 1.1rem; color: #0f0f0f; }
.ssb-secure-note {
    display: flex; align-items: center; gap: 5px;
    font-size: 0.72rem; color: #9ca3af; margin-top: 0.5rem;
}

/* ── CTA Button ── */
.ssb-btn {
    width: 100%; margin-top: 1.5rem;
    background: #0f0f0f; color: #fff;
    border: none; border-radius: 10px;
    padding: 0.95rem 1.5rem;
    font-size: 0.9rem; font-weight: 700;
    font-family: var(--ssb-font);
    cursor: pointer; transition: all 0.2s;
    display: flex; align-items: center; justify-content: center; gap: 8px;
    letter-spacing: 0.2px;
}
.ssb-btn:hover:not(:disabled) { background: var(--ssb-accent); transform: translateY(-1px); box-shadow: 0 8px 24px rgba(99,102,241,0.3); }
.ssb-btn:disabled { opacity: 0.65; cursor: not-allowed; transform: none; }

/* ── Spinner ── */
.ssb-spin {
    width: 16px; height: 16px;
    border: 2px solid rgba(255,255,255,0.3);
    border-top-color: #fff;
    border-radius: 50%;
    animation: ssb-rotate 0.7s linear infinite;
}
@keyframes ssb-rotate { to { transform: rotate(360deg); } }

/* ── Success ── */
.ssb-success {
    flex: 1; display: flex; flex-direction: column;
    align-items: center; justify-content: center;
    text-align: center; padding: 3rem 2rem;
}
.ssb-success-ring {
    width: 80px; height: 80px;
    background: linear-gradient(135deg, #10b981, #059669);
    border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    margin-bottom: 1.5rem;
    box-shadow: 0 12px 30px rgba(16,185,129,0.3);
    animation: ssb-pop 0.5s cubic-bezier(0.175,0.885,0.32,1.275) both;
}
@keyframes ssb-pop { from { transform: scale(0); opacity: 0; } to { transform: scale(1); opacity: 1; } }
.ssb-check { width: 36px; height: 36px; color: #fff; }
.ssb-success-title {
    font-family: var(--ssb-display);
    font-size: 1.75rem; font-weight: 600;
    color: #0f0f0f; margin-bottom: 0.75rem;
}
.ssb-success-body { color: #6b7280; font-size: 0.9rem; line-height: 1.8; max-width: 360px; }
.ssb-meet-btn {
    display: inline-flex; align-items: center; gap: 10px;
    background: linear-gradient(135deg, #0f9d58, #34a853);
    color: #fff; padding: 0.85rem 1.5rem;
    border-radius: 10px; text-decoration: none;
    font-weight: 700; font-size: 0.9rem;
    margin-top: 1.5rem;
    box-shadow: 0 6px 20px rgba(15,157,88,0.3);
    transition: transform 0.2s, box-shadow 0.2s;
}
.ssb-meet-btn:hover { transform: translateY(-2px); box-shadow: 0 10px 28px rgba(15,157,88,0.4); }
.ssb-paid-badge {
    display: inline-flex; align-items: center; gap: 6px;
    background: rgba(16,185,129,0.1); color: #059669;
    font-size: 0.78rem; font-weight: 700;
    padding: 0.35rem 0.75rem; border-radius: 100px;
    border: 1px solid rgba(16,185,129,0.25);
    margin-top: 1rem;
}

/* ── Responsive ── */
@media (max-width: 860px) {
    .ssb-shell { flex-direction: column; margin: 1rem 0.75rem; border-radius: 18px; }
    .ssb-left { width: 100%; min-width: unset; padding: 2rem 1.75rem; border-radius: 18px 18px 0 0; }
    .ssb-right { padding: 1.75rem 1.5rem; background: #fafafa; }
    .ssb-fields { grid-template-columns: 1fr; }
    .ssb-field-full { grid-column: 1; }
}
@media (max-width: 560px) {
    .ssb-shell { margin: 0.5rem 0.375rem; border-radius: 14px; }
    .ssb-left { padding: 1.5rem 1.25rem; border-radius: 14px 14px 0 0; }
    .ssb-right { padding: 1.25rem 1rem; }
    .ssb-left-title { font-size: 1.2rem; }
    .ssb-step-head { font-size: 1.2rem; }
    .ssb-times-grid { grid-template-columns: repeat(2, 1fr); }
}
</style>

<div class="ssb-wrap">
<div class="ssb-shell">

    <!-- Left Panel -->
    <div class="ssb-left">
        <div class="ssb-logo"><?php echo esc_html($logo_ltr); ?></div>
        <div class="ssb-team-tag"><?php echo esc_html($team_lbl); ?></div>
        <h2 class="ssb-left-title"><?php echo esc_html($evt_title); ?></h2>

        <div class="ssb-meta-row">
            <svg class="ssb-meta-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            <span class="ssb-meta-text"><?php echo esc_html($evt_dur); ?></span>
        </div>
        <div class="ssb-meta-row" id="ssb-sidebar-datetime">
            <svg class="ssb-meta-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
            <span class="ssb-meta-text">Select date &amp; time</span>
        </div>
        <div class="ssb-meta-row">
            <svg class="ssb-meta-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3.055 11H5a2 2 0 012 2v1a2 2 0 002 2 2 2 0 012 2v2.945M8 3.935V5.5A2.5 2.5 0 0010.5 8h.5a2 2 0 012 2 2 2 0 104 0 2 2 0 012-2h1.064M15 20.488V18a2 2 0 012-2h3.064M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            <span class="ssb-meta-text" id="ssb-sidebar-tz">Select your timezone</span>
        </div>

        <?php if ( $pay_enabled && floatval($price) > 0 ) : ?>
        <div class="ssb-price-badge">
            <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/></svg>
            <?php echo esc_html($currency . ' ' . number_format(floatval($price), 2)); ?>
        </div>
        <?php endif; ?>

        <div class="ssb-divider"></div>
        <div class="ssb-desc"><?php echo wp_kses_post( wpautop( $evt_desc ) ); ?></div>
    </div>

    <!-- Right Panel -->
    <div class="ssb-right">

        <!-- Step 1: Date & Time -->
        <div class="ssb-step active" id="ssb-step-1">
            <h2 class="ssb-step-head">Select Date &amp; Time</h2>

            <div class="ssb-tz-block">
                <label class="ssb-tz-label" for="ssb-tz">🌍 <?php echo esc_html($smart_ui['timezone_label']); ?></label>
                <select id="ssb-tz" class="ssb-select">
                    <option value="">— Select your timezone —</option>
                    <optgroup label="🇺🇸 United States">
                        <option value="America/New_York">Eastern Time (ET)</option>
                        <option value="America/Chicago">Central Time (CT)</option>
                        <option value="America/Denver">Mountain Time (MT)</option>
                        <option value="America/Phoenix">Mountain Time – Phoenix</option>
                        <option value="America/Los_Angeles">Pacific Time (PT)</option>
                        <option value="America/Anchorage">Alaska (AKT)</option>
                        <option value="Pacific/Honolulu">Hawaii (HST)</option>
                    </optgroup>
                    <optgroup label="🇬🇧 UK &amp; Europe">
                        <option value="Europe/London">London (GMT/BST)</option>
                        <option value="Europe/Paris">Paris / Berlin (CET)</option>
                        <option value="Europe/Moscow">Moscow (MSK)</option>
                    </optgroup>
                    <optgroup label="🌏 Asia &amp; Pacific">
                        <option value="Asia/Dubai">Dubai (GST)</option>
                        <option value="Asia/Karachi">Karachi (PKT)</option>
                        <option value="Asia/Kolkata">India (IST)</option>
                        <option value="Asia/Singapore">Singapore (SGT)</option>
                        <option value="Asia/Tokyo">Tokyo (JST)</option>
                        <option value="Australia/Sydney">Sydney (AEST)</option>
                    </optgroup>
                    <optgroup label="🌎 Americas">
                        <option value="America/Toronto">Toronto (ET)</option>
                        <option value="America/Mexico_City">Mexico City (CST)</option>
                        <option value="America/Sao_Paulo">São Paulo (BRT)</option>
                        <option value="America/Buenos_Aires">Buenos Aires (ART)</option>
                    </optgroup>
                    <optgroup label="🌐 UTC">
                        <option value="UTC">UTC</option>
                    </optgroup>
                </select>
            </div>

            <div class="ssb-cal-nav">
                <button class="ssb-nav" id="ssb-prev">&#8249;</button>
                <div class="ssb-month-lbl" id="ssb-month-lbl">—</div>
                <button class="ssb-nav" id="ssb-next">&#8250;</button>
            </div>
            <div class="ssb-cal" id="ssb-cal"></div>

            <div class="ssb-times-wrap" id="ssb-times-wrap">
                <div class="ssb-times-lbl"><?php echo esc_html($smart_ui['times_label']); ?></div>
                <div class="ssb-times-grid" id="ssb-times-grid"></div>
            </div>
        </div>

        <!-- Step 2: Details + Payment -->
        <div class="ssb-step" id="ssb-step-2">
            <button class="ssb-back" id="ssb-back">
                <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M15 19l-7-7 7-7"/></svg>
                Back
            </button>
            <h2 class="ssb-step-head"><?php echo esc_html($smart_ui['display_title']); ?></h2>
            <p class="ssb-step-sub"><?php echo esc_html($smart_ui['description']); ?></p>

            <div class="ssb-fields" id="ssb-form-fields">
                <div class="ssb-field">
                    <label class="ssb-label"><?php echo esc_html($smart_ui['first_label']); ?> <span class="ssb-req">*</span></label>
                    <input type="text" id="ssb-first" class="ssb-input" placeholder="Jane">
                </div>
                <div class="ssb-field">
                    <label class="ssb-label"><?php echo esc_html($smart_ui['last_label']); ?> <span class="ssb-req">*</span></label>
                    <input type="text" id="ssb-last" class="ssb-input" placeholder="Smith">
                </div>
                <div class="ssb-field">
                    <label class="ssb-label"><?php echo esc_html($smart_ui['email_label']); ?> <span class="ssb-req">*</span></label>
                    <input type="email" id="ssb-email" class="ssb-input" placeholder="jane@example.com">
                </div>
                <div class="ssb-field">
                    <label class="ssb-label"><?php echo esc_html($smart_ui['phone_label']); ?> <span class="ssb-req">*</span></label>
                    <input type="tel" id="ssb-phone" class="ssb-input" placeholder="+1 234 567 8900">
                </div>
                <div class="ssb-field ssb-field-full">
                    <label class="ssb-label"><?php echo esc_html($smart_ui['practice_label']); ?> <span class="ssb-req">*</span></label>
                    <div class="ssb-radios">
                        <?php foreach ((array)$smart_ui['practice_options'] as $pi=>$popt): $popt=sanitize_text_field($popt); if($popt==='') continue; $pval=sanitize_title($popt); ?>
                        <label class="ssb-radio-card" data-val="<?php echo esc_attr($pval); ?>">
                            <input type="radio" name="ssb_practice" value="<?php echo esc_attr($pval); ?>">
                            <span class="ssb-radio-dot"></span> <?php echo esc_html($popt); ?>
                        </label>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php foreach ($custom_fields as $cf): $ck=sanitize_key($cf['id']??'field'); $cl=sanitize_text_field($cf['label']??'Custom field'); $ct=sanitize_key($cf['type']??'text'); $req=!empty($cf['required']); $opts=(array)($cf['options']??[]); ?>
                <div class="ssb-field <?php echo !empty($cf['full']) ? 'ssb-field-full' : ''; ?>">
                    <label class="ssb-label"><?php echo esc_html($cl); ?><?php if($req): ?> <span class="ssb-req">*</span><?php endif; ?></label>
                    <?php if(in_array($ct,['select','radio'],true)): ?>
                        <div class="ssb-radios">
                        <?php foreach($opts as $oi=>$ov): $ov=sanitize_text_field($ov); ?>
                            <?php if($ct==='radio'): ?><label class="ssb-radio-card"><input type="radio" name="ssb_custom_<?php echo esc_attr($ck); ?>" value="<?php echo esc_attr($ov); ?>"><span class="ssb-radio-dot"></span><?php echo esc_html($ov); ?></label><?php else: ?><select class="ssb-input" name="ssb_custom_<?php echo esc_attr($ck); ?>"><option value="">Select…</option><?php foreach($opts as $sv): ?><option value="<?php echo esc_attr($sv); ?>"><?php echo esc_html($sv); ?></option><?php endforeach; ?></select><?php break; endif; ?>
                        <?php endforeach; ?>
                        </div>
                    <?php elseif($ct==='textarea'): ?>
                        <textarea class="ssb-textarea" name="ssb_custom_<?php echo esc_attr($ck); ?>" placeholder="<?php echo esc_attr($cf['placeholder']??''); ?>"></textarea>
                    <?php else: ?>
                        <input type="<?php echo esc_attr(in_array($ct,['email','tel','number','date'],true)?$ct:'text'); ?>" class="ssb-input" name="ssb_custom_<?php echo esc_attr($ck); ?>" placeholder="<?php echo esc_attr($cf['placeholder']??''); ?>">
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>

            <?php if ( $pay_enabled && floatval($price) > 0 && $stripe_pk ) : ?>
            <div class="ssb-payment-block" id="ssb-payment-block">
                <div class="ssb-payment-title">
                    <svg class="ssb-payment-lock" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                    Secure Payment
                </div>
                <div id="ssb-card-element"></div>
                <div class="ssb-card-errors" id="ssb-card-errors"></div>
                <div class="ssb-payment-total">
                    <span>Booking deposit</span>
                    <strong><?php echo esc_html($currency . ' ' . number_format(floatval($price), 2)); ?></strong>
                </div>
                <div class="ssb-secure-note">
                    <svg width="12" height="12" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                    Payments secured by Stripe. Your card details are never stored.
                </div>
            </div>
            <?php endif; ?>

            <button class="ssb-btn" id="ssb-submit-btn">
                <?php echo esc_html($pay_enabled && floatval($price) > 0 ? 'Pay & Confirm Booking' : $smart_ui['submit_label']); ?>
            </button>
        </div>

        <!-- Step 3: Success -->
        <div class="ssb-step" id="ssb-step-3">
            <div class="ssb-success">
                <div class="ssb-success-ring">
                    <svg class="ssb-check" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                </div>
                <h2 class="ssb-success-title"><?php echo esc_html($smart_ui['success_title']); ?></h2>
                <p class="ssb-success-body"><?php echo esc_html($smart_ui['success_message']); ?></p>
                <div id="ssb-meet-area"></div>
                <div id="ssb-paid-area"></div>
            </div>
        </div>

    </div><!-- /ssb-right -->
</div><!-- /ssb-shell -->
</div><!-- /ssb-wrap -->

<script>
(function(){
    var AJAX    = '<?php echo esc_js($ajax_url); ?>';
    var NONCE   = '<?php echo wp_create_nonce('ssb_public_nonce'); ?>';
    var PAY_EN  = <?php echo $pay_enabled ? 'true' : 'false'; ?>;
    var PRICE   = <?php echo floatval($price); ?>;
    var CURR    = '<?php echo esc_js($currency); ?>';
    var PK      = '<?php echo esc_js($stripe_pk); ?>';

    var selDate   = null, selTime = null, selTz = '';
    var reserved  = {};
    var curMonth  = new Date(); curMonth.setDate(1);
    var stripeObj = null, cardEl = null, piClientSecret = null;

    var MONTHS = ['January','February','March','April','May','June',
                  'July','August','September','October','November','December'];
    var TIMES  = [
        '08:30 AM','09:00 AM','09:30 AM','10:00 AM','10:30 AM','11:00 AM',
        '11:30 AM','12:00 PM','12:30 PM','01:00 PM','01:30 PM','02:00 PM',
        '02:30 PM','03:00 PM','03:30 PM','04:00 PM'
    ];

    /* -- Helpers ----------------------------------------------------------- */
    function fmtDB(d) {
        var mm = String(d.getMonth()+1).padStart(2,'0');
        var dd = String(d.getDate()).padStart(2,'0');
        return mm+'/'+dd+'/'+d.getFullYear();
    }

    /* The practice's business days are Mon-Fri (America/New_York / EST).
       A calendar date's day-of-week is the same everywhere, so no timezone
       conversion is needed to know which weekday a given Y/M/D falls on -
       we simply block Saturday (6) and Sunday (0). */
    function isClosedDay(d) {
        var day = d.getDay();
        return day === 0 || day === 6;
    }

    function parseMins(t) {
        var m = t.match(/(\d+):(\d+)\s*(AM|PM)/i);
        if(!m) return 0;
        var h=parseInt(m[1]),min=parseInt(m[2]),ap=m[3].toUpperCase();
        if(ap==='PM'&&h!==12) h+=12;
        if(ap==='AM'&&h===12) h=0;
        return h*60+min;
    }
    function nowMinsInTz(tz) {
        try {
            var parts = new Intl.DateTimeFormat('en-US',{timeZone:tz,hour:'numeric',minute:'numeric',hour12:false}).formatToParts(new Date());
            var h=0,m=0;
            parts.forEach(function(p){ if(p.type==='hour') h=parseInt(p.value); if(p.type==='minute') m=parseInt(p.value); });
            return h*60+m;
        } catch(e){ return -1; }
    }
    function isTodayInTz(date,tz) {
        if(!tz||!date) return false;
        try {
            var parts = new Intl.DateTimeFormat('en-US',{timeZone:tz,year:'numeric',month:'2-digit',day:'2-digit'}).formatToParts(new Date());
            var y=0,mo=0,d=0;
            parts.forEach(function(p){
                if(p.type==='year') y=parseInt(p.value);
                if(p.type==='month') mo=parseInt(p.value)-1;
                if(p.type==='day') d=parseInt(p.value);
            });
            return date.getFullYear()===y&&date.getMonth()===mo&&date.getDate()===d;
        } catch(e){ return false; }
    }

    /* -- Calendar ---------------------------------------------------------- */
    function buildCal() {
        var cal   = document.getElementById('ssb-cal');
        var lbl   = document.getElementById('ssb-month-lbl');
        var today = new Date(); today.setHours(0,0,0,0);
        var first = new Date(curMonth.getFullYear(), curMonth.getMonth(), 1);
        var last  = new Date(curMonth.getFullYear(), curMonth.getMonth()+1, 0);

        lbl.textContent = MONTHS[curMonth.getMonth()]+' '+curMonth.getFullYear();
        cal.innerHTML = '';

        ['Su','Mo','Tu','We','Th','Fr','Sa'].forEach(function(d){
            var h = document.createElement('div'); h.className='ssb-wh'; h.textContent=d; cal.appendChild(h);
        });
        for(var i=0;i<first.getDay();i++) cal.appendChild(document.createElement('div'));

        for(var dt=1;dt<=last.getDate();dt++){
            var cell = document.createElement('div'); cell.className='ssb-day'; cell.textContent=dt;
            var cd = new Date(curMonth.getFullYear(),curMonth.getMonth(),dt); cd.setHours(0,0,0,0);
            var isWeekend = isClosedDay(cd); // practice runs Mon-Fri (EST); Sat/Sun are not bookable
            if(cd.getTime()===today.getTime()) cell.classList.add('is-today');
            if(isWeekend){
                cell.classList.add('is-weekend');
                cell.title = 'Closed on Saturdays & Sundays';
            } else if(cd < today){ cell.classList.add('is-past'); }
            else {
                cell.classList.add('has-slots');
                (function(d,el){ el.addEventListener('click',function(){ onDayClick(d,el); }); })(cd,cell);
            }
            if(selDate&&cd.getTime()===selDate.getTime()) cell.classList.add('is-selected');
            cal.appendChild(cell);
        }
    }

    async function fetchReserved(date) {
        try {
            var res  = await fetch(AJAX,{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:new URLSearchParams({action:'ssb_get_slots',date:fmtDB(date),timezone:selTz})});
            var json = await res.json();
            reserved = json.success ? (json.data.reserved_slots||{}) : {};
        } catch(e){ reserved={}; }
    }

    async function onDayClick(date, el) {
        if(isClosedDay(date)) return; // Sat/Sun are not bookable
        if(!selTz){ alert('Please select your timezone first'); document.getElementById('ssb-tz').focus(); return; }
        selDate = date; selTime = null;
        document.querySelectorAll('.ssb-day.has-slots').forEach(function(d){ d.classList.remove('is-selected'); });
        el.classList.add('is-selected');
        await fetchReserved(date);
        var wrap = document.getElementById('ssb-times-wrap');
        wrap.classList.add('visible');
        buildTimes();
    }

    function buildTimes() {
        var grid    = document.getElementById('ssb-times-grid');
        var todayTz = isTodayInTz(selDate, selTz);
        var nowMins = todayTz ? nowMinsInTz(selTz) : -1;
        grid.innerHTML = '';
        TIMES.forEach(function(t){
            var el = document.createElement('div'); el.className='ssb-slot';
            var sm = parseMins(t);
            var isPast = todayTz && sm <= nowMins;
            if(reserved[t]){
                el.classList.add('taken');
                el.innerHTML = t+'<span class="ssb-slot-tag">Reserved</span>';
            } else if(isPast){
                el.classList.add('past');
                el.innerHTML = t+'<span class="ssb-slot-tag">Past</span>';
            } else {
                el.textContent = t;
                (function(time,node){ node.addEventListener('click',function(){ onTimeClick(time,node); }); })(t,el);
            }
            grid.appendChild(el);
        });
    }

    function onTimeClick(time, el) {
        selTime = time;
        document.querySelectorAll('.ssb-slot:not(.taken):not(.past)').forEach(function(s){ s.classList.remove('selected'); });
        el.classList.add('selected');

        var dateStr = selDate.toLocaleDateString('en-US',{weekday:'short',month:'short',day:'numeric',year:'numeric'});
        var tzOpt   = document.getElementById('ssb-tz');
        var tzText  = tzOpt.options[tzOpt.selectedIndex].text;
        document.querySelector('#ssb-sidebar-datetime .ssb-meta-text').textContent = dateStr+', '+time+' – '+tzText;

        // Transition to step 2
        setTimeout(function(){
            showStep(2);
            if(PAY_EN && PRICE>0 && PK && !stripeObj) initStripe();
        }, 220);
    }

    /* -- Stripe ------------------------------------------------------------ */
    function initStripe() {
        if(typeof Stripe === 'undefined') return;
        stripeObj = Stripe(PK);
        var elements = stripeObj.elements({
            appearance: {
                theme: 'stripe',
                variables: {
                    colorPrimary: '#6366f1',
                    borderRadius: '10px',
                    fontFamily: 'Plus Jakarta Sans, sans-serif',
                }
            }
        });
        cardEl = elements.create('card', {
            style: {
                base: {
                    fontSize: '15px', color: '#0f0f0f',
                    fontFamily: 'Plus Jakarta Sans, sans-serif',
                    '::placeholder': { color: '#9ca3af' }
                }
            }
        });
        cardEl.mount('#ssb-card-element');
        cardEl.on('change', function(e){
            document.getElementById('ssb-card-errors').textContent = e.error ? e.error.message : '';
        });
    }

    /* -- Step management --------------------------------------------------- */
    function showStep(n) {
        [1,2,3].forEach(function(i){
            document.getElementById('ssb-step-'+i).classList.remove('active');
        });
        document.getElementById('ssb-step-'+n).classList.add('active');
    }

    /* -- Submit ------------------------------------------------------------ */
    document.getElementById('ssb-submit-btn').addEventListener('click', async function(){
        var first    = document.getElementById('ssb-first').value.trim();
        var last     = document.getElementById('ssb-last').value.trim();
        var email    = document.getElementById('ssb-email').value.trim();
        var phone    = document.getElementById('ssb-phone').value.trim();
        var practice = document.querySelector('.ssb-radio-card.chosen');

        if(!first||!last||!email||!phone){ alert('Please fill in all required fields.'); return; }
        if(!practice){ alert('Please select your practice type.'); return; }
        if(!selDate||!selTime||!selTz){ alert('Please select a date, time, and timezone.'); return; }

        var btn = document.getElementById('ssb-submit-btn');
        btn.disabled = true;
        btn.innerHTML = '<span class="ssb-spin"></span> Processing…';

        var tzOpt  = document.getElementById('ssb-tz');
        var tzText = tzOpt.options[tzOpt.selectedIndex].text;
        var customFields = {};
        document.querySelectorAll('[name^=\"ssb_custom_\"]').forEach(function(el){ if(el.type==='radio' && !el.checked) return; customFields[el.name.replace('ssb_custom_','')] = el.value; });

        if(PAY_EN && PRICE>0 && stripeObj && cardEl){
            // Payment flow
            try {
                // Get PaymentIntent client_secret
                var piRes  = await fetch(AJAX,{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:new URLSearchParams({action:'ssb_create_payment_intent',nonce:NONCE})});
                var piJson = await piRes.json();

                if(!piJson.success){ alert('Payment error: '+(piJson.data.message||'Unknown error')); resetBtn(btn); return; }
                piClientSecret = piJson.data.client_secret;

                // Confirm card payment
                var result = await stripeObj.confirmCardPayment(piClientSecret, {
                    payment_method: { card: cardEl, billing_details: { name: first+' '+last, email: email } }
                });

                if(result.error){ 
                    document.getElementById('ssb-card-errors').textContent = result.error.message;
                    resetBtn(btn); return;
                }

                // Book after payment success
                var bookRes  = await fetch(AJAX,{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:new URLSearchParams({
                    action:'ssb_confirm_booking', nonce:NONCE,
                    firstName:first, lastName:last, email:email, phone:phone,
                    practiceType:practice.dataset.val,
                    date:fmtDB(selDate), time:selTime,
                    timezone:selTz, timezone_display:tzText, custom_fields:JSON.stringify(customFields),
                    payment_intent_id:result.paymentIntent.id
                })});
                var bookJson = await bookRes.json();
                if(bookJson.success){
                    showSuccess(bookJson.data.meet_link, true);
                } else {
                    alert('Error: '+(bookJson.data.message||'Booking failed')); resetBtn(btn);
                }
            } catch(err) {
                alert('Network error. Please try again.'); resetBtn(btn);
            }
        } else {
            // Free booking flow
            try {
                var res  = await fetch(AJAX,{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:new URLSearchParams({
                    action:'ssb_book', nonce:NONCE,
                    firstName:first, lastName:last, email:email, phone:phone,
                    practiceType:practice.dataset.val,
                    date:fmtDB(selDate), time:selTime,
                    timezone:selTz, timezone_display:tzText, custom_fields:JSON.stringify(customFields)
                })});
                var json = await res.json();
                if(json.success){
                    showSuccess(json.data.meet_link, false);
                } else {
                    var msg = (json.data&&json.data.message)?json.data.message:'Unknown error.';
                    alert('Error: '+msg);
                    if(json.data&&json.data.slot_taken){
                        await fetchReserved(selDate); buildTimes();
                        showStep(1);
                    }
                    resetBtn(btn);
                }
            } catch(err){
                alert('Network error. Please try again.'); resetBtn(btn);
            }
        }
    });

    function resetBtn(btn) {
        btn.disabled = false;
        btn.innerHTML = (PAY_EN && PRICE>0) ? 'Pay &amp; Confirm Booking' : 'Confirm Booking';
    }

    function showSuccess(meetLink, paid) {
        showStep(3);
        if(meetLink) {
            document.getElementById('ssb-meet-area').innerHTML = '<a href="'+meetLink+'" target="_blank" class="ssb-meet-btn"><svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 10l4.553-2.069A1 1 0 0121 8.87v6.26a1 1 0 01-1.447.9L15 14M3 8a2 2 0 012-2h10a2 2 0 012 2v8a2 2 0 01-2 2H5a2 2 0 01-2-2V8z"/></svg> Join Google Meet</a>';
        }
        if(paid) {
            document.getElementById('ssb-paid-area').innerHTML = '<div class="ssb-paid-badge"><svg width="13" height="13" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg> Payment confirmed</div>';
        }
    }

    /* -- Event listeners --------------------------------------------------- */
    document.getElementById('ssb-tz').addEventListener('change', function(){
        selTz = this.value;
        document.getElementById('ssb-sidebar-tz').textContent = selTz ? this.options[this.selectedIndex].text : 'Select your timezone';
        if(selDate) buildTimes();
    });

    document.getElementById('ssb-prev').addEventListener('click', function(){
        var today = new Date(); today.setDate(1); today.setHours(0,0,0,0);
        var prev  = new Date(curMonth.getFullYear(), curMonth.getMonth()-1, 1);
        if(prev < today) return;
        curMonth = prev; buildCal();
    });
    document.getElementById('ssb-next').addEventListener('click', function(){
        curMonth = new Date(curMonth.getFullYear(), curMonth.getMonth()+1, 1); buildCal();
    });

    document.getElementById('ssb-back').addEventListener('click', function(){
        showStep(1);
    });

    document.querySelectorAll('.ssb-radio-card').forEach(function(card){
        card.addEventListener('click', function(){
            document.querySelectorAll('.ssb-radio-card').forEach(function(c){ c.classList.remove('chosen'); });
            card.classList.add('chosen');
            card.querySelector('input').checked = true;
        });
    });

    buildCal();
})();
</script>
    <?php
    return ob_get_clean();
}
