<?php
/**
 * Zephora Logistic Systems - Error Handler
 * Comprehensive error handling and logging system
 */
class ZLS_Error_Handler {
    private static $log_file;
    private static $initialized = false;

    public static function init() {
        if (self::$initialized) return;
        self::$initialized = true;

        self::$log_file = WP_CONTENT_DIR . '/zls-logs/errors.log';

        // Create logs directory if it doesn't exist
        $log_dir = dirname(self::$log_file);
        if (!file_exists($log_dir)) {
            wp_mkdir_p($log_dir);
        }

        // Set up error handling
        set_error_handler(array(__CLASS__, 'handle_error'));
        set_exception_handler(array(__CLASS__, 'handle_exception'));

        // Add shutdown function to catch fatal errors
        register_shutdown_function(array(__CLASS__, 'handle_shutdown'));
    }

    /**
     * Handle PHP errors
     */
    public static function handle_error($errno, $errstr, $errfile, $errline) {
        $error_types = array(
            E_ERROR => 'E_ERROR',
            E_WARNING => 'E_WARNING',
            E_PARSE => 'E_PARSE',
            E_NOTICE => 'E_NOTICE',
            E_CORE_ERROR => 'E_CORE_ERROR',
            E_CORE_WARNING => 'E_CORE_WARNING',
            E_COMPILE_ERROR => 'E_COMPILE_ERROR',
            E_COMPILE_WARNING => 'E_COMPILE_WARNING',
            E_USER_ERROR => 'E_USER_ERROR',
            E_USER_WARNING => 'E_USER_WARNING',
            E_USER_NOTICE => 'E_USER_NOTICE',
            E_STRICT => 'E_STRICT',
            E_RECOVERABLE_ERROR => 'E_RECOVERABLE_ERROR',
            E_DEPRECATED => 'E_DEPRECATED',
            E_USER_DEPRECATED => 'E_USER_DEPRECATED',
        );

        $error_type = isset($error_types[$errno]) ? $error_types[$errno] : 'UNKNOWN';

        $error_data = array(
            'type' => 'php_error',
            'error_type' => $error_type,
            'message' => $errstr,
            'file' => $errfile,
            'line' => $errline,
            'trace' => debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS)
        );

        self::log_error($error_data);

        // Don't execute PHP's internal error handler for certain types
        if (!(error_reporting() & $errno)) {
            return false;
        }

        return true;
    }

    /**
     * Handle uncaught exceptions
     */
    public static function handle_exception($exception) {
        $error_data = array(
            'type' => 'uncaught_exception',
            'message' => $exception->getMessage(),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'trace' => $exception->getTraceAsString()
        );

        self::log_error($error_data);

        // Show user-friendly error page if not in admin
        if (!is_admin() && !wp_doing_ajax()) {
            wp_die(
                'Something went wrong. Please try again later or contact support.',
                'Error',
                array('response' => 500)
            );
        }
    }

    /**
     * Handle shutdown (catches fatal errors)
     */
    public static function handle_shutdown() {
        $error = error_get_last();
        if ($error !== null && in_array($error['type'], array(E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR))) {
            $error_data = array(
                'type' => 'fatal_error',
                'message' => $error['message'],
                'file' => $error['file'],
                'line' => $error['line'],
                'trace' => debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS)
            );

            self::log_error($error_data);
        }
    }

    /**
     * Log application errors
     */
    public static function log_app_error($message, $context = array(), $level = 'error') {
        $error_data = array(
            'type' => 'application_error',
            'level' => $level,
            'message' => $message,
            'context' => $context,
            'file' => isset($context['file']) ? $context['file'] : '',
            'line' => isset($context['line']) ? $context['line'] : '',
            'trace' => debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS)
        );

        self::log_error($error_data);
    }

    /**
     * Log to file
     */
    private static function log_error($error_data) {
        $timestamp = current_time('mysql');
        $user_id = get_current_user_id();
        $user_info = $user_id ? "User ID: {$user_id}" : 'Not logged in';

        $log_entry = sprintf(
            "[%s] %s | %s | %s\n",
            $timestamp,
            strtoupper($error_data['type']),
            $user_info,
            json_encode($error_data)
        );

        // Write to log file
        @error_log($log_entry, 3, self::$log_file);

        // Also log to WordPress debug log if enabled
        if (defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
            @error_log('[ZLS ERROR] ' . $error_data['message'], 3, WP_CONTENT_DIR . '/debug.log');
        }

        // Log critical errors to audit log as well
        if (in_array($error_data['type'], array('fatal_error', 'uncaught_exception')) && class_exists('ZLS_Audit_Logger')) {
            ZLS_Audit_Logger::log('CRITICAL_ERROR', array(
                'error_type' => $error_data['type'],
                'message' => $error_data['message'],
                'file' => $error_data['file'],
                'line' => $error_data['line']
            ));
        }
    }

    /**
     * Get recent errors (for admin debugging)
     */
    public static function get_recent_errors($limit = 50) {
        if (!file_exists(self::$log_file)) {
            return array();
        }

        $lines = file(self::$log_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $lines = array_slice($lines, -$limit);

        $errors = array();
        foreach ($lines as $line) {
            if (preg_match('/^\[([^\]]+)\] ([^|]+) \| ([^|]+) \| (.+)$/', $line, $matches)) {
                $errors[] = array(
                    'timestamp' => $matches[1],
                    'type' => $matches[2],
                    'user_info' => $matches[3],
                    'data' => json_decode($matches[4], true)
                );
            }
        }

        return array_reverse($errors);
    }

    /**
     * Clear error log
     */
    public static function clear_log() {
        if (file_exists(self::$log_file)) {
            @unlink(self::$log_file);
        }
    }
}