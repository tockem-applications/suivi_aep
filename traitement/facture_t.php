<?php
@include("../donnees/facture.php");
@include("donnees/impaye.php");
@include("../donnees/impaye.php");
@include("donnees/facture.php");
@include_once("../donnees/mois_facturation.php");
@include_once("donnees/mois_facturation.php");
@include_once("../traitement/abone_t.php");
@include_once("traitement/abone_t.php");
@include_once("../traitement/aep_t.php.php");
@include_once("traitement/aep_t.php.php");


class Facture_t
{


    public static function getTableauFactureactiveForReleve($id_mois = 0, $titre = 'Releve des nouveaux index')
    {
        $mois_lettre = '';
        $id_mois_actif = MoisFacturation::getIdMoisFacturationActive($_SESSION['id_aep']);
        $editable = $id_mois == $id_mois_actif || $id_mois == 0;
        if ($id_mois == 0) {
            $mois = MoisFacturation::getMoisFacturationActive($_SESSION['id_aep']);
            $mois = $mois->fetchAll();
            $id_mois = (int) $mois[0]['id'];
            $mois_lettre = getLetterMonth($mois[0]['mois']);
        } else {

            $mois = MoisFacturation::getOneById((int)$id_mois);
            $mois = $mois->fetchAll();
            //  var_dump($mois);
            //$id_mois = (int) $mois[0]['id'];
            $mois_lettre = getLetterMonth($mois[0]['mois']);
        }
        $req = Facture::getMonthFacture((int)$id_mois, $_SESSION['id_aep']);
        //echo 'oooooooooooo';
        // var_dump($req);
        $req = $req->fetchAll();
        $titre = "$titre $mois_lettre";
        $mes_facture = "";
        ob_start();
        ?>
        <tr>
            <th>Id</th>
            <th>Nom et Prenom</th>
            <th>Ancien index</th>
            <th>nouvel index</th>
        </tr>
        <?php
        foreach ($req as $data) {
            ?>

            <tr class=" <?php if((float)$data['ancien_index'] > (float)$data['nouvel_index'])
                                echo 'bg-danger';
                            elseif (((float)$data['ancien_index'] == (float)$data['nouvel_index']))
                                echo 'bg-warning';
                            elseif((float)$data['ancien_index'] < (float)$data['nouvel_index'])
                                echo '';
                            ?>">
                <td> <?php echo $data['id_abone'] ?> </td>
                <td> <?php echo $data['nom'] ?> </td>
                <td id="ancien_index<?php  echo $data['id'] ?>"> <?php echo $data['ancien_index'] ?></td>
                <td class="w-auto">
                    <input type="number" class="form-control w-0"
                        id="nouvel_index<?php echo $data['id']?>"
                        min="<?php echo $data['ancien_index']?>"
                        onkeyup="handleReleve_pressed_enter(event, this.value, <?php echo $data['id'] ?>, <?php echo $data['id_abone'] ?>)"
                        onchange="handleReleve(this.value, <?php echo $data['id'] ?>, <?php echo $data['id_abone'] ?>)" step="0.01"
                        value="<?php echo ((int) $data['nouvel_index'] == 0 ? '' :  $data['nouvel_index']) ?>"
                        aria-label="Sizing example input" aria-describedby="inputGroup-sizing-lg">
                        <input type="hidden" value="<?php echo $data['nouvel_index'] ?>" id="ex_nouvel_index<?php echo $data['id']?>">
                </td>
            </tr>
            <?php
        }
        echo '<a class=dropdown-item" href="?form=abone"> Ajouter un aboné</a>';
        $codeHtml = ob_get_clean();
        self::createTable($codeHtml, $titre, ""/*"<a href='traitement/moisfacturation_t.php?action=export_index' target='_blank'>Telecharger les index</a><br>"*/);



    ?>


    <br>

    </div>

    <?php
        //echo $mes_facture;

    }


