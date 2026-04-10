<?php

@include_once("../donnees/manager.php");
@include_once("donnees/manager.php");
@include_once("donnees/reseau.php");
@include_once("donnees/compteur.php");
@include_once("presentation/reseau_component.php");
@include_once("presentation/reseau_modals_component.php");

function ensureCompteurTypeColumnForDetailPage()
{
    try {
        $exists = Manager::prepare_query(
            "SELECT COUNT(*) AS c
             FROM information_schema.columns
             WHERE table_schema = DATABASE()
               AND table_name = 'compteur_reseau'
               AND column_name = 'type_compteur'",
            array()
        )->fetch();
        if (!$exists || (int) $exists['c'] === 0) {
            Manager::prepare_query(
                "ALTER TABLE `compteur_reseau`
                 ADD COLUMN `type_compteur` ENUM('production','distribution','reservoir')
                 NOT NULL DEFAULT 'distribution' AFTER `id_compteur`",
                array()
            );
        } else {
            Manager::prepare_query(
                "UPDATE compteur_reseau SET type_compteur = 'distribution' WHERE type_compteur IS NULL OR type_compteur = ''",
                array()
            );
        }
    } catch (Exception $e) {
        // fallback silencieux
    }
}

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$aepId = isset($_SESSION['id_aep']) ? (int) $_SESSION['id_aep'] : 0;
$message = '';
ensureCompteurTypeColumnForDetailPage();

$reseauId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if (!$aepId || !$reseauId) {
    header('Location: ?page=reseaux&error=invalid');
    exit;
}

// Filtres de mois (optionnels)
$moisDebut = isset($_GET['mois_debut']) ? $_GET['mois_debut'] : '';
$moisFin = isset($_GET['mois_fin']) ? $_GET['mois_fin'] : '';

// Charger réseau
$reseau = Manager::prepare_query(
    "SELECT r.*, p.nom AS parent_nom,
            (SELECT COUNT(*) FROM abone a WHERE a.id_reseau = r.id) AS nb_abonnes,
            (SELECT COUNT(*) FROM reseau rf WHERE rf.id_reseau_parent = r.id) AS nb_enfants
     FROM reseau r
     LEFT JOIN reseau p ON p.id = r.id_reseau_parent
     WHERE r.id = ? AND r.id_aep = ?",
    array($reseauId, $aepId)
)->fetch();
if (!$reseau) {
    header('Location: ?page=reseaux&error=not_found');
    exit;
}

$reseaux = Manager::prepare_query(
    "SELECT * FROM reseau WHERE id_aep = ? ORDER BY nom ASC",
    array($aepId)
)->fetchAll();

$moisFacturation = Manager::prepare_query(
    "SELECT mf.id, mf.mois
     FROM mois_facturation mf
     INNER JOIN constante_reseau cr ON cr.id = mf.id_constante
     WHERE cr.id_aep = ?
     ORDER BY mf.mois DESC",
    array($aepId)
)->fetchAll();

// Compteurs du réseau avec stats
$compteurs = Manager::prepare_query(
    "SELECT c.*, cr.type_compteur, SUM(i.nouvel_index - i.ancien_index) AS conso_totale, COUNT(i.id) AS nb_releves
     FROM compteur c
     INNER JOIN compteur_reseau cr ON c.id = cr.id_compteur
     LEFT JOIN indexes i ON i.id_compteur = c.id
     WHERE cr.id_reseau = ?
     GROUP BY c.id
     ORDER BY c.id DESC",
    array($reseauId)
)->fetchAll();

// Compteur de compteurs
$nbCompteurs = count($compteurs);

// Données pour le graphique Chart.js: consommation réseau vs abonnés par mois
// Consommation des compteurs réseau par mois
// Construire requête avec filtres (réseau)
$queryReseau = 'SELECT mf.mois, SUM(i.nouvel_index - i.ancien_index) AS conso
     FROM indexes i
     INNER JOIN compteur_reseau cr ON cr.id_compteur = i.id_compteur
     INNER JOIN mois_facturation mf ON mf.id = i.id_mois_facturation
     WHERE cr.id_reseau = ? AND cr.type_compteur = \'distribution\'';
$paramsReseau = array($reseauId);
if (!empty($moisDebut)) {
    $queryReseau .= ' AND mf.mois >= ?';
    $paramsReseau[] = $moisDebut;
}
if (!empty($moisFin)) {
    $queryReseau .= ' AND mf.mois <= ?';
    $paramsReseau[] = $moisFin;
}
$queryReseau .= ' GROUP BY mf.mois ORDER BY mf.mois';
$rowsReseau = Manager::prepare_query($queryReseau, $paramsReseau)->fetchAll();

// Volumes des compteurs reseau par type (production, reservoir, distribution)
$queryTypes = 'SELECT mf.mois, cr.type_compteur, SUM(i.nouvel_index - i.ancien_index) AS conso
     FROM indexes i
     INNER JOIN compteur_reseau cr ON cr.id_compteur = i.id_compteur
     INNER JOIN mois_facturation mf ON mf.id = i.id_mois_facturation
     WHERE cr.id_reseau = ?';
