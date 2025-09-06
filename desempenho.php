<?php
// desempenho.php – relatório de desempenho de funcionários
require_once 'config.php';
// Only allow managers and admins to view performance reports
requirePrivileged();

// Determine metrics: total hours, number of entries, number of completed entries, number of late entries (with justification), average duration
$role = $_SESSION['user_role'];
if ($role === 'manager') {
    $user = currentUser();
    $storeId = $user['store_id'] ?? null;
    $stmt = $pdo->prepare(
        "SELECT e.id, e.name,
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
    $metrics = $stmt->fetchAll(PDO::FETCH_ASSOC);
} else {
    // Admin sees all employees
    $stmt = $pdo->query(
        "SELECT e.id, e.name,
                COUNT(te.id) AS total_entries,
                SUM(CASE WHEN te.clock_out IS NOT NULL THEN 1 ELSE 0 END) AS completed_entries,
                SUM(CASE WHEN te.justification IS NOT NULL AND te.justification != '' THEN 1 ELSE 0 END) AS late_entries,
                SUM(strftime('%s', COALESCE(te.clock_out, CURRENT_TIMESTAMP)) - strftime('%s', te.clock_in)) AS seconds_worked
         FROM employees e
         LEFT JOIN time_entries te ON e.id = te.employee_id
         GROUP BY e.id, e.name
         ORDER BY e.name"
    );
    $metrics = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
// Calculate derived values (hours) for each employee
foreach ($metrics as &$row) {
    $row['hours'] = round(($row['seconds_worked'] ?? 0) / 3600, 2);
}
unset($row);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Desempenho – Escala Hillbillys</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container-fluid">
            <a class="navbar-brand" href="<?php echo ($role === 'manager') ? 'manager_dashboard.php' : 'dashboard.php'; ?>">Escala Hillbillys</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                    <li class="nav-item"><a class="nav-link" href="usuarios_listar.php">Funcionários</a></li>
                    <li class="nav-item"><a class="nav-link" href="escala_listar.php">Escalas</a></li>
                    <li class="nav-item"><a class="nav-link" href="escala_calendario.php">Calendário</a></li>
                    <li class="nav-item"><a class="nav-link" href="ponto_listar.php">Pontos</a></li>
                    <li class="nav-item"><a class="nav-link" href="relatorios.php">Relatórios</a></li>
                    <li class="nav-item"><a class="nav-link active" aria-current="page" href="desempenho.php">Desempenho</a></li>
                    <li class="nav-item"><a class="nav-link active" href="analytics.php">Métricas</a>
                    <li class="nav-item"><a class="nav-link" href="loja_gerenciar.php">Loja</a></li>
                </ul>
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item"><a class="nav-link" href="logout.php">Sair</a></li>
                </ul>
            </div>
        </div>
    </nav>
    <div class="container mt-4">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h3>Desempenho de Funcionários</h3>
            <a href="export_performance.php" class="btn btn-secondary">Exportar CSV</a>
        </div>
        <table class="table table-striped">
            <thead>
                <tr>
                    <th>Funcionário</th>
                    <th>Horas Totais</th>
                    <th>Total de Registros</th>
                    <th>Entradas Completadas</th>
                    <th>Registros com Justificativa</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($metrics as $row): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($row['name']); ?></td>
                        <td><?php echo htmlspecialchars($row['hours']); ?></td>
                        <td><?php echo htmlspecialchars($row['total_entries']); ?></td>
                        <td><?php echo htmlspecialchars($row['completed_entries']); ?></td>
                        <td><?php echo htmlspecialchars($row['late_entries']); ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($metrics)): ?>
                    <tr><td colspan="5">Nenhum dado disponível.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>