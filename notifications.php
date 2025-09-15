<?php
// notifications.php – página de listagem e leitura de notificações de usuário
// Inclui o arquivo de configuração e verifica login
require_once 'config.php';
requireLogin();

$userId = $_SESSION['user_id'];

// Função local de fallback caso addNotification não esteja definida em config.php
if (!function_exists('addNotification')) {
    function addNotification($pdo, $userId, $message, $type = 'general', $sendEmail = false) {
        $stmt = $pdo->prepare('INSERT INTO notifications (user_id, message, type) VALUES (?, ?, ?)');
        $stmt->execute([$userId, $message, $type]);
        // Email opcional
        if ($sendEmail) {
            // Buscar email do usuário
            $userStmt = $pdo->prepare('SELECT email, name FROM users JOIN employees ON users.employee_id = employees.id WHERE users.id = ?');
            $userStmt->execute([$userId]);
            $user = $userStmt->fetch(PDO::FETCH_ASSOC);
            if ($user) {
                $to      = $user['email'];
                $subject = 'Notificação Escala Hillbillys';
                $body    = "Olá {$user['name']},\n\n{$message}\n\nEquipe Escala Hillbillys";
                // Utilize mail() se configurado ou biblioteca externa
                @mail($to, $subject, $body);
            }
        }
    }
}

// Tratar marcação como lida
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['notification_id'])) {
    $id = intval($_POST['notification_id']);
    $stmt = $pdo->prepare('UPDATE notifications SET status = "read" WHERE id = ? AND user_id = ?');
    $stmt->execute([$id, $userId]);
}

// Obter notificações do usuário
$stmt = $pdo->prepare('SELECT id, message, type, status, created_at FROM notifications WHERE user_id = ? ORDER BY created_at DESC');
$stmt->execute([$userId]);
$notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Notificações – Escala Hillbillys</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <?php
        // Use unified navbar
        $activePage = '';
        require_once __DIR__ . '/navbar.php';
    ?>
    <div class="container mt-4">
        <h3>Notificações</h3>
        <?php if (empty($notifications)): ?>
            <p>Você não possui notificações no momento.</p>
        <?php else: ?>
            <div class="list-group">
                <?php foreach ($notifications as $n): ?>
                    <div class="list-group-item d-flex justify-content-between align-items-start <?php echo ($n['status'] === 'unread' ? 'list-group-item-warning' : ''); ?>">
                        <div class="ms-2 me-auto">
                            <div class="fw-bold text-capitalize"><?php echo htmlspecialchars(str_replace('_', ' ', $n['type'])); ?></div>
                            <?php echo nl2br(htmlspecialchars($n['message'])); ?>
                            <small class="text-muted d-block"><?php echo htmlspecialchars($n['created_at']); ?></small>
                        </div>
                        <?php if ($n['status'] === 'unread'): ?>
                            <form method="post" style="margin:0;">
                                <input type="hidden" name="notification_id" value="<?php echo $n['id']; ?>">
                                <button type="submit" class="btn btn-sm btn-outline-success">Marcar como lida</button>
                            </form>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>