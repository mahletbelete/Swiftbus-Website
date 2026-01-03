<?php
/**
 * Create uploads directory structure
 */

// Create uploads directory if it doesn't exist
$uploadDir = 'uploads/avatars/';
if (!is_dir($uploadDir)) {
    if (mkdir($uploadDir, 0755, true)) {
        echo "✓ Created uploads directory: $uploadDir\n";
    } else {
        echo "✗ Failed to create uploads directory: $uploadDir\n";
    }
} else {
    echo "✓ Uploads directory already exists: $uploadDir\n";
}

// Create .htaccess file for security
$htaccessContent = "# Prevent direct access to PHP files in uploads
<Files *.php>
    Order Deny,Allow
    Deny from all
</Files>

# Allow image files
<FilesMatch \"\.(jpg|jpeg|png|gif|webp)$\">
    Order Allow,Deny
    Allow from all
</FilesMatch>
";

$htaccessFile = 'uploads/.htaccess';
if (!file_exists($htaccessFile)) {
    if (file_put_contents($htaccessFile, $htaccessContent)) {
        echo "✓ Created security .htaccess file\n";
    } else {
        echo "✗ Failed to create .htaccess file\n";
    }
} else {
    echo "✓ Security .htaccess file already exists\n";
}

echo "\nUploads directory setup complete!\n";
?>