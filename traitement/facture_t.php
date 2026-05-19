<?php
@include_once("../donnees/facture.php");
@include_once("donnees/impaye.php");
@include_once("../donnees/impaye.php");
@include_once("donnees/facture.php");
@include_once("../donnees/mois_facturation.php");
@include_once("donnees/mois_facturation.php");
@include_once("../traitement/abone_t.php");
@include_once("traitement/abone_t.php");
@include_once("../traitement/aep_t.php.php");
@include_once("traitement/aep_t.php.php");

class Facture_t
{


    public static function getTableauFactureactiveForReleve($id_mois, $titre = 'Releve des nouveaux index:')
    {
        $mois_lettre = '';
        //        if()

        $id_mois_actif = MoisFacturation::getIdMoisFacturationActive($_SESSION['id_aep']);
        $editable = $id_mois == $id_mois_actif || $id_mois == 0;
        if ($id_mois == 0) {
            $mois = MoisFacturation::getMoisFacturationActive($_SESSION['id_aep']);
            $mois = $mois->fetchAll();
            if (count($mois)) {
                $id_mois = (int) $mois[0]['id'];
                $mois_lettre = getLetterMonth($mois[0]['mois']);
            }
        } else {

            $mois = MoisFacturation::getOneById((int) $id_mois);
            $mois = $mois->fetchAll();
            //  var_dump($mois);
            //$id_mois = (int) $mois[0]['id'];
            $mois_lettre = getLetterMonth($mois[0]['mois']);
        }
        //recuperation des index des abones
//        $req = Facture::getAboneMonthIndexes((int)$id_mois, $_SESSION['id_aep']);
//        $req = $req->fetchAll(PDO::FETCH_ASSOC);

        // recperation des index des reseaux
        $req2 = Facture::getMonthIndexes((int) $id_mois, $_SESSION['id_aep']);
        $req2 = $req2->fetchAll(PDO::FETCH_ASSOC);
        //        var_dump($req2);
        create_csv_exportation_button(
            $req2,
            'Releve-' . $_SESSION["libele_aep"] . '-' . $mois_lettre . '.csv',
            'Vous allez exporter les donnees de releve de ' . $mois_lettre . 'au format csv'
        );

        $titre = "<div class='d-flex justify-content-around'>$titre <div> $mois_lettre</div></div>";
        $mes_facture = "";
        ob_start();
        ?>

        <style>
            /* Masquer les flèches d'incrémentation dans les navigateurs modernes */
            input[type='number']::-webkit-inner-spin-button,
            input[type='number']::-webkit-outer-spin-button {
                -webkit-appearance: none;
                margin: 0;
            }

            /* Masquer les flèches dans Firefox */
            input[type='number'] {
                -moz-appearance: textfield;
            }
        </style>
        <tr>
            <th>N° compteur</th>
            <th>Nom et Prenom</th>
            <th>Ancien index</th>
            <th>nouvel index</th>
        </tr>
        <?php
        self::creerLigneTableauReleveManuelle($req2);
        //            self::creerLigneTableauReleveManuelle($req);
        echo '<a class=dropdown-item" href="?form=abone"> Ajouter un aboné</a>';
        $codeHtml = ob_get_clean();
        self::createTable($codeHtml, $titre, ""/*"<a href='traitement/moisfacturation_t.php?action=export_index' target='_blank'>Telecharger les index</a><br>"*/);



        ?>


        <br>

        </div>

        <?php
        //echo $mes_facture;
        return $id_mois;
    }
    public static function creerLigneTableauReleveManuelle($req)
    {
        foreach ($req as $data) {
            $circle_bg_color = '';
            if ((float) $data['ancien_index'] > (float) $data['nouvel_index'])
                $circle_bg_color = 'bg-danger';
            elseif (((float) $data['ancien_index'] == (float) $data['nouvel_index']))
                $circle_bg_color = 'bg-warning';
            elseif ((float) $data['ancien_index'] < (float) $data['nouvel_index'])
                $circle_bg_color = 'bg-success';
            ?>

            <tr class="p-0 m-0">
                <td><?php echo $data['numero_compteur'] ?> </td>
                <td> <?php echo $data['nom'] ?> </td>
                <td id="ancien_index<?php echo $data['id'] ?>"> <?php echo $data['ancien_index'] ?></td>
                <td class="w-auto d-flex justify-content-between align-items-center">
                    <input type="number" class="form-control w-50 border-0 p-0 ps-2 " style="background-color: rgba(0, 0, 0, 0)"
                        id="nouvel_index<?php echo $data['id'] ?>" min="<?php echo $data['ancien_index'] ?>" onclick="this.select()"
                        onchange="handleReleve(this.value, <?php echo $data['id'] ?>, <?php echo $data['id_compteur'] ?>)"
                        step="0.01"
                        value="<?php echo ((float) $data['nouvel_index'] == 0 || (float) $data['nouvel_index'] == (float) $data['ancien_index'] ? '' : $data['nouvel_index']) ?>"
                        aria-label="Sizing example input" aria-describedby="inputGroup-sizing-lg">
                    <div class="color-circle <?php echo $circle_bg_color ?>"></div>
                    <input type="hidden" value="<?php echo $data['nouvel_index'] ?>" id="ex_nouvel_index<?php echo $data['id'] ?>">
                </td>
                <!--  onkeyup="handleReleve_pressed_enter(event, this.value, <?php echo $data['id'] ?>//, <?php echo $data['id_compteur'] ?>//)"              -->
            </tr>
            <?php
        }
    }




    /**
     * Génère un tableau HTML des factures pour un mois donné.
     *
     * @param int $idMois Identifiant du mois de facturation (0 pour le mois actif)
     * @param string $titre Titre du tableau
     * @param int $idReseau Identifiant du réseau (0 pour tous les réseaux)
     * @param int $idAep Identifiant AEP depuis la session
     * @return int Identifiant du mois traité
     */
    public static function getTableauFactureByMoisId($idAep, $idMois = 0, $titre = 'Liste des recouvrements : ', $idReseau = 0, $selected_option = 'vide', $selectedReseau = 0)
    {
        // Récupérer le mois actif si $idMois est 0
        $idMoisActif = MoisFacturation::getIdMoisFacturationActive($idAep);
        $editable = ($idMois == $idMoisActif);

        // Déterminer le mois à utiliser
        $moisData = self::getMoisData($idMois, $idAep);
        if (empty($moisData)) {
            return 0; // Retourner 0 si aucune donnée de mois n'est disponible
        }

        $idMois = (int) $moisData['id'];
        $moisLettre = self::getLetterMonth($moisData['mois']);
        $titre .= " $moisLettre";

        // Récupérer les factures pour le mois
        $factures2 = Facture::getMonthFacture2($idMois, (int) $idAep, $selectedReseau)->fetchAll(PDO::FETCH_ASSOC);

        create_csv_exportation_button(
            $factures2,
            'facturation-' . $_SESSION["libele_aep"] . '_' . self::getLetterMonth($moisData["mois"]) . '.csv',
            "Vous allez exporter les donnees de facturation de " . self::getLetterMonth($moisData["mois"]) . 'au format csv'
        );

        // Générer le HTML du tableau
        $html = self::generateTableHtml2($factures2, $idReseau, $editable, $selected_option);

        // Afficher le tableau
        self::createTable($html, $titre);
        return $idMois;
    }


    /**
     * Récupère les données du mois de facturation.
     *
     * @param int $idMois Identifiant du mois
     * @param int $idAep Identifiant AEP
     * @return array Données du mois ou tableau vide si non trouvé
     */
    private static function getMoisData($idMois, $idAep)
    {
        $moisQuery = ($idMois === 0)
            ? MoisFacturation::getMoisFacturationActive($idAep)
            : MoisFacturation::getOneById($idMois);

        $mois = $moisQuery->fetchAll();
        return !empty($mois) ? $mois[0] : array();
    }

    /**
     * Convertit un numéro de mois en son nom en lettres.
     *
     * @param int $mois Numéro du mois
     * @return string Nom du mois en lettres
     */
    private static function getLetterMonth($mois)
    {
        // Assurez-vous que la fonction getLetterMonth est définie ailleurs
        return getLetterMonth($mois);
    }

