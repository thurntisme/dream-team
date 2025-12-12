<?php
// League system functions

// Fake club names for the league
define('FAKE_CLUBS', [
    'Thunder Bay United',
    'Golden Eagles FC',
    'Crystal Wolves',
    'Phoenix Rising',
    'Midnight Strikers',
    'Velocity FC',
    'Storm City FC',
    'Iron Lions',
    'Silver Hawks United',
    'Crimson Tigers',
    'Azure Dragons',
    'Emerald Panthers',
    'Royal Falcons',
    'Shadow Wolves',
    'Lightning Bolts',
    'Fire Phoenixes',
    'Ice Bears United',
    'Wind Runners FC',
    'Earth Guardians'
]);

function initializeLeague($db, $user_id)
{
    // Create league tables if they don't exist
    createLeagueTables($db);

    // Check if league is already initialized for current season
    $current_season = date('Y');
    $stmt = $db->prepare('SELECT COUNT(*) as count FROM league_teams WHERE season = :season');
    $stmt->bindValue(':season', $current_season, SQLITE3_INTEGER);
    $result = $stmt->execute();
    $row = $result->fetchArray(SQLITE3_ASSOC);

    if ($row['count'] == 0) {
        // Initialize new season
        createLeagueTeams($db, $user_id, $current_season);
        generateFixtures($db, $current_season);
    }
}

function createLeagueTables($db)
{
    // League teams table
    $sql = 'CREATE TABLE IF NOT EXISTS league_teams (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        season INTEGER NOT NULL,
        user_id INTEGER,
        name TEXT NOT NULL,
        is_user BOOLEAN DEFAULT 0,
        matches_played INTEGER DEFAULT 0,
        wins INTEGER DEFAULT 0,
        draws INTEGER DEFAULT 0,
        losses INTEGER DEFAULT 0,
        goals_for INTEGER DEFAULT 0,
        goals_against INTEGER DEFAULT 0,
        points INTEGER DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )';
    $db->exec($sql);

    // League matches table
    $sql = 'CREATE TABLE IF NOT EXISTS league_matches (
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
    )';
    $db->exec($sql);
}

function createLeagueTeams($db, $user_id, $season)
{
    // Get user's club name
    $stmt = $db->prepare('SELECT club_name FROM users WHERE id = :id');
    $stmt->bindValue(':id', $user_id, SQLITE3_INTEGER);
    $result = $stmt->execute();
    $user = $result->fetchArray(SQLITE3_ASSOC);

    // Insert user's team
    $stmt = $db->prepare('INSERT INTO league_teams (season, user_id, name, is_user) VALUES (:season, :user_id, :name, 1)');
    $stmt->bindValue(':season', $season, SQLITE3_INTEGER);
    $stmt->bindValue(':user_id', $user_id, SQLITE3_INTEGER);
    $stmt->bindValue(':name', $user['club_name'], SQLITE3_TEXT);
    $stmt->execute();

    // Insert fake teams
    foreach (FAKE_CLUBS as $club_name) {
        $stmt = $db->prepare('INSERT INTO league_teams (season, name, is_user) VALUES (:season, :name, 0)');
        $stmt->bindValue(':season', $season, SQLITE3_INTEGER);
        $stmt->bindValue(':name', $club_name, SQLITE3_TEXT);
        $stmt->execute();
    }
}

