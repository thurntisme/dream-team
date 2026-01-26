<!-- Team Selector -->
<div class="flex flex-col gap-4">
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
        <div class="flex items-center justify-between mt-6 mb-2">
            <h2 class="text-xl font-bold">Your Players</h2>
            <button id="recommendPlayersBtn" 
                class="bg-gradient-to-r from-blue-600 to-blue-700 text-white px-4 py-2 rounded-lg hover:from-blue-700 hover:to-blue-800 flex items-center gap-2 text-sm font-medium transition-all duration-200 shadow-md hover:shadow-lg relative">
                <i data-lucide="brain" class="w-4 h-4"></i>
                AI Recommendations
                <span class="absolute -top-1 -right-1 bg-yellow-400 text-yellow-900 text-xs px-1.5 py-0.5 rounded-full font-bold">€2M</span>
            </button>
        </div>
        <p class=" text-xs text-gray-500 mb-4">Click to select • <i data-lucide="user-plus"
                class="w-3 h-3 inline"></i> Choose • <i data-lucide="arrow-left-right"
                class="w-3 h-3 inline"></i>
            Switch • <i data-lucide="trash-2" class="w-3 h-3 inline"></i> Remove
        </p>
        <div id="teamValueSummary" class="mb-4 p-3 bg-gray-50 rounded-lg border">
            <div class="flex justify-between items-center mb-2">
                <div class="text-sm text-gray-600">Budget</div>
                <div id="remainingBudget" class="text-sm font-bold text-blue-600">€200.0M</div>
            </div>
            <div class="flex justify-between items-center mb-2">
                <div class="text-sm text-gray-600">Team Value</div>
                <div id="totalTeamValue" class="text-sm font-bold text-green-600">€0.0M</div>
            </div>
            <div class="w-full bg-gray-200 rounded-full h-2 mb-2">
                <div id="budgetBar" class="bg-blue-600 h-2 rounded-full transition-all duration-300"
                    style="width: 0%"></div>
            </div>
            <div id="playerCount" class="text-xs text-gray-500">0/11 players selected</div>
        </div>
    </div>
    <!-- Selected Player -->
    <div id="selectedPlayerInfo" class="bg-white rounded-lg shadow p-4 hidden">
        <h2 class="text-xl font-bold mb-4">Selected Player</h2>
        <div id="selectedPlayerContent" class="space-y-3">
            <!-- Player avatar and basic info -->
            <div class="flex items-center gap-3 pb-3 border-b">
                <div id="selectedPlayerAvatar" class="w-16 h-16 flex-shrink-0">
                    <!-- Avatar will be inserted here -->
                </div>
                <div class="flex-1">
                    <div id="selectedPlayerName" class="font-bold text-lg text-gray-900"></div>
                    <div id="selectedPlayerPosition" class="text-sm text-gray-600"></div>
                </div>
                <button id="playerInfoBtn" class="p-2 bg-purple-600 hover:bg-purple-700 text-white rounded-lg transition-colors flex-shrink-0" title="Player Info">
                    <i data-lucide="info" class="w-5 h-5"></i>
                </button>
            </div>
            
            <!-- Player stats -->
            <div class="grid grid-cols-2 gap-3">
                <div class="bg-gray-50 rounded-lg p-3">
                    <div class="text-xs text-gray-500 mb-1">Rating</div>
                    <div id="selectedPlayerRating" class="text-lg font-bold text-yellow-600 flex items-center gap-1">
                        <i data-lucide="star" class="w-4 h-4"></i>
                        <span>--</span>
                    </div>
                </div>
                <div class="bg-gray-50 rounded-lg p-3">
                    <div class="text-xs text-gray-500 mb-1">Value</div>
                    <div id="selectedPlayerValue" class="text-lg font-bold text-green-600">€0.0M</div>
                </div>
                <div class="bg-gray-50 rounded-lg p-3">
                    <div class="text-xs text-gray-500 mb-1">Level</div>
                    <div id="selectedPlayerLevel" class="text-lg font-bold text-blue-600">1</div>
                </div>
                <div class="bg-gray-50 rounded-lg p-3">
                    <div class="text-xs text-gray-500 mb-1">Card Level</div>
                    <div id="selectedPlayerCardLevel" class="text-lg font-bold text-purple-600">1</div>
                </div>
                <div class="bg-gray-50 rounded-lg p-3">
                    <div class="text-xs text-gray-500 mb-1">Fitness</div>
                    <div id="selectedPlayerFitness" class="space-y-1">
                        <div class="text-sm font-bold text-gray-900">100%</div>
                        <div class="w-full bg-gray-200 rounded-full h-1.5">
                            <div class="bg-green-500 h-full rounded-full transition-all duration-300" style="width: 100%"></div>
                        </div>
                    </div>
                </div>
                <div class="bg-gray-50 rounded-lg p-3">
                    <div class="text-xs text-gray-500 mb-1">Form</div>
                    <div id="selectedPlayerForm" class="flex items-center gap-1">
                        <span class="text-sm font-bold text-gray-900">7.0</span>
                    </div>
                </div>
                <div class="bg-gray-50 rounded-lg p-3">
                    <div class="text-xs text-gray-500 mb-1">Nationality</div>
                    <div id="selectedPlayerNationality" class="text-sm font-medium text-gray-900">--</div>
                </div>
                <div class="bg-gray-50 rounded-lg p-3">
                    <div class="text-xs text-gray-500 mb-1">Contract</div>
                    <div id="selectedPlayerContract" class="text-sm font-bold text-gray-900">-- matches</div>
                </div>
                <div class="bg-gray-50 rounded-lg p-3">
                    <div class="text-xs text-gray-500 mb-1">Salary</div>
                    <div id="selectedPlayerSalary" class="text-sm font-bold text-blue-600">€0.0M/week</div>
                </div>
            </div>
            
            <!-- Action buttons -->
            <div class="flex gap-2 pt-2">
                <button id="changePlayerBtn" class="flex-1 bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg flex items-center justify-center gap-2 transition-colors">
                    <i data-lucide="user-plus" class="w-4 h-4"></i>
                    Change
                </button>
                <button id="removePlayerBtn" class="bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded-lg flex items-center justify-center transition-colors">
                    <i data-lucide="trash-2" class="w-4 h-4"></i>
                </button>
            </div>
            
            <!-- Renew Contract Button -->
            <button id="renewContractBtn" class="w-full bg-gradient-to-r from-green-600 to-green-700 hover:from-green-700 hover:to-green-800 text-white px-4 py-2 rounded-lg flex items-center justify-center gap-2 transition-all duration-200 shadow-md hover:shadow-lg">
                <i data-lucide="file-signature" class="w-4 h-4"></i>
                Renew Contract
            </button>
        </div>
    </div>
</div>