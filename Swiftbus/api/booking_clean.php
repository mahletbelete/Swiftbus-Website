<?php
/**
 * SwiftBus Booking API - Clean Version
 * 
 * Handles ticket booking, seat selection, and booking management
 * Updated to work with cleaned database structure (no simple_bookings table)
 */

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 0); // Don't display errors in output
ini_set('log_errors', 1); // Log errors to file

// Set JSON header first
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    exit(0);
}

// Catch any fatal errors and return JSON
register_shutdown_function(function() {
    $error = error_get_last();
    if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Server error: ' . $error['message'],
            'error_details' => $error
        ]);
    }
});

// Set error handler to catch all errors
set_error_handler(function($severity, $message, $file, $line) {
    // Log the error
    error_log("PHP Error: $message in $file on line $line");
    
    // For fatal errors, return JSON response
    if ($severity === E_ERROR || $severity === E_PARSE) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'PHP Error: ' . $message,
            'file' => basename($file),
            'line' => $line
        ]);
        exit;
    }
});

try {
    require_once '../includes/functions.php';
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Failed to load required functions: ' . $e->getMessage(),
        'error_type' => 'include_error'
    ]);
    exit;
}

$action = $_GET['action'] ?? $_POST['action'] ?? '';

// Add a simple test action
if ($action === 'test') {
    handleSuccess(['message' => 'API is working correctly', 'timestamp' => date('Y-m-d H:i:s')], 'Test successful');
}

switch ($action) {
    case 'create_booking':
        handleCreateBooking();
        break;
    case 'create_simple_booking':
        handleCreateBooking(); // Redirect to unified booking creation
        break;
    case 'get_user_bookings':
        handleGetUserBookings();
        break;
    case 'get_booking_details':
        handleGetBookingDetails();
        break;
    case 'cancel_booking':
        handleCancelBooking();
        break;
    case 'update_booking':
        handleUpdateBooking();
        break;
    case 'get_booking_stats':
        handleGetBookingStats();
        break;
    case 'get_available_seats':
        handleGetAvailableSeats();
        break;
    case 'reserve_seats':
        handleReserveSeats();
        break;
    case 'release_seats':
        handleReleaseSeats();
        break;
    default:
        handleError('Invalid action', 400);
}

/**
 * Create a new booking (unified function for all booking types)
 */
