<?php

// Shortcode to display projects with filter, search, and pagination
function sd_project_display($atts) {
    ob_start();
    
    // Get query variables
    $sort_order = isset($_GET['sort_order']) ? sanitize_text_field($_GET['sort_order']) : 'ASC';
    $semester = isset($_GET['semester']) ? sanitize_text_field($_GET['semester']) : '';
    $selected_semesters = isset($_GET['selected_semesters']) ? array_map('sanitize_text_field', explode(',', $_GET['selected_semesters'])) : [];
    $search = isset($_GET['search']) ? sanitize_text_field($_GET['search']) : '';
    $paged = (get_query_var('paged')) ? get_query_var('paged') : 1;

    // Generate a unique cache key based on the query parameters
    $cache_key = 'sd_project_display_' . md5(serialize(compact('sort_order', 'semester', 'selected_semesters', 'search', 'paged')));
    $cached_results = get_transient($cache_key);

    if ($cached_results !== false) {
        echo $cached_results;
        return ob_get_clean();
    }

    $args = array(
        'post_type' => 'sd_project',
        'posts_per_page' => 10,
        'paged' => $paged,
        's' => $search, // This will search in post title and content
    );
    
    // Sorting
    if ($sort_order === 'DESC') {
        $args['orderby'] = 'title';
        $args['order'] = 'DESC';
    } else {
        $args['orderby'] = 'title';
        $args['order'] = 'ASC';
    }
    
    // Semester filtering
    if (!empty($selected_semesters)) {
        $args['tax_query'] = array(
            array(
                'taxonomy' => 'sd_semester',
                'field' => 'slug',
                'terms' => $selected_semesters,
            ),
        );
    } elseif ($semester) {
        $args['tax_query'] = array(
            array(
                'taxonomy' => 'sd_semester',
                'field' => 'slug',
                'terms' => $semester,
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
    echo '      <label for="filterGroup1">Sort</label>';
    echo '      <div class="form-check mb-4" id="filterGroup1">';
    echo '          <label class="form-check-label mr-2" for="filter1Option1">';
    echo '              <input class="form-check-input" type="radio" name="sort_order" value="ASC" id="filter1Option1" checked>';
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

    echo '<div id="sd-projects">';
    if ($query->have_posts()) {
        while ($query->have_posts()) : $query->the_post();
            $short_report = get_field('short_report_file');
            $long_report = get_field('long_report_file');
            $presentation = get_field('presentation_slides_file');
            $contributors = get_field('project_contributors');

            echo '<div class="card-box col-12">';
            echo '<div class="card custom-card">';
            echo '    <div class="card-body">';
            echo '        <h5 class="card-title my-3">' . get_the_title() . '</h5>';
            if ($contributors)
                echo '        <p class="my-1">' . esc_html($contributors) . '</p>';
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
        endwhile;
        echo '</div>';

        // Pagination controls
        $total_pages = $query->max_num_pages;
        if ($total_pages > 1) {
            echo '<div id="pagination-container">';
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
            echo '</div>';
        }
    } else {
        echo '<p>No projects found.</p>';
    }

    ?>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        console.log('Page loaded');
        const form = document.getElementById('utility-bar');
        const semesterSelector = document.getElementById('semesterSelector');
        const multiSemesterSelector = document.getElementById('multiSemesterSelector');
        const searchInput = document.getElementById('searchFilter');
        const filter2Dropdown = document.getElementById('filter2Option1');
        const filter1Option1 = document.getElementById('filter1Option1');
        const filter1Option2 = document.getElementById('filter1Option2');
        const multiSemesterCollapse = document.getElementById('multiSemesterCollapse');

        if (form) {
            form.addEventListener('submit', function(event) {
                console.log('Form submitted');
                event.preventDefault();
                hideProjects();
                updateURL();
                fetchProjects();
            });
        }

        if (filter1Option1) {
            filter1Option1.addEventListener('change', function() {
                console.log('Sort order changed to A-Z');
                updateURL();
                fetchProjects();
            });
        }

        if (filter1Option2) {
            filter1Option2.addEventListener('change', function() {
                console.log('Sort order changed to Z-A');
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

        if (semesterSelector) {
            semesterSelector.addEventListener('change', function() {
                console.log('Semester changed');
                updateURL();
                fetchProjects();
            });
        }

        if (multiSemesterSelector) {
            $(multiSemesterSelector).on('change', function() {
                console.log('Selected semesters changed');
                updateURL();
                fetchProjects();
            });
        }

        if (searchInput) {
            searchInput.addEventListener('input', debounce(function() {
                console.log('Search input changed');
                updateURL();
                fetchProjects();
            }, 0));
        }

        function debounce(func, wait) {
            let timeout;
            return function(...args) {
                const context = this;
                clearTimeout(timeout);
                timeout = setTimeout(() => func.apply(context, args), wait);
            };
        }

        function updateURL() {
            console.log('Updating URL parameters');
            const url = new URL(window.location);
            const params = new URLSearchParams(url.search);

            params.set('paged', '1');
            if (searchInput) {
                params.set('search', searchInput.value);
            }
            if (filter1Option1 && filter1Option1.checked) {
                params.set('sort_order', 'ASC');
            } else if (filter1Option2 && filter1Option2.checked) {
                params.set('sort_order', 'DESC');
            }
            if (semesterSelector && filter2Dropdown.value === 'option1') {
                if (semesterSelector.value === 'choose') {
                    params.delete('semester');
                } else {
                    params.set('semester', semesterSelector.value);
                }
                params.delete('selected_semesters');
            } else if (multiSemesterSelector && filter2Dropdown.value === 'option2') {
                const selectedSemesters = $(multiSemesterSelector).val();
                if (selectedSemesters.length > 0) {
                    params.set('selected_semesters', selectedSemesters.join(','));
                } else {
                    params.delete('selected_semesters');
                }
                params.delete('semester');
            } else {
                params.delete('semester');
                params.delete('selected_semesters');
            }

            url.search = params.toString();
            console.log('Updating URL to:', url.toString());
            history.pushState(null, '', url.toString());
        }

        function fetchProjects() {
            console.log('Fetching projects');
            const url = new URL(window.location);
            const params = new URLSearchParams(url.search);

            fetch(url.toString())
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
                })
                .catch(error => console.error('Error fetching projects:', error));
        }

        function hideProjects() {
            var projects = document.getElementById('sd-projects');
            var footer = document.getElementById('pagination-container');
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
            });
        });
    });
    </script>

    <?php
    wp_reset_postdata();

    // Cache the output for 1 hour
    set_transient($cache_key, ob_get_contents(), HOUR_IN_SECONDS);

    // Return the output
    return ob_get_clean();
}
?>