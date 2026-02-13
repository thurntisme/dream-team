<?php
session_start();

require_once 'config/config.php';
require_once 'config/constants.php';
require_once 'partials/layout.php';
require_once 'includes/league_functions.php';

$displayTeamLineup = function ($team_data, $league_roster = null) {
    if ($league_roster) {
?>
        <div class="space-y-2">
            <?php foreach (array_slice($league_roster, 0, 11) as $index => $player): ?>
                <?php if ($player): ?>
                    <div class="flex items-center gap-3 p-2 bg-gray-50 rounded-lg">
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
        </div>
    <?php
        return;
    }
    if (!$team_data) {
        echo '<p class="text-gray-500">No team data available</p>';
        return;
    }
    $team = json_decode($team_data['team'] ?? '[]', true) ?? [];
    $formation = $team_data['formation'] ?? '4-4-2';
    $roles = FORMATIONS[$formation]['roles'] ?? FORMATIONS['4-4-2']['roles'];
    ?>
    <div class="space-y-2">
        <?php foreach ($team as $index => $player): ?>
            <?php if ($player): ?>
                <div class="flex items-center gap-3 p-2 bg-gray-50 rounded-lg">
                    <div class="w-8 h-8 bg-blue-600 rounded-full flex items-center justify-center text-white text-sm font-bold">
                        <?php echo $index + 1; ?>
                    </div>
                    <div class="flex-1">
                        <div class="font-medium"><?php echo htmlspecialchars($player['name']); ?></div>
                        <div class="text-sm text-gray-600"><?php echo htmlspecialchars($roles[$index] ?? $player['position']); ?></div>
                    </div>
                    <div class="text-right">
                        <div class="text-sm font-medium"><?php echo $player['rating'] ?? '-'; ?></div>
                        <div class="text-xs text-gray-500">Rating</div>
                    </div>
                </div>
            <?php endif; ?>
        <?php endforeach; ?>
    </div>
<?php
};

$renderError = function ($message) {
    startContent();
?>
    <div class="container mx-auto py-10">
        <div class="max-w-xl mx-auto bg-white rounded-lg shadow-lg border border-red-200 overflow-hidden">
            <div class="bg-gradient-to-r from-red-500 to-red-600 text-white p-4">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 bg-white bg-opacity-20 rounded-lg flex items-center justify-center">
                        <i data-lucide="alert-triangle" class="w-5 h-5"></i>
                    </div>
                    <div>
                        <h3 class="text-lg font-bold">Unable to Load Match</h3>
                        <p class="text-red-100 text-sm">Please review the message below</p>
                    </div>
                </div>
            </div>
            <div class="p-6">
                <p class="text-red-700 font-medium"><?php echo htmlspecialchars($message); ?></p>
                <div class="mt-6">
                    <a href="league.php" class="inline-flex items-center gap-2 bg-gray-800 text-white px-4 py-2 rounded-lg hover:bg-gray-900 transition-colors">
                        <i data-lucide="arrow-left" class="w-4 h-4"></i>
                        Back to League
                    </a>
                </div>
            </div>
        </div>
    </div>
<?php
    endContent('Error');
};

$match_uuid = $_GET['uuid'] ?? ($_GET['match_uuid'] ?? null);
if (!$match_uuid) {
    $renderError('Missing match identifier.');
    return;
}

