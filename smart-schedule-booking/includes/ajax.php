<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Practice business days are Mon-Fri (America/New_York / EST). The
 * day-of-week for a calendar date (m/d/Y) is the same in every timezone,
 * so this needs no timezone conversion - it simply flags Saturday and
 * Sunday as closed/non-bookable days.
 */
function ssb_is_weekend_date( $date ) {
    $dt = DateTime::createFromFormat('m/d/Y', $date);
    if ( ! $dt ) return false;
    $w = (int) $dt->format('w'); // 0 = Sunday, 6 = Saturday
    return ( $w === 0 || $w === 6 );
}

function ssb_parse_to_canonical( $date, $time, $user_timezone, $duration_minutes = 30 ) {
    try {
        $user_tz      = new DateTimeZone($user_timezone ?: 'UTC');
        $canonical_tz = new DateTimeZone('America/New_York');
        $combined = trim($date . ' ' . $time);
        $formats = ['Y-m-d H:i','Y-m-d h:i A','m/d/Y h:i A','m/d/Y H:i','Y-m-d\TH:i'];
        $dt = false;
        foreach ($formats as $fmt) {
            $dt = DateTime::createFromFormat($fmt, $combined, $user_tz);
            if ($dt instanceof DateTime) break;
        }
        if ( ! $dt ) return false;
        $dt->setTimezone($canonical_tz);
        $end = clone $dt;
        $end->modify('+' . max(5, (int)$duration_minutes) . ' minutes');
        return ['start' => $dt, 'end' => $end];
    } catch ( Exception $e ) {
        return false;
    }
}

// Get available slots
add_action('wp_ajax_ssb_get_slots', 'ssb_get_slots_handler');
add_action('wp_ajax_nopriv_ssb_get_slots', 'ssb_get_slots_handler');
function ssb_get_slots_handler() {
    if ( function_exists('ssb_license_can') && ! ssb_license_can('core') ) wp_send_json_error(['msg'=>'A valid FormPilot Pro license is required.'], 403);
    global $wpdb;
    $table    = $wpdb->prefix . 'schedule_bookings';
    $date     = sanitize_text_field($_POST['date'] ?? '');
    $timezone = sanitize_text_field($_POST['timezone'] ?? '');
    if ( ! $date || ! $timezone ) wp_send_json_error(['message' => 'Date and timezone required']);

    if ( ssb_is_weekend_date($date) ) {
        wp_send_json_success(['reserved_slots' => [], 'closed' => true, 'message' => 'Closed on Saturdays & Sundays.']);
    }

    $slots = [
        '08:30 AM','09:00 AM','09:30 AM','10:00 AM','10:30 AM','11:00 AM',
        '11:30 AM','12:00 PM','12:30 PM','01:00 PM','01:30 PM','02:00 PM',
        '02:30 PM','03:00 PM','03:30 PM','04:00 PM',
    ];

    $reserved = [];
    foreach ( $slots as $slot ) {
        $canonical = ssb_parse_to_canonical($date, $slot, $timezone);
        if ( ! $canonical ) continue;
        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM $table WHERE canonical_start = %s AND status = 'confirmed' LIMIT 1",
            $canonical['start']->format('Y-m-d H:i:s')
        ));
        if ( $exists ) $reserved[$slot] = true;
    }

    wp_send_json_success(['reserved_slots' => $reserved]);
}

// Direct book (no payment)
add_action('wp_ajax_ssb_book', 'ssb_book_handler');
add_action('wp_ajax_nopriv_ssb_book', 'ssb_book_handler');
function ssb_book_handler() {
    if ( function_exists('ssb_license_can') && ! ssb_license_can('core') ) wp_send_json_error(['msg'=>'A valid FormPilot Pro license is required.'], 403);
    if ( ! wp_verify_nonce($_POST['nonce'] ?? '', 'ssb_public_nonce') ) {
        wp_send_json_error(['message' => 'Security check failed. Please refresh and try again.']);
    }
    // If payment is enabled, redirect to payment flow
    if ( get_option('ssb_payment_enabled', '0') === '1' ) {
        wp_send_json_error(['message' => 'Payment required. Please complete payment first.']);
    }
    ssb_book_handler_internal($_POST);
}

// Admin SMTP test
add_action('wp_ajax_ssb_test_smtp',  'ssb_ajax_test_smtp');
add_action('wp_ajax_ssb_test_email', 'ssb_ajax_test_smtp');
function ssb_ajax_test_smtp() {
    if ( function_exists('ssb_license_can') && ! ssb_license_can('smtp') ) wp_send_json_error(['msg'=>'A valid FormPilot Pro license is required.'], 403);
    check_ajax_referer('ssb_admin_nonce', 'nonce');
    if ( ! current_user_can('manage_options') ) wp_send_json_error(['message' => 'Unauthorized']);

    $to   = sanitize_email($_POST['to'] ?? $_POST['test_email'] ?? get_option('admin_email'));
    $sent = wp_mail(
        $to,
        '✅ SSB SMTP Test — ' . get_bloginfo('name'),
        '<h2>SMTP is working!</h2><p>FormPilot Pro can send emails successfully.</p>',
        ['Content-Type: text/html; charset=UTF-8']
    );

    if ( $sent ) {
        wp_send_json_success(['message' => "Test email sent to {$to}."]);
    } else {
        global $phpmailer;
        $err = is_object($phpmailer) && ! empty($phpmailer->ErrorInfo) ? $phpmailer->ErrorInfo : 'wp_mail() returned false.';
        wp_send_json_error(['message' => $err]);
    }
}
