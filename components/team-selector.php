<!-- Team Selector -->
<div class="gap-4 flex flex-col">
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
        <div id="playerList" class="space-y-2 max-h-80 overflow-y-auto"></div>
    </div>

    <!-- Substitutes Section -->
    <div class="bg-white rounded-lg shadow p-4">
        <h2 class="text-xl font-bold mb-4 flex items-center gap-2">
            <i data-lucide="users" class="w-5 h-5"></i>
            Substitutes
        </h2>
        <p class="text-xs text-gray-500 mb-4">Backup players for your squad • Max
            <?php echo $max_players - 11; ?>
            substitutes
        </p>
        <div id="substitutesList" class="space-y-2 max-h-60 overflow-y-auto"></div>
    </div>
</div>