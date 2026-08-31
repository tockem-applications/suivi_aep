<?php
require_once __DIR__ . '/_web_guard.php';
bd_web_guard();
/**
 * Script pour générer un fichier de migration SQL
 * Permet de faire passer la base de données de l'état (9) à l'état (10)
 */

// Configuration
$fileV9 = __DIR__ . '/suivi_aep_fokoue (9).sql';
$fileV10 = __DIR__ . '/suivi_aep_fokoue (10).sql';
$outputFile = __DIR__ . '/migration_9_to_10.sql';

// Lire le fichier v10 pour extraire les structures nécessaires
$contentV10 = file_get_contents($fileV10);

// Début du fichier de migration
$migration = <<<'SQL'
-- =============================================================================
-- Migration SQL : Passage de la version 9 à la version 10
-- Généré le : DATE_GENERATION
-- =============================================================================
-- Ce script permet de migrer la base de données de l'état décrit dans
-- suivi_aep_fokoue (9).sql vers l'état décrit dans suivi_aep_fokoue (10).sql
-- =============================================================================

SET SQL_MODE="NO_AUTO_VALUE_ON_ZERO";
SET FOREIGN_KEY_CHECKS=0;

-- =============================================================================
-- 1. MODIFICATIONS DES TABLES EXISTANTES
-- =============================================================================

-- Ajout de colonnes à la table `abone`
ALTER TABLE `abone`
  ADD COLUMN `type_abone` varchar(2) DEFAULT 'BP' COMMENT 'BP=Branchement Privé, BF=Borne Fontaine' AFTER `id_reseau`,
  ADD COLUMN `tarif_differencie_autorise` tinyint(1) NOT NULL DEFAULT '1' COMMENT 'Indique si le tarif différencié peut être appliqué (1=oui, 0=non)' AFTER `type_abone`,
  ADD COLUMN `type_abonnement` varchar(2) NOT NULL DEFAULT 'BP' COMMENT 'Type d''abonnement: BP=Branchement Privé, BF=Borne Fontaine' AFTER `tarif_differencie_autorise`;

-- Ajout de colonne à la table `indexes`
ALTER TABLE `indexes`
  ADD COLUMN `id_tarif_differencie` int(2) unsigned DEFAULT NULL COMMENT 'ID du tarif différencié utilisé au moment de la création (NULL = tarif de base)' AFTER `message`,
  ADD KEY `fk_tarif_differencie_indexes` (`id_tarif_differencie`);

-- Ajout de colonnes à la table `redevance`
ALTER TABLE `redevance`
  ADD COLUMN `base_calcul` varchar(20) NOT NULL DEFAULT 'vente_eau' COMMENT 'vente_eau ou branchements' AFTER `mois_debut`,
  ADD COLUMN `type_calcul` varchar(20) NOT NULL DEFAULT 'pourcentage' COMMENT 'pourcentage ou montant_fixe' AFTER `base_calcul`,
  ADD COLUMN `montant_par_m3` decimal(15,2) DEFAULT NULL COMMENT 'Montant fixe par m³ si type_calcul = montant_fixe' AFTER `type_calcul`,
  ADD COLUMN `est_sortie` tinyint(1) NOT NULL DEFAULT '1' COMMENT 'Toujours 1 car toutes les redevances sont des sorties' AFTER `montant_par_m3`;

-- Modification de la table `versements` pour permettre id_mois_facturation NULL
ALTER TABLE `versements`
  MODIFY COLUMN `id_mois_facturation` int(4) unsigned DEFAULT NULL;

-- =============================================================================
-- 2. CRÉATION DES NOUVELLES TABLES
-- =============================================================================

