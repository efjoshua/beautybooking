<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/admin-layout.php';

$me = require_permission('users');

$notice = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require();
    $act = (string) ($_POST['action'] ?? '');
    $ref = trim((string) ($_POST['ref'] ?? ''));
    $target = $ref !== '' ? db_one('SELECT * FROM users WHERE ref = ?', [$ref]) : null;

    /* You cannot lock yourself out of your own salon. */
    if ($target && (int) $target['id'] === (int) $me['id'] &&
        in_array($act, ['suspend', 'delete'], true)) {
        $notice = 'You cannot suspend or remove your own account.';
    } elseif ($act === 'suspend' && $target) {
        $new = $target['status'] === 'suspended' ? 'active' : 'suspended';
        db_update('users', ['status' => $new], ['id' => $target['id']]);
        audit("User $ref set to $new", 'user', $ref);
        redirect('admin/users.php?saved=1');
    } elseif ($act === 'reset' && $target) {
        /* Temporary password, shown once. Real deployments email a link. */
        $temp = 'pbs' . random_int(100000, 999999);
        db_update('users', [
            'password_hash' => password_hash($temp, PASSWORD_BCRYPT, ['cost' => BCRYPT_COST]),
            'status' => 'active',
        ], ['id' => $target['id']]);
        audit("Password reset for $ref", 'user', $ref);
        redirect('admin/users.php?temp=' . urlencode($temp) . '&for=' . urlencode($target['name']));
    } elseif ($act === 'save') {
        $name  = trim((string) ($_POST['name'] ?? ''));
        $email = trim(mb_strtolower((string) ($_POST['email'] ?? '')));
        $role  = (int) ($_POST['role_id'] ?? 0);

        if ($name === '')                                    $notice = 'Enter a name.';
        elseif (!filter_var($email, FILTER_VALIDATE_EMAIL))  $notice = 'Enter a valid email address.';
        elseif (!$role)                                      $notice = 'Choose a role.';
        else {
            $clash = db_val('SELECT id FROM users WHERE email = ?' .
                ($target ? ' AND id <> ?' : ''), $target ? [$email, $target['id']] : [$email]);
            if ($clash) $notice = 'Another account already uses that email.';
            else {
                $data = [
                    'name' => $name, 'email' => $email, 'role_id' => $role,
                    'staff_id' => ($_POST['staff_id'] ?? '') !== '' ? (int) $_POST['staff_id'] : null,
                    'two_factor' => !empty($_POST['two_factor']) ? 1 : 0,
                ];
                if ($target) {
                    /* Never let the last owner be demoted. */
                    $owners = (int) db_val(
                        'SELECT COUNT(*) FROM users u JOIN roles r ON r.id = u.role_id
                          WHERE r.slug = "owner" AND u.status = "active" AND u.deleted_at IS NULL');
                    $isOwnerRole = db_val('SELECT slug FROM roles WHERE id = ?', [$role]) === 'owner';
                    if ($owners <= 1 && !$isOwnerRole &&
                        db_val('SELECT slug FROM roles WHERE id = ?', [$target['role_id']]) === 'owner') {
                        $notice = 'This is the only owner account — change someone else to Owner first.';
                    } else {
                        db_update('users', $data + ['status' => (string) ($_POST['status'] ?? $target['status'])],
                                  ['id' => $target['id']]);
                        audit("User $ref updated", 'user', $ref);
                        redirect('admin/users.php?saved=1');
                    }
                } else {
                    $data['ref'] = next_ref('users', 'U-', 2);
                    $data['status'] = 'invited';       // no password until they accept
                    db_insert('users', $data);
                    audit('User ' . $data['ref'] . ' invited', 'user', $data['ref']);
                    redirect('admin/users.php?saved=1');
                }
            }
        }
    }
}

