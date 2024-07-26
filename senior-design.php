<?php
/*
Plugin Name: Senior Design
Description: {{Short project description here}}
Version: 0.0.0
Author: UCF Web Communications
License: GPL3
GitHub Plugin URI: UCF/CREOL-Senior-Design
*/

if ( ! defined( 'WPINC' ) ) {
    die;
}

require_once 'includes/senior-design-layout.php';
require_once 'includes/revised-sd-plugin.php';

add_shortcode( 'senior-design', 'senior_design_display');
add_shortcode( 'new-senior-design', 'new_senior_design_display');