-- Table `bf_gerant` : Gestionnaires des Bornes Fontaines
CREATE TABLE IF NOT EXISTS `bf_gerant` (
  `id` int(4) unsigned NOT NULL AUTO_INCREMENT,
  `id_borne_fontaine` int(4) unsigned NOT NULL,
  `nom_gerant` varchar(128) NOT NULL,
  `numero_telephone` varchar(16) DEFAULT NULL,
  `numero_piece_identite` varchar(32) DEFAULT NULL COMMENT 'Numéro de pièce d''identité',
  `type_piece_identite` varchar(16) DEFAULT NULL COMMENT 'CNI, Passeport, etc.',
  `date_debut` date NOT NULL COMMENT 'Date de début de gestion',
  `date_fin` date DEFAULT NULL COMMENT 'Date de fin de gestion (NULL si toujours actif)',
  `est_actif` tinyint(1) NOT NULL DEFAULT '1' COMMENT 'Indique si c''est le gérant actuel',
  `date_creation` datetime NOT NULL,
  `date_modification` datetime DEFAULT NULL,
  `notes` text COMMENT 'Notes additionnelles sur le gérant',
  PRIMARY KEY (`id`),
  KEY `idx_id_borne_fontaine` (`id_borne_fontaine`),
  KEY `idx_nom_gerant` (`nom_gerant`),
  KEY `idx_est_actif` (`est_actif`),
  KEY `idx_dates` (`date_debut`,`date_fin`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='Table des gérants des Bornes Fontaines avec historique';

-- Table `borne_fontaine` : Bornes Fontaines
CREATE TABLE IF NOT EXISTS `borne_fontaine` (
  `id` int(4) unsigned NOT NULL AUTO_INCREMENT,
  `id_abone` int(4) unsigned NOT NULL,
  `numero_borne` varchar(32) DEFAULT NULL COMMENT 'Numéro d''identification de la borne',
  `localisation` varchar(128) DEFAULT NULL COMMENT 'Localisation de la borne',
  `date_creation` datetime NOT NULL,
  `date_modification` datetime DEFAULT NULL,
  `description` text,
  PRIMARY KEY (`id`),
  UNIQUE KEY `id_abone` (`id_abone`),
  KEY `idx_numero_borne` (`numero_borne`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='Table des Bornes Fontaines';

-- Table `tarif_differencie` : Tarifs différenciés selon la consommation
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
  KEY `fk_constante_tarif_differencie` (`id_constante_reseau`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- =============================================================================
-- 3. CRÉATION DES VUES
-- =============================================================================

-- Vue `vue_indexes_tarifs` : Vue des tarifs par index
DROP TABLE IF EXISTS `vue_indexes_tarifs`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `vue_indexes_tarifs` AS 
SELECT 
    `i`.`id` AS `id_indexes`,
    `i`.`id_compteur` AS `id_compteur`,
    `i`.`id_mois_facturation` AS `id_mois_facturation`,
    `i`.`ancien_index` AS `ancien_index`,
    `i`.`nouvel_index` AS `nouvel_index`,
    `i`.`message` AS `message`,
    `cr`.`id` AS `id_constante_reseau`,
    `cr`.`prix_metre_cube_eau` AS `prix_metre_cube_eau`,
    `cr`.`prix_entretient_compteur` AS `prix_entretient_compteur`,
    `cr`.`prix_tva` AS `prix_tva`,
    `cr`.`date_creation` AS `date_creation`,
    `cr`.`est_actif` AS `est_actif`,
    `cr`.`description` AS `description`,
    `cr`.`id_aep` AS `id_aep`,
    NULL AS `id_tarif_differencie`,
    NULL AS `min_consommation`,
    NULL AS `max_consommation`,
    NULL AS `date_creation_tarif_differencie`,
    NULL AS `description_tarif_differencie`,
    'base' AS `type_tarif`,
    (`i`.`nouvel_index` - `i`.`ancien_index`) AS `consommation`
FROM 
    ((`indexes` `i` 
    JOIN `mois_facturation` `mf` ON (`mf`.`id` = `i`.`id_mois_facturation`)) 
    JOIN `constante_reseau` `cr` ON (`cr`.`id` = `mf`.`id_constante`))
WHERE 
    NOT EXISTS (
        SELECT 1 FROM `tarif_differencie` `td` 
        WHERE (`td`.`id_constante_reseau` = `cr`.`id`) 
        AND ((`i`.`nouvel_index` - `i`.`ancien_index`) >= `td`.`min_consommation`) 
        AND (ISNULL(`td`.`max_consommation`) OR ((`i`.`nouvel_index` - `i`.`ancien_index`) < `td`.`max_consommation`))
    )

UNION ALL

SELECT 
    `i`.`id` AS `id_indexes`,
    `i`.`id_compteur` AS `id_compteur`,
    `i`.`id_mois_facturation` AS `id_mois_facturation`,
    `i`.`ancien_index` AS `ancien_index`,
    `i`.`nouvel_index` AS `nouvel_index`,
    `i`.`message` AS `message`,
    `cr`.`id` AS `id_constante_reseau`,
    `cr`.`date_creation` AS `date_creation`,
    `cr`.`est_actif` AS `est_actif`,
    `cr`.`id_aep` AS `id_aep`,
    `td`.`id` AS `id_tarif_differencie`,
    `td`.`prix_metre_cube_eau` AS `prix_metre_cube_eau`,
    `td`.`prix_entretient_compteur` AS `prix_entretient_compteur`,
    `td`.`prix_tva` AS `prix_tva`,
    `td`.`description` AS `description`,
    `td`.`min_consommation` AS `min_consommation`,
    `td`.`max_consommation` AS `max_consommation`,
    `td`.`date_creation` AS `date_creation_tarif_differencie`,
    `td`.`description` AS `description_tarif_differencie`,
    'differencie' AS `type_tarif`,
    (`i`.`nouvel_index` - `i`.`ancien_index`) AS `consommation`
FROM 
    (((`indexes` `i` 
    JOIN `mois_facturation` `mf` ON (`mf`.`id` = `i`.`id_mois_facturation`)) 
    JOIN `constante_reseau` `cr` ON (`cr`.`id` = `mf`.`id_constante`)) 
    JOIN `tarif_differencie` `td` ON (`td`.`id_constante_reseau` = `cr`.`id`))
WHERE 
    (((`i`.`nouvel_index` - `i`.`ancien_index`) >= `td`.`min_consommation`) 
    AND (ISNULL(`td`.`max_consommation`) OR ((`i`.`nouvel_index` - `i`.`ancien_index`) < `td`.`max_consommation`)))
ORDER BY `id_indexes`, `type_tarif`, `min_consommation`;

-- Vue `vue_indexes_tarifs_resolved` : Vue résolue des tarifs par index
DROP TABLE IF EXISTS `vue_indexes_tarifs_resolved`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `vue_indexes_tarifs_resolved` AS 
SELECT 
    `i`.`id` AS `id_indexes`,
    `i`.`id_compteur` AS `id_compteur`,
    `i`.`id_mois_facturation` AS `id_mois_facturation`,
    `i`.`ancien_index` AS `ancien_index`,
    `i`.`nouvel_index` AS `nouvel_index`,
    `i`.`message` AS `message`,
    (`i`.`nouvel_index` - `i`.`ancien_index`) AS `consommation`,
    `cr`.`id` AS `id_constante_reseau`,
    `cr`.`id_aep` AS `id_aep`,
    `cr`.`date_creation` AS `date_creation`,
    `cr`.`est_actif` AS `est_actif`,
    `cr`.`description` AS `description`,
    COALESCE(`td_stocke`.`prix_metre_cube_eau`, `td_calc`.`prix_metre_cube_eau`, `cr`.`prix_metre_cube_eau`) AS `prix_metre_cube_eau`,
    COALESCE(`td_stocke`.`prix_entretient_compteur`, `td_calc`.`prix_entretient_compteur`, `cr`.`prix_entretient_compteur`) AS `prix_entretient_compteur`,
    COALESCE(`td_stocke`.`prix_tva`, `td_calc`.`prix_tva`, `cr`.`prix_tva`) AS `prix_tva`,
    (CASE 
        WHEN `td_stocke`.`id` IS NOT NULL THEN 'differencie' 
        WHEN `td_calc`.`id` IS NOT NULL THEN 'differencie' 
        ELSE 'base' 
    END) AS `type_tarif`,
    COALESCE(`td_stocke`.`id`, `td_calc`.`id`) AS `id_tarif_differencie`,
    COALESCE(`td_stocke`.`min_consommation`, `td_calc`.`min_consommation`) AS `min_consommation`,
    COALESCE(`td_stocke`.`max_consommation`, `td_calc`.`max_consommation`) AS `max_consommation`
FROM 
    ((((`indexes` `i` 
    JOIN `mois_facturation` `mf` ON (`mf`.`id` = `i`.`id_mois_facturation`)) 
    JOIN `constante_reseau` `cr` ON (`cr`.`id` = `mf`.`id_constante`)) 
    LEFT JOIN `tarif_differencie` `td_stocke` ON (`td_stocke`.`id` = `i`.`id_tarif_differencie`)) 
    LEFT JOIN `tarif_differencie` `td_calc` ON (
        (`td_calc`.`id_constante_reseau` = `cr`.`id`) 
        AND ISNULL(`i`.`id_tarif_differencie`) 
        AND ((`i`.`nouvel_index` - `i`.`ancien_index`) >= `td_calc`.`min_consommation`) 
        AND (ISNULL(`td_calc`.`max_consommation`) OR ((`i`.`nouvel_index` - `i`.`ancien_index`) < `td_calc`.`max_consommation`)) 
        AND (NOT EXISTS (
            SELECT 1 FROM `tarif_differencie` `td2` 
            WHERE (`td2`.`id_constante_reseau` = `cr`.`id`) 
            AND ((`i`.`nouvel_index` - `i`.`ancien_index`) >= `td2`.`min_consommation`) 
            AND (ISNULL(`td2`.`max_consommation`) OR ((`i`.`nouvel_index` - `i`.`ancien_index`) < `td2`.`max_consommation`)) 
            AND (`td2`.`min_consommation` > `td_calc`.`min_consommation`)
        ))
    ));

-- =============================================================================
-- 4. CONTRAINTES DE CLÉS ÉTRANGÈRES
-- =============================================================================

-- Contrainte pour `bf_gerant`
ALTER TABLE `bf_gerant`
  ADD CONSTRAINT `fk_bf_gerant_borne_fontaine` FOREIGN KEY (`id_borne_fontaine`) REFERENCES `borne_fontaine` (`id`) ON DELETE CASCADE;

-- Contrainte pour `borne_fontaine`
ALTER TABLE `borne_fontaine`
  ADD CONSTRAINT `fk_borne_fontaine_abone` FOREIGN KEY (`id_abone`) REFERENCES `abone` (`id`) ON DELETE CASCADE;

-- Contrainte pour `indexes` (id_tarif_differencie)
ALTER TABLE `indexes`
  ADD CONSTRAINT `fk_tarif_differencie_indexes` FOREIGN KEY (`id_tarif_differencie`) REFERENCES `tarif_differencie` (`id`) ON DELETE SET NULL;

-- Contrainte pour `tarif_differencie`
ALTER TABLE `tarif_differencie`
  ADD CONSTRAINT `fk_constante_tarif_differencie` FOREIGN KEY (`id_constante_reseau`) REFERENCES `constante_reseau` (`id`);

-- =============================================================================
-- 5. RECONSTITUTION DE LA VUE `vue_abones_facturation` (si nécessaire)
-- =============================================================================
-- Note: Cette vue doit être recréée après la création de vue_indexes_tarifs_resolved
-- car elle dépend de cette dernière

DROP TABLE IF EXISTS `vue_abones_facturation`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `vue_abones_facturation` AS 
SELECT 
    `vit`.`id_indexes` AS `id`,
    `vit`.`id_compteur` AS `id_compteur`,
    `vit`.`id_aep` AS `id_aep`,
    `mf`.`id` AS `id_mois`,
    `a`.`id` AS `id_abone`,
    `a`.`nom` AS `nom_abone`,
    `a`.`id_reseau` AS `id_reseau`,
    `a`.`numero_telephone` AS `numero_telephone`,
    `mf`.`mois` AS `mois`,
    `vit`.`id_constante_reseau` AS `id_constante_reseau`,
    `vit`.`id_mois_facturation` AS `id_mois_facturation`,
    `mf`.`date_facturation` AS `date_facturation`,
    `mf`.`date_depot` AS `date_depot`,
    `vit`.`ancien_index` AS `ancien_index`,
    `vit`.`nouvel_index` AS `nouvel_index`,
    `f`.`id` AS `id_facture`,
    `mf`.`date_releve` AS `date_releve`,
    `f`.`penalite` AS `penalite`,
    `a`.`numero_compte_anticipation` AS `numero_compte_anticipation`,
    (CASE WHEN (`a`.`tarif_differencie_autorise` = 0) THEN `cr`.`prix_entretient_compteur` ELSE `vit`.`prix_entretient_compteur` END) AS `prix_entretient_compteur`,
    (CASE WHEN (`a`.`tarif_differencie_autorise` = 0) THEN `cr`.`prix_metre_cube_eau` ELSE `vit`.`prix_metre_cube_eau` END) AS `prix_metre_cube_eau`,
    (CASE WHEN (`a`.`tarif_differencie_autorise` = 0) THEN `cr`.`prix_tva` ELSE `vit`.`prix_tva` END) AS `prix_tva`,
    `vit`.`consommation` AS `consommation`,
    (`vit`.`consommation` * (CASE WHEN (`a`.`tarif_differencie_autorise` = 0) THEN `cr`.`prix_metre_cube_eau` ELSE `vit`.`prix_metre_cube_eau` END)) AS `montant_conso`,
    ((`vit`.`consommation` * (CASE WHEN (`a`.`tarif_differencie_autorise` = 0) THEN `cr`.`prix_metre_cube_eau` ELSE `vit`.`prix_metre_cube_eau` END)) + (CASE WHEN (`a`.`tarif_differencie_autorise` = 0) THEN `cr`.`prix_entretient_compteur` ELSE `vit`.`prix_entretient_compteur` END)) AS `montant_conso_entretien`,
    (((`vit`.`consommation` * (CASE WHEN (`a`.`tarif_differencie_autorise` = 0) THEN `cr`.`prix_metre_cube_eau` ELSE `vit`.`prix_metre_cube_eau` END)) + (CASE WHEN (`a`.`tarif_differencie_autorise` = 0) THEN `cr`.`prix_entretient_compteur` ELSE `vit`.`prix_entretient_compteur` END)) * (1 + ((CASE WHEN (`a`.`tarif_differencie_autorise` = 0) THEN `cr`.`prix_tva` ELSE `vit`.`prix_tva` END) / 100))) AS `montant_conso_tva`,
    ((((`vit`.`consommation` * (CASE WHEN (`a`.`tarif_differencie_autorise` = 0) THEN `cr`.`prix_metre_cube_eau` ELSE `vit`.`prix_metre_cube_eau` END)) + (CASE WHEN (`a`.`tarif_differencie_autorise` = 0) THEN `cr`.`prix_entretient_compteur` ELSE `vit`.`prix_entretient_compteur` END)) * (1 + ((CASE WHEN (`a`.`tarif_differencie_autorise` = 0) THEN `cr`.`prix_tva` ELSE `vit`.`prix_tva` END) / 100))) + `f`.`penalite`) AS `montant_total`,
    (((((`vit`.`consommation` * (CASE WHEN (`a`.`tarif_differencie_autorise` = 0) THEN `cr`.`prix_metre_cube_eau` ELSE `vit`.`prix_metre_cube_eau` END)) + (CASE WHEN (`a`.`tarif_differencie_autorise` = 0) THEN `cr`.`prix_entretient_compteur` ELSE `vit`.`prix_entretient_compteur` END)) * (1 + ((CASE WHEN (`a`.`tarif_differencie_autorise` = 0) THEN `cr`.`prix_tva` ELSE `vit`.`prix_tva` END) / 100))) + `f`.`penalite`) - `f`.`montant_verse`) AS `montant_restant`,
    `f`.`montant_verse` AS `montant_verse`,
    LEAST(((((`vit`.`consommation` * (CASE WHEN (`a`.`tarif_differencie_autorise` = 0) THEN `cr`.`prix_metre_cube_eau` ELSE `vit`.`prix_metre_cube_eau` END)) + (CASE WHEN (`a`.`tarif_differencie_autorise` = 0) THEN `cr`.`prix_entretient_compteur` ELSE `vit`.`prix_entretient_compteur` END)) * (1 + ((CASE WHEN (`a`.`tarif_differencie_autorise` = 0) THEN `cr`.`prix_tva` ELSE `vit`.`prix_tva` END) / 100))) + `f`.`penalite`), `f`.`montant_verse`) AS `montant_a_valider`,
    (((((`vit`.`consommation` * (CASE WHEN (`a`.`tarif_differencie_autorise` = 0) THEN `cr`.`prix_metre_cube_eau` ELSE `vit`.`prix_metre_cube_eau` END)) + (CASE WHEN (`a`.`tarif_differencie_autorise` = 0) THEN `cr`.`prix_entretient_compteur` ELSE `vit`.`prix_entretient_compteur` END)) * (1 + ((CASE WHEN (`a`.`tarif_differencie_autorise` = 0) THEN `cr`.`prix_tva` ELSE `vit`.`prix_tva` END) / 100))) + `f`.`penalite`) - `f`.`montant_verse`) AS `impaye`
FROM 
    ((((`abone` `a` 
    JOIN `facture` `f` ON (`a`.`id` = `f`.`id_abone`)) 
    JOIN `vue_indexes_tarifs_resolved` `vit` ON (`f`.`id_indexes` = `vit`.`id_indexes`)) 
    JOIN `mois_facturation` `mf` ON (`vit`.`id_mois_facturation` = `mf`.`id`)) 
    JOIN `constante_reseau` `cr` ON (`mf`.`id_constante` = `cr`.`id`));

SET FOREIGN_KEY_CHECKS=1;

-- =============================================================================
-- FIN DE LA MIGRATION
-- =============================================================================
-- Note: Ce script de migration ne modifie PAS les données existantes.
-- Les nouvelles colonnes auront leurs valeurs par défaut.
-- Pour remplir les colonnes avec des valeurs calculées (ex: id_tarif_differencie),
-- utilisez les scripts de mise à jour appropriés (ex: update_database_fixer_tarifs.php).
-- =============================================================================

SQL;

// Remplacer la date de génération
$migration = str_replace('DATE_GENERATION', date('Y-m-d H:i:s'), $migration);

// Écrire le fichier de migration
file_put_contents($outputFile, $migration);

echo "Fichier de migration généré avec succès : $outputFile\n";
echo "Taille du fichier : " . number_format(filesize($outputFile)) . " octets\n";
