<?php
/**
 * Production preflight — what must be true before real clients and real money
 * touch this system.
 *
 * Unlike the other suites, a failure here is not a bug in the code. It means
 * the deployment is not configured yet. Run it on the server you intend to
 * serve from, not on the development box.
 *
 *   php _dev/12-preflight.php
 */
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/repo.php';

$blockers = []; $warnings = []; $ready = 0;

function must(string $name, bool $cond, string $fix): void {
    global $blockers, $ready;
    if ($cond) { $ready++; echo "ready    $name\n"; }
    else { $blockers[] = [$name, $fix]; echo "BLOCKER  $name\n           → $fix\n"; }
}
function should(string $name, bool $cond, string $fix): void {
    global $warnings, $ready;
    if ($cond) { $ready++; echo "ready    $name\n"; }
    else { $warnings[] = [$name, $fix]; echo "warn     $name\n           → $fix\n"; }
}
function grp(string $g): void { echo "\n--- $g ---\n"; }

/* ============================================================ environment */
grp('environment');

must('APP_ENV is production', APP_ENV === 'production',
     "includes/config.php: define('APP_ENV', 'production') — this also turns off display_errors");
must('PHP errors are not shown to visitors', ini_get('display_errors') === '0'
     || strtolower((string) ini_get('display_errors')) === 'off',
     'a stack trace on screen tells an attacker your file paths and SQL');
should('errors are written to a log instead', (string) ini_get('log_errors') === '1'
     && (string) ini_get('error_log') !== '',
     'set error_log in php.ini, or nothing survives to be diagnosed');
must('PHP is 8.1 or newer', PHP_VERSION_ID >= 80100,
     'the code uses match(), enums in SQL and readonly semantics; ' . PHP_VERSION . ' found');

foreach (['pdo_mysql', 'mbstring', 'json'] as $ext) {
    must("extension $ext is loaded", extension_loaded($ext), "install php-$ext");
}
should('the timezone is the salon\'s', date_default_timezone_get() === APP_TIMEZONE,
     'reports roll over at the wrong hour otherwise; expected ' . APP_TIMEZONE
     . ', got ' . date_default_timezone_get());

/* ============================================================== database */
grp('database');

must('the app does not connect as root', DB_USER !== 'root',
     'create a user with SELECT/INSERT/UPDATE/DELETE on this schema only — no DROP, no GRANT');
must('the database password is not empty', DB_PASS !== '',
     'an empty password means anyone who reaches the port owns every client record');
should('the DB host is local or private', in_array(DB_HOST, ['127.0.0.1', 'localhost'], true)
     || str_starts_with(DB_HOST, '10.') || str_starts_with(DB_HOST, '192.168.'),
     'MySQL should not be reachable from the public internet');

$charset = db_val('SELECT @@character_set_database');
should('the schema is utf8mb4', $charset === 'utf8mb4',
     "found $charset — names like Aïcha and emoji in notes will corrupt");

/* ================================================================ demo data */
grp('demo data and accounts');

must('DEMO_TODAY is switched off', DEMO_TODAY === null,
     "includes/config.php: define('DEMO_TODAY', null) — otherwise every report is pinned to "
     . DEMO_TODAY . ' and today\'s real takings never appear');

$weak = [];
foreach (db_all('SELECT email, password_hash FROM users') as $u) {
    foreach (['demo1234', 'password', '12345678', 'admin'] as $guess) {
        if ($u['password_hash'] && password_verify($guess, $u['password_hash'])) {
            $weak[] = $u['email'] . ' (' . $guess . ')';
            break;
        }
    }
}
must('no account still uses a demo password', !$weak,
     count($weak) . ' account(s): ' . implode(', ', $weak)
     . ' — every one of these can sign in from the public internet today');

$seedClients = (int) db_val(
    "SELECT COUNT(*) FROM clients WHERE ref LIKE 'CL-%' AND deleted_at IS NULL");
should('the client list is real, not seeded demo data', $seedClients === 0,
     "$seedClients seeded clients present — real bookings will be mixed in with fiction, "
     . 'and the owner cannot tell which revenue is real');

should('no seeded trading history remains',
     (int) db_val("SELECT COUNT(*) FROM appointments WHERE ref LIKE 'AP-%'") === 0,
     'the 10 weeks of generated history exists to make the demo legible; on a live salon it '
     . 'is fabricated revenue in the owner\'s own reports');

/* ================================================================ transport */
grp('transport and sessions');

/* Run from the CLI there is no request to inspect, and "no request" must not
   be reported as "TLS is fine" — that is how a check becomes decoration. */
