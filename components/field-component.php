<?php
// Football Field Component
// Reusable component for displaying football field with players

/**
 * Render a football field with players
 * 
 * @param array $team - Array of players
 * @param string $formation - Formation name (e.g., '4-4-2')
 * @param array $options - Configuration options
 * @return string - HTML for the field
 */
function renderFootballField($team, $formation, $options = [])
{
    // Default options
    $defaults = [
        'interactive' => false,
        'size' => 'large', // 'large', 'medium', 'small'
        'show_names' => true,
        'show_actions' => false,
        'selected_player' => null,
        'field_id' => 'field',
        'class' => ''
    ];

    $options = array_merge($defaults, $options);

    // Get formation data
    $formationData = FORMATIONS[$formation] ?? FORMATIONS['4-4-2'];
    $positions = $formationData['positions'];
    $roles = $formationData['roles'];

    // Size configurations
    $sizeConfig = [
        'large' => [
            'container' => 'min-h-[700px] p-8',
            'player_size' => 'w-16 h-16',
            'icon_size' => 'w-5 h-5',
            'text_size' => 'text-xs',
            'name_size' => 'text-xs',
            'field_lines' => 'inset-8'
        ],
        'medium' => [
            'container' => 'min-h-[500px] p-6',
            'player_size' => 'w-12 h-12',
            'icon_size' => 'w-4 h-4',
            'text_size' => 'text-xs',
            'name_size' => 'text-xs',
            'field_lines' => 'inset-6'
        ],
        'small' => [
            'container' => 'min-h-[400px] p-4',
            'player_size' => 'w-10 h-10',
            'icon_size' => 'w-3 h-3',
            'text_size' => 'text-xs',
            'name_size' => 'text-xs',
            'field_lines' => 'inset-4'
        ]
    ];

    $size = $sizeConfig[$options['size']];

    ob_start();
    ?>

    <!-- Football Field -->
    <div
        class="bg-gradient-to-b from-green-500 to-green-600 rounded-lg shadow relative <?php echo $size['container']; ?> <?php echo $options['class']; ?>">
        <!-- Field Lines -->
        <div
            class="absolute <?php echo $size['field_lines']; ?> border-2 border-white border-opacity-40 rounded overflow-hidden">
            <!-- Center Line -->
            <div class="absolute top-1/2 left-0 right-0 h-0.5 bg-white opacity-40"></div>
            <!-- Center Circle -->
            <div
                class="absolute top-1/2 left-1/2 transform -translate-x-1/2 -translate-y-1/2 w-24 h-24 border-2 border-white border-opacity-40 rounded-full">
            </div>
            <div
                class="absolute top-1/2 left-1/2 transform -translate-x-1/2 -translate-y-1/2 w-2 h-2 bg-white opacity-40 rounded-full">
            </div>

            <!-- Top Penalty Area -->
            <div
                class="absolute top-0 left-1/2 transform -translate-x-1/2 w-48 h-20 border-2 border-t-0 border-white border-opacity-40">
            </div>
            <div
                class="absolute top-0 left-1/2 transform -translate-x-1/2 w-24 h-10 border-2 border-t-0 border-white border-opacity-40">
            </div>

            <!-- Bottom Penalty Area -->
            <div
                class="absolute bottom-0 left-1/2 transform -translate-x-1/2 w-48 h-20 border-2 border-b-0 border-white border-opacity-40">
            </div>
            <div
                class="absolute bottom-0 left-1/2 transform -translate-x-1/2 w-24 h-10 border-2 border-b-0 border-white border-opacity-40">
            </div>

            <!-- Corner Arcs -->
            <div
                class="absolute top-0 left-0 w-8 h-8 border-2 border-t-0 border-l-0 border-white border-opacity-40 rounded-br-full">
            </div>
            <div
                class="absolute top-0 right-0 w-8 h-8 border-2 border-t-0 border-r-0 border-white border-opacity-40 rounded-bl-full">
            </div>
            <div
                class="absolute bottom-0 left-0 w-8 h-8 border-2 border-b-0 border-l-0 border-white border-opacity-40 rounded-tr-full">
            </div>
            <div
                class="absolute bottom-0 right-0 w-8 h-8 border-2 border-b-0 border-r-0 border-white border-opacity-40 rounded-tl-full">
            </div>
        </div>

        <!-- Players -->
        <div id="<?php echo $options['field_id']; ?>" class="relative h-full">
            <?php echo renderFieldPlayers($team, $positions, $roles, $options, $size); ?>
        </div>
    </div>

    <?php
    return ob_get_clean();
}

/**
 * Render players on the field
 */
