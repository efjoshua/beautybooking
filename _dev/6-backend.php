<?php
/**
 * Backend smoke tests — schema, helpers, auth, permissions.
 *   php _dev/6-backend.php
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('This script runs from the command line only.');
}


require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/auth.php';

$pass = 0; $fail = 0;
function ok(string $name, bool $cond, string $detail = ''): void {
    global $pass, $fail;
    if ($cond) { $pass++; echo "ok    $name\n"; }
    else       { $fail++; echo "FAIL  $name" . ($detail ? "  — $detail" : '') . "\n"; }
}

echo "--- connection & schema ---\n";
ok('database reachable', db_val('SELECT 1') == 1);
/* Naming the tables beats counting them: a bare count tells you something
   moved but not what, and it has to be edited on every migration anyway. */
$have = array_column(db_all(
    'SELECT table_name AS t FROM information_schema.tables WHERE table_schema = ?',
    [DB_NAME]), 't');
$want = ['appointments','appointment_items','clients','staff','services',
         'service_categories','service_products','products','stock_movements',
         'product_sales','product_sale_items','expenses','expense_categories',
         'users','roles','permissions','role_permissions','settings',
         'business_hours','staff_hours','staff_categories','contact_messages',
         'faqs','gallery','offers','offer_items','reviews',
         'login_attempts','audit_log','migrations','payments'];
$missing = array_diff($want, $have);
ok('every expected table exists', !$missing,
   'missing: ' . implode(', ', $missing) . ' (found ' . count($have) . ' tables)');
$files = glob(__DIR__ . '/../db/migrations/*.sql');
ok('every migration file is applied',
   (int) db_val('SELECT COUNT(*) FROM migrations') === count($files),
   count($files) . ' files on disk');

