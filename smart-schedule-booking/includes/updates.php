<?php
if (!defined('ABSPATH')) exit;

function ssb_update_server_url(){ return untrailingslashit(SSB_LICENSE_SERVER_URL); }
function ssb_update_check_remote(){
    if (!function_exists('ssb_license_key') || !ssb_license_key()) return false;
    $base=trailingslashit(ssb_update_server_url());
    $endpoints=[$base.'wp-json/formpilot/v1/update/check',add_query_arg('rest_route','/formpilot/v1/update/check',$base)];
    $payload=array_merge(['license_key'=>ssb_license_key()],function_exists('ssb_license_telemetry')?ssb_license_telemetry():['site_url'=>home_url('/'),'plugin_version'=>SSB_VERSION,'wp_version'=>get_bloginfo('version')]);
    foreach(array_unique($endpoints) as $endpoint){$body=wp_json_encode($payload);$headers=function_exists('ssb_license_request_headers')?ssb_license_request_headers('/formpilot/v1/update/check',$body):['Accept'=>'application/json','Content-Type'=>'application/json'];$res=wp_remote_post($endpoint,['timeout'=>20,'sslverify'=>true,'headers'=>$headers,'body'=>$body]);if(is_wp_error($res))continue;$code=(int)wp_remote_retrieve_response_code($res);$data=json_decode(wp_remote_retrieve_body($res),true);if($code<200||$code>=300||!is_array($data)||empty($data['valid'])||empty($data['update']))continue; if(!ssb_license_verify_signature(['license'=>$data['manifest']??[],'signature'=>$data['manifest_signature']??'']))continue; if(($data['manifest']['product']??'')!=='formpilot-pro')continue; return $data;} return false;
}
function ssb_update_transient($transient){
    if(!is_object($transient)||empty($transient->checked)) return $transient;
    $key='ssb_update_cache_'.md5(home_url('/').SSB_VERSION);
    $data=get_transient($key); if(false===$data){$data=ssb_update_check_remote();set_transient($key,$data?:['none'=>true],6*HOUR_IN_SECONDS);}
    if(is_array($data)&&!empty($data['version'])&&version_compare($data['version'],SSB_VERSION,'>')){
        $plugin=plugin_basename(SSB_PLUGIN_FILE);
        $transient->response[$plugin]=(object)['id'=>'formpilot-pro','slug'=>'smart-schedule-booking','plugin'=>$plugin,'new_version'=>sanitize_text_field($data['version']),'url'=>SSB_LICENSE_SERVER_URL,'package'=>esc_url_raw($data['package']),'icons'=>[],'tested'=>sanitize_text_field($data['tested']??'')];
    }
    return $transient;
}
add_filter('pre_set_site_transient_update_plugins','ssb_update_transient');
function ssb_update_plugins_api($result,$action,$args){
    if($action!=='plugin_information'||empty($args->slug)||$args->slug!=='smart-schedule-booking')return $result;
    $data=ssb_update_check_remote(); if(!$data)return $result;
    return (object)['name'=>'FormPilot Pro','slug'=>'smart-schedule-booking','version'=>sanitize_text_field($data['version']),'author'=>'FormPilot','homepage'=>SSB_LICENSE_SERVER_URL,'sections'=>['description'=>'Commercial FormPilot Pro release.','changelog'=>wp_kses_post($data['changelog']??'')],'download_link'=>esc_url_raw($data['package']),'tested'=>sanitize_text_field($data['tested']??'')];
}
add_filter('plugins_api_result','ssb_update_plugins_api',10,3);
add_action('upgrader_process_complete',function($upgrader,$hook_extra){ if(($hook_extra['action']??'')==='update'&&($hook_extra['type']??'')==='plugin'){delete_transient('ssb_update_cache_'.md5(home_url('/').SSB_VERSION));}},10,2);


add_action('admin_notices', function(){
    if (!current_user_can('update_plugins') || !function_exists('ssb_license_key') || !ssb_license_key()) return;
    $key='ssb_update_cache_'.md5(home_url('/').SSB_VERSION);
    $data=get_transient($key);
    if ($data===false) { $data=ssb_update_check_remote(); set_transient($key,$data?:['none'=>true],6*HOUR_IN_SECONDS); }
    if (!is_array($data) || empty($data['version']) || version_compare($data['version'],SSB_VERSION,'<=')) return;
    echo '<div class="notice notice-info is-dismissible"><p><strong>FormPilot Pro update available:</strong> version '.esc_html($data['version']).' is ready. <a href="'.esc_url(admin_url('update-core.php')).'">Review and update now</a>.</p></div>';
});
add_action('admin_bar_menu', function($bar){
    if (!current_user_can('update_plugins') || !function_exists('ssb_license_key') || !ssb_license_key()) return;
    $key='ssb_update_cache_'.md5(home_url('/').SSB_VERSION); $data=get_transient($key);
    if (!is_array($data) || empty($data['version']) || version_compare($data['version'],SSB_VERSION,'<=')) return;
    $bar->add_node(['id'=>'formpilot-update','title'=>'FormPilot update available','href'=>admin_url('update-core.php')]);
},100);
