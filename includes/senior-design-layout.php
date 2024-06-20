<?php

function senior_design_display() {
    // Determine the current page
    $paged = (get_query_var('paged')) ? get_query_var('paged') : 1;

    // Retrieve the category and search term from the URL parameter
    $category = isset($_GET['category']) ? $_GET['category'] : '';
    $search_term = isset($_GET['search']) ? $_GET['search'] : '';

    $args = array(
        'posts_per_page' => 5,
        'paged'          => $paged,
        'post_type'      => 'post',
        'post_status'    => 'publish',
        's'              => $search_term  // Search parameter
    );

    if (!empty($category)) {
        $args['cat'] = $category;
    } else {
        $args['cat'] = '319, 320, 321, 322, 323, 324, 325, 326, 337, 328, 329, 330'; // Default categories
    }

    $post_list = get_posts($args);
    $total_posts = count(get_posts(array_merge($args, ['posts_per_page' => -1])));

    if (!empty($post_list)) {
        echo '<style>
                .custom-card {
                    border-radius: 15px;
                    box-shadow: 0 3px 6px rgba(0,0,0,0.1);
                    margin-bottom: 20px;
                    padding: 20px;
                    transition: box-shadow 0.3s ease-in-out;
                }
                .custom-card:hover {
                    box-shadow: 0 4px 8px rgba(0,0,0,0.3); 
                }
              </style>';

        echo '<div class="container mb-4" >
                <div class="row">
                    <form action="" method="GET" class="form-inline" style="width: 100%; display: flex; justify-content: end;">
                        
                        <div class="form-group mr-4">
                            <select class="form-control" id="categorySelector" name="category" onchange="this.form.submit()" style="width: 100%;">
                                <option value="">All Semesters</option>';
                                $categories = get_categories(array('include' => '319, 320, 322, 323, 324, 325, 326, 337, 328, 329, 330'));
                                foreach ($categories as $category_option) {
                                    $selected = ($category_option->term_id == $category) ? ' selected' : '';
                                    echo '<option value="' . esc_attr($category_option->term_id) . '"' . $selected . '>' . esc_html($category_option->name) . '</option>';
                                }
                            echo '</select>
                        </div>

                        <div class="form-group" >
                            <div class="input-group" style="width: 100%;">
                                <input class="form-control" type="text" name="search" placeholder="Search by title..." value="' . esc_attr($search_term) . '">
                                <span class="input-group-btn">
                                    <button class="btn btn-primary" type="submit"><i class="fa fa-search" aria-hidden="true"></i></button>
                                </span>
                            </div>
                        </div>
                        
                    </form>
                </div>
            </div>';

        echo '<div class="row mb-5">';
        foreach ($post_list as $post) {
            setup_postdata($post);
            $permalink = get_permalink($post);
            
            echo '<div class="card-box col-12">';
            echo '<a href="' . $permalink . '">';
            echo '<div class="card custom-card">';
            echo '<div class="card-body">';
            echo '<h5 class="card-title">' . get_the_title($post) . '</h5>';
            echo '</div>';
            echo '</div>';
            echo '</a>';
            echo '</div>';
        }
        echo '</div>';
        
        // Pagination
        $total_pages = ceil($total_posts / 5);
        if ($total_pages > 1) {
            $current_page = max(1, get_query_var('paged'));
            echo '<div class="pagination">';
            echo paginate_links(array(
                'base'      => str_replace(999999999, '%#%', esc_url(get_pagenum_link(999999999))),
                'format'    => '?paged=%#%',
                'current'   => $current_page,
                'total'     => $total_pages,
                'prev_text' => __('&laquo; Prev'),
                'next_text' => __('Next &raquo;'),
            ));
            echo '</div>';
        }

        wp_reset_postdata();
    } else {
        echo 'No posts found.';
    }
}
