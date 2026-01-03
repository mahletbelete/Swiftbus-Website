<?php
/**
 * SwiftBus Authentication API
 * 
 * Handles user registration, login, logout, and authentication
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
    case 'register':
        handleRegister();
        break;
    case 'login':
        handleLogin();
        break;
    case 'logout':
        handleLogout();
        break;
    case 'check_session':
        handleCheckSession();
        break;
    case 'verify_email':
        handleVerifyEmail();
        break;
    case 'change_password':
        handleChangePassword();
        break;
    case 'update_profile':
        handleUpdateProfile();
        break;
    case 'upload_avatar':
        handleUploadAvatar();
        break;
    default:
        handleError('Invalid action', 400);
}

/**
 * Handle user registration
 */
function handleRegister() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        handleError('Method not allowed', 405);
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    // Validate required fields
    $required = ['firstName', 'lastName', 'email', 'password'];
    foreach ($required as $field) {
        if (empty($input[$field])) {
            handleError("Field '$field' is required", 400);
        }
    }
    
    // Sanitize input
    $firstName = sanitizeInput($input['firstName']);
    $lastName = sanitizeInput($input['lastName']);
    $email = sanitizeInput($input['email']);
    $phone = sanitizeInput($input['phone'] ?? '');
    $password = $input['password'];
    
    // Validate email
    if (!validateEmail($email)) {
        handleError('Invalid email address', 400);
    }
    
    // Validate phone if provided
    if (!empty($phone) && !validatePhone($phone)) {
        handleError('Invalid phone number format', 400);
    }
    
    // Validate password strength
    if (strlen($password) < 8) {
        handleError('Password must be at least 8 characters long', 400);
    }
    
    if (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]{8,}$/', $password)) {
        handleError('Password must contain uppercase, lowercase, number, and special character', 400);
    }
    
    try {
        $db = getDB();
        
        // Check if email already exists
        $stmt = $db->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            handleError('Email address already registered', 409);
        }
        
        // Determine role based on email
        $role = isAdminEmail($email) ? 'admin' : 'user';
        
        // Generate user ID
        $userId = generateUniqueId('U');
        
        // Hash password
        $passwordHash = hashPassword($password);
        
        // Insert user
        $stmt = $db->prepare("
            INSERT INTO users (user_id, email, password_hash, first_name, last_name, full_name, phone, role, joined_date) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $fullName = $firstName . ' ' . $lastName;
        $joinedDate = date('Y-m-d');
        
        $stmt->execute([
            $userId, $email, $passwordHash, $firstName, $lastName, 
            $fullName, $phone, $role, $joinedDate
        ]);
        
        // Auto-login the user
        $_SESSION['user_id'] = $userId;
        $_SESSION['user_email'] = $email;
        $_SESSION['user_name'] = $fullName;
        $_SESSION['user_role'] = $role;
        
        // Prepare response data
        $userData = [
            'id' => $userId,
            'email' => $email,
            'name' => $fullName,
            'firstName' => $firstName,
            'lastName' => $lastName,
            'role' => $role,
            'phone' => $phone ?: 'Not provided',
            'joinedDate' => $joinedDate,
            'isVerified' => false
        ];
        
        handleSuccess($userData, 'Account created successfully');
        
    } catch (Exception $e) {
        error_log("Registration error: " . $e->getMessage());
        handleError('Registration failed. Please try again.', 500);
    }
}

/**
 * Handle user login
 */
function handleLogin() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        handleError('Method not allowed', 405);
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    // Validate required fields
    if (empty($input['email']) || empty($input['password'])) {
        handleError('Email and password are required', 400);
    }
    
    $email = sanitizeInput($input['email']);
    $password = $input['password'];
    $rememberMe = $input['rememberMe'] ?? false;
    
    // Validate email format
    if (!validateEmail($email)) {
        handleError('Invalid email address', 400);
    }
    
    try {
        $db = getDB();
        
        // Get user by email
        $stmt = $db->prepare("SELECT * FROM users WHERE email = ? AND is_active = 1");
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        
        if (!$user || !verifyPassword($password, $user['password_hash'])) {
            handleError('Invalid email or password', 401);
        }
        
        // Set session variables
        $_SESSION['user_id'] = $user['user_id'];
        $_SESSION['user_email'] = $user['email'];
        $_SESSION['user_name'] = $user['full_name'];
        $_SESSION['user_role'] = $user['role'];
        
        // Set remember me cookie if requested
        if ($rememberMe) {
            $token = bin2hex(random_bytes(32));
            setcookie('remember_token', $token, time() + (86400 * 30), '/'); // 30 days
            
            // Store token in database (you might want to create a remember_tokens table)
            $stmt = $db->prepare("UPDATE users SET remember_token = ? WHERE id = ?");
            $stmt->execute([$token, $user['id']]);
        }
        
        // Update last login
        $stmt = $db->prepare("UPDATE users SET updated_at = CURRENT_TIMESTAMP WHERE id = ?");
        $stmt->execute([$user['id']]);
        
        // Prepare response data
        $userData = [
            'id' => $user['user_id'],
            'email' => $user['email'],
            'name' => $user['full_name'],
            'firstName' => $user['first_name'],
            'lastName' => $user['last_name'],
            'role' => $user['role'],
            'phone' => $user['phone'] ?: 'Not provided',
            'joinedDate' => $user['joined_date'],
            'isVerified' => (bool)$user['is_verified'],
            'profileImage' => $user['profile_image']
        ];
        
        handleSuccess($userData, 'Login successful');
        
    } catch (Exception $e) {
        error_log("Login error: " . $e->getMessage());
        handleError('Login failed. Please try again.', 500);
    }
}

