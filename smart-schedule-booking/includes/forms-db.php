<?php
if ( ! defined( 'ABSPATH' ) ) exit;

function ssb_create_form_tables() {
    global $wpdb;
    $charset = $wpdb->get_charset_collate();
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';

    // Forms table — stores each form definition
    $forms_table = $wpdb->prefix . 'ssb_forms';
    dbDelta( "CREATE TABLE IF NOT EXISTS $forms_table (
        id          mediumint(9)  NOT NULL AUTO_INCREMENT,
        form_name   varchar(200)  NOT NULL,
        form_slug   varchar(200)  NOT NULL,
        description text          DEFAULT '',
        fields      longtext      NOT NULL COMMENT 'JSON array of field definitions',
        settings    longtext      DEFAULT '{}' COMMENT 'JSON: notify_email, success_msg, submit_label, etc.',
        status      varchar(20)   DEFAULT 'active',
        created_at  datetime      DEFAULT CURRENT_TIMESTAMP,
        updated_at  datetime      DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY form_slug (form_slug)
    ) $charset;" );

    // Entries table — stores each submission
    $entries_table = $wpdb->prefix . 'ssb_form_entries';
    dbDelta( "CREATE TABLE IF NOT EXISTS $entries_table (
        id          mediumint(9)  NOT NULL AUTO_INCREMENT,
        form_id     mediumint(9)  NOT NULL,
        entry_data  longtext      NOT NULL COMMENT 'JSON key=>value of submitted fields',
        ip_address  varchar(45)   DEFAULT '',
        user_agent  varchar(500)  DEFAULT '',
        status      varchar(20)   DEFAULT 'unread',
        created_at  datetime      DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY form_id (form_id)
    ) $charset;" );
}
add_action( 'after_setup_theme', 'ssb_create_form_tables' );

// Also ensure tables exist when admin loads the Form Builder page (handles fresh installs / missing tables)
add_action( 'admin_init', 'ssb_ensure_form_tables' );
function ssb_ensure_form_tables() {
    global $wpdb;
    $forms_table   = $wpdb->prefix . 'ssb_forms';
    $entries_table = $wpdb->prefix . 'ssb_form_entries';
    // Only run dbDelta when a table is actually missing to avoid overhead on every request
    $tables_exist = $wpdb->get_var( "SHOW TABLES LIKE '$forms_table'" ) && $wpdb->get_var( "SHOW TABLES LIKE '$entries_table'" );
    if ( ! $tables_exist ) {
        ssb_create_form_tables();
    }
}

// ── Helpers ────────────────────────────────────────────────────────────────


function ssb_normalize_form_fields($fields) {
    $fields = is_array($fields) ? array_values($fields) : [];
    $out = [];
    $submit = null;
    foreach ($fields as $f) {
        if (!is_array($f)) continue;
        $f['id'] = sanitize_key($f['id'] ?? uniqid('f'));
        $f['type'] = sanitize_key($f['type'] ?? 'text');
        if ($f['type'] === 'submit') {
            if ($submit !== null) continue;
            $f['label'] = sanitize_text_field($f['label'] ?? 'Submit Button');
            $f['submit_text'] = sanitize_text_field($f['submit_text'] ?? ($f['label'] ?: 'Submit'));
            $f['required'] = false;
            $submit = $f;
            continue;
        }
        $out[] = $f;
    }
    if ($submit === null) {
        $submit = [
            'id' => uniqid('f'), 'type' => 'submit', 'label' => 'Submit Button', 'submit_text' => 'Submit',
            'placeholder' => '', 'required' => false, 'options' => [], 'width' => 'full', 'help_text' => '',
            'style' => [], 'style_customized' => false,
        ];
    }
    $out[] = $submit;
    return $out;
}

function ssb_get_form( $id ) {
    global $wpdb;
    $table = $wpdb->prefix . 'ssb_forms';
    if ( ! $wpdb->get_var( "SHOW TABLES LIKE '$table'" ) ) {
        ssb_create_form_tables();
        return null;
    }
    $row = $wpdb->get_row( $wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}ssb_forms WHERE id = %d", (int) $id
    ) );
    if ( ! $row ) return null;
    $row->fields   = ssb_normalize_form_fields( json_decode( $row->fields, true ) ?: [] );
    $row->settings = json_decode( $row->settings, true ) ?: [];
    return $row;
}

function ssb_get_all_forms() {
    global $wpdb;
    $table = $wpdb->prefix . 'ssb_forms';
    if ( ! $wpdb->get_var( "SHOW TABLES LIKE '$table'" ) ) {
        ssb_create_form_tables();
        return [];
    }
    $rows = $wpdb->get_results( "SELECT * FROM {$wpdb->prefix}ssb_forms ORDER BY created_at DESC" );
    foreach ( $rows as &$row ) {
        $row->fields   = ssb_normalize_form_fields( json_decode( $row->fields, true ) ?: [] );
        $row->settings = json_decode( $row->settings, true ) ?: [];
    }
    return $rows;
}

function ssb_get_form_entries( $form_id, $limit = 200 ) {
    global $wpdb;
    $table = $wpdb->prefix . 'ssb_form_entries';
    if ( ! $wpdb->get_var( "SHOW TABLES LIKE '$table'" ) ) {
        ssb_create_form_tables();
        return [];
    }
    return $wpdb->get_results( $wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}ssb_form_entries WHERE form_id = %d ORDER BY created_at DESC LIMIT %d",
        (int) $form_id, (int) $limit
    ) );
}

function ssb_unique_slug( $base, $exclude_id = 0 ) {
    global $wpdb;
    $slug = sanitize_title( $base );
    $orig = $slug;
    $i    = 1;
    while ( true ) {
        $sql    = $exclude_id
            ? $wpdb->prepare( "SELECT id FROM {$wpdb->prefix}ssb_forms WHERE form_slug = %s AND id != %d", $slug, $exclude_id )
            : $wpdb->prepare( "SELECT id FROM {$wpdb->prefix}ssb_forms WHERE form_slug = %s", $slug );
        $exists = $wpdb->get_var( $sql );
        if ( ! $exists ) break;
        $slug = $orig . '-' . $i++;
    }
    return $slug;
}
