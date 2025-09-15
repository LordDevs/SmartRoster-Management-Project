<?php
// Script simples para criar/alterar tabelas necessárias para a Meta 3.
// Execute este script manualmente (por linha de comando) uma vez para
// inicializar as colunas e tabelas adicionais.

require_once 'config.php';

$sqls = [
    "ALTER TABLE funcionarios ADD COLUMN weekly_max_hours FLOAT DEFAULT 40.0",
    "ALTER TABLE funcionarios ADD COLUMN hourly_rate FLOAT DEFAULT 0.0",
    "CREATE TABLE IF NOT EXISTS employee_preferences (\n".
    "    id INTEGER PRIMARY KEY AUTO_INCREMENT,\n".
    "    funcionario_id INTEGER NOT NULL,\n".
    "    day_of_week INTEGER NOT NULL,\n".
    "    available_start TIME NULL,\n".
    "    available_end TIME NULL,\n".
    "    max_hours_per_day FLOAT NULL,\n".
    "    min_rest_hours FLOAT NULL,\n".
    "    UNIQUE KEY unique_employee_day (funcionario_id, day_of_week),\n".
    "    FOREIGN KEY (funcionario_id) REFERENCES funcionarios(id)\n".
    ") ENGINE=InnoDB"
];

foreach ($sqls as $sql) {
    try {
        $conn->query($sql);
    } catch (Exception $e) {
        // Ignora erros caso as colunas/tabela já existam
        // echo $e->getMessage();
    }
}

echo "Meta 3: colunas e tabela configuradas.";