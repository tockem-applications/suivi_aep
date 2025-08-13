-- phpMyAdmin SQL Dump
-- version 3.3.9
-- http://www.phpmyadmin.net
--
-- Serveur: localhost
-- Généré le : Jeu 01 Mai 2025 à 20:08
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
                                                                                                                     (1, 'Donkeng Maxime', '695854745', '100', 'actif', 0, 1),
                                                                                                                     (2, 'Tedonkeu Mireille', '652414878', '100', 'actif', 0, 1),
                                                                                                                     (3, 'Takam Ulrich', '654852515', '100', 'actif', 0, 1),
                                                                                                                     (4, 'Mbopda Ulrich', '658545152', '100', 'actif', 0, 2),
                                                                                                                     (5, 'Matefack Cedrick', '698979521', '100', 'actif', 0, 3);

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
                                     `numero_compte` varchar(32) DEFAULT NULL,
                                     `nom_banque` varchar(64) DEFAULT NULL,
                                     PRIMARY KEY (`id`)
) ENGINE=InnoDB  DEFAULT CHARSET=latin1 AUTO_INCREMENT=5 ;

--
-- Contenu de la table `aep`
--

INSERT INTO `aep` (`id`, `libele`, `description`, `fichier_facture`, `date`, `numero_compte`, `nom_banque`) VALUES
                                                                                                                (1, 'Bassessa', 'une fois de plus je recommence tout', 'model_nkongzem', '2020-05-12', NULL, NULL),
                                                                                                                (3, 'Triyin AEP', 'Mon AEP tototot', 'model_fokoue', '2024-12-12', '18547412', 'Mufid Bafou'),
                                                                                                                (4, 'Bandjoun', 'Celui que je vais juste supprimer', 'model_fokoue', '2024-12-12', '54784120541', 'Mufid Bafang');

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
-- Structure de la table `clefs`
--

CREATE TABLE IF NOT EXISTS `clefs` (
                                       `id` int(3) unsigned NOT NULL AUTO_INCREMENT,
                                       `value` varchar(32) NOT NULL,
                                       PRIMARY KEY (`id`),
                                       UNIQUE KEY `value` (`value`)
) ENGINE=MyISAM  DEFAULT CHARSET=latin1 AUTO_INCREMENT=4 ;

--
-- Contenu de la table `clefs`
--

INSERT INTO `clefs` (`id`, `value`) VALUES
                                        (1, 'Toto'),
                                        (2, 'Bami'),
                                        (3, 'Tami');

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
) ENGINE=InnoDB  DEFAULT CHARSET=latin1 AUTO_INCREMENT=8 ;

--
-- Contenu de la table `compteur`
--

INSERT INTO `compteur` (`id`, `numero_compteur`, `longitude`, `latitude`, `derniers_index`, `description`) VALUES
                                                                                                               (1, 'Bass00001', '0.000000', '0.000000', '21.50', ''),
                                                                                                               (2, 'Bass00002', '0.000000', '0.000000', '13.90', ''),
                                                                                                               (3, '00000020', '0.000000', '0.000000', '28.00', ''),
                                                                                                               (4, '000154', '0.000000', '0.000000', '18.30', ''),
                                                                                                               (5, '000154', '0.000000', '0.000000', '0.00', ''),
                                                                                                               (6, '15421613', '0.000000', '0.000000', '6.10', ''),
                                                                                                               (7, '654123', '0.000000', '0.000000', '3.60', '');

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
                                                             (3, 4),
                                                             (4, 6),
                                                             (5, 7);

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
    (1, 3);

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
                                                                                                                                                                (1, 500, 500, '0.00', '0000-00-00', 1, '', 1),
                                                                                                                                                                (2, 250, 250, '0.00', '0000-00-00', 1, '', 4);

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
) ENGINE=InnoDB  DEFAULT CHARSET=latin1 AUTO_INCREMENT=32 ;

--
-- Contenu de la table `facture`
--

