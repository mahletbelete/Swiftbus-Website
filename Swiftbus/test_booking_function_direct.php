<?php
/**
 * Test Booking Function Directly
 * Bypass the API layer and test the booking creation function directly
 */

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Start session
session_start();

echo "<h1>Direct Booking Function Test</h1>";
echo "<style>
    body { font-family: Arial, sans-serif; margin: 20px; } 
    .success { color: green; } 
    .error { color: red; } 
    .info { color: blue; } 
    .warning { color: orange; }
    pre { background: #f5f5f5; padding: 10px; border-radius: 5px; overflow-x: auto; }
</style>";

// Set up test environment
try {
    require_once 'config/database.php';
    require_once 'includes/functions.php';
    
    $database = new Database();
    $db = $database->getConnection();
    
    // Get first admin user and set session
    $stmt = $db->query("SELECT * FROM users WHERE role = 'admin' LIMIT 1");
    $admin = $stmt->fetch();
    
    if ($admin) {
        $_SESSION['user_id'] = $admin['user_id'];
        echo "<span class='success'>✅ Test session: " . $admin['user_id'] . " (" . $admin['email'] . ")</span><br>";
    } else {
        echo "<span class='error'>❌ No admin users found</span><br>";
        exit;
    }
    
} catch (Exception $e) {
    echo "<span class='error'>❌ Setup error: " . $e->getMessage() . "</span><br>";
    exit;
}

// Test booking creation step by step
echo "<h2>Step-by-Step Booking Creation</h2>";

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

echo "<h3>1. Validate Input Data</h3>";
$required = ['from_city', 'to_city', 'departure_date', 'departure_time', 'passenger_details', 'total_amount'];
$valid = true;
foreach ($required as $field) {
    if (empty($testData[$field])) {
        echo "<span class='error'>❌ Missing field: $field</span><br>";
        $valid = false;
    } else {
        echo "<span class='success'>✅ Field $field: OK</span><br>";
    }
}

if (!$valid) {
    echo "<span class='error'>❌ Input validation failed</span><br>";
    exit;
}

echo "<h3>2. Test Helper Functions</h3>";
try {
    echo "<span class='info'>isLoggedIn(): " . (isLoggedIn() ? 'true' : 'false') . "</span><br>";
    echo "<span class='info'>getCurrentUserId(): " . (getCurrentUserId() ?? 'NULL') . "</span><br>";
    
    $user = getCurrentUser();
    if ($user) {
        echo "<span class='success'>✅ getCurrentUser(): " . $user['full_name'] . "</span><br>";
    } else {
        echo "<span class='error'>❌ getCurrentUser() failed</span><br>";
    }
    
    $bookingId = generateUniqueId('SWB');
    echo "<span class='success'>✅ generateUniqueId(): $bookingId</span><br>";
    
    $qrData = generateQRData(
        $bookingId,
        $testData['passenger_details']['fullName'],
        $testData['from_city'] . '-' . $testData['to_city'],
        $testData['departure_date'],
        $testData['selected_seats']
    );
    echo "<span class='success'>✅ generateQRData(): " . substr($qrData, 0, 50) . "...</span><br>";
    
} catch (Exception $e) {
    echo "<span class='error'>❌ Helper function error: " . $e->getMessage() . "</span><br>";
    exit;
}

echo "<h3>3. Test Database Insert</h3>";
try {
    $db->beginTransaction();
    
    // Test the exact SQL from booking_clean.php
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
        $testData['bus_company'],
        $testData['bus_type'],
        $testData['from_city'],
        $testData['to_city'],
        $testData['departure_date'],
        $testData['departure_time'],
        $testData['passenger_count'],
        json_encode($testData['selected_seats']),
        json_encode($testData['passenger_details']),
        $testData['total_amount'],
        $testData['payment_method'],
        '', // special_requirements
        $qrData
    ]);
    
    if ($result) {
        echo "<span class='success'>✅ Database insert successful</span><br>";
        
        // Verify the booking was inserted
        $stmt = $db->prepare("SELECT * FROM bookings WHERE booking_id = ?");
        $stmt->execute([$bookingId]);
        $booking = $stmt->fetch();
        
        if ($booking) {
            echo "<span class='success'>✅ Booking verified in database</span><br>";
            echo "<span class='info'>📋 Booking details:</span><br>";
            echo "<pre>" . json_encode($booking, JSON_PRETTY_PRINT) . "</pre>";
        } else {
            echo "<span class='error'>❌ Booking not found after insert</span><br>";
        }
        
        $db->commit();
        
    } else {
        echo "<span class='error'>❌ Database insert failed</span><br>";
        $db->rollBack();
    }
    
} catch (Exception $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    echo "<span class='error'>❌ Database error: " . $e->getMessage() . "</span><br>";
    echo "<span class='error'>SQL State: " . $e->getCode() . "</span><br>";
    
    // Check if it's a table/column issue
    if (strpos($e->getMessage(), "doesn't exist") !== false) {
        echo "<span class='warning'>⚠️ This looks like a table or column doesn't exist</span><br>";
        
        // Check table structure
        try {
            $stmt = $db->query("DESCRIBE bookings");
            $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
            echo "<span class='info'>📋 Available columns: " . implode(', ', $columns) . "</span><br>";
        } catch (Exception $e2) {
            echo "<span class='error'>❌ Can't describe bookings table: " . $e2->getMessage() . "</span><br>";
        }
    }
}

echo "<h3>4. Test Seat Management (if applicable)</h3>";
if (!empty($testData['selected_seats'])) {
    try {
        // Test seat initialization
        $route = $testData['from_city'] . '-' . $testData['to_city'];
        
        // Check if bus_seats table exists
        $stmt = $db->query("SHOW TABLES LIKE 'bus_seats'");
        if ($stmt->fetch()) {
            echo "<span class='success'>✅ bus_seats table exists</span><br>";
            
            // Test seat availability check
            $stmt = $db->prepare("
                SELECT COUNT(*) as count 
                FROM bus_seats 
                WHERE bus_company = ? AND bus_type = ? AND route = ? 
                AND departure_date = ? AND departure_time = ?
            ");
            $stmt->execute([
                $testData['bus_company'],
                $testData['bus_type'],
                $route,
                $testData['departure_date'],
                $testData['departure_time']
            ]);
            $seatCount = $stmt->fetch()['count'];
            echo "<span class='info'>📋 Existing seats for this route: $seatCount</span><br>";
            
        } else {
            echo "<span class='error'>❌ bus_seats table doesn't exist</span><br>";
        }
        
    } catch (Exception $e) {
        echo "<span class='error'>❌ Seat management error: " . $e->getMessage() . "</span><br>";
    }
}

echo "<h2>Conclusion</h2>";
echo "<p>This test bypasses the API layer and tests the booking creation logic directly.</p>";
echo "<p>If you see any ❌ errors above, those are the exact issues preventing booking creation.</p>";
echo "<p>The most common issues are:</p>";
echo "<ul>";
echo "<li>Missing or incorrect database table structure</li>";
echo "<li>Missing helper functions</li>";
echo "<li>User authentication issues</li>";
echo "<li>Database connection problems</li>";
echo "</ul>";
?>