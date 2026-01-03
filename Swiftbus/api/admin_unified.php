<?php
/**
 * SwiftBus Admin Unified API
 * Provides consistent data structure for all admin operations
 * Centralizes data fetching and ensures consistency across all admin pages
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

require_once '../includes/config.php';
require_once '../includes/functions.php';

// Check authentication and admin access
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

$pdo = getDB();
$action = $_GET['action'] ?? $_POST['action'] ?? '';

try {
    switch ($action) {
        // Booking operations
        case 'get_bookings':
            getUnifiedBookings();
            break;
        case 'get_booking_stats':
            getBookingStatistics();
            break;
        case 'update_booking':
            updateBooking();
            break;
            
        // User operations
        case 'get_users':
            getUnifiedUsers();
            break;
        case 'get_user_stats':
            getUserStatistics();
            break;
        case 'update_user':
            updateUser();
            break;
            
        // Bus operations
        case 'get_buses':
            getUnifiedBuses();
            break;
        case 'get_bus_stats':
            getBusStatistics();
            break;
        case 'update_bus':
            updateBus();
            break;
            
        // Route operations
        case 'get_routes':
            getUnifiedRoutes();
            break;
        case 'get_route_stats':
            getRouteStatistics();
            break;
        case 'update_route':
            updateRoute();
            break;
            
        // Schedule operations
        case 'get_schedules':
            getUnifiedSchedules();
            break;
        case 'get_schedule_stats':
            getScheduleStatistics();
            break;
        case 'update_schedule':
            updateSchedule();
            break;
            
        // Dashboard operations
        case 'get_dashboard_data':
            getDashboardData();
            break;
            
        // Sync operations
        case 'sync_all_data':
            syncAllData();
            break;
            
        default:
            throw new Exception('Invalid action: ' . $action);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'error_code' => 'ADMIN_API_ERROR'
    ]);
}

/**
 * Get unified bookings data with consistent structure
 */
function getUnifiedBookings() {
    try {
        $pdo = getDB();
        
        // Get filter parameters
        $search = $_GET['search'] ?? '';
        $booking_status = $_GET['booking_status'] ?? '';
        $payment_status = $_GET['payment_status'] ?? '';
        $travel_date = $_GET['travel_date'] ?? '';
        $limit = min((int)($_GET['limit'] ?? 100), 500); // Max 500 records
        $offset = (int)($_GET['offset'] ?? 0);
        
        // Build comprehensive query with all necessary joins
        $sql = "SELECT 
                    b.id,
                    b.booking_id,
                    b.user_id,
                    COALESCE(u.full_name, 'Unknown User') as user_name,
                    COALESCE(u.email, '') as user_email,
                    COALESCE(u.phone, '') as user_phone,
                    b.bus_company,
                    b.bus_type,
                    CONCAT(COALESCE(b.bus_company, 'Unknown'), ' - ', COALESCE(b.bus_type, 'Standard')) as bus_name,
                    b.from_city as route_origin,
                    b.to_city as route_destination,
                    CONCAT(COALESCE(b.from_city, ''), ' → ', COALESCE(b.to_city, '')) as route_display,
                    b.travel_date,
                    b.departure_time,
                    b.passenger_count,
                    b.selected_seats as seat_numbers,
                    b.total_amount,
                    LOWER(COALESCE(b.booking_status, 'pending')) as booking_status,
                    LOWER(COALESCE(b.payment_status, 'pending')) as payment_status,
                    b.booking_date,
                    b.special_requirements,
                    b.created_at,
                    b.updated_at,
                    -- Additional computed fields
                    CASE 
                        WHEN b.booking_status = 'confirmed' AND b.payment_status = 'paid' THEN 'completed'
                        WHEN b.booking_status = 'cancelled' THEN 'cancelled'
                        WHEN b.payment_status = 'failed' THEN 'payment_failed'
                        ELSE 'pending'
                    END as overall_status,
                    DATEDIFF(b.travel_date, CURDATE()) as days_until_travel
                FROM bookings b
                LEFT JOIN users u ON b.user_id = u.id
                WHERE 1=1";
        
        $params = [];
        
        // Add search filter
        if (!empty($search)) {
            $sql .= " AND (b.booking_id LIKE ? OR u.full_name LIKE ? OR u.phone LIKE ? OR u.email LIKE ? OR b.from_city LIKE ? OR b.to_city LIKE ?)";
            $searchParam = "%$search%";
            $params = array_merge($params, array_fill(0, 6, $searchParam));
        }
        
        // Add status filters
        if (!empty($booking_status)) {
            $sql .= " AND LOWER(b.booking_status) = ?";
            $params[] = strtolower($booking_status);
        }
        
        if (!empty($payment_status)) {
            $sql .= " AND LOWER(b.payment_status) = ?";
            $params[] = strtolower($payment_status);
        }
        
        if (!empty($travel_date)) {
            $sql .= " AND b.travel_date = ?";
            $params[] = $travel_date;
        }
        
        // Add ordering and pagination
        $sql .= " ORDER BY b.created_at DESC LIMIT ? OFFSET ?";
        $params[] = $limit;
        $params[] = $offset;
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Process and normalize the data
        foreach ($bookings as &$booking) {
            // Parse selected seats if it's JSON
            if (!empty($booking['seat_numbers'])) {
                $seats = json_decode($booking['seat_numbers'], true);
                if (is_array($seats)) {
                    $booking['seat_numbers'] = implode(', ', $seats);
                    $booking['seat_count'] = count($seats);
                } else {
                    $booking['seat_count'] = $booking['passenger_count'] ?? 1;
                }
            } else {
                $booking['seat_count'] = $booking['passenger_count'] ?? 1;
            }
            
            // Format dates consistently
            $booking['travel_date'] = formatDate($booking['travel_date']);
            $booking['booking_date'] = formatDate($booking['booking_date']);
            $booking['created_at'] = formatDateTime($booking['created_at']);
            $booking['updated_at'] = formatDateTime($booking['updated_at']);
            
            // Format amount
            $booking['total_amount'] = (float)$booking['total_amount'];
            $booking['formatted_amount'] = 'ETB ' . number_format($booking['total_amount'], 2);
            
            // Add status badges
            $booking['status_badge'] = getStatusBadge($booking['booking_status']);
            $booking['payment_badge'] = getPaymentBadge($booking['payment_status']);
        }
        
        // Get total count for pagination
        $countSql = "SELECT COUNT(*) as total FROM bookings b LEFT JOIN users u ON b.user_id = u.id WHERE 1=1";
        $countParams = [];
        
        // Add same filters to count query
        if (!empty($search)) {
            $countSql .= " AND (b.booking_id LIKE ? OR u.full_name LIKE ? OR u.phone LIKE ? OR u.email LIKE ? OR b.from_city LIKE ? OR b.to_city LIKE ?)";
            $searchParam = "%$search%";
            $countParams = array_fill(0, 6, $searchParam);
        }
        
        if (!empty($booking_status)) {
            $countSql .= " AND LOWER(b.booking_status) = ?";
            $countParams[] = strtolower($booking_status);
        }
        
        if (!empty($payment_status)) {
            $countSql .= " AND LOWER(b.payment_status) = ?";
            $countParams[] = strtolower($payment_status);
        }
        
        if (!empty($travel_date)) {
            $countSql .= " AND b.travel_date = ?";
            $countParams[] = $travel_date;
        }
        
        $countStmt = $pdo->prepare($countSql);
        $countStmt->execute($countParams);
        $totalCount = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];
        
        echo json_encode([
            'success' => true,
            'data' => $bookings,
            'pagination' => [
                'total' => (int)$totalCount,
                'limit' => $limit,
                'offset' => $offset,
                'has_more' => ($offset + $limit) < $totalCount,
                'current_page' => floor($offset / $limit) + 1,
                'total_pages' => ceil($totalCount / $limit)
            ],
            'metadata' => [
                'entity_type' => 'bookings',
                'generated_at' => date('Y-m-d H:i:s'),
                'cache_key' => 'admin_bookings',
                'refresh_interval' => 30000
            ]
        ]);
        
    } catch (Exception $e) {
        throw new Exception('Failed to fetch bookings: ' . $e->getMessage());
    }
}

