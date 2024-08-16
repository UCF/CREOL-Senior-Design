<?php
    function automate_sd_upload($atts) {

        // This begins output buffering, which is needed to properly output HTML within this function
        ob_start();

        add_action('admin_notices', function() {
            $screen = get_current_screen();
            if ($screen->$post_type == 'sd_project' && $screen->base == 'edit') {
                echo "<div class='updated'>";
                echo "<p>";
                echo "To import Senior Design Projects from the CSV, click the button to the right."; // TODO: Change to better explaination
                echo "<a class='button button-primary' style='margin:0.25em 1em' href='{$_SERVER["REQUEST_URI"]}&insert_sd_projects'>Insert Posts</a>";
                echo "</p>";
                echo "</div>";
            }
        });

        add_action('admin_init', function() {
            global $wpdb;

            // If the URL is not set to 'insert_sd_projects' we will return and not execute the function
            // TODO: Replace with a potentially safer alternative (ex: pop-up confirmation)
            if ( !isset($_GET['insert_sd_projects'])) {
                return;
            }

            $project = array(
                'custom-post-type' => 'sd_project',
                'custom-field' => '' // TODO: insert any ACFs (files and contributors)
            );

            $posts = function() {
                $data = array();
                $errors = array();

                $files = glob(__DIR__ . '/data/*.csv');

                foreach ($files as $file) {

                    if (!is_readable($file))
                        chmod($file, 0744);

                    if (is_readable($file) && $_file = fopen($file, 'r')) {

                        $post = array();

                        $header = fgetcsv($file);

                        while ($row = fgetcsv($_file)) {

                            foreach ($header as $i => $key) {
                                $post[$key] = $row[$i];
                            }

                            $data[] = $post;
                        }

                        fclose($_file);

                    } else {
                        $errors[] = "File '$file' could not be opened. Check the file's permissions to make sure it's readable by your server.";
                    }
                }

                if (!empty($errors)) {
                    foreach ($errors as $error) {
                        error_log($error);
                    }
                }

                return $data;
            };

            $post_exists = function($title) use ($wpdb, $project) {
                $query = $wpdb->prepare(
                    "SELECT post_title FROM {$wpdb->posts} WHERE post_title = %s AND post_type = %s",
                    $title,
                    $project['custom-post-type']
                );
                return $wpdb->get_var($query) !== null;
            };

            foreach ($posts() as $post) {

                if ($post_exists($post['title'])) {
                    continue;
                }

                $post['id'] = wp_insert_post(
                    array(
                        'post_title' => $post['title'],
                    )
                );

                $uploads_dir = wp_upload_dir();
                
            }

        });



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