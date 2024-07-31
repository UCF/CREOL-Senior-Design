<?php
// Register Custom Taxonomy
function sd_register_taxonomy() {
    if (taxonomy_exists('sd_semester')) return;
    
    $args = array(
        'labels' => array(
            'name' => 'Semesters',
            'singular_name' => 'Semester',
        ),
        'public' => true,
        'hierarchical' => true,
        'rewrite' => array('slug' => 'semesters'),
    );
    register_taxonomy('sd_semester', array('sd_project'), $args);
}
add_action('init', 'sd_register_taxonomy');
