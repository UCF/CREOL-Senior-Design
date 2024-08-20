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

            // Define paths
            $plugin_dir = plugin_dir_path(__FILE__);
            $zip_file_path = $plugin_dir . 'data/2024_fall_sd.zip';
            $extracted_path = $plugin_dir . 'extracted/2024_fall_sd/data';

            // Create directories
            if (!file_exists($extracted_path . '/data')) {
                if (!mkdir($extracted_path . '/data', 0755, true) && !file_exists($extracted_path . '/data')) {
                    error_log("Failed to create directory: " . $extracted_path . '/data');
                } else {
                    error_log("Directory created: " . $extracted_path . '/data');
                }
            }      

            // Unzip the file
            $zip = new ZipArchive;
            if ($zip->open($zip_file_path) === TRUE) {
                $extraction_success = $zip->extractTo($extracted_path);
                $zip->close();
                if (!$extraction_success) {
                    error_log("Failed to extract ZIP file to: $extracted_path");
                } else {
                    error_log("ZIP file extracted successfully to: $extracted_path");
                }
            } else {
                error_log("Failed to open ZIP file: $zip_file_path");
            }

            // List files in the extraction directory
            $files = glob($extracted_path . '/data/*');
            error_log("Extracted files: " . print_r($files, true));

            // Retrieve the data from the CSV
            $posts = function() use ($extracted_path) {
                $data = array();
                $errors = array();

                $csv_file = $extracted_path . '/data/SD_CSV_Test1.csv';

                // Check if the file exists before attempting to change permissions
                if (file_exists($csv_file)) {
                    if (!is_readable($csv_file)) {
                        chmod($csv_file, 0744); // Change permissions if necessary
                    }
                } else {
                    error_log("CSV file '$csv_file' does not exist.");
                    return $data;
                }

                if (!is_readable($csv_file)) {
                    error_log("CSV file '$csv_file' is not readable.");
                    return $data;
                }

                if ($_file = fopen($csv_file, 'r')) {
                    $header = fgetcsv($_file);

                    if ($header === false) {
                        $errors[] = "CSV file does not contain a valid header row.";
                        fclose($_file);
                        return $data;
                    }

                    while (($row = fgetcsv($_file)) !== false) {
                        $post = array(); // Reinitialize $post for each row

                        foreach ($header as $i => $key) {
                            $post[$key] = isset($row[$i]) ? $row[$i] : '';
                        }

                        $data[] = $post;
                    }

                    fclose($_file);

                } else {
                    $errors[] = "CSV file '$csv_file' could not be opened";
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
                
                // Define file fields and their corresponding ACF fields
                $file_fields = [
                    'short_report' => 'short_report_file',
                    'long_report' => 'long_report_file',
                    'presentation' => 'presentation_slides_file'
                ];
                                
                foreach ($file_fields as $field => $acf_field) {
                    $student_zip_path = $extracted_path . '/student_files/' . $post[$field];

                    if (file_exists($student_zip_path)) {
                        $student_zip = new ZipArchive;
                        if ($student_zip->open($student_zip_path) === TRUE) {
                            $student_zip->extractTo($extracted_path . '/student_files/');
                            $student_zip->close();

                            // Find and upload the correct PDF files
                            $pdf_files = glob($extracted_path . '/student_files/*.pdf');

                            foreach ($pdf_files as $pdf_file) {
                                if (strpos(strtolower($pdf_file), $field) !== false) {
                                    $file_name = basename($pdf_file);
                                    $file_type = wp_check_filetype($file_name, null);

                                    $attachment = array(
                                        'guid' => wp_upload_dir()['url'] . '/' . $file_name,
                                        'post_mime_type' => $file_type['type'],
                                        'post_title' => sanitize_file_name($file_name),
                                        'post_content' => '',
                                        'post_status' => 'inherit'
                                    );

                                    $uploaded = move_uploaded_file($pdf_file, wp_upload_dir()['path'] . '/' . $file_name);

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
                        } else {
                            error_log("Failed to open student ZIP file: $student_zip_path");
                        }
                    }
                }
            }

            // Show debugging on admin page when set to true (may be denied)
            define('WP_DEBUG_DISPLAY', true);
            @ini_set('display_errors', 1);


        });