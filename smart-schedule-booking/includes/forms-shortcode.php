<?php
if ( ! defined( 'ABSPATH' ) ) exit;

add_shortcode( 'ssb_form', 'ssb_render_custom_form' );

function ssb_render_custom_form( $atts ) {
    if ( function_exists('ssb_license_can') && ! ssb_license_can('forms') ) return '<div class="ssb-license-required">This form is temporarily unavailable. Please activate a valid FormPilot Pro license.</div>';
    $atts = shortcode_atts(['id' => 0], $atts, 'ssb_form');
    $id   = intval($atts['id']);
    if ( ! $id ) return '<p style="color:red;">ssb_form: missing id attribute. Usage: [ssb_form id="1"]</p>';

    $form = ssb_get_form($id);
    if ( ! $form || $form->status !== 'active' ) return '<p style="color:red;">Form not found or inactive.</p>';

    $s        = $form->settings;
    $primary  = ! empty($s['primary_color']) ? $s['primary_color'] : get_option('ssb_primary_color','#667eea');
    $secondary = get_option('ssb_secondary_color','#764ba2');
    $radius   = get_option('ssb_border_radius','12');
    $btn_text = get_option('ssb_button_text_color','#ffffff');
    $font     = get_option('ssb_font_family','inherit');
    $style    = isset($s['style']) && is_array($s['style']) ? $s['style'] : [];
    $sd = [
      'form_bg'=>'#ffffff','form_bg_transparent'=>false,'form_border_enabled'=>true,'form_border'=>'#edf0f3','form_radius'=>24,'form_padding'=>40,'field_gap'=>18,
      'label_color'=>'#6b7280','label_size'=>12,'heading_color'=>'#667eea','input_bg'=>'#f9fafb','input_text'=>'#111827',
      'input_border'=>'#e5e7eb','input_focus_border'=>'#667eea','input_focus_ring'=>'#dbeafe','input_error_border'=>'#dc2626','input_error_bg'=>'#fef2f2','input_placeholder'=>'#94a3b8','input_border_width'=>1,'input_radius'=>12,'input_padding'=>14,'input_font_size'=>15,
      'button_bg'=>'#667eea','button_text'=>'#ffffff','button_hover_bg'=>'#4f46e5','button_radius'=>999,
      'button_padding_y'=>15,'button_padding_x'=>34,'button_font_size'=>15,'button_alignment'=>'left','button_full_width'=>false,
      'ms_next_bg'=>'#0f172a','ms_next_text'=>'#ffffff','ms_next_hover_bg'=>'#1e293b','ms_next_border'=>'#0f172a','ms_next_border_width'=>0,'ms_next_radius'=>8,'ms_next_padding_y'=>10,'ms_next_padding_x'=>22,
      'ms_prev_bg'=>'#f1f5f9','ms_prev_text'=>'#475569','ms_prev_hover_bg'=>'#e2e8f0','ms_prev_border'=>'#e2e8f0','ms_prev_border_width'=>1,'ms_prev_radius'=>8,'ms_prev_padding_y'=>10,'ms_prev_padding_x'=>22,'ms_nav_full_width_mobile'=>false,
      'ms_progress_color'=>'#667eea','ms_track_color'=>'#e9edf5','ms_connector_color'=>'#d9deea','ms_active_color'=>'#667eea','ms_completed_color'=>'#667eea','ms_inactive_color'=>'#eef2f7','ms_text_color'=>'#64748b',
      'ms_shape'=>'circle','ms_size'=>30,'ms_bar_height'=>8,'ms_spacing'=>10,'ms_show_percent'=>true,'ms_show_step_label'=>true,'ms_show_titles'=>true,'ms_clickable'=>true,'ms_animation'=>'slide'
    ];
    $style = array_merge($sd, $style);
    $integrations = isset($s['integrations']) && is_array($s['integrations']) ? $s['integrations'] : [];
    $stripe_cfg = isset($integrations['stripe']) && is_array($integrations['stripe']) ? $integrations['stripe'] : [];
    $stripe_enabled = !empty($stripe_cfg['enabled']);
    $stripe_amount = floatval($stripe_cfg['amount'] ?? 0);
    if ($stripe_enabled) {
        wp_enqueue_script('stripe-js', 'https://js.stripe.com/v3/', [], null, true);
    }
    // Load only the spam provider script actually used by this form.
    $has_turnstile = false; $has_recaptcha = false;
    foreach ($form->fields as $sf) {
        if (($sf['type'] ?? '') === 'spam_protection') {
            if (($sf['spam_mode'] ?? '') === 'turnstile' && get_option('ssb_turnstile_site_key','')) $has_turnstile = true;
            if (($sf['spam_mode'] ?? '') === 'recaptcha' && get_option('ssb_recaptcha_site_key','')) $has_recaptcha = true;
        }
    }
    if ($has_turnstile) wp_enqueue_script('ssb-turnstile', 'https://challenges.cloudflare.com/turnstile/v0/api.js', [], null, true);
    if ($has_recaptcha) wp_enqueue_script('ssb-recaptcha', 'https://www.google.com/recaptcha/api.js', [], null, true);
    $GLOBALS['ssb_render_form_settings'] = $s;
    $uid      = 'ssbf_' . $id . '_' . uniqid();

    ob_start();
    ?>
    
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<style>
#<?php echo $uid; ?>{
  --sfb-primary: <?php echo esc_attr($primary); ?>;
  --sfb-heading-color: <?php echo esc_attr($style['heading_color'] ?? $primary); ?>;
  --sfb-secondary: <?php echo esc_attr($secondary); ?>;
  --sfb-radius: <?php echo esc_attr($radius); ?>px;
  --sfb-text: #111827;
  --sfb-muted: #6b7280;
  --sfb-border: #e5e7eb;
  --sfb-bg: <?php echo esc_attr($style['input_bg']); ?>;
  --sfb-input-text: <?php echo esc_attr($style['input_text']); ?>;
  --sfb-input-border: <?php echo esc_attr($style['input_border']); ?>;
  --sfb-input-focus-border: <?php echo esc_attr($style['input_focus_border'] ?? '#667eea'); ?>;
  --sfb-input-focus-ring: <?php echo esc_attr($style['input_focus_ring'] ?? '#dbeafe'); ?>;
  --sfb-input-error-border: <?php echo esc_attr($style['input_error_border'] ?? '#dc2626'); ?>;
  --sfb-input-error-bg: <?php echo esc_attr($style['input_error_bg'] ?? '#fef2f2'); ?>;
  --sfb-input-placeholder: <?php echo esc_attr($style['input_placeholder'] ?? '#94a3b8'); ?>;
  --sfb-input-border-width: <?php echo intval($style['input_border_width']); ?>px;
  --sfb-input-radius: <?php echo intval($style['input_radius']); ?>px;
  --sfb-input-padding: <?php echo intval($style['input_padding']); ?>px;
  --sfb-input-font-size: <?php echo intval($style['input_font_size']); ?>px;
  --sfb-error: #dc2626;
  font-family: 'Plus Jakarta Sans', <?php echo esc_attr($font); ?>, -apple-system, sans-serif;
  max-width: 820px;
  margin: 0 auto;
}
#<?php echo $uid; ?> .sfb-form-wrap{
  position:relative;
  overflow:visible;
  background:<?php echo !empty($style['form_bg_transparent']) ? 'transparent' : esc_attr($style['form_bg']); ?>;
  border:<?php echo !empty($style['form_border_enabled']) ? '1px solid '.esc_attr($style['form_border']) : 'none'; ?>;
  border-radius:<?php echo intval($style['form_radius']); ?>px;
  padding:<?php echo intval($style['form_padding']); ?>px;
  box-shadow:0 18px 55px rgba(15,23,42,.08);
  transition:box-shadow .25s ease, border-color .25s ease;
}
#<?php echo $uid; ?> .sfb-title{
  font-size:32px;
  line-height:1.2;
  font-weight:800;
  margin:0 0 12px;
  color:var(--sfb-text);
}
#<?php echo $uid; ?> .sfb-desc{
  color:var(--sfb-muted);
  margin:0 0 28px;
  line-height:1.7;
}
#<?php echo $uid; ?> .sfb-row{
  display:grid;
  grid-template-columns:repeat(2,minmax(0,1fr));
  gap:<?php echo intval($style['field_gap']); ?>px;
  margin-bottom:<?php echo intval($style['field_gap']); ?>px;
}
#<?php echo $uid; ?> .sfb-field.full{grid-column:1/-1;min-width:0}
#<?php echo $uid; ?> .sfb-field:not(.full){grid-column:auto;min-width:0}
#<?php echo $uid; ?> .sfb-label{
  display:block;
  margin-bottom:8px;
  font-size:<?php echo intval($style['label_size']); ?>px;
  font-weight:700;
  text-transform:uppercase;
  letter-spacing:.08em;
  color:<?php echo esc_attr($style['label_color']); ?>;
}
#<?php echo $uid; ?> .sfb-input,
#<?php echo $uid; ?> .sfb-select,
#<?php echo $uid; ?> .sfb-textarea{
  width:100%;
  box-sizing:border-box;
  border:<?php echo intval($style['input_border_width']); ?>px solid var(--sfb-input-border);
  background:var(--sfb-bg);
  color:var(--sfb-input-text);
  border-radius:var(--sfb-input-radius);
  padding:var(--sfb-input-padding);
  font-size:var(--sfb-input-font-size);
  outline:none;
  transition:all .2s ease;
}
#<?php echo $uid; ?> .sfb-input:focus,
#<?php echo $uid; ?> .sfb-select:focus,
#<?php echo $uid; ?> .sfb-textarea:focus{
  border-color:var(--sfb-input-focus-border);
  box-shadow:0 0 0 4px var(--sfb-input-focus-ring);
}
#<?php echo $uid; ?> .sfb-textarea{
  min-height:140px;
  resize:vertical;
}
#<?php echo $uid; ?> .sfb-heading{
  font-size:14px;
  font-weight:700;
  color:var(--sfb-heading-color);
  margin:24px 0 14px;
  display:flex;
  align-items:center;
  gap:10px;
}
#<?php echo $uid; ?> .sfb-heading:before{
  content:"";
  width:4px;height:16px;
  border-radius:99px;
  background:var(--sfb-heading-color);
}
#<?php echo $uid; ?> .sfb-divider{
  border:0;
  border-top:1px solid #eef2f7;
  margin:28px 0;
}
#<?php echo $uid; ?> .sfb-option-label{
  display:flex;
  align-items:center;
  gap:10px;
  padding:12px 14px;
  border:1.5px solid var(--sfb-border);
  border-radius:12px;
  background:var(--sfb-bg);
  cursor:pointer;
  transition:all .2s ease;
}
#<?php echo $uid; ?> .sfb-option-label:hover,
#<?php echo $uid; ?> .sfb-option-label.is-checked{
  border-color:var(--sfb-primary);
  background:#ffffff;
  box-shadow:0 8px 20px rgba(15,23,42,.05);
}
#<?php echo $uid; ?> .sfb-radio-group,
#<?php echo $uid; ?> .sfb-checkbox-group{
  display:grid;
  grid-template-columns:repeat(2,minmax(0,1fr));
  gap:12px;
}
#<?php echo $uid; ?> .sfb-submit{
  display:inline-flex;
  align-items:center;
  justify-content:center;
  gap:10px;
  border:none;
  border-radius:<?php echo intval($style['button_radius']); ?>px;
  padding:<?php echo intval($style['button_padding_y']); ?>px <?php echo intval($style['button_padding_x']); ?>px;
  font-weight:700;
  font-size:<?php echo intval($style['button_font_size']); ?>px;
  color:<?php echo esc_attr($style['button_text']); ?>;
  background:<?php echo esc_attr($style['button_bg']); ?>;
  box-shadow:0 12px 30px rgba(15,23,42,.12);
  cursor:pointer;
  transition:all .2s ease;
}
#<?php echo $uid; ?> .sfb-submit:hover{
  background:<?php echo esc_attr($style['button_hover_bg']); ?>;
  transform:translateY(-2px);
  box-shadow:0 18px 36px rgba(15,23,42,.16);
}
#<?php echo $uid; ?> .sfb-form-steps.sfb-multistep-enabled{position:relative}
#<?php echo $uid; ?> .sfb-step-pane{display:block}
#<?php echo $uid; ?> .sfb-step-pane[hidden]{display:none!important}
#<?php echo $uid; ?> .sfb-step-pane.sfb-step-anim-slide{animation:sfbStepIn .22s ease}
#<?php echo $uid; ?> .sfb-step-pane.sfb-step-anim-fade{animation:sfbStepFade .18s ease}
#<?php echo $uid; ?> .sfb-step-actions{display:flex;align-items:center;justify-content:space-between;gap:12px;margin-top:24px}
#<?php echo $uid; ?> .sfb-step-btn{appearance:none;font-weight:700;cursor:pointer;transition:all .2s ease;font-size:<?php echo intval($style['button_font_size']); ?>px;line-height:1.2}
#<?php echo $uid; ?> .sfb-btn-next{background:<?php echo esc_attr($style['ms_next_bg']); ?>;color:<?php echo esc_attr($style['ms_next_text']); ?>;border:<?php echo intval($style['ms_next_border_width']); ?>px solid <?php echo esc_attr($style['ms_next_border']); ?>;border-radius:<?php echo intval($style['ms_next_radius']); ?>px;padding:<?php echo intval($style['ms_next_padding_y']); ?>px <?php echo intval($style['ms_next_padding_x']); ?>px}
#<?php echo $uid; ?> .sfb-btn-next:hover{background:<?php echo esc_attr($style['ms_next_hover_bg']); ?>;color:<?php echo esc_attr($style['ms_next_text']); ?>;transform:translateY(-1px)}
#<?php echo $uid; ?> .sfb-btn-prev{background:<?php echo esc_attr($style['ms_prev_bg']); ?>;color:<?php echo esc_attr($style['ms_prev_text']); ?>;border:<?php echo intval($style['ms_prev_border_width']); ?>px solid <?php echo esc_attr($style['ms_prev_border']); ?>;border-radius:<?php echo intval($style['ms_prev_radius']); ?>px;padding:<?php echo intval($style['ms_prev_padding_y']); ?>px <?php echo intval($style['ms_prev_padding_x']); ?>px}
#<?php echo $uid; ?> .sfb-btn-prev:hover{background:<?php echo esc_attr($style['ms_prev_hover_bg']); ?>;color:<?php echo esc_attr($style['ms_prev_text']); ?>;transform:translateY(-1px)}
#<?php echo $uid; ?> .sfb-step-actions .sfb-step-btn.sfb-btn-next,#<?php echo $uid; ?> .sfb-step-actions .sfb-step-submit{margin-left:auto}
#<?php echo $uid; ?> .sfb-step-nodes-wrap{margin:0 0 32px;width:100%}
#<?php echo $uid; ?> .sfb-step-nodes-container{display:flex;align-items:center;justify-content:space-between;width:100%;position:relative;min-width:0}
#<?php echo $uid; ?> .sfb-step-tab{display:flex;align-items:center;gap:10px;position:relative;z-index:2;min-width:0;color:var(--sfb-ms-text);font-size:13px;font-weight:600;cursor:default;transition:color .3s ease}
#<?php echo $uid; ?> .sfb-step-number{width:var(--sfb-ms-size);height:var(--sfb-ms-size);display:inline-flex;align-items:center;justify-content:center;border-radius:var(--sfb-ms-radius);background:var(--sfb-ms-inactive);color:var(--sfb-ms-text);border:1px solid transparent;flex:0 0 var(--sfb-ms-size);font-size:14px;font-weight:600;transition:all .3s ease}
#<?php echo $uid; ?> .sfb-step-title{line-height:1.3;overflow-wrap:anywhere}
#<?php echo $uid; ?> .sfb-step-tab.active{color:var(--sfb-ms-active)}
#<?php echo $uid; ?> .sfb-step-tab.active .sfb-step-number{background:var(--sfb-ms-active);color:#fff;border-color:var(--sfb-ms-active)}
#<?php echo $uid; ?> .sfb-step-tab.completed{color:var(--sfb-ms-completed)}
#<?php echo $uid; ?> .sfb-step-tab.completed .sfb-step-number{background:var(--sfb-ms-completed);color:#fff;border-color:var(--sfb-ms-completed)}
#<?php echo $uid; ?> .sfb-step-tab.completed.sfb-step-clickable{cursor:pointer}
#<?php echo $uid; ?> .sfb-node-connector{flex:1 1 36px;min-width:18px;height:2px;background:var(--sfb-ms-connector);margin:0 var(--sfb-ms-spacing);transition:background-color .3s ease}
#<?php echo $uid; ?> .sfb-node-connector.filled{background:var(--sfb-ms-progress)}
#<?php echo $uid; ?> .sfb-bottom-progress{width:100%;margin-top:20px}
#<?php echo $uid; ?> .sfb-bottom-progress.sfb-progress-both{display:flex;flex-direction:column;gap:6px}
#<?php echo $uid; ?> .sfb-bottom-progress .sfb-progress-meta{display:flex;justify-content:space-between;align-items:center;gap:12px;font-size:12px;font-weight:600;color:var(--sfb-ms-text);margin:0;background:transparent!important;padding:0!important;border:0!important;box-shadow:none!important}
#<?php echo $uid; ?> .sfb-progress-track{position:relative;width:100%;box-sizing:border-box;background:var(--sfb-ms-track);overflow:hidden}
#<?php echo $uid; ?> .sfb-progress-both .sfb-progress-track{height:var(--sfb-ms-bar-height);border-radius:999px}
#<?php echo $uid; ?> .sfb-progress-bar{display:block;height:100%;width:0;max-width:100%;min-width:0!important;box-sizing:border-box;background:var(--sfb-ms-progress);transition:width .4s ease}
#<?php echo $uid; ?> .sfb-progress-both .sfb-progress-bar{border-radius:999px}
#<?php echo $uid; ?> .sfb-progress-bar-only .sfb-progress-track{height:max(34px,var(--sfb-ms-bar-height));border-radius:8px;display:flex;align-items:center}
#<?php echo $uid; ?> .sfb-progress-bar-only .sfb-progress-bar{border-radius:8px 0 0 8px}
#<?php echo $uid; ?> .sfb-progress-overlay{position:absolute;inset:0;width:100%;padding:0 14px;display:flex;justify-content:space-between;align-items:center;gap:12px;font-size:13px;font-weight:600;color:var(--sfb-ms-text);pointer-events:none;z-index:2}
#<?php echo $uid; ?> .sfb-step-actions{border-top:1px solid #f1f5f9;padding-top:24px;margin-top:36px}
#<?php echo $uid; ?> .sfb-progress-none{display:none!important}
@keyframes sfbStepIn{from{opacity:.4;transform:translateX(6px)}to{opacity:1;transform:none}}
@keyframes sfbStepFade{from{opacity:0}to{opacity:1}}
@media(max-width:600px){#<?php echo $uid; ?> .sfb-step-actions{flex-wrap:wrap}#<?php echo $uid; ?> .sfb-step-actions .sfb-step-btn,#<?php echo $uid; ?> .sfb-step-actions .sfb-step-submit{margin-left:0;<?php echo !empty($style['ms_nav_full_width_mobile']) ? 'flex:1 1 100%;width:100%;' : 'flex:1;'; ?>}#<?php echo $uid; ?> .sfb-step-nodes-container{overflow-x:auto;justify-content:flex-start;padding-bottom:4px;scrollbar-width:thin}#<?php echo $uid; ?> .sfb-step-tab{flex:0 0 auto}#<?php echo $uid; ?> .sfb-node-connector{flex:0 0 28px;min-width:28px}}
<?php $resp = isset($style['responsive']) && is_array($style['responsive']) ? $style['responsive'] : []; $rt = isset($resp['tablet']) && is_array($resp['tablet']) ? $resp['tablet'] : []; $rm = isset($resp['mobile']) && is_array($resp['mobile']) ? $resp['mobile'] : []; ?>
@media (max-width:1024px){
  #<?php echo $uid; ?> .sfb-form-wrap{padding:<?php echo intval($rt['form_padding'] ?? 28); ?>px;border-radius:<?php echo intval($rt['form_radius'] ?? 20); ?>px}
  #<?php echo $uid; ?> .sfb-row{gap:<?php echo intval($rt['field_gap'] ?? 14); ?>px;margin-bottom:<?php echo intval($rt['field_gap'] ?? 14); ?>px}
  #<?php echo $uid; ?> .sfb-title{font-size:<?php echo intval($rt['title_size'] ?? 28); ?>px}
  #<?php echo $uid; ?> .sfb-desc{font-size:<?php echo intval($rt['description_size'] ?? 15); ?>px}
  #<?php echo $uid; ?> .sfb-label{font-size:<?php echo intval($rt['label_size'] ?? 12); ?>px}
  #<?php echo $uid; ?> .sfb-input,#<?php echo $uid; ?> .sfb-select,#<?php echo $uid; ?> .sfb-textarea{font-size:<?php echo intval($rt['input_font_size'] ?? 15); ?>px;padding:<?php echo intval($rt['input_padding'] ?? 13); ?>px}
  #<?php echo $uid; ?> .sfb-submit{font-size:<?php echo intval($rt['button_font_size'] ?? 15); ?>px;padding:<?php echo intval($rt['button_padding_y'] ?? 14); ?>px <?php echo intval($rt['button_padding_x'] ?? 28); ?>px}
  #<?php echo $uid; ?> .sfb-step-btn{font-size:<?php echo intval($rt['button_font_size'] ?? 15); ?>px}
  #<?php echo $uid; ?> .sfb-step-number{width:<?php echo intval($rt['ms_size'] ?? 30); ?>px;height:<?php echo intval($rt['ms_size'] ?? 30); ?>px;flex-basis:<?php echo intval($rt['ms_size'] ?? 30); ?>px}
  #<?php echo $uid; ?> .sfb-progress-both .sfb-progress-track{height:<?php echo intval($rt['ms_bar_height'] ?? 8); ?>px}
}
@media (max-width:767px){
  #<?php echo $uid; ?> .sfb-form-wrap{padding:<?php echo intval($rm['form_padding'] ?? 20); ?>px;border-radius:<?php echo intval($rm['form_radius'] ?? 16); ?>px}
  #<?php echo $uid; ?> .sfb-row,#<?php echo $uid; ?> .sfb-radio-group,#<?php echo $uid; ?> .sfb-checkbox-group{grid-template-columns:1fr;gap:<?php echo intval($rm['field_gap'] ?? 12); ?>px}
  #<?php echo $uid; ?> .sfb-row{margin-bottom:<?php echo intval($rm['field_gap'] ?? 12); ?>px}
  #<?php echo $uid; ?> .sfb-title{font-size:<?php echo intval($rm['title_size'] ?? 24); ?>px}
  #<?php echo $uid; ?> .sfb-desc{font-size:<?php echo intval($rm['description_size'] ?? 14); ?>px;margin-bottom:20px}
  #<?php echo $uid; ?> .sfb-label{font-size:<?php echo intval($rm['label_size'] ?? 12); ?>px}
  #<?php echo $uid; ?> .sfb-input,#<?php echo $uid; ?> .sfb-select,#<?php echo $uid; ?> .sfb-textarea{font-size:<?php echo intval($rm['input_font_size'] ?? 16); ?>px;padding:<?php echo intval($rm['input_padding'] ?? 12); ?>px}
  #<?php echo $uid; ?> .sfb-submit{font-size:<?php echo intval($rm['button_font_size'] ?? 14); ?>px;padding:<?php echo intval($rm['button_padding_y'] ?? 13); ?>px <?php echo intval($rm['button_padding_x'] ?? 20); ?>px}
  #<?php echo $uid; ?> .sfb-step-btn{font-size:<?php echo intval($rm['button_font_size'] ?? 14); ?>px}
  #<?php echo $uid; ?> .sfb-step-actions{gap:8px}
  #<?php echo $uid; ?> .sfb-step-actions .sfb-step-btn,#<?php echo $uid; ?> .sfb-step-actions .sfb-step-submit{min-width:0}
  #<?php echo $uid; ?> .sfb-step-number{width:<?php echo intval($rm['ms_size'] ?? 28); ?>px;height:<?php echo intval($rm['ms_size'] ?? 28); ?>px;flex-basis:<?php echo intval($rm['ms_size'] ?? 28); ?>px}
  #<?php echo $uid; ?> .sfb-progress-both .sfb-progress-track{height:<?php echo intval($rm['ms_bar_height'] ?? 7); ?>px}
  #<?php echo $uid; ?> .sfb-progress-meta{font-size:11px}
  #<?php echo $uid; ?> .sfb-step-title{display:<?php echo !empty($rm['ms_show_titles']) ? 'inline' : 'none'; ?>}
}

#<?php echo $uid; ?> .sfb-submit:disabled{opacity:.65;cursor:not-allowed;transform:none}
#<?php echo $uid; ?> .sfb-submit:hover{background:<?php echo esc_attr($style['button_hover_bg']); ?>;}
#<?php echo $uid; ?> .sfb-payment-box{padding:16px;border:1px solid var(--sfb-input-border);border-radius:var(--sfb-input-radius);background:var(--sfb-bg);margin:0 0 18px;}
#<?php echo $uid; ?> .sfb-card-element{padding:14px;border:var(--sfb-input-border-width) solid var(--sfb-input-border);border-radius:var(--sfb-input-radius);background:#fff;}
#<?php echo $uid; ?> .sfb-field.full:has(.sfb-submit){display:flex;align-items:center;justify-content:<?php echo ($style['button_alignment'] ?? 'left') === 'center' ? 'center' : (($style['button_alignment'] ?? 'left') === 'right' ? 'flex-end' : 'flex-start'); ?>;}
#<?php echo $uid; ?> .sfb-submit{display:block;<?php echo (($style['button_alignment'] ?? 'left') === 'full' || !empty($style['button_full_width'])) ? 'width:100%;' : 'width:auto;'; ?>margin-left:0;margin-right:0;}
#<?php echo $uid; ?> .sfb-step-actions .sfb-step-submit{<?php echo ($style['button_alignment'] ?? 'left') === 'center' ? 'margin-left:auto!important;margin-right:auto!important;' : (($style['button_alignment'] ?? 'left') === 'right' ? 'margin-left:auto!important;margin-right:0!important;' : (($style['button_alignment'] ?? 'left') === 'full' ? 'margin-left:0!important;margin-right:0!important;' : 'margin-left:0!important;margin-right:auto!important;')); ?><?php echo (($style['button_alignment'] ?? 'left') === 'full' || !empty($style['button_full_width'])) ? 'width:100%;' : 'width:auto;'; ?>}
#<?php echo $uid; ?> .sfb-toggle-ui{width:42px;height:24px;border-radius:999px;background:#cbd5e1;display:inline-flex;align-items:center;padding:2px;box-sizing:border-box}.sfb-toggle input{position:absolute;opacity:0}.sfb-toggle-ui span{width:20px;height:20px;background:#fff;border-radius:50%;display:block;box-shadow:0 1px 3px rgba(0,0,0,.15);transition:transform .18s}.sfb-toggle input:checked + .sfb-toggle-ui{background:var(--sfb-primary)}.sfb-toggle input:checked + .sfb-toggle-ui span{transform:translateX(18px)}.sfb-booking-field .sfb-booking-status{min-height:16px;}
#<?php echo $uid; ?> .sfb-field-error{
  display:none;
  margin-top:6px;
  font-size:12px;
  color:var(--sfb-error);
  font-weight:500;
}
#<?php echo $uid; ?> .sfb-field-error.visible{display:block}
#<?php echo $uid; ?> .sfb-error{
  border-color:var(--sfb-input-error-border)!important;
  background:var(--sfb-input-error-bg)!important;
}
#<?php echo $uid; ?> .sfb-input::placeholder,#<?php echo $uid; ?> .sfb-textarea::placeholder{color:var(--sfb-input-placeholder);opacity:1;}
#<?php echo $uid; ?> .sfb-message{
  padding:16px 18px;
  border-radius:12px;
  margin-bottom:20px;
}
#<?php echo $uid; ?> .sfb-message.success{
  background:#ecfdf5;color:#065f46;border:1px solid #a7f3d0;
}
#<?php echo $uid; ?> .sfb-message.error{
  background:#fef2f2;color:#991b1b;border:1px solid #fecaca;
}
#<?php echo $uid; ?> .sfb-top-error{padding:13px 15px;margin:0 0 18px;border:1px solid #fecaca;border-radius:12px;background:#fef2f2;color:#991b1b;font-size:14px;line-height:1.5;}
#<?php echo $uid; ?> .sfb-toast{position:fixed;right:24px;bottom:24px;z-index:999999;display:flex;align-items:flex-start;gap:11px;width:min(420px,calc(100vw - 32px));padding:14px 16px;border-radius:14px;color:#fff;box-shadow:0 18px 55px rgba(15,23,42,.24);opacity:0;transform:translateY(12px);pointer-events:none;transition:opacity .2s ease,transform .2s ease;}
#<?php echo $uid; ?> .sfb-toast.show{opacity:1;transform:translateY(0);pointer-events:auto;}
#<?php echo $uid; ?> .sfb-toast.success{background:#059669;}
#<?php echo $uid; ?> .sfb-toast.error{background:#dc2626;}
#<?php echo $uid; ?> .sfb-toast-icon{width:24px;height:24px;border-radius:999px;background:rgba(255,255,255,.18);display:flex;align-items:center;justify-content:center;font-weight:800;flex:0 0 24px;}
#<?php echo $uid; ?> .sfb-toast-body{min-width:0;flex:1;}
#<?php echo $uid; ?> .sfb-toast-title{font-weight:800;font-size:13px;margin-bottom:2px;}
#<?php echo $uid; ?> .sfb-toast-message{font-size:12px;line-height:1.5;opacity:.94;overflow-wrap:anywhere;}
#<?php echo $uid; ?> .sfb-toast-close{border:0;background:transparent;color:#fff;opacity:.75;cursor:pointer;font-size:18px;line-height:1;padding:0 2px;}
#<?php echo $uid; ?> .sfb-submit-overlay{position:absolute;inset:0;z-index:50;align-items:center;justify-content:center;flex-direction:column;gap:7px;border-radius:inherit;background:rgba(255,255,255,.78);backdrop-filter:blur(2px);color:#111827;}
#<?php echo $uid; ?> .sfb-submit-overlay span{font-size:12px;color:#6b7280;}
#<?php echo $uid; ?> .sfb-submit-spinner,#<?php echo $uid; ?> .sfb-loader{width:18px;height:18px;border:2px solid rgba(255,255,255,.45);border-top-color:currentColor;border-radius:50%;animation:sfbSpin .7s linear infinite;}
#<?php echo $uid; ?> .sfb-submit-spinner{width:28px;height:28px;border-color:#dbe3ea;border-top-color:var(--sfb-primary);}
@keyframes sfbSpin{to{transform:rotate(360deg)}}
/* ── Timeslot grid buttons ── */
#<?php echo $uid; ?> .sfb-timeslot-grid{
  display:grid;
  grid-template-columns:repeat(auto-fill,minmax(120px,1fr));
  gap:10px;
  margin-top:6px;
}
#<?php echo $uid; ?> .sfb-repeater{border:1px solid var(--sfb-border);border-radius:12px;padding:14px;background:#fff}
#<?php echo $uid; ?> .sfb-repeater-item{border:1px solid #e9edf3;border-radius:10px;padding:14px;margin-bottom:10px;background:#fafbfc;position:relative}
#<?php echo $uid; ?> .sfb-repeater-item-header{display:flex;align-items:center;justify-content:space-between;gap:8px;margin-bottom:10px;font-size:12px;font-weight:700;color:#64748b}
#<?php echo $uid; ?> .sfb-repeater-remove{border:0;background:#fff1f2;color:#be123c;border-radius:7px;padding:6px 9px;font-size:11px;font-weight:700;cursor:pointer}
#<?php echo $uid; ?> .sfb-repeater-add{border:1px solid <?php echo esc_attr($style['button_bg']); ?>;background:transparent;color:<?php echo esc_attr($style['button_bg']); ?>;border-radius:8px;padding:9px 12px;font-size:12px;font-weight:700;cursor:pointer;transition:all .2s ease}
#<?php echo $uid; ?> .sfb-repeater-add:hover{background:<?php echo esc_attr($style['button_bg']); ?>;color:<?php echo esc_attr($style['button_text']); ?>}
#<?php echo $uid; ?> .sfb-timeslot-btn{
  display:block;
  position:relative;
  cursor:pointer;
}
#<?php echo $uid; ?> .sfb-timeslot-btn input{
  position:absolute;opacity:0;width:0;height:0;
}
#<?php echo $uid; ?> .sfb-timeslot-btn span{
  display:block;
  padding:10px 8px;
  border:2px solid var(--sfb-border);
  border-radius:10px;
  text-align:center;
  font-size:13px;
  font-weight:600;
  background:#fff;
  color:var(--sfb-text);
  transition:all .2s ease;
}
#<?php echo $uid; ?> .sfb-timeslot-btn:hover span{
  border-color:var(--sfb-primary);
  background:rgba(99,102,241,.06);
  color:var(--sfb-primary);
}
#<?php echo $uid; ?> .sfb-timeslot-btn.checked span{
  background:var(--sfb-primary);
  border-color:var(--sfb-primary);
  color:#fff;
  box-shadow:0 4px 12px rgba(99,102,241,.3);
}
#<?php echo $uid; ?> .sfb-timeslot-btn.reserved span{
  background:#fff5f5;
  border-color:#ef4444;
  border-width:2px;
  color:#b91c1c;
  cursor:not-allowed;
  font-weight:700;
}
#<?php echo $uid; ?> .sfb-timeslot-btn.reserved .sfb-reserved-tag{
  display:block;
  font-size:9px;
  font-weight:800;
  text-transform:uppercase;
  letter-spacing:.8px;
  color:#dc2626;
  margin-top:2px;
}
#<?php echo $uid; ?> .sfb-reserved-tag{ display:none; }
#<?php echo $uid; ?> .sfb-input.error,
#<?php echo $uid; ?> .sfb-select.error,
#<?php echo $uid; ?> .sfb-textarea.error{border-color:#dc2626 !important;box-shadow:0 0 0 4px rgba(220,38,38,.10) !important;}
@media (max-width: 768px){
  #<?php echo $uid; ?> .sfb-form-wrap{padding:20px;border-radius:min(<?php echo intval($style['form_radius']); ?>px,20px)}
  #<?php echo $uid; ?> .sfb-row,
  #<?php echo $uid; ?> .sfb-radio-group,
  #<?php echo $uid; ?> .sfb-checkbox-group{
    grid-template-columns:1fr;
  }
  #<?php echo $uid; ?> .sfb-title{font-size:26px}
}
</style>


    <div id="<?php echo $uid; ?>">
        <div class="sfb-form-wrap" style="--sfb-ms-progress:<?php echo esc_attr($style['ms_progress_color']); ?>;--sfb-ms-track:<?php echo esc_attr($style['ms_track_color']); ?>;--sfb-ms-connector:<?php echo esc_attr($style['ms_connector_color']); ?>;--sfb-ms-active:<?php echo esc_attr($style['ms_active_color']); ?>;--sfb-ms-completed:<?php echo esc_attr($style['ms_completed_color']); ?>;--sfb-ms-inactive:<?php echo esc_attr($style['ms_inactive_color']); ?>;--sfb-ms-text:<?php echo esc_attr($style['ms_text_color']); ?>;--sfb-ms-size:<?php echo intval($style['ms_size']); ?>px;--sfb-ms-bar-height:<?php echo intval($style['ms_bar_height']); ?>px;--sfb-ms-spacing:<?php echo intval($style['ms_spacing']); ?>px;--sfb-ms-radius:<?php echo $style['ms_shape']==='circle'?'50%':($style['ms_shape']==='rounded'?'10px':'4px'); ?>;--sfb-ms-tab-bg:<?php echo esc_attr($style['ms_inactive_color']); ?>;--sfb-ms-tab-active-bg:<?php echo esc_attr($style['ms_active_color']); ?>22;--sfb-ms-tab-completed-bg:<?php echo esc_attr($style['ms_completed_color']); ?>22;">
            <?php
            $show_title = array_key_exists('show_title', $s) ? !empty($s['show_title']) : true;
            $show_description = array_key_exists('show_description', $s) ? !empty($s['show_description']) : true;
            $display_title = isset($s['display_title']) && trim((string)$s['display_title']) !== '' ? $s['display_title'] : $form->form_name;
            ?>
            <?php if ($show_title && $display_title !== ''): ?>
                <h2 class="sfb-title" style="display:block !important;visibility:visible !important;"><?php echo esc_html($display_title); ?></h2>
            <?php endif; ?>
            <?php if ($show_description && $form->description): ?>
                <p class="sfb-desc"><?php echo nl2br(esc_html($form->description)); ?></p>
            <?php endif; ?>

            <div class="sfb-top-error" id="<?php echo $uid; ?>-error" style="display:none;"></div>
            <div class="sfb-toast" id="<?php echo $uid; ?>-toast" role="status" aria-live="polite">
                <div class="sfb-toast-icon">✓</div>
                <div class="sfb-toast-body"><div class="sfb-toast-title">Success</div><div class="sfb-toast-message"></div></div>
                <button type="button" class="sfb-toast-close" aria-label="Close">&times;</button>
            </div>

            <div class="sfb-submit-overlay" id="<?php echo $uid; ?>-loading" style="display:none;"><div class="sfb-submit-spinner"></div><strong>Submitting…</strong><span>Please wait.</span></div>
            <form id="<?php echo $uid; ?>-form" method="post" action="#" enctype="multipart/form-data" novalidate>
                <input type="hidden" name="action" value="ssb_submit_form">
                <input type="hidden" name="nonce" value="<?php echo esc_attr(wp_create_nonce('ssb_form_submit')); ?>">
                <input type="hidden" name="form_id" value="<?php echo esc_attr($form->id); ?>"><input type="hidden" name="ssb_rendered_at" value="<?php echo esc_attr(time()); ?>">
                <?php
                $fields = $form->fields;
                $multistep_enabled = !empty($s['multistep_enabled']);
                $progress_mode = sanitize_key((string)($s['multistep_progress'] ?? 'both')); if ($progress_mode === 'numbers' || $progress_mode === 'tabs') $progress_mode = 'steps'; if (!in_array($progress_mode, ['both','steps','bar','none'], true)) $progress_mode = 'both';
                $next_label = $s['multistep_next_label'] ?? 'Next';
                $prev_label = $s['multistep_prev_label'] ?? 'Previous';
                $multi_submit_label = $s['multistep_submit_label'] ?? ($s['submit_label'] ?? 'Submit');
                $groups = [[]];
                $step_titles = ['Step 1'];
                foreach ($fields as $f) {
                    if (($f['type'] ?? '') === 'page_break') {
                        $groups[] = [];
                        $step_titles[] = sanitize_text_field($f['step_title'] ?? ('Step '.count($groups)));
                        continue;
                    }
                    $groups[count($groups)-1][] = $f;
                }
                if (count($groups) < 2) $multistep_enabled = false;
                $step_count = count($groups);
                $form_class = $multistep_enabled ? 'sfb-multistep-enabled' : '';
                ?>
                <?php if ($multistep_enabled && in_array($progress_mode, ['both','steps'], true)): ?>
                    <div class="sfb-step-nodes-wrap" data-step-count="<?php echo (int)$step_count; ?>" aria-label="Form steps">
                        <div class="sfb-step-nodes-container">
                            <?php for ($si=0; $si<$step_count; $si++): ?>
                                <div class="sfb-step-tab<?php echo $si===0?' active':''; ?>" data-step="<?php echo $si+1; ?>" tabindex="<?php echo $si===0?'0':'-1'; ?>" aria-current="<?php echo $si===0?'step':'false'; ?>">
                                    <span class="sfb-step-number"><?php echo $si+1; ?></span>
                                    <?php if (!empty($style['ms_show_titles'])): ?><span class="sfb-step-title"><?php echo esc_html($step_titles[$si] ?: 'Step '.($si+1)); ?></span><?php endif; ?>
                                </div>
                                <?php if ($si < $step_count-1): ?><span class="sfb-node-connector" data-connector="<?php echo $si+1; ?>" aria-hidden="true"></span><?php endif; ?>
                            <?php endfor; ?>
                        </div>
                    </div>
                <?php endif; ?>
                <div class="sfb-form-steps <?php echo esc_attr($form_class); ?>" data-multistep="<?php echo $multistep_enabled?'true':'false'; ?>" data-progress-mode="<?php echo esc_attr($progress_mode); ?>">
                <?php foreach ($groups as $gi => $group): $step_no=$gi+1; ?>
                    <section class="sfb-step-pane<?php echo $gi===0?' active':''; ?> <?php echo esc_attr('sfb-step-anim-'.($style['ms_animation'] ?? 'slide')); ?>" data-step="<?php echo $step_no; ?>" <?php echo $gi===0?'':'hidden'; ?> aria-hidden="<?php echo $gi===0?'false':'true'; ?>">
                        <?php
                        // Keep consecutive half-width fields in the same CSS grid row.
                        // A full-width field, heading, divider, submit field, or step boundary
                        // flushes the pending half-width fields first.
                        $half_row = [];
                        $flush_half_row = function() use (&$half_row, $uid) {
                            if (!$half_row) return;
                            echo '<div class="sfb-row">';
                            foreach ($half_row as $half_field) echo ssb_render_field_html($half_field, $uid);
                            echo '</div>';
                            $half_row = [];
                        };
                        foreach ($group as $f):
                            if (($f['type'] ?? '') === 'submit') { $flush_half_row(); continue; }
                            if (($f['type'] ?? '') === 'heading') {
                                $flush_half_row();
                                echo '<div class="sfb-row"><div class="sfb-field full"><div class="sfb-heading">'.esc_html($f['label']).'</div></div></div>';
                                continue;
                            }
                            if (($f['type'] ?? '') === 'divider') {
                                $flush_half_row();
                                echo '<div class="sfb-row"><div class="sfb-field full"><hr class="sfb-divider"></div></div>';
                                continue;
                            }
                            $is_half = (($f['width'] ?? 'full') === 'half');
                            if ($is_half) {
                                $half_row[] = $f;
                                if (count($half_row) >= 2) $flush_half_row();
                            } else {
                                $flush_half_row();
                                echo '<div class="sfb-row">'.ssb_render_field_html($f, $uid).'</div>';
                            }
                        endforeach;
                        $flush_half_row();
                        ?>
                        <?php if ($multistep_enabled): ?>
                            <?php if ($stripe_enabled && $gi === $step_count - 1): ?>
                                <div class="sfb-payment-box">
                                    <label class="sfb-label">Payment</label>
                                    <div id="<?php echo $uid; ?>-card" class="sfb-card-element"></div>
                                    <div id="<?php echo $uid; ?>-card-error" class="sfb-field-error"></div>
                                    <small style="display:block;margin-top:8px;color:#6b7280;">Amount: <?php echo esc_html(strtoupper($stripe_cfg['currency'] ?? 'USD')); ?> <?php echo esc_html(number_format((float)$stripe_cfg['amount'],2)); ?></small>
                                </div>
                            <?php endif; ?>
                            <div class="sfb-step-actions">
                                <?php if ($gi > 0): ?><button type="button" class="sfb-step-btn sfb-btn-prev"><?php echo esc_html($prev_label); ?></button><?php endif; ?>
                                <?php if ($gi < $step_count-1): ?><button type="button" class="sfb-step-btn sfb-btn-next"><?php echo esc_html($next_label); ?></button><?php else: ?><button type="submit" class="sfb-submit sfb-step-submit"><?php echo esc_html($multi_submit_label); ?></button><?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </section>
                <?php endforeach; ?>
                <?php if (!$multistep_enabled): ?>
                    <?php if ($stripe_enabled): ?>
                        <div class="sfb-payment-box">
                            <label class="sfb-label">Payment</label>
                            <div id="<?php echo $uid; ?>-card" class="sfb-card-element"></div>
                            <div id="<?php echo $uid; ?>-card-error" class="sfb-field-error"></div>
                            <small style="display:block;margin-top:8px;color:#6b7280;">Amount: <?php echo esc_html(strtoupper($stripe_cfg['currency'] ?? 'USD')); ?> <?php echo esc_html(number_format((float)$stripe_cfg['amount'],2)); ?></small>
                        </div>
                    <?php endif; ?>
                    <?php $submit_field = null; foreach (array_reverse($fields) as $sf) { if (($sf['type'] ?? '') === 'submit') { $submit_field=$sf; break; } } ?>
                    <div class="sfb-row"><div class="sfb-field full"><button type="submit" class="sfb-submit"><?php echo esc_html($submit_field['submit_text'] ?? $submit_field['label'] ?? ($s['submit_label'] ?? 'Submit')); ?></button></div></div>
                <?php endif; ?>
                </div>
                <?php if ($multistep_enabled && in_array($progress_mode, ['both','bar'], true)): ?>
                    <?php if ($progress_mode === 'both'): ?>
                        <div class="sfb-bottom-progress sfb-progress-both" data-progress-mode="both">
                            <div class="sfb-progress-meta">
                                <span><?php echo !empty($style['ms_show_step_label']) ? 'Overall Completion' : ''; ?></span>
                                <?php if (!empty($style['ms_show_percent'])): ?><span class="sfb-progress-percent">0% Completed</span><?php endif; ?>
                            </div>
                            <div class="sfb-progress-track" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0"><div class="sfb-progress-bar" style="width:0%"></div></div>
                        </div>
                    <?php else: ?>
                        <div class="sfb-bottom-progress sfb-progress-bar-only" data-progress-mode="bar">
                            <div class="sfb-progress-track" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0">
                                <div class="sfb-progress-bar" style="width:0%"></div>
                                <div class="sfb-progress-overlay">
                                    <?php if (!empty($style['ms_show_step_label'])): ?><span class="sfb-progress-step-label">Step 1 of <?php echo (int)$step_count; ?></span><?php endif; ?>
                                    <?php if (!empty($style['ms_show_percent'])): ?><span class="sfb-progress-percent">0%</span><?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </form>
        </div>
    </div>

    <script>
    (function(){
        var uid      = '<?php echo $uid; ?>';
        var formId   = <?php echo $form->id; ?>;
        var ajaxUrl  = '<?php echo esc_js(admin_url('admin-ajax.php')); ?>';
        var nonce    = '<?php echo esc_js(wp_create_nonce('ssb_form_submit')); ?>';
        var redirect = '<?php echo esc_js($s['redirect_url'] ?? ''); ?>';
        var stripeEnabled = <?php echo $stripe_enabled ? 'true' : 'false'; ?>;
        var stripeAmount  = <?php echo wp_json_encode($stripe_amount); ?>;
        var stripe = null, cardElement = null, stripePromise = null;
        var form = document.getElementById(uid + '-form');
        var errBox = document.getElementById(uid + '-error');
        var toast = document.getElementById(uid + '-toast');
        var toastTimer = null;
        if (!form) return;

        // Repeatable groups: add/remove rows without page reloads and preserve all values.
        function renumberRepeater(rep){
            var key=rep.getAttribute('data-repeater-key'), items=rep.querySelectorAll('.sfb-repeater-item');
            items.forEach(function(item,idx){
                item.setAttribute('data-index',idx);
                var head=item.querySelector('.sfb-repeater-item-header span'); if(head) head.textContent=(rep.dataset.itemLabel||'Item')+' '+(idx+1);
item.querySelectorAll('[name]').forEach(function(el){ var re=new RegExp('^'+key.replace(/[.*+?^${}()|[\]\\]/g,'\\$&')+'\\[\\d+\\]'); el.name=el.name.replace(re,key+'['+idx+']'); });
            });
        }
        function initRepeaters(){
            form.querySelectorAll('.sfb-repeater').forEach(function(rep){
                var itemHead=rep.querySelector('.sfb-repeater-item-header span'); if(itemHead){ var t=itemHead.textContent.replace(/\\s+\\d+$/,'').trim(); rep.dataset.itemLabel=t||'Item'; }
                if(rep.dataset.bound==='1') return; rep.dataset.bound='1';
                rep.addEventListener('click',function(e){
                    var add=e.target.closest('.sfb-repeater-add'), remove=e.target.closest('.sfb-repeater-remove');
                    var items=rep.querySelector('.sfb-repeater-items'); if(!items) return;
                    var max=parseInt(rep.getAttribute('data-max'),10)||10, min=Math.max(1, parseInt(rep.getAttribute('data-min'),10)||1);
                    if(add){ e.preventDefault(); var current=items.querySelectorAll('.sfb-repeater-item').length; if(current>=max) return; var clone=items.lastElementChild ? items.lastElementChild.cloneNode(true) : null; if(!clone) return; clone.querySelectorAll('input,textarea,select').forEach(function(el){ if(el.type==='checkbox'||el.type==='radio') el.checked=false; else el.value=''; }); clone.querySelectorAll('.sfb-field-error').forEach(function(er){er.textContent='';}); var header=clone.querySelector('.sfb-repeater-item-header'); if(header && !header.querySelector('.sfb-repeater-remove')){ var btn=document.createElement('button'); btn.type='button'; btn.className='sfb-repeater-remove'; btn.textContent=rep.getAttribute('data-remove-text')||'Remove'; header.appendChild(btn); } items.appendChild(clone); renumberRepeater(rep); }
                    if(remove){ e.preventDefault(); var item=remove.closest('.sfb-repeater-item'); if(item && items.querySelectorAll('.sfb-repeater-item').length>1 && items.firstElementChild !== item){ item.remove(); renumberRepeater(rep); } }
                });
            });
        }
        initRepeaters();

        var multiStep = <?php echo $multistep_enabled ? 'true' : 'false'; ?>;
        var stepPanes = Array.prototype.slice.call(form.querySelectorAll('.sfb-step-pane'));
        var stepTabs = Array.prototype.slice.call(document.querySelectorAll('#<?php echo $uid; ?> .sfb-step-tab'));
        var currentStep = 1;
        function updateStepUI(target) {
            if (!multiStep || !stepPanes.length) return;
            target = Math.max(1, Math.min(stepPanes.length, target));
            stepPanes.forEach(function(pane){
                var active = parseInt(pane.getAttribute('data-step'),10) === target;
                pane.hidden = !active;
                pane.classList.toggle('active', active);
                pane.setAttribute('aria-hidden', active ? 'false' : 'true');
            });
            stepTabs.forEach(function(tab){
                var n=parseInt(tab.getAttribute('data-step'),10), active=n===target, completed=n<target;
                tab.classList.toggle('active',active); tab.classList.toggle('completed',completed);
                tab.classList.toggle('sfb-step-clickable', <?php echo !empty($style['ms_clickable']) ? 'true' : 'false'; ?> && completed);
                tab.setAttribute('aria-current',active?'step':'false');
                tab.tabIndex = (active || (completed && <?php echo !empty($style['ms_clickable']) ? 'true' : 'false'; ?>)) ? 0 : -1;
            });
            document.querySelectorAll('#<?php echo $uid; ?> .sfb-node-connector').forEach(function(conn){ var n=parseInt(conn.getAttribute('data-connector'),10)||0; conn.classList.toggle('filled', target>n); });
            var mode=document.querySelector('#<?php echo $uid; ?> .sfb-bottom-progress');
            var pct=stepPanes.length ? ((target-1)/stepPanes.length)*100 : 0;
            var bar=mode && mode.querySelector('.sfb-progress-bar'); if(bar) { bar.style.width=pct+'%'; }
            var track=mode && mode.querySelector('.sfb-progress-track'); if(track) track.setAttribute('aria-valuenow',String(Math.round(pct)));
            var meta=mode && mode.querySelector('.sfb-progress-step-label'); if(meta) meta.textContent='Step '+target+' of '+stepPanes.length;
            var percent=mode && mode.querySelector('.sfb-progress-percent'); if(percent) percent.textContent=Math.round(pct)+'%' + (mode.classList.contains('sfb-progress-both') ? ' Completed' : '');
            currentStep=target;
            var activePane=form.querySelector('.sfb-step-pane[data-step="'+target+'"]');
            if(activePane) { var first=activePane.querySelector('input:not([type=hidden]),select,textarea,button'); if(first && window.matchMedia('(max-width: 900px)').matches) first.focus({preventScroll:true}); }
        }
        function validatePane(stepNo) {
            var pane=form.querySelector('.sfb-step-pane[data-step="'+stepNo+'"]');
            if(!pane) return true;
            var firstInvalid=null;
            pane.querySelectorAll('input,select,textarea').forEach(function(field){
                if(field.disabled || field.type==='hidden') return;
                if(!validateSingleField(field) && !firstInvalid) firstInvalid=field;
            });
            if(firstInvalid){ firstInvalid.focus({preventScroll:true}); (firstInvalid.closest('.sfb-field')||firstInvalid).scrollIntoView({behavior:'smooth',block:'center'}); return false; }
            return true;
        }
        if(multiStep){
            form.addEventListener('click',function(e){
                var next=e.target.closest('.sfb-btn-next'), prev=e.target.closest('.sfb-btn-prev'), tab=e.target.closest('.sfb-step-tab');
                if(next){ e.preventDefault(); if(validatePane(currentStep)) updateStepUI(currentStep+1); }
                if(prev){ e.preventDefault(); updateStepUI(currentStep-1); }
                if(tab){ var n=parseInt(tab.getAttribute('data-step'),10); if(n===currentStep) return; if(<?php echo !empty($style['ms_clickable']) ? 'true' : 'false'; ?> && n<currentStep && n>=1){ e.preventDefault(); updateStepUI(n); } }
            });
            form.addEventListener('keydown',function(e){ if((e.key==='Enter'||e.key===' ') && e.target.classList.contains('sfb-step-tab')){ var n=parseInt(e.target.getAttribute('data-step'),10); if(<?php echo !empty($style['ms_clickable']) ? 'true' : 'false'; ?> && n<currentStep){e.preventDefault();updateStepUI(n);} } });
            updateStepUI(1);
        }

        function showToast(type, message) {
            if (!toast) return;
            clearTimeout(toastTimer);
            toast.className = 'sfb-toast show ' + (type === 'success' ? 'success' : 'error');
            toast.querySelector('.sfb-toast-icon').textContent = type === 'success' ? '✓' : '!';
            toast.querySelector('.sfb-toast-title').textContent = type === 'success' ? 'Success' : 'Something went wrong';
            toast.querySelector('.sfb-toast-message').textContent = message || '';
            toastTimer = setTimeout(function(){ toast.classList.remove('show'); }, type === 'success' ? 5000 : 6500);
        }
        function showTopError(message) {
            errBox.textContent = message || 'Please check the form and try again.';
            errBox.style.display = 'none';
            showToast('error', message);
        }
        function clearErrors() {
            errBox.style.display = 'none';
            form.querySelectorAll('.sfb-error,.error').forEach(function(el){ el.classList.remove('sfb-error','error'); });
            form.querySelectorAll('.sfb-field-error').forEach(function(el){
                if (!el.id || el.id.indexOf('-card-error') === -1) { el.textContent=''; el.classList.remove('visible'); el.style.display=''; }
            });
        }
        function fieldError(field, message) {
            if (!field) return;
            field.classList.add('sfb-error');
            var wrap = field.closest('.sfb-field');
            var name = field.name || '';
            var er = wrap ? wrap.querySelector('.sfb-field-error[data-field="'+CSS.escape(name.replace(/\[\]$/,''))+'"],.sfb-field-error[data-field="'+CSS.escape(name)+'"]') : null;
            if (er) { er.textContent = message; er.classList.add('visible'); }
        }
        function validateForm() {
            clearErrors();
            var firstInvalid = null;
            var handledGroups = {};
            form.querySelectorAll('[required]').forEach(function(field){
                if (field.disabled) return;
                var name = field.name || field.id;
                if ((field.type === 'radio' || field.type === 'checkbox') && handledGroups[name]) return;
                handledGroups[name] = true;
                var valid = true, message = 'This field is required.';
                if (field.type === 'radio' || field.type === 'checkbox') {
                    valid = !!form.querySelector('input[name="'+CSS.escape(field.name)+'"]:checked');
                } else {
                    valid = String(field.value || '').trim() !== '';
                }
                if (field.type === 'email' && valid && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(field.value.trim())) {
                    valid = false; message = 'Please enter a valid email address.';
                }
                if (field.type === 'url' && valid) {
                    try { new URL(field.value); } catch(e) { valid = false; message = 'Please enter a valid URL.'; }
                }
                if (!valid) { fieldError(field, message); if (!firstInvalid) firstInvalid = field; }
            });

            form.querySelectorAll('.sfb-phone-wrap').forEach(function(wrap){
                var inp = wrap.querySelector('.sfb-phone-input');
                var er  = wrap.querySelector('.sfb-phone-error');
                if (!inp) return;
                var raw = inp.value.trim();
                if (!raw && !inp.required) return;
                if (!raw || !/^\+?[0-9\s().-]{6,22}$/.test(raw)) {
                    inp.classList.add('sfb-error');
                    if (er) { er.textContent = raw ? 'Enter a valid phone number.' : 'Phone number is required.'; er.style.display='block'; }
                    if (!firstInvalid) firstInvalid = inp;
                }
            });
            if (firstInvalid) {
                if (multiStep) {
                    var badPane = firstInvalid.closest('.sfb-step-pane');
                    if (badPane) updateStepUI(parseInt(badPane.getAttribute('data-step'),10) || 1);
                }
                firstInvalid.focus({preventScroll:true});
                var wrap = firstInvalid.closest('.sfb-field');
                (wrap || firstInvalid).scrollIntoView({behavior:'smooth',block:'center'});
                return false;
            }
            return true;
        }

        function validateSingleField(field) {
            if (!field || field.disabled || !field.required) return true;
            var valid = true, message = 'This field is required.';
            if (field.type === 'radio' || field.type === 'checkbox') {
                valid = !!form.querySelector('input[name="'+CSS.escape(field.name)+'"]:checked');
            } else { valid = String(field.value || '').trim() !== ''; }
            if (field.type === 'email' && valid && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(field.value.trim())) { valid=false; message='Please enter a valid email address.'; }
            if (field.type === 'url' && valid) { try { new URL(field.value); } catch(e) { valid=false; message='Please enter a valid URL.'; } }
            if (!valid) fieldError(field, message); else { field.classList.remove('sfb-error','error'); var wrap=field.closest('.sfb-field'); if(wrap){var er=wrap.querySelector('.sfb-field-error');if(er){er.classList.remove('visible');er.textContent='';}} }
            return valid;
        }
        form.addEventListener('input', function(e){
            var field=e.target;
            if (field.matches('input,select,textarea')) { validateSingleField(field); }
        });
        form.addEventListener('change', function(e){
            if(e.target.matches('input,select,textarea')) e.target.dispatchEvent(new Event('input',{bubbles:true}));
        });

        function loadStripe() {
            if (!stripeEnabled) return Promise.resolve();
            if (cardElement) return Promise.resolve();
            if (stripePromise) return stripePromise;
            stripePromise = new Promise(function(resolve,reject){
                function mount(){
                    try {
                        var key = '<?php echo esc_js(get_option('ssb_stripe_publishable_key','')); ?>';
                        var host = document.getElementById(uid+'-card');
                        if (!key) throw new Error('Stripe publishable key is not configured.');
                        if (!host) throw new Error('Payment field is not available.');
                        if (cardElement) return resolve();
                        stripe = window.Stripe(key);
                        cardElement = stripe.elements().create('card',{style:{base:{fontSize:'15px',color:'#111827'}}});
                        cardElement.mount(host);
                        cardElement.on('change',function(ev){
                            var ce=document.getElementById(uid+'-card-error');
                            if(ce){ce.textContent=ev.error?ev.error.message:'';ce.classList.toggle('visible',!!ev.error);}
                        });
                        resolve();
                    } catch(ex) { reject(ex); }
                }
                if (window.Stripe) return mount();
                var existing=document.querySelector('script[src="https://js.stripe.com/v3/"]');
                if(existing){ existing.addEventListener('load',mount,{once:true}); existing.addEventListener('error',function(){reject(new Error('Unable to load Stripe.'));},{once:true}); return; }
                var sc=document.createElement('script'); sc.src='https://js.stripe.com/v3/'; sc.async=true; sc.onload=mount; sc.onerror=function(){reject(new Error('Unable to load Stripe.'));}; document.head.appendChild(sc);
            });
            return stripePromise;
        }
        if (stripeEnabled) loadStripe().catch(function(ex){ var ce=document.getElementById(uid+'-card-error'); if(ce){ce.textContent=ex.message;ce.classList.add('visible');} });

        function encodeForm() {
            var params = new FormData(form);
            params.set('action','ssb_submit_form'); params.set('nonce',nonce); params.set('form_id',String(formId));
            try { var tz=Intl.DateTimeFormat().resolvedOptions().timeZone||''; params.set('client_timezone',tz); if(tz) params.set('client_timezone_display',tz); } catch(e) {}
            return params;
        }
        function post(params){
            var isFD = params instanceof FormData;
            return fetch(ajaxUrl,{method:'POST',credentials:'same-origin',headers:isFD?{}:{'Content-Type':'application/x-www-form-urlencoded; charset=UTF-8'},body:isFD?params:params.toString()})
                .then(function(res){ return res.text().then(function(text){ try{return JSON.parse(text);}catch(e){throw new Error('Unexpected server response.');} }); });
        }
        function applyServerErrors(data) {
            var errors = data && data.errors ? data.errors : null;
            if (!errors) return false;
            var first=null;
            Object.keys(errors).forEach(function(key){
                var field=form.querySelector('[name="'+CSS.escape(key)+'"],[name="'+CSS.escape(key+'[]')+'"]');
                if(field){ fieldError(field,errors[key]); if(!first) first=field; }
            });
            if(first){ (first.closest('.sfb-field')||first).scrollIntoView({behavior:'smooth',block:'center'}); }
            return !!first;
        }

        form.addEventListener('submit', function(e){
            e.preventDefault(); e.stopPropagation();
            if (!validateForm()) return false;
            var btn=form.querySelector('.sfb-submit');
            var loading=document.getElementById(uid+'-loading');
            btn.disabled=true; btn.innerHTML='<span class="sfb-loader"></span> Submitting…'; if(loading) loading.style.display='flex';
            var params=encodeForm();
            var flow=Promise.resolve();
            if(stripeEnabled){
                flow=loadStripe().then(function(){
                    if(stripeAmount<=0) throw new Error('Stripe is enabled, but the payment amount is 0.');
                    return post(new URLSearchParams({action:'ssb_form_create_payment_intent',nonce:nonce,form_id:String(formId)}));
                }).then(function(r){
                    if(!r.success) throw new Error((r.data&&r.data.message)||'Unable to initialize payment.');
                    return stripe.confirmCardPayment(r.data.client_secret,{payment_method:{card:cardElement}});
                }).then(function(result){
                    if(result.error) throw new Error(result.error.message);
                    if(!result.paymentIntent) throw new Error('Payment was not completed.');
                    params.set('payment_intent_id',result.paymentIntent.id);
                });
            }
            flow.then(function(){return post(params);}).then(function(r){
                if(r.success){
                    var doneBar=document.querySelector('#<?php echo $uid; ?> .sfb-bottom-progress .sfb-progress-bar'); if(doneBar) doneBar.style.width='100%';
                    var doneTrack=document.querySelector('#<?php echo $uid; ?> .sfb-bottom-progress .sfb-progress-track'); if(doneTrack) doneTrack.setAttribute('aria-valuenow','100');
                    var donePct=document.querySelector('#<?php echo $uid; ?> .sfb-bottom-progress .sfb-progress-percent'); if(donePct) donePct.textContent='100%' + (donePct.closest('.sfb-progress-both') ? ' Completed' : '');
                    form.reset();
                    showToast('success', (r.data&&r.data.msg)||'Thank you! Your submission has been received.');
                    var go=redirect || (r.data&&r.data.redirect) || '';
                    if(go) setTimeout(function(){window.location.assign(go);},1500);
                } else {
                    var data=r.data||{};
                    if(!applyServerErrors(data)) showTopError(data.msg||'Please check the form and try again.');
                }
            }).catch(function(ex){ showTopError(ex && ex.message ? ex.message : 'Network error. Please try again.'); })
            .finally(function(){ btn.disabled=false; btn.textContent='<?php echo esc_js($multistep_enabled ? $multi_submit_label : ($s['submit_label'] ?? 'Submit')); ?>'; if(loading) loading.style.display='none'; });
            return false;
        });

        if(toast){ toast.querySelector('.sfb-toast-close').addEventListener('click',function(){ toast.classList.remove('show'); }); }
    })();
    </script>
    <script>
    (function(){
        var scope = document.getElementById('<?php echo $uid; ?>');
        if (!scope) return;

        // ── Country picker ────────────────────────────────────────────────
        scope.querySelectorAll('.sfb-phone-wrap').forEach(function(wrap){
            var btn      = wrap.querySelector('.sfb-country-btn');
            var dropdown = wrap.querySelector('.sfb-country-dropdown');
            var search   = wrap.querySelector('.sfb-country-search');
            var list     = wrap.querySelector('.sfb-country-list');
            var flagEl   = wrap.querySelector('.sfb-cc-flag');
            var dialEl   = wrap.querySelector('.sfb-cc-dial');
            var hidden   = wrap.querySelector('.sfb-cc-hidden');
            if (!btn) return;

            function selectCountry(opt) {
                flagEl.textContent = opt.dataset.flag;
                dialEl.textContent = opt.dataset.dial;
                if (hidden) hidden.value = opt.dataset.dial;
                list.querySelectorAll('.sfb-country-option').forEach(function(o){ o.classList.remove('selected'); });
                opt.classList.add('selected');
                closeDropdown();
            }

            function closeDropdown() {
                dropdown.style.display = 'none';
                btn.classList.remove('open');
                btn.setAttribute('aria-expanded','false');
            }

            btn.addEventListener('click', function(e){
                e.stopPropagation();
                var isOpen = dropdown.style.display !== 'none';
                if (isOpen) { closeDropdown(); } else {
                    dropdown.style.display = 'block';
                    btn.classList.add('open');
                    btn.setAttribute('aria-expanded','true');
                    search.value = '';
                    filterList('');
                    setTimeout(function(){ search.focus(); }, 50);
                }
            });

            list.querySelectorAll('.sfb-country-option').forEach(function(opt){
                opt.addEventListener('click', function(){ selectCountry(opt); });
            });

            search.addEventListener('input', function(){ filterList(this.value.toLowerCase()); });

            function filterList(q) {
                list.querySelectorAll('.sfb-country-option').forEach(function(opt){
                    var match = opt.dataset.name.toLowerCase().includes(q) || opt.dataset.dial.includes(q);
                    opt.style.display = match ? '' : 'none';
                });
            }

            document.addEventListener('click', function(e){
                if (!wrap.contains(e.target)) closeDropdown();
            });

            // Set US +1 as default selected
            var us = list.querySelector('[data-dial="+1"]');
            if (us) us.classList.add('selected');
        });

        // ── Timeslot radio pill highlighting + reserved check ─────────────
        var ajaxUrlTs = '<?php echo esc_js(admin_url('admin-ajax.php')); ?>';
        var tsNonce   = '<?php echo wp_create_nonce('ssb_public_nonce'); ?>';

        scope.querySelectorAll('.sfb-timeslot-grid').forEach(function(grid) {
            // Try to find a sibling date field to do live reserved check
            var fieldWrap = grid.closest('.sfb-field');

            grid.querySelectorAll('.sfb-timeslot-btn').forEach(function(btn) {
                var inp = btn.querySelector('input[type=radio]');
                if (!inp) return;

                inp.addEventListener('change', function() {
                    if (btn.classList.contains('reserved')) {
                        this.checked = false;
                        return;
                    }
                    grid.querySelectorAll('.sfb-timeslot-btn').forEach(function(b) { b.classList.remove('checked'); });
                    if (this.checked) btn.classList.add('checked');
                });
            });

            // Check slots for a given date against the booking system
            function checkReservedSlots(dateVal) {
                if (!dateVal) return;
                var clientTz = '';
                try { clientTz = Intl.DateTimeFormat().resolvedOptions().timeZone; } catch(e) {}
                var params = new URLSearchParams({action:'ssb_get_slots', nonce:tsNonce, date:dateVal, timezone:clientTz || 'America/New_York'});
                fetch(ajaxUrlTs, {method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body:params})
                .then(function(r){ return r.json(); })
                .then(function(r) {
                    var reserved = (r.success && r.data.reserved_slots) ? r.data.reserved_slots : {};
                    grid.querySelectorAll('.sfb-timeslot-btn').forEach(function(btn) {
                        var inp = btn.querySelector('input[type=radio]');
                        if (!inp) return;
                        var slotVal = inp.value;
                        if (reserved[slotVal]) {
                            btn.classList.add('reserved');
                            inp.disabled = true;
                            // Add reserved tag if not already there
                            if (!btn.querySelector('.sfb-reserved-tag')) {
                                var tag = document.createElement('span');
                                tag.className = 'sfb-reserved-tag';
                                tag.textContent = 'Reserved';
                                btn.querySelector('span').appendChild(tag);
                            }
                        } else {
                            btn.classList.remove('reserved');
                            inp.disabled = false;
                            var tag = btn.querySelector('.sfb-reserved-tag');
                            if (tag) tag.remove();
                        }
                    });
                })
                .catch(function(){});
            }

            // Listen for a date field change in the same form
            var formEl = grid.closest('form');
            if (formEl) {
                formEl.querySelectorAll('input[type=date]').forEach(function(dateInp) {
                    dateInp.addEventListener('change', function() {
                        checkReservedSlots(this.value);
                    });
                    // If date already has a value on load
                    if (dateInp.value) checkReservedSlots(dateInp.value);
                });
            }
        });
    })();
    </script>
    <?php
    return ob_get_clean();
}