    public static function getTableauFactureByMoisId($id_mois = 0, $titre = 'Liste des recouvrements: ')
    {
        if (/*isset($_GET["id_mois"], $_GET["id_constante"]) ||*/ true) {
            $mois_lettre = '';
            $id_mois_actif = MoisFacturation::getIdMoisFacturationActive($_SESSION['id_aep']);
            $editable = $id_mois == $id_mois_actif || $id_mois == 0;
            if ($id_mois == 0) {
                $mois = MoisFacturation::getMoisFacturationActive($_SESSION['id_aep']);
                $mois = $mois->fetchAll();
                if(count($mois)){
                    $id_mois = (int) $mois[0]['id'];
                    $mois_lettre = getLetterMonth($mois[0]['mois']);
                }
            } else {

                $mois = MoisFacturation::getOneById((int)$id_mois);
                $mois = $mois->fetchAll();
                //  var_dump($mois);
                //$id_mois = (int) $mois[0]['id'];
                $mois_lettre = getLetterMonth($mois[0]['mois']);
            }
            $req = Facture::getMonthFacture((int)$id_mois, $_SESSION['id_aep']);
            //echo 'oooooooooooo';
            // var_dump($req);
            $req = $req->fetchAll();
            $titre = "$titre $mois_lettre";
            $mes_facture = "";
            ob_start();
            ?>
            <tr>
                <th>Id</th>
                <th>Nom et Prenom</th>
                <th>Index</th>
                <th>Conso</th>
                <th>Impayer</th>
                <th>Facture</th>
                <th>Total</th>
                <th>Reste</th>
                <th>Versement</th>
                <th>Date</th>


            </tr>
            <?php
            foreach ($req as $data) {
//                var_dump($data['impaye']);
                $conso_mois = Facture::calculeConso(+$data['nouvel_index'], $data['ancien_index']);
                $montant_conso = Facture::calculeMontantConso(+$data['nouvel_index'], $data['ancien_index'], $data['prix_metre_cube_eau']);
                $motant_total = Facture::calculeMontantTotal(
                    $data['nouvel_index'],
                    $data['ancien_index'],
                    $data['prix_tva'],
                    $data['prix_entretient_compteur'],
                    $data['prix_metre_cube_eau'],
                    $data['impaye'],
                    $data['penalite']
                );
                $montant_tva = Facture::calculeMontantConsoTva(
                    $data['nouvel_index'],
                    $data['ancien_index'],
                    $data['prix_tva'],
                    $data['prix_entretient_compteur'],
                    $data['prix_metre_cube_eau']
                );
                $montant_restant = (int)Facture::calculeMontantRestant(
                    $data['nouvel_index'],
                    $data['ancien_index'],
                    $data['prix_tva'],
                    $data['prix_entretient_compteur'],
                    $data['prix_metre_cube_eau'],
                    $data['impaye'],
                    $data['penalite'],
                    $data['montant_verse']
                );
                ?>
                <tr <?php ?>>
                    <td> <?php echo $data['id_abone'] ?> </td>

                    <td>

                    <?php  echo make_Modal(''.$data['nom'], ''.Abone_t::afficheInputRecouvrementAbone($data['id_abone']),-1, 'recouvrement_Form_'.$data['id_abone'],'')?>
                    <a data-bs-toggle="modal" data-bs-target="#<?php echo 'recouvrement_Form_'.$data['id_abone'];?>"> <?php echo strlen($data['nom']) > 28 ? substr($data['nom'], 0, 24) . '...' : $data['nom'] ?></a> </td>
                    <td> <?php echo $data['ancien_index'] . ' - ' . $data['nouvel_index'] ?></td>

                    <td> <?php echo $conso_mois ?></td>
                    <td> <?php echo (int) $data['impaye'] ?></td>
                    <td> <?php echo $montant_tva ?></td>
                    <td>
                        <?php echo $motant_total ?>
                    </td>
                    <td> <?php echo $montant_restant ?>
                    </td>
                    <td class="w-auto">
                        <input type="number" class="form-control w-0"
                            onkeyup="handleRecouvrement_pressed_enter(event, this.value, <?php echo $data['id'] ?>)"
                            onchange="handleRecouvrement(this.value, <?php echo $data['id'] ?>)" step="0.01"
                            value="<?php echo ((int) $data['montant_verse'] == 0 ? '' : (int) $data['montant_verse']) ?>"
                            aria-label="Sizing example input" aria-describedby="inputGroup-sizing-lg">
                    </td>
                    <td class="">
                        <div><input type="datetime" id="date_releve_facture_<?php echo $data['id'] ?>" class="form-control mb-0 "
                                value="<?php echo date('d/m/Y') ?>"><?php echo '' ?></div>
                    </td>

                    <td><a href="" class="btn btn-info mb-0 ">Valider</a></td>
                </tr>
                <?php
                /*$mes_facture = $mes_facture.'<br>'.self::creerFacture(
                    $data['nom'],
                    $data['numero_compteur'],
                    $data['numero_compte_anticipation'],
                    $data['reseau'],
                    $data['ancien_index'],
                    $data['nouvel_index'],
                    $conso_mois,
                    $data['mois'],
                    $data['impaye'],
                    $data['penalite'],
                    $data['prix_metre_cube_eau'],
                    $data['prix_entretient_compteur'],
                    $montant_restant,
                    $montant_conso,
                    $data['prix_tva'],
                    $data['date_depot'],
                    '12/15/28'
                        );  */
            }
//            echo '<a class=dropdown-item" href="?form=abone"> Ajouter un aboné</a>';
            $codeHtml = ob_get_clean();
            self::createTable($codeHtml, $titre, ""/*"<a href='traitement/moisfacturation_t.php?action=export_index' target='_blank'>Telecharger les index</a><br>"*/);
        }



        ?>


        <br>

        </div>

        <?php
        //echo $mes_facture;
         return $id_mois;
    }

