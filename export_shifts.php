<?php
// export_shifts.php – export shift schedules as CSV
require_once 'config.php';
requireAdmin();

$stmt = $pdo->query('SELECT s.id, s.date, s.start_time, s.end_time, e.name AS employee_name, s.created_at
                      FROM shifts s LEFT JOIN employees e ON s.employee_id = e.id
                      ORDER BY s.date, s.start_time');
$shifts = $stmt->fetchAll(PDO::FETCH_ASSOC);

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="shifts.csv"');
$output = fopen('php://output', 'w');
// CSV headers
fputcsv($output, ['ID', 'Date', 'Start', 'End', 'Employee', 'Created at']);
foreach ($shifts as $shift) {
    fputcsv($output, $shift);
}
fclose($output);
exit();
