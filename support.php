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

    // Create support tickets table if it doesn't exist
    $db->exec('CREATE TABLE IF NOT EXISTS support_tickets (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER NOT NULL,
        ticket_number TEXT UNIQUE NOT NULL,
        priority TEXT DEFAULT "medium",
        category TEXT NOT NULL,
        subject TEXT NOT NULL,
        message TEXT NOT NULL,
        status TEXT DEFAULT "open",
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        last_response_at DATETIME,
        admin_response TEXT,
        resolution_notes TEXT,
        FOREIGN KEY (user_id) REFERENCES users(id)
    )');

    // Get user data
    $stmt = $db->prepare('SELECT name, email, club_name, budget FROM users WHERE id = :id');
    $stmt->bindValue(':id', $_SESSION['user_id'], SQLITE3_INTEGER);
    $result = $stmt->execute();
    $user = $result->fetchArray(SQLITE3_ASSOC);

    // Handle form submission
    $message = '';
    $message_type = 'info';

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_ticket'])) {
        $priority = trim($_POST['priority'] ?? 'medium');
        $category = trim($_POST['category'] ?? '');
        $subject = trim($_POST['subject'] ?? '');
        $ticket_message = trim($_POST['message'] ?? '');

        if (empty($category) || empty($subject) || empty($ticket_message)) {
            $message = 'Please fill in all required fields.';
            $message_type = 'error';
        } elseif (strlen($ticket_message) < 10) {
            $message = 'Please provide more details about your issue (minimum 10 characters).';
            $message_type = 'error';
        } else {
            // Generate unique ticket number
            $ticket_number = 'TKT-' . date('Ymd') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);

            // Ensure ticket number is unique
            $stmt = $db->prepare('SELECT COUNT(*) as count FROM support_tickets WHERE ticket_number = :ticket_number');
            $stmt->bindValue(':ticket_number', $ticket_number, SQLITE3_TEXT);
            $result = $stmt->execute();
            $exists = $result->fetchArray(SQLITE3_ASSOC);

            while ($exists['count'] > 0) {
                $ticket_number = 'TKT-' . date('Ymd') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
                $stmt = $db->prepare('SELECT COUNT(*) as count FROM support_tickets WHERE ticket_number = :ticket_number');
                $stmt->bindValue(':ticket_number', $ticket_number, SQLITE3_TEXT);
                $result = $stmt->execute();
                $exists = $result->fetchArray(SQLITE3_ASSOC);
            }

            // Insert ticket
            $stmt = $db->prepare('INSERT INTO support_tickets (user_id, ticket_number, priority, category, subject, message) VALUES (:user_id, :ticket_number, :priority, :category, :subject, :message)');
            $stmt->bindValue(':user_id', $_SESSION['user_id'], SQLITE3_INTEGER);
            $stmt->bindValue(':ticket_number', $ticket_number, SQLITE3_TEXT);
            $stmt->bindValue(':priority', $priority, SQLITE3_TEXT);
            $stmt->bindValue(':category', $category, SQLITE3_TEXT);
            $stmt->bindValue(':subject', $subject, SQLITE3_TEXT);
            $stmt->bindValue(':message', $ticket_message, SQLITE3_TEXT);

            if ($stmt->execute()) {
                $message = "Support ticket created successfully! Your ticket number is: <strong>$ticket_number</strong>. We'll respond within 24-48 hours.";
                $message_type = 'success';
            } else {
                $message = 'Failed to create support ticket. Please try again.';
                $message_type = 'error';
            }
        }
    }

    // Get user's ticket history
    $stmt = $db->prepare('SELECT * FROM support_tickets WHERE user_id = :user_id ORDER BY created_at DESC LIMIT 20');
    $stmt->bindValue(':user_id', $_SESSION['user_id'], SQLITE3_INTEGER);
    $result = $stmt->execute();

    $ticket_history = [];
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $ticket_history[] = $row;
    }

    $db->close();
} catch (Exception $e) {
    header('Location: install.php');
    exit;
}

