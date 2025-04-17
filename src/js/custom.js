


// Unique logic for select2 multi-selects

jQuery(document).ready(function($) {
    $('#multiSemesterSelector').select2({
        placeholder: 'Select semesters',
        allowClear: true,
    });

    $('#utility-bar').on('submit', function(event) {
        event.preventDefault();
        var url = new URL(window.location);
        var params = new URLSearchParams(url.search);

        var search = $('#searchFilter').val().trim();
        if (search) {
            params.set('search', search);
        } else {
            params.delete('search');
        }

        var selectedSemesters = $('#multiSemesterSelector').val();
        if (selectedSemesters && selectedSemesters.length > 0) {
            params.set('selected_semesters', selectedSemesters.join(','));
        } else {
            params.delete('selected_semesters');
        }

        params.set('paged', 1);
        window.location.search = params.toString();
    });

    $('.pagination a').on('click', function(event) {
        event.preventDefault();
        var page = $(this).data('page');
        var url = new URL(window.location);
        var params = new URLSearchParams(url.search);
        params.set('paged', page);
        window.location.search = params.toString();
    });
});