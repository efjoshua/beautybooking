<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/admin-layout.php';

$u = require_permission('my-clients');
$me = $u['staff_id'] ? (int) $u['staff_id'] : null;
$q = trim((string) ($_GET['q'] ?? ''));

$rows = $me ? repo_clients(['staff_id' => $me, 'q' => $q ?: null]) : [];

/* Figures are scoped to work THIS stylist did, not the client's whole history. */
$mineBy = [];
if ($me) {
    foreach (db_all(
        'SELECT client_id, COUNT(*) n, SUM(CASE WHEN status = "completed" THEN grand_total ELSE 0 END) spend,
                MAX(booked_on) last_seen
           FROM appointments WHERE staff_id = ? GROUP BY client_id', [$me]) as $r) {
        $mineBy[(int) $r['client_id']] = $r;
    }
}
$myRevenue = array_sum(array_map(fn($r) => (float) $r['spend'], $mineBy));
$repeat = count(array_filter($mineBy, fn($r) => (int) $r['n'] > 1));

admin_head(['title' => 'My Clients', 'subtitle' => 'People I have served', 'page' => 'my-clients.php']);
?>
<div class="page-actions">
  <div><h2 class="mb-1" style="font-size:1.375rem">My Clients</h2>
    <p class="small muted mb-0"><?= count($rows) ?> client<?= count($rows) === 1 ? '' : 's' ?></p></div>
  <div class="spacer"></div>
  <a href="my-schedule.php" class="btn btn-dark btn-sm">My schedule</a>
</div>

<?php if (!$me): ?>
  <div class="empty"><p>Your account is not linked to a stylist profile, so you have no client book yet.</p></div>
<?php else: ?>

<div class="stat-grid">
  <div class="stat accent-pink"><div class="top"><div><div class="lab">My Clients</div>
    <div class="val"><?= count($rows) ?></div>
    <div class="sub">People who have sat in my chair</div></div>
    <div class="ico" data-icon="users"></div></div></div>
  <div class="stat accent-gold"><div class="top"><div><div class="lab">Revenue I Generated</div>
    <div class="val"><?= e(money($myRevenue)) ?></div>
    <div class="sub">All time, completed work only</div></div>
    <div class="ico" data-icon="cash"></div></div></div>
  <div class="stat accent-black"><div class="top"><div><div class="lab">Repeat Clients</div>
    <div class="val"><?= $repeat ?></div>
    <div class="sub">Booked me more than once</div></div>
    <div class="ico" data-icon="chart"></div></div></div>
  <div class="stat accent-green"><div class="top"><div><div class="lab">My Rating</div>
    <div class="val"><?= number_format((float) ($u['rating'] ?? 5), 1) ?> <small>/ 5</small></div>
    <div class="sub"><?= (int) ($u['reviews_count'] ?? 0) ?> reviews</div></div>
    <div class="ico" data-icon="star"></div></div></div>
</div>

<form class="filter-bar mt-4" method="get">
  <div class="search" style="flex:1;min-width:200px">
    <svg viewBox="0 0 24 24" width="17" height="17"><circle cx="11" cy="11" r="7"/><path d="M20 20l-3.6-3.6"/></svg>
    <input class="input" name="q" type="search" value="<?= e($q) ?>" placeholder="Search my clients…">
  </div>
  <button class="btn btn-ghost btn-sm" type="submit">Search</button>
</form>

<div class="card card-pad-0">
  <div class="table-wrap">
    <table class="table table-cards" style="min-width:820px">
      <thead><tr><th>Client</th><th class="num">Times with me</th><th class="num">Revenue I generated</th>
        <th>Last seen by me</th><th class="num">Contact</th></tr></thead>
      <tbody>
      <?php foreach ($rows as $c): $m = $mineBy[(int) $c['id']] ?? null;
        $digits = preg_replace('/\D/', '', (string) $c['phone']); ?>
        <tr>
          <td><span class="cell-user"><span class="avatar avatar-sm avatar-pink"><?= e(initials($c['name'])) ?></span>
            <span><span class="name"><?= e($c['name']) ?></span>
              <span class="tiny muted"><?= e($c['phone']) ?></span></span></span></td>
          <td class="num"><?= (int) ($m['n'] ?? 0) ?></td>
          <td class="num strong"><?= e(money($m['spend'] ?? 0)) ?></td>
          <td><?= e($m && $m['last_seen'] ? fmt_day($m['last_seen']) : '—') ?></td>
          <td class="num"><a class="btn btn-ghost btn-sm" target="_blank" rel="noopener"
            href="https://wa.me/<?= e($digits) ?>">Message</a></td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$rows): ?><tr><td colspan="5"><div class="empty">
        <p>No clients match that search.</p></div></td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
  <div class="card-foot">
    <p class="data-note mb-0">You only see clients you have personally served, and the figures
      count only your own work. Salon-wide client records are owner-only.</p>
  </div>
</div>
<?php endif; ?>
<?php admin_foot(['page' => 'my-clients.php']); ?>
