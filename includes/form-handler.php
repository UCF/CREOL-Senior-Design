<?php
// Handle Student Submission
function handle_student_submission() {
    if (isset($_POST['project_title'])) {
        $post_id = wp_insert_post(array(
            'post_type' => 'project',
            'post_title' => sanitize_text_field($_POST['project_title']),
            'post_status' => 'publish'
        ));
        if ($post_id) {
            update_field('field_8_page_report', sanitize_text_field($_POST['eight_page_report']), $post_id);
            update_field('field_100_page_report', sanitize_text_field($_POST['hundred_page_report']), $post_id);
            update_field('field_presentation_slides', sanitize_text_field($_POST['presentation_slides']), $post_id);
        }
    }
}
add_action('admin_post_nopriv_submit_project', 'handle_student_submission');
add_action('admin_post_submit_project', 'handle_student_submission');
?>
