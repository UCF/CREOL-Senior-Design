<?php
/**
 * Plugin Name: SD Project Display Shortcode
 * Description: Shortcode to display projects with filtering, search, pagination, and sorting by sd_semester (using term meta "semester_date"). Projects are grouped by semester.
 * Version: 2.0.0
 * Author: Gage Notargiacomo
 */

/**
 * Filter function to modify WP_Query clauses when ordering by the taxonomy term meta 'semester_date'
 * and filtering by selected years and semesters.
 *
 * This function performs the full join of the taxonomy tables (term_relationships, term_taxonomy, terms)
 * and then joins termmeta. It orders by the numeric value in "semester_date" and applies additional WHERE conditions
 * based on the custom query vars "selected_years" and "selected_semesters".
 *
 * @param array    $clauses The query clauses.
 * @param WP_Query $query   The current WP_Query instance.
 * @return array Modified query clauses.
 */
function sd_orderby_semester_date( $clauses, $query ) {
    global $wpdb;
    
    if ( 'sd_semester_date' === $query->get('orderby') ) {
        // Join taxonomy tables (to define tt and t), then join termmeta.
        $clauses['join'] .= " 
            LEFT JOIN {$wpdb->term_relationships} AS tr ON {$wpdb->posts}.ID = tr.object_id 
            LEFT JOIN {$wpdb->term_taxonomy} AS tt ON tr.term_taxonomy_id = tt.term_taxonomy_id 
            LEFT JOIN {$wpdb->terms} AS t ON tt.term_id = t.term_id 
            LEFT JOIN {$wpdb->termmeta} AS tm ON t.term_id = tm.term_id AND tm.meta_key = 'semester_date' 
        ";
        
        // Restrict to sd_semester taxonomy.
        $clauses['where'] .= " AND tt.taxonomy = 'sd_semester' ";
        
        $order = strtoupper( $query->get('order') );
        if ( ! in_array( $order, array( 'ASC', 'DESC' ) ) ) {
            $order = 'DESC';
        }
        $clauses['orderby'] = "CAST(tm.meta_value AS DECIMAL(10,2)) $order, t.name $order, {$wpdb->posts}.post_date DESC";
        
        // --- Filtering: Apply conditions if year and/or semester filters are provided ---
        $selected_years = $query->get('selected_years');
        $selected_semesters = $query->get('selected_semesters');
        
        // Ensure they are arrays.
        if ( ! is_array( $selected_years ) ) {
            $selected_years = explode(',', $selected_years);
        }
        if ( ! is_array( $selected_semesters ) ) {
            $selected_semesters = explode(',', $selected_semesters);
        }
        
        if (( ! empty( $selected_years ) && is_array($selected_years) ) || ( ! empty( $selected_semesters ) && is_array($selected_semesters) )) {
            $conditions = array();
            $semesterMap = array(
                'Spring' => '1',
                'Summer' => '2',
                'Fall'   => '3'
            );
            
            if ( ! empty( $selected_years ) && ! empty( $selected_semesters ) ) {
                $values = array();
                foreach ( $selected_years as $year ) {
                    foreach ( $selected_semesters as $sem ) {
                        if ( isset( $semesterMap[ $sem ] ) ) {
                            $values[] = esc_sql( $year . '.' . $semesterMap[ $sem ] );
                        }
                    }
                }
                if ( ! empty( $values ) ) {
                    $in = "'" . implode("','", $values) . "'";
                    $conditions[] = "tm.meta_value IN ($in)";
                }
            } elseif ( ! empty( $selected_years ) ) {
                $yearClauses = array();
                foreach ( $selected_years as $year ) {
                    $yearClauses[] = "tm.meta_value LIKE '" . esc_sql( $year ) . ".%'";
                }
                if ( $yearClauses ) {
                    $conditions[] = "(" . implode(" OR ", $yearClauses) . ")";
                }
            } elseif ( ! empty( $selected_semesters ) ) {
                $semClauses = array();
                foreach ( $selected_semesters as $sem ) {
                    if ( isset( $semesterMap[ $sem ] ) ) {
                        $semClauses[] = "tm.meta_value LIKE '%." . esc_sql( $semesterMap[ $sem ] ) . "'";
                    }
                }
                if ( $semClauses ) {
                    $conditions[] = "(" . implode(" OR ", $semClauses) . ")";
                }
            }
            if ( ! empty( $conditions ) ) {
                $clauses['where'] .= " AND (" . implode(" AND ", $conditions) . ") ";
            }
        }
    }
    
    return $clauses;
}
add_filter('posts_clauses', 'sd_orderby_semester_date', 10, 2);


