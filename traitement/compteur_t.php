<?php
@include_once("../donnees/compteur.php");
@include_once("donnees/compteur.php");

class Compteur_t{


    public static function getAllCompteurFromIdReseau($id_reseau){
        $req = Compteur::getAllByIdReseau($id_reseau);
//        $tab = array();

        $req = $req->fetchAll();
        return $req;
    }

    public static function ajouterCompteurReseau(){
        if(isset($_GET['ajouter_compteur_reseau'], $_GET['id_reseau'])){
            try {
                $id_reseau = $_GET['id_reseau'];
                $compteur = self::createCompteurFromPost($_POST);
                if($compteur instanceof Compteur){
                    $res = $compteur->save_compteur_reseau($id_reseau);
                    if($res)
                        header("Location: ../index.php?page=reseau&id_reseau=$id_reseau&operation=success");
                    else
                        header("Location: ../index.php?page=reseau&id_reseau=$id_reseau&operation=error&message=erreurd'ajour");
                }
            }catch (Exception $e){
                echo $e->getMessage();
                header("Location: ../index.php?page=reseau&id_reseau=$id_reseau&operation=error&message=erreur innattendu dau programme");
            }
        }
    }


    public static function createCompteurFromPost(array $postData) {
        try {
            // Vérification des champs requis et suppression des espaces
            $id = isset($postData['id']) ? trim($postData['id']) : '';
            $numero_compteur = isset($postData['numero_compteur']) ? trim($postData['numero_compteur']) : '';
            $longitude = isset($postData['longitude']) ? floatval($postData['longitude']) : 0.0;
            $latitude = isset($postData['latitude']) ? floatval($postData['latitude']) : 0.0;
            $dernier_index = isset($postData['derniers_index']) ? floatval($postData['derniers_index']) : 0.0;
            $description = isset($postData['description']) ? trim($postData['description']) : '';
            // Validation des données (ajoutez d'autres validations si nécessaire)
            if (empty($numero_compteur)) {
                throw new Exception("Le numéro de compteur est requis.");
            }

            // Création de l'objet Compteur
            $compteur = new Compteur($id, $numero_compteur, $longitude, $latitude, $dernier_index, $description);

            return $compteur; // Retourner l'objet crée
        } catch (Exception $e) {
            // Gérer l'erreur (vous pouvez aussi logger l'erreur selon vos besoins)
            echo "Erreur : " . $e->getMessage();
            return null; // Retourner null en cas d'erreur
        }
    }
}

Compteur_t::ajouterCompteurReseau();