/**
 * Handle user logout
 */
function handleLogout() {
    try {
        // Clear remember me cookie if exists
        if (isset($_COOKIE['remember_token'])) {
            setcookie('remember_token', '', time() - 3600, '/');
            
            // Clear token from database
            if (isLoggedIn()) {
                $db = getDB();
                $stmt = $db->prepare("UPDATE users SET remember_token = NULL WHERE user_id = ?");
                $stmt->execute([$_SESSION['user_id']]);
            }
        }
        
        // Destroy session
        session_destroy();
        
        handleSuccess([], 'Logged out successfully');
        
    } catch (Exception $e) {
        error_log("Logout error: " . $e->getMessage());
        handleError('Logout failed', 500);
    }
}

/**
 * Enhanced session check with detailed debugging
 */
function handleCheckSession() {
    // Enhanced session debugging
    $sessionData = [
        'session_id' => session_id(),
        'session_status' => session_status(),
        'session_data' => $_SESSION ?? [],
        'cookies' => $_COOKIE ?? []
    ];
    
    error_log("Session check - Data: " . print_r($sessionData, true));
    
    if (!isLoggedIn()) {
        error_log("Session check failed - not logged in");
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
            error_log("Session check failed - user not found in database");
            handleError('User account not found. Please log in again.', 401, [
                'error_code' => 'USER_NOT_FOUND',
                'redirect' => 'login.html'
            ]);
        }
        
        // Return enhanced user data
        $userData = [
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
            'last_login' => $user['last_login']
        ];
        
        error_log("Session check successful for user: " . $user['user_id']);
        handleSuccess($userData, 'Session valid');
        
    } catch (Exception $e) {
        error_log("Session check error: " . $e->getMessage());
        handleError('Session check failed: ' . $e->getMessage(), 500);
    }
}

/**
 * Handle email verification
 */
function handleVerifyEmail() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        handleError('Method not allowed', 405);
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (empty($input['token'])) {
        handleError('Verification token is required', 400);
    }
    
    // This is a placeholder for email verification
    // In production, implement proper email verification with tokens
    
    try {
        if (!isLoggedIn()) {
            handleError('Authentication required', 401);
        }
        
        $db = getDB();
        $stmt = $db->prepare("UPDATE users SET is_verified = 1 WHERE user_id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        
        handleSuccess([], 'Email verified successfully');
        
    } catch (Exception $e) {
        error_log("Email verification error: " . $e->getMessage());
        handleError('Email verification failed', 500);
    }
}

/**
 * Handle password change
 */
