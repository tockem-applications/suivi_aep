<?php
//include_once("traitement/aep_t.php");

function display_aep_to_select()
{
    $aep_list = Aep_t::getAll();
    ?>
    <div class="container-fluid">
        <h2 class="d-flex justify-content-center p-3">Liste des AEP</h2>
        <div class="row">
            <?php if (empty($aep_list)): ?>
                <div class="col-12">
                    <div class="alert alert-warning" role="alert">
                        Aucun AEP trouvé.
                    </div>
                </div>
            <?php else: ?>
                <?php foreach ($aep_list as $aep): ?>
                    <?php
                    // Récupérer stats utiles pour l'AEP
                    $aepId = isset($aep['id']) ? (int) $aep['id'] : 0;
                    $libele = isset($aep['libele']) ? $aep['libele'] : '';

                    // Nb réseaux
                    $nbReseaux = 0;
                    $resReseaux = Manager::prepare_query("SELECT COUNT(*) as c FROM reseau WHERE id_aep = ?", array($aepId));
                    if ($resReseaux) {
                        $row = $resReseaux->fetch();
                        $nbReseaux = isset($row['c']) ? (int) $row['c'] : 0;
                    }

                    // Nb abonnés (via réseaux)
                    $nbAbonnes = 0;
                    $resAb = Manager::prepare_query(
                        "SELECT COUNT(*) as c FROM abone a INNER JOIN reseau r ON r.id = a.id_reseau WHERE r.id_aep = ?",
                        array($aepId)
                    );
                    if ($resAb) {
                        $row = $resAb->fetch();
                        $nbAbonnes = isset($row['c']) ? (int) $row['c'] : 0;
                    }

                    // Dernier mois de facturation de l'AEP
                    $lastMois = '';
                    $lastMoisId = 0;
                    $resMois = Manager::prepare_query(
                        "SELECT mf.id, mf.mois, mf.est_actif FROM mois_facturation mf INNER JOIN constante_reseau c ON c.id = mf.id_constante WHERE c.id_aep = ? ORDER BY mf.mois DESC LIMIT 1",
                        array($aepId)
                    );
                    if ($resMois) {
                        $row = $resMois->fetch();
                        if ($row) {
                            $lastMoisId = isset($row['id']) ? (int) $row['id'] : 0;
                            $lastMois = getLetterMonth($row['mois']);
                        }
                    }

                    // Montants facturé/recouvré du dernier mois (tous abonnés)
                    $montantTotal = 0;
                    $montantVerse = 0;
                    $taux = 0;
                    $consoTotale = 0;
                    // Bilan dernier mois par type : BP (branchements privés) / BF (bornes fontaine)
                    $mtBp = 0.0;
                    $mvBp = 0.0;
                    $consoBp = 0.0;
                    $tauxBp = 0;
                    $mtBf = 0.0;
                    $mvBf = 0.0;
                    $consoBf = 0.0;
                    $tauxBf = 0;
                    if ($lastMoisId > 0) {
                        $resV = Manager::prepare_query(
                            "SELECT SUM(vaf.montant_total) as mt, SUM(vaf.montant_verse) as mv, SUM(vaf.consommation) as cs FROM vue_abones_facturation vaf WHERE vaf.id_mois = ? AND vaf.id_aep = ?",
                            array($lastMoisId, $aepId)
                        );
                        if ($resV) {
                            $row = $resV->fetch();
                            $montantTotal = isset($row['mt']) ? (float) $row['mt'] : 0;
                            $montantVerse = isset($row['mv']) ? (float) $row['mv'] : 0;
                            $consoTotale = isset($row['cs']) ? (float) $row['cs'] : 0;
                            $taux = ($montantTotal > 0) ? round(($montantVerse * 100.0) / $montantTotal) : 0;
                        }
                        try {
                            $resBp = Manager::prepare_query(
                                "SELECT SUM(vaf.montant_total) AS mt, SUM(vaf.montant_verse) AS mv, SUM(vaf.consommation) AS cs
                                 FROM vue_abones_facturation vaf
                                 INNER JOIN abone a ON a.id = vaf.id_abone
                                 WHERE vaf.id_mois = ? AND vaf.id_aep = ?
                                   AND (a.type_abone IS NULL OR a.type_abone = '' OR a.type_abone = 'BP')",
                                array($lastMoisId, $aepId)
                            );
                            if ($resBp) {
                                $r = $resBp->fetch();
                                $mtBp = isset($r['mt']) ? (float) $r['mt'] : 0.0;
                                $mvBp = isset($r['mv']) ? (float) $r['mv'] : 0.0;
                                $consoBp = isset($r['cs']) ? (float) $r['cs'] : 0.0;
                                $tauxBp = ($mtBp > 0) ? (int) round(($mvBp * 100.0) / $mtBp) : 0;
                            }
                            $resBf = Manager::prepare_query(
                                "SELECT SUM(vaf.montant_total) AS mt, SUM(vaf.montant_verse) AS mv, SUM(vaf.consommation) AS cs
                                 FROM vue_abones_facturation vaf
                                 INNER JOIN abone a ON a.id = vaf.id_abone
                                 WHERE vaf.id_mois = ? AND vaf.id_aep = ? AND a.type_abone = 'BF'",
                                array($lastMoisId, $aepId)
                            );
                            if ($resBf) {
                                $r = $resBf->fetch();
                                $mtBf = isset($r['mt']) ? (float) $r['mt'] : 0.0;
                                $mvBf = isset($r['mv']) ? (float) $r['mv'] : 0.0;
                                $consoBf = isset($r['cs']) ? (float) $r['cs'] : 0.0;
                                $tauxBf = ($mtBf > 0) ? (int) round(($mvBf * 100.0) / $mtBf) : 0;
                            }
                        } catch (Exception $e) {
                            $mtBp = $mvBp = $consoBp = $mtBf = $mvBf = $consoBf = 0.0;
                            $tauxBp = $tauxBf = 0;
                        }
                    }

                    // Rendement production (distribution) : vol. abonnés / vol. compteurs réseau « distribution »
                    $volDistribution = 0.0;
                    $volAbonnesIndexes = 0.0;
                    $tauxRendementProd = null;
                    if ($lastMoisId > 0) {
                        try {
                            $resVD = Manager::prepare_query(
                                "SELECT SUM(i.nouvel_index - i.ancien_index) AS vd
                                 FROM indexes i
                                 INNER JOIN mois_facturation mf ON mf.id = i.id_mois_facturation
                                 INNER JOIN constante_reseau c ON c.id = mf.id_constante
                                 INNER JOIN compteur_reseau cr ON cr.id_compteur = i.id_compteur AND cr.type_compteur = 'distribution'
                                 WHERE c.id_aep = ? AND mf.id = ?",
                                array($aepId, $lastMoisId)
                            );
                            if ($resVD) {
                                $rVD = $resVD->fetch();
                                $volDistribution = isset($rVD['vd']) ? (float) $rVD['vd'] : 0.0;
                            }
                            $resVA = Manager::prepare_query(
                                "SELECT SUM(i.nouvel_index - i.ancien_index) AS va
                                 FROM indexes i
                                 INNER JOIN mois_facturation mf ON mf.id = i.id_mois_facturation
                                 INNER JOIN constante_reseau c ON c.id = mf.id_constante
                                 INNER JOIN compteur_abone ca ON ca.id_compteur = i.id_compteur
                                 WHERE c.id_aep = ? AND mf.id = ?",
                                array($aepId, $lastMoisId)
                            );
                            if ($resVA) {
                                $rVA = $resVA->fetch();
                                $volAbonnesIndexes = isset($rVA['va']) ? (float) $rVA['va'] : 0.0;
                            }
                            if ($volDistribution > 0) {
                                $tauxRendementProd = round(($volAbonnesIndexes * 100.0) / $volDistribution, 1);
                            }
                        } catch (Exception $e) {
                            $tauxRendementProd = null;
                            $volDistribution = 0.0;
                            $volAbonnesIndexes = 0.0;
                        }
                    }

                    // Styles utilitaires (en-tête carte)
                    $badgeClass = $lastMoisId <= 0 ? 'bg-secondary' : ($taux >= 95 ? 'bg-success' : ($taux >= 70 ? 'bg-warning' : 'bg-danger'));
                    $badgeProdClass = 'bg-secondary';
                    if ($tauxRendementProd !== null) {
                        $badgeProdClass = ($tauxRendementProd >= 85 && $tauxRendementProd <= 115) ? 'bg-success' : (($tauxRendementProd >= 60) ? 'bg-warning' : 'bg-danger');
                    }
                    ?>
                    <?php
                    $typeDistribution = isset($aep['type_distribution'])
                        ? Aep::normaliserTypeDistribution($aep['type_distribution'])
                        : null;
                    $typeDistributionLabel = '';
                    $typeDistributionClass = 'bg-light text-muted border';
                    if ($typeDistribution === 'RDS') {
                        $typeDistributionLabel = 'RDS';
                        $typeDistributionClass = 'bg-primary';
                    } elseif ($typeDistribution === 'RDC') {
                        $typeDistributionLabel = 'RDC';
                        $typeDistributionClass = 'bg-info text-dark';
                    }
                    $typeDistributionTitle = $typeDistribution === 'RDS'
                        ? 'Refoulement Distribution Séparé'
                        : ($typeDistribution === 'RDC' ? 'Refoulement Distribution Confondu' : 'Type de réseau non défini');
                    ?>
                    <div class="col-md-6 col-lg-4 mb-4">
                        <div class="card shadow-sm border-0 h-100">
                            <div class="card-header bg-light d-flex flex-wrap justify-content-between align-items-center gap-2">
                                <div class="d-flex align-items-center gap-2">
                                    <strong class="text-primary"><?php echo htmlspecialchars($libele); ?></strong>
                                    <span class="badge <?php echo $typeDistributionClass; ?>"
                                        title="<?php echo htmlspecialchars($typeDistributionTitle); ?>">
                                        <?php echo $typeDistributionLabel !== '' ? $typeDistributionLabel : '— type'; ?>
                                    </span>
                                </div>
                                <div class="d-flex flex-wrap gap-1 justify-content-end">
                                    <span class="badge <?php echo $badgeClass; ?>" title="Taux de recouvrement sur le dernier mois de facturation">
                                        <?php echo $lastMoisId > 0 ? $taux . '%' : '—'; ?> financier
                                    </span>
                                    <span class="badge <?php echo $badgeProdClass; ?>" title="Volume index abonnés / volume compteurs réseau distribution (dernier mois)">
                                        <?php echo $tauxRendementProd !== null ? $tauxRendementProd . '% prod.' : '— prod.'; ?>
                                    </span>
                                </div>
                            </div>
                            <div class="card-body">
                                <div class="d-flex justify-content-between mb-2">
                                    <div>
                                        <div class="small text-muted">Réseaux</div>
                                        <div class="fs-5"><?php echo $nbReseaux; ?></div>
                                    </div>
                                    <div>
                                        <div class="small text-muted">Abonnés</div>
                                        <div class="fs-5"><?php echo $nbAbonnes; ?></div>
                                    </div>
                                    <div>
                                        <div class="small text-muted">Mois</div>
                                        <div class="fs-6"><?php echo $lastMois ? htmlspecialchars($lastMois) : '—'; ?></div>
                                    </div>
                                </div>
                                <div class="row g-2">
                                    <div class="col-6">
                                        <div class="p-2 bg-light rounded border">
                                            <div class="small text-muted">Facturé (dernier mois)</div>
                                            <div class="fw-bold"><?php echo number_format($montantTotal, 0, ',', ' '); ?> FCFA</div>
                                        </div>
                                    </div>
                                    <div class="col-6">
                                        <div class="p-2 bg-light rounded border">
                                            <div class="small text-muted">Recouvré</div>
                                            <div class="fw-bold text-success">
                                                <?php echo number_format($montantVerse, 0, ',', ' '); ?> FCFA
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-12">
                                        <div class="p-2 bg-light rounded border">
                                            <div class="small text-muted">Consommation facturée (dernier mois)</div>
                                            <div class="fw-bold"><?php echo number_format($consoTotale, 2, ',', ' '); ?> m³</div>
                                        </div>
                                    </div>
                                </div>
                                <div class="border-top pt-2 mt-2">
                                    <div class="small text-uppercase text-muted mb-2">Bilan par type (dernier mois<?php echo $lastMois ? ' : ' . htmlspecialchars($lastMois) : ''; ?>)</div>
                                    <div class="row g-2">
                                        <div class="col-md-6">
                                            <div class="p-2 rounded border-start border-4 border-success bg-body-secondary bg-opacity-25">
                                                <div class="small text-muted">Branchements privés (BP)</div>
                                                <div class="fs-5 fw-bold text-dark">
                                                    <?php if ($lastMoisId > 0 && $mtBp > 0): ?>
                                                        <?php echo $tauxBp; ?> %
                                                    <?php elseif ($lastMoisId > 0): ?>
                                                        <span class="text-muted fs-6">0 F facturé</span>
                                                    <?php else: ?>
                                                        <span class="text-muted fs-6">—</span>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="small text-muted">
                                                    Facturé · <?php echo $lastMoisId > 0 ? number_format($mtBp, 0, ',', ' ') . ' FCFA' : '—'; ?>
                                                </div>
                                                <div class="small text-muted">
                                                    Recouvré · <?php echo $lastMoisId > 0 ? number_format($mvBp, 0, ',', ' ') . ' FCFA' : '—'; ?>
                                                </div>
                                                <div class="small text-muted mt-1">
                                                    Volume consommé · <?php echo $lastMoisId > 0 ? number_format($consoBp, 2, ',', ' ') . ' m³' : '—'; ?>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="p-2 rounded border-start border-4 border-info bg-body-secondary bg-opacity-25">
                                                <div class="small text-muted">Bornes fontaine (BF)</div>
                                                <div class="fs-5 fw-bold text-dark">
                                                    <?php if ($lastMoisId > 0 && $mtBf > 0): ?>
                                                        <?php echo $tauxBf; ?> %
                                                    <?php elseif ($lastMoisId > 0): ?>
                                                        <span class="text-muted fs-6">0 F facturé</span>
                                                    <?php else: ?>
                                                        <span class="text-muted fs-6">—</span>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="small text-muted">
                                                    Facturé · <?php echo $lastMoisId > 0 ? number_format($mtBf, 0, ',', ' ') . ' FCFA' : '—'; ?>
                                                </div>
                                                <div class="small text-muted">
                                                    Recouvré · <?php echo $lastMoisId > 0 ? number_format($mvBf, 0, ',', ' ') . ' FCFA' : '—'; ?>
                                                </div>
                                                <div class="small text-muted mt-1">
                                                    Volume consommé · <?php echo $lastMoisId > 0 ? number_format($consoBf, 2, ',', ' ') . ' m³' : '—'; ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="card-footer bg-white d-flex gap-2">
                                <a href="traitement/aep_t.php?select_aep=true&id_aep=<?php echo $aepId; ?>"
                                    class="btn btn-primary flex-fill">
                                    Sélectionner
                                </a>
<!--                                <a href="?page=aep_detail&aep_id=--><?php //echo $aepId; ?><!--" class="btn btn-outline-secondary flex-fill">-->
<!--                                    Détails AEP-->
<!--                                </a>-->
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>

            <!-- Tuile d'ajout : meme colonne que les cartes d'AEP, sinon elle
                 sort de la grille et casse l'alignement de la derniere ligne.
                 La carte entiere est le bouton -- viser une vignette est plus
                 simple que viser un bouton loge dans son pied. -->
            <div class="col-md-6 col-lg-4 mb-4">
                <button type="button" class="aep-nouvelle-carte card h-100 w-100 border-0 text-start"
                    data-bs-toggle="modal" data-bs-target="#createAepModal">
                    <div class="card-body d-flex flex-column align-items-center justify-content-center text-center py-5">
                        <div class="aep-nouvelle-icone rounded-circle d-inline-flex align-items-center justify-content-center mb-3">
                            <i class="bi bi-plus-lg"></i>
                        </div>
                        <h5 class="card-title mb-1">Nouvel AEP</h5>
                        <p class="card-text text-muted small mb-0">
                            Ajouter un réseau d'adduction au suivi
                        </p>
                    </div>
                </button>
            </div>
        </div>
    </div>
    <?php

}