function ssb_render_repeater_child_html($child, $uid, $parent_key, $index) {
    $type = sanitize_key($child['type'] ?? 'text');
    $cid = sanitize_key($child['id'] ?? uniqid('rf'));
    $name = $parent_key . '[' . $index . '][' . $cid . ']';
    $id = $uid . '_' . sanitize_key($parent_key) . '_' . $index . '_' . $cid;
    $label = esc_html($child['label'] ?? ucfirst($type));
    $ph = esc_attr($child['placeholder'] ?? '');
    $req = !empty($child['required']) ? 'required' : '';
    $html = '<div class="sfb-repeater-child-field"><label class="sfb-label" for="'.esc_attr($id).'">'.$label.($req?' <span class="sfb-req">*</span>':'').'</label>';
    switch ($type) {
        case 'textarea': $html.='<textarea class="sfb-textarea" name="'.esc_attr($name).'" id="'.esc_attr($id).'" placeholder="'.$ph.'" '.$req.'></textarea>'; break;
        case 'select': $html.='<select class="sfb-select" name="'.esc_attr($name).'" id="'.esc_attr($id).'" '.$req.'><option value="">'.($ph?:'Select…').'</option>'; foreach((array)($child['options']??[]) as $o)$html.='<option value="'.esc_attr($o).'">'.esc_html($o).'</option>'; $html.='</select>'; break;
        case 'radio': $html.='<div class="sfb-radio-group">'; foreach((array)($child['options']??[]) as $o){$html.='<label class="sfb-option-label"><input type="radio" name="'.esc_attr($name).'" value="'.esc_attr($o).'" '.$req.'> '.esc_html($o).'</label>';} $html.='</div>'; break;
        case 'checkbox': $html.='<div class="sfb-checkbox-group">'; foreach((array)($child['options']??[]) as $o){$html.='<label class="sfb-option-label"><input type="checkbox" name="'.esc_attr($name).'[]" value="'.esc_attr($o).'"> '.esc_html($o).'</label>';} $html.='</div>'; break;
        case 'toggle': $html.='<label class="sfb-toggle" style="display:flex;align-items:center;gap:10px;"><input type="checkbox" name="'.esc_attr($name).'" value="1" '.$req.'><span class="sfb-toggle-ui"><span></span></span><span>On / Off</span></label>'; break;
        case 'date': $html.='<input class="sfb-input" type="date" name="'.esc_attr($name).'" id="'.esc_attr($id).'" '.$req.'>'; break;
        case 'time': $html.='<input class="sfb-input" type="time" name="'.esc_attr($name).'" id="'.esc_attr($id).'" '.$req.'>'; break;
        case 'number': $html.='<input class="sfb-input" type="number" name="'.esc_attr($name).'" id="'.esc_attr($id).'" placeholder="'.$ph.'" '.$req.'>'; break;
        case 'url': $html.='<input class="sfb-input" type="url" name="'.esc_attr($name).'" id="'.esc_attr($id).'" placeholder="'.$ph.'" '.$req.'>'; break;
        case 'email': $html.='<input class="sfb-input" type="email" name="'.esc_attr($name).'" id="'.esc_attr($id).'" placeholder="'.$ph.'" '.$req.'>'; break;
        case 'phone': $html.='<input class="sfb-input" type="tel" name="'.esc_attr($name).'" id="'.esc_attr($id).'" placeholder="'.($ph?:'+1 234 567 8900').'" '.$req.'>'; break;
        default: $html.='<input class="sfb-input" type="text" name="'.esc_attr($name).'" id="'.esc_attr($id).'" placeholder="'.$ph.'" '.$req.'>';
    }
    return $html.'</div>';
}