    /**
     * Génère le HTML du tableau des factures.
     *
     * @param array $factures Liste des factures
     * @param int $idReseau Identifiant du réseau
     * @param bool $editable Si le tableau est éditable
     * @return string HTML du tableau
     */
    private static function generateTableHtml($factures, $idReseau, $editable, $insolvable = false, $bon_payeurs = false, $avanceur = false)
    {
        //        var_dump($bon_payeurs, $avanceur, $insolvable);
        ob_start();
        ?>

        <table class="table_searching table table-striped table-bordered table-hover">
            <thead class="table-dark">
                <tr>
                    <!--                    <th>Id</th>-->
                    <th>Nom et Prénoms</th>
                    <!-- <th>Réseau</th> -->
                    <th>Index</th>
                    <th>Conso</th>
                    <th>Impayé</th>
                    <th>Facture</th>
                    <th>Total</th>
                    <th>Reste</th>
                    <th>Versement</th>
                    <th>Avance</th>
                    <!--                    <th>Date</th>-->
                    <!--                    <th>Action</th>-->
                </tr>
            </thead>
            <tbody>
                <?php foreach ($factures as $data) {
                    if ($idReseau !== 0 && $data['id_reseau'] !== $idReseau) {
                        continue;
                    }

                    $consoMois = Facture::calculeConso((float) $data['nouvel_index'], (float) $data['ancien_index']);
                    $montantConso = Facture::calculeMontantConso((int) $data['nouvel_index'], (int) $data['ancien_index'], $data['prix_metre_cube_eau']);
                    $montantVerse = (int) ($data['montant_verse'] + 0.000000001);
                    $avance = (int) ($data['impaye2'] - 0.00001);
                    $montantTotal = (int) (Facture::calculeMontantTotal(
                        $data['nouvel_index'],
                        $data['ancien_index'],
                        $data['prix_tva'],
                        $data['prix_entretient_compteur'],
                        $data['prix_metre_cube_eau'],
                        $data['impaye'],
                        $data['penalite']
                    ) + 0.000001);
                    $montantTva = Facture::calculeMontantConsoTva(
                        $data['nouvel_index'],
                        $data['ancien_index'],
                        $data['prix_tva'],
                        $data['prix_entretient_compteur'],
                        $data['prix_metre_cube_eau']
                    );
                    $montantFacture = (int) ($montantTva + 0.000000001);
                    $montantRestant = Facture::calculeMontantRestant(
                        $data['nouvel_index'],
                        $data['ancien_index'],
                        $data['prix_tva'],
                        $data['prix_entretient_compteur'],
                        $data['prix_metre_cube_eau'],
                        $data['impaye'],
                        $data['penalite'],
                        $montantVerse
                    );


                    $categorie_payeur = '';
                    if ($montantRestant == 0)
                        $categorie_payeur = 'bg-success';
                    if ($montantRestant > 0 && $montantRestant < $montantTotal)
                        $categorie_payeur = 'bg-warning';
                    if ($montantRestant == $montantTotal)
                        $categorie_payeur = 'bg-danger';
                    //                    if($montantRestant == 0)
//                        $categorie_payeur = '';
        


                    if ($insolvable && $montantRestant != $montantTotal) {
                        continue;
                    }
                    if ($bon_payeurs && $montantRestant != 0) {
                        continue;
                    }
                    //                    var_dump(($avanceur && ($montantRestant > 0 && $montantRestant < $montantTotal)));
//                    echo "$montantRestant < $montantTotal";
                    if ($avanceur && !($montantRestant > 0 && $montantRestant < $montantTotal)) {
                        //                        var_dump($avanceur, 'llllllll');
                        continue;
                    }
                    $disabled = ($montantFacture === $montantVerse || (int) $data['impaye'] > 0) ? 'disabled' : '';
                    $placeholder = (int) $data['impaye'] > 0 ? 'Veuillez verser les impayés' : '';
                    ?>
                    <tr>
                        <!--                        <td>--><?php //echo htmlspecialchars($data['id_compteur']); ?><!--</td>-->
                        <td>
                            <?php
                            $modalId = 'recouvrement_Form_' . $data['id_compteur'];
                            echo make_Modal(
                                $data['nom'],
                                Abone_t::afficheInputRecouvrementAbone($data['id_compteur']),
                                -1,
                                $modalId,
                                ''
                            );
                            ?>
                            <a data-bs-toggle="modal" data-bs-target="#<?php echo $modalId; ?>">
                                <?php echo htmlspecialchars(strlen($data['nom']) > 28 ? substr($data['nom'], 0, 24) . '...' : $data['nom']); ?>
                            </a>
                        </td>
                        <td><?php echo htmlspecialchars($data['ancien_index'] . ' - ' . $data['nouvel_index']); ?></td>
                        <!--                        <td>--><?php //echo htmlspecialchars($data['premier_index'] ); ?><!--</td>-->
                        <td><?php echo htmlspecialchars($consoMois); ?></td>
                        <td><?php echo htmlspecialchars((int) $data['impaye']); ?></td>
                        <td><?php echo htmlspecialchars($montantTva); ?></td>
                        <td><?php echo htmlspecialchars($montantTotal); ?></td>
                        <td class="  <?php echo $categorie_payeur; ?> "><?php echo htmlspecialchars($montantRestant); ?></td>
                        <td class="pt-0 pb-0 ">
                            <input type="text" class="form-control p-1 <?php echo $disabled; ?> feedback-validation" <?php echo $disabled; ?>
                                onchange="handleRecouvrement(this.value, <?php echo $placeholder === '' ? $data['id'] : 0; ?>, this.id)"
                                value="<?php echo $placeholder === '' ? ($montantVerse === 0 ? '' : $montantVerse) : $placeholder; ?>"
                                id="montant_verse<?php echo $data['id']; ?>">
                            <!--                            <div class="circle bg-danger "></div>-->
                        </td>
                        <td><?php echo htmlspecialchars(min($avance, 0)); ?></td>
                        <!--                        <td>--><?php //echo  ?><!--</td>-->
                        <td class="d-none">
                            <input type="text" class="form-control " id="date_releve_facture_<?php echo $data['id']; ?>"
                                value="<?php echo date('d/m/Y'); ?>">
                        </td>
                        <!--                        <td><a href="#" class="btn btn-info mb-0">Valider</a></td>-->
                    </tr>
                <?php } ?>
            </tbody>
        </table>
        <?php
        return ob_get_clean();
    }

