<?php
session_start();

require_once 'config/config.php';
require_once 'config/constants.php';
require_once 'partials/layout.php';

// Require user to be logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

try {
    $db = getDbConnection();

    // Create feedback table if it doesn't exist
    $db->exec('CREATE TABLE IF NOT EXISTS user_feedback (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER NOT NULL,
        category TEXT NOT NULL,
        subject TEXT NOT NULL,
        message TEXT NOT NULL,
        status TEXT DEFAULT "pending",
        reward_amount INTEGER DEFAULT 140,
        reward_paid BOOLEAN DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        admin_response TEXT,
        FOREIGN KEY (user_id) REFERENCES users(id)
    )');

    // Get user data
    $stmt = $db->prepare('SELECT name, email, club_name, budget FROM users WHERE id = :id');
    $stmt->bindValue(':id', $_SESSION['user_id'], SQLITE3_INTEGER);
    $result = $stmt->execute();
    $user = $result->fetchArray(SQLITE3_ASSOC);
    $user_budget = $user['budget'] ?? DEFAULT_BUDGET;

    // Handle form submission
    $message = '';
    $message_type = 'info';

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_feedback'])) {
        $category = trim($_POST['category'] ?? '');
        $subject = trim($_POST['subject'] ?? '');
        $feedback_message = trim($_POST['message'] ?? '');

        if (empty($category) || empty($subject) || empty($feedback_message)) {
            $message = 'Please fill in all required fields.';
            $message_type = 'error';
        } elseif (strlen($feedback_message) < 20) {
            $message = 'Please provide more detailed feedback (minimum 20 characters).';
            $message_type = 'error';
        } else {
            // Insert feedback
            $stmt = $db->prepare('INSERT INTO user_feedback (user_id, category, subject, message) VALUES (:user_id, :category, :subject, :message)');
            $stmt->bindValue(':user_id', $_SESSION['user_id'], SQLITE3_INTEGER);
            $stmt->bindValue(':category', $category, SQLITE3_TEXT);
            $stmt->bindValue(':subject', $subject, SQLITE3_TEXT);
            $stmt->bindValue(':message', $feedback_message, SQLITE3_TEXT);

            if ($stmt->execute()) {
                // Award immediate reward for submitting feedback
                $reward_amount = 140; // Base reward for submission
                $new_budget = $user_budget + $reward_amount;

                $stmt = $db->prepare('UPDATE users SET budget = :budget WHERE id = :user_id');
                $stmt->bindValue(':budget', $new_budget, SQLITE3_INTEGER);
                $stmt->bindValue(':user_id', $_SESSION['user_id'], SQLITE3_INTEGER);
                $stmt->execute();

                // Mark reward as paid
                $stmt = $db->prepare('UPDATE user_feedback SET reward_paid = 1 WHERE user_id = :user_id AND id = (SELECT MAX(id) FROM user_feedback WHERE user_id = :user_id)');
                $stmt->bindValue(':user_id', $_SESSION['user_id'], SQLITE3_INTEGER);
                $stmt->execute();

                $user_budget = $new_budget;
                $message = "Thank you for your feedback! You've earned €140 and may earn an additional €1,260 if your feedback is approved.";
                $message_type = 'success';
            } else {
                $message = 'Failed to submit feedback. Please try again.';
                $message_type = 'error';
            }
        }
    }

    // Get user's feedback history
    $stmt = $db->prepare('SELECT * FROM user_feedback WHERE user_id = :user_id ORDER BY created_at DESC LIMIT 10');
    $stmt->bindValue(':user_id', $_SESSION['user_id'], SQLITE3_INTEGER);
    $result = $stmt->execute();

    $feedback_history = [];
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $feedback_history[] = $row;
    }

    $db->close();
} catch (Exception $e) {
    header('Location: install.php');
    exit;
}

// Feedback categories
$categories = [
    'bug_report' => 'Bug Report',
    'feature_request' => 'Feature Request',
    'gameplay' => 'Gameplay Feedback',
    'ui_ux' => 'User Interface/Experience',
    'performance' => 'Performance Issues',
    'suggestion' => 'General Suggestion',
    'other' => 'Other'
];

// Start content capture
startContent();
?>

