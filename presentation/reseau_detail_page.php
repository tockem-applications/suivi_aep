<?php

@include_once("../donnees/manager.php");
@include_once("donnees/manager.php");
@include_once("donnees/reseau.php");
@include_once("donnees/compteur.php");
@include_once("presentation/reseau_component.php");

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$aepId = isset($_SESSION['id_aep']) ? (int) $_SESSION['id_aep'] : 0;
$message = '';

$reseauId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if (!$aepId || !$reseauId) {
    header('Location: ?page=reseaux&error=invalid');
    exit;
}

// Filtres de mois (optionnels)
$moisDebut = isset($_GET['mois_debut']) ? $_GET['mois_debut'] : '';
$moisFin = isset($_GET['mois_fin']) ? $_GET['mois_fin'] : '';

// Charger réseau
$reseau = Manager::prepare_query("SELECT * FROM reseau WHERE id = ? AND id_aep = ?", array($reseauId, $aepId))->fetch();
if (!$reseau) {
    header('Location: ?page=reseaux&error=not_found');
    exit;
}

// Compteurs du réseau avec stats
$compteurs = Manager::prepare_query(
    "SELECT c.*, SUM(i.nouvel_index - i.ancien_index) AS conso_totale, COUNT(i.id) AS nb_releves
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
     WHERE cr.id_reseau = ?';
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

// Fusion des mois et alignement des séries
$mapReseau = array();
foreach ($rowsReseau as $r) {
    $mapReseau[$r['mois']] = (float) $r['conso'];
}
$mapAbonnes = array();
foreach ($rowsAbonnes as $r) {
    $mapAbonnes[$r['mois']] = (float) $r['conso'];
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
for ($i = 0; $i < count($labels); $i++) {
    $m = $labels[$i];
    $vr = isset($mapReseau[$m]) ? $mapReseau[$m] : 0.0;
    $va = isset($mapAbonnes[$m]) ? $mapAbonnes[$m] : 0.0;
    $dataReseau[] = $vr;
    $dataAbonnes[] = $va;
    $dataRendement[] = $vr > 0 ? round(($va / $vr) * 100, 2) : 0.0;
}
?>

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
            <form method="post" action="traitement/reseau_t.php"
                onsubmit="return confirm('Supprimer ce réseau et ses données associées ?');">
                <input type="hidden" name="action" value="delete_reseau">
                <input type="hidden" name="reseau_id" value="<?php echo $reseauId; ?>">
                <button type="submit" class="btn btn-outline-danger"><i class="bi bi-trash"></i> Supprimer</button>
            </form>
        </div>
    </div>

    <!-- Modal édition réseau (top) -->
    <div class="modal fade" id="editReseauTopModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Modifier le Réseau</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="post" action="traitement/reseau_t.php">
                    <input type="hidden" name="action" value="update_reseau">
                    <input type="hidden" name="reseau_id" value="<?php echo $reseauId; ?>">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Nom * </label>
                                <input type="text" class="form-control" name="nom"
                                    value="<?php echo htmlspecialchars($reseau['nom']); ?>" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Abréviation</label>
                            <input type="text" class="form-control" name="abreviation"
                                value="<?php echo htmlspecialchars(isset($reseau['abreviation']) ? $reseau['abreviation'] : ''); ?>">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Date de création *</label>
                            <input type="date" class="form-control" name="date_creation"
                                value="<?php echo htmlspecialchars($reseau['date_creation']); ?>" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Description</label>
                            <textarea class="form-control"
                                name="description_reseau"><?php echo htmlspecialchars(isset($reseau['description_reseau']) ? $reseau['description_reseau'] : ''); ?></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                        <button type="submit" class="btn btn-primary">Enregistrer</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

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
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead class="table-dark">
                                    <tr>
                                        <th>#</th>
                                        <th>Numéro</th>
                                        <th>Dernier index</th>
                                        <!-- <th>Coordonnées</th> -->
                                        <th>Conso totale</th>
                                        <th>Relevés</th>
                                        <th>Description</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($compteurs as $c): ?>
                                        <tr>
                                            <td><?php echo (int) $c['id']; ?></td>
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
                                            <td><?php echo htmlspecialchars(isset($c['description']) ? $c['description'] : ''); ?>
                                            </td>
                                            <td>
                                                <div class="btn-group" role="group">
                                                    <button type="button" class="btn btn-sm btn-outline-primary"
                                                        data-bs-toggle="modal"
                                                        data-bs-target="#editCompteurModal_<?php echo $c['id']; ?>">
                                                        <i class="bi bi-pencil"></i>
                                                    </button>
                                                    <form method="post" action="traitement/compteur_t.php"
                                                        onsubmit="return confirm('Supprimer ce compteur ?');" class="d-inline">
                                                        <input type="hidden" name="action" value="delete_compteur">
                                                        <input type="hidden" name="compteur_id" value="<?php echo $c['id']; ?>">
                                                        <input type="hidden" name="reseau_id" value="<?php echo $reseauId; ?>">
                                                        <button type="submit" class="btn btn-sm btn-outline-danger"><i
                                                                class="bi bi-trash"></i></button>
                                                    </form>
                                                </div>
                                            </td>
                                        </tr>

                                        <!-- Modal édition compteur -->
                                        <div class="modal fade" id="editCompteurModal_<?php echo (int) $c['id']; ?>"
                                            tabindex="-1">
                                            <div class="modal-dialog">
                                                <div class="modal-content">
                                                    <div class="modal-header">
                                                        <h5 class="modal-title">Modifier le compteur</h5>
                                                        <button type="button" class="btn-close"
                                                            data-bs-dismiss="modal"></button>
                                                    </div>
                                                    <form method="post" action="traitement/compteur_t.php">
                                                        <input type="hidden" name="action" value="update_compteur">
                                                        <input type="hidden" name="compteur_id"
                                                            value="<?php echo (int) $c['id']; ?>">
                                                        <input type="hidden" name="reseau_id" value="<?php echo $reseauId; ?>">
                                                        <div class="modal-body">
                                                            <div class="mb-3">
                                                                <label class="form-label">Numéro *</label>
                                                                <input type="text" class="form-control" name="numero_compteur"
                                                                    value="<?php echo htmlspecialchars($c['numero_compteur']); ?>"
                                                                    required>
                                                            </div>
                                                            <div class="mb-3">
                                                                <label class="form-label">Dernier index *</label>
                                                                <input type="number" step="0.01" class="form-control"
                                                                    name="derniers_index"
                                                                    value="<?php echo htmlspecialchars($c['derniers_index']); ?>"
                                                                    required>
                                                            </div>
                                                            <div class="row">
                                                                <div class="col">
                                                                    <label class="form-label">Longitude</label>
                                                                    <input type="number" step="0.000001" class="form-control"
                                                                        name="longitude"
                                                                        value="<?php echo htmlspecialchars(isset($c['longitude']) ? $c['longitude'] : ''); ?>">
                                                                </div>
                                                                <div class="col">
                                                                    <label class="form-label">Latitude</label>
                                                                    <input type="number" step="0.000001" class="form-control"
                                                                        name="latitude"
                                                                        value="<?php echo htmlspecialchars(isset($c['latitude']) ? $c['latitude'] : ''); ?>">
                                                                </div>
                                                            </div>
                                                            <div class="mb-3">
                                                                <label class="form-label">Description</label>
                                                                <textarea class="form-control"
                                                                    name="description"><?php echo htmlspecialchars(isset($c['description']) ? $c['description'] : ''); ?></textarea>
                                                            </div>
                                                        </div>
                                                        <div class="modal-footer">
                                                            <button type="button" class="btn btn-secondary"
                                                                data-bs-dismiss="modal">Annuler</button>
                                                            <button type="submit" class="btn btn-primary">Enregistrer</button>
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

        <div class="col-md-6">
            <div class="card h-100">
                <div class="card-header bg-light">Statistiques mensuelles (Rendement)</div>
                <div class="card-body">
                    <canvas id="reseauRendementChart"></canvas>
                </div>
            </div>
        </div>

        <!-- Modal ajout compteur -->
        <div class="modal fade" id="addCompteurModal" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Ajouter un compteur</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <form method="post"
                        action="traitement/compteur_t.php?ajouter_compteur_reseau=true&id_reseau=<?php echo $reseauId; ?>">
                        <div class="modal-body">
                            <div class="mb-3">
                                <label class="form-label">Numéro *</label>
                                <input type="text" class="form-control" name="numero_compteur" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Dernier index *</label>
                                <input type="number" step="0.01" class="form-control" name="derniers_index" required>
                            </div>
                            <div class="row">
                                <div class="col">
                                    <label class="form-label">Longitude</label>
                                    <input type="number" step="0.000001" class="form-control" name="longitude">
                                </div>
                                <div class="col">
                                    <label class="form-label">Latitude</label>
                                    <input type="number" step="0.000001" class="form-control" name="latitude">
                                </div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Description</label>
                                <textarea class="form-control" name="description"></textarea>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                            <button type="submit" class="btn btn-primary">Ajouter</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-md-6">
            <div class="card h-100">
                <div class="card-header bg-light">Statistiques mensuelles (Rendement)</div>
                <div class="card-body">
                    <canvas id="reseauRendementChart"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!--    <script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>-->
    <script>
        (function () {
            var labels = <?php echo json_encode($labels); ?>;
            var dataReseau = <?php echo json_encode($dataReseau); ?>;
            var dataAbonnes = <?php echo json_encode($dataAbonnes); ?>;
            var dataRendement = <?php echo json_encode($dataRendement); ?>;

            var el = document.getElementById('reseauRendementChart');
            if (!el) return;
            var ctx = el.getContext('2d');
            new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: labels,
                    datasets: [
                        {
                            label: 'Conso Compteurs Réseau (m³)',
                            data: dataReseau,
                            backgroundColor: 'rgba(54, 162, 235, 0.5)',
                            borderColor: 'rgba(54, 162, 235, 1)',
                            borderWidth: 1
                        },
                        {
                            label: 'Conso Compteurs Abonnés (m³)',
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
        })();
    </script>