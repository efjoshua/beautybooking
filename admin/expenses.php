<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/admin-layout.php';

require_permission('expenses');

/* ---- create / delete ---- */
$notice = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require();
    if (($_POST['action'] ?? '') === 'delete') {
        $ref = (string) ($_POST['ref'] ?? '');
        db_update('expenses', ['deleted_at' => date('Y-m-d H:i:s')], ['ref' => $ref]);
        audit("Expense $ref deleted", 'expense', $ref);
        redirect('admin/expenses.php?saved=1');
    }
    $amount = (float) ($_POST['amount'] ?? 0);
    $desc   = trim((string) ($_POST['description'] ?? ''));
    $catId  = (int) ($_POST['category_id'] ?? 0);
    $on     = (string) ($_POST['spent_on'] ?? '');

    if ($amount <= 0)            $notice = 'Enter an amount greater than zero.';
    elseif ($desc === '')        $notice = 'Enter a description.';
    elseif (!$catId)             $notice = 'Choose a category.';
    elseif (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $on)) $notice = 'Choose a valid date.';
    else {
        $ref = next_ref('expenses', 'EX-', 3);
        db_insert('expenses', [
            'ref' => $ref, 'category_id' => $catId, 'description' => $desc,
            'amount' => $amount,
            'payment_method' => in_array($_POST['payment_method'] ?? '', ['cash','momo','om','card'], true)
                ? $_POST['payment_method'] : 'cash',
            'spent_on' => $on,
            'recorded_by' => current_user()['staff_id'] ?: null,
            'is_recurring' => !empty($_POST['is_recurring']) ? 1 : 0,
        ]);
        audit("Expense $ref logged: " . money($amount), 'expense', $ref);
        redirect('admin/expenses.php?saved=1');
    }
}

$today = app_today();
$from  = (string) ($_GET['from'] ?? date('Y-m-01', strtotime($today)));
$to    = (string) ($_GET['to'] ?? date('Y-m-t', strtotime($today)));
$catF  = (string) ($_GET['cat'] ?? 'all');

$cats  = db_all('SELECT * FROM expense_categories ORDER BY name');
$sql   = 'SELECT e.*, ec.name cat, ec.slug cat_slug, s.name staff_name
            FROM expenses e JOIN expense_categories ec ON ec.id = e.category_id
       LEFT JOIN staff s ON s.id = e.recorded_by
           WHERE e.deleted_at IS NULL AND e.spent_on BETWEEN ? AND ?';
$p = [$from, $to];
if ($catF !== 'all') { $sql .= ' AND ec.slug = ?'; $p[] = $catF; }
$rows = db_all($sql . ' ORDER BY e.spent_on DESC, e.id DESC', $p);

