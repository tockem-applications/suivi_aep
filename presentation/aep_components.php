<?php
//include_once("traitement/aep_t.php");

function display_aep_to_select()
{
    $aep_list = Aep_t::getAll();
    ?>
    <div class="container-fluid">
        <h2 class="d-flex justify-content-center p-3">Liste des AEP</h2>
        <div class="row">
            <?php if (empty($aep_list)): ?>
                <div class="col-12">
                    <div class="alert alert-warning" role="alert">
                        Aucun AEP trouvé.
                    </div>
                </div>
            <?php else: ?>
                <?php foreach ($aep_list as $aep): ?>
                    <?php
                    // Récupérer stats utiles pour l'AEP
                    $aepId = isset($aep['id']) ? (int) $aep['id'] : 0;
                    $libele = isset($aep['libele']) ? $aep['libele'] : '';

                    // Nb réseaux
                    $nbReseaux = 0;
                    $resReseaux = Manager::prepare_query("SELECT COUNT(*) as c FROM reseau WHERE id_aep = ?", array($aepId));
                    if ($resReseaux) {
                        $row = $resReseaux->fetch();
                        $nbReseaux = isset($row['c']) ? (int) $row['c'] : 0;
                    }

                    // Nb abonnés (via réseaux)
                    $nbAbonnes = 0;
                    $resAb = Manager::prepare_query(
                        "SELECT COUNT(*) as c FROM abone a INNER JOIN reseau r ON r.id = a.id_reseau WHERE r.id_aep = ?",
                        array($aepId)
                    );
                    if ($resAb) {
                        $row = $resAb->fetch();
                        $nbAbonnes = isset($row['c']) ? (int) $row['c'] : 0;
                    }

                    // Dernier mois de facturation de l'AEP
                    $lastMois = '';
                    $lastMoisId = 0;
                    $resMois = Manager::prepare_query(
                        "SELECT mf.id, mf.mois, mf.est_actif FROM mois_facturation mf INNER JOIN constante_reseau c ON c.id = mf.id_constante WHERE c.id_aep = ? ORDER BY mf.mois DESC LIMIT 1",
                        array($aepId)
                    );
                    if ($resMois) {
                        $row = $resMois->fetch();
                        if ($row) {
                            $lastMoisId = isset($row['id']) ? (int) $row['id'] : 0;
                            $lastMois = getLetterMonth($row['mois']);
                        }
                    }

                    // Montants facturé/recouvré du dernier mois
                    $montantTotal = 0;
                    $montantVerse = 0;
                    $taux = 0;
                    $consoTotale = 0;
                    if ($lastMoisId > 0) {
                        $resV = Manager::prepare_query(
                            "SELECT SUM(vaf.montant_total) as mt, SUM(vaf.montant_verse) as mv, SUM(vaf.consommation) as cs FROM vue_abones_facturation vaf WHERE vaf.id_mois = ? AND vaf.id_aep = ?",
                            array($lastMoisId, $aepId)
                        );
                        if ($resV) {
                            $row = $resV->fetch();
                            $montantTotal = isset($row['mt']) ? (float) $row['mt'] : 0;
                            $montantVerse = isset($row['mv']) ? (float) $row['mv'] : 0;
                            $consoTotale = isset($row['cs']) ? (float) $row['cs'] : 0;
                            $taux = ($montantTotal > 0) ? round(($montantVerse * 100.0) / $montantTotal) : 0;
                        }
                    }

                    // Styles utilitaires
                    $badgeClass = $taux >= 95 ? 'bg-success' : ($taux >= 70 ? 'bg-warning' : 'bg-danger');
                    ?>
                    <div class="col-md-6 col-lg-4 mb-4">
                        <div class="card shadow-sm border-0 h-100">
                            <div class="card-header bg-light d-flex justify-content-between align-items-center">
                                <strong class="text-primary"><?php echo htmlspecialchars($libele); ?></strong>
                                <span class="badge <?php echo $badgeClass; ?>"><?php echo $taux; ?>% recouvrement</span>
                            </div>
                            <div class="card-body">
                                <div class="d-flex justify-content-between mb-2">
                                    <div>
                                        <div class="small text-muted">Réseaux</div>
                                        <div class="fs-5"><?php echo $nbReseaux; ?></div>
                                    </div>
                                    <div>
                                        <div class="small text-muted">Abonnés</div>
                                        <div class="fs-5"><?php echo $nbAbonnes; ?></div>
                                    </div>
                                    <div>
                                        <div class="small text-muted">Mois</div>
                                        <div class="fs-6"><?php echo $lastMois ? htmlspecialchars($lastMois) : '—'; ?></div>
                                    </div>
                                </div>
                                <div class="row g-2">
                                    <div class="col-6">
                                        <div class="p-2 bg-light rounded border">
                                            <div class="small text-muted">Facturé (dernier mois)</div>
                                            <div class="fw-bold"><?php echo number_format($montantTotal, 0, ',', ' '); ?> FCFA</div>
                                        </div>
                                    </div>
                                    <div class="col-6">
                                        <div class="p-2 bg-light rounded border">
                                            <div class="small text-muted">Recouvré</div>
                                            <div class="fw-bold text-success">
                                                <?php echo number_format($montantVerse, 0, ',', ' '); ?> FCFA
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-12">
                                        <div class="p-2 bg-light rounded border">
                                            <div class="small text-muted">Consommation (dernier mois)</div>
                                            <div class="fw-bold"><?php echo number_format($consoTotale, 2, ',', ' '); ?> m³</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="card-footer bg-white d-flex gap-2">
                                <a href="traitement/aep_t.php?select_aep=true&id_aep=<?php echo $aepId; ?>"
                                    class="btn btn-primary flex-fill">
                                    Sélectionner
                                </a>
<!--                                <a href="?page=aep_detail&aep_id=--><?php //echo $aepId; ?><!--" class="btn btn-outline-secondary flex-fill">-->
<!--                                    Détails AEP-->
<!--                                </a>-->
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>

            <div class="card card-hover h-100">
                <div class="card-body">
                    <h5 class="card-title">Nouvel AEP</h5>
                    <p class="card-text">crée le creer un AEP</p>
                    <p class="card-text">Cette action va vous permettre de creer un nouvel aep</p>
                    <p class="card-text"></p>
                </div>
                <div class="card-footer">
                    <a href="?form=aep" class="btn btn-success w-100">Créer</a>
                </div>
            </div>
        </div>
    </div>
    </div>
    <?php

}

