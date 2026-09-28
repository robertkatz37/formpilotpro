<?php
if ( ! defined( 'ABSPATH' ) ) exit;

// Form Builder is registered centrally beneath FormPilot Pro.

// ── Admin assets ───────────────────────────────────────────────────────────
add_action( 'admin_enqueue_scripts', 'ssb_forms_admin_assets' );
function ssb_forms_admin_assets( $hook ) {
    if ( strpos($hook, 'ssb-') === false ) return;
    wp_enqueue_script( 'jquery-ui-sortable' );
    wp_enqueue_script( 'jquery-ui-draggable' );
    wp_enqueue_script( 'ssb-forms-admin', SSB_PLUGIN_URL . 'assets/js/forms-admin.js', ['jquery','jquery-ui-sortable'], SSB_VERSION, true );
    wp_localize_script( 'ssb-forms-admin', 'ssbForms', [
        'ajaxUrl' => admin_url('admin-ajax.php'),
        'nonce'   => wp_create_nonce('ssb_forms_nonce'),
        'siteUrl' => get_site_url(),
    ]);
    // Plus Jakarta Sans font
    wp_enqueue_style( 'ssb-jakarta-font', 'https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap', [], null );
    wp_add_inline_style( 'ssb-jakarta-font', ssb_forms_admin_css() );
}

