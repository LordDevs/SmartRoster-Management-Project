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
// Notifications (optional)
$MAIL_FROM       = getenv('MAIL_FROM') ?: 'no-reply@localhost';
$MAIL_FROM_NAME  = getenv('MAIL_FROM_NAME') ?: 'Escala Hillbillys';
$SLACK_WEBHOOK   = getenv('SLACK_WEBHOOK_URL') ?: '';

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
    return isset($_SESSION['user_id']) && is_numeric($_SESSION['user_id']);
}

function requireLogin(): void {
    if (!isLoggedIn()) {
        header('Location: index.php?auth=required');
        exit();
    }
}

function currentUser(): ?array {
    if (!isLoggedIn()) return null;
    global $pdo;
    static $cached = null;
    if ($cached !== null) return $cached;
    $stmt = $pdo->prepare('SELECT * FROM users WHERE id = ?');
    $stmt->execute([$_SESSION['user_id']]);
    $cached = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    return $cached;
}

function requireRole(array $roles): void {
    $u = currentUser();
    $role = $u['role'] ?? null;
    if (!$role || !in_array($role, $roles, true)) {
        http_response_code(403);
        die('Access denied.');
    }
}
// --- Compat Shims (legacy code) ---
function requireAdmin(): void {
    // Ensure user is logged in and has admin role
    requireLogin();
    requireRole(['admin']);
}
function requireManager(): void {
    // Ensure user is logged in and has manager (or admin) role
    requireLogin();
    requireRole(['manager','admin']);
}
function requireEmployee(): void {
    // Ensure user is logged in and has employee role
    requireLogin();
    requireRole(['employee']);
}
/**
 * In older versions, "privileged" usually meant "admin or manager".
 * Adjust if your definition differs.
 */
function requirePrivileged(): void {
    // Ensure user is logged in and has admin or manager role
    requireLogin();
    requireRole(['admin','manager']);
}

/** Common legacy helpers */
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
