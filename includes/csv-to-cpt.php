<?php

        // This adds an action button to the Senior Design Projects page in WordPress with a confirmation popup
        add_action('admin_notices', function() {
            $screen = get_current_screen();
            if ($screen->post_type == 'sd_project' && $screen->base == 'edit') {
                echo "<div class='updated'>";
                echo "<p>";
                echo "To import Senior Design Projects from the CSV, click the button to the right."; // TODO: Change to better explanation
                echo "<a id='insert-sd-projects-button' class='button button-primary' style='margin:0.25em 1em' href='{$_SERVER["REQUEST_URI"]}&insert_sd_projects'>Insert Posts</a>";
                echo "</p>";
                echo "</div>";
        
                // Add progress bar HTML
                echo "<div id='progress-container' style='display: none; margin: 20px 0;'>
                    <div id='progress-bar' style='width: 0%; background: green; height: 20px;'></div>
                    <p id='progress-text'>Starting...</p>
                </div>";
        
                // Include JavaScript for progress
                ?>
                <script type="text/javascript">
                    document.getElementById('insert-sd-projects-button').addEventListener('click', function(e) {
                        if (!confirm('Are you sure you want to import the Senior Design Projects from the CSV? This action cannot be undone.')) {
                            e.preventDefault();
                        } else {
                            document.getElementById('progress-container').style.display = 'block';
                            updateProgress();
                        }
                    });
        
                    function updateProgress() {
                        var xhr = new XMLHttpRequest();
                        xhr.open('GET', '<?php echo admin_url('admin-ajax.php?action=progress_check&nonce=' . wp_create_nonce('insert_sd_projects_nonce')); ?>', true);
                        xhr.onload = function() {
                            if (xhr.status >= 200 && xhr.status < 300) {
                                var response = JSON.parse(xhr.responseText);
                                var percent = response.percent;
                                var status = response.status;
                                document.getElementById('progress-bar').style.width = percent + '%';
                                document.getElementById('progress-text').innerText = status;
                                if (percent < 100) {
                                    setTimeout(updateProgress, 1000); // Check progress every second
                                }
                            } else {
                                error_log('Failed to retrieve progress.');
                            }
                        };
                        xhr.send();
                    }
                </script>
                <?php
            }
        });

        add_action('wp_ajax_progress_check', function() {
            check_ajax_referer('insert_sd_projects_nonce', 'nonce');
            
            $offset = get_transient('sd_projects_import_offset') ?: 0;
            $total_rows = get_transient('sd_projects_import_total_rows') ?: 100; // Default to 100 if not set
        
            // Ensure total_rows is not zero to prevent division by zero errors
            $total_rows = max($total_rows, 1);
        
            $percent = min(100, ($offset / $total_rows) * 100);
            $status = ($percent < 100) ? 'Processing...' : 'Complete';
        
            wp_send_json(array(
                'percent' => $percent,
                'status' => $status
            ));
        });        

        // This executes when the page is loaded
        // VERSION 2.0
        add_action('admin_init', function() {

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
            $extracted_dir = $plugin_dir . 'extracted/';  
            
            // Create the extracted dir if it DNE
            if (!file_exists($extracted_dir)) {
                mkdir($extracted_dir, 0777, true);
                error_log("Created extracted directory: " . $extracted_dir);
            }

            // Extract the main ZIP file
            $zip = new ZipArchive;
            if ($zip->open($zip_file_path) === TRUE) {
                for ($i = 0; $i < $zip->numFiles; $i++) {
                    $filename = $zip->getNameIndex($i);
                    $fileinfo = pathinfo($filename);
                    error_log('File name: ' . $filename);

                    if (strpos($fileinfo['basename'], '.') !== false) {
                        copy("zip://$zip_file_path#$filename", $extracted_dir . $fileinfo['basename']);
                    }
                }
                $zip->close();
            } else {
                error_log('Failed to open main ZIP file.');
                return;
            }

            // Locate and read the CSV file
            // TODO: Add note to locate file name change
            $csv_file_path = $extracted_dir . 'SD_CSV_Test1.csv';
            if (!file_exists($csv_file_path)) {
                error_log('CSV file not found.');
                return;
            }

            if (($handle = fopen($csv_file_path, "r")) !== FALSE) {
                $headers = fgetcsv($handle); // Read header row
            
                // Define the batch size
                $batch_size = 10;
            
                // If a partial offset exists already in a transient, use that instead of 0
                $offset = get_transient('sd_projects_import_offset') ?: 0;
                $total_rows = count_rows($csv_file_path); // Implement this function
                set_transient('sd_projects_import_total_rows', $total_rows, 12 * HOUR_IN_SECONDS);
            
                // Initialize an empty batch array
                $batch = [];
            
                // Process each row in the CSV
                while (($rows = fgetcsv($handle)) !== FALSE) {
                    $data = array_combine($headers, $rows);
                    
                    // Start processing if we are at or past the current offset
                    if ($offset > 0) {
                        $offset--;
                        continue;
                    }
            
                    // Add the row to the batch
                    $batch[] = $data;
            
                    // If the batch size is reached, process the batch
                    if (count($batch) === $batch_size) {
                        foreach ($batch as $row) {
                            error_log(print_r($row, true));
                            process_row($row);
            
                            // Update the offset and progress
                            $offset++;
                            $percent = min(100, ($offset / $total_rows) * 100);
                            $status = ($percent < 100) ? 'Processing...' : 'Complete';
                            set_transient('sd_projects_import_offset', $offset, 12 * HOUR_IN_SECONDS);
                        }
            
                        // Clear the batch after processing
                        $batch = [];
            
                        // Sleep to avoid hitting server limits
                        sleep(1);
                    }
                }
            
                // Process any remaining rows in the batch
                if (!empty($batch)) {
                    foreach ($batch as $row) {
                        error_log(print_r($row, true));
                        process_row($row);
            
                        // Update the offset and progress
                        $offset++;
                        $percent = min(100, ($offset / $total_rows) * 100);
                        $status = ($percent < 100) ? 'Processing...' : 'Complete';
                        set_transient('sd_projects_import_offset', $offset, 12 * HOUR_IN_SECONDS);
                    }
                }
            
                fclose($handle);
                // Clear transients after completion
                delete_transient('sd_projects_import_offset');
                delete_transient('sd_projects_import_total_rows');
            } else {
                error_log('Failed to open the CSV file.');
            }            
        });

        // Count the number of rows total in the CSV
        function count_rows($csv_file_path) {
            $row_count = 0;
            if (($handle = fopen($csv_file_path, "r")) !== FALSE) {
                while (($data = fgetcsv($handle)) !== FALSE) {
                    $row_count++;
                }
                fclose($handle);
            }
            return $row_count;
        }

        // Create a CPT with processed data from a row of the CSV 
        function process_row($data) {
            // Check if a post with the same title (or another unique field) already exists
            $existing_post = get_page_by_title($data['title'], OBJECT, 'sd_project');
        
            if ($existing_post) {
                $post_id = $existing_post->ID;
                error_log('Post already exists: ' . $data['title']);
            } else {
                // Create a new CPT post
                $post_data = array(
                    'post_title'    => $data['title'],
                    'post_status'   => 'publish',   // You can change this to 'draft' if you don't want to publish immediately
                    'post_type'     => 'sd_project',
                );
                $post_id = wp_insert_post($post_data);
        
                if (is_wp_error($post_id)) {
                    error_log('Failed to create post for project: ' . $data['title']);
                    return;
                }
            }
        
            // Attach the contributors text field
            if (!empty($data['project_contributors'])) {
                update_field('project_contributors', $data['project_contributors'], $post_id);
            }
        
            // Handle ZIP extraction and file processing
            handle_zip_extraction_and_files($data['zip_file'], $post_id);
        }
        
        function handle_zip_extraction_and_files($zip_folder_name, $post_id) {
            global $extracted_dir;
            $zip_folder_path = $extracted_dir . $zip_folder_name;
        
            if (!file_exists($zip_folder_path)) {
                error_log('ZIP folder ' . $zip_folder_name . ' not found at path: ' . $zip_folder_path);
                return;
            }
        
            // Create the temp_extraction dir if it DNE
            $temp_extraction_dir = $extracted_dir . 'temp_extraction/';
            if (!file_exists($temp_extraction_dir)) {
                mkdir($temp_extraction_dir, 0777, true);
                error_log("Created extracted directory: " . $temp_extraction_dir);
            }
        
            $zip = new ZipArchive;
            if ($zip->open($zip_folder_path) === TRUE) {
                for ($i = 0; $i < $zip->numFiles; $i++) {
                    $filename = $zip->getNameIndex($i);
                    $fileinfo = pathinfo($filename);
        
                    // Extract files directly into a flat structure
                    if (strpos($fileinfo['basename'], '.') !== false) {
                        $destination_path = $temp_extraction_dir . $fileinfo['basename'];
                        if (!copy("zip://$zip_folder_path#$filename", $destination_path)) {
                            error_log('Failed to copy ' . $filename . ' to ' . $destination_path);
                        } else {
                            error_log('Successfully copied ' . $filename . ' to ' . $destination_path);
                        }
                    }
                }
                $zip->close();
            } else {
                error_log('Failed to open ZIP file: ' . $zip_folder_path);
                return;
            }
        
            // Identify and upload PDFs
            process_files_in_temp_dir($temp_extraction_dir, $post_id);
        
            // Clean up temporary extraction folder
            array_map('unlink', glob($temp_extraction_dir . '*'));
            if ($temp_extraction_dir) {
                rmdir($temp_extraction_dir);
            }
        }
        
        function process_files_in_temp_dir($temp_extraction_dir, $post_id) {
            $files = glob($temp_extraction_dir . '*');
            foreach ($files as $file_path) {
                if (strpos(basename($file_path), 'Short_Report') !== FALSE) {
                    $pdf_field = 'short_report_file';
                } elseif (strpos(basename($file_path), 'Long_Report') !== FALSE) {
                    $pdf_field = 'long_report_file';
                } elseif (strpos(basename($file_path), 'Presentation') !== FALSE) {
                    $pdf_field = 'presentation_slides_file';
                } else {
                    error_log('File ' . basename($file_path) . ' does not match expected pattern.');
                    continue;
                }
        
                // Check if the file already exists in the media library
                error_log('Calculating MD5 hash for file: ' . $file_path);
                $file_hash = md5_file($file_path);
                if ($file_hash === false) {
                    error_log('Failed to calculate MD5 hash for file: ' . $file_path);
                    continue;
                }
        
                $existing_attachment_id = check_existing_media_by_hash($file_hash);
        
                if ($existing_attachment_id) {
                    // File already exists, use the existing ID
                    update_field($pdf_field, $existing_attachment_id, $post_id);
                } else {
                    // Otherwise, upload the file and get the attachment ID
                    $attachment_id = upload_file_to_media_library($file_path);
                    if ($attachment_id !== false) {
                        update_field($pdf_field, $attachment_id, $post_id);
                    } else {
                        error_log('Failed to upload file: ' . $file_path);
                    }
                }
            }
        }

        // Checks the WP media library for a file with the given hash
        function check_existing_media_by_hash($file_hash) {
            global $wpdb;

            // Query the media library for any files with a matching hash
            $query = "
                SELECT ID
                FROM $wpdb->posts
                WHERE post_type = 'attachment'
                AND post_mime_type LIKE 'application/pdf'
                AND meta_key = '_file_hash'
                AND meta_value = %s
            ";
            $attachment_id = $wpdb->get_var($wpdb->prepare($query, $file_hash));

            return $attachment_id ? $attachment_id : false; // or return the attachment ID if found
        }        

        // Uploads a file to the WP media library
        function upload_file_to_media_library($file_path) {
            // Check if the file exists before proceeding
            if (!file_exists($file_path)) {
                error_log('File not found before upload: ' . $file_path);
                return false;
            }

            // Include WordPress file handling code
            require_once(ABSPATH . 'wp-admin/includes/file.php');
            require_once(ABSPATH . 'wp-admin/includes/media.php');
            require_once(ABSPATH . 'wp-admin/includes/image.php');

            // Handle the file upload
            $file = array(
                'name' => basename($file_path),
                'type' => mime_content_type($file_path),
                'tmp_name' => $file_path,
                'error' => 0,
                'size' => filesize($file_path)                
            );

            // Upload the file to the media library
            $attachment_id = media_handle_sideload($file, 0);

            // Check for errors after upload
            if (is_wp_error($attachment_id)) {
                error_log('Failed to upload file ' . basename($file_path) . ': ' . $attachment_id->get_error_message());
                return false;
            }

            // Generate and store the hash for the uploaded file
            $file_hash = md5_file(get_attached_file($attachment_id)); // Use the new file path
            update_post_meta($attachment_id, '_file_hash', $file_hash);

            return $attachment_id; // return the new attachment ID
        }

        // // This executes when the page is loaded
        // add_action('admin_init', function() {
        //     global $wpdb;

        //     // If the URL is not set to 'insert_sd_projects' we will return and not execute the function
        //     // TODO: Replace with a potentially safer alternative (ex: pop-up confirmation)
        //     if ( !isset($_GET['insert_sd_projects'])) {
        //         return;
        //     }

        //     $project = array(
        //         'custom-post-type' => 'sd_project',
        //     );

        //     // Define paths
        //     $plugin_dir = plugin_dir_path(__FILE__);
        //     $zip_file_path = $plugin_dir . 'data/2024_fall_sd.zip';
        //     $extracted_path = $plugin_dir . 'extracted';     

        //     // Unzip the file
        //     $zip = new ZipArchive;
        //     if ($zip->open($zip_file_path) === TRUE) {
        //         $extraction_success = $zip->extractTo($extracted_path);
        //         $zip->close();
        //         if (!$extraction_success) {
        //             error_log("Failed to extract ZIP file to: $extracted_path");
        //         } else {
        //             error_log("ZIP file extracted successfully to: $extracted_path");
        //         }
        //     } else {
        //         error_log("Failed to open ZIP file: $zip_file_path");
        //     }

        //     // List files in the extraction directory
        //     $files = glob($extracted_path . '/2024_fall_sd/data/*');
        //     error_log("Extracted files: " . print_r($files, true));

        //     // Retrieve the data from the CSV
        //     $posts = function() use ($extracted_path) {
        //         $data = array();
        //         $errors = array();

        //         $csv_file = $extracted_path . '/2024_fall_sd/data/SD_CSV_Test1.csv';

        //         // Check if the file exists before attempting to change permissions
        //         if (file_exists($csv_file)) {
        //             if (!is_readable($csv_file)) {
        //                 chmod($csv_file, 0744); // Change permissions if necessary
        //             }
        //         } else {
        //             error_log("CSV file '$csv_file' does not exist.");
        //             return $data;
        //         }

        //         if (!is_readable($csv_file)) {
        //             error_log("CSV file '$csv_file' is not readable.");
        //             return $data;
        //         }

        //         if ($_file = fopen($csv_file, 'r')) {
        //             $header = fgetcsv($_file);

        //             if ($header === false) {
        //                 $errors[] = "CSV file does not contain a valid header row.";
        //                 fclose($_file);
        //                 return $data;
        //             }

        //             while (($row = fgetcsv($_file)) !== false) {
        //                 $post = array(); // Reinitialize $post for each row

        //                 foreach ($header as $i => $key) {
        //                     $post[$key] = isset($row[$i]) ? $row[$i] : '';
        //                 }

        //                 $data[] = $post;
        //             }

        //             fclose($_file);

        //         } else {
        //             $errors[] = "CSV file '$csv_file' could not be opened";
        //         }

        //         // Log any errors
        //         if (!empty($errors)) {
        //             foreach ($errors as $error) {
        //                 error_log($error);
        //             }
        //         }

        //         return $data;
        //     };

        //     // Query to retrieve all posts that exist
        //     $post_exists = function($title) use ($wpdb, $project) {
        //         $query = $wpdb->prepare(
        //             "SELECT post_title FROM {$wpdb->posts} WHERE post_title = %s AND post_type = %s",
        //             $title,
        //             $project['custom-post-type']
        //         );
        //         return $wpdb->get_var($query) !== null;
        //     };

        //     foreach ($posts() as $post) {

        //         // If the post already exists, do not add it again
        //         if ($post_exists($post['title'])) {
        //             continue;
        //         }

        //         $post['id'] = wp_insert_post(
        //             array(
        //                 'post_title' => $post['title'],
        //                 'post_type' => $project['custom-post-type'],
        //                 'post_status' => 'publish',
        //             )
        //         );

        //         // Attach the contributors text field
        //         if (!empty($post['project_contributors'])) {
        //             update_field('project_contributors', $post['project_contributors'], $post['id']);
        //         }
                
        //         // Define file fields and their corresponding ACF fields
        //         $file_fields = [
        //             'short_report' => 'short_report_file',
        //             'long_report' => 'long_report_file',
        //             'presentation_slides' => 'presentation_slides_file'
        //         ];

        //         foreach ($file_fields as $field => $acf_field) {
        //             error_log("Processing field: $field with ACF field: $acf_field");

        //             $student_files_dir = $extracted_path . '/2024_fall_sd/student_files/';
        //             $student_zip_files = glob($student_files_dir . '*.zip');
                    
        //             error_log("Found " . count($student_zip_files) . " student ZIP files in directory: $student_files_dir");

        //             // Loop over each student ZIP
        //             foreach ($student_zip_files as $student_zip_path) {
        //                 error_log("Processing student ZIP file: $student_zip_path");

        //                 if (file_exists($student_zip_path)) {
        //                     $student_zip = new ZipArchive;

        //                     if ($student_zip->open($student_zip_path) === TRUE) {
        //                         $temp_dir = $extracted_path . '/temp/';
        //                         // If the temp directory does not exist, create it
        //                         if (!file_exists($temp_dir)) {
        //                             mkdir($temp_dir, 0755, true);
        //                             error_log("Created temporary directory: $temp_dir");
        //                         }
                            
        //                         // Iterate over each file in the ZIP archive
        //                         for ($i = 0; $i < $student_zip->numFiles; $i++) {
        //                             $zip_stat = $student_zip->statIndex($i);
        //                             $file_name = basename($zip_stat['name']);
                            
        //                             // Check if the current entry is a file (not a directory)
        //                             if (!preg_match('/\/$/', $zip_stat['name'])) {
        //                                 $file_path = $temp_dir . $file_name;
                            
        //                                 // Extract the file
        //                                 if (copy("zip://{$student_zip_path}#{$zip_stat['name']}", $file_path)) {
        //                                     error_log("Extracted file: $file_path");
        //                                 } else {
        //                                     error_log("Failed to extract file: $file_name");
        //                                 }
        //                             }
        //                         }
                            
        //                         $student_zip->close();
        //                         error_log("Finished extracting files to temporary directory: $temp_dir");
                            
        //                         // Find and upload the correct PDF files
        //                         $pdf_files = glob($temp_dir . '*.pdf');
        //                         error_log("Found " . count($pdf_files) . " PDF files in temporary directory: $temp_dir");
                            
        //                         $processed_files = [];

        //                         foreach ($pdf_files as $pdf_file) {
        //                             error_log("Checking PDF file: $pdf_file");

        //                             if (strpos(strtolower($pdf_file), $field) !== false) {
        //                                 $file_name = basename($pdf_file);

        //                                 if  (in_array($file_name, $processed_files)) {
        //                                     continue;
        //                                 }

        //                                 $file_type = wp_check_filetype($file_name, null);
        //                                 error_log("Matched PDF file: $file_name with field: $field");

        //                                 // Check if the file already exists in the media library
        //                                 $attachment_id = check_existing_media($pdf_file);

        //                                 if ($attachment_id) {
        //                                     // File exists, use the existing attachment ID
        //                                     update_field($acf_field, $attachment_id, $post['id']);
        //                                 } else {
        //                                     $attachment = array(
        //                                         'guid' => wp_upload_dir()['url'] . '/' . $file_name,
        //                                         'post_mime_type' => $file_type['type'],
        //                                         'post_title' => sanitize_file_name($file_name),
        //                                         'post_content' => '',
        //                                         'post_status' => 'inherit'
        //                                     );

        //                                     // Move file to uploads directory
        //                                     $uploaded = copy($pdf_file, wp_upload_dir()['path'] . '/' . $file_name);

        //                                     if ($uploaded) {
        //                                         error_log("Successfully uploaded file: $file_name to " . wp_upload_dir()['path']);
        //                                         $attach_id = wp_insert_attachment($attachment, wp_upload_dir()['path'] . '/' . $file_name, $post['id']);
        //                                         require_once(ABSPATH . 'wp-admin/includes/image.php');
        //                                         $attach_data = wp_generate_attachment_metadata($attach_id, wp_upload_dir()['path'] . '/' . $file_name);
        //                                         wp_update_attachment_metadata($attach_id, $attach_data);

        //                                         // Update the ACF field with the attachment ID
        //                                         update_field($acf_field, $attach_id, $post['id']);
        //                                         $processed_files[] = $file_name;
        //                                         error_log("Updated ACF field: $acf_field with attachment ID: $attach_id");
        //                                     } else {
        //                                         error_log("Failed to upload file: $file_name");
        //                                     }
        //                                 }
        //                             } else {
        //                                 error_log("PDF file: $pdf_file does not match field: $field");
        //                             }
        //                         }

        //                         // Cleanup temp directory
        //                         deleteDir($temp_dir);
        //                         error_log("Cleaned up temporary directory: $temp_dir");

        //                     } else {
        //                         error_log("Failed to open student ZIP file: $student_zip_path");
        //                     }
        //                 } else {
        //                     error_log("Student ZIP file does not exist: $student_zip_path");
        //                 }
        //             }
        //         }

        //     }

        //     // Show debugging on admin page when set to true (may be denied)
        //     define('WP_DEBUG_DISPLAY', true);
        //     @ini_set('display_errors', 1);


        // });

        // // Recursively delete all files and directories within a directory
        // function deleteDir($dirPath) {
        //     if (!is_dir($dirPath)) {
        //         return;
        //     }
        //     $files = array_diff(scandir($dirPath), array('.', '..'));
        //     foreach ($files as $file) {
        //         $filePath = $dirPath . DIRECTORY_SEPARATOR . $file;
        //         if (is_dir($filePath)) {
        //             deleteDir($filePath);
        //         } else {
        //             unlink($filePath);
        //         }
        //     }
        //     rmdir($dirPath);
        // }

        // // Function to check if the file already exists in the media library
        // function check_existing_media($file_path) {
        //     $filename = basename($file_path);
        //     $query = new WP_Query([
        //         'post_type' => 'attachment',
        //         'meta_query' => [
        //             [
        //                 'key' => '_wp_attached_file',
        //                 'value' => $filename,
        //                 'compare' => 'LIKE'
        //             ]
        //         ]
        //     ]);
        //     if ($query->have_posts()) {
        //         return $query->posts[0]->ID; // Return the ID of the existing file
        //     }
        //     return false; // File does not exist
        // }