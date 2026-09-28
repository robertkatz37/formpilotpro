<?php
/**
 * Plugin Name: FormPilot Pro
 * Plugin URI:
 * Description: Advanced reusable WordPress form builder with per-form styling, Stripe payments, Google Calendar, and flexible integrations.
 * Version:     10.24.0
 * Author:      Robert Katz
 * Text Domain: smart-schedule-booking
 */

if ( ! defined( 'ABSPATH' ) ) exit;

define( 'SSB_VERSION',     '10.24.0' );
define( 'SSB_LICENSE_SERVER_URL', 'https://formpilot.healthsdriven.com' );
define( 'SSB_PLUGIN_DIR',  plugin_dir_path( __FILE__ ) );
define( 'SSB_PLUGIN_URL',  plugin_dir_url( __FILE__ ) );
define( 'SSB_PLUGIN_FILE', __FILE__ );

require_once SSB_PLUGIN_DIR . 'includes/database.php';
require_once SSB_PLUGIN_DIR . 'includes/google-calendar.php';
require_once SSB_PLUGIN_DIR . 'includes/google-oauth.php';
require_once SSB_PLUGIN_DIR . 'includes/payment.php';
require_once SSB_PLUGIN_DIR . 'includes/ajax.php';
require_once SSB_PLUGIN_DIR . 'includes/email.php';
require_once SSB_PLUGIN_DIR . 'includes/admin-settings.php';
require_once SSB_PLUGIN_DIR . 'includes/admin-bookings.php';
require_once SSB_PLUGIN_DIR . 'includes/shortcode.php';
require_once SSB_PLUGIN_DIR . 'includes/forms-db.php';
require_once SSB_PLUGIN_DIR . 'includes/forms-admin.php';
require_once SSB_PLUGIN_DIR . 'includes/forms-ajax.php';
require_once SSB_PLUGIN_DIR . 'includes/forms-shortcode.php';
require_once SSB_PLUGIN_DIR . 'includes/payment-links.php';
require_once SSB_PLUGIN_DIR . 'includes/license.php';
require_once SSB_PLUGIN_DIR . 'includes/free-tier.php';
require_once SSB_PLUGIN_DIR . 'includes/templates.php';
require_once SSB_PLUGIN_DIR . 'includes/updates.php';
require_once SSB_PLUGIN_DIR . 'includes/scheduling-suite.php';
require_once SSB_PLUGIN_DIR . 'includes/advanced-scheduling.php';
require_once SSB_PLUGIN_DIR . 'includes/onboarding.php';

register_activation_hook( __FILE__, 'ssb_activate' );
function ssb_activate() {
    ssb_create_table();
    ssb_create_form_tables();
    fp_sched_install();
    if ( function_exists('fp_adv_install') ) fp_adv_install();
    ssb_license_schedule();
    $defaults = ssb_default_settings();
    foreach ( $defaults as $key => $value ) {
        if ( false === get_option( $key ) ) update_option( $key, $value );
    }
}

function ssb_default_settings() {
    return [
        'ssb_primary_color'           => '#0a0a0a',
        'ssb_secondary_color'         => '#1a1a2e',
        'ssb_accent_color'            => '#6366f1',
        'ssb_button_text_color'       => '#ffffff',
        'ssb_border_radius'           => '12',
        'ssb_font_family'             => 'inherit',
        'ssb_sidebar_text_color'      => '#ffffff',
        'ssb_event_title'             => 'Strategy Session',
        'ssb_event_label'             => 'Strategy Session',
        'ssb_event_brand_name'        => get_bloginfo('name'),
        'ssb_organizer_display_name'  => get_bloginfo('name'),
        'ssb_event_duration'          => '30 Minutes',
        'ssb_event_description'       => 'Unlock your practice\'s full potential with this 30-minute strategy session.',
        'ssb_logo_letter'             => 'P',
        'ssb_team_label'              => 'Team',
        'ssb_support_email'           => get_option('admin_email'),
        'ssb_extra_notify_email'      => '',
        'ssb_smtp_enabled'            => '0',
        'ssb_smtp_host'               => '',
        'ssb_smtp_port'               => '587',
        'ssb_smtp_encryption'         => 'tls',
        'ssb_smtp_username'           => '',
        'ssb_smtp_password'           => '',
        'ssb_smtp_from_email'         => get_option('admin_email'),
        'ssb_smtp_from_name'          => get_bloginfo('name'),
        'ssb_google_client_id'        => '',
        'ssb_google_client_secret'    => '',
        'ssb_google_calendar_id'      => 'primary',
        'ssb_google_access_token'     => '',
        'ssb_google_refresh_token'    => '',
        'ssb_google_token_expires'    => '',
        'ssb_google_meet_enabled'     => '1',
        'ssb_google_calendar_enabled' => '0',
        // Payment settings
        'ssb_payment_enabled'         => '0',
        'ssb_stripe_publishable_key'  => '',
        'ssb_stripe_secret_key'       => '',
        'ssb_stripe_test_mode'        => '1',
        'ssb_booking_price'           => '0',
        'ssb_booking_currency'        => 'USD',
        'ssb_payment_description'     => 'Booking deposit',
    ];
}

add_action( 'wp_enqueue_scripts', 'ssb_enqueue_assets' );
function ssb_enqueue_assets() {
    wp_enqueue_style( 'ssb-style', SSB_PLUGIN_URL . 'assets/css/booking.css', [], SSB_VERSION );
    wp_add_inline_style( 'ssb-style', '.ssb-license-required{padding:14px 16px;border:1px solid #fecaca;background:#fef2f2;color:#991b1b;border-radius:10px;font-size:14px;}' );
    wp_enqueue_script( 'ssb-script', SSB_PLUGIN_URL . 'assets/js/booking.js', ['jquery'], SSB_VERSION, true );

    $stripe_pk = get_option('ssb_stripe_publishable_key', '');
    $test_mode = get_option('ssb_stripe_test_mode', '1');
    if ( get_option('ssb_payment_enabled') === '1' && $stripe_pk ) {
        wp_enqueue_script( 'stripe-js', 'https://js.stripe.com/v3/', [], null, true );
    }

    wp_localize_script( 'ssb-script', 'ssbData', [
        'ajaxUrl'         => admin_url('admin-ajax.php'),
        'nonce'           => wp_create_nonce('ssb_public_nonce'),
        'paymentEnabled'  => get_option('ssb_payment_enabled', '0'),
        'stripePublicKey' => $stripe_pk,
        'bookingPrice'    => get_option('ssb_booking_price', '0'),
        'currency'        => get_option('ssb_booking_currency', 'USD'),
    ]);
}

register_deactivation_hook( __FILE__, 'ssb_plugin_deactivate' );
function ssb_plugin_deactivate() { if ( function_exists('ssb_license_unschedule') ) ssb_license_unschedule(); if ( function_exists('wp_next_scheduled') ) { $ts=wp_next_scheduled('fp_adv_minute'); if($ts) wp_unschedule_event($ts,'fp_adv_minute'); } }
