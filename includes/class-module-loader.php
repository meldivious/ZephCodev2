<?php
/**
 * Zephora Logistic Systems - Addon/Module Loader
 * Discovers plugins in /wp-content/plugins/zls-addons/
 */
class ZLS_Module_Loader {
    private static $instance = null;
    private $modules = array();
    
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public function init() {
        add_action('plugins_loaded', array($this, 'load_modules'), 20);
    }
    
    public function load_modules() {
        $addon_dir = WP_PLUGIN_DIR . '/zls-addons';
        if (!is_dir($addon_dir)) {
            wp_mkdir_p($addon_dir);
            file_put_contents($addon_dir . '/.htaccess', "Deny from all");
            return;
        }
        
        $addons = scandir($addon_dir);
        if (!is_array($addons)) return;
        
        foreach ($addons as $addon) {
            if ($addon === '.' || $addon === '..') continue;
            $addon_path = $addon_dir . '/' . $addon;
            if (!is_dir($addon_path)) continue;
            
            $main_file = $addon_path . '/' . $addon . '.php';
            if (!file_exists($main_file)) continue;
            
            $plugin_data = get_plugin_data($main_file, false, false);
            if (empty($plugin_data['Name'])) continue;
            if (strpos(file_get_contents($main_file), 'Zephora Logistic Systems Addon') === false) continue;
            
            require_once $main_file;
            $this->modules[$addon] = array('name' => $plugin_data['Name'], 'version' => $plugin_data['Version'], 'path' => $addon_path, 'loaded' => true);
            if (class_exists('ZLS_Audit_Logger')) {
                ZLS_Audit_Logger::log('MODULE_LOADED', array('module' => $addon, 'name' => $plugin_data['Name']));
            }
        }
        do_action('zls_modules_loaded', $this->modules);
    }
    
    public function get_modules() { return $this->modules; }
    public function is_module_active($slug) { return isset($this->modules[$slug]) && $this->modules[$slug]['loaded']; }
    
    public static function register_capability($cap, $callback) {
        add_filter('zls_capabilities', function($caps) use ($cap, $callback) { $caps[$cap] = $callback; return $caps; });
    }
    public static function call_capability($cap, $args = array()) {
        $caps = apply_filters('zls_capabilities', array());
        return isset($caps[$cap]) && is_callable($caps[$cap]) ? call_user_func_array($caps[$cap], $args) : null;
    }
}