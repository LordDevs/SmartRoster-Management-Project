<?php
// funcionarios_gerenciar.php – redireciona para a listagem de funcionários.
require_once 'config.php';
requireLogin();
header('Location: usuarios_listar.php');
exit();