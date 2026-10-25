<?php
/**
 * Unit tests for the PHP layer.
 *
 * Exercises every function in includes/ directly, with the edge cases that
 * actually break things: zero, negative, null, unicode, empty sets, boundary
 * values, and dates that fall outside the seeded range.
 *
 *   php _dev/8-unit.php
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only.'); }

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/repo.php';
require_once __DIR__ . '/../includes/booking.php';
require_once __DIR__ . '/../includes/auth.php';

$pass = 0; $fail = 0; $group = '';
function grp(string $g): void { global $group; $group = $g; echo "\n--- $g ---\n"; }
function is_(string $what, $actual, $expected): void {
    global $pass, $fail;
    $ok = $actual === $expected;
    if ($ok) { $pass++; echo "ok    $what\n"; }
    else {
        $fail++;
        echo "FAIL  $what\n";
        echo "        expected: " . var_export($expected, true) . "\n";
        echo "        actual:   " . var_export($actual, true) . "\n";
    }
}
function ok_(string $what, bool $cond, string $detail = ''): void {
    global $pass, $fail;
    if ($cond) { $pass++; echo "ok    $what\n"; }
    else { $fail++; echo "FAIL  $what" . ($detail ? " — $detail" : '') . "\n"; }
}
function throws_(string $what, callable $fn, string $expectClass = 'Throwable'): void {
    global $pass, $fail;
    try { $fn(); $fail++; echo "FAIL  $what — no exception thrown\n"; }
    catch (Throwable $e) {
        if ($e instanceof $expectClass) { $pass++; echo "ok    $what\n"; }
        else { $fail++; echo "FAIL  $what — got " . get_class($e) . "\n"; }
    }
}

/* ===================================================================== */
grp('helpers: money');
is_('money(0)',            money(0),            '0 FCFA');
is_('money(25000)',        money(25000),        '25,000 FCFA');
is_('money negative',      money(-1500),        '-1,500 FCFA');
is_('money rounds .5 up',  money(1000.5),       '1,001 FCFA');
is_('money from string',   money('12000'),      '12,000 FCFA');
is_('money bare',          money(25000, true),  '25,000');
is_('money huge',          money(999999999),    '999,999,999 FCFA');
is_('money null-ish',      money(null),         '0 FCFA');

grp('helpers: money_short');
is_('999',      money_short(999),      '999');
is_('1000',     money_short(1000),     '1k');
is_('1499',     money_short(1499),     '1k');
is_('25000',    money_short(25000),    '25k');
is_('999999',   money_short(999999),   '1000k');
is_('2000000',  money_short(2000000),  '2.0M');
is_('100000000',money_short(100000000),'100M');
is_('1000000',  money_short(1000000),  '1.0M');
is_('3145000',  money_short(3145000),  '3.1M');
is_('10000000', money_short(10000000), '10M');
is_('zero',     money_short(0),        '0');

grp('helpers: duration');
is_('0',    duration(0),   '0min');
is_('5',    duration(5),   '5min');
is_('60',   duration(60),  '1h');
is_('90',   duration(90),  '1h 30min');
is_('240',  duration(240), '4h');
is_('1440', duration(1440),'24h');

grp('helpers: initials');
is_('two words',   initials('Aïcha Ngo'),          'AN');
is_('unicode',     initials('Émilienne Tchana'),   'ÉT');
is_('one word',    initials('Blessing'),           'B');
is_('three words', initials('Anne Marie Ngo'),     'AM');
is_('extra space', initials('  Grace   Mbah  '),   'GM');
is_('empty',       initials(''),                   '?');

grp('helpers: escaping');
is_('script tag', e('<script>alert(1)</script>'),
    '&lt;script&gt;alert(1)&lt;/script&gt;');
is_('quotes',     e('a"b\'c'),   'a&quot;b&#039;c');
is_('ampersand',  e('Hair & Nails'), 'Hair &amp; Nails');
is_('null',       e(null),       '');
is_('unicode kept', e('Aïcha'),  'Aïcha');

grp('helpers: dates');
is_('fmt_day',        fmt_day('2026-08-20'),      'Thu, 20 Aug');
is_('fmt_date_long',  fmt_date_long('2026-08-20'),'Thursday, 20 August 2026');
is_('fmt_time',       fmt_time('14:30:00'),       '14:30');
is_('fmt_day null',   fmt_day(null),              '—');
is_('fmt_time null',  fmt_time(null),             '—');
is_('fmt_day garbage',fmt_day('not-a-date'),      '—');

