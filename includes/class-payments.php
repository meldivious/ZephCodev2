<?php
/**
 * Zephora Logistic Systems - Payment Processing
 * Manual Bank Transfer Only - No Gateway Integration
 */
class ZLS_Payments {
    public static function init() {
        // Manual payment confirmation only
        add_action('wp_ajax_zls_confirm_manual_payment', array(__CLASS__, 'confirm_manual_payment'));
        add_action('wp_ajax_nopriv_zls_confirm_manual_payment', array(__CLASS__, 'confirm_manual_payment'));
    }

    public static function confirm_manual_payment() {
        check_ajax_referer('zls_ajax', 'nonce');
        $request_id = absint($_POST['request_id'] ?? 0);
        $post = get_post($request_id);
        if (!$post || $post->post_author != get_current_user_id()) wp_send_json_error('Unauthorized');
        
        update_post_meta($request_id, '_zls_status', 'payment_pending');
        update_post_meta($request_id, '_zls_payment_method', 'bank_transfer');
        update_post_meta($request_id, '_zls_payment_confirmed_at', current_time('mysql'));
        wp_send_json_success();
    }
}