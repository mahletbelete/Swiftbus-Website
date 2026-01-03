<?php
/**
 * Debug script for admin_users.php API
 * This helps identify what's causing the JSON parsing issue
 * Access this file directly in browser: http://localhost/bus/api/test_admin_users_debug.php
 */

// Start session
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Set JSON header
header('Content-Type: application/json');

$debug = [
    'php_version' => PHP_VERSION,
    'session_status' => session_status(),
    'session_data' => [
        'user_id' => $_SESSION['user_id'] ?? 'NOT SET',
        'user_role' => $_SESSION['user_role'] ?? 'NOT SET',
        'user_email' => $_SESSION['user_email'] ?? 'NOT SET'
    ]
];

// Try to load functions
try {
    require_once '../includes/functions.php';
    $debug['functions_loaded'] = true;
    
    // Check if getDB works
    try {
        $pdo = getDB();
        $debug['db_connection'] = $pdo ? 'SUCCESS' : 'FAILED (null)';
        
        if ($pdo) {
            // Try a simple query
            $stmt = $pdo->query("SELECT COUNT(*) as count FROM users WHERE role = 'user'");
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $debug['user_count'] = $result['count'];
            
            // Get sample user data
            $stmt = $pdo->query("SELECT user_id, email, full_name, role, is_active FROM users WHERE role = 'user' LIMIT 3");
            $debug['sample_users'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Get all users count
            $stmt = $pdo->query("SELECT COUNT(*) as count FROM users");
            $debug['total_users_count'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
        }
    } catch (Exception $e) {
        $debug['db_error'] = $e->getMessage();
    }
    
    // Check auth functions
    $debug['isLoggedIn'] = isLoggedIn();
    $debug['isAdmin'] = isAdmin();
    
} catch (Exception $e) {
    $debug['functions_error'] = $e->getMessage();
}

echo json_encode([
    'success' => true,
    'debug' => $debug
], JSON_PRETTY_PRINT);
