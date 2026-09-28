<?php
if (!defined('ABSPATH')) exit;

/**
 * FormPilot central licensing lifecycle foundation.
 * Kept additive for backwards compatibility with the 4.1.x schema/API.
 */
function fplm_utc_now() { return gmdate('Y-m-d H:i:s'); }
function fplm_trial_identity_hash($site_url,$admin_email='',$site_name='') { return hash('sha256',strtolower(trim((string)$site_url)).'|'.strtolower(trim((string)$admin_email)).'|'.strtolower(trim((string)$site_name))); }
function fplm_installation_id() {
    try { return 'fpinst_' . strtolower(bin2hex(random_bytes(16))); }
    catch (Throwable $e) { return 'fpinst_' . strtolower(wp_generate_password(32, false, false)); }
}
function fplm_installation_secret() {
    try { return bin2hex(random_bytes(32)); }
    catch (Throwable $e) { return hash('sha256', wp_generate_password(64, true, true) . microtime(true)); }
}
function fplm_installation_request_id() {
    try { return 'fpreq_' . bin2hex(random_bytes(12)); }
    catch (Throwable $e) { return 'fpreq_' . wp_generate_password(24, false, false); }
}
function fplm_hash_secret($secret) { return hash('sha256', (string)$secret); }
function fplm_request_signature($secret, $timestamp, $request_id, $method, $route, $body) {
    return hash_hmac('sha256', implode("\n", [strtoupper((string)$method), (string)$route, (string)$timestamp, (string)$request_id, hash('sha256', (string)$body)]), (string)$secret);
}
function fplm_audit($event, $license_id=0, $installation_id='', $actor='system', $metadata=[]) {
    global $wpdb; $t=fplm_tables();
    if (!isset($t['audit'])) return false;
    $safe=[];
    foreach ((array)$metadata as $k=>$v) {
        $k=sanitize_key($k); if (!$k || in_array($k,['license_key','secret','token','signature','private_key','card','cvv'],true)) continue;
        if (is_scalar($v)) $safe[$k]=substr(sanitize_text_field((string)$v),0,500);
    }
    $wpdb->insert($t['audit'], [
        'license_id'=>absint($license_id), 'installation_id'=>sanitize_text_field($installation_id),
        'event'=>sanitize_key($event), 'actor'=>substr(sanitize_text_field($actor),0,190),
        'metadata'=>wp_json_encode($safe), 'created_at'=>fplm_utc_now()
    ]);
    return (bool)$wpdb->insert_id;
}
function fplm_installation_find($installation_id='', $license_id=0, $domain='') {
    global $wpdb; $t=fplm_tables();
    if (!empty($installation_id)) return $wpdb->get_row($wpdb->prepare("SELECT * FROM {$t['installations']} WHERE installation_id=%s LIMIT 1",sanitize_text_field($installation_id)));
    if ($license_id && $domain) return $wpdb->get_row($wpdb->prepare("SELECT * FROM {$t['installations']} WHERE license_id=%d AND domain=%s LIMIT 1",absint($license_id),sanitize_text_field($domain)));
    return null;
}
function fplm_installation_upsert($data, $license_id=0) {
    global $wpdb; $t=fplm_tables();
    $id=sanitize_text_field($data['installation_id']??'');
    if (!preg_match('/^fpinst_[a-f0-9]{16,64}$/i',$id)) $id=fplm_installation_id();
    $domain=sanitize_text_field($data['domain']??''); $url=esc_url_raw($data['site_url']??'');
    $existing=fplm_installation_find($id, $license_id, $domain); if($existing && $existing->domain!==$domain && $license_id){ $existing=null; $id=fplm_installation_id(); } $now=fplm_utc_now();
    $row=[
        'installation_id'=>$id,'license_id'=>absint($license_id ?: ($data['license_id']??0)),
        'domain'=>$domain,'site_url'=>$url,'site_name'=>substr(sanitize_text_field($data['site_name']??''),0,190),
        'admin_email'=>sanitize_email($data['admin_email']??''),'admin_name'=>substr(sanitize_text_field($data['admin_name']??''),0,190),
        'wp_version'=>substr(sanitize_text_field($data['wp_version']??''),0,30),'plugin_version'=>substr(sanitize_text_field($data['plugin_version']??''),0,30),
        'php_version'=>substr(sanitize_text_field($data['php_version']??''),0,30),'environment'=>sanitize_key($data['environment']??'production'),'status'=>sanitize_key($data['status']??'active'),
        'trial_started_at'=>!empty($data['trial_started_at'])?sanitize_text_field($data['trial_started_at']):null,
        'trial_expires_at'=>!empty($data['trial_expires_at'])?sanitize_text_field($data['trial_expires_at']):null,
        'last_seen'=>$now
    ];
    if (!$existing) { $row['first_seen']=$now; $secret=fplm_installation_secret(); $row['secret_hash']=fplm_hash_secret($secret); $row['created_at']=$now; $row['updated_at']=$now; $ok=$wpdb->insert($t['installations'],$row); if(!$ok)return new WP_Error('installation_save_failed','Installation could not be registered.'); $row['secret']=$secret; fplm_audit('installation_created',$row['license_id'],$id,'system',['domain'=>$domain]); return (object)$row; }
    $update=$row; unset($update['installation_id'],$update['first_seen'],$update['secret_hash'],$update['created_at']); $update['updated_at']=$now;
    $wpdb->update($t['installations'],$update,['id'=>$existing->id]); return $wpdb->get_row($wpdb->prepare("SELECT * FROM {$t['installations']} WHERE id=%d",$existing->id));
}
function fplm_verify_installation_request($r) {
    if (!$r instanceof WP_REST_Request) return true;
    $route=$r->get_route(); if (strpos($route,'/formpilot/v1/license/')!==0 && strpos($route,'/formpilot/v1/update/')!==0) return true;
    $id=sanitize_text_field($r->get_header('X-FormPilot-Installation')); $ts=(int)$r->get_header('X-FormPilot-Timestamp'); $rid=sanitize_text_field($r->get_header('X-FormPilot-Request')); $sig=sanitize_text_field($r->get_header('X-FormPilot-Signature'));
    if (!$id || !$ts || !$rid || !$sig) { if(substr($route,-8)==='/activate' || substr($route,-6)==='/trial') return true; global $wpdb; $t=fplm_tables(); $known=$id?$wpdb->get_var($wpdb->prepare("SELECT id FROM {$t['installations']} WHERE installation_id=%s AND secret_hash<>'' LIMIT 1",$id)):0; if($known) return new WP_Error('fplm_installation_auth','Installation authentication is required.',['status'=>401]); return true; } // legacy bootstrap/migration request
    if (abs(time()-$ts)>300) return new WP_Error('fplm_request_expired','License request timestamp is outside the allowed window.',['status'=>401]);
    global $wpdb; $t=fplm_tables(); $inst=$wpdb->get_row($wpdb->prepare("SELECT * FROM {$t['installations']} WHERE installation_id=%s LIMIT 1",$id));
    if (!$inst || empty($inst->secret_hash)) return new WP_Error('fplm_installation_unknown','Installation is not registered.',['status'=>401]);
    $body=$r->get_body();
    // Secret itself is never stored; bootstrap secret is returned only during registration/activation.
    $candidate=(string)$r->get_header('X-FormPilot-Secret');
    if (!$candidate || !hash_equals((string)$inst->secret_hash,fplm_hash_secret($candidate))) return new WP_Error('fplm_installation_auth','Installation authentication failed.',['status'=>401]);
    $used=$wpdb->get_var($wpdb->prepare("SELECT id FROM {$t['requests']} WHERE installation_id=%s AND request_id=%s LIMIT 1",$id,$rid));
    if ($used) return new WP_Error('fplm_replay','This license request has already been processed.',['status'=>409]);
    $expected=fplm_request_signature($candidate,$ts,$rid,$r->get_method(),$route,$body);
    if (!hash_equals($expected,$sig)) return new WP_Error('fplm_bad_signature','License request signature is invalid.',['status'=>401]);
    $wpdb->insert($t['requests'],['installation_id'=>$id,'request_id'=>$rid,'created_at'=>fplm_utc_now()]);
    return true;
}
function fplm_installation_response($inst) {
    $out=['installation_id'=>(string)$inst->installation_id];
    if (!empty($inst->secret)) $out['installation_secret']=$inst->secret;
    return $out;
}
function fplm_record_payment($license_id,$source,$reference,$amount,$currency,$paid_at=null,$external_id='',$subscription_id='',$notes='') {
    global $wpdb; $t=fplm_tables();
    $external=sanitize_text_field($external_id); $ref=sanitize_text_field($reference);
    if ($external) { $exists=$wpdb->get_var($wpdb->prepare("SELECT id FROM {$t['payments']} WHERE external_transaction_id=%s LIMIT 1",$external)); if($exists)return (int)$exists; }
    $wpdb->insert($t['payments'],['license_id'=>absint($license_id),'source'=>sanitize_key($source?:'other'),'reference'=>$ref,'amount'=>max(0,(float)$amount),'currency'=>strtoupper(sanitize_text_field($currency?:'USD')),'paid_at'=>$paid_at?:fplm_utc_now(),'external_transaction_id'=>$external,'subscription_id'=>sanitize_text_field($subscription_id),'notes'=>substr(sanitize_textarea_field($notes),0,2000),'created_at'=>fplm_utc_now()]);
    if ($wpdb->insert_id) fplm_audit('payment_received',$license_id,'','system',['source'=>$source,'reference'=>$ref,'amount'=>$amount,'currency'=>$currency]);
    return (int)$wpdb->insert_id;
}
function fplm_transition_license($license_id,$status,$actor='system',$metadata=[]) {
    global $wpdb; $t=fplm_tables(); $row=$wpdb->get_row($wpdb->prepare("SELECT * FROM {$t['licenses']} WHERE id=%d",absint($license_id))); if(!$row)return new WP_Error('license_not_found','License not found.');
    $allowed=['trial','trial_expired','active','grace_period','expired','cancelled','revoked','suspended']; $status=sanitize_key($status); if(!in_array($status,$allowed,true))return new WP_Error('invalid_license_status','Invalid license status.');
    $wpdb->update($t['licenses'],['status'=>$status,'updated_at'=>fplm_utc_now()],['id'=>$row->id]);
    fplm_audit('license_'.str_replace('grace_period','grace',$status),$row->id,'',$actor,$metadata); return true;
}
function fplm_lifecycle_cron() {
    global $wpdb; $t=fplm_tables(); $now=fplm_utc_now();
    $wpdb->query($wpdb->prepare("DELETE FROM {$t['requests']} WHERE created_at < %s",gmdate('Y-m-d H:i:s',time()-2*DAY_IN_SECONDS)));
    $trials=$wpdb->get_results($wpdb->prepare("SELECT * FROM {$t['trials']} WHERE status='active' AND expires_at < %s",$now));
    foreach((array)$trials as $tr){ $wpdb->update($t['trials'],['status'=>'expired','updated_at'=>$now],['id'=>$tr->id]); $inst=fplm_installation_find('',0,$tr->domain); if($inst) $wpdb->update($t['installations'],['status'=>'trial_expired','updated_at'=>$now],['id'=>$inst->id]); fplm_audit('trial_expired',0,$inst->installation_id??'','system',['domain'=>$tr->domain]); }
    $licenses=$wpdb->get_results("SELECT * FROM {$t['licenses']} WHERE status IN ('active','grace_period','cancelled') AND expires_at IS NOT NULL");
    foreach((array)$licenses as $l){ if(strtotime($l->expires_at)>time())continue; $status=$l->status==='cancelled'?'expired':'expired'; $wpdb->update($t['licenses'],['status'=>$status,'updated_at'=>$now],['id'=>$l->id]); fplm_audit('license_expired',$l->id,'','system'); }
}
add_action('init',function(){ if(!wp_next_scheduled('fplm_license_lifecycle')) wp_schedule_event(time()+300,'hourly','fplm_license_lifecycle'); },20);
add_action('fplm_license_lifecycle','fplm_lifecycle_cron');

