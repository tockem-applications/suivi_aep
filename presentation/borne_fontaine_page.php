<?php
/**
 * Page de gestion des Bornes Fontaines (BF)
 * Similaire à abonne_page.php mais filtrée pour les BF uniquement
 */

@include_once("../donnees/manager.php");
@include_once("donnees/manager.php");
@include_once("../donnees/borne_fontaine.php");
@include_once("donnees/borne_fontaine.php");
@include_once("../donnees/bf_gerant.php");
@include_once("donnees/bf_gerant.php");

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$nbBF = 0;

// Récupérer l'AEP actuel
$aepId = isset($_SESSION['id_aep']) ? (int)$_SESSION['id_aep'] : 0;
if (!$aepId) {
    $message = '<div class="alert alert-danger">Aucun AEP sélectionné. Veuillez sélectionner un AEP.</div>';
    $bfs = array();
} else {
    // Récupérer les filtres
    $filtreReseau = isset($_GET['filtre_reseau']) ? (int)$_GET['filtre_reseau'] : 0;
    $searchNom = isset($_GET['search_nom']) ? trim($_GET['search_nom']) : '';
    $searchNumeroBorne = isset($_GET['search_numero_borne']) ? trim($_GET['search_numero_borne']) : '';
    // Tri
    $sortBy = isset($_GET['sort_by']) ? $_GET['sort_by'] : '';
    $sortDir = isset($_GET['sort_dir']) ? $_GET['sort_dir'] : 'asc';
    
    // Construire la requête avec filtres
    $whereConditions = array("r.id_aep = ?", "a.type_abone = 'BF'");
    $params = array($aepId);
    
    if ($filtreReseau > 0) {
        $whereConditions[] = "a.id_reseau = ?";
        $params[] = $filtreReseau;
    }
    
    if (!empty($searchNom)) {
        $whereConditions[] = "a.nom LIKE ?";
        $params[] = '%' . $searchNom . '%';
    }
    
    if (!empty($searchNumeroBorne)) {
        $whereConditions[] = "bf.numero_borne LIKE ?";
        $params[] = '%' . $searchNumeroBorne . '%';
    }
    
    $whereClause = implode(" AND ", $whereConditions);
    
    // Construire ORDER BY sécurisé
    $allowedSorts = array(
        'nom' => 'a.nom',
        'reseau' => 'nom_reseau',
        'numero_borne' => 'bf.numero_borne',
        'date_creation' => 'bf.date_creation'
    );
    $orderExprPrimary = 'bf.date_creation';
    if ($sortBy !== '' && isset($allowedSorts[$sortBy])) {
        $orderExprPrimary = $allowedSorts[$sortBy];
    }
    $orderDirSql = (strtolower($sortDir) === 'desc') ? 'DESC' : 'ASC';

    // Récupérer toutes les BF avec leurs informations
    $bfs = Manager::prepare_query(
        "SELECT bf.*, a.nom, a.numero_telephone, a.numero_compte_anticipation, 
                a.etat, a.rang, r.nom as nom_reseau, r.id as id_reseau,
                (SELECT COUNT(*) FROM bf_gerant bg WHERE bg.id_borne_fontaine = bf.id) as nb_gerants,
                (SELECT COUNT(*) FROM bf_gerant bg WHERE bg.id_borne_fontaine = bf.id AND bg.est_actif = 1) as nb_gerants_actifs,
                (SELECT nom_gerant FROM bf_gerant bg WHERE bg.id_borne_fontaine = bf.id AND bg.est_actif = 1 ORDER BY bg.date_debut DESC LIMIT 1) as gerant_actuel,
                (SELECT COUNT(DISTINCT vaf.id_mois) FROM vue_abones_facturation vaf WHERE vaf.id_abone = a.id) as nb_mois_factures,
                (SELECT SUM(vaf.montant_total) FROM vue_abones_facturation vaf WHERE vaf.id_abone = a.id) as total_facture,
                (SELECT SUM(vaf.montant_verse) FROM vue_abones_facturation vaf WHERE vaf.id_abone = a.id) as total_verse,
                (SELECT SUM(vaf.montant_restant) FROM vue_abones_facturation vaf WHERE vaf.id_abone = a.id) as total_restant
         FROM borne_fontaine bf
         INNER JOIN abone a ON bf.id_abone = a.id
         INNER JOIN reseau r ON a.id_reseau = r.id
         WHERE $whereClause 
         ORDER BY " . $orderExprPrimary . " " . $orderDirSql . ", a.nom ASC",
        $params
    )->fetchAll();
    $nbBF = count($bfs);
    $message = '';
}

