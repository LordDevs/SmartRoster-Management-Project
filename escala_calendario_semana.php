<?php
// escala_calendario_semana.php
// Weekly shift visualization. Displays each day of the current week (or a selected week
// via parameter) with shifts listed in cards. Provides a more modern and intuitive
// experience compared to the monthly interactive calendar, letting users quickly
// see who is scheduled each day. Administrators see every store; managers only see
// shifts for their store.

require_once 'config.php';

// Verify login and restrict access to administrators or managers
requireLogin();
$currentUser = currentUser();
if (!$currentUser || !in_array($currentUser['role'], ['admin','manager'])) {
    header('Location: dashboard.php');
    exit();
}

// Determine which week to display. Accepts GET parameter 'week' in YYYY-MM-DD format
// representing any day in the desired week. Defaults to today when absent.
$weekParam = isset($_GET['week']) ? $_GET['week'] : date('Y-m-d');
try {
    $refDate = new DateTime($weekParam);
} catch (Exception $e) {
    $refDate = new DateTime();
}
// Adjust to Monday of the reference week (ISO 8601: Monday = 1)
$dayOfWeek = (int)$refDate->format('N');
$monday     = clone $refDate;
$monday->modify('-' . ($dayOfWeek - 1) . ' days');
$sunday     = clone $monday;
$sunday->modify('+6 days');
$dateStart  = $monday->format('Y-m-d');
$dateEnd    = $sunday->format('Y-m-d');

// Fetch shifts for the week according to the user role
$storeId = $currentUser['store_id'] ?? null;
$events  = [];
try {
    if ($currentUser['role'] === 'manager' && $storeId) {
        $stmt = $pdo->prepare('SELECT s.id, s.date, s.start_time, s.end_time, e.name AS employee_name
                               FROM shifts s
                               JOIN employees e ON s.employee_id = e.id
                               WHERE e.store_id = ? AND s.date BETWEEN ? AND ?
                               ORDER BY s.date, s.start_time');
        $stmt->execute([$storeId, $dateStart, $dateEnd]);
    } else {
        $stmt = $pdo->prepare('SELECT s.id, s.date, s.start_time, s.end_time, e.name AS employee_name
                               FROM shifts s
                               JOIN employees e ON s.employee_id = e.id
                               WHERE s.date BETWEEN ? AND ?
                               ORDER BY s.date, s.start_time');
        $stmt->execute([$dateStart, $dateEnd]);
    }
    $events = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $events = [];
}

// Group shifts by day
$shiftsByDay = [];
for ($i = 0; $i < 7; $i++) {
    $date = clone $monday;
    $date->modify("+{$i} days");
    $key = $date->format('Y-m-d');
    $shiftsByDay[$key] = [];
}
foreach ($events as $ev) {
    $shiftsByDay[$ev['date']][] = $ev;
}

// Helper to format dates as "Day, DD Month"
function formatDateLabel($dateStr) {
    $dt = DateTime::createFromFormat('Y-m-d', $dateStr);
    $days   = ['Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'];
    $months = ['January','February','March','April','May','June','July','August','September','October','November','December'];
    $dayName = $days[(int)$dt->format('w')];
    $day     = $dt->format('d');
    $month   = $months[(int)$dt->format('n') - 1];
    return "$dayName, $day $month";
}

// Calculate links for previous and next weeks
$prevWeekDate = clone $monday;
$prevWeekDate->modify('-7 days');
$nextWeekDate = clone $monday;
$nextWeekDate->modify('+7 days');
$prevWeekParam = $prevWeekDate->format('Y-m-d');
$nextWeekParam = $nextWeekDate->format('Y-m-d');

?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Weekly Shifts – Escala Hillbillys</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    <style>
        body { padding-top: 60px; }
        .day-card { min-height: 200px; }
    </style>
</head>
<body>
<?php
    // Highlight the calendar item in the navbar
    $activePage = 'calendario';
    require_once __DIR__ . '/navbar.php';
?>
<div class="container">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h3>Shifts – Week of <?php echo htmlspecialchars($monday->format('d/m/Y')); ?> to <?php echo htmlspecialchars($sunday->format('d/m/Y')); ?></h3>
        <div>
            <a href="?week=<?php echo urlencode($prevWeekParam); ?>" class="btn btn-outline-primary btn-sm me-2">&laquo; Previous Week</a>
            <a href="?week=<?php echo urlencode($nextWeekParam); ?>" class="btn btn-outline-primary btn-sm">Next Week &raquo;</a>
        </div>
    </div>
    <div class="row g-3">
        <?php foreach ($shiftsByDay as $dateStr => $shifts): ?>
        <div class="col-12 col-sm-6 col-md-4 col-lg-3">
            <div class="card h-100 day-card">
                <div class="card-header bg-primary text-white">
                    <?php echo htmlspecialchars(formatDateLabel($dateStr)); ?>
                </div>
                <div class="card-body">
                    <?php if (empty($shifts)): ?>
                        <p class="text-muted">No shifts</p>
                    <?php else: ?>
                        <ul class="list-unstyled">
                            <?php foreach ($shifts as $sh): ?>
                            <li class="mb-2">
                                <strong><?php echo htmlspecialchars($sh['start_time']); ?>–<?php echo htmlspecialchars($sh['end_time']); ?></strong><br>
                                <span><?php echo htmlspecialchars($sh['employee_name']); ?></span><br>
                                <small>
                                    <a href="escala_editar.php?id=<?php echo urlencode($sh['id']); ?>" class="text-decoration-none">Edit</a>
                                    |
                                    <a href="escala_excluir.php?id=<?php echo urlencode($sh['id']); ?>" class="text-danger text-decoration-none" onclick="return confirm('Delete this shift?');">Delete</a>
                                </small>
                            </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <div class="mt-4">
        <a href="escala_criar.php" class="btn btn-success">New Shift</a>
        <a href="escala_calendario.php" class="btn btn-outline-secondary ms-2">View Interactive Calendar</a>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>