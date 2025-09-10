<?php
// analytics.php – Painel de métricas avançadas para escalas e registros de ponto
require_once 'config.php';
requireLogin();
$user = currentUser();
if (!$user || !in_array($user['role'], ['admin','manager'])) {
    header('Location: dashboard.php');
    exit();
}
$storeId = $user['store_id'] ?? null;

// Função para calcular horas em segundos
function hoursBetween($start, $end) {
    $startTs = strtotime($start);
    $endTs   = strtotime($end);
    return max(0, $endTs - $startTs);
}

// 1. Horas agendadas por dia da semana
$scheduledHours = array_fill(0, 7, 0); // 0=domingo,6=sabado
// 2. Horas trabalhadas por dia da semana
$workedHours    = array_fill(0, 7, 0);
// 3. Horas por funcionário (agendadas e trabalhadas)
$hoursByEmployee = [];

// Obter turnos
if ($user['role'] === 'manager') {
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

// Obter registros de ponto
if ($user['role'] === 'manager') {
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

// Converter segundos para horas (com 2 casas decimais)
for ($i=0; $i<7; $i++) {
    $scheduledHours[$i] = round($scheduledHours[$i] / 3600, 2);
    $workedHours[$i]    = round($workedHours[$i] / 3600, 2);
}
// Preparar dados por funcionário
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
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Métricas Avançadas – Escala Hillbillys</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-dark bg-primary">
    <div class="container-fluid">
        <?php if ($user['role'] === 'admin'): ?>
            <a class="navbar-brand" href="dashboard.php">Escala Hillbillys</a>
        <?php else: ?>
            <a class="navbar-brand" href="manager_dashboard.php">Escala Hillbillys – Gerente</a>
        <?php endif; ?>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbars" aria-controls="navbars" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbars">
            <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                <li class="nav-item"><a class="nav-link" href="usuarios_listar.php">Funcionários</a></li>
                <li class="nav-item"><a class="nav-link" href="escala_listar.php">Escalas</a></li>
                <li class="nav-item"><a class="nav-link" href="escala_calendario.php">Calendário</a></li>
                <li class="nav-item"><a class="nav-link" href="ponto_listar.php">Pontos</a></li>
                <li class="nav-item"><a class="nav-link" href="relatorios.php">Relatórios</a></li>
                <li class="nav-item"><a class="nav-link" href="desempenho.php">Desempenho</a></li>
                <li class="nav-item"><a class="nav-link active" href="analytics.php">Métricas</a></li>
                <li class="nav-item"><a class="nav-link" href="loja_gerenciar_updated.php">Loja</a></li>
            </ul>
            <ul class="navbar-nav ms-auto">
                <li class="nav-item"><a class="nav-link" href="logout.php">Sair</a></li>
            </ul>
        </div>
    </div>
</nav>
<div class="container mt-4">
    <h3>Relatório de Horas Trabalhadas vs Agendadas</h3>
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
// Dados da semana
const labelsWeek = ['Dom','Seg','Ter','Qua','Qui','Sex','Sáb'];
const scheduledWeek = <?php echo json_encode(array_values($scheduledHours)); ?>;
const workedWeek    = <?php echo json_encode(array_values($workedHours)); ?>;

const ctxWeek = document.getElementById('chartWeek').getContext('2d');
new Chart(ctxWeek, {
    type: 'bar',
    data: {
        labels: labelsWeek,
        datasets: [
            { label: 'Horas Agendadas', data: scheduledWeek, backgroundColor: 'rgba(54, 162, 235, 0.5)', borderColor:'rgba(54,162,235,1)', borderWidth:1 },
            { label: 'Horas Trabalhadas', data: workedWeek, backgroundColor: 'rgba(255, 99, 132, 0.5)', borderColor:'rgba(255,99,132,1)', borderWidth:1 }
        ]
    },
    options: {
        responsive: true,
        plugins: {
            title: { display: true, text: 'Horas por Dia da Semana' }
        },
        scales: {
            y: { beginAtZero: true, title: { display: true, text: 'Horas' } },
            x: { title: { display: true, text: 'Dia da Semana' } }
        }
    }
});

// Dados por funcionário
const labelsEmp = <?php echo json_encode($labelsEmp); ?>;
const scheduledEmp = <?php echo json_encode($scheduledEmp); ?>;
const workedEmp    = <?php echo json_encode($workedEmp); ?>;

const ctxEmp = document.getElementById('chartEmp').getContext('2d');
new Chart(ctxEmp, {
    type: 'bar',
    data: {
        labels: labelsEmp,
        datasets: [
            { label:'Horas Agendadas', data: scheduledEmp, backgroundColor:'rgba(54,162,235,0.5)', borderColor:'rgba(54,162,235,1)', borderWidth:1 },
            { label:'Horas Trabalhadas', data: workedEmp, backgroundColor:'rgba(255,99,132,0.5)', borderColor:'rgba(255,99,132,1)', borderWidth:1 }
        ]
    },
    options: {
        responsive: true,
        plugins: { title: { display: true, text: 'Horas por Funcionário' } },
        scales: {
            y: { beginAtZero:true, title: { display:true, text:'Horas' } },
            x: { title: { display:true, text:'Funcionário' } }
        }
    }
});
</script>
</body>
</html>
