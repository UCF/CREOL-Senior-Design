<?php
/**
 * Plugin Name: SD Project Display Shortcode
 * Description: Shortcode to display projects with filtering, search, pagination, and sorting by sd_semester (using term meta "semester_date"). Projects are grouped by semester.
 * Version: 1.3
 * Author: Your Name
 */

/**
 * Note Section:
 * 
 * Working combinations:
 * Single semester, no year
 * No semester, single year
 * No semester, no year
 * Single semester, single year
 * 
 * Broken combinations:
 * Single semester, many years
 * Many semesters, no year
 * Many semesters, single year
 * Many semester, many years
 * No semester, many years
 * 
 * Issues:
 * - When selecting multiple semesters, the query returns all semesters and years (maybe it breaks and returns default?)
 * - When selecting multiple years, the query returns nothing (maybe it attempts to return projects that fall under all years provided, essentially AND instead of OR?)
 * 
 * Potential solutions:
 * - Look into the SQL query to see how the WHERE clause is being built.
 * - Check the WHERE clause to ensure that it is using OR for multiple years and semesters.
 * - Ensure that the WHERE clause is properly filtering the results based on the selected years and semesters.
 */

/**
 * Filter function to modify WP_Query clauses when ordering by the taxonomy term meta 'semester_date'
 * and filtering by selected years and semesters.
 *
 * This function performs the full join of the taxonomy tables and then joins termmeta.
 * It then orders by the numeric value in "semester_date" and applies additional WHERE conditions
 * based on the custom query vars "selected_years" and "selected_semesters".
 *
 * @param array    $clauses The query clauses.
 * @param WP_Query $query   The current WP_Query instance.
 * @return array Modified query clauses.
 */
