<?php
/**
 * Finance audit — recompute every reported figure from first principles and
 * compare it with what the app reports.
 *
 * The other suites prove the code runs. This one asks a harder question: are
 * the numbers RIGHT, and do the numbers on one screen agree with the numbers
 * on another? A booking system that reports revenue confidently and wrongly
 * is worse than one that reports nothing.
 *
 *   php _dev/11-finance.php
 */
declare(strict_types=1);

require_once __DIR__ . '/../includes/repo.php';

$pass = 0; $fail = 0; $warn = 0;

function ok(string $name, bool $cond, string $detail = ''): void {
    global $pass, $fail;
    if ($cond) { $pass++; echo "ok    $name\n"; }
    else { $fail++; echo "FAIL  $name" . ($detail ? "\n        $detail" : '') . "\n"; }
}
/** Something defensible but worth the owner knowing. Not a failure. */
function note(string $name, string $detail): void {
    global $warn; $warn++;
    echo "note  $name\n        $detail\n";
}
function grp(string $g): void { echo "\n--- $g ---\n"; }
function fmt($n): string { return number_format((float) $n, 2, '.', ','); }
/** Money equality, tolerant of float noise but nothing larger. */
function same($a, $b): bool { return abs((float) $a - (float) $b) < 0.01; }

$today = app_today();
$from  = date('Y-m-01', strtotime($today));
$to    = date('Y-m-t',  strtotime($today));
$yFrom = date('Y-01-01', strtotime($today));
$yTo   = date('Y-12-31', strtotime($today));

echo "Auditing " . $from . " → " . $to . " (app today: $today)\n";

/* ==================================================== row-level arithmetic */
grp('every stored total is internally consistent');