function ssb_render_repeater_field_html($f, $uid) {
    $key='field_'.sanitize_key($f['id']); $label=esc_html($f['label']??'Repeatable Group'); $min=max(1,intval($f['min_items']??1)); $max=max(1,intval($f['max_items']??10)); $default=max(1,$min,min($max,intval($f['default_items']??1))); $item_label=esc_html($f['item_label']??'Item');
    $html='<div class="sfb-repeater" data-repeater-key="'.esc_attr($key).'" data-min="'.$min.'" data-max="'.$max.'" data-add-text="'.esc_attr($f['add_text']??'Add Another').'" data-remove-text="'.esc_attr($f['remove_text']??'Remove').'">';
    $html.='<div class="sfb-repeater-items">';
    for($i=0;$i<$default;$i++){ $html.='<div class="sfb-repeater-item" data-index="'.$i.'"><div class="sfb-repeater-item-header"><span>'.esc_html($item_label).' '.($i+1).'</span>'; if($i>=$min) $html.='<button type="button" class="sfb-repeater-remove">'.esc_html($f['remove_text']??'Remove').'</button>'; $html.='</div>'; foreach((array)($f['child_fields']??[]) as $child)$html.=ssb_render_repeater_child_html($child,$uid,$key,$i); $html.='</div>'; }
    $html.='</div><button type="button" class="sfb-repeater-add">+ '.esc_html($f['add_text']??'Add Another').'</button></div>'; return $html;
}

