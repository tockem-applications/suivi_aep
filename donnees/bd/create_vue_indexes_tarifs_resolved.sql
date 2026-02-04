-- Vue qui résout le tarif à appliquer pour chaque index
-- Garantit une seule ligne par index (différencié si disponible et match, sinon base)
-- Rétrocompatible : les anciens index sans tarif différencié utilisent le tarif de base
-- 
-- IMPORTANT : Utilise id_tarif_differencie stocké dans indexes s'il existe (tarif figé),
-- sinon calcule dynamiquement le tarif (pour les nouveaux index non encore calculés)
-- 
-- Note: Si plusieurs tarifs différenciés matchent, on prend celui avec le min_consommation le plus élevé

CREATE OR REPLACE VIEW `vue_indexes_tarifs_resolved` AS
SELECT 
    i.id AS id_indexes,
    i.id_compteur,
    i.id_mois_facturation,
    i.ancien_index,
    i.nouvel_index,
    i.message,
    (i.nouvel_index - i.ancien_index) AS consommation,
    
    -- Informations de constante_reseau
    cr.id AS id_constante_reseau,
    cr.id_aep,
    cr.date_creation,
    cr.est_actif,
    cr.description,
    
    -- Tarif résolu : utilise id_tarif_differencie stocké s'il existe, sinon calcule dynamiquement
    -- Si id_tarif_differencie est défini, on utilise directement ce tarif (figé)
    -- Sinon, on calcule dynamiquement comme avant (pour compatibilité)
    COALESCE(
        td_stocke.prix_metre_cube_eau,
        td_calc.prix_metre_cube_eau,
        cr.prix_metre_cube_eau
    ) AS prix_metre_cube_eau,
    COALESCE(
        td_stocke.prix_entretient_compteur,
        td_calc.prix_entretient_compteur,
        cr.prix_entretient_compteur
    ) AS prix_entretient_compteur,
    COALESCE(
        td_stocke.prix_tva,
        td_calc.prix_tva,
        cr.prix_tva
    ) AS prix_tva,
    
    -- Type de tarif utilisé
    CASE 
        WHEN td_stocke.id IS NOT NULL THEN 'differencie'
        WHEN td_calc.id IS NOT NULL THEN 'differencie'
        ELSE 'base'
    END AS type_tarif,
    
    -- Métadonnées du tarif différencié (NULL si tarif de base)
    -- Priorité au tarif stocké
    COALESCE(td_stocke.id, td_calc.id) AS id_tarif_differencie,
    COALESCE(td_stocke.min_consommation, td_calc.min_consommation) AS min_consommation,
    COALESCE(td_stocke.max_consommation, td_calc.max_consommation) AS max_consommation
    
FROM 
    indexes i
    INNER JOIN mois_facturation mf ON mf.id = i.id_mois_facturation
    INNER JOIN constante_reseau cr ON cr.id = mf.id_constante
    -- Jointure avec le tarif différencié stocké (prioritaire)
    LEFT JOIN tarif_differencie td_stocke ON td_stocke.id = i.id_tarif_differencie
    -- Jointure avec le meilleur tarif différencié calculé dynamiquement (fallback si id_tarif_differencie est NULL)
    LEFT JOIN tarif_differencie td_calc ON (
        td_calc.id_constante_reseau = cr.id
        AND i.id_tarif_differencie IS NULL  -- Seulement si pas de tarif stocké
        AND (i.nouvel_index - i.ancien_index) >= td_calc.min_consommation
        AND (td_calc.max_consommation IS NULL OR (i.nouvel_index - i.ancien_index) < td_calc.max_consommation)
        -- Prendre le tarif différencié avec le min_consommation le plus élevé (le plus spécifique)
        AND NOT EXISTS (
            SELECT 1
            FROM tarif_differencie td2
            WHERE td2.id_constante_reseau = cr.id
            AND (i.nouvel_index - i.ancien_index) >= td2.min_consommation
            AND (td2.max_consommation IS NULL OR (i.nouvel_index - i.ancien_index) < td2.max_consommation)
            AND td2.min_consommation > td_calc.min_consommation
        )
    );
