<?php
/**
 * Public payment endpoint.
 *
 *   GET  ?action=state                       what the booking page should show
 *   POST action=start   ref, phone, provider, payer_phone
 *   POST action=status  payment_ref
 *
 * The booking is created FIRST and paid for SECOND, deliberately. If the
 * deposit fails, times out, or the provider is down, the client still has
 * their slot and the salon has a pending booking to chase. The alternative —
 * charge, then book — loses the appointment every time a network hiccups and
 * leaves money to refund.
 */
declare(strict_types=1);

require_once __DIR__ . '/../includes/payments.php';
require_once __DIR__ . '/../includes/booking.php';
require_once __DIR__ . '/../includes/auth.php';

/* ------------------------------------------------------------------ state */
if (($_GET['action'] ?? '') === 'state' || $_SERVER['REQUEST_METHOD'] === 'GET') {
    $state = payments_state();
    json_response([
        'ok'        => true,
        'state'     => $state,
        'providers' => array_map(fn($p) => [
            'code'  => $p['code'],
            'name'  => $p['name'],
            'short' => $p['short'],
            'ready' => $p['ready'],
            'mode'  => $p['mode'],
            'hint'  => $p['hint'],
        ], payment_providers()),
        'deposit'   => [
            'threshold' => (float) setting('deposit_threshold', '20000'),
            'percent'   => (float) setting('deposit_percent', '30'),
            'required'  => $state === 'live' && setting('payments_require_deposit', '0') === '1',
        ],
        /* Said once, here, so the page and the server can never disagree
           about what the client is being asked to do. */
        'notice'    => match ($state) {
            'off'  => '',
            'live' => 'Pay the deposit now to hold your slot.',
            default => 'Online payment is not connected yet — you can complete your booking '
                     . 'without paying, and settle at the salon.',
        },
    ]);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_error('POST only.', 405);
csrf_require();

/* JSON body or form post — the wizard sends JSON. */
$in     = request_input();
$action = (string) ($in['action'] ?? '');

/* ------------------------------------------------------------------ start */
if ($action === 'start') {
    /* Reference plus phone, exactly as manage-booking does. Without this
       anyone could push a payment prompt at any client whose booking
       reference they guessed. */
    $a = booking_lookup((string) ($in['ref'] ?? ''), (string) ($in['phone'] ?? ''));
    if (!$a) json_error('We could not find that booking.', 404);

    if (payments_state() !== 'live') {
        json_error('Online payment is not connected yet. Your booking is confirmed and you '
                 . 'can pay at the salon.', 422, ['unconfigured' => true]);
    }

    $due = (float) $a['deposit_due'] - (float) $a['deposit_paid'];
    if ($due <= 0) json_error('There is nothing left to pay on this booking.', 422);

    /* One live attempt at a time. Two prompts on one handset for one booking
       is how a client ends up paying twice. */
    $inFlight = db_one(
        'SELECT ref, requested_at FROM payments
          WHERE appointment_id = ? AND status = "pending"
          ORDER BY id DESC LIMIT 1', [$a['id']]);
    if ($inFlight
        && strtotime((string) $inFlight['requested_at']) + PAYMENT_TIMEOUT_SECONDS > time()) {
        json_response(['ok' => true, 'ref' => $inFlight['ref'], 'mode' => 'push', 'resumed' => true,
                       'message' => 'A payment request is already waiting on your phone.']);
    }

    $res = payment_start([
        'appointment_id' => (int) $a['id'],
        'client_id'      => (int) $a['client_id'],
        'provider'       => (string) ($in['provider'] ?? ''),
        'purpose'        => 'deposit',
        'amount'         => $due,
        'payer_phone'    => (string) ($in['payer_phone'] ?? $in['phone'] ?? ''),
        'note'           => APP_NAME . ' deposit for ' . $a['ref'],
    ]);

    if (!$res['ok']) json_error($res['error'], 422, ['unconfigured' => $res['unconfigured'] ?? false]);

    audit('Deposit requested for ' . $a['ref'] . ' (' . $res['ref'] . ')',
          'payment', $res['ref']);
    json_response($res);
}

/* ----------------------------------------------------------------- status */
if ($action === 'status') {
    $ref = (string) ($in['payment_ref'] ?? '');
    if (!preg_match('/^PAY-\d+$/', $ref)) json_error('Unknown payment.', 404);

    $res = payment_refresh($ref);
    if (!$res['ok']) json_error($res['error'] ?? 'Unknown payment.', 404);

    $p = db_one('SELECT amount, appointment_id FROM payments WHERE ref = ?', [$ref]);
    $paid = $p && $p['appointment_id']
        ? (float) db_val('SELECT deposit_paid FROM appointments WHERE id = ?', [$p['appointment_id']])
        : 0.0;

    json_response([
        'ok'      => true,
        'status'  => $res['status'],
        'reason'  => $res['reason'],
        'paid'    => $paid,
        'message' => match ($res['status']) {
            'succeeded' => 'Payment received — thank you! Your slot is held.',
            'failed'    => $res['reason'] ?: 'The payment did not go through.',
            'cancelled' => 'The payment was cancelled.',
            'timeout'   => 'The prompt expired. You can try again, or pay at the salon.',
            default     => 'Waiting for you to approve the payment on your phone…',
        },
    ]);
}

json_error('Unknown action.', 400);
