<?php
session_start();
require_once 'config/config.php';
require_once 'config/constants.php';
require_once 'includes/helpers.php';
require_once 'partials/layout.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}



$userId = $_SESSION['user_id'];
$message = '';
$messageType = '';

// Get selected plan from URL parameter
$selectedPlan = $_GET['plan'] ?? '';
$allPlans = getAllPlans();

// Validate selected plan
if (!isset($allPlans[$selectedPlan]) || $selectedPlan === 'free') {
    header('Location: plans.php');
    exit;
}

$plan = $allPlans[$selectedPlan];
$currentPlan = getUserPlan($userId);

// Handle payment form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'process_payment') {
        // Get form data
        $cardNumber = $_POST['card_number'] ?? '';
        $expiryMonth = $_POST['expiry_month'] ?? '';
        $expiryYear = $_POST['expiry_year'] ?? '';
        $cvv = $_POST['cvv'] ?? '';
        $cardholderName = $_POST['cardholder_name'] ?? '';
        $billingEmail = $_POST['billing_email'] ?? '';

        // Basic validation
        $errors = [];

        if (empty($cardNumber) || strlen(str_replace(' ', '', $cardNumber)) < 13) {
            $errors[] = 'Please enter a valid card number';
        }

        if (empty($expiryMonth) || empty($expiryYear)) {
            $errors[] = 'Please enter card expiry date';
        }

        if (empty($cvv) || strlen($cvv) < 3) {
            $errors[] = 'Please enter a valid CVV';
        }

        if (empty($cardholderName)) {
            $errors[] = 'Please enter cardholder name';
        }

        if (empty($billingEmail) || !filter_var($billingEmail, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Please enter a valid billing email';
        }

        if (empty($errors)) {
            // In a real application, you would process the payment with a payment gateway
            // For demo purposes, we'll simulate a successful payment

            // Simulate payment processing delay
            sleep(1);

            // For demo, we'll assume payment is successful
            $paymentSuccessful = true;

            if ($paymentSuccessful) {
                // Upgrade user plan
                if (upgradeUserPlan($userId, $selectedPlan)) {
                    // Redirect to success page
                    header('Location: payment_success.php?plan=' . $selectedPlan);
                    exit;
                } else {
                    $message = 'Payment processed but failed to upgrade plan. Please contact support.';
                    $messageType = 'error';
                }
            } else {
                $message = 'Payment failed. Please check your card details and try again.';
                $messageType = 'error';
            }
        } else {
            $message = implode('<br>', $errors);
            $messageType = 'error';
        }
    }
}

startContent();
?>

