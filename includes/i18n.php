<?php
/**
 * Princess Beauty Salon — bilingual support
 *
 * Cameroon is bilingual and Yaoundé is francophone, so the site has to be too.
 * Two things need translating and they are not the same problem:
 *
 *   1. The chrome and the page copy — written into the templates, translated
 *      through t() against the arrays in lang/.
 *   2. What the salon typed into the admin panel — service names, FAQs, offers,
 *      bios. Those live in the database, so the French sits in a `translations`
 *      table keyed by (table, row, field, locale) and comes back through tr().
 *
 * Missing a translation is never fatal. t() falls back to English, tr() falls
 * back to the row as the salon entered it, so a half-translated FAQ shows in
 * English rather than as a blank space or a raw key.
 */

declare(strict_types=1);

require_once __DIR__ . '/db.php';

const LOCALES = ['en' => 'English', 'fr' => 'Français'];
const LOCALE_DEFAULT = 'en';
const LOCALE_COOKIE = 'pbs_lang';

/**
 * The language for this request.
 *
 * ?lang= wins and is remembered for a year, because the switch in the header
 * has to survive the next click. After that it is the cookie, then whatever
 * the browser asks for — a French-speaking client arriving cold should not
 * have to find the switch at all.
 */
function locale(): string
{
    static $locale = null;
    if ($locale !== null) {
        return $locale;
    }

    $wanted = strtolower(substr((string) ($_GET['lang'] ?? ''), 0, 2));
    if (isset(LOCALES[$wanted])) {
        if (PHP_SAPI !== 'cli' && !headers_sent()) {
            setcookie(LOCALE_COOKIE, $wanted, [
                'expires'  => time() + 31536000,
                'path'     => BASE_URL . '/',
                'samesite' => 'Lax',
            ]);
        }
        return $locale = $wanted;
    }

    $cookie = strtolower(substr((string) ($_COOKIE[LOCALE_COOKIE] ?? ''), 0, 2));
    if (isset(LOCALES[$cookie])) {
        return $locale = $cookie;
    }

    foreach (explode(',', (string) ($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '')) as $part) {
        $tag = strtolower(substr(trim(explode(';', $part)[0]), 0, 2));
        if (isset(LOCALES[$tag])) {
            return $locale = $tag;
        }
    }

    return $locale = LOCALE_DEFAULT;
}

/** True when the page is being served in French. */
function is_fr(): bool
{
    return locale() === 'fr';
}

/** The dictionary for a locale, loaded once. */
function lang_strings(?string $loc = null): array
{
    static $cache = [];
    $loc = $loc ?: locale();
    if (!isset($cache[$loc])) {
        $file = APP_ROOT . '/lang/' . $loc . '.php';
        $cache[$loc] = is_file($file) ? (array) require $file : [];
    }
    return $cache[$loc];
}

/**
 * Translate a template string.
 *
 * The key IS the English text, so a template still reads as prose and an
 * untranslated string degrades to itself rather than to "home.hero.title".
 * :name placeholders are replaced after lookup.
 *
 *   t('Book an Appointment')
 *   t('Showing :count services', ['count' => 24])
 */
function t(string $key, array $vars = []): string
{
    $out = lang_strings()[$key] ?? $key;
    foreach ($vars as $k => $v) {
        $out = str_replace(':' . $k, (string) $v, $out);
    }
    return $out;
}

/** t() escaped for HTML, which is how a view almost always wants it. */
function te(string $key, array $vars = []): string
{
    return e(t($key, $vars));
}

/**
 * Translate one field of a database row.
 *
 * Falls back to the row's own value, so anything the salon adds tomorrow shows
 * up immediately in the language it was typed in rather than disappearing.
 *
 *   tr($service, 'name')                 uses $service['id']
 *   tr($faq, 'answer', 'faqs')           when the table cannot be guessed
 */
function tr(array $row, string $field, ?string $table = null): string
{
    $value = (string) ($row[$field] ?? '');
    if (locale() === LOCALE_DEFAULT || $value === '' || empty($row['id'])) {
        return $value;
    }

    $table = $table ?: ($row['__table'] ?? '');
    if ($table === '') {
        return $value;
    }

    return db_translation($table, (int) $row['id'], $field, locale()) ?? $value;
}

/**
 * All translations for one locale, fetched once per request.
 *
 * A page can render two dozen services and a dozen FAQs; that is fifty lookups
 * if each one queries. One query and an in-memory map instead.
 */
function db_translation(string $table, int $id, string $field, string $loc): ?string
{
    static $map = null;
    if ($map === null) {
        $map = [];
        foreach (db_all(
            'SELECT source_table, source_id, field, value FROM translations WHERE locale = ?',
            [$loc]) as $r) {
            $map[$r['source_table'] . ':' . $r['source_id'] . ':' . $r['field']] = $r['value'];
        }
    }
    $v = $map["$table:$id:$field"] ?? null;
    return ($v === null || $v === '') ? null : $v;
}

/** Tag every row of a result set with the table it came from, for tr(). */
function tag_rows(array $rows, string $table): array
{
    foreach ($rows as &$r) {
        $r['__table'] = $table;
    }
    return $rows;
}

/**
 * The current URL with a different language, preserving every other parameter
 * so switching language never loses the filter or the page someone was on.
 */
function locale_url(string $loc): string
{
    $q = $_GET;
    $q['lang'] = $loc;
    return basename((string) ($_SERVER['SCRIPT_NAME'] ?? 'index.php')) . '?' . http_build_query($q);
}

/** Language switch for the header and the footer. */
function locale_switch(string $cls = ''): string
{
    $out = '<div class="lang-switch ' . e($cls) . '" role="group" aria-label="Language">';
    foreach (LOCALES as $code => $label) {
        $out .= '<a href="' . e(locale_url($code)) . '"'
              . ($code === locale() ? ' class="active" aria-current="true"' : '')
              . ' hreflang="' . $code . '">' . e(strtoupper($code)) . '</a>';
    }
    return $out . '</div>';
}

/**
 * Dates in the reader's language. PHP's date() is English-only, so the month
 * and day names are mapped rather than formatted.
 */
function fmt_day_i18n(?string $date): string
{
    if (!$date) return '—';
    if (!is_fr()) return fmt_day($date);
    return fr_days()[date('N', strtotime($date))] . ' ' . date('j', strtotime($date))
         . ' ' . fr_months()[(int) date('n', strtotime($date))];
}

function fmt_date_long_i18n(?string $date): string
{
    if (!$date) return '—';
    if (!is_fr()) return fmt_date_long($date);
    $ts = strtotime($date);
    return fr_days(true)[date('N', $ts)] . ' ' . date('j', $ts) . ' '
         . fr_months(true)[(int) date('n', $ts)] . ' ' . date('Y', $ts);
}

function fr_days(bool $long = false): array
{
    $s = [1 => 'lun.', 'mar.', 'mer.', 'jeu.', 'ven.', 'sam.', 'dim.'];
    $l = [1 => 'lundi', 'mardi', 'mercredi', 'jeudi', 'vendredi', 'samedi', 'dimanche'];
    return $long ? $l : $s;
}

function fr_months(bool $long = false): array
{
    $s = [1 => 'janv.', 'févr.', 'mars', 'avr.', 'mai', 'juin',
          'juil.', 'août', 'sept.', 'oct.', 'nov.', 'déc.'];
    $l = [1 => 'janvier', 'février', 'mars', 'avril', 'mai', 'juin',
          'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre'];
    return $long ? $l : $s;
}
