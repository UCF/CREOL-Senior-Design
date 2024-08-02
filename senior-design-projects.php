<?php
/*
Plugin Name: Senior Design V2
Description: Displays Senior Design projects with search and filter functionality.
Version: 0.0.0
Author: UCF Web Communications
License: GPL3
GitHub Plugin URI: UCF/CREOL-Senior-Design
*/

// Exit if accessed directly
if (!defined('ABSPATH')) {
    die;
}

// Include required files
include(plugin_dir_path(__FILE__) . 'includes/cpt.php');
include(plugin_dir_path(__FILE__) . 'includes/taxonomy.php');
include(plugin_dir_path(__FILE__) . 'includes/shortcode.php');

// Register hooks
register_activation_hook(__FILE__, 'sd_activate_plugin');
register_deactivation_hook(__FILE__, 'sd_deactivate_plugin');

function sd_activate_plugin() {
    // Activation code here
    sd_register_post_type();
    sd_register_taxonomy();
    flush_rewrite_rules();
}

function sd_deactivate_plugin() {
    // Deactivation code here
    flush_rewrite_rules();
}
add_action('wp_enqueue_scripts', 'sd_enqueue_scripts');