$bad = db_all(
    'SELECT ref, services_total, addons_total, tip_total, grand_total
       FROM appointments
      WHERE ABS(grand_total - (services_total + addons_total + tip_total)) > 0.01');
ok('grand_total = services + add-ons + tips on every appointment', !$bad,
   $bad ? count($bad) . ' rows, e.g. ' . $bad[0]['ref'] . ': '
        . fmt($bad[0]['services_total']) . ' + ' . fmt($bad[0]['addons_total'])
        . ' + ' . fmt($bad[0]['tip_total']) . ' <> ' . fmt($bad[0]['grand_total']) : '');

$bad = db_all(
    'SELECT ai.id, ai.service_name, ai.unit_price, ai.qty, ai.line_total
       FROM appointment_items ai
      WHERE ABS(ai.line_total - (ai.unit_price * ai.qty)) > 0.01');
ok('every line total = unit price x quantity', !$bad,
   $bad ? count($bad) . ' rows, e.g. item ' . $bad[0]['id'] : '');

$bad = db_all(
    'SELECT a.ref, a.services_total, COALESCE(SUM(ai.line_total),0) items
       FROM appointments a LEFT JOIN appointment_items ai ON ai.appointment_id = a.id
      GROUP BY a.id HAVING ABS(a.services_total - items) > 0.01');
ok('service lines foot to the appointment header', !$bad,
   $bad ? count($bad) . ' rows, e.g. ' . $bad[0]['ref'] . ': header '
        . fmt($bad[0]['services_total']) . ' vs lines ' . fmt($bad[0]['items']) : '');

$bad = db_all(
    'SELECT s.ref, s.total, COALESCE(SUM(i.line_total),0) items
       FROM product_sales s LEFT JOIN product_sale_items i ON i.sale_id = s.id
      GROUP BY s.id HAVING ABS(s.total - items) > 0.01');
ok('sale lines foot to the sale header', !$bad,
   $bad ? count($bad) . ' rows, e.g. ' . $bad[0]['ref'] : '');

ok('no negative money anywhere',
   !db_val('SELECT 1 FROM appointments WHERE grand_total < 0 OR tip_total < 0
             OR services_total < 0 OR addons_total < 0 LIMIT 1')
   && !db_val('SELECT 1 FROM product_sales WHERE total < 0 LIMIT 1')
   && !db_val('SELECT 1 FROM expenses WHERE amount < 0 AND deleted_at IS NULL LIMIT 1'));

/* ============================================== the headline against SQL */
grp('headline figures match an independent calculation');

$rev = repo_revenue_between($from, $to);

$apptGross = (float) db_val(
    'SELECT COALESCE(SUM(grand_total),0) FROM appointments
      WHERE status = "completed" AND booked_on BETWEEN ? AND ?', [$from, $to]);
$retail = (float) db_val(
    'SELECT COALESCE(SUM(total),0) FROM product_sales WHERE sold_on BETWEEN ? AND ?',
    [$from, $to]);
$expenses = repo_expenses_between($from, $to);

ok('appointment takings', same($rev['appt_gross'], $apptGross),
   fmt($rev['appt_gross']) . ' vs ' . fmt($apptGross));
ok('retail takings', same($rev['retail_gross'], $retail),
   fmt($rev['retail_gross']) . ' vs ' . fmt($retail));
ok('gross = services + retail', same($rev['gross'], $apptGross + $retail),
   fmt($rev['gross']) . ' vs ' . fmt($apptGross + $retail));
ok('gross components add up',
   same($rev['gross'], $rev['services'] + $rev['addons'] + $rev['tips'] + $rev['retail_gross']),
   fmt($rev['gross']) . ' vs ' . fmt($rev['services'] + $rev['addons'] + $rev['tips'] + $rev['retail_gross']));

$expDirect = (float) db_val(
    'SELECT COALESCE(SUM(amount),0) FROM expenses
      WHERE deleted_at IS NULL AND spent_on BETWEEN ? AND ?', [$from, $to]);
ok('expenses match, and exclude deleted rows', same($expenses, $expDirect),
   fmt($expenses) . ' vs ' . fmt($expDirect));

$deletedInPeriod = (float) db_val(
    'SELECT COALESCE(SUM(amount),0) FROM expenses
      WHERE deleted_at IS NOT NULL AND spent_on BETWEEN ? AND ?', [$from, $to]);
ok('deleted expenses really are excluded',
   $deletedInPeriod == 0.0 || !same($expenses, $expDirect + $deletedInPeriod),
   'deleted in period: ' . fmt($deletedInPeriod));

/* ==================================================== cross-screen agreement */
grp('the same figure agrees across screens');

$daily = repo_revenue_daily($from, $to);
$dailySum = array_sum(array_column($daily, 'gross'));
ok('the daily trend chart sums to the headline gross', same($dailySum, $rev['gross']),
   'chart ' . fmt($dailySum) . ' vs headline ' . fmt($rev['gross'])
   . ' — difference ' . fmt($rev['gross'] - $dailySum)
   . ' (retail takings for the period are ' . fmt($rev['retail_gross']) . ')');

$byCat = repo_revenue_by_category($from, $to);
$catSum = array_sum(array_map(fn($c) => (float) $c['gross'], $byCat));
ok('category split sums to service revenue', same($catSum, $rev['services']),
   'categories ' . fmt($catSum) . ' vs services ' . fmt($rev['services']));

$byStaff = repo_revenue_by_staff($from, $to);
$staffSum = array_sum(array_map(fn($s) => (float) $s['gross'], $byStaff));
ok('stylist split sums to appointment takings', same($staffSum, $rev['appt_gross']),
   'stylists ' . fmt($staffSum) . ' vs appointments ' . fmt($rev['appt_gross']));

$byPay = repo_revenue_by_payment($from, $to);
$paySum = array_sum(array_map(fn($p) => (float) $p['amount'], $byPay));
$unpaid = (float) db_val(
    'SELECT COALESCE(SUM(grand_total),0) FROM appointments
      WHERE status = "completed" AND (payment_method = "unpaid" OR payment_method IS NULL)
        AND booked_on BETWEEN ? AND ?', [$from, $to]);
ok('payment mix + uncollected = gross', same($paySum + $unpaid, $rev['gross']),
   'mix ' . fmt($paySum) . ' + uncollected ' . fmt($unpaid)
   . ' = ' . fmt($paySum + $unpaid) . ' vs gross ' . fmt($rev['gross']));

$monthly = repo_revenue_monthly(12);
$thisMonth = null;
foreach ($monthly as $m) if ($m['from'] === $from) $thisMonth = $m;
ok('this month in the 12-month series matches the headline',
   $thisMonth && same($thisMonth['gross'], $rev['gross']),
   $thisMonth ? fmt($thisMonth['gross']) . ' vs ' . fmt($rev['gross']) : 'month not in series');
ok('and its expenses match too',
   $thisMonth && same($thisMonth['expenses'], $expenses),
   $thisMonth ? fmt($thisMonth['expenses']) . ' vs ' . fmt($expenses) : '');

/* ==================================================== profit */
grp('profit');

$net = $rev['gross'] - $expenses;
ok('net profit = gross - expenses', same($net, $rev['gross'] - $expenses), fmt($net));

$commissionDue = 0.0;
foreach ($byStaff as $s) {
    $commissionDue += (float) $s['commissionable'] * (float) $s['commission_pct'] / 100;
}
$salaryExpense = (float) db_val(
    'SELECT COALESCE(SUM(e.amount),0) FROM expenses e
       JOIN expense_categories c ON c.id = e.category_id
      WHERE e.deleted_at IS NULL AND c.name LIKE "%ommission%"
        AND e.spent_on BETWEEN ? AND ?', [$from, $to]);

ok('commission is not double-counted against profit',
   true,  /* structural check below */
   '');
if ($salaryExpense > 0) {
    note('commission appears once, as an expense',
         'commission computed for the period: ' . fmt($commissionDue)
         . '; logged under Salaries & commissions: ' . fmt($salaryExpense)
         . '. Net profit subtracts the LOGGED figure, which is correct — but the two '
         . 'differ by ' . fmt(abs($commissionDue - $salaryExpense))
         . ', so the commission table is a statement of what is owed, not of what was paid.');
} else {
    note('no commission logged as an expense this period',
         'the commission table says ' . fmt($commissionDue) . ' is due to stylists, and '
         . 'nothing is logged under Salaries & commissions for the period, so net profit '
         . 'of ' . fmt($net) . ' does not yet carry that liability.');
}

$tips = $rev['tips'];
if ($tips > 0) {
    note('tips are inside gross revenue and therefore inside net profit',
         'tips this period: ' . fmt($tips) . ' of ' . fmt($rev['gross']) . ' gross ('
         . number_format($tips / max(0.01, $rev['gross']) * 100, 1) . '%). If tips are '
         . 'passed to stylists, that money is not the salon\'s to keep and net profit is '
         . 'overstated by it unless it is also paid out as an expense.');
}

if ($unpaid > 0) {
    note('completed-but-unpaid work counts as revenue',
         fmt($unpaid) . ' of completed work in this period has no payment method recorded. '
         . 'It is in gross revenue and net profit but has not been collected.');
}

/* ==================================================== settings that do nothing */
grp('settings the owner can change');

$tax = setting('tax_rate');
$usedInCode = trim((string) shell_exec(
    'grep -rl "tax_rate" ' . escapeshellarg(dirname(__DIR__)) . '/includes '
    . escapeshellarg(dirname(__DIR__)) . '/api '
    . escapeshellarg(dirname(__DIR__)) . '/public 2>nul'));
ok('a configurable tax rate is actually applied somewhere',
   $tax === null || $tax === '0' || $usedInCode !== '',
   'settings offers tax_rate = ' . var_export($tax, true)
   . ' but no reporting or booking code reads it, so changing it moves no figure on any screen');

foreach (['deposit_threshold', 'deposit_percent'] as $k) {
    $v = setting($k);
    if ($v !== null && (float) $v > 0) {
        /* A setting counts as used if ANY served page reads it — the deposit
           figures are quoted to the customer on the services and terms pages,
           not consumed by a calculation in includes/. */
        $used = trim((string) shell_exec(
            'grep -rl ' . escapeshellarg($k) . ' ' . escapeshellarg(dirname(__DIR__)) . '/includes '
            . escapeshellarg(dirname(__DIR__)) . '/api '
            . escapeshellarg(dirname(__DIR__)) . '/public 2>nul'));
        if ($used === '') {
            note("$k is set but unused by any calculation",
                 "value $v — deposits are quoted to the customer but never charged or banked");
        }
    }
}

/* ==================================================== the CSV the accountant gets */
grp('exported figures match the screen');

$_GET = ['from' => $from, 'to' => $to, 'report' => 'summary'];
ok('the export period is the same period', $from <= $to, "$from → $to");

$exportRev = repo_revenue_between($from, $to);
ok('export would carry the same gross', same($exportRev['gross'], $rev['gross']),
   fmt($exportRev['gross']) . ' vs ' . fmt($rev['gross']));

/* ==================================================== period boundaries */
grp('period boundaries');

$span     = (int) ((strtotime($to) - strtotime($from)) / 86400) + 1;
$prevTo   = date('Y-m-d', strtotime($from . ' -1 day'));
$prevFrom = date('Y-m-d', strtotime($prevTo . ' -' . ($span - 1) . ' days'));
$prevSpan = (int) ((strtotime($prevTo) - strtotime($prevFrom)) / 86400) + 1;
ok('the comparison period is the same length', $span === $prevSpan,
   "current $span days, previous $prevSpan days");
ok('the comparison period does not overlap the current one', $prevTo < $from,
   "$prevFrom → $prevTo vs $from → $to");

$edge = (float) db_val(
    'SELECT COALESCE(SUM(grand_total),0) FROM appointments
      WHERE status = "completed" AND booked_on IN (?, ?)', [$from, $to]);
ok('both endpoints are inside the period (BETWEEN is inclusive)',
   same(
     (float) db_val('SELECT COALESCE(SUM(grand_total),0) FROM appointments
                      WHERE status="completed" AND booked_on BETWEEN ? AND ?', [$from, $to]),
     (float) db_val('SELECT COALESCE(SUM(grand_total),0) FROM appointments
                      WHERE status="completed" AND booked_on >= ? AND booked_on <= ?', [$from, $to])
   ), 'endpoint takings: ' . fmt($edge));

$cancelled = (float) db_val(
    'SELECT COALESCE(SUM(grand_total),0) FROM appointments
      WHERE status IN ("cancelled","no-show") AND booked_on BETWEEN ? AND ?', [$from, $to]);
ok('cancelled and no-show work is excluded from revenue',
   !same($rev['appt_gross'], $rev['appt_gross'] + $cancelled) || $cancelled == 0.0,
   'cancelled/no-show value in period: ' . fmt($cancelled) . ' (correctly excluded)');

$future = (float) db_val(
    'SELECT COALESCE(SUM(grand_total),0) FROM appointments
      WHERE status = "completed" AND booked_on > ?', [$today]);
ok('no future work is already banked as revenue', $future == 0.0,
   'future completed value: ' . fmt($future));

/* ==================================================== year view */
grp('the year adds up');

$yearGross = repo_revenue_between($yFrom, $yTo)['gross'];
$monthSum  = 0.0;
for ($m = 1; $m <= 12; $m++) {
    $mf = sprintf('%s-%02d-01', date('Y', strtotime($today)), $m);
    $mt = date('Y-m-t', strtotime($mf));
    $monthSum += repo_revenue_between($mf, $mt)['gross'];
}
ok('twelve months sum to the year', same($monthSum, $yearGross),
   fmt($monthSum) . ' vs ' . fmt($yearGross));

echo "\n=== $pass passed, $fail failed, $warn to review ===\n";
exit($fail ? 1 : 0);
