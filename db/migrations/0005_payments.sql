-- 0005_payments.sql
-- Mobile money collection.
--
-- Every attempt is recorded, including the ones that fail or are never
-- approved. A client who says "I paid" must be answerable from this table
-- without asking MTN, so nothing is deleted and nothing is overwritten in
-- place except the status of the attempt it belongs to.

CREATE TABLE IF NOT EXISTS payments (
  id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  ref             VARCHAR(20)     NOT NULL,

  appointment_id  INT UNSIGNED    NULL,
  client_id       INT UNSIGNED    NULL,

  provider        ENUM('momo','om','cash','card') NOT NULL,
  purpose         ENUM('deposit','balance','sale') NOT NULL DEFAULT 'deposit',

  -- What we asked for, in the salon's currency. Never recomputed from a rate.
  amount          DECIMAL(12,2)   NOT NULL,
  currency        VARCHAR(8)      NOT NULL DEFAULT 'XAF',

  -- The handset billed. Kept separate from the client's contact number
  -- because people routinely pay from a spouse's or a colleague's phone.
  payer_phone     VARCHAR(32)     NOT NULL,

  status          ENUM('pending','succeeded','failed','cancelled','timeout')
                                  NOT NULL DEFAULT 'pending',

  -- Our idempotency key, sent to the provider as their reference. Unique so a
  -- double-submitted booking cannot raise two charges.
  external_id     VARCHAR(64)     NOT NULL,
  -- Whatever the provider calls the transaction on their side, once known.
  provider_ref    VARCHAR(80)     NULL,
  -- Provider's own reason string when it fails, verbatim, for disputes.
  failure_reason  VARCHAR(255)    NULL,
  -- Last raw response, for when the reason string is not enough.
  last_response   TEXT            NULL,

  requested_at    TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  settled_at      TIMESTAMP       NULL,
  updated_at      TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP
                                  ON UPDATE CURRENT_TIMESTAMP,

  PRIMARY KEY (id),
  UNIQUE KEY uq_payments_ref (ref),
  UNIQUE KEY uq_payments_external (external_id),
  KEY ix_payments_appointment (appointment_id),
  KEY ix_payments_status (status, requested_at),
  KEY ix_payments_provider (provider, requested_at),

  CONSTRAINT fk_payments_appointment FOREIGN KEY (appointment_id)
    REFERENCES appointments (id) ON DELETE SET NULL,
  CONSTRAINT fk_payments_client FOREIGN KEY (client_id)
    REFERENCES clients (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- What the appointment itself needs to know about money taken up front.
-- deposit_paid is a cache of SUM(payments.amount WHERE succeeded); the
-- payments table remains the record of truth, exactly as stock_movements is
-- for products.
ALTER TABLE appointments
  ADD COLUMN deposit_due  DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER grand_total,
  ADD COLUMN deposit_paid DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER deposit_due;

-- Off by default. A salon that has not bought an API subscription must not
-- have its booking page asking for money it cannot collect.
INSERT INTO settings (`key`, `value`) VALUES
  ('payments_enabled', '0'),
  ('payments_require_deposit', '0')
ON DUPLICATE KEY UPDATE `key` = `key`;
