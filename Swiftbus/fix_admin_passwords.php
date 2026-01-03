<?php
// Fix admin passwords for existing database
require_once 'includes/config.php';

try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Generate correct hash for Admin@123
    $password = 'Admin@123';
    $correct_hash = '$2y$10$WU/cPjW.xQThC8sc2rNL/uY4geNTLJP6diTrHP.X/yrBkwRm5MLla';
    
    // Verify the hash works
    if (!password_verify($password, $correct_hash)) {
        throw new Exception('Generated hash does not verify correctly!');
    }
    
    echo "✅ Hash verified successfully for password: $password\n";
    
    // List of admin emails to update
    $admin_emails = [
        'ezedinmoh1@gmail.com',
        'hanamariamsebsbew1@gmail.com', 
        'mubarekali974@gmail.com',
        'wubetlemma788@gmail.com',
        'mahletbelete4@gmail.com',
        'admin@swiftbus.et'
    ];
    
    // Update each admin user's password
    $sql = "UPDATE users SET password_hash = ? WHERE email = ? AND role = 'admin'";
    $stmt = $pdo->prepare($sql);
    
    $updated_count = 0;
    foreach ($admin_emails as $email) {
        $result = $stmt->execute([$correct_hash, $email]);
        if ($stmt->rowCount() > 0) {
            echo "✅ Updated password for: $email\n";
            $updated_count++;
        } else {
            echo "⚠️  No update needed or user not found: $email\n";
        }
    }
    
    echo "\n🎉 Successfully updated passwords for $updated_count admin users!\n";
    echo "Password for all admin users is now: $password\n";
    
    // Verify by checking one user
    $check_sql = "SELECT email, password_hash FROM users WHERE email = ? AND role = 'admin'";
    $check_stmt = $pdo->prepare($check_sql);
    $check_stmt->execute(['admin@swiftbus.et']);
    $user = $check_stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($user && password_verify($password, $user['password_hash'])) {
        echo "✅ Verification successful - admin@swiftbus.et can login with: $password\n";
    } else {
        echo "❌ Verification failed for admin@swiftbus.et\n";
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
?>