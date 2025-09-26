<?php
// config.php — unified (SQLite <-> MySQL) + session/auth helpers
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'httponly' => true,
        'secure'   => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on',
        'samesite' => 'Lax',
    ]);
    session_start();
}

// ENV / Config
$DB_DRIVER = getenv('DB_DRIVER') ?: 'sqlite'; // 'sqlite' or 'mysql'
$DB_HOST   = getenv('DB_HOST')   ?: '127.0.0.1';
$DB_PORT   = getenv('DB_PORT')   ?: '3306';
$DB_NAME   = getenv('DB_NAME')   ?: 'escala_hillbillys';
$DB_USER   = getenv('DB_USER')   ?: 'root';
$DB_PASS   = getenv('DB_PASS')   ?: '';
$SQLITE_PATH = getenv('SQLITE_PATH') ?: __DIR__ . '/db.sqlite';

try {
    if ($DB_DRIVER === 'mysql') {
        $dsn = "mysql:host={$DB_HOST};port={$DB_PORT};dbname={$DB_NAME};charset=utf8mb4";
        $pdo = new PDO($dsn, $DB_USER, $DB_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
    } else {
        $dsn = "sqlite:" . $SQLITE_PATH;
        $pdo = new PDO($dsn, null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $pdo->exec('PRAGMA foreign_keys = ON;');
    }
} catch (Throwable $e) {
    http_response_code(500);
    die("Database connection error: " . htmlspecialchars($e->getMessage()));
}

function isLoggedIn(): bool {
    return isset($_SESSION['user']['id']) && is_numeric($_SESSION['user']['id']);
}

function requireLogin(): void {
    if (empty($_SESSION['user'])) {
        http_response_code(403);
        die('Access denied.');
    }
}

function currentUser(): ?array {
    if (!isLoggedIn()) return null;
    global $pdo;
    static $cached = null;
    if ($cached !== null) return $cached;
    $stmt = $pdo->prepare('SELECT * FROM users WHERE id = ?');
    $stmt->execute([$_SESSION['user']['id']]);
    $cached = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    return $cached;
}

function requireRole(array $roles): void {
    $u = currentUser();
    $role = $u['role'] ?? null;
    if (!$role || !in_array($role, $roles, true)) {
        http_response_code(403);
        die('Acesso negado.');
    }
}
// --- Compat Shims (código legado) ---
function requireAdmin(): void {
    requireLogin();
    if (($_SESSION['user']['role'] ?? '') !== 'admin') {
        http_response_code(403);
        die('Access denied.');
    }
}
function requireManager(): void {
    // Ensure user is logged in and has manager (or admin) role
    requireLogin();
    requireRole(['manager','admin']);
}
function requireEmployee(): void {
    requireLogin();
    if (($_SESSION['user']['role'] ?? '') !== 'employee') {
        http_response_code(403);
        die('Access denied.');
    }
}
/**
 * Em versões antigas, "privileged" normalmente significava "admin ou manager".
 * Ajuste se o seu conceito for diferente.
 */
function requirePrivileged(): void {
    requireLogin();
    if (!in_array(($_SESSION['user']['role'] ?? ''), ['admin','manager'], true)) {
        http_response_code(403);
        die('Access denied.');
    }
}

/** Helpers legados comuns */
function isAdmin(): bool {
    $u = currentUser();
    return ($u['role'] ?? null) === 'admin';
}
function isManager(): bool {
    $u = currentUser();
    return ($u['role'] ?? null) === 'manager';
}
function userStoreId(): ?int {
    $u = currentUser();
    return isset($u['store_id']) ? (int)$u['store_id'] : null;
}

function csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_check(string $token): bool {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}