INSERT INTO `facture` (`id`, `id_indexes`, `montant_verse`, `date_paiement`, `penalite`, `id_abone`, `message`) VALUES
                                                                                                                    (1, 1, '1735.00', '0000-00-00', '0.00', 1, ''),
                                                                                                                    (2, 2, '2300.00', '0000-00-00', '0.00', 2, ''),
                                                                                                                    (3, 3, '3050.00', '0000-00-00', '0.00', 3, ''),
                                                                                                                    (14, 18, '1300.00', '0000-00-00', '0.00', 1, ''),
                                                                                                                    (15, 19, '2300.00', '0000-00-00', '0.00', 2, ''),
                                                                                                                    (16, 20, '1350.00', '0000-00-00', '0.00', 3, ''),
                                                                                                                    (17, 22, '500.00', '0000-00-00', '0.00', 1, ''),
                                                                                                                    (18, 23, '500.00', '0000-00-00', '0.00', 2, ''),
                                                                                                                    (19, 24, '500.00', '0000-00-00', '0.00', 3, ''),
                                                                                                                    (20, 26, '2250.00', '0000-00-00', '0.00', 1, ''),
                                                                                                                    (21, 27, '1950.00', '0000-00-00', '0.00', 2, ''),
                                                                                                                    (22, 28, '3700.00', '0000-00-00', '0.00', 3, ''),
                                                                                                                    (23, 29, '1700.00', '0000-00-00', '0.00', 4, ''),
                                                                                                                    (24, 31, '1850.00', '0000-00-00', '0.00', 1, ''),
                                                                                                                    (25, 32, '1500.00', '0000-00-00', '0.00', 2, ''),
                                                                                                                    (26, 33, '1450.00', '0000-00-00', '0.00', 3, ''),
                                                                                                                    (27, 34, '1150.00', '0000-00-00', '0.00', 4, ''),
                                                                                                                    (28, 36, '1000.00', '0000-00-00', '0.00', 1, ''),
                                                                                                                    (29, 37, '0.00', '0000-00-00', '0.00', 2, ''),
                                                                                                                    (30, 38, '550.00', '0000-00-00', '0.00', 3, ''),
                                                                                                                    (31, 39, '0.00', '0000-00-00', '0.00', 4, '');

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
) ENGINE=InnoDB  DEFAULT CHARSET=latin1 AUTO_INCREMENT=73 ;

--
-- Contenu de la table `impaye`
--

INSERT INTO `impaye` (`id`, `montant`, `est_regle`, `id_facture`, `date_reglement`) VALUES
                                                                                        (71, 1400, 0, 29, '0000-00-00'),
                                                                                        (72, 100, 0, 28, '0000-00-00');

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
) ENGINE=InnoDB  DEFAULT CHARSET=latin1 AUTO_INCREMENT=41 ;

--
-- Contenu de la table `indexes`
--

INSERT INTO `indexes` (`id`, `id_compteur`, `id_mois_facturation`, `ancien_index`, `nouvel_index`, `message`) VALUES
                                                                                                                  (1, 1, 1, '10.03', '12.50', ''),
                                                                                                                  (2, 2, 1, '0.00', '3.60', ''),
                                                                                                                  (3, 4, 1, '0.00', '5.10', ''),
                                                                                                                  (4, 5, 1, '0.00', '0.00', ''),
                                                                                                                  (5, 3, 1, '5.30', '8.20', ''),
                                                                                                                  (18, 1, 5, '12.50', '14.10', ''),
                                                                                                                  (19, 2, 5, '3.60', '7.20', ''),
                                                                                                                  (20, 4, 5, '5.10', '6.80', ''),
                                                                                                                  (21, 3, 5, '11.10', '11.10', ''),
                                                                                                                  (22, 1, 6, '14.10', '14.10', ''),
                                                                                                                  (23, 2, 6, '7.20', '7.20', ''),
                                                                                                                  (24, 4, 6, '6.80', '6.80', ''),
                                                                                                                  (25, 3, 6, '11.10', '11.10', ''),
                                                                                                                  (26, 1, 7, '14.10', '17.60', ''),
                                                                                                                  (27, 2, 7, '7.20', '10.10', ''),
                                                                                                                  (28, 4, 7, '6.80', '13.20', ''),
                                                                                                                  (29, 6, 7, '1.50', '3.90', ''),
                                                                                                                  (30, 3, 7, '11.10', '13.09', ''),
                                                                                                                  (31, 1, 8, '17.60', '20.30', ''),
                                                                                                                  (32, 2, 8, '10.10', '12.10', ''),
                                                                                                                  (33, 4, 8, '13.20', '15.10', ''),
                                                                                                                  (34, 6, 8, '3.90', '5.20', ''),
                                                                                                                  (35, 3, 8, '13.09', '20.00', ''),
                                                                                                                  (36, 1, 9, '20.30', '21.50', ''),
                                                                                                                  (37, 2, 9, '12.10', '13.90', ''),
                                                                                                                  (38, 4, 9, '15.10', '18.30', ''),
                                                                                                                  (39, 6, 9, '5.20', '6.10', ''),
                                                                                                                  (40, 3, 9, '20.00', '28.00', '');

