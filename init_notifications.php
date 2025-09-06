<?php
require_once 'config.php';
try { $pdo->exec("CREATE TABLE IF NOT EXISTS notifications (id INTEGER PRIMARY KEY AUTOINCREMENT,user_id INTEGER NOT NULL,message TEXT NOT NULL,type TEXT NOT NULL,status TEXT DEFAULT 'unread',created_at DATETIME DEFAULT CURRENT_TIMESTAMP,FOREIGN KEY (user_id) REFERENCES users(id))"); echo "Tabela 'notifications' pronta."; } catch (Exception $e) { echo "Erro: ".$e->getMessage(); }
