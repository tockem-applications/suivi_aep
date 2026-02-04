-- Vue pour les abonnés et leurs facturations
-- Utilise vue_indexes_tarifs_resolved pour obtenir les tarifs (base ou différencié selon la consommation)
-- Rétrocompatible : même structure de colonnes que la version précédente
-- Les anciennes factures continuent de fonctionner car elles utilisent le tarif de base

CREATE OR REPLACE VIEW `vue_abones_facturation` AS
SELECT
    vit.id_indexes AS id,
    vit.id_compteur,
    vit.id_aep,
    mf.id AS id_mois,
    a.id AS id_abone,
    a.nom AS nom_abone,
    a.id_reseau,
    a.numero_telephone,
    mf.mois,
    vit.id_constante_reseau,
    vit.id_mois_facturation,
    mf.date_facturation,
    mf.date_depot,
    vit.ancien_index,
    vit.nouvel_index,
    f.id AS id_facture,
    mf.date_releve,
    f.penalite,
    a.numero_compte_anticipation,
    -- Utiliser les prix résolus de vue_indexes_tarifs_resolved
    -- Si tarif différencié non autorisé, on force l'utilisation du tarif de base
    CASE 
        WHEN a.tarif_differencie_autorise = 0 THEN cr.prix_entretient_compteur
        ELSE vit.prix_entretient_compteur
    END AS prix_entretient_compteur,
    CASE 
        WHEN a.tarif_differencie_autorise = 0 THEN cr.prix_metre_cube_eau
        ELSE vit.prix_metre_cube_eau
    END AS prix_metre_cube_eau,
    CASE 
        WHEN a.tarif_differencie_autorise = 0 THEN cr.prix_tva
        ELSE vit.prix_tva
    END AS prix_tva,
    -- Consommation (déjà calculée dans vue_indexes_tarifs_resolved)
    vit.consommation,
    -- Calculs financiers (utilisent les prix résolus)
    vit.consommation * CASE 
        WHEN a.tarif_differencie_autorise = 0 THEN cr.prix_metre_cube_eau
        ELSE vit.prix_metre_cube_eau
    END AS montant_conso,
    (vit.consommation * CASE 
        WHEN a.tarif_differencie_autorise = 0 THEN cr.prix_metre_cube_eau
        ELSE vit.prix_metre_cube_eau
    END + CASE 
        WHEN a.tarif_differencie_autorise = 0 THEN cr.prix_entretient_compteur
        ELSE vit.prix_entretient_compteur
    END) AS montant_conso_entretien,
    (vit.consommation * CASE 
        WHEN a.tarif_differencie_autorise = 0 THEN cr.prix_metre_cube_eau
        ELSE vit.prix_metre_cube_eau
    END + CASE 
        WHEN a.tarif_differencie_autorise = 0 THEN cr.prix_entretient_compteur
        ELSE vit.prix_entretient_compteur
    END) * (1 + CASE 
        WHEN a.tarif_differencie_autorise = 0 THEN cr.prix_tva
        ELSE vit.prix_tva
    END / 100) AS montant_conso_tva,
    ((vit.consommation * CASE 
        WHEN a.tarif_differencie_autorise = 0 THEN cr.prix_metre_cube_eau
        ELSE vit.prix_metre_cube_eau
    END + CASE 
        WHEN a.tarif_differencie_autorise = 0 THEN cr.prix_entretient_compteur
        ELSE vit.prix_entretient_compteur
    END) * (1 + CASE 
        WHEN a.tarif_differencie_autorise = 0 THEN cr.prix_tva
        ELSE vit.prix_tva
    END / 100) + f.penalite) AS montant_total,
    (((vit.consommation * CASE 
        WHEN a.tarif_differencie_autorise = 0 THEN cr.prix_metre_cube_eau
        ELSE vit.prix_metre_cube_eau
    END + CASE 
        WHEN a.tarif_differencie_autorise = 0 THEN cr.prix_entretient_compteur
        ELSE vit.prix_entretient_compteur
    END) * (1 + CASE 
        WHEN a.tarif_differencie_autorise = 0 THEN cr.prix_tva
        ELSE vit.prix_tva
    END / 100) + f.penalite) - f.montant_verse) AS montant_restant,
    f.montant_verse,
    LEAST(
        ((vit.consommation * CASE 
            WHEN a.tarif_differencie_autorise = 0 THEN cr.prix_metre_cube_eau
            ELSE vit.prix_metre_cube_eau
        END + CASE 
            WHEN a.tarif_differencie_autorise = 0 THEN cr.prix_entretient_compteur
            ELSE vit.prix_entretient_compteur
        END) * (1 + CASE 
            WHEN a.tarif_differencie_autorise = 0 THEN cr.prix_tva
            ELSE vit.prix_tva
        END / 100) + f.penalite),
        f.montant_verse
    ) AS montant_a_valider,
    (((vit.consommation * CASE 
        WHEN a.tarif_differencie_autorise = 0 THEN cr.prix_metre_cube_eau
        ELSE vit.prix_metre_cube_eau
    END + CASE 
        WHEN a.tarif_differencie_autorise = 0 THEN cr.prix_entretient_compteur
        ELSE vit.prix_entretient_compteur
    END) * (1 + CASE 
        WHEN a.tarif_differencie_autorise = 0 THEN cr.prix_tva
        ELSE vit.prix_tva
    END / 100) + f.penalite) - f.montant_verse) AS impaye
FROM
    abone a
    JOIN facture f ON a.id = f.id_abone
    JOIN vue_indexes_tarifs_resolved vit ON f.id_indexes = vit.id_indexes
    JOIN mois_facturation mf ON vit.id_mois_facturation = mf.id
    JOIN constante_reseau cr ON mf.id_constante = cr.id;
