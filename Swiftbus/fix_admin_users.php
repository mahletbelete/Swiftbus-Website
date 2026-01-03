<?php
/**
 * Fix Admin Users Script
 * 
 * This script will:
 * 1. Add the 5 specific admin emails as admin users
 * 2. Update any existing users with admin emails to have admin role
 * 3. Show the updated user list
 */

require_once 'config/database.php';
require_once 'includes/functions.php';

echo "<!DOCTYPE html>
<html>
<head>
    <title>Fix Admin Users - SwiftBus</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .success { color: green; background: #e8f5e9; padding: 10px; border-radius: 5px; margin: 10px 0; }
        .error { color: red; background: #ffebee; padding: 10px; border-radius: 5px; margin: 10px 0; }
        .info { color: blue; background: #e3f2fd; padding: 10px; border-radius: 5px; margin: 10px 0; }
        table { border-collapse: collapse; width: 100%; margin: 20px 0; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background-color: #f2f2f2; }
        .admin { background-color: #e8f5e9; }
        .user { background-color: #fff3e0; }
    </style>
</head>
<body>
    <h1>🚌 SwiftBus Admin Users Fix</h1>";

try {
    $db = getDB();
    
    // The 5 specific admin emails
    $adminEmails = [
        'ezedinmoh1@gmail.com',
        'hanamariamsebsbew1@gmail.com', 
        'mubarekali974@gmail.com',
        'wubetlemma788@gmail.com',
        'mahletbelete4@gmail.com'
    ];
    
    echo "<div class='info'>📋 Processing 5 specific admin emails...</div>";
    
    $addedCount = 0;
    $updatedCount = 0;
    
    foreach ($adminEmails as $email) {
        // Check if user already exists
        $stmt = $db->prepare("SELECT id, role FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $existingUser = $stmt->fetch();
        
        if ($existingUser) {
            // User exists, update role if needed
            if ($existingUser['role'] !== 'admin') {
                $stmt = $db->prepare("UPDATE users SET role = 'admin' WHERE email = ?");
                $stmt->execute([$email]);
                echo "<div class='success'>✅ Updated existing user $email to admin role</div>";
                $updatedCount++;
            } else {
                echo "<div class='info'>ℹ️ User $email already has admin role</div>";
            }
        } else {
            // User doesn't exist, create new admin account
            $userId = generateUniqueId('U');
            $passwordHash = hashPassword('admin123'); // Default password
            
            // Extract name from email (simple approach)
            $emailParts = explode('@', $email);
            $namePart = $emailParts[0];
            
            // Create reasonable names from email
            if (strpos($namePart, '.') !== false) {
                $names = explode('.', $namePart);
                $firstName = ucfirst($names[0]);
                $lastName = ucfirst($names[1] ?? 'Admin');
            } else {
                $firstName = ucfirst($namePart);
                $lastName = 'Admin';
            }
            
            $fullName = $firstName . ' ' . $lastName;
            $joinedDate = date('Y-m-d');
            
            $stmt = $db->prepare("
                INSERT INTO users (user_id, email, password_hash, first_name, last_name, full_name, role, joined_date, is_active) 
                VALUES (?, ?, ?, ?, ?, ?, 'admin', ?, 1)
            ");
            
            $stmt->execute([
                $userId, $email, $passwordHash, $firstName, $lastName, $fullName, $joinedDate
            ]);
            
            echo "<div class='success'>✅ Created new admin account: $email (Password: admin123)</div>";
            $addedCount++;
        }
    }
    
    echo "<h2>📊 Summary</h2>";
    echo "<div class='success'>✅ Added $addedCount new admin accounts</div>";
    echo "<div class='success'>✅ Updated $updatedCount existing accounts to admin</div>";
    
    // Show updated user list
    echo "<h2>👥 Updated User List</h2>";
    $stmt = $db->query('SELECT user_id, email, full_name, role, created_at FROM users ORDER BY role DESC, email');
    $users = $stmt->fetchAll();
    
    echo "<table>";
    echo "<tr><th>User ID</th><th>Email</th><th>Full Name</th><th>Role</th><th>Created</th><th>Admin Check</th></tr>";
    
    foreach ($users as $user) {
        $isAdmin = isAdminEmail($user['email']);
        $rowClass = $user['role'] === 'admin' ? 'admin' : 'user';
        $adminCheck = $isAdmin ? '✅ Should be Admin' : '❌ Should be User';
        
        echo "<tr class='$rowClass'>";
        echo "<td>{$user['user_id']}</td>";
        echo "<td>{$user['email']}</td>";
        echo "<td>{$user['full_name']}</td>";
        echo "<td><strong>{$user['role']}</strong></td>";
        echo "<td>{$user['created_at']}</td>";
        echo "<td>$adminCheck</td>";
        echo "</tr>";
    }
    
    echo "</table>";
    
    // Check for any role mismatches
    echo "<h2>🔍 Role Assignment Verification</h2>";
    $issues = [];
    
    foreach ($users as $user) {
        $shouldBeAdmin = isAdminEmail($user['email']);
        $isCurrentlyAdmin = $user['role'] === 'admin';
        
        if ($shouldBeAdmin && !$isCurrentlyAdmin) {
            $issues[] = "❌ {$user['email']} should be admin but is currently user";
        } elseif (!$shouldBeAdmin && $isCurrentlyAdmin) {
            $issues[] = "❌ {$user['email']} should be user but is currently admin";
        }
    }
    
    if (empty($issues)) {
        echo "<div class='success'>✅ All user roles are correctly assigned!</div>";
    } else {
        echo "<div class='error'>";
        foreach ($issues as $issue) {
            echo "<div>$issue</div>";
        }
        echo "</div>";
    }
    
    echo "<h2>🔑 Default Admin Credentials</h2>";
    echo "<div class='info'>";
    echo "<p><strong>For the 5 new admin accounts created:</strong></p>";
    echo "<ul>";
    foreach ($adminEmails as $email) {
        echo "<li>Email: <strong>$email</strong> | Password: <strong>admin123</strong></li>";
    }
    echo "</ul>";
    echo "<p><em>Note: Users should change their passwords after first login</em></p>";
    echo "</div>";
    
} catch (Exception $e) {
    echo "<div class='error'>❌ Error: " . $e->getMessage() . "</div>";
}

echo "<div style='margin-top: 30px;'>
    <a href='login.html' style='background: #1a73e8; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Test Login</a>
    <a href='check_users.php' style='background: #666; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; margin-left: 10px;'>Check Users Again</a>
</div>";

echo "</body></html>";
?>