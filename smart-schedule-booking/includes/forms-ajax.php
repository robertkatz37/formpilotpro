<?php
if ( ! defined( 'ABSPATH' ) ) exit;

// ── Save / update a form (admin only) ─────────────────────────────────────
add_action( 'wp_ajax_ssb_save_form', 'ssb_ajax_save_form' );
function ssb_ajax_save_form() {
    check_ajax_referer( 'ssb_forms_nonce', 'nonce' );
    if ( ! current_user_can('manage_options') ) wp_send_json_error(['msg'=>'Unauthorized'], 403);
    if ( ! ssb_is_paid() && empty($_POST['form_id']) && ssb_free_form_count() >= ssb_free_limits()['forms'] ) wp_send_json_error(['msg'=>ssb_free_limit_notice('forms'),'upgrade'=>true],403);

    global $wpdb;
    $table = $wpdb->prefix . 'ssb_forms';

    $id          = intval( $_POST['form_id'] ?? 0 );
    $name        = sanitize_text_field( $_POST['form_name'] ?? '' );
    $description = sanitize_textarea_field( $_POST['description'] ?? '' );
    $fields_raw  = wp_unslash( $_POST['fields'] ?? '[]' );
    $settings_raw = wp_unslash( $_POST['settings'] ?? '{}' );

    if ( ! $name ) wp_send_json_error(['msg' => 'Form name is required']);

    // Validate JSON
    $fields   = json_decode( $fields_raw, true );
    $settings = json_decode( $settings_raw, true );
    if ( ! is_array($fields) )   $fields   = [];
    if ( ! is_array($settings) ) $settings = [];

    // Multi-Step is Pro-only by default. A signed complimentary entitlement may explicitly grant it.
    if (!empty($settings['multistep_enabled']) && function_exists('ssb_license_can') && !ssb_license_can('multistep')) {
        $settings['multistep_enabled'] = false;
        $fields_raw = wp_json_encode($fields);
    }

    // Sanitize each field definition. Unknown field types are rejected server-side.
    $allowed_types = ['text','email','textarea','number','select','radio','checkbox','toggle','name','phone','address','url','file','date','time','datetime','hidden','terms','spam_protection','booking','timeslot','timezone','submit','heading','divider','page_break','repeater'];
    $clean_fields = [];
    $submit_seen = false;
    foreach ( $fields as $f ) {
        if (!is_array($f)) continue;
        $type = sanitize_key($f['type'] ?? 'text');
        if (!in_array($type, $allowed_types, true)) continue;
        if ($type === 'submit' && $submit_seen) continue;
        if ($type === 'submit') $submit_seen = true;
        $item = [
            'id'          => sanitize_key( $f['id'] ?? uniqid('f') ),
            'type'        => $type,
            'label'       => sanitize_text_field( $f['label'] ?? '' ),
            'placeholder' => sanitize_text_field( $f['placeholder'] ?? '' ),
            'required'    => $type === 'terms' || $type === 'booking' ? true : ! empty( $f['required'] ),
            'options'     => isset($f['options']) ? array_values(array_filter(array_map('sanitize_text_field', (array)$f['options']), 'strlen')) : [],
            'width'       => in_array($f['width'] ?? 'full', ['full','half'], true) ? $f['width'] : 'full',
            'help_text'   => sanitize_text_field( $f['help_text'] ?? '' ),
            'style'       => ssb_sanitize_field_style( isset($f['style']) && is_array($f['style']) ? $f['style'] : [] ),
            'style_customized' => !empty($f['style_customized']),
        ];
        foreach (['enable_country_code','disable_past','file_multiple','address_country','booking_use_google_freebusy'] as $bool_key) {
            if (array_key_exists($bool_key,$f)) $item[$bool_key] = !empty($f[$bool_key]);
        }
        foreach (['name_mode','file_types','hidden_value','terms_text','spam_mode','submit_text','timeslot_mode'] as $text_key) {
            if (array_key_exists($text_key,$f)) $item[$text_key] = sanitize_text_field($f[$text_key]);
        }
        if ($type === 'booking') $item['booking_duration'] = max(5, min(480, intval($f['booking_duration'] ?? 30)));
        if ($type === 'repeater') {
            $item['min_items'] = max(1, min(50, intval($f['min_items'] ?? 1)));
            $item['max_items'] = max(1, min(50, intval($f['max_items'] ?? 10)));
            if ($item['max_items'] < $item['min_items']) $item['max_items'] = $item['min_items'];
            $item['default_items'] = max(1, $item['min_items'], min($item['max_items'], intval($f['default_items'] ?? 1)));
            $item['add_text'] = sanitize_text_field($f['add_text'] ?? 'Add Another');
            $item['remove_text'] = sanitize_text_field($f['remove_text'] ?? 'Remove');
            $item['item_label'] = sanitize_text_field($f['item_label'] ?? 'Item');
            $child_allowed = ['text','email','textarea','number','select','radio','checkbox','toggle','date','time','url','phone'];
            $item['child_fields'] = [];
            foreach ((array)($f['child_fields'] ?? []) as $cf) {
                if (!is_array($cf)) continue;
                $ct = sanitize_key($cf['type'] ?? 'text');
                if (!in_array($ct, $child_allowed, true)) continue;
                $item['child_fields'][] = [
                    'id'=>sanitize_key($cf['id'] ?? uniqid('rf')),
                    'type'=>$ct,
                    'label'=>sanitize_text_field($cf['label'] ?? ucfirst($ct)),
                    'placeholder'=>sanitize_text_field($cf['placeholder'] ?? ''),
                    'required'=>!empty($cf['required']),
                    'options'=>array_values(array_filter(array_map('sanitize_text_field',(array)($cf['options']??[])),'strlen')),
                    'width'=>in_array($cf['width']??'full',['full','half'],true)?$cf['width']:'full',
                ];
            }
        }
        if ($type === 'submit') { $item['required'] = false; $item['submit_text'] = sanitize_text_field($f['submit_text'] ?? ($item['label'] ?: 'Submit')); $item['label'] = $item['submit_text'] ?: 'Submit'; }
        if ($type === 'heading' || $type === 'divider' || $type === 'page_break') $item['required'] = false;
        if ($type === 'page_break') { $item['step_title'] = sanitize_text_field($f['step_title'] ?? 'Next Step'); $item['step_description'] = sanitize_textarea_field($f['step_description'] ?? ''); }
        $clean_fields[] = $item;
    }
    // Normalize step separators so malformed/dragged definitions cannot create blank steps.
    $normalized_fields = [];
    $has_content_in_step = false;
    foreach ($clean_fields as $cf) {
        $ct = $cf['type'] ?? '';
        if ($ct === 'page_break') {
            if (!$has_content_in_step) continue;
            $normalized_fields[] = $cf;
            $has_content_in_step = false;
            continue;
        }
        $normalized_fields[] = $cf;
        if (!in_array($ct, ['heading','divider'], true)) $has_content_in_step = true;
    }
    $clean_fields = $normalized_fields;

    if (!$submit_seen) {
        $clean_fields[] = ['id'=>uniqid('f'),'type'=>'submit','label'=>sanitize_text_field($settings['submit_label'] ?? 'Submit'),'submit_text'=>sanitize_text_field($settings['submit_label'] ?? 'Submit'),'placeholder'=>'','required'=>false,'options'=>[],'width'=>'full','help_text'=>'','style'=>[],'style_customized'=>false];
    } else {
        $submit_fields=[]; $non_submit_fields=[];
        foreach($clean_fields as $cf){ if(($cf['type']??'')==='submit') $submit_fields[]=$cf; else $non_submit_fields[]=$cf; }
        $clean_fields=array_merge($non_submit_fields, array_slice($submit_fields,0,1));
    }

    // Free tier: premium integrations are disabled unless a valid paid entitlement exists.
    $has_stripe = !empty($settings['integrations']['stripe']['enabled']);
    $has_calendar = !empty($settings['integrations']['google_calendar']['enabled']);
    if (!ssb_is_paid()) {
        if ($has_stripe) wp_send_json_error(['msg'=>ssb_feature_locked_message('stripe'),'upgrade'=>true],403);
        if ($has_calendar) wp_send_json_error(['msg'=>ssb_feature_locked_message('calendar'),'upgrade'=>true],403);
        if (!empty($settings['style']['custom_css'])) wp_send_json_error(['msg'=>ssb_feature_locked_message('advanced_styling'),'upgrade'=>true],403);
    }

    // Sanitize settings
    $clean_settings = [
        'notify_email'  => implode( ',', array_filter( array_map( 'sanitize_email', explode( ',', $settings['notify_email'] ?? get_option('admin_email') ) ) ) ),
        'success_msg'   => sanitize_textarea_field( $settings['success_msg'] ?? 'Thank you! Your message has been sent.' ),
        'submit_label'  => sanitize_text_field( $settings['submit_label'] ?? 'Submit' ),
        'display_title' => sanitize_text_field( $settings['display_title'] ?? $name ),
        'show_title' => array_key_exists('show_title', $settings) ? !empty($settings['show_title']) : true,
        'show_description' => array_key_exists('show_description', $settings) ? !empty($settings['show_description']) : true,
        'show_labels' => array_key_exists('show_labels', $settings) ? !empty($settings['show_labels']) : true,
        'multistep_enabled' => (!empty($settings['multistep_enabled']) && (!function_exists('ssb_license_can') || ssb_license_can('multistep'))),
        'multistep_progress' => (function($mode){ $mode = sanitize_key((string)$mode); if ($mode === 'numbers' || $mode === 'tabs') $mode = 'steps'; return in_array($mode, ['both','steps','bar','none'], true) ? $mode : 'both'; })($settings['multistep_progress'] ?? 'both'),
        'multistep_next_label' => sanitize_text_field($settings['multistep_next_label'] ?? 'Next'),
        'multistep_prev_label' => sanitize_text_field($settings['multistep_prev_label'] ?? 'Previous'),
        'multistep_submit_label' => sanitize_text_field($settings['multistep_submit_label'] ?? ($settings['submit_label'] ?? 'Submit')),
        'send_copy'     => ! empty( $settings['send_copy'] ),
        'email_subject' => sanitize_text_field( $settings['email_subject'] ?? '' ),
        'redirect_url'  => esc_url_raw( $settings['redirect_url'] ?? '' ),
        'primary_color' => sanitize_hex_color( $settings['primary_color'] ?? '' ),
        'btn_radius'    => intval( $settings['btn_radius'] ?? -1 ),
        'integrations'  => [
            'stripe' => [
                'enabled' => !empty($settings['integrations']['stripe']['enabled']),
                'amount' => max(0, floatval($settings['integrations']['stripe']['amount'] ?? 0)),
                'currency' => sanitize_text_field($settings['integrations']['stripe']['currency'] ?? 'USD'),
                'description' => sanitize_text_field($settings['integrations']['stripe']['description'] ?? ''),
            ],
            'google_calendar' => [
                'enabled' => !empty($settings['integrations']['google_calendar']['enabled']),
                'calendar_id' => sanitize_text_field($settings['integrations']['google_calendar']['calendar_id'] ?? 'primary'),
                'event_label' => sanitize_text_field($settings['integrations']['google_calendar']['event_label'] ?? ''),
                'brand_name' => sanitize_text_field($settings['integrations']['google_calendar']['brand_name'] ?? ''),
            ],
        ],
        'style' => ssb_sanitize_form_style( isset($settings['style']) && is_array($settings['style']) ? $settings['style'] : [] ),
    ];

    if (!ssb_is_paid()) {
        // Free tier keeps the builder usable but uses the standard FormPilot presentation.
        $clean_settings['primary_color'] = '#6366f1';
        $clean_settings['btn_radius'] = 10;
        $clean_settings['style'] = [];
    }

    $slug = ssb_unique_slug( $name, $id );

    $data = [
        'form_name'   => $name,
        'form_slug'   => $slug,
        'description' => $description,
        'fields'      => wp_json_encode( $clean_fields ),
        'settings'    => wp_json_encode( $clean_settings ),
    ];

    if ( $id ) {
        $wpdb->update( $table, $data, ['id' => $id] );
        wp_send_json_success(['msg' => 'Form updated', 'form_id' => $id, 'slug' => $slug]);
    } else {
        $data['status'] = 'active';
        $wpdb->insert( $table, $data );
        $new_id = $wpdb->insert_id;
        wp_send_json_success(['msg' => 'Form created', 'form_id' => $new_id, 'slug' => $slug]);
    }
}


