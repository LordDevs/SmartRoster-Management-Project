<?php
// usuario_criar_max.php
// Este arquivo é uma versão extendida de usuario_criar.php com suporte para
// definir o limite semanal de horas (max_weekly_hours) para cada funcionário.

require_once 'config.php';

// Somente usuários com privilégios podem acessar (admin ou gerente)
requirePrivileged();

// Verificar a loja do usuário para determinar as opções de lojas a serem exibidas
$stores = [];
$currentUser = currentUser();
if ($_SESSION['user_role'] === 'admin') {
    // Administradores podem escolher qualquer loja
    $stores = $pdo->query('SELECT * FROM stores ORDER BY name')->fetchAll(PDO::FETCH_ASSOC);
} else {
    // Gerentes só podem associar funcionários à sua própria loja
    if ($currentUser && $currentUser['store_id']) {
        $stmt = $pdo->prepare('SELECT * FROM stores WHERE id = ?');
        $stmt->execute([$currentUser['store_id']]);
        $stores = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

// Valores padrão do formulário
$defaultValues = [
    'name' => '',
    'email' => '',
    'phone' => '',
    'ppsn' => '',
    'irp' => '',
    'hourly_rate' => '',
    'max_weekly_hours' => '40',
    'create_login' => '',
    'username' => '',
    'password' => '',
    'store_id' => '',
    'user_role' => 'employee'
];

// Mesclar valores enviados via POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($defaultValues as $key => $val) {
        $defaultValues[$key] = $_POST[$key] ?? '';
    }
}

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $store_id = $_POST['store_id'] ?? '';
    $createLogin = isset($_POST['create_login']);
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $ppsn = trim($_POST['ppsn'] ?? '');
    $irp = trim($_POST['irp'] ?? '');
    $hourly_rate = trim($_POST['hourly_rate'] ?? '');
    // Novo campo de limite de horas semanais
    $max_weekly_hours = trim($_POST['max_weekly_hours'] ?? '40');
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
    if ($createLogin) {
        if ($username === '' || $password === '') {
            $errors[] = 'Usuário e senha são obrigatórios para criar conta.';
        }
        $check = $pdo->prepare('SELECT id FROM users WHERE username = ?');
        $check->execute([$username]);
        if ($check->fetch()) {
            $errors[] = 'Nome de usuário já existente.';
        }
    }
    if (empty($errors)) {
        // Determinar loja
        if ($store_id === '' && $_SESSION['user_role'] !== 'admin') {
            $store_id = $currentUser['store_id'] ?? null;
        }
        // Inserir funcionário
        $stmt = $pdo->prepare('INSERT INTO employees (name, email, phone, store_id, ppsn, irp, hourly_rate, max_weekly_hours) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
        $hourlyRateVal = ($hourly_rate !== '') ? floatval($hourly_rate) : null;
        $maxWeeklyVal = ($max_weekly_hours !== '') ? floatval($max_weekly_hours) : 40.0;
        $stmt->execute([$name, $email, $phone, $store_id ?: null, $ppsn ?: null, $irp ?: null, $hourlyRateVal, $maxWeeklyVal]);
        $employeeId = $pdo->lastInsertId();
        // Criar login (opcional)
        if ($createLogin) {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            if ($selectedRole === 'manager') {
                $stmt = $pdo->prepare('INSERT INTO users (username, password, role, employee_id, store_id) VALUES (?, ?, ?, NULL, ?)');
                $stmt->execute([$username, $hash, 'manager', $store_id ?: null]);
            } else {
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
            <a class="navbar-brand" href="<?php echo ($_SESSION['user_role'] === 'manager') ? 'manager_dashboard.php' : 'dashboard.php'; ?>">Escala Hillbillys</a>
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
                    <li class="nav-item"><a class="nav-link" href="desempenho.php">Desempenho</a></li>
                    <li class="nav-item"><a class="nav-link" href="loja_gerenciar.php">Loja</a></li>
                    <li class="nav-item"><a class="nav-link" href="analytics.php">Métricas</a></li>
                </ul>
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item"><a class="nav-link" href="logout.php">Sair</a></li>
                </ul>
            </div>
        </div>
    </nav>
    <div class="container mt-4">
        <h3>Novo Funcionário</h3>
        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger">
                <ul class="mb-0">
                <?php foreach ($errors as $err): ?>
                    <li><?php echo htmlspecialchars($err); ?></li>
                <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
        <form method="post">
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Nome</label>
                    <input type="text" name="name" class="form-control" value="<?php echo htmlspecialchars($defaultValues['name']); ?>" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">E-mail</label>
                    <input type="email" name="email" class="form-control" value="<?php echo htmlspecialchars($defaultValues['email']); ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Telefone</label>
                    <input type="text" name="phone" class="form-control" value="<?php echo htmlspecialchars($defaultValues['phone']); ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label">PPSN</label>
                    <input type="text" name="ppsn" class="form-control" value="<?php echo htmlspecialchars($defaultValues['ppsn']); ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label">IRP</label>
                    <input type="text" name="irp" class="form-control" value="<?php echo htmlspecialchars($defaultValues['irp']); ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Salário por Hora (€)</label>
                    <input type="number" step="0.01" name="hourly_rate" class="form-control" value="<?php echo htmlspecialchars($defaultValues['hourly_rate']); ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Limite de Horas Semanais</label>
                    <input type="number" step="0.1" name="max_weekly_hours" class="form-control" value="<?php echo htmlspecialchars($defaultValues['max_weekly_hours']); ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Loja</label>
                    <select name="store_id" class="form-select">
                        <option value="">Selecione...</option>
                        <?php foreach ($stores as $s): ?>
                            <option value="<?php echo $s['id']; ?>" <?php echo ($defaultValues['store_id'] == $s['id']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($s['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <hr>
            <div class="form-check mt-2">
                <input type="checkbox" class="form-check-input" id="create_login" name="create_login" <?php echo ($defaultValues['create_login']) ? 'checked' : ''; ?>>
                <label class="form-check-label" for="create_login">Criar conta de acesso para este funcionário</label>
            </div>
            <div id="loginFields" style="display: <?php echo ($defaultValues['create_login']) ? 'block' : 'none'; ?>;">
                <div class="row g-3 mt-2">
                    <div class="col-md-4">
                        <label class="form-label">Usuário</label>
                        <input type="text" name="username" class="form-control" value="<?php echo htmlspecialchars($defaultValues['username']); ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Senha</label>
                        <input type="password" name="password" class="form-control" value="">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Perfil de Acesso</label>
                        <select name="user_role" class="form-select">
                            <option value="employee" <?php echo ($defaultValues['user_role'] === 'employee') ? 'selected' : ''; ?>>Funcionário</option>
                            <option value="manager" <?php echo ($defaultValues['user_role'] === 'manager') ? 'selected' : ''; ?>>Gerente</option>
                        </select>
                    </div>
                </div>
            </div>
            <div class="mt-4">
                <button type="submit" class="btn btn-success">Salvar</button>
                <a href="usuarios_listar.php" class="btn btn-secondary">Cancelar</a>
            </div>
        </form>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.getElementById('create_login').addEventListener('change', function() {
            document.getElementById('loginFields').style.display = this.checked ? 'block' : 'none';
        });
    </script>
</body>
</html>