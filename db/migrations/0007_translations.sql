-- 0007_translations.sql
-- French alongside English.
--
-- Two kinds of text need translating and only one of them lives here. The
-- copy written into the templates is translated from files under lang/; this
-- table holds the other kind — what the salon typed into the admin panel, and
-- therefore cannot be known in advance.
--
-- Keyed by (table, row, field, locale) rather than by adding a name_fr column
-- to every table, because the alternative is a schema change every time
-- another field turns out to need translating, and a NULL in most of them.
-- A missing row is not an error: the reader gets the original text.

CREATE TABLE IF NOT EXISTS translations (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  source_table  VARCHAR(64)  NOT NULL,
  source_id     INT UNSIGNED NOT NULL,
  field         VARCHAR(64)  NOT NULL,
  locale        VARCHAR(8)   NOT NULL,
  value         TEXT         NOT NULL,
  updated_at    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_translation (source_table, source_id, field, locale),
  INDEX idx_translation_locale (locale)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
