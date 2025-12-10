<?php
session_start();

require_once 'config.php';
require_once 'constants.php';

// Check if database is available, redirect to install if not
if (!isDatabaseAvailable()) {
    header('Location: install.php');
    exit;
}

if (!isset($_SESSION['user_id']) || !isset($_SESSION['club_name'])) {
    header('Location: index.php');
    exit;
}

try {
    $db = getDbConnection();

    $stmt = $db->prepare('SELECT formation, team FROM users WHERE id = :id');
    $stmt->bindValue(':id', $_SESSION['user_id'], SQLITE3_INTEGER);
    $result = $stmt->execute();
    $user = $result->fetchArray(SQLITE3_ASSOC);
    $saved_formation = $user['formation'] ?? '4-4-2';
    $saved_team = $user['team'] ?? '[]';

    $db->close();
} catch (Exception $e) {
    header('Location: install.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dream Team - <?php echo htmlspecialchars($_SESSION['club_name']); ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <link rel="stylesheet" href="https://code.jquery.com/ui/1.13.2/themes/base/jquery-ui.css">
    <script src="https://unpkg.com/lucide@latest"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>

<body class="bg-gray-50 min-h-screen">
    <nav class="bg-white shadow">
        <div class="container mx-auto p-4 max-w-6xl flex justify-between items-center">
            <div class="flex items-center gap-2">
                <a href="index.php" class="flex items-center">
                    <i data-lucide="shield" class="w-6 h-6 text-blue-600"></i>
                    <span class="font-bold text-lg ml-1"><?php echo htmlspecialchars($_SESSION['club_name']); ?></span>
                </a>
            </div>
            <button id="logoutBtn" class="text-gray-600 hover:text-gray-900">
                <i data-lucide="log-out" class="w-5 h-5"></i>
            </button>
        </div>
    </nav>

    <div class="container mx-auto p-4 max-w-6xl">
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
            <!-- Formation Selector -->
            <div class="bg-white rounded-lg shadow p-4">
                <h2 class="text-xl font-bold mb-4">Formation</h2>
                <select id="formation"
                    class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                    <?php foreach (FORMATIONS as $key => $formation): ?>
                        <option value="<?php echo htmlspecialchars($key); ?>"
                            title="<?php echo htmlspecialchars($formation['description']); ?>">
                            <?php echo htmlspecialchars($formation['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <h2 class="text-xl font-bold mt-6 mb-4">Your Players</h2>
                <div id="playerList" class="space-y-2 max-h-96 overflow-y-auto"></div>
            </div>

            <!-- Field -->
            <div class="lg:col-span-2 bg-gradient-to-b from-green-500 to-green-600 rounded-lg shadow p-8 relative"
                style="min-height: 700px;">
                <!-- Field Lines -->
                <div class="absolute inset-8 border-2 border-white border-opacity-40 rounded overflow-hidden">
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
                <div id="field" class="relative h-full"></div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
            <div class="lg:block"></div>
            <div class="lg:col-span-2 mt-4 flex justify-center gap-3">
                <button id="resetTeam"
                    class="bg-red-600 text-white px-6 py-2 rounded-lg hover:bg-red-700 flex items-center gap-2">
                    <i data-lucide="rotate-ccw" class="w-4 h-4"></i>
                    Reset Team
                </button>
                <button id="saveTeam"
                    class="bg-blue-600 text-white px-6 py-2 rounded-lg hover:bg-blue-700 flex items-center gap-2">
                    <i data-lucide="save" class="w-4 h-4"></i>
                    Save Team
                </button>
            </div>
        </div>
    </div>

    <!-- Player Selection Modal -->
    <div id="playerModal" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
        <div class="bg-white rounded-lg p-6 w-full max-w-md">
            <div class="flex justify-between items-center mb-4">
                <h3 id="modalTitle" class="text-xl font-bold">Select Player</h3>
                <button id="closeModal" class="text-gray-500 hover:text-gray-700">
                    <i data-lucide="x" class="w-6 h-6"></i>
                </button>
            </div>

            <div class="mb-4">
                <label id="customPlayerLabel" class="block text-sm font-medium mb-2">Custom Player Name</label>
                <div class="flex gap-2">
                    <input type="text" id="customPlayerName" placeholder="Enter custom name..."
                        class="flex-1 px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                    <button id="addCustomPlayer"
                        class="bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700">
                        Add
                    </button>
                </div>
            </div>

            <div class="border-t pt-4">
                <input type="text" id="playerSearch" placeholder="Search player..."
                    class="w-full px-3 py-2 border rounded-lg mb-4 focus:outline-none focus:ring-2 focus:ring-blue-500">
                <div id="modalPlayerList" class="space-y-2 max-h-64 overflow-y-auto"></div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/ui/1.13.2/jquery-ui.min.js"></script>
    <script>
        const players = <?php echo json_encode(getDefaultPlayers()); ?>;

        let savedTeam = <?php echo $saved_team; ?>;
        let selectedPlayers = Array.isArray(savedTeam) && savedTeam.length > 0 ? savedTeam : [];
        let currentSlotIdx = null;
        const formations = <?php echo json_encode(FORMATIONS); ?>;

        lucide.createIcons();

        $('#formation').val('<?php echo $saved_formation; ?>');

        // Initialize selectedPlayers array if empty
        if (selectedPlayers.length === 0) {
            const formation = $('#formation').val();
            const positions = formations[formation].positions;
            let totalSlots = 0;
            positions.forEach(line => totalSlots += line.length);
            selectedPlayers = new Array(totalSlots).fill(null);
        }

        renderPlayers();
        renderField();

        function renderPlayers() {
            const $list = $('#playerList').empty();

            selectedPlayers.forEach((player, idx) => {
                if (player) {
                    $list.append(`
                        <div class="flex items-center justify-between p-2 border rounded bg-blue-50">
                            <span class="font-medium">${player.name}</span>
                            <span class="text-xs text-gray-500 bg-gray-100 px-2 py-1 rounded">${player.position}</span>
                        </div>
                    `);
                }
            });

            if ($list.children().length === 0) {
                $list.append('<div class="text-center text-gray-500 py-8">No players selected</div>');
            }
        }

        function getPositionForSlot(slotIdx) {
            const formation = $('#formation').val();
            const formationData = formations[formation];
            const roles = formationData.roles;

            // Now roles array directly maps to slot indices
            if (slotIdx >= 0 && slotIdx < roles.length) {
                return roles[slotIdx];
            }
            return 'GK';
        }

        function getPositionColors(position) {
            const colorMap = {
                // Goalkeeper - Yellow/Orange
                'GK': {
                    bg: 'bg-amber-400',
                    border: 'border-amber-500',
                    text: 'text-amber-800',
                    emptyBg: 'bg-amber-400 bg-opacity-30',
                    emptyBorder: 'border-amber-400'
                },
                // Defenders - Green
                'CB': {
                    bg: 'bg-emerald-400',
                    border: 'border-emerald-500',
                    text: 'text-emerald-800',
                    emptyBg: 'bg-emerald-400 bg-opacity-30',
                    emptyBorder: 'border-emerald-400'
                },
                'LB': {
                    bg: 'bg-emerald-400',
                    border: 'border-emerald-500',
                    text: 'text-emerald-800',
                    emptyBg: 'bg-emerald-400 bg-opacity-30',
                    emptyBorder: 'border-emerald-400'
                },
                'RB': {
                    bg: 'bg-emerald-400',
                    border: 'border-emerald-500',
                    text: 'text-emerald-800',
                    emptyBg: 'bg-emerald-400 bg-opacity-30',
                    emptyBorder: 'border-emerald-400'
                },
                'LWB': {
                    bg: 'bg-emerald-400',
                    border: 'border-emerald-500',
                    text: 'text-emerald-800',
                    emptyBg: 'bg-emerald-400 bg-opacity-30',
                    emptyBorder: 'border-emerald-400'
                },
                'RWB': {
                    bg: 'bg-emerald-400',
                    border: 'border-emerald-500',
                    text: 'text-emerald-800',
                    emptyBg: 'bg-emerald-400 bg-opacity-30',
                    emptyBorder: 'border-emerald-400'
                },
                // Midfielders - Blue
                'CDM': {
                    bg: 'bg-blue-400',
                    border: 'border-blue-500',
                    text: 'text-blue-800',
                    emptyBg: 'bg-blue-400 bg-opacity-30',
                    emptyBorder: 'border-blue-400'
                },
                'CM': {
                    bg: 'bg-blue-400',
                    border: 'border-blue-500',
                    text: 'text-blue-800',
                    emptyBg: 'bg-blue-400 bg-opacity-30',
                    emptyBorder: 'border-blue-400'
                },
                'CAM': {
                    bg: 'bg-blue-400',
                    border: 'border-blue-500',
                    text: 'text-blue-800',
                    emptyBg: 'bg-blue-400 bg-opacity-30',
                    emptyBorder: 'border-blue-400'
                },
                'LM': {
                    bg: 'bg-blue-400',
                    border: 'border-blue-500',
                    text: 'text-blue-800',
                    emptyBg: 'bg-blue-400 bg-opacity-30',
                    emptyBorder: 'border-blue-400'
                },
                'RM': {
                    bg: 'bg-blue-400',
                    border: 'border-blue-500',
                    text: 'text-blue-800',
                    emptyBg: 'bg-blue-400 bg-opacity-30',
                    emptyBorder: 'border-blue-400'
                },
                // Forwards/Strikers - Red
                'LW': {
                    bg: 'bg-red-400',
                    border: 'border-red-500',
                    text: 'text-red-800',
                    emptyBg: 'bg-red-400 bg-opacity-30',
                    emptyBorder: 'border-red-400'
                },
                'RW': {
                    bg: 'bg-red-400',
                    border: 'border-red-500',
                    text: 'text-red-800',
                    emptyBg: 'bg-red-400 bg-opacity-30',
                    emptyBorder: 'border-red-400'
                },
                'ST': {
                    bg: 'bg-red-400',
                    border: 'border-red-500',
                    text: 'text-red-800',
                    emptyBg: 'bg-red-400 bg-opacity-30',
                    emptyBorder: 'border-red-400'
                },
                'CF': {
                    bg: 'bg-red-400',
                    border: 'border-red-500',
                    text: 'text-red-800',
                    emptyBg: 'bg-red-400 bg-opacity-30',
                    emptyBorder: 'border-red-400'
                }
            };

            return colorMap[position] || colorMap['GK'];
        }

        function renderField() {
            const formation = $('#formation').val();
            const positions = formations[formation].positions;
            const $field = $('#field').empty();

            let playerIdx = 0;
            positions.forEach((line, lineIdx) => {
                line.forEach(xPos => {
                    const player = selectedPlayers[playerIdx];
                    const yPos = 100 - ((lineIdx + 1) * (100 / (positions.length + 1)));
                    const idx = playerIdx;

                    const requiredPosition = getPositionForSlot(idx);
                    const colors = getPositionColors(requiredPosition);

                    if (player) {
                        $field.append(`
                            <div class="absolute cursor-pointer player-slot" 
                                 style="left: ${xPos}%; top: ${yPos}%; transform: translate(-50%, -50%);" data-idx="${idx}">
                                <div class="relative">
                                    <div class="w-16 h-16 bg-white rounded-full flex flex-col items-center justify-center shadow-lg border-2 ${colors.border}">
                                        <i data-lucide="user" class="w-5 h-5 text-gray-600"></i>
                                        <span class="text-xs font-bold text-gray-700">${requiredPosition}</span>
                                    </div>
                                    <div class="absolute top-full left-1/2 transform -translate-x-1/2 mt-1 whitespace-nowrap">
                                        <div class="text-white text-xs font-bold bg-black bg-opacity-70 px-2 py-1 rounded">${player.name}</div>
                                    </div>
                                </div>
                            </div>
                        `);
                    } else {
                        $field.append(`
                            <div class="absolute cursor-pointer empty-slot" 
                                 style="left: ${xPos}%; top: ${yPos}%; transform: translate(-50%, -50%);" data-idx="${idx}">
                                <div class="w-16 h-16 bg-white bg-opacity-20 rounded-full flex flex-col items-center justify-center border-2 border-white border-dashed hover:bg-opacity-30 transition">
                                    <i data-lucide="plus" class="w-4 h-4 text-white"></i>
                                    <span class="text-xs font-bold text-white">${requiredPosition}</span>
                                </div>
                            </div>
                        `);
                    }
                    playerIdx++;
                });
            });

            lucide.createIcons();

            $('.player-slot').click(function () {
                currentSlotIdx = $(this).data('idx');
                openPlayerModal();
            });

            $('.empty-slot').click(function () {
                currentSlotIdx = $(this).data('idx');
                openPlayerModal();
            });

            $('.player-slot').on('contextmenu', function (e) {
                e.preventDefault();
                const idx = $(this).data('idx');
                selectedPlayers[idx] = null;
                renderPlayers();
                renderField();
            });
        }

        function openPlayerModal() {
            const requiredPosition = getPositionForSlot(currentSlotIdx);
            $('#modalTitle').text(`Select ${requiredPosition} Player`);
            $('#customPlayerLabel').text(`Custom ${requiredPosition} Player Name`);
            $('#customPlayerName').attr('placeholder', `Enter custom ${requiredPosition} name...`);
            $('#playerModal').removeClass('hidden');
            $('#customPlayerName').val('');
            $('#playerSearch').val('');
            renderModalPlayers('');
            lucide.createIcons();
        }

        function renderModalPlayers(search) {
            const $list = $('#modalPlayerList').empty();
            const searchLower = search.toLowerCase();
            const requiredPosition = getPositionForSlot(currentSlotIdx);

            players.forEach((player, idx) => {
                const isSelected = selectedPlayers.some(p => p && p.name === player.name);
                const matchesPosition = player.position === requiredPosition;
                const matchesSearch = player.name.toLowerCase().includes(searchLower);

                if (!isSelected && matchesPosition && matchesSearch) {
                    $list.append(`
                        <div class="flex items-center justify-between p-3 border rounded hover:bg-blue-50 cursor-pointer modal-player-item" data-idx="${idx}">
                            <span class="font-medium">${player.name}</span>
                            <span class="text-xs text-gray-500 bg-gray-100 px-2 py-1 rounded">${player.position}</span>
                        </div>
                    `);
                }
            });

            if ($list.children().length === 0) {
                $list.append('<div class="text-center text-gray-500 py-4">No players available</div>');
            }

            $('.modal-player-item').click(function () {
                const idx = $(this).data('idx');
                selectedPlayers[currentSlotIdx] = players[idx];
                $('#playerModal').addClass('hidden');
                renderPlayers();
                renderField();
            });
        }

        $('#addCustomPlayer').click(function () {
            const customName = $('#customPlayerName').val().trim();
            if (customName) {
                const requiredPosition = getPositionForSlot(currentSlotIdx);
                selectedPlayers[currentSlotIdx] = { name: customName, position: requiredPosition };
                $('#playerModal').addClass('hidden');
                renderPlayers();
                renderField();
            }
        });

        $('#customPlayerName').keypress(function (e) {
            if (e.which === 13) {
                $('#addCustomPlayer').click();
            }
        });

        $('#playerSearch').on('input', function () {
            renderModalPlayers($(this).val());
        });

        $('#closeModal').click(function () {
            $('#playerModal').addClass('hidden');
        });

        $('#playerModal').click(function (e) {
            if (e.target === this) {
                $(this).addClass('hidden');
            }
        });

        $('#formation').change(function () {
            const formation = $('#formation').val();
            const newFormation = formations[formation];
            const newRoles = newFormation.roles;

            // Keep ALL existing players
            const existingPlayers = selectedPlayers.filter(p => p !== null);
            const newPlayers = new Array(newRoles.length).fill(null);

            // Group existing players by their exact position
            const playersByPosition = {};
            existingPlayers.forEach(player => {
                if (!playersByPosition[player.position]) {
                    playersByPosition[player.position] = [];
                }
                playersByPosition[player.position].push(player);
            });

            // Assign players to new formation slots based on exact position match
            newRoles.forEach((requiredRole, slotIdx) => {
                if (playersByPosition[requiredRole] && playersByPosition[requiredRole].length > 0) {
                    newPlayers[slotIdx] = playersByPosition[requiredRole].shift();
                }
            });

            // Try to fit remaining players in compatible positions
            const remainingPlayers = [];
            Object.values(playersByPosition).forEach(positionPlayers => {
                remainingPlayers.push(...positionPlayers);
            });

            // Fill empty slots with any remaining players (less strict matching)
            for (let i = 0; i < newPlayers.length && remainingPlayers.length > 0; i++) {
                if (newPlayers[i] === null) {
                    newPlayers[i] = remainingPlayers.shift();
                }
            }

            selectedPlayers = newPlayers;
            renderPlayers();
            renderField();
        });

        $('#resetTeam').click(function () {
            Swal.fire({
                icon: 'warning',
                title: 'Reset Team?',
                text: 'This will reload your last saved team and discard current changes.',
                showCancelButton: true,
                confirmButtonColor: '#ef4444',
                cancelButtonColor: '#6b7280',
                confirmButtonText: 'Reset Team',
                cancelButtonText: 'Cancel'
            }).then((result) => {
                if (result.isConfirmed) {
                    location.reload();
                }
            });
        });

        $('#saveTeam').click(function () {
            const filledSlots = selectedPlayers.filter(p => p !== null).length;
            const totalSlots = selectedPlayers.length;

            if (filledSlots === 0) {
                Swal.fire({
                    icon: 'warning',
                    title: 'No Players Selected',
                    text: 'Please select at least 1 player before saving',
                    confirmButtonColor: '#3b82f6'
                });
                return;
            }

            let confirmTitle = `Save Team (${filledSlots}/${totalSlots} players)`;
            let confirmText = filledSlots < totalSlots
                ? 'Your team is not complete. You can continue adding players later.'
                : 'Save your complete team?';

            Swal.fire({
                icon: 'question',
                title: confirmTitle,
                text: confirmText,
                showCancelButton: true,
                confirmButtonColor: '#3b82f6',
                cancelButtonColor: '#6b7280',
                confirmButtonText: 'Save Team',
                cancelButtonText: 'Cancel'
            }).then((result) => {
                if (result.isConfirmed) {
                    $.post('save_team.php', {
                        formation: $('#formation').val(),
                        team: JSON.stringify(selectedPlayers)
                    }, function (response) {
                        if (response.redirect) {
                            window.location.href = response.redirect;
                        } else if (response.success) {
                            Swal.fire({
                                icon: 'success',
                                title: 'Team Saved!',
                                text: filledSlots === totalSlots
                                    ? 'Your complete team has been saved successfully!'
                                    : `Team saved successfully! (${filledSlots}/${totalSlots} players selected)`,
                                confirmButtonColor: '#10b981'
                            });
                        } else {
                            Swal.fire({
                                icon: 'error',
                                title: 'Save Failed',
                                text: response.message || 'Failed to save team. Please try again.',
                                confirmButtonColor: '#ef4444'
                            });
                        }
                    }, 'json').fail(function () {
                        Swal.fire({
                            icon: 'error',
                            title: 'Connection Error',
                            text: 'Unable to save team. Please check your connection and try again.',
                            confirmButtonColor: '#ef4444'
                        });
                    });
                }
            });
        });

        $('#logoutBtn').click(function () {
            $.post('auth.php', { action: 'logout' }, function () {
                window.location.href = 'index.php';
            }, 'json');
        });
    </script>
</body>

</html>