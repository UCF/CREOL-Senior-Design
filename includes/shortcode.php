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

    if ($query->have_posts()) {
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
                    echo '            <a href="' . esc_url($short_report) . '" target="_blank">Short Report</a> | ';
                if ($long_report)
                    echo '            <a href="' . esc_url($long_report) . '" target="_blank">Long Report</a> | ';
                if ($presentation)
                    echo '            <a href="' . esc_url($presentation) . '" target="_blank">Presentation</a>';
                echo '        </p>';
            };
            echo '    </div>';
            echo '</div>';
            echo '</div>';
        endwhile;
        echo '</div>';

        // Pagination controls
        $total_pages = $query->max_num_pages;
        if ($total_pages > 1) {
            echo '<nav aria-label="Page navigation">';
            echo '<ul class="pagination justify-content-center">';

            $base_link = esc_url_raw(remove_query_arg(['paged'], get_pagenum_link(1)));
            $current_page = max(1, get_query_var('paged'));

            $link_with_params = esc_url_raw(add_query_arg(['semester' => $semester, 'search' => $search], $base_link));

            if ($current_page > 1) {
                echo '<li class="page-item"><a class="page-link" href="' . esc_url_raw(add_query_arg(['paged' => $current_page - 1], $link_with_params)) . '" aria-label="Previous"><span aria-hidden="true">&laquo;</span><span class="sr-only">Previous</span></a></li>';
            }

            for ($i = 1; $i <= $total_pages; $i++) {
                $page_link = esc_url_raw(add_query_arg(['paged' => $i], $link_with_params));
                if ($i == $current_page) {
                    echo '<li class="page-item active"><a class="page-link" href="#">' . $i . '</a></li>';
                } else {
                    echo '<li class="page-item"><a class="page-link" href="' . $page_link . '">' . $i . '</a></li>';
                }
            }

            if ($current_page < $total_pages) {
                echo '<li class="page-item"><a class="page-link" href="' . esc_url_raw(add_query_arg(['paged' => $current_page + 1], $link_with_params)) . '" aria-label="Next"><span aria-hidden="true">&raquo;</span><span class="sr-only">Next</span></a></li>';
            }

            echo '</ul></nav>';
        }

        wp_reset_postdata();
    } else {
        echo '<p>No projects found.</p>';
    }

    return ob_get_clean();
}
add_shortcode('sd_project_display', 'sd_project_display');