$roles = db_all('SELECT * FROM roles ORDER BY FIELD(slug,"owner","manager","receptionist","staff")');
$perms = db_all('SELECT * FROM permissions ORDER BY FIELD(area,"overview","operations","business","administration","personal"), id');
$rolePerms = [];
foreach (db_all('SELECT rp.role_id, p.slug FROM role_permissions rp
                   JOIN permissions p ON p.id = rp.permission_id') as $r) {
    $rolePerms[(int) $r['role_id']][] = $r['slug'];
}

$roleF   = (string) ($_GET['role'] ?? 'all');
$statusF = (string) ($_GET['status'] ?? 'all');
$q       = trim((string) ($_GET['q'] ?? ''));

$sql = 'SELECT u.*, r.slug role_slug, r.name role_name, s.job_title, s.ref staff_ref
          FROM users u JOIN roles r ON r.id = u.role_id
     LEFT JOIN staff s ON s.id = u.staff_id
         WHERE u.deleted_at IS NULL';
$p = [];
if ($roleF !== 'all')   { $sql .= ' AND r.slug = ?'; $p[] = $roleF; }
if ($statusF !== 'all') { $sql .= ' AND u.status = ?'; $p[] = $statusF; }
if ($q !== '')          { $sql .= ' AND (u.name LIKE ? OR u.email LIKE ?)'; $p[] = "%$q%"; $p[] = "%$q%"; }
$users = db_all($sql . ' ORDER BY FIELD(r.slug,"owner","manager","receptionist","staff"), u.name', $p);

$activity = db_all(
    'SELECT a.*, u.name user_name FROM audit_log a
  LEFT JOIN users u ON u.id = a.user_id
      ORDER BY a.created_at DESC, a.id DESC LIMIT 25');

$counts = db_one(
    'SELECT SUM(status="active") act, SUM(status="invited") inv, SUM(status="suspended") sus,
            SUM(two_factor=1) tfa, COUNT(*) total
       FROM users WHERE deleted_at IS NULL');

admin_head(['title' => 'Users &amp; Roles', 'subtitle' => 'Accounts &amp; permissions', 'page' => 'users.php']);
?>

<div class="page-actions">
  <div><h2 class="mb-1" style="font-size:1.375rem">Users &amp; Roles</h2>
    <p class="small muted mb-0"><?= count($users) ?> account<?= count($users) === 1 ? '' : 's' ?></p></div>
  <div class="spacer"></div>
  <button class="btn btn-primary btn-sm" id="addUser">Add user</button>
</div>

<?php if ($notice): ?>
  <div class="alert alert-danger mb-4"><span class="ico" data-icon="alert"></span>
    <span><b>Could not save</b><p><?= e($notice) ?></p></span></div>
<?php endif; ?>
<?php if (!empty($_GET['temp'])): ?>
  <div class="alert alert-warning mb-4"><span class="ico" data-icon="alert"></span>
    <span><b>Temporary password for <?= e((string) ($_GET['for'] ?? '')) ?></b>
      <p>Give them <code style="font-weight:700;letter-spacing:.1em"><?= e((string) $_GET['temp']) ?></code>
         and ask them to change it. This is shown once and is not stored anywhere in readable form.</p></span></div>
<?php endif; ?>

<div class="stat-grid">
  <div class="stat accent-black"><div class="top"><div><div class="lab">Total Accounts</div>
    <div class="val"><?= (int) $counts['total'] ?></div>
    <div class="sub"><?= (int) $counts['act'] ?> active</div></div>
    <div class="ico" data-icon="users"></div></div></div>
  <div class="stat accent-gold"><div class="top"><div><div class="lab">Pending Invites</div>
    <div class="val"><?= (int) $counts['inv'] ?></div>
    <div class="sub">Invitation sent, not yet accepted</div></div>
    <div class="ico" data-icon="mail"></div></div></div>
  <div class="stat accent-pink"><div class="top"><div><div class="lab">Suspended</div>
    <div class="val"><?= (int) $counts['sus'] ?></div>
    <div class="sub">Blocked from signing in</div></div>
    <div class="ico" data-icon="alert"></div></div></div>
  <div class="stat accent-green"><div class="top"><div><div class="lab">Two-Factor Enabled</div>
    <div class="val"><?= (int) $counts['tfa'] ?> <small>/ <?= (int) $counts['total'] ?></small></div>
    <div class="sub">Recommended for every admin account</div></div>
    <div class="ico" data-icon="check"></div></div></div>
</div>

<div class="alert alert-info mt-4">
  <span class="ico" data-icon="alert"></span>
  <span><b>Users are logins; staff are people</b>
    <p>A receptionist has an account but no chair in the diary. A stylist can exist on the
       <a href="staff.php" class="t-pink">Staff page</a> before they ever sign in. Link the two
       here so a stylist sees their own schedule and commission.</p></span>
</div>

<form class="filter-bar mt-4" method="get">
  <div class="search" style="flex:1;min-width:200px">
    <svg viewBox="0 0 24 24" width="17" height="17"><circle cx="11" cy="11" r="7"/><path d="M20 20l-3.6-3.6"/></svg>
    <input class="input" name="q" type="search" value="<?= e($q) ?>" placeholder="Search by name or email…">
  </div>
  <select class="select" name="role" onchange="this.form.submit()">
    <option value="all">All roles</option>
    <?php foreach ($roles as $r): ?>
      <option value="<?= e($r['slug']) ?>"<?= $roleF === $r['slug'] ? ' selected' : '' ?>><?= e($r['name']) ?></option>
    <?php endforeach; ?>
  </select>
  <select class="select" name="status" onchange="this.form.submit()">
    <?php foreach (['all' => 'All statuses', 'active' => 'Active', 'invited' => 'Invited', 'suspended' => 'Suspended'] as $k => $l): ?>
      <option value="<?= $k ?>"<?= $statusF === $k ? ' selected' : '' ?>><?= e($l) ?></option>
    <?php endforeach; ?>
  </select>
  <button class="btn btn-ghost btn-sm" type="submit">Search</button>
</form>

<div class="card card-pad-0">
  <div class="table-wrap">
    <table class="table table-cards" style="min-width:940px">
      <thead><tr><th>User</th><th>Role</th><th>Stylist profile</th><th>Status</th>
        <th>2FA</th><th>Last sign-in</th><th class="num">Actions</th></tr></thead>
      <tbody>
      <?php foreach ($users as $usr):
        $badge = ['active' => 'badge-success', 'invited' => 'badge-warning', 'suspended' => 'badge-danger'][$usr['status']];
        $rc = ['owner' => '#C9A227', 'manager' => '#2F6FED', 'receptionist' => '#7B54D3', 'staff' => '#E91E8C'][$usr['role_slug']] ?? '#77777F'; ?>
        <tr>
          <td><span class="cell-user"><span class="avatar avatar-sm<?= $usr['role_slug'] === 'owner' ? '' : ' avatar-pink' ?>">
            <?= e(initials($usr['name'])) ?></span>
            <span><span class="name"><?= e($usr['name']) ?>
              <?= (int) $usr['id'] === (int) $me['id'] ? '<span class="tiny muted">(you)</span>' : '' ?></span>
              <span class="tiny muted"><?= e($usr['email']) ?></span></span></span></td>
          <td><span class="badge badge-plain" style="background:<?= $rc ?>1a;color:<?= $rc ?>"><?= e($usr['role_name']) ?></span></td>
          <td><?= $usr['job_title'] ? e($usr['job_title'])
                : '<span class="muted tiny">No chair — office account</span>' ?></td>
          <td><span class="badge <?= $badge ?>"><?= e(ucfirst($usr['status'])) ?></span></td>
          <td><?= (int) $usr['two_factor'] ? '<span class="perm-yes">On</span>' : '<span class="perm-no">Off</span>' ?></td>
          <td><span class="tiny"><?= e($usr['last_login_at'] ?: 'Never') ?></span></td>
          <td class="num">
            <button class="btn-icon" data-edit='<?= e(json_encode([
              'ref' => $usr['ref'], 'name' => $usr['name'], 'email' => $usr['email'],
              'role_id' => $usr['role_id'], 'staff_id' => $usr['staff_id'],
              'status' => $usr['status'], 'two_factor' => $usr['two_factor'],
            ])) ?>' aria-label="Edit"><span data-icon="edit"></span></button>
            <form method="post" style="display:inline">
              <?= csrf_field() ?><input type="hidden" name="action" value="reset">
              <input type="hidden" name="ref" value="<?= e($usr['ref']) ?>">
              <button class="btn-icon" aria-label="Reset password"
                onclick="return confirm('Generate a new temporary password for <?= e($usr['name']) ?>?')">
                <span data-icon="logout"></span></button></form>
            <?php if ((int) $usr['id'] !== (int) $me['id']): ?>
            <form method="post" style="display:inline">
              <?= csrf_field() ?><input type="hidden" name="action" value="suspend">
              <input type="hidden" name="ref" value="<?= e($usr['ref']) ?>">
              <button class="btn-icon" aria-label="Suspend"
                onclick="return confirm('<?= $usr['status'] === 'suspended' ? 'Reactivate' : 'Suspend' ?> this account?')">
                <span data-icon="<?= $usr['status'] === 'suspended' ? 'check' : 'x' ?>"></span></button></form>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <div class="card-foot">
    <p class="data-note mb-0">Suspending blocks sign-in immediately but keeps every booking,
      sale and commission record attached to that person.</p>
  </div>