// Support categories
$categories = [
    'account' => 'Account Issues',
    'payment' => 'Payment Problems',
    'billing' => 'Billing Questions',
    'technical' => 'Technical Issues',
    'gameplay' => 'Gameplay Problems',
    'purchase' => 'Purchase Issues',
    'refund' => 'Refund Request',
    'subscription' => 'Subscription Issues',
    'data_loss' => 'Data Loss/Recovery',
    'other' => 'Other Support Request'
];

// Priority levels
$priorities = [
    'low' => 'Low Priority',
    'medium' => 'Medium Priority',
    'high' => 'High Priority',
    'urgent' => 'Urgent'
];

// Start content capture
startContent();
?>

<div class="container mx-auto p-4 max-w-6xl">
    <!-- Messages -->
    <?php if ($message): ?>
        <div
            class="mb-6 p-4 rounded-lg border <?php echo $message_type === 'success' ? 'bg-green-50 border-green-200 text-green-800' : 'bg-red-50 border-red-200 text-red-800'; ?>">
            <div class="flex items-center gap-2">
                <i data-lucide="<?php echo $message_type === 'success' ? 'check-circle' : 'alert-circle'; ?>"
                    class="w-5 h-5"></i>
                <div><?php echo $message; ?></div>
            </div>
        </div>
    <?php endif; ?>

    <!-- Header -->
    <div class="mb-8">
        <div class="flex justify-between items-center mb-4">
            <div>
                <h1 class="text-3xl font-bold text-gray-900 mb-2">Support Center</h1>
                <p class="text-gray-600">Get help with your account, payments, and technical issues</p>
            </div>
            <div class="bg-blue-100 text-blue-800 px-4 py-2 rounded-lg">
                <div class="text-sm text-blue-600">Open Tickets</div>
                <div class="text-lg font-bold">
                    <?php echo count(array_filter($ticket_history, fn($t) => $t['status'] === 'open')); ?>
                </div>
            </div>
        </div>

        <!-- Support Information -->
        <div class="bg-gradient-to-r from-blue-50 to-indigo-50 rounded-lg p-6 border border-blue-200 mb-6">
            <div class="flex items-center gap-4 mb-4">
                <div class="w-12 h-12 bg-blue-600 rounded-full flex items-center justify-center">
                    <i data-lucide="headphones" class="w-6 h-6 text-white"></i>
                </div>
                <div>
                    <h2 class="text-xl font-bold text-gray-900">24/7 Support Available</h2>
                    <p class="text-gray-600">Our support team is here to help you with any issues or questions.</p>
                </div>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div class="bg-white p-4 rounded-lg border border-blue-200">
                    <div class="flex items-center gap-2 mb-2">
                        <i data-lucide="clock" class="w-5 h-5 text-blue-600"></i>
                        <span class="font-semibold text-gray-900">Response Time</span>
                    </div>
                    <div class="text-lg font-bold text-blue-600 mb-1">24-48 Hours</div>
                    <p class="text-sm text-gray-600">Average response time for all tickets</p>
                </div>
                <div class="bg-white p-4 rounded-lg border border-blue-200">
                    <div class="flex items-center gap-2 mb-2">
                        <i data-lucide="shield-check" class="w-5 h-5 text-green-600"></i>
                        <span class="font-semibold text-gray-900">Secure Support</span>
                    </div>
                    <div class="text-lg font-bold text-green-600 mb-1">100% Private</div>
                    <p class="text-sm text-gray-600">Your information is kept confidential</p>
                </div>
                <div class="bg-white p-4 rounded-lg border border-blue-200">
                    <div class="flex items-center gap-2 mb-2">
                        <i data-lucide="users" class="w-5 h-5 text-purple-600"></i>
                        <span class="font-semibold text-gray-900">Expert Team</span>
                    </div>
                    <div class="text-lg font-bold text-purple-600 mb-1">Specialists</div>
                    <p class="text-sm text-gray-600">Dedicated support specialists</p>
                </div>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        <!-- Support Ticket Form -->
        <div class="lg:col-span-2">
            <div class="bg-white rounded-lg shadow p-6">
                <h2 class="text-xl font-bold text-gray-900 mb-6 flex items-center gap-2">
                    <i data-lucide="plus-circle" class="w-5 h-5"></i>
                    Create Support Ticket
                </h2>

                <form method="POST" class="space-y-6">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label for="category" class="block text-sm font-medium text-gray-700 mb-2">
                                Issue Category <span class="text-red-500">*</span>
                            </label>
                            <select name="category" id="category" required
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                                <option value="">Select category...</option>
                                <?php foreach ($categories as $value => $label): ?>
                                    <option value="<?php echo $value; ?>"><?php echo $label; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div>
                            <label for="priority" class="block text-sm font-medium text-gray-700 mb-2">
                                Priority Level
                            </label>
                            <select name="priority" id="priority"
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                                <?php foreach ($priorities as $value => $label): ?>
                                    <option value="<?php echo $value; ?>" <?php echo $value === 'medium' ? 'selected' : ''; ?>>
                                        <?php echo $label; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div>
                        <label for="subject" class="block text-sm font-medium text-gray-700 mb-2">
                            Subject <span class="text-red-500">*</span>
                        </label>
                        <input type="text" name="subject" id="subject" required maxlength="200"
                            placeholder="Brief description of your issue..."
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>

                    <div>
                        <label for="message" class="block text-sm font-medium text-gray-700 mb-2">
                            Detailed Description <span class="text-red-500">*</span>
                        </label>
                        <textarea name="message" id="message" required rows="6" minlength="10"
                            placeholder="Please provide detailed information about your issue. Include any error messages, steps you took, and what you expected to happen..."
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"></textarea>
                        <div class="text-sm text-gray-500 mt-1">Minimum 10 characters required</div>
                    </div>

                    <div class="bg-yellow-50 p-4 rounded-lg border border-yellow-200">
                        <div class="flex items-start gap-2">
                            <i data-lucide="info" class="w-5 h-5 text-yellow-600 mt-0.5"></i>
                            <div class="text-sm text-yellow-800">
                                <p class="font-medium mb-1">Before submitting a ticket:</p>
                                <ul class="list-disc list-inside space-y-1 text-yellow-700">
                                    <li>Check if your issue is a game bug (use Feedback instead)</li>
                                    <li>Include your account email and club name</li>
                                    <li>Provide screenshots if applicable</li>
                                    <li>Be specific about when the issue occurred</li>
                                </ul>
                            </div>
                        </div>
                    </div>

                    <button type="submit" name="submit_ticket"
                        class="w-full bg-blue-600 text-white px-6 py-3 rounded-lg hover:bg-blue-700 transition-colors font-semibold flex items-center justify-center gap-2">
                        <i data-lucide="send" class="w-5 h-5"></i>
                        Create Support Ticket
                    </button>
                </form>
            </div>
        </div>

        <!-- Ticket History & Quick Actions -->
        <div class="space-y-6">
            <!-- Quick Stats -->
            <div class="bg-white rounded-lg shadow p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-4 flex items-center gap-2">
                    <i data-lucide="bar-chart" class="w-5 h-5"></i>
                    Your Ticket Stats
                </h3>
                <div class="space-y-3">
                    <div class="flex justify-between items-center">
                        <span class="text-gray-600">Total Tickets:</span>
                        <span class="font-semibold"><?php echo count($ticket_history); ?></span>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="text-gray-600">Open:</span>
                        <span class="font-semibold text-blue-600">
                            <?php echo count(array_filter($ticket_history, fn($t) => $t['status'] === 'open')); ?>
                        </span>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="text-gray-600">Resolved:</span>
                        <span class="font-semibold text-green-600">
                            <?php echo count(array_filter($ticket_history, fn($t) => $t['status'] === 'resolved')); ?>
                        </span>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="text-gray-600">Closed:</span>
                        <span class="font-semibold text-gray-600">
                            <?php echo count(array_filter($ticket_history, fn($t) => $t['status'] === 'closed')); ?>
                        </span>
                    </div>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="bg-white rounded-lg shadow p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-4 flex items-center gap-2">
                    <i data-lucide="zap" class="w-5 h-5"></i>
                    Quick Actions
                </h3>
                <div class="space-y-3">
                    <a href="feedback.php"
                        class="flex items-center gap-3 p-3 bg-green-50 border border-green-200 rounded-lg hover:bg-green-100 transition-colors">
                        <i data-lucide="message-circle" class="w-5 h-5 text-green-600"></i>
                        <div>
                            <div class="font-medium text-green-900">Report Bug/Feedback</div>
                            <div class="text-sm text-green-700">Earn €140-€1,400 for feedback</div>
                        </div>
                    </a>
                    <a href="settings.php"
                        class="flex items-center gap-3 p-3 bg-blue-50 border border-blue-200 rounded-lg hover:bg-blue-100 transition-colors">
                        <i data-lucide="settings" class="w-5 h-5 text-blue-600"></i>
                        <div>
                            <div class="font-medium text-blue-900">Account Settings</div>
                            <div class="text-sm text-blue-700">Update your profile</div>
                        </div>
                    </a>
                    <a href="plans.php"
                        class="flex items-center gap-3 p-3 bg-purple-50 border border-purple-200 rounded-lg hover:bg-purple-100 transition-colors">
                        <i data-lucide="crown" class="w-5 h-5 text-purple-600"></i>
                        <div>
                            <div class="font-medium text-purple-900">Upgrade Plan</div>
                            <div class="text-sm text-purple-700">View subscription options</div>
                        </div>
                    </a>
                </div>
            </div>

            <!-- Recent Tickets -->
            <?php if (!empty($ticket_history)): ?>
                <div class="bg-white rounded-lg shadow p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4 flex items-center gap-2">
                        <i data-lucide="clock" class="w-5 h-5"></i>
                        Recent Tickets
                    </h3>
                    <div class="space-y-3">
                        <?php foreach (array_slice($ticket_history, 0, 5) as $ticket): ?>
                            <div class="border border-gray-200 rounded-lg p-3">
                                <div class="flex items-center justify-between mb-2">
                                    <span class="font-medium text-sm text-gray-900">
                                        <?php echo htmlspecialchars($ticket['subject']); ?>
                                    </span>
                                    <div class="flex items-center gap-2">
                                        <span class="px-2 py-1 text-xs rounded-full <?php
                                        echo $ticket['status'] === 'resolved' ? 'bg-green-100 text-green-800' :
                                            ($ticket['status'] === 'closed' ? 'bg-gray-100 text-gray-800' : 'bg-blue-100 text-blue-800');
                                        ?>">
                                            <?php echo ucfirst($ticket['status']); ?>
                                        </span>
                                        <span class="px-2 py-1 text-xs rounded-full <?php
                                        echo $ticket['priority'] === 'urgent' ? 'bg-red-100 text-red-800' :
                                            ($ticket['priority'] === 'high' ? 'bg-orange-100 text-orange-800' :
                                                ($ticket['priority'] === 'medium' ? 'bg-yellow-100 text-yellow-800' : 'bg-gray-100 text-gray-800'));
                                        ?>">
                                            <?php echo ucfirst($ticket['priority']); ?>
                                        </span>
                                    </div>
                                </div>
                                <div class="text-xs text-gray-500 mb-1">
                                    <span class="font-medium"><?php echo $ticket['ticket_number']; ?></span> •
                                    <?php echo ucfirst(str_replace('_', ' ', $ticket['category'])); ?> •
                                    <?php echo date('M j, Y', strtotime($ticket['created_at'])); ?>
                                </div>
                                <?php if ($ticket['admin_response']): ?>
                                    <div class="text-xs text-green-600 font-medium">
                                        ✓ Response received
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

<script src="assets/js/support.js"></script>

<?php
// End content capture and render layout
endContent('Support Center - ' . APP_NAME, 'support');
?>