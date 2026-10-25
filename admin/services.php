<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/admin-layout.php';

require_permission('services');

$notice = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require();
    $act = (string) ($_POST['action'] ?? '');
    $ref = trim((string) ($_POST['ref'] ?? ''));

    if ($act === 'delete' && $ref !== '') {
        /* Soft delete only. Historical revenue references this row. */
        db_update('services', ['deleted_at' => date('Y-m-d H:i:s'), 'is_public' => 0], ['ref' => $ref]);
        audit("Service $ref retired", 'service', $ref);
        redirect('admin/services.php?saved=1');
    }

    $name  = trim((string) ($_POST['name'] ?? ''));
    $price = (float) ($_POST['price'] ?? 0);
    $dur   = (int) ($_POST['duration'] ?? 0);
    $cat   = (int) ($_POST['category_id'] ?? 0);

    if ($name === '')      $notice = 'Enter a service name.';
    elseif ($price < 0)    $notice = 'Price cannot be negative.';
    elseif ($dur < 5)      $notice = 'Duration must be at least 5 minutes.';
    elseif (!$cat)         $notice = 'Choose a category.';
    else {
        $data = [
            'category_id' => $cat, 'name' => $name,
            'description' => trim((string) ($_POST['description'] ?? '')) ?: null,
            'price' => $price, 'duration_minutes' => $dur,
            'is_popular' => !empty($_POST['is_popular']) ? 1 : 0,
            'is_public'  => !empty($_POST['is_public']) ? 1 : 0,
        ];
        if ($ref !== '' && db_val('SELECT 1 FROM services WHERE ref = ?', [$ref])) {
            db_update('services', $data, ['ref' => $ref]);
            $svcId = (int) db_val('SELECT id FROM services WHERE ref = ?', [$ref]);
            audit("Service $ref updated", 'service', $ref);
        } else {
            $data['ref'] = next_ref('services', 'SV-', 2);
            $svcId = db_insert('services', $data);
            audit('Service ' . $data['ref'] . ' created', 'service', $data['ref']);
        }
        /* Re-link consumed products. */
        db_run('DELETE FROM service_products WHERE service_id = ?', [$svcId]);
        foreach ((array) ($_POST['products'] ?? []) as $pid) {
            db_insert('service_products', ['service_id' => $svcId, 'product_id' => (int) $pid, 'qty_used' => 1]);
        }
        redirect('admin/services.php?saved=1');
    }
}

$catF = (string) ($_GET['cat'] ?? 'all');
$cats = repo_categories();
$all  = repo_services(null, false);
$rows = $catF === 'all' ? $all : array_values(array_filter($all, fn($s) => $s['category_slug'] === $catF));
$products = repo_products();