grp('helpers: references');
$r1 = generate_booking_ref(); $r2 = generate_booking_ref();
ok_('booking ref format',  (bool) preg_match('/^PBS-[A-Z0-9]+$/', $r1), $r1);
ok_('booking refs differ', $r1 !== $r2);
ok_('booking ref unused',  !db_val('SELECT 1 FROM appointments WHERE ref = ?', [$r1]));
ok_('next_ref increments', next_ref('expenses', 'EX-', 3) > 'EX-000');
is_('next_ref pads',       strlen(next_ref('clients', 'CL-', 3)), 6);

grp('db helpers');
is_('db_val scalar', (int) db_val('SELECT 1 + 1'), 2);
is_('db_one missing', db_one('SELECT * FROM clients WHERE id = -1'), null);
is_('db_all empty',   db_all('SELECT * FROM clients WHERE id = -1'), []);
[$sql, $params] = db_in([1, 2, 3]);
is_('db_in sql',    $sql, '(?, ?, ?)');
is_('db_in params', $params, [1, 2, 3]);
[$sql2, $p2] = db_in([]);
is_('db_in empty is never-true', $sql2, '(NULL)');
is_('db_in empty params', $p2, []);
ok_('db_in([]) selects nothing',
    (int) db_val('SELECT COUNT(*) FROM clients WHERE id IN (NULL)') === 0);

grp('db: prepared statements resist injection');
$evil = "'; DROP TABLE clients; --";
is_('injection returns nothing', db_all('SELECT * FROM clients WHERE name = ?', [$evil]), []);
ok_('clients table survived', (int) db_val('SELECT COUNT(*) FROM clients') > 0);
$evil2 = "%' OR '1'='1";
$rows = db_all('SELECT * FROM clients WHERE name LIKE ?', ['%' . $evil2 . '%']);
is_('LIKE injection matches nothing', count($rows), 0);

grp('booking: service resolution');
$r = booking_resolve_services(['SV-03']);
is_('one service duration', $r['duration'], 90);
is_('one service total',    (float) $r['total'], 12000.0);
is_('categories',           $r['categories'], ['hair']);
$r2 = booking_resolve_services(['SV-03', 'SV-11']);
is_('two services duration', $r2['duration'], 135);
is_('two categories',        count($r2['categories']), 2);
$r3 = booking_resolve_services(['NOPE-99']);
is_('unknown ref ignored',   $r3['services'], []);
is_('unknown ref duration',  $r3['duration'], 0);
$r4 = booking_resolve_services([]);
is_('empty list',            $r4['duration'], 0);

grp('booking: time maths');
is_('t2m 00:00', t2m('00:00'), 0);
is_('t2m 09:30', t2m('09:30'), 570);
is_('t2m 23:59', t2m('23:59'), 1439);
is_('t2m with seconds', t2m('14:30:00'), 870);
is_('m2t 0',    m2t(0),    '00:00');
is_('m2t 570',  m2t(570),  '09:30');
is_('m2t wraps midnight', m2t(1500), '01:00');

grp('booking: availability rules');
$sat = booking_slots('2026-09-05', ['SV-06']);     // Saturday, 4h braids
ok_('saturday has slots', count($sat) > 0, count($sat) . ' slots');
$last = end($sat);
ok_('last slot leaves room before closing',
    t2m($last['time']) + 240 <= t2m((string) db_val(
        'SELECT closes_at FROM business_hours WHERE weekday = 5')),
    'last=' . $last['time']);

$sunEsth = booking_slots('2026-09-06', ['SV-18'], 'ST-04');  // Sun, Mon-Fri only
is_('Sunday for a Mon-Fri stylist', count($sunEsth), 0);

$impossible = booking_slots('2026-09-05', ['SV-06', 'SV-13']); // braids + nails
is_('no stylist covers braids+nails', count($impossible), 0);

$unknown = booking_slots('2026-09-05', ['NOPE']);
is_('unknown service yields nothing', count($unknown), 0);

/* A service longer than the whole trading day can never fit. */
$open  = (int) t2m((string) db_val('SELECT opens_at FROM business_hours WHERE weekday = 0'));
$close = (int) t2m((string) db_val('SELECT closes_at FROM business_hours WHERE weekday = 0'));
ok_('Monday trading window is sane', $close > $open, "$open..$close");

grp('booking: creation validation');
$base = ['services' => ['SV-11'], 'staff' => 'any', 'date' => '2026-09-10',
         'time' => '10:00', 'name' => 'Unit Test', 'phone' => '+237 690 11 22 33'];
