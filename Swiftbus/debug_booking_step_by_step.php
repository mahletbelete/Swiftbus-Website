<?php
/**
 * Step-by-Step Booking Debug
 * Test each component of the booking system
 */

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Start session
session_start();

echo "<h1>Step-by-Step Booking Debug</h1>";
echo "<style>
    body { font-family: Arial, sans-serif; margin: 20px; } 
    .success { color: green; } 
    .error { color: red; } 
    .info { color: blue; } 
    .warning { color: orange; }
    pre { background: #f5f5f5; padding: 10px; border-radius: 5px; overflow-x: auto; }
    .step { border: 1px solid #ddd; margin: 10px 0; padding: 15px; border-radius: 5px; }
    .step h3 { margin-top: 0; color: #333; }
    button { background: #007cba; color: white; border: none; padding: 10px 20px; border-radius: 5px; cursor: pointer; margin: 5px; }
    button:hover { background: #005a87; }
</style>";

// Step 1: Database Connection
echo "<div class='step'>";
echo "<h3>Step 1: Database Connection & Structure</h3>";
try {
    require_once 'config/database.php';
    $database = new Database();
    $db = $database->getConnection();
    echo "<span class='success'>✅ Database connection successful</span><br>";
    
    // Check tables
    $stmt = $db->query("SHOW TABLES");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    echo "<span class='info'>📋 Available tables: " . implode(', ', $tables) . "</span><br>";
    
    // Check if simple_bookings exists
    if (in_array('simple_bookings', $tables)) {
        echo "<span class='error'>❌ simple_bookings table exists (should be removed)</span><br>";
    } else {
        echo "<span class='success'>✅ simple_bookings table removed (correct)</span><br>";
    }
    
    // Check bookings table structure
    if (in_array('bookings', $tables)) {
        $stmt = $db->query("DESCRIBE bookings");
        $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
        echo "<span class='info'>📋 Bookings table columns: " . implode(', ', $columns) . "</span><br>";
        
        $requiredColumns = ['from_city', 'to_city', 'bus_company', 'bus_type'];
        $hasAllColumns = true;
        foreach ($requiredColumns as $col) {
            if (!in_array($col, $columns)) {
                echo "<span class='error'>❌ Missing column: $col</span><br>";
                $hasAllColumns = false;
            }
        }
        if ($hasAllColumns) {
            echo "<span class='success'>✅ Bookings table has correct structure</span><br>";
        }
    } else {
        echo "<span class='error'>❌ bookings table missing</span><br>";
    }
    
} catch (Exception $e) {
    echo "<span class='error'>❌ Database error: " . $e->getMessage() . "</span><br>";
}
echo "</div>";

// Step 2: User Authentication
echo "<div class='step'>";
echo "<h3>Step 2: User Authentication</h3>";

// Check if user is logged in
if (isset($_SESSION['user_id'])) {
    echo "<span class='success'>✅ User session exists: " . $_SESSION['user_id'] . "</span><br>";
} else {
    echo "<span class='warning'>⚠️ No user session found</span><br>";
    
    // Get first admin user and set session
    try {
        $stmt = $db->query("SELECT * FROM users WHERE role = 'admin' LIMIT 1");
        $admin = $stmt->fetch();
        if ($admin) {
            $_SESSION['user_id'] = $admin['user_id'];
            echo "<span class='info'>🔧 Set test session: " . $admin['user_id'] . " (" . $admin['email'] . ")</span><br>";
        } else {
            echo "<span class='error'>❌ No admin users found in database</span><br>";
        }
    } catch (Exception $e) {
        echo "<span class='error'>❌ Error setting test session: " . $e->getMessage() . "</span><br>";
    }
}

// Load functions and test authentication
try {
    require_once 'includes/functions.php';
    echo "<span class='success'>✅ Functions loaded</span><br>";
    
    if (isLoggedIn()) {
        echo "<span class='success'>✅ isLoggedIn() = true</span><br>";
        $user = getCurrentUser();
        if ($user) {
            echo "<span class='success'>✅ getCurrentUser(): " . $user['full_name'] . " (" . $user['email'] . ")</span><br>";
        } else {
            echo "<span class='error'>❌ getCurrentUser() returned null</span><br>";
        }
    } else {
        echo "<span class='error'>❌ isLoggedIn() = false</span><br>";
    }
} catch (Exception $e) {
    echo "<span class='error'>❌ Functions error: " . $e->getMessage() . "</span><br>";
}
echo "</div>";

// Step 3: Test API Endpoint Directly
echo "<div class='step'>";
echo "<h3>Step 3: Test API Endpoint</h3>";

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

echo "<button onclick='testAPI()'>Test API Call</button>";
echo "<div id='apiResult'></div>";

echo "<script>
async function testAPI() {
    const resultDiv = document.getElementById('apiResult');
    resultDiv.innerHTML = '<span class=\"info\">🔄 Testing API...</span><br>';
    
    try {
        const response = await fetch('api/booking_clean.php?action=create_booking', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(" . json_encode($testBookingData) . ")
        });
        
        const data = await response.json();
        
        if (data.success) {
            resultDiv.innerHTML += '<span class=\"success\">✅ API Success: ' + data.message + '</span><br>';
            resultDiv.innerHTML += '<span class=\"info\">📋 Booking ID: ' + data.data.booking_id + '</span><br>';
        } else {
            resultDiv.innerHTML += '<span class=\"error\">❌ API Failed: ' + data.message + '</span><br>';
        }
        
        resultDiv.innerHTML += '<pre>' + JSON.stringify(data, null, 2) + '</pre>';
        
    } catch (error) {
        resultDiv.innerHTML += '<span class=\"error\">❌ Network Error: ' + error.message + '</span><br>';
    }
}
</script>";

echo "</div>";

// Step 4: Check API File
echo "<div class='step'>";
echo "<h3>Step 4: API File Check</h3>";

$apiFiles = ['api/booking_clean.php', 'api/booking.php'];
foreach ($apiFiles as $file) {
    if (file_exists($file)) {
        echo "<span class='success'>✅ $file exists</span><br>";
        $size = filesize($file);
        echo "<span class='info'>📏 Size: " . number_format($size) . " bytes</span><br>";
    } else {
        echo "<span class='error'>❌ $file missing</span><br>";
    }
}

// Check which API is being used
echo "<span class='info'>🔍 Checking js/api.js endpoint...</span><br>";
$jsContent = file_get_contents('js/api.js');
if (strpos($jsContent, 'booking_clean.php') !== false) {
    echo "<span class='success'>✅ js/api.js uses booking_clean.php</span><br>";
} else {
    echo "<span class='error'>❌ js/api.js not using booking_clean.php</span><br>";
}

echo "</div>";

// Step 5: Manual Booking Creation Test
echo "<div class='step'>";
echo "<h3>Step 5: Manual Database Insert Test</h3>";

try {
    $bookingId = 'SWB' . date('Y') . str_pad(mt_rand(1, 999999), 6, '0', STR_PAD_LEFT);
    
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
        'selam-bus',
        'premium-ac',
        'addis-ababa',
        'bahirdar',
        '2025-01-15',
        '08:00',
        1,
        json_encode([1]),
        json_encode(['fullName' => 'Test User']),
        520.00,
        'telebirr',
        '',
        'test-qr-code'
    ]);
    
    if ($result) {
        echo "<span class='success'>✅ Manual booking insert successful</span><br>";
        echo "<span class='info'>📋 Booking ID: $bookingId</span><br>";
        
        // Verify it was inserted
        $stmt = $db->prepare("SELECT * FROM bookings WHERE booking_id = ?");
        $stmt->execute([$bookingId]);
        $booking = $stmt->fetch();
        
        if ($booking) {
            echo "<span class='success'>✅ Booking verified in database</span><br>";
        } else {
            echo "<span class='error'>❌ Booking not found after insert</span><br>";
        }
    } else {
        echo "<span class='error'>❌ Manual booking insert failed</span><br>";
    }
    
} catch (Exception $e) {
    echo "<span class='error'>❌ Manual insert error: " . $e->getMessage() . "</span><br>";
}

echo "</div>";

echo "<h2>Summary</h2>";
echo "<p>This debug script tests each component of the booking system.</p>";
echo "<p>Click the 'Test API Call' button to test the actual API endpoint that book-ticket.html uses.</p>";
echo "<p>If you see any ❌ errors above, those need to be fixed for booking creation to work.</p>";
?>