$total   = array_sum(array_map(fn($r) => (float) $r['amount'], $rows));
$rev     = repo_revenue_between($from, $to);
$split   = repo_expense_breakdown($from, $to);
$monthly = repo_revenue_monthly(12);
$fixed   = (float) db_val(
    'SELECT COALESCE(SUM(e.amount),0) FROM expenses e
      WHERE e.deleted_at IS NULL AND e.is_recurring = 1 AND e.spent_on BETWEEN ? AND ?', [$from, $to]);
$biggest = $split[0] ?? null;

admin_head(['title' => 'Expenses', 'subtitle' => 'Costs &amp; net profit', 'page' => 'expenses.php']);
?>

<div class="page-actions">
  <div>
    <h2 class="mb-1" style="font-size:1.375rem">Expenses</h2>
    <p class="small muted mb-0">Every cost, so profit is real and not just takings</p>
  </div>
  <div class="spacer"></div>
  <div class="btn-row">
    <a class="btn btn-ghost btn-sm" href="<?= e(url('admin/export.php')) ?>?report=summary&amp;from=<?= e($from) ?>&amp;to=<?= e($to) ?>">Export CSV</a>
    <button class="btn btn-primary btn-sm" data-modal="expModal">Log expense</button>
  </div>
</div>

<?php if ($notice): ?>
  <div class="alert alert-danger mb-4"><span class="ico" data-icon="alert"></span>
    <span><b>Could not save</b><p><?= e($notice) ?></p></span></div>
<?php endif; ?>

<div class="stat-grid">
  <div class="stat accent-pink"><div class="top"><div><div class="lab">Logged in period</div>
    <div class="val"><?= e(money($total)) ?></div>
    <div class="sub"><?= count($rows) ?> entries</div></div>
    <div class="ico" data-icon="receipt"></div></div></div>
  <div class="stat accent-black"><div class="top"><div><div class="lab">Largest category</div>
    <div class="val" style="font-size:1.25rem"><?= e($biggest['name'] ?? '—') ?></div>
    <div class="sub"><?= $biggest ? e(money($biggest['amount'])) : 'Nothing logged' ?></div></div>
    <div class="ico" data-icon="users"></div></div></div>
  <div class="stat accent-gold"><div class="top"><div><div class="lab">Fixed / recurring</div>
    <div class="val"><?= e(money($fixed)) ?></div>
    <div class="sub">Rent, utilities, salaries</div></div>
    <div class="ico" data-icon="home"></div></div></div>
  <div class="stat accent-green"><div class="top"><div><div class="lab">Net profit</div>
    <div class="val"><?= e(money($rev['gross'] - $total)) ?></div>
    <div class="sub">Gross <?= e(money_short($rev['gross'])) ?> less logged costs</div></div>
    <div class="ico" data-icon="pie"></div></div></div>
</div>

<div class="dash-grid dash-2-1 mt-4">
  <div class="card card-pad-0">
    <div class="card-head"><div><h4>Expenses by month</h4>
      <span class="tiny muted">Twelve-month view</span></div></div>
    <div class="card-body"><div class="chart-box" id="expTrend"></div></div>
  </div>
  <div class="card card-pad-0">
    <div class="card-head"><h4>Where it goes</h4></div>
    <div class="card-body"><div id="expSplit"></div>
      <?php if (!$split): ?><p class="small muted mb-0">Nothing logged in this period.</p><?php endif; ?>
    </div>
  </div>
</div>

<form class="filter-bar mt-4" method="get">
  <span class="label mb-0" style="margin-right:4px">Period</span>
  <input class="input" type="date" name="from" value="<?= e($from) ?>">
  <span class="muted small">to</span>
  <input class="input" type="date" name="to" value="<?= e($to) ?>">
  <select class="select" name="cat">
    <option value="all">All categories</option>
    <?php foreach ($cats as $c): ?>
      <option value="<?= e($c['slug']) ?>"<?= $catF === $c['slug'] ? ' selected' : '' ?>><?= e($c['name']) ?></option>
    <?php endforeach; ?>
  </select>
  <button class="btn btn-ghost btn-sm" type="submit">Apply</button>
</form>

<div class="card card-pad-0">
  <div class="table-wrap">
    <table class="table table-cards" style="min-width:880px">
      <thead><tr><th>Date</th><th>Category</th><th>Description</th><th>Paid via</th>
        <th>Recorded by</th><th class="num">Amount</th><th class="num"></th></tr></thead>
      <tbody>
      <?php foreach ($rows as $r): ?>
        <tr><td><?= e(fmt_day($r['spent_on'])) ?><br><span class="tiny muted"><?= e($r['ref']) ?></span></td>
          <td><span class="badge badge-plain badge-gold"><?= e($r['cat']) ?></span>
            <?= $r['is_recurring'] ? ' <span class="tiny muted">recurring</span>' : '' ?></td>
          <td><?= e($r['description']) ?></td>
          <td><?= payment_badge($r['payment_method']) ?></td>
          <td><?= e($r['staff_name'] ?: '—') ?></td>
          <td class="num strong"><?= e(money($r['amount'])) ?></td>
          <td class="num">
            <form method="post" onsubmit="return confirm('Delete this expense? Reports will change.')">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="ref" value="<?= e($r['ref']) ?>">
              <button class="btn-icon" aria-label="Delete"><span data-icon="trash"></span></button>
            </form></td></tr>
      <?php endforeach; ?>
      <?php if (!$rows): ?><tr><td colspan="7"><div class="empty">
        <p>No expenses logged in this period.</p></div></td></tr><?php endif; ?>
      </tbody>
      <?php if ($rows): ?>
      <tfoot><tr><td colspan="5" class="strong">Total</td>
        <td class="num strong"><?= e(money($total)) ?></td><td></td></tr></tfoot>
      <?php endif; ?>
    </table>
  </div>
  <div class="card-foot">
    <p class="data-note mb-0">Restock entries created from the Inventory page appear here
      automatically, so stock spending is never counted twice.</p>
  </div>
</div>

<div class="modal" id="expModal">
  <div class="modal-backdrop"></div>
  <div class="modal-box">
    <form method="post">
      <?= csrf_field() ?>
      <div class="modal-head"><h3>Log an expense</h3>
        <button type="button" class="modal-close" data-modal-close>&times;</button></div>
      <div class="modal-body">
        <div class="field-row">
          <div class="field"><label class="label">Date <span class="req">*</span></label>
            <input class="input" type="date" name="spent_on" value="<?= e($today) ?>" required></div>
          <div class="field"><label class="label">Amount (<?= e(CURRENCY) ?>) <span class="req">*</span></label>
            <input class="input" type="number" name="amount" min="1" step="1" required></div>
        </div>
        <div class="field"><label class="label">Category <span class="req">*</span></label>
          <select class="select" name="category_id" required>
            <?php foreach ($cats as $c): ?>
              <option value="<?= (int) $c['id'] ?>"><?= e($c['name']) ?></option>
            <?php endforeach; ?>
          </select></div>
        <div class="field"><label class="label">Description <span class="req">*</span></label>
          <input class="input" name="description" required placeholder="e.g. ENEO electricity — September"></div>
        <div class="field"><label class="label">Paid with</label>
          <select class="select" name="payment_method">
            <option value="cash">Cash</option><option value="momo">MTN MoMo</option>
            <option value="om">Orange Money</option><option value="card">Card</option>
          </select></div>
        <label class="check mb-0"><input type="checkbox" name="is_recurring" value="1">
          This is a recurring monthly cost</label>
      </div>
      <div class="modal-foot">
        <button type="button" class="btn btn-ghost btn-sm" data-modal-close>Cancel</button>
        <button type="submit" class="btn btn-primary btn-sm">Save expense</button>
      </div>
    </form>
  </div>
</div>

<?php
admin_foot(['page' => 'expenses.php', 'inline' => '<script>
var MONTHLY = ' . json_encode($monthly) . ', SPLIT = ' . json_encode($split) . ';
document.addEventListener("DOMContentLoaded", function () {
  PBS.charts.line("#expTrend", {
    labels: MONTHLY.map(function (m) { return m.label; }),
    series: [{ name: "Expenses", color: "#C9A227", data: MONTHLY.map(function (m) { return +m.expenses; }) }]
  });
  PBS.charts.ranked("#expSplit", SPLIT.map(function (s) {
    return { label: s.name, value: +s.amount, color: s.color };
  }), { rank: false });
});
</script>']);
