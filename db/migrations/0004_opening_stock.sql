-- =============================================================================
-- Opening stock balances
--
-- products.qty_on_hand is a running cache; stock_movements is the truth, and
-- the two are meant to reconcile. Seeded products were given a quantity with
-- no corresponding movement, so the ledger was short by exactly the opening
-- balance and could never be audited against the cache.
--
-- This inserts one balancing movement per product: whatever the cache says,
-- minus everything the ledger already accounts for.
-- =============================================================================

INSERT INTO stock_movements (ref, product_id, movement_type, qty, reference, note, moved_on)
SELECT CONCAT('SM-OPEN-', p.ref), p.id, 'in',
       p.qty_on_hand - COALESCE(m.total, 0),
       'OPENING', 'Opening balance', '2026-05-31'
  FROM products p
  LEFT JOIN (SELECT product_id, SUM(qty) total FROM stock_movements GROUP BY product_id) m
         ON m.product_id = p.id
 WHERE p.deleted_at IS NULL
   AND p.qty_on_hand - COALESCE(m.total, 0) <> 0;
