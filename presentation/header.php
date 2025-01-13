<?php
require("traitement/aep_t.php");

$is_aep_selected = Aep_t::isAepIdInSession();

?>

<nav class="navbar navbar-expand-lg navbar-light bg-light">
    <div class="container-fluid">
        <a class="navbar-brand" href="index.php?page=home">Suivi AEP</a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarSupportedContent"
                aria-controls="navbarSupportedContent" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        <?php if ($is_aep_selected): ?>
            <div class="collapse navbar-collapse" id="navbarSupportedContent">
                <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                    <?php display_li_aep_to_select();?>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button"
                           data-bs-toggle="dropdown" aria-expanded="false">
                            Structure
                        </a>
                        <ul class="dropdown-menu" aria-labelledby="navbarDropdown">

                            <li><a class="dropdown-item" href="?page=reseau">Reseaux</a></li>
                            <li><a class="dropdown-item" href="?form=reseau">Nouveau reseau</a></li>

                            <li>
                                <hr class="dropdown-divider">
                            </li>
                            <li><a class="dropdown-item" href="?form=abone"> Ajouter un aboné</a></li>
                            <!--                        <li><a class="dropdown-item" href="?form=contrat">Contrat</a></li>-->
                            <li><a class="dropdown-item" href="?list=abone_simple">liste Compteurs</a></li>
                            <li><a class="dropdown-item" href="?list=distribution_simple">Liste des abonés</a></li>
                            <li><a class="dropdown-item" href="?list=production_simple">Liste des compteurs de
                                    production</a></li>
                            <!--                        <li><a class="dropdown-item" href="?form=trying">trying form</a></li>-->
                            <li>
                                <hr class="dropdown-divider">
                            </li>
                        </ul>
                    </li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button"
                           data-bs-toggle="dropdown" aria-expanded="false">
                            Facturation
                        </a>
                        <ul class="dropdown-menu" aria-labelledby="navbarDropdown">
                            <li><a class="dropdown-item" href="index.php?list=recouvrement">Recouvrement</a></li>
                            <!-- <li><a class="dropdown-item" href="index.php?list=appartement">Nouveau Mois</a></li> -->
                            <li><a class="dropdown-item" href="index.php?list=facture_month">Facturation</a></li>
                            <li><a class="dropdown-item" href="index.php?list=mois_facturation">Mois Facturés</a></li>
                            <!--                        <li><a class="dropdown-item" href="index.php?list=contrat">Anciennes Factures</a></li>-->
                            <!--                        <li><a class="dropdown-item" href="index.php?list=insolvables">Insolvables</a></li>-->
                            <!--                        <li><a class="dropdown-item" href="index.php?list=proprietaire">Soldés</a></li>-->
                            <li><a class="dropdown-item" href="index.php?form=constante_reseau">Tarifs AEP</a></li>
                            <?php
                            if (isset($_SESSION['id'], $_SESSION['email'], $_SESSION['nom'], $_SESSION['prenom'])) {
                                if ($_SESSION['id'] == '1') {
                                    ?>
                                    <li><a class="dropdown-item" href="index.php?list=cle">cle</a></li>
                                <?php }
                            } ?>
                            <!--                        <li>-->
                            <!--                            <hr class="dropdown-divider">-->
                            <!--                        </li>-->
                        </ul>
                    </li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button"
                           data-bs-toggle="dropdown" aria-expanded="false">
                            Operation
                        </a>
                        <ul class="dropdown-menu" aria-labelledby="navbarDropdown">
                            <!--                        <li><a class="dropdown-item" href="?form=contrat">Facturer des abonés</a></li>-->
                            <li><a class="dropdown-item" href="traitement/abone_t.php?action=export_index"
                                   target="_blank">Exporter vers mobile </a></li>
                            <li><a class="dropdown-item" href="?form=import_index">Relève automatique</a></li>
                            <li><a class="dropdown-item" href="?list=releve_manuelle">relève manuelle</a></li>
                            <!-- <li><a class="dropdown-item" href="index.php?list=contrat">Avis de coupure</a></li> -->
                            <!--                        <li><a class="dropdown-item" href="?action=export_index&list=abone_simple" >Avis de coupure</a></li>-->
                            <!--                        <li><a class="dropdown-item" href="">Resilier Contrat</a></li>-->
                            <li>
                                <hr class="dropdown-divider">
                            </li>
                        </ul>
                    </li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button"
                           data-bs-toggle="dropdown" aria-expanded="false">
                            Finances
                        </a>
                        <ul class="dropdown-menu" aria-labelledby="navbarDropdown">
                            <!--                        <li><a class="dropdown-item" href="?form=contrat">Facturer des abonés</a></li>-->
                            <li><a class="dropdown-item" href="?form=finance">Nouvelle transaction </a></li>
                            <li><a class="dropdown-item" href="?list=transaction">Liste des depences</a></li>
                            <li><a class="dropdown-item" href="?list=releve_manuelle">Liste des entrées</a></li>
                            <!-- <li><a class="dropdown-item" href="index.php?list=contrat">Avis de coupure</a></li> -->
                            <!--                        <li><a class="dropdown-item" href="?action=export_index&list=abone_simple" >Avis de coupure</a></li>-->
                            <!--                        <li><a class="dropdown-item" href="">Resilier Contrat</a></li>-->
                            <li>
                                <hr class="dropdown-divider">
                            </li>
                        </ul>
                    </li>

                    <!-- <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button"
                           data-bs-toggle="dropdown" aria-expanded="false">
                            Impression
                        </a>
                        <ul class="dropdown-menu" aria-labelledby="navbarDropdown">
                            <li><a class="dropdown-item" href="?impression=appartement">Liste Locataires</a></li>
                            <li><a class="dropdown-item" href="index.php?impression=proprietaire">Liste Proprietaires</a></li>
                            <li><a class="dropdown-item" href="index.php?impression=locataire">Liste Locataires</a></li>
                            <li><a class="dropdown-item" href="index.php?impression=contrat">Imprimer contrat</a></li>
                            <li><a class="dropdown-item" href="index.php?impression=locataire">Locataire</a></li>
                            <li><a class="dropdown-item" href="index.php?impression=proprietaire">Prorietaire</a></li>
                            <li><a class="dropdown-item" href="index.php?impression=tarif">Tarif</a></li>
                            <li>
                                <hr class="dropdown-divider">
                            </li>
                        </ul>
                    </li> -->
                    <li class="nav-item dropdown">
                        <a class="nav-link "
                           href="<?= isset($_SESSION['id'], $_SESSION['email'], $_SESSION['nom'], $_SESSION['prenom']) ? '../traitement/admin_t.php?deconnecter=true' : 'index.php?form=login' ?>"
                           id="navbarDropdown" role="button"
                           data-bs-toggle="" aria-expanded="false">
                            <?= isset($_SESSION['id'], $_SESSION['email'], $_SESSION['nom'], $_SESSION['prenom']) ? 'Se deconnecter' : 'Se connecter' ?>
                        </a>
                    </li>
                    <!--                <li class="nav-item">-->
                    <!--                    <a class="nav-link disabled" href="#" tabindex="-1" aria-disabled="true">Disabled</a>-->
                    <!--                </li>-->
                </ul>
            </div>

            <form class="d-flex">
                <span class="h3 me-3"><?php echo $_SESSION['libele_aep'];?> </span>
                <input class="form-control me-2" type="search" id="le_input" placeholder="Search" aria-label="Search">
                <button class="btn btn-outline-success" onclick="imprimer()" type="button">Imprmer</button>
            </form>
        <?php endif; ?>
    </div>

</nav>