<?php
/**
 * Complete Booking Flow Debug
 * Test every step of the booking process
 */

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Start session
session_start();

echo "<h1>Complete Booking Flow Debug</h1>";
echo "<style>
    body { font-family: Arial, sans-serif; margin: 20px; } 
    .success { color: green; } 
    .error { color: red; } 
    .info { color: blue; } 
    .warning { color: orange; }
    pre { background: #f5f5f5; padding: 10px; border-radius: 5px; overflow-x: auto; }
    .step { border: 1px solid #ddd; margin: 10px 0; padding: 15px; border-radius: 5px; }
    .step h3 { margin-top: 0; }
</style>";

// Test data
$testBookingData = [
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

echo "<div class='step'>";
echo "<h3>Step 1: Database Connection</h3>";
try {
    require_once 'config/database.php';
    $database = new Database();
    $db = $database->getConnection();
    echo "<span class='success'>✅ Database connection successful!</span><br>";
} catch (Exception $e) {
    echo "<span class='error'>❌ Database connection failed: " . $e->getMessage() . "</span><br>";
    exit;
}
echo "</div>";

echo "<div class='step'>";
echo "<h3>Step 2: Load Functions</h3>";
try {
    require_once 'includes/functions.php';
    echo "<span class='success'>✅ Functions loaded successfully!</span><br>";
} catch (Exception $e) {
    echo "<span class='error'>❌ Functions loading failed: " . $e->getMessage() . "</span><br>";
    exit;
}
echo "</div>";

echo "<div class='step'>";
echo "<h3>Step 3: User Authentication</h3>";

// Check if there are any users in the database
$stmt = $db->query("SELECT COUNT(*) as count FROM users");
$userCount = $stmt->fetch()['count'];
echo "<span class='info'>Total users in database: $userCount</span><br>";

if ($userCount == 0) {
    echo "<span class='error'>❌ No users in database! Please import the clean database.</span><br>";
    exit;
}

// Get the first admin user
$stmt = $db->query("SELECT * FROM users WHERE role = 'admin' LIMIT 1");
$adminUser = $stmt->fetch();

if ($adminUser) {
    echo "<span class='success'>✅ Admin user found: " . $adminUser['full_name'] . " (" . $adminUser['email'] . ")</span><br>";
    
    // Set up session
    $_SESSION['user_id'] = $adminUser['user_id'];
    echo "<span class='info'>Session user_id set to: " . $_SESSION['user_id'] . "</span><br>";
    
    // Test authentication functions
    echo "<span class='info'>isLoggedIn(): " . (isLoggedIn() ? 'true' : 'false') . "</span><br>";
    echo "<span class='info'>getCurrentUserId(): " . (getCurrentUserId() ?? 'NULL') . "</span><br>";
    
    $currentUser = getCurrentUser();
    if ($currentUser) {
        echo "<span class='success'>✅ getCurrentUser() works: " . $currentUser['full_name'] . "</span><br>";
    } else {
        echo "<span class='error'>❌ getCurrentUser() failed</span><br>";
    }
} else {
    echo "<span class='error'>❌ No admin user found!</span><br>";
    exit;
}
echo "</div>";

echo "<div class='step'>";
echo "<h3>Step 4: Test Helper Functions</h3>";
try {
    echo "<span class='info'>getCityDisplayName('addis-ababa'): " . getCityDisplayName('addis-ababa') . "</span><br>";
    echo "<span class='info'>getBusCompanyDisplayName('selam-bus'): " . getBusCompanyDisplayName('selam-bus') . "</span><br>";
    echo "<span class='info'>getBusTypeDisplayName('premium-ac'): " . getBusTypeDisplayName('premium-ac') . "</span><br>";
    echo "<span class='success'>✅ Helper functions working!</span><br>";
} catch (Exception $e) {
    echo "<span class='error'>❌ Helper function error: " . $e->getMessage() . "</span><br>";
}
echo "</div>";

echo "<div class='step'>";
echo "<h3>Step 5: Test Booking Creation (Manual)</h3>";
try {
    $db->beginTransaction();
    
    // Generate booking ID
    $bookingId = generateUniqueId('SWB');
    echo "<span class='info'>Generated booking ID: $bookingId</span><br>";
    
    // Generate QR data
    $qrData = generateQRData(
        $bookingId,
        $testBookingData['passenger_details']['fullName'],
        $testBookingData['from_city'] . '-' . $testBookingData['to_city'],
        $testBookingData['departure_date'],
        $testBookingData['selected_seats']
    );
    echo "<span class='success'>✅ QR data generated</span><br>";
    
    // Insert booking
    $stmt = $db->prepare("
        INSERT INTO bookings (
            booking_id, user_id, bus_company, bus_type, from_city, to_city, 
            travel_date, departure_time, passenger_count, selected_seats, 
            passenger_details, total_amount, payment_method, special_requirements,
            qr_code, booking_status, payment_status, booking_date
        ) VALUES (
            ?, (SELECT id FROM users WHERE user_id = ?), ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', 'pending', CURRENT_TIMESTAMP
        )
    ");
    
    $result = $stmt->execute([
        $bookingId,
        $_SESSION['user_id'],
        $testBookingData['bus_company'],
        $testBookingData['bus_type'],
        $testBookingData['from_city'],
        $testBookingData['to_city'],
        $testBookingData['departure_date'],
        $testBookingData['departure_time'],
        $testBookingData['passenger_count'],
        json_encode($testBookingData['selected_seats']),
        json_encode($testBookingData['passenger_details']),
        $testBookingData['total_amount'],
        $testBookingData['payment_method'],
        '',
        $qrData
    ]);
    
    if ($result) {
        echo "<span class='success'>✅ Manual booking creation successful!</span><br>";
        echo "<span class='info'>Booking ID: $bookingId</span><br>";
        
        // Verify booking exists
        $stmt = $db->prepare("SELECT * FROM bookings WHERE booking_id = ?");
        $stmt->execute([$bookingId]);
        $booking = $stmt->fetch();
        
        if ($booking) {
            echo "<span class='success'>✅ Booking verified in database</span><br>";
        } else {
            echo "<span class='error'>❌ Booking not found after insertion</span><br>";
        }
        
        $db->commit();
    } else {
        echo "<span class='error'>❌ Manual booking creation failed</span><br>";
        $db->rollBack();
    }
    
} catch (Exception $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    echo "<span class='error'>❌ Manual booking error: " . $e->getMessage() . "</span><br>";
    echo "<pre>" . $e->getTraceAsString() . "</pre>";
}
echo "</div>";

echo "<div class='step'>";
echo "<h3>Step 6: Test API Endpoint Directly</h3>";

// Simulate API call
$_SERVER['REQUEST_METHOD'] = 'POST';
$_GET['action'] = 'create_booking';

// Create input data
$input = json_encode($testBookingData);

// Mock php://input
$temp = tmpfile();
fwrite($temp, $input);
rewind($temp);

// Capture API output
ob_start();

try {
    // Temporarily override file_get_contents for php://input
    $originalInput = $input;
    
    // Create a custom function to mock file_get_contents
    function mockFileGetContents($filename) {
        global $originalInput;
        if ($filename === 'php://input') {
            return $originalInput;
        }
        return file_get_contents($filename);
    }
    
    // Override the function temporarily
    $GLOBALS['file_get_contents'] = 'mockFileGetContents';
    
    // Include the API
    include 'api/booking_clean.php';
    
} catch (Exception $e) {
    echo "<span class='error'>❌ API Exception: " . $e->getMessage() . "</span><br>";
    echo "<pre>" . $e->getTraceAsString() . "</pre>";
}

$apiOutput = ob_get_clean();
fclose($temp);

echo "<span class='info'>API Output:</span><br>";
echo "<pre>" . htmlspecialchars($apiOutput) . "</pre>";

// Try to parse JSON response
$response = json_decode($apiOutput, true);
if ($response) {
    if ($response['success']) {
        echo "<span class='success'>✅ API booking creation successful!</span><br>";
        echo "<span class='info'>API Booking ID: " . $response['data']['booking_id'] . "</span><br>";
    } else {
        echo "<span class='error'>❌ API booking creation failed: " . $response['message'] . "</span><br>";
        if (isset($response['error'])) {
            echo "<span class='error'>Error details: " . $response['error'] . "</span><br>";
        }
    }
} else {
    echo "<span class='error'>❌ Could not parse API response as JSON</span><br>";
    echo "<span class='warning'>Raw output length: " . strlen($apiOutput) . " characters</span><br>";
}
echo "</div>";

echo "<div class='step'>";
echo "<h3>Step 7: Check Database Tables</h3>";

// Check if all required tables exist
$requiredTables = ['users', 'bookings', 'bus_companies', 'cities', 'routes', 'buses', 'schedules', 'bus_seats'];
foreach ($requiredTables as $table) {
    try {
        $stmt = $db->query("SELECT COUNT(*) as count FROM $table");
        $count = $stmt->fetch()['count'];
        echo "<span class='success'>✅ Table '$table': $count records</span><br>";
    } catch (Exception $e) {
        echo "<span class='error'>❌ Table '$table': " . $e->getMessage() . "</span><br>";
    }
}

// Check if simple_bookings table exists (it shouldn't)
try {
    $stmt = $db->query("SELECT COUNT(*) as count FROM simple_bookings");
    echo "<span class='error'>❌ 'simple_bookings' table still exists (should be removed)</span><br>";
} catch (Exception $e) {
    echo "<span class='success'>✅ 'simple_bookings' table doesn't exist (correct)</span><br>";
}
echo "</div>";

echo "<div class='step'>";
echo "<h3>Step 8: Test JavaScript API Call Simulation</h3>";

// Simulate the exact call that would come from JavaScript
echo "<span class='info'>Simulating JavaScript API call...</span><br>";

$url = 'http://localhost/api/booking_clean.php?action=create_booking';
$postData = json_encode($testBookingData);

$context = stream_context_create([
    'http' => [
        'method' => 'POST',
        'header' => "Content-Type: application/json\r\n",
        'content' => $postData
    ]
]);

echo "<span class='info'>URL: $url</span><br>";
echo "<span class='info'>POST Data: " . substr($postData, 0, 100) . "...</span><br>";

// Note: This would require a web server to work properly
echo "<span class='warning'>⚠️ This test requires a running web server (XAMPP)</span><br>";
echo "</div>";

echo "<h2>Summary</h2>";
echo "<p>This debug script tests the complete booking flow. Check each step above for any errors.</p>";
echo "<p><strong>If all steps show ✅, the booking creation should work.</strong></p>";
echo "<p><strong>If you see ❌ errors, those need to be fixed first.</strong></p>";

?>