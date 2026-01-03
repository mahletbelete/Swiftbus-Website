<?php
// Debug API to test admin API functionality
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// Start session
session_start();

// Debug information
$debug_info = [
    'php_version' => phpversion(),
    'session_status' => session_status(),
    'session_id' => session_id(),
    'session_data' => $_SESSION ?? [],
    'get_params' => $_GET,
    'post_params' => $_POST,
    'server_info' => [
        'REQUEST_METHOD' => $_SERVER['REQUEST_METHOD'],
        'REQUEST_URI' => $_SERVER['REQUEST_URI'],
        'HTTP_HOST' => $_SERVER['HTTP_HOST']
    ]
];

// Check database connection
try {
    require_once '../config/database.php';
    $debug_info['database_connection'] = 'SUCCESS';
    
    // Test basic query
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM users");
    $result = $stmt->fetch();
    $debug_info['users_count'] = $result['count'];
    
    // Check if routes table exists
    $stmt = $pdo->query("SHOW TABLES LIKE 'routes'");
    $debug_info['routes_table_exists'] = $stmt->rowCount() > 0;
    
    // Check if buses table exists
    $stmt = $pdo->query("SHOW TABLES LIKE 'buses'");
    $debug_info['buses_table_exists'] = $stmt->rowCount() > 0;
    
    // Check if bookings table exists
    $stmt = $pdo->query("SHOW TABLES LIKE 'bookings'");
    $debug_info['bookings_table_exists'] = $stmt->rowCount() > 0;
    
} catch (Exception $e) {
    $debug_info['database_connection'] = 'FAILED';
    $debug_info['database_error'] = $e->getMessage();
}

// Check authentication
if (isset($_SESSION['user_id']) && $_SESSION['role'] === 'admin') {
    $debug_info['admin_authenticated'] = true;
    $debug_info['admin_user_id'] = $_SESSION['user_id'];
    $debug_info['admin_role'] = $_SESSION['role'];
} else {
    $debug_info['admin_authenticated'] = false;
}

echo json_encode([
    'success' => true,
    'message' => 'Admin API Debug Information',
    'data' => $debug_info
], JSON_PRETTY_PRINT);
?>