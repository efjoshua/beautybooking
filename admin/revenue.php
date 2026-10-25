<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/admin-layout.php';

require_permission('revenue');

/* Period selector. Everything below derives from these two dates. */
$preset = (string) ($_GET['preset'] ?? 'month');
$today  = app_today();
switch ($preset) {
    case 'today':     $from = $to = $today; break;
    case 'week':      $from = date('Y-m-d', strtotime($today . ' -6 days')); $to = $today; break;
    case 'last':      $from = date('Y-m-01', strtotime($today . ' -1 month'));
                      $to   = date('Y-m-t',  strtotime($today . ' -1 month')); break;
    case 'year':      $from = date('Y-01-01', strtotime($today)); $to = date('Y-12-31', strtotime($today)); break;
    case 'custom':    $from = (string) ($_GET['from'] ?? date('Y-m-01', strtotime($today)));
                      $to   = (string) ($_GET['to']   ?? $today); break;
    default:          $preset = 'month';
                      $from = date('Y-m-01', strtotime($today)); $to = date('Y-m-t', strtotime($today));
}
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) $from = date('Y-m-01', strtotime($today));
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $to))   $to   = $today;
if ($from > $to) [$from, $to] = [$to, $from];

/* Previous period of equal length, for the deltas. */
$span     = max(1, (int) ((strtotime($to) - strtotime($from)) / 86400) + 1);
$prevTo   = date('Y-m-d', strtotime($from . ' -1 day'));
$prevFrom = date('Y-m-d', strtotime($prevTo . ' -' . ($span - 1) . ' days'));

$rev      = repo_revenue_between($from, $to);
$prevRev  = repo_revenue_between($prevFrom, $prevTo);
$expTotal = repo_expenses_between($from, $to);
$net      = $rev['gross'] - $expTotal;

$byCat    = repo_revenue_by_category($from, $to);
$byStaff  = repo_revenue_by_staff($from, $to);
$byPay    = repo_revenue_by_payment($from, $to);
$expSplit = repo_expense_breakdown($from, $to);
$monthly  = repo_revenue_monthly(12);
$daily    = repo_revenue_daily($from, $to);
$unpaid   = repo_appointments(['unpaid' => true]);

$exportUrl = url('admin/export.php') . '?' . http_build_query(['from' => $from, 'to' => $to]);

admin_head(['title' => 'Revenue &amp; Reports', 'subtitle' => 'Financial performance', 'page' => 'revenue.php']);
?>

<div class="page-actions">
  <div>
    <h2 class="mb-1" style="font-size:1.375rem">Revenue &amp; Financial Reports</h2>
    <p class="small muted mb-0">Period: <?= e(fmt_day($from)) ?> – <?= e(fmt_day($to)) ?>
      (<?= $span ?> day<?= $span === 1 ? '' : 's' ?>)</p>
  </div>
  <div class="spacer"></div>
  <div class="btn-row">
    <a class="btn btn-ghost btn-sm" href="<?= e($exportUrl) ?>&amp;report=summary">Export CSV</a>
    <a class="btn btn-dark btn-sm" href="expenses.php">Manage expenses</a>
  </div>
</div>

<form class="filter-bar" method="get">
  <span class="label mb-0" style="margin-right:4px">Period</span>
  <select class="select" name="preset" onchange="this.form.submit()">
    <?php foreach (['month' => 'This month', 'today' => 'Today', 'week' => 'Last 7 days',
                    'last' => 'Last month', 'year' => 'This year', 'custom' => 'Custom range…'] as $k => $lbl): ?>
      <option value="<?= $k ?>"<?= $preset === $k ? ' selected' : '' ?>><?= e($lbl) ?></option>
    <?php endforeach; ?>
  </select>
  <input class="input" type="date" name="from" value="<?= e($from) ?>">
  <span class="muted small">to</span>
  <input class="input" type="date" name="to" value="<?= e($to) ?>">
  <button class="btn btn-ghost btn-sm" type="submit" name="preset" value="custom">Apply range</button>
</form>