-- --------------------------------------------------------

--
-- Structure de la table `logs`
--

CREATE TABLE IF NOT EXISTS `logs` (
                                      `id` int(11) NOT NULL AUTO_INCREMENT,
                                      `user_id` int(11) DEFAULT NULL,
                                      `page_libelle` varchar(255) DEFAULT NULL,
                                      `action` varchar(50) DEFAULT NULL,
                                      `timestamp` datetime DEFAULT NULL,
                                      PRIMARY KEY (`id`)
) ENGINE=MyISAM  DEFAULT CHARSET=latin1 AUTO_INCREMENT=35 ;

--
-- Contenu de la table `logs`
--

INSERT INTO `logs` (`id`, `user_id`, `page_libelle`, `action`, `timestamp`) VALUES
                                                                                (1, NULL, 'page de role', 'update_role', '0000-00-00 00:00:00'),
                                                                                (2, NULL, 'role', 'add_role#4', '2025-04-22 18:54:20'),
                                                                                (3, NULL, 'role', 'add_role#5', '2025-04-22 18:55:00'),
                                                                                (4, NULL, 'role', 'add_role#6', '2025-04-22 18:56:03'),
                                                                                (5, NULL, 'role', 'add_role#7', '2025-04-22 18:57:36'),
                                                                                (6, NULL, 'role', 'delete_role', '0000-00-00 00:00:00'),
                                                                                (7, NULL, 'role', 'delete_role', '0000-00-00 00:00:00'),
                                                                                (8, NULL, 'role', 'delete_role', '0000-00-00 00:00:00'),
                                                                                (9, NULL, 'role', 'delete_role', '0000-00-00 00:00:00'),
                                                                                (10, NULL, 'role', 'delete_role#4', '0000-00-00 00:00:00'),
                                                                                (11, NULL, 'page de role', 'update_role', '0000-00-00 00:00:00'),
                                                                                (12, NULL, 'role_detail', 'remove_user_role#1', '2025-04-22 21:24:28'),
                                                                                (13, NULL, 'role_detail', 'add_user_role#1', '2025-04-22 21:25:06'),
                                                                                (14, NULL, 'role', 'delete_role#2', '0000-00-00 00:00:00'),
                                                                                (15, NULL, 'role', 'add_role#8', '2025-04-23 13:59:18'),
                                                                                (16, NULL, 'role', 'add_role#9', '2025-04-23 13:59:27'),
                                                                                (17, NULL, 'role_detail', 'remove_page_access#1', '2025-04-27 11:29:38'),
                                                                                (18, NULL, 'role_detail', 'remove_page_access#1', '2025-04-27 11:30:07'),
                                                                                (19, NULL, 'role_detail', 'add_page_access#1#1', '2025-04-27 11:33:17'),
                                                                                (20, NULL, 'role_detail', 'add_page_access#8#1', '2025-04-27 11:44:32'),
                                                                                (21, NULL, 'role_detail', 'add_user_role#8', '2025-04-27 11:48:16'),
                                                                                (22, NULL, 'role_detail', 'add_user_role#1', '2025-04-29 12:24:17'),
                                                                                (23, NULL, 'role_detail', 'add_page_access#8#3', '2025-04-29 13:01:16'),
                                                                                (24, NULL, 'role_detail', 'add_page_access#8#3', '2025-04-29 13:04:42'),
                                                                                (25, NULL, 'role_detail', 'add_page_access#8#3', '2025-04-29 13:05:35'),
                                                                                (26, NULL, 'role_detail', 'add_user_role#8', '2025-04-29 13:18:57'),
                                                                                (27, NULL, 'role_detail', 'remove_user_role#8', '2025-04-29 14:06:40'),
                                                                                (28, NULL, 'role_detail', 'remove_user_role#8', '2025-04-29 14:44:20'),
                                                                                (29, NULL, 'role_detail', 'remove_user_role#8', '2025-04-29 14:44:35'),
                                                                                (30, NULL, 'role_detail', 'remove_page_access#9', '2025-04-29 14:45:31'),
                                                                                (31, NULL, 'role_detail', 'remove_page_access#9', '2025-04-29 14:45:44'),
                                                                                (32, NULL, 'role_detail', 'remove_page_access#9', '2025-04-29 14:45:58'),
                                                                                (33, NULL, 'role', 'add_role#10', '2025-04-29 14:46:57'),
                                                                                (34, NULL, 'role', 'add_role#11', '2025-04-30 13:18:27');

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
) ENGINE=InnoDB  DEFAULT CHARSET=latin1 AUTO_INCREMENT=10 ;

