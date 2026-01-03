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

$action = $_GET['action'] ?? $_POST['action'] ?? 'get_routes';

try {
    // Try database first
    $pdo = new PDO("mysql:host=localhost;dbname=swiftbus_db", "root", "");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    switch ($action) {
        case 'get_routes':
            getRoutes($pdo);
            break;
        case 'add_route':
            addRoute($pdo);
            break;
        case 'update_route':
            updateRoute($pdo);
            break;
        case 'delete_route':
            deleteRoute($pdo);
            break;
        case 'get_route_details':
            getRouteDetails($pdo);
            break;
        default:
            throw new Exception('Invalid action');
    }
} catch (Exception $e) {
    // Database failed, use localStorage fallback
    echo json_encode([
        'success' => true,
        'data' => getLocalStorageFallbackRoutes(),
        'source' => 'localStorage',
        'message' => 'Using localStorage fallback data'
    ]);
}

function getRoutes($pdo) {
    try {
        // Get filter parameters
        $search = $_GET['search'] ?? '';
        $status = $_GET['status'] ?? '';
        $limit = max(1, min(100, intval($_GET['limit'] ?? 50)));
        $offset = max(0, intval($_GET['offset'] ?? 0));
        
        // Use main routes table with proper joins
        $sql = "SELECT 
                    r.id,
                    r.route_id,
                    CONCAT(COALESCE(oc.name, 'Unknown'), ' → ', COALESCE(dc.name, 'Unknown')) as route_name,
                    COALESCE(oc.name, 'Unknown') as origin_city,
                    COALESCE(dc.name, 'Unknown') as destination_city,
                    r.distance_km,
                    r.estimated_duration_hours,
                    r.base_price,
                    CASE WHEN r.is_active = 1 THEN 'active' ELSE 'inactive' END as status,
                    r.created_at,
                    r.updated_at,
                    COUNT(DISTINCT s.id) as assigned_schedules,
                    COUNT(DISTINCT s.bus_id) as assigned_buses,
                    COUNT(DISTINCT bk.id) as total_bookings
                FROM routes r
                LEFT JOIN cities oc ON r.origin_city_id = oc.id
                LEFT JOIN cities dc ON r.destination_city_id = dc.id
                LEFT JOIN schedules s ON r.id = s.route_id
                LEFT JOIN bookings bk ON s.id = bk.schedule_id
                WHERE 1=1";
        
        $params = [];
        
        // Add search filter
        if (!empty($search)) {
            $sql .= " AND (oc.name LIKE ? OR dc.name LIKE ? OR r.route_id LIKE ?)";
            $searchParam = "%$search%";
            $params = array_merge($params, [$searchParam, $searchParam, $searchParam]);
        }
        
        // Add status filter
        if (!empty($status)) {
            if ($status === 'active') {
                $sql .= " AND r.is_active = 1";
            } else {
                $sql .= " AND r.is_active = 0";
            }
        }
        
        // Group by and add ordering and pagination
        $sql .= " GROUP BY r.id, r.route_id, oc.name, dc.name, r.distance_km, r.estimated_duration_hours, r.base_price, r.is_active, r.created_at, r.updated_at";
        $sql .= " ORDER BY r.created_at DESC LIMIT $limit OFFSET $offset";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $routes = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Process the data
        foreach ($routes as &$route) {
            // Format duration
            if (isset($route['estimated_duration_hours'])) {
                $hours = floor($route['estimated_duration_hours']);
                $minutes = round(($route['estimated_duration_hours'] - $hours) * 60);
                $duration = $hours > 0 ? "{$hours}h" : "";
                if ($minutes > 0) {
                    $duration .= ($duration ? " " : "") . "{$minutes}m";
                }
                $route['estimated_duration'] = $duration ?: "N/A";
            }
            
            // Ensure all required fields exist
            $route['name'] = $route['route_name'];
            $route['distance'] = (int)($route['distance_km'] ?? 0);
            $route['fare_base'] = (float)($route['base_price'] ?? 0);
            $route['assigned_buses'] = (int)($route['assigned_buses'] ?? 0);
            $route['assigned_schedules'] = (int)($route['assigned_schedules'] ?? 0);
            $route['total_bookings'] = (int)($route['total_bookings'] ?? 0);
            $route['description'] = "Route from {$route['origin_city']} to {$route['destination_city']}";
            
            // Format dates
            $route['created_at'] = date('Y-m-d', strtotime($route['created_at']));
            if ($route['updated_at']) {
                $route['updated_at'] = date('Y-m-d', strtotime($route['updated_at']));
            }
        }
        
        // Get total count for pagination
        $countSql = "SELECT COUNT(*) as total FROM routes r 
                     LEFT JOIN cities oc ON r.origin_city_id = oc.id 
                     LEFT JOIN cities dc ON r.destination_city_id = dc.id 
                     WHERE 1=1";
        $countParams = [];
        
        if (!empty($search)) {
            $countSql .= " AND (oc.name LIKE ? OR dc.name LIKE ? OR r.route_id LIKE ?)";
            $searchParam = "%$search%";
            $countParams = array_merge($countParams, [$searchParam, $searchParam, $searchParam]);
        }
        
        if (!empty($status)) {
            if ($status === 'active') {
                $countSql .= " AND r.is_active = 1";
            } else {
                $countSql .= " AND r.is_active = 0";
            }
        }
        
        $countStmt = $pdo->prepare($countSql);
        $countStmt->execute($countParams);
        $totalCount = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];
        
        echo json_encode([
            'success' => true,
            'data' => $routes,
            'source' => 'database',
            'count' => count($routes),
            'pagination' => [
                'total' => (int)$totalCount,
                'limit' => $limit,
                'offset' => $offset,
                'has_more' => ($offset + $limit) < $totalCount
            ]
        ]);
        
    } catch (Exception $e) {
        throw new Exception('Failed to fetch routes: ' . $e->getMessage());
    }
}

