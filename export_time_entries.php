<?php
// export_time_entries.php – export time entry records as CSV
require_once 'config.php';
requireAdmin();

$stmt = $pdo->query('SELECT te.id, e.name AS employee_name, te.clock_in, te.clock_out, te.justification, te.created_at
                      FROM time_entries te
                      JOIN employees e ON te.employee_id = e.id
                      ORDER BY te.clock_in DESC');
$entries = $stmt->fetchAll(PDO::FETCH_ASSOC);

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="time_entries.csv"');
$output = fopen('php://output', 'w');
fputcsv($output, ['ID', 'Employee', 'Clock In', 'Clock Out', 'Justification', 'Created at']);
foreach ($entries as $entry) {
    fputcsv($output, $entry);
}
fclose($output);
exit();
