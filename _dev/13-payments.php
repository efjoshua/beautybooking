<?php
/**
 * Payment integration tests.
 *
 * The point of this suite is the thing that cannot be tested by paying: that
 * all three states behave, and that the two which involve NO money still let
 * a client book. A missing API key is the normal state of this system until
 * the owner buys a subscription, so it is tested as a first-class case rather
 * than as an error path.
 *
 *   php _dev/13-payments.php
 */
declare(strict_types=1);

require_once __DIR__ . '/../includes/payments.php';
require_once __DIR__ . '/../includes/booking.php';

$pass = 0; $fail = 0;
function ok(string $n, bool $c, string $d = ''): void {
    global $pass, $fail;
    if ($c) { $pass++; echo "ok    $n\n"; }
    else { $fail++; echo "FAIL  $n" . ($d ? "\n        $d" : '') . "\n"; }
}
function grp(string $g): void { echo "\n--- $g ---\n"; }

/** Flip a setting for the duration of a test. */
function set_setting(string $k, string $v): void {
    db_run('INSERT INTO settings (`key`, `value`) VALUES (?, ?)
            ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)', [$k, $v]);
    /* setting() caches for the request, so a write is invisible without this. */
    setting_forget();
}

$restore = [
    'payments_enabled'         => setting('payments_enabled', '0'),
    'payments_require_deposit' => setting('payments_require_deposit', '0'),
];

/* ==================================================================== */
grp('phone numbers');

$cases = [
    ['+237 655 99 88 77', '237655998877', 'om'],
    ['655998877',         '237655998877', 'om'],
    ['00237677123456',    '237677123456', 'momo'],
    ['+237 6 90 11 22 33','237690112233', 'om'],
    ['237 680 11 22 33',  '237680112233', 'momo'],
    ['12345',             null,           null],
    ['',                  null,           null],
    ['+237 555 11 22 33', null,           null],   // landline, not a mobile
    ['+237 6 12 34 56 78','237612345678', null],   // valid, unknown prefix
];
foreach ($cases as [$in, $expect, $net]) {
    $got = payment_normalise_phone($in);
    ok('normalise ' . ($in ?: '(empty)'), $got === $expect,
       var_export($got, true) . ' expected ' . var_export($expect, true));
    ok('network for ' . ($in ?: '(empty)'), payment_detect_network($in) === $net,
       var_export(payment_detect_network($in), true) . ' expected ' . var_export($net, true));
}

/* ==================================================================== */
grp('STATE 1 — payments switched off');
set_setting('payments_enabled', '0');
set_setting('payments_require_deposit', '1');   // on purpose: off must win

ok('state is off', payments_state() === 'off', payments_state());
ok('no deposit is ever due', payment_deposit_due(500000) === 0.0,
   (string) payment_deposit_due(500000));
ok('the policy quote still works for the services page',
   payment_deposit_quote(50000) === 15000.0, (string) payment_deposit_quote(50000));

$r = payment_start(['provider' => 'momo', 'amount' => 5000, 'payer_phone' => '655998877']);
ok('starting a payment is refused', !$r['ok'], json_encode($r));
ok('and it says why, without a stack trace',
   isset($r['error']) && str_contains(strtolower($r['error']), 'not connected'),
   $r['error'] ?? '');
ok('nothing was recorded',
   (int) db_val('SELECT COUNT(*) FROM payments WHERE payer_phone = "237655998877"') === 0);

/* ==================================================================== */
grp('STATE 2 — switched on, no API keys');
set_setting('payments_enabled', '1');

ok('state is unconfigured', payments_state() === 'unconfigured', payments_state());
ok('neither provider is ready',
   !payment_provider_ready('momo') && !payment_provider_ready('om'));
ok('both providers are still LISTED, so the client learns something true',
   count(payment_providers()) === 2);

/* The whole point: nobody is blocked from booking. */
ok('no deposit is demanded', payment_deposit_due(500000) === 0.0,
   (string) payment_deposit_due(500000));