$paramsTypes = array($reseauId);
if (!empty($moisDebut)) {
    $queryTypes .= ' AND mf.mois >= ?';
    $paramsTypes[] = $moisDebut;
}
if (!empty($moisFin)) {
    $queryTypes .= ' AND mf.mois <= ?';
    $paramsTypes[] = $moisFin;
}
$queryTypes .= ' GROUP BY mf.mois, cr.type_compteur ORDER BY mf.mois';
$rowsTypes = Manager::prepare_query($queryTypes, $paramsTypes)->fetchAll();

// Consommation des compteurs abonnés par mois (abonnés rattachés à ce réseau)
// Construire requête avec filtres (abonnés)
$queryAbonnes = 'SELECT mf.mois, SUM(i.nouvel_index - i.ancien_index) AS conso
     FROM indexes i
     INNER JOIN compteur_abone ca ON ca.id_compteur = i.id_compteur
     INNER JOIN abone a ON a.id = ca.id_abone
     INNER JOIN mois_facturation mf ON mf.id = i.id_mois_facturation
     WHERE a.id_reseau = ?';
$paramsAbonnes = array($reseauId);
if (!empty($moisDebut)) {
    $queryAbonnes .= ' AND mf.mois >= ?';
    $paramsAbonnes[] = $moisDebut;
}
if (!empty($moisFin)) {
    $queryAbonnes .= ' AND mf.mois <= ?';
    $paramsAbonnes[] = $moisFin;
}
$queryAbonnes .= ' GROUP BY mf.mois ORDER BY mf.mois';
$rowsAbonnes = Manager::prepare_query($queryAbonnes, $paramsAbonnes)->fetchAll();

// Consommation des compteurs des reseaux fils directs par mois
$queryReseauxFils = 'SELECT mf.mois, SUM(i.nouvel_index - i.ancien_index) AS conso
     FROM indexes i
     INNER JOIN compteur_reseau cr ON cr.id_compteur = i.id_compteur
     INNER JOIN reseau rf ON rf.id = cr.id_reseau
     INNER JOIN mois_facturation mf ON mf.id = i.id_mois_facturation
     WHERE rf.id_reseau_parent = ? AND rf.id_aep = ? AND cr.type_compteur = \'distribution\'';
$paramsReseauxFils = array($reseauId, $aepId);
if (!empty($moisDebut)) {
    $queryReseauxFils .= ' AND mf.mois >= ?';
    $paramsReseauxFils[] = $moisDebut;
}
if (!empty($moisFin)) {
    $queryReseauxFils .= ' AND mf.mois <= ?';
    $paramsReseauxFils[] = $moisFin;
}
$queryReseauxFils .= ' GROUP BY mf.mois ORDER BY mf.mois';
$rowsReseauxFils = Manager::prepare_query($queryReseauxFils, $paramsReseauxFils)->fetchAll();

// Fusion des mois et alignement des séries
$mapReseau = array();
foreach ($rowsReseau as $r) {
    $mapReseau[$r['mois']] = (float) $r['conso'];
}
$mapProd = array();
$mapReservoir = array();
$mapDistrib = array();
foreach ($rowsTypes as $r) {
    $moisType = $r['mois'];
    $typeCompteur = isset($r['type_compteur']) ? $r['type_compteur'] : 'distribution';
    $consoType = isset($r['conso']) ? (float) $r['conso'] : 0.0;
    if ($typeCompteur === 'production') {
        $mapProd[$moisType] = $consoType;
    } elseif ($typeCompteur === 'reservoir') {
        $mapReservoir[$moisType] = $consoType;
    } else {
        $mapDistrib[$moisType] = $consoType;
    }
}
$mapAbonnes = array();
foreach ($rowsAbonnes as $r) {
    $mapAbonnes[$r['mois']] = (float) $r['conso'];
}
foreach ($rowsReseauxFils as $r) {
    $m = $r['mois'];
    $mapAbonnes[$m] = (isset($mapAbonnes[$m]) ? $mapAbonnes[$m] : 0.0) + (float) $r['conso'];
}

$allMonths = array();
foreach ($mapReseau as $m => $v) {
    $allMonths[$m] = true;
}
foreach ($mapAbonnes as $m => $v) {
    $allMonths[$m] = true;
}
$labels = array_keys($allMonths);
sort($labels);

$dataReseau = array();
$dataAbonnes = array();
$dataRendement = array();
$dataTauxProduction = array();
$dataTauxReservoir = array();
$dataTauxDistribution = array();
$tableauRendement = array();
for ($i = 0; $i < count($labels); $i++) {
    $m = $labels[$i];
    $vr = isset($mapReseau[$m]) ? $mapReseau[$m] : 0.0;
    $va = isset($mapAbonnes[$m]) ? $mapAbonnes[$m] : 0.0;
    $volumeProduction = isset($mapProd[$m]) ? (float) $mapProd[$m] : 0.0;
    $volumeReservoir = isset($mapReservoir[$m]) ? (float) $mapReservoir[$m] : 0.0;
    $volumeDistribution = isset($mapDistrib[$m]) ? (float) $mapDistrib[$m] : (float) $vr;

    $tauxProduction = $volumeProduction > 0 ? round(($volumeReservoir / $volumeProduction) * 100, 2) : 0.0;
    $tauxReservoir = $volumeReservoir > 0 ? round(($volumeDistribution / $volumeReservoir) * 100, 2) : 0.0;
    $tauxDistribution = $volumeDistribution > 0 ? round(($va / $volumeDistribution) * 100, 2) : 0.0;

    $dataReseau[] = $vr;
    $dataAbonnes[] = $va;
    $dataRendement[] = $tauxDistribution;
    $dataTauxProduction[] = $tauxProduction;
    $dataTauxReservoir[] = $tauxReservoir;
    $dataTauxDistribution[] = $tauxDistribution;
    $tableauRendement[] = array(
        'mois' => $m,
        'volume_production' => $volumeProduction,
        'volume_reservoir' => $volumeReservoir,
        'volume_distribution' => $volumeDistribution,
        'volume_reseau' => $vr,
        'volume_abonnes_fils' => $va,
        'taux_production' => $tauxProduction,
        'taux_reservoir' => $tauxReservoir,
        'taux_distribution' => $tauxDistribution
    );
}
usort($tableauRendement, function ($a, $b) {
    return strcmp($b['mois'], $a['mois']);
});
?>

