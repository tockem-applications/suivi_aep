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
        // Les données sont déjà numériques (voir afficherStatistiqueReseau ligne 96)
        $consommation[] = floatval($entry['data']['consommation']);
        $nombreFactures[] = intval($entry['data']['nombre de factures']);
        $montantFacture[] = floatval($entry['data']['montant facturé']);
        $montantRecouvert[] = floatval($entry['data']['montant recouvert']);
        // Pour le taux de recouvrement, gérer le cas "-" (pas de données)
        $tauxRecouv = $entry['data']['Taux de recouvrement'];
        $tauxRecouvrement[] = ($tauxRecouv === '-' || $tauxRecouv === null) ? 0 : floatval($tauxRecouv);
    }

    // Convertir les données en JSON pour les utiliser dans JavaScript
    $moisJSON = json_encode($mois);
    $consommationJSON = json_encode($consommation);
    $nombreFacturesJSON = json_encode($nombreFactures);
    $montantFactureJSON = json_encode($montantFacture);
    $montantRecouvertJSON = json_encode($montantRecouvert);
    $tauxRecouvrementJSON = json_encode($tauxRecouvrement);
    
    echo "
        <!-- Chart.js -->
        <script src=\"https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js\"></script>
        
        <div class='card'>
            <h2 class='h2 d-flex justify-content-center mb-4'>Tableau de bord</h2>
            <div class='card-body row'>
                <div class='mt-3 col-12 col-md-6'>
                    <canvas id='reseau_chart1' style='max-height: 300px;'></canvas>
                </div>
                <div class='mt-3 col-12 col-md-6'>
                    <canvas id='reseau_chart2' style='max-height: 300px;'></canvas>
                </div>
                <div class='mt-3 col-12 col-md-6'>
                    <canvas id='reseau_chart3' style='max-height: 300px;'></canvas>
                </div>
                <div class='mt-3 col-12 col-md-6'>
                    <canvas id='reseau_chart4' style='max-height: 300px;'></canvas>
                </div>
            </div>
        </div>
        <script type='text/javascript'>
        (function() {
            var labels = $moisJSON;
            
            // Graphique 1: Facturation / Recouvrement (DEUX courbes)
            var ctx1 = document.getElementById('reseau_chart1');
            if (ctx1) {
                new Chart(ctx1.getContext('2d'), {
                    type: 'line',
                    data: {
                        labels: labels,
                        datasets: [
                            {
                                label: 'Montant facturé',
                                data: $montantFactureJSON,
                                borderColor: 'rgba(54, 162, 235, 1)', // Bleu
                                backgroundColor: 'rgba(54, 162, 235, 0.1)',
                                tension: 0.4,
                                borderWidth: 3,
                                pointRadius: 4,
                                pointHoverRadius: 6
                            },
                            {
                                label: 'Montant recouvert',
                                data: $montantRecouvertJSON,
                                borderColor: 'rgba(255, 99, 132, 1)', // Rouge/Rose
                                backgroundColor: 'rgba(255, 99, 132, 0.1)',
                                tension: 0.4,
                                borderWidth: 3,
                                pointRadius: 4,
                                pointHoverRadius: 6
                            }
                        ]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: true,
                        plugins: {
                            title: {
                                display: true,
                                text: 'Facturation / Recouvrement'
                            },
                            legend: {
                                display: true,
                                position: 'top'
                            }
                        },
                        scales: {
                            y: {
                                beginAtZero: true
                            }
                        }
                    }
                });
            }
            
            // Graphique 2: Taux de recouvrement (UNE courbe)
            var ctx2 = document.getElementById('reseau_chart2');
            if (ctx2) {
                new Chart(ctx2.getContext('2d'), {
                    type: 'line',
                    data: {
                        labels: labels,
                        datasets: [
                            {
                                label: 'Taux de recouvrement (%)',
                                data: $tauxRecouvrementJSON,
                                borderColor: 'rgba(255, 99, 132, 1)',
                                backgroundColor: 'rgba(255, 99, 132, 0.1)',
                                tension: 0.4,
                                borderWidth: 2,
                                fill: true
                            }
                        ]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: true,
                        plugins: {
                            title: {
                                display: true,
                                text: 'Taux de recouvrement'
                            },
                            legend: {
                                display: true,
                                position: 'top'
                            }
                        },
                        scales: {
                            y: {
                                beginAtZero: true,
                                ticks: {
                                    callback: function(value) {
                                        return value + '%';
                                    }
                                }
                            }
                        }
                    }
                });
            }
            
            // Graphique 3: Nombre de factures (UNE courbe)
            var ctx3 = document.getElementById('reseau_chart3');
            if (ctx3) {
                new Chart(ctx3.getContext('2d'), {
                    type: 'line',
                    data: {
                        labels: labels,
                        datasets: [
                            {
                                label: 'Nombre de factures',
                                data: $nombreFacturesJSON,
                                borderColor: 'rgba(153, 102, 255, 1)',
                                backgroundColor: 'rgba(153, 102, 255, 0.1)',
                                tension: 0.4,
                                borderWidth: 2,
                                fill: true
                            }
                        ]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: true,
                        plugins: {
                            title: {
                                display: true,
                                text: 'Nombre de factures'
                            },
                            legend: {
                                display: true,
                                position: 'top'
                            }
                        },
                        scales: {
                            y: {
                                beginAtZero: true
                            }
                        }
                    }
                });
            }
            
            // Graphique 4: Consommation (UNE courbe)
            var ctx4 = document.getElementById('reseau_chart4');
            if (ctx4) {
                new Chart(ctx4.getContext('2d'), {
                    type: 'line',
                    data: {
                        labels: labels,
                        datasets: [
                            {
                                label: 'Consommation (m³)',
                                data: $consommationJSON,
                                borderColor: 'rgba(255, 159, 64, 1)',
                                backgroundColor: 'rgba(255, 159, 64, 0.1)',
                                tension: 0.4,
                                borderWidth: 2,
                                fill: true
                            }
                        ]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: true,
                        plugins: {
                            title: {
                                display: true,
                                text: 'Consommation'
                            },
                            legend: {
                                display: true,
                                position: 'top'
                            }
                        },
                        scales: {
                            y: {
                                beginAtZero: true,
                                ticks: {
                                    callback: function(value) {
                                        return value + ' m³';
                                    }
                                }
                            }
                        }
                    }
                });
            }
        })();
        </script>";



}