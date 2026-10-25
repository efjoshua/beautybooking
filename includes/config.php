<?php
/**
 * Princess Beauty Salon — configuration
 *
 * Everything environment-specific lives here and nowhere else. In deployment
 * this file is the only one that changes.
 */

declare(strict_types=1);

/* ---------------------------------------------------------------- database */
define('DB_HOST', '127.0.0.1');
define('DB_PORT', 3306);
define('DB_NAME', 'princess_beauty_salon');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

/* ------------------------------------------------------------------- app */
define('APP_NAME', 'Princess Beauty Salon');
define('APP_ENV', 'development');            // development | production
define('APP_DEBUG', APP_ENV === 'development');
/** Filesystem root of the project. */
define('APP_ROOT', dirname(__DIR__));

/**
 * Base URL path the app is served from, no trailing slash.
 *
 * Worked out from where the project actually sits inside the web root rather
 * than written down, because the two differ on every machine: `/beautybooking`
 * under XAMPP, an empty string on a host that points the domain straight at
 * this folder, `/staging/salon` on someone's test box. Hard-coding it means
 * every stylesheet, script and link 404s the moment the app moves — the site
 * still renders, just with no CSS at all, which looks like total collapse.
 *
 * Set PBS_BASE_URL in the environment to override it, for the cases detection
 * cannot see: a reverse proxy that strips a prefix, or a symlinked docroot.
 */
if (!defined('BASE_URL')) {
    $pbsBase = getenv('PBS_BASE_URL');

    if ($pbsBase === false || $pbsBase === '') {
        $docRoot = realpath((string) ($_SERVER['DOCUMENT_ROOT'] ?? ''));
        $appRoot = realpath(APP_ROOT);
        $slash   = fn(string $p): string => rtrim(str_replace(chr(92), '/', $p), '/');

        /* Only meaningful when the project really is inside the web root. If it
           is not — a CLI run, or a docroot that points elsewhere — the base is
           the root, which is what a domain-level install needs anyway. */
        $pbsBase = ($docRoot && $appRoot && str_starts_with($slash($appRoot), $slash($docRoot) . '/'))
            ? substr($slash($appRoot), strlen($slash($docRoot)))
            : '';
    }

    define('BASE_URL', $pbsBase === '/' ? '' : rtrim((string) $pbsBase, '/'));
}

/* --------------------------------------------------------------- business */
define('CURRENCY', 'FCFA');
define('CURRENCY_CODE', 'XAF');
define('APP_TIMEZONE', 'Africa/Douala');

/**
 * The prototype was pinned to a fixed "today" so every demo figure told one
 * story. The seed data is built around that date, so reports have something
 * to show. Set DEMO_TODAY to null to use the real current date.
 */
define('DEMO_TODAY', '2026-08-20');

/* -------------------------------------------------------------- security */
define('SESSION_NAME', 'PBS_SESSION');
/** Idle minutes before a staff session is dropped. */
define('SESSION_IDLE_MINUTES', 120);
/** Failed sign-ins from one identity before it is temporarily locked. */
define('LOGIN_MAX_ATTEMPTS', 5);
define('LOGIN_LOCKOUT_MINUTES', 15);
/** password_hash cost. 12 is a reasonable 2026 default on modest hardware. */
define('BCRYPT_COST', 12);

/* -------------------------------------------------------------- payments
 * Mobile money collection. LEAVE THESE BLANK UNTIL YOU HAVE THE CREDENTIALS.
 *
 * The app has three payment states and handles all of them:
 *
 *   1. Switched off  — "Take deposits online" is off in Settings. Clients book
 *                      with nothing to pay and settle at the salon. This is the
 *                      default and it is a perfectly good way to run.
 *   2. On, no keys   — the booking page shows the MTN MoMo / Orange Money
 *                      section, explains that payment is not connected yet, and
 *                      lets the client finish the booking anyway. Nobody is
 *                      ever blocked from booking by a missing key.
 *   3. On, with keys — a real collection request is pushed to the client's
 *                      phone and the deposit is banked against the booking.
 *
 * To go from 2 to 3, fill in the constants below and nothing else. No code
 * changes, no redeploy of anything but this file.
 *
 * MTN MoMo Collection API — https://momodeveloper.mtn.com
 *   Subscribe to the "Collection" product, then create an API user and key.
 *   MOMO_ENVIRONMENT is 'sandbox' while testing and 'mtncameroon' when live.
 */
define('MOMO_SUBSCRIPTION_KEY', '');   // Ocp-Apim-Subscription-Key
define('MOMO_API_USER',         '');   // the API user UUID
define('MOMO_API_KEY',          '');   // the API key generated for that user
define('MOMO_ENVIRONMENT',      'sandbox');
define('MOMO_BASE_URL',         'https://sandbox.momodeveloper.mtn.com');
define('MOMO_CURRENCY',         MOMO_ENVIRONMENT === 'sandbox' ? 'EUR' : 'XAF');

/*
 * Orange Money Cameroon Web Payment — https://developer.orange.com
 *   Subscribe to "Orange Money Web Payment", country Cameroon.
 */
define('OM_CLIENT_ID',     '');
define('OM_CLIENT_SECRET', '');
define('OM_MERCHANT_KEY',  '');        // from your Orange Money merchant account
define('OM_BASE_URL',      'https://api.orange.com');

/** Seconds to wait for the client to approve the prompt on their handset. */
define('PAYMENT_TIMEOUT_SECONDS', 120);

/** Outbound HTTP timeout when talking to a provider. */
define('PAYMENT_HTTP_TIMEOUT', 20);

/* --------------------------------------------------------------- runtime */
date_default_timezone_set(APP_TIMEZONE);
mb_internal_encoding('UTF-8');

if (APP_DEBUG) {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
} else {
    error_reporting(E_ALL);
    ini_set('display_errors', '0');
    ini_set('log_errors', '1');
}
