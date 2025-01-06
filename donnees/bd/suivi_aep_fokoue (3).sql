-- phpMyAdmin SQL Dump
-- version 3.3.9
-- http://www.phpmyadmin.net
--
-- Serveur: localhost
-- Généré le : Ven 22 Novembre 2024 à 04:35
-- Version du serveur: 5.1.53
-- Version de PHP: 5.3.4

SET SQL_MODE="NO_AUTO_VALUE_ON_ZERO";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8 */;
drop database  suivi_aep_fokoue;
create database suivi_aep_fokoue;
use suivi_aep_fokoue;
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
  `numero_compteur` varchar(32) NOT NULL,
  `numero_telephone` varchar(16) NOT NULL,
  `numero_compte_anticipation` varchar(16) NOT NULL,
  `etat` varchar(10) NOT NULL,
  `rang` int(4) DEFAULT NULL,
  `id_reseau` int(3) unsigned NOT NULL,
  `derniers_index` decimal(7,2) unsigned DEFAULT '0.00',
  PRIMARY KEY (`id`),
  KEY `id_reseau` (`id_reseau`)
) ENGINE=InnoDB  DEFAULT CHARSET=latin1 AUTO_INCREMENT=19 ;

--
-- Contenu de la table `abone`
--

-- INSERT INTO `abone` VALUES(1, 'Manewoun Denise', '147854120147', '652147841', '11', 'actif', 13, 2, '42.00');
-- INSERT INTO `abone` VALUES(2, 'Donfack Ange', '00000001', '635471452', '12', 'actif', 0, 1, '31.00');
-- INSERT INTO `abone` VALUES(3, 'Manekeng Albert', '0000002', '654190514', '13', 'actif', 0, 2, '35.00');
-- INSERT INTO `abone` VALUES(4, 'Kana Pascal', '00000003', '682144785', '14', 'actif', 0, 1, '19.51');
-- INSERT INTO `abone` VALUES(5, 'Takam Armand', '00000005', '655487565', '15', 'actif', 0, 2, '16.20');
-- INSERT INTO `abone` VALUES(6, 'Tsafack Nteudem', '00000006', '655253214', '16', 'actif', 0, 1, '25.40');
-- INSERT INTO `abone` VALUES(7, 'Tokon Michelin', '00000011', '698895254', '21', 'actif', 0, 1, '32.40');
-- INSERT INTO `abone` VALUES(16, 'Tamo Maxime', '0000110', '698979495', '10', 'actif', 0, 1, '17.20');
-- INSERT INTO `abone` VALUES(17, 'Mamdjo Carene', '0000021', '678797574', '10', 'actif', 0, 1, '25.00');
-- INSERT INTO `abone` VALUES(18, 'Mkanso Pasca', '5847562', '677859562', '10', 'actif', 0, 3, '125.33');

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
  PRIMARY KEY (`id`)
) ENGINE=InnoDB  DEFAULT CHARSET=latin1 AUTO_INCREMENT=11 ;

--
-- Contenu de la table `constante_reseau`
--

-- INSERT INTO `constante_reseau` VALUES(1, 300, 500, '20.00', '0000-00-00', 0, '');
-- INSERT INTO `constante_reseau` VALUES(2, 300, 500, '20.00', '0000-00-00', 0, '');
-- INSERT INTO `constante_reseau` VALUES(3, 300, 500, '20.00', '0000-00-00', 0, '');
-- INSERT INTO `constante_reseau` VALUES(4, 300, 500, '25.62', '0000-00-00', 0, '');
-- INSERT INTO `constante_reseau` VALUES(5, 300, 500, '0.00', '0000-00-00', 0, '');
-- INSERT INTO `constante_reseau` VALUES(6, 300, 0, '0.00', '0000-00-00', 0, '');
-- INSERT INTO `constante_reseau` VALUES(7, 300, 500, '0.00', '0000-00-00', 0, '');
-- INSERT INTO `constante_reseau` VALUES(8, 300, 0, '0.00', '0000-00-00', 0, '');
-- INSERT INTO `constante_reseau` VALUES(9, 300, 10, '0.00', '0000-00-00', 0, '');
-- INSERT INTO `constante_reseau` VALUES(10, 300, 100, '0.00', '0000-00-00', 1, '');

