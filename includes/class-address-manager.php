<?php
class ZLS_Address_Manager {
    public static function init() {
        add_action('admin_init', [__CLASS__, 'register_settings']);
        add_filter('zls_get_us_addresses', [__CLASS__, 'get_addresses']);
    }

    public static function register_settings() {
        register_setting('zls_settings', 'zls_us_addresses', [
            'type' => 'array',
            'sanitize_callback' => [__CLASS__, 'sanitize_addresses'],
            'default' => [
                [
                    'id' => 'us_warehouse_1',
                    'label' => 'Primary US Warehouse',
                    'address_line1' => '123 Logistics Blvd',
                    'address_line2' => 'Suite 100',
                    'city' => 'Newark',
                    'state' => 'NJ',
                    'postal_code' => '07102',
                    'country' => 'USA',
                    'contact_phone' => '+1 (555) 123-4567',
                    'is_active' => true,
                ]
            ]
        ]);
    }

    public static function sanitize_addresses($addresses) {
        $sanitized = [];
        foreach ((array)$addresses as $addr) {
            $sanitized[] = [
                'id' => sanitize_text_field($addr['id'] ?? uniqid('us_')),
                'label' => sanitize_text_field($addr['label'] ?? ''),
                'address_line1' => sanitize_text_field($addr['address_line1'] ?? ''),
                'address_line2' => sanitize_text_field($addr['address_line2'] ?? ''),
                'city' => sanitize_text_field($addr['city'] ?? ''),
                'state' => sanitize_text_field($addr['state'] ?? ''),
                'postal_code' => sanitize_text_field($addr['postal_code'] ?? ''),
                'country' => sanitize_text_field($addr['country'] ?? 'USA'),
                'contact_phone' => preg_replace('/[^0-9+\-\s()]/', '', $addr['contact_phone'] ?? ''),
                'is_active' => !empty($addr['is_active']),
            ];
        }
        return $sanitized;
    }

    public static function get_addresses($addresses = null) {
        $all = get_option('zls_us_addresses', []);
        // ✅ FIX: Traditional closure for PHP 7.2 compatibility
        return array_filter($all, function($a) {
            return !empty($a['is_active']);
        });
    }

    public static function get_primary_address() {
        $active = self::get_addresses();
        return reset($active) ?: null;
    }
}