<div class="container mx-auto px-4 max-w-6xl py-8">
    <!-- Header -->
    <div class="mb-8">
        <div class="flex items-center gap-3 mb-4">
            <a href="plans.php" class="text-gray-600 hover:text-gray-800">
                <i data-lucide="arrow-left" class="w-6 h-6"></i>
            </a>
            <div>
                <h1 class="text-3xl font-bold text-gray-900">Complete Your Purchase</h1>
                <p class="text-gray-600">Upgrade to <?php echo htmlspecialchars($plan['name']); ?></p>
            </div>
        </div>
    </div>

    <!-- Message Display -->
    <?php if ($message): ?>
        <div
            class="mb-6 p-4 rounded-lg <?php echo $messageType === 'success' ? 'bg-green-100 text-green-800 border border-green-200' : 'bg-red-100 text-red-800 border border-red-200'; ?>">
            <div class="flex items-center gap-2">
                <i data-lucide="<?php echo $messageType === 'success' ? 'check-circle' : 'alert-circle'; ?>"
                    class="w-5 h-5"></i>
                <div><?php echo $message; ?></div>
            </div>
        </div>
    <?php endif; ?>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        <!-- Payment Form -->
        <div class="lg:col-span-2">
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                <h2 class="text-xl font-semibold text-gray-900 mb-6">Payment Information</h2>

                <form method="POST" id="paymentForm">
                    <input type="hidden" name="action" value="process_payment">

                    <!-- Card Number -->
                    <div class="mb-6">
                        <label for="card_number" class="block text-sm font-medium text-gray-700 mb-2">
                            Card Number
                        </label>
                        <div class="relative">
                            <input type="text" name="card_number" id="card_number"
                                class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 pl-12"
                                placeholder="1234 5678 9012 3456" maxlength="19" required>
                            <div class="absolute left-3 top-1/2 transform -translate-y-1/2">
                                <i data-lucide="credit-card" class="w-5 h-5 text-gray-400"></i>
                            </div>
                        </div>
                    </div>

                    <!-- Expiry and CVV -->
                    <div class="grid grid-cols-2 gap-4 mb-6">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                Expiry Date
                            </label>
                            <div class="grid grid-cols-2 gap-2">
                                <select name="expiry_month"
                                    class="px-3 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                    required>
                                    <option value="">MM</option>
                                    <?php for ($i = 1; $i <= 12; $i++): ?>
                                        <option value="<?php echo sprintf('%02d', $i); ?>">
                                            <?php echo sprintf('%02d', $i); ?>
                                        </option>
                                    <?php endfor; ?>
                                </select>
                                <select name="expiry_year"
                                    class="px-3 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                    required>
                                    <option value="">YY</option>
                                    <?php for ($i = date('Y'); $i <= date('Y') + 10; $i++): ?>
                                        <option value="<?php echo substr($i, -2); ?>"><?php echo substr($i, -2); ?></option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                        </div>
                        <div>
                            <label for="cvv" class="block text-sm font-medium text-gray-700 mb-2">
                                CVV
                            </label>
                            <input type="text" name="cvv" id="cvv"
                                class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                placeholder="123" maxlength="4" required>
                        </div>
                    </div>

                    <!-- Cardholder Name -->
                    <div class="mb-6">
                        <label for="cardholder_name" class="block text-sm font-medium text-gray-700 mb-2">
                            Cardholder Name
                        </label>
                        <input type="text" name="cardholder_name" id="cardholder_name"
                            class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                            placeholder="John Doe" required>
                    </div>

                    <!-- Billing Email -->
                    <div class="mb-6">
                        <label for="billing_email" class="block text-sm font-medium text-gray-700 mb-2">
                            Billing Email
                        </label>
                        <input type="email" name="billing_email" id="billing_email"
                            class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                            placeholder="john@example.com" required>
                    </div>

                    <!-- Security Notice -->
                    <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-6">
                        <div class="flex items-start gap-3">
                            <i data-lucide="shield-check" class="w-5 h-5 text-blue-600 mt-0.5"></i>
                            <div class="text-sm text-blue-800">
                                <div class="font-medium mb-1">Secure Payment</div>
                                <div>Your payment information is encrypted and secure. We use industry-standard SSL
                                    encryption to protect your data.</div>
                            </div>
                        </div>
                    </div>

                    <!-- Submit Button -->
                    <button type="submit" id="submitBtn"
                        class="w-full px-6 py-4 bg-blue-600 text-white font-medium rounded-lg hover:bg-blue-700 focus:ring-4 focus:ring-blue-300 transition-colors">
                        <div class="flex items-center justify-center gap-2">
                            <i data-lucide="lock" class="w-5 h-5"></i>
                            <span>Complete Payment - <?php echo formatPlanPrice($plan['price']); ?></span>
                        </div>
                    </button>
                </form>
            </div>
        </div>

        <!-- Order Summary -->
        <div class="lg:col-span-1">
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 sticky top-8">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">Order Summary</h3>

                <!-- Current Plan -->
                <?php if ($currentPlan): ?>
                    <div class="mb-4 pb-4 border-b border-gray-200">
                        <div class="text-sm text-gray-600 mb-1">Current Plan</div>
                        <div class="font-medium text-gray-900"><?php echo htmlspecialchars($currentPlan['name']); ?></div>
                        <div class="text-sm text-gray-500"><?php echo formatPlanPrice($currentPlan['price']); ?>/month</div>
                    </div>
                <?php endif; ?>

                <!-- New Plan -->
                <div class="mb-6">
                    <div class="text-sm text-gray-600 mb-1">Upgrading to</div>
                    <div class="font-semibold text-gray-900 text-lg"><?php echo htmlspecialchars($plan['name']); ?>
                    </div>
                    <div class="text-gray-600"><?php echo htmlspecialchars($plan['description']); ?></div>
                </div>

                <!-- Features -->
                <div class="mb-6">
                    <div class="text-sm font-medium text-gray-700 mb-3">What's included:</div>
                    <div class="space-y-2">
                        <div class="flex items-center gap-2 text-sm text-gray-600">
                            <i data-lucide="check" class="w-4 h-4 text-green-600"></i>
                            <span><?php echo $plan['features']['max_academy_players']; ?> Academy Players</span>
                        </div>
                        <div class="flex items-center gap-2 text-sm text-gray-600">
                            <i data-lucide="check" class="w-4 h-4 text-green-600"></i>
                            <span><?php echo $plan['features']['max_staff_members']; ?> Staff Members</span>
                        </div>
                        <div class="flex items-center gap-2 text-sm text-gray-600">
                            <i data-lucide="check" class="w-4 h-4 text-green-600"></i>
                            <span><?php echo number_format($plan['features']['max_stadium_capacity']); ?> Stadium
                                Capacity</span>
                        </div>
                        <?php if (!$plan['features']['show_ads']): ?>
                            <div class="flex items-center gap-2 text-sm text-gray-600">
                                <i data-lucide="check" class="w-4 h-4 text-green-600"></i>
                                <span>Ad-Free Experience</span>
                            </div>
                        <?php endif; ?>
                        <?php if (isset($plan['features']['priority_support']) && $plan['features']['priority_support']): ?>
                            <div class="flex items-center gap-2 text-sm text-gray-600">
                                <i data-lucide="check" class="w-4 h-4 text-green-600"></i>
                                <span>Priority Support</span>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Pricing -->
                <div class="border-t border-gray-200 pt-4">
                    <div class="flex justify-between items-center mb-2">
                        <span class="text-gray-600">Monthly Price</span>
                        <span class="font-medium"><?php echo formatPlanPrice($plan['price']); ?></span>
                    </div>
                    <div class="flex justify-between items-center mb-4">
                        <span class="text-gray-600">Today's Charge</span>
                        <span
                            class="text-xl font-bold text-gray-900"><?php echo formatPlanPrice($plan['price']); ?></span>
                    </div>
                    <div class="text-xs text-gray-500">
                        Billed monthly. Cancel anytime.
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    // Initialize Lucide icons
    lucide.createIcons();

    // Format card number input and detect card type
    document.getElementById('card_number').addEventListener('input', function (e) {
        let value = e.target.value.replace(/\s/g, '').replace(/[^0-9]/gi, '');
        let formattedValue = value.match(/.{1,4}/g)?.join(' ') || value;
        e.target.value = formattedValue;

        // Update card icon based on card type
        const cardIcon = e.target.parentNode.querySelector('i');
        if (value.startsWith('4')) {
            cardIcon.style.color = '#1a365d'; // Visa blue
        } else if (value.startsWith('5')) {
            cardIcon.style.color = '#eb1c26'; // Mastercard red
        } else if (value.startsWith('3')) {
            cardIcon.style.color = '#006fcf'; // Amex blue
        } else {
            cardIcon.style.color = '#6b7280'; // Default gray
        }
    });

    // Only allow numbers in CVV
    document.getElementById('cvv').addEventListener('input', function (e) {
        e.target.value = e.target.value.replace(/[^0-9]/g, '');
    });

    // Form validation
    function validateForm() {
        const cardNumber = document.getElementById('card_number').value.replace(/\s/g, '');
        const expiryMonth = document.querySelector('select[name="expiry_month"]').value;
        const expiryYear = document.querySelector('select[name="expiry_year"]').value;
        const cvv = document.getElementById('cvv').value;
        const cardholderName = document.getElementById('cardholder_name').value;
        const billingEmail = document.getElementById('billing_email').value;

        let errors = [];

        if (cardNumber.length < 13 || cardNumber.length > 19) {
            errors.push('Please enter a valid card number');
        }

        if (!expiryMonth || !expiryYear) {
            errors.push('Please select expiry date');
        }

        if (cvv.length < 3 || cvv.length > 4) {
            errors.push('Please enter a valid CVV');
        }

        if (cardholderName.trim().length < 2) {
            errors.push('Please enter cardholder name');
        }

        if (!billingEmail.includes('@')) {
            errors.push('Please enter a valid email address');
        }

        return errors;
    }

    // Form submission with loading state
    document.getElementById('paymentForm').addEventListener('submit', function (e) {
        e.preventDefault();

        const errors = validateForm();
        if (errors.length > 0) {
            alert('Please fix the following errors:\n' + errors.join('\n'));
            return;
        }

        const submitBtn = document.getElementById('submitBtn');
        const originalContent = submitBtn.innerHTML;

        submitBtn.disabled = true;
        submitBtn.innerHTML = '<div class="flex items-center justify-center gap-2"><i data-lucide="loader-2" class="w-5 h-5 animate-spin"></i><span>Processing Payment...</span></div>';

        // Re-initialize icons for the loader
        lucide.createIcons();

        // Submit the form after a short delay to show the loading state
        setTimeout(() => {
            this.submit();
        }, 1000);

        // If there's an error, restore the button
        setTimeout(() => {
            if (submitBtn.disabled) {
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalContent;
                lucide.createIcons();
            }
        }, 15000); // 15 second timeout
    });

    // Demo card numbers for testing
    const demoCards = {
        'visa': '4532 1234 5678 9012',
        'mastercard': '5555 5555 5555 4444',
        'amex': '3782 822463 10005'
    };

    // Add demo card buttons for testing
    const cardInput = document.getElementById('card_number');
    const demoContainer = document.createElement('div');
    demoContainer.className = 'mt-2 text-xs text-gray-500';
    demoContainer.innerHTML = 'Demo cards: ';

    Object.entries(demoCards).forEach(([type, number]) => {
        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'text-blue-600 hover:text-blue-800 mr-2';
        button.textContent = type.charAt(0).toUpperCase() + type.slice(1);
        button.onclick = () => {
            cardInput.value = number;
            document.getElementById('cardholder_name').value = 'John Doe';
            document.getElementById('billing_email').value = 'john@example.com';
            document.getElementById('cvv').value = '123';
            document.querySelector('select[name="expiry_month"]').value = '12';
            document.querySelector('select[name =" expiry_year "] ').value = ' 25 ';
        };
        demoContainer.appendChild(button);
    });

    cardInput.parentNode.appendChild(demoContainer);
</script>

<?php
endContent('Payment - ' . $plan['name'], 'payment');
?>