/**
 * Shortcode to display projects with filtering, search, pagination, and grouping by semester.
 * This version always shows two multi-select dropdowns: one for academic years (generated dynamically)
 * and one for semesters (fixed list: Spring, Summer, Fall).
 *
 * @param array $atts Shortcode attributes.
 * @return string HTML output for the projects.
 */
function sd_project_display($atts) {
    ob_start();
    
    // Retrieve query variables.
    $sort_order = isset($_GET['sort_order']) ? sanitize_text_field(wp_unslash($_GET['sort_order'])) : 'DESC';
    $search     = isset($_GET['search']) ? sanitize_text_field(wp_unslash($_GET['search'])) : '';    
    $paged      = (get_query_var('paged')) ? get_query_var('paged') : 1;
    
    // Convert comma-separated values into arrays.
    $selected_semesters = isset($_GET['selected_semesters']) 
        ? array_map('sanitize_text_field', explode(',', wp_unslash($_GET['selected_semesters']))) 
        : array();
    $selected_years = isset($_GET['selected_years']) 
        ? array_map('sanitize_text_field', explode(',', wp_unslash($_GET['selected_years']))) 
        : array();
    
    $cache_key = 'sd_project_display_' . md5(serialize(compact('sort_order', 'selected_semesters', 'selected_years', 'search', 'paged')));
    $cached_results = false; // e.g., get_transient($cache_key);
    if ($cached_results !== false) {
        echo $cached_results;
        return ob_get_clean();
    }
    
    $args = array(
        'post_type'      => 'sd_project',
        'posts_per_page' => 10,
        'paged'          => $paged,
        's'              => $search,
        'orderby'        => 'sd_semester_date',
        'order'          => $sort_order === 'ASC' ? 'ASC' : 'DESC',
        'selected_semesters' => $selected_semesters,
        'selected_years'     => $selected_years,
    );
    if ( ! empty( $selected_semesters ) || ! empty( $selected_years ) ) {
        $args['tax_query'] = array();
    }
    
    add_filter('posts_clauses', 'sd_orderby_semester_date', 10, 2);
    $query = new WP_Query($args);
    remove_filter('posts_clauses', 'sd_orderby_semester_date', 10);
    
    // Retrieve all sd_semester terms to generate the dynamic year list.
    $terms = get_terms(array(
        'taxonomy'   => 'sd_semester',
        'hide_empty' => false,
    ));
    $years = array();
    foreach ($terms as $term) {
        $semester_date = get_term_meta($term->term_id, 'semester_date', true); // expected format "YYYY.X"
        if ( ! empty($semester_date) ) {
            $year = substr($semester_date, 0, 4);
            if ( ! in_array($year, $years) ) {
                $years[] = $year;
            }
        }
    }
    sort($years);
    
    // Fixed list for the semester multi-select.
    $semesterOptions = array("Spring", "Summer", "Fall");
    ?>
    <style>
        .sd-card {
            border-radius: 12px;
            border-style: none;
            box-shadow: 0 0 10px 0 rgba(0, 0, 0, .15);
            margin-bottom: 20px;
            padding: 20px;
            transition: box-shadow .3s ease-in-out;
        }
        .sd-card:hover {
            box-shadow: 0 0 10px 2px rgba(0, 0, 0, .15);
        }
        .hidden {
            display: none;
        }
        .load-message {
            display: block;
        }
        .filters-section {
            margin-top: 10px;
            width: 100%;
        }
        .select2-container .select2-selection--multiple {
            padding: 10px;
        }
        .semester-header {
            margin-top: 40px;
            margin-bottom: 20px;
            border-bottom: 2px solid #ddd;
            padding-bottom: 5px;
            font-size: 1.5em;
            color: #333;
        }
        .filter-label {
            margin-bottom: 5px;
            font-weight: bold;
        }
    </style>
    <?php     
    
    // Display search and filter form.
    echo '<div class="container mb-4">';
        echo '<div class="row">';
            echo '<form class="form-inline" id="utility-bar" method="GET" action="" style="width: 100%; display: flex; justify-content: flex-end;">';
                echo '<div class="form-group ml-4">';
                    echo '<div class="input-group" style="width: 100%;">';
                        echo '<input class="form-control" type="text" id="searchFilter" name="search" placeholder="Search..." value="' . esc_attr($search) . '" style="line-height: 1.15 !important;">';
                    echo '</div>';
                echo '</div>';
                echo '<button class="btn btn-default ml-4" type="submit">Apply Filters</button>';
            echo '</form>';
        echo '</div>';
    
        // Always display the multi-selects (no toggle).
        echo '<div class="row filters-section">';
            echo '<div class="col-md-6">';
                echo '<label class="filter-label" for="multiSemesterSelector">Select Semesters</label>';
                echo '<select class="form-control multi-select" id="multiSemesterSelector" name="selected_semesters[]" multiple="multiple" style="width: 100%;">';
                    foreach ($semesterOptions as $option) {
                        $sel = in_array($option, $selected_semesters) ? 'selected="selected"' : '';
                        echo '<option value="' . esc_attr($option) . '" ' . $sel . '>' . esc_html($option) . '</option>';
                    }
                echo '</select>';
            echo '</div>';
            echo '<div class="col-md-6">';
                echo '<label class="filter-label" for="multiYearSelector">Select Years</label>';
                echo '<select class="form-control multi-select" id="multiYearSelector" name="selected_years[]" multiple="multiple" style="width: 100%;">';
                    foreach ($years as $year) {
                        $sel = in_array($year, $selected_years) ? 'selected="selected"' : '';
                        echo '<option value="' . esc_attr($year) . '" ' . $sel . '>' . esc_html($year) . '</option>';
                    }
                echo '</select>';
            echo '</div>';
        echo '</div>';
    echo '</div>';
    
    // Output the projects.
    echo '<div id="sd-projects">';
    if ($query->have_posts()) {
        $current_semester = '';
        while ($query->have_posts()) : $query->the_post();
            $project_terms = get_the_terms(get_the_ID(), 'sd_semester');
            if ($project_terms && ! is_wp_error($project_terms)) {
                $project_semester = $project_terms[0]->name;
            } else {
                $project_semester = 'Uncategorized';
            }
            if ($project_semester !== $current_semester) {
                echo '<h3 class="semester-header">' . esc_html($project_semester) . '</h3>';
                $current_semester = $project_semester;
            }
            $short_report  = get_field('short_report_file');
            $long_report   = get_field('long_report_file');
            $presentation  = get_field('presentation_slides_file');
            $contributors  = get_field('project_contributors');
            $sponsor       = get_field('sponsor');
    
            echo '<div class="card-box col-12">';
                echo '<div class="card sd-card">';
                    echo '<div class="card-body">';
                        echo '<h5 class="card-title my-3">Title: ' . get_the_title() . '</h5>';
                        if ($sponsor) {
                            echo '<p class="my-1"><strong>Sponsor: </strong> ' . esc_html($sponsor) . '</p>';
                        }
                        if ($contributors) {
                            echo '<p class="my-1"><strong>Members: </strong>' . esc_html($contributors) . '</p>';
                        }
                        if ($short_report || $long_report || $presentation) {
                            echo '<p class="my-1"><strong>View: </strong>';
                                if ($short_report) {
                                    echo '<a href="' . esc_url($short_report) . '" target="_blank">Short Report</a> | ';
                                }
                                if ($long_report) {
                                    echo '<a href="' . esc_url($long_report) . '" target="_blank">Long Report</a> | ';
                                }
                                if ($presentation) {
                                    echo '<a href="' . esc_url($presentation) . '" target="_blank">Presentation</a>';
                                }
                            echo '</p>';
                        }
                    echo '</div>';
                echo '</div>';
            echo '</div>';
        endwhile;
        echo '</div>';
    
        $total_pages = $query->max_num_pages;
        if ($total_pages > 1) {
            echo '<div id="pagination-container">';
                echo '<nav aria-label="Page navigation">';
                    echo '<ul class="pagination justify-content-center">';
                        $current_page = max(1, get_query_var('paged'));
                        for ($i = 1; $i <= $total_pages; $i++) {
                            if ($i == $current_page) {
                                echo '<li class="page-item active"><a class="page-link" href="#" data-page="' . $i . '">' . $i . '</a></li>';
                            } else {
                                echo '<li class="page-item"><a class="page-link" href="#" data-page="' . $i . '">' . $i . '</a></li>';
                            }
                        }
                    echo '</ul>';
                echo '</nav>';
            echo '</div>';
        }
    } else {
        echo '<p>No projects found.</p>';
    }
    ?>
    
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const form = document.getElementById('utility-bar');
        const multiSemesterSelector = document.getElementById('multiSemesterSelector');
        const multiYearSelector = document.getElementById('multiYearSelector');
        const searchInput = document.getElementById('searchFilter');
        const paginationContainer = document.getElementById('pagination-container');
    
        form.addEventListener('submit', function(event) {
            event.preventDefault();
            hideProjects();
            // Since we're now using a standard submit, the browser will update the URL.
            // Alternatively, you can call fetchProjects() here for AJAX-based filtering.
            form.submit();
        });
    
        searchInput.addEventListener('input', debounce(function() {
            form.submit();
        }, 300));
    
        $(multiSemesterSelector).on('change', function() {
            form.submit();
        });
        $(multiYearSelector).on('change', function() {
            form.submit();
        });
    
        if (paginationContainer) {
            paginationContainer.addEventListener('click', function(event) {
                const target = event.target;
                if (target.tagName === 'A' && target.dataset.page) {
                    event.preventDefault();
                    // Update URL and submit the form for pagination.
                    let url = new URL(window.location);
                    url.searchParams.set('paged', target.dataset.page);
                    window.location.href = url.toString();
                }
            });
        }
    
        function debounce(func, wait) {
            let timeout;
            return function(...args) {
                const context = this;
                clearTimeout(timeout);
                timeout = setTimeout(() => func.apply(context, args), wait);
            };
        }
    
        function hideProjects() {
            const projects = document.getElementById('sd-projects');
            if (projects) {
                projects.innerHTML = '';
                projects.classList.add('hidden', 'load-message');
                const pBlock = document.createElement('p');
                pBlock.textContent = 'Loading...';
                projects.appendChild(pBlock);
            }
        }
    
        $(function() {
            $('#multiSemesterSelector').select2({
                placeholder: 'Select semesters',
                allowClear: true,
            });
            $('#multiYearSelector').select2({
                placeholder: 'Select years',
                allowClear: true,
            });
        });
    });
    </script>
    <?php
    wp_reset_postdata();
    
    // Optionally, set the transient cache here.
    // set_transient($cache_key, ob_get_contents(), HOUR_IN_SECONDS);
    
    return ob_get_clean();
}
add_shortcode('sd_project_display', 'sd_project_display');
?>
