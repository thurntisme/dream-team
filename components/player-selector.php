<!-- Player Selector -->
<div class="flex flex-col gap-4">
    <!-- Line-up Section -->
    <div class="bg-white rounded-lg shadow p-4">
        <h2 class="text-xl font-bold mb-2 flex items-center gap-2">
            <i data-lucide="users" class="w-5 h-5"></i>
            Line-up
        </h2>
        <p class="text-xs text-gray-500 mb-4">Your starting XI players • Click on field positions to add or change players</p>
        <div id="playerList" class="space-y-2 max-h-80 overflow-y-auto"></div>
    </div>

    <!-- Substitutes Section -->
    <div class="bg-white rounded-lg shadow p-4">
        <h2 class="text-xl font-bold mb-2 flex items-center gap-2">
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