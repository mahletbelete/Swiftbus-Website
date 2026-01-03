<?php
/**
 * Minimal test of booking API to identify 500 error
 */

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);

// Set JSON header first
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    exit(0);
}

// Catch any fatal errors and return JSON
register_shutdown_function(function() {
    $error = error_get_last();
    if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Server error: ' . $error['message'],
            'error_details' => $error
        ]);
    }
});

// Set error handler to catch all errors
set_error_handler(function($severity, $message, $file, $line) {
    // Log the error
    error_log("PHP Error: $message in $file on line $line");
    
    // For fatal errors, return JSON response
    if ($severity === E_ERROR || $severity === E_PARSE) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'PHP Error: ' . $message,
            'file' => basename($file),
            'line' => $line
        ]);
        exit;
    }
});

try {
    // Test 1: Load functions
    echo json_encode(['step' => 1, 'message' => 'Loading functions...']) . "\n";
    require_once '../includes/functions.php';
    echo json_encode(['step' => 1, 'status' => 'success']) . "\n";
    
    // Test 2: Check action
    $action = $_GET['action'] ?? $_POST['action'] ?? '';
    echo json_encode(['step' => 2, 'action' => $action]) . "\n";
    
    if ($action !== 'get_user_bookings') {
        echo json_encode(['success' => false, 'message' => 'Invalid action: ' . $action]);
        exit;
    }
    
    // Test 3: Check authentication
    echo json_encode(['step' => 3, 'message' => 'Checking authentication...']) . "\n";
    
    if (!function_exists('isLoggedIn')) {
        throw new Exception('isLoggedIn function not found');
    }
    
    if (!isLoggedIn()) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Authentication required']);
        exit;
    }
    
    echo json_encode(['step' => 3, 'status' => 'authenticated']) . "\n";
    
    // Test 4: Get user ID
    echo json_encode(['step' => 4, 'message' => 'Getting user ID...']) . "\n";
    
    if (!function_exists('getCurrentUserId')) {
        throw new Exception('getCurrentUserId function not found');
    }
    
    $userId = getCurrentUserId();
    if (!$userId) {
        throw new Exception('Could not get current user ID');
    }
    
    echo json_encode(['step' => 4, 'user_id' => $userId]) . "\n";
    
    // Test 5: Database connection
    echo json_encode(['step' => 5, 'message' => 'Connecting to database...']) . "\n";
    
    if (!function_exists('getDB')) {
        throw new Exception('getDB function not found');
    }
    
    $db = getDB();
    echo json_encode(['step' => 5, 'status' => 'connected']) . "\n";
    
    // Test 6: Simple query
    echo json_encode(['step' => 6, 'message' => 'Testing simple query...']) . "\n";
    
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM bookings WHERE user_id = (SELECT id FROM users WHERE user_id = ?)");
    $stmt->execute([$userId]);
    $count = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo json_encode(['step' => 6, 'booking_count' => $count['count']]) . "\n";
    
    // Test 7: Full query
    echo json_encode(['step' => 7, 'message' => 'Testing full query...']) . "\n";
    
    $stmt = $db->prepare("
        SELECT 
            b.booking_id,
            b.bus_company,
            b.bus_type,
            b.from_city,
            b.to_city,
            b.travel_date as departure_date,
            b.departure_time,
            b.passenger_count,
            b.selected_seats,
            b.passenger_details,
            b.total_amount,
            b.booking_status,
            b.payment_status,
            b.payment_method,
            b.qr_code,
            b.booking_date,
            b.created_at
        FROM bookings b
        WHERE b.user_id = (SELECT id FROM users WHERE user_id = ?)
        ORDER BY b.created_at DESC
        LIMIT 10
    ");
    
    $stmt->execute([$userId]);
    $bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode(['step' => 7, 'raw_bookings' => count($bookings)]) . "\n";
    
    // Test 8: Process bookings
    echo json_encode(['step' => 8, 'message' => 'Processing bookings...']) . "\n";
    
    foreach ($bookings as &$booking) {
        // Decode JSON fields
        if ($booking['selected_seats']) {
            $booking['selected_seats'] = json_decode($booking['selected_seats'], true);
        }
        if ($booking['passenger_details']) {
            $booking['passenger_details'] = json_decode($booking['passenger_details'], true);
        }
        
        // Format dates
        $booking['departure_date'] = date('Y-m-d', strtotime($booking['departure_date']));
        $booking['booking_date'] = date('Y-m-d H:i:s', strtotime($booking['booking_date']));
        
        // Add display names
        $booking['from_city_display'] = getCityDisplayName($booking['from_city']);
        $booking['to_city_display'] = getCityDisplayName($booking['to_city']);
        $booking['bus_company_display'] = getBusCompanyDisplayName($booking['bus_company']);
        $booking['bus_type_display'] = getBusTypeDisplayName($booking['bus_type']);
        $booking['route'] = $booking['from_city_display'] . ' to ' . $booking['to_city_display'];
        
        // Generate QR code if not exists
        if (empty($booking['qr_code'])) {
            $booking['qr_code'] = generateQRData(
                $booking['booking_id'],
                $booking['passenger_details']['fullName'] ?? 'Passenger',
                $booking['from_city'] . '-' . $booking['to_city'],
                $booking['departure_date'],
                $booking['selected_seats'] ?? []
            );
        }
    }
    
    echo json_encode(['step' => 8, 'processed_bookings' => count($bookings)]) . "\n";
    
    // Success response
    $response = [
        'success' => true,
        'data' => [
            'bookings' => $bookings,
            'total' => count($bookings),
            'limit' => 10,
            'offset' => 0,
            'has_more' => false
        ]
    ];
    
    echo json_encode($response);
    
} catch (Exception $e) {
    error_log("Minimal API test error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'trace' => $e->getTraceAsString()
    ]);
}
?>