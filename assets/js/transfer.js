// Transfer Market JavaScript
// Available players data from PHP - will be injected
let availablePlayers = [];
let userBudget = 0;

let filteredPlayers = [];
let currentPage = 1;
const playersPerPage = 10;

// Initialize function to be called after data injection
function initializeTransfer(playersData, budget) {
    availablePlayers = playersData;
    userBudget = budget;
    filteredPlayers = [...availablePlayers];
    
    updatePlayerCounts();
    renderPlayers();
    lucide.createIcons();
}

// Update player category counts
function updatePlayerCounts() {
    const counts = {
        modern: 0,
        legend: 0,
        young: 0,
        standard: 0
    };

    availablePlayers.forEach(item => {
        const category = item.player.category || 'modern';
        counts[category]++;
    });

    document.getElementById('currentPlayersCount').textContent = `${counts.modern} Modern Players`;
    document.getElementById('legendPlayersCount').textContent = `${counts.legend} Legends`;
    document.getElementById('youngPlayersCount').textContent = `${counts.young} Young Talents`;
    document.getElementById('standardPlayersCount').textContent = `${counts.standard} Standard Players`;
}

// Tab switching
function switchTab(tabName) {
    // Hide all tab contents
    document.querySelectorAll('.tab-content').forEach(content => {
        content.classList.add('hidden');
    });

    // Remove active class from all tabs
    document.querySelectorAll('[id$="Tab"]').forEach(tab => {
        tab.classList.remove('bg-white', 'text-blue-600', 'shadow-sm');
        tab.classList.add('text-gray-600', 'hover:text-gray-900');
    });

    // Show selected tab content
    document.getElementById(tabName + 'Content').classList.remove('hidden');

    // Add active class to selected tab
    const activeTab = document.getElementById(tabName + 'Tab');
    activeTab.classList.add('bg-white', 'text-blue-600', 'shadow-sm');
    activeTab.classList.remove('text-gray-600', 'hover:text-gray-900');
}

// Filter and search functionality
function filterPlayers() {
    const search = document.getElementById('playerSearch').value.toLowerCase();
    const category = document.getElementById('categoryFilter').value;
    const position = document.getElementById('positionFilter').value;
    const priceRange = document.getElementById('priceFilter').value;

    filteredPlayers = availablePlayers.filter(item => {
        const player = item.player;

        // Search filter
        const matchesSearch = !search ||
            player.name.toLowerCase().includes(search) ||
            item.club_name.toLowerCase().includes(search) ||
            item.owner_name.toLowerCase().includes(search);

        // Category filter
        const matchesCategory = !category || player.category === category;

        // Position filter
        const matchesPosition = !position || player.position === position;

        // Price filter
        let matchesPrice = true;
        if (priceRange) {
            const [min, max] = priceRange.split('-').map(Number);
            const playerValue = player.value || 0;
            matchesPrice = playerValue >= min && playerValue <= max;
        }

        return matchesSearch && matchesCategory && matchesPosition && matchesPrice;
    });

    currentPage = 1; // Reset to first page when filtering
    renderPlayers();
}

