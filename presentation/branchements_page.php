<?php
@include_once("../donnees/manager.php");
@include_once("donnees/manager.php");

if (!isset($_SESSION['user_id'])) {
    header('Location: ?page=login');
    exit;
}

// Filtres: période + critères
$start = isset($_GET['start']) ? trim($_GET['start']) : '';
$end = isset($_GET['end']) ? trim($_GET['end']) : '';
$filtre_quartier = isset($_GET['quartier']) ? trim($_GET['quartier']) : '';
$filtre_reseau = isset($_GET['reseau_id']) ? (int) $_GET['reseau_id'] : 0;
$filtre_statut = isset($_GET['statut']) ? trim($_GET['statut']) : '';
$filtre_montant = isset($_GET['montant']) && $_GET['montant'] !== '' ? (int) $_GET['montant'] : null;

// Construction du WHERE
$whereParts = array();
$params = array();
if ($start !== '' && $end !== '') {
    $whereParts[] = "ba.mois >= ? AND ba.mois <= ?";
    $params[] = $start;
    $params[] = $end;
}
if ($filtre_quartier !== '') {
    $whereParts[] = "(ba.quartier LIKE ?)";
    $params[] = '%' . $filtre_quartier . '%';
}
if ($filtre_statut !== '' && in_array(strtolower($filtre_statut), array('ok', 'en attente'))) {
    $whereParts[] = "LOWER(TRIM(IFNULL(ba.statut,''))) = ?";
    $params[] = strtolower($filtre_statut);
}
if (!is_null($filtre_montant)) {
    $whereParts[] = "IFNULL(ba.versement_fcfa,0) = ?";
    $params[] = $filtre_montant;
}
if ($filtre_reseau > 0) {
    $whereParts[] = "a.id_reseau = ?";
    $params[] = $filtre_reseau;
}
if (isset($_SESSION['id_aep']) && (int) $_SESSION['id_aep'] > 0) {
    $whereParts[] = "r.id_aep = ?";
    $params[] = (int) $_SESSION['id_aep'];
}
$where = count($whereParts) ? ("WHERE " . implode(" AND ", $whereParts)) : "";

// Récupération agrégats par mois (avec jointures pour filtres)
$sqlMonthly = "SELECT ba.mois, 
                      COUNT(*) as nb_branchements,
                      SUM(IFNULL(ba.versement_fcfa,0)) as total_verse,
                      AVG(IFNULL(ba.versement_fcfa,0)) as moyenne_verse,
                      SUM(CASE WHEN UPPER(TRIM(IFNULL(ba.statut,'')))='OK' THEN 1 ELSE 0 END) as nb_ok,
                      SUM(CASE WHEN LOWER(TRIM(IFNULL(ba.statut,'')))='en attente' THEN 1 ELSE 0 END) as nb_attente
               FROM branchement_abonne ba
               LEFT JOIN abone a ON a.id = ba.id_abone
               LEFT JOIN reseau r ON r.id = a.id_reseau
               $where
               GROUP BY ba.mois
               ORDER BY ba.mois";
$monthly = Manager::prepare_query($sqlMonthly, $params);
$rows = $monthly ? $monthly->fetchAll(PDO::FETCH_ASSOC) : array();

// Totaux globaux selon filtre
$total = 0;
$moyenne = 0;
$count = 0;
$ok = 0;
$attente = 0;
if (count($rows)) {
    foreach ($rows as $r) {
        $count += (int) $r['nb_branchements'];
        $total += (int) $r['total_verse'];
        $ok += (int) $r['nb_ok'];
        $attente += (int) $r['nb_attente'];
    }
    $moyenne = $count > 0 ? round($total / $count) : 0;
}

// Détails filtrés (pour export et affichage)
$sqlDetailsAll = "SELECT ba.*, a.nom, r.nom AS reseau_nom 
                  FROM branchement_abonne ba 
                  LEFT JOIN abone a ON a.id = ba.id_abone 
                  LEFT JOIN reseau r ON r.id = a.id_reseau 
                  $where 
                  ORDER BY ba.mois DESC";
$detailsAll = Manager::prepare_query($sqlDetailsAll, $params);
$detailRows = $detailsAll ? $detailsAll->fetchAll(PDO::FETCH_ASSOC) : array();

