// Install page JavaScript
document.addEventListener('DOMContentLoaded', function() {
    lucide.createIcons();

    // SweetAlert for reset system button
    document.getElementById('resetSystemBtn')?.addEventListener('click', function () {
        Swal.fire({
            icon: 'warning',
            title: 'Reset System?',
            text: 'This will delete all data and users! This action cannot be undone.',
            showCancelButton: true,
            confirmButtonColor: '#ef4444',
            cancelButtonColor: '#6b7280',
            confirmButtonText: 'Yes, Reset System',
            cancelButtonText: 'Cancel'
        }).then((result) => {
            if (result.isConfirmed) {
                // Create a form and submit it
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = '<input type="hidden" name="reset" value="1">';
                document.body.appendChild(form);
                form.submit();
            }
        });
    });

    // SweetAlert for reset database button
    document.getElementById('resetDatabaseBtn')?.addEventListener('click', function () {
        Swal.fire({
            icon: 'warning',
            title: 'Reset Database?',
            text: 'This will delete all data! This action cannot be undone.',
            showCancelButton: true,
            confirmButtonColor: '#ef4444',
            cancelButtonColor: '#6b7280',
            confirmButtonText: 'Yes, Reset Database',
            cancelButtonText: 'Cancel'
        }).then((result) => {
            if (result.isConfirmed) {
                // Create a form and submit it
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = '<input type="hidden" name="reset" value="1">';
                document.body.appendChild(form);
                form.submit();
            }
        });
    });
});