<?php
// Smoke test: two synthetic users, separate cookie jars. Run: php tests/smoke.php [base_url]
// Needs the server running and LAB_DB_USER / LAB_DB_PASSWORD set (used only to inspect the DB).
$base = $argv[1] ?? 'http://127.0.0.1:8000';
$fails = 0;

function req(string $jar, string $method, string $path, array $o = []): array
{
    global $base;
    $ch = curl_init($base . $path);
    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST => $method, CURLOPT_RETURNTRANSFER => true, CURLOPT_HEADER => true,
        CURLOPT_COOKIEJAR => $jar, CURLOPT_COOKIEFILE => $jar, CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_HTTPHEADER => $o['headers'] ?? [],
    ]);
    if (isset($o['form'])) curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($o['form']));
    if (isset($o['raw'])) curl_setopt($ch, CURLOPT_POSTFIELDS, $o['raw']);
    $r = curl_exec($ch);
    $hs = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $out = ['code' => curl_getinfo($ch, CURLINFO_HTTP_CODE), 'head' => substr($r, 0, $hs), 'body' => substr($r, $hs)];
    curl_close($ch);
    return $out;
}
function token(string $jar): string
{
    preg_match('/name="csrf-token" content="([0-9a-f]+)"/', req($jar, 'GET', '/')['body'], $m);
    return $m[1] ?? '';
}
function check(string $name, bool $ok): void
{
    global $fails;
    echo ($ok ? 'PASS' : 'FAIL') . "  $name\n";
    $fails += $ok ? 0 : 1;
}
function post(string $jar, array $form, ?string $tok = null): array
{
    return req($jar, 'POST', '/', ['form' => $form + ['csrf' => $tok ?? token($jar)]]);
}
function api(string $jar, string $method, string $path, $body = null, array $h = [], ?string $tok = null): array
{
    $h[] = 'Content-Type: application/json';
    $h[] = 'X-CSRF-Token: ' . ($tok ?? token($jar));
    return req($jar, $method, $path, ['headers' => $h, 'raw' => is_string($body) ? $body : json_encode($body)]);
}

