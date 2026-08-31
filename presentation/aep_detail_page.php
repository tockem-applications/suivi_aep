<?php
@include_once("../donnees/manager.php");
@include_once("donnees/manager.php");

if (!isset($_SESSION['user_id'])) {
    header('Location: ?page=login');
    exit;
}

$aepId = isset($_GET['aep_id']) ? (int) $_GET['aep_id'] : 0;
if ($aepId <= 0) {
    echo '<div class="container my-5"><div class="alert alert-danger">AEP invalide.</div></div>';
    return;
}

$aep = array();
$reseaux = array();
$compteursAep = array();
$redevances = array();
$kpis = array(
    'nb_reseaux' => 0,
    'nb_abonnes' => 0,
    'nb_compteurs_reseau' => 0,
    'nb_compteurs_aep' => 0
);

try {
    $q = Manager::prepare_query("SELECT * FROM aep WHERE id = ?", array($aepId));
    $aep = $q ? $q->fetch(PDO::FETCH_ASSOC) : array();

    $q = Manager::prepare_query("SELECT * FROM reseau WHERE id_aep = ? ORDER BY nom", array($aepId));
    $reseaux = $q ? $q->fetchAll(PDO::FETCH_ASSOC) : array();

    $q = Manager::prepare_query(
        "SELECT ca.id_aep, ca.id_compteur, ca.id_position, c.numero_compteur, c.derniers_index
         FROM compteur_aep ca
         INNER JOIN compteur c ON c.id = ca.id_compteur
         WHERE ca.id_aep = ?
         ORDER BY c.numero_compteur",
        array($aepId)
    );
    $compteursAep = $q ? $q->fetchAll(PDO::FETCH_ASSOC) : array();

    $q = Manager::prepare_query("SELECT * FROM redevance WHERE id_aep = ? ORDER BY id", array($aepId));
    $redevances = $q ? $q->fetchAll(PDO::FETCH_ASSOC) : array();

    // KPIs
    $q = Manager::prepare_query("SELECT COUNT(*) c FROM reseau WHERE id_aep = ?", array($aepId));
    $kpis['nb_reseaux'] = ($q && ($r = $q->fetch())) ? (int) $r['c'] : 0;

    $q = Manager::prepare_query("SELECT COUNT(*) c FROM abone a INNER JOIN reseau r ON r.id = a.id_reseau WHERE r.id_aep = ?", array($aepId));
    $kpis['nb_abonnes'] = ($q && ($r = $q->fetch())) ? (int) $r['c'] : 0;

    $q = Manager::prepare_query("SELECT COUNT(*) c FROM compteur_reseau cr INNER JOIN reseau r ON r.id = cr.id_reseau WHERE r.id_aep = ?", array($aepId));
    $kpis['nb_compteurs_reseau'] = ($q && ($r = $q->fetch())) ? (int) $r['c'] : 0;

    $q = Manager::prepare_query("SELECT COUNT(*) c FROM compteur_aep WHERE id_aep = ?", array($aepId));
    $kpis['nb_compteurs_aep'] = ($q && ($r = $q->fetch())) ? (int) $r['c'] : 0;
} catch (Exception $e) {
}

?>

