-- phpMyAdmin SQL Dump
-- version 3.3.9
-- http://www.phpmyadmin.net
--
-- Serveur: localhost
-- Généré le : Mer 13 Novembre 2024 à 15:06
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
  `numero_compteur` varchar(32) NOT NULL,
  `numero_telephone` varchar(16) NOT NULL,
  `numero_compte_anticipation` varchar(16) NOT NULL,
  `etat` varchar(10) NOT NULL,
  `rang` int(4) DEFAULT NULL,
  `id_reseau` int(3) unsigned NOT NULL,
  `derniers_index` decimal(7,2) unsigned DEFAULT '0.00',
  PRIMARY KEY (`id`),
  KEY `id_reseau` (`id_reseau`)
) ENGINE=InnoDB  DEFAULT CHARSET=latin1 AUTO_INCREMENT=18 ;

--
-- Contenu de la table `abone`
--

INSERT INTO `abone` (`id`, `nom`, `numero_compteur`, `numero_telephone`, `numero_compte_anticipation`, `etat`, `rang`, `id_reseau`, `derniers_index`) VALUES
(1, 'Manewoun Denise', '147854120147', '652147841', '11', 'actif', 13, 2, '41.80'),
(2, 'Donfack Ange', '00000001', '635471452', '12', 'actif', 0, 1, '27.00'),
(3, 'Manekeng Albert', '0000002', '654190514', '13', 'actif', 0, 2, '33.60'),
(4, 'Kana Pascal', '00000003', '682144785', '14', 'actif', 0, 1, '18.12'),
(5, 'Takam Armand', '00000005', '655487565', '15', 'actif', 0, 2, '13.10'),
(6, 'Tsafack Nteudem', '00000006', '655253214', '16', 'actif', 0, 1, '22.00'),
(7, 'Tokon Michelin', '00000011', '698895254', '21', 'actif', 0, 1, '30.00'),
(16, 'Tamo Maxime', '0000110', '698979495', '10', 'actif', 0, 1, '15.90'),
(17, 'Mamdjo Carene', '0000021', '678797574', '10', 'actif', 0, 1, '21.80');

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
) ENGINE=InnoDB  DEFAULT CHARSET=latin1 AUTO_INCREMENT=7 ;

--
-- Contenu de la table `constante_reseau`
--

INSERT INTO `constante_reseau` (`id`, `prix_metre_cube_eau`, `prix_entretient_compteur`, `prix_tva`, `date_creation`, `est_actif`, `description`) VALUES
(1, 300, 500, '20.00', '0000-00-00', 0, ''),
(2, 300, 500, '20.00', '0000-00-00', 0, ''),
(3, 300, 500, '20.00', '0000-00-00', 0, ''),
(4, 300, 500, '25.62', '0000-00-00', 0, ''),
(5, 300, 500, '0.00', '0000-00-00', 0, ''),
(6, 300, 0, '0.00', '0000-00-00', 1, '');

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

