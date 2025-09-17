<?php
// create_shift_restricted.php
// Script para criar turnos com validações adicionais: datas passadas e restrição por loja.
// Requer funções utilitárias definidas em config.php: assertNotPastDate, userCanManageStore, getEmployeeStoreId.

require_once 'config.php';
// Inclua notificações internas
@require_once 'notifications_helpers.php';
// Inclua sistema unificado de notificações (in-app, e-mail, Slack)
@require_once __DIR__ . '/lib/notify.php';

requirePrivileged();
$user = currentUser();
$role = $user['role'];
$storeId = $user['store_id'] ?? null;

header('Content-Type: application/json');

// Obter parâmetros
$employeeId = isset($_POST['employee_id']) ? (int)$_POST['employee_id'] : 0;
$date       = trim($_POST['date'] ?? '');
$startTime  = trim($_POST['start_time'] ?? '');
$endTime    = trim($_POST['end_time'] ?? '');

if (!$employeeId || !$date || !$startTime || !$endTime) {
    echo json_encode(['success' => false, 'message' => 'Parâmetros incompletos.']);
    exit();
}

try {
    // Bloquear criação em datas passadas
    assertNotPastDate($date);

    // Buscar loja do funcionário
    $empStoreId = getEmployeeStoreId($pdo, $employeeId);
    if ($empStoreId === null) {
        echo json_encode(['success' => false, 'message' => 'Funcionário não encontrado.']);
        exit();
    }
    // Restrição de gerentes à própria loja
    if (!userCanManageStore($empStoreId)) {
        echo json_encode(['success' => false, 'message' => 'Você não tem permissão para atribuir turnos a este funcionário.']);
        exit();
    }
    // Verificar duplicidade de turno na mesma data
    $dupStmt = $pdo->prepare('SELECT COUNT(*) FROM shifts WHERE employee_id = ? AND date = ?');
    $dupStmt->execute([$employeeId, $date]);
    if ($dupStmt->fetchColumn() > 0) {
        echo json_encode(['success' => false, 'message' => 'Já existe um turno para este funcionário nesta data.']);
        exit();
    }
    // Verificar preferências de disponibilidade (se existir tabela employee_preferences)
    $dow = date('w', strtotime($date));
    $prefStmt = $pdo->prepare('SELECT available_start_time, available_end_time FROM employee_preferences WHERE employee_id = ? AND day_of_week = ?');
    $prefStmt->execute([$employeeId, $dow]);
    if ($pref = $prefStmt->fetch(PDO::FETCH_ASSOC)) {
        if (strcmp($startTime, $pref['available_start_time']) < 0 || strcmp($endTime, $pref['available_end_time']) > 0) {
            echo json_encode(['success' => false, 'message' => 'Horário fora da preferência deste funcionário.']);
            exit();
        }
    }
    // Verificar limite de horas semanais, se a coluna existir
    $empStmt = $pdo->prepare('SELECT max_weekly_hours, name FROM employees WHERE id = ?');
    $empStmt->execute([$employeeId]);
    $emp = $empStmt->fetch(PDO::FETCH_ASSOC);
    $maxWeekly = $emp['max_weekly_hours'] ?? null;
    if ($maxWeekly) {
        $duration = (strtotime($date . ' ' . $endTime) - strtotime($date . ' ' . $startTime)) / 3600.0;
        // Calcular horas já agendadas na semana
        $timestamp = strtotime($date);
        $monday = date('Y-m-d', strtotime('monday this week', $timestamp));
        $sunday = date('Y-m-d', strtotime('sunday this week', $timestamp));
        $hoursStmt = $pdo->prepare('SELECT start_time, end_time, date FROM shifts WHERE employee_id = ? AND date BETWEEN ? AND ?');
        $hoursStmt->execute([$employeeId, $monday, $sunday]);
        $currentHours = 0.0;
        foreach ($hoursStmt->fetchAll(PDO::FETCH_ASSOC) as $sh) {
            $currentHours += max(0, (strtotime($sh['date'].' '.$sh['end_time']) - strtotime($sh['date'].' '.$sh['start_time'])) / 3600.0);
        }
        if ($currentHours + $duration > (float)$maxWeekly) {
            echo json_encode(['success' => false, 'message' => 'Atribuição excede o limite de horas semanais deste funcionário ('.$maxWeekly.'h).']);
            exit();
        }
    }
    // Inserir turno
    $ins = $pdo->prepare('INSERT INTO shifts (employee_id, date, start_time, end_time) VALUES (?, ?, ?, ?)');
    $ins->execute([$employeeId, $date, $startTime, $endTime]);
    $shiftId = $pdo->lastInsertId();
    // Notificar: in-app, e-mail e Slack
    if (function_exists('addNotification')) {
        $storeName = $pdo->query('SELECT name FROM stores WHERE id = '.$empStoreId)->fetchColumn();
        $msg = formatShiftMsg($date, $startTime, $endTime, $storeName, 'Turno atribuído');
        addNotification($pdo, $employeeId, $msg, 'shift_created');
    }
    // Também enviar notificação pelos canais adicionais
    if (function_exists('notify_shift_event')) {
        $storeName = $storeName ?? ($pdo->query('SELECT name FROM stores WHERE id = '.$empStoreId)->fetchColumn());
        notify_shift_event($pdo, 'created', [
            'employee_id' => $employeeId,
            'date'        => $date,
            'start'       => $startTime,
            'end'         => $endTime,
            'store_name'  => $storeName,
        ]);
    }
    echo json_encode(['success' => true, 'shift' => [
        'id' => $shiftId,
        'employee_id' => $employeeId,
        'date' => $date,
        'start_time' => $startTime,
        'end_time' => $endTime
    ]]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Erro: '.$e->getMessage()]);
}