# Dream Team Routing System

## Clean URLs (What Users See)

✅ **User-friendly URLs without folder structure:**

- `/` → Home page
- `/welcome` → User dashboard  
- `/team` → Team management
- `/clubs` → Other clubs
- `/transfer` → Transfer market
- `/shop` → Item shop
- `/plans` → Subscription plans
- `/settings` → User settings

## Internal File Mapping (Hidden from Users)

🔒 **Internal structure (users never see this):**

- `/welcome` → `pages/welcome.php`
- `/team` → `team.php`
- `/clubs` → `clubs.php`
- Layout templates → `partials/layout.php`
- Components → `components/field-component.php`

## Parameter Routes

🎯 **Dynamic URLs with parameters:**

- `/club/123` → View club with ID 123
- `/player/456` → View player with ID 456
- `/match/vs/789` → Match against opponent ID 789

## Benefits

✨ **Why this is better:**

1. **SEO-friendly**: Clean URLs rank better in search engines
2. **User-friendly**: Easy to remember and share URLs
3. **Professional**: Modern web application URL structure
4. **Secure**: Internal folder structure is completely hidden
5. **Maintainable**: Easy to reorganize files without breaking URLs

## Example Usage

```php
// Generate clean URLs in templates
echo route('welcome');        // → /welcome
echo route('team');          // → /team
echo route('club', [123]);   // → /club/123

// Check current route
if (isCurrentRoute('welcome')) {
    echo 'Currently on dashboard';
}
```

The routing system ensures users only see clean, professional URLs while maintaining a well-organized internal file structure.