--
-- Contenu de la table `mois_facturation`
--

INSERT INTO `mois_facturation` (`id`, `mois`, `date_facturation`, `date_depot`, `id_constante`, `description`, `est_actif`) VALUES
                                                                                                                                (1, '2025-01', '0000-00-00', '0000-00-00', 1, '', 0),
                                                                                                                                (5, '2025-02', '0000-00-00', '0000-00-00', 1, '', 0),
                                                                                                                                (6, '2025-03', '0000-00-00', '2025-04-02', 1, '', 0),
                                                                                                                                (7, '2025-04', '0000-00-00', '2025-04-30', 1, '', 0),
                                                                                                                                (8, '2025-05', '0000-00-00', '0000-00-00', 1, '', 0),
                                                                                                                                (9, '2025-06', '0000-00-00', '2025-05-01', 1, '', 1);

-- --------------------------------------------------------

--
-- Structure de la table `pages`
--

CREATE TABLE IF NOT EXISTS `pages` (
                                       `id` int(11) NOT NULL AUTO_INCREMENT,
                                       `chaine` varchar(255) NOT NULL,
                                       `libelle` varchar(255) NOT NULL,
                                       `description` text,
                                       PRIMARY KEY (`id`),
                                       UNIQUE KEY `unique_chaine` (`chaine`)
) ENGINE=MyISAM  DEFAULT CHARSET=latin1 AUTO_INCREMENT=62 ;

--
-- Contenu de la table `pages`
--

