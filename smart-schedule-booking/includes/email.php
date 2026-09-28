<?php
if ( ! defined( 'ABSPATH' ) ) exit;

// ── SMTP configuration ────────────────────────────────────────────────────────
add_action( 'phpmailer_init', 'ssb_configure_smtp' );
function ssb_configure_smtp( $phpmailer ) {
    if ( get_option('ssb_smtp_enabled') !== '1' ) return;
    $host       = trim( get_option('ssb_smtp_host', '') );
    $port       = (int) get_option('ssb_smtp_port', 587);
    $encryption = get_option('ssb_smtp_encryption', 'tls');
    $username   = trim( get_option('ssb_smtp_username', '') );
    $password   = get_option('ssb_smtp_password', '');
    $from_email = trim( get_option('ssb_smtp_from_email', '') );
    $from_name  = trim( get_option('ssb_smtp_from_name', get_bloginfo('name') ) );
    if ( ! $host || ! $username ) return;
    $phpmailer->isSMTP();
    $phpmailer->Host     = $host;
    $phpmailer->SMTPAuth = true;
    $phpmailer->Port     = $port;
    $phpmailer->Username = $username;
    $phpmailer->Password = $password;
    $phpmailer->Timeout  = 30;
    if ( $encryption === 'ssl' || $port === 465 ) {
        $phpmailer->SMTPSecure  = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
        $phpmailer->SMTPAutoTLS = false;
    } elseif ( $encryption === 'tls' || $port === 587 ) {
        $phpmailer->SMTPSecure  = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        $phpmailer->SMTPAutoTLS = true;
    } else {
        $phpmailer->SMTPSecure  = '';
        $phpmailer->SMTPAutoTLS = false;
    }
    $phpmailer->SMTPOptions = [
        'ssl' => ['verify_peer' => false, 'verify_peer_name' => false, 'allow_self_signed' => true],
    ];
    if ( $from_email ) {
        $phpmailer->From     = $from_email;
        $phpmailer->FromName = $from_name ?: get_bloginfo('name');
        $phpmailer->addReplyTo($from_email, $from_name);
    }
}

function ssb_get_email_headers() {
    $from_name  = get_option('ssb_smtp_from_name',  get_bloginfo('name'));
    $from_email = get_option('ssb_smtp_from_email', get_option('admin_email'));
    return [
        'Content-Type: text/html; charset=UTF-8',
        "From: {$from_name} <{$from_email}>",
    ];
}

// ══════════════════════════════════════════════════════════════════════════════
// ADMIN ALERT — client confirmation email failed to send
// Sent regardless of the main admin notification, so this never gets missed.
// ══════════════════════════════════════════════════════════════════════════════
function ssb_send_admin_alert_email_failed( $booking_id, $first, $last, $email, $date, $time, $payment_released ) {
    $primary_email = get_option('ssb_support_email', get_option('admin_email'));
    $extra_email   = trim( get_option('ssb_extra_notify_email', '') );
    $headers       = ssb_get_email_headers();

    $subject = '⚠️ Confirmation email failed — Booking #' . intval($booking_id);

    $payment_line = $payment_released
        ? '<p style="margin:12px 0 0;color:#991b1b;"><strong>Payment was NOT charged</strong> — the card authorization was released, since the client never received confirmation.</p>'
        : '<p style="margin:12px 0 0;color:#374151;">This was a free booking, so no charge was affected.</p>';

    $body = '<div style="font-family:Arial,sans-serif;font-size:14px;color:#1f2937;line-height:1.6;">
        <p>The confirmation email to the client for booking <strong>#' . intval($booking_id) . '</strong> could not be delivered.</p>
        <p style="margin:12px 0 0;">
            <strong>Client:</strong> ' . esc_html("$first $last") . ' (' . esc_html($email) . ')<br>
            <strong>Scheduled:</strong> ' . esc_html($date) . ' @ ' . esc_html($time) . '
        </p>
        ' . $payment_line . '
        <p style="margin:16px 0 0;">The booking itself is still saved and the calendar event was still created — please follow up with the client directly (e.g. resend the confirmation, or re-run payment) as needed.</p>
    </div>';

    $sent = wp_mail($primary_email, $subject, $body, $headers);
    if ( $extra_email && $extra_email !== $primary_email ) {
        wp_mail($extra_email, $subject, $body, $headers);
    }
    if ( ! $sent ) {
        // Last-resort fallback so this is never silently lost even if mail itself is broken.
        error_log('SSB ALERT: confirmation email failed for booking #' . $booking_id . ' (' . $email . '), and the admin alert email also failed to send.');
    }
    return $sent;
}

