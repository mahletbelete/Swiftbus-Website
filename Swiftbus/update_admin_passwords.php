<?php
require_once 'config/database.php';

try {
    $database = new Database();
    $db = $database->getConnection();
    
    echo "<h2>Update Admin Passwords</h2>\n";
    echo "<p>Updating all admin user passwords to 'Admin@123'...</p>\n";
    
    // New password hash for Admin@123
    $newPassword = password_hash('Admin@123', PASSWORD_DEFAULT);
    
    // Get all admin users
    $stmt = $db->prepare("SELECT id, email, full_name FROM users WHERE role = 'admin'");
    $stmt->execute();
    $admins = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($admins)) {
        echo "❌ No admin users found in database.<br>\n";
        exit;
    }
    
    echo "<h3>Found " . count($admins) . " admin users:</h3>\n";
    
    // Update password for each admin
    $updateStmt = $db->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
    
    foreach ($admins as $admin) {
        try {
            $updateStmt->execute([$newPassword, $admin['id']]);
            echo "✅ Updated password for: {$admin['full_name']} ({$admin['email']})<br>\n";
        } catch (Exception $e) {
            echo "❌ Failed to update {$admin['email']}: " . $e->getMessage() . "<br>\n";
        }
    }
    
    echo "<h3>Password Update Complete!</h3>\n";
    echo "<p><strong>All admin users now have password: Admin@123</strong></p>\n";
    
    echo "<h3>Admin Login Credentials:</h3>\n";
    echo "<ul>\n";
    foreach ($admins as $admin) {
        echo "<li><strong>{$admin['email']}</strong> / Admin@123</li>\n";
    }
    echo "</ul>\n";
    
    echo "<p><a href='login.html'>Go to Login Page</a> | <a href='admin-dashboard.html'>Go to Admin Dashboard</a></p>\n";
    
} catch (Exception $e) {
    echo "<h3>Error:</h3>\n";
    echo "<p style='color: red;'>" . $e->getMessage() . "</p>\n";
    echo "<p>Please check your database configuration and try again.</p>\n";
}
?>