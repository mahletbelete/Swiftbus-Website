<?php
require_once 'includes/functions.php';

// Set execution time limit for large data operations
set_time_limit(300);

echo "<h1>SwiftBus Database Population</h1>\n";
echo "<pre>\n";

try {
    $db = getDB();
    
    echo "🚀 Starting database population...\n\n";
    
    // Read the SQL file
    $sqlFile = 'populate_database.sql';
    if (!file_exists($sqlFile)) {
        throw new Exception("SQL file not found: $sqlFile");
    }
    
    $sql = file_get_contents($sqlFile);
    if ($sql === false) {
        throw new Exception("Failed to read SQL file");
    }
    
    echo "📄 SQL file loaded successfully\n";
    
    // Split SQL into individual statements
    $statements = array_filter(
        array_map('trim', explode(';', $sql)),
        function($stmt) {
            return !empty($stmt) && !preg_match('/^\s*--/', $stmt);
        }
    );
    
    echo "📊 Found " . count($statements) . " SQL statements to execute\n\n";
    
    // Execute each statement
    $successCount = 0;
    $errorCount = 0;
    
    foreach ($statements as $index => $statement) {
        try {
            // Skip comments and empty statements
            if (empty(trim($statement)) || preg_match('/^\s*--/', $statement)) {
                continue;
            }
            
            $stmt = $db->prepare($statement);
            $result = $stmt->execute();
            
            if ($result) {
                $successCount++;
                
                // Show progress for important operations
                if (preg_match('/INSERT INTO `(\w+)`/', $statement, $matches)) {
                    $tableName = $matches[1];
                    $rowCount = $stmt->rowCount();
                    echo "✅ Inserted $rowCount rows into $tableName\n";
                } elseif (preg_match('/DELETE FROM `(\w+)`/', $statement, $matches)) {
                    $tableName = $matches[1];
                    $rowCount = $stmt->rowCount();
                    echo "🗑️  Cleared $rowCount rows from $tableName\n";
                } elseif (preg_match('/DROP TABLE IF EXISTS `(\w+)`/', $statement, $matches)) {
                    $tableName = $matches[1];
                    echo "🗑️  Dropped table $tableName\n";
                } elseif (preg_match('/UPDATE `(\w+)`/', $statement, $matches)) {
                    $tableName = $matches[1];
                    $rowCount = $stmt->rowCount();
                    echo "🔄 Updated $rowCount rows in $tableName\n";
                } elseif (preg_match('/SELECT.*as\s+(\w+)/', $statement, $matches)) {
                    $result = $stmt->fetch(PDO::FETCH_ASSOC);
                    if ($result) {
                        $key = array_keys($result)[0];
                        $value = $result[$key];
                        echo "📊 $key: $value\n";
                    }
                }
            } else {
                $errorCount++;
                echo "❌ Failed to execute statement " . ($index + 1) . "\n";
            }
            
        } catch (Exception $e) {
            $errorCount++;
            echo "❌ Error in statement " . ($index + 1) . ": " . $e->getMessage() . "\n";
            
            // Show the problematic statement for debugging
            if (strlen($statement) < 200) {
                echo "   Statement: " . trim($statement) . "\n";
            }
        }
    }
    
    echo "\n" . str_repeat("=", 50) . "\n";
    echo "📊 EXECUTION SUMMARY\n";
    echo str_repeat("=", 50) . "\n";
    echo "✅ Successful operations: $successCount\n";
    echo "❌ Failed operations: $errorCount\n";
    
    if ($errorCount === 0) {
        echo "\n🎉 Database populated successfully!\n";
    } else {
        echo "\n⚠️  Database populated with some errors. Check the log above.\n";
    }
    
    // Verify the data
    echo "\n" . str_repeat("-", 30) . "\n";
    echo "📋 DATA VERIFICATION\n";
    echo str_repeat("-", 30) . "\n";
    
    $tables = [
        'cities' => 'Cities',
        'bus_companies' => 'Bus Companies', 
        'buses' => 'Buses',
        'routes' => 'Routes',
        'schedules' => 'Schedules'
    ];
    
    foreach ($tables as $table => $label) {
        try {
            $stmt = $db->query("SELECT COUNT(*) as count FROM $table");
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            echo "📊 $label: " . $result['count'] . " records\n";
        } catch (Exception $e) {
            echo "❌ Error counting $label: " . $e->getMessage() . "\n";
        }
    }
    
    // Show sample data
    echo "\n" . str_repeat("-", 30) . "\n";
    echo "🔍 SAMPLE DATA\n";
    echo str_repeat("-", 30) . "\n";
    
    // Sample cities
    try {
        $stmt = $db->query("SELECT name FROM cities LIMIT 5");
        $cities = $stmt->fetchAll(PDO::FETCH_COLUMN);
        echo "🏙️  Cities: " . implode(', ', $cities) . "...\n";
    } catch (Exception $e) {
        echo "❌ Error fetching cities: " . $e->getMessage() . "\n";
    }
    
    // Sample companies
    try {
        $stmt = $db->query("SELECT name FROM bus_companies");
        $companies = $stmt->fetchAll(PDO::FETCH_COLUMN);
        echo "🚌 Companies: " . implode(', ', $companies) . "\n";
    } catch (Exception $e) {
        echo "❌ Error fetching companies: " . $e->getMessage() . "\n";
    }
    
    // Sample buses
    try {
        $stmt = $db->query("SELECT bus_number, bus_type FROM buses LIMIT 5");
        $buses = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo "🚐 Sample Buses:\n";
        foreach ($buses as $bus) {
            echo "   - {$bus['bus_number']} ({$bus['bus_type']})\n";
        }
    } catch (Exception $e) {
        echo "❌ Error fetching buses: " . $e->getMessage() . "\n";
    }
    
    echo "\n✅ Database population completed!\n";
    echo "🔗 You can now test the admin pages with real data.\n";
    
} catch (Exception $e) {
    echo "💥 FATAL ERROR: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}

echo "</pre>\n";
?>

<!DOCTYPE html>
<html>
<head>
    <title>Database Population Complete</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .success { color: green; }
        .error { color: red; }
        .info { color: blue; }
        pre { background: #f5f5f5; padding: 15px; border-radius: 5px; }
        .next-steps { background: #e8f5e9; padding: 15px; border-radius: 5px; margin-top: 20px; }
    </style>
</head>
<body>
    <div class="next-steps">
        <h2>🎯 Next Steps</h2>
        <ol>
            <li><strong>Test Admin Pages:</strong> Visit the admin pages to see real data:
                <ul>
                    <li><a href="admin-dashboard.html">Admin Dashboard</a></li>
                    <li><a href="admin-buses-list.html">Buses Management</a></li>
                    <li><a href="admin-routes-list.html">Routes Management</a></li>
                    <li><a href="admin-schedules-list.html">Schedules Management</a></li>
                    <li><a href="admin-users-list.html">Users Management</a></li>
                </ul>
            </li>
            <li><strong>Test API Endpoints:</strong> <a href="test_admin_apis.html">Test Admin APIs</a></li>
            <li><strong>Login as Admin:</strong> Use any of the 5 admin accounts with password <code>Admin@123</code></li>
        </ol>
    </div>
</body>
</html>