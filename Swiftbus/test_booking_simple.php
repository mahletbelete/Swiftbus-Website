<?php
// Simple booking test to isolate the error
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo "Please login first: <a href='login.html'>Login</a>";
    exit;
}

echo "<h1>Simple Booking Test</h1>";
echo "<p>User: " . $_SESSION['user_id'] . "</p>";

try {
    require_once 'includes/functions.php';
    echo "<p>✅ Functions loaded</p>";
    
    // Test database connection
    $db = getDB();
    echo "<p>✅ Database connected</p>";
    
    // Test user lookup
    $user = getCurrentUser();
    if ($user) {
        echo "<p>✅ User found: " . $user['full_name'] . "</p>";
    } else {
        echo "<p>❌ User not found</p>";
        exit;
    }
    
    // Test booking creation step by step
    echo "<h2>Testing Booking Creation Steps</h2>";
    
    // Step 1: Generate booking ID
    $bookingId = generateUniqueId('SWB');
    echo "<p>1. Booking ID: $bookingId</p>";
    
    // Step 2: Test QR generation
    $qrData = generateQRData($bookingId, 'Test User', 'addis-ababa-bahirdar', date('Y-m-d'), ['A1']);
    echo "<p>2. QR Data: " . substr($qrData, 0, 50) . "...</p>";
    
    // Step 3: Test database insert (without actually inserting)
    $stmt = $db->prepare("
        SELECT id FROM users WHERE user_id = ?
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $userDbId = $stmt->fetch()['id'];
    echo "<p>3. User DB ID: $userDbId</p>";
    
    // Step 4: Test payment record creation
    echo "<p>4. Testing payment record creation...</p>";
    
    $paymentId = createPaymentRecord('TEST_' . time(), 520.00, 'telebirr');
    if ($paymentId) {
        echo "<p>✅ Payment record created: $paymentId</p>";
        
        // Clean up test payment record
        $stmt = $db->prepare("DELETE FROM payments WHERE payment_id = ?");
        $stmt->execute([$paymentId]);
        echo "<p>✅ Test payment record cleaned up</p>";
    } else {
        echo "<p>⚠️ Payment record creation returned null</p>";
    }
    
    echo "<h2>✅ All Tests Passed</h2>";
    echo "<p>The booking system components are working. The error might be in the API request handling.</p>";
    
    echo "<h2>🔗 Test API Call</h2>";
    echo "<form method='post' action='api/booking_clean.php'>
        <input type='hidden' name='action' value='test'>
        <button type='submit'>Test API Endpoint</button>
    </form>";
    
} catch (Exception $e) {
    echo "<h2>❌ Error Found</h2>";
    echo "<p><strong>Error:</strong> " . $e->getMessage() . "</p>";
    echo "<p><strong>File:</strong> " . $e->getFile() . "</p>";
    echo "<p><strong>Line:</strong> " . $e->getLine() . "</p>";
    echo "<pre>" . $e->getTraceAsString() . "</pre>";
}
?>