-- --------------------------------------------------------

--
-- Structure de la table `facture`
--

CREATE TABLE IF NOT EXISTS `facture` (
  `id` int(4) unsigned NOT NULL AUTO_INCREMENT,
  `ancien_index` decimal(7,2) unsigned NOT NULL,
  `nouvel_index` decimal(7,2) unsigned NOT NULL,
  `impaye` decimal(8,2) DEFAULT '0.00',
  `montant_verse` decimal(8,2) DEFAULT '0.00',
  `date_paiement` date DEFAULT NULL,
  `penalite` decimal(8,2) DEFAULT '0.00',
  `id_mois_facturation` int(3) unsigned NOT NULL,
  `id_abone` int(4) unsigned NOT NULL,
  `message` text,
  PRIMARY KEY (`id`),
  KEY `id_mois_facturation` (`id_mois_facturation`),
  KEY `id_abone` (`id_abone`)
) ENGINE=InnoDB  DEFAULT CHARSET=latin1 AUTO_INCREMENT=281 ;

--
-- Contenu de la table `facture`
--

-- INSERT INTO `facture` VALUES(171, '3.00', '3.80', '0.00', '500.00', '0000-00-00', '0.00', 99, 6, '');
-- INSERT INTO `facture` VALUES(172, '2.30', '3.30', '-10.00', '0.00', '0000-00-00', '0.00', 99, 7, '');
-- INSERT INTO `facture` VALUES(174, '4.00', '5.20', '0.00', '400.00', '0000-00-00', '0.00', 99, 1, '');
-- INSERT INTO `facture` VALUES(175, '3.00', '3.90', '0.00', '300.00', '0000-00-00', '0.00', 99, 3, '');
-- INSERT INTO `facture` VALUES(176, '3.80', '7.00', '-260.00', '0.00', '0000-00-00', '0.00', 100, 6, '');
-- INSERT INTO `facture` VALUES(177, '3.30', '3.50', '290.00', '0.00', '0000-00-00', '0.00', 100, 7, '');
-- INSERT INTO `facture` VALUES(179, '5.20', '6.00', '-40.00', '0.00', '0000-00-00', '0.00', 100, 1, '');
-- INSERT INTO `facture` VALUES(180, '3.90', '11.30', '-30.00', '0.00', '0000-00-00', '0.00', 100, 3, '');
-- INSERT INTO `facture` VALUES(181, '7.00', '9.60', '700.00', '1000.00', '0000-00-00', '0.00', 102, 6, '');
-- INSERT INTO `facture` VALUES(182, '3.50', '5.00', '350.00', '500.00', '0000-00-00', '0.00', 102, 7, '');
-- INSERT INTO `facture` VALUES(184, '6.00', '10.90', '200.00', '2500.00', '0000-00-00', '0.00', 102, 1, '');
-- INSERT INTO `facture` VALUES(185, '11.30', '12.90', '2190.00', '1000.00', '0000-00-00', '0.00', 102, 3, '');
-- INSERT INTO `facture` VALUES(186, '0.00', '5.00', '0.00', '0.00', '0000-00-00', '0.00', 103, 2, '');
-- INSERT INTO `facture` VALUES(187, '0.00', '7.00', '0.00', '0.00', '0000-00-00', '0.00', 103, 4, '');
-- INSERT INTO `facture` VALUES(188, '9.60', '11.00', '480.00', '0.00', '0000-00-00', '0.00', 103, 6, '');
-- INSERT INTO `facture` VALUES(189, '5.00', '7.23', '300.00', '0.00', '0000-00-00', '0.00', 103, 7, '');
-- INSERT INTO `facture` VALUES(190, '0.00', '3.00', '0.00', '0.00', '0000-00-00', '0.00', 103, 16, '');
-- INSERT INTO `facture` VALUES(191, '0.00', '8.00', '0.00', '0.00', '0000-00-00', '0.00', 103, 17, '');
-- INSERT INTO `facture` VALUES(192, '12.90', '15.00', '1670.00', '0.00', '0000-00-00', '0.00', 103, 3, '');
-- INSERT INTO `facture` VALUES(193, '0.00', '3.00', '0.00', '0.00', '0000-00-00', '0.00', 103, 5, '');
-- INSERT INTO `facture` VALUES(194, '10.90', '21.00', '-830.00', '0.00', '0000-00-00', '0.00', 103, 1, '');
-- INSERT INTO `facture` VALUES(195, '5.00', '10.00', '1500.00', '1500.00', '0000-00-00', '0.00', 104, 2, '');
-- INSERT INTO `facture` VALUES(196, '7.00', '9.00', '2100.00', '2700.00', '0000-00-00', '0.00', 104, 4, '');
-- INSERT INTO `facture` VALUES(197, '11.00', '12.33', '900.00', '1300.00', '0000-00-00', '0.00', 104, 6, '');
-- INSERT INTO `facture` VALUES(198, '7.23', '10.00', '969.00', '1500.00', '0000-00-00', '0.00', 104, 7, '');
-- INSERT INTO `facture` VALUES(199, '3.00', '5.00', '900.00', '1500.00', '0000-00-00', '0.00', 104, 16, '');
-- INSERT INTO `facture` VALUES(200, '8.00', '8.50', '2400.00', '2500.00', '0000-00-00', '0.00', 104, 17, '');
-- INSERT INTO `facture` VALUES(201, '15.00', '19.00', '2300.00', '3500.00', '0000-00-00', '0.00', 104, 3, '');
-- INSERT INTO `facture` VALUES(202, '3.00', '5.00', '900.00', '400.00', '0000-00-00', '0.00', 104, 5, '');
-- INSERT INTO `facture` VALUES(203, '21.00', '25.30', '2200.00', '3500.00', '0000-00-00', '0.00', 104, 1, '');
-- INSERT INTO `facture` VALUES(204, '10.00', '13.15', '1500.00', '2445.00', '0000-00-00', '0.00', 105, 2, '');
-- INSERT INTO `facture` VALUES(205, '9.00', '11.00', '0.00', '600.00', '0000-00-00', '0.00', 105, 4, '');
-- INSERT INTO `facture` VALUES(206, '12.33', '13.50', '-1.00', '350.00', '0000-00-00', '0.00', 105, 6, '');
-- INSERT INTO `facture` VALUES(207, '10.00', '13.00', '300.00', '1200.00', '0000-00-00', '0.00', 105, 7, '');
-- INSERT INTO `facture` VALUES(208, '5.00', '6.50', '0.00', '450.00', '0000-00-00', '0.00', 105, 16, '');
-- INSERT INTO `facture` VALUES(209, '8.50', '8.50', '50.00', '50.00', '0000-00-00', '0.00', 105, 17, '');
-- INSERT INTO `facture` VALUES(210, '19.00', '19.00', '0.00', '0.00', '0000-00-00', '0.00', 105, 3, '');
-- INSERT INTO `facture` VALUES(211, '5.00', '5.00', '1100.00', '1100.00', '0000-00-00', '0.00', 105, 5, '');
-- INSERT INTO `facture` VALUES(212, '25.30', '28.00', '-10.00', '10000.00', '0000-00-00', '0.00', 105, 1, '');
-- INSERT INTO `facture` VALUES(236, '35.00', '39.00', '-7100.00', '0.00', '0000-00-00', '0.00', 113, 1, '');
-- INSERT INTO `facture` VALUES(237, '20.00', '23.40', '-445.00', '0.00', '0000-00-00', '0.00', 113, 2, '');
-- INSERT INTO `facture` VALUES(238, '26.00', '29.00', '100.00', '0.00', '0000-00-00', '0.00', 113, 3, '');
-- INSERT INTO `facture` VALUES(239, '13.00', '15.33', '600.00', '0.00', '0000-00-00', '0.00', 113, 4, '');
-- INSERT INTO `facture` VALUES(240, '8.00', '10.20', '0.00', '0.00', '0000-00-00', '0.00', 113, 5, '');
-- INSERT INTO `facture` VALUES(241, '18.00', '21.50', '1350.00', '0.00', '0000-00-00', '0.00', 113, 6, '');
-- INSERT INTO `facture` VALUES(242, '17.00', '27.30', '1200.00', '0.00', '0000-00-00', '0.00', 113, 7, '');
-- INSERT INTO `facture` VALUES(243, '9.00', '12.55', '750.00', '0.00', '0000-00-00', '0.00', 113, 16, '');
-- INSERT INTO `facture` VALUES(244, '11.00', '13.90', '-250.00', '0.00', '0000-00-00', '0.00', 113, 17, '');
-- INSERT INTO `facture` VALUES(245, '39.00', '39.00', '-5900.00', '0.00', '0000-00-00', '0.00', 114, 1, '');
-- INSERT INTO `facture` VALUES(246, '23.40', '23.40', '575.00', '0.00', '0000-00-00', '0.00', 114, 2, '');
-- INSERT INTO `facture` VALUES(247, '29.00', '29.00', '1000.00', '0.00', '0000-00-00', '0.00', 114, 3, '');
-- INSERT INTO `facture` VALUES(248, '15.33', '15.33', '1299.00', '0.00', '0000-00-00', '0.00', 114, 4, '');
-- INSERT INTO `facture` VALUES(249, '10.20', '10.20', '660.00', '0.00', '0000-00-00', '0.00', 114, 5, '');
-- INSERT INTO `facture` VALUES(250, '21.50', '21.50', '2400.00', '0.00', '0000-00-00', '0.00', 114, 6, '');
-- INSERT INTO `facture` VALUES(251, '27.30', '27.30', '4290.00', '0.00', '0000-00-00', '0.00', 114, 7, '');
-- INSERT INTO `facture` VALUES(252, '12.55', '12.55', '1815.00', '0.00', '0000-00-00', '0.00', 114, 16, '');
-- INSERT INTO `facture` VALUES(253, '13.90', '13.90', '620.00', '0.00', '0000-00-00', '0.00', 114, 17, '');
-- INSERT INTO `facture` VALUES(254, '39.00', '39.00', '-5900.00', '0.00', '0000-00-00', '0.00', 117, 1, '');
-- INSERT INTO `facture` VALUES(255, '23.40', '23.40', '575.00', '0.00', '0000-00-00', '0.00', 117, 2, '');
-- INSERT INTO `facture` VALUES(256, '29.00', '29.00', '1000.00', '0.00', '0000-00-00', '0.00', 117, 3, '');
-- INSERT INTO `facture` VALUES(257, '15.33', '15.33', '1299.00', '0.00', '0000-00-00', '0.00', 117, 4, '');
-- INSERT INTO `facture` VALUES(258, '10.20', '10.20', '660.00', '0.00', '0000-00-00', '0.00', 117, 5, '');
-- INSERT INTO `facture` VALUES(259, '21.50', '21.50', '2400.00', '0.00', '0000-00-00', '0.00', 117, 6, '');
-- INSERT INTO `facture` VALUES(260, '27.30', '27.30', '4290.00', '0.00', '0000-00-00', '0.00', 117, 7, '');
-- INSERT INTO `facture` VALUES(261, '12.55', '12.55', '1815.00', '0.00', '0000-00-00', '0.00', 117, 16, '');
-- INSERT INTO `facture` VALUES(262, '13.90', '13.90', '620.00', '0.00', '0000-00-00', '0.00', 117, 17, '');
-- INSERT INTO `facture` VALUES(263, '39.00', '39.00', '-5900.00', '0.00', '0000-00-00', '0.00', 118, 1, '');
-- INSERT INTO `facture` VALUES(264, '23.40', '23.40', '575.00', '0.00', '0000-00-00', '0.00', 118, 2, '');
-- INSERT INTO `facture` VALUES(265, '29.00', '29.00', '1000.00', '0.00', '0000-00-00', '0.00', 118, 3, '');
-- INSERT INTO `facture` VALUES(266, '15.33', '15.33', '1299.00', '0.00', '0000-00-00', '0.00', 118, 4, '');
-- INSERT INTO `facture` VALUES(267, '10.20', '10.20', '660.00', '0.00', '0000-00-00', '0.00', 118, 5, '');
-- INSERT INTO `facture` VALUES(268, '21.50', '21.50', '2400.00', '0.00', '0000-00-00', '0.00', 118, 6, '');
-- INSERT INTO `facture` VALUES(269, '27.30', '27.30', '4290.00', '0.00', '0000-00-00', '0.00', 118, 7, '');
-- INSERT INTO `facture` VALUES(270, '12.55', '12.55', '1815.00', '0.00', '0000-00-00', '0.00', 118, 16, '');
-- INSERT INTO `facture` VALUES(271, '13.90', '13.90', '620.00', '0.00', '0000-00-00', '0.00', 118, 17, '');
-- INSERT INTO `facture` VALUES(272, '39.00', '42.00', '-5900.00', '0.00', '0000-00-00', '0.00', 119, 1, '');
-- INSERT INTO `facture` VALUES(273, '23.40', '31.00', '575.00', '3000.00', '0000-00-00', '0.00', 119, 2, '');
-- INSERT INTO `facture` VALUES(274, '29.00', '35.00', '1000.00', '2900.00', '0000-00-00', '0.00', 119, 3, '');
-- INSERT INTO `facture` VALUES(275, '15.33', '19.51', '1299.00', '2700.00', '0000-00-00', '0.00', 119, 4, '');
-- INSERT INTO `facture` VALUES(276, '10.20', '16.20', '660.00', '2600.00', '0000-00-00', '0.00', 119, 5, '');
-- INSERT INTO `facture` VALUES(277, '21.50', '25.40', '2400.00', '3700.00', '0000-00-00', '0.00', 119, 6, '');
-- INSERT INTO `facture` VALUES(278, '27.30', '32.40', '4290.00', '5900.00', '0000-00-00', '0.00', 119, 7, '');
-- INSERT INTO `facture` VALUES(279, '12.55', '17.20', '1815.00', '3500.00', '0000-00-00', '0.00', 119, 16, '');
-- INSERT INTO `facture` VALUES(280, '13.90', '25.00', '620.00', '4200.00', '0000-00-00', '0.00', 119, 17, '');

