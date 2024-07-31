<?php
// Shortcode to display projects with filter, search, and pagination
function sd_project_display($atts) {
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

    echo '<form method="GET">';
    echo '<label for="semester">Semester:</label>';
    echo '<select name="semester" id="semester">';
    echo '<option value="">All Semesters</option>';
    foreach ($terms as $term) {
        $selected = ($semester == $term->slug) ? 'selected="selected"' : '';
        echo '<option value="' . esc_attr($term->slug) . '" ' . $selected . '>' . esc_html($term->name) . '</option>';
    }
    echo '</select>';

    echo '<label for="search">Search:</label>';
    echo '<input type="text" name="search" id="search" value="' . esc_attr($search) . '">';

    echo '<input type="submit" value="Filter">';
    echo '</form>';

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

    echo '<style>
        .custom-card {
            border-radius: 12px;
            border-style: none;
            box-shadow: 0 0 10px 0 rgba(0,0,0,.15);
            margin-bottom: 20px;
            padding: 20px;
            transition: box-shadow 0.3s ease-in-out;
        }
        .custom-card:hover {
            box-shadow: 0 0 10px 2px rgba(0,0,0,.15);
        }
    </style>';

    if ($query->have_posts()) :
        echo '<div class="sd-projects">';
        while ($query->have_posts()) : $query->the_post();
            $short_report = get_field('short_report_file');
            $long_report = get_field('long_report_file');
            $presentation = get_field('presentation_slides_file');
            $contributors = get_field('project_contributors');

            echo '<div class="card-box col-12">';
            echo '<div class="card custom-card">';
            echo '    <div class="card-body">';
            echo '        <h5 class="card-title my-1">' . get_the_title() . '</h5>';
            if ($contributors)
                echo '        <p>' . esc_html($contributors) . '</p>';
            if ($short_report || $long_report || $presentation) {
                echo '        <p><strong>View: </strong>';
                if ($short_report)
                    echo '            <a href="' . esc_url($short_report) . '">Short Report</a> | ';
                if ($long_report)
                    echo '            <a href="' . esc_url($long_report) . '">Short Report</a> | ';
                if ($presentation)
                    echo '            <a href="' . esc_url($presentation) . '">Short Report</a>';
            };
            echo '        </p>';
            echo '    </div>';
            echo '</div>';
            echo '</div>';

            // echo '<h1>' . get_the_title() . '</h1>';
            // echo '<p>Contributors: ' . esc_html($contributors) . '</p>';
            // echo '<p>Short Report: <a href="' . esc_url($short_report) . '">Download</a></p>';
            // echo '<p>Long Report: <a href="' . esc_url($long_report) . '">Download</a></p>';
            // echo '<p>Presentation Slides: <a href="' . esc_url($presentation) . '">Download</a></p>';
            echo get_the_content();
        endwhile;
        echo '</div>';

        // Pagination
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

    return ob_get_clean();
}
add_shortcode('sd_project_display', 'sd_project_display');
