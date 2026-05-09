<?php
/**
 * Zephora Logistic Systems - Settings Management
 * PHP 7.4+ | iOS-inspired | Secure
 */
class ZLS_Settings {
    private static $instance = null;
    
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public function init() {
        add_action('admin_menu', array($this, 'add_settings_page'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_assets'));
    }
    
    public function add_settings_page() {
        add_menu_page('Zephora Logistics', 'Zephora Logistics', 'manage_options', 'zephora-logistics', array($this, 'render_page'), 'dashicons-shipping', 30);
        add_submenu_page('zephora-logistics', 'Settings', 'Settings', 'manage_options', 'zephora-logistics', array($this, 'render_page'));
    }
    
    public function register_settings() {
        register_setting('zls_settings_group', 'zls_settings');
        register_setting('zls_settings_group', 'zls_warehouse_address');
        register_setting('zls_settings_group', 'zls_bank_details');
        register_setting('zls_settings_group', 'zls_email_templates');
    }
    
    public function enqueue_assets($hook) {
        if (strpos($hook, 'zephora-logistics') === false) return;
        wp_enqueue_style('zls-settings', ZLS_PLUGIN_URL . 'assets/css/admin.css', array(), ZLS_VERSION);
        wp_enqueue_script('zls-settings', ZLS_PLUGIN_URL . 'assets/js/settings.js', array('jquery'), ZLS_VERSION, true);
        wp_localize_script('zls-settings', 'zlsSettings', array('ajax_url' => admin_url('admin-ajax.php'), 'nonce' => wp_create_nonce('zls_settings_ajax')));
    }
    
    public function render_page() {
        $settings = get_option('zls_settings', array());
        $bank = get_option('zls_bank_details', array());
        $warehouse = get_option('zls_warehouse_address', array());
        $active_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'addresses';
        ?>
        <div class="wrap zls-settings-wrap">
            <h1>Zephora Logistics Settings</h1>
            <form method="post" action="options.php" id="zls-settings-form">
                <?php settings_fields('zls_settings_group'); ?>
                
                <nav class="zls-settings-tabs">
                    <button type="button" class="<?php echo $active_tab === 'addresses' ? 'active' : ''; ?>" data-tab="addresses">Warehouse Address</button>
                    <button type="button" class="<?php echo $active_tab === 'bank' ? 'active' : ''; ?>" data-tab="bank">Bank Details</button>
                    <button type="button" class="<?php echo $active_tab === 'email' ? 'active' : ''; ?>" data-tab="email">Email Templates</button>
                </nav>
                
                <div class="zls-settings-tab <?php echo $active_tab === 'addresses' ? 'active' : ''; ?>" id="tab-addresses" style="<?php echo $active_tab === 'addresses' ? '' : 'display:none;'; ?>">
                    <p class="description">Enter your warehouse address. This address will be displayed to customers on their dashboard.</p>
                    <div class="zls-warehouse-form" style="background:#f9f9f9;padding:20px;border-radius:8px;">
                        <h3>Warehouse Address</h3>
                        <p><label>Address Line 1<br><input type="text" name="zls_warehouse_address[address_line1]" value="<?php echo esc_attr($warehouse['address_line1'] ?? ''); ?>" class="regular-text" required></label></p>
                        <p><label>Address Line 2 (Optional)<br><input type="text" name="zls_warehouse_address[address_line2]" value="<?php echo esc_attr($warehouse['address_line2'] ?? ''); ?>" class="regular-text"></label></p>
                        <p><label>City<br><input type="text" name="zls_warehouse_address[city]" value="<?php echo esc_attr($warehouse['city'] ?? ''); ?>" class="regular-text" required></label></p>
                        <p><label>State<br><input type="text" name="zls_warehouse_address[state]" value="<?php echo esc_attr($warehouse['state'] ?? ''); ?>" class="regular-text" required></label></p>
                        <p><label>Postal Code<br><input type="text" name="zls_warehouse_address[postal_code]" value="<?php echo esc_attr($warehouse['postal_code'] ?? ''); ?>" class="regular-text" required></label></p>
                        <p><label>Country<br><input type="text" name="zls_warehouse_address[country]" value="<?php echo esc_attr($warehouse['country'] ?? 'USA'); ?>" class="regular-text"></label></p>
                        <p><label>Phone<br><input type="text" name="zls_warehouse_address[contact_phone]" value="<?php echo esc_attr($warehouse['contact_phone'] ?? ''); ?>" class="regular-text"></label></p>
                    </div>
                </div>
                
                <div class="zls-settings-tab <?php echo $active_tab === 'bank' ? 'active' : ''; ?>" id="tab-bank" style="<?php echo $active_tab === 'bank' ? '' : 'display:none;'; ?>">
                    <table class="form-table">
                        <tr><th><label>Bank Name</label></th><td><input type="text" name="zls_bank_details[bank_name]" value="<?php echo esc_attr($bank['bank_name'] ?? ''); ?>" class="regular-text"></td></tr>
                        <tr><th><label>Account Name</label></th><td><input type="text" name="zls_bank_details[account_name]" value="<?php echo esc_attr($bank['account_name'] ?? ''); ?>" class="regular-text"></td></tr>
                        <tr><th><label>Account Number</label></th><td><input type="text" name="zls_bank_details[account_number]" value="<?php echo esc_attr($bank['account_number'] ?? ''); ?>" class="regular-text"></td></tr>
                        <tr><th><label>SWIFT/BIC</label></th><td><input type="text" name="zls_bank_details[swift_code]" value="<?php echo esc_attr($bank['swift_code'] ?? ''); ?>" class="regular-text"></td></tr>
                        <tr><th><label>Payment Note Template</label></th><td><textarea name="zls_bank_details[note]" rows="3" class="large-text"><?php echo esc_textarea($bank['note'] ?? 'Reference: ZLS-{request_id}'); ?></textarea><p class="description">Use <code>{request_id}</code> as placeholder.</p></td></tr>
                    </table>
                </div>
                
                <div class="zls-settings-tab <?php echo $active_tab === 'email' ? 'active' : ''; ?>" id="tab-email" style="<?php echo $active_tab === 'email' ? '' : 'display:none;'; ?>">
                    <?php $this->render_email_templates(); ?>
                </div>
                
                <?php submit_button('Save Settings'); ?>
            </form>
        </div>
        <?php
    }
    
    public function render_email_templates() {
        $templates = get_option('zls_email_templates', array());
        $defaults = $this->get_default_email_templates();
        $admin_email = get_option('admin_email');
        ?>
        <div style="background:#f9f9f9;padding:20px;border-radius:8px;margin-bottom:20px;">
            <h3>Email Notification Templates</h3>
            <p class="description">Customize what gets sent to customers and admins for each event. Available variables: <code>{{customer_name}}</code>, <code>{{item}}</code>, <code>{{amount}}</code>, <code>{{tracking}}</code>, <code>{{status}}</code>, <code>{{request_date}}</code>, <code>{{admin_email}}</code></p>
        </div>
        
        <?php foreach ($defaults as $event => $default): ?>
        <div style="background:#fff;border:1px solid #e5e7eb;border-radius:8px;padding:20px;margin-bottom:20px;">
            <h4 style="margin-top:0;"><?php echo esc_html($default['label']); ?></h4>
            <p class="description"><?php echo esc_html($default['description']); ?></p>
            
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
        <?php
    }
    
    public function get_default_email_templates() {
        return array(
            'kyc_approved' => array(
                'label' => 'KYC Approved',
                'description' => 'Sent when admin approves user KYC verification',
                'recipient' => 'user',
                'subject' => 'KYC Approved - Welcome to Zephora Logistics!',
                'message' => 'Hi {{customer_name}},<br><br>Congratulations! Your KYC verification has been approved.<br><br>You now have full access to our SHIP FOR ME and BUY FOR ME services.<br><br>Login to your dashboard to get started: <a href="' . home_url('/my-dashboard') . '">My Dashboard</a><br><br>Thank you for choosing Zephora Logistics!<br><br>Best regards,<br>Zephora Logistics Team<br>{{admin_email}}'
            ),
            'kyc_denied' => array(
                'label' => 'KYC Denied',
                'description' => 'Sent when admin denies user KYC verification',
                'recipient' => 'user',
                'subject' => 'KYC Verification Update',
                'message' => 'Hi {{customer_name}},<br><br>Your KYC submission was reviewed and unfortunately denied.<br><br>Please review the KYC requirements and resubmit your verification documents.<br><br>If you have questions, please contact us at {{admin_email}}.<br><br>Best regards,<br>Zephora Logistics Team'
            ),
            'kyc_banned' => array(
                'label' => 'Account Suspended',
                'description' => 'Sent when admin bans/suspends a user account',
                'recipient' => 'user',
                'subject' => 'Account Suspension Notice',
                'message' => 'Hi {{customer_name}},<br><br>Your account has been suspended.<br><br>Please contact our support team at {{admin_email}} for assistance.<br><br>Best regards,<br>Zephora Logistics Team'
            ),
            'quote_sent' => array(
                'label' => 'Quote Ready Notification',
                'description' => 'Sent when admin sets a quote for the request',
                'recipient' => 'user',
                'subject' => 'Quote Ready: {{item}}',
                'message' => 'Hi {{customer_name}},<br><br>Your quote for <strong>{{item}}</strong> is ready!<br><br><strong>Amount: ₦{{amount}} + VAT</strong><br><br>Please login to your dashboard to review and confirm payment.<br><br>Thank you,<br>Zephora Logistics Team'
            ),
            'paid' => array(
                'label' => 'Payment Confirmed',
                'description' => 'Sent when admin confirms payment has been received',
                'recipient' => 'both',
                'subject' => 'Payment Confirmed - {{item}}',
                'message' => 'Hi {{customer_name}},<br><br>We have confirmed receipt of your payment (₦{{amount}}).<br><br>Your order is now being processed. We will update you shortly.<br><br><strong>Request ID:</strong> {{request_date}}<br><br>Thank you for your business!<br>Zephora Logistics Team'
            ),
            'purchasing' => array(
                'label' => 'Purchasing in Progress',
                'description' => 'Sent when admin starts purchasing the item',
                'recipient' => 'user',
                'subject' => 'We are Purchasing Your Item - {{item}}',
                'message' => 'Hi {{customer_name}},<br><br>Great news! We are now purchasing your item.<br><br><strong>Item:</strong> {{item}}<br><br>We will notify you once it arrives at our warehouse.<br><br>Best regards,<br>Zephora Logistics Team'
            ),
            'received_us' => array(
                'label' => 'Received at US Warehouse',
                'description' => 'Sent when item arrives at US warehouse',
                'recipient' => 'user',
                'subject' => 'Item Received at US Warehouse - {{item}}',
                'message' => 'Hi {{customer_name}},<br><br>Your item has been received at our US warehouse.<br><br><strong>Item:</strong> {{item}}<br><br>We will prepare it for shipment to Nigeria shortly.<br><br>Best regards,<br>Zephora Logistics Team'
            ),
            'shipped' => array(
                'label' => 'Package Shipped',
                'description' => 'Sent when package leaves the warehouse',
                'recipient' => 'user',
                'subject' => 'Your Package is On the Way - {{item}}',
                'message' => 'Hi {{customer_name}},<br><br>Great news! Your package is on the way to Nigeria.<br><br><strong>Tracking Number:</strong> {{tracking}}<br><strong>Item:</strong> {{item}}<br><br>You can track your shipment using the tracking number. We will notify you once it arrives in Lagos.<br><br>Best regards,<br>Zephora Logistics Team'
            ),
            'delivered' => array(
                'label' => 'Delivery Complete',
                'description' => 'Sent when package is delivered to customer',
                'recipient' => 'user',
                'subject' => 'Your Package Has Arrived! - {{item}}',
                'message' => 'Hi {{customer_name}},<br><br>Your package has been successfully delivered!<br><br><strong>Item:</strong> {{item}}<br><strong>Tracking:</strong> {{tracking}}<br><br>Thank you for choosing Zephora Logistics. We hope you enjoy your purchase!<br><br>If you have any questions, please contact us at {{admin_email}}.<br><br>Best regards,<br>Zephora Logistics Team'
            ),
            'cancelled' => array(
                'label' => 'Request Cancelled',
                'description' => 'Sent when a request is cancelled by admin or customer',
                'recipient' => 'both',
                'subject' => 'Request Cancelled - {{item}}',
                'message' => 'Hi {{customer_name}},<br><br>Your request has been cancelled.<br><br><strong>Item:</strong> {{item}}<br><strong>Request Date:</strong> {{request_date}}<br><br>If you have any questions about this cancellation or need assistance, please contact us at {{admin_email}}.<br><br>Best regards,<br>Zephora Logistics Team'
            )
        );
    }
}