<div class="stat-grid">
  <div class="stat accent-pink"><div class="top"><div>
    <div class="lab">Gross Revenue</div>
    <div class="val"><?= e(money($rev['gross'])) ?></div>
    <div class="sub"><?= delta_tag($rev['gross'], $prevRev['gross']) ?> vs previous period</div>
  </div><div class="ico" data-icon="cash"></div></div></div>

  <div class="stat accent-black"><div class="top"><div>
    <div class="lab">Total Expenses</div>
    <div class="val"><?= e(money($expTotal)) ?></div>
    <div class="sub"><?= $rev['gross'] > 0 ? round($expTotal / $rev['gross'] * 100) : 0 ?>% of gross revenue</div>
  </div><div class="ico" data-icon="receipt"></div></div></div>

  <div class="stat accent-green"><div class="top"><div>
    <div class="lab">Net Profit</div>
    <div class="val"><?= e(money($net)) ?></div>
    <div class="sub">Margin <?= $rev['gross'] > 0 ? round($net / $rev['gross'] * 100) : 0 ?>%
      · <?= e(money($net / max(1, $span))) ?> / day</div>
  </div><div class="ico" data-icon="pie"></div></div></div>

  <div class="stat accent-gold"><div class="top"><div>
    <div class="lab">Outstanding / Unpaid</div>
    <div class="val"><?= e(money(array_sum(array_map(fn($a) => (float) $a['grand_total'], $unpaid)))) ?></div>
    <div class="sub"><?= count($unpaid) ?> completed appointment<?= count($unpaid) === 1 ? '' : 's' ?> not settled</div>
  </div><div class="ico" data-icon="alert"></div></div></div>
</div>

<div class="card card-pad-0 mt-4">
  <div class="card-head">
    <div><h4>Revenue against expenses</h4>
      <span class="tiny muted">Gross takings compared with what the salon spent</span></div>
    <div class="chips">
      <button class="chip active" data-range="monthly">Monthly</button>
      <button class="chip" data-range="daily">Daily (period)</button>
      <button class="chip" data-range="net">Net profit</button>
    </div>
  </div>
  <div class="card-body"><div class="chart-box" id="revChart"></div></div>
</div>

<div class="dash-grid dash-2-1 mt-4">
  <div class="card card-pad-0">
    <div class="card-head">
      <div><h4>Revenue by service category</h4>
        <span class="tiny muted">Where the money actually comes from</span></div>
      <a class="btn btn-ghost btn-sm" href="<?= e($exportUrl) ?>&amp;report=category">Export</a>
    </div>
    <div class="card-body"><div id="byCat"></div>
      <?php if (!$byCat): ?><p class="small muted mb-0">No completed work in this period.</p><?php endif; ?>
    </div>
  </div>
  <div class="card card-pad-0">
    <div class="card-head"><h4>Payment methods</h4></div>
    <div class="card-body"><div class="chart-box" id="byPay"></div></div>
  </div>
</div>

<div class="card card-pad-0 mt-4">
  <div class="card-head">
    <div><h4>Revenue by stylist &amp; commission due</h4>
      <span class="tiny muted">Commission is calculated on service price only —
        see Settings › Tax &amp; commission</span></div>
    <a class="btn btn-ghost btn-sm" href="<?= e($exportUrl) ?>&amp;report=commission">Export</a>
  </div>
  <div class="table-wrap">
    <table class="table table-cards" style="min-width:800px">
      <thead><tr><th>Stylist</th><th class="num">Appointments</th><th class="num">Revenue generated</th>
        <th class="num">Rate</th><th class="num">Commission due</th><th class="num">Salon keeps</th></tr></thead>
      <tbody>
      <?php
      $tA = $tG = $tC = 0;
      foreach ($byStaff as $s):
          $comm = (float) $s['commissionable'] * (float) $s['commission_pct'] / 100;
          $tA += (int) $s['appts']; $tG += (float) $s['gross']; $tC += $comm; ?>
        <tr>
          <td><span class="cell-user"><span class="avatar avatar-sm"><?= e(initials($s['name'])) ?></span>
            <span class="name"><?= e($s['name']) ?></span></span></td>
          <td class="num"><?= (int) $s['appts'] ?></td>
          <td class="num strong"><?= e(money($s['gross'])) ?></td>
          <td class="num"><?= rtrim(rtrim(number_format((float) $s['commission_pct'], 1), '0'), '.') ?>%</td>
          <td class="num"><?= e(money($comm)) ?></td>
          <td class="num"><?= e(money((float) $s['gross'] - $comm)) ?></td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$byStaff): ?><tr><td colspan="6"><div class="empty"><p>No completed work in this period.</p></div></td></tr><?php endif; ?>
      </tbody>
      <?php if ($byStaff): ?>
      <tfoot><tr><td class="strong">Totals</td><td class="num strong"><?= $tA ?></td>
        <td class="num strong"><?= e(money($tG)) ?></td><td class="num">—</td>
        <td class="num strong"><?= e(money($tC)) ?></td>
        <td class="num strong"><?= e(money($tG - $tC)) ?></td></tr></tfoot>
      <?php endif; ?>
    </table>
  </div>
