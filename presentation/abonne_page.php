<?php

// Inclure les classes nécessaires
@include_once("../donnees/manager.php");
@include_once("donnees/manager.php");

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}
$nbAbonnes = 0;

// Récupérer l'AEP actuel
$aepId = isset($_SESSION['id_aep']) ? (int)$_SESSION['id_aep'] : 0;
if (!$aepId) {
    $message = '<div class="alert alert-danger">Aucun AEP sélectionné. Veuillez sélectionner un AEP.</div>';
    $abonnes = array();
} else {
    // Récupérer les filtres
    $filtreReseau = isset($_GET['filtre_reseau']) ? (int)$_GET['filtre_reseau'] : 0;
    $filtreImpayes = isset($_GET['filtre_impayes']) ? (int)$_GET['filtre_impayes'] : 0;
    $filtreVolumeMin = isset($_GET['filtre_volume_min']) ? (float)$_GET['filtre_volume_min'] : 0;
    $searchNom = isset($_GET['search_nom']) ? trim($_GET['search_nom']) : '';
    // Tri
    $sortBy = isset($_GET['sort_by']) ? $_GET['sort_by'] : '';
    $sortDir = isset($_GET['sort_dir']) ? $_GET['sort_dir'] : 'asc';
    
    // Construire la requête avec filtres
    $whereConditions = array("r.id_aep = ?");
    $params = array($aepId);
    
    if ($filtreReseau > 0) {
        $whereConditions[] = "a.id_reseau = ?";
        $params[] = $filtreReseau;
    }
    
    if ($filtreImpayes > 0) {
        $whereConditions[] = "(SELECT COUNT(CASE WHEN vaf2.montant_restant > 0 THEN 1 END) FROM vue_abones_facturation vaf2 WHERE vaf2.id_abone = a.id) >= ?";
        $params[] = $filtreImpayes;
    }
    
    if ($filtreVolumeMin > 0) {
        $whereConditions[] = "(SELECT SUM(vaf2.consommation) FROM vue_abones_facturation vaf2 WHERE vaf2.id_abone = a.id) >= ?";
        $params[] = $filtreVolumeMin;
    }
    
    if (!empty($searchNom)) {
        $whereConditions[] = "a.nom LIKE ?";
        $params[] = '%' . $searchNom . '%';
    }
    
    $whereClause = implode(" AND ", $whereConditions);
    
    // Construire ORDER BY sécurisé
    $allowedSorts = array(
        'rang' => 'COALESCE(a.rang, 999999)',
        'nom' => 'a.nom',
        'reseau' => 'nom_reseau',
        'restant' => 'total_restant',
        'nb_mois' => 'nb_mois_factures'
    );
    $orderExprPrimary = 'COALESCE(a.nom, 999999)';
    if ($sortBy !== '' && isset($allowedSorts[$sortBy])) {
        $orderExprPrimary = $allowedSorts[$sortBy];
    }
    $orderDirSql = (strtolower($sortDir) === 'desc') ? 'DESC' : 'ASC';

    // Récupérer tous les abonnés pour l'AEP avec statistiques et filtres
    $abonnes = Manager::prepare_query(
        "SELECT a.*, r.nom as nom_reseau, r.abreviation,
                (SELECT COUNT(DISTINCT vaf.id_mois) FROM vue_abones_facturation vaf WHERE vaf.id_abone = a.id) as nb_mois_factures,
                (SELECT SUM(vaf.montant_total) FROM vue_abones_facturation vaf WHERE vaf.id_abone = a.id) as total_facture,
                (SELECT SUM(vaf.montant_verse) FROM vue_abones_facturation vaf WHERE vaf.id_abone = a.id) as total_verse,
                (SELECT SUM(vaf.montant_restant) FROM vue_abones_facturation vaf WHERE vaf.id_abone = a.id) as total_restant,
                (SELECT COUNT(CASE WHEN vaf.montant_restant > 0 THEN 1 END) FROM vue_abones_facturation vaf WHERE vaf.id_abone = a.id) as nb_mois_impayes,
                (SELECT SUM(vaf.consommation) FROM vue_abones_facturation vaf WHERE vaf.id_abone = a.id) as total_consommation
         FROM abone a
         INNER JOIN reseau r ON a.id_reseau = r.id
         WHERE $whereClause 
         ORDER BY " . $orderExprPrimary . " " . $orderDirSql . ", a.nom ASC",
        $params
    )->fetchAll();
    $nbAbonnes = count($abonnes);
    $message = '';
}

