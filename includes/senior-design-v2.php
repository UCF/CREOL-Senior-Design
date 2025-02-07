<?php

// Shortcode to display projects with filter, search, and pagination
function sd_project_display($atts) {
    ob_start();
    
    // Get query variables
    $sort_order = isset($_GET['sort_order']) ? sanitize_text_field(wp_unslash($_GET['sort_order'])) : 'ASC';
    $selected_semesters = isset($_GET['selected_semesters']) ? array_map('sanitize_text_field', explode(',', wp_unslash($_GET['selected_semesters']))) : [];
    $search = isset($_GET['search']) ? sanitize_text_field(wp_unslash($_GET['search'])) : '';    
    $paged = (get_query_var('paged')) ? get_query_var('paged') : 1;

    // Generate a unique cache key based on the query parameters
    $cache_key = 'sd_project_display_' . md5(serialize(compact('sort_order', 'selected_semesters', 'search', 'paged')));
    $cached_results = false; // get_transient($cache_key);

    if ($cached_results !== false) {
        echo $cached_results;
        return ob_get_clean();
    }

    $args = array(
        'post_type' => 'sd_project',
        'posts_per_page' => 10,
        'paged' => $paged,
        's' => $search, // This will search in post title and content
        'orderby_taxonomy' => 'sd_semester', // Custom query var to sort projects by their semester term name
        'order' => $sort_order,
    );
    
    // // Sorting
    // if ($sort_order === 'DESC') {
    //     // $args['orderby'] = 'title';
    //     // $args['order'] = 'DESC';
    // } else {
    //     // $args['orderby'] = 'title';
    //     // $args['order'] = 'ASC';
    // }

    // Semester filtering
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

    ob_start();
    
    // Display semester dropdown
    $terms = get_terms(array(
        'taxonomy' => 'sd_semester',
        'hide_empty' => false,
    ));

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
    </style>
    <?php     

    echo '<div class="container mb-4">';
    echo '  <div class="row">';
    echo '    <form class="form-inline" id="utility-bar" method="GET" action="" style="width: 100%; display: flex; justify-content: end;">';

    // Search bar
    echo '      <div class="form-group ml-4">';
    echo '          <div class="input-group" style="width: 100%;">';
    echo '              <input class="form-control" type="text" id="searchFilter" name="search" placeholder="Search..." value="' . esc_attr($search) . '" style="line-height: 1.15 !important;">';
    echo '          </div>';
    echo '      </div>';

    // Filter button
    echo '      <div class="form-group ml-4">';
    echo '          <button class="btn btn-default" type="button" data-toggle="collapse" data-target="#filtersCollapse">Filters</button>';
    echo '      </div>';

    echo '    </form>';
    echo '  </div>';
    echo '</div>';

    // Filter collapse
    echo '<div class="collapse filters-collapse mb-4" id="filtersCollapse">';
    echo '  <div class="card card-block">';
    
    // Filter group 1 (A-Z + Z-A)
    echo '      <label for="filterGroup1">Sort by title</label>';
    echo '      <div class="form-check mb-4" id="filterGroup1">';
    echo '          <label class="form-check-label mr-2" for="filter1Option1">';
    echo '              <input class="form-check-input" type="radio" name="sort_order" value="ASC" id="filter1Option1">';
    echo '              A-Z';
    echo '          </label>';
    echo '          <label class="form-check-label mr-2" for="filter1Option2">';
    echo '              <input class="form-check-input" type="radio" name="sort_order" value="DESC" id="filter1Option2">';
    echo '              Z-A';
    echo '          </label>';
    echo '      </div>';

    // Filter group 2 (Semester selector)
    echo '      <label for="filterGroup2">Semester Select</label>';
    echo '      <div class="form-check mb-4" id="filterGroup2">';
    echo '          <select class="form-control mb-4" name="filter2" id="filter2Option1">';
    echo '              <option value="option1">All Semesters</option>';
    echo '              <option value="option2">Select Semesters</option>';
    echo '          </select>';

    // Multi-select dropdown for semesters
    echo '          <div class="collapse" id="multiSemesterCollapse">';
    echo '          <label for="multiSemesterSelector">Select Semesters</label>';
    echo '          <small class="form-text text-muted" style="margin-bottom: 8px;">Click in the box to open the dropdown menu. You can select multiple semesters in any combination.</small>';
    echo '              <select class="form-control mb-4 multi-select" id="multiSemesterSelector" name="selected_semesters[]" multiple="multiple" style="width: 100%;">';
    foreach ($terms as $term) {
        $selected = in_array($term->slug, $selected_semesters) ? 'selected="selected"' : '';
        echo '                  <option value="' . esc_attr($term->slug) . '" ' . $selected . '>' . esc_html($term->name) . '</option>';
    }
    echo '              </select>';
    echo '          </div>';
    echo '      </div>';

    echo '  </div>';
    echo '</div>';

    // Group projects by their semester term
    $grouped_projects = array();

    if ( $query->have_posts() ) {
        while ( $query->have_posts() ) {
            $query->the_post();
            $post_id = get_the_ID();
            $terms = get_the_terms( $post_id, 'sd_semester' );
            
            if ( $terms && ! is_wp_error( $terms ) ) {
                foreach ( $terms as $term ) {
                    $grouped_projects[ $term->slug ][] = $post_id;
                }
            } else {
                $grouped_projects['uncategorized'][] = $post_id;
            }
        }
        wp_reset_postdata();
    }

    // Now, output each semester group:
    foreach ( $grouped_projects as $semester_slug => $posts ) {
        if ( 'uncategorized' === $semester_slug ) {
            $semester_name = 'Uncategorized';
        } else {
            $term_obj = get_term_by( 'slug', $semester_slug, 'sd_semester' );
            $semester_name = $term_obj ? $term_obj->name : $semester_slug;
        }
        
        echo '<h2>' . esc_html( $semester_name ) . ' Projects</h2>';
        echo '<div class="semester-projects">';
        
        foreach ( $posts as $post_id ) {
            $post = get_post( $post_id );
            setup_postdata( $post );
            
            $title = get_field( 'title', $post_id );
            $short_report = get_field('short_report_file', $post_id);
            $long_report = get_field('long_report_file', $post_id);
            $presentation = get_field('presentation_slides_file', $post_id);
            $contributors = get_field('project_contributors', $post_id);
            $sponsor = get_field('sponsor', $post_id);
            
            echo '<div class="card-box col-12">';
            echo '<div class="card sd-card">';
            echo '    <div class="card-body">';
            echo '        <h5 class="card-title my-3">Title: ' . esc_html($title) . '</h5>';
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

    // echo '<div id="sd-projects">';
    // if ($query->have_posts()) {
    //     while ($query->have_posts()) : $query->the_post();
    //         $short_report = get_field('short_report_file');
    //         $long_report = get_field('long_report_file');
    //         $presentation = get_field('presentation_slides_file');
    //         $contributors = get_field('project_contributors');
    //         $sponsor = get_field('sponsor');

    //         echo '<div class="card-box col-12">';
    //         echo '<div class="card sd-card">';
    //         echo '    <div class="card-body">';
    //         echo '        <h5 class="card-title my-3">Title: ' . get_the_title() . '</h5>';
    //         if ($sponsor)
    //             echo '        <p class="my-1"><strong>Sponsor: </strong> ' . esc_html($sponsor) . ' </p>';
    //         if ($contributors)
    //             echo '        <p class="my-1"><strong>Members: </strong>' . esc_html($contributors) . '</p>';
    //         if ($short_report || $long_report || $presentation) {
    //             echo '        <p class="my-1"><strong>View: </strong>';
    //             if ($short_report)
    //                 echo '            <a href="' . esc_url($short_report) . '" target="_blank">Short Report</a> | ';
    //             if ($long_report)
    //                 echo '            <a href="' . esc_url($long_report) . '" target="_blank">Long Report</a> | ';
    //             if ($presentation)
    //                 echo '            <a href="' . esc_url($presentation) . '" target="_blank">Presentation</a>';
    //             echo '        </p>';
    //         };
    //         echo '    </div>';
    //         echo '</div>';
    //         echo '</div>';
    //     endwhile;
    //     echo '</div>';

        // Pagination controls
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
        // } else {
        // echo '<p>No projects found.</p>';
        // }

        ?>
        <script>
        document.addEventListener('DOMContentLoaded', function() {
        const form = document.getElementById('utility-bar');
        const multiSemesterSelector = document.getElementById('multiSemesterSelector');
        const searchInput = document.getElementById('searchFilter');
        const filter2Dropdown = document.getElementById('filter2Option1');
        const filter1Option1 = document.getElementById('filter1Option1');
        const filter1Option2 = document.getElementById('filter1Option2');
        const multiSemesterCollapse = document.getElementById('multiSemesterCollapse');
        const paginationContainer = document.getElementById('pagination-container');

        // Set defaults
        var params = new URLSearchParams(window.location.search);
        const sortOrder = params.get('sort_order');
        const selectedSemesters = params.get('selected_semesters');
        const search = params.get('search');

        if (sortOrder === 'DESC') {
            filter1Option2.checked = true;
        } else {
            filter1Option1.checked = true;
        }

        if (selectedSemesters) {
            filter2Dropdown.value = 'option2';
            multiSemesterCollapse.classList.add('show');
        } else {
            filter2Dropdown.value = 'option1';
            multiSemesterCollapse.classList.remove('show');
            params.delete('selected_semesters');
        }

        if (search) {
            searchInput.value = search;
        } else {
            searchInput.value = '';
            params.delete('search');
        }

        // Event listeners
        if (form) {
            form.addEventListener('submit', function(event) {
            event.preventDefault();
            hideProjects();
            updateURL();
            fetchProjects();
            });
        }

        if (filter1Option1) {
            filter1Option1.addEventListener('change', function() {
            updateURL();
            fetchProjects();
            });
        }

        if (filter1Option2) {
            filter1Option2.addEventListener('change', function() {
            updateURL();
            fetchProjects();
            });
        }

        if (filter2Dropdown) {
            filter2Dropdown.addEventListener('change', function() {
            const selectedValue = filter2Dropdown.value;

            if (selectedValue === 'option2') {
            multiSemesterCollapse.classList.add('show');
            } else {
            multiSemesterCollapse.classList.remove('show');
            }
            updateURL();
            fetchProjects();
            });
        }

        if (multiSemesterSelector) {
            $(multiSemesterSelector).on('change', function() {
            updateURL();
            fetchProjects();
            });
        }

        if (searchInput) {
            searchInput.addEventListener('input', debounce(function() {
            updateURL();
            fetchProjects();
            }, 300));
        }

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

            const sortOrder = filter1Option1.checked ? 'ASC' : 'DESC';
            params.set('sort_order', sortOrder);

            if (filter2Dropdown.value === 'option2') {
            const selectedSemesters = $(multiSemesterSelector).val();
            if (selectedSemesters && selectedSemesters.length > 0) {
            params.set('selected_semesters', selectedSemesters.join(','));
            } else {
            params.delete('selected_semesters');
            }
            } else {
            params.delete('selected_semesters');
            }

            params.set('paged', page);

            // Directly update the URL with a simple pushState, no additional encoding
            history.pushState(null, '', url.pathname + '?' + params.toString());
        }

        function fetchProjects(page = 1) {
            const url = new URL(window.location);
            const params = new URLSearchParams(url.search);

            params.set('paged', page);  // Set to the specified page
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

            // Update URL again with the decoded string
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
            projects.innerHTML = ''; // Clear existing children
            projects.classList.add('hidden');
            projects.classList.add('load-message');
            const pBlock = document.createElement('p');
            const textNode = document.createTextNode('Loading...');
            pBlock.appendChild(textNode);
            projects.appendChild(pBlock);
            }
        }

        $(function() {
            $('#multiSemesterSelector').select2({
            placeholder: 'Select semesters',
            allowClear: true,
            dropdownParent: $('#filtersCollapse'),
            }).on('select2:unselecting', function() {
            // Clear the semester params when the select2 is cleared
            updateURL();
            fetchProjects();
            });
        });
        });
        </script>

        <?php
    wp_reset_postdata();

    // Cache the output for 1 hour
    // set_transient($cache_key, ob_get_contents(), HOUR_IN_SECONDS);

    // Return the output
    return ob_get_clean();
}
?>