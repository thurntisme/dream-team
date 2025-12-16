// Welcome page JavaScript
document.addEventListener('DOMContentLoaded', function() {
    $('#clubForm').submit(function (e) {
        e.preventDefault();
        $.post('api/save_club_api.php', $(this).serialize(), function (response) {
            if (response.redirect) {
                window.location.href = response.redirect;
            } else if (response.success) {
                window.location.href = '/team';
            }
        }, 'json');
    });
});