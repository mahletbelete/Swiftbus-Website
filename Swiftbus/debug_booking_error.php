<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);

session_start();
header('Content-Type: text/html; charset=utf-8');

echo "<h1>🔍 Debug Booking Error</h1>";
echo "<style>
    body { font-family: Arial, sans-serif; max-width: 1000px; margin: 0 auto; padding: 20px; }
    .section { background: #f8f9fa; padding: 15px; margin: 15px 0; border-radius: 5px; border-left: 4px solid #007cba; }
    .success { border-left-color: #28a745; background: #d4edda; }
    .error { border-left-color: #dc3545; background: #f8d7da; }
    .warning { border-left-color: #ffc107; background: #fff3cd; }
    pre { background: #f5f5f5; padding: 10px; border-radius: 3px; overflow-x: auto; font-size: 12px; }
    button { background: #007cba; color: white; border: none; padding: 10px 20px; border-radius: 5px; cursor: pointer; margin: 5px; }
</style>";

try {
    require_once 'includes/functions.php';
    
    echo "<div class='section success'>";
    echo "<strong>✅ Database Connection:</strong> Success<br>";
    echo "</div>";
    
    // Check session
    if (!isset($_SESSION['user_id'])) {
        echo "<div class='section error'>";
        echo "<strong>❌ No Session:</strong> Please <a href='login.html'>login first</a> before testing booking.";
        echo "</div>";
        exit;
    }
    
    echo "<div class='section success'>";
    echo "<strong>✅ Session Active:</strong> " . $_SESSION['user_id'] . "<br>";
    echo "</div>";
    
    // Test the booking API with sample data
    echo "<div class='section'>";
    echo "<strong>🧪 Testing Booking Creation API...</strong><br>";
    
    // Simulate a booking request
    $testBookingData = [
        'from_city' => 'addis-ababa',
        'to_city' => 'bahirdar',
        'departure_date' => date('Y-m-d', strtotime('+1 day')), // Tomorrow
        'departure_time' => '08:00',
        'bus_company' => 'selam-bus',
        'bus_type' => 'premium-ac',
        'passenger_count' => 1,
        'selected_seats' => ['A1'],
        'passenger_details' => [
            'fullName' => 'Test User',
            'email' => 'test@example.com',
            'phone' => '+251911234567',
            'idNumber' => 'TEST123456'
        ],
        'total_amount' => 520.00,
        'payment_method' => 'telebirr',
        'special_requirements' => ''
    ];
    
    echo "<strong>Test booking data:</strong><br>";
    echo "<pre>" . json_encode($testBookingData, JSON_PRETTY_PRINT) . "</pre>";
    
    // Test each required function
    echo "<strong>Testing required functions...</strong><br>";
    
    // Test generateUniqueId
    try {
        $testId = generateUniqueId('SWB');
        echo "• generateUniqueId(): ✅ " . $testId . "<br>";
    } catch (Exception $e) {
        echo "• generateUniqueId(): ❌ " . $e->getMessage() . "<br>";
    }
    
    // Test getCurrentUser
    try {
        $user = getCurrentUser();
        if ($user) {
            echo "• getCurrentUser(): ✅ Found user: " . $user['full_name'] . "<br>";
        } else {
            echo "• getCurrentUser(): ❌ No user found<br>";
        }
    } catch (Exception $e) {
        echo "• getCurrentUser(): ❌ " . $e->getMessage() . "<br>";
    }
    
    // Test generateQRData
    try {
        $qrData = generateQRData('TEST123', 'Test User', 'addis-ababa-bahirdar', date('Y-m-d'), ['A1']);
        echo "• generateQRData(): ✅ " . substr($qrData, 0, 50) . "...<br>";
    } catch (Exception $e) {
        echo "• generateQRData(): ❌ " . $e->getMessage() . "<br>";
    }
    
    // Test createPaymentRecord
    try {
        $paymentId = createPaymentRecord('TEST123', 520.00, 'telebirr');
        if ($paymentId) {
            echo "• createPaymentRecord(): ✅ Created payment: " . $paymentId . "<br>";
        } else {
            echo "• createPaymentRecord(): ⚠️ Function exists but returned null<br>";
        }
    } catch (Exception $e) {
        echo "• createPaymentRecord(): ❌ " . $e->getMessage() . "<br>";
    }
    
    echo "</div>";
    
    // Test database tables
    echo "<div class='section'>";
    echo "<strong>🗄️ Testing Database Tables...</strong><br>";
    
    $db = getDB();
    
    $tables = ['users', 'bookings', 'payments', 'bus_seats', 'activity_logs'];
    foreach ($tables as $table) {
        try {
            $stmt = $db->prepare("SELECT COUNT(*) as count FROM $table");
            $stmt->execute();
            $count = $stmt->fetch()['count'];
            echo "• Table '$table': ✅ $count records<br>";
        } catch (Exception $e) {
            echo "• Table '$table': ❌ " . $e->getMessage() . "<br>";
        }
    }
    echo "</div>";
    
    // Test the actual API call
    echo "<div class='section'>";
    echo "<strong>🔗 Testing Actual API Call...</strong><br>";
    
    // Prepare POST data
    $_POST['action'] = 'create_booking';
    $_SERVER['REQUEST_METHOD'] = 'POST';
    
    // Capture the API output
    ob_start();
    
    // Simulate the JSON input
    $json_input = json_encode($testBookingData);
    
    // Mock the php://input
    $temp_file = tmpfile();
    fwrite($temp_file, $json_input);
    rewind($temp_file);
    
    try {
        // Include the API file to test it
        include 'api/booking_clean.php';
        $api_output = ob_get_clean();
        
        echo "<strong>API Response:</strong><br>";
        echo "<pre>" . htmlspecialchars($api_output) . "</pre>";
        
        // Try to parse as JSON
        $api_data = json_decode($api_output, true);
        if ($api_data) {
            if ($api_data['success']) {
                echo "<div style='background: #d4edda; padding: 10px; border-radius: 5px; margin-top: 10px;'>";
                echo "<strong>✅ API Success:</strong> Booking would be created successfully";
                echo "</div>";
            } else {
                echo "<div style='background: #f8d7da; padding: 10px; border-radius: 5px; margin-top: 10px;'>";
                echo "<strong>❌ API Error:</strong> " . ($api_data['message'] ?? 'Unknown error');
                if (isset($api_data['error_details'])) {
                    echo "<br><strong>Details:</strong> " . json_encode($api_data['error_details']);
                }
                echo "</div>";
            }
        } else {
            echo "<div style='background: #f8d7da; padding: 10px; border-radius: 5px; margin-top: 10px;'>";
            echo "<strong>❌ JSON Parse Error:</strong> API returned invalid JSON";
            echo "</div>";
        }
        
    } catch (Exception $e) {
        ob_end_clean();
        echo "<div style='background: #f8d7da; padding: 10px; border-radius: 5px;'>";
        echo "<strong>❌ API Exception:</strong> " . $e->getMessage() . "<br>";
        echo "<strong>File:</strong> " . $e->getFile() . "<br>";
        echo "<strong>Line:</strong> " . $e->getLine() . "<br>";
        echo "</div>";
    }
    
    fclose($temp_file);
    echo "</div>";
    
    // Check PHP error log
    echo "<div class='section'>";
    echo "<strong>📋 Recent PHP Errors:</strong><br>";
    
    $error_log_path = ini_get('error_log');
    if ($error_log_path && file_exists($error_log_path)) {
        $errors = file_get_contents($error_log_path);
        $recent_errors = array_slice(explode("\n", $errors), -20); // Last 20 lines
        
        echo "<strong>Error log path:</strong> $error_log_path<br>";
        echo "<strong>Recent errors:</strong><br>";
        echo "<pre>" . htmlspecialchars(implode("\n", $recent_errors)) . "</pre>";
    } else {
        echo "Error log not found or not configured.<br>";
        echo "Check XAMPP error logs in: C:\\xampp\\apache\\logs\\error.log<br>";
    }
    echo "</div>";
    
} catch (Exception $e) {
    echo "<div class='section error'>";
    echo "<strong>❌ Critical Error:</strong> " . $e->getMessage() . "<br>";
    echo "<strong>File:</strong> " . $e->getFile() . "<br>";
    echo "<strong>Line:</strong> " . $e->getLine() . "<br>";
    echo "<strong>Stack Trace:</strong><br>";
    echo "<pre>" . $e->getTraceAsString() . "</pre>";
    echo "</div>";
}

echo "<hr>";
echo "<p><a href='book-ticket.html'>→ Try Booking Again</a></p>";
echo "<p><a href='debug_session.php'>→ Check Session</a></p>";
?>