</div>

<div class="dash-grid dash-2-1 mt-4">
  <div class="card card-pad-0">
    <div class="card-head">
      <div><h4>Expenses in this period</h4><span class="tiny muted">Rent, restock, salaries, utilities</span></div>
      <a class="btn btn-primary btn-sm" href="expenses.php">Log an expense</a>
    </div>
    <div class="table-wrap">
      <table class="table table-cards" style="min-width:680px">
        <thead><tr><th>Date</th><th>Category</th><th>Description</th><th>Paid via</th><th class="num">Amount</th></tr></thead>
        <tbody>
        <?php $exp = db_all(
            'SELECT e.*, ec.name cat FROM expenses e JOIN expense_categories ec ON ec.id = e.category_id
              WHERE e.deleted_at IS NULL AND e.spent_on BETWEEN ? AND ?
              ORDER BY e.spent_on DESC LIMIT 30', [$from, $to]);
        foreach ($exp as $x): ?>
          <tr><td><?= e(fmt_day($x['spent_on'])) ?></td>
            <td><span class="badge badge-plain badge-gold"><?= e($x['cat']) ?></span></td>
            <td><?= e($x['description']) ?></td>
            <td><?= payment_badge($x['payment_method']) ?></td>
            <td class="num strong"><?= e(money($x['amount'])) ?></td></tr>
        <?php endforeach; ?>
        <?php if (!$exp): ?><tr><td colspan="5"><div class="empty"><p>No expenses logged in this period.</p></div></td></tr><?php endif; ?>
        </tbody>
        <?php if ($exp): ?>
        <tfoot><tr><td colspan="4" class="strong">Total expenses</td>
          <td class="num strong"><?= e(money($expTotal)) ?></td></tr></tfoot>
        <?php endif; ?>
      </table>
    </div>
  </div>

  <div class="card card-pad-0">
    <div class="card-head"><h4>Profit calculation</h4></div>
    <div class="card-body">
      <p class="tiny muted mb-4">Gross revenue less every logged cost, so the number at
        the bottom is what the business actually kept.</p>
      <div class="kv"><span>Services</span><b><?= e(money($rev['services'])) ?></b></div>
      <div class="kv"><span>Add-ons / products used</span><b><?= e(money($rev['addons'])) ?></b></div>
      <div class="kv"><span>Tips</span><b><?= e(money($rev['tips'])) ?></b></div>
      <div class="kv"><span>Counter sales</span><b><?= e(money($rev['retail_gross'])) ?></b></div>
      <div class="kv" style="border-top:2px solid var(--n-300)"><span><b>Gross revenue</b></span>
        <b><?= e(money($rev['gross'])) ?></b></div>
      <?php foreach ($expSplit as $x): ?>
        <div class="kv"><span><span style="display:inline-block;width:10px;height:10px;border-radius:3px;
          background:<?= e($x['color']) ?>;margin-right:8px"></span><?= e($x['name']) ?></span>
          <b>− <?= e(money($x['amount'])) ?></b></div>
      <?php endforeach; ?>
      <div class="kv total"><span>Net profit</span><b class="t-pink"><?= e(money($net)) ?></b></div>
    </div>
  </div>
</div>

<div class="card card-pad-0 mt-4">
  <div class="card-head">
    <div><h4>Outstanding / unpaid appointments</h4>
      <span class="tiny muted">Completed services that have not been settled</span></div>
    <a class="btn btn-ghost btn-sm" href="<?= e($exportUrl) ?>&amp;report=outstanding">Export</a>
  </div>
  <div class="table-wrap">
    <table class="table table-cards" style="min-width:860px">
      <thead><tr><th>Reference</th><th>Client</th><th>Service</th><th>Stylist</th>
        <th class="num">Age</th><th class="num">Amount</th><th class="num">Action</th></tr></thead>
      <tbody>
      <?php foreach ($unpaid as $a):
        $age = (int) ((strtotime($today) - strtotime($a['booked_on'])) / 86400); ?>
        <tr><td><?= e($a['ref']) ?><br><span class="tiny muted"><?= e(fmt_day($a['booked_on'])) ?></span></td>
          <td><?= e($a['client_name']) ?><br><span class="tiny muted"><?= e($a['client_phone']) ?></span></td>
          <td><?= e($a['service_names']) ?></td>
          <td><?= e($a['staff_name'] ?: '—') ?></td>
          <td class="num"><span class="badge badge-danger"><?= $age ?> days</span></td>
          <td class="num strong"><?= e(money($a['grand_total'])) ?></td>
          <td class="num"><a class="btn btn-gold btn-sm"
            href="appointments.php?q=<?= e($a['ref']) ?>">Settle</a></td></tr>
      <?php endforeach; ?>
      <?php if (!$unpaid): ?><tr><td colspan="7"><div class="empty">
        <p>Nothing outstanding — every completed appointment is settled.</p></div></td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php
admin_foot(['page' => 'revenue.php', 'inline' => '<script>
var MONTHLY = ' . json_encode($monthly) . ';
var DAILY   = ' . json_encode($daily) . ';
var BYCAT   = ' . json_encode($byCat) . ';
var BYPAY   = ' . json_encode($byPay) . ';
document.addEventListener("DOMContentLoaded", function () {
  function monthly() {
    PBS.charts.bars("#revChart", {
      labels: MONTHLY.map(function (m) { return m.label; }),
      series: [
        { name: "Gross revenue", color: "#E91E8C", data: MONTHLY.map(function (m) { return +m.gross; }) },
        { name: "Expenses",      color: "#C9A227", data: MONTHLY.map(function (m) { return +m.expenses; }) }
      ]
    });
  }
  monthly();

  PBS.charts.ranked("#byCat", BYCAT.map(function (c) {
    return { label: c.name, sub: "· " + c.bookings + " bookings", value: +c.gross, color: c.color };
  }), { rank: false });

  PBS.charts.donut("#byPay", {
    slices: BYPAY.map(function (p) { return { label: p.label, value: +p.amount, color: p.color }; }),
    centerLabel: "PERIOD TOTAL"
  });

  document.querySelectorAll("[data-range]").forEach(function (b) {
    b.addEventListener("click", function () {
      document.querySelectorAll("[data-range]").forEach(function (x) { x.classList.remove("active"); });
      b.classList.add("active");
      var r = b.getAttribute("data-range");
      if (r === "monthly") return monthly();
      if (r === "daily") {
        PBS.charts.line("#revChart", {
          labels: DAILY.map(function (d) { return d.label; }),
          series: [{ name: "Gross revenue", color: "#E91E8C", data: DAILY.map(function (d) { return +d.gross; }) }]
        });
      } else {
        PBS.charts.line("#revChart", {
          labels: MONTHLY.map(function (m) { return m.label; }),
          series: [{ name: "Net profit", color: "#12A150",
                     data: MONTHLY.map(function (m) { return m.gross - m.expenses; }) }]
        });
      }
    });
  });
});
</script>']);