function ssb_render_field_html($f, $uid) {
    $key      = 'field_' . $f['id'];
    $label    = esc_html($f['label']);
    $show_labels = array_key_exists('show_labels', $GLOBALS['ssb_render_form_settings'] ?? []) ? !empty($GLOBALS['ssb_render_form_settings']['show_labels']) : true;
    $ph       = esc_attr($f['placeholder'] ?? '');
    $req      = $f['required'] ? 'required' : '';
    $req_star = $f['required'] ? '<span class="sfb-req"> *</span>' : '';
    $help     = $f['help_text'] ? '<p class="sfb-help">' . esc_html($f['help_text']) . '</p>' : '';
    $width    = ($f['width'] ?? 'full') === 'half' ? 'half' : 'full';
    $type     = $f['type'];
    $fs = !empty($f['style_customized']) && isset($f['style']) && is_array($f['style']) ? $f['style'] : [];
    $field_style = '';
    if ($fs) {
        $fb = sanitize_hex_color($fs['bg'] ?? '') ?: '';
        $ft = sanitize_hex_color($fs['text'] ?? '') ?: '';
        $fborder = sanitize_hex_color($fs['border'] ?? '') ?: '';
        if ($fb) $field_style .= 'background:'.$fb.' !important;';
        if ($ft) $field_style .= 'color:'.$ft.' !important;';
        if ($fborder) $field_style .= 'border-color:'.$fborder.' !important;';
        if (isset($fs['border_width'])) $field_style .= 'border-width:'.max(0,min(6,intval($fs['border_width']))).'px !important;';
        if (isset($fs['radius'])) $field_style .= 'border-radius:'.max(0,min(60,intval($fs['radius']))).'px !important;';
        if (isset($fs['padding'])) $field_style .= 'padding:'.max(4,min(40,intval($fs['padding']))).'px !important;';
        if (isset($fs['font_size'])) $field_style .= 'font-size:'.max(10,min(30,intval($fs['font_size']))).'px !important;';
    }
    $style_attr = $field_style ? ' style="' . esc_attr($field_style) . '"' : '';

    $html = '<div class="sfb-field ' . $width . '">';
    if ($show_labels) $html .= '<label class="sfb-label" for="' . esc_attr($uid . '_' . $key) . '">' . $label . $req_star . '</label>';
    $html .= '<div class="sfb-field-error" data-field="' . esc_attr($key) . '"></div>';

    switch ($type) {
        case 'textarea':
            $html .= '<textarea class="sfb-textarea"' . $style_attr . ' name="' . esc_attr($key) . '" id="' . esc_attr($uid.'_'.$key) . '" placeholder="' . $ph . '" ' . $req . '></textarea>';
            break;

        case 'select':
            $html .= '<select class="sfb-select"' . $style_attr . ' name="' . esc_attr($key) . '" id="' . esc_attr($uid.'_'.$key) . '" ' . $req . '>';
            $html .= '<option value="">' . ($ph ?: 'Select…') . '</option>';
            foreach ($f['options'] as $opt) {
                $html .= '<option value="' . esc_attr($opt) . '">' . esc_html($opt) . '</option>';
            }
            $html .= '</select>';
            break;

        case 'radio':
            $html .= '<div class="sfb-radio-group">';
            foreach ($f['options'] as $opt) {
                $oid  = esc_attr($uid . '_' . $key . '_' . sanitize_key($opt));
                $html .= '<label class="sfb-option-label"' . $style_attr . '><input type="radio" name="' . esc_attr($key) . '" id="' . $oid . '" value="' . esc_attr($opt) . '" ' . $req . '> ' . esc_html($opt) . '</label>';
            }
            $html .= '</div>';
            break;

        case 'checkbox':
            $html .= '<div class="sfb-checkbox-group">';
            foreach ($f['options'] as $opt) {
                $oid  = esc_attr($uid . '_' . $key . '_' . sanitize_key($opt));
                $html .= '<label class="sfb-option-label"' . $style_attr . '><input type="checkbox" name="' . esc_attr($key) . '[]" id="' . $oid . '" value="' . esc_attr($opt) . '" ' . $req . '> ' . esc_html($opt) . '</label>';
            }
            $html .= '</div>';
            break;

        case 'toggle':
            $html .= '<label class="sfb-toggle" style="display:flex;align-items:center;gap:10px;cursor:pointer;"><input type="checkbox" name="' . esc_attr($key) . '" id="' . esc_attr($uid.'_'.$key) . '" value="1" ' . $req . '><span class="sfb-toggle-ui"><span></span></span><span>On / Off</span></label>';
            break;
        case 'name':
            if (($f['name_mode'] ?? 'first_last') === 'full') {
                $html .= '<input class="sfb-input"'.$style_attr.' type="text" name="'.esc_attr($key).'" id="'.esc_attr($uid.'_'.$key).'" placeholder="'.($ph ?: 'Full name').'" '.$req.'>';
            } else {
                $html .= '<div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;"><input class="sfb-input"'.$style_attr.' type="text" name="'.esc_attr($key).'[first]" id="'.esc_attr($uid.'_'.$key.'_first').'" placeholder="First name" '.($f['required']?'required':'').'><input class="sfb-input"'.$style_attr.' type="text" name="'.esc_attr($key).'[last]" id="'.esc_attr($uid.'_'.$key.'_last').'" placeholder="Last name" '.($f['required']?'required':'').'></div>';
            }
            break;
        case 'address':
            $html .= '<div style="display:grid;gap:10px;"><input class="sfb-input"'.$style_attr.' type="text" name="'.esc_attr($key).'[street]" placeholder="Street address" '.($f['required']?'required':'').'><div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;"><input class="sfb-input"'.$style_attr.' type="text" name="'.esc_attr($key).'[city]" placeholder="City" '.($f['required']?'required':'').'><input class="sfb-input"'.$style_attr.' type="text" name="'.esc_attr($key).'[state]" placeholder="State / Province" '.($f['required']?'required':'').'></div><div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;"><input class="sfb-input"'.$style_attr.' type="text" name="'.esc_attr($key).'[postal]" placeholder="ZIP / Postal Code" '.($f['required']?'required':'').'>' . (($f['address_country'] ?? true) ? '<input class="sfb-input"'.$style_attr.' type="text" name="'.esc_attr($key).'[country]" placeholder="Country" '.($f['required']?'required':'').'>' : '') . '</div></div>';
            break;
        case 'file':
            $accept=''; $types=array_filter(array_map('sanitize_key',preg_split('/[,\s]+/',(string)($f['file_types']??'')))); if($types)$accept=' accept=".'.esc_attr(implode(',.',$types)).'"';
            $html .= '<input class="sfb-input"'.$style_attr.' type="file" name="'.esc_attr($key).($f['file_multiple']?'[]':'').'" id="'.esc_attr($uid.'_'.$key).'"'.$accept.($f['file_multiple']?' multiple':'').' '.($f['required']?'required':'').'>';
            break;
        case 'datetime':
            $min = !empty($f['disable_past']) ? ' min="'.esc_attr(current_time('Y-m-d\TH:i')).'"' : '';
            $html .= '<input class="sfb-input"'.$style_attr.' type="datetime-local" name="'.esc_attr($key).'" id="'.esc_attr($uid.'_'.$key).'"'.$min.' '.$req.'>';
            break;
        case 'hidden':
            $html .= '<input type="hidden" name="'.esc_attr($key).'" value="'.esc_attr(ssb_form_resolve_hidden_value($f['hidden_value']??'')).'">';
            break;
        case 'terms':
            $html .= '<label style="display:flex;align-items:flex-start;gap:10px;font-size:13px;line-height:1.5;"><input type="checkbox" name="'.esc_attr($key).'" id="'.esc_attr($uid.'_'.$key).'" value="1" '.$req.'> <span>'.esc_html($f['terms_text']??'I agree to the Terms and Privacy Policy.').'</span></label>';
            break;
        case 'spam_protection':
            $mode=sanitize_key($f['spam_mode']??'honeypot');
            $html .= '<div class="sfb-spam-protection" data-mode="'.esc_attr($mode).'">';
            $html .= '<div style="position:absolute;left:-10000px;top:auto;width:1px;height:1px;overflow:hidden;"><label>Leave this field empty<input type="text" name="ssb_hp_'.esc_attr($f['id']).'" tabindex="-1" autocomplete="off"></label></div>';
            if ($mode==='turnstile' && get_option('ssb_turnstile_site_key','')) $html .= '<div class="cf-turnstile" data-sitekey="'.esc_attr(get_option('ssb_turnstile_site_key','')).'"></div>';
            elseif ($mode==='recaptcha' && get_option('ssb_recaptcha_site_key','')) $html .= '<div class="g-recaptcha" data-sitekey="'.esc_attr(get_option('ssb_recaptcha_site_key','')).'"></div>';
            else $html .= '<span style="display:none">Spam protection enabled.</span>';
            $html .= '</div>';
            break;
        case 'repeater':
            $html .= ssb_render_repeater_field_html($f, $uid);
            break;

        case 'booking':
            $html .= '<div class="sfb-booking-field" data-field-key="'.esc_attr($key).'" data-duration="'.esc_attr((int)($f['booking_duration']??30)).'">';
            $html .= '<div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;"><input class="sfb-input sfb-booking-date"'.$style_attr.' type="date" name="'.esc_attr($key).'[date]" id="'.esc_attr($uid.'_'.$key.'_date').'" min="'.esc_attr(date('Y-m-d')).'" '.($f['required']?'required':'').'><input class="sfb-input sfb-booking-time"'.$style_attr.' type="time" name="'.esc_attr($key).'[time]" id="'.esc_attr($uid.'_'.$key.'_time').'" '.($f['required']?'required':'').'></div>';
            $html .= '<div style="margin-top:10px;">'; $html .= ssb_render_timezone_select($key.'[timezone]', $uid.'_'.$key.'_timezone', $f['required']?'required':'', $style_attr); $html .= '</div>';
            $html .= '<div class="sfb-booking-status" style="font-size:12px;color:#64748b;margin-top:7px;"></div></div>';
            break;
        case 'date':
            $disable_past = !isset($f['disable_past']) || $f['disable_past'];
            $min_attr = $disable_past ? ' min="' . date('Y-m-d') . '"' : '';
            $html .= '<input class="sfb-input"' . $style_attr . ' type="date" name="' . esc_attr($key) . '" id="' . esc_attr($uid.'_'.$key) . '"' . $min_attr . ' ' . $req . '>';
            break;

        case 'time':
            $html .= '<input class="sfb-input"' . $style_attr . ' type="time" name="' . esc_attr($key) . '" id="' . esc_attr($uid.'_'.$key) . '" ' . $req . '>';
            break;

        case 'number':
            $html .= '<input class="sfb-input"' . $style_attr . ' type="number" name="' . esc_attr($key) . '" id="' . esc_attr($uid.'_'.$key) . '" placeholder="' . $ph . '" ' . $req . '>';
            break;

        case 'url':
            $html .= '<input class="sfb-input"' . $style_attr . ' type="url" name="' . esc_attr($key) . '" id="' . esc_attr($uid.'_'.$key) . '" placeholder="' . $ph . '" ' . $req . '>';
            break;

        case 'email':
            $html .= '<input class="sfb-input"' . $style_attr . ' type="email" name="' . esc_attr($key) . '" id="' . esc_attr($uid.'_'.$key) . '" placeholder="' . $ph . '" ' . $req . '>';
            break;

        case 'phone':
            $enable_cc = !empty($f['enable_country_code']);
            if ($enable_cc) {
                $html .= ssb_render_phone_with_country($key, $uid . '_' . $key, $req, $style_attr);
            } else {
                $html .= '<input class="sfb-input"' . $style_attr . ' type="tel" name="' . esc_attr($key) . '" id="' . esc_attr($uid.'_'.$key) . '" placeholder="' . ($ph ?: '+1 234 567 8900') . '" ' . $req . '>';
            }
            break;

        case 'timeslot':
            $mode = $f['timeslot_mode'] ?? 'select';
            $slots = $f['options'] ?? [];
            if ($mode === 'radio') {
                $html .= '<div class="sfb-timeslot-grid">';
                foreach ($slots as $slot) {
                    $sid = esc_attr($uid . '_' . $key . '_' . sanitize_key($slot));
                    $html .= '<label class="sfb-timeslot-btn"' . $style_attr . '>' .
                             '<input type="radio" name="' . esc_attr($key) . '" id="' . $sid . '" value="' . esc_attr($slot) . '" ' . $req . '>' .
                             '<span>' . esc_html($slot) . '<span class="sfb-reserved-tag"></span></span>' .
                             '</label>';
                }
                $html .= '</div>';
            } else {
                $html .= '<select class="sfb-select"' . $style_attr . ' name="' . esc_attr($key) . '" id="' . esc_attr($uid.'_'.$key) . '" ' . $req . '>';
                $html .= '<option value="">Select a time slot…</option>';
                foreach ($slots as $slot) {
                    $html .= '<option value="' . esc_attr($slot) . '">' . esc_html($slot) . '</option>';
                }
                $html .= '</select>';
            }
            break;

        case 'timezone':
            $html .= ssb_render_timezone_select($key, $uid . '_' . $key, $req, $style_attr);
            break;

        default: // text
            $html .= '<input class="sfb-input"' . $style_attr . ' type="text" name="' . esc_attr($key) . '" id="' . esc_attr($uid.'_'.$key) . '" placeholder="' . $ph . '" ' . $req . '>';
    }

    $html .= $help . '</div>';
    return $html;
}