// ── Per-form style sanitizer ───────────────────────────────────────────────
function ssb_sanitize_form_style($style) {
    $hex = function($v,$fallback='') {
        $v = sanitize_hex_color($v);
        return $v ?: $fallback;
    };
    return [
        'form_bg' => $hex($style['form_bg'] ?? '', '#ffffff'),
        'form_bg_transparent' => !empty($style['form_bg_transparent']),
        'form_border_enabled' => array_key_exists('form_border_enabled',$style) ? !empty($style['form_border_enabled']) : true,
        'form_border' => $hex($style['form_border'] ?? '', '#edf0f3'),
        'form_radius' => max(0, min(60, intval($style['form_radius'] ?? 24))),
        'form_padding' => max(0, min(100, intval($style['form_padding'] ?? 40))),
        'field_gap' => max(0, min(60, intval($style['field_gap'] ?? 18))),
        'label_color' => $hex($style['label_color'] ?? '', '#6b7280'),
        // Section heading uses one color for both heading text and its accent/border.
        'heading_color' => $hex($style['heading_color'] ?? '', '#667eea'),
        'label_size' => max(9, min(24, intval($style['label_size'] ?? 12))),
        'input_bg' => $hex($style['input_bg'] ?? '', '#f9fafb'),
        'input_text' => $hex($style['input_text'] ?? '', '#111827'),
        'input_border' => $hex($style['input_border'] ?? '', '#e5e7eb'),
        'input_focus_border' => $hex($style['input_focus_border'] ?? '', '#667eea'),
        'input_focus_ring' => $hex($style['input_focus_ring'] ?? '', '#dbeafe'),
        'input_error_border' => $hex($style['input_error_border'] ?? '', '#dc2626'),
        'input_error_bg' => $hex($style['input_error_bg'] ?? '', '#fef2f2'),
        'input_placeholder' => $hex($style['input_placeholder'] ?? '', '#94a3b8'),
        'input_border_width' => max(0, min(6, intval($style['input_border_width'] ?? 1))),
        'input_radius' => max(0, min(60, intval($style['input_radius'] ?? 12))),
        'input_padding' => max(4, min(40, intval($style['input_padding'] ?? 14))),
        'input_font_size' => max(10, min(30, intval($style['input_font_size'] ?? 15))),
        'button_bg' => $hex($style['button_bg'] ?? '', '#667eea'),
        'button_text' => $hex($style['button_text'] ?? '', '#ffffff'),
        'button_hover_bg' => $hex($style['button_hover_bg'] ?? '', '#4f46e5'),
        'button_radius' => max(0, min(60, intval($style['button_radius'] ?? 999))),
        'button_padding_y' => max(4, min(40, intval($style['button_padding_y'] ?? 15))),
        'button_padding_x' => max(4, min(80, intval($style['button_padding_x'] ?? 34))),
        'button_font_size' => max(10, min(30, intval($style['button_font_size'] ?? 15))),
        'button_alignment' => in_array(($style['button_alignment'] ?? 'left'), ['left','center','right','full'], true) ? $style['button_alignment'] : 'left',
        'button_full_width' => !empty($style['button_full_width']),
        'ms_next_bg' => $hex($style['ms_next_bg'] ?? '', '#0f172a'),
        'ms_next_text' => $hex($style['ms_next_text'] ?? '', '#ffffff'),
        'ms_next_hover_bg' => $hex($style['ms_next_hover_bg'] ?? '', '#1e293b'),
        'ms_next_border' => $hex($style['ms_next_border'] ?? '', '#0f172a'),
        'ms_next_border_width' => max(0, min(6, intval($style['ms_next_border_width'] ?? 0))),
        'ms_next_radius' => max(0, min(60, intval($style['ms_next_radius'] ?? 8))),
        'ms_next_padding_y' => max(4, min(40, intval($style['ms_next_padding_y'] ?? 10))),
        'ms_next_padding_x' => max(4, min(80, intval($style['ms_next_padding_x'] ?? 22))),
        'ms_prev_bg' => $hex($style['ms_prev_bg'] ?? '', '#f1f5f9'),
        'ms_prev_text' => $hex($style['ms_prev_text'] ?? '', '#475569'),
        'ms_prev_hover_bg' => $hex($style['ms_prev_hover_bg'] ?? '', '#e2e8f0'),
        'ms_prev_border' => $hex($style['ms_prev_border'] ?? '', '#e2e8f0'),
        'ms_prev_border_width' => max(0, min(6, intval($style['ms_prev_border_width'] ?? 1))),
        'ms_prev_radius' => max(0, min(60, intval($style['ms_prev_radius'] ?? 8))),
        'ms_prev_padding_y' => max(4, min(40, intval($style['ms_prev_padding_y'] ?? 10))),
        'ms_prev_padding_x' => max(4, min(80, intval($style['ms_prev_padding_x'] ?? 22))),
        'ms_nav_full_width_mobile' => !empty($style['ms_nav_full_width_mobile']),
        'ms_progress_color' => $hex($style['ms_progress_color'] ?? '', '#667eea'),
        'ms_track_color' => $hex($style['ms_track_color'] ?? '', '#e9edf5'),
        'ms_connector_color' => $hex($style['ms_connector_color'] ?? '', '#d9deea'),
        'ms_active_color' => $hex($style['ms_active_color'] ?? '', '#667eea'),
        'ms_completed_color' => $hex($style['ms_completed_color'] ?? '', '#667eea'),
        'ms_inactive_color' => $hex($style['ms_inactive_color'] ?? '', '#eef2f7'),
        'ms_text_color' => $hex($style['ms_text_color'] ?? '', '#64748b'),
        'ms_shape' => in_array(($style['ms_shape'] ?? 'circle'), ['circle','rounded','square'], true) ? $style['ms_shape'] : 'circle',
        'ms_size' => max(24, min(56, intval($style['ms_size'] ?? 30))),
        'ms_bar_height' => max(3, min(20, intval($style['ms_bar_height'] ?? 8))),
        'ms_spacing' => max(0, min(40, intval($style['ms_spacing'] ?? 10))),
        'ms_show_percent' => array_key_exists('ms_show_percent',$style) ? !empty($style['ms_show_percent']) : true,
        'ms_show_step_label' => array_key_exists('ms_show_step_label',$style) ? !empty($style['ms_show_step_label']) : true,
        'ms_show_titles' => array_key_exists('ms_show_titles',$style) ? !empty($style['ms_show_titles']) : true,
        'ms_clickable' => array_key_exists('ms_clickable',$style) ? !empty($style['ms_clickable']) : true,
        'ms_animation' => in_array(($style['ms_animation'] ?? 'slide'), ['slide','fade','none'], true) ? $style['ms_animation'] : 'slide',
        'responsive' => ssb_sanitize_responsive_style(isset($style['responsive']) && is_array($style['responsive']) ? $style['responsive'] : []),
    ];
}

