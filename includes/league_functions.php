<?php
// League system functions

// League club names are now defined in config/constants.php
require_once __DIR__ . '/utility_functions.php';

function initializeLeague($db, $user_uuid)
{
    // Create league tables if they don't exist
    createLeagueTables($db);

    // Check if league is already initialized for current season
    $current_season = getCurrentSeasonIdentifier($db);
    $stmt = $db->prepare('SELECT COUNT(*) as count FROM league_teams WHERE season = :season');
    $stmt->bindValue(':season', $current_season, SQLITE3_TEXT);
    $result = $stmt->execute();
    $row = $result->fetchArray(SQLITE3_ASSOC);

    if ($row['count'] == 0) {
        // Initialize new season
        createLeagueTeams($db, $user_uuid, $current_season);
        generateFixtures($db, $current_season);
    }
}

function createLeagueTables($db)
{
    if (defined('DB_DRIVER') && DB_DRIVER === 'mysql') {
        $db->exec('CREATE TABLE IF NOT EXISTS league_teams (
            id INT AUTO_INCREMENT PRIMARY KEY,
            season VARCHAR(10) NOT NULL,
            user_uuid VARCHAR(36) NULL,
            name VARCHAR(255) NOT NULL,
            is_user TINYINT(1) DEFAULT 0,
            division INT DEFAULT 1,
            matches_played INT DEFAULT 0,
            wins INT DEFAULT 0,
            draws INT DEFAULT 0,
            losses INT DEFAULT 0,
            goals_for INT DEFAULT 0,
            goals_against INT DEFAULT 0,
            points INT DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )');
        $db->exec('CREATE INDEX IF NOT EXISTS idx_league_teams_season ON league_teams (season)');
        $db->exec('CREATE INDEX IF NOT EXISTS idx_league_teams_user ON league_teams (user_uuid)');
        $db->exec('CREATE TABLE IF NOT EXISTS league_team_rosters (
            id INT AUTO_INCREMENT PRIMARY KEY,
            league_team_id INT NOT NULL,
            season VARCHAR(10) NOT NULL,
            player_data TEXT NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (league_team_id) REFERENCES league_teams(id)
        )');
        $db->exec('CREATE INDEX IF NOT EXISTS idx_league_team_rosters_team ON league_team_rosters (league_team_id)');
        $db->exec('CREATE INDEX IF NOT EXISTS idx_league_team_rosters_season ON league_team_rosters (season)');
        $db->exec('CREATE TABLE IF NOT EXISTS league_matches (
            id INT AUTO_INCREMENT PRIMARY KEY,
            season VARCHAR(10) NOT NULL,
            gameweek INT NOT NULL,
            home_team_id INT NOT NULL,
            away_team_id INT NOT NULL,
            home_score INT NULL,
            away_score INT NULL,
            status VARCHAR(20) DEFAULT "scheduled",
            match_date DATE NOT NULL,
            uuid CHAR(16) NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (home_team_id) REFERENCES league_teams(id),
            FOREIGN KEY (away_team_id) REFERENCES league_teams(id)
        )');
        $db->exec('CREATE INDEX IF NOT EXISTS idx_league_matches_season_week ON league_matches (season, gameweek)');
        $db->exec('CREATE INDEX IF NOT EXISTS idx_league_matches_home ON league_matches (home_team_id)');
        $db->exec('CREATE INDEX IF NOT EXISTS idx_league_matches_away ON league_matches (away_team_id)');
        $db->exec('CREATE INDEX IF NOT EXISTS idx_league_matches_uuid ON league_matches (uuid)');
    } else {
        $sql = 'CREATE TABLE IF NOT EXISTS league_teams (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            season TEXT NOT NULL,
            user_uuid TEXT,
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
        )';
        $db->exec($sql);
        $db->exec('CREATE INDEX IF NOT EXISTS idx_league_teams_season ON league_teams (season)');
        $db->exec('CREATE INDEX IF NOT EXISTS idx_league_teams_user ON league_teams (user_uuid)');
        $sql = 'CREATE TABLE IF NOT EXISTS league_team_rosters (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            league_team_id INTEGER NOT NULL,
            season TEXT NOT NULL,
            player_data TEXT NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (league_team_id) REFERENCES league_teams(id)
        )';
        $db->exec($sql);
        $db->exec('CREATE INDEX IF NOT EXISTS idx_league_team_rosters_team ON league_team_rosters (league_team_id)');
        $db->exec('CREATE INDEX IF NOT EXISTS idx_league_team_rosters_season ON league_team_rosters (season)');
        $sql = 'CREATE TABLE IF NOT EXISTS league_matches (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            season TEXT NOT NULL,
            gameweek INTEGER NOT NULL,
            home_team_id INTEGER NOT NULL,
            away_team_id INTEGER NOT NULL,
            home_score INTEGER,
            away_score INTEGER,
            status TEXT DEFAULT "scheduled",
            match_date DATE NOT NULL,
            uuid TEXT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (home_team_id) REFERENCES league_teams(id),
            FOREIGN KEY (away_team_id) REFERENCES league_teams(id)
        )';
        $db->exec($sql);
        // Ensure indexes (MySQL-safe for uuid; others kept as-is)
        try {
            $stmtIdx = $db->prepare('SELECT COUNT(*) as c FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = :t AND index_name = :i');
            if ($stmtIdx) {
                $stmtIdx->bindValue(':t', 'league_matches', SQLITE3_TEXT);
                $stmtIdx->bindValue(':i', 'idx_league_matches_season_week', SQLITE3_TEXT);
                $resIdx = $stmtIdx->execute();
                $rowIdx = $resIdx ? $resIdx->fetchArray(SQLITE3_ASSOC) : ['c' => 0];
                if ((int)($rowIdx['c'] ?? 0) === 0) {
                    $db->exec('CREATE INDEX idx_league_matches_season_week ON league_matches (season, gameweek)');
                }
                $stmtIdx->bindValue(':i', 'idx_league_matches_home', SQLITE3_TEXT);
                $resIdx = $stmtIdx->execute();
                $rowIdx = $resIdx ? $resIdx->fetchArray(SQLITE3_ASSOC) : ['c' => 0];
                if ((int)($rowIdx['c'] ?? 0) === 0) {
                    $db->exec('CREATE INDEX idx_league_matches_home ON league_matches (home_team_id)');
                }
                $stmtIdx->bindValue(':i', 'idx_league_matches_away', SQLITE3_TEXT);
                $resIdx = $stmtIdx->execute();
                $rowIdx = $resIdx ? $resIdx->fetchArray(SQLITE3_ASSOC) : ['c' => 0];
                if ((int)($rowIdx['c'] ?? 0) === 0) {
                    $db->exec('CREATE INDEX idx_league_matches_away ON league_matches (away_team_id)');
                }
            } else {
                // SQLite fallback
                $db->exec('CREATE INDEX IF NOT EXISTS idx_league_matches_season_week ON league_matches (season, gameweek)');
                $db->exec('CREATE INDEX IF NOT EXISTS idx_league_matches_home ON league_matches (home_team_id)');
                $db->exec('CREATE INDEX IF NOT EXISTS idx_league_matches_away ON league_matches (away_team_id)');
            }
        } catch (Throwable $e) {}
        // Ensure uuid column
        try {
            $stmtCol = $db->prepare('SELECT COUNT(*) as c FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = :t AND column_name = :c');
            if ($stmtCol) {
                $stmtCol->bindValue(':t', 'league_matches', SQLITE3_TEXT);
                $stmtCol->bindValue(':c', 'uuid', SQLITE3_TEXT);
                $resCol = $stmtCol->execute();
                $rowCol = $resCol ? $resCol->fetchArray(SQLITE3_ASSOC) : ['c' => 0];
                if ((int)($rowCol['c'] ?? 0) === 0) {
                    $db->exec('ALTER TABLE league_matches ADD COLUMN uuid CHAR(16) NULL');
                }
            } else {
                // SQLite fallback
                $res = $db->query("PRAGMA table_info(league_matches)");
                $hasUuid = false;
                while ($r = $res->fetchArray(SQLITE3_ASSOC)) {
                    if (($r['name'] ?? '') === 'uuid') {
                        $hasUuid = true;
                        break;
                    }
                }
                if (!$hasUuid) {
                    $db->exec('ALTER TABLE league_matches ADD COLUMN uuid TEXT NULL');
                }
            }
        } catch (Throwable $e) {}
        // Ensure uuid index
        try {
            $stmtIdx = $db->prepare('SELECT COUNT(*) as c FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = :t AND index_name = :i');
            if ($stmtIdx) {
                $stmtIdx->bindValue(':t', 'league_matches', SQLITE3_TEXT);
                $stmtIdx->bindValue(':i', 'idx_league_matches_uuid', SQLITE3_TEXT);
                $resIdx = $stmtIdx->execute();
                $rowIdx = $resIdx ? $resIdx->fetchArray(SQLITE3_ASSOC) : ['c' => 0];
                if ((int)($rowIdx['c'] ?? 0) === 0) {
                    $db->exec('CREATE INDEX idx_league_matches_uuid ON league_matches (uuid)');
                }
            } else {
                // SQLite fallback
                $db->exec('CREATE INDEX IF NOT EXISTS idx_league_matches_uuid ON league_matches (uuid)');
            }
        } catch (Throwable $e) {}
        // Backfill missing uuids
        try {
            $stmt = $db->prepare('SELECT id, uuid FROM league_matches WHERE uuid IS NULL OR uuid = ""');
            if ($stmt) {
                $res = $stmt->execute();
                while ($row = $res->fetchArray(SQLITE3_ASSOC)) {
                    $u = generateUUID();
                    $up = $db->prepare('UPDATE league_matches SET uuid = :uuid WHERE id = :id');
                    if ($up) {
                        $up->bindValue(':uuid', $u, SQLITE3_TEXT);
                        $up->bindValue(':id', (int)$row['id'], SQLITE3_INTEGER);
                        $up->execute();
                    }
                }
            }
        } catch (Throwable $e) {}
    }
}

