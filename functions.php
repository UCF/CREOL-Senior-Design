function sd_orderby_taxonomy( $clauses, $query ) {
    if ( isset( $query->query_vars['orderby_taxonomy'] ) && 'sd_semester' === $query->query_vars['orderby_taxonomy'] ) {
        global $wpdb;
        
        // Join term relationships for the taxonomy
        $clauses['join'] .= " 
            LEFT JOIN {$wpdb->term_relationships} AS tr ON {$wpdb->posts}.ID = tr.object_id
            LEFT JOIN {$wpdb->term_taxonomy} AS tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
            LEFT JOIN {$wpdb->terms} AS t ON tt.term_id = t.term_id";
        
        // Ensure we're filtering to the correct taxonomy 
        $clauses['where'] .= $wpdb->prepare( " AND tt.taxonomy = %s", 'sd_semester' );
        
        // Set the ORDER BY to be the term name (or change to term_id as needed)
        $order = strtoupper( $query->get( 'order' ) ) === 'DESC' ? 'DESC' : 'ASC';
        $clauses['orderby'] = " t.name " . $order;
    }
    return $clauses;
}
add_filter( 'posts_clauses', 'sd_orderby_taxonomy', 10, 2 );