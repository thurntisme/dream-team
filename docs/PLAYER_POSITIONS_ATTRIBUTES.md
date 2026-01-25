# Player Positions & Key Attributes

This document outlines all player positions in the Dream Team game and their associated key attributes used for player evaluation and display.

## Position Categories

### Goalkeeper (GK)

**Key Attributes:**
- **Diving** - Ability to make diving saves
- **Handling** - Ball control and catching ability
- **Kicking** - Distribution and goal kicks accuracy
- **Reflexes** - Quick reaction to shots
- **Positioning** - Spatial awareness and positioning

**Role:** Last line of defense, shot-stopping, distribution

---

### Defenders

#### Centre Back (CB)

**Key Attributes:**
- **Defending** - Overall defensive capability
- **Heading** - Aerial duels and clearances
- **Strength** - Physical presence and power
- **Marking** - Ability to track opponents
- **Tackling** - Winning the ball cleanly

**Role:** Central defensive anchor, aerial dominance, organizing defense

#### Left Back (LB)

**Key Attributes:**
- **Pace** - Speed to track wingers
- **Crossing** - Delivery from wide positions
- **Defending** - Defensive fundamentals
- **Stamina** - Endurance for box-to-box play
- **Dribbling** - Ball control in tight spaces

**Role:** Left-sided defender, overlapping runs, defensive coverage

#### Right Back (RB)

**Key Attributes:**
- **Pace** - Speed to track wingers
- **Crossing** - Delivery from wide positions
- **Defending** - Defensive fundamentals
- **Stamina** - Endurance for box-to-box play
- **Dribbling** - Ball control in tight spaces

**Role:** Right-sided defender, overlapping runs, defensive coverage

---

### Midfielders

#### Defensive Midfielder (CDM)

**Key Attributes:**
- **Passing** - Distribution from deep
- **Tackling** - Breaking up opposition attacks
- **Positioning** - Reading the game defensively
- **Strength** - Physical duels in midfield
- **Vision** - Seeing passing opportunities

**Role:** Defensive shield, ball recovery, initiating attacks

#### Central Midfielder (CM)

**Key Attributes:**
- **Passing** - All-around distribution
- **Dribbling** - Ball progression through midfield
- **Vision** - Creating opportunities
- **Stamina** - Box-to-box endurance
- **Shooting** - Long-range threat

**Role:** Box-to-box midfielder, linking play, versatile contribution

#### Attacking Midfielder (CAM)

**Key Attributes:**
- **Passing** - Creative distribution
- **Dribbling** - Close control and flair
- **Vision** - Seeing killer passes
- **Shooting** - Goal-scoring threat
- **Creativity** - Unlocking defenses

**Role:** Playmaker, creating chances, scoring goals

#### Left Midfielder (LM)

**Key Attributes:**
- **Pace** - Speed on the wing
- **Crossing** - Delivery into the box
- **Dribbling** - Beating defenders
- **Stamina** - Tracking back and forward
- **Passing** - Link-up play

**Role:** Wide midfielder, providing width, tracking back

#### Right Midfielder (RM)

**Key Attributes:**
- **Pace** - Speed on the wing
- **Crossing** - Delivery into the box
- **Dribbling** - Beating defenders
- **Stamina** - Tracking back and forward
- **Passing** - Link-up play

**Role:** Wide midfielder, providing width, tracking back

---

### Forwards

#### Left Winger (LW)

**Key Attributes:**
- **Pace** - Explosive speed
- **Dribbling** - One-on-one ability
- **Crossing** - Delivery from wide areas
- **Shooting** - Cutting inside to score
- **Agility** - Quick changes of direction

**Role:** Wide attacker, beating defenders, creating/scoring goals

#### Right Winger (RW)

**Key Attributes:**
- **Pace** - Explosive speed
- **Dribbling** - One-on-one ability
- **Crossing** - Delivery from wide areas
- **Shooting** - Cutting inside to score
- **Agility** - Quick changes of direction

**Role:** Wide attacker, beating defenders, creating/scoring goals

