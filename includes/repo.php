<?php
/**
 * Princess Beauty Salon — query layer
 *
 * All reporting SQL lives here rather than inside page templates, so a figure
 * has exactly one definition. Every number the owner sees is computed from
 * appointment and sale rows — nothing is stored pre-aggregated, because a
 * stored total silently goes stale the moment a booking is edited.
 */

declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';

/* ===================================================================== misc */

function repo_categories(): array
{
    return db_all('SELECT * FROM service_categories ORDER BY sort_order, id');
}

function repo_services(?string $categorySlug = null, bool $publicOnly = true): array
{
    $sql = 'SELECT s.*, c.slug AS category_slug, c.name AS category_name,
                   c.short_name AS category_short, c.icon AS category_icon, c.color
              FROM services s
              JOIN service_categories c ON c.id = s.category_id
             WHERE s.deleted_at IS NULL';
    $p = [];
    if ($publicOnly)     { $sql .= ' AND s.is_public = 1'; }
    if ($categorySlug)   { $sql .= ' AND c.slug = ?'; $p[] = $categorySlug; }
    return db_all($sql . ' ORDER BY c.sort_order, s.sort_order, s.id', $p);
}

function repo_service_by_ref(string $ref): ?array
{
    return db_one(
        'SELECT s.*, c.slug AS category_slug FROM services s
           JOIN service_categories c ON c.id = s.category_id
          WHERE s.ref = ? AND s.deleted_at IS NULL', [$ref]);
}

function repo_staff(bool $publicOnly = false): array
{
    $sql = 'SELECT * FROM staff WHERE deleted_at IS NULL';
    if ($publicOnly) $sql .= ' AND is_public = 1';
    $rows = db_all($sql . ' ORDER BY sort_order, id');

    /* attach the categories each stylist can be booked for */
    foreach ($rows as &$r) {
        $r['categories'] = db_run(
            'SELECT c.slug FROM service_categories c
               JOIN staff_categories sc ON sc.category_id = c.id
              WHERE sc.staff_id = ? ORDER BY c.sort_order', [$r['id']]
        )->fetchAll(PDO::FETCH_COLUMN);
    }
    return $rows;
}

/** Stylists qualified for EVERY one of the given category slugs. */
function repo_staff_for_categories(array $categorySlugs): array
{
    if (!$categorySlugs) return repo_staff(true);
    [$in, $params] = db_in($categorySlugs);
    return db_all(
        "SELECT s.* FROM staff s
           JOIN staff_categories sc ON sc.staff_id = s.id
           JOIN service_categories c ON c.id = sc.category_id
          WHERE s.deleted_at IS NULL AND s.is_public = 1 AND c.slug IN $in
          GROUP BY s.id
         HAVING COUNT(DISTINCT c.id) = ?
          ORDER BY s.sort_order",
        array_merge($params, [count(array_unique($categorySlugs))]));
}

/**
 * Folders that hold public work, in the order the filter chips appear.
 * `category_id` is NULL for work the salon shows but does not sell as a
 * bookable service, so those chips simply have no "book this look" target.
 */
function repo_gallery_folders(): array
{
    return db_all(
        "SELECT f.*, c.slug AS category_slug, c.short_name
           FROM gallery_folders f
      LEFT JOIN service_categories c ON c.id = f.category_id
          WHERE f.is_public = 1 AND f.role = 'gallery'
       ORDER BY f.sort_order, f.id");
}

/**
 * Photos, addressed by the folder they sit in.
 *
 * Nothing but the folder and the filename is stored, so the public path is
 * assembled here and nowhere else. `image_path` is relative to the app root;
 * pass it through url(). A row whose file has been deleted from disk still
 * renders — as the gradient tile in `tone` — until the scanner next runs.
 */
function repo_gallery(?string $folderSlug = null): array
{
    $sql = "SELECT g.*, f.folder, f.slug AS folder_slug, f.label AS folder_label,
                   f.tone, c.slug AS category_slug,
                   COALESCE(c.short_name, f.label) AS short_name,
                   s.name AS staff_name,
                   CONCAT('assets/img/', f.folder, '/', g.filename) AS image_path
              FROM gallery g
              JOIN gallery_folders f ON f.id = g.folder_id
         LEFT JOIN service_categories c ON c.id = f.category_id
         LEFT JOIN staff s ON s.id = g.staff_id
             WHERE g.is_public = 1 AND f.is_public = 1 AND f.role = 'gallery'";
    $p = [];
    if ($folderSlug) { $sql .= ' AND f.slug = ?'; $p[] = $folderSlug; }
    return db_all($sql . ' ORDER BY f.sort_order, g.sort_order, g.id', $p);
}

/**
 * Files in one folder regardless of whether it is public — used for the hero
 * and section backgrounds, which come from `backgrounds/` and never appear as
 * gallery tiles. Returns app-root-relative paths.
 */
function repo_folder_images(string $folder, int $limit = 0): array
{
    $rows = db_all(
        "SELECT CONCAT('assets/img/', f.folder, '/', g.filename) AS path
           FROM gallery g JOIN gallery_folders f ON f.id = g.folder_id
          WHERE f.folder = ? ORDER BY g.sort_order, g.id"
        . ($limit > 0 ? ' LIMIT ' . $limit : ''),
        [$folder]);
    return array_column($rows, 'path');
}

/**
 * A spread across the folders rather than the first N rows, so the strip on
 * the home page never shows eight versions of the same braid.
 */
function repo_gallery_showcase(int $limit = 8): array
{
    $byFolder = [];
    foreach (repo_gallery() as $g) {
        $byFolder[$g['folder']][] = $g;
    }

    $out = [];
    while (count($out) < $limit && $byFolder) {
        foreach (array_keys($byFolder) as $k) {
            $out[] = array_shift($byFolder[$k]);
            if (!$byFolder[$k]) { unset($byFolder[$k]); }
            if (count($out) >= $limit) { break; }
        }
    }
    return $out;
}

function repo_reviews(int $limit = 6): array
{
    return db_all(
        'SELECT * FROM reviews WHERE is_public = 1 ORDER BY id LIMIT ' . max(1, $limit));
}

function repo_faqs(): array
{
    return db_all('SELECT * FROM faqs WHERE is_public = 1 ORDER BY sort_order, id');
}

function repo_offers(): array
{
    $offers = db_all(
        'SELECT o.*, c.slug AS category_slug, c.icon AS category_icon
           FROM offers o LEFT JOIN service_categories c ON c.id = o.category_id
          WHERE o.is_active = 1 ORDER BY o.sort_order, o.id');
    foreach ($offers as &$o) {
        $o['items'] = db_run(
            'SELECT label FROM offer_items WHERE offer_id = ? ORDER BY sort_order',
            [$o['id']])->fetchAll(PDO::FETCH_COLUMN);
    }
    return $offers;
}

function repo_business_hours(): array
{
    return db_all('SELECT * FROM business_hours ORDER BY weekday');
}

/* ============================================================ appointments */


/**
 * The orderings the appointment list offers, keyed by the value in the URL.
 * Anything not on this list falls back to `submitted`.
 */
const APPT_SORTS = [
    'submitted'     => 'DATE(a.created_at) DESC, c.name ASC, a.starts_at ASC',
    'submitted-asc' => 'DATE(a.created_at) ASC,  c.name ASC, a.starts_at ASC',
    'date'          => 'a.booked_on DESC, a.starts_at DESC',
    'date-asc'      => 'a.booked_on ASC,  a.starts_at ASC',
    'name'          => 'c.name ASC, a.booked_on DESC',
];

/** Human labels for the same list, so the page and the repo cannot drift apart. */
const APPT_SORT_LABELS = [
    'submitted'     => 'Submitted — newest day first, then A–Z',
    'submitted-asc' => 'Submitted — oldest day first, then A–Z',
    'date'          => 'Appointment date — latest first',
    'date-asc'      => 'Appointment date — earliest first',
    'name'          => 'Client name — A to Z',
];
/**
 * Appointment list with filters. Staff scoping is applied HERE, not in the
 * view, so a stylist cannot see another stylist's diary by any route.
 */
function repo_appointments(array $f = []): array
{
    $sql = 'SELECT a.*, c.ref AS client_ref, c.name AS client_name, c.phone AS client_phone,
                   s.ref AS staff_ref, s.name AS staff_name, s.job_title,
                   (SELECT GROUP_CONCAT(ai.service_name SEPARATOR " + ")
                      FROM appointment_items ai WHERE ai.appointment_id = a.id) AS service_names,
                   (SELECT cat.slug FROM appointment_items ai2
                      JOIN services sv ON sv.id = ai2.service_id
                      JOIN service_categories cat ON cat.id = sv.category_id
                     WHERE ai2.appointment_id = a.id LIMIT 1) AS category_slug
              FROM appointments a
              JOIN clients c ON c.id = a.client_id
         LEFT JOIN staff s ON s.id = a.staff_id
             WHERE 1 = 1';
    $p = [];

    if (!empty($f['staff_id']))  { $sql .= ' AND a.staff_id = ?';  $p[] = $f['staff_id']; }
    if (!empty($f['client_id'])) { $sql .= ' AND a.client_id = ?'; $p[] = $f['client_id']; }
    if (!empty($f['status']) && $f['status'] !== 'all') { $sql .= ' AND a.status = ?'; $p[] = $f['status']; }
    if (!empty($f['date']))      { $sql .= ' AND a.booked_on = ?'; $p[] = $f['date']; }
    if (!empty($f['from']))      { $sql .= ' AND a.booked_on >= ?'; $p[] = $f['from']; }
    if (!empty($f['to']))        { $sql .= ' AND a.booked_on <= ?'; $p[] = $f['to']; }
    if (!empty($f['upcoming']))  { $sql .= ' AND a.booked_on > ?';  $p[] = app_today(); }
    if (!empty($f['past']))      { $sql .= ' AND a.booked_on < ?';  $p[] = app_today(); }
    if (!empty($f['unpaid']))    { $sql .= ' AND a.status = "completed" AND a.payment_method = "unpaid"'; }
    if (!empty($f['q'])) {
        $sql .= ' AND (c.name LIKE ? OR a.ref LIKE ? OR c.phone LIKE ?)';
        $like = '%' . $f['q'] . '%';
        array_push($p, $like, $like, $like);
    }

    /* Ordering is chosen from a fixed list, never from the query string
       directly — an ORDER BY is the one place a filter cannot be bound as a
       parameter, so the value has to be one we wrote ourselves.

       The default groups by the DAY a booking arrived rather than its exact
       timestamp, which is what makes the alphabetical tiebreak useful: every
       booking submitted on the same day is listed together, A to Z. Sorting on
       the raw timestamp would make the name comparison unreachable, because no
       two submissions share a second. */
    $sql .= ' ORDER BY ' . (APPT_SORTS[$f['sort'] ?? ''] ?? APPT_SORTS['submitted']);
    if (!empty($f['limit'])) {
        $sql .= ' LIMIT ' . (int) $f['limit'];
        if (!empty($f['offset'])) $sql .= ' OFFSET ' . (int) $f['offset'];
    }
    return db_all($sql, $p);
}

/** Row count for the same filters, so the pager knows how many pages exist. */
function repo_appointments_count(array $f = []): int
{
    $sql = 'SELECT COUNT(*) FROM appointments a JOIN clients c ON c.id = a.client_id WHERE 1 = 1';
    $p = [];
    if (!empty($f['staff_id']))  { $sql .= ' AND a.staff_id = ?';  $p[] = $f['staff_id']; }
    if (!empty($f['client_id'])) { $sql .= ' AND a.client_id = ?'; $p[] = $f['client_id']; }
    if (!empty($f['status']) && $f['status'] !== 'all') { $sql .= ' AND a.status = ?'; $p[] = $f['status']; }
    if (!empty($f['date']))      { $sql .= ' AND a.booked_on = ?'; $p[] = $f['date']; }
    if (!empty($f['from']))      { $sql .= ' AND a.booked_on >= ?'; $p[] = $f['from']; }
    if (!empty($f['to']))        { $sql .= ' AND a.booked_on <= ?'; $p[] = $f['to']; }
    if (!empty($f['upcoming']))  { $sql .= ' AND a.booked_on > ?';  $p[] = app_today(); }
    if (!empty($f['past']))      { $sql .= ' AND a.booked_on < ?';  $p[] = app_today(); }
    if (!empty($f['unpaid']))    { $sql .= ' AND a.status = "completed" AND a.payment_method = "unpaid"'; }
    if (!empty($f['q'])) {
        $sql .= ' AND (c.name LIKE ? OR a.ref LIKE ? OR c.phone LIKE ?)';
        $like = '%' . $f['q'] . '%';
        array_push($p, $like, $like, $like);
    }
    return (int) db_val($sql, $p);
}

function repo_appointment_by_ref(string $ref): ?array
{
    $a = db_one(
        'SELECT a.*, c.ref AS client_ref, c.name AS client_name, c.phone AS client_phone,
                c.email AS client_email, s.ref AS staff_ref, s.name AS staff_name,
                s.job_title, s.commission_pct
           FROM appointments a
           JOIN clients c ON c.id = a.client_id
      LEFT JOIN staff s ON s.id = a.staff_id
          WHERE a.ref = ?', [$ref]);
    if (!$a) return null;

    $a['items'] = db_all(
        'SELECT * FROM appointment_items WHERE appointment_id = ? ORDER BY id', [$a['id']]);
    $a['consumes'] = db_all(
        'SELECT p.name, p.unit, sp.qty_used
           FROM appointment_items ai
           JOIN service_products sp ON sp.service_id = ai.service_id
           JOIN products p ON p.id = sp.product_id
          WHERE ai.appointment_id = ?', [$a['id']]);
    return $a;
}

/** Counts per status for the tab bar. */
function repo_appointment_status_counts(?int $staffId = null): array
{
    $sql = 'SELECT status, COUNT(*) n FROM appointments WHERE 1 = 1';
    $p = [];
    if ($staffId) { $sql .= ' AND staff_id = ?'; $p[] = $staffId; }
    $rows = db_all($sql . ' GROUP BY status', $p);

    $out = ['all' => 0, 'pending' => 0, 'confirmed' => 0,
            'completed' => 0, 'cancelled' => 0, 'no-show' => 0];
    foreach ($rows as $r) {
        $out[$r['status']] = (int) $r['n'];
        $out['all'] += (int) $r['n'];
    }
    return $out;
}

/* ================================================================ revenue */

/** Gross takings between two dates: services + add-ons + tips, plus retail. */
function repo_revenue_between(string $from, string $to): array
{
    $appt = db_one(
        'SELECT COUNT(*) n, COALESCE(SUM(grand_total), 0) gross,
                COALESCE(SUM(services_total), 0) services,
                COALESCE(SUM(addons_total), 0) addons,
                COALESCE(SUM(tip_total), 0) tips
           FROM appointments
          WHERE status = "completed" AND booked_on BETWEEN ? AND ?', [$from, $to]);

    $retail = db_one(
        'SELECT COUNT(*) n, COALESCE(SUM(total), 0) gross
           FROM product_sales WHERE sold_on BETWEEN ? AND ?', [$from, $to]);

    return [
        'appointments'   => (int) $appt['n'],
        'services'       => (float) $appt['services'],
        'addons'         => (float) $appt['addons'],
        'tips'           => (float) $appt['tips'],
        'appt_gross'     => (float) $appt['gross'],
        'retail_count'   => (int) $retail['n'],
        'retail_gross'   => (float) $retail['gross'],
        'gross'          => (float) $appt['gross'] + (float) $retail['gross'],
    ];
}

function repo_expenses_between(string $from, string $to): float
{
    return (float) db_val(
        'SELECT COALESCE(SUM(amount), 0) FROM expenses
          WHERE deleted_at IS NULL AND spent_on BETWEEN ? AND ?', [$from, $to]);
}

/**
 * Daily series for the trend chart. Missing days are returned as zero.
 *
 * Counter sales belong here. This once summed appointments only, so the chart
 * footed to 907,000 while the tile directly above it read 978,000 — the same
 * screen disagreeing with itself by exactly the month's retail takings, with
 * nothing to tell the owner which number to believe.
 */
function repo_revenue_daily(string $from, string $to): array
{
    $byDay = [];

    foreach (db_all(
        'SELECT booked_on d, COALESCE(SUM(grand_total), 0) gross
           FROM appointments
          WHERE status = "completed" AND booked_on BETWEEN ? AND ?
          GROUP BY booked_on ORDER BY booked_on', [$from, $to]) as $r) {
        $byDay[$r['d']] = (float) $r['gross'];
    }

    foreach (db_all(
        'SELECT sold_on d, COALESCE(SUM(total), 0) gross
           FROM product_sales WHERE sold_on BETWEEN ? AND ?
          GROUP BY sold_on ORDER BY sold_on', [$from, $to]) as $r) {
        $byDay[$r['d']] = ($byDay[$r['d']] ?? 0.0) + (float) $r['gross'];
    }

    $out = [];
    for ($d = strtotime($from); $d <= strtotime($to); $d = strtotime('+1 day', $d)) {
        $iso = date('Y-m-d', $d);
        $out[] = ['date' => $iso, 'label' => date('j M', $d), 'gross' => $byDay[$iso] ?? 0.0];
    }
    return $out;
}

/** Monthly series, gross against expenses. */
function repo_revenue_monthly(int $months = 12): array
{
    $out = [];
    for ($i = $months - 1; $i >= 0; $i--) {
        $ts    = strtotime(app_today() . " -$i month");
        $from  = date('Y-m-01', $ts);
        $to    = date('Y-m-t', $ts);
        $out[] = [
            'label'    => date('M', $ts),
            'from'     => $from,
            'gross'    => repo_revenue_between($from, $to)['gross'],
            'expenses' => repo_expenses_between($from, $to),
        ];
    }
    return $out;
}

function repo_revenue_by_category(string $from, string $to): array
{
    return db_all(
        'SELECT c.slug, c.name, c.color,
                COUNT(DISTINCT a.id) bookings,
                COALESCE(SUM(ai.line_total), 0) gross
           FROM appointment_items ai
           JOIN appointments a ON a.id = ai.appointment_id
           JOIN services s ON s.id = ai.service_id
           JOIN service_categories c ON c.id = s.category_id
          WHERE a.status = "completed" AND a.booked_on BETWEEN ? AND ?
          GROUP BY c.id ORDER BY gross DESC', [$from, $to]);
}

/**
 * What each stylist earned, and what they are owed on it.
 *
 * `commissionable` follows the commission_basis setting. The settings page
 * tells the owner in as many words that "the commission report recalculates
 * from this basis every time it loads" — it did not, and every basis paid out
 * on service price alone. A stylist on "service + add-ons + tips" was quietly
 * short-changed on every appointment that carried either.
 *
 * The expression is chosen from a fixed map, never interpolated from input.
 */
function repo_commission_basis(): string
{
    return match (setting('commission_basis', 'service_only')) {
        'service_addons'      => 'a.services_total + a.addons_total',
        'service_addons_tips' => 'a.services_total + a.addons_total + a.tip_total',
        default               => 'a.services_total',
    };
}

function repo_revenue_by_staff(string $from, string $to): array
{
    $basis = repo_commission_basis();
    return db_all(
        "SELECT s.id, s.ref, s.name, s.commission_pct, s.rating,
                COUNT(*) appts, COALESCE(SUM(a.grand_total), 0) gross,
                COALESCE(SUM($basis), 0) commissionable
           FROM appointments a
           JOIN staff s ON s.id = a.staff_id
          WHERE a.status = \"completed\" AND a.booked_on BETWEEN ? AND ?
          GROUP BY s.id ORDER BY gross DESC", [$from, $to]);
}

function repo_revenue_by_payment(string $from, string $to): array
{
    $colors = ['momo' => '#C9A227', 'om' => '#E91E8C', 'card' => '#2F6FED', 'cash' => '#12A150'];
    $labels = ['momo' => 'MTN MoMo', 'om' => 'Orange Money', 'card' => 'Card', 'cash' => 'Cash'];

    $rows = db_all(
        'SELECT payment_method m, COUNT(*) txns, COALESCE(SUM(grand_total),0) amount
           FROM appointments
          WHERE status = "completed" AND payment_method <> "unpaid"
            AND booked_on BETWEEN ? AND ?
          GROUP BY payment_method', [$from, $to]);

    /* fold counter sales into the same mix */
    foreach (db_all(
        'SELECT payment_method m, COUNT(*) txns, COALESCE(SUM(total),0) amount
           FROM product_sales WHERE sold_on BETWEEN ? AND ? GROUP BY payment_method',
        [$from, $to]) as $r) {
        $found = false;
        foreach ($rows as &$x) {
            if ($x['m'] === $r['m']) { $x['txns'] += $r['txns']; $x['amount'] += $r['amount']; $found = true; break; }
        }
        unset($x);
        if (!$found) $rows[] = $r;
    }

    /* Gold and green must never sit adjacent — see the palette contract in
       charts.js. Fixing the order here guarantees that. */
    $order = ['momo', 'om', 'card', 'cash'];
    usort($rows, fn($a, $b) => array_search($a['m'], $order) <=> array_search($b['m'], $order));

    return array_map(fn($r) => [
        'key'    => $r['m'],
        'label'  => $labels[$r['m']] ?? ucfirst($r['m']),
        'color'  => $colors[$r['m']] ?? '#77777F',
        'txns'   => (int) $r['txns'],
        'amount' => (float) $r['amount'],
    ], $rows);
}

function repo_top_services(string $from, string $to, int $limit = 6): array
{
    return db_all(
        'SELECT ai.service_name name, COUNT(*) bookings, SUM(ai.line_total) gross
           FROM appointment_items ai
           JOIN appointments a ON a.id = ai.appointment_id
          WHERE a.status = "completed" AND a.booked_on BETWEEN ? AND ?
          GROUP BY ai.service_name ORDER BY gross DESC LIMIT ' . max(1, $limit),
        [$from, $to]);
}

function repo_expense_breakdown(string $from, string $to): array
{
    return db_all(
        'SELECT ec.name, ec.color, COALESCE(SUM(e.amount), 0) amount
           FROM expenses e JOIN expense_categories ec ON ec.id = e.category_id
          WHERE e.deleted_at IS NULL AND e.spent_on BETWEEN ? AND ?
          GROUP BY ec.id ORDER BY amount DESC', [$from, $to]);
}

/* ============================================================== inventory */

function repo_products(bool $retailOnly = false): array
{
    $sql = 'SELECT p.*, c.short_name AS category_short, c.color,
                   (SELECT COUNT(*) FROM service_products sp WHERE sp.product_id = p.id) AS used_by
              FROM products p LEFT JOIN service_categories c ON c.id = p.category_id
             WHERE p.deleted_at IS NULL';
    if ($retailOnly) $sql .= ' AND p.is_retail = 1 AND p.retail_price > 0';
    return db_all($sql . ' ORDER BY p.ref');
}

function repo_low_stock(): array
{
    return db_all(
        'SELECT * FROM products
          WHERE deleted_at IS NULL AND qty_on_hand <= reorder_level
          ORDER BY (qty_on_hand / GREATEST(reorder_level, 1)), ref');
}

function repo_stock_value(): float
{
    return (float) db_val(
        'SELECT COALESCE(SUM(qty_on_hand * unit_cost), 0) FROM products WHERE deleted_at IS NULL');
}

function repo_stock_movements(int $limit = 40): array
{
    return db_all(
        'SELECT m.*, p.name AS product_name, s.name AS staff_name
           FROM stock_movements m
           JOIN products p ON p.id = m.product_id
      LEFT JOIN staff s ON s.id = m.staff_id
          ORDER BY m.moved_on DESC, m.id DESC LIMIT ' . max(1, $limit));
}

/* ================================================================ clients */

/** Client list with lifetime value derived from completed work. */
function repo_clients(array $f = []): array
{
    $sql = 'SELECT c.*, cat.short_name AS fav_category, st.name AS fav_staff_name,
                   COALESCE(agg.visits, 0)   AS visits,
                   COALESCE(agg.spend, 0)    AS spend,
                   agg.last_visit
              FROM clients c
         LEFT JOIN service_categories cat ON cat.id = c.fav_category_id
         LEFT JOIN staff st ON st.id = c.fav_staff_id
         LEFT JOIN (
                SELECT client_id, COUNT(*) visits, SUM(grand_total) spend,
                       MAX(booked_on) last_visit
                  FROM appointments WHERE status = "completed" GROUP BY client_id
              ) agg ON agg.client_id = c.id
             WHERE c.deleted_at IS NULL';
    $p = [];

    /* A stylist only ever sees clients they have personally served. */
    if (!empty($f['staff_id'])) {
        $sql .= ' AND EXISTS (SELECT 1 FROM appointments a
                               WHERE a.client_id = c.id AND a.staff_id = ?)';
        $p[] = $f['staff_id'];
    }
    if (!empty($f['q'])) {
        $sql .= ' AND (c.name LIKE ? OR c.phone LIKE ? OR c.email LIKE ?)';
        $like = '%' . $f['q'] . '%';
        array_push($p, $like, $like, $like);
    }
    return db_all($sql . ' ORDER BY spend DESC, c.name', $p);
}

/** VIP / Regular / New, derived rather than stored so it can never go stale. */
function client_tier(array $c): string
{
    if ((int) $c['visits'] >= 12 || (float) $c['spend'] >= 200000) return 'VIP';
    if ((int) $c['visits'] <= 3) return 'New';
    return 'Regular';
}

function repo_client_by_ref(string $ref): ?array
{
    $rows = repo_clients([]);
    foreach ($rows as $r) if ($r['ref'] === $ref) return $r;
    return null;
}

/* ============================================================== dashboard */

function repo_dashboard_stats(): array
{
    $today = app_today();
    $weekFrom  = date('Y-m-d', strtotime($today . ' -6 days'));
    $monthFrom = date('Y-m-01', strtotime($today));
    $monthTo   = date('Y-m-t', strtotime($today));

    $prevDay   = date('Y-m-d', strtotime($today . ' -1 day'));
    $prevWeekF = date('Y-m-d', strtotime($today . ' -13 days'));
    $prevWeekT = date('Y-m-d', strtotime($today . ' -7 days'));
    $prevMonF  = date('Y-m-01', strtotime($today . ' -1 month'));
    $prevMonT  = date('Y-m-t',  strtotime($today . ' -1 month'));

    return [
        'today'        => repo_revenue_between($today, $today),
        'today_prev'   => repo_revenue_between($prevDay, $prevDay),
        'week'         => repo_revenue_between($weekFrom, $today),
        'week_prev'    => repo_revenue_between($prevWeekF, $prevWeekT),
        'month'        => repo_revenue_between($monthFrom, $monthTo),
        'month_prev'   => repo_revenue_between($prevMonF, $prevMonT),
        'month_expenses' => repo_expenses_between($monthFrom, $monthTo),
        'month_from'   => $monthFrom,
        'month_to'     => $monthTo,
        'new_clients_week'  => (int) db_val(
            'SELECT COUNT(*) FROM clients WHERE joined_on BETWEEN ? AND ?', [$weekFrom, $today]),
        'new_clients_month' => (int) db_val(
            'SELECT COUNT(*) FROM clients WHERE joined_on BETWEEN ? AND ?', [$monthFrom, $monthTo]),
        'outstanding' => db_one(
            'SELECT COUNT(*) n, COALESCE(SUM(grand_total), 0) amount FROM appointments
              WHERE status = "completed" AND payment_method = "unpaid"'),
        'low_stock'   => count(repo_low_stock()),
    ];
}

/** Average ticket and repeat rate — genuinely computed, not asserted. */
function repo_business_health(string $from, string $to): array
{
    $avg = (float) db_val(
        'SELECT COALESCE(AVG(grand_total), 0) FROM appointments
          WHERE status = "completed" AND booked_on BETWEEN ? AND ?', [$from, $to]);

    $total = (int) db_val('SELECT COUNT(*) FROM clients WHERE deleted_at IS NULL');
    $repeat = (int) db_val(
        'SELECT COUNT(*) FROM (SELECT client_id FROM appointments
          WHERE status = "completed" GROUP BY client_id HAVING COUNT(*) > 1) x');

    $cancelled = (int) db_val(
        'SELECT COUNT(*) FROM appointments
          WHERE booked_on BETWEEN ? AND ? AND status IN ("cancelled","no-show")', [$from, $to]);
    $all = (int) db_val(
        'SELECT COUNT(*) FROM appointments WHERE booked_on BETWEEN ? AND ?', [$from, $to]);

    return [
        'avg_ticket'    => $avg,
        'returning_pct' => $total ? round($repeat / $total * 100) : 0,
        'cancel_pct'    => $all ? round($cancelled / $all * 100, 1) : 0,
    ];
}
