<?php
declare(strict_types=1);
require_once __DIR__ . '/config.php';
// Troque para requireAdmin() se tiver
if (!isset($_SESSION['user']) || !in_array($_SESSION['user']['role'] ?? '', ['admin'])) {
  http_response_code(403);
  die('Access denied.');
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$errors = [];
$success = null;

$name = '';
$location = '';

// Carregar se edição
if ($id > 0) {
  $st = $pdo->prepare("SELECT id, name, location FROM stores WHERE id = :id");
  $st->execute([':id'=>$id]);
  $row = $st->fetch(PDO::FETCH_ASSOC);
  if (!$row) {
    http_response_code(404);
    die('Loja não encontrada.');
  }
  $name = $row['name'];
  $location = $row['location'];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $name = trim($_POST['name'] ?? '');
  $location = trim($_POST['location'] ?? '');

  if ($name === '') $errors[] = "Informe o nome da loja.";

  if (!$errors) {
    try {
      if ($id > 0) {
        $up = $pdo->prepare("UPDATE stores SET name = :n, location = :l WHERE id = :id");
        $up->execute([':n'=>$name, ':l'=>$location, ':id'=>$id]);
        $success = "Loja atualizada.";
      } else {
        // code é UNIQUE; gere um código simples a partir do nome (ou peça no form)
        $code = strtoupper(preg_replace('/[^A-Z0-9]+/i', '', substr($name,0,12)));
        if ($code === '') $code = 'STORE' . mt_rand(100,999);

        $ins = $pdo->prepare("INSERT INTO stores (name, code, location) VALUES (:n, :c, :l)");
        $ins->execute([':n'=>$name, ':c'=>$code, ':l'=>$location]);
        $id = (int)$pdo->lastInsertId();
        $success = "Loja criada.";
      }
    } catch (Throwable $e) {
      $errors[] = "Erro ao salvar loja: " . $e->getMessage();
    }
  }
}
?>
<!doctype html>
<html lang="pt-br">
<head>
  <meta charset="utf-8">
  <title><?=$id ? 'Editar Loja' : 'Criar Loja'?></title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="p-3">
  <div class="container" style="max-width:720px">
    <h1 class="mb-3"><?=$id ? 'Editar Loja' : 'Criar Loja'?></h1>

    <?php if ($success): ?><div class="alert alert-success"><?=$success?></div><?php endif; ?>
    <?php foreach ($errors as $e): ?><div class="alert alert-danger"><?=$e?></div><?php endforeach; ?>

    <form method="post" class="card card-body">
      <div class="mb-3">
        <label class="form-label">Nome</label>
        <input type="text" name="name" class="form-control" value="<?=htmlspecialchars($name)?>" required>
      </div>
      <div class="mb-3">
        <label class="form-label">Local (Location)</label>
        <input type="text" name="location" class="form-control" value="<?=htmlspecialchars($location)?>">
      </div>

      <div class="d-flex gap-2">
        <button class="btn btn-primary">Salvar</button>
        <a href="lojas_listar.php" class="btn btn-outline-secondary">Voltar</a>
      </div>
    </form>
  </div>
</body>
</html>
