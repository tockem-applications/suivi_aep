<?php
/**
 * Page de détails des versements pour une redevance et un mois spécifique
 */

@include_once("../donnees/redevance.php");
@include_once("donnees/redevance.php");
@include_once("../donnees/versements.php");
@include_once("donnees/versements.php");
@include_once("../donnees/mois_facturation.php");
@include_once("donnees/mois_facturation.php");

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Récupérer l'AEP actuel
$aepId = isset($_SESSION['id_aep']) ? (int)$_SESSION['id_aep'] : 0;
$id_redevance = isset($_GET['id_redevance']) ? (int)$_GET['id_redevance'] : 0;
$id_mois = isset($_GET['id_mois']) ? (int)$_GET['id_mois'] : 0;

if (!$aepId || !$id_redevance || !$id_mois) {
    $message = '<div class="alert alert-danger">Paramètres manquants.</div>';
    $redevance = null;
    $mois = null;
    $versements = array();
} else {
    $redevance = Redevance::getRedevance($id_redevance);
    $mois = Manager::prepare_query(
        "SELECT m.*, c.id_aep 
         FROM mois_facturation m 
         INNER JOIN constante_reseau c ON m.id_constante = c.id 
         WHERE m.id = ? AND c.id_aep = ?",
        array($id_mois, $aepId)
    )->fetch();
    
    if (!$redevance || !$mois || $redevance->id_aep != $aepId) {
        $message = '<div class="alert alert-danger">Redevance ou mois introuvable.</div>';
        $redevance = null;
        $mois = null;
        $versements = array();
    } else {
        $versements = Manager::prepare_query(
            "SELECT * FROM versements 
             WHERE id_redevance = ? AND id_mois_facturation = ? 
             ORDER BY date_versement DESC",
            array($id_redevance, $id_mois)
        )->fetchAll();
        
        $montant_estimatif = Redevance::calculerMontantEstimatif($id_redevance, $id_mois);
        $montant_verse = Redevance::getMontantDejaVerse($id_redevance, $id_mois);
        $reste_a_verser = Redevance::getResteAVerser($id_redevance, $id_mois);
        $message = '';
    }
}

// Gérer les messages
if (isset($_GET['success'])) {
    switch ($_GET['success']) {
        case 'versement_deleted':
            $message = '<div class="alert alert-success">Versement supprimé avec succès.</div>';
            break;
    }
} elseif (isset($_GET['error'])) {
    $msg = isset($_GET['message']) ? htmlspecialchars(urldecode($_GET['message'])) : 'Une erreur est survenue.';
    $message = '<div class="alert alert-danger">Erreur : ' . $msg . '</div>';
}
?>

<div class="container-fluid mt-4">
    <div class="row mb-3">
        <div class="col-12">
            <a href="?page=redevance_versements&id_redevance=<?php echo $id_redevance; ?>" class="btn btn-secondary">
                <i class="bi bi-arrow-left"></i> Retour aux versements
            </a>
        </div>
    </div>

    <?php echo $message; ?>

    <?php if ($redevance && $mois): ?>
        <!-- En-tête -->
        <div class="card shadow-sm mb-3">
            <div class="card-header bg-primary text-white">
                <h4 class="mb-0">
                    <i class="bi bi-cash-coin"></i> Détails des versements - <?php echo htmlspecialchars($redevance->libele); ?> - <?php echo $mois['mois']; ?>
                </h4>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-3">
                        <div class="card bg-info text-white">
                            <div class="card-body">
                                <h6>Redevance brute</h6>
                                <h4><?php echo number_format($montant_estimatif, 0, ',', ' '); ?> FCFA</h4>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-success text-white">
                            <div class="card-body">
                                <h6>Montant versé</h6>
                                <h4><?php echo number_format($montant_verse, 0, ',', ' '); ?> FCFA</h4>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-warning text-white">
                            <div class="card-body">
                                <h6>Reste à verser</h6>
                                <h4><?php echo number_format($reste_a_verser, 0, ',', ' '); ?> FCFA</h4>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-secondary text-white">
                            <div class="card-body">
                                <h6>Pourcentage versé</h6>
                                <h4><?php echo $montant_estimatif > 0 ? number_format(($montant_verse / $montant_estimatif) * 100, 1) : 0; ?>%</h4>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Liste des versements -->
        <div class="card shadow-sm">
            <div class="card-header bg-light">
                <h5 class="mb-0"><i class="bi bi-list-ul"></i> Historique des versements</h5>
            </div>
            <div class="card-body">
                <?php if (count($versements) > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead class="table-dark">
                                <tr>
                                    <th>Date de versement</th>
                                    <th>Montant (FCFA)</th>
                                    <th>Statut</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($versements as $versement): ?>
                                    <tr>
                                        <td><?php echo date('d/m/Y', strtotime($versement['date_versement'])); ?></td>
                                        <td><strong><?php echo number_format($versement['montant'], 0, ',', ' '); ?></strong></td>
                                        <td>
                                            <span class="badge <?php echo $versement['est_valide'] ? 'bg-success' : 'bg-warning'; ?>">
                                                <?php echo $versement['est_valide'] ? 'Validé' : 'En attente'; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <button type="button" class="btn btn-sm btn-danger" 
                                                    onclick="supprimerVersement(<?php echo $versement['id']; ?>)"
                                                    title="Supprimer">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                            <tfoot>
                                <tr class="table-info">
                                    <th>Total</th>
                                    <th><strong><?php echo number_format($montant_verse, 0, ',', ' '); ?> FCFA</strong></th>
                                    <th colspan="2"></th>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="text-center text-muted py-4">
                        <i class="bi bi-inbox fs-1"></i>
                        <p class="mt-2">Aucun versement enregistré pour ce mois</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <script>
        function supprimerVersement(idVersement) {
            if (confirm('Êtes-vous sûr de vouloir supprimer ce versement ?')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = 'traitement/versement_t.php';

                const actionInput = document.createElement('input');
                actionInput.type = 'hidden';
                actionInput.name = 'action';
                actionInput.value = 'delete_versement';

                const idInput = document.createElement('input');
                idInput.type = 'hidden';
                idInput.name = 'id';
                idInput.value = idVersement;

                form.appendChild(actionInput);
                form.appendChild(idInput);
                document.body.appendChild(form);
                form.submit();
            }
        }
        </script>
    <?php else: ?>
        <div class="alert alert-warning">
            <i class="bi bi-exclamation-triangle"></i> Redevance ou mois introuvable.
        </div>
    <?php endif; ?>
</div>
