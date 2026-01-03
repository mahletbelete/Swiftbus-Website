<?php
session_start();
header('Content-Type: text/html; charset=utf-8');

echo "<h1>🔍 Session Debug</h1>";
echo "<style>
    body { font-family: Arial, sans-serif; max-width: 800px; margin: 0 auto; padding: 20px; }
    .section { background: #f8f9fa; padding: 15px; margin: 15px 0; border-radius: 5px; border-left: 4px solid #007cba; }
    .success { border-left-color: #28a745; background: #d4edda; }
    .error { border-left-color: #dc3545; background: #f8d7da; }
    .warning { border-left-color: #ffc107; background: #fff3cd; }
    pre { background: #f5f5f5; padding: 10px; border-radius: 3px; overflow-x: auto; }
</style>";

echo "<div class='section'>";
echo "<strong>Session Status:</strong> " . (session_status() === PHP_SESSION_ACTIVE ? 'Active' : 'Inactive') . "<br>";
echo "<strong>Session ID:</strong> " . session_id() . "<br>";
echo "<strong>Session Name:</strong> " . session_name() . "<br>";
echo "</div>";

echo "<div class='section'>";
echo "<strong>Session Data:</strong><br>";
if (empty($_SESSION)) {
    echo "<span style='color: red;'>❌ Session is empty</span>";
} else {
    echo "<pre>" . print_r($_SESSION, true) . "</pre>";
}
echo "</div>";

echo "<div class='section'>";
echo "<strong>Cookies:</strong><br>";
if (empty($_COOKIE)) {
    echo "<span style='color: red;'>❌ No cookies found</span>";
} else {
    echo "<pre>" . print_r($_COOKIE, true) . "</pre>";
}
echo "</div>";

// Test database connection
try {
    require_once 'includes/functions.php';
    
    echo "<div class='section success'>";
    echo "<strong>✅ Database Connection:</strong> Success<br>";
    
    if (isset($_SESSION['user_id'])) {
        echo "<strong>Session User ID:</strong> " . $_SESSION['user_id'] . "<br>";
        
        $user = getCurrentUser();
        if ($user) {
            echo "<strong>✅ User Found:</strong><br>";
            echo "• Name: " . $user['full_name'] . "<br>";
            echo "• Email: " . $user['email'] . "<br>";
            echo "• Role: " . $user['role'] . "<br>";
        } else {
            echo "<strong>❌ User Not Found:</strong> Session user_id doesn't match database<br>";
        }
    } else {
        echo "<strong>❌ No User Session:</strong> user_id not set in session<br>";
    }
    echo "</div>";
    
} catch (Exception $e) {
    echo "<div class='section error'>";
    echo "<strong>❌ Database Error:</strong> " . $e->getMessage();
    echo "</div>";
}

// Test session functions
echo "<h2>🧪 Session Function Tests</h2>";

echo "<div class='section'>";
echo "<strong>isLoggedIn():</strong> " . (function_exists('isLoggedIn') ? (isLoggedIn() ? 'true' : 'false') : 'function not found') . "<br>";
echo "<strong>getCurrentUserId():</strong> " . (function_exists('getCurrentUserId') ? (getCurrentUserId() ?: 'null') : 'function not found') . "<br>";
echo "</div>";

// Show recent login attempts
try {
    $db = getDB();
    
    echo "<h2>📋 Recent Login Activity</h2>";
    
    $stmt = $db->prepare("
        SELECT 
            al.action, 
            al.description, 
            al.created_at,
            u.user_id as session_user_id,
            u.full_name
        FROM activity_logs al
        LEFT JOIN users u ON u.id = al.user_id
        WHERE al.action IN ('login', 'logout', 'login_failed') 
        ORDER BY al.created_at DESC 
        LIMIT 10
    ");
    $stmt->execute();
    $activities = $stmt->fetchAll();
    
    if (count($activities) > 0) {
        echo "<div class='section'>";
        echo "<table style='width: 100%; border-collapse: collapse;'>";
        echo "<tr style='background: #f2f2f2;'><th style='border: 1px solid #ddd; padding: 8px;'>User</th><th style='border: 1px solid #ddd; padding: 8px;'>Action</th><th style='border: 1px solid #ddd; padding: 8px;'>Description</th><th style='border: 1px solid #ddd; padding: 8px;'>Time</th></tr>";
        
        foreach ($activities as $activity) {
            echo "<tr>";
            echo "<td style='border: 1px solid #ddd; padding: 8px;'>" . htmlspecialchars($activity['full_name'] ?: 'Unknown') . " (" . htmlspecialchars($activity['session_user_id'] ?: 'N/A') . ")</td>";
            echo "<td style='border: 1px solid #ddd; padding: 8px;'>" . htmlspecialchars($activity['action']) . "</td>";
            echo "<td style='border: 1px solid #ddd; padding: 8px;'>" . htmlspecialchars($activity['description'] ?: 'N/A') . "</td>";
            echo "<td style='border: 1px solid #ddd; padding: 8px;'>" . htmlspecialchars($activity['created_at']) . "</td>";
            echo "</tr>";
        }
        echo "</table>";
        echo "</div>";
    } else {
        echo "<div class='section warning'>";
        echo "<strong>⚠️ No Recent Activity:</strong> No login/logout activity found";
        echo "</div>";
    }
    
} catch (Exception $e) {
    echo "<div class='section error'>";
    echo "<strong>❌ Activity Log Error:</strong> " . $e->getMessage();
    echo "</div>";
}

echo "<hr>";
echo "<p><a href='login.html'>→ Go to Login</a></p>";
echo "<p><a href='my-tickets.html'>→ Go to My Tickets</a></p>";
?>