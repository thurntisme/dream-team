<?php
session_start();

require_once 'config/config.php';
require_once 'config/constants.php';
require_once 'partials/layout.php';
require_once 'includes/league_functions.php';

// Check if this is a league match or club challenge
$match_id = $_GET['match_id'] ?? null;
$match_result_id = $_GET['match_result'] ?? null;
$match_uuid = $_GET['match_uuid'] ?? null;
$match_result_uuid = $_GET['match_result_uuid'] ?? null;
$opponent_id = $_GET['opponent'] ?? null;

if ($match_result_uuid) {
    displayMatchResultByUUID($match_result_uuid);
} elseif ($match_result_id) {
    // This is a match result display
    displayMatchResult($match_result_id);
} elseif ($match_uuid) {
    handleLeagueMatchByUUID($match_uuid);
} elseif ($match_id) {
    // This is a league match preview
    handleLeagueMatch($match_id);
} elseif ($opponent_id) {
    // This is a club challenge
    handleClubChallenge($opponent_id);
} else {
    header('Location: league.php');
    exit;
}

function handleLeagueMatch($match_id)
{
    try {
        $db = getDbConnection();
        $user_uuid = $_SESSION['user_uuid'];

        // Get match details
        $stmt = $db->prepare('
            SELECT lm.*, 
                   ht.name as home_team_name, ht.user_uuid as home_user_uuid,
                   at.name as away_team_name, at.user_uuid as away_user_uuid
            FROM league_matches lm
            JOIN league_teams ht ON lm.home_team_id = ht.id
            JOIN league_teams at ON lm.away_team_id = at.id
            WHERE lm.id = :match_id AND lm.status = \'scheduled\'
            AND (ht.user_uuid = :user_uuid OR at.user_uuid = :user_uuid)
        ');
        $stmt->bindValue(':match_id', $match_id);
        $stmt->bindValue(':user_uuid', $user_uuid);
        $result = $stmt->execute();
        if ($result === false) {
            $_SESSION['error'] = 'Failed to load match details.';
            header('Location: league.php');
            exit;
        }
        $match = $result->fetchArray(SQLITE3_ASSOC);

        if (!$match) {
            $_SESSION['error'] = 'Match not found or not available to play.';
            header('Location: league.php');
            exit;
        }

        // Determine user's role and get team data
        $is_home = ($match['home_user_uuid'] === $user_uuid);
        $opponent_user_uuid = $is_home ? $match['away_user_uuid'] : $match['home_user_uuid'];
        $opponent_team_id = $is_home ? $match['away_team_id'] : $match['home_team_id'];

        // Get user's team data
        $stmt = $db->prepare('SELECT u.name, c.club_name, c.formation, c.team, c.budget FROM users u LEFT JOIN user_club c ON c.user_uuid = u.uuid WHERE u.uuid = :uuid');
        $stmt->bindValue(':uuid', $user_uuid);
        $result = $stmt->execute();
        $user_data = $result->fetchArray(SQLITE3_ASSOC);

        // Get opponent's team data (if it's a user, otherwise generate AI team)
        $opponent_data = null;
        $opponent_roster = null;

        if ($opponent_user_uuid) {
            $stmt = $db->prepare('SELECT u.name, c.club_name, c.formation, c.team, c.budget FROM users u LEFT JOIN user_club c ON c.user_uuid = u.uuid WHERE u.uuid = :uuid');
            $stmt->bindValue(':uuid', $opponent_user_uuid);
            $result = $stmt->execute();
            $opponent_data = $result->fetchArray(SQLITE3_ASSOC);
        }

        // Get opponent's league roster (for both user and AI teams)
        $stmt = $db->prepare('SELECT player_data FROM league_team_rosters WHERE league_team_id = :team_id ORDER BY id DESC LIMIT 1');
        $stmt->bindValue(':team_id', $opponent_team_id);
        $result = $stmt->execute();
        $roster_row = $result->fetchArray(SQLITE3_ASSOC);
        if ($roster_row) {
            $opponent_roster = json_decode($roster_row['player_data'], true);
        }

        // Handle match simulation
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['simulate_match'])) {
            // Simulate ALL matches in the current gameweek, including the user's match
            // Resolve numeric id for simulation functions that accept user_id
            $stmtId = $db->prepare('SELECT id FROM users WHERE uuid = :uuid');
            $stmtId->bindValue(':uuid', $user_uuid);
            $resId = $stmtId->execute();
            $rowId = $resId ? $resId->fetchArray(SQLITE3_ASSOC) : null;
            $user_id_resolved = $rowId['id'] ?? null;

            $gameweek_results = simulateCurrentGameweek($db, (int)$user_id_resolved, $match['season'], $match['gameweek']);

            // Store results in session just in case
            $_SESSION['gameweek_results'] = $gameweek_results;

            // Check if our match was simulated successfully
            $stmt_check = $db->prepare("SELECT status FROM league_matches WHERE id = :id");
            $stmt_check->bindValue(':id', $match_id);
            $res_check = $stmt_check->execute();
            if ($res_check === false) {
                $_SESSION['error'] = 'Failed to verify match status.';
                header('Location: league.php');
                exit;
            }
            $row_check = $res_check->fetchArray(SQLITE3_ASSOC);

            if ($row_check && $row_check['status'] === 'completed') {
                // Redirect to match result page
                header('Location: match-simulator.php?match_result=' . $match_id);
                exit;
            } else {
                // Fallback: directly simulate just this match
                simulateMatch($db, (int)$match_id, (int)$user_id_resolved);
                // Re-check status
                $stmt_check2 = $db->prepare("SELECT status FROM league_matches WHERE id = :id");
                $stmt_check2->bindValue(':id', $match_id);
                $res_check2 = $stmt_check2->execute();
                $row_check2 = $res_check2 ? $res_check2->fetchArray(SQLITE3_ASSOC) : null;
                if ($row_check2 && $row_check2['status'] === 'completed') {
                    header('Location: match-simulator.php?match_result=' . $match_id);
                    exit;
                }
                $_SESSION['error'] = 'Failed to simulate match.';
            }
        }

        $db->close();
        displayLeagueMatch($match, $user_data, $opponent_data, $is_home, $opponent_roster);
    } catch (Exception $e) {
        error_log("League match error: " . $e->getMessage());
        $_SESSION['error'] = 'An error occurred while loading the match.';
        header('Location: league.php');
        exit;
    }
}

function handleLeagueMatchByUUID($match_uuid)
{
    try {
        $db = getDbConnection();
        $user_uuid = $_SESSION['user_uuid'];

        $stmt = $db->prepare('
            SELECT lm.*, 
                   ht.name as home_team_name, ht.user_uuid as home_user_uuid,
                   at.name as away_team_name, at.user_uuid as away_user_uuid
            FROM league_matches lm
            JOIN league_teams ht ON lm.home_team_id = ht.id
            JOIN league_teams at ON lm.away_team_id = at.id
            WHERE lm.uuid = :match_uuid AND lm.status = \'scheduled\'
            AND (ht.user_uuid = :user_uuid OR at.user_uuid = :user_uuid)
        ');
        $stmt->bindValue(':match_uuid', $match_uuid);
        $stmt->bindValue(':user_uuid', $user_uuid);
        $result = $stmt->execute();
        if ($result === false) {
            $_SESSION['error'] = 'Failed to load match details.';
            header('Location: league.php');
            exit;
        }
        $match = $result->fetchArray(SQLITE3_ASSOC);

        if (!$match) {
            $_SESSION['error'] = 'Match not found or not available to play.';
            header('Location: league.php');
            exit;
        }

        $is_home = ($match['home_user_uuid'] === $user_uuid);
        $opponent_user_uuid = $is_home ? $match['away_user_uuid'] : $match['home_user_uuid'];

        // Get user team
        $stmt = $db->prepare('SELECT u.name, c.club_name, c.formation, c.team, c.budget FROM users u LEFT JOIN user_club c ON c.user_uuid = u.uuid WHERE u.uuid = :uuid');
        $stmt->bindValue(':uuid', $user_uuid);
        $result = $stmt->execute();
        $user_data = $result->fetchArray(SQLITE3_ASSOC);

        // Get opponent - for league matches, load roster from league_team_rosters
        $stmt = $db->prepare('SELECT id FROM league_teams WHERE season = :season AND user_uuid = :uuid');
        $stmt->bindValue(':season', $match['season']);
        $stmt->bindValue(':uuid', $opponent_user_uuid);
        $resTeam = $stmt->execute();
        $rowTeam = $resTeam ? $resTeam->fetchArray(SQLITE3_ASSOC) : null;
        $opponent_roster = null;
        if ($rowTeam) {
            $team_id = (int)$rowTeam['id'];
            $stmt = $db->prepare('SELECT player_data FROM league_team_rosters WHERE league_team_id = :id AND season = :season');
            $stmt->bindValue(':id', $team_id);
            $stmt->bindValue(':season', $match['season']);
            $resRoster = $stmt->execute();
            $rowRoster = $resRoster ? $resRoster->fetchArray(SQLITE3_ASSOC) : null;
            if ($rowRoster) {
                $opponent_roster = json_decode($rowRoster['player_data'], true);
            }
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['simulate_match'])) {
            $stmt_check = $db->prepare("SELECT status FROM league_matches WHERE uuid = :uuid");
            $stmt_check->bindValue(':uuid', $match_uuid);
            $res_check = $stmt_check->execute();
            $row_check = $res_check ? $res_check->fetchArray(SQLITE3_ASSOC) : null;
            if ($row_check && $row_check['status'] === 'scheduled') {
                // Resolve numeric id for simulation
                $stmt_id = $db->prepare("SELECT id FROM league_matches WHERE uuid = :uuid");
                $stmt_id->bindValue(':uuid', $match_uuid);
                $res_id = $stmt_id->execute();
                $row_id = $res_id ? $res_id->fetchArray(SQLITE3_ASSOC) : null;
                $match_id = (int)($row_id['id'] ?? 0);
                if ($match_id > 0 && simulateMatch($db, $match_id, (int)$_SESSION['user_id'])) {
                    header('Location: match-simulator.php?match_result_uuid=' . $match_uuid);
                    exit;
                }
                $_SESSION['error'] = 'Failed to simulate match.';
            }
        }

        $db->close();
        displayLeagueMatch($match, $user_data, null, $is_home, null);
    } catch (Exception $e) {
        error_log("League match error: " . $e->getMessage());
        $_SESSION['error'] = 'An error occurred while loading the match.';
        header('Location: league.php');
        exit;
    }
}

