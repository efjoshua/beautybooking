<?php
/**
 * Princess Beauty Salon — authentication & authorisation
 *
 * The prototype guarded the admin panel in JavaScript, which is a courtesy,
 * not a control: anyone could reach revenue.php by typing the URL. Every
 * guard here runs on the server before a byte of the page is sent.
 */

declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';

/** Start the session with sane cookie flags. Safe to call repeatedly. */
function auth_boot(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    /* Under CLI, or if a view has already emitted output, session_name() and
       session_start() both warn. Nothing useful can be done at that point, so
       skip quietly rather than filling the page with warnings. */
    if (PHP_SAPI === 'cli' || headers_sent()) {
        if (session_status() === PHP_SESSION_NONE && PHP_SAPI === 'cli') {
            $_SESSION = $_SESSION ?? [];
        }
        return;
    }
    session_name(SESSION_NAME);
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => BASE_URL ?: '/',
        'httponly' => true,                       // JS cannot read the cookie
        'samesite' => 'Lax',                      // blocks cross-site POSTs
        'secure'   => !empty($_SERVER['HTTPS']),  // becomes true under TLS
    ]);
    session_start();

    /* Idle timeout. */
    if (isset($_SESSION['last_seen']) &&
        (time() - $_SESSION['last_seen']) > SESSION_IDLE_MINUTES * 60) {
        auth_logout();
    }
    $_SESSION['last_seen'] = time();
}

/* ------------------------------------------------------------------ CSRF */

function csrf_token(): string
{
    auth_boot();
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">';
}

function csrf_check(?string $token = null): bool
{
    auth_boot();
    $token ??= $_POST['_csrf'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    return !empty($_SESSION['csrf']) && hash_equals($_SESSION['csrf'], (string) $token);
}

/** Reject a state-changing request that carries no valid token. */
function csrf_require(): void
{
    if (!csrf_check()) {
        /* 403, not 419: 419 is a framework-ism that Apache will not emit, so
           it surfaced to the browser as a misleading 500 "server error". */
        if (str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'json') ||
            str_contains($_SERVER['REQUEST_URI'] ?? '', '/api/')) {
            json_error('Invalid or expired security token. Reload and try again.', 403);
        }
        http_response_code(403);
        exit('Invalid or expired security token. Please reload the page.');
    }
}

/* ------------------------------------------------------------ throttling */

/** The requesting client. Recording and checking must agree, or the lockout
    silently never trips. */
function client_ip(): string
{
    return (string) ($_SERVER['REMOTE_ADDR'] ?? 'cli');
}

/**
 * Throttling is keyed on (email + IP), not email alone.
 *
 * Locking purely by email means anyone who knows the owner's address can lock
 * her out of her own salon from anywhere, simply by failing five times — a
 * denial of service with no access required. Our own test suite tripped it.
 *
 * So: five failures from one IP blocks that IP for this account, while a much
 * higher count across all IPs still blocks a genuinely distributed attack.
 */
function auth_is_locked(string $email): bool
{
    $since = date('Y-m-d H:i:s', time() - LOGIN_LOCKOUT_MINUTES * 60);
    $ip = client_ip();

    $fromThisIp = (int) db_val(
        'SELECT COUNT(*) FROM login_attempts
          WHERE identifier = ? AND ip = ? AND successful = 0 AND attempted_at > ?',
        [$email, $ip, $since]
    );
    if ($fromThisIp >= LOGIN_MAX_ATTEMPTS) return true;

    $fromAnywhere = (int) db_val(
        'SELECT COUNT(*) FROM login_attempts
          WHERE identifier = ? AND successful = 0 AND attempted_at > ?',
        [$email, $since]
    );
    return $fromAnywhere >= LOGIN_MAX_ATTEMPTS * 4;
}

function auth_record_attempt(string $email, bool $ok): void
{
    db_insert('login_attempts', [
        'identifier'   => $email,
        'ip'           => client_ip(),
        'successful'   => $ok ? 1 : 0,
        'attempted_at' => date('Y-m-d H:i:s'),
    ]);
}

/* --------------------------------------------------------------- login */

/**
 * Attempt a sign-in.
 * @return array{ok:bool, error?:string}
 */
