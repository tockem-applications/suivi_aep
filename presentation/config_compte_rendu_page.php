<?php
@include_once("../donnees/config_compte_rendu_financier.php");
@include_once("donnees/config_compte_rendu_financier.php");

// Gérer les actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && $_POST['action'] === 'update') {
        $id_aep = isset($_SESSION['id_aep']) ? $_SESSION['id_aep'] : null;
        
        // Mettre à jour ou créer les configurations
        $types = array('recouvrements', 'branchements', 'redevances');
        
        foreach ($types as $code_type) {
            $libelle = isset($_POST['libelle_' . $code_type]) ? $_POST['libelle_' . $code_type] : '';
            if (!empty($libelle)) {
                // Vérifier si une config existe déjà pour ce code_type (peu importe l'id_aep)
                // La contrainte UNIQUE est sur code_type seul, donc on ne peut avoir qu'une seule config par code_type
                @include_once("../donnees/connexion.php");
                @include_once("donnees/connexion.php");
                $bd = Connexion::connect();
                $query = "SELECT * FROM config_compte_rendu_financier WHERE code_type = ? LIMIT 1";
                $stmt = $bd->prepare($query);
                $stmt->execute(array($code_type));
                $existing = $stmt->fetch(PDO::FETCH_ASSOC);
                
                $code_budgetaire = isset($_POST['code_budgetaire_' . $code_type]) && !empty($_POST['code_budgetaire_' . $code_type]) ? $_POST['code_budgetaire_' . $code_type] : null;
                
                // Pour les redevances, on ne définit plus activite_associee (sera déterminé par base_calcul de chaque redevance)
                // Pour recouvrements et branchements, on garde activite_associee
                $activite_associee = null;
                if ($code_type !== 'redevances') {
                    $activite_associee = isset($_POST['activite_associee_' . $code_type]) ? $_POST['activite_associee_' . $code_type] : 'autre';
                    // Définir l'activité par défaut selon le code_type si non spécifiée
                    if ($activite_associee === 'autre') {
                        if ($code_type === 'branchements') {
                            $activite_associee = 'branchements';
                        } elseif ($code_type === 'recouvrements') {
                            $activite_associee = 'vente_eau';
                        }
                    }
                }
                
                if ($existing) {
                    // Mettre à jour la config existante
                    $config = new ConfigCompteRenduFinancier();
                    $config->id = $existing['id'];
                    $config->libelle = $libelle;
                    $config->code_type = $code_type;
                    $config->type_flux = $existing['type_flux'];
                    $config->code_budgetaire = $code_budgetaire;
                    // Ne pas modifier activite_associee pour les redevances
                    if ($code_type !== 'redevances') {
                        $config->activite_associee = $activite_associee;
                    }
                    $config->id_aep = $id_aep; // Mettre à jour l'id_aep si nécessaire
                    $config->update();
                } else {
                    // Créer une nouvelle config
                    $config = new ConfigCompteRenduFinancier();
                    $config->code_type = $code_type;
                    $config->libelle = $libelle;
                    $config->type_flux = ($code_type === 'redevances') ? 'charge' : 'recette';
                    $config->code_budgetaire = $code_budgetaire;
                    // Ne pas définir activite_associee pour les redevances
                    if ($code_type !== 'redevances') {
                        $config->activite_associee = $activite_associee;
                    }
                    $config->id_aep = $id_aep;
                    $config->ajouter();
                }
            }
        }
        
        header('Location: ?page=config_compte_rendu&success=1');
        exit;
    }
}

// Récupérer les configurations actuelles
$id_aep = isset($_SESSION['id_aep']) ? $_SESSION['id_aep'] : null;
$res = ConfigCompteRenduFinancier::getAllConfigs($id_aep);
$configs = $res->fetchAll(PDO::FETCH_ASSOC);

// Organiser par code_type
$configs_by_type = array();
foreach ($configs as $config) {
    $configs_by_type[$config['code_type']] = $config;
}

