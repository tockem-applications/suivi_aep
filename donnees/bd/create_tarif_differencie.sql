-- Table pour les tarifs différenciés par intervalle de consommation
-- Cette table est optionnelle et n'affecte pas le fonctionnement actuel du système

CREATE TABLE IF NOT EXISTS `tarif_differencie` (
  `id` int(5) unsigned NOT NULL AUTO_INCREMENT,
  `id_constante_reseau` int(2) unsigned NOT NULL,
  `prix_metre_cube_eau` int(5) unsigned NOT NULL,
  `prix_entretient_compteur` int(5) unsigned NOT NULL,
  `prix_tva` decimal(7,2) unsigned NOT NULL,
  `min_consommation` decimal(10,2) unsigned NOT NULL DEFAULT '0.00',
  `max_consommation` decimal(10,2) unsigned DEFAULT NULL,
  `date_creation` date NOT NULL,
  `description` text,
  PRIMARY KEY (`id`),
  KEY `fk_constante_tarif_differencie` (`id_constante_reseau`),
  CONSTRAINT `fk_constante_tarif_differencie` FOREIGN KEY (`id_constante_reseau`) REFERENCES `constante_reseau` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