INSERT INTO `facture` (`id`, `ancien_index`, `nouvel_index`, `impaye`, `montant_verse`, `date_paiement`, `penalite`, `id_mois_facturation`, `id_abone`, `message`) VALUES
(171, '3.00', '3.80', '0.00', '500.00', '0000-00-00', '0.00', 99, 6, ''),
(172, '2.30', '3.30', '-10.00', '0.00', '0000-00-00', '0.00', 99, 7, ''),
(174, '4.00', '5.20', '0.00', '400.00', '0000-00-00', '0.00', 99, 1, ''),
(175, '3.00', '3.90', '0.00', '300.00', '0000-00-00', '0.00', 99, 3, ''),
(176, '3.80', '7.00', '-260.00', '0.00', '0000-00-00', '0.00', 100, 6, ''),
(177, '3.30', '3.50', '290.00', '0.00', '0000-00-00', '0.00', 100, 7, ''),
(179, '5.20', '6.00', '-40.00', '0.00', '0000-00-00', '0.00', 100, 1, ''),
(180, '3.90', '11.30', '-30.00', '0.00', '0000-00-00', '0.00', 100, 3, ''),
(181, '7.00', '9.60', '700.00', '1000.00', '0000-00-00', '0.00', 102, 6, ''),
(182, '3.50', '5.00', '350.00', '500.00', '0000-00-00', '0.00', 102, 7, ''),
(184, '6.00', '10.90', '200.00', '2500.00', '0000-00-00', '0.00', 102, 1, ''),
(185, '11.30', '12.90', '2190.00', '1000.00', '0000-00-00', '0.00', 102, 3, ''),
(186, '0.00', '5.00', '0.00', '0.00', '0000-00-00', '0.00', 103, 2, ''),
(187, '0.00', '7.00', '0.00', '0.00', '0000-00-00', '0.00', 103, 4, ''),
(188, '9.60', '11.00', '480.00', '0.00', '0000-00-00', '0.00', 103, 6, ''),
(189, '5.00', '7.23', '300.00', '0.00', '0000-00-00', '0.00', 103, 7, ''),
(190, '0.00', '3.00', '0.00', '0.00', '0000-00-00', '0.00', 103, 16, ''),
(191, '0.00', '8.00', '0.00', '0.00', '0000-00-00', '0.00', 103, 17, ''),
(192, '12.90', '15.00', '1670.00', '0.00', '0000-00-00', '0.00', 103, 3, ''),
(193, '0.00', '3.00', '0.00', '0.00', '0000-00-00', '0.00', 103, 5, ''),
(194, '10.90', '21.00', '-830.00', '0.00', '0000-00-00', '0.00', 103, 1, ''),
(195, '5.00', '10.00', '1500.00', '1500.00', '0000-00-00', '0.00', 104, 2, ''),
(196, '7.00', '9.00', '2100.00', '2700.00', '0000-00-00', '0.00', 104, 4, ''),
(197, '11.00', '12.33', '900.00', '1300.00', '0000-00-00', '0.00', 104, 6, ''),
(198, '7.23', '10.00', '969.00', '1500.00', '0000-00-00', '0.00', 104, 7, ''),
(199, '3.00', '5.00', '900.00', '1500.00', '0000-00-00', '0.00', 104, 16, ''),
(200, '8.00', '8.50', '2400.00', '2500.00', '0000-00-00', '0.00', 104, 17, ''),
(201, '15.00', '19.00', '2300.00', '3500.00', '0000-00-00', '0.00', 104, 3, ''),
(202, '3.00', '5.00', '900.00', '400.00', '0000-00-00', '0.00', 104, 5, ''),
(203, '21.00', '25.30', '2200.00', '3500.00', '0000-00-00', '0.00', 104, 1, ''),
(204, '10.00', '13.15', '1500.00', '2445.00', '0000-00-00', '0.00', 105, 2, ''),
(205, '9.00', '11.00', '0.00', '600.00', '0000-00-00', '0.00', 105, 4, ''),
(206, '12.33', '13.50', '-1.00', '350.00', '0000-00-00', '0.00', 105, 6, ''),
(207, '10.00', '13.00', '300.00', '1200.00', '0000-00-00', '0.00', 105, 7, ''),
(208, '5.00', '6.50', '0.00', '450.00', '0000-00-00', '0.00', 105, 16, ''),
(209, '8.50', '8.50', '50.00', '50.00', '0000-00-00', '0.00', 105, 17, ''),
(210, '19.00', '19.00', '0.00', '0.00', '0000-00-00', '0.00', 105, 3, ''),
(211, '5.00', '5.00', '1100.00', '1100.00', '0000-00-00', '0.00', 105, 5, ''),
(212, '25.30', '28.00', '-10.00', '10000.00', '0000-00-00', '0.00', 105, 1, ''),
(236, '35.00', '39.00', '-7100.00', '0.00', '0000-00-00', '0.00', 113, 1, ''),
(237, '20.00', '23.40', '-445.00', '0.00', '0000-00-00', '0.00', 113, 2, ''),
(238, '26.00', '29.00', '100.00', '0.00', '0000-00-00', '0.00', 113, 3, ''),
(239, '13.00', '15.33', '600.00', '0.00', '0000-00-00', '0.00', 113, 4, ''),
(240, '8.00', '10.20', '0.00', '0.00', '0000-00-00', '0.00', 113, 5, ''),
(241, '18.00', '21.50', '1350.00', '0.00', '0000-00-00', '0.00', 113, 6, ''),
(242, '17.00', '27.30', '1200.00', '0.00', '0000-00-00', '0.00', 113, 7, ''),
(243, '9.00', '12.55', '750.00', '0.00', '0000-00-00', '0.00', 113, 16, ''),
(244, '11.00', '13.90', '-250.00', '0.00', '0000-00-00', '0.00', 113, 17, ''),
(245, '39.00', '39.00', '-5900.00', '0.00', '0000-00-00', '0.00', 114, 1, ''),
(246, '23.40', '23.40', '575.00', '0.00', '0000-00-00', '0.00', 114, 2, ''),
(247, '29.00', '29.00', '1000.00', '0.00', '0000-00-00', '0.00', 114, 3, ''),
(248, '15.33', '15.33', '1299.00', '0.00', '0000-00-00', '0.00', 114, 4, ''),
(249, '10.20', '10.20', '660.00', '0.00', '0000-00-00', '0.00', 114, 5, ''),
(250, '21.50', '21.50', '2400.00', '0.00', '0000-00-00', '0.00', 114, 6, ''),
(251, '27.30', '27.30', '4290.00', '0.00', '0000-00-00', '0.00', 114, 7, ''),
(252, '12.55', '12.55', '1815.00', '0.00', '0000-00-00', '0.00', 114, 16, ''),
(253, '13.90', '13.90', '620.00', '0.00', '0000-00-00', '0.00', 114, 17, ''),
(254, '39.00', '39.00', '-5900.00', '0.00', '0000-00-00', '0.00', 117, 1, ''),
(255, '23.40', '23.40', '575.00', '0.00', '0000-00-00', '0.00', 117, 2, ''),
(256, '29.00', '29.00', '1000.00', '0.00', '0000-00-00', '0.00', 117, 3, ''),
(257, '15.33', '15.33', '1299.00', '0.00', '0000-00-00', '0.00', 117, 4, ''),
(258, '10.20', '10.20', '660.00', '0.00', '0000-00-00', '0.00', 117, 5, ''),
(259, '21.50', '21.50', '2400.00', '0.00', '0000-00-00', '0.00', 117, 6, ''),
(260, '27.30', '27.30', '4290.00', '0.00', '0000-00-00', '0.00', 117, 7, ''),
(261, '12.55', '12.55', '1815.00', '0.00', '0000-00-00', '0.00', 117, 16, ''),
(262, '13.90', '13.90', '620.00', '0.00', '0000-00-00', '0.00', 117, 17, ''),
(263, '39.00', '39.00', '-5900.00', '0.00', '0000-00-00', '0.00', 118, 1, ''),
(264, '23.40', '23.40', '575.00', '0.00', '0000-00-00', '0.00', 118, 2, ''),
(265, '29.00', '29.00', '1000.00', '0.00', '0000-00-00', '0.00', 118, 3, ''),
(266, '15.33', '15.33', '1299.00', '0.00', '0000-00-00', '0.00', 118, 4, ''),
(267, '10.20', '10.20', '660.00', '0.00', '0000-00-00', '0.00', 118, 5, ''),
(268, '21.50', '21.50', '2400.00', '0.00', '0000-00-00', '0.00', 118, 6, ''),
(269, '27.30', '27.30', '4290.00', '0.00', '0000-00-00', '0.00', 118, 7, ''),
(270, '12.55', '12.55', '1815.00', '0.00', '0000-00-00', '0.00', 118, 16, ''),
(271, '13.90', '13.90', '620.00', '0.00', '0000-00-00', '0.00', 118, 17, ''),
(272, '39.00', '41.80', '-5900.00', '0.00', '0000-00-00', '0.00', 119, 1, ''),
(273, '23.40', '27.00', '575.00', '0.00', '0000-00-00', '0.00', 119, 2, ''),
(274, '29.00', '33.60', '1000.00', '0.00', '0000-00-00', '0.00', 119, 3, ''),
(275, '15.33', '18.12', '1299.00', '0.00', '0000-00-00', '0.00', 119, 4, ''),
(276, '10.20', '13.10', '660.00', '0.00', '0000-00-00', '0.00', 119, 5, ''),
(277, '21.50', '22.00', '2400.00', '0.00', '0000-00-00', '0.00', 119, 6, ''),
(278, '27.30', '30.00', '4290.00', '0.00', '0000-00-00', '0.00', 119, 7, ''),
(279, '12.55', '15.90', '1815.00', '0.00', '0000-00-00', '0.00', 119, 16, ''),
(280, '13.90', '21.80', '620.00', '0.00', '0000-00-00', '0.00', 119, 17, '');

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

