<?php
if ( ! defined('ABSPATH') ) exit;

/** FormPilot free-tier policy. No license is required for the free tier. */
function ssb_free_limits(){
    return [
        'forms' => 3,
        'entries_month' => 25,
        'templates' => false,
        'multistep' => false,
        'event_types' => 1,
        'bookings_month' => 10,
        'calendar' => false,
        'stripe' => false,
        'payment_links' => false,
        'advanced_styling' => false,
        'branding' => false,
        'analytics' => false,
        'routing' => false,
        'workflows' => false,
        'team_scheduling' => false,
        'smtp' => true,
        'settings' => true,
        'integrations' => false,
    ];
}
function ssb_trial_status(){
    $raw=get_option('ssb_trial_payload',[]); if(!is_array($raw))$raw=[];
    $sig=(string)get_option('ssb_trial_signature','');
    if(!$raw || !$sig || !function_exists('ssb_license_verify_signature')) return ['active'=>false,'expires_at'=>'','plan'=>''];
    $data=['license'=>$raw,'signature'=>$sig,'signed_payload'=>(string)get_option('ssb_trial_signed_payload',''),'signature_alg'=>'RSA-SHA256','signature_key_id'=>'fp-key-1','signature_public_fingerprint'=>(string)get_option('ssb_trial_server_fingerprint','')];
    if(!ssb_license_verify_signature($data)) return ['active'=>false,'expires_at'=>'','plan'=>''];
    if(($raw['domain']??'')!==ssb_license_domain()) return ['active'=>false,'expires_at'=>'','plan'=>''];
    $active=($raw['status']??'')==='active' && (!empty($raw['expires_at']) ? strtotime($raw['expires_at'])>=current_time('timestamp') : false);
    return ['active'=>$active,'expires_at'=>sanitize_text_field($raw['expires_at']??''),'plan'=>'trial','features'=>is_array($raw['features']??null)?$raw['features']:[]];
}
function ssb_complimentary_payload() { $v=get_option('ssb_complimentary_payload',[]); return is_array($v)?$v:[]; }
function ssb_complimentary_status(){
    $raw=ssb_complimentary_payload(); $sig=(string)get_option('ssb_complimentary_signature','');
    if(!$raw||!$sig||!function_exists('ssb_license_verify_signature')) return ['active'=>false,'features'=>[],'expires_at'=>''];
    $data=['license'=>$raw,'signature'=>$sig,'signed_payload'=>(string)get_option('ssb_complimentary_signed_payload',''),'signature_alg'=>'RSA-SHA256','signature_key_id'=>(string)get_option('ssb_complimentary_key_id','fp-key-1'),'signature_public_fingerprint'=>(string)get_option('ssb_complimentary_fingerprint','')];
    if(!ssb_license_verify_signature($data)) return ['active'=>false,'features'=>[],'expires_at'=>''];
    if(($raw['domain']??'')!==ssb_license_domain()) return ['active'=>false,'features'=>[],'expires_at'=>''];
    $active=($raw['status']??'')==='active' && (empty($raw['expires_at']) || strtotime($raw['expires_at'])>=current_time('timestamp'));
    return ['active'=>$active,'features'=>is_array($raw['features']??null)?$raw['features']:[],'expires_at'=>sanitize_text_field($raw['expires_at']??'')];
}
function ssb_complimentary_refresh($force=false){
    if(function_exists('ssb_license_server_url') && ssb_license_server_url()){
        $last=(int)get_option('ssb_complimentary_last_check',0);
        if(!$force && $last && (time()-$last)<900) return ssb_complimentary_status();
        $body=wp_json_encode(ssb_license_telemetry()); $route='/formpilot/v1/license/complimentary';
        $r=wp_remote_post(trailingslashit(ssb_license_server_url()).'wp-json/formpilot/v1/license/complimentary',['timeout'=>10,'sslverify'=>true,'headers'=>ssb_license_request_headers($route,$body),'body'=>$body]);
        update_option('ssb_complimentary_last_check',time(),false);
        if(!is_wp_error($r)){
            $data=json_decode(wp_remote_retrieve_body($r),true);
            if(is_array($data)&&!empty($data['valid'])&&ssb_license_verify_signature($data)){
                $diag=ssb_license_signature_diagnostics($data); $ent=$diag['entitlement']??[];
                if(($ent['domain']??'')===ssb_license_domain()){
                    update_option('ssb_complimentary_payload',$ent,false); update_option('ssb_complimentary_signature',sanitize_text_field((string)$data['signature']),false); update_option('ssb_complimentary_signed_payload',sanitize_text_field((string)($data['signed_payload']??'')),false); update_option('ssb_complimentary_key_id',sanitize_text_field((string)($data['signature_key_id']??'fp-key-1')),false); update_option('ssb_complimentary_fingerprint',sanitize_text_field((string)($data['signature_public_fingerprint']??'')),false); ssb_license_store_installation_response($data);
                }
            }
        }
    }
    return ssb_complimentary_status();
}
add_action('admin_init',function(){ if(!wp_doing_ajax()) ssb_complimentary_refresh(false); },30);
function ssb_is_paid(){
    $licensed=function_exists('ssb_license_status') && !empty(ssb_license_status()['active']) && !empty(ssb_license_key());
    if($licensed) return true;
    return !empty(ssb_trial_status()['active']);
}