// Render players table with pagination
function renderPlayers() {
    const tableBody = document.getElementById('playersTableBody');
    const emptyState = document.getElementById('emptyState');
    const paginationContainer = document.getElementById('paginationContainer');

    if (filteredPlayers.length === 0) {
        tableBody.innerHTML = '';
        emptyState.classList.remove('hidden');
        paginationContainer.classList.add('hidden');
        lucide.createIcons();
        return;
    }

    emptyState.classList.add('hidden');
    paginationContainer.classList.remove('hidden');

    // Calculate pagination
    const totalPages = Math.ceil(filteredPlayers.length / playersPerPage);
    const startIndex = (currentPage - 1) * playersPerPage;
    const endIndex = Math.min(startIndex + playersPerPage, filteredPlayers.length);
    const currentPlayers = filteredPlayers.slice(startIndex, endIndex);

    // Render table rows
    tableBody.innerHTML = currentPlayers.map(item => {
        const player = item.player;
        const canAfford = userBudget >= (player.value || 0);

        // Get category display info
        const getCategoryInfo = (category) => {
            switch (category) {
                case 'legend':
                    return { text: 'Legend', class: 'bg-yellow-100 text-yellow-800' };
                case 'young':
                    return { text: 'Young', class: 'bg-green-100 text-green-800' };
                case 'modern':
                    return { text: 'Modern', class: 'bg-blue-100 text-blue-800' };
                case 'standard':
                    return { text: 'Standard', class: 'bg-gray-100 text-gray-800' };
                default:
                    return { text: 'Modern', class: 'bg-blue-100 text-blue-800' };
            }
        };

        const categoryInfo = getCategoryInfo(player.category);

        return `
            <tr class="hover:bg-gray-50">
                <td class="px-6 py-4 whitespace-nowrap">
                    <div class="flex items-center">
                        <div class="w-10 h-10 bg-gradient-to-br from-blue-500 to-blue-600 rounded-full flex items-center justify-center mr-3">
                            <i data-lucide="user" class="w-5 h-5 text-white"></i>
                        </div>
                        <div class="flex-1">
                            <div class="text-sm font-medium text-gray-900">${player.name}</div>
                        </div>
                        <button onclick="showPlayerInfo(${JSON.stringify(player).replace(/"/g, '&quot;')})" class="ml-2 p-1 text-blue-600 hover:bg-blue-100 rounded transition-colors" title="Player Info">
                            <i data-lucide="info" class="w-4 h-4"></i>
                        </button>
                    </div>
                </td>
                <td class="px-6 py-4 whitespace-nowrap">
                    <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full ${categoryInfo.class}">
                        ${categoryInfo.text}
                    </span>
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                    ${player.age ? player.age + ' years' : 'Unknown'}
                </td>
                <td class="px-6 py-4 whitespace-nowrap">
                    <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-gray-100 text-gray-800">
                        ${player.position || 'N/A'}
                    </span>
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                    ${player.rating || 'N/A'}
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-green-600">
                    ${formatMarketValue(player.value || 0)}
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                    <button onclick="makeBid(null, ${item.player_index})" 
                            class="inline-flex items-center px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-md ${canAfford ? 'text-white bg-green-600 hover:bg-green-700' : 'text-gray-500 bg-gray-300 cursor-not-allowed'} transition-colors"
                            ${!canAfford ? 'disabled' : ''}>
                        <i data-lucide="shopping-cart" class="w-4 h-4 mr-1"></i>
                        ${canAfford ? 'Buy' : 'Cannot Afford'}
                    </button>
                </td>
            </tr>
        `;
    }).join('');

    // Update pagination info
    document.getElementById('showingStart').textContent = startIndex + 1;
    document.getElementById('showingEnd').textContent = endIndex;
    document.getElementById('totalPlayers').textContent = filteredPlayers.length;

    // Render pagination controls
    renderPagination(totalPages);

    lucide.createIcons();
}

// Render pagination controls
function renderPagination(totalPages) {
    const pageNumbers = document.getElementById('pageNumbers');
    const prevButton = document.getElementById('prevPage');
    const nextButton = document.getElementById('nextPage');

    // Update prev/next buttons
    prevButton.disabled = currentPage === 1;
    nextButton.disabled = currentPage === totalPages;

    // Generate page numbers
    let pages = [];
    const maxVisiblePages = 5;

    if (totalPages <= maxVisiblePages) {
        // Show all pages if total is small
        for (let i = 1; i <= totalPages; i++) {
            pages.push(i);
        }
    } else {
        // Show smart pagination
        if (currentPage <= 3) {
            pages = [1, 2, 3, 4, 5];
        } else if (currentPage >= totalPages - 2) {
            pages = [totalPages - 4, totalPages - 3, totalPages - 2, totalPages - 1, totalPages];
        } else {
            pages = [currentPage - 2, currentPage - 1, currentPage, currentPage + 1, currentPage + 2];
        }
    }

    pageNumbers.innerHTML = pages.map(page => `
        <button onclick="goToPage(${page})" 
                class="px-3 py-2 text-sm font-medium ${page === currentPage ? 'text-blue-600 bg-blue-50 border-blue-500' : 'text-gray-500 bg-white border-gray-300 hover:bg-gray-50'} border rounded-md">
            ${page}
        </button>
    `).join('');
}

