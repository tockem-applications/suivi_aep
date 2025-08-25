<?php

//require_once("manager.php");
@include_once("../donnees/manager.php");
@include_once("donnees/manager.php");

@include_once("../donnees/indexes.php");
@include_once("donnees/indexes.php");

class Facture extends Manager
{

    //public $id;
    public $ancien_index;
    public $nouvel_index;
    public $montant_verse;
    public $date_paiement;
    public $penalite;
    public $id_mois_facturation;
    public $id_index;
    public $id_abone;
    public $message;

    public $indexes;

    public static function formatFinancier($nombre) {
        // Convertir en float pour gérer les chaînes ou entiers
        $nombre = floatval($nombre);

        // Formater avec 2 décimales, point décimal, et espace comme séparateur de milliers
        return number_format($nombre, 2, '.', ' ');
    }

    public static function deleteImpayeByIdFacture($id_indexes)
    {
        return self::prepare_query("DELETE FROM impaye WHERE id_facture in (select f.id from facture f where f.id_indexes=?)", array($id_indexes));
    }

    public static function getMonthIndex($id_mois, $id_aep)
    {
        if (!is_int($id_mois))
            return false;
        return self::prepare_query("
                select id.id, f.id_abone, a.id_reseau,  m.id id_mois, f.id id_facture, a.nom, m.mois, ancien_index, nouvel_index, c.prix_entretient_compteur, co.numero_compteur, a.numero_compte_anticipation,
                c.prix_metre_cube_eau, c.prix_tva, penalite, sum(i.montant) as impaye, f.montant_verse, f.date_paiement, date_depot, date_facturation, r.nom reseau
                from facture f 
                    inner join indexes id on id.id = f.id_indexes
                    inner join abone a on a.id = f.id_abone
                    inner join compteur_abone c_ab on c_ab.id_abone = a.id
                    inner join compteur co on co.id = c_ab.id_compteur
                    left join impaye i on i.id_facture in (select f.id from abone a2, facture f2 
                                                                                inner join indexes id2 on id2.id = f2.id_indexes 
                                                                       where a.id=a2.id and a2.id = f2.id_abone and id2.id_mois_facturation<$id_mois)
                    inner join mois_facturation m on id.id_mois_facturation =m.id 
                    inner join  constante_reseau c on c.id=m.id_constante
                    inner join reseau r on r.id = a.id_reseau
                where  m.id=? and c.id_aep=? and r.id_aep=?
                group by a.id
                order by a.id
                limit 4
            ", array($id_mois, $id_aep, $id_aep));
    }

    public static function getFactureByIndexesId($id_indexes)
    {
        return self::prepare_query("SELECT * FROM facture WHERE id_indexes=?", array($id_indexes));
    }

    public static function getAllInfoFactureFromIndexesId($id_indexes)
    {
        return self::prepare_query("SELECT id.ancien_index, id.nouvel_index,  f.*, c.prix_metre_cube_eau, c.prix_entretient_compteur, c.prix_tva, sum(i.montant) impaye FROM facture f
                 inner join indexes id on id.id=f.id_indexes
                 inner join mois_facturation m on id.id_mois_facturation=m.id
                 inner join constante_reseau c on c.id=m.id_constante
                 left join impaye i on i.id_facture=f.id
                WHERE f.id_indexes=? group by f.id", array($id_indexes));
    }


    function getconstraint()
    {
        return array('value' => $this->id, 'column' => 'id');
    }

    function getDonnee()
    {
        return array(
            'montant_verse' => $this->montant_verse
        ,
            'date_paiement' => $this->date_paiement
        ,
            'penalite' => $this->penalite
        ,
            'id_indexes' => $this->indexes->id
        ,
            'id_abone' => $this->id_abone
        ,
            'message' => $this->message
        );
    }

    function getNomTable()
    {
        return "facture";
    }

    public static function calculeMontantConsoTva($nouvel_index, $ancien_index, $tva, $entretient, $prix_eau)
    {
        $res = self::calculeMontantConsoEntretienCompteur($nouvel_index, $ancien_index, $entretient, $prix_eau) * ((int)((float)$tva / 100) + 1);
//        echo $res . '<br>';
        return $res;
    }

    public static function calculeMontantConsoEntretienCompteur($nouvel_index, $ancien_index, $entretient, $prix_eau)
    {
        $res = self::calculeMontantConso($nouvel_index, $ancien_index, $prix_eau) + (int)$entretient;
//        echo $res . '<br>';
        return $res;
    }

    public static function calculeImpaye($nouvel_index, $ancien_index, $tva, $entretient, $prix_eau, $impaye, $montant_verse, $penalite)
    {
        $res = self::calculeMontantConsoTva($nouvel_index, $ancien_index, $tva, $entretient, $prix_eau) + $penalite + $impaye - $montant_verse;
//        echo $res . '<br>';
        return $res;
    }

    public static function calculeMontantConso($nouvel_index, $ancien_index, $prix_eau)
    {
        $res = self::calculeConso($nouvel_index, $ancien_index) * (int)$prix_eau;
//        echo $res . '<br>';
        return $res;
    }

    public static function calculeMontantTotal($nouvel_index, $ancien_index, $tva, $entretient, $prix_eau, $impaye, $penalite)
    {
        $tmp = self::calculeMontantConsoTva($nouvel_index, $ancien_index, $tva, $entretient, $prix_eau);
        $res = $tmp + (int)$penalite + (int)$impaye;
//        echo $res . '  tmp2 = '.$tmp."<br> (int)$penalite  (int)$impaye <br>";
        return $res;
    }

    public static function calculeMontantRestant($nouvel_index, $ancien_index, $tva, $entretient, $prix_eau, $impaye, $penalite, $montant_versee)
    {
        $tmp =(int)(self::calculeMontantTotal($nouvel_index, $ancien_index, $tva, $entretient, $prix_eau, $impaye, $penalite)+ 0.0000001);
//        echo $tmp . '  ------------------------------<br>';
//        echo (int)$tmp . '  ------------------------------<br>';
//        var_dump($tmp);
        $res = $tmp - (int)$montant_versee;
//        echo $res . '    --------------------tmp=' . $tmp . " impaye = $impaye  montant _versee= (int)$montant_versee<br>  ";
        return $res;
    }

    public static function calculeMontantAValider($nouvel_index, $ancien_index, $tva, $entretient, $prix_eau, $impaye, $penalite, $montant_versee)
    {
        $result = self::calculeMontantTotal($nouvel_index, $ancien_index, $tva, $entretient, $prix_eau, $impaye, $penalite);
//        echo $result . '<br>';
        return $result <= $montant_versee ? $result : $montant_versee;
    }

    public static function calculeConso($nouvel_index, $ancien_index)
    {
        $res = (float)$nouvel_index - (float)$ancien_index;
//        echo $res . '<br>';
        return $res;
    }


    public function __construct($id, $ancien_index, $nouvel_index, $montant_verse, $date_paiement, $penalite, $id_mois_facturation, $id_abone, $message, $id_compteur)
    {
        $this->id = $id;
        $this->ancien_index = $ancien_index;
        $this->nouvel_index = $nouvel_index;
        $this->montant_verse = $montant_verse;
        $this->date_paiement = $date_paiement;
        $this->penalite = $penalite;
        $this->id_mois_facturation = $id_mois_facturation;
        $this->id_abone = $id_abone;
        $this->message = $message;

        $this->indexes = new Indexes('', $id_compteur, $id_mois_facturation, $ancien_index, $nouvel_index, '');


    }


    public static function getMonthFacture($id_mois, $id_aep)
    {
        if (!is_int($id_mois))
            return false;
        return self::prepare_query("
                select id.id, co.id as id_compteur, a.id_reseau,  m.id id_mois, f.id id_facture, a.nom, m.mois, ancien_index, nouvel_index, c.prix_entretient_compteur, co.numero_compteur, a.numero_compte_anticipation,
                c.prix_metre_cube_eau, c.prix_tva, penalite, sum(i.montant) as impaye, sum(impe.montant) as impaye2, f.montant_verse, f.date_paiement, date_depot, date_facturation, r.nom reseau, m.date_releve, numero_compte, nom_banque, total_verse, premier_index
                from facture f 
                    inner join indexes id on id.id = f.id_indexes
                    inner join abone as a on a.id = f.id_abone
                    inner join compteur_abone c_ab on c_ab.id_abone = a.id
                    inner join compteur co on co.id = c_ab.id_compteur
                    left join impaye impe on impe.id_facture = f.id
                    left join impaye i on (i.id_facture, a.id) in (select f2.id, a2.id from facture f2
                                                                inner join abone a2 on a2.id = f2.id_abone
                                                                inner join indexes id2 on id2.id = f2.id_indexes
                                                            where id2.id_mois_facturation<?)
                    inner join mois_facturation m on id.id_mois_facturation =m.id 
                    inner join  constante_reseau c on c.id=m.id_constante
                    inner join reseau r on r.id = a.id_reseau
                    inner join aep ae on ae.id = c.id_aep and ae.id = r.id_aep
                    inner join (
                                select sum(montant_verse) total_verse, id_abone from facture 
                                group by id_abone
                                ) as fact on fact.id_abone = a.id
                    inner join (select min(ancien_index) premier_index, id_compteur from indexes  group by id_compteur) as premier_index on premier_index.id_compteur = co.id 
                where  m.id=? and c.id_aep=? and r.id_aep=?
                group by a.id
                order by a.id
                
            ", array($id_mois, $id_mois, $id_aep, $id_aep));
        //select f.id from abone a2
        //                                                                            inner join facture f2 on a2.id = f2.id_abone
        //                                                                            inner join indexes id2 on id2.id = f2.id_indexes
        //                                                                       where a.id=a2.id and id2.id_mois_facturation<?)
    }
    public static function getMonthFacture2($id_mois, $id_aep)
    {
//        var_dump($id_mois, $id_aep);
        if (!is_int($id_mois))
            return false;
        $mois = self::prepare_query("select mois from mois_facturation where id = ?", array($id_mois));
        $mois = $mois->fetch();
        $mois = $mois['mois'];

        return self::prepare_query("
          
            SELECT 
                vaf.*, 
                COALESCE(impayer_cumule, 0) impayer_cumule, 
                montant_total + COALESCE(impayer_cumule, 0) as total_cumule,
                montant_total + COALESCE(impayer_cumule, 0) - montant_verse as restant_cumule
            FROM 
                vue_abones_facturation vaf
                left join ( 
                    SELECT SUM(montant_total - montant_verse) as impayer_cumule, id_abone
                    FROM vue_abones_facturation vaf2
                    WHERE vaf2.mois < ?
                    group by id_abone
                    ) as vaf_imp on vaf_imp.id_abone = vaf.id_abone
            WHERE 
                vaf.id_mois = ? 
                AND vaf.id_aep = ?
            ORDER BY 
                vaf.mois, vaf.id_abone

                
            ", array($mois, $id_mois, $id_aep));
    }

    public static function getMonthAllFactureData($id_mois, $id_aep)
    {
//        var_dump($id_mois, $id_aep);
        if (!is_int($id_mois))
            return false;
        $mois = self::prepare_query("select mois from mois_facturation where id = ?", array($id_mois));
        $mois = $mois->fetch();
        $mois = $mois['mois'];

        return self::prepare_query("
          
            SELECT 
                vaf.*, 
                COALESCE(impayer_cumule, 0) impayer_cumule, numero_compteur, r.nom AS reseau, nom_banque , numero_compte ,
                montant_total + COALESCE(impayer_cumule, 0) as total_cumule,
                montant_total + COALESCE(impayer_cumule, 0) - montant_verse as restant_cumule
            FROM 
                vue_abones_facturation vaf
                left join ( 
                    SELECT SUM(montant_total - montant_verse) as impayer_cumule, id_abone
                    FROM vue_abones_facturation vaf2
                    WHERE vaf2.mois < ?
                    group by id_abone
                    ) as vaf_imp on vaf_imp.id_abone = vaf.id_abone
                inner join compteur c on c.id = vaf.id_compteur
                inner join reseau r on r.id = vaf.id_reseau
                inner join aep on aep.id = vaf.id_aep
            
            
            WHERE 
                vaf.id_mois = ? 
                AND vaf.id_aep = ?
            ORDER BY 
                vaf.mois, vaf.id_abone

                
            ", array($mois, $id_mois, $id_aep));
        //select f.id from abone a2
        //                                                                            inner join facture f2 on a2.id = f2.id_abone
        //                                                                            inner join indexes id2 on id2.id = f2.id_indexes
        //                                                                       where a.id=a2.id and id2.id_mois_facturation<?)
    }

    public static function getAboneMonthIndexes($id_mois, $id_aep)
    {
        if (!is_int($id_mois))
            return false;
        return self::prepare_query("
                select id.id, co.id as id_compteur, a.id_reseau,  m.id id_mois, a.nom, m.mois, ancien_index, nouvel_index, co.numero_compteur,
             date_facturation, r.nom reseau
                from compteur co
                    inner join indexes id on id.id_compteur = co.id
                    inner join compteur_abone c_ab on c_ab.id_compteur = co.id
                    inner join abone a on a.id = c_ab.id_abone
                    inner join mois_facturation m on id.id_mois_facturation =m.id 
                    inner join reseau r on r.id = a.id_reseau
                where  m.id=? and r.id_aep=?
                group by a.id
                order by a.id
            ", array($id_mois, $id_aep));
    }

    public static function getReseauMonthIndexes($id_mois, $id_aep)
    {
        if (!is_int($id_mois))
            return false;
        return self::prepare_query("
                select id.id, co.id as id_compteur, r.id id_reseau,  m.id id_mois, r.nom, m.mois, ancien_index, nouvel_index, co.numero_compteur,
             date_facturation, r.nom reseau
                from compteur co
                    inner join indexes id on id.id_compteur = co.id
                    inner join compteur_reseau c_re on c_re.id_compteur = co.id
                    inner join reseau r on r.id = c_re.id_reseau
                    inner join mois_facturation m on id.id_mois_facturation =m.id 
                where  m.id=? and r.id_aep=?
                group by r.id
                order by r.id
            ", array($id_mois, $id_aep));
    }

    /*
    
                select f.id, f.id_abone, a.nom, f.ancien_index, f.nouvel_index, c.prix_entretient_compteur, 
                c.prix_metre_cube_eau, c.prix_tva, penalite, impaye, f.montant_verse, f.date_paiement 
                from abone a, facture f, constante_reseau c 
                where f.id_mois_facturation =$id_mois and c.id=$id_constante and a.id=f.id_abone order by a.id;
    */


    public static function getAncienneFacture($id_aep)
    {
        return Manager::prepare_query("
                select f.id as id, id_abone, id.id_compteur, m.mois, sum(montant) as impaye, penalite, prix_entretient_compteur, prix_metre_cube_eau,
                       prix_tva, montant_verse, nouvel_index, ancien_index
                from facture f 
                    left join indexes id on f.id_indexes = id.id
                    left join impaye i on f.id = i.id_facture 
                    inner join mois_facturation m on m.id = id.id_mois_facturation
                    inner join constante_reseau c on c.id = m.id_constante
                where  mois = (select max(mois) 
                               from mois_facturation m2, facture f2 
                               left join indexes id2 on f2.id_indexes = id2.id
                               where id2.id_mois_facturation = m2.id 
                                    and id_abone = f.id_abone) and
                        c.id_aep=?
                group by f.id
                ;
            ", array($id_aep)
        );
    }


    public static function getPeriodData($id_aep, $date_deut='2000-01', $date_fin='2100-12')
    {
        var_dump($date_deut, $date_fin);
        return self::prepare_query("
        SELECT
            mf.mois,
            a.id AS abonne_id,
            a.nom AS abonne_nom,
            co.numero_compteur,
            r.nom,
            co.derniers_index, id.ancien_index, id.nouvel_index,
            id.nouvel_index-id.ancien_index conso,
            c.prix_metre_cube_eau, c.prix_entretient_compteur, c.prix_tva,
            f.montant_verse,
            i.montant as impaye,
            f.penalite
        FROM
            abone a
                INNER JOIN reseau r on a.id_reseau = r.id
                INNER JOIN facture f ON f.id_abone = a.id
                INNER JOIN indexes id ON f.id_indexes = id.id 
                INNER JOIN compteur co on co.id = id.id_compteur
                LEFT JOIN impaye i on f.id = i.id_facture
                CROSS JOIN
            mois_facturation mf on mf.id = id.id_mois_facturation
                INNER JOIN constante_reseau c on mf.id_constante = c.id
        where mf.mois >=? and mf.mois <= ? and c.id_aep = ? and r.id_aep = ?
        ORDER BY
            mf.mois, a.id;
        ", array($date_deut, $date_fin, $id_aep, $id_aep));
        //on mf.id = id.id_mois_facturation
    }

    public static function effectuerRecouvrement($id_indexes, $montant_verse, $date_versement)
    {
        $id_facture = (int)$id_indexes;
        $montant_verse = (int)($montant_verse+0.000000001);
        return self::prepare_query("update facture f 
                                    inner join indexes i on f.id_indexes=i.id
                                    set montant_verse=?, date_paiement=? where i.id=?",
            array($montant_verse, $date_versement, $id_indexes));
    }

    public static function effectuerreleve($id_indexes, $index)
    {
        $id_indexes = (int)$id_indexes;
        $index = (float)$index;
        return self::prepare_query("update indexes 
                                         set nouvel_index=? 
                                         where id=?", array($index, $id_indexes));
    }

    public function save_facture()
    {
        $res = $this->indexes->ajouter();
        if (!$res)
            return 0;
        $estFacturable = Compteur::estFacturable($this->indexes->id_compteur);
        if (!$estFacturable)
            return 1;
//        $this-> = $this->indexes->id;
        return $this->ajouter();
    }


}