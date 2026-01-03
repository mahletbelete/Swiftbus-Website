<?php
session_start();
header('Content-Type: text/html; charset=utf-8');

echo "<h1>💳 Payments Table Viewer</h1>";
echo "<style>
    body { font-family: Arial, sans-serif; max-width: 1200px; margin: 0 auto; padding: 20px; }
    table { width: 100%; border-collapse: collapse; margin: 20px 0; }
    th, td { border: 1px solid #ddd; padding: 8px; text-align: left; font-size: 12px; }
    th { background: #f2f2f2; font-weight: bold; }
    .success { background: #d4edda; color: #155724; padding: 10px; border-radius: 5px; margin: 10px 0; }
    .error { background: #f8d7da; color: #721c24; padding: 10px; border-radius: 5px; margin: 10px 0; }
    .info { background: #d1ecf1; color: #0c5460; padding: 10px; border-radius: 5px; margin: 10px 0; }
</style>";

try {
    require_once 'includes/functions.php';
    
    $db = getDB();
    
    // Check if passenger columns exist
    $stmt = $db->prepare("DESCRIBE payments");
    $stmt->execute();
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $hasPassengerName = false;
    $hasPassengerEmail = false;
    
    foreach ($columns as $column) {
        if ($column['Field'] === 'passenger_name') $hasPassengerName = true;
        if ($column['Field'] === 'passenger_email') $hasPassengerEmail = true;
    }
    
    if (!$hasPassengerName || !$hasPassengerEmail) {
        echo "<div class='error'>";
        echo "<strong>⚠️ Passenger columns not found!</strong><br>";
        echo "Please run the SQL script to add passenger_name and passenger_email columns:<br>";
        echo "<strong>File:</strong> add_passenger_info_to_payments.sql<br>";
        echo "<strong>Instructions:</strong><br>";
        echo "1. Open phpMyAdmin (http://localhost/phpmyadmin)<br>";
        echo "2. Select swiftbus_db database<br>";
        echo "3. Go to SQL tab<br>";
        echo "4. Copy and paste the contents of add_passenger_info_to_payments.sql<br>";
        echo "5. Click Go<br>";
        echo "</div>";
    } else {
        echo "<div class='success'>✅ Passenger columns found in payments table</div>";
    }
    
    // Show table structure
    echo "<h2>📋 Payments Table Structure</h2>";
    echo "<table>";
    echo "<tr><th>Column</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th></tr>";
    
    foreach ($columns as $column) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($column['Field']) . "</td>";
        echo "<td>" . htmlspecialchars($column['Type']) . "</td>";
        echo "<td>" . htmlspecialchars($column['Null']) . "</td>";
        echo "<td>" . htmlspecialchars($column['Key']) . "</td>";
        echo "<td>" . htmlspecialchars($column['Default']) . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    // Show recent payments
    echo "<h2>💰 Recent Payments</h2>";
    
    $stmt = $db->prepare("
        SELECT 
            payment_id, 
            booking_id, 
            " . ($hasPassengerName ? "passenger_name," : "NULL as passenger_name,") . "
            " . ($hasPassengerEmail ? "passenger_email," : "NULL as passenger_email,") . "
            amount, 
            payment_method, 
            payment_status, 
            payment_date,
            created_at
        FROM payments 
        ORDER BY created_at DESC 
        LIMIT 10
    ");
    $stmt->execute();
    $payments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (count($payments) > 0) {
        echo "<table>";
        echo "<tr>";
        echo "<th>Payment ID</th>";
        echo "<th>Booking ID</th>";
        echo "<th>Passenger Name</th>";
        echo "<th>Passenger Email</th>";
        echo "<th>Amount</th>";
        echo "<th>Method</th>";
        echo "<th>Status</th>";
        echo "<th>Payment Date</th>";
        echo "<th>Created</th>";
        echo "</tr>";
        
        foreach ($payments as $payment) {
            echo "<tr>";
            echo "<td>" . htmlspecialchars($payment['payment_id']) . "</td>";
            echo "<td>" . htmlspecialchars($payment['booking_id']) . "</td>";
            echo "<td>" . htmlspecialchars($payment['passenger_name'] ?: 'N/A') . "</td>";
            echo "<td>" . htmlspecialchars($payment['passenger_email'] ?: 'N/A') . "</td>";
            echo "<td>ETB " . number_format($payment['amount'], 2) . "</td>";
            echo "<td>" . htmlspecialchars($payment['payment_method']) . "</td>";
            echo "<td>" . htmlspecialchars($payment['payment_status']) . "</td>";
            echo "<td>" . htmlspecialchars($payment['payment_date'] ?: 'Pending') . "</td>";
            echo "<td>" . htmlspecialchars($payment['created_at']) . "</td>";
            echo "</tr>";
        }
        echo "</table>";
        
        echo "<div class='info'>";
        echo "<strong>📊 Summary:</strong> " . count($payments) . " payment record(s) found";
        echo "</div>";
        
    } else {
        echo "<div class='info'>";
        echo "<strong>ℹ️ No payments found.</strong> Make a test booking to see payment records here.";
        echo "</div>";
    }
    
} catch (Exception $e) {
    echo "<div class='error'>";
    echo "<strong>❌ Error:</strong> " . $e->getMessage() . "<br>";
    echo "<strong>File:</strong> " . $e->getFile() . "<br>";
    echo "<strong>Line:</strong> " . $e->getLine() . "<br>";
    echo "</div>";
}

echo "<hr>";
echo "<p><strong>Next Steps:</strong></p>";
echo "<ul>";
echo "<li>If passenger columns are missing: Run the SQL script first</li>";
echo "<li>If columns exist: Make a test booking to see passenger info</li>";
echo "<li>Check that new bookings include passenger name and email</li>";
echo "</ul>";

echo "<p><a href='book-ticket.html'>→ Make Test Booking</a></p>";
echo "<p><a href='my-tickets.html'>→ View My Tickets</a></p>";
?>