<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/admin-layout.php';

require_permission('inventory');

$products = repo_products();
$retail   = repo_products(true);
$low      = repo_low_stock();
$moves    = repo_stock_movements(40);
$cats     = repo_categories();
$today    = app_today();

$retailRevenue = (float) db_val(
    'SELECT COALESCE(SUM(total), 0) FROM product_sales WHERE sold_on BETWEEN ? AND ?',
    [date('Y-m-01', strtotime($today)), date('Y-m-t', strtotime($today))]);
$retailCount = (int) db_val(
    'SELECT COUNT(*) FROM product_sales WHERE sold_on BETWEEN ? AND ?',
    [date('Y-m-01', strtotime($today)), date('Y-m-t', strtotime($today))]);

admin_head(['title' => 'Inventory / Store', 'subtitle' => 'Stock &amp; product sales', 'page' => 'inventory.php']);
?>

<div class="page-actions">
  <div>
    <h2 class="mb-1" style="font-size:1.375rem">Inventory &amp; Store</h2>
    <p class="small muted mb-0">Products, stock movements and counter sales</p>
  </div>
  <div class="spacer"></div>
  <div class="btn-row">
    <button class="btn btn-ghost btn-sm" data-modal="restockModal">Record restock</button>
    <button class="btn btn-primary btn-sm" id="addProduct">Add product</button>
  </div>
</div>

<div class="stat-grid">
  <div class="stat accent-black"><div class="top"><div><div class="lab">Products Tracked</div>
    <div class="val"><?= count($products) ?></div>
    <div class="sub"><?= count($retail) ?> also sold at the counter</div></div>
    <div class="ico" data-icon="box"></div></div></div>
  <div class="stat accent-gold"><div class="top"><div><div class="lab">Stock Value at Cost</div>
    <div class="val"><?= e(money(repo_stock_value())) ?></div>
    <div class="sub">Across all categories</div></div>
    <div class="ico" data-icon="wallet"></div></div></div>
  <div class="stat accent-pink"><div class="top"><div><div class="lab">Low / Out of Stock</div>
    <div class="val"><?= count($low) ?></div>
    <div class="sub"><?= count(array_filter($low, fn($p) => (int) $p['qty_on_hand'] === 0)) ?> completely out</div></div>
    <div class="ico" data-icon="alert"></div></div></div>
  <div class="stat accent-green"><div class="top"><div><div class="lab">Retail Sales (Month)</div>
    <div class="val"><?= e(money($retailRevenue)) ?></div>
    <div class="sub"><?= $retailCount ?> counter transactions</div></div>
    <div class="ico" data-icon="cash"></div></div></div>
</div>

<div class="card card-pad-0 mt-4">
  <div class="card-head"><div><h4>Low stock &amp; reorder alerts</h4>
    <span class="tiny muted">Anything at or below its reorder threshold</span></div></div>
  <div class="card-body grid" style="gap:10px">
    <?php if (!$low): ?>
      <div class="empty"><p>Every product is above its reorder threshold.</p></div>
    <?php else: foreach ($low as $p): $out = (int) $p['qty_on_hand'] === 0; ?>
      <div class="alert alert-<?= $out ? 'danger' : 'warning' ?>">
        <span class="ico" data-icon="alert"></span>
        <span style="flex:1"><b><?= e($p['name']) ?></b>
          <p><?= $out ? 'Out of stock' : 'Only ' . (int) $p['qty_on_hand'] . ' ' . e($p['unit']) . ' left' ?>
            · reorder level <?= (int) $p['reorder_level'] ?> · <?= e($p['supplier'] ?: 'no supplier set') ?></p></span>
        <button class="btn btn-dark btn-sm" data-restock="<?= e($p['ref']) ?>"
                data-name="<?= e($p['name']) ?>">Restock</button>
      </div>
    <?php endforeach; endif; ?>
  </div>
</div>

