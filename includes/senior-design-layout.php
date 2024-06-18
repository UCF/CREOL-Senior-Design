<?php

function senior_design_display() {
    $post_list = get_posts(array(
        'orderby'    => 'menu_order',
        'order'      => 'ASC'
    ));

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

    $posts = array();

    foreach ($post_list as $post) {
        $posts[] = $post->ID;
    }

    $current = array_search(get_the_ID(), $posts);
    $prevID = $posts[$current - 1] ?? null;
    $nextID = $posts[$current + 1] ?? null;

    echo '<div class="navigation">';
    if (!empty($prevID)) {
        echo '<div class="alignleft">';
        echo '<a href="' . get_permalink($prevID) . '" alt="' . get_the_title($prevID) . '">';
        _e('Previous', 'textdomain');
        echo '</a>';
        echo '</div>';
    }

    if (!empty($nextID)) {
        echo '<div class="alignright">';
        echo '<a href="' . get_permalink($nextID) . '" alt="' . get_the_title($nextID) . '">';
        _e('Next', 'textdomain');
        echo '</a>';
        echo '</div>';
    }
    echo '</div>';
}
