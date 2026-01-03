<?php
require_once 'includes/functions.php';

header('Content-Type: application/json');

try {
    $db = getDB();
    
    $result = [];
    
    // Check if tables exist and their structure
    $tables = ['users', 'buses', 'routes', 'schedules', 'bookings', 'payments', 'cities', 'bus_companies'];
    
    foreach ($tables as $table) {
        try {
            // Check if table exists
            $stmt = $db->prepare("SHOW TABLES LIKE ?");
            $stmt->execute([$table]);
            $exists = $stmt->fetch();
            
            if ($exists) {
                // Get table structure
                $stmt = $db->prepare("DESCRIBE $table");
                $stmt->execute();
                $columns = $stmt->fetchAll();
                
                // Get row count
                $stmt = $db->prepare("SELECT COUNT(*) as count FROM $table");
                $stmt->execute();
                $count = $stmt->fetch()['count'];
                
                $result[$table] = [
                    'exists' => true,
                    'columns' => array_column($columns, 'Field'),
                    'row_count' => $count
                ];
            } else {
                $result[$table] = ['exists' => false];
            }
        } catch (Exception $e) {
            $result[$table] = ['error' => $e->getMessage()];
        }
    }
    
    echo json_encode([
        'success' => true,
        'database_structure' => $result
    ], JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>