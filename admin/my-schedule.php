<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/admin-layout.php';

$u = require_permission('my-schedule');
$me = $u['staff_id'] ? (int) $u['staff_id'] : null;

if (!$me) {
    admin_head(['title' => 'My Schedule', 'subtitle' => 'Personal diary', 'page' => 'my-schedule.php']);
    echo '<div class="empty"><p>Your account is not linked to a stylist profile, so you have no
          diary of your own. Ask the owner to link it on the Users page.</p></div>';
    admin_foot(['page' => 'my-schedule.php']);
    exit;
}

$today = app_today();
$day   = (string) ($_GET['day'] ?? $today);
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $day)) $day = $today;

$mine     = repo_appointments(['staff_id' => $me]);
$dayList  = array_values(array_filter($mine, fn($a) => $a['booked_on'] === $day));
usort($dayList, fn($a, $b) => strcmp($a['starts_at'], $b['starts_at']));

$todayList = array_values(array_filter($mine, fn($a) => $a['booked_on'] === $today));
$done = count(array_filter($todayList, fn($a) => $a['status'] === 'completed'));
$togo = count(array_filter($todayList, fn($a) => in_array($a['status'], ['confirmed', 'pending'], true)));
$upcoming = count(array_filter($mine, fn($a) => $a['booked_on'] > $today &&
    !in_array($a['status'], ['cancelled', 'no-show'], true)));
$minsToday = array_sum(array_map(fn($a) => (int) $a['duration_minutes'], $todayList));

$next = null;
foreach ($todayList as $a) {
    if (in_array($a['status'], ['confirmed', 'pending'], true) &&
        (!$next || $a['starts_at'] < $next['starts_at'])) $next = $a;
}

/* day strip: two days back, twelve forward, with a count on each */
$strip = [];
for ($i = -2; $i < 12; $i++) {
    $d = date('Y-m-d', strtotime($today . " $i day"));
    $n = count(array_filter($mine, fn($a) => $a['booked_on'] === $d &&
        !in_array($a['status'], ['cancelled', 'no-show'], true)));
    $strip[] = ['date' => $d, 'n' => $n];
}

$monthStart = date('Y-m-01', strtotime($day));
$byDay = [];
foreach ($mine as $a) $byDay[$a['booked_on']][] = $a;

$rota = db_all('SELECT * FROM staff_hours WHERE staff_id = ? ORDER BY weekday', [$me]);
$dayNames = ['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'];

admin_head(['title' => 'My Schedule', 'subtitle' => 'Personal diary', 'page' => 'my-schedule.php']);
?>

<div class="page-actions">
  <div><h2 class="mb-1" style="font-size:1.375rem">My Schedule</h2>
    <p class="small muted mb-0">Your own diary — nobody else's chair</p></div>
  <div class="spacer"></div>
  <a href="my-clients.php" class="btn btn-dark btn-sm">My clients</a>
</div>

<div class="stat-grid">
  <div class="stat accent-pink"><div class="top"><div><div class="lab">Today</div>
    <div class="val"><?= count($todayList) ?> <small>appts</small></div>
    <div class="sub"><?= $done ?> done · <?= $togo ?> to go</div></div>
    <div class="ico" data-icon="calendar"></div></div></div>
  <div class="stat accent-gold"><div class="top"><div><div class="lab">Hours Booked Today</div>
    <div class="val"><?= number_format($minsToday / 60, 1) ?> <small>hrs</small></div>
    <div class="sub">Chair time committed</div></div>
    <div class="ico" data-icon="clock"></div></div></div>
  <div class="stat accent-black"><div class="top"><div><div class="lab">Upcoming</div>
    <div class="val"><?= $upcoming ?></div><div class="sub">Booked beyond today</div></div>
    <div class="ico" data-icon="chart"></div></div></div>
  <div class="stat accent-green"><div class="top"><div><div class="lab">Next Client</div>
    <div class="val"><?= $next ? e(fmt_time($next['starts_at'])) : '—' ?></div>
    <div class="sub"><?= $next ? e($next['client_name'] . ' · ' . $next['service_names'])
                               : 'Nothing left today' ?></div></div>
    <div class="ico" data-icon="user"></div></div></div>
</div>

<div class="card mt-4">
  <div class="date-strip">
    <?php foreach ($strip as $s): ?>
      <a class="date-pill<?= $s['date'] === $day ? ' selected' : '' ?>" href="?day=<?= e($s['date']) ?>">
        <span class="dow"><?= $s['date'] === $today ? 'Today' : e(date('D', strtotime($s['date']))) ?></span>
        <span class="d"><?= (int) date('j', strtotime($s['date'])) ?></span>
        <span class="mo"><?= $s['n'] ? $s['n'] . ' appt' : '—' ?></span></a>
    <?php endforeach; ?>
  </div>
