<?php
/**
 * SwiftBus Common Functions
 * 
 * This file contains utility functions used throughout the application
 */

// Enhanced session management with better configuration
if (session_status() == PHP_SESSION_NONE && !headers_sent()) {
    // Configure session settings for better reliability
    ini_set('session.cookie_lifetime', 86400); // 24 hours
    ini_set('session.gc_maxlifetime', 86400);   // 24 hours
    ini_set('session.cookie_httponly', 1);      // Security
    ini_set('session.use_strict_mode', 1);      // Security
    
    session_start();
    
    // Regenerate session ID periodically for security
    if (!isset($_SESSION['last_regeneration'])) {
        $_SESSION['last_regeneration'] = time();
    } elseif (time() - $_SESSION['last_regeneration'] > 1800) { // 30 minutes
        session_regenerate_id(true);
        $_SESSION['last_regeneration'] = time();
    }
}

require_once __DIR__ . '/../config/database.php';

/**
 * Get database connection
 */
function getDB() {
    $database = new Database();
    return $database->getConnection();
}

/**
 * Generate unique ID with prefix
 */
function generateUniqueId($prefix = '') {
    return $prefix . date('Y') . str_pad(mt_rand(1, 999999), 6, '0', STR_PAD_LEFT);
}

/**
 * Sanitize input data
 */
function sanitizeInput($data) {
    if (is_array($data)) {
        return array_map('sanitizeInput', $data);
    }
    return htmlspecialchars(strip_tags(trim($data)), ENT_QUOTES, 'UTF-8');
}

/**
 * Validate email address
 */
function validateEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Validate phone number (Ethiopian format)
 */
function validatePhone($phone) {
    // Remove all non-numeric characters
    $phone = preg_replace('/[^0-9]/', '', $phone);
    
    // Check if it's a valid Ethiopian phone number
    // Format 1: 9xxxxxxxx (9 digits starting with 9)
    if (strlen($phone) == 9 && substr($phone, 0, 1) == '9') {
        return true;
    }
    // Format 2: 0xxxxxxxxx (10 digits starting with 0)
    if (strlen($phone) == 10 && substr($phone, 0, 1) == '0') {
        return true;
    }
    // Format 3: 251xxxxxxxxx (12 digits starting with 251)
    if (strlen($phone) == 12 && substr($phone, 0, 3) == '251') {
        return true;
    }
    
    return false;
}

/**
 * Hash password
 */
function hashPassword($password) {
    return password_hash($password, PASSWORD_DEFAULT);
}

/**
 * Verify password
 */
function verifyPassword($password, $hash) {
    return password_verify($password, $hash);
}

/**
 * Enhanced login check with session validation
 */
function isLoggedIn() {
    // Check if session exists and has user_id
    if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
        return false;
    }
    
    // Additional validation: check if user still exists in database
    try {
        $db = getDB();
        $stmt = $db->prepare("SELECT id FROM users WHERE user_id = ? AND is_active = 1");
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch();
        
        if (!$user) {
            // User doesn't exist or is inactive, clear session
            session_unset();
            session_destroy();
            return false;
        }
        
        return true;
    } catch (Exception $e) {
        // Database error, assume logged out for safety
        error_log("Session validation error: " . $e->getMessage());
        return false;
    }
}

/**
 * Check if user is admin
 */
function isAdmin() {
    return isLoggedIn() && isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin';
}

/**
 * Enhanced require login with better error handling
 */
function requireLogin() {
    if (!isLoggedIn()) {
        // Log the authentication failure for debugging
        error_log("Authentication required - Session data: " . print_r($_SESSION, true));
        
        http_response_code(401);
        echo json_encode([
            'success' => false, 
            'message' => 'Authentication required. Please log in again.',
            'error_code' => 'AUTH_REQUIRED',
            'redirect' => 'login.html'
        ]);
        exit;
    }
}

/**
 * Require admin access
 */
function requireAdmin() {
    requireLogin();
    if (!isAdmin()) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Admin access required']);
        exit;
    }
}

/**
 * Enhanced get current user with session refresh
 */