function displayLeagueMatch($match, $user_data, $opponent_data, $is_home, $opponent_roster = null)
{
    startContent();
?>

    <script>
        // Global formation modal function - defined early so onclick handlers can access it
        window.showFormationModal = function(side, teamData) {
            const team = JSON.parse(JSON.stringify(teamData));
            const formation = team.formation || '4-4-2';
            const teamPlayers = JSON.parse(team.team || '[]');

            // Formation data
            const formations = {
                '4-4-2': {
                    roles: ['GK', 'LB', 'CB', 'CB', 'RB', 'LM', 'CM', 'CM', 'RM', 'ST', 'ST']
                },
                '4-3-3': {
                    roles: ['GK', 'LB', 'CB', 'CB', 'RB', 'CDM', 'CM', 'CM', 'LW', 'ST', 'RW']
                },
                '3-5-2': {
                    roles: ['GK', 'CB', 'CB', 'CB', 'LWB', 'CDM', 'CM', 'CAM', 'RWB', 'ST', 'ST']
                },
                '4-2-3-1': {
                    roles: ['GK', 'LB', 'CB', 'CB', 'RB', 'CDM', 'CDM', 'LW', 'CAM', 'RW', 'ST']
                },
                '4-1-4-1': {
                    roles: ['GK', 'LB', 'CB', 'CB', 'RB', 'CDM', 'LM', 'CM', 'CM', 'RM', 'ST']
                },
                '5-3-2': {
                    roles: ['GK', 'LWB', 'CB', 'CB', 'CB', 'RWB', 'CM', 'CAM', 'CM', 'ST', 'ST']
                },
                '3-4-3': {
                    roles: ['GK', 'CB', 'CB', 'CB', 'LM', 'CDM', 'CDM', 'RM', 'LW', 'ST', 'RW']
                },
                '4-3-2-1': {
                    roles: ['GK', 'LB', 'CB', 'CB', 'RB', 'CDM', 'CM', 'CM', 'CAM', 'CAM', 'ST']
                },
                '4-5-1': {
                    roles: ['GK', 'LB', 'CB', 'CB', 'RB', 'LM', 'CM', 'CM', 'CM', 'RM', 'ST']
                }
            };

            const formationRoles = formations[formation]?.roles || formations['4-4-2'].roles;

            // Build formation HTML with field visualization
            let html = `
                <div class="space-y-6">
                    <!-- Formation Header -->
                    <div class="bg-gradient-to-r from-blue-50 to-blue-100 rounded-lg p-4 border border-blue-200">
                        <div class="flex items-center justify-between">
                            <div>
                                <h3 class="text-xl font-bold text-gray-900">${formation}</h3>
                                <p class="text-sm text-gray-600">Team Formation Overview</p>
                            </div>
                            <div class="text-right">
                                <div class="text-sm text-gray-600">Players</div>
                                <div class="text-2xl font-bold text-blue-600">${teamPlayers.filter(p => p).length}/11</div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Field Visualization -->
                    <div class="bg-gradient-to-b from-green-500 to-green-600 rounded-lg shadow-lg p-6 h-[500px] relative">
                        <!-- Field Lines -->
                        <div class="absolute inset-6 border-2 border-white border-opacity-40 rounded overflow-hidden">
                            <!-- Center Line -->
                            <div class="absolute top-1/2 left-0 right-0 h-0.5 bg-white opacity-40"></div>
                            <!-- Center Circle -->
                            <div class="absolute top-1/2 left-1/2 transform -translate-x-1/2 -translate-y-1/2 w-24 h-24 border-2 border-white border-opacity-40 rounded-full"></div>
                            <div class="absolute top-1/2 left-1/2 transform -translate-x-1/2 -translate-y-1/2 w-2 h-2 bg-white opacity-40 rounded-full"></div>
                        </div>
                        
                        <!-- Players on Field -->
                        <div class="relative h-full">
            `;

            // Render players on field
            const positions = [
                [50], // GK
                [20, 40, 60, 80], // Defense
                [20, 40, 60, 80], // Midfield
                [35, 65] // Attack
            ];

            let playerIdx = 0;
            const positionColors = {
                'GK': {
                    bg: 'bg-amber-400',
                    border: 'border-amber-500'
                },
                'CB': {
                    bg: 'bg-emerald-400',
                    border: 'border-emerald-500'
                },
                'LB': {
                    bg: 'bg-emerald-400',
                    border: 'border-emerald-500'
                },
                'RB': {
                    bg: 'bg-emerald-400',
                    border: 'border-emerald-500'
                },
                'LWB': {
                    bg: 'bg-emerald-400',
                    border: 'border-emerald-500'
                },
                'RWB': {
                    bg: 'bg-emerald-400',
                    border: 'border-emerald-500'
                },
                'CDM': {
                    bg: 'bg-blue-400',
                    border: 'border-blue-500'
                },
                'CM': {
                    bg: 'bg-blue-400',
                    border: 'border-blue-500'
                },
                'CAM': {
                    bg: 'bg-blue-400',
                    border: 'border-blue-500'
                },
                'LM': {
                    bg: 'bg-blue-400',
                    border: 'border-blue-500'
                },
                'RM': {
                    bg: 'bg-blue-400',
                    border: 'border-blue-500'
                },
                'LW': {
                    bg: 'bg-red-400',
                    border: 'border-red-500'
                },
                'RW': {
                    bg: 'bg-red-400',
                    border: 'border-red-500'
                },
                'ST': {
                    bg: 'bg-red-400',
                    border: 'border-red-500'
                },
                'CF': {
                    bg: 'bg-red-400',
                    border: 'border-red-500'
                }
            };

            positions.forEach((line, lineIdx) => {
                const yPos = 100 - ((lineIdx + 1) * (100 / (positions.length + 1)));

                line.forEach(xPos => {
                    const player = teamPlayers[playerIdx];
                    const role = formationRoles[playerIdx];
                    const colors = positionColors[role] || positionColors['GK'];

                    if (player) {
                        html += `
                            <div class="absolute cursor-pointer" style="left: ${xPos}%; top: ${yPos}%; transform: translate(-50%, -50%);">
                                <div class="relative">
                                    <div class="w-14 h-14 bg-white rounded-full flex flex-col items-center justify-center shadow-lg border-2 ${colors.border} overflow-hidden hover:ring-2 hover:ring-yellow-300 transition-all">
                                        <div class="w-full h-full flex items-center justify-center bg-gradient-to-br from-blue-400 to-blue-600 text-white font-bold text-sm">
                                            ${player.name.charAt(0)}
                                        </div>
                                    </div>
                                    <div class="absolute -bottom-6 left-1/2 transform -translate-x-1/2 whitespace-nowrap">
                                        <div class="text-white text-xs font-bold bg-black bg-opacity-70 px-2 py-1 rounded">${player.name}</div>
                                    </div>
                                    <div class="absolute -top-6 left-1/2 transform -translate-x-1/2 whitespace-nowrap">
                                        <div class="text-white text-xs font-bold bg-black bg-opacity-70 px-2 py-1 rounded">${role}</div>
                                    </div>
                                </div>
                            </div>
                        `;
                    } else {
                        html += `
                            <div class="absolute" style="left: ${xPos}%; top: ${yPos}%; transform: translate(-50%, -50%);">
                                <div class="w-14 h-14 bg-white bg-opacity-20 rounded-full flex flex-col items-center justify-center border-2 border-white border-dashed">
                                    <i data-lucide="plus" class="w-5 h-5 text-white"></i>
                                </div>
                                <div class="absolute -top-6 left-1/2 transform -translate-x-1/2 whitespace-nowrap">
                                    <div class="text-white text-xs font-bold bg-black bg-opacity-70 px-2 py-1 rounded">${role}</div>
                                </div>
                            </div>
                        `;
                    }

                    playerIdx++;
                });
            });

            html += `
                        </div>
                    </div>
                    
                    <!-- Player Details -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            `;

            // Add player details
            playerIdx = 0;
            positions.forEach((line, lineIdx) => {
                line.forEach(xPos => {
                    const player = teamPlayers[playerIdx];
                    const role = formationRoles[playerIdx];
                    const colors = positionColors[role] || positionColors['GK'];

                    if (player) {
                        html += `
                            <div class="bg-gray-50 rounded-lg p-3 border-l-4 ${colors.border}">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <div class="font-semibold text-gray-900">${player.name}</div>
                                        <div class="text-xs text-gray-600">${player.position}</div>
                                    </div>
                                    <div class="text-right">
                                        <div class="text-xs font-bold text-gray-700 bg-gray-200 px-2 py-1 rounded">${role}</div>
                                        <div class="text-sm font-bold text-blue-600">★${player.rating}</div>
                                    </div>
                                </div>
                            </div>
                        `;
                    }

                    playerIdx++;
                });
            });

            html += `
                    </div>
                </div>
            `;

            document.getElementById('formationModalTitle').textContent = `${team.club_name || 'Team'} - ${formation}`;
            document.getElementById('formationContent').innerHTML = html;
            document.getElementById('formationModal').classList.remove('hidden');
            lucide.createIcons();
        };
    </script>

    <div class="container mx-auto py-6">
        <!-- Match Header -->
        <div class="bg-white rounded-lg shadow-lg border border-gray-200 overflow-hidden mb-6">
            <div class="bg-gradient-to-r from-blue-500 to-blue-600 text-white p-6">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-4">
                        <div class="w-12 h-12 bg-white bg-opacity-20 rounded-lg flex items-center justify-center">
                            <i data-lucide="stadium" class="w-6 h-6"></i>
                        </div>
                        <div>
                            <h1 class="text-2xl font-bold">Elite League Match</h1>
                            <p class="text-blue-100">
                                Gameweek <?php echo $match['gameweek']; ?> •
                                <?php echo date('l, M j, Y', strtotime($match['match_date'])); ?>
                            </p>
                        </div>
                    </div>
                    <div class="text-right">
                        <div class="bg-white bg-opacity-20 px-4 py-2 rounded-lg">
                            <i data-lucide="map-pin" class="w-4 h-4 inline mr-2"></i>
                            <?php echo htmlspecialchars($match['home_team_name']); ?> Stadium
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Team Lineups -->
        <div class="grid md:grid-cols-2 gap-6 mb-6">
            <!-- Home Team -->
            <div class="bg-white rounded-lg shadow border border-gray-200 overflow-hidden">
                <div class="bg-green-50 border-b border-green-200 p-4">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-3 flex-1">
                            <div class="w-10 h-10 <?php echo $is_home ? 'bg-blue-600' : 'bg-gray-500'; ?> rounded-full flex items-center justify-center">
                                <i data-lucide="<?php echo $is_home ? 'user' : 'users'; ?>" class="w-5 h-5 text-white"></i>
                            </div>
                            <div>
                                <h3 class="font-bold text-lg <?php echo $is_home ? 'text-blue-600' : 'text-gray-700'; ?>">
                                    <?php echo htmlspecialchars($match['home_team_name']); ?>
                                </h3>
                                <div class="flex items-center gap-2 text-sm">
                                    <i data-lucide="home" class="w-4 h-4 text-green-600"></i>
                                    <span class="text-green-600 font-medium">HOME</span>
                                    <?php if ($is_home): ?>
                                        <span class="bg-blue-100 text-blue-800 px-2 py-1 rounded-full text-xs font-bold ml-2">YOUR TEAM</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <?php if ($is_home && $user_data): ?>
                            <button onclick="showFormationModal('home', <?php echo htmlspecialchars(json_encode($user_data)); ?>)"
                                class="bg-blue-100 text-blue-700 px-3 py-1 rounded text-xs font-medium hover:bg-blue-200 transition-colors flex items-center gap-1">
                                <i data-lucide="grid-3x3" class="w-3 h-3"></i>
                                Formation
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="p-4">
                    <?php displayTeamLineup($is_home ? $user_data : $opponent_data, 'home', $is_home ? null : $opponent_roster); ?>
                </div>
            </div>

            <!-- Away Team -->
            <div class="bg-white rounded-lg shadow border border-gray-200 overflow-hidden">
                <div class="bg-orange-50 border-b border-orange-200 p-4">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-3 flex-1">
                            <div class="w-10 h-10 <?php echo !$is_home ? 'bg-blue-600' : 'bg-gray-500'; ?> rounded-full flex items-center justify-center">
                                <i data-lucide="<?php echo !$is_home ? 'user' : 'users'; ?>" class="w-5 h-5 text-white"></i>
                            </div>
                            <div>
                                <h3 class="font-bold text-lg <?php echo !$is_home ? 'text-blue-600' : 'text-gray-700'; ?>">
                                    <?php echo htmlspecialchars($match['away_team_name']); ?>
                                </h3>
                                <div class="flex items-center gap-2 text-sm">
                                    <i data-lucide="plane" class="w-4 h-4 text-orange-600"></i>
                                    <span class="text-orange-600 font-medium">AWAY</span>
                                    <?php if (!$is_home): ?>
                                        <span class="bg-blue-100 text-blue-800 px-2 py-1 rounded-full text-xs font-bold ml-2">YOUR TEAM</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <?php if (!$is_home && $user_data): ?>
                            <button onclick="showFormationModal('away', <?php echo htmlspecialchars(json_encode($user_data)); ?>)"
                                class="bg-blue-100 text-blue-700 px-3 py-1 rounded text-xs font-medium hover:bg-blue-200 transition-colors flex items-center gap-1">
                                <i data-lucide="grid-3x3" class="w-3 h-3"></i>
                                Formation
                            </button>
                        <?php elseif ($is_home && ($opponent_data || $opponent_roster)): ?>
                            <button onclick="showFormationModal('away', <?php echo htmlspecialchars(json_encode($opponent_data ?: ['team' => json_encode($opponent_roster), 'formation' => '4-4-2', 'club_name' => $match['away_team_name']])); ?>)"
                                class="bg-blue-100 text-blue-700 px-3 py-1 rounded text-xs font-medium hover:bg-blue-200 transition-colors flex items-center gap-1">
                                <i data-lucide="grid-3x3" class="w-3 h-3"></i>
                                Formation
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="p-4">
                    <?php displayTeamLineup(!$is_home ? $user_data : $opponent_data, 'away', !$is_home ? null : $opponent_roster); ?>
                </div>
            </div>
        </div>

        <!-- Match Actions -->
        <div class="bg-white rounded-lg shadow border border-gray-200 p-6">
            <div class="flex items-center justify-between">
                <div>
                    <h3 class="text-lg font-semibold text-gray-900 mb-2">Ready to Play?</h3>
                    <p class="text-gray-600">
                        Both teams are set up and ready. Click simulate to see the match result.
                    </p>
                </div>
                <div class="flex gap-3">
                    <a href="league.php"
                        class="bg-gray-100 text-gray-700 px-6 py-3 rounded-lg hover:bg-gray-200 font-medium transition-colors flex items-center gap-2">
                        <i data-lucide="arrow-left" class="w-4 h-4"></i>
                        Back to League
                    </a>
                    <form id="simulateForm" method="POST" class="inline" onsubmit="return checkPlayerFitness(<?php echo htmlspecialchars(json_encode($user_data)); ?>)">
                        <button type="submit" name="simulate_match"
                            class="bg-green-600 text-white px-8 py-3 rounded-lg hover:bg-green-700 font-bold transition-colors flex items-center gap-2 shadow-md">
                            <i data-lucide="play" class="w-5 h-5"></i>
                            Simulate Match
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Formation Modal -->
    <div id="formationModal" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-[100]">
        <div class="bg-white rounded-lg p-6 w-full max-w-4xl max-h-[90vh] overflow-y-auto">
            <div class="flex justify-between items-center mb-6">
                <h2 class="text-2xl font-bold text-gray-900" id="formationModalTitle">Team Formation</h2>
                <button id="closeFormationModal" class="text-gray-500 hover:text-gray-700">
                    <i data-lucide="x" class="w-6 h-6"></i>
                </button>
            </div>
            <div id="formationContent">
                <!-- Formation will be rendered here -->
            </div>
        </div>
    </div>

    <script>
        // Close formation modal - attach listeners when DOM is ready
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', attachFormationModalListeners);
        } else {
            attachFormationModalListeners();
        }

        function attachFormationModalListeners() {
            const closeBtn = document.getElementById('closeFormationModal');
            if (closeBtn) {
                closeBtn.addEventListener('click', function() {
                    document.getElementById('formationModal').classList.add('hidden');
                });
            }

            const modal = document.getElementById('formationModal');
            if (modal) {
                modal.addEventListener('click', function(e) {
                    if (e.target === this) {
                        this.classList.add('hidden');
                    }
                });
            }
        }

        // Check player fitness before match
        let fitnessOverride = false;
        function checkPlayerFitness(teamData) {
            if (fitnessOverride) {
                return true;
            }
            if (typeof teamData === 'string') {
                try {
                    teamData = JSON.parse(teamData);
                } catch (e) {
                    teamData = {};
                }
            }
            const parsePlayers = (src) => {
                if (!src) return [];
                if (Array.isArray(src)) return src;
                if (typeof src === 'string') {
                    try {
                        const arr = JSON.parse(src);
                        return Array.isArray(arr) ? arr : [];
                    } catch (e) {
                        return [];
                    }
                }
                return [];
            };
            const team = parsePlayers(teamData.team);
            const substitutes = parsePlayers(teamData.substitutes);
            const allPlayers = [...team, ...substitutes];
            const coerceFitness = (val) => {
                if (val === undefined || val === null) return NaN;
                if (typeof val === 'number') return val;
                if (typeof val === 'string') {
                    const m = val.match(/\d+/);
                    return m ? Number(m[0]) : NaN;
                }
                return NaN;
            };
            const lowFitnessPlayers = allPlayers.filter((player) => {
                if (!player || player.fitness === undefined || player.fitness === null) return false;
                const f = coerceFitness(player.fitness);
                return Number.isFinite(f) && f < 20;
            });
            if (lowFitnessPlayers.length > 0) {
                showFitnessWarning(lowFitnessPlayers);
                return false;
            }
            return true;
        }

        function showFitnessWarning(players) {
            const modal = document.getElementById('fitnessWarningModal');
            const playersList = document.getElementById('lowFitnessPlayersList');
            const proceedBtn = document.getElementById('proceedAnywayBtn');

            // Build player list HTML
            let html = '';
            players.forEach(player => {
                html += `
                    <div class="flex items-center justify-between p-3 bg-red-50 rounded-lg border border-red-200 mb-2">
                        <div>
                            <div class="font-medium text-gray-900">${player.name}</div>
                            <div class="text-sm text-gray-600">${player.position}</div>
                        </div>
                        <div class="text-right">
                            <div class="text-lg font-bold text-red-600">${player.fitness}%</div>
                            <div class="text-xs text-red-600">Fitness</div>
                        </div>
                    </div>
                `;
            });

            playersList.innerHTML = html;
            modal.classList.remove('hidden');
            document.body.style.overflow = 'hidden'; // Disable body scroll
            if (proceedBtn) {
                proceedBtn.onclick = function() {
                    fitnessOverride = true;
                    modal.classList.add('hidden');
                    document.body.style.overflow = '';
                    const form = document.getElementById('simulateForm');
                    if (form) form.submit();
                };
            }
        }

        function closeFitnessWarning() {
            const modal = document.getElementById('fitnessWarningModal');
            if (modal) {
                modal.classList.add('hidden');
                document.body.style.overflow = ''; // Re-enable body scroll
            }
        }

        function initFitnessWarningListeners() {
            const modal = document.getElementById('fitnessWarningModal');
            const closeBtnIcon = document.getElementById('closeFitnessWarningModal');
            const closeBtnFooter = document.getElementById('closeFitnessWarningModalBtn');

            if (closeBtnIcon) {
                closeBtnIcon.addEventListener('click', closeFitnessWarning);
            }

            if (closeBtnFooter) {
                closeBtnFooter.addEventListener('click', closeFitnessWarning);
            }

            if (modal) {
                modal.addEventListener('click', function(e) {
                    if (e.target === this) {
                        closeFitnessWarning();
                    }
                });
            }
        }

        // Initialize fitness warning listeners when DOM is ready
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', initFitnessWarningListeners);
        } else {
            initFitnessWarningListeners();
        }
    </script>

    <!-- Fitness Warning Modal -->
    <div id="fitnessWarningModal" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-[100]">
        <div class="bg-white rounded-lg p-6 w-full max-w-2xl">
            <div class="flex justify-between items-center mb-6">
                <div class="flex items-center gap-3">
                    <div class="w-12 h-12 bg-red-100 rounded-full flex items-center justify-center">
                        <i data-lucide="alert-circle" class="w-6 h-6 text-red-600"></i>
                    </div>
                    <div>
                        <h2 class="text-2xl font-bold text-gray-900">Low Fitness Warning</h2>
                        <p class="text-sm text-gray-600">Players below 20% fitness cannot play</p>
                    </div>
                </div>
                <button id="closeFitnessWarningModal" class="text-gray-500 hover:text-gray-700">
                    <i data-lucide="x" class="w-6 h-6"></i>
                </button>
            </div>

            <div class="mb-6">
                <p class="text-gray-700 mb-4">The following players have fitness below 20% and must be rested before playing:</p>
                <div id="lowFitnessPlayersList" class="space-y-2 max-h-64 overflow-y-auto">
                    <!-- Players will be listed here -->
                </div>
            </div>

            <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-6">
                <p class="text-sm text-blue-900">
                    <i data-lucide="info" class="w-4 h-4 inline mr-2"></i>
                    Please rest these players or replace them with substitutes before playing the match.
                </p>
            </div>

            <div class="flex justify-end gap-3">
                <a href="team.php" class="bg-blue-600 text-white px-6 py-2 rounded-lg hover:bg-blue-700 font-medium transition-colors inline-flex items-center gap-2">
                    <i data-lucide="users" class="w-4 h-4"></i>
                    Go to Team
                </a>
                <button id="closeFitnessWarningModalBtn" class="bg-gray-600 text-white px-6 py-2 rounded-lg hover:bg-gray-700 font-medium transition-colors">
                    Close
                </button>
                <button id="proceedAnywayBtn" class="bg-green-600 text-white px-6 py-2 rounded-lg hover:bg-green-700 font-medium transition-colors">
                    Proceed Anyway
                </button>
            </div>
        </div>
    </div>

    <?php showPlayerInfoModal(); ?>

