<?php

// Shortcode to display projects with filter, search, and pagination
function sd_project_display($atts) {
    ob_start();
    
    // Get query variables
    $sort_order = isset($_GET['sort_order']) ? sanitize_text_field($_GET['sort_order']) : 'ASC';
    $semester = isset($_GET['semester']) ? sanitize_text_field($_GET['semester']) : '';
    $start_semester = isset($_GET['start_semester']) ? sanitize_text_field($_GET['start_semester']) : '';
    $end_semester = isset($_GET['end_semester']) ? sanitize_text_field($_GET['end_semester']) : '';
    $search = isset($_GET['search']) ? sanitize_text_field($_GET['search']) : '';
    $paged = (get_query_var('paged')) ? get_query_var('paged') : 1;
    
    // Query arguments
    $args = array(
        'post_type' => 'sd_project',
        'posts_per_page' => 10,
        'paged' => $paged,
        's' => $search,
    );

    // Sorting
    if ($sort_order === 'option2') {
        $args['orderby'] = 'title';
        $args['order'] = 'DESC';
    } else {
        $args['orderby'] = 'title';
        $args['order'] = 'ASC';
    }    

    // Semester filtering
    if ($start_semester && $end_semester) {
        $args['tax_query'] = array(
            array(
                'taxonomy' => 'sd_semester',
                'field' => 'slug',
                'terms' => range($start_semester, $end_semester),
                'operator' => 'BETWEEN',
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

    // Search filtering
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

    // Debugging: Print query arguments
    echo '<pre>';
    print_r($args);
    echo '</pre>';
    
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
        .hidden {
            display: none;
        }
        .load-message {
            display: block;
        }
        .filters-collapse {
            width: 100%;
            margin-top: 10px;
        }
    </style>';
    
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
    echo '              <span class="input-group-btn">';
    echo '                  <button class="btn btn-primary" type="submit"><i class="fa fa-search" aria-hidden="true"></i></button>';
    echo '              </span>';
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
    echo '              <input class="form-check-input" type="radio" name="sort_order" value="option1" id="filter1Option1" checked>';
    echo '              A-Z';
    echo '          </label>';
    echo '          <label class="form-check-label mr-2" for="filter1Option2">';
    echo '              <input class="form-check-input" type="radio" name="sort_order" value="option2" id="filter1Option2">';
    echo '              Z-A';
    echo '          </label>';
    echo '      </div>';

    // Filter group 2 (Semester selector)
    // All semesters, single semester, range semester
    echo '      <label for="filterGroup2">Semester Select</label>';
    echo '      <div class="form-check mb-4" id="filterGroup2">';
    echo '          <select class="form-control mb-4" name="filter2" id="filter2Option1">';
    echo '              <option value="option1">All Semesters</option>';
    echo '              <option value="option2" data-toggle="collapse" data-target="#singleSemesterCollapse">Single Semester</option>';
    echo '              <option value="option3" data-toggle="collapse" data-target="#rangeSemesterCollapse">Semester Range</option>';
    echo '          </select>';

    // Single semester dropdown
    echo '          <div class="collapse" id="singleSemesterCollapse">';
    echo '              <label for="semesterSelector">Select Semester</label>';
    echo '              <select class="form-control mb-4" id="semesterSelector" name="semester" style="width: 100%;">';
    echo '                  <option value="choose">Select Semester</option>';
    foreach ($terms as $term) {
        $selected = ($semester == $term->slug) ? 'selected="selected"' : '';
        echo '                  <option value="' . esc_attr($term->slug) . '" ' . $selected . '>' . esc_html($term->name) . '</option>';
    }
    echo '              </select>';
    echo '          </div>';

    // Range semester dropdown
    echo '          <div class="collapse" id="rangeSemesterCollapse">';
    echo '              <label for="startSemesterSelector">Start Semester</label>';
    echo '              <select class="form-control mb-4" id="startSemesterSelector" name="start_semester" style="width: 100%;">';
    echo '                  <option value="choose">Select Semester</option>';
    foreach ($terms as $term) {
        $selected = ($semester == $term->slug) ? 'selected="selected"' : '';
        echo '                  <option value="' . esc_attr($term->slug) . '" ' . $selected . '>' . esc_html($term->name) . '</option>';
    }
    echo '              </select>';
    echo '              <label for="endSemesterSelector">End Semester</label>';
    echo '              <select class="form-control mb-4" id="endSemesterSelector" name="end_semester" style="width: 100%;">';
    echo '                  <option value="choose">Select Semester</option>';
    foreach ($terms as $term) {
        $selected = ($semester == $term->slug) ? 'selected="selected"' : '';
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

        wp_reset_postdata();
    } else {
        echo '<p>No projects found.</p>';
    }

    echo "<script>
        document.addEventListener('DOMContentLoaded', function() {
            console.log('Page loaded');
            const form = document.getElementById('utility-bar');
            const semesterSelector = document.getElementById('semesterSelector');
            const startSemesterSelector = document.getElementById('startSemesterSelector');
            const endSemesterSelector = document.getElementById('endSemesterSelector');
            const searchInput = document.getElementById('searchFilter');
            const filter2Dropdown = document.getElementById('filter2Option1');
            const filter1Radio = document.getElementById('filter1Option1');
            const singleSemesterCollapse = document.getElementById('singleSemesterCollapse');
            const rangeSemesterCollapse = document.getElementById('rangeSemesterCollapse');

            if (form) {
                form.addEventListener('submit', function(event) {
                    console.log('Form submitted');
                    event.preventDefault();
                    hideProjects();
                    updateURL(filter2Dropdown ? filter2Dropdown.value : '');
                    fetchProjects();
                });
            }

            if (filter1Radio) {
                filter1Radio.addEventListener('change', function() {
                    console.log('Filter 1 changed');
                    updateURL(filter1Radio ? filter1Radio.value : '');
                    fetchProjects();
                });
            }

            if (filter2Dropdown) {
                filter2Dropdown.addEventListener('change', function() {
                    const selectedValue = filter2Dropdown.value;

                    if (selectedValue === 'option2') {
                        singleSemesterCollapse.classList.add('show');
                        rangeSemesterCollapse.classList.remove('show');
                    } else if (selectedValue === 'option3') {
                        singleSemesterCollapse.classList.remove('show');
                        rangeSemesterCollapse.classList.add('show');
                    } else {
                        singleSemesterCollapse.classList.remove('show');
                        rangeSemesterCollapse.classList.remove('show');
                    }
                    updateURL(selectedValue);
                    fetchProjects();
                });
            }

            if (semesterSelector) {
                semesterSelector.addEventListener('change', function() {
                    console.log('Semester changed');
                    updateURL(filter2Dropdown ? filter2Dropdown.value : '');
                    fetchProjects();
                });
            }

            if (startSemesterSelector) {
                startSemesterSelector.addEventListener('change', function() {
                    console.log('Start semester changed');
                    updateURL(filter2Dropdown ? filter2Dropdown.value : '');
                    fetchProjects();
                });
            }

            if (endSemesterSelector) {
                endSemesterSelector.addEventListener('change', function() {
                    console.log('End semester changed');
                    updateURL(filter2Dropdown ? filter2Dropdown.value : '');
                    fetchProjects();
                });
            }

            if (searchInput) {
                searchInput.addEventListener('input', function() {
                    console.log('Search input changed');
                    updateURL(filter2Dropdown ? filter2Dropdown.value : '');
                    fetchProjects();
                });
            }

            function updateURL(type = '') {
                console.log('Updating URL parameters');
                const url = new URL(window.location);
                const params = new URLSearchParams(url.search);

                params.set('paged', '1');
                if (searchInput) {
                    params.set('search', searchInput.value);
                }
                if (filter1Dropdown) {
                    params.set('sort_order', filter1Radio.value);
                }
                if (semesterSelector && type === 'option2') {
                    if (semesterSelector.value === 'choose') {
                        params.delete('semester');
                    } else {
                        params.set('semester', semesterSelector.value);
                    }
                    params.delete('start_semester');
                    params.delete('end_semester');
                } else if (startSemesterSelector && endSemesterSelector && type === 'option3') {
                    if (startSemesterSelector.value === 'choose') {
                        params.delete('start_semester');
                    } else {
                        params.set('start_semester', startSemesterSelector.value);
                    }
                    if (endSemesterSelector.value === 'choose') {
                        params.delete('end_semester');
                    } else {
                        params.set('end_semester', endSemesterSelector.value);
                    }
                    params.delete('semester');
                } else {
                    params.delete('semester');
                    params.delete('start_semester');
                    params.delete('end_semester');
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
        });
    </script>";

    return ob_get_clean();
}
?>