<?php
/**
 * Zephora Logistic Systems - KYC Manager
 * Admin UI: View, Approve, Deny, Ban, Reinstate users
 * PHP 7.4+ Compatible | Nonce Secure | Email Notifications
 */
class ZLS_KYC_Manager {
    
    const STATUS_PENDING = 'pending';
    const STATUS_APPROVED = 'approved';
    const STATUS_DENIED = 'denied';
    const STATUS_BANNED = 'banned';
    const MAX_KYC_ATTEMPTS = 3; // Maximum number of KYC submissions allowed
    
    public static function init() {
        add_action('user_register', array(__CLASS__, 'set_default_kyc'));
        add_action('show_user_profile', array(__CLASS__, 'admin_kyc_ui'));
        add_action('edit_user_profile', array(__CLASS__, 'admin_kyc_ui'));
        add_action('personal_options_update', array(__CLASS__, 'save_kyc_admin'));
        add_action('edit_user_profile_update', array(__CLASS__, 'save_kyc_admin'));
        
        // ✅ Register KYC Review submenu (hooked early)
        add_action('admin_menu', array(__CLASS__, 'add_kyc_review_page'), 20);
        
        // ✅ Add column to Users list
        add_filter('manage_users_columns', array(__CLASS__, 'add_kyc_column'));
        add_filter('manage_users_custom_column', array(__CLASS__, 'render_kyc_column'), 10, 3);
        
        // ✅ Handle direct action links (Approve/Deny/etc)
        add_action('admin_init', array(__CLASS__, 'handle_kyc_actions'));
        
        // ✅ NEW: Handle secure file downloads for admins
        add_action('admin_init', array(__CLASS__, 'handle_kyc_file_download'));
    }
    
    /**
     * NEW: Secure File Download Handler
     * Allows admins to download KYC files bypassing .htaccess restrictions
     */
    public static function handle_kyc_file_download() {
        if (isset($_GET['action']) && $_GET['action'] === 'zls_download_kyc_file') {
            if (!current_user_can('manage_options')) wp_die('Unauthorized access.');
            
            $user_id = absint($_GET['user_id']);
            $filename = sanitize_file_name($_GET['file']);
            $nonce = $_GET['_wpnonce'];
            
            if (!wp_verify_nonce($nonce, 'zls_kyc_download_' . $user_id)) wp_die('Security check failed.');
            
            $upload_dir = wp_upload_dir();
            $filepath = $upload_dir['basedir'] . '/zls-kyc-documents/' . $user_id . '/' . $filename;
            
            if (file_exists($filepath)) {
                header('Content-Description: File Transfer');
                header('Content-Type: application/octet-stream');
                header('Content-Disposition: attachment; filename="' . basename($filepath) . '"');
                header('Expires: 0');
                header('Cache-Control: must-revalidate');
                header('Pragma: public');
                header('Content-Length: ' . filesize($filepath));
                readfile($filepath);
                exit;
            } else {
                wp_die('File not found.');
            }
        }
    }

    /**
     * NEW: Get KYC submission attempts
     */
    public static function get_attempts($user_id) {
        return (int) get_user_meta($user_id, '_zls_kyc_attempts', true);
    }

    public static function set_default_kyc($user_id) {
        update_user_meta($user_id, '_zls_kyc_status', self::STATUS_PENDING);
        update_user_meta($user_id, '_zls_kyc_data', array());
        update_user_meta($user_id, '_zls_kyc_attempts', 0); // Initialize attempts counter
    }
    
    public static function get_status($user_id) {
        return get_user_meta($user_id, '_zls_kyc_status', true) ?: self::STATUS_PENDING;
    }
    
    public static function is_approved($user_id) {
        return self::get_status($user_id) === self::STATUS_APPROVED;
    }
    
    // ✅ Add KYC column to Users list
    public static function add_kyc_column($cols) {
        $cols['zls_kyc'] = 'Zephora KYC';
        return $cols;
    }
    
    public static function render_kyc_column($val, $col, $user_id) {
        if ($col !== 'zls_kyc') return $val;
        $status = self::get_status($user_id);
        $badge = array(
            'pending' => '<span style="background:#fff3cd;color:#856404;padding:3px 8px;border-radius:4px;font-size:11px">Pending</span>',
            'approved' => '<span style="background:#d4edda;color:#155724;padding:3px 8px;border-radius:4px;font-size:11px">Approved</span>',
            'denied' => '<span style="background:#f8d7da;color:#721c24;padding:3px 8px;border-radius:4px;font-size:11px">Denied</span>',
            'banned' => '<span style="background:#343a40;color:#fff;padding:3px 8px;border-radius:4px;font-size:11px">Banned</span>',
        );
        return isset($badge[$status]) ? $badge[$status] : esc_html($status);
    }
    
