<?php
/**
 * Appointment read/write for the admin diary.
 *   GET  ?ref=PBS-1234           → full detail for the modal
 *   POST action=status|payment   → change status / take payment
 *
 * Completing an appointment deducts the stock its services consume, inside a
 * transaction, so takings and inventory can never disagree.
 */
declare(strict_types=1);
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/repo.php';
require_once __DIR__ . '/../includes/payments.php';

$u = api_require_permission('appointments');

/**
 * A stylist may only touch their own diary.
 *
 * The chair check has to come first. Reception and managers hold no chair —
 * their staff_id is NULL — and running the whole diary is precisely their job.
 * Gating on can('revenue') alone locked the receptionist out of every single
 * appointment: NULL cast to 0, which matches no stylist, so the front desk
 * could open the diary page and then not one booking on it.
 */
function assert_owns(array $a, array $u): void {
    if (!$u['staff_id']) return;                 // front of house: whole diary
    if (!can('revenue') && (int) $a['staff_id'] !== (int) $u['staff_id']) {
        json_error('That appointment is not on your diary.', 403);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $a = repo_appointment_by_ref((string) ($_GET['ref'] ?? ''));
    if (!$a) json_error('Appointment not found.', 404);
    assert_owns($a, $u);

    $commission = $a['commission_pct']
        ? (float) $a['services_total'] * (float) $a['commission_pct'] / 100 : 0.0;

    json_response(['ok' => true, 'appointment' => [
        'ref' => $a['ref'], 'status' => $a['status'], 'payment_method' => $a['payment_method'],
        'client' => $a['client_name'], 'client_ref' => $a['client_ref'],
        'phone' => $a['client_phone'], 'email' => $a['client_email'],
        'staff' => $a['staff_name'] ?: 'Unassigned', 'job_title' => $a['job_title'],
        'date' => fmt_day($a['booked_on']), 'date_iso' => $a['booked_on'],
        'starts' => fmt_time($a['starts_at']), 'ends' => fmt_time($a['ends_at']),
        'duration' => duration((int) $a['duration_minutes']),
        'note' => $a['note'],
        /* A deposit already banked changes what the front desk should charge
           at the counter, so it belongs in the same view as the total. */
        'deposit_due'  => (float) ($a['deposit_due'] ?? 0),
        'deposit_paid' => (float) ($a['deposit_paid'] ?? 0),
        'balance'      => money(max(0, (float) $a['grand_total'] - (float) ($a['deposit_paid'] ?? 0))),
        'payments'     => array_map(fn($p) => [
            'ref'      => $p['ref'],
            'provider' => $p['provider'] === 'momo' ? 'MTN MoMo' : 'Orange Money',
            'amount'   => money($p['amount']),
            'status'   => $p['status'],
            'phone'    => $p['payer_phone'],
            'when'     => fmt_day(substr((string) $p['requested_at'], 0, 10)),
            'reason'   => $p['failure_reason'],
        ], payments_for_appointment((int) $a['id'])),
        'items' => array_map(fn($i) => [
            'name' => $i['service_name'], 'price' => money($i['unit_price'])], $a['items']),
        'services_total' => money($a['services_total']),
        'addons' => money($a['addons_total']),
        'tip' => money($a['tip_total']),
        /* Raw figures as well as formatted ones: the counter form has to be
           able to reopen with what was already recorded, or collecting on a
           booking that already carried add-ons would quietly zero them. */
        'addons_raw' => (float) $a['addons_total'],
        'tip_raw' => (float) $a['tip_total'],
        'total' => money($a['grand_total']),
        'commission_pct' => (float) ($a['commission_pct'] ?? 0),
        'commission' => money($commission),
        'consumes' => array_map(fn($c) => [
            'name' => $c['name'], 'qty' => (float) $c['qty_used'], 'unit' => $c['unit']], $a['consumes']),
    ]]);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_error('Method not allowed.', 405);
csrf_require();

$a = repo_appointment_by_ref((string) ($_POST['ref'] ?? ''));
if (!$a) json_error('Appointment not found.', 404);
assert_owns($a, $u);

$action = (string) ($_POST['action'] ?? '');

if ($action === 'status') {
    $status = (string) ($_POST['status'] ?? '');
    if (!in_array($status, ['pending','confirmed','completed','cancelled','no-show'], true)) {
        json_error('Unknown status.');
    }
    if ($a['status'] === 'completed' && $status !== 'completed') {
        json_error('A completed appointment cannot be reopened — reverse the payment instead.', 422);
    }
    /* Completing banks the revenue and deducts stock, so it must not happen
       before the work does — otherwise today's takings include tomorrow's
       appointments and the stock ledger runs ahead of reality. */
    if ($status === 'completed' && $a['booked_on'] > app_today()) {
        json_error('That appointment is on ' . fmt_day($a['booked_on']) .
                   '. It cannot be completed before it happens.', 422);
    }

    /* Marking an already-completed appointment complete again must not spend
       the stock a second time. It is reachable — two people on the counter,
       a double-click, a stale tab — and the ledger would silently drift. */
    $alreadyDone = $a['status'] === 'completed';

    db_transaction(function () use ($a, $status, $alreadyDone) {
        db_update('appointments', [
            'status' => $status,
            'cancelled_at' => $status === 'cancelled' ? date('Y-m-d H:i:s') : null,
        ], ['id' => $a['id']]);

        /* Completing consumes the stock the services use. */
        if ($status === 'completed' && !$alreadyDone) {
            /* select the SERVICE name too — the note must say what was done,
               not repeat the product being deducted */
            foreach (db_all(
                'SELECT sp.product_id, sp.qty_used, p.ref, p.name AS product_name,
                        ai.service_name
                   FROM appointment_items ai
                   JOIN service_products sp ON sp.service_id = ai.service_id
                   JOIN products p ON p.id = sp.product_id
                  WHERE ai.appointment_id = ?', [$a['id']]) as $c) {
                db_insert('stock_movements', [
                    'ref' => next_ref('stock_movements', 'SM-', 4),
                    'product_id' => $c['product_id'],
                    'movement_type' => 'out',
                    'qty' => -abs((int) $c['qty_used']),
                    'reference' => $a['ref'],
                    'note' => 'Used in ' . $c['service_name'],
                    'staff_id' => $a['staff_id'],
                    'moved_on' => app_today(),
                ]);
                db_run('UPDATE products SET qty_on_hand = qty_on_hand - ? WHERE id = ?',
                    [abs((int) $c['qty_used']), $c['product_id']]);
            }
        }
    });

    audit("Appointment {$a['ref']} → $status", 'appointment', (string) $a['id']);
    json_response(['ok' => true, 'message' => 'Appointment marked ' . $status . '.']);
}

if ($action === 'payment') {
    $method = (string) ($_POST['method'] ?? '');
    if (!in_array($method, ['cash','momo','om','card'], true)) json_error('Unknown payment method.');

    $addons = max(0, (float) ($_POST['addons'] ?? 0));
    $tip    = max(0, (float) ($_POST['tip'] ?? 0));
    $grand  = (float) $a['services_total'] + $addons + $tip;

    db_update('appointments', [
        'payment_method' => $method, 'addons_total' => $addons, 'tip_total' => $tip,
        'grand_total' => $grand, 'paid_at' => date('Y-m-d H:i:s'),
    ], ['id' => $a['id']]);

    audit("Payment taken for {$a['ref']}: " . money($grand) . " by $method",
          'appointment', (string) $a['id']);
    json_response(['ok' => true, 'message' => 'Payment of ' . money($grand) . ' recorded.']);
}

json_error('Unknown action.');
