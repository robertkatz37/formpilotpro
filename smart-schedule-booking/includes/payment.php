<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Create Stripe PaymentIntent
 */
add_action('wp_ajax_ssb_create_payment_intent', 'ssb_create_payment_intent_handler');
add_action('wp_ajax_nopriv_ssb_create_payment_intent', 'ssb_create_payment_intent_handler');
function ssb_create_payment_intent_handler() {
    if ( function_exists('ssb_license_can') && ! ssb_license_can('stripe') ) wp_send_json_error(['msg'=>ssb_feature_locked_message('stripe'),'upgrade'=>true], 403);
    if ( ! wp_verify_nonce($_POST['nonce'] ?? '', 'ssb_public_nonce') ) {
        wp_send_json_error(['message' => 'Security check failed.']);
    }

    $price    = floatval( get_option('ssb_booking_price', 0) );
    $currency = strtolower( get_option('ssb_booking_currency', 'USD') );
    $secret   = get_option('ssb_stripe_secret_key', '');

    if ( ! $secret ) {
        wp_send_json_error(['message' => 'Payment not configured.']);
    }
    if ( $price <= 0 ) {
        wp_send_json_error(['message' => 'Invalid price.']);
    }

    $amount = intval( $price * 100 ); // Convert to cents

    $response = wp_remote_post('https://api.stripe.com/v1/payment_intents', [
        'headers' => [
            'Authorization' => 'Bearer ' . $secret,
            'Content-Type'  => 'application/x-www-form-urlencoded',
        ],
        'body' => http_build_query([
            'amount'                    => $amount,
            'currency'                  => $currency,
            'payment_method_types[]'    => 'card',
            'capture_method'            => 'manual', // authorize now, capture later once the booking + confirmation email succeed
            'description'               => sanitize_text_field(get_option('ssb_payment_description', 'Booking')),
            'metadata[source]'          => 'smart_schedule_booking',
        ]),
        'timeout' => 30,
    ]);

    if ( is_wp_error($response) ) {
        wp_send_json_error(['message' => 'Payment gateway error: ' . $response->get_error_message()]);
    }

    $body = json_decode( wp_remote_retrieve_body($response), true );

    if ( empty($body['client_secret']) ) {
        $err = $body['error']['message'] ?? 'Unknown Stripe error.';
        wp_send_json_error(['message' => $err]);
    }

    wp_send_json_success([
        'client_secret' => $body['client_secret'],
        'amount'        => $amount,
        'currency'      => strtoupper($currency),
    ]);
}