INSERT INTO `mois_facturation` (`id`, `mois`, `date_facturation`, `date_depot`, `id_constante`, `description`, `est_actif`) VALUES
(99, '2024-03', '0000-00-00', '0000-00-00', 6, '', 0),
(100, '2024-04', '0000-00-00', '2024-10-15', 6, '', 0),
(102, '2024-05', '0000-00-00', '2024-10-19', 6, '', 0),
(103, '2024-06', '0000-00-00', '0000-00-00', 6, 'releve manuelle', 0),
(104, '2024-07', '0000-00-00', '2024-11-07', 6, '', 0),
(105, '2024-08', '0000-00-00', '2024-10-29', 6, '', 0),
(113, '2024-12', '0000-00-00', '0000-00-00', 6, '', 0),
(114, '2024-09', '0000-00-00', '2024-11-08', 6, '', 0),
(117, '2023-03', '0000-00-00', '0000-00-00', 6, '', 0),
(118, '2023-07', '0000-00-00', '2024-11-08', 6, '', 0),
(119, '2025-01', '0000-00-00', '2024-11-11', 6, '', 1);

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

INSERT INTO `reseau` (`id`, `nom`, `abreviation`, `date_creation`, `description_reseau`) VALUES
(1, 'Bassesa', 'BASS', '2024-09-26', ''),
(2, 'Mbou', 'MB', '2024-09-26', ''),
(3, 'Folewi', 'FW', '2021-07-16', ''),
(4, 'RP', '', '2024-10-09', '');


create table impaye(
    id int(6) unsigned primary key auto_increment,
    montant int(6) unsigned not null,
    est_regle int(1) default false,
    id_facture int(4) unsigned not null,
    date_reglement date ,
    foreign key (id_facture) references facture(id) on delete cascade
) engine=innoDB;



--
-- Contraintes pour les tables exportées
--

--
-- Contraintes pour la table `abone`
--
ALTER TABLE `abone`
  ADD CONSTRAINT `abone_ibfk_1` FOREIGN KEY (`id_reseau`) REFERENCES `reseau` (`id`);


ALTER TABLE impaye
    add constraint kf_facture foreign key (id_facture) references facture(id) on delete cascade ;

--
-- Contraintes pour la table `facture`
--
ALTER TABLE `facture`
  ADD CONSTRAINT `facture_ibfk_1` FOREIGN KEY (`id_mois_facturation`) REFERENCES `mois_facturation` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `facture_ibfk_2` FOREIGN KEY (`id_abone`) REFERENCES `abone` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `mois_facturation`
--
ALTER TABLE `mois_facturation`
  ADD CONSTRAINT `mois_facturation_ibfk_1` FOREIGN KEY (`id_constante`) REFERENCES `constante_reseau` (`id`);
