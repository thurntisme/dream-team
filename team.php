<?php
session_start();

require_once 'config/config.php';
require_once 'config/constants.php';
require_once 'partials/layout.php';
require_once 'controllers/team-controller.php';

try {
    // Initialize team controller
    $teamController = new TeamController($_SESSION['user_id']);

    // Process all team data using the controller
    $teamData = $teamController->processTeamData();

    // Extract variables for backward compatibility
    $user = $teamData['user'];
    $saved_formation = $teamData['saved_formation'];
    $saved_team = $teamData['saved_team'];
    $saved_substitutes = $teamData['saved_substitutes'];
    $user_budget = $teamData['user_budget'];
    $max_players = $teamData['max_players'];
    $team_data = $teamData['team_data'];
    $substitutes_data = $teamData['substitutes_data'];
    $team_value = $teamData['team_value'];
    $club_ranking = $teamData['ranking'];
    $total_clubs = $teamData['total_clubs'];
    $club_level = $teamData['club_level'];
    $club_exp = $teamData['club_exp'];
    $level_name = $teamData['level_name'];
} catch (Exception $e) {
    header('Location: install.php');
    exit;
}

// Calculate player counts for use throughout the page
$starting_players = count(array_filter($team_data ?: [], fn($p) => $p !== null));
$substitute_data = json_decode($saved_substitutes, true) ?: [];
$substitute_players = count(array_filter($substitute_data, fn($p) => $p !== null));
$total_players = $starting_players + $substitute_players;


// Start content capture
startContent();
?>

<div class="container mx-auto p-4">
    <?php include 'components/league-validation-errors.php'; ?>
    <?php include 'components/club-overview-section.php'; ?>
    <?php include 'components/training-center.php'; ?>

    <div class="grid grid-cols-1 lg:grid-cols-7 gap-4">
        <div class="lg:col-span-2 gap-4">
            <?php include 'components/team-selector.php'; ?>
        </div>

        <div class="lg:col-span-3">
            <?php include 'components/team-field.php'; ?>
        </div>

        <div class="lg:col-span-2">
            <?php include 'components/player-selector.php'; ?>
        </div>
    </div>

    <div class="flex justify-center gap-4">
        <div class="lg:col-span-2 mt-4 flex justify-center gap-3">
            <button id="bestLineup"
                class="bg-purple-600 text-white px-6 py-2 rounded-lg hover:bg-purple-700 flex items-center gap-2">
                <i data-lucide="wand-2" class="w-4 h-4"></i>
                Best Line-up
            </button>
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

<?php include 'components/player-selection-modal.php'; ?>

<?php include 'components/player-info-modal.php'; ?>

<?php include 'components/player-recommendations-modal.php'; ?>

<script src="https://code.jquery.com/ui/1.13.2/jquery-ui.min.js"></script>
<script>
    // Best Line-up Logic
    $('#bestLineup').click(function() {
        // 1. Gather all current players (Starting + Subs)
        let squad = [];
        selectedPlayers.forEach(p => { if(p) squad.push(p); });
        substitutePlayers.forEach(p => { if(p) squad.push(p); });
        
        // Remove duplicates if any (by uuid)
        const uniqueSquad = [];
        const seenUuids = new Set();
        squad.forEach(p => {
            const id = p.uuid || p.id;
            if (id && !seenUuids.has(id)) {
                seenUuids.add(id);
                uniqueSquad.push(p);
            } else if (!id) {
                 uniqueSquad.push(p);
            }
        });
        squad = uniqueSquad;

        if (squad.length === 0) {
            Swal.fire({
                icon: 'warning',
                title: 'No Players',
                text: 'You need players in your squad to generate a line-up.',
            });
            return;
        }

        const formation = $('#formation').val();
        const roles = formations[formation].roles; // e.g. ['GK', 'LB', 'CB', ...]
        
        const newStarting = new Array(roles.length).fill(null);
        const usedPlayerIds = new Set();

        // 2. Fill Starting XI
        roles.forEach((role, slotIdx) => {
            // Filter candidates not yet used
            let candidates = squad.filter(p => !usedPlayerIds.has(p.uuid || p.id));
            
            // Score candidates for this role
            // Criteria: 
            // - Fitness > 20
            // - Form >= 6.5 (Good)
            // - Position Match (Main or Playable)
            
            // I'll filter first by strict criteria
            let strictCandidates = candidates.filter(p => {
                const fitness = p.fitness !== undefined ? p.fitness : 100;
                if (fitness <= 20) return false;
                
                const form = p.form !== undefined ? p.form : 7;
                if (form < 6.5) return false; // 6.5 is "Good"

                // Position Check
                const isMain = p.position === role;
                const isPlayable = p.playablePositions && (
                    Array.isArray(p.playablePositions) ? p.playablePositions.includes(role) : p.playablePositions == role
                );
                return isMain || isPlayable;
            });
            
            // If strict candidates exist, pick best by rating
            if (strictCandidates.length > 0) {
                strictCandidates.sort((a, b) => getEffectiveRating(b) - getEffectiveRating(a));
                const best = strictCandidates[0];
                newStarting[slotIdx] = best;
                usedPlayerIds.add(best.uuid || best.id);
            } else {
                // Fallback: Relax Form/Fitness, but keep Position
                let positionCandidates = candidates.filter(p => {
                     const isMain = p.position === role;
                     const isPlayable = p.playablePositions && (
                        Array.isArray(p.playablePositions) ? p.playablePositions.includes(role) : p.playablePositions == role
                    );
                    return isMain || isPlayable;
                });
                
                if (positionCandidates.length > 0) {
                     positionCandidates.sort((a, b) => getEffectiveRating(b) - getEffectiveRating(a));
                     const best = positionCandidates[0];
                     newStarting[slotIdx] = best;
                     usedPlayerIds.add(best.uuid || best.id);
                } else {
                    // Fallback: Best available player regardless of position (Out of Position)
                     candidates.sort((a, b) => getEffectiveRating(b) - getEffectiveRating(a));
                     if (candidates.length > 0) {
                         const best = candidates[0];
                         newStarting[slotIdx] = best;
                         usedPlayerIds.add(best.uuid || best.id);
                     }
                }
            }
        });

        // 3. Fill Substitutes with remaining players
        const remainingPlayers = squad.filter(p => !usedPlayerIds.has(p.uuid || p.id));
        // Sort remaining by rating
        remainingPlayers.sort((a, b) => getEffectiveRating(b) - getEffectiveRating(a));
        
        const subCount = Math.max(substitutePlayers.length, 7);
        const newSubs = new Array(subCount).fill(null);
        
        remainingPlayers.forEach((p, i) => {
            if (i < subCount) newSubs[i] = p;
        });

        // Apply changes
        selectedPlayers = newStarting;
        substitutePlayers = newSubs;
        
        renderPlayers();
        renderField();
        renderSubstitutes();
        
        // Recalculate overview stats
        let totalValue = 0;
        let playerCount = 0;
        let totalRating = 0;
        let ratedPlayers = 0;

        selectedPlayers.forEach(player => {
            if (player) {
                playerCount++;
                totalValue += player.value || 0;
                if (player.rating && player.rating > 0) {
                    totalRating += player.rating;
                    ratedPlayers++;
                }
            }
        });
        
        updateClubOverviewStats(totalValue, playerCount, ratedPlayers > 0 ? totalRating / ratedPlayers : 0);
        
        Swal.fire({
            icon: 'success',
            title: 'Best Line-up Selected',
            text: 'Your team has been optimized based on fitness, form, and positions.',
            timer: 2000,
            showConfirmButton: false,
            toast: true,
            position: 'top-end'
        });
    });

