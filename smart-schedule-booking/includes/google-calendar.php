<?php
/**
 * Google Calendar Event Creator
 * - Organizer sees: "Discovery Call — John Smith"  (client name)
 * - Client invite uses the configured event label and brand name
 * - Description includes client timezone + converted organizer timezone time
 * - Reminders: 60-min email + 15-min popup
 */
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Create a Google Calendar event with a Google Meet link.
 *
 * @param array $booking  Booking data array from ajax.php
 * @return array|WP_Error ['event_id','meet_link','html_link'] or WP_Error
 */
function ssb_create_google_calendar_event( $booking, $form_settings = [] ) {
    if ( ! ssb_google_is_connected() ) {
        return new WP_Error('google_not_configured', 'Google Calendar is not connected.');
    }

    $token = ssb_google_get_access_token();
    if ( ! $token ) {
        return new WP_Error('google_token_missing', 'Cannot obtain Google access token.');
    }

    $calendar_id   = trim($form_settings['calendar_id'] ?? '') ?: get_option('ssb_google_calendar_id', 'primary');
    $meet_enabled  = get_option('ssb_google_meet_enabled', '1') === '1';
    $org_tz_id     = get_option('ssb_google_timezone', 'America/New_York');
    $event_label   = trim($form_settings['event_label'] ?? '') ?: trim( get_option('ssb_event_label', 'Booking') );
    $brand_name    = trim($form_settings['brand_name'] ?? '') ?: trim( get_option('ssb_event_brand_name', get_bloginfo('name')) );

    $client_name   = trim( $booking['first_name'] . ' ' . $booking['last_name'] );
    $client_tz_str = $booking['timezone_display'] ?? $booking['timezone'] ?? '';
    $client_tz_id  = $booking['timezone'] ?? 'UTC';

    // ── Build human-readable times in BOTH timezones ──────────────────────────
    $client_time_fmt = '';
    $org_time_fmt    = '';
    try {
        $dt_client = new DateTime($booking['canonical_start_iso']);
        $dt_client->setTimezone( new DateTimeZone($client_tz_id) );
        $client_time_fmt = $dt_client->format('l, F j, Y \a\t g:i A') . ' (' . ssb_tz_abbr($dt_client) . ')';

        $dt_org = clone $dt_client;
        $dt_org->setTimezone( new DateTimeZone($org_tz_id) );
        $org_time_fmt = $dt_org->format('l, F j, Y \a\t g:i A') . ' (' . ssb_tz_abbr($dt_org) . ')';
    } catch ( Exception $e ) {
        // fallback — use stored strings
        $client_time_fmt = $booking['booking_date'] . ' ' . $booking['booking_time'] . ' (' . $client_tz_str . ')';
        $org_time_fmt    = $client_time_fmt;
    }

    // ── Organizer-side event title: "Discovery Call — John Smith" ────────────
    $title_organizer = $event_label . ' — ' . $client_name;

    // ── Client-side invite title uses configured label + brand name ───────────
    $title_client = $event_label . ' — ' . $brand_name;

    // ── Rich description shown on organizer's calendar ───────────────────────
    $description = implode("\n", [
        '📅 ' . $title_organizer,
        '',
        '👤 CLIENT DETAILS',
        '━━━━━━━━━━━━━━━━━━━━━━━━━━━━',
        'Name:          ' . $client_name,
        'Email:         ' . $booking['email'],
        'Phone:         ' . $booking['phone'],
        'Practice Type: ' . ($booking['practice_type'] ?? '—'),
        '',
        '🕐 MEETING TIME',
        '━━━━━━━━━━━━━━━━━━━━━━━━━━━━',
        'Client\'s time: ' . $client_time_fmt,
        'Your time:     ' . $org_time_fmt,
        '',
        wp_strip_all_tags( get_option('ssb_event_description', '') ),
    ]);

    // ── Build attendees list ──────────────────────────────────────────────────
    // Always include the client
    $attendees = [];
    if (!empty($booking['email']) && is_email($booking['email'])) {
        $attendees[] = [
            'email'       => $booking['email'],
            'displayName' => $client_name,
            'responseStatus' => 'needsAction',
        ];
    }

    // Add all configured team/org attendees (support, extra notify, smtp from)
    // Each gets the full calendar invite with Meet link and reminders
    $team_emails = array_unique( array_filter([
        trim( get_option('ssb_support_email',       '') ),
        trim( get_option('ssb_extra_notify_email',  '') ),
    ]) );

    $org_display = trim( get_option('ssb_organizer_display_name', $brand_name) );

    foreach ( $team_emails as $team_email ) {
        if ( ! is_email($team_email) ) continue;
        // Skip if same as client (edge case)
        if ( strtolower($team_email) === strtolower($booking['email']) ) continue;
        $attendees[] = [
            'email'          => $team_email,
            'displayName'    => $org_display,
            'responseStatus' => 'accepted',  // auto-accept for team members
            'optional'       => false,
        ];
    }

    // Organizer display name — replaces "unknown sender" in client's invite
    $org_display_name = $org_display;
    $org_email        = trim( get_option('ssb_smtp_from_email', get_option('admin_email', '') ) );

    $event_body = [
        'summary'     => $title_organizer,   // What YOU see on your calendar
        'description' => $description,
        'start'       => [
            'dateTime' => $booking['canonical_start_iso'],
            'timeZone' => $org_tz_id,
        ],
        'end'         => [
            'dateTime' => $booking['canonical_end_iso'],
            'timeZone' => $org_tz_id,
        ],
        // organizer field makes the invite show YOUR name instead of "unknown sender"
        // Note: Google only respects this if the calendar belongs to org_email.
        // The authenticated Google account's name is what truly shows — set it in
        // Google Account settings → Personal info → Name.
        'organizer'   => [
            'email'       => $org_email ?: get_option('admin_email'),
            'displayName' => $org_display_name,
            'self'        => true,
        ],
        'attendees'   => $attendees,
        'reminders'   => [
            'useDefault' => false,
            'overrides'  => [
                ['method' => 'email',  'minutes' => 60],   // 1 hour before
                ['method' => 'popup',  'minutes' => 15],   // 15 min before
            ],
        ],
        'guestsCanModifyEvent'  => false,
        'guestsCanInviteOthers' => false,
    ];

    // ── Google Meet conference ────────────────────────────────────────────────
    if ( $meet_enabled ) {
        $event_body['conferenceData'] = [
            'createRequest' => [
                'requestId'             => 'ssb-' . uniqid(),
                'conferenceSolutionKey' => ['type' => 'hangoutsMeet'],
            ],
        ];
    }

    $url = 'https://www.googleapis.com/calendar/v3/calendars/'
         . urlencode($calendar_id) . '/events'
         . ($meet_enabled ? '?conferenceDataVersion=1&sendUpdates=all' : '?sendUpdates=all');

    $response = wp_remote_post($url, [
        'timeout' => 20,
        'headers' => [
            'Authorization' => 'Bearer ' . $token,
            'Content-Type'  => 'application/json',
        ],
        'body' => wp_json_encode($event_body),
    ]);

    if ( is_wp_error($response) ) return $response;

    $code = wp_remote_retrieve_response_code($response);
    $body = json_decode(wp_remote_retrieve_body($response), true);

    if ( $code !== 200 && $code !== 201 ) {
        $msg = $body['error']['message'] ?? "HTTP $code";
        return new WP_Error('google_event_failed', $msg);
    }

    $result = [
        'event_id'       => $body['id']      ?? '',
        'html_link'      => $body['htmlLink'] ?? '',
        'meet_link'      => '',
        'org_time'       => $org_time_fmt,
        'client_time'    => $client_time_fmt,
    ];

    // Extract Meet link
    if ( $meet_enabled && ! empty($body['conferenceData']['entryPoints']) ) {
        foreach ( $body['conferenceData']['entryPoints'] as $ep ) {
            if ( $ep['entryPointType'] === 'video' ) {
                $result['meet_link'] = $ep['uri'];
                break;
            }
        }
    }

    return $result;
}


