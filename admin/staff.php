<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/admin-layout.php';

require_permission('staff');

$notice = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require();
    $ref   = trim((string) ($_POST['ref'] ?? ''));
    $name  = trim((string) ($_POST['name'] ?? ''));
    $title = trim((string) ($_POST['job_title'] ?? ''));
    $comm  = (float) ($_POST['commission_pct'] ?? 0);

    if ($name === '')                 $notice = 'Enter a name.';
    elseif ($comm < 0 || $comm > 100) $notice = 'Commission must be between 0 and 100%.';
    else {
        $data = [
            'name' => $name, 'job_title' => $title ?: 'Stylist',
            'bio' => trim((string) ($_POST['bio'] ?? '')) ?: null,
            'commission_pct' => $comm,
            'working_hours' => trim((string) ($_POST['working_hours'] ?? '')) ?: null,
            'is_public' => !empty($_POST['is_public']) ? 1 : 0,
        ];
        if ($ref !== '' && db_val('SELECT 1 FROM staff WHERE ref = ?', [$ref])) {
            db_update('staff', $data, ['ref' => $ref]);
            $sid = (int) db_val('SELECT id FROM staff WHERE ref = ?', [$ref]);
            audit("Staff $ref updated", 'staff', $ref);
        } else {
            $data['ref'] = next_ref('staff', 'ST-', 2);
            $data['since_year'] = date('Y');
            $data['tone'] = 'pink';
            $sid = db_insert('staff', $data);
            audit('Staff ' . $data['ref'] . ' added', 'staff', $data['ref']);
        }
        db_run('DELETE FROM staff_categories WHERE staff_id = ?', [$sid]);
        foreach ((array) ($_POST['cats'] ?? []) as $cid) {
            db_insert('staff_categories', ['staff_id' => $sid, 'category_id' => (int) $cid]);
        }
        redirect('admin/staff.php?saved=1');
    }
}

$staff = repo_staff();
$cats  = repo_categories();
$monthFrom = date('Y-m-01', strtotime(app_today()));
$monthTo   = date('Y-m-t', strtotime(app_today()));
$perf = [];
foreach (repo_revenue_by_staff($monthFrom, $monthTo) as $r) $perf[(int) $r['id']] = $r;

$rota = [];
foreach (db_all('SELECT * FROM staff_hours') as $h) {
    $rota[(int) $h['staff_id']][(int) $h['weekday']] = fmt_time($h['starts_at']) . ' – ' . fmt_time($h['ends_at']);
}

$commissionDue = 0.0;
foreach ($staff as $s) {
    $p = $perf[(int) $s['id']] ?? null;
    if ($p) $commissionDue += (float) $p['commissionable'] * (float) $s['commission_pct'] / 100;
}
$top = null;
foreach ($perf as $p) { if (!$top || $p['gross'] > $top['gross']) $top = $p; }
$avgRating = $staff ? array_sum(array_map(fn($s) => (float) $s['rating'], $staff)) / count($staff) : 0;

admin_head(['title' => 'Staff', 'subtitle' => 'Team &amp; commission', 'page' => 'staff.php']);
?>

<div class="page-actions">
  <div><h2 class="mb-1" style="font-size:1.375rem">Staff</h2>
    <p class="small muted mb-0">Roles, commission rates, availability and performance</p></div>
  <div class="spacer"></div>
  <div class="btn-row">
    <a class="btn btn-ghost btn-sm" href="<?= e(url('admin/export.php')) ?>?report=commission&amp;from=<?= e($monthFrom) ?>&amp;to=<?= e($monthTo) ?>">Commission run</a>
    <button class="btn btn-primary btn-sm" id="addStaff">Add staff member</button>
  </div>
</div>

<?php if ($notice): ?>
  <div class="alert alert-danger mb-4"><span class="ico" data-icon="alert"></span>
    <span><b>Could not save</b><p><?= e($notice) ?></p></span></div>
<?php endif; ?>

<div class="stat-grid">
  <div class="stat accent-black"><div class="top"><div><div class="lab">Team Members</div>
    <div class="val"><?= count($staff) ?></div>
    <div class="sub"><?= count(array_filter($staff, fn($s) => (int) $s['is_public'])) ?> shown publicly</div></div>
    <div class="ico" data-icon="users"></div></div></div>
  <div class="stat accent-gold"><div class="top"><div><div class="lab">Commission Due (month)</div>
    <div class="val"><?= e(money($commissionDue)) ?></div><div class="sub">Paid twice monthly</div></div>
    <div class="ico" data-icon="wallet"></div></div></div>
  <div class="stat accent-pink"><div class="top"><div><div class="lab">Top Earner</div>
    <div class="val" style="font-size:1.25rem"><?= e($top ? explode(' ', $top['name'])[0] : '—') ?></div>
    <div class="sub"><?= $top ? e(money($top['gross'])) . ' · ' . (int) $top['appts'] . ' appointments' : 'No completed work yet' ?></div></div>
    <div class="ico" data-icon="star"></div></div></div>
  <div class="stat accent-green"><div class="top"><div><div class="lab">Average Rating</div>
    <div class="val"><?= number_format($avgRating, 2) ?></div>
    <div class="sub"><?= array_sum(array_map(fn($s) => (int) $s['reviews_count'], $staff)) ?> reviews total</div></div>
    <div class="ico" data-icon="chart"></div></div></div>
