<?php
/**
 * Zephora Logistic Systems - Payment Processing
 * Supports Manual Bank, Paystack, Flutterwave
 */
class ZLS_Payments {
    public static function init() {
        // Frontend payment triggers
        add_action('wp_ajax_zls_initiate_payment', array(__CLASS__, 'ajax_initiate_payment'));
        add_action('wp_ajax_nopriv_zls_initiate_payment', array(__CLASS__, 'ajax_initiate_payment'));
        
        // Webhooks
        add_action('wp_ajax_nopriv_zls_webhook_paystack', array(__CLASS__, 'webhook_paystack'));
        add_action('wp_ajax_nopriv_zls_webhook_flutterwave', array(__CLASS__, 'webhook_flutterwave'));
        
        // Manual payment confirmation
        add_action('wp_ajax_zls_confirm_manual_payment', array(__CLASS__, 'confirm_manual_payment'));
        add_action('wp_ajax_nopriv_zls_confirm_manual_payment', array(__CLASS__, 'confirm_manual_payment'));
    }

    public static function ajax_initiate_payment() {
        check_ajax_referer('zls_ajax', 'nonce');
        $request_id = absint($_POST['request_id'] ?? 0);
        $gateway = sanitize_text_field($_POST['gateway'] ?? 'paystack');
        $post = get_post($request_id);
        if (!$post || $post->post_author != get_current_user_id()) wp_send_json_error('Invalid request');
        
        $quote = floatval(get_post_meta($request_id, '_zls_quote_amount', true));
        if ($quote <= 0) wp_send_json_error('No quote set');
        
        if ($gateway === 'paystack') {
            $pub = get_option('zls_paystack_public_key');
            $amount_kobo = intval($quote * 100);
            $ref = 'ZLS_' . $request_id . '_' . time() . '_' . wp_generate_password(8, false);
            wp_send_json_success(array('gateway' => 'paystack', 'key' => $pub, 'amount' => $amount_kobo, 'ref' => $ref, 'email' => wp_get_current_user()->user_email));
        } elseif ($gateway === 'flutterwave') {
            $pub = get_option('zls_flutterwave_public_key');
            $amount = $quote;
            $ref = 'ZLS_' . $request_id . '_' . time() . '_' . wp_generate_password(8, false);
            wp_send_json_success(array('gateway' => 'flutterwave', 'key' => $pub, 'amount' => $amount, 'ref' => $ref, 'email' => wp_get_current_user()->user_email));
        } else {
            wp_send_json_error('Unsupported gateway');
        }
    }

    public static function webhook_paystack() {
        $input = file_get_contents('php://input');
        $signature = isset($_SERVER['HTTP_X_PAYSTACK_SIGNATURE']) ? $_SERVER['HTTP_X_PAYSTACK_SIGNATURE'] : '';
        $secret = get_option('zls_paystack_secret_key');
        if (!$secret || hash_hmac('sha512', $input, $secret) !== $signature) {
            status_header(400); wp_die('Invalid signature');
        }
        $data = json_decode($input, true);
        if ($data['event'] === 'charge.success') {
            $ref = $data['data']['reference'];
            $amount = $data['data']['amount'] / 100;
            if (preg_match('/^ZLS_(\d+)_/', $ref, $match)) {
                self::verify_and_complete(absint($match[1]), $amount, $ref, 'paystack');
            }
        }
        status_header(200); wp_die('OK');
    }

    public static function webhook_flutterwave() {
        $input = file_get_contents('php://input');
        $signature = isset($_SERVER['HTTP_VERIF_HASH']) ? $_SERVER['HTTP_VERIF_HASH'] : '';
        $secret = get_option('zls_flutterwave_secret_key');
        if (!$secret || $signature !== md5($input . $secret)) {
            status_header(400); wp_die('Invalid signature');
        }
        $data = json_decode($input, true);
        if (isset($data['status']) && $data['status'] === 'successful') {
            $tx_ref = $data['data']['tx_ref'];
            $amount = $data['data']['amount'];
            if (preg_match('/^ZLS_(\d+)_/', $tx_ref, $match)) {
                self::verify_and_complete(absint($match[1]), $amount, $tx_ref, 'flutterwave');
            }
        }
        status_header(200); wp_die('OK');
    }

    public static function verify_and_complete($request_id, $paid_amount, $ref, $gateway) {
        $quote = floatval(get_post_meta($request_id, '_zls_quote_amount', true));
        if (abs($paid_amount - $quote) > 1.00) return; // Tolerance
        update_post_meta($request_id, '_zls_status', 'paid');
        update_post_meta($request_id, '_zls_payment_ref', $ref);
        update_post_meta($request_id, '_zls_payment_gateway', $gateway);
        update_post_meta($request_id, '_zls_paid_at', current_time('mysql'));
        
        if (class_exists('ZLS_PDF')) ZLS_PDF::generate_invoice($request_id, 'save');
        if (class_exists('ZLS_Notifications')) ZLS_Notifications::send_status_update($request_id, 'paid');
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