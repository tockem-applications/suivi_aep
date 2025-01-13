<?php

//require_once("manager.php");
@include_once("../donnees/manager.php");
@include_once("donnees/manager.php");


class MoisFacturation extends Manager
{

    //public $id;
    public $mois;
    public $date_facturation;
    public $date_depot;
    public $id_constante;
    public $est_actif;
    public $description;

    public static function getAllMois($mois_debut, $mois_fin, $id_aep)
    {
        if ($mois_debut == '') {
            $mois_debut = '1900-01';
        }
        if ($mois_fin == '') {
            $mois_fin = '2200-01';
        }
        return self::prepare_query('select m.*, count(f.id) nombre, sum(f.montant_verse) montant_versee,
                           sum(nouvel_index-ancien_index) conso
                        from mois_facturation m 
                            inner join constante_reseau c on m.id_constante = c.id
                            inner join indexes id on m.id = id.id_mois_facturation
                            inner join facture f on id.id = f.id_indexes
                        where m.mois>=? and m.mois <=? and c.id_aep=?
                        group by m.id order by mois desc ;', array($mois_debut, $mois_fin, $id_aep)
        );
    }

    public static function mois_exist($mois, $id_aep)
    {
        $res = self::prepare_query("
                            select m.* 
                            from mois_facturation m 
                                inner join constante_reseau c on c.id = m.id_constante
                            where mois=? and c.id_aep=?", array($mois, $id_aep));
        if (!$res)
            return false;
        $number = count($res->fetchAll());
        return $number == 0;
    }

    public static function mois_est_dernier($mois, $id_aep)
    {
        $res = self::prepare_query("
                            select m.* 
                            from mois_facturation m 
                                inner join constante_reseau c on m.id_constante = c.id 
                            where mois>? and c.id_aep=?;",
            array($mois, $id_aep));
        if (!$res)
            return false;
        $number = count($res->fetchAll());
        return $number == 0;
    }


    function getconstraint()
    {
        return array('value' => $this->id, 'column' => 'id');
    }

    function getDonnee()
    {
        return array('mois' => $this->mois
        , 'date_facturation' => $this->date_facturation
        , 'date_depot' => $this->date_depot
        , 'id_constante' => $this->id_constante
        , 'description' => $this->description
        , 'est_actif' => $this->est_actif);
    }

    function getNomTable()
    {
        return "mois_facturation";
    }

    function ajouterEtActiver()
    {
        $res = 0;
        try {
            $this->est_actif = true;
            $this->connecter();
            self::$bd->beginTransaction();
            self::query("update mois_facturation set est_actif=false where est_actif=true");
            $res = $this->ajouter();
            if ($res) {
                self::$bd->commit();
            } else
                self::$bd->rollBack();
        } catch (Exception $e) {
            self::$bd->rollBack();
            $res = 0;
        }
        return $res;

    }


    public static function getOrderedMonthList($id_aep)
    {
        return self::prepare_query('
                        select m.id, m.id_constante, m.mois 
                        from mois_facturation m 
                            inner join constante_reseau c on c.id=m.id_constante 
                        where c.id_aep=?
                        order by mois desc;',
            array($id_aep));
    }

    public static function updateDateDepot($id_mois, $date_depot)
    {
        return self::query("update mois_facturation set date_depot='$date_depot' where id='$id_mois';");
    }

    public function ajouternouvelleListeFacture($tab_index, $id_aep)
    {
        try {
            require("../donnees/Abones.php");
            require("../donnees/facture.php");
            $this->connecter();
            self::$bd->beginTransaction();
//            echo "kkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkk<br>";
            //on recupere la liste des ancienne factures pour avoir acces aux penalites et impayes
            $res = Facture::getAncienneFacture($id_aep);
            echo "oooooooooooooooooooooooooooooooooooooooo";
            $tab_ancienne_facture = $res->fetchAll();
            var_dump($tab_ancienne_facture);
            self::prepare_query("
                        update mois_facturation m 
                            inner join constante_reseau c on m.id_constante = c.id  
                            set m.est_actif=false  
                            where m.est_actif=true and c.id_aep=?",
                array($id_aep));
            $res = $this->ajouter();
            echo "bobobobobobobobooboboobobobooboboboboobbobobobob";
            // si l'ajout du nouveau mois fais proble on annule tout et on sort
            if (!$res) {
                self::$bd->rollBack();
                return 0;
            }
            var_dump($tab_index);
            echo "111111111111111111111111111111111111111111111111111";
            //on recupere l'id du mois que l'on vient d'ajouter pour la mettre dans nos prochaines facture
            $id_mois_facturation = self::getIdMoisFacturationActive($id_aep);
            if (!$id_mois_facturation) {
                self::$bd->rollBack(); //si on ne la retrouve pas il vaut mieu tout arreter 
                return 0;
            }
            $id_ancienne_facture = '';

            //On parcours la liste des index venu du mobile et on recupere les element essentiels
            //nouvel index, ancien index, longitude, latitude, id  de l'abone etc
            foreach ($tab_index as $ligne_tab_index) {
                echo "22222222222222222222222222222222222222222222222222222222222<br>";
                $nouvel_index = (float)$ligne_tab_index['nouvel_index'];
                $ancien_index = (float)$ligne_tab_index['ancien_index'];
                $id_abone = (int)$ligne_tab_index['id'];
                $id_compteur = (int)$ligne_tab_index['id_compteur'];

                //une fois que l'on a le nouvel index de l'abone, on le place dans l'abone pour un acces facile
                //mais avant il faut se rassurer qu'il est au moins egal a l'ancien index. sinon on ne lui ajoute pas de facture
                if ($nouvel_index < $ancien_index) {
                    $nouvel_index = $ancien_index;
                }
                echo "33333333333333333333333333333333333333333333333<br>";

                //on initialise  quelques valeur utiles pour les nouvelles factures
                $nouvel_impaye = 0.00;
                $found = false;
                $nouvelle_penalite = 0.00;
//                echo"*****************************************************************<br>";
//                echo"*****************************************************************<br>";
//                echo"*****************************************************************<br>";
                var_dump(count($tab_ancienne_facture));
                var_dump(count($tab_ancienne_facture));
                var_dump(count($tab_ancienne_facture));
                foreach ($tab_ancienne_facture as $ligne_ancienne_facture) {
                    echo "44444444444444444444444444444444444444444444444<br>";
                    if ((int)$ligne_ancienne_facture['id_compteur'] == $id_compteur) {

                        $prix_eau = $ligne_ancienne_facture['prix_metre_cube_eau'];
                        $id_ancienne_facture = $ligne_ancienne_facture['id'];
                        $prix_entretien = (int)$ligne_ancienne_facture['prix_entretient_compteur'];
                        $montant_verse = (int)$ligne_ancienne_facture['montant_verse'];
//                        $impaye = (int)$ligne_ancienne_facture['impaye'];
                        $impaye = 0;
                        $penalite = (int)$ligne_ancienne_facture['penalite'];
                        $prix_tva = (float)$ligne_ancienne_facture['prix_tva'];
                        $ex_nouvel_index = (float)$ligne_ancienne_facture['nouvel_index'];
                        $mois = $ligne_ancienne_facture['mois'];
                        $ex_ancien_index = (float)$ligne_ancienne_facture['ancien_index'];

                        if ($ex_nouvel_index != $ancien_index) {
                            $ancien_index = $ex_nouvel_index;
                        }
                        if ($ancien_index > $nouvel_index) {
                            $nouvel_index = $ancien_index;
                        }
                        $conso = $ex_nouvel_index - $ex_ancien_index;

                        //ici on calcule le nouvel impayer a partir de la consommation de l'ancien mois
                        $nouvel_impaye = Facture::calculeImpaye($ex_nouvel_index, $ex_ancien_index, $prix_tva, $prix_entretien, $prix_eau, $impaye, $montant_verse, $penalite);
                        echo "============= $impaye ================= $nouvel_impaye ====================== $montant_verse ============ $mois ========<br>";
                        break;
                    }
                    echo "5555555555555555555555555555555555555555555555555555555555<br>";
                    //maintenant on peut creer notre nouvelle facture et l'ajouter
                }

                echo "bonjour la famille <br>";
                // ace stade les index sont valides donc onsere le nouvel index dans la table de l'abone
                $res = Abones::updateIndex($id_abone, $nouvel_index);
                var_dump("eriidkddndndndndnndnd");
                if (!$res) {
                    self::$bd->rollBack();
                    return 0;
                }

                $nouvelle_facture = new Facture(0, $ancien_index
                    , $nouvel_index
                    , 0.00, '00/00/0000'
                    , $nouvelle_penalite, $id_mois_facturation
                    , $id_abone, '', $id_compteur);
                $res = $nouvelle_facture->save_facture();
                if ((int)$nouvel_impaye != 0) {
                    $impaye_object = new Impaye('', (int)$id_ancienne_facture, (int)$nouvel_impaye, 0, '00/00/0000');
                    $impaye_object->ajouter();
                }
                if (!$res) {
                    self::$bd->rollBack();
                    return 0;
                }
            }

        } catch (Exception $e) {
            self::$bd->rollBack();
            $res = 0;
            return 0;
        }
        self::$bd->commit();
        return (int)$id_mois_facturation;
    }

    public static function getMoisFacturationActive($id_aep)
    {
        return Manager::prepare_query('
                        select m.* 
                        from mois_facturation m 
                            inner join constante_reseau c on c.id=m.id_constante 
                        where c.id_aep=? and m.est_actif=true', array($id_aep));
    }

    public static function getOneById($id_mois)
    {
        return Manager::query("select * from mois_facturation  where id=$id_mois");
    }

    public static function getIdMoisFacturationActive($id_aep)
    {
        $res = self::getMoisFacturationActive($id_aep);
        $data = $res->fetchAll();
        //var_dump( (int)$data[0]['id']);
        if ($res->rowCount() == 0) {
            return 0;
        }
        return (int)$data[0]['id'];
    }

    public function __construct($id, $mois, $date_facturation, $date_depot, $id_constante, $description, $est_actif)
    {
        $this->id = $id;
        $this->mois = $mois;
        $this->date_facturation = $date_facturation;
        $this->date_depot = $date_depot;
        $this->id_constante = $id_constante;
        $this->description = $description;
        $this->est_actif = $est_actif;
    }
}