-- phpMyAdmin SQL Dump
-- version 3.3.9
-- http://www.phpmyadmin.net
--
-- Serveur: localhost
-- Généré le : Dim 12 Janvier 2025 à 20:51
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
    ) ENGINE=InnoDB  DEFAULT CHARSET=latin1 AUTO_INCREMENT=3 ;

--
-- Contenu de la table `abone`
--

INSERT INTO `abone` (`id`, `nom`, `numero_telephone`, `numero_compte_anticipation`, `etat`, `rang`, `id_reseau`) VALUES
                                                                                                                     (1, 'Tsafack Nteudem', '6541258745', '13', 'actif', 0, 1),
                                                                                                                     (2, 'Takendjeu Eddy', '6587451215', '12', 'actif', 0, 1);

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
    ) ENGINE=InnoDB  DEFAULT CHARSET=latin1 AUTO_INCREMENT=2 ;

--
-- Contenu de la table `aep`
--

INSERT INTO `aep` (`id`, `libele`, `description`, `fichier_facture`, `date`) VALUES
    (1, 'Banki', 'Mon Aep', 'model_fokoue', '2019-02-12');

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
    ) ENGINE=InnoDB  DEFAULT CHARSET=latin1 AUTO_INCREMENT=3 ;

--
-- Contenu de la table `compteur`
--

INSERT INTO `compteur` (`id`, `numero_compteur`, `longitude`, `latitude`, `derniers_index`, `description`) VALUES
                                                                                                               (1, '00000020', '0.000000', '0.000000', '22.70', ''),
                                                                                                               (2, '12014658', '0.000000', '0.000000', '5.40', '');

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
                                                             (2, 2);

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
    ) ENGINE=InnoDB  DEFAULT CHARSET=latin1 AUTO_INCREMENT=2 ;

--
-- Contenu de la table `constante_reseau`
--

INSERT INTO `constante_reseau` (`id`, `prix_metre_cube_eau`, `prix_entretient_compteur`, `prix_tva`, `date_creation`, `est_actif`, `description`, `id_aep`) VALUES
    (1, 200, 200, '0.00', '0000-00-00', 1, '', 1);

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
    ) ENGINE=InnoDB  DEFAULT CHARSET=latin1 AUTO_INCREMENT=4 ;

--
-- Contenu de la table `facture`
--

INSERT INTO `facture` (`id`, `id_indexes`, `montant_verse`, `date_paiement`, `penalite`, `id_abone`, `message`) VALUES
                                                                                                                    (1, 2, '0.00', '0000-00-00', '0.00', 1, ''),
                                                                                                                    (2, 3, '0.00', '0000-00-00', '0.00', 1, ''),
                                                                                                                    (3, 4, '0.00', '0000-00-00', '0.00', 2, '');

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
    `montant` int(6) unsigned NOT NULL,
    `est_regle` int(1) DEFAULT '0',
    `id_facture` int(4) unsigned NOT NULL,
    `date_reglement` date DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `id_facture` (`id_facture`)
    ) ENGINE=InnoDB  DEFAULT CHARSET=latin1 AUTO_INCREMENT=2 ;

--
-- Contenu de la table `impaye`
--

INSERT INTO `impaye` (`id`, `montant`, `est_regle`, `id_facture`, `date_reglement`) VALUES
    (1, 1220, 0, 1, '0000-00-00');

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
    ) ENGINE=InnoDB  DEFAULT CHARSET=latin1 AUTO_INCREMENT=5 ;

--
-- Contenu de la table `indexes`
--

INSERT INTO `indexes` (`id`, `id_compteur`, `id_mois_facturation`, `ancien_index`, `nouvel_index`, `message`) VALUES
                                                                                                                  (2, 1, 2, '15.20', '20.30', ''),
                                                                                                                  (3, 1, 3, '20.30', '22.70', ''),
                                                                                                                  (4, 2, 3, '2.10', '5.40', '');

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
    ) ENGINE=InnoDB  DEFAULT CHARSET=latin1 AUTO_INCREMENT=4 ;

--
-- Contenu de la table `mois_facturation`
--

INSERT INTO `mois_facturation` (`id`, `mois`, `date_facturation`, `date_depot`, `id_constante`, `description`, `est_actif`) VALUES
                                                                                                                                (2, '2025-02', '0000-00-00', '2025-01-10', 1, '', 0),
                                                                                                                                (3, '2025-03', '0000-00-00', '2025-01-12', 1, '', 1);

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
    ) ENGINE=InnoDB  DEFAULT CHARSET=latin1 AUTO_INCREMENT=3 ;

--
-- Contenu de la table `reseau`
--

INSERT INTO `reseau` (`id`, `nom`, `abreviation`, `date_creation`, `description_reseau`, `id_aep`) VALUES
                                                                                                       (1, 'Reseau principal', 'RP', '2025-01-10', 'oui oui oui', 1),
                                                                                                       (2, 'Reseau secondaire 1', 'Rs1', '2025-01-12', '', 1);

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
    ADD CONSTRAINT `fk_reseau_compteur_reseau` FOREIGN KEY (`id_reseau`) REFERENCES `reseau` (`id`),
  ADD CONSTRAINT `fk_compteur_compteur_reseau` FOREIGN KEY (`id_compteur`) REFERENCES `compteur` (`id`);

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
