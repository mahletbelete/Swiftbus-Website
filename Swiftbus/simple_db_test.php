<?php
// Simple database test
try {
    $pdo = new PDO("mysql:host=localhost;dbname=swiftbus_db", "root", "");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "Database connection: OK\n";
    
    // Check schedules table structure
    $stmt = $pdo->prepare("DESCRIBE schedules");
    $stmt->execute();
    $columns = $stmt->fetchAll();
    
    echo "Schedules table columns:\n";
    foreach ($columns as $col) {
        echo "- " . $col['Field'] . " (" . $col['Type'] . ")\n";
    }
    
    // Check if 'status' column exists
    $hasStatus = false;
    foreach ($columns as $col) {
        if ($col['Field'] === 'status') {
            $hasStatus = true;
            break;
        }
    }
    
    echo "\nHas 'status' column: " . ($hasStatus ? "YES" : "NO") . "\n";
    
    // Check bookings table structure
    $stmt = $pdo->prepare("DESCRIBE bookings");
    $stmt->execute();
    $bookingColumns = $stmt->fetchAll();
    
    echo "\nBookings table columns:\n";
    foreach ($bookingColumns as $col) {
        echo "- " . $col['Field'] . " (" . $col['Type'] . ")\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>