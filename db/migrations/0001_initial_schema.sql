-- =============================================================================
-- Princess Beauty Salon — initial schema
--
-- Conventions used throughout:
--   * InnoDB + utf8mb4 everywhere (client names carry accents: Aïcha, Nadège).
--   * Money is DECIMAL(12,2), never FLOAT. FCFA has no minor unit in practice
--     but decimals keep commission maths exact.
--   * `deleted_at` = soft delete. A service whose price changed or which was
--     retired must never vanish from historical revenue.
--   * Prices are COPIED onto appointment_items at booking time. If the salon
--     raises the price of braids, last month's takings must not change.
--   * Times are stored as DATE + TIME, not DATETIME, because the diary is
--     reasoned about per-day and per-slot.
-- =============================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ------------------------------------------------------------------ settings
CREATE TABLE IF NOT EXISTS settings (
  `key`        VARCHAR(64)  NOT NULL PRIMARY KEY,
  `value`      TEXT         NULL,
  updated_at   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Opening hours, one row per weekday (0 = Monday .. 6 = Sunday)
CREATE TABLE IF NOT EXISTS business_hours (
  weekday      TINYINT UNSIGNED NOT NULL PRIMARY KEY,
  day_name     VARCHAR(16)      NOT NULL,
  opens_at     TIME             NULL,
  closes_at    TIME             NULL,
  is_closed    TINYINT(1)       NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------ roles & users
CREATE TABLE IF NOT EXISTS roles (
  id           INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  slug         VARCHAR(32)  NOT NULL UNIQUE,
  name         VARCHAR(64)  NOT NULL,
  description  VARCHAR(255) NULL,
  is_system    TINYINT(1)   NOT NULL DEFAULT 1,
  created_at   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- One row per reachable area of the admin panel.
CREATE TABLE IF NOT EXISTS permissions (
  id           INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  slug         VARCHAR(48)  NOT NULL UNIQUE,
  name         VARCHAR(96)  NOT NULL,
  area         VARCHAR(32)  NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS role_permissions (
  role_id        INT UNSIGNED NOT NULL,
  permission_id  INT UNSIGNED NOT NULL,
  PRIMARY KEY (role_id, permission_id),
  CONSTRAINT fk_rp_role FOREIGN KEY (role_id)       REFERENCES roles(id)       ON DELETE CASCADE,
  CONSTRAINT fk_rp_perm FOREIGN KEY (permission_id) REFERENCES permissions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- `staff` is a PERSON and their craft. `users` is a LOGIN.
-- They are deliberately separate: a receptionist has a login but no chair,
-- and a stylist exists in the diary before they ever sign in.
CREATE TABLE IF NOT EXISTS staff (
  id             INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  ref            VARCHAR(12)  NOT NULL UNIQUE,          -- ST-01, shown in the UI
  name           VARCHAR(128) NOT NULL,
  job_title      VARCHAR(96)  NOT NULL,
  bio            TEXT         NULL,
  phone          VARCHAR(32)  NULL,
  photo          VARCHAR(255) NULL,
  commission_pct DECIMAL(5,2) NOT NULL DEFAULT 0.00,
  rating         DECIMAL(3,2) NOT NULL DEFAULT 5.00,
  reviews_count  INT UNSIGNED NOT NULL DEFAULT 0,
  working_hours  VARCHAR(96)  NULL,                     -- display string
  since_year     VARCHAR(8)   NULL,
  tone           ENUM('gold','pink') NOT NULL DEFAULT 'gold',
  is_public      TINYINT(1)   NOT NULL DEFAULT 1,       -- show on the Stylists page
  sort_order     INT          NOT NULL DEFAULT 0,
  created_at     TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at     TIMESTAMP    NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  deleted_at     TIMESTAMP    NULL DEFAULT NULL,
  INDEX idx_staff_active (deleted_at, is_public)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS users (
  id             INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  ref            VARCHAR(12)  NOT NULL UNIQUE,          -- U-01
  staff_id       INT UNSIGNED NULL,                     -- NULL = office account, no chair
  role_id        INT UNSIGNED NOT NULL,
  name           VARCHAR(128) NOT NULL,
  email          VARCHAR(190) NOT NULL UNIQUE,
  password_hash  VARCHAR(255) NULL,                     -- NULL until an invite is accepted
  status         ENUM('active','invited','suspended') NOT NULL DEFAULT 'invited',
  two_factor     TINYINT(1)   NOT NULL DEFAULT 0,
  last_login_at  DATETIME     NULL,
  created_at     TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at     TIMESTAMP    NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  deleted_at     TIMESTAMP    NULL DEFAULT NULL,
  -- RESTRICT, not CASCADE: deleting a stylist must never silently destroy the
  -- login that owns their audit trail.
  CONSTRAINT fk_users_staff FOREIGN KEY (staff_id) REFERENCES staff(id) ON DELETE RESTRICT,
  CONSTRAINT fk_users_role  FOREIGN KEY (role_id)  REFERENCES roles(id) ON DELETE RESTRICT,
  INDEX idx_users_status (status, deleted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Brute-force throttling, by email and by IP.
CREATE TABLE IF NOT EXISTS login_attempts (
  id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  identifier   VARCHAR(190) NOT NULL,        -- the email that was tried
  ip           VARCHAR(45)  NULL,
  successful   TINYINT(1)   NOT NULL DEFAULT 0,
  attempted_at DATETIME     NOT NULL,
  INDEX idx_attempt (identifier, attempted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------- services catalogue
CREATE TABLE IF NOT EXISTS service_categories (
  id           INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  slug         VARCHAR(32)  NOT NULL UNIQUE,            -- hair, braids, nails…
  name         VARCHAR(96)  NOT NULL,
  short_name   VARCHAR(32)  NOT NULL,
  icon         VARCHAR(32)  NOT NULL,                   -- key into the JS icon set
  color        VARCHAR(7)   NOT NULL,                   -- chart colour, see charts.js
  blurb        VARCHAR(255) NULL,
  sort_order   INT          NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS services (
  id               INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  ref              VARCHAR(12)  NOT NULL UNIQUE,        -- SV-01
  category_id      INT UNSIGNED NOT NULL,
  name             VARCHAR(160) NOT NULL,
  description      TEXT         NULL,
  price            DECIMAL(12,2) NOT NULL,
  duration_minutes SMALLINT UNSIGNED NOT NULL,
  is_popular       TINYINT(1)   NOT NULL DEFAULT 0,
  is_public        TINYINT(1)   NOT NULL DEFAULT 1,
  sort_order       INT          NOT NULL DEFAULT 0,
  created_at       TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at       TIMESTAMP    NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  deleted_at       TIMESTAMP    NULL DEFAULT NULL,
  CONSTRAINT fk_services_cat FOREIGN KEY (category_id) REFERENCES service_categories(id) ON DELETE RESTRICT,
  INDEX idx_services_live (deleted_at, is_public, category_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Which stylist may be booked for which category.
CREATE TABLE IF NOT EXISTS staff_categories (
  staff_id     INT UNSIGNED NOT NULL,
  category_id  INT UNSIGNED NOT NULL,
  PRIMARY KEY (staff_id, category_id),
  CONSTRAINT fk_sc_staff FOREIGN KEY (staff_id)    REFERENCES staff(id)              ON DELETE CASCADE,
  CONSTRAINT fk_sc_cat   FOREIGN KEY (category_id) REFERENCES service_categories(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Weekly rota. Absence of a row means the stylist does not work that day.
CREATE TABLE IF NOT EXISTS staff_hours (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  staff_id    INT UNSIGNED NOT NULL,
  weekday     TINYINT UNSIGNED NOT NULL,               -- 0 = Monday
  starts_at   TIME NOT NULL,
  ends_at     TIME NOT NULL,
  UNIQUE KEY uq_staff_day (staff_id, weekday),
  CONSTRAINT fk_sh_staff FOREIGN KEY (staff_id) REFERENCES staff(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------------- inventory
CREATE TABLE IF NOT EXISTS products (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  ref           VARCHAR(12)  NOT NULL UNIQUE,           -- P-01
  name          VARCHAR(160) NOT NULL,
  category_id   INT UNSIGNED NULL,                      -- NULL = general supplies
  unit          VARCHAR(32)  NOT NULL DEFAULT 'unit',
  qty_on_hand   INT          NOT NULL DEFAULT 0,        -- signed: negative reveals mistakes
  reorder_level INT UNSIGNED NOT NULL DEFAULT 0,
  unit_cost     DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  retail_price  DECIMAL(12,2) NOT NULL DEFAULT 0.00,    -- 0 = not sold to clients
  is_retail     TINYINT(1)   NOT NULL DEFAULT 0,
  supplier      VARCHAR(160) NULL,
  created_at    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at    TIMESTAMP    NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  deleted_at    TIMESTAMP    NULL DEFAULT NULL,
  CONSTRAINT fk_products_cat FOREIGN KEY (category_id) REFERENCES service_categories(id) ON DELETE SET NULL,
  INDEX idx_products_live (deleted_at, is_retail)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- What a service consumes, so completing it can deduct stock automatically.
CREATE TABLE IF NOT EXISTS service_products (
  service_id   INT UNSIGNED NOT NULL,
  product_id   INT UNSIGNED NOT NULL,
  qty_used     DECIMAL(8,2) NOT NULL DEFAULT 1.00,
  PRIMARY KEY (service_id, product_id),
  CONSTRAINT fk_sp_service FOREIGN KEY (service_id) REFERENCES services(id) ON DELETE CASCADE,
  CONSTRAINT fk_sp_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------- clients
CREATE TABLE IF NOT EXISTS clients (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  ref           VARCHAR(12)  NOT NULL UNIQUE,           -- CL-001
  name          VARCHAR(128) NOT NULL,
  phone         VARCHAR(32)  NOT NULL,
  email         VARCHAR(190) NULL,
  notes         TEXT         NULL,
  fav_category_id INT UNSIGNED NULL,
  fav_staff_id  INT UNSIGNED NULL,
  marketing_opt_in TINYINT(1) NOT NULL DEFAULT 0,
  joined_on     DATE         NOT NULL,
  created_at    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at    TIMESTAMP    NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  deleted_at    TIMESTAMP    NULL DEFAULT NULL,
  CONSTRAINT fk_clients_cat   FOREIGN KEY (fav_category_id) REFERENCES service_categories(id) ON DELETE SET NULL,
  CONSTRAINT fk_clients_staff FOREIGN KEY (fav_staff_id)    REFERENCES staff(id)              ON DELETE SET NULL,
  INDEX idx_clients_phone (phone),
  INDEX idx_clients_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------ appointments
CREATE TABLE IF NOT EXISTS appointments (
  id             INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  ref            VARCHAR(16)  NOT NULL UNIQUE,          -- PBS-4821, given to the client
  client_id      INT UNSIGNED NOT NULL,
  staff_id       INT UNSIGNED NULL,                     -- NULL = "any available"
  booked_on      DATE         NOT NULL,
  starts_at      TIME         NOT NULL,
  ends_at        TIME         NOT NULL,
  duration_minutes SMALLINT UNSIGNED NOT NULL,
  status         ENUM('pending','confirmed','completed','cancelled','no-show')
                 NOT NULL DEFAULT 'pending',
  payment_method ENUM('cash','momo','om','card','unpaid') NOT NULL DEFAULT 'unpaid',
  paid_at        DATETIME     NULL,
  -- money is denormalised here on purpose: these are the figures at the time
  -- of sale and must not move when the price list changes
  services_total DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  addons_total   DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  tip_total      DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  grand_total    DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  note           TEXT         NULL,
  source         ENUM('online','walk-in','phone','admin') NOT NULL DEFAULT 'online',
  reminder_opt_in TINYINT(1)  NOT NULL DEFAULT 1,
  created_at     TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at     TIMESTAMP    NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  cancelled_at   TIMESTAMP    NULL DEFAULT NULL,
  CONSTRAINT fk_appt_client FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE RESTRICT,
  CONSTRAINT fk_appt_staff  FOREIGN KEY (staff_id)  REFERENCES staff(id)   ON DELETE SET NULL,
  INDEX idx_appt_day    (booked_on, starts_at),
  INDEX idx_appt_staff  (staff_id, booked_on),
  INDEX idx_appt_status (status, booked_on),
  INDEX idx_appt_client (client_id, booked_on)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- One row per service on the booking. Price is copied, not referenced.
CREATE TABLE IF NOT EXISTS appointment_items (
  id               INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  appointment_id   INT UNSIGNED NOT NULL,
  service_id       INT UNSIGNED NULL,                   -- SET NULL if a service is purged
  service_name     VARCHAR(160) NOT NULL,               -- frozen label
  unit_price       DECIMAL(12,2) NOT NULL,              -- frozen price
  duration_minutes SMALLINT UNSIGNED NOT NULL,
  qty              SMALLINT UNSIGNED NOT NULL DEFAULT 1,
  line_total       DECIMAL(12,2) NOT NULL,
  CONSTRAINT fk_ai_appt    FOREIGN KEY (appointment_id) REFERENCES appointments(id) ON DELETE CASCADE,
  CONSTRAINT fk_ai_service FOREIGN KEY (service_id)     REFERENCES services(id)     ON DELETE SET NULL,
  INDEX idx_ai_service (service_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------- retail sales (counter POS)
CREATE TABLE IF NOT EXISTS product_sales (
  id             INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  ref            VARCHAR(16)  NOT NULL UNIQUE,          -- POS-118
  client_id      INT UNSIGNED NULL,                     -- NULL = passing trade
  sold_by        INT UNSIGNED NULL,                     -- staff id
  sold_on        DATE         NOT NULL,
  payment_method ENUM('cash','momo','om','card') NOT NULL DEFAULT 'cash',
  total          DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  created_at     TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_ps_client FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE SET NULL,
  CONSTRAINT fk_ps_staff  FOREIGN KEY (sold_by)   REFERENCES staff(id)   ON DELETE SET NULL,
  INDEX idx_ps_day (sold_on)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS product_sale_items (
  id           INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  sale_id      INT UNSIGNED NOT NULL,
  product_id   INT UNSIGNED NULL,
  product_name VARCHAR(160) NOT NULL,                   -- frozen label
  unit_price   DECIMAL(12,2) NOT NULL,                  -- frozen price
  qty          INT UNSIGNED NOT NULL DEFAULT 1,
  line_total   DECIMAL(12,2) NOT NULL,
  CONSTRAINT fk_psi_sale    FOREIGN KEY (sale_id)    REFERENCES product_sales(id) ON DELETE CASCADE,
  CONSTRAINT fk_psi_product FOREIGN KEY (product_id) REFERENCES products(id)      ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------- stock movements
-- Append-only ledger. products.qty_on_hand is the running cache; this is the
-- truth, and the two can be reconciled at any time.
CREATE TABLE IF NOT EXISTS stock_movements (
  id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  ref          VARCHAR(16)  NOT NULL UNIQUE,            -- SM-231
  product_id   INT UNSIGNED NOT NULL,
  movement_type ENUM('in','out','sale','adjust') NOT NULL,
  qty          INT          NOT NULL,                   -- signed: + adds, - removes
  reference    VARCHAR(32)  NULL,                       -- PO-045, AP-1042, POS-118
  note         VARCHAR(255) NULL,
  staff_id     INT UNSIGNED NULL,
  moved_on     DATE         NOT NULL,
  created_at   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_sm_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
  CONSTRAINT fk_sm_staff   FOREIGN KEY (staff_id)   REFERENCES staff(id)    ON DELETE SET NULL,
  INDEX idx_sm_product (product_id, moved_on)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------- expenses
CREATE TABLE IF NOT EXISTS expense_categories (
  id         INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  slug       VARCHAR(32) NOT NULL UNIQUE,
  name       VARCHAR(64) NOT NULL,
  color      VARCHAR(7)  NOT NULL DEFAULT '#77777F',
  is_fixed   TINYINT(1)  NOT NULL DEFAULT 0             -- recurs monthly
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS expenses (
  id             INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  ref            VARCHAR(16)  NOT NULL UNIQUE,          -- EX-088
  category_id    INT UNSIGNED NOT NULL,
  description    VARCHAR(255) NOT NULL,
  amount         DECIMAL(12,2) NOT NULL,
  payment_method ENUM('cash','momo','om','card') NOT NULL DEFAULT 'cash',
  spent_on       DATE         NOT NULL,
  recorded_by    INT UNSIGNED NULL,
  is_recurring   TINYINT(1)   NOT NULL DEFAULT 0,
  created_at     TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  deleted_at     TIMESTAMP    NULL DEFAULT NULL,
  CONSTRAINT fk_exp_cat   FOREIGN KEY (category_id) REFERENCES expense_categories(id) ON DELETE RESTRICT,
  CONSTRAINT fk_exp_staff FOREIGN KEY (recorded_by) REFERENCES staff(id)              ON DELETE SET NULL,
  INDEX idx_exp_day (spent_on, deleted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------- marketing / public site
CREATE TABLE IF NOT EXISTS offers (
  id           INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  ref          VARCHAR(12)  NOT NULL UNIQUE,
  title        VARCHAR(160) NOT NULL,
  category_id  INT UNSIGNED NULL,
  blurb        VARCHAR(255) NULL,
  badge        VARCHAR(48)  NULL,
  price        DECIMAL(12,2) NOT NULL DEFAULT 0.00,     -- 0 = "ask in salon"
  was_price    DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  valid_until  VARCHAR(32)  NULL,                       -- free text: a date or "Ongoing"
  is_active    TINYINT(1)   NOT NULL DEFAULT 1,
  sort_order   INT          NOT NULL DEFAULT 0,
  CONSTRAINT fk_offer_cat FOREIGN KEY (category_id) REFERENCES service_categories(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS offer_items (
  offer_id   INT UNSIGNED NOT NULL,
  label      VARCHAR(160) NOT NULL,
  sort_order INT NOT NULL DEFAULT 0,
  PRIMARY KEY (offer_id, label),
  CONSTRAINT fk_oi_offer FOREIGN KEY (offer_id) REFERENCES offers(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS gallery (
  id           INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  ref          VARCHAR(12)  NOT NULL UNIQUE,
  title        VARCHAR(160) NOT NULL,
  category_id  INT UNSIGNED NOT NULL,
  staff_id     INT UNSIGNED NULL,
  image_path   VARCHAR(255) NULL,                       -- NULL → gradient placeholder
  tone         VARCHAR(16)  NOT NULL DEFAULT 'ph',
  is_public    TINYINT(1)   NOT NULL DEFAULT 1,
  sort_order   INT          NOT NULL DEFAULT 0,
  created_at   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_gal_cat   FOREIGN KEY (category_id) REFERENCES service_categories(id) ON DELETE CASCADE,
  CONSTRAINT fk_gal_staff FOREIGN KEY (staff_id)    REFERENCES staff(id)              ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS reviews (
  id           INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  client_name  VARCHAR(128) NOT NULL,
  service_label VARCHAR(160) NULL,
  staff_id     INT UNSIGNED NULL,
  stars        TINYINT UNSIGNED NOT NULL DEFAULT 5,
  body         TEXT         NOT NULL,
  is_public    TINYINT(1)   NOT NULL DEFAULT 1,
  created_at   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_rev_staff FOREIGN KEY (staff_id) REFERENCES staff(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS faqs (
  id         INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  category   VARCHAR(32)  NOT NULL,
  question   VARCHAR(255) NOT NULL,
  answer     TEXT         NOT NULL,
  is_public  TINYINT(1)   NOT NULL DEFAULT 1,
  sort_order INT          NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS contact_messages (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  name        VARCHAR(128) NOT NULL,
  phone       VARCHAR(32)  NOT NULL,
  email       VARCHAR(190) NULL,
  subject     VARCHAR(128) NULL,
  body        TEXT         NOT NULL,
  opt_in      TINYINT(1)   NOT NULL DEFAULT 0,
  is_read     TINYINT(1)   NOT NULL DEFAULT 0,
  created_at  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_msg_unread (is_read, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------- audit log
-- Append-only. Who did what, for accountability across the team.
CREATE TABLE IF NOT EXISTS audit_log (
  id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  user_id     INT UNSIGNED NULL,
  action      VARCHAR(255) NOT NULL,
  entity_type VARCHAR(48)  NULL,
  entity_id   VARCHAR(32)  NULL,
  ip          VARCHAR(45)  NULL,
  created_at  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_audit_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
  INDEX idx_audit_time (created_at),
  INDEX idx_audit_entity (entity_type, entity_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