#### Striker (ST)

**Key Attributes:**
- **Shooting** - Overall finishing ability
- **Finishing** - Converting chances
- **Positioning** - Movement in the box
- **Strength** - Holding up play
- **Heading** - Aerial threat

**Role:** Primary goal scorer, target man, finishing chances

#### Centre Forward (CF)

**Key Attributes:**
- **Shooting** - Goal-scoring ability
- **Dribbling** - Creating space
- **Passing** - Link-up play
- **Positioning** - Finding space
- **Creativity** - Unpredictability

**Role:** False nine, dropping deep, linking play, scoring goals

---

## Attribute Rating System

All attributes are rated on a scale of **30-99**:

- **90-99**: World Class
- **85-89**: Elite
- **80-84**: Excellent
- **75-79**: Very Good
- **70-74**: Good
- **65-69**: Above Average
- **60-64**: Average
- **50-59**: Below Average
- **30-49**: Poor

### Attribute Generation

Player attributes are generated based on:
1. **Overall Rating** - Base value for attribute generation
2. **Position** - Determines which 5 key attributes are displayed
3. **Variation** - Random modifier (-5 to +5) for realistic diversity

**Formula:**
```
Attribute Value = Overall Rating + Random(-5, +5)
Capped between 30 and 99
```

---

## Position Compatibility

Some players can play multiple positions effectively. The system tracks:

- **Primary Position** - Player's main position
- **Playable Positions** - All positions the player can perform in

### Common Multi-Position Players

- **CB** → Can play CDM
- **LB/RB** → Can play LM/RM or LW/RW
- **CDM** → Can play CB or CM
- **CM** → Can play CDM or CAM
- **CAM** → Can play CM or CF
- **LM/RM** → Can play LW/RW
- **LW/RW** → Can play LM/RM or ST
- **CF** → Can play CAM or ST
- **ST** → Can play CF or LW/RW

---

## Usage in Game

### Player Info Modal

When viewing player details, the system displays:
1. Primary position badge
2. Overall rating (★ format)
3. Five key attributes with progress bars
4. All playable positions as badges

### Transfer Market

Players are filtered by:
- Position (dropdown filter)
- Overall rating
- Market value
- Category (Modern, Legend, Young, Standard)

### Team Selection

Position compatibility is checked when:
- Assigning players to formations
- Validating team lineups
- Suggesting player recommendations

---

## Technical Implementation

### JavaScript Function

```javascript
function generatePlayerStats(position, rating) {
    const baseStats = {
        'GK': ['Diving', 'Handling', 'Kicking', 'Reflexes', 'Positioning'],
        'CB': ['Defending', 'Heading', 'Strength', 'Marking', 'Tackling'],
        // ... other positions
    };
    
    const positionStats = baseStats[position] || baseStats['CM'];
    const stats = {};
    
    positionStats.forEach(stat => {
        const variation = Math.floor(Math.random() * 10) - 5;
        const statValue = Math.max(30, Math.min(99, rating + variation));
        stats[stat] = statValue;
    });
    
    return stats;
}
```

### Database Storage

Player attributes are stored in JSON format within the `player_data` column:

```json
{
    "name": "Player Name",
    "position": "CM",
    "rating": 85,
    "playablePositions": ["CM", "CDM", "CAM"],
    "value": 25000000
}
```

---

## Future Enhancements

Potential improvements to the attribute system:

1. **Hidden Attributes** - Mental attributes (composure, aggression, work rate)
2. **Weak Foot Rating** - Ability with non-preferred foot
3. **Skill Moves** - Dribbling complexity rating
4. **Trait System** - Special abilities (e.g., "Power Header", "Speed Dribbler")
5. **Form System** - Dynamic attribute modifiers based on recent performance
6. **Training Impact** - Attribute growth through training sessions
7. **Age Curves** - Attribute changes based on player age

---

## Related Documentation

- [Player Recommendations Feature](PLAYER_RECOMMENDATIONS_FEATURE.md)
- [League System](LEAGUE_README.md)
- [Database Schema](database.md)
