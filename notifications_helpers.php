<?php
require_once 'config.php';
function ensureNotificationsTable(PDO $pdo) {
    $pdo->exec("CREATE TABLE IF NOT EXISTS notifications (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER NOT NULL,
        message TEXT NOT NULL,
        type TEXT NOT NULL,
        status TEXT DEFAULT 'unread',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id)
    )");
}
function addNotification(PDO $pdo, int $userId, string $message, string $type='general'): void {
    ensureNotificationsTable($pdo);
    $stmt = $pdo->prepare('INSERT INTO notifications (user_id, message, type) VALUES (?, ?, ?)');
    $stmt->execute([$userId, $message, $type]);
}
function formatShiftMsg(string $date, string $start, string $end, ?string $storeName=null, string $action='Atribuído'): string {
    $storePart = $storeName ? " na loja $storeName" : '';
    return "$action: $date das $start às $end$storePart.";
}