// Go to specific page
function goToPage(page) {
    currentPage = page;
    renderPlayers();
}

// Previous page
function previousPage() {
    if (currentPage > 1) {
        currentPage--;
        renderPlayers();
    }
}

// Next page
function nextPage() {
    const totalPages = Math.ceil(filteredPlayers.length / playersPerPage);
    if (currentPage < totalPages) {
        currentPage++;
        renderPlayers();
    }
}

// Format market value
function formatMarketValue(value) {
    if (value >= 1000000) {
        return '€' + (value / 1000000).toFixed(1) + 'M';
    } else if (value >= 1000) {
        return '€' + (value / 1000).toFixed(0) + 'K';
    } else {
        return '€' + value;
    }
}

// Calculate weekly salary based on player value and rating
function calculateSalary(player) {
    const baseValue = player.value || 1000000;
    const rating = player.rating || 70;

    // Calculate weekly salary as a percentage of market value
    // Higher rated players get higher percentage
    let salaryPercentage = 0.001; // Base 0.1% of market value per week

    // Bonus based on rating
    if (rating >= 90) salaryPercentage = 0.002; // 0.2% for 90+ rated
    else if (rating >= 85) salaryPercentage = 0.0018; // 0.18% for 85+ rated
    else if (rating >= 80) salaryPercentage = 0.0015; // 0.15% for 80+ rated
    else if (rating >= 75) salaryPercentage = 0.0012; // 0.12% for 75+ rated

    const weeklySalary = Math.round(baseValue * salaryPercentage);

    // Minimum salary of €10K per week
    return Math.max(weeklySalary, 10000);
}

// Make bid function for market players
function makeBid(ownerId, playerIndex) {
    // Find the player data from availablePlayers array
    const playerItem = availablePlayers.find(item =>
        item.player_index === playerIndex
    );

    if (!playerItem) {
        Swal.fire({
            icon: 'error',
            title: 'Player Not Found',
            text: 'The selected player could not be found.',
            confirmButtonColor: '#ef4444'
        });
        return;
    }

    const player = playerItem.player;
    const playerName = player.name;
    const playerData = player; // Pass the raw object, not JSON string
    const playerValue = player.value || 0;

    const suggestedBid = playerValue; // Direct purchase at market value

    Swal.fire({
        title: `Purchase ${playerName}?`,
        html: `
            <div class="text-left space-y-4">
                <div class="bg-gray-50 p-4 rounded-lg">
                    <h4 class="font-semibold text-gray-900 mb-2">Player Details:</h4>
                    <div class="space-y-1 text-sm">
                        <div class="flex justify-between">
                            <span class="text-gray-600">Market Price:</span>
                            <span class="font-medium text-green-600">${formatMarketValue(playerValue)}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-600">Your Budget:</span>
                            <span class="font-medium text-blue-600">${formatMarketValue(userBudget)}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-600">Remaining Budget:</span>
                            <span class="font-medium ${userBudget - playerValue >= 0 ? 'text-green-600' : 'text-red-600'}">${formatMarketValue(userBudget - playerValue)}</span>
                        </div>
                    </div>
                </div>
                
                <div class="bg-blue-50 p-4 rounded-lg">
                    <p class="text-sm text-blue-800">
                        <i data-lucide="info" class="w-4 h-4 inline mr-1"></i>
                        This player will be purchased directly from the transfer market at the listed price.
                    </p>
                </div>
            </div>
        `,
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#2563eb',
        cancelButtonColor: '#6b7280',
        confirmButtonText: '<i data-lucide="shopping-cart" class="w-4 h-4 inline mr-1"></i> Purchase Player',
        cancelButtonText: 'Cancel',
        customClass: {
            popup: 'swal-wide'
        },
        didOpen: () => {
            lucide.createIcons();
        }
    }).then((result) => {
        if (result.isConfirmed) {
            purchasePlayer(playerIndex, playerName, playerData, playerValue);
        }
    });
}

