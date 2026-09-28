<?php
/**
 * Google OAuth2 Handler
 * Manages the OAuth2 flow, token storage, and token refresh for Google Calendar API.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Build the Google OAuth2 authorization URL.
 */
function ssb_google_get_auth_url() {
    $client_id    = get_option('ssb_google_client_id', '');
    $redirect_uri = ssb_google_redirect_uri();

    if ( ! $client_id ) return false;

    $params = [
        'client_id'             => $client_id,
        'redirect_uri'          => $redirect_uri,
        'response_type'         => 'code',
        'scope'                 => 'https://www.googleapis.com/auth/calendar https://www.googleapis.com/auth/calendar.events',
        'access_type'           => 'offline',
        'prompt'                => 'consent',
        'state'                 => wp_create_nonce('ssb_google_oauth'),
    ];

    return 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query($params);
}

/**
 * The redirect URI that Google sends the code back to.
 */
function ssb_google_redirect_uri() {
    return admin_url('admin.php?page=ssb-settings&ssb_google_callback=1');
}

/**
 * Exchange authorization code for access + refresh tokens.
 */
function ssb_google_exchange_code( $code ) {
    $client_id     = get_option('ssb_google_client_id', '');
    $client_secret = get_option('ssb_google_client_secret', '');
    $redirect_uri  = ssb_google_redirect_uri();

    $response = wp_remote_post('https://oauth2.googleapis.com/token', [
        'timeout' => 20,
        'body'    => [
            'code'          => $code,
            'client_id'     => $client_id,
            'client_secret' => $client_secret,
            'redirect_uri'  => $redirect_uri,
            'grant_type'    => 'authorization_code',
        ],
    ]);

    if ( is_wp_error($response) ) return $response;

    $body = json_decode(wp_remote_retrieve_body($response), true);

    if ( ! empty($body['error']) ) {
        return new WP_Error('google_token_error', $body['error_description'] ?? $body['error']);
    }

    // Store tokens
    update_option('ssb_google_access_token',  $body['access_token'] ?? '');
    update_option('ssb_google_token_expires',  time() + (int)($body['expires_in'] ?? 3600));
    if ( ! empty($body['refresh_token']) ) {
        update_option('ssb_google_refresh_token', $body['refresh_token']);
    }
    update_option('ssb_google_calendar_enabled', '1');
    update_option('ssb_google_owner_user_id', get_current_user_id());

    return true;
}

/**
 * Get a valid access token, refreshing if needed.
 */
function ssb_google_get_access_token() {
    $access_token  = get_option('ssb_google_access_token', '');
    $expires       = (int) get_option('ssb_google_token_expires', 0);
    $refresh_token = get_option('ssb_google_refresh_token', '');

    if ( ! $access_token ) return false;

    // Still valid (with 60s buffer)
    if ( $expires > time() + 60 ) return $access_token;

    // Need refresh
    if ( ! $refresh_token ) return false;

    $client_id     = get_option('ssb_google_client_id', '');
    $client_secret = get_option('ssb_google_client_secret', '');

    $response = wp_remote_post('https://oauth2.googleapis.com/token', [
        'timeout' => 20,
        'body'    => [
            'refresh_token' => $refresh_token,
            'client_id'     => $client_id,
            'client_secret' => $client_secret,
            'grant_type'    => 'refresh_token',
        ],
    ]);

    if ( is_wp_error($response) ) return false;

    $body = json_decode(wp_remote_retrieve_body($response), true);
    if ( empty($body['access_token']) ) return false;

    update_option('ssb_google_access_token', $body['access_token']);
    update_option('ssb_google_token_expires', time() + (int)($body['expires_in'] ?? 3600));

    return $body['access_token'];
}

/**
 * Check if Google Calendar is connected and ready.
 */
function ssb_google_is_connected() {
    return get_option('ssb_google_calendar_enabled') === '1'
        && get_option('ssb_google_client_id', '')
        && get_option('ssb_google_refresh_token', '');
}

/**
 * Disconnect Google Calendar (clear tokens).
 */
function ssb_google_disconnect() {
    update_option('ssb_google_access_token', '');
    update_option('ssb_google_refresh_token', '');
    update_option('ssb_google_token_expires', '');
    update_option('ssb_google_calendar_enabled', '0');
}

/**
 * Handle the OAuth2 callback on admin page load.
 */
add_action('admin_init', 'ssb_google_handle_callback');
function ssb_google_handle_callback() {
    if ( ! isset($_GET['ssb_google_callback']) ) return;
    if ( ! current_user_can('manage_options') ) return;

    // Handle disconnect
    if ( isset($_GET['ssb_google_disconnect']) ) {
        check_admin_referer('ssb_google_disconnect');
        ssb_google_disconnect();
        wp_redirect(admin_url('admin.php?page=fp-integration-google&ssb_msg=disconnected'));
        exit;
    }

    // Handle OAuth callback
    if ( ! empty($_GET['code']) ) {
        if ( ! wp_verify_nonce($_GET['state'] ?? '', 'ssb_google_oauth') ) {
            wp_redirect(admin_url('admin.php?page=fp-integration-google&ssb_msg=invalid_state'));
            exit;
        }

        $result = ssb_google_exchange_code(sanitize_text_field($_GET['code']));

        if ( is_wp_error($result) ) {
            $msg = urlencode($result->get_error_message());
            wp_redirect(admin_url("admin.php?page=fp-integration-google&ssb_msg=error&ssb_err={$msg}"));
        } else {
            wp_redirect(admin_url('admin.php?page=fp-integration-google&ssb_msg=connected'));
        }
        exit;
    }

    if ( ! empty($_GET['error']) ) {
        $err = urlencode(sanitize_text_field($_GET['error']));
        wp_redirect(admin_url("admin.php?page=fp-integration-google&ssb_msg=error&ssb_err={$err}"));
        exit;
    }
}

/**
 * AJAX: Test Google Calendar connection by listing calendars.
 */
add_action('wp_ajax_ssb_test_google', 'ssb_ajax_test_google');
function ssb_ajax_test_google() {
    check_ajax_referer('ssb_admin_nonce', 'nonce');
    if ( ! current_user_can('manage_options') ) wp_send_json_error('Unauthorized');

    $token = ssb_google_get_access_token();
    if ( ! $token ) {
        wp_send_json_error(['message' => 'No valid access token. Please connect Google Calendar first.']);
    }

    $cal_id = get_option('ssb_google_calendar_id', 'primary');
    $response = wp_remote_get(
        'https://www.googleapis.com/calendar/v3/calendars/' . urlencode($cal_id),
        [
            'timeout' => 15,
            'headers' => ['Authorization' => 'Bearer ' . $token],
        ]
    );

    if ( is_wp_error($response) ) {
        wp_send_json_error(['message' => $response->get_error_message()]);
    }

    $body = json_decode(wp_remote_retrieve_body($response), true);
    $code = wp_remote_retrieve_response_code($response);

    if ( $code === 200 ) {
        wp_send_json_success([
            'message'       => '✅ Connected! Calendar: ' . ($body['summary'] ?? $cal_id),
            'calendar_name' => $body['summary'] ?? $cal_id,
        ]);
    } else {
        wp_send_json_error(['message' => 'API Error: ' . ($body['error']['message'] ?? 'Unknown error')]);
    }
}
