<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/admin-layout.php';

$u = require_permission('dashboard');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require();
    db_run('UPDATE contact_messages SET is_read = 1 WHERE is_read = 0');
    audit('Marked contact messages read', 'message');
    redirect('admin/notifications.php?saved=1');
}

/* Notifications are DERIVED from the state of the business, not a stored feed.
   Nothing here can go stale or disagree with the pages it links to. */
$items = [];

if (can('appointments')) {
    $scope = can('revenue') ? [] : ['staff_id' => (int) $u['staff_id']];

    foreach (array_slice(repo_appointments($scope + ['status' => 'pending']), 0, 5) as $a) {
        $items[] = ['type' => 'info', 'icon' => 'bell',
            'title' => 'Booking awaiting confirmation',
            'text'  => $a['client_name'] . ' · ' . $a['service_names'] . ' · '
                     . fmt_day($a['booked_on']) . ' at ' . fmt_time($a['starts_at']),
            'href'  => 'appointments.php?status=pending'];
    }
    foreach (array_slice(repo_appointments($scope + ['status' => 'no-show']), 0, 3) as $a) {
        $items[] = ['type' => 'danger', 'icon' => 'alert', 'title' => 'No-show recorded',
            'text'  => $a['client_name'] . ' · ' . fmt_day($a['booked_on']),
            'href'  => 'appointments.php?status=no-show'];
    }
}

if (can('inventory')) {
    foreach (repo_low_stock() as $p) {
        $out = (int) $p['qty_on_hand'] === 0;
        $items[] = ['type' => $out ? 'danger' : 'warning', 'icon' => 'alert',
            'title' => ($out ? 'Out of stock: ' : 'Low stock: ') . $p['name'],
            'text'  => (int) $p['qty_on_hand'] . ' ' . $p['unit'] . ' left · reorder level '
                     . (int) $p['reorder_level'] . ' · ' . ($p['supplier'] ?: 'no supplier set'),
            'href'  => 'inventory.php'];
    }
}

if (can('revenue')) {
    $unpaid = repo_appointments(['unpaid' => true]);
    if ($unpaid) {
        $items[] = ['type' => 'warning', 'icon' => 'cash', 'title' => 'Unpaid appointments',
            'text'  => count($unpaid) . ' completed appointment' . (count($unpaid) === 1 ? '' : 's')
                     . ' worth ' . money(array_sum(array_map(fn($a) => (float) $a['grand_total'], $unpaid)))
                     . ' not yet settled',
            'href'  => 'revenue.php'];
    }
    $msgs = (int) db_val('SELECT COUNT(*) FROM contact_messages WHERE is_read = 0');
    if ($msgs) {
        $items[] = ['type' => 'info', 'icon' => 'mail', 'title' => 'Unread contact messages',
            'text'  => $msgs . ' message' . ($msgs === 1 ? '' : 's') . ' from the public site',
            'href'  => '#messages'];
    }
}

$messages = can('revenue')
    ? db_all('SELECT * FROM contact_messages ORDER BY is_read, created_at DESC LIMIT 15')
    : [];

admin_head(['title' => 'Notifications', 'subtitle' => 'Alerts &amp; activity', 'page' => 'notifications.php']);
?>
<div class="page-actions">
  <div><h2 class="mb-1" style="font-size:1.375rem">Notifications</h2>
    <p class="small muted mb-0"><?= count($items) ?> thing<?= count($items) === 1 ? '' : 's' ?>
      needing attention</p></div>
  <div class="spacer"></div>
  <?php if ($messages): ?>
    <form method="post"><?= csrf_field() ?>
      <button class="btn btn-ghost btn-sm">Mark messages read</button></form>
  <?php endif; ?>
</div>

<?php if (!$items): ?>
  <div class="card"><div class="empty">
    <span class="icon-badge mb-4" data-icon="check"></span>
    <h3>All clear</h3><p>Nothing needs your attention right now.</p></div></div>
<?php else: foreach ($items as $n): ?>
  <a href="<?= e($n['href']) ?>" class="alert alert-<?= e($n['type']) ?> mb-3">
    <span class="ico" data-icon="<?= e($n['icon']) ?>"></span>
    <span style="flex:1"><b><?= e($n['title']) ?></b><p><?= e($n['text']) ?></p></span>
    <span class="btn-icon" aria-hidden="true"><span data-icon="chevR"></span></span>
  </a>
<?php endforeach; endif; ?>

<?php if ($messages): ?>
<div class="card card-pad-0 mt-4" id="messages">
  <div class="card-head"><div><h4>Messages from the public site</h4>
    <span class="tiny muted">Sent through the contact form</span></div></div>
  <div class="table-wrap">
    <table class="table table-cards" style="min-width:760px">
      <thead><tr><th>When</th><th>From</th><th>Subject</th><th>Message</th><th class="num">Status</th></tr></thead>
      <tbody>
      <?php foreach ($messages as $m): ?>
        <tr><td><span class="tiny"><?= e($m['created_at']) ?></span></td>
          <td><?= e($m['name']) ?><br><span class="tiny muted"><?= e($m['phone']) ?></span></td>
          <td><?= e($m['subject'] ?: '—') ?></td>
          <td><?= e(mb_strimwidth($m['body'], 0, 90, '…')) ?></td>
          <td class="num"><?= (int) $m['is_read']
            ? '<span class="badge">Read</span>' : '<span class="badge badge-pink">New</span>' ?></td></tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>

<div class="card mt-4">
  <h4 class="mb-4">How you get notified</h4>
  <p class="small muted">These alerts are computed from live data every time this page loads,
    so they cannot disagree with the pages they link to. Push delivery is not connected.</p>
  <label class="flex items-center gap-4 mb-4">
    <span class="switch"><input type="checkbox" checked disabled><span></span></span>
    <span><b>In-app alerts</b><br><span class="small muted">The bell in the top bar</span></span></label>
  <label class="flex items-center gap-4 mb-0">
    <span class="switch"><input type="checkbox" disabled><span></span></span>
    <span><b>WhatsApp &amp; email</b><br>
      <span class="small muted">Needs a messaging provider — not connected</span></span></label>
</div>

<?php admin_foot(['page' => 'notifications.php']); ?>
