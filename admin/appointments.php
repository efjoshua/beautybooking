<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/admin-layout.php';

$u = require_permission('appointments');

/* A stylist's diary is scoped in the query, not hidden in the view. */
$scopeStaff = can('revenue') ? null : (int) $u['staff_id'];

$status = (string) ($_GET['status'] ?? 'all');
$when   = (string) ($_GET['when'] ?? 'all');
$staffF = (string) ($_GET['staff'] ?? 'all');
$q      = trim((string) ($_GET['q'] ?? ''));
$view   = (string) ($_GET['view'] ?? 'list');
$sort   = (string) ($_GET['sort'] ?? 'submitted');
if (!isset(APPT_SORTS[$sort])) $sort = 'submitted';

$filters = ['status' => $status, 'q' => $q ?: null, 'sort' => $sort];
if ($scopeStaff)              $filters['staff_id'] = $scopeStaff;
elseif ($staffF !== 'all')    $filters['staff_id'] = (int) db_val('SELECT id FROM staff WHERE ref = ?', [$staffF]);
if ($when === 'today')        $filters['date'] = app_today();
elseif ($when === 'upcoming') $filters['upcoming'] = true;
elseif ($when === 'past')     $filters['past'] = true;
elseif ($when === 'unpaid')   $filters['unpaid'] = true;

/* 424 rows in one response was 514KB of HTML — page it. */
$perPage = 25;
$page    = max(1, (int) ($_GET['page'] ?? 1));
$total   = repo_appointments_count($filters);
$pages   = max(1, (int) ceil($total / $perPage));
$page    = min($page, $pages);

$rows   = repo_appointments($filters + ['limit' => $perPage, 'offset' => ($page - 1) * $perPage]);
$counts = repo_appointment_status_counts($scopeStaff);
$staff  = repo_staff();

/* Month grid for the calendar view */
$monthStart = date('Y-m-01', strtotime(app_today()));
$calAppts = repo_appointments(($scopeStaff ? ['staff_id' => $scopeStaff] : []) + [
    'from' => $monthStart, 'to' => date('Y-m-t', strtotime(app_today())),
]);
$byDay = [];
foreach ($calAppts as $a) $byDay[$a['booked_on']][] = $a;

function qs(array $over): string {
    return '?' . http_build_query(array_merge(
        ['status' => $_GET['status'] ?? 'all', 'when' => $_GET['when'] ?? 'all',
         'staff' => $_GET['staff'] ?? 'all', 'q' => $_GET['q'] ?? '',
         'view' => $_GET['view'] ?? 'list', 'sort' => $_GET['sort'] ?? 'submitted',
         'page' => $_GET['page'] ?? 1], $over));
}

admin_head(['title' => 'Appointments', 'subtitle' => 'Bookings & diary', 'page' => 'appointments.php']);
?>

<div class="page-actions">
  <div>
    <h2 class="mb-1" style="font-size:1.375rem">Appointments</h2>
    <p class="small muted mb-0"><?= number_format($total) ?> appointment<?= $total === 1 ? '' : 's' ?>
      <?= $pages > 1 ? '· page ' . $page . ' of ' . $pages : '' ?>
      <?= $scopeStaff ? '· your diary only' : '' ?></p>
  </div>
  <div class="spacer"></div>
  <div class="chips">
    <a class="chip<?= $view === 'list' ? ' active' : '' ?>" href="<?= e(qs(['view' => 'list'])) ?>">List</a>
    <a class="chip<?= $view === 'calendar' ? ' active' : '' ?>" href="<?= e(qs(['view' => 'calendar'])) ?>">Calendar</a>
  </div>
  <a href="<?= url('public/book.php') ?>" target="_blank" rel="noopener" class="btn btn-primary btn-sm">New booking</a>
</div>

<div class="tabs mb-5">
  <?php foreach ([['all','All'],['pending','Pending'],['confirmed','Confirmed'],
                  ['completed','Completed'],['cancelled','Cancelled'],['no-show','No-show']] as [$k, $label]): ?>
    <a class="tab<?= $status === $k ? ' active' : '' ?>" href="<?= e(qs(['status' => $k])) ?>">
      <?= e($label) ?> <span class="muted">(<?= (int) ($counts[$k] ?? 0) ?>)</span></a>
  <?php endforeach; ?>
