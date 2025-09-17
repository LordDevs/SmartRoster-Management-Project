<?php
// escala_sugestao.php – geração de sugestões de escalas com heurísticas e preferências de disponibilidade.
// Esta página permite a gerentes e administradores gerar turnos sugeridos considerando as horas
// trabalhadas recentemente e as preferências de disponibilidade dos funcionários.

require_once 'config.php';
requirePrivileged();

// Determinar o papel e a loja (caso seja gerente)
$role = $_SESSION['user_role'];
$currentUser = currentUser();
$storeId = null;
if ($role === 'manager') {
    $storeId = $currentUser['store_id'] ?? null;
}

// Aplicar sugestões salvas na sessão (se solicitado)
if (isset($_GET['apply']) && $_GET['apply'] === '1' && isset($_SESSION['shift_suggestions'])) {
    $suggestions = $_SESSION['shift_suggestions'];
    foreach ($suggestions as $sug) {
        // Se for gerente, inserir apenas turnos de funcionários da sua loja
        if ($role === 'manager' && $storeId !== null) {
            $stmtCheck = $pdo->prepare('SELECT store_id FROM employees WHERE id = ?');
            $stmtCheck->execute([$sug['employee_id']]);
            $empStore = $stmtCheck->fetchColumn();
            if ($empStore != $storeId) {
                continue;
            }
        }
        $stmtIns = $pdo->prepare('INSERT INTO shifts (employee_id, date, start_time, end_time) VALUES (?, ?, ?, ?)');
        $stmtIns->execute([$sug['employee_id'], $sug['date'], $sug['start_time'], $sug['end_time']]);
    }
    unset($_SESSION['shift_suggestions']);
    header('Location: escala_listar.php');
    exit();
}

/**
 * Calcular as horas trabalhadas nos últimos 7 dias para um funcionário.
 *
 * @param PDO $pdo
 * @param int $employeeId
 * @return float Horas trabalhadas
 */
function computeHoursLast7Days(PDO $pdo, int $employeeId): float
{
    $stmt = $pdo->prepare(
        "SELECT SUM(strftime('%s', COALESCE(te.clock_out, CURRENT_TIMESTAMP)) - strftime('%s', te.clock_in)) AS seconds_worked
         FROM time_entries te
         WHERE te.employee_id = ? AND DATE(te.clock_in) >= DATE('now', '-6 days')"
    );
    $stmt->execute([$employeeId]);
    $seconds = $stmt->fetchColumn() ?: 0;
    return $seconds / 3600.0;
}

/**
 * Obter um mapa de preferências de disponibilidade por funcionário e dia da semana.
 *
 * @param PDO $pdo
 * @return array Estrutura [employee_id][day_of_week] => ['start' => HH:MM, 'end' => HH:MM]
 */
function fetchEmployeePreferencesMap(PDO $pdo): array
{
    $stmt = $pdo->query('SELECT employee_id, day_of_week, available_start_time, available_end_time FROM employee_preferences');
    $map = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $empId = (int)$row['employee_id'];
        $day   = (int)$row['day_of_week'];
        $map[$empId][$day] = ['start' => $row['available_start_time'], 'end' => $row['available_end_time']];
    }
    return $map;
}

$suggestions = [];