$cases = [
    'no services'      => ['services' => []],
    'bad date format'  => ['date' => '10/09/2026'],
    'date in the past' => ['date' => '2020-01-01'],
    'beyond window'    => ['date' => date('Y-m-d', strtotime('+400 day'))],
    'bad time format'  => ['time' => '25:99'],
    'empty name'       => ['name' => '   '],
    'short phone'      => ['phone' => '12'],
    'bad email'        => ['email' => 'not-an-email'],
    'unknown service'  => ['services' => ['NOPE-1']],
];
foreach ($cases as $label => $override) {
    $res = booking_create(array_merge($base, $override));
    ok_("rejected: $label", $res['ok'] === false, json_encode($res));
}

grp('booking: happy path + double-book guard');
$day = date('Y-m-d', strtotime('+9 day'));
$slots = booking_slots($day, ['SV-11']);
$free = null;
foreach ($slots as $s) if ($s['available']) { $free = $s; break; }
ok_('a free slot exists to test with', $free !== null);

if ($free) {
    $made = booking_create(array_merge($base, ['date' => $day, 'time' => $free['time']]));
    ok_('booking created', !empty($made['ok']), json_encode($made));
    $ref = $made['ref'] ?? '';

    if ($ref) {
        $a = repo_appointment_by_ref($ref);
        ok_('row exists',            $a !== null);
        is_('status is pending',     $a['status'], 'pending');
        is_('payment unpaid',        $a['payment_method'], 'unpaid');
        is_('one line item',         count($a['items']), 1);
        is_('price frozen on item',  (float) $a['items'][0]['unit_price'], 5000.0);
        is_('totals add up',
            (float) $a['grand_total'],
            (float) $a['services_total'] + (float) $a['addons_total'] + (float) $a['tip_total']);
        ok_('a stylist was assigned', $a['staff_id'] !== null);

        /* the same stylist cannot be booked into the same minute twice */
        $clash = booking_create(array_merge($base, [
            'date' => $day, 'time' => $free['time'],
            'staff' => (string) db_val('SELECT ref FROM staff WHERE id = ?', [$a['staff_id']]),
        ]));
        ok_('double-booking refused', $clash['ok'] === false, json_encode($clash));

        /* lookup requires the matching phone */
        ok_('lookup with right phone',  booking_lookup($ref, '+237 690 11 22 33') !== null);
        ok_('lookup with wrong phone',  booking_lookup($ref, '+237 600 00 00 00') === null);
        ok_('lookup with junk phone',   booking_lookup($ref, 'abc') === null);
        ok_('lookup unknown ref',       booking_lookup('PBS-0000', '+237 690 11 22 33') === null);

        /* cancel, then confirm it cannot be cancelled twice */
        $c1 = booking_cancel($ref, '+237 690 11 22 33');
        ok_('cancel succeeds', !empty($c1['ok']), json_encode($c1));
        is_('status now cancelled',
            (string) db_val('SELECT status FROM appointments WHERE ref = ?', [$ref]), 'cancelled');
        $c2 = booking_cancel($ref, '+237 690 11 22 33');
        ok_('second cancel refused', $c2['ok'] === false);

        /* clean up so repeated runs stay deterministic */
        db_run('DELETE FROM appointment_items WHERE appointment_id = ?', [$a['id']]);
        db_run('DELETE FROM appointments WHERE id = ?', [$a['id']]);
        db_run('DELETE FROM clients WHERE phone = ? AND name = ?',
               ['+237 690 11 22 33', 'Unit Test']);
    }
}

grp('repo: reads');
ok_('categories',      count(repo_categories()) === 5);
ok_('public services', count(repo_services()) === 24);
ok_('one category',    count(repo_services('nails')) === 5, (string) count(repo_services('nails')));
ok_('unknown category returns none', count(repo_services('nope')) === 0);
ok_('staff',           count(repo_staff()) >= 6);
ok_('service by ref',  repo_service_by_ref('SV-01') !== null);
ok_('unknown ref',     repo_service_by_ref('SV-99') === null);
ok_('braids stylists', count(repo_staff_for_categories(['braids'])) === 2);
ok_('braids+nails',    count(repo_staff_for_categories(['braids', 'nails'])) === 0);
ok_('empty list falls back to all', count(repo_staff_for_categories([])) >= 6);
/* The gallery mirrors the filesystem, so the count is whatever was dropped in
   assets/img/. What must hold is that every row still points at a real file. */
