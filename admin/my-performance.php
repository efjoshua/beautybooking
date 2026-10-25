<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/admin-layout.php';

$u  = require_permission('my-performance');
$me = $u['staff_id'] ? (int) $u['staff_id'] : null;

$today = app_today();
$period = (string) ($_GET['period'] ?? 'month');
if ($period === 'last') {
    $from = date('Y-m-01', strtotime($today . ' -1 month'));
    $to   = date('Y-m-t',  strtotime($today . ' -1 month'));
} elseif ($period === 'year') {
    $from = date('Y-01-01', strtotime($today)); $to = date('Y-12-31', strtotime($today));
} else {
    $period = 'month';
    $from = date('Y-m-01', strtotime($today)); $to = date('Y-m-t', strtotime($today));
}

$row = null;
foreach (repo_revenue_by_staff($from, $to) as $r) if ((int) $r['id'] === $me) $row = $r;

$gross  = (float) ($row['gross'] ?? 0);
$commPct = (float) ($u['commission_pct'] ?? 0);
$commission = (float) ($row['commissionable'] ?? 0) * $commPct / 100;
$appts  = (int) ($row['appts'] ?? 0);

$tips = (float) db_val(
    'SELECT COALESCE(SUM(tip_total), 0) FROM appointments
      WHERE staff_id = ? AND status = "completed" AND booked_on BETWEEN ? AND ?',
    [$me, $from, $to]);
