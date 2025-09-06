<?php
// swap_request.php – solicitar troca de turno
require_once 'config.php';
requireLogin();
// Only employees should be able to request swaps
if ($_SESSION['user_role'] !== 'employee') {
    header('Location: dashboard.php');
    exit();
}

$shift_id = $_GET['shift_id'] ?? null;
if (!$shift_id) {
    header('Location: escala_listar.php');
    exit();
}

// Fetch shift data along with store information
$stmt = $pdo->prepare('SELECT s.*, e.store_id AS employee_store FROM shifts s JOIN employees e ON s.employee_id = e.id WHERE s.id = ?');
$stmt->execute([$shift_id]);
$shift = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$shift) {
    header('Location: escala_listar.php');
    exit();
}

// Fetch current and other employees only within the same store as the shift's employee
if ($shift) {
    $storeId = $shift['employee_store'];
    $stmtEmp = $pdo->prepare('SELECT * FROM employees WHERE store_id = ? ORDER BY name');
    $stmtEmp->execute([$storeId]);
    $employees = $stmtEmp->fetchAll(PDO::FETCH_ASSOC);
} else {
    $employees = [];
}

$message = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $requested_to = (int)$_POST['requested_to'];
    $requested_by = $shift['employee_id'];
    // Insert swap request
    $stmt = $pdo->prepare('INSERT INTO swap_requests (shift_id, requested_by, requested_to, status) VALUES (?, ?, ?, "pending")');
    $stmt->execute([$shift_id, $requested_by, $requested_to]);
    $message = 'Solicitação de troca registrada com sucesso.';
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Solicitar Troca – Escala Hillbillys</title>
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
                    <li class="nav-item"><a class="nav-link active" aria-current="page" href="escala_listar.php">Escalas</a></li>
                    <li class="nav-item"><a class="nav-link" href="ponto_listar.php">Pontos</a></li>
                    <li class="nav-item"><a class="nav-link" href="relatorios.php">Relatórios</a></li>
                </ul>
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item"><a class="nav-link" href="logout.php">Sair</a></li>
                </ul>
            </div>
        </div>
    </nav>
    <div class="container mt-4">
        <h3>Solicitar Troca de Turno</h3>
        <?php if ($message): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>
        <p>Turno atual: <?php echo htmlspecialchars($shift['date'] . ' ' . $shift['start_time'] . '–' . $shift['end_time']); ?></p>
        <form method="post">
            <div class="mb-3">
                <label class="form-label">Selecionar funcionário para troca</label>
                <select class="form-select" name="requested_to" required>
                    <option value="">Selecione...</option>
                    <?php foreach ($employees as $emp): ?>
                        <?php if ($emp['id'] != $shift['employee_id']): ?>
                            <option value="<?php echo $emp['id']; ?>">
                                <?php echo htmlspecialchars($emp['name']); ?>
                            </option>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="submit" class="btn btn-primary">Solicitar Troca</button>
            <a href="escala_listar.php" class="btn btn-secondary">Cancelar</a>
        </form>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>