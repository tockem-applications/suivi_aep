<?php

function display_modal($id_modal, $traitement)
{
    ?>
    <!-- Modal de Confirmation de Suppression -->
    <div class="modal fade" id="sortir_locataire_modal" tabindex="-1" role="dialog" aria-labelledby="deleteModalLabel"
        aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="deleteModalLabel">Confirmation de l'arret de contrat</h5>
                    <button type="button" class="close" data-bs-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">

                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <!--                    <a href="?traitement=appartement_t.php&sortir_locataire=true&id_locataire=-->
                    <?php //echo $id_locataire;
                        ?><!--&id_appartement=--><?php //echo htmlspecialchars($id_appartement);
                            ?><!--"-->
                    <!--                       class="btn btn-danger" id="confirmDelete">sortir</a>-->
                </div>
            </div>
        </div>
    </div>
    <?php
}

function display_delete_modal($titre, $body, $traitement, $id_modal = 'deleteModalLabel')
{
    ?>
    <!-- Modal de Confirmation de Suppression -->
    <div class="modal fade" id="<?php echo $id_modal; ?>" tabindex="-1" role="dialog"
        aria-labelledby="<?php echo $id_modal; ?>" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="deleteModalLabel"><?php echo $titre ?></h5>
                    <button type="button" class="close" data-bs-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div><?php echo $body ?></div>
                    <p class="text-danger">Attenton, cette action sera irreversible !</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <a href="<?php echo $traitement; ?>" class="btn btn-danger" id="confirmDelete">Suprimer</a>
                </div>
            </div>
        </div>
    </div>
    <?php
}


function genererGraphiques($dataArray)
{
    // Initialiser des tableaux pour stocker les données graphiques
    $mois = array();
    $consommation = array();
    $nombreFactures = array();
    $montantFacture = array();
    $montantRecouvert = array();
    $tauxRecouvrement = array();
    $reverse_data_array = array_reverse($dataArray);
    // Traiter le tableau d'entrée
    foreach ($reverse_data_array as $entry) {
        $mois[] = $entry['month'];
        $consommation[] = array('label' => $entry['month'], "y" => $entry['data']['consommation']);
        $nombreFactures[] = array('label' => $entry['month'], "y" => $entry['data']['nombre de factures']);
        $montantFacture[] = array('label' => $entry['month'], "y" => $entry['data']['montant facturé']);
        $montantRecouvert[] = array('label' => $entry['month'], "y" => $entry['data']['montant recouvert']);
        $tauxRecouvrement[] = array('label' => $entry['month'], "y" => $entry['data']['Taux de recouvrement']);
    }

    // Convertir les données en JSON pour les utiliser dans JavaScript
    $moisJSON = json_encode($mois);
    $consommationJSON = json_encode($consommation);
    $nombreFacturesJSON = json_encode($nombreFactures);
    $montantFactureJSON = json_encode($montantFacture);
    $montantRecouvertJSON = json_encode($montantRecouvert);
    $tauxRecouvrementJSON = json_encode($tauxRecouvrement);
    $type_chart = 'line';
    //    var_dump($nombreFacturesJSON);
    echo "
        <div class='card ' >
            <h2 class='h2 d-flex justify-content55.json-center'>Tableau de bord</h2>
            <div class='card-body row'>
                <div id='reseau_chart1' class=' mt-3 col-12 col-md-6' style='height: 250px'></div>
                <div id='reseau_chart2' class=' mt-3 col-12 col-md-6' style='height: 250px'></div>
                <div id='reseau_chart3' class=' mt-3 col-12 col-md-6' style='height: 250px'></div>
                <div id='reseau_chart4' class=' mt-3 col-12 col-md-6' style='height: 250px'></div>
            </div>
        </div>
        <script type='text/javascript'>

    
        
        displayDoubleLineChart('reseau_chart1', 'facturation / recouvrement', '$montantFactureJSON' , '$montantRecouvertJSON' , '$type_chart', '$type_chart', 'montant facturé', 'montant recouvert')
        displayDoubleLineChart('reseau_chart2', 'Taux de reouvrement', '$tauxRecouvrementJSON' , '$tauxRecouvrementJSON' , '$type_chart', '$type_chart', 'montant facturé', 'montant recouvert')
        displayDoubleLineChart('reseau_chart3', 'Nombre de factures', '$nombreFacturesJSON' , '$nombreFacturesJSON' , '$type_chart', '$type_chart', 'montant facturé', 'montant recouvert')
        displayDoubleLineChart('reseau_chart4', 'Consommation', '$consommationJSON' , '$consommationJSON' , '$type_chart', '$type_chart', 'montant facturé', 'montant recouvert')
        


        </script>";



}