// Gérer les messages de retour
if (isset($_GET['success'])) {
    switch ($_GET['success']) {
        case 'bf_added':
            $message = '<div class="alert alert-success">Borne Fontaine ajoutée avec succès.</div>';
            break;
        case 'bf_updated':
            $message = '<div class="alert alert-success">Borne Fontaine mise à jour avec succès.</div>';
            break;
        case 'gerant_added':
            $message = '<div class="alert alert-success">Gérant ajouté avec succès.</div>';
            break;
    }
} elseif (isset($_GET['error'])) {
    switch ($_GET['error']) {
        case 'no_aep':
            $message = '<div class="alert alert-danger">Aucun AEP sélectionné. Veuillez sélectionner un AEP.</div>';
            break;
        case 'add_failed':
        case 'update_failed':
            $msg = isset($_GET['message']) ? htmlspecialchars(urldecode($_GET['message'])) : 'Une erreur est survenue.';
            $message = '<div class="alert alert-danger">Erreur : ' . $msg . '</div>';
            break;
    }
}

// Récupérer les réseaux disponibles pour l'AEP
$reseaux = array();
if ($aepId) {
    $reseaux = Manager::prepare_query(
        "SELECT * FROM reseau WHERE id_aep = ? ORDER BY nom",
        array($aepId)
    )->fetchAll();
}
?>