function generateFixtures($db, $season)
{
    // Get all teams for the season
    $stmt = $db->prepare('SELECT id FROM league_teams WHERE season = :season ORDER BY id');
    $stmt->bindValue(':season', $season, SQLITE3_INTEGER);
    $result = $stmt->execute();

    $teams = [];
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $teams[] = $row['id'];
    }

    $num_teams = count($teams);
    $total_rounds = ($num_teams - 1) * 2; // Home and away

    // Generate round-robin fixtures
    $fixtures = [];
    $gameweek = 1;

    // First half of season (each team plays each other once)
    for ($round = 0; $round < $num_teams - 1; $round++) {
        $round_fixtures = [];

        for ($i = 0; $i < $num_teams / 2; $i++) {
            $home = ($round + $i) % ($num_teams - 1);
            $away = ($num_teams - 1 - $i + $round) % ($num_teams - 1);

            // Last team stays in place
            if ($i == 0) {
                $away = $num_teams - 1;
            }

            $round_fixtures[] = [$teams[$home], $teams[$away]];
        }

        // Insert fixtures for this gameweek
        $match_date = date('Y-m-d', strtotime('+' . ($gameweek - 1) . ' weeks'));

        foreach ($round_fixtures as $fixture) {
            $stmt = $db->prepare('INSERT INTO league_matches (season, gameweek, home_team_id, away_team_id, match_date) VALUES (:season, :gameweek, :home, :away, :date)');
            $stmt->bindValue(':season', $season, SQLITE3_INTEGER);
            $stmt->bindValue(':gameweek', $gameweek, SQLITE3_INTEGER);
            $stmt->bindValue(':home', $fixture[0], SQLITE3_INTEGER);
            $stmt->bindValue(':away', $fixture[1], SQLITE3_INTEGER);
            $stmt->bindValue(':date', $match_date, SQLITE3_TEXT);
            $stmt->execute();
        }

        $gameweek++;
    }

    // Second half of season (reverse fixtures)
    for ($round = 0; $round < $num_teams - 1; $round++) {
        $round_fixtures = [];

        for ($i = 0; $i < $num_teams / 2; $i++) {
            $home = ($round + $i) % ($num_teams - 1);
            $away = ($num_teams - 1 - $i + $round) % ($num_teams - 1);

            // Last team stays in place
            if ($i == 0) {
                $away = $num_teams - 1;
            }

            // Reverse home/away for second half
            $round_fixtures[] = [$teams[$away], $teams[$home]];
        }

        // Insert fixtures for this gameweek
        $match_date = date('Y-m-d', strtotime('+' . ($gameweek - 1) . ' weeks'));

        foreach ($round_fixtures as $fixture) {
            $stmt = $db->prepare('INSERT INTO league_matches (season, gameweek, home_team_id, away_team_id, match_date) VALUES (:season, :gameweek, :home, :away, :date)');
            $stmt->bindValue(':season', $season, SQLITE3_INTEGER);
            $stmt->bindValue(':gameweek', $gameweek, SQLITE3_INTEGER);
            $stmt->bindValue(':home', $fixture[0], SQLITE3_INTEGER);
            $stmt->bindValue(':away', $fixture[1], SQLITE3_INTEGER);
            $stmt->bindValue(':date', $match_date, SQLITE3_TEXT);
            $stmt->execute();
        }

        $gameweek++;
    }
}

function getCurrentSeason($db)
{
    return date('Y');
}

function getCurrentGameweek($db, $season)
{
    $stmt = $db->prepare('SELECT MIN(gameweek) as gameweek FROM league_matches WHERE season = :season AND status = "scheduled"');
    $stmt->bindValue(':season', $season, SQLITE3_INTEGER);
    $result = $stmt->execute();
    $row = $result->fetchArray(SQLITE3_ASSOC);

    return $row['gameweek'] ?? 1;
}

function getLeagueStandings($db, $season)
{
    $sql = 'SELECT 
        lt.*,
        (lt.wins * 3 + lt.draws) as points,
        (lt.goals_for - lt.goals_against) as goal_difference
    FROM league_teams lt 
    WHERE lt.season = :season 
    ORDER BY points DESC, goal_difference DESC, lt.goals_for DESC, lt.name ASC';

    $stmt = $db->prepare($sql);
    $stmt->bindValue(':season', $season, SQLITE3_INTEGER);
    $result = $stmt->execute();

    $standings = [];
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $standings[] = $row;
    }

    return $standings;
}

