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
        // No menu registration here - handled by ZLS_Admin_UI
        add_action('admin_init', array($this, 'register_settings'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_assets'));
    }
    
    public function register_settings() {
        register_setting('zls_settings_group', 'zls_settings');
        register_setting('zls_settings_group', 'zls_warehouse_address');
        register_setting('zls_settings_group', 'zls_bank_details');
        register_setting('zls_settings_group', 'zls_email_templates');
    }
    
    public function enqueue_assets($hook) {
        if (strpos($hook, 'zls-') === false) return;
        wp_enqueue_style('zls-settings', ZLS_PLUGIN_URL . 'assets/css/admin.css', array(), ZLS_VERSION);
        wp_enqueue_script('zls-settings', ZLS_PLUGIN_URL . 'assets/js/settings.js', array('jquery'), ZLS_VERSION, true);
        wp_localize_script('zls-settings', 'zlsSettings', array('ajax_url' => admin_url('admin-ajax.php'), 'nonce' => wp_create_nonce('zls_settings_ajax')));
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