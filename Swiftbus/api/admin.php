<?php
/**
 * SwiftBus Admin API
 * 
 * Handles admin operations for buses, routes, schedules, and bookings management
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    exit(0);
}

require_once '../includes/functions.php';

$action = $_GET['action'] ?? $_POST['action'] ?? '';

switch ($action) {
    // Dashboard
    case 'get_dashboard_stats':
        handleGetDashboardStats();
        break;
    
    // Bus Companies
    case 'get_companies':
        handleGetCompanies();
        break;
    case 'create_company':
        handleCreateCompany();
        break;
    case 'update_company':
        handleUpdateCompany();
        break;
    case 'delete_company':
        handleDeleteCompany();
        break;
    
    // Buses
    case 'get_buses':
        handleGetBuses();
        break;
    case 'create_bus':
        handleCreateBus();
        break;
    case 'update_bus':
        handleUpdateBus();
        break;
    case 'delete_bus':
        handleDeleteBus();
        break;
    
    // Routes
    case 'get_routes':
        handleGetRoutes();
        break;
    case 'create_route':
        handleCreateRoute();
        break;
    case 'update_route':
        handleUpdateRoute();
        break;
    case 'delete_route':
        handleDeleteRoute();
        break;
    
    // Schedules
    case 'get_schedules':
        handleGetSchedules();
        break;
    case 'create_schedule':
        handleCreateSchedule();
        break;
    case 'update_schedule':
        handleUpdateSchedule();
        break;
    case 'delete_schedule':
        handleDeleteSchedule();
        break;
    
    // Bookings
    case 'get_all_bookings':
        handleGetAllBookings();
        break;
    case 'update_booking_status':
        handleUpdateBookingStatus();
        break;
    
    // Users
    case 'get_users':
        handleGetUsers();
        break;
    case 'update_user':
        handleUpdateUser();
        break;
    case 'delete_user':
        handleDeleteUser();
        break;
    
    // Reports
    case 'get_revenue_report':
        handleGetRevenueReport();
        break;
    case 'get_booking_report':
        handleGetBookingReport();
        break;
    
    default:
        handleError('Invalid action', 400);
}

/**
 * Get dashboard statistics
 */
function handleGetDashboardStats() {
    requireAdmin();
    
    try {
        $stats = getAdminStats();
        
        // Get recent activities
        $db = getDB();
        $stmt = $db->prepare("
            SELECT 
                al.action,
                al.target_table,
                al.created_at,
                u.full_name as admin_name
            FROM admin_logs al
            JOIN users u ON al.admin_user_id = u.id
            ORDER BY al.created_at DESC
            LIMIT 10
        ");
        $stmt->execute();
        $recentActivities = $stmt->fetchAll();
        
        $stats['recent_activities'] = $recentActivities;
        
        handleSuccess($stats);
        
    } catch (Exception $e) {
        error_log("Get dashboard stats error: " . $e->getMessage());
        handleError('Failed to fetch dashboard statistics', 500);
    }
}

/**
 * Get all bus companies
 */
function handleGetCompanies() {
    requireAdmin();
    
    try {
        $db = getDB();
        
        $stmt = $db->prepare("
            SELECT 
                bc.*,
                COUNT(b.id) as total_buses,
                COUNT(CASE WHEN b.status = 'active' THEN 1 END) as active_buses
            FROM bus_companies bc
            LEFT JOIN buses b ON bc.id = b.company_id
            GROUP BY bc.id
            ORDER BY bc.name
        ");
        $stmt->execute();
        $companies = $stmt->fetchAll();
        
        handleSuccess($companies);
        
    } catch (Exception $e) {
        error_log("Get companies error: " . $e->getMessage());
        handleError('Failed to fetch companies', 500);
    }
}

/**
 * Create new bus company
 */
function handleCreateCompany() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        handleError('Method not allowed', 405);
    }
    
    requireAdmin();
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    $required = ['name', 'description'];
    foreach ($required as $field) {
        if (empty($input[$field])) {
            handleError("Field '$field' is required", 400);
        }
    }
    
    try {
        $db = getDB();
        
        $companyId = generateUniqueId('COMP');
        
        $stmt = $db->prepare("
            INSERT INTO bus_companies (company_id, name, description, contact_phone, contact_email, website) 
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $companyId,
            sanitizeInput($input['name']),
            sanitizeInput($input['description']),
            sanitizeInput($input['contact_phone'] ?? ''),
            sanitizeInput($input['contact_email'] ?? ''),
            sanitizeInput($input['website'] ?? '')
        ]);
        
        logAdminAction('create_company', 'bus_companies', $db->lastInsertId(), null, $input);
        
        handleSuccess(['company_id' => $companyId], 'Company created successfully');
        
    } catch (Exception $e) {
        error_log("Create company error: " . $e->getMessage());
        handleError('Failed to create company', 500);
    }
}

/**
 * Get all buses
 */
