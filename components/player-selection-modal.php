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
                <button id="addCustomPlayer" class="bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700">
                    Add
                </button>
            </div>
        </div>

        <div class="border-t pt-4">
            <input type="text" id="playerSearch" placeholder="Search player..." class="w-full px-3 py-2
                border rounded-lg mb-4 focus:outline-none focus:ring-2 focus:ring-blue-500">
            <div id="modalPlayerList" class="space-y-2 max-h-64 overflow-y-auto"></div>
        </div>
    </div>
</div>