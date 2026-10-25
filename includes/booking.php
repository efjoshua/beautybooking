<?php
/**
 * Princess Beauty Salon — availability & booking
 *
 * The prototype faked availability with `(i + seed) % 5 === 0`. This computes
 * it: a slot is offered only when a qualified stylist is on shift, is free for
 * the whole service length plus turnaround, and the salon is still open when
 * the service would finish.
 */

declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/repo.php';
require_once __DIR__ . '/payments.php';
require_once __DIR__ . '/helpers.php';

/** Minutes since midnight. */
function t2m(string $hhmm): int
{
    [$h, $m] = array_map('intval', explode(':', substr($hhmm, 0, 5)));
    return $h * 60 + $m;
}
function m2t(int $mins): string
{
    return sprintf('%02d:%02d', intdiv($mins, 60) % 24, $mins % 60);
}

/**
 * Resolve a list of service refs to the facts booking needs.
 * @return array{services:array, duration:int, total:float, categories:array}
 */
function booking_resolve_services(array $refs): array
{
    $services = [];
    foreach ($refs as $ref) {
        $s = repo_service_by_ref((string) $ref);
        if ($s) $services[] = $s;
    }
    return [
        'services'   => $services,
        'duration'   => array_sum(array_column($services, 'duration_minutes')),
        'total'      => array_sum(array_map('floatval', array_column($services, 'price'))),
        'categories' => array_values(array_unique(array_column($services, 'category_slug'))),
    ];
}

/**
 * Bookable slots for one day.
 *
 * @param string      $date      Y-m-d
 * @param string[]    $refs      service refs
 * @param string|null $staffRef  a specific stylist, or null for "anyone"
 * @return array<array{time:string,available:bool,staff_id:?int}>
 */
function booking_slots(string $date, array $refs, ?string $staffRef = null): array
{
    $resolved = booking_resolve_services($refs);
    $duration = $resolved['duration'];
    if ($duration <= 0) return [];

    $weekday = (int) date('N', strtotime($date)) - 1;          // 0 = Monday
    $hours = db_one('SELECT * FROM business_hours WHERE weekday = ?', [$weekday]);
    if (!$hours || $hours['is_closed']) return [];

    $open  = t2m($hours['opens_at']);
    $close = t2m($hours['closes_at']);
    $step   = (int) (setting('slot_interval_minutes', '30'));
    $buffer = (int) (setting('buffer_minutes', '10'));

    /* Which stylists could do this work at all? */
    $eligible = repo_staff_for_categories($resolved['categories']);
    if ($staffRef) {
        $eligible = array_values(array_filter($eligible, fn($s) => $s['ref'] === $staffRef));
    }
    if (!$eligible) return [];

    /* Narrow to those actually on shift that weekday, and remember their shift. */
    $onShift = [];
    foreach ($eligible as $s) {
        $sh = db_one('SELECT * FROM staff_hours WHERE staff_id = ? AND weekday = ?',
                     [$s['id'], $weekday]);
        if ($sh) {
            $onShift[(int) $s['id']] = ['from' => t2m($sh['starts_at']), 'to' => t2m($sh['ends_at'])];
        }
    }
    if (!$onShift) return [];

    /* Existing commitments. Cancelled and no-show bookings free the chair. */
    [$in, $params] = db_in(array_keys($onShift));
    $busy = [];
    foreach (db_all(
        "SELECT staff_id, starts_at, ends_at FROM appointments
          WHERE booked_on = ? AND staff_id IN $in
            AND status NOT IN ('cancelled','no-show')",
        array_merge([$date], $params)) as $b) {
        $busy[(int) $b['staff_id']][] = [t2m($b['starts_at']), t2m($b['ends_at'])];
    }

    /* Bookings must respect the minimum notice period. */
    $minNotice = (int) setting('min_notice_hours', '2') * 60;
    $isToday = $date === app_today();

    $slots = [];
    for ($t = $open; $t + $duration <= $close; $t += $step) {
        $free = null;
        foreach ($onShift as $sid => $shift) {
            if ($t < $shift['from'] || $t + $duration > $shift['to']) continue;

            $clash = false;
            foreach ($busy[$sid] ?? [] as [$bs, $be]) {
                /* overlap, widened by the turnaround gap on both sides */
                if ($t < $be + $buffer && $bs < $t + $duration + $buffer) { $clash = true; break; }
            }
            if (!$clash) { $free = $sid; break; }
        }

        $tooSoon = $isToday && ($t < (int) date('H') * 60 + (int) date('i') + $minNotice);

        $slots[] = [
            'time'      => m2t($t),
            'available' => $free !== null && !$tooSoon,
            'staff_id'  => $free,
        ];
    }
    return $slots;
}

