<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/admin-layout.php';

$u = require_permission('clients');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require();
    $name  = trim((string) ($_POST['name'] ?? ''));
    $phone = trim((string) ($_POST['phone'] ?? ''));
    if ($name !== '' && strlen(preg_replace('/\D/', '', $phone)) >= 8) {
        $ref = next_ref('clients', 'CL-', 3);
        db_insert('clients', [
            'ref' => $ref, 'name' => $name, 'phone' => $phone,
            'email' => trim((string) ($_POST['email'] ?? '')) ?: null,
            'notes' => trim((string) ($_POST['notes'] ?? '')) ?: null,
            'fav_category_id' => ($_POST['cat'] ?? '') !== '' ? (int) $_POST['cat'] : null,
            'fav_staff_id' => ($_POST['staff'] ?? '') !== '' ? (int) $_POST['staff'] : null,
            'joined_on' => app_today(),
        ]);
        audit("Client $ref added", 'client', $ref);
        redirect('admin/clients.php?saved=1');
    }
}

$q    = trim((string) ($_GET['q'] ?? ''));
$tier = (string) ($_GET['tier'] ?? 'all');

/* A stylist only ever sees clients they have personally served. */
$filters = ['q' => $q ?: null];
if (!can('revenue') && $u['staff_id']) $filters['staff_id'] = (int) $u['staff_id'];

$all = repo_clients($filters);
$rows = $tier === 'all' ? $all : array_values(array_filter($all, fn($c) => client_tier($c) === $tier));

$totalSpend = array_sum(array_map(fn($c) => (float) $c['spend'], $all));
$vip = count(array_filter($all, fn($c) => client_tier($c) === 'VIP'));
$newMonth = (int) db_val('SELECT COUNT(*) FROM clients WHERE joined_on BETWEEN ? AND ?',
    [date('Y-m-01', strtotime(app_today())), date('Y-m-t', strtotime(app_today()))]);
$health = repo_business_health(date('Y-m-01', strtotime(app_today())), date('Y-m-t', strtotime(app_today())));

admin_head(['title' => 'Clients', 'subtitle' => 'Profiles &amp; history', 'page' => 'clients.php']);
?>

<div class="page-actions">
  <div><h2 class="mb-1" style="font-size:1.375rem">Clients</h2>
    <p class="small muted mb-0"><?= count($rows) ?> client<?= count($rows) === 1 ? '' : 's' ?>
      <?= isset($filters['staff_id']) ? '· people you have served' : '' ?></p></div>
  <div class="spacer"></div>
  <div class="btn-row">
    <a class="btn btn-ghost btn-sm" href="<?= e(url('admin/export.php')) ?>?report=appointments&amp;from=2026-01-01&amp;to=<?= e(app_today()) ?>">Export</a>
    <button class="btn btn-primary btn-sm" data-modal="newClientModal">Add client</button>
  </div>
</div>

<div class="stat-grid">
  <div class="stat accent-black"><div class="top"><div><div class="lab">Total Clients</div>
    <div class="val"><?= count($all) ?></div><div class="sub"><?= $newMonth ?> joined this month</div></div>
    <div class="ico" data-icon="users"></div></div></div>
  <div class="stat accent-gold"><div class="top"><div><div class="lab">VIP Clients</div>
    <div class="val"><?= $vip ?></div><div class="sub">12+ visits or 200k+ lifetime spend</div></div>
    <div class="ico" data-icon="crown"></div></div></div>
  <div class="stat accent-pink"><div class="top"><div><div class="lab">Lifetime Value</div>
    <div class="val"><?= e(money($totalSpend)) ?></div>
    <div class="sub">Average <?= e(money($all ? $totalSpend / count($all) : 0)) ?> per client</div></div>
    <div class="ico" data-icon="wallet"></div></div></div>
  <div class="stat accent-green"><div class="top"><div><div class="lab">Returning Rate</div>
    <div class="val"><?= (int) $health['returning_pct'] ?>%</div>
    <div class="sub">Clients with 2+ completed visits</div></div>
    <div class="ico" data-icon="chart"></div></div></div>
</div>