-- --------------------------------------------------------

--
-- Structure de la table `impaye`
--

CREATE TABLE IF NOT EXISTS `impaye` (
  `id` int(6) unsigned NOT NULL AUTO_INCREMENT,
  `montant` int(6) unsigned NOT NULL,
  `est_regle` int(1) DEFAULT '0',
  `id_facture` int(4) unsigned NOT NULL,
  `date_reglement` date DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `id_facture` (`id_facture`)
) ENGINE=InnoDB  DEFAULT CHARSET=latin1 AUTO_INCREMENT=10 ;

--
-- Contenu de la table `impaye`
--

-- INSERT INTO `impaye` VALUES(1, 0, 0, 272, '0000-00-00');
-- INSERT INTO `impaye` VALUES(2, 574, 0, 273, '0000-00-00');
-- INSERT INTO `impaye` VALUES(3, 1000, 0, 274, '0000-00-00');
-- INSERT INTO `impaye` VALUES(4, 1299, 0, 275, '0000-00-00');
-- INSERT INTO `impaye` VALUES(5, 659, 0, 276, '0000-00-00');
-- INSERT INTO `impaye` VALUES(6, 2400, 0, 277, '0000-00-00');
-- INSERT INTO `impaye` VALUES(7, 4290, 0, 278, '0000-00-00');
-- INSERT INTO `impaye` VALUES(8, 1815, 0, 279, '0000-00-00');
-- INSERT INTO `impaye` VALUES(9, 620, 0, 280, '0000-00-00');

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
  UNIQUE KEY `mois` (`mois`),
  KEY `id_constante` (`id_constante`)
) ENGINE=InnoDB  DEFAULT CHARSET=latin1 AUTO_INCREMENT=120 ;