function display_li_aep_to_select()
{
    $aep_list = Aep_t::getAll();
    ?>
    <li class="nav-item dropdown">
        <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown"
            aria-expanded="false">
            <?php echo htmlspecialchars($_SESSION['libele_aep']) ?>
        </a>
        <ul class="dropdown-menu" aria-labelledby="navbarDropdown">
            <!--                        <li><a class="dropdown-item" href="?form=contrat">Facturer des abonés</a></li>-->
            <?php if (empty($aep_list)): ?>
                <div class="col-12">
                    <div class="alert alert-warning" role="alert">
                        Aucun AEP trouvé.
                    </div>
                </div>
            <?php else: ?>
                <?php foreach ($aep_list as $aep): ?>
                    <li><a class="dropdown-item <?php /*echo $aep['id']==$_SESSION['id_aep']?'disabled':''; */ ?>"
                            href="traitement/aep_t.php?select_aep=true&id_aep=<?php echo $aep['id']; ?>"><?php echo htmlspecialchars($aep['libele']); ?></a>
                    </li>
                <?php endforeach; ?>
                <li>
                    <hr class="dropdown-divider">
                </li>
                <li>
                    <a class="dropdown-item" href="?form=aep">Nouvel Aep</a>
                </li>
                <li><a class="dropdown-item" href="traitement/aep_t.php?select_aep=true&id_aep=0">tout fermer</a>
                </li>
            <?php endif; ?>

        </ul>
    </li>
    <?php

}
