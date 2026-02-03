# Database Documentation

This document provides an overview of all database tables used in the Dream Team football management system.

## Core Tables

### users
The main user accounts table storing club managers and their information.

```sql
CREATE TABLE users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL,
    email TEXT UNIQUE NOT NULL,
    password TEXT NOT NULL,
    club_name TEXT,
    formation TEXT DEFAULT "4-4-2",
    team TEXT DEFAULT "[]",
    substitutes TEXT DEFAULT "[]",
    budget INTEGER DEFAULT 1000000000,
    max_players INTEGER DEFAULT 23,
    fans INTEGER DEFAULT 5000,
    club_exp INTEGER DEFAULT 0,
    club_level INTEGER DEFAULT 1,
    user_plan TEXT DEFAULT "free",
    plan_expires_at DATETIME,
    last_login DATETIME,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
)
```

**Purpose**: Stores user accounts, club information, team data, and financial details.

**Key Fields**:
- `team`: JSON array of player objects representing the starting XI
- `substitutes`: JSON array of substitute players
- `budget`: Club's available money in euros
- `formation`: Current tactical formation (e.g., "4-4-2")
- `max_players`: Maximum squad size (can be increased via shop items)

### user_settings
User preferences and configuration settings.

```sql
CREATE TABLE user_settings (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL,
    setting_key TEXT NOT NULL,
    setting_value TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE(user_id, setting_key)
)
```

**Purpose**: Stores user-specific settings and preferences with unique constraint per user.

## Transfer System

### transfer_bids
Manages player transfer bids between clubs.

```sql
CREATE TABLE transfer_bids (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    bidder_id INTEGER NOT NULL,
    owner_id INTEGER NOT NULL,
    player_index INTEGER NOT NULL,
    player_uuid TEXT NOT NULL,
    bid_amount INTEGER NOT NULL,
    status TEXT DEFAULT "pending",
    bid_time DATETIME DEFAULT CURRENT_TIMESTAMP,
    response_time DATETIME,
    FOREIGN KEY (bidder_id) REFERENCES users(id),
    FOREIGN KEY (owner_id) REFERENCES users(id)
)
```

**Purpose**: Tracks transfer bids between clubs for player purchases.

**Key Fields**:
- `bidder_id`: User making the bid
- `owner_id`: User who owns the player
- `player_uuid`: Unique identifier for the player
- `status`: "pending", "accepted", "rejected"

### player_inventory
Stores purchased players not currently in active squads.

```sql
CREATE TABLE player_inventory (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL,
    player_uuid TEXT NOT NULL,
    player_data TEXT NOT NULL,
    purchase_price INTEGER NOT NULL,
    purchase_date DATETIME DEFAULT CURRENT_TIMESTAMP,
    status TEXT DEFAULT "available",
    FOREIGN KEY (user_id) REFERENCES users(id)
)
```

**Purpose**: Manages players owned by clubs but not in active squads.

**Key Fields**:
- `player_data`: JSON object containing complete player information
- `purchase_price`: Amount paid for the player
- `status`: "available", "sold", etc.

## Youth System

### young_players
Academy players that can be developed and potentially sold.

```sql
CREATE TABLE young_players (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    club_id INTEGER NOT NULL,
    name TEXT NOT NULL,
    age INTEGER NOT NULL,
    position TEXT NOT NULL,
    potential_rating INTEGER NOT NULL,
    current_rating INTEGER NOT NULL,
    development_stage TEXT DEFAULT "academy",
    contract_years INTEGER DEFAULT 3,
    value INTEGER NOT NULL,
    training_focus TEXT DEFAULT "balanced",
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    promoted_at DATETIME,
    FOREIGN KEY (club_id) REFERENCES users(id) ON DELETE CASCADE
)
```

**Purpose**: Manages youth academy players for each club.

**Key Fields**:
- `potential_rating`: Maximum rating the player can achieve
- `current_rating`: Player's current skill level
- `development_stage`: "academy", "reserve", "first_team" (default: "academy")
- `training_focus`: Specific skill being developed (default: "balanced")
- `promoted_at`: Timestamp when player was promoted to first team

### young_player_bids
Transfer bids for young players between clubs.

```sql
CREATE TABLE young_player_bids (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    young_player_id INTEGER NOT NULL,
    bidder_club_id INTEGER NOT NULL,
    owner_club_id INTEGER NOT NULL,
    bid_amount INTEGER NOT NULL,
    status TEXT DEFAULT "pending",
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    expires_at DATETIME NOT NULL,
    FOREIGN KEY (young_player_id) REFERENCES young_players(id) ON DELETE CASCADE,
    FOREIGN KEY (bidder_club_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (owner_club_id) REFERENCES users(id) ON DELETE CASCADE
)
```

**Purpose**: Handles transfer market for youth players with expiration tracking.

## League System

### league_teams
Teams participating in league competitions.

