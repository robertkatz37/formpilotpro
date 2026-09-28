<?php
if ( ! defined( 'ABSPATH' ) ) exit;

add_action( 'admin_menu', 'ssb_register_menus' );
function ssb_register_menus() {
    // FormPilot uses a small number of clear product areas. Detailed pages are
    // registered as hidden admin screens and opened from the relevant hub page.
    add_menu_page(
        'FormPilot Pro', 'FormPilot Pro', 'manage_options',
        'ssb-dashboard', 'ssb_dashboard_page', 'dashicons-calendar-alt', 25
    );

    // Always-visible shell. Free users only get Forms, Settings (SMTP) and License
    // as actionable product areas; Pro users get the complete workspace.
    add_submenu_page('ssb-dashboard','Dashboard','Dashboard','manage_options','ssb-dashboard','ssb_dashboard_page');
    add_submenu_page('ssb-dashboard','Forms','Forms','manage_options','ssb-forms','ssb_forms_list_page');
    if ( function_exists('ssb_is_paid') && ssb_is_paid() ) {
        add_submenu_page('ssb-dashboard','Scheduling','Scheduling','manage_options','fp-scheduling-hub','ssb_scheduling_hub_page');
        add_submenu_page('ssb-dashboard','Integrations','Integrations','manage_options','fp-integrations','fp_adv_integrations_page');
        add_submenu_page('ssb-dashboard','Payments','Payments','manage_options','fp-payments-hub','ssb_payments_hub_page');
        add_submenu_page('ssb-dashboard','Automation','Automation','manage_options','fp-automation-hub','ssb_automation_hub_page');
        add_submenu_page('ssb-dashboard','Contacts & CRM','Contacts & CRM','manage_options','fp-contacts-hub','ssb_contacts_hub_page');
        add_submenu_page('ssb-dashboard','Analytics','Analytics','manage_options','fp-analytics','fp_sched_analytics_page');
        add_submenu_page('ssb-dashboard','Templates & Branding','Templates & Branding','manage_options','fp-growth-hub','ssb_growth_hub_page');
        add_submenu_page('ssb-dashboard','Developer','Developer','manage_options','fp-developer-hub','ssb_developer_hub_page');
        add_submenu_page('ssb-dashboard','Help & Launch Guide','Help & Launch Guide','manage_options','fp-help','fp_help_page');
    }
    add_submenu_page('ssb-dashboard','Settings','Settings','manage_options','ssb-settings','ssb_settings_page');
    add_submenu_page('ssb-dashboard','License','License','manage_options','ssb-license','ssb_license_page');

    // Detailed Pro screens are intentionally hidden from the sidebar. They are
    // reached from the category hubs, which keeps the WordPress menu readable.
    $hidden = [
        ['fp-scheduling','Event Types','fp_sched_events_page'],
        ['fp-availability','Availability','fp_sched_availability_page'],
        ['fp-bookings','Scheduling Bookings','fp_sched_bookings_page'],
        ['fp-teams','Teams','fp_sched_teams_page'],
        ['fp-routing','Routing','fp_sched_routing_page'],
        ['fp-workflows','Automations','fp_sched_workflows_page'],
        ['fp-polls','Meeting Polls','fp_sched_polls_page'],
        ['fp-links','Single-use Links','fp_sched_links_page'],
        ['fp-contacts','Contacts','fp_sched_contacts_page'],
        ['fp-integration-google','Google Calendar','fp_adv_integration_google_page'],
        ['fp-integration-stripe','Stripe','fp_adv_integration_stripe_page'],
        ['fp-integration-zoom','Zoom','fp_adv_integration_zoom_page'],
        ['fp-integration-teams','Microsoft Teams','fp_adv_integration_teams_page'],
        ['fp-integration-hubspot','HubSpot','fp_adv_integration_hubspot_page'],
        ['fp-integration-salesforce','Salesforce','fp_adv_integration_salesforce_page'],
        ['fp-branding','Branding','fp_adv_branding_page'],
        ['ssb-templates','Form Templates','ssb_templates_page'],
        ['fp-invoices','Invoices','fp_adv_invoices_page'],
        ['fp-api','API & Webhooks','fp_sched_api_page'],
        ['fp-api-keys','API Keys','fp_adv_api_keys_page'],
        ['fp-marketplace','Template Marketplace','fp_adv_marketplace_page'],
        ['ssb-payment-buttons','Stripe Payment Buttons','ssb_payment_buttons_page'],
        ['fp-demos','Demos & Examples','fp_adv_demos_page'],
        ['ssb-smart-booking-form','Smart Schedule Booking','ssb_smart_booking_form_page'],
        ['fp-routing-builder','Routing Builder','fp_adv_routing_builder_page'],
    ];
    foreach ($hidden as $screen) {
        add_submenu_page(null, $screen[1], $screen[1], 'manage_options', $screen[0], $screen[2]);
    }
}

/** Free plan: Forms + SMTP only. Everything else is a Pro workspace feature. */
function ssb_pro_admin_page_slugs() {
    return [
        'ssb-bookings','fp-scheduling-hub','fp-scheduling','fp-availability','fp-bookings','fp-teams','fp-routing',
        'fp-routing-builder','fp-integrations','fp-integration-google','fp-integration-stripe','fp-integration-zoom',
        'fp-integration-teams','fp-integration-hubspot','fp-integration-salesforce','fp-payments-hub','ssb-payment-buttons',
        'fp-invoices','fp-automation-hub','fp-workflows','fp-polls','fp-links','fp-contacts-hub','fp-contacts','fp-analytics',
        'fp-growth-hub','fp-branding','ssb-templates','fp-marketplace','fp-demos','fp-developer-hub','fp-api','fp-api-keys','fp-help'
    ];
}
function ssb_enforce_pro_admin_pages() {
    if (!is_admin() || !current_user_can('manage_options') || ssb_is_paid()) return;
    $page = sanitize_key($_GET['page'] ?? '');
    if ($page && in_array($page, ssb_pro_admin_page_slugs(), true)) {
        wp_safe_redirect(admin_url('admin.php?page=ssb-forms&fp_upgrade=1'));
        exit;
    }
}
add_action('admin_init', 'ssb_enforce_pro_admin_pages', 1);

