<?php
// weekly_overview.php
// Display a weekly summary of scheduled hours, worked hours, remaining hours, and estimated earnings for the logged-in employee.

require_once 'config.php';

// Ensure only employees can access
requireLogin();
requireEmployee();

$user = currentUser();
$employeeId   = $user['employee_id'] ?? null;
$hourlyRate   = $user['hourly_rate'] ?? 0;
$maxWeekly    = $user['max_weekly_hours'] ?? 40;

if (!$employeeId) {
    echo "Employee not associated with the user.";
    exit();
}

// Determine the start (Monday) and end (Sunday) of the current week.
$monday = new DateTime('monday this week');
$sunday = new DateTime('sunday this week');
$dateStart = $monday->format('Y-m-d');
$dateEnd   = $sunday->format('Y-m-d');

// Calculate scheduled hours (shifts) in seconds
$stmtShifts = $pdo->prepare(
    "SELECT COALESCE(SUM((strftime('%s', end_time) - strftime('%s', start_time))), 0) AS seconds " .
    "FROM shifts WHERE employee_id = ? AND date BETWEEN ? AND ?"
);
$stmtShifts->execute([$employeeId, $dateStart, $dateEnd]);
$scheduledSeconds = (float)$stmtShifts->fetchColumn();
$scheduledHours   = $scheduledSeconds / 3600.0;

// Calculate worked hours (time entries) in seconds
$stmtEntries = $pdo->prepare(
    "SELECT COALESCE(SUM((strftime('%s', COALESCE(clock_out, CURRENT_TIMESTAMP)) - strftime('%s', clock_in))), 0) AS seconds " .
    "FROM time_entries WHERE employee_id = ? AND DATE(clock_in) BETWEEN ? AND ?"
);
$stmtEntries->execute([$employeeId, $dateStart, $dateEnd]);
$workedSeconds   = (float)$stmtEntries->fetchColumn();
$workedHours     = $workedSeconds / 3600.0;

$remainingHours  = max(0.0, $maxWeekly - $workedHours);
$earnings        = $workedHours * $hourlyRate;

function formatHours($hours) {
    return number_format($hours, 2, '.', ',');
}

function formatCurrency($value) {
    return number_format($value, 2, '.', ',');
}

?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Weekly Summary – Escala Hillbillys</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <?php
    // Include standard navigation if present (adjust file name for your project).
    if (file_exists('menu.php')) {
        include 'menu.php';
    }
    ?>
    <div class="container mt-4">
        <h1 class="mb-3">My Weekly Summary</h1>
        <div class="row g-3">
            <div class="col-md-3">
                <div class="card text-bg-primary">
                    <div class="card-body">
                        <h5 class="card-title">Scheduled Hours</h5>
                        <p class="card-text fs-3"><?php echo formatHours($scheduledHours); ?> h</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-bg-success">
                    <div class="card-body">
                        <h5 class="card-title">Worked Hours</h5>
                        <p class="card-text fs-3"><?php echo formatHours($workedHours); ?> h</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-bg-warning">
                    <div class="card-body">
                        <h5 class="card-title">Remaining Hours</h5>
                        <p class="card-text fs-3"><?php echo formatHours($remainingHours); ?> h</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-bg-info">
                    <div class="card-body">
                        <h5 class="card-title">Estimated Earnings</h5>
                        <p class="card-text fs-3">€ <?php echo formatCurrency($earnings); ?></p>
                    </div>
                </div>
            </div>
        </div>
        <div class="mt-4">
            <h4>Week Progress</h4>
            <div class="progress" style="height: 30px;">
                <?php
                $percentage = $maxWeekly > 0 ? min(100, ($workedHours / $maxWeekly) * 100.0) : 0;
                ?>
                <div class="progress-bar" role="progressbar" style="width: <?php echo $percentage; ?>%;" aria-valuenow="<?php echo (int)$percentage; ?>" aria-valuemin="0" aria-valuemax="100">
                    <?php echo (int)$percentage; ?>%
                </div>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>