<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/admin-layout.php';

$u = require_permission('dashboard');

$today   = app_today();
$isOwner = can('revenue');                       // the financial view, not the role name
$myStaff = $u['staff_id'] ? (int) $u['staff_id'] : null;

/* A stylist sees only their own diary. Scoping happens in the query. */
$scope = $isOwner ? [] : ['staff_id' => $myStaff];

$todayAppts = repo_appointments($scope + ['date' => $today]);
$counts = ['completed' => 0, 'upcoming' => 0, 'lost' => 0];
foreach ($todayAppts as $a) {
    if ($a['status'] === 'completed') $counts['completed']++;
    elseif (in_array($a['status'], ['confirmed', 'pending'], true)) $counts['upcoming']++;
    else $counts['lost']++;
}

if ($isOwner) {
    $s      = repo_dashboard_stats();
    $health = repo_business_health($s['month_from'], $s['month_to']);
    $trend  = repo_revenue_daily(date('Y-m-d', strtotime($today . ' -13 days')), $today);
    $topSvc = repo_top_services($s['month_from'], $s['month_to'], 6);
    $topStf = repo_revenue_by_staff($s['month_from'], $s['month_to']);
    $lowStock = repo_low_stock();
    $netProfit = $s['month']['gross'] - $s['month_expenses'];
} else {
    $monthFrom = date('Y-m-01', strtotime($today));
    $monthTo   = date('Y-m-t', strtotime($today));
    $mine      = repo_revenue_by_staff($monthFrom, $monthTo);
    $myRow     = null;
    foreach ($mine as $r) if ((int) $r['id'] === $myStaff) $myRow = $r;
    $next = null;
    foreach ($todayAppts as $a) {
        if (in_array($a['status'], ['confirmed', 'pending'], true)) {
            if (!$next || $a['starts_at'] < $next['starts_at']) $next = $a;
        }
    }
}

admin_head([
    'title'    => 'Dashboard',
    'subtitle' => $isOwner ? 'Business overview' : 'My day',
    'page'     => 'index.php',
]);
?>

<div class="page-actions">
  <div>
    <h2 class="mb-1" style="font-size:1.5rem">
      Good morning, <?= e(explode(' ', $u['name'])[0]) ?> <?= is_owner() ? '👑' : '' ?>
    </h2>
    <p class="small muted mb-0"><?= e(fmt_date_long($today)) ?></p>
  </div>
  <div class="spacer"></div>
  <div class="btn-row">
    <a href="appointments.php" class="btn btn-ghost btn-sm">View diary</a>
    <?php if ($isOwner): ?>
      <a href="revenue.php" class="btn btn-dark btn-sm">Financial reports</a>
    <?php endif; ?>
  </div>
</div>

<!-- KPI ROW -->
<div class="stat-grid">
<?php if ($isOwner): ?>
  <div class="stat accent-pink">
    <div class="top"><div>
      <div class="lab">Today's Revenue</div>
      <div class="val"><?= e(money($s['today']['gross'])) ?></div>
      <div class="sub"><?= delta_tag($s['today']['gross'], $s['today_prev']['gross']) ?>
        vs yesterday · <?= (int) $s['today']['appointments'] ?> appointments</div>
    </div><div class="ico" data-icon="cash"></div></div>
    <div class="spark mt-3" id="sparkToday"></div>
  </div>

  <div class="stat accent-gold">
    <div class="top"><div>
      <div class="lab">This Week</div>
      <div class="val"><?= e(money($s['week']['gross'])) ?></div>
      <div class="sub"><?= delta_tag($s['week']['gross'], $s['week_prev']['gross']) ?>
        vs last week · <?= (int) $s['week']['appointments'] ?> appointments</div>
    </div><div class="ico" data-icon="chart"></div></div>
    <div class="spark mt-3" id="sparkWeek"></div>
  </div>

  <div class="stat accent-black">
    <div class="top"><div>
      <div class="lab">This Month</div>
      <div class="val"><?= e(money($s['month']['gross'])) ?></div>
      <div class="sub"><?= delta_tag($s['month']['gross'], $s['month_prev']['gross']) ?>
        vs last month · <?= (int) $s['month']['appointments'] ?> appointments</div>
    </div><div class="ico" data-icon="wallet"></div></div>
  </div>

  <div class="stat accent-green">
    <div class="top"><div>
      <div class="lab">Net Profit (Month)</div>
      <div class="val"><?= e(money($netProfit)) ?></div>
      <div class="sub">Gross <?= e(money_short($s['month']['gross'])) ?> −
        expenses <?= e(money_short($s['month_expenses'])) ?>
        <?php if ($s['month']['gross'] > 0): ?>
          · margin <?= (int) round($netProfit / $s['month']['gross'] * 100) ?>%
        <?php endif; ?></div>
    </div><div class="ico" data-icon="pie"></div></div>
  </div>
