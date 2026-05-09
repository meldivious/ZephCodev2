<?php
/**
 * Zephora Logistic Systems - Bulk Operations
 * Admin bulk actions for managing multiple requests
 */
class ZLS_Bulk_Operations {
    public static function init() {
        add_action('load-edit.php', array(__CLASS__, 'handle_bulk_actions'));
        add_action('admin_footer-edit.php', array(__CLASS__, 'render_bulk_actions'));
    }

    /**
     * Render custom bulk actions
     */
    public static function render_bulk_actions() {
        global $typenow;
        
        if (!in_array($typenow, array('zls_ship', 'zls_buy'))) {
            return;
        }
        ?>
        <script type="text/javascript">
        (function($) {
            $(document).ready(function() {
                // Add custom bulk actions
                $('<option>').val('zls_bulk_approve_quote').text('Approve & Send Quote').appendTo("select[name='action']");
                $('<option>').val('zls_bulk_approve_quote').text('Approve & Send Quote').appendTo("select[name='action2']");
                
                $('<option>').val('zls_bulk_mark_paid').text('Mark as Paid').appendTo("select[name='action']");
                $('<option>').val('zls_bulk_mark_paid').text('Mark as Paid').appendTo("select[name='action2']");
                
                $('<option>').val('zls_bulk_mark_shipped').text('Mark as Shipped').appendTo("select[name='action']");
                $('<option>').val('zls_bulk_mark_shipped').text('Mark as Shipped').appendTo("select[name='action2']");
                
                $('<option>').val('zls_bulk_mark_delivered').text('Mark as Delivered').appendTo("select[name='action']");
                $('<option>').val('zls_bulk_mark_delivered').text('Mark as Delivered').appendTo("select[name='action2']");
                
                $('<option>').val('zls_bulk_send_notification').text('Send Notification').appendTo("select[name='action']");
                $('<option>').val('zls_bulk_send_notification').text('Send Notification').appendTo("select[name='action2']");
            });
        })(jQuery);
        </script>
        <?php
    }

    /**
     * Handle bulk actions
     */
    public static function handle_bulk_actions() {
        global $typenow;
        
        if (!in_array($typenow, array('zls_ship', 'zls_buy'))) {
            return;
        }

        $wp_list_table = _get_list_table('WP_Posts_List_Table');
        $action = $wp_list_table->current_action();

        if (!$action) {
            return;
        }

        // Verify nonce
        if (!isset($_REQUEST['_wpnonce'])) {
            return;
        }

        check_admin_referer('bulk-posts');

        $post_ids = isset($_REQUEST['post']) ? array_map('absint', (array)$_REQUEST['post']) : array();
        
        if (empty($post_ids)) {
            return;
        }

        switch ($action) {
            case 'zls_bulk_approve_quote':
                self::bulk_approve_quote($post_ids);
                break;
            case 'zls_bulk_mark_paid':
                self::bulk_mark_paid($post_ids);
                break;
            case 'zls_bulk_mark_shipped':
                self::bulk_mark_shipped($post_ids);
                break;
            case 'zls_bulk_mark_delivered':
                self::bulk_mark_delivered($post_ids);
                break;
            case 'zls_bulk_send_notification':
                self::bulk_send_notification($post_ids);
                break;
        }
    }

    /**
     * Bulk approve and send quote
     */
    private static function bulk_approve_quote($post_ids) {
        $count = 0;
        foreach ($post_ids as $post_id) {
            $post = get_post($post_id);
            if ($post && in_array($post->post_type, array('zls_ship', 'zls_buy'))) {
                update_post_meta($post_id, '_zls_status', 'quote_sent');
                $count++;

                if (class_exists('ZLS_Audit_Logger')) {
                    ZLS_Audit_Logger::log('BULK_QUOTE_APPROVED', array(
                        'post_id' => $post_id,
                        'performed_by' => get_current_user_id()
                    ));
                }
            }
        }

        wp_redirect(add_query_arg('bulk_quote_approved', $count, wp_get_referer()));
        exit;
    }

    /**
     * Bulk mark as paid
     */
    private static function bulk_mark_paid($post_ids) {
        $count = 0;
        foreach ($post_ids as $post_id) {
            $post = get_post($post_id);
            if ($post && in_array($post->post_type, array('zls_ship', 'zls_buy'))) {
                update_post_meta($post_id, '_zls_status', 'paid');
                update_post_meta($post_id, '_zls_paid_at', current_time('mysql'));
                $count++;

                if (class_exists('ZLS_Notifications')) {
                    ZLS_Notifications::send_status_update($post_id, 'paid');
                }

                if (class_exists('ZLS_Audit_Logger')) {
                    ZLS_Audit_Logger::log('BULK_MARKED_PAID', array(
                        'post_id' => $post_id,
                        'performed_by' => get_current_user_id()
                    ));
                }
            }
        }

        wp_redirect(add_query_arg('bulk_marked_paid', $count, wp_get_referer()));
        exit;
    }

