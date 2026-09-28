/**
 * FormPilot Pro — Form Builder Admin JS
 */
jQuery(document).ready(function ($) {
    if (!$('#ssb-drop-zone').length) return; // only run on builder page

    /* ── State ─────────────────────────────────────────────────────────────── */
    var fields   = window.ssbInitialFields   || [];
    var settings = window.ssbInitialSettings || {};
    var activeId = null; // currently selected field id
    if (!fields.some(function(x){ return x.type === 'submit'; })) {
        fields.push({id:uid(), type:'submit', label:'Submit Button', submit_text:(settings.submit_label||'Submit'), placeholder:'', required:false, options:[], width:'full', help_text:'', style:{}, style_customized:false});
    }

    function builderToast(message, type) {
        var $t = $('#ssb-builder-toast');
        if (!$t.length) { $t = $('<div id="ssb-builder-toast"></div>').appendTo('body'); }
        $t.removeClass('success error').addClass(type || 'success').text(message).stop(true,true).fadeIn(150);
        clearTimeout(window.ssbBuilderToastTimer);
        window.ssbBuilderToastTimer = setTimeout(function(){ $t.fadeOut(220); }, 2600);
    }

    var FIELD_DEFAULTS = {
        text:     { label:'Text Input', placeholder:'Enter text…', options:[], width:'full' },
        email:    { label:'Email Address', placeholder:'your@email.com', options:[], width:'full' },
        textarea: { label:'Paragraph Text', placeholder:'Type your message…', options:[], width:'full' },
        number:   { label:'Number', placeholder:'0', options:[], width:'half' },
        select:   { label:'Select Dropdown', placeholder:'Choose one…', options:['Option 1','Option 2','Option 3'], width:'full' },
        radio:    { label:'Radio Buttons', placeholder:'', options:['Option A','Option B','Option C'], width:'full' },
        checkbox: { label:'Checkboxes', placeholder:'', options:['Choice 1','Choice 2','Choice 3'], width:'full' },
        toggle:   { label:'Toggle / Switch', placeholder:'', options:[], width:'full' },
        name:     { label:'Full Name', placeholder:'', options:[], width:'full' },
        phone:    { label:'Phone Number', placeholder:'+1 234 567 8900', options:[], width:'full', enable_country_code:true },
        address:  { label:'Address', placeholder:'', options:[], width:'full' },
        url:      { label:'Website / URL', placeholder:'https://', options:[], width:'full' },
        file:     { label:'File Upload', placeholder:'', options:[], width:'full', file_multiple:false, file_types:'pdf,doc,docx,jpg,jpeg,png' },
        date:     { label:'Date', placeholder:'', options:[], width:'half', disable_past:false },
        time:     { label:'Time', placeholder:'', options:[], width:'half' },
        datetime: { label:'Date & Time', placeholder:'', options:[], width:'full', disable_past:false },
        hidden:   { label:'Hidden Field', placeholder:'', options:[], width:'full', hidden_value:'' },
        terms:    { label:'Terms & Privacy Agreement', placeholder:'', options:[], width:'full', terms_text:'I agree to the Terms and Privacy Policy.' },
        spam_protection: { label:'Spam Protection', placeholder:'', options:[], width:'full', spam_mode:'honeypot' },
        booking:  { label:'Calendar Booking', placeholder:'', options:[], width:'full', booking_duration:30, booking_use_google_freebusy:true },
        timeslot: { label:'Time Slots', placeholder:'', options:['9:00 AM','10:00 AM','11:00 AM','12:00 PM','1:00 PM','2:00 PM','3:00 PM','4:00 PM','5:00 PM'], width:'full' },
        timezone: { label:'Timezone', placeholder:'', options:[], width:'full' },
        submit:   { label:'Submit Button', placeholder:'', options:[], width:'full', submit_text:'Submit' },
        heading:  { label:'Section Heading', placeholder:'', options:[], width:'full' },
        divider:  { label:'Divider', placeholder:'', options:[], width:'full' },
        page_break:{ label:'Page Break / Step', placeholder:'', options:[], width:'full', step_title:'Next Step', step_description:'' },
        repeater:  { label:'Repeatable Group', placeholder:'', options:[], width:'full', min_items:1, max_items:10, default_items:1, add_text:'Add Another', remove_text:'Remove', item_label:'Item', child_fields:[] },
    };

    var TEMPLATES = {
        contact: [
            { type:'text',     label:'First Name',    placeholder:'', required:true,  width:'half', options:[], help_text:'' },
            { type:'text',     label:'Last Name',     placeholder:'', required:true,  width:'half', options:[], help_text:'' },
            { type:'email',    label:'Email Address', placeholder:'your@email.com', required:true,  width:'full', options:[], help_text:'' },
            { type:'phone',    label:'Phone Number',  placeholder:'+1 234 567 8900', required:false, width:'full', options:[], help_text:'' },
            { type:'text',     label:'Subject',       placeholder:'How can we help?', required:true, width:'full', options:[], help_text:'' },
            { type:'textarea', label:'Message',       placeholder:'Tell us more…', required:true, width:'full', options:[], help_text:'' },
        ],
        lead: [
            { type:'text',  label:'Full Name',     placeholder:'Your name', required:true,  width:'full', options:[], help_text:'' },
            { type:'email', label:'Email Address', placeholder:'your@email.com', required:true,  width:'full', options:[], help_text:'' },
            { type:'phone', label:'Phone Number',  placeholder:'+1 234 567 8900', required:true, width:'full', options:[], help_text:'' },
            { type:'select',label:'Budget Range',  placeholder:'Select budget…', required:false, width:'full',
              options:['Under $1,000','$1,000–$5,000','$5,000–$10,000','Over $10,000'], help_text:'' },
            { type:'textarea', label:'Tell us about your project', placeholder:'', required:false, width:'full', options:[], help_text:'' },
        ],
        survey: [
            { type:'heading',  label:'Survey: Your Feedback',  placeholder:'', required:false, width:'full', options:[], help_text:'' },
            { type:'text',     label:'Your Name',              placeholder:'', required:false, width:'full', options:[], help_text:'' },
            { type:'radio',    label:'Overall Satisfaction',   placeholder:'', required:true,  width:'full',
              options:['Very Satisfied','Satisfied','Neutral','Dissatisfied'], help_text:'' },
            { type:'radio',    label:'Would you recommend us?',placeholder:'', required:true,  width:'full',
              options:['Definitely','Probably','Not Sure','No'], help_text:'' },
            { type:'textarea', label:'Any additional comments?',placeholder:'',required:false, width:'full', options:[], help_text:'' },
        ],
    };

    var ICONS = {
        text:'<span class=\"dashicons dashicons-editor-textcolor\"></span>',
        email:'<span class=\"dashicons dashicons-email-alt\"></span>',
        phone:'<span class=\"dashicons dashicons-phone\"></span>',
        number:'<span class=\"dashicons dashicons-calculator\"></span>',
        textarea:'<span class=\"dashicons dashicons-edit\"></span>',
        select:'<span class=\"dashicons dashicons-menu\"></span>',
        radio:'<span class=\"dashicons dashicons-marker\"></span>',
        checkbox:'<span class=\"dashicons dashicons-yes-alt\"></span>',
        toggle:'<span class=\"dashicons dashicons-admin-generic\"></span>',
        name:'<span class=\"dashicons dashicons-admin-users\"></span>',
        address:'<span class=\"dashicons dashicons-location\"></span>',
        url:'<span class=\"dashicons dashicons-admin-links\"></span>',
        file:'<span class=\"dashicons dashicons-paperclip\"></span>',
        date:'<span class=\"dashicons dashicons-calendar-alt\"></span>',
        time:'<span class=\"dashicons dashicons-clock\"></span>',
        datetime:'<span class=\"dashicons dashicons-calendar\"></span>',
        hidden:'<span class=\"dashicons dashicons-hidden\"></span>',
        terms:'<span class=\"dashicons dashicons-yes\"></span>',
        spam_protection:'<span class=\"dashicons dashicons-shield\"></span>',
        booking:'<span class=\"dashicons dashicons-calendar-alt\"></span>',
        timeslot:'<span class=\"dashicons dashicons-clock\"></span>',
        timezone:'<span class=\"dashicons dashicons-admin-site-alt3\"></span>',
        submit:'<span class=\"dashicons dashicons-yes\"></span>',
        heading:'<span class=\"dashicons dashicons-heading\"></span>',
        divider:'<span class=\"dashicons dashicons-minus\"></span>',
        page_break:'<span class=\"dashicons dashicons-arrow-right-alt2\"></span>',
        repeater:'<span class=\"dashicons dashicons-update\"></span>'
    };

    /* ── Unique ID ──────────────────────────────────────────────────────────── */
    function uid() { return 'f' + Math.random().toString(36).substr(2,8); }
    function makeField(type) {
        var def = FIELD_DEFAULTS[type] || FIELD_DEFAULTS.text;
        var f = {id:uid(), type:type, label:def.label||type, placeholder:def.placeholder||'', required:false, options:(def.options||[]).slice(), width:def.width||'full', help_text:'', style:{}, style_customized:false};
        if (type === 'phone') f.enable_country_code = true;
        if (type === 'date' || type === 'datetime') f.disable_past = !!def.disable_past;
        if (type === 'timeslot') f.timeslot_mode = 'select';
        if (type === 'submit') { f.submit_text = def.submit_text || 'Submit'; f.required=false; }
        if (type === 'file') { f.file_multiple=!!def.file_multiple; f.file_types=def.file_types||''; }
        if (type === 'terms') { f.terms_text=def.terms_text||'I agree to the Terms and Privacy Policy.'; f.required=true; }
        if (type === 'spam_protection') f.spam_mode='honeypot';
        if (type === 'booking') { f.booking_duration=30; f.booking_use_google_freebusy=true; f.required=true; }
        if (type === 'hidden') f.hidden_value='';
        if (type === 'page_break') { f.step_title='Next Step'; f.step_description=''; }
        if (type === 'repeater') { f.min_items=1; f.max_items=10; f.default_items=1; f.add_text='Add Another'; f.remove_text='Remove'; f.item_label='Item'; f.child_fields=[]; }
        return f;
    }

    /* ── Render drop zone ───────────────────────────────────────────────────── */
    function ensureSubmitField() {
        var submit = fields.find(function(x){ return x.type === 'submit'; });
        fields = fields.filter(function(x){ return x.type !== 'submit'; });
        if (!submit) submit = {id:uid(), type:'submit', label:'Submit Button', submit_text:($('#set-submit-label').val()||'Submit'), placeholder:'', required:false, options:[], width:'full', help_text:'', style:{}, style_customized:false};
        fields.push(submit);
    }

    function renderCanvas() {
        ensureSubmitField();
        var $zone = $('#ssb-drop-zone');
        $zone.empty();

        if (!fields.length) {
            $zone.append('<li class="ssb-drop-placeholder" id="ssb-empty-hint"><svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 4v16m8-8H4"/></svg>Click a field type on the left to add it here,<br>or choose a template to start quickly.</li>');
        }

        fields.forEach(function (f) {
            $zone.append(buildFieldBlock(f));
        });

        initSortable();
        updateCount();
        renderPreview();
    }

    function buildFieldBlock(f) {
        var isActive  = f.id === activeId;
        var reqDot    = f.required ? 'visible' : '';
        var typeLabel = f.type.charAt(0).toUpperCase() + f.type.slice(1);
        var icon      = ICONS[f.type] || '<span class=\"dashicons dashicons-admin-generic\"></span>';

        var $li = $('<li>')
            .addClass('ssb-field-block' + (isActive ? ' active' : ''))
            .attr('data-id', f.id);

        $li.html(
            '<div class="ssb-field-block-header">' +
            '  <span class="ssb-field-drag-handle">⠿</span>' +
            '  <span class="ssb-field-type-badge">' + icon + ' ' + typeLabel + '</span>' +
            '  <span class="ssb-field-label-preview">' + (f.label || 'Untitled') + '</span>' +
            '  <span class="ssb-field-required-dot ' + reqDot + '" title="Required"></span>' +
            '  <div class="ssb-field-actions">' +
            '    <button class="ssb-field-btn dup" title="Duplicate" data-id="' + f.id + '">⧉</button>' +
            '    <button class="ssb-field-btn del" title="Delete" data-id="' + f.id + '">✕</button>' +
            '  </div>' +
            '</div>'
        );

        $li.on('click', function (e) {
            if ($(e.target).hasClass('ssb-field-btn')) return;
            selectField(f.id);
        });

        $li.find('.del').on('click', function (e) {
            e.stopPropagation();
            if (f.type === 'submit') { builderToast('The Submit Button is required and cannot be deleted.', 'error'); return; }
            if (!confirm('Delete this field?')) return;
            fields = fields.filter(function(x){ return x.id !== f.id; });
            if (activeId === f.id) { activeId = null; showFieldInspectorEmpty(); }
            renderCanvas();
            builderToast('Field deleted.', 'success');
        });

        $li.find('.dup').on('click', function (e) {
            e.stopPropagation();
            if (f.type === 'submit') { builderToast('Only one Submit Button is allowed.', 'error'); return; }
            var orig  = fields.find(function(x){ return x.id === f.id; });
            var clone = JSON.parse(JSON.stringify(orig));
            clone.id  = uid();
            clone.label += ' (copy)';
            var idx = fields.findIndex(function(x){ return x.id === f.id; });
            fields.splice(idx + 1, 0, clone);
            activeId = clone.id;
            renderCanvas();
            selectField(clone.id);
            builderToast('Field duplicated.', 'success');
        });

        return $li;
    }

    /* ── Sortable ───────────────────────────────────────────────────────────── */
    var paletteDragBound = false;
    var paletteReceiving = false;
    var paletteDropIndex = -1;
    function initSortable() {
        var $zone = $('#ssb-drop-zone');
        if (!$zone.length || !$.fn.sortable) return;
        if ($zone.hasClass('ui-sortable')) $zone.sortable('destroy');
        $zone.sortable({
            items: '> .ssb-field-block',
            handle: '.ssb-field-drag-handle',
            placeholder: 'ssb-field-placeholder',
            tolerance: 'pointer',
            forcePlaceholderSize: true,
            scroll: true,
            scrollSensitivity: 70,
            scrollSpeed: 12,
            start: function(){ $zone.addClass('drag-active'); paletteDropIndex=-1; },
            sort: function(e, ui) {
                if (ui && ui.item && ui.item.hasClass('ssb-palette-drag-helper')) {
                    var idx = ui.placeholder && ui.placeholder.length ? ui.placeholder.index() : -1;
                    if (idx >= 0) paletteDropIndex = idx;
                }
            },
            change: function(e, ui) {
                if (ui && ui.item && ui.item.hasClass('ssb-palette-drag-helper')) {
                    var idx = ui.placeholder && ui.placeholder.length ? ui.placeholder.index() : -1;
                    if (idx >= 0) paletteDropIndex = idx;
                }
            },
            receive: function(e, ui) {
                var type = ui.item.attr('data-type');
                if (!type) return;
                if (type === 'submit' && fields.some(function(x){ return x.type === 'submit'; })) {
                    ui.item.remove();
                    paletteReceiving=false;
                    builderToast('This form already has a Submit Button.', 'error');
                    return;
                }

                paletteReceiving=true;

                // jQuery UI places the external helper where the placeholder is,
                // but the helper itself can report index -1 during receive. The
                // placeholder is the authoritative insertion position.
                var $placeholder = ui.placeholder && ui.placeholder.length ? ui.placeholder : $();
                var domIndex = paletteDropIndex >= 0 ? paletteDropIndex : ($placeholder.length ? $placeholder.index() : ui.item.index());
                if (domIndex < 0) domIndex = fields.length;

                var f = makeField(type);
                var submitIndex = fields.findIndex(function(x){ return x.type === 'submit'; });

                if (type === 'submit') {
                    fields = fields.filter(function(x){ return x.type !== 'submit'; });
                    fields.push(f);
                } else {
                    if (submitIndex >= 0) domIndex=Math.min(domIndex,submitIndex);
                    domIndex=Math.max(0,Math.min(domIndex,fields.length));
                    fields.splice(domIndex,0,f);
                }

                activeId=f.id;
                ui.item.remove();
                setTimeout(function(){
                    paletteReceiving=false;
                    renderCanvas();
                    selectField(f.id);
                    builderToast('Field added at the drop position.','success');
                },0);
            },
            update: function() {
                if (paletteReceiving) return;
                var newOrder=[];
                $zone.children('.ssb-field-block').each(function(){
                    var id=$(this).attr('data-id');
                    var f=fields.find(function(x){return x.id===id;});
                    if(f)newOrder.push(f);
                });
                if (newOrder.length) { fields=newOrder; ensureSubmitField(); renderPreview(); }
            },
            stop: function(){ $zone.removeClass('drag-active drag-over'); paletteDropIndex=-1; }
        }).disableSelection();
        if (!paletteDragBound) {
            paletteDragBound=true;
            $('.ssb-palette-item[data-type]').draggable({
                helper:function(){
                    var $source=$(this);
                    var type=$source.attr('data-type') || 'text';
                    var label=$source.find('.pi').next().text() || type;
                    // The external draggable MUST also be a sortable item.
                    // Using the palette element itself as the helper makes jQuery UI
                    // treat it as an external item and the receive index becomes -1,
                    // which caused every dropped field to land immediately above Submit.
                    return $('<li class="ssb-field-block ssb-palette-drag-helper"></li>')
                        .attr('data-type',type)
                        .html('<div class="ssb-field-block-header"><span class="ssb-field-drag-handle dashicons dashicons-menu"></span><span class="ssb-field-type-badge">'+(ICONS[type]||ICONS.text)+' '+esc(label)+'</span><span class="ssb-field-label-preview">Drop field here</span></div>');
                },
                appendTo:'body', opacity:.88, zIndex:100000,
                revert:'invalid', revertDuration:120, distance:5,
                connectToSortable:'#ssb-drop-zone',
                cursorAt:{left:18,top:18},
                start:function(){ $('#ssb-drop-zone').addClass('drag-ready'); },
                drag:function(){ $('#ssb-drop-zone').addClass('drag-over'); },
                stop:function(){ $('#ssb-drop-zone').removeClass('drag-ready drag-over'); }
            });
        }
    }

    /* ── Add field from palette ─────────────────────────────────────────────── */
    $('.ssb-palette-item[data-type]').on('click', function () {
        var type = $(this).data('type');
        if (type === 'submit' && fields.some(function(x){ return x.type === 'submit'; })) {
            builderToast('This form already has a Submit Button.', 'error');
            return;
        }
        var f = makeField(type);
        if (type === 'submit') fields = fields.filter(function(x){ return x.type !== 'submit'; }).concat([f]);
        else fields.push(f);
        renderCanvas();
        selectField(f.id);
    });

    /* ── Templates ──────────────────────────────────────────────────────────── */
    $('.ssb-palette-item[data-template]').on('click', function () {
        var tpl = $(this).data('template');
        var tFields = TEMPLATES[tpl];
        if (!tFields) return;
        if (fields.length && !confirm('This will replace existing fields. Continue?')) return;
        fields = tFields.map(function(f){ return Object.assign({}, f, {id: uid()}); });
        ensureSubmitField();

        // Set form name if blank
        if (!$('#ssb-form-name').val()) {
            var names = {contact:'Contact Form', lead:'Lead Capture Form', survey:'Customer Survey'};
            $('#ssb-form-name').val(names[tpl] || '');
        }

        // Default settings per template
        if (tpl === 'contact') {
            $('#set-submit-label').val('Send Message');
            $('#set-success-msg').val('Thank you! We\'ll be in touch shortly.');
            settings.submit_label = 'Send Message';
            settings.success_msg  = 'Thank you! We\'ll be in touch shortly.';
        }

    
        activeId = null;
        renderCanvas();
        showFieldInspectorEmpty();
    });

    /* ── Field selection & inspector ────────────────────────────────────────── */
    function selectField(id) {
        activeId = id;
        $('#ssb-drop-zone .ssb-field-block').removeClass('active');
        $('#ssb-drop-zone .ssb-field-block[data-id="' + id + '"]').addClass('active');
        var f = fields.find(function(x){ return x.id === id; });
        if (f) showFieldInspector(f);
    }

    function getField(id) { return fields.find(function(x){ return x.id === id; }); }

    function showFieldInspectorEmpty() {
        $('#ssb-inspector-field').html('<div class="ssb-inspector-empty"><span class="dashicons dashicons-edit" style="font-size:28px;width:28px;height:28px;margin-bottom:8px;"></span><div>Click a field to edit its properties</div></div>');
    }

    function normalizeRepeaterChildren(f) {
        if (!f || f.type !== 'repeater') return [];
        if (!Array.isArray(f.child_fields)) f.child_fields = [];
        f.child_fields = f.child_fields.map(function (child) {
            child = child || {};
            if (!child.id) child.id = uid();
            if (!child.type) child.type = 'text';
            if (!child.label) child.label = (FIELD_DEFAULTS[child.type] && FIELD_DEFAULTS[child.type].label) || 'Field';
            if (typeof child.required === 'undefined') child.required = false;
            if (!Array.isArray(child.options)) child.options = [];
            if (!child.width) child.width = 'full';
            if (typeof child.placeholder === 'undefined') child.placeholder = '';
            return child;
        });
        return f.child_fields;
    }

    function renderRepeaterChildren(f) {
        var $list = $('#fi-repeat-children');
        if (!$list.length || !f || f.type !== 'repeater') return;
        var children = normalizeRepeaterChildren(f);
        $list.empty();
        if (!children.length) {
            $list.append('<div class="ssb-repeat-empty">No fields in this group yet. Choose a field type below and click <strong>+ Add Field</strong>.</div>');
        }
        children.forEach(function(child) {
            var typeLabel = (FIELD_DEFAULTS[child.type] && FIELD_DEFAULTS[child.type].label) || child.type;
            var $row = $('<div class="ssb-repeat-child" data-child-id="' + esc(child.id) + '">' +
                '<span class="ssb-repeat-child-handle" title="Drag to reorder">⠿</span>' +
                '<input class="ssb-repeat-child-label" value="' + esc(child.label || '') + '" aria-label="Child field label">' +
                '<span class="ssb-repeat-child-type">' + esc(typeLabel) + '</span>' +
                '<label class="ssb-repeat-child-required"><input type="checkbox" class="ssb-repeat-child-req"' + (child.required ? ' checked' : '') + '> Req</label>' +
                '<button type="button" class="ssb-repeat-child-del" title="Delete field">✕</button>' +
                '</div>');
            $row.find('.ssb-repeat-child-label').on('input change', function () {
                var c = normalizeRepeaterChildren(f).find(function(x){ return x.id === child.id; });
                if (c) { c.label = $(this).val(); renderPreview(); }
            });
            $row.find('.ssb-repeat-child-req').on('change', function () {
                var c = normalizeRepeaterChildren(f).find(function(x){ return x.id === child.id; });
                if (c) { c.required = this.checked; renderPreview(); }
            });
            $row.find('.ssb-repeat-child-del').on('click', function () {
                var arr = normalizeRepeaterChildren(f);
                if (!confirm('Delete "' + (child.label || 'this field') + '" from this repeatable group?')) return;
                f.child_fields = arr.filter(function(x){ return x.id !== child.id; });
                renderRepeaterChildren(f);
                renderPreview();
            });
            $list.append($row);
        });
        if ($list.hasClass('ui-sortable')) $list.sortable('destroy');
        if (children.length > 1 && $.fn.sortable) {
            $list.sortable({
                items: '.ssb-repeat-child',
                handle: '.ssb-repeat-child-handle',
                axis: 'y',
                tolerance: 'pointer',
                update: function () {
                    var ordered = [];
                    $list.children('.ssb-repeat-child').each(function(){
                        var id = $(this).attr('data-child-id');
                        var child = normalizeRepeaterChildren(f).find(function(x){ return x.id === id; });
                        if (child) ordered.push(child);
                    });
                    f.child_fields = ordered;
                    renderPreview();
                }
            }).disableSelection();
        }
    }

    function bindRepeaterInspector(f) {
        if (!f || f.type !== 'repeater') return;
        normalizeRepeaterChildren(f);
        renderRepeaterChildren(f);
        $('#fi-repeat-item-label').on('input change', function(){ f.item_label = $(this).val(); renderPreview(); });
        $('#fi-repeat-min').on('input change', function(){ f.min_items = Math.max(1, Math.min(50, parseInt(this.value || 1, 10))); if (f.max_items < f.min_items) f.max_items = f.min_items; renderPreview(); });
        $('#fi-repeat-max').on('input change', function(){ f.max_items = Math.max(1, Math.min(50, parseInt(this.value || 10, 10))); if (f.max_items < f.min_items) f.min_items = f.max_items; renderPreview(); });
        $('#fi-repeat-default').on('input change', function(){ f.default_items = Math.max(1, Math.min(f.max_items || 10, parseInt(this.value || 1, 10))); renderPreview(); });
        $('#fi-repeat-add-text').on('input change', function(){ f.add_text = $(this).val(); renderPreview(); });
        $('#fi-repeat-remove-text').on('input change', function(){ f.remove_text = $(this).val(); renderPreview(); });
        $('#fi-repeat-add-field').on('click', function(){
            var type = $('#fi-repeat-add-type').val() || 'text';
            var child = makeField(type);
            child.id = uid();
            child.label = (FIELD_DEFAULTS[type] && FIELD_DEFAULTS[type].label) || 'Field';
            child.required = false;
            delete child.style;
            delete child.style_customized;
            normalizeRepeaterChildren(f).push(child);
            renderRepeaterChildren(f);
            renderPreview();
            builderToast('Field added to the repeatable group.', 'success');
        });
    }

    function showFieldInspector(f) {
        var hasOptions = ['select','radio','checkbox','timeslot'].includes(f.type);
        var isDecor    = ['heading','divider','page_break'].includes(f.type);

        var html = '<div style="font-size:.75rem;font-weight:700;color:#667eea;text-transform:uppercase;letter-spacing:.5px;margin-bottom:12px;">' +
                   ICONS[f.type] + ' ' + f.type.charAt(0).toUpperCase()+f.type.slice(1) + ' Field</div>';

        if (!isDecor) {
            html += inspField('Label', '<input class="ssb-inspector-input" id="fi-label" value="' + esc(f.label) + '">');
            if (f.type !== 'timeslot' && f.type !== 'timezone') {
                html += inspField('Placeholder', '<input class="ssb-inspector-input" id="fi-ph" value="' + esc(f.placeholder) + '">');
            }
            html += inspField('Help text', '<input class="ssb-inspector-input" id="fi-help" value="' + esc(f.help_text||'') + '" placeholder="Small hint below the field">');
            html += inspField('Required',
                '<label class="ssb-inspector-toggle"><input type="checkbox" id="fi-req"' + (f.required ? ' checked' : '') + '><span style="font-size:.85rem;">This field is required</span></label>');
            html += inspField('Width',
                '<div class="ssb-width-toggle">' +
                '  <div class="ssb-width-btn ' + (f.width==='full'?'active':'') + '" data-w="full">Full</div>' +
                '  <div class="ssb-width-btn ' + (f.width==='half'?'active':'') + '" data-w="half">Half</div>' +
                '</div>');
            var fs = f.style || {};
            html += '<div class="ssb-inspector-section-title">Selected Field Appearance</div>';
            html += '<label class="ssb-inspector-toggle" style="margin-bottom:10px;"><input type="checkbox" id="fi-inherit-style"' + (!f.style_customized ? ' checked' : '') + '><span style="font-size:.85rem;">Inherit form-level input styling</span></label>';
            html += '<div class="ssb-style-grid">' +
                '<div><label class="ssb-inspector-label">Background</label><input type="color" class="ssb-style-color" id="fi-style-bg" value="' + (fs.bg||'#f9fafb') + '"></div>' +
                '<div><label class="ssb-inspector-label">Text color</label><input type="color" class="ssb-style-color" id="fi-style-text" value="' + (fs.text||'#111827') + '"></div>' +
                '<div><label class="ssb-inspector-label">Border color</label><input type="color" class="ssb-style-color" id="fi-style-border" value="' + (fs.border||'#e5e7eb') + '"></div>' +
                '<div><label class="ssb-inspector-label">Border width</label><input class="ssb-inspector-input" type="number" id="fi-style-border-width" min="0" max="6" value="' + (fs.border_width!==undefined?fs.border_width:1) + '"></div>' +
                '<div><label class="ssb-inspector-label">Radius</label><input class="ssb-inspector-input" type="number" id="fi-style-radius" min="0" max="60" value="' + (fs.radius||12) + '"></div>' +
                '<div><label class="ssb-inspector-label">Font size</label><input class="ssb-inspector-input" type="number" id="fi-style-font-size" min="10" max="30" value="' + (fs.font_size||15) + '"></div>' +
                '<div><label class="ssb-inspector-label">Padding</label><input class="ssb-inspector-input" type="number" id="fi-style-padding" min="4" max="40" value="' + (fs.padding||14) + '"></div>' +
                '</div>';

            // Phone-specific
            if (f.type === 'phone') {
                html += inspField('Country Code Picker',
                    '<label class="ssb-inspector-toggle"><input type="checkbox" id="fi-cc"' + (f.enable_country_code ? ' checked' : '') + '><span style="font-size:.85rem;">Show country code dropdown</span></label>');
            }

            // Date-specific
            if (f.type === 'date') {
                html += inspField('Past Dates',
                    '<label class="ssb-inspector-toggle"><input type="checkbox" id="fi-disablepast"' + (f.disable_past !== false ? ' checked' : '') + '><span style="font-size:.85rem;">Disable past dates</span></label>');
            }

            if (f.type === 'datetime') {
                html += inspField('Past Date/Time', '<label class="ssb-inspector-toggle"><input type="checkbox" id="fi-disablepast"' + (f.disable_past ? ' checked' : '') + '><span style="font-size:.85rem;">Disable past date/time</span></label>');
            }
            if (f.type === 'name') {
                html += inspField('Name Parts', '<select class="ssb-inspector-input" id="fi-name-mode"><option value="first_last"'+(f.name_mode==='first_last'?' selected':'')+'>First + Last Name</option><option value="full"'+(f.name_mode==='full'?' selected':'')+'>Single Full Name</option></select>');
            }
            if (f.type === 'address') {
                html += inspField('Address Parts', '<label class="ssb-inspector-toggle"><input type="checkbox" id="fi-address-country"'+(f.address_country !== false?' checked':'')+'><span style="font-size:.85rem;">Include Country</span></label>');
            }
            if (f.type === 'file') {
                html += inspField('Allowed file types', '<input class="ssb-inspector-input" id="fi-file-types" value="'+esc(f.file_types||'pdf,doc,docx,jpg,jpeg,png')+'" placeholder="pdf,jpg,png">');
                html += inspField('Multiple files', '<label class="ssb-inspector-toggle"><input type="checkbox" id="fi-file-multiple"'+(f.file_multiple?' checked':'')+'><span style="font-size:.85rem;">Allow multiple files</span></label>');
            }
            if (f.type === 'hidden') {
                html += inspField('Stored Value', '<input class="ssb-inspector-input" id="fi-hidden-value" value="'+esc(f.hidden_value||'')+'" placeholder="Use {url}, {referrer}, {user_id}, or a fixed value">');
            }
            if (f.type === 'terms') {
                html += inspField('Agreement Text', '<textarea class="ssb-inspector-input" id="fi-terms-text" rows="3">'+esc(f.terms_text||'I agree to the Terms and Privacy Policy.')+'</textarea>');
            }
            if (f.type === 'spam_protection') {
                html += inspField('Protection Mode', '<select class="ssb-inspector-input" id="fi-spam-mode"><option value="honeypot"'+(f.spam_mode!=='turnstile'&&f.spam_mode!=='recaptcha'?' selected':'')+'>Built-in Honeypot</option><option value="turnstile"'+(f.spam_mode==='turnstile'?' selected':'')+'>Cloudflare Turnstile</option><option value="recaptcha"'+(f.spam_mode==='recaptcha'?' selected':'')+'>Google reCAPTCHA</option></select>');
                html += '<p class="ssb-inspector-note">Provider keys are configured in FormPilot Settings. Honeypot works without third-party keys.</p>';
            }
            if (f.type === 'booking') {
                html += inspField('Duration (minutes)', '<input class="ssb-inspector-input" type="number" min=5 max=480 id="fi-booking-duration" value="'+(f.booking_duration||30)+'">');
                html += inspField('Google Calendar availability', '<label class="ssb-inspector-toggle"><input type="checkbox" id="fi-booking-freebusy"'+(f.booking_use_google_freebusy!==false?' checked':'')+'><span style="font-size:.85rem;">Check Google Calendar before allowing the slot</span></label>');
                html += '<p class="ssb-inspector-note">Use this field when the form is intended to create a real appointment. It supplies date, time and timezone and creates a Calendar event when Google Calendar is enabled.</p>';
            }
            if (f.type === 'submit') {
                html += inspField('Button Text', '<input class="ssb-inspector-input" id="fi-submit-text" value="'+esc(f.submit_text||f.label||'Submit')+'">');
                html += '<p class="ssb-inspector-note">The Submit Button is required for the form and cannot be deleted. FormPilot keeps one submit action per form.</p>';
            }
            if (f.type === 'repeater') {
                html += '<div class="ssb-inspector-section-title">Repeatable Group</div>';
                html += inspField('Item label', '<input class="ssb-inspector-input" id="fi-repeat-item-label" value="'+esc(f.item_label||'Item')+'" placeholder="Work Experience">');
                html += '<div class="ssb-style-grid">' + inspField('Minimum items','<input class="ssb-inspector-input" type="number" min="1" max="50" id="fi-repeat-min" value="'+Math.max(1, parseInt(f.min_items||1,10))+'">') + inspField('Maximum items','<input class="ssb-inspector-input" type="number" min="1" max="50" id="fi-repeat-max" value="'+(f.max_items||10)+'">') + '</div>';
                html += '<div class="ssb-style-grid">' + inspField('Default items','<input class="ssb-inspector-input" type="number" min="0" max="50" id="fi-repeat-default" value="'+(f.default_items||1)+'">') + inspField('Add button text','<input class="ssb-inspector-input" id="fi-repeat-add-text" value="'+esc(f.add_text||'Add Another')+'">') + '</div>';
                html += inspField('Remove button text','<input class="ssb-inspector-input" id="fi-repeat-remove-text" value="'+esc(f.remove_text||'Remove')+'">');
                html += '<div class="ssb-inspector-field"><label class="ssb-inspector-label">Fields inside this group</label><div id="fi-repeat-children" class="ssb-repeat-children"></div><div class="ssb-repeat-add-row"><select id="fi-repeat-add-type" class="ssb-inspector-input"><option value="text">Text</option><option value="email">Email</option><option value="textarea">Textarea</option><option value="number">Number</option><option value="select">Dropdown</option><option value="radio">Radio</option><option value="checkbox">Checkboxes</option><option value="toggle">Toggle</option><option value="date">Date</option><option value="time">Time</option><option value="url">Website / URL</option><option value="phone">Phone</option></select><button type="button" class="button" id="fi-repeat-add-field">+ Add Field</button></div><p class="ssb-inspector-note">Existing template fields are listed above. Rename, mark required, delete, or drag them to reorder. Use the selector to add another field to this group.</p></div>';
            }

            // Timeslot-specific
            if (f.type === 'timeslot') {
                html += inspField('Display Style',
                    '<select class="ssb-inspector-input" id="fi-ts-mode">' +
                    '<option value="select"' + (f.timeslot_mode==='select'?' selected':'') + '>Dropdown</option>' +
                    '<option value="radio"' + (f.timeslot_mode==='radio'?' selected':'') + '>Radio Buttons</option>' +
                    '</select>');
            }
        } else {
            html += inspField('Label / Text', '<input class="ssb-inspector-input" id="fi-label" value="' + esc(f.label) + '">');
            if (f.type === 'page_break') {
                html += inspField('Step Title', '<input class="ssb-inspector-input" id="fi-step-title" value="' + esc(f.step_title || f.label || 'Next Step') + '" placeholder="e.g. Your Details">');
                html += inspField('Step Description', '<textarea class="ssb-inspector-input" id="fi-step-description" rows="3" placeholder="Optional instructions for this step">' + esc(f.step_description || '') + '</textarea>');
                html += '<p class="ssb-inspector-note">This Page Break starts the next step. Fields before it belong to the previous step. All steps remain in the same HTML form on the website.</p>';
            }
        }

        if (hasOptions) {
            var optLabel = f.type === 'timeslot' ? 'Time Slots' : 'Options';
            html += '<div class="ssb-inspector-field"><label class="ssb-inspector-label">' + optLabel + '</label>';
            html += '<div class="ssb-options-list" id="fi-options-list">';
            (f.options || []).forEach(function(opt, i){
                html += '<div class="ssb-option-row">' +
                        '<input class="fi-option" data-idx="' + i + '" value="' + esc(opt) + '">' +
                        '<button class="ssb-option-del" data-idx="' + i + '">✕</button>' +
                        '</div>';
            });
            html += '</div>';
            var addLabel = f.type === 'timeslot' ? '+ Add time slot' : '+ Add option';
            html += '<button class="ssb-add-option">' + addLabel + '</button>';
            if (f.type === 'select') {
                html += '<div class="ssb-csv-import">' +
                    '<label class="ssb-inspector-label">Import options from CSV</label>' +
                    '<input type="file" id="fi-csv" accept=".csv,text/csv">' +
                    '<small>CSV format: one option per row in the first column. A header row is optional. Uploading replaces the current options.</small>' +
                    '<button type="button" class="button" id="fi-csv-upload" style="margin-top:7px;">Upload CSV</button>' +
                    '<span id="fi-csv-status" style="display:block;margin-top:5px;font-size:.72rem;"></span>' +
                    '</div>';
            }
            html += '</div>';
        }

        $('#ssb-inspector-field').html(html);

        // The repeater inspector controls are created by the HTML above.
        // Bind them only AFTER injecting that HTML; binding before injection
        // silently matched nothing, so child fields and + Add Field appeared dead.
        if (f.type === 'repeater') { bindRepeaterInspector(f); }

        // Page Break-specific live bindings
        $('#fi-step-title').on('input change', function(){ f.step_title = $(this).val(); f.label = f.step_title || 'Next Step'; renderCanvas(); selectField(f.id); });
        $('#fi-step-description').on('input change', function(){ f.step_description = $(this).val(); });

        // Live bindings
        $('#fi-label').on('input', function(){ update(f.id,'label',this.value); });
        $('#fi-ph').on('input',    function(){ update(f.id,'placeholder',this.value); });
        $('#fi-help').on('input',  function(){ update(f.id,'help_text',this.value); });
        $('#fi-req').on('change',  function(){ update(f.id,'required',this.checked); });
        $('#fi-cc').on('change',   function(){ update(f.id,'enable_country_code',this.checked); });
        $('#fi-disablepast').on('change', function(){ update(f.id,'disable_past',this.checked); });
        $('#fi-ts-mode').on('change', function(){ update(f.id,'timeslot_mode',this.value); renderPreview(); });
        $('#fi-name-mode').on('change', function(){ update(f.id,'name_mode',this.value); renderPreview(); });
        $('#fi-address-country').on('change', function(){ update(f.id,'address_country',this.checked); renderPreview(); });
        $('#fi-file-types').on('input', function(){ update(f.id,'file_types',this.value); renderPreview(); });
        $('#fi-file-multiple').on('change', function(){ update(f.id,'file_multiple',this.checked); renderPreview(); });
        $('#fi-hidden-value').on('input', function(){ update(f.id,'hidden_value',this.value); });
        $('#fi-terms-text').on('input', function(){ update(f.id,'terms_text',this.value); renderPreview(); });
        $('#fi-spam-mode').on('change', function(){ update(f.id,'spam_mode',this.value); renderPreview(); });
        $('#fi-booking-duration').on('input change', function(){ update(f.id,'booking_duration',Math.max(5,Math.min(480,parseInt(this.value||30,10)))); });
        $('#fi-booking-freebusy').on('change', function(){ update(f.id,'booking_use_google_freebusy',this.checked); });
        $('#fi-submit-text').on('input', function(){ update(f.id,'submit_text',this.value); update(f.id,'label',this.value); renderPreview(); });
        $('#fi-inherit-style').on('change', function(){ var fld=getField(f.id); if(!fld) return; fld.style_customized=!this.checked; renderPreview(); });
        $('#ssb-inspector-field').off('input.ssbFieldStyle change.ssbFieldStyle')
            .on('input.ssbFieldStyle change.ssbFieldStyle', '[id^="fi-style-"]', function(){
                var fld=getField(f.id); if(!fld) return;
                fld.style=fld.style||{};
                fld.style_customized=true;
                var map={'fi-style-bg':'bg','fi-style-text':'text','fi-style-border':'border','fi-style-border-width':'border_width','fi-style-radius':'radius','fi-style-font-size':'font_size','fi-style-padding':'padding'};
                var key=map[this.id]; if(key) fld.style[key]=(this.type==='color'||this.type==='text')?this.value:parseInt(this.value||0,10);
                renderPreview();
            });

        $('#ssb-inspector-field').off('click.ssbWidth').on('click.ssbWidth', '.ssb-width-btn', function(){
            var w = $(this).data('w');
            update(f.id,'width',w);
            $('#ssb-inspector-field .ssb-width-btn').removeClass('active');
            $(this).addClass('active');
        });

        if (hasOptions) {
            $('#ssb-inspector-field').off('input.ssbOption').on('input.ssbOption', '.fi-option', function(){
                var idx = parseInt($(this).data('idx'));
                var fld = getField(f.id);
                if (fld) fld.options[idx] = this.value;
                renderPreview();
            });
            $('#ssb-inspector-field').off('click.ssbOptionDel').on('click.ssbOptionDel', '.ssb-option-del', function(){
                var idx = parseInt($(this).data('idx'));
                var fld = getField(f.id);
                if (fld) { fld.options.splice(idx,1); }
                showFieldInspector(getField(f.id));
                renderPreview();
            });
            $('#ssb-inspector-field').off('click.ssbAddOption').on('click.ssbAddOption', '.ssb-add-option', function(){
                var fld = getField(f.id);
                var newVal = f.type === 'timeslot' ? '10:00 AM' : 'New Option';
                if (fld) fld.options.push(newVal);
                showFieldInspector(getField(f.id));
                renderPreview();
            });
            $('#ssb-inspector-field').off('click.ssbCsv').on('click.ssbCsv', '#fi-csv-upload', function(){
                var input = document.getElementById('fi-csv');
                if (!input || !input.files.length) { $('#fi-csv-status').css('color','#dc2626').text('Choose a CSV file first.'); return; }
                var fd = new FormData();
                fd.append('action','ssb_import_select_csv');
                fd.append('nonce',ssbForms.nonce);
                fd.append('csv_file',input.files[0]);
                $('#fi-csv-upload').prop('disabled',true);
                $('#fi-csv-status').css('color','#64748b').text('Importing…');
                $.ajax({url:ssbForms.ajaxUrl,type:'POST',data:fd,processData:false,contentType:false}).done(function(r){
                    if(r.success){
                        var fld=getField(f.id);
                        if(fld){ fld.options=r.data.options||[]; showFieldInspector(fld); renderPreview(); }
                    } else {
                        $('#fi-csv-status').css('color','#dc2626').text((r.data&&r.data.msg)||'Import failed.');
                    }
                }).fail(function(){ $('#fi-csv-status').css('color','#dc2626').text('Upload failed.'); })
                  .always(function(){ $('#fi-csv-upload').prop('disabled',false); });
            });
        }
    }

    function inspField(labelText, inputHtml) {
        return '<div class="ssb-inspector-field"><label class="ssb-inspector-label">' + labelText + '</label>' + inputHtml + '</div>';
    }

    function update(id, key, value) {
        var f = getField(id);
        if (!f) return;
        f[key] = value;
        // Update block label preview live
        if (key === 'label') {
            $('#ssb-drop-zone .ssb-field-block[data-id="' + id + '"] .ssb-field-label-preview').text(value || 'Untitled');
        }
        if (key === 'required') {
            var dot = $('#ssb-drop-zone .ssb-field-block[data-id="' + id + '"] .ssb-field-required-dot');
            value ? dot.addClass('visible') : dot.removeClass('visible');
        }
        renderPreview();
    }

    function esc(str) { return String(str||'').replace(/&/g,'&amp;').replace(/"/g,'&quot;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }

    /* ── Inspector tabs ─────────────────────────────────────────────────────── */
    $('.ssb-inspector-tab').on('click', function(){
        var tab = $(this).data('tab');
        $('.ssb-inspector-tab').removeClass('active');
        $(this).addClass('active');
        if (tab === 'field') {
            $('#ssb-inspector-field').show();
            $('#ssb-inspector-form').hide();
        } else {
            $('#ssb-inspector-field').hide();
            $('#ssb-inspector-form').show();
        }
    });

    /* ── Form settings live binding ─────────────────────────────────────────── */
    var STYLE_DEFAULTS = {
        form_bg:'#ffffff', form_bg_transparent:false, form_border_enabled:true, form_border:'#edf0f3', form_radius:24, form_padding:40, field_gap:18,
        label_color:'#6b7280', label_size:12, heading_color:'#667eea', input_bg:'#f9fafb', input_text:'#111827',
        input_border:'#e5e7eb', input_focus_border:'#667eea', input_focus_ring:'#dbeafe', input_error_border:'#dc2626', input_error_bg:'#fef2f2', input_placeholder:'#94a3b8', input_border_width:1, input_radius:12, input_padding:14, input_font_size:15,
        button_bg:'#667eea', button_text:'#ffffff', button_hover_bg:'#4f46e5', button_radius:999,
        button_padding_y:15, button_padding_x:34, button_font_size:15, button_alignment:'left', button_full_width:false,
        ms_next_bg:'#0f172a', ms_next_text:'#ffffff', ms_next_hover_bg:'#1e293b', ms_next_border:'#0f172a', ms_next_border_width:0, ms_next_radius:8, ms_next_padding_y:10, ms_next_padding_x:22,
        ms_prev_bg:'#f1f5f9', ms_prev_text:'#475569', ms_prev_hover_bg:'#e2e8f0', ms_prev_border:'#e2e8f0', ms_prev_border_width:1, ms_prev_radius:8, ms_prev_padding_y:10, ms_prev_padding_x:22, ms_nav_full_width_mobile:false,
        ms_progress_color:'#667eea', ms_track_color:'#e9edf5', ms_connector_color:'#d9deea',
        ms_active_color:'#667eea', ms_completed_color:'#667eea', ms_inactive_color:'#eef2f7', ms_text_color:'#64748b',
        ms_shape:'circle', ms_size:30, ms_bar_height:8, ms_spacing:10, ms_show_percent:true, ms_show_step_label:true,
        ms_show_titles:true, ms_clickable:true, ms_animation:'slide'
    };
    var RESPONSIVE_DEFAULTS = {
        desktop:{form_padding:40,form_radius:24,field_gap:18,title_size:32,description_size:16,label_size:12,input_padding:14,input_font_size:15,button_padding_y:15,button_padding_x:34,button_font_size:15,ms_size:30,ms_bar_height:8,ms_spacing:10,ms_show_titles:true},
        tablet:{form_padding:28,form_radius:20,field_gap:14,title_size:28,description_size:15,label_size:12,input_padding:13,input_font_size:15,button_padding_y:14,button_padding_x:28,button_font_size:15,ms_size:30,ms_bar_height:8,ms_spacing:8,ms_show_titles:true},
        mobile:{form_padding:20,form_radius:16,field_gap:12,title_size:24,description_size:14,label_size:12,input_padding:12,input_font_size:16,button_padding_y:13,button_padding_x:20,button_font_size:14,ms_size:28,ms_bar_height:7,ms_spacing:6,ms_show_titles:false}
    };
    var responsiveDevice = 'desktop';
    function responsiveState() {
        var existing = (window.ssbInitialSettings && window.ssbInitialSettings.style && window.ssbInitialSettings.style.responsive) || {};
        var out={};
        Object.keys(RESPONSIVE_DEFAULTS).forEach(function(d){ out[d]=$.extend({},RESPONSIVE_DEFAULTS[d],existing[d]||{}); });
        return out;
    }
    var responsiveValues = responsiveState();
    function loadResponsiveControls(device){
        responsiveDevice=device; var vals=responsiveValues[device]||RESPONSIVE_DEFAULTS[device];
        $('.ssb-responsive-device').removeClass('active').filter('[data-device="'+device+'"]').addClass('active');
        $('.ssb-responsive-input').each(function(){ var k=$(this).data('responsive-key'); if($(this).attr('type')==='checkbox') $(this).prop('checked',!!vals[k]); else $(this).val(vals[k]); });
    }
    function captureResponsiveControls(){
        var vals=responsiveValues[responsiveDevice]||{};
        $('.ssb-responsive-input').each(function(){ var k=$(this).data('responsive-key'); vals[k]=$(this).attr('type')==='checkbox'?$(this).is(':checked'):parseInt($(this).val(),10)||0; });
        responsiveValues[responsiveDevice]=vals;
    }
    function readFormSettings() {
        var style = {};
        Object.keys(STYLE_DEFAULTS).forEach(function(k){
            var id = '#style-' + k.replace(/_/g,'-');
            var $el = $(id);
            if ($el.length) style[k] = $el.attr('type') === 'checkbox' ? $el.is(':checked') : $el.val();
        });
        var msMap = {
            ms_next_bg:'set-ms-next-bg', ms_next_text:'set-ms-next-text', ms_next_hover_bg:'set-ms-next-hover-bg', ms_next_border:'set-ms-next-border', ms_next_border_width:'set-ms-next-border-width', ms_next_radius:'set-ms-next-radius', ms_next_padding_y:'set-ms-next-padding-y', ms_next_padding_x:'set-ms-next-padding-x',
            ms_prev_bg:'set-ms-prev-bg', ms_prev_text:'set-ms-prev-text', ms_prev_hover_bg:'set-ms-prev-hover-bg', ms_prev_border:'set-ms-prev-border', ms_prev_border_width:'set-ms-prev-border-width', ms_prev_radius:'set-ms-prev-radius', ms_prev_padding_y:'set-ms-prev-padding-y', ms_prev_padding_x:'set-ms-prev-padding-x', ms_nav_full_width_mobile:'set-ms-nav-full-width',
            ms_progress_color:'set-ms-progress-color', ms_track_color:'set-ms-track-color', ms_connector_color:'set-ms-connector-color',
            ms_active_color:'set-ms-active-color', ms_completed_color:'set-ms-completed-color', ms_inactive_color:'set-ms-inactive-color', ms_text_color:'set-ms-text-color',
            ms_shape:'set-ms-shape', ms_size:'set-ms-size', ms_bar_height:'set-ms-bar-height', ms_spacing:'set-ms-spacing',
            ms_show_percent:'set-ms-show-percent', ms_show_step_label:'set-ms-show-step-label', ms_show_titles:'set-ms-show-titles', ms_clickable:'set-ms-clickable', ms_animation:'set-ms-animation'
        };
        Object.keys(msMap).forEach(function(k){ var $el=$('#'+msMap[k]); if($el.length) style[k]=$el.attr('type')==='checkbox'?$el.is(':checked'):$el.val(); });
        captureResponsiveControls();
        style.responsive = responsiveValues;
        return {
            display_title: $('#set-display-title').val(),
            show_title: $('#set-show-title').is(':checked'),
            show_description: $('#set-show-description').is(':checked'),
            show_labels: $('#set-show-labels').is(':checked'),
            multistep_enabled: $('#set-multistep-enabled').is(':checked'),
            multistep_progress: $('#set-multistep-progress').val() || 'both',
            multistep_next_label: $('#set-multistep-next').val() || 'Next',
            multistep_prev_label: $('#set-multistep-prev').val() || 'Previous',
            multistep_submit_label: $('#set-multistep-submit').val() || ($('#set-submit-label').val() || 'Submit'),
            submit_label:  $('#set-submit-label').val(),
            success_msg:   $('#set-success-msg').val(),
            notify_email:  $('#set-notify-email').val(),
            email_subject: $('#set-email-subject').val(),
            send_copy:     $('#set-send-copy').is(':checked'),
            redirect_url:  $('#set-redirect-url').val(),
            primary_color: $('#set-primary-color').val(),
            integrations: {
                stripe: {
                    enabled: $('#set-stripe-enabled').is(':checked'),
                    amount: $('#set-stripe-amount').val(),
                    currency: $('#set-stripe-currency').val(),
                    description: $('#set-stripe-description').val()
                },
                google_calendar: {
                    enabled: $('#set-calendar-enabled').is(':checked'),
                    calendar_id: $('#set-calendar-id').val(),
                    event_label: $('#set-calendar-label').val(),
                    brand_name: $('#set-calendar-brand').val()
                }
            },
            style: style
        };
    }

    /* ── Live preview ───────────────────────────────────────────────────────── */
    function renderPreview() {
        var primary  = $('#set-primary-color').val() || '#667eea';
        var submitLbl = $('#set-submit-label').val() || 'Submit';
        var displayTitle = $('#set-display-title').val() || $('#ssb-form-name').val() || '';
        var showTitle = $('#set-show-title').length ? $('#set-show-title').is(':checked') : true;
        var showDescription = $('#set-show-description').length ? $('#set-show-description').is(':checked') : true;
        var st = readFormSettings().style || {};
        var rv = (st.responsive && st.responsive[responsiveDevice]) || RESPONSIVE_DEFAULTS[responsiveDevice];
        var previewBg = st.form_bg_transparent ? 'transparent' : (st.form_bg||'#fff');
        var html = '<div style="font-family:inherit;background:' + previewBg + ';border:' + (st.form_border_enabled === false ? 'none' : '1px solid ' + (st.form_border||'#edf0f3')) + ';border-radius:' + (rv.form_radius||24) + 'px;padding:' + (rv.form_padding||40) + 'px;box-sizing:border-box;width:100%;">';
        if (showTitle && displayTitle) html += '<h2 style="font-size:' + (rv.title_size||32) + 'px;line-height:1.2;font-weight:800;margin:0 0 12px;color:#111827;display:block!important;visibility:visible!important;">' + esc(displayTitle) + '</h2>';
        if (showDescription && $('#ssb-form-desc').val()) html += '<p style="font-size:' + (rv.description_size||16) + 'px;color:#6b7280;margin:0 0 20px;line-height:1.6;">' + esc($('#ssb-form-desc').val()).replace(/\n/g,'<br>') + '</p>';

        var previewMulti = $('#set-multistep-enabled').is(':checked');
        var previewMode = $('#set-multistep-progress').val() || 'both';
        var previewSteps = 1 + fields.filter(function(x){ return x.type === 'page_break'; }).length;
        if (previewMulti && previewSteps > 1 && (previewMode === 'both' || previewMode === 'steps')) {
            var activeColor = st.ms_active_color || '#667eea', inactiveColor = st.ms_inactive_color || '#eef2f7', textColor = st.ms_text_color || '#64748b', connectorColor = st.ms_connector_color || '#d9deea';
            var stepTitles = ['Step 1']; fields.forEach(function(x){ if(x.type==='page_break') stepTitles.push(x.step_title || x.label || ('Step '+(stepTitles.length+1))); });
            html += '<div style="display:flex;align-items:center;width:100%;margin:0 0 28px;overflow:hidden;">';
            for (var ps=0; ps<previewSteps; ps++) {
                html += '<div style="display:flex;align-items:center;gap:8px;flex:0 1 auto;min-width:0;color:'+(ps===0?activeColor:textColor)+';font-size:12px;font-weight:600;"><span style="width:'+(rv.ms_size||30)+'px;height:'+(rv.ms_size||30)+'px;border-radius:'+(st.ms_shape==='square'?'4px':(st.ms_shape==='rounded'?'10px':'50%'))+';background:'+(ps===0?activeColor:inactiveColor)+';color:'+(ps===0?'#fff':textColor)+';display:inline-flex;align-items:center;justify-content:center;flex:0 0 auto;">'+(ps+1)+'</span>' + ((rv.ms_show_titles!==false && st.ms_show_titles!==false)?'<span style="white-space:normal;line-height:1.2;">'+esc(stepTitles[ps])+'</span>':'') + '</div>';
                if(ps < previewSteps-1) html += '<span style="height:2px;background:'+connectorColor+';flex:1;min-width:18px;margin:0 '+(rv.ms_spacing||8)+'px;"></span>';
            }
            html += '</div>';
        }

        var i = 0;
        while (i < fields.length) {
            var f = fields[i];
            if (f.type === 'heading') {
                html += '<div style="font-size:1.1rem;font-weight:700;color:' + (st.heading_color||primary) + ';border-bottom:2px solid ' + (st.heading_color||primary) + ';padding-bottom:4px;margin-bottom:12px;">' + esc(f.label) + '</div>';
                i++; continue;
            }
            if (f.type === 'divider') {
                html += '<hr style="border:none;border-top:1px solid #e5e7eb;margin:12px 0;">';
                i++; continue;
            }
            if (f.type === 'page_break') {
                html += '<div style="margin:18px 0;padding:12px 14px;border:1px dashed #c7d2fe;background:#eef2ff;border-radius:10px;color:#4338ca;font-size:12px;font-weight:700;"><span class="dashicons dashicons-arrow-right-alt2"></span> Next step: ' + esc(f.step_title || f.label || 'Next Step') + (f.step_description ? '<div style="font-weight:400;color:#6366f1;margin-top:4px;">' + esc(f.step_description) + '</div>' : '') + '</div>';
                i++; continue;
            }

            var isHalf  = f.width === 'half';
            var isHalf2 = isHalf && fields[i+1] && fields[i+1].width === 'half';
            var rowStyle = isHalf2 ? 'display:flex;gap:12px;margin-bottom:14px;' : 'margin-bottom:14px;';

            html += '<div style="' + rowStyle + '">';
            html += previewField(f, primary, isHalf2 ? 'flex:1;' : 'width:100%;');

            if (isHalf2) {
                html += previewField(fields[i+1], primary, 'flex:1;');
                i++;
            }
            html += '</div>';
            i++;
        }

        if (previewMulti && previewSteps > 1) {
            var nextBg = st.ms_next_bg || '#0f172a', nextText = st.ms_next_text || '#ffffff', nextBorder = st.ms_next_border || '#0f172a';
            var prevBg = st.ms_prev_bg || '#f1f5f9', prevText = st.ms_prev_text || '#475569', prevBorder = st.ms_prev_border || '#e2e8f0';
            html += '<div style="margin-top:26px;padding-top:20px;border-top:1px solid #f1f5f9;display:flex;align-items:center;justify-content:flex-end;gap:12px;flex-wrap:wrap;">';
            html += '<button type="button" style="background:'+prevBg+';color:'+prevText+';border:'+(st.ms_prev_border_width!==undefined?st.ms_prev_border_width:1)+'px solid '+prevBorder+';border-radius:'+(st.ms_prev_radius!==undefined?st.ms_prev_radius:8)+'px;padding:'+(st.ms_prev_padding_y||10)+'px '+(st.ms_prev_padding_x||22)+'px;font-size:'+(rv.button_font_size||15)+'px;font-weight:700;">'+esc($('#set-multistep-prev').val()||'Previous')+'</button>';
            html += '<button type="button" style="background:'+nextBg+';color:'+nextText+';border:'+(st.ms_next_border_width!==undefined?st.ms_next_border_width:0)+'px solid '+nextBorder+';border-radius:'+(st.ms_next_radius!==undefined?st.ms_next_radius:8)+'px;padding:'+(st.ms_next_padding_y||10)+'px '+(st.ms_next_padding_x||22)+'px;font-size:'+(rv.button_font_size||15)+'px;font-weight:700;">'+esc($('#set-multistep-next').val()||'Next')+'</button>';
            html += '</div>';
        }

        if (previewMulti && previewSteps > 1 && (previewMode === 'both' || previewMode === 'bar')) {
            var pc = st.ms_progress_color || '#667eea', tc = st.ms_track_color || '#e9edf5', mt = st.ms_text_color || '#64748b';
            html += '<div style="margin-top:22px;">';
            if (previewMode === 'both') {
                html += '<div style="display:flex;justify-content:space-between;font-size:12px;font-weight:600;color:'+mt+';margin-bottom:6px;"><span>Overall Completion</span><span>0% Completed</span></div><div style="height:'+(rv.ms_bar_height||8)+'px;background:'+tc+';border-radius:999px;overflow:hidden;"><div style="height:100%;width:0%;background:'+pc+';border-radius:999px;"></div></div>';
            } else {
                html += '<div style="height:34px;background:'+tc+';border-radius:8px;overflow:hidden;position:relative;display:flex;align-items:center;"><div style="height:100%;width:0%;background:'+pc+';"></div><div style="position:absolute;inset:0;padding:0 14px;display:flex;justify-content:space-between;align-items:center;font-size:12px;font-weight:600;color:'+mt+';"><span>Step 1 of '+previewSteps+'</span><span>0%</span></div></div>';
            }
            html += '</div>';
        }
        html += '</div>';
        $('#ssb-live-preview').html(html);
    }

    function previewField(f, primary, extraStyle) {
        var formSettings = readFormSettings();
        var st = formSettings.style || {};
        var rv = (st.responsive && st.responsive[responsiveDevice]) || RESPONSIVE_DEFAULTS[responsiveDevice];
        st.show_labels = formSettings.show_labels;
        var fs = f.style_customized ? (f.style || {}) : {};
        var lbl = st.show_labels === false ? '' : '<label style="display:block;font-size:' + (rv.label_size||12) + 'px;font-weight:600;margin-bottom:4px;color:' + (st.label_color||'#6b7280') + ';">' + esc(f.label) + (f.required ? '<span style="color:#ef4444;"> *</span>' : '') + '</label>';
        var wrap = '<div style="' + extraStyle + '">' + lbl;
        var inputStyle = 'width:100%;padding:' + (fs.padding||rv.input_padding||st.input_padding||14) + 'px;border:' + (fs.border_width!==undefined?fs.border_width:(st.input_border_width||1)) + 'px solid ' + (fs.border||st.input_border||'#e5e7eb') + ';border-radius:' + (fs.radius||st.input_radius||12) + 'px;font-size:' + (fs.font_size||rv.input_font_size||st.input_font_size||15) + 'px;color:' + (fs.text||st.input_text||'#111827') + ';box-sizing:border-box;background:' + (fs.bg||st.input_bg||'#f9fafb') + ';';
        var ph = esc(f.placeholder || '');

        switch (f.type) {
            case 'textarea':
                wrap += '<textarea style="' + inputStyle + 'min-height:80px;" readonly placeholder="' + ph + '"></textarea>'; break;
            case 'select':
                wrap += '<select style="' + inputStyle + '" disabled><option>' + esc(f.placeholder || 'Choose…') + '</option>' +
                        (f.options||[]).map(function(o){ return '<option>' + esc(o) + '</option>'; }).join('') + '</select>'; break;
            case 'radio':
                wrap += '<div>' + (f.options||[]).map(function(o){
                    return '<label style="display:flex;align-items:center;gap:6px;margin-bottom:4px;font-size:.85rem;"><input type="radio" disabled> ' + esc(o) + '</label>';
                }).join('') + '</div>'; break;
            case 'checkbox':
                wrap += '<div>' + (f.options||[]).map(function(o){
                    return '<label style="display:flex;align-items:center;gap:6px;margin-bottom:4px;font-size:.85rem;"><input type="checkbox" disabled> ' + esc(o) + '</label>';
                }).join('') + '</div>'; break;
            case 'date':
                wrap += '<input type="date" style="' + inputStyle + '" readonly>'; break;
            case 'time':
                wrap += '<input type="time" style="' + inputStyle + '" readonly>'; break;
            case 'datetime':
                wrap += '<div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;"><input type="date" style="' + inputStyle + '" readonly><input type="time" style="' + inputStyle + '" readonly></div>'; break;
            case 'number':
                wrap += '<input type="number" style="' + inputStyle + '" placeholder="' + ph + '" readonly>'; break;
            case 'toggle':
                wrap += '<label style="display:flex;align-items:center;gap:10px;font-size:.85rem;"><input type="checkbox" disabled><span>On / Off</span></label>'; break;
            case 'name':
                if (f.name_mode === 'full') wrap += '<input type="text" style="' + inputStyle + '" placeholder="Full name" readonly>'; else wrap += '<div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;"><input type="text" style="' + inputStyle + '" placeholder="First name" readonly><input type="text" style="' + inputStyle + '" placeholder="Last name" readonly></div>'; break;
            case 'address':
                wrap += '<div style="display:grid;gap:8px;"><input type="text" style="' + inputStyle + '" placeholder="Street address" readonly><div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;"><input type="text" style="' + inputStyle + '" placeholder="City" readonly><input type="text" style="' + inputStyle + '" placeholder="State / Province" readonly></div><div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;"><input type="text" style="' + inputStyle + '" placeholder="ZIP / Postal Code" readonly><input type="text" style="' + inputStyle + '" placeholder="Country" readonly></div></div>'; break;
            case 'file':
                wrap += '<input type="file" style="' + inputStyle + '" ' + (f.file_multiple?'multiple':'') + ' disabled>'; break;
            case 'hidden':
                wrap += '<div style="padding:8px 10px;background:#f3f4f6;border-radius:8px;color:#6b7280;font-size:.75rem;">Hidden value (not shown to visitors)</div>'; break;
            case 'terms':
                wrap += '<label style="display:flex;gap:8px;align-items:flex-start;font-size:.82rem;line-height:1.5;"><input type="checkbox" disabled> ' + esc(f.terms_text||'I agree to the Terms and Privacy Policy.') + '</label>'; break;
            case 'spam_protection':
                wrap += '<div style="padding:10px;border:1px dashed #cbd5e1;border-radius:8px;color:#64748b;font-size:.78rem;"><span class="dashicons dashicons-shield"></span> Spam protection (' + esc(f.spam_mode||'honeypot') + ')</div>'; break;
            case 'booking':
                wrap += '<div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;"><input type="date" style="' + inputStyle + '" readonly><input type="time" style="' + inputStyle + '" readonly></div><select style="' + inputStyle + 'margin-top:8px;" disabled><option>Timezone</option></select>'; break;
            case 'repeater':
                var children=f.child_fields||[]; var count=Math.max(1,Math.min(3,parseInt(f.default_items||1,10))); wrap += '<div style="border:1px solid #e5e7eb;border-radius:10px;padding:12px;background:#fff;"><div style="font-weight:800;font-size:12px;margin-bottom:8px;">'+esc(f.item_label||'Item')+'</div>';
                for(var ri=0;ri<count;ri++){ wrap += '<div style="border:1px solid #eef2f7;border-radius:8px;padding:10px;margin-bottom:8px;background:#fafafa;"><div style="font-size:11px;font-weight:700;color:#64748b;margin-bottom:8px;">'+esc(f.item_label||'Item')+' '+(ri+1)+'</div>'; children.forEach(function(ch){ wrap += previewField(ch,primary,'margin-bottom:8px;'); }); wrap += '</div>'; }
                wrap += '<button type="button" style="border:1px dashed #cbd5e1;background:#fff;border-radius:7px;padding:7px 10px;color:#667eea;font-size:11px;font-weight:700;">+ '+esc(f.add_text||'Add Another')+'</button></div>'; break;
            case 'submit':
                var buttonAlign = st.button_alignment || (st.button_full_width ? 'full' : 'left');
                var buttonAlignStyle = buttonAlign === 'center' ? 'text-align:center;' : (buttonAlign === 'right' ? 'text-align:right;' : 'text-align:left;');
                var buttonWidthStyle = (buttonAlign === 'full' || st.button_full_width) ? 'width:100%;' : '';
                wrap = '<div style="' + extraStyle + buttonAlignStyle + '"><button type="button" style="padding:' + (rv.button_padding_y||15) + 'px ' + (rv.button_padding_x||34) + 'px;background:' + (st.button_bg||primary) + ';color:' + (st.button_text||'#fff') + ';border:none;border-radius:' + (st.button_radius||999) + 'px;font-size:' + (rv.button_font_size||15) + 'px;font-weight:700;cursor:default;' + buttonWidthStyle + '">' + esc(f.submit_text||'Submit') + '</button></div>'; return wrap;
            case 'phone':
                if (f.enable_country_code) {
                    wrap += '<div style="display:flex;gap:6px;">' +
                            '<select style="width:130px;padding:8px 6px;border:2px solid #e5e7eb;border-radius:8px;font-size:.8rem;background:#f9fafb;" disabled><option>US +1</option><option>UK +44</option><option>DE +49</option></select>' +
                            '<input type="tel" style="flex:1;padding:8px 10px;border:2px solid #e5e7eb;border-radius:8px;font-size:.85rem;background:#f9fafb;" placeholder="Phone number" readonly>' +
                            '</div>';
                } else {
                    wrap += '<input type="tel" style="' + inputStyle + '" placeholder="+1 234 567 8900" readonly>';
                }
                break;
            case 'timeslot':
                if (f.timeslot_mode === 'radio') {
                    wrap += '<div style="display:flex;flex-wrap:wrap;gap:8px;">' + (f.options||[]).map(function(o){
                        return '<label style="display:flex;align-items:center;gap:5px;padding:6px 12px;border:2px solid #e5e7eb;border-radius:8px;font-size:.82rem;background:#f9fafb;cursor:not-allowed;">' +
                               '<input type="radio" disabled> ' + esc(o) + '</label>';
                    }).join('') + '</div>';
                } else {
                    wrap += '<select style="' + inputStyle + '" disabled>' +
                            '<option>Select a time slot…</option>' +
                            (f.options||[]).map(function(o){ return '<option>' + esc(o) + '</option>'; }).join('') +
                            '</select>';
                }
                break;
            case 'timezone':
                wrap += '<select style="' + inputStyle + '" disabled>' +
                        '<option>Select your timezone…</option>' +
                        '<option>UTC-12:00 — Baker Island</option>' +
                        '<option>UTC-08:00 — Pacific Time (US)</option>' +
                        '<option>UTC+00:00 — London / UTC</option>' +
                        '<option>UTC+05:30 — India Standard Time</option>' +
                        '<option>UTC+08:00 — China / Singapore</option>' +
                        '</select>';
                break;
            default:
                wrap += '<input type="' + f.type + '" style="' + inputStyle + '" placeholder="' + ph + '" readonly>';
        }
        if (f.help_text) wrap += '<p style="font-size:.7rem;color:#9ca3af;margin:3px 0 0;">' + esc(f.help_text) + '</p>';
        return wrap + '</div>';
    }

    /* ── Count update ───────────────────────────────────────────────────────── */
    function updateCount() {
        $('#ssb-field-count').text(fields.filter(function(f){ return f.type !== 'divider'; }).length);
    }

    /* ── Save ───────────────────────────────────────────────────────────────── */
    $('#ssb-save-form').on('click', function(){
        var name = $('#ssb-form-name').val().trim();
        if (!name) { builderToast('Please enter a form name.', 'error'); $('#ssb-form-name').focus(); return; }
        if (!fields.length) { builderToast('Please add at least one field.', 'error'); return; }

        var $btn = $(this);
        $btn.prop('disabled', true).text('Saving…');
        $('#ssb-save-status').text('');

        var payload = {
            action:      'ssb_save_form',
            nonce:       ssbForms.nonce,
            form_id:     $('#ssb-form-id').val() || 0,
            form_name:   name,
            description: $('#ssb-form-desc').val(),
            fields:      JSON.stringify(fields),
            settings:    JSON.stringify(readFormSettings()),
        };

        $.post(ssbForms.ajaxUrl, payload, function(r){
            $btn.prop('disabled', false).text('Save Form');
            if (r.success) {
                var newId = r.data.form_id;
                $('#ssb-form-id').val(newId);
                $('#ssb-save-status').css('color','#10b981').text('Saved');
                builderToast('Form saved successfully.', 'success');
                // Update shortcode display
                $('.ssb-save-bar-info strong:last').text('[ssb_form id="' + newId + '"]');

                setTimeout(function(){
                    if (!window.location.href.includes('form_id=')) {
                        history.replaceState(null,'','?page=ssb-forms&ssb_action=edit&form_id=' + newId);
                    }
                    $('#ssb-save-status').text('');
                }, 3000);
            } else {
                $('#ssb-save-status').css('color','#ef4444').text('Save failed');
                builderToast((r.data && r.data.msg) || 'Save failed.', 'error');
            }
        }).fail(function(){
            $btn.prop('disabled', false).text('Save Form');
            $('#ssb-save-status').css('color','#ef4444').text('Network error');
            builderToast('Network error while saving the form.', 'error');
        });
    });

    /* ── Responsive device controls ───────────────────────────────────────── */
    $(document).on('click','.ssb-responsive-device',function(){ captureResponsiveControls(); loadResponsiveControls($(this).data('device')); renderPreview(); });
    $(document).on('input change','.ssb-responsive-input',function(){ captureResponsiveControls(); renderPreview(); });
    $(document).on('click','.ssb-preview-device',function(){ var d=$(this).data('preview-device'); $('.ssb-preview-device').removeClass('active').filter('[data-preview-device="'+d+'"]').addClass('active'); $('#ssb-live-preview').attr('data-preview-device',d); loadResponsiveControls(d); renderPreview(); });

    /* ── Form settings live preview binding ─────────────────────────────────── */
    $('#set-submit-label, #set-primary-color, #set-display-title, #set-show-title, #set-show-description, #set-show-labels, #set-multistep-enabled, #set-multistep-progress, #set-ms-progress-color, #set-ms-track-color, #set-ms-connector-color, #set-ms-active-color, #set-ms-completed-color, #set-ms-inactive-color, #set-ms-text-color, #set-ms-shape, #set-ms-size, #set-ms-bar-height, #set-ms-spacing, #set-ms-show-titles, #ssb-form-name, #ssb-form-desc').on('input change', function(){ renderPreview(); });
    $('#set-ms-next-bg, #set-ms-next-text, #set-ms-next-hover-bg, #set-ms-next-border, #set-ms-next-border-width, #set-ms-next-radius, #set-ms-next-padding-y, #set-ms-next-padding-x, #set-ms-prev-bg, #set-ms-prev-text, #set-ms-prev-hover-bg, #set-ms-prev-border, #set-ms-prev-border-width, #set-ms-prev-radius, #set-ms-prev-padding-y, #set-ms-prev-padding-x, #set-ms-nav-full-width, #set-multistep-next, #set-multistep-prev').on('input change', function(){ renderPreview(); });

    /* ── Keyboard shortcut ──────────────────────────────────────────────────── */
    $(document).on('keydown', function(e){
        if ((e.ctrlKey || e.metaKey) && e.key === 's') {
            e.preventDefault();
            $('#ssb-save-form').trigger('click');
        }
    });

    /* ── Canvas drag-over highlight ─────────────────────────────────────────── */
    $('#ssb-drop-zone').on('sortover', function(){ $(this).addClass('drag-over'); })
                       .on('sortout',  function(){ $(this).removeClass('drag-over'); });

    /* ── Initialise ─────────────────────────────────────────────────────────── */
    // Seed settings inputs from stored data
    if (settings.notify_email)  $('#set-notify-email').val(settings.notify_email);
    if (settings.email_subject) $('#set-email-subject').val(settings.email_subject);
    if (settings.display_title !== undefined) $('#set-display-title').val(settings.display_title);
    $('#set-show-title').prop('checked', settings.show_title !== false);
    $('#set-show-description').prop('checked', settings.show_description !== false);
    $('#set-show-labels').prop('checked', settings.show_labels !== false);
    $('#set-multistep-enabled').prop('checked', !!settings.multistep_enabled);
    var storedProgressMode = settings.multistep_progress || 'both'; if (storedProgressMode === 'numbers' || storedProgressMode === 'tabs') storedProgressMode = 'steps'; $('#set-multistep-progress').val(storedProgressMode);
    $('#set-multistep-next').val(settings.multistep_next_label || 'Next');
    $('#set-multistep-prev').val(settings.multistep_prev_label || 'Previous');
    $('#set-multistep-submit').val(settings.multistep_submit_label || settings.submit_label || 'Submit');
    var msStyle = settings.style || {};
    $('#set-ms-next-bg').val(msStyle.ms_next_bg || '#0f172a');
    $('#set-ms-next-text').val(msStyle.ms_next_text || '#ffffff');
    $('#set-ms-next-hover-bg').val(msStyle.ms_next_hover_bg || '#1e293b');
    $('#set-ms-next-border').val(msStyle.ms_next_border || '#0f172a');
    $('#set-ms-next-border-width').val(msStyle.ms_next_border_width !== undefined ? msStyle.ms_next_border_width : 0);
    $('#set-ms-next-radius').val(msStyle.ms_next_radius !== undefined ? msStyle.ms_next_radius : 8);
    $('#set-ms-next-padding-y').val(msStyle.ms_next_padding_y !== undefined ? msStyle.ms_next_padding_y : 10);
    $('#set-ms-next-padding-x').val(msStyle.ms_next_padding_x !== undefined ? msStyle.ms_next_padding_x : 22);
    $('#set-ms-prev-bg').val(msStyle.ms_prev_bg || '#f1f5f9');
    $('#set-ms-prev-text').val(msStyle.ms_prev_text || '#475569');
    $('#set-ms-prev-hover-bg').val(msStyle.ms_prev_hover_bg || '#e2e8f0');
    $('#set-ms-prev-border').val(msStyle.ms_prev_border || '#e2e8f0');
    $('#set-ms-prev-border-width').val(msStyle.ms_prev_border_width !== undefined ? msStyle.ms_prev_border_width : 1);
    $('#set-ms-prev-radius').val(msStyle.ms_prev_radius !== undefined ? msStyle.ms_prev_radius : 8);
    $('#set-ms-prev-padding-y').val(msStyle.ms_prev_padding_y !== undefined ? msStyle.ms_prev_padding_y : 10);
    $('#set-ms-prev-padding-x').val(msStyle.ms_prev_padding_x !== undefined ? msStyle.ms_prev_padding_x : 22);
    $('#set-ms-nav-full-width').prop('checked', !!msStyle.ms_nav_full_width_mobile);
    $('#set-ms-progress-color').val(msStyle.ms_progress_color || '#667eea');
    $('#set-ms-track-color').val(msStyle.ms_track_color || '#e9edf5');
    $('#set-ms-connector-color').val(msStyle.ms_connector_color || '#d9deea');
    $('#set-ms-active-color').val(msStyle.ms_active_color || '#667eea');
    $('#set-ms-completed-color').val(msStyle.ms_completed_color || '#667eea');
    $('#set-ms-inactive-color').val(msStyle.ms_inactive_color || '#eef2f7');
    $('#set-ms-text-color').val(msStyle.ms_text_color || '#64748b');
    $('#set-ms-shape').val(msStyle.ms_shape || 'circle');
    $('#set-ms-size').val(msStyle.ms_size || 30);
    $('#set-ms-bar-height').val(msStyle.ms_bar_height || 8);
    $('#set-ms-spacing').val(msStyle.ms_spacing || 10);
    $('#set-ms-show-percent').prop('checked', msStyle.ms_show_percent !== false);
    $('#set-ms-show-step-label').prop('checked', msStyle.ms_show_step_label !== false);
    $('#set-ms-show-titles').prop('checked', msStyle.ms_show_titles !== false);
    $('#set-ms-clickable').prop('checked', msStyle.ms_clickable !== false);
    $('#set-ms-animation').val(msStyle.ms_animation || 'slide');
    responsiveValues = $.extend(true, responsiveValues, msStyle.responsive || {});
    loadResponsiveControls('desktop');
    if (settings.primary_color) $('#set-primary-color').val(settings.primary_color);
    if (settings.redirect_url)  $('#set-redirect-url').val(settings.redirect_url);
    if (settings.send_copy)     $('#set-send-copy').prop('checked', true);

    // Restore per-form integration and style settings.
    var integ = settings.integrations || {};
    var stripe = integ.stripe || {}, cal = integ.google_calendar || {};
    $('#set-stripe-enabled').prop('checked', !!stripe.enabled);
    $('#set-stripe-amount').val(stripe.amount || 0);
    $('#set-stripe-currency').val(stripe.currency || 'USD');
    $('#set-stripe-description').val(stripe.description || '');
    $('#set-calendar-enabled').prop('checked', !!cal.enabled);
    $('#set-calendar-id').val(cal.calendar_id || 'primary');
    $('#set-calendar-label').val(cal.event_label || '');
    $('#set-calendar-brand').val(cal.brand_name || '');
    function toggleIntegrationPanels(){
        $('#ssb-stripe-settings').toggle($('#set-stripe-enabled').is(':checked'));
        $('#ssb-calendar-settings').toggle($('#set-calendar-enabled').is(':checked'));
    }
    $('#set-stripe-enabled,#set-calendar-enabled').on('change', toggleIntegrationPanels);
    toggleIntegrationPanels();

    var savedStyle = settings.style || {};
    Object.keys(STYLE_DEFAULTS).forEach(function(k){
        var id = '#style-' + k.replace(/_/g,'-');
        var $el=$(id);
        if($el.length){
            var val = savedStyle[k] !== undefined ? savedStyle[k] : STYLE_DEFAULTS[k];
            if($el.attr('type') === 'checkbox') $el.prop('checked', !!val); else $el.val(val);
        }
    });
    $('.ssb-inspector-body').on('input change','[id^="style-"]',function(){ renderPreview(); });
    // Submit button alignment segmented control. Keep the hidden value and visual state in sync.
    function setSubmitButtonAlignment(value, refresh){
        value = ['left','center','right','full'].indexOf(value) >= 0 ? value : 'left';
        $('#style-button-alignment').val(value);
        $('.ssb-button-align-btn').removeClass('active').attr('aria-pressed','false');
        $('.ssb-button-align-btn[data-align="'+value+'"]').addClass('active').attr('aria-pressed','true');
        if (refresh !== false) renderPreview();
    }
    $(document).off('click.formpilotSubmitAlignment', '.ssb-button-align-btn').on('click.formpilotSubmitAlignment', '.ssb-button-align-btn', function(e){
        e.preventDefault();
        e.stopPropagation();
        setSubmitButtonAlignment($(this).attr('data-align'));
    });
    setSubmitButtonAlignment($('#style-button-alignment').val() || savedStyle.button_alignment || 'left', false);
    // Hex is the primary color input; native picker is a secondary convenience.
    $('.ssb-color-control').each(function(){
        var $wrap=$(this), $hex=$wrap.find('.ssb-color-hex'), $picker=$wrap.find('.ssb-color-picker');
        function validHex(v){ return /^#[0-9a-fA-F]{6}$/.test(v); }
        $hex.on('input change', function(){ var v=this.value.trim(); if(validHex(v)) $picker.val(v); renderPreview(); });
        $picker.on('input change', function(){ $hex.val(this.value.toLowerCase()); renderPreview(); });
    });


    $(document).on('input change', '.ssb-color-picker', function(){
        var target=$(this).data('color-for');
        if(target) $('#'+target).val(this.value.toLowerCase()).trigger('input');
    });
    $(document).on('input change', '.ssb-color-hex', function(){
        var v=this.value.trim();
        if(/^#[0-9a-fA-F]{6}$/.test(v)){ var picker=$('.ssb-color-picker[data-color-for="'+this.id+'"]'); if(picker.length) picker.val(v); }
    });

    renderCanvas();
    if (fields.length) selectField(fields[0].id);
});
