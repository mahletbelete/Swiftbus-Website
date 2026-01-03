<?php
/**
 * Direct API Test for Booking Creation
 */

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Start session and set up test user
session_start();
$_SESSION['user_id'] = 'USR2025000001'; // Admin user

// Set up the request environment to simulate API call
$_SERVER['REQUEST_METHOD'] = 'POST';
$_GET['action'] = 'create_booking';

// Test booking data
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

// Mock the PHP input stream
$input = json_encode($testBookingData);

// Create a temporary file to simulate php://input
$temp = tmpfile();
fwrite($temp, $input);
rewind($temp);

// Override the input stream
stream_wrapper_unregister("php");
stream_wrapper_register("php", "MockPhpStream");

class MockPhpStream {
    private $position;
    private $data;
    
    public function __construct() {
        global $input;
        $this->data = $input;
        $this->position = 0;
    }
    
    public function stream_open($path, $mode, $options, &$opened_path) {
        return true;
    }
    
    public function stream_read($count) {
        $ret = substr($this->data, $this->position, $count);
        $this->position += strlen($ret);
        return $ret;
    }
    
    public function stream_eof() {
        return $this->position >= strlen($this->data);
    }
    
    public function stream_stat() {
        return array();
    }
}

echo "<h1>Direct API Test</h1>";
echo "<style>body { font-family: Arial, sans-serif; margin: 20px; } .success { color: green; } .error { color: red; } .info { color: blue; } pre { background: #f5f5f5; padding: 10px; border-radius: 5px; }</style>";

echo "<h2>Test Data</h2>";
echo "<pre>" . json_encode($testBookingData, JSON_PRETTY_PRINT) . "</pre>";

echo "<h2>API Response</h2>";

// Capture the API output
ob_start();

try {
    // Include the API file
    include 'api/booking_clean.php';
} catch (Exception $e) {
    echo "<span class='error'>❌ Exception: " . $e->getMessage() . "</span><br>";
    echo "<pre>" . $e->getTraceAsString() . "</pre>";
}

$output = ob_get_clean();

// Restore the original stream wrapper
stream_wrapper_restore("php");

echo "<pre>" . htmlspecialchars($output) . "</pre>";

// Try to decode the JSON response
$response = json_decode($output, true);
if ($response) {
    echo "<h2>Parsed Response</h2>";
    if ($response['success']) {
        echo "<span class='success'>✅ Booking creation successful!</span><br>";
        echo "<span class='info'>Booking ID: " . $response['data']['booking_id'] . "</span><br>";
    } else {
        echo "<span class='error'>❌ Booking creation failed: " . $response['message'] . "</span><br>";
    }
} else {
    echo "<span class='error'>❌ Could not parse API response as JSON</span><br>";
}
?>