// Gérer les messages de retour
if (isset($_GET['success'])) {
    switch ($_GET['success']) {
        case 'abonne_added':
            $message = '<div class="alert alert-success">Nouvel abonné ajouté avec succès.</div>';
            break;
        case 'abonne_updated':
            $message = '<div class="alert alert-success">Abonné mis à jour avec succès.</div>';
            break;
        case 'abonne_deleted':
            $message = '<div class="alert alert-success">Abonné supprimé avec succès.</div>';
            break;
    }
} elseif (isset($_GET['error'])) {
    switch ($_GET['error']) {
        case 'no_aep':
            $message = '<div class="alert alert-danger">Aucun AEP sélectionné. Veuillez sélectionner un AEP.</div>';
            break;
        case 'add_failed':
        case 'update_failed':
        case 'delete_failed':
            $msg = isset($_GET['message']) ? htmlspecialchars(urldecode($_GET['message'])) : 'Une erreur est survenue.';
            $message = '<div class="alert alert-danger">Erreur : ' . $msg . '</div>';
            break;
        case 'invalid_request':
            $message = '<div class="alert alert-danger">Requête invalide.</div>';
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
    <h2 class="mb-4">Gestion des Abonnés</h2>
    <?php echo $message; ?>
    <a href="dashboard.php" class="btn btn-secondary mb-3">Retour au tableau de bord</a>

    <!-- Section des Abonnés -->
    <div class="card">
        <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
            <h4 class="mb-0"><i class="bi bi-people"></i> Abonnés
            <span class="badge bg-secondary ms-2"><?php echo $nbAbonnes; ?></span>
            </h4>
            <button type="button" class="btn btn-light" data-bs-toggle="modal" data-bs-target="#addAbonneModal"
                <?php echo $aepId ? '' : 'disabled'; ?>>
                <i class="bi bi-plus-circle"></i> Nouvel Abonné
            </button>
        </div>
        
        <!-- Filtres -->
        <div class="card-body border-bottom">
            <form method="GET" action="" class="row g-3">
                <input type="hidden" name="page" value="abonne">
                
                <div class="col-md-2">
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
                
                <div class="col-md-2">
                    <label for="filtre_impayes" class="form-label">Min. Impayés</label>
                    <input type="number" class="form-control" id="filtre_impayes" name="filtre_impayes" 
                           value="<?php echo $filtreImpayes; ?>" min="0" placeholder="0">
                </div>
                
                <div class="col-md-2">
                    <label for="filtre_volume_min" class="form-label">Volume min. (m³)</label>
                    <input type="number" class="form-control" id="filtre_volume_min" name="filtre_volume_min" 
                           value="<?php echo $filtreVolumeMin; ?>" min="0" step="0.01" placeholder="0">
                </div>
                
                <div class="col-md-2">
                    <label for="search_nom" class="form-label">Rechercher par nom</label>
                    <input type="text" class="form-control" id="search_nom" name="search_nom" 
                           value="<?php echo htmlspecialchars($searchNom); ?>" placeholder="Nom de l'abonné">
                </div>

                <div class="col-md-2">
                    <label for="sort_by" class="form-label">Trier par</label>
                    <select class="form-control" id="sort_by" name="sort_by">
                        <option value="">Par défaut</option>
                        <option value="rang" <?php echo $sortBy=='rang'?'selected':''; ?>>Rang</option>
                        <option value="nom" <?php echo $sortBy=='nom'?'selected':''; ?>>Nom</option>
                        <option value="reseau" <?php echo $sortBy=='reseau'?'selected':''; ?>>Réseau</option>
                        <option value="restant" <?php echo $sortBy=='restant'?'selected':''; ?>>Reste à payer</option>
                        <option value="nb_mois" <?php echo $sortBy=='nb_mois'?'selected':''; ?>>Mois facturés</option>
                    </select>
                </div>

                <div class="col-md-2">
                    <label for="sort_dir" class="form-label">Ordre</label>
                    <select class="form-control" id="sort_dir" name="sort_dir">
                        <option value="asc" <?php echo strtolower($sortDir)=='asc'?'selected':''; ?>>Croissant</option>
                        <option value="desc" <?php echo strtolower($sortDir)=='desc'?'selected':''; ?>>Décroissant</option>
                    </select>
                </div>
                
                <div class="col-md-2 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary me-2">
                        <i class="bi bi-funnel"></i> Filtrer
                    </button>
                    <a href="?page=abonne" class="btn btn-outline-secondary">
                        <i class="bi bi-x-circle"></i> Réinitialiser
                    </a>
                </div>
            </form>
            
            <!-- Résumé des filtres actifs -->
            <?php 
            $filtresActifs = array();
            if ($filtreReseau > 0) {
                $reseauFiltre = array_filter($reseaux, function($r) use ($filtreReseau) { return $r['id'] == $filtreReseau; });
                if (!empty($reseauFiltre)) {
                    $reseauFiltre = reset($reseauFiltre);
                    $filtresActifs[] = 'Réseau: ' . htmlspecialchars($reseauFiltre['nom']);
                }
            }
            if ($filtreImpayes > 0) {
                $filtresActifs[] = 'Min. impayés: ' . $filtreImpayes;
            }
            if ($filtreVolumeMin > 0) {
                $filtresActifs[] = 'Volume min: ' . $filtreVolumeMin . ' m³';
            }
            if (!empty($searchNom)) {
                $filtresActifs[] = 'Nom: ' . htmlspecialchars($searchNom);
            }
            if (!empty($filtresActifs)) {
                ?>
                <div class="mt-3">
                    <small class="text-muted">
                        <i class="bi bi-info-circle"></i> Filtres actifs : 
                        <?php echo implode(', ', $filtresActifs); ?>
                        | <?php echo count($abonnes); ?> abonné(s) trouvé(s)
                    </small>
                </div>
                <?php
            }
            ?>
        </div>
        <div class="card-body">
            <?php if (count($abonnes) > 0): ?>
                <div class="table-responsive">
                    <table class="table table-striped table-hover">
                        <thead class="table-dark">
                            <tr>
                                <th>Rang</th>
                                <th>Nom</th>
                                <th>Téléphone</th>
                                <th>Réseau</th>
                                <th>État</th>
                                <th>Mois Facturés</th>
                                <th>Volume Total (m³)</th>
                                <th>Total Facturé</th>
                                <th>Total Versé</th>
                                <th>Reste à Payer</th>
                                <th>Mois Impayés</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($abonnes as $abonne): ?>
                                <tr>
                                    <td>
                                        <?php if ($abonne['rang']): ?>
                                            <span class="badge bg-dark"><?php echo $abonne['rang']; ?></span>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($abonne['nom']); ?></strong>
                                    </td>
                                    <td>
                                        <span class="badge bg-info"><?php echo htmlspecialchars($abonne['numero_telephone']); ?></span>
                                    </td>
                                    <td>
                                        <span class="badge bg-secondary">
                                            <?php echo htmlspecialchars($abonne['nom_reseau']); ?>
                                            <?php if ($abonne['abreviation']): ?>
                                                (<?php echo htmlspecialchars($abonne['abreviation']); ?>)
                                            <?php endif; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php 
                                        $etatClass = '';
                                        switch ($abonne['etat']) {
                                            case 'actif': $etatClass = 'bg-success'; break;
                                            case 'inactif': $etatClass = 'bg-secondary'; break;
                                            case 'suspendu': $etatClass = 'bg-warning'; break;
                                            default: $etatClass = 'bg-info';
                                        }
                                        ?>
                                        <span class="badge <?php echo $etatClass; ?>">
                                            <?php echo ucfirst(htmlspecialchars($abonne['etat'])); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge bg-primary"><?php echo $abonne['nb_mois_factures']; ?> mois</span>
                                    </td>
                                    <td>
                                        <strong><?php echo number_format(isset($abonne['total_consommation']) ? $abonne['total_consommation'] : 0, 2, ',', ' '); ?> m³</strong>
                                    </td>
                                    <td>
                                        <strong><?php echo number_format(isset($abonne['total_facture']) ? $abonne['total_facture'] : 0, 0, ',', ' '); ?> FCFA</strong>
                                    </td>
                                    <td>
                                        <span class="text-success">
                                            <strong><?php echo number_format(isset($abonne['total_verse']) ? $abonne['total_verse'] : 0, 0, ',', ' '); ?> FCFA</strong>
                                        </span>
                                    </td>
                                    <td>
                                        <?php 
                                        $reste = isset($abonne['total_restant']) ? $abonne['total_restant'] : 0;
                                        $classReste = $reste > 0 ? 'text-danger' : 'text-success';
                                        ?>
                                        <span class="<?php echo $classReste; ?>">
                                            <strong><?php echo number_format($reste, 0, ',', ' '); ?> FCFA</strong>
                                        </span>
                                    </td>
                                    <td>
                                        <?php 
                                        $nbImpayes = isset($abonne['nb_mois_impayes']) ? $abonne['nb_mois_impayes'] : 0;
                                        $classImpayes = $nbImpayes > 0 ? 'bg-warning' : 'bg-success';
                                        ?>
                                        <span class="badge <?php echo $classImpayes; ?>">
                                            <?php echo $nbImpayes; ?> impayés
                                        </span>
                                    </td>
                                    <td>
                                        <a href="?page=info_abone&id=<?php echo $abonne['id']; ?>" 
                                           class="btn btn-sm btn-outline-info" title="Voir les détails">
<!--                                            <i class="bi bi-eye"></i>-->
                                            Afficher
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="text-center text-muted py-4">
                    <i class="bi bi-info-circle fs-1"></i>
                    <p class="mt-2">Aucun abonné configuré pour cet AEP</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Modal pour ajouter un nouvel abonné -->
<div class="modal fade" id="addAbonneModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Nouvel Abonné</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="addAbonneForm" method="post" action="traitement/abone_t.php?ajout_abone=1">
                    <input type="hidden" name="action" value="add_abonne">
                    <input type="hidden" name="id_aep" value="<?php echo $aepId; ?>">
<!--                    if (isset($_POST['nom'], $_POST['numero_compteur'], $_POST['numero_telephone'], $_POST['id_reseau'], $_POST['derniers_index'], $_POST['etat'])) {-->
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="nom" class="form-label">Nom complet *</label>
                                <input type="text" class="form-control" id="nom" name="nom" 
                                       placeholder="Nom et prénom de l'abonné" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="numero_telephone" class="form-label">Numéro de téléphone *</label>
                                <input type="tel" class="form-control" id="numero_telephone" name="numero_telephone" 
                                       placeholder="Ex: 237 6XX XXX XXX" required>
                            </div>
                        </div>
                    </div>
                    
                                         <div class="row">
                         <div class="col-md-6">
                             <div class="mb-3">
                                 <label for="id_reseau" class="form-label">Réseau *</label>
                                 <select class="form-control" id="id_reseau" name="id_reseau" required>
                                     <option value="">Sélectionner un réseau...</option>
                                     <?php foreach ($reseaux as $reseau): ?>
                                         <option value="<?php echo $reseau['id']; ?>">
                                             <?php echo htmlspecialchars($reseau['nom']); ?>
                                             <?php if ($reseau['abreviation']): ?>
                                                 (<?php echo htmlspecialchars($reseau['abreviation']); ?>)
                                             <?php endif; ?>
                                         </option>
                                     <?php endforeach; ?>
                                 </select>
                             </div>
                         </div>
                         <div class="col-md-6">
                             <div class="mb-3">
                                 <label for="etat" class="form-label">État *</label>
                                 <select class="form-control" id="etat" name="etat" required>
                                     <option value="actif">Actif</option>
                                     <option value="inactif">Inactif</option>
                                     <option value="suspendu">Suspendu</option>
                                 </select>
                             </div>
                         </div>
                     </div>
                    
                                         <div class="row">
                         <div class="col-mb-6">
                             <div class="col-mb-3">
                                 <label for="rang" class="form-label">Rang</label>
                                 <input type="number" class="form-control" id="rang" name="rang" 
                                        placeholder="Rang de l'abonné" min="1">
                                 <small class="form-text text-muted">Optionnel - pour le classement</small>
                             </div>
                             <div class="col-mb-3">
                                 <label for="rang" class="form-label">Numero compteur</label>
                                 <input type="text" class="form-control" id="rang" name="numero_compteur"
                                        placeholder="Numero compteur" >
                                 <small class="form-text text-muted">Ajouter un numero compteur</small>
                             </div>
                             <div class="col-mb-3">
                                 <label for="rang" class="form-label">Index pointé</label>
                                 <input type="number" class="form-control" id="derniers_index" name="derniers_index"
                                        placeholder="index" step="0.01">
                                 <small class="form-text text-muted">le derniers index est requis</small>
                             </div>
                         </div>
                     </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                <button type="submit" form="addAbonneForm" class="btn btn-primary">Créer l'abonné</button>
            </div>
        </div>
    </div>
</div>

<script>
    // Les filtres se mettent à jour automatiquement via le formulaire GET
    // Plus besoin des fonctions edit et delete
</script>
