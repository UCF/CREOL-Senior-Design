<?php
// AJAX handler for project search and filter
function sd_project_filter() {
    $semester = isset($_POST['semester']) ? sanitize_text_field($_POST['semester']) : '';
    $search = isset($_POST['search']) ? sanitize_text_field($_POST['search']) : '';
    $paged = isset($_POST['paged']) ? sanitize_text_field($_POST['paged']) : 1;

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

            echo '<h1>' . get_the_title() . '</h1>';
            echo '<p>Contributors: ' . get_field('project_contributors') . '</p>';
            echo '<p>Short Report: <a href="' . esc_url($short_report) . '">Download</a></p>';
            echo '<p>Long Report: <a href="' . esc_url($long_report) . '">Download</a></p>';
            echo '<p>Presentation Slides: <a href="' . esc_url($presentation) . '">Download</a></p>';
            echo get_the_content();
        endwhile;

        $big = 999999999;
        echo paginate_links(array(
            'base' => str_replace($big, '%#%', esc_url(get_pagenum_link($big))),
            'format' => '?paged=%#%',
            'current' => max(1, $paged),
            'total' => $query->max_num_pages
        ));

    else:
        echo '<p>No projects found.</p>';
    endif;

    wp_reset_postdata();

    die();
}
add_action('wp_ajax_sd_project_filter', 'sd_project_filter');
add_action('wp_ajax_nopriv_sd_project_filter', 'sd_project_filter');
