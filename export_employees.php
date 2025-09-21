<?php
// export_employees.php – export the employee list as CSV
require_once 'config.php';
requireAdmin();

$stmt = $pdo->query('SELECT id, name, email, phone, created_at FROM employees ORDER BY id');
$employees = $stmt->fetchAll(PDO::FETCH_ASSOC);

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="employees.csv"');
$output = fopen('php://output', 'w');
// CSV headers
fputcsv($output, ['ID', 'Name', 'Email', 'Phone', 'Created at']);
foreach ($employees as $emp) {
    fputcsv($output, $emp);
}
fclose($output);
exit();
