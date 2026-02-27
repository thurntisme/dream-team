<?php
session_start();

require_once 'config/config.php';
require_once 'config/constants.php';
require_once 'partials/layout.php';
require_once 'includes/league_functions.php';
require_once 'includes/club_functions.php';

function getFitnessColor($fitness)
{
    if ($fitness >= 80) {
        return 'bg-green-500';
    } elseif ($fitness >= 50) {
        return 'bg-yellow-500';
    } else {
        return 'bg-red-500';
    }
}

function getFormBadgeColor($form)
{
    if ($form >= 8.5)
        return 'bg-purple-100 text-purple-800 border border-purple-200';
    if ($form >= 7.5)
        return 'bg-green-100 text-green-800 border border-green-200';
    if ($form >= 6.5)
        return 'bg-blue-100 text-blue-800 border border-blue-200';
    if ($form >= 5.5)
        return 'bg-yellow-100 text-yellow-800 border border-yellow-200';
    if ($form >= 4)
        return 'bg-orange-100 text-orange-800 border border-orange-200';
    return 'bg-red-100 text-red-800 border border-red-200';
}

function getFormArrowIcon($form)
{
    if ($form >= 8)
        return '<i data-lucide="trending-up" class="w-3 h-3"></i>';
    if ($form >= 6.5)
        return '<i data-lucide="arrow-up" class="w-3 h-3"></i>';
    if ($form >= 5.5)
        return '<i data-lucide="minus" class="w-3 h-3"></i>';
    if ($form >= 4)
        return '<i data-lucide="arrow-down" class="w-3 h-3"></i>';
    return '<i data-lucide="trending-down" class="w-3 h-3"></i>';
}

