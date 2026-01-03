<?php
/**
 * Test getUserBookings API call directly
 */
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();

echo "<h1>🔍 Testing getUserBookings API</h1>";
echo "<style>
    body { font-family: Arial, sans-serif; max-width: 800px; margin: 0 auto; padding: 20px; }
    .success { background: #d4edda; padding: 10px; border-radius: 5px; margin: 10px 0; border-left: 4px solid #28a745; }
    .error { background: #f8d7da; padding: 10px; border-radius: 5px; margin: 10px 0; border-left: 4px solid #dc3545; }
    .info { background: #d1ecf1; padding: 10px; border-radius: 5px; margin: 10px 0; border-left: 4px solid #17a2b8; }
    pre { background: #f5f5f5; padding: 10px; border-radius: 3px; overflow-x: auto; font-size: 12px; }
</style>";

// Check session first
echo "<div class='info'><h3>🔐 Session Check</h3>";
if (isset($_SESSION['user_id'])) {
    echo "✅ Session active: " . $_SESSION['user_id'] . "<br>";
} else {
    echo "❌ No session found. Please <a href='login.html'>login first</a><br>";
    exit;
}
echo "</div>";

// Test the API call
echo "<div class='info'><h3>🔗 Testing API Call</h3>";

try {
    // Set up the request
    $_GET['action'] = 'get_user_bookings';
    $_SERVER['REQUEST_METHOD'] = 'GET';
    
    // Capture any output
    ob_start();
    
    // Include the API file
    include 'api/booking_clean.php';
    
    $output = ob_get_clean();
    
    echo "✅ API call completed successfully<br>";
    echo "<strong>Response:</strong><br>";
    echo "<pre>" . htmlspecialchars($output) . "</pre>";
    
    // Try to decode the JSON
    $data = json_decode($output, true);
    if ($data) {
        echo "<div class='success'>";
        echo "<strong>✅ Valid JSON Response:</strong><br>";
        echo "Success: " . ($data['success'] ? 'true' : 'false') . "<br>";
        echo "Message: " . ($data['message'] ?? 'No message') . "<br>";
        if (isset($data['data']['bookings'])) {
            echo "Bookings found: " . count($data['data']['bookings']) . "<br>";
        }
        echo "</div>";
    } else {
        echo "<div class='error'>❌ Invalid JSON response</div>";
    }
    
} catch (ParseError $e) {
    ob_end_clean();
    echo "<div class='error'>";
    echo "❌ Parse Error: " . $e->getMessage() . "<br>";
    echo "File: " . $e->getFile() . "<br>";
    echo "Line: " . $e->getLine() . "<br>";
    echo "</div>";
} catch (Error $e) {
    ob_end_clean();
    echo "<div class='error'>";
    echo "❌ Fatal Error: " . $e->getMessage() . "<br>";
    echo "File: " . $e->getFile() . "<br>";
    echo "Line: " . $e->getLine() . "<br>";
    echo "</div>";
} catch (Exception $e) {
    ob_end_clean();
    echo "<div class='error'>";
    echo "❌ Exception: " . $e->getMessage() . "<br>";
    echo "File: " . $e->getFile() . "<br>";
    echo "Line: " . $e->getLine() . "<br>";
    echo "</div>";
}

echo "</div>";

// Test individual components
echo "<div class='info'><h3>🧪 Component Tests</h3>";

try {
    require_once 'includes/functions.php';
    echo "✅ Functions loaded<br>";
    
    $db = getDB();
    echo "✅ Database connected<br>";
    
    if (function_exists('getCurrentUserId')) {
        $userId = getCurrentUserId();
        echo "✅ getCurrentUserId(): " . ($userId ?: 'null') . "<br>";
    } else {
        echo "❌ getCurrentUserId() function not found<br>";
    }
    
    if (function_exists('requireLogin')) {
        echo "✅ requireLogin() function exists<br>";
    } else {
        echo "❌ requireLogin() function not found<br>";
    }
    
} catch (Exception $e) {
    echo "❌ Component test error: " . $e->getMessage() . "<br>";
}

echo "</div>";

echo "<hr>";
echo "<p><a href='my-tickets.html'>→ Back to My Tickets</a></p>";
?>