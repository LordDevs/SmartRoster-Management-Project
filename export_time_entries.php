<?php
// export_time_entries.php – exportar registros de ponto como CSV
require_once 'config.php';
requireAdmin();

$stmt = $pdo->query('SELECT te.id, e.name AS employee_name, te.clock_in, te.clock_out, te.justification, te.created_at
                      FROM time_entries te
                      JOIN employees e ON te.employee_id = e.id
                      ORDER BY te.clock_in DESC');
$entries = $stmt->fetchAll(PDO::FETCH_ASSOC);

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="pontos.csv"');
$output = fopen('php://output', 'w');
fputcsv($output, ['ID', 'Funcionário', 'Entrada', 'Saída', 'Justificativa', 'Criado em']);
foreach ($entries as $entry) {
    fputcsv($output, $entry);
}
fclose($output);
exit();