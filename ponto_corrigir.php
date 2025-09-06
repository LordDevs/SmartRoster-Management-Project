<?php
// ponto_corrigir.php – permite a correção manual de registros de ponto pelos gerentes ou administradores.
// O usuário pode ajustar os horários de entrada/saída e fornecer uma justificativa. As correções
// são registradas com data, usuário que corrigiu e motivo.

require_once 'config.php';
requirePrivileged();

// Obter ID do registro de ponto a corrigir
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    header('Location: ponto_listar.php');
    exit();
}

// Buscar registro de ponto com nome do funcionário e loja
$stmt = $pdo->prepare('SELECT te.*, e.name AS employee_name, e.store_id FROM time_entries te JOIN employees e ON te.employee_id = e.id WHERE te.id = ?');
$stmt->execute([$id]);
$entry = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$entry) {
    header('Location: ponto_listar.php');
    exit();
}

// Verificar permissão do gerente: só pode corrigir pontos de funcionários de sua loja
$role = $_SESSION['user_role'];
if ($role === 'manager') {
    $currentUser = currentUser();
    $managerStoreId = $currentUser['store_id'] ?? null;
    if ($entry['store_id'] != $managerStoreId) {
        header('Location: ponto_listar.php');
        exit();
    }
}

// Processar atualização
$message = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $newClockIn  = $_POST['clock_in'] ?? '';
    $newClockOut = $_POST['clock_out'] ?? '';
    $reason      = trim($_POST['correction_reason'] ?? '');
    // Converter os inputs datetime-local para formato Y-m-d H:i:s
    $clockInFormatted  = $newClockIn ? date('Y-m-d H:i:s', strtotime($newClockIn)) : null;
    $clockOutFormatted = $newClockOut ? date('Y-m-d H:i:s', strtotime($newClockOut)) : null;
    $userId = $_SESSION['user_id'];
    // Atualizar o registro
    $stmtUpd = $pdo->prepare('UPDATE time_entries SET clock_in = ?, clock_out = ?, correction_reason = ?, corrected_at = CURRENT_TIMESTAMP, corrected_by = ? WHERE id = ?');
    $stmtUpd->execute([$clockInFormatted, $clockOutFormatted, $reason, $userId, $id]);
    $message = 'Registro atualizado com sucesso.';
    // Atualizar a entrada para exibir valores corrigidos
    $stmt->execute([$id]);
    $entry = $stmt->fetch(PDO::FETCH_ASSOC);
}

// Preparar valores para o formulário
// Para os campos datetime-local, é necessário usar o formato "Y-m-d\TH:i".
$valueClockIn = $entry['clock_in'] ? date('Y-m-d\TH:i', strtotime($entry['clock_in'])) : '';
$valueClockOut = $entry['clock_out'] ? date('Y-m-d\TH:i', strtotime($entry['clock_out'])) : '';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Corrigir Registro de Ponto – Escala Hillbillys</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
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
                    <li class="nav-item"><a class="nav-link" href="escala_listar.php">Escalas</a></li>
                    <li class="nav-item"><a class="nav-link active" aria-current="page" href="ponto_listar.php">Pontos</a></li>
                    <li class="nav-item"><a class="nav-link" href="relatorios.php">Relatórios</a></li>
                    <li class="nav-item"><a class="nav-link" href="desempenho.php">Desempenho</a></li>
                </ul>
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item"><a class="nav-link" href="logout.php">Sair</a></li>
                </ul>
            </div>
        </div>
    </nav>
    <div class="container mt-4">
        <h3>Corrigir Registro de Ponto</h3>
        <p><strong>Funcionário:</strong> <?php echo htmlspecialchars($entry['employee_name']); ?></p>
        <?php if ($message): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>
        <form method="post">
            <div class="row g-3 mb-3">
                <div class="col-md-4">
                    <label class="form-label">Entrada</label>
                    <input type="datetime-local" class="form-control" name="clock_in" value="<?php echo htmlspecialchars($valueClockIn); ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Saída</label>
                    <input type="datetime-local" class="form-control" name="clock_out" value="<?php echo htmlspecialchars($valueClockOut); ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Motivo da Correção</label>
                    <input type="text" class="form-control" name="correction_reason" placeholder="Justificativa" value="">
                </div>
            </div>
            <button type="submit" class="btn btn-primary">Salvar Correção</button>
            <a href="ponto_listar.php" class="btn btn-secondary ms-2">Voltar</a>
        </form>
        <?php if ($entry['correction_reason']): ?>
            <hr>
            <h5>Correção Anterior</h5>
            <p><strong>Justificativa:</strong> <?php echo htmlspecialchars($entry['correction_reason']); ?></p>
            <p><strong>Corrigido em:</strong> <?php echo htmlspecialchars($entry['corrected_at']); ?></p>
        <?php endif; ?>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>