function createLeagueTeams($db, $user_uuid, $season)
{
    // Resolve numeric user_id from uuid
    $stmt = $db->prepare('SELECT id FROM users WHERE uuid = :uuid');
    $stmt->bindValue(':uuid', $user_uuid, SQLITE3_TEXT);
    $result = $stmt->execute();
    $user_row = $result->fetchArray(SQLITE3_ASSOC);
    $user_id = $user_row ? (int)$user_row['id'] : null;

    // Get user's club info from user_club
    $stmt = $db->prepare('SELECT club_name FROM user_club WHERE user_uuid = :uuid');
    $stmt->bindValue(':uuid', $user_uuid, SQLITE3_TEXT);
    $result = $stmt->execute();
    $club = $result->fetchArray(SQLITE3_ASSOC);

    // Insert user's team in Elite League (Division 1)
    $stmt = $db->prepare('INSERT INTO league_teams (season, user_uuid, name, is_user, division) VALUES (:season, :user_uuid, :name, 1, 1)');
    $stmt->bindValue(':season', $season, SQLITE3_TEXT);
    $stmt->bindValue(':user_uuid', $user_uuid, SQLITE3_TEXT);
    $stmt->bindValue(':name', $club['club_name'] ?? 'Your Club', SQLITE3_TEXT);
    $stmt->execute();
    
    // Get the user's team ID and assign roster
    $user_team_id = $db->lastInsertRowid();
    assignTeamRoster($db, $user_team_id, $season, $user_id);

    // Insert Elite League fake teams (Division 1) - 19 teams
    foreach (FAKE_CLUBS as $club_name) {
        $stmt = $db->prepare('INSERT INTO league_teams (season, name, is_user, division) VALUES (:season, :name, 0, 1)');
        $stmt->bindValue(':season', $season, SQLITE3_TEXT);
        $stmt->bindValue(':name', $club_name, SQLITE3_TEXT);
        $stmt->execute();
        
        // Get the team ID and assign roster
        $team_id = $db->lastInsertRowid();
        assignTeamRoster($db, $team_id, $season, null);
    }

    // Insert Pro League fake teams (Division 2) - 20 teams
    foreach (CHAMPIONSHIP_CLUBS as $club_name) {
        $stmt = $db->prepare('INSERT INTO league_teams (season, name, is_user, division) VALUES (:season, :name, 0, 2)');
        $stmt->bindValue(':season', $season, SQLITE3_TEXT);
        $stmt->bindValue(':name', $club_name, SQLITE3_TEXT);
        $stmt->execute();
        
        // Get the team ID and assign roster
        $team_id = $db->lastInsertRowid();
        assignTeamRoster($db, $team_id, $season, null);
    }
}

/**
 * Assign 23 random players to a league team
 */
function assignTeamRoster($db, $league_team_id, $season, $user_id = null)
{
    // Get all available players from the system
    $all_players = getDefaultPlayers();
    
    if (empty($all_players)) {
        return false;
    }
    
    // Shuffle and select 23 random players
    shuffle($all_players);
    $selected_players = array_slice($all_players, 0, 23);
    
    // Organize players by their main position
    $players_by_position = [
        'GK' => [],
        'DEF' => [],
        'MID' => [],
        'FWD' => []
    ];
    
    foreach ($selected_players as $player) {
        $pos = $player['position'] ?? 'MID';
        
        // Map specific positions to general categories
        if (in_array($pos, ['LB', 'RB', 'CB'])) {
            $players_by_position['DEF'][] = $player;
        } elseif (in_array($pos, ['CAM', 'CM', 'CDM', 'LM', 'RM'])) {
            $players_by_position['MID'][] = $player;
        } elseif (in_array($pos, ['ST', 'CF', 'LW', 'RW'])) {
            $players_by_position['FWD'][] = $player;
        } else {
            $players_by_position['MID'][] = $player;
        }
    }
    
    // Build roster respecting player positions
    // Formation: 1 GK, 4 DEF, 4 MID, 2 FWD (starting 11) + 12 substitutes
    $roster = [];
    
    // Add starting XI (11 players)
    // 1 GK
    for ($i = 0; $i < 1 && !empty($players_by_position['GK']); $i++) {
        $player = array_pop($players_by_position['GK']);
        $player['rating'] = rand(70, 95);
        $player['fitness'] = 100;
        $player['form'] = rand(5, 10);
        $roster[] = $player;
    }
    
    // 4 DEF
    for ($i = 0; $i < 4 && !empty($players_by_position['DEF']); $i++) {
        $player = array_pop($players_by_position['DEF']);
        $player['rating'] = rand(70, 95);
        $player['fitness'] = 100;
        $player['form'] = rand(5, 10);
        $roster[] = $player;
    }
    
    // 4 MID
    for ($i = 0; $i < 4 && !empty($players_by_position['MID']); $i++) {
        $player = array_pop($players_by_position['MID']);
        $player['rating'] = rand(70, 95);
        $player['fitness'] = 100;
        $player['form'] = rand(5, 10);
        $roster[] = $player;
    }
    
    // 2 FWD
    for ($i = 0; $i < 2 && !empty($players_by_position['FWD']); $i++) {
        $player = array_pop($players_by_position['FWD']);
        $player['rating'] = rand(70, 95);
        $player['fitness'] = 100;
        $player['form'] = rand(5, 10);
        $roster[] = $player;
    }
    
    // Add substitutes (12 players) - fill remaining slots
    $remaining_players = [];
    foreach ($players_by_position as $position_players) {
        $remaining_players = array_merge($remaining_players, $position_players);
    }
    
    for ($i = 0; $i < 12 && !empty($remaining_players); $i++) {
        $player = array_pop($remaining_players);
        $player['rating'] = rand(70, 95);
        $player['fitness'] = 100;
        $player['form'] = rand(5, 10);
        $roster[] = $player;
    }
    
    // Store roster in database
    $stmt = $db->prepare('INSERT INTO league_team_rosters (league_team_id, season, player_data) VALUES (:league_team_id, :season, :player_data)');
    $stmt->bindValue(':league_team_id', $league_team_id, SQLITE3_INTEGER);
    $stmt->bindValue(':season', $season, SQLITE3_TEXT);
    $stmt->bindValue(':player_data', json_encode($roster), SQLITE3_TEXT);
    $result = $stmt->execute();
    
    return $result ? true : false;
}

function generateFixtures($db, $season)
{
    // Get all Elite League teams for the season (Division 1 only)
    $stmt = $db->prepare('SELECT id FROM league_teams WHERE season = :season AND division = 1 ORDER BY id');
    $stmt->bindValue(':season', $season, SQLITE3_TEXT);
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
            $stmt->bindValue(':season', $season, SQLITE3_TEXT);
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
            $stmt->bindValue(':season', $season, SQLITE3_TEXT);
            $stmt->bindValue(':gameweek', $gameweek, SQLITE3_INTEGER);
            $stmt->bindValue(':home', $fixture[0], SQLITE3_INTEGER);
            $stmt->bindValue(':away', $fixture[1], SQLITE3_INTEGER);
            $stmt->bindValue(':date', $match_date, SQLITE3_TEXT);
            $stmt->execute();
        }

        $gameweek++;
    }

    // Ensure all inserted matches have UUIDs
    try {
        $stmt = $db->prepare('SELECT id, uuid FROM league_matches WHERE season = :season AND (uuid IS NULL OR uuid = "")');
        if ($stmt) {
            $stmt->bindValue(':season', $season, SQLITE3_TEXT);
            $res = $stmt->execute();
            while ($row = $res->fetchArray(SQLITE3_ASSOC)) {
                $u = generateUUID();
                $up = $db->prepare('UPDATE league_matches SET uuid = :uuid WHERE id = :id');
                if ($up) {
                    $up->bindValue(':uuid', $u, SQLITE3_TEXT);
                    $up->bindValue(':id', (int)$row['id'], SQLITE3_INTEGER);
                    $up->execute();
                }
            }
        }
    } catch (Throwable $e) {}
}

