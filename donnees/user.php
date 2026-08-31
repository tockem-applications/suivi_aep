<?php
@include_once("../donnees/manager.php");
@include_once("donnees/manager.php");

class User extends Manager
{
    public $email;
    public $nom;
    public $prenom;
    public $password;
    public $salt;
    public $numero_telephone;

    public function __construct($id = 0, $email = '', $nom = '', $prenom = '', $numero_telephone = '', $password = '', $salt = '')
    {
        $this->id = $id;
        $this->email = $email;
        $this->nom = $nom;
        $this->password = $password;
        $this->prenom = $prenom;
        $this->salt = $salt;
        $this->numero_telephone = $numero_telephone;
    }

    /**
     * @throws Exception
     */
    public static function verifier_clef($value){
        $req = self::prepare_query("select id from clefs 
            left join user_clefs uc on uc.clef_id=clefs.id  
            where value=? and user_id is null 
        ", array($value));
        $data = $req->fetchAll();
        if (empty($data)) {
            return false;
        }
        return $data[0]['id'];
    }

    public static function connectUserToKey($id_user, $id_key){
        return self::prepare_query("insert into user_clefs(user_id, clef_id) values(?, ?)", array($id_user, $id_key));
    }



    public function getconstraint()
    {
        return array('value' => $this->id, 'column' => 'id');
    }

    public function getDonnee()
    {
        return array(
            'email' => $this->email,
            'nom' => $this->nom,
            'prenom' => $this->prenom,
            'password' => $this->password,
            'salt' => $this->salt,
            'numero_telephone' => $this->numero_telephone
        );
    }

    public function getNomTable()
    {
        return "users";
    }

    /**
     * Récupère un utilisateur par ID
     * @param int $id ID de l'utilisateur
     * @return array|null
     */
    public static function getUser($id)
    {
        return self::prepare_query("SELECT * FROM users WHERE id = ?", array($id))->fetch();
    }

    /**
     * Récupère un utilisateur par email
     * @param string $email Email de l'utilisateur
     * @return array|null
     */
    public static function getUserByEmail($email)
    {
        return self::prepare_query("SELECT * FROM users WHERE email = ?", array($email))->fetch();
    }

    /**
     * Supprime un utilisateur par ID
     * @param int $id ID de l'utilisateur
     * @return bool
     */
    public static function deleteById($id)
    {
        return self::prepare_query("DELETE FROM users WHERE id = ?", array($id))->rowCount() > 0;
    }
}