// Se o formulário foi enviado para gerar sugestões
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['generate_suggestion'])) {
    $start_time = $_POST['start_time'] ?? '09:00';
    $end_time   = $_POST['end_time'] ?? '17:00';
    $days       = max(1, min(30, (int)($_POST['days'] ?? 7)));

    // Buscar funcionários (apenas da loja, se gerente)
    if ($role === 'manager') {
        $stmtEmp = $pdo->prepare('SELECT id, name FROM employees WHERE store_id = ? ORDER BY name');
        $stmtEmp->execute([$storeId]);
        $employees = $stmtEmp->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $employees = $pdo->query('SELECT id, name FROM employees ORDER BY name')->fetchAll(PDO::FETCH_ASSOC);
    }

    // Calcular horas trabalhadas por funcionário nos últimos 7 dias
    $empData = [];
    foreach ($employees as $emp) {
        $hours = computeHoursLast7Days($pdo, (int)$emp['id']);
        $empData[] = ['id' => (int)$emp['id'], 'name' => $emp['name'], 'hours' => $hours];
    }
    // Ordenar do menor para o maior número de horas trabalhadas
    usort($empData, function ($a, $b) {
        return $a['hours'] <=> $b['hours'];
    });

    // Mapa de preferências de disponibilidade
    $prefsMap = fetchEmployeePreferencesMap($pdo);

    // Gerar sugestões ciclando pelos funcionários para cada dia. Para balancear, rotacionar o início da lista conforme o dia.
    $startDate = date('Y-m-d');
    $numEmps = count($empData);
    for ($d = 0; $d < $days; $d++) {
        $date = date('Y-m-d', strtotime("$startDate +{$d} day"));
        $dayOfWeek = (int)date('w', strtotime($date)); // 0=Domingo ... 6=Sábado
        // Calcular índice inicial para este dia para rodar a lista de funcionários
        $startIndex = ($numEmps > 0) ? ($d % $numEmps) : 0;
        for ($i = 0; $i < $numEmps; $i++) {
            $emp = $empData[($startIndex + $i) % $numEmps];
            $empId = $emp['id'];
            // Verificar preferência de disponibilidade para este funcionário/dia
            $pref = $prefsMap[$empId][$dayOfWeek] ?? null;
            if ($pref) {
                // Se o horário do turno estiver fora do intervalo disponível, pula
                if (strcmp($start_time, $pref['start']) < 0 || strcmp($end_time, $pref['end']) > 0) {
                    continue;
                }
            }
            $suggestions[] = [
                'date' => $date,
                'employee_id' => $empId,
                'employee_name' => $emp['name'],
                'start_time' => $start_time,
                'end_time' => $end_time
            ];
        }
    }
    // Salvar na sessão para aplicação posterior
    $_SESSION['shift_suggestions'] = $suggestions;
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Sugestão Inteligente de Escala – Escala Hillbillys</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <?php
        // Sugestão de escalas pertence à área de escalas
        $activePage = 'escalas';
        require_once __DIR__ . '/navbar.php';
    ?>
    <div class="container mt-4">
        <h3>Sugestão de Escalas (IA/Heurística)</h3>
        <?php if (empty($suggestions)): ?>
            <p>Insira os parâmetros abaixo para gerar sugestões de escalas considerando as horas trabalhadas e as preferências de disponibilidade dos funcionários.</p>
            <form method="post">
                <div class="row g-3 mb-3">
                    <div class="col-md-4">
                        <label class="form-label">Horário de Início</label>
                        <input type="time" class="form-control" name="start_time" value="09:00" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Horário de Término</label>
                        <input type="time" class="form-control" name="end_time" value="17:00" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Dias a sugerir</label>
                        <input type="number" class="form-control" name="days" value="7" min="1" max="30">
                    </div>
                </div>
                <button type="submit" name="generate_suggestion" class="btn btn-success">Gerar Sugestões</button>
                <a href="escala_listar.php" class="btn btn-secondary">Cancelar</a>
            </form>
        <?php else: ?>
            <h5>Sugestões Geradas</h5>
            <p>Foram geradas <?php echo count($suggestions); ?> escalas sugeridas.</p>
            <div class="table-responsive">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>Data</th>
                            <th>Início</th>
                            <th>Término</th>
                            <th>Funcionário</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($suggestions as $s): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($s['date']); ?></td>
                                <td><?php echo htmlspecialchars($s['start_time']); ?></td>
                                <td><?php echo htmlspecialchars($s['end_time']); ?></td>
                                <td><?php echo htmlspecialchars($s['employee_name']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="mt-3">
                <a href="escala_sugestao.php?apply=1" class="btn btn-primary">Aplicar Sugestões</a>
                <a href="escala_sugestao.php" class="btn btn-secondary">Refazer</a>
            </div>
        <?php endif; ?>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>