```sql
CREATE TABLE league_teams (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    season INTEGER NOT NULL,
    user_id INTEGER,
    name TEXT NOT NULL,
    is_user BOOLEAN DEFAULT 0,
    division INTEGER DEFAULT 1,
    matches_played INTEGER DEFAULT 0,
    wins INTEGER DEFAULT 0,
    draws INTEGER DEFAULT 0,
    losses INTEGER DEFAULT 0,
    goals_for INTEGER DEFAULT 0,
    goals_against INTEGER DEFAULT 0,
    points INTEGER DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
)
```

**Purpose**: Tracks league standings and statistics for each season.

**Key Fields**:
- `is_user`: Distinguishes between user clubs and AI clubs
- `division`: League tier (1 = Premier League, 2 = Championship)
- League statistics: wins, draws, losses, goals, points

### league_matches
Individual match fixtures and results.

```sql
CREATE TABLE league_matches (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    season INTEGER NOT NULL,
    gameweek INTEGER NOT NULL,
    home_team_id INTEGER NOT NULL,
    away_team_id INTEGER NOT NULL,
    home_score INTEGER,
    away_score INTEGER,
    status TEXT DEFAULT "scheduled",
    match_date DATE NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (home_team_id) REFERENCES league_teams(id),
    FOREIGN KEY (away_team_id) REFERENCES league_teams(id)
)
```

**Purpose**: Manages match fixtures, scheduling, and results.

**Key Fields**:
- `gameweek`: Week number in the season
- `status`: "scheduled", "completed", "postponed"
- `home_score`/`away_score`: Match results (NULL if not played)

## Shop System

### shop_items
Available items for purchase in the club shop.

```sql
CREATE TABLE shop_items (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL,
    description TEXT NOT NULL,
    price INTEGER NOT NULL,
    effect_type TEXT NOT NULL,
    effect_value TEXT NOT NULL,
    category TEXT NOT NULL,
    icon TEXT DEFAULT "package",
    duration INTEGER DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
)
```

**Purpose**: Defines purchasable items that provide various game benefits.

**Categories**:
- `training`: Player improvement items
- `financial`: Budget and income boosters
- `special`: Unique game advantages
- `premium`: Permanent upgrades
- `stadium`: Stadium customization

**Key Fields**:
- `effect_type`: Type of effect (e.g., "player_boost", "budget_boost")
- `effect_value`: JSON object with effect parameters
- `duration`: Effect duration in days (0 = permanent)

### user_inventory
Items purchased by users and their status.

```sql
CREATE TABLE user_inventory (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL,
    item_id INTEGER NOT NULL,
    purchased_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    expires_at DATETIME NULL,
    quantity INTEGER DEFAULT 1,
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (item_id) REFERENCES shop_items(id)
)
```

**Purpose**: Tracks items owned by users and their expiration status.

## Staff System

### club_staff
Club employees providing various bonuses and services.

```sql
CREATE TABLE club_staff (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL,
    staff_type TEXT NOT NULL,
    name TEXT NOT NULL,
    level INTEGER DEFAULT 1,
    salary INTEGER NOT NULL,
    contract_weeks INTEGER DEFAULT 52,
    contract_weeks_remaining INTEGER DEFAULT 52,
    hired_date DATETIME DEFAULT CURRENT_TIMESTAMP,
    bonus_applied_this_week BOOLEAN DEFAULT 0,
    FOREIGN KEY (user_id) REFERENCES users(id)
)
```

**Purpose**: Manages club staff members and their contracts.

**Staff Types**:
- `coach`: Training bonuses
- `scout`: Transfer market advantages
- `physio`: Injury prevention
- `analyst`: Match preparation bonuses

## Scouting System

### scouting_reports
Player scouting information and reports.

```sql
CREATE TABLE scouting_reports (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL,
    player_uuid TEXT NOT NULL,
    scouted_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    report_quality INTEGER DEFAULT 1,
    FOREIGN KEY (user_id) REFERENCES users(id)
)
```

**Purpose**: Tracks which players have been scouted by each club.

**Key Fields**:
- `report_quality`: Quality level of the scouting report (1-5)
- `player_uuid`: References player in the global player database

## Statistics and Analytics

### player_stats
Historical player performance data.

```sql
CREATE TABLE player_stats (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL,
    player_id TEXT NOT NULL,
    player_name TEXT NOT NULL,
    position TEXT NOT NULL,
    matches_played INTEGER DEFAULT 0,
    goals INTEGER DEFAULT 0,
    assists INTEGER DEFAULT 0,
    yellow_cards INTEGER DEFAULT 0,
    red_cards INTEGER DEFAULT 0,
    total_rating REAL DEFAULT 0,
    avg_rating REAL DEFAULT 0,
    clean_sheets INTEGER DEFAULT 0,
    saves INTEGER DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id),
    UNIQUE(user_id, player_id)
)
```

**Purpose**: Stores comprehensive player performance statistics.

**Key Fields**:
- `player_id`: Player UUID identifier
- `yellow_cards`/`red_cards`: Disciplinary records
- `total_rating`: Sum of all match ratings
- `avg_rating`: Average match rating
- `clean_sheets`: For goalkeepers - matches without conceding
- `saves`: For goalkeepers - total saves made
- Unique constraint ensures one record per player per user