function auth_login(string $email, string $password): array
{
    auth_boot();
    $email = trim(mb_strtolower($email));

    if ($email === '' || $password === '') {
        return ['ok' => false, 'error' => 'Enter your email and password.'];
    }
    if (auth_is_locked($email)) {
        return ['ok' => false, 'error' =>
            'Too many failed attempts. Try again in ' . LOGIN_LOCKOUT_MINUTES . ' minutes.'];
    }

    $user = db_one(
        'SELECT u.*, r.slug AS role_slug, r.name AS role_name, s.ref AS staff_ref
           FROM users u
           JOIN roles r ON r.id = u.role_id
           LEFT JOIN staff s ON s.id = u.staff_id
          WHERE u.email = ? AND u.deleted_at IS NULL',
        [$email]
    );

    /* Verify against a dummy hash when the user is unknown, so a missing
       account and a wrong password take the same time to answer. */
    $hash = $user['password_hash'] ?? '$2y$12$usesomesillystringfore.HrOh8kK0FhWpQKCJ5nBLQmL7uJb0S';
    $valid = password_verify($password, $hash);

    if (!$user || !$valid) {
        auth_record_attempt($email, false);
        return ['ok' => false, 'error' => 'Email or password is incorrect.'];
    }
    if ($user['status'] === 'suspended') {
        auth_record_attempt($email, false);
        return ['ok' => false, 'error' => 'This account is suspended. Speak to the owner.'];
    }
    if ($user['status'] === 'invited') {
        auth_record_attempt($email, false);
        return ['ok' => false, 'error' => 'This invitation has not been accepted yet.'];
    }

    /* Rehash if the cost factor has since increased. */
    if (password_needs_rehash($hash, PASSWORD_BCRYPT, ['cost' => BCRYPT_COST])) {
        db_update('users',
            ['password_hash' => password_hash($password, PASSWORD_BCRYPT, ['cost' => BCRYPT_COST])],
            ['id' => $user['id']]);
    }

    /* Defeats session fixation. Guarded because CLI has no session to
       regenerate and would emit a warning. */
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_regenerate_id(true);
    }
    $_SESSION['user_id']   = (int) $user['id'];
    $_SESSION['last_seen'] = time();
    unset($_SESSION['csrf']);             // fresh token for the new session

    db_update('users', ['last_login_at' => date('Y-m-d H:i:s')], ['id' => $user['id']]);
    auth_record_attempt($email, true);
    /* A correct password clears the failure history, so a user who simply
       mistyped is not still counting down toward a lockout. */
    db_run('DELETE FROM login_attempts WHERE identifier = ? AND successful = 0', [$email]);
    audit('Signed in', 'user', (string) $user['id']);

    return ['ok' => true];
}

function auth_logout(): void
{
    auth_boot();
    if (!empty($_SESSION['user_id'])) {
        audit('Signed out', 'user', (string) $_SESSION['user_id']);
    }
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}

/* ------------------------------------------------------------- identity */

/** The signed-in user, or null. Loaded once per request. */
function current_user(): ?array
{
    static $user = null;
    static $loaded = false;
    if ($loaded) return $user;
    $loaded = true;

    auth_boot();
    if (empty($_SESSION['user_id'])) return null;

    $user = db_one(
        'SELECT u.id, u.ref, u.name, u.email, u.status, u.two_factor, u.last_login_at,
                u.staff_id, r.slug AS role_slug, r.name AS role_name,
                s.ref AS staff_ref, s.job_title, s.commission_pct, s.rating,
                s.reviews_count, s.working_hours, s.since_year, s.bio, s.tone
           FROM users u
           JOIN roles r ON r.id = u.role_id
           LEFT JOIN staff s ON s.id = u.staff_id
          WHERE u.id = ? AND u.deleted_at IS NULL AND u.status = "active"',
        [$_SESSION['user_id']]
    );

    /* Suspended or deleted mid-session: drop the session immediately. */
    if (!$user) {
        auth_logout();
        return null;
    }
    return $user;
}

function is_logged_in(): bool { return current_user() !== null; }

function is_owner(): bool
{
    $u = current_user();
    return $u !== null && $u['role_slug'] === 'owner';
}

/** Permission slugs granted to the signed-in user's role. */
function user_permissions(): array
{
    static $perms = null;
    if ($perms !== null) return $perms;

    $u = current_user();
    if (!$u) return $perms = [];

    $perms = db_run(
        'SELECT p.slug FROM permissions p
           JOIN role_permissions rp ON rp.permission_id = p.id
           JOIN users u ON u.role_id = rp.role_id
          WHERE u.id = ?',
        [$u['id']]
    )->fetchAll(PDO::FETCH_COLUMN);

    return $perms;
}

function can(string $permission): bool
{
    return in_array($permission, user_permissions(), true);
}

/* --------------------------------------------------------------- guards */

/** Require a signed-in user, or bounce to the login page. */
function require_login(): array
{
    $u = current_user();
    if (!$u) {
        $target = $_SERVER['REQUEST_URI'] ?? '';
        redirect('admin/login.php?next=' . urlencode($target));
    }
    return $u;
}

/**
 * Require a permission. This is the real guard — the sidebar merely hides
 * links, which stops nobody who knows the URL.
 */
function require_permission(string $permission): array
{
    $u = require_login();
    if (!can($permission)) {
        audit("Denied access to '$permission'", 'security');
        redirect('admin/index.php?denied=' . urlencode($permission));
    }
    return $u;
}

/** JSON equivalent for API endpoints. */
function api_require_permission(string $permission): array
{
    $u = current_user();
    if (!$u) json_error('Not signed in.', 401);
    if (!can($permission)) json_error('You do not have access to that.', 403);
    return $u;
}

/* ---------------------------------------------------------------- audit */

function audit(string $action, ?string $entityType = null, ?string $entityId = null): void
{
    try {
        db_insert('audit_log', [
            'user_id'     => $_SESSION['user_id'] ?? null,
            'action'      => mb_substr($action, 0, 255),
            'entity_type' => $entityType,
            'entity_id'   => $entityId,
            'ip'          => $_SERVER['REMOTE_ADDR'] ?? null,
        ]);
    } catch (Throwable $e) {
        /* An audit failure must never break the action being audited. */
        error_log('audit failed: ' . $e->getMessage());
    }
}