function ssb_sanitize_responsive_style($responsive) {
    $defaults = [
        'desktop'=>['form_padding'=>40,'form_radius'=>24,'field_gap'=>18,'title_size'=>32,'description_size'=>16,'label_size'=>12,'input_padding'=>14,'input_font_size'=>15,'button_padding_y'=>15,'button_padding_x'=>34,'button_font_size'=>15,'ms_size'=>30,'ms_bar_height'=>8,'ms_spacing'=>10,'ms_show_titles'=>true],
        'tablet'=>['form_padding'=>28,'form_radius'=>20,'field_gap'=>14,'title_size'=>28,'description_size'=>15,'label_size'=>12,'input_padding'=>13,'input_font_size'=>15,'button_padding_y'=>14,'button_padding_x'=>28,'button_font_size'=>15,'ms_size'=>30,'ms_bar_height'=>8,'ms_spacing'=>8,'ms_show_titles'=>true],
        'mobile'=>['form_padding'=>20,'form_radius'=>16,'field_gap'=>12,'title_size'=>24,'description_size'=>14,'label_size'=>12,'input_padding'=>12,'input_font_size'=>16,'button_padding_y'=>13,'button_padding_x'=>20,'button_font_size'=>14,'ms_size'=>28,'ms_bar_height'=>7,'ms_spacing'=>6,'ms_show_titles'=>false],
    ];
    $ranges=['form_padding'=>[0,100],'form_radius'=>[0,60],'field_gap'=>[0,60],'title_size'=>[12,60],'description_size'=>[10,32],'label_size'=>[9,24],'input_padding'=>[4,40],'input_font_size'=>[10,30],'button_padding_y'=>[4,40],'button_padding_x'=>[4,80],'button_font_size'=>[10,30],'ms_size'=>[20,56],'ms_bar_height'=>[3,20],'ms_spacing'=>[0,40]];
    $out=[];
    foreach($defaults as $device=>$def){
        $src=isset($responsive[$device])&&is_array($responsive[$device])?$responsive[$device]:[]; $out[$device]=[];
        foreach($def as $k=>$dv){
            if($k==='ms_show_titles'){ $out[$device][$k]=array_key_exists($k,$src)?!empty($src[$k]):$dv; continue; }
            $v=array_key_exists($k,$src)?intval($src[$k]):$dv; $r=$ranges[$k]; $out[$device][$k]=max($r[0],min($r[1],$v));
        }
    }
    return $out;
}

