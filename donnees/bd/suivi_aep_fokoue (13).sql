-- phpMyAdmin SQL Dump
-- version 3.3.9
-- http://www.phpmyadmin.net
--
-- Serveur: localhost
-- Généré le : Lun 17 Mars 2025 à 13:13
-- Version du serveur: 5.1.53
-- Version de PHP: 5.3.4

SET SQL_MODE="NO_AUTO_VALUE_ON_ZERO";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8 */;

--
-- Base de données: `suivi_aep_fokoue`
--

-- --------------------------------------------------------

--
-- Structure de la table `abone`
--

CREATE TABLE IF NOT EXISTS `abone` (
  `id` int(4) unsigned NOT NULL AUTO_INCREMENT,
  `nom` varchar(128) NOT NULL,
  `numero_telephone` varchar(16) NOT NULL,
  `numero_compte_anticipation` varchar(16) NOT NULL,
  `etat` varchar(10) NOT NULL,
  `rang` int(4) DEFAULT NULL,
  `id_reseau` int(3) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `id_reseau` (`id_reseau`)
) ENGINE=InnoDB  DEFAULT CHARSET=latin1 AUTO_INCREMENT=6 ;

--
-- Contenu de la table `abone`
--

INSERT INTO `abone` (`id`, `nom`, `numero_telephone`, `numero_compte_anticipation`, `etat`, `rang`, `id_reseau`) VALUES
(1, 'Tsafack Nteudem', '6541258745', '13', 'actif', 0, 1),
(2, 'Takendjeu Eddy', '6587451215', '12', 'actif', 0, 1),
(3, 'Tsafack Nteudem', '6541258745', '12', 'actif', 0, 14),
(4, 'Takendjeu Eddy', '658547412', '10', 'actif', 0, 15),
(5, 'Modou Silvestre', '696562321', '10', 'actif', 0, 15);

-- --------------------------------------------------------

--
-- Structure de la table `aep`
--

