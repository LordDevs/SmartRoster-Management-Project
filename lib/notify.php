<?php
// lib/notify.php
//
// Este módulo centraliza o envio de notificações para a aplicação Escala Hillbillys.
// Fornece funções para notificar usuários de eventos de turno através de três
// canais: notificação interna (banco de dados), e‑mail e Slack. As variáveis
// de ambiente opcionais MAIL_FROM, MAIL_FROM_NAME e SLACK_WEBHOOK_URL
// configuram o remetente de e‑mail e o webhook do Slack. Caso não estejam
// definidas, valores padrão seguros serão utilizados.

declare(strict_types=1);

// Dependências:
// - Configuração de banco de dados via config.php (global $pdo)
// - Funções addNotification() e formatShiftMsg() de notifications_helpers.php

// Inclua helpers existentes para que possamos reutilizar addNotification
// e formatShiftMsg. Se o arquivo não existir, silenciosamente ignoramos.
@require_once __DIR__ . '/../notifications_helpers.php';

/**
 * Grava notificação no banco de dados para o usuário informado. Usa
 * addNotification() definido em notifications_helpers.php se existir.
 * Caso contrário, realiza inserção direta na tabela notifications.
 *
 * @param PDO    $pdo      Instância do banco de dados
 * @param int    $userId   ID do usuário destinatário
 * @param string $title    Título ou assunto breve
 * @param string $body     Corpo da mensagem
 * @param string $type     Tipo da notificação (opcional)
 */
function notify_inapp(PDO $pdo, int $userId, string $title, string $body, string $type = 'general'): void {
    // Concatene título e corpo para compatibilidade com tabela existente
    $message = trim($title . ' — ' . $body);
    if (function_exists('addNotification')) {
        addNotification($pdo, $userId, $message, $type);
        return;
    }
    // Fallback: certifique‑se de que a tabela exista e insira manualmente
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS notifications (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL,
            message TEXT NOT NULL,
            type TEXT NOT NULL,
            status TEXT DEFAULT 'unread',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id)
        )");
        $stmt = $pdo->prepare('INSERT INTO notifications (user_id, message, type) VALUES (?, ?, ?)');
        $stmt->execute([$userId, $message, $type]);
    } catch (Throwable $e) {
        error_log('[notify_inapp] ' . $e->getMessage());
    }
}

/**
 * Envia notificação por e‑mail usando a função mail() padrão do PHP.
 * Caso mail() não esteja configurado, registra o envio em um arquivo de log
 * em logs/notifications.log para diagnóstico posterior.
 *
 * @param string $to      Endereço de e‑mail de destino
 * @param string $subject Assunto do e‑mail
 * @param string $body    Corpo do e‑mail (texto simples)
 *
 * @return bool Verdadeiro se enviado com sucesso via mail(), falso caso contrário
 */
function notify_email(string $to, string $subject, string $body): bool {
    // Remetente opcional via env. MAIL_FROM_NAME é ignorado se MAIL_FROM não existir.
    $from = getenv('MAIL_FROM') ?: 'no-reply@localhost';
    $fromName = getenv('MAIL_FROM_NAME') ?: 'Escala Hillbillys';
    $headers  = '';
    if ($from) {
        $headers .= 'From: ' . ($fromName ? "$fromName <$from>" : $from) . "\r\n";
    }
    $headers .= "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";

    $ok = @mail($to, $subject, $body, $headers);
    if (!$ok) {
        // Se mail() falhar, persistir log para análise
        try {
            $logDir = __DIR__ . '/../logs';
            if (!is_dir($logDir)) {
                mkdir($logDir, 0777, true);
            }
            $logMsg = '[' . date('c') . "] EMAIL to={$to} subj={$subject} body=" . str_replace("\n", ' ', $body) . "\n";
            file_put_contents($logDir . '/notifications.log', $logMsg, FILE_APPEND);
        } catch (Throwable $e) {
            // Ignore logging errors silently
        }
    }
    return $ok;
}

/**
 * Envia notificação para Slack via Webhook. É opcional e só é
 * executada se a variável de ambiente SLACK_WEBHOOK_URL estiver definida.
 * Registra no log caso ocorra falha de requisição.
 *
 * @param string $text Texto completo da mensagem
 *
 * @return bool Verdadeiro se enviada com sucesso, falso se falhar
 */