function sd_orderby_semester_date( $clauses, $query ) {
    global $wpdb;
    
    if ( 'sd_semester_date' === $query->get('orderby') ) {
        // Join taxonomy tables (this ensures that the aliases tt and t are defined)
        $clauses['join'] .= " 
            LEFT JOIN {$wpdb->term_relationships} AS tr ON {$wpdb->posts}.ID = tr.object_id 
            LEFT JOIN {$wpdb->term_taxonomy} AS tt ON tr.term_taxonomy_id = tt.term_taxonomy_id 
            LEFT JOIN {$wpdb->terms} AS t ON tt.term_id = t.term_id 
            LEFT JOIN {$wpdb->termmeta} AS tm ON t.term_id = tm.term_id AND tm.meta_key = 'semester_date' 
        ";
        
        // Ensure we are only dealing with the sd_semester taxonomy.
        $clauses['where'] .= " AND tt.taxonomy = 'sd_semester' ";
        
        // Determine sort order.
        $order = strtoupper( $query->get('order') );
        if ( ! in_array( $order, array( 'ASC', 'DESC' ) ) ) {
            $order = 'DESC';
        }
        // Order by the numeric meta value (cast as DECIMAL), then term name, then post date.
        $clauses['orderby'] = "CAST(tm.meta_value AS DECIMAL(10,2)) $order, t.name $order, {$wpdb->posts}.post_date DESC";
        
        // --- Filtering: Apply conditions if year and/or semester filters are provided ---
        $selected_years = $query->get('selected_years');
        $selected_semesters = $query->get('selected_semesters');
        
        if (( ! empty( $selected_years ) && is_array($selected_years) ) || ( ! empty( $selected_semesters ) && is_array($selected_semesters) )) {
            $conditions = array();
            // Mapping of semester names to the numeric suffix as stored in semester_date.
            $semesterMap = array(
                'Spring' => '1',
                'Summer' => '2',
                'Fall'   => '3'
            );
            
            // Handle the case where both years and semesters are selected.
            if ( ! empty( $selected_years ) && ! empty( $selected_semesters ) ) {

                // Build a list of exact values (e.g., "2020.2" for Summer 2020).
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
            // Handle the case where only years are selected.
            } elseif ( ! empty( $selected_years ) ) {

                // When only years are selected, match values starting with that year (e.g., "2020.%").
                $yearClauses = array();
                foreach ( $selected_years as $year ) {
                    $yearClauses[] = "tm.meta_value LIKE '" . esc_sql( $year ) . ".%'";
                }
                if ( $yearClauses ) {
                    $conditions[] = "(" . implode(" OR ", $yearClauses) . ")";
                }
            // Handle the case where only semesters are selected.
            } elseif ( ! empty( $selected_semesters ) ) {

                // When only semesters are selected, match values ending with the numeric suffix.
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
            // Add the conditions to the WHERE clause.
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
 * Includes two multi-select dropdowns: one for academic years (dynamically generated)
 * and one for semesters (fixed list: Spring, Summer, Fall).
 *
 * @param array $atts Shortcode attributes.
 * @return string HTML output for the projects.
 */
function sd_project_display($atts) {
    ob_start();
    
    // Get query variables.
    $sort_order = isset($_GET['sort_order']) ? sanitize_text_field(wp_unslash($_GET['sort_order'])) : 'DESC';
    $selected_semesters = isset($_GET['selected_semesters']) ? array_map('sanitize_text_field', (array) $_GET['selected_semesters']) : [];
    $selected_years = isset($_GET['selected_years']) ? array_map('sanitize_text_field', (array) $_GET['selected_years']) : [];
    $search = isset($_GET['search']) ? sanitize_text_field(wp_unslash($_GET['search'])) : '';    
    $paged = (get_query_var('paged')) ? get_query_var('paged') : 1;

    // Build a unique cache key (if you choose to use caching).
    $cache_key = 'sd_project_display_' . md5(serialize(compact('sort_order', 'selected_semesters', 'selected_years', 'search', 'paged')));
    $cached_results = false; // e.g., get_transient($cache_key);
    if ($cached_results !== false) {
        echo $cached_results;
        return ob_get_clean();
    }

    // Build WP_Query arguments.
    $args = array(
        'post_type'      => 'sd_project',
        'posts_per_page' => 10,
        'paged'          => $paged,
        's'              => $search,
        'orderby'        => 'sd_semester_date',
        'order'          => $sort_order === 'ASC' ? 'ASC' : 'DESC',
        // Pass custom query variables so our filter can use them.
        'selected_semesters' => $selected_semesters,
        'selected_years'     => $selected_years,
    );
    // No explicit tax_query is needed since filtering is handled in the posts_clauses filter.
    if ( ! empty( $selected_semesters ) || ! empty( $selected_years ) ) {
        $args['tax_query'] = array();
    }
    
    // Add our custom posts_clauses filter.
    add_filter('posts_clauses', 'sd_orderby_semester_date', 10, 2);
    $query = new WP_Query($args);
    remove_filter('posts_clauses', 'sd_orderby_semester_date', 10);

    // Retrieve all sd_semester terms (to generate the dynamic year list).
    $terms = get_terms(array(
        'taxonomy'   => 'sd_semester',
        'hide_empty' => false,
    ));
    
    // Build the dynamic list of years from term meta values.
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
    sort($years); // Sort years in ascending order.
    
    // Fixed list for semester dropdown.
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
        .filters-collapse {
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
    </style>
    <?php     
    
    // Output the search form and filter controls.
    echo '<div class="container mb-4">';
    echo '  <div class="row">';
    echo '    <form class="form-inline" id="utility-bar" method="GET" action="" style="width: 100%; display: flex; justify-content: end;">';
    echo '      <div class="form-group ml-4">';
    echo '          <div class="input-group" style="width: 100%;">';
    echo '              <input class="form-control" type="text" id="searchFilter" name="search" placeholder="Search..." value="' . esc_attr($search) . '" style="line-height: 1.15 !important;">';
    echo '          </div>';
    echo '      </div>';
    echo '      <div class="form-group ml-4">';
    echo '          <button class="btn btn-default" type="button" data-toggle="collapse" data-target="#filtersCollapse">Filters</button>';
    echo '      </div>';
    echo '    </form>';
    echo '  </div>';
    echo '</div>';

    // Filter collapse area.
    echo '<div class="collapse filters-collapse mb-4" id="filtersCollapse">';
    echo '  <div class="card card-block">';
    // Sort order radio buttons.
    echo '      <label for="filterGroup1">Sort by Semester</label>';
    echo '      <div class="form-check mb-4" id="filterGroup1">';
    echo '          <label class="form-check-label mr-2" for="filter1Option1">';
    echo '              <input class="form-check-input" type="radio" name="sort_order" value="ASC" id="filter1Option1"> Oldest';
    echo '          </label>';
    echo '          <label class="form-check-label mr-2" for="filter1Option2">';
    echo '              <input class="form-check-input" type="radio" name="sort_order" value="DESC" id="filter1Option2"> Newest';
    echo '          </label>';
    echo '      </div>';
    // Dropdown to toggle multi-selects.
    echo '      <label for="filterGroup2">Filter by Year and Semester</label>';
    echo '      <div class="form-check mb-4" id="filterGroup2">';
    echo '          <select class="form-control mb-4" name="filter2" id="filter2Option1">';
    echo '              <option value="option1">All Semesters</option>';
    echo '              <option value="option2">Select Filters</option>';
    echo '          </select>';
    // Multi-select for semesters (fixed list).
    echo '          <div class="collapse" id="multiSemesterCollapse">';
    echo '          <label for="multiSemesterSelector">Select Semesters</label>';
    echo '          <small class="form-text text-muted" style="margin-bottom: 8px;">Select one or more semesters (e.g., Spring, Summer, Fall).</small>';
    echo '              <select class="form-control mb-4 multi-select" id="multiSemesterSelector" name="selected_semesters[]" multiple="multiple" style="width: 100%;">';
    foreach ($semesterOptions as $option) {
        $selected = in_array($option, $selected_semesters) ? 'selected="selected"' : '';
        echo '                  <option value="' . esc_attr($option) . '" ' . $selected . '>' . esc_html($option) . '</option>';
    }
    echo '              </select>';
    echo '          </div>';
    // Multi-select for years (dynamic).
    echo '          <div class="collapse" id="multiYearCollapse">';
    echo '          <label for="multiYearSelector">Select Years</label>';
    echo '          <small class="form-text text-muted" style="margin-bottom: 8px;">Select one or more academic years (e.g., 2020, 2021).</small>';
    echo '              <select class="form-control mb-4 multi-select" id="multiYearSelector" name="selected_years[]" multiple="multiple" style="width: 100%;">';
    foreach ($years as $year) {
        $selected = in_array($year, $selected_years) ? 'selected="selected"' : '';
        echo '                  <option value="' . esc_attr($year) . '" ' . $selected . '>' . esc_html($year) . '</option>';
    }
    echo '              </select>';
    echo '          </div>';
    echo '      </div>';
    echo '  </div>';
    echo '</div>';

    // Output the projects.
    echo '<div id="sd-projects">';
    if ($query->have_posts()) {
        $current_semester = '';
        while ($query->have_posts()) : $query->the_post();
            // Get the associated sd_semester term.
            $project_terms = get_the_terms(get_the_ID(), 'sd_semester');
            if ($project_terms && ! is_wp_error($project_terms)) {
                $project_semester = $project_terms[0]->name;
            } else {
                $project_semester = 'Uncategorized';
            }
            // Output a header if the semester changes.
            if ($project_semester !== $current_semester) {
                echo '<h3 class="semester-header">' . esc_html($project_semester) . '</h3>';
                $current_semester = $project_semester;
            }
            // Retrieve custom fields.
            $short_report  = get_field('short_report_file');
            $long_report   = get_field('long_report_file');
            $presentation  = get_field('presentation_slides_file');
            $contributors  = get_field('project_contributors');
            $sponsor       = get_field('sponsor');

            echo '<div class="card-box col-12">';
            echo '  <div class="card sd-card">';
            echo '      <div class="card-body">';
            echo '          <h5 class="card-title my-3">Title: ' . get_the_title() . '</h5>';
            if ($sponsor) {
                echo '          <p class="my-1"><strong>Sponsor: </strong> ' . esc_html($sponsor) . '</p>';
            }
            if ($contributors) {
                echo '          <p class="my-1"><strong>Members: </strong>' . esc_html($contributors) . '</p>';
            }
            if ($short_report || $long_report || $presentation) {
                echo '          <p class="my-1"><strong>View: </strong>';
                if ($short_report) {
                    echo '              <a href="' . esc_url($short_report) . '" target="_blank">Short Report</a> | ';
                }
                if ($long_report) {
                    echo '              <a href="' . esc_url($long_report) . '" target="_blank">Long Report</a> | ';
                }
                if ($presentation) {
                    echo '              <a href="' . esc_url($presentation) . '" target="_blank">Presentation</a>';
                }
                echo '          </p>';
            }
            echo '      </div>';
            echo '  </div>';
            echo '</div>';
        endwhile;
        echo '</div>';

        // Pagination.
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
            echo '</ul></nav>';
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
        const filter2Dropdown = document.getElementById('filter2Option1');
        const filter1Option1 = document.getElementById('filter1Option1');
        const filter1Option2 = document.getElementById('filter1Option2');
        const multiSemesterCollapse = document.getElementById('multiSemesterCollapse');
        const multiYearCollapse = document.getElementById('multiYearCollapse');
        const paginationContainer = document.getElementById('pagination-container');

        var params = new URLSearchParams(window.location.search);
        const sortOrder = params.get('sort_order');
        const selectedSemesters = params.get('selected_semesters');
        const selectedYears = params.get('selected_years');
        const search = params.get('search');

        if (sortOrder === 'ASC') {
            filter1Option1.checked = true;
        } else {
            filter1Option2.checked = true;
        }

        if (selectedSemesters || selectedYears) {
            filter2Dropdown.value = 'option2';
            multiSemesterCollapse.classList.add('show');
            multiYearCollapse.classList.add('show');
        } else {
            filter2Dropdown.value = 'option1';
            multiSemesterCollapse.classList.remove('show');
            multiYearCollapse.classList.remove('show');
            params.delete('selected_semesters');
            params.delete('selected_years');
        }

        if (search) {
            searchInput.value = search;
        } else {
            searchInput.value = '';
            params.delete('search');
        }

        form.addEventListener('submit', function(event) {
            event.preventDefault();
            hideProjects();
            updateURL();
            fetchProjects();
        });

        filter1Option1.addEventListener('change', function() {
            updateURL();
            fetchProjects();
        });
        filter1Option2.addEventListener('change', function() {
            updateURL();
            fetchProjects();
        });
        filter2Dropdown.addEventListener('change', function() {
            const selectedValue = filter2Dropdown.value;
            if (selectedValue === 'option2') {
                multiSemesterCollapse.classList.add('show');
                multiYearCollapse.classList.add('show');
            } else {
                multiSemesterCollapse.classList.remove('show');
                multiYearCollapse.classList.remove('show');
            }
            updateURL();
            fetchProjects();
        });

        $(multiSemesterSelector).on('change', function() {
            updateURL();
            fetchProjects();
        });
        $(multiYearSelector).on('change', function() {
            updateURL();
            fetchProjects();
        });

        searchInput.addEventListener('input', debounce(function() {
            updateURL();
            fetchProjects();
        }, 300));

        if (paginationContainer) {
            paginationContainer.addEventListener('click', function(event) {
                const target = event.target;
                if (target.tagName === 'A' && target.dataset.page) {
                    event.preventDefault();
                    const page = target.dataset.page;
                    updateURL(page);
                    fetchProjects(page);
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

        function updateURL(page = 1) {
            const url = new URL(window.location);
            const params = new URLSearchParams(url.search);

            if (searchInput.value.trim()) {
                params.set('search', searchInput.value.trim());
            } else {
                params.delete('search');
            }

            const sortOrderVal = filter1Option1.checked ? 'ASC' : 'DESC';
            params.set('sort_order', sortOrderVal);

            if (filter2Dropdown.value === 'option2') {
                const selSem = $(multiSemesterSelector).val();
                const selYear = $(multiYearSelector).val();
                if (selSem && selSem.length > 0) {
                    params.set('selected_semesters', selSem.join(','));
                } else {
                    params.delete('selected_semesters');
                }
                if (selYear && selYear.length > 0) {
                    params.set('selected_years', selYear.join(','));
                } else {
                    params.delete('selected_years');
                }
            } else {
                params.delete('selected_semesters');
                params.delete('selected_years');
            }

            params.set('paged', page);
            history.pushState(null, '', url.pathname + '?' + params.toString());
        }

        function fetchProjects(page = 1) {
            const url = new URL(window.location);
            const params = new URLSearchParams(url.search);
            params.set('paged', page);
            url.search = params.toString();
            const decodedUrl = decodeURIComponent(url.toString());
            fetch(decodedUrl)
            .then(response => response.text())
            .then(data => {
                const parser = new DOMParser();
                const doc = parser.parseFromString(data, 'text/html');
                const projects = doc.getElementById('sd-projects');
                const pagination = doc.getElementById('pagination-container');
                document.getElementById('sd-projects').innerHTML = projects.innerHTML;
                if (pagination) {
                    document.getElementById('pagination-container').innerHTML = pagination.innerHTML;
                } else {
                    document.getElementById('pagination-container').innerHTML = '';
                }
                history.pushState(null, '', decodedUrl);
            })
            .catch(error => console.error('Error fetching projects:', error));
        }

        function hideProjects() {
            const projects = document.getElementById('sd-projects');
            const footer = document.getElementById('pagination-container');
            if (footer) {
                footer.classList.add('hidden');
            }
            if (projects) {
                projects.innerHTML = '';
                projects.classList.add('hidden', 'load-message');
                const pBlock = document.createElement('p');
                pBlock.appendChild(document.createTextNode('Loading...'));
                projects.appendChild(pBlock);
            }
        }

        $(function() {
            $('#multiSemesterSelector').select2({
                placeholder: 'Select semesters',
                allowClear: true,
                dropdownParent: $('#filtersCollapse'),
            });
            $('#multiYearSelector').select2({
                placeholder: 'Select years',
                allowClear: true,
                dropdownParent: $('#filtersCollapse'),
            });
        });
    });
    </script>
    <?php
    wp_reset_postdata();

    // Optionally cache the output.
    // set_transient($cache_key, ob_get_contents(), HOUR_IN_SECONDS);

    return ob_get_clean();
}
add_shortcode('sd_project_display', 'sd_project_display');
?>
