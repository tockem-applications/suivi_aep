<?php


function make_Modal($titre, $codeHtml, $tab_index = -1, $identifiant = 'my_form', $action = '', $close_color = 'danger')
{
    ob_start()
    ?>
    <div class="modal fade " tabindex="<?php echo $tab_index ?>" id="<?php echo $identifiant ?>" role="dialog"
         aria-labelledby="deleteModalLabel"
         aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <span class="h2"><?php echo $titre ?></span>
                </div>
                <!--                        tetetet-->
                <div class="modal-body overflow-y-auto" style="max-height: 70vh;  ">
                    <?php echo $codeHtml ?>
                </div>
                <div class="modal-footer d-flex">
                    <button type="reset" class="btn btn-<?php echo $close_color ?> me-3" data-bs-dismiss="modal">
                        Fermer
                    </button>
                    <?php echo $action ?>
                </div>
            </div>

        </div>

    </div>
    <?php

    return ob_get_clean();
}

function make_form($traitement, $titre, $codeHtml, $tab_index = -1, $identifiant = 'my_form')
{
    ob_start()
    ?>
    <div class="modal fade" tabindex="<?php echo $tab_index ?>" id="<?php echo $identifiant ?>" role="dialog"
         aria-labelledby="deleteModalLabel"
         aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <form method="post" action="<?php echo $traitement ?>" enctype="multipart/form-data">
                    <div class="modal-header">
                        <span class="h2"><?php echo $titre ?></span>
                    </div>
                    <!--                        tetetet-->
                    <div class="p-4 modal-body">
                        <div>
                            <?php
                            echo $codeHtml;
                            ?>
                        </div>
                    </div>

                    <div class="modal-footer" style="display:flex;">
                        <button type="reset" class="btn btn-danger me-3" data-bs-dismiss="modal">Annuler</button>
                        <button type="submit" class="btn btn-primary">Valider</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <?php

    return ob_get_clean();
}

function create_accordeon($titre, $html_body, $expande, $id)
{
    return '
            <div class="accordion-item">
                    <h2 class="accordion-header">
                        <button class="accordion-button ' . ($expande == 'false' ? 'collapsed' : '') . '" type="button" data-bs-toggle="collapse"
                                data-bs-target="#collapse_index_' . $id . '" aria-expanded="' . $expande . '" aria-controls="collapse_index_' . $id . '">
                            ' . $titre . '
                        </button>
                    </h2>
                    <div id="collapse_index_' . $id . '" class="accordion-collapse collapse ' . ($expande == 'true' ? 'show' : '') . '" data-bs-parent="#accordionExample">
                        <div class="accordion-body">
                           ' . $html_body . '
                        </div>
                    </div>
            </div>
            ';
}

