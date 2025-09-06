<?php
// export_performance.php – exporta relatório de desempenho em CSV
require_once 'config.php';
requirePrivileged();

$role = $_SESSION['user_role'];
if ($role === 'manager') {
    $user = currentUser();
    $storeId = $user['store_id'] ?? null;
    $stmt = $pdo->prepare(
        "SELECT e.name,
                COUNT(te.id) AS total_entries,
                SUM(CASE WHEN te.clock_out IS NOT NULL THEN 1 ELSE 0 END) AS completed_entries,
                SUM(CASE WHEN te.justification IS NOT NULL AND te.justification != '' THEN 1 ELSE 0 END) AS late_entries,
                SUM(strftime('%s', COALESCE(te.clock_out, CURRENT_TIMESTAMP)) - strftime('%s', te.clock_in)) AS seconds_worked
         FROM employees e
         LEFT JOIN time_entries te ON e.id = te.employee_id
         WHERE e.store_id = ?
         GROUP BY e.id, e.name
         ORDER BY e.name"
    );
    $stmt->execute([$storeId]);
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
} else {
    $stmt = $pdo->query(
        "SELECT e.name,
                COUNT(te.id) AS total_entries,
                SUM(CASE WHEN te.clock_out IS NOT NULL THEN 1 ELSE 0 END) AS completed_entries,
                SUM(CASE WHEN te.justification IS NOT NULL AND te.justification != '' THEN 1 ELSE 0 END) AS late_entries,
                SUM(strftime('%s', COALESCE(te.clock_out, CURRENT_TIMESTAMP)) - strftime('%s', te.clock_in)) AS seconds_worked
         FROM employees e
         LEFT JOIN time_entries te ON e.id = te.employee_id
         GROUP BY e.id, e.name
         ORDER BY e.name"
    );
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
// Prepare CSV output
header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="performance.csv"');
$output = fopen('php://output', 'w');
// Header row
fputcsv($output, ['Funcionário', 'Horas Totais', 'Total de Registros', 'Entradas Completadas', 'Registros com Justificativa']);
foreach ($data as $row) {
    $hours = round(($row['seconds_worked'] ?? 0) / 3600, 2);
    fputcsv($output, [
        $row['name'],
        $hours,
        $row['total_entries'],
        $row['completed_entries'],
        $row['late_entries']
    ]);
}
fclose($output);
exit();