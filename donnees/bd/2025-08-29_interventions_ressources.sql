-- Schéma Interventions & Ressources pour AEP (MySQL)
-- Date: 2025-08-29
-- Conçu pour s'intégrer à la base existante (utilise aep_id pour scoper les données)

SET NAMES utf8mb4;

SET time_zone = '+00:00';

-- Sécurité: exécuter dans la bonne base (décommentez et adaptez si besoin)
-- USE `suivi_aep_fokoue`;

-- 1) Ressources humaines
CREATE TABLE IF NOT EXISTS `ressources_humaines` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `aep_id` INT NOT NULL,
    `nom` VARCHAR(150) NOT NULL,
    `fonction` VARCHAR(120) NULL,
    `competences` TEXT NULL,
    `telephone` VARCHAR(50) NULL,
    `statut` ENUM(
        'disponible',
        'occupe',
        'indisponible'
    ) DEFAULT 'disponible',
    `cout_horaire` DECIMAL(12, 2) DEFAULT 0,
    `actif` TINYINT(1) DEFAULT 1,
    `created_at` DATETIME  NULL,
    `updated_at` DATETIME NULL,
    PRIMARY KEY (`id`),
    KEY `idx_rh_aep` (`aep_id`),
    KEY `idx_rh_statut` (`statut`),
    KEY `idx_rh_actif` (`actif`)
) ENGINE = InnoDB DEFAULT CHARSET = utf8;

-- 2) Ressources matérielles
CREATE TABLE IF NOT EXISTS `ressources_materielles` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `aep_id` INT NOT NULL,
    `libelle` VARCHAR(180) NOT NULL,
    `categorie` VARCHAR(120) NULL,
    `reference` VARCHAR(120) NULL,
    `quantite_totale` DECIMAL(12, 2) DEFAULT 0,
    `quantite_disponible` DECIMAL(12, 2) DEFAULT 0,
    `unite` VARCHAR(30) DEFAULT 'u',
    `cout_unitaire` DECIMAL(12, 2) DEFAULT 0,
    `statut` ENUM(
        'disponible',
        'occupe',
        'panne',
        'hors_service'
    ) DEFAULT 'disponible',
    `actif` TINYINT(1) DEFAULT 1,
    `created_at` DATETIME NULL,
    `updated_at` DATETIME NULL,
    PRIMARY KEY (`id`),
    KEY `idx_rm_aep` (`aep_id`),
    KEY `idx_rm_statut` (`statut`),
    KEY `idx_rm_categorie` (`categorie`),
    KEY `idx_rm_actif` (`actif`)
) ENGINE = InnoDB DEFAULT CHARSET = utf8;

-- 3) Interventions
CREATE TABLE IF NOT EXISTS `interventions` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `aep_id` INT NOT NULL,
    `titre` VARCHAR(200) NOT NULL,
    `type` VARCHAR(100) NOT NULL,
    `description` TEXT NULL,
    `localisation` VARCHAR(200) NULL,
    `date_debut_prevue` DATETIME NULL,
    `date_fin_prevue` DATETIME NULL,
    `date_debut_reelle` DATETIME NULL,
    `date_fin_reelle` DATETIME NULL,
    `statut` ENUM(
        'planifiee',
        'en_cours',
        'terminee',
        'annulee'
    ) DEFAULT 'planifiee',
    `cout_estime` DECIMAL(14, 2) DEFAULT 0,
    `cout_reel` DECIMAL(14, 2) DEFAULT 0,
    `created_by` INT NULL,
    `created_at` DATETIME  NULL,
    `updated_at` DATETIME  NULL,
    PRIMARY KEY (`id`),
    KEY `idx_interv_aep` (`aep_id`),
    KEY `idx_interv_statut` (`statut`),
    KEY `idx_interv_dates` (
        `date_debut_prevue`,
        `date_fin_prevue`
    )
) ENGINE = InnoDB DEFAULT CHARSET = utf8;

-- 4) Affectations RH -> Intervention
CREATE TABLE IF NOT EXISTS `intervention_rh` (
    `intervention_id` INT NOT NULL,
    `rh_id` INT NOT NULL,
    `role_sur_intervention` VARCHAR(120) NULL,
    `heures_prevues` DECIMAL(12, 2) DEFAULT 0,
    `heures_reelles` DECIMAL(12, 2) DEFAULT 0,
    `cout_prevu` DECIMAL(14, 2) DEFAULT 0,
    `cout_reel` DECIMAL(14, 2) DEFAULT 0,
    PRIMARY KEY (`intervention_id`, `rh_id`),
    KEY `idx_irh_rh` (`rh_id`),
    CONSTRAINT `fk_irh_intervention` FOREIGN KEY (`intervention_id`) REFERENCES `interventions` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_irh_rh` FOREIGN KEY (`rh_id`) REFERENCES `ressources_humaines` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8;

-- 5) Affectations RM -> Intervention
CREATE TABLE IF NOT EXISTS `intervention_rm` (
    `intervention_id` INT NOT NULL,
    `rm_id` INT NOT NULL,
    `quantite_prevue` DECIMAL(12, 2) DEFAULT 0,
    `quantite_reelle` DECIMAL(12, 2) DEFAULT 0,
    `cout_prevu` DECIMAL(14, 2) DEFAULT 0,
    `cout_reel` DECIMAL(14, 2) DEFAULT 0,
    PRIMARY KEY (`intervention_id`, `rm_id`),
    KEY `idx_irm_rm` (`rm_id`),
    CONSTRAINT `fk_irm_intervention` FOREIGN KEY (`intervention_id`) REFERENCES `interventions` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_irm_rm` FOREIGN KEY (`rm_id`) REFERENCES `ressources_materielles` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8;

-- Vues/agrégations simples (optionnelles)
-- Vue des coûts prévus/réels par intervention
DROP VIEW IF EXISTS `v_intervention_couts`;

CREATE VIEW `v_intervention_couts` AS
SELECT
    i.id AS intervention_id,
    i.titre,
    i.aep_id,
    IFNULL(SUM(irh.cout_prevu), 0) + IFNULL(SUM(irm.cout_prevu), 0) AS cout_prevu_affectations,
    IFNULL(SUM(irh.cout_reel), 0) + IFNULL(SUM(irm.cout_reel), 0) AS cout_reel_affectations
FROM
    interventions i
    LEFT JOIN intervention_rh irh ON irh.intervention_id = i.id
    LEFT JOIN intervention_rm irm ON irm.intervention_id = i.id
GROUP BY
    i.id,
    i.titre,
    i.aep_id;

-- Fin du script