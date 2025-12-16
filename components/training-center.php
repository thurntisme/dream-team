<?php
// Training Center
if ($total_players >= 11):
    ?>
    <!-- Training Center Section -->
    <div class="mb-6">
        <div class="bg-white rounded-lg p-6">
            <div class="flex items-center justify-between mb-4 gap-2">
                <h2 class="text-xl font-bold text-gray-900 flex items-center gap-2">
                    <i data-lucide="dumbbell" class="w-6 h-6 text-green-600"></i>
                    Training Center
                </h2>
                <button id="trainAllBtn"
                    class="bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700 flex items-center gap-2 ml-auto">
                    <i data-lucide="play" class="w-4 h-4"></i>
                    Train All Players
                </button>
                <button id="dailyRecoveryBtn"
                    class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 flex items-center gap-2">
                    <i data-lucide="heart" class="w-4 h-4"></i>
                    Daily Recovery
                </button>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
                <div class="bg-green-50 border border-green-200 rounded-lg p-4 text-center">
                    <div class="text-2xl font-bold text-green-700">€2M</div>
                    <div class="text-sm text-green-600">Training Cost</div>
                </div>
                <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 text-center">
                    <div class="text-2xl font-bold text-blue-700">+5-15</div>
                    <div class="text-sm text-blue-600">Fitness Boost</div>
                </div>
                <div class="bg-purple-50 border border-purple-200 rounded-lg p-4 text-center">
                    <div class="text-2xl font-bold text-purple-700">24h</div>
                    <div class="text-sm text-purple-600">Cooldown</div>
                </div>
            </div>

            <div class="bg-gray-50 rounded-lg p-4">
                <h3 class="font-semibold text-gray-900 mb-2">Training Benefits:</h3>
                <ul class="text-sm text-gray-600 space-y-1">
                    <li>• Improves player fitness by 5-15 points</li>
                    <li>• Helps maintain player form</li>
                    <li>• Reduces injury risk for low-fitness players</li>
                    <li>• Can only be used once per day</li>
                </ul>
            </div>
        </div>
    </div>
<?php else: ?>
    <!-- Training Center Locked Message -->
    <div class="mb-6">
        <div class="bg-white rounded-lg p-6 border-l-4 border-yellow-400">
            <div class="flex items-center gap-3">
                <div class="flex-shrink-0">
                    <i data-lucide="lock" class="w-8 h-8 text-yellow-600"></i>
                </div>
                <div class="flex-1">
                    <h3 class="text-lg font-semibold text-gray-900 mb-1">Training Center Locked</h3>
                    <p class="text-gray-600 mb-3">
                        You need at least 11 players to access the Training Center.
                        Currently you have <?php echo $total_players; ?> players
                        (need <?php echo 11 - $total_players; ?> more).
                    </p>
                    <div class="flex gap-3">
                        <a href="transfer.php"
                            class="inline-flex items-center gap-2 bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 text-sm">
                            <i data-lucide="users" class="w-4 h-4"></i>
                            Buy Players
                        </a>
                        <a href="scouting.php"
                            class="inline-flex items-center gap-2 bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700 text-sm">
                            <i data-lucide="search" class="w-4 h-4"></i>
                            Scout Players
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>