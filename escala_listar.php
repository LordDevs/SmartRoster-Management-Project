<?php
// escala_listar_future.php
// List upcoming shifts. Shows only shifts dated today or later.
// Administrators see every store; managers are limited to their store.

require_once 'config.php';
requirePrivileged();
$currentUser = currentUser();
$role = $currentUser['role'];
$storeId = $currentUser['store_id'] ?? null;

// Fetch upcoming shifts
if ($role === 'manager' && $storeId) {
    $stmt = $pdo->prepare('SELECT s.id, s.date, s.start_time, s.end_time, e.name AS employee_name
                           FROM shifts s
                           JOIN employees e ON s.employee_id = e.id
                           WHERE e.store_id = ? AND s.date >= DATE("now")
                           ORDER BY s.date, s.start_time');
    $stmt->execute([$storeId]);
    $shifts = $stmt->fetchAll(PDO::FETCH_ASSOC);
} else {
    $stmt = $pdo->query('SELECT s.id, s.date, s.start_time, s.end_time, e.name AS employee_name
                         FROM shifts s
                         LEFT JOIN employees e ON s.employee_id = e.id
                         WHERE s.date >= DATE("now")
                         ORDER BY s.date, s.start_time');
    $shifts = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Fetch pending swap requests (upcoming only)
if ($role === 'manager' && $storeId) {
    $swapStmt = $pdo->prepare('SELECT sr.id, sr.shift_id, sr.status, e1.name AS requester, e2.name AS requested_to
                               FROM swap_requests sr
                               JOIN shifts s ON sr.shift_id = s.id
                               JOIN employees se ON s.employee_id = se.id
                               JOIN employees e1 ON sr.requested_by = e1.id
                               JOIN employees e2 ON sr.requested_to = e2.id
                               WHERE sr.status = "pending" AND se.store_id = ? AND s.date >= DATE("now")');
    $swapStmt->execute([$storeId]);
    $swapRequests = $swapStmt->fetchAll(PDO::FETCH_ASSOC);
} else {
    $swapStmt = $pdo->query('SELECT sr.id, sr.shift_id, sr.status, e1.name AS requester, e2.name AS requested_to
                             FROM swap_requests sr
                             JOIN shifts s ON sr.shift_id = s.id
                             JOIN employees e1 ON sr.requested_by = e1.id
                             JOIN employees e2 ON sr.requested_to = e2.id
                             WHERE sr.status = "pending" AND s.date >= DATE("now")');
    $swapRequests = $swapStmt->fetchAll(PDO::FETCH_ASSOC);
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Upcoming Shifts – Escala Hillbillys</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
</head>
<body>
    <?php
        $activePage = 'escalas';
        require_once __DIR__ . '/navbar.php';
    ?>
    <div class="container mt-4">
        <h3>Upcoming Shifts</h3>
        <div class="d-flex mb-3">
            <a href="export_shifts.php" class="btn btn-secondary me-2">Export CSV</a>
            <a href="escala_criar.php" class="btn btn-success me-2">New Shift</a>
            <a href="escala_criar.php?auto=1" class="btn btn-warning me-2">Generate Automatically</a>
            <a href="escala_sugestao.php" class="btn btn-info">AI Suggestion</a>
        </div>
        <!-- Swap requests -->
        <h5>Swap Requests:</h5>
        <?php if (empty($swapRequests)): ?>
            <p>No pending requests.</p>
        <?php else: ?>
            <ul class="list-group mb-4">
                <?php foreach ($swapRequests as $sr): ?>
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        <?php echo htmlspecialchars($sr['requester']) . ' wants to swap with ' . htmlspecialchars($sr['requested_to']); ?>
                        <div>
                            <a href="swap_action.php?id=<?php echo urlencode($sr['id']); ?>&action=approve" class="btn btn-sm btn-success ms-2">Approve</a>
                            <a href="swap_action.php?id=<?php echo urlencode($sr['id']); ?>&action=reject" class="btn btn-sm btn-danger ms-1">Reject</a>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
        <!-- Tabela de escalas -->
        <div class="table-responsive">
            <table id="shiftsTable" class="table table-striped">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Date</th>
                        <th>Start</th>
                        <th>End</th>
                        <th>Employee</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($shifts as $shift): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($shift['id']); ?></td>
                            <td><?php echo htmlspecialchars(date('d/m/Y', strtotime($shift['date']))); ?></td>
                            <td><?php echo htmlspecialchars($shift['start_time']); ?></td>
                            <td><?php echo htmlspecialchars($shift['end_time']); ?></td>
                            <td><?php echo htmlspecialchars($shift['employee_name']); ?></td>
                            <td>
                                <a href="escala_editar.php?id=<?php echo urlencode($shift['id']); ?>" class="btn btn-sm btn-primary">Edit</a>
                                <a href="escala_excluir.php?id=<?php echo urlencode($shift['id']); ?>" class="btn btn-sm btn-danger" onclick="return confirm('Delete this shift?');">Delete</a>
                                <a href="escala_trocar.php?id=<?php echo urlencode($shift['id']); ?>" class="btn btn-sm btn-warning">Swap</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
    <script>
        $(document).ready(function() {
            $('#shiftsTable').DataTable({
                language: { url: 'https://cdn.datatables.net/plug-ins/1.13.6/i18n/en-GB.json' },
                order: [[1, 'asc'], [2, 'asc']]
            });
        });
    </script>
</body>
</html>