    // ✅ Register KYC Review Page (submenu)
    public static function add_kyc_review_page() {
        // Parent slug must match the main menu slug from class-settings.php
        add_submenu_page(
            'zephora-logistics',           // Parent slug (must match add_menu_page slug)
            'KYC Review',                  // Page title
            'KYC Review',                  // Menu title
            'manage_options',              // Capability
            'zls-kyc-review',              // Menu slug
            array(__CLASS__, 'render_kyc_review_page') // Callback
        );
    }
    
    // ✅ Handle direct action links (Approve/Deny/Ban/Reinstate)
    public static function handle_kyc_actions() {
        if (!isset($_GET['page'], $_GET['action'], $_GET['user_id'], $_GET['_wpnonce'])) {
            return;
        }
        if ($_GET['page'] !== 'zls-kyc-review') return;
        if (!wp_verify_nonce($_GET['_wpnonce'], 'zls_kyc_action')) return;
        if (!current_user_can('manage_options')) return;
        
        $user_id = absint($_GET['user_id']);
        $action = sanitize_text_field($_GET['action']);
        $valid_actions = array('approve', 'deny', 'ban', 'reinstate');
        if (!in_array($action, $valid_actions)) return;
        
        $old_status = self::get_status($user_id);
        $new_status = array(
            'approve' => self::STATUS_APPROVED,
            'deny' => self::STATUS_DENIED,
            'ban' => self::STATUS_BANNED,
            'reinstate' => self::STATUS_PENDING,
        );
        
        update_user_meta($user_id, '_zls_kyc_status', $new_status[$action]);
        
        // ✅ NEW: Increment attempts on Deny action
        if ($action === 'deny') {
            $attempts = self::get_attempts($user_id);
            update_user_meta($user_id, '_zls_kyc_attempts', $attempts + 1);
        }
        
        // Send email notification
        self::send_kyc_email($user_id, $new_status[$action], $old_status);
        
        // Redirect back to review page with success message
        wp_safe_redirect(add_query_arg('kyc_updated', '1', wp_get_referer()));
        exit;
    }
    
    // ✅ Send email notification on status change
    private static function send_kyc_email($user_id, $new_status, $old_status) {
        if ($new_status === $old_status) return;
        $user = get_userdata($user_id);
        if (!$user) return;
        
        // Get email templates
        $templates = get_option('zls_email_templates', array());
        
        // Map status to template key
        $template_key = '';
        if ($new_status === self::STATUS_APPROVED) {
            $template_key = 'kyc_approved';
        } elseif ($new_status === self::STATUS_DENIED) {
            $template_key = 'kyc_denied';
        } elseif ($new_status === self::STATUS_BANNED) {
            $template_key = 'kyc_banned';
        }
        
        // If we have a template for this event, use it
        if (!empty($template_key) && !empty($templates[$template_key])) {
            $template = $templates[$template_key];
            
            // Replace variables
            $data = array(
                '{{customer_name}}' => $user->display_name,
                '{{admin_email}}' => get_option('admin_email')
            );
            
            $subject = strtr($template['subject'] ?? '', $data);
            $message = strtr($template['message'] ?? '', $data);
            
            // Send to user
            if (empty($template['recipient']) || $template['recipient'] === 'user') {
                ZLS_Notifications::send_email($user->user_email, $subject, $message);
            }
            
            // Send to admin if configured
            if ($template['recipient'] === 'both' || $template['recipient'] === 'admin') {
                ZLS_Notifications::send_email(get_option('admin_email'), '🔔 [Admin] ' . $subject, $message);
            }
        } else {
            // Fallback to default email if no template
            $subject = 'Zephora KYC Update: ' . ucfirst($new_status);
            $message = "Hello {$user->display_name},\n\n";
            $message .= "Your KYC status has been updated to: " . ucfirst($new_status) . ".\n\n";
            
            if ($new_status === self::STATUS_APPROVED) {
                $message .= "✅ You can now access SHIP FOR ME and BUY FOR ME services.\n";
                $message .= "Log in to your dashboard to get started: " . home_url('/my-dashboard') . "\n";
            } elseif ($new_status === self::STATUS_DENIED) {
                $message .= "❌ Your KYC submission was denied. Please review requirements and resubmit.\n";
            } elseif ($new_status === self::STATUS_BANNED) {
                $message .= "🚫 Your account has been suspended. Contact support for assistance.\n";
            } elseif ($new_status === self::STATUS_PENDING) {
                $message .= "🔄 Your account has been reinstated. Please complete KYC verification.\n";
            }
            
            $message .= "\nThank you,\nZephora Logistics Team";
            
            wp_mail($user->user_email, $subject, $message);
        }
    }
    
