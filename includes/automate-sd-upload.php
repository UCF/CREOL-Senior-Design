<?php
    function automate_sd_upload($atts) {

        // This begins output buffering, which is needed to properly output HTML within this function
        ob_start();

        // This will be used for generating the arguments for the WP_Query call later
        $args = array(
            'key' => 'value',
            'key' => 'value',
        );

        // Here, the call is made to WordPress (WP) using the arguments we have given it, returning the results into the $query object
        $query = new WP_Query($args);

        // If the query successfully retrieved posts from WP, do something with them here. Otherwise, provide feedback somehow
        if ($query->have_posts()) {

        } else {

        }

        // Reset the global $post object so that no errors or conflicts arise when a new call is fetched on the same page
        wp_reset_postdata();

        // This is the return statement that will output the retrieved information and/or HTML, CSS, and JS properly
        return ob_get_clean();
    }
?>