INSERT INTO `pages` (`id`, `chaine`, `libelle`, `description`) VALUES
                                                                   (24, 'page=role_detail', 'role_detail', ''),
                                                                   (25, 'form=aep', 'aep', ''),
                                                                   (26, 'page=home', 'home', ''),
                                                                   (27, 'page=reseau', 'reseau', ''),
                                                                   (28, 'form=reseau', 'reseau', ''),
                                                                   (29, 'form=abone', 'abone', ''),
                                                                   (30, 'list=compteur_reseau', 'compteur_reseau', ''),
                                                                   (31, 'list=distribution_simple', 'distribution_simple', ''),
                                                                   (32, 'list=production_simple', 'production_simple', ''),
                                                                   (33, 'list=recouvrement', 'recouvrement', ''),
                                                                   (34, 'list=facture_month', 'facture_month', ''),
                                                                   (35, 'list=liste_facture_month', 'liste_facture_month', ''),
                                                                   (36, 'list=mois_facturation', 'mois_facturation', ''),
                                                                   (37, 'form=constante_reseau', 'constante_reseau', ''),
                                                                   (38, 'form=import_index', 'import_index', ''),
                                                                   (39, 'list=releve_manuelle', 'releve_manuelle', ''),
                                                                   (40, 'form=finance', 'finance', ''),
                                                                   (41, 'list=transaction', 'transaction', ''),
                                                                   (42, 'form=login', 'login', ''),
                                                                   (43, 'form=register', 'register', ''),
                                                                   (44, 'form=role', 'role', ''),
                                                                   (45, 'page=register', 'register', ''),
                                                                   (46, 'page=role', 'role', ''),
                                                                   (47, 'page=login', 'login', ''),
                                                                   (48, 'page=aep', 'aep', ''),
                                                                   (49, 'page=edit_aep', 'edit_aep', ''),
                                                                   (50, 'page=logout', 'logout', ''),
                                                                   (51, 'list=logout', 'logout', ''),
                                                                   (52, 'page=toto', 'toto', ''),
                                                                   (53, 'page=download_index', 'download_index', ''),
                                                                   (54, 'page=info_abone', 'info_abone', ''),
                                                                   (55, 'page=clefs', 'clefs', ''),
                                                                   (56, 'page=user_details', 'user_details', ''),
                                                                   (57, 'page=redevance', 'redevance', ''),
                                                                   (58, 'page=versement', 'versement', ''),
                                                                   (59, 'page=clef', 'clef', ''),
                                                                   (60, 'page=users', 'users', ''),
                                                                   (61, 'page=page', 'page', '');

-- --------------------------------------------------------

--
-- Structure de la table `page_role_aep`
--

CREATE TABLE IF NOT EXISTS `page_role_aep` (
                                               `page_id` int(11) NOT NULL DEFAULT '0',
                                               `role_id` int(11) NOT NULL DEFAULT '0',
                                               `write_access` int(1) DEFAULT '0',
                                               PRIMARY KEY (`page_id`,`role_id`),
                                               KEY `role_id` (`role_id`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

--
-- Contenu de la table `page_role_aep`
--

INSERT INTO `page_role_aep` (`page_id`, `role_id`, `write_access`) VALUES
                                                                       (24, 1, 1),
                                                                       (25, 1, 1),
                                                                       (26, 1, 1),
                                                                       (27, 1, 1),
                                                                       (28, 1, 1),
                                                                       (53, 1, 1),
                                                                       (30, 1, 1),
                                                                       (31, 1, 1),
                                                                       (32, 1, 1),
                                                                       (33, 1, 1),
                                                                       (34, 1, 1),
                                                                       (35, 1, 1),
                                                                       (36, 1, 1),
                                                                       (37, 1, 1),
                                                                       (38, 1, 1),
                                                                       (39, 1, 1),
                                                                       (40, 1, 1),
                                                                       (41, 1, 1),
                                                                       (42, 1, 1),
                                                                       (43, 1, 1),
                                                                       (44, 1, 1),
                                                                       (45, 1, 1),
                                                                       (46, 1, 1),
                                                                       (29, 1, 1),
                                                                       (48, 1, 1),
                                                                       (49, 1, 1),
                                                                       (50, 1, 1),
                                                                       (51, 1, 1),
                                                                       (52, 1, 1),
                                                                       (53, 8, 0),
                                                                       (39, 8, 0),
                                                                       (38, 8, 1),
                                                                       (54, 1, 1),
                                                                       (36, 10, 0),
                                                                       (33, 10, 0),
                                                                       (26, 10, 0),
                                                                       (50, 9, 0),
                                                                       (43, 9, 0),
                                                                       (42, 9, 0),
                                                                       (41, 9, 0),
                                                                       (40, 9, 0),
                                                                       (26, 9, 0),
                                                                       (26, 8, 0),
                                                                       (54, 10, 0),
                                                                       (55, 1, 1),
                                                                       (56, 1, 1),
                                                                       (26, 11, 0),
                                                                       (57, 1, 1),
                                                                       (58, 1, 1),
                                                                       (59, 1, 1),
                                                                       (60, 1, 1),
                                                                       (61, 1, 1);

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
                                           `type` varchar(8) NOT NULL DEFAULT 'sortie',
                                           `mois_debut` varchar(8) DEFAULT '2025-03',
                                           PRIMARY KEY (`id`),
                                           KEY `fk_aep_redevance` (`id_aep`)
) ENGINE=InnoDB  DEFAULT CHARSET=latin1 AUTO_INCREMENT=5 ;

--
-- Contenu de la table `redevance`
--

INSERT INTO `redevance` (`id`, `libele`, `pourcentage`, `description`, `id_aep`, `type`, `mois_debut`) VALUES
                                                                                                           (2, 'CUE', '12.50', 'Rentre chez le comite des usagers de l''eau', 1, 'sortie', '2025-03'),
                                                                                                           (3, 'Mairie', '20.00', '', 1, 'sortie', '2025-02'),
                                                                                                           (4, 'toto', '10.00', '', 1, 'sortie', '2025-05');

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
) ENGINE=InnoDB  DEFAULT CHARSET=latin1 AUTO_INCREMENT=4 ;

