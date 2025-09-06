<?php
// loja_gerenciar.php – permite que gerentes (e administradores) visualizem e atualizem informações da loja.
// Gerentes podem editar apenas a própria loja; administradores poderiam, em uma versão estendida,
// selecionar qual loja editar.  Também listamos os funcionários vinculados à loja.

require_once 'config.php';
requirePrivileged();

$role = $_SESSION['user_role'];
$currentUser = currentUser();
// Determinar a loja atual
if ($role === 'manager') {
    $storeId = $currentUser['store_id'] ?? null;
} else {
    // Administradores podem escolher a loja via GET; se não fornecer, usar primeira loja
    $storeId = isset($_GET['store_id']) ? (int)$_GET['store_id'] : null;
    if (!$storeId) {
        $storeId = $pdo->query('SELECT id FROM stores ORDER BY id LIMIT 1')->fetchColumn();
    }
}

// Carregar dados da loja
$stmtStore = $pdo->prepare('SELECT * FROM stores WHERE id = ?');
$stmtStore->execute([$storeId]);
$store = $stmtStore->fetch(PDO::FETCH_ASSOC);
if (!$store) {
    die('Loja não encontrada.');
}

// Processar atualização de dados da loja
$message = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $location = trim($_POST['location'] ?? '');
    if ($name !== '') {
        $stmtUpd = $pdo->prepare('UPDATE stores SET name = ?, location = ? WHERE id = ?');
        $stmtUpd->execute([$name, $location, $storeId]);
        $message = 'Dados da loja atualizados com sucesso.';
        // Recarregar dados da loja
        $stmtStore->execute([$storeId]);
        $store = $stmtStore->fetch(PDO::FETCH_ASSOC);
    }
}

// Buscar funcionários da loja
$stmtEmp = $pdo->prepare('SELECT id, name, email, phone FROM employees WHERE store_id = ? ORDER BY name');
$stmtEmp->execute([$storeId]);
$employees = $stmtEmp->fetchAll(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Gerenciar Loja – Escala Hillbillys</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <!-- DataTables CSS para tabela de funcionários -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css" />
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
                    <li class="nav-item"><a class="nav-link" href="ponto_listar.php">Pontos</a></li>
                    <li class="nav-item"><a class="nav-link" href="relatorios.php">Relatórios</a></li>
                    <li class="nav-item"><a class="nav-link" href="desempenho.php">Desempenho</a></li>
                    <li class="nav-item"><a class="nav-link active" href="analytics.php">Métricas</a>
                    <li class="nav-item"><a class="nav-link active" aria-current="page" href="loja_gerenciar.php">Loja</a></li>
                </ul>
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item"><a class="nav-link" href="logout.php">Sair</a></li>
                </ul>
            </div>
        </div>
    </nav>
    <div class="container mt-4">
        <h3>Gerenciar Loja</h3>
        <?php if ($message): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>
        <form method="post" class="mb-4">
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">Nome da Loja</label>
                    <input type="text" class="form-control" name="name" value="<?php echo htmlspecialchars($store['name']); ?>" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Localização</label>
                    <input type="text" class="form-control" name="location" value="<?php echo htmlspecialchars($store['location']); ?>">
                </div>
                <div class="col-md-4 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary">Salvar</button>
                </div>
            </div>
        </form>
        <h4>Funcionários da Loja</h4>
        <div class="table-responsive">
            <table id="employeesTable" class="table table-striped">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Nome</th>
                        <th>E-mail</th>
                        <th>Telefone</th>
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
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function () {
        $('#employeesTable').DataTable({
            language: { url: 'https://cdn.datatables.net/plug-ins/1.13.6/i18n/pt-BR.json' },
            order: [[1, 'asc']]
        });
    });
    </script>
</body>
</html>