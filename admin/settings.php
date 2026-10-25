<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/admin-layout.php';
require_once __DIR__ . '/../includes/payments.php';

require_permission('settings');

$saved = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require();
    $panel = (string) ($_POST['panel'] ?? '');

    if ($panel === 'hours') {
        for ($d = 0; $d < 7; $d++) {
            db_update('business_hours', [
                'opens_at'  => (string) ($_POST["open_$d"] ?? '09:00') . ':00',
                'closes_at' => (string) ($_POST["close_$d"] ?? '19:00') . ':00',
                'is_closed' => empty($_POST["on_$d"]) ? 1 : 0,
            ], ['weekday' => $d]);
        }
        audit('Business hours updated', 'settings');
    } else {
        /* Only keys we know about — never write arbitrary POST data into settings. */
        $allowed = [
            'salon_name', 'tagline', 'phone', 'whatsapp', 'email', 'address',
            'instagram', 'tiktok', 'facebook',
            'booking_window_days', 'min_notice_hours', 'slot_interval_minutes',
            'buffer_minutes', 'free_cancel_hours', 'deposit_threshold', 'deposit_percent',
            'default_commission_pct', 'commission_basis', 'tax_rate',
            'brand_black', 'brand_gold', 'brand_pink',
        ];
        foreach ($allowed as $k) {
            if (array_key_exists($k, $_POST)) {
                db_run('INSERT INTO settings (`key`, `value`) VALUES (?, ?)
                        ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)',
                       [$k, trim((string) $_POST[$k])]);
            }
        }

        /* Checkboxes are absent from the POST when unchecked, so the loop
           above can only ever turn these ON. Switching payments off has to be
           possible, so each toggle is written explicitly — but only when the
           form that owns it was the one submitted, or saving the opening
           hours would silently disable payments. */
        $toggles = ['rules' => ['payments_enabled', 'payments_require_deposit']];
        foreach ($toggles[$panel] ?? [] as $k) {
            db_run('INSERT INTO settings (`key`, `value`) VALUES (?, ?)
                    ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)',
                   [$k, empty($_POST[$k]) ? '0' : '1']);
        }
        audit("Settings updated ($panel)", 'settings');
    }
    redirect('admin/settings.php?saved=1&tab=' . urlencode($panel));
}

$saved = !empty($_GET['saved']);
$tab   = (string) ($_GET['tab'] ?? 'salon');
$hours = repo_business_hours();
$payMix = repo_revenue_by_payment(date('Y-m-01', strtotime(app_today())), date('Y-m-t', strtotime(app_today())));
$payTotals = [];
foreach ($payMix as $p) $payTotals[$p['key']] = $p['amount'];

function s(string $k, string $d = ''): string { return (string) setting($k, $d); }

admin_head(['title' => 'Settings', 'subtitle' => 'Salon configuration', 'page' => 'settings.php']);
?>

<div class="page-actions">
  <div><h2 class="mb-1" style="font-size:1.375rem">Settings</h2>
    <p class="small muted mb-0">Salon profile, hours, branding, payments and rules</p></div>
</div>

<?php if ($saved): ?>
  <div class="alert alert-success mb-4"><span class="ico" data-icon="check"></span>
    <span><b>Saved</b><p>Your changes are live on the public site immediately.</p></span></div>
<?php endif; ?>

<div class="tabs mb-6" id="settingsTabs">
  <?php foreach (['salon' => 'Salon info', 'hours' => 'Business hours', 'branding' => 'Branding',
                  'payments' => 'Payments', 'rules' => 'Booking rules', 'tax' => 'Tax &amp; commission'] as $k => $l): ?>
    <button class="tab<?= $tab === $k ? ' active' : '' ?>" data-panel="<?= $k ?>"><?= $l ?></button>
  <?php endforeach; ?>
</div>