// ── Phone field with country code ──────────────────────────────────────────
function ssb_render_phone_with_country($name, $input_id, $req, $style_attr = '') {
    $countries = ssb_get_phone_countries();
    $html  = '<div class="sfb-phone-wrap" data-field="' . esc_attr($name) . '">';
    $html .= '<div class="sfb-phone-row">';
    // Country code dropdown
    $html .= '<div class="sfb-country-wrap">';
    $html .= '<button type="button" class="sfb-country-btn" aria-haspopup="listbox" aria-expanded="false">';
    $html .= '<span class="sfb-cc-flag">🌍</span>';
    $html .= '<span class="sfb-cc-dial">+1</span>';
    $html .= '<span class="sfb-cc-chevron">▾</span>';
    $html .= '</button>';
    $html .= '<div class="sfb-country-dropdown" role="listbox" style="display:none;">';
    $html .= '<div class="sfb-country-search-wrap"><input class="sfb-country-search" type="text" placeholder="Search country…" autocomplete="off"></div>';
    $html .= '<div class="sfb-country-list">';
    foreach ($countries as $c) {
        $html .= '<div class="sfb-country-option" data-dial="' . esc_attr($c['dial']) . '" data-flag="' . esc_attr($c['flag']) . '" data-name="' . esc_attr($c['name']) . '" role="option">';
        $html .= '<span class="sfb-co-flag">' . $c['flag'] . '</span>';
        $html .= '<span class="sfb-co-name">' . esc_html($c['name']) . '</span>';
        $html .= '<span class="sfb-co-dial">' . esc_html($c['dial']) . '</span>';
        $html .= '</div>';
    }
    $html .= '</div></div>';
    $html .= '<input type="hidden" name="' . esc_attr($name) . '_country" class="sfb-cc-hidden" value="+1">';
    $html .= '</div>'; // country-wrap
    // Phone input
    $html .= '<input type="tel" class="sfb-input sfb-phone-input"' . $style_attr . ' id="' . esc_attr($input_id) . '" name="' . esc_attr($name) . '" placeholder="Phone number" autocomplete="tel" ' . $req . '>';
    $html .= '</div>'; // phone-row
    $html .= '<div class="sfb-phone-error" style="display:none;"></div>';
    $html .= '</div>'; // phone-wrap
    return $html;
}