$monthFrom = date('Y-m-01', strtotime(app_today()));
$monthTo   = date('Y-m-t', strtotime(app_today()));
$perf = [];
foreach (db_all(
    'SELECT ai.service_id, COUNT(*) bookings, SUM(ai.line_total) gross
       FROM appointment_items ai JOIN appointments a ON a.id = ai.appointment_id
      WHERE a.status = "completed" AND a.booked_on BETWEEN ? AND ?
      GROUP BY ai.service_id', [$monthFrom, $monthTo]) as $r) {
    $perf[(int) $r['service_id']] = $r;
}
$linked = [];
foreach (db_all('SELECT service_id, product_id FROM service_products') as $r) {
    $linked[(int) $r['service_id']][] = (int) $r['product_id'];
}

$avgPrice = $all ? array_sum(array_map(fn($s) => (float) $s['price'], $all)) / count($all) : 0;
$avgDur   = $all ? array_sum(array_map(fn($s) => (int) $s['duration_minutes'], $all)) / count($all) : 0;

admin_head(['title' => 'Services', 'subtitle' => 'Catalogue &amp; pricing', 'page' => 'services.php']);
?>

<div class="page-actions">
  <div><h2 class="mb-1" style="font-size:1.375rem">Services</h2>
    <p class="small muted mb-0">Pricing, duration and the stock each service consumes</p></div>
  <div class="spacer"></div>
  <button class="btn btn-primary btn-sm" id="addSvc">Add service</button>
</div>

<?php if ($notice): ?>
  <div class="alert alert-danger mb-4"><span class="ico" data-icon="alert"></span>
    <span><b>Could not save</b><p><?= e($notice) ?></p></span></div>
<?php endif; ?>

<div class="stat-grid">
  <div class="stat accent-black"><div class="top"><div><div class="lab">Active Services</div>
    <div class="val"><?= count($all) ?></div><div class="sub">Across <?= count($cats) ?> categories</div></div>
    <div class="ico" data-icon="tag"></div></div></div>
  <div class="stat accent-gold"><div class="top"><div><div class="lab">Average Price</div>
    <div class="val"><?= e(money($avgPrice)) ?></div>
    <div class="sub">Range <?= e(money_short(min(array_map(fn($s) => (float) $s['price'], $all ?: [0])))) ?> –
      <?= e(money_short(max(array_map(fn($s) => (float) $s['price'], $all ?: [0])))) ?></div></div>
    <div class="ico" data-icon="cash"></div></div></div>
  <div class="stat accent-pink"><div class="top"><div><div class="lab">Average Duration</div>
    <div class="val"><?= round($avgDur) ?> <small>min</small></div>
    <div class="sub">Drives the booking slot length</div></div>
    <div class="ico" data-icon="clock"></div></div></div>
  <div class="stat accent-green"><div class="top"><div><div class="lab">Marked Popular</div>
    <div class="val"><?= count(array_filter($all, fn($s) => (int) $s['is_popular'])) ?></div>
    <div class="sub">Featured on the public site</div></div>
    <div class="ico" data-icon="star"></div></div></div>
</div>

<div class="filter-bar mt-4">
  <span class="label mb-0" style="margin-right:4px">Category</span>
  <div class="chips">
    <a class="chip<?= $catF === 'all' ? ' active' : '' ?>" href="?cat=all">All</a>
    <?php foreach ($cats as $c): ?>
      <a class="chip<?= $catF === $c['slug'] ? ' active' : '' ?>" href="?cat=<?= e($c['slug']) ?>"><?= e($c['short_name']) ?></a>
    <?php endforeach; ?>
  </div>
</div>

<div class="card card-pad-0">
  <div class="table-wrap">
    <table class="table table-cards" style="min-width:1040px">
      <thead><tr><th>Service</th><th>Category</th><th class="num">Price</th><th class="num">Duration</th>
        <th>Stock consumed</th><th class="num">Performance (month)</th><th>Visibility</th><th class="num">Actions</th></tr></thead>
      <tbody>
      <?php foreach ($rows as $s):
        $p = $perf[(int) $s['id']] ?? null;
        $lk = $linked[(int) $s['id']] ?? []; ?>
        <tr>
          <td><span class="cell-user"><span class="icon-badge icon-badge-sm" style="width:34px;height:34px"
                data-icon="<?= e($s['category_icon']) ?>"></span>
            <span><span class="name"><?= e($s['name']) ?></span>
              <span class="tiny muted"><?= e($s['ref']) ?></span></span></span></td>
          <td><span class="badge badge-plain" style="background:<?= e($s['color']) ?>1a;color:<?= e($s['color']) ?>">
            <?= e($s['category_short']) ?></span></td>
          <td class="num strong"><?= e(money($s['price'])) ?></td>
          <td class="num"><?= e(duration((int) $s['duration_minutes'])) ?></td>
          <td><?php foreach ($lk as $pid) {
                $pr = null; foreach ($products as $x) if ((int) $x['id'] === $pid) $pr = $x;
                if ($pr) echo '<span class="badge badge-plain tiny">' . e(explode(' ', $pr['name'])[0]) . '</span> ';
              } if (!$lk) echo '<span class="tiny muted">—</span>'; ?></td>
          <td class="num"><?= $p ? e(money($p['gross'])) . '<br><span class="tiny muted">' .
              (int) $p['bookings'] . ' bookings</span>' : '<span class="tiny muted">—</span>' ?></td>
          <td><?= (int) $s['is_public']
              ? ((int) $s['is_popular'] ? '<span class="badge badge-pink">Popular</span>'
                                        : '<span class="badge badge-success">Public</span>')
              : '<span class="badge">Hidden</span>' ?></td>
          <td class="num">
            <button class="btn-icon" data-edit='<?= e(json_encode([
              'ref' => $s['ref'], 'name' => $s['name'], 'description' => $s['description'],
              'price' => $s['price'], 'duration' => $s['duration_minutes'],
              'category_id' => $s['category_id'], 'is_popular' => $s['is_popular'],
              'is_public' => $s['is_public'], 'products' => $lk,
            ])) ?>' aria-label="Edit"><span data-icon="edit"></span></button>
            <form method="post" style="display:inline"
                  onsubmit="return confirm('Retire this service? It stays on past bookings but can no longer be booked.')">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="ref" value="<?= e($s['ref']) ?>">
              <button class="btn-icon" aria-label="Retire"><span data-icon="trash"></span></button>
            </form></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <div class="card-foot">
    <p class="data-note mb-0">Duration drives the slot length the public booking wizard offers.
      Retiring a service is a soft delete — historical revenue keeps its own frozen copy of the name and price.</p>
  </div>
