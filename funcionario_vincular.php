<?php
// funcionario_vincular.php – associate an employee with an existing shift
require_once 'config.php';
requireLogin();

// Fetch all employees and shifts for selection
$employees = $pdo->query('SELECT * FROM employees ORDER BY name')->fetchAll(PDO::FETCH_ASSOC);
$shifts = $pdo->query('SELECT * FROM shifts ORDER BY date, start_time')->fetchAll(PDO::FETCH_ASSOC);

$message = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $shift_id = (int)$_POST['shift_id'];
    $employee_id = (int)$_POST['employee_id'];
    $stmt = $pdo->prepare('UPDATE shifts SET employee_id = ? WHERE id = ?');
    $stmt->execute([$employee_id, $shift_id]);
    $message = 'Employee successfully assigned to the shift.';
    // Refresh shift list after update
    $shifts = $pdo->query('SELECT * FROM shifts ORDER BY date, start_time')->fetchAll(PDO::FETCH_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Assign Employee – Escala Hillbillys</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <?php
        $activePage = 'escalas';
        require_once __DIR__ . '/navbar.php';
    ?>
    <div class="container mt-4">
        <h3>Assign Employee to Shift</h3>
        <?php if ($message): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>
        <form method="post">
            <div class="mb-3">
                <label class="form-label">Shift</label>
                <select class="form-select" name="shift_id" required>
                    <option value="">Select a shift</option>
                    <?php foreach ($shifts as $shift): ?>
                        <?php $label = $shift['date'] . ' ' . $shift['start_time'] . '–' . $shift['end_time']; ?>
                        <option value="<?php echo $shift['id']; ?>" <?php if (isset($_POST['shift_id']) && $_POST['shift_id'] == $shift['id']) echo 'selected'; ?>>
                            <?php echo htmlspecialchars($label); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="mb-3">
                <label class="form-label">Employee</label>
                <select class="form-select" name="employee_id" required>
                    <option value="">Select an employee</option>
                    <?php foreach ($employees as $emp): ?>
                        <option value="<?php echo $emp['id']; ?>" <?php if (isset($_POST['employee_id']) && $_POST['employee_id'] == $emp['id']) echo 'selected'; ?>>
                            <?php echo htmlspecialchars($emp['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="submit" class="btn btn-primary">Assign</button>
            <a href="escala_listar.php" class="btn btn-secondary">Cancel</a>
        </form>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>