<style>
    .form-card {
        border: 1px solid #e9ecef;
        border-radius: 12px;
    }

    .table-responsive-dropdown {
        overflow-x: auto;
        overflow-y: visible;
    }

    .table-responsive-dropdown .dropdown-menu {
        z-index: 99999 !important;
        position: absolute;
        margin-top: 0.35rem !important;
    }

    .table-responsive-dropdown .dropdown {
        position: relative;
        z-index: 1000;
    }

    .table-rendement-recap thead .th-unit-m3,
    .table-rendement-recap thead .th-col-volume {
        background-color: #198754 !important;
        color: #fff !important;
        border-color: rgba(255, 255, 255, 0.2) !important;
    }

    .table-rendement-recap thead .th-unit-percent,
    .table-rendement-recap thead .th-col-percent {
        background-color: #0d6efd !important;
        color: #fff !important;
        border-color: rgba(255, 255, 255, 0.2) !important;
    }

    .table-rendement-recap tbody td.td-volume-col {
        background-color: rgba(25, 135, 84, 0.14) !important;
    }

    .table-rendement-recap tbody td.td-percent-col {
        background-color: rgba(13, 110, 253, 0.12) !important;
    }

    .table-rendement-recap.table-hover tbody tr:hover td.td-volume-col {
        background-color: rgba(25, 135, 84, 0.22) !important;
    }

    .table-rendement-recap.table-hover tbody tr:hover td.td-percent-col {
        background-color: rgba(13, 110, 253, 0.18) !important;
    }
</style>

