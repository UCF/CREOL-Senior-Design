<?php
// Add field to the add new term screen
function sd_add_semester_date_field() {
    ?>
    <div class="form-field">
        <label for="semester_date">Semester Sort Value</label>
        <input type="text" name="semester_date" id="semester_date" value="" placeholder="Example: 2025.1">
        <p>Enter the sortable semester value in the format YYYY.S (e.g., 2025.1 for Spring 2025).</p>
    </div>
    <?php
}
add_action('sd_semester_add_form_fields', 'sd_add_semester_date_field', 10, 2);

// Add field to the edit term screen
function sd_edit_semester_date_field($term) {
    $semester_date = get_term_meta($term->term_id, 'semester_date', true);
    ?>
    <tr class="form-field">
        <th scope="row"><label for="semester_date">Semester Sort Value</label></th>
        <td>
            <input type="text" name="semester_date" id="semester_date" value="<?php echo esc_attr($semester_date); ?>" placeholder="Example: 2025.1">
            <p class="description">Enter the sortable semester value in the format YYYY.S (e.g., 2025.1 for Spring 2025).</p>
        </td>
    </tr>
    <?php
}
add_action('sd_semester_edit_form_fields', 'sd_edit_semester_date_field', 10, 2);

function sd_save_semester_date_field($term_id) {
    if (isset($_POST['semester_date'])) {
        update_term_meta($term_id, 'semester_date', sanitize_text_field($_POST['semester_date']));
    }
}
add_action('created_sd_semester', 'sd_save_semester_date_field', 10, 2);
add_action('edited_sd_semester', 'sd_save_semester_date_field', 10, 2);
