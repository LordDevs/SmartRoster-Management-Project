<?php
// ponto_corrigir.php – allow managers or administrators to manually adjust time entries.
// Users can update clock-in/clock-out times and provide a justification. Corrections
// are recorded with timestamp, user, and reason.

require_once 'config.php';
requirePrivileged();

// Retrieve the time entry ID to correct
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    header('Location: ponto_listar.php');
    exit();
}

// Fetch the time entry with employee name and store
$stmt = $pdo->prepare('SELECT te.*, e.name AS employee_name, e.store_id FROM time_entries te JOIN employees e ON te.employee_id = e.id WHERE te.id = ?');
$stmt->execute([$id]);
$entry = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$entry) {
    header('Location: ponto_listar.php');
    exit();
}

// Ensure managers only edit entries for employees in their store
$role = $_SESSION['user_role'];
if ($role === 'manager') {
    $currentUser = currentUser();
    $managerStoreId = $currentUser['store_id'] ?? null;
    if ($entry['store_id'] != $managerStoreId) {
        header('Location: ponto_listar.php');
        exit();
    }
}

// Process updates
$message = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $newClockIn  = $_POST['clock_in'] ?? '';
    $newClockOut = $_POST['clock_out'] ?? '';
    $reason      = trim($_POST['correction_reason'] ?? '');
    // Convert datetime-local inputs to Y-m-d H:i:s
    $clockInFormatted  = $newClockIn ? date('Y-m-d H:i:s', strtotime($newClockIn)) : null;
    $clockOutFormatted = $newClockOut ? date('Y-m-d H:i:s', strtotime($newClockOut)) : null;
    $userId = $_SESSION['user_id'];
    // Update the record
    $stmtUpd = $pdo->prepare('UPDATE time_entries SET clock_in = ?, clock_out = ?, correction_reason = ?, corrected_at = CURRENT_TIMESTAMP, corrected_by = ? WHERE id = ?');
    $stmtUpd->execute([$clockInFormatted, $clockOutFormatted, $reason, $userId, $id]);
    $message = 'Time entry updated successfully.';
    // Atualizar a entrada para exibir valores corrigidos
    $stmt->execute([$id]);
    $entry = $stmt->fetch(PDO::FETCH_ASSOC);
}

// Prepare values for the form
// datetime-local inputs must use the format "Y-m-d\TH:i".
$valueClockIn = $entry['clock_in'] ? date('Y-m-d\TH:i', strtotime($entry['clock_in'])) : '';
$valueClockOut = $entry['clock_out'] ? date('Y-m-d\TH:i', strtotime($entry['clock_out'])) : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Correct Time Entry – Escala Hillbillys</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <?php
        $activePage = 'pontos';
        require_once __DIR__ . '/navbar.php';
    ?>
    <div class="container mt-4">
        <h3>Correct Time Entry</h3>
        <p><strong>Employee:</strong> <?php echo htmlspecialchars($entry['employee_name']); ?></p>
        <?php if ($message): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>
        <form method="post">
            <div class="row g-3 mb-3">
                <div class="col-md-4">
                    <label class="form-label">Clock In</label>
                    <input type="datetime-local" class="form-control" name="clock_in" value="<?php echo htmlspecialchars($valueClockIn); ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Clock Out</label>
                    <input type="datetime-local" class="form-control" name="clock_out" value="<?php echo htmlspecialchars($valueClockOut); ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Correction Reason</label>
                    <input type="text" class="form-control" name="correction_reason" placeholder="Reason" value="">
                </div>
            </div>
            <button type="submit" class="btn btn-primary">Save Correction</button>
            <a href="ponto_listar.php" class="btn btn-secondary ms-2">Back</a>
        </form>
        <?php if ($entry['correction_reason']): ?>
            <hr>
            <h5>Previous Correction</h5>
            <p><strong>Reason:</strong> <?php echo htmlspecialchars($entry['correction_reason']); ?></p>
            <p><strong>Corrected at:</strong> <?php echo htmlspecialchars($entry['corrected_at']); ?></p>
        <?php endif; ?>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>