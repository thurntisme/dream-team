# Dream Team League System

## Overview

The League system provides a complete offline football league experience with 20 clubs (19 AI clubs + user's club).

## Features

### 🏆 League Standings

- Complete league table with 20 teams
- Real-time position tracking
- Points, goals, wins/draws/losses statistics
- Goal difference calculation
- Color-coded positions (Champions League, Europa League, Relegation)

### 📅 Match Calendar

- Full season fixture list (38 gameweeks)
- Home and away matches for each team
- Interactive gameweek simulation
- "Play Gameweek" button simulates entire gameweek when you have a match
- "Simulate Gameweek" button for gameweeks without user matches

### 📊 Match History

- Complete match history for user's club
- Result tracking (Win/Draw/Loss)
- Score records
- Home/Away venue indication
- Chronological match listing

## Database Structure

### League Teams (`league_teams`)

- Stores all 20 clubs for each season
- Tracks statistics: matches played, wins, draws, losses, goals
- Identifies user's team vs AI teams

### League Matches (`league_matches`)

- Complete fixture list for the season
- Match results and scores
- Gameweek organization
- Match status (scheduled/completed)

## AI Clubs

The system includes 19 original fantasy club names to avoid copyright issues:

- Thunder Bay United, Golden Eagles FC, Crystal Wolves
- Phoenix Rising, Midnight Strikers, Velocity FC
- Storm City FC, Iron Lions, Silver Hawks United
- And 10 more competitive fantasy clubs

## Match Simulation

- Intelligent match simulation based on team strength
- Home advantage factor
- Form-based performance calculation
- Realistic score generation using probability models
- Automatic statistics updates
- **Gameweek-based simulation**: When you play your match, all other matches in that gameweek are automatically simulated

## Navigation

- Accessible via main navigation menu
- Three main tabs: Standings, Calendar, History
- Mobile-responsive design
- Real-time updates after match simulation

## Installation

The league system is automatically initialized when:

1. User first visits the league page
2. Database tables are created during installation
3. 20 teams are generated for the current season
4. Full fixture list is created (38 gameweeks)

## Usage

1. **View Standings**: See current league position and statistics
2. **Check Calendar**: View upcoming matches and play gameweeks
3. **Review History**: Analyze past performance and results
4. **Play Gameweeks**: When you have a match, playing it simulates the entire gameweek
5. **Simulate Gameweeks**: For gameweeks without your matches, simulate all matches at once

The league provides an engaging single-player football management experience with realistic competition and progression tracking.
