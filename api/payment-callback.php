<?php
/**
 * Provider callbacks (MTN X-Callback-Url, Orange notif_url).
 *
 * A callback is a HINT, never an instruction. Anyone on the internet can POST
 * here, so nothing in the request body is believed: the only thing taken from
 * it is which payment to go and ask the provider about. The answer always
 * comes from payment_refresh(), which talks to MTN or Orange directly using
 * our own credentials.
 *
 * That makes this endpoint safe to leave open, and it means a salon whose
 * server is unreachable from the internet loses nothing — the client's browser
 * polls for the same answer.
 */
declare(strict_types=1);

require_once __DIR__ . '/../includes/payments.php';

/* Never let a provider's retry storm become an error page. */
http_response_code(200);
header('Content-Type: application/json');

$raw  = file_get_contents('php://input') ?: '';
$body = json_decode($raw, true);
if (!is_array($body)) $body = $_POST;

/* Find our own reference in whatever shape the provider used. MTN echoes
   externalId; Orange echoes order_id. */
$ref = '';
foreach (['externalId', 'order_id', 'reference', 'ref'] as $k) {
    if (!empty($body[$k]) && is_string($body[$k])) { $ref = $body[$k]; break; }
}
if ($ref === '' && !empty($_GET['ref'])) $ref = (string) $_GET['ref'];

if (!preg_match('/^PAY-\d+$/', $ref)) {
    echo json_encode(['ok' => true, 'ignored' => 'no recognisable reference']);
    exit;
}

/* Record that a callback arrived, for when someone asks why a payment
   settled at 2am with nobody at the counter. */
db_run('UPDATE payments SET last_response = ? WHERE ref = ? AND last_response IS NULL',
       [mb_substr($raw, 0, 60000), $ref]);

$res = payment_refresh($ref);

echo json_encode(['ok' => true, 'status' => $res['status'] ?? 'unknown']);
