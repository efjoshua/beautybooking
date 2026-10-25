<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/public-layout.php';
require_once __DIR__ . '/../includes/payments.php';

/* Service catalogue is injected server-side so step 1 paints instantly;
   everything after that (stylists, slots) is fetched live. */
$cats = repo_categories();
$svcs = repo_services();
$payload = [];
foreach ($cats as $c) {
    $payload[] = [
        'slug' => $c['slug'], 'name' => $c['name'], 'icon' => $c['icon'],
        'services' => array_values(array_map(fn($s) => [
            'ref' => $s['ref'], 'name' => $s['name'], 'desc' => $s['description'],
            'price' => (float) $s['price'], 'duration' => (int) $s['duration_minutes'],
            'popular' => (bool) $s['is_popular'],
        ], array_filter($svcs, fn($s) => $s['category_slug'] === $c['slug']))),
    ];
}
$staffNames = [];
foreach (repo_staff(true) as $s) $staffNames[$s['ref']] = $s['name'];

/* Payment posture is decided server-side and handed to the wizard, so the
   page can never offer a payment method the server would refuse. */
$payState     = payments_state();
$payProviders = payment_providers();

public_head([
    'title' => 'Book an Appointment — ' . APP_NAME,
    'description' => 'Book hair, braids, nails, lashes or spa treatments online. Real-time availability.',
    'page' => 'book.php',
]);
?>

<section class="page-hero" style="padding-block:48px 40px">
  <div class="wrap">
    <div class="crumb"><a href="index.php"><?= te('Home') ?></a> <span>/</span> <?= te('Book') ?></div>
    <h1 style="font-size:clamp(1.6rem,3.4vw,2.25rem)"><?= te('Book Your Appointment') ?></h1>
    <p class="mb-0"><?= $payState === 'live'
      ? te('Four quick steps. Secure your slot with a Mobile Money deposit.')
      : te('Four quick steps. Nothing is charged online — you settle at the salon.') ?></p>
  </div>
</section>

<section class="section" id="bookingWizard">
  <div class="wrap">
    <div class="stepper mb-8" id="stepper"></div>

    <div class="booking-shell">
      <div>
        <div class="wizard-step active" data-step="1">
          <h3>Choose your service<span class="t-pink">.</span></h3>
          <p class="muted small">Pick one or several — we'll block out enough time for all of them.</p>
          <div id="svcList" class="mt-6"></div>
        </div>

        <div class="wizard-step" data-step="2">
          <h3>Who would you like<span class="t-pink">?</span></h3>
          <p class="muted small">Only stylists qualified for your chosen services are shown.</p>
          <div id="staffList" class="grid mt-6" style="gap:10px"></div>
        </div>

        <div class="wizard-step" data-step="3">
          <h3>Pick a date &amp; time<span class="t-pink">.</span></h3>
          <p class="muted small">Live availability from our diary. Days marked “Full” have no room left.</p>
          <div class="date-strip mt-6" id="dateStrip"></div>
          <h4 class="mt-6 mb-3">Available times</h4>
          <div class="slot-grid" id="slotGrid"></div>
          <div class="alert alert-info mt-6">
            <span class="ico" data-icon="clock"></span>
            <span><b>Heads up</b><p>Saturday slots before 11:00 usually go within 48 hours.</p></span>
          </div>
        </div>

        <div class="wizard-step" data-step="4">
          <h3>Your details<span class="t-pink">.</span></h3>
          <p class="muted small">We only use this to confirm and remind you about your booking.</p>
          <form id="detailsForm" class="mt-6" novalidate>
            <div class="field-row">
              <div class="field">
                <label class="label" for="cName">Full name <span class="req">*</span></label>
                <input class="input" id="cName" required placeholder="e.g. Marlyse Nkeng">
              </div>
              <div class="field">
                <label class="label" for="cPhone">Phone / WhatsApp <span class="req">*</span></label>
                <input class="input" id="cPhone" type="tel" required placeholder="+237 6 XX XX XX XX">
              </div>
            </div>
            <div class="field">
              <label class="label" for="cEmail">Email <span class="muted">(optional)</span></label>
              <input class="input" id="cEmail" type="email" placeholder="you@example.com">
            </div>
            <div class="field">
              <label class="label" for="cNotes">Anything we should know?</label>
              <textarea class="textarea" id="cNotes" style="min-height:96px"
                placeholder="Sensitive scalp, a photo reference, arriving with a friend…"></textarea>
            </div>
            <label class="check"><input type="checkbox" id="cReminder" checked>
              Send me a WhatsApp reminder the day before</label>
          </form>
        </div>

        <div class="wizard-step" data-step="5">
          <h3>Review &amp; confirm<span class="t-pink">.</span></h3>
          <p class="muted small">Check everything below, then confirm. You can still change or cancel later.</p>
          <div class="card mt-6" id="reviewBox"></div>

