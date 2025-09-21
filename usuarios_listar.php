<?php
// usuarios_listar.php – employee listing
require_once __DIR__ . '/config.php';
requirePrivileged();

// Get all employees along with their store name.  Managers see only their store.
if ($_SESSION['user_role'] === 'manager') {
    $currentUser = currentUser();
        $stmt = $pdo->prepare('SELECT e.*, s.name AS store_name, u.role AS user_role FROM employees e
                               LEFT JOIN stores s ON e.store_id = s.id
                               LEFT JOIN users u ON u.employee_id = e.id
                               WHERE e.store_id = ?
                               ORDER BY e.name');
    $stmt->execute([$currentUser['store_id']]);
    $employees = $stmt->fetchAll(PDO::FETCH_ASSOC);
} else {
        $stmt = $pdo->query('SELECT e.*, s.name AS store_name, u.role AS user_role FROM employees e
                             LEFT JOIN stores s ON e.store_id = s.id
                             LEFT JOIN users u ON u.employee_id = e.id
                             ORDER BY e.name');
    $employees = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Employees – Escala Hillbillys</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="style.css">
    <!-- DataTables CSS for enhanced tables -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css" />
</head>
<body>
    <?php
        $activePage = 'usuarios';
        require_once __DIR__ . '/navbar.php';
    ?>

    <div class="container mt-4">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h3>Employees</h3>
            <div>
                <a href="export_employees.php" class="btn btn-secondary me-2">Export CSV</a>
                <a href="usuario_criar.php" class="btn btn-success">Add Employee</a>
            </div>
        </div>
        <table id="employeesTable" class="table table-striped">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Phone</th>
                    <th>Store</th>
                    <th>PPSN</th>
                    <th>IRP</th>
                    <th>Pay/h (€)</th>
                    <th>Role</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($employees as $emp): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($emp['id']); ?></td>
                        <td><?php echo htmlspecialchars($emp['name']); ?></td>
                        <td><?php echo htmlspecialchars($emp['email']); ?></td>
                        <td><?php echo htmlspecialchars($emp['phone']); ?></td>
                        <td><?php echo htmlspecialchars($emp['store_name'] ?? ''); ?></td>
                        <td><?php echo htmlspecialchars($emp['ppsn'] ?? ''); ?></td>
                        <td><?php echo htmlspecialchars($emp['irp'] ?? ''); ?></td>
                        <td><?php echo $emp['hourly_rate'] !== null ? number_format($emp['hourly_rate'], 2) : ''; ?></td>
                        <td>
                            <?php
                                if (isset($emp['user_role'])) {
                                    echo htmlspecialchars($emp['user_role'] === 'manager' ? 'Manager' : 'Employee');
                                } else {
                                    echo '';
                                }
                            ?>
                        </td>
                        <td>
                            <a href="usuario_editar.php?id=<?php echo $emp['id']; ?>" class="btn btn-sm btn-primary">Edit</a>
                            <a href="usuario_excluir.php?id=<?php echo $emp['id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure you want to delete this employee?');">Delete</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Include jQuery and DataTables -->
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script>
    // Initialize DataTables for employees list
    document.addEventListener('DOMContentLoaded', function() {
        $('#employeesTable').DataTable({
            language: {
                url: 'https://cdn.datatables.net/plug-ins/1.13.6/i18n/en-GB.json'
            },
            order: [[1, 'asc']]
        });
    });
    </script>
</body>
</html>