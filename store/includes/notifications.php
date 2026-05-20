<?php
function notificationEnsureSchema(PDO $pdo)
{
    $pdo->exec("CREATE TABLE IF NOT EXISTS notifications (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        type VARCHAR(40) NOT NULL,
        title VARCHAR(160) NOT NULL,
        body TEXT NOT NULL,
        link VARCHAR(255) DEFAULT NULL,
        is_read TINYINT(1) NOT NULL DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_user_read_created (user_id, is_read, created_at),
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

function notificationCreate(PDO $pdo, $userId, $type, $title, $body, $link = 'dashboard.php')
{
    notificationEnsureSchema($pdo);
    $stmt = $pdo->prepare("INSERT INTO notifications (user_id, type, title, body, link) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([(int) $userId, $type, $title, $body, $link]);
}

function notificationUnreadCount(PDO $pdo, $userId)
{
    notificationEnsureSchema($pdo);
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
    $stmt->execute([(int) $userId]);
    return (int) $stmt->fetchColumn();
}

function notificationRecent(PDO $pdo, $userId, $limit = 8)
{
    notificationEnsureSchema($pdo);
    $stmt = $pdo->prepare("SELECT id, type, title, body, link, is_read, created_at FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT ?");
    $stmt->bindValue(1, (int) $userId, PDO::PARAM_INT);
    $stmt->bindValue(2, (int) $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}

function notificationMarkAllRead(PDO $pdo, $userId)
{
    notificationEnsureSchema($pdo);
    $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ?");
    $stmt->execute([(int) $userId]);
}
?>