// Purchase player directly from market
function purchasePlayer(playerIndex, playerName, playerData, purchaseAmount) {
    Swal.fire({
        title: 'Processing Purchase...',
        text: 'Please wait while we process your purchase',
        allowOutsideClick: false,
        allowEscapeKey: false,
        showConfirmButton: false,
        didOpen: () => {
            Swal.showLoading();
        }
    });

    const requestData = {
        player_index: parseInt(playerIndex),
        player_uuid: playerData.uuid,
        player_data: playerData,
        purchase_amount: parseInt(purchaseAmount)
    };

    fetch('api/purchase_player_api.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify(requestData)
    })
        .then(response => {
            console.log('Response status:', response.status);
            return response.json().then(data => {
                if (!response.ok) {
                    // Server returned an error status, but we have the error message
                    throw new Error(data.message || `HTTP error! status: ${response.status}`);
                }
                return data;
            });
        })
        .then(data => {
            console.log('Server response:', data);
            if (data.success) {
                Swal.fire({
                    icon: 'success',
                    title: 'Player Purchased!',
                    text: `${playerName} has been added to your team for ${formatMarketValue(purchaseAmount)}.`,
                    confirmButtonColor: '#10b981'
                }).then(() => {
                    // Refresh page to update available players
                    window.location.reload();
                });
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'Purchase Failed',
                    text: data.message || 'Unknown error occurred',
                    confirmButtonColor: '#ef4444'
                });
            }
        })
        .catch(error => {
            console.error('Error purchasing player:', error);
            Swal.fire({
                icon: 'error',
                title: 'Purchase Failed',
                text: error.message || 'Failed to purchase player. Please try again.',
                confirmButtonColor: '#ef4444'
            });
        });
}

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
                    <div class="w-24 h-24 bg-white bg-opacity-20 rounded-full flex items-center justify-center">
                        <i data-lucide="user" class="w-12 h-12"></i>
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
                    <div class="flex justify-between">
                        <span class="text-gray-600">Contract:</span>
                        <span class="font-medium">${contractRemaining} match${contractRemaining !== 1 ? 'es' : ''} remaining</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-600">Matches Played:</span>
                        <span class="font-medium">${player.matches_played || 0}</span>
                    </div>
                    ${contractRemaining <= 8 ? `
                    <div class="mt-3 p-3 rounded-lg border bg-orange-50 border-orange-200">
                        <div class="flex items-center justify-between">
                            <div>
                                <div class="text-sm font-medium text-orange-600">
                                    <i data-lucide="alert-triangle" class="w-4 h-4 inline mr-1"></i>
                                    Contract ${contractRemaining <= 3 ? 'Expiring Soon' : 'Renewal Needed'}
                                </div>
                                <div class="text-xs text-gray-600 mt-1">Contract renewal recommended</div>
                            </div>
                        </div>
                    </div>
                    ` : ''}
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

// Player Management Functions
function assignToTeam(inventoryId, playerData) {
    Swal.fire({
        title: `Assign ${playerData.name} to Team?`,
        text: 'This will move the player from your inventory to your team substitutes.',
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#10b981',
        cancelButtonColor: '#6b7280',
        confirmButtonText: 'Assign to Team',
        cancelButtonText: 'Cancel'
    }).then((result) => {
        if (result.isConfirmed) {
            manageInventoryPlayer('assign', inventoryId, playerData);
        }
    });
}

function sellPlayer(inventoryId, playerData) {
    const sellPrice = Math.round((playerData.value || 0) * 0.7); // 70% of market value

    Swal.fire({
        title: `Sell ${playerData.name}?`,
        html: `
            <div class="text-left space-y-4">
                <div class="bg-gray-50 p-4 rounded-lg">
                    <div class="space-y-1 text-sm">
                        <div class="flex justify-between">
                            <span class="text-gray-600">Market Value:</span>
                            <span class="font-medium text-green-600">${formatMarketValue(playerData.value || 0)}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-600">Sell Price (70%):</span>
                            <span class="font-medium text-blue-600">${formatMarketValue(sellPrice)}</span>
                        </div>
                    </div>
                </div>
                <p class="text-sm text-gray-600">The player will be removed from your inventory and you'll receive the sell price.</p>
            </div>
        `,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#f59e0b',
        cancelButtonColor: '#6b7280',
        confirmButtonText: 'Sell Player',
        cancelButtonText: 'Keep Player'
    }).then((result) => {
        if (result.isConfirmed) {
            manageInventoryPlayer('sell', inventoryId, playerData, sellPrice);
        }
    });
}

