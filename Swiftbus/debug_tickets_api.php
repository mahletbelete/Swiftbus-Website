<?php
/**
 * Debug script to test the tickets API directly
 */
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
    <title>Debug Tickets API - SwiftBus</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
        .container { max-width: 1000px; margin: 0 auto; background: white; padding: 20px; border-radius: 10px; }
        .section { margin: 20px 0; padding: 15px; border: 1px solid #ddd; border-radius: 8px; }
        .success { background: #d4edda; color: #155724; border-color: #c3e6cb; }
        .error { background: #f8d7da; color: #721c24; border-color: #f5c6cb; }
        .info { background: #d1ecf1; color: #0c5460; border-color: #bee5eb; }
        .warning { background: #fff3cd; color: #856404; border-color: #ffeaa7; }
        pre { background: #f8f9fa; padding: 10px; border-radius: 5px; overflow-x: auto; font-size: 12px; max-height: 400px; overflow-y: auto; }
        .btn { padding: 10px 20px; margin: 5px; border: none; border-radius: 5px; cursor: pointer; text-decoration: none; display: inline-block; }
        .btn-primary { background: #007bff; color: white; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔍 Debug Tickets API</h1>
        <p>This script directly tests the getUserBookings API to identify issues.</p>

        <?php
        try {
            // Step 1: Check session
            echo '<div class="section info">';
            echo '<h3>🔐 Session Check</h3>';
            
            if (isLoggedIn()) {
                $userId = getCurrentUserId();
                $currentUser = getCurrentUser();
                
                echo '<div class="success">';
                echo '<p>✅ User is logged in</p>';
                echo '<p><strong>User ID:</strong> ' . htmlspecialchars($userId) . '</p>';
                echo '<p><strong>Email:</strong> ' . htmlspecialchars($currentUser['email'] ?? 'N/A') . '</p>';
                echo '</div>';
            } else {
                echo '<div class="error">';
                echo '<p>❌ User is not logged in</p>';
                echo '<p><a href="quick_login_test.html" class="btn btn-primary">Login First</a></p>';
                echo '</div>';
                echo '</div>';
                exit;
            }
            echo '</div>';

            // Step 2: Test database connection
            echo '<div class="section info">';
            echo '<h3>📡 Database Connection</h3>';
            $db = getDB();
            echo '<p>✅ Database connected successfully</p>';
            echo '</div>';

            // Step 3: Check if user exists in database
            echo '<div class="section info">';
            echo '<h3>👤 User Verification</h3>';
            
            $userCheck = $db->prepare("SELECT id, user_id, email, full_name FROM users WHERE user_id = ? AND is_active = 1");
            $userCheck->execute([$userId]);
            $userRecord = $userCheck->fetch(PDO::FETCH_ASSOC);
            
            if ($userRecord) {
                echo '<div class="success">';
                echo '<p>✅ User found in database</p>';
                echo '<pre>' . print_r($userRecord, true) . '</pre>';
                echo '</div>';
                $numericUserId = $userRecord['id'];
            } else {
                echo '<div class="error">';
                echo '<p>❌ User not found in database</p>';
                echo '</div>';
                exit;
            }
            echo '</div>';

            // Step 4: Check bookings directly
            echo '<div class="section info">';
            echo '<h3>🎫 Direct Database Query</h3>';
            
            $stmt = $db->prepare("
                SELECT 
                    booking_id,
                    bus_company,
                    bus_type,
                    from_city,
                    to_city,
                    travel_date,
                    departure_time,
                    passenger_count,
                    total_amount,
                    booking_status,
                    payment_status,
                    created_at
                FROM bookings 
                WHERE user_id = ?
                ORDER BY created_at DESC
                LIMIT 5
            ");
            $stmt->execute([$numericUserId]);
            $directBookings = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if ($directBookings) {
                echo '<div class="success">';
                echo '<p>✅ Found ' . count($directBookings) . ' bookings directly from database</p>';
                echo '<pre>' . print_r($directBookings, true) . '</pre>';
                echo '</div>';
            } else {
                echo '<div class="warning">';
                echo '<p>⚠️ No bookings found for user ID: ' . $numericUserId . '</p>';
                echo '</div>';
            }
            echo '</div>';

            // Step 5: Test the API function directly
            echo '<div class="section info">';
            echo '<h3>🔧 API Function Test</h3>';
            
            // Simulate the API call
            $_GET['action'] = 'get_user_bookings';
            $_SERVER['REQUEST_METHOD'] = 'GET';
            
            // Capture output
            ob_start();
            
            try {
                // Include the API file to test the function
                include 'api/booking_clean.php';
                $apiOutput = ob_get_clean();
                
                echo '<div class="success">';
                echo '<p>✅ API function executed</p>';
                echo '<p><strong>Raw Output:</strong></p>';
                echo '<pre>' . htmlspecialchars($apiOutput) . '</pre>';
                
                // Try to decode JSON
                $apiData = json_decode($apiOutput, true);
                if ($apiData) {
                    echo '<p><strong>Parsed JSON:</strong></p>';
                    echo '<pre>' . print_r($apiData, true) . '</pre>';
                } else {
                    echo '<p><strong>JSON Parse Error:</strong> ' . json_last_error_msg() . '</p>';
                }
                echo '</div>';
                
            } catch (Exception $e) {
                ob_end_clean();
                echo '<div class="error">';
                echo '<p>❌ API function failed</p>';
                echo '<p><strong>Error:</strong> ' . htmlspecialchars($e->getMessage()) . '</p>';
                echo '<p><strong>Stack Trace:</strong></p>';
                echo '<pre>' . htmlspecialchars($e->getTraceAsString()) . '</pre>';
                echo '</div>';
            }
            echo '</div>';

        } catch (Exception $e) {
            echo '<div class="section error">';
            echo '<h3>❌ Fatal Error</h3>';
            echo '<p>Error: ' . htmlspecialchars($e->getMessage()) . '</p>';
            echo '<pre>' . htmlspecialchars($e->getTraceAsString()) . '</pre>';
            echo '</div>';
        }
        ?>

        <div class="section info">
            <h3>🔧 Next Steps</h3>
            <p>If the API function test shows an error, check the PHP error logs for more details.</p>
            <p><a href="quick_login_test.html" class="btn btn-primary">Back to Login Test</a></p>
        </div>
    </div>
</body>
</html>