function getCurrentUser() {
    if (!isLoggedIn()) {
        return null;
    }
    
    try {
        $db = getDB();
        $stmt = $db->prepare("SELECT * FROM users WHERE user_id = ? AND is_active = 1");
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch();
        
        if ($user) {
            // Update last activity
            $updateStmt = $db->prepare("UPDATE users SET last_login = NOW() WHERE user_id = ?");
            $updateStmt->execute([$_SESSION['user_id']]);
        }
        
        return $user;
    } catch (Exception $e) {
        error_log("getCurrentUser error: " . $e->getMessage());
        return null;
    }
}

/**
 * Get current user ID (session user_id)
 */
function getCurrentUserId() {
    if (!isLoggedIn()) {
        return null;
    }
    
    return $_SESSION['user_id'];
}

/**
 * Get city display name from code
 */
function getCityDisplayName($cityCode) {
    $cities = [
        'addis-ababa' => 'Addis Ababa',
        'kombolcha' => 'Kombolcha',
        'bahirdar' => 'Bahirdar',
        'dessie' => 'Dessie',
        'adama' => 'Adama',
        'hawasa' => 'Hawasa',
        'arbaminch' => 'Arbaminch',
        'gonder' => 'Gonder',
        'mekele' => 'Mekele',
        'jimma' => 'Jimma'
    ];
    
    return $cities[$cityCode] ?? ucfirst(str_replace('-', ' ', $cityCode));
}

/**
 * Get bus company display name from code
 */
function getBusCompanyDisplayName($companyCode) {
    $companies = [
        'selam-bus' => 'Selam Bus',
        'abay-bus' => 'Abay Bus',
        'ethio-bus' => 'Ethio Bus',
        'habesha-bus' => 'Habesha Bus'
    ];
    
    return $companies[$companyCode] ?? ucfirst(str_replace('-', ' ', $companyCode));
}

/**
 * Get bus type display name from code
 */
function getBusTypeDisplayName($typeCode) {
    $types = [
        'premium-ac' => 'Premium AC Bus',
        'standard-ac' => 'Standard AC Bus',
        'luxury' => 'Luxury Bus',
        'standard' => 'Standard Bus',
        'economy' => 'Economy Bus'
    ];
    
    return $types[$typeCode] ?? ucfirst(str_replace('-', ' ', $typeCode));
}

/**
 * Log admin action
 */