<?php else: ?>
  <div class="stat accent-pink">
    <div class="top"><div>
      <div class="lab">My Appointments Today</div>
      <div class="val"><?= count($todayAppts) ?></div>
      <div class="sub"><?= $counts['completed'] ?> completed · <?= $counts['upcoming'] ?> upcoming</div>
    </div><div class="ico" data-icon="calendar"></div></div>
  </div>
  <div class="stat accent-gold">
    <div class="top"><div>
      <div class="lab">My Appointments This Month</div>
      <div class="val"><?= (int) ($myRow['appts'] ?? 0) ?></div>
      <div class="sub">Completed and paid</div>
    </div><div class="ico" data-icon="users"></div></div>
  </div>
  <div class="stat accent-black">
    <div class="top"><div>
      <div class="lab">My Rating</div>
      <div class="val"><?= number_format((float) ($u['rating'] ?? 5), 1) ?> <small>/ 5</small></div>
      <div class="sub"><?= (int) ($u['reviews_count'] ?? 0) ?> client reviews</div>
    </div><div class="ico" data-icon="star"></div></div>
  </div>
  <div class="stat accent-green">
    <div class="top"><div>
      <div class="lab">Next Appointment</div>
      <div class="val"><?= $next ? e(fmt_time($next['starts_at'])) : '—' ?></div>
      <div class="sub"><?= $next
        ? e($next['client_name'] . ' · ' . $next['service_names'])
        : 'Nothing left today' ?></div>
    </div><div class="ico" data-icon="clock"></div></div>
  </div>
<?php endif; ?>
</div>

<!-- TREND + TODAY -->
<div class="dash-grid <?= $isOwner ? 'dash-2-1' : '' ?> mt-4">
  <?php if ($isOwner): ?>
  <div class="card card-pad-0">
    <div class="card-head">
      <div><h4>Revenue trend</h4><span class="tiny muted">Daily gross, last 14 days</span></div>
    </div>
    <div class="card-body"><div class="chart-box" id="revTrend"></div></div>
  </div>
  <?php endif; ?>

  <div class="card card-pad-0">
    <div class="card-head"><h4>Today at a glance</h4>
      <a href="appointments.php" class="tiny t-pink">Open diary</a></div>
    <div class="card-body">
      <div class="grid g-3" style="gap:12px">
        <div class="text-center"><div style="font-family:var(--font-serif);font-size:1.75rem;color:var(--success)"><?= $counts['completed'] ?></div><div class="tiny muted">Completed</div></div>
        <div class="text-center"><div style="font-family:var(--font-serif);font-size:1.75rem;color:var(--info)"><?= $counts['upcoming'] ?></div><div class="tiny muted">Upcoming</div></div>
        <div class="text-center"><div style="font-family:var(--font-serif);font-size:1.75rem;color:var(--danger)"><?= $counts['lost'] ?></div><div class="tiny muted">Cancelled / No-show</div></div>
      </div>
      <?php $done = count($todayAppts) ? $counts['completed'] / count($todayAppts) : 0; ?>
      <div class="progress mt-4"><i style="width:<?= round($done * 100) ?>%"></i></div>
      <p class="tiny muted mt-2 mb-0">
        <?= $counts['completed'] ?> of <?= count($todayAppts) ?> of today's bookings are done
        — <?= round($done * 100) ?>% through the day.</p>
    </div>
  </div>
</div>

<?php if ($isOwner): ?>
<!-- TOP PERFORMERS -->
<div class="dash-grid dash-1-1 mt-4">
  <div class="card card-pad-0">
    <div class="card-head">
      <div><h4>Top services</h4><span class="tiny muted">By revenue, this month</span></div>
      <a href="revenue.php" class="tiny t-pink">Full report</a>
    </div>
    <div class="card-body"><div id="topServices"></div></div>
  </div>
  <div class="card card-pad-0">
    <div class="card-head">
      <div><h4>Top stylists</h4><span class="tiny muted">By revenue generated, this month</span></div>
      <a href="staff.php" class="tiny t-pink">Manage staff</a>
    </div>
    <div class="card-body"><div id="topStaff"></div></div>
  </div>
</div>
<?php endif; ?>