<div class="container mx-auto p-4">
    <!-- Messages -->
    <?php if ($message): ?>
        <div
            class="mb-6 p-4 rounded-lg border <?php echo $message_type === 'success' ? 'bg-green-50 border-green-200 text-green-800' : 'bg-red-50 border-red-200 text-red-800'; ?>">
            <div class="flex items-center gap-2">
                <i data-lucide="<?php echo $message_type === 'success' ? 'check-circle' : 'alert-circle'; ?>"
                    class="w-5 h-5"></i>
                <?php echo htmlspecialchars($message); ?>
            </div>
        </div>
    <?php endif; ?>

    <!-- Header -->
    <div class="mb-8">
        <div class="flex justify-between items-center mb-4">
            <div>
                <h1 class="text-3xl font-bold text-gray-900 mb-2">Feedback & Suggestions</h1>
                <p class="text-gray-600">Help us improve Dream Team and earn rewards!</p>
            </div>
            <div class="bg-green-100 text-green-800 px-4 py-2 rounded-lg">
                <div class="text-sm text-green-600">Your Budget</div>
                <div class="text-lg font-bold"><?php echo formatMarketValue($user_budget); ?></div>
            </div>
        </div>

        <!-- Reward Information -->
        <div class="bg-gradient-to-r from-green-50 to-blue-50 rounded-lg p-6 border border-green-200 mb-6">
            <div class="flex items-center gap-4 mb-4">
                <div class="w-12 h-12 bg-green-600 rounded-full flex items-center justify-center">
                    <i data-lucide="coins" class="w-6 h-6 text-white"></i>
                </div>
                <div>
                    <h2 class="text-xl font-bold text-gray-900">Earn Rewards for Your Feedback!</h2>
                    <p class="text-gray-600">Your input helps us make Dream Team better for everyone.</p>
                </div>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="bg-white p-4 rounded-lg border border-green-200">
                    <div class="flex items-center gap-2 mb-2">
                        <i data-lucide="send" class="w-5 h-5 text-blue-600"></i>
                        <span class="font-semibold text-gray-900">Submit Feedback</span>
                    </div>
                    <div class="text-2xl font-bold text-blue-600 mb-1">€140</div>
                    <p class="text-sm text-gray-600">Instant reward for every feedback submission</p>
                </div>
                <div class="bg-white p-4 rounded-lg border border-green-200">
                    <div class="flex items-center gap-2 mb-2">
                        <i data-lucide="check-circle" class="w-5 h-5 text-green-600"></i>
                        <span class="font-semibold text-gray-900">Approved Feedback</span>
                    </div>
                    <div class="text-2xl font-bold text-green-600 mb-1">€1,400</div>
                    <p class="text-sm text-gray-600">Total reward when your feedback is approved</p>
                </div>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        <!-- Feedback Form -->
        <div class="lg:col-span-2">
            <div class="bg-white rounded-lg shadow p-6">
                <h2 class="text-xl font-bold text-gray-900 mb-6 flex items-center gap-2">
                    <i data-lucide="message-circle" class="w-5 h-5"></i>
                    Submit Your Feedback
                </h2>

                <form method="POST" class="space-y-6">
                    <div>
                        <label for="category" class="block text-sm font-medium text-gray-700 mb-2">
                            Category <span class="text-red-500">*</span>
                        </label>
                        <select name="category" id="category" required
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <option value="">Select a category...</option>
                            <?php foreach ($categories as $value => $label): ?>
                                <option value="<?php echo $value; ?>"><?php echo $label; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label for="subject" class="block text-sm font-medium text-gray-700 mb-2">
                            Subject <span class="text-red-500">*</span>
                        </label>
                        <input type="text" name="subject" id="subject" required maxlength="200"
                            placeholder="Brief description of your feedback..."
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>

                    <div>
                        <label for="message" class="block text-sm font-medium text-gray-700 mb-2">
                            Detailed Feedback <span class="text-red-500">*</span>
                        </label>
                        <textarea name="message" id="message" required rows="6" minlength="20"
                            placeholder="Please provide detailed feedback. The more specific you are, the better we can help improve the game..."
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"></textarea>
                        <div class="text-sm text-gray-500 mt-1">Minimum 20 characters required</div>
                    </div>

                    <div class="bg-blue-50 p-4 rounded-lg border border-blue-200">
                        <div class="flex items-start gap-2">
                            <i data-lucide="info" class="w-5 h-5 text-blue-600 mt-0.5"></i>
                            <div class="text-sm text-blue-800">
                                <p class="font-medium mb-1">Tips for better feedback:</p>
                                <ul class="list-disc list-inside space-y-1 text-blue-700">
                                    <li>Be specific about the issue or suggestion</li>
                                    <li>Include steps to reproduce bugs</li>
                                    <li>Explain how the change would improve your experience</li>
                                    <li>Provide examples when possible</li>
                                </ul>
                            </div>
                        </div>
                    </div>

                    <button type="submit" name="submit_feedback"
                        class="w-full bg-green-600 text-white px-6 py-3 rounded-lg hover:bg-green-700 transition-colors font-semibold flex items-center justify-center gap-2">
                        <i data-lucide="send" class="w-5 h-5"></i>
                        Submit Feedback & Earn €140
                    </button>
                </form>
            </div>
        </div>

        <!-- Feedback History & Info -->
        <div class="space-y-6">
            <!-- Quick Stats -->
            <div class="bg-white rounded-lg shadow p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-4 flex items-center gap-2">
                    <i data-lucide="bar-chart" class="w-5 h-5"></i>
                    Your Feedback Stats
                </h3>
                <div class="space-y-3">
                    <div class="flex justify-between items-center">
                        <span class="text-gray-600">Total Submitted:</span>
                        <span class="font-semibold"><?php echo count($feedback_history); ?></span>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="text-gray-600">Approved:</span>
                        <span class="font-semibold text-green-600">
                            <?php echo count(array_filter($feedback_history, fn($f) => $f['status'] === 'approved')); ?>
                        </span>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="text-gray-600">Pending:</span>
                        <span class="font-semibold text-yellow-600">
                            <?php echo count(array_filter($feedback_history, fn($f) => $f['status'] === 'pending')); ?>
                        </span>
                    </div>
                    <div class="border-t pt-2">
                        <div class="flex justify-between items-center">
                            <span class="text-gray-600">Earnings:</span>
                            <span class="font-semibold text-green-600">
                                €<?php echo number_format(array_sum(array_map(fn($f) => $f['reward_paid'] ? $f['reward_amount'] : 0, $feedback_history))); ?>
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Recent Feedback -->
            <?php if (!empty($feedback_history)): ?>
                <div class="bg-white rounded-lg shadow p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4 flex items-center gap-2">
                        <i data-lucide="clock" class="w-5 h-5"></i>
                        Recent Feedback
                    </h3>
                    <div class="space-y-3">
                        <?php foreach (array_slice($feedback_history, 0, 5) as $feedback): ?>
                            <div class="border border-gray-200 rounded-lg p-3">
                                <div class="flex items-center justify-between mb-2">
                                    <span class="font-medium text-sm text-gray-900">
                                        <?php echo htmlspecialchars($feedback['subject']); ?>
                                    </span>
                                    <div class="flex items-center gap-2">
                                        <span class="px-2 py-1 text-xs rounded-full <?php
                                        echo $feedback['status'] === 'approved' ? 'bg-green-100 text-green-800' :
                                            ($feedback['status'] === 'rejected' ? 'bg-red-100 text-red-800' : 'bg-yellow-100 text-yellow-800');
                                        ?>">
                                            <?php echo ucfirst($feedback['status']); ?>
                                        </span>
                                        <?php if ($feedback['status'] === 'pending'): ?>
                                            <button onclick="approveFeedback(<?php echo $feedback['id']; ?>)"
                                                class="text-xs bg-green-600 text-white px-2 py-1 rounded hover:bg-green-700 transition-colors">
                                                Approve
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="text-xs text-gray-500 mb-1">
                                    <?php echo ucfirst(str_replace('_', ' ', $feedback['category'])); ?> •
                                    <?php echo date('M j, Y', strtotime($feedback['created_at'])); ?>
                                </div>
                                <?php if ($feedback['reward_paid']): ?>
                                    <div class="text-xs text-green-600 font-medium">
                                        💰 Earned: €<?php echo number_format($feedback['reward_amount']); ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
    lucide.createIcons();

    // Character counter for message
    const messageTextarea = document.getElementById('message');
    if (messageTextarea) {
        messageTextarea.addEventListener('input', function () {
            const length = this.value.length;
            const minLength = 20;
            const counter = this.parentNode.querySelector('.text-gray-500');

            if (length < minLength) {
                counter.textContent = `${length}/${minLength} characters (minimum required)`;
                counter.className = 'text-sm text-red-500 mt-1';
            } else {
                counter.textContent = `${length} characters`;
                counter.className = 'text-sm text-green-500 mt-1';
            }
        });
    }

    // Function to approve feedback (for testing/admin purposes)
    function approveFeedback(feedbackId) {
        Swal.fire({
            title: 'Approve Feedback?',
            text: 'This will award the user an additional €1,260 (total €1,400).',
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#16a34a',
            cancelButtonColor: '#6b7280',
            confirmButtonText: 'Yes, Approve',
            cancelButtonText: 'Cancel'
        }).then((result) => {
            if (result.isConfirmed) {
                fetch('api/feedback_api.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        action: 'approve_feedback',
                        feedback_id: feedbackId
                    })
                })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            Swal.fire({
                                icon: 'success',
                                title: 'Feedback Approved!',
                                text: `User has been awarded €${data.additional_reward} additional reward.`,
                                confirmButtonColor: '#16a34a'
                            }).then(() => {
                                window.location.reload();
                            });
                        } else {
                            Swal.fire({
                                icon: 'error',
                                title: 'Approval Failed',
                                text: data.message || 'Failed to approve feedback.',
                                confirmButtonColor: '#ef4444'
                            });
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        Swal.fire({
                            icon: 'error',
                            title: 'Connection Error',
                            text: 'Failed to approve feedback. Please try again.',
                            confirmButtonColor: '#ef4444'
                        });
                    });
            }
        });
    }
</script>

<?php
// End content capture and render layout
endContent('Feedback - ' . APP_NAME, 'feedback');
?>