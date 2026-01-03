<?php
/**
 * Test Database Connection and User Retrieval
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: ' . ($_SERVER['HTTP_ORIGIN'] ?? '*'));
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Access-Control-Allow-Credentials: true');

require_once 'includes/functions.php';

try {
    // Test database connection
    $db = getDB();
    
    // Test admin user retrieval
    $stmt = $db->prepare("SELECT * FROM users WHERE email = ? AND is_active = 1");
    $stmt->execute(['mubarekali974@gmail.com']);
    $user = $stmt->fetch();
    
    if ($user) {
        // Test password verification
        $passwordValid = verifyPassword('Admin@123', $user['password_hash']);
        
        echo json_encode([
            'success' => true,
            'message' => 'Database test completed',
            'data' => [
                'database_connected' => true,
                'user_found' => true,
                'user_id' => $user['user_id'],
                'user_email' => $user['email'],
                'user_name' => $user['full_name'],
                'user_role' => $user['role'],
                'user_active' => (bool)$user['is_active'],
                'password_valid' => $passwordValid,
                'user_data_complete' => !empty($user['user_id']) && !empty($user['email']) && !empty($user['role'])
            ]
        ], JSON_PRETTY_PRINT);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Admin user not found in database',
            'data' => [
                'database_connected' => true,
                'user_found' => false
            ]
        ], JSON_PRETTY_PRINT);
    }
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Database test failed: ' . $e->getMessage(),
        'data' => [
            'database_connected' => false,
            'error' => $e->getMessage()
        ]
    ], JSON_PRETTY_PRINT);
}
?>