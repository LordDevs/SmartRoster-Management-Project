<?php
// usuario_criar.php – form for creating a new employee
require_once 'config.php';
requirePrivileged();

// Current user (admin or manager)
$currentUser = currentUser();

// Fetch available stores.  Admins can assign to any store; managers (unlikely to access) only see their store.
$stores = [];
if ($_SESSION['user_role'] === 'admin') {
    $stores = $pdo->query('SELECT * FROM stores ORDER BY name')->fetchAll(PDO::FETCH_ASSOC);
} else {
    // Manager can only assign to their own store
    if ($currentUser && $currentUser['store_id']) {
        $stmt = $pdo->prepare('SELECT * FROM stores WHERE id = ?');
        $stmt->execute([$currentUser['store_id']]);
        $stores = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

$errors = [];

// Prepare default form values
$defaultValues = [
    'name' => '',
    'email' => '',
    'phone' => '',
    // Irish compliance: PPSN and IRP fields
    'ppsn' => '',
    'irp' => '',
    // Hourly rate (salary) field
    'hourly_rate' => '',
    'create_login' => '',
    'username' => '',
    'password' => '',
    'store_id' => '',
    // Default role for new user accounts; admins can change this to 'manager'
    'user_role' => 'employee'
];

// Merge POST values with defaults for repopulating the form
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
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
    // Determine role for the new user (defaults to employee); only admins can assign manager
    $selectedRole = 'employee';
    if ($createLogin) {
        $selectedRole = $_POST['user_role'] ?? 'employee';
        if ($_SESSION['user_role'] !== 'admin') {
            // Non-admins cannot assign manager role
            $selectedRole = 'employee';
        }
        // Sanitize allowed values
        if (!in_array($selectedRole, ['employee', 'manager'])) {
            $selectedRole = 'employee';
        }
    }
    if ($name === '') {
        $errors[] = 'Nome é obrigatório.';
    }
    if ($createLogin) {
        if ($username === '' || $password === '') {
            $errors[] = 'Usuário e senha são obrigatórios para criar conta.';
        }
        // Check if username already exists
        $check = $pdo->prepare('SELECT id FROM users WHERE username = ?');
        $check->execute([$username]);
        if ($check->fetch()) {
            $errors[] = 'Nome de usuário já existente.';
        }
    }
    if (empty($errors)) {
        // Determine the store for the new employee.  Managers default to their own store.
        if ($store_id === '' && $_SESSION['user_role'] !== 'admin') {
            $store_id = $currentUser['store_id'] ?? null;
        }
        // Insert employee record
        $stmt = $pdo->prepare('INSERT INTO employees (name, email, phone, store_id, ppsn, irp, hourly_rate) VALUES (?, ?, ?, ?, ?, ?, ?)');
        $hourlyRateVal = ($hourly_rate !== '') ? floatval($hourly_rate) : null;
        $stmt->execute([$name, $email, $phone, $store_id ?: null, $ppsn ?: null, $irp ?: null, $hourlyRateVal]);
        $employeeId = $pdo->lastInsertId();
        // Optionally create user login
        if ($createLogin) {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        if ($selectedRole === 'manager') {
            // Create a manager account linked to the selected store. employee_id is NULL for managers.
            $stmt = $pdo->prepare('INSERT INTO users (username, password, role, employee_id, store_id) VALUES (?, ?, ?, NULL, ?)');
            $stmt->execute([$username, $hash, 'manager', $store_id ?: null]);
        } else {
            // Regular employee account: link to employee_id
            $stmt = $pdo->prepare('INSERT INTO users (username, password, role, employee_id) VALUES (?, ?, ?, ?)');
            $stmt->execute([$username, $hash, 'employee', $employeeId]);
        }
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
    <title>Novo Funcionário – Escala Hillbillys</title>
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
        <h3>Novo Funcionário</h3>
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

            <div class="mb-3">
                <label for="ppsn" class="form-label">PPSN</label>
                <input type="text" class="form-control" id="ppsn" name="ppsn" value="<?php echo htmlspecialchars($defaultValues['ppsn']); ?>">
            </div>
            <div class="mb-3">
                <label for="irp" class="form-label">IRP</label>
                <input type="text" class="form-control" id="irp" name="irp" value="<?php echo htmlspecialchars($defaultValues['irp']); ?>">
            </div>
            <div class="mb-3">
                <label for="hourly_rate" class="form-label">Salário por Hora (€)</label>
                <input type="number" step="0.01" class="form-control" id="hourly_rate" name="hourly_rate" value="<?php echo htmlspecialchars($defaultValues['hourly_rate']); ?>">
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
            <hr>
            <div class="form-check mb-3">
                <input class="form-check-input" type="checkbox" id="create_login" name="create_login" <?php echo $defaultValues['create_login'] ? 'checked' : ''; ?>>
                <label class="form-check-label" for="create_login">Criar conta de acesso para este funcionário</label>
            </div>
            <div id="loginFields" style="display: <?php echo $defaultValues['create_login'] ? 'block' : 'none'; ?>;">
                <div class="mb-3">
                    <label for="username" class="form-label">Usuário</label>
                    <input type="text" class="form-control" id="username" name="username" value="<?php echo htmlspecialchars($defaultValues['username']); ?>">
                </div>
                <div class="mb-3">
                    <label for="password" class="form-label">Senha</label>
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
                    <input type="hidden" name="user_role" value="employee">
                <?php endif; ?>
            </div>
            <button type="submit" class="btn btn-success">Salvar</button>
            <a href="usuarios_listar.php" class="btn btn-secondary">Cancelar</a>
        </form>
        <script>
            // Show or hide login fields based on checkbox
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