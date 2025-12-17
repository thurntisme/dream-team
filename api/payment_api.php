<?php
session_start();
header('Content-Type: application/json');

require_once '../config/config.php';
require_once '../config/constants.php';



// Check if database is available
if (!isDatabaseAvailable()) {
    echo json_encode(['success' => false, 'message' => 'Database not available']);
    exit;
}

$action = $_POST['action'] ?? '';
$userId = $_SESSION['user_id'];

try {
    switch ($action) {
        case 'validate_card':
            $cardNumber = $_POST['card_number'] ?? '';
            $expiryMonth = $_POST['expiry_month'] ?? '';
            $expiryYear = $_POST['expiry_year'] ?? '';
            $cvv = $_POST['cvv'] ?? '';

            $errors = [];

            // Basic card validation
            $cardNumber = str_replace(' ', '', $cardNumber);
            if (strlen($cardNumber) < 13 || strlen($cardNumber) > 19) {
                $errors[] = 'Invalid card number length';
            }

            if (!ctype_digit($cardNumber)) {
                $errors[] = 'Card number must contain only digits';
            }

            // Expiry validation
            $currentYear = (int) date('y');
            $currentMonth = (int) date('n');

            if (
                (int) $expiryYear < $currentYear ||
                ((int) $expiryYear == $currentYear && (int) $expiryMonth < $currentMonth)
            ) {
                $errors[] = 'Card has expired';
            }

            // CVV validation
            if (strlen($cvv) < 3 || strlen($cvv) > 4 || !ctype_digit($cvv)) {
                $errors[] = 'Invalid CVV';
            }

            echo json_encode([
                'success' => empty($errors),
                'errors' => $errors,
                'card_type' => getCardType($cardNumber)
            ]);
            break;

        case 'process_payment':
            $planKey = $_POST['plan'] ?? '';
            $cardNumber = $_POST['card_number'] ?? '';
            $amount = $_POST['amount'] ?? 0;

            // In a real application, you would integrate with a payment processor
            // like Stripe, PayPal, or similar. For demo purposes, we'll simulate

            // Simulate payment processing
            $paymentResult = simulatePayment($cardNumber, $amount);

            if ($paymentResult['success']) {
                // Upgrade user plan
                if (upgradeUserPlan($userId, $planKey)) {
                    echo json_encode([
                        'success' => true,
                        'message' => 'Payment processed successfully',
                        'transaction_id' => $paymentResult['transaction_id']
                    ]);
                } else {
                    echo json_encode([
                        'success' => false,
                        'message' => 'Payment processed but failed to upgrade plan'
                    ]);
                }
            } else {
                echo json_encode([
                    'success' => false,
                    'message' => $paymentResult['message']
                ]);
            }
            break;

        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
            break;
    }

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}

/**
 * Simulate payment processing
 * In production, this would integrate with a real payment processor
 */
function simulatePayment($cardNumber, $amount)
{
    // Remove spaces from card number
    $cardNumber = str_replace(' ', '', $cardNumber);

    // Demo card numbers that will succeed
    $successCards = [
        '4532123456789012', // Visa
        '5555555555554444', // Mastercard
        '378282246310005'   // Amex
    ];

    // Demo card numbers that will fail
    $failCards = [
        '4000000000000002', // Declined
        '4000000000000069', // Expired
        '4000000000000119'  // Processing error
    ];

    if (in_array($cardNumber, $failCards)) {
        return [
            'success' => false,
            'message' => 'Payment declined. Please check your card details.'
        ];
    }

    // Simulate processing delay
    usleep(500000); // 0.5 second delay

    return [
        'success' => true,
        'transaction_id' => 'txn_' . uniqid(),
        'message' => 'Payment processed successfully'
    ];
}

/**
 * Detect card type from card number
 */
function getCardType($cardNumber)
{
    $cardNumber = str_replace(' ', '', $cardNumber);

    if (preg_match('/^4/', $cardNumber)) {
        return 'visa';
    } elseif (preg_match('/^5[1-5]/', $cardNumber)) {
        return 'mastercard';
    } elseif (preg_match('/^3[47]/', $cardNumber)) {
        return 'amex';
    } elseif (preg_match('/^6(?:011|5)/', $cardNumber)) {
        return 'discover';
    }

    return 'unknown';
}
?>