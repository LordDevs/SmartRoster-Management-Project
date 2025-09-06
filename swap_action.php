<?php
// swap_action.php – aprovar ou rejeitar solicitações de troca de turno
require_once 'config.php';
// Only allow managers and admins to approve/reject swap requests
requirePrivileged();

$id = $_GET['id'] ?? null;
$action = $_GET['action'] ?? null;
if (!$id || !$action) {
    header('Location: escala_listar.php');
    exit();
}

// Fetch the swap request
$stmt = $pdo->prepare('SELECT * FROM swap_requests WHERE id = ?');
$stmt->execute([$id]);
$request = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$request || $request['status'] !== 'pending') {
    header('Location: escala_listar.php');
    exit();
}

if ($action === 'approve' || $action === 'reject') {
    // If the user is a manager, ensure the shift belongs to their store before approving or rejecting
    if ($_SESSION['user_role'] === 'manager') {
        $user = currentUser();
        $stmtCheck = $pdo->prepare('SELECT e.store_id FROM shifts s JOIN employees e ON s.employee_id = e.id WHERE s.id = ?');
        $stmtCheck->execute([$request['shift_id']]);
        $row = $stmtCheck->fetch(PDO::FETCH_ASSOC);
        if (!$row || $row['store_id'] != $user['store_id']) {
            header('Location: escala_listar.php');
            exit();
        }
    }
    if ($action === 'approve') {
        // Update the shift assignment to the requested_to employee
        $stmt = $pdo->prepare('UPDATE shifts SET employee_id = ? WHERE id = ?');
        $stmt->execute([$request['requested_to'], $request['shift_id']]);
        // Mark request as approved
        $stmt = $pdo->prepare('UPDATE swap_requests SET status = "approved" WHERE id = ?');
        $stmt->execute([$id]);
    } else {
        // Mark request as rejected
        $stmt = $pdo->prepare('UPDATE swap_requests SET status = "rejected" WHERE id = ?');
        $stmt->execute([$id]);
    }
}
header('Location: escala_listar.php');
exit();