    // ✅ Render KYC Review Page
    public static function render_kyc_review_page() {
        // Show success notice if action completed
        if (isset($_GET['kyc_updated']) && $_GET['kyc_updated'] === '1') {
            echo '<div class="notice notice-success is-dismissible"><p>✅ KYC status updated successfully.</p></div>';
        }
        
        $status_filter = isset($_GET['kyc_status']) ? sanitize_text_field($_GET['kyc_status']) : 'pending';
        $users = get_users(array(
            'meta_key' => '_zls_kyc_status',
            'meta_value' => $status_filter,
            'orderby' => 'registered',
            'order' => 'DESC',
            'number' => 50
        ));
        ?>
        <div class="wrap">
            <h1>KYC Review</h1>
            <p>Review user KYC submissions and manage access.</p>
            
            <!-- Status Filter Tabs -->
            <div style="margin:15px 0;display:flex;gap:8px;flex-wrap:wrap">
                <a href="?page=zls-kyc-review&kyc_status=pending" class="button <?php echo $status_filter==='pending'?'button-primary':''; ?>">Pending (<?php echo count(get_users(array('meta_key'=>'_zls_kyc_status','meta_value'=>'pending','fields'=>'ID'))); ?>)</a>
                <a href="?page=zls-kyc-review&kyc_status=approved" class="button <?php echo $status_filter==='approved'?'button-primary':''; ?>">Approved</a>
                <a href="?page=zls-kyc-review&kyc_status=denied" class="button <?php echo $status_filter==='denied'?'button-primary':''; ?>">Denied</a>
                <a href="?page=zls-kyc-review&kyc_status=banned" class="button <?php echo $status_filter==='banned'?'button-primary':''; ?>">Banned</a>
            </div>
            
            <?php if (empty($users)): ?>
                <div class="notice notice-info"><p>No users with status "<?php echo esc_html($status_filter); ?>".</p></div>
            <?php else: ?>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th>User</th>
                            <th>Email</th>
                            <th>Registered</th>
                            <th>NIN</th>
                            <th>Phone</th>
                            <th>Address</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($users as $user): 
                        $kyc = get_user_meta($user->ID, '_zls_kyc_data', true) ?: array();
                        $note = get_user_meta($user->ID, '_zls_kyc_note', true);
                    ?>
                    <tr>
                        <td>
                            <strong><?php echo esc_html($user->display_name); ?></strong><br>
                            <small>ID: #<?php echo absint($user->ID); ?></small>
                        </td>
                        <td><?php echo esc_html($user->user_email); ?></td>
                        <td><?php echo date('M j, Y', strtotime($user->user_registered)); ?></td>
                        <td><?php echo esc_html($kyc['nin'] ?? '-'); ?></td>
                        <td><?php echo esc_html($kyc['phone'] ?? '-'); ?></td>
                        <td><?php echo esc_html(wp_trim_words($kyc['address'] ?? '-', 10)); ?></td>
                        <td>
                            <a href="<?php echo admin_url('user-edit.php?user_id='.$user->ID); ?>" class="button button-small">Edit</a>
                            <?php if ($status_filter === 'pending'): ?>
                                <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=zls-kyc-review&action=approve&user_id='.$user->ID), 'zls_kyc_action'); ?>" class="button button-small button-primary" onclick="return confirm('Approve this user?')">✅ Approve</a>
                                <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=zls-kyc-review&action=deny&user_id='.$user->ID), 'zls_kyc_action'); ?>" class="button button-small" onclick="return confirm('Deny this user?')">❌ Deny</a>
                                <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=zls-kyc-review&action=ban&user_id='.$user->ID), 'zls_kyc_action'); ?>" class="button button-small" style="background:#dc3545;color:#fff" onclick="return confirm('Ban this user?')">🚫 Ban</a>
                            <?php elseif ($status_filter === 'denied' || $status_filter === 'banned'): ?>
                                <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=zls-kyc-review&action=reinstate&user_id='.$user->ID), 'zls_kyc_action'); ?>" class="button button-small button-secondary" onclick="return confirm('Reinstate this user?')">🔄 Reinstate</a>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
        <?php
    }
    