    private static function generateTableHtml2($factures, $idReseau, $editable, $selected_option)
    {
        //        var_dump($bon_payeurs, $avanceur, $insolvable);
        ob_start();
        ?>
        <div class="card shadow-sm">
            <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                <h4 class="mb-0"><i class="bi bi-people"></i> Recouvrent
                    <span class="badge bg-light text-dark ms-2"><?php echo count($factures); ?></span>
                </h4>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table_searching table table-striped table-bordered table-hover table-sm mb-0">
                        <thead class="table-dark">
                            <tr>
                                <th class="text-nowrap">Nom et Prénom</th>
                                <th class="text-center text-nowrap">Réseau</th>
                                <th class="text-center text-nowrap">Index</th>
                                <th class="text-center text-nowrap">Conso<br><small class="text-muted">(m³)</small></th>
                                <th class="text-center text-nowrap">Prix m³<br><small class="text-muted">(FCFA)</small></th>
                                <th class="text-end text-nowrap">Pénalité</th>
                                <th class="text-end text-nowrap">Impayé</th>
                                <th class="text-end text-nowrap">Facture</th>
                                <th class="text-end text-nowrap">Total</th>
                                <th class="text-end text-nowrap">Versement</th>
                                <th class="text-end text-nowrap">Reste</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($factures as $data) {
                                $insolvable = $selected_option == 'insolvable';
                                $partiel = $selected_option == 'paiement_partiel';
                                $solvable = $selected_option == 'solvable';
                                $anticipation = $selected_option == 'anticipation';
                                $en_regle = $selected_option == 'en_regle';
                                $pas_en_regle = $selected_option == 'pas_en_regle';

                                //                            if ($idReseau !== 0 && $data['id_reseau'] !== $idReseau) {
////                                var_dump("cherie");
//                                continue;
//                            }
                    
                                $consoMois = Facture::calculeConso((float) $data['nouvel_index'], (float) $data['ancien_index']);
                                $montantConso = $data['consommation'];
                                $montantVerse = (int) ($data['montant_verse'] + 0.000000001);
                                $avance = (int) ($data['impaye'] - 0.00001);
                                $montantTotal = $data['total_cumule'];
                                $montantTva = $data['montant_conso_tva'];
                                $montantFacture = (int) ($montantTva + 0.000000001);
                                $montantRestant = $data['restant_cumule'];


                                $categorie_payeur = '';
                                if ($montantRestant < 0)
                                    $categorie_payeur = 'anticipation';
                                elseif ($montantRestant > 0 && $montantRestant < $montantTotal)
                                    $categorie_payeur = 'paiement-partiel';
                                elseif ($montantRestant == $montantTotal && $montantRestant != 0)
                                    $categorie_payeur = 'insolvables';
                                elseif ($montantRestant == 0)
                                    $categorie_payeur = 'solvables';
                                //                    if($montantRestant == 0)
//                        $categorie_payeur = '';
                    

                                //                             var_dump(1);
//                             echo "<tr> bobobo</tr>";
                                if ($insolvable && $montantRestant != $montantTotal) {
                                    continue;
                                }
                                //                             var_dump(1);
                                if ($en_regle && $montantRestant > 0) {
                                    continue;
                                }
                                //                             var_dump(1);
                                if ($pas_en_regle && $montantRestant <= 0) {
                                    continue;
                                }
                                //                             var_dump(1);
                                if ($solvable && $montantRestant != 0) {
                                    continue;
                                }
                                //                             var_dump(1);
                                if ($anticipation && $montantRestant >= 0) {
                                    continue;
                                }
                                //                             var_dump(1);
                                if ($partiel && !($montantRestant > 0 && $montantRestant < $montantTotal)) {
                                    continue;
                                }
                                //                             var_dump(1);
                                //                    $disabled = ($montantFacture === $montantVerse || (int)$data['impaye'] > 0) ? 'disabled' : '';
                                $disabled = '';
                                //                    $placeholder = (int)$data['impaye'] > 0 || false ? 'Veuillez verser les impayés' : '';
                                $placeholder = '';
                                ?>
                                <tr id="abone_compteur_id_<?php echo $data['id_compteur'] ?>">
                                    <!--                        <td>--><?php //echo htmlspecialchars($data['id_compteur']); ?><!--</td>-->
                                    <td>
                                        <?php
                                        $modalId = 'recouvrement_Form_' . $data['id_compteur'];
                                        echo make_Modal(
                                            $data['nom_abone'],
                                            Abone_t::afficheInputRecouvrementAbone($data['id_compteur']),
                                            -1,
                                            $modalId,
                                            ''
                                        );
                                        ?>
                                        <a data-bs-toggle="modal" data-bs-target="#<?php echo $modalId; ?>"
                                            style="color: #212529; font-weight: 600;">
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
                                                <span
                                                    class="badge bg-secondary text-white"><?php echo number_format((float) $data['ancien_index'], 2, ',', ' '); ?></span>
                                                <span class="text-muted" style="color: #6c757d;">→</span>
                                                <span
                                                    class="badge bg-primary text-white"><?php echo number_format((float) $data['nouvel_index'], 2, ',', ' '); ?></span>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="text-center">
                                        <strong
                                            style="color: #212529;"><?php echo number_format($consoMois, 2, ',', ' '); ?></strong>
                                    </td>
                                    <td class="text-center">
                                        <strong
                                            style="color: #212529;"><?php echo number_format(isset($data['prix_metre_cube_eau']) ? $data['prix_metre_cube_eau'] : 0, 0, ',', ' '); ?></strong>
                                    </td>
                                    <td class="text-end" style="color: #212529;">
                                        <?php echo htmlspecialchars(Facture::formatFinancier((int) $data['penalite'])); ?>
                                    </td>
                                    <td class="text-end" style="color: #212529;">
                                        <?php echo htmlspecialchars(Facture::formatFinancier((int) $data['impayer_cumule'])); ?>
                                    </td>
                                    <td class="text-end" style="color: #212529;">
                                        <?php echo htmlspecialchars(Facture::formatFinancier($montantTva)); ?>
                                    </td>
                                    <td class="text-end" style="color: #212529;">
                                        <?php echo htmlspecialchars(Facture::formatFinancier($montantTotal)); ?>
                                    </td>

                                    <td class="pt-0 pb-0 text-end">
                                        <?php if ($editable): ?>
                                            <input type="text" class="form-control p-1 <?php echo $disabled; ?> feedback-validation"
                                                <?php echo $disabled; ?>
                                                onchange="handleRecouvrement(this.value, <?php echo $placeholder === '' ? $data['id'] : 0; ?>, this.id)"
                                                value="<?php echo $placeholder === '' ? ($montantVerse === 0 ? '' : $montantVerse) : $placeholder; ?>"
                                                id="montant_verse<?php echo $data['id']; ?>">
                                        <?php else: ?>
                                            <span
                                                style="color: #212529;"><?php echo htmlspecialchars(Facture::formatFinancier($montantVerse)); ?></span>
                                            <!-- Code à exécuter si toutes les conditions précédentes sont fausses -->
                                        <?php endif; ?>
                                        <!--                            <div class="circle bg-danger "></div>-->
                                    </td>
                                    <td class=" text-end <?php echo $categorie_payeur; ?> ">
                                        <?php echo htmlspecialchars(Facture::formatFinancier($montantRestant)); ?>
                                    </td>
                                    <!--                        <td>--><?php //echo htmlspecialchars(min($avance, 0)); ?><!--</td>-->
                                    <!--                        <td>--><?php //echo  ?><!--</td>-->
                                    <td class="d-none">
                                        <input type="text" class="form-control " id="date_releve_facture_<?php echo $data['id']; ?>"
                                            value="<?php echo date('d/m/Y'); ?>">
                                    </td>
                                    <!--                        <td><a href="#" class="btn btn-info mb-0">Valider</a></td>-->
                                </tr>
                            <?php } ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <style>
            .table_searching {
                font-family: 'Inter', 'Segoe UI', 'Roboto', 'Helvetica Neue', Arial, sans-serif;
            }

            .table_searching thead th {
                font-size: 0.875rem;
                font-weight: 600;
                white-space: nowrap;
                vertical-align: middle;
                padding: 0.75rem;
                color: #ffffff !important;
                background-color: #212529;
                border-color: #32383e;
            }

            .table_searching tbody td {
                font-size: 0.9rem;
                padding: 0.75rem;
                vertical-align: middle;
                color: #212529;
                border-color: #dee2e6;
                font-weight: 500;
            }

            .table_searching tbody td strong {
                font-weight: 700;
                color: #212529;
            }

            .table_searching tbody tr:hover {
                background-color: #f8f9fa;
            }

            .table_searching tbody tr:hover td {
                color: #212529;
            }

            .table_searching .text-end {
                font-family: 'Inter', 'Segoe UI', 'Roboto', 'Helvetica Neue', Arial, sans-serif;
                font-weight: 600;
                color: #212529;
            }

            .table_searching tbody td a {
                color: #212529;
                text-decoration: none;
                font-weight: 600;
            }

            .table_searching tbody td a:hover {
                color: #0d6efd;
                text-decoration: underline;
            }

            .table_searching .badge {
                font-weight: 600;
                font-size: 0.8rem;
            }

            .table_searching .text-muted {
                color: #6c757d !important;
            }
        </style>

        <?php
        return ob_get_clean();
    }

    /**
     * Affiche le tableau avec le titre.
     *
     * @param string $html Contenu HTML du tableau
     * @param string $titre Titre du tableau
     */
    private static function createTable2($html, $titre)
    {
        // Assurez-vous que la méthode createTable est définie ailleurs
        self::createTable($html, $titre, '');
    }