<!-- AGENDA + SIDE -->
<div class="dash-grid <?= $isOwner ? 'dash-2-1' : '' ?> mt-4">
  <div class="card card-pad-0">
    <div class="card-head">
      <div><h4>Today's appointments</h4><span class="tiny muted">Chronological</span></div>
      <a href="appointments.php" class="btn btn-ghost btn-sm">See all</a>
    </div>
    <div class="card-body">
      <?php if (!$todayAppts): ?>
        <div class="empty"><p>No appointments booked for today.</p></div>
      <?php else: ?>
        <?php
        usort($todayAppts, fn($a, $b) => strcmp($a['starts_at'], $b['starts_at']));
        foreach ($todayAppts as $a): ?>
          <div class="agenda-item">
            <div class="time"><b><?= e(fmt_time($a['starts_at'])) ?></b>
              <span><?= e(duration((int) $a['duration_minutes'])) ?></span></div>
            <div class="body">
              <b><?= e($a['client_name']) ?></b>
              <small><?= e($a['service_names']) ?><?= $a['staff_name'] ? ' · ' . e($a['staff_name']) : '' ?></small>
            </div>
            <div class="right">
              <?= status_badge($a['status']) ?>
              <span class="amt"><?= e(money($a['grand_total'])) ?></span>
            </div>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </div>

  <?php if ($isOwner): ?>
  <div class="grid" style="gap:16px;align-content:start">
    <div class="card card-pad-0">
      <div class="card-head">
        <div><h4>Low stock alerts</h4><span class="tiny muted">At or below reorder level</span></div>
        <a href="inventory.php" class="tiny t-pink">Store</a>
      </div>
      <div class="card-body">
        <?php if (!$lowStock): ?>
          <p class="small muted mb-0">Every product is above its reorder threshold.</p>
        <?php else: foreach (array_slice($lowStock, 0, 5) as $p):
          $ratio = $p['reorder_level'] > 0 ? min(100, $p['qty_on_hand'] / $p['reorder_level'] * 100) : 0;
          $col = $p['qty_on_hand'] == 0 ? 'var(--danger)' : 'var(--warning)'; ?>
          <div class="bar-row">
            <span class="nm"><?= e($p['name']) ?> <span class="tiny muted">· <?= e($p['supplier']) ?></span></span>
            <span class="vl"><?= (int) $p['qty_on_hand'] ?> / <?= (int) $p['reorder_level'] ?> <?= e($p['unit']) ?></span>
            <span class="track"><i style="width:<?= $ratio ?>%;background:<?= $col ?>"></i></span>
          </div>
        <?php endforeach; endif; ?>
      </div>
    </div>

    <div class="card card-pad-0">
      <div class="card-head"><h4>Business health</h4></div>
      <div class="card-body">
        <div class="kv"><span>New clients this week</span><b><?= (int) $s['new_clients_week'] ?></b></div>
        <div class="kv"><span>New clients this month</span><b><?= (int) $s['new_clients_month'] ?></b></div>
        <div class="kv"><span>Returning client rate</span><b><?= (int) $health['returning_pct'] ?>%</b></div>
        <div class="kv"><span>Average ticket value</span><b><?= e(money($health['avg_ticket'])) ?></b></div>
        <div class="kv"><span>Cancellation rate</span><b><?= $health['cancel_pct'] ?>%</b></div>
        <div class="kv total"><span>Unpaid appointments</span>
          <b class="t-pink"><?= e(money($s['outstanding']['amount'])) ?>
            <span class="tiny muted">(<?= (int) $s['outstanding']['n'] ?>)</span></b></div>
      </div>
    </div>
  </div>
  <?php endif; ?>
</div>

<?php
$inline = '';
if ($isOwner) {
    $inline = '<script>
    var TREND = ' . json_encode($trend) . ';
    var TOP_SVC = ' . json_encode($topSvc) . ';
    var TOP_STF = ' . json_encode(array_map(fn($r) => [
        'name' => $r['name'], 'appts' => (int) $r['appts'],
        'gross' => (float) $r['gross'], 'rating' => (float) $r['rating'],
    ], $topStf)) . ';
    document.addEventListener("DOMContentLoaded", function () {
      PBS.charts.line("#revTrend", {
        labels: TREND.map(function (d) { return d.label; }),
        series: [{ name: "Gross revenue", color: "#E91E8C",
                   data: TREND.map(function (d) { return d.gross; }) }]
      });
      var last7 = TREND.slice(-7).map(function (d) { return d.gross; });
      PBS.charts.spark("#sparkToday", { data: last7, color: "#E91E8C" });
      PBS.charts.spark("#sparkWeek", { data: TREND.map(function (d) { return d.gross; }), color: "#C9A227" });

      PBS.charts.ranked("#topServices", TOP_SVC.map(function (t, i) {
        return { label: t.name, sub: "· " + t.bookings + " bookings",
                 value: +t.gross, color: PBS.charts.PALETTE[i % 5] };
      }));
      PBS.charts.ranked("#topStaff", TOP_STF.map(function (t, i) {
        return { label: t.name, sub: "· " + t.appts + " done · ★" + t.rating.toFixed(1),
                 value: t.gross, color: PBS.charts.PALETTE[i % 5] };
      }));
    });
    </script>';
}
admin_foot(['page' => 'index.php', 'inline' => $inline]);