<!-- SALON -->
<div data-settings-panel="salon"<?= $tab === 'salon' ? '' : ' class="hidden"' ?>>
  <form method="post"><?= csrf_field() ?><input type="hidden" name="panel" value="salon">
    <div class="dash-grid dash-1-1">
      <div class="card"><h4 class="mb-5">Salon profile</h4>
        <div class="field"><label class="label">Salon name</label>
          <input class="input" name="salon_name" value="<?= e(s('salon_name')) ?>"></div>
        <div class="field"><label class="label">Tagline</label>
          <input class="input" name="tagline" value="<?= e(s('tagline')) ?>"></div>
        <div class="field mb-0"><label class="label">Address</label>
          <input class="input" name="address" value="<?= e(s('address')) ?>"></div>
      </div>
      <div class="card"><h4 class="mb-5">Contact &amp; socials</h4>
        <div class="field"><label class="label">Phone</label>
          <input class="input" name="phone" value="<?= e(s('phone')) ?>"></div>
        <div class="field"><label class="label">WhatsApp number (digits only)</label>
          <input class="input" name="whatsapp" value="<?= e(s('whatsapp')) ?>"></div>
        <div class="field"><label class="label">Email</label>
          <input class="input" type="email" name="email" value="<?= e(s('email')) ?>"></div>
        <div class="field-row">
          <div class="field"><label class="label">Instagram</label>
            <input class="input" name="instagram" value="<?= e(s('instagram')) ?>"></div>
          <div class="field"><label class="label">TikTok</label>
            <input class="input" name="tiktok" value="<?= e(s('tiktok')) ?>"></div>
        </div>
        <div class="field mb-0"><label class="label">Facebook</label>
          <input class="input" name="facebook" value="<?= e(s('facebook')) ?>"></div>
      </div>
    </div>
    <div class="mt-4"><button class="btn btn-primary btn-sm">Save salon info</button></div>
  </form>
</div>

<!-- HOURS -->
<div data-settings-panel="hours"<?= $tab === 'hours' ? '' : ' class="hidden"' ?>>
  <form method="post"><?= csrf_field() ?><input type="hidden" name="panel" value="hours">
    <div class="card card-pad-0">
      <div class="card-head"><div><h4>Business hours</h4>
        <span class="tiny muted">Controls which slots the public booking wizard offers</span></div></div>
      <div class="table-wrap">
        <table class="table table-cards" style="min-width:560px">
          <thead><tr><th>Day</th><th>Open</th><th>From</th><th>To</th></tr></thead>
          <tbody>
          <?php foreach ($hours as $h): $d = (int) $h['weekday']; ?>
            <tr><td class="strong"><?= e($h['day_name']) ?></td>
              <td><label class="switch"><input type="checkbox" name="on_<?= $d ?>" value="1"
                    <?= (int) $h['is_closed'] ? '' : 'checked' ?>><span></span></label></td>
              <td><input class="input" type="time" name="open_<?= $d ?>"
                    value="<?= e(fmt_time($h['opens_at'])) ?>" style="width:130px"></td>
              <td><input class="input" type="time" name="close_<?= $d ?>"
                    value="<?= e(fmt_time($h['closes_at'])) ?>" style="width:130px"></td></tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <div class="card-foot flex justify-between wrapflex gap-3">
        <p class="data-note mb-0">Individual stylist hours on the Staff page narrow these further.</p>
        <button class="btn btn-primary btn-sm">Save hours</button>
      </div>
    </div>
  </form>
</div>

