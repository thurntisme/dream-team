# 404 Error System Documentation

## Overview
The 404 Error System provides a consistent, professional way to handle missing pages and unauthorized access throughout the Dream Team application. It includes a reusable 404 page and helper functions for different error scenarios.

## Components

### 1. **Main 404 Page** (`404.php`)
A standalone, customizable 404 error page with:
- Professional design matching the application
- Animated elements and smooth transitions
- Customizable error messages via URL parameters
- Keyboard navigation support
- Analytics tracking integration
- Mobile-responsive design

### 2. **Error Handler Functions** (`includes/error_handlers.php`)
Helper functions for consistent error handling:
- `show404Error()` - Generic 404 error
- `show403Error()` - Access forbidden
- `showFeatureDisabledError()` - Disabled features
- `showMaintenanceError()` - Maintenance mode
- `showDatabaseError()` - Database issues
- `showAuthRequiredError()` - Authentication required
- `showClubRequiredError()` - Club setup required
- `showPremiumRequiredError()` - Premium features
- `showRateLimitError()` - Rate limiting
- `showFileNotFoundError()` - File not found
- `renderInline404Error()` - Inline error display
- `handleError()` - Smart error handling (AJAX vs regular)

### 3. **Apache Configuration** (`.htaccess`)
- Custom error document pointing to `404.php`
- Protection for sensitive files
- Log file access prevention

## Usage Examples

### Basic 404 Error
```php
// Simple 404 with default message
show404Error();

// Custom 404 with specific message
show404Error(
    'Debug Logs Not Available',
    'This feature is disabled in production.',
    'Contact your administrator for access.'
);
```

### Feature-Specific Errors
```php
// Feature disabled
showFeatureDisabledError('Debug Logs');

// Authentication required
showAuthRequiredError();

// Premium feature
showPremiumRequiredError('Advanced Analytics');

// Access forbidden
show403Error('You do not have admin privileges.');
```

### AJAX-Friendly Error Handling
```php
// Automatically detects AJAX and returns JSON or shows page
handleError(
    'Resource Not Found',
    'The requested data could not be loaded.',
    'Please refresh the page and try again.',
    404
);
```

### Inline Error Display
```php
// For displaying errors within existing pages
echo renderInline404Error(
    'No Results Found',
    'No players match your search criteria.',
    'Try adjusting your filters.'
);
```

## Customization Options

### URL Parameters for 404.php
The 404 page accepts several URL parameters for customization:

| Parameter | Description | Default |
|-----------|-------------|---------|
| `title` | Error title | "Page Not Found" |
| `message` | Main error message | "The page you are looking for does not exist..." |
| `details` | Additional details | "If you believe this is an error..." |
| `back` | Show back button (0/1) | 1 (true) |
| `home` | Home URL | "index.php" |

### Example URLs
```
404.php?title=Feature%20Disabled&message=Debug%20logs%20are%20not%20available
404.php?title=Maintenance&message=System%20under%20maintenance&back=0
404.php?title=Premium%20Required&home=plans.php
```

### Function Parameters
```php
show404Error(
    $title = null,           // Custom title
    $message = null,         // Custom message  
    $details = null,         // Additional details
    $showBackButton = true,  // Show back button
    $homeUrl = 'index.php',  // Home page URL
    $exit = true            // Exit after redirect
);
```

## Design Features

### Visual Elements
- **Gradient backgrounds** for modern appearance
- **Animated floating icon** for engagement
- **Smooth fade-in animations** for professional feel
- **Responsive design** for all devices
- **Consistent branding** with Dream Team colors

### Interactive Features
- **Keyboard navigation** (Escape/Backspace = back, Enter/H = home)
- **Smart back button** with fallback to home
- **Hover effects** on interactive elements
- **Analytics tracking** integration ready

### Accessibility
- **Semantic HTML** structure
- **ARIA labels** where appropriate
- **High contrast** text and backgrounds
- **Keyboard accessible** navigation
- **Screen reader friendly**

## Integration Examples

### Debug Logs Protection
```php
// In debug_logs.php
require_once 'includes/error_handlers.php';

$logger = DebugLogger::getInstance();
if (!$logger->isEnabled()) {
    showFeatureDisabledError('Debug Logs');
}
```

### Authentication Check
```php
// In protected pages
if (!isset($_SESSION['user_id'])) {
    showAuthRequiredError();
}
```

### Premium Feature Gate
```php
// In premium features
if (!userHasPremium($userId)) {
    showPremiumRequiredError('Advanced Team Analytics');
}
```

### File Access Protection
```php
// In file download handlers
if (!file_exists($filePath)) {
    showFileNotFoundError(basename($filePath));
}

if (!userCanAccessFile($userId, $filePath)) {
    show403Error('You do not have permission to access this file.');
}
```

### API Error Handling
```php
// In API endpoints
try {
    $data = processApiRequest();
    echo json_encode(['success' => true, 'data' => $data]);
} catch (NotFoundException $e) {
    handleError('Resource Not Found', $e->getMessage(), '', 404);
} catch (UnauthorizedException $e) {
    handleError('Access Denied', $e->getMessage(), '', 403);
}
```

## Configuration

