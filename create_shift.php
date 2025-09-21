<?php
// create_shift_restricted.php
// Script to create shifts with additional validation: past-date checks and per-store restrictions.
// Requires utility functions defined in config.php: assertNotPastDate, userCanManageStore, getEmployeeStoreId.

require_once 'config.php';
// Include notifications if the alert system is enabled
@require_once 'notifications_helpers.php';

requirePrivileged();
$user = currentUser();
$role = $user['role'];
$storeId = $user['store_id'] ?? null;

header('Content-Type: application/json');

// Retrieve parameters
$employeeId = isset($_POST['employee_id']) ? (int)$_POST['employee_id'] : 0;
$date       = trim($_POST['date'] ?? '');
$startTime  = trim($_POST['start_time'] ?? '');
$endTime    = trim($_POST['end_time'] ?? '');

if (!$employeeId || !$date || !$startTime || !$endTime) {
    echo json_encode(['success' => false, 'message' => 'Incomplete parameters.']);
    exit();
}

try {
    // Prevent creation for past dates
    assertNotPastDate($date);

    // Fetch the employee's store
    $empStoreId = getEmployeeStoreId($pdo, $employeeId);
    if ($empStoreId === null) {
        echo json_encode(['success' => false, 'message' => 'Employee not found.']);
        exit();
    }
    // Restrict managers to their store
    if (!userCanManageStore($empStoreId)) {
        echo json_encode(['success' => false, 'message' => 'You do not have permission to assign shifts to this employee.']);
        exit();
    }
    // Ensure no duplicate shift on the same date
    $dupStmt = $pdo->prepare('SELECT COUNT(*) FROM shifts WHERE employee_id = ? AND date = ?');
    $dupStmt->execute([$employeeId, $date]);
    if ($dupStmt->fetchColumn() > 0) {
        echo json_encode(['success' => false, 'message' => 'A shift already exists for this employee on that date.']);
        exit();
    }
    // Check availability preferences (if table exists)
    $dow = date('w', strtotime($date));
    $prefStmt = $pdo->prepare('SELECT available_start_time, available_end_time FROM employee_preferences WHERE employee_id = ? AND day_of_week = ?');
    $prefStmt->execute([$employeeId, $dow]);
    if ($pref = $prefStmt->fetch(PDO::FETCH_ASSOC)) {
        if (strcmp($startTime, $pref['available_start_time']) < 0 || strcmp($endTime, $pref['available_end_time']) > 0) {
            echo json_encode(['success' => false, 'message' => 'Shift time is outside this employee’s preferred window.']);
            exit();
        }
    }
    // Check weekly hour limit, if defined
    $empStmt = $pdo->prepare('SELECT max_weekly_hours, name FROM employees WHERE id = ?');
    $empStmt->execute([$employeeId]);
    $emp = $empStmt->fetch(PDO::FETCH_ASSOC);
    $maxWeekly = $emp['max_weekly_hours'] ?? null;
    if ($maxWeekly) {
        $duration = (strtotime($date . ' ' . $endTime) - strtotime($date . ' ' . $startTime)) / 3600.0;
        // Calculate already scheduled hours for the week
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
            echo json_encode(['success' => false, 'message' => 'Assignment exceeds this employee’s weekly hour limit ('.$maxWeekly.'h).']);
            exit();
        }
    }
    // Insert shift
    $ins = $pdo->prepare('INSERT INTO shifts (employee_id, date, start_time, end_time) VALUES (?, ?, ?, ?)');
    $ins->execute([$employeeId, $date, $startTime, $endTime]);
    $shiftId = $pdo->lastInsertId();
    // Notification
    if (function_exists('addNotification')) {
        $storeName = $pdo->query('SELECT name FROM stores WHERE id = '.$empStoreId)->fetchColumn();
        $msg = formatShiftMsg($date, $startTime, $endTime, $storeName, 'Shift assigned');
        addNotification($pdo, $employeeId, $msg, 'shift_created');
    }
    echo json_encode(['success' => true, 'shift' => [
        'id' => $shiftId,
        'employee_id' => $employeeId,
        'date' => $date,
        'start_time' => $startTime,
        'end_time' => $endTime
    ]]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: '.$e->getMessage()]);
}
