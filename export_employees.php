<?php
// export_employees.php – exportar lista de funcionários como CSV
require_once 'config.php';
requireAdmin();

$stmt = $pdo->query('SELECT id, name, email, phone, created_at FROM employees ORDER BY id');
$employees = $stmt->fetchAll(PDO::FETCH_ASSOC);

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="funcionarios.csv"');
$output = fopen('php://output', 'w');
// CSV headers
fputcsv($output, ['ID', 'Nome', 'Email', 'Telefone', 'Criado em']);
foreach ($employees as $emp) {
    fputcsv($output, $emp);
}
fclose($output);
exit();