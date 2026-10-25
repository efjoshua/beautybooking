<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/admin-layout.php';

$u = require_permission('dashboard');
$notice = null;
$ok = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require();

    if (($_POST['action'] ?? '') === 'password') {
        $cur = (string) ($_POST['current'] ?? '');
        $new = (string) ($_POST['new'] ?? '');
        $rep = (string) ($_POST['repeat'] ?? '');
        $hash = (string) db_val('SELECT password_hash FROM users WHERE id = ?', [$u['id']]);

        if (!password_verify($cur, $hash)) $notice = 'Your current password is not correct.';
        elseif (strlen($new) < 8)          $notice = 'Choose a password of at least 8 characters.';
        elseif ($new !== $rep)             $notice = 'The two new passwords do not match.';
        else {
            db_update('users',
                ['password_hash' => password_hash($new, PASSWORD_BCRYPT, ['cost' => BCRYPT_COST])],
                ['id' => $u['id']]);
            audit('Changed own password', 'user', (string) $u['id']);
            $ok = 'Password updated.';
        }
    } else {
        $name = trim((string) ($_POST['name'] ?? ''));
        if ($name === '') $notice = 'Enter your name.';
        else {
            db_update('users', ['name' => $name], ['id' => $u['id']]);
            if ($u['staff_id']) {
                db_update('staff', ['bio' => trim((string) ($_POST['bio'] ?? '')) ?: null],
                          ['id' => $u['staff_id']]);
            }
            audit('Updated own profile', 'user', (string) $u['id']);
            redirect('admin/profile.php?saved=1');
        }
    }
}

$perms = user_permissions();
admin_head(['title' => 'My Profile', 'subtitle' => 'Account settings', 'page' => 'profile.php']);
?>
<div class="page-actions">
  <div><h2 class="mb-1" style="font-size:1.375rem">My Profile</h2>
    <p class="small muted mb-0">Your details, your login, your security</p></div>
</div>

<?php if ($notice): ?>
  <div class="alert alert-danger mb-4"><span class="ico" data-icon="alert"></span>
    <span><b>Could not save</b><p><?= e($notice) ?></p></span></div>
<?php endif; ?>
<?php if ($ok || !empty($_GET['saved'])): ?>
  <div class="alert alert-success mb-4"><span class="ico" data-icon="check"></span>
    <span><b>Saved</b><p><?= e($ok ?: 'Profile updated.') ?></p></span></div>
<?php endif; ?>

<div class="card mb-4">
  <div class="flex items-center gap-4 wrapflex">
    <span class="avatar avatar-lg<?= is_owner() ? '' : ' avatar-pink' ?>"
          style="width:72px;height:72px;font-size:1.25rem"><?= e(initials($u['name'])) ?></span>
    <div style="flex:1;min-width:180px">
      <h3 class="mb-1"><?= e($u['name']) ?></h3>
      <span class="tiny muted"><?= e($u['job_title'] ?: $u['role_name']) ?><?=
        $u['since_year'] ? ' · with us since ' . e($u['since_year']) : '' ?></span>
      <div class="mt-2"><span class="badge badge-gold"><?= e($u['role_name']) ?></span></div>
    </div>
    <?php if ($u['staff_id']): ?>
      <div class="text-right">
        <div class="stars"><?= str_repeat('★', (int) round((float) $u['rating'])) ?></div>
        <span class="tiny muted"><?= number_format((float) $u['rating'], 1) ?> from
          <?= (int) $u['reviews_count'] ?> reviews</span></div>
    <?php endif; ?>
  </div>
</div>

<div class="dash-grid dash-2-1">
  <div class="grid" style="gap:16px;align-content:start">
    <div class="card">
      <h4 class="mb-5">Personal details</h4>
      <form method="post"><?= csrf_field() ?>
        <div class="field-row">
          <div class="field"><label class="label">Full name</label>
            <input class="input" name="name" value="<?= e($u['name']) ?>" required></div>
          <div class="field"><label class="label">Email</label>
            <input class="input" value="<?= e($u['email']) ?>" disabled>
            <p class="hint">Only the owner can change the email on an account.</p></div>
        </div>
        <?php if ($u['staff_id']): ?>
          <div class="field"><label class="label">Public bio</label>
            <textarea class="textarea" name="bio" style="min-height:90px"><?= e((string) $u['bio']) ?></textarea>
            <p class="hint">Shown on the Stylists page of the public site.</p></div>
          <div class="field mb-0"><label class="label">Working hours</label>
            <input class="input" value="<?= e((string) $u['working_hours']) ?>" disabled>
            <p class="hint">Only the owner can change your rota — ask at the desk.</p></div>
        <?php endif; ?>
        <button class="btn btn-primary btn-sm mt-5">Save details</button>
      </form>
    </div>

    <div class="card">
      <h4 class="mb-2">Security</h4>
      <p class="small muted">Keep your account safe — you can see client contact details.</p>
      <form method="post" class="mt-4"><?= csrf_field() ?>
        <input type="hidden" name="action" value="password">
        <div class="field"><label class="label">Current password</label>
          <input class="input" type="password" name="current" required autocomplete="current-password"></div>
        <div class="field-row">
          <div class="field"><label class="label">New password</label>
            <input class="input" type="password" name="new" minlength="8" required autocomplete="new-password"></div>
          <div class="field"><label class="label">Repeat new password</label>
            <input class="input" type="password" name="repeat" minlength="8" required autocomplete="new-password"></div>
        </div>
        <button class="btn btn-ghost btn-sm">Update password</button>
      </form>
    </div>
  </div>

  <div class="grid" style="gap:16px;align-content:start">
    <div class="card card-pad-0">
      <div class="card-head"><h4>Account</h4></div>
      <div class="card-body">
        <div class="kv"><span>Account ID</span><b><?= e($u['ref']) ?></b></div>
        <div class="kv"><span>Role</span><b><?= e($u['role_name']) ?></b></div>
        <div class="kv"><span>Status</span><b><?= e(ucfirst($u['status'])) ?></b></div>
        <div class="kv"><span>Last sign-in</span><b><?= e($u['last_login_at'] ?: '—') ?></b></div>
        <div class="kv"><span>Two-factor</span><b><?= (int) $u['two_factor'] ? 'Enabled' : 'Not set up' ?></b></div>
        <?php if ($u['staff_id']): ?>
          <div class="kv"><span>Commission rate</span>
            <b><?= rtrim(rtrim(number_format((float) $u['commission_pct'], 1), '0'), '.') ?>%</b></div>
        <?php endif; ?>
        <div class="kv total"><span>Access to</span><b><?= count($perms) ?> areas</b></div>
      </div>
    </div>

    <div class="card">
      <h4 class="mb-3">What you can reach</h4>
      <div class="chips">
        <?php foreach ($perms as $p): ?>
          <span class="badge badge-plain badge-gold"><?= e($p) ?></span>
        <?php endforeach; ?>
      </div>
    </div>

    <div class="card">
      <h4 class="mb-3">Session</h4>
      <p class="small muted">Signed in on this device.</p>
      <form method="post" action="<?= url('admin/logout.php') ?>">
        <?= csrf_field() ?>
        <button class="btn btn-ghost btn-block btn-sm">Sign out</button>
      </form>
    </div>
  </div>
</div>

<?php admin_foot(['page' => 'profile.php']); ?>