    public static function getListeFactureByMoisId()
    {
        if (isset($_GET["id_mois"], $_GET["id_constante"])) {

            $id_mois = (int) htmlspecialchars($_GET["id_mois"]);
            $id_constante = (int) htmlspecialchars($_GET["id_constante"]);

            // Récupérer la liste des abonnés pour la navigation
//            $abones_nav = Facture::getAbonesListForNavigation($id_mois, $_SESSION['id_aep']);
//            $abones_list = $abones_nav ? $abones_nav->fetchAll() : array();

            $req = Facture::getMonthAllFactureData($id_mois, $_SESSION['id_aep']);
            $req = $req->fetchAll();
            $abones_list = $req;

            // Récupérer le mois en lettres pour l'affichage
            $mois_query = Facture::prepare_query("SELECT mois FROM mois_facturation WHERE id = ?", array($id_mois));
            $mois_data = $mois_query->fetch();
            $mois_lettre = $mois_data ? getLetterMonth($mois_data['mois']) : '';

            // Afficher le panneau de navigation à gauche
            ?>
            <style>
                .facture-container {
                    display: flex;
                    gap: 20px;
                    margin-top: 20px;
                }

                .navigation-panel {
                    width: 350px;
                    max-height: calc(100vh - 110px);
                    overflow-y: auto;
                    background: #f8f9fa;
                    border-radius: 10px;
                    padding: 15px;
                    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
                    position: sticky;
                    top: 20px;
                }

                .navigation-panel h5 {
                    color: #495057;
                    margin-bottom: 10px;
                    font-weight: 600;
                    border-bottom: 2px solid #007bff;
                    padding-bottom: 8px;
                }

                .controls-section {
                    margin-bottom: 15px;
                    padding: 10px;
                    background: #f8f9fa;
                    border-radius: 6px;
                }

                .search-box {
                    width: 100%;
                    margin-bottom: 10px;
                    padding: 6px 8px;
                    font-size: 0.85rem;
                    border: 1px solid #dee2e6;
                    border-radius: 4px;
                }

                .action-buttons {
                    display: flex;
                    gap: 5px;
                    flex-wrap: wrap;
                }

                .action-buttons button {
                    flex: 1;
                    min-width: 80px;
                    padding: 5px 8px;
                    font-size: 0.75rem;
                    border: none;
                    border-radius: 4px;
                    cursor: pointer;
                    transition: all 0.2s;
                }

                .btn-check-all {
                    background: #28a745;
                    color: white;
                }

                .btn-check-all:hover {
                    background: #218838;
                }

                .btn-uncheck-all {
                    background: #dc3545;
                    color: white;
                }

                .btn-uncheck-all:hover {
                    background: #c82333;
                }

                .disabled-list {
                    margin-top: 10px;
                    padding-top: 10px;
                    border-top: 1px solid #dee2e6;
                }

                .disabled-list h6 {
                    color: #6c757d;
                    font-size: 0.85rem;
                    margin-bottom: 8px;
                }

                .disabled-names {
                    font-size: 0.75rem;
                    color: #6c757d;
                    line-height: 1.8;
                }

                .disabled-names .name-item {
                    padding: 3px 0;
                    border-bottom: 1px dotted #dee2e6;
                }

                .disabled-names .name-item:last-child {
                    border-bottom: none;
                }

                .abone-item {
                    display: flex;
                    align-items: center;
                    padding: 6px 8px;
                    margin-bottom: 4px;
                    background: white;
                    border-radius: 6px;
                    border: 1px solid #dee2e6;
                    cursor: pointer;
                    transition: all 0.2s ease;
                    font-size: 0.85rem;
                }

                .abone-item:hover {
                    background: #e9ecef;
                    border-color: #007bff;
                }

                .abone-item.checked {
                    background: #d1ecf1;
                    border-color: #0c5460;
                }

                .abone-item.unchecked {
                    background: #fff3cd;
                    border-color: #ffc107;
                }

                .abone-item input[type="checkbox"] {
                    margin-right: 8px;
                    transform: scale(1.15);
                    cursor: pointer;
                }

                .abone-info {
                    flex: 1;
                    min-width: 0;
                }

                .abone-name {
                    font-weight: 600;
                    color: #212529;
                    font-size: 0.85rem;
                    margin-bottom: 3px;
                    white-space: nowrap;
                    overflow: hidden;
                    text-overflow: ellipsis;
                }

                .abone-summary {
                    display: flex;
                    justify-content: space-between;
                    font-size: 0.75rem;
                    color: #495057;
                }

                .summary-item {
                    display: flex;
                    align-items: center;
                    gap: 3px;
                }

                .summary-item.negative {
                    color: #dc3545;
                    font-weight: 600;
                }

                .summary-item.positive {
                    color: #28a745;
                    font-weight: 600;
                }

                .factures-content {
                    flex: 1;
                    overflow-y: auto;
                    max-height: calc(100vh - 110px);
                    padding-right: 10px;
                }
            </style>

            <div class="facture-container">
                <!-- Panneau de navigation -->
                <div class="navigation-panel">
                    <h5><i class="bi bi-list-check"></i> Abonnés facturés - <?php echo $mois_lettre; ?></h5>

                    <!-- Contrôles -->
                    <div class="controls-section">
                        <input type="text" class="search-box" id="search-abones" placeholder="Rechercher un abonné..."
                            onkeyup="filterAbones(this.value)">
                        <div class="action-buttons">
                            <button class="btn-check-all" onclick="checkAll(false)">✓ Cocher tout</button>
                            <button class="btn-uncheck-all" onclick="uncheckAll(false)">✗ Décocher tout</button>
                        </div>
                    </div>

                    <div id="active-abones">
                        <?php foreach ($abones_list as $abone): ?>
                            <div class="abone-item checked" id="abone-<?php echo $abone['id_abone']; ?>"
                                onclick="scrollToAbone(<?php echo $abone['id_abone']; ?>);">
                                <input type="checkbox" id="checkbox-<?php echo $abone['id_abone']; ?>" checked
                                    onclick="event.stopPropagation(); toggleAbone(<?php echo $abone['id_abone']; ?>);">
                                <div class="abone-info">
                                    <div class="abone-name" title="<?php echo htmlspecialchars($abone['nom_abone']); ?>">
                                        <?php echo htmlspecialchars($abone['nom_abone']); ?>
                                    </div>
                                    <div class="abone-summary">
                                        <div class="summary-item">
                                            <span>Total:</span>
                                            <strong><?php echo number_format($abone['total_cumule'], 0, ',', ' '); ?> FCFA</strong>
                                        </div>
                                        <div
                                            class="summary-item <?php echo $abone['impayer_cumule'] < 0 ? 'positive' : ($abone['impayer_cumule'] > 0 ? 'negative' : ''); ?>">
                                            <span>Impayé:</span>
                                            <strong><?php echo number_format($abone['impayer_cumule'], 0, ',', ' '); ?> FCFA</strong>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <!-- Liste des noms décochés -->
                    <div class="disabled-list" id="disabled-list" style="display: none;">
                        <h6><i class="bi bi-x-circle"></i> Noms désactivés:</h6>
                        <div class="disabled-names" id="disabled-names"></div>
                    </div>
                </div>

                <!-- Contenu des factures -->
                <div class="factures-content" id="a_imprimer">
                    <?php

                    $mes_facture = "";
                    foreach ($req as $data) {
                        $conso_mois = Facture::calculeConso(+$data['nouvel_index'], $data['ancien_index']);
                        $montant_conso = Facture::calculeMontantConso(+$data['nouvel_index'], $data['ancien_index'], $data['prix_metre_cube_eau']);
                        $montant_total = $data['total_cumule'];
                        $montant_restant = $data['restant_cumule'];

                        $curent_aep = Aep::getOne($_SESSION['id_aep'], 'aep');
                        $curent_aep = $curent_aep->fetch();
                        $model_facture = 'model_nkongzem';
                        if ($curent_aep) {
                            $model_facture = $curent_aep['fichier_facture'];
                        }

                        $mes_facture = $mes_facture . '<div class="facture-item" data-abone-id="' . $data['id_abone'] . '">' .
                            self::creerFacture(
                                $model_facture,
                                $data['nom_abone'],
                                $data['numero_compteur'],
                                $data['numero_compte_anticipation'],
                                $data['reseau'],
                                $data['ancien_index'],
                                $data['nouvel_index'],
                                $conso_mois,
                                $data['mois'],
                                $data['impayer_cumule'],
                                $data['penalite'],
                                $data['prix_metre_cube_eau'],
                                $data['prix_entretient_compteur'],
                                $montant_total,
                                $data['montant_conso'],
                                $data['prix_tva'],
                                self::addDaysAndFormat($data['date_depot'], 0),
                                self::addDaysAndFormat($data['date_depot']),
                                $data['id_compteur'],
                                $data['id_mois'],
                                addZeros($data['id_facture'], 6),
                                self::addDaysAndFormat($data['date_releve'], 0),
                                $data['numero_compte'],
                                $data['nom_banque']
                            ) . '</div>';
                    }
                    echo $mes_facture;
                    ?>
                </div>
            </div>

            <script>
                const disabledNames = [];

                function toggleAbone(aboneId) {
                    const checkbox = document.getElementById('checkbox-' + aboneId);
                    const aboneItem = document.getElementById('abone-' + aboneId);
                    const factureItems = document.querySelectorAll('.facture-item[data-abone-id="' + aboneId + '"]');

                    if (checkbox.checked) {
                        aboneItem.classList.add('checked');
                        aboneItem.classList.remove('unchecked');

                        // Retirer de la liste des désactivés
                        const index = disabledNames.indexOf(aboneId);
                        if (index > -1) {
                            disabledNames.splice(index, 1);
                        }

                        factureItems.forEach(item => {
                            item.style.display = 'block';
                        });
                    } else {
                        aboneItem.classList.remove('checked');
                        aboneItem.classList.add('unchecked');

                        // Ajouter à la liste des désactivés
                        if (disabledNames.indexOf(aboneId) === -1) {
                            disabledNames.push(aboneId);
                        }

                        factureItems.forEach(item => {
                            item.style.display = 'none';
                        });
                    }

                    updateDisabledList();
                }

                function scrollToAbone(aboneId) {
                    const factureItems = document.querySelectorAll('.facture-item[data-abone-id="' + aboneId + '"]');
                    if (factureItems.length > 0) {
                        // Scroll uniquement dans le conteneur des factures
                        const facturesContent = document.querySelector('.factures-content');
                        const targetPosition = factureItems[0].offsetTop - 20;

                        facturesContent.scrollTo({
                            top: targetPosition,
                            behavior: 'smooth'
                        });

                        // Highlight temporairement
                        factureItems.forEach(item => {
                            item.style.transition = 'box-shadow 0.3s';
                            item.style.boxShadow = '0 0 20px rgba(0,123,255,0.5)';
                            setTimeout(() => {
                                item.style.boxShadow = '';
                            }, 2000);
                        });
                    }
                }

                function updateDisabledList() {
                    const disabledListDiv = document.getElementById('disabled-list');
                    const disabledNamesDiv = document.getElementById('disabled-names');

                    if (disabledNames.length === 0) {
                        disabledListDiv.style.display = 'none';
                        disabledNamesDiv.innerHTML = '';
                        return;
                    }

                    disabledListDiv.style.display = 'block';

                    let html = '';
                    disabledNames.forEach(aboneId => {
                        const aboneItem = document.getElementById('abone-' + aboneId);
                        if (aboneItem) {
                            const name = aboneItem.querySelector('.abone-name').textContent.trim();
                            html += '<div class="name-item">• ' + name + '</div>';
                        }
                    });

                    disabledNamesDiv.innerHTML = html;
                }

                function checkAll(byReseau) {
                    const checkboxes = document.querySelectorAll('#active-abones .abone-item input[type="checkbox"]');
                    checkboxes.forEach(checkbox => {
                        if (!checkbox.checked) {
                            checkbox.click();
                        }
                    });
                }

                function uncheckAll(byReseau) {
                    const checkboxes = document.querySelectorAll('#active-abones .abone-item input[type="checkbox"]');
                    checkboxes.forEach(checkbox => {
                        if (checkbox.checked) {
                            checkbox.click();
                        }
                    });
                }

                function filterAbones(searchTerm) {
                    const aboneItems = document.querySelectorAll('#active-abones .abone-item');
                    const searchLower = searchTerm.toLowerCase().trim();

                    aboneItems.forEach(item => {
                        const aboneName = item.querySelector('.abone-name').textContent.toLowerCase();
                        if (searchLower === '' || aboneName.includes(searchLower)) {
                            item.style.display = '';
                        } else {
                            item.style.display = 'none';
                        }
                    });
                }

                // Initialiser tous les éléments comme activés
                document.addEventListener('DOMContentLoaded', function () {
                    const checkboxes = document.querySelectorAll('.abone-item input[type="checkbox"]');
                    checkboxes.forEach(checkbox => {
                        const aboneId = checkbox.id.replace('checkbox-', '');
                        toggleAbone(parseInt(aboneId));
                    });
                });
            </script>
            <?php
        }

    }





