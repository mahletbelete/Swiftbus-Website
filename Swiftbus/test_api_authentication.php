<?php
/**
 * Test API Authentication
 */

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Start session
session_start();

echo "<h1>API Authentication Test</h1>";
echo "<style>body { font-family: Arial, sans-serif; margin: 20px; } .success { color: green; } .error { color: red; } .info { color: blue; } pre { background: #f5f5f5; padding: 10px; border-radius: 5px; }</style>";

// Test 1: Check if user is logged in
echo "<h2>Test 1: Session Check</h2>";
echo "<span class='info'>Session ID: " . session_id() . "</span><br>";
echo "<span class='info'>Session data:</span><br>";
echo "<pre>" . print_r($_SESSION, true) . "</pre>";

if (isset($_SESSION['user_id'])) {
    echo "<span class='success'>✅ User ID in session: " . $_SESSION['user_id'] . "</span><br>";
} else {
    echo "<span class='error'>❌ No user ID in session</span><br>";
    
    // Set up a test session
    echo "<span class='info'>Setting up test session...</span><br>";
    $_SESSION['user_id'] = 'USR2025000001';
    echo "<span class='success'>✅ Test session created with user_id: " . $_SESSION['user_id'] . "</span><br>";
}

// Test 2: Load functions and test authentication
echo "<h2>Test 2: Authentication Functions</h2>";
try {
    require_once 'includes/functions.php';
    
    echo "<span class='info'>isLoggedIn(): " . (isLoggedIn() ? 'true' : 'false') . "</span><br>";
    echo "<span class='info'>getCurrentUserId(): " . (getCurrentUserId() ?? 'NULL') . "</span><br>";
    
    $user = getCurrentUser();
    if ($user) {
        echo "<span class='success'>✅ User found: " . $user['full_name'] . " (" . $user['email'] . ")</span><br>";
        echo "<span class='info'>User role: " . $user['role'] . "</span><br>";
    } else {
        echo "<span class='error'>❌ User not found in database</span><br>";
    }
    
} catch (Exception $e) {
    echo "<span class='error'>❌ Error: " . $e->getMessage() . "</span><br>";
}

// Test 3: Simulate API call with authentication
echo "<h2>Test 3: API Call Simulation</h2>";

// Set up request environment
$_SERVER['REQUEST_METHOD'] = 'POST';
$_GET['action'] = 'create_booking';

$testData = [
    'from_city' => 'addis-ababa',
    'to_city' => 'bahirdar',
    'departure_date' => '2025-01-15',
    'departure_time' => '08:00',
    'bus_company' => 'selam-bus',
    'bus_type' => 'premium-ac',
    'passenger_count' => 1,
    'selected_seats' => [1],
    'passenger_details' => [
        'fullName' => 'Test User',
        'email' => 'test@example.com',
        'phone' => '+251911234567',
        'nationality' => 'ethiopian',
        'idNumber' => 'ID123456',
        'ageGroup' => 'adult'
    ],
    'total_amount' => 520.00,
    'payment_method' => 'telebirr'
];

echo "<span class='info'>Test data prepared</span><br>";

// Test the requireLogin function directly
echo "<h3>Testing requireLogin() function</h3>";
try {
    requireLogin();
    echo "<span class='success'>✅ requireLogin() passed</span><br>";
} catch (Exception $e) {
    echo "<span class='error'>❌ requireLogin() failed: " . $e->getMessage() . "</span><br>";
}

// Test 4: Check database connection in API context
echo "<h2>Test 4: Database Connection</h2>";
try {
    $db = getDB();
    echo "<span class='success'>✅ Database connection successful</span><br>";
    
    // Test user lookup
    $stmt = $db->prepare("SELECT * FROM users WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();
    
    if ($user) {
        echo "<span class='success'>✅ User found in database: " . $user['full_name'] . "</span><br>";
    } else {
        echo "<span class='error'>❌ User not found in database for user_id: " . $_SESSION['user_id'] . "</span><br>";
        
        // List all users
        $stmt = $db->query("SELECT user_id, full_name, email FROM users LIMIT 5");
        $users = $stmt->fetchAll();
        echo "<span class='info'>Available users:</span><br>";
        foreach ($users as $u) {
            echo "<span class='info'>- {$u['user_id']}: {$u['full_name']} ({$u['email']})</span><br>";
        }
    }
    
} catch (Exception $e) {
    echo "<span class='error'>❌ Database error: " . $e->getMessage() . "</span><br>";
}

// Test 5: Test the actual API function
echo "<h2>Test 5: API Function Test</h2>";

// Mock the input stream
$inputData = json_encode($testData);

// Create a temporary stream
$temp = fopen('php://temp', 'r+');
fwrite($temp, $inputData);
rewind($temp);

// Override php://input temporarily
stream_wrapper_unregister("php");
stream_wrapper_register("php", "MockInputStream");

class MockInputStream {
    private static $data;
    
    public static function setData($data) {
        self::$data = $data;
    }
    
    public function stream_open($path, $mode, $options, &$opened_path) {
        return true;
    }
    
    public function stream_read($count) {
        global $inputData;
        return $inputData;
    }
    
    public function stream_eof() {
        return true;
    }
    
    public function stream_stat() {
        return [];
    }
}

MockInputStream::setData($inputData);

echo "<span class='info'>Calling API function directly...</span><br>";

ob_start();
try {
    // Include just the function we need
    include_once 'api/booking_clean.php';
} catch (Exception $e) {
    echo "<span class='error'>❌ API function error: " . $e->getMessage() . "</span><br>";
    echo "<pre>" . $e->getTraceAsString() . "</pre>";
}
$output = ob_get_clean();

// Restore original stream wrapper
stream_wrapper_restore("php");
fclose($temp);

echo "<span class='info'>API Output:</span><br>";
echo "<pre>" . htmlspecialchars($output) . "</pre>";

// Parse the output
$response = json_decode($output, true);
if ($response) {
    if ($response['success']) {
        echo "<span class='success'>✅ API call successful!</span><br>";
        echo "<span class='info'>Booking ID: " . $response['data']['booking_id'] . "</span><br>";
    } else {
        echo "<span class='error'>❌ API call failed: " . $response['message'] . "</span><br>";
    }
} else {
    echo "<span class='error'>❌ Could not parse API response</span><br>";
    if (strlen($output) == 0) {
        echo "<span class='error'>No output from API (possible fatal error)</span><br>";
    }
}

echo "<h2>Conclusion</h2>";
echo "<p>If you see any ❌ errors above, those need to be fixed for the booking API to work.</p>";
echo "<p>The most common issues are:</p>";
echo "<ul>";
echo "<li>User not logged in (no session)</li>";
echo "<li>User not found in database</li>";
echo "<li>Database connection issues</li>";
echo "<li>Missing required functions</li>";
echo "</ul>";
?>