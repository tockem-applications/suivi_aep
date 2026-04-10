<?php

@include_once("../donnees/manager.php");
@include_once("donnees/manager.php");
@include_once("presentation/reseau_modals_component.php");

function ensureReseauHierarchyColumn()
{
    try {
        $exists = Manager::prepare_query(
            "SELECT COUNT(*) AS c FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'reseau' AND column_name = 'id_reseau_parent'",
            array()
        )->fetch();

        if (!$exists || (int) $exists['c'] === 0) {
            Manager::prepare_query("ALTER TABLE `reseau` ADD COLUMN `id_reseau_parent` int(5) unsigned NULL DEFAULT NULL AFTER `id_aep`", array());
            Manager::prepare_query("CREATE INDEX `idx_reseau_parent` ON `reseau` (`id_reseau_parent`)", array());
        }
    } catch (Exception $e) {
        // La page reste utilisable même si l'ajout auto échoue.
    }
}

function ensureCompteurTypeColumnForReseauxPage()
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
        }
    } catch (Exception $e) {
        // fallback silencieux
    }
}

function buildReseauTreeRows($reseaux)
{
    $byId = array();
    $children = array();
    $roots = array();
    $ordered = array();
    $visited = array();

    foreach ($reseaux as $reseau) {
        $id = (int) $reseau['id'];
        $byId[$id] = $reseau;
    }

    foreach ($reseaux as $reseau) {
        $id = (int) $reseau['id'];
        $parentId = !empty($reseau['id_reseau_parent']) ? (int) $reseau['id_reseau_parent'] : 0;
        if ($parentId > 0 && isset($byId[$parentId])) {
            if (!isset($children[$parentId])) {
                $children[$parentId] = array();
            }
            $children[$parentId][] = $id;
        } else {
            $roots[] = $id;
        }
    }

    foreach ($roots as $rootId) {
        appendReseauNode($rootId, 0, '', $ordered, $visited, $byId, $children);
    }

    foreach ($reseaux as $reseau) {
        $id = (int) $reseau['id'];
        if (!isset($visited[$id])) {
            appendReseauNode($id, 0, '', $ordered, $visited, $byId, $children);
        }
    }

    return $ordered;
}

function appendReseauNode($nodeId, $level, $path, &$ordered, &$visited, $byId, $children)
{
    if (isset($visited[$nodeId]) || !isset($byId[$nodeId])) {
        return;
    }
    $visited[$nodeId] = true;
    $row = $byId[$nodeId];
    $row['niveau'] = $level;
    $row['chemin'] = $path === '' ? $row['nom'] : $path . ' > ' . $row['nom'];
    $ordered[] = $row;

    if (isset($children[$nodeId])) {
        foreach ($children[$nodeId] as $childId) {
            appendReseauNode($childId, $level + 1, $row['chemin'], $ordered, $visited, $byId, $children);
        }
    }
}

function getLastMonthRendementForReseau($reseauId, $aepId)
{
    $lastMonthRow = Manager::prepare_query(
        "SELECT mf.id, mf.mois
         FROM mois_facturation mf
         INNER JOIN constante_reseau cr ON cr.id = mf.id_constante
         WHERE cr.id_aep = ? AND mf.est_actif = 1
         ORDER BY mf.mois DESC
         LIMIT 1",
        array($aepId)
    )->fetch();

    // Fallback: si aucun mois actif n'est défini, prendre le mois le plus récent
    if (!$lastMonthRow) {
        $lastMonthRow = Manager::prepare_query(
            "SELECT mf.id, mf.mois
             FROM mois_facturation mf
             INNER JOIN constante_reseau cr ON cr.id = mf.id_constante
             WHERE cr.id_aep = ?
             ORDER BY mf.mois DESC
             LIMIT 1",
            array($aepId)
        )->fetch();
    }

    if (!$lastMonthRow) {
        return array('mois' => '', 'rendement' => null);
    }

    $idMois = (int) $lastMonthRow['id'];
    $mois = $lastMonthRow['mois'];

    $consoReseauRow = Manager::prepare_query(
        "SELECT SUM(i.nouvel_index - i.ancien_index) AS conso
         FROM indexes i
         INNER JOIN compteur_reseau cr ON cr.id_compteur = i.id_compteur
         WHERE cr.id_reseau = ? AND i.id_mois_facturation = ? AND cr.type_compteur = 'distribution'",
        array($reseauId, $idMois)
    )->fetch();
    $consoReseau = $consoReseauRow && $consoReseauRow['conso'] !== null ? (float) $consoReseauRow['conso'] : 0.0;

    $consoAbonnesRow = Manager::prepare_query(
        "SELECT SUM(i.nouvel_index - i.ancien_index) AS conso
         FROM indexes i
         INNER JOIN compteur_abone ca ON ca.id_compteur = i.id_compteur
         INNER JOIN abone a ON a.id = ca.id_abone
         WHERE a.id_reseau = ? AND i.id_mois_facturation = ?",
        array($reseauId, $idMois)
    )->fetch();
    $consoAbonnes = $consoAbonnesRow && $consoAbonnesRow['conso'] !== null ? (float) $consoAbonnesRow['conso'] : 0.0;

    $consoFilsRow = Manager::prepare_query(
        "SELECT SUM(i.nouvel_index - i.ancien_index) AS conso
         FROM indexes i
         INNER JOIN compteur_reseau cr ON cr.id_compteur = i.id_compteur
         INNER JOIN reseau rf ON rf.id = cr.id_reseau
         WHERE rf.id_reseau_parent = ? AND rf.id_aep = ? AND i.id_mois_facturation = ? AND cr.type_compteur = 'distribution'",
        array($reseauId, $aepId, $idMois)
    )->fetch();
    $consoFils = $consoFilsRow && $consoFilsRow['conso'] !== null ? (float) $consoFilsRow['conso'] : 0.0;

    $numerateur = $consoAbonnes + $consoFils;
    $rendement = $consoReseau > 0 ? round(($numerateur / $consoReseau) * 100, 2) : null;

    return array('mois' => $mois, 'rendement' => $rendement);
}

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$aepId = isset($_SESSION['id_aep']) ? (int) $_SESSION['id_aep'] : 0;
$message = '';

