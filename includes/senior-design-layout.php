<?php

function senior_design_display() {
    $post_list = get_posts(array(
        'posts_per_page' => -1,
        'post_type'      => 'post',
        'post_status'    => 'publish',
        'category_name'  => 'Spring 2023',
    ));

    if (!empty($post_list)) {
        echo '<style>
                .custom-card {
                    border-radius: 15px; /* Rounded border */
                    box-shadow: 0 4px 8px rgba(0,0,0,0.1); /* Optional: adds shadow for better visibility */
                    margin-bottom: 20px; /* Space between cards */
                }
              </style>';

        echo '<div class="row mb-5">';
        foreach ($post_list as $post) {
            setup_postdata($post);
            $permalink = get_permalink($post);

            echo '<div class="card-box col-12">';
            echo '<div class="card custom-card">';
            echo '<a href="' . $permalink . '">';
            echo '<div class="card-body">';
            echo '<h5 class="card-title">' . get_the_title($post) . '</h5>';
            echo '</div>';
            echo '</a>';
            echo '</div>';
            echo '</div>';
        }
        echo '</div>';
        wp_reset_postdata();
    } else {
        echo 'No posts found.';
    }
}