    // ✅ KYC UI in User Profile Edit Screen (UPDATED FOR USABILITY)
    public static function admin_kyc_ui($user) {
        if (!current_user_can('manage_options')) return;
        $status = self::get_status($user->ID);
        $kyc = get_user_meta($user->ID, '_zls_kyc_data', true) ?: array();
        $note = get_user_meta($user->ID, '_zls_kyc_note', true);
        ?>
        <h3>🔐 Zephora KYC Verification</h3>
        <table class="form-table">
            <tr>
                <th>KYC Status</th>
                <td>
                    <select name="zls_kyc_status">
                        <option value="pending" <?php selected($status, 'pending'); ?>>Pending</option>
                        <option value="approved" <?php selected($status, 'approved'); ?>>✅ Approved</option>
                        <option value="denied" <?php selected($status, 'denied'); ?>>❌ Denied</option>
                        <option value="banned" <?php selected($status, 'banned'); ?>>🚫 Banned</option>
                    </select>
                    <p class="description">Banned users cannot submit requests. Denied users can resubmit KYC.</p>
                </td>
            </tr>
            <tr>
                <th>Submitted KYC Data</th>
                <td>
                    <?php if (!empty($kyc) && is_array($kyc)): ?>
                        <div style="background:#f9f9f9;padding:12px;border-radius:6px;border:1px solid #ddd;margin-bottom:10px;">
                            <p style="margin:0 0 8px;"><strong>📅 Submitted:</strong> <?php echo isset($kyc['submitted_at']) ? date('F j, Y \a\t g:i a', strtotime($kyc['submitted_at'])) : 'N/A'; ?></p>
                            
                            <?php if (isset($kyc['gov_id_file'])): ?>
                                <p style="margin:0 0 4px;">
                                    <strong>🆔 Government ID:</strong> <?php echo esc_html($kyc['gov_id_file']); ?>
                                    <a href="<?php echo wp_nonce_url(admin_url('?action=zls_download_kyc_file&user_id='.$user->ID.'&file='.urlencode($kyc['gov_id_file'])), 'zls_kyc_download_'.$user->ID); ?>" class="button button-small" style="margin-left:8px;">⬇️ Download</a>
                                </p>
                            <?php endif; ?>
                            
                            <?php if (isset($kyc['proof_address_file'])): ?>
                                <p style="margin:0;">
                                    <strong>📄 Proof of Address:</strong> <?php echo esc_html($kyc['proof_address_file']); ?>
                                    <a href="<?php echo wp_nonce_url(admin_url('?action=zls_download_kyc_file&user_id='.$user->ID.'&file='.urlencode($kyc['proof_address_file'])), 'zls_kyc_download_'.$user->ID); ?>" class="button button-small" style="margin-left:8px;">⬇️ Download</a>
                                </p>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <span style="color:#999;">No documents submitted yet.</span>
                    <?php endif; ?>
                </td>
            </tr>
            <tr>
                <th>Admin Note</th>
                <td>
                    <textarea name="zls_kyc_note" rows="3" class="large-text" placeholder="Optional: Reason for denial/ban or approval notes"><?php echo esc_textarea($note); ?></textarea>
                </td>
            </tr>
            <!-- ✅ NONCE FIELD -->
            <tr>
                <td colspan="2">
                    <?php wp_nonce_field('zls_kyc_admin', '_zls_kyc_nonce'); ?>
                </td>
            </tr>
        </table>
        <?php
    }
    
    // ✅ Save KYC status from user profile edit
    public static function save_kyc_admin($user_id) {
        if (!current_user_can('manage_options')) return;
        if (!isset($_POST['_zls_kyc_nonce']) || !wp_verify_nonce($_POST['_zls_kyc_nonce'], 'zls_kyc_admin')) return;
        
        $old_status = self::get_status($user_id);
        $new_status = sanitize_text_field($_POST['zls_kyc_status'] ?? self::STATUS_PENDING);
        $note = sanitize_textarea_field($_POST['zls_kyc_note'] ?? '');
        
        update_user_meta($user_id, '_zls_kyc_status', $new_status);
        update_user_meta($user_id, '_zls_kyc_note', $note);
        
        // Send email if status changed
        if ($old_status !== $new_status) {
            self::send_kyc_email($user_id, $new_status, $old_status);
        }

        // ✅ NEW: Increment attempts if status changed to Denied
        if ($new_status === self::STATUS_DENIED && $old_status !== self::STATUS_DENIED) {
            $attempts = self::get_attempts($user_id);
            update_user_meta($user_id, '_zls_kyc_attempts', $attempts + 1);
        }
    }
}