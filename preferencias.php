<?php
// preferencias.php – page for employees to define their shift availability preferences.
// Only users with the "employee" role may access this page.

require_once 'config.php';
requireLogin();

// Ensure only employees access the page
$currentUser = currentUser();
if (!$currentUser || $currentUser['role'] !== 'employee') {
    header('Location: dashboard.php');
    exit();
}

$employeeId = $currentUser['employee_id'];
$message = null;

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // For each day of the week (0=Sunday through 6=Saturday)
    for ($d = 0; $d < 7; $d++) {
        $start = $_POST['start'][$d] ?? '';
        $end   = $_POST['end'][$d] ?? '';
        // Normalize empty fields to null
        $start = trim($start);
        $end = trim($end);
        // If both times are provided
        if ($start !== '' && $end !== '') {
            // Check if a preference already exists for this day
            $stmtCheck = $pdo->prepare('SELECT id FROM employee_preferences WHERE employee_id = ? AND day_of_week = ?');
            $stmtCheck->execute([$employeeId, $d]);
            $prefId = $stmtCheck->fetchColumn();
            if ($prefId) {
                // Update existing record
                $stmtUpd = $pdo->prepare('UPDATE employee_preferences SET available_start_time = ?, available_end_time = ? WHERE id = ?');
                $stmtUpd->execute([$start, $end, $prefId]);
            } else {
                // Insert new record
                $stmtIns = $pdo->prepare('INSERT INTO employee_preferences (employee_id, day_of_week, available_start_time, available_end_time) VALUES (?, ?, ?, ?)');
                $stmtIns->execute([$employeeId, $d, $start, $end]);
            }
        } else {
            // If no times were provided, remove any existing preference
            $stmtDel = $pdo->prepare('DELETE FROM employee_preferences WHERE employee_id = ? AND day_of_week = ?');
            $stmtDel->execute([$employeeId, $d]);
        }
    }
    $message = 'Preferences updated successfully.';
}

// Fetch existing preferences to display in the form
$stmtPrefs = $pdo->prepare('SELECT day_of_week, available_start_time, available_end_time FROM employee_preferences WHERE employee_id = ?');
$stmtPrefs->execute([$employeeId]);
$prefs = [];
foreach ($stmtPrefs->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $prefs[$row['day_of_week']] = $row;
}

// Day names for display
$dayNames = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Shift Preferences – Escala Hillbillys</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container-fluid">
            <a class="navbar-brand" href="portal.php">Escala Hillbillys</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                    <li class="nav-item"><a class="nav-link" href="portal.php">Upcoming Shifts</a></li>
                    <li class="nav-item"><a class="nav-link active" aria-current="page" href="preferencias.php">Preferences</a></li>
                </ul>
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item"><a class="nav-link" href="logout.php">Sign Out</a></li>
                </ul>
            </div>
        </div>
    </nav>
    <div class="container mt-4">
        <h3>My Shift Preferences</h3>
        <?php if ($message): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>
        <p>Set the hours when you are available to work each day of the week. Leave fields blank to indicate you are unavailable on that day.</p>
        <form method="post">
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th>Day</th>
                        <th>Available Start</th>
                        <th>Available End</th>
                    </tr>
                </thead>
                <tbody>
                    <?php for ($i = 0; $i < 7; $i++): ?>
                        <?php
                            $pref = $prefs[$i] ?? null;
                            $startVal = $pref['available_start_time'] ?? '';
                            $endVal   = $pref['available_end_time'] ?? '';
                        ?>
                        <tr>
                            <td><?php echo htmlspecialchars($dayNames[$i]); ?></td>
                            <td><input type="time" class="form-control" name="start[<?php echo $i; ?>]" value="<?php echo htmlspecialchars($startVal); ?>"></td>
                            <td><input type="time" class="form-control" name="end[<?php echo $i; ?>]" value="<?php echo htmlspecialchars($endVal); ?>"></td>
                        </tr>
                    <?php endfor; ?>
                </tbody>
            </table>
            <button type="submit" class="btn btn-primary">Save Preferences</button>
            <a href="portal.php" class="btn btn-secondary ms-2">Back</a>
        </form>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>