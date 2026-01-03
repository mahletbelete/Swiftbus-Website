<?php
/**
 * Test API Error - Debug 500 Internal Server Error
 */
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);

echo "<h1>🔍 API Error Diagnosis</h1>";
echo "<style>
    body { font-family: Arial, sans-serif; max-width: 800px; margin: 0 auto; padding: 20px; }
    .success { background: #d4edda; padding: 10px; border-radius: 5px; margin: 10px 0; border-left: 4px solid #28a745; }
    .error { background: #f8d7da; padding: 10px; border-radius: 5px; margin: 10px 0; border-left: 4px solid #dc3545; }
    .info { background: #d1ecf1; padding: 10px; border-radius: 5px; margin: 10px 0; border-left: 4px solid #17a2b8; }
    pre { background: #f5f5f5; padding: 10px; border-radius: 3px; overflow-x: auto; font-size: 12px; }
</style>";

// Test 1: Check if files exist
echo "<div class='info'><h3>📁 File Existence Check</h3>";
$files = [
    'api/booking_clean.php',
    'includes/functions.php',
    'config/database.php',
    'api/auth.php'
];

foreach ($files as $file) {
    if (file_exists($file)) {
        echo "✅ $file exists<br>";
    } else {
        echo "❌ $file missing<br>";
    }
}
echo "</div>";

// Test 2: Check PHP syntax
echo "<div class='info'><h3>🔍 PHP Syntax Check</h3>";
$output = [];
$return_var = 0;

// Check booking_clean.php syntax
exec('php -l api/booking_clean.php 2>&1', $output, $return_var);
if ($return_var === 0) {
    echo "✅ api/booking_clean.php syntax OK<br>";
} else {
    echo "❌ api/booking_clean.php syntax error:<br>";
    echo "<pre>" . implode("\n", $output) . "</pre>";
}

// Check functions.php syntax
$output = [];
exec('php -l includes/functions.php 2>&1', $output, $return_var);
if ($return_var === 0) {
    echo "✅ includes/functions.php syntax OK<br>";
} else {
    echo "❌ includes/functions.php syntax error:<br>";
    echo "<pre>" . implode("\n", $output) . "</pre>";
}
echo "</div>";

// Test 3: Try to include files
echo "<div class='info'><h3>📥 File Inclusion Test</h3>";
try {
    require_once 'includes/functions.php';
    echo "✅ includes/functions.php loaded successfully<br>";
} catch (Exception $e) {
    echo "❌ Error loading includes/functions.php: " . $e->getMessage() . "<br>";
} catch (ParseError $e) {
    echo "❌ Parse error in includes/functions.php: " . $e->getMessage() . "<br>";
} catch (Error $e) {
    echo "❌ Fatal error in includes/functions.php: " . $e->getMessage() . "<br>";
}
echo "</div>";

// Test 4: Test database connection
echo "<div class='info'><h3>🗄️ Database Connection Test</h3>";
try {
    $db = getDB();
    echo "✅ Database connection successful<br>";
    
    // Test a simple query
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM users LIMIT 1");
    $stmt->execute();
    $result = $stmt->fetch();
    echo "✅ Database query successful - found " . $result['count'] . " users<br>";
    
} catch (Exception $e) {
    echo "❌ Database error: " . $e->getMessage() . "<br>";
}
echo "</div>";

// Test 5: Test session
echo "<div class='info'><h3>🔐 Session Test</h3>";
session_start();
if (isset($_SESSION['user_id'])) {
    echo "✅ Session active: " . $_SESSION['user_id'] . "<br>";
    
    try {
        $user = getCurrentUser();
        if ($user) {
            echo "✅ User found: " . $user['full_name'] . "<br>";
        } else {
            echo "⚠️ Session exists but user not found in database<br>";
        }
    } catch (Exception $e) {
        echo "❌ Error getting current user: " . $e->getMessage() . "<br>";
    }
} else {
    echo "⚠️ No active session - user not logged in<br>";
}
echo "</div>";

// Test 6: Test API endpoint directly
echo "<div class='info'><h3>🔗 API Endpoint Test</h3>";
try {
    // Simulate the API call
    $_GET['action'] = 'get_user_bookings';
    $_SERVER['REQUEST_METHOD'] = 'GET';
    
    // Capture output
    ob_start();
    
    // Include the API file
    include 'api/booking_clean.php';
    
    $output = ob_get_clean();
    
    echo "✅ API call completed<br>";
    echo "<strong>API Response:</strong><br>";
    echo "<pre>" . htmlspecialchars($output) . "</pre>";
    
} catch (Exception $e) {
    ob_end_clean();
    echo "❌ API error: " . $e->getMessage() . "<br>";
} catch (Error $e) {
    ob_end_clean();
    echo "❌ Fatal API error: " . $e->getMessage() . "<br>";
}
echo "</div>";

echo "<hr>";
echo "<p><strong>Next Steps:</strong></p>";
echo "<ul>";
echo "<li>If syntax errors: Fix the PHP syntax issues</li>";
echo "<li>If database errors: Check database connection and tables</li>";
echo "<li>If session errors: Make sure user is logged in</li>";
echo "<li>If API errors: Check the specific error message above</li>";
echo "</ul>";
?>