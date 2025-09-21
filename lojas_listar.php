<?php
// lojas_listar.php
// Page for listing and creating stores
// Accessible only to administrators. Allows viewing all stores, adding
// a new store, and navigating to each store's management page.

require_once 'config.php';

// Restrict access to administrators only
requireAdmin();

// Ensure database connection
$pdo = $pdo ?? null;
if (!$pdo) {
    // If $pdo is not defined in config.php, create a new connection
    $dsn = 'sqlite:' . __DIR__ . '/db.sqlite';
    $pdo = new PDO($dsn);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
}

// Feedback message
$message = null;

// Handle new store creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_store'])) {
    $name = trim($_POST['name'] ?? '');
    $location = trim($_POST['location'] ?? '');
    if ($name === '') {
        $message = 'Store name is required.';
    } else {
        $stmtInsert = $pdo->prepare('INSERT INTO stores (name, location) VALUES (?, ?)');
        $stmtInsert->execute([$name, $location]);
        $message = 'Store created successfully.';
    }
}

// Buscar todas as lojas
$stmtStores = $pdo->query('SELECT id, name, location FROM stores ORDER BY name');
$stores = $stmtStores->fetchAll(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Stores – Escala Hillbillys</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
</head>
<body>
    <!-- Navigation bar -->
    <?php
        $activePage = 'lojas';
        require_once __DIR__ . '/navbar.php';
    ?>

    <div class="container mt-4">
        <h3>Stores</h3>
        <?php if ($message): ?>
            <div class="alert alert-info">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>
        <!-- Form to create a new store -->
        <div class="card mb-4">
            <div class="card-header bg-secondary text-white">Add New Store</div>
            <div class="card-body">
                <form method="post" class="row g-3">
                    <input type="hidden" name="create_store" value="1">
                    <div class="col-md-5">
                        <label for="storeName" class="form-label">Store Name</label>
                        <input type="text" class="form-control" id="storeName" name="name" required>
                    </div>
                    <div class="col-md-5">
                        <label for="storeLocation" class="form-label">Location</label>
                        <input type="text" class="form-control" id="storeLocation" name="location" placeholder="Optional">
                    </div>
                    <div class="col-md-2 d-flex align-items-end">
                        <button type="submit" class="btn btn-success w-100">Add</button>
                    </div>
                </form>
            </div>
        </div>
        <!-- Stores table -->
        <div class="table-responsive">
            <table id="storesTable" class="table table-striped">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <th>Location</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($stores as $st): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($st['id']); ?></td>
                            <td><?php echo htmlspecialchars($st['name']); ?></td>
                            <td><?php echo htmlspecialchars($st['location']); ?></td>
                            <td>
                                <a href="loja_gerenciar.php?store_id=<?php echo urlencode($st['id']); ?>" class="btn btn-sm btn-primary">Edit</a>
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
            $('#storesTable').DataTable({
                language: { url: 'https://cdn.datatables.net/plug-ins/1.13.6/i18n/en-GB.json' },
                order: [[1, 'asc']]
            });
        });
    </script>
</body>
</html>