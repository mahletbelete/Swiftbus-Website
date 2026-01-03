<?php
echo "<h1>PHP Syntax Checker</h1>";
echo "<style>
    body { font-family: Arial, sans-serif; max-width: 800px; margin: 0 auto; padding: 20px; }
    .success { color: green; }
    .error { color: red; background: #ffe6e6; padding: 10px; border-radius: 5px; }
</style>";

$files_to_check = [
    'includes/functions.php',
    'api/booking_clean.php',
    'api/auth.php'
];

foreach ($files_to_check as $file) {
    echo "<h2>Checking: $file</h2>";
    
    if (!file_exists($file)) {
        echo "<div class='error'>❌ File not found</div>";
        continue;
    }
    
    // Check PHP syntax
    $output = [];
    $return_code = 0;
    exec("php -l \"$file\" 2>&1", $output, $return_code);
    
    if ($return_code === 0) {
        echo "<div class='success'>✅ Syntax OK</div>";
    } else {
        echo "<div class='error'>❌ Syntax Error:<br>";
        echo implode("<br>", $output);
        echo "</div>";
    }
    
    echo "<hr>";
}

// Test if we can include the files
echo "<h2>Testing File Inclusion</h2>";

try {
    require_once 'includes/functions.php';
    echo "<div class='success'>✅ includes/functions.php loaded successfully</div>";
} catch (Exception $e) {
    echo "<div class='error'>❌ Error loading includes/functions.php: " . $e->getMessage() . "</div>";
}

echo "<hr>";
echo "<p><a href='test_booking_simple.php'>→ Test Booking Components</a></p>";
echo "<p><a href='debug_booking_error.php'>→ Full Booking Debug</a></p>";
?>