if (isset($_GET['error'])) {
    $text = isset($_GET['message']) ? urldecode($_GET['message']) : 'Une erreur est survenue.';
    $message = '<div class="alert alert-danger">' . htmlspecialchars($text) . '</div>';
}
if (isset($_GET['success'])) {
    $message = '<div class="alert alert-success">Opération effectuée avec succès.</div>';
}

ensureReseauHierarchyColumn();
ensureCompteurTypeColumnForReseauxPage();

$reseaux = array();
$reseauxById = array();
$reseauxTries = array();
$rendementHeaderMois = '';

if (!$aepId) {
    $message .= '<div class="alert alert-danger">Aucun AEP sélectionné. Veuillez sélectionner un AEP.</div>';
} else {
    $reseaux = Manager::prepare_query(
        "SELECT r.*, p.nom AS parent_nom,
                (SELECT COUNT(*) FROM abone a WHERE a.id_reseau = r.id) AS nb_abonnes,
                (SELECT COUNT(*) FROM reseau rf WHERE rf.id_reseau_parent = r.id) AS nb_enfants
         FROM reseau r
         LEFT JOIN reseau p ON p.id = r.id_reseau_parent
         WHERE r.id_aep = ?
         ORDER BY r.nom ASC",
        array($aepId)
    )->fetchAll();

    foreach ($reseaux as $idx => $reseau) {
        $rendementInfo = getLastMonthRendementForReseau((int) $reseau['id'], $aepId);
        $reseaux[$idx]['rendement_last_month'] = $rendementInfo['rendement'];
        $reseaux[$idx]['rendement_last_month_label'] = $rendementInfo['mois'];
        if ($rendementHeaderMois === '' && !empty($rendementInfo['mois'])) {
            $rendementHeaderMois = getLetterMonth($rendementInfo['mois']);
        }
        $reseauxById[(int) $reseau['id']] = $reseaux[$idx];
    }
    $reseauxTries = buildReseauTreeRows($reseaux);
}
?>

<style>
    .tree-level-badge {
        min-width: 30px;
        text-align: center;
    }

    .reseau-tree-name {
        white-space: nowrap;
    }

    .reseau-tree-name .indent {
        display: inline-block;
    }

    .form-card {
        border: 1px solid #e9ecef;
        border-radius: 12px;
    }
</style>