--
-- Contenu de la table `mois_facturation`
--

-- INSERT INTO `mois_facturation` VALUES(99, '2024-03', '0000-00-00', '0000-00-00', 6, '', 0);
-- INSERT INTO `mois_facturation` VALUES(100, '2024-04', '0000-00-00', '2024-10-15', 6, '', 0);
-- INSERT INTO `mois_facturation` VALUES(102, '2024-05', '0000-00-00', '2024-10-19', 6, '', 0);
-- INSERT INTO `mois_facturation` VALUES(103, '2024-06', '0000-00-00', '0000-00-00', 6, 'releve manuelle', 0);
-- INSERT INTO `mois_facturation` VALUES(104, '2024-07', '0000-00-00', '2024-11-07', 6, '', 0);
-- INSERT INTO `mois_facturation` VALUES(105, '2024-08', '0000-00-00', '2024-10-29', 6, '', 0);
-- INSERT INTO `mois_facturation` VALUES(113, '2024-12', '0000-00-00', '0000-00-00', 6, '', 0);
-- INSERT INTO `mois_facturation` VALUES(114, '2024-09', '0000-00-00', '2024-11-08', 6, '', 0);
-- INSERT INTO `mois_facturation` VALUES(117, '2023-03', '0000-00-00', '0000-00-00', 6, '', 0);
-- INSERT INTO `mois_facturation` VALUES(118, '2023-07', '0000-00-00', '2024-11-08', 6, '', 0);
-- INSERT INTO `mois_facturation` VALUES(119, '2025-01', '0000-00-00', '2024-11-21', 10, '', 1);

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
  PRIMARY KEY (`id`),
  UNIQUE KEY `nom` (`nom`)
) ENGINE=InnoDB  DEFAULT CHARSET=latin1 AUTO_INCREMENT=5 ;

