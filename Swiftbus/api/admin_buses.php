<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

require_once '../includes/config.php';
require_once '../includes/functions.php';

// Check if user is authenticated and is admin
if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => 'Authentication required'
    ]);
    exit;
}

$user = getCurrentUser();
if (!$user || $user['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'message' => 'Admin access required'
    ]);
    exit;
}

$action = $_GET['action'] ?? $_POST['action'] ?? 'get_buses';

try {
    switch ($action) {
        case 'get_buses':
            getBuses();
            break;
        case 'add_bus':
            addBus();
            break;
        case 'update_bus':
            updateBus();
            break;
        case 'delete_bus':
            deleteBus();
            break;
        case 'get_bus_details':
            getBusDetails();
            break;
        default:
            throw new Exception('Invalid action');
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

function getBuses() {
    try {
        $pdo = getDB();
        
        // Get filter parameters
        $search = $_GET['search'] ?? '';
        $status = $_GET['status'] ?? '';
        $bus_type = $_GET['bus_type'] ?? '';
        $limit = (int)($_GET['limit'] ?? 50);
        $offset = (int)($_GET['offset'] ?? 0);
        
        // Try admin_buses table first
        $sql = "SELECT 
                    id,
                    bus_number,
                    bus_name,
                    bus_type,
                    total_seats,
                    status,
                    company_name,
                    license_plate,
                    driver_name,
                    driver_phone,
                    last_maintenance,
                    next_maintenance,
                    amenities,
                    created_at,
                    updated_at
                FROM admin_buses
                WHERE 1=1";
        
        $params = [];
        
        // Add search filter
        if (!empty($search)) {
            $sql .= " AND (bus_number LIKE ? OR bus_name LIKE ? OR license_plate LIKE ? OR driver_name LIKE ?)";
            $searchParam = "%$search%";
            $params = array_merge($params, [$searchParam, $searchParam, $searchParam, $searchParam]);
        }
        
        // Add status filter
        if (!empty($status)) {
            $sql .= " AND status = ?";
            $params[] = $status;
        }
        
        // Add bus type filter
        if (!empty($bus_type)) {
            $sql .= " AND bus_type = ?";
            $params[] = $bus_type;
        }
        
        // Add ordering and pagination
        $sql .= " ORDER BY created_at DESC LIMIT ? OFFSET ?";
        $params[] = $limit;
        $params[] = $offset;
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $buses = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // If no buses in admin_buses, try main buses table
        if (empty($buses)) {
            $sql = "SELECT 
                        b.id,
                        b.bus_id as bus_number,
                        CONCAT(c.name, ' - ', b.bus_type) as bus_name,
                        b.bus_type,
                        b.total_seats,
                        b.status,
                        c.name as company_name,
                        b.license_plate,
                        'N/A' as driver_name,
                        'N/A' as driver_phone,
                        b.last_maintenance_date as last_maintenance,
                        b.next_maintenance_date as next_maintenance,
                        b.amenities,
                        b.created_at,
                        b.updated_at
                    FROM buses b
                    LEFT JOIN bus_companies c ON b.company_id = c.id
                    WHERE 1=1";
            
            $params = [];
            
            if (!empty($search)) {
                $sql .= " AND (b.bus_id LIKE ? OR b.license_plate LIKE ? OR c.name LIKE ?)";
                $searchParam = "%$search%";
                $params = array_merge($params, [$searchParam, $searchParam, $searchParam]);
            }
            
            if (!empty($status)) {
                $sql .= " AND b.status = ?";
                $params[] = $status;
            }
            
            if (!empty($bus_type)) {
                $sql .= " AND b.bus_type = ?";
                $params[] = $bus_type;
            }
            
            $sql .= " ORDER BY b.created_at DESC LIMIT ? OFFSET ?";
            $params[] = $limit;
            $params[] = $offset;
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $buses = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
        
        // Process the data
        foreach ($buses as &$bus) {
            // Parse amenities if it's JSON
            if (!empty($bus['amenities'])) {
                $amenities = json_decode($bus['amenities'], true);
                if (is_array($amenities)) {
                    $bus['amenities_list'] = $amenities;
                    $bus['amenities_display'] = implode(', ', $amenities);
                } else {
                    $bus['amenities_list'] = [];
                    $bus['amenities_display'] = $bus['amenities'];
                }
            } else {
                $bus['amenities_list'] = [];
                $bus['amenities_display'] = 'None';
            }
            
            // Format dates
            if ($bus['last_maintenance']) {
                $bus['last_maintenance'] = date('Y-m-d', strtotime($bus['last_maintenance']));
            }
            if ($bus['next_maintenance']) {
                $bus['next_maintenance'] = date('Y-m-d', strtotime($bus['next_maintenance']));
            }
            
            // Ensure all required fields exist
            $bus['bus_number'] = $bus['bus_number'] ?? 'N/A';
            $bus['bus_name'] = $bus['bus_name'] ?? 'Unknown Bus';
            $bus['company_name'] = $bus['company_name'] ?? 'Unknown Company';
            $bus['driver_name'] = $bus['driver_name'] ?? 'N/A';
            $bus['driver_phone'] = $bus['driver_phone'] ?? 'N/A';
            $bus['license_plate'] = $bus['license_plate'] ?? 'N/A';
        }
        
        // Get total count for pagination
        $countSql = "SELECT COUNT(*) as total FROM admin_buses WHERE 1=1";
        $countParams = [];
        
        if (!empty($search)) {
            $countSql .= " AND (bus_number LIKE ? OR bus_name LIKE ? OR license_plate LIKE ? OR driver_name LIKE ?)";
            $searchParam = "%$search%";
            $countParams = array_merge($countParams, [$searchParam, $searchParam, $searchParam, $searchParam]);
        }
        
        if (!empty($status)) {
            $countSql .= " AND status = ?";
            $countParams[] = $status;
        }
        
        if (!empty($bus_type)) {
            $countSql .= " AND bus_type = ?";
            $countParams[] = $bus_type;
        }
        
        $countStmt = $pdo->prepare($countSql);
        $countStmt->execute($countParams);
        $totalCount = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];
        
        // If no count from admin_buses, try main buses table
        if ($totalCount == 0) {
            $countSql = "SELECT COUNT(*) as total FROM buses b LEFT JOIN bus_companies c ON b.company_id = c.id WHERE 1=1";
            $countParams = [];
            
            if (!empty($search)) {
                $countSql .= " AND (b.bus_id LIKE ? OR b.license_plate LIKE ? OR c.name LIKE ?)";
                $searchParam = "%$search%";
                $countParams = array_merge($countParams, [$searchParam, $searchParam, $searchParam]);
            }
            
            if (!empty($status)) {
                $countSql .= " AND b.status = ?";
                $countParams[] = $status;
            }
            
            if (!empty($bus_type)) {
                $countSql .= " AND b.bus_type = ?";
                $countParams[] = $bus_type;
            }
            
            $countStmt = $pdo->prepare($countSql);
            $countStmt->execute($countParams);
            $totalCount = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];
        }
        
        echo json_encode([
            'success' => true,
            'data' => $buses,
            'pagination' => [
                'total' => (int)$totalCount,
                'limit' => $limit,
                'offset' => $offset,
                'has_more' => ($offset + $limit) < $totalCount
            ]
        ]);
        
    } catch (Exception $e) {
        throw new Exception('Failed to fetch buses: ' . $e->getMessage());
    }
}

function addBus() {
    try {
        $pdo = getDB();
        
        $input = json_decode(file_get_contents('php://input'), true);
        
        // Validate required fields
        $required = ['bus_number', 'bus_name', 'bus_type', 'total_seats'];
        foreach ($required as $field) {
            if (empty($input[$field])) {
                throw new Exception("Field '$field' is required");
            }
        }
        
        // Prepare data
        $busNumber = $input['bus_number'];
        $busName = $input['bus_name'];
        $busType = $input['bus_type'];
        $totalSeats = (int)$input['total_seats'];
        $status = $input['status'] ?? 'Active';
        $companyName = $input['company_name'] ?? 'SwiftBus';
        $licensePlate = $input['license_plate'] ?? '';
        $driverName = $input['driver_name'] ?? '';
        $driverPhone = $input['driver_phone'] ?? '';
        $amenities = isset($input['amenities']) ? json_encode($input['amenities']) : null;
        
        // Check if bus number already exists
        $stmt = $pdo->prepare("SELECT id FROM admin_buses WHERE bus_number = ?");
        $stmt->execute([$busNumber]);
        if ($stmt->fetch()) {
            throw new Exception('Bus number already exists');
        }
        
        // Insert new bus
        $stmt = $pdo->prepare("
            INSERT INTO admin_buses (
                bus_number, bus_name, bus_type, total_seats, status, 
                company_name, license_plate, driver_name, driver_phone, amenities
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $result = $stmt->execute([
            $busNumber, $busName, $busType, $totalSeats, $status,
            $companyName, $licensePlate, $driverName, $driverPhone, $amenities
        ]);
        
        if (!$result) {
            throw new Exception('Failed to add bus');
        }
        
        echo json_encode([
            'success' => true,
            'message' => 'Bus added successfully',
            'bus_id' => $pdo->lastInsertId()
        ]);
        
    } catch (Exception $e) {
        throw new Exception('Failed to add bus: ' . $e->getMessage());
    }
}

function updateBus() {
    try {
        $pdo = getDB();
        
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!isset($input['id'])) {
            throw new Exception('Bus ID is required');
        }
        
        $busId = $input['id'];
        
        // Build update query
        $updateFields = [];
        $params = [];
        
        $allowedFields = ['bus_name', 'bus_type', 'total_seats', 'status', 'company_name', 
                         'license_plate', 'driver_name', 'driver_phone', 'last_maintenance', 'next_maintenance'];
        
        foreach ($allowedFields as $field) {
            if (isset($input[$field])) {
                $updateFields[] = "$field = ?";
                $params[] = $input[$field];
            }
        }
        
        if (isset($input['amenities'])) {
            $updateFields[] = "amenities = ?";
            $params[] = json_encode($input['amenities']);
        }
        
        $updateFields[] = "updated_at = NOW()";
        
        if (empty($updateFields)) {
            throw new Exception('No fields to update');
        }
        
        $sql = "UPDATE admin_buses SET " . implode(', ', $updateFields) . " WHERE id = ?";
        $params[] = $busId;
        
        $stmt = $pdo->prepare($sql);
        $result = $stmt->execute($params);
        
        if (!$result) {
            throw new Exception('Failed to update bus');
        }
        
        if ($stmt->rowCount() === 0) {
            throw new Exception('Bus not found');
        }
        
        echo json_encode([
            'success' => true,
            'message' => 'Bus updated successfully'
        ]);
        
    } catch (Exception $e) {
        throw new Exception('Failed to update bus: ' . $e->getMessage());
    }
}

function deleteBus() {
    try {
        $pdo = getDB();
        
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!isset($input['id'])) {
            throw new Exception('Bus ID is required');
        }
        
        $busId = $input['id'];
        
        // Check if bus has active schedules
        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM admin_schedules WHERE bus_id = ? AND status = 'Active'");
        $stmt->execute([$busId]);
        $scheduleCount = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
        
        if ($scheduleCount > 0) {
            // Don't delete, just deactivate
            $stmt = $pdo->prepare("UPDATE admin_buses SET status = 'Inactive', updated_at = NOW() WHERE id = ?");
            $result = $stmt->execute([$busId]);
            $message = 'Bus deactivated (has active schedules)';
        } else {
            // Safe to delete
            $stmt = $pdo->prepare("DELETE FROM admin_buses WHERE id = ?");
            $result = $stmt->execute([$busId]);
            $message = 'Bus deleted successfully';
        }
        
        if (!$result) {
            throw new Exception('Failed to delete bus');
        }
        
        if ($stmt->rowCount() === 0) {
            throw new Exception('Bus not found');
        }
        
        echo json_encode([
            'success' => true,
            'message' => $message
        ]);
        
    } catch (Exception $e) {
        throw new Exception('Failed to delete bus: ' . $e->getMessage());
    }
}

function getBusDetails() {
    try {
        $pdo = getDB();
        
        $busId = $_GET['bus_id'] ?? '';
        
        if (empty($busId)) {
            throw new Exception('Bus ID is required');
        }
        
        $sql = "SELECT * FROM admin_buses WHERE id = ?";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$busId]);
        $bus = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$bus) {
            throw new Exception('Bus not found');
        }
        
        // Parse amenities if it's JSON
        if (!empty($bus['amenities'])) {
            $bus['amenities'] = json_decode($bus['amenities'], true);
        }
        
        echo json_encode([
            'success' => true,
            'data' => $bus
        ]);
        
    } catch (Exception $e) {
        throw new Exception('Failed to get bus details: ' . $e->getMessage());
    }
}
?>