function ssb_admin_hub_card($title, $description, $url, $icon='→', $locked=false) {
    $class = 'fp-hub-card'.($locked ? ' is-locked' : '');
    echo '<a class="'.esc_attr($class).'" href="'.esc_url($url).'">';
    echo '<span class="fp-hub-icon">'.esc_html($icon).'</span><span class="fp-hub-copy"><strong>'.esc_html($title).'</strong><small>'.esc_html($description).'</small></span>';
    echo '<span class="fp-hub-arrow">'.($locked ? 'PRO' : '→').'</span></a>';
}
function ssb_pro_hub_guard($title='This area') {
    if (ssb_is_paid()) return true;
    echo '<div class="wrap fp-product-admin"><div class="fp-upgrade-panel"><span class="fp-card-kicker">FORMPILOT PRO</span><h1>'.esc_html($title).' requires Pro</h1><p>Free includes the Form Builder and Email & SMTP. Activate a FormPilot license to unlock this workspace.</p><p><a class="button button-primary button-large" href="'.esc_url(admin_url('admin.php?page=ssb-license')).'">Activate Pro License</a></p></div></div>';
    return false;
}
function ssb_scheduling_hub_page(){ if(!ssb_pro_hub_guard('Scheduling'))return; echo '<div class="wrap fp-product-admin"><div class="fp-hub-hero"><div><span>FORMPILOT PRO</span><h1>Scheduling</h1><p>Build event types, availability, teams and routing in one place.</p></div></div><div class="fp-hub-grid">'; ssb_admin_hub_card('Event Types','Create and manage bookable meeting types.',admin_url('admin.php?page=fp-scheduling'),'◷'); ssb_admin_hub_card('Availability','Set weekly hours and booking windows.',admin_url('admin.php?page=fp-availability'),'⌚'); ssb_admin_hub_card('Scheduling Bookings','Review and manage scheduler bookings.',admin_url('admin.php?page=fp-bookings'),'✓'); ssb_admin_hub_card('Teams','Assign hosts and build team scheduling.',admin_url('admin.php?page=fp-teams'),'◉'); ssb_admin_hub_card('Routing','Send visitors to the right event or host.',admin_url('admin.php?page=fp-routing'),'↗'); ssb_admin_hub_card('Routing Builder','Create qualification questions and rules.',admin_url('admin.php?page=fp-routing-builder'),'☷'); echo '</div></div>'; }
function ssb_payments_hub_page(){ if(!ssb_pro_hub_guard('Payments'))return; echo '<div class="wrap fp-product-admin"><div class="fp-hub-hero"><div><span>FORMPILOT PRO</span><h1>Payments</h1><p>Connect Stripe, charge for events and manage customer invoices.</p></div></div><div class="fp-hub-grid">'; ssb_admin_hub_card('Stripe','Connect Stripe and accept paid bookings.',admin_url('admin.php?page=fp-integration-stripe'),'S'); ssb_admin_hub_card('Payment Buttons','Create reusable Stripe payment buttons.',admin_url('admin.php?page=ssb-payment-buttons'),'$'); ssb_admin_hub_card('Invoices','Review invoices generated from paid bookings.',admin_url('admin.php?page=fp-invoices'),'▤'); echo '</div></div>'; }
function ssb_automation_hub_page(){ if(!ssb_pro_hub_guard('Automation'))return; echo '<div class="wrap fp-product-admin"><div class="fp-hub-hero"><div><span>FORMPILOT PRO</span><h1>Automation</h1><p>Automate reminders, follow-up, polls and one-time booking access.</p></div></div><div class="fp-hub-grid">'; ssb_admin_hub_card('Automations','Trigger email and webhook workflows around bookings.',admin_url('admin.php?page=fp-workflows'),'⚡'); ssb_admin_hub_card('Meeting Polls','Let invitees vote on available meeting times.',admin_url('admin.php?page=fp-polls'),'☑'); ssb_admin_hub_card('Single-use Links','Create controlled booking links with limits.',admin_url('admin.php?page=fp-links'),'↗'); echo '</div></div>'; }
function ssb_contacts_hub_page(){ if(!ssb_pro_hub_guard('Contacts & CRM'))return; echo '<div class="wrap fp-product-admin"><div class="fp-hub-hero"><div><span>FORMPILOT PRO</span><h1>Contacts & CRM</h1><p>Review contacts and connect customer data to your CRM.</p></div></div><div class="fp-hub-grid">'; ssb_admin_hub_card('Contacts','View people captured through bookings.',admin_url('admin.php?page=fp-contacts'),'◎'); ssb_admin_hub_card('HubSpot','Sync booking contacts into HubSpot.',admin_url('admin.php?page=fp-integration-hubspot'),'H'); ssb_admin_hub_card('Salesforce','Sync scheduler contacts with Salesforce.',admin_url('admin.php?page=fp-integration-salesforce'),'S'); echo '</div></div>'; }
function ssb_growth_hub_page(){ if(!ssb_pro_hub_guard('Templates & Branding'))return; $template_count=function_exists('ssb_template_library')?count(ssb_template_library()):0; echo '<div class="wrap fp-product-admin"><div class="fp-hub-hero"><div><span>FORMPILOT PRO</span><h1>Templates & Branding</h1><p>Start faster with ready-made form templates, scheduling templates and a polished customer experience.</p></div></div><div class="fp-hub-grid">'; ssb_admin_hub_card('Form Templates',$template_count.' ready-made forms you can install and customize.',admin_url('admin.php?page=ssb-templates'),'▦'); ssb_admin_hub_card('Scheduling Templates','Start event scheduling from ready-made booking setups.',admin_url('admin.php?page=fp-marketplace'),'◇'); ssb_admin_hub_card('Branding','Customize the scheduler appearance and identity.',admin_url('admin.php?page=fp-branding'),'✦'); ssb_admin_hub_card('Demos & Examples','See complete FormPilot workflows before launch.',admin_url('admin.php?page=fp-demos'),'▶'); echo '</div></div>'; }
function ssb_developer_hub_page(){ if(!ssb_pro_hub_guard('Developer'))return; echo '<div class="wrap fp-product-admin"><div class="fp-hub-hero"><div><span>FORMPILOT PRO</span><h1>Developer</h1><p>Connect external systems with API keys and signed webhooks.</p></div></div><div class="fp-hub-grid">'; ssb_admin_hub_card('API & Webhooks','Receive booking events and use the business API.',admin_url('admin.php?page=fp-api'),'⌘'); ssb_admin_hub_card('API Keys','Create authenticated keys for your business API.',admin_url('admin.php?page=fp-api-keys'),'⚿'); echo '</div></div>'; }

function ssb_dashboard_page() {
    if (!current_user_can('manage_options')) return;
    global $wpdb;
    $t = function_exists('fp_sched_tables') ? fp_sched_tables() : [];
    $uid = get_current_user_id();
    $events = !empty($t['events']) ? (int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$t['events']} WHERE user_id=%d", $uid)) : 0;
    $bookings = !empty($t['bookings']) ? (int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$t['bookings']} b JOIN {$t['events']} e ON e.id=b.event_id WHERE e.user_id=%d", $uid)) : 0;
    $contacts = !empty($t['contacts']) ? (int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$t['contacts']} WHERE user_id=%d", $uid)) : 0;
    $state = function_exists('ssb_license_status') ? ssb_license_status() : ['active'=>false,'plan'=>''];
    ?>
    <div class="wrap fp-product-admin">
      <div class="fp-product-hero"><div><div class="fp-product-kicker">FORMPILOT PRO</div><h1>Everything you need to collect, schedule and convert</h1><p>Forms, scheduling, payments, automations, integrations and analytics in one workspace.</p></div><div class="fp-license-pill <?php echo !empty($state['active'])?'is-active':'is-free'; ?>"><?php echo !empty($state['active'])?'● License active':'Free plan'; ?><?php if(!empty($state['plan'])) echo ' · '.esc_html(ucfirst($state['plan'])); ?></div></div>
      <div class="fp-stat-grid"><a href="<?php echo esc_url(admin_url('admin.php?page=fp-scheduling')); ?>"><strong><?php echo (int)$events; ?></strong><span>Event types</span></a><a href="<?php echo esc_url(admin_url('admin.php?page=fp-bookings')); ?>"><strong><?php echo (int)$bookings; ?></strong><span>Scheduling bookings</span></a><a href="<?php echo esc_url(admin_url('admin.php?page=fp-contacts')); ?>"><strong><?php echo (int)$contacts; ?></strong><span>Contacts</span></a><a href="<?php echo esc_url(admin_url('admin.php?page=fp-integrations')); ?>"><strong>→</strong><span>Integrations</span></a></div>
      <div class="fp-dashboard-grid">
        <div class="fp-dashboard-card"><div class="fp-card-kicker">SCHEDULING</div><h2>Build your booking experience</h2><p>Create event types, configure availability, manage teams and publish branded scheduling pages.</p><div class="fp-action-row"><a class="button button-primary" href="<?php echo esc_url(admin_url('admin.php?page=fp-scheduling')); ?>">Manage event types</a><a class="button" href="<?php echo esc_url(admin_url('admin.php?page=fp-availability')); ?>">Availability</a></div></div>
        <div class="fp-dashboard-card"><div class="fp-card-kicker">CONNECTIONS</div><h2>Connect your business tools</h2><p>Manage Google Calendar, Stripe, Zoom, Microsoft Teams, HubSpot and Salesforce from one integration center.</p><div class="fp-action-row"><a class="button button-primary" href="<?php echo esc_url(admin_url('admin.php?page=fp-integrations')); ?>">Open integrations</a></div></div>
        <div class="fp-dashboard-card"><div class="fp-card-kicker">AUTOMATION</div><h2>Keep follow-up moving</h2><p>Use reminders, workflows, webhooks, routing and CRM synchronization without leaving FormPilot.</p><div class="fp-action-row"><a class="button" href="<?php echo esc_url(admin_url('admin.php?page=fp-workflows')); ?>">Automations</a><a class="button" href="<?php echo esc_url(admin_url('admin.php?page=fp-api')); ?>">API & Webhooks</a></div></div>
        <div class="fp-dashboard-card"><div class="fp-card-kicker">CUSTOMER EXPERIENCE</div><h2>Polish every touchpoint</h2><p>Brand your scheduler, publish reusable templates, create single-use links and review analytics.</p><div class="fp-action-row"><a class="button" href="<?php echo esc_url(admin_url('admin.php?page=fp-branding')); ?>">Branding</a><a class="button" href="<?php echo esc_url(admin_url('admin.php?page=fp-marketplace')); ?>">Templates</a></div></div>
      </div>
    </div>
    <?php
}

