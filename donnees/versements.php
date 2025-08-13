<?php

@include_once("../donnees/manager.php");
@include_once("donnees/manager.php");

class Versement extends Manager
{
    public $id;
    public $montant;
    public $date_versement;
    public $id_mois_facturation;
    public $id_redevance;

    function getConstraint()
    {
        return array('value' => $this->id, 'column' => 'id');
    }

    function getDonnee()
    {
        return array(
            'montant' => $this->montant,
            'date_versement' => $this->date_versement,
            'id_mois_facturation' => $this->id_mois_facturation,
            'id_redevance' => $this->id_redevance
        );
    }

    function getNomTable()
    {
        return "versements";
    }


    /**
     * @throws Exception
     */
    public static function getMontantVerse($id_redevance, $id_mois_facturation){
        $req = self::prepare_query("
            SELECT sum(montant) montant  from versements where id_redevance = ? and id_mois_facturation = ?
        ", array($id_redevance, $id_mois_facturation))->fetch();
//        var_dump($req['montant']);
        if (!count($req)){
            return 0;
        }
        return $req['montant'];
    }
    public static function CalculeVersement($id_aep)
    {
       return self::prepare_query(" 
        SELECT m.id as id_mois_facturation, r.id as id_redevance,  sum(nouvel_index - ancien_index) as conso, 
            sum(montant_verse) as montant_verse, 
            prix_metre_cube_eau, prix_entretient_compteur, r.libele, count(f.id) nombre, m.mois, pourcentage
        from facture f 
            inner join indexes i on f.id_indexes = i.id
            inner join mois_facturation m on i.id_mois_facturation = m.id
            inner join constante_reseau as c on m.id_constante = c.id
            inner join redevance r on c.id_aep = r.id_aep
        where c.id_aep=? and m.mois >= r.mois_debut
        group by r.id, m.id
        order by m.mois desc
        ", array($id_aep) );
//(select id, id_mois_facturation, id_redevance sum(v2.montant) from versements v2 where m.id = v2.id_mois_facturation and r.id = v2.id_redevance)
    }

    public function __construct($id, $montant, $date_versement, $id_mois_facturation, $id_redevance)
    {
        $this->id = $id;
        $this->montant = $montant;
        $this->date_versement = $date_versement;
        $this->id_mois_facturation = $id_mois_facturation;
        $this->id_redevance = $id_redevance;
    }

    // Méthode pour récupérer un versement par ID
    public static function getVersement($id)
    {
        $query = Manager::prepare_query(
            "SELECT * FROM versements WHERE id = ?",
            array($id)
        );
        $data = $query->fetch();
        if ($data) {
            return new Versement(
                $data['id'],
                $data['montant'],
                $data['date_versement'],
                $data['id_mois_facturation'],
                $data['id_redevance']
            );
        }
        return null;
    }
}