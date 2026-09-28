<?php
if ( ! defined('ABSPATH') ) exit;

/**
 * Public verification key for FormPilot commercial entitlements.
 * The private signing key exists only on the licensing server.
 */
define('SSB_LICENSE_PUBLIC_KEY', <<<'FORMPILOT_PUBLIC_KEY'
-----BEGIN PUBLIC KEY-----
MIIBojANBgkqhkiG9w0BAQEFAAOCAY8AMIIBigKCAYEA1CbDf+CHsnqFQ3sCLhd3
D4CdgfHNdHYQZxaNZjFOBsLM17DR41csm0OstwZEyuAg6y2kAUDV1XP/FIgF2ICE
l9VvDJ28KT9xqzBfeuDNVGQBed+/5nhLKz+0Csoig/E+tsEPBBZmjjg3KEAlgeeV
Bb5wkU8BedEABndisuiBmdptQhE7m/T6kXEb1XLsqKqrvzviM1MaDA4JuWE1PAli
IpxLii8RjpjbB+GVgQR81ytWE6MOGxf5VGxfqUqG1z6n2B9BFAMDdtcneYkjr14g
pfRlosBFjpBfhOFQLNDBOx4ODI6/ug9CeV/Jzq1k2+egE+nqiTDubEzfeAlUlSPS
y0xgzNfEwNkSfKuIsHjJvyes36h8OMleUTXrAREmdsiM7qw5Q/FnuMH5b6qJt8J8
JakTNLacFbhEWgxLC4UkEhxoiqBKqEhH2CNVqsde6qYx2uQva8PEOqs+Q03RVYnn
1gRN57fLT5ULVyKaT+cBNc/oOzAhc0tF6scxjbtEr6VRAgMBAAE=
-----END PUBLIC KEY-----
FORMPILOT_PUBLIC_KEY
);

/**
 * FormPilot Pro commercial licensing client.
 * The license server decides which domains/features are authorized.
 */
function ssb_license_server_url() {
    // Commercial builds are pinned to the official FormPilot licensing server.
    if (defined('SSB_LICENSE_SERVER_URL') && SSB_LICENSE_SERVER_URL) return untrailingslashit(esc_url_raw(SSB_LICENSE_SERVER_URL));
    return '';
}
function ssb_license_key() { return trim((string)get_option('ssb_license_key','')); }
function ssb_license_payload() { $v = get_option('ssb_license_payload', []); return is_array($v) ? $v : []; }
function ssb_license_last_check() { return (int)get_option('ssb_license_last_check', 0); }
function ssb_license_grace_until() { return (int)get_option('ssb_license_grace_until', 0); }
function ssb_license_message() { return sanitize_text_field(get_option('ssb_license_message','')); }
function ssb_license_installation_id() {
    $id=sanitize_text_field((string)get_option('ssb_license_installation_id',''));
    if(!preg_match('/^fpinst_[a-f0-9]{16,64}$/i',$id)){ $id='fpinst_'.strtolower(str_replace('-','',wp_generate_uuid4())); update_option('ssb_license_installation_id',$id,false); }
    return $id;
}
function ssb_license_installation_secret() { return trim((string)get_option('ssb_license_installation_secret','')); }
function ssb_license_telemetry() {
    $u=wp_get_current_user();
    return ['installation_id'=>ssb_license_installation_id(),'site_url'=>home_url('/'),'site_name'=>get_bloginfo('name'),'admin_email'=>sanitize_email(get_option('admin_email','')),'admin_name'=>is_user_logged_in()&&current_user_can('manage_options')?sanitize_text_field(trim(($u->display_name??''))):'','plugin_version'=>SSB_VERSION,'wp_version'=>get_bloginfo('version'),'php_version'=>PHP_VERSION,'environment'=>function_exists('wp_get_environment_type')?sanitize_key(wp_get_environment_type()):'production'];
}
function ssb_license_request_headers($route,$body) {
    $headers=['Accept'=>'application/json','Content-Type'=>'application/json','X-FormPilot-Installation'=>ssb_license_installation_id()];
    $secret=ssb_license_installation_secret();
    if($secret){ $ts=time(); $rid='fpreq_'.strtolower(str_replace('-','',wp_generate_uuid4())); $canonical=implode("\n",['POST',$route,(string)$ts,$rid,hash('sha256',$body)]); $headers['X-FormPilot-Timestamp']=(string)$ts; $headers['X-FormPilot-Request']=$rid; $headers['X-FormPilot-Secret']=$secret; $headers['X-FormPilot-Signature']=hash_hmac('sha256',$canonical,$secret); }
    return $headers;
}
function ssb_license_store_installation_response($data) {
    if(!is_array($data))return;
    if(!empty($data['installation_id'])&&preg_match('/^fpinst_[a-f0-9]{16,64}$/i',(string)$data['installation_id'])) update_option('ssb_license_installation_id',sanitize_text_field($data['installation_id']),false);
    if(!empty($data['installation_secret'])) update_option('ssb_license_installation_secret',sanitize_text_field($data['installation_secret']),false);
}

