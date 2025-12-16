<!-- League Validation Errors -->
<?php if (isset($_GET['league_validation_failed']) && isset($_SESSION['league_validation_errors'])): ?>
    <?php
    $validation_errors = $_SESSION['league_validation_errors'];
    unset($_SESSION['league_validation_errors']); // Clear after displaying
    ?>
    <div class="mb-6 bg-red-50 border border-red-200 rounded-lg p-6">
        <div class="flex items-start gap-3">
            <i data-lucide="alert-triangle" class="w-6 h-6 text-red-600 mt-1"></i>
            <div class="flex-1">
                <h3 class="text-lg font-semibold text-red-800 mb-2">
                    Club Not Eligible for League
                </h3>
                <p class="text-red-700 mb-4">
                    Your club doesn't meet the minimum requirements to participate in the league.
                    Please address the following issues:
                </p>
                <ul class="list-disc list-inside space-y-1 text-red-700 mb-4">
                    <?php foreach ($validation_errors as $error): ?>
                        <li><?php echo htmlspecialchars($error); ?></li>
                    <?php endforeach; ?>
                </ul>
                <div class="flex gap-3">
                    <a href="transfer.php"
                        class="bg-red-600 text-white px-4 py-2 rounded-lg hover:bg-red-700 flex items-center gap-2">
                        <i data-lucide="users" class="w-4 h-4"></i>
                        Buy Players
                    </a>
                    <button onclick="window.location.reload()"
                        class="bg-gray-600 text-white px-4 py-2 rounded-lg hover:bg-gray-700 flex items-center gap-2">
                        <i data-lucide="refresh-cw" class="w-4 h-4"></i>
                        Check Again
                    </button>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>