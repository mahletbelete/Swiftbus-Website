<?php
echo "<h1>🔍 Duplicate Function Checker</h1>";
echo "<style>
    body { font-family: Arial, sans-serif; max-width: 1000px; margin: 0 auto; padding: 20px; }
    .success { color: green; background: #d4edda; padding: 10px; border-radius: 5px; margin: 5px 0; }
    .error { color: red; background: #f8d7da; padding: 10px; border-radius: 5px; margin: 5px 0; }
    .warning { color: orange; background: #fff3cd; padding: 10px; border-radius: 5px; margin: 5px 0; }
    pre { background: #f5f5f5; padding: 10px; border-radius: 3px; overflow-x: auto; font-size: 12px; }
</style>";

$files_to_check = [
    'includes/functions.php',
    'api/booking_clean.php',
    'api/booking.php'
];

$all_functions = [];
$duplicates = [];

foreach ($files_to_check as $file) {
    if (!file_exists($file)) {
        echo "<div class='warning'>⚠️ File not found: $file</div>";
        continue;
    }
    
    echo "<h2>Checking: $file</h2>";
    
    $content = file_get_contents($file);
    
    // Find all function declarations
    preg_match_all('/function\s+([a-zA-Z_][a-zA-Z0-9_]*)\s*\(/', $content, $matches);
    
    if (!empty($matches[1])) {
        echo "<div class='success'>Found " . count($matches[1]) . " functions:</div>";
        
        foreach ($matches[1] as $function_name) {
            echo "• $function_name<br>";
            
            if (isset($all_functions[$function_name])) {
                $duplicates[$function_name][] = $file;
                if (!in_array($all_functions[$function_name], $duplicates[$function_name])) {
                    $duplicates[$function_name][] = $all_functions[$function_name];
                }
            } else {
                $all_functions[$function_name] = $file;
            }
        }
    } else {
        echo "<div class='warning'>No functions found</div>";
    }
    
    echo "<hr>";
}

echo "<h2>🚨 Duplicate Functions Found</h2>";

if (empty($duplicates)) {
    echo "<div class='success'>✅ No duplicate functions found!</div>";
} else {
    foreach ($duplicates as $function_name => $files) {
        echo "<div class='error'>";
        echo "<strong>❌ Duplicate: $function_name()</strong><br>";
        echo "Found in: " . implode(', ', array_unique($files));
        echo "</div>";
    }
}

echo "<h2>📋 Summary</h2>";
echo "<div class='success'>Total unique functions: " . count($all_functions) . "</div>";
echo "<div class='error'>Duplicate functions: " . count($duplicates) . "</div>";

if (count($duplicates) > 0) {
    echo "<div class='warning'>";
    echo "<strong>⚠️ Action Required:</strong><br>";
    echo "Remove duplicate functions to fix the 'Cannot redeclare' errors.<br>";
    echo "Keep functions in includes/functions.php and remove from other files.";
    echo "</div>";
}

echo "<hr>";
echo "<p><a href='diagnose_booking_issue.php'>→ Test Booking After Fix</a></p>";
?>