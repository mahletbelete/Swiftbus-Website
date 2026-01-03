<?php
/**
 * SwiftBus Admin Authentication API
 * 
 * Handles admin authentication, profile management, and admin-specific operations
 * Following the same successful patterns as api/auth.php
 */

// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: ' . ($_SERVER['HTTP_ORIGIN'] ?? '*'));
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Access-Control-Allow-Credentials: true');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    exit(0);
}

require_once '../includes/functions.php';

$action = $_GET['action'] ?? $_POST['action'] ?? '';

switch ($action) {
    case 'check_session':
        handleCheckAdminSession();
        break;
    case 'update_profile':
        handleUpdateAdminProfile();
        break;
    case 'change_password':
        handleChangeAdminPassword();
        break;
    case 'upload_avatar':
        handleUploadAdminAvatar();
        break;
    case 'get_profile':
        handleGetAdminProfile();
        break;
    default:
        handleError('Invalid action', 400);
}

/**
 * Check admin session (following auth.php pattern)
 */
function handleCheckAdminSession() {
    // Enhanced session debugging
    $sessionData = [
        'session_id' => session_id(),
        'session_status' => session_status(),
        'session_data' => $_SESSION ?? [],
        'cookies' => $_COOKIE ?? []
    ];
    
    error_log("Admin session check - Data: " . print_r($sessionData, true));
    
    if (!isLoggedIn()) {
        error_log("Admin session check failed - not logged in");
        handleError('Not authenticated. Please log in again.', 401, [
            'error_code' => 'SESSION_EXPIRED',
            'redirect' => 'login.html',
            'debug' => [
                'session_exists' => session_id() ? true : false,
                'user_id_in_session' => isset($_SESSION['user_id']),
                'session_data_count' => count($_SESSION ?? [])
            ]
        ]);
    }
    
    try {
        $user = getCurrentUser();
        
        if (!$user) {
            // Clear invalid session
            session_unset();
            session_destroy();
            error_log("Admin session check failed - user not found in database");
            handleError('User account not found. Please log in again.', 401, [
                'error_code' => 'USER_NOT_FOUND',
                'redirect' => 'login.html'
            ]);
        }
        
        // Check if user is admin
        if ($user['role'] !== 'admin') {
            error_log("Admin session check failed - user is not admin: " . $user['role']);
            handleError('Admin access required.', 403, [
                'error_code' => 'INSUFFICIENT_PRIVILEGES',
                'redirect' => 'user-dashboard.html'
            ]);
        }
        
        // Return enhanced admin data
        $adminData = [
            'id' => $user['user_id'],
            'user_id' => $user['user_id'], // Add this for compatibility
            'email' => $user['email'],
            'name' => $user['full_name'],
            'full_name' => $user['full_name'], // Add this for compatibility
            'firstName' => $user['first_name'],
            'lastName' => $user['last_name'],
            'role' => $user['role'],
            'phone' => $user['phone'] ?: 'Not provided',
            'joinedDate' => $user['joined_date'],
            'isVerified' => (bool)$user['is_verified'],
            'profileImage' => $user['profile_image'],
            'session_valid' => true,
            'last_login' => $user['last_login'],
            'is_admin' => true
        ];
        
        error_log("Admin session check successful for user: " . $user['user_id']);
        handleSuccess($adminData, 'Admin session valid');
        
    } catch (Exception $e) {
        error_log("Admin session check error: " . $e->getMessage());
        handleError('Session check failed: ' . $e->getMessage(), 500);
    }
}

/**
 * Get admin profile data
 */
function handleGetAdminProfile() {
    if (!isLoggedIn()) {
        handleError('Authentication required', 401);
    }
    
    if (!isAdmin()) {
        handleError('Admin access required', 403);
    }
    
    try {
        $user = getCurrentUser();
        
        if (!$user) {
            handleError('Admin not found', 404);
        }
        
        // Return admin profile data
        $adminData = [
            'id' => $user['user_id'],
            'email' => $user['email'],
            'name' => $user['full_name'],
            'firstName' => $user['first_name'],
            'lastName' => $user['last_name'],
            'role' => $user['role'],
            'phone' => $user['phone'] ?: 'Not provided',
            'joinedDate' => $user['joined_date'],
            'isVerified' => (bool)$user['is_verified'],
            'profileImage' => $user['profile_image'],
            'is_admin' => true
        ];
        
        handleSuccess($adminData, 'Admin profile retrieved successfully');
        
    } catch (Exception $e) {
        error_log("Get admin profile error: " . $e->getMessage());
        handleError('Failed to retrieve admin profile', 500);
    }
}

/**
 * Handle admin profile update (following auth.php pattern)
 */
