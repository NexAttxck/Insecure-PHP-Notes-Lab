# Campus Notes: repaired version

Security-review lab project (Integrative Programming and Technologies). Start from a deliberately vulnerable PHP/MySQL app and fix 11 issues. Run only locally with synthetic data. Never expose it to the internet.

## Files

- `index.php`: web controller (register, login, logout, note create/update/delete). All state changes are POST + CSRF token.
- `api.php`: JSON API (GET own notes, POST update own note). Session auth, CSRF header, same-origin only.
- `lib.php`: shared helpers (PDO, escaping `h()`, sessions, CSRF, validation, `own_note()` ownership check, error handler).
- `config.php`: settings from environment variables. No credentials in code.
- `views/page.php`: HTML template; every dynamic value is escaped.
- `schema.sql`: tables and two synthetic users (bcrypt hashes).
- `tests/smoke.php`: two-user security checks against the running server.

## Local setup

1. PHP 8 with `pdo_mysql`, and MySQL/MariaDB. (Tested: PHP 8.2.12, MariaDB 10.4.32 from XAMPP.)
2. As a local admin, import `schema.sql` into a fresh database.
3. Create a limited local account: `GRANT SELECT, INSERT, UPDATE, DELETE ON notes_lab.* TO 'your_local_user'@'localhost'`.
4. Set `LAB_DB_USER` and `LAB_DB_PASSWORD` in your shell (PowerShell: `$env:LAB_DB_USER = '...'`). The app refuses to start without them. Do not commit them.
5. From this folder: `php -S 127.0.0.1:8000`, then open `http://127.0.0.1:8000`.
6. Synthetic accounts (throwaway): `alex@example.test` / `LabOnly-Alex-123`, `sam@example.test` / `LabOnly-Sam-456`.
7. Run the checks with the server running: `php tests/smoke.php`.

Optional: `LAB_DB_HOST`, `LAB_DB_NAME`, `LAB_ALLOWED_ORIGINS` (comma-separated, default `http://127.0.0.1:8000,http://localhost:8000`).

## Limits

Local HTTP only: the session cookie `Secure` flag turns on automatically under HTTPS but was not tested here. No rate limiting, MFA, absolute session expiry or password reset. Production needs HTTPS and a real deployment review.