if (isset($_GET['list'])) {
//    var_dump($_SESSION);
    if ($_GET['list'] == 'abone_simple') {
        require_once('traitement/abone_t.php');
        Abone_t::getListeAboneSimple();

    }
    if ($_GET['list'] == 'distribution_simple') {
        require_once('traitement/abone_t.php');
        Abone_t::getListeAboneSimple('distribution');

    }
    if ($_GET['list'] == 'production_simple') {
        require_once('traitement/abone_t.php');
        Abone_t::getListeAboneSimple('production');

    } elseif ($_GET['list'] == 'liste_facture_month') {
        require_once("traitement/facture_t.php");
        if (isset($_GET["id_selected_month"]))
            Facture_t::getListeFactureByMoisId();
    } elseif ($_GET['list'] == 'ajout_abones') {
        require_once("traitement/abone_t.php");
        $data = Abone_t::getData();
        var_dump($data);

    } elseif ($_GET['list'] == 'transaction') {
        require_once("traitement/flux_financier_t.php");
//        var_dump('oosodooooooooooooo');
        Flux_financier_t::afficheFluxFinancier();
//        var_dump($data);

    } elseif ($_GET['list'] == 'insolvables') {
        require_once("traitement/facture_t.php");
        if (isset($_GET["id_selected_month"]))
            Facture_t::getListeFactureByMoisId();
    } elseif ($_GET['list'] == 'releve_indexx') {
        require_once("traitement/facture_t.php");
        if (isset($_GET["id_selected_month"]))
            Facture_t::getListeFactureByMoisId();
    } else if ($_GET['list'] == 'facture_month') {
        require_once("traitement/mois_facturation_t.php");
        require_once("traitement/facture_t.php");
        ?>
        <div class="container d-flex align-items-center justify-content-center pt-5 ">
            <div class="text-center col-10 col-md-7 col-lg-6 col-xl-5 ">
                <form method="post" action="traitement/mois_facturation_t.php?&get_mois_facturation=true">
                    <h2>Selectionner le mois de facturation</h2>
                    <hr>
                    <div class="">
                        <div class="input-group mb-3">
                            <span class="input-group-text w-25" id="inputGroup-sizing-default">Mois</span>
                            <select name="mois_facturation" class="form-select " aria-label="Sizing example input"
                                    aria-describedby="inputGroup-sizing-default" id="">
                                <option value="">Choisissez une option...</option>
                                <?php
                                if (isset($_GET["id_selected_month"]))
                                    MoisFacturation_t::getoption($_GET["id_selected_month"]);
                                else
                                    MoisFacturation_t::getoption();
                                ?>
                                <!-- <option value="non_actif">Non Actif</option> -->
                            </select>
                        </div>
                        <div class="input-group mb-3">
                            <span class="input-group-text w-25">Date de depot</span>
                            <input type="date" value="<?php echo date('Y-m-d') ?>" name="date_depot"
                                   data-bs-toggle="tooltip"
                                   data-bs-placement="right" class="form-control"
                                   data-bs-title="Il s'agit de la date du jour ou vous deposerez les facture. Ce cera aujourd'hui ci vous ne le replissez pas.">
                        </div>


                        <div style="display:flex;">
                            <button type="submit" class="btn btn-primary">Facturer</button>
                        </div>
                    </div>

                </form>
            </div>
        </div>
        <?php
        //if(isset($_GET["id_selected_month"]))
        if (isset($_GET["id_selected_month"])) {
            //Facture_t::getTableauFactureByMoisId();
        }

    } else if ($_GET['list'] == 'releve_manuelle') {
        include_once("traitement/facture_t.php");
        require_once("traitement/mois_facturation_t.php");
        ?>
        <div class="container d-flex align-items-center justify-content-center pt-5 ">
            <div class="text-center col-10 col-md-7 col-lg-6 col-xl-5 ">
                <form method="post" action="?list=releve_manuelle">
                    <h2>Selectionner le mois de facturation</h2>
                    <hr>
                    <div class="">
                        <div class="input-group mb-3">
                            <span class="input-group-text w-25" id="inputGroup-sizing-default">Mois</span>
                            <select name="mois_facturation" class="form-select " aria-label="Sizing example input"
                                    aria-describedby="inputGroup-sizing-default" id="">
                                <option value="">Choisissez une option...</option>
                                <?php
                                if (isset($_GET["id_selected_month"]))
                                    MoisFacturation_t::getoption($_GET["id_selected_month"]);
                                else
                                    MoisFacturation_t::getoption();
                                ?>
                                <!-- <option value="non_actif">Non Actif</option> -->
                            </select>
                            <button type="submit" class="input-group-text btn btn-primary">Afficher</button>
                        </div>
                    </div>

                </form>
            </div>
        </div>
        <?php
        if (isset($_POST["mois_facturation"])) {
            Facture_t::getTableauFactureactiveForReleve($_POST["mois_facturation"]);
        }
        //echo $id_mois_listing;
    } else if ($_GET['list'] == 'mois_facturation') {
        include_once("traitement/mois_facturation_t.php");
        //echo $id_mois_listing;
        MoisFacturation_t::getListeMoisFacture();
    } else if ($_GET['list'] == 'recouvrement') {
        // require_once '../traitement/locataire_t.php';
        // Locataire_t::getAll('Liste Des Locataires');
        include_once("traitement/facture_t.php");
        require_once("traitement/mois_facturation_t.php");
        $id_mois_listing = 0;
        if (isset($_GET["id_selected_month"]))
            $id_mois_listing = $_GET['id_selected_month']
        ?>
        <div class="container mt-3 d-flex justify-content-center">
            <div class="row">
                <form action="?" method="GET" class="">
                    <input type="hidden" name="list" value="recouvrement">
                    <div class="input-group">
                        <span class="input-group-text">Mois de facturation</span>
                        <select name="id_selected_month" class="form-select" id="">
                            <option value="">Veuillez choisir un mois</option>
                            <?php
                            MoisFacturation_t::getOnlyOption($id_mois_listing);
                            ?>
                        </select>
                        <button type="submit" class="btn-primary">Afficher</button>
                    </div>
                </form>
            </div>
        </div>
        <?php
        //echo $id_mois_listing;

        $id_mois_listing = Facture_t::getTableauFactureByMoisId($id_mois_listing);
        //echo $id_mois_listing;
//        echo "<div class='col-12'><a class='btn-primary btn w-25' href='?list=liste_facture_month&id_constante=0&id_mois=$id_mois_listing&id_selected_month=$id_mois_listing'>Facturer</a></div>";
        ?>

        <?php
    } else if ($_GET['list'] == 'proprietaire') {
        require_once '../traitement/proprietaire_t.php';
        Proprietaire_t::getAll('Liste Des Prooprietaires');
    } else if ($_GET['list'] == 'tarif') {
        require_once '../traitement/tarif_t.php';
        tarif_t::getAll('Liste Des Tarifs');
    } else if ($_GET['list'] == 'cle') {
        if ($_SESSION['id'] == '1') {
            require_once '../traitement/admin_t.php';
            Admin_t::getAllCle();
        } else {
            header("location: ../presentation/index.php?form=login");
        }
    }
} elseif (isset($_GET['page'])) {
    if ($_GET['page'] == 'info_abone') {
        include_once('traitement/abone_t.php');
        $id_abone = 0;
        if (isset($_GET['id'])) {
            $id_abone = (int)$_GET['id'];
        }


        ?>
        <div class="row">
            <article
                    class="col-12 col-sm-12 col-md-12 col-xl-5 col-xxl-4 border-3 border-top-0 border-bottom-0 border-start-0">
                <a href="?list=abone_simple" class="btn btn-primary ">< Liste des abones </a>
                <div class="me-2">
                    <?php Abone_t::afficheInfoAbone($id_abone); ?>
                </div>
            </article>

            <aside class=" col-xl-7">
                <?php
                echo Abone_t::afficheInputRecouvrementAbone($id_abone);
                ?>
                <!--                <div class="col-12 h-5"> Est ce aue tout le meonde va bien</div>-->
            </aside>
        </div>
        <?php

        //Abone_t::getListeAboneSimple();
    } else if ($_GET['page'] == 'tarif') {

        include_once('traitement/constante_reseau_t.php');
        ?>
        <a href="?form=constante_reseau">modifier les tarifs</a>
        <?php
    }else if ($_GET['page'] == 'home') {
        include_once('presentation/home.php');
    } else if ($_GET['page'] == 'proprietaire') {
        require_once '../traitement/proprietaire_t.php';
        Proprietaire_t::getAll('Liste Des Prooprietaires');
    } else if ($_GET['page'] == 'tarif') {
        require_once '../traitement/tarif_t.php';
        tarif_t::getAll('Liste Des Tarifs');
    } else if ($_GET['page'] == 'cle') {
        if ($_SESSION['id'] == '1') {
            require_once '../traitement/admin_t.php';
            Admin_t::getAllCle();
        } else {
            header("location: ../presentation/index.php?form=login");
        }
    } else {
        echo "<strong>Erreur 404: La pae que vous recherchez n'existe pas</strong>";
    }

} elseif (isset($_GET['action'])) {
    if ($_GET['action'] == 'export_index') {
        include_once('traitement/abone_t.php');
        Abone_t::getJsonDataToExport();
    } else {
        echo "<strong>Erreur 404: La pae que vous recherchez n'existe pas</strong>";
    }

} else if (count($_GET) == 0) {
    header("location: index.php?page=home");
}

?>