<!-- BRANDING -->
<div data-settings-panel="branding"<?= $tab === 'branding' ? '' : ' class="hidden"' ?>>
  <form method="post"><?= csrf_field() ?><input type="hidden" name="panel" value="branding">
    <div class="dash-grid dash-1-1">
      <div class="card"><h4 class="mb-5">Brand colours</h4>
        <div class="field-row">
          <div class="field"><label class="label">Primary (black)</label>
            <input class="input" name="brand_black" value="<?= e(s('brand_black', '#0B0B0B')) ?>"></div>
          <div class="field"><label class="label">Accent (gold)</label>
            <input class="input" name="brand_gold" value="<?= e(s('brand_gold', '#C9A227')) ?>"></div>
        </div>
        <div class="field"><label class="label">Highlight (hot pink)</label>
          <input class="input" name="brand_pink" value="<?= e(s('brand_pink', '#E91E8C')) ?>"></div>
        <div class="flex gap-2 mt-4">
          <span style="width:44px;height:44px;border-radius:8px;background:<?= e(s('brand_black', '#0B0B0B')) ?>"></span>
          <span style="width:44px;height:44px;border-radius:8px;background:<?= e(s('brand_gold', '#C9A227')) ?>"></span>
          <span style="width:44px;height:44px;border-radius:8px;background:<?= e(s('brand_pink', '#E91E8C')) ?>"></span>
        </div>
        <p class="data-note mt-4 mb-0">Stored here for reference. The live palette lives in
          <b>assets/css/base.css</b> as CSS custom properties — the chart colours are
          validated for colour-vision deficiency and should not be changed casually.</p>
      </div>
      <div class="card"><h4 class="mb-5">Logo &amp; imagery</h4>
        <div class="field"><label class="label">Logo</label>
          <img src="<?= url('assets/img/logo.svg') ?>" alt="Current logo"
               style="width:170px;border:1px solid var(--n-200);border-radius:8px"></div>
        <div class="field mb-0"><label class="label">Favicon</label>
          <img src="<?= url('assets/img/favicon.svg') ?>" alt="Current favicon" style="width:44px"></div>
        <p class="data-note mt-4 mb-0">Image upload is not wired up. Replace the files in
          <b>assets/img/</b> directly for now.</p>
      </div>
    </div>
    <div class="mt-4"><button class="btn btn-primary btn-sm">Save branding</button></div>
  </form>
</div>

<!-- PAYMENTS -->
<div data-settings-panel="payments"<?= $tab === 'payments' ? '' : ' class="hidden"' ?>>
  <div class="card card-pad-0">
    <div class="card-head"><div><h4>Accepted payment methods</h4>
      <span class="tiny muted">Amounts are this month's actual takings by method</span></div></div>
    <div class="card-body"><div class="grid" style="gap:12px">
      <?php foreach ([['cash', 'Cash', 'Settled at the chair'],
                      ['momo', 'MTN Mobile Money', 'Most used method'],
                      ['om', 'Orange Money', 'Second mobile wallet'],
                      ['card', 'Card (Visa / Mastercard)', 'Via POS terminal']] as [$k, $label, $desc]): ?>
        <label class="flex items-center gap-4 card card-flat" style="margin:0">
          <span class="switch"><input type="checkbox" checked disabled><span></span></span>
          <span style="flex:1"><b><?= e($label) ?></b><br><span class="small muted"><?= e($desc) ?></span></span>
          <span class="badge badge-plain badge-gold"><?= e(money($payTotals[$k] ?? 0)) ?> this month</span>
        </label>
      <?php endforeach; ?>
    </div>
    <p class="data-note mt-4 mb-0">All four methods are always available in the booking flow and
      at the counter. Disabling one is not yet wired up.</p>
    </div>
  </div>
</div>