function ssb_free_form_count(){
    global $wpdb; $table=$wpdb->prefix.'ssb_forms';
    if(!$wpdb->get_var("SHOW TABLES LIKE '$table'")) return 0;
    return (int)$wpdb->get_var("SELECT COUNT(*) FROM {$table} WHERE status <> 'trash'");
}
function ssb_free_entries_this_month(){
    global $wpdb; $table=$wpdb->prefix.'ssb_form_entries';
    if(!$wpdb->get_var("SHOW TABLES LIKE '$table'")) return 0;
    $start = wp_date('Y-m-01 00:00:00');
    return (int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$table} WHERE created_at >= %s",$start));
}
function ssb_free_can($feature){
    $l=ssb_free_limits(); return isset($l[$feature]) ? (bool)$l[$feature] : false;
}
function ssb_feature_locked_message($feature){
    $names=['calendar'=>'Google Calendar & Meet','stripe'=>'Stripe payments','payment_links'=>'Payment links','advanced_styling'=>'Advanced styling','branding'=>'Branded scheduling pages','analytics'=>'Analytics','routing'=>'Routing forms','workflows'=>'Automations / workflows','team_scheduling'=>'Team scheduling','group_events'=>'Group events','collective_events'=>'Collective events','round_robin'=>'Round-robin scheduling','single_use_links'=>'Single-use links','meeting_polls'=>'Meeting polls','reminders'=>'Reminders','followups'=>'Follow-ups','webhooks'=>'Webhooks','api'=>'API access','crm_integrations'=>'CRM integrations','video_integrations'=>'Video integrations','contacts'=>'Contacts','invoices'=>'Invoices','templates'=>'Premium templates','integrations'=>'Integrations','settings'=>'Advanced settings','smtp'=>'Email & SMTP'];
    $name=$names[$feature]??ucwords(str_replace('_',' ',$feature));
    return sprintf('%s is a Pro feature. Activate a FormPilot license to unlock it.',$name);
}
function ssb_free_limit_notice($type='forms'){
    $l=ssb_free_limits();
    if($type==='forms') return 'The free plan allows up to 3 forms. Activate FormPilot Pro for unlimited forms, the complete template library and advanced features.';
    if($type==='event_types') return 'The free plan includes 1 event type and one-on-one scheduling. Activate FormPilot Pro for advanced scheduling.';
    if($type==='bookings') return 'The free plan includes 10 bookings per month. Activate FormPilot Pro for higher limits.';
    if($type==='entries') return 'The free plan allows 25 submissions per month. Activate FormPilot Pro for higher limits.';
    return 'This feature is available with FormPilot Pro.';
}
