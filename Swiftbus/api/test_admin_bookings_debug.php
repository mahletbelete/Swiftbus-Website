<?php
/**
 * Debug script for admin_bookings.php API
 */

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');

$debug = [
    'php_version' => PHP_VERSION,
    'session_status' => session_status(),
    'session_data' => [
        'user_id' => $_SESSION['user_id'] ?? 'NOT SET',
        'user_role' => $_SESSION['user_role'] ?? 'NOT SET'
    ]
];

try {
    require_once '../includes/functions.php';
    $debug['functions_loaded'] = true;
    
    try {
        $pdo = getDB();
        $debug['db_connection'] = $pdo ? 'SUCCESS' : 'FAILED (null)';
        
        if ($pdo) {
            // Check bookings table structure
            $stmt = $pdo->query("DESCRIBE bookings");
            $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
            $debug['bookings_columns'] = $columns;
            
            // Count bookings
            $stmt = $pdo->query("SELECT COUNT(*) as count FROM bookings");
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $debug['bookings_count'] = $result['count'];
            
            // Get sample booking data
            $stmt = $pdo->query("SELECT booking_id, user_id, from_city, to_city, travel_date, booking_status, total_amount FROM bookings LIMIT 3");
            $debug['sample_bookings'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    } catch (Exception $e) {
        $debug['db_error'] = $e->getMessage();
    }
    
    $debug['isLoggedIn'] = isLoggedIn();
    $debug['isAdmin'] = isAdmin();
    
} catch (Exception $e) {
    $debug['functions_error'] = $e->getMessage();
}

echo json_encode([
    'success' => true,
    'debug' => $debug
], JSON_PRETTY_PRINT);
