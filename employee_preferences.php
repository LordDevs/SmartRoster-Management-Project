<?php
declare(strict_types=1);
require_once __DIR__ . '/config.php';
// Troque para requireEmployee() se tiver:
if (!isset($_SESSION['user']) || !in_array($_SESSION['user']['role'] ?? '', ['employee','admin','manager'])) {
  http_response_code(403);
  die('Access denied.');
}

// Descobrir employee_id do usuário logado (supondo users.employee_id)
$employee_id = (int)($_SESSION['user']['employee_id'] ?? 0);
// Admin/manager podem editar preferências de um funcionário via ?employee_id=#
if (($employee_id <= 0) && in_array($_SESSION['user']['role'], ['admin','manager'], true)) {
  $employee_id = (int)($_GET['employee_id'] ?? 0);
}
if ($employee_id <= 0) {
  http_response_code(400);
  die('Funcionário não identificado.');
}

$errors = [];
$success = null;

// Salvar
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  // Espera inputs: start[1..7], end[1..7]
  $pdo->beginTransaction();
  try {
    for ($dow=1; $dow<=7; $dow++) {
      $start = trim($_POST['start'][$dow] ?? '');
      $end   = trim($_POST['end'][$dow] ?? '');

      // se ambos vazios => deletar preferência do dia
      if ($start === '' && $end === '') {
        $del = $pdo->prepare("DELETE FROM employee_preferences WHERE employee_id = :eid AND day_of_week = :dow");
        $del->execute([':eid'=>$employee_id, ':dow'=>$dow]);
        continue;
      }

      // se um deles vazio => erro simples
      if ($start === '' || $end === '') {
        throw new RuntimeException("Dia $dow: informe início e fim, ou deixe ambos vazios.");
      }
      if ($start >= $end) {
        throw new RuntimeException("Dia $dow: fim deve ser maior que início.");
      }

      // upsert simples
      $sel = $pdo->prepare("SELECT id FROM employee_preferences WHERE employee_id = :eid AND day_of_week = :dow");
      $sel->execute([':eid'=>$employee_id, ':dow'=>$dow]);
      $id = $sel->fetchColumn();

      if ($id) {
        $upd = $pdo->prepare("
          UPDATE employee_preferences
          SET available_start_time = :st, available_end_time = :en
          WHERE id = :id
        ");
        $upd->execute([':st'=>$start, ':en'=>$end, ':id'=>$id]);
      } else {
        $ins = $pdo->prepare("
          INSERT INTO employee_preferences (employee_id, day_of_week, available_start_time, available_end_time)
          VALUES (:eid, :dow, :st, :en)
        ");
        $ins->execute([':eid'=>$employee_id, ':dow'=>$dow, ':st'=>$start, ':en'=>$end]);
      }
    }
    $pdo->commit();
    $success = "Preferências salvas.";
  } catch (Throwable $e) {
    $pdo->rollBack();
    $errors[] = "Erro ao salvar: " . $e->getMessage();
  }
}

// Carrega para exibir
$pref = $pdo->prepare("
  SELECT day_of_week, available_start_time, available_end_time
  FROM employee_preferences
  WHERE employee_id = :eid
");
$pref->execute([':eid'=>$employee_id]);
$current = [];
foreach ($pref->fetchAll(PDO::FETCH_ASSOC) as $r) {
  $current[(int)$r['day_of_week']] = $r;
}

$days = [1=>'Seg',2=>'Ter',3=>'Qua',4=>'Qui',5=>'Sex',6=>'Sáb',7=>'Dom'];
?>
<!doctype html>
<html lang="pt-br">
<head>
  <meta charset="utf-8">
  <title>Preferências de Disponibilidade</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="p-3">
  <div class="container" style="max-width:840px">
    <h1 class="mb-3">Preferências de Disponibilidade</h1>
    <p class="text-muted">Deixe ambos os campos vazios em um dia para marcar **indisponível**.</p>

    <?php if ($success): ?><div class="alert alert-success"><?=$success?></div><?php endif; ?>
    <?php foreach ($errors as $e): ?><div class="alert alert-danger"><?=$e?></div><?php endforeach; ?>

    <form method="post" class="card card-body">
      <div class="table-responsive">
        <table class="table align-middle">
          <thead>
            <tr><th>Dia</th><th>Início</th><th>Fim</th></tr>
          </thead>
          <tbody>
          <?php foreach ($days as $dnum=>$dname): 
            $st = $current[$dnum]['available_start_time'] ?? '';
            $en = $current[$dnum]['available_end_time'] ?? '';
          ?>
            <tr>
              <td class="fw-semibold"><?=$dname?></td>
              <td style="max-width:200px">
                <input type="time" class="form-control" name="start[<?=$dnum?>]" value="<?=htmlspecialchars($st)?>">
              </td>
              <td style="max-width:200px">
                <input type="time" class="form-control" name="end[<?=$dnum?>]" value="<?=htmlspecialchars($en)?>">
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>

      <div class="mt-3 d-flex gap-2">
        <button class="btn btn-primary">Salvar</button>
        <a class="btn btn-outline-secondary" href="employee_dashboard.php">Voltar</a>
      </div>
    </form>
  </div>
</body>
</html>
