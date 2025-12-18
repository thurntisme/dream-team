<?php
/**
 * 500 Internal Server Error Page
 * Professional error page for the Dream Team application
 */

// Set proper HTTP status code
http_response_code(500);
header('HTTP/1.0 500 Internal Server Error');

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>500 - Internal Server Error - Dream Team</title>
    <meta name="robots" content="noindex, nofollow">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
        /* Custom animations */
        @keyframes float {
            0%, 100% { transform: translateY(0px); }
            50% { transform: translateY(-10px); }
        }
        
        .float-animation {
            animation: float 3s ease-in-out infinite;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .fade-in {
            animation: fadeIn 0.6s ease-out;
        }
        
        .fade-in-delay {
            animation: fadeIn 0.8s ease-out;
        }

        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.5; }
        }
        
        .pulse-animation {
            animation: pulse 2s ease-in-out infinite;
        }
    </style>
</head>
<body class="bg-gradient-to-br from-gray-50 to-gray-100 min-h-screen flex items-center justify-center p-4">
    <div class="max-w-lg mx-auto text-center">
        <!-- Animated 500 Icon -->
        <div class="mb-8 fade-in">
            <div class="w-32 h-32 bg-gradient-to-br from-blue-100 to-purple-100 rounded-full flex items-center justify-center mx-auto mb-6 float-animation">
                <i data-lucide="server-crash" class="w-16 h-16 text-gray-400"></i>
            </div>
        </div>
        
        <!-- Error Content -->
        <div class="mb-8 fade-in-delay">
            <h1 class="text-8xl font-bold text-transparent bg-clip-text bg-gradient-to-r from-blue-600 to-purple-600 mb-4"></h1></h1>
                500
            </h1>
            <h2 class="text-3xl font-bold text-gray-900 mb-4">
                Internal Server Error
            </h2>
            <p class="text-lg text-gray-600 mb-4 leading-relaxed">
                Something went wrong on our end. We're working to fix it.
            </p>
            <p class="text-sm text-gray-500">
                Please try again in a few moments. If the problem persists, contact support.
            </p>
        </div>
        
        <!-- Status Indicator -->
        <div class="mb-8 fade-in-delay">
            <div class="inline-flex items-center gap-2 bg-blue-100 text-blue-800 px-4 py-2 rounded-full text-sm">
                <div class="w-2 h-2 bg-blue-500 rounded-full pulse-animation"></div>
                Server Issue Detected
            </div>
        </div>
        
        <!-- Action Buttons -->
        <div class="space-y-4 fade-in-delay">
            <button onclick="window.location.reload()" 
                    class="inline-flex items-center justify-center w-full px-6 py-3 bg-gradient-to-r from-blue-600 to-purple-600 text-white rounded-lg hover:from-blue-700 hover:to-purple-700 transition-all duration-200 font-medium shadow-lg hover:shadow-xl transform hover:-translate-y-1">
                <i data-lucide="refresh-cw" class="w-5 h-5 mr-2"></i>
                Try Again
            </button>
            
            <a href="index.php" 
               class="inline-flex items-center justify-center w-full px-6 py-3 bg-white text-gray-700 border border-gray-300 rounded-lg hover:bg-gray-50 transition-all duration-200 font-medium shadow-md hover:shadow-lg">
                <i data-lucide="home" class="w-5 h-5 mr-2"></i>
                Go to Homepage
            </a>
            
            <button onclick="goBack()" 
                    class="inline-flex items-center justify-center w-full px-6 py-3 bg-gray-100 text-gray-600 border border-gray-200 rounded-lg hover:bg-gray-200 transition-all duration-200 font-medium">
                <i data-lucide="arrow-left" class="w-5 h-5 mr-2"></i>
                Go Back
            </button>
            
            <!-- Additional helpful links -->
            <div class="flex justify-center space-x-4 pt-4">
                <a href="support.php" class="text-sm text-gray-500 hover:text-gray-700 transition-colors">
                    <i data-lucide="help-circle" class="w-4 h-4 inline mr-1"></i>
                    Support
                </a>
                <a href="feedback.php" class="text-sm text-gray-500 hover:text-gray-700 transition-colors">
                    <i data-lucide="message-circle" class="w-4 h-4 inline mr-1"></i>
                    Report Issue
                </a>
            </div>
        </div>
        
        <!-- Footer -->
        <div class="mt-12 pt-8 border-t border-gray-200 fade-in-delay">
            <div class="flex items-center justify-center space-x-4 mb-4">
                <div class="w-8 h-8 bg-gradient-to-r from-blue-600 to-purple-600 rounded-lg flex items-center justify-center">
                    <i data-lucide="zap" class="w-4 h-4 text-white"></i>
                </div>
                <span class="text-lg font-bold text-gray-900">Dream Team</span>
            </div>
            <p class="text-xs text-gray-400">
                &copy; <?php echo date('Y'); ?> Dream Team. All rights reserved.
            </p>
        </div>
    </div>
    
    <script>
        // Initialize Lucide icons
        lucide.createIcons();
        
        // Go back function with fallback
        function goBack() {
            if (window.history.length > 1) {
                window.history.back();
            } else {
                window.location.href = 'index.php';
            }
        }
        
        // Add some interactive effects
        document.addEventListener('DOMContentLoaded', function() {
            // Add hover effect to the main icon
            const icon = document.querySelector('.float-animation');
            if (icon) {
                icon.addEventListener('mouseenter', function() {
                    this.style.transform = 'scale(1.1) translateY(-10px)';
                    this.style.transition = 'transform 0.3s ease';
                });
                
                icon.addEventListener('mouseleave', function() {
                    this.style.transform = 'scale(1) translateY(0px)';
                });
            }
            
            // Add keyboard navigation
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape' || e.key === 'Backspace') {
                    goBack();
                } else if (e.key === 'Enter' || e.key === 'h') {
                    window.location.href = 'index.php';
                } else if (e.key === 'r' || e.key === 'F5') {
                    e.preventDefault();
                    window.location.reload();
                }
            });
            
            // Auto-retry functionality (optional)
            let retryCount = 0;
            const maxRetries = 3;
            
            // Add retry button functionality
            const retryBtn = document.querySelector('button[onclick="window.location.reload()"]');
            if (retryBtn) {
                retryBtn.addEventListener('click', function(e) {
                    e.preventDefault();
                    retryCount++;
                    
                    // Show loading state
                    const originalText = this.innerHTML;
                    this.innerHTML = '<i data-lucide="loader-2" class="w-5 h-5 mr-2 animate-spin"></i>Retrying...';
                    this.disabled = true;
                    
                    // Reload after a short delay
                    setTimeout(() => {
                        window.location.reload();
                    }, 1000);
                });
            }
        });
    </script>
</body>
</html>