--
-- Contenu de la table `reseau`
--

INSERT INTO `reseau` (`id`, `nom`, `abreviation`, `date_creation`, `description_reseau`, `id_aep`) VALUES
                                                                                                       (1, 'Reseau principal', 'RP', '2020-03-17', '', 1),
                                                                                                       (2, 'Reseau secondaire 1', 'Rs1', '2025-04-01', '', 1),
                                                                                                       (3, 'Reseau secondaire 2', 'Rs2', '2025-04-23', '', 1);

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
-- Structure de la table `roles`
--

CREATE TABLE IF NOT EXISTS `roles` (
                                       `id` int(11) NOT NULL AUTO_INCREMENT,
                                       `nom` varchar(50) NOT NULL,
                                       PRIMARY KEY (`id`)
) ENGINE=MyISAM  DEFAULT CHARSET=latin1 AUTO_INCREMENT=12 ;

--
-- Contenu de la table `roles`
--

INSERT INTO `roles` (`id`, `nom`) VALUES
                                      (1, 'Administrateur'),
                                      (8, 'Releveur'),
                                      (9, 'Comptable'),
                                      (10, 'Recouvreur'),
                                      (11, 'Visiteur');

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
-- Structure de la table `users`
--

CREATE TABLE IF NOT EXISTS `users` (
                                       `id` int(5) unsigned NOT NULL AUTO_INCREMENT,
                                       `email` varchar(32) NOT NULL,
                                       `nom` varchar(32) NOT NULL,
                                       `prenom` varchar(32) NOT NULL,
                                       `numero_telephone` varchar(16) NOT NULL,
                                       `password` varchar(64) DEFAULT NULL,
                                       `salt` varchar(64) DEFAULT NULL,
                                       PRIMARY KEY (`id`)
) ENGINE=MyISAM  DEFAULT CHARSET=latin1 AUTO_INCREMENT=10 ;

--
-- Contenu de la table `users`
--

INSERT INTO `users` (`id`, `email`, `nom`, `prenom`, `numero_telephone`, `password`, `salt`) VALUES
                                                                                                 (1, 'sd@gmail.com', 'slks aslk', 'M', '654152545', 'ef797c8118f02dfb', ''),
                                                                                                 (2, 'tockemapplications@gmail.com', 'Tsafack', 'Erick', '654190514', 'ef797c8118f02dfb', ''),
                                                                                                 (3, 'tockemapplications2@gmail.com', 'sncdsl;n', 'sldnlds', '25412545', 'e150f4455ed28e8c', ''),
                                                                                                 (4, 'tockemapplications3@gmail.com', 'sksk', 'sksk', '6565656565', 'aa005661a830c458', 'ae8604e73a48e7022190406c201ca358'),
                                                                                                 (5, 'tockemapplications5@gmail.com', 'olol', 'lolo', '653636363', 'f916e27059dc0b41', '5bbb445a4f503f8234ecbe37903f0c24'),
                                                                                                 (6, 'tockemapplications6@gmail.com', 'opopo', 'poioi', '658585959', '3270175410fe1cc8ee5633e04a14fcad56ee04bbaa5329604385bed55fe1098d', '15f7ce4388a49a9be16cf70015ec2f9e'),
                                                                                                 (7, 'tockemapplications0@gmail.com', 'Takam Ulrich', 'Erick', '6565656565', '1f3b595625daad307953bb1153055ef9d28ffa2bbde1dc384c6d109fca6d552a', '7a85e01b353c290533d2deda2c84805a'),
                                                                                                 (8, 'ericktsafack@gmail.com', 'Tsafack', 'Erick', '654190514', 'fb259da39527c902bcbd1161b579ab84cb416e59208f637819e72f30e11751e1', '2abf440fceec6545a861fd6a3b52a819'),
                                                                                                 (9, 'bonjour@gmail.com', 'Mezankeu', 'Darelle', '658595653', '66b69e627ba0867e07b680bdc8966ded1c0d97edc8e079cc3a1ca6936b4a7cee', '249324c1cb4a556af910a1d2ef1eb9e2');

