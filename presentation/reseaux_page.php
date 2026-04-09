<?php

@include_once("../donnees/manager.php");
@include_once("donnees/manager.php");

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

$reseaux = array();
$reseauxById = array();
$reseauxTries = array();

if (!$aepId) {
    $message .= '<div class="alert alert-danger">Aucun AEP sélectionné. Veuillez sélectionner un AEP.</div>';
} else {
    $reseaux = Manager::prepare_query(
        "SELECT r.*, p.nom AS parent_nom
         FROM reseau r
         LEFT JOIN reseau p ON p.id = r.id_reseau_parent
         WHERE r.id_aep = ?
         ORDER BY r.nom ASC",
        array($aepId)
    )->fetchAll();

    foreach ($reseaux as $reseau) {
        $reseauxById[(int) $reseau['id']] = $reseau;
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
                                    <td title="<?php echo htmlspecialchars($description); ?>">
                                        <?php echo htmlspecialchars(substr($description, 0, 70)); ?><?php echo strlen($description) > 70 ? '…' : ''; ?>
                                    </td>
                                    <td>
                                        <div class="d-flex gap-1">
                                            <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal"
                                                data-bs-target="#editReseauModal_<?php echo $reseau['id']; ?>">
                                                <i class="bi bi-pencil"></i> Modifier
                                            </button>
                                            <form method="post" action="traitement/reseau_t.php"
                                                onsubmit="return confirm('Supprimer ce réseau et ses données associées ?');" class="d-inline">
                                                <input type="hidden" name="action" value="delete_reseau">
                                                <input type="hidden" name="reseau_id" value="<?php echo (int) $reseau['id']; ?>">
                                                <button type="submit" class="btn btn-sm btn-outline-danger">
                                                    <i class="bi bi-trash"></i> Supprimer
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>

                                <div class="modal fade" id="editReseauModal_<?php echo $reseau['id']; ?>" tabindex="-1">
                                    <div class="modal-dialog modal-dialog-centered">
                                        <div class="modal-content form-card">
                                            <div class="modal-header bg-primary text-white">
                                                <h5 class="modal-title"><i class="bi bi-pencil-square me-2"></i>Modifier le réseau</h5>
                                                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                            </div>
                                            <form method="post" action="traitement/reseau_t.php">
                                                <input type="hidden" name="action" value="update_reseau">
                                                <input type="hidden" name="reseau_id" value="<?php echo (int) $reseau['id']; ?>">
                                                <div class="modal-body">
                                                    <div class="row g-3">
                                                        <div class="col-md-8">
                                                            <label class="form-label">Nom du réseau *</label>
                                                            <input type="text" class="form-control" name="nom"
                                                                value="<?php echo htmlspecialchars($reseau['nom']); ?>" required>
                                                        </div>
                                                        <div class="col-md-4">
                                                            <label class="form-label">Abréviation</label>
                                                            <input type="text" class="form-control" name="abreviation"
                                                                value="<?php echo htmlspecialchars($reseau['abreviation']); ?>">
                                                        </div>
                                                        <div class="col-md-6">
                                                            <label class="form-label">Date de création *</label>
                                                            <input type="date" class="form-control" name="date_creation"
                                                                value="<?php echo htmlspecialchars($reseau['date_creation']); ?>" required>
                                                        </div>
                                                        <div class="col-md-6">
                                                            <label class="form-label">Réseau parent (optionnel)</label>
                                                            <select name="id_reseau_parent" class="form-select">
                                                                <option value="">Aucun (réseau racine)</option>
                                                                <?php foreach ($reseaux as $candidate): ?>
                                                                    <?php if ((int) $candidate['id'] !== (int) $reseau['id']): ?>
                                                                        <option value="<?php echo (int) $candidate['id']; ?>"
                                                                            <?php echo ((int) $reseau['id_reseau_parent'] === (int) $candidate['id']) ? 'selected' : ''; ?>>
                                                                            <?php echo htmlspecialchars($candidate['nom']); ?>
                                                                        </option>
                                                                    <?php endif; ?>
                                                                <?php endforeach; ?>
                                                            </select>
                                                        </div>
                                                        <div class="col-12">
                                                            <label class="form-label">Description</label>
                                                            <textarea class="form-control" rows="3"
                                                                name="description_reseau"><?php echo htmlspecialchars($description); ?></textarea>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Annuler</button>
                                                    <button type="submit" class="btn btn-primary">
                                                        <i class="bi bi-check2-circle me-1"></i>Enregistrer
                                                    </button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>
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