/** Create a PaymentIntent using the selected form's own Stripe settings. */
add_action('wp_ajax_ssb_form_create_payment_intent', 'ssb_form_create_payment_intent_handler');
add_action('wp_ajax_nopriv_ssb_form_create_payment_intent', 'ssb_form_create_payment_intent_handler');
function ssb_form_create_payment_intent_handler() {
    if ( function_exists('ssb_license_can') && ! ssb_license_can('stripe') ) wp_send_json_error(['msg'=>ssb_feature_locked_message('stripe'),'upgrade'=>true], 403);
    if ( ! wp_verify_nonce($_POST['nonce'] ?? '', 'ssb_form_submit') ) {
        wp_send_json_error(['message' => 'Security check failed.']);
    }
    $form_id = absint($_POST['form_id'] ?? 0);
    $form = function_exists('ssb_get_form') ? ssb_get_form($form_id) : null;
    if (!$form || $form->status !== 'active') wp_send_json_error(['message'=>'Form not found.']);
    $cfg = $form->settings['integrations']['stripe'] ?? [];
    if (empty($cfg['enabled'])) wp_send_json_error(['message'=>'Stripe is not enabled for this form.']);
    $amount_decimal = floatval($cfg['amount'] ?? 0);
    if ($amount_decimal <= 0) wp_send_json_error(['message'=>'Enter a payment amount for this form.']);
    $secret = get_option('ssb_stripe_secret_key','');
    if (!$secret) wp_send_json_error(['message'=>'Stripe secret key is not configured.']);
    $currency = strtolower(sanitize_key($cfg['currency'] ?? 'USD'));
    if (!preg_match('/^[a-z]{3}$/',$currency)) $currency='usd';
    $amount = (int) round($amount_decimal * 100);
    $description = sanitize_text_field($cfg['description'] ?? $form->form_name);
    $response = wp_remote_post('https://api.stripe.com/v1/payment_intents', [
        'headers'=>['Authorization'=>'Bearer '.$secret,'Content-Type'=>'application/x-www-form-urlencoded'],
        'body'=>http_build_query([
            'amount'=>$amount, 'currency'=>$currency, 'payment_method_types[]'=>'card', 'capture_method'=>'automatic',
            'description'=>$description ?: $form->form_name, 'metadata[form_id]'=>$form_id,
            'metadata[form_name]'=>$form->form_name,
        ]),
        'timeout'=>30,
    ]);
    if (is_wp_error($response)) wp_send_json_error(['message'=>'Stripe error: '.$response->get_error_message()]);
    $body=json_decode(wp_remote_retrieve_body($response),true);
    if (empty($body['client_secret'])) wp_send_json_error(['message'=>$body['error']['message'] ?? 'Unable to create payment.']);
    wp_send_json_success(['client_secret'=>$body['client_secret'],'amount'=>$amount,'currency'=>strtoupper($currency)]);
}

/**
 * Confirm booking after payment
 */
add_action('wp_ajax_ssb_confirm_booking', 'ssb_confirm_booking_handler');
add_action('wp_ajax_nopriv_ssb_confirm_booking', 'ssb_confirm_booking_handler');
function ssb_confirm_booking_handler() {
    if ( function_exists('ssb_license_can') && ! ssb_license_can('stripe') ) wp_send_json_error(['msg'=>ssb_feature_locked_message('stripe'),'upgrade'=>true], 403);
    if ( ! wp_verify_nonce($_POST['nonce'] ?? '', 'ssb_public_nonce') ) {
        wp_send_json_error(['message' => 'Security check failed.']);
    }

    $payment_intent_id = sanitize_text_field($_POST['payment_intent_id'] ?? '');
    $secret            = get_option('ssb_stripe_secret_key', '');
    $payment_enabled   = get_option('ssb_payment_enabled', '0');

    // If payment is enabled, verify the payment
    if ( $payment_enabled === '1' && $payment_intent_id && $secret ) {
        $response = wp_remote_get('https://api.stripe.com/v1/payment_intents/' . urlencode($payment_intent_id), [
            'headers' => [
                'Authorization' => 'Bearer ' . $secret,
            ],
            'timeout' => 30,
        ]);

        if ( is_wp_error($response) ) {
            wp_send_json_error(['message' => 'Could not verify payment.']);
        }

        $pi = json_decode( wp_remote_retrieve_body($response), true );

        // With manual capture, a successfully authorized card sits in
        // 'requires_capture' (held, not yet charged) rather than 'succeeded'.
        $pi_status = $pi['status'] ?? '';
        if ( $pi_status !== 'requires_capture' && $pi_status !== 'succeeded' ) {
            wp_send_json_error(['message' => 'Payment not completed. Status: ' . ($pi_status ?: 'unknown')]);
        }
    }

    // Proceed with booking
    ssb_book_handler_internal($_POST, $payment_intent_id);
}

/**
 * Capture a previously-authorized (held) PaymentIntent — actually takes the money.
 * Called only after the booking + client confirmation email have succeeded.
 */
