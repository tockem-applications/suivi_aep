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
                    <div class="col-md-4 mb-4">
                        <div class="card card-hover h-100">
                            <div class="card-body">
                                <h5 class="card-title"><?php echo htmlspecialchars($aep['libele']); ?></h5>
                                <p class="card-text">crée le: <?php echo htmlspecialchars($aep['date']); ?></p>
                                <p class="card-text">
                                    Description: <?php echo htmlspecialchars($aep['description']); ?></p>
                                <p class="card-text">Modèle de
                                    facture: <?php echo htmlspecialchars($aep['fichier_facture']); ?></p>

                            </div>
                            <div class="card-footer">
                                <a href="traitement/aep_t.php?select_aep=true&id_aep=<?php echo $aep['id']; ?>"
                                   class="btn btn-primary w-100">Selectionner</a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
            <div class="col-md-4 mb-4">
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
        <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button"
           data-bs-toggle="dropdown" aria-expanded="false">
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
                    <li><a class="dropdown-item <?php /*echo $aep['id']==$_SESSION['id_aep']?'disabled':''; */?>"
                           href="traitement/aep_t.php?select_aep=true&id_aep=<?php echo $aep['id']; ?>"><?php echo htmlspecialchars($aep['libele']); ?></a>
                    </li>
                <?php endforeach; ?>
                <li>
                    <hr class="dropdown-divider">
                </li>
                <li>
                    <a class="dropdown-item" href="?form=aep">Nouvel Aep</a>
                </li>
                <li><a class="dropdown-item"
                       href="traitement/aep_t.php?select_aep=true&id_aep=0">tout fermer</a>
                </li>
            <?php endif; ?>

        </ul>
    </li>
    <?php

}