<?php
    endContent('League Match');
}

// Player Info Modal
function showPlayerInfoModal()
{
?>
    <!-- Player Info Modal -->
    <div id="playerInfoModal" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-[100]">
        <div class="bg-white rounded-lg p-6 w-full max-w-2xl max-h-[90vh] overflow-y-auto">
            <div class="flex justify-end mb-6">
                <button id="closePlayerInfoModal" class="text-gray-500 hover:text-gray-700">
                    <i data-lucide="x" class="w-6 h-6"></i>
                </button>
            </div>
            <div id="playerInfoContent">
                <!-- Player info will be loaded here -->
            </div>
        </div>
    </div>

    <script>
        // Format market value for display (JavaScript version)
        function formatMarketValue(value) {
            if (value >= 1000000) {
                return '€' + (value / 1000000).toFixed(1) + 'M';
            } else if (value >= 1000) {
                return '€' + (value / 1000).toFixed(0) + 'K';
            }
            return '€' + value;
        }

        function showPlayerInfo(playerData) {
            const player = playerData;

            // Get contract matches (initialize if not set)
            const contractMatches = player.contract_matches || Math.floor(Math.random() * 36) + 15;
            const contractRemaining = player.contract_matches_remaining || contractMatches;

            // Use player's actual attributes if available, otherwise generate them
            let stats = player.attributes || generatePlayerStats(player.position, player.rating);

            // Capitalize first letter of attribute names
            const formatAttributeName = (name) => {
                return name.charAt(0).toUpperCase() + name.slice(1);
            };

            // Normalize stats object to have capitalized keys for display
            const normalizedStats = {};
            Object.entries(stats).forEach(([key, value]) => {
                normalizedStats[formatAttributeName(key)] = value;
            });

            const playerInfoHtml = `
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <!-- Player Header -->
                    <div class="lg:col-span-2 bg-gradient-to-r from-blue-600 to-blue-800 text-white rounded-lg p-6">
                        <div class="flex items-center gap-6">
                            <div class="w-24 h-24 bg-white bg-opacity-20 rounded-full flex items-center justify-center overflow-hidden">
                                ${player.avatar ?
                    `<img src="${player.avatar.startsWith('http') ? player.avatar : '<?php echo PLAYER_IMAGES_BASE_PATH; ?>' + player.avatar}" alt="${player.name}" class="w-full h-full object-cover" onerror="this.onerror=null; this.parentElement.innerHTML='<i data-lucide=\\'user\\' class=\\'w-12 h-12\\'></i>';">` :
                    `<i data-lucide="user" class="w-12 h-12"></i>`
                }
                            </div>
                            <div class="flex-1">
                                <h2 class="text-3xl font-bold mb-2">${player.name}</h2>
                                <div class="flex items-center gap-4 text-blue-100">
                                    <span class="bg-blue-500 px-2 py-1 rounded text-sm font-semibold">${player.position}</span>
                                    ${player.nationality ? `
                                    <span class="flex items-center gap-1">
                                        <i data-lucide="flag" class="w-4 h-4"></i>
                                        ${player.nationality}
                                    </span>
                                    ` : ''}
                                    ${player.age ? `
                                    <span class="flex items-center gap-1">
                                        <i data-lucide="calendar" class="w-4 h-4"></i>
                                        ${player.age} years
                                    </span>
                                    ` : ''}
                                </div>
                            </div>
                            <div class="text-right">
                                <div class="text-3xl font-bold">★${player.rating}</div>
                                <div class="text-blue-200 text-sm">Overall Rating</div>
                            </div>
                        </div>
                    </div>

                    <!-- Career Information -->
                    <div class="bg-gray-50 rounded-lg p-4">
                        <h3 class="text-lg font-semibold mb-4 flex items-center gap-2">
                            <i data-lucide="briefcase" class="w-5 h-5 text-green-600"></i>
                            Career Information
                        </h3>
                        <div class="space-y-3">
                            ${player.nationality ? `
                            <div class="flex justify-between">
                                <span class="text-gray-600">Nationality:</span>
                                <span class="font-medium flex items-center gap-1">
                                    <i data-lucide="flag" class="w-4 h-4"></i>
                                    ${player.nationality}
                                </span>
                            </div>
                            ` : ''}
                            ${player.age ? `
                            <div class="flex justify-between">
                                <span class="text-gray-600">Age:</span>
                                <span class="font-medium">${player.age} years old</span>
                            </div>
                            ` : ''}
                            <div class="flex justify-between">
                                <span class="text-gray-600">Current Club:</span>
                                <span class="font-medium">${player.club || 'Free Agent'}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-600">Market Value:</span>
                                <span class="font-medium text-green-600">${formatMarketValue(player.value)}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-600">Primary Position:</span>
                                <span class="font-medium">${player.position}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-600">Matches Played:</span>
                                <span class="font-medium">${player.matches_played || 0}</span>
                            </div>
                        </div>
                    </div>

                    <!-- Positions & Skills -->
                    <div class="bg-gray-50 rounded-lg p-4">
                        <h3 class="text-lg font-semibold mb-4 flex items-center gap-2">
                            <i data-lucide="target" class="w-5 h-5 text-purple-600"></i>
                            Positions & Skills
                        </h3>
                        <div class="space-y-4">
                            <div>
                                <span class="text-gray-600 text-sm">Playable Positions:</span>
                                <div class="flex flex-wrap gap-2 mt-2">
                                    ${(player.playablePositions || [player.position]).map(pos =>
                        `<span class="bg-blue-100 text-blue-800 px-2 py-1 rounded text-sm font-medium">${pos}</span>`
                    ).join('')}
                                </div>
                            </div>
                            <div>
                                <span class="text-gray-600 text-sm">Key Attributes:</span>
                                <div class="mt-2 space-y-2">
                                    ${Object.entries(normalizedStats).map(([stat, value]) => `
                                        <div class="flex justify-between items-center">
                                            <span class="text-sm">${stat}</span>
                                            <div class="flex items-center gap-2">
                                                <div class="w-16 bg-gray-200 rounded-full h-2">
                                                    <div class="bg-blue-600 h-2 rounded-full" style="width: ${value}%"></div>
                                                </div>
                                                <span class="text-sm font-medium w-8">${value}</span>
                                            </div>
                                        </div>
                                    `).join('')}
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Description -->
                    <div class="lg:col-span-2 bg-gray-50 rounded-lg p-4">
                        <h3 class="text-lg font-semibold mb-4 flex items-center gap-2">
                            <i data-lucide="file-text" class="w-5 h-5 text-orange-600"></i>
                            Player Description
                        </h3>
                        <p class="text-gray-700 leading-relaxed">${player.description || 'Professional football player with great potential and skills.'}</p>
                    </div>
                </div>
            `;

            document.getElementById('playerInfoContent').innerHTML = playerInfoHtml;
            document.getElementById('playerInfoModal').classList.remove('hidden');
            lucide.createIcons();
        }

        // Helper function to generate player stats based on position and rating
        function generatePlayerStats(position, rating) {
            const baseStats = {
                pace: Math.min(99, rating + Math.floor(Math.random() * 20) - 10),
                shooting: Math.min(99, rating + Math.floor(Math.random() * 20) - 10),
                passing: Math.min(99, rating + Math.floor(Math.random() * 20) - 10),
                dribbling: Math.min(99, rating + Math.floor(Math.random() * 20) - 10),
                defense: Math.min(99, rating + Math.floor(Math.random() * 20) - 10),
                physical: Math.min(99, rating + Math.floor(Math.random() * 20) - 10)
            };
            return baseStats;
        }

        // Close player info modal
        document.getElementById('closePlayerInfoModal').addEventListener('click', function() {
            document.getElementById('playerInfoModal').classList.add('hidden');
        });

        document.getElementById('playerInfoModal').addEventListener('click', function(e) {
            if (e.target === this) {
                this.classList.add('hidden');
            }
        });
    </script>
<?php
}

