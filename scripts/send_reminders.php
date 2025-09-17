<?php
// scripts/send_reminders.php
//
// Este script deve ser executado via cron ou tarefa agendada para enviar
// lembretes 24h antes do início dos turnos. Ele consulta a tabela de
// turnos (shifts) para encontrar os turnos que começam entre agora e
// 24 horas no futuro, resolve o usuário associado a cada funcionário
// e envia notificações através de in-app, e-mail e Slack.

declare(strict_types=1);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/notify.php';

// Esta consulta assume que a tabela shifts possui campos: date, start_time, end_time,
// employee_id e opcionalmente store_id. Ajuste conforme sua estrutura.
$query = "
SELECT s.employee_id,
       s.date       AS date,
       s.start_time AS start,
       s.end_time   AS end,
       st.name      AS store_name
FROM shifts s
LEFT JOIN stores st ON st.id = s.store_id
WHERE datetime(s.date || ' ' || s.start_time) BETWEEN datetime('now') AND datetime('now', '+1 day')
";

try {
    $stmt = $pdo->query($query);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if ($rows && function_exists('notify_shift_reminder')) {
        notify_shift_reminder($pdo, $rows);
        echo "Sent reminders: " . count($rows) . "\n";
    } else {
        echo "No reminders due." . "\n";
    }
} catch (Throwable $e) {
    // Registrar falha
    try {
        $logDir = __DIR__ . '/../logs';
        if (!is_dir($logDir)) {
            mkdir($logDir, 0777, true);
        }
        $logMsg = '[' . date('c') . "] REMINDER_FAIL " . $e->getMessage() . "\n";
        file_put_contents($logDir . '/notifications.log', $logMsg, FILE_APPEND);
    } catch (Throwable $ex) {
        // Ignore logging errors
    }
    echo "Error: " . $e->getMessage() . "\n";
    http_response_code(500);
}
