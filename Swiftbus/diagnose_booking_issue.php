<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();

echo "<h1>🔍 Booking Issue Diagnosis</h1>";
echo "<style>
    body { font-family: Arial, sans-serif; max-width: 1000px; margin: 0 auto; padding: 20px; }
    .section { background: #f8f9fa; padding: 15px; margin: 15px 0; border-radius: 5px; border-left: 4px solid #007cba; }
    .success { border-left-color: #28a745; background: #d4edda; }
    .error { border-left-color: #dc3545; background: #f8d7da; }
    .warning { border-left-color: #ffc107; background: #fff3cd; }
    pre { background: #f5f5f5; padding: 10px; border-radius: 3px; overflow-x: auto; font-size: 12px; }
    button { background: #007cba; color: white; border: none; padding: 10px 20px; border-radius: 5px; cursor: pointer; margin: 5px; }
</style>";

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo "<div class='section error'>";
    echo "<strong>❌ Not Logged In:</strong> Please <a href='login.html'>login first</a> before testing booking.";
    echo "</div>";
    exit;
}

echo "<div class='section success'>";
echo "<strong>✅ Session Active:</strong> " . $_SESSION['user_id'] . "<br>";
echo "</div>";

try {
    require_once 'includes/functions.php';
    
    echo "<div class='section success'>";
    echo "<strong>✅ Functions Loaded:</strong> includes/functions.php loaded successfully<br>";
    echo "</div>";
    
    // Test database connection
    $db = getDB();
    echo "<div class='section success'>";
    echo "<strong>✅ Database Connected:</strong> Connection successful<br>";
    echo "</div>";
    
    // Test user lookup
    $user = getCurrentUser();
    if ($user) {
        echo "<div class='section success'>";
        echo "<strong>✅ User Found:</strong> " . $user['full_name'] . " (" . $user['email'] . ")<br>";
        echo "</div>";
    } else {
        echo "<div class='section error'>";
        echo "<strong>❌ User Not Found:</strong> Session user_id doesn't match database<br>";
        echo "</div>";
        exit;
    }
    
    // Test required functions exist
    echo "<div class='section'>";
    echo "<strong>🔍 Checking Required Functions:</strong><br>";
    
    $required_functions = [
        'generateUniqueId',
        'generateQRData',
        'createPaymentRecord',
        'updatePaymentRecord',
        'logActivity'
    ];
    
    foreach ($required_functions as $func) {
        if (function_exists($func)) {
            echo "• $func(): ✅ Exists<br>";
        } else {
            echo "• $func(): ❌ Missing<br>";
        }
    }
    echo "</div>";
    
    // Test database tables
    echo "<div class='section'>";
    echo "<strong>🗄️ Checking Database Tables:</strong><br>";
    
    $required_tables = ['users', 'bookings', 'payments', 'bus_seats', 'activity_logs'];
    foreach ($required_tables as $table) {
        try {
            $stmt = $db->prepare("SELECT COUNT(*) as count FROM $table LIMIT 1");
            $stmt->execute();
            $count = $stmt->fetch()['count'];
            echo "• Table '$table': ✅ Accessible ($count records)<br>";
        } catch (Exception $e) {
            echo "• Table '$table': ❌ " . $e->getMessage() . "<br>";
        }
    }
    echo "</div>";
    
    // Test the booking API directly
    echo "<div class='section'>";
    echo "<strong>🔗 Testing Booking API Directly:</strong><br>";
    
    // Prepare test data
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
    
    echo "<strong>Test Data:</strong><br>";
    echo "<pre>" . json_encode($testData, JSON_PRETTY_PRINT) . "</pre>";
    
    // Make actual API call
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'http://localhost/bus/api/booking_clean.php?action=create_booking');
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($testData));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Cookie: ' . $_SERVER['HTTP_COOKIE'] // Pass session cookie
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    curl_close($ch);
    
    echo "<strong>HTTP Status:</strong> $http_code<br>";
    
    if ($curl_error) {
        echo "<div style='background: #f8d7da; padding: 10px; border-radius: 5px; margin-top: 10px;'>";
        echo "<strong>❌ CURL Error:</strong> $curl_error";
        echo "</div>";
    } else {
        echo "<strong>API Response:</strong><br>";
        echo "<pre>" . htmlspecialchars($response) . "</pre>";
        
        $api_data = json_decode($response, true);
        if ($api_data) {
            if ($api_data['success']) {
                echo "<div style='background: #d4edda; padding: 10px; border-radius: 5px; margin-top: 10px;'>";
                echo "<strong>✅ API Success:</strong> Booking API is working correctly!";
                echo "</div>";
            } else {
                echo "<div style='background: #f8d7da; padding: 10px; border-radius: 5px; margin-top: 10px;'>";
                echo "<strong>❌ API Error:</strong> " . ($api_data['message'] ?? 'Unknown error');
                if (isset($api_data['error_details'])) {
                    echo "<br><strong>Error Details:</strong><br>";
                    echo "<pre>" . json_encode($api_data['error_details'], JSON_PRETTY_PRINT) . "</pre>";
                }
                echo "</div>";
            }
        } else {
            echo "<div style='background: #f8d7da; padding: 10px; border-radius: 5px; margin-top: 10px;'>";
            echo "<strong>❌ Invalid JSON Response:</strong> API returned non-JSON data";
            echo "</div>";
        }
    }
    echo "</div>";
    
    // Test individual components
    echo "<div class='section'>";
    echo "<strong>🧪 Testing Individual Components:</strong><br>";
    
    try {
        // Test ID generation
        $testId = generateUniqueId('SWB');
        echo "• ID Generation: ✅ $testId<br>";
        
        // Test QR generation
        $qrData = generateQRData($testId, 'Test User', 'addis-ababa-bahirdar', date('Y-m-d'), ['A1']);
        echo "• QR Generation: ✅ " . substr($qrData, 0, 30) . "...<br>";
        
        // Test payment record (if function exists)
        if (function_exists('createPaymentRecord')) {
            $paymentId = createPaymentRecord('TEST_' . time(), 100.00, 'test');
            if ($paymentId) {
                echo "• Payment Record: ✅ Created $paymentId<br>";
                // Clean up
                $stmt = $db->prepare("DELETE FROM payments WHERE payment_id = ?");
                $stmt->execute([$paymentId]);
                echo "• Payment Cleanup: ✅ Test record removed<br>";
            } else {
                echo "• Payment Record: ⚠️ Function returned null<br>";
            }
        }
        
    } catch (Exception $e) {
        echo "• Component Test: ❌ " . $e->getMessage() . "<br>";
    }
    echo "</div>";
    
} catch (Exception $e) {
    echo "<div class='section error'>";
    echo "<strong>❌ Critical Error:</strong> " . $e->getMessage() . "<br>";
    echo "<strong>File:</strong> " . $e->getFile() . "<br>";
    echo "<strong>Line:</strong> " . $e->getLine() . "<br>";
    echo "</div>";
}

echo "<hr>";
echo "<p><strong>Next Steps:</strong></p>";
echo "<ul>";
echo "<li>If API test shows success: The backend is working, check frontend JavaScript</li>";
echo "<li>If API test shows error: Check the error details above</li>";
echo "<li>If components fail: Check the specific function that's failing</li>";
echo "</ul>";

echo "<p><a href='book-ticket.html'>→ Try Booking Again</a></p>";
echo "<p><a href='my-tickets.html'>→ Check My Tickets</a></p>";
?>