<?php
/**
 * GET /api/slots.php?date=YYYY-MM-DD&services=SV-01,SV-06&staff=ST-02
 * Real availability, computed from the rota and existing bookings.
 */
declare(strict_types=1);
require_once __DIR__ . '/../includes/booking.php';

$date  = (string) ($_GET['date'] ?? '');
$refs  = array_filter(explode(',', (string) ($_GET['services'] ?? '')));
$staff = ($_GET['staff'] ?? '') ?: null;
if ($staff === 'any') $staff = null;

if (!$refs) json_error('Choose a service first.');

if ($date === '') {
    json_response(['ok' => true, 'days' => booking_open_days($refs, $staff)]);
}
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) json_error('Invalid date.');

$resolved = booking_resolve_services($refs);
json_response([
    'ok'       => true,
    'date'     => $date,
    'duration' => $resolved['duration'],
    'total'    => $resolved['total'],
    'slots'    => booking_slots($date, $refs, $staff),
]);