function getUserMatches($db, $user_id, $season)
{
    // Get user's team ID
    $stmt = $db->prepare('SELECT id FROM league_teams WHERE season = :season AND user_id = :user_id');
    $stmt->bindValue(':season', $season, SQLITE3_INTEGER);
    $stmt->bindValue(':user_id', $user_id, SQLITE3_INTEGER);
    $result = $stmt->execute();
    $user_team = $result->fetchArray(SQLITE3_ASSOC);

    if (!$user_team)
        return [];

    $team_id = $user_team['id'];

    $sql = 'SELECT 
        lm.*,
        CASE 
            WHEN lm.home_team_id = :team_id THEN ht.name 
            ELSE at.name 
        END as opponent,
        CASE 
            WHEN lm.home_team_id = :team_id THEN "H" 
            ELSE "A" 
        END as venue,
        CASE 
            WHEN lm.home_team_id = :team_id THEN lm.home_score 
            ELSE lm.away_score 
        END as user_score,
        CASE 
            WHEN lm.home_team_id = :team_id THEN lm.away_score 
            ELSE lm.home_score 
        END as opponent_score,
        CASE 
            WHEN lm.status != "completed" THEN NULL
            WHEN (lm.home_team_id = :team_id AND lm.home_score > lm.away_score) OR 
                 (lm.away_team_id = :team_id AND lm.away_score > lm.home_score) THEN "W"
            WHEN lm.home_score = lm.away_score THEN "D"
            ELSE "L"
        END as result
    FROM league_matches lm
    JOIN league_teams ht ON lm.home_team_id = ht.id
    JOIN league_teams at ON lm.away_team_id = at.id
    WHERE lm.season = :season 
    AND (lm.home_team_id = :team_id OR lm.away_team_id = :team_id)
    AND lm.status = "completed"
    ORDER BY lm.gameweek DESC, lm.match_date DESC';

    $stmt = $db->prepare($sql);
    $stmt->bindValue(':season', $season, SQLITE3_INTEGER);
    $stmt->bindValue(':team_id', $team_id, SQLITE3_INTEGER);
    $result = $stmt->execute();

    $matches = [];
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $matches[] = $row;
    }

    return $matches;
}

function getUpcomingMatches($db, $user_id, $season)
{
    // Get user's team ID
    $stmt = $db->prepare('SELECT id FROM league_teams WHERE season = :season AND user_id = :user_id');
    $stmt->bindValue(':season', $season, SQLITE3_INTEGER);
    $stmt->bindValue(':user_id', $user_id, SQLITE3_INTEGER);
    $result = $stmt->execute();
    $user_team = $result->fetchArray(SQLITE3_ASSOC);

    if (!$user_team)
        return [];

    $team_id = $user_team['id'];

    // Get next few gameweeks
    $current_gameweek = getCurrentGameweek($db, $season);

    $sql = 'SELECT 
        lm.*,
        ht.name as home_team,
        at.name as away_team,
        ht.user_id as home_team_id,
        at.user_id as away_team_id
    FROM league_matches lm
    JOIN league_teams ht ON lm.home_team_id = ht.id
    JOIN league_teams at ON lm.away_team_id = at.id
    WHERE lm.season = :season 
    AND lm.gameweek >= :current_gameweek
    AND lm.gameweek <= :max_gameweek
    ORDER BY lm.gameweek ASC, lm.match_date ASC';

    $stmt = $db->prepare($sql);
    $stmt->bindValue(':season', $season, SQLITE3_INTEGER);
    $stmt->bindValue(':current_gameweek', $current_gameweek, SQLITE3_INTEGER);
    $stmt->bindValue(':max_gameweek', $current_gameweek + 5, SQLITE3_INTEGER); // Show next 5 gameweeks
    $result = $stmt->execute();

    $matches = [];
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        // Set team IDs for user identification
        $row['home_team_id'] = $row['home_team_id'] ?: 0;
        $row['away_team_id'] = $row['away_team_id'] ?: 0;
        $matches[] = $row;
    }

    return $matches;
}

