CREATE OR REPLACE VIEW vue_abones_facturation AS
SELECT
    i.id,
    id_compteur, id_aep,
    mf.id as id_mois,
    a.id AS id_abone,
    a.nom AS nom_abone,
    a.id_reseau,
    a.numero_telephone,
    mf.mois,
    cr.id AS id_constante_reseau,
     i.id_mois_facturation,
    mf.date_facturation,
    mf.date_depot,
    i.ancien_index,
    i.nouvel_index, f.id AS id_facture, date_releve,
        penalite, numero_compte_anticipation,
    prix_entretient_compteur,prix_metre_cube_eau, prix_tva,
    -- calculeConso: nouvel_index - ancien_index
    (i.nouvel_index - i.ancien_index) AS consommation,
    -- calculeMontantConso: consommation * prix_eau
    (i.nouvel_index - i.ancien_index) * cr.prix_metre_cube_eau AS montant_conso,
    -- calculeMontantConsoEntretienCompteur: montant_conso + entretien
    ((i.nouvel_index - i.ancien_index) * cr.prix_metre_cube_eau + cr.prix_entretient_compteur) AS montant_conso_entretien,
    -- calculeMontantConsoTva: montant_conso_entretien * (1 + tva/100)
    ((i.nouvel_index - i.ancien_index) * cr.prix_metre_cube_eau + cr.prix_entretient_compteur) * (1 + cr.prix_tva / 100) AS montant_conso_tva,
    -- calculeMontantTotal: montant_conso_tva + penalite + impaye
    ((i.nouvel_index - i.ancien_index) * cr.prix_metre_cube_eau + cr.prix_entretient_compteur) * (1 + cr.prix_tva / 100) + f.penalite AS montant_total,
    -- calculeMontantRestant: montant_total - montant_verse
    (((i.nouvel_index - i.ancien_index) * cr.prix_metre_cube_eau + cr.prix_entretient_compteur) * (1 + cr.prix_tva / 100) + f.penalite ) - f.montant_verse AS montant_restant,
    montant_verse,
    -- calculeMontantAValider: min(montant_total, montant_verse)
    LEAST(
            ((i.nouvel_index - i.ancien_index) * cr.prix_metre_cube_eau + cr.prix_entretient_compteur) * (1 + cr.prix_tva / 100) + f.penalite ,
            f.montant_verse
    ) AS montant_a_valider,
    -- calculeImpaye: montant_conso_tva + penalite + impaye - montant_verse
    (((i.nouvel_index - i.ancien_index) * cr.prix_metre_cube_eau + cr.prix_entretient_compteur) * (1 + cr.prix_tva / 100) + f.penalite - f.montant_verse) AS impaye
FROM
    abone a
        JOIN facture f ON a.id = f.id_abone
        JOIN indexes i ON f.id_indexes = i.id
        JOIN mois_facturation mf ON i.id_mois_facturation = mf.id
        JOIN constante_reseau cr ON mf.id_constante = cr.id;
