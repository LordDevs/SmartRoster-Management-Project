<?php
// escala_criar.php – criar, editar ou excluir escalas (turnos)
require_once 'config.php';
// Only allow managers and admins to create, edit or delete shifts. Employees should not access this page.
requirePrivileged();
// Capture current role once for reuse
$currentRole = $_SESSION['user_role'];

// Handle delete action
if (isset($_GET['delete'])) {
    $deleteId = (int)$_GET['delete'];
    // Before deleting, ensure the user has permission.  Managers can only delete shifts in their store.
    if ($currentRole === 'manager') {
        $user = currentUser();
        // Join to find the store of the shift's employee
        $stmtDel = $pdo->prepare('SELECT e.store_id FROM shifts s JOIN employees e ON s.employee_id = e.id WHERE s.id = ?');
        $stmtDel->execute([$deleteId]);
        $row = $stmtDel->fetch(PDO::FETCH_ASSOC);
        if (!$row || $row['store_id'] != $user['store_id']) {
            header('Location: escala_listar.php');
            exit();
        }
    }
    $pdo->prepare('DELETE FROM shifts WHERE id = ?')->execute([$deleteId]);
    header('Location: escala_listar.php');
    exit();
}

// Fetch employees for selection.  Managers see only employees in their store.
if ($currentRole === 'manager') {
    $user = currentUser();
    $storeId = $user['store_id'] ?? null;
    $stmtEmp = $pdo->prepare('SELECT * FROM employees WHERE store_id = ? ORDER BY name');
    $stmtEmp->execute([$storeId]);
    $employees = $stmtEmp->fetchAll(PDO::FETCH_ASSOC);
} else {
    // Admin sees all employees
    $employees = $pdo->query('SELECT * FROM employees ORDER BY name')->fetchAll(PDO::FETCH_ASSOC);
}