function simulateMatch($db, $match_id, $user_id)
{
    // Get match details
    $stmt = $db->prepare('SELECT * FROM league_matches WHERE id = :id AND status = "scheduled"');
    $stmt->bindValue(':id', $match_id, SQLITE3_INTEGER);
    $result = $stmt->execute();
    $match = $result->fetchArray(SQLITE3_ASSOC);

    if (!$match)
        return false;

    // Get team details
    $stmt = $db->prepare('SELECT * FROM league_teams WHERE id = :id');
    $stmt->bindValue(':id', $match['home_team_id'], SQLITE3_INTEGER);
    $result = $stmt->execute();
    $home_team = $result->fetchArray(SQLITE3_ASSOC);

    $stmt = $db->prepare('SELECT * FROM league_teams WHERE id = :id');
    $stmt->bindValue(':id', $match['away_team_id'], SQLITE3_INTEGER);
    $result = $stmt->execute();
    $away_team = $result->fetchArray(SQLITE3_ASSOC);

    // Simple match simulation
    $home_strength = calculateTeamStrength($home_team, true); // Home advantage
    $away_strength = calculateTeamStrength($away_team, false);

    // Generate scores based on team strength
    $home_score = generateScore($home_strength);
    $away_score = generateScore($away_strength);

    // Update match result
    $stmt = $db->prepare('UPDATE league_matches SET home_score = :home_score, away_score = :away_score, status = "completed" WHERE id = :id');
    $stmt->bindValue(':home_score', $home_score, SQLITE3_INTEGER);
    $stmt->bindValue(':away_score', $away_score, SQLITE3_INTEGER);
    $stmt->bindValue(':id', $match_id, SQLITE3_INTEGER);
    $stmt->execute();

    // Update team statistics
    updateTeamStats($db, $match['home_team_id'], $home_score, $away_score, true);
    updateTeamStats($db, $match['away_team_id'], $away_score, $home_score, false);

    return true;
}

function simulateGameweek($db, $match_id, $user_id)
{
    // Get the gameweek of the user's match
    $stmt = $db->prepare('SELECT gameweek, season FROM league_matches WHERE id = :id');
    $stmt->bindValue(':id', $match_id, SQLITE3_INTEGER);
    $result = $stmt->execute();
    $match_info = $result->fetchArray(SQLITE3_ASSOC);

    if (!$match_info) {
        return false;
    }

    $gameweek = $match_info['gameweek'];
    $season = $match_info['season'];

    // Get user's team ID for tracking their match
    $stmt = $db->prepare('SELECT id FROM league_teams WHERE season = :season AND user_id = :user_id');
    $stmt->bindValue(':season', $season, SQLITE3_INTEGER);
    $stmt->bindValue(':user_id', $user_id, SQLITE3_INTEGER);
    $result = $stmt->execute();
    $user_team = $result->fetchArray(SQLITE3_ASSOC);
    $user_team_id = $user_team ? $user_team['id'] : null;

    // Get all matches in this gameweek with team names
    $stmt = $db->prepare('SELECT 
        lm.id, lm.home_team_id, lm.away_team_id,
        ht.name as home_team, at.name as away_team
        FROM league_matches lm
        JOIN league_teams ht ON lm.home_team_id = ht.id
        JOIN league_teams at ON lm.away_team_id = at.id
        WHERE lm.season = :season AND lm.gameweek = :gameweek AND lm.status = "scheduled"');
    $stmt->bindValue(':season', $season, SQLITE3_INTEGER);
    $stmt->bindValue(':gameweek', $gameweek, SQLITE3_INTEGER);
    $result = $stmt->execute();

    $matches_simulated = 0;
    $user_match_result = null;
    $all_results = [];

    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        if (simulateMatch($db, $row['id'], $user_id)) {
            $matches_simulated++;

            // Get the match result
            $stmt2 = $db->prepare('SELECT home_score, away_score FROM league_matches WHERE id = :id');
            $stmt2->bindValue(':id', $row['id'], SQLITE3_INTEGER);
            $result2 = $stmt2->execute();
            $match_result = $result2->fetchArray(SQLITE3_ASSOC);

            $match_data = [
                'home_team' => $row['home_team'],
                'away_team' => $row['away_team'],
                'home_score' => $match_result['home_score'],
                'away_score' => $match_result['away_score']
            ];

            // Check if this is the user's match
            if ($user_team_id && ($row['home_team_id'] == $user_team_id || $row['away_team_id'] == $user_team_id)) {
                $user_match_result = $match_data;
                $user_match_result['is_home'] = ($row['home_team_id'] == $user_team_id);
            }

            $all_results[] = $match_data;
        }
    }

    // Get user's current position in the league and budget info
    $user_position = null;
    $budget_earned = 0;
    if ($user_team_id && $user_match_result) {
        $standings = getLeagueStandings($db, $season);
        foreach ($standings as $index => $team) {
            if ($team['user_id'] == $user_id) {
                $user_position = $index + 1;
                break;
            }
        }

        // Calculate budget earned from the match
        $user_score = $user_match_result['is_home'] ? $user_match_result['home_score'] : $user_match_result['away_score'];
        $opponent_score = $user_match_result['is_home'] ? $user_match_result['away_score'] : $user_match_result['home_score'];

        // Base reward
        if ($user_score > $opponent_score) {
            $budget_earned += 5000000; // €5M for win
        } elseif ($user_score == $opponent_score) {
            $budget_earned += 2000000; // €2M for draw
        } else {
            $budget_earned += 1000000; // €1M for participation
        }

        // Goal bonus
        $budget_earned += $user_score * 500000; // €500K per goal

        // Home bonus
        if ($user_match_result['is_home']) {
            $budget_earned += 1000000; // €1M home bonus
        }
    }

    return [
        'matches_simulated' => $matches_simulated,
        'gameweek' => $gameweek,
        'user_match' => $user_match_result,
        'all_results' => $all_results,
        'user_position' => $user_position,
        'budget_earned' => $budget_earned
    ];
}