function ssb_stripe_capture_payment_intent( $payment_intent_id ) {
    $secret = get_option('ssb_stripe_secret_key', '');
    if ( ! $secret || ! $payment_intent_id ) return false;

    $response = wp_remote_post('https://api.stripe.com/v1/payment_intents/' . urlencode($payment_intent_id) . '/capture', [
        'headers' => ['Authorization' => 'Bearer ' . $secret],
        'timeout' => 30,
    ]);

    if ( is_wp_error($response) ) {
        error_log('SSB: Stripe capture failed for ' . $payment_intent_id . ' — ' . $response->get_error_message());
        return false;
    }
    $body = json_decode( wp_remote_retrieve_body($response), true );
    return ( ($body['status'] ?? '') === 'succeeded' );
}

/**
 * Cancel/release a held PaymentIntent authorization — the card is never charged.
 * Called when the booking couldn't be confirmed to the client (e.g. email failed to send),
 * so the customer's account is not debited for a booking they were never notified about.
 */
function ssb_stripe_release_payment_intent( $payment_intent_id ) {
    $secret = get_option('ssb_stripe_secret_key', '');
    if ( ! $secret || ! $payment_intent_id ) return false;

    $response = wp_remote_post('https://api.stripe.com/v1/payment_intents/' . urlencode($payment_intent_id) . '/cancel', [
        'headers' => ['Authorization' => 'Bearer ' . $secret],
        'timeout' => 30,
    ]);

    if ( is_wp_error($response) ) {
        error_log('SSB: Stripe auth release failed for ' . $payment_intent_id . ' — ' . $response->get_error_message());
        return false;
    }
    $body = json_decode( wp_remote_retrieve_body($response), true );
    return ( ($body['status'] ?? '') === 'canceled' );
}

/**
 * Internal booking function (used by both payment and non-payment flows)
 */