</div>

<form class="filter-bar" method="get">
  <input type="hidden" name="status" value="<?= e($status) ?>">
  <input type="hidden" name="view" value="<?= e($view) ?>">
  <input type="hidden" name="sort" value="<?= e($sort) ?>">
  <div class="search" style="flex:1;min-width:200px">
    <svg viewBox="0 0 24 24" width="17" height="17"><circle cx="11" cy="11" r="7"/><path d="M20 20l-3.6-3.6"/></svg>
    <input class="input" name="q" type="search" value="<?= e($q) ?>"
           placeholder="Search by client, phone or reference…">
  </div>
  <?php if (!$scopeStaff): ?>
  <select class="select" name="staff" onchange="this.form.submit()">
    <option value="all">All stylists</option>
    <?php foreach ($staff as $s): ?>
      <option value="<?= e($s['ref']) ?>"<?= $staffF === $s['ref'] ? ' selected' : '' ?>><?= e($s['name']) ?></option>
    <?php endforeach; ?>
  </select>
  <?php endif; ?>
  <select class="select" name="when" onchange="this.form.submit()">
    <option value="all"<?= $when === 'all' ? ' selected' : '' ?>>All dates</option>
    <option value="today"<?= $when === 'today' ? ' selected' : '' ?>>Today only</option>
    <option value="upcoming"<?= $when === 'upcoming' ? ' selected' : '' ?>>Upcoming</option>
    <option value="past"<?= $when === 'past' ? ' selected' : '' ?>>Past</option>
    <option value="unpaid"<?= $when === 'unpaid' ? ' selected' : '' ?>>Unpaid only</option>
  </select>
  <button class="btn btn-ghost btn-sm" type="submit">Apply</button>
</form>

<div class="flex items-center gap-3 wrapflex mb-4">
  <span class="tiny muted" style="letter-spacing:.14em;text-transform:uppercase">Order by</span>
  <?php foreach (APPT_SORT_LABELS as $k => $label): ?>
    <a class="chip<?= $sort === $k ? ' active' : '' ?>" href="<?= e(qs(['sort' => $k, 'page' => 1])) ?>"><?= e($label) ?></a>
  <?php endforeach; ?>
</div>

<?php if ($view === 'calendar'): ?>
  <div class="card card-pad-0">
    <div class="card-head"><h4><?= e(date('F Y', strtotime($monthStart))) ?></h4>
      <span class="mock-flag">Live diary</span></div>
    <div class="card-body">
      <?php
      $first = (int) date('N', strtotime($monthStart)) - 1;
      $days  = (int) date('t', strtotime($monthStart));
      echo '<div class="cal">';
      foreach (['M','T','W','T','F','S','S'] as $dn) echo '<div class="dow">' . $dn . '</div>';
      for ($i = 0; $i < $first; $i++) echo '<div class="day out"></div>';
      for ($dnum = 1; $dnum <= $days; $dnum++) {
          $iso  = date('Y-m-', strtotime($monthStart)) . str_pad((string) $dnum, 2, '0', STR_PAD_LEFT);
          $list = $byDay[$iso] ?? [];
          $cls  = 'day' . ($iso === app_today() ? ' today' : '') . ($list ? ' has-appt' : '');
          echo '<div class="' . $cls . '"><span class="n">' . $dnum . '</span>';
          foreach (array_slice($list, 0, 3) as $a) {
              echo '<span class="ev ' . e((string) $a['category_slug']) . '">'
                 . e(fmt_time($a['starts_at']) . ' ' . explode(' ', $a['client_name'])[0]) . '</span>';
          }
          if (count($list) > 3) echo '<span class="more">+' . (count($list) - 3) . ' more</span>';
          echo '</div>';
      }
      echo '</div>';
      ?>
      <div class="chart-legend mt-4">
        <?php foreach (repo_categories() as $c): ?>
          <span class="li"><span class="sw" style="background:<?= e($c['color']) ?>"></span><?= e($c['short_name']) ?></span>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
