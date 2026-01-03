<?php
/**
 * Server Check - Verify XAMPP is working
 */

echo "<h1>SwiftBus Server Check</h1>";
echo "<style>body { font-family: Arial, sans-serif; margin: 20px; } .success { color: green; } .error { color: red; } .info { color: blue; }</style>";

echo "<h2>Server Status</h2>";
echo "<span class='success'>✅ PHP is working!</span><br>";
echo "<span class='info'>PHP Version: " . phpversion() . "</span><br>";
echo "<span class='info'>Server: " . $_SERVER['SERVER_SOFTWARE'] . "</span><br>";
echo "<span class='info'>Document Root: " . $_SERVER['DOCUMENT_ROOT'] . "</span><br>";

echo "<h2>Database Connection</h2>";
try {
    require_once 'config/database.php';
    $database = new Database();
    $db = $database->getConnection();
    echo "<span class='success'>✅ Database connection successful!</span><br>";
    
    // Test query
    $stmt = $db->query("SELECT COUNT(*) as count FROM users");
    $count = $stmt->fetch()['count'];
    echo "<span class='info'>Users in database: $count</span><br>";
    
} catch (Exception $e) {
    echo "<span class='error'>❌ Database error: " . $e->getMessage() . "</span><br>";
}

echo "<h2>API Files Check</h2>";
$apiFiles = ['auth.php', 'booking_clean.php', 'search.php', 'payment.php', 'admin.php'];
foreach ($apiFiles as $file) {
    $path = "api/$file";
    if (file_exists($path)) {
        echo "<span class='success'>✅ $path exists</span><br>";
    } else {
        echo "<span class='error'>❌ $path missing</span><br>";
    }
}

echo "<h2>Session Test</h2>";
session_start();
echo "<span class='info'>Session ID: " . session_id() . "</span><br>";
if (isset($_SESSION['user_id'])) {
    echo "<span class='success'>✅ User logged in: " . $_SESSION['user_id'] . "</span><br>";
} else {
    echo "<span class='info'>ℹ️ No user session (login required)</span><br>";
}

echo "<h2>Instructions</h2>";
echo "<p><strong>If you see this page, XAMPP is working correctly!</strong></p>";
echo "<p>Access your application at:</p>";
echo "<ul>";
echo "<li><a href='http://localhost/bus/login.html'>Login Page</a></li>";
echo "<li><a href='http://localhost/bus/book-ticket.html'>Booking Page</a></li>";
echo "<li><a href='http://localhost/bus/test_api_access.html'>API Test</a></li>";
echo "</ul>";

echo "<h2>API Test</h2>";
echo "<button onclick='testAPI()'>Test Booking API</button>";
echo "<div id='apiResult'></div>";

echo "<script>
async function testAPI() {
    const result = document.getElementById('apiResult');
    result.innerHTML = 'Testing API...';
    
    try {
        const response = await fetch('api/booking_clean.php?action=get_booking_stats');
        const data = await response.json();
        
        if (data.success) {
            result.innerHTML = '<span class=\"success\">✅ API Working: ' + data.message + '</span>';
        } else {
            result.innerHTML = '<span class=\"error\">❌ API Error: ' + data.message + '</span>';
        }
    } catch (error) {
        result.innerHTML = '<span class=\"error\">❌ Connection Error: ' + error.message + '</span>';
    }
}
</script>";
?>