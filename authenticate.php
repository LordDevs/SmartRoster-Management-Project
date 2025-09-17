<?php
// authenticate.php – handle login form submissions
require_once __DIR__ . '/config.php';

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit();
}

$username = trim($_POST['username'] ?? '');
$password = $_POST['password'] ?? '';

// Look up the user by username
$stmt = $pdo->prepare('SELECT * FROM users WHERE username = ?');
$stmt->execute([$username]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if ($user && password_verify($password, $user['password'])) {
    // Successful login
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['user_role'] = $user['role'];
    // Redirect based on role: employees to portal, managers to manager dashboard, admins to admin dashboard
    if ($user['role'] === 'employee') {
        header('Location: portal.php');
    } elseif ($user['role'] === 'manager') {
        header('Location: manager_dashboard.php');
    } else {
        header('Location: dashboard.php');
    }
    exit();
}

// If we reach here, login failed
$_SESSION['login_error'] = 'Usuário ou senha inválidos.';
header('Location: index.php');
exit();