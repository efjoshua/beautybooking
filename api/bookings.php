<?php
/**
 * POST /api/bookings.php — create a booking.
 * Accepts JSON or form-encoded. Requires a CSRF token.
 */
declare(strict_types=1);
require_once __DIR__ . '/../includes/booking.php';
require_once __DIR__ . '/../includes/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_error('POST only.', 405);
csrf_require();

$in = request_input();
if (!$in) json_error('Malformed request.');

if (is_string($in['services'] ?? null)) {
    $in['services'] = array_filter(explode(',', $in['services']));
}

$result = booking_create($in);
if (!$result['ok']) json_error($result['error'], 422);

audit('Online booking created: ' . $result['ref'], 'appointment', (string) $result['id']);
json_response([
    'ok'          => true,
    'ref'         => $result['ref'],
    /* > 0 only when a deposit can actually be collected, so the page can
       simply ask "is there anything to pay?" without knowing about states. */
    'deposit_due' => (float) ($result['deposit_due'] ?? 0),
]);