function notify_slack(string $text): bool {
    $hook = getenv('SLACK_WEBHOOK_URL') ?: '';
    if (!$hook) {
        return false;
    }
    $payload = json_encode(['text' => $text], JSON_UNESCAPED_UNICODE);
    $ch = curl_init($hook);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $payload,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 8,
    ]);
    $res = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);
    if ($res === false || $status < 200 || $status >= 300) {
        try {
            $logDir = __DIR__ . '/../logs';
            if (!is_dir($logDir)) {
                mkdir($logDir, 0777, true);
            }
            $logMsg = '[' . date('c') . "] SLACK_FAIL payload={$payload} status={$status} err=" . ($err ?: 'n/a') . "\n";
            file_put_contents($logDir . '/notifications.log', $logMsg, FILE_APPEND);
        } catch (Throwable $e) {
            // silenciosamente ignore
        }
        return false;
    }
    return true;
}

/**
 * Resolve detalhes do destinatário para notificações de turnos.
 * Retorna um array com user_id, email e nome se encontrado, ou null.
 *
 * @param PDO $pdo
 * @param int $employeeId
 * @return array|null
 */
function resolveEmployeeUser(PDO $pdo, int $employeeId): ?array {
    try {
        // Tenta obter dados do funcionário e usuário associado
        $sql = "SELECT u.id AS user_id, COALESCE(e.email, u.email) AS email, COALESCE(e.name, u.name) AS name
                FROM employees e
                LEFT JOIN users u ON u.id = e.id OR u.id = e.user_id
                WHERE e.id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$employeeId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return null;
        }
        return [
            'user_id' => isset($row['user_id']) ? (int)$row['user_id'] : null,
            'email'   => $row['email'] ?? '',
            'name'    => $row['name'] ?? '',
        ];
    } catch (Throwable $e) {
        error_log('[resolveEmployeeUser] ' . $e->getMessage());
        return null;
    }
}

/**
 * Envia notificação de evento de turno (criação, atualização ou exclusão).
 * Monta título e corpo com base nas informações fornecidas e envia
 * através dos canais especificados.
 *
 * @param PDO    $pdo
 * @param string $action   'created'|'updated'|'deleted'
 * @param array  $shift    Dados do turno: employee_id, date, start, end, store_name
 * @param array  $channels Canais a notificar: 'inapp','email','slack'
 */
function notify_shift_event(PDO $pdo, string $action, array $shift, array $channels = ['inapp','email','slack']): void {
    $empId = (int)($shift['employee_id'] ?? 0);
    if ($empId <= 0) {
        return;
    }
    $who = resolveEmployeeUser($pdo, $empId);
    if (!$who) {
        return;
    }
    $date  = $shift['date'] ?? '';
    $start = $shift['start'] ?? '';
    $end   = $shift['end'] ?? '';
    $store = $shift['store_name'] ?? '';
    $title = '';
    switch ($action) {
        case 'created':
            $title = 'Novo turno';
            break;
        case 'updated':
            $title = 'Turno atualizado';
            break;
        case 'deleted':
            $title = 'Turno cancelado';
            break;
        default:
            $title = 'Atualização de escala';
    }
    // Corpo: Data, intervalo e loja se houver
    $storePart = $store ? " — {$store}" : '';
    $body = trim("{$date} {$start}-{$end}{$storePart}");
    // Enviar por cada canal
    // In-app sempre envia, se houver user_id
    if (in_array('inapp', $channels, true) && !empty($who['user_id'])) {
        notify_inapp($pdo, (int)$who['user_id'], $title, $body, 'shift_'.$action);
    }
    // Enviar e-mail
    if (in_array('email', $channels, true) && !empty($who['email'])) {
        $subject = '[Escala] ' . $title;
        $msgBody = ($who['name'] ? $who['name'] . ":\n" : '') . $body;
        notify_email($who['email'], $subject, $msgBody);
    }
    // Slack global
    if (in_array('slack', $channels, true)) {
        notify_slack("*{$title}* — {$body}");
    }
}

/**
 * Envia lembretes de turno (24h antes). Este helper percorre um array de
 * turnos e dispara a notificação de "updated" para cada um. Deve ser
 * invocado por um script agendado (cron) para lembrar os funcionários.
 *
 * @param PDO   $pdo
 * @param array $rows Cada item deve ter employee_id, date, start, end, store_name
 */
function notify_shift_reminder(PDO $pdo, array $rows): void {
    foreach ($rows as $r) {
        notify_shift_event($pdo, 'updated', $r, ['inapp','email','slack']);
    }
}
