<?php
session_start();

require_once 'config/config.php';
require_once 'config/constants.php';
require_once 'partials/layout.php';

// Require user to be logged in (in a real app, you'd check for admin privileges)
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

try {
    $db = getDbConnection();

    // Get all support tickets
    $stmt = $db->prepare('
        SELECT t.*, u.name as user_name, u.email as user_email, u.club_name 
        FROM support_tickets t 
        JOIN users u ON t.user_id = u.id 
        ORDER BY 
            CASE t.priority 
                WHEN "urgent" THEN 1 
                WHEN "high" THEN 2 
                WHEN "medium" THEN 3 
                WHEN "low" THEN 4 
            END,
            t.created_at DESC 
        LIMIT 50
    ');
    $result = $stmt->execute();

    $all_tickets = [];
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $all_tickets[] = $row;
    }

    $db->close();
} catch (Exception $e) {
    header('Location: install.php');
    exit;
}

// Start content capture
startContent();
?>

<div class="container mx-auto p-4 max-w-7xl">
    <!-- Header -->
    <div class="mb-8">
        <h1 class="text-3xl font-bold text-gray-900 mb-2">Support Ticket Management</h1>
        <p class="text-gray-600">Manage and respond to user support tickets</p>
    </div>

    <!-- Stats Overview -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-8">
        <div class="bg-white rounded-lg shadow p-6">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 bg-blue-100 rounded-full flex items-center justify-center">
                    <i data-lucide="inbox" class="w-5 h-5 text-blue-600"></i>
                </div>
                <div>
                    <div class="text-2xl font-bold text-gray-900">
                        <?php echo count(array_filter($all_tickets, fn($t) => $t['status'] === 'open')); ?>
                    </div>
                    <div class="text-sm text-gray-600">Open Tickets</div>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-lg shadow p-6">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 bg-yellow-100 rounded-full flex items-center justify-center">
                    <i data-lucide="clock" class="w-5 h-5 text-yellow-600"></i>
                </div>
                <div>
                    <div class="text-2xl font-bold text-gray-900">
                        <?php echo count(array_filter($all_tickets, fn($t) => $t['status'] === 'in_progress')); ?>
                    </div>
                    <div class="text-sm text-gray-600">In Progress</div>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-lg shadow p-6">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 bg-red-100 rounded-full flex items-center justify-center">
                    <i data-lucide="alert-triangle" class="w-5 h-5 text-red-600"></i>
                </div>
                <div>
                    <div class="text-2xl font-bold text-gray-900">
                        <?php echo count(array_filter($all_tickets, fn($t) => $t['priority'] === 'urgent' && $t['status'] !== 'closed')); ?>
                    </div>
                    <div class="text-sm text-gray-600">Urgent</div>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-lg shadow p-6">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 bg-green-100 rounded-full flex items-center justify-center">
                    <i data-lucide="check-circle" class="w-5 h-5 text-green-600"></i>
                </div>
                <div>
                    <div class="text-2xl font-bold text-gray-900">
                        <?php echo count(array_filter($all_tickets, fn($t) => $t['status'] === 'resolved')); ?>
                    </div>
                    <div class="text-sm text-gray-600">Resolved</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Tickets Table -->
    <div class="bg-white rounded-lg shadow overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-200">
            <h2 class="text-lg font-semibold text-gray-900">All Support Tickets</h2>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Ticket</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">User
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Category</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Priority</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Status</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Created</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Actions</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php foreach ($all_tickets as $ticket): ?>
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div>
                                    <div class="text-sm font-medium text-gray-900">
                                        <?php echo htmlspecialchars($ticket['ticket_number']); ?></div>
                                    <div class="text-sm text-gray-500">
                                        <?php echo htmlspecialchars(substr($ticket['subject'], 0, 50)) . (strlen($ticket['subject']) > 50 ? '...' : ''); ?>
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div>
                                    <div class="text-sm font-medium text-gray-900">
                                        <?php echo htmlspecialchars($ticket['user_name']); ?></div>
                                    <div class="text-sm text-gray-500"><?php echo htmlspecialchars($ticket['club_name']); ?>
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span
                                    class="text-sm text-gray-900"><?php echo ucfirst(str_replace('_', ' ', $ticket['category'])); ?></span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="px-2 py-1 text-xs rounded-full <?php
                                echo $ticket['priority'] === 'urgent' ? 'bg-red-100 text-red-800' :
                                    ($ticket['priority'] === 'high' ? 'bg-orange-100 text-orange-800' :
                                        ($ticket['priority'] === 'medium' ? 'bg-yellow-100 text-yellow-800' : 'bg-gray-100 text-gray-800'));
                                ?>">
                                    <?php echo ucfirst($ticket['priority']); ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="px-2 py-1 text-xs rounded-full <?php
                                echo $ticket['status'] === 'resolved' ? 'bg-green-100 text-green-800' :
                                    ($ticket['status'] === 'closed' ? 'bg-gray-100 text-gray-800' :
                                        ($ticket['status'] === 'in_progress' ? 'bg-blue-100 text-blue-800' : 'bg-yellow-100 text-yellow-800'));
                                ?>">
                                    <?php echo ucfirst(str_replace('_', ' ', $ticket['status'])); ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                <?php echo date('M j, Y', strtotime($ticket['created_at'])); ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                <div class="flex gap-2">
                                    <button onclick="viewTicket(<?php echo $ticket['id']; ?>)"
                                        class="text-blue-600 hover:text-blue-900">View</button>
                                    <?php if ($ticket['status'] !== 'closed' && $ticket['status'] !== 'resolved'): ?>
                                        <button onclick="respondToTicket(<?php echo $ticket['id']; ?>)"
                                            class="text-green-600 hover:text-green-900">Respond</button>
                                        <button onclick="resolveTicket(<?php echo $ticket['id']; ?>)"
                                            class="text-purple-600 hover:text-purple-900">Resolve</button>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
    lucide.createIcons();

    function viewTicket(ticketId) {
        // In a real implementation, this would show ticket details
        Swal.fire({
            title: 'Ticket Details',
            text: `Viewing ticket ID: ${ticketId}`,
            icon: 'info',
            confirmButtonColor: '#3b82f6'
        });
    }

    function respondToTicket(ticketId) {
        Swal.fire({
            title: 'Respond to Ticket',
            html: `
                <textarea id="response" class="w-full p-3 border rounded-lg" rows="4" 
                          placeholder="Enter your response to the user..."></textarea>
            `,
            showCancelButton: true,
            confirmButtonColor: '#16a34a',
            cancelButtonColor: '#6b7280',
            confirmButtonText: 'Send Response',
            cancelButtonText: 'Cancel',
            preConfirm: () => {
                const response = document.getElementById('response').value;
                if (!response.trim()) {
                    Swal.showValidationMessage('Please enter a response');
                    return false;
                }
                return response;
            }
        }).then((result) => {
            if (result.isConfirmed) {
                fetch('api/support_api.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        action: 'add_response',
                        ticket_id: ticketId,
                        response: result.value
                    })
                })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            Swal.fire({
                                icon: 'success',
                                title: 'Response Sent!',
                                text: 'Your response has been sent to the user.',
                                confirmButtonColor: '#16a34a'
                            }).then(() => {
                                window.location.reload();
                            });
                        } else {
                            Swal.fire({
                                icon: 'error',
                                title: 'Failed to Send Response',
                                text: data.message || 'Please try again.',
                                confirmButtonColor: '#ef4444'
                            });
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        Swal.fire({
                            icon: 'error',
                            title: 'Connection Error',
                            text: 'Failed to send response. Please try again.',
                            confirmButtonColor: '#ef4444'
                        });
                    });
            }
        });
    }

    function resolveTicket(ticketId) {
        Swal.fire({
            title: 'Resolve Ticket?',
            text: 'This will mark the ticket as resolved.',
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#16a34a',
            cancelButtonColor: '#6b7280',
            confirmButtonText: 'Yes, Resolve',
            cancelButtonText: 'Cancel'
        }).then((result) => {
            if (result.isConfirmed) {
                fetch('api/support_api.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        action: 'update_ticket_status',
                        ticket_id: ticketId,
                        status: 'resolved',
                        admin_response: 'Ticket resolved by admin.'
                    })
                })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            Swal.fire({
                                icon: 'success',
                                title: 'Ticket Resolved!',
                                text: 'The ticket has been marked as resolved.',
                                confirmButtonColor: '#16a34a'
                            }).then(() => {
                                window.location.reload();
                            });
                        } else {
                            Swal.fire({
                                icon: 'error',
                                title: 'Failed to Resolve',
                                text: data.message || 'Please try again.',
                                confirmButtonColor: '#ef4444'
                            });
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        Swal.fire({
                            icon: 'error',
                            title: 'Connection Error',
                            text: 'Failed to resolve ticket. Please try again.',
                            confirmButtonColor: '#ef4444'
                        });
                    });
            }
        });
    }
</script>

<?php
// End content capture and render layout
endContent('Admin Support - ' . APP_NAME, 'admin');
?>