<?php
/**
 * Fix Database Structure Issue
 * 
 * The problem is that config/database.php creates the old complex structure,
 * but booking_clean.php expects the simplified structure from swiftbus_database_clean.sql
 */

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>Database Structure Fix</h1>";
echo "<style>
    body { font-family: Arial, sans-serif; margin: 20px; } 
    .success { color: green; } 
    .error { color: red; } 
    .info { color: blue; } 
    .warning { color: orange; }
    pre { background: #f5f5f5; padding: 10px; border-radius: 5px; }
    .step { border: 1px solid #ddd; margin: 10px 0; padding: 15px; border-radius: 5px; }
    .step h3 { margin-top: 0; }
</style>";

echo "<div class='step'>";
echo "<h3>Problem Identified</h3>";
echo "<p><span class='error'>❌ The issue is a database structure mismatch:</span></p>";
echo "<ul>";
echo "<li><strong>config/database.php</strong> creates complex structure with schedules table</li>";
echo "<li><strong>booking_clean.php</strong> expects simplified structure with direct booking fields</li>";
echo "<li><strong>swiftbus_database_clean.sql</strong> has the correct simplified structure</li>";
echo "</ul>";
echo "</div>";

echo "<div class='step'>";
echo "<h3>Current Database Structure Check</h3>";

try {
    require_once 'config/database.php';
    $database = new Database();
    $db = $database->getConnection();
    echo "<span class='success'>✅ Database connection successful</span><br>";
    
    // Check if we have the old or new structure
    $stmt = $db->query("DESCRIBE bookings");
    $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    echo "<span class='info'>Current bookings table columns:</span><br>";
    echo "<pre>" . implode(', ', $columns) . "</pre>";
    
    $hasSimplifiedStructure = in_array('from_city', $columns) && in_array('to_city', $columns) && in_array('bus_company', $columns);
    
    if ($hasSimplifiedStructure) {
        echo "<span class='success'>✅ You have the CORRECT simplified structure!</span><br>";
        echo "<span class='info'>The booking API should work with this structure.</span><br>";
    } else {
        echo "<span class='error'>❌ You have the OLD complex structure!</span><br>";
        echo "<span class='warning'>⚠️ You need to import swiftbus_database_clean.sql</span><br>";
    }
    
} catch (Exception $e) {
    echo "<span class='error'>❌ Database error: " . $e->getMessage() . "</span><br>";
}
echo "</div>";

echo "<div class='step'>";
echo "<h3>Solution</h3>";
echo "<p>To fix the booking creation error, you need to:</p>";
echo "<ol>";
echo "<li><strong>Backup your current database</strong> (if you have important data)</li>";
echo "<li><strong>Import the clean database:</strong>";
echo "<ul>";
echo "<li>Open phpMyAdmin (http://localhost/phpmyadmin)</li>";
echo "<li>Drop the existing 'swiftbus_db' database</li>";
echo "<li>Create a new database named 'swiftbus_db'</li>";
echo "<li>Select the database and click 'Import'</li>";
echo "<li>Choose 'swiftbus_database_clean.sql' file</li>";
echo "<li>Click 'Go' to import</li>";
echo "</ul>";
echo "</li>";
echo "<li><strong>Test the booking creation again</strong></li>";
echo "</ol>";
echo "</div>";

echo "<div class='step'>";
echo "<h3>Alternative: Disable Auto-Initialization</h3>";
echo "<p>To prevent config/database.php from recreating the wrong structure:</p>";
echo "<ol>";
echo "<li>Comment out the auto-initialization in config/database.php</li>";
echo "<li>Or set \$_SESSION['db_initialized'] = true; manually</li>";
echo "</ol>";
echo "</div>";

echo "<div class='step'>";
echo "<h3>Expected Bookings Table Structure</h3>";
echo "<p>The correct structure should have these key columns:</p>";
echo "<pre>";
echo "- id (auto increment)\n";
echo "- booking_id (unique)\n";
echo "- user_id (foreign key)\n";
echo "- bus_company (varchar)\n";
echo "- bus_type (varchar)\n";
echo "- from_city (varchar)\n";
echo "- to_city (varchar)\n";
echo "- travel_date (date)\n";
echo "- departure_time (varchar)\n";
echo "- passenger_count (int)\n";
echo "- selected_seats (json)\n";
echo "- passenger_details (json)\n";
echo "- total_amount (decimal)\n";
echo "- booking_status (enum)\n";
echo "- payment_status (enum)\n";
echo "- ... other fields";
echo "</pre>";
echo "</div>";

// Check if clean database file exists
echo "<div class='step'>";
echo "<h3>File Check</h3>";
if (file_exists('swiftbus_database_clean.sql')) {
    echo "<span class='success'>✅ swiftbus_database_clean.sql file found</span><br>";
    $fileSize = filesize('swiftbus_database_clean.sql');
    echo "<span class='info'>File size: " . number_format($fileSize) . " bytes</span><br>";
} else {
    echo "<span class='error'>❌ swiftbus_database_clean.sql file not found</span><br>";
    echo "<span class='warning'>Make sure the file is in the root directory</span><br>";
}
echo "</div>";

echo "<h2>Summary</h2>";
echo "<p><strong>The booking creation error is caused by a database structure mismatch.</strong></p>";
echo "<p>Import the <code>swiftbus_database_clean.sql</code> file to fix this issue.</p>";
echo "<p>After importing, the booking creation should work properly.</p>";
?>