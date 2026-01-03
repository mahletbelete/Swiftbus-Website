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

// Get database connection
$pdo = getDB();

$action = $_GET['action'] ?? $_POST['action'] ?? '';

try {
    switch ($action) {
        case 'get_bookings':
            getBookings();
            break;
        case 'update_booking':
            updateBooking();
            break;
        case 'cancel_booking':
            cancelBooking();
            break;
        case 'get_booking_details':
            getBookingDetails();
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

function getBookings() {
    try {
        $pdo = getDB();
        // Get filter parameters
        $search = $_GET['search'] ?? '';
        $booking_status = $_GET['booking_status'] ?? '';
        $payment_status = $_GET['payment_status'] ?? '';
        $travel_date = $_GET['travel_date'] ?? '';
        $limit = (int)($_GET['limit'] ?? 50);
        $offset = (int)($_GET['offset'] ?? 0);
        
        // Build query
        $sql = "SELECT 
                    b.id,
                    b.booking_id,
                    b.user_id,
                    u.full_name as user_name,
                    u.email as user_email,
                    u.phone as user_phone,
                    b.bus_company,
                    b.bus_type,
                    b.from_city as route_origin,
                    b.to_city as route_destination,
                    b.travel_date,
                    b.departure_time,
                    b.passenger_count,
                    b.selected_seats as seat_numbers,
                    b.total_amount,
                    b.booking_status,
                    b.payment_status,
                    b.booking_date,
                    b.created_at,
                    b.updated_at
                FROM bookings b
                LEFT JOIN users u ON b.user_id = u.id
                WHERE 1=1";
        
        $params = [];
        
        // Add search filter
        if (!empty($search)) {
            $sql .= " AND (b.booking_id LIKE ? OR u.full_name LIKE ? OR u.phone LIKE ? OR u.email LIKE ?)";
            $searchParam = "%$search%";
            $params = array_merge($params, [$searchParam, $searchParam, $searchParam, $searchParam]);
        }
        
        // Add booking status filter
        if (!empty($booking_status)) {
            $sql .= " AND b.booking_status = ?";
            $params[] = $booking_status;
        }
        
        // Add payment status filter
        if (!empty($payment_status)) {
            $sql .= " AND b.payment_status = ?";
            $params[] = $payment_status;
        }
        
        // Add travel date filter
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
        
        // Process the data
        foreach ($bookings as &$booking) {
            // Parse selected seats if it's JSON
            if (!empty($booking['seat_numbers'])) {
                $seats = json_decode($booking['seat_numbers'], true);
                if (is_array($seats)) {
                    $booking['seat_numbers'] = implode(', ', $seats);
                }
            }
            
            // Format dates
            $booking['travel_date'] = date('Y-m-d', strtotime($booking['travel_date']));
            $booking['booking_date'] = date('Y-m-d', strtotime($booking['booking_date']));
            
            // Create bus name from company and type
            $booking['bus_name'] = $booking['bus_company'] . ' - ' . $booking['bus_type'];
        }
        
        // Get total count for pagination
        $countSql = "SELECT COUNT(*) as total FROM bookings b LEFT JOIN users u ON b.user_id = u.id WHERE 1=1";
        $countParams = [];
        
        if (!empty($search)) {
            $countSql .= " AND (b.booking_id LIKE ? OR u.full_name LIKE ? OR u.phone LIKE ? OR u.email LIKE ?)";
            $searchParam = "%$search%";
            $countParams = array_merge($countParams, [$searchParam, $searchParam, $searchParam, $searchParam]);
        }
        
        if (!empty($booking_status)) {
            $countSql .= " AND b.booking_status = ?";
            $countParams[] = $booking_status;
        }
        
        if (!empty($payment_status)) {
            $countSql .= " AND b.payment_status = ?";
            $countParams[] = $payment_status;
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
                'has_more' => ($offset + $limit) < $totalCount
            ]
        ]);
        
    } catch (Exception $e) {
        throw new Exception('Failed to fetch bookings: ' . $e->getMessage());
    }
}

