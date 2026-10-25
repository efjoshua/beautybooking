<?php
/**
 * Princess Beauty Salon — mobile money collection
 *
 * THE ONE RULE THIS FILE OBEYS
 * A missing API key must never stop anyone booking. Payment is an optional
 * layer on top of a booking system that works perfectly well without it, and
 * every function here is written so the worst case is "we could not take the
 * deposit, the booking stands".
 *
 * Three states, all supported:
 *
 *   off          Deposits are switched off in Settings. The booking page never
 *                mentions payment. Clients settle at the salon.
 *   unconfigured Deposits are on but no provider has credentials. The booking
 *                page shows the MoMo / Orange Money section, says plainly that
 *                payment is not connected, and lets the client book anyway.
 *   live         At least one provider has credentials. Real collection runs.
 *
 * Going from `unconfigured` to `live` is filling in constants in config.php.
 * There is no other step: the drivers below are complete implementations of
 * the MTN MoMo Collection API and the Orange Money Web Payment API.
 */

declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';

/* ==================================================================== state */

/** Has the owner switched deposits on at all? */
function payments_enabled(): bool
{
    return setting('payments_enabled', '0') === '1';
}

/**
 * Does this provider have everything it needs to actually charge someone?
 * Credentials are trimmed because a stray space pasted from an email is the
 * single most common way these go wrong.
 */
function payment_provider_ready(string $provider): bool
{
    switch ($provider) {
        case 'momo':
            return trim(MOMO_SUBSCRIPTION_KEY) !== ''
                && trim(MOMO_API_USER) !== ''
                && trim(MOMO_API_KEY) !== '';
        case 'om':
            return trim(OM_CLIENT_ID) !== ''
                && trim(OM_CLIENT_SECRET) !== ''
                && trim(OM_MERCHANT_KEY) !== '';
        default:
            return false;
    }
}

/** 'off' | 'unconfigured' | 'live' */
function payments_state(): string
{
    if (!payments_enabled()) return 'off';
    foreach (['momo', 'om'] as $p) {
        if (payment_provider_ready($p)) return 'live';
    }
    return 'unconfigured';
}

/**
 * The providers to show, each flagged with whether it can actually take money.
 * Both are always listed when payments are on: a client seeing "Orange Money —
 * not connected yet" learns something true. Hiding it would imply the salon
 * does not accept Orange at all, which is not what the owner is saying.
 */
function payment_providers(): array
{
    return [
        [
            'code'  => 'momo',
            'name'  => 'MTN Mobile Money',
            'short' => 'MTN MoMo',
            'ready' => payment_provider_ready('momo'),
            'mode'  => 'push',        // prompt appears on the handset
            'hint'  => 'You will get a prompt on your phone asking for your MoMo PIN.',
        ],
        [
            'code'  => 'om',
            'name'  => 'Orange Money',
            'short' => 'Orange Money',
            'ready' => payment_provider_ready('om'),
            'mode'  => 'redirect',    // Orange hosts the payment page
            'hint'  => 'You will be taken to the Orange Money page to approve the payment.',
        ],
    ];
}

/* ================================================================= deposits */

/**
 * What must be paid up front for a booking of this value.
 *
 * Returns 0 whenever a deposit cannot actually be collected, so callers never
 * have to ask about state — the deposit is either a real number they can
 * charge, or nothing. This is finally what `deposit_threshold` and
 * `deposit_percent` are for: both settings existed and were quoted to clients
 * on the services page, but no code had ever read them to compute anything.
 */
function payment_deposit_due(float $total): float
{
    if (payments_state() !== 'live') return 0.0;
    if (setting('payments_require_deposit', '0') !== '1') return 0.0;

    $threshold = (float) setting('deposit_threshold', '20000');
    if ($total < $threshold) return 0.0;

    $pct = max(0.0, min(100.0, (float) setting('deposit_percent', '30')));
    /* Rounded to the whole franc: FCFA has no subunit and asking for 6,742.5
       would be rejected by both providers. */
    return (float) round($total * $pct / 100);
}

/**
 * What the client should be TOLD they may owe, regardless of whether we can
 * collect it. Used for the quote on the services page, which is a statement of
 * policy rather than a charge.
 */
