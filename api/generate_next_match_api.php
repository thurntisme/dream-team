<?php
session_start();
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../includes/league_functions.php';
require_once __DIR__ . '/../includes/utility_functions.php';

header('Content-Type: application/json');

$resp = ['ok' => false];

try {
    $db = getDbConnection();
    $user_uuid = $_SESSION['user_uuid'] ?? null;
    if (!$user_uuid) {
        echo json_encode(['ok' => false, 'error' => 'not_authenticated']);
        exit;
    }

    // Ensure league tables exist
    try {
        createLeagueTables($db);
    } catch (Throwable $e) {
    }

    $season = getCurrentSeasonIdentifier($db);

    $stmtTeam = $db->prepare('SELECT id FROM league_teams WHERE season = :season AND user_uuid = :uuid');
    if ($stmtTeam === false) {
        echo json_encode(['ok' => false, 'error' => 'prepare_failed_team', 'message' => $db->lastErrorMsg()]);
        exit;
    }
    $stmtTeam->bindValue(':season', $season);
    $stmtTeam->bindValue(':uuid', $user_uuid);
    $resTeam = $stmtTeam->execute();
    $rowTeam = $resTeam ? $resTeam->fetchArray(SQLITE3_ASSOC) : null;
    if (!$rowTeam) {
        // Attempt to initialize league for the user
        try {
            initializeLeague($db, $user_uuid);
        } catch (Throwable $e) {
        }
        // Re-check
        $stmtTeam = $db->prepare('SELECT id FROM league_teams WHERE season = :season AND user_uuid = :uuid');
        if ($stmtTeam === false) {
            echo json_encode(['ok' => false, 'error' => 'prepare_failed_team_retry', 'message' => $db->lastErrorMsg()]);
            exit;
        }
        $stmtTeam->bindValue(':season', $season);
        $stmtTeam->bindValue(':uuid', $user_uuid);
        $resTeam = $stmtTeam->execute();
        $rowTeam = $resTeam ? $resTeam->fetchArray(SQLITE3_ASSOC) : null;
        if (!$rowTeam) {
            echo json_encode(['ok' => false, 'error' => 'team_not_found']);
            exit;
        }
    }
    $team_id = (int)$rowTeam['id'];

    $stmtMatch = $db->prepare('
        SELECT lm.id
        FROM league_matches lm
        WHERE lm.season = :season
          AND lm.status = \'scheduled\'
          AND (lm.home_team_id = :team_id OR lm.away_team_id = :team_id)
        ORDER BY lm.gameweek ASC, lm.id ASC
        LIMIT 1
    ');
    if ($stmtMatch === false) {
        echo json_encode(['ok' => false, 'error' => 'prepare_failed_match', 'message' => $db->lastErrorMsg()]);
        exit;
    }
    $stmtMatch->bindValue(':season', $season);
    $stmtMatch->bindValue(':team_id', $team_id);
    $resMatch = $stmtMatch->execute();
    $rowMatch = $resMatch ? $resMatch->fetchArray(SQLITE3_ASSOC) : null;
    if (!$rowMatch) {
        // No scheduled match found - generate fixtures if needed
        try {
            generateFixtures($db, $season);
        } catch (Throwable $e) {
        }
        // Retry
        $stmtMatch = $db->prepare('
            SELECT lm.id, lm.uuid
            FROM league_matches lm
            WHERE lm.season = :season
            AND lm.status = \'scheduled\'
            AND (lm.home_team_id = :team_id OR lm.away_team_id = :team_id)
            ORDER BY lm.gameweek ASC, lm.id ASC
            LIMIT 1
        ');
        if ($stmtMatch === false) {
            echo json_encode(['ok' => false, 'error' => 'prepare_failed_match_retry', 'message' => $db->lastErrorMsg()]);
            exit;
        }
        $stmtMatch->bindValue(':season', $season);
        $stmtMatch->bindValue(':team_id', $team_id);
        $resMatch = $stmtMatch->execute();
        $rowMatch = $resMatch ? $resMatch->fetchArray(SQLITE3_ASSOC) : null;
        if (!$rowMatch) {
            $stmtCount = $db->prepare('SELECT COUNT(*) as c FROM league_matches WHERE season = :season AND (home_team_id = :team_id OR away_team_id = :team_id)');
            if ($stmtCount) {
                $stmtCount->bindValue(':season', $season);
                $stmtCount->bindValue(':team_id', $team_id);
                $resCount = $stmtCount->execute();
                $rowCount = $resCount ? $resCount->fetchArray(SQLITE3_ASSOC) : ['c' => 0];
                $hasAnyMatches = (int)($rowCount['c'] ?? 0) > 0;
                if (!$hasAnyMatches) {
                    try {
                        initializeLeague($db, $user_uuid);
                        generateFixtures($db, $season);
                    } catch (Throwable $e) {}
                    $stmtMatch = $db->prepare('
                        SELECT lm.id, lm.uuid
                        FROM league_matches lm
                        WHERE lm.season = :season
                          AND lm.status = \'scheduled\'
                          AND (lm.home_team_id = :team_id OR lm.away_team_id = :team_id)
                        ORDER BY lm.gameweek ASC, lm.id ASC
                        LIMIT 1
                    ');
                    if ($stmtMatch) {
                        $stmtMatch->bindValue(':season', $season);
                        $stmtMatch->bindValue(':team_id', $team_id);
                        $resMatch = $stmtMatch->execute();
                        $rowMatch = $resMatch ? $resMatch->fetchArray(SQLITE3_ASSOC) : null;
                    }
                }
                if (!$rowMatch) {
                    $isComplete = false;
                    try {
                        $isComplete = isSeasonComplete($db, $season);
                    } catch (Throwable $e) {}
                    if ($isComplete) {
                        $season = getNextSeasonIdentifier($db);
                        try {
                            createLeagueTeams($db, $user_uuid, $season);
                            generateFixtures($db, $season);
                        } catch (Throwable $e) {}
                        // Reselect team_id for the new season
                        $stmtTeam = $db->prepare('SELECT id FROM league_teams WHERE season = :season AND user_uuid = :uuid');
                        if ($stmtTeam) {
                            $stmtTeam->bindValue(':season', $season);
                            $stmtTeam->bindValue(':uuid', $user_uuid);
                            $resTeam = $stmtTeam->execute();
                            $rowTeam = $resTeam ? $resTeam->fetchArray(SQLITE3_ASSOC) : null;
                            if ($rowTeam) {
                                $team_id = (int)$rowTeam['id'];
                            }
                        }
                        $stmtMatch = $db->prepare('
                            SELECT lm.id, lm.uuid
                            FROM league_matches lm
                            WHERE lm.season = :season
                              AND lm.status = \'scheduled\'
                              AND (lm.home_team_id = :team_id OR lm.away_team_id = :team_id)
                            ORDER BY lm.gameweek ASC, lm.id ASC
                            LIMIT 1
                        ');
                        if ($stmtMatch) {
                            $stmtMatch->bindValue(':season', $season);
                            $stmtMatch->bindValue(':team_id', $team_id);
                            $resMatch = $stmtMatch->execute();
                            $rowMatch = $resMatch ? $resMatch->fetchArray(SQLITE3_ASSOC) : null;
                        }
                    }
                }
            }
            if (!$rowMatch) {
                $stmtOpp = $db->prepare('SELECT id FROM league_teams WHERE season = :season AND division = 1 AND id != :team_id ORDER BY id ASC LIMIT 1');
                if ($stmtOpp) {
                    $stmtOpp->bindValue(':season', $season);
                    $stmtOpp->bindValue(':team_id', $team_id);
                    $resOpp = $stmtOpp->execute();
                    $rowOpp = $resOpp ? $resOpp->fetchArray(SQLITE3_ASSOC) : null;
                    if ($rowOpp && isset($rowOpp['id'])) {
                        $gw = 1;
                        try {
                            $gw = getCurrentGameweek($db, $season);
                        } catch (Throwable $e) {}
                        $date = date('Y-m-d');
                        $stmtIns = $db->prepare('INSERT INTO league_matches (season, gameweek, home_team_id, away_team_id, match_date) VALUES (:season, :gameweek, :home, :away, :date)');
                        if ($stmtIns) {
                            $stmtIns->bindValue(':season', $season);
                            $stmtIns->bindValue(':gameweek', $gw);
                            $stmtIns->bindValue(':home', $team_id);
                            $stmtIns->bindValue(':away', (int)$rowOpp['id']);
                            $stmtIns->bindValue(':date', $date);
                            $insRes = $stmtIns->execute();
                            if ($insRes) {
                                $newId = (int)$db->lastInsertRowID();
                                $rowMatch = ['id' => $newId, 'uuid' => null];
                            }
                        }
                    }
                }
                if (!$rowMatch) {
                    echo json_encode(['ok' => false, 'error' => 'no_scheduled_match']);
                    exit;
                }
            }
        }
    }

    $match_id = (int)($rowMatch['id'] ?? 0);
    $match_uuid = '';
    // Ensure uuid column exists for league_matches (MySQL)
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
        }
    } catch (Throwable $e) {
        // ignore
    }
    // Try to read existing uuid
    $stmtGetUuid = $db->prepare('SELECT uuid FROM league_matches WHERE id = :id');
    if ($stmtGetUuid) {
        $stmtGetUuid->bindValue(':id', $match_id);
        $resGetUuid = $stmtGetUuid->execute();
        $rowGetUuid = $resGetUuid ? $resGetUuid->fetchArray(SQLITE3_ASSOC) : null;
        $match_uuid = $rowGetUuid['uuid'] ?? '';
    }
    if (!$match_uuid) {
        $match_uuid = generateUUID();
        $up = $db->prepare('UPDATE league_matches SET uuid = :uuid WHERE id = :id');
        if ($up) {
            $up->bindValue(':uuid', $match_uuid);
            $up->bindValue(':id', $match_id);
            $up->execute();
        }
    }

    $resp = ['ok' => true, 'match_uuid' => $match_uuid, 'match_id' => $match_id];
    $db->close();
} catch (Throwable $e) {
    $resp = ['ok' => false, 'error' => 'exception', 'message' => $e->getMessage()];
}

echo json_encode($resp);
