<?php
session_start();
header('Content-Type: text/html; charset=utf-8');

echo "<h1>🔍 My Tickets Integration Analysis</h1>";
echo "<style>
    body { font-family: Arial, sans-serif; max-width: 1200px; margin: 0 auto; padding: 20px; }
    .section { background: #f8f9fa; padding: 20px; margin: 15px 0; border-radius: 8px; border-left: 4px solid #007cba; }
    .success { border-left-color: #28a745; background: #d4edda; }
    .error { border-left-color: #dc3545; background: #f8d7da; }
    .warning { border-left-color: #ffc107; background: #fff3cd; }
    .info { border-left-color: #17a2b8; background: #d1ecf1; }
    table { width: 100%; border-collapse: collapse; margin: 10px 0; }
    th, td { border: 1px solid #ddd; padding: 8px; text-align: left; font-size: 12px; }
    th { background: #f2f2f2; font-weight: bold; }
    pre { background: #f5f5f5; padding: 10px; border-radius: 3px; overflow-x: auto; font-size: 11px; max-height: 300px; overflow-y: auto; }
    .test-result { padding: 10px; margin: 5px 0; border-radius: 5px; }
    .pass { background: #d4edda; color: #155724; }
    .fail { background: #f8d7da; color: #721c24; }
</style>";

try {
    require_once 'includes/functions.php';
    
    // Check authentication
    if (!isset($_SESSION['user_id'])) {
        echo "<div class='section error'>";
        echo "<strong>❌ Authentication Required:</strong> Please <a href='login.html'>login first</a> to test My Tickets integration.";
        echo "</div>";
        exit;
    }
    
    echo "<div class='section success'>";
    echo "<strong>✅ User Authenticated:</strong> " . $_SESSION['user_id'];
    echo "</div>";
    
    $db = getDB();
    $user = getCurrentUser();
    
    echo "<div class='section info'>";
    echo "<strong>👤 Current User:</strong> " . $user['full_name'] . " (" . $user['email'] . ")";
    echo "</div>";
    
    // Test 1: Check API Endpoint
    echo "<div class='section'>";
    echo "<h2>🔗 Test 1: API Endpoint Integration</h2>";
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'http://localhost/bus/api/booking_clean.php?action=get_user_bookings');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Cookie: ' . $_SERVER['HTTP_COOKIE']
    ]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($http_code === 200) {
        $api_data = json_decode($response, true);
        if ($api_data && $api_data['success']) {
            echo "<div class='test-result pass'>✅ API Endpoint: Working correctly</div>";
            echo "<strong>Bookings found:</strong> " . count($api_data['data']['bookings'] ?? []) . "<br>";
            
            if (!empty($api_data['data']['bookings'])) {
                echo "<strong>Sample booking structure:</strong><br>";
                echo "<pre>" . json_encode($api_data['data']['bookings'][0], JSON_PRETTY_PRINT) . "</pre>";
            }
        } else {
            echo "<div class='test-result fail'>❌ API Endpoint: " . ($api_data['message'] ?? 'Unknown error') . "</div>";
        }
    } else {
        echo "<div class='test-result fail'>❌ API Endpoint: HTTP $http_code</div>";
    }
    echo "</div>";
    
    // Test 2: Database Integration
    echo "<div class='section'>";
    echo "<h2>🗄️ Test 2: Database Integration</h2>";
    
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
        LIMIT 5
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (count($bookings) > 0) {
        echo "<div class='test-result pass'>✅ Database Query: Found " . count($bookings) . " booking(s)</div>";
        
        echo "<h3>📋 Raw Database Data:</h3>";
        echo "<table>";
        echo "<tr><th>Booking ID</th><th>Route</th><th>Date</th><th>Status</th><th>Payment</th><th>Amount</th><th>Seats</th><th>Passenger Details</th></tr>";
        
        foreach ($bookings as $booking) {
            $passengerDetails = json_decode($booking['passenger_details'], true);
            $selectedSeats = json_decode($booking['selected_seats'], true);
            
            echo "<tr>";
            echo "<td>" . htmlspecialchars($booking['booking_id']) . "</td>";
            echo "<td>" . htmlspecialchars($booking['from_city']) . " → " . htmlspecialchars($booking['to_city']) . "</td>";
            echo "<td>" . htmlspecialchars($booking['departure_date']) . "</td>";
            echo "<td>" . htmlspecialchars($booking['booking_status']) . "</td>";
            echo "<td>" . htmlspecialchars($booking['payment_status']) . "</td>";
            echo "<td>ETB " . number_format($booking['total_amount'], 2) . "</td>";
            echo "<td>" . (is_array($selectedSeats) ? implode(', ', $selectedSeats) : 'N/A') . "</td>";
            echo "<td>" . htmlspecialchars($passengerDetails['fullName'] ?? 'N/A') . "</td>";
            echo "</tr>";
        }
        echo "</table>";
        
    } else {
        echo "<div class='test-result fail'>❌ Database Query: No bookings found for current user</div>";
        
        // Check if bookings exist for other users
        $stmt = $db->prepare("SELECT COUNT(*) as total FROM bookings");
        $stmt->execute();
        $total = $stmt->fetch()['total'];
        
        echo "<strong>Total bookings in database:</strong> $total<br>";
        if ($total > 0) {
            echo "<strong>Issue:</strong> Bookings exist but not for current user<br>";
        }
    }
    echo "</div>";
    
    // Test 3: Data Processing
    echo "<div class='section'>";
    echo "<h2>⚙️ Test 3: Data Processing (My-Tickets Logic)</h2>";
    
    if (count($bookings) > 0) {
        echo "<h3>🔄 Processing bookings like my-tickets.html does:</h3>";
        
        foreach ($bookings as $index => $booking) {
            echo "<h4>Processing Booking " . ($index + 1) . ": " . $booking['booking_id'] . "</h4>";
            
            // Simulate the createTicketFromAPIBooking function
            $today = new DateTime();
            $departureDate = new DateTime($booking['departure_date']);
            $status = $booking['booking_status'] ?: 'confirmed';
            
            // Auto-determine status based on date
            if ($status === 'confirmed' && $departureDate < $today) {
                $status = 'completed';
            }
            
            // Handle passenger details
            $passengerDetails = [];
            if ($booking['passenger_details']) {
                $passengerDetails = json_decode($booking['passenger_details'], true) ?: [];
            }
            
            // Handle selected seats
            $selectedSeats = [];
            if ($booking['selected_seats']) {
                $selectedSeats = json_decode($booking['selected_seats'], true) ?: ['N/A'];
            } else {
                $selectedSeats = ['N/A'];
            }
            
            $processedTicket = [
                'id' => $booking['booking_id'],
                'bookingId' => $booking['booking_id'],
                'fromCity' => getCityDisplayName($booking['from_city']),
                'toCity' => getCityDisplayName($booking['to_city']),
                'departureDate' => $booking['departure_date'],
                'departureTime' => $booking['departure_time'],
                'busCompany' => $booking['company_name'] ?: getBusCompanyDisplayName($booking['bus_company']),
                'busType' => getBusTypeDisplayName($booking['bus_type']),
                'passengerCount' => $booking['passenger_count'],
                'seats' => $selectedSeats,
                'totalAmount' => $booking['total_amount'],
                'paymentMethod' => $booking['payment_method'] ?: $booking['payment_status'],
                'bookingDate' => $booking['booking_date'],
                'status' => $status,
                'passengerName' => $passengerDetails['fullName'] ?? $user['full_name'],
                'passengerEmail' => $passengerDetails['email'] ?? $user['email']
            ];
            
            echo "<div class='test-result pass'>✅ Processed successfully</div>";
            echo "<strong>Processed ticket data:</strong><br>";
            echo "<pre>" . json_encode($processedTicket, JSON_PRETTY_PRINT) . "</pre>";
        }
        
    } else {
        echo "<div class='test-result fail'>❌ No bookings to process</div>";
    }
    echo "</div>";
    
    // Test 4: My-Tickets Page Simulation
    echo "<div class='section'>";
    echo "<h2>🎫 Test 4: My-Tickets Page Simulation</h2>";
    
    echo "<h3>🧪 Simulating my-tickets.html JavaScript flow:</h3>";
    
    // Step 1: Authentication check
    echo "<div class='test-result pass'>✅ Step 1: User authentication - PASSED</div>";
    
    // Step 2: API call
    if (isset($api_data) && $api_data['success']) {
        echo "<div class='test-result pass'>✅ Step 2: API call - PASSED (" . count($api_data['data']['bookings'] ?? []) . " bookings)</div>";
        
        // Step 3: Data processing
        if (!empty($api_data['data']['bookings'])) {
            echo "<div class='test-result pass'>✅ Step 3: Data processing - PASSED</div>";
            echo "<div class='test-result pass'>✅ Step 4: UI display - READY (tickets would be displayed)</div>";
            
            echo "<h3>📊 Expected My-Tickets Display:</h3>";
            echo "<ul>";
            foreach ($api_data['data']['bookings'] as $booking) {
                $passengerDetails = json_decode($booking['passenger_details'], true) ?: [];
                echo "<li><strong>" . $booking['booking_id'] . "</strong> - " . 
                     getCityDisplayName($booking['from_city']) . " → " . 
                     getCityDisplayName($booking['to_city']) . " (" . 
                     $booking['departure_date'] . ")</li>";
            }
            echo "</ul>";
            
        } else {
            echo "<div class='test-result fail'>❌ Step 3: No bookings to display</div>";
        }
    } else {
        echo "<div class='test-result fail'>❌ Step 2: API call failed</div>";
    }
    echo "</div>";
    
    // Test 5: Integration Issues Check
    echo "<div class='section'>";
    echo "<h2>🔧 Test 5: Integration Issues Check</h2>";
    
    $issues = [];
    
    // Check helper functions
    $helperFunctions = ['getCityDisplayName', 'getBusCompanyDisplayName', 'getBusTypeDisplayName'];
    foreach ($helperFunctions as $func) {
        if (!function_exists($func)) {
            $issues[] = "Missing helper function: $func()";
        }
    }
    
    // Check database structure
    try {
        $stmt = $db->prepare("DESCRIBE bookings");
        $stmt->execute();
        $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        $requiredColumns = ['booking_id', 'user_id', 'from_city', 'to_city', 'travel_date', 'departure_time', 'passenger_details', 'selected_seats'];
        foreach ($requiredColumns as $col) {
            if (!in_array($col, $columns)) {
                $issues[] = "Missing database column: bookings.$col";
            }
        }
    } catch (Exception $e) {
        $issues[] = "Database structure check failed: " . $e->getMessage();
    }
    
    if (empty($issues)) {
        echo "<div class='test-result pass'>✅ No integration issues found</div>";
    } else {
        echo "<div class='test-result fail'>❌ Integration issues found:</div>";
        echo "<ul>";
        foreach ($issues as $issue) {
            echo "<li>" . htmlspecialchars($issue) . "</li>";
        }
        echo "</ul>";
    }
    echo "</div>";
    
    // Summary and Recommendations
    echo "<div class='section info'>";
    echo "<h2>📋 Summary & Recommendations</h2>";
    
    if (count($bookings) > 0 && isset($api_data) && $api_data['success']) {
        echo "<div class='test-result pass'>";
        echo "<strong>✅ INTEGRATION STATUS: WORKING</strong><br>";
        echo "My-Tickets page should display " . count($bookings) . " ticket(s) correctly.";
        echo "</div>";
        
        echo "<h3>🎯 Next Steps:</h3>";
        echo "<ul>";
        echo "<li>✅ Open <a href='my-tickets.html' target='_blank'>my-tickets.html</a> to verify display</li>";
        echo "<li>✅ Check that all ticket information appears correctly</li>";
        echo "<li>✅ Test filtering and search functionality</li>";
        echo "<li>✅ Verify QR code generation</li>";
        echo "</ul>";
        
    } else {
        echo "<div class='test-result fail'>";
        echo "<strong>❌ INTEGRATION STATUS: ISSUES FOUND</strong><br>";
        echo "My-Tickets page will show 'No Tickets Found' message.";
        echo "</div>";
        
        echo "<h3>🔧 Required Fixes:</h3>";
        echo "<ul>";
        if (count($bookings) === 0) {
            echo "<li>❌ No bookings found for current user - make a test booking</li>";
        }
        if (!isset($api_data) || !$api_data['success']) {
            echo "<li>❌ API endpoint not working - check booking_clean.php</li>";
        }
        echo "</ul>";
    }
    echo "</div>";
    
} catch (Exception $e) {
    echo "<div class='section error'>";
    echo "<strong>❌ Analysis Error:</strong> " . $e->getMessage() . "<br>";
    echo "<strong>File:</strong> " . $e->getFile() . "<br>";
    echo "<strong>Line:</strong> " . $e->getLine() . "<br>";
    echo "</div>";
}

echo "<hr>";
echo "<p><strong>Quick Links:</strong></p>";
echo "<p><a href='my-tickets.html' target='_blank'>→ Open My Tickets Page</a></p>";
echo "<p><a href='book-ticket.html'>→ Make Test Booking</a></p>";
echo "<p><a href='view_payments.php'>→ View Payments Table</a></p>";
?>