<form class="filter-bar mt-4" method="get">
  <div class="search" style="flex:1;min-width:200px">
    <svg viewBox="0 0 24 24" width="17" height="17"><circle cx="11" cy="11" r="7"/><path d="M20 20l-3.6-3.6"/></svg>
    <input class="input" name="q" type="search" value="<?= e($q) ?>" placeholder="Search by name, phone or email…">
  </div>
  <div class="chips">
    <?php foreach (['all' => 'All', 'VIP' => 'VIP', 'Regular' => 'Regular', 'New' => 'New'] as $k => $lbl): ?>
      <a class="chip<?= $tier === $k ? ' active' : '' ?>"
         href="?tier=<?= $k ?>&amp;q=<?= e($q) ?>"><?= e($lbl) ?></a>
    <?php endforeach; ?>
  </div>
  <button class="btn btn-ghost btn-sm" type="submit">Search</button>
</form>

<div class="card card-pad-0">
  <div class="table-wrap">
    <table class="table table-cards" style="min-width:900px">
      <thead><tr><th>Client</th><th>Tier</th><th class="num">Visits</th><th class="num">Lifetime spend</th>
        <th>Preferred</th><th>Last visit</th><th class="num"></th></tr></thead>
      <tbody>
      <?php foreach ($rows as $c): $t = client_tier($c); ?>
        <tr>
          <td><span class="cell-user"><span class="avatar avatar-sm avatar-pink"><?= e(initials($c['name'])) ?></span>
            <span><span class="name"><?= e($c['name']) ?></span>
              <span class="tiny muted"><?= e($c['phone']) ?></span></span></span></td>
          <td><span class="badge <?= $t === 'VIP' ? 'badge-gold' : ($t === 'New' ? 'badge-pink' : 'badge') ?>"><?= $t ?></span></td>
          <td class="num"><?= (int) $c['visits'] ?></td>
          <td class="num strong"><?= e(money($c['spend'])) ?></td>
          <td><?= e($c['fav_category'] ?: '—') ?><br>
            <span class="tiny muted">with <?= e($c['fav_staff_name'] ? explode(' ', $c['fav_staff_name'])[0] : '—') ?></span></td>
          <td><?= e($c['last_visit'] ? fmt_day($c['last_visit']) : 'Never') ?></td>
          <td class="num"><button class="btn btn-ghost btn-sm" data-client="<?= e($c['ref']) ?>">Profile</button></td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$rows): ?><tr><td colspan="7"><div class="empty"><p>No clients match this filter.</p></div></td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
  <div class="card-foot">
    <p class="data-note mb-0">Visits and lifetime spend are computed from completed appointments,
      so they can never go stale. The tier follows from them.</p>
  </div>
</div>

<div class="modal modal-lg" id="clientModal">
  <div class="modal-backdrop"></div>
  <div class="modal-box">
    <div class="modal-head"><h3>Client profile</h3>
      <button class="modal-close" data-modal-close>&times;</button></div>
    <div class="modal-body" id="clientModalBody"></div>
    <div class="modal-foot">
      <button class="btn btn-ghost btn-sm" data-modal-close>Close</button>
      <a class="btn btn-primary btn-sm" href="<?= url('public/book.php') ?>" target="_blank" rel="noopener">Book for this client</a>
    </div>
  </div>
</div>

<div class="modal" id="newClientModal">
  <div class="modal-backdrop"></div>
  <div class="modal-box">
    <form method="post">
      <?= csrf_field() ?>
      <div class="modal-head"><h3>Add a client</h3>
        <button type="button" class="modal-close" data-modal-close>&times;</button></div>
      <div class="modal-body">
        <div class="field"><label class="label">Full name <span class="req">*</span></label>
          <input class="input" name="name" required></div>
        <div class="field-row">
          <div class="field"><label class="label">Phone / WhatsApp <span class="req">*</span></label>
            <input class="input" name="phone" required placeholder="+237 6 XX XX XX XX"></div>
          <div class="field"><label class="label">Email</label>
            <input class="input" name="email" type="email" placeholder="optional"></div>
        </div>
        <div class="field-row">
          <div class="field"><label class="label">Preferred service</label>
            <select class="select" name="cat"><option value="">No preference</option>
              <?php foreach (repo_categories() as $c): ?>
                <option value="<?= (int) $c['id'] ?>"><?= e($c['name']) ?></option><?php endforeach; ?></select></div>
          <div class="field"><label class="label">Preferred stylist</label>
            <select class="select" name="staff"><option value="">No preference</option>
              <?php foreach (repo_staff() as $s): ?>
                <option value="<?= (int) $s['id'] ?>"><?= e($s['name']) ?></option><?php endforeach; ?></select></div>
        </div>
        <div class="field mb-0"><label class="label">Notes</label>
          <textarea class="textarea" name="notes" style="min-height:80px"
            placeholder="Allergies, scalp sensitivity, preferred times…"></textarea></div>
      </div>
      <div class="modal-foot">
        <button type="button" class="btn btn-ghost btn-sm" data-modal-close>Cancel</button>
        <button type="submit" class="btn btn-primary btn-sm">Save client</button>
      </div>
    </form>
  </div>
