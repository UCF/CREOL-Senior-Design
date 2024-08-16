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

<?php

    /**
     * Show insert posts button on backend (only on the given CPT page)
     * TODO: Replace 'your_custom_post_type' with desired CPT
     */
    add_action('admin_notices', function() {
        // Only show on a specific CPT page
        $screen = get_current_screen();
        if ( $screen->post_type == 'your_custom_post_type' && $screen->base == 'edit' ) {
            echo "<div class='updated'>";
            echo "<p>";
            echo "To insert the posts into the database, click the button to the right.";
            echo "<a class='button button-primary' style='margin:0.25em 1em' href='{$_SERVER["REQUEST_URI"]}&insert_sitepoint_posts'>Insert Posts</a>";
            echo "</p>";
            echo "</div>";
        }
    });

    /**
     * Create and insert posts from CSV files
     */
    add_action( "admin_init", function() {
        global $wpdb;

        // I'd recommend replacing this with your own code to make sure
        //  the post creation _only_ happens when you want it to.
        if ( ! isset( $_GET["insert_sitepoint_posts"] ) ) {
            return;
        }

        // Change these to whatever you set
        $sitepoint = array(
            "custom-field" => "sitepoint_post_attachment", // TODO: replace with custom-field slug(s)
            "custom-post-type" => "sitepoint_posts" // TODO: replace with custom-post-type slug
        );

        // Get the data from all those CSVs!
        $posts = function() {
            $data = array();
            $errors = array();

            // Get array of CSV files
            $files = glob( __DIR__ . "/data/*.csv" );

            foreach ( $files as $file ) {

                // Attempt to change permissions if not readable
                if ( ! is_readable( $file ) ) {
                    chmod( $file, 0744 );
                }

                // Check if file is writable, then open it in 'read only' mode
                if ( is_readable( $file ) && $_file = fopen( $file, "r" ) ) {

                    // To sum this part up, all it really does is go row by
                    //  row, column by column, saving all the data
                    $post = array();

                    // Get first row in CSV, which is of course the headers
                    $header = fgetcsv( $_file );

                    while ( $row = fgetcsv( $_file ) ) {

                        foreach ( $header as $i => $key ) {
                            $post[$key] = $row[$i];
                        }

                        $data[] = $post;
                    }

                    fclose( $_file );

                } else {
                    $errors[] = "File '$file' could not be opened. Check the file's permissions to make sure it's readable by your server.";
                }
            }

            if ( ! empty( $errors ) ) {
                // ... do stuff with the errors
            }

            return $data;
        };

        // Simple check to see if the current post exists within the
        //  database. This isn't very efficient, but it works.
        $post_exists = function( $title ) use ( $wpdb, $sitepoint ) {

            // Get an array of all posts within our custom post type
            $posts = $wpdb->get_col( "SELECT post_title FROM {$wpdb->posts} WHERE post_type = '{$sitepoint["custom-post-type"]}'" );

            // Check if the passed title exists in array
            return in_array( $title, $posts );
        };

        foreach ( $posts() as $post ) {

            // If the post exists, skip this post and go to the next one
            // TODO: title must match the header of the CSV file
            if ( $post_exists( $post["title"] ) ) {
                continue;
            }

            // Insert the post into the database
            // TODO: each one of these must match the headers of the CSV file
            $post["id"] = wp_insert_post( array(
                "post_title" => $post["title"],
                "post_content" => $post["content"],
                "post_type" => $sitepoint["custom-post-type"],
                "post_status" => "publish"
            ));

            // Get uploads dir
            $uploads_dir = wp_upload_dir();

            // Set attachment meta
            $attachment = array();
            $attachment["path"] = "{$uploads_dir["baseurl"]}/sitepoint-attachments/{$post["attachment"]}";
            $attachment["file"] = wp_check_filetype( $attachment["path"] );
            $attachment["name"] = basename( $attachment["path"], ".{$attachment["file"]["ext"]}" );

            // Replace post attachment data
            $post["attachment"] = $attachment;

            // Insert attachment into media library
            $post["attachment"]["id"] = wp_insert_attachment( array(
                "guid" => $post["attachment"]["path"],
                "post_mime_type" => $post["attachment"]["file"]["type"],
                "post_title" => $post["attachment"]["name"],
                "post_content" => "",
                "post_status" => "inherit"
            ));

            // Update post's custom field with attachment
            update_field( $sitepoint["custom-field"], $post["attachment"]["id"], $post["id"] );
            
        }

    });
?>