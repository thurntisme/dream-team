# Dream Team - Optimized Folder Structure

## Overview
This document outlines the optimized folder structure implemented to improve code organization, maintainability, and scalability.

## Directory Structure

```
/
├── api/                     # API endpoints
│   ├── approve_reward_api.php    # Match reward approval API
│   ├── clear_gameweek_results_api.php # Clear gameweek results API
│   ├── field_modal.php      # Field modal API endpoint
│   ├── manage_bid_api.php   # Transfer bid management API
│   ├── manage_inventory_api.php # Player inventory management API
│   ├── payment_api.php      # Payment processing API
│   ├── plan_api.php         # User plan management API
│   ├── purchase_item_api.php # Shop item purchase API
│   ├── purchase_player_api.php # Player purchase API
│   ├── renew_contract_api.php # Player contract renewal API
│   ├── save_club_api.php    # Club creation/update API
│   ├── save_team_api.php    # Team formation save API
│   ├── scout_player_api.php # Player scouting API
│   ├── settings_api.php     # User settings API
│   ├── submit_bid_api.php   # Transfer bid submission API
│   ├── train_players_api.php # Player training API
│   ├── update_match_result_api.php # Match result update API
│   ├── upgrade_card_level_api.php # Player card upgrade API
│   ├── upgrade_stadium_api.php # Stadium upgrade API
│   └── young_player_api.php # Young player management API
│
├── assets/                  # Static assets
│   ├── css/                 # Stylesheets (future)
│   ├── js/                  # JavaScript files (future)
│   ├── images/              # Image assets (future)
│   ├── json/                # JSON data files
│   │   └── players.json     # Player data
│   ├── robots.txt           # SEO robots file
│   └── sitemap.xml          # SEO sitemap
│
├── config/                  # Configuration files
│   ├── config.php           # Database and app configuration
│   ├── constants.php        # Application constants and plans
│   └── seo_config.php       # SEO configuration
│
├── database/                # Database related files
│   ├── dreamteam.db         # SQLite database
│   └── seed.php             # Database seeding script
│
├── includes/                # Shared utilities and functions
│   ├── ads.php              # Advertisement system
│   ├── helpers.php          # Helper functions
│   ├── league_functions.php # League-specific functions
│   ├── routing.php          # Routing helper functions
│   └── staff_functions.php  # Staff management functions
│

│
├── partials/                # Template partials and includes
│   ├── analytics.php        # Analytics tracking partial
│   ├── layout.php           # Main layout template
│   └── meta.php             # SEO meta tags partial
│
├── components/              # Reusable UI components
│   └── field-component.php  # Football field rendering component
│
├── public/                  # Public assets (future use)
├── storage/                 # Logs, cache, temporary files
│   ├── cache/               # Application cache
│   └── logs/                # Application logs
│
└── [Root Pages]             # Core pages and utilities
    ├── academy.php          # Young player academy
    ├── auth.php             # Authentication
    ├── index.php            # Login page
    ├── payment.php          # Payment processing
    ├── plans.php            # Subscription plans
    ├── routes.php           # Centralized routing system
    ├── settings.php         # User settings
    ├── welcome.php          # Dashboard/welcome page
    └── [Other game pages]   # Team, transfer, league, etc.
```

## Benefits of New Structure

### 1. **Separation of Concerns**
- **API endpoints** isolated in `/api/` directory
- **Configuration** centralized in `/config/`
- **Utilities** organized in `/includes/`
- **Static assets** grouped in `/assets/`

### 2. **Improved Maintainability**
- Easier to locate specific functionality
- Reduced file clutter in root directory
- Clear separation between different types of files

### 3. **Better Security**
- Sensitive configuration files in dedicated directory
- API endpoints can be secured separately
- Database files isolated from web-accessible areas

### 4. **Scalability**
- Structure supports future growth
- Easy to add new modules and features
- Prepared for MVC architecture migration

### 5. **Component-Based Architecture**
- **Components**: Reusable UI elements (field displays, modals, widgets)
- **Partials**: Template includes (layout, meta tags, analytics)
- **Pages**: Main application screens organized separately
- **Clear separation** between presentation logic and business logic

### 6. **Centralized Routing System**
- **Clean URLs**: SEO-friendly URLs without .php extensions or folder structure
- **Internal mapping**: URLs like `/welcome` map to `pages/welcome.php` internally
- **Parameter routing**: Dynamic routes with parameters (e.g., `/club/123`)
- **Route helpers**: Functions for URL generation and navigation
- **404 handling**: Centralized error handling for missing pages
- **Maintainable**: All routes defined in one place (`routes.php`)

