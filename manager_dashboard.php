<?php
// manager_dashboard.php – dashboard para gerentes de loja
require_once 'config.php';
requireLogin();

$user = currentUser();
// Only allow managers to access this page
if (!$user || $user['role'] !== 'manager') {
    header('Location: dashboard.php');
    exit();
}

$storeId = $user['store_id'];

// Fetch statistics for the manager's store
// Total number of employees in the store
$stmt = $pdo->prepare('SELECT COUNT(*) FROM employees WHERE store_id = ?');
$stmt->execute([$storeId]);
$employeeCount = $stmt->fetchColumn();

// Total number of upcoming shifts in the store
$stmt = $pdo->prepare("SELECT COUNT(*) FROM shifts s JOIN employees e ON s.employee_id = e.id WHERE e.store_id = ? AND s.date >= DATE('now')");
$stmt->execute([$storeId]);
$shiftsCount = $stmt->fetchColumn();

// Total hours worked this month by store employees
$stmt = $pdo->prepare("SELECT SUM(strftime('%s', COALESCE(te.clock_out, CURRENT_TIMESTAMP)) - strftime('%s', te.clock_in)) AS seconds_worked FROM time_entries te JOIN employees e ON te.employee_id = e.id WHERE e.store_id = ? AND strftime('%Y-%m', te.clock_in) = strftime('%Y-%m', 'now')");
$stmt->execute([$storeId]);
$totalSeconds = $stmt->fetchColumn() ?: 0;
$totalHours = round($totalSeconds / 3600, 2);

// Hours worked per day for last 7 days for this store
$hoursData = [];
for ($i = 6; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-{$i} days"));
    $stmt = $pdo->prepare("SELECT SUM(strftime('%s', COALESCE(te.clock_out, CURRENT_TIMESTAMP)) - strftime('%s', te.clock_in)) AS seconds_worked FROM time_entries te JOIN employees e ON te.employee_id = e.id WHERE e.store_id = ? AND DATE(te.clock_in) = ?");
    $stmt->execute([$storeId, $date]);
    $seconds = $stmt->fetchColumn() ?: 0;
    $hoursData[] = ['date' => $date, 'hours' => round($seconds / 3600, 2)];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>Manager Dashboard – Escala Hillbillys</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="style.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
    <style>body { padding-top: 60px; }</style>
</head>
<body>
    <?php
        // Manager dashboard does not highlight any specific nav item
        $activePage = '';
        require_once __DIR__ . '/navbar.php';
    ?>
    <div class="container">
        <div class="row mb-4">
            <div class="col-md-4">
                <div class="card text-white bg-success mb-3">
                    <div class="card-header">Employees</div>
                    <div class="card-body">
                        <h5 class="card-title">Total: <?php echo $employeeCount; ?></h5>
                        <p class="card-text">Number of employees in this store.</p>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card text-white bg-info mb-3">
                    <div class="card-header">Upcoming Shifts</div>
                    <div class="card-body">
                        <h5 class="card-title">Total: <?php echo $shiftsCount; ?></h5>
                        <p class="card-text">Future shifts scheduled for this store.</p>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card text-white bg-warning mb-3">
                    <div class="card-header">Hours This Month</div>
                    <div class="card-body">
                        <h5 class="card-title">Total: <?php echo $totalHours; ?> h</h5>
                        <p class="card-text">Hours worked this month by store employees.</p>
                    </div>
                </div>
            </div>
        </div>
        <div class="card">
            <div class="card-header">Hours Worked (Last 7 Days)</div>
            <div class="card-body">
                <canvas id="hoursChart"></canvas>
            </div>
        </div>
    </div>
    <script>
    const chartLabels = <?php echo json_encode(array_column($hoursData, 'date')); ?>;
    const chartData = <?php echo json_encode(array_column($hoursData, 'hours')); ?>;
    const ctx = document.getElementById('hoursChart').getContext('2d');
    new Chart(ctx, {
        type: 'bar',
        data: { labels: chartLabels, datasets: [{ label: 'Hours worked', data: chartData, backgroundColor: 'rgba(54,162,235,0.5)', borderColor: 'rgba(54,162,235,1)', borderWidth: 1 }] },
        options: { scales: { y: { beginAtZero: true, title: { display: true, text: 'Hours' } }, x: { title: { display: true, text: 'Date' } } }, plugins: { legend: { display: false } } }
    });
    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>