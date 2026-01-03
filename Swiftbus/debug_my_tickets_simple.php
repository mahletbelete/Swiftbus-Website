<?php
/**
 * Simple debug script to test getUserBookings API directly
 */

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Start session
session_start();

echo "<h1>Debug My Tickets API</h1>";

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo "<p style='color: red;'>❌ No user session found. Please login first.</p>";
    echo "<p><a href='login.html'>Go to Login</a></p>";
    exit;
}

echo "<p style='color: green;'>✅ User session found: " . $_SESSION['user_id'] . "</p>";

// Test the API directly
echo "<h2>Testing getUserBookings API</h2>";

try {
    // Include required files
    require_once 'includes/functions.php';
    
    echo "<p>✅ Functions loaded successfully</p>";
    
    // Get database connection
    $db = getDB();
    echo "<p>✅ Database connection successful</p>";
    
    // Get current user ID
    $userId = getCurrentUserId();
    echo "<p>✅ Current user ID: $userId</p>";
    
    // Query bookings directly
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
        LIMIT 10
    ");
    
    $stmt->execute([$userId]);
    $bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<p>✅ Query executed successfully</p>";
    echo "<p><strong>Found " . count($bookings) . " booking(s)</strong></p>";
    
    if (count($bookings) > 0) {
        echo "<h3>Raw Booking Data:</h3>";
        foreach ($bookings as $index => $booking) {
            echo "<div style='border: 1px solid #ccc; padding: 10px; margin: 10px 0; background: #f9f9f9;'>";
            echo "<h4>Booking " . ($index + 1) . ":</h4>";
            echo "<pre>" . print_r($booking, true) . "</pre>";
            echo "</div>";
        }
        
        echo "<h3>Processed Booking Data (like my-tickets.html does):</h3>";
        foreach ($bookings as $index => $booking) {
            // Process like my-tickets.html does
            $passengerDetails = [];
            if ($booking['passenger_details']) {
                $passengerDetails = json_decode($booking['passenger_details'], true) ?: [];
            }
            
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
                'status' => $booking['booking_status'],
                'passengerName' => $passengerDetails['fullName'] ?? 'Passenger',
                'passengerEmail' => $passengerDetails['email'] ?? 'N/A'
            ];
            
            echo "<div style='border: 1px solid #28a745; padding: 10px; margin: 10px 0; background: #d4edda;'>";
            echo "<h4>Processed Ticket " . ($index + 1) . ":</h4>";
            echo "<pre>" . print_r($processedTicket, true) . "</pre>";
            echo "</div>";
        }
        
    } else {
        echo "<p style='color: orange;'>⚠️ No bookings found for this user</p>";
        
        // Check if there are any bookings in the database at all
        $stmt = $db->prepare("SELECT COUNT(*) as total FROM bookings");
        $stmt->execute();
        $total = $stmt->fetch()['total'];
        
        echo "<p>Total bookings in database: $total</p>";
        
        if ($total > 0) {
            echo "<p>There are bookings in the database, but none for the current user.</p>";
            
            // Show all user IDs in bookings table
            $stmt = $db->prepare("SELECT DISTINCT user_id FROM bookings LIMIT 5");
            $stmt->execute();
            $userIds = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
            echo "<p>User IDs with bookings: " . implode(', ', $userIds) . "</p>";
            echo "<p>Current user ID: $userId</p>";
        } else {
            echo "<p>No bookings exist in the database at all.</p>";
        }
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Error: " . $e->getMessage() . "</p>";
    echo "<p>File: " . $e->getFile() . "</p>";
    echo "<p>Line: " . $e->getLine() . "</p>";
}

echo "<hr>";
echo "<p><a href='my-tickets.html'>Go to My Tickets Page</a></p>";
echo "<p><a href='test_my_tickets_fix.html'>Go to Test Page</a></p>";
?>