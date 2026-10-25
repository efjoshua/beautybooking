<?php
/**
 * Where Orange Money sends the client back to after their hosted page.
 *
 * The client's browser returning here is NOT proof of payment — anyone can
 * type this URL. The only thing trusted is what Orange says when we ask them
 * directly, which is what payment_refresh() does.
 */
declare(strict_types=1);
require_once __DIR__ . '/../includes/public-layout.php';
require_once __DIR__ . '/../includes/payments.php';

$ref = (string) ($_GET['ref'] ?? '');
$cancelled = !empty($_GET['cancelled']);

$payment = preg_match('/^PAY-\d+$/', $ref)
    ? db_one('SELECT * FROM payments WHERE ref = ?', [$ref])
    : null;

$status = 'unknown';
if ($payment) {
    if ($cancelled && $payment['status'] === 'pending') {
        payment_settle((int) $payment['id'], 'cancelled', 'Cancelled on the Orange Money page.');
        $status = 'cancelled';
    } else {
        $res = payment_refresh($ref);
        $status = $res['status'] ?? 'unknown';
    }
}

$booking = $payment && $payment['appointment_id']
    ? db_one('SELECT ref, grand_total, deposit_paid FROM appointments WHERE id = ?',
             [$payment['appointment_id']])
    : null;

public_head([
    'title' => 'Payment — ' . APP_NAME,
    'description' => 'Your payment result.',
    'page' => 'payment-return.php',
]);
?>

<section class="section">
  <div class="wrap" style="max-width:680px">
    <?php if ($status === 'succeeded'): ?>
      <div class="alert alert-success mb-6"><span class="ico" data-icon="check"></span>
        <span><b>Payment received</b>
          <p>Thank you. Your deposit of <?= e(money($payment['amount'])) ?> is recorded
             and your slot is held.</p></span></div>
    <?php elseif ($status === 'cancelled'): ?>
      <div class="alert alert-warning mb-6"><span class="ico" data-icon="alert"></span>
        <span><b>Payment cancelled</b>
          <p>Nothing was charged. <b>Your booking is still held</b> — you can pay at the
             salon instead.</p></span></div>
    <?php elseif ($status === 'pending'): ?>
      <div class="alert alert-info mb-6"><span class="ico" data-icon="clock"></span>
        <span><b>Payment still processing</b>
          <p>Orange Money has not confirmed yet. Your booking is held either way — refresh
             this page in a moment, or simply settle at the salon.</p></span></div>
    <?php elseif ($payment): ?>
      <div class="alert alert-danger mb-6"><span class="ico" data-icon="alert"></span>
        <span><b>The payment did not go through</b>
          <p><?= e($payment['failure_reason'] ?: 'No money was taken.') ?>
             <b>Your booking is still held</b> — try again from Manage Booking, or pay at
             the salon.</p></span></div>
    <?php else: ?>
      <div class="alert alert-danger mb-6"><span class="ico" data-icon="alert"></span>
        <span><b>We could not find that payment</b>
          <p>If money left your account, contact the salon with the reference and we will
             sort it out.</p></span></div>
    <?php endif; ?>

    <?php if ($booking): ?>
      <div class="card">
        <h3 class="mb-4">Booking <?= e($booking['ref']) ?></h3>
        <div class="kv"><span>Total</span><b><?= e(money($booking['grand_total'])) ?></b></div>
        <div class="kv"><span>Paid so far</span><b><?= e(money($booking['deposit_paid'])) ?></b></div>
        <div class="kv"><span>Due at the salon</span>
          <b><?= e(money(max(0, (float) $booking['grand_total'] - (float) $booking['deposit_paid']))) ?></b></div>
      </div>
      <div class="btn-row mt-6">
        <a class="btn btn-primary" href="<?= e(url('public/confirmation.php')) ?>?ref=<?= e($booking['ref']) ?>">
          View my booking</a>
        <a class="btn btn-ghost" href="<?= e(url('public/index.php')) ?>">Back to the salon</a>
      </div>
    <?php endif; ?>
  </div>
</section>

<?php public_foot(['page' => 'payment-return.php']); ?>
