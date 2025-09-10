<?php
// schema_update_meta3.php
// This script adds the max_weekly_hours column to the employees table
// if it does not already exist. It should be run once from a browser or
// the command line to update your database schema for Meta 3.

require_once 'config.php';

// Only allow admins to run this script
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    die('Acesso negado. Apenas administradores podem executar a atualização do esquema.');
}

try {
    // Check if column exists
    $result = $pdo->query("PRAGMA table_info(employees)")->fetchAll(PDO::FETCH_ASSOC);
    $columns = array_column($result, 'name');
    if (!in_array('max_weekly_hours', $columns)) {
        // Add max_weekly_hours column with default value 40 hours
        $pdo->exec("ALTER TABLE employees ADD COLUMN max_weekly_hours REAL DEFAULT 40.0");
        echo 'Coluna max_weekly_hours adicionada com sucesso.';
    } else {
        echo 'A coluna max_weekly_hours já existe. Nenhuma alteração necessária.';
    }
} catch (Exception $e) {
    echo 'Erro ao atualizar o esquema: ' . $e->getMessage();
}