$gal = repo_gallery();
ok_('gallery has rows', count($gal) > 0, count($gal) . ' photo(s)');
ok_('every gallery row resolves to a file on disk',
    !array_filter($gal, fn($g) => !is_file(APP_ROOT . '/' . $g['image_path'])));
ok_('gallery folders', count(repo_gallery_folders()) > 0);
ok_('folder filter narrows', count(repo_gallery('makeup')) < count($gal));
ok_('faqs',            count(repo_faqs()) === 13);
ok_('offers have items', !empty(repo_offers()[0]['items']));
ok_('business hours',  count(repo_business_hours()) === 7);

grp('repo: revenue maths');
$rev = repo_revenue_between('2026-06-01', '2026-06-30');
ok_('June has revenue', $rev['gross'] > 0, money($rev['gross']));
is_('gross = appointments + retail',
    round($rev['gross'], 2), round($rev['appt_gross'] + $rev['retail_gross'], 2));
is_('appt gross = services + addons + tips',
    round($rev['appt_gross'], 2),
    round($rev['services'] + $rev['addons'] + $rev['tips'], 2));

$empty = repo_revenue_between('2019-01-01', '2019-01-31');
is_('empty period gross',  $empty['gross'], 0.0);
is_('empty period count',  $empty['appointments'], 0);

$reversed = repo_revenue_between('2026-06-30', '2026-06-01');   // from > to
is_('reversed range returns nothing', $reversed['appointments'], 0);

$daily = repo_revenue_daily('2026-06-01', '2026-06-07');
is_('daily fills every day', count($daily), 7);
ok_('daily labels present', !empty($daily[0]['label']));

$byCat = repo_revenue_by_category('2026-06-01', '2026-08-31');
is_('all five categories earn', count($byCat), 5);
$catSum = array_sum(array_map(fn($c) => (float) $c['gross'], $byCat));
$svcSum = (float) db_val(
    'SELECT COALESCE(SUM(ai.line_total),0) FROM appointment_items ai
       JOIN appointments a ON a.id = ai.appointment_id
      WHERE a.status = "completed" AND a.booked_on BETWEEN ? AND ?',
    ['2026-06-01', '2026-08-31']);
is_('category split sums to service revenue', round($catSum, 2), round($svcSum, 2));

$byPay = repo_revenue_by_payment('2026-06-01', '2026-08-31');
ok_('payment methods present', count($byPay) > 0);
$order = array_column($byPay, 'key');
$gi = array_search('momo', $order, true);
$gr = array_search('cash', $order, true);
ok_('gold and green never adjacent in the payment donut',
    $gi === false || $gr === false || abs($gi - $gr) > 1,
    'momo@' . var_export($gi, true) . ' cash@' . var_export($gr, true));

grp('repo: clients & tiers');
$clients = repo_clients([]);
ok_('clients returned', count($clients) > 0);
$sample = $clients[0];
ok_('spend is numeric',  is_numeric($sample['spend']));
ok_('visits is numeric', is_numeric($sample['visits']));
is_('tier VIP by visits',   client_tier(['visits' => 12, 'spend' => 0]),      'VIP');
is_('tier VIP by spend',    client_tier(['visits' => 1,  'spend' => 200000]), 'VIP');
is_('tier New at 3 visits', client_tier(['visits' => 3,  'spend' => 1000]),   'New');
is_('tier Regular at 4',    client_tier(['visits' => 4,  'spend' => 1000]),   'Regular');
is_('tier boundary 11',     client_tier(['visits' => 11, 'spend' => 0]),      'Regular');

grp('repo: appointments filtering');
$all   = repo_appointments_count([]);
$done  = repo_appointments_count(['status' => 'completed']);
$pend  = repo_appointments_count(['status' => 'pending']);
ok_('status filters narrow', $done < $all && $pend < $all, "$done/$pend of $all");
ok_('count matches rows',
    count(repo_appointments(['status' => 'pending'])) === $pend);
$paged = repo_appointments(['limit' => 5]);
is_('limit respected', count($paged), 5);
$page2 = repo_appointments(['limit' => 5, 'offset' => 5]);
ok_('offset shifts the window', $paged[0]['ref'] !== $page2[0]['ref']);
$scoped = repo_appointments(['staff_id' => 2]);
ok_('staff scope excludes others',
    count(array_filter($scoped, fn($a) => (int) $a['staff_id'] !== 2)) === 0);