function ssb_license_domain() {
    $host = wp_parse_url(home_url('/'), PHP_URL_HOST);
    $host = strtolower((string)$host);
    return preg_replace('/^www\./', '', $host);
}
function ssb_license_endpoint($path) {
    $base = trailingslashit(ssb_license_server_url());
    if (!$base) return '';
    return $base . 'wp-json/formpilot/v1/license/' . ltrim($path,'/');
}

function ssb_trial_request(){
    $base=trailingslashit(ssb_license_server_url()); if(!$base)return new WP_Error('trial_not_configured','Trial server URL is not configured.');
    $endpoint=$base.'wp-json/formpilot/v1/license/trial'; $route='/formpilot/v1/license/trial'; $body=wp_json_encode(ssb_license_telemetry());
    $r=wp_remote_post($endpoint,['timeout'=>20,'redirection'=>3,'sslverify'=>true,'headers'=>ssb_license_request_headers($route,$body),'body'=>$body]);
    if(is_wp_error($r))return $r; $code=(int)wp_remote_retrieve_response_code($r); $data=json_decode(wp_remote_retrieve_body($r),true);
    if(!is_array($data))return new WP_Error('trial_invalid_response','Trial server did not return JSON.');
    if($code<200||$code>=300||empty($data['valid']))return new WP_Error('trial_rejected',sanitize_text_field($data['message']??'The free trial could not be started.'),['response'=>$data]);
    if(!ssb_license_verify_signature($data))return new WP_Error('trial_invalid_signature','The trial entitlement signature could not be verified.');
    $diag=ssb_license_signature_diagnostics($data); $ent=$diag['entitlement']??[];
    if(($ent['domain']??'')!==ssb_license_domain())return new WP_Error('trial_domain','The trial entitlement is for a different domain.');
    ssb_license_store_installation_response($data);
    update_option('ssb_trial_payload',$ent,false); update_option('ssb_trial_signature',sanitize_text_field((string)$data['signature']),false); update_option('ssb_trial_signed_payload',sanitize_text_field((string)($data['signed_payload']??'')),false); update_option('ssb_trial_server_fingerprint',sanitize_text_field((string)($data['signature_public_fingerprint']??'')),false); return $data;
}