add_action( 'admin_enqueue_scripts', 'ssb_admin_assets' );
function ssb_admin_assets( $hook ) {
    wp_enqueue_style('formpilot-admin', SSB_PLUGIN_URL . 'assets/css/formpilot-admin.css', [], SSB_VERSION);
    if ( strpos($hook, 'ssb-') === false && strpos($hook, 'fp-') === false && strpos($hook, 'schedule-booking') === false ) return;
    wp_enqueue_style('wp-color-picker');
    wp_enqueue_script('wp-color-picker');
    wp_enqueue_style('ssb-inter-font', 'https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap', [], null);
    wp_enqueue_style('ssb-admin-pro', SSB_PLUGIN_URL . 'assets/css/admin-pro.css', ['wp-color-picker'], SSB_VERSION);
    wp_enqueue_script('ssb-admin', SSB_PLUGIN_URL . 'assets/js/admin.js', ['jquery','wp-color-picker'], SSB_VERSION, true);
    wp_localize_script('ssb-admin', 'ssbAdmin', [
        'ajaxUrl' => admin_url('admin-ajax.php'),
        'nonce'   => wp_create_nonce('ssb_admin_nonce'),
    ]);
    // Inline responsive admin CSS
    wp_add_inline_style('wp-color-picker', '
        /* SSB Admin — Responsive */
        @media (max-width: 960px) {
            .ssb-admin-wrap { max-width: 100% !important; }
            .ssb-two-col, .ssb-compare-grid { grid-template-columns: 1fr !important; }
        }
        @media (max-width: 782px) {
            /* WP already handles admin menu; fix our content */
            .ssb-admin-wrap .form-table th {
                display: block; width: 100%; padding-bottom: 4px;
                padding-top: 16px; font-size: 13px;
            }
            .ssb-admin-wrap .form-table td {
                display: block; width: 100%; padding-top: 0; padding-left: 0;
            }
            .ssb-admin-wrap .form-table input[type="text"],
            .ssb-admin-wrap .form-table input[type="email"],
            .ssb-admin-wrap .form-table input[type="password"],
            .ssb-admin-wrap .form-table input[type="number"],
            .ssb-admin-wrap .form-table select,
            .ssb-admin-wrap .form-table textarea {
                width: 100% !important; max-width: 100% !important;
                font-size: 14px !important; box-sizing: border-box !important;
            }
            .ssb-admin-wrap .regular-text,
            .ssb-admin-wrap .large-text { width: 100% !important; }
            .ssb-admin-wrap .wp-picker-container { width: 100%; }
            .ssb-admin-wrap .wp-picker-container input[type="text"] { width: auto !important; }
            .ssb-admin-wrap p.submit { padding-top: 10px; }
            .ssb-admin-wrap .button-large { width: 100%; text-align: center; box-sizing: border-box; }
            /* Port buttons */
            .ssb-port-btn { margin-top: 6px !important; }
            /* SMTP test row */
            #ssb-test-email-addr { width: 100% !important; margin-bottom: 8px; }
            .ssb-admin-wrap div[style*="display:flex"] { flex-wrap: wrap !important; }
            /* Preview button */
            #ssb-preview-btn { width: 100% !important; text-align: center; }
        }
        @media (max-width: 480px) {
            .ssb-admin-wrap h2 { font-size: 16px !important; }
            .ssb-admin-wrap h3 { font-size: 14px !important; }
            .nav-tab { font-size: 12px !important; padding: 8px 10px !important; }
            .ssb-panel { padding: 18px !important; }
            code { word-break: break-all; }
        }
    ');
}

function ssb_settings_page() {
    if ( ! current_user_can('manage_options') ) return;
    if ( function_exists('ssb_license_admin_guard') && ! ssb_license_admin_guard('settings') ) return;

    // Free plan intentionally exposes only Email & SMTP inside Settings.
    $active_tab = sanitize_text_field($_GET['tab'] ?? (ssb_is_paid() ? 'appearance' : 'email'));
    if ( ! ssb_is_paid() && $active_tab !== 'email' ) $active_tab = 'email';

    // Notification messages from OAuth callback
    $oauth_msg = sanitize_text_field($_GET['ssb_msg'] ?? '');
    $oauth_err = sanitize_text_field($_GET['ssb_err'] ?? '');

    // Save general settings — only save fields that belong to the current tab's form
    if ( isset($_POST['ssb_save_settings']) && check_admin_referer('ssb_settings_nonce') ) {

        // Appearance tab fields
        $appearance_fields = [
            'ssb_primary_color','ssb_secondary_color','ssb_accent_color','ssb_button_text_color',
            'ssb_border_radius','ssb_font_family','ssb_sidebar_text_color',
        ];

        // Content tab fields
        $content_fields = [
            'ssb_event_title','ssb_event_label','ssb_event_brand_name',
            'ssb_organizer_display_name',
            'ssb_event_duration','ssb_event_description',
            'ssb_logo_letter','ssb_team_label',
        ];

        // Email/SMTP tab fields
        $email_fields = [
            'ssb_support_email','ssb_extra_notify_email',
            'ssb_smtp_host','ssb_smtp_port','ssb_smtp_encryption',
            'ssb_smtp_username','ssb_smtp_password',
            'ssb_smtp_from_email','ssb_smtp_from_name',
        ];

        // Detect which tab was submitted by checking which fields are present in $_POST.
        // Each tab's form only contains its own fields, so we only save what was actually submitted.
        if ( ssb_is_paid() && isset($_POST['ssb_primary_color']) ) {
            // Appearance tab submitted
            foreach ( $appearance_fields as $field ) {
                update_option( $field, sanitize_text_field( $_POST[$field] ?? '' ) );
            }
            $active_tab = 'appearance';
        } elseif ( ssb_is_paid() && (isset($_POST['ssb_event_label']) || isset($_POST['ssb_logo_letter'])) ) {
            // Content tab submitted
            foreach ( $content_fields as $field ) {
                if ( $field === 'ssb_event_description' ) {
                    update_option( $field, wp_kses_post( $_POST[$field] ?? '' ) );
                } else {
                    update_option( $field, sanitize_text_field( $_POST[$field] ?? '' ) );
                }
            }
            $active_tab = 'content';
        } elseif ( isset($_POST['ssb_support_email']) || isset($_POST['ssb_smtp_host']) ) {
            // Email/SMTP tab submitted
            foreach ( $email_fields as $field ) {
                update_option( $field, sanitize_text_field( $_POST[$field] ?? '' ) );
            }
            // Checkbox: only present in POST when checked; explicit 0 when unchecked
            update_option( 'ssb_smtp_enabled', isset($_POST['ssb_smtp_enabled']) ? '1' : '0' );
            $active_tab = 'email';
        }

        echo '<div class="notice notice-success is-dismissible"><p>Settings saved.</p></div>';
    }

    // Save Spam Protection settings
    if ( ssb_is_paid() && isset($_POST['ssb_save_spam']) && check_admin_referer('ssb_spam_settings_nonce') ) {
        update_option('ssb_turnstile_site_key', sanitize_text_field($_POST['ssb_turnstile_site_key'] ?? ''));
        update_option('ssb_turnstile_secret_key', sanitize_text_field($_POST['ssb_turnstile_secret_key'] ?? ''));
        update_option('ssb_recaptcha_site_key', sanitize_text_field($_POST['ssb_recaptcha_site_key'] ?? ''));
        update_option('ssb_recaptcha_secret_key', sanitize_text_field($_POST['ssb_recaptcha_secret_key'] ?? ''));
        $active_tab = 'spam';
        echo '<div class="notice notice-success is-dismissible"><p>Spam protection settings saved.</p></div>';
    }

    // Save Payment settings
    if ( ssb_is_paid() && isset($_POST['ssb_save_payment']) && check_admin_referer('ssb_payment_nonce') ) {
        update_option('ssb_payment_enabled',        isset($_POST['ssb_payment_enabled']) ? '1' : '0');
        update_option('ssb_stripe_publishable_key', sanitize_text_field($_POST['ssb_stripe_publishable_key'] ?? ''));
        update_option('ssb_stripe_secret_key',      sanitize_text_field($_POST['ssb_stripe_secret_key'] ?? ''));
        update_option('ssb_stripe_test_mode',       isset($_POST['ssb_stripe_test_mode']) ? '1' : '0');
        update_option('ssb_booking_price',          sanitize_text_field($_POST['ssb_booking_price'] ?? '0'));
        update_option('ssb_booking_currency',       sanitize_text_field($_POST['ssb_booking_currency'] ?? 'USD'));
        update_option('ssb_payment_description',    sanitize_text_field($_POST['ssb_payment_description'] ?? 'Booking deposit'));
        echo '<div class="notice notice-success is-dismissible"><p>Payment settings saved.</p></div>';
        $active_tab = 'payment';
    }

    // Save Google Calendar settings
    if ( ssb_is_paid() && isset($_POST['ssb_save_google']) && check_admin_referer('ssb_google_settings_nonce') ) {
        update_option('ssb_google_client_id',     sanitize_text_field($_POST['ssb_google_client_id'] ?? ''));
        update_option('ssb_google_client_secret', sanitize_text_field($_POST['ssb_google_client_secret'] ?? ''));
        update_option('ssb_google_calendar_id',   sanitize_text_field($_POST['ssb_google_calendar_id'] ?? 'primary'));
        update_option('ssb_google_timezone',      sanitize_text_field($_POST['ssb_google_timezone'] ?? 'America/New_York'));
        update_option('ssb_google_meet_enabled',  isset($_POST['ssb_google_meet_enabled']) ? '1' : '0');
        echo '<div class="notice notice-success is-dismissible"><p>Google Calendar settings saved.</p></div>';
        $active_tab = 'google';
    }

    $p  = get_option('ssb_primary_color',     '#667eea');
    $s  = get_option('ssb_secondary_color',   '#764ba2');
    $bt = get_option('ssb_button_text_color', '#ffffff');
    $br = get_option('ssb_border_radius',     '12');
    $ff = get_option('ssb_font_family',       'inherit');
    $st = get_option('ssb_sidebar_text_color','#ffffff');
    ?>
    <div class="wrap ssb-settings-page ssb-admin-wrap">
        <div class="ssb-page-header">
            <div class="ssb-page-header-left">
                <div class="ssb-page-icon">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                </div>
                <div>
                    <h1 class="ssb-page-title">Settings</h1>
                    <p class="ssb-page-subtitle">Configure how FormPilot Pro looks and behaves</p>
                </div>
            </div>
            <div class="ssb-page-header-right">
                <span class="ssb-pill">v<?php echo esc_html(SSB_VERSION); ?></span>
            </div>
        </div>

        <?php if ( $oauth_msg === 'connected' ): ?>
            <div class="notice notice-success is-dismissible"><p>Google Calendar connected successfully. You can now test the connection below.</p></div>
        <?php elseif ( $oauth_msg === 'disconnected' ): ?>
            <div class="notice notice-info is-dismissible"><p>Google Calendar disconnected.</p></div>
        <?php elseif ( $oauth_msg === 'error' ): ?>
            <div class="notice notice-error is-dismissible"><p>Google OAuth error: <?php echo esc_html(urldecode($oauth_err)); ?></p></div>
        <?php elseif ( $oauth_msg === 'invalid_state' ): ?>
            <div class="notice notice-error is-dismissible"><p>Security check failed. Please try connecting again.</p></div>
        <?php endif; ?>

        <!-- Tab nav -->
        <nav class="nav-tab-wrapper">
            <?php
            $tab_icons = [
                'appearance' => '<svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21a4 4 0 01-4-4V5a2 2 0 012-2h4a2 2 0 012 2v12a4 4 0 01-4 4zm0 0h12a2 2 0 002-2v-4a2 2 0 00-2-2h-4"/></svg>',
                'content'    => '<svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>',
                'email'      => '<svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>',
                'google'     => '<svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>',
                'spam'       => '<svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3l7 4v5c0 4.5-2.9 7.9-7 9-4.1-1.1-7-4.5-7-9V7l7-4zm-3 9l2 2 4-4"/></svg>',
                'payment'    => '<svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M5 6h14a2 2 0 012 2v8a2 2 0 01-2 2H5a2 2 0 01-2-2V8a2 2 0 012-2z"/></svg>',
                'shortcode'  => '<svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 20l4-16M6 8l-4 4 4 4m12-8l4 4-4 4"/></svg>',
            ];
            $tabs = [
                'appearance' => 'Appearance',
                'content'    => 'Content',
                'email'      => 'Email & SMTP',
                'google'     => 'Google Calendar',
                'spam'       => 'Spam Protection',
                'payment'    => 'Payments',
                'shortcode'  => 'Shortcode',
            ];
            foreach ( $tabs as $slug => $label ):
                if ( ! ssb_is_paid() && $slug !== 'email' ) continue;
                $cls = ($active_tab === $slug) ? 'nav-tab nav-tab-active' : 'nav-tab';
            ?>
                <a href="?page=ssb-settings&tab=<?php echo $slug; ?>" class="<?php echo $cls; ?>"><?php echo $tab_icons[$slug]; ?><?php echo $label; ?></a>
            <?php endforeach; ?>
        </nav>

        <div class="ssb-panel">

        <?php if ( $active_tab === 'appearance' && ssb_is_paid() ): ?>
        <!-- ═══════════════════ APPEARANCE ═══════════════════ -->
        <form method="post">
            <?php wp_nonce_field('ssb_settings_nonce'); ?>
            <h2>Appearance Settings</h2>
            <p class="ssb-panel-lead">Match the booking widget to your brand.</p>
            <table class="form-table">
                <tr>
                    <th><label>Primary Color</label></th>
                    <td>
                        <input type="text" name="ssb_primary_color" value="<?php echo esc_attr($p); ?>" class="ssb-color-picker">
                        <p class="description">Used for buttons, selected states, accents</p>
                    </td>
                </tr>
                <tr>
                    <th><label>Secondary / Gradient Color</label></th>
                    <td>
                        <input type="text" name="ssb_secondary_color" value="<?php echo esc_attr($s); ?>" class="ssb-color-picker">
                        <p class="description">Used in gradients alongside primary color</p>
                    </td>
                </tr>
                <tr>
                    <th><label>Button Text Color</label></th>
                    <td><input type="text" name="ssb_button_text_color" value="<?php echo esc_attr($bt); ?>" class="ssb-color-picker"></td>
                </tr>
                <tr>
                    <th><label>Sidebar Text Color</label></th>
                    <td><input type="text" name="ssb_sidebar_text_color" value="<?php echo esc_attr($st); ?>" class="ssb-color-picker"></td>
                </tr>
                <tr>
                    <th><label>Border Radius (px)</label></th>
                    <td>
                        <input type="number" name="ssb_border_radius" value="<?php echo esc_attr($br); ?>" min="0" max="50" class="small-text">
                        <p class="description">0 = sharp corners · 50 = fully rounded</p>
                    </td>
                </tr>
                <tr>
                    <th><label>Font Family</label></th>
                    <td>
                        <select name="ssb_font_family">
                            <?php
                            $fonts = [
                                'inherit'                                                  => 'Theme Default',
                                '-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif'   => 'System Sans-Serif',
                                'Georgia,serif'                                             => 'Georgia (Serif)',
                                '"Courier New",monospace'                                   => 'Courier New (Mono)',
                                '"Arial",sans-serif'                                        => 'Arial',
                            ];
                            foreach ( $fonts as $val => $label ) {
                                printf('<option value="%s" %s>%s</option>', esc_attr($val), selected($ff,$val,false), esc_html($label));
                            }
                            ?>
                        </select>
                    </td>
                </tr>
            </table>
            <!-- Live preview -->
            <div class="ssb-preview-block">
                <div>
                    <div class="ssb-preview-label">Live button preview</div>
                    <div id="ssb-preview-btn" style="display:inline-block;margin-top:10px;padding:14px 28px;background:linear-gradient(135deg,<?php echo esc_attr($p); ?>,<?php echo esc_attr($s); ?>);color:<?php echo esc_attr($bt); ?>;border-radius:<?php echo esc_attr($br); ?>px;font-weight:700;font-size:16px;font-family:<?php echo esc_attr($ff); ?>;cursor:pointer;border:none;">Schedule Meeting</div>
                </div>
            </div>
            <p class="submit"><input type="submit" name="ssb_save_settings" class="button button-primary button-large" value="Save Appearance"></p>
        </form>

        <?php elseif ( $active_tab === 'content' && ssb_is_paid() ): ?>
        <!-- ═══════════════════ CONTENT ═══════════════════ -->
        <form method="post">
            <?php wp_nonce_field('ssb_settings_nonce'); ?>
            <h2>Booking Form &amp; Calendar Content</h2>
            <p class="ssb-panel-lead">Control what clients see on the booking form and in their calendar invite.</p>

            <!-- Calendar event naming explained -->
            <div class="ssb-alert ssb-alert-info">
                <h4>How calendar event titles work</h4>
                <div class="ssb-compare-grid">
                    <div class="ssb-compare-card">
                        <b>Your calendar (organizer)</b>
                        <span>You see the client's name so you know who each call is with:</span>
                        <code>Discovery Call — John Smith</code>
                    </div>
                    <div class="ssb-compare-card">
                        <b>Client's invite email</b>
                        <span>Client sees your brand name — looks professional:</span>
                        <code>Discovery Call — Your Brand</code>
                    </div>
                </div>
            </div>

            <table class="form-table">
                <tr>
                    <th><label for="ssb_event_label">Event Label</label></th>
                    <td>
                        <input type="text" id="ssb_event_label" name="ssb_event_label"
                               value="<?php echo esc_attr(get_option('ssb_event_label','Discovery Call')); ?>"
                               class="regular-text" placeholder="Discovery Call">
                        <p class="description">The first part of the title — e.g. <strong>Discovery Call</strong>, Strategy Session, Consultation</p>
                    </td>
                </tr>
                <tr>
                    <th><label for="ssb_event_brand_name">Your Brand Name</label></th>
                    <td>
                        <input type="text" id="ssb_event_brand_name" name="ssb_event_brand_name"
                               value="<?php echo esc_attr(get_option('ssb_event_brand_name', get_bloginfo('name'))); ?>"
                               class="regular-text" placeholder="Your Brand">
                        <p class="description">Shown on the client's calendar invite — e.g. <strong>Your Brand</strong>. Makes the invite look branded and professional.</p>
                    </td>
                </tr>
                <tr>
                    <th><label for="ssb_organizer_display_name">Organizer Display Name</label></th>
                    <td>
                        <input type="text" id="ssb_organizer_display_name" name="ssb_organizer_display_name"
                               value="<?php echo esc_attr(get_option('ssb_organizer_display_name', get_bloginfo('name'))); ?>"
                               class="regular-text" placeholder="Support Team">
                        <p class="description">
                            The name shown as the <strong>event organizer</strong> in the client's invite email — replaces "unknown sender".<br>
                            <span style="color:#dc2626;font-weight:600;">For best results:</span> Also update your Google Account name at
                            <a href="https://myaccount.google.com/name" target="_blank">myaccount.google.com/name</a> to match this.
                        </p>
                    </td>
                </tr>
            </table>

            <div class="ssb-alert ssb-alert-warning">
                <h4>Why does Google show "Invitation from unknown sender"?</h4>
                <p>
                    Google flags calendar invites as "unknown sender" when the Google account sending them hasn't established trust with the recipient's Gmail.
                    This is a <strong>Google security feature</strong>, not a plugin issue. To fix it permanently:
                </p>
                <ol style="margin:10px 0 0;padding-left:18px;line-height:1.9;">
                    <li>Go to <a href="https://myaccount.google.com/name" target="_blank">myaccount.google.com/name</a> — set your account name to <strong>"<?php echo esc_html(get_option('ssb_organizer_display_name', get_bloginfo('name'))); ?>"</strong></li>
                    <li>Go to <a href="https://myaccount.google.com/profile" target="_blank">myaccount.google.com/profile</a> — add your organization/company name</li>
                    <li>In Google Cloud Console → OAuth consent screen → set the <strong>App name</strong> to your brand name and upload a <strong>logo</strong></li>
                    <li>If your app is in <em>Testing</em> mode, move it to <strong>Production</strong> (publish the OAuth app) — testing-mode apps always show as "unknown"</li>
                    <li>Ask a few clients to click <strong>"Yes"</strong> on the invite — over time Google learns to trust the sender</li>
                </ol>
            </div>

            <h3>Booking Form Display</h3>
            <table class="form-table">
                <tr>
                    <th><label>Logo Letter</label></th>
                    <td>
                        <input type="text" name="ssb_logo_letter" value="<?php echo esc_attr(get_option('ssb_logo_letter','P')); ?>" maxlength="2" class="small-text">
                        <p class="description">Single letter shown in the sidebar logo box</p>
                    </td>
                </tr>
                <tr>
                    <th><label>Team Label</label></th>
                    <td><input type="text" name="ssb_team_label" value="<?php echo esc_attr(get_option('ssb_team_label','Team')); ?>" class="regular-text"></td>
                </tr>
                <tr>
                    <th><label>Duration</label></th>
                    <td><input type="text" name="ssb_event_duration" value="<?php echo esc_attr(get_option('ssb_event_duration','30 Minutes')); ?>" class="regular-text"></td>
                </tr>
                <tr>
                    <th><label>Session Description</label></th>
                    <td>
                        <?php
                        wp_editor(
                            get_option('ssb_event_description',''),
                            'ssb_event_description',
                            [
                                'textarea_name' => 'ssb_event_description',
                                'textarea_rows' => 6,
                                'media_buttons' => false,
                                'teeny'         => true,
                                'quicktags'     => true,
                            ]
                        );
                        ?>
                        <p class="description">Shown in the booking form sidebar and included in the calendar event description</p>
                    </td>
                </tr>
            </table>
            <p class="submit"><input type="submit" name="ssb_save_settings" class="button button-primary button-large" value="Save Content Settings"></p>
        </form>

        <?php elseif ( $active_tab === 'email' ): ?>
        <!-- ═══════════════════ EMAIL & SMTP ═══════════════════ -->
        <form method="post">
            <?php wp_nonce_field('ssb_settings_nonce'); ?>
            <?php
            $smtp_enabled = get_option('ssb_smtp_enabled') === '1';
            $smtp_host    = get_option('ssb_smtp_host', '');
            $can_reach    = null;
            if ( $smtp_enabled && $smtp_host ) {
                $conn = @fsockopen($smtp_host, (int)get_option('ssb_smtp_port',587), $errno, $errstr, 3);
                $can_reach = (bool) $conn;
                if ( $conn ) fclose($conn);
            }
            ?>
            <?php if ( $smtp_enabled && $smtp_host && $can_reach === false ): ?>
            <div class="ssb-alert ssb-alert-danger">
                <strong>Cannot reach SMTP host on port <?php echo esc_html(get_option('ssb_smtp_port','587')); ?>.</strong><br>
                Your server may be blocking outbound SMTP. Try port 465+SSL, or use a transactional email service.
            </div>
            <?php elseif ( $smtp_enabled && $smtp_host && $can_reach ): ?>
            <div class="ssb-alert ssb-alert-success">
                <strong>SMTP host reachable.</strong> Click "Send Test Email" below to verify credentials.
            </div>
            <?php endif; ?>

            <h2>Notification Settings</h2>
            <table class="form-table">
                <tr>
                    <th><label>Primary Notify Email</label></th>
                    <td>
                        <input type="email" name="ssb_support_email" value="<?php echo esc_attr(get_option('ssb_support_email','')); ?>" class="regular-text">
                        <p class="description">Receives booking notifications — e.g. <code>support@example.com</code></p>
                    </td>
                </tr>
                <tr>
                    <th><label>Extra Notify Email</label></th>
                    <td>
                        <input type="email" name="ssb_extra_notify_email" value="<?php echo esc_attr(get_option('ssb_extra_notify_email','')); ?>" class="regular-text" placeholder="info@example.com">
                        <p class="description">
                            <strong>Second email address</strong> that also receives every booking notification.<br>
                            Both <code>support@</code> and <code>info@</code> will each get a full copy. Leave blank to send to Primary only.
                        </p>
                    </td>
                </tr>
            </table>

            <h3>SMTP Settings</h3>
            <table class="form-table">
                <tr>
                    <th><label>Enable SMTP</label></th>
                    <td>
                        <div class="ssb-toggle-row">
                            <label class="ssb-switch">
                                <input type="checkbox" name="ssb_smtp_enabled" value="1" <?php checked(get_option('ssb_smtp_enabled'),'1'); ?>>
                                <span class="ssb-switch-track"></span>
                            </label>
                            <div class="ssb-toggle-copy"><strong>Use SMTP instead of WordPress default mail</strong></div>
                        </div>
                    </td>
                </tr>
                <tr>
                    <th><label>SMTP Host</label></th>
                    <td>
                        <input type="text" id="ssb_smtp_host" name="ssb_smtp_host" value="<?php echo esc_attr(get_option('ssb_smtp_host','')); ?>" class="regular-text" placeholder="mail.privateemail.com">
                        <p class="description">Namecheap: <code>mail.privateemail.com</code> · Gmail: <code>smtp.gmail.com</code></p>
                    </td>
                </tr>
                <tr>
                    <th><label>SMTP Port</label></th>
                    <td>
                        <input type="number" id="ssb_smtp_port" name="ssb_smtp_port" value="<?php echo esc_attr(get_option('ssb_smtp_port','587')); ?>" class="small-text">
                        &nbsp;
                        <button type="button" class="button button-small ssb-port-btn" data-port="587" data-enc="tls">587 + TLS</button>
                        <button type="button" class="button button-small ssb-port-btn" data-port="465" data-enc="ssl" style="margin-left:4px;">465 + SSL</button>
                    </td>
                </tr>
                <tr>
                    <th><label>Encryption</label></th>
                    <td>
                        <select id="ssb_smtp_encryption" name="ssb_smtp_encryption">
                            <option value="tls" <?php selected(get_option('ssb_smtp_encryption','tls'),'tls'); ?>>TLS (port 587 recommended)</option>
                            <option value="ssl" <?php selected(get_option('ssb_smtp_encryption','tls'),'ssl'); ?>>SSL (port 465)</option>
                            <option value="none" <?php selected(get_option('ssb_smtp_encryption','tls'),'none'); ?>>None (not recommended)</option>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th><label>SMTP Username</label></th>
                    <td><input type="text" id="ssb_smtp_username" name="ssb_smtp_username" value="<?php echo esc_attr(get_option('ssb_smtp_username','')); ?>" class="regular-text" placeholder="you@yourdomain.com"></td>
                </tr>
                <tr>
                    <th><label>SMTP Password</label></th>
                    <td><input type="password" name="ssb_smtp_password" value="<?php echo esc_attr(get_option('ssb_smtp_password','')); ?>" class="regular-text" autocomplete="new-password"></td>
                </tr>
                <tr>
                    <th><label>From Email</label></th>
                    <td><input type="email" name="ssb_smtp_from_email" value="<?php echo esc_attr(get_option('ssb_smtp_from_email','')); ?>" class="regular-text"></td>
                </tr>
                <tr>
                    <th><label>From Name</label></th>
                    <td><input type="text" name="ssb_smtp_from_name" value="<?php echo esc_attr(get_option('ssb_smtp_from_name','')); ?>" class="regular-text"></td>
                </tr>
            </table>

            <div class="ssb-preview-block" style="border-style:solid;">
                <div style="width:100%;">
                    <div class="ssb-preview-label">Test SMTP connection</div>
                    <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;margin-top:10px;">
                        <input type="email" id="ssb-test-email-addr" placeholder="Send test to…" class="regular-text" value="<?php echo esc_attr(get_option('admin_email')); ?>">
                        <button type="button" id="ssb-test-smtp" class="button button-primary">Send Test Email</button>
                    </div>
                    <div id="ssb-test-result" class="ssb-result-banner" style="display:none;"></div>
                </div>
            </div>

            <p class="submit"><input type="submit" name="ssb_save_settings" class="button button-primary button-large" value="Save Email Settings"></p>
        </form>

        <?php elseif ( $active_tab === 'google' && ssb_is_paid() ): ?>
        <!-- ═══════════════════ GOOGLE CALENDAR ═══════════════════ -->
        <?php
        $is_connected = ssb_google_is_connected();
        $auth_url     = ssb_google_get_auth_url();
        ?>

        <div class="ssb-compare-grid" style="margin-top:0;margin-bottom:26px;gap:20px;">
            <!-- Status card -->
            <div class="ssb-alert <?php echo $is_connected ? 'ssb-alert-success' : 'ssb-alert-danger'; ?>" style="margin-bottom:0;">
                <h4 style="font-size:15px;">
                    <?php echo $is_connected ? 'Connected to Google Calendar' : 'Not Connected'; ?>
                </h4>
                <p>
                    <?php if ( $is_connected ): ?>
                        Calendar ID: <strong><?php echo esc_html(get_option('ssb_google_calendar_id','primary')); ?></strong><br>
                        Google Meet: <strong><?php echo get_option('ssb_google_meet_enabled','1') === '1' ? 'Enabled' : 'Disabled'; ?></strong>
                    <?php else: ?>
                        Complete the setup below to enable Google Calendar sync and automatic Google Meet links.
                    <?php endif; ?>
                </p>
                <?php if ( $is_connected ): ?>
                <div style="margin-top:14px;display:flex;gap:8px;flex-wrap:wrap;">
                    <button type="button" id="ssb-test-google" class="button button-primary">Test Connection</button>
                    <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=ssb-settings&tab=google&ssb_google_callback=1&ssb_google_disconnect=1'),'ssb_google_disconnect'); ?>"
                       class="button" onclick="return confirm('Disconnect Google Calendar?')">Disconnect</a>
                </div>
                <div id="ssb-google-test-result" class="ssb-result-banner" style="display:none;"></div>
                <?php endif; ?>
            </div>

            <!-- How it works -->
            <div class="ssb-alert ssb-alert-info" style="margin-bottom:0;">
                <h4 style="font-size:15px;">What this does</h4>
                <ul style="margin:0;padding-left:18px;line-height:1.8;">
                    <li>Automatically creates Google Calendar events on every booking</li>
                    <li>Generates a <strong>Google Meet link</strong> for each session</li>
                    <li>Sends Meet link in confirmation emails to customers</li>
                    <li>Invites the customer to the calendar event</li>
                    <li>Sets reminders: 24h email + 15min popup</li>
                </ul>
            </div>
        </div>

        <!-- Step-by-step setup guide -->
        <?php if ( ! $is_connected ): ?>
        <div class="ssb-alert ssb-alert-warning" style="padding:24px;">
            <h4 style="font-size:14px;margin-bottom:16px;">Setup guide — 3 easy steps</h4>

            <div style="display:grid;grid-template-columns:36px 1fr;gap:12px;align-items:start;margin-bottom:16px;">
                <div style="width:32px;height:32px;background:var(--ssb-accent);border-radius:50%;display:flex;align-items:center;justify-content:center;font-weight:800;color:#fff;font-size:14px;">1</div>
                <div>
                    <strong>Create Google Cloud Project &amp; Enable Calendar API</strong>
                    <ol style="margin:6px 0 0;padding-left:18px;font-size:13px;color:#6b7280;line-height:1.8;">
                        <li>Go to <a href="https://console.cloud.google.com/" target="_blank">console.cloud.google.com</a> → Create Project</li>
                        <li>Enable <strong>Google Calendar API</strong> (APIs &amp; Services → Enable APIs)</li>
                        <li>Go to <strong>APIs &amp; Services → OAuth consent screen</strong></li>
                        <li>Set User Type: <strong>External</strong> → Fill App name &amp; your email</li>
                        <li>Add scope: <code>https://www.googleapis.com/auth/calendar</code></li>
                        <li>Add your Google account as a <strong>Test User</strong></li>
                    </ol>
                </div>
            </div>

            <div style="display:grid;grid-template-columns:36px 1fr;gap:12px;align-items:start;margin-bottom:16px;">
                <div style="width:32px;height:32px;background:var(--ssb-accent);border-radius:50%;display:flex;align-items:center;justify-content:center;font-weight:800;color:#fff;font-size:14px;">2</div>
                <div>
                    <strong>Create OAuth 2.0 Credentials</strong>
                    <ol style="margin:6px 0 0;padding-left:18px;font-size:13px;color:#6b7280;line-height:1.8;">
                        <li>APIs &amp; Services → Credentials → Create Credentials → <strong>OAuth 2.0 Client ID</strong></li>
                        <li>Application type: <strong>Web application</strong></li>
                        <li>Add Authorized Redirect URI:<br>
                            <code style="background:#fff;padding:4px 8px;border-radius:4px;border:1px solid #e5e7eb;font-size:12px;display:inline-block;margin-top:4px;word-break:break-all;">
                                <?php echo esc_url(ssb_google_redirect_uri()); ?>
                            </code>
                        </li>
                        <li>Copy your <strong>Client ID</strong> and <strong>Client Secret</strong> into the fields below</li>
                    </ol>
                </div>
            </div>

            <div style="display:grid;grid-template-columns:36px 1fr;gap:12px;align-items:start;">
                <div style="width:32px;height:32px;background:var(--ssb-accent);border-radius:50%;display:flex;align-items:center;justify-content:center;font-weight:800;color:#fff;font-size:14px;">3</div>
                <div>
                    <strong>Save Settings &amp; Click Connect</strong>
                    <p style="margin:4px 0 0;font-size:13px;color:#6b7280;">Enter your Client ID &amp; Secret below, save, then click the <em>Connect to Google Calendar</em> button.</p>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Google Calendar settings form -->
        <form method="post">
            <?php wp_nonce_field('ssb_google_settings_nonce'); ?>
            <h2>API Credentials</h2>
            <table class="form-table">
                <tr>
                    <th><label for="ssb_google_client_id">Client ID</label></th>
                    <td>
                        <input type="text" id="ssb_google_client_id" name="ssb_google_client_id"
                               value="<?php echo esc_attr(get_option('ssb_google_client_id','')); ?>"
                               class="large-text" placeholder="xxxxxxxxxx.apps.googleusercontent.com">
                        <p class="description">Found in Google Cloud Console → APIs &amp; Services → Credentials</p>
                    </td>
                </tr>
                <tr>
                    <th><label for="ssb_google_client_secret">Client Secret</label></th>
                    <td>
                        <input type="password" id="ssb_google_client_secret" name="ssb_google_client_secret"
                               value="<?php echo esc_attr(get_option('ssb_google_client_secret','')); ?>"
                               class="regular-text" autocomplete="new-password">
                        <p class="description">Keep this secret — never share it publicly</p>
                    </td>
                </tr>
            </table>

            <h3>Calendar Settings</h3>
            <table class="form-table">
                <tr>
                    <th><label for="ssb_google_calendar_id">Calendar ID</label></th>
                    <td>
                        <input type="text" id="ssb_google_calendar_id" name="ssb_google_calendar_id"
                               value="<?php echo esc_attr(get_option('ssb_google_calendar_id','primary')); ?>"
                               class="regular-text" placeholder="primary">
                        <p class="description">Use <code>primary</code> for your main calendar, or find your Calendar ID in Google Calendar Settings → your calendar → Calendar ID</p>
                    </td>
                </tr>
                <tr>
                    <th><label for="ssb_google_timezone">Event Timezone</label></th>
                    <td>
                        <select id="ssb_google_timezone" name="ssb_google_timezone">
                            <?php
                            $tz_saved = get_option('ssb_google_timezone', 'America/New_York');
                            $tz_list  = [
                                'America/New_York'    => 'Eastern (ET)',
                                'America/Chicago'     => 'Central (CT)',
                                'America/Denver'      => 'Mountain (MT)',
                                'America/Los_Angeles' => 'Pacific (PT)',
                                'America/Phoenix'     => 'Arizona (no DST)',
                                'America/Anchorage'   => 'Alaska (AKT)',
                                'Pacific/Honolulu'    => 'Hawaii (HT)',
                                'Europe/London'       => 'London (GMT/BST)',
                                'Europe/Paris'        => 'Paris (CET)',
                                'Europe/Berlin'       => 'Berlin (CET)',
                                'Asia/Karachi'        => 'Karachi (PKT)',
                                'Asia/Kolkata'        => 'India (IST)',
                                'Asia/Dubai'          => 'Dubai (GST)',
                                'Asia/Singapore'      => 'Singapore (SGT)',
                                'Asia/Tokyo'          => 'Tokyo (JST)',
                                'Australia/Sydney'    => 'Sydney (AEST)',
                                'UTC'                 => 'UTC',
                            ];
                            foreach ( $tz_list as $tz_val => $tz_label ) {
                                printf('<option value="%s" %s>%s</option>', esc_attr($tz_val), selected($tz_saved,$tz_val,false), esc_html($tz_label));
                            }
                            ?>
                        </select>
                        <p class="description">Timezone used when creating Google Calendar events</p>
                    </td>
                </tr>
                <tr>
                    <th><label>Google Meet Links</label></th>
                    <td>
                        <div class="ssb-toggle-row">
                            <label class="ssb-switch">
                                <input type="checkbox" name="ssb_google_meet_enabled" value="1" <?php checked(get_option('ssb_google_meet_enabled','1'),'1'); ?>>
                                <span class="ssb-switch-track"></span>
                            </label>
                            <div class="ssb-toggle-copy">
                                <strong>Automatically generate Google Meet link for each booking</strong>
                                <span>The Meet link is included in the calendar invite and confirmation email</span>
                            </div>
                        </div>
                    </td>
                </tr>
            </table>

            <p class="submit" style="display:flex;gap:12px;align-items:center;flex-wrap:wrap;">
                <input type="submit" name="ssb_save_google" class="button button-primary button-large" value="Save Google Settings">
                <?php if ( ! $is_connected && $auth_url ): ?>
                    <a href="<?php echo esc_url($auth_url); ?>" class="button button-large" style="background:#4285f4;color:#fff;border-color:#3367d6;display:inline-flex;align-items:center;gap:8px;">
                        <svg width="18" height="18" viewBox="0 0 48 48"><path fill="#fff" d="M44.5 20H24v8.5h11.7C34.2 33.6 29.7 37 24 37c-7.2 0-13-5.8-13-13s5.8-13 13-13c3.1 0 6 1.1 8.1 3l6-6C34.6 5.1 29.5 3 24 3 12.4 3 3 12.4 3 24s9.4 21 21 21c10.9 0 20-7.9 20-21 0-1.3-.2-2.7-.5-4z"/></svg>
                        Connect to Google Calendar
                    </a>
                <?php elseif ( $is_connected && $auth_url ): ?>
                    <a href="<?php echo esc_url($auth_url); ?>" class="button" title="Re-authorize if you're having issues">Re-authorize</a>
                <?php elseif ( ! $auth_url ): ?>
                    <span style="color:var(--ssb-danger);font-size:13px;">Enter Client ID above and save first to enable Connect button.</span>
                <?php endif; ?>
            </p>
        </form>

        <?php elseif ( $active_tab === 'spam' && ssb_is_paid() ): ?>
        <div class="ssb-settings-card" style="max-width:900px;">
            <h2>Spam Protection</h2>
            <p class="description">Use the built-in honeypot on any form without third-party credentials, or configure Turnstile/reCAPTCHA here and select the provider on a Spam Protection field in the Form Builder.</p>
            <form method="post">
                <?php wp_nonce_field('ssb_spam_settings_nonce'); ?>
                <table class="form-table">
                    <tr><th>Cloudflare Turnstile Site Key</th><td><input class="regular-text" name="ssb_turnstile_site_key" value="<?php echo esc_attr(get_option('ssb_turnstile_site_key','')); ?>"><p class="description">Public site key from Cloudflare Turnstile.</p></td></tr>
                    <tr><th>Cloudflare Turnstile Secret Key</th><td><input type="password" class="regular-text" name="ssb_turnstile_secret_key" value="<?php echo esc_attr(get_option('ssb_turnstile_secret_key','')); ?>" autocomplete="new-password"></td></tr>
                    <tr><th>Google reCAPTCHA Site Key</th><td><input class="regular-text" name="ssb_recaptcha_site_key" value="<?php echo esc_attr(get_option('ssb_recaptcha_site_key','')); ?>"><p class="description">Use a reCAPTCHA v2 checkbox key for the checkbox widget.</p></td></tr>
                    <tr><th>Google reCAPTCHA Secret Key</th><td><input type="password" class="regular-text" name="ssb_recaptcha_secret_key" value="<?php echo esc_attr(get_option('ssb_recaptcha_secret_key','')); ?>" autocomplete="new-password"></td></tr>
                </table>
                <p><button class="button button-primary" name="ssb_save_spam" value="1">Save Spam Protection</button></p>
            </form>
        </div>

        <?php elseif ( $active_tab === 'payment' && ssb_is_paid() ): ?>
        <!-- ═══════════════════ PAYMENT ═══════════════════ -->
        <h2>Payment Settings (Stripe)</h2>
        <p class="ssb-panel-lead">Collect a booking deposit via Stripe. Customers pay directly on the booking form before their slot is confirmed.</p>
        <form method="post">
            <?php wp_nonce_field('ssb_payment_nonce'); ?>
            <table class="form-table">
                <tr>
                    <th>Enable Payments</th>
                    <td>
                        <div class="ssb-toggle-row">
                            <label class="ssb-switch">
                                <input type="checkbox" name="ssb_payment_enabled" value="1" <?php checked(get_option('ssb_payment_enabled','0'),'1'); ?>>
                                <span class="ssb-switch-track"></span>
                            </label>
                            <div class="ssb-toggle-copy">
                                <strong>Enable Stripe for the legacy [smart_schedule_booking] page</strong>
                                <span>This setting controls the legacy <code>[smart_schedule_booking]</code> booking page only. Other FormPilot forms use their own per-form Stripe toggle.</span>
                            </div>
                        </div>
                    </td>
                </tr>
                <tr>
                    <th>Test Mode</th>
                    <td>
                        <div class="ssb-toggle-row">
                            <label class="ssb-switch">
                                <input type="checkbox" name="ssb_stripe_test_mode" value="1" <?php checked(get_option('ssb_stripe_test_mode','1'),'1'); ?>>
                                <span class="ssb-switch-track"></span>
                            </label>
                            <div class="ssb-toggle-copy">
                                <strong>Use Stripe test keys (sandbox)</strong>
                                <span>Disable when you're ready to accept real payments. Use <code>4242 4242 4242 4242</code> to test.</span>
                            </div>
                        </div>
                    </td>
                </tr>
                <tr>
                    <th><label for="ssb_stripe_publishable_key">Publishable Key</label></th>
                    <td>
                        <input type="text" id="ssb_stripe_publishable_key" name="ssb_stripe_publishable_key" class="regular-text" value="<?php echo esc_attr(get_option('ssb_stripe_publishable_key','')); ?>" placeholder="pk_test_...">
                        <p class="description">Starts with <code>pk_test_</code> (test) or <code>pk_live_</code> (live). Found in your <a href="https://dashboard.stripe.com/apikeys" target="_blank">Stripe Dashboard → API Keys</a>.</p>
                    </td>
                </tr>
                <tr>
                    <th><label for="ssb_stripe_secret_key">Secret Key</label></th>
                    <td>
                        <input type="password" id="ssb_stripe_secret_key" name="ssb_stripe_secret_key" class="regular-text" value="<?php echo esc_attr(get_option('ssb_stripe_secret_key','')); ?>" placeholder="sk_test_...">
                        <p class="description">Keep this secret. Never share or expose it publicly. Starts with <code>sk_test_</code> or <code>sk_live_</code>.</p>
                    </td>
                </tr>
                <tr>
                    <th><label for="ssb_booking_price">Booking Price</label></th>
                    <td>
                        <input type="number" id="ssb_booking_price" name="ssb_booking_price" class="small-text" min="0" step="0.01" value="<?php echo esc_attr(get_option('ssb_booking_price','0')); ?>">
                        <p class="description">Amount in the selected currency. Set to 0 to make it free (payment form hidden).</p>
                    </td>
                </tr>
                <tr>
                    <th><label for="ssb_booking_currency">Currency</label></th>
                    <td>
                        <select id="ssb_booking_currency" name="ssb_booking_currency">
                            <?php
                            $saved_curr = get_option('ssb_booking_currency','USD');
                            $currencies = ['USD'=>'USD — US Dollar','EUR'=>'EUR — Euro','GBP'=>'GBP — British Pound','CAD'=>'CAD — Canadian Dollar','AUD'=>'AUD — Australian Dollar','PKR'=>'PKR — Pakistani Rupee','INR'=>'INR — Indian Rupee','SGD'=>'SGD — Singapore Dollar','AED'=>'AED — UAE Dirham'];
                            foreach($currencies as $code => $label) printf('<option value="%s" %s>%s</option>', esc_attr($code), selected($saved_curr,$code,false), esc_html($label));
                            ?>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th><label for="ssb_payment_description">Payment Description</label></th>
                    <td>
                        <input type="text" id="ssb_payment_description" name="ssb_payment_description" class="regular-text" value="<?php echo esc_attr(get_option('ssb_payment_description','Booking deposit')); ?>">
                        <p class="description">Shown in Stripe dashboard as the payment description.</p>
                    </td>
                </tr>
            </table>
            <div class="ssb-alert ssb-alert-warning" style="max-width:620px;">
                <h4>How to get your Stripe keys</h4>
                <ol style="margin:8px 0 0;padding-left:20px;line-height:1.8;">
                    <li>Log in to <a href="https://dashboard.stripe.com" target="_blank">dashboard.stripe.com</a></li>
                    <li>Go to <strong>Developers → API Keys</strong></li>
                    <li>Copy the <strong>Publishable key</strong> and <strong>Secret key</strong></li>
                    <li>Paste them above and save</li>
                </ol>
            </div>
            <p class="submit">
                <input type="submit" name="ssb_save_payment" class="button button-primary button-large" value="Save Payment Settings">
            </p>
        </form>

        <?php elseif ( $active_tab === 'shortcode' && ssb_is_paid() ): ?>
        <!-- ═══════════════════ SHORTCODE ═══════════════════ -->
        <h2>Shortcodes</h2>
        <p class="ssb-panel-lead">Drop these into any page or post to display your booking tools.</p>
        <div class="ssb-shortcode-card">
            <h3>Main Booking Calendar</h3>
            <div class="ssb-code-chip">[smart_schedule_booking]<span class="ssb-copy-hint">Click to select</span></div>
        </div>
        <div class="ssb-shortcode-card">
            <h3>Custom Form Builder</h3>
            <div class="ssb-code-chip">[ssb_form id="1"]<span class="ssb-copy-hint">Click to select</span></div>
            <p class="description" style="margin-top:10px;">Replace <code>1</code> with your form ID from the Form Builder.</p>
        </div>
        <?php endif; ?>

        </div><!-- end tab panel -->
    </div>
    <?php
}

add_action('admin_head', function(){ if(empty($_GET['page']) || strpos((string)$_GET['page'],'ssb-')===false) return; ?>
<style>
.fp-product-admin{max-width:1440px;margin-top:24px}.fp-product-hero{display:flex;justify-content:space-between;align-items:center;gap:28px;padding:34px 38px;border-radius:22px;background:linear-gradient(135deg,#111827 0%,#312e81 58%,#4f46e5 100%);color:#fff;box-shadow:0 18px 50px rgba(30,41,59,.16);margin-bottom:22px}.fp-product-kicker,.fp-card-kicker{font-size:10px;font-weight:800;letter-spacing:.16em}.fp-product-kicker{color:#c4b5fd}.fp-product-hero h1{color:#fff;font-size:31px;line-height:1.15;margin:7px 0 9px;max-width:800px}.fp-product-hero p{color:#dbeafe;font-size:14px;margin:0;max-width:760px}.fp-license-pill{white-space:nowrap;padding:10px 14px;border-radius:999px;font-weight:700;font-size:12px}.fp-license-pill.is-active{background:#064e3b;color:#a7f3d0}.fp-license-pill.is-free{background:#fff;color:#475569}.fp-stat-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:14px;margin-bottom:22px}.fp-stat-grid a{display:block;text-decoration:none;background:#fff;border:1px solid #e5e7eb;border-radius:16px;padding:20px;box-shadow:0 5px 20px rgba(15,23,42,.035)}.fp-stat-grid strong{display:block;font-size:25px;color:#111827}.fp-stat-grid span{display:block;color:#64748b;font-size:12px;margin-top:4px}.fp-dashboard-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:16px}.fp-dashboard-card{background:#fff;border:1px solid #e5e7eb;border-radius:18px;padding:24px;box-shadow:0 5px 20px rgba(15,23,42,.035)}.fp-card-kicker{color:#6366f1;margin-bottom:7px}.fp-dashboard-card h2{font-size:18px;margin:0 0 8px}.fp-dashboard-card p{color:#64748b;line-height:1.6;margin:0 0 17px}.fp-action-row{display:flex;gap:8px;flex-wrap:wrap}.fp-action-row .button{border-radius:9px}.fp-action-row .button-primary{background:#4f46e5;border-color:#4f46e5}.fp-scheduling-admin{max-width:1440px}@media(max-width:900px){.fp-product-hero{align-items:flex-start;flex-direction:column}.fp-stat-grid{grid-template-columns:repeat(2,1fr)}.fp-dashboard-grid{grid-template-columns:1fr}}@media(max-width:600px){.fp-stat-grid{grid-template-columns:1fr}.fp-product-hero{padding:25px}.fp-product-hero h1{font-size:25px}}
</style>
<?php });
