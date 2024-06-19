<?php

function senior_design_display() {
    // Determine the current page
    $paged = (get_query_var('paged')) ? get_query_var('paged') : 1;

    $args = array(
        'posts_per_page' => 5,
        'paged'          => $paged,
        'post_type'      => 'post',
        'post_status'    => 'publish',
        'cat'            => '319, 320, 321, 322, 323, 324, 325, 326, 337, 328, 329, 330'
    );

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
        if ($total_pages > 1){
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
