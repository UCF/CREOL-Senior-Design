<?php

function senior_design_display() {
    // Initialize variables to manage pagination and form inputs
    $paged = (get_query_var('paged')) ? get_query_var('paged') : 1;
    $category = isset($_GET['category']) ? $_GET['category'] : '';
    $search_term = isset($_GET['search']) ? $_GET['search'] : '';

    // Setup the arguments for WP_Query or get_posts
    $args = array(
        'posts_per_page' => 10,
        'paged'          => $paged,
        'post_type'      => 'post',
        'post_status'    => 'publish',
        's'              => $search_term  // This parameter includes search functionality
    );

    // Add category filter if a category has been selected
    if (!empty($category)) {
        $args['cat'] = $category;
    } else {
        $args['cat'] = '319, 320, 322, 323, 324, 325, 326, 327, 328, 329, 330, 331, 332, 333, 334, 335, 336, 337, 338, 339, 340, 341';
    }

    // Retrieve the list of posts based on the arguments
    $post_list = get_posts($args);
    $total_posts = count(get_posts(array_merge($args, ['posts_per_page' => -1])));
    $total_pages = ceil($total_posts / $args['posts_per_page']);

    // Check if there are any posts returned by the query
    if (!empty($post_list)) {
        // Inline CSS for card styling
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

        // Form setup for category selection and search functionality
        echo '<div class="container mb-4">
                <div class="row">
                    <form action="" method="GET" class="form-inline" style="width: 100%; display: flex; justify-content: end;">

                        <div class="form-group mr-4">
                            <select class="form-control" id="categorySelector" name="category" style="width: 100%;">
                                <option value="">All Semesters</option>';
        // Fetch categories and populate dropdown
        $categories = get_categories(array('include' => '319, 320, 322, 323, 324, 325, 326, 327, 328, 329, 330'));
        foreach ($categories as $category_option) {
            $selected = ($category_option->term_id == $category) ? ' selected' : '';
            echo '<option value="' . esc_attr($category_option->term_id) . '"' . $selected . '>' . esc_html($category_option->name) . '</option>';
        }
        echo '</select>
                        </div>

                        <div class="form-group">
                            <div class="input-group" style="width: 100%;">
                                <input class="form-control" type="text" name="search" placeholder="Search by title" value="' . esc_attr($search_term) . '" style="line-height: 1.15 !important;">
                                <span class="input-group-btn">
                                    <button class="btn btn-primary" type="submit"><i class="fa fa-search" aria-hidden="true"></i></button>
                                </span>
                            </div>
                        </div>
                    </form>
                </div>
            </div>';

        // Display posts
        echo '<div class="row mb-5">';
        foreach ($post_list as $post) {
            setup_postdata($post);
            $permalink = get_permalink($post);
            echo '<div class="card-box col-12">';
            echo '<a href="' . $permalink . '">';
            echo '<div class="card custom-card">';
            echo '<div class="card-body">';
            echo '<h5 class="card-title mb-1" style="margin-top: 2px;">' . get_the_title($post) . '</h5>';
            echo '</div>';
            echo '</div>';
            echo '</a>';
            echo '</div>';
        }
        echo '</div>';

        // Pagination controls
        if ($total_pages > 1) {
            echo '<nav aria-label="Page navigation">';
            echo '<ul class="pagination justify-content-center">';
        
            $base_link = esc_url(remove_query_arg('paged', get_pagenum_link(1)));
            $current_page = max(1, get_query_var('paged'));
        
            $link_with_params = add_query_arg([
                'category' => $category,
                'search' => $search_term
            ], $base_link);
        
            if ($current_page > 1) {
                echo '<li class="page-item"><a class="page-link" href="' . esc_url(add_query_arg('paged', $current_page - 1, $link_with_params)) . '" aria-label="Previous"><span aria-hidden="true">&laquo;</span><span class="sr-only">Previous</span></a></li>';
            }
        
            for ($i = 1; $i <= $total_pages; $i++) {
                $page_link = esc_url(add_query_arg('paged', $i, $link_with_params));
                if ($i == $current_page) {
                    echo '<li class="page-item active"><a class="page-link" href="#">' . $i . '</a></li>';
                } else {
                    echo '<li class="page-item"><a class="page-link" href="' . $page_link . '">' . $i . '</a></li>';
                }
            }
        
            if ($current_page < $total_pages) {
                echo '<li class="page-item"><a class="page-link" href="' . esc_url(add_query_arg('paged', $current_page + 1, $link_with_params)) . '" aria-label="Next"><span aria-hidden="true">&raquo;</span><span class="sr-only">Next</span></a></li>';
            }
        
            echo '</ul></nav>';
        }

        wp_reset_postdata();
    } else {
        echo 'No posts found.';
    }

    echo "<script>
        document.addEventListener('DOMContentLoaded', function () {
            const categorySelector = document.getElementById('categorySelector');
            const searchInput = document.querySelector('input[name=\"search\"]');
            
            categorySelector.addEventListener('change', function () {
                updateURLParams();
            });
            
            searchInput.addEventListener('change', function () {
                updateURLParams();
            });

            function updateURLParams() {
                const url = new URL(window.location);
                url.searchParams.set('paged', '1');
                url.searchParams.set('category', categorySelector.value);
                url.searchParams.set('search', searchInput.value);
                window.location.href = url.href;
            }
        });
        </script>";
}
