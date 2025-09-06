<?php
// funcionario_vincular.php – associar funcionário a uma escala existente
require_once 'config.php';
requireLogin();

// Fetch all employees and shifts for selection
$employees = $pdo->query('SELECT * FROM employees ORDER BY name')->fetchAll(PDO::FETCH_ASSOC);
$shifts = $pdo->query('SELECT * FROM shifts ORDER BY date, start_time')->fetchAll(PDO::FETCH_ASSOC);

$message = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $shift_id = (int)$_POST['shift_id'];
    $employee_id = (int)$_POST['employee_id'];
    $stmt = $pdo->prepare('UPDATE shifts SET employee_id = ? WHERE id = ?');
    $stmt->execute([$employee_id, $shift_id]);
    $message = 'Funcionário vinculado com sucesso à escala.';
    // Refresh shift list after update
    $shifts = $pdo->query('SELECT * FROM shifts ORDER BY date, start_time')->fetchAll(PDO::FETCH_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Vincular Funcionário – Escala Hillbillys</title>
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
                    <li class="nav-item"><a class="nav-link" href="escala_calendario.php">Calendário</a></li>
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
        <h3>Vincular Funcionário a Escala</h3>
        <?php if ($message): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>
        <form method="post">
            <div class="mb-3">
                <label class="form-label">Escala</label>
                <select class="form-select" name="shift_id" required>
                    <option value="">Selecione uma escala</option>
                    <?php foreach ($shifts as $shift): ?>
                        <?php $label = $shift['date'] . ' ' . $shift['start_time'] . '–' . $shift['end_time']; ?>
                        <option value="<?php echo $shift['id']; ?>" <?php if (isset($_POST['shift_id']) && $_POST['shift_id'] == $shift['id']) echo 'selected'; ?>>
                            <?php echo htmlspecialchars($label); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="mb-3">
                <label class="form-label">Funcionário</label>
                <select class="form-select" name="employee_id" required>
                    <option value="">Selecione um funcionário</option>
                    <?php foreach ($employees as $emp): ?>
                        <option value="<?php echo $emp['id']; ?>" <?php if (isset($_POST['employee_id']) && $_POST['employee_id'] == $emp['id']) echo 'selected'; ?>>
                            <?php echo htmlspecialchars($emp['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="submit" class="btn btn-primary">Vincular</button>
            <a href="escala_listar.php" class="btn btn-secondary">Cancelar</a>
        </form>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>