</div>

<div class="card card-pad-0 mt-4">
  <div class="card-head"><div><h4>What each role can reach</h4>
    <span class="tiny muted">The menu and the URL guard both read from this table</span></div></div>
  <div class="card-body">
    <div class="perm-grid">
      <table class="perm-table">
        <thead><tr><th>Area</th>
          <?php foreach ($roles as $r): ?><th><?= e(explode(' / ', $r['name'])[0]) ?></th><?php endforeach; ?>
        </tr></thead>
        <tbody>
        <?php foreach ($perms as $pm): ?>
          <tr><td><?= e($pm['name']) ?></td>
            <?php foreach ($roles as $r): ?>
              <td><?= in_array($pm['slug'], $rolePerms[(int) $r['id']] ?? [], true)
                  ? '<span class="perm-yes">&#10003;</span>' : '<span class="perm-no">&mdash;</span>' ?></td>
            <?php endforeach; ?></tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<div class="card card-pad-0 mt-4">
  <div class="card-head"><div><h4>Activity log</h4>
    <span class="tiny muted">Who did what, for accountability across the team</span></div></div>
  <div class="table-wrap">
    <table class="table table-cards" style="min-width:760px">
      <thead><tr><th>When</th><th>User</th><th>Type</th><th>Action</th></tr></thead>
      <tbody>
      <?php foreach ($activity as $a): ?>
        <tr><td><span class="tiny"><?= e($a['created_at']) ?></span></td>
          <td><?= e($a['user_name'] ?: 'System') ?></td>
          <td><span class="badge badge-plain"><?= e($a['entity_type'] ?: '—') ?></span></td>
          <td><?= e($a['action']) ?></td></tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<div class="modal modal-lg" id="userModal">
  <div class="modal-backdrop"></div>
  <div class="modal-box">
    <form method="post">
      <?= csrf_field() ?><input type="hidden" name="action" value="save">
      <div class="modal-head"><h3 id="umTitle">Add a user</h3>
        <button type="button" class="modal-close" data-modal-close>&times;</button></div>
      <div class="modal-body">
        <input type="hidden" name="ref" id="umRef">
        <div class="field-row">
          <div class="field"><label class="label">Full name <span class="req">*</span></label>
            <input class="input" name="name" id="umName" required></div>
          <div class="field"><label class="label">Email <span class="req">*</span></label>
            <input class="input" type="email" name="email" id="umEmail" required></div>
        </div>
        <div class="field"><label class="label">Role <span class="req">*</span></label>
          <div class="grid" style="gap:8px">
            <?php foreach ($roles as $r): ?>
              <label class="pick" style="cursor:pointer">
                <input type="radio" name="role_id" value="<?= (int) $r['id'] ?>" data-role="<?= (int) $r['id'] ?>"
                  <?= $r['slug'] === 'staff' ? 'checked' : '' ?>
                  style="accent-color:var(--pink);width:18px;height:18px;flex:none">
                <span class="info"><b><?= e($r['name']) ?></b><small><?= e($r['description']) ?></small></span>
                <span class="badge badge-plain"><?= count($rolePerms[(int) $r['id']] ?? []) ?> areas</span>
              </label>
            <?php endforeach; ?>
          </div></div>
        <div class="field"><label class="label">Link to a stylist profile</label>
          <select class="select" name="staff_id" id="umStaff">
            <option value="">Not a stylist — office account only</option>
            <?php foreach (repo_staff() as $s): ?>
              <option value="<?= (int) $s['id'] ?>"><?= e($s['name']) ?> — <?= e($s['job_title']) ?></option>
            <?php endforeach; ?>
          </select>
          <p class="hint">Linking lets this person see their own diary and commission.</p></div>
        <div class="field-row">
          <div class="field" id="umStatusWrap"><label class="label">Account status</label>
            <select class="select" name="status" id="umStatus">
              <option value="active">Active</option><option value="invited">Invited</option>
              <option value="suspended">Suspended</option></select></div>
          <div class="field"><label class="label">Require two-factor</label>
            <label class="switch mt-2"><input type="checkbox" name="two_factor" id="um2fa" value="1"><span></span></label></div>
        </div>
      </div>
      <div class="modal-foot">
        <button type="button" class="btn btn-ghost btn-sm" data-modal-close>Cancel</button>
        <button type="submit" class="btn btn-primary btn-sm">Save account</button>
      </div>
    </form>
  </div>