<div class="container-fluid mt-5">
    <h2 class="mb-3">Gestion des Réseaux</h2>
    <?php echo $message; ?>

    <div class="card shadow-sm">
        <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
            <h5 class="mb-0"><i class="bi bi-diagram-3 me-2"></i>Structure des réseaux</h5>
            <button type="button" class="btn btn-light btn-sm" data-bs-toggle="modal" data-bs-target="#addReseauModal"
                <?php echo $aepId ? '' : 'disabled'; ?>>
                <i class="bi bi-plus-circle me-1"></i>Nouveau réseau
            </button>
        </div>
        <div class="card-body">
            <?php if (count($reseauxTries) > 0): ?>
                <div class="table-responsive">
                    <table class="table table-striped table-hover align-middle mb-0">
                        <thead class="table-dark">
                            <tr>
                                <th>Réseau</th>
                                <th>Niveau</th>
                                <th>Parent direct</th>
                                <th>Abréviation</th>
                                <th>Date création</th>
                                <th>
                                    Rendement
                                    <?php if ($rendementHeaderMois !== ''): ?>
                                        (<?php echo htmlspecialchars($rendementHeaderMois); ?>)
                                    <?php endif; ?>
                                </th>
                                <th>Description</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($reseauxTries as $reseau): ?>
                                <?php
                                $niveau = (int) $reseau['niveau'];
                                $indentPx = $niveau * 18;
                                $description = isset($reseau['description_reseau']) ? $reseau['description_reseau'] : '';
                                ?>
                                <tr>
                                    <td class="reseau-tree-name">
                                        <span class="indent" style="width: <?php echo $indentPx; ?>px;"></span>
                                        <?php if ($niveau > 0): ?>
                                            <i class="bi bi-arrow-return-right text-muted me-1"></i>
                                        <?php endif; ?>
                                        <strong><?php echo htmlspecialchars($reseau['nom']); ?></strong>
                                    </td>
                                    <td>
                                        <span class="badge bg-info text-dark tree-level-badge">
                                            <?php echo $niveau; ?>
                                        </span>
                                    </td>
                                    <td><?php echo !empty($reseau['parent_nom']) ? htmlspecialchars($reseau['parent_nom']) : '<span class="text-muted">Racine</span>'; ?></td>
                                    <td><?php echo !empty($reseau['abreviation']) ? htmlspecialchars($reseau['abreviation']) : '-'; ?></td>
                                    <td><?php echo htmlspecialchars($reseau['date_creation']); ?></td>
                                    <td>
                                        <?php if (isset($reseau['rendement_last_month']) && $reseau['rendement_last_month'] !== null): ?>
                                            <span class="badge bg-primary"
                                                title="Rendement = (volume abonnes + volume reseaux fils directs) / volume compteurs reseau">
                                                <?php echo number_format((float) $reseau['rendement_last_month'], 2, ',', ' '); ?>%
                                            </span>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td title="<?php echo htmlspecialchars($description); ?>">
                                        <?php echo htmlspecialchars(substr($description, 0, 70)); ?><?php echo strlen($description) > 70 ? '…' : ''; ?>
                                    </td>
                                    <td>
                                        <div class="d-flex gap-1">
                                            <a href="?page=reseau_detail&id=<?php echo (int) $reseau['id']; ?>"
                                                class="btn btn-sm btn-outline-info">
                                                <i class="bi bi-eye"></i> Détails
                                            </a>
                                            <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal"
                                                data-bs-target="#editReseauModal_<?php echo $reseau['id']; ?>">
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
                                            <span class="d-inline-block"
                                                <?php if ($deleteDisabled): ?>
                                                    data-bs-toggle="tooltip"
                                                    data-bs-placement="top"
                                                    data-bs-title="<?php echo htmlspecialchars($deleteReason); ?>"
                                                    tabindex="0"
                                                <?php endif; ?>>
                                                <button type="button" class="btn btn-sm btn-outline-danger"
                                                    data-bs-toggle="modal"
                                                    data-bs-target="#deleteReseauModal_<?php echo $reseau['id']; ?>"
                                                    <?php echo $deleteDisabled ? 'disabled' : ''; ?>>
                                                    <i class="bi bi-trash"></i> Supprimer
                                                </button>
                                            </span>
                                        </div>
                                    </td>
                                </tr>
                                <?php
                                renderReseauUpdateModalComponent($reseau, $reseaux, 'editReseauModal_' . $reseau['id']);
                                renderReseauDeleteModalComponent(
                                    $reseau,
                                    'deleteReseauModal_' . $reseau['id'],
                                    isset($reseau['nb_abonnes']) ? (int) $reseau['nb_abonnes'] : 0,
                                    isset($reseau['nb_enfants']) ? (int) $reseau['nb_enfants'] : 0
                                );
                                ?>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="text-center text-muted py-5">
                    <i class="bi bi-diagram-3 fs-1"></i>
                    <p class="mt-3 mb-0">Aucun réseau pour cet AEP. Créez votre premier nœud racine.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="modal fade" id="addReseauModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content form-card">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title"><i class="bi bi-plus-circle me-2"></i>Créer un réseau</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="post" action="traitement/reseau_t.php">
                    <input type="hidden" name="action" value="add_reseau">
                    <div class="modal-body">
                        <div class="row g-3">
                            <div class="col-md-8">
                                <label class="form-label">Nom du réseau *</label>
                                <input type="text" class="form-control" name="nom" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Abréviation</label>
                                <input type="text" class="form-control" name="abreviation">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Date de création *</label>
                                <input type="date" class="form-control" name="date_creation" value="<?php echo date('Y-m-d'); ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Réseau parent (optionnel)</label>
                                <select name="id_reseau_parent" class="form-select">
                                    <option value="">Aucun (réseau racine)</option>
                                    <?php foreach ($reseaux as $candidate): ?>
                                        <option value="<?php echo (int) $candidate['id']; ?>">
                                            <?php echo htmlspecialchars($candidate['nom']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-12">
                                <label class="form-label">Description</label>
                                <textarea class="form-control" rows="3" name="description_reseau"
                                    placeholder="Ex: Branche Est du réseau principal..."></textarea>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Annuler</button>
                        <button type="submit" class="btn btn-success">
                            <i class="bi bi-check2-circle me-1"></i>Créer le réseau
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
    (function () {
        if (typeof bootstrap === 'undefined') return;
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        tooltipTriggerList.forEach(function (tooltipTriggerEl) {
            new bootstrap.Tooltip(tooltipTriggerEl);
        });
    })();
</script>