</div>

<div class="card card-pad-0 mt-4">
  <div class="card-head"><div><h4>Performance this month</h4>
    <span class="tiny muted">Revenue generated per stylist — feeds the commission report</span></div>
    <a href="revenue.php" class="tiny t-pink">Commission detail</a></div>
  <div class="card-body"><div id="staffPerf"></div>
    <?php if (!$perf): ?><p class="small muted mb-0">No completed work this month yet.</p><?php endif; ?></div>
</div>

<h4 class="mt-6 mb-4">Team members</h4>
<div class="grid g-3" id="staffCards">
  <?php foreach ($staff as $s): $p = $perf[(int) $s['id']] ?? null;
    $comm = $p ? (float) $p['commissionable'] * (float) $s['commission_pct'] / 100 : 0; ?>
    <div class="card card-hover">
      <div class="staff-card-head mb-4">
        <span class="avatar avatar-lg<?= $s['tone'] === 'pink' ? ' avatar-pink' : '' ?>"><?= e(initials($s['name'])) ?></span>
        <div class="who"><h4><?= e($s['name']) ?></h4>
          <span class="tiny muted"><?= e($s['job_title']) ?></span></div>
        <span class="badge <?= (int) $s['is_public'] ? 'badge-gold' : 'badge-plain badge' ?>">
          <?= (int) $s['is_public'] ? 'Public' : 'Hidden' ?></span>
      </div>
      <div class="kv"><span>Revenue this month</span><b><?= e(money($p['gross'] ?? 0)) ?></b></div>
      <div class="kv"><span>Appointments completed</span><b><?= (int) ($p['appts'] ?? 0) ?></b></div>
      <div class="kv"><span>Commission rate</span><b><?= rtrim(rtrim(number_format((float) $s['commission_pct'], 1), '0'), '.') ?>%</b></div>
      <div class="kv"><span>Commission earned</span><b class="t-pink"><?= e(money($comm)) ?></b></div>
      <div class="kv"><span>Rating</span><b>★ <?= number_format((float) $s['rating'], 1) ?>
        <span class="tiny muted">(<?= (int) $s['reviews_count'] ?>)</span></b></div>
      <div class="kv"><span>Working hours</span><b class="small"><?= e($s['working_hours'] ?: '—') ?></b></div>
      <div class="mt-4"><span class="tiny muted">Services assigned</span>
        <div class="chips mt-2">
          <?php foreach ($s['categories'] as $slug):
            $c = null; foreach ($cats as $x) if ($x['slug'] === $slug) $c = $x;
            if ($c): ?>
            <span class="badge badge-plain" style="background:<?= e($c['color']) ?>1a;color:<?= e($c['color']) ?>">
              <?= e($c['name']) ?></span>
          <?php endif; endforeach; ?>
          <?php if (!$s['categories']): ?><span class="tiny muted">None assigned — cannot be booked</span><?php endif; ?>
        </div></div>
      <div class="flex gap-2 mt-5">
        <button class="btn btn-ghost btn-sm" data-edit='<?= e(json_encode([
          'ref' => $s['ref'], 'name' => $s['name'], 'job_title' => $s['job_title'],
          'bio' => $s['bio'], 'commission_pct' => $s['commission_pct'],
          'working_hours' => $s['working_hours'], 'is_public' => $s['is_public'],
          'cats' => $s['categories'],
        ])) ?>'>Edit</button>
        <a class="btn btn-outline btn-sm" href="appointments.php?staff=<?= e($s['ref']) ?>">Schedule</a>
      </div>
    </div>
  <?php endforeach; ?>
</div>

<div class="card card-pad-0 mt-4">
  <div class="card-head"><div><h4>Weekly availability</h4>
    <span class="tiny muted">Working hours control which slots the public wizard offers</span></div></div>
  <div class="table-wrap">
    <table class="table table-cards" style="min-width:820px">
      <thead><tr><th>Stylist</th><th>Mon</th><th>Tue</th><th>Wed</th><th>Thu</th><th>Fri</th><th>Sat</th><th>Sun</th></tr></thead>
      <tbody>
      <?php foreach ($staff as $s): ?>
        <tr><td><span class="cell-user"><span class="avatar avatar-sm"><?= e(initials($s['name'])) ?></span>
          <span class="name"><?= e($s['name']) ?></span></span></td>
          <?php for ($d = 0; $d < 7; $d++): $h = $rota[(int) $s['id']][$d] ?? null; ?>
            <td><?= $h ? '<span class="badge badge-success badge-plain">' . e($h) . '</span>'
                       : '<span class="badge badge-plain">Off</span>' ?></td>
          <?php endfor; ?>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <div class="card-foot">
    <p class="data-note mb-0">A stylist with no hours on a day cannot be booked that day —
      the availability engine reads this table directly.</p>
  </div>
