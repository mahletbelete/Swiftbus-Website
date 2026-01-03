<?php
/**
 * Simple API Test Endpoint
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

try {
    require_once '../includes/functions.php';
    
    $db = getDB();
    $stmt = $db->query("SELECT COUNT(*) as count FROM users");
    $result = $stmt->fetch();
    
    echo json_encode([
        'success' => true,
        'message' => 'API working correctly',
        'data' => [
            'users_count' => $result['count'],
            'timestamp' => date('Y-m-d H:i:s')
        ]
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'API Error: ' . $e->getMessage()
    ]);
}
?>