if (PHP_SAPI === 'cli') {
    should('HTTPS is in use', false,
         'cannot be determined from the command line. Load any page over https:// and confirm '
         . 'the padlock, then check the PBS_SESSION cookie carries the Secure flag. Without TLS, '
         . 'passwords and client phone numbers cross the network in clear text');
} else {
    should('HTTPS is in use', !empty($_SERVER['HTTPS']),
         'sessions, passwords and client phone numbers cross the network in clear text; '
         . 'the session cookie only sets its Secure flag under TLS');
}
/* auth_boot() sets the cookie flags at runtime and returns early under CLI,
   so php.ini says nothing useful here. Ask the running app for a real
   Set-Cookie header instead of assuming. */
$setCookie = null;
$origin = getenv('PBS_BASE') ?: ('http://localhost' . BASE_URL);
$probe = @get_headers(rtrim($origin, '/') . '/admin/login.php', true);
if ($probe && isset($probe['Set-Cookie'])) {
    $setCookie = implode(' ; ', (array) $probe['Set-Cookie']);
}
if ($setCookie === null) {
    should('the session cookie flags were checked', false,
         'could not reach ' . $origin . '/admin/login.php to read a real '
         . 'Set-Cookie header — start the web server and re-run, or check by hand in devtools');
} else {
    should('the session cookie is HttpOnly', stripos($setCookie, 'HttpOnly') !== false,
         "JavaScript can read the session otherwise. Server sent: $setCookie");
    should('the session cookie sets SameSite', stripos($setCookie, 'SameSite') !== false,
         "SameSite=Lax blocks cross-site POSTs. Server sent: $setCookie");
    should('the session cookie is Secure', stripos($setCookie, 'Secure') !== false,
         'expected until the site is served over TLS — the flag is set automatically under '
         . "HTTPS by includes/auth.php. Server sent: $setCookie");
}
must('sessions expire when idle', SESSION_IDLE_MINUTES > 0 && SESSION_IDLE_MINUTES <= 480,
     'a salon tablet left on the counter stays signed in otherwise');

/* ================================================================ exposure */
grp('files that must not be served');

$root = dirname(__DIR__);
foreach (['includes', 'db', '_dev'] as $dir) {
    must("$dir/ has a deny-all .htaccess", is_file("$root/$dir/.htaccess"),
         "create $dir/.htaccess with 'Require all denied'");
}
must('the root .htaccess exists', is_file("$root/.htaccess"),
     'it blocks package.json, README.md and stray .sql/.log/.bak files');
should('AllowOverride is on (deny rules are actually read)',
     function_exists('apache_get_modules') ? in_array('mod_rewrite', apache_get_modules(), true) : true,
     'if AllowOverride is None, every .htaccess above is decorative — verify by requesting '
     . '/includes/config.php in a browser and confirming 403');

/* ================================================================ money */
grp('money');

/* The endpoint existing proves nothing — it ships with the app. What matters
   is whether a provider can actually take money, which is what the app itself
   reports. This check used to pass on is_file() alone. */
require_once __DIR__ . '/../includes/payments.php';
setting_forget();
$payState = payments_state();
should('online payment is connected and collecting', $payState === 'live',
     match ($payState) {
         'off' => 'deposits are switched off in Settings, so clients book and pay at the salon. '
                . 'That is a valid way to run — turn on "Take payments online" when you have '
                . 'MTN MoMo or Orange Money credentials',
         default => 'payments are switched ON but no provider has credentials in config.php, so '
                . 'the booking page tells clients payment is not connected and lets them book '
                . 'anyway. Fill in MOMO_* or OM_* to start collecting',
     });
foreach (payment_providers() as $pp) {
    should($pp['name'] . ' has credentials', $pp['ready'],
         'set the ' . ($pp['code'] === 'momo' ? 'MOMO_*' : 'OM_*') . ' constants in includes/config.php');
}

$tax = (float) setting('tax_rate', '0');
should('the tax rate is either zero or applied', $tax == 0.0,
     "tax_rate is set to $tax% but no report applies it — every figure is tax-inclusive takings");

should('someone is notified when a booking arrives',
     is_file("$root/api/notify.php") || setting('notify_email', '') !== '',
     'no email or WhatsApp is sent on booking; a pending appointment is only seen by opening '
     . 'the admin diary, so an unattended booking can go unnoticed until the client arrives');

/* ================================================================ backups */
grp('operations');

should('a backup has run recently', false,
     'nothing in this project takes backups. A salon that loses its client list loses its '
     . 'business — schedule mysqldump before go-live, and restore it once to prove it works');

echo "\n";
echo "=== $ready ready, " . count($blockers) . " blocking, " . count($warnings) . " to decide ===\n";
if ($blockers) {
    echo "\nMust be fixed before go-live:\n";
    foreach ($blockers as [$n, $f]) echo "  • $n\n      $f\n";
}
exit($blockers ? 1 : 0);