<?php else: ?>
  <div class="card card-pad-0">
    <div class="table-wrap">
      <table class="table table-cards" style="min-width:1000px">
        <thead><tr>
          <th>Reference</th><th>Client</th><th>Service</th><th>Stylist</th>
          <th>Status</th><th>Payment</th><th class="num">Revenue</th><th></th>
        </tr></thead>
        <tbody>
        <?php if (!$rows): ?>
          <tr><td colspan="8"><div class="empty"><p>No appointments match these filters.</p></div></td></tr>
        <?php else: foreach ($rows as $a): ?>
          <tr>
            <td><span class="strong"><?= e($a['ref']) ?></span><br>
              <span class="tiny muted"><?= e(fmt_day($a['booked_on'])) ?> · <?= e(fmt_time($a['starts_at'])) ?></span><br>
              <span class="tiny muted">submitted <?= e(fmt_day(substr((string) $a['created_at'], 0, 10))) ?></span></td>
            <td><span class="cell-user">
              <span class="avatar avatar-sm avatar-pink"><?= e(initials($a['client_name'])) ?></span>
              <span><span class="name"><?= e($a['client_name']) ?></span>
                <span class="tiny muted"><?= e($a['client_phone']) ?></span></span></span></td>
            <td><?= e($a['service_names']) ?><br>
              <span class="tiny muted"><?= e(duration((int) $a['duration_minutes'])) ?></span></td>
            <td><?= $a['staff_name']
                  ? '<span class="cell-user"><span class="avatar avatar-sm">' . e(initials($a['staff_name']))
                    . '</span><span><span class="name">' . e($a['staff_name']) . '</span>'
                    . '<span class="tiny muted">' . e((string) $a['job_title']) . '</span></span></span>'
                  : '<span class="muted">Unassigned</span>' ?></td>
            <td><?= status_badge($a['status']) ?></td>
            <td><?= payment_badge($a['payment_method']) ?></td>
            <td class="num"><span class="strong"><?= e(money($a['grand_total'])) ?></span>
              <?php if ($a['addons_total'] > 0 || $a['tip_total'] > 0): ?>
                <br><span class="tiny muted">svc <?= e(money_short($a['services_total'])) ?>
                <?= $a['addons_total'] > 0 ? '+ add ' . e(money_short($a['addons_total'])) : '' ?>
                <?= $a['tip_total'] > 0 ? '+ tip ' . e(money_short($a['tip_total'])) : '' ?></span>
              <?php endif; ?></td>
            <td class="num">
              <span class="flex gap-2" style="justify-content:flex-end">
                <a class="btn btn-ghost btn-sm" target="_blank" rel="noopener"
                   href="<?= e(url('admin/receipt.php?ref=' . urlencode($a['ref']))) ?>"
                   title="Open the printable receipt">Receipt</a>
                <button class="btn-icon" data-appt="<?= e($a['ref']) ?>" aria-label="View">
                  <span data-icon="arrowRight"></span></button></span></td>
          </tr>
        <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
    <div class="card-foot flex items-center justify-between wrapflex gap-3">
      <p class="data-note mb-0">Each completed appointment writes one revenue entry —
        service price + add-ons + tip — and deducts the stock its services consume.</p>
      <?php if ($pages > 1): ?>
        <div class="flex gap-2 items-center">
          <?php if ($page > 1): ?>
            <a class="btn btn-ghost btn-sm" href="<?= e(qs(['page' => $page - 1])) ?>">← Prev</a>
          <?php endif; ?>
          <span class="tiny muted"><?= $page ?> / <?= $pages ?></span>
          <?php if ($page < $pages): ?>
            <a class="btn btn-ghost btn-sm" href="<?= e(qs(['page' => $page + 1])) ?>">Next →</a>
          <?php endif; ?>
        </div>
      <?php endif; ?>
    </div>
  </div>
<?php endif; ?>

<!-- DETAIL MODAL -->
<div class="modal modal-lg" id="apptModal">
  <div class="modal-backdrop"></div>
  <div class="modal-box">
    <div class="modal-head"><h3>Appointment</h3>
      <button class="modal-close" data-modal-close>&times;</button></div>
    <div class="modal-body" id="apptModalBody"></div>
    <div class="modal-foot" id="apptModalFoot"></div>
  </div>
</div>

