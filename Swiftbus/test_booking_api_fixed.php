<?php
/**
 * Test Booking API After Fixing Duplicate Functions
 */
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();

echo "<h1>🧪 Testing Booking API (Fixed)</h1>";
echo "<style>
    body { font-family: Arial, sans-serif; max-width: 800px; margin: 0 auto; padding: 20px; }
    .success { background: #d4edda; padding: 10px; border-radius: 5px; margin: 10px 0; border-left: 4px solid #28a745; }
    .error { background: #f8d7da; padding: 10px; border-radius: 5px; margin: 10px 0; border-left: 4px solid #dc3545; }
    .info { background: #d1ecf1; padding: 10px; border-radius: 5px; margin: 10px 0; border-left: 4px solid #17a2b8; }
    pre { background: #f5f5f5; padding: 10px; border-radius: 3px; overflow-x: auto; font-size: 12px; }
</style>";

// Check session
if (!isset($_SESSION['user_id'])) {
    echo "<div class='error'>❌ Please login first: <a href='login.html'>Login</a></div>";
    exit;
}

echo "<div class='success'>✅ Session Active: " . $_SESSION['user_id'] . "</div>";

try {
    // Test functions loading
    require_once 'includes/functions.php';
    echo "<div class='success'>✅ Functions loaded successfully</div>";
    
    // Test database connection
    $db = getDB();
    echo "<div class='success'>✅ Database connected</div>";
    
    // Test user lookup
    $user = getCurrentUser();
    if ($user) {
        echo "<div class='success'>✅ User found: " . $user['full_name'] . "</div>";
    } else {
        echo "<div class='error'>❌ User not found in database</div>";
        exit;
    }
    
    // Test required functions
    $functions = [
        'generateUniqueId',
        'generateQRData', 
        'createPaymentRecord',
        'updatePaymentRecord',
        'reserveSeatsForBooking',
        'releaseSeatsForBooking',
        'initializeSeatsForRoute',
        'logActivity'
    ];
    
    echo "<div class='info'><strong>🔍 Function Check:</strong><br>";
    foreach ($functions as $func) {
        if (function_exists($func)) {
            echo "• $func(): ✅<br>";
        } else {
            echo "• $func(): ❌ Missing<br>";
        }
    }
    echo "</div>";
    
    // Test API call
    echo "<div class='info'><strong>🔗 Testing API Call:</strong><br>";
    
    $testData = [
        'from_city' => 'addis-ababa',
        'to_city' => 'bahirdar', 
        'departure_date' => date('Y-m-d', strtotime('+1 day')),
        'departure_time' => '08:00',
        'bus_company' => 'selam-bus',
        'bus_type' => 'premium-ac',
        'passenger_count' => 1,
        'selected_seats' => ['A1'],
        'passenger_details' => [
            'fullName' => 'Test User',
            'email' => $user['email'],
            'phone' => '+251911234567',
            'idNumber' => 'TEST123456'
        ],
        'total_amount' => 520.00,
        'payment_method' => 'telebirr'
    ];
    
    // Simulate API call by including the API file
    $_POST['action'] = 'create_booking';
    $_SERVER['REQUEST_METHOD'] = 'POST';
    
    // Capture output
    ob_start();
    
    // Mock the input
    $GLOBALS['mock_input'] = json_encode($testData);
    
    // Override file_get_contents for this test
    function file_get_contents($filename) {
        if ($filename === 'php://input') {
            return $GLOBALS['mock_input'];
        }
        return call_user_func_array('file_get_contents', func_get_args());
    }
    
    try {
        include 'api/booking_clean.php';
        $output = ob_get_clean();
        
        echo "API Response:<br>";
        echo "<pre>" . htmlspecialchars($output) . "</pre>";
        
        $response = json_decode($output, true);
        if ($response && $response['success']) {
            echo "<div class='success'>✅ Booking API working correctly!</div>";
        } else {
            echo "<div class='error'>❌ API Error: " . ($response['message'] ?? 'Unknown error') . "</div>";
        }
        
    } catch (Exception $e) {
        ob_end_clean();
        echo "<div class='error'>❌ Exception: " . $e->getMessage() . "</div>";
        echo "<div class='error'>File: " . $e->getFile() . " Line: " . $e->getLine() . "</div>";
    }
    
    echo "</div>";
    
} catch (Exception $e) {
    echo "<div class='error'>❌ Critical Error: " . $e->getMessage() . "</div>";
    echo "<div class='error'>File: " . $e->getFile() . " Line: " . $e->getLine() . "</div>";
}

echo "<hr>";
echo "<p><a href='book-ticket.html'>→ Try Booking</a> | <a href='my-tickets.html'>→ My Tickets</a></p>";
?>