-- Script SQL pour créer les tables nécessaires aux Bornes Fontaines
-- Ce script est idempotent et peut être exécuté plusieurs fois sans erreur

SET FOREIGN_KEY_CHECKS = 0;

-- 1. Ajouter le champ type_abone à la table abone si elle n'existe pas
-- Type: 'BP' (Branchement Privé) ou 'BF' (Borne Fontaine)
SET @column_exists = (
    SELECT COUNT(*) 
    FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'abone' 
    AND COLUMN_NAME = 'type_abone'
);

SET @sql = IF(@column_exists = 0,
    'ALTER TABLE abone ADD COLUMN type_abone VARCHAR(2) DEFAULT ''BP'' COMMENT ''BP=Branchement Privé, BF=Borne Fontaine'' AFTER id_reseau',
    'SELECT ''Column type_abone already exists'' AS message'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 2. Créer la table borne_fontaine
DROP TABLE IF EXISTS `borne_fontaine`;
CREATE TABLE `borne_fontaine` (
    `id` INT(4) UNSIGNED NOT NULL AUTO_INCREMENT,
    `id_abone` INT(4) UNSIGNED NOT NULL,
    `numero_borne` VARCHAR(32) DEFAULT NULL COMMENT 'Numéro d''identification de la borne',
    `localisation` VARCHAR(128) DEFAULT NULL COMMENT 'Localisation de la borne',
    `date_creation` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `date_modification` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    `description` TEXT DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `id_abone` (`id_abone`),
    KEY `idx_numero_borne` (`numero_borne`),
    CONSTRAINT `fk_borne_fontaine_abone` FOREIGN KEY (`id_abone`) REFERENCES `abone` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Table des Bornes Fontaines';

-- 3. Créer la table bf_gerant pour gérer l'historique des gérants
DROP TABLE IF EXISTS `bf_gerant`;
CREATE TABLE `bf_gerant` (
    `id` INT(4) UNSIGNED NOT NULL AUTO_INCREMENT,
    `id_borne_fontaine` INT(4) UNSIGNED NOT NULL,
    `nom_gerant` VARCHAR(128) NOT NULL,
    `numero_telephone` VARCHAR(16) DEFAULT NULL,
    `numero_piece_identite` VARCHAR(32) DEFAULT NULL COMMENT 'Numéro de pièce d''identité',
    `type_piece_identite` VARCHAR(16) DEFAULT NULL COMMENT 'CNI, Passeport, etc.',
    `date_debut` DATE NOT NULL COMMENT 'Date de début de gestion',
    `date_fin` DATE DEFAULT NULL COMMENT 'Date de fin de gestion (NULL si toujours actif)',
    `est_actif` BOOLEAN NOT NULL DEFAULT TRUE COMMENT 'Indique si c''est le gérant actuel',
    `date_creation` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `date_modification` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    `notes` TEXT DEFAULT NULL COMMENT 'Notes additionnelles sur le gérant',
    PRIMARY KEY (`id`),
    KEY `idx_id_borne_fontaine` (`id_borne_fontaine`),
    KEY `idx_nom_gerant` (`nom_gerant`),
    KEY `idx_est_actif` (`est_actif`),
    KEY `idx_dates` (`date_debut`, `date_fin`),
    CONSTRAINT `fk_bf_gerant_borne_fontaine` FOREIGN KEY (`id_borne_fontaine`) REFERENCES `borne_fontaine` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Table des gérants des Bornes Fontaines avec historique';

-- 4. Créer un index pour améliorer les performances
CREATE INDEX IF NOT EXISTS `idx_abone_type` ON `abone` (`type_abone`);

-- 5. Mettre à jour les abonnés existants pour qu'ils soient par défaut des BP
UPDATE `abone` SET `type_abone` = 'BP' WHERE `type_abone` IS NULL OR `type_abone` = '';

SET FOREIGN_KEY_CHECKS = 1;
