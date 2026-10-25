<?php
/** POST /api/lookup.php — find or cancel a booking by reference + phone. */
declare(strict_types=1);
require_once __DIR__ . '/../includes/booking.php';
require_once __DIR__ . '/../includes/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_error('POST only.', 405);
csrf_require();

$ref   = (string) ($_POST['ref'] ?? '');
$phone = (string) ($_POST['phone'] ?? '');

if (($_POST['action'] ?? '') === 'cancel') {
    $r = booking_cancel($ref, $phone);
    if (!$r['ok']) json_error($r['error'], 404);
    json_response(['ok' => true, 'message' => $r['message']]);
}

$a = booking_lookup($ref, $phone);
if (!$a) json_error('No booking matches that reference and phone number.', 404);

json_response(['ok' => true, 'booking' => [
    'ref' => $a['ref'], 'status' => $a['status'],
    'date' => $a['booked_on'], 'date_long' => fmt_date_long($a['booked_on']),
    'starts' => fmt_time($a['starts_at']), 'ends' => fmt_time($a['ends_at']),
    'duration' => (int) $a['duration_minutes'],
    'staff' => $a['staff_name'] ?: 'Any available stylist',
    'client' => $a['client_name'],
    'total' => (float) $a['grand_total'],
    'total_fmt' => money($a['grand_total']),
    'services' => array_column($a['items'], 'service_name'),
]]);
