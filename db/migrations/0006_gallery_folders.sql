-- 0006_gallery_folders.sql
-- The gallery becomes a mirror of the filesystem.
--
-- Photos are files, not rows. The database never stores an image, only the
-- directory it lives in and the name of the file inside it, so the full path
-- is derived at read time:  assets/img/<folder>/<filename>.
--
-- The folder is also what a photo *is*. `assets/img/lashes/p3.jpeg` belongs to
-- the lashes set because of where it sits, not because someone tagged it, so
-- the filter chips and the bookable category both hang off gallery_folders
-- rather than off each individual row. Dropping a new photo into a folder and
-- running `npm run images` is the entire publishing workflow.
--
-- category_id is nullable on purpose: the salon photographs work it does not
-- yet sell as a bookable service (makeup). Those folders show in the gallery
-- and simply have no "book this look" target.

CREATE TABLE IF NOT EXISTS gallery_folders (
  id           INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  folder       VARCHAR(64)  NOT NULL UNIQUE,          -- directory under assets/img/
  slug         VARCHAR(32)  NOT NULL UNIQUE,          -- filter chip value
  label        VARCHAR(64)  NOT NULL,
  category_id  INT UNSIGNED NULL,                     -- bookable category, when one exists
  role         ENUM('gallery','background','team') NOT NULL DEFAULT 'gallery',
  tone         VARCHAR(16)  NOT NULL DEFAULT 'ph',    -- gradient shown while a file is missing
  is_public    TINYINT(1)   NOT NULL DEFAULT 1,
  sort_order   INT          NOT NULL DEFAULT 0,
  CONSTRAINT fk_gf_cat FOREIGN KEY (category_id)
    REFERENCES service_categories(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO gallery_folders (folder, slug, label, category_id, role, tone, is_public, sort_order) VALUES
  ('hair-styles', 'hair-styles', 'Braids & Styles', (SELECT id FROM service_categories WHERE slug = 'braids'),  'gallery',    'ph-pink',  1, 0),
  ('haircut',     'haircut',     'Cuts & Styling', (SELECT id FROM service_categories WHERE slug = 'hair'),    'gallery',    'ph-gold',  1, 1),
  ('makeup',      'makeup',      'Makeup',          NULL,                                                       'gallery',    'ph',       1, 2),
  ('manicure',    'manicure',    'Manicure',        (SELECT id FROM service_categories WHERE slug = 'nails'),   'gallery',    'ph-pink',  1, 3),
  ('pedicure',    'pedicure',    'Pedicure',        (SELECT id FROM service_categories WHERE slug = 'nails'),   'gallery',    'ph-cream', 1, 4),
  ('lashes',      'lashes',      'Lashes & Brows',  (SELECT id FROM service_categories WHERE slug = 'facials'), 'gallery',    'ph',       1, 5),
  ('massage',     'massage',     'Massage & Spa',   (SELECT id FROM service_categories WHERE slug = 'massage'), 'gallery',    'ph-cream', 1, 6),
  ('backgrounds', 'backgrounds', 'Backgrounds',     NULL,                                                       'background', 'ph',       0, 7),
  ('team',        'team',        'The Team',        NULL,                                                       'team',       'ph-gold',  0, 8);

-- The sixteen seeded gallery rows were captions with no photo behind them.
-- Real files replace them; the scanner owns this table from here on.
DELETE FROM gallery;

ALTER TABLE gallery
  DROP FOREIGN KEY fk_gal_cat;

ALTER TABLE gallery
  DROP COLUMN category_id,
  DROP COLUMN image_path,
  ADD COLUMN folder_id INT UNSIGNED NOT NULL AFTER title,
  ADD COLUMN filename  VARCHAR(160) NOT NULL AFTER folder_id,
  ADD CONSTRAINT fk_gal_folder FOREIGN KEY (folder_id)
    REFERENCES gallery_folders(id) ON DELETE CASCADE,
  ADD UNIQUE KEY uq_gal_file (folder_id, filename);

-- Staff photos follow the same rule: `staff.photo` holds a bare filename that
-- lives in assets/img/team/, never a path and never an upload blob.
ALTER TABLE staff
  MODIFY COLUMN photo VARCHAR(160) NULL COMMENT 'filename inside assets/img/team/';
