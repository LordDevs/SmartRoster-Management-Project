<?php
// usuario_editar.php – editar dados de um funcionário
require_once 'config.php';
// Allow admins and managers to edit employees.  Managers are restricted to their own store.
requirePrivileged();

// Fetch available stores for selection.  Admins see all; managers see their own.
$stores = [];
if ($_SESSION['user_role'] === 'admin') {
    $stores = $pdo->query('SELECT * FROM stores ORDER BY name')->fetchAll(PDO::FETCH_ASSOC);
} else {
    $currentUserTmp = currentUser();
    if ($currentUserTmp && $currentUserTmp['store_id']) {
        $stmtTmp = $pdo->prepare('SELECT * FROM stores WHERE id = ?');
        $stmtTmp->execute([$currentUserTmp['store_id']]);
        $stores = $stmtTmp->fetchAll(PDO::FETCH_ASSOC);
    }
}

$id = $_GET['id'] ?? null;
if (!$id) {
    header('Location: usuarios_listar.php');
    exit();
}

// Fetch existing employee data
$stmt = $pdo->prepare('SELECT * FROM employees WHERE id = ?');
$stmt->execute([$id]);
$employee = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$employee) {
    header('Location: usuarios_listar.php');
    exit();
}

// If the user is a manager, ensure they can only edit employees in their own store
if ($_SESSION['user_role'] === 'manager') {
    $currentUserMgr = currentUser();
    if ($employee['store_id'] != $currentUserMgr['store_id']) {
        header('Location: usuarios_listar.php');
        exit();
    }
}

$errors = [];

// Fetch associated user record (if exists) for employee login
$userStmt = $pdo->prepare('SELECT * FROM users WHERE employee_id = ?');
$userStmt->execute([$id]);
$userAccount = $userStmt->fetch(PDO::FETCH_ASSOC);