## Communication Systems

### support_tickets
User support and help requests.

```sql
CREATE TABLE support_tickets (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL,
    ticket_number TEXT UNIQUE NOT NULL,
    priority TEXT DEFAULT "medium",
    category TEXT NOT NULL,
    subject TEXT NOT NULL,
    message TEXT NOT NULL,
    status TEXT DEFAULT "open",
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    last_response_at DATETIME,
    admin_response TEXT,
    resolution_notes TEXT,
    FOREIGN KEY (user_id) REFERENCES users(id)
)
```

**Purpose**: Manages user support requests and tickets with tracking.

**Key Fields**:
- `ticket_number`: Unique ticket identifier for reference
- `category`: Support category/type
- `priority`: "low", "medium", "high", "urgent"
- `status`: "open", "in_progress", "resolved", "closed"
- `last_response_at`: Timestamp of last response
- `admin_response`: Admin's response to the ticket
- `resolution_notes`: Notes about how the ticket was resolved

### user_feedback
User feedback and suggestions.

```sql
CREATE TABLE user_feedback (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL,
    category TEXT NOT NULL,
    subject TEXT NOT NULL,
    message TEXT NOT NULL,
    status TEXT DEFAULT "pending",
    reward_amount INTEGER DEFAULT 140,
    reward_paid BOOLEAN DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    admin_response TEXT,
    FOREIGN KEY (user_id) REFERENCES users(id)
)
```

**Purpose**: Collects user feedback and feature suggestions with reward system.

**Key Fields**:
- `category`: Feedback category/type
- `subject`: Feedback subject line
- `status`: "pending", "reviewed", "resolved"
- `reward_amount`: Budget reward for feedback (default: €140)
- `reward_paid`: Whether reward has been paid to user
- `admin_response`: Admin's response to the feedback

### news
Club and game news articles.

```sql
CREATE TABLE news (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL,
    category TEXT NOT NULL,
    priority TEXT NOT NULL DEFAULT "normal",
    title TEXT NOT NULL,
    content TEXT NOT NULL,
    player_data TEXT,
    actions TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    expires_at DATETIME NOT NULL,
    FOREIGN KEY (user_id) REFERENCES users(id)
)
```

**Purpose**: Manages news articles and notifications for users with expiration.

**Key Fields**:
- `category`: News category/type
- `priority`: "low", "normal", "high", "urgent"
- `player_data`: JSON data about related players
- `actions`: JSON array of available actions for the news item
- `expires_at`: When the news item expires and should be removed

## International System

### nation_calls
National team call-ups for players.

```sql
CREATE TABLE nation_calls (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL,
    called_players TEXT NOT NULL,
    total_reward INTEGER NOT NULL,
    call_date DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id)
)
```

**Purpose**: Manages international call-ups for club players and rewards.

**Key Fields**:
- `called_players`: JSON array of players called up for international duty
- `total_reward`: Total financial reward earned from nation calls
- `call_date`: When the nation call occurred

## Stadium System

### stadiums
Club stadium information and customization.

```sql
CREATE TABLE stadiums (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL,
    name TEXT DEFAULT "Home Stadium",
    capacity INTEGER DEFAULT 10000,
    level INTEGER DEFAULT 1,
    facilities TEXT DEFAULT "{}",
    last_upgrade DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id)
)
```

**Purpose**: Stores stadium information and upgrade levels.

**Key Fields**:
- `name`: Stadium name (default: "Home Stadium")
- `capacity`: Stadium capacity (default: 10,000)
- `level`: Stadium upgrade level
- `facilities`: JSON object storing facility upgrades
- `last_upgrade`: Timestamp of last stadium upgrade

## Database Relationships

### Primary Relationships
- `users` → `user_settings` (1:many)
- `users` → `young_players` (1:many)
- `users` → `transfer_bids` (1:many as bidder/owner)
- `users` → `league_teams` (1:1 per season)
- `users` → `club_staff` (1:many)
- `users` → `user_inventory` (1:many)

### Secondary Relationships
- `young_players` → `young_player_bids` (1:many)
- `league_teams` → `league_matches` (1:many as home/away)
- `shop_items` → `user_inventory` (1:many)

## Data Storage Notes

### JSON Fields
Several tables store JSON data for flexibility:
- `users.team`: Array of player objects
- `users.substitutes`: Array of substitute players
- `player_inventory.player_data`: Complete player information
- `shop_items.effect_value`: Effect parameters

### File Storage
- Player images and assets are stored in `/assets/images/players/`
- Club logos and badges in `/assets/images/clubs/`
- The main database file is stored as `database/dreamteam.db`

### Indexing Recommendations
For optimal performance, consider adding indexes on:
- `transfer_bids.bidder_id` and `transfer_bids.owner_id`
- `league_matches.season` and `league_matches.gameweek`
- `user_inventory.user_id` and `user_inventory.expires_at`
- `young_players.club_id`
- `scouting_reports.user_id`

This database structure supports a comprehensive football management game with transfer systems, youth development, league competitions, and various club management features.