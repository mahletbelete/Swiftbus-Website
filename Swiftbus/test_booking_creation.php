<?php
session_start();
header('Content-Type: text/html; charset=utf-8');

echo "<h1>🔍 Test Booking Creation Process</h1>";
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
    button { background: #007cba; color: white; border: none; padding: 10px 20px; border-radius: 5px; cursor: pointer; margin: 5px; }
</style>";

try {
    require_once 'includes/functions.php';
    
    // Check session
    if (!isset($_SESSION['user_id'])) {
        echo "<div class='section error'><strong>❌ No Session:</strong> Please <a href='login.html'>login first</a>.</div>";
        exit;
    }
    
    echo "<div class='section success'><strong>✅ Session Active:</strong> " . $_SESSION['user_id'] . "</div>";
    
    $db = getDB();
    
    // Get current user details
    $stmt = $db->prepare("SELECT id, user_id, full_name, email FROM users WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();
    
    if (!$user) {
        echo "<div class='section error'><strong>❌ User Not Found:</strong> Session user not in database!</div>";
        exit;
    }
    
    echo "<div class='section success'>";
    echo "<strong>✅ Current User:</strong><br>";
    echo "• Database ID: " . $user['id'] . "<br>";
    echo "• User ID: " . $user['user_id'] . "<br>";
    echo "• Name: " . $user['full_name'] . "<br>";
    echo "• Email: " . $user['email'] . "<br>";
    echo "</div>";
    
    $currentDbUserId = $user['id'];
    
    // Check recent bookings
    echo "<h2>📋 Recent Bookings Analysis</h2>";
    
    // All bookings in last 24 hours
    $stmt = $db->prepare("
        SELECT 
            b.booking_id,
            b.user_id,
            u.user_id as session_user_id,
            u.full_name,
            b.from_city,
            b.to_city,
            b.booking_status,
            b.payment_status,
            b.created_at,
            CASE WHEN b.user_id = ? THEN 'YES' ELSE 'NO' END as is_current_user
        FROM bookings b
        LEFT JOIN users u ON u.id = b.user_id
        WHERE b.created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
        ORDER BY b.created_at DESC
        LIMIT 10
    ");
    $stmt->execute([$currentDbUserId]);
    $recentBookings = $stmt->fetchAll();
    
    if (count($recentBookings) > 0) {
        echo "<div class='section'>";
        echo "<strong>Recent Bookings (Last 24 Hours):</strong>";
        echo "<table>";
        echo "<tr><th>Booking ID</th><th>User</th><th>Route</th><th>Status</th><th>Payment</th><th>Created</th><th>Your Booking?</th></tr>";
        
        foreach ($recentBookings as $booking) {
            $rowClass = $booking['is_current_user'] === 'YES' ? 'style="background: #d4edda;"' : '';
            echo "<tr $rowClass>";
            echo "<td>" . htmlspecialchars($booking['booking_id']) . "</td>";
            echo "<td>" . htmlspecialchars($booking['full_name']) . " (" . htmlspecialchars($booking['session_user_id']) . ")</td>";
            echo "<td>" . htmlspecialchars($booking['from_city']) . " → " . htmlspecialchars($booking['to_city']) . "</td>";
            echo "<td>" . htmlspecialchars($booking['booking_status']) . "</td>";
            echo "<td>" . htmlspecialchars($booking['payment_status']) . "</td>";
            echo "<td>" . htmlspecialchars($booking['created_at']) . "</td>";
            echo "<td><strong>" . $booking['is_current_user'] . "</strong></td>";
            echo "</tr>";
        }
        echo "</table>";
        echo "</div>";
        
        // Count current user's bookings
        $userBookingsCount = count(array_filter($recentBookings, function($b) { return $b['is_current_user'] === 'YES'; }));
        
        if ($userBookingsCount > 0) {
            echo "<div class='section success'>";
            echo "<strong>✅ Found Your Bookings:</strong> $userBookingsCount booking(s) in the last 24 hours belong to you.";
            echo "</div>";
        } else {
            echo "<div class='section warning'>";
            echo "<strong>⚠️ No Your Bookings:</strong> None of the recent bookings belong to your current user account.";
            echo "</div>";
        }
        
    } else {
        echo "<div class='section warning'>";
        echo "<strong>⚠️ No Recent Bookings:</strong> No bookings created in the last 24 hours.";
        echo "</div>";
    }
    
    // Check all bookings for current user
    $stmt = $db->prepare("
        SELECT COUNT(*) as total_bookings
        FROM bookings 
        WHERE user_id = ?
    ");
    $stmt->execute([$currentDbUserId]);
    $totalUserBookings = $stmt->fetch()['total_bookings'];
    
    echo "<div class='section'>";
    echo "<strong>Total Bookings for Current User:</strong> $totalUserBookings";
    echo "</div>";
    
    // Test the getUserBookings API
    echo "<h2>🔗 API Test</h2>";
    
    echo "<div class='section'>";
    echo "<strong>Testing getUserBookings API...</strong><br>";
    
    // Simulate API call
    $_GET['action'] = 'get_user_bookings';
    
    ob_start();
    try {
        include 'api/booking_clean.php';
        $apiOutput = ob_get_clean();
        
        echo "<strong>API Response:</strong><br>";
        echo "<pre>" . htmlspecialchars($apiOutput) . "</pre>";
        
        // Try to parse JSON
        $apiData = json_decode($apiOutput, true);
        if ($apiData) {
            if ($apiData['success']) {
                $apiBookingCount = count($apiData['data']['bookings'] ?? []);
                echo "<div style='background: #d4edda; padding: 10px; border-radius: 5px; margin-top: 10px;'>";
                echo "<strong>✅ API Success:</strong> Found $apiBookingCount booking(s) via API";
                echo "</div>";
            } else {
                echo "<div style='background: #f8d7da; padding: 10px; border-radius: 5px; margin-top: 10px;'>";
                echo "<strong>❌ API Error:</strong> " . ($apiData['message'] ?? 'Unknown error');
                echo "</div>";
            }
        } else {
            echo "<div style='background: #f8d7da; padding: 10px; border-radius: 5px; margin-top: 10px;'>";
            echo "<strong>❌ JSON Parse Error:</strong> API returned invalid JSON";
            echo "</div>";
        }
        
    } catch (Exception $e) {
        ob_end_clean();
        echo "<div style='background: #f8d7da; padding: 10px; border-radius: 5px;'>";
        echo "<strong>❌ API Exception:</strong> " . $e->getMessage();
        echo "</div>";
    }
    echo "</div>";
    
    // Provide next steps
    echo "<h2>🎯 Next Steps</h2>";
    
    if ($totalUserBookings > 0) {
        echo "<div class='section success'>";
        echo "<strong>✅ Bookings Exist:</strong> You have $totalUserBookings booking(s) in the database.<br>";
        echo "<strong>Issue:</strong> The my-tickets.html page is not displaying them correctly.<br>";
        echo "<strong>Solution:</strong> Check the API response above and debug the frontend JavaScript.";
        echo "</div>";
    } else {
        echo "<div class='section warning'>";
        echo "<strong>⚠️ No Bookings:</strong> You have no bookings in the database.<br>";
        echo "<strong>Issue:</strong> Bookings are not being created or are being created under a different user.<br>";
        echo "<strong>Solution:</strong> Debug the booking creation process.";
        echo "</div>";
    }
    
} catch (Exception $e) {
    echo "<div class='section error'>";
    echo "<strong>❌ Error:</strong> " . $e->getMessage();
    echo "</div>";
}

echo "<hr>";
echo "<p><a href='debug_booking_flow.html'>→ Debug Booking Flow</a></p>";
echo "<p><a href='my-tickets.html'>→ Go to My Tickets</a></p>";
echo "<p><a href='book-ticket.html'>→ Book a Test Ticket</a></p>";
?>