try {
    $db = getDbConnection();
    $user_uuid = $_SESSION['user_uuid'] ?? null;
    if (!$user_uuid) {
        header('Location: index.php');
        exit;
    }

    $stmtMatch = $db->prepare('
        SELECT lm.*, 
               ht.name as home_team_name, ht.user_uuid as home_user_uuid,
               at.name as away_team_name, at.user_uuid as away_user_uuid
        FROM league_matches lm
        JOIN league_teams ht ON lm.home_team_id = ht.id
        JOIN league_teams at ON lm.away_team_id = at.id
        WHERE lm.uuid = :match_uuid
    ');
    if ($stmtMatch === false) {
        $renderError('Failed to prepare match query.');
        return;
    }
    $stmtMatch->bindValue(':match_uuid', $match_uuid);
    // No user filter to allow viewing any match by UUID
    $result = $stmtMatch->execute();
    if ($result === false) {
        $renderError('Failed to load match details.');
        return;
    }
    $match = $result->fetchArray(SQLITE3_ASSOC);
    if (!$match) {
        $renderError('Match not found or not available to play.');
        return;
    }

    $is_home = ($match['home_user_uuid'] === $user_uuid);
    $opponent_user_uuid = $is_home ? $match['away_user_uuid'] : $match['home_user_uuid'];
    $is_user_match = ($match['home_user_uuid'] === $user_uuid) || ($match['away_user_uuid'] === $user_uuid);
    $home_roster = null;
    $away_roster = null;
    // Resolve numeric user_id for reward session tracking
    $user_id_resolved = null;
    $stmtUserId = $db->prepare('SELECT id FROM users WHERE uuid = :uuid');
    if ($stmtUserId) {
        $stmtUserId->bindValue(':uuid', $user_uuid);
        $resUserId = $stmtUserId->execute();
        $rowUserId = $resUserId ? $resUserId->fetchArray(SQLITE3_ASSOC) : null;
        $user_id_resolved = (int)($rowUserId['id'] ?? 0);
    }
    if (!empty($match['home_team_id'])) {
        $stmtHomeRoster = $db->prepare('SELECT player_data FROM league_team_rosters WHERE league_team_id = :id AND season = :season');
        if ($stmtHomeRoster) {
            $stmtHomeRoster->bindValue(':id', (int)$match['home_team_id']);
            $stmtHomeRoster->bindValue(':season', $match['season']);
            $resHomeRoster = $stmtHomeRoster->execute();
            $rowHomeRoster = $resHomeRoster ? $resHomeRoster->fetchArray(SQLITE3_ASSOC) : null;
            if ($rowHomeRoster) {
                $home_roster = json_decode($rowHomeRoster['player_data'], true);
            }
        }
    }
    if (!empty($match['away_team_id'])) {
        $stmtAwayRoster = $db->prepare('SELECT player_data FROM league_team_rosters WHERE league_team_id = :id AND season = :season');
        if ($stmtAwayRoster) {
            $stmtAwayRoster->bindValue(':id', (int)$match['away_team_id']);
            $stmtAwayRoster->bindValue(':season', $match['season']);
            $resAwayRoster = $stmtAwayRoster->execute();
            $rowAwayRoster = $resAwayRoster ? $resAwayRoster->fetchArray(SQLITE3_ASSOC) : null;
            if ($rowAwayRoster) {
                $away_roster = json_decode($rowAwayRoster['player_data'], true);
            }
        }
    }

    $stmtUser = $db->prepare('SELECT u.name, c.club_name, c.formation, c.team, c.budget FROM users u LEFT JOIN user_club c ON c.user_uuid = u.uuid WHERE u.uuid = :uuid');
    if ($stmtUser) {
        $stmtUser->bindValue(':uuid', $user_uuid);
        $resUser = $stmtUser->execute();
        $user_data = $resUser ? $resUser->fetchArray(SQLITE3_ASSOC) : null;
    } else {
        $user_data = null;
    }

    $opponent_roster = null;
    $stmtOppTeam = $db->prepare('SELECT id FROM league_teams WHERE season = :season AND user_uuid = :uuid');
    $rowTeam = null;
    if ($stmtOppTeam) {
        $stmtOppTeam->bindValue(':season', $match['season']);
        $stmtOppTeam->bindValue(':uuid', $opponent_user_uuid);
        $resTeam = $stmtOppTeam->execute();
        $rowTeam = $resTeam ? $resTeam->fetchArray(SQLITE3_ASSOC) : null;
    }
    if ($rowTeam) {
        $team_id = (int)$rowTeam['id'];
        $stmtOppRoster = $db->prepare('SELECT player_data FROM league_team_rosters WHERE league_team_id = :id AND season = :season');
        $rowRoster = null;
        if ($stmtOppRoster) {
            $stmtOppRoster->bindValue(':id', $team_id);
            $stmtOppRoster->bindValue(':season', $match['season']);
            $resRoster = $stmtOppRoster->execute();
            $rowRoster = $resRoster ? $resRoster->fetchArray(SQLITE3_ASSOC) : null;
        }
        if ($rowRoster) {
            $opponent_roster = json_decode($rowRoster['player_data'], true);
        }
    }

    if (($match['status'] ?? '') === 'completed') {
        $user_score = $is_home ? (int)($match['home_score'] ?? 0) : (int)($match['away_score'] ?? 0);
        $opponent_score = $is_home ? (int)($match['away_score'] ?? 0) : (int)($match['home_score'] ?? 0);
        $match_result = 'draw';
        if ($user_score > $opponent_score) {
            $match_result = 'win';
        } elseif ($user_score < $opponent_score) {
            $match_result = 'loss';
        }
        $rewards = calculateLeagueMatchRewards($match_result, $user_score, $opponent_score, $is_home);
        $fan_change = 0;
        $gameweek_results = $_SESSION['gameweek_results'] ?? null;
        if ($gameweek_results && isset($gameweek_results['fan_change_info'])) {
            $fan_change = (int)$gameweek_results['fan_change_info']['fan_change'];
            $rewards['budget_earned'] = $gameweek_results['budget_earned'];
            $rewards['breakdown'] = $gameweek_results['budget_breakdown'];
        } else {
            $stmtId = $db->prepare('SELECT id FROM users WHERE uuid = :uuid');
            if ($stmtId) {
                $stmtId->bindValue(':uuid', $user_uuid);
                $resId = $stmtId->execute();
                $rowId = $resId ? $resId->fetchArray(SQLITE3_ASSOC) : null;
                $user_id_resolved = (int)($rowId['id'] ?? 0);
                $fan_breakdown = getFanRevenueBreakdown($db, $user_uuid, $is_home, 100);
                foreach ($fan_breakdown as $item) {
                    $rewards['breakdown'][] = $item;
                }
                $total_budget = 0;
                foreach ($rewards['breakdown'] as $item) {
                    $total_budget += $item['amount'];
                }
                $rewards['budget_earned'] = $total_budget;
                $fan_change = 0;
                if ($match_result === 'win') {
                    $fan_change = 125;
                } elseif ($match_result === 'draw') {
                    $fan_change = 12;
                } else {
                    $fan_change = -62;
                }
                $goal_diff = $user_score - $opponent_score;
                $fan_change += $goal_diff * 10;
            }
        }
        $stmtInfo = $db->prepare('SELECT c.fans, s.capacity, s.level FROM user_club c LEFT JOIN stadiums s ON s.user_uuid = c.user_uuid WHERE c.user_uuid = :uuid');
        if ($stmtInfo) {
            $stmtInfo->bindValue(':uuid', $user_uuid);
            $resInfo = $stmtInfo->execute();
            $user_extra = $resInfo ? $resInfo->fetchArray(SQLITE3_ASSOC) : null;
            if ($user_extra) {
                $user_data = array_merge($user_data ?? [], $user_extra);
            }
        }
    }
    $db->close();

    startContent();
?>
    <div class="container mx-auto py-6">
        <div class="bg-white rounded-lg shadow-lg border border-gray-200 overflow-hidden mb-6">
            <div class="bg-gradient-to-r from-blue-500 to-blue-600 text-white p-4">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 bg-white bg-opacity-20 rounded-lg flex items-center justify-center">
                            <i data-lucide="calendar" class="w-5 h-5"></i>
                        </div>
                        <div>
                            <h3 class="text-lg font-bold">Elite League Match</h3>
                            <p class="text-blue-100 text-sm">
                                Gameweek <?php echo $match['gameweek']; ?> •
                                <?php echo date('l, M j, Y', strtotime($match['match_date'])); ?>
                            </p>
                        </div>
                    </div>
                    <div class="bg-white bg-opacity-20 px-3 py-1 rounded-full text-sm font-medium">
                        <i data-lucide="clock" class="w-4 h-4 inline mr-1"></i>
                        Scheduled
                    </div>
                </div>
            </div>

            <div class="p-6">
                <div class="flex items-center justify-between">
                    <div class="flex-1 text-center">
                        <div class="w-16 h-16 mx-auto mb-3 <?php echo $is_home ? 'bg-blue-600' : 'bg-gray-500'; ?> rounded-full flex items-center justify-center shadow-md">
                            <i data-lucide="<?php echo $is_home ? 'user' : 'users'; ?>" class="w-6 h-6 text-white"></i>
                        </div>
                        <h4 class="font-bold text-lg <?php echo $is_home ? 'text-blue-600' : 'text-gray-700'; ?> mb-1">
                            <?php echo htmlspecialchars($match['home_team_name']); ?>
                        </h4>
                        <div class="flex items-center justify-center gap-1 text-sm">
                            <i data-lucide="home" class="w-4 h-4 text-green-600"></i>
                            <span class="text-green-600 font-medium">HOME</span>
                        </div>
                    </div>

                    <div class="flex-shrink-0 mx-8">
                        <div class="w-16 h-16 bg-gray-100 border-2 border-gray-300 rounded-full flex items-center justify-center">
                            <span class="text-gray-600 font-black text-xl">VS</span>
                        </div>
                    </div>

                    <div class="flex-1 text-center">
                        <div class="w-16 h-16 mx-auto mb-3 <?php echo !$is_home ? 'bg-blue-600' : 'bg-gray-500'; ?> rounded-full flex items-center justify-center shadow-md">
                            <i data-lucide="<?php echo !$is_home ? 'user' : 'users'; ?>" class="w-6 h-6 text-white"></i>
                        </div>
                        <h4 class="font-bold text-lg <?php echo !$is_home ? 'text-blue-600' : 'text-gray-700'; ?> mb-1">
                            <?php echo htmlspecialchars($match['away_team_name']); ?>
                        </h4>
                        <div class="flex items-center justify-center gap-1 text-sm">
                            <i data-lucide="plane" class="w-4 h-4 text-gray-600"></i>
                            <span class="text-gray-600 font-medium">AWAY</span>
                        </div>
                    </div>
                </div>

                <?php if (($match['status'] ?? '') === 'completed'): ?>
                    <div id="result" class="bg-white rounded-lg shadow border border-gray-200 overflow-hidden my-6">
                        <div class="bg-gray-50 border-b border-gray-200 p-4">
                            <div class="flex justify-center items-center gap-3">
                                <div class="w-10 h-10 bg-gray-700 rounded-full flex items-center justify-center">
                                    <i data-lucide="trophy" class="w-5 h-5 text-white"></i>
                                </div>
                                <div>
                                    <h3 class="font-bold text-lg text-gray-800">Final Score</h3>
                                    <p class="text-sm text-gray-600">Gameweek <?php echo $match['gameweek']; ?></p>
                                </div>
                            </div>
                        </div>
                        <div class="p-6">
                            <div class="flex items-center justify-center gap-8">
                                <div class="text-center">
                                    <div class="text-lg font-semibold text-gray-800"><?php echo htmlspecialchars($match['home_team_name']); ?></div>
                                    <div class="text-4xl font-black text-gray-900"><?php echo (int)($match['home_score'] ?? 0); ?></div>
                                </div>
                                <div class="text-3xl font-black text-gray-500">-</div>
                                <div class="text-center">
                                    <div class="text-lg font-semibold text-gray-800"><?php echo htmlspecialchars($match['away_team_name']); ?></div>
                                    <div class="text-4xl font-black text-gray-900"><?php echo (int)($match['away_score'] ?? 0); ?></div>
                                </div>
                            </div>
                        </div>
                    </div>
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
                                <div>
                                    <h4 class="font-semibold text-gray-900 mb-3 flex items-center gap-2">
                                        <i data-lucide="banknote" class="w-4 h-4 text-green-600"></i>
                                        Budget Earned
                                    </h4>
                                    <div class="space-y-2">
                                        <?php foreach (($rewards['breakdown'] ?? []) as $reward): ?>
                                            <div class="flex justify-between items-center p-2 bg-gray-50 rounded">
                                                <span class="text-sm"><?php echo htmlspecialchars($reward['description']); ?></span>
                                                <span class="font-medium text-green-600">+€<?php echo number_format($reward['amount']); ?></span>
                                            </div>
                                        <?php endforeach; ?>
                                        <div class="border-t pt-2 mt-3">
                                            <div class="flex justify-between items-center font-bold mb-3">
                                                <span>Total Budget Earned:</span>
                                                <span class="text-green-600 text-lg">+€<?php echo number_format($rewards['budget_earned'] ?? 0); ?></span>
                                            </div>
                                            <div class="grid grid-cols-2 gap-3">
                                                <div class="bg-red-50 border border-red-200 rounded-lg p-3">
                                                    <div class="text-xs text-red-600 font-semibold mb-1">BEFORE MATCH</div>
                                                    <div class="text-lg font-bold text-red-700">
                                                        €<?php echo number_format(($user_data['budget'] ?? 0) - ($rewards['budget_earned'] ?? 0)); ?>
                                                    </div>
                                                </div>
                                                <div class="bg-green-50 border border-green-200 rounded-lg p-3">
                                                    <div class="text-xs text-green-600 font-semibold mb-1">AFTER MATCH</div>
                                                    <div class="text-lg font-bold text-green-700">
                                                        €<?php echo number_format($user_data['budget'] ?? 0); ?>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div>
                                    <h4 class="font-semibold text-gray-900 mb-3 flex items-center gap-2">
                                        <i data-lucide="users" class="w-4 h-4 text-blue-600"></i>
                                        Fan Support
                                    </h4>
                                    <div class="space-y-3">
                                        <div class="p-4 bg-blue-50 rounded-lg">
                                            <div class="flex items-center justify-between mb-2">
                                                <span class="text-sm text-gray-600">Fan Change:</span>
                                                <span class="font-bold <?php echo ($fan_change ?? 0) >= 0 ? 'text-green-600' : 'text-red-600'; ?>">
                                                    <?php echo ($fan_change ?? 0) >= 0 ? '+' : ''; ?><?php echo number_format($fan_change ?? 0); ?>
                                                </span>
                                            </div>
                                            <div class="flex items-center justify-between">
                                                <span class="text-sm text-gray-600">Current Fans:</span>
                                                <span class="font-medium text-blue-600"><?php echo number_format($user_data['fans'] ?? 5000); ?></span>
                                            </div>
                                        </div>
                                        <div class="text-xs text-gray-500">
                                            <?php if (($fan_change ?? 0) > 0): ?>
                                                <i data-lucide="trending-up" class="w-3 h-3 inline mr-1"></i>
                                                Great performance! Your fanbase is growing.
                                            <?php elseif (($fan_change ?? 0) < 0): ?>
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
                <?php endif; ?>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mt-6">
                    <div class="bg-white rounded-lg shadow border border-gray-200 overflow-hidden">
                        <div class="bg-green-50 border-b border-green-200 p-4">
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 <?php echo $is_home ? 'bg-blue-600' : 'bg-gray-500'; ?> rounded-full flex items-center justify-center">
                                    <i data-lucide="<?php echo $is_home ? 'user' : 'users'; ?>" class="w-5 h-5 text-white"></i>
                                </div>
                                <div>
                                    <h3 class="font-bold text-lg <?php echo $is_home ? 'text-blue-600' : 'text-gray-700'; ?>">
                                        <?php echo htmlspecialchars($match['home_team_name']); ?>
                                    </h3>
                                    <div class="text-sm">
                                        <span class="text-green-600 font-medium">HOME</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="p-4">
                            <?php $displayTeamLineup($is_home ? $user_data : null, $is_home ? null : $home_roster); ?>
                        </div>
                    </div>

                    <div class="bg-white rounded-lg shadow border border-gray-200 overflow-hidden">
                        <div class="bg-orange-50 border-b border-orange-200 p-4">
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 <?php echo !$is_home ? 'bg-blue-600' : 'bg-gray-500'; ?> rounded-full flex items-center justify-center">
                                    <i data-lucide="<?php echo !$is_home ? 'user' : 'users'; ?>" class="w-5 h-5 text-white"></i>
                                </div>
                                <div>
                                    <h3 class="font-bold text-lg <?php echo !$is_home ? 'text-blue-600' : 'text-gray-700'; ?>">
                                        <?php echo htmlspecialchars($match['away_team_name']); ?>
                                    </h3>
                                    <div class="text-sm">
                                        <span class="text-orange-600 font-medium">AWAY</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="p-4">
                            <?php $displayTeamLineup(!$is_home ? $user_data : null, !$is_home ? null : $away_roster); ?>
                        </div>
                    </div>
                </div>

                <?php if (($match['status'] ?? '') === 'completed'): ?>
                    <?php
                    $session_key = "mystery_box_claimed_{$match['uuid']}_{$user_uuid}";
                    $mystery_box_claimed = isset($_SESSION[$session_key]) && $_SESSION[$session_key] === true;
                    ?>
                    <div class="bg-white rounded-lg shadow border border-gray-200 overflow-hidden mt-6">
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
                                        data-box="<?php echo $i; ?>" <?php echo $mystery_box_claimed ? 'style=\"pointer-events: none;\"' : ''; ?>>
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
                    <div class="text-center mt-6">
                        <button id="approveBtn" class="mx-auto bg-blue-600 text-white px-8 py-3 rounded-lg hover:bg-blue-700 font-bold transition-colors inline-flex items-center gap-2 shadow-md">
                            <i data-lucide="check-circle" class="w-5 h-5"></i>
                            Approve
                        </button>
                        <script>
                            (function() {
                                const btn = document.getElementById('approveBtn');
                                if (!btn) return;
                                btn.addEventListener('click', function() {
                                    window.location.href = 'league.php?tab=standings';
                                });
                            })();
                        </script>
                    </div>
                    <script>
                        <?php if (!$mystery_box_claimed): ?>
                                (function() {
                                    let rewardOptions = null;

                                    async function ensureRewardOptions() {
                                        if (rewardOptions) return rewardOptions;

                                        try {
                                            const res = await fetch(
                                                'api/mystery_box_reward_api.php?match_uuid=<?= urlencode($match['uuid'] ?? ''); ?>', {
                                                    method: 'GET'
                                                }
                                            );

                                            const data = await res.json().catch(() => null);

                                            if (
                                                res.ok &&
                                                data &&
                                                data.success &&
                                                !data.claimed &&
                                                Array.isArray(data.options) &&
                                                data.options.length === 3
                                            ) {
                                                rewardOptions = data.options;
                                                return rewardOptions;
                                            }

                                            throw new Error('Failed to load reward options');
                                        } catch (e) {
                                            const errorDiv = document.createElement('div');
                                            errorDiv.className =
                                                'mt-3 p-2 bg-red-100 text-red-800 rounded-lg text-sm';
                                            errorDiv.innerHTML =
                                                '<i data-lucide="x-circle" class="w-4 h-4 inline mr-1"></i>Unable to load reward options.';

                                            const container =
                                                document.getElementById('reward-content') ||
                                                document.getElementById('reward-display');

                                            if (container) {
                                                container.classList.remove('hidden');
                                                container.appendChild(errorDiv);
                                                if (typeof lucide !== 'undefined') lucide.createIcons();
                                            }

                                            return null;
                                        }
                                    }

                                    document.querySelectorAll('.mystery-box').forEach(box => {
                                        box.addEventListener('click', async function() {

                                            // 🔒 Prevent double click
                                            if (this.dataset.locked) return;
                                            this.dataset.locked = "true";

                                            const options = await ensureRewardOptions();
                                            if (!options) return;

                                            const boxNumber = parseInt(this.dataset.box);
                                            const selectedReward = options[boxNumber - 1];

                                            if (!selectedReward) return;

                                            // Disable all boxes
                                            document.querySelectorAll('.mystery-box').forEach(b => {
                                                b.style.pointerEvents = 'none';
                                            });

                                            // Inject animations once
                                            if (!document.querySelector('#mystery-animations')) {
                                                const style = document.createElement('style');
                                                style.id = 'mystery-animations';
                                                style.textContent = `
                    @keyframes shake {
                        0%,100%{transform:translateX(0)}
                        25%{transform:translateX(-5px)}
                        75%{transform:translateX(5px)}
                    }
                    @keyframes pulse-glow {
                        0%,100%{box-shadow:0 0 20px rgba(255,215,0,.5)}
                        50%{box-shadow:0 0 30px rgba(255,215,0,.8)}
                    }
                    @keyframes flip-reveal {
                        0%{transform:rotateY(0)}
                        50%{transform:rotateY(90deg)}
                        100%{transform:rotateY(0)}
                    }
                    @keyframes fade-gray {
                        from{opacity:1}
                        to{opacity:.6}
                    }
                    @keyframes fade-in-up {
                        from{opacity:0;transform:translateY(20px)}
                        to{opacity:1;transform:translateY(0)}
                    }
                `;
                                                document.head.appendChild(style);
                                            }

                                            // Shake animation
                                            this.style.animation = 'shake 0.5s ease-in-out';

                                            setTimeout(() => {

                                                this.style.animation = 'pulse-glow 1s ease-in-out infinite';

                                                document.querySelectorAll('.mystery-box').forEach(b => {
                                                    if (parseInt(b.dataset.box) !== boxNumber) {
                                                        b.style.animation = 'fade-gray 0.5s ease-out forwards';
                                                    }
                                                });

                                                setTimeout(() => {

                                                    document.querySelectorAll('.mystery-box').forEach(b => {

                                                        const boxNum = parseInt(b.dataset.box);
                                                        const reward = options[boxNum - 1];
                                                        const boxContent = b.querySelector('.bg-gradient-to-br');

                                                        if (!boxContent) return;

                                                        b.style.animation = 'flip-reveal 0.8s ease-in-out';

                                                        setTimeout(() => {

                                                            if (boxNum === boxNumber) {
                                                                boxContent.innerHTML = `
                                    <div class="w-16 h-16 mx-auto mb-3 bg-white bg-opacity-30 rounded-full flex items-center justify-center text-2xl">
                                        ${reward.icon}
                                    </div>
                                    <div class="font-bold text-lg text-yellow-200">SELECTED!</div>
                                    <div class="text-sm">${reward.text}</div>
                                `;
                                                            } else {
                                                                boxContent.innerHTML = `
                                    <div class="w-16 h-16 mx-auto mb-3 bg-white bg-opacity-20 rounded-full flex items-center justify-center text-2xl">
                                        ${reward.icon}
                                    </div>
                                    <div class="text-sm opacity-75">${reward.text}</div>
                                `;
                                                                b.style.opacity = '0.6';
                                                            }

                                                        }, 400);
                                                    });

                                                    // Show main reward display
                                                    setTimeout(() => {

                                                        const rewardDisplay = document.getElementById('reward-display');
                                                        const rewardContent = document.getElementById('reward-content');

                                                        rewardContent.innerHTML = `
                            <div class="text-3xl mb-2">${selectedReward.icon}</div>
                            <div class="text-xl font-bold">${selectedReward.text}</div>
                            <div class="text-sm mt-2 opacity-90">
                                Check the other boxes to see what you could have won!
                            </div>
                        `;

                                                        rewardDisplay.classList.remove('hidden');
                                                        rewardDisplay.style.animation = 'fade-in-up 0.5s ease-out forwards';

                                                        // POST save reward
                                                        fetch('api/mystery_box_reward_api.php', {
                                                                method: 'POST',
                                                                headers: {
                                                                    'Content-Type': 'application/json'
                                                                },
                                                                body: JSON.stringify({
                                                                    reward: selectedReward,
                                                                    match_uuid: <?= json_encode($match['uuid'] ?? null); ?>
                                                                })
                                                            })
                                                            .then(res => {
                                                                if (!res.ok) throw new Error('Server error');
                                                                return res.json();
                                                            })
                                                            .then(data => {
                                                                if (!data.success) throw new Error(data.message);

                                                                const confirmationDiv = document.createElement('div');
                                                                confirmationDiv.className =
                                                                    'mt-3 p-2 bg-green-100 text-green-800 rounded-lg text-sm';
                                                                confirmationDiv.innerHTML =
                                                                    '<i data-lucide="check-circle" class="w-4 h-4 inline mr-1"></i>' +
                                                                    data.message;

                                                                rewardContent.appendChild(confirmationDiv);

                                                                if (typeof lucide !== 'undefined') {
                                                                    lucide.createIcons();
                                                                }
                                                            })
                                                            .catch(err => {
                                                                console.error('Reward save failed:', err);

                                                                const errorDiv = document.createElement('div');
                                                                errorDiv.className =
                                                                    'mt-3 p-2 bg-red-100 text-red-800 rounded-lg text-sm';
                                                                errorDiv.innerHTML =
                                                                    'Error saving reward. Please refresh.';

                                                                rewardContent.appendChild(errorDiv);
                                                            });

                                                    }, 1000);

                                                }, 800);

                                            }, 500);

                                        });
                                    });

                                })();

                        <?php endif; ?>
                    </script>
                <?php endif; ?>

                <div class="mt-6 text-center">
                    <?php if (($match['status'] ?? 'scheduled') === 'scheduled' && $is_user_match): ?>
                        <button id="simulateBtn" class="mx-auto bg-green-600 text-white px-6 py-2 rounded-lg hover:bg-green-700 font-medium transition-colors inline-flex items-center gap-2 shadow-md">
                            <i data-lucide="play" class="w-4 h-4"></i>
                            Simulate Match
                        </button>
                        <script>
                            (function() {
                                const btn = document.getElementById('simulateBtn');
                                if (!btn) return;
                                btn.addEventListener('click', async function() {
                                    if (btn.disabled) return;
                                    btn.disabled = true;
                                    btn.classList.add('opacity-50');
                                    btn.innerHTML = '<svg class="animate-spin h-4 w-4 text-white" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="white" stroke-width="4" fill="none"></circle><path class="opacity-75" fill="white" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path></svg><span>Simulating…</span>';
                                    try {
                                        const fd = new URLSearchParams();
                                        fd.set('simulate_match', '1');
                                        const res = await fetch('api/match_simulator_api.php', {
                                            method: 'POST',
                                            headers: {
                                                'Content-Type': 'application/x-www-form-urlencoded'
                                            },
                                            body: (function() {
                                                fd.set('uuid', '<?php echo urlencode($match_uuid); ?>');
                                                return fd.toString();
                                            })()
                                        });
                                        const data = await res.json().catch(() => null);
                                        console.log(res, data);
                                        if (res.ok && data && data.ok) {
                                            window.location.href = 'league_match.php?uuid=<?php echo urlencode($match_uuid); ?>';
                                            return;
                                        }
                                        alert('Simulation failed. Please try again.');
                                        btn.disabled = false;
                                        btn.classList.remove('opacity-50');
                                        btn.innerHTML = '<i data-lucide="play" class="w-4 h-4"></i><span>Simulate Match</span>';
                                    } catch (e) {
                                        alert('Simulation failed. Please try again.');
                                        btn.disabled = false;
                                        btn.classList.remove('opacity-50');
                                        btn.innerHTML = '<i data-lucide="play" class="w-4 h-4"></i><span>Simulate Match</span>';
                                    }
                                });
                            })();
                        </script>
                    <?php elseif (($match['status'] ?? 'scheduled') === 'scheduled' && !$is_user_match): ?>
                        <div class="inline-flex items-center gap-2 bg-gray-300 text-gray-700 px-6 py-2 rounded-lg font-medium shadow-md cursor-not-allowed">
                            <i data-lucide="lock" class="w-4 h-4"></i>
                            Not your match
                        </div>
                    <?php else: ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
<?php
    endContent('League Match');
} catch (Throwable $e) {
    $renderError('An error occurred while loading the match. ' . $e->getMessage());
}