function displayMatchResult($match_id)
{
    try {
        $db = getDbConnection();
        $user_uuid = $_SESSION['user_uuid'];

        // Get match details
        $stmt = $db->prepare('
            SELECT lm.*, 
                   ht.name as home_team_name, ht.user_uuid as home_user_uuid,
                   at.name as away_team_name, at.user_uuid as away_user_uuid
            FROM league_matches lm
            JOIN league_teams ht ON lm.home_team_id = ht.id
            JOIN league_teams at ON lm.away_team_id = at.id
            WHERE lm.id = :match_id AND lm.status = \'completed\'
            AND (ht.user_uuid = :user_uuid OR at.user_uuid = :user_uuid)
        ');
        $stmt->bindValue(':match_id', $match_id);
        $stmt->bindValue(':user_uuid', $user_uuid);
        $result = $stmt->execute();
        if ($result === false) {
            $_SESSION['error'] = 'Failed to load match result.';
            header('Location: league.php');
            exit;
        }
        $match = $result->fetchArray(SQLITE3_ASSOC);

        if (!$match) {
            $_SESSION['error'] = 'Match result not found.';
            header('Location: league.php');
            exit;
        }

        // Get user's current data
        $stmt = $db->prepare('SELECT c.club_name, c.budget, c.fans, s.capacity, s.level FROM user_club c LEFT JOIN stadiums s ON s.user_uuid = c.user_uuid WHERE c.user_uuid = :uuid');
        $stmt->bindValue(':uuid', $user_uuid);
        $result = $stmt->execute();
        $user_data = $result->fetchArray(SQLITE3_ASSOC);

        // Determine user's role
        $is_home = ($match['home_user_uuid'] === $user_uuid);
        $user_score = $is_home ? $match['home_score'] : $match['away_score'];
        $opponent_score = $is_home ? $match['away_score'] : $match['home_score'];

        // Determine match result
        $match_result = 'draw';
        if ($user_score > $opponent_score) {
            $match_result = 'win';
        } elseif ($user_score < $opponent_score) {
            $match_result = 'loss';
        }

        // Calculate match rewards
        $rewards = calculateLeagueMatchRewards($match_result, $user_score, $opponent_score, $is_home);

        // Try to get results from session if available (most accurate)
        $gameweek_results = $_SESSION['gameweek_results'] ?? null;

        if ($gameweek_results && isset($gameweek_results['fan_change_info'])) {
            // Use session data
            $rewards['fan_change'] = $gameweek_results['fan_change_info']['fan_change'];
            $rewards['budget_earned'] = $gameweek_results['budget_earned'];
            $rewards['breakdown'] = $gameweek_results['budget_breakdown'];
        } else {
            // Fallback calculation if session data is missing
            
            // 1. Get Fan Revenue Breakdown
            // Pass a positive value to ensure we get the breakdown (value doesn't matter for the breakdown generation)
            // Resolve numeric id for functions expecting user_id
            $stmtId = $db->prepare('SELECT id FROM users WHERE uuid = :uuid');
            $stmtId->bindValue(':uuid', $user_uuid);
            $resId = $stmtId->execute();
            $rowId = $resId ? $resId->fetchArray(SQLITE3_ASSOC) : null;
            $user_id_resolved = $rowId['id'] ?? null;
            $fan_breakdown = getFanRevenueBreakdown($db, (int)$user_id_resolved, $is_home, 100);
            
            foreach ($fan_breakdown as $item) {
                $rewards['breakdown'][] = $item;
            }
            
            // Recalculate total budget
            $total_budget = 0;
            foreach ($rewards['breakdown'] as $item) {
                $total_budget += $item['amount'];
            }
            $rewards['budget_earned'] = $total_budget;
            
            // 2. Estimate Fan Change (Approximation since actual random value is lost)
            $fan_change = 0;
            if ($match_result === 'win') {
                $fan_change = 125; // Avg of 50-200
            } elseif ($match_result === 'draw') {
                $fan_change = 12; // Avg of -25-50
            } else {
                $fan_change = -62; // Avg of -100 to -25
            }
            
            $goal_diff = $user_score - $opponent_score;
            $fan_change += $goal_diff * 10;
            
            $rewards['fan_change'] = (int)$fan_change;
        }

        // Check if mystery box has already been claimed
        $session_key = "mystery_box_claimed_{$match_id}_{$user_id}";
        $mystery_box_claimed = isset($_SESSION[$session_key]) && $_SESSION[$session_key] === true;

        $db->close();
        displayMatchResultPage($match, $is_home, $user_score, $opponent_score, $match_result, $rewards, $user_data, $mystery_box_claimed);
    } catch (Exception $e) {
        error_log("Match result error: " . $e->getMessage());
        $_SESSION['error'] = 'An error occurred while loading the match result.';
        header('Location: league.php');
        exit;
    }
}

function displayMatchResultByUUID($match_uuid)
{
    try {
        $db = getDbConnection();
        $stmt = $db->prepare('SELECT id FROM league_matches WHERE uuid = :uuid');
        $stmt->bindValue(':uuid', $match_uuid);
        $res = $stmt->execute();
        $row = $res ? $row = $res->fetchArray(SQLITE3_ASSOC) : null;
        if (!$row) {
            $_SESSION['error'] = 'Match not found.';
            header('Location: league.php');
            exit;
        }
        displayMatchResult((int)$row['id']);
    } catch (Throwable $e) {
        $_SESSION['error'] = 'Failed to load match result.';
        header('Location: league.php');
        exit;
    }
}

function displayMatchResultPage($match, $is_home, $user_score, $opponent_score, $match_result, $rewards, $user_data, $mystery_box_claimed = false)
{
    startContent();
?>

    <div class="container mx-auto py-6">
        <!-- Match Result Header -->
        <div class="bg-white rounded-lg shadow-lg border border-gray-200 overflow-hidden mb-6">
            <div class="bg-gradient-to-r <?php
                                            echo $match_result === 'win' ? 'from-green-500 to-green-600' : ($match_result === 'draw' ? 'from-yellow-500 to-yellow-600' : 'from-red-500 to-red-600');
                                            ?> text-white p-6">
                <div class="text-center">
                    <div class="mb-4">
                        <div class="text-6xl font-bold mb-2">
                            <?php echo $user_score; ?> - <?php echo $opponent_score; ?>
                        </div>
                        <div class="text-2xl font-bold">
                            <?php
                            echo $match_result === 'win' ? '🎉 VICTORY!' : ($match_result === 'draw' ? '🤝 DRAW!' : '😞 DEFEAT!');
                            ?>
                        </div>
                    </div>
                    <div class="flex items-center justify-center gap-8">
                        <div class="text-right w-[25%] px-4">
                            <div class="text-lg font-semibold">
                                <?php echo $is_home ? htmlspecialchars($match['home_team_name']) : htmlspecialchars($match['away_team_name']); ?>
                            </div>
                            <div class="text-sm opacity-90">Your Team</div>
                        </div>
                        <div class="text-3xl font-bold">VS</div>
                        <div class="text-left w-[25%] px-4">
                            <div class="text-lg font-semibold">
                                <?php echo $is_home ? htmlspecialchars($match['away_team_name']) : htmlspecialchars($match['home_team_name']); ?>
                            </div>
                            <div class="text-sm opacity-90">Opponent</div>
                        </div>
                    </div>
                    <div class="mt-4 text-sm opacity-90">
                        Gameweek <?php echo $match['gameweek']; ?> • Elite League
                    </div>
                </div>
            </div>
        </div>

        <!-- Match Rewards -->
        <div class="bg-white rounded-lg shadow-lg border border-gray-200 overflow-hidden mb-6">
            <div class="bg-gradient-to-r from-green-500 to-green-600 text-white p-4">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 bg-white bg-opacity-20 rounded-lg flex items-center justify-center">
                        <i data-lucide="coins" class="w-5 h-5"></i>
                    </div>
                    <div>
                        <h3 class="text-lg font-bold">Match Rewards</h3>
                        <p class="text-green-100 text-sm">Your earnings from this match</p>
                    </div>
                </div>
            </div>
            <div class="p-6">
                <div class="grid md:grid-cols-2 gap-6">
                    <!-- Budget Rewards -->
                    <div>
                        <h4 class="font-semibold text-gray-900 mb-3 flex items-center gap-2">
                            <i data-lucide="banknote" class="w-4 h-4 text-green-600"></i>
                            Budget Earned
                        </h4>
                        <div class="space-y-2">
                            <?php foreach ($rewards['breakdown'] as $reward): ?>
                                <div class="flex justify-between items-center p-2 bg-gray-50 rounded">
                                    <span class="text-sm"><?php echo htmlspecialchars($reward['description']); ?></span>
                                    <span class="font-medium text-green-600">+€<?php echo number_format($reward['amount']); ?></span>
                                </div>
                            <?php endforeach; ?>
                            <div class="border-t pt-2 mt-3">
                                <div class="flex justify-between items-center font-bold mb-3">
                                    <span>Total Budget Earned:</span>
                                    <span class="text-green-600 text-lg">+€<?php echo number_format($rewards['budget_earned']); ?></span>
                                </div>

                                <!-- Budget Before and After -->
                                <div class="grid grid-cols-2 gap-3">
                                    <div class="bg-red-50 border border-red-200 rounded-lg p-3">
                                        <div class="text-xs text-red-600 font-semibold mb-1">BEFORE MATCH</div>
                                        <div class="text-lg font-bold text-red-700">
                                            €<?php echo number_format($user_data['budget'] - $rewards['budget_earned']); ?>
                                        </div>
                                    </div>
                                    <div class="bg-green-50 border border-green-200 rounded-lg p-3">
                                        <div class="text-xs text-green-600 font-semibold mb-1">AFTER MATCH</div>
                                        <div class="text-lg font-bold text-green-700">
                                            €<?php echo number_format($user_data['budget']); ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Fan Changes -->
                    <div>
                        <h4 class="font-semibold text-gray-900 mb-3 flex items-center gap-2">
                            <i data-lucide="users" class="w-4 h-4 text-blue-600"></i>
                            Fan Support
                        </h4>
                        <div class="space-y-3">
                            <div class="p-4 bg-blue-50 rounded-lg">
                                <div class="flex items-center justify-between mb-2">
                                    <span class="text-sm text-gray-600">Fan Change:</span>
                                    <span class="font-bold <?php echo $rewards['fan_change'] >= 0 ? 'text-green-600' : 'text-red-600'; ?>">
                                        <?php echo $rewards['fan_change'] >= 0 ? '+' : ''; ?><?php echo number_format($rewards['fan_change']); ?>
                                    </span>
                                </div>
                                <div class="flex items-center justify-between">
                                    <span class="text-sm text-gray-600">Current Fans:</span>
                                    <span class="font-medium text-blue-600"><?php echo number_format($user_data['fans'] ?? 5000); ?></span>
                                </div>
                            </div>
                            <div class="text-xs text-gray-500">
                                <?php if ($rewards['fan_change'] > 0): ?>
                                    <i data-lucide="trending-up" class="w-3 h-3 inline mr-1"></i>
                                    Great performance! Your fanbase is growing.
                                <?php elseif ($rewards['fan_change'] < 0): ?>
                                    <i data-lucide="trending-down" class="w-3 h-3 inline mr-1"></i>
                                    Disappointing result. Some fans are losing faith.
                                <?php else: ?>
                                    <i data-lucide="minus" class="w-3 h-3 inline mr-1"></i>
                                    Steady performance. Fan support remains stable.
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Post-Match Reward -->
        <div class="bg-white rounded-lg shadow-lg border border-gray-200 overflow-hidden mb-6">
            <div class="bg-gradient-to-r from-purple-500 to-purple-600 text-white p-4">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 bg-white bg-opacity-20 rounded-lg flex items-center justify-center">
                        <i data-lucide="gift" class="w-5 h-5"></i>
                    </div>
                    <div>
                        <h3 class="text-lg font-bold">Post-Match Reward</h3>
                        <p class="text-purple-100 text-sm">Choose 1 of 3 mystery boxes to reveal your reward!</p>
                    </div>
                </div>
            </div>
            <div class="p-6">
                <div class="grid grid-cols-3 gap-4 mb-6">
                    <?php for ($i = 1; $i <= 3; $i++): ?>
                        <div class="mystery-box cursor-pointer transform hover:scale-105 transition-transform duration-200 <?php echo $mystery_box_claimed ? 'opacity-50 cursor-not-allowed' : ''; ?>"
                            data-box="<?php echo $i; ?>" <?php echo $mystery_box_claimed ? 'style="pointer-events: none;"' : ''; ?>>
                            <div class="bg-gradient-to-br from-purple-400 to-purple-600 rounded-lg p-6 text-center text-white shadow-lg">
                                <?php if ($mystery_box_claimed): ?>
                                    <div class="w-16 h-16 mx-auto mb-3 bg-white bg-opacity-20 rounded-full flex items-center justify-center">
                                        <i data-lucide="lock" class="w-8 h-8"></i>
                                    </div>
                                    <div class="font-bold text-lg">Already Claimed</div>
                                    <div class="text-sm opacity-90">Reward used</div>
                                <?php else: ?>
                                    <div class="w-16 h-16 mx-auto mb-3 bg-white bg-opacity-20 rounded-full flex items-center justify-center">
                                        <i data-lucide="gift" class="w-8 h-8"></i>
                                    </div>
                                    <div class="font-bold text-lg">Mystery Box <?php echo $i; ?></div>
                                    <div class="text-sm opacity-90">Click to reveal</div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endfor; ?>
                </div>

                <!-- Reward Display (Hidden initially) -->
                <div id="reward-display" class="<?php echo $mystery_box_claimed ? '' : 'hidden'; ?>">
                    <?php if ($mystery_box_claimed): ?>
                        <div class="bg-gradient-to-r from-gray-400 to-gray-500 rounded-lg p-6 text-center text-white">
                            <div class="text-2xl font-bold mb-2">🔒 Already Claimed</div>
                            <div class="text-lg">You have already claimed your mystery box reward for this match.</div>
                            <div class="text-sm mt-2 opacity-90">Each match allows only one mystery box claim.</div>
                        </div>
                    <?php else: ?>
                        <div class="bg-gradient-to-r from-yellow-400 to-orange-500 rounded-lg p-6 text-center text-white">
                            <div class="text-2xl font-bold mb-2">🎁 Congratulations!</div>
                            <div id="reward-content" class="text-lg"></div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Continue Button -->
        <div class="text-center">
            <button id="continueBtn"
                class="bg-blue-600 text-white px-8 py-3 rounded-lg hover:bg-blue-700 font-bold transition-colors inline-flex items-center gap-2">
                <i data-lucide="arrow-right" class="w-5 h-5"></i>
                <span>Continue to League</span>
            </button>
        </div>
    </div>

    <script>
        // Handle continue button click
        document.getElementById('continueBtn').addEventListener('click', function() {
            const btn = this;
            const originalContent = btn.innerHTML;

            // Show loading state
            btn.disabled = true;
            btn.innerHTML = '<i data-lucide="loader" class="w-5 h-5 animate-spin"></i><span>Simulating League...</span>';
            if (typeof lucide !== 'undefined') {
                lucide.createIcons();
            }

            // Call API to simulate league
            fetch('api/simulate_league_api.php')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Redirect to league standings
                        window.location.href = 'league.php?tab=standings';
                    } else {
                        console.error('Simulation failed:', data.message);
                        alert('Failed to simulate league: ' + data.message);
                        // Reset button state
                        btn.disabled = false;
                        btn.innerHTML = originalContent;
                        if (typeof lucide !== 'undefined') {
                            lucide.createIcons();
                        }
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('An error occurred. Redirecting to league...');
                    // Fallback redirect
                    window.location.href = 'league.php?tab=standings';
                });
        });

        // Mystery box selection - only if not already claimed
        <?php if (!$mystery_box_claimed): ?>
            document.querySelectorAll('.mystery-box').forEach(box => {
                box.addEventListener('click', function() {
                    const boxNumber = this.dataset.box;

                    // Generate all 3 rewards (one for each box)
                    const allRewards = [{
                            type: 'budget',
                            amount: 150000,
                            text: 'You received €150,000!',
                            icon: '💰'
                        },
                        {
                            type: 'budget',
                            amount: 250000,
                            text: 'You received €250,000!',
                            icon: '💰'
                        },
                        {
                            type: 'budget',
                            amount: 350000,
                            text: 'You received €350,000!',
                            icon: '💰'
                        },
                        {
                            type: 'player',
                            text: 'You received a random player card!',
                            icon: '⚽'
                        },
                        {
                            type: 'item',
                            text: 'You received a training boost item!',
                            icon: '🏃'
                        },
                        {
                            type: 'budget',
                            amount: 100000,
                            text: 'You received €100,000!',
                            icon: '💰'
                        },
                        {
                            type: 'fans',
                            amount: 300,
                            text: 'You gained 300 new fans!',
                            icon: '👥'
                        }
                    ];

                    // Shuffle and pick 3 different rewards
                    const shuffled = [...allRewards].sort(() => 0.5 - Math.random());
                    const boxRewards = shuffled.slice(0, 3);
                    const selectedReward = boxRewards[boxNumber - 1];

                    // Disable all boxes
                    document.querySelectorAll('.mystery-box').forEach(b => {
                        b.style.pointerEvents = 'none';
                    });

                    // Add shake animation to selected box (no scaling)
                    this.style.transition = 'all 0.3s ease';
                    this.style.animation = 'shake 0.5s ease-in-out';

                    // Add animations if not already added
                    if (!document.querySelector('#mystery-animations')) {
                        const style = document.createElement('style');
                        style.id = 'mystery-animations';
                        style.textContent = `
                        @keyframes shake {
                            0%, 100% { transform: translateX(0); }
                            25% { transform: translateX(-5px); }
                            75% { transform: translateX(5px); }
                        }
                        @keyframes pulse-glow {
                            0%, 100% { box-shadow: 0 0 20px rgba(255, 215, 0, 0.5); }
                            50% { box-shadow: 0 0 30px rgba(255, 215, 0, 0.8); }
                        }
                        @keyframes flip-reveal {
                            0% { transform: rotateY(0deg); }
                            50% { transform: rotateY(90deg); }
                            100% { transform: rotateY(0deg); }
                        }
                        @keyframes fade-gray {
                            0% { opacity: 1; }
                            100% { opacity: 0.6; }
                        }
                    `;
                        document.head.appendChild(style);
                    }

                    // Show opening animation and reveal all boxes
                    setTimeout(() => {
                        // Add glow effect to selected box
                        this.style.animation = 'pulse-glow 1s ease-in-out infinite';
                        this.style.borderRadius = '12px';

                        // Fade other boxes
                        document.querySelectorAll('.mystery-box').forEach((b, index) => {
                            const boxNum = parseInt(b.dataset.box);
                            if (boxNum != boxNumber) {
                                b.style.animation = 'fade-gray 0.5s ease-out forwards';
                            }
                        });

                        // Reveal all boxes with flip animation
                        setTimeout(() => {
                            document.querySelectorAll('.mystery-box').forEach((b, index) => {
                                const boxNum = parseInt(b.dataset.box);
                                const reward = boxRewards[boxNum - 1];
                                const boxContent = b.querySelector('.bg-gradient-to-br');

                                // Add flip animation
                                b.style.animation = 'flip-reveal 0.8s ease-in-out';

                                setTimeout(() => {
                                    if (boxNum == boxNumber) {
                                        // Selected box - show as winner (no bouncing icon)
                                        boxContent.innerHTML = `
                                        <div class="w-16 h-16 mx-auto mb-3 bg-white bg-opacity-30 rounded-full flex items-center justify-center text-2xl">
                                            ${reward.icon}
                                        </div>
                                        <div class="font-bold text-lg text-yellow-200">SELECTED!</div>
                                        <div class="text-sm">${reward.text}</div>
                                    `;
                                        boxContent.className = 'bg-gradient-to-br from-yellow-400 to-yellow-600 rounded-lg p-6 text-center text-white shadow-lg';
                                        b.style.animation = 'pulse-glow 2s ease-in-out infinite';
                                    } else {
                                        // Other boxes - show what was inside
                                        boxContent.innerHTML = `
                                        <div class="w-16 h-16 mx-auto mb-3 bg-white bg-opacity-20 rounded-full flex items-center justify-center text-2xl">
                                            ${reward.icon}
                                        </div>
                                        <div class="font-bold text-sm text-gray-300">Box ${boxNum}</div>
                                        <div class="text-xs opacity-75">${reward.text}</div>
                                    `;
                                        boxContent.className = 'bg-gradient-to-br from-gray-400 to-gray-600 rounded-lg p-6 text-center text-white shadow-lg';
                                        b.style.opacity = '0.6';
                                        b.style.animation = 'none';
                                    }
                                }, 400); // Half way through flip animation
                            });

                            // Show main reward display with slide-up animation
                            setTimeout(() => {
                                const rewardDisplay = document.getElementById('reward-display');
                                document.getElementById('reward-content').innerHTML = `
                                <div class="text-3xl mb-2">${selectedReward.icon}</div>
                                <div class="text-xl font-bold">${selectedReward.text}</div>
                                <div class="text-sm mt-2 opacity-90">Check the other boxes to see what you could have won!</div>
                            `;
                                rewardDisplay.style.transform = 'translateY(20px)';
                                rewardDisplay.style.opacity = '0';
                                rewardDisplay.style.transition = 'all 0.5s ease-out';
                                rewardDisplay.classList.remove('hidden');

                                // Slide up animation
                                setTimeout(() => {
                                    rewardDisplay.style.transform = 'translateY(0)';
                                    rewardDisplay.style.opacity = '1';
                                }, 50);

                                // Send reward to backend
                                // Show processing indicator
                                // Applying reward logic starts here
                                fetch('api/mystery_box_reward_api.php', {
                                        method: 'POST',
                                        headers: {
                                            'Content-Type': 'application/json',
                                        },
                                        body: JSON.stringify({
                                            reward: selectedReward,
                                            match_id: <?php echo $match['id'] ?? 'null'; ?>
                                        })
                                    })
                                    .then(response => response.json())
                                    .then(data => {
                                        // Remove processing indicator
                                        const processingIndicator = document.getElementById('processing-indicator');
                                        if (processingIndicator) {
                                            processingIndicator.remove();
                                        }

                                        if (data.success) {
                                            console.log('Reward applied successfully:', data.message);

                                            // Disable all mystery boxes permanently
                                            document.querySelectorAll('.mystery-box').forEach(b => {
                                                b.style.pointerEvents = 'none';
                                                b.style.opacity = '0.5';
                                                b.style.cursor = 'not-allowed';
                                            });

                                            // Update the reward display with confirmation
                                            const confirmationDiv = document.createElement('div');
                                            confirmationDiv.className = 'mt-3 p-2 bg-green-100 text-green-800 rounded-lg text-sm';
                                            confirmationDiv.innerHTML = `
                                        <i data-lucide="check-circle" class="w-4 h-4 inline mr-1"></i>
                                        ${data.message}
                                    `;
                                            document.getElementById('reward-content').appendChild(confirmationDiv);

                                            // Display player info if available
                                            if (data.player_data) {
                                                const player = data.player_data;
                                                const playerCard = document.createElement('div');
                                                playerCard.className = 'mt-4 bg-white rounded-lg shadow-md p-4 text-gray-800 transform transition-all duration-500 animate-fade-in-up';

                                                // Determine border color based on rating
                                                let borderColor = 'border-gray-200';
                                                let headerBg = 'bg-gray-100';

                                                if (player.rating >= 85) {
                                                    borderColor = 'border-yellow-400 border-2';
                                                    headerBg = 'bg-gradient-to-r from-yellow-100 to-yellow-50';
                                                } else if (player.rating >= 80) {
                                                    borderColor = 'border-blue-400 border-2';
                                                    headerBg = 'bg-gradient-to-r from-blue-100 to-blue-50';
                                                } else if (player.rating >= 75) {
                                                    borderColor = 'border-green-400 border-2';
                                                    headerBg = 'bg-gradient-to-r from-green-100 to-green-50';
                                                }

                                                playerCard.classList.add(borderColor.split(' ')[0]);
                                                if (borderColor.includes('border-2')) playerCard.classList.add('border-2');

                                                // Use default stats if not present (fallback)
                                                const stats = {
                                                    pace: player.pace || Math.floor(Math.random() * (95 - 60) + 60),
                                                    shooting: player.shooting || Math.floor(Math.random() * (90 - 50) + 50),
                                                    passing: player.passing || Math.floor(Math.random() * (90 - 50) + 50),
                                                    dribbling: player.dribbling || Math.floor(Math.random() * (92 - 55) + 55),
                                                    defending: player.defending || Math.floor(Math.random() * (88 - 40) + 40),
                                                    physical: player.physical || Math.floor(Math.random() * (90 - 50) + 50)
                                                };

                                                playerCard.innerHTML = `
                                            <div class="flex items-center gap-4 ${headerBg} p-3 rounded-t-lg -mx-4 -mt-4 mb-3 border-b border-gray-100">
                                                <div class="w-16 h-16 bg-gradient-to-br from-blue-600 to-purple-700 rounded-full flex items-center justify-center text-white text-2xl font-bold shadow-lg ring-2 ring-white">
                                                    ${player.rating}
                                                </div>
                                                <div class="text-left flex-1">
                                                    <div class="font-bold text-xl text-gray-900">${player.name}</div>
                                                    <div class="flex items-center gap-2 text-sm text-gray-600">
                                                        <span class="px-2 py-0.5 bg-white border border-gray-200 rounded text-xs font-bold uppercase tracking-wider">${player.position}</span>
                                                        <span class="text-gray-400">•</span>
                                                        <span class="flex items-center gap-1"><i data-lucide="activity" class="w-3 h-3"></i> ${player.fitness || 100}% Fit</span>
                                                    </div>
                                                </div>
                                            </div>
                                            
                                            <div class="grid grid-cols-2 gap-x-4 gap-y-2 text-sm">
                                                 <div class="flex justify-between items-center border-b border-gray-100 pb-1">
                                                    <span class="text-gray-500">PAC</span>
                                                    <span class="font-bold ${stats.pace > 80 ? 'text-green-600' : 'text-gray-800'}">${stats.pace}</span>
                                                 </div>
                                                 <div class="flex justify-between items-center border-b border-gray-100 pb-1">
                                                    <span class="text-gray-500">DRI</span>
                                                    <span class="font-bold ${stats.dribbling > 80 ? 'text-green-600' : 'text-gray-800'}">${stats.dribbling}</span>
                                                 </div>
                                                 <div class="flex justify-between items-center border-b border-gray-100 pb-1">
                                                    <span class="text-gray-500">SHO</span>
                                                    <span class="font-bold ${stats.shooting > 80 ? 'text-green-600' : 'text-gray-800'}">${stats.shooting}</span>
                                                 </div>
                                                 <div class="flex justify-between items-center border-b border-gray-100 pb-1">
                                                    <span class="text-gray-500">DEF</span>
                                                    <span class="font-bold ${stats.defending > 80 ? 'text-green-600' : 'text-gray-800'}">${stats.defending}</span>
                                                 </div>
                                                 <div class="flex justify-between items-center pb-1">
                                                    <span class="text-gray-500">PAS</span>
                                                    <span class="font-bold ${stats.passing > 80 ? 'text-green-600' : 'text-gray-800'}">${stats.passing}</span>
                                                 </div>
                                                 <div class="flex justify-between items-center pb-1">
                                                    <span class="text-gray-500">PHY</span>
                                                    <span class="font-bold ${stats.physical > 80 ? 'text-green-600' : 'text-gray-800'}">${stats.physical}</span>
                                                 </div>
                                            </div>
                                            
                                            <div class="mt-3 pt-2 border-t border-gray-100 text-center">
                                                <span class="text-xs text-green-600 font-medium flex items-center justify-center gap-1">
                                                    <i data-lucide="check" class="w-3 h-3"></i> Added to Substitutes
                                                </span>
                                            </div>
                                        `;
                                                document.getElementById('reward-content').appendChild(playerCard);

                                                // Initialize icons
                                                if (typeof lucide !== 'undefined') {
                                                    lucide.createIcons();
                                                }
                                            }

                                            // Initialize lucide icons for the new element
                                            if (typeof lucide !== 'undefined') {
                                                lucide.createIcons();
                                            }
                                        } else {
                                            console.error('Failed to apply reward:', data.message);

                                            // Show error message
                                            const errorDiv = document.createElement('div');
                                            errorDiv.className = 'mt-3 p-2 bg-red-100 text-red-800 rounded-lg text-sm';
                                            errorDiv.innerHTML = `
                                        <i data-lucide="x-circle" class="w-4 h-4 inline mr-1"></i>
                                        Failed to apply reward. Please try again.
                                    `;
                                            document.getElementById('reward-content').appendChild(errorDiv);

                                            // Initialize lucide icons for the new element
                                            if (typeof lucide !== 'undefined') {
                                                lucide.createIcons();
                                            }
                                        }
                                    })
                                    .catch(error => {
                                        console.error('Error applying reward:', error);

                                        // Remove processing indicator
                                        const processingIndicator = document.getElementById('processing-indicator');
                                        if (processingIndicator) {
                                            processingIndicator.remove();
                                        }

                                        // Show error message
                                        const errorDiv = document.createElement('div');
                                        errorDiv.className = 'mt-3 p-2 bg-red-100 text-red-800 rounded-lg text-sm';
                                        errorDiv.innerHTML = `
                                    <i data-lucide="wifi-off" class="w-4 h-4 inline mr-1"></i>
                                    Connection error. Reward may not have been applied.
                                `;
                                        document.getElementById('reward-content').appendChild(errorDiv);

                                        // Initialize lucide icons for the new element
                                        if (typeof lucide !== 'undefined') {
                                            lucide.createIcons();
                                        }
                                    });

                                console.log('Reward selected:', selectedReward);
                                console.log('All box contents:', boxRewards);
                            }, 1000);

                        }, 200);
                    }, 800);
                });
            });
        <?php endif; ?>
    </script>

    <?php
    endContent('Match Result');
}

