<?php
// escala_calendario_semana.php
// Visualização de escalas em formato semanal. Exibe cada dia da semana corrente (ou de uma semana
// selecionada via parâmetro) com os turnos listados em cartões. Fornece uma experiência mais
// moderna e intuitiva em relação ao calendário interativo mensal, permitindo ao usuário
// visualizar rapidamente quem está escalado em cada dia. Administradores veem todas as lojas;
// gerentes veem apenas os turnos da sua loja.

require_once 'config.php';

// Verifica login e restringe acesso apenas a administradores ou gerentes
requireLogin();
$currentUser = currentUser();
if (!$currentUser || !in_array($currentUser['role'], ['admin','manager'])) {
    header('Location: dashboard.php');
    exit();
}

// Determina a semana a ser exibida. Aceita parâmetro GET 'week' no formato YYYY-MM-DD
// representando qualquer dia da semana desejada. Se não houver, usa a data atual.
$weekParam = isset($_GET['week']) ? $_GET['week'] : date('Y-m-d');
try {
    $refDate = new DateTime($weekParam);
} catch (Exception $e) {
    $refDate = new DateTime();
}
// Ajusta para a segunda-feira da semana de referência (ISO 8601: Monday = 1)
$dayOfWeek = (int)$refDate->format('N');
$monday     = clone $refDate;
$monday->modify('-' . ($dayOfWeek - 1) . ' days');
$sunday     = clone $monday;
$sunday->modify('+6 days');
$dateStart  = $monday->format('Y-m-d');
$dateEnd    = $sunday->format('Y-m-d');

// Recupera turnos da semana conforme o papel do usuário
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

// Organiza turnos por dia
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

// Função auxiliar para formatar data no padrão "Dia, DD de Mês"
function formatDateLabel($dateStr) {
    $dt = DateTime::createFromFormat('Y-m-d', $dateStr);
    $dias  = ['Domingo','Segunda','Terça','Quarta','Quinta','Sexta','Sábado'];
    $meses = ['janeiro','fevereiro','março','abril','maio','junho','julho','agosto','setembro','outubro','novembro','dezembro'];
    $diaSemana = $dias[(int)$dt->format('w')];
    $dia       = $dt->format('d');
    $mes       = $meses[(int)$dt->format('n') - 1];
    return "$diaSemana, $dia de $mes";
}

// Calcula links para semana anterior e próxima semana
$prevWeekDate = clone $monday;
$prevWeekDate->modify('-7 days');
$nextWeekDate = clone $monday;
$nextWeekDate->modify('+7 days');
$prevWeekParam = $prevWeekDate->format('Y-m-d');
$nextWeekParam = $nextWeekDate->format('Y-m-d');

?><!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Escalas Semanais – Escala Hillbillys</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    <style>
        body { padding-top: 60px; }
        .day-card { min-height: 200px; }
    </style>
</head>
<body>
<?php
    // Define a página ativa como "calendario" para que o item Calendário seja destacado no menu
    $activePage = 'calendario';
    require_once __DIR__ . '/navbar.php';
?>
<div class="container">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h3>Escalas – Semana de <?php echo htmlspecialchars($monday->format('d/m/Y')); ?> a <?php echo htmlspecialchars($sunday->format('d/m/Y')); ?></h3>
        <div>
            <a href="?week=<?php echo urlencode($prevWeekParam); ?>" class="btn btn-outline-primary btn-sm me-2">&laquo; Semana Anterior</a>
            <a href="?week=<?php echo urlencode($nextWeekParam); ?>" class="btn btn-outline-primary btn-sm">Próxima Semana &raquo;</a>
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
                        <p class="text-muted">Sem turnos</p>
                    <?php else: ?>
                        <ul class="list-unstyled">
                            <?php foreach ($shifts as $sh): ?>
                            <li class="mb-2">
                                <strong><?php echo htmlspecialchars($sh['start_time']); ?>–<?php echo htmlspecialchars($sh['end_time']); ?></strong><br>
                                <span><?php echo htmlspecialchars($sh['employee_name']); ?></span><br>
                                <small>
                                    <a href="escala_editar.php?id=<?php echo urlencode($sh['id']); ?>" class="text-decoration-none">Editar</a>
                                    |
                                    <a href="escala_excluir.php?id=<?php echo urlencode($sh['id']); ?>" class="text-danger text-decoration-none" onclick="return confirm('Excluir este turno?');">Excluir</a>
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
        <a href="escala_criar.php" class="btn btn-success">Novo Turno</a>
        <a href="escala_calendario.php" class="btn btn-outline-secondary ms-2">Ver Calendário Interativo</a>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>