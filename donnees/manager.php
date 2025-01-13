<?php
//H&*nh9w%nw+JU
//require_once("connexion.php");
session_start();
//session_
@include_once("../donnees/connexion.php");
@include_once("donnees/connexion.php");





function getLetterMonth($mois){
    $lettreMonth = array(
        '01'=>'Janvier',
        '02'=>'Fevrier',
        '03'=>'Mars',
        '04'=>'Avril',
        '05'=>'Mai',
        '06'=>'Juin',
        '07'=>'juiller',
        '08'=>'Aout',
        '09'=>'Septembre',
        '10'=>'Octobre',
        '11'=>'Novembre',
        '12'=>'Decembre'
        
    );
    $tab = explode('-', $mois);
    $index = $tab[1];
    return isset($lettreMonth[$index]) ? $lettreMonth[$index].' '.$tab[0]:'';
}

function addZeros($chaine, $nomber_of_zero=5){
    $reste = $nomber_of_zero - count($chaine);
    $res = $chaine;
    for ($i=0; $i < $reste; $i++) { 
        $res = '0'.$res;
    }
    return $res;
}


abstract class Manager{

    protected static $bd = null;

    private static $bdXml = null;

    public $id;
    
    abstract  function getDonnee();

    abstract function getNomTable();

    abstract function getconstraint();

    public function __construct(){
        $this->connecter();
    }

    public function connecter(){
        if(self::$bd == null) {
            //self::$bdXml =Connexion::connectXml();
            echo "------------------------------<br>";
            self::$bd = Connexion::connect();
        }
    }

    public static function save(){
        self::$bdXml->saveXml('donnees/bd.tld');
    }


    public static function getAllXml($table_name){
        if(self::$bdXml == null)
            self::$bdXml = Connexion::connectXml();
        $liste = self::$bdXml[$table_name.'s'];
        return $liste;
    }

    public function ajouterXml(){
        $donnees =$this->getDonnee();
        var_dump(self::$bdXml[$this->getNomTable().'s']);

        var_dump(self::$bdXml['Proprietaire']);
        $element = self::$bdXml[$this->getNomTable().'s']->addChild($this->getNomTable(), '');
        $element->addAttribute('id', self::$bdXml[$this->getNomTable()]->count());
        foreach( $donnees as $clef=>$valeur){
            $element->addChild($clef, $valeur);
        }
        self::save();
        return true;
    }

    public function ajouterXml2($id=0){
        $donnees =$this->getDonnee();
//        var_dump(self::$bdXml[$this->getNomTable().'s']);
//
        $object = $this->getObject();
        //var_dump($object);
        if($id == 0){
            $id = $object->id+1;
            $object->id = $id;
        }
//        var_dump(self::$bdXml['Proprietaire']);

        $element = $object->addChild($this->getNomTable(), '');
        $element->addAttribute('id', $id);
        foreach( $donnees as $clef=>$valeur){
            //echo $clef.' '.$valeur.' <br>';
            $element->addChild($clef, $valeur);
            
        }
        //var_dump( $element);
        //var_dump( $object);
        $object->saveXml();
        self::save();
        $this->id = $id;
        return $id;
    }

    public function deleteXml($id_delete){
        try{
            //$elment = self::getOneXml($this->getNomTable(), $id_delete);
            $objet = self::getObject2($this->getNomTable());
            for($i= 0;$i<count($objet);$i++){
                $ligne = $objet[$i];
                if($ligne->attributes()->id == $id_delete){
                    unset($objet[$i]);
                    break;
                }
            }
            $objet->saveXml();
            //var_dump($elment);
            //unset($elment);
            self::save();
            return true;
        }catch (Exception $e){
            return false;
        }
    }

    public function update(){
        $this->connecter();
        $nomTable =$this->getNomTable();
        $donnees =$this->getDonnee();
        $contrainte = $this->getconstraint();
        $colones = "";
        $valeurs = "";
        $i = 0;
        $data = array();
        foreach( $donnees as $clef=>$valeur){
            if($i == 0){
                $colones = "$clef = ?";
//                $valeurs = "?";
            }
            else{
                $colones = "$colones, $clef = ?";
//                $valeurs = "$valeurs, ?";
            }
            $data[$i] = $valeur;
            $i++;
        }
        $data[$i]= $contrainte['value'];
        try {
            $req = self::$bd->prepare("update $nomTable set $colones where ".$contrainte['column']."= ?;");
            $req->execute($data);
//            $this->updateXml();
            return  $req;
        }catch (Exception $e){
            echo "echec de modification du(de la) $nomTable";
            return false;
        }
    }