function displayTeamLineup($team_data, $side, $league_roster = null)
{
    // If league_roster is provided, use it instead of team_data
    if ($league_roster) {
    ?>
        <div class="space-y-4">
            <!-- Formation -->
            <div class="text-center">
                <span class="bg-gray-100 text-gray-700 px-3 py-1 rounded-full text-sm font-medium">
                    League Team Roster
                </span>
            </div>

            <!-- Starting XI (first 11 players) -->
            <div>
                <h4 class="font-semibold text-gray-900 mb-3 flex items-center gap-2">
                    <i data-lucide="users" class="w-4 h-4"></i>
                    Starting XI
                </h4>
                <div class="space-y-2">
                    <?php if (!empty($league_roster)): ?>
                        <?php foreach (array_slice($league_roster, 0, 11) as $index => $player): ?>
                            <?php if ($player): ?>
                                <div class="flex items-center gap-3 p-2 bg-gray-50 rounded-lg cursor-pointer hover:bg-blue-50 transition-colors" onclick="showPlayerInfo(<?php echo htmlspecialchars(json_encode($player)); ?>)">
                                    <div class="w-8 h-8 bg-blue-600 rounded-full flex items-center justify-center text-white text-sm font-bold">
                                        <?php echo $index + 1; ?>
                                    </div>
                                    <div class="flex-1">
                                        <div class="font-medium"><?php echo htmlspecialchars($player['name']); ?></div>
                                        <div class="text-sm text-gray-600"><?php echo htmlspecialchars($player['position']); ?></div>
                                    </div>
                                    <div class="text-right">
                                        <div class="text-sm font-medium"><?php echo $player['rating']; ?></div>
                                        <div class="text-xs text-gray-500">Rating</div>
                                    </div>
                                </div>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p class="text-gray-500 text-center py-4">No roster data available</p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Substitutes (first 5 players from bench) -->
            <?php if (count($league_roster) > 11): ?>
                <div>
                    <h4 class="font-semibold text-gray-900 mb-3 flex items-center gap-2">
                        <i data-lucide="user-plus" class="w-4 h-4"></i>
                        Substitutes
                    </h4>
                    <div class="space-y-2">
                        <?php foreach (array_slice($league_roster, 11, 5) as $player): ?>
                            <?php if ($player): ?>
                                <div class="flex items-center gap-3 p-2 bg-yellow-50 rounded-lg cursor-pointer hover:bg-yellow-100 transition-colors" onclick="showPlayerInfo(<?php echo htmlspecialchars(json_encode($player)); ?>)">
                                    <div class="w-6 h-6 bg-yellow-500 rounded-full flex items-center justify-center text-white text-xs font-bold">
                                        S
                                    </div>
                                    <div class="flex-1">
                                        <div class="font-medium text-sm"><?php echo htmlspecialchars($player['name']); ?></div>
                                        <div class="text-xs text-gray-600"><?php echo htmlspecialchars($player['position']); ?></div>
                                    </div>
                                    <div class="text-right">
                                        <div class="text-xs font-medium"><?php echo $player['rating']; ?></div>
                                    </div>
                                </div>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    <?php
        return;
    }

    if (!$team_data) {
        // AI team - generate basic lineup
        echo '<div class="text-center text-gray-500 py-8">';
        echo '<i data-lucide="users" class="w-12 h-12 mx-auto mb-3 text-gray-400"></i>';
        echo '<p>AI Team Lineup</p>';
        echo '<p class="text-sm">Formation: 4-4-2</p>';
        echo '</div>';
        return;
    }

    $team = json_decode($team_data['team'], true) ?? [];
    $substitutes = json_decode($team_data['substitutes'], true) ?? [];
    $formation = $team_data['formation'] ?? '4-4-2';

    // Get formation roles for displaying positions
    $formationRoles = FORMATIONS[$formation]['roles'] ?? FORMATIONS['4-4-2']['roles'];

    ?>
    <div class="space-y-4">
        <!-- Formation -->
        <div class="text-center">
            <span class="bg-gray-100 text-gray-700 px-3 py-1 rounded-full text-sm font-medium">
                Formation: <?php echo htmlspecialchars($formation); ?>
            </span>
        </div>

        <!-- Starting XI -->
        <div>
            <h4 class="font-semibold text-gray-900 mb-3 flex items-center gap-2">
                <i data-lucide="users" class="w-4 h-4"></i>
                Starting XI
            </h4>
            <div class="space-y-2">
                <?php if (!empty($team)): ?>
                    <?php foreach ($team as $index => $player): ?>
                        <?php if ($player): ?>
                            <?php
                            // Get the formation role for this position
                            $formationRole = $formationRoles[$index] ?? $player['position'];
                            ?>
                            <div class="flex items-center gap-3 p-2 bg-gray-50 rounded-lg cursor-pointer hover:bg-blue-50 transition-colors" onclick="showPlayerInfo(<?php echo htmlspecialchars(json_encode($player)); ?>)">
                                <div class="w-8 h-8 bg-blue-600 rounded-full flex items-center justify-center text-white text-sm font-bold">
                                    <?php echo $index + 1; ?>
                                </div>
                                <div class="flex-1">
                                    <div class="font-medium"><?php echo htmlspecialchars($player['name']); ?></div>
                                    <div class="text-sm text-gray-600"><?php echo htmlspecialchars($formationRole); ?></div>
                                </div>
                                <div class="text-right">
                                    <div class="text-sm font-medium"><?php echo $player['rating']; ?></div>
                                    <div class="text-xs text-gray-500">Rating</div>
                                </div>
                            </div>
                        <?php endif; ?>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p class="text-gray-500 text-center py-4">No starting lineup set</p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Substitutes -->
        <?php if (!empty($substitutes)): ?>
            <div>
                <h4 class="font-semibold text-gray-900 mb-3 flex items-center gap-2">
                    <i data-lucide="user-plus" class="w-4 h-4"></i>
                    Substitutes
                </h4>
                <div class="space-y-2">
                    <?php foreach (array_slice($substitutes, 0, 5) as $player): ?>
                        <?php if ($player): ?>
                            <div class="flex items-center gap-3 p-2 bg-yellow-50 rounded-lg cursor-pointer hover:bg-yellow-100 transition-colors" onclick="showPlayerInfo(<?php echo htmlspecialchars(json_encode($player)); ?>)">
                                <div class="w-6 h-6 bg-yellow-500 rounded-full flex items-center justify-center text-white text-xs font-bold">
                                    S
                                </div>
                                <div class="flex-1">
                                    <div class="font-medium text-sm"><?php echo htmlspecialchars($player['name']); ?></div>
                                    <div class="text-xs text-gray-600"><?php echo htmlspecialchars($player['position']); ?></div>
                                </div>
                                <div class="text-right">
                                    <div class="text-xs font-medium"><?php echo $player['rating']; ?></div>
                                </div>
                            </div>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
<?php
}

