<?php
/**
 * Test Complete Booking Flow - Create and Retrieve
 */
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();

echo "<h1>🧪 Complete Booking Flow Test</h1>";
echo "<style>
    body { font-family: Arial, sans-serif; max-width: 900px; margin: 0 auto; padding: 20px; }
    .success { background: #d4edda; padding: 15px; border-radius: 5px; margin: 10px 0; border-left: 4px solid #28a745; }
    .error { background: #f8d7da; padding: 15px; border-radius: 5px; margin: 10px 0; border-left: 4px solid #dc3545; }
    .info { background: #d1ecf1; padding: 15px; border-radius: 5px; margin: 10px 0; border-left: 4px solid #17a2b8; }
    .warning { background: #fff3cd; padding: 15px; border-radius: 5px; margin: 10px 0; border-left: 4px solid #ffc107; }
    pre { background: #f5f5f5; padding: 10px; border-radius: 3px; overflow-x: auto; font-size: 12px; }
    .step { background: white; padding: 20px; margin: 15px 0; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
    .step h3 { color: #007cba; margin-top: 0; }
</style>";

// Check session
if (!isset($_SESSION['user_id'])) {
    echo "<div class='error'>❌ Please login first: <a href='login.html'>Login</a></div>";
    exit;
}

echo "<div class='success'>✅ Session Active: " . $_SESSION['user_id'] . "</div>";

try {
    require_once 'includes/functions.php';
    
    echo "<div class='step'>";
    echo "<h3>Step 1: Environment Check</h3>";
    
    // Test database connection
    $db = getDB();
    echo "✅ Database connected<br>";
    
    // Test user lookup
    $user = getCurrentUser();
    if ($user) {
        echo "✅ User found: " . $user['full_name'] . " (" . $user['email'] . ")<br>";
    } else {
        echo "<div class='error'>❌ User not found in database</div>";
        exit;
    }
    
    // Check required functions
    $functions = ['generateUniqueId', 'generateQRData', 'createPaymentRecord', 'reserveSeatsForBooking', 'initializeSeatsForRoute'];
    foreach ($functions as $func) {
        if (function_exists($func)) {
            echo "✅ Function $func() exists<br>";
        } else {
            echo "❌ Function $func() missing<br>";
        }
    }
    echo "</div>";
    
    echo "<div class='step'>";
    echo "<h3>Step 2: Database Tables Check</h3>";
    
    $tables = ['users', 'bookings', 'payments', 'bus_seats'];
    foreach ($tables as $table) {
        try {
            $stmt = $db->prepare("SELECT COUNT(*) as count FROM $table LIMIT 1");
            $stmt->execute();
            $count = $stmt->fetch()['count'];
            echo "✅ Table '$table': $count records<br>";
        } catch (Exception $e) {
            echo "❌ Table '$table': " . $e->getMessage() . "<br>";
        }
    }
    echo "</div>";
    
    echo "<div class='step'>";
    echo "<h3>Step 3: Test Booking Creation</h3>";
    
    // Create test booking data
    $testBookingId = generateUniqueId('TEST');
    $testData = [
        'booking_id' => $testBookingId,
        'from_city' => 'addis-ababa',
        'to_city' => 'bahirdar',
        'departure_date' => date('Y-m-d', strtotime('+2 days')),
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
    
    echo "Test booking ID: $testBookingId<br>";
    echo "Travel date: " . $testData['departure_date'] . "<br>";
    
    try {
        $db->beginTransaction();
        
        // Insert test booking directly
        $stmt = $db->prepare("
            INSERT INTO bookings (
                booking_id, user_id, bus_company, bus_type, from_city, to_city, 
                travel_date, departure_time, passenger_count, selected_seats, 
                passenger_details, total_amount, payment_method, 
                qr_code, booking_status, payment_status, booking_date
            ) VALUES (
                ?, (SELECT id FROM users WHERE user_id = ?), ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'confirmed', 'paid', CURRENT_TIMESTAMP
            )
        ");
        
        $qrData = generateQRData($testBookingId, 'Test User', 'addis-ababa-bahirdar', $testData['departure_date'], ['A1']);
        
        $stmt->execute([
            $testBookingId,
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
            $qrData
        ]);
        
        // Create payment record
        $paymentId = createPaymentRecord($testBookingId, $testData['total_amount'], $testData['payment_method'], $testData['passenger_details']);
        if ($paymentId) {
            echo "✅ Payment record created: $paymentId<br>";
        }
        
        $db->commit();
        echo "✅ Test booking created successfully<br>";
        
    } catch (Exception $e) {
        $db->rollBack();
        echo "❌ Booking creation failed: " . $e->getMessage() . "<br>";
    }
    echo "</div>";
    
    echo "<div class='step'>";
    echo "<h3>Step 4: Test Booking Retrieval</h3>";
    
    try {
        // Test direct database query
        $stmt = $db->prepare("
            SELECT 
                b.*,
                bc.name as company_name
            FROM bookings b
            LEFT JOIN bus_companies bc ON bc.company_id = b.bus_company
            WHERE b.user_id = (SELECT id FROM users WHERE user_id = ?)
            ORDER BY b.created_at DESC
            LIMIT 5
        ");
        $stmt->execute([$_SESSION['user_id']]);
        $bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo "Found " . count($bookings) . " bookings in database:<br>";
        
        foreach ($bookings as $booking) {
            $isTestBooking = strpos($booking['booking_id'], 'TEST') === 0;
            $icon = $isTestBooking ? '🧪' : '🎫';
            echo "$icon " . $booking['booking_id'] . " - " . $booking['from_city'] . " to " . $booking['to_city'] . " (" . $booking['booking_status'] . ")<br>";
        }
        
        if (count($bookings) > 0) {
            echo "✅ Database query successful<br>";
        } else {
            echo "⚠️ No bookings found in database<br>";
        }
        
    } catch (Exception $e) {
        echo "❌ Database query failed: " . $e->getMessage() . "<br>";
    }
    echo "</div>";
    
    echo "<div class='step'>";
    echo "<h3>Step 5: Test API Endpoint</h3>";
    
    try {
        // Test the API endpoint directly
        $apiUrl = 'http://localhost/bus/api/booking_clean.php?action=get_user_bookings';
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $apiUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Cookie: ' . $_SERVER['HTTP_COOKIE']
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);
        
        echo "HTTP Status: $httpCode<br>";
        
        if ($curlError) {
            echo "❌ CURL Error: $curlError<br>";
        } else {
            echo "✅ API call successful<br>";
            
            $apiData = json_decode($response, true);
            if ($apiData) {
                if ($apiData['success']) {
                    $bookingCount = isset($apiData['data']['bookings']) ? count($apiData['data']['bookings']) : 0;
                    echo "✅ API returned $bookingCount bookings<br>";
                    
                    if ($bookingCount > 0) {
                        echo "<details><summary>View API Response</summary>";
                        echo "<pre>" . htmlspecialchars(json_encode($apiData, JSON_PRETTY_PRINT)) . "</pre>";
                        echo "</details>";
                    }
                } else {
                    echo "❌ API Error: " . ($apiData['message'] ?? 'Unknown error') . "<br>";
                    echo "<pre>" . htmlspecialchars($response) . "</pre>";
                }
            } else {
                echo "❌ Invalid JSON response<br>";
                echo "<pre>" . htmlspecialchars($response) . "</pre>";
            }
        }
        
    } catch (Exception $e) {
        echo "❌ API test failed: " . $e->getMessage() . "<br>";
    }
    echo "</div>";
    
    echo "<div class='step'>";
    echo "<h3>Step 6: Cleanup</h3>";
    
    try {
        // Clean up test booking
        $stmt = $db->prepare("DELETE FROM bookings WHERE booking_id = ?");
        $stmt->execute([$testBookingId]);
        echo "✅ Test booking cleaned up<br>";
        
        // Clean up test payment
        $stmt = $db->prepare("DELETE FROM payments WHERE booking_id = ?");
        $stmt->execute([$testBookingId]);
        echo "✅ Test payment cleaned up<br>";
        
    } catch (Exception $e) {
        echo "⚠️ Cleanup warning: " . $e->getMessage() . "<br>";
    }
    echo "</div>";
    
} catch (Exception $e) {
    echo "<div class='error'>❌ Critical Error: " . $e->getMessage() . "</div>";
    echo "<div class='error'>File: " . $e->getFile() . " Line: " . $e->getLine() . "</div>";
}

echo "<hr>";
echo "<div class='info'>";
echo "<h3>🎯 Next Steps</h3>";
echo "<p>If all tests pass, the booking system should be working. Try:</p>";
echo "<ul>";
echo "<li><a href='book-ticket.html'>Book a new ticket</a></li>";
echo "<li><a href='my-tickets.html'>View your tickets</a></li>";
echo "<li><a href='test_booking_api_fixed.php'>Run API test</a></li>";
echo "</ul>";
echo "</div>";
?>