<?php

//require_once("manager.php");
@include_once("../donnees/manager.php");
@include_once("donnees/manager.php");

class Facture extends Manager
{

    //public $id;
    public $ancien_index;
    public $nouvel_index;
    public $montant_verse;
    public $date_paiement;
    public $penalite;
    public $id_mois_facturation;
    public $id_abone;
    public $message;

    public static function deleteByIdFacture($id_facture)
    {
        return self::prepare_query("DELETE FROM impaye WHERE id_facture = ?", array($id_facture));
    }


    function getconstraint()
    {
        return array('value' => $this->id, 'column' => 'id');
    }
    function getDonnee()
    {
        return array(
            'ancien_index' => $this->ancien_index
            ,
            'nouvel_index' => $this->nouvel_index
            ,
            'montant_verse' => $this->montant_verse
            ,
            'date_paiement' => $this->date_paiement
            ,
            'penalite' => $this->penalite
            ,
            'id_mois_facturation' => $this->id_mois_facturation
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
        return self::calculeMontantConsoEntretienCompteur($nouvel_index, $ancien_index, $entretient, $prix_eau) * ($tva / 100 + 1);
    }

    public static function calculeMontantConsoEntretienCompteur($nouvel_index, $ancien_index, $entretient, $prix_eau)
    {
        return self::calculeMontantConso($nouvel_index, $ancien_index, $prix_eau) + $entretient;
    }

    public static function calculeImpaye($nouvel_index, $ancien_index, $tva, $entretient, $prix_eau, $impaye, $montant_verse, $penalite)
    {
        return self::calculeMontantConsoTva($nouvel_index, $ancien_index, $tva, $entretient, $prix_eau) + $penalite + $impaye - $montant_verse;
    }

    public static function calculeMontantConso($nouvel_index, $ancien_index, $prix_eau)
    {
        return self::calculeConso($nouvel_index, $ancien_index) * $prix_eau;
    }

    public static function calculeMontantTotal($nouvel_index, $ancien_index, $tva, $entretient, $prix_eau, $impaye, $penalite)
    {
        return self::calculeMontantConsoTva($nouvel_index, $ancien_index, $tva, $entretient, $prix_eau) + $penalite + $impaye;
    }

    public static function calculeMontantRestant($nouvel_index, $ancien_index, $tva, $entretient, $prix_eau, $impaye, $penalite, $montant_versee)
    {
        return self::calculeMontantTotal($nouvel_index, $ancien_index, $tva, $entretient, $prix_eau, $impaye, $penalite) - $montant_versee;
    }

    public static function calculeConso($nouvel_index, $ancien_index)
    {
        return $nouvel_index - $ancien_index;
    }


    public function __construct($id, $ancien_index, $nouvel_index, $montant_verse, $date_paiement, $penalite, $id_mois_facturation, $id_abone, $message)
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


    }


    public static function getMonthFacture($id_mois, $id_aep)
    {
        if (!is_int($id_mois))
            return false;
        return self::prepare_query("
                select f.id, f.id_abone, m.id id_mois, f.id id_facture, a.nom, m.mois, f.ancien_index, f.nouvel_index, c.prix_entretient_compteur, a.numero_compteur, a.numero_compte_anticipation,
                c.prix_metre_cube_eau, c.prix_tva, penalite, sum(i.montant) as impaye, f.montant_verse, f.date_paiement, date_depot, date_facturation, r.nom reseau
                from facture f 
                    inner join abone a on a.id = f.id_abone
                    left join impaye i on i.id_facture in (select f.id from abone a2, facture f where a.id=a2.id and a2.id = f.id_abone and f.id_mois_facturation<$id_mois)
                    inner join mois_facturation m on f.id_mois_facturation =m.id 
                    inner join  constante_reseau c on c.id=m.id_constante
                    inner join reseau r on r.id = a.id_reseau
                where  m.id=?  and a.type_compteur = 'distribution' and c.id_aep=? and r.id_aep=?
                group by a.id
                order by a.id
                limit 4
            ", array($id_mois, $id_aep, $id_aep));
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
                select f.id as id, id_abone, m.mois, sum(montant) as impaye, penalite, prix_entretient_compteur, prix_metre_cube_eau, prix_tva, montant_verse, nouvel_index, ancien_index
                from facture f 
                    left join impaye i on f.id = i.id_facture 
                    inner join mois_facturation m on m.id = f.id_mois_facturation
                    inner join constante_reseau c on c.id = m.id_constante
                where  mois = (select max(mois) 
                               from mois_facturation m2, facture f2 
                               where f2.id_mois_facturation = m2.id 
                                    and id_abone = f.id_abone) and
                        c.id_aep=?
                group by f.id
                ;
            ", array($id_aep)
        );
    }


    public static function getPeriodData($date_deut, $date_fin){
        return self::prepare_query("
        SELECT
            mf.mois,
            a.id AS abonne_id,
            a.nom AS abonne_nom,
            a.numero_compteur,
            r.nom,
            derniers_index, ancien_index, nouvel_index,
            nouvel_index-ancien_index conso,
            prix_metre_cube_eau, prix_entretient_compteur, c.prix_tva,
            f.montant_verse,
            f.impaye,
            f.penalite
        FROM
            abone a
                CROSS JOIN
            mois_facturation mf
                LEFT JOIN
            facture f ON f.id_mois_facturation = mf.id AND f.id_abone = a.id
                INNER JOIN reseau r on a.id_reseau = r.id
                INNER JOIN constante_reseau c on mf.id_constante = c.id
        where
            mf.mois >=? and mf.mois <= ?
        ORDER BY
            mf.mois, a.id;
        ", array($date_deut, $date_fin));
    }

    public static function effectuerRecouvrement($id_facture, $montant_verse, $date_versement)
    {
        $id_facture = (int)$id_facture;
        $montant_verse = (int)$montant_verse;
        return self::query("update facture set montant_verse='$montant_verse', date_paiement='$date_versement' where id=$id_facture");
    }
    public static function effectuerreleve($id_facture, $index)
    {
        $id_facture = (int)$id_facture;
        $index = (float)$index;
        return self::query("update facture set nouvel_index=$index where id=$id_facture");
    }



}