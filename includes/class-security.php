<?php
/**
 * Zephora Logistic Systems - Security Utilities
 * PHP 7.4+ Compatible | No arrow functions | No array defaults
 */
class ZLS_Security {
    
    public static function init() {
        add_action('init', array(__CLASS__, 'enforce_security_headers'));
        add_filter('wp_kses_allowed_html', array(__CLASS__, 'allow_safe_html'), 10, 2);
    }

    /**
     * Add security headers to frontend responses
     */
    public static function enforce_security_headers() {
        if (is_admin() || headers_sent()) {
            return;
        }
        
        // Safe headers for PHP 7.4 + WordPress
        if (!headers_sent()) {
            header("X-Content-Type-Options: nosniff");
            header("X-Frame-Options: SAMEORIGIN");
            header("Referrer-Policy: strict-origin-when-cross-origin");
        }
    }

    /**
     * Allow safe HTML in notes/descriptions
     */
    public static function allow_safe_html($allowed, $context) {
        if ($context === 'zls_notes') {
            $extra = array(
                'br' => array(),
                'p' => array(),
                'strong' => array(),
                'em' => array(),
                'ul' => array(),
                'ol' => array(),
                'li' => array()
            );
            return array_merge($allowed, $extra);
        }
        return $allowed;
    }

    /**
     * Rate limiting: 5 submissions per minute per user
     * PHP 7.4 safe: no arrow functions
     */
    public static function check_rate_limit($action, $user_id, $limit = 5, $window = 60) {
        $key = 'zls_rate_' . md5($action . '_' . $user_id);
        $count = (int) get_transient($key);
        
        if ($count >= $limit) {
            $log_data = array(
                'action' => $action,
                'user_id' => $user_id,
                'ip' => isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : 'cli'
            );
            if (class_exists('ZLS_Audit_Logger')) {
                ZLS_Audit_Logger::log('RATE_LIMIT_EXCEEDED', $log_data);
            }
            return false;
        }
        
        set_transient($key, $count + 1, $window);
        return true;
    }

    /**
     * Centralized input sanitization
     * PHP 7.4 safe: no array defaults, no arrow functions, explicit null checks
     */
    public static function sanitize_request_data($data, $type) {
        // Ensure $data is array
        if (!is_array($data)) {
            $data = array();
        }
        
        $sanitized = array();
        
        // Common address fields (used by both types)
        $address_fields = array(
            'recipient_name' => 'sanitize_text_field',
            'recipient_phone' => array(__CLASS__, 'sanitize_phone'),
            'recipient_email' => 'sanitize_email',
            'address_line1' => 'sanitize_text_field',
            'city' => 'sanitize_text_field',
            'state' => 'sanitize_text_field',
            'postal_code' => 'sanitize_text_field',
            'landmark' => 'sanitize_text_field',
        );
        
        if ($type === 'ship_for_me') {
            $fields = array(
                'product_name' => 'sanitize_text_field',
                'tracking_number' => 'sanitize_text_field',
                'product_url' => 'esc_url_raw',
                'packages' => 'absint',
                'weight_kg' => 'floatval',
                'value_usd' => 'floatval',
                'content_desc' => 'sanitize_textarea_field',
            );
            $fields = array_merge($fields, $address_fields);
            
            foreach ($fields as $field => $callback) {
                $raw = isset($data[$field]) ? $data[$field] : '';
                $sanitized[$field] = self::apply_sanitization($raw, $callback);
            }
            
            // Required field validation
            if (empty($sanitized['product_name']) || empty($sanitized['content_desc']) || empty($sanitized['recipient_name'])) {
                return new WP_Error('missing_required', 'Required fields missing');
            }
            
        } elseif ($type === 'buy_for_me') {
            $fields = array(
                'product_name' => 'sanitize_text_field',
                'product_url' => 'esc_url_raw',
                'specs' => 'sanitize_textarea_field',
                'budget_usd' => 'floatval',
                'quantity' => 'absint',
                'preferred_store' => 'sanitize_text_field',
                'content_desc' => 'sanitize_textarea_field',
            );
            $fields = array_merge($fields, $address_fields);
            
            foreach ($fields as $field => $callback) {
                $raw = isset($data[$field]) ? $data[$field] : '';
                $sanitized[$field] = self::apply_sanitization($raw, $callback);
            }
            
            // Required field validation
            if (empty($sanitized['product_name']) || empty($sanitized['content_desc'])) {
                return new WP_Error('missing_required', 'Required fields missing');
            }
        }
        
        return $sanitized;
    }
    
    /**
     * Helper: Apply sanitization callback safely
     */
    private static function apply_sanitization($value, $callback) {
        if (is_string($callback) && function_exists($callback)) {
            return call_user_func($callback, $value);
        } elseif (is_array($callback) && is_callable($callback)) {
            return call_user_func($callback, $value);
        }
        // Fallback: return sanitized string
        return sanitize_text_field($value);
    }
    
    /**
     * Sanitize phone number: keep only digits, +, -, spaces
     */
    public static function sanitize_phone($phone) {
        if (!is_string($phone)) {
            return '';
        }
        return preg_replace('/[^0-9+\-\s]/', '', $phone);
    }
    
    /**
     * Verify nonce with additional timestamp check (anti-replay)
     */
    public static function verify_nonce_with_ttl($nonce, $action, $ttl = 3600) {
        if (!wp_verify_nonce($nonce, $action)) {
            return false;
        }
        
        // Optional: check nonce timestamp if stored with meta
        $timestamp = (int) get_transient('zls_nonce_' . md5($nonce));
        if ($timestamp && (time() - $timestamp) > $ttl) {
            return false;
        }
        
        // Store timestamp for future checks
        set_transient('zls_nonce_' . md5($nonce), time(), $ttl);
        
        return true;
    }
    
    /**
     * Capability check wrapper with audit logging
     */
    public static function check_cap($cap, $object_id = null, $log_failure = true) {
        $has_cap = $object_id 
            ? current_user_can($cap, $object_id) 
            : current_user_can($cap);
            
        if (!$has_cap && $log_failure && class_exists('ZLS_Audit_Logger')) {
            ZLS_Audit_Logger::log('CAPABILITY_DENIED', array(
                'cap' => $cap,
                'object_id' => $object_id,
                'user_id' => get_current_user_id(),
                'ip' => isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : 'cli'
            ));
        }
        
        return $has_cap;
    }
}