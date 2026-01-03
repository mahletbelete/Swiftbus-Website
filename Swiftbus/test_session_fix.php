<?php
/**
 * Test script to verify session authentication fix
 */
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Include functions
require_once 'includes/functions.php';

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Session Fix Test - SwiftBus</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
        .container { max-width: 800px; margin: 0 auto; background: white; padding: 20px; border-radius: 10px; }
        .section { margin: 20px 0; padding: 15px; border: 1px solid #ddd; border-radius: 8px; }
        .success { background: #d4edda; color: #155724; border-color: #c3e6cb; }
        .error { background: #f8d7da; color: #721c24; border-color: #f5c6cb; }
        .info { background: #d1ecf1; color: #0c5460; border-color: #bee5eb; }
        .warning { background: #fff3cd; color: #856404; border-color: #ffeaa7; }
        pre { background: #f8f9fa; padding: 10px; border-radius: 5px; overflow-x: auto; font-size: 12px; }
        .btn { padding: 10px 20px; margin: 5px; border: none; border-radius: 5px; cursor: pointer; text-decoration: none; display: inline-block; }
        .btn-primary { background: #007bff; color: white; }
        .btn-success { background: #28a745; color: white; }
        .btn-danger { background: #dc3545; color: white; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔧 Session Authentication Fix Test</h1>
        <p>This script tests the enhanced session management and authentication system.</p>

        <?php
        try {
            // Test 1: Session Configuration
            echo '<div class="section info">';
            echo '<h3>📋 Session Configuration</h3>';
            echo '<p><strong>Session Status:</strong> ' . session_status() . ' (1=disabled, 2=active, 3=none)</p>';
            echo '<p><strong>Session ID:</strong> ' . (session_id() ?: 'None') . '</p>';
            echo '<p><strong>Cookie Lifetime:</strong> ' . ini_get('session.cookie_lifetime') . ' seconds</p>';
            echo '<p><strong>GC Max Lifetime:</strong> ' . ini_get('session.gc_maxlifetime') . ' seconds</p>';
            echo '<p><strong>Session Save Path:</strong> ' . session_save_path() . '</p>';
            echo '</div>';

            // Test 2: Database Connection
            echo '<div class="section info">';
            echo '<h3>📡 Database Connection</h3>';
            $db = getDB();
            echo '<p>✅ Database connected successfully</p>';
            echo '</div>';

            // Test 3: Authentication Status
            echo '<div class="section">';
            echo '<h3>🔐 Authentication Status</h3>';
            
            if (isLoggedIn()) {
                echo '<div class="success">';
                echo '<p>✅ User is logged in</p>';
                
                $currentUser = getCurrentUser();
                $userId = getCurrentUserId();
                
                echo '<p><strong>User ID (session):</strong> ' . htmlspecialchars($userId) . '</p>';
                echo '<p><strong>Email:</strong> ' . htmlspecialchars($currentUser['email'] ?? 'N/A') . '</p>';
                echo '<p><strong>Name:</strong> ' . htmlspecialchars($currentUser['full_name'] ?? 'N/A') . '</p>';
                echo '<p><strong>Role:</strong> ' . htmlspecialchars($currentUser['role'] ?? 'N/A') . '</p>';
                echo '<p><strong>Last Login:</strong> ' . htmlspecialchars($currentUser['last_login'] ?? 'N/A') . '</p>';
                echo '</div>';
                
                // Test API calls
                echo '<div class="info">';
                echo '<h4>🧪 API Test Buttons</h4>';
                echo '<button class="btn btn-primary" onclick="testCheckSession()">Test Check Session API</button>';
                echo '<button class="btn btn-success" onclick="testGetUserBookings()">Test Get User Bookings API</button>';
                echo '<button class="btn btn-danger" onclick="testLogout()">Test Logout</button>';
                echo '</div>';
                
            } else {
                echo '<div class="warning">';
                echo '<p>⚠️ User is not logged in</p>';
                echo '<p>Session data: <pre>' . print_r($_SESSION, true) . '</pre></p>';
                echo '<p><a href="login.html" class="btn btn-primary">Login to Test</a></p>';
                echo '</div>';
            }
            echo '</div>';

            // Test 4: Session Data
            echo '<div class="section info">';
            echo '<h3>📊 Session Data</h3>';
            echo '<pre>' . print_r($_SESSION, true) . '</pre>';
            echo '</div>';

        } catch (Exception $e) {
            echo '<div class="section error">';
            echo '<h3>❌ Error</h3>';
            echo '<p>Error: ' . htmlspecialchars($e->getMessage()) . '</p>';
            echo '<pre>' . htmlspecialchars($e->getTraceAsString()) . '</pre>';
            echo '</div>';
        }
        ?>

        <div class="section info">
            <h3>🔧 Test Results</h3>
            <div id="testResults">
                <p>Click the test buttons above to see API responses here.</p>
            </div>
        </div>
    </div>

    <script>
        async function testCheckSession() {
            const resultsDiv = document.getElementById('testResults');
            resultsDiv.innerHTML = '<p>🔄 Testing check session API...</p>';
            
            try {
                const response = await fetch('api/auth.php?action=check_session', {
                    credentials: 'include'
                });
                const data = await response.json();
                
                resultsDiv.innerHTML = `
                    <h4>✅ Check Session API Response:</h4>
                    <pre>${JSON.stringify(data, null, 2)}</pre>
                `;
            } catch (error) {
                resultsDiv.innerHTML = `
                    <h4>❌ Check Session API Error:</h4>
                    <pre>${error.message}</pre>
                `;
            }
        }

        async function testGetUserBookings() {
            const resultsDiv = document.getElementById('testResults');
            resultsDiv.innerHTML = '<p>🔄 Testing get user bookings API...</p>';
            
            try {
                const response = await fetch('api/booking_clean.php?action=get_user_bookings', {
                    credentials: 'include'
                });
                const data = await response.json();
                
                resultsDiv.innerHTML = `
                    <h4>✅ Get User Bookings API Response:</h4>
                    <pre>${JSON.stringify(data, null, 2)}</pre>
                `;
            } catch (error) {
                resultsDiv.innerHTML = `
                    <h4>❌ Get User Bookings API Error:</h4>
                    <pre>${error.message}</pre>
                `;
            }
        }

        async function testLogout() {
            const resultsDiv = document.getElementById('testResults');
            resultsDiv.innerHTML = '<p>🔄 Testing logout API...</p>';
            
            try {
                const response = await fetch('api/auth.php?action=logout', {
                    method: 'POST',
                    credentials: 'include'
                });
                const data = await response.json();
                
                resultsDiv.innerHTML = `
                    <h4>✅ Logout API Response:</h4>
                    <pre>${JSON.stringify(data, null, 2)}</pre>
                    <p><a href="login.html" class="btn btn-primary">Login Again</a></p>
                `;
            } catch (error) {
                resultsDiv.innerHTML = `
                    <h4>❌ Logout API Error:</h4>
                    <pre>${error.message}</pre>
                `;
            }
        }
    </script>
</body>
</html>