// ── Timezone select ────────────────────────────────────────────────────────
function ssb_render_timezone_select($name, $input_id, $req, $style_attr = '') {
    $zones = [
        'UTC-12:00' => ['Baker Island Time', 'UTC-12:00'],
        'UTC-11:00' => ['Samoa Standard Time', 'UTC-11:00'],
        'UTC-10:00' => ['Hawaii-Aleutian Standard Time', 'UTC-10:00'],
        'UTC-09:00' => ['Alaska Standard Time', 'UTC-09:00'],
        'UTC-08:00' => ['Pacific Time (US & Canada)', 'UTC-08:00'],
        'UTC-07:00' => ['Mountain Time (US & Canada)', 'UTC-07:00'],
        'UTC-06:00' => ['Central Time (US & Canada)', 'UTC-06:00'],
        'UTC-05:00' => ['Eastern Time (US & Canada)', 'UTC-05:00'],
        'UTC-04:00' => ['Atlantic Time / Venezuela', 'UTC-04:00'],
        'UTC-03:00' => ['Brazil / Argentina', 'UTC-03:00'],
        'UTC-02:00' => ['South Georgia', 'UTC-02:00'],
        'UTC-01:00' => ['Azores / Cape Verde', 'UTC-01:00'],
        'UTC+00:00' => ['London / Dublin / Lisbon (GMT/UTC)', 'UTC+00:00'],
        'UTC+01:00' => ['Central Europe / West Africa', 'UTC+01:00'],
        'UTC+02:00' => ['Eastern Europe / South Africa', 'UTC+02:00'],
        'UTC+03:00' => ['Moscow / East Africa / Arabia', 'UTC+03:00'],
        'UTC+03:30' => ['Iran Standard Time', 'UTC+03:30'],
        'UTC+04:00' => ['Gulf / Azerbaijan / Mauritius', 'UTC+04:00'],
        'UTC+04:30' => ['Afghanistan Time', 'UTC+04:30'],
        'UTC+05:00' => ['Pakistan / Uzbekistan', 'UTC+05:00'],
        'UTC+05:30' => ['India Standard Time', 'UTC+05:30'],
        'UTC+05:45' => ['Nepal Time', 'UTC+05:45'],
        'UTC+06:00' => ['Bangladesh / Bhutan / Omsk', 'UTC+06:00'],
        'UTC+06:30' => ['Cocos Islands / Myanmar', 'UTC+06:30'],
        'UTC+07:00' => ['Indochina / West Indonesia / Bangkok', 'UTC+07:00'],
        'UTC+08:00' => ['China / Singapore / Perth / Philippines', 'UTC+08:00'],
        'UTC+09:00' => ['Japan / Korea / East Indonesia', 'UTC+09:00'],
        'UTC+09:30' => ['Australia Central Time', 'UTC+09:30'],
        'UTC+10:00' => ['Australia Eastern / Papua New Guinea', 'UTC+10:00'],
        'UTC+11:00' => ['Vladivostok / Solomon Islands', 'UTC+11:00'],
        'UTC+12:00' => ['New Zealand / Fiji / Kamchatka', 'UTC+12:00'],
        'UTC+13:00' => ['Tonga / Samoa', 'UTC+13:00'],
    ];

    $html  = '<select class="sfb-select sfb-timezone-select"' . $style_attr . ' name="' . esc_attr($name) . '" id="' . esc_attr($input_id) . '" ' . $req . '>';
    $html .= '<option value="">Select your timezone…</option>';
    foreach ($zones as $val => $info) {
        $html .= '<option value="' . esc_attr($val) . '">' . esc_html($info[0]) . ' (' . esc_html($info[1]) . ')</option>';
    }
    $html .= '</select>';

    // Auto-detect timezone JS (outputs selected value on page load)
    $html .= '<script>
    (function(){
        var sel = document.getElementById(' . json_encode($input_id) . ');
        if (!sel) return;
        try {
            var tz = Intl.DateTimeFormat().resolvedOptions().timeZone;
            var offset = -new Date().getTimezoneOffset();
            var sign = offset >= 0 ? "+" : "-";
            var abs = Math.abs(offset);
            var h = String(Math.floor(abs/60)).padStart(2,"0");
            var m = String(abs%60).padStart(2,"0");
            var utcKey = "UTC" + sign + h + ":" + m;
            var opts = sel.options;
            for (var i=0;i<opts.length;i++){
                if (opts[i].value === utcKey){ sel.selectedIndex=i; break; }
            }
        } catch(e){}
    })();
    </script>';

    return $html;
}