function handleGetBuses() {
    requireAdmin();
    
    $companyId = $_GET['company_id'] ?? '';
    $status = $_GET['status'] ?? '';
    
    try {
        $db = getDB();
        
        $whereClause = "WHERE 1=1";
        $params = [];
        
        if ($companyId) {
            $whereClause .= " AND bc.company_id = ?";
            $params[] = $companyId;
        }
        
        if ($status) {
            $whereClause .= " AND b.status = ?";
            $params[] = $status;
        }
        
        $stmt = $db->prepare("
            SELECT 
                b.*,
                bc.name as company_name,
                bc.company_id as company_code
            FROM buses b
            JOIN bus_companies bc ON b.company_id = bc.id
            $whereClause
            ORDER BY bc.name, b.bus_number
        ");
        $stmt->execute($params);
        $buses = $stmt->fetchAll();
        
        // Parse amenities JSON
        foreach ($buses as &$bus) {
            $bus['amenities'] = json_decode($bus['amenities'], true) ?: [];
        }
        
        handleSuccess($buses);
        
    } catch (Exception $e) {
        error_log("Get buses error: " . $e->getMessage());
        handleError('Failed to fetch buses', 500);
    }
}

/**
 * Create new bus
 */
function handleCreateBus() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        handleError('Method not allowed', 405);
    }
    
    requireAdmin();
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    $required = ['company_id', 'bus_number', 'bus_type', 'total_seats'];
    foreach ($required as $field) {
        if (empty($input[$field])) {
            handleError("Field '$field' is required", 400);
        }
    }
    
    try {
        $db = getDB();
        
        // Get company internal ID
        $stmt = $db->prepare("SELECT id FROM bus_companies WHERE company_id = ?");
        $stmt->execute([sanitizeInput($input['company_id'])]);
        $company = $stmt->fetch();
        
        if (!$company) {
            handleError('Company not found', 404);
        }
        
        $busId = generateUniqueId('BUS');
        $amenities = $input['amenities'] ?? [];
        
        $stmt = $db->prepare("
            INSERT INTO buses (
                bus_id, company_id, bus_number, bus_type, total_seats, 
                amenities, license_plate, model, year_manufactured
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $busId,
            $company['id'],
            sanitizeInput($input['bus_number']),
            sanitizeInput($input['bus_type']),
            (int)$input['total_seats'],
            json_encode($amenities),
            sanitizeInput($input['license_plate'] ?? ''),
            sanitizeInput($input['model'] ?? ''),
            (int)($input['year_manufactured'] ?? null)
        ]);
        
        logAdminAction('create_bus', 'buses', $db->lastInsertId(), null, $input);
        
        handleSuccess(['bus_id' => $busId], 'Bus created successfully');
        
    } catch (Exception $e) {
        error_log("Create bus error: " . $e->getMessage());
        handleError('Failed to create bus', 500);
    }
}

/**
 * Get all routes
 */
function handleGetRoutes() {
    requireAdmin();
    
    try {
        $db = getDB();
        
        $stmt = $db->prepare("
            SELECT 
                r.*,
                oc.name as origin_city,
                oc.city_code as origin_code,
                dc.name as destination_city,
                dc.city_code as destination_code,
                COUNT(s.id) as schedule_count
            FROM routes r
            JOIN cities oc ON r.origin_city_id = oc.id
            JOIN cities dc ON r.destination_city_id = dc.id
            LEFT JOIN schedules s ON r.id = s.route_id AND s.is_active = 1
            GROUP BY r.id
            ORDER BY oc.name, dc.name
        ");
        $stmt->execute();
        $routes = $stmt->fetchAll();
        
        handleSuccess($routes);
        
    } catch (Exception $e) {
        error_log("Get routes error: " . $e->getMessage());
        handleError('Failed to fetch routes', 500);
    }
}

/**
 * Create new route
 */
function handleCreateRoute() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        handleError('Method not allowed', 405);
    }
    
    requireAdmin();
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    $required = ['origin_city', 'destination_city', 'base_price'];
    foreach ($required as $field) {
        if (empty($input[$field])) {
            handleError("Field '$field' is required", 400);
        }
    }
    
    try {
        $db = getDB();
        
        // Get city IDs
        $stmt = $db->prepare("SELECT id FROM cities WHERE city_code = ?");
        $stmt->execute([sanitizeInput($input['origin_city'])]);
        $originCity = $stmt->fetch();
        
        $stmt->execute([sanitizeInput($input['destination_city'])]);
        $destinationCity = $stmt->fetch();
        
        if (!$originCity || !$destinationCity) {
            handleError('Invalid city codes', 400);
        }
        
        if ($originCity['id'] === $destinationCity['id']) {
            handleError('Origin and destination cannot be the same', 400);
        }
        
        // Check if route already exists
        $stmt = $db->prepare("
            SELECT id FROM routes 
            WHERE origin_city_id = ? AND destination_city_id = ?
        ");
        $stmt->execute([$originCity['id'], $destinationCity['id']]);
        if ($stmt->fetch()) {
            handleError('Route already exists', 409);
        }
        
        $routeId = generateUniqueId('RT');
        
        $stmt = $db->prepare("
            INSERT INTO routes (
                route_id, origin_city_id, destination_city_id, 
                distance_km, estimated_duration_hours, base_price
            ) VALUES (?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $routeId,
            $originCity['id'],
            $destinationCity['id'],
            (int)($input['distance_km'] ?? 0),
            (float)($input['estimated_duration_hours'] ?? 0),
            (float)$input['base_price']
        ]);
        
        logAdminAction('create_route', 'routes', $db->lastInsertId(), null, $input);
        
        handleSuccess(['route_id' => $routeId], 'Route created successfully');
        
    } catch (Exception $e) {
        error_log("Create route error: " . $e->getMessage());
        handleError('Failed to create route', 500);
    }
}