function handleClubChallenge($opponent_id)
{
    // Challenge system configuration
    define('CHALLENGE_BASE_COST', 5000000); // €5M base cost
    define('WIN_REWARD_PERCENTAGE', 1.5); // 150% of challenge cost (50% profit + cost back)

    try {
        $db = getDbConnection();

        // Get user's team data
        $stmt = $db->prepare('SELECT u.name, c.club_name, c.formation, c.team, c.budget FROM users u LEFT JOIN user_club c ON c.user_uuid = u.uuid WHERE u.id = :id');
        $stmt->bindValue(':id', $_SESSION['user_id']);
        $result = $stmt->execute();
        $user_data = $result->fetchArray(SQLITE3_ASSOC);

        // Get opponent's team data
        $stmt = $db->prepare('SELECT u.name, c.club_name, c.formation, c.team, c.budget FROM users u LEFT JOIN user_club c ON c.user_uuid = u.uuid WHERE u.id = :opponent_id AND u.id != :user_id');
        $stmt->bindValue(':opponent_id', $opponent_id);
        $stmt->bindValue(':user_id', $_SESSION['user_id']);
        $result = $stmt->execute();
        $opponent_data = $result->fetchArray(SQLITE3_ASSOC);

        if (!$user_data || !$opponent_data) {
            header('Location: clubs.php');
            exit;
        }

        // Check if challenge was properly initiated
        if (!isset($_SESSION['active_challenge']) || $_SESSION['active_challenge']['opponent_id'] != $opponent_id) {
            $_SESSION['challenge_error'] = 'Invalid challenge session. Please initiate the challenge again.';
            header('Location: clubs.php');
            exit;
        }

        // Get challenge data from session
        $challenge_cost = $_SESSION['active_challenge']['challenge_cost'];
        $potential_reward = $challenge_cost * 1.5; // 150% of challenge cost

        // Validate teams (without deducting budget again)
        $user_team = json_decode($user_data['team'] ?? '[]', true);
        $user_player_count = count(array_filter($user_team, fn($p) => $p !== null));

        if ($user_player_count < 11) {
            $_SESSION['challenge_error'] = 'You need a complete team (11 players) to challenge other clubs!';
            header('Location: clubs.php');
            exit;
        }

        $opponent_team = json_decode($opponent_data['team'] ?? '[]', true);
        $opponent_player_count = count(array_filter($opponent_team, fn($p) => $p !== null));
        if ($opponent_player_count < 11) {
            $_SESSION['challenge_error'] = 'This club doesn\'t have a complete team and cannot be challenged.';
            header('Location: clubs.php');
            exit;
        }

        // Calculate team values
        $user_team_value = calculateTeamValue($user_team);
        $opponent_team_value = calculateTeamValue($opponent_team);

        // Simulate the match
        $match_result = simulateClubMatch($user_team, $opponent_team, $user_team_value, $opponent_team_value);

        // Apply match injuries to user team
        if (!empty($match_result['injuries'])) {
            $user_team = applyClubMatchInjuries($user_team, $match_result['injuries']);

            // Update user team in database with injuries
            $stmt = $db->prepare('UPDATE user_club SET team = :team WHERE user_uuid = (SELECT uuid FROM users WHERE id = :user_id)');
            $stmt->bindValue(':team', json_encode($user_team));
            $stmt->bindValue(':user_id', $_SESSION['user_id']);
            $stmt->execute();
        }

        // Process match result and calculate rewards (but don't apply yet)
        $financial_result = calculateMatchRewards($_SESSION['user_id'], $match_result, $challenge_cost, $potential_reward);

        // Store pending reward in session for approval
        $_SESSION['pending_reward'] = [
            'amount' => $financial_result['earnings'],
            'challenge_cost' => $challenge_cost,
            'match_result' => $match_result['result'],
            'opponent_name' => $opponent_data['club_name'],
            'financial_details' => $financial_result
        ];

        // Clear the active challenge session since match is now set up
        unset($_SESSION['active_challenge']);

        $db->close();

        // Display club challenge result page
        displayClubChallengeResult($match_result, $user_data, $opponent_data, $financial_result);
    } catch (Exception $e) {
        header('Location: clubs.php');
        exit;
    }
}