function handleCreateBooking() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        handleError('Method not allowed', 405);
    }
    
    requireLogin();
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    // Validate required fields for unified booking
    $required = ['from_city', 'to_city', 'departure_date', 'departure_time', 'passenger_details', 'total_amount'];
    foreach ($required as $field) {
        if (empty($input[$field])) {
            handleError("Field '$field' is required", 400);
        }
    }
    
    $fromCity = sanitizeInput($input['from_city']);
    $toCity = sanitizeInput($input['to_city']);
    $departureDate = sanitizeInput($input['departure_date']);
    $departureTime = sanitizeInput($input['departure_time']);
    $busCompany = sanitizeInput($input['bus_company'] ?? 'selam-bus');
    $busType = sanitizeInput($input['bus_type'] ?? 'premium-ac');
    $passengerCount = (int)($input['passenger_count'] ?? 1);
    $selectedSeats = $input['selected_seats'] ?? [];
    $passengerDetails = $input['passenger_details'];
    $totalAmount = (float)$input['total_amount'];
    $paymentMethod = sanitizeInput($input['payment_method'] ?? '');
    $specialRequirements = sanitizeInput($input['special_requirements'] ?? '');
    
    // Validate travel date
    if (!strtotime($departureDate) || $departureDate < date('Y-m-d')) {
        handleError('Invalid or past travel date', 400);
    }
    
    // Validate passenger count
    if ($passengerCount < 1 || $passengerCount > 6) {
        handleError('Passenger count must be between 1 and 6', 400);
    }
    
    // Validate amount
    if ($totalAmount <= 0) {
        handleError('Invalid total amount', 400);
    }
    
    // Validate selected seats
    if (!empty($selectedSeats) && count($selectedSeats) !== $passengerCount) {
        handleError('Number of selected seats must match passenger count', 400);
    }
    
    try {
        $db = getDB();
        $db->beginTransaction();
        
        // Generate booking ID
        $bookingId = generateUniqueId('SWB');
        
        // Get user
        $user = getCurrentUser();
        
        // Generate QR code data
        $qrData = generateQRData(
            $bookingId,
            $passengerDetails['fullName'] ?? $user['full_name'],
            $fromCity . '-' . $toCity,
            $departureDate,
            $selectedSeats
        );
        
        // Insert unified booking
        $stmt = $db->prepare("
            INSERT INTO bookings (
                booking_id, user_id, bus_company, bus_type, from_city, to_city, 
                travel_date, departure_time, passenger_count, selected_seats, 
                passenger_details, total_amount, payment_method, special_requirements,
                qr_code, booking_status, payment_status, booking_date
            ) VALUES (
                ?, (SELECT id FROM users WHERE user_id = ?), ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', 'pending', CURRENT_TIMESTAMP
            )
        ");
        
        $stmt->execute([
            $bookingId,
            $_SESSION['user_id'],
            $busCompany,
            $busType,
            $fromCity,
            $toCity,
            $departureDate,
            $departureTime,
            $passengerCount,
            json_encode($selectedSeats),
            json_encode($passengerDetails),
            $totalAmount,
            $paymentMethod,
            $specialRequirements,
            $qrData
        ]);
        
        // Reserve seats if selected
        if (!empty($selectedSeats)) {
            $route = $fromCity . '-' . $toCity;
            reserveSeatsForBooking($bookingId, $busCompany, $busType, $route, $departureDate, $departureTime, $selectedSeats);
        }
        
        // Create initial payment record
        createPaymentRecord($bookingId, $totalAmount, $paymentMethod, $passengerDetails);
        
        // Log activity
        logActivity($_SESSION['user_id'], 'booking_created', 'booking', $bookingId, [
            'from_city' => $fromCity,
            'to_city' => $toCity,
            'departure_date' => $departureDate,
            'total_amount' => $totalAmount
        ]);
        
        $db->commit();
        
        // Prepare response
        $booking = [
            'booking_id' => $bookingId,
            'from_city' => $fromCity,
            'to_city' => $toCity,
            'departure_date' => $departureDate,
            'departure_time' => $departureTime,
            'bus_company' => $busCompany,
            'bus_type' => $busType,
            'passenger_count' => $passengerCount,
            'selected_seats' => $selectedSeats,
            'total_amount' => $totalAmount,
            'status' => 'pending',
            'payment_status' => 'pending',
            'qr_code' => $qrData
        ];
        
        handleSuccess($booking, 'Booking created successfully');
        
    } catch (Exception $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        error_log("Create booking error: " . $e->getMessage());
        handleError('Booking creation failed. Please try again.', 500);
    }
}

/**
 * Enhanced get user bookings with better error handling
 */
