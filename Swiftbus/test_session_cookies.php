<?php
/**
 * Test Session and Cookie Configuration
 */

// Start session
session_start();

header('Content-Type: application/json');

// Get current session configuration
$sessionConfig = [
    'session_id' => session_id(),
    'session_name' => session_name(),
    'session_status' => session_status(),
    'session_save_path' => session_save_path(),
    'session_cookie_params' => session_get_cookie_params(),
    'php_session_id' => $_COOKIE[session_name()] ?? 'NOT SET',
    'all_cookies' => $_COOKIE,
    'session_data' => $_SESSION,
    'server_info' => [
        'HTTP_HOST' => $_SERVER['HTTP_HOST'] ?? 'NOT SET',
        'SERVER_NAME' => $_SERVER['SERVER_NAME'] ?? 'NOT SET',
        'REQUEST_URI' => $_SERVER['REQUEST_URI'] ?? 'NOT SET',
        'HTTP_ORIGIN' => $_SERVER['HTTP_ORIGIN'] ?? 'NOT SET',
        'HTTP_REFERER' => $_SERVER['HTTP_REFERER'] ?? 'NOT SET'
    ]
];

// Test setting a session variable
$_SESSION['test_time'] = date('Y-m-d H:i:s');
$_SESSION['test_counter'] = ($_SESSION['test_counter'] ?? 0) + 1;

echo json_encode([
    'success' => true,
    'message' => 'Session test completed',
    'data' => $sessionConfig
], JSON_PRETTY_PRINT);
?>