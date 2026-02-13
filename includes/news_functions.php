<?php
/**
 * News Management Functions
 * 
 * This file contains functions related to news generation, management,
 * and display for the game's news system.
 */

// Prevent direct access
if (!defined('DREAM_TEAM_APP')) {
    exit('Direct access not allowed');
}

require_once __DIR__ . '/league_functions.php';

/**
 * Manage news items - clean expired and generate new ones
 */
if (!function_exists('manageNewsItems')) {
    function manageNewsItems($db, $user_uuid)
    {
        // Clean up expired news
        cleanExpiredNews($db, $user_uuid);

        // Get current news count and last creation time
        $stmt = $db->prepare('
            SELECT 
                COUNT(*) as count,
                MAX(created_at) as last_created
            FROM news 
            WHERE user_uuid = :user_uuid
        ');
        $stmt->bindValue(':user_uuid', $user_uuid, SQLITE3_TEXT);
        $result = $stmt->execute();
        $newsInfo = $result->fetchArray(SQLITE3_ASSOC);

        $currentCount = $newsInfo['count'];
        $lastCreated = $newsInfo['last_created'];

        // Check if we should generate news
        $shouldGenerate = false;

        if ($currentCount < 6) {
            // If we have space, check time since last news
            if ($lastCreated === null) {
                // No news exists, generate one
                $shouldGenerate = true;
            } else {
                // Check if last news was created more than 30 minutes ago
                $lastCreatedTime = strtotime($lastCreated);
                $thirtyMinutesAgo = time() - (30 * 60);

                if ($lastCreatedTime < $thirtyMinutesAgo) {
                    $shouldGenerate = true;
                }
            }
        }

        // Generate new news if conditions are met
        if ($shouldGenerate) {
            generateNewNewsItems($db, $user_uuid, 6 - $currentCount);
        }

        // Return all current news items
        return getCurrentNewsItems($db, $user_uuid);
    }
}

/**
 * Clean up expired news items
 */
if (!function_exists('cleanExpiredNews')) {
    function cleanExpiredNews($db, $user_uuid)
    {
        $stmt = $db->prepare('DELETE FROM news WHERE user_uuid = :user_uuid AND expires_at < NOW()');
        if ($stmt === false) {
            return;
        }
        $stmt->bindValue(':user_uuid', $user_uuid, SQLITE3_TEXT);
        $stmt->execute();
    }
}

/**
 * Get current news items from database
 */
if (!function_exists('getCurrentNewsItems')) {
    function getCurrentNewsItems($db, $user_uuid)
    {
        $stmt = $db->prepare('
            SELECT * FROM news 
            WHERE user_uuid = :user_uuid AND expires_at > NOW()
            ORDER BY 
                CASE WHEN priority = "high" THEN 1 ELSE 2 END,
                created_at DESC
        ');
        if ($stmt === false) {
            return [];
        }
        $stmt->bindValue(':user_uuid', $user_uuid, SQLITE3_TEXT);
        $result = $stmt->execute();

        $newsItems = [];
        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            $newsItem = [
                'id' => $row['id'],
                'category' => $row['category'],
                'priority' => $row['priority'],
                'title' => $row['title'],
                'content' => $row['content'],
                'created_at' => $row['created_at'],
                'expires_at' => $row['expires_at'],
                'time_ago' => getTimeAgo(strtotime($row['created_at']))
            ];

            // Decode JSON fields
            if ($row['player_data']) {
                $newsItem['player_data'] = json_decode($row['player_data'], true);
            }
            if ($row['actions']) {
                $newsItem['actions'] = json_decode($row['actions'], true);
            }

            $newsItems[] = $newsItem;
        }

        return $newsItems;
    }
}

/**
 * Generate new news items and save to database
 */
if (!function_exists('generateNewNewsItems')) {
    function generateNewNewsItems($db, $user_uuid, $maxItems)
    {
        $stmt = $db->prepare('SELECT team, substitutes FROM users WHERE uuid = :uuid');
        $stmt->bindValue(':uuid', $user_uuid, SQLITE3_TEXT);
        $result = $stmt->execute();
        $userData = $result->fetchArray(SQLITE3_ASSOC);

        $team = json_decode($userData['team'] ?? '[]', true) ?: [];
        $substitutes = json_decode($userData['substitutes'] ?? '[]', true) ?: [];
        $allPlayers = array_merge(array_filter($team), array_filter($substitutes));

        $possibleNews = [];
        $possibleNews = array_merge($possibleNews, generateHotTransferNews());
        if (!empty($allPlayers)) {
            $possibleNews = array_merge($possibleNews, generateDepartureRequestNews($allPlayers));
        }
        $possibleNews = array_merge($possibleNews, generatePlayerInterestNews($user_uuid));

        $opponentPreview = generateNextOpponentNews($db, $user_uuid);
        $opponentPlayers = generateOpponentPlayersNews($db, $user_uuid);

        $selectedNews = null;
        if (!empty($opponentPreview)) {
            $selectedNews = $opponentPreview[0];
        } elseif (!empty($possibleNews)) {
            $selectedNews = $possibleNews[array_rand($possibleNews)];
        } elseif (!empty($opponentPlayers)) {
            $selectedNews = $opponentPlayers[0];
        }

        if ($selectedNews) {
            $stmt = $db->prepare('SELECT COUNT(*) as count FROM news WHERE user_uuid = :user_uuid');
            $stmt->bindValue(':user_uuid', $user_uuid, SQLITE3_TEXT);
            $result = $stmt->execute();
            $currentCount = $result->fetchArray(SQLITE3_ASSOC)['count'];

            if ($currentCount >= 6) {
                $stmt = $db->prepare('
                    DELETE FROM news 
                    WHERE user_uuid = :user_uuid 
                    AND id = (
                        SELECT id FROM news 
                        WHERE user_uuid = :user_uuid 
                        ORDER BY created_at ASC 
                        LIMIT 1
                    )
                ');
                $stmt->bindValue(':user_uuid', $user_uuid, SQLITE3_TEXT);
                $stmt->execute();
            }

            saveNewsItem($db, $user_uuid, $selectedNews);
        }
    }
}

/**
 * Save a news item to the database
 */
if (!function_exists('saveNewsItem')) {
    function saveNewsItem($db, $user_uuid, $newsData)
    {
        $expiresAt = date('Y-m-d H:i:s', time() + (4 * 60 * 60)); // 4 hours from now

        $stmt = $db->prepare('
            INSERT INTO news (user_uuid, category, priority, title, content, player_data, actions, expires_at)
            VALUES (:user_uuid, :category, :priority, :title, :content, :player_data, :actions, :expires_at)
        ');

        $stmt->bindValue(':user_uuid', $user_uuid, SQLITE3_TEXT);
        $stmt->bindValue(':category', $newsData['category'], SQLITE3_TEXT);
        $stmt->bindValue(':priority', $newsData['priority'], SQLITE3_TEXT);
        $stmt->bindValue(':title', $newsData['title'], SQLITE3_TEXT);
        $stmt->bindValue(':content', $newsData['content'], SQLITE3_TEXT);
        $stmt->bindValue(':player_data', isset($newsData['player_data']) ? json_encode($newsData['player_data']) : null, SQLITE3_TEXT);
        $stmt->bindValue(':actions', isset($newsData['actions']) ? json_encode($newsData['actions']) : null, SQLITE3_TEXT);
        $stmt->bindValue(':expires_at', $expiresAt, SQLITE3_TEXT);

        return $stmt->execute();
    }
}

/**
 * Generate hot transfer news
 */
if (!function_exists('generateHotTransferNews')) {
    function generateHotTransferNews()
    {
        $news = [];
        $players = getDefaultPlayers();

        // 1% chance to generate hot transfer stories
        if (rand(0, 100) < 1) {
            // Generate 1-2 hot transfer stories
            for ($i = 0; $i < rand(1, 2); $i++) {
                $player = $players[array_rand($players)];
                $clubs = ['Manchester City', 'Real Madrid', 'Barcelona', 'Bayern Munich', 'PSG', 'Liverpool', 'Chelsea'];
                $fromClub = $clubs[array_rand($clubs)];
                $toClub = $clubs[array_rand($clubs)];

                while ($fromClub === $toClub) {
                    $toClub = $clubs[array_rand($clubs)];
                }

                $transferFee = $player['value'] * (1 + rand(-20, 50) / 100);

                $news[] = [
                    'category' => 'hot_transfer',
                    'priority' => rand(0, 100) > 70 ? 'high' : 'normal',
                    'title' => $player['name'] . ' linked with €' . number_format($transferFee / 1000000, 1) . 'M move',
                    'content' => $fromClub . ' star ' . $player['name'] . ' is reportedly close to joining ' . $toClub . ' in a deal worth €' . number_format($transferFee / 1000000, 1) . ' million. The ' . $player['position'] . ' has been a key player this season.',
                    'player_data' => $player,
                    'actions' => []
                ];
            }
        }

        return $news;
    }
}

/**
 * Generate departure request news from user's players
 */
if (!function_exists('generateDepartureRequestNews')) {
    function generateDepartureRequestNews($players)
    {
        $news = [];

        // 1% chance a player wants to leave
        foreach ($players as $player) {
            if (rand(0, 100) < 1) {
                $reasons = [
                    'seeking more playing time',
                    'wanting to play in Champions League',
                    'family reasons',
                    'attracted by a bigger club offer',
                    'looking for a new challenge'
                ];

                $reason = $reasons[array_rand($reasons)];

                $news[] = [
                    'category' => 'departure_request',
                    'priority' => 'high',
                    'title' => $player['name'] . ' requests transfer',
                    'content' => 'Your player ' . $player['name'] . ' has submitted a transfer request, citing ' . $reason . '. The player is looking to move in the next transfer window.',
                    'player_data' => $player,
                    'actions' => [
                        [
                            'type' => 'negotiate',
                            'label' => 'Negotiate',
                            'icon' => 'message-circle',
                            'style' => 'bg-blue-600 text-white hover:bg-blue-700'
                        ],
                        [
                            'type' => 'dismiss',
                            'label' => 'Dismiss',
                            'icon' => 'x',
                            'style' => 'bg-gray-200 text-gray-700 hover:bg-gray-300'
                        ]
                    ]
                ];
                break; // Only one departure request at a time
            }
        }

        return $news;
    }
}

if (!function_exists('generateNextOpponentNews')) {
    function generateNextOpponentNews($db, $user_uuid)
    {
        $news = [];
        $season = getCurrentSeasonIdentifier($db);
        $upcoming = getUpcomingMatches($db, $user_uuid, $season);
        if (empty($upcoming)) {
            return $news;
        }
        $nextMatch = null;
        foreach ($upcoming as $m) {
            if (($m['home_user_uuid'] ?? null) === $user_uuid || ($m['away_user_uuid'] ?? null) === $user_uuid) {
                $nextMatch = $m;
                break;
            }
        }
        if (!$nextMatch) {
            return $news;
        }
        $isHome = (($nextMatch['home_user_uuid'] ?? null) === $user_uuid);
        $opponentName = $isHome ? $nextMatch['away_team'] : $nextMatch['home_team'];
        $venue = $isHome ? 'Home' : 'Away';
        $gw = (int)($nextMatch['gameweek'] ?? 0);
        $dateText = $nextMatch['match_date'] ?? 'TBD';
        $standings = getLeagueStandings($db, $season);
        $opponentTeam = null;
        $opponentPos = null;
        foreach ($standings as $idx => $team) {
            if ($team['name'] === $opponentName) {
                $opponentTeam = $team;
                $opponentPos = $idx + 1;
                break;
            }
        }
        $userTeamPos = null;
        foreach ($standings as $idx => $team) {
            if (($team['user_uuid'] ?? null) === $user_uuid) {
                $userTeamPos = $idx + 1;
                break;
            }
        }
        $oppPoints = $opponentTeam['points'] ?? null;
        $oppGD = null;
        if ($opponentTeam) {
            $oppGD = ($opponentTeam['goals_for'] ?? 0) - ($opponentTeam['goals_against'] ?? 0);
        }
        $difficulty = null;
        if ($opponentTeam) {
            $difficulty = round(calculateTeamStrength($opponentTeam, false));
        }
        $title = 'Next Opponent: ' . $opponentName . ' (' . $venue . ')';
        $parts = [];
        $parts[] = 'Gameweek ' . $gw . ' on ' . $dateText . '.';
        if ($opponentPos !== null) {
            $parts[] = $opponentName . ' is ' . $opponentPos . 'th with ' . ($oppPoints ?? 0) . ' pts' . ($oppGD !== null ? ' (GD ' . $oppGD . ')' : '');
        }
        if ($userTeamPos !== null) {
            $parts[] = 'Your club stands ' . $userTeamPos . 'th.';
        }
        if ($difficulty !== null) {
            $parts[] = 'Expected difficulty: ' . $difficulty . '/100.';
        }
        $content = implode(' ', $parts);
        $news[] = [
            'category' => 'match_preview',
            'priority' => 'normal',
            'title' => $title,
            'content' => $content,
            'actions' => [
                [
                    'type' => 'view_league',
                    'label' => 'View League',
                    'icon' => 'calendar',
                    'style' => 'bg-blue-600 text-white hover:bg-blue-700'
                ]
            ]
        ];
        return $news;
    }
}

if (!function_exists('generateOpponentPlayersNews')) {
    function generateOpponentPlayersNews($db, $user_uuid)
    {
        $news = [];
        $season = getCurrentSeasonIdentifier($db);
        $upcoming = getUpcomingMatches($db, $user_uuid, $season);
        if (empty($upcoming)) {
            return $news;
        }
        $nextMatch = null;
        foreach ($upcoming as $m) {
            if (($m['home_user_uuid'] ?? null) === $user_uuid || ($m['away_user_uuid'] ?? null) === $user_uuid) {
                $nextMatch = $m;
                break;
            }
        }
        if (!$nextMatch) {
            return $news;
        }
        $isHome = (($nextMatch['home_user_uuid'] ?? null) === $user_uuid);
        $opponentName = $isHome ? $nextMatch['away_team'] : $nextMatch['home_team'];
        $players = getDefaultPlayers();
        $candidates = array_filter($players, function ($p) {
            return ($p['rating'] ?? 0) >= 85;
        });
        if (empty($candidates)) {
            $candidates = $players;
        }
        shuffle($candidates);
        $pickCount = min(2, max(1, rand(1, 2)));
        $picked = array_slice($candidates, 0, $pickCount);
        foreach ($picked as $player) {
            $title = 'Opponent Key Player: ' . $player['name'] . ' (' . $player['position'] . ')';
            $content = 'Scouting report: ' . $opponentName . ' are expected to feature ' . $player['name'] . ' (' . $player['position'] . ', ★' . ($player['rating'] ?? 0) . '). Keep an eye on their impact.';
            $news[] = [
                'category' => 'opponent_player',
                'priority' => (($player['rating'] ?? 0) >= 90) ? 'high' : 'normal',
                'title' => $title,
                'content' => $content,
                'player_data' => $player,
                'actions' => [
                    [
                        'type' => 'view_league',
                        'label' => 'View League',
                        'icon' => 'calendar',
                        'style' => 'bg-blue-600 text-white hover:bg-blue-700'
                    ]
                ]
            ];
        }
        return $news;
    }
}

/**
 * Generate news about players interested in joining
 */
if (!function_exists('generatePlayerInterestNews')) {
    function generatePlayerInterestNews($user_id)
    {
        $news = [];
        $players = getDefaultPlayers();

        // 1% chance to generate interested players
        if (rand(0, 100) < 1) {
            // Generate 1 interested player
            for ($i = 0; $i < 1; $i++) {
                $player = $players[array_rand($players)];
                $reasons = [
                    'impressed by your recent performances',
                    'attracted by your club\'s playing style',
                    'looking for regular first-team football',
                    'wants to be part of your project',
                    'seeking a new challenge'
                ];

                $reason = $reasons[array_rand($reasons)];

                $news[] = [
                    'category' => 'player_interest',
                    'priority' => 'normal',
                    'title' => $player['name'] . ' interested in joining your club',
                    'content' => 'Free agent ' . $player['name'] . ' has expressed interest in joining your club. The ' . $player['position'] . ' is ' . $reason . ' and is available for immediate signing.',
                    'player_data' => $player,
                    'actions' => [
                        [
                            'type' => 'offer_contract',
                            'label' => 'Make Offer',
                            'icon' => 'file-text',
                            'style' => 'bg-green-600 text-white hover:bg-green-700'
                        ],
                        [
                            'type' => 'not_interested',
                            'label' => 'Not Interested',
                            'icon' => 'x',
                            'style' => 'bg-gray-200 text-gray-700 hover:bg-gray-300'
                        ]
                    ]
                ];
            }
        }

        return $news;
    }
}

/**
 * Get news category styling
 */
if (!function_exists('getNewsCategoryStyle')) {
    function getNewsCategoryStyle($category)
    {
        $styles = [
            'hot_transfer' => [
                'bg' => 'bg-red-100',
                'text' => 'text-red-600',
                'icon' => 'trending-up',
                'badge' => 'bg-red-100 text-red-800'
            ],
            'departure_request' => [
                'bg' => 'bg-orange-100',
                'text' => 'text-orange-600',
                'icon' => 'user-x',
                'badge' => 'bg-orange-100 text-orange-800'
            ],
            'player_interest' => [
                'bg' => 'bg-green-100',
                'text' => 'text-green-600',
                'icon' => 'user-plus',
                'badge' => 'bg-green-100 text-green-800'
            ],
            'match_preview' => [
                'bg' => 'bg-blue-100',
                'text' => 'text-blue-600',
                'icon' => 'calendar',
                'badge' => 'bg-blue-100 text-blue-800'
            ],
            'opponent_player' => [
                'bg' => 'bg-indigo-100',
                'text' => 'text-indigo-600',
                'icon' => 'target',
                'badge' => 'bg-indigo-100 text-indigo-800'
            ]
        ];

        return $styles[$category] ?? $styles['hot_transfer'];
    }
}
?>
