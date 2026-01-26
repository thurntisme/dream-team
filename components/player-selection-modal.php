<!-- Player Selection Modal -->
<div id="playerModal" 
     class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50"
     role="dialog"
     aria-modal="true"
     aria-hidden="true"
     aria-labelledby="modalTitle"
     tabindex="-1">
    <div class="bg-white rounded-lg p-6 w-full max-w-md" role="document">
        <div class="flex justify-between items-center mb-4">
            <h3 id="modalTitle" class="text-xl font-bold">Select Player</h3>
            <button id="closeModal" 
                    class="text-gray-500 hover:text-gray-700"
                    aria-label="Close modal">
                <i data-lucide="x" class="w-6 h-6"></i>
            </button>
        </div>

        <div>
            <input type="text" 
                   id="playerSearch" 
                   placeholder="Search player..." 
                   aria-label="Search for players"
                   class="w-full px-3 py-2 border rounded-lg mb-4 focus:outline-none focus:ring-2 focus:ring-blue-500">
            <div id="modalPlayerList" 
                 class="space-y-2 max-h-64 overflow-y-auto"
                 role="list"
                 aria-label="Available players"></div>
        </div>
    </div>
</div>