function payment_deposit_quote(float $total): float
{
    $threshold = (float) setting('deposit_threshold', '20000');
    if ($total < $threshold) return 0.0;
    $pct = max(0.0, min(100.0, (float) setting('deposit_percent', '30')));
    return (float) round($total * $pct / 100);
}

/* ================================================================== numbers */

/**
 * Cameroon MSISDN, normalised to the 237XXXXXXXX form both providers want.
 * Accepts what people actually type: +237 6 55 99 88 77, 655998877, 00237…
 */
function payment_normalise_phone(string $phone): ?string
{
    $d = preg_replace('/\D/', '', $phone) ?? '';
    if (str_starts_with($d, '00237')) $d = substr($d, 5);
    elseif (str_starts_with($d, '237')) $d = substr($d, 3);
    /* Cameroon mobiles are 9 digits and begin with 6. */
    if (strlen($d) !== 9 || $d[0] !== '6') return null;
    return '237' . $d;
}

/**
 * Which network a number belongs to, by prefix. Used to warn a client who has
 * typed an Orange number under MTN MoMo — the charge would simply fail, and
 * "check your PIN" is a maddening thing to be told when the real problem is
 * that MTN was never going to be able to bill an Orange line.
 *
 * Returns 'momo', 'om' or null when the prefix is not one we recognise. A null
 * is never treated as an error: prefixes are reassigned between operators and
 * being wrong about this must not block a payment.
 */
function payment_detect_network(string $phone): ?string
{
    $n = payment_normalise_phone($phone);
    if ($n === null) return null;
    $local = substr($n, 3);              // 6XXXXXXXX
    $two   = substr($local, 0, 2);
    $three = substr($local, 0, 3);

    if ($two === '67') return 'momo';
    if ($two === '69') return 'om';
    if (in_array($three, ['650','651','652','653','654','680','681','682','683','684'], true)) return 'momo';
    if (in_array($three, ['655','656','657','658','659','685','686','687','688','689'], true)) return 'om';
    return null;
}

/* ================================================================== records */

/** A short human reference for the payment, e.g. PAY-0007. */
function payment_next_ref(): string
{
    return next_ref('payments', 'PAY-', 4);
}

/**
 * Record the intent to charge before contacting anyone. If the provider call
 * times out we still know a charge may be in flight, which is the whole reason
 * this row is written first.
 */
function payment_open(array $in): array
{
    $externalId = bin2hex(random_bytes(16));
    $ref = payment_next_ref();

    $id = db_insert('payments', [
        'ref'            => $ref,
        'appointment_id' => $in['appointment_id'] ?? null,
        'client_id'      => $in['client_id'] ?? null,
        'provider'       => $in['provider'],
        'purpose'        => $in['purpose'] ?? 'deposit',
        'amount'         => $in['amount'],
        'currency'       => $in['currency'] ?? CURRENCY_CODE,
        'payer_phone'    => $in['payer_phone'],
        'status'         => 'pending',
        'external_id'    => $externalId,
    ]);

    return ['id' => $id, 'ref' => $ref, 'external_id' => $externalId];
}

/** Move a payment to a terminal state and keep the appointment's cache honest. */
function payment_settle(int $id, string $status, ?string $reason = null, $raw = null): void
{
    db_transaction(function () use ($id, $status, $reason, $raw) {
        $p = db_one('SELECT * FROM payments WHERE id = ?', [$id]);
        if (!$p) return;
        /* Terminal states are final. A late webhook must not resurrect a
           payment we already told the client had failed. */
        if ($p['status'] !== 'pending') return;

        db_update('payments', [
            'status'         => $status,
            'failure_reason' => $reason ? mb_substr($reason, 0, 255) : null,
            'last_response'  => $raw === null ? null : (is_string($raw) ? $raw : json_encode($raw)),
            'settled_at'     => $status === 'succeeded' ? date('Y-m-d H:i:s') : null,
        ], ['id' => $id]);

        if ($status === 'succeeded' && $p['appointment_id']) {
            /* Recomputed from the ledger, never incremented — the same reason
               products.qty_on_hand is rebuilt from stock_movements. */
            $paid = (float) db_val(
                'SELECT COALESCE(SUM(amount), 0) FROM payments
                  WHERE appointment_id = ? AND status = "succeeded"',
                [$p['appointment_id']]);
            db_update('appointments', ['deposit_paid' => $paid], ['id' => $p['appointment_id']]);
        }
    });
}

