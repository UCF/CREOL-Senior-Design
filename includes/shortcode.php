<?php
// Shortcode to display projects with filter, search, and pagination
function sd_project_display($atts) {
    ob_start();

    // Your existing logic for querying posts and displaying content
    $semester = isset($_GET['semester']) ? sanitize_text_field($_GET['semester']) : '';
    $search = isset($_GET['search']) ? sanitize_text_field($_GET['search']) : '';
    $paged = (get_query_var('paged')) ? get_query_var('paged') : 1;

    $args = array(
        'post_type' => 'sd_project',
        'posts_per_page' => 10,
        'paged' => $paged,
        's' => $search,
    );

    if ($semester) {
        $args['tax_query'] = array(
            array(
                'taxonomy' => 'sd_semester',
                'field' => 'slug',
                'terms' => $semester,
            ),
        );
    }

    $post_list = get_posts($args);

    // Output the HTML for your shortcode
    ?>
    <div class="container mb-4">
        <!-- Semester dropdown and search form -->
        <form class="form-inline" id="utility-bar" method="GET" action="" style="width: 100%; display: flex; justify-content: end;">
            <div class="form-group mr-4">
                <select class="form-control" id="semesterSelector" name="semester" style="width: 100%;">
                    <option value="">All Semesters</option>
                    <?php
                    $terms = get_terms(array(
                        'taxonomy' => 'sd_semester',
                        'hide_empty' => false,
                    ));

                    foreach ($terms as $term) {
                        $selected = ($semester == $term->slug) ? 'selected="selected"' : '';
                        echo '<option value="' . esc_attr($term->slug) . '" ' . $selected . '>' . esc_html($term->name) . '</option>';
                    }
                    ?>
                </select>
            </div>
            <div class="form-group">
                <div class="input-group" style="width: 100%;">
                    <input class="form-control" type="text" id="searchFilter" name="search" placeholder="Search by title" value="<?php echo esc_attr($search); ?>" style="line-height: 1.15 !important;">
                    <span class="input-group-btn">
                        <button class="btn btn-primary" type="submit"><i class="fa fa-search" aria-hidden="true"></i></button>
                    </span>
                </div>
            </div>
        </form>
    </div>

    <div class="sd-projects">
        <?php
        if (!empty($post_list)) {
            foreach ($post_list as $post) {
                setup_postdata($post);
                $short_report = get_field('short_report_file');
                $long_report = get_field('long_report_file');
                $presentation = get_field('presentation_slides_file');
                $contributors = get_field('project_contributors');

                echo '<div class="card-box col-12">';
                echo '<div class="card custom-card">';
                echo '    <div class="card-body">';
                echo '        <h5 class="card-title my-3">' . get_the_title() . '</h5>';
                if ($contributors)
                    echo '        <p class="my-1">' . esc_html($contributors) . '</p>';
                if ($short_report || $long_report || $presentation) {
                    echo '        <p class="my-1"><strong>View: </strong>';
                    if ($short_report)
                        echo '            <a href="' . esc_url($short_report) . '" target="_blank">Short Report</a> | ';
                    if ($long_report)
                        echo '            <a href="' . esc_url($long_report) . '" target="_blank">Long Report</a> | ';
                    if ($presentation)
                        echo '            <a href="' . esc_url($presentation) . '" target="_blank">Presentation</a>';
                    echo '        </p>';
                }
                echo '    </div>';
                echo '</div>';
                echo '</div>';
            }
            wp_reset_postdata();
        } else {
            echo '<p>No projects found.</p>';
        }
        ?>
    </div>

    <?php
    return ob_get_clean();
}
add_shortcode('sd_project_display', 'sd_project_display');