**Important**: Users only see clean URLs like `/welcome`, `/team`, `/clubs`. The internal folder structure (`pages/`, `partials/`, `components/`) is completely hidden from users.

## Clean URL Access

### API Endpoints
APIs are accessible via clean URLs:
- `/api/field_modal` → `api/field_modal.php`
- `/api/payment` → `api/payment_api.php`
- `/api/plan` → `api/plan_api.php`
- `/api/settings` → `api/settings_api.php`
- `/api/young_player` → `api/young_player_api.php`

### Pages
Main application pages are accessible via clean URLs through the centralized routing system:
- `/` or `/home` → `landing.php`
- `/welcome` or `/dashboard` → `welcome.php`
- `/team` or `/squad` → `team.php`
- `/transfer` or `/transfers` → `transfer.php`
- `/scouting` or `/scout` → `scouting.php`
- `/league` → `league.php`
- `/clubs` → `clubs.php`
- `/shop` or `/store` → `shop.php`
- `/plans` or `/pricing` → `plans.php`
- `/settings` or `/profile` → `settings.php`
- `/academy` → `academy.php`
- `/young-players` → `young_player_market.php`
- `/match` or `/simulator` → `match-simulator.php`
- `/stadium` → `stadium.php`
- `/staff` → `staff.php`
- `/payment` → `payment.php`
- `/payment-success` → `payment_success.php`
- `/login` or `/play` or `/game` → `index.php`
- `/install` or `/setup` → `install.php`

### Parameter Routes
Dynamic routes with parameters:
- `/club/123` → `clubs.php?id=123`
- `/player/456` → `transfer.php?player_id=456`
- `/match/vs/789` → `match-simulator.php?opponent_id=789`

## Migration Notes

### Updated Include Paths
All files have been updated to use the new paths:
```php
// Old
require_once 'config.php';
require_once 'helpers.php';

// New
require_once 'config/config.php';
require_once 'includes/helpers.php';
```

### Migration Status: ✅ COMPLETE + ENHANCED
- ✅ All 59 PHP files moved to organized folders
- ✅ All include paths updated across 25+ files
- ✅ API endpoints restructured with clean URLs
- ✅ Database paths updated
- ✅ SEO configuration paths fixed
- ✅ All syntax errors resolved
- ✅ **NEW**: Pages folder created for main application pages
- ✅ **NEW**: Welcome page moved back to root `welcome.php` for simplicity
- ✅ **NEW**: Components folder created for reusable UI components
- ✅ **NEW**: Partials folder created for template includes
- ✅ **NEW**: Layout, meta, and analytics moved to `partials/`
- ✅ **NEW**: Field components moved to `components/`
- ✅ **NEW**: Cleaned up unused files and redirect files
- ✅ **NEW**: Centralized routing system with `routes.php`
- ✅ **NEW**: Clean URLs for all pages and parameter routes
- ✅ **NEW**: Routing helper functions for URL generation
- ✅ **NEW**: All API files moved to api/ directory (15 files moved)

### Database Path
Database path updated in configuration:
```php
// Old
define('DB_FILE', 'dreamteam.db');

// New
define('DB_FILE', __DIR__ . '/../database/dreamteam.db');
```

## Next Steps for Further Optimization

### Phase 2: MVC Architecture
```
app/
├── Controllers/
│   ├── AuthController.php
│   ├── GameController.php
│   ├── PaymentController.php
│   └── UserController.php
├── Models/
│   ├── User.php
│   ├── Player.php
│   ├── Club.php
│   └── Payment.php
├── Services/
│   ├── PaymentService.php
│   ├── GameService.php
│   └── NotificationService.php
└── Views/
    ├── layouts/
    ├── pages/
    └── components/
```

### Phase 3: Advanced Features
- **Composer** for dependency management
- **Environment configuration** (.env files)
- **Logging system** implementation
- **Caching layer** for performance
- **Testing framework** integration

## File Access Patterns

### For Main Pages
```php
require_once 'config/config.php';
require_once 'config/constants.php';
require_once 'includes/helpers.php';
require_once 'partials/layout.php';
```

### For API Endpoints
```php
require_once '../config/config.php';
require_once '../config/constants.php';
```

### For Database Scripts
```php
require_once '../config/config.php';
require_once '../config/constants.php';
```

