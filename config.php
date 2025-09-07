<?php
// config.php - application configuration and auth helpers

session_start();

$pdo = new PDO('sqlite:' . __DIR__ . '/db.sqlite');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

function currentUser()
{
    global $pdo;
    if (!isset($_SESSION['user_id'])) {
        return null;
    }
    $stmt = $pdo->prepare('SELECT * FROM users WHERE id = ?');
    $stmt->execute([$_SESSION['user_id']]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function requireLogin()
{
    if (!isset($_SESSION['user_id'])) {
        header('Location: index.php');
        exit();
    }
}

function requireAdmin()
{
    requireLogin();
    if (($_SESSION['user_role'] ?? null) !== 'admin') {
        header('Location: index.php');
        exit();
    }
}

function requirePrivileged()
{
    requireLogin();
    $role = $_SESSION['user_role'] ?? null;
    if (!in_array($role, ['admin', 'manager'], true)) {
        header('Location: index.php');
        exit();
    }
}

