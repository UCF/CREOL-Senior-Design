<?php
// Register Custom Post Type
function sd_register_post_type() {
    $args = array(
        'labels' => array(
            'name' => 'Senior Design Projects',
            'singular_name' => 'Senior Design Project',
        ),
        'public' => true,
        'has_archive' => true,
        'supports' => array('title', 'editor', 'custom-fields'),
        'rewrite' => array('slug' => 'senior-design-projects'),
    );
    register_post_type('sd_project', $args);
}
add_action('init', 'sd_register_post_type');