$displayTeamLineup = function ($team_data, $league_roster = null, $is_home = false) {
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
            <?php if (count($league_roster) > 11): ?>
                <div class="my-3 border-t border-gray-200"></div>
                <?php foreach (array_slice($league_roster, 11, 5) as $index => $player): ?>
                    <?php if ($player): ?>
                        <div class="flex items-center gap-3 p-2 bg-gray-50 rounded-lg">
                            <div class="w-8 h-8 bg-gray-500 rounded-full flex items-center justify-center text-white text-sm font-bold">
                                <?php echo 12 + $index; ?>
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
            <?php endif; ?>
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
        <?php foreach (array_slice($team, 0, 11) as $index => $player): ?>
            <?php if ($player): ?>
                <div class="flex items-center gap-3 p-2 bg-gray-50 rounded-lg">
                    <div class="w-8 h-8 bg-blue-600 rounded-full flex items-center justify-center text-white text-sm font-bold">
                        <?php echo $index + 1; ?>
                    </div>
                    <div class="flex-1">
                        <div class="font-medium"><?php echo htmlspecialchars($player['name']); ?></div>
                        <div class="text-sm text-gray-600"><?php echo htmlspecialchars($roles[$index] ?? $player['position']); ?>
                        </div>
                    </div>
                    <div class="w-16 text-center">
                        <div
                            class="mt-2 bg-gray-700 bg-opacity-80 rounded-full h-1.5 overflow-hidden shadow-md border border-white border-opacity-30">
                            <div class="<?php echo getFitnessColor($player['fitness']); ?> h-full transition-all duration-300"
                                style="width: <?= max(0, min(100, $player['fitness'] ?? 0)) ?>%"></div>
                        </div>
                        <div class="text-xs text-gray-500 mt-1">Fitness</div>
                    </div>
                    <div class="w-16 text-center">
                        <div
                            class="w-6 h-6 mx-auto rounded-full flex items-center justify-center shadow-md <?php echo getFormBadgeColor($player['form'] ?? 0); ?> ring-1 ring-white z-10">
                            <?php echo getFormArrowIcon($player['form'] ?? 0); ?>
                        </div>
                    </div>
                    <div class="text-right">
                        <div class="text-sm font-medium"><?php echo $player['rating'] ?? '-'; ?></div>
                        <div class="text-xs text-gray-500">Rating</div>
                    </div>
                </div>
            <?php endif; ?>
        <?php endforeach; ?>
        <?php if (count($team) > 11): ?>
            <div class="my-3 border-t border-gray-200"></div>
            <?php foreach (array_slice($team, 11, 5) as $bIndex => $player): ?>
                <?php if ($player): ?>
                    <div class="flex items-center gap-3 p-2 bg-gray-50 rounded-lg">
                        <div class="w-8 h-8 bg-gray-500 rounded-full flex items-center justify-center text-white text-sm font-bold">
                            <?php echo 12 + $bIndex; ?>
                        </div>
                        <div class="flex-1">
                            <div class="font-medium"><?php echo htmlspecialchars($player['name']); ?></div>
                            <div class="text-sm text-gray-600"><?php echo htmlspecialchars($player['position']); ?></div>
                        </div>
                        <div class="w-16 text-center">
                            <div
                                class="mt-2 bg-gray-700 bg-opacity-80 rounded-full h-1.5 overflow-hidden shadow-md border border-white border-opacity-30">
                                <div class="<?php echo getFitnessColor($player['fitness']); ?> h-full transition-all duration-300"
                                    style="width: <?= max(0, min(100, $player['fitness'] ?? 0)) ?>%"></div>
                            </div>
                            <div class="text-xs text-gray-500 mt-1">Fitness</div>
                        </div>
                        <div class="w-16 text-center">
                            <div
                                class="w-6 h-6 mx-auto rounded-full flex items-center justify-center shadow-md <?php echo getFormBadgeColor($player['form'] ?? 0); ?> ring-1 ring-white z-10">
                                <?php echo getFormArrowIcon($player['form'] ?? 0); ?>
                            </div>
                        </div>
                        <div class="text-right">
                            <div class="text-sm font-medium"><?php echo $player['rating'] ?? '-'; ?></div>
                            <div class="text-xs text-gray-500">Rating</div>
                        </div>
                    </div>
                <?php endif; ?>
            <?php endforeach; ?>
        <?php endif; ?>
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
                    <a href="league.php"
                        class="inline-flex items-center gap-2 bg-gray-800 text-white px-4 py-2 rounded-lg hover:bg-gray-900 transition-colors">
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

// read parameters: opponent for new challenge and uuid for viewing a past match
$history_uuid = $_GET['uuid'] ?? null;
$opponent_uuid = $_GET['opponent'] ?? null;

// if we're not looking at a historic match, opponent must be supplied
if (!$opponent_uuid && !$history_uuid) {
    $renderError('Missing opponent.');
    return;
}


try {
    $db = getDbConnection();
    $user_uuid = $_SESSION['user_uuid'] ?? null;
    if (!$user_uuid) {
        header('Location: index.php');
        exit;
    }

    // we will build the match object below as soon as we have the necessary data
    $match = [
        'uuid' => null,
        'home_user_uuid' => null,
        'away_user_uuid' => null,
        'home_team_name' => '',
        'away_team_name' => '',
        'status' => 'pending',
        'formation' => null,
        'season' => date('Y'),
        'award' => 0,
        'date' => date('Y-m-d H:i:s'),
    ];

    // if viewing historical match, load basic info early so we can derive opponent UUID
    if ($history_uuid) {
        $stmtHist = $db->prepare('SELECT home_user_uuid, away_user_uuid, home_score, away_score, award, created_at FROM online_matches WHERE uuid = :uuid');
        if ($stmtHist) {
            $stmtHist->bindValue(':uuid', $history_uuid, SQLITE3_TEXT);
            $resHist = $stmtHist->execute();
            $rowHist = $resHist ? $resHist->fetchArray(SQLITE3_ASSOC) : null;
        } else {
            $rowHist = null;
        }

        if ($rowHist) {
            $match['uuid'] = $history_uuid;
            $match['home_user_uuid'] = $rowHist['home_user_uuid'];
            $match['away_user_uuid'] = $rowHist['away_user_uuid'];
            $match['date'] = $rowHist['created_at'];
            $match['home_score'] = (int) $rowHist['home_score'];
            $match['away_score'] = (int) $rowHist['away_score'];
            $match['award'] = (int) $rowHist['award'];
            $match['status'] = 'completed';

            // if no opponent was explicitly passed, figure it out now
            if (!$opponent_uuid) {
                if ($user_uuid === $match['home_user_uuid']) {
                    $opponent_uuid = $match['away_user_uuid'];
                    $is_home = true;
                } elseif ($user_uuid === $match['away_user_uuid']) {
                    $opponent_uuid = $match['home_user_uuid'];
                    $is_home = false;
                }
            }
        }
    }

    // Fetch user's own team data (always needed)
    $stmtUser = $db->prepare('SELECT u.uuid, u.name, c.club_name, c.formation, c.team, c.budget FROM users u JOIN user_club c ON c.user_uuid = u.uuid WHERE u.uuid = :uuid');
    if ($stmtUser) {
        $stmtUser->bindValue(':uuid', $user_uuid, SQLITE3_TEXT);
        $resUser = $stmtUser->execute();
        $user_data = $resUser ? $resUser->fetchArray(SQLITE3_ASSOC) : null;
    } else {
        $user_data = null;
    }

    if (!$user_data) {
        $renderError('User club data not found.');
        return;
    }

    // fetch opponent club data if we have an opponent UUID (might still be null for unknown history)
    if ($opponent_uuid) {
        $stmtOpponent = $db->prepare('SELECT u.uuid, u.name, c.club_name, c.formation, c.team, c.budget FROM users u JOIN user_club c ON c.user_uuid = u.uuid WHERE u.uuid = :uuid');
        if ($stmtOpponent) {
            $stmtOpponent->bindValue(':uuid', $opponent_uuid, SQLITE3_TEXT);
            $resOpponent = $stmtOpponent->execute();
            $opponent_data = $resOpponent ? $resOpponent->fetchArray(SQLITE3_ASSOC) : null;
        } else {
            $opponent_data = null;
        }

        if (!$opponent_data) {
            // only show an error if we expected an opponent (i.e. not in a weird history state)
            if (!$history_uuid) {
                $renderError('Opponent not found or insufficient data.');
                return;
            }
            // for history, we can continue without roster
            $opponent_data = null;
        }
    } else {
        $opponent_data = null;
    }

    // build match object for new challenge if not coming from history
    if (!$history_uuid) {
        $match['home_user_uuid'] = $user_uuid;
        $match['away_user_uuid'] = $opponent_uuid;
        $match['home_team_name'] = $user_data['club_name'] ?? 'Your Club';
        $match['away_team_name'] = $opponent_data['club_name'] ?? 'Opponent Club';
        $match['formation'] = $user_data['formation'] ?? '4-4-2';
    } else {
        // fill team names appropriately based on which side the current user occupies
        if ($match['home_user_uuid'] === $user_uuid) {
            $match['home_team_name'] = $user_data['club_name'] ?? 'Your Club';
            $match['away_team_name'] = $opponent_data['club_name'] ?? 'Opponent Club';
        } else {
            $match['away_team_name'] = $user_data['club_name'] ?? 'Your Club';
            $match['home_team_name'] = $opponent_data['club_name'] ?? 'Opponent Club';
        }
        $match['formation'] = $user_data['formation'] ?? '4-4-2';
    }

    // ensure is_home has a value when not already determined
    if (!isset($is_home)) {
        $is_home = true;
    }

    $opponent_user_uuid = $opponent_uuid;
    $is_user_match = true;
    $home_roster = null;
    $away_roster = $opponent_data ? json_decode($opponent_data['team'] ?? '[]', true) ?? [] : [];

    // determine readiness for simulation
    $club_ready = areClubPlayersReady($db, $user_uuid);

    // close db since we no longer need it for rendering
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
                            <h3 class="text-lg font-bold">Online Match</h3>
                            <p class="text-blue-100 text-sm">
                                <?= $match['date'] ?? null ?>
                            </p>
                        </div>
                    </div>
                    <div class="bg-white bg-opacity-20 px-3 py-1 rounded-full text-sm font-medium">
                        <i data-lucide="clock" class="w-4 h-4 inline mr-1"></i>
                        <?= $match['status'] == 'completed' ? 'Completed' : 'Preparing' ?>
                    </div>
                </div>
            </div>

            <div class="p-6">
                <div class="flex items-center justify-between">
                    <div class="flex-1 text-center">
                        <div
                            class="w-16 h-16 mx-auto mb-3 <?php echo $is_home ? 'bg-blue-600' : 'bg-gray-500'; ?> rounded-full flex items-center justify-center shadow-md">
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
                        <div
                            class="w-16 h-16 bg-gray-100 border-2 border-gray-300 rounded-full flex items-center justify-center">
                            <span class="text-gray-600 font-black text-xl">VS</span>
                        </div>
                    </div>

                    <div class="flex-1 text-center">
                        <div
                            class="w-16 h-16 mx-auto mb-3 <?php echo !$is_home ? 'bg-blue-600' : 'bg-gray-500'; ?> rounded-full flex items-center justify-center shadow-md">
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
                                </div>
                            </div>
                        </div>
                        <div class="p-6">
                            <div class="flex items-center justify-center gap-8">
                                <div class="text-center">
                                    <div class="text-lg font-semibold text-gray-800">
                                        <?php echo htmlspecialchars($match['home_team_name']); ?>
                                    </div>
                                    <div class="text-4xl font-black text-gray-900">
                                        <?php echo (int) ($match['home_score'] ?? 0); ?>
                                    </div>
                                </div>
                                <div class="text-3xl font-black text-gray-500">-</div>
                                <div class="text-center">
                                    <div class="text-lg font-semibold text-gray-800">
                                        <?php echo htmlspecialchars($match['away_team_name']); ?>
                                    </div>
                                    <div class="text-4xl font-black text-gray-900">
                                        <?php echo (int) ($match['away_score'] ?? 0); ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php if (!empty($is_user_match)): ?>
                        <?php
                        $user_score = $is_home ? (int) ($match['home_score'] ?? 0) : (int) ($match['away_score'] ?? 0);
                        $opponent_score = $is_home ? (int) ($match['away_score'] ?? 0) : (int) ($match['home_score'] ?? 0);
                        $result_label = $user_score > $opponent_score ? 'You won!' : ($user_score < $opponent_score ? 'You were beaten.' : 'Draw.');
                        $result_color = $user_score > $opponent_score ? 'bg-green-50 border-green-200 text-green-700' : ($user_score < $opponent_score ? 'bg-red-50 border-red-200 text-red-700' : 'bg-yellow-50 border-yellow-200 text-yellow-700');
                        $result_icon = $user_score > $opponent_score ? 'trophy' : ($user_score < $opponent_score ? 'alert-triangle' : 'minus');
                        ?>
                        <div class="rounded-lg border <?php echo $result_color; ?> p-4 mb-6 flex justify-center items-center gap-3">
                            <div class="text-2xl font-bold <?php
                            echo $user_score > $opponent_score ? 'text-green-600' : ($user_score < $opponent_score ? 'text-red-600' : 'text-yellow-600');
                            ?>">
                                <?php
                                echo $user_score > $opponent_score ? '🎉 VICTORY!' : ($user_score < $opponent_score ? '😞 DEFEAT!' : '🤝 DRAW!');
                                ?>
                            </div>
                        </div>
                    <?php endif; ?>
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
                                    <div class="flex justify-between items-center font-bold mb-3">
                                        <span>Total Budget Earned:</span>
                                        <span
                                            class="text-green-600 text-lg">+€<?php echo number_format($match['award'] ?? 0); ?></span>
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
                                <div
                                    class="w-10 h-10 <?php echo $is_home ? 'bg-blue-600' : 'bg-gray-500'; ?> rounded-full flex items-center justify-center">
                                    <i data-lucide="<?php echo $is_home ? 'user' : 'users'; ?>"
                                        class="w-5 h-5 text-white"></i>
                                </div>
                                <div>
                                    <h3
                                        class="font-bold text-lg <?php echo $is_home ? 'text-blue-600' : 'text-gray-700'; ?>">
                                        <?php echo htmlspecialchars($match['home_team_name']); ?>
                                    </h3>
                                    <div class="text-sm">
                                        <span class="text-green-600 font-medium">HOME</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="p-4">
                            <?php $displayTeamLineup($is_home ? $user_data : null, $is_home ? null : $home_roster, $is_home); ?>
                        </div>
                    </div>

                    <div class="bg-white rounded-lg shadow border border-gray-200 overflow-hidden">
                        <div class="bg-orange-50 border-b border-orange-200 p-4">
                            <div class="flex items-center gap-3">
                                <div
                                    class="w-10 h-10 <?php echo !$is_home ? 'bg-blue-600' : 'bg-gray-500'; ?> rounded-full flex items-center justify-center">
                                    <i data-lucide="<?php echo !$is_home ? 'user' : 'users'; ?>"
                                        class="w-5 h-5 text-white"></i>
                                </div>
                                <div>
                                    <h3
                                        class="font-bold text-lg <?php echo !$is_home ? 'text-blue-600' : 'text-gray-700'; ?>">
                                        <?php echo htmlspecialchars($match['away_team_name']); ?>
                                    </h3>
                                    <div class="text-sm">
                                        <span class="text-orange-600 font-medium">AWAY</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="p-4">
                            <?php $displayTeamLineup(!$is_home ? $user_data : null, !$is_home ? null : $away_roster, $is_home); ?>
                        </div>
                    </div>
                </div>

                <div class="mt-6 text-center">
                    <?php
                    if ($is_user_match) {
                        if (($match['status'] ?? '') === 'pending') {
                            if ($club_ready) {
                                ?>
                                <button id="simulateBtn"
                                    class="mx-auto bg-green-600 text-white px-6 py-2 rounded-lg hover:bg-green-700 font-medium transition-colors inline-flex items-center gap-2 shadow-md">
                                    <i data-lucide="play" class="w-4 h-4"></i>
                                    Simulate Match
                                </button>
                                <script>
                                    (function () {
                                        const btn = document.getElementById('simulateBtn');
                                        if (!btn) return;
                                        btn.addEventListener('click', async function () {
                                            // confirm cost before proceeding
                                            const cost = 5000; // simulation fee (could be dynamic)
                                            if (typeof Swal !== 'undefined') {
                                                const result = await Swal.fire({
                                                    title: 'Simulate match?',
                                                    text: `This will cost €${cost}. Proceed?`,
                                                    icon: 'question',
                                                    showCancelButton: true,
                                                    confirmButtonColor: '#10b981',
                                                    cancelButtonColor: '#ef4444',
                                                    confirmButtonText: 'Yes, simulate'
                                                });
                                                if (!result.isConfirmed) return;
                                            } else {
                                                if (!confirm(`Simulate match for €${cost}?`)) return;
                                            }

                                            if (btn.disabled) return;
                                            btn.disabled = true;
                                            btn.classList.add('opacity-50');
                                            btn.innerHTML = '<svg class="animate-spin h-4 w-4 text-white" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="white" stroke-width="4" fill="none"></circle><path class="opacity-75" fill="white" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path></svg><span>Simulating…</span>';
                                            try {
                                                const fd = new URLSearchParams();
                                                fd.set('simulate_match', '1');
                                                const res = await fetch('api/online_match_simulator_api.php', {
                                                    method: 'POST',
                                                    headers: {
                                                        'Content-Type': 'application/x-www-form-urlencoded'
                                                    },
                                                    body: (function () {
                                                        fd.set('home_uuid', '<?php echo $user_uuid; ?>');
                                                        fd.set('away_uuid', '<?php echo $opponent_uuid; ?>');
                                                        fd.set('simulate', '1');
                                                        return fd.toString();
                                                    })()
                                                });
                                                const data = await res.json().catch(() => null);
                                                if (res.ok && data && data.ok) {
                                                    window.location.href = 'online_match.php?uuid=' + encodeURIComponent(data.match_uuid);
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
                                <?php
                            } else {
                                ?>
                                <div class="flex flex-col items-center mt-3">
                                    <span
                                        class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-red-100 text-red-700 text-sm font-semibold border border-red-200">
                                        <i data-lucide="alert-circle" class="w-4 h-4"></i>
                                        Your squad for the next match must include at least 16 eligible players with fitness above 20
                                        and remaining contract matches.
                                    </span>
                                    <a href="team.php"
                                        class="mt-2 inline-flex items-center gap-2 px-4 py-1.5 rounded-lg bg-blue-600 text-white font-medium shadow hover:bg-blue-700 transition-colors">
                                        <i data-lucide="arrow-right" class="w-4 h-4"></i>
                                        Go to Team
                                    </a>
                                </div>
                                <?php
                            }
                        } else { ?>
                            <a href="clubs.php"
                                class="mx-auto bg-blue-600 text-white px-8 py-3 rounded-lg hover:bg-blue-700 font-bold transition-colors inline-flex items-center gap-2 shadow-md">
                                <i data-lucide="check-circle" class="w-5 h-5"></i>
                                Approve
                            </a>
                        <?php }
                    } else {
                        ?>
                        <div
                            class="inline-flex items-center gap-2 bg-gray-300 text-gray-700 px-6 py-2 rounded-lg font-medium shadow-md cursor-not-allowed">
                            <i data-lucide="lock" class="w-4 h-4"></i>
                            Not your match
                        </div>
                    <?php } ?>
                </div>
            </div>
        </div>
    </div>
    <?php
    endContent('Online Match');
} catch (Throwable $e) {
    $renderError('An error occurred while loading the match. ' . $e->getMessage());
}
