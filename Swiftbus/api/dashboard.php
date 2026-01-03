<?php
/**
 * SwiftBus Dashboard API
 * 
 * Handles dashboard statistics and metrics for both admin and user dashboards
 */

// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: ' . ($_SERVER['HTTP_ORIGIN'] ?? '*'));
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Access-Control-Allow-Credentials: true');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    exit(0);
}

require_once '../includes/functions.php';

$action = $_GET['action'] ?? $_POST['action'] ?? '';

switch ($action) {
    case 'get_admin_stats':
        handleGetAdminStats();
        break;
    case 'get_user_stats':
        handleGetUserStats();
        break;
    case 'get_notifications':
        handleGetNotifications();
        break;
    case 'get_recent_activities':
        handleGetRecentActivities();
        break;
    case 'get_upcoming_trips':
        handleGetUpcomingTrips();
        break;
    case 'get_system_alerts':
        handleGetSystemAlerts();
        break;
    default:
        handleError('Invalid action', 400);
}

/**
 * Get admin dashboard statistics
 */
function handleGetAdminStats() {
    if (!isLoggedIn()) {
        handleError('Authentication required', 401);
    }
    
    if (!isAdmin()) {
        handleError('Admin access required', 403);
    }
    
    try {
        $db = getDB();
        
        // Get current date ranges
        $today = date('Y-m-d');
        $weekStart = date('Y-m-d', strtotime('monday this week'));
        $monthStart = date('Y-m-01');
        $yearStart = date('Y-01-01');
        
        // Total Bookings Statistics with enhanced data
        $bookingStats = [];
        
        // Today's bookings
        $stmt = $db->prepare("SELECT COUNT(*) as count, COALESCE(SUM(total_amount), 0) as revenue FROM bookings WHERE DATE(created_at) = ?");
        $stmt->execute([$today]);
        $todayBookings = $stmt->fetch();
        $bookingStats['today'] = [
            'count' => (int)$todayBookings['count'],
            'revenue' => (float)$todayBookings['revenue']
        ];
        
        // This week's bookings
        $stmt = $db->prepare("SELECT COUNT(*) as count, COALESCE(SUM(total_amount), 0) as revenue FROM bookings WHERE DATE(created_at) >= ?");
        $stmt->execute([$weekStart]);
        $weekBookings = $stmt->fetch();
        $bookingStats['week'] = [
            'count' => (int)$weekBookings['count'],
            'revenue' => (float)$weekBookings['revenue']
        ];
        
        // This month's bookings
        $stmt = $db->prepare("SELECT COUNT(*) as count, COALESCE(SUM(total_amount), 0) as revenue FROM bookings WHERE DATE(created_at) >= ?");
        $stmt->execute([$monthStart]);
        $monthBookings = $stmt->fetch();
        $bookingStats['month'] = [
            'count' => (int)$monthBookings['count'],
            'revenue' => (float)$monthBookings['revenue']
        ];
        
        // This year's bookings
        $stmt = $db->prepare("SELECT COUNT(*) as count, COALESCE(SUM(total_amount), 0) as revenue FROM bookings WHERE DATE(created_at) >= ?");
        $stmt->execute([$yearStart]);
        $yearBookings = $stmt->fetch();
        $bookingStats['year'] = [
            'count' => (int)$yearBookings['count'],
            'revenue' => (float)$yearBookings['revenue']
        ];
        
        // Enhanced Bus Statistics
        $stmt = $db->prepare("SELECT 
                             COUNT(*) as total,
                             SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active,
                             SUM(CASE WHEN status = 'maintenance' THEN 1 ELSE 0 END) as maintenance,
                             SUM(CASE WHEN status = 'inactive' THEN 1 ELSE 0 END) as inactive
                             FROM buses");
        $stmt->execute();
        $busStats = $stmt->fetch();
        
        // Enhanced Route Statistics
        $stmt = $db->prepare("SELECT 
                             COUNT(*) as total,
                             SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) as active
                             FROM routes");
        $stmt->execute();
        $routeStats = $stmt->fetch();
        
        // Enhanced User Statistics
        $stmt = $db->prepare("SELECT 
                             COUNT(*) as total, 
                             SUM(CASE WHEN role = 'admin' THEN 1 ELSE 0 END) as admins,
                             SUM(CASE WHEN role = 'user' THEN 1 ELSE 0 END) as users,
                             SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) as active,
                             SUM(CASE WHEN DATE(joined_date) = ? THEN 1 ELSE 0 END) as new_today
                             FROM users");
        $stmt->execute([$today]);
        $userStats = $stmt->fetch();
        
        // Enhanced Upcoming Trips Statistics
        $nextWeek = date('Y-m-d', strtotime('+7 days'));
        
        // Today's trips
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM schedules WHERE effective_from <= ? AND (effective_until IS NULL OR effective_until >= ?) AND is_active = 1");
        $stmt->execute([$today, $today]);
        $todayTrips = $stmt->fetch();
        
        // Next 7 days trips
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM schedules WHERE effective_from <= ? AND (effective_until IS NULL OR effective_until >= ?) AND is_active = 1");
        $stmt->execute([$nextWeek, $today]);
        $upcomingTrips = $stmt->fetch();
        
        // Popular Routes (top 5 by booking count)
        $stmt = $db->prepare("SELECT r.route_id, r.origin_city_id, r.destination_city_id, 
                             oc.name as origin_city, dc.name as destination_city,
                             COUNT(b.id) as booking_count 
                             FROM routes r 
                             LEFT JOIN cities oc ON r.origin_city_id = oc.id
                             LEFT JOIN cities dc ON r.destination_city_id = dc.id
                             LEFT JOIN bookings b ON r.id = b.route_id 
                             WHERE r.is_active = 1 
                             GROUP BY r.id 
                             ORDER BY booking_count DESC 
                             LIMIT 5");
        $stmt->execute();
        $popularRoutes = $stmt->fetchAll();
        
        $dashboardData = [
            'bookings' => $bookingStats,
            'buses' => [
                'total' => (int)$busStats['total'],
                'active' => (int)$busStats['active'],
                'maintenance' => (int)$busStats['maintenance'],
                'inactive' => (int)$busStats['inactive']
            ],
            'routes' => [
                'total' => (int)$routeStats['total'],
                'active' => (int)$routeStats['active'],
                'popular' => $popularRoutes
            ],
            'users' => [
                'total' => (int)$userStats['total'],
                'active' => (int)$userStats['active'],
                'admins' => (int)$userStats['admins'],
                'regular_users' => (int)$userStats['users'],
                'new_today' => (int)$userStats['new_today']
            ],
            'upcoming_trips' => [
                'count' => (int)$upcomingTrips['count'],
                'today' => (int)$todayTrips['count'],
                'next_7_days' => (int)$upcomingTrips['count']
            ],
            'generated_at' => date('Y-m-d H:i:s')
        ];
        
        handleSuccess($dashboardData, 'Admin dashboard statistics retrieved successfully');
        
    } catch (Exception $e) {
        error_log("Admin dashboard stats error: " . $e->getMessage());
        handleError('Failed to retrieve dashboard statistics', 500);
    }
}

/**
 * Get user dashboard statistics
 */
function handleGetUserStats() {
    if (!isLoggedIn()) {
        handleError('Authentication required', 401);
    }
    
    try {
        $db = getDB();
        $userId = getCurrentUserId();
        
        // User's booking statistics
        $stmt = $db->prepare("SELECT 
                             COUNT(*) as total_bookings,
                             SUM(CASE WHEN status = 'confirmed' THEN 1 ELSE 0 END) as confirmed_bookings,
                             SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_bookings,
                             SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled_bookings,
                             COALESCE(SUM(total_amount), 0) as total_spent
                             FROM bookings WHERE user_id = ?");
        $stmt->execute([$userId]);
        $userBookings = $stmt->fetch();
        
        // Upcoming trips
        $today = date('Y-m-d');
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM bookings WHERE user_id = ? AND travel_date >= ? AND status IN ('confirmed', 'pending')");
        $stmt->execute([$userId, $today]);
        $upcomingTrips = $stmt->fetch();
        
        // Recent bookings (last 30 days)
        $lastMonth = date('Y-m-d', strtotime('-30 days'));
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM bookings WHERE user_id = ? AND DATE(created_at) >= ?");
        $stmt->execute([$userId, $lastMonth]);
        $recentBookings = $stmt->fetch();
        
        // Favorite routes
        $stmt = $db->prepare("SELECT r.route_name, r.origin_city, r.destination_city, COUNT(b.id) as trip_count 
                             FROM routes r 
                             JOIN bookings b ON r.id = b.route_id 
                             WHERE b.user_id = ? 
                             GROUP BY r.id 
                             ORDER BY trip_count DESC 
                             LIMIT 3");
        $stmt->execute([$userId]);
        $favoriteRoutes = $stmt->fetchAll();
        
        $userDashboardData = [
            'bookings' => [
                'total' => (int)$userBookings['total_bookings'],
                'confirmed' => (int)$userBookings['confirmed_bookings'],
                'pending' => (int)$userBookings['pending_bookings'],
                'cancelled' => (int)$userBookings['cancelled_bookings'],
                'total_spent' => (float)$userBookings['total_spent']
            ],
            'upcoming_trips' => (int)$upcomingTrips['count'],
            'recent_activity' => (int)$recentBookings['count'],
            'favorite_routes' => $favoriteRoutes,
            'generated_at' => date('Y-m-d H:i:s')
        ];
        
        handleSuccess($userDashboardData, 'User dashboard statistics retrieved successfully');
        
    } catch (Exception $e) {
        error_log("User dashboard stats error: " . $e->getMessage());
        handleError('Failed to retrieve user dashboard statistics', 500);
    }
}

/**
 * Get notifications for dashboard
 */
function handleGetNotifications() {
    if (!isLoggedIn()) {
        handleError('Authentication required', 401);
    }
    
    try {
        $db = getDB();
        $userId = getCurrentUserId();
        $isAdminUser = isAdmin();
        
        $notifications = [];
        
        if ($isAdminUser) {
            // Admin notifications
            
            // Low bus availability
            $stmt = $db->prepare("SELECT COUNT(*) as count FROM buses WHERE status = 'maintenance'");
            $stmt->execute();
            $maintenanceBuses = $stmt->fetch();
            
            if ($maintenanceBuses['count'] > 5) {
                $notifications[] = [
                    'id' => 'maintenance_alert',
                    'type' => 'warning',
                    'title' => 'High Maintenance Alert',
                    'message' => $maintenanceBuses['count'] . ' buses are currently under maintenance',
                    'icon' => 'fas fa-wrench',
                    'created_at' => date('Y-m-d H:i:s')
                ];
            }
            
            // Overbooking alerts
            $stmt = $db->prepare("SELECT s.id, r.route_name, s.date, s.departure_time, 
                                 (s.total_seats - s.available_seats) as booked_seats, s.total_seats
                                 FROM schedules s 
                                 JOIN routes r ON s.route_id = r.id 
                                 WHERE s.available_seats < 5 AND s.date >= CURDATE() 
                                 ORDER BY s.date, s.departure_time 
                                 LIMIT 5");
            $stmt->execute();
            $nearFullTrips = $stmt->fetchAll();
            
            foreach ($nearFullTrips as $trip) {
                $notifications[] = [
                    'id' => 'overbooking_' . $trip['id'],
                    'type' => 'info',
                    'title' => 'Near Full Capacity',
                    'message' => $trip['route_name'] . ' on ' . $trip['date'] . ' - Only ' . $trip['available_seats'] . ' seats left',
                    'icon' => 'fas fa-users',
                    'created_at' => date('Y-m-d H:i:s')
                ];
            }
            
            // Recent cancellations
            $yesterday = date('Y-m-d', strtotime('-1 day'));
            $stmt = $db->prepare("SELECT COUNT(*) as count FROM bookings WHERE status = 'cancelled' AND DATE(updated_at) >= ?");
            $stmt->execute([$yesterday]);
            $recentCancellations = $stmt->fetch();
            
            if ($recentCancellations['count'] > 10) {
                $notifications[] = [
                    'id' => 'cancellation_spike',
                    'type' => 'error',
                    'title' => 'High Cancellation Rate',
                    'message' => $recentCancellations['count'] . ' bookings cancelled in the last 24 hours',
                    'icon' => 'fas fa-times-circle',
                    'created_at' => date('Y-m-d H:i:s')
                ];
            }
            
        } else {
            // User notifications
            
            // Upcoming trips
            $tomorrow = date('Y-m-d', strtotime('+1 day'));
            $stmt = $db->prepare("SELECT b.id, r.route_name, b.travel_date, s.departure_time 
                                 FROM bookings b 
                                 JOIN routes r ON b.route_id = r.id 
                                 JOIN schedules s ON b.schedule_id = s.id 
                                 WHERE b.user_id = ? AND b.travel_date = ? AND b.status = 'confirmed'");
            $stmt->execute([$userId, $tomorrow]);
            $tomorrowTrips = $stmt->fetchAll();
            
            foreach ($tomorrowTrips as $trip) {
                $notifications[] = [
                    'id' => 'upcoming_trip_' . $trip['id'],
                    'type' => 'info',
                    'title' => 'Trip Tomorrow',
                    'message' => $trip['route_name'] . ' at ' . $trip['departure_time'],
                    'icon' => 'fas fa-calendar-check',
                    'created_at' => date('Y-m-d H:i:s')
                ];
            }
            
            // Booking confirmations (last 7 days)
            $lastWeek = date('Y-m-d', strtotime('-7 days'));
            $stmt = $db->prepare("SELECT COUNT(*) as count FROM bookings WHERE user_id = ? AND status = 'confirmed' AND DATE(updated_at) >= ?");
            $stmt->execute([$userId, $lastWeek]);
            $recentConfirmations = $stmt->fetch();
            
            if ($recentConfirmations['count'] > 0) {
                $notifications[] = [
                    'id' => 'recent_confirmations',
                    'type' => 'success',
                    'title' => 'Bookings Confirmed',
                    'message' => $recentConfirmations['count'] . ' booking(s) confirmed this week',
                    'icon' => 'fas fa-check-circle',
                    'created_at' => date('Y-m-d H:i:s')
                ];
            }
        }
        
        // Add system-wide notifications
        $notifications[] = [
            'id' => 'system_update',
            'type' => 'info',
            'title' => 'System Update',
            'message' => 'SwiftBus system is running smoothly. All services operational.',
            'icon' => 'fas fa-info-circle',
            'created_at' => date('Y-m-d H:i:s')
        ];
        
        handleSuccess($notifications, 'Notifications retrieved successfully');
        
    } catch (Exception $e) {
        error_log("Notifications error: " . $e->getMessage());
        handleError('Failed to retrieve notifications', 500);
    }
}

/**
 * Get recent activities
 */
function handleGetRecentActivities() {
    if (!isLoggedIn()) {
        handleError('Authentication required', 401);
    }
    
    try {
        $db = getDB();
        $isAdminUser = isAdmin();
        
        $activities = [];
        
        if ($isAdminUser) {
            // Admin activities - system-wide
            $stmt = $db->prepare("SELECT 'booking' as type, b.id, u.full_name as user_name, r.route_name, b.status, b.created_at 
                                 FROM bookings b 
                                 JOIN users u ON b.user_id = u.user_id 
                                 JOIN routes r ON b.route_id = r.id 
                                 ORDER BY b.created_at DESC 
                                 LIMIT 10");
            $stmt->execute();
            $recentBookings = $stmt->fetchAll();
            
            foreach ($recentBookings as $booking) {
                $activities[] = [
                    'id' => 'booking_' . $booking['id'],
                    'type' => 'booking',
                    'title' => 'New Booking',
                    'description' => $booking['user_name'] . ' booked ' . $booking['route_name'],
                    'status' => $booking['status'],
                    'timestamp' => $booking['created_at'],
                    'icon' => 'fas fa-ticket-alt'
                ];
            }
        } else {
            // User activities - personal only
            $userId = getCurrentUserId();
            $stmt = $db->prepare("SELECT b.id, r.route_name, b.status, b.created_at, b.updated_at 
                                 FROM bookings b 
                                 JOIN routes r ON b.route_id = r.id 
                                 WHERE b.user_id = ? 
                                 ORDER BY b.updated_at DESC 
                                 LIMIT 10");
            $stmt->execute([$userId]);
            $userBookings = $stmt->fetchAll();
            
            foreach ($userBookings as $booking) {
                $activities[] = [
                    'id' => 'user_booking_' . $booking['id'],
                    'type' => 'booking',
                    'title' => 'Your Booking',
                    'description' => 'Booking for ' . $booking['route_name'] . ' - ' . ucfirst($booking['status']),
                    'status' => $booking['status'],
                    'timestamp' => $booking['updated_at'],
                    'icon' => 'fas fa-ticket-alt'
                ];
            }
        }
        
        handleSuccess($activities, 'Recent activities retrieved successfully');
        
    } catch (Exception $e) {
        error_log("Recent activities error: " . $e->getMessage());
        handleError('Failed to retrieve recent activities', 500);
    }
}

/**
 * Get upcoming trips
 */
function handleGetUpcomingTrips() {
    if (!isLoggedIn()) {
        handleError('Authentication required', 401);
    }
    
    try {
        $db = getDB();
        $isAdminUser = isAdmin();
        $today = date('Y-m-d');
        
        if ($isAdminUser) {
            // Admin view - all upcoming trips
            $stmt = $db->prepare("SELECT s.id, r.route_name, r.origin_city, r.destination_city, 
                                 s.date, s.departure_time, s.arrival_time, 
                                 (s.total_seats - s.available_seats) as booked_seats, s.total_seats,
                                 b.bus_number, bc.company_name
                                 FROM schedules s 
                                 JOIN routes r ON s.route_id = r.id 
                                 JOIN buses b ON s.bus_id = b.id 
                                 JOIN bus_companies bc ON b.company_id = bc.id 
                                 WHERE s.date >= ? AND s.status = 'active' 
                                 ORDER BY s.date, s.departure_time 
                                 LIMIT 20");
            $stmt->execute([$today]);
        } else {
            // User view - personal upcoming trips
            $userId = getCurrentUserId();
            $stmt = $db->prepare("SELECT bk.id as booking_id, r.route_name, r.origin_city, r.destination_city, 
                                 bk.travel_date, s.departure_time, s.arrival_time, 
                                 bk.selected_seats, bk.total_amount, bk.status,
                                 b.bus_number, bc.company_name
                                 FROM bookings bk 
                                 JOIN routes r ON bk.route_id = r.id 
                                 JOIN schedules s ON bk.schedule_id = s.id 
                                 JOIN buses b ON s.bus_id = b.id 
                                 JOIN bus_companies bc ON b.company_id = bc.id 
                                 WHERE bk.user_id = ? AND bk.travel_date >= ? AND bk.status IN ('confirmed', 'pending') 
                                 ORDER BY bk.travel_date, s.departure_time 
                                 LIMIT 10");
            $stmt->execute([$userId, $today]);
        }
        
        $upcomingTrips = $stmt->fetchAll();
        
        handleSuccess($upcomingTrips, 'Upcoming trips retrieved successfully');
        
    } catch (Exception $e) {
        error_log("Upcoming trips error: " . $e->getMessage());
        handleError('Failed to retrieve upcoming trips', 500);
    }
}

/**
 * Get system alerts
 */
function handleGetSystemAlerts() {
    if (!isLoggedIn()) {
        handleError('Authentication required', 401);
    }
    
    if (!isAdmin()) {
        handleError('Admin access required', 403);
    }
    
    try {
        $db = getDB();
        
        $alerts = [];
        
        // Check for system issues with enhanced detection
        
        // 1. Buses needing maintenance (critical if > 30% of fleet)
        $stmt = $db->prepare("SELECT 
                             COUNT(*) as total_buses,
                             SUM(CASE WHEN status = 'maintenance' THEN 1 ELSE 0 END) as maintenance_buses,
                             SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active_buses
                             FROM buses");
        $stmt->execute();
        $busStatus = $stmt->fetch();
        
        $maintenancePercentage = $busStatus['total_buses'] > 0 ? 
            ($busStatus['maintenance_buses'] / $busStatus['total_buses']) * 100 : 0;
        
        if ($busStatus['maintenance_buses'] > 0) {
            $level = $maintenancePercentage > 30 ? 'error' : 'warning';
            $alerts[] = [
                'id' => 'maintenance_buses',
                'level' => $level,
                'title' => 'Buses Under Maintenance',
                'message' => $busStatus['maintenance_buses'] . ' buses (' . round($maintenancePercentage, 1) . '% of fleet) are currently under maintenance',
                'action' => 'View Buses',
                'url' => 'admin-buses-list.html',
                'created_at' => date('Y-m-d H:i:s')
            ];
        }
        
        // 2. Routes with no active schedules (critical business issue)
        $stmt = $db->prepare("SELECT r.id, r.route_id, 
                             oc.name as origin_city, dc.name as destination_city
                             FROM routes r 
                             LEFT JOIN cities oc ON r.origin_city_id = oc.id
                             LEFT JOIN cities dc ON r.destination_city_id = dc.id
                             LEFT JOIN schedules s ON r.id = s.route_id AND s.is_active = 1 AND s.effective_from <= CURDATE() AND (s.effective_until IS NULL OR s.effective_until >= CURDATE())
                             WHERE r.is_active = 1 AND s.id IS NULL");
        $stmt->execute();
        $routesWithoutSchedules = $stmt->fetchAll();
        
        if (count($routesWithoutSchedules) > 0) {
            $alerts[] = [
                'id' => 'routes_no_schedules',
                'level' => 'error',
                'title' => 'Routes Without Schedules',
                'message' => count($routesWithoutSchedules) . ' active routes have no upcoming schedules. Revenue loss possible.',
                'action' => 'Add Schedules',
                'url' => 'admin-schedules-list.html',
                'created_at' => date('Y-m-d H:i:s')
            ];
        }
        
        // 3. High cancellation rate (business health indicator)
        $yesterday = date('Y-m-d', strtotime('-1 day'));
        $stmt = $db->prepare("SELECT 
                             COUNT(*) as total_bookings,
                             SUM(CASE WHEN booking_status = 'cancelled' THEN 1 ELSE 0 END) as cancelled_bookings
                             FROM bookings WHERE DATE(created_at) >= ?");
        $stmt->execute([$yesterday]);
        $cancellationData = $stmt->fetch();
        
        $cancellationRate = $cancellationData['total_bookings'] > 0 ? 
            ($cancellationData['cancelled_bookings'] / $cancellationData['total_bookings']) * 100 : 0;
        
        if ($cancellationRate > 15) { // Alert if > 15% cancellation rate
            $level = $cancellationRate > 25 ? 'error' : 'warning';
            $alerts[] = [
                'id' => 'high_cancellation',
                'level' => $level,
                'title' => 'High Cancellation Rate',
                'message' => sprintf('%.1f%% cancellation rate in the last 24 hours (%d of %d bookings)', 
                    $cancellationRate, $cancellationData['cancelled_bookings'], $cancellationData['total_bookings']),
                'action' => 'View Bookings',
                'url' => 'admin-bookings-all.html',
                'created_at' => date('Y-m-d H:i:s')
            ];
        }
        
        // 4. Low seat availability (revenue opportunity) - Note: schedules table doesn't have available_seats
        // We'll check for schedules that might be overbooked based on bookings vs bus capacity
        $stmt = $db->prepare("SELECT COUNT(DISTINCT s.id) as count 
                             FROM schedules s
                             JOIN buses b ON s.bus_id = b.id
                             LEFT JOIN bookings bk ON s.id = bk.schedule_id AND bk.booking_status = 'confirmed'
                             WHERE s.effective_from <= DATE_ADD(CURDATE(), INTERVAL 7 DAY) 
                             AND (s.effective_until IS NULL OR s.effective_until >= CURDATE())
                             AND s.is_active = 1
                             GROUP BY s.id
                             HAVING (b.total_seats - COUNT(bk.id)) < 5");
        $stmt->execute();
        $lowAvailability = $stmt->fetch();
        
        if ($lowAvailability['count'] > 3) {
            $alerts[] = [
                'id' => 'low_availability',
                'level' => 'warning',
                'title' => 'Low Seat Availability',
                'message' => $lowAvailability['count'] . ' trips in the next 7 days have less than 5 seats available. Consider adding more buses.',
                'action' => 'View Schedules',
                'url' => 'admin-schedules-list.html',
                'created_at' => date('Y-m-d H:i:s')
            ];
        }
        
        // 5. Revenue alerts (business performance)
        $stmt = $db->prepare("SELECT COALESCE(SUM(total_amount), 0) as today_revenue FROM bookings WHERE DATE(created_at) = ?");
        $stmt->execute([$today]);
        $todayRevenue = $stmt->fetch();
        
        $stmt = $db->prepare("SELECT COALESCE(AVG(daily_revenue), 0) as avg_revenue FROM (
                             SELECT DATE(created_at) as date, SUM(total_amount) as daily_revenue 
                             FROM bookings 
                             WHERE DATE(created_at) >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
                             AND DATE(created_at) < CURDATE()
                             GROUP BY DATE(created_at)
                             ) as daily_stats");
        $stmt->execute();
        $avgRevenue = $stmt->fetch();
        
        if ($avgRevenue['avg_revenue'] > 0 && $todayRevenue['today_revenue'] < ($avgRevenue['avg_revenue'] * 0.5)) {
            $alerts[] = [
                'id' => 'low_revenue',
                'level' => 'warning',
                'title' => 'Low Revenue Alert',
                'message' => sprintf('Today\'s revenue (ETB %s) is significantly below 7-day average (ETB %s)', 
                    number_format($todayRevenue['today_revenue']), number_format($avgRevenue['avg_revenue'])),
                'action' => 'View Analytics',
                'url' => 'admin-dashboard.html',
                'created_at' => date('Y-m-d H:i:s')
            ];
        }
        
        // 6. System health check
        if (empty($alerts)) {
            $alerts[] = [
                'id' => 'system_healthy',
                'level' => 'info',
                'title' => 'All Systems Operational',
                'message' => 'No critical issues detected. System is running smoothly.',
                'action' => 'View Dashboard',
                'url' => 'admin-dashboard.html',
                'created_at' => date('Y-m-d H:i:s')
            ];
        }
        
        handleSuccess($alerts, 'System alerts retrieved successfully');
        
    } catch (Exception $e) {
        error_log("System alerts error: " . $e->getMessage());
        handleError('Failed to retrieve system alerts', 500);
    }
}
?>