function simulateCurrentGameweek($db, $user_id, $season, $gameweek)
{
    // Get user's team ID for tracking their match
    $stmt = $db->prepare('SELECT id FROM league_teams WHERE season = :season AND user_id = :user_id');
    $stmt->bindValue(':season', $season, SQLITE3_INTEGER);
    $stmt->bindValue(':user_id', $user_id, SQLITE3_INTEGER);
    $result = $stmt->execute();
    $user_team = $result->fetchArray(SQLITE3_ASSOC);
    $user_team_id = $user_team ? $user_team['id'] : null;

    // Get all matches in this gameweek with team names
    $stmt = $db->prepare('SELECT 
        lm.id, lm.home_team_id, lm.away_team_id,
        ht.name as home_team, at.name as away_team
        FROM league_matches lm
        JOIN league_teams ht ON lm.home_team_id = ht.id
        JOIN league_teams at ON lm.away_team_id = at.id
        WHERE lm.season = :season AND lm.gameweek = :gameweek AND lm.status = "scheduled"');
    $stmt->bindValue(':season', $season, SQLITE3_INTEGER);
    $stmt->bindValue(':gameweek', $gameweek, SQLITE3_INTEGER);
    $result = $stmt->execute();

    $matches_simulated = 0;
    $user_match_result = null;
    $all_results = [];

    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        if (simulateMatch($db, $row['id'], $user_id)) {
            $matches_simulated++;

            // Get the match result
            $stmt2 = $db->prepare('SELECT home_score, away_score FROM league_matches WHERE id = :id');
            $stmt2->bindValue(':id', $row['id'], SQLITE3_INTEGER);
            $result2 = $stmt2->execute();
            $match_result = $result2->fetchArray(SQLITE3_ASSOC);

            $match_data = [
                'home_team' => $row['home_team'],
                'away_team' => $row['away_team'],
                'home_score' => $match_result['home_score'],
                'away_score' => $match_result['away_score']
            ];

            // Check if this is the user's match
            if ($user_team_id && ($row['home_team_id'] == $user_team_id || $row['away_team_id'] == $user_team_id)) {
                $user_match_result = $match_data;
                $user_match_result['is_home'] = ($row['home_team_id'] == $user_team_id);
            }

            $all_results[] = $match_data;
        }
    }

    // Get user's current position in the league
    $user_position = null;
    if ($user_team_id) {
        $standings = getLeagueStandings($db, $season);
        foreach ($standings as $index => $team) {
            if ($team['user_id'] == $user_id) {
                $user_position = $index + 1;
                break;
            }
        }
    }

    // Calculate budget earned if user had a match
    $budget_earned = 0;
    if ($user_team_id && $user_match_result) {
        // Calculate budget earned from the match
        $user_score = $user_match_result['is_home'] ? $user_match_result['home_score'] : $user_match_result['away_score'];
        $opponent_score = $user_match_result['is_home'] ? $user_match_result['away_score'] : $user_match_result['home_score'];

        // Base reward
        if ($user_score > $opponent_score) {
            $budget_earned += 5000000; // €5M for win
        } elseif ($user_score == $opponent_score) {
            $budget_earned += 2000000; // €2M for draw
        } else {
            $budget_earned += 1000000; // €1M for participation
        }

        // Goal bonus
        $budget_earned += $user_score * 500000; // €500K per goal

        // Home bonus
        if ($user_match_result['is_home']) {
            $budget_earned += 1000000; // €1M home bonus
        }
    }

    return [
        'matches_simulated' => $matches_simulated,
        'gameweek' => $gameweek,
        'user_match' => $user_match_result,
        'all_results' => $all_results,
        'user_position' => $user_position,
        'budget_earned' => $budget_earned
    ];
}

