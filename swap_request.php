<?php
declare(strict_types=1);
require_once __DIR__ . '/config.php';
// Troque para requireEmployee() se tiver
if (!isset($_SESSION['user']) || ($_SESSION['user']['role'] ?? '') !== 'employee') {
  http_response_code(403);
  die('Access denied.');
}

$employee_id = (int)($_SESSION['user']['employee_id'] ?? 0);
if ($employee_id <= 0) {
  http_response_code(400);
  die('Funcionário não identificado.');
}

$errors = [];
$success = null;

// Loja do funcionário (para filtrar elegíveis)
$storeId = null;
try {
  $s = $pdo->prepare("SELECT store_id FROM employees WHERE id = :id");
  $s->execute([':id'=>$employee_id]);
  $storeId = (int)$s->fetchColumn();
} catch (Throwable $e) {
  $errors[] = "Erro ao obter loja do funcionário: " . $e->getMessage();
}

// Turnos futuros do próprio funcionário
$myShifts = [];
if ($storeId) {
  $q = $pdo->prepare("
    SELECT id, date, start_time, end_time
    FROM shifts
    WHERE employee_id = :eid AND date >= CURDATE()
    ORDER BY date, start_time
  ");
  $q->execute([':eid'=>$employee_id]);
  $myShifts = $q->fetchAll(PDO::FETCH_ASSOC);
}

// Funcionários elegíveis da mesma loja (exclui o próprio)
$targetEmployees = [];
if ($storeId) {
  $qe = $pdo->prepare("
    SELECT id, name FROM employees
    WHERE store_id = :sid AND active = 1 AND id <> :eid
    ORDER BY name
  ");
  $qe->execute([':sid'=>$storeId, ':eid'=>$employee_id]);
  $targetEmployees = $qe->fetchAll(PDO::FETCH_ASSOC);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $shift_id     = (int)($_POST['shift_id'] ?? 0);
  $requested_to = (int)($_POST['requested_to'] ?? 0);

  if ($shift_id <= 0) $errors[] = "Selecione um turno.";
  if ($requested_to <= 0) $errors[] = "Selecione o colega para quem deseja ceder o turno.";

  // Validar que o turno é do solicitante
  if (!$errors) {
    $own = $pdo->prepare("SELECT 1 FROM shifts WHERE id = :sid AND employee_id = :eid");
    $own->execute([':sid'=>$shift_id, ':eid'=>$employee_id]);
    if (!$own->fetchColumn()) $errors[] = "Turno inválido.";
  }

  // Validar que o requested_to é da mesma loja
  if (!$errors && $storeId) {
    $chk = $pdo->prepare("SELECT 1 FROM employees WHERE id = :tid AND store_id = :sid AND active = 1");
    $chk->execute([':tid'=>$requested_to, ':sid'=>$storeId]);
    if (!$chk->fetchColumn()) $errors[] = "Colega inválido para troca (loja diferente?).";
  }

  if (!$errors) {
    try {
      $ins = $pdo->prepare("
        INSERT INTO swap_requests (shift_id, requested_by, requested_to, status)
        VALUES (:sid, :by, :to, 'pending')
      ");
      $ins->execute([':sid'=>$shift_id, ':by'=>$employee_id, ':to'=>$requested_to]);
      $success = "Solicitação enviada. Aguarde aprovação.";
    } catch (Throwable $e) {
      $errors[] = "Erro ao registrar solicitação: " . $e->getMessage();
    }
  }
}
?>
<!doctype html>
<html lang="pt-br">
<head>
  <meta charset="utf-8">
  <title>Solicitar Troca de Turno</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="p-3">
  <div class="container" style="max-width:720px">
    <h1 class="mb-3">Solicitar Troca de Turno</h1>

    <?php if ($success): ?><div class="alert alert-success"><?=$success?></div><?php endif; ?>
    <?php foreach ($errors as $e): ?><div class="alert alert-danger"><?=$e?></div><?php endforeach; ?>

    <form method="post" class="card card-body">
      <div class="mb-3">
        <label class="form-label">Meu turno</label>
        <select name="shift_id" class="form-select" required>
          <option value="">Selecione...</option>
          <?php foreach ($myShifts as $s): ?>
          <option value="<?=$s['id']?>" <?=isset($shift_id)&&$shift_id==(int)$s['id']?'selected':''?>>
            <?=htmlspecialchars(sprintf("%s • %s–%s",
              $s['date'], substr($s['start_time'],0,5), substr($s['end_time'],0,5)
            ))?>
          </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="mb-3">
        <label class="form-label">Colega (mesma loja)</label>
        <select name="requested_to" class="form-select" required>
          <option value="">Selecione...</option>
          <?php foreach ($targetEmployees as $e): ?>
          <option value="<?=$e['id']?>" <?=isset($requested_to)&&$requested_to==(int)$e['id']?'selected':''?>>
            <?=htmlspecialchars($e['name'])?>
          </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="d-flex gap-2">
        <button class="btn btn-primary">Enviar pedido</button>
        <a class="btn btn-outline-secondary" href="employee_dashboard.php">Voltar</a>
      </div>
    </form>
  </div>
</body>
</html>
