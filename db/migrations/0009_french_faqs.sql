-- 0009_french_faqs.sql
-- French for the FAQ and the stylist biographies.
--
-- The FAQ is where a client goes when they are hesitating, so leaving it in
-- English is exactly the wrong half to leave untranslated: the answers about
-- deposits, lateness and bringing your own hair are the ones that decide
-- whether someone books at all.

INSERT INTO translations (source_table, source_id, field, locale, value) VALUES
('faqs', 1, 'question', 'fr', 'Faut-il un rendez-vous ou puis-je venir sans réserver ?'),
('faqs', 1, 'answer', 'fr', 'Les clientes ayant réservé passent toujours en premier, et le samedi est presque toujours complet. Nous acceptons les visites spontanées lorsqu''il y a une vraie disponibilité, mais nous ne pouvons rien promettre — réserver en ligne prend environ deux minutes et garantit votre fauteuil.'),
('faqs', 2, 'question', 'fr', 'Combien de temps à l''avance puis-je réserver ?'),
('faqs', 2, 'answer', 'fr', 'Trois semaines. Si vous avez besoin d''une date plus lointaine — un mariage, un voyage — écrivez-nous sur WhatsApp et nous l''inscrirons manuellement dans l''agenda.'),
('faqs', 3, 'question', 'fr', 'Puis-je réserver plusieurs prestations à la fois ?'),
('faqs', 3, 'answer', 'fr', 'Oui. Sélectionnez tout ce que vous souhaitez à la première étape de la réservation et nous bloquerons assez de temps pour l''ensemble. Lorsque deux prestations peuvent se faire en même temps, comme les tresses et les ongles, nous vous attribuons deux spécialistes.'),
('faqs', 4, 'question', 'fr', 'Comment modifier ou annuler ?'),
('faqs', 4, 'answer', 'fr', 'Utilisez « Gérer ma réservation » avec votre code de réservation, ou appelez le salon. L''annulation est gratuite jusqu''à quatre heures avant votre créneau.'),
('faqs', 5, 'question', 'fr', 'Quels moyens de paiement acceptez-vous ?'),
('faqs', 5, 'answer', 'fr', 'Espèces, MTN Mobile Money, Orange Money et carte bancaire. Vous réglez au fauteuil après votre prestation — rien n''est débité lors de la réservation en ligne.'),
('faqs', 6, 'question', 'fr', 'Dois-je verser un acompte ?'),
('faqs', 6, 'answer', 'fr', 'Uniquement pour les prestations de plus de 20 000 FCFA, et surtout le samedi. Il est de 30 % par MoMo et vient en déduction de votre note finale.'),
('faqs', 7, 'question', 'fr', 'Vos prix sont-ils définitifs ?'),
('faqs', 7, 'answer', 'fr', 'Le prix affiché sur le site est celui que vous payez. Si une coiffure demande plus de produit ou plus de temps, nous vous faisons un devis avant de commencer — jamais après.'),
('faqs', 8, 'question', 'fr', 'Dois-je apporter mes propres mèches pour les tresses ?'),
('faqs', 8, 'answer', 'fr', 'Le prix des tresses couvre la main-d''œuvre, vous pouvez donc apporter les vôtres. Nous vendons aussi du Kanekalon et des mèches pré-étirées au comptoir si c''est plus simple.'),
('faqs', 9, 'question', 'fr', 'Combien de temps prennent les tresses sans nœuds ?'),
('faqs', 9, 'answer', 'fr', 'Environ quatre heures pour une tête complète en longueur moyenne. Plus long ou plus fin demande davantage — le parcours de réservation affiche la durée exacte au moment où vous choisissez la prestation.'),
('faqs', 10, 'question', 'fr', 'Le défrisage ou le lissage abîme-t-il mes cheveux ?'),
('faqs', 10, 'answer', 'fr', 'Pas de la façon dont nous le faisons. Nous utilisons une protection thermique à chaque lissage et nous vous dirons honnêtement si une coiffure va abîmer vos cheveux plutôt que de simplement encaisser.'),
('faqs', 11, 'question', 'fr', 'Que se passe-t-il si je suis en retard ?'),
('faqs', 11, 'answer', 'fr', 'Écrivez-nous sur WhatsApp. Nous gardons votre fauteuil pendant 15 minutes ; au-delà, le créneau peut être attribué à quelqu''un d''autre et la réservation est notée comme absence.'),
('faqs', 12, 'question', 'fr', 'Puis-je venir avec mes enfants ?'),
('faqs', 12, 'answer', 'fr', 'Bien sûr — et nous les coiffons aussi. Les tresses enfants sont une prestation à part entière : réservez-leur un créneau plutôt que de les ajouter le jour même.'),
('faqs', 13, 'question', 'fr', 'Y a-t-il un parking ?'),
('faqs', 13, 'answer', 'fr', 'Oui, le long de la route principale juste devant. Nous sommes au premier étage — cherchez l''enseigne à la couronne dorée au-dessus de l''entrée.')
ON DUPLICATE KEY UPDATE value = VALUES(value);