/** Everything recorded against one booking. */
function payments_for_appointment(int $appointmentId): array
{
    return db_all(
        'SELECT * FROM payments WHERE appointment_id = ? ORDER BY id DESC',
        [$appointmentId]);
}

/* ===================================================================== http */

/**
 * One place where this app talks to the outside world.
 *
 * Returns a shaped array rather than throwing, because every caller has to
 * degrade gracefully anyway: a provider being unreachable is a normal Tuesday
 * in Douala, not an exceptional condition.
 *
 * @return array{ok:bool, status:int, body:array|string|null, error:?string}
 */
function payment_http(string $method, string $url, array $headers = [], $body = null): array
{
    if (!function_exists('curl_init')) {
        return ['ok' => false, 'status' => 0, 'body' => null,
                'error' => 'The PHP cURL extension is not installed, so no provider can be reached.'];
    }

    $ch = curl_init($url);
    $payload = null;
    if ($body !== null) {
        $payload = is_string($body) ? $body : json_encode($body, JSON_UNESCAPED_SLASHES);
    }

    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST  => strtoupper($method),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_TIMEOUT        => PAYMENT_HTTP_TIMEOUT,
        CURLOPT_CONNECTTIMEOUT => 10,
        /* Certificate verification stays ON. A payment channel with TLS
           verification disabled is not a payment channel. */
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
    ]);
    if ($payload !== null) curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);

    $raw    = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err    = curl_error($ch);
    curl_close($ch);

    if ($raw === false) {
        return ['ok' => false, 'status' => $status, 'body' => null,
                'error' => $err ?: 'The provider could not be reached.'];
    }

    $decoded = json_decode((string) $raw, true);
    return [
        'ok'     => $status >= 200 && $status < 300,
        'status' => $status,
        'body'   => $decoded === null ? (string) $raw : $decoded,
        'error'  => null,
    ];
}

/* ============================================================== MTN MoMo */

/**
 * OAuth token for the Collection product. Cached for the request only —
 * these are valid for an hour, but a booking request makes at most two calls
 * and a stale token cache is a support call nobody enjoys.
 */
function momo_token(): array
{
    static $cached = null;
    if ($cached !== null) return $cached;

    $r = payment_http('POST', rtrim(MOMO_BASE_URL, '/') . '/collection/token/', [
        'Ocp-Apim-Subscription-Key: ' . trim(MOMO_SUBSCRIPTION_KEY),
        'Authorization: Basic ' . base64_encode(trim(MOMO_API_USER) . ':' . trim(MOMO_API_KEY)),
        'Content-Length: 0',
    ]);

    if (!$r['ok'] || !is_array($r['body']) || empty($r['body']['access_token'])) {
        $cached = ['ok' => false, 'error' => momo_explain($r)];
        return $cached;
    }
    $cached = ['ok' => true, 'token' => (string) $r['body']['access_token']];
    return $cached;
}

/** Turn a provider response into something a salon owner can act on. */
function momo_explain(array $r): string
{
    if ($r['error']) return 'MTN MoMo could not be reached: ' . $r['error'];
    $body = is_array($r['body']) ? $r['body'] : [];
    $msg  = $body['message'] ?? $body['error_description'] ?? null;
    return match ($r['status']) {
        401     => 'MTN MoMo rejected the credentials. Check MOMO_API_USER and MOMO_API_KEY in config.php.',
        403     => 'MTN MoMo rejected the subscription key. Check MOMO_SUBSCRIPTION_KEY, and that the '
                 . 'key belongs to the Collection product.',
        404     => 'MTN MoMo returned "not found" — usually MOMO_BASE_URL or MOMO_ENVIRONMENT is wrong.',
        default => 'MTN MoMo returned HTTP ' . $r['status'] . ($msg ? ': ' . $msg : '.'),
    };
}

/**
 * Push a payment request to the client's handset.
 * @return array{ok:bool, mode?:string, message?:string, error?:string}
 */