/**
 * Compute org-side time (EST/EDT) from an ISO datetime string.
 * Returns e.g. "12:00 PM EST"
 */
function ssb_org_time_formatted( $iso_start ) {
    $org_tz_id = get_option('ssb_google_timezone', 'America/New_York');
    try {
        $dt = new DateTime($iso_start);
        $dt->setTimezone( new DateTimeZone($org_tz_id) );
        $abbr = $dt->format('T');
        // Shorten long names on some servers
        if ( strlen($abbr) > 6 ) {
            preg_match_all('/\b[A-Z]/', $abbr, $m);
            $abbr = implode('', $m[0]) ?: $abbr;
        }
        return $dt->format('g:i A') . ' ' . $abbr;
    } catch ( Exception $e ) {
        return '';
    }
}

/**
 * Get org timezone label like "EST / EDT"
 */
function ssb_org_tz_label() {
    $org_tz_id = get_option('ssb_google_timezone', 'America/New_York');
    $labels = [
        'America/New_York'    => 'EST / EDT',
        'America/Chicago'     => 'CST / CDT',
        'America/Denver'      => 'MST / MDT',
        'America/Los_Angeles' => 'PST / PDT',
        'America/Phoenix'     => 'MST',
        'America/Anchorage'   => 'AKST / AKDT',
        'Pacific/Honolulu'    => 'HST',
        'Europe/London'       => 'GMT / BST',
        'Europe/Paris'        => 'CET / CEST',
        'Asia/Karachi'        => 'PKT',
        'Asia/Kolkata'        => 'IST',
        'UTC'                 => 'UTC',
    ];
    return $labels[$org_tz_id] ?? $org_tz_id;
}

// ══════════════════════════════════════════════════════════════════════════════
// ADMIN NOTIFICATION EMAIL  — matches the professional design in the screenshot
// Sent to: primary notify email + extra notify email (both configurable)
// ══════════════════════════════════════════════════════════════════════════════
function ssb_send_admin_email(
    $first, $last, $email, $phone, $practice_type,
    $date, $time, $timezone_display, $client_tz_id = '',
    $iso_start = '', $meet_link = ''
) {
    $_label    = trim( get_option('ssb_event_label', 'Discovery Call') );
    $_brand    = trim( get_option('ssb_event_brand_name', get_bloginfo('name')) );
    $duration  = get_option('ssb_event_duration', '30 Minutes');
    $headers   = ssb_get_email_headers();

    // Primary + extra recipient
    $primary_email = get_option('ssb_support_email', get_option('admin_email'));
    $extra_email   = trim( get_option('ssb_extra_notify_email', '') );

    $subject = '📅 ' . $_label . ' with ' . $first . ' ' . $last . ' — ' . $date . ' @ ' . $time;

    // Compute organizer-side time
    $org_time     = $iso_start ? ssb_org_time_formatted($iso_start) : $time;
    $org_tz_label = ssb_org_tz_label();

    // Build org time badge
    $org_time_badge = '
        <div style="background:#0f4c75;color:#fff;border-radius:6px;padding:10px 16px;margin-bottom:24px;font-size:13px;line-height:1.6;">
            <strong style="color:#7ec8e3;">&#128337; AUTOMATED EST TIME:</strong>
            This meeting takes place at <strong>' . esc_html($org_time) . '</strong> on ' . esc_html($date) . '.
        </div>';

    // Meet link section
    $meet_section = '';
    if ( $meet_link ) {
        $meet_section = '
        <tr>
            <td colspan="2" style="padding:20px 0 0;">
                <a href="' . esc_url($meet_link) . '"
                   style="display:inline-block;background:#0f9d58;color:#fff;text-decoration:none;padding:12px 24px;border-radius:8px;font-weight:700;font-size:14px;letter-spacing:0.3px;">
                    &#127909; Join Google Meet
                </a>
                <p style="margin:8px 0 0;font-size:12px;color:#6b7280;word-break:break-all;">
                    <a href="' . esc_url($meet_link) . '" style="color:#0f9d58;">' . esc_html($meet_link) . '</a>
                </p>
            </td>
        </tr>';
    }

    $body = ssb_build_admin_email(
        $_label, $_brand, $first, $last, $email, $phone,
        $practice_type, $date, $time, $timezone_display,
        $org_time, $org_tz_label, $duration, $meet_link,
        $org_time_badge, $meet_section
    );

    // Send to primary
    $sent = wp_mail($primary_email, $subject, $body, $headers);

    // Send to extra notify email if set and different
    if ( $extra_email && $extra_email !== $primary_email ) {
        wp_mail($extra_email, $subject, $body, $headers);
    }

    return $sent;
}