/**
 * Get all schedules
 */
function handleGetSchedules() {
    requireAdmin();
    
    $routeId = $_GET['route_id'] ?? '';
    $busId = $_GET['bus_id'] ?? '';
    
    try {
        $db = getDB();
        
        $whereClause = "WHERE 1=1";
        $params = [];
        
        if ($routeId) {
            $whereClause .= " AND r.route_id = ?";
            $params[] = $routeId;
        }
        
        if ($busId) {
            $whereClause .= " AND b.bus_id = ?";
            $params[] = $busId;
        }
        
        $stmt = $db->prepare("
            SELECT 
                s.*,
                r.route_id,
                oc.name as origin_city,
                dc.name as destination_city,
                b.bus_id,
                b.bus_number,
                bc.name as company_name
            FROM schedules s
            JOIN routes r ON s.route_id = r.id
            JOIN cities oc ON r.origin_city_id = oc.id
            JOIN cities dc ON r.destination_city_id = dc.id
            JOIN buses b ON s.bus_id = b.id
            JOIN bus_companies bc ON b.company_id = bc.id
            $whereClause
            ORDER BY s.departure_time
        ");
        $stmt->execute($params);
        $schedules = $stmt->fetchAll();
        
        // Parse days_of_week JSON
        foreach ($schedules as &$schedule) {
            $schedule['days_of_week'] = json_decode($schedule['days_of_week'], true) ?: [];
        }
        
        handleSuccess($schedules);
        
    } catch (Exception $e) {
        error_log("Get schedules error: " . $e->getMessage());
        handleError('Failed to fetch schedules', 500);
    }
}

/**
 * Create new schedule
 */
function handleCreateSchedule() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        handleError('Method not allowed', 405);
    }
    
    requireAdmin();
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    $required = ['bus_id', 'route_id', 'departure_time', 'arrival_time', 'days_of_week', 'price'];
    foreach ($required as $field) {
        if (empty($input[$field])) {
            handleError("Field '$field' is required", 400);
        }
    }
    
    try {
        $db = getDB();
        
        // Get bus and route internal IDs
        $stmt = $db->prepare("SELECT id FROM buses WHERE bus_id = ?");
        $stmt->execute([sanitizeInput($input['bus_id'])]);
        $bus = $stmt->fetch();
        
        $stmt = $db->prepare("SELECT id FROM routes WHERE route_id = ?");
        $stmt->execute([sanitizeInput($input['route_id'])]);
        $route = $stmt->fetch();
        
        if (!$bus || !$route) {
            handleError('Invalid bus or route ID', 400);
        }
        
        $scheduleId = generateUniqueId('SCH');
        $daysOfWeek = $input['days_of_week'];
        
        if (!is_array($daysOfWeek) || empty($daysOfWeek)) {
            handleError('Days of week must be a non-empty array', 400);
        }
        
        $stmt = $db->prepare("
            INSERT INTO schedules (
                schedule_id, bus_id, route_id, departure_time, arrival_time, 
                days_of_week, price, effective_from, effective_until
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $scheduleId,
            $bus['id'],
            $route['id'],
            sanitizeInput($input['departure_time']),
            sanitizeInput($input['arrival_time']),
            json_encode($daysOfWeek),
            (float)$input['price'],
            sanitizeInput($input['effective_from'] ?? date('Y-m-d')),
            sanitizeInput($input['effective_until'] ?? null)
        ]);
        
        logAdminAction('create_schedule', 'schedules', $db->lastInsertId(), null, $input);
        
        handleSuccess(['schedule_id' => $scheduleId], 'Schedule created successfully');
        
    } catch (Exception $e) {
        error_log("Create schedule error: " . $e->getMessage());
        handleError('Failed to create schedule', 500);
    }
}

/**
 * Get all bookings (admin view)
 */
