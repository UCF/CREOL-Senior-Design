<?php

        // Adds an action button to the Senior Design Projects page in WordPress with a confirmation popup
        // This button triggers the import process of Senior Design Projects from a CSV file with a progress bar
        add_action('admin_notices', function() {
            $screen = get_current_screen();
            if ($screen->post_type == 'sd_project' && $screen->base == 'edit') {
                echo "<div class='updated'>";
                echo "<p>";
                echo "To import Senior Design Projects from the CSV, click the button to the right."; // TODO: Improve explanation for users
                echo "<a id='insert-sd-projects-button' class='button button-primary' style='margin:0.25em 1em' href='{$_SERVER["REQUEST_URI"]}&insert_sd_projects'>Insert Posts</a>";
                echo "</p>";
                echo "</div>";
        
                // Adds the HTML structure for the progress bar that appears during the import process
                echo "<div id='progress-container' style='display: none; margin: 20px 0;'>
                    <div id='progress-bar' style='width: 0%; background: green; height: 20px;'></div>
                    <p id='progress-text'>Starting...</p>
                </div>";
        
                // JavaScript for handling the progress bar and import confirmation
                ?>
                <script type="text/javascript">
                    document.getElementById('insert-sd-projects-button').addEventListener('click', function(e) {
                        // Shows a confirmation popup before starting the import process
                        if (!confirm('Are you sure you want to import the Senior Design Projects from the CSV? This action cannot be undone.')) {
                            e.preventDefault();
                        } else {
                            // Shows the progress bar and starts the update process
                            document.getElementById('progress-container').style.display = 'block';
                            updateProgress();
                        }
                    });
        
                    // Function to update the progress bar by querying the server
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
                                // Continuously updates the progress until it reaches 100%
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

        // Handles the AJAX request to check the progress of the import process
        add_action('wp_ajax_progress_check', function() {
            check_ajax_referer('insert_sd_projects_nonce', 'nonce');
            
            // Retrieves the current offset and total rows to calculate progress
            $offset = get_transient('sd_projects_import_offset') ?: 0;
            $total_rows = get_transient('sd_projects_import_total_rows') ?: 100; // Default to 100 if not set
        
            // Prevents division by zero errors
            $total_rows = max($total_rows, 1);
        
            // Calculates the percentage of progress based on the offset and total rows
            $percent = min(100, ($offset / $total_rows) * 100);
            $status = ($percent < 100) ? 'Processing...' : 'Complete';
        
            // Sends a JSON response with the progress percentage and status
            wp_send_json(array(
                'percent' => $percent,
                'status' => $status
            ));
        });        

        // This executes when the page is loaded
        add_action('admin_init', function() {

            // If the URL is not set to 'insert_sd_projects', the function will return without executing
            if ( !isset($_GET['insert_sd_projects'])) {
                return;
            }

            // Defines the custom post type (CPT) for Senior Design Projects
            $project = array(
                'custom-post-type' => 'sd_project',
            );
            
            // Define paths for plugin directory, ZIP file, and extraction directory
            $plugin_dir = plugin_dir_path(__FILE__);
            $zip_file_path = $plugin_dir . 'data/2024_fall_sd.zip';
            $extracted_dir = $plugin_dir . 'extracted/';  
            
            // Creates the extraction directory if it doesn't exist
            if (!file_exists($extracted_dir)) {
                mkdir($extracted_dir, 0777, true);
                error_log("Created extracted directory: " . $extracted_dir);
            }

            // Extracts the contents of the main ZIP file
            $zip = new ZipArchive;
            if ($zip->open($zip_file_path) === TRUE) {
                for ($i = 0; $i < $zip->numFiles; $i++) {
                    $filename = $zip->getNameIndex($i);
                    $fileinfo = pathinfo($filename);
                    error_log('File name: ' . $filename);

                    // Copies the files directly from the ZIP to the extraction directory
                    // This avoids nested directories
                    if (strpos($fileinfo['basename'], '.') !== false) {
                        copy("zip://$zip_file_path#$filename", $extracted_dir . $fileinfo['basename']);
                    }
                }
                $zip->close();
            } else {
                error_log('Failed to open main ZIP file.');
                return;
            }

            // Locates and reads the CSV file for importing data
            $csv_file_path = $extracted_dir . 'SD_CSV_Test1.csv'; // *This is subject to change
            if (!file_exists($csv_file_path)) {
                error_log('CSV file not found.');
                return;
            }

            if (($handle = fopen($csv_file_path, "r")) !== FALSE) {
                $headers = fgetcsv($handle); // Reads the header row from the CSV file
            
                // Define the batch size for processing rows
                $batch_size = 10;
            
                // Retrieves the current offset or sets it to 0 if not found
                $offset = get_transient('sd_projects_import_offset') ?: 0;
                $total_rows = count_rows($csv_file_path); // Counts the total number of rows in the CSV
                set_transient('sd_projects_import_total_rows', $total_rows, 12 * HOUR_IN_SECONDS);
            
                // Initializes an empty batch array to hold CSV rows for processing
                $batch = [];
            
                // Process each row in the CSV
                while (($rows = fgetcsv($handle)) !== FALSE) {
                    $data = array_combine($headers, $rows);
                    
                    // Skips rows until the current offset is reached
                    if ($offset > 0) {
                        $offset--;
                        continue;
                    }
            
                    // Adds the row to the batch
                    $batch[] = $data;
            
                    // Processes the batch if the batch size is reached
                    if (count($batch) === $batch_size) {
                        foreach ($batch as $row) {
                            error_log(print_r($row, true));
                            process_row($row);
            
                            // Updates the offset and progress
                            $offset++;
                            $percent = min(100, ($offset / $total_rows) * 100);
                            $status = ($percent < 100) ? 'Processing...' : 'Complete';
                            set_transient('sd_projects_import_offset', $offset, 12 * HOUR_IN_SECONDS);
                        }
            
                        // Clears the batch after processing
                        $batch = [];
            
                        // Sleep to avoid hitting server limits
                        sleep(1);
                    }
                }
            
                // Processes any remaining rows in the batch
                if (!empty($batch)) {
                    foreach ($batch as $row) {
                        error_log(print_r($row, true));
                        process_row($row);
            
                        // Updates the offset and progress
                        $offset++;
                        $percent = min(100, ($offset / $total_rows) * 100);
                        $status = ($percent < 100) ? 'Processing...' : 'Complete';
                        set_transient('sd_projects_import_offset', $offset, 12 * HOUR_IN_SECONDS);
                    }
                }
            
                fclose($handle);

                // Clears transients after completion
                delete_transient('sd_projects_import_offset');
                delete_transient('sd_projects_import_total_rows');
            } else {
                error_log('Failed to open the CSV file.');
            }            
        });

        // Counts the total number of rows in the CSV
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

        // Creates a CPT with processed data from a row of the CSV 
        function process_row($data) {
            // Checks if a post with the same title already exists to prevent duplicates
            $existing_post = get_page_by_title($data['title'], OBJECT, 'sd_project');
        
            if ($existing_post) {
                $post_id = $existing_post->ID;
                error_log('Post already exists: ' . $data['title']);
            } else {
                // Creates a new CPT post if one does not exist
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
        
            // Attaches the contributors text field to the CPT
            if (!empty($data['project_contributors'])) {
                update_field('project_contributors', $data['project_contributors'], $post_id);
            }
        
            // Handles the ZIP extraction and file processing for the PDF files
            handle_zip_extraction_and_files($data['zip_file'], $post_id);
        }

        /**
         * Handles the extraction of files from a ZIP folder and processes them.
         *
         * @param string $zip_folder_name The name of the ZIP folder to extract.
         * @param int $post_id The ID of the post where ACF fields will be updated.
         */        
        function handle_zip_extraction_and_files($zip_folder_name, $post_id) {
            global $extracted_dir;

            // Define the path for the ZIP folder
            $zip_folder_path = $extracted_dir . $zip_folder_name;
        
            // Check if the ZIP folder exists
            if (!file_exists($zip_folder_path)) {
                error_log('ZIP folder ' . $zip_folder_name . ' not found at path: ' . $zip_folder_path);
                return;
            }
        
            // Create a temporary directory for extraction if it does not exist
            $temp_extraction_dir = $extracted_dir . 'temp_extraction/';
            if (!file_exists($temp_extraction_dir)) {
                mkdir($temp_extraction_dir, 0777, true);
                error_log("Created extracted directory: " . $temp_extraction_dir);
            }
        
            // Open the ZIP file
            $zip = new ZipArchive;
            if ($zip->open($zip_folder_path) === TRUE) {
                // Loop through each file in the ZIP archive
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
        
            // Process the extracted files and update the corresponding ACF fields
            process_files_in_temp_dir($temp_extraction_dir, $post_id);
        
            // Clean up temporary extraction folder
            array_map('unlink', glob($temp_extraction_dir . '*'));
            if ($temp_extraction_dir) {
                rmdir($temp_extraction_dir);
            }
        }
        
        /**
         * Processes files in the temporary extraction directory and updates ACF fields.
         *
         * @param string $temp_extraction_dir The path to the temporary extraction directory.
         * @param int $post_id The ID of the post where ACF fields will be updated.
         */
        function process_files_in_temp_dir($temp_extraction_dir, $post_id) {
            $files = glob($temp_extraction_dir . '*');
            foreach ($files as $file_path) {

                // Determine the appropriate ACF field based on the file name
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
        
                // Check if the file already exists in the media library by hash
                error_log('Calculating MD5 hash for file: ' . $file_path);
                $file_hash = md5_file($file_path);
                if ($file_hash === false) {
                    error_log('Failed to calculate MD5 hash for file: ' . $file_path);
                    continue;
                }
        
                $existing_attachment_id = check_existing_media_by_hash($file_hash);
        
                // If not found by hash, check by file name
                if (!$existing_attachment_id) {
                    $existing_attachment_id = check_existing_media_by_name(basename($file_path));
                }

                if ($existing_attachment_id) {
                    // File already exists, use the existing ID
                    update_field($pdf_field, $existing_attachment_id, $post_id);
                } else {
                    // Otherwise, upload the file and update the ACF field with the new attachment ID
                    $attachment_id = upload_file_to_media_library($file_path);
                    if ($attachment_id !== false) {
                        update_field($pdf_field, $attachment_id, $post_id);
                    } else {
                        error_log('Failed to upload file: ' . $file_path);
                    }
                }
            }
        }
        
        /**
         * Checks the WP media library for a file with the given name.
         *
         * @param string $file_name The name of the file to check.
         * @return int|false The attachment ID if found, false otherwise.
         */
        function check_existing_media_by_name($file_name) {
            $args = array(
                'post_type'   => 'attachment',
                'post_status' => 'inherit',
                'meta_query'  => array(
                    array(
                        'key'     => '_wp_attached_file',
                        'value'   => $file_name,
                        'compare' => 'LIKE',
                    ),
                ),
            );
            
            $query = new WP_Query($args);
            
            if ($query->have_posts()) {
                return $query->posts[0]->ID;
            }
            
            return false;
        }

        /**
         * Checks the WP media library for a file with the given hash.
         *
         * @param string $file_hash The MD5 hash of the file to check.
         * @return int|false The attachment ID if found, false otherwise.
         */
        function check_existing_media_by_hash($file_hash) {
            global $wpdb;

            // Query the media library for files with a matching hash
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

        /**
         * Uploads a file to the WP media library.
         *
         * @param string $file_path The path to the file to upload.
         * @return int|false The attachment ID if the upload was successful, false otherwise.
         */
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

            // Prepare the file for upload
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