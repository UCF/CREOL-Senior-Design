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

require_once 'includes/people-layout.php';

add_shortcode( 'senior-design', 'people_display');