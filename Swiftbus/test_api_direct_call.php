<?php
/**
 * Direct API Call Test
 * Test the booking API directly to see the exact error
 */

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Start session and set up test user
session_start();

echo "<h1>Direct API Call Test</h1>";
echo "<style>
    body { font-family: Arial, sans-serif; margin: 20px; } 
    .success { color: green; } 
    .error { color: red; } 
    .info { color: blue; } 
    .warning { color: orange; }
    pre { background: #f5f5f5; padding: 10px; border-radius: 5px; overflow-x: auto; }
</style>";

// Set up test user session
try {
    require_once 'config/database.php';
    $database = new Database();
    $db = $database->getConnection();
    
    // Get first admin user
    $stmt = $db->query("SELECT * FROM users WHERE role = 'admin' LIMIT 1");
    $admin = $stmt->fetch();
    
    if ($admin) {
        $_SESSION['user_id'] = $admin['user_id'];
        echo "<span class='success'>✅ Test session set: " . $admin['user_id'] . " (" . $admin['email'] . ")</span><br>";
    } else {
        echo "<span class='error'>❌ No admin users found</span><br>";
        exit;
    }
} catch (Exception $e) {
    echo "<span class='error'>❌ Setup error: " . $e->getMessage() . "</span><br>";
    exit;
}

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

echo "<h2>Test Data</h2>";
echo "<pre>" . json_encode($testBookingData, JSON_PRETTY_PRINT) . "</pre>";

// Simulate the API call
echo "<h2>API Call Simulation</h2>";

// Set up request environment
$_SERVER['REQUEST_METHOD'] = 'POST';
$_GET['action'] = 'create_booking';

// Mock the input stream
$inputData = json_encode($testBookingData);

// Create a temporary file to simulate php://input
$tempFile = tempnam(sys_get_temp_dir(), 'api_test');
file_put_contents($tempFile, $inputData);

// Override file_get_contents for php://input
function mockFileGetContents($filename) {
    global $inputData;
    if ($filename === 'php://input') {
        return $inputData;
    }
    return file_get_contents($filename);
}

// Temporarily override the function
$originalFunction = 'file_get_contents';
if (!function_exists('original_file_get_contents')) {
    function original_file_get_contents($filename) {
        return file_get_contents($filename);
    }
}

echo "<span class='info'>🔄 Calling API...</span><br>";

// Capture all output and errors
ob_start();
$errorOutput = '';

// Set custom error handler
set_error_handler(function($severity, $message, $file, $line) use (&$errorOutput) {
    $errorOutput .= "PHP Error: $message in $file on line $line\n";
});

try {
    // Mock php://input by temporarily creating a stream
    $context = stream_context_create([
        'http' => [
            'method' => 'POST',
            'header' => 'Content-Type: application/json',
            'content' => $inputData
        ]
    ]);
    
    // Include the API file
    include 'api/booking_clean.php';
    
} catch (Exception $e) {
    echo "<span class='error'>❌ Exception caught: " . $e->getMessage() . "</span><br>";
    echo "<span class='error'>Stack trace:</span><br>";
    echo "<pre>" . $e->getTraceAsString() . "</pre>";
} catch (Error $e) {
    echo "<span class='error'>❌ Fatal error: " . $e->getMessage() . "</span><br>";
    echo "<span class='error'>Stack trace:</span><br>";
    echo "<pre>" . $e->getTraceAsString() . "</pre>";
}

// Restore error handler
restore_error_handler();

$output = ob_get_clean();

echo "<h2>API Output</h2>";
if (!empty($errorOutput)) {
    echo "<span class='error'>❌ PHP Errors:</span><br>";
    echo "<pre>" . htmlspecialchars($errorOutput) . "</pre>";
}

echo "<span class='info'>📋 Raw Output:</span><br>";
echo "<pre>" . htmlspecialchars($output) . "</pre>";

// Try to parse as JSON
if (!empty($output)) {
    $response = json_decode($output, true);
    if ($response) {
        echo "<h2>Parsed Response</h2>";
        if ($response['success']) {
            echo "<span class='success'>✅ Success: " . $response['message'] . "</span><br>";
            echo "<span class='info'>📋 Booking ID: " . $response['data']['booking_id'] . "</span><br>";
        } else {
            echo "<span class='error'>❌ Failed: " . $response['message'] . "</span><br>";
        }
        echo "<pre>" . json_encode($response, JSON_PRETTY_PRINT) . "</pre>";
    } else {
        echo "<span class='error'>❌ Output is not valid JSON</span><br>";
        echo "<span class='warning'>This usually indicates a PHP error or syntax issue</span><br>";
    }
} else {
    echo "<span class='error'>❌ No output from API</span><br>";
    echo "<span class='warning'>This usually indicates a fatal PHP error</span><br>";
}

// Clean up
unlink($tempFile);

echo "<h2>Additional Checks</h2>";

// Check if required files exist
$requiredFiles = [
    'api/booking_clean.php',
    'includes/functions.php',
    'config/database.php'
];

foreach ($requiredFiles as $file) {
    if (file_exists($file)) {
        echo "<span class='success'>✅ $file exists</span><br>";
    } else {
        echo "<span class='error'>❌ $file missing</span><br>";
    }
}

// Check PHP syntax
echo "<h3>PHP Syntax Check</h3>";
$syntaxCheck = shell_exec('php -l api/booking_clean.php 2>&1');
if (strpos($syntaxCheck, 'No syntax errors') !== false) {
    echo "<span class='success'>✅ booking_clean.php syntax OK</span><br>";
} else {
    echo "<span class='error'>❌ booking_clean.php syntax error:</span><br>";
    echo "<pre>" . htmlspecialchars($syntaxCheck) . "</pre>";
}

echo "<h2>Conclusion</h2>";
echo "<p>This test simulates the exact API call that book-ticket.html makes.</p>";
echo "<p>If you see errors above, those are the exact issues causing the booking creation to fail.</p>";
?>