// Field styles are opt-in. Empty/unchanged fields inherit the form-level styling.
function ssb_sanitize_field_style($style) {
    $out = [];
    $hex_keys = ['bg','text','border'];
    foreach ($hex_keys as $key) {
        if (array_key_exists($key,$style)) {
            $v = sanitize_hex_color($style[$key]);
            if ($v) $out[$key] = $v;
        }
    }
    foreach (['border_width'=>[0,6],'radius'=>[0,60],'font_size'=>[10,30],'padding'=>[4,40]] as $key=>$range) {
        if (array_key_exists($key,$style)) $out[$key] = max($range[0],min($range[1],intval($style[$key])));
    }
    return $out;
}

// ── Import dropdown options from CSV ────────────────────────────────────────
add_action('wp_ajax_ssb_import_select_csv', 'ssb_ajax_import_select_csv');
function ssb_ajax_import_select_csv() {
    if ( function_exists('ssb_license_can') && ! ssb_license_can('builder') ) wp_send_json_error(['msg'=>'A valid FormPilot Pro license is required.'], 403);
    check_ajax_referer('ssb_forms_nonce', 'nonce');
    if (!current_user_can('manage_options')) wp_send_json_error(['msg'=>'Unauthorized']);
    if (empty($_FILES['csv_file']) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
        wp_send_json_error(['msg'=>'Please choose a valid CSV file.']);
    }
    $file = $_FILES['csv_file'];
    if (($file['size'] ?? 0) > 5 * 1024 * 1024) wp_send_json_error(['msg'=>'CSV file must be 5 MB or smaller.']);
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if ($ext !== 'csv') wp_send_json_error(['msg'=>'Only .csv files are supported.']);

    $fh = fopen($file['tmp_name'], 'r');
    if (!$fh) wp_send_json_error(['msg'=>'Could not read the CSV file.']);
    $options = [];
    $row = 0;
    while (($data = fgetcsv($fh)) !== false) {
        $row++;
        $cell = trim(sanitize_text_field($data[0] ?? ''));
        // Accept a simple one-column CSV. Ignore common header names.
        if ($row === 1 && in_array(strtolower($cell), ['option','options','value','values','label','labels','name','names'], true)) continue;
        if ($cell !== '') $options[] = $cell;
    }
    fclose($fh);
    $options = array_values(array_unique($options));
    if (!$options) wp_send_json_error(['msg'=>'No values were found in the CSV.']);
    wp_send_json_success(['options'=>$options, 'count'=>count($options)]);
}

// ── Delete a form ──────────────────────────────────────────────────────────
add_action( 'wp_ajax_ssb_delete_form', 'ssb_ajax_delete_form' );
function ssb_ajax_delete_form() {
    check_ajax_referer( 'ssb_forms_nonce', 'nonce' );
    if ( ! current_user_can('manage_options') ) wp_send_json_error(['msg' => 'Unauthorized']);

    global $wpdb;
    $id = intval( $_POST['form_id'] ?? 0 );
    if ( ! $id ) wp_send_json_error(['msg' => 'Invalid ID']);

    $wpdb->delete( $wpdb->prefix . 'ssb_forms',        ['id'      => $id] );
    $wpdb->delete( $wpdb->prefix . 'ssb_form_entries', ['form_id' => $id] );

    wp_send_json_success(['msg' => 'Form deleted']);
}

// ── Front-end form submission ──────────────────────────────────────────────

function ssb_form_sanitize_recursive($value) {
    if (is_array($value)) {
        $out=[]; foreach($value as $k=>$v) $out[sanitize_key($k)] = ssb_form_sanitize_recursive($v); return $out;
    }
    return sanitize_text_field((string)$value);
}

function ssb_form_resolve_hidden_value($value) {
    $repl = [
        '{url}' => esc_url_raw(home_url(add_query_arg([], $GLOBALS['wp']->request ?? ''))),
        '{referrer}' => esc_url_raw(wp_get_referer() ?: ''),
        '{user_id}' => is_user_logged_in() ? (string)get_current_user_id() : '',
        '{user_email}' => is_user_logged_in() ? (string)wp_get_current_user()->user_email : '',
    ];
    return strtr((string)$value, $repl);
}

function ssb_form_store_upload($file, $field) {
    if (!is_array($file) || empty($file['tmp_name']) || !empty($file['error'])) return '';
    if (($file['size'] ?? 0) > 10 * 1024 * 1024) return new WP_Error('file_size','File exceeds the 10 MB limit.');
    $allowed = array_values(array_filter(array_map('sanitize_key', preg_split('/[,\s]+/', (string)($field['file_types'] ?? 'pdf,doc,docx,jpg,jpeg,png')))));
    $check = wp_check_filetype_and_ext($file['tmp_name'], $file['name']);
    $ext = strtolower($check['ext'] ?? pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!$ext || ($allowed && !in_array($ext, $allowed, true))) return new WP_Error('file_type','This file type is not allowed.');
    require_once ABSPATH . 'wp-admin/includes/file.php';
    $mime_map = [
        'pdf'=>'application/pdf','doc'=>'application/msword','docx'=>'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'xls'=>'application/vnd.ms-excel','xlsx'=>'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet','csv'=>'text/csv',
        'jpg'=>'image/jpeg','jpeg'=>'image/jpeg','png'=>'image/png','gif'=>'image/gif','webp'=>'image/webp','txt'=>'text/plain','zip'=>'application/zip'
    ];
    $mimes = []; foreach ($allowed as $a) if (isset($mime_map[$a])) $mimes[$a] = $mime_map[$a];
    $upload = wp_handle_upload($file, ['test_form'=>false,'mimes'=>$mimes]);
    if (isset($upload['error'])) return new WP_Error('file_upload',$upload['error']);
    return esc_url_raw($upload['url'] ?? '');
}

