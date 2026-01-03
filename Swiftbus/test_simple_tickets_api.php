<?php
/**
 * Simplified test of the tickets API to isolate the issue
 */
error_reporting(E_ALL);
ini_set('display_errors', 0); // Don't display errors in output
ini_set('log_errors', 1);     // Log errors to file

// Set JSON header
header('Content-Type: application/json');

// Start session
session_start();

// Include functions
require_once 'includes/functions.php';

try {
    // Check if user is logged in
    if (!isLoggedIn()) {
        echo json_encode([
            'success' => false,
            'message' => 'Not logged in',
            'step' => 'authentication_check'
        ]);
        exit;
    }
    
    $userId = getCurrentUserId();
    if (!$userId) {
        echo json_encode([
            'success' => false,
            'message' => 'No user ID in session',
            'step' => 'get_user_id'
        ]);
        exit;
    }
    
    // Get database connection
    $db = getDB();
    
    // Check if user exists
    $userCheck = $db->prepare("SELECT id FROM users WHERE user_id = ? AND is_active = 1");
    $userCheck->execute([$userId]);
    $userRecord = $userCheck->fetch();
    
    if (!$userRecord) {
        echo json_encode([
            'success' => false,
            'message' => 'User not found in database',
            'step' => 'user_verification',
            'user_id' => $userId
        ]);
        exit;
    }
    
    // Simple query to get bookings
    $stmt = $db->prepare("
        SELECT 
            booking_id,
            bus_company,
            from_city,
            to_city,
            travel_date,
            total_amount,
            booking_status
        FROM bookings 
        WHERE user_id = (SELECT id FROM users WHERE user_id = ?)
        ORDER BY created_at DESC
        LIMIT 5
    ");
    
    $stmt->execute([$userId]);
    $bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Return simple response
    echo json_encode([
        'success' => true,
        'message' => 'API test successful',
        'data' => [
            'bookings' => $bookings,
            'total' => count($bookings),
            'user_id' => $userId,
            'steps_completed' => 7
        ]
    ]);
    
} catch (Exception $e) {
    error_log("Simple tickets API error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Exception: ' . $e->getMessage(),
        'step' => 'exception_handler',
        'file' => basename($e->getFile()),
        'line' => $e->getLine()
    ]);
}
?>