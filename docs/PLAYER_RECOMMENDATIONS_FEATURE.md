# Player Recommendations Feature

## Overview
The Player Recommendations feature is a **premium AI-powered service** that analyzes your team composition and suggests suitable players to improve your squad. Users are charged €2M per analysis request. The system considers your formation, budget, current team strengths/weaknesses, and provides prioritized recommendations.

## Features

### 1. Premium Payment System
- **Cost**: €2M per recommendation request
- **Confirmation Dialog**: Users must confirm payment before analysis
- **Budget Validation**: Checks if user has sufficient funds
- **Automatic Deduction**: Budget is updated immediately after payment
- **Payment Feedback**: Success notification with remaining budget
- **Insufficient Funds**: Clear error message with budget requirements

### 2. Progress Visualization
- **Animated Progress Bar**: Shows real-time analysis progress (0-100%)
- **Multi-Step Process**: 10 realistic AI analysis steps with descriptions
- **Status Indicators**: Visual tracking of Team Scan → AI Analysis → Results
- **Gradient Animations**: Smooth, professional progress bar with shimmer effects
- **Realistic Timing**: Variable step durations for authentic AI processing feel
- **Safety Timeout**: Maximum 15-second analysis time with fallback

### 3. Intelligent Team Analysis
- Identifies empty positions in your starting XI
- Detects weak positions (below 75 rating average)
- Calculates team statistics (total players, average rating, available budget)
- Analyzes position distribution and depth

### 2. Smart Recommendations
The system recommends players based on:
- **Position Needs**: Prioritizes filling empty positions
- **Quality Upgrades**: Suggests players who improve weak positions
- **Squad Depth**: Recommends quality backups for strong positions
- **Budget Constraints**: Only shows affordable players
- **Value Rating**: Considers player rating vs. cost ratio

### 3. Priority Levels
Recommendations are categorized into three priority levels:
- **High Priority** (Red): Fills empty positions in starting XI
- **Medium Priority** (Orange): Upgrades weak positions (below 75 rating)
- **Low Priority** (Blue): Adds squad depth and quality backups

### 4. User Interface
- Clean, modern modal design with gradient header
- **Animated progress bar** with realistic AI analysis steps
- **Multi-stage progress tracking** (Team Scan → AI Analysis → Results)
- Team analysis dashboard showing key metrics
- Filterable recommendations by priority level
- Detailed player cards with stats and reasoning
- Quick actions: View Details and Add Player buttons
- Responsive design for mobile and desktop

## Files Created

### 1. API Endpoint
**File**: `api/recommend_players_api.php`
- Analyzes current team composition
- Generates personalized recommendations
- Returns prioritized player suggestions with reasoning

### 2. UI Component
**File**: `components/player-recommendations-modal.php`
- Modal interface for displaying recommendations
- Team analysis section
- Filterable recommendations list
- Loading, error, and empty states

### 3. Styling
**File**: `assets/css/recommendations-modal.css`
- Custom styles for the recommendations modal
- Priority-based color coding
- Hover effects and animations
- Responsive design adjustments

### 4. Integration
**Modified Files**:
- `team.php`: Added JavaScript functionality and modal include
- `components/team-selector.php`: Added "Get Recommendations" button

## How It Works

### Recommendation Algorithm

1. **Team Analysis Phase**
   ```
   - Parse current team and substitutes
   - Identify formation requirements
   - Count players per position
   - Calculate average ratings per position
   - Detect empty and weak positions
   ```

2. **Scoring System**
   Each player receives a score based on:
   - **Empty Position Bonus**: +100 points
   - **Weak Position Upgrade**: +50 points + (player rating - current avg)
   - **Formation Match**: +30 points
   - **Rating Bonus**: +0.5 × player rating
   - **Value Ratio**: +2 × (rating / price in millions)

3. **Prioritization**
   - High: Fills empty positions
   - Medium: Upgrades weak positions
   - Low: Adds squad depth

4. **Filtering**
   - Excludes players already in team/substitutes
   - Removes players exceeding budget
   - Limits to top 10 recommendations

## Usage

### For Users
1. Navigate to the Team page
2. Click the "AI Recommendations" button (blue button with brain icon and €2M price tag)
3. **Confirm payment** in the popup dialog (€2M will be deducted from budget)
4. **Watch the AI analysis progress** with realistic steps:
   - Initializing AI analysis engine
   - Scanning current team composition
   - Analyzing formation requirements
   - Evaluating player ratings and positions
   - Identifying team weaknesses
   - Searching player database
   - Calculating compatibility scores
   - Applying budget constraints
   - Ranking recommendations by priority
   - Finalizing analysis results
