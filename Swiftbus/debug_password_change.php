<?php
/**
 * Debug Password Change API
 * 
 * This file helps debug password change issues
 */

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    exit(0);
}

require_once 'includes/functions.php';

$action = $_GET['action'] ?? $_POST['action'] ?? '';

switch ($action) {
    case 'debug_password_change':
        debugPasswordChange();
        break;
    case 'test_session':
        testSession();
        break;
    case 'test_user_data':
        testUserData();
        break;
    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
}

function debugPasswordChange() {
    $debug = [];
    
    try {
        // Check request method
        $debug['request_method'] = $_SERVER['REQUEST_METHOD'];
        
        // Check if user is logged in
        $debug['is_logged_in'] = isLoggedIn();
        $debug['session_data'] = $_SESSION ?? [];
        
        if (!isLoggedIn()) {
            echo json_encode(['success' => false, 'debug' => $debug, 'message' => 'Not logged in']);
            return;
        }
        
        // Get input data
        $input = json_decode(file_get_contents('php://input'), true);
        $debug['input_data'] = $input;
        
        if (empty($input['currentPassword']) || empty($input['newPassword'])) {
            echo json_encode(['success' => false, 'debug' => $debug, 'message' => 'Missing password fields']);
            return;
        }
        
        $currentPassword = $input['currentPassword'];
        $newPassword = $input['newPassword'];
        
        $debug['current_password_length'] = strlen($currentPassword);
        $debug['new_password_length'] = strlen($newPassword);
        
        // Test database connection
        $db = getDB();
        $debug['database_connected'] = $db ? true : false;
        
        // Get current user data
        $stmt = $db->prepare("SELECT * FROM users WHERE user_id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch();
        
        $debug['user_found'] = $user ? true : false;
        if ($user) {
            $debug['user_data'] = [
                'id' => $user['id'],
                'user_id' => $user['user_id'],
                'email' => $user['email'],
                'has_password_hash' => !empty($user['password_hash'])
            ];
        }
        
        if (!$user) {
            echo json_encode(['success' => false, 'debug' => $debug, 'message' => 'User not found']);
            return;
        }
        
        // Test password verification
        $debug['password_verify_result'] = verifyPassword($currentPassword, $user['password_hash']);
        
        if (!verifyPassword($currentPassword, $user['password_hash'])) {
            echo json_encode(['success' => false, 'debug' => $debug, 'message' => 'Current password is incorrect']);
            return;
        }
        
        // Test password hashing
        $newPasswordHash = hashPassword($newPassword);
        $debug['new_password_hashed'] = !empty($newPasswordHash);
        
        // Test update query
        $stmt = $db->prepare("UPDATE users SET password_hash = ?, updated_at = CURRENT_TIMESTAMP WHERE user_id = ?");
        $updateResult = $stmt->execute([$newPasswordHash, $_SESSION['user_id']]);
        $debug['update_result'] = $updateResult;
        $debug['affected_rows'] = $stmt->rowCount();
        
        if ($updateResult) {
            echo json_encode(['success' => true, 'debug' => $debug, 'message' => 'Password change successful']);
        } else {
            echo json_encode(['success' => false, 'debug' => $debug, 'message' => 'Database update failed']);
        }
        
    } catch (Exception $e) {
        $debug['exception'] = $e->getMessage();
        $debug['exception_trace'] = $e->getTraceAsString();
        echo json_encode(['success' => false, 'debug' => $debug, 'message' => 'Exception: ' . $e->getMessage()]);
    }
}

function testSession() {
    echo json_encode([
        'success' => true,
        'session_status' => session_status(),
        'session_data' => $_SESSION ?? [],
        'is_logged_in' => isLoggedIn(),
        'current_user_id' => getCurrentUserId()
    ]);
}

function testUserData() {
    try {
        if (!isLoggedIn()) {
            echo json_encode(['success' => false, 'message' => 'Not logged in']);
            return;
        }
        
        $user = getCurrentUser();
        echo json_encode([
            'success' => true,
            'user_data' => $user,
            'session_user_id' => $_SESSION['user_id'] ?? null
        ]);
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}
?>