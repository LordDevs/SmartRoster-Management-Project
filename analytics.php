<?php
// analytics.php – advanced metrics dashboard for schedules and time entries
require_once __DIR__ . '/config.php';
requireLogin();

$role = $_SESSION['user']['role'] ?? null;
if (!$role || !in_array($role, ['admin','manager'], true)) {
    header('Location: dashboard.php');
    exit();
}
$storeId = $_SESSION['user']['store_id'] ?? null;

// Helper to calculate hours in seconds
function hoursBetween($start, $end) {
    $startTs = strtotime($start);
    $endTs   = strtotime($end);
    return max(0, $endTs - $startTs);
}

// 1. Scheduled hours per day of the week
$scheduledHours = array_fill(0, 7, 0); // 0=Sunday, 6=Saturday
// 2. Hours worked per day of the week
$workedHours    = array_fill(0, 7, 0);
// 3. Hours per employee (scheduled and worked)
$hoursByEmployee = [];

// Fetch shifts
if ($role === 'manager') {
    $stmtShifts = $pdo->prepare('SELECT s.employee_id, e.name, s.date, s.start_time, s.end_time FROM shifts s JOIN employees e ON s.employee_id = e.id WHERE e.store_id = ?');
    $stmtShifts->execute([$storeId]);
} else {
    $stmtShifts = $pdo->query('SELECT s.employee_id, e.name, s.date, s.start_time, s.end_time FROM shifts s JOIN employees e ON s.employee_id = e.id');
}
$shiftRows = $stmtShifts->fetchAll(PDO::FETCH_ASSOC);
foreach ($shiftRows as $row) {
    $dow = date('w', strtotime($row['date']));
    $seconds = hoursBetween($row['date'].' '.$row['start_time'], $row['date'].' '.$row['end_time']);
    $scheduledHours[$dow] += $seconds;
    $eid = $row['employee_id'];
    if (!isset($hoursByEmployee[$eid])) $hoursByEmployee[$eid] = ['name' => $row['name'], 'scheduled' => 0, 'worked' => 0];
    $hoursByEmployee[$eid]['scheduled'] += $seconds;
}

// Fetch time entries
if ($role === 'manager') {
    $stmtEntries = $pdo->prepare('SELECT te.employee_id, e.name, te.clock_in, te.clock_out FROM time_entries te JOIN employees e ON te.employee_id = e.id WHERE e.store_id = ?');
    $stmtEntries->execute([$storeId]);
} else {
    $stmtEntries = $pdo->query('SELECT te.employee_id, e.name, te.clock_in, te.clock_out FROM time_entries te JOIN employees e ON te.employee_id = e.id');
}
$entryRows = $stmtEntries->fetchAll(PDO::FETCH_ASSOC);
foreach ($entryRows as $row) {
    $dateTimeIn  = $row['clock_in'];
    $dateTimeOut = $row['clock_out'] ?: date('Y-m-d H:i:s');
    $dow         = date('w', strtotime($dateTimeIn));
    $seconds     = hoursBetween($dateTimeIn, $dateTimeOut);
    $workedHours[$dow] += $seconds;
    $eid = $row['employee_id'];
    if (!isset($hoursByEmployee[$eid])) $hoursByEmployee[$eid] = ['name' => $row['name'], 'scheduled' => 0, 'worked' => 0];
    $hoursByEmployee[$eid]['worked'] += $seconds;
}

// Convert seconds to hours (two decimals)
for ($i = 0; $i < 7; $i++) {
    $scheduledHours[$i] = round($scheduledHours[$i] / 3600, 2);
    $workedHours[$i]    = round($workedHours[$i] / 3600, 2);
}
// Prepare per-employee data
$labelsEmp = [];
$scheduledEmp = [];
$workedEmp = [];
foreach ($hoursByEmployee as $eid => $data) {
    $labelsEmp[]     = $data['name'];
    $scheduledEmp[] = round($data['scheduled'] / 3600, 2);
    $workedEmp[]    = round($data['worked'] / 3600, 2);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Advanced Metrics – Escala Hillbillys</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="style.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
</head>
<body>
<?php
    // Define the active page for the navbar
    $activePage = 'metricas';
    require_once __DIR__ . '/navbar.php';
?>
<div class="container mt-4">
    <h3>Hours Worked vs Scheduled Report</h3>
    <div class="row">
        <div class="col-md-6 mb-4">
            <canvas id="chartWeek"></canvas>
        </div>
        <div class="col-md-6 mb-4">
            <canvas id="chartEmp"></canvas>
        </div>
    </div>
</div>
<script>
// Weekly data
const labelsWeek = ['Sun','Mon','Tue','Wed','Thu','Fri','Sat'];
const scheduledWeek = <?php echo json_encode(array_values($scheduledHours)); ?>;
const workedWeek    = <?php echo json_encode(array_values($workedHours)); ?>;

const ctxWeek = document.getElementById('chartWeek').getContext('2d');
new Chart(ctxWeek, {
    type: 'bar',
    data: {
        labels: labelsWeek,
        datasets: [
            { label: 'Scheduled Hours', data: scheduledWeek, backgroundColor: 'rgba(54, 162, 235, 0.5)', borderColor:'rgba(54,162,235,1)', borderWidth:1 },
            { label: 'Worked Hours', data: workedWeek, backgroundColor: 'rgba(255, 99, 132, 0.5)', borderColor:'rgba(255,99,132,1)', borderWidth:1 }
        ]
    },
    options: {
        responsive: true,
        plugins: {
            title: { display: true, text: 'Hours by Day of Week' }
        },
        scales: {
            y: { beginAtZero: true, title: { display: true, text: 'Hours' } },
            x: { title: { display: true, text: 'Day of Week' } }
        }
    }
});

// Per-employee data
const labelsEmp = <?php echo json_encode($labelsEmp); ?>;
const scheduledEmp = <?php echo json_encode($scheduledEmp); ?>;
const workedEmp    = <?php echo json_encode($workedEmp); ?>;

const ctxEmp = document.getElementById('chartEmp').getContext('2d');
new Chart(ctxEmp, {
    type: 'bar',
    data: {
        labels: labelsEmp,
        datasets: [
            { label:'Scheduled Hours', data: scheduledEmp, backgroundColor:'rgba(54,162,235,0.5)', borderColor:'rgba(54,162,235,1)', borderWidth:1 },
            { label:'Worked Hours', data: workedEmp,    backgroundColor:'rgba(255,99,132,0.5)', borderColor:'rgba(255,99,132,1)', borderWidth:1 }
        ]
    },
    options: {
        responsive: true,
        plugins: {
            title: { display: true, text: 'Hours by Employee' }
        },
        scales: {
            y: { beginAtZero: true, title: { display: true, text: 'Hours' } },
            x: { title: { display: true, text: 'Employee' }, ticks: { display: false } }
        }
    }
});
</script>
</body>
</html>