</script>
<script>
    const players = <?php echo json_encode(getDefaultPlayers()); ?>;
    let maxBudget = <?php echo $user_budget; ?>; // User's maximum budget
    const maxPlayers = <?php echo $max_players; ?>; // Maximum squad size
    const imageBaseUrl = '<?= PLAYER_IMAGES_BASE_PATH ?>';

    let selectedPlayerIdx = null; // Track which player is currently selected
    let selectedSubIdx = null; // Track which substitute is currently selected

    let savedTeam = <?php echo $saved_team; ?>;
    let selectedPlayers = Array.isArray(savedTeam) && savedTeam.length > 0 ? savedTeam : [];
    let savedSubstitutes = <?php echo $saved_substitutes; ?>;
    let substitutePlayers = Array.isArray(savedSubstitutes) && savedSubstitutes.length > 0 ? savedSubstitutes : [];
    let currentSlotIdx = null;
    let isSelectingSubstitute = false; // Track if we're selecting for substitutes
    const formations = <?php echo json_encode(FORMATIONS); ?>;

    lucide.createIcons();

    // UUID generation function
    function generateUUID() {
        return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function(c) {
            var r = Math.random() * 16 | 0,
                v = c == 'x' ? r : (r & 0x3 | 0x8);
            return v.toString(16);
        });
    }

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
    renderSubstitutes();

    // Initialize club overview stats on page load
    let initialTotalValue = 0;
    let initialPlayerCount = 0;
    let initialTotalRating = 0;
    let initialRatedPlayers = 0;

    selectedPlayers.forEach(player => {
        if (player) {
            initialPlayerCount++;
            initialTotalValue += player.value || 0;
            if (player.rating && player.rating > 0) {
                initialTotalRating += player.rating;
                initialRatedPlayers++;
            }
        }
    });

    updateClubOverviewStats(initialTotalValue, initialPlayerCount, initialRatedPlayers > 0 ? initialTotalRating / initialRatedPlayers : 0);

    // Format market value for display
    function formatMarketValue(value) {
        if (value >= 1000000) {
            return '€' + (value / 1000000).toFixed(1) + 'M';
        } else if (value >= 1000) {
            return '€' + Math.round(value / 1000) + 'K';
        } else {
            return '€' + value.toLocaleString();
        }
    }

    // Get effective player rating based on fitness and form
    function getEffectiveRating(player) {
        const baseRating = player.rating || 70;
        const fitness = player.fitness || 100;
        const form = player.form || 7;

        // Fitness affects rating (0.5-1.0 multiplier)
        const fitnessMultiplier = 0.5 + (fitness / 200);

        // Form affects rating (-5 to +5 points)
        const formBonus = (form - 7) * 0.7;

        const effectiveRating = (baseRating * fitnessMultiplier) + formBonus;

        return Math.max(1, Math.min(99, Math.round(effectiveRating)));
    }

    // Get fitness status text
    function getFitnessStatusText(fitness) {
        if (fitness >= 90) return 'Excellent';
        if (fitness >= 75) return 'Good';
        if (fitness >= 60) return 'Average';
        if (fitness >= 40) return 'Poor';
        return 'Injured';
    }

    // Get fitness status color
    function getFitnessStatusColor(fitness) {
        if (fitness >= 90) return 'bg-green-100 text-green-800';
        if (fitness >= 75) return 'bg-blue-100 text-blue-800';
        if (fitness >= 60) return 'bg-yellow-100 text-yellow-800';
        if (fitness >= 40) return 'bg-orange-100 text-orange-800';
        return 'bg-red-100 text-red-800';
    }

    // Get form status text
    function getFormStatusText(form) {
        if (form >= 8.5) return 'Superb';
        if (form >= 7.5) return 'Excellent';
        if (form >= 6.5) return 'Good';
        if (form >= 5.5) return 'Average';
        if (form >= 4) return 'Poor';
        return 'Terrible';
    }

    // Get form status color
    function getFormStatusColor(form) {
        if (form >= 8.5) return 'bg-purple-100 text-purple-800';
        if (form >= 7.5) return 'bg-green-100 text-green-800';
        if (form >= 6.5) return 'bg-blue-100 text-blue-800';
        if (form >= 5.5) return 'bg-yellow-100 text-yellow-800';
        if (form >= 4) return 'bg-orange-100 text-orange-800';
        return 'bg-red-100 text-red-800';
    }

    // Get fitness progress bar color
    function getFitnessProgressColor(fitness) {
        if (fitness >= 90) return 'bg-green-500';
        if (fitness >= 75) return 'bg-blue-500';
        if (fitness >= 60) return 'bg-yellow-500';
        if (fitness >= 40) return 'bg-orange-500';
        return 'bg-red-500';
    }

    // Check if player is injured
    function isPlayerInjured(player) {
        return player.injury && player.injury.days_remaining > 0;
    }

    // Get injury status text
    function getInjuryStatusText(player) {
        if (!isPlayerInjured(player)) return '';
        const injury = player.injury;
        return `${injury.name} (${injury.days_remaining} days)`;
    }

    // Get injury status badge
    function getInjuryBadge(player) {
        if (!isPlayerInjured(player)) return '';
        return '<span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-red-100 text-red-800 ml-2">⚕️ Injured</span>';
    }

    // Get form badge color
    function getFormBadgeColor(form) {
        if (form >= 8.5) return 'bg-purple-100 text-purple-800 border border-purple-200';
        if (form >= 7.5) return 'bg-green-100 text-green-800 border border-green-200';
        if (form >= 6.5) return 'bg-blue-100 text-blue-800 border border-blue-200';
        if (form >= 5.5) return 'bg-yellow-100 text-yellow-800 border border-yellow-200';
        if (form >= 4) return 'bg-orange-100 text-orange-800 border border-orange-200';
        return 'bg-red-100 text-red-800 border border-red-200';
    }

    // Get form arrow icon based on form level
    function getFormArrowIcon(form) {
        if (form >= 8) return '<i data-lucide="trending-up" class="w-3 h-3"></i>';
        if (form >= 6.5) return '<i data-lucide="arrow-up" class="w-3 h-3"></i>';
        if (form >= 5.5) return '<i data-lucide="minus" class="w-3 h-3"></i>';
        if (form >= 4) return '<i data-lucide="arrow-down" class="w-3 h-3"></i>';
        return '<i data-lucide="trending-down" class="w-3 h-3"></i>';
    }

    // Get contract status information
    function getContractStatus(player) {
        const remaining = player.contract_matches_remaining || player.contract_matches || 25;

        if (remaining <= 0) {
            return {
                text: 'Expired',
                color: 'text-red-600',
                bg: 'bg-red-100',
                border: 'border-red-200',
                urgency: 'critical'
            };
        } else if (remaining <= 3) {
            return {
                text: 'Expiring Soon',
                color: 'text-red-600',
                bg: 'bg-red-100',
                border: 'border-red-200',
                urgency: 'high'
            };
        } else if (remaining <= 8) {
            return {
                text: 'Renewal Needed',
                color: 'text-orange-600',
                bg: 'bg-orange-100',
                border: 'border-orange-200',
                urgency: 'medium'
            };
        } else if (remaining <= 15) {
            return {
                text: 'Active',
                color: 'text-yellow-600',
                bg: 'bg-yellow-100',
                border: 'border-yellow-200',
                urgency: 'low'
            };
        } else {
            return {
                text: 'Secure',
                color: 'text-green-600',
                bg: 'bg-green-100',
                border: 'border-green-200',
                urgency: 'none'
            };
        }
    }

    // Get player level display information
    function getLevelDisplayInfo(level) {
        if (level >= 40) {
            return {
                text: 'Legendary',
                color: 'text-purple-600',
                bg: 'bg-purple-100',
                border: 'border-purple-200'
            };
        } else if (level >= 30) {
            return {
                text: 'Elite',
                color: 'text-yellow-600',
                bg: 'bg-yellow-100',
                border: 'border-yellow-200'
            };
        } else if (level >= 20) {
            return {
                text: 'Expert',
                color: 'text-blue-600',
                bg: 'bg-blue-100',
                border: 'border-blue-200'
            };
        } else if (level >= 10) {
            return {
                text: 'Professional',
                color: 'text-green-600',
                bg: 'bg-green-100',
                border: 'border-green-200'
            };
        } else if (level >= 5) {
            return {
                text: 'Experienced',
                color: 'text-orange-600',
                bg: 'bg-orange-100',
                border: 'border-orange-200'
            };
        } else {
            return {
                text: 'Rookie',
                color: 'text-gray-600',
                bg: 'bg-gray-100',
                border: 'border-gray-200'
            };
        }
    }

    // Get player level status
    function getPlayerLevelStatus(player) {
        const level = player.level || 1;
        const experience = player.experience || 0;

        if (level >= 50) {
            return {
                level: level,
                experience: experience,
                experienceForNext: 0,
                progressPercentage: 100,
                isMaxLevel: true
            };
        }

        // Calculate experience requirements (matching PHP logic)
        function getExperienceForLevel(lvl) {
            return lvl * 100 + (lvl - 1) * 50;
        }

        function getTotalExperienceForLevel(targetLevel) {
            let total = 0;
            for (let i = 1; i < targetLevel; i++) {
                total += getExperienceForLevel(i + 1);
            }
            return total;
        }

        const totalRequiredCurrent = getTotalExperienceForLevel(level);
        const totalRequiredNext = getTotalExperienceForLevel(level + 1);
        const experienceForNext = totalRequiredNext - experience;
        const experienceInCurrentLevel = experience - totalRequiredCurrent;
        const experienceNeededForLevel = totalRequiredNext - totalRequiredCurrent;

        const progressPercentage = experienceNeededForLevel > 0 ?
            (experienceInCurrentLevel / experienceNeededForLevel) * 100 :
            0;

        return {
            level: level,
            experience: experience,
            experienceForNext: experienceForNext,
            experienceProgress: experienceInCurrentLevel,
            experienceNeeded: experienceNeededForLevel,
            progressPercentage: Math.min(100, Math.max(0, progressPercentage)),
            isMaxLevel: false
        };
    }

    // Get card level display information
    function getCardLevelDisplayInfo(cardLevel) {
        if (cardLevel >= 10) {
            return {
                text: 'Diamond',
                color: 'text-cyan-600',
                bg: 'bg-cyan-100',
                border: 'border-cyan-200',
                icon: 'diamond'
            };
        } else if (cardLevel >= 8) {
            return {
                text: 'Platinum',
                color: 'text-purple-600',
                bg: 'bg-purple-100',
                border: 'border-purple-200',
                icon: 'star'
            };
        } else if (cardLevel >= 6) {
            return {
                text: 'Gold',
                color: 'text-yellow-600',
                bg: 'bg-yellow-100',
                border: 'border-yellow-200',
                icon: 'award'
            };
        } else if (cardLevel >= 4) {
            return {
                text: 'Silver',
                color: 'text-gray-600',
                bg: 'bg-gray-100',
                border: 'border-gray-200',
                icon: 'medal'
            };
        } else if (cardLevel >= 2) {
            return {
                text: 'Bronze',
                color: 'text-orange-600',
                bg: 'bg-orange-100',
                border: 'border-orange-200',
                icon: 'shield'
            };
        } else {
            return {
                text: 'Basic',
                color: 'text-green-600',
                bg: 'bg-green-100',
                border: 'border-green-200',
                icon: 'user'
            };
        }
    }

    // Calculate card level upgrade cost
    function getCardLevelUpgradeCost(currentLevel, playerValue) {
        const baseCost = currentLevel * 500000; // €0.5M per level
        const valueMultiplier = 1 + (playerValue / 50000000); // +1 for every €50M value
        return Math.floor(baseCost * valueMultiplier);
    }

    // Calculate player salary
    function calculatePlayerSalary(player) {
        const baseSalary = player.base_salary || Math.max(1000, (player.value || 1000000) * 0.001);
        const cardLevel = player.card_level || 1;
        const salaryMultiplier = 1 + ((cardLevel - 1) * 0.2);
        return Math.floor(baseSalary * salaryMultiplier);
    }

    // Get card level benefits
    function getCardLevelBenefits(cardLevel) {
        const ratingBonus = (cardLevel - 1) * 1.0;
        const fitnessBonus = (cardLevel - 1) * 2;
        const salaryIncrease = (cardLevel - 1) * 20;

        return {
            ratingBonus: ratingBonus,
            fitnessBonus: fitnessBonus,
            salaryIncreasePercent: salaryIncrease,
            maxFitness: Math.min(100, 100 + fitnessBonus)
        };
    }

    // Calculate card level upgrade success rate
    function getCardLevelUpgradeSuccessRate(currentLevel) {
        const baseSuccessRate = 85;
        const levelPenalty = (currentLevel - 1) * 10;
        return Math.max(30, baseSuccessRate - levelPenalty);
    }

    function renderPlayers() {
        const $list = $('#playerList').empty();
        let totalValue = 0;
        let playerCount = 0;
        let totalRating = 0;
        let ratedPlayers = 0;

        selectedPlayers.forEach((player, idx) => {
            if (player) {
                playerCount++;
                totalValue += player.value || 0;

                // Calculate ratings for average
                if (player.rating && player.rating > 0) {
                    totalRating += player.rating;
                    ratedPlayers++;
                }

                const isCustom = player.isCustom || false;
                const isSelected = selectedPlayerIdx === idx;

                // Base styling - gray background by default, soft blue when selected
                let bgClass = 'bg-gray-50 border-gray-200';
                const nameClass = isCustom ? 'font-medium text-purple-700' : 'font-medium';
                const valueClass = isCustom ? 'text-sm text-purple-600 font-semibold' : 'text-sm text-green-600 font-semibold';
                const customBadge = isCustom ? '<span class="text-xs text-purple-600 bg-purple-100 px-1 py-0.5 rounded ml-1">CUSTOM</span>' : '';

                // Selected styling - soft blue background
                if (isSelected) {
                    bgClass = 'bg-blue-100 border-blue-300';
                }

                $list.append(`
                        <div class="flex items-center justify-between p-2 border rounded ${bgClass} cursor-pointer transition-all duration-200 player-list-item hover:bg-blue-50" data-idx="${idx}">
                            <div class="flex-1" onclick="selectPlayer(${idx})">
                                <div class="flex items-center gap-2">
                                    <div class="${nameClass}">${player.name}${customBadge}</div>
                                    ${(player.fitness || 100) < 20 ? '<i data-lucide="alert-circle" class="w-4 h-4 text-red-600" title="Low Fitness - Player needs rest"></i>' : ''}
                                </div>
                                <div class="${valueClass}">${formatMarketValue(player.value || 0)}</div>
                                <div class="text-xs text-gray-500 mt-1">${player.position} • ★${getEffectiveRating(player)}</div>
                                <div class="flex gap-2 mt-1 items-center">
                                    <div class="flex-1">
                                        <div class="text-xs text-gray-500 mb-1">Fitness</div>
                                        <div class="w-full bg-gray-200 rounded-full h-2">
                                            <div class="h-2 rounded-full transition-all duration-300 ${getFitnessProgressColor(player.fitness || 100)}" style="width: ${player.fitness || 100}%"></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    `);
            }
        });

        // Update team value and budget summary
        const remainingBudget = maxBudget - totalValue;
        const budgetUsedPercentage = (totalValue / maxBudget) * 100;

        $('#totalTeamValue').text(formatMarketValue(totalValue));
        $('#remainingBudget').text(formatMarketValue(remainingBudget));
        const totalSquadSize = playerCount + substitutePlayers.filter(p => p !== null).length;
        $('#playerCount').text(`${playerCount}/11 starting • ${totalSquadSize}/${maxPlayers} total`);

        // Update budget bar
        $('#budgetBar').css('width', Math.min(budgetUsedPercentage, 100) + '%');

        // Change budget bar color based on usage
        const $budgetBar = $('#budgetBar');
        $budgetBar.removeClass('bg-blue-600 bg-yellow-500 bg-red-600');
        if (budgetUsedPercentage >= 90) {
            $budgetBar.addClass('bg-red-600');
        } else if (budgetUsedPercentage >= 70) {
            $budgetBar.addClass('bg-yellow-500');
        } else {
            $budgetBar.addClass('bg-blue-600');
        }

        // Change remaining budget color if over budget
        const $remainingBudget = $('#remainingBudget');
        $remainingBudget.removeClass('text-blue-600 text-red-600');
        if (remainingBudget < 0) {
            $remainingBudget.addClass('text-red-600');
        } else {
            $remainingBudget.addClass('text-blue-600');
        }

        // Update club overview statistics in real-time
        const totalSquadPlayers = playerCount + substitutePlayers.filter(p => p !== null).length;
        updateClubOverviewStats(totalValue, totalSquadPlayers, ratedPlayers > 0 ? totalRating / ratedPlayers : 0);

        if ($list.children().length === 0) {
            $list.append('<div class="text-center text-gray-500 py-8">No players selected<br><small class="text-xs">Click on field positions to add players</small></div>');
        } else if (selectedPlayerIdx === null && playerCount > 0) {
            $list.append('<div class="text-center text-gray-400 py-2 text-xs border-t mt-2">💡 Click on a player to select and see options</div>');
        }

        lucide.createIcons();
    }

    // Render substitutes list
    function renderSubstitutes() {
        const $list = $('#substitutesList').empty();
        const maxSubstitutes = maxPlayers - 11; // Max substitutes = total squad - starting 11

        // Update substitute count display
        const currentSubsCount = substitutePlayers.filter(p => p !== null).length;
        $('#substituteCountDisplay').text(`${currentSubsCount}/${maxSubstitutes}`);

        substitutePlayers.forEach((player, idx) => {
            if (player) {
                const isCustom = player.isCustom || false;
                const isSelected = selectedSubIdx === idx;
                const bgClass = isSelected ? 'bg-blue-50 border-blue-300 ring-2 ring-blue-300' : 'bg-gray-50 border-gray-200';
                const nameClass = isCustom ? 'font-medium text-purple-700' : 'font-medium';
                const valueClass = isCustom ? 'text-sm text-purple-600 font-semibold' : 'text-sm text-green-600 font-semibold';
                const customBadge = isCustom ? '<span class="text-xs text-purple-600 bg-purple-100 px-1 py-0.5 rounded ml-1">CUSTOM</span>' : '';

                $list.append(`
                    <div class="flex items-center justify-between p-2 border rounded ${bgClass} hover:bg-blue-50 transition-all duration-200 cursor-pointer" onclick="selectSubstitute(${idx})">
                        <div class="flex-1">
                            <div class="flex items-center gap-2">
                                <div class="${nameClass}">${player.name}${customBadge}</div>
                                ${(player.fitness || 100) < 20 ? '<i data-lucide="alert-circle" class="w-4 h-4 text-red-600" title="Low Fitness - Player needs rest"></i>' : ''}
                            </div>
                            <div class="${valueClass}">${formatMarketValue(player.value || 0)}</div>
                            <div class="text-xs text-gray-500 mt-1">${player.position} • ★${getEffectiveRating(player)}</div>
                            <div class="flex gap-2 mt-1 items-center">
                                <div class="flex-1">
                                    <div class="text-xs text-gray-500 mb-1">Fitness</div>
                                    <div class="w-full bg-gray-200 rounded-full h-2">
                                        <div class="h-2 rounded-full transition-all duration-300 ${getFitnessProgressColor(player.fitness || 100)}" style="width: ${player.fitness || 100}%"></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="flex items-center gap-1 ml-2">
                            ${selectedPlayers.findIndex(p => p === null) !== -1 ? `<button onclick="promoteSubstitute(${idx})" class="p-1 text-blue-600 hover:bg-blue-100 rounded transition-colors" title="Promote to Starting XI">
                                <i data-lucide="arrow-up" class="w-4 h-4"></i>
                            </button>` : `<button onclick="switchWithStartingPlayer(${idx})" class="p-1 text-orange-600 hover:bg-orange-100 rounded transition-colors" title="Switch with Starting Player">
                                <i data-lucide="repeat" class="w-4 h-4"></i>
                            </button>`}
                        </div>
                    </div>
                `);
            }
        });

        if ($list.children().length === 0) {
            $list.append('<div class="text-center text-gray-500 py-4">No substitutes selected<br><small class="text-xs">Substitutes will appear here when added</small></div>');
        }

        lucide.createIcons();
    }

    // Function to update club overview statistics in real-time
    function updateClubOverviewStats(teamValue, playerCount, avgRating) {
        // Update team value in club overview
        $('#clubTeamValue').text(formatMarketValue(teamValue));

        // Update player count in club overview (total squad size)
        $('#clubPlayerCount').text(`${playerCount}/${maxPlayers}`);

        // Update average rating in club overview
        $('#clubAvgRating').text(avgRating > 0 ? avgRating.toFixed(1) : '0');

        // Use club level from PHP (passed from server)
        const clubLevel = <?php echo $club_level; ?>;
        const levelName = getClubLevelNameJS(clubLevel);
        const levelColors = getLevelColorJS(clubLevel);

        // Update level badge
        const $levelBadge = $('.inline-flex.items-center.gap-1.px-3.py-1.rounded-full.text-sm.font-medium.border').first();
        if ($levelBadge.length) {
            // Remove old color classes
            $levelBadge.removeClass('bg-purple-100 text-purple-800 border-purple-200 bg-blue-100 text-blue-800 border-blue-200 bg-green-100 text-green-800 border-green-200 bg-yellow-100 text-yellow-800 border-yellow-200 bg-gray-100 text-gray-800 border-gray-200');
            // Add new color classes
            $levelBadge.addClass(levelColors);
            // Update text
            $levelBadge.html(`<i data-lucide="star" class="w-4 h-4"></i> Level ${clubLevel} - ${levelName}`);
        }

        // Update level progress bonus
        const levelBonus = getLevelBonusJS(clubLevel);
        const $levelProgressBonus = $('.text-lg.font-bold.text-purple-600');
        if ($levelProgressBonus.length) {
            $levelProgressBonus.text(`+${levelBonus}% Bonus`);
        }

        // Update challenge status
        const canChallenge = playerCount >= 11;
        const $challengeStatus = $('.text-lg.font-bold').filter(function() {
            return $(this).text() === 'Ready' || $(this).text() === 'Not Ready';
        });

        if ($challengeStatus.length) {
            $challengeStatus.removeClass('text-green-600 text-red-600');
            $challengeStatus.addClass(canChallenge ? 'text-green-600' : 'text-red-600');
            $challengeStatus.text(canChallenge ? 'Ready' : 'Not Ready');

            // Update challenge status description
            const $challengeDesc = $challengeStatus.siblings('.text-sm.text-gray-600.mt-1');
            if ($challengeDesc.length) {
                $challengeDesc.text(canChallenge ? 'Can challenge other clubs' : `Need ${11 - playerCount} more players`);
            }
        }

        // Recreate icons after updating content
        lucide.createIcons();
    }

    // JavaScript version of club level name
    function getClubLevelNameJS(level) {
        if (level >= 40) return 'Legendary';
        if (level >= 35) return 'Mythical';
        if (level >= 30) return 'Elite Master';
        if (level >= 25) return 'Elite';
        if (level >= 20) return 'Professional Master';
        if (level >= 15) return 'Professional';
        if (level >= 10) return 'Semi-Professional';
        if (level >= 5) return 'Amateur';
        return 'Beginner';
    }

    // JavaScript version of level colors
    function getLevelColorJS(level) {
        if (level >= 40) return 'bg-gradient-to-r from-yellow-400 to-orange-500 text-white border-yellow-500';
        if (level >= 35) return 'bg-gradient-to-r from-purple-500 to-pink-500 text-white border-purple-500';
        if (level >= 30) return 'bg-gradient-to-r from-indigo-500 to-purple-600 text-white border-indigo-500';
        if (level >= 25) return 'bg-purple-100 text-purple-800 border-purple-200';
        if (level >= 20) return 'bg-indigo-100 text-indigo-800 border-indigo-200';
        if (level >= 15) return 'bg-blue-100 text-blue-800 border-blue-200';
        if (level >= 10) return 'bg-green-100 text-green-800 border-green-200';
        if (level >= 5) return 'bg-yellow-100 text-yellow-800 border-yellow-200';
        return 'bg-gray-100 text-gray-800 border-gray-200';
    }

    // JavaScript version of level bonus calculation
    function getLevelBonusJS(level) {
        switch (level) {
            case 5:
                return 25;
            case 4:
                return 20;
            case 3:
                return 15;
            case 2:
                return 10;
            case 1:
            default:
                return 0;
        }
    }

    // Function to update club statistics after player changes
    function updateClubStats() {
        // Since renderPlayers() already handles all the summary box updates,
        // we just need to call it to refresh everything
        renderPlayers();
        renderSubstitutes();
    }



    // Remove substitute player
    function removeSubstitute(idx) {
        const player = substitutePlayers[idx];

        Swal.fire({
            title: `Remove ${player.name}?`,
            text: 'This will remove the substitute from your squad',
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#ef4444',
            cancelButtonColor: '#6b7280',
            confirmButtonText: 'Remove Player',
            cancelButtonText: 'Cancel'
        }).then((result) => {
            if (result.isConfirmed) {
                substitutePlayers[idx] = null;
                renderSubstitutes();
                updateClubStats();

                // Auto-save the changes to database
                $.post('api/save_team_api.php', {
                    formation: $('#formation').val(),
                    team: JSON.stringify(selectedPlayers),
                    substitutes: JSON.stringify(substitutePlayers)
                }, function(response) {
                    if (response.success) {
                        Swal.fire({
                            icon: 'success',
                            title: 'Substitute Removed',
                            text: `${player.name} has been removed from your substitutes`,
                            timer: 2000,
                            showConfirmButton: false,
                            toast: true,
                            position: 'top-end'
                        });
                    } else {
                        // If save failed, revert the change
                        substitutePlayers[idx] = player;
                        renderSubstitutes();
                        updateClubStats();

                        Swal.fire({
                            icon: 'error',
                            title: 'Failed to Remove Substitute',
                            text: response.message || 'Could not save changes. Please try again.',
                            confirmButtonColor: '#ef4444'
                        });
                    }
                }, 'json').fail(function() {
                    // If request failed, revert the change
                    substitutePlayers[idx] = player;
                    renderSubstitutes();
                    updateClubStats();

                    Swal.fire({
                        icon: 'error',
                        title: 'Connection Error',
                        text: 'Could not save changes. Please check your connection and try again.',
                        confirmButtonColor: '#ef4444'
                    });
                });
            }
        });
    }

    // Promote substitute to starting XI
    function promoteSubstitute(subIdx) {
        const substitute = substitutePlayers[subIdx];

        // Find empty slot in starting XI or ask user to replace
        const emptyStartingSlot = selectedPlayers.findIndex(p => p === null);

        if (emptyStartingSlot !== -1) {
            // Move to empty starting slot
            selectedPlayers[emptyStartingSlot] = substitute;
            substitutePlayers[subIdx] = null;

            renderPlayers();
            renderField();
            renderSubstitutes();
            updateClubStats();

            // Auto-save the changes to database
            $.post('api/save_team_api.php', {
                formation: $('#formation').val(),
                team: JSON.stringify(selectedPlayers),
                substitutes: JSON.stringify(substitutePlayers)
            }, function(response) {
                if (response.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Player Promoted!',
                        text: `${substitute.name} has been promoted to the starting XI`,
                        timer: 2000,
                        showConfirmButton: false,
                        toast: true,
                        position: 'top-end'
                    });
                } else {
                    // If save failed, revert the change
                    selectedPlayers[emptyStartingSlot] = null;
                    substitutePlayers[subIdx] = substitute;
                    renderPlayers();
                    renderField();
                    renderSubstitutes();
                    updateClubStats();

                    Swal.fire({
                        icon: 'error',
                        title: 'Failed to Promote Player',
                        text: response.message || 'Could not save changes. Please try again.',
                        confirmButtonColor: '#ef4444'
                    });
                }
            }, 'json').fail(function() {
                // If request failed, revert the change
                selectedPlayers[emptyStartingSlot] = null;
                substitutePlayers[subIdx] = substitute;
                renderPlayers();
                renderField();
                renderSubstitutes();
                updateClubStats();

                Swal.fire({
                    icon: 'error',
                    title: 'Connection Error',
                    text: 'Could not save changes. Please check your connection and try again.',
                    confirmButtonColor: '#ef4444'
                });
            });
        } else {
            // Ask user which starting player to replace
            Swal.fire({
                title: 'Replace Starting Player?',
                text: 'Starting XI is full. Which player would you like to replace?',
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#3b82f6',
                cancelButtonColor: '#6b7280',
                confirmButtonText: 'Choose Player to Replace',
                cancelButtonText: 'Cancel'
            }).then((result) => {
                if (result.isConfirmed) {
                    // Show starting players for selection
                    showStartingPlayersForReplacement(subIdx);
                }
            });
        }
    }

    // Show starting players for replacement
    function showStartingPlayersForReplacement(subIdx) {
        const substitute = substitutePlayers[subIdx];
        let playersHtml = '';

        selectedPlayers.forEach((player, idx) => {
            if (player) {
                const position = getPositionForSlot(idx);
                playersHtml += `
                    <div class="flex items-center justify-between p-3 border rounded hover:bg-gray-50 cursor-pointer" onclick="replaceStartingPlayer(${idx}, ${subIdx})">
                        <div>
                            <div class="font-medium">${player.name}</div>
                            <div class="text-sm text-gray-600">${position} • ★${player.rating || 'N/A'}</div>
                        </div>
                        <div class="text-sm text-green-600 font-semibold">${formatMarketValue(player.value || 0)}</div>
                    </div>
                `;
            }
        });

        Swal.fire({
            title: `Promote ${substitute.name}`,
            html: `
                <div class="text-left">
                    <p class="mb-4 text-gray-600">Select a starting player to replace:</p>
                    <div class="space-y-2 max-h-60 overflow-y-auto">
                        ${playersHtml}
                    </div>
                </div>
            `,
            showConfirmButton: false,
            showCancelButton: true,
            cancelButtonText: 'Cancel',
            customClass: {
                popup: 'swal-wide'
            }
        });
    }

    // Replace starting player with substitute
    function replaceStartingPlayer(startingIdx, subIdx) {
        const startingPlayer = selectedPlayers[startingIdx];
        const substitute = substitutePlayers[subIdx];

        // Swap players
        selectedPlayers[startingIdx] = substitute;
        substitutePlayers[subIdx] = startingPlayer;

        renderPlayers();
        renderField();
        renderSubstitutes();
        updateClubStats();

        Swal.close();

        // Auto-save the changes to database
        $.post('api/save_team_api.php', {
            formation: $('#formation').val(),
            team: JSON.stringify(selectedPlayers),
            substitutes: JSON.stringify(substitutePlayers)
        }, function(response) {
            if (response.success) {
                Swal.fire({
                    icon: 'success',
                    title: 'Players Swapped!',
                    text: `${substitute.name} promoted to starting XI, ${startingPlayer.name} moved to substitutes`,
                    timer: 3000,
                    showConfirmButton: false,
                    toast: true,
                    position: 'top-end'
                });
            } else {
                // If save failed, revert the change
                selectedPlayers[startingIdx] = startingPlayer;
                substitutePlayers[subIdx] = substitute;
                renderPlayers();
                renderField();
                renderSubstitutes();
                updateClubStats();

                Swal.fire({
                    icon: 'error',
                    title: 'Failed to Swap Players',
                    text: response.message || 'Could not save changes. Please try again.',
                    confirmButtonColor: '#ef4444'
                });
            }
        }, 'json').fail(function() {
            // If request failed, revert the change
            selectedPlayers[startingIdx] = startingPlayer;
            substitutePlayers[subIdx] = substitute;
            renderPlayers();
            renderField();
            renderSubstitutes();
            updateClubStats();

            Swal.fire({
                icon: 'error',
                title: 'Connection Error',
                text: 'Could not save changes. Please check your connection and try again.',
                confirmButtonColor: '#ef4444'
            });
        });
    }

    // Switch substitute with starting player
    function switchWithStartingPlayer(subIdx) {
        const substitute = substitutePlayers[subIdx];
        let playersHtml = '';

        selectedPlayers.forEach((player, idx) => {
            if (player) {
                const position = getPositionForSlot(idx);
                const isCustom = player.isCustom || false;
                const nameClass = isCustom ? 'font-medium text-purple-700' : 'font-medium';
                const customBadge = isCustom ? '<span class="text-xs text-purple-600 bg-purple-100 px-1 py-0.5 rounded ml-1">CUSTOM</span>' : '';

                playersHtml += `
                    <div class="flex items-center justify-between p-3 border rounded hover:bg-gray-50 cursor-pointer" onclick="performPlayerSwitch(${idx}, ${subIdx})">
                        <div>
                            <div class="${nameClass}">${player.name}${customBadge}${getInjuryBadge(player)}</div>
                            <div class="text-sm text-gray-600">${position} • ★${getEffectiveRating(player)} • Lv.${player.level || 1}</div>
                            <div class="text-xs text-gray-500">
                                Fitness: ${player.fitness || 100}% • Form: ${(player.form || 7).toFixed(1)}
                                ${isPlayerInjured(player) ? `<br><span class="text-red-600">⚕️ ${getInjuryStatusText(player)}</span>` : ''}
                            </div>
                        </div>
                        <div class="text-sm text-green-600 font-semibold">${formatMarketValue(player.value || 0)}</div>
                    </div>
                `;
            }
        });

        Swal.fire({
            title: `Switch ${substitute.name}`,
            html: `
                <div class="text-left">
                    <p class="mb-4 text-gray-600">Select a starting player to switch with:</p>
                    <div class="space-y-2 max-h-60 overflow-y-auto">
                        ${playersHtml}
                    </div>
                </div>
            `,
            showConfirmButton: false,
            showCancelButton: true,
            cancelButtonText: 'Cancel',
            customClass: {
                popup: 'swal-wide'
            }
        });
    }

    // Perform the actual player switch
    function performPlayerSwitch(startingIdx, subIdx) {
        const startingPlayer = selectedPlayers[startingIdx];
        const substitute = substitutePlayers[subIdx];

        // Swap players
        selectedPlayers[startingIdx] = substitute;
        substitutePlayers[subIdx] = startingPlayer;

        renderPlayers();
        renderField();
        renderSubstitutes();
        updateClubStats();

        Swal.close();

        // Auto-save the changes to database
        $.post('api/save_team_api.php', {
            formation: $('#formation').val(),
            team: JSON.stringify(selectedPlayers),
            substitutes: JSON.stringify(substitutePlayers)
        }, function(response) {
            if (response.success) {
                Swal.fire({
                    icon: 'success',
                    title: 'Players Switched!',
                    text: `${substitute.name} ↔ ${startingPlayer.name}`,
                    timer: 3000,
                    showConfirmButton: false,
                    toast: true,
                    position: 'top-end'
                });

                // Add level up notification if applicable
                if (response.level_up) {
                    setTimeout(() => {
                        Swal.fire({
                            icon: 'success',
                            title: 'Level Up!',
                            text: `Congratulations! Your club reached level ${response.level_up.new_level}!`,
                            confirmButtonColor: '#3b82f6'
                        });
                    }, 3500);
                }
            } else {
                // If save failed, revert the change
                selectedPlayers[startingIdx] = startingPlayer;
                substitutePlayers[subIdx] = substitute;
                renderPlayers();
                renderField();
                renderSubstitutes();
                updateClubStats();

                Swal.fire({
                    icon: 'error',
                    title: 'Failed to Switch Players',
                    text: response.message || 'Could not save changes. Please try again.',
                    confirmButtonColor: '#ef4444'
                });
            }
        }, 'json').fail(function() {
            // If request failed, revert the change
            selectedPlayers[startingIdx] = startingPlayer;
            substitutePlayers[subIdx] = substitute;
            renderPlayers();
            renderField();
            renderSubstitutes();
            updateClubStats();

            Swal.fire({
                icon: 'error',
                title: 'Connection Error',
                text: 'Could not save changes. Please check your connection and try again.',
                confirmButtonColor: '#ef4444'
            });
        });
    }

    // Function to select a player (highlight only)
    function selectPlayer(idx) {
        selectedPlayerIdx = selectedPlayerIdx === idx ? null : idx; // Toggle selection
        renderPlayers();
        renderField(); // Update field to show selection
        updateSelectedPlayerInfo(); // Update selected player info box
    }
    
    // Function to select a substitute (highlight only)
    function selectSubstitute(idx) {
        selectedSubIdx = selectedSubIdx === idx ? null : idx; // Toggle selection
        renderSubstitutes();

        if (selectedSubIdx !== null) {
            const player = substitutePlayers[selectedSubIdx];
            if (player) {
                // Reuse player info modal for substitutes
                showPlayerInfo(player);
            }
        }
    }

    // Function to update selected player info box
    function updateSelectedPlayerInfo() {
        const $infoBox = $('#selectedPlayerInfo');

        if (selectedPlayerIdx === null) {
            // No player selected, hide the info box
            $infoBox.addClass('hidden');
            return;
        }

        const player = selectedPlayers[selectedPlayerIdx];

        if (!player) {
            // Empty slot selected, hide the info box
            $infoBox.addClass('hidden');
            return;
        }

        // Show the info box
        $infoBox.removeClass('hidden');

        // Update avatar
        const avatarHtml = getPlayerAvatarHtml(player.name, player.avatar);
        $('#selectedPlayerAvatar').html(avatarHtml);

        // Update basic info
        $('#selectedPlayerName').text(player.name);
        
        // Ensure playablePositions is available (fallback to global list if missing in saved player)
        if (!player.playablePositions && typeof players !== 'undefined') {
             const defaultPlayer = players.find(p => (p.uuid && p.uuid === player.uuid) || (p.id && p.id === player.id));
             if (defaultPlayer && defaultPlayer.playablePositions) {
                 player.playablePositions = defaultPlayer.playablePositions;
             }
        }

        // Display Main Position | Playable Positions
        let positionText = player.position || 'Unknown';
        if (player.playablePositions) {
            const playable = Array.isArray(player.playablePositions) 
                ? player.playablePositions.join(',') 
                : player.playablePositions;
            
            if (playable && playable.length > 0) {
                positionText += ' | ' + playable;
            }
        }
        $('#selectedPlayerPosition').text(positionText);

        // Update rating
        $('#selectedPlayerRating span').text(player.rating || 'N/A');

        // Update value
        $('#selectedPlayerValue').text(formatMarketValue(player.value || 0));

        // Update level
        $('#selectedPlayerLevel').text(player.level || 1);

        // Update card level
        $('#selectedPlayerCardLevel').text(player.card_level || 1);

        // Update fitness
        const fitness = player.fitness || 100;
        const fitnessColor = fitness >= 80 ? 'bg-green-500' : fitness >= 50 ? 'bg-yellow-500' : 'bg-red-500';
        $('#selectedPlayerFitness').html(`
            <div class="text-sm font-bold text-gray-900">${fitness}%</div>
            <div class="w-full bg-gray-200 rounded-full h-1.5">
                <div class="${fitnessColor} h-full rounded-full transition-all duration-300" style="width: ${fitness}%"></div>
            </div>
        `);

        // Update form
        const form = player.form || 7;
        const formBadgeColor = getFormBadgeColor(form);
        const formArrowIcon = getFormArrowIcon(form);
        $('#selectedPlayerForm').html(`
            <span class="px-2 py-1 rounded-full ${formBadgeColor} flex items-center gap-1 text-sm">
                ${formArrowIcon}
                ${form.toFixed(1)}
            </span>
        `);

        // Update nationality
        console.log(player)
        $('#selectedPlayerNationality').text(player.nation || player.nationality || 'Unknown');

        // Update contract (remaining matches)
        const remainingMatches = player.contract_remaining || player.contract_matches_remaining || player.contract_matches || 0;
        const contractColor = remainingMatches <= 5 ? 'text-red-600' : remainingMatches <= 10 ? 'text-yellow-600' : 'text-gray-900';
        $('#selectedPlayerContract').html(`<span class="${contractColor}">${remainingMatches} matches</span>`);

        // Update salary
        const salary = player.salary || 0;
        $('#selectedPlayerSalary').text(formatMarketValue(salary) + '/week');

        // Update action buttons
        $('#playerInfoBtn').off('click').on('click', function() {
            showPlayerInfo(player);
        });

        $('#changePlayerBtn').off('click').on('click', function() {
            choosePlayer(selectedPlayerIdx);
        });

        $('#removePlayerBtn').off('click').on('click', function() {
            removePlayer(selectedPlayerIdx);
        });

        // Update renew contract button
        $('#renewContractBtn').off('click').on('click', function() {
            renewPlayerContract(player, selectedPlayerIdx);
        });

        // Reinitialize lucide icons
        lucide.createIcons();
    }

    // Function to renew player contract
    function renewPlayerContract(player, playerIdx) {
        const renewalCost = Math.floor((player.value || 0) * 0.1); // 10% of player value
        const contractExtension = 20; // Add 20 matches

        Swal.fire({
            title: 'Renew Contract?',
            html: `
                <div class="text-left space-y-3">
                    <div class="bg-gray-50 p-4 rounded-lg">
                        <h4 class="font-semibold text-gray-900 mb-2">Player:</h4>
                        <div class="text-sm">
                            <div class="flex justify-between mb-1">
                                <span class="text-gray-600">Name:</span>
                                <span class="font-medium">${player.name}</span>
                            </div>
                            <div class="flex justify-between mb-1">
                                <span class="text-gray-600">Current Contract:</span>
                                <span class="font-medium">${player.contract_remaining || 0} matches</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-600">After Renewal:</span>
                                <span class="font-medium text-green-600">${(player.contract_remaining || 0) + contractExtension} matches</span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="bg-blue-50 p-4 rounded-lg border border-blue-200">
                        <h4 class="font-semibold text-blue-900 mb-2">Cost:</h4>
                        <div class="text-2xl font-bold text-blue-600">${formatMarketValue(renewalCost)}</div>
                        <p class="text-xs text-gray-600 mt-1">Contract extension: +${contractExtension} matches</p>
                    </div>
                </div>
            `,
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#10b981',
            cancelButtonColor: '#6b7280',
            confirmButtonText: '<i data-lucide="file-signature" class="w-4 h-4 inline mr-1"></i> Renew Contract',
            cancelButtonText: 'Cancel',
            didOpen: () => {
                lucide.createIcons();
            }
        }).then((result) => {
            if (result.isConfirmed) {
                // Show loading
                Swal.fire({
                    title: 'Processing...',
                    text: 'Renewing contract',
                    allowOutsideClick: false,
                    allowEscapeKey: false,
                    showConfirmButton: false,
                    didOpen: () => {
                        Swal.showLoading();
                    }
                });

                // Call API to renew contract
                $.post('api/renew_contract_api.php', {
                    player_uuid: player.uuid,
                    renewal_cost: renewalCost,
                    contract_extension: contractExtension
                }, function(response) {
                    if (response.success) {
                        // Update local player data
                        player.contract_remaining = (player.contract_remaining || 0) + contractExtension;
                        selectedPlayers[playerIdx] = player;

                        // Update budget
                        maxBudget = response.new_budget;
                        $('#clubBudget').text(formatMarketValue(response.new_budget));

                        // Refresh displays
                        updateSelectedPlayerInfo();
                        renderPlayers();
                        updateClubStats();

                        // Show success message
                        Swal.fire({
                            icon: 'success',
                            title: 'Contract Renewed!',
                            html: `
                                <div class="text-center">
                                    <p class="mb-2">${player.name}'s contract has been extended!</p>
                                    <p class="text-sm text-gray-600">New contract: ${player.contract_remaining} matches</p>
                                    <p class="text-sm text-blue-600">Remaining Budget: ${formatMarketValue(response.new_budget)}</p>
                                </div>
                            `,
                            timer: 3000,
                            showConfirmButton: false,
                            toast: true,
                            position: 'top-end'
                        });
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'Renewal Failed',
                            text: response.message || 'Could not renew contract',
                            confirmButtonColor: '#3b82f6'
                        });
                    }
                }).fail(function() {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: 'Failed to renew contract. Please try again.',
                        confirmButtonColor: '#3b82f6'
                    });
                });
            }
        });
    }

    // Function to choose a player (open modal to select any player)
    function choosePlayer(idx) {
        currentSlotIdx = idx;
        openPlayerModal();
    }

    // Function to switch player with currently selected player
    function switchPlayer(idx) {
        if (selectedPlayerIdx === null) {
            // No player selected, just open modal to choose
            choosePlayer(idx);
            return;
        }

        if (selectedPlayerIdx === idx) {
            // Clicking on the same selected player, open modal to change
            choosePlayer(idx);
            return;
        }

        // Switch positions between selected player and clicked player
        const selectedPlayer = selectedPlayers[selectedPlayerIdx];
        const clickedPlayer = selectedPlayers[idx];

        // Swap the players
        selectedPlayers[selectedPlayerIdx] = clickedPlayer;
        selectedPlayers[idx] = selectedPlayer;

        // Clear selection after switch
        selectedPlayerIdx = null;

        // Update display
        renderPlayers();
        renderField();
        updateClubStats();

        // Show confirmation
        Swal.fire({
            icon: 'success',
            title: 'Players Switched!',
            text: `${selectedPlayer ? selectedPlayer.name : 'Empty position'} and ${clickedPlayer ? clickedPlayer.name : 'Empty position'} have been switched`,
            timer: 2000,
            showConfirmButton: false,
            toast: true,
            position: 'top-end'
        });
    }

    // Function to remove a player
    function removePlayer(idx) {
        const player = selectedPlayers[idx];

        Swal.fire({
            title: `Remove ${player.name}?`,
            text: 'This will remove the player from your team',
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#ef4444',
            cancelButtonColor: '#6b7280',
            confirmButtonText: 'Remove Player',
            cancelButtonText: 'Cancel'
        }).then((result) => {
            if (result.isConfirmed) {
                selectedPlayers[idx] = null;
                if (selectedPlayerIdx === idx) {
                    selectedPlayerIdx = null; // Clear selection if removed player was selected
                }
                renderPlayers();
                renderField();
                updateClubStats();

                // Auto-save the changes to database
                $.post('api/save_team_api.php', {
                    formation: $('#formation').val(),
                    team: JSON.stringify(selectedPlayers),
                    substitutes: JSON.stringify(substitutePlayers)
                }, function(response) {
                    if (response.success) {
                        Swal.fire({
                            icon: 'success',
                            title: 'Player Removed',
                            text: `${player.name} has been removed from your team`,
                            timer: 2000,
                            showConfirmButton: false,
                            toast: true,
                            position: 'top-end'
                        });
                    } else {
                        // If save failed, revert the change
                        selectedPlayers[idx] = player;
                        renderPlayers();
                        renderField();
                        updateClubStats();

                        Swal.fire({
                            icon: 'error',
                            title: 'Failed to Remove Player',
                            text: response.message || 'Could not save changes. Please try again.',
                            confirmButtonColor: '#ef4444'
                        });
                    }
                }, 'json').fail(function() {
                    // If request failed, revert the change
                    selectedPlayers[idx] = player;
                    renderPlayers();
                    renderField();
                    updateClubStats();

                    Swal.fire({
                        icon: 'error',
                        title: 'Connection Error',
                        text: 'Could not save changes. Please check your connection and try again.',
                        confirmButtonColor: '#ef4444'
                    });
                });
            }
        });
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

    // Generate player avatar HTML
    function getPlayerAvatarHtml(playerName, imageUrl = null) {
        const colors = [{
                bg: 'bg-blue-600',
                text: 'text-white'
            },
            {
                bg: 'bg-green-600',
                text: 'text-white'
            },
            {
                bg: 'bg-purple-600',
                text: 'text-white'
            },
            {
                bg: 'bg-red-600',
                text: 'text-white'
            },
            {
                bg: 'bg-yellow-600',
                text: 'text-white'
            },
            {
                bg: 'bg-indigo-600',
                text: 'text-white'
            },
            {
                bg: 'bg-pink-600',
                text: 'text-white'
            },
            {
                bg: 'bg-teal-600',
                text: 'text-white'
            },
            {
                bg: 'bg-orange-600',
                text: 'text-white'
            },
            {
                bg: 'bg-cyan-600',
                text: 'text-white'
            }
        ];

        // Get initials from player name
        const nameParts = playerName.trim().split(' ');
        let initials = '';
        if (nameParts.length >= 2) {
            initials = (nameParts[0][0] + nameParts[nameParts.length - 1][0]).toUpperCase();
        } else {
            initials = playerName.substring(0, 2).toUpperCase();
        }

        // Generate consistent color based on name hash
        let hash = 0;
        for (let i = 0; i < playerName.length; i++) {
            hash = ((hash << 5) - hash) + playerName.charCodeAt(i);
            hash = hash & hash;
        }
        const colorIndex = Math.abs(hash) % colors.length;
        const color = colors[colorIndex];

        // If we have a valid image URL, return image tag
        if (imageUrl) {
            return `<img src="${imageBaseUrl + imageUrl}" alt="${playerName}" class="w-full h-full object-cover rounded-full" onerror="this.onerror=null; this.parentElement.innerHTML='<div class=\\'w-full h-full ${color.bg} ${color.text} rounded-full flex items-center justify-center font-bold text-lg\\'>${initials}</div>';">`;
        }

        // Return initials avatar
        return `<div class="w-full h-full ${color.bg} ${color.text} rounded-full flex items-center justify-center font-bold text-lg">${initials}</div>`;
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
                    const isSelected = selectedPlayerIdx === idx;
                    const fitness = player.fitness || 100;
                    const fitnessColor = fitness >= 80 ? 'bg-green-500' : fitness >= 50 ? 'bg-yellow-500' : 'bg-red-500';
                    const form = player.form || 7;
                    const formArrowIcon = getFormArrowIcon(form);
                    const formBadgeColor = getFormBadgeColor(form);

                    $field.append(`
                            <div class="absolute cursor-pointer player-slot transition-all duration-200" 
                                 style="left: ${xPos}%; top: ${yPos}%; transform: translate(-50%, -50%);" data-idx="${idx}">
                                <div class="relative">
                                    <div class="w-16 h-16 bg-white rounded-full flex items-center justify-center shadow-lg border-2 ${colors.border} transition-all duration-200 player-circle ${isSelected ? 'ring-4 ring-yellow-400 ring-opacity-80' : ''} overflow-hidden">
                                        ${getPlayerAvatarHtml(player.name, player.avatar)}
                                    </div>
                                    
                                    <!-- Form Icon -->
                                    <div class="absolute top-0 -right-2 w-6 h-6 rounded-full flex items-center justify-center shadow-md ${formBadgeColor} ring-1 ring-white z-10" title="Form: ${form.toFixed(1)}">
                                        ${formArrowIcon}
                                    </div>

                                    <!-- Fitness progress bar -->
                                    <div class="absolute top-full left-1/2 transform -translate-x-1/2 mt-1 w-16">
                                        <div class="bg-gray-700 bg-opacity-80 rounded-full h-1.5 overflow-hidden shadow-md border border-white border-opacity-30">
                                            <div class="${fitnessColor} h-full transition-all duration-300" style="width: ${fitness}%"></div>
                                        </div>
                                    </div>
                                    
                                    <div class="absolute top-full left-1/2 transform -translate-x-1/2 mt-3 whitespace-nowrap">
                                        <div class="text-white text-xs font-bold bg-black bg-opacity-70 px-2 py-1 rounded">${player.name}</div>
                                    </div>
                                    
                                    <!-- Action buttons for selected player -->
                                    ${isSelected ? `
                                        <div class="absolute -bottom-2 left-1/2 transform -translate-x-1/2 flex gap-2 action-buttons">
                                            <button onclick="removePlayer(${idx})" class="w-7 h-7 bg-red-500 hover:bg-red-600 text-white rounded-full flex items-center justify-center shadow-lg transition-all duration-200 hover:scale-110" title="Remove Player">
                                                <i data-lucide="trash-2" class="w-3 h-3"></i>
                                            </button>
                                            <button onclick="choosePlayer(${idx})" class="w-7 h-7 bg-green-500 hover:bg-green-600 text-white rounded-full flex items-center justify-center shadow-lg transition-all duration-200 hover:scale-110" title="Choose Different Player">
                                                <i data-lucide="user-plus" class="w-3 h-3"></i>
                                            </button>
                                        </div>
                                    ` : ''}
                                    
                                    <!-- Hover switch button for non-selected players -->
                                    ${!isSelected && (selectedPlayerIdx !== null || selectedSubIdx !== null) ? `
                                        <div class="absolute -bottom-2 left-1/2 transform -translate-x-1/2 hover-switch-btn opacity-0 transition-all duration-200">
                                            <button onclick="${selectedPlayerIdx !== null ? `switchPlayer(${idx})` : `performPlayerSwitch(${idx}, ${selectedSubIdx})`}" class="w-7 h-7 bg-blue-500 hover:bg-blue-600 text-white rounded-full flex items-center justify-center shadow-lg transition-all duration-200 hover:scale-110" title="Switch with Selected Player">
                                                <i data-lucide="arrow-left-right" class="w-3 h-3"></i>
                                            </button>
                                        </div>
                                    ` : ''}
                                </div>
                            </div>
                        `);
                } else {
                    $field.append(`
                            <div class="absolute cursor-pointer empty-slot" 
                                 style="left: ${xPos}%; top: ${yPos}%; transform: translate(-50%, -50%);" data-idx="${idx}">
                                <div class="w-16 h-16 bg-white bg-opacity-20 rounded-full flex flex-col items-center justify-center border-2 border-white border-dashed hover:border-blue-300 hover:bg-opacity-30 transition-all duration-200">
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

        // Click handlers
        $('.player-slot').click(function() {
            const idx = $(this).data('idx');
            if (selectedSubIdx !== null) {
                performPlayerSwitch(idx, selectedSubIdx);
                selectedSubIdx = null;
            } else {
                selectPlayer(idx);
            }
        });

        $('.empty-slot').click(function() {
            const idx = $(this).data('idx');
            if (selectedSubIdx !== null) {
                performPlayerSwitch(idx, selectedSubIdx);
                selectedSubIdx = null;
            } else {
                choosePlayer(idx);
            }
        });

        // Hover effects
        $('.player-slot').hover(
            function() {
                const idx = $(this).data('idx');
                const isSelected = selectedPlayerIdx === idx;

                if (!isSelected) {
                    // Highlight border for non-selected players
                    if (selectedPlayerIdx !== null || selectedSubIdx !== null) {
                        // If there's a selected player, show switch-ready highlight
                        $(this).find('.player-circle').addClass('ring-2 ring-blue-400 ring-opacity-70');
                        $(this).find('.hover-switch-btn').removeClass('opacity-0').addClass('opacity-100');
                    } else {
                        // No selected player, just basic hover
                        $(this).find('.player-circle').addClass('ring-2 ring-gray-300 ring-opacity-50');
                    }
                }
            },
            function() {
                const idx = $(this).data('idx');
                const isSelected = selectedPlayerIdx === idx;

                if (!isSelected) {
                    // Remove all hover effects
                    $(this).find('.player-circle').removeClass('ring-2 ring-blue-400 ring-opacity-70 ring-gray-300 ring-opacity-50');
                    $(this).find('.hover-switch-btn').removeClass('opacity-100').addClass('opacity-0');
                }
            }
        );

        // Right-click context menu for quick removal
        $('.player-slot').on('contextmenu', function(e) {
            e.preventDefault();
            const idx = $(this).data('idx');
            removePlayer(idx);
        });
    }

    function openPlayerModal() {
        let requiredPosition = '';
        let modalTitle = '';

        if (isSelectingSubstitute) {
            modalTitle = 'Select Substitute Player';
            requiredPosition = 'Any Position';
        } else {
            requiredPosition = getPositionForSlot(currentSlotIdx);
            modalTitle = `Select ${requiredPosition} Player`;
        }

        // Calculate current team value (excluding the slot we're replacing)
        let currentTeamValue = 0;
        selectedPlayers.forEach((p, idx) => {
            if (p && (!isSelectingSubstitute && idx !== currentSlotIdx)) {
                currentTeamValue += p.value || 0;
            }
        });

        // Add substitute values
        substitutePlayers.forEach((p, idx) => {
            if (p && (isSelectingSubstitute && idx !== currentSlotIdx)) {
                currentTeamValue += p.value || 0;
            }
        });

        const remainingBudget = maxBudget - currentTeamValue;

        $('#modalTitle').html(`${modalTitle} <span class="text-sm font-normal text-blue-600">(Budget: ${formatMarketValue(remainingBudget)})</span>`);
        openModal('playerModal');
        $('#playerSearch').val('');
        renderModalPlayers('');
        lucide.createIcons();
    }

    function renderModalPlayers(search) {
        const $list = $('#modalPlayerList').empty();
        const searchLower = search.toLowerCase();
        let requiredPosition = '';

        if (isSelectingSubstitute) {
            requiredPosition = ''; // Any position for substitutes
        } else {
            requiredPosition = getPositionForSlot(currentSlotIdx);
        }

        // Calculate current team value (excluding the slot we're replacing)
        let currentTeamValue = 0;
        selectedPlayers.forEach((p, idx) => {
            if (p && (!isSelectingSubstitute && idx !== currentSlotIdx)) {
                currentTeamValue += p.value || 0;
            }
        });

        // Add substitute values
        substitutePlayers.forEach((p, idx) => {
            if (p && (isSelectingSubstitute && idx !== currentSlotIdx)) {
                currentTeamValue += p.value || 0;
            }
        });

        // Collect and sort players - team players first
        const matchingPlayers = [];
        const addedPlayerUuids = new Set(); // Track added players to avoid duplicates

        players.forEach((player, idx) => {
            const isSelectedInStarting = selectedPlayers.some(p => p && p.uuid === player.uuid);
            const isSelectedInSubs = substitutePlayers.some(p => p && p.uuid === player.uuid);
            const isSelected = isSelectedInStarting || isSelectedInSubs;

            const matchesSearch = player.name.toLowerCase().includes(searchLower);

            // Skip if already added (prevents duplicates)
            if (addedPlayerUuids.has(player.uuid)) {
                return;
            }

            // Determine position match for both team and non-team players
            let matchesPosition = false;
            if (isSelectingSubstitute) {
                matchesPosition = true; // Any position for substitutes
            } else {
                const playablePositions = player.playablePositions || [player.position];
                matchesPosition = playablePositions.includes(requiredPosition);
            }

            if (matchesPosition && matchesSearch) {
                matchingPlayers.push({
                    player,
                    idx,
                    isSelected,
                    isSelectedInStarting,
                    isSelectedInSubs,
                    showMainPosition: isSelected // Show main position for squad players
                });
                addedPlayerUuids.add(player.uuid);
            }
        });

        // Sort: team players first (starting XI, then substitutes), then available players
        matchingPlayers.sort((a, b) => {
            if (a.isSelected && !b.isSelected) return -1;
            if (!a.isSelected && b.isSelected) return 1;
            if (a.isSelected && b.isSelected) {
                // Both in team: starting XI before substitutes
                if (a.isSelectedInStarting && !b.isSelectedInStarting) return -1;
                if (!a.isSelectedInStarting && b.isSelectedInStarting) return 1;
            }
            return 0;
        });

        // Render sorted players
        matchingPlayers.forEach(({
            player,
            idx,
            isSelected,
            isSelectedInStarting,
            showMainPosition
        }) => {
            const wouldExceedBudget = (currentTeamValue + (player.value || 0)) > maxBudget;

            // Determine player status and styling
            let itemClass, priceClass, budgetWarning = '',
                teamBadge = '';

            if (isSelected) {
                // Player is already in team - highlight with green background but keep clickable
                const teamPosition = isSelectedInStarting ? 'Starting XI' : 'Substitute';
                itemClass = 'bg-green-50 border-green-300 hover:bg-green-100 cursor-pointer modal-player-item';
                priceClass = 'text-gray-600';
                teamBadge = `<span class="text-xs bg-green-600 text-white px-2 py-1 rounded font-medium">${teamPosition}</span>`;
            } else {
                // Player is available
                const isAffordable = !wouldExceedBudget;
                itemClass = isAffordable ? 'hover:bg-blue-50 cursor-pointer modal-player-item' : 'bg-gray-100 cursor-not-allowed opacity-60';
                priceClass = isAffordable ? 'text-green-600' : 'text-red-600';
                budgetWarning = wouldExceedBudget ? '<div class="text-xs text-red-500 mt-1">Exceeds budget</div>' : '';
            }

            // Show playable positions
            // For team players, always show their main position
            // For available players, show if they can play multiple positions
            const playablePositions = player.playablePositions || [player.position];
            let positionsDisplay;

            if (showMainPosition) {
                // Team player - show main position only
                positionsDisplay = `<span class="text-xs text-gray-500 bg-gray-100 px-2 py-1 rounded">${player.position}</span>`;
            } else {
                // Available player - show playable positions with indicator
                positionsDisplay = playablePositions.length > 1 ?
                    `<span class="text-xs text-gray-500 bg-gray-100 px-2 py-1 rounded">${player.position}</span><span class="text-xs text-blue-500 ml-1" title="Can also play: ${playablePositions.filter(p => p !== player.position).join(', ')}">+${playablePositions.length - 1}</span>` :
                    `<span class="text-xs text-gray-500 bg-gray-100 px-2 py-1 rounded">${player.position}</span>`;
            }

            // Determine if player is clickable (team players and affordable players are clickable)
            const isClickable = isSelected || !wouldExceedBudget;

            // Generate player avatar
            const avatarHtml = getPlayerAvatarHtml(player.name, player.avatar);

            $list.append(`
                    <div class="flex items-center justify-between p-3 border rounded ${itemClass}" ${isClickable ? `data-idx="${idx}"` : ''}>
                        <div class="flex items-center gap-3 flex-1" ${isClickable ? `onclick="selectModalPlayer(${idx})"` : ''}>
                            <div class="w-12 h-12 flex-shrink-0">
                                ${avatarHtml}
                            </div>
                            <div class="flex-1">
                                <div class="flex items-center gap-2">
                                    <div class="font-medium">${player.name}</div>
                                    ${teamBadge}
                                </div>
                                <div class="text-sm ${priceClass} font-semibold">${formatMarketValue(player.value || 0)}</div>
                                ${budgetWarning}
                            </div>
                        </div>
                        <div class="flex items-center gap-2">
                            <div class="flex flex-col items-end gap-1">
                                ${positionsDisplay}
                                <div class="flex items-center gap-1">
                                    <span class="text-xs text-yellow-600">★</span>
                                    <span class="text-xs text-gray-600">${player.rating || 'N/A'}</span>
                                </div>
                            </div>
                            <button onclick="showPlayerInfo(${JSON.stringify(player).replace(/"/g, '&quot;')}); event.stopPropagation();" class="p-1 text-blue-600 hover:bg-blue-100 rounded transition-colors" title="Player Info">
                                <i data-lucide="info" class="w-3 h-3"></i>
                            </button>
                        </div>
                    </div>
                `);
        });

        if ($list.children().length === 0) {
            $list.append('<div class="text-center text-gray-500 py-4">No players available</div>');
        }

        // Handle modal player selection
        window.selectModalPlayer = function(idx) {
            if (idx !== undefined) {
                const player = players[idx];

                // Check if player is already in team (by uuid)
                const isInStarting = selectedPlayers.some(p => p && p.uuid === player.uuid);
                const isInSubs = substitutePlayers.some(p => p && p.uuid === player.uuid);
                const isAlreadyInTeam = isInStarting || isInSubs;

                if (isAlreadyInTeam) {
                    // Player is already in team - just switch positions
                    const currentPlayer = isSelectingSubstitute ? substitutePlayers[currentSlotIdx] : selectedPlayers[currentSlotIdx];

                    // Find where the selected player currently is (by uuid)
                    let sourceIsSubstitute = false;
                    let sourceIdx = -1;

                    if (isInStarting) {
                        sourceIdx = selectedPlayers.findIndex(p => p && p.uuid === player.uuid);
                        sourceIsSubstitute = false;
                    } else {
                        sourceIdx = substitutePlayers.findIndex(p => p && p.uuid === player.uuid);
                        sourceIsSubstitute = true;
                    }

                    // Perform the switch
                    if (isSelectingSubstitute) {
                        // Moving to substitutes
                        substitutePlayers[currentSlotIdx] = player;
                        if (sourceIsSubstitute) {
                            substitutePlayers[sourceIdx] = currentPlayer;
                        } else {
                            selectedPlayers[sourceIdx] = currentPlayer;
                        }
                    } else {
                        // Moving to starting XI
                        selectedPlayers[currentSlotIdx] = player;
                        if (sourceIsSubstitute) {
                            substitutePlayers[sourceIdx] = currentPlayer;
                        } else {
                            selectedPlayers[sourceIdx] = currentPlayer;
                        }
                    }

                    // Save the team with switched positions
                    $.post('api/save_team_api.php', {
                        formation: $('#formation').val(),
                        team: JSON.stringify(selectedPlayers),
                        substitutes: JSON.stringify(substitutePlayers)
                    }, function(response) {
                        if (response.success) {
                            closeModal('playerModal');
                            isSelectingSubstitute = false;

                            // Update displays
                            renderPlayers();
                            renderField();
                            renderSubstitutes();

                            // Show success message
                            Swal.fire({
                                icon: 'success',
                                title: 'Players Switched!',
                                text: `${player.name} has been moved to the new position`,
                                timer: 2000,
                                showConfirmButton: false,
                                toast: true,
                                position: 'top-end'
                            });
                        } else {
                            Swal.fire({
                                icon: 'error',
                                title: 'Error',
                                text: response.message || 'Failed to switch players',
                                confirmButtonColor: '#3b82f6'
                            });
                        }
                    }).fail(function() {
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: 'Failed to save team changes',
                            confirmButtonColor: '#3b82f6'
                        });
                    });

                    return;
                }

                // Player is not in team - proceed with purchase logic
                // Double-check budget before adding
                let currentTeamValue = 0;
                selectedPlayers.forEach((p, i) => {
                    if (p && i !== currentSlotIdx) {
                        currentTeamValue += p.value || 0;
                    }
                });

                if ((currentTeamValue + (player.value || 0)) > maxBudget) {
                    Swal.fire({
                        icon: 'warning',
                        title: 'Budget Exceeded',
                        text: `Adding ${player.name} would exceed your budget of ${formatMarketValue(maxBudget)}`,
                        confirmButtonColor: '#3b82f6'
                    });
                    return;
                }

                // Show confirmation alert before buying player
                const currentPlayer = isSelectingSubstitute ? substitutePlayers[currentSlotIdx] : selectedPlayers[currentSlotIdx];
                const isReplacement = currentPlayer !== null;
                const requiredPosition = isSelectingSubstitute ? 'Substitute' : getPositionForSlot(currentSlotIdx);

                let confirmTitle = isReplacement ? 'Replace Player?' : 'Buy Player?';
                let confirmText = isReplacement ?
                    `Replace ${currentPlayer.name} with ${player.name} for ${formatMarketValue(player.value || 0)}?` :
                    `Buy ${player.name} (${requiredPosition}) for ${formatMarketValue(player.value || 0)}?`;

                Swal.fire({
                    title: confirmTitle,
                    html: `
                        <div class="text-left space-y-3">
                            <div class="bg-gray-50 p-4 rounded-lg">
                                <h4 class="font-semibold text-gray-900 mb-2">Player Details:</h4>
                                <div class="space-y-1 text-sm">
                                    <div class="flex justify-between">
                                        <span class="text-gray-600">Name:</span>
                                        <span class="font-medium">${player.name}</span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span class="text-gray-600">Position:</span>
                                        <span class="font-medium">${requiredPosition}</span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span class="text-gray-600">Rating:</span>
                                        <span class="font-medium">${player.rating || 'N/A'}</span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span class="text-gray-600">Cost:</span>
                                        <span class="font-medium text-red-600">${formatMarketValue(player.value || 0)}</span>
                                    </div>
                                </div>
                            </div>
                            
                            ${isReplacement ? `
                            <div class="bg-yellow-50 p-4 rounded-lg border border-yellow-200">
                                <h4 class="font-semibold text-yellow-900 mb-2">Current Player:</h4>
                                <div class="space-y-1 text-sm">
                                    <div class="flex justify-between">
                                        <span class="text-gray-600">Name:</span>
                                        <span class="font-medium">${currentPlayer.name}</span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span class="text-gray-600">Value:</span>
                                        <span class="font-medium text-green-600">${formatMarketValue(currentPlayer.value || 0)}</span>
                                    </div>
                                </div>
                                <p class="text-xs text-yellow-700 mt-2">This player will be removed from your team</p>
                            </div>
                            ` : ''}
                            
                            <div class="bg-blue-50 p-4 rounded-lg border border-blue-200">
                                <h4 class="font-semibold text-blue-900 mb-2">Budget Impact:</h4>
                                <div class="space-y-1 text-sm">
                                    <div class="flex justify-between">
                                        <span class="text-gray-600">Current Budget:</span>
                                        <span class="font-medium text-blue-600">${formatMarketValue(maxBudget - currentTeamValue)}</span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span class="text-gray-600">After Purchase:</span>
                                        <span class="font-medium text-green-600">${formatMarketValue(maxBudget - currentTeamValue - (player.value || 0))}</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    `,
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonColor: '#10b981',
                    cancelButtonColor: '#6b7280',
                    confirmButtonText: isReplacement ? '<i data-lucide="refresh-cw" class="w-4 h-4 inline mr-1"></i> Replace Player' : '<i data-lucide="shopping-cart" class="w-4 h-4 inline mr-1"></i> Buy Player',
                    cancelButtonText: 'Cancel',
                    customClass: {
                        popup: 'swal-wide'
                    },
                    didOpen: () => {
                        lucide.createIcons();
                    }
                }).then((result) => {
                    if (result.isConfirmed) {
                        // Show loading message
                        Swal.fire({
                            title: 'Processing Purchase...',
                            text: 'Please wait while we complete your purchase',
                            allowOutsideClick: false,
                            allowEscapeKey: false,
                            showConfirmButton: false,
                            didOpen: () => {
                                Swal.showLoading();
                            }
                        });

                        // Add player to team or substitutes
                        if (isSelectingSubstitute) {
                            substitutePlayers[currentSlotIdx] = player;
                        } else {
                            selectedPlayers[currentSlotIdx] = player;
                        }

                        // Save team and update budget
                        $.post('api/purchase_player_api.php', {
                            formation: $('#formation').val(),
                            team: JSON.stringify(selectedPlayers),
                            substitutes: JSON.stringify(substitutePlayers),
                            player_cost: player.value || 0,
                            player_uuid: player.uuid
                        }, function(response) {
                            if (response.success) {
                                // Update local budget variable
                                maxBudget = response.new_budget;

                                // Update budget display in club overview
                                $('#clubBudget').text(formatMarketValue(response.new_budget));

                                closeModal('playerModal');
                                isSelectingSubstitute = false;

                                // Update displays
                                renderPlayers();
                                renderField();
                                renderSubstitutes();
                                updateClubStats();

                                // Show success message
                                Swal.fire({
                                    icon: 'success',
                                    title: isReplacement ? 'Player Replaced!' : 'Player Purchased!',
                                    html: `
                                        <div class="text-center">
                                            <p class="mb-2">${player.name} has been added to your ${isSelectingSubstitute ? 'substitutes' : 'team'}!</p>
                                            <p class="text-sm text-gray-600">Cost: ${formatMarketValue(player.value || 0)}</p>
                                            <p class="text-sm text-blue-600">Remaining Budget: ${formatMarketValue(response.new_budget)}</p>
                                        </div>
                                    `,
                                    timer: 3000,
                                    showConfirmButton: false,
                                    toast: true,
                                    position: 'top-end'
                                });
                            } else {
                                // Revert the change if save failed
                                if (isSelectingSubstitute) {
                                    substitutePlayers[currentSlotIdx] = null;
                                } else {
                                    selectedPlayers[currentSlotIdx] = currentPlayer;
                                }

                                renderPlayers();
                                renderField();
                                renderSubstitutes();
                                updateClubStats();

                                Swal.fire({
                                    icon: 'error',
                                    title: 'Purchase Failed',
                                    text: response.message || 'Failed to complete purchase. Please try again.',
                                    confirmButtonColor: '#ef4444'
                                });
                            }
                        }, 'json').fail(function() {
                            // Revert the change if request failed
                            if (isSelectingSubstitute) {
                                substitutePlayers[currentSlotIdx] = null;
                            } else {
                                selectedPlayers[currentSlotIdx] = currentPlayer;
                            }

                            renderPlayers();
                            renderField();
                            renderSubstitutes();
                            updateClubStats();

                            Swal.fire({
                                icon: 'error',
                                title: 'Connection Error',
                                text: 'Unable to complete purchase. Please check your connection and try again.',
                                confirmButtonColor: '#ef4444'
                            });
                        });
                    }
                });
            }
        };
    }


    $('#playerSearch').on('input', function() {
        renderModalPlayers($(this).val());
    });

    $('#closeModal').click(function() {
        closeModal('playerModal');
        isSelectingSubstitute = false;
    });

    $('#playerModal').click(function(e) {
        if (e.target === this) {
            closeModal('playerModal');
            isSelectingSubstitute = false;
        }
    });



    $('#formation').change(function() {
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

    $('#resetTeam').click(function() {
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

    $('#saveTeam').click(function() {
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
        let confirmText = filledSlots < totalSlots ?
            'Your team is not complete. You can continue adding players later.' :
            'Save your complete team?';

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
                $.post('api/save_team_api.php', {
                    formation: $('#formation').val(),
                    team: JSON.stringify(selectedPlayers),
                    substitutes: JSON.stringify(substitutePlayers)
                }, function(response) {
                    if (response.redirect) {
                        window.location.href = response.redirect;
                    } else if (response.success) {
                        // Handle level up notification first
                        if (response.level_up) {
                            handleLevelUpNotification(response);
                        } else {
                            Swal.fire({
                                icon: 'success',
                                title: 'Team Saved!',
                                text: filledSlots === totalSlots ?
                                    'Your complete team has been saved successfully!' : `Team saved successfully! (${filledSlots}/${totalSlots} players selected)`,
                                confirmButtonColor: '#10b981'
                            });
                        }
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'Save Failed',
                            text: response.message || 'Failed to save team. Please try again.',
                            confirmButtonColor: '#ef4444'
                        });
                    }
                }, 'json').fail(function() {
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

    // Player Info Modal Functions
    function showPlayerInfo(playerData) {
        const player = playerData;



        // Get contract matches (initialize if not set)
        const contractMatches = player.contract_matches || Math.floor(Math.random() * 36) + 15; // 15-50 matches
        const contractRemaining = player.contract_matches_remaining || contractMatches;

        // Generate some stats (random for demo)
        const stats = generatePlayerStats(player.position, player.rating);

        const playerInfoHtml = `
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <!-- Player Header -->
                <div class="lg:col-span-2 bg-gradient-to-r from-blue-600 to-blue-800 text-white rounded-lg p-6">
                    <div class="flex items-center gap-6">
                        <div class="w-24 h-24 bg-white bg-opacity-20 rounded-full flex items-center justify-center overflow-hidden">
                            ${getPlayerAvatarHtml(player.name, player.avatar)}
                        </div>
                        <div class="flex-1">
                            <h2 class="text-3xl font-bold mb-2">${player.name}</h2>
                            <div class="flex items-center gap-4 text-blue-100">
                                <span class="bg-blue-500 px-2 py-1 rounded text-sm font-semibold">${player.position}</span>
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
                        ${(player.playablePositions && player.playablePositions.length > 0) ? `
                        <div class="flex justify-between">
                            <span class="text-gray-600">Playable Positions:</span>
                            <span class="font-medium">${Array.isArray(player.playablePositions) ? player.playablePositions.join(', ') : player.playablePositions}</span>
                        </div>
                        ` : ''}
                        <div class="flex justify-between">
                            <span class="text-gray-600">Contract:</span>
                            <span class="font-medium">${contractRemaining} match${contractRemaining !== 1 ? 'es' : ''} remaining</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-600">Matches Played:</span>
                            <span class="font-medium">${player.matches_played || 0}</span>
                        </div>
                        ${contractRemaining <= 8 ? `
                        <div class="mt-3 p-3 rounded-lg border ${getContractStatus(player).bg} ${getContractStatus(player).border}">
                            <div class="flex items-center justify-between">
                                <div>
                                    <div class="text-sm font-medium ${getContractStatus(player).color}">
                                        <i data-lucide="alert-triangle" class="w-4 h-4 inline mr-1"></i>
                                        ${getContractStatus(player).text}
                                    </div>
                                    <div class="text-xs text-gray-600 mt-1">Contract renewal recommended</div>
                                </div>
                                <button onclick="renewContract('${player.uuid}', '${player.name}', ${contractRemaining})" class="px-3 py-1 bg-blue-600 text-white text-sm rounded hover:bg-blue-700">
                                    Renew
                                </button>
                            </div>
                        </div>
                        ` : ''}
                    </div>
                </div>

                <!-- Player Condition -->
                <div class="bg-gray-50 rounded-lg p-4">
                    <h3 class="text-lg font-semibold mb-4 flex items-center gap-2">
                        <i data-lucide="activity" class="w-5 h-5 text-blue-600"></i>
                        Player Condition
                    </h3>
                    <div class="space-y-4">
                        <div>
                            <div class="flex justify-between items-center mb-2">
                                <span class="text-gray-600">Fitness:</span>
                                <span class="font-medium text-gray-700">${getFitnessStatusText(player.fitness || 100)}</span>
                            </div>
                            <div class="w-full bg-gray-200 rounded-full h-2">
                                <div class="${getFitnessProgressColor(player.fitness || 100)} h-2 rounded-full transition-all duration-300" style="width: ${player.fitness || 100}%"></div>
                            </div>
                            <div class="text-xs text-gray-500 mt-1">${player.fitness || 100}/100</div>
                        </div>
                        <div>
                            <div class="flex justify-between items-center mb-2">
                                <span class="text-gray-600">Form:</span>
                                <span class="px-3 py-1 rounded-full ${getFormBadgeColor(player.form || 7)} flex items-center gap-2 text-sm font-medium">
                                    ${getFormArrowIcon(player.form || 7)}
                                    ${(player.form || 7).toFixed(1)}/10.0
                                </span>
                            </div>
                            <div class="text-xs text-gray-500 mt-1">${getFormStatusText(player.form || 7)} form level</div>
                        </div>
                        <div>
                            <div class="flex justify-between items-center mb-2">
                                <span class="text-gray-600">Level:</span>
                                <span class="px-3 py-1 rounded-full ${getLevelDisplayInfo(player.level || 1).bg} ${getLevelDisplayInfo(player.level || 1).border} flex items-center gap-2 text-sm font-medium ${getLevelDisplayInfo(player.level || 1).color}">
                                    <i data-lucide="star" class="w-3 h-3"></i>
                                    ${player.level || 1} - ${getLevelDisplayInfo(player.level || 1).text}
                                </span>
                            </div>
                            ${(player.level || 1) < 50 ? `
                            <div class="w-full bg-gray-200 rounded-full h-2 mb-1">
                                <div class="bg-gradient-to-r from-blue-500 to-purple-500 h-2 rounded-full transition-all duration-300" style="width: ${getPlayerLevelStatus(player).progressPercentage}%"></div>
                            </div>
                            <div class="text-xs text-gray-500">
                                ${getPlayerLevelStatus(player).experienceProgress}/${getPlayerLevelStatus(player).experienceNeeded} XP to next level
                            </div>
                            ` : `
                            <div class="text-xs text-yellow-600 font-semibold">MAX LEVEL REACHED</div>
                            `}
                        </div>
                        <div class="pt-2 border-t border-gray-200">
                            <div class="flex justify-between">
                                <span class="text-gray-600">Effective Rating:</span>
                                <span class="font-bold text-lg text-blue-600">★${getEffectiveRating(player)}</span>
                            </div>
                            <div class="text-xs text-gray-500 mt-1">Base: ★${player.rating} (modified by fitness, form, level & card level)</div>
                        </div>
                    </div>
                </div>

                <!-- Card Level & Upgrades -->
                <div class="bg-gray-50 rounded-lg p-4">
                    <h3 class="text-lg font-semibold mb-4 flex items-center gap-2">
                        <i data-lucide="credit-card" class="w-5 h-5 text-indigo-600"></i>
                        Card Level & Salary
                    </h3>
                    <div class="space-y-4">
                        <div>
                            <div class="flex justify-between items-center mb-2">
                                <span class="text-gray-600">Card Level:</span>
                                <span class="px-3 py-1 rounded-full ${getCardLevelDisplayInfo(player.card_level || 1).bg} ${getCardLevelDisplayInfo(player.card_level || 1).border} flex items-center gap-2 text-sm font-medium ${getCardLevelDisplayInfo(player.card_level || 1).color}">
                                    <i data-lucide="${getCardLevelDisplayInfo(player.card_level || 1).icon}" class="w-3 h-3"></i>
                                    ${player.card_level || 1} - ${getCardLevelDisplayInfo(player.card_level || 1).text}
                                </span>
                            </div>
                        </div>
                        <div>
                            <div class="flex justify-between items-center mb-2">
                                <span class="text-gray-600">Weekly Salary:</span>
                                <span class="font-medium text-red-600">${formatMarketValue(calculatePlayerSalary(player))}</span>
                            </div>
                            <div class="text-xs text-gray-500">Base: ${formatMarketValue(player.base_salary || Math.max(1000, (player.value || 1000000) * 0.001))} (+${getCardLevelBenefits(player.card_level || 1).salaryIncreasePercent}% from card level)</div>
                        </div>
                        <div class="bg-blue-50 border border-blue-200 rounded-lg p-3">
                            <h4 class="font-semibold text-blue-900 mb-2">Card Level Benefits:</h4>
                            <div class="space-y-1 text-sm text-blue-800">
                                <div>• Rating Bonus: +${getCardLevelBenefits(player.card_level || 1).ratingBonus} points</div>
                                <div>• Max Fitness: ${getCardLevelBenefits(player.card_level || 1).maxFitness}/100</div>
                                <div>• Salary Increase: +${getCardLevelBenefits(player.card_level || 1).salaryIncreasePercent}%</div>
                            </div>
                        </div>
                        ${(player.card_level || 1) < 10 ? `
                        <div class="bg-green-50 border border-green-200 rounded-lg p-3">
                            <div class="flex items-center justify-between">
                                <div>
                                    <div class="font-semibold text-green-900">Upgrade Available</div>
                                    <div class="text-sm text-green-700">Level ${(player.card_level || 1) + 1} - ${getCardLevelDisplayInfo((player.card_level || 1) + 1).text}</div>
                                    <div class="text-xs text-green-600 mt-1">Cost: ${formatMarketValue(getCardLevelUpgradeCost(player.card_level || 1, player.value || 1000000))}</div>
                                </div>
                                <button onclick="upgradeCardLevel('${player.uuid}', '${player.name}', ${player.card_level || 1}, ${player.value || 1000000})" class="px-3 py-1 bg-green-600 text-white text-sm rounded hover:bg-green-700">
                                    Upgrade
                                </button>
                            </div>
                        </div>
                        ` : `
                        <div class="bg-cyan-50 border border-cyan-200 rounded-lg p-3 text-center">
                            <div class="text-cyan-800 font-semibold">Maximum Card Level Reached!</div>
                            <div class="text-xs text-cyan-600 mt-1">This player has reached the highest card level</div>
                        </div>
                        `}
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
                                ${player?.playablePositions?.map(pos =>
            `<span class="bg-blue-100 text-blue-800 px-2 py-1 rounded text-sm font-medium">${pos}</span>`
        ).join('')}
                            </div>
                        </div>
                        <div>
                            <span class="text-gray-600 text-sm">Key Attributes:</span>
                            <div class="mt-2 space-y-2">
                                ${Object.entries(stats).map(([stat, value]) => `
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
                    <p class="text-gray-700 leading-relaxed">${player.description || ""}</p>
                </div>
            </div>
        `;

        $('#playerInfoContent').html(playerInfoHtml);
        openModal('playerInfoModal');
        lucide.createIcons();
    }

    // Contract renewal functionality
    window.renewContract = function(playerUuid, playerName, currentRemaining) {
        const renewalCost = Math.floor(Math.random() * 5000000) + 2000000; // €2M - €7M
        const newMatches = Math.floor(Math.random() * 21) + 20; // 20-40 new matches

        Swal.fire({
            title: `Renew Contract for ${playerName}?`,
            html: `
                <div class="text-left space-y-3">
                    <div class="bg-gray-50 p-4 rounded-lg">
                        <h4 class="font-semibold text-gray-900 mb-2">Contract Details:</h4>
                        <div class="space-y-1 text-sm">
                            <div class="flex justify-between">
                                <span class="text-gray-600">Current Remaining:</span>
                                <span class="font-medium">${currentRemaining} matches</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-600">New Contract:</span>
                                <span class="font-medium text-green-600">+${newMatches} matches</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-600">Total After Renewal:</span>
                                <span class="font-medium text-blue-600">${currentRemaining + newMatches} matches</span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="bg-red-50 p-4 rounded-lg border border-red-200">
                        <h4 class="font-semibold text-red-900 mb-2">Renewal Cost:</h4>
                        <div class="text-lg font-bold text-red-600">${formatMarketValue(renewalCost)}</div>
                        <div class="text-xs text-red-700 mt-1">This amount will be deducted from your budget</div>
                    </div>
                </div>
            `,
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#10b981',
            cancelButtonColor: '#6b7280',
            confirmButtonText: '<i data-lucide="file-signature" class="w-4 h-4 inline mr-1"></i> Renew Contract',
            cancelButtonText: 'Cancel',
            customClass: {
                popup: 'swal-wide'
            },
            didOpen: () => {
                lucide.createIcons();
            }
        }).then((result) => {
            if (result.isConfirmed) {
                // Process contract renewal
                $.post('api/renew_contract_api.php', {
                    player_uuid: playerUuid,
                    renewal_cost: renewalCost,
                    new_matches: newMatches
                }, function(response) {
                    if (response.success) {
                        // Update local budget
                        maxBudget = response.new_budget;
                        $('#clubBudget').text(formatMarketValue(response.new_budget));

                        // Update player data
                        selectedPlayers.forEach((player, idx) => {
                            if (player && player.uuid === playerUuid) {
                                selectedPlayers[idx].contract_matches_remaining = (selectedPlayers[idx].contract_matches_remaining || 0) + newMatches;
                            }
                        });

                        substitutePlayers.forEach((player, idx) => {
                            if (player && player.uuid === playerUuid) {
                                substitutePlayers[idx].contract_matches_remaining = (substitutePlayers[idx].contract_matches_remaining || 0) + newMatches;
                            }
                        });

                        // Close modal and refresh displays
                        closeModal('playerInfoModal');
                        renderPlayers();
                        renderSubstitutes();

                        Swal.fire({
                            icon: 'success',
                            title: 'Contract Renewed!',
                            text: `${playerName}'s contract has been extended by ${newMatches} matches for ${formatMarketValue(renewalCost)}.`,
                            timer: 3000,
                            showConfirmButton: false,
                            toast: true,
                            position: 'top-end'
                        });
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'Renewal Failed',
                            text: response.message || 'Could not renew contract. Please try again.',
                            confirmButtonColor: '#ef4444'
                        });
                    }
                }, 'json').fail(function() {
                    Swal.fire({
                        icon: 'error',
                        title: 'Connection Error',
                        text: 'Could not process contract renewal. Please check your connection and try again.',
                        confirmButtonColor: '#ef4444'
                    });
                });
            }
        });
    };

    // Card level upgrade functionality
    window.upgradeCardLevel = function(playerUuid, playerName, currentCardLevel, playerValue) {
        const upgradeCost = getCardLevelUpgradeCost(currentCardLevel, playerValue);
        const newCardLevel = currentCardLevel + 1;
        const cardInfo = getCardLevelDisplayInfo(newCardLevel);
        const benefits = getCardLevelBenefits(newCardLevel);

        // Calculate success rate
        const baseSuccessRate = 85;
        const levelPenalty = (currentCardLevel - 1) * 10;
        const successRate = Math.max(30, baseSuccessRate - levelPenalty);

        Swal.fire({
            title: `Upgrade ${playerName}'s Card Level?`,
            html: `
                <div class="text-left space-y-3">
                    <div class="bg-gray-50 p-4 rounded-lg">
                        <h4 class="font-semibold text-gray-900 mb-2">Upgrade Details:</h4>
                        <div class="space-y-1 text-sm">
                            <div class="flex justify-between">
                                <span class="text-gray-600">Current Level:</span>
                                <span class="font-medium">${currentCardLevel} - ${getCardLevelDisplayInfo(currentCardLevel).text}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-600">Target Level:</span>
                                <span class="font-medium text-green-600">${newCardLevel} - ${cardInfo.text}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-600">Success Rate:</span>
                                <span class="font-medium ${successRate >= 70 ? 'text-green-600' : successRate >= 50 ? 'text-yellow-600' : 'text-red-600'}">${successRate}%</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-600">Upgrade Cost:</span>
                                <span class="font-medium text-red-600">${formatMarketValue(upgradeCost)}</span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="bg-yellow-50 p-4 rounded-lg border border-yellow-200">
                        <h4 class="font-semibold text-yellow-900 mb-2">⚠️ Important Notice:</h4>
                        <div class="space-y-1 text-sm text-yellow-800">
                            <div>• Upgrade cost is paid regardless of success or failure</div>
                            <div>• Higher card levels have lower success rates</div>
                            <div>• You can retry failed upgrades (additional cost applies)</div>
                        </div>
                    </div>
                    
                    <div class="bg-green-50 p-4 rounded-lg border border-green-200">
                        <h4 class="font-semibold text-green-900 mb-2">Success Benefits:</h4>
                        <div class="space-y-1 text-sm text-green-800">
                            <div>• Rating Bonus: +${benefits.ratingBonus} points</div>
                            <div>• Max Fitness: ${benefits.maxFitness}/100</div>
                            <div>• Fitness Recovery: Improved</div>
                        </div>
                    </div>
                    
                    <div class="bg-red-50 p-4 rounded-lg border border-red-200">
                        <h4 class="font-semibold text-red-900 mb-2">Consequences:</h4>
                        <div class="space-y-1 text-sm text-red-800">
                            <div>• Salary Increase: +${benefits.salaryIncreasePercent}% weekly cost</div>
                            <div>• Upgrade Cost: ${formatMarketValue(upgradeCost)} (paid now)</div>
                        </div>
                    </div>
                    
                    <div class="bg-blue-50 p-4 rounded-lg border border-blue-200">
                        <h4 class="font-semibold text-blue-900 mb-2">Budget Impact:</h4>
                        <div class="space-y-1 text-sm">
                            <div class="flex justify-between">
                                <span class="text-gray-600">Current Budget:</span>
                                <span class="font-medium text-blue-600">${formatMarketValue(maxBudget)}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-600">After Payment:</span>
                                <span class="font-medium text-orange-600">${formatMarketValue(maxBudget - upgradeCost)}</span>
                            </div>
                        </div>
                    </div>
                </div>
            `,
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#10b981',
            cancelButtonColor: '#6b7280',
            confirmButtonText: '<i data-lucide="dice-6" class="w-4 h-4 inline mr-1"></i> Try Upgrade',
            cancelButtonText: 'Cancel',
            customClass: {
                popup: 'swal-wide'
            },
            didOpen: () => {
                lucide.createIcons();
            }
        }).then((result) => {
            if (result.isConfirmed) {
                // Show processing popup with progress bar
                showUpgradeProcessing(playerUuid, playerName, currentCardLevel, successRate);
            }
        });
    };

    // Show upgrade processing with progress bar and luck animation
    function showUpgradeProcessing(playerUuid, playerName, currentCardLevel, successRate) {
        let progress = 0;
        let currentStep = 0;
        let progressInterval = null;

        const steps = [
            'Preparing upgrade materials...',
            'Analyzing player potential...',
            'Calculating enhancement factors...',
            'Processing upgrade...',
            'Finalizing results...'
        ];

        // Function to clean up all intervals and timeouts
        const cleanupIntervals = () => {
            if (progressInterval) {
                clearInterval(progressInterval);
                progressInterval = null;
            }
        };

        Swal.fire({
            title: `Upgrading ${playerName}`,
            html: `
                <div class="text-center space-y-4">
                    <div class="bg-gray-50 p-4 rounded-lg">
                        <div class="text-lg font-semibold text-gray-900 mb-2">Card Level ${currentCardLevel} → ${currentCardLevel + 1}</div>
                        <div class="text-sm text-gray-600">Success Rate: <span class="font-medium ${successRate >= 70 ? 'text-green-600' : successRate >= 50 ? 'text-yellow-600' : 'text-red-600'}">${successRate}%</span></div>
                    </div>
                    
                    <div class="space-y-2">
                        <div id="upgradeStep" class="text-sm text-gray-600">${steps[0]}</div>
                        <div class="w-full bg-gray-200 rounded-full h-3">
                            <div id="upgradeProgress" class="bg-gradient-to-r from-blue-500 to-purple-600 h-3 rounded-full transition-all duration-500" style="width: 0%"></div>
                        </div>
                        <div id="upgradePercentage" class="text-xs text-gray-500">0%</div>
                    </div>
                </div>
            `,
            allowOutsideClick: false,
            allowEscapeKey: false,
            showConfirmButton: false,
            customClass: {
                popup: 'swal-wide'
            },
            willClose: () => {
                // Ensure intervals are cleaned up when modal closes
                cleanupIntervals();
            }
        });

        // Add timeout as safety mechanism (max 10 seconds)
        const timeoutId = setTimeout(() => {
            cleanupIntervals();
            processUpgrade(playerUuid, playerName, currentCardLevel);
        }, 10000);

        // Animate progress
        progressInterval = setInterval(() => {
            progress += Math.random() * 15 + 5; // Random progress between 5-20%

            if (progress >= 100) {
                progress = 100;
                clearTimeout(timeoutId); // Clear timeout since we're completing normally
                cleanupIntervals();

                // Process the actual upgrade
                processUpgrade(playerUuid, playerName, currentCardLevel);
                return;
            }

            // Update progress bar (check if elements exist to prevent errors)
            const progressBar = document.getElementById('upgradeProgress');
            const progressText = document.getElementById('upgradePercentage');
            const stepText = document.getElementById('upgradeStep');

            if (progressBar) progressBar.style.width = progress + '%';
            if (progressText) progressText.textContent = Math.floor(progress) + '%';

            // Update step text
            const stepIndex = Math.min(Math.floor(progress / 20), steps.length - 1);
            if (stepIndex !== currentStep) {
                currentStep = stepIndex;
                if (stepText) stepText.textContent = steps[stepIndex];
            }
        }, 200);
    }

    // Process the actual upgrade
    function processUpgrade(playerUuid, playerName, currentCardLevel) {
        // Determine player type (team or substitute)
        let playerType = 'team';
        let playerFound = false;

        // Check main team
        selectedPlayers.forEach((player, idx) => {
            if (player && player.uuid === playerUuid) {
                playerFound = true;
                playerType = 'team';
            }
        });

        // Check substitutes if not found in main team
        if (!playerFound) {
            substitutePlayers.forEach((player, idx) => {
                if (player && player.uuid === playerUuid) {
                    playerFound = true;
                    playerType = 'substitute';
                }
            });
        }

        // If player not found, show error and return
        if (!playerFound) {
            Swal.fire({
                icon: 'error',
                title: 'Player Not Found',
                text: 'Could not find the player in your squad. Please refresh and try again.',
                confirmButtonColor: '#ef4444'
            });
            return;
        }

        // Process upgrade
        $.post('api/upgrade_card_level_api.php', {
            player_uuid: playerUuid,
            player_type: playerType
        }, function(response) {
            if (response.success) {
                // Update local budget
                maxBudget = response.new_budget;
                $('#clubBudget').text(formatMarketValue(response.new_budget));

                // Update player data in local arrays
                if (playerType === 'team') {
                    selectedPlayers.forEach((player, idx) => {
                        if (player && player.uuid === playerUuid) {
                            selectedPlayers[idx] = response.updated_player;
                        }
                    });
                } else {
                    substitutePlayers.forEach((player, idx) => {
                        if (player && player.uuid === playerUuid) {
                            substitutePlayers[idx] = response.updated_player;
                        }
                    });
                }

                // Close modal and refresh displays
                closeModal('playerInfoModal');
                renderPlayers();
                renderSubstitutes();

                // Show result based on upgrade success/failure
                if (response.upgrade_result === 'success') {
                    Swal.fire({
                        icon: 'success',
                        title: '🎉 Upgrade Successful!',
                        html: `
                            <div class="text-center space-y-3">
                                <div class="bg-green-50 p-4 rounded-lg border border-green-200">
                                    <div class="text-lg font-bold text-green-900">${response.player_name}</div>
                                    <div class="text-sm text-green-700">Card Level ${response.old_card_level} → ${response.new_card_level}</div>
                                </div>
                                
                                <div class="bg-gray-50 p-3 rounded-lg">
                                    <div class="text-sm space-y-1">
                                        <div class="flex justify-between">
                                            <span class="text-gray-600">Success Rate:</span>
                                            <span class="font-medium text-green-600">${response.success_rate}%</span>
                                        </div>
                                        <div class="flex justify-between">
                                            <span class="text-gray-600">Your Roll:</span>
                                            <span class="font-medium text-blue-600">${response.luck_roll}</span>
                                        </div>
                                        <div class="flex justify-between">
                                            <span class="text-gray-600">Cost Paid:</span>
                                            <span class="font-medium text-red-600">${formatMarketValue(response.upgrade_cost)}</span>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="bg-blue-50 p-3 rounded-lg border border-blue-200">
                                    <div class="text-sm text-blue-800">
                                        <div>✨ New Rating Bonus: +${response.benefits.ratingBonus}</div>
                                        <div>💪 Max Fitness: ${response.benefits.maxFitness}/100</div>
                                        <div>💰 Weekly Salary: ${formatMarketValue(response.new_salary)}</div>
                                    </div>
                                </div>
                            </div>
                        `,
                        confirmButtonText: 'Awesome!',
                        confirmButtonColor: '#10b981'
                    });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: '💔 Upgrade Failed',
                        html: `
                            <div class="text-center space-y-3">
                                <div class="bg-red-50 p-4 rounded-lg border border-red-200">
                                    <div class="text-lg font-bold text-red-900">${response.player_name}</div>
                                    <div class="text-sm text-red-700">Upgrade attempt failed</div>
                                </div>
                                
                                <div class="bg-gray-50 p-3 rounded-lg">
                                    <div class="text-sm space-y-1">
                                        <div class="flex justify-between">
                                            <span class="text-gray-600">Success Rate:</span>
                                            <span class="font-medium text-orange-600">${response.success_rate}%</span>
                                        </div>
                                        <div class="flex justify-between">
                                            <span class="text-gray-600">Your Roll:</span>
                                            <span class="font-medium text-red-600">${response.luck_roll}</span>
                                        </div>
                                        <div class="flex justify-between">
                                            <span class="text-gray-600">Cost Paid:</span>
                                            <span class="font-medium text-red-600">${formatMarketValue(response.upgrade_cost)}</span>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="bg-yellow-50 p-3 rounded-lg border border-yellow-200">
                                    <div class="text-sm text-yellow-800">
                                        <div>🔄 You can try again (additional cost applies)</div>
                                        <div>💡 Higher card levels have lower success rates</div>
                                    </div>
                                </div>
                            </div>
                        `,
                        confirmButtonText: 'Try Again',
                        showCancelButton: true,
                        cancelButtonText: 'Maybe Later',
                        confirmButtonColor: '#f59e0b',
                        cancelButtonColor: '#6b7280'
                    }).then((result) => {
                        if (result.isConfirmed) {
                            // Retry the upgrade
                            upgradeCardLevel(playerUuid, playerName, currentCardLevel, response.updated_player.value);
                        }
                    });
                }
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'Upgrade Failed',
                    text: response.message || 'Could not upgrade card level. Please try again.',
                    confirmButtonColor: '#ef4444'
                });
            }
        }, 'json').fail(function() {
            Swal.fire({
                icon: 'error',
                title: 'Connection Error',
                text: 'Could not process card level upgrade. Please check your connection and try again.',
                confirmButtonColor: '#ef4444'
            });
        });
    }

    // Training functionality (only if button exists)
    $('#trainAllBtn').click(function() {
        Swal.fire({
            icon: 'question',
            title: 'Train All Players?',
            html: `
                <div class="text-left">
                    <p class="mb-3">This will improve fitness for all players in your squad.</p>
                    <div class="bg-gray-50 p-3 rounded">
                        <div class="flex justify-between mb-2">
                            <span>Training Cost:</span>
                            <span class="font-bold text-red-600">€2,000,000</span>
                        </div>
                        <div class="flex justify-between mb-2">
                            <span>Fitness Improvement:</span>
                            <span class="font-bold text-green-600">+5 to +15 per player</span>
                        </div>
                        <div class="flex justify-between">
                            <span>Cooldown:</span>
                            <span class="font-bold text-blue-600">24 hours</span>
                        </div>
                    </div>
                </div>
            `,
            showCancelButton: true,
            confirmButtonColor: '#16a34a',
            cancelButtonColor: '#6b7280',
            confirmButtonText: 'Start Training',
            cancelButtonText: 'Cancel'
        }).then((result) => {
            if (result.isConfirmed) {
                // Show loading
                Swal.fire({
                    title: 'Training in Progress...',
                    html: 'Improving player fitness',
                    allowOutsideClick: false,
                    didOpen: () => {
                        Swal.showLoading();
                    }
                });

                // Send training request
                $.post('api/train_players_api.php', {
                        action: 'train_all'
                    })
                    .done(function(response) {
                        if (response.success) {
                            // Handle level up notification first
                            if (response.level_up) {
                                handleLevelUpNotification(response);
                            } else {
                                Swal.fire({
                                    icon: 'success',
                                    title: 'Training Complete!',
                                    html: `
                                    <div class="text-left">
                                        <p class="mb-3">All players have completed training successfully!</p>
                                        <div class="bg-green-50 p-3 rounded">
                                            <div class="text-sm text-green-800">
                                                <strong>Results:</strong><br>
                                                • ${response.players_trained} players trained<br>
                                                • Average fitness improvement: +${response.avg_improvement}<br>
                                                • Cost: €${response.cost.toLocaleString()}
                                            </div>
                                        </div>
                                    </div>
                                `,
                                    confirmButtonColor: '#16a34a'
                                }).then(() => {
                                    // Reload page to show updated player conditions
                                    window.location.reload();
                                });
                            }
                        } else {
                            Swal.fire({
                                icon: 'error ',
                                title: 'Training Failed ',
                                text: response.message || ' Unable to complete training session ',
                                confirmButtonColor: '#ef4444'
                            });
                        }
                    })
                    .fail(function() {
                        Swal.fire({
                            icon: 'error',
                            title: 'Training Failed',
                            text: 'Network error occurred during training',
                            confirmButtonColor: '#ef4444'
                        });
                    });
            }
        });
    });

    // Daily Recovery functionality
    $('#dailyRecoveryBtn').click(function() {
        Swal.fire({
            icon: 'question',
            title: 'Process Daily Recovery?',
            html: `
                <div class="text-left">
                    <p class="mb-3">This will process daily recovery for all players:</p>
                    <div class="bg-gray-50 p-3 rounded">
                        <div class="text-sm text-gray-700">
                            <div class="mb-2">• Injured players recover 1 day</div>
                            <div class="mb-2">• Healthy players gain 1-3 fitness</div>
                            <div class="mb-2">• Fully recovered players return to action</div>
                            <div class="text-blue-600 font-medium">• Can only be used once per day</div>
                        </div>
                    </div>
                </div>
            `,
            showCancelButton: true,
            confirmButtonColor: '#2563eb',
            cancelButtonColor: '#6b7280',
            confirmButtonText: 'Process Recovery',
            cancelButtonText: 'Cancel'
        }).then((result) => {
            if (result.isConfirmed) {
                // Show loading
                Swal.fire({
                    title: 'Processing Recovery...',
                    html: 'Updating player conditions',
                    allowOutsideClick: false,
                    didOpen: () => {
                        Swal.showLoading();
                    }
                });

                // Send recovery request
                $.post('api/daily_recovery_api.php', {
                        action: 'process_daily_recovery'
                    })
                    .done(function(response) {
                        if (response.success) {
                            let resultHtml = '<div class="text-left">';

                            if (response.recovery_count > 0) {
                                resultHtml += `<p class="mb-2 text-green-600"><strong>${response.recovery_count} player(s) fully recovered!</strong></p>`;
                                response.recoveries.forEach(recovery => {
                                    resultHtml += `<div class="text-sm text-green-700">• ${recovery.player_name} is back to full health</div>`;
                                });
                            }

                            if (response.fitness_improvement_count > 0) {
                                resultHtml += `<p class="mb-2 mt-3 text-blue-600"><strong>${response.fitness_improvement_count} player(s) improved fitness:</strong></p>`;
                                response.fitness_improvements.forEach(improvement => {
                                    resultHtml += `<div class="text-sm text-blue-700">• ${improvement.player_name}: +${improvement.fitness_gain} fitness (${improvement.new_fitness}%)</div>`;
                                });
                            }

                            if (response.recovery_count === 0 && response.fitness_improvement_count === 0) {
                                resultHtml += '<p class="text-gray-600">No significant changes today. All players are in good condition!</p>';
                            }

                            resultHtml += '</div>';

                            Swal.fire({
                                icon: 'success',
                                title: 'Daily Recovery Complete!',
                                html: resultHtml,
                                confirmButtonColor: '#2563eb'
                            }).then(() => {
                                // Reload page to show updated player conditions
                                window.location.reload();
                            });
                        } else {
                            let errorMessage = response.message || 'Unable to process daily recovery';
                            if (response.already_processed) {
                                errorMessage = 'Daily recovery has already been processed today. Try again tomorrow!';
                            }

                            Swal.fire({
                                icon: 'info',
                                title: 'Recovery Not Processed',
                                text: errorMessage,
                                confirmButtonColor: '#2563eb'
                            });
                        }
                    })
                    .fail(function() {
                        Swal.fire({
                            icon: 'error',
                            title: 'Recovery Failed',
                            text: 'Network error occurred during recovery processing',
                            confirmButtonColor: '#ef4444'
                        });
                    });
            }
        });
    });

    // Helper function to generate player stats based on position and rating
    function generatePlayerStats(position, rating) {
        const baseStats = {
            'GK': ['Diving', 'Handling', 'Kicking', 'Reflexes', 'Positioning'],
            'CB': ['Defending', 'Heading', 'Strength', 'Marking', 'Tackling'],
            'LB': ['Pace', 'Crossing', 'Defending', 'Stamina', 'Dribbling'],
            'RB': ['Pace', 'Crossing', 'Defending', 'Stamina', 'Dribbling'],
            'CDM': ['Passing', 'Tackling', 'Positioning', 'Strength', 'Vision'],
            'CM': ['Passing', 'Dribbling', 'Vision', 'Stamina', 'Shooting'],
            'CAM': ['Passing', 'Dribbling', 'Vision', 'Shooting', 'Creativity'],
            'LM': ['Pace', 'Crossing', 'Dribbling', 'Stamina', 'Passing'],
            'RM': ['Pace', 'Crossing', 'Dribbling', 'Stamina', 'Passing'],
            'LW': ['Pace', 'Dribbling', 'Crossing', 'Shooting', 'Agility'],
            'RW': ['Pace', 'Dribbling', 'Crossing', 'Shooting', 'Agility'],
            'ST': ['Shooting', 'Finishing', 'Positioning', 'Strength', 'Heading'],
            'CF': ['Shooting', 'Dribbling', 'Passing', 'Positioning', 'Creativity']
        };

        const positionStats = baseStats[position] || baseStats['CM'];
        const stats = {};

        positionStats.forEach(stat => {
            // Generate stats based on overall rating with some variation
            const variation = Math.floor(Math.random() * 10) - 5; // -5 to +5
            const statValue = Math.max(30, Math.min(99, rating + variation));
            stats[stat] = statValue;
        });

        return stats;
    }

    // Close player info modal
    $('#closePlayerInfoModal ').click(function() {
        closeModal('playerInfoModal');
    });

    $('#playerInfoModal').click(function(e) {
        if (e.target === this) {
            closeModal('playerInfoModal');
        }
    });

    // Player Recommendations Functionality
    let currentRecommendations = [];

    // Open recommendations modal with cost confirmation
    $('#recommendPlayersBtn').click(function() {
        // First get the cost
        $.post('api/recommend_players_api.php', {
            action: 'get_cost'
        }, function(response) {
            if (response.success) {
                showRecommendationCostConfirmation(response.cost, response.formatted_cost);
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'Service Unavailable',
                    text: 'Unable to load recommendation service. Please try again.',
                    confirmButtonColor: '#ef4444'
                });
            }
        }, 'json').fail(function() {
            Swal.fire({
                icon: 'error',
                title: 'Connection Error',
                text: 'Unable to connect to recommendation service. Please check your connection.',
                confirmButtonColor: '#ef4444'
            });
        });
    });

    // Show cost confirmation dialog
    function showRecommendationCostConfirmation(cost, formattedCost) {
        const currentBudget = maxBudget;
        const remainingAfter = currentBudget - cost;

        if (currentBudget < cost) {
            Swal.fire({
                icon: 'warning',
                title: 'Insufficient Budget',
                html: `
                    <div class="text-left space-y-3">
                        <div class="bg-red-50 p-4 rounded-lg border border-red-200">
                            <h4 class="font-semibold text-red-900 mb-2">Budget Required:</h4>
                            <div class="space-y-1 text-sm">
                                <div class="flex justify-between">
                                    <span class="text-gray-600">Service Cost:</span>
                                    <span class="font-medium text-red-600">${formattedCost}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-600">Your Budget:</span>
                                    <span class="font-medium">${formatMarketValue(currentBudget)}</span>
                                </div>
                                <div class="flex justify-between border-t pt-1">
                                    <span class="text-gray-600">Shortage:</span>
                                    <span class="font-medium text-red-600">${formatMarketValue(cost - currentBudget)}</span>
                                </div>
                            </div>
                        </div>
                        
                        <div class="bg-blue-50 p-4 rounded-lg border border-blue-200">
                            <div class="text-sm text-blue-800">
                                <i data-lucide="lightbulb" class="w-4 h-4 inline mr-1"></i>
                                Earn more budget by winning matches or selling players
                            </div>
                        </div>
                    </div>
                `,
                confirmButtonText: 'Understood',
                confirmButtonColor: '#3b82f6',
                customClass: {
                    popup: 'swal-wide'
                },
                didOpen: () => {
                    lucide.createIcons();
                }
            });
            return;
        }

        Swal.fire({
            title: 'AI Player Recommendations',
            html: `
                <div class="text-left space-y-4">
                    <div class="bg-gradient-to-r from-blue-50 to-indigo-50 p-4 rounded-lg border border-blue-200">
                        <div class="flex items-center gap-3 mb-3">
                            <div class="w-12 h-12 bg-blue-600 rounded-full flex items-center justify-center">
                                <i data-lucide="brain" class="w-6 h-6 text-white"></i>
                            </div>
                            <div>
                                <h4 class="font-bold text-blue-900">Premium AI Analysis</h4>
                                <p class="text-sm text-blue-700">Get personalized player recommendations</p>
                            </div>
                        </div>
                        
                        <div class="space-y-2 text-sm text-blue-800">
                            <div class="flex items-center gap-2">
                                <i data-lucide="check" class="w-4 h-4 text-green-600"></i>
                                <span>Analyze your team composition</span>
                            </div>
                            <div class="flex items-center gap-2">
                                <i data-lucide="check" class="w-4 h-4 text-green-600"></i>
                                <span>Identify weak positions and gaps</span>
                            </div>
                            <div class="flex items-center gap-2">
                                <i data-lucide="check" class="w-4 h-4 text-green-600"></i>
                                <span>Recommend best value players</span>
                            </div>
                            <div class="flex items-center gap-2">
                                <i data-lucide="check" class="w-4 h-4 text-green-600"></i>
                                <span>Prioritize by formation needs</span>
                            </div>
                        </div>
                    </div>

                    <div class="bg-gray-50 p-4 rounded-lg border">
                        <h4 class="font-semibold text-gray-900 mb-3">Service Cost:</h4>
                        <div class="space-y-2 text-sm">
                            <div class="flex justify-between">
                                <span class="text-gray-600">AI Analysis Fee:</span>
                                <span class="font-bold text-red-600">${formattedCost}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-600">Current Budget:</span>
                                <span class="font-medium">${formatMarketValue(currentBudget)}</span>
                            </div>
                            <div class="flex justify-between border-t pt-2">
                                <span class="text-gray-600">After Payment:</span>
                                <span class="font-medium ${remainingAfter >= 0 ? 'text-green-600' : 'text-red-600'}">${formatMarketValue(remainingAfter)}</span>
                            </div>
                        </div>
                    </div>

                    <div class="bg-yellow-50 p-4 rounded-lg border border-yellow-200">
                        <div class="flex items-start gap-2">
                            <i data-lucide="info" class="w-4 h-4 text-yellow-600 mt-0.5"></i>
                            <div class="text-sm text-yellow-800">
                                <strong>One-time fee:</strong> This analysis will provide up to 10 personalized player recommendations based on your current team needs and budget.
                            </div>
                        </div>
                    </div>
                </div>
            `,
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#10b981',
            cancelButtonColor: '#6b7280',
            confirmButtonText: `<i data-lucide="credit-card" class="w-4 h-4 inline mr-2"></i>Pay ${formattedCost} & Get Recommendations`,
            cancelButtonText: 'Cancel',
            customClass: {
                popup: 'swal-wide'
            },
            didOpen: () => {
                lucide.createIcons();
            }
        }).then((result) => {
            if (result.isConfirmed) {
                openModal('recommendationsModal');
                loadRecommendations();
            }
        });
    }

    // Close recommendations modal
    $('#closeRecommendationsModal, #closeRecommendationsModalFooter').click(function() {
        closeModal('recommendationsModal');
    });

    // Close modal when clicking outside
    $('#recommendationsModal').click(function(e) {
        if (e.target === this) {
            closeModal('recommendationsModal');
        }
    });

    // Retry button
    $('#retryRecommendations').click(function() {
        loadRecommendations();
    });

    // Filter recommendations
    $('.recommendation-filter').click(function() {
        $('.recommendation-filter').removeClass('active bg-blue-600 text-white').addClass('bg-gray-200 text-gray-700');
        $(this).removeClass('bg-gray-200 text-gray-700').addClass('active bg-blue-600 text-white');

        const filter = $(this).data('filter');
        filterRecommendations(filter);
    });

    // Load recommendations from API with progress animation
    function loadRecommendations() {
        // Show progress animation
        showAIAnalysisProgress();
    }

    // Show AI analysis progress with realistic steps
    function showAIAnalysisProgress() {
        let progress = 0;
        let currentStep = 0;
        let progressInterval = null;

        const analysisSteps = [{
                text: 'Initializing AI analysis engine...',
                duration: 800
            },
            {
                text: 'Scanning current team composition...',
                duration: 1200
            },
            {
                text: 'Analyzing formation requirements...',
                duration: 1000
            },
            {
                text: 'Evaluating player ratings and positions...',
                duration: 1500
            },
            {
                text: 'Identifying team weaknesses...',
                duration: 1000
            },
            {
                text: 'Searching player database...',
                duration: 1800
            },
            {
                text: 'Calculating compatibility scores...',
                duration: 1200
            },
            {
                text: 'Applying budget constraints...',
                duration: 800
            },
            {
                text: 'Ranking recommendations by priority...',
                duration: 1000
            },
            {
                text: 'Finalizing analysis results...',
                duration: 600
            }
        ];

        // Update loading content with progress bar
        $('#recommendationsLoading').html(`
            <div class="text-center py-8">
                <div class="mb-6">
                    <div class="w-16 h-16 bg-gradient-to-r from-blue-600 to-purple-600 rounded-full flex items-center justify-center mx-auto mb-4">
                        <i data-lucide="brain" class="w-8 h-8 text-white animate-pulse"></i>
                    </div>
                    <h3 class="text-xl font-bold text-gray-800 mb-2">AI Analysis in Progress</h3>
                    <p class="text-gray-600">Our advanced AI is analyzing your team...</p>
                </div>
                
                <div class="max-w-md mx-auto">
                    <div class="mb-4">
                        <div id="analysisStep" class="text-sm text-blue-600 font-medium mb-2">${analysisSteps[0].text}</div>
                        <div class="w-full bg-gray-200 rounded-full h-3 overflow-hidden">
                            <div id="analysisProgress" class="bg-gradient-to-r from-blue-500 via-purple-500 to-blue-600 h-3 rounded-full transition-all duration-500 relative" style="width: 0%">
                                <div class="absolute inset-0 bg-white opacity-30 animate-pulse"></div>
                            </div>
                        </div>
                        <div class="flex justify-between items-center mt-2">
                            <span id="analysisPercentage" class="text-xs text-gray-500">0%</span>
                            <span class="text-xs text-gray-500">Processing...</span>
                        </div>
                    </div>
                    
                    <div class="grid grid-cols-3 gap-2 mt-4">
                        <div class="bg-blue-50 p-2 rounded text-center">
                            <div class="text-xs text-blue-600 font-medium">Team Scan</div>
                            <div id="teamScanStatus" class="text-xs text-gray-500">Pending</div>
                        </div>
                        <div class="bg-purple-50 p-2 rounded text-center">
                            <div class="text-xs text-purple-600 font-medium">AI Analysis</div>
                            <div id="aiAnalysisStatus" class="text-xs text-gray-500">Pending</div>
                        </div>
                        <div class="bg-green-50 p-2 rounded text-center">
                            <div class="text-xs text-green-600 font-medium">Results</div>
                            <div id="resultsStatus" class="text-xs text-gray-500">Pending</div>
                        </div>
                    </div>
                </div>
            </div>
        `);

        $('#recommendationsLoading').removeClass('hidden');
        $('#teamAnalysisSection, #recommendationsSection, #recommendationsError').addClass('hidden');

        // Recreate icons after HTML update
        lucide.createIcons();

        let stepIndex = 0;
        let stepProgress = 0;

        function updateProgress() {
            const currentStepData = analysisSteps[stepIndex];
            const stepDuration = currentStepData.duration;
            const progressIncrement = (100 / analysisSteps.length) / (stepDuration / 50);

            stepProgress += progressIncrement;
            progress = (stepIndex * (100 / analysisSteps.length)) + (stepProgress * (100 / analysisSteps.length) / 100);

            // Update progress bar
            $('#analysisProgress').css('width', Math.min(progress, 100) + '%');
            $('#analysisPercentage').text(Math.floor(Math.min(progress, 100)) + '%');

            // Update status indicators
            if (progress >= 30 && $('#teamScanStatus').text() === 'Pending') {
                $('#teamScanStatus').text('Complete').removeClass('text-gray-500').addClass('text-blue-600');
            }
            if (progress >= 70 && $('#aiAnalysisStatus').text() === 'Pending') {
                $('#aiAnalysisStatus').text('Complete').removeClass('text-gray-500').addClass('text-purple-600');
            }
            if (progress >= 95 && $('#resultsStatus').text() === 'Pending') {
                $('#resultsStatus').text('Complete').removeClass('text-gray-500').addClass('text-green-600');
            }

            // Check if current step is complete
            if (stepProgress >= 100) {
                stepIndex++;
                stepProgress = 0;

                if (stepIndex < analysisSteps.length) {
                    $('#analysisStep').text(analysisSteps[stepIndex].text);
                } else {
                    // All steps complete, make API call
                    clearInterval(progressInterval);

                    // Show final completion step
                    $('#analysisStep').text('Analysis complete! Loading results...');
                    $('#analysisProgress').css('width', '100%');
                    $('#analysisPercentage').text('100%');

                    // Wait a moment then load actual results
                    setTimeout(() => {
                        loadActualRecommendations();
                    }, 500);
                    return;
                }
            }
        }

        // Start progress animation
        progressInterval = setInterval(updateProgress, 50);

        // Safety timeout (max 15 seconds)
        setTimeout(() => {
            if (progressInterval) {
                clearInterval(progressInterval);
                loadActualRecommendations();
            }
        }, 15000);
    }

    // Load actual recommendations from API
    function loadActualRecommendations() {
        $.post('api/recommend_players_api.php', {
            action: 'get_recommendations'
        }, function(response) {
            $('#recommendationsLoading').addClass('hidden');

            if (response.success) {
                // Update local budget after payment
                if (response.cost_paid) {
                    maxBudget = response.budget;
                    $('#clubBudget').text(formatMarketValue(response.budget));

                    // Show payment success toast
                    Swal.fire({
                        icon: 'success',
                        title: 'Analysis Complete!',
                        html: `
                            <div class="text-center">
                                <p class="mb-2">AI analysis completed successfully!</p>
                                <p class="text-sm text-gray-600">Cost: ${formatMarketValue(response.cost_paid)}</p>
                                <p class="text-sm text-blue-600">Remaining Budget: ${formatMarketValue(response.budget)}</p>
                            </div>
                        `,
                        timer: 3000,
                        showConfirmButton: false,
                        toast: true,
                        position: 'top-end'
                    });
                }

                currentRecommendations = response.recommendations;
                displayTeamAnalysis(response);
                displayRecommendations(response.recommendations);
                $('#teamAnalysisSection, #recommendationsSection').removeClass('hidden');
            } else {
                if (response.error_type === 'insufficient_budget') {
                    closeModal('recommendationsModal');
                    Swal.fire({
                        icon: 'warning',
                        title: 'Insufficient Budget',
                        html: `
                            <div class="text-left">
                                <p class="mb-3">You don't have enough budget for AI recommendations.</p>
                                <div class="bg-gray-50 p-3 rounded">
                                    <div class="flex justify-between mb-2">
                                        <span>Required:</span>
                                        <span class="font-bold text-red-600">${formatMarketValue(response.required)}</span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span>Your Budget:</span>
                                        <span class="font-bold">${formatMarketValue(response.current)}</span>
                                    </div>
                                </div>
                            </div>
                        `,
                        confirmButtonColor: '#3b82f6'
                    });
                } else {
                    $('#recommendationsError').removeClass('hidden');
                }
            }
        }, 'json').fail(function() {
            $('#recommendationsLoading').addClass('hidden');
            $('#recommendationsError').removeClass('hidden');
        });
    }

    // Display team analysis
    function displayTeamAnalysis(data) {
        $('#totalPlayersCount').text(data.teamAnalysis.totalPlayers + '/11');
        $('#avgTeamRating').text('★' + data.teamAnalysis.avgRating);
        $('#availableBudget').text(formatMarketValue(data.budget));

        // Show issues if any
        if (data.emptyPositions.length > 0 || data.weakPositions.length > 0) {
            $('#issuesFound').removeClass('hidden');

            if (data.emptyPositions.length > 0) {
                $('#emptyPositionsList').removeClass('hidden');
                $('#emptyPositionsText').text(data.emptyPositions.join(', '));
            }

            if (data.weakPositions.length > 0) {
                $('#weakPositionsList').removeClass('hidden');
                $('#weakPositionsText').text(data.weakPositions.join(', '));
            }
        }
    }

    // Display recommendations
    function displayRecommendations(recommendations) {
        const $list = $('#recommendationsList');
        $list.empty();

        if (recommendations.length === 0) {
            $('#noRecommendations').removeClass('hidden');
            return;
        }

        recommendations.forEach((rec, index) => {
            const player = rec.player;
            const priorityColors = {
                'high': 'border-red-200 bg-red-50',
                'medium': 'border-orange-200 bg-orange-50',
                'low': 'border-blue-200 bg-blue-50'
            };

            const priorityLabels = {
                'high': 'High Priority',
                'medium': 'Medium Priority',
                'low': 'Squad Depth'
            };

            const priorityTextColors = {
                'high': 'text-red-700',
                'medium': 'text-orange-700',
                'low': 'text-blue-700'
            };

            $list.append(`
                <div class="recommendation-item border rounded-lg p-4 ${priorityColors[rec.priority]} transition-all duration-200 hover:shadow-md" data-priority="${rec.priority}">
                    <div class="flex items-center justify-between">
                        <div class="flex-1">
                            <div class="flex items-center gap-3 mb-2">
                                <div class="w-12 h-12 bg-white rounded-full flex items-center justify-center shadow-sm">
                                    <i data-lucide="user" class="w-6 h-6 text-gray-600"></i>
                                </div>
                                <div>
                                    <h4 class="font-bold text-lg">${player.name}</h4>
                                    <div class="flex items-center gap-2 text-sm text-gray-600">
                                        <span class="bg-white px-2 py-1 rounded font-medium">${player.position}</span>
                                        <span class="flex items-center gap-1">
                                            <i data-lucide="star" class="w-3 h-3 text-yellow-500"></i>
                                            ${player.rating}
                                        </span>
                                        <span class="text-green-600 font-semibold">${formatMarketValue(player.value)}</span>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <div class="flex items-center gap-2 mb-1">
                                    <span class="px-2 py-1 rounded-full text-xs font-medium ${priorityTextColors[rec.priority]} bg-white">
                                        ${priorityLabels[rec.priority]}
                                    </span>
                                </div>
                                <p class="text-sm text-gray-700">${rec.reason}</p>
                            </div>

                            <div class="flex items-center gap-4 text-xs text-gray-600">
                                <div class="flex items-center gap-1">
                                    <i data-lucide="trending-up" class="w-3 h-3"></i>
                                    Match Score: ${Math.round(rec.score)}
                                </div>
                                <div class="flex items-center gap-1">
                                    <i data-lucide="dollar-sign" class="w-3 h-3"></i>
                                    Value Rating: ${(player.rating / (player.value / 1000000)).toFixed(1)}
                                </div>
                            </div>
                        </div>
                        
                        <div class="flex flex-col gap-2 ml-4">
                            <button onclick="showPlayerInfo(${JSON.stringify(player).replace(/"/g, '&quot;')})" 
                                class="px-3 py-2 bg-white text-gray-700 rounded hover:bg-gray-50 border flex items-center gap-2 text-sm">
                                <i data-lucide="info" class="w-4 h-4"></i>
                                View Details
                            </button>
                            <button onclick="selectRecommendedPlayer(${JSON.stringify(player).replace(/"/g, '&quot;')})" 
                                class="px-3 py-2 bg-green-600 text-white rounded hover:bg-green-700 flex items-center gap-2 text-sm font-medium">
                                <i data-lucide="plus" class="w-4 h-4"></i>
                                Add Player
                            </button>
                        </div>
                    </div>
                </div>
            `);
        });

        lucide.createIcons();
    }

    // Filter recommendations by priority
    function filterRecommendations(filter) {
        $('.recommendation-item').each(function() {
            const priority = $(this).data('priority');
            if (filter === 'all' || priority === filter) {
                $(this).removeClass('hidden');
            } else {
                $(this).addClass('hidden');
            }
        });
    }

    // Select recommended player
    window.selectRecommendedPlayer = function(player) {
        // Close recommendations modal
        closeModal('recommendationsModal');

        // Find empty slot or ask user to choose position
        const emptySlot = selectedPlayers.findIndex(p => p === null);

        if (emptySlot !== -1) {
            // Direct assignment to empty slot
            currentSlotIdx = emptySlot;
            isSelectingSubstitute = false;

            // Show confirmation before adding
            Swal.fire({
                title: 'Add Recommended Player?',
                html: `
                    <div class="text-left space-y-3">
                        <div class="bg-gray-50 p-4 rounded-lg">
                            <h4 class="font-semibold text-gray-900 mb-2">Player Details:</h4>
                            <div class="space-y-1 text-sm">
                                <div class="flex justify-between">
                                    <span class="text-gray-600">Name:</span>
                                    <span class="font-medium">${player.name}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-600">Position:</span>
                                    <span class="font-medium">${player.position}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-600">Rating:</span>
                                    <span class="font-medium">★${player.rating}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-600">Cost:</span>
                                    <span class="font-medium text-red-600">${formatMarketValue(player.value)}</span>
                                </div>
                            </div>
                        </div>
                        
                        <div class="bg-blue-50 p-4 rounded-lg border border-blue-200">
                            <div class="text-sm text-blue-800">
                                <i data-lucide="lightbulb" class="w-4 h-4 inline mr-1"></i>
                                This player was recommended based on your team's needs
                            </div>
                        </div>
                    </div>
                `,
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#10b981',
                cancelButtonColor: '#6b7280',
                confirmButtonText: '<i data-lucide="plus" class="w-4 h-4 inline mr-1"></i> Add Player',
                cancelButtonText: 'Cancel',
                customClass: {
                    popup: 'swal-wide'
                },
                didOpen: () => {
                    lucide.createIcons();
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    // Add the player using existing logic
                    if (window.selectModalPlayer) {
                        // Find player index in the players array
                        const playerIndex = players.findIndex(p => p.name === player.name);
                        if (playerIndex !== -1) {
                            window.selectModalPlayer(playerIndex);
                        }
                    }
                }
            });
        } else {
            // Team is full, ask user to choose position to replace
            Swal.fire({
                title: 'Team is Full',
                text: 'Your starting XI is complete. Would you like to add this player as a substitute or replace an existing player?',
                icon: 'question',
                showCancelButton: true,
                showDenyButton: true,
                confirmButtonText: 'Add as Substitute',
                denyButtonText: 'Replace Player',
                cancelButtonText: 'Cancel',
                confirmButtonColor: '#3b82f6',
                denyButtonColor: '#f59e0b',
                cancelButtonColor: '#6b7280'
            }).then((result) => {
                if (result.isConfirmed) {
                    // Add as substitute
                    const emptySubSlot = substitutePlayers.findIndex(p => p === null);
                    if (emptySubSlot !== -1) {
                        currentSlotIdx = emptySubSlot;
                        isSelectingSubstitute = true;
                        const playerIndex = players.findIndex(p => p.name === player.name);
                        if (playerIndex !== -1 && window.selectModalPlayer) {
                            window.selectModalPlayer(playerIndex);
                        }
                    } else {
                        Swal.fire({
                            icon: 'warning',
                            title: 'Substitutes Full',
                            text: 'Your substitute bench is also full. Please remove a player first.',
                            confirmButtonColor: '#3b82f6'
                        });
                    }
                } else if (result.isDenied) {
                    // Show player selection for replacement
                    Swal.fire({
                        icon: 'info',
                        title: 'Choose Position',
                        text: 'Click on a field position to replace that player with the recommended player.',
                        confirmButtonColor: '#3b82f6'
                    });
                }
            });
        }
    };

    // Make substitute functions globally available
    window.removeSubstitute = removeSubstitute;
    window.promoteSubstitute = promoteSubstitute;
    window.switchWithStartingPlayer = switchWithStartingPlayer;
    window.performPlayerSwitch = performPlayerSwitch;
    window.replaceStartingPlayer = replaceStartingPlayer;
    window.showPlayerInfo = showPlayerInfo;

    // Quick search for substitute functionality
    $('#quickSearchSubstitute').on('click', function() {
        const maxSubstitutes = maxPlayers - 11;

        // Ensure substitutePlayers array has the correct length
        while (substitutePlayers.length < maxSubstitutes) {
            substitutePlayers.push(null);
        }

        // Find the next available substitute slot
        const availableSlot = substitutePlayers.findIndex(p => p === null);

        if (availableSlot === -1) {
            Swal.fire({
                icon: 'warning',
                title: 'Substitutes Full',
                text: `You already have the maximum number of substitutes (${maxSubstitutes}).`,
                confirmButtonColor: '#3b82f6'
            });
            return;
        }

        // Double-check that the slot is actually empty (safety check)
        if (substitutePlayers[availableSlot] !== null && substitutePlayers[availableSlot] !== undefined) {
            Swal.fire({
                icon: 'error',
                title: 'Slot Not Empty',
                text: 'The selected substitute slot is not empty. Please try again.',
                confirmButtonColor: '#3b82f6'
            });
            return;
        }

        // Set up for substitute selection
        currentSlotIdx = availableSlot;
        isSelectingSubstitute = true;

        // Open the player selection modal
        openPlayerModal();
    });

    // Team Fitness Upgrade
    $('#upgradeFitnessBtn').click(function() {
        // Calculate estimated cost (client-side estimation)
        let totalCost = 0;
        let playersToHeal = 0;
        const costPerPoint = 1000;

        // Check starting players
        selectedPlayers.forEach(player => {
            if (player && (player.fitness || 100) < 100) {
                const missing = 100 - (player.fitness || 100);
                let cost = missing * costPerPoint;

                // Rating multiplier logic (approximate to server)
                const rating = player.rating || 75;
                const multiplier = Math.max(1.0, rating / 75);
                cost = Math.round(cost * multiplier);

                totalCost += cost;
                playersToHeal++;
            }
        });

        // Check substitutes
        substitutePlayers.forEach(player => {
            if (player && (player.fitness || 100) < 100) {
                const missing = 100 - (player.fitness || 100);
                let cost = missing * costPerPoint;

                // Rating multiplier logic (approximate to server)
                const rating = player.rating || 75;
                const multiplier = Math.max(1.0, rating / 75);
                cost = Math.round(cost * multiplier);

                totalCost += cost;
                playersToHeal++;
            }
        });

        if (playersToHeal === 0) {
            Swal.fire({
                icon: 'info',
                title: 'Full Fitness',
                text: 'All players are already at 100% fitness!',
                confirmButtonColor: '#3b82f6'
            });
            return;
        }

        Swal.fire({
            title: 'Upgrade Team Fitness?',
            html: `
                <div class="text-left space-y-3">
                    <div class="bg-blue-50 p-4 rounded-lg border border-blue-200">
                        <h4 class="font-semibold text-blue-900 mb-2">Fitness Restoration</h4>
                        <div class="space-y-1 text-sm text-blue-800">
                            <div class="flex justify-between">
                                <span>Players to heal:</span>
                                <span class="font-bold">${playersToHeal}</span>
                            </div>
                            <div class="flex justify-between">
                                <span>Target Fitness:</span>
                                <span class="font-bold text-green-600">100%</span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="bg-yellow-50 p-4 rounded-lg border border-yellow-200">
                        <h4 class="font-semibold text-yellow-900 mb-2">Estimated Cost:</h4>
                        <div class="text-2xl font-bold text-yellow-600">${formatMarketValue(totalCost)}</div>
                        <p class="text-xs text-yellow-700 mt-1">Cost depends on player ratings and missing fitness.</p>
                    </div>
                </div>
            `,
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#10b981',
            cancelButtonColor: '#6b7280',
            confirmButtonText: '<i data-lucide="zap" class="w-4 h-4 inline mr-1"></i> Confirm Upgrade',
            cancelButtonText: 'Cancel',
            didOpen: () => {
                lucide.createIcons();
            }
        }).then((result) => {
            if (result.isConfirmed) {
                // Show loading
                Swal.fire({
                    title: 'Restoring Fitness...',
                    text: 'Please wait while we treat your players',
                    allowOutsideClick: false,
                    allowEscapeKey: false,
                    showConfirmButton: false,
                    didOpen: () => {
                        Swal.showLoading();
                    }
                });

                // Call API
                $.post('api/upgrade_team_fitness_api.php', {}, function(response) {
                    if (response.success) {
                        // Update local data
                        if (response.updated_team) {
                            selectedPlayers = response.updated_team;
                        }
                        if (response.updated_substitutes) {
                            substitutePlayers = response.updated_substitutes;
                        }

                        // Update budget
                        maxBudget = response.new_budget;
                        $('#remainingBudget').text(formatMarketValue(maxBudget));
                        $('#clubBudget').text(formatMarketValue(maxBudget));

                        renderPlayers();
                        renderField();
                        renderSubstitutes();
                        updateClubStats();
                        if (typeof selectedPlayerIdx !== 'undefined' && selectedPlayerIdx !== null) {
                            updateSelectedPlayerInfo();
                        }

                        Swal.fire({
                            icon: 'success',
                            title: 'Fitness Restored!',
                            text: `Successfully restored fitness for all players. Cost: ${formatMarketValue(response.cost)}`,
                            timer: 3000,
                            showConfirmButton: false,
                            toast: true,
                            position: 'top-end'
                        });
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'Upgrade Failed',
                            text: response.message || 'Could not upgrade fitness',
                            confirmButtonColor: '#ef4444'
                        });
                    }
                }, 'json').fail(function() {
                    Swal.fire({
                        icon: 'error',
                        title: 'Connection Error',
                        text: 'Failed to connect to server',
                        confirmButtonColor: '#ef4444'
                    });
                });
            }
        });
    });

    // Team Form Upgrade
    $('#upgradeFormBtn').click(function() {
        // Calculate estimated cost (client-side estimation)
        let totalCost = 0;
        let playersToBoost = 0;
        const costPerPoint = 50000; // Higher cost per form point

        // Check starting players
        selectedPlayers.forEach(player => {
            if (player && (player.form || 7) < 10) {
                const missing = 10 - (player.form || 7);
                let cost = missing * costPerPoint;

                // Rating multiplier logic
                const rating = player.rating || 75;
                const multiplier = Math.max(1.0, rating / 75);
                cost = Math.round(cost * multiplier);

                totalCost += cost;
                playersToBoost++;
            }
        });

        // Check substitutes
        substitutePlayers.forEach(player => {
            if (player && (player.form || 7) < 10) {
                const missing = 10 - (player.form || 7);
                let cost = missing * costPerPoint;

                // Rating multiplier logic
                const rating = player.rating || 75;
                const multiplier = Math.max(1.0, rating / 75);
                cost = Math.round(cost * multiplier);

                totalCost += cost;
                playersToBoost++;
            }
        });

        if (playersToBoost === 0) {
            Swal.fire({
                icon: 'info',
                title: 'Peak Form',
                text: 'All players are already at peak form (10.0)!',
                confirmButtonColor: '#3b82f6'
            });
            return;
        }

        Swal.fire({
            title: 'Upgrade Team Form?',
            html: `
                <div class="text-left space-y-3">
                    <div class="bg-purple-50 p-4 rounded-lg border border-purple-200">
                        <h4 class="font-semibold text-purple-900 mb-2">Form Boost</h4>
                        <div class="space-y-1 text-sm text-purple-800">
                            <div class="flex justify-between">
                                <span>Players to boost:</span>
                                <span class="font-bold">${playersToBoost}</span>
                            </div>
                            <div class="flex justify-between">
                                <span>Target Form:</span>
                                <span class="font-bold text-green-600">10.0 (Superb)</span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="bg-yellow-50 p-4 rounded-lg border border-yellow-200">
                        <h4 class="font-semibold text-yellow-900 mb-2">Estimated Cost:</h4>
                        <div class="text-2xl font-bold text-yellow-600">${formatMarketValue(totalCost)}</div>
                        <p class="text-xs text-yellow-700 mt-1">Form upgrades are premium services.</p>
                    </div>
                </div>
            `,
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#8b5cf6',
            cancelButtonColor: '#6b7280',
            confirmButtonText: '<i data-lucide="trending-up" class="w-4 h-4 inline mr-1"></i> Confirm Boost',
            cancelButtonText: 'Cancel',
            didOpen: () => {
                lucide.createIcons();
            }
        }).then((result) => {
            if (result.isConfirmed) {
                // Show loading
                Swal.fire({
                    title: 'Boosting Form...',
                    text: 'Motivating players to peak performance',
                    allowOutsideClick: false,
                    allowEscapeKey: false,
                    showConfirmButton: false,
                    didOpen: () => {
                        Swal.showLoading();
                    }
                });

                // Call API
                $.post('api/upgrade_team_form_api.php', {}, function(response) {
                    if (response.success) {
                        // Update local data
                        if (response.updated_team) {
                            selectedPlayers = response.updated_team;
                        }
                        if (response.updated_substitutes) {
                            substitutePlayers = response.updated_substitutes;
                        }

                        // Update budget
                        maxBudget = response.new_budget;
                        $('#remainingBudget').text(formatMarketValue(maxBudget));
                        $('#clubBudget').text(formatMarketValue(maxBudget));

                        renderPlayers();
                        renderField();
                        renderSubstitutes();
                        updateClubStats();
                        if (typeof selectedPlayerIdx !== 'undefined' && selectedPlayerIdx !== null) {
                            updateSelectedPlayerInfo();
                        }

                        Swal.fire({
                            icon: 'success',
                            title: 'Form Boosted!',
                            text: `Successfully boosted form for all players. Cost: ${formatMarketValue(response.cost)}`,
                            timer: 3000,
                            showConfirmButton: false,
                            toast: true,
                            position: 'top-end'
                        });
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'Upgrade Failed',
                            text: response.message || 'Could not upgrade form',
                            confirmButtonColor: '#ef4444'
                        });
                    }
                }, 'json').fail(function() {
                    Swal.fire({
                        icon: 'error',
                        title: 'Connection Error',
                        text: 'Failed to connect to server',
                        confirmButtonColor: '#ef4444'
                    });
                });
            }
        });
    });
</script>

<link rel="stylesheet" href="assets/css/swal-custom.css">
<link rel="stylesheet" href="assets/css/player-modal.css">
<link rel="stylesheet" href="assets/css/recommendations-modal.css">
<?php
// End content capture and render layout
endContent($_SESSION['club_name'], 'team', true, false, true);
?>