/**
 * Get unified users data
 */
function getUnifiedUsers() {
    try {
        $pdo = getDB();
        
        $search = $_GET['search'] ?? '';
        $status = $_GET['status'] ?? '';
        $role = $_GET['role'] ?? '';
        $limit = min((int)($_GET['limit'] ?? 100), 500);
        $offset = (int)($_GET['offset'] ?? 0);
        
        $sql = "SELECT 
                    u.id,
                    u.user_id,
                    u.full_name,
                    u.email,
                    u.phone,
                    u.role,
                    CASE WHEN u.is_active = 1 THEN 'active' ELSE 'inactive' END as status,
                    CASE WHEN u.is_verified = 1 THEN 'verified' ELSE 'unverified' END as verification_status,
                    u.joined_date,
                    u.last_login,
                    u.profile_image,
                    u.created_at,
                    u.updated_at,
                    -- Computed fields
                    COUNT(b.id) as total_bookings,
                    COALESCE(SUM(b.total_amount), 0) as total_spent,
                    MAX(b.created_at) as last_booking_date,
                    DATEDIFF(CURDATE(), u.last_login) as days_since_login
                FROM users u
                LEFT JOIN bookings b ON u.id = b.user_id
                WHERE 1=1";
        
        $params = [];
        
        if (!empty($search)) {
            $sql .= " AND (u.full_name LIKE ? OR u.email LIKE ? OR u.phone LIKE ?)";
            $searchParam = "%$search%";
            $params = array_merge($params, [$searchParam, $searchParam, $searchParam]);
        }
        
        if (!empty($status)) {
            if ($status === 'active') {
                $sql .= " AND u.is_active = 1";
            } elseif ($status === 'inactive') {
                $sql .= " AND u.is_active = 0";
            } elseif ($status === 'verified') {
                $sql .= " AND u.is_verified = 1";
            } elseif ($status === 'unverified') {
                $sql .= " AND u.is_verified = 0";
            }
        }
        
        if (!empty($role)) {
            $sql .= " AND u.role = ?";
            $params[] = $role;
        }
        
        $sql .= " GROUP BY u.id ORDER BY u.created_at DESC LIMIT ? OFFSET ?";
        $params[] = $limit;
        $params[] = $offset;
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Process users data
        foreach ($users as &$user) {
            $user['joined_date'] = formatDate($user['joined_date']);
            $user['last_login'] = formatDateTime($user['last_login']);
            $user['last_booking_date'] = formatDateTime($user['last_booking_date']);
            $user['created_at'] = formatDateTime($user['created_at']);
            $user['updated_at'] = formatDateTime($user['updated_at']);
            
            $user['total_spent'] = (float)$user['total_spent'];
            $user['formatted_spent'] = 'ETB ' . number_format($user['total_spent'], 2);
            
            $user['status_badge'] = getStatusBadge($user['status']);
            $user['verification_badge'] = getVerificationBadge($user['verification_status']);
        }
        
        // Get total count
        $countSql = "SELECT COUNT(*) as total FROM users u WHERE 1=1";
        $countParams = [];
        
        if (!empty($search)) {
            $countSql .= " AND (u.full_name LIKE ? OR u.email LIKE ? OR u.phone LIKE ?)";
            $searchParam = "%$search%";
            $countParams = [$searchParam, $searchParam, $searchParam];
        }
        
        if (!empty($status)) {
            if ($status === 'active') {
                $countSql .= " AND u.is_active = 1";
            } elseif ($status === 'inactive') {
                $countSql .= " AND u.is_active = 0";
            }
        }
        
        if (!empty($role)) {
            $countSql .= " AND u.role = ?";
            $countParams[] = $role;
        }
        
        $countStmt = $pdo->prepare($countSql);
        $countStmt->execute($countParams);
        $totalCount = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];
        
        echo json_encode([
            'success' => true,
            'data' => $users,
            'pagination' => [
                'total' => (int)$totalCount,
                'limit' => $limit,
                'offset' => $offset,
                'has_more' => ($offset + $limit) < $totalCount,
                'current_page' => floor($offset / $limit) + 1,
                'total_pages' => ceil($totalCount / $limit)
            ],
            'metadata' => [
                'entity_type' => 'users',
                'generated_at' => date('Y-m-d H:i:s'),
                'cache_key' => 'admin_users',
                'refresh_interval' => 60000
            ]
        ]);
        
    } catch (Exception $e) {
        throw new Exception('Failed to fetch users: ' . $e->getMessage());
    }
}