// Automatic generation of schedules
if (isset($_GET['auto'])) {
    // If the form was submitted for auto generation
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $startTime = $_POST['start_time'] ?? '09:00';
        $endTime = $_POST['end_time'] ?? '17:00';
        $days = (int)($_POST['days'] ?? 7);
        $startDate = date('Y-m-d');
        // Prepare statement to fetch preferences by employee and day of week
        $prefStmt = $pdo->prepare('SELECT available_start_time, available_end_time FROM employee_preferences WHERE employee_id = ? AND day_of_week = ?');
        // Helper: compute hours worked in the last 7 days for fairness
        $computeHoursStmt = $pdo->prepare(
            "SELECT SUM(strftime('%s', COALESCE(te.clock_out, CURRENT_TIMESTAMP)) - strftime('%s', te.clock_in)) AS seconds_worked
             FROM time_entries te
             WHERE te.employee_id = ? AND DATE(te.clock_in) >= DATE('now', '-6 days')"
        );
        // Compute hours for each employee to sort ascending
        $empHours = [];
        foreach ($employees as $emp) {
            $computeHoursStmt->execute([$emp['id']]);
            $seconds = $computeHoursStmt->fetchColumn() ?: 0;
            $hours = $seconds / 3600.0;
            $empHours[] = ['employee' => $emp, 'hours' => $hours];
        }
        // Sort employees by hours worked ascending
        usort($empHours, function($a, $b) {
            return $a['hours'] <=> $b['hours'];
        });
        $numEmps = count($empHours);
        // Loop over each day; rotate employee list to balance assignments across days
        for ($d = 0; $d < $days; $d++) {
            $date = date('Y-m-d', strtotime("$startDate +{$d} day"));
            $dayOfWeek = (int)date('w', strtotime($date));
            // Determine the starting index for this day to rotate assignments
            $startIndex = $d % ($numEmps > 0 ? $numEmps : 1);
            // Build rotated list
            $rotated = [];
            for ($i = 0; $i < $numEmps; $i++) {
                $rotated[] = $empHours[($startIndex + $i) % $numEmps]['employee'];
            }
            foreach ($rotated as $emp) {
                // Verificar preferências de disponibilidade
                $prefStmt->execute([$emp['id'], $dayOfWeek]);
                $pref = $prefStmt->fetch(PDO::FETCH_ASSOC);
                if ($pref) {
                    if (strcmp($startTime, $pref['available_start_time']) < 0 || strcmp($endTime, $pref['available_end_time']) > 0) {
                        // Horário proposto fora da faixa de preferência, pular este funcionário
                        continue;
                    }
                }
                // Verificar se já existe turno para o funcionário na mesma data (evita sobreposição)
                $checkStmt = $pdo->prepare('SELECT COUNT(*) FROM shifts WHERE employee_id = ? AND date = ?');
                $checkStmt->execute([$emp['id'], $date]);
                $existing = $checkStmt->fetchColumn();
                if ($existing > 0) {
                    // Já existe um turno para este funcionário neste dia, pular
                    continue;
                }
                // Inserir turno se não houver conflito e estiver dentro das preferências
                $stmt = $pdo->prepare('INSERT INTO shifts (employee_id, date, start_time, end_time) VALUES (?, ?, ?, ?)');
                $stmt->execute([$emp['id'], $date, $startTime, $endTime]);
            }
        }
        header('Location: escala_listar.php');
        exit();
    }
    // Show confirmation form for auto generation
    ?>
    <!DOCTYPE html>
    <html lang="pt-BR">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Gerar Escalas Automaticamente</title>
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
            <h3>Gerar Escalas Automaticamente</h3>
            <p>Preencha os horários padrão para gerar escalas para todos os funcionários nos próximos dias.</p>
            <form method="post">
                <div class="row g-3 mb-3">
                    <div class="col-md-4">
                        <label class="form-label">Horário de Início</label>
                        <input type="time" class="form-control" name="start_time" value="09:00" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Horário de Término</label>
                        <input type="time" class="form-control" name="end_time" value="17:00" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Dias a gerar</label>
                        <input type="number" class="form-control" name="days" value="7" min="1" max="30">
                    </div>
                </div>
                <button type="submit" class="btn btn-success">Gerar</button>
                <a href="escala_listar.php" class="btn btn-secondary">Cancelar</a>
            </form>
        </div>
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    </body>
    </html>
    <?php
    return;
}

