<!-- Player Recommendations Modal -->
<div id="recommendationsModal" class="fixed inset-0 bg-black bg-opacity-50 z-50 hidden">
    <div class="flex items-center justify-center min-h-screen p-4">
        <div class="bg-white rounded-lg shadow-xl max-w-4xl w-full max-h-[90vh] overflow-hidden">
            <!-- Modal Header -->
            <div class="bg-gradient-to-r from-blue-600 to-blue-800 text-white p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <h2 class="text-2xl font-bold flex items-center gap-2">
                            <i data-lucide="brain" class="w-6 h-6"></i>
                            AI Player Recommendations
                            <span class="bg-yellow-400 text-yellow-900 text-sm px-2 py-1 rounded-full font-bold ml-2">PREMIUM</span>
                        </h2>
                        <p class="text-blue-100 mt-1">Advanced AI analysis of your squad with personalized suggestions</p>
                    </div>
                    <button id="closeRecommendationsModal" class="text-white hover:text-blue-200 transition-colors">
                        <i data-lucide="x" class="w-6 h-6"></i>
                    </button>
                </div>
            </div>

            <!-- Modal Content -->
            <div class="p-6 overflow-y-auto max-h-[calc(90vh-180px)]">
                <!-- Loading State -->
                <div id="recommendationsLoading" class="text-center py-8">
                    <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-600 mx-auto mb-4"></div>
                    <p class="text-gray-600">Initializing AI analysis...</p>
                </div>

                <!-- Team Analysis Section -->
                <div id="teamAnalysisSection" class="hidden mb-6">
                    <h3 class="text-lg font-semibold mb-4 flex items-center gap-2">
                        <i data-lucide="bar-chart-3" class="w-5 h-5 text-green-600"></i>
                        Team Analysis
                    </h3>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
                        <div class="bg-blue-50 p-4 rounded-lg border border-blue-200">
                            <div class="text-2xl font-bold text-blue-600" id="totalPlayersCount">0</div>
                            <div class="text-sm text-blue-700">Players in Squad</div>
                        </div>
                        <div class="bg-green-50 p-4 rounded-lg border border-green-200">
                            <div class="text-2xl font-bold text-green-600" id="avgTeamRating">0</div>
                            <div class="text-sm text-green-700">Average Rating</div>
                        </div>
                        <div class="bg-purple-50 p-4 rounded-lg border border-purple-200">
                            <div class="text-2xl font-bold text-purple-600" id="availableBudget">€0M</div>
                            <div class="text-sm text-purple-700">Available Budget</div>
                        </div>
                    </div>

                    <!-- Issues Found -->
                    <div id="issuesFound" class="hidden">
                        <h4 class="font-semibold text-red-600 mb-2 flex items-center gap-2">
                            <i data-lucide="alert-triangle" class="w-4 h-4"></i>
                            Issues Found
                        </h4>
                        <div class="space-y-2 mb-4">
                            <div id="emptyPositionsList" class="hidden">
                                <div class="bg-red-50 p-3 rounded border border-red-200">
                                    <div class="text-sm font-medium text-red-800">Empty Positions:</div>
                                    <div id="emptyPositionsText" class="text-sm text-red-700"></div>
                                </div>
                            </div>
                            <div id="weakPositionsList" class="hidden">
                                <div class="bg-orange-50 p-3 rounded border border-orange-200">
                                    <div class="text-sm font-medium text-orange-800">Weak Positions (Below 75 Rating):</div>
                                    <div id="weakPositionsText" class="text-sm text-orange-700"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Recommendations Section -->
                <div id="recommendationsSection" class="hidden">
                    <h3 class="text-lg font-semibold mb-4 flex items-center gap-2">
                        <i data-lucide="users" class="w-5 h-5 text-blue-600"></i>
                        Recommended Players
                    </h3>
                    
                    <!-- Filter Options -->
                    <div class="mb-4 flex flex-wrap gap-2">
                        <button class="recommendation-filter active px-3 py-1 rounded-full text-sm font-medium bg-blue-600 text-white" data-filter="all">
                            All Recommendations
                        </button>
                        <button class="recommendation-filter px-3 py-1 rounded-full text-sm font-medium bg-gray-200 text-gray-700 hover:bg-gray-300" data-filter="high">
                            High Priority
                        </button>
                        <button class="recommendation-filter px-3 py-1 rounded-full text-sm font-medium bg-gray-200 text-gray-700 hover:bg-gray-300" data-filter="medium">
                            Medium Priority
                        </button>
                        <button class="recommendation-filter px-3 py-1 rounded-full text-sm font-medium bg-gray-200 text-gray-700 hover:bg-gray-300" data-filter="low">
                            Squad Depth
                        </button>
                    </div>

                    <!-- Recommendations List -->
                    <div id="recommendationsList" class="space-y-3">
                        <!-- Recommendations will be populated here -->
                    </div>

                    <!-- No Recommendations -->
                    <div id="noRecommendations" class="hidden text-center py-8">
                        <div class="text-gray-400 mb-4">
                            <i data-lucide="check-circle" class="w-16 h-16 mx-auto"></i>
                        </div>
                        <h4 class="text-lg font-semibold text-gray-600 mb-2">Great Squad!</h4>
                        <p class="text-gray-500">Your team looks well-balanced. No urgent recommendations at this time.</p>
                    </div>
                </div>

                <!-- Error State -->
                <div id="recommendationsError" class="hidden text-center py-8">
                    <div class="text-red-400 mb-4">
                        <i data-lucide="alert-circle" class="w-16 h-16 mx-auto"></i>
                    </div>
                    <h4 class="text-lg font-semibold text-red-600 mb-2">Error Loading Recommendations</h4>
                    <p class="text-gray-500 mb-4">Unable to analyze your team. Please try again.</p>
                    <button id="retryRecommendations" class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700">
                        Try Again
                    </button>
                </div>
            </div>

            <!-- Modal Footer -->
            <div class="bg-gray-50 px-6 py-4 border-t">
                <div class="flex justify-between items-center">
                    <div class="text-sm text-gray-500">
                        <i data-lucide="info" class="w-4 h-4 inline mr-1"></i>
                        Premium AI service • €2M per analysis • Recommendations based on formation, budget & team needs
                    </div>
                    <button id="closeRecommendationsModalFooter" class="px-4 py-2 bg-gray-600 text-white rounded hover:bg-gray-700">
                        Close
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>