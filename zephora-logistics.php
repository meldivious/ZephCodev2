<?php
/**
 * Plugin Name: Zephora Logistic Systems
 * Plugin URI: https://zephora.logistics
 * Description: Secure frontend logistics platform: KYC-gated SHIP FOR ME & BUY FOR ME workflows with iOS-inspired UI.
 * Version: 1.0.17
 * Author: Zephora Engineering
 * Author URI: https://zephora.logistics
 * License: GPL-3.0-only
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain: zls
 * Domain Path: /languages
 * Requires PHP: 7.4
 * Requires at least: 6.4
 */


if (!defined('ABSPATH')) exit;

define('ZLS_VERSION', '1.0.17');
define('ZLS_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('ZLS_PLUGIN_URL', plugin_dir_url(__FILE__));
define('ZLS_AUDIT_LOG', WP_CONTENT_DIR . '/zls-audit.log');

// CREATE PLUGIN PAGES FUNCTION
function zls_create_plugin_pages() {
    $login_content = '<!-- Custom login page content can be added here -->
<div class="zls-login-notice">
    <p>Please use the WordPress login form or contact administrator for login access.</p>
    <p><a href="' . wp_login_url() . '">Go to WordPress Login</a></p>
</div>';

    $pages = array(
        array(
            'title' => 'My Dashboard',
            'slug' => 'my-dashboard',
            'content' => '[zls_dashboard]',
            'template' => ''
        ),
        array(
            'title' => 'KYC Verification',
            'slug' => 'kyc-verification',
            'content' => '[zls_dashboard]', // Uses dashboard shortcode with KYC handling
            'template' => ''
        ),
        array(
            'title' => 'Ship For Me',
            'slug' => 'ship-for-me',
            'content' => '[zls_ship_for_me]',
            'template' => ''
        ),
        array(
            'title' => 'Buy For Me',
            'slug' => 'buy-for-me',
            'content' => '[zls_buy_for_me]',
            'template' => ''
        ),
        array(
            'title' => 'Login',
            'slug' => 'login',
            'content' => $login_content,
            'template' => ''
        )
    );

    foreach ($pages as $page) {
        // Check if page already exists
        $existing_page = get_page_by_path($page['slug']);

        if (!$existing_page) {
            $page_id = wp_insert_post(array(
                'post_title' => $page['title'],
                'post_name' => $page['slug'],
                'post_content' => $page['content'],
                'post_status' => 'publish',
                'post_type' => 'page',
                'post_author' => 1,
                'comment_status' => 'closed',
                'ping_status' => 'closed'
            ));

            if ($page_id && !is_wp_error($page_id)) {
                // Log successful page creation
                if (class_exists('ZLS_Audit_Logger')) {
                    ZLS_Audit_Logger::log('PAGE_CREATED', array(
                        'page_id' => $page_id,
                        'page_title' => $page['title'],
                        'page_slug' => $page['slug']
                    ));
                }
            } else {
                // Log page creation failure
                if (class_exists('ZLS_Error_Handler')) {
                    ZLS_Error_Handler::log_app_error('Failed to create plugin page', array(
                        'page_title' => $page['title'],
                        'page_slug' => $page['slug'],
                        'error' => is_wp_error($page_id) ? $page_id->get_error_message() : 'Unknown error'
                    ));
                }
            }
        }
    }
}

// 1. ACTIVATION HOOK
function zls_activate_plugin() {
    if (!current_user_can('activate_plugins')) return;

    // Create necessary directories
    $upload_dir = wp_upload_dir();
    $dirs = array(
        $upload_dir['basedir'] . '/zls-invoices',
        $upload_dir['basedir'] . '/zls-kyc-documents',
        WP_CONTENT_DIR . '/zls-logs'
    );

    foreach ($dirs as $dir) {
        if (!file_exists($dir)) {
            if (!wp_mkdir_p($dir)) {
                if (class_exists('ZLS_Error_Handler')) {
                    ZLS_Error_Handler::log_app_error('Failed to create directory during activation', array('directory' => $dir));
                }
                error_log('ZLS: Failed to create directory: ' . $dir);
            }
        }
    }

    // Create necessary pages
    zls_create_plugin_pages();

    // Set default options
    $default_settings = array(
        'default_tax_rate' => '7.5'
    );

    if (!get_option('zls_settings')) {
        if (!update_option('zls_settings', $default_settings)) {
            if (class_exists('ZLS_Error_Handler')) {
                ZLS_Error_Handler::log_app_error('Failed to set default settings during activation');
            }
        }
    }

    // Create default US address if none exist
    if (!get_option('zls_us_addresses')) {
        $default_address = array(
            'addr_1' => array(
                'id' => 'addr_1',
                'label' => 'Texas Warehouse',
                'address_line1' => '123 Logistics Drive',
                'address_line2' => '',
                'city' => 'Houston',
                'state' => 'TX',
                'postal_code' => '77001',
                'country' => 'USA',
                'contact_phone' => '+1-555-0123',
                'is_active' => '1'
            )
        );
        if (!update_option('zls_us_addresses', $default_address)) {
            if (class_exists('ZLS_Error_Handler')) {
                ZLS_Error_Handler::log_app_error('Failed to set default US address during activation');
            }
        }
    }

    // Flush rewrite rules for custom post types
    flush_rewrite_rules();

    // Log activation
    if (class_exists('ZLS_Audit_Logger')) {
        ZLS_Audit_Logger::log('PLUGIN_ACTIVATED', array(
            'version' => ZLS_VERSION,
            'activated_by' => get_current_user_id()
        ));
    }
}
register_activation_hook(__FILE__, 'zls_activate_plugin');

// 2. DEACTIVATION HOOK
function zls_deactivate_plugin() {
    if (!current_user_can('activate_plugins')) return;

    // Flush rewrite rules
    flush_rewrite_rules();

    // Log deactivation
    if (class_exists('ZLS_Audit_Logger')) {
        ZLS_Audit_Logger::log('PLUGIN_DEACTIVATED', array(
            'version' => ZLS_VERSION,
            'deactivated_by' => get_current_user_id()
        ));
    }
}
register_deactivation_hook(__FILE__, 'zls_deactivate_plugin');

// 3. UNINSTALL HOOK
function zls_uninstall_cleanup() {
    if (!current_user_can('activate_plugins')) return;
    $opts = array('zls_settings','zls_us_addresses','zls_bank_details','zls_email_templates');
    foreach ($opts as $o) delete_option($o);
    if (file_exists(ZLS_AUDIT_LOG)) @unlink(ZLS_AUDIT_LOG);
}
register_uninstall_hook(__FILE__, 'zls_uninstall_cleanup');



// Register Post Types
add_action('init', 'zls_register_ship_buy_types');
function zls_register_ship_buy_types() {
    register_post_type('zls_ship', array(
        'public' => false,
        'show_ui' => false,
        'supports' => array('title', 'author'),
        'rewrite' => false,
        'query_var' => false,
    ));
    register_post_type('zls_buy', array(
        'public' => false,
        'show_ui' => false,
        'supports' => array('title', 'author'),
        'rewrite' => false,
        'query_var' => false,
    ));
}

// Handle Ship For Me Submission
add_action('init', 'zls_handle_ship_submission');
function zls_handle_ship_submission() {
    if (!isset($_POST['sfm_submit'])) return;
    if (!is_user_logged_in()) return;
    if (!isset($_POST['zls_ship_nonce']) || !wp_verify_nonce($_POST['zls_ship_nonce'], 'zls_ship')) {
        wp_die('Security check failed');
    }
    
    if (!class_exists('ZLS_Ship_For_Me')) return;
    
    $result = ZLS_Ship_For_Me::submit();
    
    if ($result === true || is_numeric($result)) {
        // Redirect to dashboard with success flag
        wp_redirect(home_url('/my-dashboard?sfm_ok=1'));
        exit;
    }
    
    if (is_wp_error($result)) {
        set_transient('zls_ship_error_' . get_current_user_id(), $result->get_error_message(), 30);
        wp_redirect(wp_get_referer());
        exit;
    }
}


// Handle Buy For Me Submission
add_action('init', 'zls_handle_buy_submission', 1);
function zls_handle_buy_submission() {
    // Only run if form was submitted
    if (!isset($_POST['bfm_submit'])) {
        return;
    }
    
    // Check user is logged in
    if (!is_user_logged_in()) {
        return;
    }
    
    // Verify nonce
    if (!isset($_POST['zls_buy_nonce']) || !wp_verify_nonce($_POST['zls_buy_nonce'], 'zls_buy')) {
        wp_die('Security check failed');
    }
    
    // Make sure class exists
    if (!class_exists('ZLS_Buy_For_Me')) {
        return;
    }
    
    // Submit the form
    $result = ZLS_Buy_For_Me::submit();
    
    // Handle success
    if ($result === true || is_numeric($result)) {
        wp_redirect(home_url('/my-dashboard?bfm_ok=1'));
        exit;
    }
    
    // Handle error
    if (is_wp_error($result)) {
        set_transient('zls_buy_error_' . get_current_user_id(), $result->get_error_message(), 30);
        wp_redirect(wp_get_referer());
        exit;
    }
}

// 2. SHORTCODE
add_shortcode('zls_dashboard', 'zls_render_dashboard_shortcode');

function zls_render_dashboard_shortcode() {
    // ✅ Guest redirect to /login
    if (!is_user_logged_in()) {
        wp_redirect(home_url('/login'));
        exit;
    }

    $user_id = get_current_user_id();
    $is_admin = current_user_can('manage_options');

    // User Display Name
    $user = wp_get_current_user();
    $display_name = $user->first_name ? $user->first_name : $user->display_name;

    if ($is_admin) {
        $kyc_status = 'approved';
        $kyc_data = array();
    } else {
        $kyc_status = get_user_meta($user_id, '_zls_kyc_status', true) ?: 'pending';
        $kyc_data = get_user_meta($user_id, '_zls_kyc_data', true) ?: array();
    }

    // ✅ ADMIN BYPASS: Only redirect non-admins with pending KYC
    if (!$is_admin && $kyc_status !== 'approved') {
        wp_redirect(home_url('/kyc-verification'));
        exit;
    }

    // ✅ Handle KYC Submission
    if (isset($_POST['zls_kyc_submit']) && isset($_POST['zls_nonce']) && wp_verify_nonce($_POST['zls_nonce'], 'zls_kyc_action') && !$is_admin) {
        update_user_meta($user_id, '_zls_kyc_data', array(
            'nin' => sanitize_text_field($_POST['zls_kyc_nin']),
            'phone' => sanitize_text_field($_POST['zls_kyc_phone']),
            'address' => sanitize_textarea_field($_POST['zls_kyc_address'])
        ));
        update_user_meta($user_id, '_zls_kyc_status', 'pending');
        wp_safe_redirect(add_query_arg('kyc_done', '1', wp_get_referer()));
        exit;
    }

// ✅ Handle SHIP Submission
if (isset($_POST['sfm_submit']) && isset($_POST['zls_ship_nonce']) && wp_verify_nonce($_POST['zls_ship_nonce'], 'zls_ship')) {
    if (class_exists('ZLS_Ship_For_Me')) {
        $res = ZLS_Ship_For_Me::submit();
        if ($res === true) { 
            wp_safe_redirect(home_url('/my-dashboard')); 
            exit; 
        }
        elseif (is_wp_error($res)) {
            $ship_error = $res->get_error_message();
        }
    }
}



    // ✅ DELEGATE TO DASHBOARD CLASS
    if (class_exists('ZLS_Dashboard')) {
        return ZLS_Dashboard::render();
    }

    return '<p>Dashboard module not loaded.</p>';
}



// Add Ship For Me shortcode
add_shortcode('zls_ship_for_me', 'zls_render_ship_for_me_shortcode');

function zls_render_ship_for_me_shortcode() {
    if (class_exists('ZLS_Ship_For_Me')) {
        return ZLS_Ship_For_Me::render();
    }
    return '<p>Ship For Me module not loaded.</p>';
}

// Add Buy For Me shortcode
add_shortcode('zls_buy_for_me', 'zls_render_buy_for_me_shortcode');
function zls_render_buy_for_me_shortcode() {
    if (class_exists('ZLS_Buy_For_Me')) {
        return ZLS_Buy_For_Me::render();
    }
    return '<p>Buy For Me module not loaded.</p>';
}


// 3. BOOTSTRAP
function zls_load_core_classes() {
    $files = array(
        'class-security.php',
        'class-audit-logger.php',
        'class-error-handler.php',
        'class-kyc-manager.php',
        'class-address-manager.php',
        'class-request-base.php',
        'class-request-manager.php',
        'class-ship-for-me.php',
        'class-buy-for-me.php',
        'class-notes.php',
        'class-payments.php',
        'class-notifications.php',
        'class-pdf.php',
        'class-admin-ui.php',
        'class-dashboard.php',
        'class-bulk-operations.php',
        'class-export-manager.php',
        'class-module-loader.php',
        'class-gdpr.php',
        'class-core.php',
        'class-kyc-frontend.php'
    );
    foreach($files as $f) {
        if(file_exists(ZLS_PLUGIN_DIR . 'includes/' . $f)) {
            require_once ZLS_PLUGIN_DIR . 'includes/' . $f;
        }
    }
}

add_action('plugins_loaded', 'zls_bootstrap');
function zls_bootstrap() {
    zls_load_core_classes();
    if (class_exists('ZLS_Error_Handler')) ZLS_Error_Handler::init();
    if (class_exists('ZLS_KYC_Manager')) ZLS_KYC_Manager::init();
    if (class_exists('ZLS_KYC_Frontend')) ZLS_KYC_Frontend::init();
    if (class_exists('ZLS_Request_Manager')) ZLS_Request_Manager::init();
    if (class_exists('ZLS_Payments')) ZLS_Payments::init();
    if (class_exists('ZLS_PDF')) ZLS_PDF::init();
    if (class_exists('ZLS_Notifications')) ZLS_Notifications::init();
    if (class_exists('ZLS_Settings')) ZLS_Settings::get_instance()->init();
    if (class_exists('ZLS_GDPR')) ZLS_GDPR::init();
    if (class_exists('ZLS_Bulk_Operations')) ZLS_Bulk_Operations::init();
    if (class_exists('ZLS_Export_Manager')) ZLS_Export_Manager::init();
}


// ✅ Ensure our CPTs are registered before admin UI loads
add_action('plugins_loaded', function() {
    if (!post_type_exists('zls_ship')) {
        require_once ZLS_PLUGIN_DIR . 'includes/class-post-types.php';
    }
}, 5);

// 4. AJAX PDF
add_action('wp_ajax_download_zls_invoice', 'zls_ajax_download_invoice');
add_action('wp_ajax_nopriv_download_zls_invoice', 'zls_ajax_download_invoice');
function zls_ajax_download_invoice() {
    try {
        if (!class_exists('ZLS_PDF')) {
            if (class_exists('ZLS_Error_Handler')) {
                ZLS_Error_Handler::log_app_error('PDF module not loaded during invoice download attempt');
            }
            wp_die('PDF module not loaded');
        }
        $request_id = absint($_GET['request_id'] ?? 0);
        if (!$request_id) {
            wp_die('Invalid request ID');
        }
        $post = get_post($request_id);
        if (!$post || ($post->post_author != get_current_user_id() && !current_user_can('manage_options'))) {
            if (class_exists('ZLS_Error_Handler')) {
                ZLS_Error_Handler::log_app_error('Unauthorized invoice download attempt', array(
                    'request_id' => $request_id,
                    'user_id' => get_current_user_id(),
                    'post_author' => $post ? $post->post_author : 'null'
                ));
            }
            wp_die('Unauthorized');
        }
        ZLS_PDF::generate_invoice($request_id, 'download');
    } catch (Exception $e) {
        if (class_exists('ZLS_Error_Handler')) {
            ZLS_Error_Handler::log_app_error('Exception during invoice download', array(
                'exception' => $e->getMessage(),
                'request_id' => $_GET['request_id'] ?? 0
            ));
        }
        wp_die('An error occurred while generating the invoice');
    }
}


// 5. AJAX: Fetch Shipment/Purchase Details
// AJAX: Fetch Shipment Details
add_action('wp_ajax_fetch_shipment_details', 'zls_ajax_shipment_details');
function zls_ajax_shipment_details() {
    if (!is_user_logged_in()) wp_die('Unauthorized');

    $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
    if (!$post_id) wp_die('Missing ID');

    $post = get_post($post_id);
    if (!$post || !in_array($post->post_type, ['zls_ship', 'zls_buy'])) {
        wp_die('Invalid Post Type');
    }

    if ($post->post_author != get_current_user_id() && !current_user_can('manage_options')) {
        wp_die('Unauthorized access');
    }

    // Get ALL post meta for debugging
    $all_meta = get_post_meta($post_id);
    error_log('=== POST ' . $post_id . ' META ===');
    error_log(print_r($all_meta, true));

    $unique_id = get_user_meta($post->post_author, '_zls_unique_id', true) ?: 'N/A';
    $status = get_post_meta($post_id, '_zls_status', true) ?: 'pending';
    $created = get_post_meta($post_id, '_zls_created_at', true) ?: $post->post_date;

    $meta = array(
        'product_name'    => get_post_meta($post_id, '_zls_product_name', true),
        'tracking_number' => get_post_meta($post_id, '_zls_tracking_number', true),
        'value_usd'       => get_post_meta($post_id, '_zls_value_usd', true),
        'packages'        => get_post_meta($post_id, '_zls_packages', true),
        'weight_kg'       => get_post_meta($post_id, '_zls_weight_kg', true),
        'budget_usd'      => get_post_meta($post_id, '_zls_budget_usd', true),
        'quantity'        => get_post_meta($post_id, '_zls_quantity', true),
        'specs'           => get_post_meta($post_id, '_zls_specs', true),
        'product_url'     => get_post_meta($post_id, '_zls_product_url', true),
        'content_desc'    => get_post_meta($post_id, '_zls_content_desc', true),
        'recipient_name'  => get_post_meta($post_id, '_zls_recipient_name', true),
        'recipient_phone' => get_post_meta($post_id, '_zls_recipient_phone', true),
        'recipient_email' => get_post_meta($post_id, '_zls_recipient_email', true),
        'address_line1'   => get_post_meta($post_id, '_zls_address_line1', true),
        'city'            => get_post_meta($post_id, '_zls_city', true),
        'state'           => get_post_meta($post_id, '_zls_state', true),
        'postal_code'     => get_post_meta($post_id, '_zls_postal_code', true),
        'landmark'        => get_post_meta($post_id, '_zls_landmark', true),
        'quote_amount'    => get_post_meta($post_id, '_zls_quote_amount', true),
    );

    $response = array(
        'success'     => true,
        'data'        => array(
            'id'          => $post_id,
            'type'        => $post->post_type,
            'status'      => $status,
            'date'        => date('M j, Y', strtotime($created)),
            'customer_id' => 'CUST-' . strtoupper($unique_id),
            'invoice_url' => home_url('/wp-admin/admin-ajax.php?action=download_zls_invoice&request_id=' . $post_id),
            'meta'        => $meta
        )
    );

    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}


// AJAX: Delete Pending Request
add_action('wp_ajax_zls_delete_request', 'zls_ajax_delete_request');
function zls_ajax_delete_request() {
    if (!is_user_logged_in()) wp_send_json_error('Unauthorized', 403);
    
    $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
    $nonce   = isset($_POST['nonce']) ? sanitize_text_field($_POST['nonce']) : '';
    
    if (!$post_id || !wp_verify_nonce($nonce, 'zls_delete_' . $post_id)) {
        wp_send_json_error('Invalid request', 403);
    }

    $post = get_post($post_id);
    if (!$post || !in_array($post->post_type, ['zls_ship', 'zls_buy'])) {
        wp_send_json_error('Invalid post type', 400);
    }

    // ✅ Ownership check
    if ($post->post_author != get_current_user_id() && !current_user_can('manage_options')) {
        wp_send_json_error('Unauthorized', 403);
    }

    // ✅ Status check (only pending)
    $status = get_post_meta($post_id, '_zls_status', true);
    if ($status !== 'pending') {
        wp_send_json_error('Only pending requests can be deleted.', 400);
    }

    // ✅ Delete & Log
    $deleted = wp_delete_post($post_id, true); // true = permanent delete
    if ($deleted) {
        if (class_exists('ZLS_Audit_Logger')) {
            ZLS_Audit_Logger::log('REQUEST_DELETED', [
                'post_id' => $post_id,
                'type'    => $post->post_type,
                'user_id' => get_current_user_id()
            ]);
        }
        wp_send_json_success('Request deleted successfully.');
    }
    
    wp_send_json_error('Deletion failed.', 500);
}

// AJAX: Load More Recent Activity
add_action('wp_ajax_load_recent_activity', 'zls_ajax_load_recent_activity');
function zls_ajax_load_recent_activity() {
    if (!is_user_logged_in()) wp_send_json_error('Unauthorized', 403);
    check_ajax_referer('zls_load_activity', 'nonce');

    $page = isset($_POST['page']) ? absint($_POST['page']) : 2;
    $user_id = get_current_user_id();

    $query = new WP_Query(array(
        'post_type'      => array('zls_ship', 'zls_buy'),
        'author'         => $user_id,
        'posts_per_page' => 10,
        'paged'          => $page,
        'orderby'        => 'date',
        'order'          => 'DESC'
    ));

    ob_start();
    if ($query->have_posts()) {
        while ($query->have_posts()) {
            $query->the_post();
            $status    = get_post_meta(get_the_ID(), '_zls_status', true);
            $tracking  = get_post_meta(get_the_ID(), '_zls_tracking_number', true);
            $post_type = get_post_type();
            $type_label = ($post_type === 'zls_ship') ? 'Ship To Nigeria' : 'Buy For Me';

            $status_class = 'zls-status-pending';
            $status_text  = 'Pending';
            if (in_array($status, array('received_us'))) {
                $status_class = 'zls-status-received';
                $status_text  = 'Received in Texas';
            } elseif (in_array($status, array('shipped', 'in_transit'))) {
                $status_class = 'zls-status-transit';
                $status_text  = ucfirst(str_replace('_', ' ', $status));
            } elseif ($status === 'delivered') {
                $status_class = 'zls-status-delivered';
                $status_text  = 'Delivered';
            } elseif ($status !== 'pending') {
                $status_text = ucfirst(str_replace('_', ' ', $status));
            }
            ?>
            <tr>
                <td style="font-weight: 600; color: #111827;"><?php the_title(); ?></td>
                <td><?php echo esc_html($type_label); ?></td>
                <td><?php echo $tracking ? esc_html($tracking) : '—'; ?></td>
                <td><span class="zls-status-badge <?php echo esc_attr($status_class); ?>"><?php echo esc_html($status_text); ?></span></td>
                <td><?php echo get_the_date('M j, Y'); ?></td>
                <td style="white-space:nowrap; display:flex; gap:8px; align-items:center;">
                    <button class="zls-btn-view zls-open-modal" data-post-id="<?php echo get_the_ID(); ?>">View</button>
                    <?php if ($status === 'pending'): ?>
                        <button class="zls-btn-delete" data-post-id="<?php echo get_the_ID(); ?>" data-nonce="<?php echo wp_create_nonce('zls_delete_' . get_the_ID()); ?>" title="Delete pending request">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg>
                        </button>
                    <?php endif; ?>
                </td>
            </tr>
            <?php
        }
        wp_reset_postdata();
    }
    $html = ob_get_clean();

    wp_send_json_success(array(
        'html'         => $html,
        'max_pages'    => $query->max_num_pages,
        'current_page' => $page,
        'has_more'     => $page < $query->max_num_pages
    ));
}


// AJAX: Confirm Payment
add_action('wp_ajax_confirm_payment', 'zls_ajax_confirm_payment');
function zls_ajax_confirm_payment() {
    if (!is_user_logged_in()) wp_send_json_error('Unauthorized', 403);
    
    $post_id = isset($_POST['post_id']) ? absint($_POST['post_id']) : 0;
    if (!$post_id) wp_send_json_error('Missing ID', 400);

    $post = get_post($post_id);
    if (!$post) wp_send_json_error('Invalid Post', 404);
    
    // Ownership check
    if ($post->post_author != get_current_user_id() && !current_user_can('manage_options')) {
        wp_send_json_error('Unauthorized', 403);
    }

    $status = get_post_meta($post_id, '_zls_status', true);
    if ($status === 'payment_pending' || $status === 'paid') {
        wp_send_json_error('Payment already confirmed.', 400);
    }

    // ✅ Update Status
    update_post_meta($post_id, '_zls_status', 'payment_pending');
    
    if (class_exists('ZLS_Audit_Logger')) {
        ZLS_Audit_Logger::log('PAYMENT_CONFIRMED', ['post_id' => $post_id, 'user_id' => get_current_user_id()]);
    }

    wp_send_json_success('Payment confirmed. We will verify and process your order shortly.');
}



