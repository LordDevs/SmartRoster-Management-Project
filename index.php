<?php
declare(strict_types=1);
require_once __DIR__ . '/config.php';

// já logado? manda pro destino certo
if (!empty($_SESSION['user'])) {
  $role = $_SESSION['user']['role'] ?? '';
  if ($role === 'employee') {
    header('Location: portal.php'); exit;
  } elseif ($role === 'manager') {
    header('Location: manager_dashboard.php'); exit;
  } else {
    header('Location: dashboard.php'); exit; // admin
  }
}

// simples token anti-CSRF
if (empty($_SESSION['csrf'])) {
  $_SESSION['csrf'] = bin2hex(random_bytes(16));
}
$err = $_SESSION['login_error'] ?? null;
unset($_SESSION['login_error']);
?>
<!doctype html>
<html lang="pt-br">
<head>
  <meta charset="utf-8">
  <title>SmartRoster – Login</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light d-flex align-items-center" style="min-height:100vh">
  <div class="container">
    <div class="row justify-content-center">
      <div class="col-sm-10 col-md-6 col-lg-4">
        <div class="card shadow-sm">
          <div class="card-body p-4">
            <h1 class="h4 mb-3 text-center">SmartRoster</h1>
            <?php if ($err): ?>
              <div class="alert alert-danger py-2"><?=htmlspecialchars($err)?></div>
            <?php endif; ?>
            <form method="post" action="authenticate.php" novalidate>
              <input type="hidden" name="csrf" value="<?=$_SESSION['csrf']?>">
              <div class="mb-3">
                <label class="form-label">Usuário</label>
                <input type="text" name="username" class="form-control" autofocus required>
              </div>
              <div class="mb-3">
                <label class="form-label">Senha</label>
                <div class="input-group">
                  <input type="password" name="password" id="pwd" class="form-control" required>
                  <button class="btn btn-outline-secondary" type="button" onclick="togglePwd()">👁</button>
                </div>
              </div>
              <button class="btn btn-primary w-100">Entrar</button>
            </form>
            <div class="text-center mt-3">
              <small class="text-muted">© <?=date('Y')?> SmartRosterManagementProject</small>
            </div>
          </div>
        </div>
        <?php if (!empty($_GET['seed'])): // opcional, só pra debug ?>
          <pre class="mt-3 small text-muted border rounded p-2">Dica: crie um admin no banco
INSERT INTO users (username, password, role) VALUES ('admin', '$2y$10$hashAqui...', 'admin');
          </pre>
        <?php endif; ?>
      </div>
    </div>
  </div>
<script>
function togglePwd(){
  const i = document.getElementById('pwd');
  i.type = (i.type === 'password') ? 'text' : 'password';
}
</script>
</body>
</html>
