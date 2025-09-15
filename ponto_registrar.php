<?php
// ponto_registrar.php – registro de ponto (clock in/out)
require_once 'config.php';
// Only managers and admins should register point on behalf of employees.
// Employees use their portal to register their own point.
requirePrivileged();

// Fetch employees for selection.  Managers only see employees in their store.
$role = $_SESSION['user_role'];
if ($role === 'manager') {
    $user = currentUser();
    $storeId = $user['store_id'] ?? null;
    $stmtEmp = $pdo->prepare('SELECT * FROM employees WHERE store_id = ? ORDER BY name');
    $stmtEmp->execute([$storeId]);
    $employees = $stmtEmp->fetchAll(PDO::FETCH_ASSOC);
} else {
    $employees = $pdo->query('SELECT * FROM employees ORDER BY name')->fetchAll(PDO::FETCH_ASSOC);
}

// Handle clock in/out
$message = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $employee_id = (int)$_POST['employee_id'];
    // For managers, ensure the selected employee belongs to their store
    if ($role === 'manager') {
        $allowed = false;
        foreach ($employees as $emp) {
            if ($emp['id'] == $employee_id) { $allowed = true; break; }
        }
        if (!$allowed) {
            $message = 'Funcionário inválido para sua loja.';
        }
    }
    if (!isset($message)) {
        $now = date('Y-m-d H:i:s');
        // Check if there is an open entry for this employee
        $stmt = $pdo->prepare('SELECT * FROM time_entries WHERE employee_id = ? AND clock_out IS NULL ORDER BY clock_in DESC LIMIT 1');
        $stmt->execute([$employee_id]);
        $openEntry = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($openEntry) {
            // User is clocking out
            $justification = trim($_POST['justification'] ?? '');
            $stmt = $pdo->prepare('UPDATE time_entries SET clock_out = ?, justification = ? WHERE id = ?');
            $stmt->execute([$now, $justification, $openEntry['id']]);
            $message = 'Saída registrada com sucesso.';
        } else {
            // User is clocking in
            $stmt = $pdo->prepare('INSERT INTO time_entries (employee_id, clock_in) VALUES (?, ?)');
            $stmt->execute([$employee_id, $now]);
            $message = 'Entrada registrada com sucesso.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Registrar Ponto – Escala Hillbillys</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <?php
        $activePage = 'pontos';
        require_once __DIR__ . '/navbar.php';
    ?>
    <div class="container mt-4">
        <h3>Registrar Ponto</h3>
        <?php if ($message): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>
        <form method="post">
            <div class="mb-3">
                <label class="form-label">Funcionário</label>
                <select class="form-select" name="employee_id" required>
                    <option value="">Selecione...</option>
                    <?php foreach ($employees as $emp): ?>
                        <option value="<?php echo $emp['id']; ?>">
                            <?php echo htmlspecialchars($emp['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="mb-3">
                <label class="form-label">Justificativa (opcional)</label>
                <input type="text" class="form-control" name="justification" placeholder="Digite a justificativa caso esteja atrasado ou saindo mais cedo">
            </div>
            <button type="submit" class="btn btn-success">Registrar</button>
            <a href="ponto_listar.php" class="btn btn-secondary">Voltar</a>
        </form>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>