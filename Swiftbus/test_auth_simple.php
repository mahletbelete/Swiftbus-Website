<?php
// Simple authentication test
session_start();

// Set admin session for testing
$_SESSION['user_id'] = 'U001';
$_SESSION['user_role'] = 'admin';
$_SESSION['user_email'] = 'admin@swiftbus.et';

echo "Session set for testing:\n";
echo "User ID: " . ($_SESSION['user_id'] ?? 'Not set') . "\n";
echo "Role: " . ($_SESSION['user_role'] ?? 'Not set') . "\n";
echo "Email: " . ($_SESSION['user_email'] ?? 'Not set') . "\n";

// Test database connection
try {
    $pdo = new PDO("mysql:host=localhost;dbname=swiftbus_db", "root", "");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "Database connection: OK\n";
    
    // Test simple query
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM schedules");
    $stmt->execute();
    $result = $stmt->fetch();
    echo "Schedules count: " . $result['count'] . "\n";
    
} catch (Exception $e) {
    echo "Database error: " . $e->getMessage() . "\n";
}
?>