// ── Country phone codes ────────────────────────────────────────────────────
function ssb_get_phone_countries() {
    return [
        ['name'=>'Afghanistan',          'dial'=>'+93',  'flag'=>'🇦🇫'],
        ['name'=>'Albania',              'dial'=>'+355', 'flag'=>'🇦🇱'],
        ['name'=>'Algeria',              'dial'=>'+213', 'flag'=>'🇩🇿'],
        ['name'=>'Argentina',            'dial'=>'+54',  'flag'=>'🇦🇷'],
        ['name'=>'Australia',            'dial'=>'+61',  'flag'=>'🇦🇺'],
        ['name'=>'Austria',              'dial'=>'+43',  'flag'=>'🇦🇹'],
        ['name'=>'Bangladesh',           'dial'=>'+880', 'flag'=>'🇧🇩'],
        ['name'=>'Belgium',              'dial'=>'+32',  'flag'=>'🇧🇪'],
        ['name'=>'Brazil',               'dial'=>'+55',  'flag'=>'🇧🇷'],
        ['name'=>'Canada',               'dial'=>'+1',   'flag'=>'🇨🇦'],
        ['name'=>'Chile',                'dial'=>'+56',  'flag'=>'🇨🇱'],
        ['name'=>'China',                'dial'=>'+86',  'flag'=>'🇨🇳'],
        ['name'=>'Colombia',             'dial'=>'+57',  'flag'=>'🇨🇴'],
        ['name'=>'Czech Republic',       'dial'=>'+420', 'flag'=>'🇨🇿'],
        ['name'=>'Denmark',              'dial'=>'+45',  'flag'=>'🇩🇰'],
        ['name'=>'Egypt',                'dial'=>'+20',  'flag'=>'🇪🇬'],
        ['name'=>'Finland',              'dial'=>'+358', 'flag'=>'🇫🇮'],
        ['name'=>'France',               'dial'=>'+33',  'flag'=>'🇫🇷'],
        ['name'=>'Germany',              'dial'=>'+49',  'flag'=>'🇩🇪'],
        ['name'=>'Ghana',                'dial'=>'+233', 'flag'=>'🇬🇭'],
        ['name'=>'Greece',               'dial'=>'+30',  'flag'=>'🇬🇷'],
        ['name'=>'Hong Kong',            'dial'=>'+852', 'flag'=>'🇭🇰'],
        ['name'=>'Hungary',              'dial'=>'+36',  'flag'=>'🇭🇺'],
        ['name'=>'India',                'dial'=>'+91',  'flag'=>'🇮🇳'],
        ['name'=>'Indonesia',            'dial'=>'+62',  'flag'=>'🇮🇩'],
        ['name'=>'Iran',                 'dial'=>'+98',  'flag'=>'🇮🇷'],
        ['name'=>'Iraq',                 'dial'=>'+964', 'flag'=>'🇮🇶'],
        ['name'=>'Ireland',              'dial'=>'+353', 'flag'=>'🇮🇪'],
        ['name'=>'Israel',               'dial'=>'+972', 'flag'=>'🇮🇱'],
        ['name'=>'Italy',                'dial'=>'+39',  'flag'=>'🇮🇹'],
        ['name'=>'Japan',                'dial'=>'+81',  'flag'=>'🇯🇵'],
        ['name'=>'Jordan',               'dial'=>'+962', 'flag'=>'🇯🇴'],
        ['name'=>'Kenya',                'dial'=>'+254', 'flag'=>'🇰🇪'],
        ['name'=>'South Korea',          'dial'=>'+82',  'flag'=>'🇰🇷'],
        ['name'=>'Kuwait',               'dial'=>'+965', 'flag'=>'🇰🇼'],
        ['name'=>'Lebanon',              'dial'=>'+961', 'flag'=>'🇱🇧'],
        ['name'=>'Malaysia',             'dial'=>'+60',  'flag'=>'🇲🇾'],
        ['name'=>'Mexico',               'dial'=>'+52',  'flag'=>'🇲🇽'],
        ['name'=>'Morocco',              'dial'=>'+212', 'flag'=>'🇲🇦'],
        ['name'=>'Netherlands',          'dial'=>'+31',  'flag'=>'🇳🇱'],
        ['name'=>'New Zealand',          'dial'=>'+64',  'flag'=>'🇳🇿'],
        ['name'=>'Nigeria',              'dial'=>'+234', 'flag'=>'🇳🇬'],
        ['name'=>'Norway',               'dial'=>'+47',  'flag'=>'🇳🇴'],
        ['name'=>'Oman',                 'dial'=>'+968', 'flag'=>'🇴🇲'],
        ['name'=>'Pakistan',             'dial'=>'+92',  'flag'=>'🇵🇰'],
        ['name'=>'Philippines',          'dial'=>'+63',  'flag'=>'🇵🇭'],
        ['name'=>'Poland',               'dial'=>'+48',  'flag'=>'🇵🇱'],
        ['name'=>'Portugal',             'dial'=>'+351', 'flag'=>'🇵🇹'],
        ['name'=>'Qatar',                'dial'=>'+974', 'flag'=>'🇶🇦'],
        ['name'=>'Romania',              'dial'=>'+40',  'flag'=>'🇷🇴'],
        ['name'=>'Russia',               'dial'=>'+7',   'flag'=>'🇷🇺'],
        ['name'=>'Saudi Arabia',         'dial'=>'+966', 'flag'=>'🇸🇦'],
        ['name'=>'Singapore',            'dial'=>'+65',  'flag'=>'🇸🇬'],
        ['name'=>'South Africa',         'dial'=>'+27',  'flag'=>'🇿🇦'],
        ['name'=>'Spain',                'dial'=>'+34',  'flag'=>'🇪🇸'],
        ['name'=>'Sri Lanka',            'dial'=>'+94',  'flag'=>'🇱🇰'],
        ['name'=>'Sweden',               'dial'=>'+46',  'flag'=>'🇸🇪'],
        ['name'=>'Switzerland',          'dial'=>'+41',  'flag'=>'🇨🇭'],
        ['name'=>'Syria',                'dial'=>'+963', 'flag'=>'🇸🇾'],
        ['name'=>'Taiwan',               'dial'=>'+886', 'flag'=>'🇹🇼'],
        ['name'=>'Thailand',             'dial'=>'+66',  'flag'=>'🇹🇭'],
        ['name'=>'Turkey',               'dial'=>'+90',  'flag'=>'🇹🇷'],
        ['name'=>'UAE',                  'dial'=>'+971', 'flag'=>'🇦🇪'],
        ['name'=>'Ukraine',              'dial'=>'+380', 'flag'=>'🇺🇦'],
        ['name'=>'United Kingdom',       'dial'=>'+44',  'flag'=>'🇬🇧'],
        ['name'=>'United States',        'dial'=>'+1',   'flag'=>'🇺🇸'],
        ['name'=>'Vietnam',              'dial'=>'+84',  'flag'=>'🇻🇳'],
        ['name'=>'Yemen',                'dial'=>'+967', 'flag'=>'🇾🇪'],
    ];
}