function renderFieldPlayers($team, $positions, $roles, $options, $size)
{
    $html = '';
    $playerIdx = 0;

    foreach ($positions as $lineIdx => $line) {
        foreach ($line as $xPos) {
            $player = $team[$playerIdx] ?? null;
            $yPos = 100 - (($lineIdx + 1) * (100 / (count($positions) + 1)));
            $requiredPosition = $roles[$playerIdx] ?? 'GK';
            $colors = getPositionColors($requiredPosition);
            $isSelected = $options['selected_player'] === $playerIdx;

            if ($player) {
                var_dump('player info: '.$player);
                $isCustom = $player['isCustom'] ?? false;
                $customBadge = $isCustom ? '<div class="absolute -top-1 -right-1 w-2 h-2 bg-purple-500 rounded-full"></div>' : '';
                $selectedClass = $isSelected ? 'ring-4 ring-yellow-400 ring-opacity-80' : '';

                $html .= '
                    <div class="absolute cursor-pointer player-slot transition-all duration-200" 
                         style="left: ' . $xPos . '%; top: ' . $yPos . '%; transform: translate(-50%, -50%);" data-idx="' . $playerIdx . '">
                        <div class="relative">
                            <div class="' . $size['player_size'] . ' bg-white rounded-full flex flex-col items-center justify-center shadow-lg border-2 ' . $colors['border'] . ' transition-all duration-200 player-circle ' . $selectedClass . ' overflow-hidden">
                                ' . getPlayerAvatarWithImage($player['name'], $player['avatar'] ?? null, 'md') . '
                                ' . $customBadge . '
                            </div>';

                if ($options['show_names']) {
                    $html .= '
                        <div class="absolute top-full left-1/2 transform -translate-x-1/2 mt-1 whitespace-nowrap">
                            <div class="text-white ' . $size['name_size'] . ' font-bold bg-black bg-opacity-70 px-2 py-1 rounded">' . htmlspecialchars($player['name']) . '</div>
                        </div>';
                }

                if ($options['show_actions'] && $isSelected) {
                    $html .= '
                        <div class="absolute -bottom-2 left-1/2 transform -translate-x-1/2 flex gap-2 action-buttons">
                            <button onclick="removePlayer(' . $playerIdx . ')" class="w-7 h-7 bg-red-500 hover:bg-red-600 text-white rounded-full flex items-center justify-center shadow-lg transition-all duration-200 hover:scale-110" title="Remove Player">
                                <i data-lucide="trash-2" class="w-3 h-3"></i>
                            </button>
                            <button onclick="choosePlayer(' . $playerIdx . ')" class="w-7 h-7 bg-green-500 hover:bg-green-600 text-white rounded-full flex items-center justify-center shadow-lg transition-all duration-200 hover:scale-110" title="Choose Different Player">
                                <i data-lucide="user-plus" class="w-3 h-3"></i>
                            </button>
                        </div>';
                }

                if ($options['show_actions'] && !$isSelected && $options['selected_player'] !== null) {
                    $html .= '
                        <div class="absolute -bottom-2 left-1/2 transform -translate-x-1/2 hover-switch-btn opacity-0 transition-all duration-200">
                            <button onclick="switchPlayer(' . $playerIdx . ')" class="w-7 h-7 bg-blue-500 hover:bg-blue-600 text-white rounded-full flex items-center justify-center shadow-lg transition-all duration-200 hover:scale-110" title="Switch with Selected Player">
                                <i data-lucide="arrow-left-right" class="w-3 h-3"></i>
                            </button>
                        </div>';
                }

                $html .= '</div></div>';
            } else {
                // Empty slot
                $html .= '
                    <div class="absolute cursor-pointer empty-slot" 
                         style="left: ' . $xPos . '%; top: ' . $yPos . '%; transform: translate(-50%, -50%);" data-idx="' . $playerIdx . '">
                        <div class="' . $size['player_size'] . ' bg-white bg-opacity-20 rounded-full flex flex-col items-center justify-center border-2 border-white border-dashed hover:border-blue-300 hover:bg-opacity-30 transition-all duration-200">
                            <i data-lucide="plus" class="' . $size['icon_size'] . ' text-white"></i>
                            <span class="' . $size['text_size'] . ' font-bold text-white">' . htmlspecialchars($requiredPosition) . '</span>
                        </div>
                    </div>';
            }

            $playerIdx++;
        }
    }

    return $html;
}

/**
 * Get position colors for styling
 */
