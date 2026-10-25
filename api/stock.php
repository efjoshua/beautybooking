<?php
/**
 * Inventory writes: counter sale, restock, product create/update.
 * A sale and its stock deduction happen in one transaction — takings and
 * inventory must never disagree.
 */
declare(strict_types=1);
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/repo.php';

api_require_permission('inventory');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_error('POST only.', 405);
csrf_require();

$action = (string) ($_POST['action'] ?? '');
$me = current_user()['staff_id'] ?: null;

/* ------------------------------------------------------------ counter sale */
if ($action === 'sell') {
    $lines = json_decode((string) ($_POST['lines'] ?? '[]'), true);
    if (!is_array($lines) || !$lines) json_error('The cart is empty.');
    $method = in_array($_POST['method'] ?? '', ['cash', 'momo', 'om', 'card'], true)
        ? (string) $_POST['method'] : 'cash';

    try {
        $result = db_transaction(function () use ($lines, $method, $me) {
            $total = 0.0;
            $resolved = [];
            foreach ($lines as $l) {
                $p = db_one('SELECT * FROM products WHERE ref = ? AND deleted_at IS NULL',
                            [$l['ref'] ?? '']);
                if (!$p) throw new RuntimeException('Unknown product in the cart.');
                $qty = max(1, (int) ($l['qty'] ?? 1));
                if ((int) $p['qty_on_hand'] < $qty) {
                    throw new RuntimeException('Only ' . (int) $p['qty_on_hand'] . ' left of '
                        . $p['name'] . '.');
                }
                $line = $qty * (float) $p['retail_price'];
                $total += $line;
                $resolved[] = [$p, $qty, $line];
            }

            $ref = next_ref('product_sales', 'POS-', 3);
            $saleId = db_insert('product_sales', [
                'ref' => $ref, 'client_id' => null, 'sold_by' => $me,
                'sold_on' => app_today(), 'payment_method' => $method, 'total' => $total,
            ]);

            foreach ($resolved as [$p, $qty, $line]) {
                db_insert('product_sale_items', [
                    'sale_id' => $saleId, 'product_id' => $p['id'], 'product_name' => $p['name'],
                    'unit_price' => $p['retail_price'], 'qty' => $qty, 'line_total' => $line,
                ]);
                db_insert('stock_movements', [
                    'ref' => next_ref('stock_movements', 'SM-', 4),
                    'product_id' => $p['id'], 'movement_type' => 'sale', 'qty' => -$qty,
                    'reference' => $ref, 'note' => 'Counter sale', 'staff_id' => $me,
                    'moved_on' => app_today(),
                ]);
                db_run('UPDATE products SET qty_on_hand = qty_on_hand - ? WHERE id = ?',
                       [$qty, $p['id']]);
            }
            return ['ref' => $ref, 'total' => $total];
        });
    } catch (RuntimeException $e) {
        json_error($e->getMessage(), 422);
    }

    audit('Counter sale ' . $result['ref'] . ' — ' . money($result['total']), 'sale', $result['ref']);
    json_response(['ok' => true, 'ref' => $result['ref'],
                   'message' => 'Sale of ' . money($result['total']) . ' recorded.']);
}

/* ---------------------------------------------------------------- restock */
if ($action === 'restock') {
    $p = db_one('SELECT * FROM products WHERE ref = ? AND deleted_at IS NULL', [$_POST['ref'] ?? '']);
    if (!$p) json_error('Product not found.', 404);
    $qty  = (int) ($_POST['qty'] ?? 0);
    $cost = (float) ($_POST['cost'] ?? 0);
    $po   = trim((string) ($_POST['po'] ?? ''));
    $logExpense = !empty($_POST['log_expense']);
    if ($qty <= 0) json_error('Enter a quantity greater than zero.');

    db_transaction(function () use ($p, $qty, $cost, $po, $logExpense, $me) {
        db_insert('stock_movements', [
            'ref' => next_ref('stock_movements', 'SM-', 4),
            'product_id' => $p['id'], 'movement_type' => 'in', 'qty' => $qty,
            'reference' => $po ?: null,
            'note' => 'Restock — ' . ($p['supplier'] ?: 'supplier'),
            'staff_id' => $me, 'moved_on' => app_today(),
        ]);
        db_run('UPDATE products SET qty_on_hand = qty_on_hand + ? WHERE id = ?', [$qty, $p['id']]);

        /* Log it as an expense too, so restock spending reaches net profit. */
        if ($cost > 0 && $logExpense) {
            db_insert('expenses', [
                'ref' => next_ref('expenses', 'EX-', 3),
                'category_id' => db_val('SELECT id FROM expense_categories WHERE slug = ?', ['restock']),
                'description' => 'Restock: ' . $qty . ' x ' . $p['name'],
                'amount' => $cost, 'payment_method' => 'momo',
                'spent_on' => app_today(), 'recorded_by' => $me,
            ]);
        }
    });

    audit('Restocked ' . $p['ref'] . ' +' . $qty, 'product', $p['ref']);
    json_response(['ok' => true, 'message' => 'Added ' . $qty . ' x ' . $p['name'] . ' to stock.']);
}

/* ------------------------------------------------------- create / update */
if ($action === 'save') {
    $ref  = trim((string) ($_POST['ref'] ?? ''));
    $name = trim((string) ($_POST['name'] ?? ''));
    if ($name === '') json_error('Enter a product name.');

    $data = [
        'name' => $name,
        'category_id' => ($_POST['category_id'] ?? '') !== '' ? (int) $_POST['category_id'] : null,
        'unit' => trim((string) ($_POST['unit'] ?? 'unit')) ?: 'unit',
        'reorder_level' => max(0, (int) ($_POST['reorder_level'] ?? 0)),
        'unit_cost' => max(0, (float) ($_POST['unit_cost'] ?? 0)),
        'retail_price' => max(0, (float) ($_POST['retail_price'] ?? 0)),
        'is_retail' => !empty($_POST['is_retail']) ? 1 : 0,
        'supplier' => trim((string) ($_POST['supplier'] ?? '')) ?: null,
    ];

    if ($ref !== '' && db_val('SELECT 1 FROM products WHERE ref = ?', [$ref])) {
        db_update('products', $data, ['ref' => $ref]);
        audit('Product ' . $ref . ' updated', 'product', $ref);
        json_response(['ok' => true, 'message' => 'Product updated.']);
    }

    $newRef = next_ref('products', 'P-', 2);
    $data['ref'] = $newRef;
    $data['qty_on_hand'] = max(0, (int) ($_POST['qty_on_hand'] ?? 0));
    $id = db_insert('products', $data);
    if ($data['qty_on_hand'] > 0) {
        db_insert('stock_movements', [
            'ref' => next_ref('stock_movements', 'SM-', 4), 'product_id' => $id,
            'movement_type' => 'in', 'qty' => $data['qty_on_hand'], 'reference' => 'OPENING',
            'note' => 'Opening stock', 'staff_id' => $me, 'moved_on' => app_today(),
        ]);
    }
    audit('Product ' . $newRef . ' created', 'product', $newRef);
    json_response(['ok' => true, 'message' => 'Product added.']);
}

json_error('Unknown action.');