function handleGetAllBookings() {
    requireAdmin();
    
    $status = $_GET['status'] ?? '';
    $limit = (int)($_GET['limit'] ?? 50);
    $offset = (int)($_GET['offset'] ?? 0);
    $search = $_GET['search'] ?? '';
    
    try {
        $db = getDB();
        
        $whereClause = "WHERE 1=1";
        $params = [];
        
        if ($status) {
            $whereClause .= " AND b.booking_status = ?";
            $params[] = $status;
        }
        
        if ($search) {
            $whereClause .= " AND (b.booking_id LIKE ? OR u.full_name LIKE ? OR u.email LIKE ?)";
            $searchTerm = "%$search%";
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }
        
        $stmt = $db->prepare("
            SELECT 
                b.*,
                u.full_name as passenger_name,
                u.email as passenger_email,
                s.departure_time,
                s.arrival_time,
                oc.name as origin_city,
                dc.name as destination_city,
                bus.bus_number,
                comp.name as company_name
            FROM bookings b
            JOIN users u ON b.user_id = u.id
            JOIN schedules s ON b.schedule_id = s.id
            JOIN routes r ON s.route_id = r.id
            JOIN cities oc ON r.origin_city_id = oc.id
            JOIN cities dc ON r.destination_city_id = dc.id
            JOIN buses bus ON s.bus_id = bus.id
            JOIN bus_companies comp ON bus.company_id = comp.id
            $whereClause
            ORDER BY b.created_at DESC
            LIMIT ? OFFSET ?
        ");
        
        $params[] = $limit;
        $params[] = $offset;
        $stmt->execute($params);
        $bookings = $stmt->fetchAll();
        
        // Format bookings
        $formattedBookings = [];
        foreach ($bookings as $booking) {
            $passengerDetails = json_decode($booking['passenger_details'], true);
            $selectedSeats = json_decode($booking['selected_seats'], true) ?: [];
            
            $formattedBookings[] = [
                'booking_id' => $booking['booking_id'],
                'passenger_name' => $booking['passenger_name'],
                'passenger_email' => $booking['passenger_email'],
                'status' => $booking['booking_status'],
                'payment_status' => $booking['payment_status'],
                'travel_date' => $booking['travel_date'],
                'passenger_count' => (int)$booking['passenger_count'],
                'selected_seats' => $selectedSeats,
                'total_amount' => (float)$booking['total_amount'],
                'booking_date' => $booking['booking_date'],
                'route' => $booking['origin_city'] . ' → ' . $booking['destination_city'],
                'departure_time' => $booking['departure_time'],
                'bus_number' => $booking['bus_number'],
                'company_name' => $booking['company_name'],
                'passenger_details' => $passengerDetails
            ];
        }
        
        // Get total count
        $countParams = array_slice($params, 0, -2); // Remove limit and offset
        $countStmt = $db->prepare("
            SELECT COUNT(*) as total 
            FROM bookings b
            JOIN users u ON b.user_id = u.id
            $whereClause
        ");
        $countStmt->execute($countParams);
        $total = $countStmt->fetch()['total'];
        
        handleSuccess([
            'bookings' => $formattedBookings,
            'pagination' => [
                'total' => (int)$total,
                'limit' => $limit,
                'offset' => $offset,
                'has_more' => ($offset + $limit) < $total
            ]
        ]);
        
    } catch (Exception $e) {
        error_log("Get all bookings error: " . $e->getMessage());
        handleError('Failed to fetch bookings', 500);
    }
}

/**
 * Update booking status
 */
function handleUpdateBookingStatus() {
    if ($_SERVER['REQUEST_METHOD'] !== 'PUT') {
        handleError('Method not allowed', 405);
    }
    
    requireAdmin();
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (empty($input['booking_id']) || empty($input['status'])) {
        handleError('Booking ID and status are required', 400);
    }
    
    $bookingId = sanitizeInput($input['booking_id']);
    $status = sanitizeInput($input['status']);
    $validStatuses = ['pending', 'confirmed', 'cancelled', 'completed'];
    
    if (!in_array($status, $validStatuses)) {
        handleError('Invalid booking status', 400);
    }
    
    try {
        $db = getDB();
        
        // Get current booking
        $stmt = $db->prepare("SELECT * FROM bookings WHERE booking_id = ?");
        $stmt->execute([$bookingId]);
        $booking = $stmt->fetch();
        
        if (!$booking) {
            handleError('Booking not found', 404);
        }
        
        $oldStatus = $booking['booking_status'];
        
        // Update booking status
        $stmt = $db->prepare("UPDATE bookings SET booking_status = ? WHERE booking_id = ?");
        $stmt->execute([$status, $bookingId]);
        
        logAdminAction('update_booking_status', 'bookings', $booking['id'], 
                      ['status' => $oldStatus], ['status' => $status]);
        
        handleSuccess(['booking_id' => $bookingId, 'status' => $status], 
                     'Booking status updated successfully');
        
    } catch (Exception $e) {
        error_log("Update booking status error: " . $e->getMessage());
        handleError('Failed to update booking status', 500);
    }
}

/**
 * Get revenue report
 */
function handleGetRevenueReport() {
    requireAdmin();
    
    $startDate = $_GET['start_date'] ?? date('Y-m-01'); // First day of current month
    $endDate = $_GET['end_date'] ?? date('Y-m-d'); // Today
    
    try {
        $db = getDB();
        
        // Daily revenue
        $stmt = $db->prepare("
            SELECT 
                DATE(b.created_at) as date,
                COUNT(*) as bookings,
                SUM(b.total_amount) as revenue
            FROM bookings b
            WHERE b.payment_status = 'paid'
            AND DATE(b.created_at) BETWEEN ? AND ?
            GROUP BY DATE(b.created_at)
            ORDER BY date
        ");
        $stmt->execute([$startDate, $endDate]);
        $dailyRevenue = $stmt->fetchAll();
        
        // Revenue by company
        $stmt = $db->prepare("
            SELECT 
                comp.name as company_name,
                COUNT(*) as bookings,
                SUM(b.total_amount) as revenue
            FROM bookings b
            JOIN schedules s ON b.schedule_id = s.id
            JOIN buses bus ON s.bus_id = bus.id
            JOIN bus_companies comp ON bus.company_id = comp.id
            WHERE b.payment_status = 'paid'
            AND DATE(b.created_at) BETWEEN ? AND ?
            GROUP BY comp.id
            ORDER BY revenue DESC
        ");
        $stmt->execute([$startDate, $endDate]);
        $revenueByCompany = $stmt->fetchAll();
        
        // Total summary
        $stmt = $db->prepare("
            SELECT 
                COUNT(*) as total_bookings,
                SUM(b.total_amount) as total_revenue,
                AVG(b.total_amount) as avg_booking_value
            FROM bookings b
            WHERE b.payment_status = 'paid'
            AND DATE(b.created_at) BETWEEN ? AND ?
        ");
        $stmt->execute([$startDate, $endDate]);
        $summary = $stmt->fetch();
        
        handleSuccess([
            'summary' => $summary,
            'daily_revenue' => $dailyRevenue,
            'revenue_by_company' => $revenueByCompany,
            'period' => [
                'start_date' => $startDate,
                'end_date' => $endDate
            ]
        ]);
        
    } catch (Exception $e) {
        error_log("Get revenue report error: " . $e->getMessage());
        handleError('Failed to generate revenue report', 500);
    }
}

/**
 * Get booking report
 */
function handleGetBookingReport() {
    requireAdmin();
    
    $startDate = $_GET['start_date'] ?? date('Y-m-01');
    $endDate = $_GET['end_date'] ?? date('Y-m-d');
    
    try {
        $db = getDB();
        
        // Booking status distribution
        $stmt = $db->prepare("
            SELECT 
                booking_status,
                COUNT(*) as count
            FROM bookings
            WHERE DATE(created_at) BETWEEN ? AND ?
            GROUP BY booking_status
        ");
        $stmt->execute([$startDate, $endDate]);
        $statusDistribution = $stmt->fetchAll();
        
        // Popular routes
        $stmt = $db->prepare("
            SELECT 
                CONCAT(oc.name, ' → ', dc.name) as route,
                COUNT(*) as bookings
            FROM bookings b
            JOIN schedules s ON b.schedule_id = s.id
            JOIN routes r ON s.route_id = r.id
            JOIN cities oc ON r.origin_city_id = oc.id
            JOIN cities dc ON r.destination_city_id = dc.id
            WHERE DATE(b.created_at) BETWEEN ? AND ?
            GROUP BY r.id
            ORDER BY bookings DESC
            LIMIT 10
        ");
        $stmt->execute([$startDate, $endDate]);
        $popularRoutes = $stmt->fetchAll();
        
        handleSuccess([
            'status_distribution' => $statusDistribution,
            'popular_routes' => $popularRoutes,
            'period' => [
                'start_date' => $startDate,
                'end_date' => $endDate
            ]
        ]);
        
    } catch (Exception $e) {
        error_log("Get booking report error: " . $e->getMessage());
        handleError('Failed to generate booking report', 500);
    }
}

/**
 * Update bus company
 */
function handleUpdateCompany() {
    if ($_SERVER['REQUEST_METHOD'] !== 'PUT') {
        handleError('Method not allowed', 405);
    }
    
    requireAdmin();
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (empty($input['company_id'])) {
        handleError('Company ID is required', 400);
    }
    
    try {
        $db = getDB();
        
        // Get current company data
        $stmt = $db->prepare("SELECT * FROM bus_companies WHERE company_id = ?");
        $stmt->execute([sanitizeInput($input['company_id'])]);
        $oldCompany = $stmt->fetch();
        
        if (!$oldCompany) {
            handleError('Company not found', 404);
        }
        
        // Build update query
        $updates = [];
        $params = [];
        
        $allowedFields = ['name', 'description', 'contact_phone', 'contact_email', 'website', 'is_active'];
        foreach ($allowedFields as $field) {
            if (isset($input[$field])) {
                $updates[] = "$field = ?";
                $params[] = sanitizeInput($input[$field]);
            }
        }
        
        if (empty($updates)) {
            handleError('No valid fields to update', 400);
        }
        
        $sql = "UPDATE bus_companies SET " . implode(', ', $updates) . " WHERE id = ?";
        $params[] = $oldCompany['id'];
        
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        
        logAdminAction('update_company', 'bus_companies', $oldCompany['id'], $oldCompany, $input);
        
        handleSuccess(['company_id' => $input['company_id']], 'Company updated successfully');
        
    } catch (Exception $e) {
        error_log("Update company error: " . $e->getMessage());
        handleError('Failed to update company', 500);
    }
}

/**
 * Delete bus company
 */
function handleDeleteCompany() {
    if ($_SERVER['REQUEST_METHOD'] !== 'DELETE') {
        handleError('Method not allowed', 405);
    }
    
    requireAdmin();
    
    if (empty($_GET['company_id'])) {
        handleError('Company ID is required', 400);
    }
    
    $companyId = sanitizeInput($_GET['company_id']);
    
    try {
        $db = getDB();
        
        // Get company data before deletion
        $stmt = $db->prepare("SELECT * FROM bus_companies WHERE company_id = ?");
        $stmt->execute([$companyId]);
        $company = $stmt->fetch();
        
        if (!$company) {
            handleError('Company not found', 404);
        }
        
        // Check if company has buses
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM buses WHERE company_id = ?");
        $stmt->execute([$company['id']]);
        $busCount = $stmt->fetch()['count'];
        
        if ($busCount > 0) {
            handleError('Cannot delete company with existing buses', 400);
        }
        
        // Delete company
        $stmt = $db->prepare("DELETE FROM bus_companies WHERE id = ?");
        $stmt->execute([$company['id']]);
        
        logAdminAction('delete_company', 'bus_companies', $company['id'], $company, null);
        
        handleSuccess(['company_id' => $companyId], 'Company deleted successfully');
        
    } catch (Exception $e) {
        error_log("Delete company error: " . $e->getMessage());
        handleError('Failed to delete company', 500);
    }
}

/**
 * Update bus
 */
function handleUpdateBus() {
    if ($_SERVER['REQUEST_METHOD'] !== 'PUT') {
        handleError('Method not allowed', 405);
    }
    
    requireAdmin();
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (empty($input['bus_id'])) {
        handleError('Bus ID is required', 400);
    }
    
    try {
        $db = getDB();
        
        // Get current bus data
        $stmt = $db->prepare("SELECT * FROM buses WHERE bus_id = ?");
        $stmt->execute([sanitizeInput($input['bus_id'])]);
        $oldBus = $stmt->fetch();
        
        if (!$oldBus) {
            handleError('Bus not found', 404);
        }
        
        // Build update query
        $updates = [];
        $params = [];
        
        $allowedFields = ['bus_number', 'bus_type', 'total_seats', 'status', 'license_plate', 'model', 'year_manufactured'];
        foreach ($allowedFields as $field) {
            if (isset($input[$field])) {
                if ($field === 'amenities') {
                    $updates[] = "$field = ?";
                    $params[] = json_encode($input[$field]);
                } else {
                    $updates[] = "$field = ?";
                    $params[] = sanitizeInput($input[$field]);
                }
            }
        }
        
        if (isset($input['amenities'])) {
            $updates[] = "amenities = ?";
            $params[] = json_encode($input['amenities']);
        }
        
        if (empty($updates)) {
            handleError('No valid fields to update', 400);
        }
        
        $sql = "UPDATE buses SET " . implode(', ', $updates) . " WHERE id = ?";
        $params[] = $oldBus['id'];
        
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        
        logAdminAction('update_bus', 'buses', $oldBus['id'], $oldBus, $input);
        
        handleSuccess(['bus_id' => $input['bus_id']], 'Bus updated successfully');
        
    } catch (Exception $e) {
        error_log("Update bus error: " . $e->getMessage());
        handleError('Failed to update bus', 500);
    }
}

/**
 * Delete bus
 */
function handleDeleteBus() {
    if ($_SERVER['REQUEST_METHOD'] !== 'DELETE') {
        handleError('Method not allowed', 405);
    }
    
    requireAdmin();
    
    if (empty($_GET['bus_id'])) {
        handleError('Bus ID is required', 400);
    }
    
    $busId = sanitizeInput($_GET['bus_id']);
    
    try {
        $db = getDB();
        
        // Get bus data before deletion
        $stmt = $db->prepare("SELECT * FROM buses WHERE bus_id = ?");
        $stmt->execute([$busId]);
        $bus = $stmt->fetch();
        
        if (!$bus) {
            handleError('Bus not found', 404);
        }
        
        // Check if bus has schedules
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM schedules WHERE bus_id = ?");
        $stmt->execute([$bus['id']]);
        $scheduleCount = $stmt->fetch()['count'];
        
        if ($scheduleCount > 0) {
            handleError('Cannot delete bus with existing schedules', 400);
        }
        
        // Delete bus
        $stmt = $db->prepare("DELETE FROM buses WHERE id = ?");
        $stmt->execute([$bus['id']]);
        
        logAdminAction('delete_bus', 'buses', $bus['id'], $bus, null);
        
        handleSuccess(['bus_id' => $busId], 'Bus deleted successfully');
        
    } catch (Exception $e) {
        error_log("Delete bus error: " . $e->getMessage());
        handleError('Failed to delete bus', 500);
    }
}

/**
 * Update route
 */
function handleUpdateRoute() {
    if ($_SERVER['REQUEST_METHOD'] !== 'PUT') {
        handleError('Method not allowed', 405);
    }
    
    requireAdmin();
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (empty($input['route_id'])) {
        handleError('Route ID is required', 400);
    }
    
    try {
        $db = getDB();
        
        // Get current route data
        $stmt = $db->prepare("SELECT * FROM routes WHERE route_id = ?");
        $stmt->execute([sanitizeInput($input['route_id'])]);
        $oldRoute = $stmt->fetch();
        
        if (!$oldRoute) {
            handleError('Route not found', 404);
        }
        
        // Build update query
        $updates = [];
        $params = [];
        
        $allowedFields = ['distance_km', 'estimated_duration_hours', 'base_price', 'is_active'];
        foreach ($allowedFields as $field) {
            if (isset($input[$field])) {
                $updates[] = "$field = ?";
                $params[] = sanitizeInput($input[$field]);
            }
        }
        
        if (empty($updates)) {
            handleError('No valid fields to update', 400);
        }
        
        $sql = "UPDATE routes SET " . implode(', ', $updates) . " WHERE id = ?";
        $params[] = $oldRoute['id'];
        
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        
        logAdminAction('update_route', 'routes', $oldRoute['id'], $oldRoute, $input);
        
        handleSuccess(['route_id' => $input['route_id']], 'Route updated successfully');
        
    } catch (Exception $e) {
        error_log("Update route error: " . $e->getMessage());
        handleError('Failed to update route', 500);
    }
}

/**
 * Delete route
 */
function handleDeleteRoute() {
    if ($_SERVER['REQUEST_METHOD'] !== 'DELETE') {
        handleError('Method not allowed', 405);
    }
    
    requireAdmin();
    
    if (empty($_GET['route_id'])) {
        handleError('Route ID is required', 400);
    }
    
    $routeId = sanitizeInput($_GET['route_id']);
    
    try {
        $db = getDB();
        
        // Get route data before deletion
        $stmt = $db->prepare("SELECT * FROM routes WHERE route_id = ?");
        $stmt->execute([$routeId]);
        $route = $stmt->fetch();
        
        if (!$route) {
            handleError('Route not found', 404);
        }
        
        // Check if route has schedules
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM schedules WHERE route_id = ?");
        $stmt->execute([$route['id']]);
        $scheduleCount = $stmt->fetch()['count'];
        
        if ($scheduleCount > 0) {
            handleError('Cannot delete route with existing schedules', 400);
        }
        
        // Delete route
        $stmt = $db->prepare("DELETE FROM routes WHERE id = ?");
        $stmt->execute([$route['id']]);
        
        logAdminAction('delete_route', 'routes', $route['id'], $route, null);
        
        handleSuccess(['route_id' => $routeId], 'Route deleted successfully');
        
    } catch (Exception $e) {
        error_log("Delete route error: " . $e->getMessage());
        handleError('Failed to delete route', 500);
    }
}

/**
 * Update schedule
 */
function handleUpdateSchedule() {
    if ($_SERVER['REQUEST_METHOD'] !== 'PUT') {
        handleError('Method not allowed', 405);
    }
    
    requireAdmin();
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (empty($input['schedule_id'])) {
        handleError('Schedule ID is required', 400);
    }
    
    try {
        $db = getDB();
        
        // Get current schedule data
        $stmt = $db->prepare("SELECT * FROM schedules WHERE schedule_id = ?");
        $stmt->execute([sanitizeInput($input['schedule_id'])]);
        $oldSchedule = $stmt->fetch();
        
        if (!$oldSchedule) {
            handleError('Schedule not found', 404);
        }
        
        // Build update query
        $updates = [];
        $params = [];
        
        $allowedFields = ['departure_time', 'arrival_time', 'price', 'is_active', 'effective_from', 'effective_until'];
        foreach ($allowedFields as $field) {
            if (isset($input[$field])) {
                $updates[] = "$field = ?";
                $params[] = sanitizeInput($input[$field]);
            }
        }
        
        if (isset($input['days_of_week'])) {
            $updates[] = "days_of_week = ?";
            $params[] = json_encode($input['days_of_week']);
        }
        
        if (empty($updates)) {
            handleError('No valid fields to update', 400);
        }
        
        $sql = "UPDATE schedules SET " . implode(', ', $updates) . " WHERE id = ?";
        $params[] = $oldSchedule['id'];
        
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        
        logAdminAction('update_schedule', 'schedules', $oldSchedule['id'], $oldSchedule, $input);
        
        handleSuccess(['schedule_id' => $input['schedule_id']], 'Schedule updated successfully');
        
    } catch (Exception $e) {
        error_log("Update schedule error: " . $e->getMessage());
        handleError('Failed to update schedule', 500);
    }
}

/**
 * Delete schedule
 */
function handleDeleteSchedule() {
    if ($_SERVER['REQUEST_METHOD'] !== 'DELETE') {
        handleError('Method not allowed', 405);
    }
    
    requireAdmin();
    
    if (empty($_GET['schedule_id'])) {
        handleError('Schedule ID is required', 400);
    }
    
    $scheduleId = sanitizeInput($_GET['schedule_id']);
    
    try {
        $db = getDB();
        
        // Get schedule data before deletion
        $stmt = $db->prepare("SELECT * FROM schedules WHERE schedule_id = ?");
        $stmt->execute([$scheduleId]);
        $schedule = $stmt->fetch();
        
        if (!$schedule) {
            handleError('Schedule not found', 404);
        }
        
        // Check if schedule has bookings
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM bookings WHERE schedule_id = ?");
        $stmt->execute([$schedule['id']]);
        $bookingCount = $stmt->fetch()['count'];
        
        if ($bookingCount > 0) {
            handleError('Cannot delete schedule with existing bookings', 400);
        }
        
        // Delete schedule
        $stmt = $db->prepare("DELETE FROM schedules WHERE id = ?");
        $stmt->execute([$schedule['id']]);
        
        logAdminAction('delete_schedule', 'schedules', $schedule['id'], $schedule, null);
        
        handleSuccess(['schedule_id' => $scheduleId], 'Schedule deleted successfully');
        
    } catch (Exception $e) {
        error_log("Delete schedule error: " . $e->getMessage());
        handleError('Failed to delete schedule', 500);
    }
}

/**
 * Get all users
 */
function handleGetUsers() {
    requireAdmin();
    
    $role = $_GET['role'] ?? '';
    $limit = (int)($_GET['limit'] ?? 50);
    $offset = (int)($_GET['offset'] ?? 0);
    $search = $_GET['search'] ?? '';
    
    try {
        $db = getDB();
        
        $whereClause = "WHERE 1=1";
        $params = [];
        
        if ($role) {
            $whereClause .= " AND role = ?";
            $params[] = $role;
        }
        
        if ($search) {
            $whereClause .= " AND (full_name LIKE ? OR email LIKE ?)";
            $searchTerm = "%$search%";
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }
        
        $stmt = $db->prepare("
            SELECT 
                user_id, email, full_name, first_name, last_name, 
                phone, role, is_verified, is_active, joined_date, created_at
            FROM users 
            $whereClause
            ORDER BY created_at DESC
            LIMIT ? OFFSET ?
        ");
        
        $params[] = $limit;
        $params[] = $offset;
        $stmt->execute($params);
        $users = $stmt->fetchAll();
        
        // Get total count
        $countParams = array_slice($params, 0, -2); // Remove limit and offset
        $countStmt = $db->prepare("
            SELECT COUNT(*) as total 
            FROM users 
            $whereClause
        ");
        $countStmt->execute($countParams);
        $total = $countStmt->fetch()['total'];
        
        handleSuccess([
            'users' => $users,
            'pagination' => [
                'total' => (int)$total,
                'limit' => $limit,
                'offset' => $offset,
                'has_more' => ($offset + $limit) < $total
            ]
        ]);
        
    } catch (Exception $e) {
        error_log("Get users error: " . $e->getMessage());
        handleError('Failed to fetch users', 500);
    }
}

/**
 * Update user
 */
function handleUpdateUser() {
    if ($_SERVER['REQUEST_METHOD'] !== 'PUT') {
        handleError('Method not allowed', 405);
    }
    
    requireAdmin();
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (empty($input['user_id'])) {
        handleError('User ID is required', 400);
    }
    
    try {
        $db = getDB();
        
        // Get current user data
        $stmt = $db->prepare("SELECT * FROM users WHERE user_id = ?");
        $stmt->execute([sanitizeInput($input['user_id'])]);
        $oldUser = $stmt->fetch();
        
        if (!$oldUser) {
            handleError('User not found', 404);
        }
        
        // Build update query
        $updates = [];
        $params = [];
        
        $allowedFields = ['first_name', 'last_name', 'phone', 'role', 'is_verified', 'is_active'];
        foreach ($allowedFields as $field) {
            if (isset($input[$field])) {
                $updates[] = "$field = ?";
                $params[] = sanitizeInput($input[$field]);
            }
        }
        
        // Update full_name if first_name or last_name changed
        if (isset($input['first_name']) || isset($input['last_name'])) {
            $firstName = $input['first_name'] ?? $oldUser['first_name'];
            $lastName = $input['last_name'] ?? $oldUser['last_name'];
            $updates[] = "full_name = ?";
            $params[] = $firstName . ' ' . $lastName;
        }
        
        if (empty($updates)) {
            handleError('No valid fields to update', 400);
        }
        
        $sql = "UPDATE users SET " . implode(', ', $updates) . " WHERE id = ?";
        $params[] = $oldUser['id'];
        
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        
        logAdminAction('update_user', 'users', $oldUser['id'], $oldUser, $input);
        
        handleSuccess(['user_id' => $input['user_id']], 'User updated successfully');
        
    } catch (Exception $e) {
        error_log("Update user error: " . $e->getMessage());
        handleError('Failed to update user', 500);
    }
}

/**
 * Delete user
 */
function handleDeleteUser() {
    if ($_SERVER['REQUEST_METHOD'] !== 'DELETE') {
        handleError('Method not allowed', 405);
    }
    
    requireAdmin();
    
    if (empty($_GET['user_id'])) {
        handleError('User ID is required', 400);
    }
    
    $userId = sanitizeInput($_GET['user_id']);
    
    try {
        $db = getDB();
        
        // Get user data before deletion
        $stmt = $db->prepare("SELECT * FROM users WHERE user_id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch();
        
        if (!$user) {
            handleError('User not found', 404);
        }
        
        // Prevent deleting admin users
        if ($user['role'] === 'admin') {
            handleError('Cannot delete admin users', 400);
        }
        
        // Check if user has bookings
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM bookings WHERE user_id = ?");
        $stmt->execute([$user['id']]);
        $bookingCount = $stmt->fetch()['count'];
        
        if ($bookingCount > 0) {
            // Instead of deleting, deactivate the user
            $stmt = $db->prepare("UPDATE users SET is_active = 0 WHERE id = ?");
            $stmt->execute([$user['id']]);
            
            logAdminAction('deactivate_user', 'users', $user['id'], $user, ['is_active' => 0]);
            
            handleSuccess(['user_id' => $userId], 'User deactivated successfully (has existing bookings)');
        } else {
            // Delete user
            $stmt = $db->prepare("DELETE FROM users WHERE id = ?");
            $stmt->execute([$user['id']]);
            
            logAdminAction('delete_user', 'users', $user['id'], $user, null);
            
            handleSuccess(['user_id' => $userId], 'User deleted successfully');
        }
        
    } catch (Exception $e) {
        error_log("Delete user error: " . $e->getMessage());
        handleError('Failed to delete user', 500);
    }
}
?>