function handleUpdateAdminProfile() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        handleError('Method not allowed', 405);
    }
    
    if (!isLoggedIn()) {
        handleError('Authentication required', 401);
    }
    
    if (!isAdmin()) {
        handleError('Admin access required', 403);
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    // Validate required fields
    if (empty($input['firstName']) || empty($input['lastName'])) {
        handleError('First name and last name are required', 400);
    }
    
    $firstName = sanitizeInput($input['firstName']);
    $lastName = sanitizeInput($input['lastName']);
    $phone = sanitizeInput($input['phone'] ?? '');
    $dob = sanitizeInput($input['dob'] ?? '');
    $address = sanitizeInput($input['address'] ?? '');
    $city = sanitizeInput($input['city'] ?? '');
    $country = sanitizeInput($input['country'] ?? 'Ethiopia');
    $emergencyContact = sanitizeInput($input['emergencyContact'] ?? '');
    
    // Validate phone if provided
    if (!empty($phone) && !validatePhone($phone)) {
        handleError('Invalid phone number format', 400);
    }
    
    // Validate emergency contact if provided
    if (!empty($emergencyContact) && !validatePhone($emergencyContact)) {
        handleError('Invalid emergency contact number format', 400);
    }
    
    try {
        $db = getDB();
        
        // Get current admin
        $stmt = $db->prepare("SELECT * FROM users WHERE user_id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch();
        
        if (!$user) {
            handleError('Admin not found', 404);
        }
        
        // Update admin profile
        $fullName = $firstName . ' ' . $lastName;
        
        $stmt = $db->prepare("
            UPDATE users SET 
                first_name = ?, 
                last_name = ?, 
                full_name = ?, 
                phone = ?, 
                updated_at = CURRENT_TIMESTAMP 
            WHERE user_id = ?
        ");
        
        $stmt->execute([$firstName, $lastName, $fullName, $phone, $_SESSION['user_id']]);
        
        // Update session data
        $_SESSION['user_name'] = $fullName;
        
        // Log the profile update activity
        try {
            logActivity($_SESSION['user_id'], 'admin_profile_updated', 'user', $user['id'], [
                'updated_fields' => ['firstName', 'lastName', 'phone'],
                'updated_at' => date('Y-m-d H:i:s')
            ]);
        } catch (Exception $logError) {
            error_log("Failed to log admin profile update activity: " . $logError->getMessage());
        }
        
        // Return updated admin data
        $adminData = [
            'id' => $user['user_id'],
            'email' => $user['email'],
            'name' => $fullName,
            'firstName' => $firstName,
            'lastName' => $lastName,
            'role' => $user['role'],
            'phone' => $phone ?: 'Not provided',
            'dob' => $dob,
            'address' => $address,
            'city' => $city,
            'country' => $country,
            'emergencyContact' => $emergencyContact,
            'joinedDate' => $user['joined_date'],
            'isVerified' => (bool)$user['is_verified'],
            'profileImage' => $user['profile_image'],
            'is_admin' => true
        ];
        
        handleSuccess($adminData, 'Admin profile updated successfully');
        
    } catch (Exception $e) {
        error_log("Admin profile update error: " . $e->getMessage());
        handleError('Profile update failed. Please try again.', 500);
    }
}

/**
 * Handle admin password change (following auth.php pattern)
 */
function handleChangeAdminPassword() {
    // Log the start of password change attempt
    error_log("Admin password change attempt started");
    
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        error_log("Admin password change failed: Wrong request method");
        handleError('Method not allowed', 405);
    }
    
    if (!isLoggedIn()) {
        error_log("Admin password change failed: User not logged in");
        handleError('Authentication required', 401);
    }
    
    if (!isAdmin()) {
        error_log("Admin password change failed: User is not admin");
        handleError('Admin access required', 403);
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    error_log("Admin password change input received: " . json_encode(array_keys($input ?: [])));
    
    // Validate required fields
    if (empty($input['currentPassword']) || empty($input['newPassword'])) {
        error_log("Admin password change failed: Missing required fields");
        handleError('Current password and new password are required', 400);
    }
    
    $currentPassword = $input['currentPassword'];
    $newPassword = $input['newPassword'];
    
    error_log("Admin password lengths - Current: " . strlen($currentPassword) . ", New: " . strlen($newPassword));
    
    // Validate new password strength
    if (strlen($newPassword) < 8) {
        error_log("Admin password change failed: New password too short");
        handleError('New password must be at least 8 characters long', 400);
    }
    
    // Check for at least one letter and one number (more flexible than the original)
    if (!preg_match('/[a-zA-Z]/', $newPassword)) {
        error_log("Admin password change failed: New password missing letter");
        handleError('New password must contain at least one letter', 400);
    }
    
    if (!preg_match('/\d/', $newPassword)) {
        error_log("Admin password change failed: New password missing number");
        handleError('New password must contain at least one number', 400);
    }
    
    try {
        $db = getDB();
        error_log("Database connection established for admin password change");
        
        // Get current admin data
        $stmt = $db->prepare("SELECT * FROM users WHERE user_id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch();
        
        if (!$user) {
            error_log("Admin password change failed: User not found for user_id: " . $_SESSION['user_id']);
            handleError('Admin not found', 404);
        }
        
        error_log("Admin found for password change: " . $user['email']);
        
        // Verify current password
        $passwordVerified = verifyPassword($currentPassword, $user['password_hash']);
        error_log("Current password verification result: " . ($passwordVerified ? 'true' : 'false'));
        
        if (!$passwordVerified) {
            error_log("Admin password change failed: Current password incorrect");
            handleError('Current password is incorrect', 401);
        }
        
        // Hash new password
        $newPasswordHash = hashPassword($newPassword);
        error_log("New password hashed successfully");
        
        // Update password in database
        $stmt = $db->prepare("UPDATE users SET password_hash = ?, updated_at = CURRENT_TIMESTAMP WHERE user_id = ?");
        $updateResult = $stmt->execute([$newPasswordHash, $_SESSION['user_id']]);
        $affectedRows = $stmt->rowCount();
        
        error_log("Admin password update result: " . ($updateResult ? 'true' : 'false') . ", Affected rows: " . $affectedRows);
        
        if (!$updateResult || $affectedRows === 0) {
            error_log("Admin password change failed: Database update failed");
            handleError('Failed to update password in database', 500);
        }
        
        // Log the password change activity
        try {
            logActivity($_SESSION['user_id'], 'admin_password_changed', 'user', $user['id'], [
                'changed_at' => date('Y-m-d H:i:s'),
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
            ]);
            error_log("Admin password change activity logged successfully");
        } catch (Exception $logError) {
            error_log("Failed to log admin password change activity: " . $logError->getMessage());
            // Don't fail the password change if logging fails
        }
        
        error_log("Admin password change completed successfully for user: " . $user['email']);
        handleSuccess([], 'Admin password changed successfully');
        
    } catch (Exception $e) {
        error_log("Admin password change error: " . $e->getMessage());
        error_log("Admin password change stack trace: " . $e->getTraceAsString());
        handleError('Password change failed. Please try again.', 500);
    }
}

/**
 * Handle admin avatar upload (following auth.php pattern)
 */
function handleUploadAdminAvatar() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        handleError('Method not allowed', 405);
    }
    
    if (!isLoggedIn()) {
        handleError('Authentication required', 401);
    }
    
    if (!isAdmin()) {
        handleError('Admin access required', 403);
    }
    
    // Check if file was uploaded
    if (!isset($_FILES['avatar']) || $_FILES['avatar']['error'] !== UPLOAD_ERR_OK) {
        handleError('No file uploaded or upload error', 400);
    }
    
    $file = $_FILES['avatar'];
    
    // Validate file type
    $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    if (!in_array($file['type'], $allowedTypes)) {
        handleError('Invalid file type. Only JPEG, PNG, GIF, and WebP are allowed', 400);
    }
    
    // Validate file size (max 5MB)
    $maxSize = 5 * 1024 * 1024; // 5MB
    if ($file['size'] > $maxSize) {
        handleError('File size too large. Maximum size is 5MB', 400);
    }
    
    try {
        // Create uploads directory if it doesn't exist
        $uploadDir = '../uploads/avatars/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        
        // Generate unique filename
        $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename = $_SESSION['user_id'] . '_admin_' . time() . '.' . $extension;
        $filepath = $uploadDir . $filename;
        
        // Move uploaded file
        if (!move_uploaded_file($file['tmp_name'], $filepath)) {
            handleError('Failed to save uploaded file', 500);
        }
        
        // Update admin profile image in database
        $db = getDB();
        $profileImagePath = 'uploads/avatars/' . $filename;
        
        $stmt = $db->prepare("UPDATE users SET profile_image = ?, updated_at = CURRENT_TIMESTAMP WHERE user_id = ?");
        $stmt->execute([$profileImagePath, $_SESSION['user_id']]);
        
        // Log the avatar upload activity
        try {
            logActivity($_SESSION['user_id'], 'admin_avatar_uploaded', 'user', null, [
                'filename' => $filename,
                'uploaded_at' => date('Y-m-d H:i:s')
            ]);
        } catch (Exception $logError) {
            error_log("Failed to log admin avatar upload activity: " . $logError->getMessage());
        }
        
        handleSuccess([
            'profileImage' => $profileImagePath,
            'filename' => $filename
        ], 'Admin avatar uploaded successfully');
        
    } catch (Exception $e) {
        error_log("Admin avatar upload error: " . $e->getMessage());
        handleError('Avatar upload failed. Please try again.', 500);
    }
}
?>