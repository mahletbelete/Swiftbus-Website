<?php
/**
 * Final Booking System Fix
 * This script will verify and fix all booking system components
 */
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();

echo "<h1>🔧 Final Booking System Fix</h1>";
echo "<style>
    body { font-family: Arial, sans-serif; max-width: 1000px; margin: 0 auto; padding: 20px; }
    .success { background: #d4edda; padding: 15px; border-radius: 5px; margin: 10px 0; border-left: 4px solid #28a745; }
    .error { background: #f8d7da; padding: 15px; border-radius: 5px; margin: 10px 0; border-left: 4px solid #dc3545; }
    .info { background: #d1ecf1; padding: 15px; border-radius: 5px; margin: 10px 0; border-left: 4px solid #17a2b8; }
    .warning { background: #fff3cd; padding: 15px; border-radius: 5px; margin: 10px 0; border-left: 4px solid #ffc107; }
    pre { background: #f5f5f5; padding: 10px; border-radius: 3px; overflow-x: auto; font-size: 12px; }
    .step { background: white; padding: 20px; margin: 15px 0; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
    .step h3 { color: #007cba; margin-top: 0; }
    button { background: #007cba; color: white; border: none; padding: 10px 20px; border-radius: 5px; cursor: pointer; margin: 5px; }
</style>";

// Check session
if (!isset($_SESSION['user_id'])) {
    echo "<div class='error'>❌ Please login first: <a href='login.html'>Login</a></div>";
    exit;
}

echo "<div class='success'>✅ Session Active: " . $_SESSION['user_id'] . "</div>";

try {
    require_once 'includes/functions.php';
    
    echo "<div class='step'>";
    echo "<h3>🔍 System Diagnosis</h3>";
    
    // Test database connection
    $db = getDB();
    echo "✅ Database connected<br>";
    
    // Test user lookup
    $user = getCurrentUser();
    if ($user) {
        echo "✅ User found: " . $user['full_name'] . " (" . $user['email'] . ")<br>";
    } else {
        echo "<div class='error'>❌ User not found in database</div>";
        exit;
    }
    
    // Check if old booking.php file exists (should be deleted)
    if (file_exists('api/booking.php')) {
        echo "⚠️ Old api/booking.php file still exists - this may cause conflicts<br>";
    } else {
        echo "✅ Old api/booking.php file removed<br>";
    }
    
    // Check if booking_clean.php exists
    if (file_exists('api/booking_clean.php')) {
        echo "✅ api/booking_clean.php exists<br>";
    } else {
        echo "❌ api/booking_clean.php missing<br>";
    }
    
    // Check required functions
    $functions = [
        'generateUniqueId' => 'Generate unique booking IDs',
        'generateQRData' => 'Generate QR codes for tickets',
        'createPaymentRecord' => 'Create payment records',
        'updatePaymentRecord' => 'Update payment status',
        'reserveSeatsForBooking' => 'Reserve seats for bookings',
        'releaseSeatsForBooking' => 'Release cancelled seats',
        'initializeSeatsForRoute' => 'Initialize seat availability',
        'logActivity' => 'Log user activities'
    ];
    
    foreach ($functions as $func => $description) {
        if (function_exists($func)) {
            echo "✅ $func() - $description<br>";
        } else {
            echo "❌ $func() missing - $description<br>";
        }
    }
    echo "</div>";
    
    echo "<div class='step'>";
    echo "<h3>🗄️ Database Structure Check</h3>";
    
    $tables = [
        'users' => 'User accounts',
        'bookings' => 'Ticket bookings',
        'payments' => 'Payment records',
        'bus_seats' => 'Seat availability',
        'activity_logs' => 'User activity tracking'
    ];
    
    foreach ($tables as $table => $description) {
        try {
            $stmt = $db->prepare("SELECT COUNT(*) as count FROM $table LIMIT 1");
            $stmt->execute();
            $count = $stmt->fetch()['count'];
            echo "✅ Table '$table': $count records - $description<br>";
        } catch (Exception $e) {
            echo "❌ Table '$table': " . $e->getMessage() . " - $description<br>";
        }
    }
    echo "</div>";
    
    echo "<div class='step'>";
    echo "<h3>🧪 API Functionality Test</h3>";
    
    // Test API endpoints
    $endpoints = [
        'get_user_bookings' => 'Retrieve user bookings',
        'get_available_seats' => 'Check seat availability',
        'test' => 'Basic API connectivity'
    ];
    
    foreach ($endpoints as $action => $description) {
        try {
            $url = "http://localhost/bus/api/booking_clean.php?action=$action";
            if ($action === 'get_available_seats') {
                $url .= "&bus_company=selam-bus&route=addis-ababa-bahirdar&departure_date=" . date('Y-m-d', strtotime('+1 day')) . "&departure_time=08:00";
            }
            
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Cookie: ' . $_SERVER['HTTP_COOKIE']
            ]);
            curl_setopt($ch, CURLOPT_TIMEOUT, 5);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);
            
            if ($curlError) {
                echo "❌ $action: CURL Error - $curlError<br>";
            } elseif ($httpCode === 200) {
                $data = json_decode($response, true);
                if ($data && isset($data['success'])) {
                    if ($data['success']) {
                        echo "✅ $action: Working - $description<br>";
                    } else {
                        echo "⚠️ $action: API Error - " . ($data['message'] ?? 'Unknown') . "<br>";
                    }
                } else {
                    echo "❌ $action: Invalid response format<br>";
                }
            } else {
                echo "❌ $action: HTTP $httpCode<br>";
            }
            
        } catch (Exception $e) {
            echo "❌ $action: " . $e->getMessage() . "<br>";
        }
    }
    echo "</div>";
    
    echo "<div class='step'>";
    echo "<h3>🎫 Create Test Booking</h3>";
    
    try {
        // Create a test booking to verify the system works
        $testBookingId = generateUniqueId('TEST');
        $testData = [
            'from_city' => 'addis-ababa',
            'to_city' => 'bahirdar',
            'departure_date' => date('Y-m-d', strtotime('+2 days')),
            'departure_time' => '08:00',
            'bus_company' => 'selam-bus',
            'bus_type' => 'premium-ac',
            'passenger_count' => 1,
            'selected_seats' => ['A1'],
            'passenger_details' => [
                'fullName' => 'Test User',
                'email' => $user['email'],
                'phone' => '+251911234567',
                'idNumber' => 'TEST123456'
            ],
            'total_amount' => 520.00,
            'payment_method' => 'telebirr'
        ];
        
        echo "Creating test booking: $testBookingId<br>";
        
        // Test booking creation via API
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'http://localhost/bus/api/booking_clean.php?action=create_booking');
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($testData));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Cookie: ' . $_SERVER['HTTP_COOKIE']
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);
        
        if ($curlError) {
            echo "❌ Booking creation failed: $curlError<br>";
        } elseif ($httpCode === 200) {
            $result = json_decode($response, true);
            if ($result && $result['success']) {
                echo "✅ Test booking created successfully<br>";
                $createdBookingId = $result['data']['booking_id'];
                
                // Now test retrieval
                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, 'http://localhost/bus/api/booking_clean.php?action=get_user_bookings');
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_HTTPHEADER, [
                    'Cookie: ' . $_SERVER['HTTP_COOKIE']
                ]);
                curl_setopt($ch, CURLOPT_TIMEOUT, 5);
                
                $response = curl_exec($ch);
                curl_close($ch);
                
                $bookings = json_decode($response, true);
                if ($bookings && $bookings['success'] && isset($bookings['data']['bookings'])) {
                    $bookingCount = count($bookings['data']['bookings']);
                    echo "✅ Retrieved $bookingCount bookings from API<br>";
                    
                    // Check if our test booking is in the results
                    $found = false;
                    foreach ($bookings['data']['bookings'] as $booking) {
                        if ($booking['booking_id'] === $createdBookingId) {
                            $found = true;
                            break;
                        }
                    }
                    
                    if ($found) {
                        echo "✅ Test booking found in API results<br>";
                    } else {
                        echo "⚠️ Test booking not found in API results<br>";
                    }
                } else {
                    echo "❌ Failed to retrieve bookings: " . ($bookings['message'] ?? 'Unknown error') . "<br>";
                }
                
                // Clean up test booking
                try {
                    $stmt = $db->prepare("DELETE FROM bookings WHERE booking_id = ?");
                    $stmt->execute([$createdBookingId]);
                    $stmt = $db->prepare("DELETE FROM payments WHERE booking_id = ?");
                    $stmt->execute([$createdBookingId]);
                    echo "✅ Test booking cleaned up<br>";
                } catch (Exception $e) {
                    echo "⚠️ Cleanup warning: " . $e->getMessage() . "<br>";
                }
                
            } else {
                echo "❌ Booking creation failed: " . ($result['message'] ?? 'Unknown error') . "<br>";
                if (isset($result['error_details'])) {
                    echo "<pre>" . htmlspecialchars(json_encode($result['error_details'], JSON_PRETTY_PRINT)) . "</pre>";
                }
            }
        } else {
            echo "❌ HTTP Error: $httpCode<br>";
            echo "<pre>" . htmlspecialchars($response) . "</pre>";
        }
        
    } catch (Exception $e) {
        echo "❌ Test booking error: " . $e->getMessage() . "<br>";
    }
    echo "</div>";
    
    echo "<div class='step'>";
    echo "<h3>📊 Current Bookings Status</h3>";
    
    try {
        // Show current bookings for this user
        $stmt = $db->prepare("
            SELECT booking_id, from_city, to_city, travel_date, booking_status, payment_status, created_at
            FROM bookings 
            WHERE user_id = (SELECT id FROM users WHERE user_id = ?)
            ORDER BY created_at DESC
            LIMIT 10
        ");
        $stmt->execute([$_SESSION['user_id']]);
        $userBookings = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (count($userBookings) > 0) {
            echo "Found " . count($userBookings) . " existing bookings:<br>";
            echo "<table style='width: 100%; border-collapse: collapse; margin-top: 10px;'>";
            echo "<tr style='background: #f2f2f2;'>";
            echo "<th style='border: 1px solid #ddd; padding: 8px; text-align: left;'>Booking ID</th>";
            echo "<th style='border: 1px solid #ddd; padding: 8px; text-align: left;'>Route</th>";
            echo "<th style='border: 1px solid #ddd; padding: 8px; text-align: left;'>Date</th>";
            echo "<th style='border: 1px solid #ddd; padding: 8px; text-align: left;'>Status</th>";
            echo "<th style='border: 1px solid #ddd; padding: 8px; text-align: left;'>Payment</th>";
            echo "</tr>";
            
            foreach ($userBookings as $booking) {
                echo "<tr>";
                echo "<td style='border: 1px solid #ddd; padding: 8px;'>" . htmlspecialchars($booking['booking_id']) . "</td>";
                echo "<td style='border: 1px solid #ddd; padding: 8px;'>" . htmlspecialchars($booking['from_city'] . ' → ' . $booking['to_city']) . "</td>";
                echo "<td style='border: 1px solid #ddd; padding: 8px;'>" . htmlspecialchars($booking['travel_date']) . "</td>";
                echo "<td style='border: 1px solid #ddd; padding: 8px;'>" . htmlspecialchars($booking['booking_status']) . "</td>";
                echo "<td style='border: 1px solid #ddd; padding: 8px;'>" . htmlspecialchars($booking['payment_status']) . "</td>";
                echo "</tr>";
            }
            echo "</table>";
        } else {
            echo "⚠️ No existing bookings found for this user<br>";
        }
        
    } catch (Exception $e) {
        echo "❌ Error checking bookings: " . $e->getMessage() . "<br>";
    }
    echo "</div>";
    
} catch (Exception $e) {
    echo "<div class='error'>❌ Critical Error: " . $e->getMessage() . "</div>";
    echo "<div class='error'>File: " . $e->getFile() . " Line: " . $e->getLine() . "</div>";
}

echo "<hr>";
echo "<div class='info'>";
echo "<h3>🎯 System Status Summary</h3>";
echo "<p>If all tests above show ✅, your booking system should be working correctly.</p>";
echo "<p><strong>Next Steps:</strong></p>";
echo "<ul>";
echo "<li><a href='book-ticket.html' target='_blank'>🎫 Try booking a ticket</a></li>";
echo "<li><a href='my-tickets.html' target='_blank'>📋 Check your tickets</a></li>";
echo "<li><a href='test_complete_booking_flow.php' target='_blank'>🧪 Run complete flow test</a></li>";
echo "</ul>";
echo "</div>";
?>