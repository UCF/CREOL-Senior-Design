<?php

// Shortcode to display projects without filter, search, and pagination
function sd_project_display($atts) {
    ob_start();

    $args = array(
        'post_type' => 'sd_project',
        'posts_per_page' => -1, // Display all posts
        'orderby' => 'title',
        'order' => 'ASC',
    );

    $query = new WP_Query($args);

    ob_start();

    // Group projects by their semester term
    $grouped_projects = array();

    if ($query->have_posts()) {
        while ($query->have_posts()) {
            $query->the_post();
            $post_id = get_the_ID();
            $terms = get_the_terms($post_id, 'sd_semester');

            if ($terms && !is_wp_error($terms)) {
                foreach ($terms as $term) {
                    $grouped_projects[$term->slug][] = $post_id;
                }
            } else {
                $grouped_projects['uncategorized'][] = $post_id;
            }
        }
        wp_reset_postdata();
    }

    // Define the order of terms within a year
    $term_order = array('spring' => 1, 'summer' => 2, 'fall' => 3);

    // Sort the grouped projects based on year and term order
    uksort($grouped_projects, function($a, $b) use ($term_order) {
        // Extract year and term from the slug
        preg_match('/(spring|summer|fall)-(\d{4})/', $a, $matches_a);
        preg_match('/(spring|summer|fall)-(\d{4})/', $b, $matches_b);

        if ($matches_a && $matches_b) {
            $term_a = $matches_a[1];
            $year_a = (int)$matches_a[2];
            $term_b = $matches_b[1];
            $year_b = (int)$matches_b[2];

            // Compare years first
            if ($year_a !== $year_b) {
                return $year_b - $year_a; // Descending order for years
            }

            // If years are the same, compare terms
            return $term_order[$term_a] - $term_order[$term_b];
        }

        // Handle cases where the slug doesn't match the pattern
        return strcmp($a, $b);
    });

    // Now, output each semester group:
    foreach ($grouped_projects as $semester_slug => $posts) {
        if ('uncategorized' === $semester_slug) {
            $semester_name = 'Uncategorized';
        } else {
            $term_obj = get_term_by('slug', $semester_slug, 'sd_semester');
            $semester_name = $term_obj ? $term_obj->name : $semester_slug;
        }

        echo '<h2>' . esc_html($semester_name) . ' Projects</h2>';
        echo '<div class="semester-projects">';

        foreach ($posts as $post_id) {
            $post = get_post($post_id);
            setup_postdata($post);

            $short_report = get_field('short_report_file', $post_id);
            $long_report = get_field('long_report_file', $post_id);
            $presentation = get_field('presentation_slides_file', $post_id);
            $contributors = get_field('project_contributors', $post_id);
            $sponsor = get_field('sponsor', $post_id);

            echo '<div class="card-box col-12">';
            echo '<div class="card sd-card">';
            echo '    <div class="card-body">';
            echo '        <h5 class="card-title my-3">' . get_the_title($post_id) . '</h5>';
            if ($sponsor)
                echo '        <p class="my-1"><strong>Sponsor: </strong> ' . esc_html($sponsor) . ' </p>';
            if ($contributors)
                echo '        <p class="my-1"><strong>Members: </strong>' . esc_html($contributors) . '</p>';
            if ($short_report || $long_report || $presentation) {
                echo '        <p class="my-1"><strong>View: </strong>';
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
        }
        wp_reset_postdata();
        echo '</div>';
    }

    return ob_get_clean();
}
?>