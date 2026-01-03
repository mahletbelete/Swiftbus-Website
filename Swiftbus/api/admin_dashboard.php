<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
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

$action = $_GET['action'] ?? 'dashboard_stats';

try {
    switch ($action) {
        case 'dashboard_stats':
            getDashboardStats();
            break;
        case 'recent_activities':
            getRecentActivities();
            break;
        case 'revenue_stats':
            getRevenueStats();
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

function getDashboardStats() {
    try {
        $pdo = getDB();
        
        // Get total bookings
        $stmt = $pdo->query("SELECT COUNT(*) as total FROM bookings");
        $totalBookings = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
        
        // Get confirmed bookings
        $stmt = $pdo->query("SELECT COUNT(*) as total FROM bookings WHERE booking_status = 'confirmed'");
        $confirmedBookings = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
        
        // Get pending bookings
        $stmt = $pdo->query("SELECT COUNT(*) as total FROM bookings WHERE booking_status = 'pending'");
        $pendingBookings = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
        
        // Get cancelled bookings
        $stmt = $pdo->query("SELECT COUNT(*) as total FROM bookings WHERE booking_status = 'cancelled'");
        $cancelledBookings = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
        
        // Get active users (excluding admins)
        $stmt = $pdo->query("SELECT COUNT(*) as total FROM users WHERE role = 'user' AND is_active = 1");
        $activeUsers = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
        
        // Get total users
        $stmt = $pdo->query("SELECT COUNT(*) as total FROM users WHERE role = 'user'");
        $totalUsers = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
        
        // Get active buses (from main buses table)
        $stmt = $pdo->query("SELECT COUNT(*) as total FROM buses WHERE status = 'active'");
        $activeBuses = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
        
        // If no buses in main table, check admin_buses table
        if ($activeBuses == 0) {
            $stmt = $pdo->query("SELECT COUNT(*) as total FROM admin_buses WHERE status = 'Active'");
            $activeBuses = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
        }
        
        // Get total buses
        $stmt = $pdo->query("SELECT COUNT(*) as total FROM buses");
        $totalBuses = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
        
        if ($totalBuses == 0) {
            $stmt = $pdo->query("SELECT COUNT(*) as total FROM admin_buses");
            $totalBuses = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
        }
        
        // Get active routes
        $stmt = $pdo->query("SELECT COUNT(*) as total FROM routes WHERE is_active = 1");
        $activeRoutes = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
        
        // If no routes in main table, check admin_routes table
        if ($activeRoutes == 0) {
            $stmt = $pdo->query("SELECT COUNT(*) as total FROM admin_routes WHERE status = 'Active'");
            $activeRoutes = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
        }
        
        // Get today's revenue
        $stmt = $pdo->query("
            SELECT COALESCE(SUM(total_amount), 0) as revenue 
            FROM bookings 
            WHERE DATE(created_at) = CURDATE() AND payment_status = 'paid'
        ");
        $todayRevenue = $stmt->fetch(PDO::FETCH_ASSOC)['revenue'];
        
        // Get this month's revenue
        $stmt = $pdo->query("
            SELECT COALESCE(SUM(total_amount), 0) as revenue 
            FROM bookings 
            WHERE MONTH(created_at) = MONTH(CURDATE()) 
            AND YEAR(created_at) = YEAR(CURDATE()) 
            AND payment_status = 'paid'
        ");
        $monthRevenue = $stmt->fetch(PDO::FETCH_ASSOC)['revenue'];
        
        // Get this year's revenue
        $stmt = $pdo->query("
            SELECT COALESCE(SUM(total_amount), 0) as revenue 
            FROM bookings 
            WHERE YEAR(created_at) = YEAR(CURDATE()) 
            AND payment_status = 'paid'
        ");
        $yearRevenue = $stmt->fetch(PDO::FETCH_ASSOC)['revenue'];
        
        // If no data from database, try to get from localStorage-like fallback
        if ($totalBookings == 0) {
            // Check if there are any bookings at all
            $stmt = $pdo->query("SELECT COUNT(*) as total FROM bookings");
            $anyBookings = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
            
            if ($anyBookings == 0) {
                // No bookings in database, use reasonable defaults
                $totalBookings = 0;
                $confirmedBookings = 0;
                $pendingBookings = 0;
                $cancelledBookings = 0;
            }
        }
        
        echo json_encode([
            'success' => true,
            'data' => [
                'bookings' => [
                    'total' => (int)$totalBookings,
                    'confirmed' => (int)$confirmedBookings,
                    'pending' => (int)$pendingBookings,
                    'cancelled' => (int)$cancelledBookings
                ],
                'users' => [
                    'active' => (int)$activeUsers,
                    'total' => (int)$totalUsers
                ],
                'buses' => [
                    'active' => (int)$activeBuses,
                    'total' => (int)$totalBuses
                ],
                'routes' => [
                    'active' => (int)$activeRoutes
                ],
                'revenue' => [
                    'today' => (float)$todayRevenue,
                    'month' => (float)$monthRevenue,
                    'year' => (float)$yearRevenue
                ]
            ]
        ]);
        
    } catch (Exception $e) {
        throw new Exception('Failed to fetch dashboard stats: ' . $e->getMessage());
    }
}

function getRecentActivities() {
    try {
        $pdo = getDB();
        
        $activities = [];
        
        // Get recent bookings
        $stmt = $pdo->query("
            SELECT b.booking_id, b.from_city, b.to_city, b.created_at, u.full_name
            FROM bookings b
            LEFT JOIN users u ON b.user_id = u.id
            ORDER BY b.created_at DESC
            LIMIT 3
        ");
        $recentBookings = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($recentBookings as $booking) {
            $timeAgo = getTimeAgo($booking['created_at']);
            $activities[] = [
                'icon' => 'fas fa-ticket-alt',
                'title' => 'New Booking',
                'description' => "Booking {$booking['booking_id']} for {$booking['from_city']} → {$booking['to_city']}",
                'time' => $timeAgo,
                'timestamp' => $booking['created_at']
            ];
        }
        
        // Get recent user registrations
        $stmt = $pdo->query("
            SELECT full_name, email, created_at
            FROM users
            WHERE role = 'user'
            ORDER BY created_at DESC
            LIMIT 2
        ");
        $recentUsers = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($recentUsers as $user) {
            $timeAgo = getTimeAgo($user['created_at']);
            $activities[] = [
                'icon' => 'fas fa-user-plus',
                'title' => 'New User Registration',
                'description' => "{$user['full_name']} joined SwiftBus",
                'time' => $timeAgo,
                'timestamp' => $user['created_at']
            ];
        }
        
        // Get recent payments
        $stmt = $pdo->query("
            SELECT p.amount, p.payment_date, p.booking_id
            FROM payments p
            WHERE p.payment_status = 'completed'
            ORDER BY p.payment_date DESC
            LIMIT 2
        ");
        $recentPayments = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($recentPayments as $payment) {
            if ($payment['payment_date']) {
                $timeAgo = getTimeAgo($payment['payment_date']);
                $activities[] = [
                    'icon' => 'fas fa-money-bill-wave',
                    'title' => 'Payment Received',
                    'description' => "ETB " . number_format($payment['amount'], 2) . " for booking {$payment['booking_id']}",
                    'time' => $timeAgo,
                    'timestamp' => $payment['payment_date']
                ];
            }
        }
        
        // Sort activities by timestamp (most recent first)
        usort($activities, function($a, $b) {
            return strtotime($b['timestamp']) - strtotime($a['timestamp']);
        });
        
        // If no real activities, provide some default ones
        if (empty($activities)) {
            $activities = [
                [
                    'icon' => 'fas fa-info-circle',
                    'title' => 'System Ready',
                    'description' => 'SwiftBus admin dashboard is ready for use',
                    'time' => 'Just now'
                ],
                [
                    'icon' => 'fas fa-database',
                    'title' => 'Database Connected',
                    'description' => 'Successfully connected to SwiftBus database',
                    'time' => '1 minute ago'
                ]
            ];
        }
        
        echo json_encode([
            'success' => true,
            'data' => array_slice($activities, 0, 5) // Limit to 5 activities
        ]);
        
    } catch (Exception $e) {
        throw new Exception('Failed to fetch recent activities: ' . $e->getMessage());
    }
}

function getRevenueStats() {
    try {
        $pdo = getDB();
        
        // Get revenue for last 7 days
        $stmt = $pdo->query("
            SELECT 
                DATE(created_at) as date,
                COALESCE(SUM(total_amount), 0) as revenue
            FROM bookings 
            WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
            AND payment_status = 'paid'
            GROUP BY DATE(created_at)
            ORDER BY date ASC
        ");
        $dailyRevenue = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Fill in missing dates with 0 revenue
        $revenueData = [];
        for ($i = 6; $i >= 0; $i--) {
            $date = date('Y-m-d', strtotime("-$i days"));
            $revenue = 0;
            
            foreach ($dailyRevenue as $day) {
                if ($day['date'] === $date) {
                    $revenue = (float)$day['revenue'];
                    break;
                }
            }
            
            $revenueData[] = [
                'date' => $date,
                'revenue' => $revenue,
                'formatted_date' => date('M j', strtotime($date))
            ];
        }
        
        echo json_encode([
            'success' => true,
            'data' => $revenueData
        ]);
        
    } catch (Exception $e) {
        throw new Exception('Failed to fetch revenue stats: ' . $e->getMessage());
    }
}

function getTimeAgo($datetime) {
    $time = time() - strtotime($datetime);
    
    if ($time < 60) return 'Just now';
    if ($time < 3600) return floor($time/60) . ' minutes ago';
    if ($time < 86400) return floor($time/3600) . ' hours ago';
    if ($time < 2592000) return floor($time/86400) . ' days ago';
    if ($time < 31536000) return floor($time/2592000) . ' months ago';
    return floor($time/31536000) . ' years ago';
}
?>