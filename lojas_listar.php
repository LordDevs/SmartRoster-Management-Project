<?php
// lojas_listar.php
// Página para listar e criar novas lojas
// Esta página está acessível apenas para administradores. Ela permite
// visualizar todas as lojas cadastradas, adicionar uma nova loja e
// acessar a página de gerenciamento de cada loja individualmente.

require_once 'config.php';

// Garantir que somente administradores possam acessar
requireAdmin();

// Conectar ao banco de dados
$pdo = $pdo ?? null;
if (!$pdo) {
    // Se por alguma razão $pdo não estiver definido em config.php, crie uma nova conexão
    $dsn = 'sqlite:' . __DIR__ . '/db.sqlite';
    $pdo = new PDO($dsn);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
}

// Mensagem de retorno para feedback
$message = null;

// Processar criação de nova loja
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_store'])) {
    $name = trim($_POST['name'] ?? '');
    $location = trim($_POST['location'] ?? '');
    if ($name === '') {
        $message = 'O nome da loja é obrigatório.';
    } else {
        $stmtInsert = $pdo->prepare('INSERT INTO stores (name, location) VALUES (?, ?)');
        $stmtInsert->execute([$name, $location]);
        $message = 'Loja criada com sucesso.';
    }
}

// Buscar todas as lojas
$stmtStores = $pdo->query('SELECT id, name, location FROM stores ORDER BY name');
$stores = $stmtStores->fetchAll(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lojas – Escala Hillbillys</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
</head>
<body>
    <!-- Barra de navegação -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container-fluid">
            <a class="navbar-brand" href="dashboard.php">Escala Hillbillys</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Alternar navegação">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                    <li class="nav-item"><a class="nav-link" href="usuarios_listar.php">Funcionários</a></li>
                    <li class="nav-item"><a class="nav-link" href="escala_listar.php">Escalas</a></li>
                    <li class="nav-item"><a class="nav-link" href="escala_calendario.php">Calendário</a></li>
                    <li class="nav-item"><a class="nav-link" href="ponto_listar.php">Pontos</a></li>
                    <li class="nav-item"><a class="nav-link" href="relatorios.php">Relatórios</a></li>
                    <li class="nav-item"><a class="nav-link" href="desempenho.php">Desempenho</a></li>
                    <li class="nav-item"><a class="nav-link" href="analytics.php">Métricas</a></li>
                    <li class="nav-item"><a class="nav-link active" aria-current="page" href="lojas_listar.php">Lojas</a></li>
                </ul>
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item"><a class="nav-link" href="logout.php">Sair</a></li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <h3>Lojas</h3>
        <?php if ($message): ?>
            <div class="alert alert-info">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>
        <!-- Formulário para criar nova loja -->
        <div class="card mb-4">
            <div class="card-header bg-secondary text-white">Adicionar Nova Loja</div>
            <div class="card-body">
                <form method="post" class="row g-3">
                    <input type="hidden" name="create_store" value="1">
                    <div class="col-md-5">
                        <label for="storeName" class="form-label">Nome da Loja</label>
                        <input type="text" class="form-control" id="storeName" name="name" required>
                    </div>
                    <div class="col-md-5">
                        <label for="storeLocation" class="form-label">Localização</label>
                        <input type="text" class="form-control" id="storeLocation" name="location" placeholder="Opcional">
                    </div>
                    <div class="col-md-2 d-flex align-items-end">
                        <button type="submit" class="btn btn-success w-100">Adicionar</button>
                    </div>
                </form>
            </div>
        </div>
        <!-- Tabela de lojas -->
        <div class="table-responsive">
            <table id="storesTable" class="table table-striped">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Nome</th>
                        <th>Localização</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($stores as $st): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($st['id']); ?></td>
                            <td><?php echo htmlspecialchars($st['name']); ?></td>
                            <td><?php echo htmlspecialchars($st['location']); ?></td>
                            <td>
                                <a href="loja_gerenciar.php?store_id=<?php echo urlencode($st['id']); ?>" class="btn btn-sm btn-primary">Editar</a>
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
                language: { url: 'https://cdn.datatables.net/plug-ins/1.13.6/i18n/pt-BR.json' },
                order: [[1, 'asc']]
            });
        });
    </script>
</body>
</html>