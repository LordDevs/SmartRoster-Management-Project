
<?php
require_once __DIR__ . '/set_mysql_env_example.php';
require_once __DIR__ . '/../config.php';

header('Content-Type: text/plain; charset=utf-8');

echo "=== VERIFY ENV ===\n";
echo "Driver: " . getenv('DB_DRIVER') . "\n";
echo "Host  : " . getenv('DB_HOST') . "\n";
echo "Port  : " . getenv('DB_PORT') . "\n";
echo "DB    : " . getenv('DB_NAME') . "\n";
echo "SQLite: " . getenv('SQLITE_PATH') . "\n\n";


try {
    global $pdo;
    $engine = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
    echo "PDO driver active: {$engine}\n";
    if ($engine === 'mysql') {
        $stmt = $pdo->query("SELECT VERSION() AS v");
        $row = $stmt->fetch();
        echo "MySQL version: " . ($row['v'] ?? 'n/a') . "\n";
    } else {
        $stmt = $pdo->query("SELECT sqlite_version() AS v");
        $row = $stmt->fetch();
        echo "SQLite version: " . ($row['v'] ?? 'n/a') . "\n";
    }
    // quick table check
    $tables = ['users','stores','employees','shifts','time_entries','swap_requests','notifications','employee_preferences'];
    foreach ($tables as $t) {
        try {
            $pdo->query("SELECT 1 FROM {$t} LIMIT 1");
            echo "[OK] table '{$t}' accessible\n";
        } catch (Throwable $e) {
            echo "[!] table '{$t}' missing or inaccessible: " . $e->getMessage() . "\n";
        }
    }
} catch (Throwable $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    http_response_code(500);
    exit;
}
echo "\nAll good.\n";