/** Days in the booking window with at least one free slot. */
function booking_open_days(array $refs, ?string $staffRef = null, int $days = 21): array
{
    $out = [];
    for ($i = 0; $i < $days; $i++) {
        $date = date('Y-m-d', strtotime(app_today() . " +$i day"));
        $slots = booking_slots($date, $refs, $staffRef);
        $free = count(array_filter($slots, fn($s) => $s['available']));
        $out[] = [
            'date'  => $date,
            'label' => $i === 0 ? 'Today' : date('D', strtotime($date)),
            'day'   => (int) date('j', strtotime($date)),
            'month' => date('M', strtotime($date)),
            'free'  => $free,
        ];
    }
    return $out;
}

/**
 * Create a booking. Runs in a transaction and re-checks availability while
 * holding the row, so two people submitting the same slot cannot both win.
 *
 * @return array{ok:bool, ref?:string, error?:string}
 */
function booking_create(array $in): array
{
    $refs   = array_values(array_filter((array) ($in['services'] ?? [])));
    $date   = (string) ($in['date'] ?? '');
    $time   = substr((string) ($in['time'] ?? ''), 0, 5);
    $name   = trim((string) ($in['name'] ?? ''));
    $phone  = trim((string) ($in['phone'] ?? ''));
    $email  = trim((string) ($in['email'] ?? ''));
    $note   = trim((string) ($in['note'] ?? ''));
    $staffR = ($in['staff'] ?? 'any');
    $staffR = ($staffR === 'any' || $staffR === '') ? null : (string) $staffR;

    /* ---- validation ---- */
    if (!$refs)                       return ['ok' => false, 'error' => 'Choose at least one service.'];
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date))
                                      return ['ok' => false, 'error' => 'Choose a valid date.'];
    if (!preg_match('/^\d{2}:\d{2}$/', $time))
                                      return ['ok' => false, 'error' => 'Choose a time slot.'];
    if ($name === '')                 return ['ok' => false, 'error' => 'Enter your name.'];
    if (strlen(preg_replace('/\D/', '', $phone)) < 8)
                                      return ['ok' => false, 'error' => 'Enter a valid phone number.'];
    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL))
                                      return ['ok' => false, 'error' => 'That email address is not valid.'];
    if ($date < app_today())        return ['ok' => false, 'error' => 'That date has already passed.'];

    $maxDays = (int) setting('booking_window_days', '21');
    if ($date > date('Y-m-d', strtotime(app_today() . " +$maxDays day")))
        return ['ok' => false, 'error' => "Bookings open only $maxDays days ahead."];

    $resolved = booking_resolve_services($refs);
    if (!$resolved['services'])       return ['ok' => false, 'error' => 'Those services are no longer available.'];

    return db_transaction(function () use (
        $refs, $date, $time, $name, $phone, $email, $note, $staffR, $resolved
    ) {
        /* Re-check availability inside the transaction — the slot may have
           gone in the seconds since the wizard last looked. */
        $slots = booking_slots($date, $refs, $staffR);
        $match = null;
        foreach ($slots as $s) if ($s['time'] === $time) { $match = $s; break; }

        if (!$match) {
            return ['ok' => false, 'error' => 'That time is outside our opening hours.'];
        }
        if (!$match['available']) {
            return ['ok' => false, 'error' =>
                'Sorry — that slot was taken while you were booking. Please pick another time.'];
        }

        /* Match an existing client on phone number, otherwise create one. */
        $digits = preg_replace('/\D/', '', $phone);
        $client = db_one(
            "SELECT * FROM clients
              WHERE deleted_at IS NULL
                AND REPLACE(REPLACE(REPLACE(phone,' ',''),'+',''),'-','') = ?",
            [$digits]);

        if ($client) {
            $clientId = (int) $client['id'];
            if ($email !== '' && empty($client['email'])) {
                db_update('clients', ['email' => $email], ['id' => $clientId]);
            }
        } else {
            $clientId = db_insert('clients', [
                'ref'       => next_ref('clients', 'CL-', 3),
                'name'      => $name,
                'phone'     => $phone,
                'email'     => $email ?: null,
                'joined_on' => app_today(),
                'fav_category_id' => db_val(
                    'SELECT id FROM service_categories WHERE slug = ?',
                    [$resolved['categories'][0] ?? 'hair']),
            ]);
        }

        $duration = $resolved['duration'];
        $ref = generate_booking_ref();

        $apptId = db_insert('appointments', [
            'ref'              => $ref,
            'client_id'        => $clientId,
            'staff_id'         => $match['staff_id'],
            'booked_on'        => $date,
            'starts_at'        => $time . ':00',
            'ends_at'          => m2t(t2m($time) + $duration) . ':00',
            'duration_minutes' => $duration,
            'status'           => 'pending',
            'payment_method'   => 'unpaid',
            'services_total'   => $resolved['total'],
            'addons_total'     => 0,
            'tip_total'        => 0,
            'grand_total'      => $resolved['total'],
            /* Frozen at booking time, like the prices above it. Raising the
               deposit percentage next month must not retroactively make this
               client owe more than they were told. */
            'deposit_due'      => payment_deposit_due((float) $resolved['total']),
            'deposit_paid'     => 0,
            'note'             => $note ?: null,
            'source'           => 'online',
            'reminder_opt_in'  => !empty($in['reminder']) ? 1 : 0,
        ]);

        foreach ($resolved['services'] as $s) {
            db_insert('appointment_items', [
                'appointment_id'   => $apptId,
                'service_id'       => $s['id'],
                'service_name'     => $s['name'],
                'unit_price'       => $s['price'],
                'duration_minutes' => $s['duration_minutes'],
                'qty'              => 1,
                'line_total'       => $s['price'],
            ]);
        }

        /* The caller needs to know whether to ask for money next. */
        return ['ok' => true, 'ref' => $ref, 'id' => $apptId,
                'deposit_due' => payment_deposit_due((float) $resolved['total'])];
    });
}

