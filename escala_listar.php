<?php
// escala_listar.php – lista de escalas (turnos)
require_once 'config.php';
// Only managers and admins should access this page. Employees should use their portal to see their own escalas.
requirePrivileged();

// Fetch all shifts with employee names.  Managers only see shifts for their store.
$role = $_SESSION['user_role'];
if ($role === 'manager') {
    $user = currentUser();
    $storeId = $user['store_id'] ?? null;
    // Only fetch shifts where the assigned employee belongs to this manager's store
    $stmt = $pdo->prepare('SELECT s.id, s.date, s.start_time, s.end_time, e.name AS employee_name
                           FROM shifts s
                           JOIN employees e ON s.employee_id = e.id
                           WHERE e.store_id = ?
                           ORDER BY s.date, s.start_time');
    $stmt->execute([$storeId]);
    $shifts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    // Fetch pending swap requests for this store
    $swapStmt = $pdo->prepare('SELECT sr.id, sr.shift_id, sr.status, e1.name AS requester, e2.name AS requested_to
                               FROM swap_requests sr
                               JOIN shifts s ON sr.shift_id = s.id
                               JOIN employees se ON s.employee_id = se.id
                               JOIN employees e1 ON sr.requested_by = e1.id
                               JOIN employees e2 ON sr.requested_to = e2.id
                               WHERE sr.status = "pending" AND se.store_id = ?');
    $swapStmt->execute([$storeId]);
    $swapRequests = $swapStmt->fetchAll(PDO::FETCH_ASSOC);
} else {
    // Admin sees all shifts and all pending swap requests
    $stmt = $pdo->query('SELECT s.id, s.date, s.start_time, s.end_time, e.name AS employee_name
                          FROM shifts s
                          LEFT JOIN employees e ON s.employee_id = e.id
                          ORDER BY s.date, s.start_time');
    $shifts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $swapStmt = $pdo->query('SELECT sr.id, sr.shift_id, sr.status, e1.name AS requester, e2.name AS requested_to
                              FROM swap_requests sr
                              JOIN employees e1 ON sr.requested_by = e1.id
                              JOIN employees e2 ON sr.requested_to = e2.id
                              WHERE sr.status = "pending"');
    $swapRequests = $swapStmt->fetchAll(PDO::FETCH_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Escalas – Escala Hillbillys</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <!-- DataTables CSS for interactive tables -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css" />
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container-fluid">
            <a class="navbar-brand" href="dashboard.php">Escala Hillbillys</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                    <li class="nav-item"><a class="nav-link" href="usuarios_listar.php">Funcionários</a></li>
                    <li class="nav-item"><a class="nav-link active" aria-current="page" href="escala_listar.php">Escalas</a></li>
                    <li class="nav-item"><a class="nav-link" href="escala_calendario.php">Calendário</a></li>
                    <li class="nav-item"><a class="nav-link" href="ponto_listar.php">Pontos</a></li>
                    <li class="nav-item"><a class="nav-link" href="relatorios.php">Relatórios</a></li>
                    <li class="nav-item"><a class="nav-link" href="desempenho.php">Desempenho</a></li>
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
            <h3>Escalas</h3>
            <div class="btn-group" role="group" aria-label="Ações">
                <a href="export_shifts.php" class="btn btn-secondary">Exportar CSV</a>
                <a href="escala_criar.php" class="btn btn-success">Nova Escala</a>
                <a href="escala_criar.php?auto=1" class="btn btn-warning">Gerar Automaticamente</a>
                <!-- Botão para sugestão automática com IA/Heurística -->
                <a href="escala_sugestao.php" class="btn btn-info">Sugestão IA</a>
            </div>
        </div>

        <?php if ($swapRequests): ?>
            <div class="alert alert-info">
                <strong>Solicitações de Troca:</strong>
                <ul class="mb-0">
                    <?php foreach ($swapRequests as $req): ?>
                        <li>
                            <?php echo htmlspecialchars($req['requester']); ?> deseja trocar com <?php echo htmlspecialchars($req['requested_to']); ?>
                            <a href="swap_action.php?id=<?php echo $req['id']; ?>&action=approve" class="btn btn-sm btn-success ms-2">Aprovar</a>
                            <a href="swap_action.php?id=<?php echo $req['id']; ?>&action=reject" class="btn btn-sm btn-danger ms-1">Rejeitar</a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <table id="shiftsTable" class="table table-striped">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Data</th>
                    <th>Início</th>
                    <th>Término</th>
                    <th>Funcionário</th>
                    <th>Ações</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($shifts as $shift): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($shift['id']); ?></td>
                        <td><?php echo htmlspecialchars($shift['date']); ?></td>
                        <td><?php echo htmlspecialchars($shift['start_time']); ?></td>
                        <td><?php echo htmlspecialchars($shift['end_time']); ?></td>
                        <td><?php echo htmlspecialchars($shift['employee_name'] ?? ''); ?></td>
                        <td>
                            <a href="escala_criar.php?edit=<?php echo $shift['id']; ?>" class="btn btn-sm btn-primary">Editar</a>
                            <a href="escala_criar.php?delete=<?php echo $shift['id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Excluir esta escala?');">Excluir</a>
                            <?php if ($shift['employee_name']): ?>
                                <a href="swap_request.php?shift_id=<?php echo $shift['id']; ?>" class="btn btn-sm btn-warning">Troca</a>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <!-- jQuery and DataTables JS for interactive tables -->
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script>
    // Initialize DataTables for the shifts table
    document.addEventListener('DOMContentLoaded', function() {
        // Use jQuery DataTables for enhanced search, sort, and pagination
        $('#shiftsTable').DataTable({
            language: {
                url: 'https://cdn.datatables.net/plug-ins/1.13.6/i18n/pt-BR.json'
            },
            order: [[1, 'asc'], [2, 'asc']]
        });
    });
    </script>
</body>
</html>