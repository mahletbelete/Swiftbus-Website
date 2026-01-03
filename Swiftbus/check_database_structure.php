<?php
/**
 * Check Database Structure
 */

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>SwiftBus Database Structure Check</h1>";
echo "<style>body { font-family: Arial, sans-serif; margin: 20px; } .success { color: green; } .error { color: red; } .info { color: blue; } table { border-collapse: collapse; width: 100%; } th, td { border: 1px solid #ddd; padding: 8px; text-align: left; } th { background-color: #f2f2f2; }</style>";

try {
    require_once 'config/database.php';
    $database = new Database();
    $db = $database->getConnection();
    echo "<span class='success'>✅ Database connection successful!</span><br><br>";
} catch (Exception $e) {
    echo "<span class='error'>❌ Database connection failed: " . $e->getMessage() . "</span><br>";
    exit;
}

// Check if we're using the clean database structure
echo "<h2>Database Tables</h2>";
try {
    $stmt = $db->query("SHOW TABLES");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    echo "<table>";
    echo "<tr><th>Table Name</th><th>Status</th></tr>";
    
    $expectedTables = [
        'users' => 'Required',
        'bookings' => 'Required',
        'bus_companies' => 'Required',
        'cities' => 'Required',
        'routes' => 'Required',
        'buses' => 'Required',
        'schedules' => 'Required',
        'bus_seats' => 'Required',
        'user_sessions' => 'Required',
        'activity_logs' => 'Required',
        'simple_bookings' => 'Should NOT exist (old table)'
    ];
    
    foreach ($expectedTables as $table => $status) {
        $exists = in_array($table, $tables);
        if ($table === 'simple_bookings') {
            // This table should NOT exist in clean database
            if ($exists) {
                echo "<tr><td>$table</td><td><span class='error'>❌ EXISTS (should be removed)</span></td></tr>";
            } else {
                echo "<tr><td>$table</td><td><span class='success'>✅ REMOVED (correct)</span></td></tr>";
            }
        } else {
            if ($exists) {
                echo "<tr><td>$table</td><td><span class='success'>✅ EXISTS</span></td></tr>";
            } else {
                echo "<tr><td>$table</td><td><span class='error'>❌ MISSING</span></td></tr>";
            }
        }
    }
    echo "</table><br>";
    
} catch (Exception $e) {
    echo "<span class='error'>❌ Error checking tables: " . $e->getMessage() . "</span><br>";
}

// Check users table
echo "<h2>Users Table</h2>";
try {
    $stmt = $db->query("SELECT COUNT(*) as count FROM users");
    $userCount = $stmt->fetch()['count'];
    echo "<span class='info'>Total users: $userCount</span><br>";
    
    if ($userCount > 0) {
        $stmt = $db->query("SELECT user_id, full_name, email, role FROM users LIMIT 10");
        $users = $stmt->fetchAll();
        
        echo "<table>";
        echo "<tr><th>User ID</th><th>Name</th><th>Email</th><th>Role</th></tr>";
        foreach ($users as $user) {
            echo "<tr><td>{$user['user_id']}</td><td>{$user['full_name']}</td><td>{$user['email']}</td><td>{$user['role']}</td></tr>";
        }
        echo "</table><br>";
    } else {
        echo "<span class='error'>❌ No users found! Database may not be initialized.</span><br>";
    }
} catch (Exception $e) {
    echo "<span class='error'>❌ Error checking users: " . $e->getMessage() . "</span><br>";
}

// Check bookings table structure
echo "<h2>Bookings Table Structure</h2>";
try {
    $stmt = $db->query("DESCRIBE bookings");
    $columns = $stmt->fetchAll();
    
    echo "<table>";
    echo "<tr><th>Column</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th></tr>";
    foreach ($columns as $column) {
        echo "<tr><td>{$column['Field']}</td><td>{$column['Type']}</td><td>{$column['Null']}</td><td>{$column['Key']}</td><td>{$column['Default']}</td></tr>";
    }
    echo "</table><br>";
    
    // Check if bookings table has data
    $stmt = $db->query("SELECT COUNT(*) as count FROM bookings");
    $bookingCount = $stmt->fetch()['count'];
    echo "<span class='info'>Total bookings: $bookingCount</span><br>";
    
} catch (Exception $e) {
    echo "<span class='error'>❌ Error checking bookings table: " . $e->getMessage() . "</span><br>";
}

// Check bus companies
echo "<h2>Bus Companies</h2>";
try {
    $stmt = $db->query("SELECT * FROM bus_companies");
    $companies = $stmt->fetchAll();
    
    echo "<table>";
    echo "<tr><th>Company ID</th><th>Name</th><th>Status</th></tr>";
    foreach ($companies as $company) {
        echo "<tr><td>{$company['company_id']}</td><td>{$company['name']}</td><td>{$company['status']}</td></tr>";
    }
    echo "</table><br>";
    
    if (count($companies) !== 4) {
        echo "<span class='error'>❌ Expected 4 bus companies, found " . count($companies) . "</span><br>";
    } else {
        echo "<span class='success'>✅ Correct number of bus companies (4)</span><br>";
    }
} catch (Exception $e) {
    echo "<span class='error'>❌ Error checking bus companies: " . $e->getMessage() . "</span><br>";
}

// Check cities
echo "<h2>Cities</h2>";
try {
    $stmt = $db->query("SELECT * FROM cities");
    $cities = $stmt->fetchAll();
    
    echo "<table>";
    echo "<tr><th>City Code</th><th>Name</th><th>Status</th></tr>";
    foreach ($cities as $city) {
        echo "<tr><td>{$city['city_code']}</td><td>{$city['name']}</td><td>{$city['status']}</td></tr>";
    }
    echo "</table><br>";
    
    if (count($cities) !== 10) {
        echo "<span class='error'>❌ Expected 10 cities, found " . count($cities) . "</span><br>";
    } else {
        echo "<span class='success'>✅ Correct number of cities (10)</span><br>";
    }
} catch (Exception $e) {
    echo "<span class='error'>❌ Error checking cities: " . $e->getMessage() . "</span><br>";
}

echo "<h2>Summary</h2>";
echo "<p>This diagnostic shows the current database structure. If you see any errors above, you may need to:</p>";
echo "<ul>";
echo "<li>Re-import the <code>swiftbus_database_clean.sql</code> file</li>";
echo "<li>Make sure you're using the correct database name</li>";
echo "<li>Check that all required tables exist</li>";
echo "<li>Verify that the <code>simple_bookings</code> table has been removed</li>";
echo "</ul>";
?>