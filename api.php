<?php
declare(strict_types=1);
define('IS_API', true);
require __DIR__ . '/lib.php';
security_headers();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function out(int $code, array $body): never
{
    http_response_code($code);
    echo json_encode($body);
    exit;
}

// No CORS headers are sent, so browsers keep other origins from reading responses.
// A request that names an untrusted Origin is rejected outright (blocks cross-site writes too).
$origin = $_SERVER['HTTP_ORIGIN'] ?? null;
if ($origin !== null && !in_array($origin, ALLOWED_ORIGINS, true)) {
    out(403, ['error' => 'Forbidden origin']);
}

start_session();
$uid = $_SESSION['user_id'] ?? null;
if ($uid === null) {
    out(401, ['error' => 'Authentication required']);
}

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    // The owner is the session user. A user_id for someone else is refused, not honored.
    if (isset($_GET['user_id']) && int_id($_GET['user_id']) !== $uid) {
        out(403, ['error' => 'Forbidden']);
    }
    $s = db()->prepare('SELECT id, user_id, title, body FROM notes WHERE user_id = ? ORDER BY id');
    $s->execute([$uid]);
    out(200, $s->fetchAll());
}

if ($method === 'POST') {
    if (stripos($_SERVER['CONTENT_TYPE'] ?? '', 'application/json') !== 0) {
        out(415, ['error' => 'Content-Type must be application/json']);
    }
    if (!csrf_ok($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null)) {
        out(403, ['error' => 'Invalid CSRF token']);
    }
    $raw = file_get_contents('php://input', false, null, 0, 65537);
    if ($raw === false || strlen($raw) > 65536) {
        out(413, ['error' => 'Body too large']);
    }
    try {
        $data = json_decode($raw, true, 8, JSON_THROW_ON_ERROR);
    } catch (JsonException) {
        out(400, ['error' => 'Malformed JSON']);
    }
    $id = is_array($data) ? int_id($data['id'] ?? null) : null;
    $title = is_array($data) ? str_in($data['title'] ?? null, 1, 120) : null;
    $body = is_array($data) ? str_in($data['body'] ?? null, 1, 5000) : null;
    if ($id === null || $title === null || $body === null) {
        out(400, ['error' => 'Required: id (int), title (1-120 chars), body (1-5000 chars)']);
    }
    if (own_note($id, $uid) === null) {
        out(404, ['error' => 'Not found']);
    }
    db()->prepare('UPDATE notes SET title = ?, body = ? WHERE id = ? AND user_id = ?')->execute([$title, $body, $id, $uid]);
    out(200, ['updated' => 1]);
}

header('Allow: GET, POST');
out(405, ['error' => 'Method not supported']);