--
-- Contenu de la table `reseau`
--

INSERT INTO `reseau` VALUES(1, 'Nza-ah', 'Nza', '2024-09-26', '');
INSERT INTO `reseau` VALUES(2, 'Mbouh', 'MB', '2024-09-26', '');
INSERT INTO `reseau` VALUES(3, 'Lewet', 'LW', '2021-07-16', '');
-- INSERT INTO `reseau` VALUES(4, 'RP', '', '2024-10-09', '');

--
-- Contraintes pour les tables exportées
--

    create table flux_financier(
        id int(6) unsigned primary key auto_increment,
        date date not null,
        mois varchar(7) not null,
        libele varchar(128) not null,
        prix int(7) unsigned not null,
        type varchar(8) not null default 'sortie',
        description text(1000)
    )engine=innoDB;



--
-- Contraintes pour la table `abone`
--
ALTER TABLE `abone`
  ADD CONSTRAINT `abone_ibfk_1` FOREIGN KEY (`id_reseau`) REFERENCES `reseau` (`id`);

--
-- Contraintes pour la table `facture`
--
ALTER TABLE `facture`
  ADD CONSTRAINT `facture_ibfk_1` FOREIGN KEY (`id_mois_facturation`) REFERENCES `mois_facturation` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `facture_ibfk_2` FOREIGN KEY (`id_abone`) REFERENCES `abone` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `impaye`
