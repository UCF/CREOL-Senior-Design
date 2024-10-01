<?php
/*
Plugin Name: Senior Design
Description: Displays Senior Design projects with search and filter functionality.
Version: 2.0.0
Author: UCF Web Communications
License: GPL3
GitHub Plugin URI: UCF/CREOL-Senior-Design
*/

if ( ! defined( 'WPINC' ) ) {
    die;
}

function select2_test_enqueue_scripts() {
    // Enqueue Select2 CSS
    wp_enqueue_style('select2-css', 'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css');

    // Enqueue Select2 JS
    wp_enqueue_script('select2-js', 'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js', array('jquery'), null, true);

    // Enqueue custom script to initialize Select2
    wp_enqueue_script('select2-test-init', plugin_dir_url(__FILE__) . 'includes/select2-test-init.js', array('select2-js'), null, true);
}
add_action('wp_enqueue_scripts', 'select2_test_enqueue_scripts');

function select2_test_shortcode() {
    ob_start();
    ?>
    <div class="form-check">
        <label for="testSelect2">Label</label>
        <select class="form-control" id="testSelect2" style="width: 100%;">
            <option value="1">Option 1</option>
            <option value="2">Option 2</option>
            <option value="3">Option 3</option>
        </select>
    </div>
    <?php
    return ob_get_clean();
}

// require_once 'includes/senior-design-layout.php';
require_once 'includes/senior-design-v2.php';
require_once 'includes/csv-to-cpt.php';


// add_shortcode( 'senior-design', 'senior_design_display');
add_shortcode('sd_project_display', 'sd_project_display');
add_shortcode('select2_test', 'select2_test_shortcode');