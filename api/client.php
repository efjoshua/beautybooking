<?php
/** GET /api/client.php?ref=CL-001 — profile + booking history for the modal. */
declare(strict_types=1);
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/repo.php';

$u = api_require_permission('clients');

$ref = (string) ($_GET['ref'] ?? '');
$c = repo_client_by_ref($ref);
if (!$c) json_error('Client not found.', 404);

/* A stylist may only open clients they have personally served. */
if (!can('revenue') && $u['staff_id']) {
    $served = db_val('SELECT 1 FROM appointments WHERE client_id = ? AND staff_id = ? LIMIT 1',
                     [$c['id'], $u['staff_id']]);
    if (!$served) json_error('That client is not on your book.', 403);
}

$historyFilter = ['client_id' => (int) $c['id'], 'limit' => 40];
if (!can('revenue') && $u['staff_id']) $historyFilter['staff_id'] = (int) $u['staff_id'];

$visits = max(1, (int) $c['visits']);

json_response(['ok' => true, 'client' => [
    'ref' => $c['ref'], 'name' => $c['name'], 'phone' => $c['phone'],
    'email' => $c['email'], 'notes' => $c['notes'],
    'tier' => client_tier($c),
    'joined' => fmt_day($c['joined_on']),
    'visits' => (int) $c['visits'],
    'spend' => money($c['spend']),
    'avg' => money((float) $c['spend'] / $visits),
    'fav_category' => $c['fav_category'],
    'fav_staff' => $c['fav_staff_name'],
    'history' => array_map(fn($a) => [
        'date' => fmt_day($a['booked_on']),
        'service' => $a['service_names'],
        'status' => $a['status'],
        'total' => money($a['grand_total']),
    ], repo_appointments($historyFilter)),
]]);
