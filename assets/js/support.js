// Support page JavaScript
document.addEventListener('DOMContentLoaded', function() {
    lucide.createIcons();

    // Character counter for message
    const messageTextarea = document.getElementById('message');
    if (messageTextarea) {
        messageTextarea.addEventListener('input', function () {
            const length = this.value.length;
            const minLength = 10;
            const counter = this.parentNode.querySelector('.text-gray-500');

            if (length < minLength) {
                counter.textContent = `${length}/${minLength} characters (minimum required)`;
                counter.className = 'text-sm text-red-500 mt-1';
            } else {
                counter.textContent = `${length} characters`;
                counter.className = 'text-sm text-green-500 mt-1';
            }
        });
    }

    // Priority level styling
    const prioritySelect = document.getElementById('priority');
    if (prioritySelect) {
        prioritySelect.addEventListener('change', function () {
            const priority = this.value;
            this.className = 'w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500';

            if (priority === 'urgent') {
                this.className += ' bg-red-50 border-red-300';
            } else if (priority === 'high') {
                this.className += ' bg-orange-50 border-orange-300';
            } else if (priority === 'medium') {
                this.className += ' bg-yellow-50 border-yellow-300';
            }
        });
    }
});