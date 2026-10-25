<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/public-layout.php';
require_once __DIR__ . '/../includes/booking.php';

/* The reference alone is guessable, so the phone number must match too —
   otherwise anyone could enumerate PBS-#### and read clients' details. */
$ref   = (string) ($_GET['ref'] ?? '');
$phone = (string) ($_GET['phone'] ?? '');
$booking = ($ref && $phone) ? booking_lookup($ref, $phone) : null;

public_head([
    'title' => ($booking ? 'Booking Confirmed' : 'Booking') . ' — ' . APP_NAME,
    'page' => 'book.php',
    'noindex' => true,
]);
?>

<section class="section bg-soft">
  <div class="wrap">
  <?php if (!$booking): ?>

    <div class="confirm-card">
      <div class="card-body text-center">
        <span class="icon-badge mb-4" data-icon="alert"></span>
        <h3>We could not find that booking</h3>
        <p class="muted">The link may be incomplete. Look your booking up with your
           reference code and the phone number you booked with.</p>
        <div class="grid g-2 mt-6" style="gap:10px">
          <a class="btn btn-primary" href="manage-booking.php"><?= te('Find my booking') ?></a>
          <a class="btn btn-outline" href="book.php"><?= te('Make a new booking') ?></a>
        </div>
      </div>
    </div>

  <?php else: ?>

    <div class="confirm-card">
      <div class="confirm-top">
        <div class="tick-anim">
          <svg viewBox="0 0 24 24" width="38" height="38"><path d="M4 12.5l5.5 5.5L20 6.5"/></svg>
        </div>
        <h2 class="t-white mb-2">You're booked!</h2>
        <p class="mb-4" style="color:rgba(255,255,255,.66)">
          Keep this reference — you'll need it to change or cancel.</p>
        <span class="ref-code"><?= e($booking['ref']) ?></span>
      </div>

      <div class="card-body">
        <h4 class="mb-4">Appointment details</h4>
        <?php foreach ($booking['items'] as $it): ?>
          <div class="ticket-row"><span>Service</span><b><?= e($it['service_name']) ?></b></div>
        <?php endforeach; ?>
        <div class="ticket-row"><span>Stylist</span>
          <b><?= e($booking['staff_name'] ?: 'Any available stylist') ?></b></div>
        <div class="ticket-row"><span>Date</span><b><?= e(fmt_date_long($booking['booked_on'])) ?></b></div>
        <div class="ticket-row"><span>Time</span>
          <b><?= e(fmt_time($booking['starts_at'])) ?> – <?= e(fmt_time($booking['ends_at'])) ?>
            (<?= e(duration((int) $booking['duration_minutes'])) ?>)</b></div>
        <div class="ticket-row"><span>Booked for</span>
          <b><?= e($booking['client_name']) ?><br>
            <small class="muted" style="font-weight:400"><?= e($booking['client_phone']) ?></small></b></div>
        <div class="ticket-row"><span>Status</span><b><?= status_badge($booking['status']) ?></b></div>
        <div class="ticket-row"><span>Total</span>
          <b class="t-pink" style="font-size:1.25rem"><?= e(money($booking['grand_total'])) ?></b></div>

        <div class="alert alert-info mt-6">
          <span class="ico" data-icon="pin"></span>
          <span><b><?= e(setting('address', '')) ?></b>
            <p>Look for the gold crown sign above the entrance, first floor.</p></span>
        </div>

        <div class="grid g-2 mt-6" style="gap:10px">
          <a class="btn btn-primary" target="_blank" rel="noopener"
             href="https://wa.me/<?= e(setting('whatsapp', '')) ?>?text=<?=
               rawurlencode('Hello ' . APP_NAME . '! My booking reference is ' . $booking['ref'] . '.') ?>">
            Message us on WhatsApp</a>
          <a class="btn btn-outline" href="manage-booking.php?ref=<?= e($booking['ref']) ?>">Manage booking</a>
        </div>
        <div class="grid g-2 mt-3" style="gap:10px">
          <a class="btn btn-ghost btn-sm" href="book.php"><?= te('Book another appointment') ?></a>
          <a class="btn btn-ghost btn-sm" href="index.php"><?= te('Back to home') ?></a>
        </div>
      </div>

      <div class="card-foot text-center">
        <p class="tiny muted mb-0">Need to change or cancel? Call
          <a href="tel:<?= e(preg_replace('/\s/', '', setting('phone', ''))) ?>" class="t-pink"><?=
            e(setting('phone', '')) ?></a> at least
          <?= e(setting('free_cancel_hours', '4')) ?> hours before.</p>
      </div>
    </div>

  <?php endif; ?>
  </div>
</section>

<?php public_foot(['page' => 'book.php']); ?>
