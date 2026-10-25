<?php
/**
 * Princess Beauty Salon — admin chrome (server-rendered)
 *
 * The prototype built this in admin-layout.js from a client-side role. The
 * menu is now filtered from the database against the signed-in user's actual
 * permissions, and every page additionally calls require_permission() — the
 * menu hides links, the guard is what enforces them.
 */

declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/repo.php';

/** Menu definition: [href, label, icon, permission, badge-callback]. */
function admin_menu(): array
{
    return [
        ['group', 'Overview'],
        ['index.php',         'Dashboard',         'chart',    'dashboard'],
        ['group', 'Operations'],
        ['appointments.php',  'Appointments',      'calendar', 'appointments'],
        ['clients.php',       'Clients',           'users',    'clients'],
        ['services.php',      'Services',          'tag',      'services'],
        ['staff.php',         'Staff',             'user',     'staff'],
        ['group', 'Business'],
        ['revenue.php',       'Revenue & Reports', 'cash',     'revenue'],
        ['expenses.php',      'Expenses',          'receipt',  'expenses'],
        ['inventory.php',     'Inventory / Store', 'box',      'inventory'],
        ['group', 'My Work'],
        ['my-schedule.php',   'My Schedule',       'clock',    'my-schedule'],
        ['my-clients.php',    'My Clients',        'users',    'my-clients'],
        ['my-performance.php','My Performance',    'chart',    'my-performance'],
        ['group', 'Administration'],
        ['users.php',         'Users & Roles',     'users',    'users'],
        ['settings.php',      'Settings',          'settings', 'settings'],
        ['notifications.php', 'Notifications',     'bell',     'dashboard'],
        ['profile.php',       'My Profile',        'user',     'dashboard'],
    ];
}

/** Mobile tab bar — the four or five things each role reaches most. */
function admin_tabs(): array
{
    $all = [
        ['index.php',        'Home',     'home',     'dashboard'],
        ['appointments.php', 'Diary',    'calendar', 'appointments'],
        ['my-schedule.php',  'Schedule', 'calendar', 'my-schedule'],
        ['revenue.php',      'Revenue',  'cash',     'revenue'],
        ['inventory.php',    'Store',    'box',      'inventory'],
        ['clients.php',      'Clients',  'users',    'clients'],
        ['my-clients.php',   'Clients',  'users',    'my-clients'],
        ['my-performance.php','Stats',   'chart',    'my-performance'],
        ['profile.php',      'Profile',  'user',     'dashboard'],
    ];
    $out = [];
    foreach ($all as $t) {
        if (can($t[3])) $out[] = $t;
        if (count($out) === 5) break;
    }
    return $out;
}

/**
 * Open an admin page: <head>, sidebar, topbar, and the opening content div.
 * $opts: title, subtitle, page (filename for the active state).
 */
