<?php
/**
 * Princess Beauty Salon — printable receipt
 *
 *   admin/receipt.php?ref=PBS-4821            open it
 *   admin/receipt.php?ref=PBS-4821&print=1    open it and raise the print dialog
 *
 * Deliberately a page of its own rather than a modal: it has to survive being
 * printed, saved as PDF, and sent to a client on WhatsApp, and none of those
 * work on something that only exists inside another screen.
 *
 * The layout is an 80mm till roll by default because that is what sits on the
 * counter. On A4 it centres the same column rather than stretching the figures
 * across a page nobody can read.
 */
declare(strict_types=1);
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/repo.php';

$u = require_permission('appointments');

$a = repo_appointment_by_ref((string) ($_GET['ref'] ?? ''));
if (!$a) {
    http_response_code(404);
    exit('That appointment does not exist.');
}

/* A stylist may print their own chair's receipts and nobody else's. The list
   page scopes its query; this page is reachable by URL, so it checks again. */
if (!can('revenue') && (int) $a['staff_id'] !== (int) $u['staff_id']) {
    audit("Denied receipt for {$a['ref']}", 'security');
    redirect('admin/appointments.php');
}

$paid      = $a['payment_method'] !== 'unpaid';
$deposit   = (float) $a['deposit_paid'];
$grand     = (float) $a['grand_total'] > 0 ? (float) $a['grand_total'] : (float) $a['services_total'];
$balance   = max(0, $grand - $deposit);
$methods   = ['cash' => 'Cash', 'momo' => 'MTN Mobile Money', 'om' => 'Orange Money',
              'card' => 'Card', 'unpaid' => 'Not yet paid'];
$autoPrint = !empty($_GET['print']);