function momo_request(array $payment, string $phone, float $amount, string $note): array
{
    $tok = momo_token();
    if (!$tok['ok']) return ['ok' => false, 'error' => $tok['error']];

    $headers = [
        'Authorization: Bearer ' . $tok['token'],
        'X-Reference-Id: ' . momo_uuid_from($payment['external_id']),
        'X-Target-Environment: ' . MOMO_ENVIRONMENT,
        'Ocp-Apim-Subscription-Key: ' . trim(MOMO_SUBSCRIPTION_KEY),
        'Content-Type: application/json',
    ];
    $callback = payment_callback_url('momo');
    if ($callback) $headers[] = 'X-Callback-Url: ' . $callback;

    $r = payment_http('POST', rtrim(MOMO_BASE_URL, '/') . '/collection/v1_0/requesttopay', $headers, [
        'amount'     => number_format($amount, 0, '.', ''),
        'currency'   => MOMO_CURRENCY,
        'externalId' => $payment['ref'],
        'payer'      => ['partyIdType' => 'MSISDN', 'partyId' => $phone],
        'payerMessage' => mb_substr($note, 0, 160),
        'payeeNote'    => mb_substr(APP_NAME . ' ' . $payment['ref'], 0, 160),
    ]);

    /* 202 Accepted is the success case here: the prompt is on its way. */
    if ($r['status'] === 202) {
        return ['ok' => true, 'mode' => 'push',
                'message' => 'Check your phone and enter your MTN MoMo PIN to approve.'];
    }
    return ['ok' => false, 'error' => momo_explain($r)];
}

/** Ask MTN what happened to a request we pushed. */
function momo_status(array $payment): array
{
    $tok = momo_token();
    if (!$tok['ok']) return ['status' => 'pending', 'reason' => $tok['error']];

    $r = payment_http('GET',
        rtrim(MOMO_BASE_URL, '/') . '/collection/v1_0/requesttopay/'
            . momo_uuid_from($payment['external_id']),
        [
            'Authorization: Bearer ' . $tok['token'],
            'X-Target-Environment: ' . MOMO_ENVIRONMENT,
            'Ocp-Apim-Subscription-Key: ' . trim(MOMO_SUBSCRIPTION_KEY),
        ]);

    if (!$r['ok'] || !is_array($r['body'])) {
        /* Unknown is not failed. Leaving it pending is the safe answer: a
           payment we wrongly call failed is one the client paid twice. */
        return ['status' => 'pending', 'reason' => momo_explain($r), 'raw' => $r['body']];
    }

    $s = strtoupper((string) ($r['body']['status'] ?? ''));
    $reason = $r['body']['reason']['message'] ?? $r['body']['reason'] ?? null;
    if (is_array($reason)) $reason = json_encode($reason);

    return [
        'status' => match ($s) {
            'SUCCESSFUL' => 'succeeded',
            'FAILED'     => 'failed',
            default      => 'pending',
        },
        'reason' => $reason ? (string) $reason : null,
        'raw'    => $r['body'],
    ];
}

/** MTN wants a UUID v4; our external_id is 32 hex chars, which is one. */
function momo_uuid_from(string $hex): string
{
    $h = substr(str_pad($hex, 32, '0'), 0, 32);
    return sprintf('%s-%s-4%s-a%s-%s',
        substr($h, 0, 8), substr($h, 8, 4), substr($h, 13, 3),
        substr($h, 17, 3), substr($h, 20, 12));
}

/* ========================================================== Orange Money */

function om_token(): array
{
    static $cached = null;
    if ($cached !== null) return $cached;

    $r = payment_http('POST', rtrim(OM_BASE_URL, '/') . '/oauth/v3/token', [
        'Authorization: Basic ' . base64_encode(trim(OM_CLIENT_ID) . ':' . trim(OM_CLIENT_SECRET)),
        'Content-Type: application/x-www-form-urlencoded',
        'Accept: application/json',
    ], 'grant_type=client_credentials');

    if (!$r['ok'] || !is_array($r['body']) || empty($r['body']['access_token'])) {
        $cached = ['ok' => false, 'error' => om_explain($r)];
        return $cached;
    }
    $cached = ['ok' => true, 'token' => (string) $r['body']['access_token']];
    return $cached;
}

