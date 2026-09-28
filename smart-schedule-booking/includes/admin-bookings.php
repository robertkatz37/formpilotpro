<?php
if ( ! defined( 'ABSPATH' ) ) exit;

function ssb_bookings_page() {
    if ( function_exists('ssb_license_admin_guard') && ! ssb_license_admin_guard('core') ) return;
    global $wpdb;
    $table = $wpdb->prefix . 'schedule_bookings';

    // Handle status update
    if ( isset($_POST['ssb_update_status']) && check_admin_referer('ssb_update_status') ) {
        $wpdb->update( $table,
            ['status' => sanitize_text_field($_POST['new_status'])],
            ['id'     => intval($_POST['booking_id'])]
        );
        echo '<div class="notice notice-success"><p>Status updated.</p></div>';
    }

    // Handle delete
    if ( isset($_POST['ssb_delete']) && check_admin_referer('ssb_delete_booking') ) {
        $wpdb->delete( $table, ['id' => intval($_POST['booking_id'])] );
        echo '<div class="notice notice-success"><p>Booking deleted.</p></div>';
    }

    // Filter
    $status_filter = sanitize_text_field( $_GET['status_filter'] ?? '' );
    $search        = sanitize_text_field( $_GET['s'] ?? '' );

    $where = 'WHERE 1=1';
    if ( $status_filter ) $where .= $wpdb->prepare(' AND status = %s', $status_filter);
    if ( $search )        $where .= $wpdb->prepare(' AND (first_name LIKE %s OR last_name LIKE %s OR email LIKE %s)', "%$search%", "%$search%", "%$search%");

    $bookings = $wpdb->get_results("SELECT * FROM $table $where ORDER BY created_at DESC");

    $columns = $wpdb->get_col("SHOW COLUMNS FROM $table");
    $has_tz  = in_array('timezone_display', $columns);

    // Stats
    $total     = (int) $wpdb->get_var("SELECT COUNT(*) FROM $table");
    $confirmed = (int) $wpdb->get_var("SELECT COUNT(*) FROM $table WHERE status='confirmed'");
    $pending   = (int) $wpdb->get_var("SELECT COUNT(*) FROM $table WHERE status='pending'");
    $cancelled = (int) $wpdb->get_var("SELECT COUNT(*) FROM $table WHERE status='cancelled'");

    // Revenue: sum of the fee snapshotted at booking time for every meeting
    // that was successfully scheduled (confirmed or completed) - cancelled
    // bookings don't count.
    $has_fee_col  = in_array('fee_amount', $columns);
    $revenue      = $has_fee_col ? (float) $wpdb->get_var("SELECT SUM(fee_amount) FROM $table WHERE status IN ('confirmed','completed') AND payment_status NOT IN ('auth_released','authorized')") : 0;
    $revenue_curr = get_option('ssb_booking_currency', 'USD');

    // Small inline icon helper
    $icon = function( $name ) {
        $paths = [
            'total'     => 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4',
            'confirmed' => 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z',
            'pending'   => 'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z',
            'cancelled' => 'M6 18L18 6M6 6l12 12',
            'revenue'   => 'M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V6m0 10v2m9-8a9 9 0 11-18 0 9 9 0 0118 0z',
            'mail'      => 'M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z',
            'phone'     => 'M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z',
            'video'     => 'M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z',
            'search'    => 'M21 21l-4.35-4.35M11 19a8 8 0 100-16 8 8 0 000 16z',
            'empty'     => 'M9 13h6m-3-3v6m-7 4h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v11a2 2 0 002 2z',
        ];
        $d = $paths[$name] ?? '';
        return '<svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="' . $d . '"/></svg>';
    };
    ?>
    <div class="wrap ssb-admin-wrap">
        <div class="ssb-page-header">
            <div class="ssb-page-header-left">
                <div class="ssb-page-icon">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                </div>
                <div>
                    <h1 class="ssb-page-title">Bookings</h1>
                    <p class="ssb-page-subtitle">Every booking made through your scheduling widget</p>
                </div>
            </div>
        </div>

        <!-- Stats -->
        <div class="ssb-stats-grid">
            <div class="ssb-stat-card ssb-stat-total">
                <div class="ssb-stat-top"><span class="ssb-stat-label">Total</span><div class="ssb-stat-icon"><?php echo $icon('total'); ?></div></div>
                <div class="ssb-stat-value"><?php echo esc_html($total); ?></div>
            </div>
            <div class="ssb-stat-card ssb-stat-confirmed">
                <div class="ssb-stat-top"><span class="ssb-stat-label">Confirmed</span><div class="ssb-stat-icon"><?php echo $icon('confirmed'); ?></div></div>
                <div class="ssb-stat-value"><?php echo esc_html($confirmed); ?></div>
            </div>
            <div class="ssb-stat-card ssb-stat-pending">
                <div class="ssb-stat-top"><span class="ssb-stat-label">Pending</span><div class="ssb-stat-icon"><?php echo $icon('pending'); ?></div></div>
                <div class="ssb-stat-value"><?php echo esc_html($pending); ?></div>
            </div>
            <div class="ssb-stat-card ssb-stat-cancelled">
                <div class="ssb-stat-top"><span class="ssb-stat-label">Cancelled</span><div class="ssb-stat-icon"><?php echo $icon('cancelled'); ?></div></div>
                <div class="ssb-stat-value"><?php echo esc_html($cancelled); ?></div>
            </div>
            <div class="ssb-stat-card ssb-stat-revenue">
                <div class="ssb-stat-top"><span class="ssb-stat-label">Revenue</span><div class="ssb-stat-icon"><?php echo $icon('revenue'); ?></div></div>
                <div class="ssb-stat-value"><?php echo esc_html($revenue_curr . ' ' . number_format($revenue, 2)); ?></div>
            </div>
        </div>

        <!-- Filter -->
        <form method="get" class="ssb-toolbar">
            <input type="hidden" name="page" value="ssb-bookings">
            <input type="text" name="s" placeholder="Search name or email…" value="<?php echo esc_attr($search); ?>" class="regular-text">
            <select name="status_filter">
                <option value="">All Statuses</option>
                <?php foreach (['confirmed','pending','cancelled','completed'] as $st): ?>
                    <option value="<?php echo $st; ?>" <?php selected($status_filter,$st); ?>><?php echo ucfirst($st); ?></option>
                <?php endforeach; ?>
            </select>
            <button class="button">Filter</button>
            <?php if ($search || $status_filter): ?>
                <a href="?page=ssb-bookings" class="button">Clear</a>
            <?php endif; ?>
        </form>

        <div class="ssb-table-card">
        <table class="ssb-table">
            <thead>
                <tr>
                    <th style="width:50px">ID</th>
                    <th>Client</th>
                    <th>Contact</th>
                    <th>Date &amp; Time</th>
                    <th>Timezone</th>
                    <th>Practice</th>
                    <th>Fee</th>
                    <th>Meet Link</th>
                    <th>Status</th>
                    <th>Created</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if ( empty($bookings) ): ?>
                    <tr><td colspan="11">
                        <div class="ssb-empty-state">
                            <?php echo $icon('empty'); ?>
                            <p>No bookings found.</p>
                        </div>
                    </td></tr>
                <?php else: ?>
                    <?php foreach ( $bookings as $b ): ?>
                        <?php
                        $badge_class = 'ssb-badge-' . ( in_array($b->status, ['confirmed','pending','cancelled','completed']) ? $b->status : 'default' );
                        $tz_text = ($has_tz && !empty($b->timezone_display)) ? $b->timezone_display : $b->timezone;
                        $initials = strtoupper(substr($b->first_name ?? '', 0, 1) . substr($b->last_name ?? '', 0, 1));
                        ?>
                        <tr>
                            <td><strong>#<?php echo (int) $b->id; ?></strong></td>
                            <td>
                                <div class="ssb-cell-person">
                                    <div class="ssb-avatar"><?php echo esc_html($initials); ?></div>
                                    <strong><?php echo esc_html("$b->first_name $b->last_name"); ?></strong>
                                </div>
                            </td>
                            <td class="ssb-cell-contact">
                                <a href="mailto:<?php echo esc_attr($b->email); ?>"><?php echo $icon('mail'); ?><?php echo esc_html($b->email); ?></a>
                                <a href="tel:<?php echo esc_attr($b->phone); ?>"><?php echo $icon('phone'); ?><?php echo esc_html($b->phone); ?></a>
                            </td>
                            <td>
                                <strong><?php echo esc_html($b->booking_date); ?></strong><br>
                                <span style="color:var(--ssb-accent);font-weight:700;"><?php echo esc_html($b->booking_time); ?></span>
                            </td>
                            <td><small><?php echo esc_html($tz_text); ?></small></td>
                            <td><?php echo esc_html(ucfirst($b->practice_type)); ?></td>
                            <td>
                                <?php if ( $has_fee_col && floatval($b->fee_amount ?? 0) > 0 ): ?>
                                    <?php if ( ($b->payment_status ?? '') === 'auth_released' ): ?>
                                        <span class="ssb-fee-warn" title="Card authorization was released — client was never charged because their confirmation email failed to send.">Not charged</span>
                                    <?php elseif ( ($b->payment_status ?? '') === 'authorized' ): ?>
                                        <span class="ssb-fee-hold" title="Card is held but not yet captured.">Pending capture</span>
                                    <?php else: ?>
                                        <span class="ssb-fee-paid"><?php echo esc_html(($b->fee_currency ?: 'USD') . ' ' . number_format(floatval($b->fee_amount), 2)); ?></span>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span class="ssb-fee-free">Free</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ( ! empty($b->google_meet_link) ): ?>
                                    <a href="<?php echo esc_url($b->google_meet_link); ?>" target="_blank" class="ssb-meet-chip">
                                        <?php echo $icon('video'); ?> Join
                                    </a>
                                <?php else: ?>
                                    <span class="ssb-fee-free">—</span>
                                <?php endif; ?>
                            </td>
                            <td><span class="ssb-badge <?php echo esc_attr($badge_class); ?>"><?php echo esc_html(ucfirst($b->status)); ?></span></td>
                            <td><small><?php echo esc_html( date('M d, Y H:i', strtotime($b->created_at)) ); ?></small></td>
                            <td>
                                <div class="ssb-row-actions">
                                    <form method="post" style="display:inline-flex;gap:4px;align-items:center;">
                                        <?php wp_nonce_field('ssb_update_status'); ?>
                                        <input type="hidden" name="booking_id" value="<?php echo (int) $b->id; ?>">
                                        <select name="new_status">
                                            <?php foreach (['confirmed','pending','cancelled','completed'] as $st): ?>
                                                <option value="<?php echo $st; ?>" <?php selected($b->status,$st); ?>><?php echo ucfirst($st); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                        <button type="submit" name="ssb_update_status" class="button button-small">Update</button>
                                    </form>
                                    <form method="post" style="display:inline;" onsubmit="return confirm('Delete this booking?');">
                                        <?php wp_nonce_field('ssb_delete_booking'); ?>
                                        <input type="hidden" name="booking_id" value="<?php echo (int) $b->id; ?>">
                                        <button type="submit" name="ssb_delete" class="button button-small" style="color:var(--ssb-danger);">Delete</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
        </div>
    </div>
    <?php
}