function ssb_book_handler_internal( $post_data, $payment_intent_id = '' ) {
    global $wpdb;
    $table = $wpdb->prefix . 'schedule_bookings';

    $data = [
        'first_name'       => sanitize_text_field($post_data['firstName'] ?? ''),
        'last_name'        => sanitize_text_field($post_data['lastName'] ?? ''),
        'email'            => sanitize_email($post_data['email'] ?? ''),
        'phone'            => sanitize_text_field($post_data['phone'] ?? ''),
        'practice_type'    => sanitize_text_field($post_data['practiceType'] ?? ''),
        'booking_date'     => sanitize_text_field($post_data['date'] ?? ''),
        'booking_time'     => sanitize_text_field($post_data['time'] ?? ''),
        'timezone'         => sanitize_text_field($post_data['timezone'] ?? ''),
        'timezone_display' => sanitize_text_field($post_data['timezone_display'] ?? ''),
        'custom_answers'  => wp_json_encode(array_map('sanitize_text_field', (array)json_decode(wp_unslash($post_data['custom_fields'] ?? '{}'), true))),
    ];

    foreach ( ['first_name','last_name','email','phone','booking_date','booking_time','timezone'] as $f ) {
        if ( empty($data[$f]) ) wp_send_json_error(['message' => 'All fields are required.']);
    }
    if ( ! is_email($data['email']) ) wp_send_json_error(['message' => 'Invalid email address.']);

    if ( ssb_is_weekend_date($data['booking_date']) ) {
        wp_send_json_error(['message' => 'Bookings are not available on Saturdays or Sundays.']);
    }

    $canonical = ssb_parse_to_canonical($data['booking_date'], $data['booking_time'], $data['timezone']);
    if ( ! $canonical ) wp_send_json_error(['message' => 'Invalid date or time.']);

    $canonical_start = $canonical['start']->format('Y-m-d H:i:s');
    $canonical_end   = $canonical['end']->format('Y-m-d H:i:s');

    $existing = $wpdb->get_var($wpdb->prepare(
        "SELECT id FROM $table WHERE canonical_start = %s AND status = 'confirmed' LIMIT 1",
        $canonical_start
    ));
    if ( $existing ) {
        wp_send_json_error(['message' => 'This slot was just reserved. Please choose another time.', 'slot_taken' => true]);
    }

    $data['canonical_start']    = $canonical_start;
    $data['canonical_end']      = $canonical_end;
    $data['status']             = 'confirmed';
    $data['payment_intent_id']  = $payment_intent_id;
    // 'authorized' = card held via Stripe but not yet charged; finalized to
    // 'paid' (capture) or 'auth_released' (cancel) once we know whether the
    // client confirmation email actually went out, just below.
    $data['payment_status']     = $payment_intent_id ? 'authorized' : 'unpaid';
    // Snapshot the fee configured at the time of booking, so revenue reporting
    // stays accurate even if the price is changed later in Settings.
    $data['fee_amount']         = floatval( get_option('ssb_booking_price', 0) );
    $data['fee_currency']       = strtoupper( get_option('ssb_booking_currency', 'USD') );

    $inserted = $wpdb->insert($table, $data);
    if ( ! $inserted ) wp_send_json_error(['message' => 'Database error: ' . $wpdb->last_error]);

    $booking_id = $wpdb->insert_id;

    $booking = $data;
    $booking['canonical_start_iso'] = $canonical['start']->format(DateTime::ATOM);
    $booking['canonical_end_iso']   = $canonical['end']->format(DateTime::ATOM);
    $booking['meet_link']           = '';
    $booking['html_link']           = '';

    $google_result = ssb_create_google_calendar_event($booking);
    if ( ! is_wp_error($google_result) && ! empty($google_result['event_id']) ) {
        $wpdb->update($table, [
            'google_event_id'  => $google_result['event_id'],
            'google_meet_link' => $google_result['meet_link'] ?? '',
            'google_html_link' => $google_result['html_link'] ?? '',
        ], ['id' => $booking_id]);
        $booking['meet_link'] = $google_result['meet_link'] ?? '';
        $booking['html_link'] = $google_result['html_link'] ?? '';
    }

    $customer_email_sent = true; // free bookings (no email function available) don't block anything
    if ( function_exists('ssb_send_customer_email') ) {
        $customer_email_sent = ssb_send_customer_email(
            $data['email'], $data['first_name'], $data['last_name'],
            $data['booking_date'], $data['booking_time'], $data['timezone_display'],
            $data['timezone'], $booking['canonical_start_iso'] ?? '', $booking['meet_link']
        );
    }

    // The fee is only ever actually taken once we know the client got their
    // confirmation. If the email failed to send (mail server down, SMTP
    // error, etc.), release the card authorization instead of charging it.
    $payment_released = false;
    if ( $payment_intent_id ) {
        if ( $customer_email_sent ) {
            $captured = ssb_stripe_capture_payment_intent($payment_intent_id);
            $data['payment_status'] = $captured ? 'paid' : 'authorized'; // capture can be retried/reconciled manually if it somehow fails
        } else {
            ssb_stripe_release_payment_intent($payment_intent_id);
            $data['payment_status'] = 'auth_released';
            $payment_released = true;
        }
        $wpdb->update($table, ['payment_status' => $data['payment_status']], ['id' => $booking_id]);
    }

    if ( function_exists('ssb_send_admin_email') ) {
        ssb_send_admin_email(
            $data['first_name'], $data['last_name'], $data['email'],
            $data['phone'], $data['practice_type'],
            $data['booking_date'], $data['booking_time'], $data['timezone_display'],
            $data['timezone'], $booking['canonical_start_iso'] ?? '', $booking['meet_link']
        );
    }

    // Always alert admin when the client never got their confirmation, whether
    // or not payment was involved — this must never go unnoticed.
    if ( ! $customer_email_sent && function_exists('ssb_send_admin_alert_email_failed') ) {
        ssb_send_admin_alert_email_failed(
            $booking_id, $data['first_name'], $data['last_name'], $data['email'],
            $data['booking_date'], $data['booking_time'], $payment_released
        );
    }

    wp_send_json_success([
        'message'    => 'Booking confirmed',
        'booking_id' => $booking_id,
        'meet_link'  => $booking['meet_link'],
        'paid'       => ( $data['payment_status'] === 'paid' ),
    ]);
}