<!-- RULES -->
<div data-settings-panel="rules"<?= $tab === 'rules' ? '' : ' class="hidden"' ?>>
  <form method="post"><?= csrf_field() ?><input type="hidden" name="panel" value="rules">
    <div class="dash-grid dash-1-1">
      <div class="card"><h4 class="mb-5">Booking window</h4>
        <div class="field-row">
          <div class="field"><label class="label">Book up to (days ahead)</label>
            <input class="input" type="number" name="booking_window_days" min="1" max="365"
                   value="<?= e(s('booking_window_days', '21')) ?>"></div>
          <div class="field"><label class="label">Minimum notice (hours)</label>
            <input class="input" type="number" name="min_notice_hours" min="0" max="72"
                   value="<?= e(s('min_notice_hours', '2')) ?>"></div>
        </div>
        <div class="field-row">
          <div class="field"><label class="label">Slot interval (minutes)</label>
            <select class="select" name="slot_interval_minutes">
              <?php foreach ([15, 30, 60] as $i): ?>
                <option value="<?= $i ?>"<?= s('slot_interval_minutes', '30') == $i ? ' selected' : '' ?>><?= $i ?></option>
              <?php endforeach; ?></select></div>
          <div class="field"><label class="label">Turnaround between clients (min)</label>
            <input class="input" type="number" name="buffer_minutes" min="0" max="60"
                   value="<?= e(s('buffer_minutes', '10')) ?>"></div>
        </div>
        <div class="field mb-0"><label class="label">Free cancellation window (hours)</label>
          <input class="input" type="number" name="free_cancel_hours" min="0" max="72"
                 value="<?= e(s('free_cancel_hours', '4')) ?>"></div>
        <p class="data-note mt-4 mb-0">These are enforced by the availability engine, not just displayed —
          changing the interval changes the slots offered on the next page load.</p>
      </div>
      <div class="card"><h4 class="mb-5">Deposits &amp; online payment</h4>

        <?php $payState = payments_state(); ?>
        <label class="check mb-3"><input type="checkbox" name="payments_enabled" value="1"
          <?= s('payments_enabled', '0') === '1' ? 'checked' : '' ?>>
          <span><b>Take payments online</b><br>
          <span class="small muted">Off means clients book with nothing to pay and settle at
          the salon. Turn it on when you are ready to collect deposits.</span></span></label>

        <label class="check mb-4"><input type="checkbox" name="payments_require_deposit" value="1"
          <?= s('payments_require_deposit', '0') === '1' ? 'checked' : '' ?>>
          <span><b>Require a deposit to hold a slot</b><br>
          <span class="small muted">Only applies to bookings at or above the threshold below.
          Clients can always choose to pay at the salon instead — a booking is never lost
          because a payment failed.</span></span></label>

        <?php if ($payState === 'unconfigured'): ?>
        <div class="alert alert-warning mb-4"><span class="ico" data-icon="alert"></span>
          <span><b>Payments are on, but no provider is connected</b>
            <p>Clients see the MTN MoMo and Orange Money section on the booking page with a
               note that payment is not set up, and finish their booking without paying.
               Nothing is broken and nobody is blocked. To start collecting, put your API
               credentials in <b>includes/config.php</b> — no other change is needed.</p></span></div>
        <?php elseif ($payState === 'live'): ?>
        <div class="alert alert-success mb-4"><span class="ico" data-icon="check"></span>
          <span><b>Connected and collecting</b>
            <p><?php
              $live = array_filter(payment_providers(), fn($p) => $p['ready']);
              echo e(implode(' and ', array_column($live, 'name')));
            ?> can take payments. Every attempt, including the ones that fail, is recorded
               against the booking.</p></span></div>
        <?php endif; ?>

        <div class="field-row">
          <div class="field"><label class="label">Deposit threshold (<?= e(CURRENCY) ?>)</label>
            <input class="input" type="number" name="deposit_threshold" min="0" step="1"
                   value="<?= e(s('deposit_threshold', '20000')) ?>"></div>
          <div class="field"><label class="label">Deposit percentage</label>
            <input class="input" type="number" name="deposit_percent" min="0" max="100"
                   value="<?= e(s('deposit_percent', '30')) ?>"></div>
        </div>

        <table class="table mt-4" style="min-width:0">
          <thead><tr><th>Provider</th><th>Status</th></tr></thead>
          <tbody>
          <?php foreach (payment_providers() as $pp): ?>
            <tr>
              <td><span class="pay-logo pay-<?= e($pp['code']) ?>"><?= e($pp['short']) ?></span></td>
              <td><?= $pp['ready']
                  ? '<span class="badge badge-success">Connected</span>'
                  : '<span class="badge">Keys not set</span>' ?></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>

        <p class="data-note mb-0">The threshold and percentage are quoted to clients on the
          Services and Terms pages whether or not payment is connected, because they describe
          the salon's policy rather than a charge.</p>
      </div>
    </div>
    <div class="mt-4"><button class="btn btn-primary btn-sm">Save booking rules</button></div>
  </form>