function ssb_forms_admin_css() { return '

    .ssb-inspector-section-title{margin:22px 0 10px;padding-top:12px;border-top:1px solid #eef2f7;font-size:.72rem;font-weight:800;text-transform:uppercase;letter-spacing:.08em;color:#667eea;}
    .ssb-integration-panel{margin:10px 0 4px;padding:10px;background:#f8fafc;border:1px solid #e5e7eb;border-radius:8px;display:none;}
    .ssb-integration-panel .ssb-inspector-label{margin-top:8px;}
    .ssb-inspector-note{font-size:.72rem;color:#6b7280;line-height:1.5;margin:7px 0 0;}
    .ssb-style-grid{display:grid;grid-template-columns:1fr 1fr;gap:10px;}
    .ssb-style-grid>div{min-width:0;}
    .ssb-style-color{width:42px;height:38px;padding:3px;border:1px solid #e5e7eb;border-radius:6px;background:#fff;} .ssb-color-control{display:flex;gap:6px;align-items:center}.ssb-color-control .ssb-color-hex{flex:1;min-width:0}.ssb-color-picker{flex:0 0 42px}
    .ssb-csv-import{margin-top:8px;padding:10px;background:#f8fafc;border:1px dashed #cbd5e1;border-radius:8px;}
    .ssb-csv-import small{display:block;color:#64748b;margin-top:5px;line-height:1.4;}
    .ssb-fb-wrap, .ssb-builder, .ssb-builder * { font-family: "Plus Jakarta Sans", -apple-system, BlinkMacSystemFont, sans-serif !important; }
    .ssb-builder .dashicons, .ssb-builder .dashicons:before, .ssb-fb-wrap .dashicons, .ssb-fb-wrap .dashicons:before { font-family: dashicons !important; }
    #ssb-builder-toast{display:none;position:fixed;right:28px;bottom:78px;z-index:99999;min-width:260px;max-width:420px;padding:12px 16px;border-radius:12px;color:#fff;font-size:13px;font-weight:700;box-shadow:0 16px 40px rgba(15,23,42,.22)}#ssb-builder-toast.success{background:#059669}#ssb-builder-toast.error{background:#dc2626}.ssb-payment-link-note a{color:#4f46e5;font-weight:700;text-decoration:none}.ssb-payment-link-note a:hover{text-decoration:underline}
    .ssb-fb-wrap { max-width:none; margin-right:20px; }
    .ssb-form-cards { display:grid; grid-template-columns:repeat(auto-fill,minmax(300px,1fr)); gap:20px; margin-top:20px; }
    .ssb-form-card { background:#fff; border:1px solid #e5e7eb; border-radius:12px; padding:20px; box-shadow:0 2px 8px rgba(0,0,0,.05); transition:box-shadow .2s; }
    .ssb-form-card:hover { box-shadow:0 4px 16px rgba(0,0,0,.1); }
    .ssb-form-card-title { font-size:1.1rem; font-weight:700; color:#1f2937; margin:0 0 6px; }
    .ssb-form-card-meta { font-size:.8rem; color:#6b7280; margin-bottom:12px; }
    .ssb-form-card-sc { background:#f3f4f6; border-radius:6px; padding:6px 10px; font-family:monospace; font-size:.85rem; color:#374151; margin-bottom:14px; cursor:copy; border:1px dashed #d1d5db; }
    .ssb-form-card-sc:hover { background:#e5e7eb; }
    .ssb-form-card-actions { display:flex; gap:8px; flex-wrap:wrap; }

    /* Builder layout */
    .ssb-builder { display:grid; grid-template-columns:210px minmax(460px,1fr) 390px; gap:0; height:calc(100vh - 100px); min-height:600px; border:1px solid #e5e7eb; border-radius:12px; overflow:hidden; background:#fff; margin-top:16px; }
    .ssb-builder-palette { background:#1f2937; padding:16px; overflow-y:auto; }
    .ssb-builder-palette h3 { color:#9ca3af; font-size:.7rem; text-transform:uppercase; letter-spacing:1px; margin:0 0 10px; }
    .ssb-palette-item { background:#374151; border:1px solid #4b5563; color:#f9fafb; padding:10px 14px; border-radius:8px; margin-bottom:8px; cursor:grab; display:flex; align-items:center; gap:10px; font-size:.875rem; font-weight:600; transition:all .15s; user-select:none; }
    .ssb-palette-item:hover { background:#4b5563; border-color:#667eea; }
    .ssb-palette-item .pi { font-size:1.1rem; }

    .ssb-builder-canvas { background:#f7f8fc; padding:24px; overflow-y:auto; min-width:0; }
    .ssb-canvas-header { margin-bottom:16px; }
    .ssb-canvas-header input { font-size:1.4rem; font-weight:700; border:none; background:transparent; width:100%; outline:none; color:#1f2937; border-bottom:2px dashed #e5e7eb; padding-bottom:6px; }
    .ssb-canvas-header input:focus { border-bottom-color:#667eea; }
    .ssb-canvas-header textarea { font-size:.9rem; border:none; background:transparent; width:100%; outline:none; color:#6b7280; resize:none; margin-top:4px; }

    .ssb-drop-zone { min-height:300px; border:2px dashed #e5e7eb; border-radius:10px; padding:16px; transition:all .2s; }
    .ssb-drop-zone.drag-over { border-color:#667eea; background:rgba(102,126,234,.05); } .ssb-drop-zone.drag-ready { border-color:#667eea; background:rgba(102,126,234,.04); } .ssb-field-placeholder{border:2px dashed #667eea!important;background:rgba(102,126,234,.06)!important;min-height:58px;border-radius:10px;margin-bottom:10px;list-style:none;} .ssb-repeat-children{display:flex;flex-direction:column;gap:6px;margin-top:6px}.ssb-repeat-child{display:flex;align-items:center;gap:7px;padding:8px 9px;border:1px solid #e5e7eb;border-radius:7px;background:#f8fafc;cursor:grab}.ssb-repeat-child-label{flex:1;min-width:0;border:1px solid #e5e7eb;border-radius:5px;padding:5px 7px;font-size:12px}.ssb-repeat-child-required{font-size:10px;color:#64748b;white-space:nowrap}.ssb-repeat-child-required input{margin:0 3px 0 0}.ssb-repeat-child-handle{color:#94a3b8;cursor:grab}.ssb-repeat-child-type{font-size:10px;color:#94a3b8;margin-left:auto}.ssb-repeat-child-del{border:0;background:transparent;color:#ef4444;cursor:pointer}.ssb-repeat-add-row{display:grid;grid-template-columns:1fr auto;gap:7px;margin-top:8px}.ssb-repeat-add-row .button{min-height:36px}
    .ssb-drop-placeholder { text-align:center; padding:60px 20px; color:#9ca3af; }
    .ssb-drop-placeholder svg { width:48px; height:48px; margin:0 auto 12px; display:block; opacity:.4; }

    .ssb-field-block { background:#fff; border:2px solid #e5e7eb; border-radius:10px; padding:14px; margin-bottom:10px; cursor:grab; position:relative; transition:all .2s; }
    .ssb-field-block:hover, .ssb-field-block.active { border-color:#667eea; box-shadow:0 0 0 3px rgba(102,126,234,.1); }
    .ssb-field-block.ssb-ui-sortable-helper { box-shadow:0 8px 24px rgba(0,0,0,.15); opacity:.9; }
    .ssb-field-block-header { display:flex; align-items:center; gap:8px; }
    .ssb-field-drag-handle { color:#9ca3af; cursor:grab; font-size:1.1rem; padding:2px 4px; }
    .ssb-field-type-badge { background:#f3f4f6; border-radius:4px; padding:2px 8px; font-size:.7rem; font-weight:700; color:#6b7280; text-transform:uppercase; letter-spacing:.5px; }
    .ssb-field-label-preview { flex:1; font-weight:600; font-size:.9rem; color:#374151; }
    .ssb-field-required-dot { width:8px; height:8px; border-radius:50%; background:#ef4444; display:none; }
    .ssb-field-required-dot.visible { display:block; }
    .ssb-field-actions { display:flex; gap:4px; margin-left:auto; }
    .ssb-field-btn { background:none; border:none; cursor:pointer; padding:4px 6px; border-radius:4px; font-size:.85rem; color:#9ca3af; transition:all .15s; }
    .ssb-field-btn:hover { background:#f3f4f6; color:#374151; }
    .ssb-field-btn.del:hover { background:#fef2f2; color:#ef4444; }

    .ssb-field-half-row { display:grid; grid-template-columns:1fr 1fr; gap:10px; }

    /* Inspector */
    .ssb-builder-inspector { background:#fff; border-left:1px solid #e5e7eb; padding:0; overflow-y:auto; min-width:0; box-shadow:-8px 0 24px rgba(15,23,42,.035); }
    .ssb-inspector-header { padding:14px 16px; border-bottom:1px solid #e5e7eb; background:#f9fafb; }
    .ssb-inspector-header h3 { margin:0; font-size:.9rem; font-weight:700; color:#374151; }
    .ssb-inspector-body { padding:20px; }
    .ssb-inspector-field{background:#fff}.ssb-style-grid{gap:12px}.ssb-color-control{background:#f8fafc;padding:4px;border:1px solid #edf0f4;border-radius:8px}.ssb-color-control .ssb-color-hex{background:#fff}.ssb-style-color{width:44px;height:40px}.ssb-inspector-section-title{margin-top:24px;padding-top:16px}
    .ssb-inspector-empty { padding:40px 20px; text-align:center; color:#9ca3af; font-size:.875rem; }
    .ssb-inspector-field { margin-bottom:14px; }
    .ssb-inspector-label { display:block; font-size:.75rem; font-weight:700; color:#6b7280; text-transform:uppercase; letter-spacing:.5px; margin-bottom:5px; }
    .ssb-inspector-input { width:100%; box-sizing:border-box; min-width:0; padding:7px 10px; border:1.5px solid #e5e7eb; border-radius:6px; font-size:.875rem; background:#fff; }
    .ssb-inspector-input:focus { outline:none; border-color:#667eea; }
    .ssb-inspector-toggle { display:flex; align-items:center; gap:10px; }
    .ssb-inspector-toggle input[type=checkbox] { width:16px; height:16px; accent-color:#667eea; }
    .ssb-options-list { margin-top:6px; }
    .ssb-option-row { display:flex; gap:6px; margin-bottom:6px; }
    .ssb-option-row input { flex:1; padding:6px 8px; border:1.5px solid #e5e7eb; border-radius:6px; font-size:.8rem; }
    .ssb-option-del { background:none; border:none; cursor:pointer; color:#ef4444; font-size:1rem; padding:2px 6px; }
    .ssb-add-option { background:none; border:1.5px dashed #e5e7eb; border-radius:6px; padding:6px; width:100%; cursor:pointer; color:#6b7280; font-size:.8rem; transition:all .15s; }
    .ssb-add-option:hover { border-color:#667eea; color:#667eea; }

    .ssb-width-toggle { display:flex; gap:6px; }
    .ssb-width-btn { flex:1; padding:6px; border:1.5px solid #e5e7eb; border-radius:6px; text-align:center; cursor:pointer; font-size:.8rem; font-weight:600; color:#6b7280; transition:all .15s; }
    .ssb-width-btn.active { border-color:#667eea; background:#667eea; color:#fff; }
    .ssb-button-align-control{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:6px;}
    .ssb-button-align-btn{min-width:0;min-height:58px;padding:7px 5px;border:1.5px solid #e5e7eb;border-radius:7px;background:#fff;color:#64748b;cursor:pointer;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:4px;transition:all .15s;font:inherit;}
    .ssb-button-align-btn .dashicons{font-size:18px;width:18px;height:18px;line-height:18px;font-family:dashicons!important;}
    .ssb-button-align-btn .ssb-button-align-text{font-size:10px;font-weight:700;line-height:1.1;}
    .ssb-button-align-btn:hover{border-color:#667eea;color:#667eea;background:#f8faff;}
    .ssb-button-align-btn.active{border-color:#667eea;background:#667eea;color:#fff;box-shadow:0 0 0 2px rgba(102,126,234,.12);}

    /* Form settings tabs in inspector */
    .ssb-inspector-tabs { display:flex; border-bottom:1px solid #e5e7eb; }
    .ssb-inspector-tab { flex:1; padding:10px; text-align:center; font-size:.8rem; font-weight:600; cursor:pointer; color:#6b7280; border-bottom:2px solid transparent; transition:all .15s; }
    .ssb-inspector-tab.active { color:#667eea; border-bottom-color:#667eea; }

    /* Save bar */
    .ssb-save-bar { position:fixed; bottom:0; left:160px; right:0; background:#1f2937; padding:12px 24px; display:flex; align-items:center; justify-content:space-between; z-index:100; box-shadow:0 -4px 16px rgba(0,0,0,.15); }
    .ssb-save-bar-info { color:#9ca3af; font-size:.875rem; }
    .ssb-save-bar-info strong { color:#fff; }
    .ssb-save-btn { background:linear-gradient(135deg,#667eea,#764ba2); color:#fff; border:none; padding:10px 28px; border-radius:8px; font-weight:700; font-size:.95rem; cursor:pointer; transition:all .2s; }
    .ssb-save-btn:hover { transform:translateY(-1px); box-shadow:0 4px 12px rgba(102,126,234,.4); }
    .ssb-save-btn:disabled { opacity:.6; cursor:not-allowed; transform:none; }

    @media (max-width: 1280px) {
        .ssb-builder { grid-template-columns:190px minmax(400px,1fr) 350px; }
    }
    @media (max-width: 1180px) {
        .ssb-builder { grid-template-columns:190px minmax(340px,1fr) 330px; }
        .ssb-style-grid { grid-template-columns:1fr; }
    }
    @media (max-width: 960px) {
        .ssb-builder { grid-template-columns:1fr; height:auto; min-height:0; overflow:visible; }
        .ssb-builder-palette { max-height:260px; }
        .ssb-builder-canvas { min-height:520px; }
        .ssb-builder-inspector { border-left:0; border-top:1px solid #e5e7eb; min-height:420px; }
        .ssb-save-bar { left:0; padding:10px 14px; }
        .ssb-save-bar-info { font-size:.75rem; }
    }

    /* Entries */
    .ssb-entries-table { width:100%; border-collapse:collapse; }
    .ssb-entries-table th { background:#f9fafb; padding:10px 14px; text-align:left; font-size:.8rem; text-transform:uppercase; letter-spacing:.5px; color:#6b7280; border-bottom:2px solid #e5e7eb; }
    .ssb-entries-table td { padding:10px 14px; border-bottom:1px solid #f3f4f6; font-size:.875rem; vertical-align:top; }
    .ssb-entries-table tr.unread td { background:#fffbeb; }
    .ssb-entry-badge { display:inline-block; padding:2px 8px; border-radius:10px; font-size:.7rem; font-weight:700; }
    .ssb-entry-badge.unread { background:#fef3c7; color:#92400e; }
    .ssb-entry-badge.read { background:#d1fae5; color:#065f46; }
    .ssb-entry-expand { cursor:pointer; color:#667eea; font-size:.8rem; text-decoration:underline; }
    .ssb-entry-detail { display:none; margin-top:8px; background:#f9fafb; border-radius:6px; padding:10px; }
    .ssb-entry-detail table { width:100%; border-collapse:collapse; }
    .ssb-entry-detail td { padding:4px 8px; border-bottom:1px solid #e5e7eb; font-size:.8rem; }
    .ssb-entry-detail td:first-child { font-weight:600; color:#667eea; width:35%; }
'; }

// ── Forms List Page ────────────────────────────────────────────────────────
function ssb_forms_list_page() {
    if ( function_exists('ssb_license_admin_guard') && ! ssb_license_admin_guard('forms') ) return;
    $action = sanitize_key( $_GET['ssb_action'] ?? '' );
    if ( isset($_GET['fp_upgrade']) ) echo '<div class="notice notice-info is-dismissible"><p><strong>FormPilot Pro:</strong> this workspace is available with a Pro license. Free includes Forms and Email & SMTP. <a href="'.esc_url(admin_url('admin.php?page=ssb-license')).'">Activate your license</a>.</p></div>';

    if ( $action === 'edit' || $action === 'new' ) {
        ssb_forms_builder_page();
        return;
    }
    if ( $action === 'entries' ) {
        ssb_forms_entries_page();
        return;
    }

    $forms = ssb_get_all_forms();
    global $wpdb;
    $is_pro = ssb_is_paid();
    $form_limit = (int) ssb_free_limits()['forms'];
    $form_count = count($forms);
    $at_free_limit = !$is_pro && $form_count >= $form_limit;
    ?>
    <div class="wrap ssb-fb-wrap">
        <div style="display:flex;align-items:center;justify-content:space-between;gap:16px;margin-bottom:4px;flex-wrap:wrap;">
            <div><h1 style="margin:0;"><span class="dashicons dashicons-editor-textcolor" aria-hidden="true"></span> Form Builder</h1><p style="color:#6b7280;margin:4px 0 0;">Create custom forms and embed them anywhere with a shortcode.</p></div>
            <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
                <?php if($is_pro): ?><a href="<?php echo esc_url(admin_url('admin.php?page=ssb-templates')); ?>" class="button">▦ Browse Form Templates</a><?php endif; ?> <a href="<?php echo esc_url(admin_url('admin.php?page=ssb-smart-booking-form')); ?>" class="button">⚙ Smart Schedule Booking</a>
                <?php if($at_free_limit): ?><a href="<?php echo esc_url(admin_url('admin.php?page=ssb-license')); ?>" class="button button-primary button-large">Upgrade to Create More</a><?php else: ?><a href="<?php echo esc_url(admin_url('admin.php?page=ssb-forms&ssb_action=new')); ?>" class="button button-primary button-large">+ Create New Form</a><?php endif; ?>
            </div>
        </div>
        <?php if(!$is_pro): ?>
            <div style="margin:18px 0;padding:14px 16px;border:1px solid #fde68a;background:#fffbeb;border-radius:12px;display:flex;justify-content:space-between;gap:14px;align-items:center;flex-wrap:wrap;">
                <div><strong>Free plan: <?php echo esc_html($form_count); ?>/<?php echo esc_html($form_limit); ?> forms used.</strong><div style="color:#92400e;font-size:12px;margin-top:3px;">Create up to 3 forms for free. Pro unlocks unlimited forms plus the complete Form Templates library.</div></div>
                <a class="button button-primary" href="<?php echo esc_url(admin_url('admin.php?page=ssb-license')); ?>">Unlock Pro</a>
            </div>
        <?php else: ?>
            <div style="margin:18px 0;padding:14px 16px;border:1px solid #dbeafe;background:#eff6ff;border-radius:12px;">
                <strong>Pro Form Library:</strong> Build from scratch or start with ready-made templates. <a href="<?php echo esc_url(admin_url('admin.php?page=ssb-templates')); ?>">Browse all form templates <span class="dashicons dashicons-arrow-right-alt2" aria-hidden="true"></span></a>
            </div>
        <?php endif; ?>

        <?php if ( empty($forms) ): ?>
            <div style="text-align:center;padding:80px 20px;background:#fff;border:1px solid #e5e7eb;border-radius:12px;margin-top:20px;">
                <span class="dashicons dashicons-forms" style="font-size:48px;width:48px;height:48px;margin-bottom:16px;"></span>
                <h2 style="color:#374151;">No forms yet</h2>
                <p style="color:#6b7280;">Create your first form to get started.</p>
                <a href="<?php echo admin_url('admin.php?page=ssb-forms&ssb_action=new'); ?>" class="button button-primary button-large">Create Your First Form</a>
            </div>
        <?php else: ?>
            <div class="ssb-form-cards">
                <?php
                    // Ensure entries table exists before counting
                    $entries_tbl = $wpdb->prefix . 'ssb_form_entries';
                    $entries_tbl_exists = (bool) $wpdb->get_var( "SHOW TABLES LIKE '$entries_tbl'" );
                    foreach ( $forms as $form ):
                    $entry_count = $entries_tbl_exists ? (int) $wpdb->get_var( $wpdb->prepare(
                        "SELECT COUNT(*) FROM {$wpdb->prefix}ssb_form_entries WHERE form_id = %d", $form->id
                    )) : 0;
                    $unread = $entries_tbl_exists ? (int) $wpdb->get_var( $wpdb->prepare(
                        "SELECT COUNT(*) FROM {$wpdb->prefix}ssb_form_entries WHERE form_id = %d AND status='unread'", $form->id
                    )) : 0;
                    $field_count = count($form->fields);
                    $shortcode = '[ssb_form id="' . $form->id . '"]';
                ?>
                <div class="ssb-form-card">
                    <div style="display:flex;align-items:start;justify-content:space-between;margin-bottom:6px;">
                        <h3 class="ssb-form-card-title"><?php echo esc_html($form->form_name); ?></h3>
                        <span style="background:<?php echo $form->status==='active' ? '#d1fae5' : '#fee2e2'; ?>;color:<?php echo $form->status==='active' ? '#065f46' : '#991b1b'; ?>;padding:2px 8px;border-radius:10px;font-size:.7rem;font-weight:700;"><?php echo ucfirst($form->status); ?></span>
                    </div>
                    <?php if ( $form->description ): ?>
                        <p style="font-size:.8rem;color:#6b7280;margin:0 0 8px;"><?php echo esc_html($form->description); ?></p>
                    <?php endif; ?>
                    <div class="ssb-form-card-meta">
                        <?php echo $field_count; ?> field<?php echo $field_count !== 1 ? 's' : ''; ?> &nbsp;·&nbsp;
                        <?php echo $entry_count; ?> submission<?php echo $entry_count !== 1 ? 's' : ''; ?>
                        <?php if ($unread): ?><span style="background:#fef3c7;color:#92400e;padding:1px 6px;border-radius:8px;font-size:.7rem;font-weight:700;"><?php echo $unread; ?> new</span><?php endif; ?>
                    </div>
                    <div class="ssb-form-card-sc" title="Click to copy shortcode" onclick="navigator.clipboard.writeText(this.textContent);this.style.background='#d1fae5';setTimeout(()=>this.style.background='',1000);">
                        <?php echo esc_html($shortcode); ?>
                    </div>
                    <div class="ssb-form-card-actions">
                        <a href="<?php echo admin_url('admin.php?page=ssb-forms&ssb_action=edit&form_id=' . $form->id); ?>" class="button button-small">✏️ Edit</a>
                        <a href="<?php echo admin_url('admin.php?page=ssb-forms&ssb_action=entries&form_id=' . $form->id); ?>" class="button button-small"><span class="dashicons dashicons-download" aria-hidden="true"></span> Entries (<?php echo $entry_count; ?>)</a>
                        <button class="button button-small ssb-delete-form" data-id="<?php echo $form->id; ?>" data-name="<?php echo esc_attr($form->form_name); ?>" style="color:#dc2626;"><span class="dashicons dashicons-trash" aria-hidden="true"></span> Delete</button>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <script>
    jQuery(document).ready(function($){
        $('.ssb-delete-form').on('click', function(){
            var name = $(this).data('name');
            var id   = $(this).data('id');
            if (!confirm('Delete form "' + name + '" and ALL its submissions? This cannot be undone.')) return;
            $.post(ssbForms.ajaxUrl, { action:'ssb_delete_form', nonce:ssbForms.nonce, form_id:id }, function(r){
                if (r.success) location.reload();
                else alert(r.data.msg || 'Error');
            });
        });
    });
    </script>
    <?php
}

// ── Form Builder Page ──────────────────────────────────────────────────────
function ssb_forms_builder_page() {
    if ( function_exists('ssb_license_admin_guard') && ! ssb_license_admin_guard('builder') ) return;
    $form_id = intval( $_GET['form_id'] ?? 0 );
    $form    = $form_id ? ssb_get_form( $form_id ) : null;

    $form_name   = $form ? $form->form_name   : '';
    $description = $form ? $form->description : '';
    $fields_json = $form ? wp_json_encode( $form->fields )   : '[]';
    $settings    = $form ? $form->settings : [];
    $settings_json = wp_json_encode( $settings );
    ?>
    <div class="wrap ssb-fb-wrap" style="margin-right:0;">
        <div style="display:flex;align-items:center;gap:12px;margin-bottom:8px;">
            <a href="<?php echo admin_url('admin.php?page=ssb-forms'); ?>" style="color:#6b7280;text-decoration:none;font-size:.9rem;"><span class="dashicons dashicons-arrow-left-alt2" aria-hidden="true"></span> All Forms</a>
            <h1 style="margin:0;"><?php echo $form_id ? 'Edit Form' : 'Create New Form'; ?></h1>
            <?php if ($form_id): ?>
                <code style="background:#f3f4f6;padding:4px 10px;border-radius:6px;font-size:.85rem;">[ssb_form id="<?php echo $form_id; ?>"]</code>
            <?php endif; ?>
        </div>

        <div class="ssb-builder">

            <!-- Palette -->
            <div class="ssb-builder-palette">
                <h3>Field Types</h3><p style="font-size:.68rem;color:#9ca3af;line-height:1.5;margin:0 0 12px;">Click to add instantly, or drag any field into the exact position you want. Drag existing fields to reorder them. Changes are live; save when finished.</p>
                <?php
                $palette = [
                    ['text', 'editor-textcolor', 'Text Input'], ['email', 'email-alt', 'Email Address'], ['textarea', 'edit', 'Paragraph Text'], ['number', 'calculator', 'Number'],
                    ['select', 'menu', 'Select Dropdown'], ['radio', 'marker', 'Radio Buttons'], ['checkbox', 'yes-alt', 'Checkboxes'], ['toggle', 'admin-generic', 'Toggle / Switch'],
                    ['name', 'admin-users', 'Full Name'], ['phone', 'phone', 'Phone Number'], ['address', 'location', 'Address'], ['url', 'admin-links', 'Website / URL'],
                    ['file', 'paperclip', 'File Upload'], ['date', 'calendar-alt', 'Date'], ['time', 'clock', 'Time'], ['datetime', 'calendar', 'Date & Time'],
                    ['hidden', 'hidden', 'Hidden Field'], ['terms', 'yes', 'Terms & Privacy'], ['spam_protection', 'shield', 'Spam Protection'],
                    ['booking', 'calendar-alt', 'Calendar Booking'], ['timeslot', 'clock', 'Time Slots'], ['timezone', 'admin-site-alt3', 'Timezone'],
                    ['submit', 'yes', 'Submit Button'], ['heading', 'heading', 'Heading'], ['divider', 'minus', 'Divider'], ['page_break', 'arrow-right-alt2', 'Page Break / Step'], ['repeater', 'update', 'Repeater / Repeatable Group'],
                ];
                foreach ($palette as [$type, $icon, $label]): ?>
                    <div class="ssb-palette-item" data-type="<?php echo $type; ?>">
                        <span class="pi dashicons dashicons-<?php echo esc_attr($icon); ?>" aria-hidden="true"></span>
                        <span><?php echo $label; ?></span>
                    </div>
                <?php endforeach; ?>

                <h3 style="margin-top:24px;">Templates</h3>
                <div class="ssb-palette-item" data-template="contact" style="background:#1d4ed8;border-color:#2563eb;">
                    <span class="pi dashicons dashicons-email-alt" aria-hidden="true"></span><span>Contact Form</span>
                </div>
                <div class="ssb-palette-item" data-template="lead" style="background:#065f46;border-color:#059669;">
                    <span class="pi dashicons dashicons-chart-line" aria-hidden="true"></span><span>Lead Capture</span>
                </div>
                <div class="ssb-palette-item" data-template="survey" style="background:#7c3aed;border-color:#8b5cf6;">
                    <span class="pi dashicons dashicons-chart-bar" aria-hidden="true"></span><span>Survey</span>
                </div>
            </div>

            <!-- Canvas -->
            <div class="ssb-builder-canvas">
                <div class="ssb-canvas-header">
                    <label style="display:block;font-size:.72rem;font-weight:700;color:#6b7280;margin-bottom:4px;">Internal Form Name (not shown on website)</label>
                    <input type="text" id="ssb-form-name" placeholder="e.g. Consultation Booking" value="<?php echo esc_attr($form_name); ?>">
                    <textarea id="ssb-form-desc" rows="1" placeholder="Optional description…" style="width:100%;font-family:inherit;"><?php echo esc_textarea($description); ?></textarea>
                </div>

                <ul id="ssb-drop-zone" class="ssb-drop-zone">
                    <li class="ssb-drop-placeholder" id="ssb-empty-hint">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 4v16m8-8H4"/></svg>
                        Click a field type on the left to add it here,<br>or choose a template to start quickly.
                    </li>
                </ul>

                <!-- Preview pane -->
                <div style="margin-top:24px;padding:20px;background:#fff;border:1px solid #e5e7eb;border-radius:10px;">
                    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:16px;gap:10px;flex-wrap:wrap;">
                        <h3 style="margin:0;font-size:.9rem;color:#6b7280;text-transform:uppercase;letter-spacing:1px;">Live Preview</h3>
                        <div class="ssb-preview-device-tabs" role="tablist">
                            <button type="button" class="ssb-preview-device active" data-preview-device="desktop"><span class="dashicons dashicons-desktop" aria-hidden="true"></span></button>
                            <button type="button" class="ssb-preview-device" data-preview-device="tablet">▣</button>
                            <button type="button" class="ssb-preview-device" data-preview-device="mobile"><span class="dashicons dashicons-smartphone" aria-hidden="true"></span></button>
                        </div>
                    </div>
                    <div id="ssb-live-preview" class="ssb-live-preview-shell" data-preview-device="desktop" style="pointer-events:none;"></div>
                </div>
            </div>

            <?php $multistep_allowed = function_exists('ssb_license_can') ? ssb_license_can('multistep') : false; ?>
    <!-- Inspector -->
            <div class="ssb-builder-inspector">
                <div class="ssb-inspector-tabs">
                    <div class="ssb-inspector-tab active" data-tab="field">Field</div>
                    <div class="ssb-inspector-tab" data-tab="form">Form Settings</div>
                </div>

                <!-- Field inspector -->
                <div id="ssb-inspector-field" class="ssb-inspector-body">
                    <div class="ssb-inspector-empty">
                        <span class="dashicons dashicons-edit" style="font-size:28px;width:28px;height:28px;margin-bottom:8px;"></span>
                        Click a field to edit its properties
                    </div>
                </div>

                <!-- Form settings inspector -->
                <div id="ssb-inspector-form" class="ssb-inspector-body" style="display:none;">
                    <div class="ssb-inspector-section-title">Display & Identity</div>
                    <div class="ssb-inspector-field">
                        <label class="ssb-inspector-label">Display Title</label>
                        <input class="ssb-inspector-input" id="set-display-title" value="<?php echo esc_attr($settings['display_title'] ?? $form_name); ?>" placeholder="Title shown to visitors">
                        <p style="font-size:.75rem;color:#9ca3af;margin-top:4px;">Separate from the internal Form Name. Leave blank to use the Form Name.</p>
                    </div>
                    <div class="ssb-inspector-field">
                        <label class="ssb-inspector-toggle"><input type="checkbox" id="set-show-title" <?php checked(array_key_exists('show_title',$settings) ? !empty($settings['show_title']) : true); ?>> <span>Show form title on the website</span></label>
                    </div>
                    <div class="ssb-inspector-field">
                        <label class="ssb-inspector-toggle"><input type="checkbox" id="set-show-labels" <?php checked(array_key_exists('show_labels',$settings) ? !empty($settings['show_labels']) : true); ?>> <span>Show field labels on the website</span></label>
                    </div>
                    <div class="ssb-inspector-field">
                        <label class="ssb-inspector-toggle"><input type="checkbox" id="set-show-description" <?php checked(array_key_exists('show_description',$settings) ? !empty($settings['show_description']) : true); ?>> <span>Show form description on the website</span></label>
                    </div>
                    <div class="ssb-inspector-field">
                        <label class="ssb-inspector-label">Submit Button Label</label>
                        <input class="ssb-inspector-input" id="set-submit-label" value="<?php echo esc_attr($settings['submit_label'] ?? 'Submit'); ?>">
                    </div>
                    <div class="ssb-inspector-section-title">Multi-Step Form</div>
                    <?php if ($multistep_allowed): ?>
                    <div class="ssb-inspector-field">
                        <label class="ssb-inspector-toggle"><input type="checkbox" id="set-multistep-enabled"> <span>Enable Multi-Step Form</span></label>
                        <p style="font-size:.75rem;color:#9ca3af;margin-top:4px;">Add Page Break / Step fields to divide the form. All fields remain in one DOM form and navigation does not reload the page.</p>
                    </div>
                    <div class="ssb-inspector-field">
                        <label class="ssb-inspector-label">Progress Display</label>
                        <select class="ssb-inspector-input" id="set-multistep-progress">
                            <option value="both">Step Nodes + Progress Bar</option>
                            <option value="steps">Step Nodes Only</option>
                            <option value="bar">Progress Bar Only</option>
                            <option value="none">No Progress Indicator</option>
                        </select>
                        <p style="font-size:.75rem;color:#9ca3af;margin-top:4px;">Choose both indicators, the top step nodes only, the bottom progress bar only, or hide indicators completely. Navigation and validation remain unchanged.</p>
                    </div>
                    <div class="ssb-style-grid">
                        <div><label class="ssb-inspector-label">Next Button</label><input class="ssb-inspector-input" id="set-multistep-next" value="Next"></div>
                        <div><label class="ssb-inspector-label">Previous Button</label><input class="ssb-inspector-input" id="set-multistep-prev" value="Previous"></div>
                    </div>
                    <div class="ssb-inspector-field">
                        <label class="ssb-inspector-label">Final Submit Button</label><input class="ssb-inspector-input" id="set-multistep-submit" value="Submit">
                    </div>
                    <?php else: ?>
                    <div class="fp-pro-locked-panel" style="padding:12px;border:1px solid #e5e7eb;border-radius:10px;background:#f8fafc;"><strong>Multi-Step Forms</strong><p style="margin:6px 0;color:#64748b;font-size:12px;">Multi-Step Forms are available with FormPilot Pro.</p><a class="button button-primary" href="<?php echo esc_url(admin_url('admin.php?page=ssb-license')); ?>">Activate Pro</a></div>
                    <?php endif; ?>
                    <div class="ssb-inspector-section-title">Multi-Step Navigation Buttons</div>
                    <p style="font-size:.75rem;color:#9ca3af;margin:-2px 0 12px;">Style the Next and Previous buttons independently. Final Submit continues to use the normal Submit Button design settings.</p>
                    <div class="ssb-inspector-field"><strong style="display:block;margin-bottom:8px;">Next Button Style</strong></div>
                    <div class="ssb-style-grid">
                        <div><label class="ssb-inspector-label">Background</label><input type="color" class="ssb-inspector-input" id="set-ms-next-bg" value="<?php echo esc_attr($settings['style']['ms_next_bg'] ?? '#0f172a'); ?>"></div>
                        <div><label class="ssb-inspector-label">Text Color</label><input type="color" class="ssb-inspector-input" id="set-ms-next-text" value="<?php echo esc_attr($settings['style']['ms_next_text'] ?? '#ffffff'); ?>"></div>
                    </div>
                    <div class="ssb-style-grid">
                        <div><label class="ssb-inspector-label">Hover Background</label><input type="color" class="ssb-inspector-input" id="set-ms-next-hover-bg" value="<?php echo esc_attr($settings['style']['ms_next_hover_bg'] ?? '#1e293b'); ?>"></div>
                        <div><label class="ssb-inspector-label">Border Color</label><input type="color" class="ssb-inspector-input" id="set-ms-next-border" value="<?php echo esc_attr($settings['style']['ms_next_border'] ?? '#0f172a'); ?>"></div>
                    </div>
                    <div class="ssb-style-grid">
                        <div><label class="ssb-inspector-label">Border Width (px)</label><input type="number" min="0" max="6" class="ssb-inspector-input" id="set-ms-next-border-width" value="<?php echo intval($settings['style']['ms_next_border_width'] ?? 0); ?>"></div>
                        <div><label class="ssb-inspector-label">Radius (px)</label><input type="number" min="0" max="60" class="ssb-inspector-input" id="set-ms-next-radius" value="<?php echo intval($settings['style']['ms_next_radius'] ?? 8); ?>"></div>
                    </div>
                    <div class="ssb-style-grid">
                        <div><label class="ssb-inspector-label">Vertical Padding</label><input type="number" min="4" max="40" class="ssb-inspector-input" id="set-ms-next-padding-y" value="<?php echo intval($settings['style']['ms_next_padding_y'] ?? 10); ?>"></div>
                        <div><label class="ssb-inspector-label">Horizontal Padding</label><input type="number" min="4" max="80" class="ssb-inspector-input" id="set-ms-next-padding-x" value="<?php echo intval($settings['style']['ms_next_padding_x'] ?? 22); ?>"></div>
                    </div>
                    <div class="ssb-inspector-field"><strong style="display:block;margin:10px 0 8px;">Previous Button Style</strong></div>
                    <div class="ssb-style-grid">
                        <div><label class="ssb-inspector-label">Background</label><input type="color" class="ssb-inspector-input" id="set-ms-prev-bg" value="<?php echo esc_attr($settings['style']['ms_prev_bg'] ?? '#f1f5f9'); ?>"></div>
                        <div><label class="ssb-inspector-label">Text Color</label><input type="color" class="ssb-inspector-input" id="set-ms-prev-text" value="<?php echo esc_attr($settings['style']['ms_prev_text'] ?? '#475569'); ?>"></div>
                    </div>
                    <div class="ssb-style-grid">
                        <div><label class="ssb-inspector-label">Hover Background</label><input type="color" class="ssb-inspector-input" id="set-ms-prev-hover-bg" value="<?php echo esc_attr($settings['style']['ms_prev_hover_bg'] ?? '#e2e8f0'); ?>"></div>
                        <div><label class="ssb-inspector-label">Border Color</label><input type="color" class="ssb-inspector-input" id="set-ms-prev-border" value="<?php echo esc_attr($settings['style']['ms_prev_border'] ?? '#e2e8f0'); ?>"></div>
                    </div>
                    <div class="ssb-style-grid">
                        <div><label class="ssb-inspector-label">Border Width (px)</label><input type="number" min="0" max="6" class="ssb-inspector-input" id="set-ms-prev-border-width" value="<?php echo intval($settings['style']['ms_prev_border_width'] ?? 1); ?>"></div>
                        <div><label class="ssb-inspector-label">Radius (px)</label><input type="number" min="0" max="60" class="ssb-inspector-input" id="set-ms-prev-radius" value="<?php echo intval($settings['style']['ms_prev_radius'] ?? 8); ?>"></div>
                    </div>
                    <div class="ssb-style-grid">
                        <div><label class="ssb-inspector-label">Vertical Padding</label><input type="number" min="4" max="40" class="ssb-inspector-input" id="set-ms-prev-padding-y" value="<?php echo intval($settings['style']['ms_prev_padding_y'] ?? 10); ?>"></div>
                        <div><label class="ssb-inspector-label">Horizontal Padding</label><input type="number" min="4" max="80" class="ssb-inspector-input" id="set-ms-prev-padding-x" value="<?php echo intval($settings['style']['ms_prev_padding_x'] ?? 22); ?>"></div>
                    </div>
                    <div class="ssb-inspector-field">
                        <label class="ssb-inspector-toggle"><input type="checkbox" id="set-ms-nav-full-width"> <span>Full-width navigation buttons on mobile</span></label>
                    </div>
                    <div class="ssb-inspector-section-title">Multi-Step Design</div>
                    <div class="ssb-inspector-field">
                        <label class="ssb-inspector-label">Progress Bar Color</label>
                        <input type="color" class="ssb-inspector-input" id="set-ms-progress-color" value="<?php echo esc_attr($settings['style']['ms_progress_color'] ?? '#667eea'); ?>">
                    </div>
                    <div class="ssb-style-grid">
                        <div><label class="ssb-inspector-label">Progress Track</label><input type="color" class="ssb-inspector-input" id="set-ms-track-color" value="<?php echo esc_attr($settings['style']['ms_track_color'] ?? '#e9edf5'); ?>"></div>
                        <div><label class="ssb-inspector-label">Connector Color</label><input type="color" class="ssb-inspector-input" id="set-ms-connector-color" value="<?php echo esc_attr($settings['style']['ms_connector_color'] ?? '#d9deea'); ?>"></div>
                    </div>
                    <div class="ssb-style-grid">
                        <div><label class="ssb-inspector-label">Active Step</label><input type="color" class="ssb-inspector-input" id="set-ms-active-color" value="<?php echo esc_attr($settings['style']['ms_active_color'] ?? '#667eea'); ?>"></div>
                        <div><label class="ssb-inspector-label">Completed Step</label><input type="color" class="ssb-inspector-input" id="set-ms-completed-color" value="<?php echo esc_attr($settings['style']['ms_completed_color'] ?? '#667eea'); ?>"></div>
                    </div>
                    <div class="ssb-style-grid">
                        <div><label class="ssb-inspector-label">Inactive Step</label><input type="color" class="ssb-inspector-input" id="set-ms-inactive-color" value="<?php echo esc_attr($settings['style']['ms_inactive_color'] ?? '#eef2f7'); ?>"></div>
                        <div><label class="ssb-inspector-label">Step Text</label><input type="color" class="ssb-inspector-input" id="set-ms-text-color" value="<?php echo esc_attr($settings['style']['ms_text_color'] ?? '#64748b'); ?>"></div>
                    </div>
                    <div class="ssb-style-grid">
                        <div><label class="ssb-inspector-label">Step Shape</label><select class="ssb-inspector-input" id="set-ms-shape"><option value="circle">Circle</option><option value="rounded">Rounded</option><option value="square">Square</option></select></div>
                        <div><label class="ssb-inspector-label">Indicator Size</label><input type="number" min="24" max="56" class="ssb-inspector-input" id="set-ms-size" value="30"></div>
                    </div>
                    <div class="ssb-style-grid">
                        <div><label class="ssb-inspector-label">Bar Height (px)</label><input type="number" min="3" max="20" class="ssb-inspector-input" id="set-ms-bar-height" value="8"></div>
                        <div><label class="ssb-inspector-label">Step Spacing (px)</label><input type="number" min="0" max="40" class="ssb-inspector-input" id="set-ms-spacing" value="10"></div>
                    </div>
                    <div class="ssb-style-grid">
                        <label class="ssb-inspector-toggle"><input type="checkbox" id="set-ms-show-percent" checked> <span>Show percentage</span></label>
                        <label class="ssb-inspector-toggle"><input type="checkbox" id="set-ms-show-step-label" checked> <span>Show Step X of Y</span></label>
                    </div>
                    <div class="ssb-style-grid">
                        <label class="ssb-inspector-toggle"><input type="checkbox" id="set-ms-show-titles" checked> <span>Show step titles</span></label>
                        <label class="ssb-inspector-toggle"><input type="checkbox" id="set-ms-clickable" checked> <span>Allow completed steps to be clicked</span></label>
                    </div>
                    <div class="ssb-inspector-field">
                        <label class="ssb-inspector-label">Animation</label>
                        <select class="ssb-inspector-input" id="set-ms-animation"><option value="slide">Slide / Fade</option><option value="fade">Fade</option><option value="none">None</option></select>
                    </div>
                    <div class="ssb-inspector-field">
                        <label class="ssb-inspector-label">Step Titles</label><p style="font-size:.75rem;color:#9ca3af;margin:0;">Select each Page Break in the canvas to edit its step title and optional description.</p>
                    </div>
                    <div class="ssb-inspector-field">
                        <label class="ssb-inspector-label">Success Message</label>
                        <textarea class="ssb-inspector-input" id="set-success-msg" rows="3"><?php echo esc_textarea($settings['success_msg'] ?? 'Thank you! Your message has been sent.'); ?></textarea>
                    </div>
                    <div class="ssb-inspector-field">
                        <label class="ssb-inspector-label">Notification Email</label>
                        <input class="ssb-inspector-input" type="text" id="set-notify-email" value="<?php echo esc_attr($settings['notify_email'] ?? get_option('admin_email')); ?>">
                        <p style="font-size:.75rem;color:#9ca3af;margin-top:4px;">Where to send submission alerts. Separate multiple emails with commas.</p>
                    </div>
                    <div class="ssb-inspector-field">
                        <label class="ssb-inspector-label">Email Subject / Title</label>
                        <input class="ssb-inspector-input" id="set-email-subject" value="<?php echo esc_attr($settings['email_subject'] ?? ''); ?>" placeholder="Leave blank to use form name">
                        <p style="font-size:.75rem;color:#9ca3af;margin-top:4px;">Custom subject sent with notification email. If blank, the form title is used.</p>
                    </div>
                    <div class="ssb-inspector-field">
                        <label class="ssb-inspector-toggle">
                            <input type="checkbox" id="set-send-copy" <?php checked(!empty($settings['send_copy'])); ?>>
                            <span style="font-size:.85rem;">Send copy to submitter's email</span>
                        </label>
                    </div>
                    <div class="ssb-inspector-field">
                        <label class="ssb-inspector-label">Redirect URL after submit</label>
                        <input class="ssb-inspector-input" type="url" id="set-redirect-url" value="<?php echo esc_attr($settings['redirect_url'] ?? ''); ?>" placeholder="https://... (leave blank for success msg)">
                    </div>
                    <div class="ssb-inspector-field">
                        <label class="ssb-inspector-label">Form Primary Color (override)</label>
                        <div class="ssb-color-control"><input class="ssb-inspector-input ssb-color-hex" type="text" id="set-primary-color" value="<?php echo esc_attr($settings['primary_color'] ?? get_option('ssb_primary_color','#667eea')); ?>" maxlength="7" placeholder="#667eea"><input type="color" class="ssb-style-color ssb-color-picker" data-color-for="set-primary-color" value="<?php echo esc_attr($settings['primary_color'] ?? get_option('ssb_primary_color','#667eea')); ?>"></div>
                    </div>
                    <?php if ( ssb_is_paid() ): ?>
                    <div class="ssb-inspector-section-title">Integrations</div>
                    <div class="ssb-inspector-field">
                        <label class="ssb-inspector-toggle"><input type="checkbox" id="set-stripe-enabled"> <span>Enable Stripe for this form</span></label>
                        <div id="ssb-stripe-settings" class="ssb-integration-panel">
                            <label class="ssb-inspector-label">Amount</label>
                            <input class="ssb-inspector-input" type="number" min="0" step="0.01" id="set-stripe-amount" value="0">
                            <label class="ssb-inspector-label">Currency</label>
                            <input class="ssb-inspector-input" id="set-stripe-currency" value="USD" maxlength="3">
                            <label class="ssb-inspector-label">Payment description</label>
                            <input class="ssb-inspector-input" id="set-stripe-description" value="" placeholder="Form payment">
                            <p class="ssb-inspector-note">Uses the Stripe API keys configured in the plugin. Set the amount to 0 to collect no payment.</p><p class="ssb-inspector-note ssb-payment-link-note"><a href="<?php echo esc_url(admin_url('admin.php?page=ssb-payment-buttons')); ?>">Create a Stripe Payment Button shortcode <span class="dashicons dashicons-arrow-right-alt2" aria-hidden="true"></span></a></p>
                        </div>
                    </div>
                    <div class="ssb-inspector-field">
                        <label class="ssb-inspector-toggle"><input type="checkbox" id="set-calendar-enabled"> <span>Enable Google Calendar for this form</span></label>
                        <div id="ssb-calendar-settings" class="ssb-integration-panel">
                            <label class="ssb-inspector-label">Calendar ID</label>
                            <input class="ssb-inspector-input" id="set-calendar-id" value="primary" placeholder="primary">
                            <label class="ssb-inspector-label">Event label</label>
                            <input class="ssb-inspector-input" id="set-calendar-label" value="" placeholder="Leave blank for global event label">
                            <label class="ssb-inspector-label">Brand name</label>
                            <input class="ssb-inspector-input" id="set-calendar-brand" value="" placeholder="Leave blank for website name">
                            <p class="ssb-inspector-note">For a real appointment, add the <strong>Calendar Booking</strong> field (recommended), or add Date + Time + Email fields. FormPilot checks the selected Google Calendar for conflicts, creates the event, stores the event/Meet links, and exposes the booking in Scheduling <span class="dashicons dashicons-arrow-right-alt2" aria-hidden="true"></span> Bookings.</p>
                        </div>
                    </div>

                    <div class="ssb-inspector-section-title">Form Appearance</div>
                    <div class="ssb-style-grid">
                        <div><label class="ssb-inspector-label">Form background</label><div class="ssb-color-control"><input type="text" class="ssb-color-hex ssb-inspector-input" id="style-form-bg" value="#ffffff" maxlength="7" placeholder="#000000"><input type="color" class="ssb-style-color ssb-color-picker" data-color-for="style-form-bg" value="#ffffff"></div><label class="ssb-inspector-toggle" style="margin-top:7px;"><input type="checkbox" id="style-form-bg-transparent"><span style="font-size:.8rem;">Transparent background</span></label></div>
                        <div><label class="ssb-inspector-label">Form border</label><div class="ssb-color-control"><input type="text" class="ssb-color-hex ssb-inspector-input" id="style-form-border" value="#edf0f3" maxlength="7" placeholder="#000000"><input type="color" class="ssb-style-color ssb-color-picker" data-color-for="style-form-border" value="#edf0f3"></div><label class="ssb-inspector-toggle" style="margin-top:7px;"><input type="checkbox" id="style-form-border-enabled" checked><span style="font-size:.8rem;">Show form border</span></label></div>
                        <div><label class="ssb-inspector-label">Form radius</label><input class="ssb-inspector-input" type="number" id="style-form-radius" min="0" max="60"></div>
                        <div><label class="ssb-inspector-label">Form padding</label><input class="ssb-inspector-input" type="number" id="style-form-padding" min="0" max="100"></div>
                        <div><label class="ssb-inspector-label">Field gap</label><input class="ssb-inspector-input" type="number" id="style-field-gap" min="0" max="60"></div>
                        <div><label class="ssb-inspector-label">Label color</label><div class="ssb-color-control"><input type="text" class="ssb-color-hex ssb-inspector-input" id="style-label-color" value="#6b7280" maxlength="7" placeholder="#000000"><input type="color" class="ssb-style-color ssb-color-picker" data-color-for="style-label-color" value="#6b7280"></div></div>
                        <div><label class="ssb-inspector-label">Section heading color</label><div class="ssb-color-control"><input type="text" class="ssb-color-hex ssb-inspector-input" id="style-heading-color" value="#667eea" maxlength="7" placeholder="#000000"><input type="color" class="ssb-style-color ssb-color-picker" data-color-for="style-heading-color" value="#667eea"></div><p class="ssb-inspector-note" style="margin:5px 0 0;">One color controls the heading text and its left border.</p></div>
                        <div><label class="ssb-inspector-label">Label size</label><input class="ssb-inspector-input" type="number" id="style-label-size" min="9" max="24"></div>
                        <div><label class="ssb-inspector-label">Input background</label><div class="ssb-color-control"><input type="text" class="ssb-color-hex ssb-inspector-input" id="style-input-bg" value="#f9fafb" maxlength="7" placeholder="#000000"><input type="color" class="ssb-style-color ssb-color-picker" data-color-for="style-input-bg" value="#f9fafb"></div></div>
                        <div><label class="ssb-inspector-label">Input text</label><div class="ssb-color-control"><input type="text" class="ssb-color-hex ssb-inspector-input" id="style-input-text" value="#111827" maxlength="7" placeholder="#000000"><input type="color" class="ssb-style-color ssb-color-picker" data-color-for="style-input-text" value="#111827"></div></div>
                        <div><label class="ssb-inspector-label">Input border</label><div class="ssb-color-control"><input type="text" class="ssb-color-hex ssb-inspector-input" id="style-input-border" value="#e5e7eb" maxlength="7" placeholder="#000000"><input type="color" class="ssb-style-color ssb-color-picker" data-color-for="style-input-border" value="#e5e7eb"></div></div>
                        <div><label class="ssb-inspector-label">Focus border</label><div class="ssb-color-control"><input type="text" class="ssb-color-hex ssb-inspector-input" id="style-input-focus-border" value="#667eea" maxlength="7" placeholder="#000000"><input type="color" class="ssb-style-color ssb-color-picker" data-color-for="style-input-focus-border" value="#667eea"></div></div>
                        <div><label class="ssb-inspector-label">Focus ring</label><div class="ssb-color-control"><input type="text" class="ssb-color-hex ssb-inspector-input" id="style-input-focus-ring" value="#dbeafe" maxlength="7" placeholder="#000000"><input type="color" class="ssb-style-color ssb-color-picker" data-color-for="style-input-focus-ring" value="#dbeafe"></div></div>
                        <div><label class="ssb-inspector-label">Error border</label><div class="ssb-color-control"><input type="text" class="ssb-color-hex ssb-inspector-input" id="style-input-error-border" value="#dc2626" maxlength="7" placeholder="#000000"><input type="color" class="ssb-style-color ssb-color-picker" data-color-for="style-input-error-border" value="#dc2626"></div></div>
                        <div><label class="ssb-inspector-label">Error background</label><div class="ssb-color-control"><input type="text" class="ssb-color-hex ssb-inspector-input" id="style-input-error-bg" value="#fef2f2" maxlength="7" placeholder="#000000"><input type="color" class="ssb-style-color ssb-color-picker" data-color-for="style-input-error-bg" value="#fef2f2"></div></div>
                        <div><label class="ssb-inspector-label">Placeholder color</label><div class="ssb-color-control"><input type="text" class="ssb-color-hex ssb-inspector-input" id="style-input-placeholder" value="#94a3b8" maxlength="7" placeholder="#000000"><input type="color" class="ssb-style-color ssb-color-picker" data-color-for="style-input-placeholder" value="#94a3b8"></div></div>
                        <div><label class="ssb-inspector-label">Input border width</label><input class="ssb-inspector-input" type="number" id="style-input-border-width" min="0" max="6"></div>
                        <div><label class="ssb-inspector-label">Input radius</label><input class="ssb-inspector-input" type="number" id="style-input-radius" min="0" max="60"></div>
                        <div><label class="ssb-inspector-label">Input padding</label><input class="ssb-inspector-input" type="number" id="style-input-padding" min="4" max="40"></div>
                        <div><label class="ssb-inspector-label">Input font size</label><input class="ssb-inspector-input" type="number" id="style-input-font-size" min="10" max="30"></div>
                    </div>

                    <div class="ssb-inspector-section-title">Responsive Design</div>
                    <div class="ssb-responsive-device-tabs" role="tablist">
                        <button type="button" class="ssb-responsive-device active" data-device="desktop"><span class="dashicons dashicons-desktop" aria-hidden="true"></span> Desktop</button>
                        <button type="button" class="ssb-responsive-device" data-device="tablet">▣ Tablet</button>
                        <button type="button" class="ssb-responsive-device" data-device="mobile"><span class="dashicons dashicons-smartphone" aria-hidden="true"></span> Mobile</button>
                    </div>
                    <p class="ssb-inspector-note">Click a device to set values specifically for that screen size. Mobile and tablet values are saved with this form and override the desktop values on the website.</p>
                    <div id="ssb-responsive-controls">
                        <div class="ssb-style-grid">
                            <div><label class="ssb-inspector-label">Form Padding</label><input class="ssb-inspector-input ssb-responsive-input" data-responsive-key="form_padding" type="number" min="0" max="100"></div>
                            <div><label class="ssb-inspector-label">Form Radius</label><input class="ssb-inspector-input ssb-responsive-input" data-responsive-key="form_radius" type="number" min="0" max="60"></div>
                            <div><label class="ssb-inspector-label">Field Gap</label><input class="ssb-inspector-input ssb-responsive-input" data-responsive-key="field_gap" type="number" min="0" max="60"></div>
                            <div><label class="ssb-inspector-label">Title Size</label><input class="ssb-inspector-input ssb-responsive-input" data-responsive-key="title_size" type="number" min="12" max="60"></div>
                            <div><label class="ssb-inspector-label">Description Size</label><input class="ssb-inspector-input ssb-responsive-input" data-responsive-key="description_size" type="number" min="10" max="32"></div>
                            <div><label class="ssb-inspector-label">Label Size</label><input class="ssb-inspector-input ssb-responsive-input" data-responsive-key="label_size" type="number" min="9" max="24"></div>
                            <div><label class="ssb-inspector-label">Input Padding</label><input class="ssb-inspector-input ssb-responsive-input" data-responsive-key="input_padding" type="number" min="4" max="40"></div>
                            <div><label class="ssb-inspector-label">Input Font Size</label><input class="ssb-inspector-input ssb-responsive-input" data-responsive-key="input_font_size" type="number" min="10" max="30"></div>
                            <div><label class="ssb-inspector-label">Button Font Size</label><input class="ssb-inspector-input ssb-responsive-input" data-responsive-key="button_font_size" type="number" min="10" max="30"></div>
                            <div><label class="ssb-inspector-label">Button Vertical Padding</label><input class="ssb-inspector-input ssb-responsive-input" data-responsive-key="button_padding_y" type="number" min="4" max="40"></div>
                            <div><label class="ssb-inspector-label">Button Horizontal Padding</label><input class="ssb-inspector-input ssb-responsive-input" data-responsive-key="button_padding_x" type="number" min="4" max="80"></div>
                            <div><label class="ssb-inspector-label">Step Indicator Size</label><input class="ssb-inspector-input ssb-responsive-input" data-responsive-key="ms_size" type="number" min="20" max="56"></div>
                            <div><label class="ssb-inspector-label">Progress Bar Height</label><input class="ssb-inspector-input ssb-responsive-input" data-responsive-key="ms_bar_height" type="number" min="3" max="20"></div>
                        </div>
                        <label class="ssb-inspector-toggle" style="margin-top:10px;"><input type="checkbox" class="ssb-responsive-input" data-responsive-key="ms_show_titles"> <span>Show step titles on this device</span></label>
                    </div>
                    <div class="ssb-inspector-section-title">Submit Button</div>
                    <div class="ssb-style-grid">
                        <div><label class="ssb-inspector-label">Background</label><div class="ssb-color-control"><input type="text" class="ssb-color-hex ssb-inspector-input" id="style-button-bg" value="#667eea" maxlength="7" placeholder="#000000"><input type="color" class="ssb-style-color ssb-color-picker" data-color-for="style-button-bg" value="#667eea"></div></div>
                        <div><label class="ssb-inspector-label">Text color</label><div class="ssb-color-control"><input type="text" class="ssb-color-hex ssb-inspector-input" id="style-button-text" value="#ffffff" maxlength="7" placeholder="#000000"><input type="color" class="ssb-style-color ssb-color-picker" data-color-for="style-button-text" value="#ffffff"></div></div>
                        <div><label class="ssb-inspector-label">Hover background</label><div class="ssb-color-control"><input type="text" class="ssb-color-hex ssb-inspector-input" id="style-button-hover-bg" value="#4f46e5" maxlength="7" placeholder="#000000"><input type="color" class="ssb-style-color ssb-color-picker" data-color-for="style-button-hover-bg" value="#4f46e5"></div></div>
                        <div><label class="ssb-inspector-label">Radius</label><input class="ssb-inspector-input" type="number" id="style-button-radius" min="0" max="60"></div>
                        <div><label class="ssb-inspector-label">Vertical padding</label><input class="ssb-inspector-input" type="number" id="style-button-padding-y" min="4" max="40"></div>
                        <div><label class="ssb-inspector-label">Horizontal padding</label><input class="ssb-inspector-input" type="number" id="style-button-padding-x" min="4" max="80"></div>
                        <div><label class="ssb-inspector-label">Font size</label><input class="ssb-inspector-input" type="number" id="style-button-font-size" min="10" max="30"></div>
                    </div>
                    <div class="ssb-inspector-field">
                        <label class="ssb-inspector-label">Submit Button Alignment</label>
                        <input type="hidden" id="style-button-alignment" value="left">
                        <div class="ssb-button-align-control" role="group" aria-label="Submit Button Alignment">
                            <button type="button" class="ssb-button-align-btn active" data-align="left" aria-label="Align left" title="Left"><span class="dashicons dashicons-editor-alignleft" aria-hidden="true"></span><span class="ssb-button-align-text">Left</span></button>
                            <button type="button" class="ssb-button-align-btn" data-align="center" aria-label="Align center" title="Center"><span class="dashicons dashicons-editor-aligncenter" aria-hidden="true"></span><span class="ssb-button-align-text">Center</span></button>
                            <button type="button" class="ssb-button-align-btn" data-align="right" aria-label="Align right" title="Right"><span class="dashicons dashicons-editor-alignright" aria-hidden="true"></span><span class="ssb-button-align-text">Right</span></button>
                            <button type="button" class="ssb-button-align-btn" data-align="full" aria-label="Full width" title="Full Width"><span class="dashicons dashicons-align-wide" aria-hidden="true"></span><span class="ssb-button-align-text">Full</span></button>
                        </div>
                    </div>
                    <?php else: ?>
                    <div class="fp-free-builder-note"><strong>Free plan</strong><span>Basic form building is available. Styling, payments and calendar integrations are Pro features.</span><a href="<?php echo esc_url(admin_url('admin.php?page=ssb-license')); ?>">Activate Pro</a></div>
                    <?php endif; ?>

                </div>
            </div>
        </div>

        <!-- Save bar -->
        <div class="ssb-save-bar">
            <div class="ssb-save-bar-info">
                <strong id="ssb-field-count">0</strong> fields &nbsp;·&nbsp;
                <?php if ($form_id): ?>Shortcode: <strong>[ssb_form id="<?php echo $form_id; ?>"]</strong><?php else: ?>Shortcode generated after save<?php endif; ?>
            </div>
            <div style="display:flex;gap:12px;align-items:center;">
                <span id="ssb-save-status" style="color:#9ca3af;font-size:.85rem;"></span>
                <button class="ssb-save-btn" id="ssb-save-form"><span class="dashicons dashicons-saved" aria-hidden="true"></span> Save Form</button>
            </div>
        </div>
    </div>

    <!-- Hidden inputs -->
    <input type="hidden" id="ssb-form-id" value="<?php echo $form_id; ?>">
    <input type="hidden" id="ssb-fields-data" value="">
    <input type="hidden" id="ssb-settings-data" value="">
    <script>
        window.ssbInitialFields   = <?php echo $fields_json; ?>;
        window.ssbInitialSettings = <?php echo $settings_json; ?>;
    </script>
    <?php
}

// ── Form Entries Page ──────────────────────────────────────────────────────
function ssb_forms_entries_page() {
    if ( function_exists('ssb_license_admin_guard') && ! ssb_license_admin_guard('forms') ) return;
    $form_id = intval( $_GET['form_id'] ?? 0 );
    $form    = ssb_get_form( $form_id );
    if ( ! $form ) { echo '<div class="wrap"><p>Form not found.</p></div>'; return; }

    $entries = ssb_get_form_entries( $form_id );
    global $wpdb;
    $total  = count($entries);
    $unread = array_filter($entries, fn($e) => $e->status === 'unread');
    ?>
    <div class="wrap ssb-fb-wrap">
        <div style="display:flex;align-items:center;gap:12px;margin-bottom:12px;">
            <a href="<?php echo admin_url('admin.php?page=ssb-forms'); ?>" style="color:#6b7280;text-decoration:none;font-size:.9rem;"><span class="dashicons dashicons-arrow-left-alt2" aria-hidden="true"></span> All Forms</a>
            <h1 style="margin:0;"><span class="dashicons dashicons-download" aria-hidden="true"></span> Entries — <?php echo esc_html($form->form_name); ?></h1>
        </div>

        <div style="display:flex;gap:16px;margin-bottom:20px;">
            <?php foreach ([['Total',$total,'#667eea'],['Unread',count($unread),'#f59e0b'],['Read',$total-count($unread),'#10b981']] as [$lbl,$cnt,$clr]): ?>
                <div style="background:#fff;border:2px solid <?php echo $clr; ?>;border-radius:10px;padding:12px 20px;text-align:center;min-width:100px;">
                    <div style="font-size:1.8rem;font-weight:800;color:<?php echo $clr; ?>;"><?php echo $cnt; ?></div>
                    <div style="font-size:.8rem;color:#6b7280;font-weight:600;"><?php echo $lbl; ?></div>
                </div>
            <?php endforeach; ?>
            <div style="margin-left:auto;align-self:center;">
                <a href="<?php echo admin_url('admin.php?page=ssb-forms&ssb_action=edit&form_id=' . $form_id); ?>" class="button"><span class="dashicons dashicons-edit" aria-hidden="true"></span> Edit Form</a>
                <button id="ssb-export-csv" class="button" style="margin-left:6px;"><span class="dashicons dashicons-download" aria-hidden="true"></span> Export CSV</button>
            </div>
        </div>

        <?php if ( empty($entries) ): ?>
            <div style="text-align:center;padding:60px;background:#fff;border:1px solid #e5e7eb;border-radius:10px;color:#6b7280;">
                <span class="dashicons dashicons-inbox" style="font-size:40px;width:40px;height:40px;margin-bottom:12px;"></span>
                No submissions yet. Share your form shortcode to start collecting responses.
            </div>
        <?php else: ?>
        <div style="background:#fff;border:1px solid #e5e7eb;border-radius:10px;overflow:hidden;">
            <table class="ssb-entries-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Summary</th>
                        <th>Date</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($entries as $i => $entry):
                        $data    = json_decode($entry->entry_data, true) ?: [];
                        $preview = array_slice($data, 0, 2);
                        $summary = implode(' · ', array_map(fn($v) => substr($v,0,40), array_values($preview)));
                    ?>
                    <tr class="<?php echo $entry->status; ?>" data-entry-id="<?php echo $entry->id; ?>">
                        <td style="color:#9ca3af;"><?php echo $total - $i; ?></td>
                        <td>
                            <div style="font-weight:600;margin-bottom:4px;"><?php echo esc_html($summary ?: '(empty)'); ?></div>
                            <span class="ssb-entry-expand" onclick="this.closest('tr').nextElementSibling.querySelector('.ssb-entry-detail').style.display==='none'?(this.closest('tr').nextElementSibling.querySelector('.ssb-entry-detail').style.display='block',this.textContent='▲ Hide'):(this.closest('tr').nextElementSibling.querySelector('.ssb-entry-detail').style.display='none',this.textContent='▼ View all fields')">▼ View all fields</span>
                        </td>
                        <td style="white-space:nowrap;color:#6b7280;font-size:.8rem;"><?php echo date('M d, Y H:i', strtotime($entry->created_at)); ?></td>
                        <td><span class="ssb-entry-badge <?php echo $entry->status; ?>"><?php echo ucfirst($entry->status); ?></span></td>
                        <td style="white-space:nowrap;">
                            <?php if ($entry->status === 'unread'): ?>
                                <button class="button button-small ssb-mark-read" data-id="<?php echo $entry->id; ?>"><span class="dashicons dashicons-yes" aria-hidden="true"></span> Read</button>
                            <?php endif; ?>
                            <button class="button button-small ssb-del-entry" data-id="<?php echo $entry->id; ?>" style="color:#dc2626;margin-left:4px;">Delete</button>
                        </td>
                    </tr>
                    <tr>
                        <td colspan="5" style="padding:0;">
                            <div class="ssb-entry-detail" style="display:none;padding:12px;">
                                <table>
                                    <?php foreach ($data as $label => $value): ?>
                                    <tr>
                                        <td><?php echo esc_html($label); ?></td>
                                        <td><?php echo nl2br(esc_html($value)); ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </table>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>

    <script>
    jQuery(document).ready(function($){
        var entries = <?php echo wp_json_encode( array_map(function($e){ return ['id'=>$e->id,'data'=>json_decode($e->entry_data,true),'date'=>$e->created_at]; }, $entries) ); ?>;

        $('.ssb-mark-read').on('click', function(){
            var id = $(this).data('id'), $btn = $(this);
            $.post(ssbForms.ajaxUrl, {action:'ssb_update_entry', nonce:ssbForms.nonce, entry_id:id, entry_action:'read'}, function(){
                $btn.closest('tr').removeClass('unread').find('.ssb-entry-badge').removeClass('unread').addClass('read').text('Read');
                $btn.remove();
            });
        });

        $('.ssb-del-entry').on('click', function(){
            if (!confirm('Delete this entry?')) return;
            var id = $(this).data('id'), $row = $(this).closest('tr');
            $.post(ssbForms.ajaxUrl, {action:'ssb_update_entry', nonce:ssbForms.nonce, entry_id:id, entry_action:'delete'}, function(){
                $row.next('tr').remove();
                $row.remove();
            });
        });

        // CSV export
        $('#ssb-export-csv').on('click', function(){
            if (!entries.length) { alert('No entries to export.'); return; }
            var allKeys = [];
            entries.forEach(function(e){ Object.keys(e.data||{}).forEach(function(k){ if(!allKeys.includes(k)) allKeys.push(k); }); });
            var csvRows = [['Date'].concat(allKeys).map(function(v){ return '"'+String(v).replace(/"/g,'""')+'"'; }).join(',')];
            entries.forEach(function(e){
                var row = [e.date].concat(allKeys.map(function(k){ return e.data[k]||''; }));
                csvRows.push(row.map(function(v){ return '"'+String(v).replace(/"/g,'""')+'"'; }).join(','));
            });
            var blob = new Blob([csvRows.join('\n')], {type:'text/csv'});
            var a = document.createElement('a');
            a.href = URL.createObjectURL(blob);
            a.download = '<?php echo sanitize_file_name($form->form_name); ?>-entries.csv';
            a.click();
        });
    });
    </script>
    <?php
}


function ssb_smart_booking_form_page(){
    if(!current_user_can('manage_options')) return;
    if(function_exists('ssb_license_admin_guard') && !ssb_license_admin_guard('advanced_styling')) return;
    $defaults=['style_radius'=>'12','style_input_radius'=>'10','style_button_radius'=>'10','style_primary'=>'#0a0a0a','style_accent'=>'#6366f1','style_bg'=>'#ffffff','style_text'=>'#111827','display_title'=>'Strategy Session','description'=>'Choose a time, then enter your details to confirm the booking.','first_label'=>'First Name','last_label'=>'Last Name','email_label'=>'Email','phone_label'=>'Phone','practice_label'=>'Practice Type','practice_options'=>['Full-time','Part-time'],'timezone_label'=>'Your Timezone','times_label'=>'Available Times','submit_label'=>'Confirm Booking','success_title'=>"You're all set!","success_message"=>'Your session has been confirmed. We\'ve sent a confirmation email with all the details.'];
    $settings=get_option('ssb_smart_booking_editor_settings',[]); if(!is_array($settings))$settings=[]; $settings=array_merge($defaults,$settings);
    $fields=get_option('ssb_smart_booking_custom_fields',[]); if(!is_array($fields))$fields=[];
    if(isset($_POST['ssb_smart_save'])&&check_admin_referer('ssb_smart_booking_editor')){
        $raw=wp_unslash($_POST['custom_fields']??'[]');$decoded=json_decode($raw,true);if(!is_array($decoded))$decoded=[];$clean=[];
        foreach($decoded as $f){$id=sanitize_key($f['id']??uniqid('cf'));if(!$id)continue;$type=sanitize_key($f['type']??'text');if(!in_array($type,['text','email','tel','number','textarea','select','radio','date'],true))$type='text';$opts=[];foreach((array)($f['options']??[]) as $o){$o=sanitize_text_field($o);if($o!=='')$opts[]=$o;}$clean[]=['id'=>$id,'label'=>sanitize_text_field($f['label']??'Custom field'),'type'=>$type,'placeholder'=>sanitize_text_field($f['placeholder']??''),'required'=>!empty($f['required']),'full'=>!empty($f['full']),'options'=>$opts];}
        update_option('ssb_smart_booking_custom_fields',$clean,false);
        $incoming_settings=json_decode(wp_unslash($_POST['smart_settings']??'[]'),true); if(is_array($incoming_settings)){ foreach(['style_radius','style_input_radius','style_button_radius','style_primary','style_accent','style_bg','style_text','first_label','last_label','email_label','phone_label','practice_label','timezone_label','times_label','submit_label','success_title','success_message'] as $k){ if(array_key_exists($k,$incoming_settings)) $settings[$k]=is_string($incoming_settings[$k])?sanitize_textarea_field($incoming_settings[$k]):$incoming_settings[$k]; } if(isset($incoming_settings['practice_options'])&&is_array($incoming_settings['practice_options']))$settings['practice_options']=array_values(array_filter(array_map('sanitize_text_field',$incoming_settings['practice_options']))); }
        $settings['display_title']=sanitize_text_field($_POST['display_title']??$settings['display_title']??$defaults['display_title']);$settings['description']=sanitize_textarea_field($_POST['description']??$settings['description']??'');
        foreach(['style_radius','style_input_radius','style_button_radius','style_primary','style_accent','style_bg','style_text','first_label','last_label','email_label','phone_label','practice_label','timezone_label','times_label','submit_label','success_title'] as $k)$settings[$k]=sanitize_text_field($_POST[$k]??$defaults[$k]);
        $settings['success_message']=sanitize_textarea_field($_POST['success_message']??$defaults['success_message']);
        $po=array_map('sanitize_text_field',(array)($_POST['practice_options']??[]));$settings['practice_options']=array_values(array_filter($po,fn($x)=>$x!==''));if(!$settings['practice_options'])$settings['practice_options']=$defaults['practice_options'];
        update_option('ssb_smart_booking_editor_settings',$settings,false);$fields=$clean;
        echo '<div class="notice notice-success is-dismissible"><p><strong>Smart Schedule Booking saved.</strong> The live shortcode form now uses these fields, labels and values.</p></div>';
    }
    $core=[['id'=>'first_name','label'=>$settings['first_label'],'type'=>'text','placeholder'=>'Jane','required'=>true,'locked'=>true],['id'=>'last_name','label'=>$settings['last_label'],'type'=>'text','placeholder'=>'Smith','required'=>true,'locked'=>true],['id'=>'email','label'=>$settings['email_label'],'type'=>'email','placeholder'=>'jane@example.com','required'=>true,'locked'=>true],['id'=>'phone','label'=>$settings['phone_label'],'type'=>'tel','placeholder'=>'+1 234 567 8900','required'=>true,'locked'=>true],['id'=>'practice_type','label'=>$settings['practice_label'],'type'=>'radio','options'=>$settings['practice_options'],'required'=>true,'locked'=>true,'editable_options'=>true],['id'=>'booking_date','label'=>'Date','type'=>'date','placeholder'=>'','required'=>true,'locked'=>true],['id'=>'booking_time','label'=>$settings['times_label'],'type'=>'timeslot','placeholder'=>'','required'=>true,'locked'=>true],['id'=>'timezone','label'=>$settings['timezone_label'],'type'=>'timezone','placeholder'=>'','required'=>true,'locked'=>true]];
    $all=array_merge($core,array_map(function($f){$f['locked']=false;return $f;},$fields));
    $nonce=wp_create_nonce('ssb_smart_booking_editor');
    echo '<div class="wrap ssb-fb-wrap fp-smart-editor" style="margin-right:0"><div style="display:flex;align-items:center;gap:12px;margin-bottom:8px"><a href="'.esc_url(admin_url('admin.php?page=ssb-forms')).'" style="color:#6b7280;text-decoration:none;font-size:.9rem"><span class="dashicons dashicons-arrow-left-alt2" aria-hidden="true"></span> All Forms</a><h1 style="margin:0">Smart Schedule Booking</h1><code style="background:#f3f4f6;padding:4px 10px;border-radius:6px">[smart_schedule_booking]</code></div><p style="color:#64748b;margin-top:0">Edit the actual legacy booking experience with the same builder-style workspace used by FormPilot forms. Core scheduling fields stay connected to the booking engine; custom fields can be added, reordered and styled.</p>';
    echo '<form method="post" id="ssb-smart-builder">'.wp_nonce_field('ssb_smart_booking_editor','_wpnonce',true,false).'<input type="hidden" name="ssb_smart_save" value="1"><input type="hidden" name="custom_fields" id="ssb-smart-json"><input type="hidden" name="smart_settings" id="ssb-smart-settings-json">';
    echo '<div class="ssb-builder fp-smart-builder"><div class="ssb-builder-palette"><h3>Field Types</h3><p style="font-size:.68rem;color:#9ca3af;line-height:1.5;margin:0 0 12px;">Click to add instantly, or drag any field into the exact position you want. Drag existing fields to reorder them. Changes are live; save when finished.</p>';
    foreach([['text','editor-textcolor','Text'],['email','email-alt','Email'],['tel','phone','Phone'],['number','calculator','Number'],['textarea','edit','Textarea'],['select','menu','Dropdown'],['radio','marker','Radio'],['date','calendar-alt','Date']] as $x)echo '<div class="ssb-palette-item fp-smart-add" data-type="'.$x[0].'"><span class="pi dashicons dashicons-'.$x[1].'" aria-hidden="true"></span><span>'.$x[2].'</span></div>';
    echo '<h3 style="margin-top:24px">Booking Core</h3><div class="fp-smart-core-note">Name, email, phone, date, time, timezone and scheduling logic remain connected to the legacy booking engine.</div></div>';
    echo '<div class="ssb-builder-canvas"><div class="ssb-canvas-header"><label style="display:block;font-size:.72rem;font-weight:700;color:#6b7280;margin-bottom:4px">Display title</label><input type="text" id="smart-display-title" value="'.esc_attr($settings['display_title']).'"><textarea id="smart-description" rows="2" placeholder="Description…">'.esc_textarea($settings['description']).'</textarea></div><ul id="smart-drop-zone" class="ssb-drop-zone"></ul><div class="fp-smart-preview-wrap"><div class="ssb-inspector-section-title">LIVE PREVIEW</div><div id="smart-live-preview"></div></div></div>';
    echo '<div class="ssb-builder-inspector"><div class="ssb-inspector-tabs"><div class="ssb-inspector-tab active" data-tab="smart-field">Field</div><div class="ssb-inspector-tab" data-tab="smart-form">Form Settings</div></div><div id="smart-inspector-field" class="ssb-inspector-body"><div class="ssb-inspector-empty">Click a field in the form canvas to edit it.</div></div><div id="smart-inspector-form" class="ssb-inspector-body" style="display:none"><div class="ssb-inspector-section-title">Form Settings</div>';
    foreach([['first_label','First name label'],['last_label','Last name label'],['email_label','Email label'],['phone_label','Phone label'],['practice_label','Practice type label'],['timezone_label','Timezone label'],['times_label','Available times label'],['submit_label','Submit button'],['success_title','Success title']] as $x)echo '<div class="ssb-inspector-field"><label class="ssb-inspector-label">'.esc_html($x[1]).'</label><input class="ssb-inspector-input smart-setting" data-setting="'.$x[0].'" value="'.esc_attr($settings[$x[0]]).'"></div>';echo '<div style="margin-top:18px;padding-top:14px;border-top:1px solid #e5e7eb"><strong style="display:block;margin-bottom:10px">Core scheduling fields</strong><div style="font-size:12px;color:#64748b;line-height:1.55">Name, email, phone, date, time and timezone stay connected to the legacy booking engine. Their labels and visual styling can be changed, but the booking/calendar/timezone logic is locked.</div><div class="fp-core-chip-row"><span><span class="dashicons dashicons-admin-users" aria-hidden="true"></span> Name</span><span><span class="dashicons dashicons-email-alt" aria-hidden="true"></span> Email</span><span><span class="dashicons dashicons-phone" aria-hidden="true"></span> Phone</span><span><span class="dashicons dashicons-calendar-alt" aria-hidden="true"></span> Date</span><span><span class="dashicons dashicons-clock" aria-hidden="true"></span> Time</span><span><span class="dashicons dashicons-admin-site-alt3" aria-hidden="true"></span> Timezone</span></div></div><div style="margin-top:18px;padding-top:14px;border-top:1px solid #e5e7eb"><strong style="display:block;margin-bottom:10px">Style</strong><div class="ssb-inspector-field"><label class="ssb-inspector-label">Primary</label><input type="color" class="smart-setting" data-setting="style_primary" value="'.esc_attr($settings['style_primary']).'"></div><div class="ssb-inspector-field"><label class="ssb-inspector-label">Accent</label><input type="color" class="smart-setting" data-setting="style_accent" value="'.esc_attr($settings['style_accent']).'"></div><div class="ssb-inspector-field"><label class="ssb-inspector-label">Background</label><input type="color" class="smart-setting" data-setting="style_bg" value="'.esc_attr($settings['style_bg']).'"></div><div class="ssb-inspector-field"><label class="ssb-inspector-label">Text</label><input type="color" class="smart-setting" data-setting="style_text" value="'.esc_attr($settings['style_text']).'"></div><div class="ssb-inspector-field"><label class="ssb-inspector-label">Field radius (px)</label><input type="number" min="0" max="30" class="ssb-inspector-input smart-setting" data-setting="style_input_radius" value="'.esc_attr($settings['style_input_radius']).'"></div><div class="ssb-inspector-field"><label class="ssb-inspector-label">Button radius (px)</label><input type="number" min="0" max="30" class="ssb-inspector-input smart-setting" data-setting="style_button_radius" value="'.esc_attr($settings['style_button_radius']).'"></div></div>';
    echo '<div class="ssb-inspector-field"><label class="ssb-inspector-label">Practice options</label><textarea class="ssb-inspector-input smart-setting smart-practice-options" data-setting="practice_options" rows="4">'.esc_textarea(implode("\n",$settings['practice_options'])).'</textarea></div><div class="ssb-inspector-field"><label class="ssb-inspector-label">Success message</label><textarea class="ssb-inspector-input smart-setting" data-setting="success_message" rows="4">'.esc_textarea($settings['success_message']).'</textarea></div></div></div></div>';
    echo '<div class="ssb-save-bar"><div class="ssb-save-bar-info"><strong id="smart-field-count">'.count($all).'</strong> fields · Shortcode: <strong>[smart_schedule_booking]</strong></div><div style="display:flex;gap:12px;align-items:center"><span id="smart-save-status" style="color:#059669;font-size:.85rem"></span><button class="ssb-save-btn"><span class="dashicons dashicons-saved" aria-hidden="true"></span> Save Booking Form</button></div></div></form></div>';
    ?>
    <style>
    .fp-smart-builder{grid-template-columns:190px minmax(420px,1fr) 330px!important}.fp-smart-builder .ssb-builder-palette{min-height:760px}.fp-smart-core-note{font-size:11px;line-height:1.55;color:#64748b;background:#f8fafc;border:1px solid #e5e7eb;border-radius:10px;padding:10px}.fp-smart-preview-wrap{margin-top:22px;padding:20px;background:#fff;border:1px solid #e5e7eb;border-radius:12px}.fp-smart-card{display:grid;grid-template-columns:220px 1fr;min-height:470px;border:1px solid #e5e7eb;border-radius:18px;overflow:hidden;background:#fff;box-shadow:0 10px 35px rgba(15,23,42,.07)}.fp-smart-side{background:linear-gradient(160deg,var(--sp,#111827),var(--sa,#312e81));color:#fff;padding:25px}.fp-smart-side h3{color:#fff;margin:10px 0}.fp-smart-side p{color:#cbd5e1;font-size:12px;line-height:1.6}.fp-smart-side .fp-mini-pill{display:inline-block;background:rgba(255,255,255,.12);padding:7px 10px;border-radius:9px;font-size:11px;margin:5px 0}.fp-smart-main{padding:25px;background:var(--sbg,#fafafa);color:var(--st,#111827)}.fp-smart-main h3{margin-top:0}.fp-smart-field{margin-bottom:12px}.fp-smart-field label{display:block;font-size:11px;font-weight:700;color:#475569;margin-bottom:5px}.fp-smart-field input,.fp-smart-field select,.fp-smart-field textarea{width:100%;padding:9px;border:1px solid #d1d5db;border-radius:var(--sir,8px);background:#fff}.fp-smart-field textarea{min-height:60px}.fp-core-chip-row{display:flex;flex-wrap:wrap;gap:6px;margin-top:10px}.fp-core-chip-row span{font-size:10px;background:#f1f5f9;color:#475569;padding:5px 7px;border-radius:999px}.fp-smart-button{margin-top:8px;width:100%;padding:11px;border:0;border-radius:var(--sbr,9px);background:var(--sa,#6366f1);color:#fff;font-weight:700}.smart-core-row{cursor:pointer}.smart-core-row.locked{border-left:3px solid #6366f1}.smart-row-actions{font-size:11px;color:#64748b}.smart-field-card{padding:12px!important}.smart-field-card:hover{border-color:#a5b4fc!important}.smart-locked{font-size:10px;background:#eef2ff;color:#4338ca;padding:3px 6px;border-radius:99px}.smart-option-box{margin-top:8px}.smart-option-box textarea{width:100%;min-height:55px}.fp-smart-builder .ssb-drop-zone{min-height:300px}
    @media(max-width:1200px){.fp-smart-builder{grid-template-columns:170px minmax(360px,1fr)!important}.fp-smart-builder .ssb-builder-inspector{grid-column:1/-1;display:grid;grid-template-columns:1fr 1fr}.fp-smart-builder .ssb-inspector-tabs{grid-column:1/-1}}
    </style>
    <script>
    jQuery(function($){var fields=<?php echo wp_json_encode($all);?>||[];var coreCount=<?php echo count($core);?>;var settings=<?php echo wp_json_encode($settings);?>||{};var zone=$('#smart-drop-zone'),ins=$('#smart-inspector-field'),preview=$('#smart-live-preview');
    function esc(s){return $('<div>').text(s==null?'':String(s)).html()}
    function render(){zone.empty();fields.forEach(function(f,i){var row=$('<li class="ssb-field-card smart-field-card smart-core-row '+(f.locked?'locked':'')+'" style="position:relative;list-style:none;margin-bottom:9px;padding:13px;border:1px solid #e5e7eb;border-radius:10px;background:#fff"></li>');row.append('<div style="display:flex;justify-content:space-between;align-items:center"><strong>'+esc(f.label||'Field')+'</strong><span>'+esc(f.type)+(f.locked?' <span class="smart-locked">CORE</span>':'')+'</span></div><div class="smart-row-actions">'+(f.placeholder?'Placeholder: '+esc(f.placeholder):'')+(f.required?' · Required':'')+'</div>');row.on('click',function(){select(i)});if(!f.locked){row.append('<button type="button" class="button-link-delete smart-remove" style="position:absolute;right:10px;bottom:8px">Remove</button>');row.find('.smart-remove').on('click',function(e){e.stopPropagation();fields.splice(i,1);render();});}zone.append(row)});$('#smart-field-count').text(fields.length);drawPreview();}
    function select(i){var f=fields[i];var html='<div class="ssb-inspector-section-title">'+(f.locked?'Core field':'Custom field')+'</div><div class="ssb-inspector-field"><label class="ssb-inspector-label">Label</label><input class="ssb-inspector-input smart-edit" data-k="label" value="'+esc(f.label||'')+'"></div><div class="ssb-inspector-field"><label class="ssb-inspector-label">Type</label><select class="ssb-inspector-input smart-edit" data-k="type" '+(f.locked?'disabled':'')+'><option>text</option><option>email</option><option>tel</option><option>number</option><option>textarea</option><option>select</option><option>radio</option><option>date</option></select></div><div class="ssb-inspector-field"><label class="ssb-inspector-label">Placeholder</label><input class="ssb-inspector-input smart-edit" data-k="placeholder" value="'+esc(f.placeholder||'')+'"></div><label class="ssb-inspector-toggle"><input type="checkbox" class="smart-edit" data-k="required" '+(f.required?'checked':'')+'> Required</label>';if(['select','radio'].indexOf(f.type)>=0)html+='<div class="ssb-inspector-field smart-option-box"><label class="ssb-inspector-label">Options (one per line)</label><textarea class="ssb-inspector-input smart-edit" data-k="options" rows="5">'+esc((f.options||[]).join('\n'))+'</textarea></div>';if(f.locked)html+='<p style="font-size:11px;color:#64748b;margin-top:15px">Core scheduling fields cannot be deleted because the booking engine needs them. Their labels and presentation can be customized.</p>';ins.html(html);ins.find('[data-k=type]').val(f.type||'text');ins.find('.smart-edit').on('input change',function(){var k=$(this).data('k');if(k==='options')f.options=$(this).val().split(/\n|,/).map(function(x){return x.trim()}).filter(Boolean);else f[k]=$(this).is(':checkbox')?$(this).is(':checked'):$(this).val();render();select(i);});}
    function drawPreview(){var h='<div class="fp-smart-card" style="--sp:'+esc(settings.style_primary||'#0a0a0a')+';--sa:'+esc(settings.style_accent||'#6366f1')+';--sbg:'+esc(settings.style_bg||'#fff')+';--st:'+esc(settings.style_text||'#111827')+';--sir:'+esc(settings.style_input_radius||10)+'px;--sbr:'+esc(settings.style_button_radius||10)+'px"><aside class="fp-smart-side"><div class="fp-mini-pill">BOOKING</div><h3>'+esc($('#smart-display-title').val()||settings.display_title)+'</h3><p>'+esc($('#smart-description').val()||settings.description)+'</p><div class="fp-mini-pill">30 minutes</div><div class="fp-mini-pill"><span class="dashicons dashicons-admin-site-alt3" aria-hidden="true"></span> '+esc(settings.timezone_label||'Your Timezone')+'</div><div class="fp-mini-pill"><span class="dashicons dashicons-calendar-alt" aria-hidden="true"></span> Choose a date & time</div></aside><main class="fp-smart-main"><h3>Your Details</h3><p style="color:#64748b;font-size:12px">Fill in your info to confirm the booking.</p>';fields.forEach(function(f){if(['first_name','last_name','email','phone','practice_type'].indexOf(f.id)>=0||!f.locked){h+='<div class="fp-smart-field"><label>'+esc(f.label||'Field')+(f.required?' *':'')+'</label>';if(f.type==='textarea')h+='<textarea placeholder="'+esc(f.placeholder||'')+'"></textarea>';else if(f.type==='select')h+='<select><option>Select…</option>'+((f.options||[]).map(function(o){return '<option>'+esc(o)+'</option'}).join(''))+'</select>';else if(f.type==='radio')h+='<div>'+((f.options||[]).map(function(o){return '<label style="display:inline-block;margin-right:8px"><input type="radio"> '+esc(o)+'</label'}).join(''))+'</div>';else if(f.type==='timeslot')h+='<select><option>Select an available time</option><option>9:00 AM</option><option>10:00 AM</option><option>11:00 AM</option></select>';else if(f.type==='timezone')h+='<select><option>Your timezone</option></select>';else if(f.type==='date')h+='<input type="date">';else h+='<input type="text" placeholder="'+esc(f.placeholder||'')+'">';h+='</div>';});h+='<button class="fp-smart-button">'+esc(settings.submit_label||'Confirm Booking')+'</button></main></div>';preview.html(h);$('#ssb-smart-json').val(JSON.stringify(fields.filter(function(f){return !f.locked;})));}
    $('#ssb-smart-builder').on('submit',function(){settings.display_title=$('#smart-display-title').val();settings.description=$('#smart-description').val();$('#ssb-smart-settings-json').val(JSON.stringify(settings));});$('#smart-display-title,#smart-description').on('input',drawPreview);$('.smart-setting').on('input change',function(){var k=$(this).data('setting');settings[k]=$(this).hasClass('smart-practice-options')?$(this).val().split(/\n|,/).map(function(x){return x.trim()}).filter(Boolean):$(this).val();drawPreview();});$('.fp-smart-add').on('click',function(){fields.push({id:'custom_'+Date.now(),label:$(this).find('span:last').text(),type:$(this).data('type'),placeholder:'',required:false,full:true,options:[]});render();select(fields.length-1);});render();select(0);
    });
    </script>
    <?php
}
