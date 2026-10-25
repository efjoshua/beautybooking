<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/public-layout.php';

public_head([
    'title' => 'Manage Your Booking — ' . APP_NAME,
    'description' => 'Look up, reschedule or cancel your appointment using your reference code.',
    'page' => 'manage-booking.php',
]);
?>

<section class="page-hero">
  <div class="wrap">
    <div class="crumb"><a href="index.php"><?= te('Home') ?></a> <span>/</span> <?= te('Manage Booking') ?></div>
    <h1>Manage Your Booking</h1>
    <p>Check, reschedule or cancel an appointment. Your reference code is on the
       confirmation we gave you.</p>
  </div>
</section>

<section class="section">
  <div class="wrap">
    <div class="lookup-card">
      <div class="card">
        <h3 class="mb-2">Find my appointment</h3>
        <p class="small muted">Both fields must match the booking — the reference alone
           is not enough to open someone's details.</p>
        <form id="lookupForm" class="mt-5">
          <div class="field">
            <label class="label" for="lookupRef">Booking reference <span class="req">*</span></label>
            <input class="input" id="lookupRef" name="ref" required
                   value="<?= e((string) ($_GET['ref'] ?? '')) ?>"
                   placeholder="e.g. PBS-4821" style="letter-spacing:.14em;text-transform:uppercase">
            <p class="hint">Starts with PBS- followed by four digits.</p>
          </div>
          <div class="field">
            <label class="label" for="lookupPhone">Phone number used to book <span class="req">*</span></label>
            <input class="input" id="lookupPhone" name="phone" type="tel" required
                   placeholder="+237 6 XX XX XX XX">
          </div>
          <button type="submit" class="btn btn-primary btn-block"><?= te('Find my booking') ?></button>
        </form>
      </div>

      <div id="lookupResult" class="hidden mt-6"></div>

      <div class="divider-or">Or</div>

      <div class="grid" style="gap:10px">
        <a href="https://wa.me/<?= e(setting('whatsapp', '')) ?>" target="_blank" rel="noopener" class="card card-hover">
          <div class="info-tile"><span class="icon-badge icon-badge-sm" data-icon="whatsapp"></span>
            <div><h4>Message us on WhatsApp</h4>
              <p>Fastest for last-minute changes — we reply within about two hours.</p></div></div></a>
        <a href="tel:<?= e(preg_replace('/\s/', '', setting('phone', ''))) ?>" class="card card-hover">
          <div class="info-tile"><span class="icon-badge icon-badge-sm" data-icon="phone"></span>
            <div><h4>Call the salon</h4><p><?= e(setting('phone', '')) ?> — during opening hours.</p></div></div></a>
        <a href="book.php" class="card card-hover">
          <div class="info-tile"><span class="icon-badge icon-badge-sm" data-icon="plus"></span>
            <div><h4>Make a new booking</h4><p>No existing appointment? Start a fresh one here.</p></div></div></a>
      </div>

      <div class="alert alert-warning mt-6">
        <span class="ico" data-icon="clock"></span>
        <span><b>Cancellation policy</b>
          <p>Free up to <?= e(setting('free_cancel_hours', '4')) ?> hours before your slot.
             After that any deposit is retained, and repeated no-shows may require a deposit
             on future bookings.</p></span>
      </div>
    </div>
  </div>
</section>

<?php public_foot(['page' => 'manage-booking.php', 'inline' => '<script>
(function () {
  var API = ' . json_encode(url('api/lookup.php')) . ';
  var form = document.getElementById("lookupForm");
  var out = document.getElementById("lookupResult");

  function show(b) {
    var steps = { pending: 1, confirmed: 2, completed: 4, cancelled: 0, "no-show": 0 };
    var on = steps[b.status] || 0;
    out.innerHTML =
      "<div class=\"card\">" +
        "<div class=\"flex items-center justify-between gap-3 wrapflex mb-4\">" +
          "<div><span class=\"eyebrow mb-0\">Reference</span>" +
          "<b style=\"font-size:1.125rem;letter-spacing:.1em\">" + PBS.esc(b.ref) + "</b></div>" +
          "<span class=\"badge " + (b.status === "cancelled" ? "badge-danger" : "badge-success") + "\">" +
          PBS.esc(b.status) + "</span></div>" +
        "<div class=\"status-track\">" +
          [1,2,3,4].map(function (i) {
            return "<span class=\"seg" + (i <= on ? " on" : "") + "\"></span>"; }).join("") +
        "</div>" +
        "<div class=\"flex justify-between tiny muted mb-4\"><span>Booked</span>" +
          "<span>Confirmed</span><span>In salon</span><span>Complete</span></div>" +
        b.services.map(function (s) {
          return "<div class=\"ticket-row\"><span>Service</span><b>" + PBS.esc(s) + "</b></div>"; }).join("") +
        "<div class=\"ticket-row\"><span>Stylist</span><b>" + PBS.esc(b.staff) + "</b></div>" +
        "<div class=\"ticket-row\"><span>When</span><b>" + PBS.esc(b.date_long) +
          "<br>" + b.starts + " – " + b.ends + "</b></div>" +
        "<div class=\"ticket-row\"><span>Total due</span><b class=\"t-pink\">" + b.total_fmt + "</b></div>" +
        (b.status === "cancelled" || b.status === "completed" ? "" :
          "<div class=\"grid g-2 mt-5\" style=\"gap:10px\">" +
            "<a class=\"btn btn-outline btn-sm\" href=\"book.php\">' . e(t('Book another time')) . '</a>" +
            "<button class=\"btn btn-ghost btn-sm\" id=\"cancelBk\">' . e(t('Cancel booking')) . '</button></div>") +
        "<p class=\"tiny muted mt-4 mb-0\">Free cancellation up to ' .
          e(setting('free_cancel_hours', '4')) . ' hours before your slot.</p>" +
      "</div>";
    out.classList.remove("hidden");
    PBS.paintIcons(out);
  }

  form.addEventListener("submit", function (ev) {
    ev.preventDefault();
    PBS.api(API, { method: "POST", body: new FormData(form) })
      .then(function (r) { show(r.booking); })
      .catch(function (err) {
        out.innerHTML = "<div class=\"alert alert-danger\"><span class=\"ico\">" + PBS.icon("alert") +
          "</span><span><b>Not found</b><p>" + PBS.esc(err.message) + "</p></span></div>";
        out.classList.remove("hidden");
      });
  });

  document.addEventListener("click", function (ev) {
    if (!ev.target.closest("#cancelBk")) return;
    if (!confirm("Cancel this booking? This cannot be undone.")) return;
    var fd = new FormData(form);
    fd.append("action", "cancel");
    PBS.api(API, { method: "POST", body: fd })
      .then(function (r) { PBS.toast(r.message); form.dispatchEvent(new Event("submit")); })
      .catch(function (err) { PBS.toast(err.message, "error"); });
  });
})();
</script>']); ?>