<div class="dash-grid dash-1-2 mt-4">
  <div class="card card-pad-0">
    <div class="card-head"><div><h4>Sell a product</h4>
      <span class="tiny muted">Counter sale — logs revenue, deducts stock</span></div></div>
    <div class="card-body">
      <div class="field"><label class="label" for="posProduct">Product</label>
        <select class="select" id="posProduct">
          <option value="">Choose a product…</option>
          <?php foreach ($retail as $p): ?>
            <option value="<?= e($p['ref']) ?>" data-name="<?= e($p['name']) ?>"
                    data-price="<?= (float) $p['retail_price'] ?>" data-stock="<?= (int) $p['qty_on_hand'] ?>"
                    <?= (int) $p['qty_on_hand'] === 0 ? 'disabled' : '' ?>>
              <?= e($p['name']) ?> — <?= e(money($p['retail_price'])) ?>
              (<?= (int) $p['qty_on_hand'] ?> in stock)</option>
          <?php endforeach; ?>
        </select></div>
      <div class="field-row">
        <div class="field"><label class="label" for="posQty">Quantity</label>
          <input class="input" id="posQty" type="number" value="1" min="1"></div>
        <div class="field"><label class="label" for="posMethod">Paid with</label>
          <select class="select" id="posMethod">
            <option value="cash">Cash</option><option value="momo">MTN MoMo</option>
            <option value="om">Orange Money</option><option value="card">Card</option>
          </select></div>
      </div>
      <button class="btn btn-ghost btn-block btn-sm" id="posAdd">Add to sale</button>
      <div class="pos-cart mt-4" id="posCart"></div>
      <button class="btn btn-primary btn-block mt-4" id="posSell">Complete sale</button>
      <p class="data-note mt-3 mb-0">A completed sale writes to <b>product_sales</b>,
        appears in the revenue report, and deducts from stock.</p>
    </div>
  </div>

  <div class="card card-pad-0">
    <div class="card-head"><div><h4>Product &amp; supply list</h4>
      <span class="tiny muted">Quantity on hand, unit cost, reorder level</span></div>
      <a class="btn btn-ghost btn-sm" href="<?= e(url('admin/export.php')) ?>?report=summary">Export</a></div>
    <div class="table-wrap">
      <table class="table table-cards" style="min-width:980px">
        <thead><tr><th>Product</th><th>Category</th><th class="num">On hand</th><th class="num">Reorder at</th>
          <th class="num">Unit cost</th><th class="num">Retail price</th><th class="num">Stock value</th>
          <th>Linked to</th><th class="num">Status</th></tr></thead>
        <tbody>
        <?php foreach ($products as $p):
          $qty = (int) $p['qty_on_hand']; $lvl = (int) $p['reorder_level'];
          $state = $qty === 0 ? 'out' : ($qty <= $lvl ? 'low' : 'ok');
          $badge = ['ok' => 'badge-success', 'low' => 'badge-warning', 'out' => 'badge-danger'][$state];
          $label = ['ok' => 'In stock', 'low' => 'Low', 'out' => 'Out'][$state]; ?>
          <tr>
            <td><span class="strong"><?= e($p['name']) ?></span><br>
              <span class="tiny muted"><?= e($p['ref']) ?> · <?= e($p['supplier'] ?: '—') ?></span></td>
            <td><?php if ($p['category_short']): ?>
              <span class="badge badge-plain" style="background:<?= e($p['color']) ?>1a;color:<?= e($p['color']) ?>">
                <?= e($p['category_short']) ?></span>
              <?php else: ?><span class="tiny muted">General</span><?php endif; ?></td>
            <td class="num"><span class="strong"><?= $qty ?></span>
              <span class="tiny muted"><?= e($p['unit']) ?></span>
              <div class="stock-bar mt-2" style="margin-left:auto">
                <i class="<?= $state ?>" style="width:<?= $lvl > 0 ? min(100, $qty / max(1, $lvl * 2) * 100) : 100 ?>%"></i></div></td>
            <td class="num"><?= $lvl ?></td>
            <td class="num"><?= e(money($p['unit_cost'])) ?></td>
            <td class="num"><?= $p['retail_price'] > 0
              ? e(money($p['retail_price'])) : '<span class="muted tiny">Not sold</span>' ?></td>
            <td class="num strong"><?= e(money($qty * (float) $p['unit_cost'])) ?></td>
            <td><span class="tiny muted"><?= (int) $p['used_by'] ?: '—' ?>
              <?= (int) $p['used_by'] ? 'service' . ((int) $p['used_by'] > 1 ? 's' : '') : '' ?></span></td>
            <td class="num"><span class="badge <?= $badge ?>"><?= $label ?></span>
              <button class="btn-icon" data-edit-product="<?= e($p['ref']) ?>"
                data-json='<?= e(json_encode([
                  'ref' => $p['ref'], 'name' => $p['name'], 'unit' => $p['unit'],
                  'category_id' => $p['category_id'], 'reorder_level' => $p['reorder_level'],
                  'unit_cost' => $p['unit_cost'], 'retail_price' => $p['retail_price'],
                  'is_retail' => $p['is_retail'], 'supplier' => $p['supplier'],
                ])) ?>' aria-label="Edit"><span data-icon="edit"></span></button></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <div class="card-foot">
      <p class="data-note mb-0">"Linked to" counts the services that consume this product.
        When such a service is marked complete, stock is deducted automatically.</p>
    </div>
  </div>
