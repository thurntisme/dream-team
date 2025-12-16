// Clear session page JavaScript
function toggleAllCheckboxes() {
    const checkboxes = document.querySelectorAll('input[name="session_items[]"]');
    const allChecked = Array.from(checkboxes).every(cb => cb.checked);
    
    checkboxes.forEach(cb => {
        cb.checked = !allChecked;
    });
}

document.addEventListener('DOMContentLoaded', function() {
    lucide.createIcons();
});