    public static function exportFactureByInterval()
    {
        //        var_dump($_GET);
        if (!isset($_GET['choix'], $_GET['mois_debut'], $_GET['mois_fin']))
            return;
        echo 'vous pouver fermer cet onglet';

        $date_debut = htmlspecialchars($_GET['mois_debut']) == '' ? '2000-01' : htmlspecialchars($_GET['mois_debut']);
        $date_fin = htmlspecialchars($_GET['mois_fin']) == '' ? '2100-12' : htmlspecialchars($_GET['mois_fin']);

        $req = Facture::getPeriodData($_SESSION['id_aep'], $date_debut, $date_fin);

        $data = $req->fetchAll();
        var_dump($date_debut, $date_fin, $_GET);
        var_dump($data);
        //        exit();
        $tab = array();
        $tab_ligne = array();


        if (count($data) < 1)
            return;
        $i = 0;
        foreach ($data[0] as $key => $value) {
            if ($i % 2 == 0) {
                $tab_ligne[] = $key;
            }
            $i++;
        }
        $tab[] = $tab_ligne;

        $i = 0;
        foreach ($data as $ligne) {
            $tab_ligne = array();
            foreach ($ligne as $key => $value) {
                if ($i % 2 == 0) {
                    $tab_ligne[] = $value;
                }
                $i++;
            }
            $tab[] = $tab_ligne;
        }
        //        var_dump($tab);
        ?>
        <script>
            // Exemple de tableau JavaScript
            const exporting_data = <?php echo json_encode($tab) ?>;

            // Fonction pour convertir le tableau en CSV
            function convertToCSV(array) {
                return array.map(row => {
                    return row.join(';');
                }).join('\n');
            }

            // Fonction pour télécharger le CSV
            function downloadCSV() {
                const csvContent = convertToCSV(exporting_data);
                const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
                const url = URL.createObjectURL(blob);

                const link = document.createElement('a');
                link.setAttribute('href', url);
                link.setAttribute('download', 'data.csv'); // Nom du fichier
                document.body.appendChild(link); // Nécessaire pour Firefox

                link.click(); // Simule un clic pour télécharger le fichier
                document.body.removeChild(link); // Supprime le lien après le téléchargement
            }
            // alert('erick');
            downloadCSV();
            window.close();
            // Événement de clic sur le bouton

        </script>
        <?php
        //        header("location: ../presentation/index.php");
//        if (!$res)
//            header("location: ../presentation/index.php?list=tarif&operation=error");
//        else
//            header("location: ../presentation/index.php?list=tarif&operation=succes");


    }

    public static function creerFacture(
        $type_facture,
        $nom,
        $numero_compteur,
        $numero_compte_anticipation,
        $reseau,
        $ancien_index,
        $nouvel_index,
        $conso_mois,
        $mois,
        $impaye,
        $penalite,
        $prix_eau,
        $prix_entretient_compteur,
        $totalFacture,
        $facture_mois,
        $tva,
        $date_depot,
        $date_max_paiement,
        $id_compteur = 0,
        $id_mois = 0,
        $id_facture = 0,
        $date_releve = '',
        $numero_compte_bancaire = '',
        $nom_banque = ''
    ) {
        switch ($type_facture) {
            case 'model_fokoue':
                return self::creerFactureFokoue2(
                    $nom,
                    $numero_compteur,
                    $numero_compte_anticipation,
                    $reseau,
                    $ancien_index,
                    $nouvel_index,
                    $conso_mois,
                    $mois,
                    $impaye,
                    $penalite,
                    $prix_eau,
                    $prix_entretient_compteur,
                    $totalFacture,
                    $facture_mois,
                    $tva,
                    $date_depot,
                    $date_max_paiement,
                    $id_compteur,
                    $id_mois,
                    $id_facture,
                    $date_releve,
                    $numero_compte_bancaire,
                    $nom_banque
                );
                break;
            case 'model_nkongzem':
                return self::creerFactureNkongzem(
                    $nom,
                    $numero_compteur,
                    $numero_compte_anticipation,
                    $reseau,
                    $ancien_index,
                    $nouvel_index,
                    $conso_mois,
                    $mois,
                    $impaye,
                    $penalite,
                    $prix_eau,
                    $prix_entretient_compteur,
                    $totalFacture,
                    $facture_mois,
                    $tva,
                    $date_depot,
                    $date_max_paiement,
                    $id_compteur,
                    $id_mois,
                    $id_facture,
                    $numero_compte_bancaire,
                    $date_releve,
                    $nom_banque
                );
                break;
            default:
                return self::creerFactureNkongzem(
                    $nom,
                    $numero_compteur,
                    $numero_compte_anticipation,
                    $reseau,
                    $ancien_index,
                    $nouvel_index,
                    $conso_mois,
                    $mois,
                    $impaye,
                    $penalite,
                    $prix_eau,
                    $prix_entretient_compteur,
                    $totalFacture,
                    $facture_mois,
                    $tva,
                    $date_depot,
                    $date_max_paiement,
                    $id_compteur,
                    $id_mois,
                    $id_facture,
                    $numero_compte_bancaire,
                    $date_releve,
                    $nom_banque
                );
                break;

        }

    }