    public static function getListeFactureByMoisId()
    {
        if (isset($_GET["id_mois"], $_GET["id_constante"])) {

            $id_mois = (int) htmlspecialchars($_GET["id_mois"]);
            $id_constante = (int) htmlspecialchars($_GET["id_constante"]);

            $req = Facture::getMonthFacture($id_mois, $_SESSION['id_aep']);
            $req = $req->fetchAll();
            $mes_facture = "";
            ?>

            <?php
            foreach ($req as $data) {
                $conso_mois = Facture::calculeConso(+$data['nouvel_index'], $data['ancien_index']);
                $montant_conso = Facture::calculeMontantConso(+$data['nouvel_index'], $data['ancien_index'], $data['prix_metre_cube_eau']);
                $motant_total = Facture::calculeMontantTotal(
                    $data['nouvel_index'],
                    $data['ancien_index'],
                    $data['prix_tva'],
                    $data['prix_entretient_compteur'],
                    $data['prix_metre_cube_eau'],
                    $data['impaye'],
                    $data['penalite']
                );
                $montant_restant = Facture::calculeMontantRestant(
                    $data['nouvel_index'],
                    $data['ancien_index'],
                    $data['prix_tva'],
                    $data['prix_entretient_compteur'],
                    $data['prix_metre_cube_eau'],
                    $data['impaye'],
                    $data['penalite'],
                    $data['montant_verse']
                );

                $curent_aep = Aep::getOne($_SESSION['id_aep'], 'aep');
                $curent_aep = $curent_aep->fetch();
                $model_facture= 'model_nkongzem';
                if ($curent_aep) {
                    $model_facture = $curent_aep['fichier_facture'];
                }
//                var_dump($data);
                $mes_facture = $mes_facture . '<br>' . self::creerFacture(
                        $model_facture,
                    $data['nom'],
                    $data['numero_compteur'],
                    $data['numero_compte_anticipation'],
                    $data['reseau'],
                    $data['ancien_index'],
                    $data['nouvel_index'],
                    $conso_mois,
                    $data['mois'],
                    $data['impaye'],
                    $data['penalite'],
                    $data['prix_metre_cube_eau'],
                    $data['prix_entretient_compteur'],
                    $montant_restant,
                    $montant_conso,
                    $data['prix_tva'],
                    self::addDaysAndFormat($data['date_depot'], 0),
                    self::addDaysAndFormat($data['date_depot']),
                    $data['id_abone'],
                    $data['id_mois'],
                    addZeros($data['id_facture'], 6)
                );
            }
        echo $mes_facture;
        }

    }