function displayClubChallengeResult($match_result, $user_data, $opponent_data, $financial_result)
{
    startContent();
?>

    <div class="container mx-auto py-6">
        <div class="max-w-4xl mx-auto">
            <!-- Match Result Header -->
            <div class="bg-white rounded-lg shadow-lg border border-gray-200 overflow-hidden mb-6">
                <div class="bg-gradient-to-r from-purple-500 to-purple-600 text-white p-6">
                    <div class="text-center">
                        <h1 class="text-3xl font-bold mb-4">Club Challenge Result</h1>
                        <div class="grid grid-cols-3 gap-4 items-center text-purple-100 font-medium">
                            <div class="text-right truncate">
                                <?php echo htmlspecialchars($user_data['club_name']); ?>
                            </div>
                            <div class="text-center text-sm uppercase tracking-wider text-purple-200">
                                VS
                            </div>
                            <div class="text-left truncate">
                                <?php echo htmlspecialchars($opponent_data['club_name']); ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Match Result Display -->
            <div class="bg-white rounded-lg shadow border border-gray-200 p-6 mb-6">
                <div class="text-center">
                    <div class="text-6xl font-bold mb-4 <?php
                                                        echo $match_result['result'] === 'win' ? 'text-green-600' : ($match_result['result'] === 'draw' ? 'text-yellow-600' : 'text-red-600');
                                                        ?>">
                        <?php echo $match_result['user_score']; ?> - <?php echo $match_result['opponent_score']; ?>
                    </div>
                    <div class="text-2xl font-bold mb-2 <?php
                                                        echo $match_result['result'] === 'win' ? 'text-green-600' : ($match_result['result'] === 'draw' ? 'text-yellow-600' : 'text-red-600');
                                                        ?>">
                        <?php
                        echo $match_result['result'] === 'win' ? 'VICTORY!' : ($match_result['result'] === 'draw' ? 'DRAW!' : 'DEFEAT!');
                        ?>
                    </div>
                </div>
            </div>

            <!-- Continue to reward approval -->
            <div class="text-center">
                <a href="clubs.php" class="bg-blue-600 text-white px-6 py-3 rounded-lg hover:bg-blue-700 font-medium">
                    Continue
                </a>
            </div>
        </div>
    </div>

<?php
    endContent('Club Challenge Result');
}

