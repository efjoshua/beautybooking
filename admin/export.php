<?php
/**
 * CSV export for the financial reports.
 * The prototype showed a toast saying "phase 2"; this streams a real file.
 */
declare(strict_types=1);
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/repo.php';

require_permission('revenue');

$from   = (string) ($_GET['from'] ?? date('Y-m-01'));
$to     = (string) ($_GET['to'] ?? app_today());
$report = (string) ($_GET['report'] ?? 'summary');
foreach ([$from, $to] as $d) {
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $d)) { http_response_code(400); exit('Bad date.'); }
}

$name = sprintf('pbs-%s-%s-to-%s.csv', $report, $from, $to);
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $name . '"');

$out = fopen('php://output', 'w');
fwrite($out, "\xEF\xBB\xBF");            // BOM so Excel reads the accents

switch ($report) {
    case 'category':
        fputcsv($out, ['Category', 'Bookings', 'Gross (' . CURRENCY . ')']);
        foreach (repo_revenue_by_category($from, $to) as $r) {
            fputcsv($out, [$r['name'], $r['bookings'], number_format((float) $r['gross'], 2, '.', '')]);
        }
        break;

    case 'commission':
        fputcsv($out, ['Stylist', 'Appointments', 'Revenue generated', 'Rate %', 'Commission due', 'Salon keeps']);
        foreach (repo_revenue_by_staff($from, $to) as $r) {
            $c = (float) $r['commissionable'] * (float) $r['commission_pct'] / 100;
            fputcsv($out, [$r['name'], $r['appts'],
                number_format((float) $r['gross'], 2, '.', ''), $r['commission_pct'],
                number_format($c, 2, '.', ''), number_format((float) $r['gross'] - $c, 2, '.', '')]);
        }
        break;

    case 'outstanding':
        fputcsv($out, ['Reference', 'Date', 'Client', 'Phone', 'Service', 'Stylist', 'Amount']);
        foreach (repo_appointments(['unpaid' => true]) as $a) {
            fputcsv($out, [$a['ref'], $a['booked_on'], $a['client_name'], $a['client_phone'],
                $a['service_names'], $a['staff_name'], number_format((float) $a['grand_total'], 2, '.', '')]);
        }
        break;

    case 'appointments':
        fputcsv($out, ['Reference', 'Date', 'Start', 'Client', 'Service', 'Stylist',
                       'Status', 'Payment', 'Services', 'Add-ons', 'Tip', 'Total']);
        foreach (repo_appointments(['from' => $from, 'to' => $to]) as $a) {
            fputcsv($out, [$a['ref'], $a['booked_on'], substr((string) $a['starts_at'], 0, 5),
                $a['client_name'], $a['service_names'], $a['staff_name'], $a['status'],
                $a['payment_method'],
                number_format((float) $a['services_total'], 2, '.', ''),
                number_format((float) $a['addons_total'], 2, '.', ''),
                number_format((float) $a['tip_total'], 2, '.', ''),
                number_format((float) $a['grand_total'], 2, '.', '')]);
        }
        break;

    default:
        $rev = repo_revenue_between($from, $to);
        $exp = repo_expenses_between($from, $to);
        fputcsv($out, ['Princess Beauty Salon — summary', $from . ' to ' . $to]);
        fputcsv($out, []);
        fputcsv($out, ['Metric', 'Value (' . CURRENCY . ')']);
        foreach ([
            'Completed appointments' => $rev['appointments'],
            'Service revenue'  => number_format($rev['services'], 2, '.', ''),
            'Add-ons'          => number_format($rev['addons'], 2, '.', ''),
            'Tips'             => number_format($rev['tips'], 2, '.', ''),
            'Counter sales'    => number_format($rev['retail_gross'], 2, '.', ''),
            'GROSS REVENUE'    => number_format($rev['gross'], 2, '.', ''),
            'Total expenses'   => number_format($exp, 2, '.', ''),
            'NET PROFIT'       => number_format($rev['gross'] - $exp, 2, '.', ''),
        ] as $k => $v) { fputcsv($out, [$k, $v]); }

        fputcsv($out, []);
        fputcsv($out, ['Category', 'Bookings', 'Gross']);
        foreach (repo_revenue_by_category($from, $to) as $r) {
            fputcsv($out, [$r['name'], $r['bookings'], number_format((float) $r['gross'], 2, '.', '')]);
        }
        fputcsv($out, []);
        fputcsv($out, ['Payment method', 'Transactions', 'Amount']);
        foreach (repo_revenue_by_payment($from, $to) as $r) {
            fputcsv($out, [$r['label'], $r['txns'], number_format($r['amount'], 2, '.', '')]);
        }
}
fclose($out);
audit("Exported $report report ($from to $to)", 'report');