// Exports CSV
if (isset($_GET['export'])) {
    $exportType = $_GET['export'];
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="branchements_' . $exportType . '_' . date('Ymd_His') . '.csv"');
    $out = fopen('php://output', 'w');
    fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF)); // BOM UTF-8
    if ($exportType === 'summary') {
        fputcsv($out, array('Mois', 'Nombre', 'Total (FCFA)', 'Moyenne (FCFA)', 'OK', 'En attente'), ';');
        foreach ($rows as $r) {
            fputcsv($out, array(getLetterMonth($r['mois']), (int) $r['nb_branchements'], (int) $r['total_verse'], (int) $r['moyenne_verse'], (int) $r['nb_ok'], (int) $r['nb_attente']), ';');
        }
    } elseif ($exportType === 'details') {
        fputcsv($out, array('Mois', 'Abonne', 'Montant (FCFA)', 'Statut', 'Quartier', 'Code abonne', 'Telephone', 'Reseau'), ';');
        foreach ($detailRows as $d) {
            fputcsv($out, array(getLetterMonth($d['mois']), isset($d['nom']) ? $d['nom'] : '', (int) (isset($d['versement_fcfa']) ? $d['versement_fcfa'] : 0), isset($d['statut']) ? $d['statut'] : '', isset($d['quartier']) ? $d['quartier'] : '', isset($d['code_abonne']) ? $d['code_abonne'] : '', isset($d['telephone']) ? $d['telephone'] : '', isset($d['reseau_nom']) ? $d['reseau_nom'] : ''), ';');
        }
    }
    fclose($out);
    exit;
}

?>