$r = payment_start(['provider' => 'momo', 'amount' => 5000, 'payer_phone' => '655998877']);
ok('a payment attempt is refused cleanly', !$r['ok']);
ok('and flagged as "not configured" rather than "failed"',
   !empty($r['unconfigured']), json_encode($r));

/* ==================================================================== */
grp('STATE 2 — a real booking still completes with payments on but unconfigured');

$svc  = db_val('SELECT ref FROM services WHERE deleted_at IS NULL ORDER BY price DESC LIMIT 1');
$open = booking_open_days([$svc]);
$day  = null;
foreach ($open as $d) { if ($d['free'] > 0) { $day = $d['date']; break; } }
ok('a bookable day exists to test with', $day !== null);

$made = null;
if ($day) {
    $slots = booking_slots($day, [$svc]);
    $time  = null;
    foreach ($slots as $s) { if ($s['available']) { $time = $s['time']; break; } }

    $made = booking_create([
        'services' => [$svc], 'staff' => 'any', 'date' => $day, 'time' => $time,
        'name' => 'Payments Test', 'phone' => '+237 655 00 11 22',
    ]);
    ok('the booking was created', !empty($made['ok']), json_encode($made));
    ok('with no deposit owed', (float) ($made['deposit_due'] ?? -1) === 0.0,
       var_export($made['deposit_due'] ?? null, true));
    if (!empty($made['ok'])) {
        $row = db_one('SELECT status, deposit_due, deposit_paid FROM appointments WHERE ref = ?',
                      [$made['ref']]);
        ok('and it is a normal pending booking', $row['status'] === 'pending', json_encode($row));
        ok('deposit_due stored as zero', (float) $row['deposit_due'] === 0.0);
    }
}

/* ==================================================================== */
grp('STATE 3 — keys present (simulated)');

/* The drivers cannot be exercised without real credentials, so what is tested
   here is everything AROUND them: the decision to attempt a charge, the
   validation before one, and the record written when one fails. Constants
   cannot be redefined, so provider readiness is asserted against the real
   config and the rest is driven through payment_open()/payment_settle(). */
$keysPresent = payment_provider_ready('momo') || payment_provider_ready('om');
echo "      (credentials are " . ($keysPresent ? 'PRESENT' : 'absent')
   . " in config.php — the live path is "
   . ($keysPresent ? 'exercisable' : 'not reachable from here') . ")\n";

$p = payment_open([
    'appointment_id' => null, 'client_id' => null,
    'provider' => 'momo', 'amount' => 7500,
    'currency' => 'XAF', 'payer_phone' => '237677000111',
]);
ok('a payment row is opened before the provider is called', !empty($p['id']));
ok('it has our own idempotency key', strlen($p['external_id']) === 32, $p['external_id']);
ok('the reference is human-readable', str_starts_with($p['ref'], 'PAY-'), $p['ref']);
ok('it starts pending',
   db_val('SELECT status FROM payments WHERE id = ?', [$p['id']]) === 'pending');

/* MTN wants a UUID; ours must be well-formed or every request 400s. */
$uuid = momo_uuid_from($p['external_id']);
ok('the MTN reference is a valid UUID',
   (bool) preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/', $uuid),
   $uuid);
ok('the same payment always maps to the same UUID',
   momo_uuid_from($p['external_id']) === $uuid);

payment_settle((int) $p['id'], 'failed', 'Simulated: insufficient balance');
$row = db_one('SELECT * FROM payments WHERE id = ?', [$p['id']]);
ok('a failure is recorded, not deleted', $row['status'] === 'failed', $row['status']);
ok('with the provider reason kept for disputes',
   str_contains((string) $row['failure_reason'], 'insufficient'), (string) $row['failure_reason']);

/* A late callback must not resurrect a settled payment. */
payment_settle((int) $p['id'], 'succeeded', 'late webhook');
ok('a terminal payment cannot be re-settled by a late callback',
   db_val('SELECT status FROM payments WHERE id = ?', [$p['id']]) === 'failed');

