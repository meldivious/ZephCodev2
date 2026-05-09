<?php
class ZLS_Audit_Logger {
    public static function init() {
        // 🔐 Security: Protect log file via .htaccess
        $htaccess = ZLS_PLUGIN_DIR . '.htaccess';
        if (!file_exists($htaccess)) {
            $rules = "<FilesMatch \"\.(log|ini|bak|sql|sh)$\">\n    Order Deny,Allow\n    Deny from all\n</FilesMatch>\n\n<Files \"zls-audit.log\">\n    Order Deny,Allow\n    Deny from all\n</Files>";
            file_put_contents($htaccess, $rules);
        }
    }

    public static function log($event, $context = array()) {
        $log_entry = array(
            'timestamp' => current_time('mysql'),
            'event' => $event,
            'context' => $context,
            'server' => array(
                'ip' => isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : 'cli',
                'user_agent' => isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '',
                'request_uri' => isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : ''
            )
        );
        
        $log_line = json_encode($log_entry) . "\n";
        @error_log($log_line, 3, ZLS_AUDIT_LOG);
        
        if (defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
            @error_log('[ZLS AUDIT] ' . $event . ': ' . json_encode($context));
        }
    }
}