function handleChangePassword() {
    // Log the start of password change attempt
    error_log("Password change attempt started");
    
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        error_log("Password change failed: Wrong request method");
        handleError('Method not allowed', 405);
    }
    
    if (!isLoggedIn()) {
        error_log("Password change failed: User not logged in");
        handleError('Authentication required', 401);
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    error_log("Password change input received: " . json_encode(array_keys($input ?: [])));
    
    // Validate required fields
    if (empty($input['currentPassword']) || empty($input['newPassword'])) {
        error_log("Password change failed: Missing required fields");
        handleError('Current password and new password are required', 400);
    }
    
    $currentPassword = $input['currentPassword'];
    $newPassword = $input['newPassword'];
    
    error_log("Password lengths - Current: " . strlen($currentPassword) . ", New: " . strlen($newPassword));
    
    // Validate new password strength
    if (strlen($newPassword) < 8) {
        error_log("Password change failed: New password too short");
        handleError('New password must be at least 8 characters long', 400);
    }
    
    // Check for at least one letter and one number (more flexible than the original)
    if (!preg_match('/[a-zA-Z]/', $newPassword)) {
        error_log("Password change failed: New password missing letter");
        handleError('New password must contain at least one letter', 400);
    }
    
    if (!preg_match('/\d/', $newPassword)) {
        error_log("Password change failed: New password missing number");
        handleError('New password must contain at least one number', 400);
    }
    
    try {
        $db = getDB();
        error_log("Database connection established for password change");
        
        // Get current user data
        $stmt = $db->prepare("SELECT * FROM users WHERE user_id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch();
        
        if (!$user) {
            error_log("Password change failed: User not found for user_id: " . $_SESSION['user_id']);
            handleError('User not found', 404);
        }
        
        error_log("User found for password change: " . $user['email']);
        
        // Verify current password
        $passwordVerified = verifyPassword($currentPassword, $user['password_hash']);
        error_log("Current password verification result: " . ($passwordVerified ? 'true' : 'false'));
        
        if (!$passwordVerified) {
            error_log("Password change failed: Current password incorrect");
            handleError('Current password is incorrect', 401);
        }
        
        // Hash new password
        $newPasswordHash = hashPassword($newPassword);
        error_log("New password hashed successfully");
        
        // Update password in database
        $stmt = $db->prepare("UPDATE users SET password_hash = ?, updated_at = CURRENT_TIMESTAMP WHERE user_id = ?");
        $updateResult = $stmt->execute([$newPasswordHash, $_SESSION['user_id']]);
        $affectedRows = $stmt->rowCount();
        
        error_log("Password update result: " . ($updateResult ? 'true' : 'false') . ", Affected rows: " . $affectedRows);
        
        if (!$updateResult || $affectedRows === 0) {
            error_log("Password change failed: Database update failed");
            handleError('Failed to update password in database', 500);
        }
        
        // Log the password change activity
        try {
            logActivity($_SESSION['user_id'], 'password_changed', 'user', $user['id'], [
                'changed_at' => date('Y-m-d H:i:s'),
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
            ]);
            error_log("Password change activity logged successfully");
        } catch (Exception $logError) {
            error_log("Failed to log password change activity: " . $logError->getMessage());
            // Don't fail the password change if logging fails
        }
        
        error_log("Password change completed successfully for user: " . $user['email']);
        handleSuccess([], 'Password changed successfully');
        
    } catch (Exception $e) {
        error_log("Password change error: " . $e->getMessage());
        error_log("Password change stack trace: " . $e->getTraceAsString());
        handleError('Password change failed. Please try again.', 500);
    }
}

/**
 * Handle profile update
 */
function handleUpdateProfile() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        handleError('Method not allowed', 405);
    }
    
    if (!isLoggedIn()) {
        handleError('Authentication required', 401);
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
        
        // Get current user
        $stmt = $db->prepare("SELECT * FROM users WHERE user_id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch();
        
        if (!$user) {
            handleError('User not found', 404);
        }
        
        // Update user profile
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
            logActivity($_SESSION['user_id'], 'profile_updated', 'user', $user['id'], [
                'updated_fields' => ['firstName', 'lastName', 'phone'],
                'updated_at' => date('Y-m-d H:i:s')
            ]);
        } catch (Exception $logError) {
            error_log("Failed to log profile update activity: " . $logError->getMessage());
        }
        
        // Return updated user data
        $userData = [
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
            'profileImage' => $user['profile_image']
        ];
        
        handleSuccess($userData, 'Profile updated successfully');
        
    } catch (Exception $e) {
        error_log("Profile update error: " . $e->getMessage());
        handleError('Profile update failed. Please try again.', 500);
    }
}

/**
 * Handle avatar upload
 */
function handleUploadAvatar() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        handleError('Method not allowed', 405);
    }
    
    if (!isLoggedIn()) {
        handleError('Authentication required', 401);
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
        $filename = $_SESSION['user_id'] . '_' . time() . '.' . $extension;
        $filepath = $uploadDir . $filename;
        
        // Move uploaded file
        if (!move_uploaded_file($file['tmp_name'], $filepath)) {
            handleError('Failed to save uploaded file', 500);
        }
        
        // Update user profile image in database
        $db = getDB();
        $profileImagePath = 'uploads/avatars/' . $filename;
        
        $stmt = $db->prepare("UPDATE users SET profile_image = ?, updated_at = CURRENT_TIMESTAMP WHERE user_id = ?");
        $stmt->execute([$profileImagePath, $_SESSION['user_id']]);
        
        // Log the avatar upload activity
        try {
            logActivity($_SESSION['user_id'], 'avatar_uploaded', 'user', null, [
                'filename' => $filename,
                'uploaded_at' => date('Y-m-d H:i:s')
            ]);
        } catch (Exception $logError) {
            error_log("Failed to log avatar upload activity: " . $logError->getMessage());
        }
        
        handleSuccess([
            'profileImage' => $profileImagePath,
            'filename' => $filename
        ], 'Avatar uploaded successfully');
        
    } catch (Exception $e) {
        error_log("Avatar upload error: " . $e->getMessage());
        handleError('Avatar upload failed. Please try again.', 500);
    }
}
?>