<?php
session_start();
header('Content-Type: text/html; charset=utf-8');

echo "<h1>🔍 Database Booking Check</h1>";
echo "<style>
    body { font-family: Arial, sans-serif; max-width: 1000px; margin: 0 auto; padding: 20px; }
    .section { background: #f8f9fa; padding: 15px; margin: 15px 0; border-radius: 5px; border-left: 4px solid #007cba; }
    .success { border-left-color: #28a745; background: #d4edda; }
    .error { border-left-color: #dc3545; background: #f8d7da; }
    .warning { border-left-color: #ffc107; background: #fff3cd; }
    table { width: 100%; border-collapse: collapse; margin: 10px 0; }
    th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
    th { background: #f2f2f2; }
    pre { background: #f5f5f5; padding: 10px; border-radius: 3px; overflow-x: auto; }
</style>";

try {
    require_once 'includes/functions.php';
    
    echo "<div class='section success'><strong>✅ Database Connection:</strong> Successfully connected</div>";
    
    // Check session
    if (isset($_SESSION['user_id'])) {
        echo "<div class='section success'><strong>✅ Session:</strong> User logged in as: " . $_SESSION['user_id'] . "</div>";
        $currentUserId = $_SESSION['user_id'];
    } else {
        echo "<div class='section error'><strong>❌ Session:</strong> No user session found. Please <a href='login.html'>login first</a>.</div>";
        exit;
    }
    
    $db = getDB();
    
    // 1. Check total bookings in database
    echo "<h2>📊 Database Statistics</h2>";
    
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM bookings");
    $stmt->execute();
    $totalBookings = $stmt->fetch()['total'];
    
    echo "<div class='section'><strong>Total bookings in database:</strong> $totalBookings</div>";
    
    // 2. Check bookings for current user
    $stmt = $db->prepare("
        SELECT COUNT(*) as user_bookings 
        FROM bookings b 
        WHERE b.user_id = (SELECT id FROM users WHERE user_id = ?)
    ");
    $stmt->execute([$currentUserId]);
    $userBookings = $stmt->fetch()['user_bookings'];
    
    echo "<div class='section'><strong>Bookings for current user ($currentUserId):</strong> $userBookings</div>";
    
    // 3. Show user ID mapping
    echo "<h2>👤 User ID Mapping</h2>";
    
    $stmt = $db->prepare("SELECT id, user_id, full_name, email FROM users WHERE user_id = ?");
    $stmt->execute([$currentUserId]);
    $user = $stmt->fetch();
    
    if ($user) {
        echo "<div class='section success'>";
        echo "<strong>✅ User found:</strong><br>";
        echo "• Database ID: " . $user['id'] . "<br>";
        echo "• User ID: " . $user['user_id'] . "<br>";
        echo "• Name: " . $user['full_name'] . "<br>";
        echo "• Email: " . $user['email'] . "<br>";
        echo "</div>";
        
        $dbUserId = $user['id'];
    } else {
        echo "<div class='section error'><strong>❌ User not found in database!</strong></div>";
        exit;
    }
    
    // 4. Show all users with bookings
    echo "<h2>📋 Users with Bookings</h2>";
    
    $stmt = $db->prepare("
        SELECT u.user_id, u.full_name, u.email, COUNT(b.id) as booking_count
        FROM users u
        LEFT JOIN bookings b ON b.user_id = u.id
        GROUP BY u.id
        HAVING booking_count > 0
        ORDER BY booking_count DESC
    ");
    $stmt->execute();
    $usersWithBookings = $stmt->fetchAll();
    
    if (count($usersWithBookings) > 0) {
        echo "<table>";
        echo "<tr><th>User ID</th><th>Name</th><th>Email</th><th>Bookings</th><th>Current User?</th></tr>";
        foreach ($usersWithBookings as $userWithBooking) {
            $isCurrent = $userWithBooking['user_id'] === $currentUserId ? '✅ YES' : '';
            echo "<tr>";
            echo "<td>" . htmlspecialchars($userWithBooking['user_id']) . "</td>";
            echo "<td>" . htmlspecialchars($userWithBooking['full_name']) . "</td>";
            echo "<td>" . htmlspecialchars($userWithBooking['email']) . "</td>";
            echo "<td>" . $userWithBooking['booking_count'] . "</td>";
            echo "<td><strong>$isCurrent</strong></td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<div class='section warning'><strong>⚠️ No users have bookings</strong></div>";
    }
    
    // 5. Show current user's bookings in detail
    if ($userBookings > 0) {
        echo "<h2>🎫 Current User's Bookings</h2>";
        
        $stmt = $db->prepare("
            SELECT 
                b.booking_id,
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
                b.booking_date,
                b.created_at
            FROM bookings b
            WHERE b.user_id = ?
            ORDER BY b.created_at DESC
        ");
        $stmt->execute([$dbUserId]);
        $bookings = $stmt->fetchAll();
        
        foreach ($bookings as $index => $booking) {
            echo "<div class='section'>";
            echo "<h3>Booking " . ($index + 1) . ": " . $booking['booking_id'] . "</h3>";
            echo "<table>";
            echo "<tr><th>Field</th><th>Value</th></tr>";
            
            foreach ($booking as $key => $value) {
                if ($key === 'selected_seats' || $key === 'passenger_details') {
                    // Try to decode JSON
                    $decoded = json_decode($value, true);
                    if ($decoded) {
                        $value = "<pre>" . json_encode($decoded, JSON_PRETTY_PRINT) . "</pre>";
                    }
                }
                echo "<tr><td><strong>$key</strong></td><td>$value</td></tr>";
            }
            echo "</table>";
            echo "</div>";
        }
        
        // 6. Test the API call that my-tickets.html makes
        echo "<h2>🔗 API Test</h2>";
        
        // Simulate the API call
        $_GET['action'] = 'get_user_bookings';
        
        ob_start();
        include 'api/booking_clean.php';
        $apiOutput = ob_get_clean();
        
        echo "<div class='section'>";
        echo "<strong>API Response (what my-tickets.html receives):</strong><br>";
        echo "<pre>" . htmlspecialchars($apiOutput) . "</pre>";
        echo "</div>";
        
    } else {
        echo "<div class='section warning'>";
        echo "<strong>⚠️ No bookings found for current user</strong><br>";
        echo "This explains why my-tickets.html shows no tickets.<br><br>";
        echo "<strong>Possible causes:</strong><br>";
        echo "• You're logged in with a different account than the one that made bookings<br>";
        echo "• The bookings were created with a different user_id<br>";
        echo "• There's a mismatch in the user ID mapping<br>";
        echo "</div>";
    }
    
} catch (Exception $e) {
    echo "<div class='section error'>";
    echo "<strong>❌ Error:</strong> " . $e->getMessage() . "<br>";
    echo "<strong>File:</strong> " . $e->getFile() . "<br>";
    echo "<strong>Line:</strong> " . $e->getLine() . "<br>";
    echo "</div>";
}

echo "<hr>";
echo "<p><a href='my-tickets.html'>→ Go to My Tickets Page</a></p>";
echo "<p><a href='debug_tickets_complete.html'>→ Go to Complete Debug Tool</a></p>";
?>