function hasUserMatchInGameweek($db, $user_id, $season, $gameweek)
{
    // Get user's team ID
    $stmt = $db->prepare('SELECT id FROM league_teams WHERE season = :season AND user_id = :user_id');
    $stmt->bindValue(':season', $season, SQLITE3_INTEGER);
    $stmt->bindValue(':user_id', $user_id, SQLITE3_INTEGER);
    $result = $stmt->execute();
    $user_team = $result->fetchArray(SQLITE3_ASSOC);

    if (!$user_team)
        return false;

    $team_id = $user_team['id'];

    // Check if user has a match in this gameweek
    $stmt = $db->prepare('SELECT COUNT(*) as count FROM league_matches 
                         WHERE season = :season AND gameweek = :gameweek 
                         AND (home_team_id = :team_id OR away_team_id = :team_id)
                         AND status = "scheduled"');
    $stmt->bindValue(':season', $season, SQLITE3_INTEGER);
    $stmt->bindValue(':gameweek', $gameweek, SQLITE3_INTEGER);
    $stmt->bindValue(':team_id', $team_id, SQLITE3_INTEGER);
    $result = $stmt->execute();
    $row = $result->fetchArray(SQLITE3_ASSOC);

    return $row['count'] > 0;
}

function validateClubForLeague($user)
{
    $validation_errors = [];

    // Check if user has a club name
    if (empty($user['club_name'])) {
        $validation_errors[] = 'Club name is required to participate in the league';
    }

    // Check minimum budget requirement (€10M)
    $min_budget = 10000000;
    if ($user['budget'] < $min_budget) {
        $validation_errors[] = 'Minimum budget of ' . formatMarketValue($min_budget) . ' required to continue in the league';
    }

    // Check team composition
    $team = json_decode($user['team'], true);
    if (!is_array($team)) {
        $validation_errors[] = 'Invalid team data';
    } else {
        // Count players by position
        $position_counts = [];
        $total_players = 0;

        foreach ($team as $player) {
            if ($player && isset($player['position'])) {
                $position = $player['position'];
                $position_counts[$position] = ($position_counts[$position] ?? 0) + 1;
                $total_players++;
            }
        }

        // Check minimum players (at least 11)
        if ($total_players < 11) {
            $validation_errors[] = 'Minimum 11 players required in your squad (currently have ' . $total_players . ')';
        }

        // Check required positions
        $required_positions = [
            'GK' => ['min' => 1, 'name' => 'Goalkeeper'],
            'CB' => ['min' => 2, 'name' => 'Centre Back'],
            'LB' => ['min' => 1, 'name' => 'Left Back'],
            'RB' => ['min' => 1, 'name' => 'Right Back']
        ];

        foreach ($required_positions as $pos => $req) {
            $count = $position_counts[$pos] ?? 0;
            if ($count < $req['min']) {
                $validation_errors[] = 'Minimum ' . $req['min'] . ' ' . $req['name'] . '(s) required (currently have ' . $count . ')';
            }
        }

        // Check for at least 4 midfielders (any type)
        $midfielder_positions = ['CDM', 'CM', 'CAM', 'LM', 'RM'];
        $midfielder_count = 0;
        foreach ($midfielder_positions as $pos) {
            $midfielder_count += $position_counts[$pos] ?? 0;
        }
        if ($midfielder_count < 4) {
            $validation_errors[] = 'Minimum 4 midfielders required (currently have ' . $midfielder_count . ')';
        }

        // Check for at least 2 forwards
        $forward_positions = ['ST', 'CF', 'LW', 'RW'];
        $forward_count = 0;
        foreach ($forward_positions as $pos) {
            $forward_count += $position_counts[$pos] ?? 0;
        }
        if ($forward_count < 2) {
            $validation_errors[] = 'Minimum 2 forwards required (currently have ' . $forward_count . ')';
        }
    }

    // Check formation validity
    $formation = $user['formation'] ?? '4-4-2';
    $valid_formations = getFormationsList();
    if (!in_array($formation, $valid_formations)) {
        $validation_errors[] = 'Invalid formation selected: ' . $formation;
    }

    return [
        'is_valid' => empty($validation_errors),
        'errors' => $validation_errors
    ];
}

function calculateTeamStrength($team, $is_home = false)
{
    // Base strength calculation
    $strength = 50; // Base strength

    // Form factor (based on recent performance)
    if ($team['matches_played'] > 0) {
        $form = ($team['points'] / ($team['matches_played'] * 3)) * 100;
        $strength += ($form - 33.33) * 0.5; // Adjust based on form
    }

    // Home advantage
    if ($is_home) {
        $strength += 5;
    }

    // Add some randomness
    $strength += rand(-10, 10);

    return max(20, min(80, $strength)); // Keep between 20-80
}

function generateScore($strength)
{
    // Convert strength to goal probability
    $goal_probability = $strength / 100;

    // Generate goals using Poisson-like distribution
    $goals = 0;
    $base_chance = $goal_probability * 2; // Average 2 goals for 100% strength

    for ($i = 0; $i < 6; $i++) { // Max 6 goals
        if (rand(0, 100) / 100 < $base_chance) {
            $goals++;
            $base_chance *= 0.7; // Reduce chance for additional goals
        }
    }

    return $goals;
}

function updateTeamStats($db, $team_id, $goals_for, $goals_against, $is_home)
{
    // Determine result
    $wins = $goals_for > $goals_against ? 1 : 0;
    $draws = $goals_for == $goals_against ? 1 : 0;
    $losses = $goals_for < $goals_against ? 1 : 0;
    $points = $wins * 3 + $draws;

    // Update team statistics
    $stmt = $db->prepare('UPDATE league_teams SET 
        matches_played = matches_played + 1,
        wins = wins + :wins,
        draws = draws + :draws,
        losses = losses + :losses,
        goals_for = goals_for + :goals_for,
        goals_against = goals_against + :goals_against,
        points = points + :points
        WHERE id = :id');

    $stmt->bindValue(':wins', $wins, SQLITE3_INTEGER);
    $stmt->bindValue(':draws', $draws, SQLITE3_INTEGER);
    $stmt->bindValue(':losses', $losses, SQLITE3_INTEGER);
    $stmt->bindValue(':goals_for', $goals_for, SQLITE3_INTEGER);
    $stmt->bindValue(':goals_against', $goals_against, SQLITE3_INTEGER);
    $stmt->bindValue(':points', $points, SQLITE3_INTEGER);
    $stmt->bindValue(':id', $team_id, SQLITE3_INTEGER);
    $stmt->execute();

    // Update user budget if this is a user team
    $stmt = $db->prepare('SELECT user_id FROM league_teams WHERE id = :id AND user_id IS NOT NULL');
    $stmt->bindValue(':id', $team_id, SQLITE3_INTEGER);
    $result = $stmt->execute();
    $user_team = $result->fetchArray(SQLITE3_ASSOC);

    if ($user_team) {
        // Calculate budget reward based on match result
        $budget_reward = 0;
        if ($wins) {
            $budget_reward = 5000000; // €5M for win
        } elseif ($draws) {
            $budget_reward = 2000000; // €2M for draw
        } else {
            $budget_reward = 1000000; // €1M for participation (loss)
        }

        // Add bonus for goals scored
        $goal_bonus = $goals_for * 500000; // €500K per goal

        // Add home advantage bonus
        $home_bonus = $is_home ? 1000000 : 0; // €1M home bonus

        $total_reward = $budget_reward + $goal_bonus + $home_bonus;

        // Update user budget
        $stmt = $db->prepare('UPDATE users SET budget = budget + :reward WHERE id = :user_id');
        $stmt->bindValue(':reward', $total_reward, SQLITE3_INTEGER);
        $stmt->bindValue(':user_id', $user_team['user_id'], SQLITE3_INTEGER);
        $stmt->execute();

        // Update player conditions (fitness and form) after match
        updatePlayerConditions($db, $user_team['user_id'], $wins, $draws, $losses, $goals_for, $goals_against);
    }
}

function updatePlayerConditions($db, $user_id, $wins, $draws, $losses, $goals_for, $goals_against)
{
    // Get user's team and substitutes
    $stmt = $db->prepare('SELECT team, substitutes FROM users WHERE id = :user_id');
    $stmt->bindValue(':user_id', $user_id, SQLITE3_INTEGER);
    $result = $stmt->execute();
    $user_data = $result->fetchArray(SQLITE3_ASSOC);

    if (!$user_data)
        return;

    $team = json_decode($user_data['team'], true);
    $substitutes = json_decode($user_data['substitutes'], true);

    // Determine match performance
    $performance = 'average';
    if ($wins && $goals_for >= 3) {
        $performance = 'excellent';
    } elseif ($wins) {
        $performance = 'good';
    } elseif ($losses && $goals_against >= 3) {
        $performance = 'poor';
    }

    $team_updated = false;
    $subs_updated = false;

    // Update main team players (they played the match)
    if (is_array($team)) {
        for ($i = 0; $i < count($team); $i++) {
            if ($team[$i]) {
                // Players who played lose fitness but can gain/lose form
                $team[$i] = updatePlayerFitness($team[$i], true, 0);
                $team[$i] = updatePlayerForm($team[$i], $performance);
                $team[$i]['matches_played'] = ($team[$i]['matches_played'] ?? 0) + 1;
                $team[$i]['last_match_date'] = date('Y-m-d');

                // Decrease contract matches remaining
                if (!isset($team[$i]['contract_matches_remaining'])) {
                    $team[$i]['contract_matches_remaining'] = $team[$i]['contract_matches'] ?? rand(15, 50);
                }
                $team[$i]['contract_matches_remaining'] = max(0, $team[$i]['contract_matches_remaining'] - 1);

                // Award experience points based on match performance
                $base_experience = 10; // Base experience for playing
                $performance_bonus = 0;

                switch ($performance) {
                    case 'excellent':
                        $performance_bonus = 15;
                        break;
                    case 'good':
                        $performance_bonus = 10;
                        break;
                    case 'average':
                        $performance_bonus = 5;
                        break;
                    case 'poor':
                        $performance_bonus = 0;
                        break;
                }

                // Win/draw bonus
                $result_bonus = 0;
                if ($wins > 0) {
                    $result_bonus = 5; // Win bonus
                } elseif ($draws > 0) {
                    $result_bonus = 2; // Draw bonus
                }

                $total_experience = $base_experience + $performance_bonus + $result_bonus;
                $team[$i] = addPlayerExperience($team[$i], $total_experience);
                $team_updated = true;
            }
        }
    }

    // Update substitute players (they rested, so fitness improves)
    if (is_array($substitutes)) {
        for ($i = 0; $i < count($substitutes); $i++) {
            if ($substitutes[$i]) {
                // Calculate days since last match for recovery
                $last_match = $substitutes[$i]['last_match_date'] ?? null;
                $days_since = $last_match ? (strtotime(date('Y-m-d')) - strtotime($last_match)) / 86400 : 7;

                $substitutes[$i] = updatePlayerFitness($substitutes[$i], false, $days_since);
                // Form slowly declines when not playing
                if (rand(1, 3) === 1) { // 33% chance
                    $substitutes[$i]['form'] = max(1, ($substitutes[$i]['form'] ?? 7) - 0.1);
                }
                $subs_updated = true;
            }
        }
    }

    // Update database if players were modified
    if ($team_updated || $subs_updated) {
        $stmt = $db->prepare('UPDATE users SET team = :team, substitutes = :substitutes WHERE id = :user_id');
        $stmt->bindValue(':team', json_encode($team), SQLITE3_TEXT);
        $stmt->bindValue(':substitutes', json_encode($substitutes), SQLITE3_TEXT);
        $stmt->bindValue(':user_id', $user_id, SQLITE3_INTEGER);
        $stmt->execute();
    }
}
?>