$search = repo_appointments(['q' => 'Marlyse']);
ok_('search narrows', count($search) < $all && count($search) > 0);
$nonsense = repo_appointments(['q' => 'zzzzzznotaclient']);
is_('nonsense search finds nothing', count($nonsense), 0);

grp('repo: inventory');
ok_('products',    count(repo_products()) >= 18);
ok_('retail only', count(repo_products(true)) < count(repo_products()));
ok_('stock value positive', repo_stock_value() > 0, money(repo_stock_value()));
foreach (repo_low_stock() as $p) {
    ok_('low-stock item really is low: ' . $p['ref'],
        (int) $p['qty_on_hand'] <= (int) $p['reorder_level']);
}

grp('settings');
is_('known key',    setting('salon_name'),            'Princess Beauty Salon');
is_('unknown key',  setting('does_not_exist'),        null);
is_('with default', setting('does_not_exist', 'x'),   'x');
ok_('numeric setting', is_numeric(setting('booking_window_days')));

grp('auth: password hashing');
$h = password_hash('demo1234', PASSWORD_BCRYPT, ['cost' => BCRYPT_COST]);
ok_('verifies',         password_verify('demo1234', $h));
ok_('rejects wrong',    !password_verify('demo1235', $h));
ok_('rejects empty',    !password_verify('', $h));
ok_('cost is 12',       str_contains($h, '$2y$12$'));
ok_('no rehash needed', !password_needs_rehash($h, PASSWORD_BCRYPT, ['cost' => BCRYPT_COST]));

grp('auth: login paths');
/* These deliberately fail, so clear the slate first and again afterwards —
   otherwise running the suite twice locks a real account out for 15 minutes. */
db_run('DELETE FROM login_attempts');
$before = (int) db_val('SELECT COUNT(*) FROM login_attempts');
$r = auth_login('nobody@nowhere.test', 'whatever');
ok_('unknown user refused', $r['ok'] === false);
is_('generic message (no user enumeration)', $r['error'], 'Email or password is incorrect.');
$r = auth_login('aicha@princessbeautysalon.cm', 'wrongpassword');
is_('wrong password same message', $r['error'], 'Email or password is incorrect.');
$r = auth_login('', '');
ok_('empty credentials refused', $r['ok'] === false);
$r = auth_login('pauline.manager@princessbeautysalon.cm', 'demo1234');
ok_('suspended account refused', $r['ok'] === false, json_encode($r));
ok_('suspended message differs', str_contains((string) $r['error'], 'suspended'));
$r = auth_login('sandra@princessbeautysalon.cm', 'demo1234');
ok_('invited account refused', $r['ok'] === false);
ok_('attempts were recorded',
    (int) db_val('SELECT COUNT(*) FROM login_attempts') > $before);

/* lockout is keyed on email + IP, so five failures from one address trip it
   while the same account stays reachable from elsewhere */
db_run('DELETE FROM login_attempts');
for ($i = 0; $i < LOGIN_MAX_ATTEMPTS; $i++) {
    auth_login('lockme@example.test', 'wrong');
}
ok_('locked after ' . LOGIN_MAX_ATTEMPTS . ' failures from one IP',
    auth_is_locked('lockme@example.test'));
ok_('a different account is unaffected', !auth_is_locked('someone.else@example.test'));

/* a correct password clears the failure history */
db_run('DELETE FROM login_attempts');
auth_login('aicha@princessbeautysalon.cm', 'wrong');
auth_login('aicha@princessbeautysalon.cm', 'wrong');
$midway = (int) db_val('SELECT COUNT(*) FROM login_attempts WHERE identifier = ? AND successful = 0',
                       ['aicha@princessbeautysalon.cm']);
is_('failures recorded', $midway, 2);
auth_login('aicha@princessbeautysalon.cm', 'demo1234');
is_('success clears the failure history',
    (int) db_val('SELECT COUNT(*) FROM login_attempts WHERE identifier = ? AND successful = 0',
                 ['aicha@princessbeautysalon.cm']), 0);
ok_('owner not left locked out', !auth_is_locked('aicha@princessbeautysalon.cm'));

db_run('DELETE FROM login_attempts');