$A = tempnam(sys_get_temp_dir(), 'ja'); $S = tempnam(sys_get_temp_dir(), 'js'); $N = tempnam(sys_get_temp_dir(), 'jn');
$db = new PDO('mysql:host=127.0.0.1;dbname=notes_lab;charset=utf8mb4', getenv('LAB_DB_USER'), getenv('LAB_DB_PASSWORD'), [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

// Login: invalid, SQL-injection string, valid; session id rotates.
post($A, ['action' => 'login', 'email' => 'alex@example.test', 'password' => 'wrong-password']);
check('invalid login: no access', api($A, 'GET', '/api.php')['code'] === 401);
post($A, ['action' => 'login', 'email' => "' OR '1'='1' -- ", 'password' => 'x']);
check('SQLi login string: no access', api($A, 'GET', '/api.php')['code'] === 401);
$before = file_get_contents($A);
post($A, ['action' => 'login', 'email' => 'alex@example.test', 'password' => 'LabOnly-Alex-123']);
post($S, ['action' => 'login', 'email' => 'sam@example.test', 'password' => 'LabOnly-Sam-456']);
check('valid login (alex)', api($A, 'GET', '/api.php')['code'] === 200);
check('session id changed after login', preg_replace('/\s+/', '', $before) !== preg_replace('/\s+/', '', file_get_contents($A)));

// API: no session, other user's id, untrusted origin, malformed JSON, validation.
check('API no session -> 401', req($N, 'GET', '/api.php')['code'] === 401);
$list = json_decode(api($A, 'GET', '/api.php')['body'], true);
check('API list returns only own notes', count($list) >= 1 && count(array_unique(array_column($list, 'user_id'))) === 1 && $list[0]['user_id'] == 1);
check('API list with other user_id -> 403', api($A, 'GET', '/api.php?user_id=2')['code'] === 403);
check('API update of other user\'s note -> 404', api($A, 'POST', '/api.php', ['id' => 3, 'title' => 'pwned', 'body' => 'pwned'])['code'] === 404);
check('other user\'s note unchanged in DB', $db->query('SELECT title FROM notes WHERE id=3')->fetchColumn() !== 'pwned');
check('API untrusted Origin -> 403', api($A, 'POST', '/api.php', ['id' => 1, 'title' => 't', 'body' => 'b'], ['Origin: http://evil.example'])['code'] === 403);
check('API untrusted Origin GET -> 403, no CORS header', ($r = req($A, 'GET', '/api.php', ['headers' => ['Origin: http://evil.example']]))['code'] === 403 && stripos($r['head'], 'Access-Control-Allow') === false);
check('API missing CSRF -> 403', req($A, 'POST', '/api.php', ['headers' => ['Content-Type: application/json'], 'raw' => '{"id":1,"title":"t","body":"b"}'])['code'] === 403);
check('API malformed JSON -> 400', api($A, 'POST', '/api.php', '{bad json')['code'] === 400);
check('API missing fields -> 400', api($A, 'POST', '/api.php', ['id' => 1])['code'] === 400);
check('API id=true (bool) -> 400', api($A, 'POST', '/api.php', '{"id":true,"title":"t","body":"b"}')['code'] === 400);
check('API wrong content type -> 415', req($A, 'POST', '/api.php', ['headers' => ['X-CSRF-Token: ' . token($A)], 'raw' => 'a=b'])['code'] === 415);
check('API valid update of own note -> 200', api($A, 'POST', '/api.php', ['id' => 1, 'title' => 'Alex: course reminder', 'body' => 'Review the security reading before class.'])['code'] === 200);

// Web: ownership, CSRF, methods, XSS, owner/role spoofing.
check('edit other user\'s note -> 404', req($A, 'GET', '/?edit=3')['code'] === 404);
check('page lists only own notes', strpos(req($A, 'GET', '/')['body'], 'Sam: study plan') === false);
check('delete other user\'s note -> 404', post($A, ['action' => 'delete', 'id' => 3])['code'] === 404);
check('other user\'s note still exists', (int) $db->query('SELECT COUNT(*) FROM notes WHERE id=3')->fetchColumn() === 1);
check('GET ?delete=1 does nothing', req($A, 'GET', '/?delete=1')['code'] === 200 && (int) $db->query('SELECT COUNT(*) FROM notes WHERE id=1')->fetchColumn() === 1);
check('POST without CSRF token -> 403', req($A, 'POST', '/', ['form' => ['action' => 'delete', 'id' => 1]])['code'] === 403);
check('POST with wrong CSRF token -> 403', post($A, ['action' => 'delete', 'id' => 1], 'deadbeef')['code'] === 403);
post($A, ['action' => 'save', 'user_id' => 2, 'title' => '<script>alert(1)</script>', 'body' => '<img src=x onerror=alert(1)>']);
$page = req($A, 'GET', '/')['body'];
check('HTML-like note renders as text', strpos($page, '&lt;script&gt;alert(1)&lt;/script&gt;') !== false && strpos($page, '<script>alert(1)') === false && strpos($page, '<img src=x') === false);
check('owner spoof ignored (note owned by alex)', (int) $db->query("SELECT user_id FROM notes WHERE title='<script>alert(1)</script>' ORDER BY id DESC LIMIT 1")->fetchColumn() === 1);
$email = 'role-test-' . bin2hex(random_bytes(3)) . '@example.test';
post($N, ['action' => 'register', 'email' => $email, 'password' => 'LabOnly-Role-789', 'role' => 'admin']);
$st = $db->prepare('SELECT role, password_hash FROM users WHERE email=?'); $st->execute([$email]); $u = $st->fetch(PDO::FETCH_ASSOC);
check('role spoof ignored (role=student)', ($u['role'] ?? '') === 'student');
check('password stored as bcrypt, not MD5', str_starts_with($u['password_hash'] ?? '', '$2y$') && $u['password_hash'] !== md5('LabOnly-Role-789'));
check('security headers present', stripos(req($A, 'GET', '/')['head'], 'Content-Security-Policy') !== false);

// Logout ends access (and the old cookie stays dead).
$old = file_get_contents($A);
post($A, ['action' => 'logout']);
check('after logout API -> 401', api($A, 'GET', '/api.php')['code'] === 401);
file_put_contents($S . '.old', $old);
check('old session cookie rejected after logout', req($S . '.old', 'GET', '/api.php')['code'] === 401);

// Cleanup test rows.
$db->exec("DELETE FROM notes WHERE title='<script>alert(1)</script>'");
$db->prepare('DELETE FROM users WHERE email=?')->execute([$email]);
echo $fails === 0 ? "\nALL PASSED\n" : "\n$fails FAILED\n";
exit($fails === 0 ? 0 : 1);
