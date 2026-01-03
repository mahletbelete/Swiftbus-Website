<?php
/**
 * Debug Booking Creation Issue
 */

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Start session
session_start();

echo "<h1>SwiftBus Booking Creation Debug</h1>";
echo "<style>body { font-family: Arial, sans-serif; margin: 20px; } .success { color: green; } .error { color: red; } .info { color: blue; } pre { background: #f5f5f5; padding: 10px; border-radius: 5px; }</style>";

// Step 1: Test database connection
echo "<h2>Step 1: Database Connection</h2>";
try {
    require_once 'config/database.php';
    $database = new Database();
    $db = $database->getConnection();
    echo "<span class='success'>✅ Database connection successful!</span><br>";
} catch (Exception $e) {
    echo "<span class='error'>❌ Database connection failed: " . $e->getMessage() . "</span><br>";
    exit;
}

// Step 2: Test functions file
echo "<h2>Step 2: Functions File</h2>";
try {
    require_once 'includes/functions.php';
    echo "<span class='success'>✅ Functions file loaded successfully!</span><br>";
} catch (Exception $e) {
    echo "<span class='error'>❌ Functions file error: " . $e->getMessage() . "</span><br>";
    exit;
}

// Step 3: Set up test user session
echo "<h2>Step 3: User Session Setup</h2>";
// Use the first admin user from the clean database
$_SESSION['user_id'] = 'USR2025000001';
echo "<span class='info'>ℹ️ Set session user_id: " . $_SESSION['user_id'] . "</span><br>";

// Test if user exists in database
try {
    $stmt = $db->prepare("SELECT * FROM users WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();
    
    if ($user) {
        echo "<span class='success'>✅ User found: " . $user['full_name'] . " (" . $user['email'] . ")</span><br>";
        echo "<span class='info'>ℹ️ User role: " . $user['role'] . "</span><br>";
    } else {
        echo "<span class='error'>❌ User not found in database!</span><br>";
        
        // Check if users table exists and has data
        $stmt = $db->query("SELECT COUNT(*) as count FROM users");
        $count = $stmt->fetch()['count'];
        echo "<span class='info'>ℹ️ Total users in database: " . $count . "</span><br>";
        
        if ($count == 0) {
            echo "<span class='error'>❌ No users in database! Please run the database initialization.</span><br>";
            exit;
        }
    }
} catch (Exception $e) {
    echo "<span class='error'>❌ User query error: " . $e->getMessage() . "</span><br>";
    exit;
}

// Step 4: Test helper functions
echo "<h2>Step 4: Helper Functions</h2>";
try {
    echo "<span class='info'>isLoggedIn(): " . (isLoggedIn() ? 'true' : 'false') . "</span><br>";
    echo "<span class='info'>getCurrentUserId(): " . (getCurrentUserId() ?? 'NULL') . "</span><br>";
    echo "<span class='info'>getCityDisplayName('addis-ababa'): " . getCityDisplayName('addis-ababa') . "</span><br>";
    echo "<span class='info'>getBusCompanyDisplayName('selam-bus'): " . getBusCompanyDisplayName('selam-bus') . "</span><br>";
    echo "<span class='success'>✅ Helper functions working!</span><br>";
} catch (Exception $e) {
    echo "<span class='error'>❌ Helper function error: " . $e->getMessage() . "</span><br>";
}

// Step 5: Test booking creation manually
echo "<h2>Step 5: Manual Booking Creation Test</h2>";

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

echo "<span class='info'>Test booking data:</span><br>";
echo "<pre>" . json_encode($testBookingData, JSON_PRETTY_PRINT) . "</pre>";

try {
    $db->beginTransaction();
    
    // Generate booking ID
    $bookingId = generateUniqueId('SWB');
    echo "<span class='info'>Generated booking ID: " . $bookingId . "</span><br>";
    
    // Get user
    $user = getCurrentUser();
    if (!$user) {
        throw new Exception("User not found");
    }
    echo "<span class='success'>✅ User data retrieved</span><br>";
    
    // Generate QR code data
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
        echo "<span class='success'>✅ Booking inserted successfully!</span><br>";
        echo "<span class='info'>Booking ID: " . $bookingId . "</span><br>";
        
        // Check if booking was actually inserted
        $stmt = $db->prepare("SELECT * FROM bookings WHERE booking_id = ?");
        $stmt->execute([$bookingId]);
        $booking = $stmt->fetch();
        
        if ($booking) {
            echo "<span class='success'>✅ Booking verified in database!</span><br>";
            echo "<pre>" . json_encode($booking, JSON_PRETTY_PRINT) . "</pre>";
        } else {
            echo "<span class='error'>❌ Booking not found after insertion!</span><br>";
        }
        
        $db->commit();
    } else {
        echo "<span class='error'>❌ Booking insertion failed!</span><br>";
        $db->rollBack();
    }
    
} catch (Exception $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    echo "<span class='error'>❌ Booking creation error: " . $e->getMessage() . "</span><br>";
    echo "<span class='error'>Stack trace:</span><br>";
    echo "<pre>" . $e->getTraceAsString() . "</pre>";
}

// Step 6: Test API endpoint directly
echo "<h2>Step 6: API Endpoint Test</h2>";

// Simulate API call
$_SERVER['REQUEST_METHOD'] = 'POST';
$_GET['action'] = 'create_booking';

// Mock the input
$input = json_encode($testBookingData);
file_put_contents('php://temp', $input);

echo "<span class='info'>Testing API endpoint directly...</span><br>";

// Capture API output
ob_start();
try {
    // Mock the input stream for the API
    $GLOBALS['HTTP_RAW_POST_DATA'] = $input;
    
    // Include the API file
    include 'api/booking_clean.php';
} catch (Exception $e) {
    echo "<span class='error'>❌ API Error: " . $e->getMessage() . "</span><br>";
}
$apiOutput = ob_get_clean();

echo "<span class='info'>API Output:</span><br>";
echo "<pre>" . htmlspecialchars($apiOutput) . "</pre>";

echo "<h2>Debug Complete</h2>";
echo "<p>If you see any errors above, those need to be fixed before booking creation will work.</p>";
?>