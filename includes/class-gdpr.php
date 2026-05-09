<?php
/**
 * Zephora Logistic Systems - GDPR Compliance Hooks
 * Hooks into WP Privacy Tools (Export & Erase)
 */
class ZLS_GDPR {
    public static function init() {
        add_filter('wp_privacy_personal_data_exporters', array(__CLASS__, 'register_exporter'));
        add_filter('wp_privacy_personal_data_erasers', array(__CLASS__, 'register_eraser'));
        add_filter('wp_get_privacy_policy_content', array(__CLASS__, 'add_policy_content'), 10, 2);
        add_action('delete_user', array(__CLASS__, 'anonymize_on_delete'), 10, 2);
    }
    
    public static function register_exporter($exporters) {
        $exporters['zephora_logistics'] = array('exporter_friendly_name' => 'Zephora Logistics', 'callback' => array(__CLASS__, 'export_data'));
        return $exporters;
    }
    
    public static function register_eraser($erasers) {
        $erasers['zephora_logistics'] = array('eraser_friendly_name' => 'Zephora Logistics', 'callback' => array(__CLASS__, 'erase_data'));
        return $erasers;
    }
    
    public static function export_data($email, $page = 1) {
        $user = get_user_by('email', $email);
        if (!$user) return array('data' => array(), 'done' => true);
        $items = array();
        $kyc = get_user_meta($user->ID, '_zls_kyc_data', true);
        if (!empty($kyc)) {
            $kyc_items = array();
            foreach ($kyc as $k => $v) { if (!empty($v)) $kyc_items[] = array('name' => ucwords(str_replace('_',' ', $k)), 'value' => $v); }
            if (!empty($kyc_items)) $items[] = array('group_id' => 'zls_kyc', 'group_label' => 'KYC Data', 'item_id' => "user-{$user->ID}", 'data' => $kyc_items);
        }
        return array('data' => $items, 'done' => true);
    }
    
    public static function erase_data($email, $page = 1) {
        $user = get_user_by('email', $email);
        if (!$user) return array('items_removed' => 0, 'items_retained' => 0, 'messages' => array(), 'done' => true);
        
        $kyc = get_user_meta($user->ID, '_zls_kyc_data', true);
        $removed = 0;
        if (!empty($kyc)) {
            $anon = array();
            foreach ($kyc as $k => $v) { $anon[$k] = '[ANONYMIZED]'; }
            update_user_meta($user->ID, '_zls_kyc_data', $anon);
            update_user_meta($user->ID, '_zls_kyc_status', 'anonymized');
            $removed = 1;
        }
        return array('items_removed' => $removed, 'items_retained' => 0, 'messages' => array($removed ? 'KYC data anonymized for compliance.' : 'No data found.'), 'done' => true);
    }
    
    public static function add_policy_content($content, $post_id) {
        $extra = '<h2>Zephora Logistics</h2><p>We collect KYC data (ID, phone, address) and shipment details for verification, customs clearance, and delivery. Data is retained for audit compliance. You may request export or anonymization via Tools → Personal Data.</p>';
        return $content . $extra;
    }
    
    public static function anonymize_on_delete($user_id, $reassign = null) {
        $kyc = get_user_meta($user_id, '_zls_kyc_data', true);
        if (!empty($kyc)) {
            $anon = array();
            foreach ($kyc as $k => $v) $anon[$k] = '[DELETED_USER]';
            update_user_meta($user_id, '_zls_kyc_data', $anon);
        }
        foreach (array('zls_ship', 'zls_buy') as $pt) {
            $posts = get_posts(array('post_type' => $pt, 'author' => $user_id, 'fields' => 'ids', 'numberposts' => -1));
            foreach ($posts as $pid) { update_post_meta($pid, '_zls_anonymized', '1'); if ($reassign) wp_update_post(array('ID' => $pid, 'post_author' => $reassign)); }
        }
    }
}