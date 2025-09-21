<?php
// usuario_editar_max.php
// Extended version of usuario_editar.php with a weekly hour limit (max_weekly_hours) field.

require_once 'config.php';

requirePrivileged();

// Determine store options based on the current user's store
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

// Load employee data
$stmt = $pdo->prepare('SELECT * FROM employees WHERE id = ?');
$stmt->execute([$id]);
$employee = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$employee) {
    header('Location: usuarios_listar.php');
    exit();
}

// Ensure managers edit employees only within their own store
if ($_SESSION['user_role'] === 'manager') {
    $currentUserMgr = currentUser();
    if ($employee['store_id'] != $currentUserMgr['store_id']) {
        header('Location: usuarios_listar.php');
        exit();
    }
}

// Load the associated user account (if any)
$userStmt = $pdo->prepare('SELECT * FROM users WHERE employee_id = ?');
$userStmt->execute([$id]);
$userAccount = $userStmt->fetch(PDO::FETCH_ASSOC);

// Default values
$defaultValues = [
    'name' => $employee['name'],
    'email' => $employee['email'],
    'phone' => $employee['phone'],
    'store_id' => $employee['store_id'],
    'ppsn' => $employee['ppsn'] ?? '',
    'irp' => $employee['irp'] ?? '',
    'hourly_rate' => $employee['hourly_rate'] ?? '',
    'max_weekly_hours' => $employee['max_weekly_hours'] ?? '40',
    'create_login' => $userAccount ? '1' : '',
    'username' => $userAccount['username'] ?? '',
    'password' => '',
    'user_role' => $userAccount['role'] ?? 'employee'
];

// Merge values provided via POST
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
        $errors[] = 'Name is required.';
    }
    if ($createLogin) {
        if ($username === '') {
            $errors[] = 'Username is required.';
        }
        // Check if the username changed or is new
        if ($username !== '' && (!$userAccount || $username !== $userAccount['username'])) {
            $check = $pdo->prepare('SELECT id FROM users WHERE username = ?');
            $check->execute([$username]);
            if ($check->fetch()) {
                $errors[] = 'Username already exists.';
            }
        }
    }
    if (empty($errors)) {
        // Set store if not provided (for managers) or keep the current one
        if ($store_id === '' && $_SESSION['user_role'] !== 'admin') {
            $currentUserTmp2 = currentUser();
            $store_id = $currentUserTmp2['store_id'] ?? $employee['store_id'];
        }
        // Update employee
        $stmt = $pdo->prepare('UPDATE employees SET name = ?, email = ?, phone = ?, store_id = ?, ppsn = ?, irp = ?, hourly_rate = ?, max_weekly_hours = ? WHERE id = ?');
        $hourlyRateVal = ($hourly_rate !== '') ? floatval($hourly_rate) : null;
        $maxWeeklyVal = ($max_weekly_hours !== '') ? floatval($max_weekly_hours) : 40.0;
        $stmt->execute([$name, $email, $phone, $store_id ?: null, $ppsn ?: null, $irp ?: null, $hourlyRateVal, $maxWeeklyVal, $id]);
        // Atualizar ou criar login
        if ($createLogin) {
            if ($userAccount) {
                // Update existing user
                $updates = [];
                $values = [];
                $updates[] = 'username = ?';
                $values[] = $username;
                if ($password !== '') {
                    $updates[] = 'password = ?';
                    $values[] = password_hash($password, PASSWORD_DEFAULT);
                }
                if ($_SESSION['user_role'] === 'admin' && $selectedRole !== $userAccount['role']) {
                    $updates[] = 'role = ?';
                    $values[] = $selectedRole;
                }
                if ($_SESSION['user_role'] === 'admin') {
                    if ($selectedRole === 'manager') {
                        $updates[] = 'employee_id = NULL';
                        $updates[] = 'store_id = ?';
                        $values[] = $store_id ?: null;
                    } else {
                        $updates[] = 'employee_id = ?';
                        $values[] = $id;
                        $updates[] = 'store_id = NULL';
                    }
                }
                $values[] = $userAccount['id'];
                $stmt2 = $pdo->prepare('UPDATE users SET ' . implode(', ', $updates) . ' WHERE id = ?');
                $stmt2->execute($values);
            } else {
                // Create new account
                if ($password === '') {
                    $password = 'password123';
                }
                $hash = password_hash($password, PASSWORD_DEFAULT);
                if ($selectedRole === 'manager') {
                    $stmt2 = $pdo->prepare('INSERT INTO users (username, password, role, employee_id, store_id) VALUES (?, ?, ?, NULL, ?)');
                    $stmt2->execute([$username, $hash, 'manager', $store_id ?: null]);
                } else {
                    $stmt2 = $pdo->prepare('INSERT INTO users (username, password, role, employee_id) VALUES (?, ?, ?, ?)');
                    $stmt2->execute([$username, $hash, 'employee', $id]);
                }
            }
        }
        header('Location: usuarios_listar.php');
        exit();
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Edit Employee – Escala Hillbillys</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <?php
        $activePage = 'usuarios';
        require_once __DIR__ . '/navbar.php';
    ?>
    <div class="container mt-4">
        <h3>Edit Employee</h3>
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
                    <label class="form-label">Name</label>
                    <input type="text" name="name" class="form-control" value="<?php echo htmlspecialchars($defaultValues['name']); ?>" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Email</label>
                    <input type="email" name="email" class="form-control" value="<?php echo htmlspecialchars($defaultValues['email']); ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Phone</label>
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
                    <label class="form-label">Hourly Pay (€)</label>
                    <input type="number" step="0.01" name="hourly_rate" class="form-control" value="<?php echo htmlspecialchars($defaultValues['hourly_rate']); ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Weekly Hour Limit</label>
                    <input type="number" step="0.1" name="max_weekly_hours" class="form-control" value="<?php echo htmlspecialchars($defaultValues['max_weekly_hours']); ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Store</label>
                    <select name="store_id" class="form-select">
                        <option value="">Select...</option>
                        <?php foreach ($stores as $s): ?>
                            <option value="<?php echo $s['id']; ?>" <?php echo ($defaultValues['store_id'] == $s['id']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($s['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <hr>
            <div class="form-check mt-2">
                <input type="checkbox" class="form-check-input" id="create_login" name="create_login" <?php echo ($defaultValues['create_login']) ? 'checked' : ''; ?>>
                <label class="form-check-label" for="create_login">Create/Update login account</label>
            </div>
            <div id="loginFields" style="display: <?php echo ($defaultValues['create_login']) ? 'block' : 'none'; ?>;">
                <div class="row g-3 mt-2">
                    <div class="col-md-4">
                        <label class="form-label">Username</label>
                        <input type="text" name="username" class="form-control" value="<?php echo htmlspecialchars($defaultValues['username']); ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Password (leave blank to keep)</label>
                        <input type="password" name="password" class="form-control" value="">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Access Role</label>
                        <select name="user_role" class="form-select">
                            <option value="employee" <?php echo ($defaultValues['user_role'] === 'employee') ? 'selected' : ''; ?>>Employee</option>
                            <option value="manager" <?php echo ($defaultValues['user_role'] === 'manager') ? 'selected' : ''; ?>>Manager</option>
                        </select>
                    </div>
                </div>
            </div>
            <div class="mt-4">
                <button type="submit" class="btn btn-success">Save</button>
                <a href="usuarios_listar.php" class="btn btn-secondary">Cancel</a>
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