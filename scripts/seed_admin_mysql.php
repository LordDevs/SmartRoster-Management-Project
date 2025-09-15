<?php
require_once __DIR__ . '/set_mysql_env_example.php';
require_once __DIR__ . '/../config.php';

$username = 'admin';
$password = 'admin'; // troque depois!
$hash = password_hash($password, PASSWORD_DEFAULT);

$pdo->exec("INSERT INTO stores (name, code) VALUES ('Matriz', 'MAIN') ON DUPLICATE KEY UPDATE name=VALUES(name)");
$storeId = (int)$pdo->lastInsertId();

$stmt = $pdo->prepare("INSERT INTO users (username, password_hash, role, store_id)
                       VALUES (?, ?, 'admin', ?) 
                       ON DUPLICATE KEY UPDATE password_hash=VALUES(password_hash), role='admin', store_id=VALUES(store_id)");
$stmt->execute([$username, $hash, $storeId ?: 1]);

echo "Admin criado/atualizado com sucesso. Login: admin / admin";
