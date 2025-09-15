
<?php
require_once __DIR__ . '/config.php';
requireLogin();

// Fetch time entries with employee names.  Managers see only entries for their store.
$role = $_SESSION['user_role'];
if ($role === 'manager') {
    $user = currentUser();
    $storeId = $user['store_id'] ?? null;
    $stmt = $pdo->prepare('SELECT te.id, te.employee_id, te.clock_in, te.clock_out, te.justification, e.name AS employee_name
                            FROM time_entries te
                            JOIN employees e ON te.employee_id = e.id
                            WHERE e.store_id = ?
                            ORDER BY te.clock_in DESC');
    $stmt->execute([$storeId]);
    $entries = $stmt->fetchAll(PDO::FETCH_ASSOC);
} else {
    $stmt = $pdo->query('SELECT te.id, te.employee_id, te.clock_in, te.clock_out, te.justification, e.name AS employee_name
                          FROM time_entries te
                          JOIN employees e ON te.employee_id = e.id
                          ORDER BY te.clock_in DESC');
    $entries = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Registros de Ponto – Escala Hillbillys</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="style.css">
    <!-- DataTables CSS for enhanced tables -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css" />
</head>
<body>
    <?php
        $activePage = 'pontos';
        require_once __DIR__ . '/navbar.php';
    ?>
    <div class="container mt-4">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h3>Registros de Ponto</h3>
            <div class="btn-group" role="group" aria-label="Ações">
                <a href="export_time_entries.php" class="btn btn-secondary">Exportar CSV</a>
                <a href="ponto_registrar.php" class="btn btn-success">Registrar Ponto</a>
            </div>
        </div>
        <table id="timeEntriesTable" class="table table-striped">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Funcionário</th>
                    <th>Entrada</th>
                    <th>Saída</th>
                    <th>Justificativa</th>
                    <th>Duração (h)</th>
                    <th>Ações</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($entries as $entry): ?>
                    <?php
                        $duration = '';
                        if ($entry['clock_out']) {
                            $start = strtotime($entry['clock_in']);
                            $end = strtotime($entry['clock_out']);
                            $duration = round(($end - $start) / 3600, 2);
                        }
                    ?>
                    <tr>
                        <td><?php echo htmlspecialchars($entry['id']); ?></td>
                        <td><?php echo htmlspecialchars($entry['employee_name']); ?></td>
                        <td><?php echo htmlspecialchars($entry['clock_in']); ?></td>
                        <td><?php echo htmlspecialchars($entry['clock_out']); ?></td>
                        <td><?php echo htmlspecialchars($entry['justification']); ?></td>
                        <td><?php echo $duration; ?></td>
                        <td>
                            <?php if ($_SESSION['user_role'] !== 'employee'): ?>
                                <a href="ponto_corrigir.php?id=<?php echo $entry['id']; ?>" class="btn btn-sm btn-warning">Corrigir</a>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <!-- jQuery and DataTables for interactive table -->
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        $('#timeEntriesTable').DataTable({
            language: {
                url: 'https://cdn.datatables.net/plug-ins/1.13.6/i18n/pt-BR.json'
            },
            order: [[2, 'desc']]
        });
    });
    </script>
</body>
</html>