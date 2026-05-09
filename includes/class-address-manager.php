<?php
class ZLS_Address_Manager {
    public static function init() {
        add_action('admin_init', [__CLASS__, 'register_settings']);
        add_filter('zls_get_warehouse_address', [__CLASS__, 'get_address']);
    }

    public static function register_settings() {
        register_setting('zls_settings_group', 'zls_warehouse_address', [
            'type' => 'array',
            'sanitize_callback' => [__CLASS__, 'sanitize_address'],
            'default' => [
                'address_line1' => '',
                'address_line2' => '',
                'city' => '',
                'state' => '',
                'postal_code' => '',
                'country' => 'USA',
                'contact_phone' => '',
            ]
        ]);
    }

    public static function sanitize_address($address) {
        return [
            'address_line1' => sanitize_text_field($address['address_line1'] ?? ''),
            'address_line2' => sanitize_text_field($address['address_line2'] ?? ''),
            'city' => sanitize_text_field($address['city'] ?? ''),
            'state' => sanitize_text_field($address['state'] ?? ''),
            'postal_code' => sanitize_text_field($address['postal_code'] ?? ''),
            'country' => sanitize_text_field($address['country'] ?? 'USA'),
            'contact_phone' => preg_replace('/[^0-9+\-\s()]/', '', $address['contact_phone'] ?? ''),
        ];
    }

    public static function get_address($address = null) {
        return get_option('zls_warehouse_address', []);
    }

    public static function get_primary_address() {
        return self::get_address();
    }
}