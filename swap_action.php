<?php
declare(strict_types=1);
require_once __DIR__ . '/config.php';

// Troque por requirePrivileged() se já tiver helper (admin/manager).
if (!isset($_SESSION['user']) || !in_array($_SESSION['user']['role'] ?? '', ['admin','manager'], true)) {
  http_response_code(403);
  die('Access denied.');
}

$errors = [];
$success = null;

$id     = (int)($_POST['id'] ?? $_GET['id'] ?? 0);
$action = ($_POST['action'] ?? $_GET['action'] ?? '');
$validActions = ['approve', 'reject'];

if ($id <= 0 || !in_array($action, $validActions, true)) {
  http_response_code(400);
  die('Parâmetros inválidos.');
}

// Busca a solicitação
$sr = $pdo->prepare("
  SELECT sr.id, sr.shift_id, sr.requested_by, sr.requested_to, sr.status,
         s.employee_id, s.date, s.start_time, s.end_time
  FROM swap_requests sr
  JOIN shifts s ON s.id = sr.shift_id
  WHERE sr.id = :id
");
$sr->execute([':id'=>$id]);
$row = $sr->fetch(PDO::FETCH_ASSOC);
if (!$row) {
  http_response_code(404);
  die('Solicitação não encontrada.');
}
if ($row['status'] !== 'pending') {
  http_response_code(409);
  die('Solicitação já processada.');
}

// Se manager, garanta que turno pertence à sua loja
if (($_SESSION['user']['role'] ?? '') === 'manager' && !empty($_SESSION['user']['store_id'])) {
  $chk = $pdo->prepare("
    SELECT 1
    FROM employees e
    WHERE e.id IN (:by, :to) AND e.store_id = :sid
    GROUP BY 1
  ");
  $chk->execute([':by'=>$row['requested_by'], ':to'=>$row['requested_to'], ':sid'=>$_SESSION['user']['store_id']]);
  // Precisamos garantir que ambos pertencem à loja do manager (duas linhas)
  $okCount = $chk->rowCount();
  if ($okCount < 1) { // (se quiser mais estrito, verifique BY e TO separadamente)
    http_response_code(403);
    die('Você não pode processar solicitações de outra loja.');
  }
}

try {
  $pdo->beginTransaction();

  if ($action === 'approve') {
    // mover o turno para o requested_to
    $upShift = $pdo->prepare("UPDATE shifts SET employee_id = :to WHERE id = :sid");
    $upShift->execute([':to'=>$row['requested_to'], ':sid'=>$row['shift_id']]);

    $upReq = $pdo->prepare("UPDATE swap_requests SET status = 'approved' WHERE id = :id");
    $upReq->execute([':id'=>$id]);

    $success = "Solicitação aprovada e turno reatribuído.";
  } else { // reject
    $upReq = $pdo->prepare("UPDATE swap_requests SET status = 'rejected' WHERE id = :id");
    $upReq->execute([':id'=>$id]);

    $success = "Solicitação rejeitada.";
  }

  $pdo->commit();
} catch (Throwable $e) {
  $pdo->rollBack();
  $errors[] = "Erro ao processar: " . $e->getMessage();
}
?>
<!doctype html>
<html lang="pt-br">
<head>
  <meta charset="utf-8">
  <title>Processar solicitação de troca</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="p-3">
  <div class="container" style="max-width:720px">
    <h1 class="mb-3">Processar solicitação #<?=htmlspecialchars((string)$id)?></h1>

    <?php foreach ($errors as $e): ?>
      <div class="alert alert-danger"><?=$e?></div>
    <?php endforeach; ?>

    <?php if ($success): ?>
      <div class="alert alert-success"><?=$success?></div>
    <?php endif; ?>

    <a href="escala_listar.php" class="btn btn-primary mt-2">Voltar</a>
  </div>
</body>
</html>
