/**
 * Layout JavaScript
 * Main JavaScript functionality for the Dream Team application layout
 */

// Initialize when DOM is ready
document.addEventListener('DOMContentLoaded', function() {
    // Initialize Lucide icons
    lucide.createIcons();
});

/**
 * Modal/Popup Management Functions
 * Handle opening/closing modals and managing body scroll
 */

// Open modal and disable body scroll
window.openModal = function(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        // Show modal
        modal.classList.remove('hidden');
        
        // Update ARIA attributes
        modal.setAttribute('aria-hidden', 'false');
        
        // Disable body scroll
        document.body.style.overflow = 'hidden';
        document.body.style.paddingRight = getScrollbarWidth() + 'px';
        
        // Add modal-open class for additional styling if needed
        document.body.classList.add('modal-open');
        
        // Focus the modal for keyboard navigation
        modal.focus();
        
        // Trigger custom event
        const event = new CustomEvent('modalOpened', { detail: { modalId: modalId } });
        document.dispatchEvent(event);
    }
};

// Close modal and enable body scroll
window.closeModal = function(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        // Hide modal
        modal.classList.add('hidden');
        
        // Update ARIA attributes
        modal.setAttribute('aria-hidden', 'true');
        
        // Check if there are any other open modals by role and aria-hidden
        const openModals = document.querySelectorAll('[role="dialog"][aria-hidden="false"]');
        
        // Only re-enable scroll if no other modals are open
        if (openModals.length === 0) {
            document.body.style.overflow = '';
            document.body.style.paddingRight = '';
            document.body.classList.remove('modal-open');
        }
        
        // Trigger custom event
        const event = new CustomEvent('modalClosed', { detail: { modalId: modalId } });
        document.dispatchEvent(event);
    }
};

// Toggle modal (open if closed, close if open)
window.toggleModal = function(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        if (modal.classList.contains('hidden')) {
            openModal(modalId);
        } else {
            closeModal(modalId);
        }
    }
};

// Get scrollbar width (to prevent layout shift when disabling scroll)
function getScrollbarWidth() {
    // Create temporary div to measure scrollbar width
    const outer = document.createElement('div');
    outer.style.visibility = 'hidden';
    outer.style.overflow = 'scroll';
    outer.style.msOverflowStyle = 'scrollbar';
    document.body.appendChild(outer);
    
    const inner = document.createElement('div');
    outer.appendChild(inner);
    
    const scrollbarWidth = outer.offsetWidth - inner.offsetWidth;
    
    outer.parentNode.removeChild(outer);
    
    return scrollbarWidth;
}

// Close modal when clicking outside (on backdrop)
window.setupModalBackdropClose = function(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.addEventListener('click', function(e) {
            // Close if clicking directly on the modal backdrop (not on modal content)
            if (e.target === modal) {
                closeModal(modalId);
            }
        });
    }
};

// Close modal when pressing Escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        // Find all open modals
        const openModals = document.querySelectorAll('[id$="Modal"]:not(.hidden), [id$="modal"]:not(.hidden)');
        
        // Close the most recently opened modal
        if (openModals.length > 0) {
            const lastModal = openModals[openModals.length - 1];
            closeModal(lastModal.id);
        }
    }
});

// Global level-up notification handler
window.handleLevelUpNotification = function (response) {
    if (response && response.level_up) {
        const levelUp = response.level_up;
        const levelsGained = levelUp.levels_gained || 1;
        const newLevel = levelUp.new_level;

        // Show celebration notification
        Swal.fire({
            title: '🎉 Level Up!',
            html: `
                <div class="text-center">
                    <div class="text-6xl mb-4">⭐</div>
                    <div class="text-xl font-bold text-purple-600 mb-2">
                        Club Level ${newLevel}
                    </div>
                    <div class="text-gray-600 mb-4">
                        ${levelsGained > 1 ? `Gained ${levelsGained} levels!` : 'Level up achieved!'}
                    </div>
                    <div class="bg-gradient-to-r from-purple-100 to-blue-100 rounded-lg p-3 text-sm">
                        <div class="font-semibold text-purple-800">New Benefits Unlocked!</div>
                        <div class="text-purple-700 mt-1">
                            • Increased match rewards<br>
                            • Better player development<br>
                            • Enhanced club prestige
                        </div>
                    </div>
                </div>
            `,
            icon: 'success',
            confirmButtonColor: '#7c3aed',
            confirmButtonText: 'Awesome!',
            showClass: {
                popup: 'animate__animated animate__bounceIn'
            },
            hideClass: {
                popup: 'animate__animated animate__bounceOut'
            }
        }).then(() => {
            // Refresh the page to update level displays
            window.location.reload();
        });
    }
};

