<?php

// Shortcode to display projects with multiple filters
function sd_project_display($atts) {
    ob_start();
    
    // Get query variables
    $selected_semesters = isset($_GET['semesters']) ? array_map('sanitize_text_field', $_GET['semesters']) : array();
    $selected_project_types = isset($_GET['project_types']) ? array_map('sanitize_text_field', $_GET['project_types']) : array();
    $search = isset($_GET['search']) ? sanitize_text_field($_GET['search']) : '';
    $paged = (get_query_var('paged')) ? get_query_var('paged') : 1;

    // Query arguments
    $args = array(
        'post_type' => 'sd_project',
        'posts_per_page' => 10,
        'paged' => $paged,
        's' => $search,
    );

    // Add taxonomy query for semesters if any selected
    if (!empty($selected_semesters)) {
        $args['tax_query'][] = array(
            'taxonomy' => 'sd_semester',
            'field' => 'slug',
            'terms' => $selected_semesters,
            'operator' => 'IN' // Allows multiple terms
        );
    }

    // Add taxonomy query for project types if any selected
    if (!empty($selected_project_types)) {
        $args['tax_query'][] = array(
            'taxonomy' => 'project_type',
            'field' => 'slug',
            'terms' => $selected_project_types,
            'operator' => 'IN'
        );
    }

    // Optional: Add meta query for custom fields, like project_contributors
    if ($search) {
        $args['meta_query'] = array(
            'relation' => 'OR',
            array(
                'key' => 'project_contributors',
                'value' => $search,
                'compare' => 'LIKE'
            )
        );
    }
    
    // Add CSS for cards and filter form
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
        .filter-section {
            margin-bottom: 20px;
        }
    </style>';
    
    $query = new WP_Query($args);

    // Get terms for semester and project type
    $semesters = get_terms(array('taxonomy' => 'sd_semester', 'hide_empty' => false));
    $project_types = get_terms(array('taxonomy' => 'project_type', 'hide_empty' => false));

    // Display filter form with checkboxes
    echo '<form class="form-inline mb-4" method="GET" action="" id="filter-form">';

    echo '<div class="filter-section">';
    echo '<h5>Select Semesters:</h5>';
    foreach ($semesters as $semester) {
        $checked = in_array($semester->slug, $selected_semesters) ? 'checked' : '';
        echo '<div class="form-check">';
        echo '<input type="checkbox" name="semesters[]" value="' . esc_attr($semester->slug) . '" ' . $checked . '> ' . esc_html($semester->name);
        echo '</div>';
    }
    echo '</div>';

    echo '<div class="filter-section">';
    echo '<h5>Select Project Types:</h5>';
    foreach ($project_types as $project_type) {
        $checked = in_array($project_type->slug, $selected_project_types) ? 'checked' : '';
        echo '<div class="form-check">';
        echo '<input type="checkbox" name="project_types[]" value="' . esc_attr($project_type->slug) . '" ' . $checked . '> ' . esc_html($project_type->name);
        echo '</div>';
    }
    echo '</div>';

    // Search field
    echo '<div class="filter-section">';
    echo '<input type="text" name="search" class="form-control" placeholder="Search by title or contributor" value="' . esc_attr($search) . '">';
    echo '</div>';

    // Submit button
    echo '<button type="submit" class="btn btn-primary">Apply Filters</button>';

    echo '</form>';

    // Display projects
    echo '<div id="sd-projects">';
    if ($query->have_posts()) {
        while ($query->have_posts()) : $query->the_post();
            $contributors = get_field('project_contributors');
            echo '<div class="card custom-card">';
            echo '<div class="card-body">';
            echo '<h5 class="card-title">' . get_the_title() . '</h5>';
            if ($contributors) {
                echo '<p>Contributors: ' . esc_html($contributors) . '</p>';
            }
            echo '</div>';
            echo '</div>';
        endwhile;

        // Pagination
        $total_pages = $query->max_num_pages;
        if ($total_pages > 1) {
            echo '<div class="pagination">';
            echo paginate_links(array(
                'total' => $total_pages,
                'current' => $paged,
            ));
            echo '</div>';
        }
    } else {
        echo '<p>No projects found.</p>';
    }
    echo '</div>';

    wp_reset_postdata();

    return ob_get_clean();
}

?>