function ssb_form_verify_spam_protection($field) {
    $fid = sanitize_key($field['id'] ?? '');
    if (!$fid) return true;
    $hp = sanitize_text_field($_POST['ssb_hp_'.$fid] ?? '');
    if ($hp !== '') return new WP_Error('spam','Spam protection blocked this submission.');
    $rendered = intval($_POST['ssb_rendered_at'] ?? 0);
    if ($rendered && (time() - $rendered) < 1) return new WP_Error('spam','Please wait a moment and try again.');
    $mode = sanitize_key($field['spam_mode'] ?? 'honeypot');
    if ($mode === 'turnstile') {
        $secret = trim((string)get_option('ssb_turnstile_secret_key',''));
        $token = sanitize_text_field($_POST['cf-turnstile-response'] ?? '');
        if ($secret) {
            if (!$token) return new WP_Error('spam','Spam verification is required.');
            $r = wp_remote_post('https://challenges.cloudflare.com/turnstile/v0/siteverify',['timeout'=>15,'body'=>['secret'=>$secret,'response'=>$token,'remoteip'=>sanitize_text_field($_SERVER['REMOTE_ADDR'] ?? '')]]);
            $ok = !is_wp_error($r) && !empty(json_decode(wp_remote_retrieve_body($r),true)['success']);
            if (!$ok) return new WP_Error('spam','Spam verification failed.');
        }
    } elseif ($mode === 'recaptcha') {
        $secret = trim((string)get_option('ssb_recaptcha_secret_key',''));
        $token = sanitize_text_field($_POST['g-recaptcha-response'] ?? '');
        if ($secret) {
            if (!$token) return new WP_Error('spam','Spam verification is required.');
            $r = wp_remote_post('https://www.google.com/recaptcha/api/siteverify',['timeout'=>15,'body'=>['secret'=>$secret,'response'=>$token,'remoteip'=>sanitize_text_field($_SERVER['REMOTE_ADDR'] ?? '')]]);
            $ok = !is_wp_error($r) && !empty(json_decode(wp_remote_retrieve_body($r),true)['success']);
            if (!$ok) return new WP_Error('spam','Spam verification failed.');
        }
    }
    return true;
}