$noShows = (int) db_val(
    'SELECT COUNT(*) FROM appointments WHERE staff_id = ? AND status = "no-show"
       AND booked_on BETWEEN ? AND ?', [$me, $from, $to]);

/* My own month-by-month, computed — not a share of the salon's curve. */
$trend = [];
for ($i = 11; $i >= 0; $i--) {
    $ts = strtotime($today . " -$i month");
    $mf = date('Y-m-01', $ts); $mt = date('Y-m-t', $ts);
    $trend[] = ['label' => date('M', $ts), 'gross' => (float) db_val(
        'SELECT COALESCE(SUM(grand_total), 0) FROM appointments
          WHERE staff_id = ? AND status = "completed" AND booked_on BETWEEN ? AND ?',
        [$me, $mf, $mt])];
}

$mix = db_all(
    'SELECT c.name, c.color, SUM(ai.line_total) gross
       FROM appointment_items ai
       JOIN appointments a ON a.id = ai.appointment_id
       JOIN services s ON s.id = ai.service_id
       JOIN service_categories c ON c.id = s.category_id
      WHERE a.staff_id = ? AND a.status = "completed" AND a.booked_on BETWEEN ? AND ?
      GROUP BY c.id ORDER BY gross DESC', [$me, $from, $to]);

$reviews = $me ? db_all('SELECT * FROM reviews WHERE is_public = 1 ORDER BY id LIMIT 3') : [];

admin_head(['title' => 'My Performance', 'subtitle' => 'Personal stats', 'page' => 'my-performance.php']);
?>

<div class="page-actions">
  <div><h2 class="mb-1" style="font-size:1.375rem">My Performance</h2>
    <p class="small muted mb-0">Your own numbers and commission</p></div>
  <div class="spacer"></div>
  <form method="get"><select class="select" name="period" onchange="this.form.submit()"
      style="width:auto;min-width:180px">
    <option value="month"<?= $period === 'month' ? ' selected' : '' ?>>This month</option>
    <option value="last"<?= $period === 'last' ? ' selected' : '' ?>>Last month</option>
    <option value="year"<?= $period === 'year' ? ' selected' : '' ?>>This year</option>
  </select></form>
</div>

<?php if (!$me): ?>
  <div class="empty"><p>Your account is not linked to a stylist profile, so there is nothing to report yet.</p></div>
<?php else: ?>

<div class="stat-grid">
  <div class="stat accent-pink"><div class="top"><div><div class="lab">Revenue Generated</div>
    <div class="val"><?= e(money($gross)) ?></div>
    <div class="sub"><?= $appts ?> appointments completed</div></div>
    <div class="ico" data-icon="cash"></div></div></div>
  <div class="stat accent-gold"><div class="top"><div><div class="lab">My Commission</div>
    <div class="val"><?= e(money($commission)) ?></div>
    <div class="sub">At <?= rtrim(rtrim(number_format($commPct, 1), '0'), '.') ?>% · paid 1st &amp; 16th</div></div>
    <div class="ico" data-icon="wallet"></div></div></div>
  <div class="stat accent-black"><div class="top"><div><div class="lab">Average Ticket</div>
    <div class="val"><?= e(money($appts ? $gross / $appts : 0)) ?></div>
    <div class="sub">Per completed appointment</div></div>
    <div class="ico" data-icon="check"></div></div></div>
  <div class="stat accent-green"><div class="top"><div><div class="lab">My Rating</div>
    <div class="val"><?= number_format((float) ($u['rating'] ?? 5), 1) ?> <small>/ 5</small></div>
    <div class="sub"><?= (int) ($u['reviews_count'] ?? 0) ?> client reviews</div></div>
    <div class="ico" data-icon="star"></div></div></div>
</div>

<div class="dash-grid dash-2-1 mt-4">
  <div class="card card-pad-0">
    <div class="card-head"><div><h4>My revenue by month</h4>
      <span class="tiny muted">What I have generated for the salon</span></div></div>
    <div class="card-body"><div class="chart-box" id="myTrend"></div></div>
  </div>
  <div class="card card-pad-0">
    <div class="card-head"><h4>Commission breakdown</h4></div>
    <div class="card-body">
      <div class="kv"><span>Commission rate</span><b><?= rtrim(rtrim(number_format($commPct, 1), '0'), '.') ?>%</b></div>
      <div class="kv"><span>Revenue generated</span><b><?= e(money($gross)) ?></b></div>
      <div class="kv"><span>Commissionable (services)</span><b><?= e(money($row['commissionable'] ?? 0)) ?></b></div>
      <div class="kv"><span>Commission earned</span><b><?= e(money($commission)) ?></b></div>
      <div class="kv"><span>Tips (kept in full)</span><b><?= e(money($tips)) ?></b></div>
      <div class="kv"><span>Appointments completed</span><b><?= $appts ?></b></div>
      <div class="kv"><span>No-shows on my diary</span><b><?= $noShows ?></b></div>
      <div class="kv total"><span>Estimated payout</span>
        <b class="t-pink"><?= e(money($commission + $tips)) ?></b></div>
    </div>
  </div>
</div>

<div class="dash-grid dash-1-1 mt-4">
  <div class="card card-pad-0">
    <div class="card-head"><div><h4>What I spend my time on</h4>
      <span class="tiny muted">Revenue by service category</span></div></div>
    <div class="card-body"><div id="myMix"></div>
      <?php if (!$mix): ?><p class="small muted mb-0">No completed work in this period.</p><?php endif; ?></div>
  </div>
  <div class="card card-pad-0">
    <div class="card-head"><div><h4>Recent reviews</h4><span class="tiny muted">What clients said</span></div></div>
    <div class="card-body">
      <?php foreach ($reviews as $r): ?>
        <div class="card card-flat mb-3">
          <div class="stars"><?= str_repeat('★', (int) $r['stars']) ?></div>
          <p class="small mb-2" style="font-style:italic">“<?= e($r['body']) ?>”</p>
          <span class="tiny muted"><?= e($r['client_name']) ?> · <?= e($r['service_label']) ?></span>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</div>

<div class="alert alert-info mt-4">
  <span class="ico" data-icon="alert"></span>
  <span><b>You are seeing only your own figures</b>
    <p>Salon-wide revenue, other stylists' performance and all expense data are restricted
       to the Owner and Manager roles.</p></span>
</div>

<?php endif; ?>

<?php
admin_foot(['page' => 'my-performance.php', 'inline' => '<script>
var TREND = ' . json_encode($trend) . ', MIX = ' . json_encode($mix) . ';
document.addEventListener("DOMContentLoaded", function () {
  if (document.getElementById("myTrend")) {
    PBS.charts.line("#myTrend", {
      labels: TREND.map(function (t) { return t.label; }),
      series: [{ name: "My revenue", color: "#E91E8C",
                 data: TREND.map(function (t) { return +t.gross; }) }]
    });
  }
  if (MIX.length) {
    PBS.charts.ranked("#myMix", MIX.map(function (m) {
      return { label: m.name, value: +m.gross, color: m.color };
    }), { rank: false });
  }
});
</script>']);
