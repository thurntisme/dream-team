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

/**
 * Manage news items - clean expired and generate new ones
 */
if (!function_exists('manageNewsItems')) {
    function manageNewsItems($db, $user_id)
    {
        // Clean up expired news
        cleanExpiredNews($db, $user_id);

        // Get current news count and last creation time
        $stmt = $db->prepare('
            SELECT 
                COUNT(*) as count,
                MAX(created_at) as last_created
            FROM news 
            WHERE user_id = :user_id
        ');
        $stmt->bindValue(':user_id', $user_id, SQLITE3_INTEGER);
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
            generateNewNewsItems($db, $user_id, 6 - $currentCount);
        }

        // Return all current news items
        return getCurrentNewsItems($db, $user_id);
    }
}

/**
 * Clean up expired news items
 */
if (!function_exists('cleanExpiredNews')) {
    function cleanExpiredNews($db, $user_id)
    {
        $stmt = $db->prepare('DELETE FROM news WHERE user_id = :user_id AND expires_at < datetime("now")');
        $stmt->bindValue(':user_id', $user_id, SQLITE3_INTEGER);
        $stmt->execute();
    }
}

/**
 * Get current news items from database
 */
if (!function_exists('getCurrentNewsItems')) {
    function getCurrentNewsItems($db, $user_id)
    {
        $stmt = $db->prepare('
            SELECT * FROM news 
            WHERE user_id = :user_id AND expires_at > datetime("now")
            ORDER BY 
                CASE WHEN priority = "high" THEN 1 ELSE 2 END,
                created_at DESC
        ');
        $stmt->bindValue(':user_id', $user_id, SQLITE3_INTEGER);
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
    function generateNewNewsItems($db, $user_id, $maxItems)
    {
        // Get user's team players for departure requests
        $stmt = $db->prepare('SELECT team, substitutes FROM users WHERE id = :id');
        $stmt->bindValue(':id', $user_id, SQLITE3_INTEGER);
        $result = $stmt->execute();
        $userData = $result->fetchArray(SQLITE3_ASSOC);

        $team = json_decode($userData['team'] ?? '[]', true) ?: [];
        $substitutes = json_decode($userData['substitutes'] ?? '[]', true) ?: [];
        $allPlayers = array_merge(array_filter($team), array_filter($substitutes));

        $generatedCount = 0;

        // Collect all possible news items
        $possibleNews = [];

        // Hot transfers
        $hotTransfers = generateHotTransferNews();
        $possibleNews = array_merge($possibleNews, $hotTransfers);

        // Departure requests
        if (!empty($allPlayers)) {
            $departureRequests = generateDepartureRequestNews($allPlayers);
            $possibleNews = array_merge($possibleNews, $departureRequests);
        }

        // Player interest
        $interestedPlayers = generatePlayerInterestNews($user_id);
        $possibleNews = array_merge($possibleNews, $interestedPlayers);

        // If we have possible news, pick one randomly
        if (!empty($possibleNews)) {
            $selectedNews = $possibleNews[array_rand($possibleNews)];

            // Check if we need to remove oldest item to make space
            $stmt = $db->prepare('SELECT COUNT(*) as count FROM news WHERE user_id = :user_id');
            $stmt->bindValue(':user_id', $user_id, SQLITE3_INTEGER);
            $result = $stmt->execute();
            $currentCount = $result->fetchArray(SQLITE3_ASSOC)['count'];

            if ($currentCount >= 6) {
                // Remove the oldest news item
                $stmt = $db->prepare('
                    DELETE FROM news 
                    WHERE user_id = :user_id 
                    AND id = (
                        SELECT id FROM news 
                        WHERE user_id = :user_id 
                        ORDER BY created_at ASC 
                        LIMIT 1
                    )
                ');
                $stmt->bindValue(':user_id', $user_id, SQLITE3_INTEGER);
                $stmt->execute();
            }

            // Add the new news item
            saveNewsItem($db, $user_id, $selectedNews);
        }
    }
}

/**
 * Save a news item to the database
 */
if (!function_exists('saveNewsItem')) {
    function saveNewsItem($db, $user_id, $newsData)
    {
        $expiresAt = date('Y-m-d H:i:s', time() + (4 * 60 * 60)); // 4 hours from now

        $stmt = $db->prepare('
            INSERT INTO news (user_id, category, priority, title, content, player_data, actions, expires_at)
            VALUES (:user_id, :category, :priority, :title, :content, :player_data, :actions, :expires_at)
        ');

        $stmt->bindValue(':user_id', $user_id, SQLITE3_INTEGER);
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
            ]
        ];

        return $styles[$category] ?? $styles['hot_transfer'];
    }
}