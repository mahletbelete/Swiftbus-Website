<?php
// Simple test without complex diagnostics
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>Simple Booking Test</h1>";
echo "<style>body { font-family: Arial, sans-serif; max-width: 800px; margin: 0 auto; padding: 20px; }</style>";

try {
    echo "<p>1. Testing basic PHP...</p>";
    
    session_start();
    echo "<p>2. Session started...</p>";
    
    require_once 'includes/functions.php';
    echo "<p>3. Functions loaded...</p>";
    
    $db = getDB();
    echo "<p>4. Database connected...</p>";
    
    if (isset($_SESSION['user_id'])) {
        echo "<p>5. User logged in: " . $_SESSION['user_id'] . "</p>";
    } else {
        echo "<p>5. No user session - <a href='login.html'>Please login</a></p>";
    }
    
    echo "<h2>✅ Basic test passed!</h2>";
    echo "<p><a href='book-ticket.html'>Try booking now</a></p>";
    
} catch (Exception $e) {
    echo "<h2>❌ Error found:</h2>";
    echo "<p><strong>Error:</strong> " . $e->getMessage() . "</p>";
    echo "<p><strong>File:</strong> " . $e->getFile() . "</p>";
    echo "<p><strong>Line:</strong> " . $e->getLine() . "</p>";
}
?>