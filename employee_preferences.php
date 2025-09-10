<?php
require_once 'config.php';
require_once 'authenticate.php';
require_once 'helpers.php';

// Garante que apenas funcionários logados possam acessar
$funcionario_id = $_SESSION['user_id'] ?? null;
if (!$funcionario_id) {
    header('Location: index.php');
    exit;
}

// Recupera preferências existentes
$stmt = $conn->prepare('SELECT day_of_week, available_start, available_end, max_hours_per_day, min_rest_hours FROM employee_preferences WHERE funcionario_id = ?');
$stmt->bind_param('i', $funcionario_id);
$stmt->execute();
$result = $stmt->get_result();
$preferences = [];
while ($row = $result->fetch_assoc()) {
    $preferences[$row['day_of_week']] = $row;
}
$stmt->close();

// Funções auxiliares para valores padrão
function get_pref_field($prefs, $day, $field) {
    return isset($prefs[$day][$field]) ? $prefs[$day][$field] : '';
}

?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Preferências de Funcionário</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" integrity="sha384-9ndCyUa4y3Yz2+1Jqv1MDwse4bCMZJ6uaOYJ6xZgTwHUnYlXyt1e1NEMqH9cO2+" crossorigin="anonymous">
</head>
<body>
<?php include 'navbar.php'; ?>
<div class="container mt-4">
    <h1>Minhas Preferências de Turno</h1>
    <form action="employee_preferences_save.php" method="post">
        <input type="hidden" name="funcionario_id" value="<?php echo htmlspecialchars($funcionario_id); ?>">
        <table class="table table-bordered">
            <thead>
                <tr>
                    <th>Dia da Semana</th>
                    <th>Início Disponível</th>
                    <th>Fim Disponível</th>
                    <th>Máx Horas/Dia</th>
                    <th>Descanso Mín. (h)</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $dias = ['Domingo','Segunda','Terça','Quarta','Quinta','Sexta','Sábado'];
                for ($d = 0; $d < 7; $d++):
                    $pref = $preferences[$d] ?? [];
                ?>
                <tr>
                    <td><?php echo $dias[$d]; ?></td>
                    <td><input type="time" name="preferences[<?php echo $d; ?>][available_start]" value="<?php echo htmlspecialchars(get_pref_field($preferences, $d, 'available_start')); ?>" class="form-control"></td>
                    <td><input type="time" name="preferences[<?php echo $d; ?>][available_end]" value="<?php echo htmlspecialchars(get_pref_field($preferences, $d, 'available_end')); ?>" class="form-control"></td>
                    <td><input type="number" step="0.5" name="preferences[<?php echo $d; ?>][max_hours_per_day]" value="<?php echo htmlspecialchars(get_pref_field($preferences, $d, 'max_hours_per_day')); ?>" class="form-control"></td>
                    <td><input type="number" step="0.5" name="preferences[<?php echo $d; ?>][min_rest_hours]" value="<?php echo htmlspecialchars(get_pref_field($preferences, $d, 'min_rest_hours')); ?>" class="form-control"></td>
                </tr>
                <?php endfor; ?>
            </tbody>
        </table>
        <div class="mb-3">
            <label for="weekly_max_hours" class="form-label">Horas Máximas por Semana:</label>
            <input type="number" step="0.5" name="weekly_max_hours" id="weekly_max_hours" value="<?php echo htmlspecialchars(getWeeklyMaxHours($funcionario_id, $conn)); ?>" class="form-control" required>
        </div>
        <div class="mb-3">
            <label for="hourly_rate" class="form-label">Salário por Hora (€):</label>
            <input type="number" step="0.01" name="hourly_rate" id="hourly_rate" value="<?php echo htmlspecialchars(getHourlyRate($funcionario_id, $conn)); ?>" class="form-control" required>
        </div>
        <button type="submit" class="btn btn-primary">Salvar Preferências</button>
    </form>
</div>
</body>
</html>