<div class="container-fluid mt-5">
    <div class="d-flex align-items-center justify-content-between mb-3">
        <div>
            <h2 class="mb-1">Réseau: <?php echo htmlspecialchars($reseau['nom']); ?>
                <?php echo $reseau['abreviation'] ? '(' . htmlspecialchars($reseau['abreviation']) . ')' : ''; ?>
            </h2>
            <small class="text-muted">Créé le <?php echo htmlspecialchars($reseau['date_creation']); ?></small>
        </div>
        <div class="btn-group">
            <a href="?page=reseaux" class="btn btn-outline-secondary">Retour</a>
            <button type="button" class="btn btn-outline-primary" data-bs-toggle="modal"
                data-bs-target="#editReseauTopModal">
                <i class="bi bi-pencil"></i> Modifier
            </button>
            <?php
            $hasAbonnes = ((int) (isset($reseau['nb_abonnes']) ? $reseau['nb_abonnes'] : 0)) > 0;
            $hasChildren = ((int) (isset($reseau['nb_enfants']) ? $reseau['nb_enfants'] : 0)) > 0;
            $deleteDisabled = $hasAbonnes || $hasChildren;
            $deleteReason = '';
            if ($hasAbonnes && $hasChildren) {
                $deleteReason = 'Suppression impossible: ce reseau contient des abonnes et des reseaux fils.';
            } elseif ($hasAbonnes) {
                $deleteReason = 'Suppression impossible: ce reseau contient des abonnes.';
            } elseif ($hasChildren) {
                $deleteReason = 'Suppression impossible: ce reseau a des reseaux fils.';
            }
            ?>
            <span class="d-inline-block" <?php if ($deleteDisabled): ?> data-bs-toggle="tooltip" data-bs-placement="top"
                    data-bs-title="<?php echo htmlspecialchars($deleteReason); ?>" tabindex="0" <?php endif; ?>>
                <button type="button" class="btn btn-outline-danger" data-bs-toggle="modal"
                    data-bs-target="#deleteReseauTopModal" <?php echo $deleteDisabled ? 'disabled' : ''; ?>>
                    <i class="bi bi-trash"></i> Supprimer
                </button>
            </span>
        </div>
    </div>

    <?php
    renderReseauUpdateModalComponent($reseau, $reseaux, 'editReseauTopModal');
    renderReseauDeleteModalComponent(
        $reseau,
        'deleteReseauTopModal',
        isset($reseau['nb_abonnes']) ? (int) $reseau['nb_abonnes'] : 0,
        isset($reseau['nb_enfants']) ? (int) $reseau['nb_enfants'] : 0
    );
    ?>

    <form method="get" action="" class="row g-2 align-items-end mb-3">
        <input type="hidden" name="page" value="reseau_detail">
        <input type="hidden" name="id" value="<?php echo $reseauId; ?>">
        <div class="col-sm-3">
            <label class="form-label">Mois début</label>
            <input type="month" class="form-control" name="mois_debut"
                value="<?php echo htmlspecialchars($moisDebut); ?>">
        </div>
        <div class="col-sm-3">
            <label class="form-label">Mois fin</label>
            <input type="month" class="form-control" name="mois_fin" value="<?php echo htmlspecialchars($moisFin); ?>">
        </div>
        <div class="col-sm-3">
            <button type="submit" class="btn btn-primary"><i class="bi bi-funnel"></i> Filtrer</button>
            <a href="?page=reseau_detail&id=<?php echo $reseauId; ?>"
                class="btn btn-outline-secondary">Réinitialiser</a>
        </div>
    </form>

    <div class="row g-3">

        <div class="col-md-6">
            <div class="card h-100">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <div>
                        <strong>Compteurs Réseau</strong>
                        <span class="badge bg-primary ms-2"><?php echo $nbCompteurs; ?></span>
                    </div>
                    <button type="button" class="btn btn-sm btn-success" data-bs-toggle="modal"
                        data-bs-target="#addCompteurModal">
                        <i class="bi bi-plus-circle"></i> Ajouter
                    </button>
                </div>
                <div class="card-body">
                    <?php if ($nbCompteurs > 0): ?>
                        <div class="table-responsive-dropdown">
                            <table class="table_searching table table-striped table-hover">
                                <thead class="table-dark">
                                    <tr>
                                        <th>Numéro</th>
                                        <th>Dernier index</th>
                                        <!-- <th>Coordonnées</th> -->
                                        <th>Conso totale</th>
                                        <th>Relevés</th>
                                        <th>Type</th>
                                        <th>Description</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($compteurs as $c): ?>
                                        <tr>
                                            <td><strong><?php echo htmlspecialchars($c['numero_compteur']); ?></strong></td>
                                            <td><?php echo htmlspecialchars($c['derniers_index']); ?></td>
                                            <!--                                            <td>-->
                                            <!--                                                --><?php //echo isset($c['longitude']) ? htmlspecialchars($c['longitude']) : ''; ?><!--,-->
                                            <!--                                                --><?php //echo isset($c['latitude']) ? htmlspecialchars($c['latitude']) : ''; ?>
                                            <!--                                            </td>-->
                                            <td><span
                                                    class="badge bg-info"><?php echo number_format(isset($c['conso_totale']) ? $c['conso_totale'] : 0, 2, ',', ' '); ?>
                                                    m³</span></td>
                                            <td><span
                                                    class="badge bg-secondary"><?php echo (int) (isset($c['nb_releves']) ? $c['nb_releves'] : 0); ?></span>
                                            </td>
                                            <td>
                                                <?php
                                                $typeCompteur = isset($c['type_compteur']) ? $c['type_compteur'] : 'distribution';
                                                $typeLabel = $typeCompteur === 'production' ? 'Production' : ($typeCompteur === 'reservoir' ? 'Reservoir' : 'Distribution');
                                                $typeClass = $typeCompteur === 'production' ? 'bg-warning text-dark' : ($typeCompteur === 'reservoir' ? 'bg-info text-dark' : 'bg-primary');
                                                ?>
                                                <span class="badge <?php echo $typeClass; ?>"><?php echo $typeLabel; ?></span>
                                            </td>
                                            <td><?php echo htmlspecialchars(isset($c['description']) ? $c['description'] : ''); ?>
                                            </td>
                                            <td>
                                                <div class="dropdown">
                                                    <button class="btn btn-sm btn-outline-secondary compteur-actions-toggle"
                                                        type="button" data-bs-toggle="dropdown" data-bs-boundary="viewport"
                                                        aria-expanded="false" title="Actions">
                                                        <i class="bi bi-three-dots-vertical"></i>
                                                    </button>
                                                    <ul class="dropdown-menu dropdown-menu-end">
                                                        <li>
                                                            <button type="button" class="dropdown-item" data-bs-toggle="modal"
                                                                data-bs-target="#editCompteurModal_<?php echo $c['id']; ?>">
                                                                <i class="bi bi-pencil me-2"></i>Modifier
                                                            </button>
                                                        </li>
                                                        <li>
                                                            <button type="button" class="dropdown-item" data-bs-toggle="modal"
                                                                data-bs-target="#addIndexCompteurModal_<?php echo (int) $c['id']; ?>">
                                                                <i class="bi bi-plus-circle me-2"></i>Ajouter un index
                                                            </button>
                                                        </li>
                                                        <li>
                                                            <button type="button" class="dropdown-item btn-open-indexes-modal"
                                                                data-compteur-id="<?php echo (int) $c['id']; ?>"
                                                                data-compteur-numero="<?php echo htmlspecialchars($c['numero_compteur']); ?>">
                                                                <i class="bi bi-clock-history me-2"></i>Voir / Modifier indexes
                                                            </button>
                                                        </li>
                                                        <li>
                                                            <hr class="dropdown-divider">
                                                        </li>
                                                        <li>
                                                            <button type="button" class="dropdown-item text-danger"
                                                                data-bs-toggle="modal"
                                                                data-bs-target="#deleteCompteurModal_<?php echo (int) $c['id']; ?>">
                                                                <i class="bi bi-trash me-2"></i>Supprimer
                                                            </button>
                                                        </li>
                                                    </ul>
                                                </div>
                                            </td>
                                        </tr>

                                        <!-- Modal édition compteur -->
                                        <div class="modal fade" id="editCompteurModal_<?php echo (int) $c['id']; ?>"
                                            tabindex="-1">
                                            <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
                                                <div class="modal-content form-card">
                                                    <div class="modal-header bg-primary text-white">
                                                        <h5 class="modal-title"><i class="bi bi-pencil-square me-2"></i>Modifier
                                                            le
                                                            compteur</h5>
                                                        <button type="button" class="btn-close btn-close-white"
                                                            data-bs-dismiss="modal"></button>
                                                    </div>
                                                    <form method="post" action="traitement/compteur_t.php">
                                                        <input type="hidden" name="action" value="update_compteur">
                                                        <input type="hidden" name="compteur_id"
                                                            value="<?php echo (int) $c['id']; ?>">
                                                        <input type="hidden" name="reseau_id" value="<?php echo $reseauId; ?>">
                                                        <div class="modal-body">
                                                            <div class="row g-3">
                                                                <div class="col-md-6">
                                                                    <label class="form-label">Numéro *</label>
                                                                    <input type="text" class="form-control"
                                                                        name="numero_compteur"
                                                                        value="<?php echo htmlspecialchars($c['numero_compteur']); ?>"
                                                                        required>
                                                                </div>
                                                                <div class="col-md-6">
                                                                    <label class="form-label">Dernier index *</label>
                                                                    <input type="number" step="0.01" class="form-control"
                                                                        name="derniers_index"
                                                                        value="<?php echo htmlspecialchars($c['derniers_index']); ?>"
                                                                        required>
                                                                </div>
                                                                <div class="col-md-6">
                                                                    <label class="form-label">Type de compteur *</label>
                                                                    <select name="type_compteur" class="form-select" required>
                                                                        <option value="distribution" <?php echo (!isset($c['type_compteur']) || $c['type_compteur'] === 'distribution') ? 'selected' : ''; ?>>Distribution</option>
                                                                        <option value="production" <?php echo (isset($c['type_compteur']) && $c['type_compteur'] === 'production') ? 'selected' : ''; ?>>Production</option>
                                                                        <option value="reservoir" <?php echo (isset($c['type_compteur']) && $c['type_compteur'] === 'reservoir') ? 'selected' : ''; ?>>Reservoir</option>
                                                                    </select>
                                                                </div>
                                                                <div class="col-md-6">
                                                                    <label class="form-label">Longitude</label>
                                                                    <input type="number" step="0.000001" class="form-control"
                                                                        name="longitude"
                                                                        value="<?php echo htmlspecialchars(isset($c['longitude']) ? $c['longitude'] : ''); ?>">
                                                                </div>
                                                                <div class="col-md-6">
                                                                    <label class="form-label">Latitude</label>
                                                                    <input type="number" step="0.000001" class="form-control"
                                                                        name="latitude"
                                                                        value="<?php echo htmlspecialchars(isset($c['latitude']) ? $c['latitude'] : ''); ?>">
                                                                </div>
                                                                <div class="col-12">
                                                                    <label class="form-label">Description</label>
                                                                    <textarea class="form-control" rows="3"
                                                                        name="description"><?php echo htmlspecialchars(isset($c['description']) ? $c['description'] : ''); ?></textarea>
                                                                </div>
                                                            </div>
                                                        </div>
                                                        <div class="modal-footer">
                                                            <button type="button" class="btn btn-outline-secondary"
                                                                data-bs-dismiss="modal">Annuler</button>
                                                            <button type="submit" class="btn btn-primary">
                                                                <i class="bi bi-check2-circle me-1"></i>Enregistrer
                                                            </button>
                                                        </div>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Modal suppression compteur -->
                                        <div class="modal fade" id="deleteCompteurModal_<?php echo (int) $c['id']; ?>"
                                            tabindex="-1">
                                            <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
                                                <div class="modal-content form-card">
                                                    <div class="modal-header bg-danger text-white">
                                                        <h5 class="modal-title"><i
                                                                class="bi bi-exclamation-triangle me-2"></i>Supprimer le
                                                            compteur
                                                        </h5>
                                                        <button type="button" class="btn-close btn-close-white"
                                                            data-bs-dismiss="modal"></button>
                                                    </div>
                                                    <div class="modal-body">
                                                        <p class="mb-2">
                                                            Voulez-vous vraiment supprimer ce compteur ?
                                                        </p>
                                                        <div class="alert alert-warning mb-0">
                                                            <strong>Compteur :</strong>
                                                            <?php echo htmlspecialchars($c['numero_compteur']); ?>
                                                        </div>
                                                    </div>
                                                    <div class="modal-footer">
                                                        <button type="button" class="btn btn-outline-secondary"
                                                            data-bs-dismiss="modal">Annuler</button>
                                                        <form method="post" action="traitement/compteur_t.php" class="d-inline">
                                                            <input type="hidden" name="action" value="delete_compteur">
                                                            <input type="hidden" name="compteur_id"
                                                                value="<?php echo (int) $c['id']; ?>">
                                                            <input type="hidden" name="reseau_id"
                                                                value="<?php echo $reseauId; ?>">
                                                            <button type="submit" class="btn btn-danger">
                                                                <i class="bi bi-trash me-1"></i>Supprimer
                                                            </button>
                                                        </form>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Modal ajout index compteur -->
                                        <div class="modal fade" id="addIndexCompteurModal_<?php echo (int) $c['id']; ?>"
                                            tabindex="-1">
                                            <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
                                                <div class="modal-content form-card">
                                                    <div class="modal-header bg-success text-white">
                                                        <h5 class="modal-title"><i class="bi bi-plus-circle me-2"></i>Ajouter un
                                                            index
                                                        </h5>
                                                        <button type="button" class="btn-close btn-close-white"
                                                            data-bs-dismiss="modal"></button>
                                                    </div>
                                                    <form method="post" action="traitement/compteur_t.php">
                                                        <input type="hidden" name="action" value="add_index_compteur_reseau">
                                                        <input type="hidden" name="compteur_id"
                                                            value="<?php echo (int) $c['id']; ?>">
                                                        <input type="hidden" name="reseau_id" value="<?php echo $reseauId; ?>">
                                                        <div class="modal-body">
                                                            <div class="row g-3">
                                                                <div class="col-12">
                                                                    <label class="form-label">Mois de facturation *</label>
                                                                    <select class="form-select" name="id_mois_facturation"
                                                                        required>
                                                                        <option value="">Selectionner un mois...</option>
                                                                        <?php foreach ($moisFacturation as $m): ?>
                                                                            <option value="<?php echo (int) $m['id']; ?>">
                                                                                <?php echo htmlspecialchars(getLetterMonth($m['mois'])); ?>
                                                                            </option>
                                                                        <?php endforeach; ?>
                                                                    </select>
                                                                </div>
                                                                <div class="col-md-6">
                                                                    <label class="form-label">Ancien index *</label>
                                                                    <input type="number" step="0.01" min="0"
                                                                        class="form-control" name="ancien_index"
                                                                        value="<?php echo htmlspecialchars((string) $c['derniers_index']); ?>"
                                                                        required>
                                                                </div>
                                                                <div class="col-md-6">
                                                                    <label class="form-label">Nouvel index *</label>
                                                                    <input type="number" step="0.01" min="0"
                                                                        class="form-control" name="nouvel_index" required>
                                                                </div>
                                                                <div class="col-12">
                                                                    <div class="alert alert-info mb-0 py-2">
                                                                        Le nouvel index doit etre superieur ou egal a l'ancien
                                                                        index.
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                        <div class="modal-footer">
                                                            <button type="button" class="btn btn-outline-secondary"
                                                                data-bs-dismiss="modal">Annuler</button>
                                                            <button type="submit" class="btn btn-success">
                                                                <i class="bi bi-check2-circle me-1"></i>Ajouter l'index
                                                            </button>
                                                        </div>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php else: ?>
                            <div class="text-center text-muted py-4">
                                <i class="bi bi-info-circle fs-1"></i>
                                <p class="mt-2">Aucun compteur dans ce réseau</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-6 d-flex flex-column gap-3">
            <div class="card">
                <div class="card-header bg-light">Statistiques mensuelles (Rendement distribution)</div>
                <div class="card-body">
                    <div class="alert alert-info py-2 small">
                        Formule: Rendement = (Volume compteurs abonnes + Volume compteurs reseaux fils directs) / Volume
                        compteurs reseau.
                    </div>
                    <canvas id="reseauRendementChart"></canvas>
                </div>
            </div>
            <!-- <div class="card">
                <div class="card-header bg-light">Rendements (%)</div>
                <div class="card-body">
                    <div class="alert alert-info py-2 small mb-2">
                        Production = réservoir / production · Réservoir = distribution / réservoir · Distribution = abonnés + fils / distribution
                    </div>
                    <canvas id="reseauRendementsPercentChart"></canvas>
                </div>
            </div> -->
        </div>

        <div class="col-12">
            <div class="card shadow-sm">
                <div class="card-header bg-light">
                    <strong>Tableau récapitulatif du rendement de distribution</strong>
                </div>
                <div class="card-body">
                    <div class="alert alert-info py-2 small">
                        <strong>Formules utilisées :</strong><br>
                        Rendement production = Volume reservoir / Volume production<br>
                        Rendement reservoir = Volume distribution / Volume reservoir<br>
                        Rendement distribution = Volume abonnés + fils / Volume distribution
                    </div>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0 table-rendement-recap">
                            <thead>
                                <tr>
                                    <th rowspan="2" class="align-middle table-secondary">Mois</th>
                                    <th colspan="4" class="text-center th-unit-m3">Volume (m3)</th>
                                    <th colspan="3" class="text-center th-unit-percent">Rendement (%)</th>
                                </tr>
                                <tr>
                                    <th class="text-end th-col-volume">Production</th>
                                    <th class="text-end th-col-volume">Reservoir</th>
                                    <th class="text-end th-col-volume">Distribution</th>
                                    <th class="text-end th-col-volume">Abonnés + fils</th>
                                    <th class="text-end th-col-percent">Production</th>
                                    <th class="text-end th-col-percent">Reservoir</th>
                                    <th class="text-end th-col-percent">Distribution</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($tableauRendement)): ?>
                                    <?php foreach ($tableauRendement as $row): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars(getLetterMonth($row['mois'])); ?></td>
                                            <td class="text-end td-volume-col">
                                                <?php echo number_format((float) $row['volume_production'], 2, ',', ' '); ?>
                                            </td>
                                            <td class="text-end td-volume-col">
                                                <?php echo number_format((float) $row['volume_reservoir'], 2, ',', ' '); ?></td>
                                            <td class="text-end td-volume-col">
                                                <?php echo number_format((float) $row['volume_distribution'], 2, ',', ' '); ?>
                                            </td>
                                            <td class="text-end td-volume-col">
                                                <?php echo number_format((float) $row['volume_abonnes_fils'], 2, ',', ' '); ?>
                                            </td>
                                            <td class="text-end td-percent-col">
                                                <?php echo number_format((float) $row['taux_production'], 2, ',', ' '); ?>%</td>
                                            <td class="text-end td-percent-col">
                                                <?php echo number_format((float) $row['taux_reservoir'], 2, ',', ' '); ?>%</td>
                                            <td class="text-end td-percent-col fw-bold">
                                                <?php echo number_format((float) $row['taux_distribution'], 2, ',', ' '); ?>%
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="8" class="text-center text-muted">Aucune donnée de rendement
                                            disponible.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Modal ajout compteur -->
        <div class="modal fade" id="addCompteurModal" tabindex="-1">
            <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
                <div class="modal-content form-card">
                    <div class="modal-header bg-success text-white">
                        <h5 class="modal-title"><i class="bi bi-plus-circle me-2"></i>Ajouter un compteur</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <form method="post"
                        action="traitement/compteur_t.php?ajouter_compteur_reseau=true&id_reseau=<?php echo $reseauId; ?>">
                        <div class="modal-body">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label">Numéro *</label>
                                    <input type="text" class="form-control" name="numero_compteur" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Dernier index *</label>
                                    <input type="number" step="0.01" min="0" class="form-control" name="derniers_index"
                                        required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Type de compteur *</label>
                                    <select class="form-select" name="type_compteur" required>
                                        <option value="distribution" selected>Distribution</option>
                                        <option value="production">Production</option>
                                        <option value="reservoir">Reservoir</option>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Longitude</label>
                                    <input type="number" step="0.000001" class="form-control" name="longitude">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Latitude</label>
                                    <input type="number" step="0.000001" class="form-control" name="latitude">
                                </div>
                                <div class="col-12">
                                    <label class="form-label">Description</label>
                                    <textarea class="form-control" rows="3" name="description"></textarea>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-outline-secondary"
                                data-bs-dismiss="modal">Annuler</button>
                            <button type="submit" class="btn btn-success">
                                <i class="bi bi-check2-circle me-1"></i>Ajouter
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal asynchrone pour edition des anciens indexes -->
    <div class="modal fade" id="indexesCompteurAsyncModal" tabindex="-1">
        <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content form-card">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="indexesCompteurAsyncTitle">
                        <i class="bi bi-clock-history me-2"></i>Indexes du compteur
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="indexesCompteurAsyncBody">
                    <div class="text-center py-4 text-muted">Chargement...</div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Fermer</button>
                    <button type="button" class="btn btn-primary" id="btnSaveIndexesAsync">
                        <i class="bi bi-check2-circle me-1"></i>Enregistrer les modifications
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!--    <script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>-->
    <script>
        (function () {
            if (typeof bootstrap !== 'undefined') {
                var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
                tooltipTriggerList.forEach(function (tooltipTriggerEl) {
                    new bootstrap.Tooltip(tooltipTriggerEl);
                });

                var compteurDropdownToggles = [].slice.call(document.querySelectorAll('.compteur-actions-toggle'));
                compteurDropdownToggles.forEach(function (toggleEl) {
                    new bootstrap.Dropdown(toggleEl, {
                        boundary: document.body,
                        popperConfig: function (defaultConfig) {
                            defaultConfig.strategy = 'fixed';
                            defaultConfig.modifiers = defaultConfig.modifiers || [];
                            defaultConfig.modifiers.push({
                                name: 'offset',
                                options: {
                                    offset: [0, 14]
                                }
                            });
                            defaultConfig.modifiers.push({
                                name: 'preventOverflow',
                                options: {
                                    boundary: 'viewport',
                                    padding: 8
                                }
                            });
                            return defaultConfig;
                        }
                    });
                });
            }

            var asyncModalEl = document.getElementById('indexesCompteurAsyncModal');
            var asyncModalBody = document.getElementById('indexesCompteurAsyncBody');
            var asyncModalTitle = document.getElementById('indexesCompteurAsyncTitle');
            var btnSaveAsync = document.getElementById('btnSaveIndexesAsync');
            var asyncModal = null;
            if (asyncModalEl && typeof bootstrap !== 'undefined') {
                asyncModal = new bootstrap.Modal(asyncModalEl);
            }

            document.querySelectorAll('.btn-open-indexes-modal').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    var compteurId = btn.getAttribute('data-compteur-id');
                    var compteurNumero = btn.getAttribute('data-compteur-numero') || '';
                    if (!compteurId || !asyncModal) return;

                    asyncModalTitle.innerHTML = '<i class="bi bi-clock-history me-2"></i>Indexes du compteur ' + compteurNumero;
                    asyncModalBody.innerHTML = '<div class="text-center py-4 text-muted">Chargement...</div>';
                    asyncModal.show();

                    fetch('traitement/compteur_t.php?action=get_compteur_indexes_modal&compteur_id=' + encodeURIComponent(compteurId) + '&reseau_id=<?php echo (int) $reseauId; ?>')
                        .then(function (response) { return response.text(); })
                        .then(function (html) {
                            asyncModalBody.innerHTML = html;
                        })
                        .catch(function () {
                            asyncModalBody.innerHTML = '<div class="alert alert-danger mb-0">Impossible de charger les indexes.</div>';
                        });
                });
            });

            if (btnSaveAsync) {
                btnSaveAsync.addEventListener('click', function () {
                    var form = document.getElementById('form-edit-indexes-compteur');
                    if (!form) return;

                    var formData = new FormData(form);
                    btnSaveAsync.disabled = true;

                    fetch('traitement/compteur_t.php', {
                        method: 'POST',
                        body: formData
                    })
                        .then(function (response) { return response.json(); })
                        .then(function (json) {
                            if (json && json.success) {
                                asyncModalBody.insertAdjacentHTML('afterbegin', '<div class="alert alert-success">Indexes mis a jour avec succes.</div>');
                                setTimeout(function () { window.location.reload(); }, 700);
                            } else {
                                var msg = (json && json.message) ? json.message : 'Erreur lors de la sauvegarde.';
                                asyncModalBody.insertAdjacentHTML('afterbegin', '<div class="alert alert-danger">' + msg + '</div>');
                            }
                        })
                        .catch(function () {
                            asyncModalBody.insertAdjacentHTML('afterbegin', '<div class="alert alert-danger">Erreur reseau lors de la sauvegarde.</div>');
                        })
                        .finally(function () {
                            btnSaveAsync.disabled = false;
                        });
                });
            }

            var labels = <?php echo json_encode($labels); ?>;
            var dataReseau = <?php echo json_encode($dataReseau); ?>;
            var dataAbonnes = <?php echo json_encode($dataAbonnes); ?>;
            var dataRendement = <?php echo json_encode($dataRendement); ?>;
            var dataTauxProduction = <?php echo json_encode($dataTauxProduction); ?>;
            var dataTauxReservoir = <?php echo json_encode($dataTauxReservoir); ?>;
            var dataTauxDistribution = <?php echo json_encode($dataTauxDistribution); ?>;

            var el = document.getElementById('reseauRendementChart');
            if (el) {
                var ctx = el.getContext('2d');
                new Chart(ctx, {
                    type: 'bar',
                    data: {
                        labels: labels,
                        datasets: [
                            {
                                label: 'Conso Compteurs Réseau (Distribution) (m³)',
                                data: dataReseau,
                                backgroundColor: 'rgba(54, 162, 235, 0.5)',
                                borderColor: 'rgba(54, 162, 235, 1)',
                                borderWidth: 1
                            },
                            {
                                label: 'Conso Abonnés + Fils directs (Distribution) (m³)',
                                data: dataAbonnes,
                                backgroundColor: 'rgba(75, 192, 192, 0.5)',
                                borderColor: 'rgba(75, 192, 192, 1)',
                                borderWidth: 1
                            },
                            {
                                type: 'line',
                                label: 'Rendement (%)',
                                data: dataRendement,
                                yAxisID: 'y1',
                                borderColor: 'rgba(255, 99, 132, 1)',
                                backgroundColor: 'rgba(255, 99, 132, 0.2)',
                                tension: 0.2,
                                borderWidth: 2,
                                pointRadius: 3
                            }
                        ]
                    },
                    options: {
                        responsive: true,
                        interaction: { mode: 'index', intersect: false },
                        scales: {
                            y: {
                                beginAtZero: true,
                                title: { display: true, text: 'm³' }
                            },
                            y1: {
                                beginAtZero: true,
                                position: 'right',
                                title: { display: true, text: '%' },
                                grid: { drawOnChartArea: false },
                                suggestedMax: 120
                            }
                        },
                        plugins: {
                            tooltip: { enabled: true },
                            legend: { position: 'top' }
                        }
                    }
                });
            }

            var elPct = document.getElementById('reseauRendementsPercentChart');
            if (elPct) {
                var ctxPct = elPct.getContext('2d');
                new Chart(ctxPct, {
                    type: 'line',
                    data: {
                        labels: labels,
                        datasets: [
                            {
                                label: 'Rendement production (%)',
                                data: dataTauxProduction,
                                borderColor: 'rgba(25, 135, 84, 1)',
                                backgroundColor: 'rgba(25, 135, 84, 0.12)',
                                tension: 0.2,
                                borderWidth: 2,
                                pointRadius: 3,
                                fill: false
                            },
                            {
                                label: 'Rendement réservoir (%)',
                                data: dataTauxReservoir,
                                borderColor: 'rgba(13, 202, 240, 1)',
                                backgroundColor: 'rgba(13, 202, 240, 0.12)',
                                tension: 0.2,
                                borderWidth: 2,
                                pointRadius: 3,
                                fill: false
                            },
                            {
                                label: 'Rendement distribution (%)',
                                data: dataTauxDistribution,
                                borderColor: 'rgba(13, 110, 253, 1)',
                                backgroundColor: 'rgba(13, 110, 253, 0.12)',
                                tension: 0.2,
                                borderWidth: 2,
                                pointRadius: 3,
                                fill: false
                            }
                        ]
                    },
                    options: {
                        responsive: true,
                        interaction: { mode: 'index', intersect: false },
                        scales: {
                            y: {
                                beginAtZero: true,
                                title: { display: true, text: '%' },
                                suggestedMax: 120
                            }
                        },
                        plugins: {
                            tooltip: { enabled: true },
                            legend: { position: 'top' }
                        }
                    }
                });
            }
        })();
    </script>