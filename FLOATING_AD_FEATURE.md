# Floating Ad Feature

## Overview
The Floating Ad feature displays a non-intrusive promotional banner for free users across all pages of the application. It encourages users to upgrade to premium plans while maintaining a good user experience.

## Implementation

### Location
The floating ad is now centrally managed in the **layout.php** file, ensuring consistent display across all pages without requiring individual page modifications.

### Files Modified

1. **partials/layout.php**
   - Added floating ad rendering before the JavaScript section
   - Automatically displays for all authenticated free users
   - No need to add ad code to individual pages

2. **includes/ads.php**
   - Enhanced `renderFloatingAd()` function with:
     - Multiple ad variants (3 different messages)
     - Gradient backgrounds and icons
     - Auto-hide after 30 seconds
     - Smooth animations
     - Responsive design

3. **Removed from individual pages**:
   - academy.php
   - welcome.php
   - (Any other pages that had individual floating ad calls)

## Features

### 1. **Automatic Display**
- Shows automatically 3 seconds after page load
- Only displays for free users (checks `shouldShowAds()`)
- Appears on all pages via layout.php

### 2. **Multiple Ad Variants**
Three rotating ad messages:
- **Variant 1**: "Upgrade to Premium" (Blue to Purple gradient, Crown icon)
- **Variant 2**: "Go Ad-Free" (Green to Blue gradient, Zap icon)
- **Variant 3**: "Unlock Premium" (Purple to Pink gradient, Star icon)

### 3. **User-Friendly Behavior**
- **Slide-in animation**: Smooth entrance from right side
- **Close button**: Users can dismiss the ad
- **Auto-hide**: Disappears after 30 seconds if not interacted with
- **Hover effect**: Enhanced shadow on hover
- **Responsive**: Works on mobile and desktop

### 4. **Visual Design**
- Clean white card with shadow
- Gradient icon backgrounds
- Gradient CTA buttons
- Professional typography
- "Advertisement" label for transparency

## Technical Details

### Display Logic
```php
<?php if ($isLoggedIn && shouldShowAds($_SESSION['user_id'])): ?>
    <?php renderFloatingAd($_SESSION['user_id']); ?>
<?php endif; ?>
```

### User Plan Check
The ad only shows when:
1. User is logged in (`$isLoggedIn`)
2. User is on a free plan (`shouldShowAds()` returns true)

### JavaScript Functionality
- **Initialization check**: Prevents multiple instances
- **Delayed display**: 3-second delay after page load
- **Icon rendering**: Ensures Lucide icons are created
- **Close function**: Smooth slide-out and removal
- **Auto-hide timer**: 30-second timeout

### CSS Classes
- `fixed bottom-4 right-4`: Positioning
- `z-50`: High z-index to stay on top
- `max-w-sm`: Maximum width constraint
- `translate-x-full`: Initial hidden state
- `transition-all duration-500`: Smooth animations

## Customization

### Adding New Ad Variants
Edit `includes/ads.php` and add to the `$adVariants` array:

```php
[
    'title' => 'Your Title',
    'description' => 'Your description',
    'cta' => 'Button Text',
    'icon' => 'lucide-icon-name',
    'gradient' => 'from-color-500 to-color-600'
]
```

### Adjusting Timing
In `includes/ads.php`:
- **Display delay**: Change `3000` (3 seconds)
- **Auto-hide delay**: Change `30000` (30 seconds)

### Positioning
Modify the CSS classes in `renderFloatingAd()`:
- `bottom-4 right-4`: Change to `top-4 left-4` for top-left
- `bottom-4 left-4`: For bottom-left
- `top-4 right-4`: For top-right

### Size
- `max-w-sm`: Small (384px)
- `max-w-md`: Medium (448px)
- `max-w-lg`: Large (512px)

## Benefits

### For Users
- **Non-intrusive**: Appears in corner, doesn't block content
- **Dismissible**: Can be closed anytime
- **Auto-hides**: Doesn't stay forever
- **Informative**: Clear upgrade benefits

### For Developers
- **Centralized**: Single location in layout.php
- **Maintainable**: Easy to update across all pages
- **Consistent**: Same behavior everywhere
- **Flexible**: Easy to customize variants

### For Business
- **Conversion**: Encourages premium upgrades
- **Visibility**: Appears on every page
- **Professional**: Well-designed and polished
- **Trackable**: Can add analytics easily

## Analytics Integration

To track ad performance, add to the CTA button:

```php
echo '<a href="plans.php" onclick="trackFloatingAdClick()" class="...">';
```

Then add tracking function:

```javascript
function trackFloatingAdClick() {
    // Google Analytics
    if (typeof gtag !== 'undefined') {
        gtag('event', 'click', {
            'event_category': 'Floating Ad',
            'event_label': 'Upgrade CTA'
        });
    }
    
    // Facebook Pixel
    if (typeof fbq !== 'undefined') {
        fbq('track', 'ViewContent', {
            content_name: 'Floating Ad Click'
        });
    }
}
```

## A/B Testing

To test different variants, modify the selection logic:

```php
// Instead of random selection
$ad = $adVariants[array_rand($adVariants)];

// Use user ID for consistent variant per user
$variantIndex = $user_id % count($adVariants);
$ad = $adVariants[$variantIndex];
```

## Mobile Optimization

The floating ad is already responsive, but for better mobile experience:

```php
// Add mobile-specific classes
echo '<div id="floatingAd" class="fixed bottom-4 right-4 md:right-4 md:bottom-4 left-4 md:left-auto z-50 max-w-sm...">';
```

This makes it full-width on mobile (left-4 right-4) and positioned in corner on desktop.

## Troubleshooting

### Ad Not Showing
1. Check if user is logged in
2. Verify user is on free plan
3. Check browser console for JavaScript errors
4. Ensure Lucide icons are loaded

### Multiple Ads Appearing
- The `floatingAdInitialized` flag prevents this
- If issue persists, check for multiple layout.php includes

### Icons Not Rendering
- Ensure `lucide.createIcons()` is called after ad appears
- Check if Lucide library is loaded in layout.php

### Ad Stays Too Long
- Adjust the 30-second timeout in the JavaScript
- Or remove the auto-hide feature entirely

## Future Enhancements

Potential improvements:
1. **Frequency capping**: Show once per session
2. **Cookie-based dismissal**: Remember if user closed it
3. **Contextual ads**: Different messages per page
4. **Animation variants**: Different entrance effects
5. **Sound effects**: Optional audio on appearance
6. **Video ads**: Embed promotional videos
7. **Countdown timer**: "Limited time offer" urgency
8. **User targeting**: Based on behavior or demographics

## Best Practices

1. **Don't overdo it**: One floating ad is enough
2. **Respect dismissal**: Don't show again immediately
3. **Keep it relevant**: Match ad to user's needs
4. **Test variants**: A/B test different messages
5. **Monitor performance**: Track clicks and conversions
6. **Stay compliant**: Follow advertising regulations
7. **Mobile-first**: Ensure good mobile experience
8. **Accessibility**: Ensure keyboard navigation works

## Compliance

### GDPR Considerations
- Floating ads are not tracking cookies
- No personal data collection required
- User can dismiss anytime
- Transparent "Advertisement" label

### Accessibility
- Keyboard accessible (Tab to close button)
- Screen reader friendly
- High contrast text
- Clear call-to-action

## Support

For issues or questions:
1. Check browser console for errors
2. Verify user plan status in database
3. Test with different user accounts
4. Review layout.php implementation
5. Check ads.php function definition

## License

This feature is part of the Dream Team application and follows the same license terms.