function om_explain(array $r): string
{
    if ($r['error']) return 'Orange Money could not be reached: ' . $r['error'];
    $body = is_array($r['body']) ? $r['body'] : [];
    $msg  = $body['message'] ?? $body['description'] ?? $body['error_description'] ?? null;
    return match ($r['status']) {
        401     => 'Orange Money rejected the credentials. Check OM_CLIENT_ID and OM_CLIENT_SECRET.',
        403     => 'Orange Money refused the request. Check that OM_MERCHANT_KEY belongs to this account '
                 . 'and that Web Payment is enabled for Cameroon.',
        default => 'Orange Money returned HTTP ' . $r['status'] . ($msg ? ': ' . $msg : '.'),
    };
}

/**
 * Orange hosts its own payment page, so this returns a URL to send the client
 * to rather than pushing a prompt.
 */
function om_request(array $payment, string $phone, float $amount, string $note): array
{
    $tok = om_token();
    if (!$tok['ok']) return ['ok' => false, 'error' => $tok['error']];

    $return = payment_return_url($payment['ref']);
    $r = payment_http('POST',
        rtrim(OM_BASE_URL, '/') . '/orange-money-webpay/cm/v1/webpayment',
        [
            'Authorization: Bearer ' . $tok['token'],
            'Content-Type: application/json',
            'Accept: application/json',
        ],
        [
            'merchant_key' => trim(OM_MERCHANT_KEY),
            'currency'     => MOMO_ENVIRONMENT === 'sandbox' ? 'OUV' : 'XAF',
            'order_id'     => $payment['ref'],
            'amount'       => (int) round($amount),
            'return_url'   => $return,
            'cancel_url'   => $return . '&cancelled=1',
            'notif_url'    => payment_callback_url('om') ?: $return,
            'lang'         => 'fr',
            'reference'    => mb_substr(APP_NAME, 0, 30),
        ]);

    if (!$r['ok'] || !is_array($r['body']) || empty($r['body']['payment_url'])) {
        return ['ok' => false, 'error' => om_explain($r)];
    }

    /* pay_token is needed to ask about this transaction later. */
    db_update('payments', [
        'provider_ref' => (string) ($r['body']['pay_token'] ?? ''),
    ], ['id' => $payment['id']]);

    return [
        'ok'           => true,
        'mode'         => 'redirect',
        'redirect_url' => (string) $r['body']['payment_url'],
        'message'      => 'Continue on the Orange Money page to approve the payment.',
    ];
}

function om_status(array $payment): array
{
    $tok = om_token();
    if (!$tok['ok']) return ['status' => 'pending', 'reason' => $tok['error']];

    $r = payment_http('POST',
        rtrim(OM_BASE_URL, '/') . '/orange-money-webpay/cm/v1/transactionstatus',
        [
            'Authorization: Bearer ' . $tok['token'],
            'Content-Type: application/json',
            'Accept: application/json',
        ],
        [
            'order_id'  => $payment['ref'],
            'amount'    => (int) round((float) $payment['amount']),
            'pay_token' => (string) $payment['provider_ref'],
        ]);

    if (!$r['ok'] || !is_array($r['body'])) {
        return ['status' => 'pending', 'reason' => om_explain($r), 'raw' => $r['body']];
    }

    $s = strtoupper((string) ($r['body']['status'] ?? ''));
    return [
        'status' => match ($s) {
            'SUCCESS'            => 'succeeded',
            'FAILED', 'EXPIRED'  => 'failed',
            'CANCELLED'          => 'cancelled',
            default              => 'pending',   // INITIATED / PENDING
        },
        'reason' => $r['body']['message'] ?? null,
        'raw'    => $r['body'],
    ];
}

/* ================================================================ dispatch */

/**
 * Start a collection. Never throws; the caller always gets a decision.
 */