// Challenge validation functions and utilities
function calculateMatchRewards($user_id, $match_result, $challenge_cost, $potential_reward)
{
    $earnings = 0;
    $details = [];

    if ($match_result['result'] === 'win') {
        $earnings = $potential_reward;
        $details[] = "Victory reward: €" . number_format($potential_reward);
    } elseif ($match_result['result'] === 'draw') {
        $earnings = $challenge_cost; // Get challenge cost back
        $details[] = "Draw - challenge cost returned: €" . number_format($challenge_cost);
    } else {
        $earnings = 0;
        $details[] = "Defeat - no reward";
    }

    return [
        'earnings' => $earnings,
        'details' => $details
    ];
}

function calculateTeamValue($team)
{
    $total_value = 0;
    foreach ($team as $player) {
        if ($player && isset($player['value'])) {
            $total_value += $player['value'];
        }
    }
    return $total_value;
}

// Simulate match between two teams (for club challenges)
function simulateClubMatch($user_team, $opponent_team, $user_team_value, $opponent_team_value)
{
    // Calculate team strengths based on multiple factors
    $user_strength = calculateClubTeamStrength($user_team, $user_team_value);
    $opponent_strength = calculateClubTeamStrength($opponent_team, $opponent_team_value);

    // Add some randomness to make matches unpredictable
    $user_performance = $user_strength * (0.7 + (mt_rand(0, 60) / 100)); // 70-130% of strength
    $opponent_performance = $opponent_strength * (0.7 + (mt_rand(0, 60) / 100));

    // Generate scores based on performance
    $user_score = max(0, round(($user_performance / 100) * (mt_rand(0, 4) + mt_rand(0, 2))));
    $opponent_score = max(0, round(($opponent_performance / 100) * (mt_rand(0, 4) + mt_rand(0, 2))));

    // Determine result
    $result = 'draw';
    if ($user_score > $opponent_score) {
        $result = 'win';
    } elseif ($user_score < $opponent_score) {
        $result = 'loss';
    }

    // Generate potential injuries (low chance)
    $injuries = [];
    if (mt_rand(1, 100) <= 15) { // 15% chance of injury
        $injured_player_index = mt_rand(0, count($user_team) - 1);
        if ($user_team[$injured_player_index]) {
            $injuries[] = $injured_player_index;
        }
    }

    return [
        'user_score' => $user_score,
        'opponent_score' => $opponent_score,
        'result' => $result,
        'user_performance' => round($user_performance, 1),
        'opponent_performance' => round($opponent_performance, 1),
        'injuries' => $injuries
    ];
}

function calculateClubTeamStrength($team, $team_value)
{
    if (empty($team)) return 50;

    $total_rating = 0;
    $player_count = 0;

    foreach ($team as $player) {
        if ($player && isset($player['rating'])) {
            $total_rating += $player['rating'];
            $player_count++;
        }
    }

    if ($player_count === 0) return 50;

    $average_rating = $total_rating / $player_count;

    // Base strength from average rating (50-100 range)
    $strength = min(100, max(50, $average_rating));

    // Slight adjustment based on team value
    $value_factor = min(10, ($team_value / 100000000) * 5); // Max 10 point bonus for very expensive teams
    $strength += $value_factor;

    return min(100, $strength);
}

function applyClubMatchInjuries($team, $injury_indices)
{
    foreach ($injury_indices as $index) {
        if (isset($team[$index]) && $team[$index]) {
            // Reduce player fitness/rating temporarily
            $team[$index]['fitness'] = max(50, ($team[$index]['fitness'] ?? 100) - mt_rand(10, 30));
            $team[$index]['injury_status'] = 'minor'; // Could be used for future injury system
        }
    }
    return $team;
}
?>
