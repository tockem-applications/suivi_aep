-- phpMyAdmin SQL Dump
-- version 3.3.9
-- http://www.phpmyadmin.net
--
-- Serveur: localhost
-- Généré le : Lun 06 Janvier 2025 à 13:01
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


drop database suivi_aep_fokoue;
create database suivi_aep_fokoue;

use suivi_aep_fokoue;

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
    ) ENGINE=InnoDB  DEFAULT CHARSET=latin1 AUTO_INCREMENT=201 ;

--
-- Contenu de la table `abone`
--

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
    ) ENGINE=InnoDB  DEFAULT CHARSET=latin1 AUTO_INCREMENT=16 ;

--
-- Contenu de la table `constante_reseau`
--

-- --------------------------------------------------------
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
) ENGINE=InnoDB  DEFAULT CHARSET=latin1 AUTO_INCREMENT=135 ;

--
-- Structure de la table `facture`
--

CREATE TABLE IF NOT EXISTS `facture` (
                                         `id` int(4) unsigned NOT NULL AUTO_INCREMENT,
    `ancien_index` decimal(7,2) unsigned NOT NULL,
    `nouvel_index` decimal(7,2) unsigned NOT NULL,
    `montant_verse` decimal(8,2) DEFAULT '0.00',
    `date_paiement` date DEFAULT NULL,
    `penalite` decimal(8,2) DEFAULT '0.00',
    `id_mois_facturation` int(3) unsigned NOT NULL,
    `id_abone` int(4) unsigned NOT NULL,
    `message` text,
    PRIMARY KEY (`id`),
    KEY `id_mois_facturation` (`id_mois_facturation`),
    KEY `id_abone` (`id_abone`)
    ) ENGINE=InnoDB  DEFAULT CHARSET=latin1 AUTO_INCREMENT=1 ;

--
-- Contenu de la table `facture`
--

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
    ) ENGINE=InnoDB  DEFAULT CHARSET=latin1 AUTO_INCREMENT=6 ;

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
    ) ENGINE=InnoDB  DEFAULT CHARSET=latin1 AUTO_INCREMENT=70 ;

--
-- Contenu de la table `impaye`
--

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
    ) ENGINE=InnoDB  DEFAULT CHARSET=latin1 AUTO_INCREMENT=8 ;

--
-- Contenu de la table `reseau`
--

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



create table compteur(
     id integer(5) unsigned primary key auto_increment,
     numero_compteur varchar(16) not null,
     longitude decimal(12, 6),
     latitude decimal(12, 6),
     derniers_index decimal(7,2) not null,
     description text(1000)
) engine = innoDB;

create table compteur_abone(
       id_abone integer(5) unsigned not null,
       id_compteur integer(5) unsigned not null,
       constraint fk_abone_compteur_abone foreign key (id_abone) references abone(id) on delete CASCADE ,
       constraint fk_compteur_compteur_abone foreign key (id_compteur) references compteur(id) on delete cascade
)engine=innoDB;


create table position_compteur_aep(
      id integer(2) unsigned primary key auto_increment,
      position varchar(32) not null
)engine = innoDB;


create table compteur_aep(
     id_aep integer(4) unsigned not null,
     id_compteur integer(5) unsigned not null,
     id_position integer(2) unsigned not null,
     constraint fk_position_compteur_aep foreign key (id_position) references position_compteur_aep(id),
     constraint fk_aep_compteur_aep foreign key (id_aep) references aep(id),
     constraint fk_compteur_compteur_aep foreign key (id_compteur) references compteur(id)
)engine=innoDB;



create table compteur_reseau(
    id_reseau integer(5) unsigned not null,
    id_compteur integer(5) unsigned not null,
    constraint fk_reseau_compteur_reseau foreign key (id_reseau) references reseau(id),
    constraint fk_compteur_compteur_reseau foreign key (id_compteur) references compteur(id)
)engine=innoDB;


create table decompte_aep(
    id_aep integer(5) unsigned not null,
    id_mois_facturation integer(6) unsigned not null,
    ancien_index decimal(7,2) not null,
    nouvel_index decimal(7,2) not null default 0.0,
    message text(1000) ,
    constraint fk_aep_decompte_aep foreign key (id_aep) references aep(id),
    constraint fk_mois_facturation_decompte_aep foreign key (id_mois_facturation) references mois_facturation(id)
)engine = innoDB;


create table decompte_reseau(
     id_aep integer(5) unsigned not null,
     id_mois_facturation integer(6) unsigned not null,
     ancien_index decimal(7,2) not null,
     nouvel_index decimal(7,2) not null default 0.0,
     message text(1000) ,
     constraint fk_aep_decompte_aep foreign key (id_aep) references aep(id),
     constraint fk_mois_facturation_decompte_aep foreign key (id_mois_facturation) references mois_facturation(id)
)engine = innoDB;


ALTER TABLE `abone`
    ADD CONSTRAINT `abone_ibfk_1` FOREIGN KEY (`id_reseau`) REFERENCES `reseau` (`id`);

--
-- Contraintes pour la table `avoir_role`
--
ALTER TABLE `avoir_role`
    ADD CONSTRAINT `fk_role_avoir_role` FOREIGN KEY (`id_role`) REFERENCES `aep` (`id`),
  ADD CONSTRAINT `fk_utilisateur_avoir_role` FOREIGN KEY (`id_utilisateur`) REFERENCES `utilisateur` (`id`);

--
-- Contraintes pour la table `constante_reseau`
--
ALTER TABLE `constante_reseau`
    ADD CONSTRAINT `fk_aep_constante` FOREIGN KEY (`id_aep`) REFERENCES `aep` (`id`);

--
-- Contraintes pour la table `facture`
--
ALTER TABLE `facture`
    ADD CONSTRAINT `facture_ibfk_1` FOREIGN KEY (`id_mois_facturation`) REFERENCES `mois_facturation` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `facture_ibfk_2` FOREIGN KEY (`id_abone`) REFERENCES `abone` (`id`) ON DELETE CASCADE;

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


#
#
# select a.nom, count(f.id) duree, r.nom reseau, numero_telephone, r.id as id_reseau, sum(nouvel_index - ancien_index)
#                           consommation, derniers_index , sum(i.montant) as impaye,  numero_compteur, sum(montant_verse) as montant_verse,
#        max(date_paiement) as date_paiement, a.etat , 'distribution' as type_compteur
# from abone a
#          inner join compteur_abone c_ab on c_ab.id_abone = a.id
#          inner join compteur c on c.id = c_ab.id_compteur
#          left join facture f on a.id = f.id_abone
#          left join impaye i on i.id_facture = f.id
#          inner join reseau r on r.id = a.id_reseau
# where a.id=207;
#
#
#
# select a.nom, count(f.id) as duree
# from abone a
# #          inner join compteur_abone c_ab on c_ab.id_abone = a.id
# #          inner join compteur c on c.id = c_ab.id_compteur
#          inner join facture f on a.id = f.id_abone
#          left join impaye i on i.id_facture = f.id
#          inner join reseau r on r.id = a.id_reseau
# where a.id=207;
#
#
# select * from abone a
#         inner join compteur_abone c_ab on c_ab.id_abone = a.id
#         inner join compteur c on c.id = c_ab.id_compteur
#         left join facture f on a.id = f.id_abone

update abone a
    inner join compteur_abone c_ab on c_ab.id_abone = a.id
    inner join compteur co on co.id = c_ab.id_compteur
set derniers_index=1 where 1 =1;


