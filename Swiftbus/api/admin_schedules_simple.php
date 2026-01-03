<?php
// Simple admin schedules API for testing
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Start session
session_start();

// Set admin session for testing if not set
if (!isset($_SESSION['user_id'])) {
    $_SESSION['user_id'] = 'U001';
    $_SESSION['user_role'] = 'admin';
    $_SESSION['user_email'] = 'admin@swiftbus.et';
}

try {
    // Database connection
    $pdo = new PDO("mysql:host=localhost;dbname=swiftbus_db", "root", "");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    $action = $_GET['action'] ?? 'get_schedules';
    
    if ($action === 'get_schedules') {
        // Simple query to get schedules
        $query = "
            SELECT 
                s.id,
                s.schedule_id,
                s.departure_time,
                s.arrival_time,
                s.price,
                s.is_active,
                s.effective_from,
                s.effective_until,
                s.days_of_week,
                s.created_at,
                b.bus_id,
                b.bus_number,
                b.total_seats,
                r.route_id,
                'Unknown Route' as route_name,
                'Unknown' as origin_city,
                'Unknown' as destination_city,
                0 as booked_seats,
                b.total_seats as available_seats
            FROM schedules s
            LEFT JOIN buses b ON s.bus_id = b.id
            LEFT JOIN routes r ON s.route_id = r.id
            WHERE s.is_active = 1
            ORDER BY s.created_at DESC
            LIMIT 50
        ";
        
        $stmt = $pdo->prepare($query);
        $stmt->execute();
        $schedules = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Format the data
        $formattedSchedules = array_map(function($schedule) {
            return [
                'id' => $schedule['schedule_id'] ?? $schedule['id'],
                'bus_id' => $schedule['bus_id'] ?? 'BUS001',
                'bus_name' => $schedule['bus_number'] ?? 'Unknown Bus',
                'bus_plate' => 'N/A',
                'route_id' => $schedule['route_id'] ?? 'RT001',
                'route_name' => $schedule['route_name'] ?? 'Unknown Route',
                'origin_city' => $schedule['origin_city'] ?? 'Unknown',
                'destination_city' => $schedule['destination_city'] ?? 'Unknown',
                'schedule_date' => $schedule['effective_from'] ?? date('Y-m-d'),
                'departure_time' => $schedule['departure_time'] ?? '08:00:00',
                'arrival_time' => $schedule['arrival_time'] ?? '16:00:00',
                'total_seats' => (int)($schedule['total_seats'] ?? 45),
                'booked_seats' => (int)($schedule['booked_seats'] ?? 0),
                'available_seats' => (int)($schedule['available_seats'] ?? 45),
                'status' => $schedule['is_active'] ? 'active' : 'inactive',
                'created_at' => $schedule['created_at'],
                'notes' => '',
                'price' => $schedule['price'] ?? 0,
                'days_of_week' => $schedule['days_of_week'] ?? '[]'
            ];
        }, $schedules);
        
        echo json_encode([
            'success' => true,
            'data' => $formattedSchedules,
            'count' => count($formattedSchedules)
        ]);
        
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Action not supported in simple API'
        ]);
    }
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage(),
        'error' => $e->getMessage()
    ]);
}
?>