</div>

<?php
admin_foot(['page' => 'users.php', 'inline' => '<script>
document.addEventListener("click", function (e) {
  if (e.target.closest("#addUser")) {
    ["umRef","umName","umEmail"].forEach(function (i) { document.getElementById(i).value = ""; });
    document.getElementById("umStaff").value = "";
    document.getElementById("um2fa").checked = false;
    document.getElementById("umStatusWrap").style.display = "none";
    document.getElementById("umTitle").textContent = "Add a user";
    PBS.openModal("userModal"); return;
  }
  var b = e.target.closest("[data-edit]");
  if (b) {
    var u = JSON.parse(b.getAttribute("data-edit"));
    document.getElementById("umTitle").textContent = "Edit account — " + u.name;
    document.getElementById("umRef").value = u.ref;
    document.getElementById("umName").value = u.name;
    document.getElementById("umEmail").value = u.email;
    document.getElementById("umStaff").value = u.staff_id || "";
    document.getElementById("umStatus").value = u.status;
    document.getElementById("um2fa").checked = !!+u.two_factor;
    document.getElementById("umStatusWrap").style.display = "";
    var r = document.querySelector("[data-role=\\"" + u.role_id + "\\"]");
    if (r) r.checked = true;
    PBS.openModal("userModal");
  }
});
</script>']);