/**
 * Get comprehensive dashboard data
 */
function getDashboardData() {
    try {
        $pdo = getDB();
        
        // Get current date ranges
        $today = date('Y-m-d');
        $monthStart = date('Y-m-01');
        
        // Booking statistics
        $bookingStats = [];
        
        // Today's bookings
        $stmt = $pdo->prepare("SELECT 
                              COUNT(*) as count, 
                              COALESCE(SUM(total_amount), 0) as revenue,
                              COUNT(CASE WHEN booking_status = 'confirmed' THEN 1 END) as confirmed,
                              COUNT(CASE WHEN booking_status = 'pending' THEN 1 END) as pending,
                              COUNT(CASE WHEN booking_status = 'cancelled' THEN 1 END) as cancelled
                              FROM bookings WHERE DATE(created_at) = ?");
        $stmt->execute([$today]);
        $todayData = $stmt->fetch();
        
        $bookingStats['today'] = [
            'total' => (int)$todayData['count'],
            'revenue' => (float)$todayData['revenue'],
            'confirmed' => (int)$todayData['confirmed'],
            'pending' => (int)$todayData['pending'],
            'cancelled' => (int)$todayData['cancelled']
        ];
        
        // This month's bookings
        $stmt = $pdo->prepare("SELECT 
                              COUNT(*) as count, 
                              COALESCE(SUM(total_amount), 0) as revenue,
                              COUNT(CASE WHEN booking_status = 'confirmed' THEN 1 END) as confirmed,
                              COUNT(CASE WHEN booking_status = 'pending' THEN 1 END) as pending,
                              COUNT(CASE WHEN booking_status = 'cancelled' THEN 1 END) as cancelled
                              FROM bookings WHERE DATE(created_at) >= ?");
        $stmt->execute([$monthStart]);
        $monthData = $stmt->fetch();
        
        $bookingStats['month'] = [
            'total' => (int)$monthData['count'],
            'revenue' => (float)$monthData['revenue'],
            'confirmed' => (int)$monthData['confirmed'],
            'pending' => (int)$monthData['pending'],
            'cancelled' => (int)$monthData['cancelled']
        ];
        
        // User statistics
        $stmt = $pdo->prepare("SELECT 
                              COUNT(*) as total,
                              COUNT(CASE WHEN is_active = 1 THEN 1 END) as active,
                              COUNT(CASE WHEN role = 'admin' THEN 1 END) as admins,
                              COUNT(CASE WHEN DATE(created_at) = ? THEN 1 END) as new_today
                              FROM users");
        $stmt->execute([$today]);
        $userStats = $stmt->fetch();
        
        // Bus statistics (handle case where buses table might not exist)
        $busStats = ['total' => 0, 'active' => 0, 'maintenance' => 0, 'inactive' => 0];
        try {
            $stmt = $pdo->prepare("SELECT 
                                  COUNT(*) as total,
                                  COUNT(CASE WHEN status = 'active' THEN 1 END) as active,
                                  COUNT(CASE WHEN status = 'maintenance' THEN 1 END) as maintenance,
                                  COUNT(CASE WHEN status = 'inactive' THEN 1 END) as inactive
                                  FROM buses");
            $stmt->execute();
            $busStats = $stmt->fetch();
        } catch (Exception $e) {
            // Buses table might not exist, use default values
        }
        
        // Route statistics (handle case where routes table might not exist)
        $routeStats = ['total' => 0, 'active' => 0];
        try {
            $stmt = $pdo->prepare("SELECT 
                                  COUNT(*) as total,
                                  COUNT(CASE WHEN is_active = 1 THEN 1 END) as active
                                  FROM routes");
            $stmt->execute();
            $routeStats = $stmt->fetch();
        } catch (Exception $e) {
            // Routes table might not exist, use default values
        }
        
        $dashboardData = [
            'bookings' => $bookingStats,
            'users' => [
                'total' => (int)$userStats['total'],
                'active' => (int)$userStats['active'],
                'admins' => (int)$userStats['admins'],
                'new_today' => (int)$userStats['new_today']
            ],
            'buses' => [
                'total' => (int)$busStats['total'],
                'active' => (int)$busStats['active'],
                'maintenance' => (int)$busStats['maintenance'],
                'inactive' => (int)$busStats['inactive']
            ],
            'routes' => [
                'total' => (int)$routeStats['total'],
                'active' => (int)$routeStats['active']
            ],
            'generated_at' => date('Y-m-d H:i:s')
        ];
        
        echo json_encode([
            'success' => true,
            'data' => $dashboardData,
            'metadata' => [
                'entity_type' => 'dashboard',
                'generated_at' => date('Y-m-d H:i:s'),
                'cache_key' => 'admin_dashboard',
                'refresh_interval' => 30000
            ]
        ]);
        
    } catch (Exception $e) {
        throw new Exception('Failed to fetch dashboard data: ' . $e->getMessage());
    }
}

/**
 * Sync all data - returns summary of all entities
 */
function syncAllData() {
    try {
        $syncData = [
            'bookings' => [],
            'users' => [],
            'buses' => [],
            'routes' => [],
            'dashboard' => []
        ];
        
        // Get basic counts for each entity
        $pdo = getDB();
        
        // Bookings summary
        $stmt = $pdo->prepare("SELECT 
                              COUNT(*) as total,
                              COUNT(CASE WHEN booking_status = 'confirmed' THEN 1 END) as confirmed,
                              COUNT(CASE WHEN booking_status = 'pending' THEN 1 END) as pending,
                              COUNT(CASE WHEN booking_status = 'cancelled' THEN 1 END) as cancelled
                              FROM bookings");
        $stmt->execute();
        $syncData['bookings'] = $stmt->fetch();
        
        // Users summary
        $stmt = $pdo->prepare("SELECT 
                              COUNT(*) as total,
                              COUNT(CASE WHEN is_active = 1 THEN 1 END) as active,
                              COUNT(CASE WHEN role = 'admin' THEN 1 END) as admins
                              FROM users");
        $stmt->execute();
        $syncData['users'] = $stmt->fetch();
        
        // Buses summary
        $stmt = $pdo->prepare("SELECT 
                              COUNT(*) as total,
                              COUNT(CASE WHEN status = 'active' THEN 1 END) as active
                              FROM buses");
        $stmt->execute();
        $syncData['buses'] = $stmt->fetch();
        
        // Routes summary
        $stmt = $pdo->prepare("SELECT 
                              COUNT(*) as total,
                              COUNT(CASE WHEN is_active = 1 THEN 1 END) as active
                              FROM routes");
        $stmt->execute();
        $syncData['routes'] = $stmt->fetch();
        
        echo json_encode([
            'success' => true,
            'data' => $syncData,
            'metadata' => [
                'sync_timestamp' => date('Y-m-d H:i:s'),
                'entities_synced' => array_keys($syncData)
            ]
        ]);
        
    } catch (Exception $e) {
        throw new Exception('Failed to sync data: ' . $e->getMessage());
    }
}

/**
 * Get booking statistics
 */
function getBookingStatistics() {
    try {
        $pdo = getDB();
        
        $today = date('Y-m-d');
        $monthStart = date('Y-m-01');
        
        $stmt = $pdo->prepare("SELECT 
                              COUNT(*) as total,
                              COUNT(CASE WHEN booking_status = 'confirmed' THEN 1 END) as confirmed,
                              COUNT(CASE WHEN booking_status = 'pending' THEN 1 END) as pending,
                              COUNT(CASE WHEN booking_status = 'cancelled' THEN 1 END) as cancelled,
                              COALESCE(SUM(total_amount), 0) as total_revenue,
                              COUNT(CASE WHEN DATE(created_at) = ? THEN 1 END) as today_bookings,
                              COUNT(CASE WHEN DATE(created_at) >= ? THEN 1 END) as month_bookings
                              FROM bookings");
        $stmt->execute([$today, $monthStart]);
        $stats = $stmt->fetch();
        
        echo json_encode([
            'success' => true,
            'data' => $stats,
            'metadata' => [
                'entity_type' => 'booking_stats',
                'generated_at' => date('Y-m-d H:i:s')
            ]
        ]);
        
    } catch (Exception $e) {
        throw new Exception('Failed to fetch booking statistics: ' . $e->getMessage());
    }
}

/**
 * Get user statistics
 */
function getUserStatistics() {
    try {
        $pdo = getDB();
        
        $today = date('Y-m-d');
        
        $stmt = $pdo->prepare("SELECT 
                              COUNT(*) as total,
                              COUNT(CASE WHEN is_active = 1 THEN 1 END) as active,
                              COUNT(CASE WHEN is_active = 0 THEN 1 END) as inactive,
                              COUNT(CASE WHEN role = 'admin' THEN 1 END) as admins,
                              COUNT(CASE WHEN role = 'user' THEN 1 END) as regular_users,
                              COUNT(CASE WHEN is_verified = 1 THEN 1 END) as verified,
                              COUNT(CASE WHEN is_verified = 0 THEN 1 END) as unverified,
                              COUNT(CASE WHEN DATE(created_at) = ? THEN 1 END) as new_today
                              FROM users");
        $stmt->execute([$today]);
        $stats = $stmt->fetch();
        
        echo json_encode([
            'success' => true,
            'data' => $stats,
            'metadata' => [
                'entity_type' => 'user_stats',
                'generated_at' => date('Y-m-d H:i:s')
            ]
        ]);
        
    } catch (Exception $e) {
        throw new Exception('Failed to fetch user statistics: ' . $e->getMessage());
    }
}

/**
 * Get unified buses data
 */
function getUnifiedBuses() {
    try {
        $pdo = getDB();
        
        $search = $_GET['search'] ?? '';
        $status = $_GET['status'] ?? '';
        $type = $_GET['type'] ?? '';
        $limit = min((int)($_GET['limit'] ?? 100), 500);
        $offset = (int)($_GET['offset'] ?? 0);
        
        // Query buses with company name from bus_companies table
        $sql = "SELECT 
                    b.id,
                    b.bus_id,
                    b.bus_number,
                    b.bus_type,
                    b.total_seats,
                    b.status,
                    b.license_plate,
                    b.model,
                    b.amenities,
                    b.created_at,
                    b.updated_at,
                    bc.name as company_name,
                    bc.company_id as company_code
                FROM buses b
                LEFT JOIN bus_companies bc ON b.company_id = bc.id
                WHERE 1=1";
        
        $params = [];
        
        if (!empty($search)) {
            $sql .= " AND (b.bus_id LIKE ? OR b.bus_number LIKE ? OR bc.name LIKE ? OR b.license_plate LIKE ?)";
            $searchParam = "%$search%";
            $params = array_merge($params, [$searchParam, $searchParam, $searchParam, $searchParam]);
        }
        
        if (!empty($status)) {
            $sql .= " AND b.status = ?";
            $params[] = $status;
        }
        
        if (!empty($type)) {
            $sql .= " AND b.bus_type = ?";
            $params[] = $type;
        }
        
        $sql .= " ORDER BY b.created_at DESC LIMIT ? OFFSET ?";
        $params[] = $limit;
        $params[] = $offset;
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $buses = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Process buses data
        foreach ($buses as &$bus) {
            $bus['bus_name'] = ($bus['company_name'] ?? 'Unknown') . ' - ' . ($bus['bus_number'] ?? 'Unknown');
            $bus['type_display'] = ucfirst(str_replace('-', ' ', $bus['bus_type'] ?? 'standard'));
            $bus['created_at'] = formatDateTime($bus['created_at']);
            $bus['updated_at'] = formatDateTime($bus['updated_at']);
            $bus['status_badge'] = getStatusBadge($bus['status'] ?? 'unknown');
            // Parse amenities if JSON
            if (!empty($bus['amenities'])) {
                $amenities = json_decode($bus['amenities'], true);
                $bus['amenities_list'] = is_array($amenities) ? $amenities : [];
            } else {
                $bus['amenities_list'] = [];
            }
        }
        
        // Get total count
        $countSql = "SELECT COUNT(*) as total FROM buses b LEFT JOIN bus_companies bc ON b.company_id = bc.id WHERE 1=1";
        $countParams = [];
        
        if (!empty($search)) {
            $countSql .= " AND (b.bus_id LIKE ? OR b.bus_number LIKE ? OR bc.name LIKE ? OR b.license_plate LIKE ?)";
            $searchParam = "%$search%";
            $countParams = [$searchParam, $searchParam, $searchParam, $searchParam];
        }
        
        if (!empty($status)) {
            $countSql .= " AND b.status = ?";
            $countParams[] = $status;
        }
        
        if (!empty($type)) {
            $countSql .= " AND b.bus_type = ?";
            $countParams[] = $type;
        }
        
        $countStmt = $pdo->prepare($countSql);
        $countStmt->execute($countParams);
        $totalCount = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];
        
        echo json_encode([
            'success' => true,
            'data' => $buses,
            'pagination' => [
                'total' => (int)$totalCount,
                'limit' => $limit,
                'offset' => $offset,
                'has_more' => ($offset + $limit) < $totalCount,
                'current_page' => floor($offset / $limit) + 1,
                'total_pages' => ceil($totalCount / $limit)
            ],
            'metadata' => [
                'entity_type' => 'buses',
                'generated_at' => date('Y-m-d H:i:s'),
                'cache_key' => 'admin_buses',
                'refresh_interval' => 120000
            ]
        ]);
        
    } catch (Exception $e) {
        throw new Exception('Failed to fetch buses: ' . $e->getMessage());
    }
}

/**
 * Get bus statistics
 */
function getBusStatistics() {
    try {
        $pdo = getDB();
        
        // Check if buses table exists
        $stmt = $pdo->prepare("SHOW TABLES LIKE 'buses'");
        $stmt->execute();
        if ($stmt->rowCount() === 0) {
            echo json_encode([
                'success' => true,
                'data' => [
                    'total' => 0,
                    'active' => 0,
                    'maintenance' => 0,
                    'inactive' => 0
                ],
                'metadata' => [
                    'entity_type' => 'bus_stats',
                    'generated_at' => date('Y-m-d H:i:s'),
                    'note' => 'Buses table not found'
                ]
            ]);
            return;
        }
        
        $stmt = $pdo->prepare("SELECT 
                              COUNT(*) as total,
                              COUNT(CASE WHEN status = 'active' THEN 1 END) as active,
                              COUNT(CASE WHEN status = 'maintenance' THEN 1 END) as maintenance,
                              COUNT(CASE WHEN status = 'inactive' THEN 1 END) as inactive
                              FROM buses");
        $stmt->execute();
        $stats = $stmt->fetch();
        
        echo json_encode([
            'success' => true,
            'data' => $stats,
            'metadata' => [
                'entity_type' => 'bus_stats',
                'generated_at' => date('Y-m-d H:i:s')
            ]
        ]);
        
    } catch (Exception $e) {
        throw new Exception('Failed to fetch bus statistics: ' . $e->getMessage());
    }
}

/**
 * Get unified routes data
 */
function getUnifiedRoutes() {
    try {
        $pdo = getDB();
        
        $search = $_GET['search'] ?? '';
        $status = $_GET['status'] ?? '';
        $limit = min((int)($_GET['limit'] ?? 100), 500);
        $offset = (int)($_GET['offset'] ?? 0);
        
        // Query routes with city names from cities table
        $sql = "SELECT 
                    r.id,
                    r.route_id,
                    r.origin_city_id,
                    r.destination_city_id,
                    r.distance_km,
                    r.estimated_duration_hours,
                    r.base_price,
                    r.is_active,
                    r.created_at,
                    r.updated_at,
                    oc.name as origin_city,
                    oc.city_code as origin_code,
                    dc.name as destination_city,
                    dc.city_code as destination_code
                FROM routes r
                LEFT JOIN cities oc ON r.origin_city_id = oc.id
                LEFT JOIN cities dc ON r.destination_city_id = dc.id
                WHERE 1=1";
        
        $params = [];
        
        if (!empty($search)) {
            $sql .= " AND (r.route_id LIKE ? OR oc.name LIKE ? OR dc.name LIKE ?)";
            $searchParam = "%$search%";
            $params = array_merge($params, [$searchParam, $searchParam, $searchParam]);
        }
        
        if (!empty($status)) {
            if ($status === 'active') {
                $sql .= " AND r.is_active = 1";
            } elseif ($status === 'inactive') {
                $sql .= " AND r.is_active = 0";
            }
        }
        
        $sql .= " ORDER BY r.created_at DESC LIMIT ? OFFSET ?";
        $params[] = $limit;
        $params[] = $offset;
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $routes = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Process routes data
        foreach ($routes as &$route) {
            $route['route_name'] = ($route['origin_city'] ?? '') . ' - ' . ($route['destination_city'] ?? '');
            $route['route_display'] = ($route['origin_city'] ?? '') . ' → ' . ($route['destination_city'] ?? '');
            $route['status'] = $route['is_active'] ? 'active' : 'inactive';
            $route['distance'] = $route['distance_km'] . ' km';
            $route['duration'] = $route['estimated_duration_hours'] . ' hours';
            $route['price'] = 'ETB ' . number_format($route['base_price'], 2);
            $route['created_at'] = formatDateTime($route['created_at']);
            $route['updated_at'] = formatDateTime($route['updated_at']);
            $route['status_badge'] = getStatusBadge($route['status']);
        }
        
        // Get total count
        $countSql = "SELECT COUNT(*) as total FROM routes r 
                     LEFT JOIN cities oc ON r.origin_city_id = oc.id 
                     LEFT JOIN cities dc ON r.destination_city_id = dc.id 
                     WHERE 1=1";
        $countParams = [];
        
        if (!empty($search)) {
            $countSql .= " AND (r.route_id LIKE ? OR oc.name LIKE ? OR dc.name LIKE ?)";
            $searchParam = "%$search%";
            $countParams = [$searchParam, $searchParam, $searchParam];
        }
        
        if (!empty($status)) {
            if ($status === 'active') {
                $countSql .= " AND r.is_active = 1";
            } elseif ($status === 'inactive') {
                $countSql .= " AND r.is_active = 0";
            }
        }
        
        $countStmt = $pdo->prepare($countSql);
        $countStmt->execute($countParams);
        $totalCount = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];
        
        echo json_encode([
            'success' => true,
            'data' => $routes,
            'pagination' => [
                'total' => (int)$totalCount,
                'limit' => $limit,
                'offset' => $offset,
                'has_more' => ($offset + $limit) < $totalCount,
                'current_page' => floor($offset / $limit) + 1,
                'total_pages' => ceil($totalCount / $limit)
            ],
            'metadata' => [
                'entity_type' => 'routes',
                'generated_at' => date('Y-m-d H:i:s'),
                'cache_key' => 'admin_routes',
                'refresh_interval' => 300000
            ]
        ]);
        
    } catch (Exception $e) {
        throw new Exception('Failed to fetch routes: ' . $e->getMessage());
    }
}

/**
 * Get route statistics
 */
function getRouteStatistics() {
    try {
        $pdo = getDB();
        
        // Check if routes table exists
        $stmt = $pdo->prepare("SHOW TABLES LIKE 'routes'");
        $stmt->execute();
        if ($stmt->rowCount() === 0) {
            echo json_encode([
                'success' => true,
                'data' => [
                    'total' => 0,
                    'active' => 0,
                    'inactive' => 0
                ],
                'metadata' => [
                    'entity_type' => 'route_stats',
                    'generated_at' => date('Y-m-d H:i:s'),
                    'note' => 'Routes table not found'
                ]
            ]);
            return;
        }
        
        $stmt = $pdo->prepare("SELECT 
                              COUNT(*) as total,
                              COUNT(CASE WHEN is_active = 1 THEN 1 END) as active,
                              COUNT(CASE WHEN is_active = 0 THEN 1 END) as inactive
                              FROM routes");
        $stmt->execute();
        $stats = $stmt->fetch();
        
        echo json_encode([
            'success' => true,
            'data' => $stats,
            'metadata' => [
                'entity_type' => 'route_stats',
                'generated_at' => date('Y-m-d H:i:s')
            ]
        ]);
        
    } catch (Exception $e) {
        throw new Exception('Failed to fetch route statistics: ' . $e->getMessage());
    }
}

/**
 * Get unified schedules data
 */
function getUnifiedSchedules() {
    try {
        $pdo = getDB();
        
        $search = $_GET['search'] ?? '';
        $status = $_GET['status'] ?? '';
        $route = $_GET['route'] ?? '';
        $date = $_GET['date'] ?? '';
        $limit = min((int)($_GET['limit'] ?? 100), 500);
        $offset = (int)($_GET['offset'] ?? 0);
        
        // Query schedules with route and bus information
        $sql = "SELECT 
                    s.id,
                    s.schedule_id,
                    s.bus_id as bus_fk_id,
                    s.route_id as route_fk_id,
                    s.departure_time,
                    s.arrival_time,
                    s.days_of_week,
                    s.price,
                    s.is_active,
                    s.effective_from,
                    s.effective_until,
                    s.created_at,
                    s.updated_at,
                    oc.name as origin_city,
                    dc.name as destination_city,
                    b.bus_number,
                    b.bus_type,
                    bc.name as company_name
                FROM schedules s
                LEFT JOIN routes r ON s.route_id = r.id
                LEFT JOIN cities oc ON r.origin_city_id = oc.id
                LEFT JOIN cities dc ON r.destination_city_id = dc.id
                LEFT JOIN buses b ON s.bus_id = b.id
                LEFT JOIN bus_companies bc ON b.company_id = bc.id
                WHERE 1=1";
        
        $params = [];
        
        if (!empty($search)) {
            $sql .= " AND (s.schedule_id LIKE ? OR oc.name LIKE ? OR dc.name LIKE ? OR b.bus_number LIKE ? OR bc.name LIKE ?)";
            $searchParam = "%$search%";
            $params = array_merge($params, [$searchParam, $searchParam, $searchParam, $searchParam, $searchParam]);
        }
        
        if (!empty($status)) {
            if ($status === 'active') {
                $sql .= " AND s.is_active = 1";
            } elseif ($status === 'inactive') {
                $sql .= " AND s.is_active = 0";
            }
        }
        
        if (!empty($route)) {
            $sql .= " AND s.route_id = ?";
            $params[] = $route;
        }
        
        if (!empty($date)) {
            $sql .= " AND s.effective_from <= ? AND (s.effective_until IS NULL OR s.effective_until >= ?)";
            $params[] = $date;
            $params[] = $date;
        }
        
        $sql .= " ORDER BY s.created_at DESC LIMIT ? OFFSET ?";
        $params[] = $limit;
        $params[] = $offset;
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $schedules = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Process schedules data
        foreach ($schedules as &$schedule) {
            $schedule['route_name'] = ($schedule['origin_city'] ?? '') . ' - ' . ($schedule['destination_city'] ?? '');
            $schedule['route_display'] = ($schedule['origin_city'] ?? '') . ' → ' . ($schedule['destination_city'] ?? '');
            $schedule['bus_name'] = ($schedule['company_name'] ?? 'Unknown') . ' - ' . ($schedule['bus_number'] ?? 'Unknown');
            $schedule['bus_type_display'] = ucfirst(str_replace('-', ' ', $schedule['bus_type'] ?? 'standard'));
            $schedule['status'] = $schedule['is_active'] ? 'active' : 'inactive';
            $schedule['price_display'] = 'ETB ' . number_format($schedule['price'], 2);
            $schedule['effective_from'] = formatDate($schedule['effective_from']);
            $schedule['effective_until'] = formatDate($schedule['effective_until']);
            $schedule['created_at'] = formatDateTime($schedule['created_at']);
            $schedule['updated_at'] = formatDateTime($schedule['updated_at']);
            $schedule['status_badge'] = getStatusBadge($schedule['status']);
            // Parse days_of_week if JSON
            if (!empty($schedule['days_of_week'])) {
                $days = json_decode($schedule['days_of_week'], true);
                $schedule['days_list'] = is_array($days) ? $days : [];
                $schedule['days_display'] = is_array($days) ? implode(', ', $days) : $schedule['days_of_week'];
            } else {
                $schedule['days_list'] = [];
                $schedule['days_display'] = 'All days';
            }
        }
        
        // Get total count
        $countSql = "SELECT COUNT(*) as total FROM schedules s 
                     LEFT JOIN routes r ON s.route_id = r.id 
                     LEFT JOIN cities oc ON r.origin_city_id = oc.id
                     LEFT JOIN cities dc ON r.destination_city_id = dc.id
                     LEFT JOIN buses b ON s.bus_id = b.id 
                     LEFT JOIN bus_companies bc ON b.company_id = bc.id
                     WHERE 1=1";
        $countParams = [];
        
        if (!empty($search)) {
            $countSql .= " AND (s.schedule_id LIKE ? OR oc.name LIKE ? OR dc.name LIKE ? OR b.bus_number LIKE ? OR bc.name LIKE ?)";
            $searchParam = "%$search%";
            $countParams = [$searchParam, $searchParam, $searchParam, $searchParam, $searchParam];
        }
        
        if (!empty($status)) {
            if ($status === 'active') {
                $countSql .= " AND s.is_active = 1";
            } elseif ($status === 'inactive') {
                $countSql .= " AND s.is_active = 0";
            }
        }
        
        if (!empty($route)) {
            $countSql .= " AND s.route_id = ?";
            $countParams[] = $route;
        }
        
        if (!empty($date)) {
            $countSql .= " AND s.effective_from <= ? AND (s.effective_until IS NULL OR s.effective_until >= ?)";
            $countParams[] = $date;
            $countParams[] = $date;
        }
        
        $countStmt = $pdo->prepare($countSql);
        $countStmt->execute($countParams);
        $totalCount = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];
        
        echo json_encode([
            'success' => true,
            'data' => $schedules,
            'pagination' => [
                'total' => (int)$totalCount,
                'limit' => $limit,
                'offset' => $offset,
                'has_more' => ($offset + $limit) < $totalCount,
                'current_page' => floor($offset / $limit) + 1,
                'total_pages' => ceil($totalCount / $limit)
            ],
            'metadata' => [
                'entity_type' => 'schedules',
                'generated_at' => date('Y-m-d H:i:s'),
                'cache_key' => 'admin_schedules',
                'refresh_interval' => 60000
            ]
        ]);
        
    } catch (Exception $e) {
        throw new Exception('Failed to fetch schedules: ' . $e->getMessage());
    }
}

/**
 * Get schedule statistics
 */
function getScheduleStatistics() {
    try {
        $pdo = getDB();
        
        // Check if schedules table exists
        $stmt = $pdo->prepare("SHOW TABLES LIKE 'schedules'");
        $stmt->execute();
        if ($stmt->rowCount() === 0) {
            echo json_encode([
                'success' => true,
                'data' => [
                    'total' => 0,
                    'active' => 0,
                    'inactive' => 0
                ],
                'metadata' => [
                    'entity_type' => 'schedule_stats',
                    'generated_at' => date('Y-m-d H:i:s'),
                    'note' => 'Schedules table not found'
                ]
            ]);
            return;
        }
        
        $stmt = $pdo->prepare("SELECT 
                              COUNT(*) as total,
                              COUNT(CASE WHEN is_active = 1 THEN 1 END) as active,
                              COUNT(CASE WHEN is_active = 0 THEN 1 END) as inactive
                              FROM schedules");
        $stmt->execute();
        $stats = $stmt->fetch();
        
        echo json_encode([
            'success' => true,
            'data' => $stats,
            'metadata' => [
                'entity_type' => 'schedule_stats',
                'generated_at' => date('Y-m-d H:i:s')
            ]
        ]);
        
    } catch (Exception $e) {
        throw new Exception('Failed to fetch schedule statistics: ' . $e->getMessage());
    }
}

/**
 * Update booking (placeholder implementation)
 */
function updateBooking() {
    try {
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!isset($input['booking_id'])) {
            throw new Exception('Booking ID is required');
        }
        
        // This is a placeholder - implement actual update logic as needed
        echo json_encode([
            'success' => true,
            'message' => 'Booking update functionality not yet implemented',
            'data' => $input
        ]);
        
    } catch (Exception $e) {
        throw new Exception('Failed to update booking: ' . $e->getMessage());
    }
}

/**
 * Update user (placeholder implementation)
 */
function updateUser() {
    try {
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!isset($input['user_id'])) {
            throw new Exception('User ID is required');
        }
        
        // This is a placeholder - implement actual update logic as needed
        echo json_encode([
            'success' => true,
            'message' => 'User update functionality not yet implemented',
            'data' => $input
        ]);
        
    } catch (Exception $e) {
        throw new Exception('Failed to update user: ' . $e->getMessage());
    }
}

/**
 * Update bus (placeholder implementation)
 */
function updateBus() {
    try {
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!isset($input['bus_id'])) {
            throw new Exception('Bus ID is required');
        }
        
        // This is a placeholder - implement actual update logic as needed
        echo json_encode([
            'success' => true,
            'message' => 'Bus update functionality not yet implemented',
            'data' => $input
        ]);
        
    } catch (Exception $e) {
        throw new Exception('Failed to update bus: ' . $e->getMessage());
    }
}

/**
 * Update route (placeholder implementation)
 */
function updateRoute() {
    try {
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!isset($input['route_id'])) {
            throw new Exception('Route ID is required');
        }
        
        // This is a placeholder - implement actual update logic as needed
        echo json_encode([
            'success' => true,
            'message' => 'Route update functionality not yet implemented',
            'data' => $input
        ]);
        
    } catch (Exception $e) {
        throw new Exception('Failed to update route: ' . $e->getMessage());
    }
}

/**
 * Update schedule (placeholder implementation)
 */
function updateSchedule() {
    try {
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!isset($input['schedule_id'])) {
            throw new Exception('Schedule ID is required');
        }
        
        // This is a placeholder - implement actual update logic as needed
        echo json_encode([
            'success' => true,
            'message' => 'Schedule update functionality not yet implemented',
            'data' => $input
        ]);
        
    } catch (Exception $e) {
        throw new Exception('Failed to update schedule: ' . $e->getMessage());
    }
}

/**
 * Helper functions
 */
function formatDate($date) {
    if (!$date) return null;
    return date('Y-m-d', strtotime($date));
}

function formatDateTime($datetime) {
    if (!$datetime) return null;
    return date('Y-m-d H:i:s', strtotime($datetime));
}

function getStatusBadge($status) {
    $badges = [
        'confirmed' => ['class' => 'success', 'text' => 'Confirmed'],
        'pending' => ['class' => 'warning', 'text' => 'Pending'],
        'cancelled' => ['class' => 'danger', 'text' => 'Cancelled'],
        'active' => ['class' => 'success', 'text' => 'Active'],
        'inactive' => ['class' => 'danger', 'text' => 'Inactive']
    ];
    
    return $badges[$status] ?? ['class' => 'secondary', 'text' => ucfirst($status)];
}

function getPaymentBadge($status) {
    $badges = [
        'paid' => ['class' => 'success', 'text' => 'Paid'],
        'pending' => ['class' => 'warning', 'text' => 'Pending'],
        'failed' => ['class' => 'danger', 'text' => 'Failed'],
        'refunded' => ['class' => 'info', 'text' => 'Refunded']
    ];
    
    return $badges[$status] ?? ['class' => 'secondary', 'text' => ucfirst($status)];
}

function getVerificationBadge($status) {
    $badges = [
        'verified' => ['class' => 'success', 'text' => 'Verified'],
        'unverified' => ['class' => 'warning', 'text' => 'Unverified']
    ];
    
    return $badges[$status] ?? ['class' => 'secondary', 'text' => ucfirst($status)];
}
?>