grp('auth: role permissions from the database');
$expect = [
    'owner'        => ['revenue' => true,  'inventory' => true,  'users' => true,  'settings' => true],
    'manager'      => ['revenue' => true,  'inventory' => true,  'users' => false, 'settings' => false],
    'receptionist' => ['revenue' => false, 'inventory' => true,  'users' => false, 'settings' => false],
    'staff'        => ['revenue' => false, 'inventory' => false, 'users' => false, 'settings' => false],
];
foreach ($expect as $role => $areas) {
    $have = db_run(
        'SELECT p.slug FROM permissions p
           JOIN role_permissions rp ON rp.permission_id = p.id
           JOIN roles r ON r.id = rp.role_id WHERE r.slug = ?',
        [$role])->fetchAll(PDO::FETCH_COLUMN);
    foreach ($areas as $area => $should) {
        ok_("$role " . ($should ? 'has' : 'lacks') . " $area",
            in_array($area, $have, true) === $should);
    }
}
$staffPerms = db_run(
    'SELECT p.slug FROM permissions p
       JOIN role_permissions rp ON rp.permission_id = p.id
       JOIN roles r ON r.id = rp.role_id WHERE r.slug = "staff"')->fetchAll(PDO::FETCH_COLUMN);
ok_('stylist keeps personal areas',
    in_array('my-schedule', $staffPerms, true) &&
    in_array('my-performance', $staffPerms, true));

grp('data integrity');
is_('no orphan appointment items', (int) db_val(
    'SELECT COUNT(*) FROM appointment_items ai
      LEFT JOIN appointments a ON a.id = ai.appointment_id WHERE a.id IS NULL'), 0);
is_('no orphan sale items', (int) db_val(
    'SELECT COUNT(*) FROM product_sale_items si
      LEFT JOIN product_sales s ON s.id = si.sale_id WHERE s.id IS NULL'), 0);
is_('no negative stock', (int) db_val(
    'SELECT COUNT(*) FROM products WHERE deleted_at IS NULL AND qty_on_hand < 0'), 0);
is_('stock ledger reconciles', (int) db_val(
    'SELECT COUNT(*) FROM (
        SELECT p.id FROM products p
          LEFT JOIN stock_movements m ON m.product_id = p.id
         WHERE p.deleted_at IS NULL
         GROUP BY p.id, p.qty_on_hand
        HAVING p.qty_on_hand <> COALESCE(SUM(m.qty), 0)) x'), 0);
is_('every appointment ends after it starts', (int) db_val(
    'SELECT COUNT(*) FROM appointments WHERE ends_at <= starts_at'), 0);
is_('duration matches the clock', (int) db_val(
    'SELECT COUNT(*) FROM appointments
      WHERE TIME_TO_SEC(TIMEDIFF(ends_at, starts_at)) / 60 <> duration_minutes'), 0);
is_('completed work is never unpaid-and-forgotten in the future', (int) db_val(
    'SELECT COUNT(*) FROM appointments WHERE status = "completed" AND booked_on > ?',
    [date('Y-m-d')]), 0);
is_('no stylist double-booked', (int) db_val(
    'SELECT COUNT(*) FROM appointments a1
       JOIN appointments a2 ON a1.staff_id = a2.staff_id AND a1.booked_on = a2.booked_on
        AND a1.id < a2.id AND a1.starts_at < a2.ends_at AND a2.starts_at < a1.ends_at
      WHERE a1.status <> "cancelled" AND a2.status <> "cancelled"'), 0);
is_('every user has a valid role', (int) db_val(
    'SELECT COUNT(*) FROM users u LEFT JOIN roles r ON r.id = u.role_id WHERE r.id IS NULL'), 0);
is_('active users all have a password', (int) db_val(
    'SELECT COUNT(*) FROM users WHERE status = "active" AND password_hash IS NULL'), 0);
is_('emails are unique', (int) db_val(
    'SELECT COUNT(*) FROM (SELECT email FROM users GROUP BY email HAVING COUNT(*) > 1) x'), 0);

grp('transactions roll back');
$countBefore = (int) db_val('SELECT COUNT(*) FROM clients');
try {
    db_transaction(function () {
        db_insert('clients', ['ref' => 'CL-TXN', 'name' => 'Rollback Test',
                              'phone' => '+237 600000000', 'joined_on' => date('Y-m-d')]);
        throw new RuntimeException('deliberate');
    });
} catch (RuntimeException $e) { /* expected */ }
is_('failed transaction left no row', (int) db_val('SELECT COUNT(*) FROM clients'), $countBefore);
ok_('no stray CL-TXN', !db_val('SELECT 1 FROM clients WHERE ref = "CL-TXN"'));

echo "\n=== $pass passed, $fail failed ===\n";
exit($fail ? 1 : 0);
