<?php
/**
 * Check PHP Errors in API Files
 */

echo "<h1>PHP Error Check</h1>";
echo "<style>
    body { font-family: Arial, sans-serif; margin: 20px; } 
    .success { color: green; } 
    .error { color: red; } 
    .info { color: blue; } 
    .warning { color: orange; }
    pre { background: #f5f5f5; padding: 10px; border-radius: 5px; overflow-x: auto; }
</style>";

echo "<h2>PHP Syntax Check</h2>";

$apiFiles = [
    'api/booking_clean.php',
    'api/auth.php',
    'includes/functions.php',
    'config/database.php'
];

foreach ($apiFiles as $file) {
    echo "<h3>Checking: $file</h3>";
    
    if (!file_exists($file)) {
        echo "<span class='error'>❌ File not found: $file</span><br>";
        continue;
    }
    
    // Check PHP syntax
    $output = [];
    $return_var = 0;
    exec("php -l \"$file\" 2>&1", $output, $return_var);
    
    if ($return_var === 0) {
        echo "<span class='success'>✅ Syntax OK</span><br>";
    } else {
        echo "<span class='error'>❌ Syntax Error:</span><br>";
        echo "<pre>" . implode("\n", $output) . "</pre>";
    }
}

echo "<h2>Test API Endpoints</h2>";

// Test basic API functionality
echo "<h3>Testing booking_clean.php</h3>";

// Capture any output/errors
ob_start();
$error_output = '';

// Set error handler
set_error_handler(function($severity, $message, $file, $line) use (&$error_output) {
    $error_output .= "Error: $message in " . basename($file) . " on line $line\n";
});

try {
    // Test if we can include the API file without errors
    $_GET['action'] = 'test';
    include 'api/booking_clean.php';
    
} catch (Exception $e) {
    $error_output .= "Exception: " . $e->getMessage() . "\n";
} catch (Error $e) {
    $error_output .= "Fatal Error: " . $e->getMessage() . "\n";
}

restore_error_handler();
$output = ob_get_clean();

if (empty($error_output)) {
    echo "<span class='success'>✅ API file loads without errors</span><br>";
    if (!empty($output)) {
        echo "<span class='info'>API Output:</span><br>";
        echo "<pre>" . htmlspecialchars($output) . "</pre>";
    }
} else {
    echo "<span class='error'>❌ API has errors:</span><br>";
    echo "<pre>" . htmlspecialchars($error_output) . "</pre>";
}

echo "<h2>Database Connection Test</h2>";
try {
    require_once 'config/database.php';
    $database = new Database();
    $db = $database->getConnection();
    echo "<span class='success'>✅ Database connection OK</span><br>";
    
    // Test a simple query
    $stmt = $db->query("SELECT 1");
    echo "<span class='success'>✅ Database query OK</span><br>";
    
} catch (Exception $e) {
    echo "<span class='error'>❌ Database error: " . $e->getMessage() . "</span><br>";
}

echo "<h2>Session Test</h2>";
session_start();
echo "<span class='info'>Session ID: " . session_id() . "</span><br>";

echo "<h2>Required Functions Test</h2>";
$requiredFunctions = [
    'getDB',
    'sanitizeInput',
    'isLoggedIn',
    'getCurrentUser',
    'getCurrentUserId',
    'generateUniqueId',
    'generateQRData',
    'handleSuccess',
    'handleError'
];

foreach ($requiredFunctions as $func) {
    if (function_exists($func)) {
        echo "<span class='success'>✅ Function $func exists</span><br>";
    } else {
        echo "<span class='error'>❌ Function $func missing</span><br>";
    }
}

echo "<h2>Recommendations</h2>";
echo "<ul>";
echo "<li>If you see syntax errors above, fix them first</li>";
echo "<li>If functions are missing, add them to includes/functions.php</li>";
echo "<li>If database connection fails, check config/database.php</li>";
echo "<li>Test the API at: <a href='api/booking_clean.php?action=test'>api/booking_clean.php?action=test</a></li>";
echo "</ul>";
?>