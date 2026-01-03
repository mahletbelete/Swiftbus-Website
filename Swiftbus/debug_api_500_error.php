<?php
/**
 * Debug 500 Error in booking_clean.php API
 */

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);

// Start session
session_start();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Debug API 500 Error - SwiftBus</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
        .container { max-width: 1000px; margin: 0 auto; background: white; padding: 20px; border-radius: 10px; }
        .section { margin: 20px 0; padding: 15px; border: 1px solid #ddd; border-radius: 8px; }
        .success { background: #d4edda; color: #155724; border-color: #c3e6cb; }
        .error { background: #f8d7da; color: #721c24; border-color: #f5c6cb; }
        .info { background: #d1ecf1; color: #0c5460; border-color: #bee5eb; }
        .warning { background: #fff3cd; color: #856404; border-color: #ffeaa7; }
        pre { background: #f8f9fa; padding: 10px; border-radius: 5px; overflow-x: auto; font-size: 12px; }
        button { background: #007bff; color: white; border: none; padding: 10px 20px; border-radius: 5px; cursor: pointer; margin: 5px; }
        button:hover { background: #0056b3; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🐛 Debug API 500 Error</h1>
        <p>This script helps debug the 500 Internal Server Error in the booking API.</p>

        <?php
        // Test 1: Check if functions.php loads
        echo '<div class="section">';
        echo '<h3>📁 Step 1: Check functions.php</h3>';
        
        try {
            require_once 'includes/functions.php';
            echo '<div class="success">✅ functions.php loaded successfully</div>';
        } catch (Exception $e) {
            echo '<div class="error">❌ Error loading functions.php: ' . htmlspecialchars($e->getMessage()) . '</div>';
            echo '<pre>' . htmlspecialchars($e->getTraceAsString()) . '</pre>';
            echo '</div>';
            exit;
        }
        echo '</div>';

        // Test 2: Check database connection
        echo '<div class="section">';
        echo '<h3>🗄️ Step 2: Check Database Connection</h3>';
        
        try {
            $db = getDB();
            echo '<div class="success">✅ Database connection successful</div>';
        } catch (Exception $e) {
            echo '<div class="error">❌ Database connection failed: ' . htmlspecialchars($e->getMessage()) . '</div>';
            echo '<pre>' . htmlspecialchars($e->getTraceAsString()) . '</pre>';
            echo '</div>';
            exit;
        }
        echo '</div>';

        // Test 3: Check authentication functions
        echo '<div class="section">';
        echo '<h3>🔐 Step 3: Check Authentication Functions</h3>';
        
        try {
            $isLoggedIn = isLoggedIn();
            echo '<p><strong>isLoggedIn():</strong> ' . ($isLoggedIn ? 'true' : 'false') . '</p>';
            
            if ($isLoggedIn) {
                $userId = getCurrentUserId();
                echo '<p><strong>getCurrentUserId():</strong> ' . htmlspecialchars($userId ?: 'null') . '</p>';
                
                $user = getCurrentUser();
                echo '<p><strong>getCurrentUser():</strong></p>';
                echo '<pre>' . print_r($user, true) . '</pre>';
                echo '<div class="success">✅ Authentication functions working</div>';
            } else {
                echo '<div class="warning">⚠️ User not logged in - this might cause the 500 error</div>';
                echo '<p><a href="login.html">Please login first</a></p>';
            }
        } catch (Exception $e) {
            echo '<div class="error">❌ Authentication error: ' . htmlspecialchars($e->getMessage()) . '</div>';
            echo '<pre>' . htmlspecialchars($e->getTraceAsString()) . '</pre>';
        }
        echo '</div>';

        // Test 4: Test the exact API call that's failing
        echo '<div class="section">';
        echo '<h3>📡 Step 4: Test getUserBookings API Logic</h3>';
        
        if (isLoggedIn()) {
            try {
                $userId = getCurrentUserId();
                $status = '';
                $limit = 50;
                $offset = 0;
                
                echo '<p>Testing with parameters:</p>';
                echo '<ul>';
                echo '<li><strong>User ID:</strong> ' . htmlspecialchars($userId) . '</li>';
                echo '<li><strong>Status:</strong> ' . htmlspecialchars($status ?: 'all') . '</li>';
                echo '<li><strong>Limit:</strong> ' . $limit . '</li>';
                echo '<li><strong>Offset:</strong> ' . $offset . '</li>';
                echo '</ul>';
                
                // Build WHERE clause (same as in booking_clean.php)
                $whereClause = "WHERE b.user_id = (SELECT id FROM users WHERE user_id = ?)";
                $params = [$userId];
                
                if ($status && $status !== 'all') {
                    $whereClause .= " AND b.booking_status = ?";
                    $params[] = $status;
                }
                
                echo '<p><strong>WHERE clause:</strong> ' . htmlspecialchars($whereClause) . '</p>';
                echo '<p><strong>Parameters:</strong> ' . htmlspecialchars(json_encode($params)) . '</p>';
                
                // Test the query
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
                    $whereClause
                    ORDER BY b.created_at DESC
                    LIMIT ? OFFSET ?
                ");
                
                $params[] = $limit;
                $params[] = $offset;
                
                echo '<p><strong>Final parameters:</strong> ' . htmlspecialchars(json_encode($params)) . '</p>';
                
                $stmt->execute($params);
                $bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                echo '<div class="success">✅ Query executed successfully</div>';
                echo '<p><strong>Results found:</strong> ' . count($bookings) . '</p>';
                
                if (count($bookings) > 0) {
                    echo '<p><strong>Sample booking:</strong></p>';
                    echo '<pre>' . print_r($bookings[0], true) . '</pre>';
                }
                
            } catch (Exception $e) {
                echo '<div class="error">❌ Query failed: ' . htmlspecialchars($e->getMessage()) . '</div>';
                echo '<pre>' . htmlspecialchars($e->getTraceAsString()) . '</pre>';
            }
        } else {
            echo '<div class="warning">⚠️ Cannot test API logic - user not logged in</div>';
        }
        echo '</div>';

        // Test 5: Check for missing functions
        echo '<div class="section">';
        echo '<h3>🔍 Step 5: Check Required Functions</h3>';
        
        $requiredFunctions = [
            'requireLogin',
            'getCurrentUserId', 
            'handleSuccess',
            'handleError',
            'getCityDisplayName',
            'getBusCompanyDisplayName',
            'getBusTypeDisplayName',
            'generateQRData',
            'logActivity'
        ];
        
        $missingFunctions = [];
        foreach ($requiredFunctions as $func) {
            if (!function_exists($func)) {
                $missingFunctions[] = $func;
            }
        }
        
        if (empty($missingFunctions)) {
            echo '<div class="success">✅ All required functions exist</div>';
        } else {
            echo '<div class="error">❌ Missing functions: ' . implode(', ', $missingFunctions) . '</div>';
        }
        echo '</div>';

        // Test 6: Direct API call simulation
        echo '<div class="section">';
        echo '<h3>🎯 Step 6: Simulate API Call</h3>';
        
        if (isLoggedIn()) {
            echo '<button onclick="testAPICall()">Test API Call</button>';
            echo '<div id="apiResult"></div>';
        } else {
            echo '<div class="warning">⚠️ Please login first to test API call</div>';
        }
        echo '</div>';
        ?>

        <div class="section info">
            <h3>🔧 Common Causes of 500 Error</h3>
            <ul>
                <li><strong>Missing functions:</strong> Check if all required functions are defined in functions.php</li>
                <li><strong>Database connection:</strong> Verify database credentials and connection</li>
                <li><strong>Authentication:</strong> User not logged in when API requires authentication</li>
                <li><strong>SQL syntax:</strong> Error in the SQL query</li>
                <li><strong>PHP syntax:</strong> Syntax error in the PHP code</li>
                <li><strong>Missing tables:</strong> Required database tables don't exist</li>
            </ul>
        </div>

        <div class="section warning">
            <h3>📋 Next Steps</h3>
            <ol>
                <li>Check all the steps above for any errors</li>
                <li>If authentication fails, login first</li>
                <li>Check PHP error logs in XAMPP (usually in xampp/apache/logs/error.log)</li>
                <li>Test the API call using the button above</li>
                <li>If still failing, check the actual booking_clean.php file for syntax errors</li>
            </ol>
        </div>
    </div>

    <script>
        async function testAPICall() {
            const resultDiv = document.getElementById('apiResult');
            resultDiv.innerHTML = '<div class="info">Testing API call...</div>';
            
            try {
                const response = await fetch('api/booking_clean.php?action=get_user_bookings', {
                    method: 'GET',
                    credentials: 'include',
                    headers: {
                        'Content-Type': 'application/json'
                    }
                });
                
                console.log('Response status:', response.status);
                console.log('Response headers:', [...response.headers.entries()]);
                
                if (response.ok) {
                    const data = await response.json();
                    resultDiv.innerHTML = `
                        <div class="success">✅ API call successful!</div>
                        <pre>${JSON.stringify(data, null, 2)}</pre>
                    `;
                } else {
                    const errorText = await response.text();
                    resultDiv.innerHTML = `
                        <div class="error">❌ API call failed with status ${response.status}</div>
                        <p><strong>Response:</strong></p>
                        <pre>${errorText}</pre>
                    `;
                }
            } catch (error) {
                resultDiv.innerHTML = `
                    <div class="error">❌ API call error: ${error.message}</div>
                    <pre>${error.stack}</pre>
                `;
            }
        }
    </script>
</body>
</html>