<!-- TAKE PAYMENT MODAL -->
<div class="modal" id="payModal">
  <div class="modal-backdrop"></div>
  <div class="modal-box">
    <div class="modal-head"><h3>Take payment</h3>
      <button class="modal-close" data-modal-close>&times;</button></div>
    <div class="modal-body">
      <p class="small muted" id="payFor"></p>
      <div class="field-row">
        <div class="field"><label class="label">Add-ons / products used</label>
          <input class="input" type="number" id="payAddons" value="0" min="0" step="1"></div>
        <div class="field"><label class="label">Tip</label>
          <input class="input" type="number" id="payTip" value="0" min="0" step="1"></div>
      </div>
      <div class="field mb-0"><label class="label">Paid with</label>
        <select class="select" id="payMethod">
          <option value="cash">Cash</option><option value="momo">MTN MoMo</option>
          <option value="om">Orange Money</option><option value="card">Card</option>
        </select></div>
    </div>
    <div class="modal-foot">
      <button class="btn btn-ghost btn-sm" data-modal-close>Cancel</button>
      <button class="btn btn-gold btn-sm" id="payConfirm">Complete &amp; take payment</button>
    </div>
  </div>
</div>

<?php
admin_foot(['page' => 'appointments.php', 'inline' => '<script>
(function () {
  var API = ' . json_encode(url('api/appointment.php')) . ';
  var RECEIPT = ' . json_encode(url('admin/receipt.php')) . ';
  var current = null, payMode = "complete";

  function open(ref) {
    PBS.openModal("apptModal");
    document.getElementById("apptModalBody").innerHTML = "<p class=\"muted\">Loading…</p>";
    document.getElementById("apptModalFoot").innerHTML = "";
    PBS.api(API + "?ref=" + encodeURIComponent(ref)).then(function (r) {
      var a = r.appointment; current = a;
      document.getElementById("apptModalBody").innerHTML =
        "<div class=\"flex items-center gap-4 mb-6 wrapflex\">" +
          "<span class=\"avatar avatar-lg avatar-pink\">" + PBS.initials(a.client) + "</span>" +
          "<div style=\"flex:1;min-width:160px\"><h4 class=\"mb-0\">" + PBS.esc(a.client) + "</h4>" +
          "<span class=\"tiny muted\">" + PBS.esc(a.phone) + "</span></div>" +
          "<span>" + statusBadge(a.status) + "</span></div>" +
        kv("Reference", a.ref) + kv("When", a.date + " at " + a.starts) +
        kv("Duration", a.duration) + kv("Stylist", a.staff) +
        kv("Payment", a.payment_method === "unpaid" ? "Not yet paid" : a.payment_method.toUpperCase()) +
        (a.note ? kv("Note", a.note) : "") +
        "<h4 class=\"mt-6 mb-2\">Revenue breakdown</h4>" +
        a.items.map(function (i) { return kv(i.name, i.price); }).join("") +
        kv("Add-ons / products", a.addons) + kv("Tip", a.tip) +
        "<div class=\"kv total\"><span>Total</span><b>" + a.total + "</b></div>" +
        (a.commission_pct ? "<p class=\"tiny muted mt-3\">Stylist commission at " + a.commission_pct +
          "% = <b>" + a.commission + "</b> — feeds the commission report.</p>" : "") +
        (a.consumes.length ? "<h4 class=\"mt-6 mb-2\">Stock this consumes</h4>" +
          "<p class=\"tiny muted mb-2\">Deducted automatically when marked complete.</p>" +
          a.consumes.map(function (c) { return kv(c.name, "−" + c.qty + " " + c.unit); }).join("") : "");

      var foot = ["<button class=\"btn btn-ghost btn-sm\" data-modal-close>Close</button>",
        "<a class=\"btn btn-ghost btn-sm\" target=\"_blank\" rel=\"noopener\" href=\"" +
          RECEIPT + "?ref=" + encodeURIComponent(a.ref) + "\">Receipt</a>"];
      if (a.status === "pending")   foot.push(btn("confirmed", "Confirm booking", "btn-outline"));
      if (a.status !== "completed" && a.status !== "cancelled") {
        foot.push("<button class=\"btn btn-ghost btn-sm\" data-set=\"no-show\">No-show</button>");
        foot.push("<button class=\"btn btn-ghost btn-sm\" data-set=\"cancelled\">Cancel</button>");
        foot.push("<button class=\"btn btn-gold btn-sm\" id=\"openPay\" data-pay=\"complete\">" +
                  "Complete &amp; take payment</button>");
      } else if (a.status === "completed" && a.payment_method === "unpaid") {
        /* The work is done and the money is still owed. This is the whole
           point of the unpaid filter and the outstanding report, so there
           has to be a way to actually collect it. Payment only — the
           appointment is already complete and its stock already spent. */
        foot.push("<button class=\"btn btn-gold btn-sm\" id=\"openPay\" data-pay=\"only\">" +
                  "Take payment</button>");
      }
      document.getElementById("apptModalFoot").innerHTML = foot.join("");
      PBS.paintIcons(document.getElementById("apptModal"));
    }).catch(function (e) {
      document.getElementById("apptModalBody").innerHTML =
        "<div class=\"alert alert-danger\"><span>" + PBS.esc(e.message) + "</span></div>";
    });
  }
  function kv(k, v) { return "<div class=\"kv\"><span>" + PBS.esc(k) + "</span><b>" + PBS.esc(String(v)) + "</b></div>"; }
  function btn(s, label, cls) { return "<button class=\"btn " + cls + " btn-sm\" data-set=\"" + s + "\">" + label + "</button>"; }
  function statusBadge(s) {
    var m = { pending:"badge-warning", confirmed:"badge-info", completed:"badge-success",
              cancelled:"badge-danger", "no-show":"badge" };
    return "<span class=\"badge " + (m[s]||"badge") + "\">" + s + "</span>";
  }

  function setStatus(status) {
    var fd = new FormData();
    fd.append("ref", current.ref); fd.append("action", "status"); fd.append("status", status);
    PBS.api(API, { method: "POST", body: fd })
      .then(function (r) { PBS.toast(r.message); setTimeout(function(){ location.reload(); }, 700); })
      .catch(function (e) { PBS.toast(e.message, "error"); });
  }

  document.addEventListener("click", function (e) {
    var v = e.target.closest("[data-appt]");
    if (v) { open(v.getAttribute("data-appt")); return; }
    var s = e.target.closest("[data-set]");
    if (s) { setStatus(s.getAttribute("data-set")); return; }
    var pay = e.target.closest("#openPay");
    if (pay) {
      payMode = pay.getAttribute("data-pay") || "complete";
      /* Reopen with whatever was already recorded, so collecting later does
         not wipe add-ons someone entered at the chair. */
      document.getElementById("payAddons").value = current.addons_raw || "";
      document.getElementById("payTip").value = current.tip_raw || "";
      document.getElementById("payFor").textContent =
        current.client + " · " + current.items.map(function (i) { return i.name; }).join(" + ");
      PBS.openModal("payModal");
    }
    if (e.target.closest("#payConfirm")) {
      var fd = new FormData();
      fd.append("ref", current.ref); fd.append("action", "payment");
      fd.append("method", document.getElementById("payMethod").value);
      fd.append("addons", document.getElementById("payAddons").value || 0);
      fd.append("tip", document.getElementById("payTip").value || 0);
      PBS.api(API, { method: "POST", body: fd }).then(function (r) {
        /* Collecting on an already-completed booking is a payment and nothing
           more — re-sending the status would be a no-op at best. */
        var after = Promise.resolve();
        if (payMode !== "only") {
          var f2 = new FormData();
          f2.append("ref", current.ref); f2.append("action", "status"); f2.append("status", "completed");
          after = PBS.api(API, { method: "POST", body: f2 });
        }
        return after.then(function () {
          PBS.toast(r.message);
          /* The money is in; the client is still standing there. Open the
             receipt with the print dialog already up rather than making
             someone hunt for it a screen later. */
          window.open(RECEIPT + "?ref=" + encodeURIComponent(current.ref) + "&print=1", "_blank");
          setTimeout(function(){ location.reload(); }, 800);
        });
      }).catch(function (e) { PBS.toast(e.message, "error"); });
    }
  });
})();
</script>']);
