<?php
/**
 * Zephora Logistic Systems Plugin Uninstall Handler
 * 
 * This file is executed when the plugin is deleted from WordPress.
 * It performs a complete cleanup of all plugin data.
 * 
 * @package ZLS
 * @since 1.0.0
 */

// Exit if not called from WordPress uninstall process
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Security check - ensure user has permissions
if (!current_user_can('activate_plugins')) {
    exit;
}

// Define plugin constants for cleanup
define('ZLS_TABLE_PREFIX', 'zls_');

/**
 * Remove all plugin tables
 */
function zls_uninstall_remove_tables() {
    global $wpdb;
    
    $tables = array(
        'requests',
        'kyc_verifications',
        'addresses',
        'payments',
        'audit_logs',
        'notifications',
        'notes',
        'exchange_rates'
    );
    
    foreach ($tables as $table) {
        $table_name = $wpdb->prefix . ZLS_TABLE_PREFIX . $table;
        $wpdb->query("DROP TABLE IF EXISTS {$table_name}");
    }
}

/**
 * Remove all plugin options
 */
function zls_uninstall_remove_options() {
    $options = array(
        'zls_version',
        'zls_db_version',
        'zls_settings',
        'zls_paystack_settings',
        'zls_flutterwave_settings',
        'zls_termii_settings',
        'zls_email_settings',
        'zls_warehouse_address',
        'zls_us_addresses',
        'zls_exchange_rates',
        'zls_kyc_requirements',
        'zls_shipping_rates',
        'zls_buy_for_me_fees',
        'zls_gdpr_settings',
        'zls_audit_retention_days',
        'zls_installed',
        'zls_activation_date'
    );
    
    foreach ($options as $option) {
        delete_option($option);
    }
    
    // Delete network-wide options if multisite
    if (is_multisite()) {
        foreach ($options as $option) {
            delete_site_option($option);
        }
    }
}

/**
 * Remove all transients
 */
function zls_uninstall_remove_transients() {
    global $wpdb;
    
    $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_zls_%'");
    $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_zls_%'");
    
    if (is_multisite()) {
        $wpdb->query("DELETE FROM {$wpdb->sitemeta} WHERE meta_key LIKE '_site_transient_zls_%'");
        $wpdb->query("DELETE FROM {$wpdb->sitemeta} WHERE meta_key LIKE '_site_transient_timeout_zls_%'");
    }
}

/**
 * Remove user metadata
 */
function zls_uninstall_remove_user_meta() {
    global $wpdb;
    
    $user_meta_keys = array(
        'zls_kyc_status',
        'zls_kyc_verified_date',
        'zls_customer_id',
        'zls_default_address',
        'zls_wallet_balance',
        'zls_total_shipments',
        'zls_account_status'
    );
    
    foreach ($user_meta_keys as $meta_key) {
        $wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->usermeta} WHERE meta_key = %s", $meta_key));
    }
}

/**
 * Remove scheduled hooks
 */
function zls_uninstall_remove_scheduled_hooks() {
    $hooks = array(
        'zls_hourly_rate_update',
        'zls_daily_cleanup',
        'zls_weekly_report',
        'zls_payment_reminder',
        'zls_kyc_expiry_check'
    );
    
    foreach ($hooks as $hook) {
        wp_clear_scheduled_hook($hook);
    }
}

/**
 * Clean up uploaded files (optional - commented out for safety)
 * Uncomment if you want to remove uploaded KYC documents and PDFs
 */
function zls_uninstall_remove_uploads() {
    $upload_dir = wp_upload_dir();
    $zls_upload_dir = $upload_dir['basedir'] . '/zephora-logistics/';
    
    if (file_exists($zls_upload_dir)) {
        // WARNING: This will permanently delete all uploaded files
        // zls_recursive_delete($zls_upload_dir);
    }
}

/**
 * Recursively delete directory (helper function)
 */
function zls_recursive_delete($dir) {
    if (!file_exists($dir)) {
        return;
    }
    
    $files = array_diff(scandir($dir), array('.', '..'));
    foreach ($files as $file) {
        $path = $dir . '/' . $file;
        if (is_dir($path)) {
            zls_recursive_delete($path);
        } else {
            unlink($path);
        }
    }
    rmdir($dir);
}

// Execute uninstall routine
zls_uninstall_remove_scheduled_hooks();
zls_uninstall_remove_tables();
zls_uninstall_remove_options();
zls_uninstall_remove_transients();
zls_uninstall_remove_user_meta();
// zls_uninstall_remove_uploads(); // Uncomment to also delete uploaded files

// Clear any cached data
wp_cache_flush();
