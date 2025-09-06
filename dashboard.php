<?php
// dashboard.php – main dashboard for logged‑in users
require_once 'config.php';
requireAdmin();

// Fetch some basic statistics for display on the dashboard

// Total number of employees
$employeeCount = $pdo->query('SELECT COUNT(*) FROM employees')->fetchColumn();

// Total number of upcoming shifts (today and future)
$shiftsCount = $pdo->query("SELECT COUNT(*) FROM shifts WHERE date >= DATE('now')")->fetchColumn();

// Total hours logged this month (sum of clock_out - clock_in)
$stmt = $pdo->prepare("SELECT SUM(strftime('%s', COALESCE(clock_out, CURRENT_TIMESTAMP)) - strftime('%s', clock_in)) AS seconds_worked FROM time_entries WHERE strftime('%Y-%m', clock_in) = strftime('%Y-%m', 'now')");
$stmt->execute();
$totalSeconds = $stmt->fetchColumn() ?: 0;
$totalHours = round($totalSeconds / 3600, 2);

// Fetch hours worked per day for the last 7 days for chart
$hoursData = [];
for ($i = 6; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-{$i} days"));
    $stmt = $pdo->prepare("SELECT SUM(strftime('%s', COALESCE(clock_out, CURRENT_TIMESTAMP)) - strftime('%s', clock_in)) AS seconds_worked FROM time_entries WHERE DATE(clock_in) = ?");
    $stmt->execute([$date]);
    $seconds = $stmt->fetchColumn() ?: 0;
    $hoursData[] = [
        'date' => $date,
        'hours' => round($seconds / 3600, 2)
    ];
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>Painel – Escala Hillbillys</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
    <style>
        body { padding-top: 60px; }
    </style>
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary fixed-top">
        <div class="container-fluid">
            <a class="navbar-brand" href="dashboard.php">Escala Hillbillys</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarSupportedContent" aria-controls="navbarSupportedContent" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarSupportedContent">
                <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                    <li class="nav-item"><a class="nav-link" href="usuarios_listar.php">Funcionários</a></li>
                    <li class="nav-item"><a class="nav-link" href="escala_listar.php">Escalas</a></li>
                    <li class="nav-item"><a class="nav-link" href="escala_calendario.php">Calendário</a></li>
                    <li class="nav-item"><a class="nav-link" href="ponto_listar.php">Pontos</a></li>
                    <li class="nav-item"><a class="nav-link" href="relatorios.php">Relatórios</a></li>
                    <li class="nav-item"><a class="nav-link" href="desempenho.php">Desempenho</a></li>
                    <li class="nav-item"><a class="nav-link active" href="analytics.php">Métricas</a>
                    <li class="nav-item"><a class="nav-link" href="loja_gerenciar.php">Loja</a></li>
                </ul>
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item"><a class="nav-link" href="logout.php">Sair</a></li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container">
        <div class="row mb-4">
            <div class="col-md-4">
                <div class="card text-white bg-success mb-3">
                    <div class="card-header">Funcionários</div>
                    <div class="card-body">
                        <h5 class="card-title">Total: <?php echo $employeeCount; ?></h5>
                        <p class="card-text">Número de funcionários cadastrados.</p>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card text-white bg-info mb-3">
                    <div class="card-header">Escalas Futuras</div>
                    <div class="card-body">
                        <h5 class="card-title">Total: <?php echo $shiftsCount; ?></h5>
                        <p class="card-text">Número de turnos agendados.</p>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card text-white bg-warning mb-3">
                    <div class="card-header">Horas no Mês</div>
                    <div class="card-body">
                        <h5 class="card-title">Total: <?php echo $totalHours; ?> h</h5>
                        <p class="card-text">Horas trabalhadas no mês atual.</p>
                    </div>
                </div>
            </div>
        </div>
        <div class="card">
            <div class="card-header">Horas trabalhadas (últimos 7 dias)</div>
            <div class="card-body">
                <canvas id="hoursChart"></canvas>
            </div>
        </div>
    </div>

    <script>
    // Prepare data for the hours chart
    const chartLabels = <?php echo json_encode(array_column($hoursData, 'date')); ?>;
    const chartData = <?php echo json_encode(array_column($hoursData, 'hours')); ?>;

    const ctx = document.getElementById('hoursChart').getContext('2d');
    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: chartLabels,
            datasets: [{
                label: 'Horas trabalhadas',
                data: chartData,
                backgroundColor: 'rgba(54, 162, 235, 0.5)',
                borderColor: 'rgba(54, 162, 235, 1)',
                borderWidth: 1
            }]
        },
        options: {
            scales: {
                y: {
                    beginAtZero: true,
                    title: { display: true, text: 'Horas' }
                },
                x: {
                    title: { display: true, text: 'Data' }
                }
            },
            plugins: {
                legend: { display: false },
                title: { display: false }
            }
        }
    });
    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>