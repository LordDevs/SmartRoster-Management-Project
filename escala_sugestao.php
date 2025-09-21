<?php
// escala_sugestao.php – generate shift suggestions using heuristics and availability preferences.
// Managers and administrators can produce suggested shifts based on recent hours worked
// and employee availability preferences.

require_once 'config.php';
requirePrivileged();

// Determine role and store (when manager)
$role = $_SESSION['user_role'];
$currentUser = currentUser();
$storeId = null;
if ($role === 'manager') {
    $storeId = $currentUser['store_id'] ?? null;
}

// Apply saved suggestions from the session when requested
if (isset($_GET['apply']) && $_GET['apply'] === '1' && isset($_SESSION['shift_suggestions'])) {
    $suggestions = $_SESSION['shift_suggestions'];
    foreach ($suggestions as $sug) {
        // Managers can only insert shifts for employees in their store
        if ($role === 'manager' && $storeId !== null) {
            $stmtCheck = $pdo->prepare('SELECT store_id FROM employees WHERE id = ?');
            $stmtCheck->execute([$sug['employee_id']]);
            $empStore = $stmtCheck->fetchColumn();
            if ($empStore != $storeId) {
                continue;
            }
        }
        $stmtIns = $pdo->prepare('INSERT INTO shifts (employee_id, date, start_time, end_time) VALUES (?, ?, ?, ?)');
        $stmtIns->execute([$sug['employee_id'], $sug['date'], $sug['start_time'], $sug['end_time']]);
    }
    unset($_SESSION['shift_suggestions']);
    header('Location: escala_listar.php');
    exit();
}

/**
 * Calculate hours worked in the last 7 days for an employee.
 *
 * @param PDO $pdo
 * @param int $employeeId
 * @return float Hours worked
 */
function computeHoursLast7Days(PDO $pdo, int $employeeId): float
{
    $stmt = $pdo->prepare(
        "SELECT SUM(strftime('%s', COALESCE(te.clock_out, CURRENT_TIMESTAMP)) - strftime('%s', te.clock_in)) AS seconds_worked
         FROM time_entries te
         WHERE te.employee_id = ? AND DATE(te.clock_in) >= DATE('now', '-6 days')"
    );
    $stmt->execute([$employeeId]);
    $seconds = $stmt->fetchColumn() ?: 0;
    return $seconds / 3600.0;
}

/**
 * Build a map of availability preferences per employee and weekday.
 *
 * @param PDO $pdo
 * @return array Structure [employee_id][day_of_week] => ['start' => HH:MM, 'end' => HH:MM]
 */
function fetchEmployeePreferencesMap(PDO $pdo): array
{
    $stmt = $pdo->query('SELECT employee_id, day_of_week, available_start_time, available_end_time FROM employee_preferences');
    $map = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $empId = (int)$row['employee_id'];
        $day   = (int)$row['day_of_week'];
        $map[$empId][$day] = ['start' => $row['available_start_time'], 'end' => $row['available_end_time']];
    }
    return $map;
}

$suggestions = [];

// Handle form submission to generate suggestions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['generate_suggestion'])) {
    $start_time = $_POST['start_time'] ?? '09:00';
    $end_time   = $_POST['end_time'] ?? '17:00';
    $days       = max(1, min(30, (int)($_POST['days'] ?? 7)));

    // Fetch employees (limited to the store when manager)
    if ($role === 'manager') {
        $stmtEmp = $pdo->prepare('SELECT id, name FROM employees WHERE store_id = ? ORDER BY name');
        $stmtEmp->execute([$storeId]);
        $employees = $stmtEmp->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $employees = $pdo->query('SELECT id, name FROM employees ORDER BY name')->fetchAll(PDO::FETCH_ASSOC);
    }

    // Calculate hours worked per employee over the last 7 days
    $empData = [];
    foreach ($employees as $emp) {
        $hours = computeHoursLast7Days($pdo, (int)$emp['id']);
        $empData[] = ['id' => (int)$emp['id'], 'name' => $emp['name'], 'hours' => $hours];
    }
    // Sort ascending by hours worked
    usort($empData, function ($a, $b) {
        return $a['hours'] <=> $b['hours'];
    });

    // Availability preferences map
    $prefsMap = fetchEmployeePreferencesMap($pdo);

    // Generate suggestions by cycling through employees each day, rotating the starting point for balance.
    $startDate = date('Y-m-d');
    $numEmps = count($empData);
    for ($d = 0; $d < $days; $d++) {
        $date = date('Y-m-d', strtotime("$startDate +{$d} day"));
        $dayOfWeek = (int)date('w', strtotime($date)); // 0=Sunday ... 6=Saturday
        // Calculate the starting index for this day to rotate the employee list
        $startIndex = ($numEmps > 0) ? ($d % $numEmps) : 0;
        for ($i = 0; $i < $numEmps; $i++) {
            $emp = $empData[($startIndex + $i) % $numEmps];
            $empId = $emp['id'];
            // Check availability preference for this employee/day
            $pref = $prefsMap[$empId][$dayOfWeek] ?? null;
            if ($pref) {
                // Skip when the shift falls outside the available window
                if (strcmp($start_time, $pref['start']) < 0 || strcmp($end_time, $pref['end']) > 0) {
                    continue;
                }
            }
            $suggestions[] = [
                'date' => $date,
                'employee_id' => $empId,
                'employee_name' => $emp['name'],
                'start_time' => $start_time,
                'end_time' => $end_time
            ];
        }
    }
    // Save in the session for later application
    $_SESSION['shift_suggestions'] = $suggestions;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Smart Shift Suggestions – Escala Hillbillys</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <?php
        // Shift suggestions live within the schedules area
        $activePage = 'escalas';
        require_once __DIR__ . '/navbar.php';
    ?>
    <div class="container mt-4">
        <h3>Shift Suggestions (AI/Heuristic)</h3>
        <?php if (empty($suggestions)): ?>
            <p>Enter the parameters below to generate shift suggestions that consider worked hours and availability preferences.</p>
            <form method="post">
                <div class="row g-3 mb-3">
                    <div class="col-md-4">
                        <label class="form-label">Start Time</label>
                        <input type="time" class="form-control" name="start_time" value="09:00" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">End Time</label>
                        <input type="time" class="form-control" name="end_time" value="17:00" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Days to Suggest</label>
                        <input type="number" class="form-control" name="days" value="7" min="1" max="30">
                    </div>
                </div>
                <button type="submit" name="generate_suggestion" class="btn btn-success">Generate Suggestions</button>
                <a href="escala_listar.php" class="btn btn-secondary">Cancel</a>
            </form>
        <?php else: ?>
            <h5>Generated Suggestions</h5>
            <p><?php echo count($suggestions); ?> suggested shifts were created.</p>
            <div class="table-responsive">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Start</th>
                            <th>End</th>
                            <th>Employee</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($suggestions as $s): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($s['date']); ?></td>
                                <td><?php echo htmlspecialchars($s['start_time']); ?></td>
                                <td><?php echo htmlspecialchars($s['end_time']); ?></td>
                                <td><?php echo htmlspecialchars($s['employee_name']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="mt-3">
                <a href="escala_sugestao.php?apply=1" class="btn btn-primary">Apply Suggestions</a>
                <a href="escala_sugestao.php" class="btn btn-secondary">Regenerate</a>
            </div>
        <?php endif; ?>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>