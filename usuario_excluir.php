<?php
// usuario_excluir.php – excluir um funcionário
require_once 'config.php';
// Only admins and managers can delete employees
requirePrivileged();

$id = $_GET['id'] ?? null;
if ($id) {
    // Verify privileges: managers can only delete employees from their own store
    if ($_SESSION['user_role'] === 'manager') {
        $currentUser = currentUser();
        // Check employee's store
        $stmt = $pdo->prepare('SELECT store_id FROM employees WHERE id = ?');
        $stmt->execute([$id]);
        $empStore = $stmt->fetchColumn();
        if ($empStore != $currentUser['store_id']) {
            header('Location: usuarios_listar.php');
            exit();
        }
    }
    // Delete the employee and any associated shifts and time entries
    $pdo->prepare('DELETE FROM shifts WHERE employee_id = ?')->execute([$id]);
    $pdo->prepare('DELETE FROM time_entries WHERE employee_id = ?')->execute([$id]);
    $pdo->prepare('DELETE FROM employees WHERE id = ?')->execute([$id]);
}
header('Location: usuarios_listar.php');
exit();