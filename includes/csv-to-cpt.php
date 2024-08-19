<?php

        // This adds an action button to the Senior Design Projects page in WOrdPress
        add_action('admin_notices', function() {
            $screen = get_current_screen();
            if ($screen->post_type == 'sd_project' && $screen->base == 'edit') {
                echo "<div class='updated'>";
                echo "<p>";
                echo "To import Senior Design Projects from the CSV, click the button to the right."; // TODO: Change to better explaination
                echo "<a class='button button-primary' style='margin:0.25em 1em' href='{$_SERVER["REQUEST_URI"]}&insert_sd_projects'>Insert Posts</a>";
                echo "</p>";
                echo "</div>";
            }
        });

        // This executes when the page is loaded
        add_action('admin_init', function() {
            global $wpdb;

            // If the URL is not set to 'insert_sd_projects' we will return and not execute the function
            // TODO: Replace with a potentially safer alternative (ex: pop-up confirmation)
            if ( !isset($_GET['insert_sd_projects'])) {
                return;
            }

            $project = array(
                'custom-post-type' => 'sd_project',
            );

            // Retrieve the data from the CSV
            $posts = function() {
                $data = array();
                $errors = array();

                // Will read through each file in the path: /includes/data/
                $files = glob(__DIR__ . '/data/*.csv');

                foreach ($files as $file) {
                    // Try to change permissions if the file is unreadable
                    if (!is_readable($file))
                        chmod($file, 0744);

                    if (is_readable($file) && $_file = fopen($file, 'r')) {
                        $header = fgetcsv($_file);

                        if ($header === false) {
                            $errors[] = "File '$file' does not contain valid header row.";
                            fclose($_file);
                            continue;
                        }

                        while (($row = fgetcsv($_file)) !== false) {
                            $post = array(); // Reinitialize $post for each row

                            foreach ($header as $i => $key) {
                                // Ensure $row[$i] is set, otherwise, set as empty string
                                $post[$key] = isset($row[$i]) ? $row[$i] : '';
                            }

                            $data[] = $post;
                        }

                        fclose($_file);

                    } else {
                        $errors[] = "File '$file' could not be opened. Check the file's permissions to make sure it's readable by your server.";
                    }
                }

                // Log any errors
                if (!empty($errors)) {
                    foreach ($errors as $error) {
                        error_log($error);
                    }
                }

                return $data;
            };


            // Query to retrieve all posts that exist
            $post_exists = function($title) use ($wpdb, $project) {
                $query = $wpdb->prepare(
                    "SELECT post_title FROM {$wpdb->posts} WHERE post_title = %s AND post_type = %s",
                    $title,
                    $project['custom-post-type']
                );
                return $wpdb->get_var($query) !== null;
            };

            foreach ($posts() as $post) {

                // If the post already exists, do not add it again
                if ($post_exists($post['title'])) {
                    continue;
                }

                $post['id'] = wp_insert_post(
                    array(
                        'post_title' => $post['title'],
                        'post_type' => $project['custom-post-type'],
                        'post_status' => 'publish',
                    )
                );

                // Attach the contributors text field
                if (!empty($post['project_contributors'])) {
                    update_field('project_contributors', $post['project_contributors'], $post['id']);
                }
                
                $file_fields = [
                    'short_report' => 'short_report_file',
                    'long_report' => 'long_report_file',
                    'presentation' => 'presentation_slides_file'
                ];
                
                // Ensure $acf_fields is defined properly as a simple indexed array
                $acf_fields = [
                    'short_report_file', 
                    'long_report_file', 
                    'presentation_slides_file'
                ];
                
                foreach ($file_fields as $field => $acf_field) {
                    if (!empty($post[$field])) {
                        $file_path = $post[$field];
                        $file_name = basename($file_path);
                        $file_type = wp_check_filetype($file_name, null);
                    
                        $attachment = array(
                            'guid' => wp_upload_dir()['url'] . '/' . $file_name,
                            'post_mime_type' => $file_type['type'],
                            'post_title' => sanitize_file_name($file_name),
                            'post_content' => '',
                            'post_status' => 'inherit'
                        );
                    
                        $uploaded = move_uploaded_file($file_path, wp_upload_dir()['path'] . '/' . $file_name);
                    
                        if ($uploaded) {
                            $attach_id = wp_insert_attachment($attachment, wp_upload_dir()['path'] . '/' . $file_name, $post['id']);
                            require_once(ABSPATH . 'wp-admin/includes/image.php');
                            $attach_data = wp_generate_attachment_metadata($attach_id, wp_upload_dir()['path'] . '/' . $file_name);
                            wp_update_attachment_metadata($attach_id, $attach_data);
                    
                            // Update the ACF field with the attachment ID
                            update_field($acf_field, $attach_id, $post['id']);
                        }
                    }
                }
            }

        });