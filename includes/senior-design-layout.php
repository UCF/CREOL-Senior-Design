<?php

function senior_design_display() {
    $post_list = get_posts(array(
        'posts_per_page' => -1,
        'post_status'    => 'publish',
        'category_name'  => 'senior-design-projects', 
        'meta_key'       => 'person_orderby_name',
        'orderby'        => 'menu_order',
        'order'          => 'ASC'
    ));
    echo '<pre>';
    print_r($post_list);
    echo '</pre>';

    if (!empty($post_list)) {
        echo '<div class="row mb-5">';
        foreach ($post_list as $post) {
            setup_postdata($post);
            $permalink = get_permalink($post);
            $featured_image = get_the_post_thumbnail($post->ID, 'medium');
            $job_title = get_field('person_jobtitle', $post->ID);

            echo '<div class="card-box col-lg-2 col-md-3 col-sm-4 col-6">';
            echo '<div class="card custom-card">';
            echo '<a href="' . $permalink . '">';
            if (!empty($featured_image)) {
                echo $featured_image;
            }
            echo '<div class="card-body">';
            echo '<h5 class="card-title">' . get_the_title($post) . '</h5>';
            if (!empty($job_title)) {
                echo '<div class="job-title"><i>' . esc_html($job_title) . '</i></div>';
            }
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