function display_li_aep_to_select()
{
    $aep_list = Aep_t::getAll();
    ?>
    <li class="nav-item dropdown">
        <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown"
            aria-expanded="false">
            <?php echo htmlspecialchars($_SESSION['libele_aep']) ?>
        </a>
        <ul class="dropdown-menu" aria-labelledby="navbarDropdown">
            <!--                        <li><a class="dropdown-item" href="?form=contrat">Facturer des abonés</a></li>-->
            <?php if (empty($aep_list)): ?>
                <div class="col-12">
                    <div class="alert alert-warning" role="alert">
                        Aucun AEP trouvé.
                    </div>
                </div>
            <?php else: ?>
                <?php foreach ($aep_list as $aep): ?>
                    <li><a class="dropdown-item <?php /*echo $aep['id']==$_SESSION['id_aep']?'disabled':''; */ ?>"
                            href="traitement/aep_t.php?select_aep=true&id_aep=<?php echo $aep['id']; ?>"><?php echo htmlspecialchars($aep['libele']); ?></a>
                    </li>
                <?php endforeach; ?>
                <li>
                    <hr class="dropdown-divider">
                </li>
                <li>
                    <button type="button" class="dropdown-item" data-bs-toggle="modal" data-bs-target="#createAepModal">Nouvel AEP</button>
                </li>
                <li><a class="dropdown-item" href="traitement/aep_t.php?select_aep=true&id_aep=0">tout fermer</a>
                </li>
            <?php endif; ?>

        </ul>
    </li>
    <?php

}

