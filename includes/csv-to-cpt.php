<?php
    /**
     * This file contains all the code related to the import process of CREOL Senior Design Projects via a CSV file.
     * 
     * Goals:
     *      1. Automate the process of creating posts for CREOL Senior Design Projects each semester.
     *      2. Utilize a single ZIP folder to encompass all necessary files for each Senior Design class. This is for easy transportation from the professor.
     *      3. Create a process within the WordPress admin page that is both smooth and safe.
     *      4. Provide ample user feedback to the WP admin utilizing this tool.
     *      5. Allow for the automation to update existing posts.
     * 
     * Helpful resources:
     *      https://www.geeksforgeeks.org/how-to-parse-a-csv-file-in-php/
     *      https://github.com/ezekg/sitepoint-programmatically-insert-wp-posts/tree/master
     *      https://developer.wordpress.org/
     * 
     * TODO:
     *      1. Integrate automation of the 'Semesters' Taxonomy.
     *      2. Create a PDF containing instructions for professors (and therefore students).
     *      3. Add additional visual feedback for the WP admin (# of posts added, errors, etc.).
     *      4. Allow for the automation to update existing posts.
     *      5. Add "autocorrection" of CSV fields.
     */

        // Adds an action button to the Senior Design Projects page in WordPress for ZIP file upload
        add_action('admin_notices', function() {
            $screen = get_current_screen();
            if ($screen->post_type == 'sd_project' && $screen->base == 'edit') {
                echo "<div class='updated'>";
                echo "<p>To import Senior Design Projects from a ZIP file, upload the ZIP file using the button below.</p>";
                echo "</div>";
                
                // Form for file upload
                echo "<form method='post' enctype='multipart/form-data' style='margin: 20px 0;'>";
                echo "<input type='file' name='zip_file' id='zip_file' accept='.zip' required />";
                echo "<input type='submit' class='button button-primary' value='Upload ZIP File' />";
                echo "</form>";

                // Handle file upload
                if (isset($_FILES['zip_file']) && !empty($_FILES['zip_file']['name'])) {
                    handle_zip_file_upload(); // Call your file handling function here
                }
            }
        });

        // Handles the file upload and parsing of the ZIP file
        function handle_zip_file_upload() {
            if (!current_user_can('manage_options')) {
                wp_die('You do not have sufficient permissions to upload files.');
            }

            // Check the file is a ZIP.
            $file = $_FILES['zip_file'];
            $file_type = wp_check_filetype($file['name']);

            if ($file_type['ext'] !== 'zip') {
                echo '<div class="notice notice-error is-dismissible"><p>Only ZIP files are allowed.</p></div>';
                return;
            }

            // Handle file upload.
            $uploaded_file = wp_handle_upload($file, ['test_form' => false]);

            if ($uploaded_file && !isset($uploaded_file['error'])) {
                $file_path = $uploaded_file['file']; // Get the full path of the uploaded file

                // Call your ZIP parsing function here.
                parse_zip_file($file_path); // Pass the file path to your parsing function.

                echo '<div class="notice notice-success is-dismissible"><p>File uploaded successfully and parsing started.</p></div>';
            } else {
                echo '<div class="notice notice-error is-dismissible"><p>Error: ' . esc_html($uploaded_file['error']) . '</p></div>';
            }
        }

        // This executes when the page is loaded after clicking the action button
        add_action('admin_init', function() {
            if ( !isset($_GET['insert_sd_projects'])) {
                return;
            }

            // No longer define $zip_file_path here; use the uploaded file instead
            $upload_dir = wp_upload_dir();
            $zip_file_path = $upload_dir['basedir'] . '/' . basename($_FILES['zip_file']['name']); // Adjusting to use the uploaded file path

            global $extracted_dir;
            $extracted_dir = plugin_dir_path(__FILE__) . 'extracted/';  

            // Ensure extraction directory exists
            if (!file_exists($extracted_dir)) {
                mkdir($extracted_dir, 0777, true);
            }

            // Extract the uploaded ZIP file
            $zip = new ZipArchive;
            if ($zip->open($zip_file_path) === TRUE) {
                for ($i = 0; $i < $zip->numFiles; $i++) {
                    $filename = $zip->getNameIndex($i);
                    $fileinfo = pathinfo($filename);
                    // Check if the file has a valid extension and copy it
                    if (strpos($fileinfo['basename'], '.') !== false) {
                        copy("zip://$zip_file_path#$filename", $extracted_dir . $fileinfo['basename']);
                    }
                }
                $zip->close();
            } else {
                error_log('Failed to open uploaded ZIP file.');
                return;
            }

            // Locate and read the CSV file for importing data
            $csv_file_path = $extracted_dir . 'SD_CSV_Test1.csv'; // This is subject to change
            if (!file_exists($csv_file_path)) {
                error_log('CSV file not found.');
                return;
            }

            if (($handle = fopen($csv_file_path, "r")) !== FALSE) {
                // Reads the header row from the CSV file
                $headers = fgetcsv($handle); // *These headers are subject to change
            
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

            /** 
             * Goal: Assign semester taxonomies to our SD projects
             * 
             * Info:
             * Semester taxonomy slug: sd_semester
             * Semester slugs: spring_2024, summer_2024, etc.
             * Semester labels: Spring 2024, Summer 2024, etc.
             * CPT slug: sd_project
             * 
             * Steps:
             * 1. Parse the column containing the taxonomy
             * 2. Normalize the data
             * 3. Match it to an existing taxonomy (if it doesn't match, don't add the project OR
             * create a new taxonomy)
             * 4. Log any errors for later review
            */
            if (!empty($data['semester'])) {
                $csv_semester = $data['semester'];
                $semester = trim(ucwords(strtolower($csv_semester)));
                $exists = term_exists($semester, 'sd_semester');
                if ($exists) {
                    // If last param is false, the semester will replace any old semesters
                    wp_set_object_terms($post_id, $semester, 'sd_semester', false);
                } else {
                    error_log('Error: Semester "' . $semester . '" from post ID ' . $post_id . ' does not exist in the "sd_semester" taxonomy.');
                }
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
                // *These ACF field names are subject to change
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