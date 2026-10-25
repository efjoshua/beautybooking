<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/public-layout.php';

public_head([
    'title' => 'Terms &amp; Booking Policy — ' . APP_NAME,
    'page' => 'terms.php',
]);
?>

<section class="page-hero">
  <div class="wrap">
    <div class="crumb"><a href="<?= e(url('public/index.php')) ?>">Home</a> <span>/</span> Terms</div>
    <h1>Terms &amp; Booking Policy</h1>
    <p>Last updated 20 August 2026</p>
  </div>
</section>

<section class="section">
  <div class="wrap wrap-narrow legal">

    <div class="grid g-2 mb-8" style="gap:12px">
      <div class="card"><h4>Free cancellation</h4>
        <p class="small muted mb-0">Up to 4 hours before your appointment.</p></div>
      <div class="card"><h4>15-minute grace</h4>
        <p class="small muted mb-0">We hold your chair, then release the slot.</p></div>
      <div class="card"><h4>Deposits</h4>
        <p class="small muted mb-0">30% on services above 20,000 FCFA.</p></div>
      <div class="card"><h4>Quoted before we start</h4>
        <p class="small muted mb-0">Any extra cost is agreed with you first.</p></div>
    </div>

    <h2>1. Making a booking</h2>
    <p>A booking is confirmed when you receive a reference code by WhatsApp. Until then the
      slot is not held. Bookings can be made up to three weeks ahead; for dates beyond that,
      contact the salon directly.</p>
    <p>You must be 18 or over to book. Children are very welcome as clients but must be
      booked in by a parent or guardian who stays for the appointment.</p>

    <h2>2. Deposits</h2>
    <p>Services priced above <?= e(money((float) setting('deposit_threshold', '20000'))) ?>, and most Saturday bookings, require a <?= e(setting('deposit_percent', '30')) ?>% deposit
      by Mobile Money to hold the slot. The deposit is deducted from your final bill.</p>
    <p>Deposits are refundable if you cancel more than <?= e(setting('free_cancel_hours', '4')) ?> hours before your appointment, and
      are retained if you cancel later than that or do not arrive.</p>

    <h2>3. Cancelling or rescheduling</h2>
    <ul>
      <li>More than <?= e(setting('free_cancel_hours', '4')) ?> hours before: free, and any deposit is refunded or moved to the new date.</li>
      <li>Less than <?= e(setting('free_cancel_hours', '4')) ?> hours before: the deposit is retained.</li>
      <li>You can cancel through <a href="<?= e(url('public/manage-booking.php')) ?>" class="t-pink">Manage Booking</a>,
        on WhatsApp, or by calling the salon.</li>
    </ul>

    <h2>4. Lateness</h2>
    <p>We hold your chair for 15 minutes. After that we may need to shorten your service to
      protect the next client's appointment, or release the slot entirely and record a no-show.
      Message us if you are running late and we will do what we can.</p>

    <h2>5. No-shows</h2>
    <p>Not arriving without telling us is recorded against your record. After two no-shows we
      may ask for a deposit on all future bookings.</p>

    <h2>6. Pricing</h2>
    <p>Prices shown on this site are what you pay. Where a style needs materially more product
      or time than the standard service — extra length, extra density, colour correction — we
      quote the difference before starting work, never after.</p>
    <p>Braid prices cover labour only. You may bring your own hair, or buy it at the counter.</p>

    <h2>7. Your hair, skin and health</h2>
    <p>Please tell your stylist about allergies, scalp sensitivity, recent chemical treatments,
      pregnancy, or any skin condition before we begin. For lash and facial services we may
      require a patch test 24 hours in advance.</p>
    <p>We reserve the right to decline a requested style where we believe it will damage your
      hair or skin. We will always explain why and offer an alternative.</p>

    <h2>8. Results</h2>
    <p>We will do everything we reasonably can to achieve the look you asked for, but results
      vary with hair type, condition and history. If you are unhappy, tell us before you leave
      and we will put it right where we can.</p>

    <h2>9. Personal belongings</h2>
    <p>Please keep your belongings with you. The salon is not responsible for items lost or
      damaged on the premises.</p>

    <h2>10. Payment</h2>
    <p>We accept cash, MTN Mobile Money, Orange Money and card, settled at the chair after your
      service. Outstanding balances must be cleared before a further appointment is booked.</p>

    <h2>11. Photography</h2>
    <p>We sometimes photograph finished work for our Instagram and TikTok. We always ask first,
      and you are entirely free to say no — it will not affect your service in any way.</p>

    <h2>12. Changes to these terms</h2>
    <p>We may update these terms. The version in force is the one published here on the day of
      your appointment.</p>

    <div class="alert alert-info mt-8">
      <span class="ico" data-icon="alert"></span>
      <span><b>Prototype notice</b>
        <p>This is placeholder policy text for the design prototype. Have it reviewed by a
           legal adviser before the site goes live.</p></span>
    </div>

    <div class="text-center mt-10">
      <a href="<?= e(url('public/book.php')) ?>" class="btn btn-primary">Book an Appointment</a>
      <a href="<?= e(url('public/faq.php')) ?>" class="btn btn-ghost">Read the FAQ</a>
    </div>

  </div>
</section>


<?php public_foot(['page' => 'terms.php']); ?>