function ssb_build_admin_email(
    $label, $brand, $first, $last, $email, $phone,
    $practice_type, $date, $time, $client_tz,
    $org_time, $org_tz_label, $duration, $meet_link,
    $org_time_badge, $meet_section
) {
    $year = date('Y');
    $site_url = get_bloginfo('url');

    return '<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>' . esc_html($label) . ' Notification</title>
</head>
<body style="margin:0;padding:0;background:#f0f2f5;font-family:-apple-system,BlinkMacSystemFont,\'Segoe UI\',Arial,sans-serif;color:#1f2937;">

<table width="100%" cellpadding="0" cellspacing="0" style="background:#f0f2f5;padding:32px 16px;">
<tr><td align="center">
<table width="600" cellpadding="0" cellspacing="0" style="max-width:600px;width:100%;">

    <!-- HEADER -->
    <tr>
        <td style="background:#0d2137;border-radius:12px 12px 0 0;padding:28px 36px;text-align:left;">
            <p style="margin:0 0 4px;font-size:12px;color:#7ec8e3;letter-spacing:1.5px;text-transform:uppercase;font-weight:600;">New Booking</p>
            <h1 style="margin:0;color:#ffffff;font-size:22px;font-weight:700;line-height:1.3;">
                &#128197; ' . esc_html($label) . ' with ' . esc_html($first . ' ' . $last) . '
            </h1>
            <p style="margin:8px 0 0;color:#9bbfd4;font-size:13px;">
                A new meeting has been scheduled!
            </p>
        </td>
    </tr>

    <!-- BODY -->
    <tr>
        <td style="background:#ffffff;padding:28px 36px;border-left:1px solid #e5e7eb;border-right:1px solid #e5e7eb;">

            ' . $org_time_badge . '

            <!-- CLIENT INFORMATION -->
            <table width="100%" cellpadding="0" cellspacing="0" style="margin-bottom:24px;">
                <tr>
                    <td colspan="2" style="padding-bottom:12px;border-bottom:2px solid #e5e7eb;margin-bottom:12px;">
                        <span style="font-size:12px;font-weight:700;color:#6b7280;letter-spacing:1.2px;text-transform:uppercase;">
                            &#128100; Client Information
                        </span>
                    </td>
                </tr>
                <tr><td height="12"></td></tr>
                <tr>
                    <td style="font-size:13px;color:#6b7280;padding:8px 0;width:140px;vertical-align:top;">Name:</td>
                    <td style="font-size:14px;color:#1f2937;font-weight:600;padding:8px 0;">' . esc_html($first . ' ' . $last) . '</td>
                </tr>
                <tr>
                    <td style="font-size:13px;color:#6b7280;padding:8px 0;border-top:1px solid #f3f4f6;vertical-align:top;">Email:</td>
                    <td style="font-size:14px;padding:8px 0;border-top:1px solid #f3f4f6;">
                        <a href="mailto:' . esc_attr($email) . '" style="color:#2563eb;text-decoration:none;">' . esc_html($email) . '</a>
                    </td>
                </tr>
                <tr>
                    <td style="font-size:13px;color:#6b7280;padding:8px 0;border-top:1px solid #f3f4f6;vertical-align:top;">Phone:</td>
                    <td style="font-size:14px;padding:8px 0;border-top:1px solid #f3f4f6;">
                        <a href="tel:' . esc_attr($phone) . '" style="color:#2563eb;text-decoration:none;">' . esc_html($phone) . '</a>
                    </td>
                </tr>
                <tr>
                    <td style="font-size:13px;color:#6b7280;padding:8px 0;border-top:1px solid #f3f4f6;vertical-align:top;">Practice Type:</td>
                    <td style="font-size:14px;color:#1f2937;font-weight:600;padding:8px 0;border-top:1px solid #f3f4f6;">' . esc_html($practice_type) . '</td>
                </tr>
            </table>

            <!-- MEETING DETAILS -->
            <table width="100%" cellpadding="0" cellspacing="0" style="margin-bottom:24px;">
                <tr>
                    <td colspan="2" style="padding-bottom:12px;border-bottom:2px solid #e5e7eb;">
                        <span style="font-size:12px;font-weight:700;color:#6b7280;letter-spacing:1.2px;text-transform:uppercase;">
                            &#128197; Meeting Details
                        </span>
                    </td>
                </tr>
                <tr><td height="12"></td></tr>
                <tr>
                    <td style="font-size:13px;color:#6b7280;padding:10px 0;width:140px;vertical-align:middle;">Your Time (' . esc_html($org_tz_label) . '):</td>
                    <td style="padding:10px 0;vertical-align:middle;">
                        <span style="display:inline-block;background:#0d2137;color:#fff;font-size:13px;font-weight:700;padding:5px 14px;border-radius:6px;">
                            ' . esc_html($org_time)  . '
                        </span>
                    </td>
                </tr>
                <tr>
                    <td style="font-size:13px;color:#6b7280;padding:10px 0;border-top:1px solid #f3f4f6;vertical-align:middle;">Client\'s Time:</td>
                    <td style="padding:10px 0;border-top:1px solid #f3f4f6;vertical-align:middle;">
                        <span style="display:inline-block;background:#e0f2fe;color:#0369a1;font-size:13px;font-weight:700;padding:5px 14px;border-radius:6px;">
                            ' . esc_html($time) . '
                        </span>
                    </td>
                </tr>
                <tr>
                    <td style="font-size:13px;color:#6b7280;padding:10px 0;border-top:1px solid #f3f4f6;vertical-align:top;">Client\'s Timezone:</td>
                    <td style="font-size:14px;color:#1f2937;font-weight:500;padding:10px 0;border-top:1px solid #f3f4f6;">' . esc_html($client_tz) . '</td>
                </tr>
                <tr>
                    <td style="font-size:13px;color:#6b7280;padding:10px 0;border-top:1px solid #f3f4f6;vertical-align:top;">Date:</td>
                    <td style="font-size:14px;color:#1f2937;font-weight:600;padding:10px 0;border-top:1px solid #f3f4f6;">' . esc_html($date) . '</td>
                </tr>
                <tr>
                    <td style="font-size:13px;color:#6b7280;padding:10px 0;border-top:1px solid #f3f4f6;vertical-align:top;">Duration:</td>
                    <td style="font-size:14px;color:#1f2937;font-weight:500;padding:10px 0;border-top:1px solid #f3f4f6;">' . esc_html($duration) . '</td>
                </tr>
                ' . $meet_section . '
            </table>

            <!-- ACTION REQUIRED -->
            <table width="100%" cellpadding="0" cellspacing="0" style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;padding:0;">
                <tr>
                    <td style="padding:16px 20px;">
                        <p style="margin:0 0 10px;font-size:12px;font-weight:700;color:#374151;letter-spacing:1px;text-transform:uppercase;">
                            &#9989; Action Required
                        </p>
                        <ol style="margin:0;padding-left:18px;font-size:13px;color:#4b5563;line-height:2;">
                            <li>Add this appointment to your master calendar</li>
                            <li>Prepare strategy points for the call</li>
                            <li>Review client\'s practice type: <strong>' . esc_html($practice_type) . '</strong></li>
                        </ol>
                    </td>
                </tr>
            </table>

        </td>
    </tr>

    <!-- FOOTER -->
    <tr>
        <td style="background:#f8fafc;border:1px solid #e5e7eb;border-top:none;border-radius:0 0 12px 12px;padding:16px 36px;text-align:center;">
            <p style="margin:0;font-size:12px;color:#9ca3af;">
                &copy; ' . $year . ' <a href="' . esc_url($site_url) . '" style="color:#6b7280;text-decoration:none;">' . esc_html($brand) . '</a>
                &nbsp;&middot;&nbsp; Powered by FormPilot Pro
            </p>
        </td>
    </tr>

</table>
</td></tr>
</table>
</body>
</html>';
}