function getCurrentSeason($db)
{
    return date('Y');
}

function getNextSeasonIdentifier($db)
{
    // Get the current year
    $current_year = date('Y');
    
    // Get the highest season number for this year
    $stmt = $db->prepare('SELECT MAX(season) as max_season FROM league_teams WHERE season LIKE :year_pattern');
    $stmt->bindValue(':year_pattern', $current_year . '/%', SQLITE3_TEXT);
    $result = $stmt->execute();
    $row = $result->fetchArray(SQLITE3_ASSOC);
    
    if ($row['max_season']) {
        // Extract the season number and increment
        $parts = explode('/', $row['max_season']);
        $season_num = intval($parts[1]) + 1;
    } else {
        // First season of the year
        $season_num = 1;
    }
    
    // Format: 2026/01, 2026/02, etc.
    return sprintf('%d/%02d', $current_year, $season_num);
}

function getCurrentSeasonIdentifier($db)
{
    // Get the most recent season identifier
    $current_year = date('Y');
    
    $stmt = $db->prepare('SELECT DISTINCT season FROM league_teams WHERE season LIKE :year_pattern ORDER BY season DESC LIMIT 1');
    $stmt->bindValue(':year_pattern', $current_year . '/%', SQLITE3_TEXT);
    $result = $stmt->execute();
    $row = $result->fetchArray(SQLITE3_ASSOC);
    
    return $row['season'] ?? getNextSeasonIdentifier($db);
}

function getCurrentGameweek($db, $season)
{
    $stmt = $db->prepare('SELECT MIN(gameweek) as gameweek FROM league_matches WHERE season = :season AND status = \'scheduled\'');
    $stmt->bindValue(':season', $season, SQLITE3_TEXT);
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
    WHERE lt.season = :season AND lt.division = 1
    ORDER BY points DESC, goal_difference DESC, lt.goals_for DESC, lt.is_user DESC, lt.name ASC';

    $stmt = $db->prepare($sql);
    $stmt->bindValue(':season', $season, SQLITE3_TEXT);
    $result = $stmt->execute();

    $standings = [];
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $standings[] = $row;
    }

    return $standings;
}

function getUserMatches($db, $user_id, $season)
{
    // Resolve user_uuid from numeric id
    $stmt = $db->prepare('SELECT uuid FROM users WHERE id = :id');
    $stmt->bindValue(':id', $user_id, SQLITE3_INTEGER);
    $resUuid = $stmt->execute();
    $rowUuid = $resUuid ? $resUuid->fetchArray(SQLITE3_ASSOC) : null;
    $user_uuid = $rowUuid['uuid'] ?? null;

    // Get user's team ID
    $stmt = $db->prepare('SELECT id FROM league_teams WHERE season = :season AND user_uuid = :user_uuid');
    if ($stmt === false) {
        $stmt = $db->prepare('SELECT id FROM league_teams WHERE season = :season AND user_id = :user_id');
        $stmt->bindValue(':season', $season, SQLITE3_TEXT);
        $stmt->bindValue(':user_id', $user_id, SQLITE3_INTEGER);
    } else {
        $stmt->bindValue(':season', $season, SQLITE3_TEXT);
        $stmt->bindValue(':user_uuid', $user_uuid, SQLITE3_TEXT);
    }
    $result = $stmt->execute();
    $user_team = $result->fetchArray(SQLITE3_ASSOC);

    if (!$user_team)
        return [];

    $team_id = $user_team['id'];

    $sql = 'SELECT 
        lm.*,
        CASE 
            WHEN lm.home_team_id = :team_id THEN at.name 
            ELSE ht.name 
        END as opponent,
        CASE 
            WHEN lm.home_team_id = :team_id THEN \'H\' 
            ELSE \'A\' 
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
            WHEN lm.status != \'completed\' THEN NULL
            WHEN (lm.home_team_id = :team_id AND lm.home_score > lm.away_score) OR 
                 (lm.away_team_id = :team_id AND lm.away_score > lm.home_score) THEN \'W\'
            WHEN lm.home_score = lm.away_score THEN \'D\'
            ELSE \'L\'
        END as result
    FROM league_matches lm
    JOIN league_teams ht ON lm.home_team_id = ht.id
    JOIN league_teams at ON lm.away_team_id = at.id
    WHERE lm.season = :season 
    AND (lm.home_team_id = :team_id OR lm.away_team_id = :team_id)
    AND lm.status = \'completed\'
    ORDER BY lm.gameweek DESC, lm.match_date DESC';

    $stmt = $db->prepare($sql);
    $stmt->bindValue(':season', $season, SQLITE3_TEXT);
    $stmt->bindValue(':team_id', $team_id, SQLITE3_INTEGER);
    $result = $stmt->execute();

    $matches = [];
    if ($result === false) {
        return $matches;
    }
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $matches[] = $row;
    }

    return $matches;
}

function getUpcomingMatches($db, $user_id, $season)
{
    // Resolve user_uuid from numeric id
    $stmt = $db->prepare('SELECT uuid FROM users WHERE id = :id');
    $stmt->bindValue(':id', $user_id, SQLITE3_INTEGER);
    $resUuid = $stmt->execute();
    $rowUuid = $resUuid ? $resUuid->fetchArray(SQLITE3_ASSOC) : null;
    $user_uuid = $rowUuid['uuid'] ?? null;

    // Get user's team ID
    $stmt = $db->prepare('SELECT id FROM league_teams WHERE season = :season AND user_uuid = :user_uuid');
    if ($stmt === false) {
        $stmt = $db->prepare('SELECT id FROM league_teams WHERE season = :season AND user_id = :user_id');
        $stmt->bindValue(':season', $season, SQLITE3_TEXT);
        $stmt->bindValue(':user_id', $user_id, SQLITE3_INTEGER);
    } else {
        $stmt->bindValue(':season', $season, SQLITE3_TEXT);
        $stmt->bindValue(':user_uuid', $user_uuid, SQLITE3_TEXT);
    }
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
        hu.id as home_team_id,
        au.id as away_team_id
    FROM league_matches lm
    JOIN league_teams ht ON lm.home_team_id = ht.id
    JOIN league_teams at ON lm.away_team_id = at.id
    LEFT JOIN users hu ON hu.uuid = ht.user_uuid
    LEFT JOIN users au ON au.uuid = at.user_uuid
    WHERE lm.season = :season 
    AND lm.gameweek >= :current_gameweek
    AND lm.gameweek <= :max_gameweek
    ORDER BY lm.gameweek ASC, lm.match_date ASC';

    $stmt = $db->prepare($sql);
    if ($stmt === false) {
        return [];
    }
    $stmt->bindValue(':season', $season, SQLITE3_TEXT);
    $stmt->bindValue(':current_gameweek', $current_gameweek, SQLITE3_INTEGER);
    $stmt->bindValue(':max_gameweek', $current_gameweek + 5, SQLITE3_INTEGER); // Show next 5 gameweeks
    $result = $stmt->execute();

    $matches = [];
    if ($result === false) {
        return $matches;
    }
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
    // Resolve user_uuid from numeric id
    $stmt = $db->prepare('SELECT uuid FROM users WHERE id = :id');
    $stmt->bindValue(':id', $user_id, SQLITE3_INTEGER);
    $resUuid = $stmt->execute();
    $rowUuid = $resUuid ? $resUuid->fetchArray(SQLITE3_ASSOC) : null;
    $user_uuid = $rowUuid['uuid'] ?? null;

    // Get match details
    $stmt = $db->prepare('SELECT * FROM league_matches WHERE id = :id AND status = \'scheduled\'');
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
    $stmt = $db->prepare('UPDATE league_matches SET home_score = :home_score, away_score = :away_score, status = \'completed\' WHERE id = :id');
    $stmt->bindValue(':home_score', $home_score, SQLITE3_INTEGER);
    $stmt->bindValue(':away_score', $away_score, SQLITE3_INTEGER);
    $stmt->bindValue(':id', $match_id, SQLITE3_INTEGER);
    $stmt->execute();

    // Update team statistics
    updateTeamStats($db, $match['home_team_id'], $home_score, $away_score, true);
    updateTeamStats($db, $match['away_team_id'], $away_score, $home_score, false);

    // Apply rewards to user if they participated in this match
    $user_team_id = null;
    $is_user_home = false;
    
    // Check if user is home team
    $stmt = $db->prepare('SELECT user_uuid FROM league_teams WHERE id = :id AND user_uuid = :user_uuid');
    $stmt->bindValue(':id', $match['home_team_id'], SQLITE3_INTEGER);
    $stmt->bindValue(':user_uuid', $user_uuid, SQLITE3_TEXT);
    $result = $stmt->execute();
    if ($result->fetchArray(SQLITE3_ASSOC)) {
        $user_team_id = $match['home_team_id'];
        $is_user_home = true;
    } else {
        // Check if user is away team
        $stmt = $db->prepare('SELECT user_uuid FROM league_teams WHERE id = :id AND user_uuid = :user_uuid');
        $stmt->bindValue(':id', $match['away_team_id'], SQLITE3_INTEGER);
        $stmt->bindValue(':user_uuid', $user_uuid, SQLITE3_TEXT);
        $result = $stmt->execute();
        if ($result->fetchArray(SQLITE3_ASSOC)) {
            $user_team_id = $match['away_team_id'];
            $is_user_home = false;
        }
    }

    // If user participated, calculate and apply additional rewards
    if ($user_team_id) {
        $user_score = $is_user_home ? $home_score : $away_score;
        $opponent_score = $is_user_home ? $away_score : $home_score;
        
        // Determine match result for user
        $match_result = 'draw';
        if ($user_score > $opponent_score) {
            $match_result = 'win';
        } elseif ($user_score < $opponent_score) {
            $match_result = 'loss';
        }

        // Calculate additional rewards (beyond what updateTeamStats already applied)
        $rewards = calculateLeagueMatchRewards($match_result, $user_score, $opponent_score, $is_user_home);
        
        // Apply budget rewards
        $stmt = $db->prepare('UPDATE users SET budget = budget + :reward WHERE id = :user_id');
        $stmt->bindValue(':reward', $rewards['budget_earned'], SQLITE3_INTEGER);
        $stmt->bindValue(':user_id', $user_id, SQLITE3_INTEGER);
        $stmt->execute();
        
        // Apply fan changes
        $stmt = $db->prepare('SELECT fans FROM users WHERE id = :user_id');
        $stmt->bindValue(':user_id', $user_id, SQLITE3_INTEGER);
        $result = $stmt->execute();
        $user_data = $result->fetchArray(SQLITE3_ASSOC);
        
        $current_fans = $user_data['fans'] ?? 5000;
        $new_fans = max(1000, $current_fans + $rewards['fan_change']); // Minimum 1000 fans
        
        $stmt = $db->prepare('UPDATE users SET fans = :fans WHERE id = :user_id');
        $stmt->bindValue(':fans', $new_fans, SQLITE3_INTEGER);
        $stmt->bindValue(':user_id', $user_id, SQLITE3_INTEGER);
        $stmt->execute();
    }

    return true;
}

