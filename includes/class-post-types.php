<?php
/**
 * Zephora Logistic Systems - Custom Post Types Registration
 */

add_action('init', 'zls_register_logistics_post_types');
function zls_register_logistics_post_types() {
    
    $common_args = array(
        'public'                => false,
        'publicly_queryable'    => false,
        'exclude_from_search'   => true,
        'show_ui'               => true,
        'show_in_menu'          => false,  // Hide from default menu (we use custom)
        'show_in_admin_bar'     => true,
        'capability_type'       => 'post',
        'map_meta_cap'          => true,   // Critical for meta capabilities
        'hierarchical'          => false,
        'supports'              => array('title', 'author', 'custom-fields'),
        'has_archive'           => false,
        'rewrite'               => false,
        'query_var'             => false,
        'can_export'            => true,
        'delete_with_user'      => true,
        'show_in_rest'          => false,
    );
    
    // Ship Requests
    register_post_type('zls_ship', array_merge($common_args, array(
        'labels' => array(
            'name' => 'Ship Requests',
            'singular_name' => 'Ship Request',
            'add_new_item' => 'Add New Ship Request',
            'edit_item' => 'Edit Ship Request',
        ),
        'menu_icon' => 'dashicons-airplane',
    )));
    
    // Buy Requests
    register_post_type('zls_buy', array_merge($common_args, array(
        'labels' => array(
            'name' => 'Buy Requests',
            'singular_name' => 'Buy Request',
            'add_new_item' => 'Add New Buy Request',
            'edit_item' => 'Edit Buy Request',
        ),
        'menu_icon' => 'dashicons-cart',
    )));
}

// ✅ FORCE-GRANT ADMIN ACCESS TO OUR CPTS
add_filter('user_has_cap', function($allcaps, $caps, $args, $user) {
    if (!isset($args[0]) || !in_array($args[0], ['edit_post', 'edit_posts', 'delete_post'])) {
        return $allcaps;
    }
    
    // If user is admin, grant access to our CPTs
    if (in_array('manage_options', $allcaps) && !empty($args[2])) {
        $post = get_post($args[2]);
        if ($post && in_array($post->post_type, ['zls_ship', 'zls_buy'])) {
            $allcaps[$args[0]] = true;
        }
    }
    return $allcaps;
}, 10, 4);

// ✅ MIGRATE OLD CPT NAME (one-time)
add_action('admin_init', 'zls_migrate_old_cpt');
function zls_migrate_old_cpt() {
    $old_posts = get_posts(array('post_type' => 'zls_ship_for_me', 'numberposts' => 1, 'fields' => 'ids'));
    if (!empty($old_posts)) {
        foreach ($old_posts as $post_id) {
            wp_update_post(array('ID' => $post_id, 'post_type' => 'zls_ship'));
        }
    }
}