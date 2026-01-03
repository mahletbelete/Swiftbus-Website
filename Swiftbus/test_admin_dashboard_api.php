<?php
// Test script to verify admin dashboard API
require_once 'includes/config.php';

try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "🔗 Database connection: ✅ SUCCESS\n\n";
    
    // Test each query that the API uses
    echo "📊 Testing Dashboard Queries:\n";
    echo "=" . str_repeat("=", 50) . "\n";
    
    // 1. Test booking queries
    echo "1. BOOKING STATISTICS:\n";
    
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM bookings");
    $totalBookings = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    echo "   Total bookings: $totalBookings\n";
    
    $stmt = $pdo->query("
        SELECT 
            COUNT(*) as count,
            COALESCE(SUM(CASE WHEN payment_status = 'paid' THEN total_amount ELSE 0 END), 0) as revenue
        FROM bookings 
        WHERE DATE(created_at) = CURDATE()
    ");
    $todayData = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "   Today's bookings: {$todayData['count']}, Revenue: ETB {$todayData['revenue']}\n";
    
    $stmt = $pdo->query("
        SELECT 
            booking_status,
            COUNT(*) as count
        FROM bookings 
        GROUP BY booking_status
    ");
    $statusData = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "   Booking status breakdown:\n";
    foreach ($statusData as $status) {
        echo "     - {$status['booking_status']}: {$status['count']}\n";
    }
    
    // 2. Test bus queries
    echo "\n2. BUS STATISTICS:\n";
    $stmt = $pdo->query("
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active,
            SUM(CASE WHEN status = 'maintenance' THEN 1 ELSE 0 END) as maintenance
        FROM buses
    ");
    $busData = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "   Total buses: {$busData['total']}\n";
    echo "   Active buses: {$busData['active']}\n";
    echo "   Maintenance buses: {$busData['maintenance']}\n";
    
    // 3. Test route queries
    echo "\n3. ROUTE STATISTICS:\n";
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM routes");
    $totalRoutes = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    echo "   Total routes: $totalRoutes\n";
    
    $stmt = $pdo->query("SELECT COUNT(*) as active FROM routes WHERE is_active = 1");
    $activeRoutes = $stmt->fetch(PDO::FETCH_ASSOC)['active'];
    echo "   Active routes: $activeRoutes\n";
    
    // 4. Test user queries
    echo "\n4. USER STATISTICS:\n";
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM users WHERE role = 'user'");
    $totalUsers = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    echo "   Total users (excluding admins): $totalUsers\n";
    
    $stmt = $pdo->query("SELECT COUNT(*) as active FROM users WHERE role = 'user' AND is_active = 1");
    $activeUsers = $stmt->fetch(PDO::FETCH_ASSOC)['active'];
    echo "   Active users: $activeUsers\n";
    
    $stmt = $pdo->query("
        SELECT COUNT(*) as new_today 
        FROM users 
        WHERE role = 'user' AND DATE(created_at) = CURDATE()
    ");
    $newUsersToday = $stmt->fetch(PDO::FETCH_ASSOC)['new_today'];
    echo "   New users today: $newUsersToday\n";
    
    // 5. Test admin count
    echo "\n5. ADMIN VERIFICATION:\n";
    $stmt = $pdo->query("SELECT COUNT(*) as admin_count FROM users WHERE role = 'admin'");
    $adminCount = $stmt->fetch(PDO::FETCH_ASSOC)['admin_count'];
    echo "   Total admin users: $adminCount (should be 6)\n";
    
    if ($adminCount == 6) {
        echo "   ✅ Admin count is correct\n";
    } else {
        echo "   ⚠️ Admin count is not 6 as expected\n";
    }
    
    echo "\n" . str_repeat("=", 60) . "\n";
    echo "🎉 All database queries executed successfully!\n";
    echo "📊 The admin dashboard should now display accurate data.\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
?>