<div class="container-fluid mt-5">
    <h2 class="mb-4"><i class="bi bi-droplet"></i> Gestion des Bornes Fontaines</h2>
    <?php echo $message; ?>
    <a href="dashboard.php" class="btn btn-secondary mb-3">Retour au tableau de bord</a>

    <!-- Section des Bornes Fontaines -->
    <div class="card">
        <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
            <h4 class="mb-0"><i class="bi bi-droplet"></i> Bornes Fontaines
            <span class="badge bg-secondary ms-2"><?php echo $nbBF; ?></span>
            </h4>
            <button type="button" class="btn btn-light" data-bs-toggle="modal" data-bs-target="#convertirAboneModal"
                <?php echo $aepId ? '' : 'disabled'; ?>>
                <i class="bi bi-plus-circle"></i> Convertir un abonné en BF
            </button>
        </div>
        
        <!-- Filtres -->
        <div class="card-body border-bottom">
            <form method="GET" action="" class="row g-3">
                <input type="hidden" name="page" value="borne_fontaine">
                
                <div class="col-md-3">
                    <label for="filtre_reseau" class="form-label">Réseau</label>
                    <select class="form-control" id="filtre_reseau" name="filtre_reseau">
                        <option value="0">Tous les réseaux</option>
                        <?php foreach ($reseaux as $reseau): ?>
                            <option value="<?php echo $reseau['id']; ?>" <?php echo $filtreReseau == $reseau['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($reseau['nom']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="col-md-3">
                    <label for="search_nom" class="form-label">Rechercher par nom</label>
                    <input type="text" class="form-control" id="search_nom" name="search_nom" 
                           value="<?php echo htmlspecialchars($searchNom); ?>" placeholder="Nom de la BF">
                </div>

                <div class="col-md-3">
                    <label for="search_numero_borne" class="form-label">Numéro de borne</label>
                    <input type="text" class="form-control" id="search_numero_borne" name="search_numero_borne" 
                           value="<?php echo htmlspecialchars($searchNumeroBorne); ?>" placeholder="Numéro de borne">
                </div>

                <div class="col-md-3">
                    <label for="sort_by" class="form-label">Trier par</label>
                    <select class="form-control" id="sort_by" name="sort_by">
                        <option value="">Par défaut</option>
                        <option value="nom" <?php echo $sortBy=='nom'?'selected':''; ?>>Nom</option>
                        <option value="reseau" <?php echo $sortBy=='reseau'?'selected':''; ?>>Réseau</option>
                        <option value="numero_borne" <?php echo $sortBy=='numero_borne'?'selected':''; ?>>Numéro de borne</option>
                        <option value="date_creation" <?php echo $sortBy=='date_creation'?'selected':''; ?>>Date de création</option>
                    </select>
                </div>
                
                <div class="col-md-12 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary me-2">
                        <i class="bi bi-funnel"></i> Filtrer
                    </button>
                    <a href="?page=borne_fontaine" class="btn btn-outline-secondary">
                        <i class="bi bi-x-circle"></i> Réinitialiser
                    </a>
                </div>
            </form>
        </div>
        
        <div class="card-body">
            <?php if (count($bfs) > 0): ?>
                <div class="table-responsive">
                    <table class="table_searching table table-striped table-hover">
                        <thead class="table-dark">
                            <tr>
                                <th>Numéro Borne</th>
                                <th>Nom</th>
                                <th>Gérant Actuel</th>
                                <th>Localisation</th>
                                <th>Réseau</th>
                                <th>État</th>
                                <th>Mois Facturés</th>
                                <th>Total Facturé</th>
                                <th>Total Versé</th>
                                <th>Reste à Payer</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($bfs as $bf): ?>
                                <tr>
                                    <td>
                                        <?php if ($bf['numero_borne']): ?>
                                            <span class="badge bg-info"><?php echo htmlspecialchars($bf['numero_borne']); ?></span>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($bf['nom']); ?></strong>
                                    </td>
                                    <td>
                                        <?php if ($bf['gerant_actuel']): ?>
                                            <span class="badge bg-success"><?php echo htmlspecialchars($bf['gerant_actuel']); ?></span>
                                        <?php else: ?>
                                            <span class="badge bg-warning">Aucun gérant</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($bf['localisation']): ?>
                                            <small><?php echo htmlspecialchars($bf['localisation']); ?></small>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge bg-secondary">
                                            <?php echo htmlspecialchars($bf['nom_reseau']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php 
                                        $etatClass = '';
                                        switch ($bf['etat']) {
                                            case 'actif': $etatClass = 'bg-success'; break;
                                            case 'inactif': $etatClass = 'bg-secondary'; break;
                                            case 'suspendu': $etatClass = 'bg-warning'; break;
                                            default: $etatClass = 'bg-info';
                                        }
                                        ?>
                                        <span class="badge <?php echo $etatClass; ?>">
                                            <?php echo ucfirst(htmlspecialchars($bf['etat'])); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge bg-primary"><?php echo $bf['nb_mois_factures']; ?> mois</span>
                                    </td>
                                    <td>
                                        <strong><?php echo number_format(isset($bf['total_facture']) ? $bf['total_facture'] : 0, 0, ',', ' '); ?> FCFA</strong>
                                    </td>
                                    <td>
                                        <span class="text-success">
                                            <strong><?php echo number_format(isset($bf['total_verse']) ? $bf['total_verse'] : 0, 0, ',', ' '); ?> FCFA</strong>
                                        </span>
                                    </td>
                                    <td>
                                        <?php 
                                        $reste = isset($bf['total_restant']) ? $bf['total_restant'] : 0;
                                        $classReste = $reste > 0 ? 'text-danger' : 'text-success';
                                        ?>
                                        <span class="<?php echo $classReste; ?>">
                                            <strong><?php echo number_format($reste, 0, ',', ' '); ?> FCFA</strong>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="btn-group" role="group">
                                            <a href="?page=info_bf&id=<?php echo $bf['id']; ?>" 
                                               class="btn btn-sm btn-outline-info" title="Voir les détails">
                                                <i class="bi bi-eye"></i>
                                            </a>
                                            <button type="button" class="btn btn-sm btn-outline-primary" 
                                                    onclick="gererGerants(<?php echo $bf['id']; ?>)" title="Gérer les gérants">
                                                <i class="bi bi-people"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="text-center text-muted py-4">
                    <i class="bi bi-droplet fs-1"></i>
                    <p class="mt-2">Aucune Borne Fontaine configurée pour cet AEP</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Modal pour convertir un abonné en BF -->
<div class="modal fade" id="convertirAboneModal" tabindex="-1" aria-labelledby="convertirAboneModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="convertirAboneModalLabel">Convertir un abonné en Borne Fontaine</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="traitement/borne_fontaine_t.php?action=convertir">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="id_abone" class="form-label">Sélectionner un abonné (BP uniquement)</label>
                        <select class="form-select" id="id_abone" name="id_abone" required>
                            <option value="">-- Choisir un abonné --</option>
                            <?php
                            if ($aepId) {
                                $abonnesBP = Manager::prepare_query(
                                    "SELECT a.id, a.nom, r.nom as reseau 
                                     FROM abone a 
                                     INNER JOIN reseau r ON a.id_reseau = r.id 
                                     WHERE r.id_aep = ? AND (a.type_abone IS NULL OR a.type_abone = '' OR a.type_abone = 'BP')
                                     ORDER BY a.nom",
                                    array($aepId)
                                )->fetchAll();
                                foreach ($abonnesBP as $abone) {
                                    echo "<option value='{$abone['id']}'>" . htmlspecialchars($abone['nom'] . ' - ' . $abone['reseau']) . "</option>";
                                }
                            }
                            ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="numero_borne" class="form-label">Numéro de la borne</label>
                        <input type="text" class="form-control" id="numero_borne" name="numero_borne" 
                               placeholder="Ex: BF-001">
                    </div>
                    <div class="mb-3">
                        <label for="localisation" class="form-label">Localisation</label>
                        <input type="text" class="form-control" id="localisation" name="localisation" 
                               placeholder="Ex: Quartier Centre, Rue principale">
                    </div>
                    <div class="mb-3">
                        <label for="description" class="form-label">Description</label>
                        <textarea class="form-control" id="description" name="description" rows="3" 
                                  placeholder="Description de la borne fontaine"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-primary">Convertir en BF</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal pour gérer les gérants -->
<div class="modal fade" id="gererGerantsModal" tabindex="-1" aria-labelledby="gererGerantsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="gererGerantsModalLabel">Gérer les gérants de la Borne Fontaine</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="gererGerantsContent">
                <!-- Le contenu sera chargé dynamiquement -->
            </div>
        </div>
    </div>
</div>

<script>
function gererGerants(idBF) {
    // Charger le contenu de gestion des gérants via AJAX
    // Pour l'instant, rediriger vers une page dédiée
    window.location.href = '?page=info_bf&id=' + idBF + '&tab=gerants';
}
</script>