function getLocalStorageFallbackRoutes() {
    return [
        [
            'id' => 1,
            'route_id' => 'RT001',
            'route_name' => 'Addis Ababa → Bahir Dar',
            'name' => 'Addis Ababa → Bahir Dar',
            'origin_city' => 'Addis Ababa',
            'destination_city' => 'Bahir Dar',
            'distance' => 565,
            'estimated_duration' => '6h 30m',
            'fare_base' => 850.00,
            'status' => 'active',
            'assigned_buses' => 3,
            'assigned_schedules' => 8,
            'total_bookings' => 45,
            'description' => 'Route from Addis Ababa to Bahir Dar',
            'created_at' => '2024-01-01',
            'updated_at' => '2024-01-15'
        ],
        [
            'id' => 2,
            'route_id' => 'RT002',
            'route_name' => 'Addis Ababa → Gondar',
            'name' => 'Addis Ababa → Gondar',
            'origin_city' => 'Addis Ababa',
            'destination_city' => 'Gondar',
            'distance' => 748,
            'estimated_duration' => '7h 45m',
            'fare_base' => 950.00,
            'status' => 'active',
            'assigned_buses' => 2,
            'assigned_schedules' => 6,
            'total_bookings' => 32,
            'description' => 'Route from Addis Ababa to Gondar',
            'created_at' => '2024-01-01',
            'updated_at' => '2024-01-10'
        ],
        [
            'id' => 3,
            'route_id' => 'RT003',
            'route_name' => 'Addis Ababa → Hawassa',
            'name' => 'Addis Ababa → Hawassa',
            'origin_city' => 'Addis Ababa',
            'destination_city' => 'Hawassa',
            'distance' => 275,
            'estimated_duration' => '4h 30m',
            'fare_base' => 450.00,
            'status' => 'active',
            'assigned_buses' => 4,
            'assigned_schedules' => 12,
            'total_bookings' => 78,
            'description' => 'Route from Addis Ababa to Hawassa',
            'created_at' => '2024-01-01',
            'updated_at' => '2024-01-20'
        ],
        [
            'id' => 4,
            'route_id' => 'RT004',
            'route_name' => 'Addis Ababa → Dire Dawa',
            'name' => 'Addis Ababa → Dire Dawa',
            'origin_city' => 'Addis Ababa',
            'destination_city' => 'Dire Dawa',
            'distance' => 515,
            'estimated_duration' => '8h 15m',
            'fare_base' => 750.00,
            'status' => 'active',
            'assigned_buses' => 2,
            'assigned_schedules' => 4,
            'total_bookings' => 28,
            'description' => 'Route from Addis Ababa to Dire Dawa',
            'created_at' => '2024-01-01',
            'updated_at' => '2024-01-12'
        ]
    ];
}

// Placeholder functions for other actions
function addRoute($pdo) {
    echo json_encode(['success' => false, 'message' => 'Add route not implemented yet']);
}

function updateRoute($pdo) {
    echo json_encode(['success' => false, 'message' => 'Update route not implemented yet']);
}

function deleteRoute($pdo) {
    echo json_encode(['success' => false, 'message' => 'Delete route not implemented yet']);
}

function getRouteDetails($pdo) {
    echo json_encode(['success' => false, 'message' => 'Get route details not implemented yet']);
}
?>