### Apache Setup
The `.htaccess` file includes:
```apache
# Custom Error Pages
ErrorDocument 404 /404.php

# Protect sensitive files
<Files ".env">
    Order allow,deny
    Deny from all
</Files>

# Protect log files
<FilesMatch "\.log$">
    Order allow,deny
    Deny from all
</FilesMatch>
```

### Analytics Integration
Add to `404.php` for tracking:
```javascript
// Google Analytics
if (typeof gtag !== 'undefined') {
    gtag('event', 'page_view', {
        'page_title': '404 Error',
        'page_location': window.location.href,
        'custom_map': {
            'error_type': '404',
            'error_title': 'Page Not Found'
        }
    });
}

// Facebook Pixel
if (typeof fbq !== 'undefined') {
    fbq('track', 'PageView', {
        content_name: '404 Error Page'
    });
}
```

## Error Types and Use Cases

### 1. **404 - Not Found**
- Missing pages or resources
- Invalid URLs
- Deleted content
```php
show404Error('Page Not Found', 'This page has been removed or moved.');
```

### 2. **403 - Forbidden**
- Insufficient permissions
- Admin-only areas
- Blocked users
```php
show403Error('Admin access required for this feature.');
```

### 3. **Feature Disabled**
- Debug tools in production
- Beta features
- Maintenance mode features
```php
showFeatureDisabledError('Beta Analytics Dashboard');
```

### 4. **Authentication Required**
- Login-protected pages
- Session expired
- Guest restrictions
```php
showAuthRequiredError();
```

### 5. **Premium Required**
- Paid features
- Subscription gates
- Upgrade prompts
```php
showPremiumRequiredError('Advanced Player Statistics');
```

### 6. **Rate Limited**
- API rate limits
- Spam prevention
- Resource protection
```php
showRateLimitError(300); // 5 minutes
```

## Best Practices

### 1. **Consistent Messaging**
- Use appropriate error types for different scenarios
- Provide helpful, actionable error messages
- Include next steps or alternatives when possible

### 2. **User Experience**
- Always provide a way back or forward
- Use friendly, non-technical language
- Maintain visual consistency with the application

### 3. **Security**
- Don't reveal sensitive information in error messages
- Use generic messages for security-related errors
- Log detailed errors server-side, show simple messages to users

### 4. **Performance**
- Error pages should load quickly
- Minimize external dependencies
- Use efficient redirects

### 5. **SEO Considerations**
- Return proper HTTP status codes
- Use `noindex, nofollow` meta tags on error pages
- Provide helpful navigation to valid content

## Testing

### Manual Testing
1. **Direct access**: Visit `404.php` directly
2. **Invalid URLs**: Try non-existent pages
3. **Parameter testing**: Test with different URL parameters
4. **Mobile testing**: Verify responsive design
5. **Keyboard navigation**: Test keyboard shortcuts

### Automated Testing
```php
// Test error handler functions
function testErrorHandlers() {
    // Test 404 error
    ob_start();
    show404Error('Test Title', 'Test Message', 'Test Details', true, 'test.php', false);
    $output = ob_get_clean();
    
    // Verify redirect header was set
    $headers = headers_list();
    assert(in_array('Location: 404.php?title=Test+Title&message=Test+Message&details=Test+Details&home=test.php', $headers));
    
    // Test inline error
    $inlineError = renderInline404Error('Test', 'Message');
    assert(strpos($inlineError, 'Test') !== false);
    assert(strpos($inlineError, 'Message') !== false);
}
```

## Troubleshooting

### Common Issues

1. **404 page not showing**
   - Check `.htaccess` ErrorDocument directive
   - Verify `404.php` file exists and is readable
   - Check Apache mod_rewrite is enabled

2. **Styling issues**
   - Ensure Tailwind CSS is loading
   - Check for JavaScript errors in console
   - Verify Lucide icons are loading

3. **Redirect loops**
   - Check for recursive error handling
   - Verify error handler functions don't call themselves
   - Ensure 404.php doesn't trigger additional errors

4. **Parameters not working**
   - Check URL encoding of parameters
   - Verify parameter names match expected values
   - Check for special characters in messages

### Debug Mode
Add to `404.php` for debugging:
```php
<?php if (isset($_GET['debug'])): ?>
    <div style="background: #f0f0f0; padding: 10px; margin: 10px; font-family: monospace;">
        <strong>Debug Info:</strong><br>
        Request URI: <?php echo $_SERVER['REQUEST_URI'] ?? 'N/A'; ?><br>
        Referrer: <?php echo $_SERVER['HTTP_REFERER'] ?? 'N/A'; ?><br>
        Parameters: <?php print_r($_GET); ?>
    </div>
<?php endif; ?>
```

## Future Enhancements

### Planned Features
1. **Error reporting integration** - Automatic error logging
2. **A/B testing** - Different error page variants
3. **Localization** - Multi-language error messages
4. **Smart suggestions** - AI-powered page suggestions
5. **Error analytics** - Detailed error tracking dashboard

### Customization Options
1. **Themes** - Different visual themes for error pages
2. **Animations** - More animation options
3. **Templates** - Multiple error page templates
4. **Branding** - Easy branding customization

## Support

For issues or questions:
1. Check Apache error logs for server-side issues
2. Use browser developer tools for client-side debugging
3. Test with `?debug=1` parameter for additional information
4. Verify all required files are present and readable

## License

This 404 error system is part of the Dream Team application and follows the same license terms.