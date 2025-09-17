

<?php
// index.php – login page for the Escala Hillbillys system
require_once __DIR__ . '/scripts/set_mysql_env_example.php';
require_once __DIR__ . '/config.php';

// If the user is already logged in, redirect to the dashboard.
if (isLoggedIn()) {
    header('Location: dashboard.php');
    exit();
}

// If there is a login error stored in the session, capture and clear it.
$loginError = $_SESSION['login_error'] ?? null;
unset($_SESSION['login_error']);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>Escala Hillbillys – Login</title>
    <!-- Bootstrap CSS from CDN for responsive layout -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="style.css">
    <style>
        body {
            background: linear-gradient(135deg, #6a11cb 0%, #2575fc 100%);
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .login-container {
            background: #ffffff;
            padding: 2rem;
            border-radius: 8px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            width: 100%;
            max-width: 400px;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <h2 class="mb-3 text-center">Escala Hillbillys</h2>
        <?php if ($loginError): ?>
            <div class="alert alert-danger">
                <?php echo htmlspecialchars($loginError); ?>
            </div>
        <?php endif; ?>
        <form action="authenticate.php" method="post">
            <div class="mb-3">
                <label for="username" class="form-label">Usuário</label>
                <input type="text" class="form-control" id="username" name="username" required>
            </div>
            <div class="mb-3">
                <label for="password" class="form-label">Senha</label>
                <input type="password" class="form-control" id="password" name="password" required>
            </div>
            <button type="submit" class="btn btn-primary w-100">Entrar</button>
        </form>
    </div>
    <!-- Bootstrap JS (optional) -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>