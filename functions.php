<?php
// Shortcode to display projects with filter, search, and pagination
function sd_project_display($atts) {
    ob_start();
    
    // Get query variables
    $selected_semesters = isset($_GET['selected_semesters']) ? array_map('sanitize_text_field', explode(',', wp_unslash($_GET['selected_semesters']))) : [];
    $search = isset($_GET['search']) ? sanitize_text_field(wp_unslash($_GET['search'])) : '';    
    $paged = (get_query_var('paged')) ? get_query_var('paged') : 1;

    $args = array(
        'post_type' => 'sd_project',
        'posts_per_page' => 10,
        'paged' => $paged,
        's' => $search,
        'orderby' => 'title',
        'order' => 'ASC',
    );

    if (!empty($selected_semesters)) {
        $args['tax_query'] = array(
            array(
                'taxonomy' => 'sd_semester',
                'field' => 'slug',
                'terms' => $selected_semesters,
            ),
        );
    }
    
    $query = new WP_Query($args);

    // Display semester dropdown
    $terms = get_terms(array(
        'taxonomy' => 'sd_semester',
        'hide_empty' => false,
    ));

    echo '<form method="GET" action="">';
    echo '<input type="text" name="search" placeholder="Search..." value="' . esc_attr($search) . '">';
    echo '<select name="selected_semesters[]" multiple>';
    foreach ($terms as $term) {
        $selected = in_array($term->slug, $selected_semesters) ? 'selected' : '';
        echo '<option value="' . esc_attr($term->slug) . '" ' . $selected . '>' . esc_html($term->name) . '</option>';
    }
    echo '</select>';
    echo '<button type="submit">Filter</button>';
    echo '</form>';

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

    // Output each semester group
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
            
            echo '<div class="card">';
            echo '<h3>' . get_the_title($post_id) . '</h3>';
            echo '<p>' . get_the_excerpt($post_id) . '</p>';
            echo '</div>';
        }
        wp_reset_postdata();
        echo '</div>';
    }

    // Pagination controls
    $total_pages = $query->max_num_pages;
    if ($total_pages > 1) {
        echo '<nav class="pagination">';
        echo paginate_links(array(
            'total' => $total_pages,
            'current' => $paged,
        ));
        echo '</nav>';
    }

    return ob_get_clean();
}
add_shortcode('sd_project_display', 'sd_project_display');

function sd_enqueue_scripts() {
    wp_enqueue_style('select2-css', 'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css');
    wp_enqueue_script('select2-js', 'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js', array('jquery'), null, true);
    wp_enqueue_script('sd-custom-js', plugin_dir_url(__FILE__) . 'js/custom.js', array('jquery', 'select2-js'), null, true);
}
add_action('wp_enqueue_scripts', 'sd_enqueue_scripts');