function getFanRevenueBreakdown($db, $user_id, $is_home, $total_revenue, $previous_fans = null)
{
    if ($total_revenue <= 0) {
        return [['description' => 'Fan Revenue', 'amount' => 0]];
    }

    // Get detailed fan revenue breakdown
    $stmt = $db->prepare('SELECT u.fans, s.capacity, s.level FROM users u LEFT JOIN stadiums s ON u.id = s.user_id WHERE u.id = :user_id');
    $stmt->bindValue(':user_id', $user_id, SQLITE3_INTEGER);
    $result = $stmt->execute();
    $user_data = $result->fetchArray(SQLITE3_ASSOC);

    // Use previous_fans if provided, otherwise use current fans from DB
    $current_fans = $previous_fans !== null ? $previous_fans : ($user_data['fans'] ?? 5000);
    $stadium_capacity = $user_data['capacity'] ?? 10000;
    $stadium_level = $user_data['level'] ?? 1;

    $breakdown = [];

    // 1. Merchandise/Media Revenue (All matches, based on total fans)
    $merch_revenue = $current_fans * 10;
    $breakdown[] = ['description' => "Fan Engagement ({$current_fans} fans)", 'amount' => $merch_revenue];

    // 2. Ticket Revenue (Home matches only, capped by capacity)
    if ($is_home) {
        $attendance = min($current_fans, $stadium_capacity);
        $ticket_price = 30; // Average ticket price
        $ticket_revenue = $attendance * $ticket_price;
        $breakdown[] = ['description' => "Ticket Sales ({$attendance} attendance)", 'amount' => $ticket_revenue];

        // 3. Stadium Facilities (Home matches only)
        $stadium_multipliers = [1 => 1.0, 2 => 1.2, 3 => 1.5, 4 => 1.8, 5 => 2.2];
        $stadium_multiplier = $stadium_multipliers[$stadium_level] ?? 1.0;
        $stadium_facility_revenue = 50000 * $stadium_multiplier;
        
        $breakdown[] = ['description' => "Stadium Facilities (Level {$stadium_level})", 'amount' => $stadium_facility_revenue];
    }

    return $breakdown;
}

function updateFansAfterMatch($db, $user_id, $user_score, $opponent_score, $is_home)
{
    // Get user's current fans and stadium info
    $stmt = $db->prepare('SELECT u.fans, s.capacity, s.level FROM users u LEFT JOIN stadiums s ON u.id = s.user_id WHERE u.id = :user_id');
    $stmt->bindValue(':user_id', $user_id, SQLITE3_INTEGER);
    $result = $stmt->execute();
    $user_data = $result->fetchArray(SQLITE3_ASSOC);

    $current_fans = $user_data['fans'] ?? 5000;
    $stadium_capacity = $user_data['capacity'] ?? 10000;
    $stadium_level = $user_data['level'] ?? 1;

    // Calculate additional revenue
    $additional_revenue = 0;

    // 1. Merchandise/Media Revenue (All matches)
    $merch_revenue = $current_fans * 10; // €10 per fan
    $additional_revenue += $merch_revenue;

    // 2. Ticket Revenue & Facilities (Home matches only)
    if ($is_home) {
        // Ticket Sales
        $attendance = min($current_fans, $stadium_capacity);
        $ticket_price = 30; // €30 per ticket
        $ticket_revenue = $attendance * $ticket_price;
        $additional_revenue += $ticket_revenue;

        // Stadium Facilities
        $stadium_multipliers = [1 => 1.0, 2 => 1.2, 3 => 1.5, 4 => 1.8, 5 => 2.2];
        $stadium_multiplier = $stadium_multipliers[$stadium_level] ?? 1.0;
        $stadium_revenue = 50000 * $stadium_multiplier; // Base stadium revenue with multiplier
        $additional_revenue += $stadium_revenue;
    }

    // Update fan count based on match result (randomly influenced)
    $fan_change = 0;
    if ($user_score > $opponent_score) {
        // Win: gain 50-200 fans
        $fan_change = rand(50, 200);
    } elseif ($user_score == $opponent_score) {
        // Draw: gain/lose 0-50 fans
        $fan_change = rand(-25, 50);
    } else {
        // Loss: lose 25-100 fans
        $fan_change = rand(-100, -25);
    }

    // Goal difference affects fan change
    $goal_diff = $user_score - $opponent_score;
    $fan_change += $goal_diff * 10; // +/- 10 fans per goal difference

    // Update fan count (minimum 1000 fans)
    $new_fans = max(1000, $current_fans + $fan_change);

    $stmt = $db->prepare('UPDATE users SET fans = :fans WHERE id = :user_id');
    $stmt->bindValue(':fans', $new_fans, SQLITE3_INTEGER);
    $stmt->bindValue(':user_id', $user_id, SQLITE3_INTEGER);
    $stmt->execute();

    return [
        'fan_change' => $fan_change,
        'new_fans' => $new_fans,
        'additional_revenue' => $additional_revenue,
        'previous_fans' => $current_fans
    ];
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

    // Resolve user_uuid from numeric id and get user's league team
    $stmt = $db->prepare('SELECT uuid FROM users WHERE id = :id');
    $stmt->bindValue(':id', $user_id, SQLITE3_INTEGER);
    $resUuid = $stmt->execute();
    $rowUuid = $resUuid ? $resUuid->fetchArray(SQLITE3_ASSOC) : null;
    $user_uuid = $rowUuid['uuid'] ?? null;

    // Get user's team ID for tracking their match
    $stmt = $db->prepare('SELECT id FROM league_teams WHERE season = :season AND user_uuid = :user_uuid');
    $stmt->bindValue(':season', $season, SQLITE3_TEXT);
    $stmt->bindValue(':user_uuid', $user_uuid, SQLITE3_TEXT);
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
        WHERE lm.season = :season AND lm.gameweek = :gameweek AND lm.status = \'scheduled\'');
    $stmt->bindValue(':season', $season, SQLITE3_TEXT);
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
    $budget_breakdown = [];
    $fan_change_info = null;

    if ($user_team_id && $user_match_result) {
        $standings = getLeagueStandings($db, $season);
        foreach ($standings as $index => $team) {
            if (($team['user_uuid'] ?? null) === $user_uuid) {
                $user_position = $index + 1;
                break;
            }
        }

        // Calculate budget earned from the match
        $user_score = $user_match_result['is_home'] ? $user_match_result['home_score'] : $user_match_result['away_score'];
        $opponent_score = $user_match_result['is_home'] ? $user_match_result['away_score'] : $user_match_result['home_score'];

        // Base reward
        if ($user_score > $opponent_score) {
            $base_reward = 800000;
            $budget_earned += $base_reward;
            $budget_breakdown[] = ['description' => 'Match Victory', 'amount' => $base_reward];
        } elseif ($user_score == $opponent_score) {
            $base_reward = 300000;
            $budget_earned += $base_reward;
            $budget_breakdown[] = ['description' => 'Match Draw', 'amount' => $base_reward];
        } else {
            $base_reward = 150000;
            $budget_earned += $base_reward;
            $budget_breakdown[] = ['description' => 'Match Participation', 'amount' => $base_reward];
        }

        // Goal bonus
        if ($user_score > 0) {
            $goal_bonus = $user_score * 100000;
            $budget_earned += $goal_bonus;
            $budget_breakdown[] = ['description' => "Goals Scored ({$user_score})", 'amount' => $goal_bonus];
        }

        // Home bonus
        if ($user_match_result['is_home']) {
            $home_bonus = 250000;
            $budget_earned += $home_bonus;
            $budget_breakdown[] = ['description' => 'Home Match Bonus', 'amount' => $home_bonus];
        }

        // Update fans and calculate additional revenue
        $fan_result = updateFansAfterMatch($db, $user_id, $user_score, $opponent_score, $user_match_result['is_home']);

        if ($fan_result['additional_revenue'] > 0) {
            $budget_earned += $fan_result['additional_revenue'];
            // Get detailed fan revenue breakdown
            $fan_breakdown = getFanRevenueBreakdown($db, $user_id, $user_match_result['is_home'], $fan_result['additional_revenue'], $fan_result['previous_fans']);
            foreach ($fan_breakdown as $item) {
                $budget_breakdown[] = $item;
            }
        }

        // Fan change information
        $fan_change_info = [
            'fan_change' => $fan_result['fan_change'],
            'new_fans' => $fan_result['new_fans']
        ];

        // Generate post-match player options if user had a match
        if ($user_match_result) {
            generatePostMatchPlayerOptions($db, $user_id);
        }
    }

    return [
        'matches_simulated' => $matches_simulated,
        'gameweek' => $gameweek,
        'user_match' => $user_match_result,
        'all_results' => $all_results,
        'user_position' => $user_position,
        'budget_earned' => $budget_earned,
        'budget_breakdown' => $budget_breakdown,
        'fan_change_info' => $fan_change_info
    ];
}