// Editing an existing shift
if (isset($_GET['edit'])) {
    $editId = (int)$_GET['edit'];
    // Fetch shift and ensure the manager has access to edit it
    $stmt = $pdo->prepare('SELECT s.*, e.store_id AS employee_store FROM shifts s JOIN employees e ON s.employee_id = e.id WHERE s.id = ?');
    $stmt->execute([$editId]);
    $shift = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$shift) {
        header('Location: escala_listar.php');
        exit();
    }
    // If user is manager, ensure shift belongs to their store
    if ($currentRole === 'manager') {
        $user = currentUser();
        if ($shift['employee_store'] != $user['store_id']) {
            header('Location: escala_listar.php');
            exit();
        }
    }
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $date = $_POST['date'] ?? $shift['date'];
        $start_time = $_POST['start_time'] ?? $shift['start_time'];
        $end_time = $_POST['end_time'] ?? $shift['end_time'];
        $employee_id = $_POST['employee_id'] ?? $shift['employee_id'];
        // If manager, ensure the selected employee belongs to their store
        if ($currentRole === 'manager') {
            $user = currentUser();
            $allowed = false;
            foreach ($employees as $emp) {
                if ($emp['id'] == $employee_id) { $allowed = true; break; }
            }
            if (!$allowed) {
                header('Location: escala_listar.php');
                exit();
            }
        }
        $stmt = $pdo->prepare('UPDATE shifts SET employee_id = ?, date = ?, start_time = ?, end_time = ? WHERE id = ?');
        $stmt->execute([$employee_id, $date, $start_time, $end_time, $editId]);
        header('Location: escala_listar.php');
        exit();
    }
    // Display edit form
    ?>
    <!DOCTYPE html>
    <html lang="pt-BR">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Editar Escala</title>
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
            <h3>Editar Escala</h3>
            <form method="post">
                <div class="row g-3 mb-3">
                    <div class="col-md-3">
                        <label class="form-label">Data</label>
                        <input type="date" class="form-control" name="date" value="<?php echo htmlspecialchars($shift['date']); ?>" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Início</label>
                        <input type="time" class="form-control" name="start_time" value="<?php echo htmlspecialchars($shift['start_time']); ?>" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Término</label>
                        <input type="time" class="form-control" name="end_time" value="<?php echo htmlspecialchars($shift['end_time']); ?>" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Funcionário</label>
                        <select class="form-select" name="employee_id" required>
                            <?php foreach ($employees as $emp): ?>
                                <option value="<?php echo $emp['id']; ?>" <?php if ($emp['id'] == $shift['employee_id']) echo 'selected'; ?>><?php echo htmlspecialchars($emp['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <button type="submit" class="btn btn-primary">Salvar</button>
                <a href="escala_listar.php" class="btn btn-secondary">Cancelar</a>
            </form>
        </div>
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    </body>
    </html>
    <?php
    return;
}

// Creating new shifts manually
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $date = $_POST['date'] ?? '';
    $start_time = $_POST['start_time'] ?? '';
    $end_time = $_POST['end_time'] ?? '';
    $employee_ids = $_POST['employee_ids'] ?? [];
    // Insert a shift for each selected employee
    // Prepare preference statement once
    $prefStmt = $pdo->prepare('SELECT available_start_time, available_end_time FROM employee_preferences WHERE employee_id = ? AND day_of_week = ?');
    $dayOfWeek = (int)date('w', strtotime($date));
    $skipped = [];
    foreach ($employee_ids as $empId) {
        // For managers, ensure the selected employee is in their store
        if ($currentRole === 'manager') {
            $allowed = false;
            foreach ($employees as $emp) {
                if ($emp['id'] == $empId) { $allowed = true; break; }
            }
            if (!$allowed) {
                continue;
            }
        }
        // Check preferences for this employee/day
        $prefStmt->execute([$empId, $dayOfWeek]);
        $pref = $prefStmt->fetch(PDO::FETCH_ASSOC);
        if ($pref) {
            if (strcmp($start_time, $pref['available_start_time']) < 0 || strcmp($end_time, $pref['available_end_time']) > 0) {
                // Skip if outside preference window
                $skipped[] = $empId;
                continue;
            }
        }
        $stmt = $pdo->prepare('INSERT INTO shifts (employee_id, date, start_time, end_time) VALUES (?, ?, ?, ?)');
        $stmt->execute([$empId, $date, $start_time, $end_time]);
    }
    // TODO: could display a message about skipped employees due to preferences
    header('Location: escala_listar.php');
    exit();
}

// Show creation form
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Nova Escala – Escala Hillbillys</title>
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
                    <li class="nav-item"><a class="nav-link" href="desempenho.php">Desempenho</a></li>
                </ul>
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item"><a class="nav-link" href="logout.php">Sair</a></li>
                </ul>
            </div>
        </div>
    </nav>
    <div class="container mt-4">
        <h3>Nova Escala</h3>
        <form method="post">
            <div class="row g-3 mb-3">
                <div class="col-md-3">
                    <label class="form-label">Data</label>
                    <input type="date" class="form-control" name="date" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Início</label>
                    <input type="time" class="form-control" name="start_time" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Término</label>
                    <input type="time" class="form-control" name="end_time" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Funcionários</label>
                    <select class="form-select" name="employee_ids[]" multiple required>
                        <?php foreach ($employees as $emp): ?>
                            <option value="<?php echo $emp['id']; ?>"><?php echo htmlspecialchars($emp['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <small class="text-muted">Use Ctrl/Command para selecionar múltiplos.</small>
                </div>
            </div>
            <button type="submit" class="btn btn-success">Criar</button>
            <a href="escala_listar.php" class="btn btn-secondary">Cancelar</a>
        </form>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>