CREATE TABLE IF NOT EXISTS `aep` (
  `id` int(4) unsigned NOT NULL AUTO_INCREMENT,
  `libele` varchar(64) NOT NULL,
  `description` text,
  `fichier_facture` varchar(64) DEFAULT NULL,
  `date` date DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB  DEFAULT CHARSET=latin1 AUTO_INCREMENT=5 ;

--
-- Contenu de la table `aep`
--

INSERT INTO `aep` (`id`, `libele`, `description`, `fichier_facture`, `date`) VALUES
(1, 'Banki', 'Mon Aep', 'model_fokoue', '2019-02-12'),
(2, 'Tortchu', 'je ne sais pas tro quoi mettre comme commentaire', 'model_nkongzem', '2024-12-12'),
(3, 'aep', 'Mon Aep', 'model_fokoue', '2024-12-12'),
(4, 'Fonah', 'Il faut qu''il grandisse vite', 'model_fokoue', '2024-12-12');

-- --------------------------------------------------------

--
-- Structure de la table `avoir_role`
--

CREATE TABLE IF NOT EXISTS `avoir_role` (
  `id` int(5) unsigned NOT NULL AUTO_INCREMENT,
  `id_role` int(4) unsigned NOT NULL,
  `id_utilisateur` int(4) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_role_avoir_role` (`id_role`),
  KEY `fk_utilisateur_avoir_role` (`id_utilisateur`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 AUTO_INCREMENT=1 ;

--
-- Contenu de la table `avoir_role`
--


-- --------------------------------------------------------

--
-- Structure de la table `compteur`
--

CREATE TABLE IF NOT EXISTS `compteur` (
  `id` int(5) unsigned NOT NULL AUTO_INCREMENT,
  `numero_compteur` varchar(16) NOT NULL,
  `longitude` decimal(12,6) DEFAULT NULL,
  `latitude` decimal(12,6) DEFAULT NULL,
  `derniers_index` decimal(7,2) NOT NULL,
  `description` text,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB  DEFAULT CHARSET=latin1 AUTO_INCREMENT=15 ;

--
-- Contenu de la table `compteur`
--

INSERT INTO `compteur` (`id`, `numero_compteur`, `longitude`, `latitude`, `derniers_index`, `description`) VALUES
(1, '00000020', '0.000000', '0.000000', '35.00', ''),
(2, '12014658', '0.000000', '0.000000', '15.00', ''),
(3, '44ssdousd54', '0.000000', '0.000000', '0.00', ''),
(4, '44ssdousd54', '0.000000', '0.000000', '0.00', ''),
(5, '44ssdousd54', '0.000000', '0.000000', '12.20', ''),
(6, '44ssdousd54', '0.000000', '0.000000', '12.20', ''),
(7, '12321254', '0.000000', '0.000000', '1.20', ''),
(8, '4512', '0.000000', '0.000000', '10.00', ''),
(9, '123456789', '0.000000', '0.000000', '18.00', ''),
(10, '12015425', '0.000000', '0.000000', '5.00', ''),
(11, '00000020', '0.000000', '0.000000', '3.50', ''),
(12, '1020000', '0.000000', '0.000000', '19.60', ''),
(13, '001041', '0.000000', '0.000000', '35.00', ''),
(14, '10154878', '0.000000', '0.000000', '22.80', '');

-- --------------------------------------------------------

--
-- Structure de la table `compteur_abone`
--

CREATE TABLE IF NOT EXISTS `compteur_abone` (
  `id_abone` int(5) unsigned NOT NULL,
  `id_compteur` int(5) unsigned NOT NULL,
  KEY `fk_abone_compteur_abone` (`id_abone`),
  KEY `fk_compteur_compteur_abone` (`id_compteur`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

--
-- Contenu de la table `compteur_abone`
--

INSERT INTO `compteur_abone` (`id_abone`, `id_compteur`) VALUES
(1, 1),
(2, 2),
(3, 11),
(4, 13),
(5, 14);

-- --------------------------------------------------------

--
-- Structure de la table `compteur_aep`
--

CREATE TABLE IF NOT EXISTS `compteur_aep` (
  `id_aep` int(4) unsigned NOT NULL,
  `id_compteur` int(5) unsigned NOT NULL,
  `id_position` int(2) unsigned NOT NULL,
  KEY `fk_position_compteur_aep` (`id_position`),
  KEY `fk_aep_compteur_aep` (`id_aep`),
  KEY `fk_compteur_compteur_aep` (`id_compteur`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

--
-- Contenu de la table `compteur_aep`
--


-- --------------------------------------------------------

--
-- Structure de la table `compteur_reseau`
--

CREATE TABLE IF NOT EXISTS `compteur_reseau` (
  `id_reseau` int(5) unsigned NOT NULL,
  `id_compteur` int(5) unsigned NOT NULL,
  KEY `fk_reseau_compteur_reseau` (`id_reseau`),
  KEY `fk_compteur_compteur_reseau` (`id_compteur`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

--
-- Contenu de la table `compteur_reseau`
--

INSERT INTO `compteur_reseau` (`id_reseau`, `id_compteur`) VALUES
(1, 8),
(2, 9),
(13, 10),
(15, 12);

-- --------------------------------------------------------

--
-- Structure de la table `constante_reseau`
--

CREATE TABLE IF NOT EXISTS `constante_reseau` (
  `id` int(2) unsigned NOT NULL AUTO_INCREMENT,
  `prix_metre_cube_eau` int(5) unsigned NOT NULL,
  `prix_entretient_compteur` int(5) unsigned NOT NULL,
  `prix_tva` decimal(7,2) unsigned NOT NULL,
  `date_creation` date NOT NULL,
  `est_actif` tinyint(1) NOT NULL,
  `description` text,
  `id_aep` int(4) unsigned NOT NULL DEFAULT '1',
  PRIMARY KEY (`id`),
  KEY `fk_aep_constante` (`id_aep`)
) ENGINE=InnoDB  DEFAULT CHARSET=latin1 AUTO_INCREMENT=3 ;

--
-- Contenu de la table `constante_reseau`
--

INSERT INTO `constante_reseau` (`id`, `prix_metre_cube_eau`, `prix_entretient_compteur`, `prix_tva`, `date_creation`, `est_actif`, `description`, `id_aep`) VALUES
(1, 200, 200, '0.00', '0000-00-00', 1, '', 1),
(2, 1000, 0, '0.00', '0000-00-00', 1, 'Ici c''est free, il y''a pas d''entretient compteur.', 4);

-- --------------------------------------------------------

--
-- Structure de la table `facture`
--

CREATE TABLE IF NOT EXISTS `facture` (
  `id` int(4) unsigned NOT NULL AUTO_INCREMENT,
  `id_indexes` int(6) unsigned NOT NULL,
  `montant_verse` decimal(8,2) DEFAULT '0.00',
  `date_paiement` date DEFAULT NULL,
  `penalite` decimal(8,2) DEFAULT '0.00',
  `id_abone` int(4) unsigned NOT NULL,
  `message` text,
  PRIMARY KEY (`id`),
  KEY `id_abone` (`id_abone`),
  KEY `fk_index_facture` (`id_indexes`)
) ENGINE=InnoDB  DEFAULT CHARSET=latin1 AUTO_INCREMENT=40 ;

--
-- Contenu de la table `facture`
--

INSERT INTO `facture` (`id`, `id_indexes`, `montant_verse`, `date_paiement`, `penalite`, `id_abone`, `message`) VALUES
(1, 2, '1220.00', '0000-00-00', '0.00', 1, ''),
(2, 3, '679.00', '0000-00-00', '0.00', 1, ''),
(3, 4, '860.00', '0000-00-00', '0.00', 2, ''),
(4, 7, '860.00', '0000-00-00', '0.00', 1, ''),
(5, 8, '579.00', '0000-00-00', '0.00', 2, ''),
(6, 9, '0.00', '0000-00-00', '0.00', 1, ''),
(7, 10, '0.00', '0000-00-00', '0.00', 2, ''),
(8, 11, '800.00', '0000-00-00', '0.00', 1, ''),
(9, 12, '540.00', '0000-00-00', '0.00', 2, ''),
(10, 13, '0.00', '0000-00-00', '0.00', 1, ''),
(11, 14, '0.00', '0000-00-00', '0.00', 2, ''),
(12, 15, '1000.00', '0000-00-00', '0.00', 1, ''),
(13, 16, '600.00', '0000-00-00', '0.00', 2, ''),
(14, 17, '0.00', '0000-00-00', '0.00', 1, ''),
(15, 18, '0.00', '0000-00-00', '0.00', 2, ''),
(26, 39, '2800.00', '0000-00-00', '0.00', 4, ''),
(27, 41, '3000.00', '0000-00-00', '0.00', 4, ''),
(28, 43, '7000.00', '0000-00-00', '0.00', 4, ''),
(29, 45, '0.00', '0000-00-00', '0.00', 4, ''),
(30, 47, '600.00', '0000-00-00', '0.00', 1, ''),
(31, 48, '1000.00', '0000-00-00', '0.00', 2, ''),
(32, 52, '3399.00', '0000-00-00', '0.00', 4, ''),
(33, 53, '2000.00', '0000-00-00', '0.00', 5, ''),
(34, 55, '0.00', '0000-00-00', '0.00', 4, ''),
(35, 56, '0.00', '0000-00-00', '0.00', 5, ''),
(36, 58, '0.00', '0000-00-00', '0.00', 4, ''),
(37, 59, '0.00', '0000-00-00', '0.00', 5, ''),
(38, 61, '0.00', '0000-00-00', '0.00', 4, ''),
(39, 62, '0.00', '0000-00-00', '0.00', 5, '');

-- --------------------------------------------------------

--
-- Structure de la table `flux_financier`
--

CREATE TABLE IF NOT EXISTS `flux_financier` (
  `id` int(6) unsigned NOT NULL AUTO_INCREMENT,
  `date` date NOT NULL,
  `mois` varchar(7) NOT NULL,
  `libele` varchar(128) NOT NULL,
  `prix` int(7) unsigned NOT NULL,
  `type` varchar(8) NOT NULL DEFAULT 'sortie',
  `description` text,
  `id_aep` int(5) unsigned NOT NULL DEFAULT '1',
  PRIMARY KEY (`id`),
  KEY `fk_aep_flux_financier` (`id_aep`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 AUTO_INCREMENT=1 ;

--
-- Contenu de la table `flux_financier`
--


-- --------------------------------------------------------

--
-- Structure de la table `impaye`
--

CREATE TABLE IF NOT EXISTS `impaye` (
  `id` int(6) unsigned NOT NULL AUTO_INCREMENT,
  `montant` int(7) DEFAULT NULL,
  `est_regle` int(1) DEFAULT '0',
  `id_facture` int(4) unsigned NOT NULL,
  `date_reglement` date DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `id_facture` (`id_facture`)
) ENGINE=InnoDB  DEFAULT CHARSET=latin1 AUTO_INCREMENT=16 ;

--
-- Contenu de la table `impaye`
--

INSERT INTO `impaye` (`id`, `montant`, `est_regle`, `id_facture`, `date_reglement`) VALUES
(11, -1, 0, 5, '0000-00-00'),
(13, 3600, 0, 34, '0000-00-00'),
(14, 2300, 0, 35, '0000-00-00'),
(15, -1, 0, 32, '0000-00-00');

-- --------------------------------------------------------

--
-- Structure de la table `indexes`
--

CREATE TABLE IF NOT EXISTS `indexes` (
  `id` int(6) unsigned NOT NULL AUTO_INCREMENT,
  `id_compteur` int(5) unsigned NOT NULL,
  `id_mois_facturation` int(3) unsigned NOT NULL,
  `ancien_index` decimal(7,2) unsigned NOT NULL,
  `nouvel_index` decimal(7,2) unsigned NOT NULL,
  `message` text,
  PRIMARY KEY (`id`),
  KEY `fk_compteur_indexes` (`id_compteur`),
  KEY `fk_mois_facturatio_indexes` (`id_mois_facturation`)
) ENGINE=InnoDB  DEFAULT CHARSET=latin1 AUTO_INCREMENT=64 ;

--
-- Contenu de la table `indexes`
--

INSERT INTO `indexes` (`id`, `id_compteur`, `id_mois_facturation`, `ancien_index`, `nouvel_index`, `message`) VALUES
(2, 1, 2, '15.20', '20.30', ''),
(3, 1, 3, '20.30', '22.70', ''),
(4, 2, 3, '2.10', '5.40', ''),
(7, 1, 6, '22.70', '26.00', ''),
(8, 2, 6, '5.40', '7.30', ''),
(9, 1, 6, '22.70', '22.70', ''),
(10, 2, 6, '5.40', '5.40', ''),
(11, 1, 7, '26.00', '29.00', ''),
(12, 2, 7, '7.30', '9.00', ''),
(13, 1, 7, '26.00', '26.00', ''),
(14, 2, 7, '7.30', '7.30', ''),
(15, 1, 8, '29.00', '33.00', ''),
(16, 2, 8, '9.00', '11.00', ''),
(17, 1, 8, '29.00', '29.00', ''),
(18, 2, 8, '9.00', '9.00', ''),
(39, 13, 19, '12.20', '15.00', ''),
(40, 12, 19, '0.00', '2.00', ''),
(41, 13, 20, '15.00', '18.00', ''),
(42, 12, 20, '2.00', '6.00', ''),
(43, 13, 21, '18.00', '25.00', ''),
(44, 12, 21, '6.00', '10.00', ''),
(45, 13, 22, '25.00', '25.00', ''),
(46, 12, 22, '10.00', '10.00', ''),
(47, 1, 23, '33.00', '35.00', ''),
(48, 2, 23, '11.00', '15.00', ''),
(49, 8, 23, '6.20', '10.00', ''),
(50, 9, 23, '15.30', '18.00', ''),
(51, 10, 23, '0.00', '5.00', ''),
(52, 13, 24, '25.00', '28.40', ''),
(53, 14, 24, '12.00', '14.00', ''),
(54, 12, 24, '10.00', '12.00', ''),
(55, 13, 25, '28.40', '32.00', ''),
(56, 14, 25, '14.00', '16.30', ''),
(57, 12, 25, '12.00', '15.25', ''),
(58, 13, 26, '32.00', '32.00', ''),
(59, 14, 26, '16.30', '16.30', ''),
(60, 12, 26, '15.25', '15.25', ''),
(61, 13, 27, '32.00', '35.00', ''),
(62, 14, 27, '16.30', '22.80', ''),
(63, 12, 27, '15.25', '19.60', '');

-- --------------------------------------------------------

--
-- Structure de la table `mois_facturation`
--

CREATE TABLE IF NOT EXISTS `mois_facturation` (
  `id` int(3) unsigned NOT NULL AUTO_INCREMENT,
  `mois` varchar(32) NOT NULL,
  `date_facturation` date NOT NULL,
  `date_depot` date NOT NULL,
  `id_constante` int(2) unsigned NOT NULL,
  `description` text,
  `est_actif` tinyint(1) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `id_constante` (`id_constante`)
) ENGINE=InnoDB  DEFAULT CHARSET=latin1 AUTO_INCREMENT=28 ;

--
-- Contenu de la table `mois_facturation`
--

INSERT INTO `mois_facturation` (`id`, `mois`, `date_facturation`, `date_depot`, `id_constante`, `description`, `est_actif`) VALUES
(2, '2025-02', '0000-00-00', '2025-01-10', 1, '', 0),
(3, '2025-03', '0000-00-00', '2025-01-27', 1, '', 0),
(6, '2025-04', '0000-00-00', '0000-00-00', 1, '', 0),
(7, '2025-05', '0000-00-00', '0000-00-00', 1, '', 0),
(8, '2025-06', '0000-00-00', '0000-00-00', 1, '', 0),
(19, '2025-01', '0000-00-00', '2025-03-06', 2, '', 0),
(20, '2025-02', '0000-00-00', '0000-00-00', 2, '', 0),
(21, '2025-03', '0000-00-00', '0000-00-00', 2, '', 0),
(22, '2025-04', '0000-00-00', '2025-03-11', 2, '', 0),
(23, '2025-07', '0000-00-00', '0000-00-00', 1, '', 1),
(24, '2025-05', '0000-00-00', '0000-00-00', 2, '', 0),
(25, '2025-06', '0000-00-00', '0000-00-00', 2, '', 0),
(26, '2025-07', '0000-00-00', '2025-03-17', 2, '', 0),
(27, '2025-08', '0000-00-00', '0000-00-00', 2, '', 1);

-- --------------------------------------------------------

--
-- Structure de la table `position_compteur_aep`
--

CREATE TABLE IF NOT EXISTS `position_compteur_aep` (
  `id` int(2) unsigned NOT NULL AUTO_INCREMENT,
  `position` varchar(32) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 AUTO_INCREMENT=1 ;

--
-- Contenu de la table `position_compteur_aep`
--


-- --------------------------------------------------------

--
-- Structure de la table `redevance`
--

CREATE TABLE IF NOT EXISTS `redevance` (
  `id` int(4) unsigned NOT NULL AUTO_INCREMENT,
  `libele` varchar(64) NOT NULL,
  `pourcentage` decimal(5,2) NOT NULL,
  `description` text,
  `id_aep` int(4) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_aep_redevance` (`id_aep`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 AUTO_INCREMENT=1 ;

--
-- Contenu de la table `redevance`
--


-- --------------------------------------------------------

--
-- Structure de la table `reseau`
--

CREATE TABLE IF NOT EXISTS `reseau` (
  `id` int(3) unsigned NOT NULL AUTO_INCREMENT,
  `nom` varchar(32) NOT NULL,
  `abreviation` varchar(16) DEFAULT NULL,
  `date_creation` date NOT NULL,
  `description_reseau` text,
  `id_aep` int(4) unsigned NOT NULL DEFAULT '1',
  PRIMARY KEY (`id`),
  KEY `fk_aep_reseau` (`id_aep`)
) ENGINE=InnoDB  DEFAULT CHARSET=latin1 AUTO_INCREMENT=17 ;

--
-- Contenu de la table `reseau`
--

INSERT INTO `reseau` (`id`, `nom`, `abreviation`, `date_creation`, `description_reseau`, `id_aep`) VALUES
(1, 'Reseau principal', 'RP', '2025-01-10', 'oui oui oui', 1),
(2, 'Reseau secondaire 1', 'Rs1', '2025-01-12', '', 1),
(3, 'Reseau secondaire 2', 'Rs2', '2025-01-12', '', 1),
(13, 'Vers ecole', 'VE', '2025-01-13', '', 1),
(14, 'Reseau principal', 'RP', '2025-01-13', '', 2),
(15, 'Reseau principal', 'RP', '2025-02-12', '', 4),
(16, 'Reseau secondaire 2', 'Rs2', '2025-03-14', '', 4);

-- --------------------------------------------------------

--
-- Structure de la table `role`
--

CREATE TABLE IF NOT EXISTS `role` (
  `id` int(3) unsigned NOT NULL AUTO_INCREMENT,
  `role` varchar(32) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `role` (`role`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 AUTO_INCREMENT=1 ;

--
-- Contenu de la table `role`
--


-- --------------------------------------------------------

--
-- Structure de la table `travail`
--

CREATE TABLE IF NOT EXISTS `travail` (
  `id` int(5) unsigned NOT NULL AUTO_INCREMENT,
  `id_aep` int(4) unsigned NOT NULL,
  `id_utilisateur` int(4) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_aep_travail` (`id_aep`),
  KEY `fk_utilisateur_travail` (`id_utilisateur`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 AUTO_INCREMENT=1 ;

--
-- Contenu de la table `travail`
--


-- --------------------------------------------------------

--
-- Structure de la table `utilisateur`
--

CREATE TABLE IF NOT EXISTS `utilisateur` (
  `id` int(5) unsigned NOT NULL AUTO_INCREMENT,
  `email` varchar(32) NOT NULL,
  `nom` varchar(32) NOT NULL,
  `prenom` varchar(32) NOT NULL,
  `numero_telephone` varchar(16) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 AUTO_INCREMENT=1 ;

--
-- Contenu de la table `utilisateur`
--


--
-- Contraintes pour les tables exportées
--

--
-- Contraintes pour la table `abone`
--
ALTER TABLE `abone`
  ADD CONSTRAINT `abone_ibfk_1` FOREIGN KEY (`id_reseau`) REFERENCES `reseau` (`id`);

--
-- Contraintes pour la table `avoir_role`
--
ALTER TABLE `avoir_role`
  ADD CONSTRAINT `fk_role_avoir_role` FOREIGN KEY (`id_role`) REFERENCES `aep` (`id`),
  ADD CONSTRAINT `fk_utilisateur_avoir_role` FOREIGN KEY (`id_utilisateur`) REFERENCES `utilisateur` (`id`);

--
-- Contraintes pour la table `compteur_abone`
--
ALTER TABLE `compteur_abone`
  ADD CONSTRAINT `fk_abone_compteur_abone` FOREIGN KEY (`id_abone`) REFERENCES `abone` (`id`),
  ADD CONSTRAINT `fk_compteur_compteur_abone` FOREIGN KEY (`id_compteur`) REFERENCES `compteur` (`id`);

--
-- Contraintes pour la table `compteur_aep`
--
ALTER TABLE `compteur_aep`
  ADD CONSTRAINT `fk_aep_compteur_aep` FOREIGN KEY (`id_aep`) REFERENCES `aep` (`id`),
  ADD CONSTRAINT `fk_compteur_compteur_aep` FOREIGN KEY (`id_compteur`) REFERENCES `compteur` (`id`),
  ADD CONSTRAINT `fk_position_compteur_aep` FOREIGN KEY (`id_position`) REFERENCES `position_compteur_aep` (`id`);

--
-- Contraintes pour la table `compteur_reseau`
--
ALTER TABLE `compteur_reseau`
  ADD CONSTRAINT `fk_compteur_compteur_reseau` FOREIGN KEY (`id_compteur`) REFERENCES `compteur` (`id`),
  ADD CONSTRAINT `fk_reseau_compteur_reseau` FOREIGN KEY (`id_reseau`) REFERENCES `reseau` (`id`);

--
-- Contraintes pour la table `constante_reseau`
--
ALTER TABLE `constante_reseau`
  ADD CONSTRAINT `fk_aep_constante` FOREIGN KEY (`id_aep`) REFERENCES `aep` (`id`);

--
-- Contraintes pour la table `facture`
--
ALTER TABLE `facture`
  ADD CONSTRAINT `facture_ibfk_2` FOREIGN KEY (`id_abone`) REFERENCES `abone` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_index_facture` FOREIGN KEY (`id_indexes`) REFERENCES `indexes` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `flux_financier`
--
ALTER TABLE `flux_financier`
  ADD CONSTRAINT `fk_aep_flux_financier` FOREIGN KEY (`id_aep`) REFERENCES `aep` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `impaye`
--
ALTER TABLE `impaye`
  ADD CONSTRAINT `impaye_ibfk_1` FOREIGN KEY (`id_facture`) REFERENCES `facture` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `indexes`
--
ALTER TABLE `indexes`
  ADD CONSTRAINT `fk_compteur_indexes` FOREIGN KEY (`id_compteur`) REFERENCES `compteur` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_mois_facturatio_indexes` FOREIGN KEY (`id_mois_facturation`) REFERENCES `mois_facturation` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `mois_facturation`
--
ALTER TABLE `mois_facturation`
  ADD CONSTRAINT `mois_facturation_ibfk_1` FOREIGN KEY (`id_constante`) REFERENCES `constante_reseau` (`id`);

--
-- Contraintes pour la table `redevance`
--
ALTER TABLE `redevance`
  ADD CONSTRAINT `fk_aep_redevance` FOREIGN KEY (`id_aep`) REFERENCES `aep` (`id`);

--
-- Contraintes pour la table `reseau`
--
ALTER TABLE `reseau`
  ADD CONSTRAINT `fk_aep_reseau` FOREIGN KEY (`id_aep`) REFERENCES `aep` (`id`);

--
-- Contraintes pour la table `travail`
--
ALTER TABLE `travail`
  ADD CONSTRAINT `fk_aep_travail` FOREIGN KEY (`id_aep`) REFERENCES `aep` (`id`),
  ADD CONSTRAINT `fk_utilisateur_travail` FOREIGN KEY (`id_utilisateur`) REFERENCES `utilisateur` (`id`);