</div>

<div class="card card-pad-0 mt-4">
  <div class="card-head"><div><h4>Stock in / out log</h4>
    <span class="tiny muted">Restocks, service usage, counter sales and write-offs</span></div></div>
  <div class="table-wrap">
    <table class="table table-cards" style="min-width:900px">
      <thead><tr><th>Date</th><th>Product</th><th>Type</th><th class="num">Qty</th>
        <th>Reference</th><th>Note</th><th>Recorded by</th></tr></thead>
      <tbody>
      <?php foreach ($moves as $m):
        $cls = ['in' => 'badge-success', 'sale' => 'badge-info',
                'out' => 'badge-warning', 'adjust' => 'badge-danger'][$m['movement_type']] ?? 'badge'; ?>
        <tr><td><?= e(fmt_day($m['moved_on'])) ?><br><span class="tiny muted"><?= e($m['ref']) ?></span></td>
          <td><?= e($m['product_name']) ?></td>
          <td><span class="badge <?= $cls ?>"><?= e(strtoupper($m['movement_type'])) ?></span></td>
          <td class="num strong"><?= (int) $m['qty'] > 0 ? '+' : '' ?><?= (int) $m['qty'] ?></td>
          <td><?= e($m['reference'] ?: '—') ?></td>
          <td><?= e($m['note'] ?: '') ?></td>
          <td><?= e($m['staff_name'] ?: '—') ?></td></tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- RESTOCK MODAL -->
<div class="modal" id="restockModal">
  <div class="modal-backdrop"></div>
  <div class="modal-box">
    <div class="modal-head"><h3>Record a restock</h3>
      <button class="modal-close" data-modal-close>&times;</button></div>
    <div class="modal-body">
      <div class="field"><label class="label">Product</label>
        <select class="select" id="rsProduct">
          <?php foreach ($products as $p): ?>
            <option value="<?= e($p['ref']) ?>"><?= e($p['name']) ?> —
              <?= (int) $p['qty_on_hand'] ?> <?= e($p['unit']) ?> on hand</option>
          <?php endforeach; ?>
        </select></div>
      <div class="field-row">
        <div class="field"><label class="label">Quantity received</label>
          <input class="input" id="rsQty" type="number" value="10" min="1"></div>
        <div class="field"><label class="label">Total cost (<?= e(CURRENCY) ?>)</label>
          <input class="input" id="rsCost" type="number" min="0" step="1" placeholder="0"></div>
      </div>
      <div class="field"><label class="label">Purchase order reference</label>
        <input class="input" id="rsPo" placeholder="e.g. PO-046"></div>
      <label class="check mb-0"><input type="checkbox" id="rsExpense" checked>
        Also log this as an expense under "Product restock"</label>
    </div>
    <div class="modal-foot">
      <button class="btn btn-ghost btn-sm" data-modal-close>Cancel</button>
      <button class="btn btn-primary btn-sm" id="rsSave">Add to stock</button>
    </div>
  </div>
