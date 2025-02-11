<?php
/**
 * Plugin Name: SD Project Display Shortcode
 * Description: Shortcode to display projects with filtering, search, pagination, and sorting by sd_semester (using term meta "semester_date"). Projects are grouped by semester.
 * Version: 1.3
 * Author: Your Name
 *
 * Note:
 * Working combinations:
 *  - Single semester, no year
 *  - No semester, single year
 *  - No semester, no year
 *  - Single semester, single year
 *
 * Broken combinations (before):
 *  - Single semester, many years
 *  - Many semesters, no year
 *  - Many semesters, single year
 *  - Many semesters, many years
 *  - No semester, many years
 *
 * The issues were due to the WHERE clause combining conditions with AND
 * when multiple values were provided. In this refactor, we always build a single
 * filtering condition using OR so that projects matching ANY allowed value are returned.
 */

/**
 * Filter function to modify WP_Query clauses when ordering by the taxonomy term meta 'semester_date'
 * and filtering by selected years and semesters.
 *
 * This function joins all necessary taxonomy tables (term_relationships, term_taxonomy, terms, termmeta)
 * so that we can both order by the numeric "semester_date" value and filter projects according to
 * the selected academic year(s) and semester(s).
 *
 * @param array    $clauses The query clauses.
 * @param WP_Query $query   The current WP_Query instance.
 * @return array Modified query clauses.
 */
function sd_orderby_semester_date( $clauses, $query ) {
    global $wpdb;
    
    if ( 'sd_semester_date' === $query->get('orderby') ) {
        // Join the taxonomy tables to get our term meta.
        $clauses['join'] .= " 
            LEFT JOIN {$wpdb->term_relationships} AS tr ON {$wpdb->posts}.ID = tr.object_id 
            LEFT JOIN {$wpdb->term_taxonomy} AS tt ON tr.term_taxonomy_id = tt.term_taxonomy_id 
            LEFT JOIN {$wpdb->terms} AS t ON tt.term_id = t.term_id 
            LEFT JOIN {$wpdb->termmeta} AS tm ON t.term_id = tm.term_id AND tm.meta_key = 'semester_date' 
        ";
        
        // Limit the query to the 'sd_semester' taxonomy.
        $clauses['where'] .= " AND tt.taxonomy = 'sd_semester' ";
        
        // Set the order direction.
        $order = strtoupper( $query->get('order') );
        if ( ! in_array( $order, array( 'ASC', 'DESC' ) ) ) {
            $order = 'DESC';
        }
        
        // Order by the numeric value (casting the meta value as DECIMAL), then term name, then post date.
        $clauses['orderby'] = "CAST(tm.meta_value AS DECIMAL(10,2)) $order, t.name $order, {$wpdb->posts}.post_date DESC";
        
        // --- Filtering: Build WHERE clause for selected years and semesters ---
        $selected_years = $query->get('selected_years');     // Expect an array of years, e.g. [2020,2021]
        $selected_semesters = $query->get('selected_semesters'); // Expect an array of semester names, e.g. ["Summer"]
        
        // Only build conditions if at least one filter is provided.
        if ( ! empty($selected_years) || ! empty($selected_semesters) ) {
            $conditions = array();
            // Mapping 
