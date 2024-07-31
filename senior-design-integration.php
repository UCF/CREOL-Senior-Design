<?php
/*
Plugin Name: Senior Design Integration
Description: Integrates ACF fields, CPT, and taxonomies for Senior Design Projects.
Version: 1.0
Author: UCF Web Communications
License: GPL3
GitHub Plugin URI: UCF/CREOL-Senior-Design
*/

if ( ! defined( 'WPINC' ) ) {
    die;
}

// Register Custom Post Type
function ucf_sd_project_post_type() {
    if (post_type_exists('sd_project')) return;

    $args = array(
        'labels' => array(
            'name' => __('Senior Design Projects', 'ucf'),
            'singular_name' => __('Senior Design Project', 'ucf'),
        ),
        'public' => true,
        'has_archive' => true,
        'supports' => array('title', 'editor', 'custom-fields'),
        'rewrite' => array('slug' => 'senior-design-projects'),
    );
    register_post_type('sd_project', $args);
}
add_action('init', 'ucf_sd_project_post_type');

// Register Custom Taxonomy
function ucf_sd_project_taxonomy() {
    if (taxonomy_exists('sd_semester')) return;

    $args = array(
        'labels' => array(
            'name' => __('Semesters', 'ucf'),
            'singular_name' => __('Semester', 'ucf'),
        ),
        'public' => true,
        'hierarchical' => true,
        'rewrite' => array('slug' => 'semesters'),
    );
    register_taxonomy('sd_semester', array('sd_project'), $args);
}
add_action('init', 'ucf_sd_project_taxonomy');

// Shortcode to display projects with filter, search, and pagination
function ucf_sd_project_display($atts) {
    ob_start();

    // Get query variables
    $semester = isset($_GET['semester']) ? sanitize_text_field($_GET['semester']) : '';
    $search = isset($_GET['search']) ? sanitize_text_field($_GET['search']) : '';
    $paged = (get_query_var('paged')) ? get_query_var('paged') : 1;

    // Display semester dropdown
    $terms = get_terms(array(
        'taxonomy' => 'sd_semester',
        'hide_empty' => false,
    ));

    ?>
    <form method="GET">
        <label for="semester"><?php _e('Semester:', 'ucf'); ?></label>
        <select name="semester" id="semester">
            <option value=""><?php _e('All Semesters', 'ucf'); ?></option>
            <?php foreach ($terms as $term) : ?>
                <option value="<?php echo esc_attr($term->slug); ?>" <?php selected($semester, $term->slug); ?>>
                    <?php echo esc_html($term->name); ?>
                </option>
            <?php endforeach; ?>
        </select>

        <label for="search"><?php _e('Search:', 'ucf'); ?></label>
        <input type="text" name="search" id="search" value="<?php echo esc_attr($search); ?>">

        <input type="submit" value="<?php _e('Filter', 'ucf'); ?>">
    </form>
    <?php

    // Query arguments
    $args = array(
        'post_type' => 'sd_project',
        'posts_per_page' => 10,
        'paged' => $paged,
        's' => $search,
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

    if ($query->have_posts()) :
        while ($query->have_posts()) : $query->the_post();
            $short_report = get_field('short_report_file');
            $long_report = get_field('long_report_file');
            $presentation = get_field('presentation_slides_file');

            echo '<h1>' . esc_html(get_the_title()) . '</h1>';
            echo '<h2>' . esc_html(get_field('subtitle')) . '</h2>';
            if ($short_report) {
                echo '<p>' . __('Short Report: ', 'ucf') . '<a href="' . esc_url($short_report) . '">' . __('Download', 'ucf') . '</a></p>';
            }
            if ($long_report) {
                echo '<p>' . __('Long Report: ', 'ucf') . '<a href="' . esc_url($long_report) . '">' . __('Download', 'ucf') . '</a></p>';
            }
            if ($presentation) {
                echo '<p>' . __('Presentation Slides: ', 'ucf') . '<a href="' . esc_url($presentation) . '">' . __('Download', 'ucf') . '</a></p>';
            }
            echo apply_filters('the_content', get_the_content());
        endwhile;

        // Pagination
        $big = 999999999; // need an unlikely integer
        echo paginate_links(array(
            'base' => str_replace($big, '%#%', esc_url(get_pagenum_link($big))),
            'format' => '?paged=%#%',
            'current' => max(1, $paged),
            'total' => $query->max_num_pages
        ));

    else:
        echo '<p>' . __('No projects found.', 'ucf') . '</p>';
    endif;

    wp_reset_postdata();

    return ob_get_clean();
}
add_shortcode('sd_project_display', 'ucf_sd_project_display');