// Valeurs par défaut si pas de config spécifique
$libelle_recouvrements = isset($configs_by_type['recouvrements']['libelle']) ? $configs_by_type['recouvrements']['libelle'] : 'Recouvrements';
$libelle_branchements = isset($configs_by_type['branchements']['libelle']) ? $configs_by_type['branchements']['libelle'] : 'Branchements';
$libelle_redevances = isset($configs_by_type['redevances']['libelle']) ? $configs_by_type['redevances']['libelle'] : 'Redevances';

$code_budgetaire_recouvrements = isset($configs_by_type['recouvrements']['code_budgetaire']) ? $configs_by_type['recouvrements']['code_budgetaire'] : '';
$code_budgetaire_branchements = isset($configs_by_type['branchements']['code_budgetaire']) ? $configs_by_type['branchements']['code_budgetaire'] : '';
$code_budgetaire_redevances = isset($configs_by_type['redevances']['code_budgetaire']) ? $configs_by_type['redevances']['code_budgetaire'] : '';

$activite_recouvrements = isset($configs_by_type['recouvrements']['activite_associee']) ? $configs_by_type['recouvrements']['activite_associee'] : 'vente_eau';
$activite_branchements = isset($configs_by_type['branchements']['activite_associee']) ? $configs_by_type['branchements']['activite_associee'] : 'branchements';
$activite_redevances = isset($configs_by_type['redevances']['activite_associee']) ? $configs_by_type['redevances']['activite_associee'] : 'autre';
?>