function admin_head(array $opts): void
{
    $u        = current_user();
    $page     = $opts['page'] ?? basename($_SERVER['SCRIPT_NAME']);
    $title    = $opts['title'] ?? 'Dashboard';
    $subtitle = $opts['subtitle'] ?? '';
    $unpaid   = (int) db_val('SELECT COUNT(*) FROM appointments
                               WHERE status = "completed" AND payment_method = "unpaid"');
    $lowStock = count(repo_low_stock());
    $pending  = (int) db_val('SELECT COUNT(*) FROM appointments WHERE status = "pending"');
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= e($title) ?> — <?= e(APP_NAME) ?> Admin</title>
<meta name="robots" content="noindex">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Great+Vibes&family=Montserrat:wght@400;500;600;700;800&family=Playfair+Display:ital,wght@0,600;0,700;1,600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= url('assets/css/base.css') ?>">
<link rel="stylesheet" href="<?= url('assets/css/components.css') ?>">
<link rel="stylesheet" href="<?= url('assets/css/admin.css') ?>">
<link rel="icon" type="image/svg+xml" href="<?= url('assets/img/favicon.svg') ?>">
<script>window.PBS_CSRF = <?= json_encode(csrf_token()) ?>;
        window.PBS_CURRENCY = <?= json_encode(CURRENCY) ?>;</script>
</head>
<body class="admin has-admin-tabs" data-page="<?= e($page) ?>">

<div class="admin-shell">

  <aside class="sidebar" id="sidebar">
    <div class="sidebar-brand">
      <span class="mark">P</span>
      <span class="txt"><b>PRINCESS</b><span>Beauty Salon</span></span>
    </div>
    <div class="sidebar-scroll">
      <?php
      $buffer = '';
      foreach (admin_menu() as $item) {
          if ($item[0] === 'group') {
              /* hold the heading back until an item under it is permitted */
              $buffer = '<div class="side-group">' . e($item[1]) . '</div>';
              continue;
          }
          [$href, $label, $icon, $perm] = $item;
          if (!can($perm)) continue;
          if ($buffer) { echo $buffer; $buffer = ''; }

          $count = null; $gold = false;
          if ($href === 'appointments.php' && $pending)  { $count = $pending; }
          if ($href === 'inventory.php'    && $lowStock) { $count = $lowStock; $gold = true; }
          ?>
          <a href="<?= e($href) ?>" class="side-link<?= $page === $href ? ' active' : '' ?>">
            <span data-icon="<?= e($icon) ?>"></span><span><?= e($label) ?></span>
            <?php if ($count): ?>
              <span class="count<?= $gold ? ' gold' : '' ?>"><?= (int) $count ?></span>
            <?php endif; ?>
          </a>
      <?php } ?>

      <div class="side-group">Shortcuts</div>
      <a href="<?= url('public/index.php') ?>" class="side-link" target="_blank" rel="noopener">
        <span data-icon="home"></span><span>View public site</span></a>
      <a href="<?= url('public/book.php') ?>" class="side-link" target="_blank" rel="noopener">
        <span data-icon="plus"></span><span>New booking</span></a>
    </div>

    <div class="sidebar-foot">
      <a href="profile.php" class="side-user">
        <span class="avatar<?= is_owner() ? '' : ' avatar-pink' ?>"><?= e(initials($u['name'])) ?></span>
        <span class="txt"><b><?= e($u['name']) ?></b><span><?= e($u['role_name']) ?></span></span>
        <span data-icon="settings"></span>
      </a>
      <form method="post" action="<?= url('admin/logout.php') ?>" style="margin-top:8px">
        <?= csrf_field() ?>
        <button type="submit" class="side-link full" style="color:rgba(255,255,255,.5);text-align:left">
          <span data-icon="logout"></span><span>Sign out</span>
        </button>
      </form>
    </div>
  </aside>
  <div class="sidebar-scrim" id="sidebarScrim"></div>

  <div class="admin-main">
    <header class="topbar">
      <button class="sidebar-toggle" id="sidebarToggle" aria-label="Toggle menu"><i></i><i></i><i></i></button>
      <h1 class="page-title"><?= e($title) ?><small><?= e($subtitle) ?></small></h1>
      <div class="topbar-search search">
        <svg viewBox="0 0 24 24" width="17" height="17"><circle cx="11" cy="11" r="7"/><path d="M20 20l-3.6-3.6"/></svg>
        <input class="input" type="search" placeholder="Search clients, bookings, products…" id="globalSearch">
      </div>
      <div class="topbar-right">
        <a href="notifications.php" class="btn-icon bell" aria-label="Notifications">
          <span data-icon="bell"></span>
          <?php if ($unpaid || $lowStock || $pending): ?><span class="dot"></span><?php endif; ?>
        </a>
        <a href="<?= url('public/book.php') ?>" class="btn btn-primary btn-sm btn-newbooking" target="_blank" rel="noopener">
          <span data-icon="plus"></span><span class="lbl">New Booking</span>
        </a>
      </div>
    </header>

    <div class="admin-content">
<?php
}

/** Close an admin page: content div, mobile tabs, scripts. */
function admin_foot(array $opts = []): void
{
    $page = $opts['page'] ?? basename($_SERVER['SCRIPT_NAME']);
    ?>
    </div><!-- /.admin-content -->

    <nav class="admin-tabs" aria-label="Quick access"><ul>
      <?php foreach (admin_tabs() as [$href, $label, $icon, $perm]): ?>
        <li><a href="<?= e($href) ?>"<?= $page === $href ? ' class="active"' : '' ?>>
          <span data-icon="<?= e($icon) ?>"></span><span><?= e($label) ?></span></a></li>
      <?php endforeach; ?>
    </ul></nav>
  </div><!-- /.admin-main -->
</div><!-- /.admin-shell -->

<script src="<?= url('assets/js/ui.js') ?>"></script>
<script src="<?= url('assets/js/charts.js') ?>"></script>
<script src="<?= url('assets/js/admin-ui.js') ?>"></script>
<?php foreach ($opts['scripts'] ?? [] as $s): ?>
<script src="<?= e($s) ?>"></script>
<?php endforeach; ?>
<?= $opts['inline'] ?? '' ?>
</body>
</html>
<?php
}