function logAdminAction($action, $targetTable = null, $targetId = null, $oldValues = null, $newValues = null) {
    if (!isAdmin()) {
        return false;
    }
    
    $db = getDB();
    $stmt = $db->prepare("
        INSERT INTO admin_logs (admin_user_id, action, target_table, target_id, old_values, new_values, ip_address, user_agent) 
        VALUES ((SELECT id FROM users WHERE user_id = ?), ?, ?, ?, ?, ?, ?, ?)
    ");
    
    return $stmt->execute([
        $_SESSION['user_id'],
        $action,
        $targetTable,
        $targetId,
        $oldValues ? json_encode($oldValues) : null,
        $newValues ? json_encode($newValues) : null,
        $_SERVER['REMOTE_ADDR'] ?? null,
        $_SERVER['HTTP_USER_AGENT'] ?? null
    ]);
}

/**
 * Send JSON response
 */
function sendJsonResponse($data, $statusCode = 200) {
    http_response_code($statusCode);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

/**
 * Format currency (Ethiopian Birr)
 */
function formatCurrency($amount) {
    return 'ETB ' . number_format($amount, 2);
}

/**
 * Format date for display
 */
function formatDate($date, $format = 'M j, Y') {
    return date($format, strtotime($date));
}

/**
 * Format time for display
 */
function formatTime($time, $format = 'g:i A') {
    return date($format, strtotime($time));
}

/**
 * Calculate distance between two coordinates (Haversine formula)
 */
function calculateDistance($lat1, $lon1, $lat2, $lon2) {
    $earthRadius = 6371; // Earth's radius in kilometers
    
    $dLat = deg2rad($lat2 - $lat1);
    $dLon = deg2rad($lon2 - $lon1);
    
    $a = sin($dLat/2) * sin($dLat/2) + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon/2) * sin($dLon/2);
    $c = 2 * atan2(sqrt($a), sqrt(1-$a));
    
    return $earthRadius * $c;
}

/**
 * Generate QR code data for booking
 */
function generateQRData($bookingId, $passengerName, $route, $date, $seatNumbers) {
    return json_encode([
        'booking_id' => $bookingId,
        'passenger' => $passengerName,
        'route' => $route,
        'date' => $date,
        'seats' => $seatNumbers,
        'generated_at' => date('Y-m-d H:i:s')
    ]);
}

/**
 * Get city name by code
 */
function getCityName($cityCode) {
    $db = getDB();
    $stmt = $db->prepare("SELECT name FROM cities WHERE city_code = ?");
    $stmt->execute([$cityCode]);
    $result = $stmt->fetch();
    return $result ? $result['name'] : $cityCode;
}

/**
 * Get available seats for a schedule on a specific date
 */
function getAvailableSeats($scheduleId, $travelDate) {
    $db = getDB();
    
    // Get total seats for the bus
    $stmt = $db->prepare("
        SELECT b.total_seats 
        FROM schedules s 
        JOIN buses b ON s.bus_id = b.id 
        WHERE s.id = ?
    ");
    $stmt->execute([$scheduleId]);
    $bus = $stmt->fetch();
    
    if (!$bus) {
        return [];
    }
    
    $totalSeats = $bus['total_seats'];
    
    // Get booked seats for this schedule and date
    $stmt = $db->prepare("
        SELECT selected_seats 
        FROM bookings 
        WHERE schedule_id = ? AND travel_date = ? AND booking_status IN ('confirmed', 'pending')
    ");
    $stmt->execute([$scheduleId, $travelDate]);
    $bookings = $stmt->fetchAll();
    
    $bookedSeats = [];
    foreach ($bookings as $booking) {
        if ($booking['selected_seats']) {
            $seats = json_decode($booking['selected_seats'], true);
            if (is_array($seats)) {
                $bookedSeats = array_merge($bookedSeats, $seats);
            }
        }
    }
    
    // Generate available seats
    $availableSeats = [];
    for ($i = 1; $i <= $totalSeats; $i++) {
        if (!in_array($i, $bookedSeats)) {
            $availableSeats[] = $i;
        }
    }
    
    return $availableSeats;
}

/**
 * Calculate booking total amount
 */
function calculateBookingTotal($scheduleId, $passengerCount, $selectedSeats = []) {
    $db = getDB();
    
    // Get schedule price
    $stmt = $db->prepare("SELECT price FROM schedules WHERE id = ?");
    $stmt->execute([$scheduleId]);
    $schedule = $stmt->fetch();
    
    if (!$schedule) {
        return 0;
    }
    
    $seatCount = !empty($selectedSeats) ? count($selectedSeats) : $passengerCount;
    $baseFare = $schedule['price'] * $seatCount;
    $serviceFee = 25; // Fixed service fee
    $tax = $baseFare * 0.10; // 10% tax
    
    return $baseFare + $serviceFee + $tax;
}

/**
 * Send email (placeholder - implement with actual email service)
 */
function sendEmail($to, $subject, $body, $isHtml = true) {
    // This is a placeholder function
    // In production, integrate with services like PHPMailer, SendGrid, etc.
    
    // For now, just log the email
    error_log("Email to: $to, Subject: $subject, Body: $body");
    
    return true;
}

/**
 * Generate booking confirmation email
 */
function sendBookingConfirmation($bookingId) {
    $db = getDB();
    
    // Get booking details
    $stmt = $db->prepare("
        SELECT b.*, u.email, u.full_name, s.departure_time, s.arrival_time,
               r.*, oc.name as origin_city, dc.name as destination_city,
               bus.bus_number, comp.name as company_name
        FROM bookings b
        JOIN users u ON b.user_id = u.id
        JOIN schedules s ON b.schedule_id = s.id
        JOIN routes r ON s.route_id = r.id
        JOIN cities oc ON r.origin_city_id = oc.id
        JOIN cities dc ON r.destination_city_id = dc.id
        JOIN buses bus ON s.bus_id = bus.id
        JOIN bus_companies comp ON bus.company_id = comp.id
        WHERE b.booking_id = ?
    ");
    $stmt->execute([$bookingId]);
    $booking = $stmt->fetch();
    
    if (!$booking) {
        return false;
    }
    
    $subject = "Booking Confirmation - SwiftBus Ticket #{$bookingId}";
    $body = "
        <h2>Booking Confirmed!</h2>
        <p>Dear {$booking['full_name']},</p>
        <p>Your bus ticket has been successfully booked. Here are your booking details:</p>
        
        <div style='background: #f8f9fa; padding: 20px; border-radius: 8px; margin: 20px 0;'>
            <h3>Booking Details</h3>
            <p><strong>Booking ID:</strong> {$booking['booking_id']}</p>
            <p><strong>Route:</strong> {$booking['origin_city']} → {$booking['destination_city']}</p>
            <p><strong>Date:</strong> " . formatDate($booking['travel_date']) . "</p>
            <p><strong>Time:</strong> " . formatTime($booking['departure_time']) . "</p>
            <p><strong>Bus Company:</strong> {$booking['company_name']}</p>
            <p><strong>Bus Number:</strong> {$booking['bus_number']}</p>
            <p><strong>Passengers:</strong> {$booking['passenger_count']}</p>
            <p><strong>Total Amount:</strong> " . formatCurrency($booking['total_amount']) . "</p>
        </div>
        
        <p>Please arrive at the departure point at least 30 minutes before departure time.</p>
        <p>Thank you for choosing SwiftBus!</p>
    ";
    
    return sendEmail($booking['email'], $subject, $body);
}

/**
 * Check if admin emails are predefined
 */
function isAdminEmail($email) {
    $adminEmails = [
        'ezedinmoh1@gmail.com',
        'hanamariamsebsbew1@gmail.com',
        'mubarekali974@gmail.com',
        'wubetlemma788@gmail.com',
        'mahletbelete4@gmail.com',
        'admin@swiftbus.et'
    ];
    
    return in_array(strtolower($email), array_map('strtolower', $adminEmails));
}

/**
 * Get user statistics for dashboard
 */
function getUserStats($userId) {
    $db = getDB();
    
    // Get user's booking statistics
    $stmt = $db->prepare("
        SELECT 
            COUNT(*) as total_bookings,
            COUNT(CASE WHEN booking_status = 'confirmed' AND travel_date >= CURDATE() THEN 1 END) as upcoming_trips,
            COUNT(CASE WHEN booking_status = 'completed' THEN 1 END) as completed_trips,
            COALESCE(SUM(total_amount), 0) as total_spent
        FROM bookings 
        WHERE user_id = (SELECT id FROM users WHERE user_id = ?)
    ");
    $stmt->execute([$userId]);
    
    return $stmt->fetch();
}

/**
 * Get admin statistics for dashboard
 */
function getAdminStats() {
    $db = getDB();
    
    $stats = [];
    
    // Total bookings
    $stmt = $db->query("SELECT COUNT(*) as count FROM bookings");
    $stats['total_bookings'] = $stmt->fetch()['count'];
    
    // Active users
    $stmt = $db->query("SELECT COUNT(*) as count FROM users WHERE is_active = 1 AND role = 'user'");
    $stats['active_users'] = $stmt->fetch()['count'];
    
    // Active buses
    $stmt = $db->query("SELECT COUNT(*) as count FROM buses WHERE status = 'active'");
    $stats['active_buses'] = $stmt->fetch()['count'];
    
    // Today's revenue
    $stmt = $db->query("
        SELECT COALESCE(SUM(total_amount), 0) as revenue 
        FROM bookings 
        WHERE DATE(created_at) = CURDATE() AND payment_status = 'paid'
    ");
    $stats['revenue_today'] = $stmt->fetch()['revenue'];
    
    return $stats;
}

/**
 * Log user activity
 */
function logActivity($userId, $action, $entityType, $entityId, $metadata = []) {
    try {
        $db = getDB();
        $stmt = $db->prepare("
            INSERT INTO activity_logs (user_id, action, entity_type, entity_id, metadata, ip_address, user_agent)
            VALUES ((SELECT id FROM users WHERE user_id = ?), ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $userId,
            $action,
            $entityType,
            $entityId,
            json_encode($metadata),
            $_SERVER['REMOTE_ADDR'] ?? '',
            $_SERVER['HTTP_USER_AGENT'] ?? ''
        ]);
    } catch (Exception $e) {
        error_log("Activity logging error: " . $e->getMessage());
        // Don't fail the main operation if logging fails
    }
}

/**
 * Reserve seats for a booking (handles both temporary and permanent reservations)
 */
function reserveSeatsForBooking($bookingId, $busCompany, $busType, $route, $departureDate, $departureTime, $seats, $permanent = false) {
    try {
        $db = getDB();
        
        // Initialize seats if they don't exist
        initializeSeatsForRoute($busCompany, $busType, $route, $departureDate, $departureTime);
        
        foreach ($seats as $seatNumber) {
            if ($permanent) {
                // Permanent reservation (after payment)
                $stmt = $db->prepare("
                    UPDATE bus_seats 
                    SET is_occupied = 1, booking_id = ?, reserved_until = NULL
                    WHERE bus_company = ? AND bus_type = ? AND route = ? 
                    AND departure_date = ? AND departure_time = ? AND seat_number = ?
                ");
                $stmt->execute([$bookingId, $busCompany, $busType, $route, $departureDate, $departureTime, $seatNumber]);
            } else {
                // Temporary reservation (15 minutes during booking)
                $reserveUntil = date('Y-m-d H:i:s', time() + (15 * 60));
                $stmt = $db->prepare("
                    UPDATE bus_seats 
                    SET booking_id = ?, reserved_until = ?
                    WHERE bus_company = ? AND bus_type = ? AND route = ? 
                    AND departure_date = ? AND departure_time = ? AND seat_number = ?
                    AND (is_occupied = 0 OR booking_id = ?)
                ");
                $stmt->execute([
                    $bookingId, $reserveUntil, $busCompany, $busType, $route, 
                    $departureDate, $departureTime, $seatNumber, $bookingId
                ]);
            }
        }
        
        $type = $permanent ? 'permanently' : 'temporarily';
        error_log("Reserved " . count($seats) . " seats $type for booking: $bookingId");
        
    } catch (Exception $e) {
        error_log("Reserve seats for booking error: " . $e->getMessage());
    }
}

/**
 * Release seats for a booking (when booking is cancelled or expires)
 */
function releaseSeatsForBooking($busCompany, $busType, $route, $departureDate, $departureTime, $selectedSeats) {
    try {
        $db = getDB();
        
        foreach ($selectedSeats as $seatNumber) {
            $stmt = $db->prepare("
                UPDATE bus_seats 
                SET booking_id = NULL, reserved_until = NULL, is_occupied = 0
                WHERE bus_company = ? AND bus_type = ? AND route = ? 
                AND departure_date = ? AND departure_time = ? AND seat_number = ?
            ");
            $stmt->execute([$busCompany, $busType, $route, $departureDate, $departureTime, $seatNumber]);
        }
        
        error_log("Released " . count($selectedSeats) . " seats");
        
    } catch (Exception $e) {
        error_log("Release seats for booking error: " . $e->getMessage());
    }
}

/**
 * Initialize seats for a route if they don't exist
 */
function initializeSeatsForRoute($busCompany, $busType, $route, $departureDate, $departureTime) {
    $db = getDB();
    
    // Check if seats already exist
    $stmt = $db->prepare("
        SELECT COUNT(*) as count 
        FROM bus_seats 
        WHERE bus_company = ? AND bus_type = ? AND route = ? 
        AND departure_date = ? AND departure_time = ?
    ");
    $stmt->execute([$busCompany, $busType, $route, $departureDate, $departureTime]);
    $result = $stmt->fetch();
    
    if ($result['count'] == 0) {
        // Initialize seats based on bus type
        $seatCount = getSeatCountForBusType($busType);
        
        for ($i = 1; $i <= $seatCount; $i++) {
            $seatType = 'regular';
            
            // Designate some seats as women-only (seats 1-4 and every 5th seat)
            if ($i <= 4 || $i % 5 == 0) {
                $seatType = 'women-only';
            }
            
            // Designate accessible seats (seats near the front)
            if ($i == 5 || $i == 6) {
                $seatType = 'accessible';
            }
            
            $stmt = $db->prepare("
                INSERT INTO bus_seats (
                    bus_company, bus_type, route, departure_date, departure_time, 
                    seat_number, seat_type, is_occupied
                ) VALUES (?, ?, ?, ?, ?, ?, ?, 0)
            ");
            $stmt->execute([$busCompany, $busType, $route, $departureDate, $departureTime, $i, $seatType]);
        }
        
        error_log("Initialized $seatCount seats for route: $route");
    }
}

/**
 * Get seat count based on bus type
 */
function getSeatCountForBusType($busType) {
    $seatCounts = [
        'premium-ac' => 35,
        'standard-ac' => 40,
        'luxury' => 30,
        'standard' => 45,
        'economy' => 50
    ];
    
    return $seatCounts[$busType] ?? 35;
}

/**
 * Create payment record in payments table
 */
function createPaymentRecord($bookingId, $amount, $paymentMethod = null, $passengerDetails = null) {
    try {
        $db = getDB();
        
        // Generate payment ID
        $paymentId = generateUniqueId('PAY');
        
        // Extract passenger info
        $passengerName = null;
        $passengerEmail = null;
        
        if ($passengerDetails && is_array($passengerDetails)) {
            $passengerName = $passengerDetails['fullName'] ?? null;
            $passengerEmail = $passengerDetails['email'] ?? null;
        }
        
        // Insert payment record with passenger info
        $stmt = $db->prepare("
            INSERT INTO payments (
                payment_id, booking_id, passenger_name, passenger_email, 
                amount, payment_method, payment_status, created_at
            ) VALUES (
                ?, ?, ?, ?, ?, ?, 'pending', CURRENT_TIMESTAMP
            )
        ");
        
        $stmt->execute([
            $paymentId,
            $bookingId,
            $passengerName,
            $passengerEmail,
            $amount,
            $paymentMethod ?: 'pending'
        ]);
        
        return $paymentId;
        
    } catch (Exception $e) {
        error_log("Create payment record error: " . $e->getMessage());
        // Don't throw error as this is not critical for booking creation
        return null;
    }
}

/**
 * Update payment record after successful payment
 */
function updatePaymentRecord($bookingId, $paymentMethod, $status = 'completed', $passengerDetails = null) {
    try {
        $db = getDB();
        
        // Prepare update fields
        $updateFields = [
            'payment_method = ?',
            'payment_status = ?',
            'payment_date = CURRENT_TIMESTAMP',
            'updated_at = CURRENT_TIMESTAMP'
        ];
        $params = [$paymentMethod, $status];
        
        // Add passenger info if provided and not already set
        if ($passengerDetails && is_array($passengerDetails)) {
            $updateFields[] = 'passenger_name = COALESCE(passenger_name, ?)';
            $updateFields[] = 'passenger_email = COALESCE(passenger_email, ?)';
            $params[] = $passengerDetails['fullName'] ?? null;
            $params[] = $passengerDetails['email'] ?? null;
        }
        
        $params[] = $bookingId;
        
        // Update payment record
        $stmt = $db->prepare("
            UPDATE payments 
            SET " . implode(', ', $updateFields) . "
            WHERE booking_id = ?
        ");
        
        $stmt->execute($params);
        
        return true;
        
    } catch (Exception $e) {
        error_log("Update payment record error: " . $e->getMessage());
        return false;
    }
}

/**
 * Enhanced error handler for API responses with optional extra data
 */
function handleError($message, $code = 500, $extraData = null) {
    error_log("SwiftBus Error: $message");
    
    $response = ['success' => false, 'message' => $message];
    
    // Add extra data if provided
    if ($extraData !== null) {
        $response = array_merge($response, $extraData);
    }
    
    sendJsonResponse($response, $code);
}

/**
 * Success handler for API responses
 */
function handleSuccess($data = [], $message = 'Success') {
    sendJsonResponse(['success' => true, 'message' => $message, 'data' => $data]);
}
?>