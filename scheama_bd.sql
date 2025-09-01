-- Export SQL généré par l'application (PHP)
SET NAMES utf8;
SET
FOREIGN_KEY_CHECKS=0;


-- -----------------------------
-- Structure de la table `abone`
-- -----------------------------
DROP TABLE IF EXISTS `abone`;
CREATE TABLE `abone`
(
    `id`                         int(4) unsigned NOT NULL AUTO_INCREMENT,
    `nom`                        varchar(128) NOT NULL,
    `numero_telephone`           varchar(16)  NOT NULL,
    `numero_compte_anticipation` varchar(16)  NOT NULL,
    `etat`                       varchar(10)  NOT NULL,
    `rang`                       int(4) DEFAULT NULL,
    `id_reseau`                  int(3) unsigned NOT NULL,
    PRIMARY KEY (`id`),
    KEY                          `id_reseau` (`id_reseau`),
    CONSTRAINT `abone_ibfk_1` FOREIGN KEY (`id_reseau`) REFERENCES `reseau` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=latin1;

-- Données de `abone`
-- -----------------------------
-- Structure de la table `aep`
-- -----------------------------
DROP TABLE IF EXISTS `aep`;
CREATE TABLE `aep`
(
    `id`              int(4) unsigned NOT NULL AUTO_INCREMENT,
    `libele`          varchar(64) NOT NULL,
    `description`     text,
    `fichier_facture` varchar(64) DEFAULT NULL,
    `date`            date        DEFAULT NULL,
    `numero_compte`   varchar(32) DEFAULT NULL,
    `nom_banque`      varchar(64) DEFAULT NULL,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=latin1;


-- -----------------------------
-- Structure de la table `clefs`
-- -----------------------------
DROP TABLE IF EXISTS `clefs`;
CREATE TABLE `clefs`
(
    `id`    int(3) unsigned NOT NULL AUTO_INCREMENT,
    `value` varchar(32) NOT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `value` (`value`)
) ENGINE=MyISAM AUTO_INCREMENT=13 DEFAULT CHARSET=latin1;


-- -----------------------------
-- Structure de la table `compteur`
-- -----------------------------
DROP TABLE IF EXISTS `compteur`;
CREATE TABLE `compteur`
(
    `id`              int(5) unsigned NOT NULL AUTO_INCREMENT,
    `numero_compteur` varchar(16)   NOT NULL,
    `longitude`       decimal(12, 6) DEFAULT NULL,
    `latitude`        decimal(12, 6) DEFAULT NULL,
    `derniers_index`  decimal(7, 2) NOT NULL,
    `description`     text,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=latin1;



-- -----------------------------
-- Structure de la table `compteur_abone`
-- -----------------------------
DROP TABLE IF EXISTS `compteur_abone`;
CREATE TABLE `compteur_abone`
(
    `id_abone`    int(5) unsigned NOT NULL,
    `id_compteur` int(5) unsigned NOT NULL,
    KEY           `fk_abone_compteur_abone` (`id_abone`),
    KEY           `fk_compteur_compteur_abone` (`id_compteur`),
    CONSTRAINT `fk_abone_compteur_abone` FOREIGN KEY (`id_abone`) REFERENCES `abone` (`id`),
    CONSTRAINT `fk_compteur_compteur_abone` FOREIGN KEY (`id_compteur`) REFERENCES `compteur` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;



-- -----------------------------
-- Structure de la table `compteur_aep`
-- -----------------------------
DROP TABLE IF EXISTS `compteur_aep`;
CREATE TABLE `compteur_aep`
(
    `id_aep`      int(4) unsigned NOT NULL,
    `id_compteur` int(5) unsigned NOT NULL,
    `id_position` int(2) unsigned NOT NULL,
    KEY           `fk_position_compteur_aep` (`id_position`),
    KEY           `fk_aep_compteur_aep` (`id_aep`),
    KEY           `fk_compteur_compteur_aep` (`id_compteur`),
    CONSTRAINT `fk_aep_compteur_aep` FOREIGN KEY (`id_aep`) REFERENCES `aep` (`id`),
    CONSTRAINT `fk_compteur_compteur_aep` FOREIGN KEY (`id_compteur`) REFERENCES `compteur` (`id`),
    CONSTRAINT `fk_position_compteur_aep` FOREIGN KEY (`id_position`) REFERENCES `position_compteur_aep` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- Données de `compteur_aep`

-- -----------------------------
-- Structure de la table `compteur_reseau`
-- -----------------------------
DROP TABLE IF EXISTS `compteur_reseau`;
CREATE TABLE `compteur_reseau`
(
    `id_reseau`   int(5) unsigned NOT NULL,
    `id_compteur` int(5) unsigned NOT NULL,
    KEY           `fk_reseau_compteur_reseau` (`id_reseau`),
    KEY           `fk_compteur_compteur_reseau` (`id_compteur`),
    CONSTRAINT `fk_compteur_compteur_reseau` FOREIGN KEY (`id_compteur`) REFERENCES `compteur` (`id`),
    CONSTRAINT `fk_reseau_compteur_reseau` FOREIGN KEY (`id_reseau`) REFERENCES `reseau` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;


-- -----------------------------
-- Structure de la table `constante_reseau`
-- -----------------------------
DROP TABLE IF EXISTS `constante_reseau`;
CREATE TABLE `constante_reseau`
(
    `id`                       int(2) unsigned NOT NULL AUTO_INCREMENT,
    `prix_metre_cube_eau`      int(5) unsigned NOT NULL,
    `prix_entretient_compteur` int(5) unsigned NOT NULL,
    `prix_tva`                 decimal(7, 2) unsigned NOT NULL,
    `date_creation`            date NOT NULL,
    `est_actif`                tinyint(1) NOT NULL,
    `description`              text,
    `id_aep`                   int(4) unsigned NOT NULL DEFAULT '1',
    PRIMARY KEY (`id`),
    KEY                        `fk_aep_constante` (`id_aep`),
    CONSTRAINT `fk_aep_constante` FOREIGN KEY (`id_aep`) REFERENCES `aep` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=latin1;

-- -----------------------------
-- Structure de la table `facture`
-- -----------------------------
DROP TABLE IF EXISTS `facture`;
CREATE TABLE `facture`
(
    `id`            int(4) unsigned NOT NULL AUTO_INCREMENT,
    `id_indexes`    int(6) unsigned NOT NULL,
    `montant_verse` decimal(15, 2) DEFAULT '0.00',
    `date_paiement` date           DEFAULT NULL,
    `penalite`      decimal(15, 2) DEFAULT '0.00',
    `id_abone`      int(4) unsigned NOT NULL,
    `message`       text,
    PRIMARY KEY (`id`),
    KEY             `id_abone` (`id_abone`),
    KEY             `fk_index_facture` (`id_indexes`),
    CONSTRAINT `facture_ibfk_2` FOREIGN KEY (`id_abone`) REFERENCES `abone` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_index_facture` FOREIGN KEY (`id_indexes`) REFERENCES `indexes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=122 DEFAULT CHARSET=latin1;


-- -----------------------------
-- Structure de la table `flux_financier`
-- -----------------------------
DROP TABLE IF EXISTS `flux_financier`;
CREATE TABLE `flux_financier`
(
    `id`          int(6) unsigned NOT NULL AUTO_INCREMENT,
    `date`        date         NOT NULL,
    `mois`        varchar(7)   NOT NULL,
    `libele`      varchar(128) NOT NULL,
    `prix`        bigint(20) unsigned NOT NULL,
    `type`        varchar(8)   NOT NULL DEFAULT 'sortie',
    `description` text,
    `id_aep`      int(5) unsigned NOT NULL DEFAULT '1',
    PRIMARY KEY (`id`),
    KEY           `fk_aep_flux_financier` (`id_aep`),
    CONSTRAINT `fk_aep_flux_financier` FOREIGN KEY (`id_aep`) REFERENCES `aep` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=latin1;

-- -----------------------------
-- Structure de la table `impaye`
-- -----------------------------
DROP TABLE IF EXISTS `impaye`;
CREATE TABLE `impaye`
(
    `id`             int(6) unsigned NOT NULL AUTO_INCREMENT,
    `montant`        bigint(20) DEFAULT NULL,
    `est_regle`      int(1) DEFAULT '0',
    `id_facture`     int(4) unsigned NOT NULL,
    `date_reglement` date DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY              `id_facture` (`id_facture`),
    CONSTRAINT `impaye_ibfk_1` FOREIGN KEY (`id_facture`) REFERENCES `facture` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=latin1;


-- -----------------------------
-- Structure de la table `indexes`
-- -----------------------------
DROP TABLE IF EXISTS `indexes`;
CREATE TABLE `indexes`
(
    `id`                  int(6) unsigned NOT NULL AUTO_INCREMENT,
    `id_compteur`         int(5) unsigned NOT NULL,
    `id_mois_facturation` int(3) unsigned NOT NULL,
    `ancien_index`        decimal(8, 2) NOT NULL,
    `nouvel_index`        decimal(7, 2) unsigned NOT NULL,
    `message`             text,
    PRIMARY KEY (`id`),
    KEY                   `fk_compteur_indexes` (`id_compteur`),
    KEY                   `fk_mois_facturatio_indexes` (`id_mois_facturation`),
    CONSTRAINT `fk_compteur_indexes` FOREIGN KEY (`id_compteur`) REFERENCES `compteur` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_mois_facturatio_indexes` FOREIGN KEY (`id_mois_facturation`) REFERENCES `mois_facturation` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=144 DEFAULT CHARSET=latin1;


-- -----------------------------
-- Structure de la table `intervention_rh`
-- -----------------------------
DROP TABLE IF EXISTS `intervention_rh`;
CREATE TABLE `intervention_rh`
(
    `intervention_id`       int(11) NOT NULL,
    `rh_id`                 int(11) NOT NULL,
    `role_sur_intervention` varchar(120)   DEFAULT NULL,
    `heures_prevues`        decimal(12, 2) DEFAULT '0.00',
    `heures_reelles`        decimal(12, 2) DEFAULT '0.00',
    `cout_prevu`            decimal(14, 2) DEFAULT '0.00',
    `cout_reel`             decimal(14, 2) DEFAULT '0.00',
    PRIMARY KEY (`intervention_id`, `rh_id`),
    KEY                     `idx_irh_rh` (`rh_id`),
    CONSTRAINT `fk_irh_intervention` FOREIGN KEY (`intervention_id`) REFERENCES `interventions` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_irh_rh` FOREIGN KEY (`rh_id`) REFERENCES `ressources_humaines` (`id`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- Données de `intervention_rh`

-- -----------------------------
-- Structure de la table `intervention_rm`
-- -----------------------------
DROP TABLE IF EXISTS `intervention_rm`;
CREATE TABLE `intervention_rm`
(
    `intervention_id` int(11) NOT NULL,
    `rm_id`           int(11) NOT NULL,
    `quantite_prevue` decimal(12, 2) DEFAULT '0.00',
    `quantite_reelle` decimal(12, 2) DEFAULT '0.00',
    `cout_prevu`      decimal(14, 2) DEFAULT '0.00',
    `cout_reel`       decimal(14, 2) DEFAULT '0.00',
    PRIMARY KEY (`intervention_id`, `rm_id`),
    KEY               `idx_irm_rm` (`rm_id`),
    CONSTRAINT `fk_irm_intervention` FOREIGN KEY (`intervention_id`) REFERENCES `interventions` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_irm_rm` FOREIGN KEY (`rm_id`) REFERENCES `ressources_materielles` (`id`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- Données de `intervention_rm`

-- -----------------------------
-- Structure de la table `interventions`
-- -----------------------------
DROP TABLE IF EXISTS `interventions`;
CREATE TABLE `interventions`
(
    `id`                int(11) NOT NULL AUTO_INCREMENT,
    `aep_id`            int(11) NOT NULL,
    `titre`             varchar(200) NOT NULL,
    `type`              varchar(100) NOT NULL,
    `description`       text,
    `localisation`      varchar(200)   DEFAULT NULL,
    `date_debut_prevue` datetime       DEFAULT NULL,
    `date_fin_prevue`   datetime       DEFAULT NULL,
    `date_debut_reelle` datetime       DEFAULT NULL,
    `date_fin_reelle`   datetime       DEFAULT NULL,
    `statut`            enum('planifiee','en_cours','terminee','annulee') DEFAULT 'planifiee',
    `cout_estime`       decimal(14, 2) DEFAULT '0.00',
    `cout_reel`         decimal(14, 2) DEFAULT '0.00',
    `created_by`        int(11) DEFAULT NULL,
    `created_at`        datetime       DEFAULT NULL,
    `updated_at`        datetime       DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY                 `idx_interv_aep` (`aep_id`),
    KEY                 `idx_interv_statut` (`statut`),
    KEY                 `idx_interv_dates` (`date_debut_prevue`,`date_fin_prevue`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8;

-- -----------------------------
-- Structure de la table `logs`
-- -----------------------------
DROP TABLE IF EXISTS `logs`;
CREATE TABLE `logs`
(
    `id`           int(11) NOT NULL AUTO_INCREMENT,
    `user_id`      int(11) DEFAULT NULL,
    `page_libelle` varchar(255) DEFAULT NULL,
    `action`       varchar(50)  DEFAULT NULL,
    `timestamp`    datetime     DEFAULT NULL,
    PRIMARY KEY (`id`)
) ENGINE=MyISAM AUTO_INCREMENT=45 DEFAULT CHARSET=latin1;



-- -----------------------------
-- Structure de la table `mois_facturation`
-- -----------------------------
DROP TABLE IF EXISTS `mois_facturation`;
CREATE TABLE `mois_facturation`
(
    `id`               int(3) unsigned NOT NULL AUTO_INCREMENT,
    `mois`             varchar(32) NOT NULL,
    `date_facturation` date        NOT NULL,
    `date_depot`       date        NOT NULL,
    `id_constante`     int(2) unsigned NOT NULL,
    `description`      text,
    `est_actif`        tinyint(1) NOT NULL,
    `date_releve`      date DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `unique_id_mois` (`id`,`mois`),
    KEY                `id_constante` (`id_constante`),
    CONSTRAINT `mois_facturation_ibfk_1` FOREIGN KEY (`id_constante`) REFERENCES `constante_reseau` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=25 DEFAULT CHARSET=latin1;


-- -----------------------------
-- Structure de la table `page_role_aep`
-- -----------------------------
DROP TABLE IF EXISTS `page_role_aep`;
CREATE TABLE `page_role_aep`
(
    `page_id`      int(11) NOT NULL DEFAULT '0',
    `role_id`      int(11) NOT NULL DEFAULT '0',
    `write_access` int(1) DEFAULT '0',
    PRIMARY KEY (`page_id`, `role_id`),
    KEY            `role_id` (`role_id`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

-- -----------------------------
-- Structure de la table `pages`
-- -----------------------------
DROP TABLE IF EXISTS `pages`;
CREATE TABLE `pages`
(
    `id`          int(11) NOT NULL AUTO_INCREMENT,
    `chaine`      varchar(255) NOT NULL,
    `libelle`     varchar(255) NOT NULL,
    `description` text,
    PRIMARY KEY (`id`),
    UNIQUE KEY `unique_chaine` (`chaine`)
) ENGINE=MyISAM AUTO_INCREMENT=90 DEFAULT CHARSET=latin1;


-- -----------------------------
-- Structure de la table `position_compteur_aep`
-- -----------------------------
DROP TABLE IF EXISTS `position_compteur_aep`;
CREATE TABLE `position_compteur_aep`
(
    `id`       int(2) unsigned NOT NULL AUTO_INCREMENT,
    `position` varchar(32) NOT NULL,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- Données de `position_compteur_aep`

-- -----------------------------
-- Structure de la table `redevance`
-- -----------------------------
DROP TABLE IF EXISTS `redevance`;
CREATE TABLE `redevance`
(
    `id`          int(4) unsigned NOT NULL AUTO_INCREMENT,
    `libele`      varchar(64)   NOT NULL,
    `pourcentage` decimal(5, 2) NOT NULL,
    `description` text,
    `id_aep`      int(4) unsigned DEFAULT NULL,
    `type`        varchar(8)    NOT NULL DEFAULT 'sortie',
    `mois_debut`  varchar(8)             DEFAULT '2025-03',
    PRIMARY KEY (`id`),
    KEY           `fk_aep_redevance` (`id_aep`),
    CONSTRAINT `fk_aep_redevance` FOREIGN KEY (`id_aep`) REFERENCES `aep` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=latin1;


-- -----------------------------
-- Structure de la table `reseau`
-- -----------------------------
DROP TABLE IF EXISTS `reseau`;
CREATE TABLE `reseau`
(
    `id`                 int(3) unsigned NOT NULL AUTO_INCREMENT,
    `nom`                varchar(32) NOT NULL,
    `abreviation`        varchar(16) DEFAULT NULL,
    `date_creation`      date        NOT NULL,
    `description_reseau` text,
    `id_aep`             int(4) unsigned NOT NULL DEFAULT '1',
    PRIMARY KEY (`id`),
    KEY                  `fk_aep_reseau` (`id_aep`),
    CONSTRAINT `fk_aep_reseau` FOREIGN KEY (`id_aep`) REFERENCES `aep` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=latin1;

-- -----------------------------
-- Structure de la table `ressources_humaines`
-- -----------------------------
DROP TABLE IF EXISTS `ressources_humaines`;
CREATE TABLE `ressources_humaines`
(
    `id`           int(11) NOT NULL AUTO_INCREMENT,
    `aep_id`       int(11) NOT NULL,
    `nom`          varchar(150) NOT NULL,
    `fonction`     varchar(120)   DEFAULT NULL,
    `competences`  text,
    `telephone`    varchar(50)    DEFAULT NULL,
    `statut`       enum('disponible','occupe','indisponible') DEFAULT 'disponible',
    `cout_horaire` decimal(12, 2) DEFAULT '0.00',
    `actif`        tinyint(1) DEFAULT '1',
    `created_at`   datetime       DEFAULT NULL,
    `updated_at`   datetime       DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY            `idx_rh_aep` (`aep_id`),
    KEY            `idx_rh_statut` (`statut`),
    KEY            `idx_rh_actif` (`actif`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8;


-- -----------------------------
-- Structure de la table `ressources_materielles`
-- -----------------------------
DROP TABLE IF EXISTS `ressources_materielles`;
CREATE TABLE `ressources_materielles`
(
    `id`                  int(11) NOT NULL AUTO_INCREMENT,
    `aep_id`              int(11) NOT NULL,
    `libelle`             varchar(180) NOT NULL,
    `categorie`           varchar(120)   DEFAULT NULL,
    `reference`           varchar(120)   DEFAULT NULL,
    `quantite_totale`     decimal(12, 2) DEFAULT '0.00',
    `quantite_disponible` decimal(12, 2) DEFAULT '0.00',
    `unite`               varchar(30)    DEFAULT 'u',
    `cout_unitaire`       decimal(12, 2) DEFAULT '0.00',
    `statut`              enum('disponible','occupe','panne','hors_service') DEFAULT 'disponible',
    `actif`               tinyint(1) DEFAULT '1',
    `created_at`          datetime       DEFAULT NULL,
    `updated_at`          datetime       DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY                   `idx_rm_aep` (`aep_id`),
    KEY                   `idx_rm_statut` (`statut`),
    KEY                   `idx_rm_categorie` (`categorie`),
    KEY                   `idx_rm_actif` (`actif`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- Données de `ressources_materielles`

-- -----------------------------
-- Structure de la table `roles`
-- -----------------------------
DROP TABLE IF EXISTS `roles`;
CREATE TABLE `roles`
(
    `id`  int(11) NOT NULL AUTO_INCREMENT,
    `nom` varchar(50) NOT NULL,
    PRIMARY KEY (`id`)
) ENGINE=MyISAM AUTO_INCREMENT=13 DEFAULT CHARSET=latin1;


-- -----------------------------
-- Structure de la table `travail`
-- -----------------------------
DROP TABLE IF EXISTS `travail`;
CREATE TABLE `travail`
(
    `id`             int(5) unsigned NOT NULL AUTO_INCREMENT,
    `id_aep`         int(4) unsigned NOT NULL,
    `id_utilisateur` int(4) unsigned NOT NULL,
    PRIMARY KEY (`id`),
    KEY              `fk_aep_travail` (`id_aep`),
    KEY              `fk_utilisateur_travail` (`id_utilisateur`),
    CONSTRAINT `fk_aep_travail` FOREIGN KEY (`id_aep`) REFERENCES `aep` (`id`),
    CONSTRAINT `fk_utilisateur_travail` FOREIGN KEY (`id_utilisateur`) REFERENCES `utilisateur` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- Données de `travail`

-- -----------------------------
-- Structure de la table `user_clefs`
-- -----------------------------
DROP TABLE IF EXISTS `user_clefs`;
CREATE TABLE `user_clefs`
(
    `clef_id` int(3) unsigned NOT NULL,
    `user_id` int(3) unsigned NOT NULL,
    PRIMARY KEY (`clef_id`),
    KEY       `user_id` (`user_id`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;


-- -----------------------------
-- Structure de la table `user_roles`
-- -----------------------------
DROP TABLE IF EXISTS `user_roles`;
CREATE TABLE `user_roles`
(
    `user_id` int(11) NOT NULL DEFAULT '0',
    `role_id` int(11) NOT NULL DEFAULT '0',
    PRIMARY KEY (`user_id`, `role_id`),
    KEY       `role_id` (`role_id`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;



-- -----------------------------
-- Structure de la table `users`
-- -----------------------------
DROP TABLE IF EXISTS `users`;
CREATE TABLE `users`
(
    `id`               int(5) unsigned NOT NULL AUTO_INCREMENT,
    `email`            varchar(32) NOT NULL,
    `nom`              varchar(32) NOT NULL,
    `prenom`           varchar(32) NOT NULL,
    `numero_telephone` varchar(16) NOT NULL,
    `password`         varchar(64) DEFAULT NULL,
    `salt`             varchar(64) DEFAULT NULL,
    PRIMARY KEY (`id`)
) ENGINE=MyISAM AUTO_INCREMENT=17 DEFAULT CHARSET=latin1;


-- -----------------------------
-- Structure de la table `utilisateur`
-- -----------------------------
DROP TABLE IF EXISTS `utilisateur`;
CREATE TABLE `utilisateur`
(
    `id`               int(5) unsigned NOT NULL AUTO_INCREMENT,
    `email`            varchar(32) NOT NULL,
    `nom`              varchar(32) NOT NULL,
    `prenom`           varchar(32) NOT NULL,
    `numero_telephone` varchar(16) NOT NULL,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- Données de `utilisateur`

-- -----------------------------
-- Structure de la table `versements`
-- -----------------------------
DROP TABLE IF EXISTS `versements`;
CREATE TABLE `versements`
(
    `id`                  int(4) unsigned NOT NULL AUTO_INCREMENT,
    `montant`             decimal(15, 2) NOT NULL,
    `date_versement`      date           NOT NULL,
    `id_mois_facturation` int(4) unsigned NOT NULL,
    `id_redevance`        int(4) unsigned NOT NULL,
    `est_valide`          tinyint(1) NOT NULL DEFAULT '0',
    PRIMARY KEY (`id`),
    KEY                   `id_mois_facturation` (`id_mois_facturation`),
    KEY                   `id_redevance` (`id_redevance`)
) ENGINE=MyISAM AUTO_INCREMENT=38 DEFAULT CHARSET=latin1;



--
-- Structure de la vue `vue_abones_facturation`
--
DROP TABLE IF EXISTS `vue_abones_facturation`;

CREATE
ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `vue_abones_facturation` AS
select `i`.`id`                                                                                                     AS `id`,
       `i`.`id_compteur`                                                                                            AS `id_compteur`,
       `cr`.`id_aep`                                                                                                AS `id_aep`,
       `mf`.`id`                                                                                                    AS `id_mois`,
       `a`.`id`                                                                                                     AS `id_abone`,
       `a`.`nom`                                                                                                    AS `nom_abone`,
       `a`.`id_reseau`                                                                                              AS `id_reseau`,
       `a`.`numero_telephone`                                                                                       AS `numero_telephone`,
       `mf`.`mois`                                                                                                  AS `mois`,
       `cr`.`id`                                                                                                    AS `id_constante_reseau`,
       `i`.`id_mois_facturation`                                                                                    AS `id_mois_facturation`,
       `mf`.`date_facturation`                                                                                      AS `date_facturation`,
       `mf`.`date_depot`                                                                                            AS `date_depot`,
       `i`.`ancien_index`                                                                                           AS `ancien_index`,
       `i`.`nouvel_index`                                                                                           AS `nouvel_index`,
       `f`.`id`                                                                                                     AS `id_facture`,
       `mf`.`date_releve`                                                                                           AS `date_releve`,
       `f`.`penalite`                                                                                               AS `penalite`,
       `a`.`numero_compte_anticipation`                                                                             AS `numero_compte_anticipation`,
       `cr`.`prix_entretient_compteur`                                                                              AS `prix_entretient_compteur`,
       `cr`.`prix_metre_cube_eau`                                                                                   AS `prix_metre_cube_eau`,
       `cr`.`prix_tva`                                                                                              AS `prix_tva`,
       (`i`.`nouvel_index` - `i`.`ancien_index`)                                                                    AS `consommation`,
       ((`i`.`nouvel_index` - `i`.`ancien_index`) * `cr`.`prix_metre_cube_eau`)                                     AS `montant_conso`,
       (((`i`.`nouvel_index` - `i`.`ancien_index`) * `cr`.`prix_metre_cube_eau`) +
        `cr`.`prix_entretient_compteur`)                                                                            AS `montant_conso_entretien`,
       ((((`i`.`nouvel_index` - `i`.`ancien_index`) * `cr`.`prix_metre_cube_eau`) + `cr`.`prix_entretient_compteur`) *
        (1 + (`cr`.`prix_tva` / 100)))                                                                              AS `montant_conso_tva`,
       (((((`i`.`nouvel_index` - `i`.`ancien_index`) * `cr`.`prix_metre_cube_eau`) + `cr`.`prix_entretient_compteur`) *
         (1 + (`cr`.`prix_tva` / 100))) +
        `f`.`penalite`)                                                                                             AS `montant_total`,
       ((((((`i`.`nouvel_index` - `i`.`ancien_index`) * `cr`.`prix_metre_cube_eau`) + `cr`.`prix_entretient_compteur`) *
          (1 + (`cr`.`prix_tva` / 100))) + `f`.`penalite`) -
        `f`.`montant_verse`)                                                                                        AS `montant_restant`,
       `f`.`montant_verse`                                                                                          AS `montant_verse`,
       least((((((`i`.`nouvel_index` - `i`.`ancien_index`) * `cr`.`prix_metre_cube_eau`) +
                `cr`.`prix_entretient_compteur`) * (1 + (`cr`.`prix_tva` / 100))) + `f`.`penalite`),
             `f`.`montant_verse`)                                                                                   AS `montant_a_valider`,
       ((((((`i`.`nouvel_index` - `i`.`ancien_index`) * `cr`.`prix_metre_cube_eau`) + `cr`.`prix_entretient_compteur`) *
          (1 + (`cr`.`prix_tva` / 100))) + `f`.`penalite`) -
        `f`.`montant_verse`)                                                                                        AS `impaye`
from ((((`abone` `a` join `facture` `f` on ((`a`.`id` = `f`.`id_abone`))) join `indexes` `i`
        on ((`f`.`id_indexes` = `i`.`id`))) join `mois_facturation` `mf`
       on ((`i`.`id_mois_facturation` = `mf`.`id`))) join `constante_reseau` `cr`
      on ((`mf`.`id_constante` = `cr`.`id`)));


SET
FOREIGN_KEY_CHECKS=1;


