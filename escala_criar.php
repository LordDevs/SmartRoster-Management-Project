<?php
declare(strict_types=1);
require_once __DIR__ . '/config.php';
// Troque para o helper do seu projeto:
if (!isset($_SESSION['user']) || !in_array($_SESSION['user']['role'] ?? '', ['admin','manager'])) {
  http_response_code(403);
  die('Access denied.');
}

$errors = [];
$success = null;

// Carrega funcionários: admin vê todos; manager vê apenas da própria loja
try {
  if (($_SESSION['user']['role'] ?? '') === 'manager' && !empty($_SESSION['user']['store_id'])) {
    $stmt = $pdo->prepare("SELECT id, name FROM employees WHERE store_id = :sid AND active = 1 ORDER BY name");
    $stmt->execute([':sid' => $_SESSION['user']['store_id']]);
  } else {
    $stmt = $pdo->query("SELECT id, name FROM employees WHERE active = 1 ORDER BY name");
  }
  $employees = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
  $errors[] = "Erro ao carregar funcionários: " . $e->getMessage();
  $employees = [];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $employee_id = (int)($_POST['employee_id'] ?? 0);
  $date        = trim($_POST['date'] ?? '');
  $start_time  = trim($_POST['start_time'] ?? '');
  $end_time    = trim($_POST['end_time'] ?? '');

  // validação básica
  if ($employee_id <= 0) $errors[] = "Selecione um funcionário.";
  if (!$date) $errors[] = "Informe a data do turno.";
  if (!$start_time || !$end_time) $errors[] = "Informe horário inicial e final.";
  if (!$errors && $start_time >= $end_time) $errors[] = "Hora final deve ser maior que a inicial.";

  // Se manager, garantir que o funcionário pertence à sua loja
  if (!$errors && ($_SESSION['user']['role'] ?? '') === 'manager' && !empty($_SESSION['user']['store_id'])) {
    $chk = $pdo->prepare("SELECT 1 FROM employees WHERE id = :id AND store_id = :sid");
    $chk->execute([':id'=>$employee_id, ':sid'=>$_SESSION['user']['store_id']]);
    if (!$chk->fetchColumn()) {
      $errors[] = "Você não pode criar turnos para funcionários de outra loja.";
    }
  }

  // Verificações de conflito e preferências
  if (!$errors) {
    // 1) Checar sobreposição: mesmo funcionário, mesmo dia, intervalo se intersecta
    $sqlOverlap = "
      SELECT COUNT(*) FROM shifts
      WHERE employee_id = :eid AND date = :dt
        AND NOT (end_time <= :start OR start_time >= :end)
    ";
    $st = $pdo->prepare($sqlOverlap);
    $st->execute([':eid'=>$employee_id, ':dt'=>$date, ':start'=>$start_time, ':end'=>$end_time]);
    if ((int)$st->fetchColumn() > 0) {
      $errors[] = "Conflito: o funcionário já possui turno nesse intervalo.";
    }

    // 2) Checar preferências do funcionário para o dia da semana
    $dwMap = ['0'=>'7', '1'=>'1','2'=>'2','3'=>'3','4'=>'4','5'=>'5','6'=>'6']; // MySQL WEEKDAY(): 0=Mon..6=Sun; nosso day_of_week 1=Mon..7=Sun
    $weekday = (int)date('N', strtotime($date)); // 1..7 (Mon..Sun)
    $pref = $pdo->prepare("
      SELECT available_start_time, available_end_time
      FROM employee_preferences
      WHERE employee_id = :eid AND day_of_week = :dow
    ");
    $pref->execute([':eid'=>$employee_id, ':dow'=>$weekday]);
    $row = $pref->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
      // Sem registro => indisponível nesse dia
      $errors[] = "O funcionário está indisponível neste dia (sem janela de disponibilidade cadastrada).";
    } else {
      $avStart = $row['available_start_time'];
      $avEnd   = $row['available_end_time'];
      if (!($start_time >= $avStart && $end_time <= $avEnd)) {
        $errors[] = "Fora da disponibilidade: janela do dia é $avStart–$avEnd.";
      }
    }
  }

  if (!$errors) {
    try {
      $ins = $pdo->prepare("
        INSERT INTO shifts (employee_id, date, start_time, end_time)
        VALUES (:eid, :dt, :st, :en)
      ");
      $ins->execute([
        ':eid'=>$employee_id, ':dt'=>$date, ':st'=>$start_time, ':en'=>$end_time
      ]);
      $success = "Turno criado com sucesso.";
    } catch (Throwable $e) {
      $errors[] = "Erro ao salvar turno: " . $e->getMessage();
    }
  }
}
?>
<!doctype html>
<html lang="pt-br">
<head>
  <meta charset="utf-8">
  <title>Criar Turno</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="p-3">
  <div class="container" style="max-width:720px">
    <h1 class="mb-3">Criar Turno</h1>

    <?php if ($success): ?>
      <div class="alert alert-success"><?=htmlspecialchars($success)?></div>
    <?php endif; ?>
    <?php foreach ($errors as $err): ?>
      <div class="alert alert-danger"><?=htmlspecialchars($err)?></div>
    <?php endforeach; ?>

    <form method="post" class="card card-body">
      <div class="mb-3">
        <label class="form-label">Funcionário</label>
        <select name="employee_id" class="form-select" required>
          <option value="">Selecione...</option>
          <?php foreach ($employees as $emp): ?>
            <option value="<?=$emp['id']?>" <?=isset($employee_id)&&$employee_id==(int)$emp['id']?'selected':''?>>
              <?=htmlspecialchars($emp['name'])?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="row g-3">
        <div class="col-md-4">
          <label class="form-label">Data</label>
          <input type="date" name="date" class="form-control" value="<?=htmlspecialchars($date ?? '')?>" required>
        </div>
        <div class="col-md-4">
          <label class="form-label">Início</label>
          <input type="time" name="start_time" class="form-control" value="<?=htmlspecialchars($start_time ?? '')?>" required>
        </div>
        <div class="col-md-4">
          <label class="form-label">Fim</label>
          <input type="time" name="end_time" class="form-control" value="<?=htmlspecialchars($end_time ?? '')?>" required>
        </div>
      </div>
      <div class="mt-3 d-flex gap-2">
        <button class="btn btn-primary">Salvar</button>
        <a class="btn btn-outline-secondary" href="escala_listar.php">Voltar</a>
      </div>
    </form>
  </div>
</body>
</html>
