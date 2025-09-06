<?php
// preferencias.php – página para os funcionários definirem suas preferências de disponibilidade de turnos.
// Somente usuários com papel "employee" podem acessar esta página.

require_once 'config.php';
requireLogin();

// Garantir que apenas funcionários acessam
$currentUser = currentUser();
if (!$currentUser || $currentUser['role'] !== 'employee') {
    header('Location: dashboard.php');
    exit();
}

$employeeId = $currentUser['employee_id'];
$message = null;

// Processar envio do formulário
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Para cada dia da semana (0=Domingo até 6=Sábado)
    for ($d = 0; $d < 7; $d++) {
        $start = $_POST['start'][$d] ?? '';
        $end   = $_POST['end'][$d] ?? '';
        // Normalizar campos vazios para null
        $start = trim($start);
        $end = trim($end);
        // Se ambos horários fornecidos
        if ($start !== '' && $end !== '') {
            // Verificar se já existe uma preferência para este dia
            $stmtCheck = $pdo->prepare('SELECT id FROM employee_preferences WHERE employee_id = ? AND day_of_week = ?');
            $stmtCheck->execute([$employeeId, $d]);
            $prefId = $stmtCheck->fetchColumn();
            if ($prefId) {
                // Atualizar existente
                $stmtUpd = $pdo->prepare('UPDATE employee_preferences SET available_start_time = ?, available_end_time = ? WHERE id = ?');
                $stmtUpd->execute([$start, $end, $prefId]);
            } else {
                // Inserir novo registro
                $stmtIns = $pdo->prepare('INSERT INTO employee_preferences (employee_id, day_of_week, available_start_time, available_end_time) VALUES (?, ?, ?, ?)');
                $stmtIns->execute([$employeeId, $d, $start, $end]);
            }
        } else {
            // Se não há horários, remover a preferência existente (se houver)
            $stmtDel = $pdo->prepare('DELETE FROM employee_preferences WHERE employee_id = ? AND day_of_week = ?');
            $stmtDel->execute([$employeeId, $d]);
        }
    }
    $message = 'Preferências atualizadas com sucesso.';
}

// Buscar preferências existentes para exibir no formulário
$stmtPrefs = $pdo->prepare('SELECT day_of_week, available_start_time, available_end_time FROM employee_preferences WHERE employee_id = ?');
$stmtPrefs->execute([$employeeId]);
$prefs = [];
foreach ($stmtPrefs->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $prefs[$row['day_of_week']] = $row;
}

// Nomes dos dias da semana em português
$dayNames = ['Domingo', 'Segunda', 'Terça', 'Quarta', 'Quinta', 'Sexta', 'Sábado'];
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Preferências de Turno – Escala Hillbillys</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container-fluid">
            <a class="navbar-brand" href="portal.php">Escala Hillbillys</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Alternar navegação">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                    <li class="nav-item"><a class="nav-link" href="portal.php">Próximos Turnos</a></li>
                    <li class="nav-item"><a class="nav-link active" aria-current="page" href="preferencias.php">Preferências</a></li>
                </ul>
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item"><a class="nav-link" href="logout.php">Sair</a></li>
                </ul>
            </div>
        </div>
    </nav>
    <div class="container mt-4">
        <h3>Minhas Preferências de Turno</h3>
        <?php if ($message): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>
        <p>Defina os horários nos quais você está disponível para trabalhar em cada dia da semana. Deixe em branco para indicar que não está disponível naquele dia.</p>
        <form method="post">
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th>Dia</th>
                        <th>Início Disponível</th>
                        <th>Fim Disponível</th>
                    </tr>
                </thead>
                <tbody>
                    <?php for ($i = 0; $i < 7; $i++): ?>
                        <?php
                            $pref = $prefs[$i] ?? null;
                            $startVal = $pref['available_start_time'] ?? '';
                            $endVal   = $pref['available_end_time'] ?? '';
                        ?>
                        <tr>
                            <td><?php echo htmlspecialchars($dayNames[$i]); ?></td>
                            <td><input type="time" class="form-control" name="start[<?php echo $i; ?>]" value="<?php echo htmlspecialchars($startVal); ?>"></td>
                            <td><input type="time" class="form-control" name="end[<?php echo $i; ?>]" value="<?php echo htmlspecialchars($endVal); ?>"></td>
                        </tr>
                    <?php endfor; ?>
                </tbody>
            </table>
            <button type="submit" class="btn btn-primary">Salvar Preferências</button>
            <a href="portal.php" class="btn btn-secondary ms-2">Voltar</a>
        </form>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>