<div class="container-fluid my-4">
    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
        <h2 class="mb-0">Branchements</h2>
        <a href="?page=aep" class="btn btn-secondary">Retour</a>
    </div>

    <form class="card p-3 mb-3" method="GET">
        <input type="hidden" name="page" value="branchements">
        <div class="row g-3 align-items-end">
            <div class="col-6 col-md-3">
                <label class="form-label">Début</label>
                <input type="month" name="start" class="form-control" value="<?php echo htmlspecialchars($start); ?>">
            </div>
            <div class="col-6 col-md-3">
                <label class="form-label">Fin</label>
                <input type="month" name="end" class="form-control" value="<?php echo htmlspecialchars($end); ?>">
            </div>
            <div class="col-12 col-md-3">
                <label class="form-label">Quartier</label>
                <input type="text" name="quartier" class="form-control" placeholder="Ex: MBIH1"
                    value="<?php echo htmlspecialchars($filtre_quartier); ?>">
            </div>
            <div class="col-12 col-md-3">
                <label class="form-label">Réseau</label>
                <select name="reseau_id" class="form-select">
                    <option value="0">Tous</option>
                    <?php
                    $paramsRes = array();
                    $sqlRes = "SELECT id, nom FROM reseau";
                    if (isset($_SESSION['id_aep']) && (int) $_SESSION['id_aep'] > 0) {
                        $sqlRes .= " WHERE id_aep = ?";
                        $paramsRes[] = (int) $_SESSION['id_aep'];
                    }
                    $sqlRes .= " ORDER BY nom";
                    $resRes = Manager::prepare_query($sqlRes, $paramsRes);
                    $reseaux = $resRes ? $resRes->fetchAll(PDO::FETCH_ASSOC) : array();
                    foreach ($reseaux as $re) {
                        echo '<option value="' . (int) $re['id'] . '" ' . ($filtre_reseau == (int) $re['id'] ? 'selected' : '') . '>' . htmlspecialchars($re['nom']) . '</option>';
                    }
                    ?>
                </select>
            </div>
            <div class="col-12 col-md-3">
                <label class="form-label">Montant (FCFA)</label>
                <input type="number" name="montant" class="form-control" min="0" step="500"
                    value="<?php echo htmlspecialchars(is_null($filtre_montant) ? '' : $filtre_montant); ?>"
                    placeholder="Ex: 70000">
            </div>
            <div class="col-12 col-md-3">
                <label class="form-label">Statut</label>
                <select name="statut" class="form-select">
                    <option value="">Tous</option>
                    <option value="OK" <?php echo strtolower($filtre_statut) == 'ok' ? 'selected' : ''; ?>>OK</option>
                    <option value="en attente" <?php echo strtolower($filtre_statut) == 'en attente' ? 'selected' : ''; ?>>En
                        attente</option>
                </select>
            </div>
            <div class="col-12 d-flex justify-content-end gap-2">
                <a href="?page=branchements" class="btn btn-outline-secondary">Réinitialiser</a>
                <button type="submit" class="btn btn-primary">Appliquer</button>
            </div>
        </div>
    </form>

    <div class="row g-3 mb-3">
        <div class="col-6 col-md-3">
            <div class="card p-3 border-start border-4 border-primary">
                <div class="small text-muted">Nombre de branchements</div>
                <div class="fs-3 fw-bold text-primary"><?php echo (int) $count; ?></div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card p-3 border-start border-4 border-success">
                <div class="small text-muted">Total versé</div>
                <div class="fs-3 fw-bold text-success"><?php echo number_format((int) $total, 0, ',', ' '); ?> FCFA
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card p-3 border-start border-4 border-info">
                <div class="small text-muted">Moyenne</div>
                <div class="fs-3 fw-bold text-info"><?php echo number_format((int) $moyenne, 0, ',', ' '); ?> FCFA</div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card p-3 border-start border-4 border-warning">
                <div class="small text-muted">OK / En attente</div>
                <div class="fs-3 fw-bold"><span class="text-success"><?php echo (int) $ok; ?></span> / <span
                        class="text-warning"><?php echo (int) $attente; ?></span></div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-12 col-lg-6">
            <div class="card p-3">
                <h5 class="mb-3">Évolution des montants par mois</h5>
                <div style="height:300px"><canvas id="br-evolution"></canvas></div>
            </div>
        </div>
        <div class="col-12 col-lg-6">
            <div class="card p-3">
                <h5 class="mb-3">Répartition OK vs En attente</h5>
                <div style="height:300px"><canvas id="br-ratio"></canvas></div>
            </div>
        </div>
        <div class="col-12">
            <div class="card p-3">
                <h5 class="mb-3">Nombre de branchements par tranche de montants (par mois)</h5>
                <div style="height:360px"><canvas id="br-buckets"></canvas></div>
            </div>
        </div>
        <div class="col-12">
            <div class="card p-3">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5 class="mb-0">Résumé par mois</h5>
                    <?php
                    create_csv_exportation_button(
                        $rows,
                        'resume_branchement.csv',
                        'exporter les details des montant de branchments'
                    );
                    ?>
                </div>
                <div class="table-responsive">
                    <table class="table table-bordered table-striped">
                        <thead class="table-light">
                            <tr>
                                <th>Mois</th>
                                <th class="text-end">Nombre</th>
                                <th class="text-end">Total (FCFA)</th>
                                <th class="text-end">Moyenne (FCFA)</th>
                                <th class="text-end">OK</th>
                                <th class="text-end">En attente</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($rows)) {
                                foreach ($rows as $r) { ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars(getLetterMonth($r['mois'])); ?></td>
                                        <td class="text-end"><?php echo (int) $r['nb_branchements']; ?></td>
                                        <td class="text-end"><?php echo number_format((int) $r['total_verse'], 0, ',', ' '); ?>
                                        </td>
                                        <td class="text-end">
                                            <?php echo number_format((int) $r['moyenne_verse'], 0, ',', ' '); ?>
                                        </td>
                                        <td class="text-end text-success"><?php echo (int) $r['nb_ok']; ?></td>
                                        <td class="text-end text-warning"><?php echo (int) $r['nb_attente']; ?></td>
                                    </tr>
                                <?php }
                            } else { ?>
                                <tr>
                                    <td colspan="6" class="text-center text-muted">Aucune donnée</td>
                                </tr>
                            <?php } ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="col-12">
            <div class="card p-3">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5 class="mb-0">Détails des branchements (période sélectionnée)</h5>
                    <?php
                    $sqlDetails = "SELECT ba.*, a.nom, r.nom AS reseau_nom 
                                        FROM branchement_abonne ba 
                                        LEFT JOIN abone a ON a.id = ba.id_abone 
                                        LEFT JOIN reseau r ON r.id = a.id_reseau 
                                        $where 
                                        ORDER BY ba.mois DESC";
                    $details = Manager::prepare_query($sqlDetails, $params);
                    $detailRows = $details ? $details->fetchAll(PDO::FETCH_ASSOC) : array();
                    create_csv_exportation_button(
                        $detailRows,
                        'donnees_branchement.csv',
                        'exporter les donnees de branchments au format csv'
                    );
                    ?>
                </div>
                <div class="table-responsive">
                    <table class="table table-bordered table-hover">
                        <thead class="table-light">
                            <tr>
                                <th>Mois</th>
                                <th>Abonné</th>
                                <th class="text-end">Montant (FCFA)</th>
                                <th>Statut</th>
                                <th>Quartier</th>
                                <th>Code abonné</th>
                                <th>N° Tél.</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php


                            if (count($detailRows)) {
                                foreach ($detailRows as $d) { ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars(getLetterMonth($d['mois'])); ?></td>
                                        <td><a href="?page=info_abone&id=<?php echo (int) (isset($d['id_abone']) ? $d['id_abone'] : 0); ?>"
                                                class="text-decoration-underline"><?php echo htmlspecialchars(isset($d['nom']) ? $d['nom'] : ''); ?></a>
                                        </td>
                                        <td class="text-end">
                                            <?php echo number_format((int) (isset($d['versement_fcfa']) ? $d['versement_fcfa'] : 0), 0, ',', ' '); ?>
                                        </td>
                                        <td
                                            class="<?php echo (isset($d['statut']) && strtoupper(trim($d['statut'])) == 'OK') ? 'text-success' : 'text-warning'; ?>">
                                            <?php echo htmlspecialchars(isset($d['statut']) ? $d['statut'] : ''); ?>
                                        </td>
                                        <td><?php echo htmlspecialchars(isset($d['quartier']) ? $d['quartier'] : ''); ?></td>
                                        <td><?php echo htmlspecialchars(isset($d['code_abonne']) ? $d['code_abonne'] : ''); ?>
                                        </td>
                                        <td><?php echo htmlspecialchars(isset($d['telephone']) ? $d['telephone'] : ''); ?></td>
                                    </tr>
                                <?php }
                            } else { ?>
                                <tr>
                                    <td colspan="7" class="text-center text-muted">Aucune donnée</td>
                                </tr>
                            <?php } ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    (function () {
        try {
            var rows = <?php echo json_encode($rows); ?>;
            var monthKeyToLabel = {};
            rows.forEach(function (r) { monthKeyToLabel[r.mois] = <?php /* inline php to call getLetterMonth */ ?>''; });
            // On reconstruit via PHP pour avoir les bons libellés
            var keyToLabel = <?php echo json_encode(array_reduce($rows, function ($acc, $r) {
                $acc[$r['mois']] = getLetterMonth($r['mois']);
                return $acc;
            }, array())); ?>;
            var labels = rows.map(function (r) { return keyToLabel[r.mois] || r.mois; });
            var totals = rows.map(function (r) { return parseInt(r.total_verse || 0, 10); });

            var ctx = document.getElementById('br-evolution');
            if (ctx && window.Chart) {
                new Chart(ctx.getContext('2d'), {
                    type: 'line',
                    data: { labels: labels, datasets: [{ label: 'Total versé', data: totals, borderColor: 'rgba(54,162,235,1)', backgroundColor: 'rgba(54,162,235,0.15)', tension: 0.2, fill: true }] },
                    options: { responsive: true, scales: { y: { beginAtZero: true } }, plugins: { legend: { position: 'bottom' } } }
                });
            }

            var ok = <?php echo (int) $ok; ?>;
            var attente = <?php echo (int) $attente; ?>;
            var ctx2 = document.getElementById('br-ratio');
            if (ctx2 && window.Chart) {
                new Chart(ctx2.getContext('2d'), {
                    type: 'doughnut',
                    data: { labels: ['OK', 'En attente'], datasets: [{ data: [ok, attente], backgroundColor: ['rgba(39,174,96,0.85)', 'rgba(241,196,15,0.85)'] }] },
                    options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'bottom' } } }
                });
            }

            // Graphique empilé par tranches de montants - buckets dynamiques d'après les données
            var monthKeys = rows.map(function (r) { return r.mois; });
            var detailRows = <?php echo json_encode(isset($detailRows) ? $detailRows : array()); ?>;

            function buildDynamicBuckets(values) {
                var palette = [
                    'rgba(155,155,155,0.8)', 'rgba(54,162,235,0.8)', 'rgba(75,192,192,0.8)',
                    'rgba(255,206,86,0.8)', 'rgba(255,159,64,0.8)', 'rgba(255,99,132,0.8)',
                    'rgba(153,102,255,0.8)', 'rgba(201,203,207,0.8)'
                ];
                if (!values.length) return [];
                var min = Math.min.apply(null, values), max = Math.max.apply(null, values);
                var n = values.length;
                var k = Math.max(3, Math.min(8, Math.ceil(Math.log2(n)) + 1)); // règle de Sturges bornée
                if (min === max) {
                    return [{ key: 'all', label: (min === 0 ? '0' : (min.toLocaleString() + ' FCFA')), color: palette[1], min: min, max: max }];
                }
                var buckets = [];
                var hasZero = values.some(function (v) { return v === 0; });
                if (hasZero) buckets.push({ key: 'zero', label: '0', color: palette[0], min: 0, max: 0 });
                var rangeMin = hasZero ? 1 : min;
                var k2 = k - (hasZero ? 1 : 0);
                var step = Math.ceil((max - rangeMin + 1) / k2);
                var colorIdx = 1;
                for (var i = 0; i < k2; i++) {
                    var bMin = rangeMin + i * step;
                    var bMax = (i === k2 - 1) ? max : (bMin + step - 1);
                    buckets.push({ key: bMin + '_' + bMax, label: (bMin.toLocaleString() + '-' + bMax.toLocaleString()), color: palette[(colorIdx++) % palette.length], min: bMin, max: bMax });
                }
                return buckets;
            }

            var values = detailRows.map(function (d) { return parseInt(d.versement_fcfa || 0, 10); });
            var bucketDefs = buildDynamicBuckets(values);
            var indexByMonth = {};
            monthKeys.forEach(function (m) { indexByMonth[m] = {}; bucketDefs.forEach(function (b) { indexByMonth[m][b.key] = 0; }); });
            detailRows.forEach(function (d) {
                var m = d.mois; var v = parseInt(d.versement_fcfa || 0, 10);
                if (!indexByMonth[m]) { indexByMonth[m] = {}; bucketDefs.forEach(function (b) { indexByMonth[m][b.key] = 0; }); if (monthKeys.indexOf(m) === -1) monthKeys.push(m); }
                for (var i = 0; i < bucketDefs.length; i++) { var b = bucketDefs[i]; if (v >= b.min && v <= b.max) { indexByMonth[m][b.key]++; break; } }
            });
            monthKeys.sort();

            // Remplacer le graphique empilé par un graphique multi-séries par montant exact
            var amounts = Array.from(new Set(detailRows.map(function (d) { return parseInt(d.versement_fcfa || 0, 10); }))).sort(function (a, b) { return a - b; });
            var palette2 = ['#5bc0de', '#5cb85c', '#f0ad4e', '#d9534f', '#428bca', '#9b59b6', '#16a085', '#7f8c8d', '#34495e', '#c0392b'];
            var byMonthAmount = {};
            monthKeys.forEach(function (m) { byMonthAmount[m] = {}; amounts.forEach(function (a) { byMonthAmount[m][a] = 0; }); });
            detailRows.forEach(function (d) { var m = d.mois; var a = parseInt(d.versement_fcfa || 0, 10); if (!(m in byMonthAmount)) { byMonthAmount[m] = {}; amounts.forEach(function (x) { byMonthAmount[m][x] = 0; }); monthKeys.push(m); } if (!(a in byMonthAmount[m])) { byMonthAmount[m][a] = 0; if (amounts.indexOf(a) === -1) { amounts.push(a); } } byMonthAmount[m][a]++; });
            monthKeys = Array.from(new Set(monthKeys)).sort();
            var monthLabels2 = monthKeys.map(function (m) { return (keyToLabel[m] || m); });
            var datasets = amounts.map(function (a, idx) { return { label: (a === 0 ? '0' : (a.toLocaleString() + ' FCFA')), backgroundColor: palette2[idx % palette2.length], data: monthKeys.map(function (m) { return byMonthAmount[m][a] || 0; }) }; });
            var ctx3 = document.getElementById('br-buckets');
            if (ctx3 && window.Chart) {
                new Chart(ctx3.getContext('2d'), {
                    type: 'bar',
                    data: { labels: monthLabels2, datasets: datasets },
                    options: { responsive: true, scales: { y: { beginAtZero: true } }, plugins: { legend: { position: 'bottom' } } }
                });
            }
        } catch (e) { console.error(e); }
    })();
</script>