<?php if ($payState !== 'off'): ?>
          <div class="card mt-4" id="payBlock">
            <h4 class="mb-3">Payment</h4>
<?php if ($payState === 'unconfigured'): ?>
            <div class="alert alert-info mb-0"><span class="ico" data-icon="alert"></span>
              <span><b>Online payment is not connected yet</b>
                <p>MTN MoMo and Orange Money are not set up on this site, so there is nothing
                   to pay now. Confirm your booking below and settle at the salon.</p></span></div>
            <div class="pay-methods mt-4">
<?php foreach ($payProviders as $pp): ?>
              <div class="pay-method is-off">
                <span class="pay-logo pay-<?= e($pp['code']) ?>"><?= e($pp['short']) ?></span>
                <span class="small muted">Not configured</span>
              </div>
<?php endforeach; ?>
            </div>
<?php else: ?>
            <p class="small muted" id="payIntro">A deposit holds your slot. The balance is paid
              at the salon after your appointment.</p>
            <div class="pay-methods mt-3">
<?php foreach ($payProviders as $pp): ?>
              <label class="pay-method<?= $pp['ready'] ? '' : ' is-off' ?>">
                <input type="radio" name="payProvider" value="<?= e($pp['code']) ?>"
                       <?= $pp['ready'] ? '' : 'disabled' ?>>
                <span class="pay-logo pay-<?= e($pp['code']) ?>"><?= e($pp['short']) ?></span>
                <span class="small muted"><?= $pp['ready']
                    ? e($pp['hint']) : 'Not configured on this site' ?></span>
              </label>
<?php endforeach; ?>
            </div>
<?php endif; ?>
          </div>
<?php endif; ?>

          <div class="flex gap-3 mt-4 wrapflex">
            <button class="btn btn-ghost btn-sm" data-goto="1"><?= te('Change service') ?></button>
            <button class="btn btn-ghost btn-sm" data-goto="2"><?= te('Change stylist') ?></button>
            <button class="btn btn-ghost btn-sm" data-goto="3"><?= te('Change time') ?></button>
            <button class="btn btn-ghost btn-sm" data-goto="4"><?= te('Edit details') ?></button>
          </div>
        </div>

        <div class="wizard-step" data-step="6">
          <h3>Pay your deposit<span class="t-pink">.</span></h3>
          <p class="muted small" id="payStepIntro">Your booking is held. Approve the payment on
            your phone to confirm it.</p>
          <div class="card mt-6" id="payPanel"></div>
        </div>

        <div class="wizard-nav">
          <button class="btn btn-ghost hidden" id="btnBack">← <?= te('Back') ?></button>
          <button class="btn btn-primary" id="btnNext" style="margin-left:auto"><?= te('Continue') ?> →</button>
          <button class="btn btn-gold hidden" id="btnConfirm" style="margin-left:auto"><?= te('Confirm Booking') ?></button>
        </div>
      </div>

      <aside class="summary">
        <h4><?= te('Your booking') ?></h4>
        <p class="tiny mb-4" style="color:rgba(255,255,255,.45)" id="summaryCount">0 services</p>
        <div id="summaryBody"></div>
        <div class="total">
          <span class="tiny" style="letter-spacing:.16em;text-transform:uppercase;color:rgba(255,255,255,.5)">Total</span>
          <b id="summaryTotal">0 <?= e(CURRENCY) ?></b>
        </div>
        <div id="summaryDeposit"></div>
        <p class="tiny mt-4 mb-0" style="color:rgba(255,255,255,.45)">
          <?= $payState === 'live'
              ? 'A deposit may be required to hold your slot.'
              : 'Nothing is charged now.' ?> Free cancellation up to
          <?= e(setting('free_cancel_hours', '4')) ?> hours before.</p>
      </aside>
    </div>
  </div>
</section>

<?php
public_foot([
    'page' => 'book.php',
    'inline' => '<script>window.PBS_CATEGORIES = ' . json_encode($payload) . ';'
              . 'window.PBS_STAFF_NAMES = ' . json_encode($staffNames) . ';'
              . 'window.PBS_PAY = ' . json_encode([
                    'state'     => $payState,
                    'providers' => array_map(fn($p) => [
                        'code' => $p['code'], 'short' => $p['short'],
                        'ready' => $p['ready'], 'mode' => $p['mode'], 'hint' => $p['hint'],
                    ], $payProviders),
                    'threshold' => (float) setting('deposit_threshold', '20000'),
                    'percent'   => (float) setting('deposit_percent', '30'),
                    'required'  => $payState === 'live'
                                   && setting('payments_require_deposit', '0') === '1',
                ]) . ';</script>'
              . '<script src="' . url('assets/js/booking-live.js') . '"></script>',
]);