function simulateCurrentGameweek($db, $user_id, $season, $gameweek)
{
    // Resolve user_uuid from numeric id and get user's league team
    $stmt = $db->prepare('SELECT uuid FROM users WHERE id = :id');
    $stmt->bindValue(':id', $user_id, SQLITE3_INTEGER);
    $resUuid = $stmt->execute();
    $rowUuid = $resUuid ? $resUuid->fetchArray(SQLITE3_ASSOC) : null;
    $user_uuid = $rowUuid['uuid'] ?? null;

    // Get user's team ID for tracking their match
    $stmt = $db->prepare('SELECT id FROM league_teams WHERE season = :season AND user_uuid = :user_uuid');
    $stmt->bindValue(':season', $season, SQLITE3_TEXT);
    $stmt->bindValue(':user_uuid', $user_uuid, SQLITE3_TEXT);
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
        WHERE lm.season = :season AND lm.gameweek = :gameweek AND lm.status = \'scheduled\'');
    $stmt->bindValue(':season', $season, SQLITE3_TEXT);
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
            if (($team['user_uuid'] ?? null) === $user_uuid) {
                $user_position = $index + 1;
                break;
            }
        }
    }

    // Calculate budget earned if user had a match
    $budget_earned = 0;
    $budget_breakdown = [];
    $fan_change_info = null;

    if ($user_team_id && $user_match_result) {
        // Calculate budget earned from the match
        $user_score = $user_match_result['is_home'] ? $user_match_result['home_score'] : $user_match_result['away_score'];
        $opponent_score = $user_match_result['is_home'] ? $user_match_result['away_score'] : $user_match_result['home_score'];

        // Base reward
        if ($user_score > $opponent_score) {
            $base_reward = 800000;
            $budget_earned += $base_reward;
            $budget_breakdown[] = ['description' => 'Match Victory', 'amount' => $base_reward];
        } elseif ($user_score == $opponent_score) {
            $base_reward = 300000;
            $budget_earned += $base_reward;
            $budget_breakdown[] = ['description' => 'Match Draw', 'amount' => $base_reward];
        } else {
            $base_reward = 150000;
            $budget_earned += $base_reward;
            $budget_breakdown[] = ['description' => 'Match Participation', 'amount' => $base_reward];
        }

        // Goal bonus
        if ($user_score > 0) {
            $goal_bonus = $user_score * 100000;
            $budget_earned += $goal_bonus;
            $budget_breakdown[] = ['description' => "Goals Scored ({$user_score})", 'amount' => $goal_bonus];
        }

        // Home bonus
        if ($user_match_result['is_home']) {
            $home_bonus = 250000;
            $budget_earned += $home_bonus;
            $budget_breakdown[] = ['description' => 'Home Match Bonus', 'amount' => $home_bonus];
        }

        // Update fans and calculate additional revenue
        $fan_result = updateFansAfterMatch($db, $user_id, $user_score, $opponent_score, $user_match_result['is_home']);

        if ($fan_result['additional_revenue'] > 0) {
            $budget_earned += $fan_result['additional_revenue'];
            // Get detailed fan revenue breakdown
            $fan_breakdown = getFanRevenueBreakdown($db, $user_id, $user_match_result['is_home'], $fan_result['additional_revenue'], $fan_result['previous_fans']);
            foreach ($fan_breakdown as $item) {
                $budget_breakdown[] = $item;
            }
        }

        // Fan change information
        $fan_change_info = [
            'fan_change' => $fan_result['fan_change'],
            'new_fans' => $fan_result['new_fans']
        ];
    }

    return [
        'matches_simulated' => $matches_simulated,
        'gameweek' => $gameweek,
        'user_match' => $user_match_result,
        'all_results' => $all_results,
        'user_position' => $user_position,
        'budget_earned' => $budget_earned,
        'budget_breakdown' => $budget_breakdown,
        'fan_change_info' => $fan_change_info
    ];
}

