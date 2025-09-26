<?php
declare(strict_types=1);
require_once __DIR__ . '/config.php';

// Troque por requireLogin() se quiser permitir employees verem a lista (só leitura)
if (!isset($_SESSION['user'])) {
  http_response_code(403);
  die('Access denied.');
}

$role = $_SESSION['user']['role'] ?? '';
$storeId = (int)($_SESSION['user']['store_id'] ?? 0);

$errors = [];

// Lista de turnos futuros
try {
  if ($role === 'manager' && $storeId > 0) {
    $sql = "
      SELECT s.id, s.date, s.start_time, s.end_time, e.name AS employee
      FROM shifts s
      JOIN employees e ON e.id = s.employee_id
      WHERE s.date >= CURDATE() AND e.store_id = :sid
      ORDER BY s.date, s.start_time
    ";
    $st = $pdo->prepare($sql);
    $st->execute([':sid'=>$storeId]);
  } else {
    $sql = "
      SELECT s.id, s.date, s.start_time, s.end_time, e.name AS employee, st.name AS store_name
      FROM shifts s
      JOIN employees e ON e.id = s.employee_id
      LEFT JOIN stores st ON st.id = e.store_id
      WHERE s.date >= CURDATE()
      ORDER BY s.date, s.start_time
    ";
    $st = $pdo->query($sql);
  }
  $shifts = $st->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
  $errors[] = "Erro ao carregar escalas: " . $e->getMessage();
  $shifts = [];
}

// Solicitações pendentes (somente admin/manager)
$pending = [];
if (in_array($role, ['admin','manager'], true)) {
  try {
    if ($role === 'manager' && $storeId > 0) {
      $q = $pdo->prepare("
        SELECT sr.id, s.date, s.start_time, s.end_time,
               e1.name AS requested_by, e2.name AS requested_to
        FROM swap_requests sr
        JOIN shifts s ON s.id = sr.shift_id
        JOIN employees e1 ON e1.id = sr.requested_by
        JOIN employees e2 ON e2.id = sr.requested_to
        WHERE sr.status = 'pending' AND e1.store_id = :sid AND e2.store_id = :sid
        ORDER BY sr.id DESC
      ");
      $q->execute([':sid'=>$storeId]);
    } else {
      $q = $pdo->query("
        SELECT sr.id, s.date, s.start_time, s.end_time,
               e1.name AS requested_by, e2.name AS requested_to
        FROM swap_requests sr
        JOIN shifts s ON s.id = sr.shift_id
        JOIN employees e1 ON e1.id = sr.requested_by
        JOIN employees e2 ON e2.id = sr.requested_to
        WHERE sr.status = 'pending'
        ORDER BY sr.id DESC
      ");
    }
    $pending = $q->fetchAll(PDO::FETCH_ASSOC);
  } catch (Throwable $e) {
    $errors[] = "Erro ao carregar solicitações de troca: " . $e->getMessage();
  }
}
?>
<!doctype html>
<html lang="pt-br">
<head>
  <meta charset="utf-8">
  <title>Escalas</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="p-3">
<div class="container">
  <h1 class="mb-3">Próximos turnos</h1>

  <?php foreach ($errors as $e): ?>
    <div class="alert alert-danger"><?=$e?></div>
  <?php endforeach; ?>

  <div class="card mb-4">
    <div class="table-responsive">
      <table class="table table-striped table-hover mb-0">
        <thead>
          <tr>
            <th>Data</th>
            <th>Início</th>
            <th>Fim</th>
            <th>Funcionário</th>
            <?php if ($role !== 'manager'): ?><th>Loja</th><?php endif; ?>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($shifts as $s): ?>
            <tr>
              <td><?=htmlspecialchars($s['date'])?></td>
              <td><?=htmlspecialchars(substr($s['start_time'],0,5))?></td>
              <td><?=htmlspecialchars(substr($s['end_time'],0,5))?></td>
              <td><?=htmlspecialchars($s['employee'])?></td>
              <?php if ($role !== 'manager'): ?>
                <td><?=htmlspecialchars($s['store_name'] ?? '-')?></td>
              <?php endif; ?>
            </tr>
          <?php endforeach; ?>
          <?php if (empty($shifts)): ?>
            <tr><td colspan="5" class="text-muted">Sem turnos futuros.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <?php if (in_array($role, ['admin','manager'], true)): ?>
    <h2 class="h4">Solicitações de troca pendentes</h2>
    <div class="card">
      <div class="table-responsive">
        <table class="table table-sm align-middle mb-0">
          <thead>
            <tr>
              <th>#</th>
              <th>Data</th>
              <th>Janela</th>
              <th>De</th>
              <th>Para</th>
              <th class="text-end">Ações</th>
            </tr>
          </thead>
          <tbody>
          <?php foreach ($pending as $p): ?>
            <tr>
              <td><?=$p['id']?></td>
              <td><?=htmlspecialchars($p['date'])?></td>
              <td><?=htmlspecialchars(substr($p['start_time'],0,5).'–'.substr($p['end_time'],0,5))?></td>
              <td><?=htmlspecialchars($p['requested_by'])?></td>
              <td><?=htmlspecialchars($p['requested_to'])?></td>
              <td class="text-end">
                <form action="swap_action.php" method="post" class="d-inline">
                  <input type="hidden" name="id" value="<?=$p['id']?>">
                  <input type="hidden" name="action" value="approve">
                  <button class="btn btn-success btn-sm">Aprovar</button>
                </form>
                <form action="swap_action.php" method="post" class="d-inline ms-1">
                  <input type="hidden" name="id" value="<?=$p['id']?>">
                  <input type="hidden" name="action" value="reject">
                  <button class="btn btn-outline-danger btn-sm">Rejeitar</button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
          <?php if (empty($pending)): ?>
            <tr><td colspan="6" class="text-muted">Nenhuma solicitação pendente.</td></tr>
          <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  <?php endif; ?>
</div>
</body>
</html>