audit("Printed receipt for {$a['ref']}", 'appointment', (string) $a['id']);
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Receipt <?= e($a['ref']) ?> — <?= e(setting('salon_name', APP_NAME)) ?></title>
<style>
  :root{ --ink:#0B0B0B; --muted:#6B6B74; --rule:#D9D9E0; --gold:#9C7C15; }
  *{ box-sizing:border-box; }
  body{
    margin:0; padding:24px 12px 60px; background:#EDEDF1;
    font:13px/1.5 "Segoe UI",-apple-system,Helvetica,Arial,sans-serif; color:var(--ink);
  }
  .roll{
    width:80mm; max-width:100%; margin:0 auto; background:#fff; padding:9mm 7mm 7mm;
    box-shadow:0 6px 28px rgba(11,11,11,.14);
  }
  .brand{ text-align:center; border-bottom:1px solid var(--rule); padding-bottom:5mm; }
  .brand .name{ font:italic 700 17px Georgia,serif; color:#E91E8C; }
  .brand .sub{ font-size:8.5px; letter-spacing:.34em; text-transform:uppercase; margin-top:2px; }
  .brand .meta{ font-size:10.5px; color:var(--muted); margin-top:6px; line-height:1.45; }

  h1{ font-size:11px; letter-spacing:.22em; text-transform:uppercase; text-align:center;
      margin:5mm 0 1mm; color:var(--gold); }
  .ref{ text-align:center; font:700 15px "Consolas",monospace; letter-spacing:.04em;
        margin-bottom:5mm; }

  .kv{ width:100%; font-size:11.5px; margin-bottom:4mm; }
  .kv td{ padding:1.5px 0; vertical-align:top; }
  .kv td:first-child{ color:var(--muted); width:38%; }
  .kv td:last-child{ text-align:right; font-weight:600; }

  table.lines{ width:100%; border-collapse:collapse; font-size:11.5px;
               border-top:1px dashed var(--rule); }
  table.lines th{ text-align:left; font-size:9px; letter-spacing:.14em; text-transform:uppercase;
                  color:var(--muted); padding:3mm 0 1.5mm; font-weight:600; }
  table.lines th.n, table.lines td.n{ text-align:right; }
  table.lines td{ padding:1.6mm 0; border-bottom:1px solid #F1F1F5; vertical-align:top; }
  table.lines .qty{ color:var(--muted); font-size:10.5px; }

  .totals{ width:100%; font-size:12px; margin-top:3mm; }
  .totals td{ padding:1.4mm 0; }
  .totals td:last-child{ text-align:right; font-weight:600; }
  .totals tr.grand td{ border-top:2px solid var(--ink); padding-top:2.5mm; font-size:15px;
                       font-weight:700; }
  .totals tr.bal td{ color:#C2185B; font-weight:700; }

  .stamp{ margin-top:5mm; text-align:center; font-size:10px; letter-spacing:.2em;
          text-transform:uppercase; padding:2.5mm; border:1px dashed var(--rule); }
  .stamp.paid{ color:#1B7F4B; border-color:#9BD6B4; }
  .stamp.due{ color:#C2185B; border-color:#F0A9CB; }

  .thanks{ text-align:center; font-size:10.5px; color:var(--muted); margin-top:5mm;
           border-top:1px dashed var(--rule); padding-top:4mm; line-height:1.5; }
  .thanks b{ color:var(--ink); }

  .bar{ width:80mm; max-width:100%; margin:0 auto 12px; display:flex; gap:8px; }
  .bar a, .bar button{
    flex:1; padding:9px 10px; border:1px solid var(--rule); border-radius:8px;
    background:#fff; font:600 12px "Segoe UI",Arial; color:var(--ink);
    cursor:pointer; text-align:center; text-decoration:none;
  }
  .bar .go{ background:#0B0B0B; color:#fff; border-color:#0B0B0B; }

  @media print{
    @page{ margin:6mm; }
    body{ background:#fff; padding:0; }
    .roll{ width:auto; box-shadow:none; padding:0; }
    .bar{ display:none; }
  }
</style>
</head>
<body>

<div class="bar">
  <a href="<?= url('admin/appointments.php') ?>">← Appointments</a>
  <button class="go" onclick="window.print()">Print / Save PDF</button>
</div>

<div class="roll">
  <div class="brand">
    <div class="name">Princess</div>
    <div class="sub">Beauty Salon</div>
    <div class="meta">
      <?= e(setting('address', '')) ?><br>
      <?= e(setting('phone', '')) ?><?php if (setting('email')): ?> · <?= e(setting('email', '')) ?><?php endif; ?>
    </div>
  </div>

  <h1><?= $paid ? 'Receipt' : 'Statement' ?></h1>
  <div class="ref"><?= e($a['ref']) ?></div>

  <table class="kv">
    <tr><td>Date of service</td><td><?= e(fmt_date_long($a['booked_on'])) ?></td></tr>
    <tr><td>Time</td><td><?= e(fmt_time($a['starts_at'])) ?> – <?= e(fmt_time($a['ends_at'])) ?></td></tr>
    <tr><td>Client</td><td><?= e($a['client_name']) ?></td></tr>
    <?php if ($a['client_phone']): ?>
      <tr><td>Phone</td><td><?= e($a['client_phone']) ?></td></tr>
    <?php endif; ?>
    <tr><td>Stylist</td><td><?= e($a['staff_name'] ?: 'The team') ?></td></tr>
    <tr><td>Issued</td><td><?= e(fmt_date_long(app_today())) ?></td></tr>
  </table>

  <table class="lines">
    <thead><tr><th>Service</th><th class="n">Amount</th></tr></thead>
    <tbody>
      <?php foreach ($a['items'] as $it): ?>
        <tr>
          <td><?= e($it['service_name']) ?>
            <?php if ((int) $it['qty'] > 1): ?>
              <br><span class="qty"><?= (int) $it['qty'] ?> × <?= e(money($it['unit_price'])) ?></span>
            <?php endif; ?>
          </td>
          <td class="n"><?= e(money($it['line_total'])) ?></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>

  <table class="totals">
    <tr><td>Services</td><td><?= e(money($a['services_total'])) ?></td></tr>
    <?php if ((float) $a['addons_total'] > 0): ?>
      <tr><td>Add-ons at the chair</td><td><?= e(money($a['addons_total'])) ?></td></tr>
    <?php endif; ?>
    <?php if ((float) $a['tip_total'] > 0): ?>
      <tr><td>Tip</td><td><?= e(money($a['tip_total'])) ?></td></tr>
    <?php endif; ?>
    <tr class="grand"><td>Total</td><td><?= e(money($grand)) ?></td></tr>
    <?php if ($deposit > 0): ?>
      <tr><td>Deposit already paid</td><td>− <?= e(money($deposit)) ?></td></tr>
      <tr class="<?= $balance > 0 ? 'bal' : '' ?>">
        <td><?= $balance > 0 ? 'Balance due' : 'Balance' ?></td><td><?= e(money($balance)) ?></td></tr>
    <?php endif; ?>
    <tr><td>Paid by</td><td><?= e($methods[$a['payment_method']] ?? $a['payment_method']) ?></td></tr>
  </table>

  <?php if ($paid): ?>
    <div class="stamp paid">Paid in full
      <?= $a['paid_at'] ? '· ' . e(fmt_day(substr((string) $a['paid_at'], 0, 10))) : '' ?></div>
  <?php else: ?>
    <div class="stamp due">Payment outstanding</div>
  <?php endif; ?>

  <div class="thanks">
    <b><?= e(setting('tagline', 'Look Good. Feel Good. Be You.')) ?></b><br>
    Thank you for sitting in our chair.<br>
    Keep <?= e($a['ref']) ?> to manage or rebook this appointment.
    <?php if (setting('instagram')): ?><br><?= e(setting('instagram', '')) ?><?php endif; ?>
  </div>
</div>

<?php if ($autoPrint): ?>
<script>window.addEventListener('load', function () { window.print(); });</script>
<?php endif; ?>
</body>
</html>