function hasUserMatchInGameweek($db, $user_id, $season, $gameweek)
{
    // Get user's team ID
    // Resolve user_uuid from numeric id
    $stmt = $db->prepare('SELECT uuid FROM users WHERE id = :id');
    $stmt->bindValue(':id', $user_id, SQLITE3_INTEGER);
    $resUuid = $stmt->execute();
    $rowUuid = $resUuid ? $resUuid->fetchArray(SQLITE3_ASSOC) : null;
    $user_uuid = $rowUuid['uuid'] ?? null;

    $stmt = $db->prepare('SELECT id FROM league_teams WHERE season = :season AND user_uuid = :user_uuid');
    $stmt->bindValue(':season', $season, SQLITE3_TEXT);
    $stmt->bindValue(':user_uuid', $user_uuid, SQLITE3_TEXT);
    $result = $stmt->execute();
    $user_team = $result->fetchArray(SQLITE3_ASSOC);

    if (!$user_team)
        return false;

    $team_id = $user_team['id'];

    // Check if user has a match in this gameweek
    $stmt = $db->prepare('SELECT COUNT(*) as count FROM league_matches 
                         WHERE season = :season AND gameweek = :gameweek 
                         AND (home_team_id = :team_id OR away_team_id = :team_id)
                         AND status = \'scheduled\'');
    $stmt->bindValue(':season', $season, SQLITE3_TEXT);
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
        $team = array_filter(array_map(function ($item) {
            return !empty($item);
        }, $team));
        $total_players = count($team);

        // Check minimum players (at least 11)
        if ($total_players < 11) {
            $validation_errors[] = 'Minimum 11 players required in your squad (currently have ' . $total_players . ')';
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
    $stmt = $db->prepare('SELECT user_uuid FROM league_teams WHERE id = :id AND user_uuid IS NOT NULL');
    $stmt->bindValue(':id', $team_id, SQLITE3_INTEGER);
    $result = $stmt->execute();
    $user_team = $result->fetchArray(SQLITE3_ASSOC);

    if ($user_team) {
        // Update player conditions (fitness and form) after match
        // Resolve numeric id from uuid then call update
        $stmtU = $db->prepare('SELECT id FROM users WHERE uuid = :uuid');
        $stmtU->bindValue(':uuid', $user_team['user_uuid'], SQLITE3_TEXT);
        $resU = $stmtU->execute();
        $rowU = $resU ? $resU->fetchArray(SQLITE3_ASSOC) : null;
        if ($rowU && isset($rowU['id'])) {
            updatePlayerConditions($db, (int)$rowU['id'], $wins, $draws, $losses, $goals_for, $goals_against);
        }
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

    // Update user's matches played counter and check for nation calls
    $stmt = $db->prepare('UPDATE users SET matches_played = matches_played + 1 WHERE id = :user_id');
    $stmt->bindValue(':user_id', $user_id, SQLITE3_INTEGER);
    $stmt->execute();

    // Process nation calls if conditions are met
    $nationCallResult = processNationCalls($db, $user_id);
    if ($nationCallResult['success']) {
        // Store nation call notification in session for display
        $_SESSION['nation_call_notification'] = $nationCallResult;
    }

    // Update player statistics
    $matchResult = 'draw'; // Default
    if ($wins > 0) {
        $matchResult = 'win';
    } elseif ($losses > 0) {
        $matchResult = 'loss';
    }

    // Update statistics for main team players (they played the match)
    if (is_array($team) && !empty($team)) {
        $playingPlayers = array_filter($team);
        updatePlayerStatistics($db, $user_id, $playingPlayers, $matchResult, $goals_for, $goals_against);
    }
}
/**
 * Generate 3 random players (young or standard) for post-match selection
 */
function getPostMatchPlayerOptions()
{
    $all_players = getDefaultPlayers();

    // Filter for young and standard players only
    $eligible_players = array_filter($all_players, function ($player) {
        return in_array($player['category'], ['young', 'standard']);
    });

    if (count($eligible_players) < 3) {
        return []; // Not enough players
    }

    // Shuffle and get 3 random players
    $shuffled = $eligible_players;
    shuffle($shuffled);
    return array_slice($shuffled, 0, 3);
}

/**
 * Store post-match player options in session for user selection
 */
function generatePostMatchPlayerOptions($db, $user_id)
{
    $player_options = getPostMatchPlayerOptions();
    if (empty($player_options)) {
        return false;
    }

    // Store in session with timestamp
    $_SESSION['post_match_players'] = [
        'options' => $player_options,
        'user_id' => $user_id,
        'generated_at' => time(),
        'expires_at' => time() + (24 * 60 * 60) // 24 hours
    ];

    return true;
}

/**
 * Process user's selection of post-match player
 */
function selectPostMatchPlayer($db, $user_id, $selected_index)
{
    if (!isset($_SESSION['post_match_players'])) {
        return ['success' => false, 'message' => 'No player options available'];
    }

    $post_match_data = $_SESSION['post_match_players'];

    // Validate session data
    if ($post_match_data['user_id'] != $user_id) {
        return ['success' => false, 'message' => 'Invalid user session'];
    }

    if (time() > $post_match_data['expires_at']) {
        unset($_SESSION['post_match_players']);
        return ['success' => false, 'message' => 'Player selection has expired'];
    }

    if (!isset($post_match_data['options'][$selected_index])) {
        return ['success' => false, 'message' => 'Invalid player selection'];
    }

    $selected_player = $post_match_data['options'][$selected_index];

    try {
        // Add player to user's inventory
        $stmt = $db->prepare('INSERT INTO player_inventory (user_id, player_uuid, player_data, purchase_price, purchase_date, status) VALUES (:user_id, :uuid, :data, 0, CURRENT_TIMESTAMP, "available")');
        $stmt->bindValue(':user_id', $user_id, SQLITE3_INTEGER);
        $stmt->bindValue(':uuid', $selected_player['uuid'], SQLITE3_TEXT);
        $stmt->bindValue(':data', json_encode($selected_player), SQLITE3_TEXT);
        $stmt->execute();

        // Clear the session data
        unset($_SESSION['post_match_players']);

        return [
            'success' => true,
            'message' => 'Player added to your squad successfully!',
            'player' => $selected_player
        ];

    } catch (Exception $e) {
        error_log("Post-match player selection error: " . $e->getMessage());
        return ['success' => false, 'message' => 'Failed to add player to squad'];
    }
}

/**
 * Get top scorers across all teams in the league
 */
function getTopScorers($db, $season, $limit = 3)
{
    $limit = (int)$limit;
    $sql = 'SELECT 
        ps.player_name,
        ps.goals,
        ps.user_id,
        u.club_name
    FROM player_stats ps
    JOIN users u ON ps.user_id = u.id
    JOIN league_teams lt ON u.uuid = lt.user_uuid AND lt.season = :season
    WHERE ps.goals > 0
    ORDER BY ps.goals DESC, ps.assists DESC, ps.player_name ASC
    LIMIT ' . $limit;

    $stmt = $db->prepare($sql);
    if ($stmt === false) {
        return [];
    }
    $stmt->bindValue(':season', $season, SQLITE3_TEXT);
    $result = $stmt->execute();

    $scorers = [];
    if ($result === false) {
        return $scorers;
    }
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $scorers[] = $row;
    }

    return $scorers;
}

/**
 * Get top assist providers across all teams in the league
 */
function getTopAssists($db, $season, $limit = 3)
{
    $limit = (int)$limit;
    $sql = 'SELECT 
        ps.player_name,
        ps.assists,
        ps.user_id,
        u.club_name
    FROM player_stats ps
    JOIN users u ON ps.user_id = u.id
    JOIN league_teams lt ON u.uuid = lt.user_uuid AND lt.season = :season
    WHERE ps.assists > 0
    ORDER BY ps.assists DESC, ps.goals DESC, ps.player_name ASC
    LIMIT ' . $limit;

    $stmt = $db->prepare($sql);
    if ($stmt === false) {
        return [];
    }
    $stmt->bindValue(':season', $season, SQLITE3_TEXT);
    $result = $stmt->execute();

    $assisters = [];
    if ($result === false) {
        return $assisters;
    }
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $assisters[] = $row;
    }

    return $assisters;
}

/**
 * Get top rated players across all teams in the league
 */
function getTopRatedPlayers($db, $season, $limit = 3)
{
    $limit = (int)$limit;
    $sql = 'SELECT 
        ps.player_name,
        ps.position,
        ps.matches_played,
        ps.user_id,
        CASE WHEN ps.matches_played > 0 THEN ROUND(CAST(ps.total_rating AS REAL) / ps.matches_played, 1) ELSE 0 END as avg_rating,
        u.club_name
    FROM player_stats ps
    JOIN users u ON ps.user_id = u.id
    JOIN league_teams lt ON u.uuid = lt.user_uuid AND lt.season = :season
    WHERE ps.matches_played >= 3
    ORDER BY avg_rating DESC, ps.matches_played DESC, ps.player_name ASC
    LIMIT ' . $limit;

    $stmt = $db->prepare($sql);
    if ($stmt === false) {
        return [];
    }
    $stmt->bindValue(':season', $season, SQLITE3_TEXT);
    $result = $stmt->execute();

    $players = [];
    if ($result === false) {
        return $players;
    }
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $players[] = $row;
    }

    return $players;
}

/**
 * Get players with most yellow cards
 */
function getMostYellowCards($db, $season, $limit = 3)
{
    $limit = (int)$limit;
    $sql = 'SELECT 
        ps.player_name,
        ps.yellow_cards,
        ps.user_id,
        u.club_name
    FROM player_stats ps
    JOIN users u ON ps.user_id = u.id
    JOIN league_teams lt ON u.uuid = lt.user_uuid AND lt.season = :season
    WHERE ps.yellow_cards > 0
    ORDER BY ps.yellow_cards DESC, ps.red_cards DESC, ps.player_name ASC
    LIMIT ' . $limit;

    $stmt = $db->prepare($sql);
    if ($stmt === false) {
        return [];
    }
    $stmt->bindValue(':season', $season, SQLITE3_TEXT);
    $result = $stmt->execute();

    $players = [];
    if ($result === false) {
        return $players;
    }
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $players[] = $row;
    }

    return $players;
}

/**
 * Get players with most red cards
 */
function getMostRedCards($db, $season, $limit = 3)
{
    $limit = (int)$limit;
    $sql = 'SELECT 
        ps.player_name,
        ps.red_cards,
        ps.user_id,
        u.club_name
    FROM player_stats ps
    JOIN users u ON ps.user_id = u.id
    JOIN league_teams lt ON u.uuid = lt.user_uuid AND lt.season = :season
    WHERE ps.red_cards > 0
    ORDER BY ps.red_cards DESC, ps.yellow_cards DESC, ps.player_name ASC
    LIMIT ' . $limit;

    $stmt = $db->prepare($sql);
    if ($stmt === false) {
        return [];
    }
    $stmt->bindValue(':season', $season, SQLITE3_TEXT);
    $result = $stmt->execute();

    $players = [];
    if ($result === false) {
        return $players;
    }
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $players[] = $row;
    }

    return $players;
}

/**
 * Get top goalkeepers based on clean sheets and saves
 */
function getTopGoalkeepers($db, $season, $limit = 3)
{
    $limit = (int)$limit;
    $sql = 'SELECT 
        ps.player_name,
        ps.clean_sheets,
        ps.saves,
        ps.matches_played,
        ps.user_id,
        u.club_name
    FROM player_stats ps
    JOIN users u ON ps.user_id = u.id
    JOIN league_teams lt ON u.uuid = lt.user_uuid AND lt.season = :season
    WHERE ps.position = \'GK\' AND ps.matches_played > 0
    ORDER BY ps.clean_sheets DESC, ps.saves DESC, ps.matches_played DESC, ps.player_name ASC
    LIMIT ' . $limit;

    $stmt = $db->prepare($sql);
    if ($stmt === false) {
        return [];
    }
    $stmt->bindValue(':season', $season, SQLITE3_TEXT);
    $result = $stmt->execute();

    $goalkeepers = [];
    if ($result === false) {
        return $goalkeepers;
    }
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $goalkeepers[] = $row;
    }

    return $goalkeepers;
}
/**
 * Check if the season is complete (all matches played)
 */