</div>

<div class="modal modal-lg" id="svcModal">
  <div class="modal-backdrop"></div>
  <div class="modal-box">
    <form method="post">
      <?= csrf_field() ?>
      <div class="modal-head"><h3 id="svcTitle">Add a service</h3>
        <button type="button" class="modal-close" data-modal-close>&times;</button></div>
      <div class="modal-body">
        <input type="hidden" name="ref" id="svcRef">
        <div class="field"><label class="label">Service name <span class="req">*</span></label>
          <input class="input" name="name" id="svcName" required placeholder="e.g. Knotless Box Braids"></div>
        <div class="field-row">
          <div class="field"><label class="label">Category <span class="req">*</span></label>
            <select class="select" name="category_id" id="svcCat" required>
              <?php foreach ($cats as $c): ?>
                <option value="<?= (int) $c['id'] ?>"><?= e($c['name']) ?></option><?php endforeach; ?></select></div>
          <div class="field"><label class="label">Price (<?= e(CURRENCY) ?>) <span class="req">*</span></label>
            <input class="input" type="number" name="price" id="svcPrice" min="0" step="1" required></div>
        </div>
        <div class="field-row">
          <div class="field"><label class="label">Duration (minutes) <span class="req">*</span></label>
            <input class="input" type="number" name="duration" id="svcDur" min="5" step="5" required></div>
          <div class="field"><label class="label">Visibility</label>
            <label class="check mt-2"><input type="checkbox" name="is_public" id="svcPublic" value="1" checked>
              Show on the public site</label>
            <label class="check"><input type="checkbox" name="is_popular" id="svcPopular" value="1">
              Mark as popular</label></div>
        </div>
        <div class="field"><label class="label">Description</label>
          <textarea class="textarea" name="description" id="svcDesc" style="min-height:80px"></textarea></div>
        <div class="field mb-0"><label class="label">Inventory consumed per booking</label>
          <p class="hint mb-2">Linked products are deducted automatically when this service is completed.</p>
          <div style="max-height:190px;overflow:auto">
          <?php foreach ($products as $p): ?>
            <label class="check mb-2"><input type="checkbox" name="products[]"
              value="<?= (int) $p['id'] ?>" data-prod="<?= (int) $p['id'] ?>"><?= e($p['name']) ?></label>
          <?php endforeach; ?>
          </div></div>
      </div>
      <div class="modal-foot">
        <button type="button" class="btn btn-ghost btn-sm" data-modal-close>Cancel</button>
        <button type="submit" class="btn btn-primary btn-sm">Save service</button>
      </div>
    </form>
  </div>
</div>

<?php
admin_foot(['page' => 'services.php', 'inline' => '<script>
document.addEventListener("click", function (e) {
  function reset() {
    ["svcRef","svcName","svcDesc"].forEach(function (i) { document.getElementById(i).value = ""; });
    document.getElementById("svcPrice").value = "";
    document.getElementById("svcDur").value = "";
    document.getElementById("svcPublic").checked = true;
    document.getElementById("svcPopular").checked = false;
    document.querySelectorAll("[data-prod]").forEach(function (c) { c.checked = false; });
  }
  if (e.target.closest("#addSvc")) {
    reset(); document.getElementById("svcTitle").textContent = "Add a service";
    PBS.openModal("svcModal"); return;
  }
  var b = e.target.closest("[data-edit]");
  if (b) {
    reset();
    var s = JSON.parse(b.getAttribute("data-edit"));
    document.getElementById("svcTitle").textContent = "Edit " + s.name;
    document.getElementById("svcRef").value = s.ref;
    document.getElementById("svcName").value = s.name;
    document.getElementById("svcDesc").value = s.description || "";
    document.getElementById("svcPrice").value = parseFloat(s.price);
    document.getElementById("svcDur").value = s.duration;
    document.getElementById("svcCat").value = s.category_id;
    document.getElementById("svcPublic").checked = !!+s.is_public;
    document.getElementById("svcPopular").checked = !!+s.is_popular;
    (s.products || []).forEach(function (pid) {
      var c = document.querySelector("[data-prod=\\"" + pid + "\\"]");
      if (c) c.checked = true;
    });
    PBS.openModal("svcModal");
  }
});
</script>']);