// ══════════════════════════════════════════════════════════════════════════════
// CUSTOMER CONFIRMATION EMAIL — clean, professional, branded
// ══════════════════════════════════════════════════════════════════════════════
function ssb_send_customer_email(
    $email, $first, $last, $date, $time,
    $timezone_display, $client_tz_id = '',
    $iso_start = '', $meet_link = ''
) {
    $_label   = trim( get_option('ssb_event_label',      'Discovery Call') );
    $_brand   = trim( get_option('ssb_event_brand_name', get_bloginfo('name')) );
    $duration = get_option('ssb_event_duration', '30 Minutes');
    $primary  = get_option('ssb_primary_color',   '#0d2137');
    $headers  = ssb_get_email_headers();

    $name    = trim($first . ' ' . $last);
    $subject = '✅ Your ' . $_label . ' is Confirmed — ' . $_brand;

    // Meet link button
    $meet_btn = '';
    if ( $meet_link ) {
        $meet_btn = '
            <tr><td height="24"></td></tr>
            <tr>
                <td align="center"
                    style="background:#f0fdf4;border:1px solid #bbf7d0;border-radius:10px;padding:20px 24px;">
                    <p style="margin:0 0 12px;font-size:13px;color:#15803d;font-weight:600;">
                        &#127909; Your Google Meet Link is Ready
                    </p>
                    <a href="' . esc_url($meet_link) . '"
                       style="display:inline-block;background:#0f9d58;color:#fff;text-decoration:none;
                              padding:13px 32px;border-radius:8px;font-weight:700;font-size:15px;letter-spacing:0.3px;">
                        Join Google Meet
                    </a>
                    <p style="margin:10px 0 0;font-size:11px;color:#16a34a;word-break:break-all;">
                        <a href="' . esc_url($meet_link) . '" style="color:#16a34a;">' . esc_html($meet_link) . '</a>
                    </p>
                </td>
            </tr>';
    }

    $year     = date('Y');
    $site_url = get_bloginfo('url');

    $body = '<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Booking Confirmed</title>
</head>
<body style="margin:0;padding:0;background:#f0f2f5;font-family:-apple-system,BlinkMacSystemFont,\'Segoe UI\',Arial,sans-serif;color:#1f2937;">

<table width="100%" cellpadding="0" cellspacing="0" style="background:#f0f2f5;padding:32px 16px;">
<tr><td align="center">
<table width="600" cellpadding="0" cellspacing="0" style="max-width:600px;width:100%;">

    <!-- HEADER -->
    <tr>
        <td style="background:' . esc_attr($primary) . ';border-radius:12px 12px 0 0;padding:32px 36px;text-align:center;">
            <p style="margin:0 0 6px;font-size:12px;color:#7ec8e3;letter-spacing:1.5px;text-transform:uppercase;font-weight:600;">Booking Confirmed</p>
            <h1 style="margin:0;color:#ffffff;font-size:24px;font-weight:700;line-height:1.3;">
                &#127881; ' . esc_html($_label) . ' Confirmed!
            </h1>
            <p style="margin:10px 0 0;color:rgba(255,255,255,0.8);font-size:14px;">
                You\'re all set, ' . esc_html($name) . '. We look forward to speaking with you!
            </p>
        </td>
    </tr>

    <!-- BODY -->
    <tr>
        <td style="background:#ffffff;padding:28px 36px;border-left:1px solid #e5e7eb;border-right:1px solid #e5e7eb;">

            <p style="margin:0 0 20px;font-size:15px;color:#374151;line-height:1.7;">
                Hi <strong>' . esc_html($first) . '</strong>, your <strong>' . esc_html($_label) . '</strong>
                with <strong>' . esc_html($_brand) . '</strong> has been scheduled.
                Here are your meeting details:
            </p>

            <!-- BOOKING DETAILS -->
            <table width="100%" cellpadding="0" cellspacing="0" style="margin-bottom:20px;">
                <tr>
                    <td colspan="2" style="padding-bottom:12px;border-bottom:2px solid #e5e7eb;">
                        <span style="font-size:12px;font-weight:700;color:#6b7280;letter-spacing:1.2px;text-transform:uppercase;">
                            &#128197; Your Meeting Details
                        </span>
                    </td>
                </tr>
                <tr><td height="12"></td></tr>
                <tr>
                    <td style="font-size:13px;color:#6b7280;padding:10px 0;width:130px;vertical-align:top;">Date:</td>
                    <td style="font-size:14px;color:#1f2937;font-weight:600;padding:10px 0;">' . esc_html($date) . '</td>
                </tr>
                <tr>
                    <td style="font-size:13px;color:#6b7280;padding:10px 0;border-top:1px solid #f3f4f6;vertical-align:middle;">Time:</td>
                    <td style="padding:10px 0;border-top:1px solid #f3f4f6;vertical-align:middle;">
                        <span style="display:inline-block;background:#e0f2fe;color:#0369a1;font-size:13px;font-weight:700;padding:5px 14px;border-radius:6px;">
                            ' . esc_html($time) . '
                        </span>
                    </td>
                </tr>
                <tr>
                    <td style="font-size:13px;color:#6b7280;padding:10px 0;border-top:1px solid #f3f4f6;vertical-align:top;">Timezone:</td>
                    <td style="font-size:14px;color:#1f2937;font-weight:500;padding:10px 0;border-top:1px solid #f3f4f6;">' . esc_html($timezone_display ?: 'Your local timezone') . '</td>
                </tr>
                <tr>
                    <td style="font-size:13px;color:#6b7280;padding:10px 0;border-top:1px solid #f3f4f6;vertical-align:top;">Duration:</td>
                    <td style="font-size:14px;color:#1f2937;font-weight:500;padding:10px 0;border-top:1px solid #f3f4f6;">' . esc_html($duration) . '</td>
                </tr>
                <tr>
                    <td style="font-size:13px;color:#6b7280;padding:10px 0;border-top:1px solid #f3f4f6;vertical-align:top;">With:</td>
                    <td style="font-size:14px;color:#1f2937;font-weight:600;padding:10px 0;border-top:1px solid #f3f4f6;">' . esc_html($_brand) . ' Team</td>
                </tr>

                ' . $meet_btn . '
            </table>

            <!-- REMINDER NOTE -->
            <table width="100%" cellpadding="0" cellspacing="0">
                <tr>
                    <td style="background:#fffbeb;border-left:3px solid #f59e0b;border-radius:0 6px 6px 0;padding:14px 18px;font-size:13px;color:#92400e;line-height:1.7;">
                        <strong>&#9200; Reminders:</strong> You\'ll receive an email reminder 1 hour before,
                        and a popup reminder 15 minutes before the meeting. A calendar invitation has
                        been sent to your inbox — please accept it to add to your calendar.
                    </td>
                </tr>
            </table>

            <p style="margin:20px 0 0;font-size:13px;color:#6b7280;line-height:1.7;text-align:center;">
                Need to reschedule? Reply to this email or contact
                <a href="mailto:' . esc_attr(get_option('ssb_support_email', get_option('admin_email'))) . '"
                   style="color:#2563eb;">' . esc_html(get_option('ssb_support_email', get_option('admin_email'))) . '</a>
            </p>
        </td>
    </tr>

    <!-- FOOTER -->
    <tr>
        <td style="background:#f8fafc;border:1px solid #e5e7eb;border-top:none;border-radius:0 0 12px 12px;padding:16px 36px;text-align:center;">
            <p style="margin:0;font-size:12px;color:#9ca3af;">
                &copy; ' . $year . ' <a href="' . esc_url($site_url) . '" style="color:#6b7280;text-decoration:none;">' . esc_html($_brand) . '</a>
                &nbsp;&middot;&nbsp; Powered by FormPilot Pro
            </p>
        </td>
    </tr>

</table>
</td></tr>
</table>
</body>
</html>';

    return wp_mail($email, $subject, $body, $headers);
}