</div>

<!-- PRODUCT MODAL -->
<div class="modal" id="productModal">
  <div class="modal-backdrop"></div>
  <div class="modal-box">
    <div class="modal-head"><h3 id="pmTitle">Add a product</h3>
      <button class="modal-close" data-modal-close>&times;</button></div>
    <div class="modal-body">
      <input type="hidden" id="pmRef">
      <div class="field"><label class="label">Product name <span class="req">*</span></label>
        <input class="input" id="pmName" placeholder="e.g. Pre-stretched Braid Hair XL"></div>
      <div class="field-row">
        <div class="field"><label class="label">Category</label>
          <select class="select" id="pmCat"><option value="">General supplies</option>
            <?php foreach ($cats as $c): ?>
              <option value="<?= (int) $c['id'] ?>"><?= e($c['name']) ?></option>
            <?php endforeach; ?></select></div>
        <div class="field"><label class="label">Unit</label>
          <input class="input" id="pmUnit" placeholder="bottle / pack / kit" value="unit"></div>
      </div>
      <div class="field-row">
        <div class="field" id="pmQtyWrap"><label class="label">Opening quantity</label>
          <input class="input" id="pmQty" type="number" value="0" min="0"></div>
        <div class="field"><label class="label">Reorder threshold</label>
          <input class="input" id="pmReorder" type="number" value="5" min="0"></div>
      </div>
      <div class="field-row">
        <div class="field"><label class="label">Unit cost (<?= e(CURRENCY) ?>)</label>
          <input class="input" id="pmCost" type="number" min="0" step="1" value="0"></div>
        <div class="field"><label class="label">Retail price (<?= e(CURRENCY) ?>)</label>
          <input class="input" id="pmPrice" type="number" min="0" step="1" value="0"></div>
      </div>
      <div class="field"><label class="label">Supplier</label>
        <input class="input" id="pmSupplier" placeholder="e.g. Marché Mokolo"></div>
      <label class="check mb-0"><input type="checkbox" id="pmRetail">
        Also sold directly to clients at the counter</label>
    </div>
    <div class="modal-foot">
      <button class="btn btn-ghost btn-sm" data-modal-close>Cancel</button>
      <button class="btn btn-primary btn-sm" id="pmSave">Save product</button>
    </div>
  </div>
</div>

