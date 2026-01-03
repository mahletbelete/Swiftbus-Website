<?php
/**
 * Simple script to check if tickets are being stored in the database
 */

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Start session
session_start();

// Include functions
require_once 'includes/functions.php';

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Check Database Tickets - SwiftBus</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
        .container { max-width: 1000px; margin: 0 auto; background: white; padding: 20px; border-radius: 10px; }
        .section { margin: 20px 0; padding: 15px; border: 1px solid #ddd; border-radius: 8px; }
        .success { background: #d4edda; color: #155724; border-color: #c3e6cb; }
        .error { background: #f8d7da; color: #721c24; border-color: #f5c6cb; }
        .info { background: #d1ecf1; color: #0c5460; border-color: #bee5eb; }
        .warning { background: #fff3cd; color: #856404; border-color: #ffeaa7; }
        pre { background: #f8f9fa; padding: 10px; border-radius: 5px; overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; margin: 10px 0; }
        th, td { padding: 8px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background-color: #f8f9fa; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔍 Database Tickets Check</h1>
        <p>This script checks if tickets are being stored in the database and helps debug the my-tickets display issue.</p>

        <?php
        try {
            // Check database connection
            echo '<div class="section info">';
            echo '<h3>📡 Database Connection</h3>';
            
            $db = getDB();
            echo '<p>✅ Database connected successfully</p>';
            echo '</div>';

            // Check authentication
            echo '<div class="section">';
            echo '<h3>🔐 Authentication Status</h3>';
            
            if (isLoggedIn()) {
                $currentUser = getCurrentUser();
                $userId = getCurrentUserId();
                
                echo '<div class="success">';
                echo '<p>✅ User is logged in</p>';
                echo '<p><strong>User ID (session):</strong> ' . htmlspecialchars($userId) . '</p>';
                echo '<p><strong>Email:</strong> ' . htmlspecialchars($currentUser['email'] ?? 'N/A') . '</p>';
                echo '<p><strong>Name:</strong> ' . htmlspecialchars($currentUser['full_name'] ?? 'N/A') . '</p>';
                echo '<p><strong>Role:</strong> ' . htmlspecialchars($currentUser['role'] ?? 'N/A') . '</p>';
                echo '</div>';
                
                // Get user's numeric ID from database
                $stmt = $db->prepare("SELECT id, user_id, email, full_name FROM users WHERE user_id = ?");
                $stmt->execute([$userId]);
                $userRecord = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($userRecord) {
                    echo '<div class="info">';
                    echo '<p><strong>Database User Record:</strong></p>';
                    echo '<pre>' . print_r($userRecord, true) . '</pre>';
                    echo '</div>';
                    
                    $numericUserId = $userRecord['id'];
                } else {
                    echo '<div class="error">';
                    echo '<p>❌ User record not found in database for user_id: ' . htmlspecialchars($userId) . '</p>';
                    echo '</div>';
                    $numericUserId = null;
                }
            } else {
                echo '<div class="warning">';
                echo '<p>⚠️ User is not logged in</p>';
                echo '<p>Please <a href="login.html">login</a> to check your tickets</p>';
                echo '</div>';
                $numericUserId = null;
            }
            echo '</div>';

            // Check all bookings in database
            echo '<div class="section">';
            echo '<h3>📊 All Bookings in Database</h3>';
            
            $stmt = $db->prepare("
                SELECT 
                    b.id,
                    b.booking_id,
                    b.user_id,
                    u.user_id as session_user_id,
                    u.email,
                    u.full_name,
                    b.from_city,
                    b.to_city,
                    b.travel_date,
                    b.booking_status,
                    b.payment_status,
                    b.total_amount,
                    b.created_at
                FROM bookings b
                LEFT JOIN users u ON u.id = b.user_id
                ORDER BY b.created_at DESC
                LIMIT 20
            ");
            $stmt->execute();
            $allBookings = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if ($allBookings) {
                echo '<div class="info">';
                echo '<p>Found ' . count($allBookings) . ' bookings in database (showing last 20):</p>';
                echo '<table>';
                echo '<tr><th>Booking ID</th><th>User ID (DB)</th><th>Session User ID</th><th>Email</th><th>Route</th><th>Date</th><th>Status</th><th>Amount</th><th>Created</th></tr>';
                
                foreach ($allBookings as $booking) {
                    $isCurrentUser = ($numericUserId && $booking['user_id'] == $numericUserId) ? ' style="background-color: #d4edda;"' : '';
                    echo '<tr' . $isCurrentUser . '>';
                    echo '<td>' . htmlspecialchars($booking['booking_id']) . '</td>';
                    echo '<td>' . htmlspecialchars($booking['user_id']) . '</td>';
                    echo '<td>' . htmlspecialchars($booking['session_user_id']) . '</td>';
                    echo '<td>' . htmlspecialchars($booking['email']) . '</td>';
                    echo '<td>' . htmlspecialchars($booking['from_city'] . ' → ' . $booking['to_city']) . '</td>';
                    echo '<td>' . htmlspecialchars($booking['travel_date']) . '</td>';
                    echo '<td>' . htmlspecialchars($booking['booking_status'] . '/' . $booking['payment_status']) . '</td>';
                    echo '<td>ETB ' . htmlspecialchars($booking['total_amount']) . '</td>';
                    echo '<td>' . htmlspecialchars($booking['created_at']) . '</td>';
                    echo '</tr>';
                }
                echo '</table>';
                echo '<p><em>Green rows are bookings for the current logged-in user</em></p>';
                echo '</div>';
            } else {
                echo '<div class="warning">';
                echo '<p>⚠️ No bookings found in database</p>';
                echo '</div>';
            }
            echo '</div>';

            // Check current user's bookings specifically
            if ($numericUserId) {
                echo '<div class="section">';
                echo '<h3>🎫 Current User\'s Bookings</h3>';
                
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
                    WHERE b.user_id = ?
                    ORDER BY b.created_at DESC
                ");
                $stmt->execute([$numericUserId]);
                $userBookings = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                if ($userBookings) {
                    echo '<div class="success">';
                    echo '<p>✅ Found ' . count($userBookings) . ' bookings for current user:</p>';
                    
                    foreach ($userBookings as $booking) {
                        echo '<div style="border: 1px solid #ccc; margin: 10px 0; padding: 10px; border-radius: 5px;">';
                        echo '<h4>Booking: ' . htmlspecialchars($booking['booking_id']) . '</h4>';
                        echo '<p><strong>Route:</strong> ' . htmlspecialchars($booking['from_city'] . ' → ' . $booking['to_city']) . '</p>';
                        echo '<p><strong>Date:</strong> ' . htmlspecialchars($booking['departure_date']) . ' at ' . htmlspecialchars($booking['departure_time']) . '</p>';
                        echo '<p><strong>Status:</strong> ' . htmlspecialchars($booking['booking_status']) . ' / ' . htmlspecialchars($booking['payment_status']) . '</p>';
                        echo '<p><strong>Amount:</strong> ETB ' . htmlspecialchars($booking['total_amount']) . '</p>';
                        echo '<p><strong>Bus:</strong> ' . htmlspecialchars($booking['bus_company']) . ' (' . htmlspecialchars($booking['bus_type']) . ')</p>';
                        echo '<p><strong>Passengers:</strong> ' . htmlspecialchars($booking['passenger_count']) . '</p>';
                        echo '<p><strong>Seats:</strong> ' . htmlspecialchars($booking['selected_seats'] ?: 'None') . '</p>';
                        echo '<p><strong>Created:</strong> ' . htmlspecialchars($booking['created_at']) . '</p>';
                        echo '</div>';
                    }
                    echo '</div>';
                } else {
                    echo '<div class="warning">';
                    echo '<p>⚠️ No bookings found for current user (user_id: ' . htmlspecialchars($numericUserId) . ')</p>';
                    echo '</div>';
                }
                echo '</div>';

                // Test the exact API query
                echo '<div class="section">';
                echo '<h3>🔍 API Query Test</h3>';
                
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
                        b.created_at,
                        bc.name as company_name,
                        bc.rating as company_rating
                    FROM bookings b
                    LEFT JOIN bus_companies bc ON bc.company_id = b.bus_company
                    WHERE b.user_id = (SELECT id FROM users WHERE user_id = ?)
                    ORDER BY b.created_at DESC
                    LIMIT 50
                ");
                $stmt->execute([$userId]);
                $apiResults = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                if ($apiResults) {
                    echo '<div class="success">';
                    echo '<p>✅ API query returned ' . count($apiResults) . ' results</p>';
                    echo '<p>This is exactly what the getUserBookings API should return.</p>';
                    echo '</div>';
                } else {
                    echo '<div class="error">';
                    echo '<p>❌ API query returned no results</p>';
                    echo '<p>Query used: <code>WHERE b.user_id = (SELECT id FROM users WHERE user_id = \'' . htmlspecialchars($userId) . '\')</code></p>';
                    echo '</div>';
                }
                echo '</div>';
            }

        } catch (Exception $e) {
            echo '<div class="section error">';
            echo '<h3>❌ Error</h3>';
            echo '<p>Error: ' . htmlspecialchars($e->getMessage()) . '</p>';
            echo '<pre>' . htmlspecialchars($e->getTraceAsString()) . '</pre>';
            echo '</div>';
        }
        ?>

        <div class="section info">
            <h3>🔧 Next Steps</h3>
            <p>Based on the results above:</p>
            <ul>
                <li>If you see bookings in "All Bookings" but not in "Current User's Bookings", there's a user ID mismatch</li>
                <li>If you see bookings in "Current User's Bookings" but the API query returns nothing, there's an API issue</li>
                <li>If everything shows bookings, the issue is in the JavaScript processing</li>
                <li>Use the browser console on my-tickets.html to see detailed debug logs</li>
            </ul>
        </div>
    </div>
</body>
</html>