    public static function exportFactureByInterval(){
//        var_dump($_GET);
        if(!isset($_GET['choix'], $_GET['mois_debut'], $_GET['mois_fin']))
            return;
        echo 'vous pouver fermer cet onglet';

        $date_debut = htmlspecialchars($_GET['mois_debut']);
        $date_fin = htmlspecialchars($_GET['mois_fin']);
        $req = Facture::getPeriodData($date_debut, $date_fin);
        $data = $req->fetchAll();
        $tab = array();
        $tab_ligne = array();


        if(count($data) < 1)
            return;
        $i = 0;
        foreach ($data[0] as $key => $value) {
            if($i % 2 == 0){
            $tab_ligne[] = $key;
            }
            $i++;
        }
        $tab[] = $tab_ligne;

        $i = 0;
        foreach ($data as $ligne){
            $tab_ligne = array();
            foreach ($ligne as $key => $value) {
                if($i % 2 == 0){
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
        // Événement de clic sur le bouton

    </script>
        <?php

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
        $id_abone = 0,
        $id_mois = 0,
        $id_facture = 0
    ) {
        switch ($type_facture) {
            case 'model_fokoue':
                return self::creerFactureFokoue(
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
                                    $id_abone ,
                                    $id_mois,
                                    $id_facture
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
                                    $id_abone ,
                                    $id_mois,
                                    $id_facture
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
                                    $id_abone ,
                                    $id_mois,
                                    $id_facture,
                                    '030903699-01-05'
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
        $id_facture = 0
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
                <div>Impayés: <?php echo (int) $impaye ?></div>
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

                <table class="table table-striped table-bordered">
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
        $numero_compte_banque ="030903699-01-06"
    ) {
        ob_start();
        ?>
        <div class="facture_abone mt-0">
            <div class="row">
                <div class="logo_commune col">
                    <img src="presentation/assets/images/logo_tockem.png" height="" style="height: 25vh" class="col-12"
                        alt="IMAGE DU LOGO DE L'ASSOCIATION TOCKEM">
<!--                        alt="IMAGE DU LOGO DE LA COMMUNE DE FOKOUE">-->
                </div>
                <div class="logo_commune text-center align-self-center col-9 ">
                    <div class="h3 fs-2 fw-bold m-0">FACTURE D’EAU POTABLE</div>
                    <div class="fs-4 m-0 fst-italic fw-bold">N° de Compte Mufid Doumbouo : <?php echo $numero_compte_banque?></div>
                    <div class="fs-4 m-0 fw-bold " style="color: #5B9BD5">Merci de payer dans les délais</div>
                    <div class="fs-5 m-0 fw-bold">ATTENTION !!!: VOUS RISQUEZ UNE COUPURE POUR FACTURES IMPAYEES</div>
                    <div class="fs-4 m-0" style="color: #2F5496;">Votre abonnement sera résilié au-delà de <span class="text-black fw-bold">3</span> factures impayées</div>
                </div>
                <div class="logo_amgeea col">
                    <img src="presentation/assets/images/logo_nkongzem.png" style="height: 25vh" class="col-12" alt="IMAGE DU LOGO DE LA COMMUNE DE NKONGZEM">
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
                <div class="fs-6 fw-bold text-center me-1 text-white d-flex justify-content-center align-items-center" style="border: 2px solid #5B9BD5; border-radius: 10px; background-color: #2F5597; width: 50%">
                    Pour régler votre facture rendez vous aux Bureau de la régie communale de
                    l’eau de Nkong-Zem entre 9h30 et 15h
                </div>
                <div class="fs-6 fw-bold text-center text-white  d-flex justify-content-center align-items-center" style="border: 2px solid #5B9BD5; border-radius: 10px; background-color: #2F5597 ; width: 25%">
                    Date limite de paiement <br>
                    10 Jours dès réception
                </div>
            </div>

            <div class="d-flex justify-content-evenly mt-2">
                <div class="fs-6 text-start me-1" style="border: 2px solid #5B9BD5; border-radius: 10px; background-color: #8FAADC ; width: 65%">
                    <table class="table w-100">
                        <tbody>
                            <tr>
                                <th>Adresse</th>
                                <td><?php echo $reseau?></td>
                                <th>N° Compteur</th>
                                <td><?php echo $numero_compteur?></td>
                            </tr>
                            <tr>
                                <th>Nom/Prénom</th>
                                <td><?php echo strlen($nom) <=40? $nom: substr($nom, 0, 40).'..'?></td>
                                <th>Date de relevé</th>
                                <td><?php echo getLetterMonth($mois)?></td>
                            </tr>
                            <tr>
                                <th>Réseau AEP</th>
                                <td><?php echo $_SESSION['libele_aep'];?></td>
                                <th>Date de facturation</th>
                                <td><?php echo $date_depot?></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div style="border: 2px solid #5B9BD5; border-radius: 10px; background-color: #2F5597 ; width: 35%">
                    <table class="table">
                        <tbody>
                            <tr>
                                <th>Impayés</th>
                                <td><?php echo (int)$impaye?> FCFA</td>
                            </tr>
                            <tr>
                                <th>Facture du mois</th>
                                <td><?php echo (int)$facture_mois?> FCFA</td>
                            </tr>
                            <tr>
                                <th>Dette totale</th>
                                <td><?php echo self::moneyFormatter($totalFacture)?> FCFA</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
            <!-- <hr class="border"> -->
            <!--<div class="text-center">
                <h2>FACTURE D'EAU / WATER BILL Nº <?php /*echo "$id_facture" */?></h2>
            </div>
            <div class="motivation fst-italic text-center fs-6">
                «Tous ensemble pour un accès durable a l'eau, à l'énergie et à l'assainissement dans la commune de Fokoué»
            </div>
            <div class=" d-flex justify-content-center h5">
                <div class="email ">Date de dépot: <span class="text-success"><?php /*echo $date_depot */?></span></div>
                <div class="col-2"></div>
                <div class="code_posta l">Date limite de paiement: <span
                        class="text-danger"><?php /*echo $date_max_paiement */?></span></div>
            </div>
            <div class="d-flex justify-content-center fs-4 ">
                <div class="">Période de facturation: <span class="text-uppercase"> <?php /*echo getLetterMonth($mois) */?></span>
                </div>
                <div class="col-2"></div>
                <div>Impayés: <?php /*echo (int) $impaye */?></div>
                <div class="col-2"></div>
                <div class="">Penalite: <?php /*echo (int) $penalite */?></div>
            </div>

            <div class="row">
                <div class="col-6"></div>
                <div class="col fs-5 ">Compte anticipation: <?php /*echo $numero_compte_anticipation */?></div>
            </div>

            <div class="d-flex justify-content-around fs-5">
                <div class="text-truncation">Nom du client: <span class="text-uppercase fw-bold"> <?php /*echo $nom */?></span>
                </div>
                <div>Nº compteur: <?php /*echo $numero_compteur */?></div>
                <div>Reseau: <?php /*echo $reseau */?></div>
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
                        <td class=""><?php echo $facture_mois ?></td>
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
                    <tr style="background-color: #5b9bd5">
                        <th colspan="5">Total TTC (en FCFA) </th>
                        <td colspan="2" style="background-color: #d2deef" class=" text-center fs-5 fw-bold">
                            <?php echo self::moneyFormatter($totalFacture) ?>
                        </td>
                    </tr>
                </table>

                <div class="text-center fs-">
                    <span>Pour rapporter un dysfonctionnement sur le réseau, contactez le 671938259 ou le 695794780</span><br>
                    <span>Pour vous abonner au service public de l’eau de Bassessa, contactez le 658077979 ou le 670905523</span><br>
                    <span class="fw-bold">NB : Les mauvais usages concernent la revente de l’eau au voisinage et les branchements frauduleux.</span>

                </div>
                <div class="d-flex justify-content-center">
                    <div class="col-11 fs-5 fw-bold text-center me-1 text-white d-flex justify-content-center align-items-center" style="border: 2px solid #5B9BD5; border-radius: 10px; background-color: #2F5597;">
                        Le payement de votre facture dans les délais est le garant d’un service d’eau potable durable
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
        return file_put_contents($fileName, $content);
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
                $data = $request_body['recouvrement'];
                $montant_verse = (int) $request_body['montant_verse'];
                $date_versement = (int) $request_body['date_recouvrement'];
                $id_facture = (int) $request_body['id_facture'];
                $facture = Facture::getOne($id_facture);
                $impaye = Impaye::getImpaye($id_facture);
                $impaye = $impaye->fetchAll();
                $impaye = (int)$impaye['impaye'];
                $facture = $facture->fetchAll();
                $total = (int)$impaye['impaye'] + (int)$facture['montant_verse'];
                $reste = 0;
                if($impaye != 0){
                    $reste = $total - $montant_verse;
                }
                if($reste == 0 || $impaye == 0){
                    $res = Facture::effectuerRecouvrement($id_facture, $montant_verse, $date_versement);
                    Facture::deleteByIdFacture($id_facture);
                    throw new exception("1 de recouvrement");
                }
                else if($reste > 0){
                    $res = Facture::effectuerRecouvrement($id_facture, $montant_verse, $date_versement);
                    Facture::deleteByIdFacture($id_facture);
                    new Impaye('', $id_facture, $reste, 0, date('d/m/Y'));
                    throw new exception("2 de recouvrement");
                }else if($reste < 0){
                    $res = Facture::effectuerRecouvrement($id_facture, $total, $date_versement);
                    Facture::deleteByIdFacture($id_facture);
                    new Impaye('', $id_facture, $reste, 0, date('d/m/Y'));
                    throw new exception("3 de recouvrement");
                }
                //echo $res;

            }
        }
        }catch (Exception $e){
            echo $e->getMessage();
            self::writeToFile('log.txt', $e->getMessage());
        }
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
                $id_facture = (int) $request_body['id_facture'];
                $id_abone = (int) $request_body['id_abone'];
                $res = Facture::effectuerreleve($id_facture, $index);
                Abones::updateIndex($id_abone, $index);
                echo $res;

            }
        }
    }


    public static function createTable($htmlTableCode, $titre = 'liste', $autre_entete = '')
    {
        ?>
            <table class="table table-striped table-bordered">
                <thead>
                    <h3 style="text-align: center; margin-top: 20px;">
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