This structure provides a solid foundation for the Dream Team application while maintaining backward compatibility and preparing for future enhancements.

## Routing System Fixes

### Fixed Redirect Loop Issue & Simplified Structure
- **Problem**: `/welcome` was causing infinite redirects due to complex folder structure
- **Solution**: 
  - Moved `pages/welcome.php` back to root `welcome.php`
  - Updated routing to point to `welcome.php` directly
  - Simplified paths back to relative includes
  - Maintained clean URL `/welcome` → `welcome.php`

### Path Resolution Best Practices
- **Root pages**: Use simple relative paths like `config/config.php`
- **Partials**: Use `__DIR__ . '/meta.php'` for same-directory includes  
- **JavaScript**: Use clean URLs like `/login` for redirects, relative paths for forms
- **Forms**: Use relative paths like `save_club.php` for same-directory actions

The routing system now works correctly without redirect loops and provides clean, professional URLs.

## Club Level System Implementation ✅ COMPLETE

### Overview
The club level system has been successfully updated from calculated values to persistent database fields, providing a more robust and scalable experience progression system.

### Database Schema Updates
- ✅ Added `club_exp` INTEGER field to users table (default: 0)
- ✅ Added `club_level` INTEGER field to users table (default: 1)
- ✅ Migration logic for existing users
- ✅ Removed all calculated level functions

### Experience System Features
- **Level Range**: 1-50 levels with exponential EXP requirements
- **EXP Formula**: `(level-1)² × 100` points required per level
- **Level Names**: Beginner → Amateur → Professional → Elite → Mythical → Legendary
- **Progress Tracking**: Real-time EXP progress bars and level displays

### EXP Rewards Implementation
Experience points are awarded for various game actions:

| Action | EXP Reward | Reason |
|--------|------------|---------|
| Team Formation Update | 10 EXP | Team management |
| Player Purchase | 15 EXP | Squad building |
| Training Session | 20 EXP | Player development |
| Stadium Upgrade | 25 EXP | Infrastructure improvement |
| Match Victory | 30 EXP | Competitive success |
| Match Draw | 15 EXP | Competitive participation |
| Match Loss | 5 EXP | Learning experience |
| Card Level Upgrade (Success) | 12 EXP | Player enhancement |
| Card Level Upgrade (Failed) | 5 EXP | Attempt recognition |
| Contract Renewal | 8 EXP | Squad management |
| Player Scouting (Premium) | 10 EXP | Strategic planning |
| Player Scouting (Detailed) | 6 EXP | Information gathering |
| Player Scouting (Basic) | 3 EXP | Basic research |

### Frontend Features
- **Level-Up Notifications**: Animated celebrations with SweetAlert2
- **Progress Bars**: Real-time EXP progress visualization
- **Level Badges**: Color-coded level indicators throughout UI
- **Auto-Refresh**: Page refreshes after level-up to update displays

### API Integration
All relevant API endpoints now include:
- EXP reward calculation and application
- Level-up detection and response
- Consistent response format with `level_up` data

### Helper Functions
New experience system functions in `includes/helpers.php`:
- `addClubExp()` - Award experience points
- `getLevelFromExp()` - Calculate level from EXP
- `getExpRequiredForLevel()` - Get EXP requirement for level
- `getExpProgress()` - Get progress within current level
- `getClubLevelName()` - Get level display name

### Files Updated
- ✅ `auth.php` - Database schema and migration
- ✅ `includes/helpers.php` - Experience system functions
- ✅ `partials/layout.php` - Level display and notifications
- ✅ `team.php` - Progress bars and level information
- ✅ `api/save_team_api.php` - Team management EXP
- ✅ `api/purchase_player_api.php` - Player purchase EXP
- ✅ `api/train_players_api.php` - Training EXP
- ✅ `api/upgrade_stadium_api.php` - Stadium upgrade EXP
- ✅ `api/update_match_result_api.php` - Match result EXP
- ✅ `api/renew_contract_api.php` - Contract renewal EXP
- ✅ `api/upgrade_card_level_api.php` - Card upgrade EXP
- ✅ `api/scout_player_api.php` - Scouting EXP

### Benefits
1. **Persistent Progress**: Levels saved in database, not recalculated
2. **Scalable System**: Supports 50 levels with room for expansion
3. **Engaging Gameplay**: Regular rewards for all game activities
4. **Visual Feedback**: Clear progress indication and celebrations
5. **Performance**: No more expensive calculations on every page load