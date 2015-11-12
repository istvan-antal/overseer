$(function () {
    var localStorage = window.localStorage || {};
    
    $('.ui.checkbox').checkbox();
    $('.ui.progress').progress();

    $('.ux-issue-resolved-toggle').change(function () {
        localStorage.hideResolvedIssues = $(this).is(':checked');
        
        if (localStorage.hideResolvedIssues === 'true') {
            $('.ux-issue-resolved').addClass('hidden');
        } else {
            $('.ux-issue-resolved').removeClass('hidden');
        }
    });
    
    if (localStorage.hideResolvedIssues === 'true') {
        $('.ux-issue-resolved-toggle').attr('checked', true).trigger('change');
    }
});