</div>

<div class="dash-grid dash-2-1 mt-4">
  <div class="card card-pad-0">
    <div class="card-head"><div><h4><?= e(fmt_date_long($day)) ?><?= $day === $today ? ' · Today' : '' ?></h4>
      <span class="tiny muted">Tap a client to call or message them</span></div></div>
    <div class="card-body">
      <?php if (!$dayList): ?>
        <div class="empty"><p>Nothing booked for this day. Enjoy the quiet.</p></div>
      <?php else: ?>
        <div class="timeline">
        <?php foreach ($dayList as $a):
          $cls = $a['status'] === 'completed' ? 'done'
               : (in_array($a['status'], ['confirmed', 'pending'], true) ? 'next' : '');
          $digits = preg_replace('/\D/', '', (string) $a['client_phone']); ?>
          <div class="tl-item <?= $cls ?>">
            <div class="flex items-center justify-between gap-3 wrapflex">
              <span class="tl-time"><?= e(fmt_time($a['starts_at'])) ?> – <?= e(fmt_time($a['ends_at'])) ?></span>
              <?= status_badge($a['status']) ?>
            </div>
            <div class="card card-flat mt-2">
              <div class="flex items-center gap-3">
                <span class="avatar avatar-pink"><?= e(initials($a['client_name'])) ?></span>
                <div style="flex:1;min-width:0">
                  <b style="color:var(--black)"><?= e($a['client_name']) ?></b>
                  <div class="tiny muted"><?= e($a['service_names']) ?> ·
                    <?= e(duration((int) $a['duration_minutes'])) ?></div></div>
                <span style="font-family:var(--font-serif);font-weight:700;color:var(--black)">
                  <?= e(money($a['grand_total'])) ?></span>
              </div>
              <?php if ($a['note']): ?>
                <p class="tiny muted mt-3 mb-0" style="padding-top:10px;border-top:1px dashed var(--n-200)">
                  <?= e($a['note']) ?></p>
              <?php endif; ?>
              <div class="flex gap-2 mt-3 wrapflex">
                <a class="btn btn-ghost btn-sm" href="tel:<?= e($digits) ?>">Call</a>
                <a class="btn btn-ghost btn-sm" target="_blank" rel="noopener"
                   href="https://wa.me/<?= e($digits) ?>">WhatsApp</a>
                <a class="btn btn-outline btn-sm" href="appointments.php?q=<?= e($a['ref']) ?>">Open</a>
              </div>
            </div>
          </div>
        <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
  </div>

  <div class="grid" style="gap:16px;align-content:start">
    <div class="card card-pad-0">
      <div class="card-head"><h4><?= e(date('F Y', strtotime($monthStart))) ?></h4></div>
      <div class="card-body">
        <?php
        $first = (int) date('N', strtotime($monthStart)) - 1;
        $days  = (int) date('t', strtotime($monthStart));
        echo '<div class="cal">';
        foreach (['M','T','W','T','F','S','S'] as $dn) echo '<div class="dow">' . $dn . '</div>';
        for ($i = 0; $i < $first; $i++) echo '<div class="day out"></div>';
        for ($n = 1; $n <= $days; $n++) {
            $iso = date('Y-m-', strtotime($monthStart)) . str_pad((string) $n, 2, '0', STR_PAD_LEFT);
            $list = $byDay[$iso] ?? [];
            $cls = 'day' . ($iso === $today ? ' today' : '') . ($list ? ' has-appt' : '');
            echo '<div class="' . $cls . '"><span class="n">' . $n . '</span>';
            if ($list) echo '<span class="ev">' . count($list) . ' booked</span>';
            echo '</div>';
        }
        echo '</div>';
        ?>
      </div>
    </div>

    <div class="card">
      <h4 class="mb-4">My working hours</h4>
      <?php if (!$rota): ?>
        <p class="small muted mb-0">No rota set. Ask the owner to add your hours on the Staff page —
          without them you cannot be booked at all.</p>
      <?php else: foreach ($rota as $r): ?>
        <div class="kv"><span><?= e($dayNames[(int) $r['weekday']]) ?></span>
          <b><?= e(fmt_time($r['starts_at'])) ?> – <?= e(fmt_time($r['ends_at'])) ?></b></div>
      <?php endforeach; endif; ?>
      <p class="data-note mt-4 mb-0">Only the owner can change your rota. Days not listed
        are days you cannot be booked.</p>
    </div>
  </div>
</div>

<?php admin_foot(['page' => 'my-schedule.php']); ?>
