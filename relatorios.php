<?php
// relatorios.php – advanced reports and dashboards
require_once 'config.php';
// Only allow managers and admins to view reports. Employees should not access this page.
requirePrivileged();

// Compute hours worked per employee for the current month
$role = $_SESSION['user_role'];
if ($role === 'manager') {
    $user = currentUser();
    $storeId = $user['store_id'] ?? null;
    $stmt = $pdo->prepare(
        "SELECT e.name AS employee_name,
                SUM(strftime('%s', COALESCE(te.clock_out, CURRENT_TIMESTAMP)) - strftime('%s', te.clock_in)) AS seconds_worked
         FROM employees e
         LEFT JOIN time_entries te ON e.id = te.employee_id AND strftime('%Y-%m', te.clock_in) = strftime('%Y-%m', 'now')
         WHERE e.store_id = ?
         GROUP BY e.id, e.name"
    );
    $stmt->execute([$storeId]);
    $hoursPerEmployee = $stmt->fetchAll(PDO::FETCH_ASSOC);
} else {
    $stmt = $pdo->query(
        "SELECT e.name AS employee_name,
                SUM(strftime('%s', COALESCE(te.clock_out, CURRENT_TIMESTAMP)) - strftime('%s', te.clock_in)) AS seconds_worked
         FROM employees e
         LEFT JOIN time_entries te ON e.id = te.employee_id AND strftime('%Y-%m', te.clock_in) = strftime('%Y-%m', 'now')
         GROUP BY e.id, e.name"
    );
    $hoursPerEmployee = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Prepare data for charts
$employeeLabels = [];
$employeeHours = [];
$employeeOvertime = [];
$monthlyHourLimit = 160; // Example: 40 hours/week * 4 weeks
foreach ($hoursPerEmployee as $row) {
    $employeeLabels[] = $row['employee_name'];
    $hours = round(($row['seconds_worked'] ?? 0) / 3600, 2);
    $employeeHours[] = $hours;
    $overtime = max(0, $hours - $monthlyHourLimit);
    $employeeOvertime[] = round($overtime, 2);
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Reports – Escala Hillbillys</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="style.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
</head>
<body>
    <?php
        $activePage = 'relatorios';
        require_once __DIR__ . '/navbar.php';
    ?>
    <div class="container mt-4">
        <h3>Reports and Dashboards</h3>
        <div class="row">
            <div class="col-lg-6">
                <div class="card mb-4">
                    <div class="card-header">Hours Worked per Employee (Current Month)</div>
                    <div class="card-body">
                        <canvas id="employeeHoursChart"></canvas>
                    </div>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="card mb-4">
                    <div class="card-header">Overtime per Employee (Current Month)</div>
                    <div class="card-body">
                        <canvas id="employeeOvertimeChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script>
    const employeeLabels = <?php echo json_encode($employeeLabels); ?>;
    const hoursData = <?php echo json_encode($employeeHours); ?>;
    const overtimeData = <?php echo json_encode($employeeOvertime); ?>;

    // Hours per employee
    new Chart(document.getElementById('employeeHoursChart').getContext('2d'), {
        type: 'bar',
        data: {
            labels: employeeLabels,
            datasets: [{
                label: 'Hours Worked',
                data: hoursData,
                backgroundColor: 'rgba(75, 192, 192, 0.5)',
                borderColor: 'rgba(75, 192, 192, 1)',
                borderWidth: 1
            }]
        },
        options: {
            scales: {
                y: { beginAtZero: true, title: { display: true, text: 'Hours' } },
                x: { title: { display: true, text: 'Employees' } }
            },
            plugins: { legend: { display: false } }
        }
    });

    // Overtime per employee
    new Chart(document.getElementById('employeeOvertimeChart').getContext('2d'), {
        type: 'bar',
        data: {
            labels: employeeLabels,
            datasets: [{
                label: 'Overtime Hours',
                data: overtimeData,
                backgroundColor: 'rgba(255, 99, 132, 0.5)',
                borderColor: 'rgba(255, 99, 132, 1)',
                borderWidth: 1
            }]
        },
        options: {
            scales: {
                y: { beginAtZero: true, title: { display: true, text: 'Hours' } },
                x: { title: { display: true, text: 'Employees' } }
            },
            plugins: { legend: { display: false } }
        }
    });
    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>