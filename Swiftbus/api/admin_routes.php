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

$action = $_GET['action'] ?? $_POST['action'] ?? 'get_routes';

try {
    switch ($action) {
        case 'get_routes':
            getRoutes();
            break;
        case 'add_route':
            addRoute();
            break;
        case 'update_route':
            updateRoute();
            break;
        case 'delete_route':
            deleteRoute();
            break;
        case 'get_route_details':
            getRouteDetails();
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

function getRoutes() {
    try {
        $pdo = getDB();
        
        // Get filter parameters
        $search = $_GET['search'] ?? '';
        $status = $_GET['status'] ?? '';
        $limit = (int)($_GET['limit'] ?? 50);
        $offset = (int)($_GET['offset'] ?? 0);
        
        // Try main routes table with proper joins
        $sql = "SELECT 
                    r.id,
                    r.route_id,
                    CONCAT(oc.name, ' → ', dc.name) as route_name,
                    oc.name as origin_city,
                    dc.name as destination_city,
                    r.distance_km,
                    r.estimated_duration_hours,
                    r.base_price,
                    CASE WHEN r.is_active = 1 THEN 'active' ELSE 'inactive' END as status,
                    r.created_at,
                    r.updated_at,
                    COUNT(DISTINCT s.id) as assigned_schedules,
                    COUNT(DISTINCT b.id) as assigned_buses
                FROM routes r
                LEFT JOIN cities oc ON r.origin_city_id = oc.id
                LEFT JOIN cities dc ON r.destination_city_id = dc.id
                LEFT JOIN schedules s ON r.id = s.route_id AND s.is_active = 1
                LEFT JOIN buses b ON s.bus_id = b.id AND b.status = 'active'
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
        $sql .= " ORDER BY r.created_at DESC LIMIT ? OFFSET ?";
        $params[] = $limit;
        $params[] = $offset;
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $routes = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // If no routes in main table, try admin_routes table as fallback
        if (empty($routes)) {
            $sql = "SELECT 
                        id,
                        route_name,
                        origin_city,
                        destination_city,
                        distance_km,
                        estimated_duration,
                        base_price,
                        status,
                        description,
                        stops,
                        created_at,
                        updated_at,
                        0 as assigned_schedules,
                        0 as assigned_buses
                    FROM admin_routes
                    WHERE 1=1";
            
            $params = [];
            
            if (!empty($search)) {
                $sql .= " AND (route_name LIKE ? OR origin_city LIKE ? OR destination_city LIKE ?)";
                $searchParam = "%$search%";
                $params = array_merge($params, [$searchParam, $searchParam, $searchParam]);
            }
            
            if (!empty($status)) {
                $sql .= " AND status = ?";
                $params[] = $status;
            }
            
            $sql .= " ORDER BY created_at DESC LIMIT ? OFFSET ?";
            $params[] = $limit;
            $params[] = $offset;
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $routes = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
        
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
            $route['id'] = $route['route_id'] ?? $route['id'];
            $route['name'] = $route['route_name'] ?? ($route['origin_city'] . ' → ' . $route['destination_city']);
            $route['distance'] = (int)($route['distance_km'] ?? 0);
            $route['estimated_duration'] = $route['estimated_duration'] ?? 'N/A';
            $route['fare_base'] = (float)($route['base_price'] ?? 0);
            $route['assigned_buses'] = (int)($route['assigned_buses'] ?? 0);
            $route['assigned_schedules'] = (int)($route['assigned_schedules'] ?? 0);
            $route['description'] = $route['description'] ?? "Route from {$route['origin_city']} to {$route['destination_city']}";
            
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

function addRoute() {
    try {
        $pdo = getDB();
        
        $input = json_decode(file_get_contents('php://input'), true);
        
        // Validate required fields
        $required = ['route_name', 'origin_city', 'destination_city', 'base_price'];
        foreach ($required as $field) {
            if (empty($input[$field])) {
                throw new Exception("Field '$field' is required");
            }
        }
        
        // Prepare data
        $routeName = $input['route_name'];
        $originCity = $input['origin_city'];
        $destinationCity = $input['destination_city'];
        $distanceKm = (float)($input['distance_km'] ?? 0);
        $estimatedDuration = $input['estimated_duration'] ?? '';
        $basePrice = (float)$input['base_price'];
        $status = $input['status'] ?? 'Active';
        $description = $input['description'] ?? '';
        $stops = isset($input['stops']) ? json_encode($input['stops']) : null;
        
        // Insert new route
        $stmt = $pdo->prepare("
            INSERT INTO admin_routes (
                route_name, origin_city, destination_city, distance_km, 
                estimated_duration, base_price, status, description, stops
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $result = $stmt->execute([
            $routeName, $originCity, $destinationCity, $distanceKm,
            $estimatedDuration, $basePrice, $status, $description, $stops
        ]);
        
        if (!$result) {
            throw new Exception('Failed to add route');
        }
        
        echo json_encode([
            'success' => true,
            'message' => 'Route added successfully',
            'route_id' => $pdo->lastInsertId()
        ]);
        
    } catch (Exception $e) {
        throw new Exception('Failed to add route: ' . $e->getMessage());
    }
}

function updateRoute() {
    try {
        $pdo = getDB();
        
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!isset($input['id'])) {
            throw new Exception('Route ID is required');
        }
        
        $routeId = $input['id'];
        
        // Build update query
        $updateFields = [];
        $params = [];
        
        $allowedFields = ['route_name', 'origin_city', 'destination_city', 'distance_km', 
                         'estimated_duration', 'base_price', 'status', 'description'];
        
        foreach ($allowedFields as $field) {
            if (isset($input[$field])) {
                $updateFields[] = "$field = ?";
                $params[] = $input[$field];
            }
        }
        
        if (isset($input['stops'])) {
            $updateFields[] = "stops = ?";
            $params[] = json_encode($input['stops']);
        }
        
        $updateFields[] = "updated_at = NOW()";
        
        if (empty($updateFields)) {
            throw new Exception('No fields to update');
        }
        
        $sql = "UPDATE admin_routes SET " . implode(', ', $updateFields) . " WHERE id = ?";
        $params[] = $routeId;
        
        $stmt = $pdo->prepare($sql);
        $result = $stmt->execute($params);
        
        if (!$result) {
            throw new Exception('Failed to update route');
        }
        
        if ($stmt->rowCount() === 0) {
            throw new Exception('Route not found');
        }
        
        echo json_encode([
            'success' => true,
            'message' => 'Route updated successfully'
        ]);
        
    } catch (Exception $e) {
        throw new Exception('Failed to update route: ' . $e->getMessage());
    }
}

function deleteRoute() {
    try {
        $pdo = getDB();
        
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!isset($input['id'])) {
            throw new Exception('Route ID is required');
        }
        
        $routeId = $input['id'];
        
        // Check if route has active schedules
        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM admin_schedules WHERE route_id = ? AND status = 'Active'");
        $stmt->execute([$routeId]);
        $scheduleCount = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
        
        if ($scheduleCount > 0) {
            // Don't delete, just deactivate
            $stmt = $pdo->prepare("UPDATE admin_routes SET status = 'Inactive', updated_at = NOW() WHERE id = ?");
            $result = $stmt->execute([$routeId]);
            $message = 'Route deactivated (has active schedules)';
        } else {
            // Safe to delete
            $stmt = $pdo->prepare("DELETE FROM admin_routes WHERE id = ?");
            $result = $stmt->execute([$routeId]);
            $message = 'Route deleted successfully';
        }
        
        if (!$result) {
            throw new Exception('Failed to delete route');
        }
        
        if ($stmt->rowCount() === 0) {
            throw new Exception('Route not found');
        }
        
        echo json_encode([
            'success' => true,
            'message' => $message
        ]);
        
    } catch (Exception $e) {
        throw new Exception('Failed to delete route: ' . $e->getMessage());
    }
}

function getRouteDetails() {
    try {
        $pdo = getDB();
        
        $routeId = $_GET['route_id'] ?? '';
        
        if (empty($routeId)) {
            throw new Exception('Route ID is required');
        }
        
        $sql = "SELECT * FROM admin_routes WHERE id = ?";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$routeId]);
        $route = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$route) {
            throw new Exception('Route not found');
        }
        
        // Parse stops if it's JSON
        if (!empty($route['stops'])) {
            $route['stops'] = json_decode($route['stops'], true);
        }
        
        echo json_encode([
            'success' => true,
            'data' => $route
        ]);
        
    } catch (Exception $e) {
        throw new Exception('Failed to get route details: ' . $e->getMessage());
    }
}
?>