    public static function creerFactureFokoue(
        $nom,
        $numero_compteur,
        $numero_compte_anticipation,
        $reseau,
        $ancien_index,
        $nouvel_index,
        $conso_mois,
        $mois,
        $impaye,
        $penalite,
        $prix_eau,
        $prix_entretient_compteur,
        $totalFacture,
        $facture_mois,
        $tva,
        $date_depot,
        $date_max_paiement,
        $id_abone = 0,
        $id_mois = 0,
        $id_facture = 0,
        $date_releve = '',
        $numero_compte_bancaire = 0,
        $nom_banque
    ) {
        ob_start();
        ?>
        <div class="facture_abone m-2">
            <div class="row ">
                <div class="logo_commune col">
                    <img src="presentation/assets/images/logo_commune_fokoue.png" class="col-12"
                        alt="IMAGE DU LOGO DE LA COMMUNE DE FOKOUE">
                </div>
                <div class="logo_commune text-center align-self-center col-9 ">
                    <span class="h4">Agence Municipale de la Gestion de l'Energie,<br> de l'Eau et de l'Assainissement de la
                        <br>commune de Fokoué (AMGEEA)</span>
                </div>
                <div class="logo_amgeea col">
                    <img src="presentation/assets/images/logo_amgeea.png" class="col-12" alt="IMAGE DU LOGO DE L'AMGEEA">
                </div>
            </div>

            <div class=" d-flex justify-content-center fs-5">
                <div class="email">Email: <a href="https://fokoue/amgeea/home">amgeeafokoue@gmail.com</a></div>
                <div class="col-2"> </div>
                <div class="code_postal">B.P 02 Fokoue</div>
            </div>

            <div class="row text-center fs-5 border-bottom border-secondary border-3 pb-3 mb-2">
                <div class="numero_telephone">
                    <!-- https://getbootstrap.com/docs/5.3/layout/columns/ -->
                    Tél: 656 16 16 82 / 699 35 25 11 / 699 82 01 49 / 677 03 58 09
                </div>
            </div>
            <!-- <hr class="border"> -->
            <div class="text-center">
                <h2>FACTURE D'EAU / WATER BILL Nº <?php echo "$id_facture" ?></h2>
            </div>
            <div class="motivation fst-italic text-center fs-6">
                «Tous ensemble pour un accès durable a l'eau, à l'énergie et à l'assainissement dans la commune de Fokoué»
            </div>
            <div class=" d-flex justify-content-center h5">
                <div class="email ">Date de dépot: <span class="text-success"><?php echo $date_depot ?></span></div>
                <div class="col-2"></div>
                <div class="code_posta l">Date limite de paiement: <span
                        class="text-danger"><?php echo $date_max_paiement ?></span></div>
            </div>
            <div class="d-flex justify-content-center fs-4 ">
                <div class="">Période de facturation: <span class="text-uppercase"> <?php echo getLetterMonth($mois) ?></span>
                </div>
                <div class="col-2"></div>
                <div>Impayés: <?php echo self::moneyFormatter((int) $impaye) ?></div>
                <div class="col-2"></div>
                <div class="">Penalite: <?php echo (int) $penalite ?></div>
            </div>

            <div class="row">
                <div class="col-6"></div>
                <div class="col fs-5 ">Compte anticipation: <?php echo $numero_compte_anticipation ?></div>
            </div>

            <div class="d-flex justify-content-around fs-5">
                <div class="text-truncation">Nom du client: <span class="text-uppercase fw-bold"> <?php echo $nom ?></span>
                </div>
                <div>Nº compteur: <?php echo $numero_compteur ?></div>
                <div>Reseau: <?php echo $reseau ?></div>
            </div>
            <div>

                <table class="table_searching table table-striped table-bordered">
                    <tr>
                        <th>Rubrique Facture</th>
                        <th class="text-center">Ancien index</th>
                        <th class="text-center">Nouvel index</th>
                        <th class="text-center">Consommation</th>
                        <th class="text-center">Tarif</th>
                        <th class="text-center">Montant HT</th>
                        <th class="text-center">TVA</th>
                    </tr>
                    <tr class="text-center">
                        <td></td>
                        <td></td>
                        <td></td>
                        <span class="text-center">
                            <td>m <sup class="">3</sup></td>
                            <td>Fcfa</td>
                            <td>Fcfa</td>
                            <td>Fcfa</td>
                        </span>
                    </tr>
                    <tr>
                        <th>Consommation facturée</th>
                        <td class="text-center"><?php echo $ancien_index ?></td>
                        <td class="text-center"><?php echo $nouvel_index ?></td>
                        <td class="text-center"><?php echo $conso_mois ?></td>
                        <td class="text-center"><?php echo $prix_eau ?></td>
                        <td class="text-center"><?php echo $facture_mois ?></td>
                        <td class="text-center"><?php echo $tva ?></td>
                    </tr>
                    <tr>
                        <th>Entretient compteur</th>
                        <td></td>
                        <td></td>
                        <td></td>
                        <td class="text-center"><?php echo $prix_entretient_compteur ?></td>
                        <td class="text-center"><?php echo $prix_entretient_compteur ?></td>
                        <td></td>
                    </tr>
                    <tr>
                        <th colspan="4">Montant total facture</th>
                        <td colspan="2" class=" text-center fs-4 border border-2 border-secondary ">
                            <?php echo self::moneyFormatter($totalFacture) ?>
                        </td>

                        <td></td>

                    </tr>
                </table>

                <div class="text-center fw-bold fs-5">
                    ATTENTION: Tout paiment apreès la date limite est augmentée des frais de pénalité (2500 Fcfa). <br>
                    Any payment after the date above will be increased with penality (2500 Fcaa).

                </div>

            </div>
            <?php

            return ob_get_clean();
    }
    public static function creerFactureFokoue2(
        $nom,
        $numero_compteur,
        $numero_compte_anticipation,
        $reseau,
        $ancien_index,
        $nouvel_index,
        $conso_mois,
        $mois,
        $impaye,
        $penalite,
        $prix_eau,
        $prix_entretient_compteur,
        $totalFacture,
        $facture_mois,
        $tva,
        $date_depot,
        $date_max_paiement,
        $id_abone = 0,
        $id_mois = 0,
        $id_facture = 0,
        $date_releve = '',
        $numero_compte_bancaire = 0,
        $nom_banque
    ) {
        ob_start();
        ?>


            <div class="container my-0 py-0 border border-5 border-black">
                <style>
                    @media print {
                        @page {
                            size: A4 landscape;
                            margin: 1cm;
                        }

                        body {
                            font-size: 12pt;
                        }

                        .container {
                            width: 100%;
                            max-width: 100%;
                        }

                        .additional-space {
                            min-height: 150px;
                        }
                    }

                    body {
                        font-family: Arial, sans-serif;
                    }

                    .logo_commune img,
                    .logo_amgeea img {
                        max-height: 80px;
                        width: auto;
                    }

                    .table th,
                    .table td {
                        vertical-align: middle;
                    }

                    .border-heavy {
                        border: 2px solid #6c757d;
                    }

                    .additional-space {
                        min-height: 150px;
                    }
                </style>
                <div class="row align-items-center mb-3">
                    <div class="col-2 logo_commune">
                        <img src="presentation/assets/images/logo_commune_fokoue.png" class="img-fluid"
                            alt="Logo Commune de Fokoué">
                    </div>
                    <div class="col-8 text-center">
                        <h4 class="mb-0">Agence Municipale de la Gestion de l'Energie, de l'Eau et de
                            l'Assainissement<br>Commune de Fokoué (AMGEEA)</h4>
                    </div>
                    <div class="col-2 logo_amgeea">
                        <img src="presentation/assets/images/logo_amgeea.png" class="img-fluid" alt="Logo AMGEEA">
                    </div>
                </div>

                <div class="row text-center mb-2">
                    <div class="col">
                        <span>Email: <a href="https://fokoue/amgeea/home">amgeeafokoue@gmail.com</a></span>
                    </div>
                    <div class="col">
                        <span>B.P 02 Fokoué</span>
                    </div>
                </div>

                <div class="row text-center mb-3">
                    <div class="col">
                        <span>Tél: 656 16 16 82 / 699 35 25 11 / 699 82 01 49 / 677 03 58 09</span>
                    </div>
                </div>

                <div class="text-center mb-3">
                    <h2>FACTURE D'EAU / WATER BILL Nº <?php echo $id_facture ?></h2>
                </div>

                <div class="text-center fst-italic mb-3">
                    «Tous ensemble pour un accès durable à l'eau, à l'énergie et à l'assainissement dans la commune de Fokoué»
                </div>

                <div class="row mb-3">
                    <div class="col text-center">
                        Date de dépôt: <span class="text-success"><?php echo $date_depot ?></span>
                    </div>
                    <div class="col text-center">
                        Date limite de paiement: <span class="text-danger"><?php echo $date_max_paiement ?></span>
                    </div>
                </div>

                <div class="row mb-3">
                    <div class="col text-center">
                        Période de facturation: <span class="text-uppercase"><?php echo getLetterMonth($mois) ?></span>
                    </div>
                    <div class="col text-center">
                        <?php
                        $impaye = (int) $impaye;
                        // ontransforme la valeur absolu de l'impaye/avance en nombre au format financier
                        $impaye_string_value = self::moneyFormatter(abs($impaye));
                        if ($impaye < 0)
                            //                            echo "Avance: $impaye";
                            echo "Compte anticipation: <span class='text-success'>$impaye_string_value</span>";
                        elseif ($impaye == 0)
                            echo "Impayés: <span>$impaye_string_value</span>";
                        else
                            echo "Impayés: <span class='text-danger'>$impaye_string_value</span>";

                        ?>
                    </div>
                    <div class="col text-center">
                        Pénalité: <?php echo self::moneyFormatter((int) $penalite) ?>
                    </div>
                </div>

                <!--                <div class="row mb-3">-->
                <!--                    <div class="col-6"></div>-->
                <!--                    <div class="col-6">-->
                <!--                        Compte anticipation: --><?php //echo $numero_compte_anticipation ?>
                <!--                    </div>-->
                <!--                </div>-->

                <div class="d-flex justify-content-around">
                    <div class="">
                        Nom du client: <span class="text-uppercase fw-bold"><?php echo trim($nom) ?></span>
                    </div>
                    <div class="">
                        Nº compteur: <?php echo $numero_compteur ?>
                    </div>
                    <div class="">
                        Réseau: <?php echo $reseau ?>
                    </div>
                </div>
                <br>

                <table class="table table-striped table-bordered border-heavy mb-3">
                    <thead>
                        <tr>
                            <th>Rubrique Facture</th>
                            <th class="text-center">Ancien index</th>
                            <th class="text-center">Nouvel index</th>
                            <th class="text-center">Consommation</th>
                            <th class="text-center">Tarif</th>
                            <th class="text-center">Montant HT</th>
                            <th class="text-center">TVA</th>
                        </tr>
                        <tr class="text-center">
                            <td></td>
                            <td></td>
                            <td></td>
                            <td>m<sup>3</sup></td>
                            <td>Fcfa</td>
                            <td>Fcfa</td>
                            <td>Fcfa</td>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <th>Consommation facturée</th>
                            <td class="text-center"><?php echo $ancien_index ?></td>
                            <td class="text-center"><?php echo $nouvel_index ?></td>
                            <td class="text-center"><?php echo $conso_mois ?></td>
                            <td class="text-center"><?php echo $prix_eau ?></td>
                            <td class="text-center"><?php echo self::moneyFormatter($facture_mois) ?></td>
                            <td class="text-center"><?php echo $tva ?></td>
                        </tr>
                        <tr>
                            <th>Entretien compteur</th>
                            <td></td>
                            <td></td>
                            <td></td>
                            <td class="text-center"><?php echo $prix_entretient_compteur ?></td>
                            <td class="text-center"><?php echo $prix_entretient_compteur ?></td>
                            <td></td>
                        </tr>
                        <tr>
                            <th colspan="4">Montant total facture</th>
                            <td colspan="2" class="text-center fs-4 border-heavy">
                                <?php echo self::moneyFormatter($totalFacture) ?>
                            </td>
                            <td></td>
                        </tr>
                    </tbody>
                </table>

                <div class="text-center fw-bold mb-3">
                    ATTENTION: Tout paiement après la date limite est augmenté des frais de pénalité (2500 Fcfa).<br>
                    Any payment after the date above will be increased with penalty (2500 Fcfa).
                </div>

                <!--                <div class="additional-space">-->
                <!-- Additional space for other content -->
                <!--                </div>-->
            </div>
            <?php
            return ob_get_clean();
    }
    public static function creerFactureNkongzem(
        $nom,
        $numero_compteur,
        $numero_compte_anticipation,
        $reseau,
        $ancien_index,
        $nouvel_index,
        $conso_mois,
        $mois,
        $impaye,
        $penalite,
        $prix_eau,
        $prix_entretient_compteur,
        $totalFacture,
        $facture_mois,
        $tva,
        $date_depot,
        $date_max_paiement,
        $id_abone = 0,
        $id_mois = 0,
        $id_facture = 0,
        $numero_compte_banque = "---------------",
        $date_releve = '',
        $nom_banque = ''
    ) {
        ob_start();
        ?>
            <div class="facture_abone mt-0">
                <div class="row">
                    <div class="logo_commune col d-flex align-items-center text-center justify-content-between text-success">
                        <img src="presentation/assets/images/logo_tockem.png" height="" style="height: 25vh" class="col-12"
                            alt="IMAGE DU LOGO DE L'ASSOCIATION TOCKEM">
                        <!--                        alt="IMAGE DU LOGO DE LA COMMUNE DE FOKOUE">-->
                        <h3 class="ps-2 fw-bold float-none" style="font-family: Calibri,serif">
                            <?php echo getLetterMonth($mois) ?>
                        </h3>
                    </div>
                    <div class="logo_commune text-center align-self-center col-9 ">
                        <div class="h3 fs-2 fw-bold m-0">FACTURE D’EAU POTABLE. <span class="fst-italic">N°
                                <?php echo $id_facture ?></span></div>
                        <div class="fs-4 m-0 fst-italic fw-bold">N° de Compte <?php echo htmlspecialchars($nom_banque) ?> :
                            <?php echo htmlspecialchars($numero_compte_banque) ?>
                        </div>
                        <div class="fs-4 m-0 fw-bold " style="color: #5B9BD5">Merci de payer dans les délais</div>
                        <div class="fs-6 m-0 fw-bold">ATTENTION !!!: VOUS RISQUEZ UNE COUPURE POUR FACTURES IMPAYEES</div>
                        <div class="fs-4 m-0" style="color: #2F5496;">Votre abonnement sera résilié au-delà de <span
                                class="text-black fw-bold">3</span> factures impayées</div>
                    </div>
                    <div class="logo_amgeea col">
                        <img src="presentation/assets/images/logo_nkongzem.png" style="height: 25vh" class="col-12"
                            alt="IMAGE DU LOGO DE LA COMMUNE DE NKONGZEM">
                    </div>
                </div>

                <div class="d-flex justify-content-evenly">
                    <div class="" style="width: 25%; font-size: 12px">
                        <span class="fw-bold">Association TOCKEM</span><br>
                        Siège Bureau d’exploitation : Nkong
                        Zem 1er étage immeuble derrière la
                        place des fêtes.
                        BP 62 DSCHANG (Cameroun)
                    </div>
                    <div class="fs-6 fw-bold text-center me-1 text-white d-flex justify-content-center align-items-center"
                        style="border: 2px solid #5B9BD5; border-radius: 10px; background-color: #2F5597; width: 50%">
                        Pour régler votre facture rendez vous aux Bureau de la régie communale de
                        l’eau de Nkong-Zem entre 9h30 et 15h
                    </div>
                    <div class="fs-6 fw-bold text-center text-white  d-flex justify-content-center align-items-center"
                        style="border: 2px solid #5B9BD5; border-radius: 10px; background-color: #2F5597 ; width: 25%">
                        Date limite de paiement <br>
                        10 Jours dès réception
                    </div>
                </div>

                <div class="d-flex justify-content-evenly mt-2">
                    <div class="fs-6 text-start me-1"
                        style="border: 2px solid #5B9BD5; border-radius: 10px; background-color: #8FAADC ; width: 65%">
                        <table class="table w-100">
                            <tbody>
                                <tr>
                                    <th>Adresse</th>
                                    <td><?php echo $reseau ?></td>
                                    <th>N° Compteur</th>
                                    <td><?php echo $numero_compteur ?></td>
                                </tr>
                                <tr>
                                    <th>Nom/Prénom</th>
                                    <td><?php echo strlen($nom) <= 40 ? $nom : substr($nom, 0, 40) . '..' ?></td>
                                    <th>Date de relevé</th>
                                    <td><?php echo $date_releve ?></td>
                                </tr>
                                <tr>
                                    <th>Réseau AEP</th>
                                    <td><?php echo $_SESSION['libele_aep']; ?></td>
                                    <th>Date de facturation</th>
                                    <td><?php echo $date_depot ?></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <div style="border: 2px solid #5B9BD5; border-radius: 10px; background-color: #2F5597 ; width: 35%">
                        <table class="table">
                            <tbody>
                                <tr>
                                    <th>Impayés</th>
                                    <td><?php echo self::moneyFormatter($impaye) ?> FCFA</td>
                                </tr>
                                <tr>
                                    <th>Facture du mois</th>
                                    <td><?php echo self::moneyFormatter($facture_mois + $prix_entretient_compteur) ?> FCFA</td>
                                </tr>
                                <tr>
                                    <th>Dette totale</th>
                                    <td><?php echo self::moneyFormatter($totalFacture) ?> FCFA</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
                <!-- <hr class="border"> -->
                <!--<div class="text-center">
                <h2>FACTURE D'EAU / WATER BILL Nº <?php /*echo "$id_facture" */ ?></h2>
            </div>
            <div class="motivation fst-italic text-center fs-6">
                «Tous ensemble pour un accès durable a l'eau, à l'énergie et à l'assainissement dans la commune de Fokoué»
            </div>
            <div class=" d-flex justify-content-center h5">
                <div class="email ">Date de dépot: <span class="text-success"><?php /*echo $date_depot */ ?></span></div>
                <div class="col-2"></div>
                <div class="code_posta l">Date limite de paiement: <span
                        class="text-danger"><?php /*echo $date_max_paiement */ ?></span></div>
            </div>
            <div class="d-flex justify-content-center fs-4 ">
                <div class="">Période de facturation: <span class="text-uppercase"> <?php /*echo getLetterMonth($mois) */ ?></span>
                </div>
                <div class="col-2"></div>
                <div>Impayés: <?php /*echo (int) $impaye */ ?></div>
                <div class="col-2"></div>
                <div class="">Penalite: <?php /*echo (int) $penalite */ ?></div>
            </div>

            <div class="row">
                <div class="col-6"></div>
                <div class="col fs-5 ">Compte anticipation: <?php /*echo $numero_compte_anticipation */ ?></div>
            </div>

            <div class="d-flex justify-content-around fs-5">
                <div class="text-truncation">Nom du client: <span class="text-uppercase fw-bold"> <?php /*echo $nom */ ?></span>
                </div>
                <div>Nº compteur: <?php /*echo $numero_compteur */ ?></div>
                <div>Reseau: <?php /*echo $reseau */ ?></div>
            </div>-->
                <div class="mt-3">

                    <table class="table">
                        <tr style="background-color: #5b9bd5">
                            <th></th>
                            <th class="">Ancien index</th>
                            <th class="">Nouvel index</th>
                            <th class="">Quantité</th>
                            <th class="">Tarif unitaire/m3</th>
                            <th class="">Unité</th>
                            <th class="">Montant (FCFA)</th>
                        </tr>
                        <tr class="text-start" style="background-color: #d2deef">
                            <th>Conso. Compteur actuel </th>
                            <td class=""><?php echo $ancien_index ?></td>
                            <td class=""><?php echo $nouvel_index ?></td>
                            <td class=""><?php echo $conso_mois ?></td>
                            <td class=""><?php echo $prix_eau ?></td>
                            <td class="">m <sup>3</sup></td>
                            <td class=""><?php echo self::moneyFormatter($facture_mois) ?></td>
                        </tr>
                        <tr class="text-start" style="background-color: #eaeff7">
                            <th>Entretient compteur</th>
                            <td></td>
                            <td></td>
                            <td></td>
                            <td class=""></td>
                            <td class="">1 Mois</td>
                            <td><?php echo $prix_entretient_compteur ?></td>
                        </tr>
                        <!--                    <tr class="m-0 p-5" style="background-color: #d2deef">-->
                        <!--                        <th>Pénalité</th>-->
                        <!--                        <td></td>-->
                        <!--                        <td></td>-->
                        <!--                        <td></td>-->
                        <!--                        <td></td>-->
                        <!--                        <td></td>-->
                        <!--                        <td class="">--><?php //echo self::moneyFormatter($penalite) ?><!--</td>-->
                        <!--                    </tr>-->
                        <tr style="background-color: #5b9bd5">
                            <th colspan="5">Total TTC (en FCFA) </th>
                            <td colspan="2" style="background-color: #d2deef" class=" text-center fs-5 fw-bold">
                                <?php echo self::moneyFormatter($totalFacture) ?>
                            </td>
                        </tr>
                    </table>

                    <div class="text-center fs-">
                        <span>Pour rapporter un dysfonctionnement sur le réseau, contactez le 670 02 90 33</span><br>
                        <span>Pour vous abonner au service public de l’eau de Bassessa, contactez le 698 19 32 80</span><br>
                        <span class="fw-bold">NB : Les mauvais usages concernent la revente de l’eau au voisinage et les
                            branchements frauduleux.</span>

                    </div>
                    <div class="d-flex justify-content-center">
                        <div class="col-11 fs-5 fw-bold text-center me-1 text-white d-flex justify-content-center align-items-center"
                            style="border: 2px solid #5B9BD5; border-radius: 10px; background-color: #2F5597;">
                            Le payement de votre facture dans les délais est le garant d’un service d’eau potable durable
                        </div>
                    </div>


                </div>
            </div>

            <?php

            return ob_get_clean();
    }

