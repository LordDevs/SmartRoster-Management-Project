<?php
require_once 'config.php';
require_once 'authenticate.php';

$funcionario_id = $_POST['funcionario_id'] ?? null;
if (!$funcionario_id) {
    header('Location: employee_preferences.php');
    exit;
}

// Atualiza limites semanais e salário por hora
$weekly_max_hours = isset($_POST['weekly_max_hours']) ? floatval($_POST['weekly_max_hours']) : 40;
$hourly_rate      = isset($_POST['hourly_rate'])      ? floatval($_POST['hourly_rate'])      : 0;
$stmt = $conn->prepare('UPDATE funcionarios SET weekly_max_hours = ?, hourly_rate = ? WHERE id = ?');
$stmt->bind_param('ddi', $weekly_max_hours, $hourly_rate, $funcionario_id);
$stmt->execute();
$stmt->close();

// Preferências por dia
$preferences = $_POST['preferences'] ?? [];
for ($d = 0; $d < 7; $d++) {
    $pref = $preferences[$d] ?? [];
    $available_start = $pref['available_start'] ?? null;
    $available_end   = $pref['available_end']   ?? null;
    $max_hours       = $pref['max_hours_per_day'] ?? null;
    $min_rest        = $pref['min_rest_hours']  ?? null;

    $isEmpty = empty($available_start) && empty($available_end) && empty($max_hours) && empty($min_rest);
    if ($isEmpty) {
        // Remove preferência existente
        $del = $conn->prepare('DELETE FROM employee_preferences WHERE funcionario_id = ? AND day_of_week = ?');
        $del->bind_param('ii', $funcionario_id, $d);
        $del->execute();
        $del->close();
        continue;
    }
    // Insere ou atualiza
    $sql = 'INSERT INTO employee_preferences (funcionario_id, day_of_week, available_start, available_end, max_hours_per_day, min_rest_hours) VALUES (?,?,?,?,?,?) '
         . 'ON DUPLICATE KEY UPDATE available_start=VALUES(available_start), available_end=VALUES(available_end), max_hours_per_day=VALUES(max_hours_per_day), min_rest_hours=VALUES(min_rest_hours)';
    $ins = $conn->prepare($sql);
    $ins->bind_param('iissdd', $funcionario_id, $d, $available_start, $available_end, $max_hours, $min_rest);
    $ins->execute();
    $ins->close();
}

header('Location: employee_preferences.php');
exit;