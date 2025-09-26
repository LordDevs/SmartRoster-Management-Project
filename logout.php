<?php
declare(strict_types=1);
require_once __DIR__ . '/config.php';

// limpa a sessão com segurança
$_SESSION = [];
if (ini_get('session.use_cookies')) {
  $params = session_get_cookie_params();
  setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
}
session_destroy();

// opcional: iniciar nova sessão pra setar um flash de mensagem
session_start();
$_SESSION['login_error'] = 'Você saiu da sessão.';
header('Location: index.php');
exit();
