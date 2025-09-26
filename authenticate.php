<?php
// authenticate.php – handle login form submissions
declare(strict_types=1);
require_once __DIR__ . '/config.php';

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit();
}

$username = trim($_POST['username'] ?? '');
$password = $_POST['password'] ?? '';

if ($username === '' || $password === '') {
    $_SESSION['login_error'] = 'Informe usuário e senha.';
    header('Location: index.php');
    exit();
}

// Busca usuário
$stmt = $pdo->prepare('SELECT id, username, password, role, employee_id, store_id FROM users WHERE username = ?');
$stmt->execute([$username]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

// Verifica senha
if ($user && password_verify($password, $user['password'])) {
    // Normaliza estrutura esperada pelo app
    $_SESSION['user'] = [
        'id'          => (int)$user['id'],
        'username'    => $user['username'],
        'role'        => $user['role'],                     // 'admin' | 'manager' | 'employee'
        'employee_id' => $user['employee_id'] ? (int)$user['employee_id'] : null,
        'store_id'    => $user['store_id'] ? (int)$user['store_id'] : null,
    ];

    // (Opcional) zera os antigos para evitar confusão
    unset($_SESSION['user_id'], $_SESSION['user_role']);

    // Redireciona por papel
    if ($user['role'] === 'employee') {
        header('Location: portal.php');
    } elseif ($user['role'] === 'manager') {
        header('Location: manager_dashboard.php');
    } else {
        header('Location: dashboard.php'); // admin
    }
    exit();
}

// Falha no login
$_SESSION['login_error'] = 'Invalid username or password.';
header('Location: index.php');
exit();
