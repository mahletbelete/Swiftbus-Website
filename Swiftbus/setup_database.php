<?php
require_once 'config/database.php';

try {
    $database = new Database();
    $db = $database->getConnection();
    
    echo "<h2>SwiftBus Database Setup</h2>\n";
    echo "<p>Setting up database with sample data...</p>\n";
    
    // First, get company IDs from the existing companies
    echo "<h3>Getting Company Information...</h3>\n";
    $companyStmt = $db->prepare("SELECT id, name FROM bus_companies ORDER BY id");
    $companyStmt->execute();
    $companies = $companyStmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($companies)) {
        echo "✗ No bus companies found in database. Please run the database schema first.<br>\n";
        exit;
    }
    
    echo "✓ Found " . count($companies) . " companies:<br>\n";
    foreach ($companies as $company) {
        echo "  - {$company['name']} (ID: {$company['id']})<br>\n";
    }
    
    // Insert sample buses using correct schema
    echo "<h3>Setting up Buses...</h3>\n";
    $buses = [
        ['BUS001', 1, '3-4567', 'luxury', 50, '["WiFi","AC","Toilet","Entertainment"]', 'ET-SEL-4567', 'Volvo B9R', 2022, '2024-01-15'],
        ['BUS002', 2, '3-7890', 'standard-ac', 45, '["AC","Comfortable Seats"]', 'ET-ABA-7890', 'Scania Touring', 2021, '2024-01-10'],
        ['BUS003', 3, '3-1234', 'economy', 30, '["Basic Seating"]', 'ET-ETH-1234', 'Mercedes Sprinter', 2023, '2024-01-20'],
        ['BUS004', 4, '3-5678', 'luxury', 55, '["WiFi","AC","Cultural Entertainment"]', 'ET-HAB-5678', 'MAN Lions Coach', 2020, '2023-12-01'],
        ['BUS005', 1, '3-9012', 'luxury', 48, '["WiFi","AC","Meals","Charging Ports"]', 'ET-SEL-9012', 'Higer Luxury', 2022, '2024-01-18'],
        ['BUS006', 2, '3-2468', 'standard-ac', 42, '["AC","Comfortable Seats"]', 'ET-ABA-2468', 'Sky Bus Model', 2021, '2024-01-12'],
        ['BUS007', 3, '3-1357', 'economy', 38, '["Basic Comfort"]', 'ET-ETH-1357', 'Golden Bus Model', 2020, '2024-01-08'],
        ['BUS008', 4, '3-8642', 'luxury', 52, '["WiFi","AC","Entertainment","Meals"]', 'ET-HAB-8642', 'Express Bus Model', 2019, '2023-12-28']
    ];
    
    $stmt = $db->prepare("INSERT INTO buses (bus_id, company_id, bus_number, bus_type, total_seats, amenities, license_plate, model, year_manufactured, last_maintenance_date) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    
    foreach ($buses as $bus) {
        try {
            $stmt->execute($bus);
            echo "✓ Added bus: {$bus[2]} ({$companies[$bus[1]-1]['name']})<br>\n";
        } catch (PDOException $e) {
            if ($e->getCode() == 23000) { // Duplicate entry
                echo "- Bus {$bus[2]} already exists<br>\n";
            } else {
                echo "✗ Error adding bus {$bus[2]}: " . $e->getMessage() . "<br>\n";
            }
        }
    }
    
    // Get city IDs for routes
    echo "<h3>Getting City Information...</h3>\n";
    $cityStmt = $db->prepare("SELECT id, name FROM cities ORDER BY id");
    $cityStmt->execute();
    $cities = $cityStmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($cities)) {
        echo "✗ No cities found in database. Please run the database schema first.<br>\n";
        exit;
    }
    
    echo "✓ Found " . count($cities) . " cities<br>\n";
    
    // Create city name to ID mapping
    $cityMap = [];
    foreach ($cities as $city) {
        $cityMap[strtolower($city['name'])] = $city['id'];
    }
    
    // Insert additional routes (the schema already has basic routes)
    echo "<h3>Setting up Additional Routes...</h3>\n";
    $additionalRoutes = [
        ['RT_EXTRA_001', $cityMap['addis ababa'], $cityMap['kombolcha'], 376, 6.0, 400],
        ['RT_EXTRA_002', $cityMap['kombolcha'], $cityMap['addis ababa'], 376, 6.0, 400],
        ['RT_EXTRA_003', $cityMap['bahirdar'], $cityMap['jimma'], 450, 7.0, 380],
        ['RT_EXTRA_004', $cityMap['jimma'], $cityMap['bahirdar'], 450, 7.0, 380]
    ];
    
    $routeStmt = $db->prepare("INSERT INTO routes (route_id, origin_city_id, destination_city_id, distance_km, estimated_duration_hours, base_price) VALUES (?, ?, ?, ?, ?, ?)");
    
    foreach ($additionalRoutes as $route) {
        try {
            $routeStmt->execute($route);
            $originCity = array_search($route[1], $cityMap);
            $destCity = array_search($route[2], $cityMap);
            echo "✓ Added route: {$originCity} → {$destCity}<br>\n";
        } catch (PDOException $e) {
            if ($e->getCode() == 23000) { // Duplicate entry
                echo "- Route {$route[0]} already exists<br>\n";
            } else {
                echo "✗ Error adding route {$route[0]}: " . $e->getMessage() . "<br>\n";
            }
        }
    }
    
    // Insert sample schedules for the next 30 days
    echo "<h3>Setting up Schedules...</h3>\n";
    
    // Get route and bus IDs
    $routeStmt = $db->prepare("SELECT id FROM routes WHERE is_active = 1 LIMIT 10");
    $routeStmt->execute();
    $routes = $routeStmt->fetchAll(PDO::FETCH_COLUMN);
    
    $busStmt = $db->prepare("SELECT id FROM buses WHERE status = 'active' LIMIT 8");
    $busStmt->execute();
    $buses = $busStmt->fetchAll(PDO::FETCH_COLUMN);
    
    if (count($routes) > 0 && count($buses) > 0) {
        $scheduleStmt = $db->prepare("INSERT INTO schedules (schedule_id, bus_id, route_id, departure_time, arrival_time, days_of_week, price, effective_from) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        
        $scheduleCount = 0;
        for ($day = 0; $day < 30; $day++) {
            $date = date('Y-m-d', strtotime("+$day days"));
            
            // Create 2-3 schedules per day
            for ($schedule = 0; $schedule < rand(2, 3); $schedule++) {
                $routeId = $routes[array_rand($routes)];
                $busId = $buses[array_rand($buses)];
                
                // Random departure times
                $departureHour = rand(6, 20);
                $departureMinute = rand(0, 3) * 15; // 0, 15, 30, 45
                $departureTime = sprintf('%02d:%02d:00', $departureHour, $departureMinute);
                
                // Arrival time (4-12 hours later)
                $travelHours = rand(4, 12);
                $arrivalTime = date('H:i:s', strtotime($departureTime) + ($travelHours * 3600));
                
                $price = rand(150, 500);
                $scheduleId = 'SCH' . date('Ymd') . str_pad($scheduleCount + 1, 4, '0', STR_PAD_LEFT);
                $daysOfWeek = '["monday","tuesday","wednesday","thursday","friday","saturday","sunday"]';
                
                try {
                    $scheduleStmt->execute([$scheduleId, $busId, $routeId, $departureTime, $arrivalTime, $daysOfWeek, $price, $date]);
                    $scheduleCount++;
                } catch (PDOException $e) {
                    // Skip duplicates or conflicts
                }
            }
        }
        echo "✓ Added $scheduleCount schedules<br>\n";
    } else {
        echo "✗ No active routes or buses found for schedules<br>\n";
    }
    
    // Create additional admin user if not exists (using the schema format)
    echo "<h3>Setting up Admin User...</h3>\n";
    
    $adminStmt = $db->prepare("SELECT id FROM users WHERE email = 'admin@swiftbus.com'");
    $adminStmt->execute();
    
    if (!$adminStmt->fetch()) {
        $adminPassword = password_hash('admin123', PASSWORD_DEFAULT);
        $createAdminStmt = $db->prepare("INSERT INTO users (user_id, email, password_hash, first_name, last_name, full_name, phone, role, is_verified, joined_date) VALUES (?, ?, ?, ?, ?, ?, ?, 'admin', 1, ?)");
        
        try {
            $userId = 'U' . date('Ymd') . '0001';
            $createAdminStmt->execute([$userId, 'admin@swiftbus.com', $adminPassword, 'Admin', 'User', 'Admin User', '+251911000000', date('Y-m-d')]);
            echo "✓ Created admin user (email: admin@swiftbus.com, password: admin123)<br>\n";
        } catch (PDOException $e) {
            echo "✗ Error creating admin user: " . $e->getMessage() . "<br>\n";
        }
    } else {
        echo "- Admin user already exists<br>\n";
    }
    
    // Create sample regular users
    echo "<h3>Setting up Sample Users...</h3>\n";
    $users = [
        ['John', 'Doe', 'john@example.com', '+251911111111'],
        ['Jane', 'Smith', 'jane@example.com', '+251922222222'],
        ['Mike', 'Johnson', 'mike@example.com', '+251933333333'],
        ['Sarah', 'Williams', 'sarah@example.com', '+251944444444'],
        ['Robert', 'Brown', 'robert@example.com', '+251955555555']
    ];
    
    $userStmt = $db->prepare("INSERT INTO users (user_id, email, password_hash, first_name, last_name, full_name, phone, role, is_verified, joined_date) VALUES (?, ?, ?, ?, ?, ?, ?, 'user', 1, ?)");
    $defaultPassword = password_hash('password123', PASSWORD_DEFAULT);
    
    $userCount = 2; // Start from 2 since admin is 1
    foreach ($users as $user) {
        try {
            $userId = 'U' . date('Ymd') . str_pad($userCount, 4, '0', STR_PAD_LEFT);
            $fullName = $user[0] . ' ' . $user[1];
            $userStmt->execute([$userId, $user[2], $defaultPassword, $user[0], $user[1], $fullName, $user[3], date('Y-m-d')]);
            echo "✓ Added user: {$fullName} ({$user[2]})<br>\n";
            $userCount++;
        } catch (PDOException $e) {
            if ($e->getCode() == 23000) { // Duplicate entry
                echo "- User {$user[2]} already exists<br>\n";
            } else {
                echo "✗ Error adding user {$user[0]} {$user[1]}: " . $e->getMessage() . "<br>\n";
            }
        }
    }
    
    // Create sample bookings using the complex booking structure
    echo "<h3>Setting up Sample Bookings...</h3>\n";
    
    // Get user IDs (excluding admin)
    $userStmt = $db->prepare("SELECT id, full_name, email, phone FROM users WHERE role = 'user' LIMIT 5");
    $userStmt->execute();
    $userDetails = $userStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get schedule IDs
    $scheduleStmt = $db->prepare("SELECT id FROM schedules LIMIT 10");
    $scheduleStmt->execute();
    $scheduleIds = $scheduleStmt->fetchAll(PDO::FETCH_COLUMN);
    
    if (count($userDetails) > 0 && count($scheduleIds) > 0) {
        $bookingStmt = $db->prepare("INSERT INTO bookings (booking_id, user_id, schedule_id, bus_company, bus_type, from_city, to_city, travel_date, departure_time, passenger_count, selected_seats, passenger_details, total_amount, booking_status, payment_status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        
        $statuses = ['confirmed', 'pending', 'cancelled'];
        $paymentStatuses = ['paid', 'pending', 'failed'];
        $busTypes = ['economy', 'standard', 'standard-ac', 'premium-ac', 'luxury'];
        $companyNames = ['Selam Bus', 'Abay Bus', 'Ethio Bus', 'Habesha Bus'];
        $cityNames = ['Addis Ababa', 'Bahirdar', 'Hawasa', 'Mekele', 'Gonder', 'Jimma', 'Dessie', 'Adama'];
        
        $bookingCount = 0;
        for ($i = 0; $i < 15; $i++) {
            $userDetail = $userDetails[array_rand($userDetails)];
            $scheduleId = $scheduleIds[array_rand($scheduleIds)];
            $bookingId = 'BK' . date('Ymd') . str_pad($i + 1, 4, '0', STR_PAD_LEFT);
            
            $seatNumbers = json_encode([rand(1, 50)]);
            $passengerDetails = json_encode([
                [
                    'name' => $userDetail['full_name'],
                    'age' => rand(18, 65),
                    'gender' => rand(0, 1) ? 'male' : 'female'
                ]
            ]);
            $totalAmount = rand(150, 500);
            $status = $statuses[array_rand($statuses)];
            $paymentStatus = $paymentStatuses[array_rand($paymentStatuses)];
            $busCompany = $companyNames[array_rand($companyNames)];
            $busType = $busTypes[array_rand($busTypes)];
            $fromCity = $cityNames[array_rand($cityNames)];
            $toCity = $cityNames[array_rand($cityNames)];
            $travelDate = date('Y-m-d', strtotime('+' . rand(1, 30) . ' days'));
            $departureTime = sprintf('%02d:%02d', rand(6, 20), rand(0, 3) * 15);
            
            try {
                $bookingStmt->execute([
                    $bookingId, $userDetail['id'], $scheduleId,
                    $busCompany, $busType, $fromCity, $toCity,
                    $travelDate, $departureTime, 1, $seatNumbers,
                    $passengerDetails, $totalAmount, $status, $paymentStatus
                ]);
                $bookingCount++;
            } catch (PDOException $e) {
                // Skip duplicates
                echo "- Skipped booking due to constraint: " . $e->getMessage() . "<br>\n";
            }
        }
        echo "✓ Added $bookingCount sample bookings<br>\n";
    } else {
        echo "✗ No users or schedules found for bookings<br>\n";
    }
    
    echo "<h3>Database Setup Complete!</h3>\n";
    echo "<p><strong>Admin Login:</strong></p>\n";
    echo "<ul>\n";
    echo "<li>Email: admin@swiftbus.com</li>\n";
    echo "<li>Password: admin123</li>\n";
    echo "</ul>\n";
    echo "<p><strong>Existing Admin Logins (from schema):</strong></p>\n";
    echo "<ul>\n";
    echo "<li>ezedinmoh1@gmail.com (password: admin123)</li>\n";
    echo "<li>hanamariamsebsbew1@gmail.com (password: admin123)</li>\n";
    echo "<li>mubarekali974@gmail.com (password: admin123)</li>\n";
    echo "<li>wubetlemma788@gmail.com (password: admin123)</li>\n";
    echo "<li>mahletbelete4@gmail.com (password: admin123)</li>\n";
    echo "</ul>\n";
    echo "<p><strong>Sample User Login:</strong></p>\n";
    echo "<ul>\n";
    echo "<li>Email: john@example.com</li>\n";
    echo "<li>Password: password123</li>\n";
    echo "</ul>\n";
    echo "<p><a href='admin-dashboard.html'>Go to Admin Dashboard</a> | <a href='login.html'>Go to Login</a></p>\n";
    
} catch (Exception $e) {
    echo "<h3>Error:</h3>\n";
    echo "<p style='color: red;'>" . $e->getMessage() . "</p>\n";
    echo "<p>Please check your database configuration and try again.</p>\n";
}
?>