    /**
     * Bulk mark as shipped
     */
    private static function bulk_mark_shipped($post_ids) {
        $count = 0;
        foreach ($post_ids as $post_id) {
            $post = get_post($post_id);
            if ($post && in_array($post->post_type, array('zls_ship', 'zls_buy'))) {
                update_post_meta($post_id, '_zls_status', 'shipped');
                update_post_meta($post_id, '_zls_shipped_at', current_time('mysql'));
                $count++;

                if (class_exists('ZLS_Notifications')) {
                    ZLS_Notifications::send_status_update($post_id, 'shipped');
                }

                if (class_exists('ZLS_Audit_Logger')) {
                    ZLS_Audit_Logger::log('BULK_MARKED_SHIPPED', array(
                        'post_id' => $post_id,
                        'performed_by' => get_current_user_id()
                    ));
                }
            }
        }

        wp_redirect(add_query_arg('bulk_marked_shipped', $count, wp_get_referer()));
        exit;
    }

    /**
     * Bulk mark as delivered
     */
    private static function bulk_mark_delivered($post_ids) {
        $count = 0;
        foreach ($post_ids as $post_id) {
            $post = get_post($post_id);
            if ($post && in_array($post->post_type, array('zls_ship', 'zls_buy'))) {
                update_post_meta($post_id, '_zls_status', 'delivered');
                update_post_meta($post_id, '_zls_delivered_at', current_time('mysql'));
                $count++;

                if (class_exists('ZLS_Notifications')) {
                    ZLS_Notifications::send_status_update($post_id, 'delivered');
                }

                if (class_exists('ZLS_Audit_Logger')) {
                    ZLS_Audit_Logger::log('BULK_MARKED_DELIVERED', array(
                        'post_id' => $post_id,
                        'performed_by' => get_current_user_id()
                    ));
                }
            }
        }

        wp_redirect(add_query_arg('bulk_marked_delivered', $count, wp_get_referer()));
        exit;
    }

    /**
     * Bulk send notification
     */
    private static function bulk_send_notification($post_ids) {
        $count = 0;
        foreach ($post_ids as $post_id) {
            $post = get_post($post_id);
            if ($post && in_array($post->post_type, array('zls_ship', 'zls_buy'))) {
                $status = get_post_meta($post_id, '_zls_status', true);
                
                if (class_exists('ZLS_Notifications')) {
                    ZLS_Notifications::send_status_update($post_id, $status);
                    $count++;
                }

                if (class_exists('ZLS_Audit_Logger')) {
                    ZLS_Audit_Logger::log('BULK_NOTIFICATION_SENT', array(
                        'post_id' => $post_id,
                        'status' => $status,
                        'performed_by' => get_current_user_id()
                    ));
                }
            }
        }

        wp_redirect(add_query_arg('bulk_notifications_sent', $count, wp_get_referer()));
        exit;
    }

    /**
     * Get bulk actions summary
     */
    public static function get_bulk_actions_summary($post_ids) {
        $summary = array(
            'total_requests' => count($post_ids),
            'total_value_usd' => 0,
            'total_value_ngn' => 0,
            'status_counts' => array(),
            'type_counts' => array()
        );

        foreach ($post_ids as $post_id) {
            $post = get_post($post_id);
            if (!$post) continue;

            // Count by type
            $type = $post->post_type === 'zls_ship' ? 'Ship' : 'Buy';
            $summary['type_counts'][$type] = ($summary['type_counts'][$type] ?? 0) + 1;

            // Count by status
            $status = get_post_meta($post_id, '_zls_status', true) ?: 'pending';
            $summary['status_counts'][$status] = ($summary['status_counts'][$status] ?? 0) + 1;

            // Calculate total values
            $quote_amount = floatval(get_post_meta($post_id, '_zls_quote_amount', true) ?? 0);
            $summary['total_value_ngn'] += $quote_amount;

            // Simple USD conversion using default rate (1500 NGN = 1 USD)
            $summary['total_value_usd'] += $quote_amount / 1500;
        }

        return $summary;
    }
}