    public static function moneyFormatter($montant)
    {
        return number_format($montant, 0, ',', ' ');
    }

    public static function addDaysAndFormat($string_date, $days = 10)
    {
        $date = new DateTime($string_date);
        $date->modify("+$days days");
        return $date->format('d/m/Y');
    }



    public static function writeToFile($fileName, $content)
    {
        $chaine = "";
        if (file_exists($fileName))
            $chaine = file_get_contents($fileName);
        return file_put_contents($fileName, $content . '\n' . $chaine);
    }

    public static function updateMontantVerse()
    {
        // 'recouvrement': true,  'data'
        try {
            if (isset($_GET['recouvrement_facture'])) {
                $request_body = json_decode(file_get_contents('php://input'), true);
                //echo $_POST['recouvrement'];
                //recouvrement: true,  'id_facture': id_facture, 'date_versement': date_recouvrement, 'montant_verse': montant })
                if (isset($request_body['recouvrement'])) {
                    //                $data = $request_body['recouvrement'];
                    $montant_verse = (int) ($request_body['montant_verse'] + 0.00000001);
                    $date_versement = (int) $request_body['date_recouvrement'];
                    $id_indexes = (int) $request_body['id_indexes'];
                    self::writeToFile('../donnees/log.txt', "3 de recouvrement");
                    $facture = Facture::getAllInfoFactureFromIndexesId($id_indexes);

                    $facture = $facture->fetchAll();
                    $nombre = count($facture);
                    $facture = $facture[0];
                    $ancien_index = (float) $facture['ancien_index'];
                    $nouvel_index = (float) $facture['nouvel_index'];
                    $prix_metre_cube_eau = (int) $facture['prix_metre_cube_eau'];
                    $prix_entretient_compteur = (int) $facture['prix_entretient_compteur'];
                    $prix_tva = (float) $facture['prix_tva'];
                    $id_facture = (int) ($facture['id']);
                    $impaye = (int) ($facture['impaye'] + 0.000000001);
                    $penalite = (int) $facture['penalite'];

                    if ($impaye < 0) {
                        $montant_verse += -$impaye;
                        $impaye = 0;
                    }
                    self::writeToFile('../donnees/log_impaye.txt', "on est passee     $impaye    " . $facture['impaye']);

                    // Appel de calculeMontantTotal
//                $montant_total = Facture::calculeMontantTotal($nouvel_index, $ancien_index, $prix_tva, $prix_entretient_compteur, $prix_metre_cube_eau, $total_montant, $penalite);

                    // Appel de calculeMontantRestant
//                $montant_restant = Facture::calculeMontantRestant($nouvel_index, $ancien_index, $prix_tva, $prix_entretient_compteur, $prix_metre_cube_eau, 0, $penalite, $montant_verse);
                    // calcul du montant que l'on doit enregistrer
//                $montant_a_valider = Facture::calculeMontantAValider($nouvel_index, $ancien_index, $prix_tva, $prix_entretient_compteur, $prix_metre_cube_eau, 0, $penalite, $montant_verse);

                    //                $total = ($impaye+0.0000000001) + (int)($facture['montant_verse']+0.000000000001);
                    $reste = 0;


                    //on suprime tou les impayer et on fait comme s'il jamais eu de reouvrement.
//                $tot = Facture::deleteImpayeByIdFacture($id_indexes);
                    //on fait le recouvrement en enregistrant un montant qui n'est superieur a celui de la facture
                    $res = Facture::effectuerRecouvrement($id_indexes, $montant_verse, $date_versement);
                    //                self::writeToFile('../donnees/log.txt', "id_indexes=$id_indexes   nombre=$nombre  nouvel_index =$nouvel_index, ancien_index=$ancien_index, prix_tva=$prix_tva, prix_entretient_compteur=$prix_entretient_compteur, prix_metre_cube_eau=$prix_metre_cube_eau, impaye0, penalite=$penalite, montant_verse=$montant_verse);\nmontant_restant: $montant_restant   impayes=$impaye  montant_verse=$montant_verse montant_a_valider=$montant_a_valider" );
                    //si le montant versé est different a celui de la facture, la difference va dans les impayés
//                if($montant_restant != 0){
//                    $impayeObject = new Impaye('', $id_facture, $montant_restant, 0, date('d/m/Y'));
//                    $impayeObject->ajouter();
//                }

                }
            }
        } catch (Exception $e) {
            echo $e->getMessage();
            self::writeToFile('log2.txt', $e->getMessage());
        }
    }

