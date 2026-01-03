<?php
/**
 * SwiftBus Initialization Script
 * 
 * Run this file once to set up the database and initial data
 */

session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

echo "<!DOCTYPE html>
<html lang='en'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>SwiftBus - Database Initialization</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 800px; margin: 50px auto; padding: 20px; }
        .success { color: #28a745; }
        .error { color: #dc3545; }
        .info { color: #17a2b8; }
        .step { margin: 20px 0; padding: 15px; border-left: 4px solid #007bff; background: #f8f9fa; }
    </style>
</head>
<body>
    <h1>SwiftBus Database Initialization</h1>";

try {
    echo "<div class='step'>";
    echo "<h3>Step 1: Database Connection</h3>";
    
    $database = new Database();
    $db = $database->getConnection();
    
    if ($db) {
        echo "<p class='success'>✓ Database connection successful</p>";
    } else {
        throw new Exception("Failed to connect to database");
    }
    echo "</div>";

    echo "<div class='step'>";
    echo "<h3>Step 2: Initialize Database Schema</h3>";
    
    if ($database->initializeDatabase()) {
        echo "<p class='success'>✓ Database schema created successfully</p>";
        echo "<p class='info'>• Created all necessary tables</p>";
        echo "<p class='info'>• Inserted initial cities and bus companies</p>";
        echo "<p class='info'>• Created default admin user</p>";
    } else {
        throw new Exception("Failed to initialize database schema");
    }
    echo "</div>";

    echo "<div class='step'>";
    echo "<h3>Step 3: Insert Sample Data</h3>";
    
    // Insert sample routes
    insertSampleRoutes($db);
    echo "<p class='success'>✓ Sample routes inserted</p>";
    
    // Insert sample buses
    insertSampleBuses($db);
    echo "<p class='success'>✓ Sample buses inserted</p>";
    
    // Insert sample schedules
    insertSampleSchedules($db);
    echo "<p class='success'>✓ Sample schedules inserted</p>";
    
    // Insert sample users
    insertSampleUsers($db);
    echo "<p class='success'>✓ Sample users inserted</p>";
    
    echo "</div>";

    echo "<div class='step'>";
    echo "<h3>Step 4: Verification</h3>";
    
    // Verify data
    $stats = verifyData($db);
    echo "<p class='success'>✓ Database verification complete</p>";
    echo "<ul>";
    echo "<li>Cities: {$stats['cities']}</li>";
    echo "<li>Bus Companies: {$stats['companies']}</li>";
    echo "<li>Routes: {$stats['routes']}</li>";
    echo "<li>Buses: {$stats['buses']}</li>";
    echo "<li>Schedules: {$stats['schedules']}</li>";
    echo "<li>Users: {$stats['users']}</li>";
    echo "</ul>";
    echo "</div>";

    echo "<div class='step'>";
    echo "<h3>🎉 Initialization Complete!</h3>";
    echo "<p class='success'>Your SwiftBus application is ready to use.</p>";
    echo "<h4>Default Admin Account:</h4>";
    echo "<ul>";
    echo "<li><strong>Email:</strong> admin@swiftbus.et</li>";
    echo "<li><strong>Password:</strong> admin123</li>";
    echo "</ul>";
    echo "<h4>Test User Account:</h4>";
    echo "<ul>";
    echo "<li><strong>Email:</strong> user@test.com</li>";
    echo "<li><strong>Password:</strong> password123</li>";
    echo "</ul>";
    echo "<p><a href='index.html' style='background: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Go to Application</a></p>";
    echo "</div>";

} catch (Exception $e) {
    echo "<div class='step'>";
    echo "<h3 class='error'>❌ Initialization Failed</h3>";
    echo "<p class='error'>Error: " . $e->getMessage() . "</p>";
    echo "<p>Please check your database configuration and try again.</p>";
    echo "</div>";
}

echo "</body></html>";

/**
 * Insert sample routes
 */
function insertSampleRoutes($db) {
    $routes = [
        // Major routes from Addis Ababa
        ['addis-ababa', 'bahir-dar', 565, 8.5, 450],
        ['addis-ababa', 'gondar', 748, 11.0, 580],
        ['addis-ababa', 'mekele', 783, 12.0, 620],
        ['addis-ababa', 'hawassa', 275, 4.5, 320],
        ['addis-ababa', 'dire-dawa', 515, 8.0, 480],
        ['addis-ababa', 'jimma', 352, 6.0, 380],
        ['addis-ababa', 'adama', 99, 1.5, 150],
        ['addis-ababa', 'dessie', 401, 6.5, 420],
        ['addis-ababa', 'kombolcha', 376, 6.0, 400],
        ['addis-ababa', 'arbaminch', 505, 8.0, 450],
        
        // Inter-city routes
        ['bahir-dar', 'gondar', 180, 3.0, 220],
        ['bahir-dar', 'mekele', 680, 10.0, 520],
        ['hawassa', 'arbaminch', 230, 3.5, 280],
        ['hawassa', 'adama', 210, 3.0, 250],
        ['jimma', 'hawassa', 290, 4.5, 320],
        ['dessie', 'mekele', 382, 6.0, 380],
        ['dessie', 'kombolcha', 25, 0.5, 80],
        ['adama', 'dire-dawa', 416, 6.5, 420],
        
        // Return routes (reverse direction)
        ['bahir-dar', 'addis-ababa', 565, 8.5, 450],
        ['gondar', 'addis-ababa', 748, 11.0, 580],
        ['mekele', 'addis-ababa', 783, 12.0, 620],
        ['hawassa', 'addis-ababa', 275, 4.5, 320],
        ['dire-dawa', 'addis-ababa', 515, 8.0, 480],
        ['jimma', 'addis-ababa', 352, 6.0, 380],
        ['adama', 'addis-ababa', 99, 1.5, 150],
        ['dessie', 'addis-ababa', 401, 6.5, 420],
        ['kombolcha', 'addis-ababa', 376, 6.0, 400],
        ['arbaminch', 'addis-ababa', 505, 8.0, 450]
    ];
    
    foreach ($routes as $route) {
        // Get city IDs
        $stmt = $db->prepare("SELECT id FROM cities WHERE city_code = ?");
        $stmt->execute([$route[0]]);
        $originCity = $stmt->fetch();
        
        $stmt->execute([$route[1]]);
        $destinationCity = $stmt->fetch();
        
        if ($originCity && $destinationCity) {
            // Check if route already exists
            $stmt = $db->prepare("
                SELECT id FROM routes 
                WHERE origin_city_id = ? AND destination_city_id = ?
            ");
            $stmt->execute([$originCity['id'], $destinationCity['id']]);
            
            if (!$stmt->fetch()) {
                $routeId = generateUniqueId('RT');
                $stmt = $db->prepare("
                    INSERT INTO routes (route_id, origin_city_id, destination_city_id, distance_km, estimated_duration_hours, base_price) 
                    VALUES (?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([$routeId, $originCity['id'], $destinationCity['id'], $route[2], $route[3], $route[4]]);
            }
        }
    }
}

/**
 * Insert sample buses
 */
function insertSampleBuses($db) {
    $companies = ['selam-bus', 'abay-bus', 'sky-bus', 'ethio-bus', 'habesha-bus', 'zemen-bus'];
    $busTypes = ['economy', 'standard', 'standard-ac', 'premium-ac', 'luxury'];
    $amenities = [
        'economy' => ['Basic Seating'],
        'standard' => ['Comfortable Seats', 'Reading Light'],
        'standard-ac' => ['Air Conditioning', 'Comfortable Seats', 'Reading Light'],
        'premium-ac' => ['Air Conditioning', 'Reclining Seats', 'WiFi', 'Charging Ports'],
        'luxury' => ['Air Conditioning', 'Luxury Seats', 'WiFi', 'Entertainment', 'Toilet', 'Refreshments']
    ];
    
    $seatCounts = [
        'economy' => 50,
        'standard' => 45,
        'standard-ac' => 40,
        'premium-ac' => 35,
        'luxury' => 30
    ];
    
    foreach ($companies as $companyCode) {
        // Get company ID
        $stmt = $db->prepare("SELECT id FROM bus_companies WHERE company_id = ?");
        $stmt->execute([$companyCode]);
        $company = $stmt->fetch();
        
        if ($company) {
            // Add 3-5 buses per company
            $busCount = rand(3, 5);
            for ($i = 1; $i <= $busCount; $i++) {
                $busType = $busTypes[array_rand($busTypes)];
                $busId = generateUniqueId('BUS');
                $busNumber = strtoupper(substr($companyCode, 0, 3)) . '-' . str_pad($i, 3, '0', STR_PAD_LEFT);
                
                $stmt = $db->prepare("
                    INSERT INTO buses (bus_id, company_id, bus_number, bus_type, total_seats, amenities, license_plate, model, year_manufactured) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                
                $stmt->execute([
                    $busId,
                    $company['id'],
                    $busNumber,
                    $busType,
                    $seatCounts[$busType],
                    json_encode($amenities[$busType]),
                    'ET-' . rand(1000, 9999),
                    'Mercedes Benz',
                    rand(2018, 2023)
                ]);
            }
        }
    }
}

/**
 * Insert sample schedules
 */
function insertSampleSchedules($db) {
    // Get all routes and buses
    $stmt = $db->query("SELECT id, route_id FROM routes LIMIT 20"); // Limit for demo
    $routes = $stmt->fetchAll();
    
    $stmt = $db->query("SELECT id, bus_id FROM buses");
    $buses = $stmt->fetchAll();
    
    $departureTimes = ['06:00:00', '08:00:00', '10:00:00', '14:00:00', '16:00:00', '18:00:00', '20:00:00'];
    $daysOfWeek = [
        ['monday', 'wednesday', 'friday'],
        ['tuesday', 'thursday', 'saturday'],
        ['monday', 'tuesday', 'wednesday', 'thursday', 'friday'],
        ['saturday', 'sunday'],
        ['monday', 'wednesday', 'friday', 'sunday']
    ];
    
    foreach ($routes as $route) {
        // Add 1-3 schedules per route
        $scheduleCount = rand(1, 3);
        $usedBuses = [];
        
        for ($i = 0; $i < $scheduleCount && $i < count($buses); $i++) {
            // Select a random bus that hasn't been used for this route
            do {
                $bus = $buses[array_rand($buses)];
            } while (in_array($bus['id'], $usedBuses));
            
            $usedBuses[] = $bus['id'];
            
            $departureTime = $departureTimes[array_rand($departureTimes)];
            $arrivalTime = date('H:i:s', strtotime($departureTime) + (rand(2, 12) * 3600)); // 2-12 hours later
            
            $scheduleId = generateUniqueId('SCH');
            
            $stmt = $db->prepare("
                INSERT INTO schedules (schedule_id, bus_id, route_id, departure_time, arrival_time, days_of_week, price, effective_from) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $scheduleId,
                $bus['id'],
                $route['id'],
                $departureTime,
                $arrivalTime,
                json_encode($daysOfWeek[array_rand($daysOfWeek)]),
                rand(200, 800), // Random price between 200-800 ETB
                date('Y-m-d')
            ]);
        }
    }
}

/**
 * Insert sample users
 */
function insertSampleUsers($db) {
    $users = [
        [
            'email' => 'user@test.com',
            'password' => 'password123',
            'first_name' => 'Test',
            'last_name' => 'User',
            'phone' => '+251911234567',
            'role' => 'user'
        ],
        [
            'email' => 'john.doe@email.com',
            'password' => 'password123',
            'first_name' => 'John',
            'last_name' => 'Doe',
            'phone' => '+251922345678',
            'role' => 'user'
        ],
        [
            'email' => 'jane.smith@email.com',
            'password' => 'password123',
            'first_name' => 'Jane',
            'last_name' => 'Smith',
            'phone' => '+251933456789',
            'role' => 'user'
        ]
    ];
    
    foreach ($users as $userData) {
        // Check if user already exists
        $stmt = $db->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$userData['email']]);
        
        if (!$stmt->fetch()) {
            $userId = generateUniqueId('U');
            $fullName = $userData['first_name'] . ' ' . $userData['last_name'];
            
            $stmt = $db->prepare("
                INSERT INTO users (user_id, email, password_hash, first_name, last_name, full_name, phone, role, is_verified, joined_date) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $userId,
                $userData['email'],
                hashPassword($userData['password']),
                $userData['first_name'],
                $userData['last_name'],
                $fullName,
                $userData['phone'],
                $userData['role'],
                1, // Verified
                date('Y-m-d')
            ]);
        }
    }
}

/**
 * Verify inserted data
 */
function verifyData($db) {
    $stats = [];
    
    $tables = ['cities', 'bus_companies', 'routes', 'buses', 'schedules', 'users'];
    
    foreach ($tables as $table) {
        $stmt = $db->query("SELECT COUNT(*) as count FROM $table");
        $result = $stmt->fetch();
        $stats[str_replace('bus_', '', $table)] = $result['count'];
    }
    
    return $stats;
}
?>