-- 0008_french_content.sql
-- French for the catalogue: the words a client actually chooses from.
--
-- Service and category text is the one part of the site a francophone client
-- cannot work around — you can guess what "Gallery" means from the pictures,
-- but not what "Silk Press" costs you or how long "Knotless Box Braids" takes.
--
-- Written as (table, row, field) rows rather than name_fr columns, so adding
-- another language later is data, not a migration. Idempotent: re-running
-- updates the text rather than colliding on the unique key.

INSERT INTO translations (source_table, source_id, field, locale, value) VALUES
-- ------------------------------------------------------------- categories
('service_categories', 1, 'name', 'fr', 'Coupes & Coiffage'),
('service_categories', 1, 'short_name', 'fr', 'Coiffure'),
('service_categories', 1, 'blurb', 'fr', 'Coupes précises, lissages, brushings et coiffures de gala pour tous les types de cheveux.'),
('service_categories', 2, 'name', 'fr', 'Tresses & Nattes'),
('service_categories', 2, 'short_name', 'fr', 'Tresses'),
('service_categories', 2, 'blurb', 'fr', 'Tresses sans nœuds, nattes collées, twists et coiffures protectrices qui tiennent des semaines.'),
('service_categories', 3, 'name', 'fr', 'Ongles & Nail Art'),
('service_categories', 3, 'short_name', 'fr', 'Ongles'),
('service_categories', 3, 'blurb', 'fr', 'Manucures, pédicures, gel, acrylique et nail art peint à la main.'),
('service_categories', 4, 'name', 'fr', 'Soins du visage & Cils'),
('service_categories', 4, 'short_name', 'fr', 'Visage'),
('service_categories', 4, 'blurb', 'fr', 'Soins nettoyants en profondeur, restructuration des sourcils, teinture et extensions de cils du classique au volume.'),
('service_categories', 5, 'name', 'fr', 'Massage & Spa'),
('service_categories', 5, 'short_name', 'fr', 'Spa'),
('service_categories', 5, 'blurb', 'fr', 'Massage relaxant du corps entier, thérapie aux pierres chaudes et gommages.'),

-- --------------------------------------------------------------- services
('services', 1, 'name', 'fr', 'Shampooing & Brushing Signature'),
('services', 1, 'description', 'fr', 'Nettoyage en profondeur, soin et brushing souple et volumineux.'),
('services', 2, 'name', 'fr', 'Coupe & Coiffage sur mesure'),
('services', 2, 'description', 'fr', 'Consultation, coupe structurante et coiffage de finition.'),
('services', 3, 'name', 'fr', 'Lissage Silk Press'),
('services', 3, 'description', 'fr', 'Lissage impeccable, sans frisottis, avec protection thermique.'),
('services', 4, 'name', 'fr', 'Défrisage / Retouche'),
('services', 4, 'description', 'fr', 'Application douce du défrisant avec protection du cuir chevelu.'),
('services', 5, 'name', 'fr', 'Coiffure Mariée & Événement'),
('services', 5, 'description', 'fr', 'Chignon glamour complet avec accessoires pour votre grand jour.'),
('services', 6, 'name', 'fr', 'Tresses sans nœuds (Knotless)'),
('services', 6, 'description', 'fr', 'Tresses légères sans nœuds, longueur moyenne incluse.'),
('services', 7, 'name', 'fr', 'Nattes collées / Ghana Weaving'),
('services', 7, 'description', 'fr', 'Nattes collées classiques ou à motifs, finition nette.'),
('services', 8, 'name', 'fr', 'Twist Out / Passion Twists'),
('services', 8, 'description', 'fr', 'Passion twists souples et fournies, à la finition naturelle.'),
('services', 9, 'name', 'fr', 'Tresses Enfants'),
('services', 9, 'description', 'fr', 'Coiffage tout en douceur et en patience pour les petites princesses.'),
('services', 10, 'name', 'fr', 'Pose & Coiffage de Perruque'),
('services', 10, 'description', 'fr', 'Pose lace invisible, personnalisation et coiffage.'),
('services', 11, 'name', 'fr', 'Manucure Classique'),
('services', 11, 'description', 'fr', 'Mise en forme, soin des cuticules, polissage et vernis.'),
('services', 12, 'name', 'fr', 'Pédicure Spa'),
('services', 12, 'description', 'fr', 'Bain, gommage, soin des callosités, massage et vernis.'),
('services', 13, 'name', 'fr', 'Pose de Gel'),
('services', 13, 'description', 'fr', 'Couleur gel longue tenue, brillance intense.'),
('services', 14, 'name', 'fr', 'Pose complète Acrylique'),
('services', 14, 'description', 'fr', 'Extensions en acrylique sculptées, toutes longueurs.'),
('services', 15, 'name', 'fr', 'Nail Art personnalisé (la pose)'),
('services', 15, 'description', 'fr', 'Motifs peints à la main, chrome, strass et French.'),
('services', 16, 'name', 'fr', 'Soin Nettoyant Profond'),
('services', 16, 'description', 'fr', 'Nettoyage, exfoliation, extraction, masque et hydratation.'),
('services', 17, 'name', 'fr', 'Soin Éclat Illuminateur'),
('services', 17, 'description', 'fr', 'Traitement à la vitamine C pour un teint uniforme et lumineux.'),
('services', 18, 'name', 'fr', 'Extensions de Cils Classiques'),
('services', 18, 'description', 'fr', 'Pose naturelle cil à cil, tient 3 à 4 semaines.'),
('services', 19, 'name', 'fr', 'Pose de Cils Volume'),
('services', 19, 'description', 'fr', 'Bouquets volume fournis et aériens, effet maximal.'),
('services', 20, 'name', 'fr', 'Restructuration & Teinture Sourcils'),
('services', 20, 'description', 'fr', 'Fil ou cire, plus teinture pour encadrer votre visage.'),
('services', 21, 'name', 'fr', 'Relaxation Corps Entier'),
('services', 21, 'description', 'fr', 'Massage suédois du corps entier pour tout relâcher.'),
('services', 22, 'name', 'fr', 'Thérapie aux Pierres Chaudes'),
('services', 22, 'description', 'fr', 'Pierres de basalte chauffées pour dissoudre les tensions profondes.'),
('services', 23, 'name', 'fr', 'Dos, Nuque & Épaules'),
('services', 23, 'description', 'fr', 'Pression ciblée là où le stress pèse le plus.'),
('services', 24, 'name', 'fr', 'Gommage & Soin du Corps'),
('services', 24, 'description', 'fr', 'Gommage exfoliant suivi d’une finition au beurre de karité.')
ON DUPLICATE KEY UPDATE value = VALUES(value);