    public function updateXml(){
        $donnees =$this->getDonnee();
//        var_dump(self::$bdXml[$this->getNomTable().'s']);
//
        //$object = $this->getObject();
        $objet = self::getObject2($this->getNomTable());
        $id = 0;
        for($i= 0;$i<count($objet);$i++){
            $ligne = $objet[$i];
            if($ligne->attributes()->id == $this->id){
                unset($objet[$i]);
                $tab = $this->getdonnee();
                foreach($tab as $key => $value){
                    echo $key;
                    $objet[$i]->$key = $value;
                    $objet[$i]->addChild($key, $value);
                }
                break;
            }
        }
        //$objet->saveXml();
        self::save();
        //var_dump($object);
//        var_dump(self::$bdXml['Proprietaire']);
        //$this->deleteXml($this->id);
        //$this->ajouterXml2($this->id);
//        require ('ooooooooooooooooooooo');
        return true;
    }

    public static function getOneXml($nomTable, $id){
        if(self::$bdXml == null)
            self::$bdXml = Connexion::connectXml();
        $liste = self::getObject2($nomTable);
        
        $element =null;
        
        foreach ($liste as $cle=>$valeur){
            if ($valeur->attributes()->id == $id){
                $element = $valeur;
                break;
            }
        }
        self::save();
        return $element;
    }

    public  function getObject()
    {
        self::$bdXml =Connexion::connectXml();
        //self::$bd = Connexion::connect();
        $nomTable = $this->getNomTable().'s';
        if($nomTable == 'Abones')
            return self::$bdXml->Abones;
        else if($nomTable == 'Proprietaire')
            return self::$bdXml->Proprietaires;
        else if($nomTable == 'Locataire')
            return self::$bdXml->Locataires;
        else if($nomTable == 'Tarif')
            return self::$bdXml->Tarifs;
        else if($nomTable == 'Appartement')
            return self::$bdXml->Appartements;
        else if($nomTable == 'Contrat')
            return self::$bdXml->Contrats;

    }

    public static function getObject2($nomTable)
    {
//        if(self::$bd == null) {
            self::$bdXml =Connexion::connectXml();
            //self::$bd = Connexion::connect();
//        }
        if($nomTable == 'Abone')
            return self::$bdXml->Abones->Abone;
        else if($nomTable == 'Proprietaire')
            return self::$bdXml->Proprieataires->Proprietaire;
        else if($nomTable == 'Locataire')
            return self::$bdXml->Locataires->Locataire;
        else if($nomTable == 'Tarif')
            return self::$bdXml->Tarifs->Tarif;
        else if($nomTable == 'Appartement')
            return self::$bdXml->Appartements->Appartement;
        return null;

    }

    public static function deleteObject2($nomTable, $index)
    {
        self::$bdXml =Connexion::connectXml();
        //self::$bd = Connexion::connect();
        echo '<br>'.$nomTable.'<br>';
        if($nomTable == 'Admin')
             unset(self::$bdXml->Admins->Admin[$index]);
        else if($nomTable == 'Proprietaire')
             unset(self::$bdXml->Proprieataires->Proprietaire[$index]);
        else if($nomTable == 'Locataire')
            unset(self::$bdXml->Locataires->Locataire[$index]);
        else if($nomTable == "Tarif") {
            var_dump(self::$bdXml->Tarifs->Tarif[$index]);
            echo '<br>'.self::$bdXml->Tarifs.'<br>'. $index;
            unset(self::$bdXml->Tarifs->Tarif[$index]);
        }
        else if($nomTable == 'Appartement')
             unset(self::$bdXml->Appartements->Appartement[$index]);
        return null;



    }



