<?php
declare(strict_types=1);

// Errors: never shown to visitors; log a sanitized line (no message, no trace, no request data).
ini_set('display_errors', '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);
set_exception_handler(function (Throwable $e): void {
    $ref = bin2hex(random_bytes(4));
    error_log("[$ref] " . get_class($e) . ' code=' . $e->getCode() . ' at ' . basename($e->getFile()) . ':' . $e->getLine());
    http_response_code(500);
    $msg = "Server error. Reference: $ref";
    if (defined('IS_API')) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['error' => $msg]);
    } else {
        header('Content-Type: text/plain; charset=utf-8');
        echo $msg;
    }
});

require __DIR__ . '/config.php';

function h(?string $s): string
{
    return htmlspecialchars((string) $s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function db(): PDO
{
    static $pdo = null;
    return $pdo ??= new PDO(DB_DSN, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
}

function security_headers(): void
{
    header("Content-Security-Policy: default-src 'none'; style-src 'unsafe-inline'; form-action 'self'; base-uri 'none'; frame-ancestors 'none'");
    header('X-Content-Type-Options: nosniff');
}

function start_session(): void
{
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    session_set_cookie_params([
        'httponly' => true,
        'samesite' => 'Lax',
        'secure' => !empty($_SERVER['HTTPS']), // ponytail: Secure flag needs HTTPS; local HTTP dev cannot set it
        'path' => '/',
    ]);
    session_start();
    if (isset($_SESSION['last_activity']) && time() - $_SESSION['last_activity'] > SESSION_IDLE) {
        end_session();
        session_start();
    }
    $_SESSION['last_activity'] = time();
}

function end_session(): void
{
    $_SESSION = [];
    $p = session_get_cookie_params();
    setcookie(session_name(), '', [
        'expires' => time() - 42000, 'path' => $p['path'], 'domain' => $p['domain'],
        'secure' => $p['secure'], 'httponly' => $p['httponly'], 'samesite' => $p['samesite'],
    ]);
    session_destroy();
}

function csrf_token(): string
{
    return $_SESSION['csrf'] ??= bin2hex(random_bytes(32));
}

function csrf_ok(mixed $t): bool
{
    return is_string($t) && hash_equals(csrf_token(), $t);
}

// Positive integer from a string or int; null otherwise (bool/array/float rejected).
function int_id(mixed $v): ?int
{
    if (!is_int($v) && !is_string($v)) {
        return null;
    }
    $i = filter_var($v, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    return $i === false ? null : $i;
}

// Trimmed string with length between $min and $max; null otherwise.
function str_in(mixed $v, int $min, int $max): ?string
{
    if (!is_string($v)) {
        return null;
    }
    $v = trim($v);
    $n = mb_strlen($v, 'UTF-8');
    return ($n >= $min && $n <= $max) ? $v : null;
}

// A note, only if it belongs to $uid. Every read/edit/delete goes through this.
function own_note(int $id, int $uid): ?array
{
    $s = db()->prepare('SELECT id, user_id, title, body FROM notes WHERE id = ? AND user_id = ?');
    $s->execute([$id, $uid]);
    return $s->fetch() ?: null;
}
