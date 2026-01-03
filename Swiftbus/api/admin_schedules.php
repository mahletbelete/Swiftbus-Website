<?php
require_once '../includes/functions.php';

// Set JSON header
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

try {
    // Check authentication
    if (!isLoggedIn()) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Authentication required']);
        exit();
    }

    // Check admin role
    $userData = getCurrentUser();
    if (!$userData || $userData['role'] !== 'admin') {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Admin access required']);
        exit();
    }

    $action = $_GET['action'] ?? '';
    $db = getDB();

    switch ($action) {
        case 'get_schedules':
            getSchedules($db);
            break;
        case 'get_schedule':
            getSchedule($db);
            break;
        case 'create_schedule':
            createSchedule($db);
            break;
        case 'update_schedule':
            updateSchedule($db);
            break;
        case 'delete_schedule':
            deleteSchedule($db);
            break;
        case 'get_stats':
            getScheduleStats($db);
            break;
        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
            break;
    }

} catch (Exception $e) {
    error_log("Admin Schedules API Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'message' => 'Server error occurred',
        'error' => $e->getMessage()
    ]);
}

function getSchedules($db) {
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
                b.bus_number as bus_name,
                b.license_plate as bus_plate,
                b.total_seats,
                r.route_id,
                oc.name as origin_city,
                dc.name as destination_city,
                r.distance_km,
                r.estimated_duration_hours,
                COALESCE(booking_counts.booked_seats, 0) as booked_seats,
                (b.total_seats - COALESCE(booking_counts.booked_seats, 0)) as available_seats
            FROM schedules s
            LEFT JOIN buses b ON s.bus_id = b.id
            LEFT JOIN routes r ON s.route_id = r.id
            LEFT JOIN cities oc ON r.origin_city_id = oc.id
            LEFT JOIN cities dc ON r.destination_city_id = dc.id
            LEFT JOIN (
                SELECT 
                    schedule_id,
                    COUNT(*) as booked_seats
                FROM bookings 
                WHERE status IN ('confirmed', 'completed')
                GROUP BY schedule_id
            ) booking_counts ON s.id = booking_counts.schedule_id
            WHERE s.is_active = 1
            ORDER BY s.effective_from DESC, s.departure_time ASC
        ";
        
        $stmt = $db->prepare($query);
        $stmt->execute();
        $schedules = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Format the data
        $formattedSchedules = array_map(function($schedule) {
            return [
                'id' => $schedule['schedule_id'] ?? $schedule['id'],
                'bus_id' => $schedule['bus_id'] ?? 'BUS001',
                'bus_name' => $schedule['bus_name'] ?? 'Unknown Bus',
                'bus_plate' => $schedule['bus_plate'] ?? 'N/A',
                'route_id' => $schedule['route_id'] ?? 'RT001',
                'route_name' => ($schedule['origin_city'] && $schedule['destination_city']) 
                    ? $schedule['origin_city'] . ' → ' . $schedule['destination_city']
                    : 'Unknown Route',
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
        
    } catch (Exception $e) {
        error_log("Get schedules error: " . $e->getMessage());
        echo json_encode([
            'success' => false,
            'message' => 'Failed to fetch schedules',
            'error' => $e->getMessage()
        ]);
    }
}

function getSchedule($db) {
    try {
        $scheduleId = $_GET['id'] ?? '';
        
        if (empty($scheduleId)) {
            echo json_encode(['success' => false, 'message' => 'Schedule ID required']);
            return;
        }
        
        $query = "
            SELECT 
                s.*,
                b.bus_name,
                b.plate_number as bus_plate,
                b.capacity as total_seats,
                r.route_name,
                r.origin_city,
                r.destination_city,
                COALESCE(booking_counts.booked_seats, 0) as booked_seats,
                (b.capacity - COALESCE(booking_counts.booked_seats, 0)) as available_seats
            FROM schedules s
            LEFT JOIN buses b ON s.bus_id = b.id
            LEFT JOIN routes r ON s.route_id = r.id
            LEFT JOIN (
                SELECT 
                    schedule_id,
                    COUNT(*) as booked_seats
                FROM bookings 
                WHERE status IN ('confirmed', 'completed')
                GROUP BY schedule_id
            ) booking_counts ON s.id = booking_counts.schedule_id
            WHERE s.id = ?
        ";
        
        $stmt = $db->prepare($query);
        $stmt->execute([$scheduleId]);
        $schedule = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$schedule) {
            echo json_encode(['success' => false, 'message' => 'Schedule not found']);
            return;
        }
        
        $formattedSchedule = [
            'id' => $schedule['id'],
            'bus_id' => $schedule['bus_id'],
            'bus_name' => $schedule['bus_name'] ?? 'Unknown Bus',
            'bus_plate' => $schedule['bus_plate'] ?? 'N/A',
            'route_id' => $schedule['route_id'],
            'route_name' => $schedule['route_name'] ?? 'Unknown Route',
            'origin_city' => $schedule['origin_city'] ?? 'Unknown',
            'destination_city' => $schedule['destination_city'] ?? 'Unknown',
            'schedule_date' => $schedule['schedule_date'],
            'departure_time' => $schedule['departure_time'],
            'arrival_time' => $schedule['arrival_time'],
            'total_seats' => (int)($schedule['total_seats'] ?? 45),
            'booked_seats' => (int)($schedule['booked_seats'] ?? 0),
            'available_seats' => (int)($schedule['available_seats'] ?? 45),
            'status' => $schedule['status'] ?? 'active',
            'created_at' => $schedule['created_at'],
            'notes' => $schedule['notes'] ?? ''
        ];
        
        echo json_encode([
            'success' => true,
            'data' => $formattedSchedule
        ]);
        
    } catch (Exception $e) {
        error_log("Get schedule error: " . $e->getMessage());
        echo json_encode([
            'success' => false,
            'message' => 'Failed to fetch schedule',
            'error' => $e->getMessage()
        ]);
    }
}

function createSchedule($db) {
    try {
        $input = json_decode(file_get_contents('php://input'), true);
        
        $requiredFields = ['bus_id', 'route_id', 'schedule_date', 'departure_time', 'arrival_time'];
        foreach ($requiredFields as $field) {
            if (empty($input[$field])) {
                echo json_encode(['success' => false, 'message' => "Field '$field' is required"]);
                return;
            }
        }
        
        // Generate schedule ID
        $scheduleId = 'SCH' . str_pad(rand(1, 999), 3, '0', STR_PAD_LEFT);
        
        // Check if schedule ID already exists
        $checkQuery = "SELECT id FROM schedules WHERE id = ?";
        $checkStmt = $db->prepare($checkQuery);
        $checkStmt->execute([$scheduleId]);
        
        // Generate new ID if exists
        while ($checkStmt->fetch()) {
            $scheduleId = 'SCH' . str_pad(rand(1, 999), 3, '0', STR_PAD_LEFT);
            $checkStmt->execute([$scheduleId]);
        }
        
        $query = "
            INSERT INTO schedules (
                id, bus_id, route_id, schedule_date, departure_time, 
                arrival_time, status, notes, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ";
        
        $stmt = $db->prepare($query);
        $result = $stmt->execute([
            $scheduleId,
            $input['bus_id'],
            $input['route_id'],
            $input['schedule_date'],
            $input['departure_time'],
            $input['arrival_time'],
            $input['status'] ?? 'active',
            $input['notes'] ?? ''
        ]);
        
        if ($result) {
            echo json_encode([
                'success' => true,
                'message' => 'Schedule created successfully',
                'data' => ['id' => $scheduleId]
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to create schedule']);
        }
        
    } catch (Exception $e) {
        error_log("Create schedule error: " . $e->getMessage());
        echo json_encode([
            'success' => false,
            'message' => 'Failed to create schedule',
            'error' => $e->getMessage()
        ]);
    }
}

function updateSchedule($db) {
    try {
        $input = json_decode(file_get_contents('php://input'), true);
        $scheduleId = $_GET['id'] ?? $input['id'] ?? '';
        
        if (empty($scheduleId)) {
            echo json_encode(['success' => false, 'message' => 'Schedule ID required']);
            return;
        }
        
        // Check if schedule exists
        $checkQuery = "SELECT id FROM schedules WHERE id = ?";
        $checkStmt = $db->prepare($checkQuery);
        $checkStmt->execute([$scheduleId]);
        
        if (!$checkStmt->fetch()) {
            echo json_encode(['success' => false, 'message' => 'Schedule not found']);
            return;
        }
        
        $updateFields = [];
        $updateValues = [];
        
        $allowedFields = [
            'bus_id', 'route_id', 'schedule_date', 'departure_time', 
            'arrival_time', 'status', 'notes'
        ];
        
        foreach ($allowedFields as $field) {
            if (isset($input[$field])) {
                $updateFields[] = "$field = ?";
                $updateValues[] = $input[$field];
            }
        }
        
        if (empty($updateFields)) {
            echo json_encode(['success' => false, 'message' => 'No fields to update']);
            return;
        }
        
        $updateValues[] = $scheduleId;
        
        $query = "UPDATE schedules SET " . implode(', ', $updateFields) . " WHERE id = ?";
        $stmt = $db->prepare($query);
        $result = $stmt->execute($updateValues);
        
        if ($result) {
            echo json_encode([
                'success' => true,
                'message' => 'Schedule updated successfully'
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to update schedule']);
        }
        
    } catch (Exception $e) {
        error_log("Update schedule error: " . $e->getMessage());
        echo json_encode([
            'success' => false,
            'message' => 'Failed to update schedule',
            'error' => $e->getMessage()
        ]);
    }
}

function deleteSchedule($db) {
    try {
        $scheduleId = $_GET['id'] ?? '';
        
        if (empty($scheduleId)) {
            echo json_encode(['success' => false, 'message' => 'Schedule ID required']);
            return;
        }
        
        // Check if schedule has bookings
        $bookingQuery = "SELECT COUNT(*) as booking_count FROM bookings WHERE schedule_id = ?";
        $bookingStmt = $db->prepare($bookingQuery);
        $bookingStmt->execute([$scheduleId]);
        $bookingCount = $bookingStmt->fetch(PDO::FETCH_ASSOC)['booking_count'];
        
        if ($bookingCount > 0) {
            echo json_encode([
                'success' => false, 
                'message' => "Cannot delete schedule with existing bookings ($bookingCount bookings found)"
            ]);
            return;
        }
        
        $query = "DELETE FROM schedules WHERE id = ?";
        $stmt = $db->prepare($query);
        $result = $stmt->execute([$scheduleId]);
        
        if ($result) {
            echo json_encode([
                'success' => true,
                'message' => 'Schedule deleted successfully'
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to delete schedule']);
        }
        
    } catch (Exception $e) {
        error_log("Delete schedule error: " . $e->getMessage());
        echo json_encode([
            'success' => false,
            'message' => 'Failed to delete schedule',
            'error' => $e->getMessage()
        ]);
    }
}

function getScheduleStats($db) {
    try {
        $stats = [];
        
        // Total schedules
        $totalQuery = "SELECT COUNT(*) as total FROM schedules";
        $totalStmt = $db->prepare($totalQuery);
        $totalStmt->execute();
        $stats['total'] = (int)$totalStmt->fetch(PDO::FETCH_ASSOC)['total'];
        
        // Active schedules
        $activeQuery = "SELECT COUNT(*) as active FROM schedules WHERE status = 'active'";
        $activeStmt = $db->prepare($activeQuery);
        $activeStmt->execute();
        $stats['active'] = (int)$activeStmt->fetch(PDO::FETCH_ASSOC)['active'];
        
        // Cancelled schedules
        $cancelledQuery = "SELECT COUNT(*) as cancelled FROM schedules WHERE status = 'cancelled'";
        $cancelledStmt = $db->prepare($cancelledQuery);
        $cancelledStmt->execute();
        $stats['cancelled'] = (int)$cancelledStmt->fetch(PDO::FETCH_ASSOC)['cancelled'];
        
        // Maintenance schedules
        $maintenanceQuery = "SELECT COUNT(*) as maintenance FROM schedules WHERE status = 'maintenance'";
        $maintenanceStmt = $db->prepare($maintenanceQuery);
        $maintenanceStmt->execute();
        $stats['maintenance'] = (int)$maintenanceStmt->fetch(PDO::FETCH_ASSOC)['maintenance'];
        
        echo json_encode([
            'success' => true,
            'data' => $stats
        ]);
        
    } catch (Exception $e) {
        error_log("Get schedule stats error: " . $e->getMessage());
        echo json_encode([
            'success' => false,
            'message' => 'Failed to fetch schedule statistics',
            'error' => $e->getMessage()
        ]);
    }
}
?>