    public function ajouter(){
        $this->connecter();
        $nomTable =$this->getNomTable();
        $donnees =$this->getDonnee();
        var_dump($donnees);
        $colones = "";
        $valeurs = "";
        $i = 0;
        $data = array();
        foreach( $donnees as $clef=>$valeur){
            if($i == 0){
                $colones = "$clef";
                $valeurs = "?";
            }
            else{
                $colones = "$colones, $clef ";
                $valeurs = "$valeurs, ?";
            }
            $data[$i] = $valeur;
            $i++;
        }
        try {
            echo "insert into $nomTable ($colones) values ($valeurs);";
            var_dump($data);
            $req = self::$bd->prepare("insert into $nomTable ($colones) values ($valeurs);");
//            $this->ajouterXml();
//            require ('fkgkfkgfk');
            $res = $req->execute($data);
            $this->id = self::$bd->lastInsertId($nomTable);
            $this->id = self::$bd->lastInsertId($nomTable);
            echo 'yyyyyyyyyyyyyyyyyyyyyyyyyyyyyyyyyyyy';
            echo $res;
            echo 'yyyyyyyyyyyyyyyyyyyyyyyyyyyyyyyyyyyy';
            return $res;

        }catch (Exception $e){
            echo "echec dajout du(de la) $nomTable <br>";
            echo $e;
            return false;
        }
    }

    public static function getAll($table_name){
        if(self::$bd == null)
            self::$bd = Connexion::connect();
        $req = self::$bd->query("select * from $table_name");
        return $req;
    }



    public static function query($query){
        if(self::$bd == null)
            self::$bd = Connexion::connect();
        $req = self::$bd->query($query);
//        $req->execute(array("table_name"=>htmlspecialchars($table_name)));
        return $req;
    }

    public static function prepare_query($query, $data){
        try {
            if(self::$bd == null)
                self::$bd = Connexion::connect();
            $req = self::$bd->prepare($query);
            $res = $req->execute($data);
//        var_dump($data);
            return $res ? $req: false;
        }catch (Exception $e){
            echo $e."<br>";
            echo $query;
//            throw new Exception($e);
        }
        return false;
    }



    public static function uploadImage($image_name){
        if(isset($_FILES[$image_name])) {
//            $file = $_FILES[$image_name];
//        var_dump($file);
            if ($_FILES[$image_name]['name'] == '')
                return '';
            move_uploaded_file($_FILES[$image_name]['tmp_name'], '../donnees/imports/' . basename($_FILES[$image_name]['name']));
            $name = $_FILES[$image_name]['name'];
//            require_once ('../donnees/images');
            return "../donnees/imports/$name";
        }
        return '';
    }
    public function delete($id_delete){
        if(self::$bd == null)
            self::$bd = Connexion::connect();
        $nomTable =$this->getNomTable();
//        $this->deleteXml($id_delete);
        $req = self::$bd->prepare('delete from '.$nomTable.' where id=?;');
        $req->execute(array($id_delete));
        return $req;
    }
    public static function delete_by_id($nom_table, $id_delete){
        if(self::$bd == null)
            self::$bd = Connexion::connect();
//        $this->deleteXml($id_delete);
        $req = self::$bd->prepare('delete from '.$nom_table.' where id=?;');
        $request = $req->execute(array($id_delete));
        return $req;
    }

   
    public static function getOne( $id, $nom_table='abone'){
        if(self::$bd == null)
            self::$bd = Connexion::connect();
        $req = self::$bd->prepare("select * from $nom_table a where id=?;");
        $req->execute(array( $id));
        return $req;
    }
    
    public static function getOneByEmail($nomTable, $email){
        if(self::$bd == null)
            self::$bd = Connexion::connect();
        $req = self::$bd->prepare("select * from $nomTable where email=?;");
        $req->execute(array( $email));
        return $req;
    }
    public static function getOneByEmailXml($nomTable, $email){
        if(self::$bdXml == null)
            self::$bdXml = Connexion::connectXml();
        $liste = self::$bdXml[$nomTable.'s'];
        $element =null;
        foreach ($liste as $cle=>$valeur){
            if ($valeur[$nomTable]== $email)
                $element = $valeur;
        }
        return $element;
    }
}



