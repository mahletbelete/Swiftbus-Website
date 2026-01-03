<?php
/**
 * Test Current Password Verification
 */

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    exit(0);
}

require_once 'includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!isLoggedIn()) {
        echo json_encode(['success' => false, 'message' => 'Not logged in']);
        exit;
    }
    
    if (empty($input['password'])) {
        echo json_encode(['success' => false, 'message' => 'Password required']);
        exit;
    }
    
    try {
        $db = getDB();
        $stmt = $db->prepare("SELECT * FROM users WHERE user_id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch();
        
        if (!$user) {
            echo json_encode(['success' => false, 'message' => 'User not found']);
            exit;
        }
        
        $passwordMatch = verifyPassword($input['password'], $user['password_hash']);
        
        echo json_encode([
            'success' => true,
            'password_match' => $passwordMatch,
            'user_id' => $user['user_id'],
            'email' => $user['email'],
            'has_password_hash' => !empty($user['password_hash']),
            'password_hash_length' => strlen($user['password_hash']),
            'input_password_length' => strlen($input['password'])
        ]);
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'POST method required']);
}
?>