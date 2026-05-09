<?php
/**
 * Zephora Logistic Systems - User Dashboard
 * Modern iOS-inspired UI with real-time data and shipment tracking
 */
class ZLS_Dashboard {
    public static function render() {
        // Check login
        if (!is_user_logged_in()) {
            return '<div style="text-align:center;padding:40px;font-family:system-ui,sans-serif;">
                <h3>🔒 Login Required</h3>
                <p>Please <a href="/login">log in</a> to access your dashboard.</p>
            </div>';
        }

        $user_id = get_current_user_id();
        $user = wp_get_current_user();
        $display_name = $user->first_name ? $user->first_name : $user->display_name;
        
        // ✅ FIX: Fetch Real Unique ID
        $unique_id = get_user_meta($user_id, '_zls_unique_id', true);
        if (empty($unique_id)) $unique_id = 'PENDING'; // Fallback
        
        // Get dynamic bank details and warehouse address from settings
        $bank_details = get_option('zls_bank_details', array());
        $warehouse_address = get_option('zls_warehouse_address', array());

        // KYC Check
        $kyc_status = get_user_meta($user_id, '_zls_kyc_status', true) ?: 'pending';
        if ($kyc_status !== 'approved') {
            wp_redirect(home_url('/kyc-verification'));
            exit;
        }

        // Fetch real-time stats
        $active_statuses = array('pending', 'quote_sent', 'payment_pending', 'paid', 'purchasing', 'received_us', 'shipped');
        $active_q = new WP_Query(array(
            'post_type' => array('zls_ship', 'zls_buy'),
            'author' => $user_id,
            'posts_per_page' => -1,
            'fields' => 'ids',
            'meta_query' => array(array('key' => '_zls_status', 'value' => $active_statuses, 'compare' => 'IN'))
        ));
        $active_count = $active_q->found_posts;

        $ship_q = new WP_Query(array('post_type' => 'zls_ship', 'author' => $user_id, 'posts_per_page' => -1, 'fields' => 'ids'));
        $ship_count = $ship_q->found_posts;

        $buy_q = new WP_Query(array('post_type' => 'zls_buy', 'author' => $user_id, 'posts_per_page' => -1, 'fields' => 'ids'));
        $buy_count = $buy_q->found_posts;

        // Fetch recent active shipments for tracking cards
        $recent_shipments = self::get_recent_active_shipments($user_id, 2);

        // Get recent activity for table
        $recent_activity = new WP_Query(array(
            'post_type' => array('zls_ship', 'zls_buy'),
            'author' => $user_id,
            'posts_per_page' => 10,
            'orderby' => 'date',
            'order' => 'DESC'
        ));

        ob_start();
        ?>
        <style>
            .zls-dashboard { max-width: 1220px; margin: 0 auto; padding: 24px 20px; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: #f0f0f1; min-height: 100vh; color: #111827; }
            .zls-dash-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 24px; flex-wrap: wrap; gap: 16px; }
            .zls-greeting h1 { margin: 0; font-size: 26px; font-weight: 700; color: #000; }
            .zls-greeting p { margin: 4px 0 0; color: #6B7280; font-size: 15px; }
            .zls-btn-new { background: #6B3AE4; color: #fff; border: none; padding: 12px 24px; border-radius: 10px; font-weight: 600; font-size: 14px; cursor: pointer; transition: all 0.2s; display: inline-flex; align-items: center; gap: 6px; }
            .zls-btn-new:hover { background: #5a2dcf; transform: translateY(-1px); }
            .zls-stats-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px; margin-bottom: 24px; }
            @media (max-width: 768px) { .zls-stats-grid { grid-template-columns: repeat(2, 1fr); } }
            @media (max-width: 480px) { .zls-stats-grid { grid-template-columns: 1fr; } }
            .zls-stat-card { background: #fff; padding: 20px; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.04); border: 1px solid #E5E7EB; }
            .zls-stat-label { font-size: 12px; font-weight: 600; color: #6B7280; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 8px; }
            .zls-stat-value { font-size: 28px; font-weight: 800; color: #000; line-height: 1; }
            .zls-main-grid { display: grid; grid-template-columns: 1fr 340px; gap: 24px; }
            @media (max-width: 900px) { .zls-main-grid { grid-template-columns: 1fr; } }
            .zls-track-card { background: #fff; border-radius: 16px; padding: 24px; margin-bottom: 20px; box-shadow: 0 2px 8px rgba(0,0,0,0.04); border: 1px solid #E5E7EB; }
            .zls-track-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 20px; flex-wrap: wrap; gap: 12px; }
            .zls-track-type { display: inline-block; background: #EDE9FE; color: #6B3AE4; padding: 4px 12px; border-radius: 20px; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; }
            .zls-track-type.buy { background: #D1FAE5; color: #059669; }
            .zls-track-title { font-size: 18px; font-weight: 700; margin: 8px 0 0; }
            .zls-track-date { text-align: right; }
            .zls-track-date-label { font-size: 11px; color: #6B7280; font-weight: 600; text-transform: uppercase; }
            .zls-track-date-value { font-size: 14px; font-weight: 600; margin-top: 2px; }
            .zls-progress { position: relative; margin: 28px 0 24px; display: flex; justify-content: space-between; }
            .zls-progress-line { position: absolute; top: 16px; left: 0; right: 0; height: 4px; background: #E5E7EB; border-radius: 2px; z-index: 1; }
            .zls-progress-fill { position: absolute; top: 16px; left: 0; height: 4px; background: #6B3AE4; border-radius: 2px; z-index: 2; transition: width 0.3s ease; }
            .zls-progress-step { position: relative; z-index: 3; text-align: center; flex: 1; }
            .zls-step-dot { width: 36px; height: 36px; background: #fff; border: 4px solid #E5E7EB; border-radius: 50%; margin: 0 auto 8px; display: flex; align-items: center; justify-content: center; font-weight: 700; color: #D1D5DB; font-size: 13px; transition: all 0.2s; }
            .zls-step-dot.completed { background: #6B3AE4; border-color: #6B3AE4; color: #fff; }
            .zls-step-dot.active { border-color: #6B3AE4; color: #6B3AE4; background: #F5F3FF; }
            .zls-step-dot.active-delivered { background: #6B3AE4; border-color: #6B3AE4; color: #fff; }
            .zls-step-label { font-size: 12px; font-weight: 600; color: #4B5563; }
            .zls-step-sub { font-size: 11px; color: #9CA3AF; }
            .zls-track-status { font-size: 13px; color: #6B7280; margin-top: 16px; padding-top: 16px; border-top: 1px solid #F3F4F6; }
            .zls-track-status strong { color: #000; }
            .zls-sidebar { display: flex; flex-direction: column; gap: 20px; }
            .zls-warehouse-card { background: #111827; color: #fff; border-radius: 16px; overflow: hidden; }
            .zls-wh-head { background: #1F2937; padding: 16px 20px; display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #374151; }
            .zls-wh-head h3 { margin: 0; font-size: 16px; font-weight: 700; }
            .zls-wh-body { padding: 20px; }
            .zls-wh-body p { font-size: 13px; color: #D1D5DB; line-height: 1.5; margin: 0 0 16px; }
            .zls-wh-addr { background: rgba(255,255,255,0.05); padding: 12px; border-radius: 10px; margin-bottom: 10px; border: 1px solid rgba(255,255,255,0.08); }
            .zls-wh-addr-label { font-size: 10px; color: #9CA3AF; text-transform: uppercase; font-weight: 700; margin-bottom: 4px; letter-spacing: 0.5px; }
            .zls-wh-addr-val { font-size: 13px; color: #E5E7EB; display: flex; justify-content: space-between; align-items: center; }
            .zls-copy-btn { cursor: pointer; color: #6B3AE4; font-size: 14px; background: none; border: none; padding: 2px; transition: color 0.2s; }
            .zls-copy-btn:hover { color: #8B5CF6; }
            .zls-wh-warning { background: #FEF3C7; color: #92400E; padding: 12px; border-radius: 10px; font-size: 12px; display: flex; gap: 10px; align-items: start; border: 1px solid #FDE68A; margin-top: 12px; }
            .zls-wh-warning strong { color: #6B3AE4; font-weight: 700; }
            .zls-help-card { background: #F5F3FF; border: 1px solid #E9D5FF; border-radius: 14px; padding: 20px; text-align: center; }
            .zls-help-card h4 { margin: 0 0 6px; color: #4C1D95; font-size: 15px; }
            .zls-help-card p { margin: 0 0 16px; color: #6B7280; font-size: 13px; }
            .zls-btn-chat { background: #fff; color: #6B3AE4; border: 1px solid #E5E7EB; padding: 10px 20px; border-radius: 8px; cursor: pointer; font-weight: 600; width: 100%; transition: all 0.2s; }
            .zls-btn-chat:hover { background: #6B3AE4; color: #fff; }
            /* Recent Activity Table Styles */
            .zls-activity-section { margin-top: 24px; }
            .zls-activity-section h3 { margin: 0 0 16px; font-size: 18px; font-weight: 600; }
            .zls-activity-table-wrap { background: #fff; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.04); overflow: hidden; border: 1px solid #E5E7EB; }
            .zls-activity-table { width: 100%; border-collapse: collapse; font-size: 14px; }
            .zls-activity-table th { text-align: left; padding: 14px 20px; background: #F9FAFB; color: #6B7280; font-weight: 600; font-size: 12px; text-transform: uppercase; letter-spacing: 0.5px; border-bottom: 2px solid #E5E7EB; }
            .zls-activity-table td { padding: 14px 20px; border-bottom: 1px solid #F3F4F6; vertical-align: middle; }
            .zls-activity-table tr:last-child td { border-bottom: none; }
            .zls-activity-table tr:hover { background: #F9FAFB; }
            .zls-status-badge { display: inline-block; padding: 4px 12px; border-radius: 20px; font-size: 12px; font-weight: 600; text-transform: uppercase; }
            .zls-status-pending { background: #FEF3C7; color: #D97706; }
            .zls-status-delivered { background: #D1FAE5; color: #059669; }
            .zls-status-transit { background: #DBEAFE; color: #2563EB; }
            .zls-status-received { background: #E0E7FF; color: #4338CA; }
            .zls-btn-view { background: #6B3AE4; color: #fff; border: none; padding: 6px 16px; border-radius: 6px; font-size: 13px; font-weight: 600; cursor: pointer; text-decoration: none; display: inline-block; transition: background 0.2s; }
            .zls-btn-view:hover { background: #5a2dcf; }
            .zls-no-activity { text-align: center; padding: 40px; color: #9CA3AF; font-size: 14px; }
            
            
            /* Progress Stepper Styles (for Modal) */
.zls-progress { position: relative; margin: 28px 0 24px; display: flex; justify-content: space-between; align-items: center; }
.zls-progress-line { position: absolute; top: 16px; left: 0; right: 0; height: 4px; background: #E5E7EB; border-radius: 2px; z-index: 1; }
.zls-progress-fill { position: absolute; top: 16px; left: 0; height: 4px; background: #6B3AE4; border-radius: 2px; z-index: 2; transition: width 0.3s ease; }
.zls-progress-step { position: relative; z-index: 3; text-align: center; flex: 1; padding: 0 5px; }
.zls-step-dot { width: 36px; height: 36px; background: #fff; border: 4px solid #E5E7EB; border-radius: 50%; margin: 0 auto 8px; display: flex; align-items: center; justify-content: center; font-weight: 700; color: #D1D5DB; font-size: 13px; transition: all 0.2s; }
.zls-step-dot.completed { background: #6B3AE4; border-color: #6B3AE4; color: #fff; }
.zls-step-dot.active { border-color: #6B3AE4; color: #6B3AE4; background: #F5F3FF; }
.zls-step-dot.active-delivered { background: #6B3AE4; border-color: #6B3AE4; color: #fff; }
.zls-step-label { font-size: 12px; font-weight: 600; color: #4B5563; margin-bottom: 2px; }
.zls-step-sub { font-size: 11px; color: #9CA3AF; line-height: 1.2; }

/* Responsive adjustment for modal */
@media (max-width: 900px) {
    .zls-progress-step { padding: 0; }
    .zls-step-dot { width: 32px; height: 32px; font-size: 11px; border-width: 3px; }
    .zls-step-label { font-size: 11px; }
    .zls-step-sub { display: none; } /* Hide subtitles on very small screens */
}
          
          
          .zls-btn-delete {
    background: transparent;
    border: 1px solid #fee2e2;
    color: #ef4444;
    padding: 6px 10px;
    border-radius: 6px;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.2s;
}
.zls-btn-delete:hover {
    background: #fee2e2;
    color: #dc2626;
    border-color: #fecaca;
}
            
            
        </style>
        <div class="zls-dashboard">
            
            
            <!-- Header -->
            <div class="zls-dash-header">
                <div class="zls-greeting">
                    <h1>Welcome back, <?php echo esc_html($display_name); ?></h1>
                    <p>Here's what's happening with your global shipments today.</p>
                </div>
              <div style="display:flex;gap:10px;flex-wrap:wrap;">
    <a href="<?php echo home_url('/ship-for-me'); ?>" class="zls-btn-new" style="text-decoration:none;">Ship For Me</a>
    <a href="<?php echo home_url('/buy-for-me'); ?>" class="zls-btn-new" style="background:#059669;text-decoration:none;">Buy For Me</a>
</div>
            </div>
            <!-- Stats -->
            <div class="zls-stats-grid">
                <div class="zls-stat-card">
                    <div class="zls-stat-label">Active Shipment</div>
                    <div class="zls-stat-value"><?php echo esc_html($active_count); ?></div>
                </div>
                <div class="zls-stat-card">
                    <div class="zls-stat-label">Ship For Me</div>
                    <div class="zls-stat-value"><?php echo esc_html($ship_count); ?></div>
                </div>
                <div class="zls-stat-card">
                    <div class="zls-stat-label">Buy For Me</div>
                    <div class="zls-stat-value"><?php echo esc_html($buy_count); ?></div>
                </div>
                <div class="zls-stat-card">
                    <div class="zls-stat-label">Total Shipment</div>
                    <div class="zls-stat-value"><?php echo esc_html($ship_count + $buy_count); ?></div>
                </div>
            </div>
            
             <?php if (isset($_GET['sfm_ok']) && $_GET['sfm_ok'] === '1'): ?>
<div class="zls-success-message" style="background:#d1fae5;color:#065f46;padding:16px;border-radius:12px;margin-bottom:24px;text-align:center;font-weight:600;">
    ✅ Shipping request submitted successfully! We'll review and send you a quote soon.
</div>
<?php elseif (isset($_GET['bfm_ok']) && $_GET['bfm_ok'] === '1'): ?>
<div style="background:#d1fae5;color:#065f46;padding:16px;border-radius:12px;margin-bottom:24px;text-align:center;font-weight:600;">
    ✅ Purchase request submitted successfully!  We'll review and send you a quote soon.</div>
<?php endif; ?>
            
            
            <div class="zls-main-grid">
                <!-- Left Column -->
                <div>
                    <?php if (!empty($recent_shipments)): ?>
                        <?php foreach ($recent_shipments as $ship): ?>
                            <div class="zls-track-card">
                                <div class="zls-track-header">
                                    <div>
                                        <span class="zls-track-type <?php echo $ship['type'] === 'zls_buy' ? 'buy' : ''; ?>">
                                            <?php echo $ship['type'] === 'zls_buy' ? 'BUY FOR ME' : 'SHIP FOR ME'; ?>
                                        </span>
                                        <h3 class="zls-track-title"><?php echo esc_html($ship['title']); ?></h3>
                                    </div>
                                    <div class="zls-track-date">
                                        <div class="zls-track-date-label">Date Created</div>
                                        <div class="zls-track-date-value"><?php echo esc_html($ship['date']); ?></div>
                                    </div>
                                </div>
                                <div class="zls-progress">
                                    <div class="zls-progress-line"></div>
                                    <div class="zls-progress-fill" style="width: <?php echo $ship['progress']; ?>%;"></div>
                                    <?php foreach ($ship['steps'] as $i => $step):
                                        $dot_class = ($i < $ship['current_step'] - 1) ? 'completed' : (($i === $ship['current_step'] - 1) ? 'active' : '');
                                        $dot_content = ($i < $ship['current_step'] - 1) ? '✓' : ($i + 1);
                                    ?>
                                    <div class="zls-progress-step">
                                        <div class="zls-step-dot <?php echo $dot_class; ?>"><?php echo $dot_content; ?></div>
                                        <div class="zls-step-label"><?php echo esc_html($step['label']); ?></div>
                                        <div class="zls-step-sub"><?php echo esc_html($step['sub']); ?></div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                                <div class="zls-track-status">
                                    Current status: <strong><?php echo esc_html($ship['status_label']); ?></strong>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div style="text-align:center;padding:40px;background:#fff;border-radius:16px;border:1px solid #E5E7EB;color:#6B7280;">
                            <p>No active shipments. Start shipping today!</p>
                        </div>
                    <?php endif; ?>
                    <!-- Recent Activity Table -->
                    <div class="zls-activity-section">
                        <h3>Recent Activity</h3>
                        <div class="zls-activity-table-wrap">
                            <table class="zls-activity-table">
                                <thead>
                                    <tr>
                                        <th>Product Name</th>
                                        <th>Type</th>
                                        <th>Tracking #</th>
                                        <th>Status</th>
                                        <th>Date</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if ($recent_activity->have_posts()): ?>
                                        <?php while($recent_activity->have_posts()): $recent_activity->the_post();
                                            $status = get_post_meta(get_the_ID(), '_zls_status', true);
                                            $tracking = get_post_meta(get_the_ID(), '_zls_tracking_number', true);
                                            $post_type = get_post_type();
                                            // Type label
                                            $type_label = ($post_type === 'zls_ship') ? 'Ship For Me' : 'Buy For Me';
                                            // Status class and text
                                            $status_class = 'zls-status-pending';
                                            $status_text = 'Pending';
                                            if (in_array($status, array('received_us'))) {
                                                $status_class = 'zls-status-received';
                                                $status_text = 'Received in Texas';
                                            } elseif (in_array($status, array('shipped', 'in_transit'))) {
                                                $status_class = 'zls-status-transit';
                                                $status_text = ucfirst(str_replace('_', ' ', $status));
                                            } elseif ($status === 'delivered') {
                                                $status_class = 'zls-status-delivered';
                                                $status_text = 'Delivered';
                                            } elseif ($status !== 'pending') {
                                                $status_text = ucfirst(str_replace('_', ' ', $status));
                                            }
                                        ?>
                                        <tr>
                                            <td style="font-weight: 600; color: #111827;"><?php the_title(); ?></td>
                                            <td><?php echo esc_html($type_label); ?></td>
                                            <td><?php echo $tracking ? esc_html($tracking) : '—'; ?></td>
                                            <td><span class="zls-status-badge <?php echo $status_class; ?>"><?php echo esc_html($status_text); ?></span></td>
                                            <td><?php echo get_the_date('M j, Y'); ?></td>
                               

<td style="white-space:nowrap; display:flex; gap:8px; align-items:center;">
    <button class="zls-btn-view zls-open-modal" data-post-id="<?php echo get_the_ID(); ?>">View</button>
    <?php if ($status === 'pending'): ?>
        <button class="zls-btn-delete" 
                data-post-id="<?php echo get_the_ID(); ?>" 
                data-nonce="<?php echo wp_create_nonce('zls_delete_' . get_the_ID()); ?>"
                title="Delete pending request">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg>
        </button>
    <?php endif; ?>
</td>
                                        </tr>
                                        <?php endwhile; wp_reset_postdata(); ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="6" class="zls-no-activity">No recent activity</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                            
   
<!-- Load More Button -->
<div id="zls-load-more-wrap" style="text-align:center; margin-top:24px; <?php echo ($recent_activity->max_num_pages <= 1) ? 'display:none;' : ''; ?>">
    <button id="zls-load-more-btn" class="zls-btn-load-more" data-page="1" data-nonce="<?php echo wp_create_nonce('zls_load_activity'); ?>">
        Load More Activity
    </button>
</div>
                            
                            
                        </div>
                    </div>
                </div>
                <!-- Right Sidebar -->
                <div class="zls-sidebar">
                    <div class="zls-warehouse-card">
                        <div class="zls-wh-head">
                            <h3>US Warehouse</h3>
                            <span>📍</span>
                        </div>
                        <div class="zls-wh-body">
                            <p>Use this address as your shipping destination when shopping from US online stores.</p>
                            <?php if ($warehouse_address): ?>
                            <div class="zls-wh-addr">
                                <div class="zls-wh-addr-label">Address Line</div>
                                <div class="zls-wh-addr-val">
                                    <?php echo esc_html($warehouse_address['address_line1']); ?>
                                    <button class="zls-copy-btn" onclick="copyToClipboard('<?php echo esc_js($warehouse_address['address_line1']); ?>')">❐</button>
                                </div>
                            </div>
                            <?php if (!empty($warehouse_address['address_line2'])): ?>
                            <div class="zls-wh-addr">
                                <div class="zls-wh-addr-label">Unit / Suite</div>
                                <div class="zls-wh-addr-val">
                                    <?php echo esc_html($warehouse_address['address_line2']); ?>
                                    <button class="zls-copy-btn" onclick="copyToClipboard('<?php echo esc_js($warehouse_address['address_line2']); ?>')">❐</button>
                                </div>
                            </div>
                            <?php endif; ?>
                            <div class="zls-wh-addr">
                                <div class="zls-wh-addr-label">Zip & State</div>
                                <div class="zls-wh-addr-val">
                                    <?php echo esc_html($warehouse_address['city'] . ' ' . $warehouse_address['postal_code'] . ', ' . $warehouse_address['state']); ?>
                                    <button class="zls-copy-btn" onclick="copyToClipboard('<?php echo esc_js($warehouse_address['city'] . ' ' . $warehouse_address['postal_code'] . ', ' . $warehouse_address['state']); ?>')">❐</button>
                                </div>
                            </div>
                            <?php else: ?>
                            <p>No warehouse address configured. Please contact support.</p>
                            <?php endif; ?>
                            <div class="zls-wh-warning">
                                <span>ℹ️</span>
                                <!-- ✅ FIX: Dynamic Unique ID Display -->
                                <div>Always include your Unique ID <strong><?php echo esc_html($unique_id); ?></strong> in the address field to avoid delivery delays.</div>
                            </div>
                        </div>
                    </div>
                    <div class="zls-help-card">
                        <h4>Need help?</h4>
                        <p>Contact our support team for assistance</p>
                        <button class="zls-btn-chat">Live Chat</button>
                    </div>
                </div>
            </div>
        </div>
        <script>
        function copyToClipboard(text) {
            navigator.clipboard.writeText(text).then(function() {
                // Optional: Show feedback
            }).catch(function(err) {
                // Fallback for older browsers
                var ta = document.createElement('textarea');
                ta.value = text;
                document.body.appendChild(ta);
                ta.select();
                document.execCommand('copy');
                document.body.removeChild(ta);
            });
        }
        </script>
        
        <!-- Modal Container -->
<div id="zls-modal-overlay" style="display:none;">
    <div class="zls-modal-container">
        <div class="zls-modal-box">
            
            <!-- Header -->
            <div class="zls-modal-header">
                <div>
                    <div class="zls-breadcrumb">SHIPMENTS / DETAILS</div>
                    <h2 class="zls-modal-title">Shipment Details</h2>
                    <div class="zls-modal-meta">
                        Request ID: <span id="m-req-id" class="zls-req-id">—</span> • 
                        Date: <span id="m-date">—</span>
                    </div>
                </div>
                <div class="zls-modal-actions">
                    <span id="m-status-badge" class="zls-status-pill">—</span>
              
                </div>
            </div>

            <!-- Body -->
            <div class="zls-modal-body">
                <!-- Left Column -->
                <div class="zls-modal-left">
                    <div class="zls-card">
                        <div class="zls-card-head">
                            
                            <h3>Product Information</h3>
                        </div>
                        <div class="zls-product-grid" id="m-product-grid">
                            <!-- Loading Spinner -->
                            <div class="zls-loading-state"><div class="zls-spinner"></div>Loading...</div>
                        </div>
                        <div id="m-product-url" class="zls-url-box" style="display:none;"></div>
                    </div>

                    <div class="zls-card">
                        <div class="zls-card-head">
                          
                            <h3>Content Description</h3>
                        </div>
                        <p id="m-content-desc" class="zls-desc-text">—</p>
                    </div>

                    <div class="zls-card">
                        <div class="zls-card-head"><h3>Timeline</h3></div>
                        <div id="m-timeline" class="zls-timeline"></div>
                    </div>
                </div>

                <!-- Right Column -->
                <div class="zls-modal-right">
                    <div class="zls-card">
                        <div class="zls-card-head"><h3>Request Information</h3></div>
                        <div class="zls-info-block">
                            <div class="zls-info-label">CUSTOMER ID</div>
                            <div id="m-customer-id" class="zls-info-value">—</div>
                        </div>
                        <div class="zls-info-block">
                            <div class="zls-info-label">REQUEST TYPE</div>
                            <div id="m-req-type" class="zls-type-pill">—</div>
                        </div>
                        <div class="zls-info-block">
                            <div class="zls-info-label">DATE SUBMITTED</div>
                            <div id="m-date-sub" class="zls-info-value">—</div>
                        </div>
                    </div>

                    <div class="zls-card">
                        <div class="zls-card-head">
                           
                            <h3>Delivery Address</h3>
                        </div>
                        <div id="m-address" class="zls-address-block">—</div>
                    </div>
                    
                    
                    <!-- Payment Instructions Card -->
<div class="zls-payment-card">
    <!-- Amount to Pay Section -->
    <div class="zls-pay-amount-section">
        <div class="zls-pay-amount-label">Amount to Pay</div>
        <div class="zls-pay-amount-value" id="pay-amount-display">—</div>
    </div>
    
    <div class="zls-pay-header">PAYMENT INSTRUCTIONS</div>
    
    <div class="zls-pay-row">
        <div class="zls-pay-label">BANK NAME</div>
        <div class="zls-pay-value"><?php echo esc_html($bank_details['bank_name'] ?? 'Access Bank PLC'); ?></div>
    </div>
    
    <div class="zls-pay-row">
        <div class="zls-pay-label">ACCOUNT NAME</div>
        <div class="zls-pay-value"><?php echo esc_html($bank_details['account_name'] ?? 'Shipping Shop Limited'); ?></div>
    </div>
    
    <div class="zls-pay-row">
        <div class="zls-pay-label">ACCOUNT NUMBER</div>
        <div class="zls-pay-value-row">
            <span class="zls-pay-value" style="color:#8B5CF6;" id="pay-acc-num"><?php echo esc_html($bank_details['account_number'] ?? '0123456789'); ?></span>
            <button class="zls-copy-small" onclick="copyToClipboard('<?php echo esc_js($bank_details['account_number'] ?? '0123456789'); ?>')">❐</button>
        </div>
    </div>

    <div class="zls-pay-note">
        <span>⚠️</span>
        <div>Note: Use your Unique ID <strong id="pay-ref-text"><?php echo esc_html($unique_id); ?></strong> as your transaction reference to ensure faster verification.</div>
    </div>
    
    <button id="btn-confirm-payment" class="zls-btn-confirm">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"></polyline></svg>
        I've Paid & Confirm Order
    </button>
</div>

                 
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Styles -->
<style>
/* Font Override as requested */
#zls-modal-overlay, #zls-modal-overlay * {
    font-family: 'Segoe UI', system-ui, -apple-system, sans-serif !important;
    font-weight: 600 !important;
    letter-spacing: 0.01em;
}

/* Modal Layout */
#zls-modal-overlay { 
    position:fixed; top:0; left:0; width:100%; height:100%; 
    background:rgba(17,24,39,0.7); z-index:99999; 
    display:flex; align-items:center; justify-content:center; padding:20px;
    opacity:0; transition:opacity 0.25s ease;
}
#zls-modal-overlay.active { opacity:1; }
.zls-modal-container { width:100%; max-width:1100px; max-height:92vh; }
.zls-modal-box { background:#fff; border-radius:20px; overflow:hidden; display:flex; flex-direction:column; max-height:92vh; box-shadow:0 25px 50px rgba(0,0,0,0.3); }

/* Header */
.zls-modal-header { padding:24px 32px; border-bottom:1px solid #F3F4F6; display:flex; justify-content:space-between; align-items:flex-start; gap:20px; }
.zls-breadcrumb { font-size:11px; color:#6B7280; margin-bottom:4px; letter-spacing:0.5px; }
.zls-modal-title { margin:0; font-size:24px; font-weight:800 !important; color:#111827; }
.zls-modal-meta { margin-top:4px; font-size:13px; color:#6B7280; }
.zls-req-id { font-weight:700 !important; color:#6B3AE4; }
.zls-status-pill { padding:6px 12px; border-radius:20px; font-size:11px; font-weight:700 !important; text-transform:uppercase; background:#F3F4F6; color:#4B5563; }
.zls-status-pill.delivered { background:#D1FAE5; color:#065F46; }
.zls-status-pill.pending, .zls-status-pill.quote_sent { background:#FEF3C7; color:#92400E; }
.zls-btn-invoice { background:#6B3AE4; color:#fff; border:none; padding:10px 20px; border-radius:10px; font-weight:600 !important; cursor:pointer; font-size:13px; }

/* Body */
.zls-modal-body { display:grid; grid-template-columns:1fr 320px; gap:24px; padding:24px; overflow-y:auto; }
.zls-card { background:#fff; border:1px solid #E5E7EB; border-radius:16px; padding:20px; margin-bottom:20px; }
.zls-card-head { display:flex; align-items:center; gap:10px; margin-bottom:16px; }
.zls-card-head h3 { margin:0; font-size:15px; font-weight:700 !important; color:#111827; }
.zls-card-icon { width:32px; height:32px; border-radius:8px; display:flex; align-items:center; justify-content:center; font-size:16px; }

/* Grid & Fields */
.zls-product-grid { display:grid; grid-template-columns:repeat(2, 1fr); gap:16px; }
.zls-prod-field { display:flex; flex-direction:column; gap:4px; }
.zls-prod-label { font-size:10px; color:#6B7280; text-transform:uppercase; font-weight:700 !important; letter-spacing:0.5px; }
.zls-prod-value { font-size:14px; color:#111827; font-weight:600 !important; }
.zls-desc-text { margin:0; font-size:14px; color:#4B5563; line-height:1.6; font-weight:500 !important; }
.zls-url-box { background:#F9FAFB; padding:10px 14px; border-radius:8px; margin-top:12px; }
.zls-url-box a { color:#6B3AE4; text-decoration:none; font-size:13px; word-break:break-all; }

/* Info Right */
.zls-info-block { margin-bottom:14px; }
.zls-info-label { font-size:10px; color:#9CA3AF; text-transform:uppercase; font-weight:800 !important; margin-bottom:4px; }
.zls-info-value { font-size:14px; color:#111827; font-weight:600 !important; }
.zls-type-pill { display:inline-block; background:#F3F0FF; color:#6B3AE4; padding:3px 8px; border-radius:4px; font-size:12px; font-weight:700 !important; }
.zls-addr-box { background:#F9FAFB; padding:12px; border-radius:8px; font-size:13px; color:#4B5563; line-height:1.5; margin-top:10px; }

/* Timeline */
.zls-timeline { padding-left:24px; position:relative; }
.zls-timeline::before { content:''; position:absolute; left:9px; top:8px; bottom:8px; width:2px; }
.zls-tl-step { position:relative; margin-bottom:20px; }
.zls-tl-dot { position:absolute; left:-24px; top:4px; width:18px; height:18px; border-radius:50%; border:3px solid #E5E7EB; background:#fff; }
.zls-tl-step.active .zls-tl-dot { border-color:#10B981; background:#10B981; }
.zls-tl-step.past .zls-tl-dot { border-color:#6B3AE4; background:#6B3AE4; }
.zls-tl-label { margin:0; font-size:12px; font-weight:700 !important; text-transform:uppercase; color:#111827; }
.zls-tl-meta { margin:2px 0 0; font-size:11px; color:#6B7280; font-weight:500 !important; }

/* Payment Instructions Card */
.zls-payment-card {
    background: #111827; /* Dark background */
    color: #fff;
    border-radius: 16px;
    padding: 24px;
    margin-top: 20px;
}
.zls-pay-amount-section {
    background: rgba(255,255,255,0.05);
    padding: 16px;
    border-radius: 10px;
    margin-bottom: 20px;
    text-align: center;
    border: 1px solid rgba(255,255,255,0.1);
}
.zls-pay-amount-label {
    font-size: 11px;
    font-weight: 700;
    color: #9CA3AF;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-bottom: 6px;
}
.zls-pay-amount-value {
    font-size: 24px;
    font-weight: 800;
    color: #10B981; /* Green for money */
    letter-spacing: 0.5px;
}
.zls-pay-header {
    font-size: 12px;
    font-weight: 800;
    color: #F9FAFB;
    text-transform: uppercase;
    letter-spacing: 1px;
    margin-bottom: 20px;
    border-bottom: 1px solid rgba(255,255,255,0.1);
    padding-bottom: 12px;
}
.zls-pay-row { margin-bottom: 16px; }
.zls-pay-label {
    font-size: 10px;
    font-weight: 700;
    color: #9CA3AF;
    margin-bottom: 4px;
    text-transform: uppercase;
}
.zls-pay-value { font-size: 16px; font-weight: 600; color: #F9FAFB; }
.zls-pay-value-row { display: flex; align-items: center; gap: 10px; }
.zls-copy-small {
    background: none; border: none; cursor: pointer; color: #9CA3AF; font-size: 16px; padding: 4px;
}
.zls-copy-small:hover { color: #fff; }
.zls-pay-note {
    background: rgba(255,255,255,0.08);
    padding: 12px;
    border-radius: 8px;
    font-size: 12px;
    color: #D1D5DB;
    margin: 20px 0;
    display: flex;
    gap: 10px;
    align-items: flex-start;
    line-height: 1.5;
}
.zls-pay-note strong { color: #fff; font-weight: 700; }
.zls-btn-confirm {
    width: 100%;
    background: #fff;
    color: #111827;
    border: none;
    padding: 14px;
    border-radius: 12px;
    font-weight: 700;
    font-size: 15px;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    transition: all 0.2s;
}
.zls-btn-confirm:hover { background: #F3F4F6; }
.zls-btn-confirm:disabled { opacity: 0.7; cursor: not-allowed; }
.zls-btn-confirm.confirmed { background: #10B981; color: #fff; cursor: default; }

/* Loading */
.zls-loading-state { display:flex; align-items:center; gap:10px; color:#6B7280; padding:20px 0; justify-content:center; }
.zls-spinner { width:20px; height:20px; border:2px solid #E5E7EB; border-top-color:#6B3AE4; border-radius:50%; animation:spin 0.8s linear infinite; }
@keyframes spin { to { transform:rotate(360deg); } }

@media (max-width:900px) {
    .zls-modal-body { grid-template-columns:1fr; }
    .zls-product-grid { grid-template-columns:1fr; }
}




.zls-btn-load-more {
    background: #fff;
    color: #6B3AE4;
    border: 1px solid #E5E7EB;
    padding: 12px 32px;
    border-radius: 10px;
    font-weight: 600;
    font-size: 14px;
    cursor: pointer;
    transition: all 0.2s;
    display: inline-flex;
    align-items: center;
    gap: 8px;
}
.zls-btn-load-more:hover { background: #F5F3FF; border-color: #6B3AE4; }
.zls-btn-load-more:disabled { opacity: 0.6; cursor: not-allowed; }




</style>

<!-- Script -->
<script>
(function() {
    // 1. Define the correct AJAX URL using PHP
    const ajaxurl = '<?php echo admin_url('admin-ajax.php'); ?>';
    const overlay = document.getElementById('zls-modal-overlay');
    
    // ✅ ADD THIS: Global variable for current modal post ID
    let currentPostId = null;

    function openModal(postId) {
        currentPostId = postId; // ✅ Set the global variable
        overlay.style.display = 'flex';
        requestAnimationFrame(() => overlay.classList.add('active'));
        fetchDetails(postId);
    }

    function closeModal() {
        overlay.classList.remove('active');
        setTimeout(() => { overlay.style.display = 'none'; }, 250);
        currentPostId = null; // ✅ Clear when closed
    }

    function fetchDetails(postId) {
        // Show loading
        document.getElementById('m-product-grid').innerHTML = '<div class="zls-loading-state"><div class="zls-spinner"></div>Loading details...</div>';
        document.getElementById('m-timeline').innerHTML = '';

        const formData = new FormData();
        formData.append('action', 'fetch_shipment_details');
        formData.append('post_id', postId);

        // Use Fetch API
        fetch(ajaxurl, {
            method: 'POST',
            body: formData
        })
        .then(response => {
            if (!response.ok) throw new Error('Network response was not ok');
            return response.json();
        })
        .then(data => {
            if (data.success) {
                populateModal(data.data);
            } else {
                throw new Error(data.data || 'Server error');
            }
        })
        .catch(error => {
            console.error('Fetch Error:', error);
            document.getElementById('m-product-grid').innerHTML = 
                '<div style="color:red;padding:20px;text-align:center;">' + error.message + '</div>';
        });
    }

    function populateModal(d) {
        console.log('Data received:', d);

        // --- Header & Status ---
        document.getElementById('m-req-id').textContent = 'LF-' + String(d.id).padStart(5, '0') + '-NG';
        document.getElementById('m-date').textContent = d.date;
        document.getElementById('m-date-sub').textContent = d.date;
        
        const badge = document.getElementById('m-status-badge');
        badge.textContent = d.status.replace(/_/g, ' ');
        badge.className = 'zls-status-pill ' + d.status;

        // --- Product Info Grid ---
        const m = d.meta;
        let gridHTML = '';
        
        if (d.type === 'zls_ship') {
            gridHTML = `
                <div class="zls-prod-field"><div class="zls-prod-label">Product Name</div><div class="zls-prod-value">${esc(m.product_name) || '—'}</div></div>
                <div class="zls-prod-field"><div class="zls-prod-label">Tracking Number</div><div class="zls-prod-value">${esc(m.tracking_number) || 'Not Assigned'}</div></div>
                <div class="zls-prod-field"><div class="zls-prod-label">Amount/Value</div><div class="zls-prod-value">${m.value_usd ? '$' + Number(m.value_usd).toFixed(2) : '—'}</div></div>
                <div class="zls-prod-field"><div class="zls-prod-label">Packages</div><div class="zls-prod-value">${m.packages ? m.packages + ' Unit(s)' : '1 Unit'}</div></div>
                <div class="zls-prod-field"><div class="zls-prod-label">Weight</div><div class="zls-prod-value">${m.weight_kg ? m.weight_kg + ' kg' : '0.0 kg'}</div></div>
            `;
            const urlBox = document.getElementById('m-product-url');
            if (m.product_url) {
                urlBox.innerHTML = '<a href="'+esc(m.product_url)+'" target="_blank">'+esc(m.product_url)+' ↗</a>';
                urlBox.style.display = 'block';
            } else {
                urlBox.style.display = 'none';
            }
        } else {
            gridHTML = `
                <div class="zls-prod-field"><div class="zls-prod-label">Product Name</div><div class="zls-prod-value">${esc(m.product_name) || '—'}</div></div>
                <div class="zls-prod-field"><div class="zls-prod-label">Quantity</div><div class="zls-prod-value">${m.quantity || '1'}</div></div>
                <div class="zls-prod-field"><div class="zls-prod-label">Amount</div><div class="zls-prod-value">${m.budget_usd ? '$' + Number(m.budget_usd).toFixed(2) : '—'}</div></div>
                <div class="zls-prod-field"><div class="zls-prod-label">Preferred Store</div><div class="zls-prod-value">${esc(m.preferred_store) || 'Any Store'}</div></div>
                <div class="zls-prod-field"><div class="zls-prod-label">Specs</div><div class="zls-prod-value">${esc(m.specs) || '—'}</div></div>
            `;
            document.getElementById('m-product-url').style.display = 'none';
        }
        document.getElementById('m-product-grid').innerHTML = gridHTML;
        document.getElementById('m-content-desc').textContent = m.content_desc || 'No description provided.';

        // --- PROGRESS STEPPER LOGIC ---
        const steps = [
            { label: 'Received', sub: 'Texas, US' },
            { label: 'Processing', sub: 'Warehouse' },
            { label: 'In Transit', sub: 'Flying' },
            { label: 'Delivered', sub: 'Destination' }
        ];
        
        let currentStep = 1;
        if (d.status === 'delivered') currentStep = 4;
        else if (d.status === 'shipped') currentStep = 3;
        else if (d.status === 'paid' || d.status === 'received_us' || d.status === 'purchasing') currentStep = 2;
        
        // For delivered status, show 100% progress
        const progressPercent = (d.status === 'delivered') ? 100 : ((currentStep - 1) / 3) * 100;
        
        let stepperHTML = `
            <div class="zls-progress">
                <div class="zls-progress-line"></div>
                <div class="zls-progress-fill" style="width: ${progressPercent}%;"></div>
        `;
        
        steps.forEach((step, i) => {
            const stepIndex = i + 1;
            let dotClass = '';
            let dotContent = stepIndex;
            
            // For delivered status, all steps including the last one should be completed
            if (d.status === 'delivered') {
                dotClass = 'completed';
                dotContent = '✓';
            } else if (stepIndex < currentStep) {
                dotClass = 'completed';
                dotContent = '✓';
            } else if (stepIndex === currentStep) {
                // If current step is the last one (delivered), treat it as completed
                if (stepIndex === steps.length) {
                    dotClass = 'completed';
                } else {
                    dotClass = 'active';
                }
                dotContent = '✓';
            }
            
            stepperHTML += `
                <div class="zls-progress-step">
                    <div class="zls-step-dot ${dotClass}">${dotContent}</div>
                    <div class="zls-step-label">${step.label}</div>
                    <div class="zls-step-sub">${step.sub}</div>
                </div>
            `;
        });
        
        stepperHTML += '</div>';
        document.getElementById('m-timeline').innerHTML = stepperHTML;

        // --- Info Right & Address ---
        document.getElementById('m-customer-id').textContent = d.customer_id;
        document.getElementById('m-req-type').textContent = d.type === 'zls_ship' ? 'Ship For Me' : 'Buy For Me';
        
        const addrParts = [m.address_line1, m.city, m.state, m.postal_code].filter(Boolean);
        document.getElementById('m-address').innerHTML = `
            <p style="margin:0 0 4px;font-weight:700!important;">${esc(m.recipient_name) || '—'}</p>
            <p style="margin:0 0 4px;font-size:13px;color:#6B7280;">${esc(m.recipient_phone) || '—'}</p>
            <p style="margin:0;font-size:13px;color:#6B7280;">${esc(m.recipient_email) || '—'}</p>
            <div class="zls-addr-box">
                ${addrParts.length ? addrParts.join(', ') + '<br>Nigeria' : 'No Address'}
            </div>
        `;
    
        
        
        
        // --- PAYMENT CARD LOGIC (MUST BE INSIDE populateModal) ---
const btnPay = document.getElementById('btn-confirm-payment');
const amountDisplay = document.getElementById('pay-amount-display');

// Helper function to format currency
function formatCurrency(amount) {
    if (!amount) return '';
    const num = parseFloat(amount);
    return '₦' + num.toLocaleString('en-NG', { minimumFractionDigits: 0, maximumFractionDigits: 0 });
}

if (btnPay && amountDisplay) {
    // Show quote pending or the quote amount based on status
    if (d.status === 'pending') {
        amountDisplay.textContent = 'Quote Pending';
        // Hide button when pending
        btnPay.style.display = 'none';
    } else if ((d.status === 'quote_sent' || d.status === 'payment_pending' || d.status === 'paid' || d.status === 'purchasing' || d.status === 'received_us' || d.status === 'shipped' || d.status === 'delivered') && m.quote_amount) {
        // Show the quoted amount for all statuses after quote is sent
        amountDisplay.textContent = formatCurrency(m.quote_amount) + ' + vat';
        // Show button only when quote_sent
        btnPay.style.display = (d.status === 'quote_sent') ? 'flex' : 'none';
    } else {
        amountDisplay.textContent = 'Quote Pending';
        btnPay.style.display = 'none';
    }
    
    // Check payment status and update button
    if (d.status === 'payment_pending') {
        // User has clicked "I've Paid" - waiting for admin
        btnPay.disabled = true;
        btnPay.innerHTML = '⏳ Verifying Payment';
        btnPay.classList.remove('confirmed');
    } else if (d.status === 'paid') {
        // Admin has confirmed payment
        btnPay.disabled = true;
        btnPay.innerHTML = '✓ Payment Received!';
        btnPay.classList.add('confirmed');
    } else if (d.status === 'quote_sent') {
        // User can click to confirm payment only when quote_sent
        btnPay.disabled = false;
        btnPay.innerHTML = '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"></polyline></svg> I\'ve Paid & Confirm Order';
        btnPay.classList.remove('confirmed');
    } else {
        // Other statuses - disable button
        btnPay.disabled = true;
        btnPay.textContent = 'Payment Not Required';
    }
}
        
    }

    function esc(str) {
        const div = document.createElement('div');
        div.textContent = str || '';
        return div.innerHTML;
    }

    // --- MODAL EVENTS ---
    document.addEventListener('click', function(e) {
        if (e.target.closest('.zls-open-modal')) {
            e.preventDefault();
            openModal(e.target.closest('.zls-open-modal').dataset.postId);
        }
    });
    
    overlay.addEventListener('click', function(e) { if (e.target === overlay) closeModal(); });
    document.addEventListener('keydown', function(e) { if (e.key === 'Escape' && overlay.style.display !== 'none') closeModal(); });


   
   
   // Inside the IIFE, replace the payment click handler with:

document.addEventListener('click', function(e) {
    const btn = e.target.closest('#btn-confirm-payment');
    if (!btn) return;
    e.preventDefault();

    if (btn.disabled || btn.classList.contains('confirmed')) return;
    if (!currentPostId) {
        console.error('Payment Error: No request ID found.');
        return;
    }

    // Confirm action
    if (!confirm('Have you completed the payment to the account above? This will notify our team to verify your payment.')) {
        return;
    }

    // Loading State
    btn.disabled = true;
    btn.innerHTML = '<div class="zls-spinner" style="width:18px;height:18px;border-color:#D1D5DB;border-top-color:#111827;"></div> Confirming...';

    const formData = new FormData();
    formData.append('action', 'confirm_payment');
    formData.append('post_id', currentPostId);

    fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            // Show "Verifying Payment" state
            btn.innerHTML = '⏳ Verifying Payment';
            showToast('✅ Payment confirmation sent! We will verify shortly.', 'success');
            
            setTimeout(() => {
                if (currentPostId) fetchDetails(currentPostId);
            }, 1500);
        } else {
            alert('❌ ' + (data.data || 'Failed to confirm payment.'));
            btn.disabled = false;
            btn.innerHTML = '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"></polyline></svg> I\'ve Paid & Confirm Order';
        }
    })
    .catch(err => {
        console.error('Payment AJAX Error:', err);
        alert('Network error. Please try again.');
        btn.disabled = false;
        btn.innerHTML = '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"></polyline></svg> I\'ve Paid & Confirm Order';
    });
});
   
   
   
})(); // ✅ END OF IIFE

// --- DELETE REQUEST LOGIC (Outside IIFE) ---
document.addEventListener('click', function(e) {
    const delBtn = e.target.closest('.zls-btn-delete');
    if (!delBtn) return;
    
    e.preventDefault();
    const postId = delBtn.dataset.postId;
    const nonce  = delBtn.dataset.nonce;
    const row    = delBtn.closest('tr');
    
    if (!confirm('Are you sure you want to delete this pending request? This action cannot be undone.')) {
        return;
    }
    
    delBtn.disabled = true;
    delBtn.innerHTML = '<div class="zls-spinner" style="width:14px;height:14px;border-width:2px;"></div>';
    
    const formData = new FormData();
    formData.append('action', 'zls_delete_request');
    formData.append('post_id', postId);
    formData.append('nonce', nonce);
    
    fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            row.style.transition = 'all 0.3s ease';
            row.style.opacity = '0';
            row.style.transform = 'translateX(-20px)';
            setTimeout(() => row.remove(), 300);
            updateDashboardStats(-1);
            showToast('✅ Request deleted successfully.', 'success');
        } else {
            alert('❌ ' + (data.data || 'Failed to delete request.'));
            delBtn.disabled = false;
            delBtn.innerHTML = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg>';
        }
    })
    .catch(err => {
        console.error(err);
        alert('Network error. Please try again.');
        delBtn.disabled = false;
        delBtn.innerHTML = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg>';
    });
});

// Helper: Update stats dynamically
function updateDashboardStats(change) {
    const activeEl = document.querySelector('.zls-stat-card:nth-child(1) .zls-stat-value');
    const totalEl  = document.querySelector('.zls-stat-card:nth-child(4) .zls-stat-value');
    if (activeEl) activeEl.textContent = Math.max(0, parseInt(activeEl.textContent) + change);
    if (totalEl) totalEl.textContent = Math.max(0, parseInt(totalEl.textContent) + change);
}

// Helper: Toast notification
function showToast(message, type = 'success') {
    const toast = document.createElement('div');
    toast.style.cssText = `
        position: fixed; bottom: 30px; right: 30px; background: ${type === 'success' ? '#10b981' : '#ef4444'}; 
        color: #fff; padding: 14px 24px; border-radius: 10px; font-weight: 600; font-size: 14px; 
        box-shadow: 0 4px 12px rgba(0,0,0,0.15); z-index: 100000; animation: slideIn 0.3s ease;
    `;
    toast.textContent = message;
    document.body.appendChild(toast);
    setTimeout(() => {
        toast.style.opacity = '0';
        toast.style.transition = 'opacity 0.3s';
        setTimeout(() => toast.remove(), 300);
    }, 3000);
}

// --- AJAX LOAD MORE PAGINATION ---
const loadMoreBtn = document.getElementById('zls-load-more-btn');
if (loadMoreBtn) {
    loadMoreBtn.addEventListener('click', function() {
        const btn = this;
        const nextPage = parseInt(btn.dataset.page) + 1;
        const nonce = btn.dataset.nonce;
        const tbody = document.querySelector('.zls-activity-table tbody');

        btn.disabled = true;
        btn.innerHTML = '<div class="zls-spinner" style="width:16px;height:16px;border-width:2px;margin:0 auto;"></div> Loading...';

        const formData = new FormData();
        formData.append('action', 'load_recent_activity');
        formData.append('page', nextPage);
        formData.append('nonce', nonce);

        fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
            method: 'POST',
            body: formData
        })
        .then(res => res.json())
        .then(data => {
            if (data.success && data.data.html.trim() !== '') {
                tbody.insertAdjacentHTML('beforeend', data.data.html);
                btn.dataset.page = nextPage;
                if (!data.data.has_more) {
                    document.getElementById('zls-load-more-wrap').style.display = 'none';
                } else {
                    btn.disabled = false;
                    btn.textContent = 'Load More Activity';
                }
            } else {
                document.getElementById('zls-load-more-wrap').style.display = 'none';
            }
        })
        .catch(err => {
            console.error('Pagination Error:', err);
            btn.disabled = false;
            btn.textContent = 'Load More Activity';
        });
    });
}
</script>
        
        
        <?php
        return ob_get_clean();
    }

    // ... Helper functions (get_recent_active_shipments, get_shipment_progress) remain unchanged ...
    private static function get_recent_active_shipments($user_id, $limit = 2) {
        $active_statuses = array('pending', 'quote_sent', 'payment_pending', 'paid', 'purchasing', 'received_us', 'shipped');
        $query = new WP_Query(array(
            'post_type' => array('zls_ship', 'zls_buy'),
            'author' => $user_id,
            'meta_query' => array(
                array(
                    'key' => '_zls_status',
                    'value' => $active_statuses,
                    'compare' => 'IN'
                )
            ),
            'posts_per_page' => $limit,
            'orderby' => 'date',
            'order' => 'DESC'
        ));
        $shipments = array();
        if ($query->have_posts()) {
            while ($query->have_posts()) {
                $query->the_post();
                $post_id = get_the_ID();
                $type = get_post_type();
                $status = get_post_meta($post_id, '_zls_status', true) ?: 'pending';
                $title = get_the_title() ?: 'Untitled Request';
                $date = get_the_date('M j, Y');
                $shipments[] = self::get_shipment_progress($type, $status, $title, $date);
            }
            wp_reset_postdata();
        }
        return $shipments;
    }
    private static function get_shipment_progress($type, $status, $title, $date) {
        $is_buy = ($type === 'zls_buy');
        if ($is_buy) {
            $steps = array(
                array('label' => 'Purchased', 'sub' => 'Texas, US'),
                array('label' => 'Processing', 'sub' => 'Warehouse'),
                array('label' => 'In Transit', 'sub' => 'Flying'),
                array('label' => 'Delivered', 'sub' => 'Destination')
            );
            $step_map = array(
                'pending' => 1, 'quote_sent' => 1, 'payment_pending' => 1,
                'paid' => 2, 'purchasing' => 2,
                'received_us' => 3, 'shipped' => 3,
                'delivered' => 4
            );
        } else {
            $steps = array(
                array('label' => 'Received', 'sub' => 'Texas, US'),
                array('label' => 'Processing', 'sub' => 'Warehouse'),
                array('label' => 'In Transit', 'sub' => 'Flying'),
                array('label' => 'Delivered', 'sub' => 'Destination')
            );
            $step_map = array(
                'pending' => 1, 'quote_sent' => 1, 'payment_pending' => 1,
                'paid' => 2, 'received_us' => 2, 'purchasing' => 2,
                'shipped' => 3, 'delivered' => 4
            );
        }
        $current_step = isset($step_map[$status]) ? $step_map[$status] : 1;
        $progress = (($current_step - 1) / 3) * 100;
        $status_labels = array(
            'pending' => 'Pending', 'quote_sent' => 'Quote Sent',
            'payment_pending' => 'Awaiting Payment', 'paid' => 'Paid & Processing',
            'purchasing' => 'Processing', 'received_us' => 'Processing',
            'shipped' => 'In Transit', 'delivered' => 'Delivered'
        );
        $status_label = isset($status_labels[$status]) ? $status_labels[$status] : 'Pending';
        return array(
            'type' => $type, 'title' => $title, 'date' => $date,
            'status' => $status, 'status_label' => $status_label,
            'current_step' => $current_step, 'progress' => $progress, 'steps' => $steps
        );
    }
}