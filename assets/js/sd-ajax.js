jQuery(document).ready(function($) {
    $('form').on('submit', function(event) {
        event.preventDefault();

        var semester = $('#semester').val();
        var search = $('#search').val();
        var paged = 1;

        $.ajax({
            url: sd_ajax_obj.ajax_url,
            type: 'POST',
            data: {
                action: 'sd_project_filter',
                semester: semester,
                search: search,
                paged: paged,
            },
            success: function(response) {
                $('.sd-projects').html(response);
            }
        });
    });
});