function isSeasonComplete($db, $season)
{
    $stmt = $db->prepare('SELECT COUNT(*) as total_matches, 
                         SUM(CASE WHEN status = \'completed\' THEN 1 ELSE 0 END) as completed_matches
                         FROM league_matches 
                         WHERE season = :season');
    $stmt->bindValue(':season', $season, SQLITE3_TEXT);
    $result = $stmt->execute();
    $row = $result->fetchArray(SQLITE3_ASSOC);

    return $row['total_matches'] > 0 && $row['total_matches'] == $row['completed_matches'];
}

/**
 * Get Championship (Division 2) standings
 */
function getChampionshipStandings($db, $season)
{
    $sql = 'SELECT 
        lt.*,
        (lt.wins * 3 + lt.draws) as points,
        (lt.goals_for - lt.goals_against) as goal_difference
    FROM league_teams lt 
    WHERE lt.season = :season AND lt.division = 2
    ORDER BY points DESC, goal_difference DESC, lt.goals_for DESC, lt.name ASC';

    $stmt = $db->prepare($sql);
    $stmt->bindValue(':season', $season, SQLITE3_TEXT);
    $result = $stmt->execute();

    $standings = [];
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $standings[] = $row;
    }

    return $standings;
}

/**
 * Process relegation and promotion at the end of the season
 */
function processRelegationPromotion($db, $season)
{
    // Check if season is complete
    if (!isSeasonComplete($db, $season)) {
        return ['success' => false, 'message' => 'Season is not yet complete'];
    }

    // Check if relegation has already been processed for this season
    $stmt = $db->prepare('SELECT COUNT(*) as count FROM league_teams WHERE season = :next_season');
    $stmt->bindValue(':next_season', $season + 1, SQLITE3_TEXT);
    $result = $stmt->execute();
    $row = $result->fetchArray(SQLITE3_ASSOC);

    if ($row['count'] > 0) {
        return ['success' => false, 'message' => 'Relegation already processed for this season'];
    }

    // Get final Elite League standings
    $premier_standings = getLeagueStandings($db, $season);

    // Get final Pro League standings
    $championship_standings = getChampionshipStandings($db, $season);

    if (count($premier_standings) < 20 || count($championship_standings) < 3) {
        return ['success' => false, 'message' => 'Invalid league structure for relegation'];
    }

    // Teams to be relegated (bottom 3 from Elite League)
    $relegated_teams = array_slice($premier_standings, -3);

    // Teams to be promoted (top 3 from Championship)
    $promoted_teams = array_slice($championship_standings, 0, 3);

    // Get season end summary and calculate rewards
    $season_summary = getSeasonEndSummary($db, $season, null); // Get general summary first

    // Check if user's team is relegated
    $user_relegated = false;
    $user_uuid = null;
    foreach ($relegated_teams as $team) {
        if ($team['is_user']) {
            $user_relegated = true;
            $user_uuid = $team['user_uuid'] ?? null;
            break;
        }
    }

    // If user not relegated, find user in staying teams
    if (!$user_relegated) {
        $staying_teams = array_slice($premier_standings, 0, 17);
        foreach ($staying_teams as $team) {
            if ($team['is_user']) {
                $user_uuid = $team['user_uuid'] ?? null;
                break;
            }
        }
    }

    try {
        $db->exec('BEGIN TRANSACTION');

        // Apply season end rewards to user if found
        if ($user_uuid) {
            // Resolve numeric id from uuid for rewards/reference
            $stmtU = $db->prepare('SELECT id FROM users WHERE uuid = :uuid');
            $stmtU->bindValue(':uuid', $user_uuid, SQLITE3_TEXT);
            $resU = $stmtU->execute();
            $rowU = $resU ? $resU->fetchArray(SQLITE3_ASSOC) : null;
            $resolved_id = $rowU['id'] ?? null;
            if ($resolved_id) {
                $user_season_summary = getSeasonEndSummary($db, $season, (int)$resolved_id);
                applySeasonEndRewards($db, (int)$resolved_id, $user_season_summary['user_rewards']);
                $season_summary = $user_season_summary; // Use user-specific summary
            }
        }

        // Create teams for next season
        $next_season = $season + 1;

        // 1. Keep top 17 Elite League teams in Division 1
        $staying_premier_teams = array_slice($premier_standings, 0, 17);
        foreach ($staying_premier_teams as $team) {
            $stmt = $db->prepare('INSERT INTO league_teams (season, user_uuid, name, is_user, division) VALUES (:season, :user_uuid, :name, :is_user, 1)');
            $stmt->bindValue(':season', $next_season, SQLITE3_TEXT);
            $stmt->bindValue(':user_uuid', $team['user_uuid'] ?? null, SQLITE3_TEXT);
            $stmt->bindValue(':name', $team['name'], SQLITE3_TEXT);
            $stmt->bindValue(':is_user', $team['is_user'], SQLITE3_INTEGER);
            $stmt->execute();
        }

        // 2. Promote top 3 Championship teams to Division 1
        foreach ($promoted_teams as $team) {
            $stmt = $db->prepare('INSERT INTO league_teams (season, user_uuid, name, is_user, division) VALUES (:season, :user_uuid, :name, :is_user, 1)');
            $stmt->bindValue(':season', $next_season, SQLITE3_TEXT);
            $stmt->bindValue(':user_uuid', $team['user_uuid'] ?? null, SQLITE3_TEXT);
            $stmt->bindValue(':name', $team['name'], SQLITE3_TEXT);
            $stmt->bindValue(':is_user', $team['is_user'], SQLITE3_INTEGER);
            $stmt->execute();
        }

        // 3. Handle relegated teams
        if ($user_relegated) {
            // If user is relegated, put them in Championship (Division 2)
            foreach ($relegated_teams as $team) {
                if ($team['is_user']) {
                    $stmt = $db->prepare('INSERT INTO league_teams (season, user_uuid, name, is_user, division) VALUES (:season, :user_uuid, :name, :is_user, 2)');
                    $stmt->bindValue(':season', $next_season, SQLITE3_TEXT);
                    $stmt->bindValue(':user_uuid', $team['user_uuid'] ?? null, SQLITE3_TEXT);
                    $stmt->bindValue(':name', $team['name'], SQLITE3_TEXT);
                    $stmt->bindValue(':is_user', $team['is_user'], SQLITE3_INTEGER);
                    $stmt->execute();
                    break;
                }
            }

            // Fill remaining Championship spots with remaining teams and new teams
            $remaining_championship = array_slice($championship_standings, 3); // Teams 4-24 from Championship
            foreach ($remaining_championship as $team) {
                $stmt = $db->prepare('INSERT INTO league_teams (season, user_uuid, name, is_user, division) VALUES (:season, :user_uuid, :name, :is_user, 2)');
                $stmt->bindValue(':season', $next_season, SQLITE3_TEXT);
                $stmt->bindValue(':user_uuid', $team['user_uuid'] ?? null, SQLITE3_TEXT);
                $stmt->bindValue(':name', $team['name'], SQLITE3_TEXT);
                $stmt->bindValue(':is_user', $team['is_user'], SQLITE3_INTEGER);
                $stmt->execute();
            }

            // Add relegated Elite League teams (non-user) to Pro League
            foreach ($relegated_teams as $team) {
                if (!$team['is_user']) {
                    $stmt = $db->prepare('INSERT INTO league_teams (season, user_uuid, name, is_user, division) VALUES (:season, :user_uuid, :name, :is_user, 2)');
                    $stmt->bindValue(':season', $next_season, SQLITE3_TEXT);
                    $stmt->bindValue(':user_uuid', $team['user_uuid'] ?? null, SQLITE3_TEXT);
                    $stmt->bindValue(':name', $team['name'], SQLITE3_TEXT);
                    $stmt->bindValue(':is_user', $team['is_user'], SQLITE3_INTEGER);
                    $stmt->execute();
                }
            }
        } else {
            // User stays in Elite League, create new Pro League with remaining teams
            $remaining_championship = array_slice($championship_standings, 3); // Teams 4-24 from Championship
            foreach ($remaining_championship as $team) {
                $stmt = $db->prepare('INSERT INTO league_teams (season, user_uuid, name, is_user, division) VALUES (:season, :user_uuid, :name, :is_user, 2)');
                $stmt->bindValue(':season', $next_season, SQLITE3_TEXT);
                $stmt->bindValue(':user_uuid', $team['user_uuid'] ?? null, SQLITE3_TEXT);
                $stmt->bindValue(':name', $team['name'], SQLITE3_TEXT);
                $stmt->bindValue(':is_user', $team['is_user'], SQLITE3_INTEGER);
                $stmt->execute();
            }

            // Add relegated Elite League teams to Pro League
            foreach ($relegated_teams as $team) {
                $stmt = $db->prepare('INSERT INTO league_teams (season, user_uuid, name, is_user, division) VALUES (:season, :user_uuid, :name, :is_user, 2)');
                $stmt->bindValue(':season', $next_season, SQLITE3_TEXT);
                $stmt->bindValue(':user_uuid', $team['user_uuid'] ?? null, SQLITE3_TEXT);
                $stmt->bindValue(':name', $team['name'], SQLITE3_TEXT);
                $stmt->bindValue(':is_user', $team['is_user'], SQLITE3_INTEGER);
                $stmt->execute();
            }
        }

        // Generate fixtures for next season (Elite League only)
        generateFixtures($db, $next_season);

        $db->exec('COMMIT');

        return [
            'success' => true,
            'message' => 'Relegation and promotion processed successfully',
            'relegated_teams' => array_map(function ($team) {
                return $team['name'];
            }, $relegated_teams),
            'promoted_teams' => array_map(function ($team) {
                return $team['name'];
            }, $promoted_teams),
            'user_relegated' => $user_relegated,
            'next_season' => $next_season,
            'season_summary' => $season_summary
        ];

    } catch (Exception $e) {
        $db->exec('ROLLBACK');
        error_log("Relegation processing error: " . $e->getMessage());
        return ['success' => false, 'message' => 'Failed to process relegation: ' . $e->getMessage()];
    }
}

/**
 * Check if relegation should be processed and show notification
 */
function checkSeasonEnd($db, $user_id)
{
    $current_season = getCurrentSeason($db);

    // Check if season is complete and relegation hasn't been processed
    if (isSeasonComplete($db, $current_season)) {
        // Check if next season already exists
        $stmt = $db->prepare('SELECT COUNT(*) as count FROM league_teams WHERE season = :next_season');
        $stmt->bindValue(':next_season', $current_season + 1, SQLITE3_TEXT);
        $result = $stmt->execute();
        $row = $result->fetchArray(SQLITE3_ASSOC);

        if ($row['count'] == 0) {
            // Season is complete but relegation not processed
            return [
                'season_complete' => true,
                'relegation_pending' => true,
                'current_season' => $current_season
            ];
        }
    }

    return [
        'season_complete' => false,
        'relegation_pending' => false,
        'current_season' => $current_season
    ];
}
/**
 * Calculate season end rewards based on league position
 */
function calculateSeasonEndRewards($position, $division = 1)
{
    $rewards = [];

    if ($division == 1) { // Elite League
        // Base prize money for Elite League participation
        $base_prize = 50000000; // €50M base

        // Position-based prize money (higher for better positions)
        $position_prize = (21 - $position) * 2000000; // €2M per position from bottom

        // Special bonuses
        if ($position == 1) {
            $rewards[] = ['type' => 'prize', 'description' => 'Elite League Champions', 'amount' => 100000000];
            $rewards[] = ['type' => 'prize', 'description' => 'Champions League Qualification', 'amount' => 25000000];
        } elseif ($position <= 4) {
            $rewards[] = ['type' => 'prize', 'description' => 'Champions League Qualification', 'amount' => 25000000];
        } elseif ($position <= 6) {
            $rewards[] = ['type' => 'prize', 'description' => 'Europa League Qualification', 'amount' => 15000000];
        }

        // Relegation compensation (if relegated)
        if ($position >= 18) {
            $rewards[] = ['type' => 'compensation', 'description' => 'Relegation Compensation', 'amount' => 20000000];
        }

        $rewards[] = ['type' => 'prize', 'description' => 'Elite League Participation', 'amount' => $base_prize];
        $rewards[] = ['type' => 'prize', 'description' => "Final Position: {$position}th Place", 'amount' => $position_prize];

    } else { // Championship
        // Championship rewards (smaller amounts)
        $base_prize = 10000000; // €10M base
        $position_prize = (25 - $position) * 500000; // €500K per position from bottom

        if ($position == 1) {
            $rewards[] = ['type' => 'prize', 'description' => 'Pro League Winners', 'amount' => 20000000];
            $rewards[] = ['type' => 'prize', 'description' => 'Elite League Promotion', 'amount' => 30000000];
        } elseif ($position <= 3) {
            $rewards[] = ['type' => 'prize', 'description' => 'Elite League Promotion', 'amount' => 30000000];
        }

        $rewards[] = ['type' => 'prize', 'description' => 'Championship Participation', 'amount' => $base_prize];
        $rewards[] = ['type' => 'prize', 'description' => "Final Position: {$position}th Place", 'amount' => $position_prize];
    }

    return $rewards;
}

/**
 * Get season end summary with final standings and rewards
 */
function getSeasonEndSummary($db, $season, $user_id)
{
    // Get final Elite League standings
    $premier_standings = getLeagueStandings($db, $season);

    // Get final Pro League standings  
    $championship_standings = getChampionshipStandings($db, $season);

    // Find user's position and division
    $user_position = null;
    $user_division = null;
    $user_team_data = null;

    // Resolve user_uuid from numeric id
    $stmtU = $db->prepare('SELECT uuid FROM users WHERE id = :id');
    $stmtU->bindValue(':id', $user_id, SQLITE3_INTEGER);
    $resU = $stmtU->execute();
    $rowU = $resU ? $resU->fetchArray(SQLITE3_ASSOC) : null;
    $user_uuid = $rowU['uuid'] ?? null;

    // Check Elite League first
    foreach ($premier_standings as $index => $team) {
        if (($team['user_uuid'] ?? null) === $user_uuid) {
            $user_position = $index + 1;
            $user_division = 1;
            $user_team_data = $team;
            break;
        }
    }

    // If not found in Elite League, check Pro League
    if ($user_position === null) {
        foreach ($championship_standings as $index => $team) {
            if (($team['user_uuid'] ?? null) === $user_uuid) {
                $user_position = $index + 1;
                $user_division = 2;
                $user_team_data = $team;
                break;
            }
        }
    }

    // Calculate user's rewards
    $user_rewards = [];
    $total_reward = 0;

    if ($user_position !== null) {
        $user_rewards = calculateSeasonEndRewards($user_position, $user_division);
        $total_reward = array_sum(array_column($user_rewards, 'amount'));
    }

    // Get season statistics
    $season_stats = [
        'top_scorer' => getTopScorers($db, $season, 1)[0] ?? null,
        'top_assists' => getTopAssists($db, $season, 1)[0] ?? null,
        'best_player' => getTopRatedPlayers($db, $season, 1)[0] ?? null,
    ];

    return [
        'season' => $season,
        'premier_standings' => $premier_standings,
        'championship_standings' => $championship_standings,
        'user_position' => $user_position,
        'user_division' => $user_division,
        'user_team_data' => $user_team_data,
        'user_rewards' => $user_rewards,
        'total_reward' => $total_reward,
        'season_stats' => $season_stats,
        'relegated_teams' => array_slice($premier_standings, -3),
        'promoted_teams' => array_slice($championship_standings, 0, 3)
    ];
}

/**
 * Apply season end rewards to user's budget
 */
function applySeasonEndRewards($db, $user_id, $rewards)
{
    $total_amount = array_sum(array_column($rewards, 'amount'));

    if ($total_amount > 0) {
        $stmt = $db->prepare('UPDATE users SET budget = budget + :amount WHERE id = :user_id');
        $stmt->bindValue(':amount', $total_amount, SQLITE3_INTEGER);
        $stmt->bindValue(':user_id', $user_id, SQLITE3_INTEGER);
        $stmt->execute();

        return true;
    }

    return false;
}

/**
 * Calculate rewards for league match results
 */
function calculateLeagueMatchRewards($match_result, $user_score, $opponent_score, $is_home) {
    $rewards = [];
    $total_budget = 0;

    // Base reward based on match result
    if ($match_result === 'win') {
        $base_reward = 800000;
        $rewards[] = ['description' => 'Match Victory', 'amount' => $base_reward];
        $total_budget += $base_reward;
    } elseif ($match_result === 'draw') {
        $base_reward = 300000;
        $rewards[] = ['description' => 'Match Draw', 'amount' => $base_reward];
        $total_budget += $base_reward;
    } else {
        $base_reward = 150000;
        $rewards[] = ['description' => 'Match Participation', 'amount' => $base_reward];
        $total_budget += $base_reward;
    }

    // Goal bonus
    if ($user_score > 0) {
        $goal_bonus = $user_score * 100000;
        $rewards[] = ['description' => "Goals Scored ({$user_score})", 'amount' => $goal_bonus];
        $total_budget += $goal_bonus;
    }

    // Home bonus
    if ($is_home) {
        $home_bonus = 250000;
        $rewards[] = ['description' => 'Home Match Bonus', 'amount' => $home_bonus];
        $total_budget += $home_bonus;
    }

    return [
        'budget_earned' => $total_budget,
        'breakdown' => $rewards
    ];
}
