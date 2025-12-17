# Constants Migration Summary

## Overview
Successfully moved all constant fields defined inside functions to `config/constants.php` for better organization and maintainability.

## Constants Moved

### 1. League System Constants
- **FAKE_CLUBS**: Premier League fake team names (19 teams)
- **CHAMPIONSHIP_CLUBS**: Championship fake team names (24 teams)
- **Source**: `includes/league_functions.php`

### 2. Staff System Constants
- **STAFF_COSTS**: Complete staff configuration including:
  - Head Coach (5 levels, candidates, costs, bonuses)
  - Fitness Coach (5 levels, candidates, costs, bonuses)
  - Scout (5 levels, candidates, costs, bonuses)
  - Youth Coach (5 levels, candidates, costs, bonuses)
  - Medical Staff (5 levels, candidates, costs, bonuses)
- **Source**: `staff.php` function `getStaffCosts()`

### 3. Stadium System Constants
- **STADIUM_LEVELS**: Stadium upgrade levels (1-5) with costs, capacity, revenue multipliers
- **STADIUM_FEATURES**: Features available at each stadium level
- **Source**: `stadium.php`

### 4. Injury System Constants
- **INJURY_TYPES**: Injury types with duration ranges, fitness penalties, and probabilities:
  - Minor Strain (80% probability)
  - Muscle Injury (15% probability)
  - Serious Injury (5% probability)
- **Source**: `api/injury_system_api.php`, `match-simulator.php`

### 5. Scouting System Constants
- **SCOUTING_COSTS**: Scout report costs (basic, detailed, premium)
- **SCOUTING_QUALITY_NAMES**: Quality level names mapping
- **POSITION_MAPPING**: Position to category mapping (GK, DEF, MID, FWD)
- **FORMATION_REQUIREMENTS**: Position requirements for each formation
- **Source**: `scouting.php`

### 6. Support System Constants
- **SUPPORT_CATEGORIES**: Support ticket categories (10 types)
- **SUPPORT_PRIORITIES**: Priority levels (low, medium, high, urgent)
- **Source**: `support.php`

## Files Updated

### Modified Files
1. **config/constants.php** - Added all new constants and helper functions
2. **staff.php** - Removed `getStaffCosts()` function, now uses constants
3. **stadium.php** - Replaced local arrays with constant references
4. **scouting.php** - Replaced local arrays with constant references
5. **support.php** - Replaced local arrays with constant references
6. **api/injury_system_api.php** - Updated to use injury type constants
7. **match-simulator.php** - Updated to use injury type constants
8. **includes/league_functions.php** - Removed constant definitions

### Helper Functions Added
- `getStaffCosts()` - Returns STAFF_COSTS constant
- `getStadiumLevels()` - Returns STADIUM_LEVELS constant
- `getStadiumFeatures()` - Returns STADIUM_FEATURES constant
- `getInjuryTypes()` - Returns INJURY_TYPES constant
- `getScoutingCosts()` - Returns SCOUTING_COSTS constant
- `getPositionMapping()` - Returns POSITION_MAPPING constant
- `getFormationRequirements()` - Returns FORMATION_REQUIREMENTS constant
- `getSupportCategories()` - Returns SUPPORT_CATEGORIES constant
- `getSupportPriorities()` - Returns SUPPORT_PRIORITIES constant

## Benefits

### 1. Centralized Configuration
- All game constants are now in one location
- Easier to maintain and update values
- Consistent data across the application

### 2. Better Performance
- Constants are loaded once and cached
- No need to recreate arrays in functions repeatedly
- Reduced memory usage

### 3. Improved Maintainability
- Single source of truth for all game data
- Easier to modify game balance and costs
- Clear separation of configuration from logic

### 4. Enhanced Flexibility
- Easy to add new staff types, injury types, etc.
- Simple to adjust game economics
- Straightforward to extend support categories

## Technical Notes

### Injury System Update
The injury system was updated to handle range-based values:
- Constants now store min/max ranges for duration and fitness penalty
- Runtime code generates random values within these ranges
- Maintains randomness while centralizing configuration

### Backward Compatibility
All existing function calls continue to work through helper functions that return the appropriate constants.

## Testing Recommendations

1. **Staff System**: Test hiring, upgrading, and firing staff members
2. **Stadium System**: Test stadium upgrades and feature displays
3. **Scouting System**: Test all three scout types and position recommendations
4. **Injury System**: Test injury occurrence and healing in matches
5. **Support System**: Test ticket creation with different categories and priorities
6. **League System**: Test league generation with fake clubs

All constants are now properly organized and easily maintainable in the central configuration file.