/** Look up a booking for the public "manage booking" page. */
function booking_lookup(string $ref, string $phone): ?array
{
    $digits = preg_replace('/\D/', '', $phone);
    if (strlen($digits) < 6) return null;

    $a = repo_appointment_by_ref(strtoupper(trim($ref)));
    if (!$a) return null;

    /* The reference alone is guessable; the phone number must match too. */
    $onFile = preg_replace('/\D/', '', (string) $a['client_phone']);
    if (substr($onFile, -8) !== substr($digits, -8)) return null;

    return $a;
}

/** Cancel a booking the client owns. */
function booking_cancel(string $ref, string $phone): array
{
    $a = booking_lookup($ref, $phone);
    if (!$a) return ['ok' => false, 'error' => 'We could not find that booking.'];
    if (in_array($a['status'], ['cancelled', 'completed'], true)) {
        return ['ok' => false, 'error' => 'That booking can no longer be cancelled.'];
    }

    $hours = (int) setting('free_cancel_hours', '4');
    $startsAt = strtotime($a['booked_on'] . ' ' . $a['starts_at']);
    $late = $startsAt - time() < $hours * 3600;

    db_update('appointments',
        ['status' => 'cancelled', 'cancelled_at' => date('Y-m-d H:i:s')],
        ['id' => $a['id']]);

    return ['ok' => true, 'late' => $late,
            'message' => $late
                ? 'Booking cancelled. As this was within ' . $hours . ' hours, any deposit is retained.'
                : 'Booking cancelled. No charge applies.'];
}
