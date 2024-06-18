<?php

function senior_design_display() {

    $post_list = get_posts( array(
        'orderby'    => 'menu_order',
        'sort_order' => 'asc'
    ) );

    $posts = array();

    foreach ( $post_list as $post ) {
    $posts[] += $post->ID;
    }

    $current = array_search( get_the_ID(), $posts );

    $prevID = $posts[ $current-1 ];
    $nextID = $posts[ $current+1 ];
    ?>

    <div class="navigation">
    <?php if ( ! empty( $prevID ) ): ?>
        <div class="alignleft">
            <a href="<?php echo get_permalink( $prevID ); ?>" alt="<?php echo get_the_title( $prevID ); ?>">
                <?php _e( 'Previous', 'textdomain' ); ?>
            </a>
        </div>
    <?php endif;

    if ( ! empty( $nextID ) ) : ?>
        <div class="alignright">
            <a href="<?php echo get_permalink( $nextID ); ?>" alt="<?php echo get_the_title( $nextID ); ?>">
                <?php _e( 'Next', 'textdomain' ); ?>
            </a>
        </div>
    <?php endif; ?>
    </div>
    <?php
}