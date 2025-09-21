<?php
// funcionarios_gerenciar.php – redirect to the employee listing page.
require_once 'config.php';
requireLogin();
header('Location: usuarios_listar.php');
exit();
