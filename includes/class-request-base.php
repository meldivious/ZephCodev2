<?php
abstract class ZLS_Request_Base {
    public static function handle_submission($post_type, $data) {
        if (!is_user_logged_in()) return new WP_Error('unauth', 'Login required');
        $user_id = get_current_user_id();
        
        if (ZLS_KYC_Manager::get_status($user_id) !== 'approved') {
            return new WP_Error('kyc_pending', 'Complete KYC verification first');
        }
        
        if (!ZLS_Security::check_rate_limit('request_submit', $user_id)) {
            return new WP_Error('rate_limit', 'Too many requests. Wait a moment.');
        }

        // ✅ Fix: Map CPT names to sanitizer expectations
        $type = (strpos($post_type, 'ship') !== false) ? 'ship_for_me' : 'buy_for_me';
        $sanitized = ZLS_Security::sanitize_request_data($data, $type);
        
        if (is_wp_error($sanitized)) return $sanitized;

        // ✅ Fix: Safe fallback for product_name
        $post_id = wp_insert_post([
            'post_title' => $sanitized['product_name'] ?? 'New Request #' . time(),
            'post_type'  => $post_type,
            'post_author'=> $user_id,
            'post_status'=> 'publish'
        ]);

        foreach ($sanitized as $key => $val) {
            update_post_meta($post_id, "_zls_{$key}", $val);
        }
        
        update_post_meta($post_id, '_zls_status', 'pending');
        update_post_meta($post_id, '_zls_created_at', current_time('mysql'));
        
        ZLS_Audit_Logger::log('REQUEST_CREATED', [
            'post_id' => $post_id,
            'type'    => $post_type,
            'user_id' => $user_id
        ]);
        
        return $post_id;
    }

    public static function get_status_labels() {
        return [
            'pending'       => 'Pending Review',
            'quote_sent'    => 'Quote Ready',
            'payment_pending'=> 'Awaiting Payment',
            'paid'          => 'Paid & Processing',
            'purchasing'    => 'Procuring Item',
            'received_us'   => 'Received in US',
            'shipped'       => 'Shipped to Lagos',
            'delivered'     => 'Delivered',
            'cancelled'     => 'Cancelled'
        ];
    }
}