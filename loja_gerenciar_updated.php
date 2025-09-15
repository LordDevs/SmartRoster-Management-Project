<?php
// loja_gerenciar_updated.php
// Página para gerenciar uma loja específica (editar nome/locação e listar funcionários).
// Aceita parâmetro GET 'store_id' para selecionar a loja. Apenas administradores e
// gerentes da loja podem acessar. Administradores podem gerenciar qualquer loja.

require_once 'config.php';

// Verificar login e permissões
requirePrivileged();
$currentUser = currentUser();
$userRole = $currentUser['role'];
$userStoreId = $currentUser['store_id'] ?? null;

// Obter ID da loja a ser gerenciada
$storeId = null;
if (isset($_GET['store_id'])) {
    $storeId = (int)$_GET['store_id'];
} elseif ($userRole === 'manager' && $userStoreId) {
    // Gerente assume a própria loja se não informar param
    $storeId = (int)$userStoreId;
} else {
    // Admin sem parâmetro: carregar a primeira loja
    $storeId = (int)$pdo->query('SELECT id FROM stores ORDER BY id LIMIT 1')->fetchColumn();
}

// Carregar dados da loja
$stmtStore = $pdo->prepare('SELECT * FROM stores WHERE id = ?');
$stmtStore->execute([$storeId]);
$store = $stmtStore->fetch(PDO::FETCH_ASSOC);
if (!$store) {
    die('Loja não encontrada.');
}

// Verificar se usuário pode gerenciar esta loja: gerentes só podem gerenciar sua loja
if ($userRole === 'manager' && $userStoreId !== $storeId) {
    die('Você não tem permissão para gerenciar esta loja.');
}

// Processar atualização de dados da loja (somente admin)
$message = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_store'])) {
    // Apenas admins podem editar loja
    if ($userRole !== 'admin') {
        die('Apenas administradores podem editar lojas.');
    }
    $name = trim($_POST['name'] ?? '');
    $location = trim($_POST['location'] ?? '');
    if ($name !== '') {
        $stmtUpd = $pdo->prepare('UPDATE stores SET name = ?, location = ? WHERE id = ?');
        $stmtUpd->execute([$name, $location, $storeId]);
        $message = 'Dados da loja atualizados com sucesso.';
        // Recarregar
        $stmtStore->execute([$storeId]);
        $store = $stmtStore->fetch(PDO::FETCH_ASSOC);
    }
}

// Buscar funcionários desta loja
$stmtEmp = $pdo->prepare('SELECT id, name, email, phone FROM employees WHERE store_id = ? ORDER BY name');
$stmtEmp->execute([$storeId]);
$employees = $stmtEmp->fetchAll(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gerenciar Loja – Escala Hillbillys</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
</head>
<body>
    <?php
        // página loja/gerenciar
        $activePage = ($userRole === 'admin') ? 'lojas' : 'loja';
        require_once __DIR__ . '/navbar.php';
    ?>
    <div class="container mt-4">
        <h3>Gerenciar Loja</h3>
        <?php if ($message): ?>
            <div class="alert alert-success">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>
        <form method="post" class="card mb-4">
            <div class="card-header bg-secondary text-white">Dados da Loja</div>
            <div class="card-body row g-3">
                <input type="hidden" name="update_store" value="1">
                <div class="col-md-6">
                    <label for="name" class="form-label">Nome</label>
                    <input type="text" class="form-control" id="name" name="name" value="<?php echo htmlspecialchars($store['name']); ?>" required <?php echo ($userRole !== 'admin') ? 'readonly' : ''; ?>>
                </div>
                <div class="col-md-6">
                    <label for="location" class="form-label">Localização</label>
                    <input type="text" class="form-control" id="location" name="location" value="<?php echo htmlspecialchars($store['location']); ?>" <?php echo ($userRole !== 'admin') ? 'readonly' : ''; ?>>
                </div>
                <?php if ($userRole === 'admin'): ?>
                <div class="col-md-12">
                    <button type="submit" class="btn btn-primary">Salvar</button>
                </div>
                <?php endif; ?>
            </div>
        </form>
        <h4>Funcionários da Loja</h4>
        <div class="table-responsive">
            <table id="employeesTable" class="table table-striped">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Nome</th>
                        <th>Email</th>
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
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
    <script>
        $(document).ready(function() {
            $('#employeesTable').DataTable({
                language: { url: 'https://cdn.datatables.net/plug-ins/1.13.6/i18n/pt-BR.json' },
                order: [[1, 'asc']]
            });
        });
    </script>
</body>
</html>