</div>

<?php
admin_foot(['page' => 'clients.php', 'inline' => '<script>
document.addEventListener("click", function (e) {
  var b = e.target.closest("[data-client]");
  if (!b) return;
  PBS.openModal("clientModal");
  document.getElementById("clientModalBody").innerHTML = "<p class=\"muted\">Loading…</p>";
  PBS.api(' . json_encode(url('api/client.php')) . ' + "?ref=" + encodeURIComponent(b.getAttribute("data-client")))
    .then(function (r) {
      var c = r.client;
      document.getElementById("clientModalBody").innerHTML =
        "<div class=\"flex items-center gap-4 mb-6 wrapflex\">" +
          "<span class=\"avatar avatar-lg avatar-pink\">" + PBS.initials(c.name) + "</span>" +
          "<div style=\"flex:1;min-width:160px\"><h4 class=\"mb-0\">" + PBS.esc(c.name) + "</h4>" +
          "<span class=\"tiny muted\">Client since " + c.joined + " · " + c.ref + "</span></div>" +
          "<span class=\"badge " + (c.tier === "VIP" ? "badge-gold" : "badge") + "\">" + c.tier + "</span></div>" +
        "<div class=\"stat-grid\" style=\"grid-template-columns:repeat(auto-fit,minmax(130px,1fr));gap:8px\">" +
          "<div class=\"stat stat-mini accent-pink\"><div class=\"lab\">Visits</div><div class=\"val\">" + c.visits + "</div></div>" +
          "<div class=\"stat stat-mini accent-gold\"><div class=\"lab\">Lifetime Spend</div><div class=\"val\">" + c.spend + "</div></div>" +
          "<div class=\"stat stat-mini accent-black\"><div class=\"lab\">Avg Ticket</div><div class=\"val\">" + c.avg + "</div></div>" +
        "</div>" +
        kv("Phone", c.phone) + kv("Email", c.email || "—") +
        kv("Preferred service", c.fav_category || "—") + kv("Preferred stylist", c.fav_staff || "—") +
        (c.notes ? "<div class=\"alert alert-info mt-4\"><span class=\"ico\">" + PBS.icon("edit") +
          "</span><span><b>Stylist notes</b><p>" + PBS.esc(c.notes) + "</p></span></div>" : "") +
        "<h4 class=\"mt-6 mb-3\">Booking history <span class=\"tiny muted\">(" + c.history.length + ")</span></h4>" +
        "<div class=\"table-wrap\"><table class=\"table table-cards\" style=\"min-width:420px\">" +
        "<thead><tr><th>Date</th><th>Service</th><th>Status</th><th class=\"num\">Total</th></tr></thead><tbody>" +
        c.history.map(function (h) {
          return "<tr><td>" + h.date + "</td><td>" + PBS.esc(h.service) + "</td>" +
            "<td><span class=\"badge\">" + h.status + "</span></td><td class=\"num strong\">" + h.total + "</td></tr>";
        }).join("") + "</tbody></table></div>";
      PBS.labelCells(document.getElementById("clientModalBody"));
      PBS.paintIcons(document.getElementById("clientModalBody"));
    }).catch(function (err) {
      document.getElementById("clientModalBody").innerHTML =
        "<div class=\"alert alert-danger\"><span>" + PBS.esc(err.message) + "</span></div>";
    });
  function kv(k, v) { return "<div class=\"kv\"><span>" + k + "</span><b>" + PBS.esc(String(v)) + "</b></div>"; }
});
</script>']);
