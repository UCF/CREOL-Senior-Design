<?php
// Template for displaying project content

// Retreive file URLs from the fields associated with post
// These fields belong to the category Senior Design Projects
$short_report = get_field('field_short_report');
$long_report = get_field('field_long_report');
$presentation_slides = get_field('field_presentation_slides');
?>
<div class="project-details">
    <h2><?php the_title(); ?></h2>
    <div class="project-reports">
        <a href="<?php echo esc_url($short_report); ?>">8-Page Report</a>
        <a href="<?php echo esc_url($long_report); ?>">100-Page Report</a>
        <a href="<?php echo esc_url($presentation_slides); ?>">Presentation Slides</a>
    </div>
</div>
