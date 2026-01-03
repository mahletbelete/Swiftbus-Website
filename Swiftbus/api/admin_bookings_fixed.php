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

$action = $_GET['action'] ?? $_POST['action'] ?? 'get_bookings';

try {
    // Try database first
    $pdo = new PDO("mysql:host=localhost;dbname=swiftbus_db", "root", "");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    switch ($action) {
        case 'get_bookings':
            getBookings($pdo);
            break;
        case 'get_booking':
            getBooking($pdo);
            break;
        case 'update_booking':
            updateBooking($pdo);
            break;
        case 'cancel_booking':
            cancelBooking($pdo);
            break;
        default:
            throw new Exception('Invalid action');
    }
} catch (Exception $e) {
    // Database failed, use localStorage fallback
    echo json_encode([
        'success' => true,
        'data' => getLocalStorageFallbackBookings(),
        'source' => 'localStorage',
        'message' => 'Using localStorage fallback data'
    ]);
}

function getBookings($pdo) {
    try {
        // Get filter parameters
        $search = $_GET['search'] ?? '';
        $status = $_GET['status'] ?? '';
        $date_from = $_GET['date_from'] ?? '';
        $date_to = $_GET['date_to'] ?? '';
        $limit = max(1, min(100, intval($_GET['limit'] ?? 50)));
        $offset = max(0, intval($_GET['offset'] ?? 0));
        
        $sql = "SELECT 
                    b.id,
                    b.booking_id,
                    b.user_id,
                    u.full_name as passenger_name,
                    u.email as passenger_email,
                    u.phone as passenger_phone,
                    b.bus_company,
                    b.bus_type,
                    b.from_city,
                    b.to_city,
                    b.travel_date,
                    b.departure_time,
                    b.passenger_count,
                    b.selected_seats,
                    b.passenger_details,
                    b.total_amount,
                    b.booking_status,
                    b.payment_status,
                    b.payment_method,
                    b.payment_reference,
                    b.special_requirements,
                    b.qr_code,
                    b.booking_date,
                    b.created_at
                FROM bookings b
                LEFT JOIN users u ON b.user_id = u.id
                WHERE 1=1";
        
        $params = [];
        
        // Add search filter
        if (!empty($search)) {
            $sql .= " AND (b.booking_id LIKE ? OR u.full_name LIKE ? OR u.email LIKE ? OR b.from_city LIKE ? OR b.to_city LIKE ?)";
            $searchParam = "%$search%";
            $params = array_merge($params, [$searchParam, $searchParam, $searchParam, $searchParam, $searchParam]);
        }
        
        // Add status filter
        if (!empty($status)) {
            $sql .= " AND b.booking_status = ?";
            $params[] = $status;
        }
        
        // Add date filters
        if (!empty($date_from)) {
            $sql .= " AND b.travel_date >= ?";
            $params[] = $date_from;
        }
        
        if (!empty($date_to)) {
            $sql .= " AND b.travel_date <= ?";
            $params[] = $date_to;
        }
        
        // Add ordering and pagination
        $sql .= " ORDER BY b.created_at DESC LIMIT $limit OFFSET $offset";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Process the data
        foreach ($bookings as &$booking) {
            // Parse JSON fields
            if (!empty($booking['selected_seats'])) {
                $booking['selected_seats'] = json_decode($booking['selected_seats'], true);
            }
            if (!empty($booking['passenger_details'])) {
                $booking['passenger_details'] = json_decode($booking['passenger_details'], true);
            }
            
            // Format dates
            $booking['travel_date'] = date('Y-m-d', strtotime($booking['travel_date']));
            $booking['booking_date'] = date('Y-m-d H:i:s', strtotime($booking['booking_date']));
            $booking['created_at'] = date('Y-m-d H:i:s', strtotime($booking['created_at']));
            
            // Format amount
            $booking['total_amount'] = (float)$booking['total_amount'];
            
            // Add status labels
            $booking['status_label'] = ucfirst($booking['booking_status']);
            $booking['payment_label'] = ucfirst($booking['payment_status']);
        }
        
        // Get total count for pagination
        $countSql = "SELECT COUNT(*) as total FROM bookings b LEFT JOIN users u ON b.user_id = u.id WHERE 1=1";
        $countParams = [];
        
        if (!empty($search)) {
            $countSql .= " AND (b.booking_id LIKE ? OR u.full_name LIKE ? OR u.email LIKE ? OR b.from_city LIKE ? OR b.to_city LIKE ?)";
            $searchParam = "%$search%";
            $countParams = array_merge($countParams, [$searchParam, $searchParam, $searchParam, $searchParam, $searchParam]);
        }
        
        if (!empty($status)) {
            $countSql .= " AND b.booking_status = ?";
            $countParams[] = $status;
        }
        
        if (!empty($date_from)) {
            $countSql .= " AND b.travel_date >= ?";
            $countParams[] = $date_from;
        }
        
        if (!empty($date_to)) {
            $countSql .= " AND b.travel_date <= ?";
            $countParams[] = $date_to;
        }
        
        $countStmt = $pdo->prepare($countSql);
        $countStmt->execute($countParams);
        $totalCount = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];
        
        echo json_encode([
            'success' => true,
            'data' => $bookings,
            'source' => 'database',
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

function getLocalStorageFallbackBookings() {
    return [
        [
            'id' => 1,
            'booking_id' => 'BK001',
            'user_id' => 1,
            'passenger_name' => 'John Doe',
            'passenger_email' => 'john.doe@example.com',
            'passenger_phone' => '+251911123456',
            'bus_company' => 'Selam Bus',
            'bus_type' => 'premium-ac',
            'from_city' => 'Addis Ababa',
            'to_city' => 'Bahir Dar',
            'travel_date' => date('Y-m-d', strtotime('+1 day')),
            'departure_time' => '08:00',
            'passenger_count' => 2,
            'selected_seats' => [12, 13],
            'passenger_details' => [
                [
                    'fullName' => 'John Doe',
                    'email' => 'john.doe@example.com',
                    'phone' => '+251911123456',
                    'nationality' => 'Ethiopian',
                    'idNumber' => 'ID123456',
                    'ageGroup' => 'adult'
                ],
                [
                    'fullName' => 'Jane Doe',
                    'email' => 'jane.doe@example.com',
                    'phone' => '+251922234567',
                    'nationality' => 'Ethiopian',
                    'idNumber' => 'ID234567',
                    'ageGroup' => 'adult'
                ]
            ],
            'total_amount' => 1700.00,
            'booking_status' => 'confirmed',
            'payment_status' => 'paid',
            'payment_method' => 'telebirr',
            'payment_reference' => 'TB123456789',
            'special_requirements' => '',
            'qr_code' => 'QR_BK001_' . time(),
            'booking_date' => date('Y-m-d H:i:s'),
            'created_at' => date('Y-m-d H:i:s'),
            'status_label' => 'Confirmed',
            'payment_label' => 'Paid'
        ],
        [
            'id' => 2,
            'booking_id' => 'BK002',
            'user_id' => 2,
            'passenger_name' => 'Jane Smith',
            'passenger_email' => 'jane.smith@example.com',
            'passenger_phone' => '+251922234567',
            'bus_company' => 'Abay Bus',
            'bus_type' => 'standard-ac',
            'from_city' => 'Addis Ababa',
            'to_city' => 'Gondar',
            'travel_date' => date('Y-m-d', strtotime('+2 days')),
            'departure_time' => '09:00',
            'passenger_count' => 1,
            'selected_seats' => [8],
            'passenger_details' => [
                [
                    'fullName' => 'Jane Smith',
                    'email' => 'jane.smith@example.com',
                    'phone' => '+251922234567',
                    'nationality' => 'Ethiopian',
                    'idNumber' => 'ID345678',
                    'ageGroup' => 'adult'
                ]
            ],
            'total_amount' => 950.00,
            'booking_status' => 'pending',
            'payment_status' => 'pending',
            'payment_method' => 'cbe',
            'payment_reference' => '',
            'special_requirements' => 'Window seat preferred',
            'qr_code' => '',
            'booking_date' => date('Y-m-d H:i:s', strtotime('-1 hour')),
            'created_at' => date('Y-m-d H:i:s', strtotime('-1 hour')),
            'status_label' => 'Pending',
            'payment_label' => 'Pending'
        ],
        [
            'id' => 3,
            'booking_id' => 'BK003',
            'user_id' => 3,
            'passenger_name' => 'Mike Johnson',
            'passenger_email' => 'mike.johnson@example.com',
            'passenger_phone' => '+251933345678',
            'bus_company' => 'Ethio Bus',
            'bus_type' => 'standard',
            'from_city' => 'Addis Ababa',
            'to_city' => 'Hawassa',
            'travel_date' => date('Y-m-d', strtotime('+3 days')),
            'departure_time' => '10:00',
            'passenger_count' => 3,
            'selected_seats' => [15, 16, 17],
            'passenger_details' => [
                [
                    'fullName' => 'Mike Johnson',
                    'email' => 'mike.johnson@example.com',
                    'phone' => '+251933345678',
                    'nationality' => 'Ethiopian',
                    'idNumber' => 'ID456789',
                    'ageGroup' => 'adult'
                ],
                [
                    'fullName' => 'Sarah Johnson',
                    'email' => 'sarah.johnson@example.com',
                    'phone' => '+251944456789',
                    'nationality' => 'Ethiopian',
                    'idNumber' => 'ID567890',
                    'ageGroup' => 'adult'
                ],
                [
                    'fullName' => 'Tommy Johnson',
                    'email' => '',
                    'phone' => '',
                    'nationality' => 'Ethiopian',
                    'idNumber' => 'ID678901',
                    'ageGroup' => 'child'
                ]
            ],
            'total_amount' => 1350.00,
            'booking_status' => 'confirmed',
            'payment_status' => 'paid',
            'payment_method' => 'dashen',
            'payment_reference' => 'DB987654321',
            'special_requirements' => 'Family seating together',
            'qr_code' => 'QR_BK003_' . time(),
            'booking_date' => date('Y-m-d H:i:s', strtotime('-2 hours')),
            'created_at' => date('Y-m-d H:i:s', strtotime('-2 hours')),
            'status_label' => 'Confirmed',
            'payment_label' => 'Paid'
        ]
    ];
}

// Placeholder functions for other actions
function getBooking($pdo) {
    echo json_encode(['success' => false, 'message' => 'Get booking not implemented yet']);
}

function updateBooking($pdo) {
    echo json_encode(['success' => false, 'message' => 'Update booking not implemented yet']);
}

function cancelBooking($pdo) {
    echo json_encode(['success' => false, 'message' => 'Cancel booking not implemented yet']);
}
?>