</div>

<div class="modal modal-lg" id="staffModal">
  <div class="modal-backdrop"></div>
  <div class="modal-box">
    <form method="post">
      <?= csrf_field() ?>
      <div class="modal-head"><h3 id="stTitle">Add staff member</h3>
        <button type="button" class="modal-close" data-modal-close>&times;</button></div>
      <div class="modal-body">
        <input type="hidden" name="ref" id="stRef">
        <div class="field-row">
          <div class="field"><label class="label">Full name <span class="req">*</span></label>
            <input class="input" name="name" id="stName" required></div>
          <div class="field"><label class="label">Job title</label>
            <input class="input" name="job_title" id="stTitleField" placeholder="e.g. Senior Braider"></div>
        </div>
        <div class="field-row">
          <div class="field"><label class="label">Commission rate (%)</label>
            <input class="input" type="number" name="commission_pct" id="stComm" min="0" max="100" step="0.5"
                   value="<?= e(setting('default_commission_pct', '35')) ?>"></div>
          <div class="field"><label class="label">Working hours (display)</label>
            <input class="input" name="working_hours" id="stHours" placeholder="Mon-Sat · 09:00 - 19:00"></div>
        </div>
        <div class="field"><label class="label">Public bio</label>
          <textarea class="textarea" name="bio" id="stBio" style="min-height:80px"></textarea></div>
        <div class="field"><label class="label">Services this stylist can be booked for</label>
          <?php foreach ($cats as $c): ?>
            <label class="check mb-2"><input type="checkbox" name="cats[]" value="<?= (int) $c['id'] ?>"
              data-cat="<?= e($c['slug']) ?>"><?= e($c['name']) ?></label>
          <?php endforeach; ?></div>
        <label class="check mb-0"><input type="checkbox" name="is_public" id="stPublic" value="1" checked>
          Show on the public Stylists page</label>
      </div>
      <div class="modal-foot">
        <button type="button" class="btn btn-ghost btn-sm" data-modal-close>Cancel</button>
        <button type="submit" class="btn btn-primary btn-sm">Save</button>
      </div>
    </form>
  </div>
</div>

<?php
admin_foot(['page' => 'staff.php', 'inline' => '<script>
var PERF = ' . json_encode(array_values($perf)) . ';
var DEFAULT_COMMISSION = ' . json_encode((float) setting('default_commission_pct', '35')) . ';
document.addEventListener("DOMContentLoaded", function () {
  if (PERF.length) {
    PBS.charts.ranked("#staffPerf", PERF.map(function (s, i) {
      return { label: s.name, sub: "· " + s.appts + " appointments",
               value: +s.gross, color: PBS.charts.PALETTE[i % 5] };
    }));
  }
});
document.addEventListener("click", function (e) {
  function reset() {
    ["stRef","stName","stTitleField","stHours","stBio"].forEach(function (i) {
      document.getElementById(i).value = ""; });
    /* the configured default for this salon, not a number baked into the script */
    document.getElementById("stComm").value = DEFAULT_COMMISSION;
    document.getElementById("stPublic").checked = true;
    document.querySelectorAll("[data-cat]").forEach(function (c) { c.checked = false; });
  }
  if (e.target.closest("#addStaff")) {
    reset(); document.getElementById("stTitle").textContent = "Add staff member";
    PBS.openModal("staffModal"); return;
  }
  var b = e.target.closest("[data-edit]");
  if (b) {
    reset();
    var s = JSON.parse(b.getAttribute("data-edit"));
    document.getElementById("stTitle").textContent = "Edit " + s.name;
    document.getElementById("stRef").value = s.ref;
    document.getElementById("stName").value = s.name;
    document.getElementById("stTitleField").value = s.job_title || "";
    document.getElementById("stComm").value = parseFloat(s.commission_pct);
    document.getElementById("stHours").value = s.working_hours || "";
    document.getElementById("stBio").value = s.bio || "";
    document.getElementById("stPublic").checked = !!+s.is_public;
    (s.cats || []).forEach(function (slug) {
      var c = document.querySelector("[data-cat=\\"" + slug + "\\"]");
      if (c) c.checked = true;
    });
    PBS.openModal("staffModal");
  }
});
</script>']);
