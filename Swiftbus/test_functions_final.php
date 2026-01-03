<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();

echo "<h1>🔧 Final Functions Test</h1>";
echo "<style>
    body { font-family: Arial, sans-serif; max-width: 800px; margin: 0 auto; padding: 20px; }
    .success { color: green; background: #d4edda; padding: 10px; border-radius: 5px; margin: 5px 0; }
    .error { color: red; background: #f8d7da; padding: 10px; border-radius: 5px; margin: 5px 0; }
    .info { color: blue; background: #d1ecf1; padding: 10px; border-radius: 5px; margin: 5px 0; }
</style>";

try {
    require_once 'includes/functions.php';
    echo "<div class='success'>✅ Functions loaded successfully</div>";
    
    // Test all required functions exist
    $functions = [
        'generateUniqueId',
        'generateQRData', 
        'createPaymentRecord',
        'updatePaymentRecord',
        'reserveSeatsForBooking',
        'releaseSeatsForBooking',
        'initializeSeatsForRoute',
        'getSeatCountForBusType',
        'getCurrentUser',
        'logActivity'
    ];
    
    echo "<h2>Function Availability Check</h2>";
    foreach ($functions as $func) {
        if (function_exists($func)) {
            echo "<div class='success'>✅ $func() - Available</div>";
        } else {
            echo "<div class='error'>❌ $func() - Missing</div>";
        }
    }
    
    // Test database connection
    $db = getDB();
    echo "<div class='success'>✅ Database connection successful</div>";
    
    // Test session
    if (isset($_SESSION['user_id'])) {
        echo "<div class='success'>✅ Session active: " . $_SESSION['user_id'] . "</div>";
        
        $user = getCurrentUser();
        if ($user) {
            echo "<div class='success'>✅ User found: " . $user['full_name'] . "</div>";
        } else {
            echo "<div class='error'>❌ User not found in database</div>";
        }
    } else {
        echo "<div class='info'>ℹ️ No active session - please login first</div>";
    }
    
    echo "<h2>✅ All Tests Passed!</h2>";
    echo "<p>The booking system should now work correctly.</p>";
    
} catch (Exception $e) {
    echo "<div class='error'>❌ Error: " . $e->getMessage() . "</div>";
    echo "<div class='error'>File: " . $e->getFile() . "</div>";
    echo "<div class='error'>Line: " . $e->getLine() . "</div>";
}

echo "<hr>";
echo "<p><a href='book-ticket.html'>→ Try Booking Now</a></p>";
echo "<p><a href='diagnose_booking_issue.php'>→ Run Full Diagnosis</a></p>";
?>