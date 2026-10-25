<?php
/**
 * Princess Beauty Salon — view & formatting helpers
 */

declare(strict_types=1);

require_once __DIR__ . '/config.php';

/** Escape for HTML output. Short name because it is used constantly in views. */
function e(?string $s): string
{
    return htmlspecialchars((string) $s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** 25000 → "25,000 FCFA" */
function money($amount, bool $bare = false): string
{
    $n = number_format((float) $amount, 0, '.', ',');
    return $bare ? $n : $n . ' ' . CURRENCY;
}

/**
 * 3145000 → "3.1M", 25000 → "25k" — for chart axes and tight tiles.
 *
 * The trailing rtrim(…, '0') this used to carry ate significant digits:
 * 10,000,000 rendered as "1M" and 100,000,000 as "1M" — a financial figure
 * understated by a factor of ten or a hundred. Below 10M we show one decimal,
 * at or above it we show none, and nothing is stripped afterwards.
 * Must stay byte-identical to PBS.moneyShort() in assets/js/ui.js, because
 * PHP renders the stat tiles and JS renders the chart axes on the same screen.
 */
function money_short($amount): string
{
    $v = (float) $amount;
    if ($v >= 1000000) {
        return number_format($v / 1000000, $v >= 10000000 ? 0 : 1, '.', '') . 'M';
    }
    if ($v >= 1000) return (string) round($v / 1000) . 'k';
    return (string) round($v);
}

/** 240 → "4h", 90 → "1h 30min" */
function duration(int $minutes): string
{
    $h = intdiv($minutes, 60);
    $m = $minutes % 60;
    return trim(($h ? "{$h}h" : '') . ($h && $m ? ' ' : '') . ($m ? "{$m}min" : '')) ?: '0min';
}

/** "Aïcha Ngo" → "AN" */
function initials(string $name): string
{
    $parts = preg_split('/\s+/u', trim($name)) ?: [];
    $out = '';
    foreach (array_slice($parts, 0, 2) as $p) {
        $out .= mb_strtoupper(mb_substr($p, 0, 1));
    }
    return $out ?: '?';
}

/** "2026-08-20" → "Thu, 20 Aug" */
function fmt_day(?string $date): string
{
    if (!$date) return '—';
    $ts = strtotime($date);
    return $ts ? date('D, j M', $ts) : '—';
}

/** "2026-08-20" → "Thursday, 20 August 2026" */
function fmt_date_long(?string $date): string
{
    if (!$date) return '—';
    $ts = strtotime($date);
    return $ts ? date('l, j F Y', $ts) : '—';
}

/** "14:00:00" → "14:00" */
function fmt_time(?string $t): string
{
    return $t ? substr($t, 0, 5) : '—';
}

/** URL for a path inside the app: url('/public/book.php') */
function url(string $path = ''): string
{
    return BASE_URL . '/' . ltrim($path, '/');
}

/** Redirect and stop. */
function redirect(string $path): never
{
    header('Location: ' . (str_starts_with($path, 'http') ? $path : url($path)));
    exit;
}

/** Send a JSON response and stop. */
function json_response($data, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function json_error(string $message, int $status = 400, array $extra = []): never
{
    json_response(['ok' => false, 'error' => $message] + $extra, $status);
}

/**
 * Bumped whenever settings are written, so the cache below knows it is stale.
 * A counter rather than a flag: it cannot be "already cleared" by one caller
 * and then missed by another.
 */
function setting_generation(bool $bump = false): int
{
    static $gen = 0;
    if ($bump) $gen++;
    return $gen;
}

/**
 * Read a setting, with a fallback.
 *
 * Cached for the request, which is right for a page render and wrong for
 * anything that writes a setting and then asks about it in the same request.
 * Call setting_forget() in between — the admin settings form and the tests
 * both do.
 */
function setting(string $key, ?string $default = null): ?string
{
    static $cache = null;
    static $loadedAt = -1;

    if ($cache === null || $loadedAt !== setting_generation()) {
        require_once __DIR__ . '/db.php';
        $cache = [];
        foreach (db_all('SELECT `key`, `value` FROM settings') as $row) {
            $cache[$row['key']] = $row['value'];
        }
        $loadedAt = setting_generation();
    }
    return $cache[$key] ?? $default;
}

/** Drop the settings cache, so the next read sees what was just written. */
function setting_forget(): void
{
    setting_generation(true);
}

/**
 * The submitted fields, whether the request was a form post or a JSON body.
 *
 * PBS.api() sends JSON with an X-CSRF-Token header, which leaves $_POST
 * completely empty. An endpoint that reads $_POST directly therefore sees no
 * fields at all and falls through to whatever its default branch is — the
 * payment endpoint answered "Unknown action" to every perfectly valid request
 * from the booking wizard, because `action` was in the JSON body it never
 * read. Reading through here removes the trap.
 */
function request_input(): array
{
    static $cache = null;
    if ($cache !== null) return $cache;

    $raw = file_get_contents('php://input');
    if (is_string($raw) && $raw !== '' && str_starts_with(ltrim($raw), '{')) {
        $decoded = json_decode($raw, true);
        if (is_array($decoded)) {
            /* Form fields still win: they cannot be present unless the client
               actually sent them, and mixing the two is never intentional. */
            return $cache = $_POST + $decoded;
        }
    }
    return $cache = $_POST;
}

/** Status badge markup, matching the prototype's classes. */
function status_badge(string $status): string
{
    $map = [
        'pending'   => ['Pending',   'badge-warning'],
        'confirmed' => ['Confirmed', 'badge-info'],
        'completed' => ['Completed', 'badge-success'],
        'cancelled' => ['Cancelled', 'badge-danger'],
        'no-show'   => ['No-show',   'badge'],
    ];
    [$label, $cls] = $map[$status] ?? [ucfirst($status), 'badge'];
    return '<span class="badge ' . $cls . '">' . e($label) . '</span>';
}

/** Payment-method badge. */
function payment_badge(string $method): string
{
    $map = [
        'cash'   => ['Cash',         'badge'],
        'momo'   => ['MTN MoMo',     'badge-warning'],
        'om'     => ['Orange Money', 'badge-danger'],
        'card'   => ['Card',         'badge-info'],
        'unpaid' => ['Unpaid',       'badge-danger'],
    ];
    [$label, $cls] = $map[$method] ?? [ucfirst($method), 'badge'];
    return '<span class="badge badge-plain ' . $cls . '">' . e($label) . '</span>';
}

/** Percentage change between two periods, for the dashboard delta tags. */
function delta_tag($current, $previous): string
{
    if (!$previous) {
        return '<span class="delta flat">—</span>';
    }
    $pct = (($current - $previous) / $previous) * 100;
    $up = $pct >= 0;
    return sprintf(
        '<span class="delta %s">%s%.1f%%</span>',
        $up ? 'up' : 'down',
        $up ? '&#9650; ' : '&#9660; ',
        abs($pct)
    );
}

/** Generate a booking reference that is not already taken. */
function generate_booking_ref(): string
{
    require_once __DIR__ . '/db.php';
    for ($i = 0; $i < 20; $i++) {
        $ref = 'PBS-' . random_int(1000, 9999);
        if (!db_val('SELECT 1 FROM appointments WHERE ref = ?', [$ref])) {
            return $ref;
        }
    }
    /* Astronomically unlikely; fall back to something guaranteed unique. */
    return 'PBS-' . strtoupper(bin2hex(random_bytes(3)));
}

/** Next sequential reference for a table, e.g. next_ref('expenses','EX-',3). */
function next_ref(string $table, string $prefix, int $pad = 3): string
{
    require_once __DIR__ . '/db.php';
    $max = db_val(
        "SELECT MAX(CAST(SUBSTRING(ref, ?) AS UNSIGNED)) FROM `$table` WHERE ref LIKE ?",
        [strlen($prefix) + 1, $prefix . '%']
    );
    return $prefix . str_pad((string) (((int) $max) + 1), $pad, '0', STR_PAD_LEFT);
}

/**
 * A photo tile, or the gradient that stands in for one.
 *
 * The database stores a folder and a filename, never an image, so the only
 * thing a view ever holds is a path like `assets/img/lashes/p3.jpeg`. If the
 * file is really on disk it is drawn inside the same `.ph` box the gradient
 * used, which means every existing size rule — aspect ratios, radii, the
 * gallery's hover zoom — keeps working untouched. If it is missing, or the
 * row has no filename yet, the caller gets the old gradient with its script
 * initial. Nothing on the page breaks while a folder is still half full.
 *
 *   photo(['path' => $g['image_path'], 'tone' => $g['tone'],
 *          'label' => 'Braids', 'alt' => $g['title'], 'ratio' => '3/4'])
 */
function photo(array $o): string
{
    $path  = $o['path']  ?? null;
    $tone  = $o['tone']  ?? 'ph';
    $class = trim('ph ' . ($tone === 'ph' ? '' : $tone) . ' ' . ($o['class'] ?? ''));

    $style = $o['style'] ?? '';
    if (!empty($o['ratio']))  { $style = 'aspect-ratio:' . $o['ratio'] . ';' . $style; }
    if (!empty($o['radius'])) { $style = 'border-radius:' . $o['radius'] . ';' . $style; }

    $html = '<div class="' . e($class) . '"'
          . ($style !== '' ? ' style="' . e($style) . '"' : '') . '>';

    if ($path && is_file(APP_ROOT . '/' . ltrim($path, '/'))) {
        $html .= '<img src="' . e(url($path)) . '" alt="' . e($o['alt'] ?? '') . '"'
               . ' loading="' . e($o['loading'] ?? 'lazy') . '" decoding="async">';
    } elseif (!empty($o['label'])) {
        $html .= '<i>' . e($o['label']) . '</i>';
    }

    return $html . '</div>';
}

/**
 * Where a stylist's portrait lives. `staff.photo` holds a bare filename so the
 * folder is decided in one place; a stylist without a file gets NULL and
 * photo() falls back to their initials on a gradient.
 */
function staff_photo(?string $filename): ?string
{
    return $filename ? 'assets/img/team/' . $filename : null;
}