function updateBooking() {
    try {
        $pdo = getDB();
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!isset($input['booking_id'])) {
            throw new Exception('Booking ID is required');
        }
        
        $booking_id = $input['booking_id'];
        $booking_status = $input['booking_status'] ?? null;
        $payment_status = $input['payment_status'] ?? null;
        $notes = $input['notes'] ?? '';
        
        // Build update query
        $updateFields = [];
        $params = [];
        
        if ($booking_status !== null) {
            $updateFields[] = "booking_status = ?";
            $params[] = $booking_status;
        }
        
        if ($payment_status !== null) {
            $updateFields[] = "payment_status = ?";
            $params[] = $payment_status;
        }
        
        if (!empty($notes)) {
            $updateFields[] = "special_requirements = ?";
            $params[] = $notes;
        }
        
        $updateFields[] = "updated_at = NOW()";
        
        if (empty($updateFields)) {
            throw new Exception('No fields to update');
        }
        
        $sql = "UPDATE bookings SET " . implode(', ', $updateFields) . " WHERE booking_id = ?";
        $params[] = $booking_id;
        
        $stmt = $pdo->prepare($sql);
        $result = $stmt->execute($params);
        
        if (!$result) {
            throw new Exception('Failed to update booking');
        }
        
        if ($stmt->rowCount() === 0) {
            throw new Exception('Booking not found');
        }
        
        echo json_encode([
            'success' => true,
            'message' => 'Booking updated successfully'
        ]);
        
    } catch (Exception $e) {
        throw new Exception('Failed to update booking: ' . $e->getMessage());
    }
}

function cancelBooking() {
    try {
        $pdo = getDB();
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!isset($input['booking_id'])) {
            throw new Exception('Booking ID is required');
        }
        
        $booking_id = $input['booking_id'];
        $reason = $input['reason'] ?? 'Cancelled by admin';
        
        $sql = "UPDATE bookings SET 
                booking_status = 'cancelled',
                cancellation_reason = ?,
                cancellation_date = NOW(),
                updated_at = NOW()
                WHERE booking_id = ?";
        
        $stmt = $pdo->prepare($sql);
        $result = $stmt->execute([$reason, $booking_id]);
        
        if (!$result) {
            throw new Exception('Failed to cancel booking');
        }
        
        if ($stmt->rowCount() === 0) {
            throw new Exception('Booking not found');
        }
        
        echo json_encode([
            'success' => true,
            'message' => 'Booking cancelled successfully'
        ]);
        
    } catch (Exception $e) {
        throw new Exception('Failed to cancel booking: ' . $e->getMessage());
    }
}

function getBookingDetails() {
    try {
        $pdo = getDB();
        $booking_id = $_GET['booking_id'] ?? '';
        
        if (empty($booking_id)) {
            throw new Exception('Booking ID is required');
        }
        
        $sql = "SELECT 
                    b.*,
                    u.full_name as user_name,
                    u.email as user_email,
                    u.phone as user_phone
                FROM bookings b
                LEFT JOIN users u ON b.user_id = u.id
                WHERE b.booking_id = ?";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$booking_id]);
        $booking = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$booking) {
            throw new Exception('Booking not found');
        }
        
        // Parse passenger details if it's JSON
        if (!empty($booking['passenger_details'])) {
            $booking['passenger_details'] = json_decode($booking['passenger_details'], true);
        }
        
        // Parse selected seats if it's JSON
        if (!empty($booking['selected_seats'])) {
            $booking['selected_seats'] = json_decode($booking['selected_seats'], true);
        }
        
        echo json_encode([
            'success' => true,
            'data' => $booking
        ]);
        
    } catch (Exception $e) {
        throw new Exception('Failed to get booking details: ' . $e->getMessage());
    }
}
?>