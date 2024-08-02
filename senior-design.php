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

require_once 'includes/senior-design-layout.php';
require_once 'includes/senior-design-v2.php';

add_shortcode( 'senior-design', 'senior_design_display');
add_shortcode('sd_project_display', 'sd_project_display');