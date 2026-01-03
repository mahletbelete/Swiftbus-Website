<?php
/**
 * Check current users in database
 */

require_once 'config/database.php';
require_once 'includes/functions.php';

echo "<!DOCTYPE html>
<html>
<head>
    <title>Database Users Check</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        table { border-collapse: collapse; width: 100%; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background-color: #f2f2f2; }
        .admin { background-color: #e8f5e9; }
        .user { background-color: #fff3e0; }
    </style>
</head>
<body>
    <h1>Current Users in Database</h1>";

try {
    $db = getDB();
    $stmt = $db->query('SELECT user_id, email, full_name, role, created_at FROM users ORDER BY id');
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
    
    echo "<h2>Admin Email List (from functions.php)</h2>";
    echo "<ul>";
    $adminEmails = [
        'ezedinmoh1@gmail.com',
        'hanamariamsebsbew1@gmail.com',
        'mubarekali974@gmail.com',
        'wubetlemma788@gmail.com',
        'mahletbelete4@gmail.com',
        'admin@swiftbus.et'
    ];
    
    foreach ($adminEmails as $email) {
        echo "<li>$email</li>";
    }
    echo "</ul>";
    
    echo "<h2>Role Assignment Issues</h2>";
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
        echo "<p style='color: green;'>✅ All user roles are correctly assigned!</p>";
    } else {
        echo "<ul>";
        foreach ($issues as $issue) {
            echo "<li style='color: red;'>$issue</li>";
        }
        echo "</ul>";
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'>Error: " . $e->getMessage() . "</p>";
}

echo "</body></html>";
?>