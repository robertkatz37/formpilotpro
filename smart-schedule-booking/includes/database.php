<?php
if ( ! defined( 'ABSPATH' ) ) exit;

function ssb_create_table() {
    global $wpdb;
    $table           = $wpdb->prefix . 'schedule_bookings';
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE $table (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        first_name varchar(100) NOT NULL,
        last_name varchar(100) NOT NULL,
        email varchar(100) NOT NULL,
        phone varchar(50) NOT NULL,
        practice_type varchar(50) NOT NULL DEFAULT '',
        booking_date varchar(50) NOT NULL,
        booking_time varchar(50) NOT NULL,
        timezone varchar(100) NOT NULL,
        timezone_display varchar(200) DEFAULT '',
        custom_answers longtext DEFAULT NULL,
        canonical_start datetime NOT NULL,
        canonical_end datetime NOT NULL,
        google_event_id varchar(255) DEFAULT '',
        google_meet_link varchar(500) DEFAULT '',
        google_html_link varchar(500) DEFAULT '',
        payment_intent_id varchar(255) DEFAULT '',
        payment_status varchar(50) DEFAULT 'unpaid',
        fee_amount decimal(10,2) DEFAULT 0.00,
        fee_currency varchar(10) DEFAULT 'USD',
        status varchar(50) DEFAULT 'pending',
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY canonical_start (canonical_start),
        KEY status (status),
        KEY date_time_tz (booking_date, booking_time, timezone)
    ) $charset_collate;";

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta($sql);

    // Add new columns to existing installations
    $new_columns = [
        'custom_answers'   => "ALTER TABLE $table ADD COLUMN custom_answers longtext DEFAULT NULL AFTER timezone_display",
        'google_meet_link'  => "ALTER TABLE $table ADD COLUMN google_meet_link varchar(500) DEFAULT '' AFTER google_event_id",
        'google_html_link'  => "ALTER TABLE $table ADD COLUMN google_html_link varchar(500) DEFAULT '' AFTER google_meet_link",
        'payment_intent_id' => "ALTER TABLE $table ADD COLUMN payment_intent_id varchar(255) DEFAULT '' AFTER google_html_link",
        'payment_status'    => "ALTER TABLE $table ADD COLUMN payment_status varchar(50) DEFAULT 'unpaid' AFTER payment_intent_id",
        'fee_amount'        => "ALTER TABLE $table ADD COLUMN fee_amount decimal(10,2) DEFAULT 0.00 AFTER payment_status",
        'fee_currency'      => "ALTER TABLE $table ADD COLUMN fee_currency varchar(10) DEFAULT 'USD' AFTER fee_amount",
    ];
    foreach ( $new_columns as $col => $alter_sql ) {
        $exists = $wpdb->get_var($wpdb->prepare("SHOW COLUMNS FROM $table LIKE %s", $col));
        if ( ! $exists ) $wpdb->query($alter_sql);
    }
}
add_action('after_setup_theme', 'ssb_create_table');