// Enhanced AJAX success handler for level-ups
window.handleApiResponse = function (response, successCallback) {
    if (response.success) {
        // Handle level up first if present
        if (response.level_up) {
            handleLevelUpNotification(response);
        } else if (successCallback) {
            successCallback(response);
        }
    } else {
        // Handle error
        Swal.fire({
            icon: 'error',
            title: 'Error',
            text: response.message || 'An error occurred',
            confirmButtonColor: '#ef4444'
        });
    }
};

// Session management function
window.initSessionManagement = function(sessionExpireTime) {
    // Check session expiration every minute
    setInterval(function () {
        const now = Math.floor(Date.now() / 1000);
        const timeLeft = sessionExpireTime - now;

        // Show warning 5 minutes before expiration
        if (timeLeft <= 300 && timeLeft > 0) {
            const minutes = Math.floor(timeLeft / 60);
            const seconds = timeLeft % 60;

            Swal.fire({
                title: 'Session Expiring Soon',
                text: `Your session will expire in ${minutes}:${seconds.toString().padStart(2, '0')}. Do you want to extend it?`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#3085d6',
                cancelButtonColor: '#d33',
                confirmButtonText: 'Extend Session',
                cancelButtonText: 'Logout'
            }).then((result) => {
                if (result.isConfirmed) {
                    // Extend session by making a simple request
                    $.post('auth.php', { action: 'extend_session' }, function (response) {
                        if (response.success) {
                            location.reload();
                        }
                    }, 'json');
                } else {
                    // Logout
                    $.post('auth.php', { action: 'logout' }, function () {
                        window.location.href = '/login';
                    }, 'json');
                }
            });
        } else if (timeLeft <= 0) {
            // Session expired
            Swal.fire({
                title: 'Session Expired',
                text: 'Your session has expired. Please login again.',
                icon: 'error',
                confirmButtonColor: '#3085d6',
                confirmButtonText: 'Login'
            }).then(() => {
                window.location.href = '/login';
            });
        }
    }, 60000); // Check every minute
};

// Navigation and UI functionality
$(document).ready(function() {
    // Navigation dropdown functionality
    $('.nav-dropdown-btn').click(function (e) {
        e.stopPropagation();
        const dropdown = $(this).siblings('.nav-dropdown');

        // Close all other dropdowns
        $('.nav-dropdown').not(dropdown).addClass('hidden');

        // Toggle current dropdown
        dropdown.toggleClass('hidden');
    });

    // User dropdown toggle
    $('#userMenuBtn').click(function (e) {
        e.stopPropagation();
        // Close nav dropdowns
        $('.nav-dropdown').addClass('hidden');
        $('#userDropdown').toggleClass('hidden');
    });

    // Mobile menu toggle
    $('#mobileMenuBtn').click(function (e) {
        e.stopPropagation();
        $('#mobileMenu').toggleClass('hidden');
    });

    // Logout functionality (both desktop and mobile)
    $('#logoutBtn, #mobileLogoutBtn').click(function () {
        Swal.fire({
            title: 'Logout?',
            text: 'Are you sure you want to logout?',
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#ef4444',
            cancelButtonColor: '#6b7280',
            confirmButtonText: 'Yes, Logout',
            cancelButtonText: 'Cancel'
        }).then((result) => {
            if (result.isConfirmed) {
                $.post('auth.php', { action: 'logout' }, function () {
                    window.location.href = '/login';
                }, 'json').fail(function () {
                    // Fallback if auth.php fails
                    window.location.href = '/login';
                });
            }
        });
    });

    // Close dropdowns when clicking outside
    $(document).click(function (e) {
        // Close navigation dropdowns
        if (!$(e.target).closest('.nav-dropdown-btn, .nav-dropdown').length) {
            $('.nav-dropdown').addClass('hidden');
        }

        // Close user dropdown
        if (!$(e.target).closest('#userMenuBtn, #userDropdown').length) {
            $('#userDropdown').addClass('hidden');
        }

        // Close mobile menu
        if (!$(e.target).closest('#mobileMenuBtn, #mobileMenu').length) {
            $('#mobileMenu').addClass('hidden');
        }
    });

    // Close dropdowns when pressing Escape
    $(document).keydown(function (e) {
        if (e.key === 'Escape') {
            $('.nav-dropdown').addClass('hidden');
            $('#userDropdown').addClass('hidden');
            $('#mobileMenu').addClass('hidden');
        }
    });
});