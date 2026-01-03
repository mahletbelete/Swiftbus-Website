<?php
/**
 * Simple API Test - Isolate the 500 error
 */
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Set JSON header
header('Content-Type: application/json');

echo json_encode([
    'test' => 'Simple API test',
    'timestamp' => date('Y-m-d H:i:s'),
    'success' => true
]);
?>