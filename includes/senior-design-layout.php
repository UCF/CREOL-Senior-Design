<?php

function senior_design_display() {
    $the_query = new WP_Query( array( 'category_name' => '330' ) );
    if ( $the_query->have_posts() ) {
        echo '<ul>';
        while ( $the_query->have_posts() ) {
            $the_query->the_post();
            echo '<li>' . esc_html( get_the_title() ) . '</li>';
        }
        echo '</ul>';
    } else {
        esc_html_e( 'Sorry, no posts matched your criteria.' );
    }
    // Restore original Post Data.
    wp_reset_postdata();
}
