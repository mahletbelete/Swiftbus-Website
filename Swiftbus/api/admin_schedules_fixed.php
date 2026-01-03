<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// Start session for testing
session_start();
if (!isset($_SESSION['user_id'])) {
    $_SESSION['user_id'] = 'U001';
    $_SESSION['user_role'] = 'admin';
}

$action = $_GET['action'] ?? $_POST['action'] ?? 'get_schedules';

try {
    // Try database first
    $pdo = new PDO("mysql:host=localhost;dbname=swiftbus_db", "root", "");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    switch ($action) {
        case 'get_schedules':
            getSchedules($pdo);
            break;
        case 'get_schedule':
            getSchedule($pdo);
            break;
        case 'create_schedule':
            createSchedule($pdo);
            break;
        case 'update_schedule':
            updateSchedule($pdo);
            break;
        case 'delete_schedule':
            deleteSchedule($pdo);
            break;
        case 'get_stats':
            getScheduleStats($pdo);
            break;
        default:
            throw new Exception('Invalid action');
    }
} catch (Exception $e) {
    // Database failed, use localStorage fallback
    echo json_encode([
        'success' => true,
        'data' => getLocalStorageFallbackSchedules(),
        'source' => 'localStorage',
        'message' => 'Using localStorage fallback data'
    ]);
}

function getSchedules($pdo) {
    try {
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
            'source' => 'database',
            'count' => count($formattedSchedules)
        ]);
        
    } catch (Exception $e) {
        throw new Exception('Failed to fetch schedules: ' . $e->getMessage());
    }
}

function getLocalStorageFallbackSchedules() {
    return [
        [
            'id' => 'SCH001',
            'bus_id' => 'BUS001',
            'bus_name' => 'Selam Express',
            'bus_plate' => 'AA-001-001',
            'route_id' => 'RT001',
            'route_name' => 'Addis Ababa → Bahir Dar',
            'origin_city' => 'Addis Ababa',
            'destination_city' => 'Bahir Dar',
            'schedule_date' => date('Y-m-d'),
            'departure_time' => '08:00:00',
            'arrival_time' => '14:00:00',
            'total_seats' => 45,
            'booked_seats' => 12,
            'available_seats' => 33,
            'status' => 'active',
            'created_at' => date('Y-m-d H:i:s'),
            'notes' => 'Regular service',
            'price' => 850.00,
            'days_of_week' => '["1","2","3","4","5","6","7"]'
        ],
        [
            'id' => 'SCH002',
            'bus_id' => 'BUS002',
            'bus_name' => 'Abay Comfort',
            'bus_plate' => 'AA-002-002',
            'route_id' => 'RT002',
            'route_name' => 'Addis Ababa → Gondar',
            'origin_city' => 'Addis Ababa',
            'destination_city' => 'Gondar',
            'schedule_date' => date('Y-m-d'),
            'departure_time' => '09:00:00',
            'arrival_time' => '16:00:00',
            'total_seats' => 50,
            'booked_seats' => 8,
            'available_seats' => 42,
            'status' => 'active',
            'created_at' => date('Y-m-d H:i:s'),
            'notes' => 'Express service',
            'price' => 950.00,
            'days_of_week' => '["1","3","5"]'
        ],
        [
            'id' => 'SCH003',
            'bus_id' => 'BUS003',
            'bus_name' => 'Ethio Standard',
            'bus_plate' => 'AA-003-003',
            'route_id' => 'RT003',
            'route_name' => 'Addis Ababa → Hawassa',
            'origin_city' => 'Addis Ababa',
            'destination_city' => 'Hawassa',
            'schedule_date' => date('Y-m-d'),
            'departure_time' => '10:00:00',
            'arrival_time' => '14:30:00',
            'total_seats' => 55,
            'booked_seats' => 20,
            'available_seats' => 35,
            'status' => 'active',
            'created_at' => date('Y-m-d H:i:s'),
            'notes' => 'Economy service',
            'price' => 450.00,
            'days_of_week' => '["2","4","6","7"]'
        ]
    ];
}

// Placeholder functions for other actions
function getSchedule($pdo) {
    echo json_encode(['success' => false, 'message' => 'Get schedule not implemented yet']);
}

function createSchedule($pdo) {
    echo json_encode(['success' => false, 'message' => 'Create schedule not implemented yet']);
}

function updateSchedule($pdo) {
    echo json_encode(['success' => false, 'message' => 'Update schedule not implemented yet']);
}

function deleteSchedule($pdo) {
    echo json_encode(['success' => false, 'message' => 'Delete schedule not implemented yet']);
}

function getScheduleStats($pdo) {
    echo json_encode(['success' => false, 'message' => 'Get schedule stats not implemented yet']);
}
?>