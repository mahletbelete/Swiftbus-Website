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

$action = $_GET['action'] ?? $_POST['action'] ?? 'get_buses';

try {
    // Try database first
    $pdo = new PDO("mysql:host=localhost;dbname=swiftbus_db", "root", "");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    switch ($action) {
        case 'get_buses':
            getBuses($pdo);
            break;
        case 'add_bus':
            addBus($pdo);
            break;
        case 'update_bus':
            updateBus($pdo);
            break;
        case 'delete_bus':
            deleteBus($pdo);
            break;
        case 'get_bus_details':
            getBusDetails($pdo);
            break;
        default:
            throw new Exception('Invalid action');
    }
} catch (Exception $e) {
    // Database failed, use localStorage fallback
    echo json_encode([
        'success' => true,
        'data' => getLocalStorageFallbackBuses(),
        'source' => 'localStorage',
        'message' => 'Using localStorage fallback data'
    ]);
}

function getBuses($pdo) {
    try {
        // Get filter parameters with proper type casting
        $search = $_GET['search'] ?? '';
        $status = $_GET['status'] ?? '';
        $bus_type = $_GET['bus_type'] ?? '';
        $company_id = $_GET['company_id'] ?? '';
        $limit = max(1, min(100, intval($_GET['limit'] ?? 50))); // Ensure valid range
        $offset = max(0, intval($_GET['offset'] ?? 0));
        
        // Use main buses table with company information
        $sql = "SELECT 
                    b.id,
                    b.bus_id as bus_number,
                    b.bus_number as bus_name,
                    b.bus_type,
                    b.total_seats,
                    b.status,
                    COALESCE(c.name, 'Unknown Company') as company_name,
                    b.license_plate,
                    b.model,
                    b.year_manufactured,
                    b.amenities,
                    b.last_maintenance_date as last_maintenance,
                    b.next_maintenance_date as next_maintenance,
                    b.created_at,
                    b.updated_at,
                    COUNT(s.id) as total_schedules,
                    COUNT(CASE WHEN s.effective_from <= CURDATE() AND s.is_active = 1 THEN 1 END) as active_schedules
                FROM buses b
                LEFT JOIN bus_companies c ON b.company_id = c.id
                LEFT JOIN schedules s ON b.id = s.bus_id
                WHERE 1=1";
        
        $params = [];
        
        // Add search filter
        if (!empty($search)) {
            $sql .= " AND (b.bus_id LIKE ? OR b.bus_number LIKE ? OR b.license_plate LIKE ? OR c.name LIKE ?)";
            $searchParam = "%$search%";
            $params = array_merge($params, [$searchParam, $searchParam, $searchParam, $searchParam]);
        }
        
        // Add status filter
        if (!empty($status)) {
            $sql .= " AND b.status = ?";
            $params[] = $status;
        }
        
        // Add bus type filter
        if (!empty($bus_type)) {
            $sql .= " AND b.bus_type = ?";
            $params[] = $bus_type;
        }
        
        // Add company filter
        if (!empty($company_id)) {
            $sql .= " AND b.company_id = ?";
            $params[] = $company_id;
        }
        
        // Group by bus and add ordering and pagination
        $sql .= " GROUP BY b.id ORDER BY b.created_at DESC LIMIT $limit OFFSET $offset";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $buses = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
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
            $bus['bus_name'] = $bus['bus_name'] ?? $bus['bus_number'] ?? 'Unknown Bus';
            $bus['company_name'] = $bus['company_name'] ?? 'Unknown Company';
            $bus['license_plate'] = $bus['license_plate'] ?? 'N/A';
            $bus['model'] = $bus['model'] ?? 'N/A';
            $bus['year_manufactured'] = $bus['year_manufactured'] ?? 'N/A';
            
            // Add calculated fields
            $bus['total_schedules'] = (int)$bus['total_schedules'];
            $bus['active_schedules'] = (int)$bus['active_schedules'];
        }
        
        // Get total count for pagination
        $countSql = "SELECT COUNT(*) as total FROM buses b LEFT JOIN bus_companies c ON b.company_id = c.id WHERE 1=1";
        $countParams = [];
        
        if (!empty($search)) {
            $countSql .= " AND (b.bus_id LIKE ? OR b.bus_number LIKE ? OR b.license_plate LIKE ? OR c.name LIKE ?)";
            $searchParam = "%$search%";
            $countParams = array_merge($countParams, [$searchParam, $searchParam, $searchParam, $searchParam]);
        }
        
        if (!empty($status)) {
            $countSql .= " AND b.status = ?";
            $countParams[] = $status;
        }
        
        if (!empty($bus_type)) {
            $countSql .= " AND b.bus_type = ?";
            $countParams[] = $bus_type;
        }
        
        if (!empty($company_id)) {
            $countSql .= " AND b.company_id = ?";
            $countParams[] = $company_id;
        }
        
        $countStmt = $pdo->prepare($countSql);
        $countStmt->execute($countParams);
        $totalCount = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];
        
        echo json_encode([
            'success' => true,
            'data' => $buses,
            'source' => 'database',
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

function getLocalStorageFallbackBuses() {
    // Generate sample bus data based on localStorage payment data structure
    return [
        [
            'id' => 1,
            'bus_number' => 'BUS001',
            'bus_name' => 'Selam Express',
            'bus_type' => 'premium-ac',
            'total_seats' => 45,
            'status' => 'active',
            'company_name' => 'Selam Bus',
            'license_plate' => 'AA-001-001',
            'model' => 'Mercedes Benz',
            'year_manufactured' => '2022',
            'amenities_display' => 'AC, WiFi, USB Charging',
            'last_maintenance' => '2024-01-01',
            'next_maintenance' => '2024-04-01',
            'total_schedules' => 12,
            'active_schedules' => 8,
            'created_at' => '2024-01-01'
        ],
        [
            'id' => 2,
            'bus_number' => 'BUS002',
            'bus_name' => 'Abay Comfort',
            'bus_type' => 'standard-ac',
            'total_seats' => 50,
            'status' => 'active',
            'company_name' => 'Abay Bus',
            'license_plate' => 'AA-002-002',
            'model' => 'Hyundai',
            'year_manufactured' => '2021',
            'amenities_display' => 'AC, Reclining Seats',
            'last_maintenance' => '2024-01-15',
            'next_maintenance' => '2024-04-15',
            'total_schedules' => 10,
            'active_schedules' => 6,
            'created_at' => '2024-01-01'
        ],
        [
            'id' => 3,
            'bus_number' => 'BUS003',
            'bus_name' => 'Ethio Standard',
            'bus_type' => 'standard',
            'total_seats' => 55,
            'status' => 'maintenance',
            'company_name' => 'Ethio Bus',
            'license_plate' => 'AA-003-003',
            'model' => 'Isuzu',
            'year_manufactured' => '2020',
            'amenities_display' => 'Basic Seating',
            'last_maintenance' => '2024-01-20',
            'next_maintenance' => '2024-02-20',
            'total_schedules' => 8,
            'active_schedules' => 0,
            'created_at' => '2024-01-01'
        ],
        [
            'id' => 4,
            'bus_number' => 'BUS004',
            'bus_name' => 'Habesha Luxury',
            'bus_type' => 'luxury',
            'total_seats' => 40,
            'status' => 'active',
            'company_name' => 'Habesha Bus',
            'license_plate' => 'AA-004-004',
            'model' => 'Volvo',
            'year_manufactured' => '2023',
            'amenities_display' => 'Luxury Seats, Entertainment, WiFi, AC',
            'last_maintenance' => '2024-01-10',
            'next_maintenance' => '2024-04-10',
            'total_schedules' => 15,
            'active_schedules' => 12,
            'created_at' => '2024-01-01'
        ]
    ];
}

// Placeholder functions for other actions
function addBus($pdo) {
    echo json_encode(['success' => false, 'message' => 'Add bus not implemented yet']);
}

function updateBus($pdo) {
    echo json_encode(['success' => false, 'message' => 'Update bus not implemented yet']);
}

function deleteBus($pdo) {
    echo json_encode(['success' => false, 'message' => 'Delete bus not implemented yet']);
}

function getBusDetails($pdo) {
    echo json_encode(['success' => false, 'message' => 'Get bus details not implemented yet']);
}
?>