5. View team analysis and identified issues
6. Browse recommended players
7. Filter by priority level (All, High, Medium, Low)
8. Click "View Details" to see full player information
9. Click "Add Player" to purchase and add to your team

**Note**: Each recommendation request costs €2M and provides up to 10 personalized suggestions.

### For Developers

#### API Requests

**Get Cost Information:**
```javascript
$.post('api/recommend_players_api.php', { action: 'get_cost' }, function(response) {
    if (response.success) {
        console.log('Cost:', response.cost);
        console.log('Formatted:', response.formatted_cost);
    }
}, 'json');
```

**Get Recommendations (with payment):**
```javascript
$.post('api/recommend_players_api.php', { action: 'get_recommendations' }, function(response) {
    if (response.success) {
        // Handle recommendations
        console.log('Recommendations:', response.recommendations);
        console.log('New Budget:', response.budget);
        console.log('Cost Paid:', response.cost_paid);
    } else if (response.error_type === 'insufficient_budget') {
        // Handle insufficient funds
        console.log('Required:', response.required);
        console.log('Current:', response.current);
    }
}, 'json');
```

#### API Response Structures

**Cost Information Response:**
```json
{
    "success": true,
    "cost": 2000000,
    "formatted_cost": "€2.0M"
}
```

**Recommendations Response (Success):**
```json
{
    "success": true,
    "recommendations": [
        {
            "player": {
                "uuid": "...",
                "name": "Player Name",
                "position": "ST",
                "rating": 85,
                "value": 15000000,
                ...
            },
            "score": 150.5,
            "reason": "Fills empty ST position",
            "priority": "high"
        }
    ],
    "budget": 498000000,
    "cost_paid": 2000000,
    "emptyPositions": ["ST", "CB"],
    "weakPositions": ["LB"],
    "teamAnalysis": {
        "totalPlayers": 9,
        "avgRating": 72.5,
        "positionCounts": {...}
    }
}
```

**Insufficient Budget Response:**
```json
{
    "success": false,
    "message": "Insufficient budget for player recommendations",
    "required": 2000000,
    "current": 1500000,
    "error_type": "insufficient_budget"
}
```

## Customization

### Adjusting Recommendation Criteria

Edit `api/recommend_players_api.php`:

```php
// Change weak position threshold (default: 75)
if ($avgRating < 75) {
    $weakPositions[] = $pos;
}

// Adjust max recommendations (default: 10)
$maxRecommendations = 10;

// Modify scoring weights
$score += 100; // Empty position bonus
$score += 50;  // Weak position bonus
$score += 30;  // Formation match bonus
```

### Styling Customization

Edit `assets/css/recommendations-modal.css`:

```css
/* Change priority colors */
.recommendation-item[data-priority="high"] {
    background: linear-gradient(135deg, #fef2f2 0%, #fee2e2 100%);
}

/* Adjust modal size */
.max-w-4xl {
    max-width: 56rem; /* Change as needed */
}
```

## Technical Details

### Dependencies
- jQuery (for AJAX and DOM manipulation)
- Lucide Icons (for UI icons)
- SweetAlert2 (for confirmation dialogs)
- Tailwind CSS (for styling)

### Browser Compatibility
- Chrome/Edge: ✅ Full support
- Firefox: ✅ Full support
- Safari: ✅ Full support
- Mobile browsers: ✅ Responsive design

### Performance
- Player data is cached in PHP (static variable)
- Recommendations calculated server-side
- Modal loads on-demand
- Smooth animations with CSS transitions

## Future Enhancements

Potential improvements for future versions:
1. Machine learning-based recommendations
2. Historical performance analysis
3. Chemistry/synergy calculations
4. Injury risk assessment
5. Age and potential growth factors
6. Tactical fit analysis
7. Save favorite recommendations
8. Compare multiple players side-by-side
9. Budget planning tools
10. Transfer market integration

## Troubleshooting

### No Recommendations Shown
- Check if player JSON files exist in `assets/json/`
- Verify budget is sufficient
- Ensure team has available slots

### API Errors
- Check PHP error logs
- Verify database connection
- Ensure user is authenticated

### Styling Issues
- Clear browser cache
- Check if CSS file is loaded
- Verify Tailwind CSS is available

## Support

For issues or questions:
1. Check the browser console for JavaScript errors
2. Review PHP error logs for API issues
3. Verify all files are properly uploaded
4. Ensure database schema is up to date

## License

This feature is part of the Dream Team application and follows the same license terms.