--
ALTER TABLE `impaye`
  ADD CONSTRAINT `impaye_ibfk_1` FOREIGN KEY (`id_facture`) REFERENCES `facture` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `mois_facturation`
--
ALTER TABLE `mois_facturation`
  ADD CONSTRAINT `mois_facturation_ibfk_1` FOREIGN KEY (`id_constante`) REFERENCES `constante_reseau` (`id`);

alter table facture
    drop column impaye;

select ancien_index, nouvel_index, i.montant, montant_verse, id_mois_facturation, nom, numero_compteur, etat
from facture f
    left join impaye i on f.id = i.id_facture
    inner join abone a on f.id_abone = a.id
limit 10;


select f.id, f.id_abone, m.id id_mois, f.id id_facture, a.nom, m.mois, f.ancien_index, f.nouvel_index, c.prix_entretient_compteur, a.numero_compteur, a.numero_compte_anticipation,
       c.prix_metre_cube_eau, c.prix_tva, penalite, sum(i.montant) as impaye, f.montant_verse, f.date_paiement, date_depot, date_facturation, r.nom reseau
from facture f
         inner join abone a on a.id = f.id_abone
         left join impaye i on i.id_facture in (select f.id from abone a, facture f where a.id = f.id_abone and f.id_mois_facturation<28)
         inner join mois_facturation m on f.id_mois_facturation =m.id
         inner join  constante_reseau c on c.id=m.id_constante
         inner join reseau r on r.id = a.id_reseau
where  m.id=28
group by a.id
order by a.id