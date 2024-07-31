<?php
/*
Plugin Name: Senior Design Projects
Description: Displays Senior Design projects with search and filter functionality.
Version: 1.0
Author: Your Name
License: GPL3
*/

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Include required files
include(plugin_dir_path(__FILE__) . 'includes/cpt.php');
include(plugin_dir_path(__FILE__) . 'includes/taxonomy.php');
include(plugin_dir_path(__FILE__) . 'includes/shortcode.php');
include(plugin_dir_path(__FILE__) . 'includes/ajax-handler.php');

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

function sd_enqueue_scripts() {
    wp_enqueue_script('sd-ajax', plugin_dir_url(__FILE__) . 'assets/js/sd-ajax.js', array('jquery'), null, true);

    wp_localize_script('sd-ajax', 'sd_ajax_obj', array(
        'ajax_url' => admin_url('admin-ajax.php'),
    ));
}
add_action('wp_enqueue_scripts', 'sd_enqueue_scripts');