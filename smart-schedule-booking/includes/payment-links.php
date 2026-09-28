<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * FormPilot Pro — Stripe Payment Link buttons.
 * Payment Links are created in Stripe from a fixed Product + Price and the
 * generated buy.stripe.com URL is stored locally for use via shortcode.
 */
function ssb_get_payment_buttons() {
    $buttons = get_option('ssb_stripe_payment_buttons', []);
    return is_array($buttons) ? $buttons : [];
}

function ssb_save_payment_buttons($buttons) {
    update_option('ssb_stripe_payment_buttons', array_values($buttons), false);
}

function ssb_stripe_api_request($method, $path, $body = []) {
    $secret = trim((string) get_option('ssb_stripe_secret_key', ''));
    if (!$secret) return new WP_Error('missing_key', 'Stripe Secret Key is not configured.');
    $args = [
        'method'  => strtoupper($method),
        'headers' => [
            'Authorization' => 'Bearer ' . $secret,
            'Content-Type'  => 'application/x-www-form-urlencoded; charset=UTF-8',
        ],
        'timeout' => 30,
    ];
    if (!empty($body)) $args['body'] = http_build_query($body, '', '&');
    $response = wp_remote_request('https://api.stripe.com/v1/' . ltrim($path, '/'), $args);
    if (is_wp_error($response)) return $response;
    $code = wp_remote_retrieve_response_code($response);
    $data = json_decode(wp_remote_retrieve_body($response), true);
    if ($code < 200 || $code >= 300) {
        return new WP_Error('stripe_api', $data['error']['message'] ?? 'Stripe returned an error.', ['status' => $code, 'data' => $data]);
    }
    return is_array($data) ? $data : new WP_Error('stripe_json', 'Invalid response from Stripe.');
}

function ssb_create_stripe_payment_link($name, $amount, $currency, $description = '', $return_url = '') {
    $name = sanitize_text_field($name);
    $currency = strtolower(sanitize_key($currency));
    $amount = (float) $amount;
    if ($name === '') return new WP_Error('invalid_name', 'Button/product name is required.');
    if ($amount <= 0) return new WP_Error('invalid_amount', 'Amount must be greater than 0.');
    if (!preg_match('/^[a-z]{3}$/', $currency)) return new WP_Error('invalid_currency', 'Currency must be a 3-letter ISO currency code.');

    $product = ssb_stripe_api_request('POST', 'products', [
        'name'        => $name,
        'description' => sanitize_text_field($description),
        'metadata[formpilot]' => '1',
    ]);
    if (is_wp_error($product)) return $product;

    $price = ssb_stripe_api_request('POST', 'prices', [
        'unit_amount' => (string) max(1, (int) round($amount * 100)),
        'currency'    => $currency,
        'product'     => $product['id'],
        'metadata[formpilot]' => '1',
    ]);
    if (is_wp_error($price)) return $price;

    $link_body = [
        'line_items[0][price]'    => $price['id'],
        'line_items[0][quantity]' => '1',
        'metadata[formpilot]'    => '1',
    ];
    if ($return_url && filter_var($return_url, FILTER_VALIDATE_URL)) {
        $link_body['after_completion[type]'] = 'redirect';
        $link_body['after_completion[redirect][url]'] = esc_url_raw($return_url);
    }
    $link = ssb_stripe_api_request('POST', 'payment_links', $link_body);
    if (is_wp_error($link)) return $link;

    return [
        'stripe_id'  => sanitize_text_field($link['id'] ?? ''),
        'url'        => esc_url_raw($link['url'] ?? ''),
        'product_id' => sanitize_text_field($product['id'] ?? ''),
        'price_id'   => sanitize_text_field($price['id'] ?? ''),
    ];
}

function ssb_deactivate_stripe_payment_link($stripe_id) {
    if (!$stripe_id) return false;
    $result = ssb_stripe_api_request('POST', 'payment_links/' . rawurlencode($stripe_id), ['active' => 'false']);
    return !is_wp_error($result);
}