function ssb_license_request($action) {
    $key = ssb_license_key();
    if (!ssb_license_server_url() || !$key) return new WP_Error('license_not_configured','License server URL and license key are required.');

    $payload = array_merge(['license_key'=>$key],ssb_license_telemetry());

    // Try the normal REST URL first, then WordPress' rest_route fallback.
    // The fallback is useful on hosts where /wp-json/ is blocked or rewrite rules are not ready yet.
    $endpoints = [
        ssb_license_endpoint($action),
        add_query_arg('rest_route', '/formpilot/v1/license/' . ltrim($action,'/'), trailingslashit(ssb_license_server_url())),
    ];
    $last_error = null;

    foreach (array_unique(array_filter($endpoints)) as $endpoint) {
        $body=wp_json_encode($payload);
        $response = wp_remote_post($endpoint, [
            'timeout' => 20,
            'redirection' => 3,
            'sslverify' => true,
            'headers' => ssb_license_request_headers('/formpilot/v1/license/'.ltrim($action,'/'),$body),
            'body' => $body,
        ]);
        if (is_wp_error($response)) {
            $last_error = $response;
            continue;
        }

        $code = (int) wp_remote_retrieve_response_code($response);
        $body = (string) wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (!is_array($data)) {
            $ctype = (string) wp_remote_retrieve_header($response, 'content-type');
            $preview = trim(wp_strip_all_tags($body));
            if (strlen($preview) > 180) $preview = substr($preview, 0, 180) . '…';
            $message = 'License server did not return JSON (HTTP ' . ($code ?: 0) . ').';
            if ($ctype) $message .= ' Content-Type: ' . sanitize_text_field($ctype) . '.';
            if ($preview) $message .= ' Response: ' . sanitize_text_field($preview);
            $last_error = new WP_Error('license_invalid_response', $message, ['status'=>$code,'endpoint'=>$endpoint]);
            continue;
        }

        if ($code < 200 || $code >= 300 || empty($data['valid'])) {
            $message = sanitize_text_field($data['message'] ?? 'License validation failed.');
            if (!$message) $message = 'License validation failed (HTTP '.($code ?: 0).').';
            if (!empty($data['activation_error'])) $message .= ' Server diagnostic: '.sanitize_text_field((string)$data['activation_error']);
            if (isset($data['activation_table_exists'])) $message .= ' Activation table: '.($data['activation_table_exists']?'present':'missing').'.';
            return new WP_Error('license_rejected', $message, ['response'=>$data,'status'=>$code]);
        }
        ssb_license_store_installation_response($data);
        return $data;
    }

    return $last_error ?: new WP_Error('license_unreachable','The FormPilot license server could not be reached.');
}
function ssb_license_signature_diagnostics($data) {
    $out = [
        'ok' => false,
        'reason' => 'Unknown signature verification failure.',
        'payload_sha256' => '',
        'signed_payload_present' => false,
        'signature_present' => !empty($data['signature']),
        'signature_result' => null,
        'openssl_error' => '',
        'key_id' => sanitize_text_field((string)($data['signature_key_id'] ?? 'fp-key-1')),
        'algorithm' => sanitize_text_field((string)($data['signature_alg'] ?? 'RSA-SHA256')),
        'server_fingerprint' => sanitize_text_field((string)($data['signature_public_fingerprint'] ?? '')),
        'client_fingerprint' => ssb_license_public_fingerprint(),
        'entitlement' => [],
    ];
    if (!is_array($data) || empty($data['signature'])) {
        $out['reason'] = 'The license response did not contain a signature.';
        return $out;
    }
    $json = '';
    if (!empty($data['signed_payload'])) {
        $out['signed_payload_present'] = true;
        $json = base64_decode((string)$data['signed_payload'], true);
        if ($json === false || $json === '') {
            $out['reason'] = 'The signed payload is not valid base64.';
            return $out;
        }
        $decoded = json_decode($json, true);
        if (!is_array($decoded)) {
            $out['reason'] = 'The signed payload is not valid JSON.';
            return $out;
        }
        $out['entitlement'] = $decoded;
    } elseif (!empty($data['license']) && is_array($data['license'])) {
        // Backward compatibility with pre-10.8 license responses. New builds use
        // signed_payload as the authoritative cryptographic source.
        $json = wp_json_encode($data['license'], JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
        $out['entitlement'] = $data['license'];
    } else {
        $out['reason'] = 'The license response did not contain an entitlement payload.';
        return $out;
    }
    $out['payload_sha256'] = strtoupper(hash('sha256', $json));
    $entitlement = $out['entitlement'];
    if (($entitlement['product'] ?? '') !== 'formpilot-pro') {
        $out['reason'] = 'The signed entitlement has an unexpected product identifier.';
        return $out;
    }
    $sig = base64_decode((string)$data['signature'], true);
    if ($sig === false || $sig === '') {
        $out['reason'] = 'The signature is not valid base64.';
        return $out;
    }
    if (!function_exists('openssl_verify')) {
        $out['reason'] = 'OpenSSL signature verification is unavailable on this server.';
        return $out;
    }
    $result = openssl_verify($json, $sig, SSB_LICENSE_PUBLIC_KEY, OPENSSL_ALGO_SHA256);
    $out['signature_result'] = $result;
    if ($result === 1) {
        $out['ok'] = true;
        $out['reason'] = 'Signature verified successfully.';
        return $out;
    }
    $errors = [];
    while (function_exists('openssl_error_string') && ($err = openssl_error_string())) $errors[] = $err;
    $out['openssl_error'] = implode(' | ', $errors);
    $out['reason'] = $result === 0 ? 'The RSA-SHA256 signature does not match the signed payload.' : 'OpenSSL returned an error while verifying the signature.';
    return $out;
}
function ssb_license_verify_signature($data) {
    $diag = ssb_license_signature_diagnostics($data);
    return !empty($diag['ok']);
}
function ssb_license_public_fingerprint() {
    $pem=trim((string)SSB_LICENSE_PUBLIC_KEY);
    $der=base64_decode(preg_replace('/-----[^-]+-----/','',str_replace(["\r","\n",' '],'',$pem)),true);
    return $der ? strtoupper(hash('sha256',$der)) : '';
}
function ssb_license_store_valid($data) {
    $diag = ssb_license_signature_diagnostics($data);
    $entitlement = is_array($diag['entitlement'] ?? null) ? $diag['entitlement'] : [];
    if (empty($diag['ok'])) {
        update_option('ssb_license_signature_diagnostic', $diag, false);
        update_option('ssb_license_message', 'License signature verification failed: ' . $diag['reason'], false);
        return false;
    }
    // The signed payload is authoritative. Do not require the separately returned
    // convenience `license` object to have identical PHP array serialization.
    if (($entitlement['domain'] ?? '') !== ssb_license_domain()) {
        $diag['ok'] = false;
        $diag['reason'] = 'The signed entitlement is for a different domain: ' . sanitize_text_field((string)($entitlement['domain'] ?? '')) . '.';
        update_option('ssb_license_signature_diagnostic', $diag, false);
        update_option('ssb_license_message', $diag['reason'], false);
        return false;
    }
    update_option('ssb_license_signature_diagnostic', $diag, false);
    update_option('ssb_license_signature', sanitize_text_field((string)$data['signature']), false);
    if (!empty($data['signed_payload'])) update_option('ssb_license_signed_payload', sanitize_text_field((string)$data['signed_payload']), false); else delete_option('ssb_license_signed_payload');
    update_option('ssb_license_payload', $entitlement, false);
    update_option('ssb_license_signature_alg', sanitize_text_field((string)($data['signature_alg'] ?? 'RSA-SHA256')), false);
    update_option('ssb_license_key_id', sanitize_text_field((string)($data['signature_key_id'] ?? 'fp-key-1')), false);
    update_option('ssb_license_server_fingerprint', sanitize_text_field((string)($data['signature_public_fingerprint'] ?? '')), false);
    update_option('ssb_license_last_check', time(), false);
    update_option('ssb_license_grace_until', time() + 7 * DAY_IN_SECONDS, false);
    update_option('ssb_license_message', 'License active.', false);
    return true;
}
function ssb_license_validate($force=false) {
    if (!ssb_license_key() || !ssb_license_server_url()) return false;
    if (!$force && ssb_license_last_check() && (time() - ssb_license_last_check()) < DAY_IN_SECONDS) {
        $payload = ssb_license_payload();
        $cached = [
            'license'=>$payload,
            'signature'=>get_option('ssb_license_signature',''),
            'signed_payload'=>get_option('ssb_license_signed_payload',''),
            'signature_alg'=>get_option('ssb_license_signature_alg','RSA-SHA256'),
            'signature_key_id'=>get_option('ssb_license_key_id','fp-key-1'),
            'signature_public_fingerprint'=>get_option('ssb_license_server_fingerprint',''),
        ];
        $diag = ssb_license_signature_diagnostics($cached);
        update_option('ssb_license_signature_diagnostic',$diag,false);
        return !empty($diag['ok']) && !empty($diag['entitlement']['status']) && $diag['entitlement']['status'] === 'active';
    }
    $data = ssb_license_request('validate');
    if (is_wp_error($data)) {
        $server_message = $data->get_error_message();
        if ($force && stripos($server_message, 'domain is not activated') !== false && ssb_license_key()) {
            $repair = ssb_license_request('activate');
            if (!is_wp_error($repair) && ssb_license_store_valid($repair)) return true;
            if (is_wp_error($repair)) $server_message = $repair->get_error_message();
        }
        update_option('ssb_license_message', $server_message, false);
        $details = $data->get_error_data();
        $remote = is_array($details) && is_array($details['response'] ?? null) ? $details['response'] : [];
        // A signed server decision such as suspended/revoked/expired is authoritative and must never use offline grace.
        if (!empty($remote['signature']) && (!empty($remote['signed_payload']) || !empty($remote['license'])) && ssb_license_verify_signature($remote)) {
            $rdiag = ssb_license_signature_diagnostics($remote);
            update_option('ssb_license_signature_diagnostic',$rdiag,false);
            $status = sanitize_key($rdiag['entitlement']['status'] ?? ($remote['license']['status'] ?? ''));
            // A cryptographically signed inactive decision is authoritative.
            if (in_array($status, ['suspended','revoked','expired'], true)) {
                update_option('ssb_license_signature', sanitize_text_field((string)$remote['signature']), false);
                if (!empty($remote['signed_payload'])) update_option('ssb_license_signed_payload', sanitize_text_field((string)$remote['signed_payload']), false);
                update_option('ssb_license_payload', is_array($rdiag['entitlement'] ?? null) ? $rdiag['entitlement'] : [], false);
                update_option('ssb_license_last_check', time(), false);
                delete_option('ssb_license_grace_until');
                update_option('ssb_license_message', sanitize_text_field($remote['message'] ?? ('License is '.$status.'.')), false);
                return false;
            }
        }
        return ssb_license_grace_until() > time();
    }
    if (!ssb_license_store_valid($data)) return false;
    return true;
}
function ssb_license_activate($server_url, $key) {
    // Ignore client-supplied license server URLs in commercial builds.
    $key = preg_replace('/\s+/', '', strtoupper(sanitize_text_field($key)));
    update_option('ssb_license_key', $key, false);
    delete_option('ssb_license_payload');
    delete_option('ssb_license_signature');
    delete_option('ssb_license_signed_payload');
    delete_option('ssb_license_last_check');
    delete_option('ssb_license_grace_until');
    delete_option('ssb_license_signature_diagnostic');
    $data = ssb_license_request('activate');
    if (is_wp_error($data)) {
        update_option('ssb_license_message', $data->get_error_message(), false);
        return $data;
    }
    if (!ssb_license_store_valid($data)) {
        $server_message = is_array($data) ? sanitize_text_field($data['message'] ?? '') : '';
        if ($server_message) return new WP_Error('license_server_error', $server_message, ['server_response'=>$data]);
        return new WP_Error('license_invalid_signature','The license server returned an invalid signed entitlement.');
    }
    return $data;
}
function ssb_license_deactivate() {
    $data = ssb_license_request('deactivate');
    delete_option('ssb_license_payload');
    delete_option('ssb_license_signature');
    delete_option('ssb_license_signed_payload');
    delete_option('ssb_license_last_check');
    delete_option('ssb_license_grace_until');
    update_option('ssb_license_message', is_wp_error($data) ? $data->get_error_message() : 'License deactivated.', false);
    return $data;
}
function ssb_license_status() {
    $trial=ssb_trial_status();
    $payload = ssb_license_payload();
    $signature = get_option('ssb_license_signature','');
    $signed_payload = get_option('ssb_license_signed_payload','');
    $cached_response = [
        'license'=>$payload,
        'signature'=>$signature,
        'signed_payload'=>$signed_payload,
        'signature_alg'=>get_option('ssb_license_signature_alg','RSA-SHA256'),
        'signature_key_id'=>get_option('ssb_license_key_id','fp-key-1'),
        'signature_public_fingerprint'=>get_option('ssb_license_server_fingerprint',''),
    ];
    $signature_ok = ssb_license_verify_signature($cached_response);
    update_option('ssb_license_signature_diagnostic', ssb_license_signature_diagnostics($cached_response), false);
    $license_status=sanitize_key($payload['status'] ?? ''); $active = $signature_ok && (($payload['domain'] ?? '') === ssb_license_domain()) && in_array($license_status,['active','grace_period','cancelled'],true);
    if ($active && !empty($payload['expires_at']) && strtotime($payload['expires_at']) < current_time('timestamp')) { $active = false; }
    $authoritative_inactive = in_array($license_status, ['suspended','revoked','expired','trial_expired'], true);
    $grace = !$active && !$authoritative_inactive && ssb_license_grace_until() > time();
    if(!$active && !$grace && !empty($trial['active'])) return [
        'active'=>true,'server_active'=>true,'grace'=>false,'trial'=>true,'plan'=>'trial','expires_at'=>$trial['expires_at'],'features'=>$trial['features'],'domain'=>ssb_license_domain(),'message'=>'14-day FormPilot Pro trial is active.'
    ];
    return [
        'active'=>$active || $grace,
        'server_active'=>$active,
        'grace'=>$grace,
        'trial'=>false,
        'plan'=>sanitize_key($payload['plan'] ?? ''),
        'expires_at'=>sanitize_text_field($payload['expires_at'] ?? ''),
        'features'=>is_array($payload['features'] ?? null) ? $payload['features'] : (['core'=>true,'forms'=>true,'builder'=>true,'smtp'=>true,'settings'=>true]),
        'domain'=>sanitize_text_field($payload['domain'] ?? ''),
        'message'=>ssb_license_message(),
        'status'=>$license_status,
    ];
}
function ssb_license_can($feature='core') {
    $state = ssb_license_status();
    // FormPilot has a genuine free tier. Core administration, the form builder, and basic forms remain available without a license.
    if (!$state['active']) return in_array($feature, ['core','forms','builder','smtp','settings'], true);
    if (!$feature) return true;
    if (!$state['features']) return false;
    // Paid FormPilot plans are full-feature plans. Older signed entitlements
    // may predate a feature key added in a later client release; keep those
    // valid paid licenses functional while the signed plan remains active.
    if (!array_key_exists($feature, $state['features'])) {
        $full_plans = ['premium','agency','lifetime'];
        if (in_array(sanitize_key($state['plan'] ?? ''), $full_plans, true)) return true;
    }
    return !empty($state['features'][$feature]);
}
function ssb_license_admin_guard($feature='core') {
    if (ssb_license_can($feature)) return true;
    echo '<div class="wrap"><div class="notice notice-error"><p><strong>FormPilot Pro license required.</strong> Activate a valid license for this website to use this feature.</p><p><a class="button button-primary" href="' . esc_url(admin_url('admin.php?page=ssb-license')) . '">Activate License</a></p></div></div>';
    return false;
}
function ssb_license_cron() { ssb_license_validate(true); }
function ssb_license_schedule() {
    if (!wp_next_scheduled('ssb_license_daily_check')) wp_schedule_event(time()+300, 'daily', 'ssb_license_daily_check');
}
add_action('ssb_license_daily_check','ssb_license_cron');
function ssb_license_unschedule() { $t=wp_next_scheduled('ssb_license_daily_check'); if($t) wp_unschedule_event($t,'ssb_license_daily_check'); }

add_action('wp_ajax_ssb_license_action', 'ssb_license_ajax_action');
function ssb_license_ajax_action() {
    if (!current_user_can('manage_options')) wp_send_json_error(['message'=>'Unauthorized.'], 403);
    check_ajax_referer('ssb_admin_nonce', 'nonce');
    $action = sanitize_key($_POST['license_action'] ?? '');
    $result = true;
    if ($action === 'activate') {
        $key = $_POST['license_key'] ?? '';
        $result = ssb_license_activate('', $key);
    } elseif ($action === 'check') {
        $result = ssb_license_validate(true);
    } elseif ($action === 'deactivate') {
        $result = ssb_license_deactivate();
    } else {
        wp_send_json_error(['message'=>'Invalid license action.'], 400);
    }
    if (is_wp_error($result)) {
        wp_send_json_error([
            'message' => $result->get_error_message(),
            'state' => ssb_license_status(),
        ], 400);
    }
    $state = ssb_license_status();
    wp_send_json_success([
        'message' => ssb_license_message(),
        'state' => $state,
        'active' => !empty($state['active']),
        'plan' => $state['plan'],
        'expires_at' => $state['expires_at'],
        'domain' => $state['domain'] ?: ssb_license_domain(),
        'last_check' => ssb_license_last_check() ? wp_date(get_option('date_format').' '.get_option('time_format'), ssb_license_last_check()) : '—',
        'features' => $state['features'],
        'has_key' => (bool)ssb_license_key(),
    ]);
}

function ssb_license_page() {
    if (!current_user_can('manage_options')) return;
    $notice=''; $type='';
    if (isset($_POST['ssb_license_action']) && check_admin_referer('ssb_license_settings')) {
        $action=sanitize_key($_POST['ssb_license_action']);
        if($action==='activate'){
            $result=ssb_license_activate($_POST['ssb_license_server_url']??'',$_POST['ssb_license_key']??'');
            if(is_wp_error($result)){ $notice=$result->get_error_message(); $type='error'; } else { $notice='License activated for '.ssb_license_domain().'.'; $type='success'; }
        } elseif($action==='deactivate'){
            $result=ssb_license_deactivate(); $notice=is_wp_error($result)?$result->get_error_message():'License deactivated.'; $type=is_wp_error($result)?'error':'success';
        } elseif($action==='trial'){
            $result=ssb_trial_request(); $notice=is_wp_error($result)?$result->get_error_message():'Your 14-day Pro trial is now active on this website.'; $type=is_wp_error($result)?'error':'success';
        } elseif($action==='check'){
            $ok=ssb_license_validate(true); $notice=ssb_license_message(); $type=$ok?'success':'error';
        }
    }
    $state=ssb_license_status();
    ?>
    <div class="wrap ssb-license-page"><div class="ssb-license-hero"><div><span>FormPilot Pro</span><h1>Commercial License</h1><p>Authorize this WordPress installation and unlock the features included in its plan.</p></div><div class="ssb-license-status <?php echo $state['active']?'active':'inactive'; ?>"><?php echo $state['active']?'● Active':'● Not active'; ?></div></div>
    <?php if($notice): ?><div class="notice notice-<?php echo esc_attr($type); ?> is-dismissible"><p><?php echo esc_html($notice); ?></p></div><?php endif; ?>
    <div class="ssb-license-grid">
      <div class="ssb-license-card">
        <h2>License Connection</h2>
        <form id="ssb-license-activate-form" method="post">
          <?php wp_nonce_field('ssb_license_settings'); ?>
          <input type="hidden" name="ssb_license_action" value="activate">
          <p><label>License Server URL<br><input class="regular-text" type="url" value="<?php echo esc_attr(ssb_license_server_url()); ?>" readonly></label></p>
          <p><label>License Key<br><input id="ssb-license-key" class="regular-text" type="text" name="ssb_license_key" value="<?php echo esc_attr(ssb_license_key()); ?>" placeholder="FP-XXXX-XXXX-XXXX-XXXX-XXXX"></label></p>
          <p><label>This website<br><input class="regular-text" readonly value="<?php echo esc_attr(home_url('/')); ?>"></label></p>
          <p><button id="ssb-license-activate" class="button button-primary">Activate / Update License</button> <button type="submit" name="ssb_license_action" value="trial" class="button" style="margin-left:6px">Start 14-Day Free Trial</button></p>
          <p class="description">Activation and validation are performed against the official FormPilot license server.</p>
        </form>
        <hr>
        <button type="button" id="ssb-license-check" class="button">Check License Now</button>
        <?php if(ssb_license_key()): ?>
          <button type="button" id="ssb-license-deactivate" class="button" style="margin-left:8px">Deactivate License</button>
        <?php endif; ?>
        <span id="ssb-license-live-indicator" class="ssb-license-live-indicator" aria-live="polite"></span>
      </div>
      <div class="ssb-license-card">
        <h2>License Details</h2>
        <dl>
          <dt>Domain</dt><dd id="ssb-license-domain"><?php echo esc_html($state['domain']?:ssb_license_domain()); ?></dd>
          <dt>Plan</dt><dd id="ssb-license-plan"><?php echo esc_html(!empty($state['trial'])?'14-Day Free Trial':($state['plan']?:'—')); ?></dd>
          <dt>Expires</dt><dd id="ssb-license-expires"><?php echo esc_html($state['expires_at']?:'Never'); ?></dd>
          <dt>Last check</dt><dd id="ssb-license-last-check"><?php echo ssb_license_last_check()?esc_html(wp_date(get_option('date_format').' '.get_option('time_format'),ssb_license_last_check())):'—'; ?></dd>
        </dl>
        <h3>Status</h3>
        <div id="ssb-license-state-card" class="ssb-license-state-card <?php echo $state['active']?'is-active':'is-inactive'; ?>">
          <strong id="ssb-license-state-text"><?php echo $state['active']?'License active':'License not active'; ?></strong>
          <span id="ssb-license-state-message"><?php echo esc_html($state['message'] ?: ($state['active']?'Your license is active on this website.':'Activate a license to unlock Pro features.')); ?></span>
        </div>
        <h3>Enabled Features</h3>
        <div id="ssb-license-features" class="ssb-license-features">
          <?php foreach($state['features'] as $feature=>$enabled): if($enabled): ?><span><?php echo esc_html(ucwords(str_replace('_',' ',$feature))); ?></span><?php endif; endforeach; ?>
        </div>
        <?php if(!empty($state['trial'])): ?><p class="description"><strong>14-day Pro trial:</strong> full Pro features are available until <?php echo esc_html($state['expires_at']); ?>. No payment is required to start the trial.</p><?php endif; ?>
        <?php if($state['grace']&&!$state['server_active']): ?><p class="description">The license server could not be reached. FormPilot is using its temporary 7-day grace period.</p><?php endif; ?>
      </div>
    </div>
    <style>
      .ssb-license-page{max-width:1050px;margin-top:22px}.ssb-license-hero{display:flex;justify-content:space-between;align-items:center;gap:20px;padding:28px 30px;border-radius:18px;background:linear-gradient(135deg,#111827,#312e81);color:#fff;margin-bottom:18px}.ssb-license-hero span{font-size:11px;text-transform:uppercase;letter-spacing:.12em;opacity:.7}.ssb-license-hero h1{color:#fff;margin:4px 0;font-size:28px}.ssb-license-hero p{color:#cbd5e1;margin:0}.ssb-license-status{padding:9px 14px;border-radius:999px;font-weight:700;font-size:12px}.ssb-license-status.active{background:#064e3b;color:#a7f3d0}.ssb-license-status.inactive{background:#7f1d1d;color:#fecaca}.ssb-license-grid{display:grid;grid-template-columns:1fr 1fr;gap:18px}.ssb-license-card{background:#fff;border:1px solid #e5e7eb;border-radius:16px;padding:22px}.ssb-license-card h2{margin-top:0}.ssb-license-card input{margin-top:5px}.ssb-license-card dt{font-size:11px;text-transform:uppercase;color:#64748b;font-weight:700;margin-top:12px}.ssb-license-card dd{margin:4px 0 0;font-weight:600}.ssb-license-features{display:flex;flex-wrap:wrap;gap:7px}.ssb-license-features span{background:#eef2ff;color:#4338ca;padding:6px 9px;border-radius:999px;font-size:11px;font-weight:700}.ssb-license-state-card{margin-top:8px;padding:14px 16px;border-radius:12px;border:1px solid #e5e7eb;background:#f8fafc}.ssb-license-state-card.is-active{background:#ecfdf5;border-color:#a7f3d0}.ssb-license-state-card.is-inactive{background:#fff7ed;border-color:#fed7aa}.ssb-license-state-card strong{display:block;font-size:15px}.ssb-license-state-card span{display:block;margin-top:4px;color:#64748b;font-size:12px}.ssb-license-live-indicator{display:inline-block;margin-left:10px;font-size:12px;color:#64748b}.ssb-license-live-indicator.is-busy{color:#4f46e5}.ssb-license-live-indicator.is-ok{color:#047857}.ssb-license-live-indicator.is-error{color:#b91c1c}@media(max-width:800px){.ssb-license-grid{grid-template-columns:1fr}.ssb-license-hero{align-items:flex-start;flex-direction:column}}
    </style>
    <?php
}
