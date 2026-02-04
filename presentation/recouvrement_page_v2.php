<?php
/**
 * Page de Recouvrement V2 - Version refactorisée avec code propre
 */

// Inclure les classes nécessaires
@include_once("../donnees/manager.php");
@include_once("donnees/manager.php");
@include_once("../donnees/facture.php");
@include_once("../donnees/mois_facturation.php");
@include_once("../donnees/constante_reseau.php");
@include_once("../traitement/facture_t.php");
@include_once("../traitement/mois_facturation_t.php");
@include_once("../traitement/abone_t.php");
@include_once("../traitement/reseau_t.php");
@include_once("donnees/facture.php");
@include_once("donnees/mois_facturation.php");
@include_once("donnees/constante_reseau.php");
@include_once("traitement/facture_t.php");
@include_once("traitement/mois_facturation_t.php");
@include_once("traitement/abone_t.php");
@include_once("traitement/reseau_t.php");

/**
 * Fonction principale pour afficher la page de recouvrement V2
 */
function display_recouvrement_v2()
{
    // Vérifier l'authentification
    if (!isset($_SESSION['user_id'])) {
        ?>
        <div class="container-fluid mt-4">
            <div class="alert alert-warning">
                <i class="bi bi-exclamation-triangle"></i> Vous devez être connecté pour accéder à cette page.
            </div>
            <a href="?page=login" class="btn btn-primary">Se connecter</a>
        </div>
        <?php
        return;
    }

    // Récupérer l'AEP actuel
    $aepId = isset($_SESSION['id_aep']) ? (int) $_SESSION['id_aep'] : 0;
    if (!$aepId) {
        ?>
        <div class="container-fluid mt-4">
            <div class="alert alert-danger">
                <i class="bi bi-exclamation-triangle"></i> Aucun AEP sélectionné. Veuillez sélectionner un AEP.
            </div>
            <a href="?page=aep_dashboard" class="btn btn-primary">Retour au tableau de bord</a>
        </div>
        <?php
        return;
    }

    // Récupérer les paramètres de filtrage
    $idMois = isset($_GET['id_selected_month']) ? (int) $_GET['id_selected_month'] : 0;
    $idReseau = isset($_GET['id_reseau_filter']) ? (int) $_GET['id_reseau_filter'] : 0;
    $idTarifFilter = isset($_GET['id_tarif_filter']) ? $_GET['id_tarif_filter'] : '';
    $selectOption = isset($_GET['select_option']) ? $_GET['select_option'] : 'vide';
    $searchTerm = isset($_GET['search']) ? trim($_GET['search']) : '';

    // Si aucun mois n'est sélectionné, utiliser le mois actif
    if ($idMois == 0) {
        $idMois = MoisFacturation::getIdMoisFacturationActive($aepId);
    }

    // Récupérer les informations du mois pour obtenir le tarif de base associé
    $moisData = MoisFacturation::getOneById($idMois)->fetch();
    $idConstanteReseau = isset($moisData['id_constante']) ? (int) $moisData['id_constante'] : 0;

    // Récupérer le tarif de base et les tarifs différenciés associés au mois
    $tarifBase = null;
    $tarifsDifferencies = array();
    $tarifsDisponibles = array();

    if ($idConstanteReseau > 0) {
        $tarifBase = Manager::prepare_query(
            "SELECT * FROM constante_reseau WHERE id = ?",
            array($idConstanteReseau)
        )->fetch();

        if ($tarifBase) {
            $tarifsDisponibles[] = array(
                'id' => 'base_' . $idConstanteReseau,
                'id_constante' => $idConstanteReseau,
                'id_tarif_differencie' => null,
                'prix_metre_cube_eau' => $tarifBase['prix_metre_cube_eau'],
                'description' => !empty($tarifBase['description']) ? $tarifBase['description'] : 'Tarif de base',
                'type' => 'base',
                'min_consommation' => null,
                'max_consommation' => null
            );
        }

        @include_once("../donnees/tarif_differencie.php");
        @include_once("donnees/tarif_differencie.php");
        if (class_exists('TarifDifferencie')) {
            $tarifsDifferencies = TarifDifferencie::getTarifsByConstante($idConstanteReseau);

            foreach ($tarifsDifferencies as $td) {
                $min = number_format($td['min_consommation'], 0, ',', ' ');
                $max = $td['max_consommation'] !== null ? number_format($td['max_consommation'], 0, ',', ' ') : '∞';
                $desc = !empty($td['description']) ? $td['description'] : '[' . $min . ' - ' . $max . '[';
                $tarifsDisponibles[] = array(
                    'id' => 'diff_' . $td['id'],
                    'id_constante' => $idConstanteReseau,
                    'id_tarif_differencie' => $td['id'],
                    'prix_metre_cube_eau' => $td['prix_metre_cube_eau'],
                    'description' => $desc,
                    'type' => 'differencie',
                    'min_consommation' => $td['min_consommation'],
                    'max_consommation' => $td['max_consommation']
                );
            }
        }
    }

    // Récupérer les factures
    $idMoisActif = MoisFacturation::getIdMoisFacturationActive($aepId);
    $editable = ($idMois == $idMoisActif);

    $facturesQuery = Facture::getMonthFacture2($idMois, $aepId, $idReseau);
    if ($facturesQuery) {
        $allFactures = $facturesQuery->fetchAll(PDO::FETCH_ASSOC);

        foreach ($allFactures as &$f) {
            if (isset($f['id_indexes']) || isset($f['id'])) {
                $idIndexes = isset($f['id_indexes']) ? $f['id_indexes'] : $f['id'];
                $vitData = Manager::prepare_query(
                    "SELECT id_tarif_differencie, type_tarif FROM vue_indexes_tarifs_resolved WHERE id_indexes = ?",
                    array($idIndexes)
                )->fetch();
                if ($vitData) {
                    $f['id_tarif_differencie'] = $vitData['id_tarif_differencie'];
                    $f['type_tarif'] = $vitData['type_tarif'];
                } else {
                    $f['id_tarif_differencie'] = null;
                    $f['type_tarif'] = 'base';
                }
            }
        }
        unset($f);

        if (!empty($idTarifFilter)) {
            $factures = array();
            foreach ($allFactures as $f) {
                $match = false;

                if (strpos($idTarifFilter, 'base_') === 0) {
                    $idConstanteFromFilter = (int) str_replace('base_', '', $idTarifFilter);
                    if (
                        isset($f['id_constante_reseau']) && $f['id_constante_reseau'] == $idConstanteFromFilter &&
                        (!isset($f['id_tarif_differencie']) || $f['id_tarif_differencie'] === null)
                    ) {
                        $match = true;
                    }
                } elseif (strpos($idTarifFilter, 'diff_') === 0) {
                    $idTarifDiffFromFilter = (int) str_replace('diff_', '', $idTarifFilter);
                    if (isset($f['id_tarif_differencie']) && $f['id_tarif_differencie'] == $idTarifDiffFromFilter) {
                        $match = true;
                    }
                }

                if ($match) {
                    $factures[] = $f;
                }
            }
        } else {
            $factures = $allFactures;
        }
    } else {
        $factures = array();
    }

    if (!empty($factures) && function_exists('create_csv_exportation_button')) {
        $moisData = MoisFacturation::getOneById($idMois)->fetch();
        if ($moisData && function_exists('getLetterMonth')) {
            $moisLettre = getLetterMonth(isset($moisData['mois']) ? $moisData['mois'] : '');
            $libeleAep = isset($_SESSION["libele_aep"]) ? $_SESSION["libele_aep"] : 'export';
            create_csv_exportation_button(
                $factures,
                'facturation-' . $libeleAep . '_' . $moisLettre . '.csv',
                "Vous allez exporter les données de facturation de " . $moisLettre . ' au format csv'
            );
        }
    }

    $filteredFactures = array();
    foreach ($factures as $data) {
        $montantTotal = isset($data['total_cumule']) ? $data['total_cumule'] : 0;
        $montantRestant = isset($data['restant_cumule']) ? $data['restant_cumule'] : 0;

        $shouldInclude = true;
        switch ($selectOption) {
            case 'insolvable':
                // Insolvables : reste positif ET égal au total (aucun paiement effectué)
                $shouldInclude = ($montantRestant > 0 && $montantRestant == $montantTotal);
                break;
            case 'en_regle':
                $shouldInclude = ($montantRestant <= 0);
                break;
            case 'pas_en_regle':
                $shouldInclude = ($montantRestant > 0);
                break;
            case 'solvable':
                $shouldInclude = ($montantRestant == 0 && $montantTotal > 0);
                break;
            case 'anticipation':
                $shouldInclude = ($montantRestant < 0);
                break;
            case 'paiement_partiel':
                $shouldInclude = ($montantRestant > 0 && $montantRestant < $montantTotal);
                break;
        }

        if ($shouldInclude) {
            $filteredFactures[] = $data;
        }
    }

    // Construire le titre dynamique
    $moisData = MoisFacturation::getOneById($idMois)->fetch();
    $moisLettre = '';
    if ($moisData && function_exists('getLetterMonth')) {
        $moisLettre = getLetterMonth(isset($moisData['mois']) ? $moisData['mois'] : '');
    }

    $titre = 'Recouvrement';

    if ($moisLettre) {
        $titre .= ' de ' . $moisLettre;
    }

    if ($idReseau > 0) {
        $reseauData = Manager::prepare_query(
            "SELECT nom FROM reseau WHERE id = ?",
            array($idReseau)
        )->fetch();
        if ($reseauData && isset($reseauData['nom'])) {
            $titre .= ' sur ' . htmlspecialchars($reseauData['nom']);
        }
    }

    if (!empty($idTarifFilter)) {
        $tarifLabel = '';
        if (strpos($idTarifFilter, 'base_') === 0) {
            $idConstanteFromFilter = (int) str_replace('base_', '', $idTarifFilter);
            $tarifData = Manager::prepare_query(
                "SELECT prix_metre_cube_eau, description FROM constante_reseau WHERE id = ?",
                array($idConstanteFromFilter)
            )->fetch();
            if ($tarifData) {
                $tarifLabel = number_format($tarifData['prix_metre_cube_eau'], 0, ',', ' ') . ' FCFA/m³';
                if (!empty($tarifData['description'])) {
                    $tarifLabel .= ' (' . htmlspecialchars($tarifData['description']) . ')';
                }
            }
        } elseif (strpos($idTarifFilter, 'diff_') === 0) {
            $idTarifDiffFromFilter = (int) str_replace('diff_', '', $idTarifFilter);
            $tarifDiffData = Manager::prepare_query(
                "SELECT prix_metre_cube_eau, description FROM tarif_differencie WHERE id = ?",
                array($idTarifDiffFromFilter)
            )->fetch();
            if ($tarifDiffData) {
                $tarifLabel = number_format($tarifDiffData['prix_metre_cube_eau'], 0, ',', ' ') . ' FCFA/m³';
                if (!empty($tarifDiffData['description'])) {
                    $tarifLabel .= ' (' . htmlspecialchars($tarifDiffData['description']) . ')';
                }
                $tarifLabel = '[Diff] ' . $tarifLabel;
            }
        }
        if ($tarifLabel) {
            $titre .= ' de tarif ' . $tarifLabel;
        }
    }

    $titles = array(
        'insolvable' => 'Insolvables',
        'paiement_partiel' => 'Paiements partiels',
        'solvable' => 'Solvables',
        'anticipation' => 'Anticipation',
        'en_regle' => 'En règle',
        'pas_en_regle' => 'Pas en règle'
    );
    if ($selectOption != 'vide' && isset($titles[$selectOption])) {
        $titre .= ' : ' . $titles[$selectOption];
    }

    ?>
    <div class="container-fluid mt-4">
        <div class="row mb-3">
            <div class="col-12">
                <h2 class="mb-0">
                    <i class="bi bi-cash-coin"></i> Recouvrement V2
                </h2>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="?page=aep_dashboard&aep_id=<?php echo $aepId; ?>">Tableau de
                                bord</a></li>
                        <li class="breadcrumb-item active">Recouvrement</li>
                    </ol>
                </nav>
            </div>
        </div>

        <div class="card mb-4">
            <div class="card-header bg-light">
                <h5 class="mb-0"><i class="bi bi-funnel"></i> Filtres</h5>
            </div>
            <div class="card-body">
                <form action="?" method="GET" id="recouvrement-filters-form">
                    <input type="hidden" name="list" value="recouvrement_v2">
                    <div class="row g-3">
                        <div class="col-md-3">
                            <label for="id_selected_month" class="form-label">Mois de facturation</label>
                            <select name="id_selected_month" id="id_selected_month" class="form-select">
                                <?php MoisFacturation_t::getOnlyOption($idMois); ?>
                            </select>
                        </div>

                        <div class="col-md-3">
                            <label for="id_reseau_filter" class="form-label">Réseau</label>
                            <select name="id_reseau_filter" id="id_reseau_filter" class="form-select">
                                <option value="0">Tous les réseaux</option>
                                <?php
                                $reseaux = Manager::prepare_query(
                                    "SELECT id, nom FROM reseau WHERE id_aep = ? ORDER BY nom",
                                    array($aepId)
                                )->fetchAll();
                                foreach ($reseaux as $reseau) {
                                    $selected = ($reseau['id'] == $idReseau) ? 'selected' : '';
                                    echo "<option value='{$reseau['id']}' $selected>" . htmlspecialchars($reseau['nom']) . "</option>";
                                }
                                ?>
                            </select>
                        </div>

                        <div class="col-md-3">
                            <label for="id_tarif_filter" class="form-label">Tarif</label>
                            <select name="id_tarif_filter" id="id_tarif_filter" class="form-select">
                                <option value="">Tous les tarifs</option>
                                <?php
                                if (!empty($tarifsDisponibles)) {
                                    foreach ($tarifsDisponibles as $tarif) {
                                        $selected = ($tarif['id'] == $idTarifFilter) ? 'selected' : '';
                                        $label = number_format($tarif['prix_metre_cube_eau'], 0, ',', ' ') . ' FCFA/m³';
                                        if ($tarif['description']) {
                                            $label .= ' - ' . htmlspecialchars($tarif['description']);
                                        }
                                        $prefix = ($tarif['type'] == 'differencie') ? '[Diff] ' : '';
                                        echo "<option value='" . htmlspecialchars($tarif['id']) . "' $selected>" . htmlspecialchars($prefix . $label) . "</option>";
                                    }
                                } else {
                                    echo "<option value=''>Aucun tarif disponible pour ce mois</option>";
                                }
                                ?>
                            </select>
                        </div>

                        <div class="col-md-3">
                            <label for="search_input" class="form-label">Rechercher</label>
                            <div class="input-group">
                                <input type="text" name="search" id="search_input" class="form-control"
                                    placeholder="Nom, réseau..." value="<?php echo htmlspecialchars($searchTerm); ?>">
                                <button class="btn btn-outline-secondary" type="button" id="clear_search_btn"
                                    title="Effacer">
                                    <i class="bi bi-x"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                    <div class="row mt-3">
                        <div class="col-12 text-center">
                            <a href="?list=recouvrement_v2&id_selected_month=<?php echo $idMois; ?>"
                                class="btn btn-warning btn-lg shadow-sm">
                                <i class="bi bi-arrow-counterclockwise"></i> Réinitialiser tous les filtres
                            </a>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <div class="card mb-4">
            <div class="card-body">
                <div class="d-flex flex-wrap gap-2 justify-content-center" role="group">
                    <?php
                    $options = array(
                        'insolvable' => array('label' => 'Insolvables', 'icon' => 'bi-exclamation-triangle', 'class' => 'btn-danger'),
                        'paiement_partiel' => array('label' => 'Paiement partiel', 'icon' => 'bi-hourglass-split', 'class' => 'btn-warning'),
                        'pas_en_regle' => array('label' => 'Pas en règle', 'icon' => 'bi-x-circle', 'class' => 'btn-warning', 'style' => 'background-color: #fd7e14; border-color: #fd7e14;'),
                        'solvable' => array('label' => 'Solvables', 'icon' => 'bi-check-circle', 'class' => 'btn-primary'),
                        'anticipation' => array('label' => 'Anticipation', 'icon' => 'bi-arrow-up-circle', 'class' => 'btn-success'),
                        'en_regle' => array('label' => 'En règle', 'icon' => 'bi-check2-all', 'class' => 'btn-info')
                    );

                    foreach ($options as $key => $option) {
                        $isActive = $selectOption === $key;
                        $urlParams = array(
                            'list' => 'recouvrement_v2',
                            'id_selected_month' => $idMois,
                            'id_reseau_filter' => $idReseau,
                            'select_option' => $key
                        );
                        if (!empty($idTarifFilter)) {
                            $urlParams['id_tarif_filter'] = $idTarifFilter;
                        }
                        if ($searchTerm) {
                            $urlParams['search'] = $searchTerm;
                        }
                        $url = '?' . http_build_query($urlParams);
                        ?>
                        <a href="<?php echo htmlspecialchars($url); ?>"
                            class="btn btn-sm <?php echo $option['class']; ?> <?php echo $isActive ? 'active' : ''; ?>" <?php if (isset($option['style'])): ?>style="<?php echo $option['style']; ?>" <?php endif; ?>>
                            <i class="bi <?php echo $option['icon']; ?>"></i> <?php echo $option['label']; ?>
                        </a>
                        <?php
                    }
                    ?>
                </div>
            </div>
        </div>

        <div class="card shadow-sm">
            <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                <h4 class="mb-0">
                    <i class="bi bi-people"></i> <?php echo htmlspecialchars($titre); ?>
                    <span class="badge bg-light text-dark ms-2"><?php echo count($filteredFactures); ?></span>
                </h4>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-striped table-bordered table-hover table-sm mb-0">
                        <thead class="table-dark">
                            <tr>
                                <th class="text-nowrap">Nom et Prénom</th>
                                <th class="text-center text-nowrap">Réseau</th>
                                <th class="text-center text-nowrap">Index</th>
                                <th class="text-center text-nowrap">Conso</th>
                                <th class="text-center text-nowrap">Prix m³</th>
                                <th class="text-end text-nowrap">Pénalité</th>
                                <th class="text-end text-nowrap">Impayé</th>
                                <th class="text-end text-nowrap">Facture</th>
                                <th class="text-end text-nowrap">Total</th>
                                <th class="text-end text-nowrap">Versement</th>
                                <th class="text-end text-nowrap">Reste</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            if (empty($filteredFactures)) {
                                echo '<tr><td colspan="11" class="text-center text-muted py-4">Aucune facture trouvée</td></tr>';
                            } else {
                                foreach ($filteredFactures as $data) {
                                    $consoMois = Facture::calculeConso((float) $data['nouvel_index'], (float) $data['ancien_index']);
                                    $montantVerse = (int) ($data['montant_verse'] + 0.000000001);
                                    $montantTotal = isset($data['total_cumule']) ? $data['total_cumule'] : 0;
                                    $montantTva = isset($data['montant_conso_tva']) ? $data['montant_conso_tva'] : 0;
                                    $montantRestant = isset($data['restant_cumule']) ? $data['restant_cumule'] : 0;

                                    $categoriePayeur = '';
                                    if ($montantRestant < 0) {
                                        // Reste négatif = anticipation (paiement en avance)
                                        $categoriePayeur = 'anticipation';
                                    } elseif ($montantRestant > 0 && $montantRestant < $montantTotal) {
                                        // Reste positif mais inférieur au total = paiement partiel
                                        $categoriePayeur = 'paiement-partiel';
                                    } elseif ($montantRestant > 0 && $montantRestant == $montantTotal) {
                                        // Reste positif ET égal au total = insolvable (aucun paiement)
                                        $categoriePayeur = 'insolvable';
                                    } elseif ($montantRestant == 0 && $montantTotal > 0) {
                                        // Reste = 0 et total > 0 = solvable (tout payé)
                                        $categoriePayeur = 'solvable';
                                    } elseif ($montantRestant == 0 && $montantTotal == 0) {
                                        // Reste = 0 et total = 0 = en règle (rien à payer)
                                        $categoriePayeur = 'en-regle';
                                    } elseif ($montantRestant > 0) {
                                        // Reste positif (général) = pas en règle
                                        $categoriePayeur = 'pas-en-regle';
                                    }

                                    $modalId = 'recouvrement_Form_' . $data['id_compteur'];
                                    ?>
                                    <tr id="abone_compteur_id_<?php echo $data['id_compteur']; ?>">
                                        <td>
                                            <?php
                                            if (function_exists('make_Modal')) {
                                                echo make_Modal(
                                                    $data['nom_abone'],
                                                    Abone_t::afficheInputRecouvrementAbone($data['id_compteur']),
                                                    -1,
                                                    $modalId,
                                                    ''
                                                );
                                            }
                                            ?>
                                            <a data-bs-toggle="modal" data-bs-target="#<?php echo $modalId; ?>"
                                                style="color: #212529; font-weight: 600; text-decoration: none;">
                                                <?php echo htmlspecialchars(strlen($data['nom_abone']) > 28 ? substr($data['nom_abone'], 0, 24) . '...' : $data['nom_abone']); ?>
                                            </a>
                                        </td>
                                        <td class="text-center">
                                            <span
                                                class="badge bg-secondary text-white"><?php echo htmlspecialchars($data['reseau']); ?></span>
                                        </td>
                                        <td class="text-center">
                                            <div class="d-flex flex-column align-items-center gap-1">
                                                <div class="d-flex align-items-center gap-2">
                                                    <span class="badge bg-secondary text-white">
                                                        <?php echo number_format((float) $data['ancien_index'], 2, ',', ' '); ?>
                                                    </span>
                                                    <span class="text-muted">→</span>
                                                    <span class="badge bg-primary text-white">
                                                        <?php echo number_format((float) $data['nouvel_index'], 2, ',', ' '); ?>
                                                    </span>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="text-center">
                                            <strong><?php echo number_format($consoMois, 2, ',', ' '); ?></strong>
                                        </td>
                                        <td class="text-center">
                                            <strong><?php echo number_format(isset($data['prix_metre_cube_eau']) ? $data['prix_metre_cube_eau'] : 0, 0, ',', ' '); ?></strong>
                                        </td>
                                        <td class="text-end">
                                            <?php echo htmlspecialchars(Facture::formatFinancier((int) $data['penalite'])); ?>
                                        </td>
                                        <td class="text-end">
                                            <?php echo htmlspecialchars(Facture::formatFinancier((int) $data['impayer_cumule'])); ?>
                                        </td>
                                        <td class="text-end"><?php echo htmlspecialchars(Facture::formatFinancier($montantTva)); ?>
                                        </td>
                                        <td class="text-end">
                                            <?php echo htmlspecialchars(Facture::formatFinancier($montantTotal)); ?>
                                        </td>
                                        <td class="pt-0 pb-0 text-end">
                                            <?php if ($editable): ?>
                                                <input type="text" class="form-control p-1 feedback-validation"
                                                    onchange="handleRecouvrement(this.value, <?php echo $data['id']; ?>, this.id)"
                                                    value="<?php echo $montantVerse === 0 ? '' : $montantVerse; ?>"
                                                    id="montant_verse<?php echo $data['id']; ?>">
                                            <?php else: ?>
                                                <span><?php echo htmlspecialchars(Facture::formatFinancier($montantVerse)); ?></span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-end <?php echo $categoriePayeur; ?>">
                                            <?php echo htmlspecialchars(Facture::formatFinancier($montantRestant)); ?>
                                        </td>
                                        <td class="d-none">
                                            <input type="hidden" class="form-control"
                                                id="date_releve_facture_<?php echo $data['id']; ?>"
                                                value="<?php echo date('d/m/Y'); ?>">
                                        </td>
                                    </tr>
                                    <?php
                                }
                            }
                            ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <style>
        .table thead th {
            font-size: 0.875rem;
            font-weight: 600;
            white-space: nowrap;
            vertical-align: middle;
            padding: 0.75rem;
            color: #ffffff !important;
            background-color: #212529;
            border-color: #32383e;
        }

        .table tbody td {
            font-size: 0.9rem;
            padding: 0.75rem;
            vertical-align: middle;
            color: #212529;
            border-color: #dee2e6;
            font-weight: 500;
        }

        .table tbody td strong {
            font-weight: 700;
            color: #212529;
        }

        .table tbody tr:hover {
            background-color: #f8f9fa;
        }

        .table .text-end {
            font-family: 'SF Mono', 'Monaco', 'Inconsolata', 'Roboto Mono', 'Courier New', monospace;
            font-weight: 600;
        }

        .table .anticipation {
            background-color: #198754 !important;
            color: #fff !important;
        }

        .table .paiement-partiel {
            background-color: #ffc107 !important;
            color: #000 !important;
        }

        .table .insolvable {
            background-color: #dc3545 !important;
            color: #fff !important;
        }

        .table .solvable {
            background-color: #0d6efd !important;
            color: #fff !important;
        }

        .table .pas-en-regle {
            background-color: #fd7e14 !important;
            color: #fff !important;
        }

        .table .en-regle {
            background-color: #0dcaf0 !important;
            color: #000 !important;
        }
    </style>

    <script>
        (function () {
            'use strict';

            const form = document.getElementById('recouvrement-filters-form');
            if (form) {
                const selects = form.querySelectorAll('select');
                selects.forEach(function (select) {
                    select.add EventListener('change', function () {
                        form.submit();
                    });
                });
            }

            const searchInput = document.getElementById('search_input');
            const clearBtn = document.getElementById('clear_search_btn');
            let searchTimeout;

            if (searchInput) {
                searchInput.addEventListener('input', function () {
                    clearTimeout(searchTimeout);
                    const term = this.value.trim();

                    if (clearBtn) {
                        clearBtn.style.display = term ? 'block' : 'none';
                    }

                    if (term.length >= 2 || term.length === 0) {
                        searchTimeout = setTimeout(function () {
                            filterTable(term);
                        }, 300);
                    }
                });

                searchInput.addEventListener('keydown', function (e) {
                    if (e.key === 'Escape') {
                        this.value = '';
                        if (clearBtn) clearBtn.style.display = 'none';
                        filterTable('');
                    }
                });
            }

            if (clearBtn) {
                clearBtn.addEventListener('click', function () {
                    if (searchInput) {
                        searchInput.value = '';
                        this.style.display = 'none';
                        filterTable('');
                    }
                });
            }

            function filterTable(searchTerm) {
                const table = document.querySelector('.table tbody');
                if (!table) return;

                const rows = table.querySelectorAll('tr');
                const term = searchTerm.toLowerCase().trim();
                let visibleCount = 0;

                rows.forEach(function (row) {
                    if (row.querySelector('td[colspan]')) {
                        return;
                    }

                    const cells = row.querySelectorAll('td');
                    let found = false;

                    cells.forEach(function (cell) {
                        const text = cell.textContent.toLowerCase();
                        if (text.includes(term)) {
                            found = true;
                        }
                    });

                    if (term === '' || found) {
                        row.style.display = '';
                        visibleCount++;
                    } else {
                        row.style.display = 'none';
                    }
                });

                showNoResultsMessage(visibleCount === 0 && term !== '');
            }

            function showNoResultsMessage(show) {
                const table = document.querySelector('.table tbody');
                if (!table) return;

                let noResultsRow = document.getElementById('no-results-row');

                if (show && !noResultsRow) {
                    noResultsRow = document.createElement('tr');
                    noResultsRow.id = 'no-results-row';
                    noResultsRow.innerHTML = '<td colspan="11" class="text-center text-muted py-4"><i class="bi bi-search"></i> Aucun résultat trouvé</td>';
                    table.appendChild(noResultsRow);
                } else if (!show && noResultsRow) {
                    noResultsRow.remove();
                }
            }

            if (searchInput && clearBtn) {
                clearBtn.style.display = searchInput.value ? 'block' : 'none';
            }

            <?php if ($searchTerm): ?>
                filterTable('<?php echo addslashes($searchTerm); ?>');
            <?php endif; ?>
        })();
    </script>
    <?php
}