add_action( 'wp_ajax_ssb_submit_form',        'ssb_ajax_submit_form' );
add_action( 'wp_ajax_nopriv_ssb_submit_form', 'ssb_ajax_submit_form' );
function ssb_ajax_submit_form() {
    // Public endpoint: logged-out visitors must be allowed to submit.
    // Nonce + server-side field validation protect this request; admin capabilities
    // belong only on form-management endpoints.
    if (!ssb_is_paid() && ssb_free_entries_this_month() >= ssb_free_limits()['entries_month']) wp_send_json_error(['msg'=>ssb_free_limit_notice('entries'),'upgrade'=>true],403);
    if ( ! ssb_is_paid() && empty($_POST['form_id']) && ssb_free_form_count() >= ssb_free_limits()['forms'] ) wp_send_json_error(['msg'=>ssb_free_limit_notice('forms'),'upgrade'=>true],403);
    check_ajax_referer( 'ssb_form_submit', 'nonce' );

    global $wpdb;
    $form_id = intval( $_POST['form_id'] ?? 0 );
    $form    = ssb_get_form( $form_id );

    if ( ! $form || $form->status !== 'active' ) {
        wp_send_json_error(['msg' => 'Form not found']);
    }

    $entry_data        = [];
    $errors            = [];
    $field_errors      = [];
    $client_tz_id      = sanitize_text_field( $_POST['client_timezone'] ?? '' );
    $client_tz_display = sanitize_text_field( $_POST['client_timezone_display'] ?? $client_tz_id );

    // ── Collect field values ────────────────────────────────────────────────
    $selected_timeslot = '';
    $date_field_value  = '';
    $canonical = null;
    $booking_duration = 30;
    $booking_field_present = false;
    $booking_use_freebusy = true;

    foreach ( $form->fields as $field ) {
        $type = sanitize_key($field['type'] ?? 'text');
        if (in_array($type, ['heading','divider','page_break','submit','spam_protection'], true)) {
            if ($type === 'spam_protection') {
                $spam = ssb_form_verify_spam_protection($field);
                if (is_wp_error($spam)) $errors[] = $spam->get_error_message();
            }
            continue;
        }
        $key = 'field_' . $field['id'];
        $raw = $_POST[$key] ?? '';
        if ($type === 'repeater') {
            $rows = is_array($raw) ? $raw : [];
            $min = max(1, intval($field['min_items'] ?? 1)); $max = max(1, intval($field['max_items'] ?? 10));
            if (count($rows) < $min || count($rows) > $max) {
                $errors[] = sprintf('%s must contain between %d and %d item(s).', $field['label'], $min, $max);
                continue;
            }
            $clean_rows=[];
            foreach ($rows as $ri=>$row) {
                if (!is_array($row)) continue;
                $clean_row=[];
                foreach ((array)($field['child_fields'] ?? []) as $child) {
                    $cid = sanitize_key($child['id'] ?? ''); if (!$cid) continue;
                    $cv = $row[$cid] ?? '';
                    if (is_array($cv)) $cv = array_values(array_filter(array_map('sanitize_text_field',$cv),'strlen'));
                    else $cv = sanitize_textarea_field((string)$cv);
                    if (!empty($child['required'])) {
                        $empty = is_array($cv) ? count($cv)===0 : trim((string)$cv)==='';
                        if ($empty) { $field_errors[$key.'['.$ri.']['.$cid.']'] = ($child['label'] ?? 'This field').' is required.'; $errors[] = $field['label'].' item '.($ri+1).': '.($child['label'] ?? 'field').' is required.'; }
                    }
                    $clean_row[$child['label'] ?? $cid] = $cv;
                }
                $clean_rows[]=$clean_row;
            }
            if ($errors) continue;
            $entry_data[$field['label']] = $clean_rows;
            continue;
        }
        if ($type === 'file') {
            $files_raw = $_FILES[$key] ?? null;
            $urls = [];
            if (is_array($files_raw) && isset($files_raw['name'])) {
                if (is_array($files_raw['name'])) {
                    foreach ($files_raw['name'] as $idx=>$name) {
                        $one = ['name'=>$name,'type'=>$files_raw['type'][$idx] ?? '','tmp_name'=>$files_raw['tmp_name'][$idx] ?? '','error'=>$files_raw['error'][$idx] ?? UPLOAD_ERR_NO_FILE,'size'=>$files_raw['size'][$idx] ?? 0];
                        if (($one['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) continue;
                        $stored = ssb_form_store_upload($one,$field); if (is_wp_error($stored)) $errors[]=$stored->get_error_message(); elseif($stored) $urls[]=$stored;
                        if (empty($field['file_multiple']) && $urls) break;
                    }
                } elseif (($files_raw['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
                    $stored=ssb_form_store_upload($files_raw,$field); if(is_wp_error($stored)) $errors[]=$stored->get_error_message(); elseif($stored) $urls[]=$stored;
                }
            }
            $value = !empty($field['file_multiple']) ? $urls : ($urls[0] ?? '');
        } elseif (in_array($type,['name','address','booking'],true) && is_array($raw)) {
            $value = ssb_form_sanitize_recursive($raw);
        } elseif ($type === 'checkbox') {
            $value = is_array($raw) ? implode(', ', array_map('sanitize_text_field',$raw)) : sanitize_text_field($raw);
        } elseif ($type === 'toggle') {
            $value = !empty($raw) ? '1' : '0';
        } elseif ($type === 'hidden') {
            $value = ssb_form_resolve_hidden_value($field['hidden_value'] ?? '');
        } else {
            $value = sanitize_text_field($raw);
        }

        $empty = is_array($value) ? !array_filter($value, fn($v)=>is_array($v)?array_filter($v):trim((string)$v)!=='') : trim((string)$value)==='';
        if ( ! empty($field['required']) && $empty ) {
            $message = $field['label'] . ' is required.'; $errors[]=$message; $field_errors[$key]=$message;
        }
        if ( $type === 'email' && $value && !is_email($value) ) {
            $message=$field['label'].' must be a valid email address.'; $errors[]=$message; $field_errors[$key]=$message;
        }
        if ( $type === 'url' && $value ) {
            $url = esc_url_raw($value); if (!$url || !wp_http_validate_url($url)) { $message=$field['label'].' must be a valid URL.'; $errors[]=$message; $field_errors[$key]=$message; }
        }
        if ($type === 'terms' && !empty($field['required']) && empty($raw)) {
            $message='You must accept '.$field['label'].'.'; $errors[]=$message; $field_errors[$key]=$message;
        }
        if ($type === 'booking') {
            $booking_date = sanitize_text_field($value['date'] ?? '');
            $booking_time = sanitize_text_field($value['time'] ?? '');
            $booking_tz = sanitize_text_field($value['timezone'] ?? $client_tz_id ?? 'UTC');
            if ($booking_date && $booking_time) { $date_field_value=$booking_date; $selected_timeslot=$booking_time; $client_tz_id=$booking_tz ?: $client_tz_id; }
            $booking_field_present = true;
            $booking_duration = max(5, min(480, intval($field['booking_duration'] ?? 30)));
            $booking_use_freebusy = $field['booking_use_google_freebusy'] ?? true;
        }
        if ( in_array($type,['timeslot','time'],true) && $value ) $selected_timeslot = is_scalar($value) ? (string)$value : '';
        if ( $type === 'date' && $value ) $date_field_value = (string)$value;
        if ( $type === 'datetime' && $value ) {
            $parts=preg_split('/[T ]/',(string)$value,2); $date_field_value=$parts[0]??''; $selected_timeslot=$parts[1]??'';
        }
        $entry_data[$field['label']] = $value;
    }

    if ( $errors ) {
        wp_send_json_error(['msg' => implode(' ', $errors), 'errors' => $field_errors]);
    }

    // Verify a per-form Stripe payment before saving the entry.
    $integrations = $form->settings['integrations'] ?? [];
    $stripe_cfg = $integrations['stripe'] ?? [];
    $payment_intent_id = sanitize_text_field($_POST['payment_intent_id'] ?? '');
    if (!empty($stripe_cfg['enabled']) && floatval($stripe_cfg['amount'] ?? 0) > 0) {
        if (!$payment_intent_id) wp_send_json_error(['msg'=>'Payment is required before submitting this form.']);
        $secret = get_option('ssb_stripe_secret_key','');
        $check = $secret ? wp_remote_get('https://api.stripe.com/v1/payment_intents/'.rawurlencode($payment_intent_id), [
            'headers'=>['Authorization'=>'Bearer '.$secret], 'timeout'=>20
        ]) : false;
        $pi = ($check && !is_wp_error($check)) ? json_decode(wp_remote_retrieve_body($check),true) : [];
        $expected_amount = (int)round(floatval($stripe_cfg['amount']) * 100);
        if (empty($pi['id']) || !in_array(($pi['status'] ?? ''), ['succeeded','requires_capture'], true) || intval($pi['amount'] ?? 0) !== $expected_amount) {
            wp_send_json_error(['msg'=>'Payment could not be verified. Please try again.']);
        }
    }

    // ── Timeslot conflict check ─────────────────────────────────────────────
    // If the form has a timeslot field and a date, verify the slot isn't already booked
    if ( $selected_timeslot && $date_field_value ) {
        $tz_id    = $client_tz_id ?: 'America/New_York';
        $canonical = ssb_parse_to_canonical( $date_field_value, $selected_timeslot, $tz_id, $booking_duration );
        if ( $canonical ) {
            $bookings_table = $wpdb->prefix . 'schedule_bookings';
            $table_exists   = $wpdb->get_var( "SHOW TABLES LIKE '$bookings_table'" );
            if ( $table_exists ) {
                $conflict = $wpdb->get_var( $wpdb->prepare(
                    "SELECT id FROM $bookings_table WHERE canonical_start = %s AND status = 'confirmed' LIMIT 1",
                    $canonical['start']->format('Y-m-d H:i:s')
                ));
                if ( $conflict ) {
                    wp_send_json_error(['msg' => 'Sorry, the selected time slot (' . esc_html($selected_timeslot) . ') is already booked. Please choose a different time.', 'slot_taken' => true]);
                }
            }
        }
    }

    // Google Calendar is authoritative for real appointment fields when enabled.
    $settings = $form->settings;
    $integrations = $settings['integrations'] ?? [];
    if ( ! empty($integrations['google_calendar']['enabled']) && $canonical && ($booking_field_present ? $booking_use_freebusy : true) && function_exists('ssb_google_calendar_is_free') ) {
        $start_iso = $canonical['start']->setTimezone(new DateTimeZone('UTC'))->format(DateTime::ATOM);
        $end_iso   = $canonical['end']->setTimezone(new DateTimeZone('UTC'))->format(DateTime::ATOM);
        $free = ssb_google_calendar_is_free($start_iso, $end_iso, $integrations['google_calendar']['calendar_id'] ?? '');
        if (is_wp_error($free)) wp_send_json_error(['msg'=>'Google Calendar availability could not be checked. Please try again or disable Calendar availability checking.']);
        if (!$free) wp_send_json_error(['msg'=>'That time is already busy on the connected Google Calendar. Please choose another time.','slot_taken'=>true]);
    }
    // ── Build timezone info for email ───────────────────────────────────────
    $tz_info = '';
    if ( $client_tz_id ) {
        $eastern_tz  = new DateTimeZone('America/New_York');
        $eastern_now = new DateTime('now', $eastern_tz);
        $eastern_abbr = $eastern_now->format('T'); // EST or EDT

        $client_label = $client_tz_display ?: $client_tz_id;

        // If there's a timeslot + date, convert the specific time to Eastern
        if ( $selected_timeslot && $date_field_value ) {
            try {
                $dt_client = new DateTime( $date_field_value . ' ' . $selected_timeslot, new DateTimeZone($client_tz_id) );
                $dt_eastern = clone $dt_client;
                $dt_eastern->setTimezone( $eastern_tz );
                $eastern_time_str = $dt_eastern->format('g:i A') . ' ' . $eastern_abbr . ' (' . $dt_eastern->format('D, M j, Y') . ')';
                $client_time_str  = $dt_client->format('g:i A') . ' — ' . $client_label;
                $tz_info = $client_time_str . ' / ' . $eastern_time_str;
            } catch ( Exception $e ) {
                $tz_info = $client_label;
            }
        } else {
            $tz_info = $client_label;
        }
    }

    // Save entry (append integration metadata to entry_data for record keeping)
    $entry_data_to_save = $entry_data;
    if ( $tz_info ) {
        $entry_data_to_save['__timezone__'] = $tz_info;
    }
    if ( $client_tz_id ) {
        $entry_data_to_save['__timezone_id__'] = sanitize_text_field($client_tz_id);
    }
    if ( $payment_intent_id ) $entry_data_to_save['__payment_intent__'] = $payment_intent_id;

    $wpdb->insert( $wpdb->prefix . 'ssb_form_entries', [
        'form_id'    => $form_id,
        'entry_data' => wp_json_encode( $entry_data_to_save ),
        'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '',
        'user_agent' => substr( $_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500 ),
        'status'     => 'unread',
    ]);
    $saved_entry_id = (int) $wpdb->insert_id;

    // Per-form Google Calendar integration. Global OAuth credentials are reused,
    // while calendar ID, label and brand can be selected independently per form.
    if ( ! empty($integrations['google_calendar']['enabled']) && $selected_timeslot && $date_field_value && $canonical ) {
        $email = '';
        $phone = '';
        $first = '';
        $last = '';
        foreach ($form->fields as $field) {
            $v = $entry_data[$field['label']] ?? '';
            if ($field['type'] === 'email' && !$email) $email = $v;
            if ($field['type'] === 'phone' && !$phone) $phone = $v;
            if (!$first && stripos($field['label'],'first') !== false) $first = $v;
            if (!$last && stripos($field['label'],'last') !== false) $last = $v;
        }
        if (!$first && !$last) {
            foreach ($form->fields as $field) {
                if (stripos($field['label'],'name') !== false && !empty($entry_data[$field['label']])) {
                    $name_value = $entry_data[$field['label']];
                    if (is_array($name_value)) { $first = sanitize_text_field($name_value['first'] ?? ''); $last = sanitize_text_field($name_value['last'] ?? ''); }
                    else { $parts = preg_split('/\s+/', trim((string)$name_value), 2); $first = $parts[0] ?? ''; $last = $parts[1] ?? ''; }
                    break;
                }
            }
        }
        if ($canonical) {
            $end = clone $canonical['start'];
            $end->modify('+' . $booking_duration . ' minutes');
            $calendar_booking = [
                'first_name' => $first ?: 'Guest',
                'last_name' => $last,
                'email' => $email,
                'phone' => $phone,
                'practice_type' => '',
                'booking_date' => $date_field_value,
                'booking_time' => $selected_timeslot,
                'timezone' => $client_tz_id ?: 'UTC',
                'timezone_display' => $client_tz_display ?: ($client_tz_id ?: 'UTC'),
                'canonical_start_iso' => $canonical['start']->format(DateTime::ATOM),
                'canonical_end_iso' => $end->format(DateTime::ATOM),
            ];
            $google_result = ssb_create_google_calendar_event($calendar_booking, $integrations['google_calendar']);
            if ( $saved_entry_id && ! is_wp_error($google_result) ) {
                $entry_meta = $entry_data_to_save;
                if ( ! empty($google_result['event_id']) ) $entry_meta['__google_event_id__'] = sanitize_text_field($google_result['event_id']);
                if ( ! empty($google_result['meet_link']) ) $entry_meta['__meeting_url__'] = esc_url_raw($google_result['meet_link']);
                if ( ! empty($google_result['html_link']) ) $entry_meta['__calendar_url__'] = esc_url_raw($google_result['html_link']);
                $wpdb->update($wpdb->prefix . 'ssb_form_entries', ['entry_data'=>wp_json_encode($entry_meta)], ['id'=>$saved_entry_id]);
            }

            // Also create a legacy scheduling record so Calendar Booking forms appear in Scheduling → Bookings.
            if ($canonical && $booking_field_present) {
                $bookings_table = $wpdb->prefix . 'schedule_bookings';
                if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $bookings_table))) {
                    $exists = $wpdb->get_var($wpdb->prepare("SELECT id FROM $bookings_table WHERE canonical_start=%s AND status IN ('pending','confirmed') LIMIT 1", $canonical['start']->format('Y-m-d H:i:s')));
                    if (!$exists) {
                        $payment_status = $payment_intent_id ? 'paid' : 'unpaid';
                        $fee_amount = !empty($stripe_cfg['enabled']) ? floatval($stripe_cfg['amount'] ?? 0) : 0;
                        $fee_currency = sanitize_text_field($stripe_cfg['currency'] ?? 'USD');
                        $wpdb->insert($bookings_table,[
                            'first_name'=>$first ?: 'Guest','last_name'=>$last,'email'=>$email,'phone'=>$phone,
                            'practice_type'=>'','booking_date'=>$date_field_value,'booking_time'=>$selected_timeslot,
                            'timezone'=>$client_tz_id ?: 'UTC','timezone_display'=>$client_tz_display ?: ($client_tz_id ?: 'UTC'),
                            'custom_answers'=>wp_json_encode(['form_id'=>$form_id,'entry_id'=>$saved_entry_id,'form'=>$form->form_name]),
                            'canonical_start'=>$canonical['start']->format('Y-m-d H:i:s'),'canonical_end'=>$canonical['end']->format('Y-m-d H:i:s'),
                            'google_event_id'=>$google_result['event_id'] ?? '','google_meet_link'=>$google_result['meet_link'] ?? '','google_html_link'=>$google_result['html_link'] ?? '',
                            'payment_intent_id'=>$payment_intent_id,'payment_status'=>$payment_status,'fee_amount'=>$fee_amount,'fee_currency'=>$fee_currency,'status'=>'confirmed'
                        ]);
                    }
                }
            }
        }
    }

    // Send notification email (pass timezone info)
    ssb_send_form_notification( $form, $entry_data, $tz_info );

    // Send copy to submitter if enabled

    if ( ! empty($settings['send_copy']) ) {
        $submitter_email = '';
        foreach ( $form->fields as $field ) {
            if ( $field['type'] === 'email' ) {
                $submitter_email = $entry_data[ $field['label'] ] ?? '';
                break;
            }
        }
        if ( $submitter_email ) {
            ssb_send_form_copy( $form, $entry_data, $submitter_email );
        }
    }

    $redirect = $settings['redirect_url'] ?? '';
    wp_send_json_success([
        'msg'         => $settings['success_msg'] ?? 'Thank you! Your message has been sent.',
        'redirect'    => $redirect,
    ]);
}

// ── Mark entry read / delete entry ────────────────────────────────────────
add_action( 'wp_ajax_ssb_update_entry', 'ssb_ajax_update_entry' );
function ssb_ajax_update_entry() {
    if ( ! current_user_can('manage_options') ) wp_send_json_error(['msg' => 'Unauthorized']);
    if ( ! ssb_is_paid() && empty($_POST['form_id']) && ssb_free_form_count() >= ssb_free_limits()['forms'] ) wp_send_json_error(['msg'=>ssb_free_limit_notice('forms'),'upgrade'=>true],403);
    check_ajax_referer( 'ssb_forms_nonce', 'nonce' );
    if ( ! current_user_can('manage_options') ) wp_send_json_error();

    global $wpdb;
    $entry_id = intval( $_POST['entry_id'] ?? 0 );
    $action   = sanitize_key( $_POST['entry_action'] ?? '' );

    if ( $action === 'delete' ) {
        $wpdb->delete( $wpdb->prefix . 'ssb_form_entries', ['id' => $entry_id] );
        wp_send_json_success(['msg' => 'Entry deleted']);
    } elseif ( $action === 'read' ) {
        $wpdb->update( $wpdb->prefix . 'ssb_form_entries', ['status' => 'read'], ['id' => $entry_id] );
        wp_send_json_success(['msg' => 'Marked as read']);
    }
    wp_send_json_error();
}

// ── Email notifications ────────────────────────────────────────────────────
function ssb_form_display_value($value) {
    if (is_array($value)) {
        $parts=[]; foreach($value as $k=>$v){ $dv=ssb_form_display_value($v); if($dv!=='') $parts[] = is_string($k) ? ucfirst(str_replace('_',' ',$k)).': '.$dv : $dv; }
        return implode(' · ',$parts);
    }
    return (string)$value;
}

function ssb_send_form_notification( $form, $entry_data, $tz_info = '' ) {
    $settings = $form->settings;
    $raw_emails = $settings['notify_email'] ?? get_option('admin_email');

// Support comma, semicolon, or newline separated emails
$emails = preg_split('/[,;\r\n]+/', $raw_emails);

$emails = array_map('trim', $emails);

$emails = array_filter($emails, function($email) {
    return is_email($email);
});

if (empty($emails)) {
    $emails = [get_option('admin_email')];
}
    $subject  = ! empty($settings['email_subject']) ? $settings['email_subject'] : $form->form_name;
    $headers  = ssb_get_email_headers();

    $rows = '';
    foreach ( $entry_data as $label => $value ) {
        if ( substr($label, 0, 2) === '__' ) continue;
        $rows .= '<tr>
            <td style="padding:10px 14px;font-weight:700;background:#f9fafb;border:1px solid #e5e7eb;width:35%;font-family:Plus Jakarta Sans,Arial,sans-serif;">' . esc_html($label) . '</td>
            <td style="padding:10px 14px;border:1px solid #e5e7eb;font-family:Plus Jakarta Sans,Arial,sans-serif;">' . nl2br(esc_html(ssb_form_display_value($value))) . '</td>
        </tr>';
    }

    $tz_row = '';
    if ( $tz_info ) {
        $eastern_abbr = 'ET';
        try {
            $now_et = new DateTime('now', new DateTimeZone('America/New_York'));
            $eastern_abbr = $now_et->format('T');
        } catch (Exception $e) {}
        $tz_row = '<tr><td colspan="2" style="padding:12px 16px;background:#eff6ff;border:1px solid #bfdbfe;">'
            . '<span style="font-family:Plus Jakarta Sans,Arial,sans-serif;font-size:13px;color:#1e40af;">'
            . '&#127758; <strong>Client Timezone &amp; Slot:</strong> ' . esc_html($tz_info)
            . ' &nbsp;&middot;&nbsp; <em>You manage in Eastern Time (' . esc_html($eastern_abbr) . ')</em>'
            . '</span></td></tr>';
    }

    $message = '<!DOCTYPE html><html><body style="font-family:Plus Jakarta Sans,Arial,sans-serif;color:#333;margin:0;padding:0;">'
        . '<div style="max-width:600px;margin:0 auto;padding:20px;">'
        . '<div style="background:linear-gradient(135deg,#667eea,#764ba2);padding:24px;border-radius:10px 10px 0 0;color:#fff;">'
        . '<h2 style="margin:0;font-family:Plus Jakarta Sans,Arial,sans-serif;">&#128236; ' . esc_html($subject) . '</h2>'
        . '<p style="margin:8px 0 0;opacity:0.85;font-family:Plus Jakarta Sans,Arial,sans-serif;">Form: <strong>' . esc_html($form->form_name) . '</strong></p>'
        . '</div>'
        . '<div style="background:#fff;padding:24px;border:1px solid #e5e7eb;border-radius:0 0 10px 10px;">'
        . ( $tz_row ? '<table style="width:100%;border-collapse:collapse;margin-bottom:14px;">' . $tz_row . '</table>' : '' )
        . '<table style="width:100%;border-collapse:collapse;">' . $rows . '</table>'
        . '<p style="color:#6b7280;font-size:12px;margin-top:20px;font-family:Plus Jakarta Sans,Arial,sans-serif;">Submitted: ' . current_time('Y-m-d H:i:s') . '</p>'
        . '</div></div></body></html>';

    foreach ($emails as $email) {
    wp_mail($email, $subject, $message, $headers);
}
}
function ssb_send_form_copy( $form, $entry_data, $to_email ) {
    $settings = $form->settings;
    $subject  = 'Your submission to: ' . $form->form_name;
    $headers  = ssb_get_email_headers();

    $rows = '';
    foreach ( $entry_data as $label => $value ) {
        $rows .= '<tr>
            <td style="padding:8px 12px;font-weight:bold;color:#667eea;">' . esc_html($label) . '</td>
            <td style="padding:8px 12px;">' . nl2br(esc_html(ssb_form_display_value($value))) . '</td>
        </tr>';
    }

    $message = '<!DOCTYPE html><html><body style="font-family:Arial,sans-serif;color:#333;">
    <div style="max-width:600px;margin:0 auto;padding:20px;">
        <div style="background:linear-gradient(135deg,#667eea,#764ba2);padding:24px;border-radius:10px 10px 0 0;color:#fff;">
            <h2 style="margin:0;">✅ We received your message!</h2>
        </div>
        <div style="background:#fff;padding:24px;border:1px solid #e5e7eb;border-radius:0 0 10px 10px;">
            <p>Thank you for contacting us via <strong>' . esc_html($form->form_name) . '</strong>. Here is a copy of what you submitted:</p>
            <table style="width:100%;border-collapse:collapse;background:#f9fafb;border-radius:8px;">' . $rows . '</table>
            <p style="color:#6b7280;font-size:13px;margin-top:16px;">We will get back to you as soon as possible.</p>
        </div>
    </div></body></html>';

    wp_mail( $to_email, $subject, $message, $headers );
}
