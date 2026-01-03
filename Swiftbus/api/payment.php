<?php
/**
 * SwiftBus Payment API
 * 
 * Handles payment processing, payment methods, and payment status
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    exit(0);
}

require_once '../includes/functions.php';

$action = $_GET['action'] ?? $_POST['action'] ?? '';

switch ($action) {
    case 'process_payment':
        handleProcessPayment();
        break;
    case 'verify_payment':
        handleVerifyPayment();
        break;
    case 'get_payment_methods':
        handleGetPaymentMethods();
        break;
    case 'get_payment_status':
        handleGetPaymentStatus();
        break;
    case 'refund_payment':
        handleRefundPayment();
        break;
    default:
        handleError('Invalid action', 400);
}

/**
 * Process payment for a booking
 */
function handleProcessPayment() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        handleError('Method not allowed', 405);
    }
    
    requireLogin();
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    // Validate required fields
    $required = ['booking_id', 'payment_method', 'amount'];
    foreach ($required as $field) {
        if (empty($input[$field])) {
            handleError("Field '$field' is required", 400);
        }
    }
    
    $bookingId = sanitizeInput($input['booking_id']);
    $paymentMethod = sanitizeInput($input['payment_method']);
    $amount = (float)$input['amount'];
    $paymentData = $input['payment_data'] ?? [];
    
    // Validate payment method
    $validMethods = ['telebirr', 'cbe', 'dashen', 'card', 'cash'];
    if (!in_array($paymentMethod, $validMethods)) {
        handleError('Invalid payment method', 400);
    }
    
    // Validate amount
    if ($amount <= 0) {
        handleError('Invalid payment amount', 400);
    }
    
    try {
        $db = getDB();
        $db->beginTransaction();
        
        // Get booking details
        $stmt = $db->prepare("
            SELECT b.*, u.email, u.full_name 
            FROM bookings b 
            JOIN users u ON b.user_id = u.id 
            WHERE b.booking_id = ? AND b.user_id = (SELECT id FROM users WHERE user_id = ?)
        ");
        $stmt->execute([$bookingId, $_SESSION['user_id']]);
        $booking = $stmt->fetch();
        
        if (!$booking) {
            $db->rollBack();
            handleError('Booking not found', 404);
        }
        
        if ($booking['payment_status'] === 'paid') {
            $db->rollBack();
            handleError('Booking is already paid', 400);
        }
        
        if ($booking['booking_status'] === 'cancelled') {
            $db->rollBack();
            handleError('Cannot pay for cancelled booking', 400);
        }
        
        // Validate payment amount matches booking total
        if (abs($amount - $booking['total_amount']) > 0.01) {
            $db->rollBack();
            handleError('Payment amount does not match booking total', 400);
        }
        
        // Generate payment ID and transaction reference
        $paymentId = generateUniqueId('PAY');
        $transactionRef = generateTransactionReference($paymentMethod);
        
        // Process payment based on method
        $paymentResult = processPaymentByMethod($paymentMethod, $amount, $paymentData);
        
        if (!$paymentResult['success']) {
            $db->rollBack();
            handleError($paymentResult['message'], 400);
        }
        
        // Insert payment record
        $stmt = $db->prepare("
            INSERT INTO payments (
                payment_id, booking_id, amount, payment_method, 
                payment_status, transaction_reference, gateway_response, payment_date
            ) VALUES (?, ?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP)
        ");
        
        $stmt->execute([
            $paymentId,
            $booking['id'],
            $amount,
            $paymentMethod,
            'completed',
            $transactionRef,
            json_encode($paymentResult['gateway_response'] ?? [])
        ]);
        
        // Update booking status
        $stmt = $db->prepare("
            UPDATE bookings 
            SET payment_status = 'paid', 
                booking_status = 'confirmed',
                payment_method = ?,
                payment_reference = ?
            WHERE id = ?
        ");
        $stmt->execute([$paymentMethod, $transactionRef, $booking['id']]);
        
        $db->commit();
        
        // Send confirmation email
        sendBookingConfirmation($bookingId);
        
        handleSuccess([
            'payment_id' => $paymentId,
            'booking_id' => $bookingId,
            'transaction_reference' => $transactionRef,
            'amount' => $amount,
            'payment_method' => $paymentMethod,
            'status' => 'completed',
            'payment_date' => date('Y-m-d H:i:s')
        ], 'Payment processed successfully');
        
    } catch (Exception $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        error_log("Process payment error: " . $e->getMessage());
        handleError('Payment processing failed. Please try again.', 500);
    }
}

/**
 * Verify payment status
 */
function handleVerifyPayment() {
    requireLogin();
    
    if (empty($_GET['payment_id']) && empty($_GET['booking_id'])) {
        handleError('Payment ID or Booking ID is required', 400);
    }
    
    $paymentId = sanitizeInput($_GET['payment_id'] ?? '');
    $bookingId = sanitizeInput($_GET['booking_id'] ?? '');
    
    try {
        $db = getDB();
        
        $whereClause = '';
        $params = [];
        
        if ($paymentId) {
            $whereClause = "WHERE p.payment_id = ?";
            $params[] = $paymentId;
        } else {
            $whereClause = "WHERE b.booking_id = ?";
            $params[] = $bookingId;
        }
        
        $stmt = $db->prepare("
            SELECT 
                p.*,
                b.booking_id,
                b.total_amount as booking_amount,
                b.booking_status
            FROM payments p
            JOIN bookings b ON p.booking_id = b.id
            $whereClause
            AND b.user_id = (SELECT id FROM users WHERE user_id = ?)
            ORDER BY p.created_at DESC
            LIMIT 1
        ");
        
        $params[] = $_SESSION['user_id'];
        $stmt->execute($params);
        $payment = $stmt->fetch();
        
        if (!$payment) {
            handleError('Payment not found', 404);
        }
        
        $gatewayResponse = json_decode($payment['gateway_response'], true) ?: [];
        
        $result = [
            'payment_id' => $payment['payment_id'],
            'booking_id' => $payment['booking_id'],
            'amount' => (float)$payment['amount'],
            'payment_method' => $payment['payment_method'],
            'payment_status' => $payment['payment_status'],
            'transaction_reference' => $payment['transaction_reference'],
            'payment_date' => $payment['payment_date'],
            'refund_amount' => (float)$payment['refund_amount'],
            'refund_date' => $payment['refund_date'],
            'booking_status' => $payment['booking_status'],
            'gateway_response' => $gatewayResponse
        ];
        
        handleSuccess($result);
        
    } catch (Exception $e) {
        error_log("Verify payment error: " . $e->getMessage());
        handleError('Payment verification failed', 500);
    }
}

/**
 * Get available payment methods
 */
function handleGetPaymentMethods() {
    $paymentMethods = [
        [
            'id' => 'telebirr',
            'name' => 'Telebirr',
            'description' => 'Pay with Telebirr mobile money',
            'icon' => 'fas fa-mobile-alt',
            'is_active' => true,
            'processing_fee' => 0.00,
            'min_amount' => 10.00,
            'max_amount' => 50000.00
        ],
        [
            'id' => 'cbe',
            'name' => 'CBE Bank',
            'description' => 'Commercial Bank of Ethiopia',
            'icon' => 'fas fa-university',
            'is_active' => true,
            'processing_fee' => 5.00,
            'min_amount' => 50.00,
            'max_amount' => 100000.00
        ],
        [
            'id' => 'dashen',
            'name' => 'Dashen Bank',
            'description' => 'Dashen Bank payment gateway',
            'icon' => 'fas fa-credit-card',
            'is_active' => true,
            'processing_fee' => 3.00,
            'min_amount' => 50.00,
            'max_amount' => 75000.00
        ],
        [
            'id' => 'card',
            'name' => 'Credit/Debit Card',
            'description' => 'Visa, MasterCard, American Express',
            'icon' => 'fas fa-credit-card',
            'is_active' => true,
            'processing_fee' => 2.5, // Percentage
            'min_amount' => 10.00,
            'max_amount' => 200000.00
        ]
    ];
    
    handleSuccess($paymentMethods);
}

/**
 * Get payment status for a booking
 */
function handleGetPaymentStatus() {
    requireLogin();
    
    if (empty($_GET['booking_id'])) {
        handleError('Booking ID is required', 400);
    }
    
    $bookingId = sanitizeInput($_GET['booking_id']);
    
    try {
        $db = getDB();
        
        $stmt = $db->prepare("
            SELECT 
                b.booking_id,
                b.payment_status,
                b.payment_method,
                b.payment_reference,
                b.total_amount,
                p.payment_id,
                p.payment_status as payment_detail_status,
                p.transaction_reference,
                p.payment_date,
                p.refund_amount,
                p.refund_date
            FROM bookings b
            LEFT JOIN payments p ON b.id = p.booking_id
            WHERE b.booking_id = ? AND b.user_id = (SELECT id FROM users WHERE user_id = ?)
        ");
        $stmt->execute([$bookingId, $_SESSION['user_id']]);
        $result = $stmt->fetch();
        
        if (!$result) {
            handleError('Booking not found', 404);
        }
        
        $paymentStatus = [
            'booking_id' => $result['booking_id'],
            'payment_status' => $result['payment_status'],
            'payment_method' => $result['payment_method'],
            'total_amount' => (float)$result['total_amount'],
            'payment_details' => null
        ];
        
        if ($result['payment_id']) {
            $paymentStatus['payment_details'] = [
                'payment_id' => $result['payment_id'],
                'status' => $result['payment_detail_status'],
                'transaction_reference' => $result['transaction_reference'],
                'payment_date' => $result['payment_date'],
                'refund_amount' => (float)$result['refund_amount'],
                'refund_date' => $result['refund_date']
            ];
        }
        
        handleSuccess($paymentStatus);
        
    } catch (Exception $e) {
        error_log("Get payment status error: " . $e->getMessage());
        handleError('Failed to fetch payment status', 500);
    }
}

/**
 * Process refund for a payment
 */
function handleRefundPayment() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        handleError('Method not allowed', 405);
    }
    
    requireLogin();
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (empty($input['payment_id'])) {
        handleError('Payment ID is required', 400);
    }
    
    $paymentId = sanitizeInput($input['payment_id']);
    $refundAmount = (float)($input['refund_amount'] ?? 0);
    $reason = sanitizeInput($input['reason'] ?? 'User requested refund');
    
    try {
        $db = getDB();
        $db->beginTransaction();
        
        // Get payment details
        $stmt = $db->prepare("
            SELECT p.*, b.booking_id, b.user_id 
            FROM payments p 
            JOIN bookings b ON p.booking_id = b.id 
            WHERE p.payment_id = ? AND b.user_id = (SELECT id FROM users WHERE user_id = ?)
        ");
        $stmt->execute([$paymentId, $_SESSION['user_id']]);
        $payment = $stmt->fetch();
        
        if (!$payment) {
            $db->rollBack();
            handleError('Payment not found', 404);
        }
        
        if ($payment['payment_status'] !== 'completed') {
            $db->rollBack();
            handleError('Cannot refund incomplete payment', 400);
        }
        
        if ($payment['refund_amount'] > 0) {
            $db->rollBack();
            handleError('Payment has already been refunded', 400);
        }
        
        // Validate refund amount
        if ($refundAmount <= 0 || $refundAmount > $payment['amount']) {
            $refundAmount = $payment['amount']; // Full refund
        }
        
        // Process refund (this would integrate with actual payment gateway)
        $refundResult = processRefund($payment['payment_method'], $payment['transaction_reference'], $refundAmount);
        
        if (!$refundResult['success']) {
            $db->rollBack();
            handleError($refundResult['message'], 400);
        }
        
        // Update payment record
        $stmt = $db->prepare("
            UPDATE payments 
            SET payment_status = 'refunded',
                refund_amount = ?,
                refund_date = CURRENT_TIMESTAMP
            WHERE id = ?
        ");
        $stmt->execute([$refundAmount, $payment['id']]);
        
        // Update booking status
        $stmt = $db->prepare("
            UPDATE bookings 
            SET payment_status = 'refunded'
            WHERE id = ?
        ");
        $stmt->execute([$payment['booking_id']]);
        
        $db->commit();
        
        handleSuccess([
            'payment_id' => $paymentId,
            'refund_amount' => $refundAmount,
            'refund_date' => date('Y-m-d H:i:s'),
            'status' => 'refunded'
        ], 'Refund processed successfully');
        
    } catch (Exception $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        error_log("Process refund error: " . $e->getMessage());
        handleError('Refund processing failed. Please try again.', 500);
    }
}

/**
 * Process payment by method (simulation for demo)
 */
function processPaymentByMethod($method, $amount, $paymentData) {
    // This is a simulation for demo purposes
    // In production, integrate with actual payment gateways
    
    switch ($method) {
        case 'telebirr':
            return processTelebirrPayment($amount, $paymentData);
        case 'cbe':
            return processCBEPayment($amount, $paymentData);
        case 'dashen':
            return processDashenPayment($amount, $paymentData);
        case 'card':
            return processCardPayment($amount, $paymentData);
        default:
            return ['success' => false, 'message' => 'Unsupported payment method'];
    }
}

/**
 * Simulate Telebirr payment
 */
function processTelebirrPayment($amount, $data) {
    // Simulate payment processing
    sleep(1); // Simulate processing time
    
    // For demo, always succeed
    return [
        'success' => true,
        'message' => 'Payment successful',
        'gateway_response' => [
            'gateway' => 'telebirr',
            'status' => 'success',
            'reference' => 'TB' . time() . rand(1000, 9999),
            'phone' => $data['phone'] ?? '+251911234567'
        ]
    ];
}

/**
 * Simulate CBE payment
 */
function processCBEPayment($amount, $data) {
    sleep(1);
    
    return [
        'success' => true,
        'message' => 'Payment successful',
        'gateway_response' => [
            'gateway' => 'cbe',
            'status' => 'success',
            'reference' => 'CBE' . time() . rand(1000, 9999),
            'account' => $data['account'] ?? '****6789'
        ]
    ];
}

/**
 * Simulate Dashen payment
 */
function processDashenPayment($amount, $data) {
    sleep(1);
    
    return [
        'success' => true,
        'message' => 'Payment successful',
        'gateway_response' => [
            'gateway' => 'dashen',
            'status' => 'success',
            'reference' => 'DSH' . time() . rand(1000, 9999),
            'card' => $data['card_number'] ?? '****1111'
        ]
    ];
}

/**
 * Simulate card payment
 */
function processCardPayment($amount, $data) {
    sleep(1);
    
    return [
        'success' => true,
        'message' => 'Payment successful',
        'gateway_response' => [
            'gateway' => 'card',
            'status' => 'success',
            'reference' => 'CARD' . time() . rand(1000, 9999),
            'card' => $data['card_number'] ?? '****4444',
            'auth_code' => rand(100000, 999999)
        ]
    ];
}

/**
 * Generate transaction reference
 */
function generateTransactionReference($method) {
    $prefix = strtoupper(substr($method, 0, 3));
    return $prefix . date('Ymd') . rand(100000, 999999);
}

/**
 * Process refund (simulation)
 */
function processRefund($method, $originalRef, $amount) {
    // Simulate refund processing
    sleep(1);
    
    return [
        'success' => true,
        'message' => 'Refund processed successfully',
        'refund_reference' => 'REF' . time() . rand(1000, 9999)
    ];
}