function handleGetUserBookings() {
    // Enhanced authentication check
    if (!isLoggedIn()) {
        error_log("getUserBookings failed - user not logged in. Session: " . print_r($_SESSION, true));
        http_response_code(401);
        echo json_encode([
            'success' => false, 
            'message' => 'Authentication required. Please log in to view your tickets.',
            'error_code' => 'AUTH_REQUIRED',
            'redirect' => 'login.html',
            'debug' => [
                'session_exists' => session_id() ? true : false,
                'user_id_in_session' => isset($_SESSION['user_id'])
            ]
        ]);
        exit;
    }
    
    $userId = getCurrentUserId();
    if (!$userId) {
        error_log("getUserBookings failed - no user ID in session");
        http_response_code(401);
        echo json_encode([
            'success' => false, 
            'message' => 'Invalid session. Please log in again.',
            'error_code' => 'INVALID_SESSION',
            'redirect' => 'login.html'
        ]);
        exit;
    }
    
    $status = $_GET['status'] ?? '';
    $limit = max(1, min(100, (int)($_GET['limit'] ?? 50))); // Ensure integer, min 1, max 100
    $offset = max(0, (int)($_GET['offset'] ?? 0)); // Ensure integer, min 0
    
    try {
        $db = getDB();
        
        // First, verify the user exists
        $userCheck = $db->prepare("SELECT id FROM users WHERE user_id = ? AND is_active = 1");
        $userCheck->execute([$userId]);
        $userRecord = $userCheck->fetch();
        
        if (!$userRecord) {
            error_log("getUserBookings failed - user not found in database: " . $userId);
            http_response_code(401);
            echo json_encode([
                'success' => false, 
                'message' => 'User account not found. Please log in again.',
                'error_code' => 'USER_NOT_FOUND',
                'redirect' => 'login.html'
            ]);
            exit;
        }
        
        error_log("getUserBookings - Loading tickets for user: " . $userId . " (DB ID: " . $userRecord['id'] . ")");
        
        // Build WHERE clause
        $whereClause = "WHERE b.user_id = (SELECT id FROM users WHERE user_id = ?)";
        $params = [$userId];
        
        if ($status && $status !== 'all') {
            $whereClause .= " AND b.booking_status = ?";
            $params[] = $status;
        }
        
        // Get bookings from unified bookings table
        $stmt = $db->prepare("
            SELECT 
                b.booking_id,
                b.bus_company,
                b.bus_type,
                b.from_city,
                b.to_city,
                b.travel_date as departure_date,
                b.departure_time,
                b.passenger_count,
                b.selected_seats,
                b.passenger_details,
                b.total_amount,
                b.booking_status,
                b.payment_status,
                b.payment_method,
                b.qr_code,
                b.booking_date,
                b.created_at
            FROM bookings b
            $whereClause
            ORDER BY b.created_at DESC
            LIMIT $limit OFFSET $offset
        ");
        
        // Execute with only the WHERE clause parameters (not limit/offset)
        $stmt->execute($params);
        $bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        error_log("getUserBookings - Found " . count($bookings) . " bookings for user: " . $userId);
        
        // Process bookings data
        foreach ($bookings as &$booking) {
            // Decode JSON fields
            if ($booking['selected_seats']) {
                $booking['selected_seats'] = json_decode($booking['selected_seats'], true);
            }
            if ($booking['passenger_details']) {
                $booking['passenger_details'] = json_decode($booking['passenger_details'], true);
            }
            
            // Format dates
            $booking['departure_date'] = date('Y-m-d', strtotime($booking['departure_date']));
            $booking['booking_date'] = date('Y-m-d H:i:s', strtotime($booking['booking_date']));
            
            // Add display names (using helper functions instead of database lookup)
            $booking['from_city_display'] = getCityDisplayName($booking['from_city']);
            $booking['to_city_display'] = getCityDisplayName($booking['to_city']);
            $booking['bus_company_display'] = getBusCompanyDisplayName($booking['bus_company']);
            $booking['bus_type_display'] = getBusTypeDisplayName($booking['bus_type']);
            $booking['route'] = $booking['from_city_display'] . ' to ' . $booking['to_city_display'];
            
            // Set default company info (since we removed the LEFT JOIN)
            $booking['company_name'] = $booking['bus_company_display'];
            $booking['company_rating'] = 4.5; // Default rating
            
            // Generate QR code if not exists
            if (empty($booking['qr_code'])) {
                $booking['qr_code'] = generateQRData(
                    $booking['booking_id'],
                    $booking['passenger_details']['fullName'] ?? 'Passenger',
                    $booking['from_city'] . '-' . $booking['to_city'],
                    $booking['departure_date'],
                    $booking['selected_seats'] ?? []
                );
            }
        }
        
        // Get total count for pagination
        $countStmt = $db->prepare("
            SELECT COUNT(*) as total 
            FROM bookings b 
            $whereClause
        ");
        $countStmt->execute($params); // Use the same params (without limit/offset)
        $total = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];
        
        error_log("getUserBookings - Returning " . count($bookings) . " bookings, total: " . $total);
        
        handleSuccess([
            'bookings' => $bookings,
            'total' => (int)$total,
            'limit' => $limit,
            'offset' => $offset,
            'has_more' => ($offset + $limit) < $total,
            'user_id' => $userId,
            'debug_info' => [
                'query_executed' => true,
                'bookings_processed' => count($bookings),
                'user_verified' => true
            ]
        ]);
        
    } catch (Exception $e) {
        error_log("Get user bookings error: " . $e->getMessage());
        error_log("Get user bookings stack trace: " . $e->getTraceAsString());
        handleError('Failed to retrieve bookings: ' . $e->getMessage(), 500, [
            'error_details' => $e->getMessage(),
            'user_id' => $userId ?? 'unknown'
        ]);
    }
}

/**
 * Get booking details
 */
function handleGetBookingDetails() {
    requireLogin();
    
    if (empty($_GET['booking_id'])) {
        handleError('Booking ID is required', 400);
    }
    
    $bookingId = sanitizeInput($_GET['booking_id']);
    
    try {
        $db = getDB();
        
        $stmt = $db->prepare("
            SELECT 
                b.*,
                bc.name as company_name,
                bc.rating as company_rating,
                bc.contact_phone,
                bc.contact_email
            FROM bookings b
            LEFT JOIN bus_companies bc ON bc.company_id = b.bus_company
            WHERE b.booking_id = ? AND b.user_id = (SELECT id FROM users WHERE user_id = ?)
        ");
        $stmt->execute([$bookingId, $_SESSION['user_id']]);
        $booking = $stmt->fetch();
        
        if (!$booking) {
            handleError('Booking not found', 404);
        }
        
        $passengerDetails = json_decode($booking['passenger_details'], true);
        $selectedSeats = json_decode($booking['selected_seats'], true) ?: [];
        
        $result = [
            'booking_id' => $booking['booking_id'],
            'status' => $booking['booking_status'],
            'payment_status' => $booking['payment_status'],
            'travel_date' => $booking['travel_date'],
            'departure_time' => $booking['departure_time'],
            'passenger_count' => (int)$booking['passenger_count'],
            'selected_seats' => $selectedSeats,
            'total_amount' => (float)$booking['total_amount'],
            'booking_date' => $booking['booking_date'],
            'route' => [
                'from' => [
                    'code' => $booking['from_city'],
                    'name' => getCityDisplayName($booking['from_city'])
                ],
                'to' => [
                    'code' => $booking['to_city'],
                    'name' => getCityDisplayName($booking['to_city'])
                ]
            ],
            'bus' => [
                'company' => [
                    'code' => $booking['bus_company'],
                    'name' => $booking['company_name'] ?: getBusCompanyDisplayName($booking['bus_company']),
                    'rating' => (float)($booking['company_rating'] ?: 4.5),
                    'contact_phone' => $booking['contact_phone'],
                    'contact_email' => $booking['contact_email']
                ],
                'type' => [
                    'code' => $booking['bus_type'],
                    'name' => getBusTypeDisplayName($booking['bus_type'])
                ]
            ],
            'passenger_details' => $passengerDetails,
            'special_requirements' => $booking['special_requirements'],
            'payment_method' => $booking['payment_method'],
            'qr_code' => $booking['qr_code']
        ];
        
        handleSuccess($result);
        
    } catch (Exception $e) {
        error_log("Get booking details error: " . $e->getMessage());
        handleError('Failed to fetch booking details', 500);
    }
}

/**
 * Cancel booking
 */
function handleCancelBooking() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        handleError('Method not allowed', 405);
    }
    
    requireLogin();
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (empty($input['booking_id'])) {
        handleError('Booking ID is required', 400);
    }
    
    $bookingId = sanitizeInput($input['booking_id']);
    $cancellationReason = sanitizeInput($input['reason'] ?? 'User requested cancellation');
    
    try {
        $db = getDB();
        $db->beginTransaction();
        
        // Get booking details
        $stmt = $db->prepare("
            SELECT * FROM bookings 
            WHERE booking_id = ? AND user_id = (SELECT id FROM users WHERE user_id = ?)
        ");
        $stmt->execute([$bookingId, $_SESSION['user_id']]);
        $booking = $stmt->fetch();
        
        if (!$booking) {
            $db->rollBack();
            handleError('Booking not found', 404);
        }
        
        if ($booking['booking_status'] === 'cancelled') {
            $db->rollBack();
            handleError('Booking is already cancelled', 400);
        }
        
        if ($booking['booking_status'] === 'completed') {
            $db->rollBack();
            handleError('Cannot cancel completed booking', 400);
        }
        
        // Check if cancellation is allowed (e.g., not too close to departure)
        $travelDateTime = $booking['travel_date'] . ' ' . $booking['departure_time'];
        $hoursUntilDeparture = (strtotime($travelDateTime) - time()) / 3600;
        
        if ($hoursUntilDeparture < 2) {
            $db->rollBack();
            handleError('Cannot cancel booking less than 2 hours before departure', 400);
        }
        
        // Update booking status
        $stmt = $db->prepare("
            UPDATE bookings 
            SET booking_status = 'cancelled', 
                cancellation_date = CURRENT_TIMESTAMP,
                cancellation_reason = ?
            WHERE id = ?
        ");
        $stmt->execute([$cancellationReason, $booking['id']]);
        
        // Release seats
        if ($booking['selected_seats']) {
            $selectedSeats = json_decode($booking['selected_seats'], true);
            $route = $booking['from_city'] . '-' . $booking['to_city'];
            releaseSeatsForBooking($booking['bus_company'], $booking['bus_type'], $route, 
                                 $booking['travel_date'], $booking['departure_time'], $selectedSeats);
        }
        
        // Process refund if payment was made
        $refundAmount = 0;
        if ($booking['payment_status'] === 'paid') {
            $refundAmount = $booking['total_amount'];
            
            // Apply cancellation policy
            if ($hoursUntilDeparture < 24) {
                $refundAmount = 0; // No refund within 24 hours
            } else {
                $refundAmount *= 0.75; // 75% refund for cancellations more than 24 hours
            }
            
            if ($refundAmount > 0) {
                // Update payment status
                $stmt = $db->prepare("
                    UPDATE bookings 
                    SET payment_status = 'refunded'
                    WHERE id = ?
                ");
                $stmt->execute([$booking['id']]);
            }
        }
        
        // Log activity
        logActivity($_SESSION['user_id'], 'booking_cancelled', 'booking', $bookingId, [
            'reason' => $cancellationReason,
            'refund_amount' => $refundAmount
        ]);
        
        $db->commit();
        
        handleSuccess([
            'booking_id' => $bookingId,
            'status' => 'cancelled',
            'refund_amount' => $refundAmount,
            'cancellation_date' => date('Y-m-d H:i:s')
        ], 'Booking cancelled successfully');
        
    } catch (Exception $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        error_log("Cancel booking error: " . $e->getMessage());
        handleError('Booking cancellation failed. Please try again.', 500);
    }
}

/**
 * Update booking (limited fields)
 */
function handleUpdateBooking() {
    if ($_SERVER['REQUEST_METHOD'] !== 'PUT') {
        handleError('Method not allowed', 405);
    }
    
    requireLogin();
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (empty($input['booking_id'])) {
        handleError('Booking ID is required', 400);
    }
    
    $bookingId = sanitizeInput($input['booking_id']);
    $allowedFields = ['special_requirements', 'passenger_details', 'payment_status', 'payment_method'];
    $updates = [];
    $params = [];
    
    foreach ($allowedFields as $field) {
        if (isset($input[$field])) {
            if ($field === 'passenger_details') {
                $updates[] = "$field = ?";
                $params[] = json_encode($input[$field]);
            } else {
                $updates[] = "$field = ?";
                $params[] = sanitizeInput($input[$field]);
            }
        }
    }
    
    if (empty($updates)) {
        handleError('No valid fields to update', 400);
    }
    
    try {
        $db = getDB();
        
        // Check if booking exists and belongs to user
        $stmt = $db->prepare("
            SELECT id, booking_status 
            FROM bookings 
            WHERE booking_id = ? AND user_id = (SELECT id FROM users WHERE user_id = ?)
        ");
        $stmt->execute([$bookingId, $_SESSION['user_id']]);
        $booking = $stmt->fetch();
        
        if (!$booking) {
            handleError('Booking not found', 404);
        }
        
        if ($booking['booking_status'] === 'cancelled') {
            handleError('Cannot update cancelled booking', 400);
        }
        
        // Update booking
        $sql = "UPDATE bookings SET " . implode(', ', $updates) . ", updated_at = CURRENT_TIMESTAMP WHERE id = ?";
        $params[] = $booking['id'];
        
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        
        // If payment status or method is being updated, also update payment record
        if (isset($input['payment_status']) || isset($input['payment_method'])) {
            $paymentMethod = $input['payment_method'] ?? null;
            $paymentStatus = $input['payment_status'] ?? 'pending';
            
            // Map booking payment status to payment table status
            $paymentTableStatus = $paymentStatus === 'paid' ? 'completed' : $paymentStatus;
            
            updatePaymentRecord($bookingId, $paymentMethod, $paymentTableStatus);
        }
        
        // Log activity
        logActivity($_SESSION['user_id'], 'booking_updated', 'booking', $bookingId, $input);
        
        handleSuccess(['booking_id' => $bookingId], 'Booking updated successfully');
        
    } catch (Exception $e) {
        error_log("Update booking error: " . $e->getMessage());
        handleError('Booking update failed. Please try again.', 500);
    }
}

/**
 * Get booking statistics
 */
function handleGetBookingStats() {
    requireLogin();
    
    try {
        $db = getDB();
        
        $stmt = $db->prepare("
            SELECT 
                COUNT(*) as total_bookings,
                COUNT(CASE WHEN booking_status = 'confirmed' THEN 1 END) as confirmed_bookings,
                COUNT(CASE WHEN booking_status = 'pending' THEN 1 END) as pending_bookings,
                COUNT(CASE WHEN booking_status = 'cancelled' THEN 1 END) as cancelled_bookings,
                COUNT(CASE WHEN booking_status = 'completed' THEN 1 END) as completed_bookings,
                SUM(CASE WHEN payment_status = 'paid' THEN total_amount ELSE 0 END) as total_spent,
                AVG(total_amount) as average_booking_amount
            FROM bookings 
            WHERE user_id = (SELECT id FROM users WHERE user_id = ?)
        ");
        $stmt->execute([$_SESSION['user_id']]);
        $stats = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Convert to appropriate types
        foreach ($stats as $key => $value) {
            if (in_array($key, ['total_spent', 'average_booking_amount'])) {
                $stats[$key] = (float)$value;
            } else {
                $stats[$key] = (int)$value;
            }
        }
        
        handleSuccess($stats);
        
    } catch (Exception $e) {
        error_log("Get booking stats error: " . $e->getMessage());
        handleError('Failed to fetch booking statistics', 500);
    }
}

/**
 * Get available seats for a specific route and date
 */
function handleGetAvailableSeats() {
    if (empty($_GET['bus_company']) || empty($_GET['route']) || empty($_GET['departure_date']) || empty($_GET['departure_time'])) {
        handleError('Bus company, route, departure date, and time are required', 400);
    }
    
    $busCompany = sanitizeInput($_GET['bus_company']);
    $busType = sanitizeInput($_GET['bus_type'] ?? 'premium-ac');
    $route = sanitizeInput($_GET['route']);
    $departureDate = sanitizeInput($_GET['departure_date']);
    $departureTime = sanitizeInput($_GET['departure_time']);
    
    // Log the request for debugging
    error_log("Get available seats request: company=$busCompany, type=$busType, route=$route, date=$departureDate, time=$departureTime");
    
    try {
        $db = getDB();
        
        // Initialize seats if they don't exist for this route/date/time
        initializeSeatsForRoute($busCompany, $busType, $route, $departureDate, $departureTime);
        
        // Get seat availability
        $stmt = $db->prepare("
            SELECT seat_number, seat_type, is_occupied, booking_id
            FROM bus_seats 
            WHERE bus_company = ? AND bus_type = ? AND route = ? 
            AND departure_date = ? AND departure_time = ?
            ORDER BY seat_number
        ");
        $stmt->execute([$busCompany, $busType, $route, $departureDate, $departureTime]);
        $seats = $stmt->fetchAll();
        
        // Log the results for debugging
        error_log("Found " . count($seats) . " seats in database");
        
        $seatMap = [];
        foreach ($seats as $seat) {
            $seatMap[] = [
                'number' => (int)$seat['seat_number'],
                'type' => $seat['seat_type'],
                'available' => !$seat['is_occupied'],
                'booking_id' => $seat['booking_id']
            ];
        }
        
        error_log("Returning seat map with " . count($seatMap) . " seats");
        
        handleSuccess([
            'seats' => $seatMap,
            'total_seats' => count($seatMap),
            'available_seats' => count(array_filter($seatMap, function($seat) { return $seat['available']; }))
        ]);
        
    } catch (Exception $e) {
        error_log("Get available seats error: " . $e->getMessage());
        handleError('Failed to fetch seat availability', 500);
    }
}

/**
 * Reserve seats temporarily during booking process
 */
function handleReserveSeats() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        handleError('Method not allowed', 405);
    }
    
    requireLogin();
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    $required = ['bus_company', 'route', 'departure_date', 'departure_time', 'seats'];
    foreach ($required as $field) {
        if (empty($input[$field])) {
            handleError("Field '$field' is required", 400);
        }
    }
    
    $busCompany = sanitizeInput($input['bus_company']);
    $busType = sanitizeInput($input['bus_type'] ?? 'premium-ac');
    $route = sanitizeInput($input['route']);
    $departureDate = sanitizeInput($input['departure_date']);
    $departureTime = sanitizeInput($input['departure_time']);
    $seats = $input['seats'];
    $bookingId = sanitizeInput($input['booking_id'] ?? '');
    $permanent = $input['permanent'] ?? false; // NEW: Flag for permanent reservation (after payment)
    
    // Generate reservation ID for temporary reservations
    $reservationId = $permanent && $bookingId ? $bookingId : 'TEMP_' . $_SESSION['user_id'] . '_' . time();
    
    try {
        $db = getDB();
        $db->beginTransaction();
        
        // Initialize seats if they don't exist
        initializeSeatsForRoute($busCompany, $busType, $route, $departureDate, $departureTime);
        
        // Check if seats are available and reserve them
        foreach ($seats as $seatNumber) {
            $stmt = $db->prepare("
                SELECT is_occupied, reserved_until, booking_id
                FROM bus_seats 
                WHERE bus_company = ? AND bus_type = ? AND route = ? 
                AND departure_date = ? AND departure_time = ? AND seat_number = ?
            ");
            $stmt->execute([$busCompany, $busType, $route, $departureDate, $departureTime, $seatNumber]);
            $seat = $stmt->fetch();
            
            if (!$seat) {
                $db->rollBack();
                handleError("Seat $seatNumber not found", 404);
            }
            
            // For permanent reservations (after payment), allow if seat belongs to same booking
            if ($permanent && $bookingId) {
                if ($seat['is_occupied'] && $seat['booking_id'] !== $bookingId) {
                    $db->rollBack();
                    handleError("Seat $seatNumber is occupied by another booking", 409);
                }
            } else {
                // For temporary reservations, check availability
                if ($seat['is_occupied'] || ($seat['reserved_until'] && strtotime($seat['reserved_until']) > time())) {
                    $db->rollBack();
                    handleError("Seat $seatNumber is not available", 409);
                }
            }
            
            if ($permanent) {
                // **NEW: Permanent reservation after payment**
                $stmt = $db->prepare("
                    UPDATE bus_seats 
                    SET is_occupied = 1, booking_id = ?, reserved_until = NULL
                    WHERE bus_company = ? AND bus_type = ? AND route = ? 
                    AND departure_date = ? AND departure_time = ? AND seat_number = ?
                ");
                $stmt->execute([$reservationId, $busCompany, $busType, $route, $departureDate, $departureTime, $seatNumber]);
                
                // Log the permanent seat reservation
                logActivity($_SESSION['user_id'], 'seat_occupied', 'seat', $seatNumber, [
                    'booking_id' => $bookingId,
                    'route' => $route,
                    'departure_date' => $departureDate,
                    'departure_time' => $departureTime
                ]);
            } else {
                // Temporary reservation for 15 minutes
                $reserveUntil = date('Y-m-d H:i:s', time() + (15 * 60));
                $stmt = $db->prepare("
                    UPDATE bus_seats 
                    SET booking_id = ?, reserved_until = ?
                    WHERE bus_company = ? AND bus_type = ? AND route = ? 
                    AND departure_date = ? AND departure_time = ? AND seat_number = ?
                ");
                $stmt->execute([$reservationId, $reserveUntil, $busCompany, $busType, $route, $departureDate, $departureTime, $seatNumber]);
            }
        }
        
        $db->commit();
        
        $response = [
            'reservation_id' => $reservationId,
            'reserved_seats' => $seats,
            'permanent' => $permanent
        ];
        
        if (!$permanent) {
            $response['expires_at'] = $reserveUntil;
        }
        
        $message = $permanent ? 'Seats permanently reserved after payment' : 'Seats reserved successfully';
        handleSuccess($response, $message);
        
    } catch (Exception $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        error_log("Reserve seats error: " . $e->getMessage());
        handleError('Failed to reserve seats', 500);
    }
}

/**
 * Release temporarily reserved seats
 */
function handleReleaseSeats() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        handleError('Method not allowed', 405);
    }
    
    requireLogin();
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (empty($input['reservation_id'])) {
        handleError('Reservation ID is required', 400);
    }
    
    $reservationId = sanitizeInput($input['reservation_id']);
    
    try {
        $db = getDB();
        
        $stmt = $db->prepare("
            UPDATE bus_seats 
            SET booking_id = NULL, reserved_until = NULL
            WHERE booking_id = ? AND booking_id LIKE 'TEMP_%'
        ");
        $stmt->execute([$reservationId]);
        
        handleSuccess(['released' => $stmt->rowCount()], 'Seats released successfully');
        
    } catch (Exception $e) {
        error_log("Release seats error: " . $e->getMessage());
        handleError('Failed to release seats', 500);
    }
}

/**
 * Helper Functions
 */

?>