</div>

<!-- TAX -->
<div data-settings-panel="tax"<?= $tab === 'tax' ? '' : ' class="hidden"' ?>>
  <form method="post"><?= csrf_field() ?><input type="hidden" name="panel" value="tax">
    <div class="dash-grid dash-1-1">
      <div class="card"><h4 class="mb-5">Tax</h4>
        <div class="field-row">
          <div class="field"><label class="label">Currency</label>
            <input class="input" value="<?= e(CURRENCY) ?> (<?= e(CURRENCY_CODE) ?>)" disabled></div>
          <div class="field"><label class="label">Tax rate (%)</label>
            <input class="input" type="number" name="tax_rate" min="0" max="100" step="0.5"
                   value="<?= e(s('tax_rate', '0')) ?>"></div>
        </div>
        <?php if ((float) s('tax_rate', '0') > 0): ?>
        <div class="alert alert-warning mt-3 mb-3"><span class="ico" data-icon="alert"></span>
          <span><b>This rate is stored but not yet applied</b>
            <p>No figure on any report is adjusted by it. Applying VAT properly needs a
               decision this system cannot make for you — whether listed prices already
               include tax, and whether retail is taxed on the same basis as services.
               Until that is built, treat every reported figure as tax-inclusive takings.</p></span></div>
        <?php endif; ?>
        <p class="data-note mb-0">Currency is set in <b>includes/config.php</b> because it
          affects formatting everywhere, not just reports.</p>
      </div>
      <div class="card"><h4 class="mb-5">Commission defaults</h4>
        <div class="field"><label class="label">Default rate for new staff (%)</label>
          <input class="input" type="number" name="default_commission_pct" min="0" max="100" step="0.5"
                 value="<?= e(s('default_commission_pct', '35')) ?>"></div>
        <div class="field mb-0"><label class="label">Commission is calculated on</label>
          <select class="select" name="commission_basis">
            <?php foreach (['service_only' => 'Service price only',
                            'service_addons' => 'Service price + add-ons',
                            'service_addons_tips' => 'Service + add-ons + tips'] as $k => $l): ?>
              <option value="<?= $k ?>"<?= s('commission_basis', 'service_only') === $k ? ' selected' : '' ?>><?= e($l) ?></option>
            <?php endforeach; ?></select></div>
        <div class="alert alert-warning mt-4"><span class="ico" data-icon="alert"></span>
          <span><b>This changes past figures</b>
            <p>The commission report recalculates from this basis every time it loads, so
               changing it restates what every stylist is owed — including for work already done.</p></span></div>
      </div>
    </div>
    <div class="mt-4"><button class="btn btn-primary btn-sm">Save tax &amp; commission</button></div>
  </form>
</div>

<?php
admin_foot(['page' => 'settings.php', 'inline' => '<script>
document.querySelectorAll("#settingsTabs .tab").forEach(function (b) {
  b.addEventListener("click", function () {
    document.querySelectorAll("#settingsTabs .tab").forEach(function (x) { x.classList.remove("active"); });
    b.classList.add("active");
    var id = b.getAttribute("data-panel");
    document.querySelectorAll("[data-settings-panel]").forEach(function (p) {
      p.classList.toggle("hidden", p.getAttribute("data-settings-panel") !== id);
    });
  });
});
</script>']);