function getPositionColors($position)
{
    $colorMap = [
        // Goalkeeper - Yellow/Orange
        'GK' => [
            'bg' => 'bg-amber-400',
            'border' => 'border-amber-500',
            'text' => 'text-amber-800',
            'emptyBg' => 'bg-amber-400 bg-opacity-30',
            'emptyBorder' => 'border-amber-400'
        ],
        // Defenders - Green
        'CB' => [
            'bg' => 'bg-emerald-400',
            'border' => 'border-emerald-500',
            'text' => 'text-emerald-800',
            'emptyBg' => 'bg-emerald-400 bg-opacity-30',
            'emptyBorder' => 'border-emerald-400'
        ],
        'LB' => [
            'bg' => 'bg-emerald-400',
            'border' => 'border-emerald-500',
            'text' => 'text-emerald-800',
            'emptyBg' => 'bg-emerald-400 bg-opacity-30',
            'emptyBorder' => 'border-emerald-400'
        ],
        'RB' => [
            'bg' => 'bg-emerald-400',
            'border' => 'border-emerald-500',
            'text' => 'text-emerald-800',
            'emptyBg' => 'bg-emerald-400 bg-opacity-30',
            'emptyBorder' => 'border-emerald-400'
        ],
        'LWB' => [
            'bg' => 'bg-emerald-400',
            'border' => 'border-emerald-500',
            'text' => 'text-emerald-800',
            'emptyBg' => 'bg-emerald-400 bg-opacity-30',
            'emptyBorder' => 'border-emerald-400'
        ],
        'RWB' => [
            'bg' => 'bg-emerald-400',
            'border' => 'border-emerald-500',
            'text' => 'text-emerald-800',
            'emptyBg' => 'bg-emerald-400 bg-opacity-30',
            'emptyBorder' => 'border-emerald-400'
        ],
        // Midfielders - Blue
        'CDM' => [
            'bg' => 'bg-blue-400',
            'border' => 'border-blue-500',
            'text' => 'text-blue-800',
            'emptyBg' => 'bg-blue-400 bg-opacity-30',
            'emptyBorder' => 'border-blue-400'
        ],
        'CM' => [
            'bg' => 'bg-blue-400',
            'border' => 'border-blue-500',
            'text' => 'text-blue-800',
            'emptyBg' => 'bg-blue-400 bg-opacity-30',
            'emptyBorder' => 'border-blue-400'
        ],
        'CAM' => [
            'bg' => 'bg-blue-400',
            'border' => 'border-blue-500',
            'text' => 'text-blue-800',
            'emptyBg' => 'bg-blue-400 bg-opacity-30',
            'emptyBorder' => 'border-blue-400'
        ],
        'LM' => [
            'bg' => 'bg-blue-400',
            'border' => 'border-blue-500',
            'text' => 'text-blue-800',
            'emptyBg' => 'bg-blue-400 bg-opacity-30',
            'emptyBorder' => 'border-blue-400'
        ],
        'RM' => [
            'bg' => 'bg-blue-400',
            'border' => 'border-blue-500',
            'text' => 'text-blue-800',
            'emptyBg' => 'bg-blue-400 bg-opacity-30',
            'emptyBorder' => 'border-blue-400'
        ],
        // Forwards/Strikers - Red
        'LW' => [
            'bg' => 'bg-red-400',
            'border' => 'border-red-500',
            'text' => 'text-red-800',
            'emptyBg' => 'bg-red-400 bg-opacity-30',
            'emptyBorder' => 'border-red-400'
        ],
        'RW' => [
            'bg' => 'bg-red-400',
            'border' => 'border-red-500',
            'text' => 'text-red-800',
            'emptyBg' => 'bg-red-400 bg-opacity-30',
            'emptyBorder' => 'border-red-400'
        ],
        'ST' => [
            'bg' => 'bg-red-400',
            'border' => 'border-red-500',
            'text' => 'text-red-800',
            'emptyBg' => 'bg-red-400 bg-opacity-30',
            'emptyBorder' => 'border-red-400'
        ],
        'CF' => [
            'bg' => 'bg-red-400',
            'border' => 'border-red-500',
            'text' => 'text-red-800',
            'emptyBg' => 'bg-red-400 bg-opacity-30',
            'emptyBorder' => 'border-red-400'
        ]
    ];

    return $colorMap[$position] ?? $colorMap['GK'];
}

/**
 * Generate JavaScript for field interactions
 */
function generateFieldJavaScript($options = [])
{
    if (!$options['interactive']) {
        return '';
    }

    ob_start();
    ?>
    <script>
        // Field interaction handlers
        $('.player-slot').click(function () {
            const idx = $(this).data('idx');
            selectPlayer(idx);
        });

        $('.empty-slot').click(function () {
            const idx = $(this).data('idx');
            choosePlayer(idx);
        });

        // Hover effects for interactive elements
        $('.player-slot').hover(
            function () {
                if (!$(this).find('.player-circle').hasClass('ring-4')) {
                    $(this).find('.player-circle').addClass('ring-2 ring-blue-300');
                }
                if (selectedPlayerIdx !== null && $(this).data('idx') !== selectedPlayerIdx) {
                    $(this).find('.hover-switch-btn').removeClass('opacity-0');
                }
            },
            function () {
                $(this).find('.player-circle').removeClass('ring-2 ring-blue-300');
                $(this).find('.hover-switch-btn').addClass('opacity-0');
            }
        );

        $('.empty-slot').hover(
            function () {
                $(this).find('div').first().addClass('border-blue-300 bg-opacity-30');
            },
            function () {
                $(this).find('div').first().removeClass('border-blue-300 bg-opacity-30');
            }
        );
    </script>
    <?php
    return ob_get_clean();
}
?>