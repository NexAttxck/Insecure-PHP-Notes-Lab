<?php
declare(strict_types=1);

// Credentials come from the environment only. No fallback to root/empty password.
$user = getenv('LAB_DB_USER');
$pass = getenv('LAB_DB_PASSWORD');
if ($user === false || $user === '' || $pass === false || $pass === '') {
    throw new RuntimeException('Database credentials unavailable.');
}

define('DB_DSN', 'mysql:host=' . (getenv('LAB_DB_HOST') ?: '127.0.0.1') . ';dbname=' . (getenv('LAB_DB_NAME') ?: 'notes_lab') . ';charset=utf8mb4');
define('DB_USER', $user);
define('DB_PASS', $pass);

// Origins allowed to call the API with the session cookie (same-origin app only).
define('ALLOWED_ORIGINS', explode(',', getenv('LAB_ALLOWED_ORIGINS') ?: 'http://127.0.0.1:8000,http://localhost:8000'));

define('SESSION_IDLE', 1800); // seconds
// Valid bcrypt hash of a throwaway string; checked when the email is unknown so timing stays similar.
define('DUMMY_HASH', '$2y$10$Jf/Wp/wP921I6Kg9JOl3xu9z1rAoDQ9oE.gNHKX/nXhp.xqGj0XUm');