-- --------------------------------------------------------

--
-- Structure de la table `user_clefs`
--

CREATE TABLE IF NOT EXISTS `user_clefs` (
                                            `clef_id` int(3) unsigned NOT NULL,
                                            `user_id` int(3) unsigned NOT NULL,
                                            PRIMARY KEY (`clef_id`),
                                            KEY `user_id` (`user_id`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

--
-- Contenu de la table `user_clefs`
--

INSERT INTO `user_clefs` (`clef_id`, `user_id`) VALUES
                                                    (3, 8),
                                                    (2, 9);

-- --------------------------------------------------------

--
-- Structure de la table `user_roles`
--

CREATE TABLE IF NOT EXISTS `user_roles` (
                                            `user_id` int(11) NOT NULL DEFAULT '0',
                                            `role_id` int(11) NOT NULL DEFAULT '0',
                                            PRIMARY KEY (`user_id`,`role_id`),
                                            KEY `role_id` (`role_id`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

--
-- Contenu de la table `user_roles`
--

INSERT INTO `user_roles` (`user_id`, `role_id`) VALUES
                                                    (1, 1),
                                                    (7, 1),
                                                    (8, 8),
                                                    (9, 8),
                                                    (9, 10);

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


-- --------------------------------------------------------

--
-- Structure de la table `versements`
--

CREATE TABLE IF NOT EXISTS `versements` (
                                            `id` int(4) unsigned NOT NULL AUTO_INCREMENT,
                                            `montant` decimal(10,2) NOT NULL,
                                            `date_versement` date NOT NULL,
                                            `id_mois_facturation` int(4) unsigned NOT NULL,
                                            `id_redevance` int(4) unsigned NOT NULL,
                                            PRIMARY KEY (`id`),
                                            KEY `id_mois_facturation` (`id_mois_facturation`),
                                            KEY `id_redevance` (`id_redevance`)
) ENGINE=MyISAM  DEFAULT CHARSET=latin1 AUTO_INCREMENT=7 ;

--
-- Contenu de la table `versements`
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


create view facture_sum2 as
    select m.id as id_mois_facturation, sum(nouvel_index - ancien_index) as conso,
           sum(montant_verse) as montant_verse, c.id_aep,
           prix_metre_cube_eau, prix_entretient_compteur, count(f.id) nombre, m.mois
    from facture f
          inner join indexes i on f.id_indexes = i.id
          inner join mois_facturation m on i.id_mois_facturation = m.id
          inner join constante_reseau as c on m.id_constante = c.id
    group by m.id
    order by m.mois;

SELECT m.id as id_mois_facturation, r.id as id_redevance,  sum(nouvel_index - ancien_index) as conso,
       sum(montant_verse) as montant_verse, sum(montant) as montant_remis,
       prix_metre_cube_eau, prix_entretient_compteur, r.libele, count(f.id) nombre, m.mois, pourcentage
from facture f
         inner join indexes i on f.id_indexes = i.id
         inner join mois_facturation m on i.id_mois_facturation = m.id
         inner join constante_reseau as c on m.id_constante = c.id
         inner join redevance r on c.id_aep = r.id_aep
         left join  versements v on v.id_redevance = r.id and v.id_mois_facturation = m.id
where c.id_aep=? and m.mois >= r.mois_debut
group by r.id, m.id
order by m.mois;