echo "\n--- referential integrity ---\n";
ok('no orphan appointment items', (int) db_val(
    'SELECT COUNT(*) FROM appointment_items ai
      LEFT JOIN appointments a ON a.id = ai.appointment_id WHERE a.id IS NULL') === 0);
ok('no appointment without a client', (int) db_val(
    'SELECT COUNT(*) FROM appointments WHERE client_id IS NULL') === 0);
ok('item totals match appointment services_total', (int) db_val(
    'SELECT COUNT(*) FROM (
        SELECT a.id, a.services_total AS st, SUM(ai.line_total) AS line_sum
          FROM appointments a
          JOIN appointment_items ai ON ai.appointment_id = a.id
         GROUP BY a.id, a.services_total
        HAVING ABS(line_sum - st) > 0.01
     ) x') === 0);
ok('grand_total = services + addons + tip', (int) db_val(
    'SELECT COUNT(*) FROM appointments
      WHERE ABS(grand_total - (services_total + addons_total + tip_total)) > 0.01') === 0);
ok('no stylist double-booked', (int) db_val(
    'SELECT COUNT(*) FROM appointments a1
       JOIN appointments a2 ON a1.staff_id = a2.staff_id AND a1.booked_on = a2.booked_on
        AND a1.id < a2.id AND a1.starts_at < a2.ends_at AND a2.starts_at < a1.ends_at
      WHERE a1.status <> "cancelled" AND a2.status <> "cancelled"') === 0);

echo "\n--- stock ledger reconciles ---\n";
/* qty_on_hand is a cache; stock_movements is the truth. Disagreement means
   some write path updated one without the other. */
$drift = db_all(
    'SELECT p.ref, p.qty_on_hand AS cached, COALESCE(SUM(m.qty), 0) AS ledger
       FROM products p LEFT JOIN stock_movements m ON m.product_id = p.id
      WHERE p.deleted_at IS NULL
      GROUP BY p.id, p.qty_on_hand HAVING cached <> ledger');
ok('cached quantity matches the movement ledger', count($drift) === 0,
   count($drift) . ' drifted');
ok('no product has negative stock', (int) db_val(
    'SELECT COUNT(*) FROM products WHERE deleted_at IS NULL AND qty_on_hand < 0') === 0);

echo "\n--- helpers ---\n";
ok('money()',        money(25000) === '25,000 FCFA', money(25000));
ok('money_short() M', money_short(3145000) === '3.1M', money_short(3145000));
ok('money_short() k', money_short(25000) === '25k', money_short(25000));
ok('duration()',     duration(240) === '4h' && duration(90) === '1h 30min', duration(90));
ok('initials() accents', initials('Aïcha Ngo') === 'AN', initials('Aïcha Ngo'));
ok('e() escapes',    e('<b>&"') === '&lt;b&gt;&amp;&quot;');
ok('setting() reads DB', setting('salon_name') === 'Princess Beauty Salon', (string) setting('salon_name'));
ok('next_ref()',     str_starts_with(next_ref('expenses', 'EX-'), 'EX-'), next_ref('expenses', 'EX-'));
ok('generate_booking_ref unique', (bool) preg_match('/^PBS-\w+$/', generate_booking_ref()));

echo "\n--- passwords ---\n";
$owner = db_one('SELECT * FROM users WHERE email = ?', ['aicha@princessbeautysalon.cm']);
ok('owner account exists', $owner !== null);
ok('seeded password verifies', $owner && password_verify('demo1234', $owner['password_hash']));
ok('wrong password rejected', $owner && !password_verify('wrong', $owner['password_hash']));
ok('invited account has no hash', db_val(
    'SELECT password_hash FROM users WHERE status = "invited" LIMIT 1') === null);

echo "\n--- roles & permissions ---\n";
$ownerPerms = db_run(
    'SELECT p.slug FROM permissions p
       JOIN role_permissions rp ON rp.permission_id = p.id
       JOIN roles r ON r.id = rp.role_id WHERE r.slug = "owner"')->fetchAll(PDO::FETCH_COLUMN);
ok('owner has every area', count($ownerPerms) === 13, count($ownerPerms) . ' perms');

$stylistPerms = db_run(
    'SELECT p.slug FROM permissions p
       JOIN role_permissions rp ON rp.permission_id = p.id
       JOIN roles r ON r.id = rp.role_id WHERE r.slug = "staff"')->fetchAll(PDO::FETCH_COLUMN);
ok('stylist cannot reach revenue',   !in_array('revenue', $stylistPerms, true));
ok('stylist cannot reach inventory', !in_array('inventory', $stylistPerms, true));
ok('stylist cannot reach users',     !in_array('users', $stylistPerms, true));
ok('stylist CAN reach own schedule',  in_array('my-schedule', $stylistPerms, true));

$recep = db_run(
    'SELECT p.slug FROM permissions p
       JOIN role_permissions rp ON rp.permission_id = p.id
       JOIN roles r ON r.id = rp.role_id WHERE r.slug = "receptionist"')->fetchAll(PDO::FETCH_COLUMN);
ok('receptionist books but sees no money',
    in_array('appointments', $recep, true) && !in_array('revenue', $recep, true));

echo "\n--- reports derive from real rows ---\n";
$aug = db_one(
    'SELECT COUNT(*) n, COALESCE(SUM(grand_total),0) total FROM appointments
      WHERE status = "completed" AND booked_on BETWEEN "2026-08-01" AND "2026-08-31"');
ok('August revenue is computed', (float) $aug['total'] > 0,
    money((float) $aug['total']) . ' from ' . $aug['n'] . ' appointments');
$jun = (float) db_val('SELECT SUM(grand_total) FROM appointments
     WHERE status="completed" AND booked_on BETWEEN "2026-06-01" AND "2026-06-30"');
ok('June has a full month of trading', $jun > 1000000, money($jun));

$byCat = db_all(
    'SELECT c.name, SUM(ai.line_total) gross FROM appointment_items ai
       JOIN appointments a ON a.id = ai.appointment_id
       JOIN services s ON s.id = ai.service_id
       JOIN service_categories c ON c.id = s.category_id
      WHERE a.status = "completed" GROUP BY c.id');
ok('revenue splits across all 5 categories', count($byCat) === 5, count($byCat) . ' categories');

echo "\n=== $pass passed, $fail failed ===\n";
exit($fail ? 1 : 0);
