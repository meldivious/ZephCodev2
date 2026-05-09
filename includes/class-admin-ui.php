<?php
/**
 * Zephora Logistic Systems - Admin UI
 * iOS-inspired admin interface with request management
 */
class ZLS_Admin_UI {
    
    public static function init() {
        static $init = false;
        if ($init) return;
        $init = true;
        
        // Admin menu
        add_action('admin_menu', [__CLASS__, 'add_admin_menu']);
        
        // Assets
        add_action('admin_enqueue_scripts', [__CLASS__, 'enqueue_admin_assets']);
        
        // Existing hooks
        add_action('add_meta_boxes', [__CLASS__, 'meta_boxes']);
        add_action('save_post_zls_ship', [__CLASS__, 'save_request'], 10, 2);
        add_action('save_post_zls_buy', [__CLASS__, 'save_request'], 10, 2);
        add_action('manage_zls_ship_posts_custom_column', [__CLASS__, 'custom_column'], 10, 2);
        add_action('manage_zls_buy_posts_custom_column', [__CLASS__, 'custom_column'], 10, 2);
        add_filter('manage_edit-zls_ship_columns', [__CLASS__, 'add_columns']);
        add_filter('manage_edit-zls_buy_columns', [__CLASS__, 'add_columns']);
        
        // Bulk actions
        add_action('admin_footer-edit.php', [__CLASS__, 'bulk_actions_js']);
        add_action('load-edit.php', [__CLASS__, 'handle_bulk_actions']);
    }

    // ADMIN MENU REGISTRATION
    public static function add_admin_menu() {
        // Top-level menu
        add_menu_page(
            'Zephora Logistics',
            'Zephora Logistics',
            'manage_options',
            'zls-dashboard',
            [__CLASS__, 'render_admin_dashboard'],
            'dashicons-airplane',
            30
        );
        
        // Submenus
        add_submenu_page(
            'zls-dashboard',
            'Dashboard',
            'Dashboard',
            'manage_options',
            'zls-dashboard',
            [__CLASS__, 'render_admin_dashboard']
        );
        
        add_submenu_page(
            'zls-dashboard',
            'Ship Requests',
            'Ship Requests',
            'manage_options',
            'edit.php?post_type=zls_ship'
        );
        
        add_submenu_page(
            'zls-dashboard',
            'Buy Requests',
            'Buy Requests',
            'manage_options',
            'edit.php?post_type=zls_buy'
        );
        
        add_submenu_page(
            'zls-dashboard',
            'KYC Reviews',
            'KYC Reviews <span class="awaiting-mod count-' . self::get_pending_kyc_count() . '"><span class="pending-count">' . self::get_pending_kyc_count() . '</span></span>',
            'manage_options',
            'zls-kyc-reviews',
            [__CLASS__, 'render_kyc_reviews'],
            4
        );
        
        add_submenu_page(
            'zls-dashboard',
            'Email Templates',
            'Email Templates',
            'manage_options',
            'zls-email-templates',
            [__CLASS__, 'render_email_templates']
        );
        
        add_submenu_page(
            'zls-dashboard',
            'Settings',
            'Settings',
            'manage_options',
            'zls-settings',
            [__CLASS__, 'render_settings']
        );
    }

