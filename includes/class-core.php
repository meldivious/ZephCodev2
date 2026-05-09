<?php
class ZLS_Core {
    private static $initialized = false;
    
    public static function init() {
        if (self::$initialized) return;
        self::$initialized = true;
        
        add_action('init', array(__CLASS__, 'register_cpts'), 5);
        add_action('admin_init', array(__CLASS__, 'redirect_non_admins'), 1);
        add_action('wp_enqueue_scripts', array(__CLASS__, 'enqueue_frontend'));
        add_action('admin_enqueue_scripts', array(__CLASS__, 'enqueue_admin'));
        
        // ✅ Register Shortcode EARLY
        add_shortcode('zls_dashboard', array(__CLASS__, 'render_dashboard'));
        
        // ✅ Initialize Settings UI (hooks into admin_menu)
        if (class_exists('ZLS_Settings')) ZLS_Settings::get_instance()->init();
        
        if (class_exists('ZLS_GDPR')) ZLS_GDPR::init();
        if (class_exists('ZLS_Module_Loader')) ZLS_Module_Loader::get_instance()->init();
        
        // Form handlers
        add_action('admin_post_zls_ship_submit', array(__CLASS__, 'handle_ship_submit'));
        add_action('admin_post_nopriv_zls_ship_submit', array(__CLASS__, 'handle_ship_submit'));
        add_action('admin_post_zls_buy_submit', array(__CLASS__, 'handle_buy_submit'));
        add_action('admin_post_nopriv_zls_buy_submit', array(__CLASS__, 'handle_buy_submit'));
        add_action('admin_post_zls_confirm_payment', array(__CLASS__, 'handle_payment_confirm'));
        add_action('admin_post_nopriv_zls_confirm_payment', array(__CLASS__, 'handle_payment_confirm'));
    }
    
    public static function register_cpts() {
        register_post_type('zls_ship', array('labels' => array('name' => 'Ship Requests', 'singular_name' => 'Ship Request'), 'public' => false, 'show_ui' => true, 'show_in_menu' => 'zephora-logistics', 'supports' => array('title', 'author'), 'menu_icon' => 'dashicons-cart', 'map_meta_cap' => true));
        register_post_type('zls_buy', array('labels' => array('name' => 'Buy Requests', 'singular_name' => 'Buy Request'), 'public' => false, 'show_ui' => true, 'show_in_menu' => 'zephora-logistics', 'supports' => array('title', 'author'), 'menu_icon' => 'dashicons-store', 'map_meta_cap' => true));
    }
    
    public static function redirect_non_admins() {
        if (is_admin() && !current_user_can('administrator') && !wp_doing_ajax()) {
            wp_redirect(home_url('/')); exit;
        }
    }
    
    public static function enqueue_frontend() {
        if (is_admin()) return;
        wp_enqueue_style('zls-frontend', ZLS_PLUGIN_URL . 'assets/css/frontend.css', array(), ZLS_VERSION);
        wp_enqueue_script('zls-frontend', ZLS_PLUGIN_URL . 'assets/js/frontend.js', array('jquery'), ZLS_VERSION, true);
        wp_localize_script('zls-frontend', 'zlsConfig', array('ajax_url' => admin_url('admin-ajax.php'), 'nonce' => wp_create_nonce('zls_ajax')));
    }
    
    public static function enqueue_admin() {
        wp_enqueue_style('zls-admin', ZLS_PLUGIN_URL . 'assets/css/admin.css', array(), ZLS_VERSION);
        wp_enqueue_script('zls-admin', ZLS_PLUGIN_URL . 'assets/js/admin.js', array('jquery'), ZLS_VERSION, true);
    }
    
    // ✅ SHORTCODE: GUARANTEED RETURN
    public static function render_dashboard() {
        if (!class_exists('ZLS_Dashboard')) return '⚠️ Dashboard component missing.';
        return ZLS_Dashboard::render();
    }
    
    public static function handle_ship_submit() {
        if (!class_exists('ZLS_Ship_For_Me')) wp_die('Ship module missing');
        $result = ZLS_Ship_For_Me::submit();
        if (is_wp_error($result)) wp_die($result->get_error_message());
        wp_safe_redirect(wp_get_referer() ?: home_url()); exit;
    }
    public static function handle_buy_submit() {
        if (!class_exists('ZLS_Buy_For_Me')) wp_die('Buy module missing');
        $result = ZLS_Buy_For_Me::submit();
        if (is_wp_error($result)) wp_die($result->get_error_message());
        wp_safe_redirect(wp_get_referer() ?: home_url()); exit;
    }
    public static function handle_payment_confirm() {
        if (!class_exists('ZLS_Payments')) wp_die('Payments module missing');
        $result = ZLS_Payments::handle_manual_payment(absint($_POST['request_id'] ?? 0));
        if (is_wp_error($result)) wp_die($result->get_error_message());
        wp_safe_redirect(home_url('/')); exit;
    }
}