<?php
declare(strict_types=1);
require __DIR__ . '/lib.php';
security_headers();
start_session();

function go(string $flash = ''): never
{
    if ($flash !== '') {
        $_SESSION['flash'] = $flash;
    }
    header('Location: /', true, 303);
    exit;
}

function stop(int $code, string $msg): never
{
    http_response_code($code);
    exit($msg);
}

$uid = $_SESSION['user_id'] ?? null;

// All state changes are POST + CSRF token. GET only renders.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_ok($_POST['csrf'] ?? null)) {
        stop(403, 'Invalid request.');
    }
    $action = $_POST['action'] ?? '';

    if ($action === 'register') {
        $email = str_in($_POST['email'] ?? null, 3, 254);
        $pw = $_POST['password'] ?? null;
        if ($email === null || !filter_var($email, FILTER_VALIDATE_EMAIL) || !is_string($pw) || strlen($pw) < 8 || strlen($pw) > 72) {
            go('Enter a valid email and a password of 8 to 72 characters.');
        }
        try {
            // Role is assigned by the server, never by the client.
            db()->prepare('INSERT INTO users (email, password_hash, role) VALUES (?, ?, ?)')
                ->execute([$email, password_hash($pw, PASSWORD_DEFAULT), 'student']);
        } catch (PDOException $e) {
            if ($e->getCode() !== '23000') { // duplicate email
                throw $e;
            }
            go('Could not register with that email.');
        }
        go('Account created. Please sign in.');
    }

    if ($action === 'login') {
        $email = str_in($_POST['email'] ?? null, 3, 254);
        $pw = $_POST['password'] ?? null;
        $user = false;
        if ($email !== null && is_string($pw)) {
            $s = db()->prepare('SELECT id, password_hash, role FROM users WHERE email = ?');
            $s->execute([$email]);
            $user = $s->fetch();
        }
        $ok = password_verify(is_string($pw) ? $pw : '', $user ? $user['password_hash'] : DUMMY_HASH);
        if ($user && $ok) {
            session_regenerate_id(true);
            unset($_SESSION['csrf']);
            $_SESSION['user_id'] = (int) $user['id'];
            $_SESSION['role'] = $user['role'];
            go();
        }
        go('Invalid email or password.');
    }

    if ($action === 'logout') {
        end_session();
        header('Location: /', true, 303);
        exit;
    }

    // Everything below needs a signed-in user; the owner is always the session user.
    if ($uid === null) {
        stop(401, 'Authentication required.');
    }

    if ($action === 'save') {
        $title = str_in($_POST['title'] ?? null, 1, 120);
        $body = str_in($_POST['body'] ?? null, 1, 5000);
        if ($title === null || $body === null) {
            go('Title (1-120) and body (1-5000) are required.');
        }
        db()->prepare('INSERT INTO notes (user_id, title, body) VALUES (?, ?, ?)')->execute([$uid, $title, $body]);
        go();
    }

    if ($action === 'update') {
        $id = int_id($_POST['id'] ?? null);
        if ($id === null || own_note($id, $uid) === null) {
            stop(404, 'Not found.');
        }
        $title = str_in($_POST['title'] ?? null, 1, 120);
        $body = str_in($_POST['body'] ?? null, 1, 5000);
        if ($title === null || $body === null) {
            go('Title (1-120) and body (1-5000) are required.');
        }
        db()->prepare('UPDATE notes SET title = ?, body = ? WHERE id = ? AND user_id = ?')->execute([$title, $body, $id, $uid]);
        go();
    }

    if ($action === 'delete') {
        $id = int_id($_POST['id'] ?? null);
        if ($id === null || own_note($id, $uid) === null) {
            stop(404, 'Not found.');
        }
        db()->prepare('DELETE FROM notes WHERE id = ? AND user_id = ?')->execute([$id, $uid]);
        go();
    }

    stop(400, 'Invalid request.');
}

$message = $_SESSION['flash'] ?? '';
unset($_SESSION['flash']);
$notes = [];
$editing = null;
if ($uid !== null) {
    $s = db()->prepare('SELECT id, user_id, title, body FROM notes WHERE user_id = ? ORDER BY id DESC');
    $s->execute([$uid]);
    $notes = $s->fetchAll();
    if (isset($_GET['edit'])) {
        $id = int_id($_GET['edit']);
        $editing = $id === null ? null : own_note($id, $uid);
        if ($editing === null) {
            stop(404, 'Not found.');
        }
    }
}
$csrf = csrf_token();
$role = $_SESSION['role'] ?? '';
require __DIR__ . '/views/page.php';