db_run('DELETE FROM payments WHERE id = ?', [$p['id']]);

/* ==================================================================== */
grp('deposit_paid is derived, never incremented');

if ($made && !empty($made['ok'])) {
    $apptId = (int) db_val('SELECT id FROM appointments WHERE ref = ?', [$made['ref']]);

    $a = payment_open(['appointment_id' => $apptId, 'provider' => 'momo',
                       'amount' => 3000, 'payer_phone' => '237677000111']);
    $b = payment_open(['appointment_id' => $apptId, 'provider' => 'om',
                       'amount' => 2000, 'payer_phone' => '237699000111']);
    payment_settle((int) $a['id'], 'succeeded');
    ok('one success sets deposit_paid',
       (float) db_val('SELECT deposit_paid FROM appointments WHERE id = ?', [$apptId]) === 3000.0);

    payment_settle((int) $b['id'], 'succeeded');
    ok('two successes sum correctly',
       (float) db_val('SELECT deposit_paid FROM appointments WHERE id = ?', [$apptId]) === 5000.0);

    /* Marking one failed AFTER the fact must be reflected, which an
       incrementing counter could never do. */
    db_run('UPDATE payments SET status = "failed" WHERE id = ?', [$b['id']]);
    $c = payment_open(['appointment_id' => $apptId, 'provider' => 'momo',
                       'amount' => 1000, 'payer_phone' => '237677000111']);
    payment_settle((int) $c['id'], 'succeeded');
    ok('deposit_paid is recomputed from the ledger, not added to',
       (float) db_val('SELECT deposit_paid FROM appointments WHERE id = ?', [$apptId]) === 4000.0,
       db_val('SELECT deposit_paid FROM appointments WHERE id = ?', [$apptId])
       . ' — expected 4000 (3000 + 1000, with the 2000 now failed)');

    ok('every attempt is still on file, including the failed one',
       count(payments_for_appointment($apptId)) === 3);

    /* tidy */
    db_run('DELETE FROM payments WHERE appointment_id = ?', [$apptId]);
    db_run('DELETE FROM appointment_items WHERE appointment_id = ?', [$apptId]);
    db_run('DELETE FROM appointments WHERE id = ?', [$apptId]);
    /* booking_create reuses an existing client when the phone matches, so the
       client may have history that predates this test. Remove it only when
       nothing else points at it. */
    db_run('DELETE FROM clients WHERE phone = ?
             AND NOT EXISTS (SELECT 1 FROM appointments a WHERE a.client_id = clients.id)',
           ['+237 655 00 11 22']);
}

/* ==================================================================== */
grp('validation refuses what would waste a charge');
set_setting('payments_enabled', '1');

foreach ([
    ['no provider',        ['provider' => '',     'amount' => 5000, 'payer_phone' => '655998877']],
    ['unknown provider',   ['provider' => 'visa', 'amount' => 5000, 'payer_phone' => '655998877']],
    ['bad phone',          ['provider' => 'momo', 'amount' => 5000, 'payer_phone' => '123']],
    ['zero amount',        ['provider' => 'momo', 'amount' => 0,    'payer_phone' => '677123456']],
    ['negative amount',    ['provider' => 'momo', 'amount' => -100, 'payer_phone' => '677123456']],
] as [$label, $in]) {
    $r = payment_start($in);
    ok("refused: $label", !$r['ok'], json_encode($r));
}

ok('no junk rows were written by the refusals',
   (int) db_val('SELECT COUNT(*) FROM payments WHERE amount <= 0') === 0);

/* ==================================================================== */
grp('the salon can always turn it off again');

set_setting('payments_enabled', '0');
ok('back to off', payments_state() === 'off');
ok('and deposits stop being demanded', payment_deposit_due(500000) === 0.0);

foreach ($restore as $k => $v) set_setting($k, (string) $v);
echo "      (settings restored: payments_enabled=" . setting('payments_enabled', '0') . ")\n";

echo "\n=== $pass passed, $fail failed ===\n";
exit($fail ? 1 : 0);