function ssb_google_calendar_is_free($start_iso, $end_iso, $calendar_id = '') {
    if (!ssb_google_is_connected()) return new WP_Error('google_not_configured','Google Calendar is not connected.');
    $token=ssb_google_get_access_token(); if(!$token) return new WP_Error('google_token_missing','Cannot obtain Google access token.');
    $calendar_id=trim($calendar_id) ?: get_option('ssb_google_calendar_id','primary');
    $body=['timeMin'=>$start_iso,'timeMax'=>$end_iso,'items'=>[['id'=>$calendar_id]]];
    $r=wp_remote_post('https://www.googleapis.com/calendar/v3/freeBusy',['timeout'=>20,'headers'=>['Authorization'=>'Bearer '.$token,'Content-Type'=>'application/json'],'body'=>wp_json_encode($body)]);
    if(is_wp_error($r)) return $r;
    $code=wp_remote_retrieve_response_code($r); $data=json_decode(wp_remote_retrieve_body($r),true);
    if($code!==200) return new WP_Error('google_freebusy_failed',$data['error']['message']??('HTTP '.$code));
    $busy=$data['calendars'][$calendar_id]['busy']??[];
    return empty($busy);
}

/**
 * Get a short timezone abbreviation from a DateTime object.
 * e.g. "EST", "CDT", "PKT"
 */
function ssb_tz_abbr( DateTime $dt ) {
    $abbr = $dt->format('T');
    // Some servers return full names like "Eastern Standard Time" — shorten them
    if ( strlen($abbr) > 6 ) {
        // Build abbreviation from capitals
        preg_match_all('/\b[A-Z]/', $abbr, $m);
        $abbr = implode('', $m[0]) ?: $abbr;
    }
    return $abbr;
}

/**
 * Delete a Google Calendar event (on booking cancellation).
 */
function ssb_delete_google_calendar_event( $event_id, $calendar_id = '' ) {
    if ( ! ssb_google_is_connected() || ! $event_id ) return false;
    $token       = ssb_google_get_access_token();
    $calendar_id = trim($calendar_id) ?: get_option('ssb_google_calendar_id', 'primary');
    if ( ! $token ) return false;

    $response = wp_remote_request(
        'https://www.googleapis.com/calendar/v3/calendars/'
        . urlencode($calendar_id) . '/events/' . urlencode($event_id),
        [
            'method'  => 'DELETE',
            'timeout' => 15,
            'headers' => ['Authorization' => 'Bearer ' . $token],
        ]
    );
    return ! is_wp_error($response) && wp_remote_retrieve_response_code($response) === 204;
}
