<?php
// portal.php – área do funcionário para visualizar escalas e pontos
require_once 'config.php';
requireLogin();

// Only allow employees to access this portal
$currentUser = currentUser();
if (!$currentUser || $currentUser['role'] !== 'employee') {
    header('Location: dashboard.php');
    exit();
}

// Get the associated employee record
$employeeId = $currentUser['employee_id'];
$stmt = $pdo->prepare('SELECT * FROM employees WHERE id = ?');
$stmt->execute([$employeeId]);
$employee = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$employee) {
    die('Funcionário não encontrado.');
}

// Handle register point if button clicked
$portalMessage = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['register_point'])) {
    $now = date('Y-m-d H:i:s');
    // Check if there is an open time entry
    $stmt = $pdo->prepare('SELECT * FROM time_entries WHERE employee_id = ? AND clock_out IS NULL ORDER BY clock_in DESC LIMIT 1');
    $stmt->execute([$employeeId]);
    $openEntry = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($openEntry) {
        // Register clock out
        $stmt = $pdo->prepare('UPDATE time_entries SET clock_out = ? WHERE id = ?');
        $stmt->execute([$now, $openEntry['id']]);
        $portalMessage = 'Saída registrada com sucesso.';
    } else {
        // Register clock in
        $stmt = $pdo->prepare('INSERT INTO time_entries (employee_id, clock_in) VALUES (?, ?)');
        $stmt->execute([$employeeId, $now]);
        $portalMessage = 'Entrada registrada com sucesso.';
    }
}

// Fetch upcoming shifts (today and future)
$stmt = $pdo->prepare("SELECT * FROM shifts WHERE employee_id = ? AND date >= DATE('now') ORDER BY date, start_time");
$stmt->execute([$employeeId]);
$upcomingShifts = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch past time entries (limit to 10)
$stmt = $pdo->prepare('SELECT * FROM time_entries WHERE employee_id = ? ORDER BY clock_in DESC LIMIT 10');
$stmt->execute([$employeeId]);
$timeEntries = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Compute weekly scheduled hours, worked hours and earnings
// Determine start and end of current week (Monday to Sunday)
$monday = date('Y-m-d', strtotime('monday this week'));
$sunday = date('Y-m-d', strtotime('sunday this week'));
// Fetch shifts within this week for the employee
$stmt = $pdo->prepare('SELECT date, start_time, end_time FROM shifts WHERE employee_id = ? AND date BETWEEN ? AND ?');
$stmt->execute([$employeeId, $monday, $sunday]);
$weekShifts = $stmt->fetchAll(PDO::FETCH_ASSOC);
$scheduledHours = 0.0;
foreach ($weekShifts as $s) {
    $startTs = strtotime($s['date'] . ' ' . $s['start_time']);
    $endTs = strtotime($s['date'] . ' ' . $s['end_time']);
    $scheduledHours += max(0, ($endTs - $startTs) / 3600);
}
// Compute worked hours within the week
$stmt = $pdo->prepare('SELECT clock_in, clock_out FROM time_entries WHERE employee_id = ? AND DATE(clock_in) BETWEEN ? AND ?');
$stmt->execute([$employeeId, $monday, $sunday]);
$weekEntries = $stmt->fetchAll(PDO::FETCH_ASSOC);
$workedHours = 0.0;
foreach ($weekEntries as $te) {
    $start = strtotime($te['clock_in']);
    // If clock_out is null (open entry), use current time
    $end = $te['clock_out'] ? strtotime($te['clock_out']) : time();
    $workedHours += max(0, ($end - $start) / 3600);
}
// Determine remaining hours from scheduled; could be negative if worked more
$remainingHours = $scheduledHours - $workedHours;
// Compute earnings (using hourly rate)
$hourlyRate = $employee['hourly_rate'] ?: 0;
$earnings = $workedHours * $hourlyRate;
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Portal do Funcionário – Escala Hillbillys</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
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
                    <li class="nav-item"><a class="nav-link" href="portal.php">Próximos Turnos</a></li>
                    <li class="nav-item"><a class="nav-link" href="preferencias.php">Preferências</a></li>
                </ul>
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item"><a class="nav-link" href="logout.php">Sair</a></li>
                </ul>
            </div>
        </div>
    </nav>
    <div class="container mt-4">
        <h3>Bem-vindo(a), <?php echo htmlspecialchars($employee['name']); ?></h3>
        <?php if ($portalMessage): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($portalMessage); ?></div>
        <?php endif; ?>
        <div class="mb-4">
            <form method="post">
                <input type="hidden" name="register_point" value="1">
                <?php
                // Determine whether the next action is clock in or out
                $stmt = $pdo->prepare('SELECT * FROM time_entries WHERE employee_id = ? AND clock_out IS NULL ORDER BY clock_in DESC LIMIT 1');
                $stmt->execute([$employeeId]);
                $openEntry = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($openEntry) {
                    $buttonLabel = 'Registrar Saída';
                    $buttonClass = 'btn-danger';
                } else {
                    $buttonLabel = 'Registrar Entrada';
                    $buttonClass = 'btn-success';
                }
                ?>
                <button type="submit" class="btn <?php echo $buttonClass; ?>"><?php echo $buttonLabel; ?></button>
            </form>
        </div>
        <!-- Summary of weekly hours and earnings -->
        <div class="mb-4">
            <h5>Horas da Semana</h5>
            <p>Horas agendadas: <strong><?php echo number_format($scheduledHours, 2); ?></strong>h</p>
            <p>Horas trabalhadas: <strong><?php echo number_format($workedHours, 2); ?></strong>h</p>
            <?php $progress = ($scheduledHours > 0) ? min(100, ($workedHours / $scheduledHours) * 100) : 0; ?>
            <div class="progress" style="height: 20px;">
                <div class="progress-bar" role="progressbar" style="width: <?php echo $progress; ?>%;" aria-valuenow="<?php echo $progress; ?>" aria-valuemin="0" aria-valuemax="100">
                    <?php echo number_format($progress, 0); ?>%
                </div>
            </div>
            <p class="mt-2">Horas restantes: <strong><?php echo number_format($remainingHours, 2); ?></strong>h</p>
            <p>Ganhos estimados esta semana: <strong>€<?php echo number_format($earnings, 2); ?></strong></p>
        </div>
        <h4>Próximos Turnos</h4>
        <table class="table table-striped mb-4">
            <thead>
                <tr>
                    <th>Data</th>
                    <th>Início</th>
                    <th>Término</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($upcomingShifts as $shift): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($shift['date']); ?></td>
                        <td><?php echo htmlspecialchars($shift['start_time']); ?></td>
                        <td><?php echo htmlspecialchars($shift['end_time']); ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($upcomingShifts)): ?>
                    <tr><td colspan="3">Nenhum turno futuro.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
        <h4>Últimos Registros de Ponto</h4>
        <table class="table table-striped">
            <thead>
                <tr>
                    <th>Entrada</th>
                    <th>Saída</th>
                    <th>Duração (h)</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($timeEntries as $entry): ?>
                    <?php
                        $duration = '';
                        if ($entry['clock_out']) {
                            $start = strtotime($entry['clock_in']);
                            $end = strtotime($entry['clock_out']);
                            $duration = round(($end - $start) / 3600, 2);
                        }
                    ?>
                    <tr>
                        <td><?php echo htmlspecialchars($entry['clock_in']); ?></td>
                        <td><?php echo htmlspecialchars($entry['clock_out']); ?></td>
                        <td><?php echo $duration; ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($timeEntries)): ?>
                    <tr><td colspan="3">Nenhum registro de ponto.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>