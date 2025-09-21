<?php
require_once 'config.php';
requireEmployee();
require_once 'helpers.php';

// Ensure only logged-in employees reach this page
$funcionario_id = $_SESSION['user_id'] ?? null;
if (!$funcionario_id) {
    header('Location: index.php');
    exit;
}

// Calculate the current week's start and end (Monday to Sunday) using the Europe/Dublin timezone
$tz       = new DateTimeZone('Europe/Dublin');
$now      = new DateTime('now', $tz);
$weekStart = clone $now;
$weekStart->modify('monday this week');
$weekStart->setTime(0,0,0);
$weekEnd = clone $weekStart;
$weekEnd->modify('+7 days');

$hoursWorked   = getEmployeeHoursWorked($funcionario_id, $weekStart, $weekEnd, $conn);
$weeklyMax     = getWeeklyMaxHours($funcionario_id, $conn);
$hoursRemaining = max(0, $weeklyMax - $hoursWorked);
$rate          = getHourlyRate($funcionario_id, $conn);
$earnings      = $hoursWorked * $rate;
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Employee Dashboard</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" integrity="sha384-9ndCyUa4y3Yz2+1Jqv1MDwse4bCMZJ6uaOYJ6xZgTwHUnYlXyt1e1NEMqH9cO2+" crossorigin="anonymous">
    <link rel="stylesheet" href="style.css">
</head>
<body>
<?php include 'navbar.php'; ?>
<div class="container mt-4">
    <h1>My Dashboard</h1>
    <div class="row">
        <div class="col-md-4 mb-3">
            <div class="card shadow-sm">
                <div class="card-header">Hours Worked (week)</div>
                <div class="card-body">
                    <h3 class="card-title"><?php echo number_format($hoursWorked, 2); ?> h</h3>
                </div>
            </div>
        </div>
        <div class="col-md-4 mb-3">
            <div class="card shadow-sm">
                <div class="card-header">Hours Remaining</div>
                <div class="card-body">
                    <h3 class="card-title"><?php echo number_format($hoursRemaining, 2); ?> h</h3>
                </div>
            </div>
        </div>
        <div class="col-md-4 mb-3">
            <div class="card shadow-sm">
                <div class="card-header">Estimated Earnings</div>
                <div class="card-body">
                    <h3 class="card-title">€<?php echo number_format($earnings, 2); ?></h3>
                </div>
            </div>
        </div>
    </div>
    <a href="employee_preferences.php" class="btn btn-secondary mt-4">Edit Preferences</a>
</div>
</body>
</html>