<div class="container mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="text-primary fw-bold">Configuration du Compte Rendu Financier</h2>
        <div>
            <a href="?page=nouveau_compte_exploitation" class="btn btn-secondary me-2">
                <i class="fas fa-arrow-left me-2"></i>Retour au compte d'exploitation
            </a>
            <a href="?page=compte_rendu_financier" class="btn btn-outline-secondary">
                <i class="fas fa-chart-line me-2"></i>Compte rendu détaillé
            </a>
        </div>
    </div>

    <?php if (isset($_GET['success'])): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            Configuration mise à jour avec succès !
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <form method="post" action="">
        <input type="hidden" name="action" value="update">
        
        <!-- Sous-section Recouvrements -->
        <div class="card shadow-sm mb-4">
            <div class="card-header bg-success text-white">
                <h5 class="mb-0">
                    <i class="fas fa-arrow-up me-2"></i>Recouvrements (Recettes)
                </h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label for="libelle_recouvrements" class="form-label fw-bold">
                            Libellé <span class="text-danger">*</span>
                        </label>
                        <input type="text" class="form-control" id="libelle_recouvrements" 
                               name="libelle_recouvrements" 
                               value="<?php echo htmlspecialchars($libelle_recouvrements); ?>" 
                               required>
                        <small class="form-text text-muted">
                            Libellé à afficher dans les comptes rendus
                        </small>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label for="code_budgetaire_recouvrements" class="form-label fw-bold">
                            Code budgétaire
                        </label>
                        <input type="text" class="form-control" id="code_budgetaire_recouvrements" 
                               name="code_budgetaire_recouvrements" 
                               value="<?php echo htmlspecialchars($code_budgetaire_recouvrements); ?>" 
                               maxlength="10" placeholder="Ex: A001, BUD-001">
                        <small class="form-text text-muted">
                            Code pour le regroupement (max 10 caractères)
                        </small>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label for="activite_associee_recouvrements" class="form-label fw-bold">
                            Activité associée <span class="text-danger">*</span>
                        </label>
                        <select class="form-select" id="activite_associee_recouvrements" 
                                name="activite_associee_recouvrements" required>
                            <option value="branchements" <?php echo $activite_recouvrements === 'branchements' ? 'selected' : ''; ?>>Branchements</option>
                            <option value="vente_eau" <?php echo $activite_recouvrements === 'vente_eau' ? 'selected' : ''; ?>>Vente d'eau</option>
                            <option value="autre" <?php echo $activite_recouvrements === 'autre' ? 'selected' : ''; ?>>Autre</option>
                        </select>
                        <small class="form-text text-muted">
                            Pour la répartition dans les trésoreries
                        </small>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Sous-section Branchements -->
        <div class="card shadow-sm mb-4">
            <div class="card-header bg-success text-white">
                <h5 class="mb-0">
                    <i class="fas fa-arrow-up me-2"></i>Branchements (Recettes)
                </h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label for="libelle_branchements" class="form-label fw-bold">
                            Libellé <span class="text-danger">*</span>
                        </label>
                        <input type="text" class="form-control" id="libelle_branchements" 
                               name="libelle_branchements" 
                               value="<?php echo htmlspecialchars($libelle_branchements); ?>" 
                               required>
                        <small class="form-text text-muted">
                            Libellé à afficher dans les comptes rendus
                        </small>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label for="code_budgetaire_branchements" class="form-label fw-bold">
                            Code budgétaire
                        </label>
                        <input type="text" class="form-control" id="code_budgetaire_branchements" 
                               name="code_budgetaire_branchements" 
                               value="<?php echo htmlspecialchars($code_budgetaire_branchements); ?>" 
                               maxlength="10" placeholder="Ex: A001, BUD-001">
                        <small class="form-text text-muted">
                            Code pour le regroupement (max 10 caractères)
                        </small>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label for="activite_associee_branchements" class="form-label fw-bold">
                            Activité associée <span class="text-danger">*</span>
                        </label>
                        <select class="form-select" id="activite_associee_branchements" 
                                name="activite_associee_branchements" required>
                            <option value="branchements" <?php echo $activite_branchements === 'branchements' ? 'selected' : ''; ?>>Branchements</option>
                            <option value="vente_eau" <?php echo $activite_branchements === 'vente_eau' ? 'selected' : ''; ?>>Vente d'eau</option>
                            <option value="autre" <?php echo $activite_branchements === 'autre' ? 'selected' : ''; ?>>Autre</option>
                        </select>
                        <small class="form-text text-muted">
                            Pour la répartition dans les trésoreries
                        </small>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Sous-section Redevances -->
        <div class="card shadow-sm mb-4">
            <div class="card-header bg-danger text-white">
                <h5 class="mb-0">
                    <i class="fas fa-arrow-down me-2"></i>Redevances (Charges)
                </h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label for="libelle_redevances" class="form-label fw-bold">
                            Libellé <span class="text-danger">*</span>
                        </label>
                        <input type="text" class="form-control" id="libelle_redevances" 
                               name="libelle_redevances" 
                               value="<?php echo htmlspecialchars($libelle_redevances); ?>" 
                               required>
                        <small class="form-text text-muted">
                            Libellé à afficher dans les comptes rendus
                        </small>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label for="code_budgetaire_redevances" class="form-label fw-bold">
                            Code budgétaire
                        </label>
                        <input type="text" class="form-control" id="code_budgetaire_redevances" 
                               name="code_budgetaire_redevances" 
                               value="<?php echo htmlspecialchars($code_budgetaire_redevances); ?>" 
                               maxlength="10" placeholder="Ex: A001, BUD-001">
                        <small class="form-text text-muted">
                            Code pour le regroupement (max 10 caractères)
                        </small>
                    </div>
                    <div class="col-md-4 mb-3">
                        <div class="alert alert-info mb-0">
                            <small>
                                <i class="fas fa-info-circle me-1"></i>
                                <strong>Note :</strong> La répartition des redevances dans les trésoreries se fait automatiquement selon le champ "base de calcul" de chaque redevance (configuré dans la page de gestion des redevances).
                            </small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="d-flex justify-content-end mb-4">
            <button type="submit" class="btn btn-primary btn-lg">
                <i class="fas fa-save me-2"></i>Enregistrer toutes les configurations
            </button>
        </div>
    </form>
    
    <div class="alert alert-info mt-4">
        <i class="fas fa-info-circle me-2"></i>
        <strong>Note :</strong> Ces libellés seront utilisés dans le compte rendu financier pour afficher les montants par type de flux.
        Les recouvrements et branchements sont automatiquement considérés comme des recettes, 
        et les versements de redevances comme des charges.
    </div>
</div>