// Default form values
$defaultValues = [
    'name' => $employee['name'],
    'email' => $employee['email'],
    'phone' => $employee['phone'],
    'store_id' => $employee['store_id'],
    // Compliance and salary fields
    'ppsn' => $employee['ppsn'] ?? '',
    'irp' => $employee['irp'] ?? '',
    'hourly_rate' => $employee['hourly_rate'] ?? '',
    'create_login' => $userAccount ? '1' : '',
    'username' => $userAccount['username'] ?? '',
    'password' => '',
    // Preselect the user's role if login exists, otherwise default to employee
    'user_role' => $userAccount['role'] ?? 'employee'
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Merge posted values
    foreach ($defaultValues as $key => $val) {
        $defaultValues[$key] = $_POST[$key] ?? '';
    }
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $store_id = $_POST['store_id'] ?? '';
    $createLogin = isset($_POST['create_login']);
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    // New fields
    $ppsn = trim($_POST['ppsn'] ?? '');
    $irp = trim($_POST['irp'] ?? '');
    $hourly_rate = trim($_POST['hourly_rate'] ?? '');
    // Determine selected role; defaults to employee; only admins can assign manager
    $selectedRole = 'employee';
    if ($createLogin) {
        $selectedRole = $_POST['user_role'] ?? 'employee';
        if ($_SESSION['user_role'] !== 'admin') {
            $selectedRole = 'employee';
        }
        if (!in_array($selectedRole, ['employee', 'manager'])) {
            $selectedRole = 'employee';
        }
    }
    if ($name === '') {
        $errors[] = 'Nome é obrigatório.';
    }
    // Handle login validation
    if ($createLogin) {
        if ($username === '') {
            $errors[] = 'Usuário é obrigatório.';
        }
        // Check username uniqueness if changed or new
        if ($username !== '' && (!$userAccount || $username !== $userAccount['username'])) {
            $check = $pdo->prepare('SELECT id FROM users WHERE username = ?');
            $check->execute([$username]);
            if ($check->fetch()) {
                $errors[] = 'Nome de usuário já existente.';
            }
        }
    }
    if (empty($errors)) {
        // Update employee
        // If store is not provided for admins, keep the existing store; for managers, set to their own store.
        if ($store_id === '' && $_SESSION['user_role'] !== 'admin') {
            $currentUserTmp2 = currentUser();
            $store_id = $currentUserTmp2['store_id'] ?? $employee['store_id'];
        }
        $stmt = $pdo->prepare('UPDATE employees SET name = ?, email = ?, phone = ?, store_id = ?, ppsn = ?, irp = ?, hourly_rate = ? WHERE id = ?');
        $hourlyRateVal = ($hourly_rate !== '') ? floatval($hourly_rate) : null;
        $stmt->execute([$name, $email, $phone, $store_id ?: null, $ppsn ?: null, $irp ?: null, $hourlyRateVal, $id]);
        // Update or create user login
        if ($createLogin) {
            if ($userAccount) {
                // Update existing user. Build dynamic query for username, password, role, and store/employee mapping.
                $updates = [];
                $values = [];
                // Always update username
                $updates[] = 'username = ?';
                $values[] = $username;
                // Update password if provided
                if ($password !== '') {
                    $updates[] = 'password = ?';
                    $values[] = password_hash($password, PASSWORD_DEFAULT);
                }
                // Update role if changed and if admin
                if ($_SESSION['user_role'] === 'admin' && $selectedRole !== $userAccount['role']) {
                    $updates[] = 'role = ?';
                    $values[] = $selectedRole;
                }
                // Adjust store_id and employee_id based on selected role
                if ($_SESSION['user_role'] === 'admin') {
                    if ($selectedRole === 'manager') {
                        // Manager accounts should have employee_id NULL and store_id set
                        $updates[] = 'employee_id = NULL';
                        $updates[] = 'store_id = ?';
                        $values[] = $store_id ?: null;
                    } else {
                        // Employee accounts: employee_id set, store_id NULL
                        $updates[] = 'employee_id = ?';
                        $values[] = $id;
                        $updates[] = 'store_id = NULL';
                    }
                }
                $values[] = $userAccount['id'];
                $stmt = $pdo->prepare('UPDATE users SET ' . implode(', ', $updates) . ' WHERE id = ?');
                $stmt->execute($values);
            } else {
                // Create new user
                if ($password === '') {
                    $password = 'senha123';
                }
                $hash = password_hash($password, PASSWORD_DEFAULT);
                if ($selectedRole === 'manager') {
                    // Manager: role manager, employee_id NULL, store_id set
                    $stmt = $pdo->prepare('INSERT INTO users (username, password, role, employee_id, store_id) VALUES (?, ?, ?, NULL, ?)');
                    $stmt->execute([$username, $hash, 'manager', $store_id ?: null]);
                } else {
                    // Employee: role employee, link to employee_id
                    $stmt = $pdo->prepare('INSERT INTO users (username, password, role, employee_id) VALUES (?, ?, ?, ?)');
                    $stmt->execute([$username, $hash, 'employee', $id]);
                }
            }
        } elseif ($userAccount) {
            // Delete existing user account if createLogin unchecked
            $stmt = $pdo->prepare('DELETE FROM users WHERE id = ?');
            $stmt->execute([$userAccount['id']]);
        }
        header('Location: usuarios_listar.php');
        exit();
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Editar Funcionário – Escala Hillbillys</title>
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
                    <li class="nav-item"><a class="nav-link" href="escala_calendario.php">Calendário</a></li>
                    <li class="nav-item"><a class="nav-link" href="ponto_listar.php">Pontos</a></li>
                    <li class="nav-item"><a class="nav-link" href="relatorios.php">Relatórios</a></li>
                    <li class="nav-item"><a class="nav-link" href="desempenho.php">Desempenho</a></li>
                    <li class="nav-item"><a class="nav-link" href="loja_gerenciar.php">Loja</a></li>
                </ul>
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item"><a class="nav-link" href="logout.php">Sair</a></li>
                </ul>
            </div>
        </div>
    </nav>
    <div class="container mt-4">
        <h3>Editar Funcionário</h3>
        <?php if ($errors): ?>
            <div class="alert alert-danger">
                <ul class="mb-0">
                    <?php foreach ($errors as $err): ?>
                        <li><?php echo htmlspecialchars($err); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
        <form method="post">
            <div class="mb-3">
                <label for="name" class="form-label">Nome</label>
                <input type="text" class="form-control" id="name" name="name" value="<?php echo htmlspecialchars($defaultValues['name']); ?>" required>
            </div>
            <div class="mb-3">
                <label for="email" class="form-label">E-mail</label>
                <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($defaultValues['email']); ?>">
            </div>
            <div class="mb-3">
                <label for="phone" class="form-label">Telefone</label>
                <input type="text" class="form-control" id="phone" name="phone" value="<?php echo htmlspecialchars($defaultValues['phone']); ?>">
            </div>

            <?php if (!empty($stores)): ?>
            <div class="mb-3">
                <label for="store_id" class="form-label">Loja</label>
                <select class="form-select" id="store_id" name="store_id" <?php echo $_SESSION['user_role'] !== 'admin' ? 'disabled' : ''; ?>>
                    <option value="">Selecione...</option>
                    <?php foreach ($stores as $store): ?>
                        <option value="<?php echo $store['id']; ?>" <?php echo ($defaultValues['store_id'] == $store['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($store['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php if ($_SESSION['user_role'] !== 'admin'): ?>
                    <input type="hidden" name="store_id" value="<?php echo $stores[0]['id']; ?>">
                <?php endif; ?>
            </div>
            <?php endif; ?>
            <!-- Compliance and salary fields -->
            <div class="mb-3">
                <label for="ppsn" class="form-label">PPSN (Número de Segurança Social)</label>
                <input type="text" class="form-control" id="ppsn" name="ppsn" value="<?php echo htmlspecialchars($defaultValues['ppsn']); ?>">
                <small class="form-text text-muted">Obrigatório para cumprimento da legislação irlandesa.</small>
            </div>
            <div class="mb-3">
                <label for="irp" class="form-label">IRP (Irish Residence Permit)</label>
                <input type="text" class="form-control" id="irp" name="irp" value="<?php echo htmlspecialchars($defaultValues['irp']); ?>">
                <small class="form-text text-muted">Se aplicável, insira o número do cartão de residência.</small>
            </div>
            <div class="mb-3">
                <label for="hourly_rate" class="form-label">Salário por Hora (€)</label>
                <input type="number" step="0.01" class="form-control" id="hourly_rate" name="hourly_rate" value="<?php echo htmlspecialchars($defaultValues['hourly_rate']); ?>">
                <small class="form-text text-muted">Utilizado para calcular ganhos no portal do funcionário.</small>
            </div>
            <hr>
            <div class="form-check mb-3">
                <input class="form-check-input" type="checkbox" id="create_login" name="create_login" <?php echo $defaultValues['create_login'] ? 'checked' : ''; ?>>
                <label class="form-check-label" for="create_login">Habilitar acesso para este funcionário</label>
            </div>
            <div id="loginFields" style="display: <?php echo $defaultValues['create_login'] ? 'block' : 'none'; ?>;">
                <div class="mb-3">
                    <label for="username" class="form-label">Usuário</label>
                    <input type="text" class="form-control" id="username" name="username" value="<?php echo htmlspecialchars($defaultValues['username']); ?>">
                </div>
                <div class="mb-3">
                    <label for="password" class="form-label">Senha (deixe em branco para manter a atual)</label>
                    <input type="password" class="form-control" id="password" name="password" value="<?php echo htmlspecialchars($defaultValues['password']); ?>">
                </div>
                <?php if ($_SESSION['user_role'] === 'admin'): ?>
                <div class="mb-3">
                    <label for="user_role" class="form-label">Perfil de Acesso</label>
                    <select class="form-select" id="user_role" name="user_role">
                        <option value="employee" <?php echo ($defaultValues['user_role'] === 'employee') ? 'selected' : ''; ?>>Funcionário</option>
                        <option value="manager" <?php echo ($defaultValues['user_role'] === 'manager') ? 'selected' : ''; ?>>Gerente</option>
                    </select>
                </div>
                <?php else: ?>
                    <input type="hidden" name="user_role" value="<?php echo htmlspecialchars($defaultValues['user_role']); ?>">
                <?php endif; ?>
            </div>
            <button type="submit" class="btn btn-primary">Salvar</button>
            <a href="usuarios_listar.php" class="btn btn-secondary">Cancelar</a>
        </form>
        <script>
            const createLoginCheckbox = document.getElementById('create_login');
            const loginFields = document.getElementById('loginFields');
            createLoginCheckbox.addEventListener('change', function() {
                loginFields.style.display = this.checked ? 'block' : 'none';
            });
        </script>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>