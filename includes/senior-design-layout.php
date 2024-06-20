function senior_design_display() {
    // Determine the current page
    $paged = (get_query_var('paged')) ? get_query_var('paged') : 1;

    $category = isset($_GET['category']) ? $_GET['category'] : '';
    $search_term = isset($_GET['search']) ? $_GET['search'] : '';

    $args = array(
        'posts_per_page' => 10,
        'paged'          => $paged,
        'post_type'      => 'post',
        'post_status'    => 'publish',
        's'              => $search_term  // Search parameter
    );

    if (!empty($category)) {
        $args['cat'] = $category;
    } else {
        $args['cat'] = '319, 320, 322, 323, 324, 325, 326, 327, 328, 329, 330';
    }

    $post_list = get_posts($args);
    $total_posts = count(get_posts(array_merge($args, ['posts_per_page' => -1])));
    $total_pages = ceil($total_posts / $args['posts_per_page']);

    if (!empty($post_list)) {
        // Style and scripts for card and form
        echo '<style>
            .custom-card {
                border-radius: 15px;
                box-shadow: 0 3px 6px rgba(0,0,0,0.1);
                margin-bottom: 20px;
                padding: 20px;
                transition: box-shadow 0.3s ease-in-out;
            }
            .custom-card:hover {
                box-shadow: 0 4px 8px rgba(0,0,0,0.3); 
            }
            </style>';

        // Begin form
        echo '<div class="container mb-4">
                <div class="row">
                    <form action="" method="GET" class="form-inline" style="width: 100%; display: flex; justify-content: end;">';

        // Category selector
        echo '<div class="form-group mr-4">
              <select class="form-control" id="categorySelector" name="category" onchange="submitFormResetPage()" style="width: 100%;">
              <option value="">All Semesters</option>';
        $categories = get_categories(array('include' => '319, 320, 322, 323, 324, 325, 326, 327, 328, 329, 330'));
        foreach ($categories as $category_option) {
            $selected = ($category_option->term_id == $category) ? ' selected' : '';
            echo '<option value="' . esc_attr($category_option->term_id) . '"' . $selected . '>' . esc_html($category_option->name) . '</option>';
        }
        echo '</select></div>';

        // Search box
        echo '<div class="form-group">
              <div class="input-group" style="width: 100%;">
              <input class="form-control" type="text" name="search" placeholder="Search by title..." value="' . esc_attr($search_term) . '" onchange="submitFormResetPage()" style="line-height: 1.15 !important;">
              <span class="input-group-btn">
              <button class="btn btn-primary" type="submit"><i class="fa fa-search" aria-hidden="true"></i></button>
              </span></div></div>';

        echo '</form></div></div>';

        // Posts display
        echo '<div class="row mb-5">';
        foreach ($post_list as $post) {
            setup_postdata($post);
            $permalink = get_permalink($post);
            echo '<div class="card-box col-12">';
            echo '<a href="' . $permalink . '">';
            echo '<div class="card custom-card">';
            echo '<div class="card-body">';
            echo '<h5 class="card-title mb-1" style="margin-top: 2px;">' . get_the_title($post) . '</h5>';
            echo '</div></div></a></div>';
        }
        echo '</div>';

        include_pagination_logic($total_pages, $paged);  // Assume this function handles pagination

        wp_reset_postdata();
    } else {
        echo 'No posts found.';
    }

    // JavaScript to reset the page number on form change
    echo '<script>
        function submitFormResetPage() {
            var form = document.querySelector("form");
            form.action = addQueryParam(form.action, "paged", "1");
            form.submit();
        }
        function addQueryParam(url, key, value) {
            var newParam = key + "=" + value,
                params = "?" + newParam;

            if (url.indexOf("?") !== -1) {
                params = "&" + newParam;
            }

            var newUrl = url.split("?")[0] + params;
            return newUrl;
        }
    </script>';
}

