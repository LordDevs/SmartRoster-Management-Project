<?php
// update_shift_restricted.php
// Script para atualizar turnos com validações adicionais de datas passadas e escopo por loja.
// Requer helpers: assertNotPastDate, userCanManageStore, getEmployeeStoreId.

require_once 'config.php';
@require_once 'notifications_helpers.php';

requirePrivileged();
$user = currentUser();
$role = $user['role'];
$storeId = $user['store_id'] ?? null;

header('Content-Type: application/json');

$id        = isset($_POST['id']) ? (int)$_POST['id'] : 0;
$employee  = isset($_POST['employee_id']) ? (int)$_POST['employee_id'] : null;
$date      = trim($_POST['date'] ?? '');
$startTime = trim($_POST['start_time'] ?? '');
$endTime   = trim($_POST['end_time'] ?? '');

if (!$id) {
    echo json_encode(['success' => false, 'message' => 'ID do turno ausente.']);
    exit();
}

try {
    // Carregar turno atual
    $q = $pdo->prepare('SELECT s.*, e.store_id AS emp_store, e.id AS employee_id, e.max_weekly_hours, e.name AS emp_name, st.name AS store_name FROM shifts s JOIN employees e ON e.id = s.employee_id LEFT JOIN stores st ON st.id = e.store_id WHERE s.id = ?');
    $q->execute([$id]);
    $current = $q->fetch(PDO::FETCH_ASSOC);
    if (!$current) {
        echo json_encode(['success' => false, 'message' => 'Turno não encontrado.']);
        exit();
    }
    // Se gerente, verificar se pode manipular esta loja
    if (!userCanManageStore((int)$current['emp_store'])) {
        echo json_encode(['success' => false, 'message' => 'Sem permissão para alterar este turno.']);
        exit();
    }
    // Determinar novos valores (fallback no atual se em branco)
    $newEmployee = $employee ?: (int)$current['employee_id'];
    $newDate     = $date ?: $current['date'];
    $newStart    = $startTime ?: $current['start_time'];
    $newEnd      = $endTime ?: $current['end_time'];
    // Bloquear datas passadas
    assertNotPastDate($newDate);
    // Se mudou de funcionário, validar novo funcionário e permissão
    if ($employee) {
        $emp = $pdo->prepare('SELECT id, store_id, max_weekly_hours, name FROM employees WHERE id = ?');
        $emp->execute([$newEmployee]);
        $empData = $emp->fetch(PDO::FETCH_ASSOC);
        if (!$empData) {
            echo json_encode(['success' => false, 'message' => 'Funcionário inválido.']);
            exit();
        }
        if (!userCanManageStore((int)$empData['store_id'])) {
            echo json_encode(['success' => false, 'message' => 'Sem permissão para atribuir este funcionário.']);
            exit();
        }
    } else {
        $empData = [
            'id' => $current['employee_id'],
            'store_id' => $current['emp_store'],
            'max_weekly_hours' => $current['max_weekly_hours'],
            'name' => $current['emp_name'],
            'store_name' => $current['store_name']
        ];
    }
    // Verificar duplicidade na mesma data para o novo funcionário
    $dup = $pdo->prepare('SELECT COUNT(*) FROM shifts WHERE employee_id = ? AND date = ? AND id <> ?');
    $dup->execute([$newEmployee, $newDate, $id]);
    if ($dup->fetchColumn() > 0) {
        echo json_encode(['success' => false, 'message' => 'Já existe um turno deste funcionário nesta data.']);
        exit();
    }
    // Verificar preferências de disponibilidade
    $dow = date('w', strtotime($newDate));
    $prefStmt = $pdo->prepare('SELECT available_start_time, available_end_time FROM employee_preferences WHERE employee_id = ? AND day_of_week = ?');
    $prefStmt->execute([$newEmployee, $dow]);
    if ($p = $prefStmt->fetch(PDO::FETCH_ASSOC)) {
        if (strcmp($newStart, $p['available_start_time']) < 0 || strcmp($newEnd, $p['available_end_time']) > 0) {
            echo json_encode(['success' => false, 'message' => 'Novo horário está fora da preferência do funcionário.']);
            exit();
        }
    }
    // Verificar limite de horas semanais se existir
    $maxWeekly = $empData['max_weekly_hours'] ?? null;
    if ($maxWeekly) {
        $newDuration = (strtotime($newDate.' '.$newEnd) - strtotime($newDate.' '.$newStart)) / 3600.0;
        $timestamp = strtotime($newDate);
        $monday = date('Y-m-d', strtotime('monday this week', $timestamp));
        $sunday = date('Y-m-d', strtotime('sunday this week', $timestamp));
        $stmtHours = $pdo->prepare('SELECT id, date, start_time, end_time FROM shifts WHERE employee_id = ? AND date BETWEEN ? AND ? AND id <> ?');
        $stmtHours->execute([$newEmployee, $monday, $sunday, $id]);
        $hoursSum = 0.0;
        foreach ($stmtHours->fetchAll(PDO::FETCH_ASSOC) as $s) {
            $hoursSum += max(0, (strtotime($s['date'].' '.$s['end_time']) - strtotime($s['date'].' '.$s['start_time'])) / 3600.0);
        }
        if ($hoursSum + $newDuration > (float)$maxWeekly) {
            echo json_encode(['success' => false, 'message' => 'Atualização excede o limite de horas semanais deste funcionário ('.$maxWeekly.'h).']);
            exit();
        }
    }
    // Atualizar turno
    $up = $pdo->prepare('UPDATE shifts SET employee_id = ?, date = ?, start_time = ?, end_time = ? WHERE id = ?');
    $up->execute([$newEmployee, $newDate, $newStart, $newEnd, $id]);
    // Notificação
    if (function_exists('addNotification')) {
        $storeName = $empData['store_name'] ?? $current['store_name'] ?? null;
        $msg = formatShiftMsg($newDate, $newStart, $newEnd, $storeName, 'Turno atualizado');
        addNotification($pdo, (int)$newEmployee, $msg, 'shift_updated');
    }
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Erro: '.$e->getMessage()]);
}