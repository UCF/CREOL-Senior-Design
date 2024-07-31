<?php
/*
Plugin Name: ACF Shortcodes for Senior Design Projects
Description: Shortcodes to display ACF fields for Senior Design Projects.
Version: 1.0
Author: Your Name
*/

// Shortcode to display ACF fields
function sd_project_acf_fields($atts) {
    ob_start();

    $semester = isset($_GET['semester']) ? sanitize_text_field($_GET['semester']) : '';

    $args = array(
        'post_type' => 'sd_project',
        'posts_per_page' => -1,
    );

    if ($semester) {
        $args['tax_query'] = array(
            array(
                'taxonomy' => 'sd_semester',
                'field' => 'slug',
                'terms' => $semester,
            ),
        );
    }

    $query = new WP_Query($args);

    if ($query->have_posts()) : while ($query->have_posts()) : $query->the_post();
        $short_report = get_field('short_report_file');
        $long_report = get_field('long_report_file');
        $presentation = get_field('presentation_slides_file');

        echo '<h1>' . get_the_title() . '</h1>';
        echo '<h2>' . get_field('subtitle') . '</h2>';
        echo '<p>Short Report: <a href="' . $short_report . '">Download</a></p>';
        echo '<p>Long Report: <a href="' . $long_report . '">Download</a></p>';
        echo '<p>Presentation Slides: <a href="' . $presentation . '">Download</a></p>';
        echo get_the_content();

        echo '<div class="card-box col-12">';
        // echo '<a href="' . $permalink . '">';
        echo '<div class="card custom-card">';
        echo '<div class="card-body">';
        echo '<h5 class="card-title mb-1" style="margin-top: 2px;">' . get_the_title($post) . '</h5>';
        echo '<div class="project-reports">';
        if($short_report || $long_report || $presentation_slides) {
            echo 'View: ';
            if($short_report)
                echo '<a href="' . esc_url($short_report) . '" target="_blank">Short Report</a> | ';
            if($long_report)
                echo '<a href="' . esc_url($long_report) . '" target="_blank">Long Report</a> | ';
            if($presentation_slides)
                echo '<a href="' . esc_url($presentation_slides) . '" target="_blank">Presentation Slides</a>';
        } 
        echo '</div>';
        echo '</div>';
        echo '</div>';
        // echo '</a>';
        echo '</div>';

        echo '<div class="card-box col-12">';
        // echo '<a href="' . $permalink . '">';
        echo '<div class="card custom-card">';
        echo '<div class="card-body">';
        echo '<h5 class="card-title mb-1" style="margin-top: 2px;">' . get_the_title($post) . '</h5>';
        echo '<div class="project-reports">';
        if($short_report || $long_report || $presentation_slides) {
            echo 'View: ';
            if($short_report)
                echo '<a href="' . esc_url($short_report) . '" target="_blank">Short Report</a> | ';
            if($long_report)
                echo '<a href="' . esc_url($long_report) . '" target="_blank">Long Report</a> | ';
            if($presentation_slides)
                echo '<a href="' . esc_url($presentation_slides) . '" target="_blank">Presentation Slides</a>';
        } 
        echo '</div>';
        echo '</div>';
        echo '</div>';
        // echo '</a>';
        echo '</div>';
    endwhile; endif;

    wp_reset_postdata();

    return ob_get_clean();
}
add_shortcode('sd_project_fields', 'sd_project_acf_fields');

?>