    // Fonction pour convertir le tableau associatif en chaîne de caractères
    function convertirEnString($tableau)
    {
        $result = '';
        foreach ($tableau as $cle => $valeur) {
            $result .= "$cle: $valeur\n";
        }
        return trim($result); // Retirer les espaces inutiles
    }


    public static function updateNouvelIndex()
    {
        // 'recouvrement': true,  'data'
        if (isset($_GET['releve_manuelle'])) {
            $request_body = json_decode(file_get_contents('php://input'), true);
            //echo $_POST['recouvrement'];
            //recouvrement: true,  'id_facture': id_facture, 'date_versement': date_recouvrement, 'montant_verse': montant })
            if (isset($request_body['recouvrement'])) {
                $index = (float) $request_body['nouvel_index'];
                $id_indexes = (int) $request_body['id_indexes'];
                $id_compteur = (int) $request_body['id_compteur'];
                $res = Facture::effectuerreleve($id_indexes, $index);
                Abones::updateIndexByCompteur_id($id_compteur, $index);
                self::writeToFile('log5.txt', self::convertirEnString($request_body));
                echo $res;

            }
        }
    }


    public static function createTable($htmlTableCode, $titre = 'liste', $autre_entete = '')
    {
        ?>
            <table class="table table-striped table-bordered table-hover">
                <thead>
                    <h3 class="py-3 " style="text-align: center; margin-top: 20px;">
                        <?php echo $titre ?>
                    </h3>
                    <?php echo $autre_entete ?>
                </thead>
                <tbody>
                    <?php echo $htmlTableCode; ?>
                </tbody>
            </table>
            <?php
    }

}

Facture_t::updateMontantVerse();
Facture_t::updateNouvelIndex();
Facture_t::exportFactureByInterval();
//exit('555555555555555555555555555');
