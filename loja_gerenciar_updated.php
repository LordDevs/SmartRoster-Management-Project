<?php
// loja_gerenciar_updated.php
// Page to manage a specific store (edit name/location and list employees).
// Accepts GET parameter 'store_id' to select the store. Only administrators and
// store managers may access. Administrators can manage any store.

require_once 'config.php';

// Validate login and permissions
requirePrivileged();
$currentUser = currentUser();
$userRole = $currentUser['role'];
$userStoreId = $currentUser['store_id'] ?? null;

// Determine which store will be managed
$storeId = null;
if (isset($_GET['store_id'])) {
    $storeId = (int)$_GET['store_id'];
} elseif ($userRole === 'manager' && $userStoreId) {
    // Manager defaults to their own store when parameter is missing
    $storeId = (int)$userStoreId;
} else {
    // Admin without parameter: load the first store
    $storeId = (int)$pdo->query('SELECT id FROM stores ORDER BY id LIMIT 1')->fetchColumn();
}

// Load store data
$stmtStore = $pdo->prepare('SELECT * FROM stores WHERE id = ?');
$stmtStore->execute([$storeId]);
$store = $stmtStore->fetch(PDO::FETCH_ASSOC);
if (!$store) {
    die('Store not found.');
}

// Ensure the user can manage this store: managers are limited to their store
if ($userRole === 'manager' && $userStoreId !== $storeId) {
    die('You do not have permission to manage this store.');
}

// Process store updates (admins only)
$message = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_store'])) {
    // Only admins may edit store details
    if ($userRole !== 'admin') {
        die('Only administrators can edit stores.');
    }
    $name = trim($_POST['name'] ?? '');
    $location = trim($_POST['location'] ?? '');
    if ($name !== '') {
        $stmtUpd = $pdo->prepare('UPDATE stores SET name = ?, location = ? WHERE id = ?');
        $stmtUpd->execute([$name, $location, $storeId]);
        $message = 'Store details updated successfully.';
        // Recarregar
        $stmtStore->execute([$storeId]);
        $store = $stmtStore->fetch(PDO::FETCH_ASSOC);
    }
}

// Fetch employees for this store
$stmtEmp = $pdo->prepare('SELECT id, name, email, phone FROM employees WHERE store_id = ? ORDER BY name');
$stmtEmp->execute([$storeId]);
$employees = $stmtEmp->fetchAll(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Store – Escala Hillbillys</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
</head>
<body>
    <?php
        // store management page
        $activePage = ($userRole === 'admin') ? 'lojas' : 'loja';
        require_once __DIR__ . '/navbar.php';
    ?>
    <div class="container mt-4">
        <h3>Manage Store</h3>
        <?php if ($message): ?>
            <div class="alert alert-success">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>
        <form method="post" class="card mb-4">
            <div class="card-header bg-secondary text-white">Store Details</div>
            <div class="card-body row g-3">
                <input type="hidden" name="update_store" value="1">
                <div class="col-md-6">
                    <label for="name" class="form-label">Name</label>
                    <input type="text" class="form-control" id="name" name="name" value="<?php echo htmlspecialchars($store['name']); ?>" required <?php echo ($userRole !== 'admin') ? 'readonly' : ''; ?>>
                </div>
                <div class="col-md-6">
                    <label for="location" class="form-label">Location</label>
                    <input type="text" class="form-control" id="location" name="location" value="<?php echo htmlspecialchars($store['location']); ?>" <?php echo ($userRole !== 'admin') ? 'readonly' : ''; ?>>
                </div>
                <?php if ($userRole === 'admin'): ?>
                <div class="col-md-12">
                    <button type="submit" class="btn btn-primary">Save</button>
                </div>
                <?php endif; ?>
            </div>
        </form>
        <h4>Store Employees</h4>
        <div class="table-responsive">
            <table id="employeesTable" class="table table-striped">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Phone</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($employees as $emp): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($emp['id']); ?></td>
                            <td><?php echo htmlspecialchars($emp['name']); ?></td>
                            <td><?php echo htmlspecialchars($emp['email']); ?></td>
                            <td><?php echo htmlspecialchars($emp['phone']); ?></td>
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
            $('#employeesTable').DataTable({
                language: { url: 'https://cdn.datatables.net/plug-ins/1.13.6/i18n/en-GB.json' },
                order: [[1, 'asc']]
            });
        });
    </script>
</body>
</html>