// The 4.1.6 update endpoint references this signer. Keep it compatible with the existing RSA key.
if (!function_exists('fplm_sign_entitlement')) {
    function fplm_sign_entitlement($payload) {
        $json=wp_json_encode($payload,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE); $sig='';
        if ($json===false || !openssl_sign($json,$sig,FPLM_SIGNING_PRIVATE_KEY,OPENSSL_ALGO_SHA256)) return '';
        return base64_encode($sig);
    }
}

add_action('admin_menu',function(){
    add_submenu_page('fplm-dashboard','Licensing Center','Licensing Center','manage_options','fplm-licensing-center','fplm_licensing_center_page');
    add_submenu_page('fplm-dashboard','Installations','Installations','manage_options','fplm-installations','fplm_installations_admin_page');
    add_submenu_page('fplm-dashboard','Payments','Payments','manage_options','fplm-payments','fplm_payments_admin_page');
    add_submenu_page('fplm-dashboard','Audit Log','Audit Log','manage_options','fplm-audit','fplm_audit_admin_page');
});
function fplm_admin_action_guard($nonce='fplm_central') { if(!current_user_can('manage_options') || !check_admin_referer($nonce)) wp_die('Unauthorized.'); }
function fplm_licensing_center_page() {
    global $wpdb; fplm_ensure_schema(); $t=fplm_tables();
    if(isset($_POST['fplm_create_license'])){ fplm_admin_action_guard(); $plans=fplm_plans(); $plan=sanitize_key($_POST['plan']??'premium'); $p=$plans[$plan]??$plans['premium']; $duration=sanitize_key($_POST['duration']??'plan'); $expires=null; if($duration==='custom'){ $expires=!empty($_POST['custom_expiry'])?gmdate('Y-m-d H:i:s',strtotime(sanitize_text_field($_POST['custom_expiry']))):null; } elseif($duration==='30')$expires=gmdate('Y-m-d H:i:s',time()+30*DAY_IN_SECONDS); elseif($duration==='90')$expires=gmdate('Y-m-d H:i:s',time()+90*DAY_IN_SECONDS); elseif($duration==='365')$expires=gmdate('Y-m-d H:i:s',time()+365*DAY_IN_SECONDS); elseif($duration==='lifetime'||$p['interval']==='lifetime')$expires=null; else $expires=$p['interval']==='monthly'?gmdate('Y-m-d H:i:s',time()+30*DAY_IN_SECONDS):gmdate('Y-m-d H:i:s',time()+365*DAY_IN_SECONDS); $key=fplm_license_key(); $wpdb->insert($t['licenses'],['license_hash'=>fplm_hash($key),'license_last4'=>substr($key,-4),'encrypted_key'=>fplm_encrypt($key),'customer_name'=>sanitize_text_field($_POST['customer_name']??''),'customer_email'=>sanitize_email($_POST['customer_email']??''),'plan'=>$plan,'status'=>'active','max_activations'=>max(1,absint($_POST['max_activations']??$p['activations'])),'expires_at'=>$expires,'features'=>wp_json_encode($p['features']),'amount'=>max(0,(float)($_POST['amount']??$p['amount'])),'currency'=>strtoupper(sanitize_text_field($_POST['currency']??$p['currency'])),'billing_interval'=>$p['interval'],'created_at'=>fplm_utc_now(),'updated_at'=>fplm_utc_now()]); $new_id=(int)$wpdb->insert_id; if($new_id){ $actor=wp_get_current_user(); fplm_audit('license_created',$new_id,'',$actor->user_login?:'admin',['source'=>sanitize_key($_POST['source']??'manual'),'duration'=>$duration,'reason'=>$_POST['notes']??'']); if(!empty($_POST['payment_received']))fplm_record_payment($new_id,$_POST['source']??'manual',$_POST['payment_reference']??'',(float)($_POST['amount']??$p['amount']),$_POST['currency']??$p['currency'],fplm_utc_now(),$_POST['external_id']??'',$_POST['subscription_id']??'',$_POST['notes']??''); echo '<div class="notice notice-success"><p>License created. Key: <code>'.esc_html($key).'</code></p></div>'; } }
    if(isset($_POST['fplm_central_action'])){ fplm_admin_action_guard(); $a=sanitize_key($_POST['fplm_central_action']); $lid=absint($_POST['license_id']??0); $actor=wp_get_current_user(); $actor_name=$actor->user_login?:'admin';
        if($a==='status') fplm_transition_license($lid,sanitize_key($_POST['status']??''),$actor_name);
        elseif($a==='extend'){ $days=absint($_POST['days']??0); $row=$wpdb->get_row($wpdb->prepare("SELECT * FROM {$t['licenses']} WHERE id=%d",$lid)); if($row&&$days){$base=max(time(),$row->expires_at?strtotime($row->expires_at):time());$wpdb->update($t['licenses'],['expires_at'=>gmdate('Y-m-d H:i:s',$base+$days*DAY_IN_SECONDS),'status'=>'active','updated_at'=>fplm_utc_now()],['id'=>$lid]);fplm_audit('license_extended',$lid,'',$actor_name,['days'=>$days]);}}
        elseif($a==='payment'){ fplm_record_payment($lid,$_POST['source']??'other',$_POST['reference']??'',(float)($_POST['amount']??0),$_POST['currency']??'USD',$_POST['paid_at']??null,$_POST['external_id']??'',$_POST['subscription_id']??'',$_POST['notes']??''); }
        elseif($a==='reactivate'){ fplm_transition_license($lid,'active',$actor_name); }
    }
    $rows=$wpdb->get_results("SELECT * FROM {$t['licenses']} ORDER BY id DESC LIMIT 200"); fplm_admin_header('Licensing Center');
    echo '<div class="postbox" style="padding:18px;max-width:1100px"><h2>Create manual / complimentary license</h2><form method="post" style="display:grid;grid-template-columns:repeat(3,1fr);gap:10px">'.wp_nonce_field('fplm_central','_wpnonce',true,false).'<input type="hidden" name="fplm_create_license" value="1"><input name="customer_name" placeholder="Customer name" required><input type="email" name="customer_email" placeholder="Customer email"><select name="plan">'; foreach(fplm_plans() as $k=>$p)echo '<option value="'.esc_attr($k).'">'.esc_html($p['name']).'</option>'; echo '</select><select name="duration"><option value="plan">Plan default</option><option value="30">30 days</option><option value="90">90 days</option><option value="365">365 days</option><option value="lifetime">Lifetime</option><option value="custom">Custom expiry</option></select><input type="datetime-local" name="custom_expiry"><input type="number" min="1" name="max_activations" placeholder="Activation limit"><input type="number" min="0" step="0.01" name="amount" placeholder="Amount"><input name="currency" maxlength="3" value="USD" placeholder="Currency"><select name="source"><option value="manual">Manual</option><option value="bank_transfer">Bank transfer</option><option value="cash">Cash</option><option value="jazzcash">JazzCash</option><option value="easypaisa">Easypaisa</option><option value="invoice">Invoice</option><option value="complimentary">Complimentary</option><option value="other">Other</option></select><input name="payment_reference" placeholder="Payment reference"><input name="external_id" placeholder="External transaction ID"><input name="notes" placeholder="Notes"><label><input type="checkbox" name="payment_received" value="1"> Payment received now</label><button class="button button-primary">Create License</button></form></div><div class="postbox" style="padding:18px"><h2>Central lifecycle controls</h2><p class="description">All manual lifecycle actions are logged. Lifetime licenses use a NULL expiry.</p><table class="widefat striped"><thead><tr><th>Customer</th><th>Key</th><th>Plan</th><th>Status</th><th>Expiry</th><th>Actions</th></tr></thead><tbody>';
    foreach((array)$rows as $r){ echo '<tr><td>'.esc_html($r->customer_name?:$r->customer_email).'<br><small>'.esc_html($r->customer_email).'</small></td><td><code>••••-'.esc_html($r->license_last4).'</code></td><td>'.esc_html($r->plan).'</td><td>'.esc_html($r->status).'</td><td>'.esc_html($r->expires_at?:'Lifetime').'</td><td><form method="post" style="display:inline-flex;gap:5px;align-items:center;flex-wrap:wrap">'.wp_nonce_field('fplm_central','_wpnonce',true,false).'<input type="hidden" name="license_id" value="'.(int)$r->id.'"><input type="hidden" name="fplm_central_action" value="status"><select name="status"><option>active</option><option>cancelled</option><option>grace_period</option><option>suspended</option><option>revoked</option><option>expired</option></select><button class="button">Apply</button></form> <form method="post" style="display:inline-flex;gap:5px;align-items:center">'.wp_nonce_field('fplm_central','_wpnonce',true,false).'<input type="hidden" name="license_id" value="'.(int)$r->id.'"><input type="hidden" name="fplm_central_action" value="extend"><input name="days" type="number" min="1" value="365" style="width:80px"><button class="button">Extend</button></form></td></tr>'; }
    echo '</tbody></table></div></div>';
}
function fplm_installations_admin_page(){ global $wpdb; fplm_ensure_schema(); $t=fplm_tables(); $q=sanitize_text_field($_GET['s']??''); $sql="SELECT i.*,l.plan,l.customer_email FROM {$t['installations']} i LEFT JOIN {$t['licenses']} l ON l.id=i.license_id"; if($q)$sql.=$wpdb->prepare(" WHERE i.domain LIKE %s OR i.site_url LIKE %s OR i.site_name LIKE %s OR i.admin_email LIKE %s OR i.installation_id LIKE %s",'%'.$wpdb->esc_like($q).'%','%'.$wpdb->esc_like($q).'%','%'.$wpdb->esc_like($q).'%','%'.$wpdb->esc_like($q).'%','%'.$wpdb->esc_like($q).'%'); $sql.=' ORDER BY i.last_seen DESC LIMIT 300'; $rows=$wpdb->get_results($sql); fplm_admin_header('Installations'); echo '<form method="get" style="margin:12px 0"><input type="hidden" name="page" value="fplm-installations"><input name="s" value="'.esc_attr($q).'" placeholder="Site, email, installation ID, domain" class="regular-text"><button class="button">Search</button></form><table class="widefat striped"><thead><tr><th>Installation</th><th>Site</th><th>Customer</th><th>License</th><th>Status</th><th>Versions</th><th>Environment</th><th>First seen</th><th>Last seen</th></tr></thead><tbody>'; foreach((array)$rows as $r) echo '<tr><td><code>'.esc_html($r->installation_id).'</code></td><td>'.esc_html($r->domain).'<br>'.esc_html($r->site_name).'<br><small>'.esc_html($r->admin_email).'</small></td><td>'.esc_html($r->customer_email).'</td><td>'.esc_html($r->plan).'</td><td>'.esc_html($r->status).'</td><td>FP '.esc_html($r->plugin_version).' / WP '.esc_html($r->wp_version).' / PHP '.esc_html($r->php_version).'</td><td>'.esc_html($r->environment??'production').'</td><td>'.esc_html($r->first_seen).'</td><td>'.esc_html($r->last_seen).'</td></tr>'; if(!$rows)echo '<tr><td colspan="9">No installations found.</td></tr>'; echo '</tbody></table></div>'; }
function fplm_payments_admin_page(){ global $wpdb; fplm_ensure_schema(); $t=fplm_tables(); $rows=$wpdb->get_results("SELECT p.*,l.customer_email,l.plan FROM {$t['payments']} p LEFT JOIN {$t['licenses']} l ON l.id=p.license_id ORDER BY p.id DESC LIMIT 300"); fplm_admin_header('Payments'); echo '<table class="widefat striped"><thead><tr><th>License</th><th>Source</th><th>Reference</th><th>Amount</th><th>Paid</th><th>External</th><th>Notes</th></tr></thead><tbody>'; foreach((array)$rows as $r)echo '<tr><td>'.esc_html($r->customer_email).' · '.esc_html($r->plan).'</td><td>'.esc_html($r->source).'</td><td>'.esc_html($r->reference).'</td><td>'.esc_html($r->currency.' '.number_format((float)$r->amount,2)).'</td><td>'.esc_html($r->paid_at).'</td><td>'.esc_html($r->external_transaction_id).'</td><td>'.esc_html($r->notes).'</td></tr>'; if(!$rows)echo '<tr><td colspan="7">No payment records yet.</td></tr>'; echo '</tbody></table></div>'; }
function fplm_audit_admin_page(){ global $wpdb; fplm_ensure_schema(); $t=fplm_tables(); $rows=$wpdb->get_results("SELECT * FROM {$t['audit']} ORDER BY id DESC LIMIT 500"); fplm_admin_header('Audit Log'); echo '<table class="widefat striped"><thead><tr><th>Time UTC</th><th>Event</th><th>License</th><th>Installation</th><th>Actor</th><th>Metadata</th></tr></thead><tbody>'; foreach((array)$rows as $r)echo '<tr><td>'.esc_html($r->created_at).'</td><td>'.esc_html($r->event).'</td><td>'.(int)$r->license_id.'</td><td><code>'.esc_html($r->installation_id).'</code></td><td>'.esc_html($r->actor).'</td><td><code>'.esc_html($r->metadata).'</code></td></tr>'; if(!$rows)echo '<tr><td colspan="6">No audit events yet.</td></tr>'; echo '</tbody></table></div>'; }

// Payment failure/recovery lifecycle handlers.
function fplm_handle_payment_failed($invoice) {
    global $wpdb; $t=fplm_tables(); $sub=sanitize_text_field($invoice['subscription']??''); if(!$sub)return;
    $row=$wpdb->get_row($wpdb->prepare("SELECT * FROM {$t['licenses']} WHERE stripe_subscription_id=%s",$sub)); if(!$row)return;
    $hours=max(1,absint(get_option('fplm_payment_failure_grace_hours',72)));
    $grace=max(time()+$hours*HOUR_IN_SECONDS, $row->expires_at?strtotime($row->expires_at):0);
    $wpdb->update($t['licenses'],['status'=>'grace_period','expires_at'=>gmdate('Y-m-d H:i:s',$grace),'updated_at'=>fplm_utc_now()],['id'=>$row->id]);
    fplm_audit('payment_failed',$row->id,'','stripe',['subscription_id'=>$sub,'grace_until'=>gmdate('Y-m-d H:i:s',$grace)]);
}
