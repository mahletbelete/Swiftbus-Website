<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();

echo "<h1>🔍 Simple API Debug</h1>";
echo "<style>body { font-family: Arial, sans-serif; max-width: 800px; margin: 0 auto; padding: 20px; }</style>";

if (!isset($_SESSION['user_id'])) {
    echo "<p>❌ Please <a href='login.html'>login first</a></p>";
    exit;
}

echo "<p>✅ User: " . $_SESSION['user_id'] . "</p>";

try {
    // Test direct API call
    echo "<h2>Testing API Direct Call</h2>";
    
    $_GET['action'] = 'get_user_bookings';
    $_SERVER['REQUEST_METHOD'] = 'GET';
    
    ob_start();
    include 'api/booking_clean.php';
    $output = ob_get_clean();
    
    echo "<p><strong>Raw API Output:</strong></p>";
    echo "<pre>" . htmlspecialchars($output) . "</pre>";
    
    $data = json_decode($output, true);
    if ($data) {
        echo "<p><strong>Parsed JSON:</strong></p>";
        echo "<pre>" . json_encode($data, JSON_PRETTY_PRINT) . "</pre>";
        
        if ($data['success']) {
            echo "<p>✅ API Success: " . count($data['data']['bookings'] ?? []) . " bookings found</p>";
        } else {
            echo "<p>❌ API Error: " . $data['message'] . "</p>";
        }
    } else {
        echo "<p>❌ Invalid JSON response</p>";
    }
    
} catch (Exception $e) {
    echo "<p>❌ Exception: " . $e->getMessage() . "</p>";
    echo "<p>File: " . $e->getFile() . "</p>";
    echo "<p>Line: " . $e->getLine() . "</p>";
}
?>