<?php
/**
 * Zephora Logistic Systems - Notifications (Email Only)
 * Email notifications for status changes and KYC events
 */
class ZLS_Notifications {
    public static function init() {
        add_action('save_post_zls_ship', array(__CLASS__, 'on_status_change'), 10, 3);
        add_action('save_post_zls_buy', array(__CLASS__, 'on_status_change'), 10, 3);
    }
    
    public static function on_status_change($post_id, $post, $update) {
        if (!$update || defined('DOING_AUTOSAVE')) return;
        $new = sanitize_text_field($_POST['zls_status'] ?? get_post_meta($post_id, '_zls_status', true));
        $old = get_post_meta($post_id, '_zls_status', true);
        if ($new === $old) return;
        
        $user = get_userdata($post->post_author);
        if (!$user) return;
        
        // Send notification if template exists for this event
        self::send_notification($post_id, $new, $user, $post);
    }
    
    public static function send_notification($post_id, $event, $user, $post) {
        $templates = get_option('zls_email_templates', array());
        
        // If no template for this event, do nothing
        if (empty($templates[$event])) return;
        
        $template = $templates[$event];
        
        // Get data for variable replacement
        $data = array(
            '{{customer_name}}' => $user->display_name,
            '{{item}}' => $post->post_title,
            '{{amount}}' => self::format_amount(get_post_meta($post_id, '_zls_quote_amount', true)),
            '{{tracking}}' => get_post_meta($post_id, '_zls_tracking_number', true) ?: 'Pending',
            '{{status}}' => ucfirst(str_replace('_', ' ', $event)),
            '{{request_date}}' => $post->post_date,
            '{{admin_email}}' => get_option('admin_email')
        );
        
        // Replace variables in subject and message
        $subject = strtr($template['subject'] ?? '', $data);
        $message = strtr($template['message'] ?? '', $data);
        
        // Determine recipients
        $recipient = $template['recipient'] ?? 'user';
        $admin_email = get_option('admin_email');
        
        if ($recipient === 'user' || $recipient === 'both') {
            self::send_email($user->user_email, $subject, $message);
        }
        
        if ($recipient === 'admin' || $recipient === 'both') {
            self::send_email($admin_email, '🔔 [Admin] ' . $subject, $message);
        }
    }
    
    private static function format_amount($amount) {
        if (!$amount) return '—';
        $num = floatval($amount);
        return '₦' . number_format($num, 0);
    }

    public static function send_email($to, $subject, $message) {
        $headers = array('Content-Type: text/html; charset=UTF-8');
        wp_mail($to, $subject, wpautop($message), $headers);
    }
}