function ssb_payment_buttons_page() {
    if ( function_exists('ssb_license_admin_guard') && ! ssb_license_admin_guard('payment_links') ) return;
    if (!current_user_can('manage_options')) wp_die('Unauthorized');
    $notice = '';
    $notice_type = 'success';

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ssb_payment_button_action'])) {
        check_admin_referer('ssb_payment_buttons');
        $action = sanitize_key($_POST['ssb_payment_button_action']);
        $buttons = ssb_get_payment_buttons();

        if ($action === 'create') {
            $name = sanitize_text_field(wp_unslash($_POST['button_name'] ?? ''));
            $amount = (float) ($_POST['amount'] ?? 0);
            $currency = sanitize_text_field(wp_unslash($_POST['currency'] ?? 'USD'));
            $description = sanitize_text_field(wp_unslash($_POST['description'] ?? ''));
            $return_url = esc_url_raw(wp_unslash($_POST['return_url'] ?? ''));
            $text = sanitize_text_field(wp_unslash($_POST['button_text'] ?? 'Pay Now')) ?: 'Pay Now';
            $result = ssb_create_stripe_payment_link($name, $amount, $currency, $description, $return_url);
            if (is_wp_error($result)) {
                $notice_type = 'error';
                $notice = $result->get_error_message();
            } elseif (empty($result['url'])) {
                $notice_type = 'error';
                $notice = 'Stripe created the link but did not return a payment URL.';
            } else {
                $id = 1;
                foreach ($buttons as $b) $id = max($id, ((int) ($b['id'] ?? 0)) + 1);
                $buttons[] = [
                    'id' => $id,
                    'name' => $name,
                    'amount' => round($amount, 2),
                    'currency' => strtoupper(substr($currency, 0, 3)),
                    'description' => $description,
                    'button_text' => $text,
                    'url' => $result['url'],
                    'stripe_id' => $result['stripe_id'],
                    'product_id' => $result['product_id'],
                    'price_id' => $result['price_id'],
                    'created_at' => current_time('mysql'),
                ];
                ssb_save_payment_buttons($buttons);
                $notice = 'Payment button created in Stripe.';
            }
        } elseif ($action === 'delete') {
            $id = absint($_POST['button_id'] ?? 0);
            foreach ($buttons as $idx => $button) {
                if ((int) ($button['id'] ?? 0) === $id) {
                    ssb_deactivate_stripe_payment_link($button['stripe_id'] ?? '');
                    unset($buttons[$idx]);
                    ssb_save_payment_buttons($buttons);
                    $notice = 'Payment button removed and its Stripe Payment Link was deactivated.';
                    break;
                }
            }
        }
    }

    $buttons = ssb_get_payment_buttons();
    ?>
    <div class="wrap ssb-payment-buttons-page">
        <div class="ssb-pb-hero">
            <div>
                <span class="ssb-pb-kicker">FormPilot Pro</span>
                <h1>Stripe Payment Buttons</h1>
                <p>Create a fixed-price Stripe Payment Link, then place the generated shortcode anywhere in WordPress.</p>
            </div>
            <div class="ssb-pb-badge">Hosted by Stripe</div>
        </div>
        <?php if ($notice): ?><div class="ssb-pb-notice <?php echo esc_attr($notice_type); ?>"><?php echo esc_html($notice); ?></div><?php endif; ?>

        <div class="ssb-pb-grid">
            <div class="ssb-pb-card">
                <div class="ssb-pb-card-head"><h2>Create payment button</h2><span>1. Fill in → 2. Create → 3. Copy shortcode</span></div>
                <form method="post">
                    <?php wp_nonce_field('ssb_payment_buttons'); ?>
                    <input type="hidden" name="ssb_payment_button_action" value="create">
                    <div class="ssb-pb-fields">
                        <label>Button / Product Name<input required type="text" name="button_name" placeholder="Consultation Fee"></label>
                        <label>Amount<input required type="number" name="amount" min="0.01" step="0.01" placeholder="100.00"></label>
                        <label>Currency<input required type="text" name="currency" value="USD" maxlength="3" placeholder="USD"></label>
                        <label>Button Text<input type="text" name="button_text" value="Pay Now" placeholder="Pay Now"></label>
                        <label class="wide">Payment Description<input type="text" name="description" placeholder="Payment for consultation"></label>
                        <label class="wide">After Payment Redirect URL <span class="optional">(optional)</span><input type="url" name="return_url" placeholder="https://example.com/payment-success/"></label>
                    </div>
                    <div class="ssb-pb-help">The amount is fixed in Stripe. Visitors are sent to Stripe's hosted checkout/payment-link page, so your secret key never appears in the browser.</div>
                    <button class="button button-primary button-large" type="submit">Create Stripe Payment Link</button>
                </form>
            </div>

            <div class="ssb-pb-card">
                <div class="ssb-pb-card-head"><h2>Your buttons</h2><span><?php echo count($buttons); ?> saved</span></div>
                <?php if (!$buttons): ?>
                    <div class="ssb-pb-empty"><div>💳</div><strong>No payment buttons yet</strong><span>Create one on the left and the shortcode will appear here.</span></div>
                <?php else: foreach ($buttons as $button): ?>
                    <div class="ssb-pb-item">
                        <div class="ssb-pb-item-top"><strong><?php echo esc_html($button['name']); ?></strong><span><?php echo esc_html($button['currency'] . ' ' . number_format((float)$button['amount'], 2)); ?></span></div>
                        <div class="ssb-pb-shortcode" data-copy="[ssb_stripe_button id=&quot;<?php echo (int)$button['id']; ?>&quot;]"><code>[ssb_stripe_button id="<?php echo (int)$button['id']; ?>"]</code><button type="button" class="ssb-pb-copy">Copy</button></div>
                        <div class="ssb-pb-actions"><a href="<?php echo esc_url($button['url']); ?>" target="_blank" rel="noopener noreferrer">Open Stripe Link ↗</a><form method="post" onsubmit="return confirm('Deactivate this Stripe Payment Link?');"><?php wp_nonce_field('ssb_payment_buttons'); ?><input type="hidden" name="ssb_payment_button_action" value="delete"><input type="hidden" name="button_id" value="<?php echo (int)$button['id']; ?>"><button type="submit" class="link-delete">Remove</button></form></div>
                    </div>
                <?php endforeach; endif; ?>
            </div>
        </div>
    </div>
    <style>
    .ssb-payment-buttons-page{max-width:1200px;margin-top:24px}.ssb-pb-hero{display:flex;justify-content:space-between;gap:24px;align-items:center;background:linear-gradient(135deg,#111827,#312e81);color:#fff;padding:28px 32px;border-radius:18px;margin-bottom:18px}.ssb-pb-kicker{font-size:11px;text-transform:uppercase;letter-spacing:.12em;opacity:.7}.ssb-pb-hero h1{color:#fff;margin:5px 0 6px;font-size:30px}.ssb-pb-hero p{margin:0;color:#cbd5e1;font-size:14px}.ssb-pb-badge{padding:9px 13px;border:1px solid rgba(255,255,255,.18);border-radius:999px;font-size:12px;white-space:nowrap}.ssb-pb-grid{display:grid;grid-template-columns:minmax(0,1fr) minmax(0,1fr);gap:18px}.ssb-pb-card{background:#fff;border:1px solid #e5e7eb;border-radius:16px;padding:22px;box-shadow:0 4px 20px rgba(15,23,42,.05)}.ssb-pb-card-head{display:flex;justify-content:space-between;gap:15px;align-items:baseline;margin-bottom:18px}.ssb-pb-card-head h2{margin:0;font-size:17px}.ssb-pb-card-head span{font-size:12px;color:#64748b}.ssb-pb-fields{display:grid;grid-template-columns:1fr 1fr;gap:13px}.ssb-pb-fields label{font-size:12px;font-weight:700;color:#475569}.ssb-pb-fields label.wide{grid-column:1/-1}.ssb-pb-fields .optional{font-weight:500;color:#94a3b8}.ssb-pb-fields input{display:block;width:100%;margin-top:6px;box-sizing:border-box;border:1px solid #dbe1ea;border-radius:9px;padding:10px 11px;font-size:13px}.ssb-pb-fields input:focus{outline:none;border-color:#6366f1;box-shadow:0 0 0 3px rgba(99,102,241,.1)}.ssb-pb-help{font-size:12px;line-height:1.6;color:#64748b;background:#f8fafc;border-radius:10px;padding:11px 12px;margin:15px 0}.ssb-pb-notice{padding:12px 15px;border-radius:10px;margin:0 0 18px;font-size:13px}.ssb-pb-notice.success{background:#ecfdf5;color:#065f46;border:1px solid #a7f3d0}.ssb-pb-notice.error{background:#fef2f2;color:#991b1b;border:1px solid #fecaca}.ssb-pb-item{border:1px solid #e5e7eb;border-radius:12px;padding:13px;margin-bottom:10px}.ssb-pb-item-top{display:flex;justify-content:space-between;gap:10px;margin-bottom:9px}.ssb-pb-item-top strong{color:#1e293b}.ssb-pb-item-top span{font-weight:800;color:#4f46e5}.ssb-pb-shortcode{display:flex;align-items:center;gap:8px;background:#f8fafc;border:1px dashed #cbd5e1;border-radius:8px;padding:7px 8px}.ssb-pb-shortcode code{flex:1;white-space:nowrap;overflow:auto}.ssb-pb-copy{border:0;background:#111827;color:#fff;border-radius:6px;padding:5px 9px;cursor:pointer;font-size:11px}.ssb-pb-actions{display:flex;justify-content:space-between;align-items:center;margin-top:9px;font-size:12px}.ssb-pb-actions a{color:#4f46e5;text-decoration:none}.ssb-pb-actions form{margin:0}.ssb-pb-actions .link-delete{border:0;background:none;color:#dc2626;cursor:pointer;padding:0}.ssb-pb-empty{display:flex;align-items:center;justify-content:center;flex-direction:column;gap:6px;min-height:240px;color:#64748b;text-align:center}.ssb-pb-empty div{font-size:38px}.ssb-pb-empty strong{color:#334155}.ssb-pb-empty span{font-size:12px}@media(max-width:900px){.ssb-pb-grid{grid-template-columns:1fr}.ssb-pb-hero{align-items:flex-start;flex-direction:column}.ssb-pb-fields{grid-template-columns:1fr}.ssb-pb-fields label.wide{grid-column:auto}}
    </style>
    <script>jQuery(function($){$('.ssb-pb-copy').on('click',function(){var v=$(this).closest('.ssb-pb-shortcode').find('code').text();navigator.clipboard&&navigator.clipboard.writeText(v);var b=$(this);b.text('Copied');setTimeout(function(){b.text('Copy')},1200);});});</script>
    <?php
}

add_shortcode('ssb_stripe_button', 'ssb_render_stripe_button');
function ssb_render_stripe_button($atts) {
    if ( function_exists('ssb_license_can') && ! ssb_license_can('payment_links') ) return '';
    $atts = shortcode_atts(['id'=>0,'text'=>'','class'=>'','new_tab'=>'1'], $atts, 'ssb_stripe_button');
    $id = absint($atts['id']);
    if (!$id) return '';
    foreach (ssb_get_payment_buttons() as $button) {
        if ((int)($button['id'] ?? 0) !== $id || empty($button['url'])) continue;
        $text = sanitize_text_field($atts['text']) ?: ($button['button_text'] ?? 'Pay Now');
        $target = ($atts['new_tab'] === '0') ? '_self' : '_blank';
        $class = sanitize_html_class($atts['class']);
        $class_attr = 'ssb-stripe-pay-button' . ($class ? ' ' . $class : '');
        return '<a class="' . esc_attr($class_attr) . '" href="' . esc_url($button['url']) . '" target="' . esc_attr($target) . '" rel="noopener noreferrer">' . esc_html($text) . '</a>';
    }
    return '';
}

add_action('wp_head', 'ssb_stripe_button_css');
function ssb_stripe_button_css() {
    if (is_admin()) return;
    echo '<style>.ssb-stripe-pay-button{display:inline-flex;align-items:center;justify-content:center;gap:8px;padding:12px 22px;border-radius:10px;background:#635bff;color:#fff!important;text-decoration:none!important;font-weight:700;line-height:1.2;box-shadow:0 8px 22px rgba(99,91,255,.22);transition:transform .15s,box-shadow .15s,opacity .15s}.ssb-stripe-pay-button:hover{transform:translateY(-1px);box-shadow:0 10px 26px rgba(99,91,255,.28);opacity:.96}</style>';
}