function deletePlayer(inventoryId, playerName) {
    Swal.fire({
        title: `Release ${playerName}?`,
        text: 'This will permanently remove the player from your inventory without compensation.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ef4444',
        cancelButtonColor: '#6b7280',
        confirmButtonText: 'Release Player',
        cancelButtonText: 'Keep Player'
    }).then((result) => {
        if (result.isConfirmed) {
            manageInventoryPlayer('delete', inventoryId, { name: playerName });
        }
    });
}

function manageInventoryPlayer(action, inventoryId, playerData, sellPrice = 0) {
    Swal.fire({
        title: 'Processing...',
        text: 'Please wait while we process your request',
        allowOutsideClick: false,
        allowEscapeKey: false,
        showConfirmButton: false,
        didOpen: () => {
            Swal.showLoading();
        }
    });

    const requestData = {
        action: action,
        inventory_id: inventoryId,
        player_data: playerData,
        sell_price: sellPrice
    };

    fetch('api/manage_inventory_api.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify(requestData)
    })
        .then(response => {
            return response.json().then(data => {
                if (!response.ok) {
                    throw new Error(data.message || `HTTP error! status: ${response.status}`);
                }
                return data;
            });
        })
        .then(data => {
            if (data.success) {
                let title = 'Success!';
                let message = '';

                switch (action) {
                    case 'assign':
                        title = 'Player Assigned!';
                        message = `${playerData.name} has been added to your team substitutes.`;
                        break;
                    case 'sell':
                        title = 'Player Sold!';
                        message = `${playerData.name} has been sold for ${formatMarketValue(sellPrice)}.`;
                        break;
                    case 'delete':
                        title = 'Player Released!';
                        message = `${playerData.name} has been released from your inventory.`;
                        break;
                }

                Swal.fire({
                    icon: 'success',
                    title: title,
                    text: message,
                    confirmButtonColor: '#10b981'
                }).then(() => {
                    window.location.reload();
                });
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'Action Failed',
                    text: data.message || 'Unknown error occurred',
                    confirmButtonColor: '#ef4444'
                });
            }
        })
        .catch(error => {
            console.error('Error managing player:', error);
            Swal.fire({
                icon: 'error',
                title: 'Action Failed',
                text: error.message || 'Failed to process request. Please try again.',
                confirmButtonColor: '#ef4444'
            });
        });
}

// Initialize event listeners when DOM is loaded
document.addEventListener('DOMContentLoaded', function () {
    // Event listeners for tabs
    document.getElementById('playersTab')?.addEventListener('click', () => {
        switchTab('players');
        currentPage = 1;
        renderPlayers();
    });
    document.getElementById('myPlayersTab')?.addEventListener('click', () => switchTab('myPlayers'));

    // Event listeners for filters
    document.getElementById('playerSearch')?.addEventListener('input', filterPlayers);
    document.getElementById('categoryFilter')?.addEventListener('change', filterPlayers);
    document.getElementById('positionFilter')?.addEventListener('change', filterPlayers);
    document.getElementById('priceFilter')?.addEventListener('change', filterPlayers);

    // Event listeners for pagination
    document.getElementById('prevPage')?.addEventListener('click', previousPage);
    document.getElementById('nextPage')?.addEventListener('click', nextPage);

    // Close player info modal
    document.getElementById('closePlayerInfoModal')?.addEventListener('click', function () {
        document.getElementById('playerInfoModal').classList.add('hidden');
    });

    document.getElementById('playerInfoModal')?.addEventListener('click', function (e) {
        if (e.target === this) {
            this.classList.add('hidden');
        }
    });
});