// -----------------------------------------------------------------------------
// Creation d'un AEP, en modale.
//
// Elle remplace l'ancienne page ?form=aep : meme presentation que la modale de
// modification, pour que creer et modifier ne demandent pas deux apprentissages.
// Rendue ici parce que aep_components.php est inclus par index.php sur toutes
// les pages : le bouton « Creer » du tableau de bord comme celui de la liste
// des AEP visent donc la meme modale.
// -----------------------------------------------------------------------------
@include_once("donnees/aep.php");
@include_once("../donnees/aep.php");
?>
<style>
    /* Modales AEP (creation et modification) — apparence moderne */
    .aep-edit-modal .modal-content { border-radius: 1rem; overflow: hidden; }

    /* Le <form> enveloppe l'en-tete, le corps et le pied. Bootstrap attend ces
       trois blocs en enfants directs de .modal-content : sans cette regle, le
       formulaire n'est pas une boite flexible, .modal-body ne peut pas retrecir,
       et le contenu deborde au lieu de defiler -- l'en-tete et le pied sortent
       alors de l'ecran sur un formulaire long. */
    .aep-edit-modal .modal-content > form {
        display: flex;
        flex-direction: column;
        min-height: 0;
        max-height: 100%;
    }
    .aep-edit-modal .modal-content > form > .modal-body {
        overflow-y: auto;
        min-height: 0;
    }
    .aep-edit-modal .modal-content > form > .modal-header,
    .aep-edit-modal .modal-content > form > .modal-footer {
        flex: 0 0 auto;
    }
    .aep-edit-header {
        background: linear-gradient(135deg, #0d6efd 0%, #4dabf7 100%);
        padding: 1.1rem 1.5rem;
    }
    .aep-edit-icon {
        width: 2.75rem; height: 2.75rem;
        background: rgba(255, 255, 255, 0.2);
        border: 1px solid rgba(255, 255, 255, 0.35);
    }
    /* Tuile « Nouvel AEP » : bordure en pointilles pour la distinguer d'un AEP
       existant, et non d'une carte pleine qui laisserait croire a une donnee. */
    .aep-nouvelle-carte {
        background-color: transparent;
        border: 2px dashed #ced4da !important;
        color: inherit;
        transition: border-color .15s ease, background-color .15s ease, transform .15s ease, box-shadow .15s ease;
    }
    .aep-nouvelle-carte:hover,
    .aep-nouvelle-carte:focus-visible {
        border-color: #198754 !important;
        background-color: #f3faf5;
        transform: translateY(-2px);
        box-shadow: 0 .5rem 1rem rgba(25, 135, 84, .10);
    }
    .aep-nouvelle-carte .aep-nouvelle-icone {
        width: 3.25rem; height: 3.25rem;
        background-color: #e8f5ec;
        color: #198754;
        font-size: 1.5rem;
        transition: background-color .15s ease;
    }
    .aep-nouvelle-carte:hover .aep-nouvelle-icone { background-color: #d3ecdb; }
    .aep-nouvelle-carte .card-title { color: #198754; font-weight: 600; }

    .aep-edit-section { transition: box-shadow .2s ease, transform .2s ease; }
    .aep-edit-section:hover { box-shadow: 0 .5rem 1rem rgba(0,0,0,.06) !important; }

    /* Cartes radio (type de réseau) */
    .aep-type-card {
        cursor: pointer;
        transition: border-color .15s ease, background-color .15s ease, transform .15s ease, box-shadow .15s ease;
        background-color: #fff;
    }
    .aep-type-card .aep-type-check { opacity: 0; transform: scale(.7); transition: opacity .15s ease, transform .15s ease; }
    .aep-type-card:hover { border-color: #0d6efd; transform: translateY(-1px); box-shadow: 0 .25rem .5rem rgba(13,110,253,.08); }
    .aep-type-input:checked + .aep-type-card {
        border-color: #0d6efd; border-width: 2px;
        background-color: #f1f7ff;
        box-shadow: 0 .25rem .75rem rgba(13,110,253,.12);
    }
    .aep-type-input:checked + .aep-type-card .aep-type-check { opacity: 1; transform: scale(1); }
    .aep-type-input:focus-visible + .aep-type-card { outline: 2px solid #0d6efd; outline-offset: 2px; }

    /* Cartes radio (modèle de facture) */
    .aep-model-card {
        cursor: pointer;
        transition: border-color .15s ease, transform .15s ease, box-shadow .15s ease;
        background-color: #fff;
    }
    .aep-model-card .aep-model-check { opacity: 0; transform: scale(.7); transition: opacity .15s ease, transform .15s ease; }
    .aep-model-card:hover { border-color: #0d6efd; transform: translateY(-1px); box-shadow: 0 .25rem .5rem rgba(13,110,253,.08); }
    .aep-model-input:checked + .aep-model-card {
        border-color: #0d6efd; border-width: 2px;
        box-shadow: 0 .25rem .75rem rgba(13,110,253,.12);
    }
    .aep-model-input:checked + .aep-model-card .aep-model-check { opacity: 1; transform: scale(1); }
    .aep-model-input:focus-visible + .aep-model-card { outline: 2px solid #0d6efd; outline-offset: 2px; }
</style>
<div class="modal fade aep-edit-modal" id="createAepModal" tabindex="-1"
    aria-labelledby="createAepModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content border-0 shadow-lg">
            <form action="traitement/aep_t.php?ajout=true" method="post" novalidate>
                <div class="modal-header aep-edit-header text-white border-0">
                    <div class="d-flex align-items-center gap-3">
                        <div class="aep-edit-icon rounded-circle d-inline-flex align-items-center justify-content-center">
                            <i class="bi bi-droplet-half fs-4"></i>
                        </div>
                        <div>
                            <h5 class="modal-title mb-0 fw-semibold" id="createAepModalLabel">Nouvel AEP</h5>
                            <div class="small opacity-75">
                                <i class="bi bi-plus-circle me-1"></i> Créer un réseau d'adduction
                            </div>
                        </div>
                    </div>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"
                        aria-label="Fermer"></button>
                </div>

                <div class="modal-body bg-body-tertiary p-4">
                    <!-- Identité -->
                    <section class="aep-edit-section card border-0 shadow-sm mb-3">
                        <div class="card-body">
                            <h6 class="text-uppercase text-muted small fw-bold mb-3">
                                <i class="bi bi-info-circle me-1"></i> Identité
                            </h6>
                            <div class="row g-3">
                                <div class="col-md-8">
                                    <label for="creer_libele" class="form-label">
                                        Libellé <span class="text-danger">*</span>
                                    </label>
                                    <input type="text" class="form-control" id="creer_libele" name="libele"
                                        minlength="3" maxlength="64" required placeholder="Bassessa">
                                </div>
                                <div class="col-md-4">
                                    <label for="creer_date" class="form-label">
                                        Date <span class="text-danger">*</span>
                                    </label>
                                    <input type="date" class="form-control" id="creer_date" name="date" required
                                        value="<?php echo date('Y-m-d'); ?>">
                                </div>
                                <div class="col-12">
                                    <label for="creer_description" class="form-label">
                                        Description <span class="text-danger">*</span>
                                    </label>
                                    <textarea class="form-control" id="creer_description" name="description" rows="2"
                                        minlength="10" required
                                        placeholder="Réseau d'adduction d'eau potable de…"></textarea>
                                </div>
                            </div>
                        </div>
                    </section>

                    <!-- Détails bancaires -->
                    <section class="aep-edit-section card border-0 shadow-sm mb-3">
                        <div class="card-body">
                            <h6 class="text-uppercase text-muted small fw-bold mb-3">
                                <i class="bi bi-bank me-1"></i> Détails bancaires
                                <span class="text-muted fw-normal text-lowercase">— optionnel</span>
                            </h6>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label for="creer_nom_banque" class="form-label">Nom de la banque</label>
                                    <div class="input-group">
                                        <span class="input-group-text bg-body-tertiary"><i class="bi bi-building"></i></span>
                                        <input type="text" class="form-control" id="creer_nom_banque"
                                            name="nom_banque" maxlength="64">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <label for="creer_numero_compte" class="form-label">Numéro de compte</label>
                                    <div class="input-group">
                                        <span class="input-group-text bg-body-tertiary"><i class="bi bi-credit-card-2-front"></i></span>
                                        <input type="text" class="form-control" id="creer_numero_compte"
                                            name="numero_compte" maxlength="32" pattern="[A-Za-z0-9\-]{0,32}">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </section>

                    <!-- Paiement mobile -->
                    <section class="aep-edit-section card border-0 shadow-sm mb-3">
                        <div class="card-body">
                            <h6 class="text-uppercase text-muted small fw-bold mb-3">
                                <i class="bi bi-phone me-1"></i> Paiement mobile
                                <span class="text-muted fw-normal text-lowercase">— optionnel</span>
                            </h6>
                            <p class="text-muted small mb-3">
                                Reproduits tels quels sur la facture. Saisis la syntaxe complète ainsi que
                                le nom qui s'affichera à la validation&nbsp;: c'est ce qui permet à l'abonné
                                de vérifier qu'il paie au bon destinataire.
                            </p>
                            <div class="row g-3">
                                <div class="col-12">
                                    <label for="creer_code_marchand_1" class="form-label">Code marchand</label>
                                    <textarea class="form-control" rows="2" maxlength="255"
                                        id="creer_code_marchand_1" name="code_marchand_1"></textarea>
                                    <div class="form-text">Modèle&nbsp;: <code class="user-select-all">MOMO: *126*14*NUMERO*Montant#. Nom: NOM DU BENEFICIAIRE</code></div>
                                </div>
                            </div>
                        </div>
                    </section>

                    <!-- Type de réseau -->
                    <section class="aep-edit-section card border-0 shadow-sm mb-3">
                        <div class="card-body">
                            <h6 class="text-uppercase text-muted small fw-bold mb-1">
                                <i class="bi bi-diagram-3 me-1"></i> Type de réseau
                            </h6>
                            <p class="small text-muted mb-3">
                                Conditionne le calcul du <strong>rendement</strong> du réseau (production / distribution).
                            </p>
                            <div class="row g-2 aep-type-options">
                                <div class="col-md-4">
                                    <input class="form-check-input visually-hidden aep-type-input" type="radio"
                                        name="type_distribution" id="creer_td_rds" value="RDS">
                                    <label class="aep-type-card border rounded-3 p-3 d-block h-100" for="creer_td_rds">
                                        <div class="d-flex align-items-center gap-2 mb-1">
                                            <i class="bi bi-arrow-right-square text-primary fs-5"></i>
                                            <span class="fw-bold">RDS</span>
                                            <i class="bi bi-check-circle-fill aep-type-check ms-auto text-success"></i>
                                        </div>
                                        <div class="small text-muted">Refoulement Distribution <strong>Séparé</strong></div>
                                    </label>
                                </div>
                                <div class="col-md-4">
                                    <input class="form-check-input visually-hidden aep-type-input" type="radio"
                                        name="type_distribution" id="creer_td_rdc" value="RDC">
                                    <label class="aep-type-card border rounded-3 p-3 d-block h-100" for="creer_td_rdc">
                                        <div class="d-flex align-items-center gap-2 mb-1">
                                            <i class="bi bi-share text-info fs-5"></i>
                                            <span class="fw-bold">RDC</span>
                                            <i class="bi bi-check-circle-fill aep-type-check ms-auto text-success"></i>
                                        </div>
                                        <div class="small text-muted">Refoulement Distribution <strong>Confondu</strong></div>
                                    </label>
                                </div>
                                <div class="col-md-4">
                                    <input class="form-check-input visually-hidden aep-type-input" type="radio"
                                        name="type_distribution" id="creer_td_none" value="" checked>
                                    <label class="aep-type-card border rounded-3 p-3 d-block h-100" for="creer_td_none">
                                        <div class="d-flex align-items-center gap-2 mb-1">
                                            <i class="bi bi-dash-circle text-muted fs-5"></i>
                                            <span class="fw-bold text-muted">Non défini</span>
                                            <i class="bi bi-check-circle-fill aep-type-check ms-auto text-success"></i>
                                        </div>
                                        <div class="small text-muted">À renseigner ultérieurement</div>
                                    </label>
                                </div>
                            </div>
                        </div>
                    </section>

                    <!-- Modèle de facture -->
                    <section class="aep-edit-section card border-0 shadow-sm">
                        <div class="card-body">
                            <h6 class="text-uppercase text-muted small fw-bold mb-3">
                                <i class="bi bi-receipt me-1"></i> Modèle de facture
                                <span class="text-danger">*</span>
                            </h6>
                            <div class="row g-3 aep-model-options">
                                <div class="col-md-6">
                                    <input class="visually-hidden aep-model-input" type="radio"
                                        name="fichier_facture" id="creer_mf_fokoue" value="model_fokoue" required>
                                    <label for="creer_mf_fokoue"
                                        class="aep-model-card border rounded-3 d-block h-100 overflow-hidden">
                                        <div class="ratio ratio-16x9 bg-body-tertiary">
                                            <img src="presentation/assets/images/model_fokoue.png"
                                                alt="Modèle Fokoué" class="object-fit-contain p-2">
                                        </div>
                                        <div class="p-2 d-flex align-items-center justify-content-between">
                                            <span class="fw-semibold">Modèle de Fokoué</span>
                                            <i class="bi bi-check-circle-fill aep-model-check text-success"></i>
                                        </div>
                                    </label>
                                </div>
                                <div class="col-md-6">
                                    <input class="visually-hidden aep-model-input" type="radio"
                                        name="fichier_facture" id="creer_mf_nkong" value="model_nkongzem" checked>
                                    <label for="creer_mf_nkong"
                                        class="aep-model-card border rounded-3 d-block h-100 overflow-hidden">
                                        <div class="ratio ratio-16x9 bg-body-tertiary">
                                            <img src="presentation/assets/images/model_nkongzem.png"
                                                alt="Modèle Nkongzem" class="object-fit-contain p-2">
                                        </div>
                                        <div class="p-2 d-flex align-items-center justify-content-between">
                                            <span class="fw-semibold">Modèle de Nkongzem</span>
                                            <i class="bi bi-check-circle-fill aep-model-check text-success"></i>
                                        </div>
                                    </label>
                                </div>
                            </div>
                        </div>
                    </section>
                </div>

                <div class="modal-footer bg-white border-top">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">
                        <i class="bi bi-x-lg me-1"></i> Annuler
                    </button>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-check2 me-1"></i> Créer l'AEP
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
