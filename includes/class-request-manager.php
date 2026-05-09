<?php
/**
 * Zephora Logistic Systems - Request Management
 * Unified admin table for Ship & Buy requests
 */
class ZLS_Request_Manager {
    public static function init() {
        add_action('admin_menu', [__CLASS__, 'add_submenu']);
        add_action('admin_init', [__CLASS__, 'process_actions']);
    }

    public static function add_submenu() {
        add_submenu_page(
            'zephora-logistics',
            'Request Management',
            'Request Management',
            'manage_options',
            'zls-requests',
            [__CLASS__, 'render_page']
        );
    }

    public static function process_actions() {
        if (!isset($_POST['zls_req_action'], $_POST['zls_req_nonce'])) return;
        if (!wp_verify_nonce($_POST['zls_req_nonce'], 'zls_req_manage')) return;
        if (!current_user_can('manage_options')) return;

        $post_id = absint($_POST['post_id'] ?? 0);
        if (!$post_id) return;

        $action = sanitize_text_field($_POST['zls_req_action']);
        if ($action === 'update_status') {
            update_post_meta($post_id, '_zls_status', sanitize_text_field($_POST['new_status'] ?? 'pending'));
        } elseif ($action === 'update_quote') {
            $quote = floatval($_POST['quote_amount'] ?? 0);
            update_post_meta($post_id, '_zls_quote_amount', $quote);
            if ($quote > 0 && get_post_meta($post_id, '_zls_status', true) === 'pending') {
                update_post_meta($post_id, '_zls_status', 'quote_sent');
            }
        }
        wp_safe_redirect(add_query_arg('req_updated', '1', wp_get_referer()));
        exit;
    }

    public static function render_page() {
        if (isset($_GET['req_updated'])) {
            echo '<div class="notice notice-success is-dismissible"><p>✅ Request updated successfully.</p></div>';
        }

        $type_filter = isset($_GET['req_type']) ? sanitize_text_field($_GET['req_type']) : 'all';
        $status_filter = isset($_GET['req_status']) ? sanitize_text_field($_GET['req_status']) : 'all';

        $args = ['post_type' => ['zls_ship', 'zls_buy'], 'posts_per_page' => 50, 'orderby' => 'date', 'order' => 'DESC', 'post_status' => 'publish'];
        if ($type_filter !== 'all') $args['post_type'] = 'zls_' . $type_filter;
        if ($status_filter !== 'all') $args['meta_query'] = [['key' => '_zls_status', 'value' => $status_filter]];

        $query = new WP_Query($args);
        $labels = class_exists('ZLS_Request_Base') ? ZLS_Request_Base::get_status_labels() : [];
        ?>
        <div class="wrap">
            <h1>Request Management</h1>
            <div style="margin:15px 0;display:flex;gap:8px;flex-wrap:wrap;">
                <strong>Type:</strong>
                <a href="?page=zls-requests&req_type=all&req_status=<?php echo esc_attr($status_filter); ?>" class="button <?php echo $type_filter==='all'?'button-primary':''; ?>">All</a>
                <a href="?page=zls-requests&req_type=ship&req_status=<?php echo esc_attr($status_filter); ?>" class="button <?php echo $type_filter==='ship'?'button-primary':''; ?>">📦 Ship</a>
                <a href="?page=zls-requests&req_type=buy&req_status=<?php echo esc_attr($status_filter); ?>" class="button <?php echo $type_filter==='buy'?'button-primary':''; ?>">🛒 Buy</a>
                <span style="margin-left:12px;"><strong>Status:</strong></span>
                <a href="?page=zls-requests&req_type=<?php echo esc_attr($type_filter); ?>&req_status=all" class="button <?php echo $status_filter==='all'?'button-primary':''; ?>">All</a>
                <a href="?page=zls-requests&req_type=<?php echo esc_attr($type_filter); ?>&req_status=pending" class="button <?php echo $status_filter==='pending'?'button-primary':''; ?>">Pending</a>
                <a href="?page=zls-requests&req_type=<?php echo esc_attr($type_filter); ?>&req_status=quote_sent" class="button <?php echo $status_filter==='quote_sent'?'button-primary':''; ?>">Quote Ready</a>
                <a href="?page=zls-requests&req_type=<?php echo esc_attr($type_filter); ?>&req_status=paid" class="button <?php echo $status_filter==='paid'?'button-primary':''; ?>">Paid</a>
            </div>

            <?php if (!$query->have_posts()): ?>
                <div class="notice notice-info"><p>No requests found.</p></div>
            <?php else: ?>
                <table class="wp-list-table widefat fixed striped">
                    <thead><tr><th>ID</th><th>Type</th><th>Customer</th><th>Product</th><th>Status</th><th>Quote (₦)</th><th>Date</th><th>Actions</th></tr></thead>
                    <tbody>
                    <?php while($query->have_posts()): $query->the_post();
                        $pid = get_the_ID();
                        $type = get_post_type() === 'zls_ship' ? 'Ship' : 'Buy';
                        $user = get_userdata(get_post_field('post_author', $pid));
                        $status = get_post_meta($pid, '_zls_status', true) ?: 'pending';
                        $quote = floatval(get_post_meta($pid, '_zls_quote_amount', true));
                    ?>
                    <tr>
                        <td>#<?php echo absint($pid); ?></td>
                        <td><span class="dashicons <?php echo $type==='Ship'?'dashicons-cart':'dashicons-store'; ?>"></span> <?php echo $type; ?></td>
                        <td><?php echo $user ? '<strong>'.esc_html($user->display_name).'</strong><br><small>'.esc_html($user->user_email).'</small>' : 'Unknown'; ?></td>
                        <td><strong><?php the_title(); ?></strong></td>
                        <td>
                            <form method="post" style="display:inline;">
                                <?php wp_nonce_field('zls_req_manage', 'zls_req_nonce'); ?>
                                <input type="hidden" name="post_id" value="<?php echo $pid; ?>">
                                <input type="hidden" name="zls_req_action" value="update_status">
                                <select name="new_status" onchange="this.form.submit()" style="padding:4px;border-radius:4px;">
                                    <?php foreach($labels as $k=>$v): ?>
                                        <option value="<?php echo esc_attr($k); ?>" <?php selected($status, $k); ?>><?php echo esc_html($v); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </form>
                        </td>
                        <td>
                            <form method="post" style="display:flex;gap:4px;align-items:center;">
                                <?php wp_nonce_field('zls_req_manage', 'zls_req_nonce'); ?>
                                <input type="hidden" name="post_id" value="<?php echo $pid; ?>">
                                <input type="hidden" name="zls_req_action" value="update_quote">
                                <input type="number" name="quote_amount" value="<?php echo esc_attr($quote); ?>" step="0.01" style="width:80px;padding:4px;border-radius:4px;">
                                <button type="submit" class="button button-small">Set</button>
                            </form>
                        </td>
                        <td><?php echo get_the_date('M j, Y'); ?></td>
                        <td><a href="<?php echo admin_url('post.php?post='.$pid.'&action=edit'); ?>" class="button button-small">Edit</a></td>
                    </tr>
                    <?php endwhile; wp_reset_postdata(); ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
        <?php
    }
}