<div class="container my-4">
    <div class="d-flex align-items-center justify-content-between mb-3">
        <h2 class="mb-0">Détails AEP</h2>
        <div class="d-flex gap-2 flex-wrap">
            <a href="?page=aep" class="btn btn-secondary">Gestion AEP</a>
            <a href="?page=aep_dashboard&aep_id=<?php echo $aepId; ?>" class="btn btn-primary">Tableau de bord</a>
            <a href="?page=reseaux" class="btn btn-outline-primary">Réseaux</a>
            <a href="?page=abonne" class="btn btn-outline-primary">Abonnés</a>
            <a href="?page=redevance" class="btn btn-outline-primary">Redevances</a>
            <a href="?page=recouvrement" class="btn btn-outline-success">Recouvrement</a>
            <a href="?page=transaction" class="btn btn-outline-dark">Flux financiers</a>
        </div>
    </div>

    <?php if (!$aep): ?>
        <div class="alert alert-warning">AEP introuvable.</div>
    <?php else: ?>
        <div class="row g-3 mb-4">
            <div class="col-12 col-lg-6">
                <div class="card p-4 h-100">
                    <h3 class="h5 mb-3">Informations générales</h3>
                    <dl class="row mb-0">
                        <dt class="col-sm-4">Libellé</dt>
                        <dd class="col-sm-8"><?php echo htmlspecialchars($aep['libele']); ?></dd>
                        <dt class="col-sm-4">Date</dt>
                        <dd class="col-sm-8"><?php echo htmlspecialchars($aep['date']); ?></dd>
                        <dt class="col-sm-4">Description</dt>
                        <dd class="col-sm-8"><?php echo nl2br(htmlspecialchars($aep['description'])); ?></dd>
                    </dl>
                </div>
            </div>
            <div class="col-12 col-lg-6">
                <div class="card p-4 h-100">
                    <h3 class="h5 mb-3">Informations bancaires</h3>
                    <dl class="row mb-0">
                        <dt class="col-sm-4">Banque</dt>
                        <dd class="col-sm-8">
                            <?php echo htmlspecialchars(isset($aep['nom_banque']) ? $aep['nom_banque'] : ''); ?>
                        </dd>
                        <dt class="col-sm-4">N° compte</dt>
                        <dd class="col-sm-8">
                            <?php echo htmlspecialchars(isset($aep['numero_compte']) ? $aep['numero_compte'] : ''); ?>
                        </dd>
                        <dt class="col-sm-4">Modèle facture</dt>
                        <dd class="col-sm-8">
                            <?php echo htmlspecialchars(isset($aep['fichier_facture']) ? $aep['fichier_facture'] : ''); ?>
                        </dd>
                    </dl>
                </div>
            </div>
        </div>

        <div class="row g-3 mb-4">
            <div class="col-6 col-lg-3">
                <div class="card p-3 h-100 border-start border-4 border-primary">
                    <div class="small text-muted">Réseaux</div>
                    <div class="fs-4 fw-bold text-primary"><?php echo $kpis['nb_reseaux']; ?></div>
                </div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="card p-3 h-100 border-start border-4 border-info">
                    <div class="small text-muted">Abonnés</div>
                    <div class="fs-4 fw-bold text-info"><?php echo $kpis['nb_abonnes']; ?></div>
                </div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="card p-3 h-100 border-start border-4 border-warning">
                    <div class="small text-muted">Compteurs réseaux</div>
                    <div class="fs-4 fw-bold text-warning"><?php echo $kpis['nb_compteurs_reseau']; ?></div>
                </div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="card p-3 h-100 border-start border-4 border-success">
                    <div class="small text-muted">Compteurs AEP</div>
                    <div class="fs-4 fw-bold text-success"><?php echo $kpis['nb_compteurs_aep']; ?></div>
                </div>
            </div>
        </div>

        <div class="row g-3">
            <div class="col-12 col-lg-6">
                <div class="card p-4 h-100">
                    <h3 class="h5 mb-3">Réseaux</h3>
                    <?php if (empty($reseaux)): ?>
                        <div class="text-muted">Aucun réseau.</div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-sm table-bordered mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Nom</th>
                                        <th>Abonnés</th>
                                        <th>Compteurs</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($reseaux as $r): ?>
                                        <?php
                                        $rid = (int) $r['id'];
                                        $cAb = Manager::prepare_query("SELECT COUNT(*) c FROM abone WHERE id_reseau = ?", array($rid))->fetch();
                                        $cCr = Manager::prepare_query("SELECT COUNT(*) c FROM compteur_reseau WHERE id_reseau = ?", array($rid))->fetch();
                                        ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($r['nom']); ?></td>
                                            <td><?php echo isset($cAb['c']) ? (int) $cAb['c'] : 0; ?></td>
                                            <td><?php echo isset($cCr['c']) ? (int) $cCr['c'] : 0; ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="col-12 col-lg-6">
                <div class="card p-4 h-100">
                    <h3 class="h5 mb-3">Compteurs AEP</h3>
                    <?php if (empty($compteursAep)): ?>
                        <div class="text-muted">Aucun compteur AEP.</div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-sm table-bordered mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>N° Compteur</th>
                                        <th>Dernier index</th>
                                        <th>Position</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($compteursAep as $c): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars(isset($c['numero_compteur']) ? $c['numero_compteur'] : ''); ?>
                                            </td>
                                            <td><?php echo htmlspecialchars(isset($c['derniers_index']) ? $c['derniers_index'] : ''); ?>
                                            </td>
                                            <td><?php echo htmlspecialchars(isset($c['id_position']) ? $c['id_position'] : ''); ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="col-12">
                <div class="card p-4">
                    <h3 class="h5 mb-3">Redevances</h3>
                    <?php if (empty($redevances)): ?>
                        <div class="text-muted">Aucune redevance définie.</div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-sm table-bordered mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Libellé</th>
                                        <th>Pourcentage</th>
                                        <th>Type</th>
                                        <th>Mois de début</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($redevances as $rd): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($rd['libele']); ?></td>
                                            <td><?php echo htmlspecialchars($rd['pourcentage']); ?>%</td>
                                            <td><?php echo htmlspecialchars($rd['type']); ?></td>
                                            <td><?php echo htmlspecialchars(isset($rd['mois_debut']) ? $rd['mois_debut'] : ''); ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>