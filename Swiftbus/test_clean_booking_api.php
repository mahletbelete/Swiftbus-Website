<?php
/**
 * Clean test of the booking API without any debug output
 */

// Clean output buffer
if (ob_get_level()) {
    ob_end_clean();
}

// Start output buffering to catch any unwanted output
ob_start();

// Error handling - log only, don't display
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Set JSON header
header('Content-Type: application/json');

// Start session
session_start();

try {
    // Include functions with correct path
    require_once __DIR__ . '/includes/functions.php';
    
    // Set the action for the API
    $_GET['action'] = 'get_user_bookings';
    $_SERVER['REQUEST_METHOD'] = 'GET';
    
    // Clear any output that might have been generated
    ob_clean();
    
    // Call the function directly instead of including the file
    handleGetUserBookings();
    
} catch (Exception $e) {
    // Clear any output
    ob_clean();
    
    // Return clean JSON error
    echo json_encode([
        'success' => false,
        'message' => 'API test failed: ' . $e->getMessage(),
        'error_type' => 'exception',
        'file' => basename($e->getFile()),
        'line' => $e->getLine()
    ]);
} catch (Error $e) {
    // Clear any output
    ob_clean();
    
    // Return clean JSON error for fatal errors
    echo json_encode([
        'success' => false,
        'message' => 'Fatal error: ' . $e->getMessage(),
        'error_type' => 'fatal',
        'file' => basename($e->getFile()),
        'line' => $e->getLine()
    ]);
}

// End output buffering and send the response
ob_end_flush();
?>