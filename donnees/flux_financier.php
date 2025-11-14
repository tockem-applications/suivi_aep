<?php

@include_once("../donnees/manager.php");
@include_once("donnees/manager.php");

class FluxFinancier extends Manager
{
    public $id;              // Identifiant unique
    public $date;            // Date du flux financier
    public $mois;            // Mois du flux financier
    public $libele;          // Libellé du flux
    public $prix;            // Montant du flux
    public $id_aep;            // Type de flux (sortie ou entrée)
    public $type;            // Type de flux (sortie ou entrée)
    public $description;     // Description du flux

    public static function getFinanceData($mois = '', $type = '', $prix_min = 0, $id_aep = '')
    {
        return self::prepare_query("
                            SELECT * 
                            from flux_financier 
                            where mois like concat('%', ?) and 
                                  type like concat('%', ?) and 
                                  prix>=? and
                                  id_aep=?
                            order by mois desc;", array($mois, $type, $prix_min, $id_aep));
    }

    public static function getFluxById($id)
    {
        return self::prepare_query("SELECT * from flux_financier where id = ?;", array($id));
    }

    public static function getAllVersements($id_aep)
    {
        return self::prepare_query("
            select rq.*, 'no_action' as actions from  (SELECT 
                f.id,
                CONCAT(mf.mois, '-28') as date,
                mf.mois,
                CONCAT('Recouvrements - ', mf.mois) as libele,
                sum(f.montant_verse) as prix,
                'entree' as type,
                CONCAT('versements de ', f.montant_verse, ' FCFA pour le mois de - ', mf.mois) as description,
                ? as id_aep
            FROM facture f
            INNER JOIN indexes i on f.id_indexes = i.id
            INNER JOIN mois_facturation mf ON i.id_mois_facturation = mf.id
            inner join constante_reseau cr on mf.id_constante = cr.id
            WHERE cr.id_aep = ? AND f.montant_verse > 0 and mf.mois <> '2020-01'
            group by mf.id
            
            union
            
            SELECT 
                ba.id,
                CONCAT(ba.mois, '-28') as date,
                ba.mois,
                CONCAT('Branchements - ', ba.mois) as libele,
                sum(ba.versement_fcfa) as prix,
                'entree' as type,
                CONCAT('Branchements de ', ba.versement_fcfa, ' FCFA pour le mois de - ', ba.mois) as description,
                ? as id_aep
            FROM abone a
            INNER JOIN compteur_abone ca on a.id = ca.id_abone
            INNER JOIN compteur c on c.id = ca.id_compteur
            INNER JOIN reseau r on a.id_reseau = r.id
            INNER JOIN branchement_abonne as ba on ba.id_abone = a.id
            WHERE r.id_aep =? 
            group by ba.mois) as rq
            ORDER BY rq.date DESC;
        ", array($id_aep, $id_aep, $id_aep, $id_aep));
    }

    function getConstraint()
    {
        return array('value' => $this->id, 'column' => 'id');
    }

    function getDonnee()
    {
        return array(
            'date' => $this->date,
            'mois' => $this->mois,
            'libele' => $this->libele,
            'prix' => $this->prix,
            'type' => $this->type,
            'id_aep' => $this->id_aep,
            'description' => $this->description
        );
    }

    function getNomTable()
    {
        return "flux_financier";
    }

    public static function delete_flux($id)
    {
        return self::prepare_query("DELETE FROM flux_financier where id =?", array($id));
    }

    public function __construct($id, $date, $libele, $prix, $type, $description, $mois = '', $id_aep = 1)
    {
        $this->id = $id;
        $this->date = $date;
        $this->mois = $mois == '' ? date('Y-m', strtotime($date)) : $mois;
        $this->libele = $libele;
        $this->prix = $prix;
        $this->type = $type;
        $this->id_aep = $id_aep;
        $this->description = $description;
    }
}