<?php
admin_foot(['page' => 'inventory.php', 'inline' => '<script>
(function () {
  var API = ' . json_encode(url('api/stock.php')) . ';
  var cart = [];
  var $ = function (s) { return document.querySelector(s); };

  function post(fields) {
    var fd = new FormData();
    Object.keys(fields).forEach(function (k) { fd.append(k, fields[k]); });
    return PBS.api(API, { method: "POST", body: fd });
  }

  function renderCart() {
    var total = cart.reduce(function (t, l) { return t + l.qty * l.price; }, 0);
    $("#posCart").innerHTML = cart.length
      ? cart.map(function (l, i) {
          return "<div class=\"pos-line\"><span>" + PBS.esc(l.name) + " x " + l.qty + "</span>" +
            "<b>" + PBS.money(l.qty * l.price) + " <button class=\"btn-icon btn-sm\" data-rm=\"" + i +
            "\" style=\"width:24px;height:24px;margin-left:6px\">" + PBS.icon("x") + "</button></b></div>";
        }).join("") +
        "<div class=\"pos-line\" style=\"border-top:1px solid var(--n-300);margin-top:8px;padding-top:10px\">" +
        "<b>Total</b><b class=\"t-pink\">" + PBS.money(total) + "</b></div>"
      : "<p class=\"tiny muted mb-0\">Cart is empty. Add a product to record a counter sale.</p>";
  }
  renderCart();

  document.addEventListener("click", function (e) {
    var rm = e.target.closest("[data-rm]");
    if (rm) { cart.splice(+rm.getAttribute("data-rm"), 1); renderCart(); return; }

    if (e.target.closest("#posAdd")) {
      var sel = $("#posProduct"), opt = sel.options[sel.selectedIndex];
      if (!sel.value) { PBS.toast("Choose a product first.", "error"); return; }
      var qty = Math.max(1, parseInt($("#posQty").value, 10) || 1);
      var stock = +opt.getAttribute("data-stock");
      var already = cart.filter(function (l) { return l.ref === sel.value; })
                        .reduce(function (t, l) { return t + l.qty; }, 0);
      if (already + qty > stock) {
        PBS.toast("Only " + stock + " in stock.", "error"); return;
      }
      cart.push({ ref: sel.value, name: opt.getAttribute("data-name"),
                  price: +opt.getAttribute("data-price"), qty: qty });
      renderCart();
      return;
    }

    if (e.target.closest("#posSell")) {
      if (!cart.length) { PBS.toast("Cart is empty.", "error"); return; }
      post({ action: "sell", method: $("#posMethod").value, lines: JSON.stringify(cart) })
        .then(function (r) { PBS.toast(r.message); setTimeout(function () { location.reload(); }, 900); })
        .catch(function (err) { PBS.toast(err.message, "error"); });
      return;
    }

    var rs = e.target.closest("[data-restock]");
    if (rs) { $("#rsProduct").value = rs.getAttribute("data-restock"); PBS.openModal("restockModal"); return; }

    if (e.target.closest("#rsSave")) {
      post({ action: "restock", ref: $("#rsProduct").value, qty: $("#rsQty").value,
             cost: $("#rsCost").value || 0, po: $("#rsPo").value,
             log_expense: $("#rsExpense").checked ? 1 : 0 })
        .then(function (r) { PBS.toast(r.message); setTimeout(function () { location.reload(); }, 900); })
        .catch(function (err) { PBS.toast(err.message, "error"); });
      return;
    }

    if (e.target.closest("#addProduct")) {
      $("#pmTitle").textContent = "Add a product";
      ["pmRef","pmName","pmSupplier"].forEach(function (id) { $("#" + id).value = ""; });
      $("#pmUnit").value = "unit"; $("#pmQty").value = 0; $("#pmReorder").value = 5;
      $("#pmCost").value = 0; $("#pmPrice").value = 0; $("#pmRetail").checked = false;
      $("#pmQtyWrap").style.display = "";
      PBS.openModal("productModal");
      return;
    }

    var ep = e.target.closest("[data-edit-product]");
    if (ep) {
      var p = JSON.parse(ep.getAttribute("data-json"));
      $("#pmTitle").textContent = "Edit " + p.name;
      $("#pmRef").value = p.ref; $("#pmName").value = p.name; $("#pmUnit").value = p.unit;
      $("#pmCat").value = p.category_id || ""; $("#pmReorder").value = p.reorder_level;
      $("#pmCost").value = p.unit_cost; $("#pmPrice").value = p.retail_price;
      $("#pmSupplier").value = p.supplier || ""; $("#pmRetail").checked = !!+p.is_retail;
      $("#pmQtyWrap").style.display = "none";   // stock changes go through restock
      PBS.openModal("productModal");
      return;
    }

    if (e.target.closest("#pmSave")) {
      post({ action: "save", ref: $("#pmRef").value, name: $("#pmName").value,
             category_id: $("#pmCat").value, unit: $("#pmUnit").value,
             qty_on_hand: $("#pmQty").value, reorder_level: $("#pmReorder").value,
             unit_cost: $("#pmCost").value, retail_price: $("#pmPrice").value,
             supplier: $("#pmSupplier").value, is_retail: $("#pmRetail").checked ? 1 : 0 })
        .then(function (r) { PBS.toast(r.message); setTimeout(function () { location.reload(); }, 900); })
        .catch(function (err) { PBS.toast(err.message, "error"); });
    }
  });
})();
</script>']);
