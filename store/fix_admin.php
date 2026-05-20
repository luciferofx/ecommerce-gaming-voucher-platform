<?php
require_once 'includes/db.php';

$admin_username = 'admin';
$admin_password = password_hash('admin123', PASSWORD_DEFAULT);

try {
    // Check if admin exists
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = ?");
    $stmt->execute([$admin_username]);
    
    if ($stmt->fetchColumn() > 0) {
        // Update the password
        $stmt = $pdo->prepare("UPDATE users SET password = ?, role = 'admin' WHERE username = ?");
        $stmt->execute([$admin_password, $admin_username]);
        echo "Admin password reset successfully! You can now log in with:<br>Username: admin<br>Password: admin123";
    } else {
        // Create the admin
        $stmt = $pdo->prepare("INSERT INTO users (username, password, role) VALUES (?, ?, 'admin')");
        $stmt->execute([$admin_username, $admin_password]);
        echo "Admin account created successfully! You can now log in with:<br>Username: admin<br>Password: admin123";
    }
    
    echo "<br><br><a href='index.php'>Go to Login Page</a>";

} catch(PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>