function payment_start(array $in): array
{
    $provider = (string) ($in['provider'] ?? '');
    if (!in_array($provider, ['momo', 'om'], true)) {
        return ['ok' => false, 'error' => 'Choose MTN MoMo or Orange Money.'];
    }
    if (payments_state() !== 'live' || !payment_provider_ready($provider)) {
        return ['ok' => false, 'error' => 'Online payment is not connected yet.',
                'unconfigured' => true];
    }

    $phone = payment_normalise_phone((string) ($in['payer_phone'] ?? ''));
    if ($phone === null) {
        return ['ok' => false, 'error' => 'Enter a valid Cameroon mobile number, e.g. 6 55 99 88 77.'];
    }

    $amount = round((float) ($in['amount'] ?? 0));
    if ($amount <= 0) return ['ok' => false, 'error' => 'There is nothing to pay.'];

    $network = payment_detect_network($phone);
    if ($network !== null && $network !== $provider) {
        $other = $network === 'momo' ? 'an MTN' : 'an Orange';
        return ['ok' => false, 'error' => "That looks like $other number. "
              . 'Choose the matching payment method, or enter a different phone number.'];
    }

    $payment = payment_open([
        'appointment_id' => $in['appointment_id'] ?? null,
        'client_id'      => $in['client_id'] ?? null,
        'provider'       => $provider,
        'purpose'        => $in['purpose'] ?? 'deposit',
        'amount'         => $amount,
        'currency'       => $provider === 'momo' ? MOMO_CURRENCY : CURRENCY_CODE,
        'payer_phone'    => $phone,
    ]);

    $note = (string) ($in['note'] ?? (APP_NAME . ' deposit'));
    $res  = $provider === 'momo'
        ? momo_request($payment, $phone, $amount, $note)
        : om_request($payment, $phone, $amount, $note);

    if (!$res['ok']) {
        payment_settle((int) $payment['id'], 'failed', $res['error']);
        return ['ok' => false, 'error' => $res['error'], 'ref' => $payment['ref']];
    }

    return [
        'ok'           => true,
        'ref'          => $payment['ref'],
        'mode'         => $res['mode'],
        'message'      => $res['message'] ?? '',
        'redirect_url' => $res['redirect_url'] ?? null,
        'amount'       => $amount,
    ];
}

/**
 * Ask the provider where a payment got to, and record the answer.
 * Safe to call repeatedly — the client's browser polls this.
 */
function payment_refresh(string $ref): array
{
    $p = db_one('SELECT * FROM payments WHERE ref = ?', [$ref]);
    if (!$p) return ['ok' => false, 'error' => 'No such payment.'];

    if ($p['status'] !== 'pending') {
        return ['ok' => true, 'status' => $p['status'], 'reason' => $p['failure_reason']];
    }

    /* Give up on a prompt nobody answered, rather than spinning forever. */
    if (strtotime((string) $p['requested_at']) + PAYMENT_TIMEOUT_SECONDS < time()) {
        $res = $p['provider'] === 'momo' ? momo_status($p) : om_status($p);
        if ($res['status'] === 'pending') {
            payment_settle((int) $p['id'], 'timeout',
                'The prompt was not approved in time.');
            return ['ok' => true, 'status' => 'timeout',
                    'reason' => 'The payment prompt expired before it was approved.'];
        }
    } else {
        $res = $p['provider'] === 'momo' ? momo_status($p) : om_status($p);
    }

    if ($res['status'] !== 'pending') {
        payment_settle((int) $p['id'], $res['status'], $res['reason'] ?? null, $res['raw'] ?? null);
    }

    return ['ok' => true, 'status' => $res['status'], 'reason' => $res['reason'] ?? null];
}

/* ===================================================================== urls */

/** Where a provider should call us back. Null when we cannot be reached. */
function payment_callback_url(string $provider): ?string
{
    $host = $_SERVER['HTTP_HOST'] ?? '';
    if ($host === '' || str_contains($host, 'localhost') || str_contains($host, '127.0.0.1')) {
        /* A callback to localhost would never arrive. Polling covers us. */
        return null;
    }
    $scheme = !empty($_SERVER['HTTPS']) ? 'https' : 'http';
    return $scheme . '://' . $host . BASE_URL . '/api/payment-callback.php?provider=' . $provider;
}

/** Where Orange sends the client back to after their hosted page. */
function payment_return_url(string $ref): string
{
    $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $scheme = !empty($_SERVER['HTTPS']) ? 'https' : 'http';
    return $scheme . '://' . $host . BASE_URL . '/public/payment-return.php?ref=' . urlencode($ref);
}