    // ADMIN ASSETS
    public static function enqueue_admin_assets($hook) {
        // Only load on our admin pages
        if (strpos($hook, 'zls-') === false && $hook !== 'edit.php') return;
        
        // Inline CSS (matches frontend design)
        wp_add_inline_style('wp-admin', '
            /* Zephora Admin Styles */
            .zls-admin-wrap { font-family: "Segoe UI", system-ui, -apple-system, sans-serif; }
            .zls-stats-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px; margin: 20px 0; }
            .zls-stat-card { background: #fff; padding: 20px; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.04); border: 1px solid #e5e7eb; }
            .zls-stat-label { font-size: 12px; font-weight: 600; color: #6b7280; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 8px; }
            .zls-stat-value { font-size: 28px; font-weight: 800; color: #111827; line-height: 1; }
            .zls-status-badge { display: inline-block; padding: 4px 12px; border-radius: 20px; font-size: 11px; font-weight: 700; text-transform: uppercase; }
            .zls-status-pending { background: #fef3c7; color: #92400e; }
            .zls-status-quote_sent { background: #dbeafe; color: #1e40af; }
            .zls-status-payment_pending { background: #fef3c7; color: #92400e; }
            .zls-status-paid { background: #d1fae5; color: #065f46; }
            .zls-status-purchasing { background: #e0e7ff; color: #4338ca; }
            .zls-status-received_us { background: #e0e7ff; color: #4338ca; }
            .zls-status-shipped { background: #dbeafe; color: #1e40af; }
            .zls-status-delivered { background: #d1fae5; color: #065f46; }
            .zls-status-cancelled { background: #fee2e2; color: #dc2626; }
            .zls-btn-primary { background: #6b3ae4; color: #fff; border: none; padding: 10px 20px; border-radius: 10px; font-weight: 600; cursor: pointer; }
            .zls-btn-primary:hover { background: #5a2dcf; }
            .zls-btn-secondary { background: #fff; color: #6b3ae4; border: 1px solid #e5e7eb; padding: 10px 20px; border-radius: 10px; font-weight: 600; cursor: pointer; }
            .zls-btn-secondary:hover { background: #f9fafb; }
            .zls-card { background: #fff; border: 1px solid #e5e7eb; border-radius: 16px; padding: 24px; margin-bottom: 20px; }
            .zls-card-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; padding-bottom: 16px; border-bottom: 1px solid #f3f4f6; }
            .zls-card-title { font-size: 18px; font-weight: 700; color: #111827; margin: 0; }
            .zls-form-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 20px; }
            .zls-form-group label { display: block; font-size: 12px; font-weight: 700; color: #6b7280; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 8px; }
            .zls-form-group input, .zls-form-group select, .zls-form-group textarea { width: 100%; padding: 12px 14px; border: 1px solid #e5e7eb; border-radius: 10px; font-size: 14px; }
            .zls-form-group input:focus, .zls-form-group select:focus, .zls-form-group textarea:focus { outline: none; border-color: #6b3ae4; box-shadow: 0 0 0 3px rgba(107,58,228,0.1); }
            .zls-kyc-thumb { width: 80px; height: 80px; border-radius: 8px; object-fit: cover; border: 1px solid #e5e7eb; }
            .zls-kyc-row { display: flex; gap: 16px; align-items: center; padding: 12px 0; border-bottom: 1px solid #f3f4f6; }
            .zls-kyc-row:last-child { border-bottom: none; }
            @media (max-width: 1200px) { .zls-stats-grid { grid-template-columns: repeat(2, 1fr); } }
            @media (max-width: 768px) { .zls-stats-grid, .zls-form-grid { grid-template-columns: 1fr; } }
        ');
    }

    // ADMIN DASHBOARD
    public static function render_admin_dashboard() {
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        
        // Fetch stats
        $total_requests = wp_count_posts('zls_ship')->publish + wp_count_posts('zls_buy')->publish;
        $pending_review = self::count_by_status('pending');
        $awaiting_payment = self::count_by_status('payment_pending');
        $in_progress = self::count_by_status('paid') + self::count_by_status('purchasing') + self::count_by_status('received_us') + self::count_by_status('shipped');
        $delivered = self::count_by_status('delivered');
        $pending_kyc = self::get_pending_kyc_count();
        
        ?>
        <div class="wrap zls-admin-wrap">
            <h1 class="wp-heading-inline">🚀 Zephora Logistics Admin</h1>
            <a href="<?php echo admin_url('post-new.php?post_type=zls_ship'); ?>" class="page-title-action">+ New Ship Request</a>
            
            <!-- Stats Grid -->
            <div class="zls-stats-grid">
                <div class="zls-stat-card">
                    <div class="zls-stat-label">Total Requests</div>
                    <div class="zls-stat-value"><?php echo number_format($total_requests); ?></div>
                </div>
                <div class="zls-stat-card">
                    <div class="zls-stat-label">Pending Review</div>
                    <div class="zls-stat-value" style="color:#92400e;"><?php echo number_format($pending_review); ?></div>
                </div>
                <div class="zls-stat-card">
                    <div class="zls-stat-label">Awaiting Payment</div>
                    <div class="zls-stat-value" style="color:#92400e;"><?php echo number_format($awaiting_payment); ?></div>
                </div>
                <div class="zls-stat-card">
                    <div class="zls-stat-label">In Progress</div>
                    <div class="zls-stat-value" style="color:#4338ca;"><?php echo number_format($in_progress); ?></div>
                </div>
                <div class="zls-stat-card">
                    <div class="zls-stat-label">Delivered</div>
                    <div class="zls-stat-value" style="color:#065f46;"><?php echo number_format($delivered); ?></div>
                </div>
                <div class="zls-stat-card">
                    <div class="zls-stat-label">Pending KYC</div>
                    <div class="zls-stat-value" style="color:#dc2626;"><?php echo number_format($pending_kyc); ?></div>
                </div>
            </div>
            
            
            <!-- Recent Activity -->
            <div class="zls-card">
                <div class="zls-card-header">
                    <h2 class="zls-card-title">Recent Activity</h2>
                    <a href="<?php echo admin_url('edit.php?post_type=zls_ship'); ?>" class="zls-btn-secondary">View All</a>
                </div>
                <?php
                $recent = new WP_Query(array(
                    'post_type' => array('zls_ship', 'zls_buy'),
                    'posts_per_page' => 5,
                    'orderby' => 'date',
                    'order' => 'DESC'
                ));
                if ($recent->have_posts()):
                ?>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th>Request</th>
                            <th>Type</th>
                            <th>Customer</th>
                            <th>Status</th>
                            <th>Date</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while($recent->have_posts()): $recent->the_post(); 
                            $status = get_post_meta(get_the_ID(), '_zls_status', true) ?: 'pending';
                            $type = get_post_type() === 'zls_ship' ? 'Ship' : 'Buy';
                            $user = get_userdata(get_post_field('post_author', get_the_ID()));
                        ?>
                        <tr>
                            <td><strong><?php the_title(); ?></strong></td>
                            <td><?php echo $type; ?></td>
                            <td><?php echo $user ? esc_html($user->display_name) : 'Unknown'; ?></td>
                            <td><span class="zls-status-badge zls-status-<?php echo esc_attr($status); ?>"><?php echo esc_html(ucfirst(str_replace('_', ' ', $status))); ?></span></td>
                            <td><?php echo get_the_date('M j, Y'); ?></td>
                            <td><a href="<?php echo get_edit_post_link(); ?>" class="zls-btn-secondary" style="padding:6px 12px;font-size:12px;">Edit</a></td>
                        </tr>
                        <?php endwhile; wp_reset_postdata(); ?>
                    </tbody>
                </table>
                <?php else: ?>
                <p style="color:#6b7280;">No recent activity.</p>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }

    // KYC REVIEWS PAGE
    public static function render_kyc_reviews() {
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        
        // Handle actions
        if (isset($_GET['zls_kyc_action'], $_GET['user_id'], $_GET['_wpnonce']) && wp_verify_nonce($_GET['_wpnonce'], 'zls_kyc_admin')) {
            $user_id = absint($_GET['user_id']);
            $action = sanitize_text_field($_GET['zls_kyc_action']);
            $reason = isset($_GET['reason']) ? sanitize_textarea_field($_GET['reason']) : '';
            
            if ($action === 'approve') {
                update_user_meta($user_id, '_zls_kyc_status', 'approved');
                update_user_meta($user_id, '_zls_kyc_approved_at', current_time('mysql'));
                if (class_exists('ZLS_Audit_Logger')) {
                    ZLS_Audit_Logger::log('KYC_APPROVED', ['user_id' => $user_id, 'admin_id' => get_current_user_id()]);
                }
                // Notify user
                if (class_exists('ZLS_Notifications')) {
                    ZLS_Notifications::send($user_id, 'kyc_approved', []);
                }
                echo '<div class="notice notice-success"><p>KYC approved for user #' . $user_id . '</p></div>';
            } elseif ($action === 'deny') {
                update_user_meta($user_id, '_zls_kyc_status', 'denied');
                update_user_meta($user_id, '_zls_kyc_note', $reason);
                update_user_meta($user_id, '_zls_kyc_attempts', (int)get_user_meta($user_id, '_zls_kyc_attempts', true) + 1);
                if (class_exists('ZLS_Audit_Logger')) {
                    ZLS_Audit_Logger::log('KYC_DENIED', ['user_id' => $user_id, 'admin_id' => get_current_user_id(), 'reason' => $reason]);
                }
                // Notify user
                if (class_exists('ZLS_Notifications')) {
                    ZLS_Notifications::send($user_id, 'kyc_denied', ['reason' => $reason]);
                }
                echo '<div class="notice notice-warning"><p>KYC denied for user #' . $user_id . '</p></div>';
            }
        }
        
        // Fetch pending KYC users
        $pending_users = get_users(array(
            'meta_key' => '_zls_kyc_status',
            'meta_value' => 'pending',
            'orderby' => 'meta_value',
            'order' => 'ASC'
        ));
        ?>
        <div class="wrap zls-admin-wrap">
            <h1 class="wp-heading-inline">KYC Reviews</h1>
            <span class="awaiting-mod count-<?php echo count($pending_users); ?>"><span class="pending-count"><?php echo count($pending_users); ?> pending</span></span>
            
            <?php if (empty($pending_users)): ?>
                <div class="zls-card">
                    <p style="color:#6b7280;">No pending KYC submissions. Great job!</p>
                </div>
            <?php else: ?>
                <div class="zls-card">
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th>User</th>
                                <th>Unique ID</th>
                                <th>Phone</th>
                                <th>Submitted</th>
                                <th>Documents</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($pending_users as $user): 
                                $kyc_data = get_user_meta($user->ID, '_zls_kyc_data', true) ?: [];
                                $submitted = isset($kyc_data['submitted_at']) ? $kyc_data['submitted_at'] : 'N/A';
                            ?>
                            <tr>
                                <td>
                                    <strong><?php echo esc_html($user->display_name); ?></strong><br>
                                    <small style="color:#6b7280;"><?php echo esc_html($user->user_email); ?></small>
                                </td>
                                <td><?php echo esc_html(get_user_meta($user->ID, '_zls_unique_id', true) ?: '—'); ?></td>
                                <td><?php echo esc_html(get_user_meta($user->ID, '_zls_phone', true) ?: '—'); ?></td>
                                <td><?php echo $submitted !== 'N/A' ? date('M j, Y', strtotime($submitted)) : '—'; ?></td>
                                <td>
                                    <?php if (!empty($kyc_data['gov_id_file'])): ?>
                                        <a href="#" class="zls-btn-secondary" style="padding:4px 8px;font-size:11px;" onclick="alert('Document viewer coming soon'); return false;">ID</a>
                                    <?php endif; ?>
                                    <?php if (!empty($kyc_data['proof_address_file'])): ?>
                                        <a href="#" class="zls-btn-secondary" style="padding:4px 8px;font-size:11px;" onclick="alert('Document viewer coming soon'); return false;">Address</a>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=zls-kyc-reviews&zls_kyc_action=approve&user_id=' . $user->ID), 'zls_kyc_admin'); ?>" class="zls-btn-primary" style="padding:6px 12px;font-size:12px;" onclick="return confirm('Approve KYC for <?php echo esc_js($user->display_name); ?>?')">Approve</a>
                                    <a href="#" class="zls-btn-secondary" style="padding:6px 12px;font-size:12px;" onclick="toggleDenyForm(<?php echo $user->ID; ?>); return false;">Deny</a>
                                    <div id="deny-form-<?php echo $user->ID; ?>" style="display:none; margin-top:8px;">
                                        <form method="get" style="display:flex; gap:8px;">
                                            <input type="hidden" name="page" value="zls-kyc-reviews">
                                            <input type="hidden" name="zls_kyc_action" value="deny">
                                            <input type="hidden" name="user_id" value="<?php echo $user->ID; ?>">
                                            <?php wp_nonce_field('zls_kyc_admin', '_wpnonce', true, false); ?>
                                            <input type="text" name="reason" placeholder="Reason for denial" required style="padding:6px 10px;border:1px solid #e5e7eb;border-radius:6px;font-size:12px;">
                                            <button type="submit" class="zls-btn-secondary" style="padding:6px 12px;font-size:12px;background:#fee2e2;color:#dc2626;border-color:#fecaca;">Confirm</button>
                                        </form>
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
        function toggleDenyForm(userId) {
            const form = document.getElementById('deny-form-' + userId);
            form.style.display = form.style.display === 'none' ? 'block' : 'none';
        }
        </script>
        <?php
    }

    // SETTINGS PAGE
    public static function render_settings() {
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        
        // Handle save - Bank Details
        if (isset($_POST['zls_save_bank_details'], $_POST['zls_bank_nonce']) && wp_verify_nonce($_POST['zls_bank_nonce'], 'zls_bank_details')) {
            $bank = array(
                'bank_name' => sanitize_text_field($_POST['bank_name'] ?? ''),
                'account_name' => sanitize_text_field($_POST['account_name'] ?? ''),
                'account_number' => sanitize_text_field($_POST['account_number'] ?? ''),
                'swift_code' => sanitize_text_field($_POST['swift_code'] ?? ''),
                'note' => sanitize_textarea_field($_POST['payment_note'] ?? ''),
            );
            update_option('zls_bank_details', $bank);
            echo '<div class="notice notice-success"><p>Bank details saved successfully.</p></div>';
        }
        
        // Handle save - Warehouse Address
        if (isset($_POST['zls_save_warehouse_address'], $_POST['zls_warehouse_nonce']) && wp_verify_nonce($_POST['zls_warehouse_nonce'], 'zls_warehouse_address')) {
            $warehouse = array(
                'address_line1' => sanitize_text_field($_POST['address_line1'] ?? ''),
                'address_line2' => sanitize_text_field($_POST['address_line2'] ?? ''),
                'city' => sanitize_text_field($_POST['city'] ?? ''),
                'state' => sanitize_text_field($_POST['state'] ?? ''),
                'postal_code' => sanitize_text_field($_POST['postal_code'] ?? ''),
                'country' => sanitize_text_field($_POST['country'] ?? 'USA'),
                'contact_phone' => sanitize_text_field($_POST['contact_phone'] ?? ''),
            );
            update_option('zls_warehouse_address', $warehouse);
            echo '<div class="notice notice-success"><p>Warehouse address saved successfully.</p></div>';
        }
        
        // Handle save - SMTP Settings
        if (isset($_POST['zls_save_smtp_settings'], $_POST['zls_smtp_nonce']) && wp_verify_nonce($_POST['zls_smtp_nonce'], 'zls_smtp_settings')) {
            $smtp = array(
                'enabled' => isset($_POST['smtp_enabled']) ? true : false,
                'host' => sanitize_text_field($_POST['smtp_host'] ?? ''),
                'port' => absint($_POST['smtp_port'] ?? 587),
                'encryption' => sanitize_text_field($_POST['smtp_encryption'] ?? 'tls'),
                'username' => sanitize_text_field($_POST['smtp_username'] ?? ''),
                'password' => sanitize_text_field($_POST['smtp_password'] ?? ''),
                'from_email' => sanitize_email($_POST['smtp_from_email'] ?? get_option('admin_email')),
                'from_name' => sanitize_text_field($_POST['smtp_from_name'] ?? get_bloginfo('name')),
            );
            update_option('zls_smtp_settings', $smtp);
            echo '<div class="notice notice-success"><p>SMTP settings saved successfully.</p></div>';
        }
        
        // Handle test email
        if (isset($_POST['zls_test_email'], $_POST['zls_test_nonce']) && wp_verify_nonce($_POST['zls_test_nonce'], 'zls_test_email')) {
            $test_email = sanitize_email($_POST['test_email_to']);
            if ($test_email) {
                $subject = 'Zephora Logistics - Test Email';
                $message = '<h2>Test Email Successful</h2><p>This is a test email from your Zephora Logistics plugin.</p><p>If you received this, your SMTP configuration is working correctly.</p><p>Sent at: ' . current_time('mysql') . '</p>';
                $headers = array('Content-Type: text/html; charset=UTF-8');
                
                if (wp_mail($test_email, $subject, $message, $headers)) {
                    echo '<div class="notice notice-success"><p>Test email sent successfully to ' . esc_html($test_email) . '</p></div>';
                } else {
                    echo '<div class="notice notice-error"><p>Failed to send test email. Check your SMTP configuration.</p></div>';
                }
            }
        }
        
        $bank = get_option('zls_bank_details', array());
        $warehouse = get_option('zls_warehouse_address', array());
        $smtp = get_option('zls_smtp_settings', array());
        ?>
        <div class="wrap zls-admin-wrap">
            <h1 class="wp-heading-inline">Settings</h1>
            
            <!-- Bank Details Form -->
            <form method="post" class="zls-card">
                <?php wp_nonce_field('zls_bank_details', 'zls_bank_nonce'); ?>
                
                <div class="zls-card-header">
                    <h2 class="zls-card-title">Bank Details for Payment</h2>
                    <p class="description">These details are displayed to users on the frontend for bank transfer payments.</p>
                </div>
                
                <div class="zls-form-grid">
                    <div class="zls-form-group">
                        <label>Bank Name</label>
                        <input type="text" name="bank_name" value="<?php echo esc_attr($bank['bank_name'] ?? ''); ?>" class="regular-text">
                    </div>
                    <div class="zls-form-group">
                        <label>Account Name</label>
                        <input type="text" name="account_name" value="<?php echo esc_attr($bank['account_name'] ?? ''); ?>" class="regular-text">
                    </div>
                    <div class="zls-form-group">
                        <label>Account Number</label>
                        <input type="text" name="account_number" value="<?php echo esc_attr($bank['account_number'] ?? ''); ?>" class="regular-text">
                    </div>
                    <div class="zls-form-group">
                        <label>SWIFT/BIC Code (Optional)</label>
                        <input type="text" name="swift_code" value="<?php echo esc_attr($bank['swift_code'] ?? ''); ?>" class="regular-text">
                    </div>
                </div>
                
                <div class="zls-form-group" style="margin-top:16px;">
                    <label>Payment Note Template</label>
                    <textarea name="payment_note" rows="3" class="large-text"><?php echo esc_textarea($bank['note'] ?? 'Reference: ZLS-{request_id}'); ?></textarea>
                    <p class="description">Use <code>{request_id}</code> as placeholder for the request ID.</p>
                </div>
                
                <div style="margin-top:24px;">
                    <button type="submit" name="zls_save_bank_details" class="button button-primary">Save Bank Details</button>
                </div>
            </form>
            
            <!-- Warehouse Address Form -->
            <form method="post" class="zls-card">
                <?php wp_nonce_field('zls_warehouse_address', 'zls_warehouse_nonce'); ?>
                
                <div class="zls-card-header">
                    <h2 class="zls-card-title">Warehouse Address</h2>
                    <p class="description">This address is displayed to users on their dashboard.</p>
                </div>
                
                <div class="zls-form-grid">
                    <div class="zls-form-group">
                        <label>Address Line 1</label>
                        <input type="text" name="address_line1" value="<?php echo esc_attr($warehouse['address_line1'] ?? ''); ?>" class="regular-text" required>
                    </div>
                    <div class="zls-form-group">
                        <label>Address Line 2 (Optional)</label>
                        <input type="text" name="address_line2" value="<?php echo esc_attr($warehouse['address_line2'] ?? ''); ?>" class="regular-text">
                    </div>
                    <div class="zls-form-group">
                        <label>City</label>
                        <input type="text" name="city" value="<?php echo esc_attr($warehouse['city'] ?? ''); ?>" class="regular-text" required>
                    </div>
                    <div class="zls-form-group">
                        <label>State</label>
                        <input type="text" name="state" value="<?php echo esc_attr($warehouse['state'] ?? ''); ?>" class="regular-text" required>
                    </div>
                    <div class="zls-form-group">
                        <label>Postal Code</label>
                        <input type="text" name="postal_code" value="<?php echo esc_attr($warehouse['postal_code'] ?? ''); ?>" class="regular-text" required>
                    </div>
                    <div class="zls-form-group">
                        <label>Country</label>
                        <input type="text" name="country" value="<?php echo esc_attr($warehouse['country'] ?? 'USA'); ?>" class="regular-text">
                    </div>
                    <div class="zls-form-group">
                        <label>Phone</label>
                        <input type="text" name="contact_phone" value="<?php echo esc_attr($warehouse['contact_phone'] ?? ''); ?>" class="regular-text">
                    </div>
                </div>
                
                <div style="margin-top:24px;">
                    <button type="submit" name="zls_save_warehouse_address" class="button button-primary">Save Warehouse Address</button>
                </div>
            </form>
            
            <!-- SMTP Settings Form -->
            <form method="post" class="zls-card">
                <?php wp_nonce_field('zls_smtp_settings', 'zls_smtp_nonce'); ?>
                
                <div class="zls-card-header">
                    <h2 class="zls-card-title">SMTP Email Configuration</h2>
                    <p class="description">Configure SMTP for reliable email delivery. Leave disabled to use default WordPress mail.</p>
                </div>
                
                <div class="zls-form-group">
                    <label style="display:flex;align-items:center;gap:8px;">
                        <input type="checkbox" name="smtp_enabled" <?php checked(!empty($smtp['enabled'])); ?>>
                        Enable SMTP
                    </label>
                </div>
                
                <div class="zls-form-grid">
                    <div class="zls-form-group">
                        <label>SMTP Host</label>
                        <input type="text" name="smtp_host" value="<?php echo esc_attr($smtp['host'] ?? ''); ?>" class="regular-text" placeholder="smtp.gmail.com">
                    </div>
                    <div class="zls-form-group">
                        <label>SMTP Port</label>
                        <input type="number" name="smtp_port" value="<?php echo esc_attr($smtp['port'] ?? '587'); ?>" class="small-text" placeholder="587">
                    </div>
                    <div class="zls-form-group">
                        <label>Encryption</label>
                        <select name="smtp_encryption" class="regular-text">
                            <option value="">None</option>
                            <option value="tls" <?php selected($smtp['encryption'] ?? '', 'tls'); ?>>TLS</option>
                            <option value="ssl" <?php selected($smtp['encryption'] ?? '', 'ssl'); ?>>SSL</option>
                        </select>
                    </div>
                </div>
                
                <div class="zls-form-grid">
                    <div class="zls-form-group">
                        <label>SMTP Username</label>
                        <input type="text" name="smtp_username" value="<?php echo esc_attr($smtp['username'] ?? ''); ?>" class="regular-text">
                    </div>
                    <div class="zls-form-group">
                        <label>SMTP Password</label>
                        <input type="password" name="smtp_password" value="<?php echo esc_attr($smtp['password'] ?? ''); ?>" class="regular-text">
                    </div>
                </div>
                
                <div class="zls-form-grid">
                    <div class="zls-form-group">
                        <label>From Email</label>
                        <input type="email" name="smtp_from_email" value="<?php echo esc_attr($smtp['from_email'] ?? get_option('admin_email')); ?>" class="regular-text">
                    </div>
                    <div class="zls-form-group">
                        <label>From Name</label>
                        <input type="text" name="smtp_from_name" value="<?php echo esc_attr($smtp['from_name'] ?? get_bloginfo('name')); ?>" class="regular-text">
                    </div>
                </div>
                
                <div style="margin-top:24px;">
                    <button type="submit" name="zls_save_smtp_settings" class="button button-primary">Save SMTP Settings</button>
                </div>
            </form>
            
            <!-- Test Email Form -->
            <form method="post" class="zls-card">
                <?php wp_nonce_field('zls_test_email', 'zls_test_nonce'); ?>
                
                <div class="zls-card-header">
                    <h2 class="zls-card-title">Test Email Delivery</h2>
                    <p class="description">Send a test email to verify your configuration.</p>
                </div>
                
                <div class="zls-form-group">
                    <label>Send Test Email To</label>
                    <input type="email" name="test_email_to" value="<?php echo esc_attr(get_option('admin_email')); ?>" class="regular-text" required>
                </div>
                
                <div style="margin-top:24px;">
                    <button type="submit" name="zls_test_email" class="button">Send Test Email</button>
                </div>
            </form>
        </div>
        
        <?php
        // Hook into PHPMailer if SMTP is enabled
        add_action('phpmailer_init', function($phpmailer) use ($smtp) {
            if (empty($smtp['enabled']) || empty($smtp['host'])) return;
            
            $phpmailer->isSMTP();
            $phpmailer->Host = $smtp['host'];
            $phpmailer->Port = $smtp['port'];
            $phpmailer->SMTPAuth = true;
            $phpmailer->Username = $smtp['username'];
            $phpmailer->Password = $smtp['password'];
            $phpmailer->SMTPSecure = $smtp['encryption'];
            $phpmailer->setFrom($smtp['from_email'], $smtp['from_name']);
        });
        ?>
    }

    // EMAIL TEMPLATES PAGE
    public static function render_email_templates() {
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        
        // Handle save
        if (isset($_POST['zls_save_email_templates'], $_POST['zls_email_nonce']) && wp_verify_nonce($_POST['zls_email_nonce'], 'zls_email_templates')) {
            $templates = array();
            $events = array('kyc_approved', 'kyc_denied', 'kyc_banned', 'quote_sent', 'paid', 'purchasing', 'received_us', 'shipped', 'delivered', 'cancelled');
            foreach ($events as $event) {
                $templates[$event] = array(
                    'recipient' => sanitize_text_field($_POST['zls_email_templates'][$event]['recipient'] ?? 'user'),
                    'subject' => sanitize_text_field($_POST['zls_email_templates'][$event]['subject'] ?? ''),
                    'message' => wp_kses_post($_POST['zls_email_templates'][$event]['message'] ?? ''),
                );
            }
            update_option('zls_email_templates', $templates);
            echo '<div class="notice notice-success"><p>Email templates saved successfully.</p></div>';
        }
        
        $templates = get_option('zls_email_templates', array());
        $defaults = ZLS_Settings::get_instance()->get_default_email_templates();
        $admin_email = get_option('admin_email');
        ?>
        <div class="wrap zls-admin-wrap">
            <h1 class="wp-heading-inline">Email Templates</h1>
            
            <div class="zls-card" style="margin-bottom:20px;">
                <div class="zls-card-header">
                    <h2 class="zls-card-title">Email Notification Templates</h2>
                    <p class="description">Customize what gets sent to customers and admins for each event. Available variables: <code>{{customer_name}}</code>, <code>{{item}}</code>, <code>{{amount}}</code>, <code>{{tracking}}</code>, <code>{{status}}</code>, <code>{{request_date}}</code>, <code>{{admin_email}}</code></p>
                </div>
            </div>
            
            <form method="post">
                <?php wp_nonce_field('zls_email_templates', 'zls_email_nonce'); ?>
                
                <?php foreach ($defaults as $event => $default): ?>
                <div class="zls-card" style="margin-bottom:20px;">
                    <div class="zls-card-header">
                        <h3 class="zls-card-title"><?php echo esc_html($default['label']); ?></h3>
                        <p class="description"><?php echo esc_html($default['description']); ?></p>
                    </div>
                    
                    <table class="form-table">
                        <tr>
                            <th style="width:150px;"><label>Send To:</label></th>
                            <td>
                                <fieldset>
                                    <label><input type="radio" name="zls_email_templates[<?php echo esc_attr($event); ?>][recipient]" value="user" <?php checked(!empty($templates[$event]) ? $templates[$event]['recipient'] : $default['recipient'], 'user'); ?>> Customer Only</label><br>
                                    <label><input type="radio" name="zls_email_templates[<?php echo esc_attr($event); ?>][recipient]" value="admin" <?php checked(!empty($templates[$event]) ? $templates[$event]['recipient'] : $default['recipient'], 'admin'); ?>> Admin Only</label><br>
                                    <label><input type="radio" name="zls_email_templates[<?php echo esc_attr($event); ?>][recipient]" value="both" <?php checked(!empty($templates[$event]) ? $templates[$event]['recipient'] : $default['recipient'], 'both'); ?>> Both</label>
                                </fieldset>
                            </td>
                        </tr>
                        <tr>
                            <th><label>Subject Line:</label></th>
                            <td>
                                <input type="text" name="zls_email_templates[<?php echo esc_attr($event); ?>][subject]" class="large-text" value="<?php echo esc_attr(!empty($templates[$event]) ? $templates[$event]['subject'] : $default['subject']); ?>">
                            </td>
                        </tr>
                        <tr>
                            <th><label>Message Body:</label></th>
                            <td>
                                <?php 
                                    $content = !empty($templates[$event]) ? $templates[$event]['message'] : $default['message'];
                                    wp_editor($content, 'zls_email_template_' . $event, array(
                                        'textarea_name' => 'zls_email_templates[' . esc_attr($event) . '][message]',
                                        'media_buttons' => false,
                                        'textarea_rows' => 6,
                                        'teeny' => true
                                    ));
                                ?>
                            </td>
                        </tr>
                    </table>
                </div>
                <?php endforeach; ?>
                
                <div style="margin-top:24px;">
                    <button type="submit" name="zls_save_email_templates" class="button button-primary">Save Email Templates</button>
                </div>
            </form>
        </div>
        <?php
    }

    // EXISTING METHODS (kept from your original)
    public static function meta_boxes() {
        if (!current_user_can('manage_options')) return;
        add_meta_box('zls_request_box', 'Workflow & Quote', [__CLASS__, 'render_meta'], ['zls_ship', 'zls_buy'], 'side');
        add_meta_box('zls_data_box', 'Request Data', [__CLASS__, 'render_data'], ['zls_ship', 'zls_buy'], 'normal');
        add_meta_box('zls_notes_box', 'Internal Notes', [__CLASS__, 'render_notes'], ['zls_ship', 'zls_buy'], 'normal');
    }

    public static function render_meta($post) {
        wp_nonce_field('zls_admin', 'zls_nonce');
        $status = get_post_meta($post->ID, '_zls_status', true) ?: 'pending';
        $quote = get_post_meta($post->ID, '_zls_quote_amount', true);
        $track = get_post_meta($post->ID, '_zls_tracking_admin', true);
        ?>
        <p><label><strong>Status:</strong><br>
            <select name="zls_status" style="width:100%; margin-top:4px;">
                <?php 
                $labels = class_exists('ZLS_Request_Base') ? ZLS_Request_Base::get_status_labels() : [];
                foreach($labels as $k => $v): ?>
                    <option value="<?php echo esc_attr($k); ?>" <?php selected($status, $k); ?>><?php echo esc_html($v); ?></option>
                <?php endforeach; ?>
            </select>
        </label></p>
        <p><label><strong>Quote Amount (₦):</strong><br>
            <input type="number" step="0.01" name="zls_quote" value="<?php echo esc_attr($quote); ?>" style="width:100%; margin-top:4px;" placeholder="Enter the amount to charge the customer">
        </label></p>
        <p><label><strong>Admin Tracking #:</strong><br>
            <input type="text" name="zls_track" value="<?php echo esc_attr($track); ?>" style="width:100%; margin-top:4px;" placeholder="Optional">
        </label></p>
        <?php
    }

    public static function render_notes($post) {
        $notes = get_post_meta($post->ID, '_zls_notes', true);
        ?>
        <textarea name="zls_notes" rows="4" style="width:100%;"><?php echo esc_textarea($notes); ?></textarea>
        <p style="font-size:11px; color:#6b7280; margin-top:8px;">Internal notes - not visible to customers.</p>
        <?php
    }

    public static function render_data($post) {
        $meta = get_post_meta($post->ID);
        echo '<table class="widefat"><tbody>';
        foreach($meta as $key => $val) {
            if (strpos($key, '_zls_') === 0 && $key !== '_zls_notes') {
                $val = is_array($val) ? reset($val) : $val;
                echo '<tr><td style="width:40%;"><strong>'.esc_html(str_replace('_zls_','',$key)).'</strong></td><td>'.esc_html($val ?: '—').'</td></tr>';
            }
        }
        echo '</tbody></table>';
    }

    public static function save_request($post_id, $post) {
        if (!isset($_POST['zls_nonce']) || !wp_verify_nonce($_POST['zls_nonce'], 'zls_admin')) return;
        if (!current_user_can('edit_post', $post_id)) return;
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
        
        // Update meta
        $old_status = get_post_meta($post_id, '_zls_status', true);
        $new_status = sanitize_text_field($_POST['zls_status'] ?? 'pending');
        
        update_post_meta($post_id, '_zls_status', $new_status);
        update_post_meta($post_id, '_zls_quote_amount', sanitize_text_field($_POST['zls_quote'] ?? ''));
        update_post_meta($post_id, '_zls_tracking_admin', sanitize_text_field($_POST['zls_track'] ?? ''));
        update_post_meta($post_id, '_zls_notes', sanitize_textarea_field($_POST['zls_notes'] ?? ''));
        
        // Send email notifications on status change
        if ($new_status !== $old_status && class_exists('ZLS_Notifications')) {
            $user = get_userdata($post->post_author);
            if ($user) {
                ZLS_Notifications::send_notification($post_id, $new_status, $user, $post);
            }
        }
        
        // Audit log
        if (class_exists('ZLS_Audit_Logger')) {
            ZLS_Audit_Logger::log('REQUEST_UPDATED', [
                'post_id' => $post_id,
                'status' => $new_status,
                'admin_id' => get_current_user_id()
            ]);
        }
    }

    public static function add_columns($cols) {
        $cols['cb'] = '<input type="checkbox">';
        $cols['title'] = 'Request';
        $cols['customer'] = 'Customer';
        $cols['status'] = 'Status';
        $cols['quote'] = 'Quote (₦)';
        $cols['payment'] = 'Payment (₦)';
        $cols['date'] = 'Date';
        return $cols;
    }
    
    public static function custom_column($col, $post_id) {
        if ($col === 'status') {
            $status = get_post_meta($post_id, '_zls_status', true) ?: 'pending';
            $labels = class_exists('ZLS_Request_Base') ? ZLS_Request_Base::get_status_labels() : [];
            echo '<span class="zls-status-badge zls-status-'.esc_attr($status).'">'.esc_html($labels[$status] ?? ucfirst($status)).'</span>';
        }
        if ($col === 'quote') {
            $quote = floatval(get_post_meta($post_id, '_zls_quote_amount', true));
            echo $quote ? '₦'.number_format($quote) : '<span style="color:#9ca3af;">—</span>';
        }
        if ($col === 'payment') {
            $quote = floatval(get_post_meta($post_id, '_zls_quote_amount', true));
            echo $quote ? '₦'.number_format($quote) : '<span style="color:#9ca3af;">—</span>';
        }
        if ($col === 'customer') {
            $post = get_post($post_id);
            $user = get_userdata($post->post_author);
            if ($user) {
                echo '<strong>'.esc_html($user->display_name).'</strong><br>';
                echo '<small style="color:#6b7280;">'.esc_html(get_user_meta($post->post_author, '_zls_unique_id', true) ?: '—').'</small>';
            }
        }
    }
    
    // BULK ACTIONS
    public static function bulk_actions_js() {
        global $typenow;
        if (!in_array($typenow, ['zls_ship', 'zls_buy'])) return;
        ?>
        <script>
        jQuery(function($) {
            $('#doaction, #doaction2').click(function(e) {
                const action = $('select[name="action"]').val();
                if (action === 'zls_mark_paid') {
                    if (!confirm('Mark selected requests as PAID? This will notify customers.')) {
                        e.preventDefault();
                    }
                }
                if (action === 'zls_cancel') {
                    if (!confirm('Cancel selected requests? This cannot be undone.')) {
                        e.preventDefault();
                    }
                }
            });
        });
        </script>
        <?php
    }
    
    public static function handle_bulk_actions() {
        $screen = get_current_screen();
        if (!in_array($screen->id, ['edit-zls_ship', 'edit-zls_buy'])) return;
        
        $action = isset($_REQUEST['action']) ? sanitize_text_field($_REQUEST['action']) : '';
        if ($action === '-1') $action = isset($_REQUEST['action2']) ? sanitize_text_field($_REQUEST['action2']) : '';
        
        if (in_array($action, ['zls_mark_paid', 'zls_cancel']) && !empty($_REQUEST['post'])) {
            check_admin_referer('bulk-posts');
            $post_ids = array_map('absint', $_REQUEST['post']);
            
            foreach ($post_ids as $post_id) {
                if (!current_user_can('edit_post', $post_id)) continue;
                
                if ($action === 'zls_mark_paid') {
                    update_post_meta($post_id, '_zls_status', 'paid');
                    if (class_exists('ZLS_Notifications')) {
                        $post = get_post($post_id);
                        ZLS_Notifications::send($post->post_author, 'payment_received', [
                            'item' => $post->post_title
                        ]);
                    }
                } elseif ($action === 'zls_cancel') {
                    update_post_meta($post_id, '_zls_status', 'cancelled');
                    if (class_exists('ZLS_Notifications')) {
                        $post = get_post($post_id);
                        ZLS_Notifications::send($post->post_author, 'request_cancelled', [
                            'item' => $post->post_title
                        ]);
                    }
                }
                
                if (class_exists('ZLS_Audit_Logger')) {
                    ZLS_Audit_Logger::log('BULK_ACTION', [
                        'post_id' => $post_id,
                        'action' => $action,
                        'admin_id' => get_current_user_id()
                    ]);
                }
            }
            
            wp_safe_redirect(add_query_arg('updated', count($post_ids), wp_get_referer()));
            exit;
        }
    }

    // FILTERS FOR LIST TABLES
    public static function filter_request_list($post_type) {
        global $typenow;
        if ($typenow !== $post_type) return;
        
        $status = isset($_GET['zls_status']) ? sanitize_text_field($_GET['zls_status']) : '';
        ?>
        <select name="zls_status">
            <option value="">All Statuses</option>
            <?php
            $labels = class_exists('ZLS_Request_Base') ? ZLS_Request_Base::get_status_labels() : [];
            foreach ($labels as $key => $label) {
                printf('<option value="%s" %s>%s</option>', 
                    esc_attr($key), 
                    selected($status, $key, false), 
                    esc_html($label)
                );
            }
            ?>
        </select>
        <?php
    }
    
    // HELPER METHODS
    private static function count_by_status($status) {
        $ship = new WP_Query(array('post_type' => 'zls_ship', 'fields' => 'ids', 'posts_per_page' => -1, 'meta_query' => array(array('key' => '_zls_status', 'value' => $status))));
        $buy = new WP_Query(array('post_type' => 'zls_buy', 'fields' => 'ids', 'posts_per_page' => -1, 'meta_query' => array(array('key' => '_zls_status', 'value' => $status))));
        return $ship->found_posts + $buy->found_posts;
    }
    
    private static function get_pending_kyc_count() {
        $users = get_users(array('meta_key' => '_zls_kyc_status', 'meta_value' => 'pending', 'fields' => 'ID'));
        return count($users);
    }
}

// Initialize
add_action('init', function() {
    if (class_exists('ZLS_Admin_UI')) {
        ZLS_Admin_UI::init();
    }
}, 20);

// Add filters to list tables
add_action('restrict_manage_posts', function() {
    if (class_exists('ZLS_Admin_UI')) {
        ZLS_Admin_UI::filter_request_list('zls_ship');
        ZLS_Admin_UI::filter_request_list('zls_buy');
    }
});

// Register bulk actions
add_filter('bulk_actions-edit-zls_ship', function($actions) {
    $actions['zls_mark_paid'] = 'Mark as Paid';
    $actions['zls_cancel'] = 'Cancel Request';
    return $actions;
});
add_filter('bulk_actions-edit-zls_buy', function($actions) {
    $actions['zls_mark_paid'] = 'Mark as Paid';
    $actions['zls_cancel'] = 'Cancel Request';
    return $actions;
});