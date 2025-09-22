<?php
include("../donnees/manager.php");
header('Content-Type: text/html; charset=UTF-8');
$uploadDir = '../donnees/csv/uploads/';
$message = '';
$data = array();

/**
 * @param $chaine
 * @return float
 */
function str_to_float($chaine)
{
    // Remplacer la virgule par un point
    $chaine = str_replace(',', '.', $chaine);
    // Convertir en float
    return floatval($chaine);
}

/**
 * @param $bd
 * @param $prix_metre_cube_eau
 * @param $prix_entretient_compteur
 * @param $pourcentage_tva
 * @param $date_creation
 * @param $est_actif
 * @param $description
 * @param $id_aep
 * @return mixed
 * @throws Exception
 */
function addConstanceGetID($bd, $prix_metre_cube_eau, $prix_entretient_compteur, $pourcentage_tva, $date_creation, $est_actif, $description, $id_aep)
{
    try {
        Manager::prepare_query("update constante_reseau set est_actif=false where id_aep=? and est_actif=true", array($id_aep));
        Manager::prepare_query("insert into constante_reseau 
    (prix_metre_cube_eau, prix_entretient_compteur,prix_tva, date_creation, est_actif, description, id_aep)
     values (?, ?, ?, ?, ?, ?, ? )
    ", array($prix_metre_cube_eau, $prix_entretient_compteur, $pourcentage_tva, $date_creation, $est_actif, $description, $id_aep));
        return Manager::getBdd()->lastInsertId();
    } catch (PDOException $e) {
        // Gestion des erreurs
        throw new Exception("Erreur lors de l'ajout de la constante réseau : " . $e->getMessage());
    }
}

/**
 * @param $bd
 * @param $mois
 * @param $date_facturation
 * @param $date_depot
 * @param $id_constante
 * @param $description
 * @param $est_actif
 * @param $date_releve
 * @return mixed
 * @throws Exception
 */
function addMoisFacturationGetID($bd, $mois, $date_facturation, $date_depot, $id_constante, $description, $est_actif, $date_releve, $id_aep)
{
    try {
        // Désactiver les autres mois de facturation actifs pour le même id_constante
        if ($est_actif) {
            Manager::prepare_query("UPDATE mois_facturation SET est_actif = ? 
                        WHERE est_actif = ? and  id_constante in (select id from constante_reseau where id_aep = ?)",
                array(false, true, $id_aep));
        }

        // Préparation de la requête d'insertion
        $sql = "INSERT INTO mois_facturation (
            mois, 
            date_facturation, 
            date_depot, 
            id_constante, 
            description, 
            est_actif, 
            date_releve
        ) VALUES (
            ?, ?, ?, ?, ?, ?, ?
        )";

        // Exécution de la requête avec prepare_query
        Manager::prepare_query($sql, array(
            $mois,
            $date_facturation,
            $date_depot,
            $id_constante,
            $description,
            $est_actif,
            $date_releve
        ));

        // Retourner l'ID du mois de facturation inséré
        return Manager::getBdd()->lastInsertId();

    } catch (PDOException $e) {
        // Gestion des erreurs
        throw new Exception("Erreur lors de l'ajout du mois de facturation : " . $e->getMessage());
    }
}


/**
 * @param $bd
 * @param $id_compteur
 * @param $id_mois_facturation
 * @param $ancien_index
 * @param $nouvel_index
 * @param $message
 * @return mixed
 * @throws Exception
 */
function addIndexGetID($bd, $id_compteur, $id_mois_facturation, $ancien_index, $nouvel_index, $message)
{
    try {
        // Préparation de la requête d'insertion

        $ancien_index = str_to_float($ancien_index);
        $nouvel_index = max(str_to_float($nouvel_index), $ancien_index);
        $sql = "INSERT INTO indexes (
            id_compteur, 
            id_mois_facturation, 
            ancien_index, 
            nouvel_index, 
            message
        ) VALUES (
            ?, ?, ?, ?, ?
        )";

        // Exécution de la requête avec prepare_query
        Manager::prepare_query($sql, array(
            $id_compteur,
            $id_mois_facturation,
            $ancien_index,
            $nouvel_index,
            $message
        ));

        // Retourner l'ID de l'index inséré
        return Manager::getBdd()->lastInsertId();

    } catch (PDOException $e) {
        // Gestion des erreurs
        throw new Exception("Erreur lors de l'ajout de l'index : " . $e->getMessage());
    }
}

/**
 * @param $bd
 * @param $id_indexes
 * @param $montant_verse
 * @param $date_paiement
 * @param $penalite
 * @param $id_abone
 * @param $message
 * @return mixed
 * @throws Exception
 */
function addFactureGetID($bd, $id_indexes, $montant_verse, $date_paiement, $penalite, $id_abone, $message)
{
    try {
        // Préparation de la requête d'insertion
        $sql = "INSERT INTO facture (
            id_indexes, 
            montant_verse, 
            date_paiement, 
            penalite, 
            id_abone, 
            message
        ) VALUES (
            ?, ?, ?, ?, ?, ?
        )";

        // Exécution de la requête avec prepare_query
        Manager::prepare_query($sql, array(
            $id_indexes,
            $montant_verse,
            $date_paiement,
            $penalite,
            $id_abone,
            $message
        ));

        // Retourner l'ID de la facture insérée
        return Manager::getBdd()->lastInsertId();

    } catch (PDOException $e) {
        // Gestion des erreurs
        throw new Exception("Erreur lors de l'ajout de la facture : " . $e->getMessage());
    }
}

/**
 * @param $bd
 * @param $numero_compteur
 * @param $longitude
 * @param $latitude
 * @param $derniers_index
 * @param $description
 * @return mixed
 * @throws Exception
 */
function addCompteurGetID($bd, $numero_compteur, $longitude, $latitude, $derniers_index, $description)
{
    $derniers_index = str_to_float($derniers_index);
    try {
        // Préparation de la requête d'insertion
        $sql = "INSERT INTO compteur (
            numero_compteur, 
            longitude, 
            latitude, 
            derniers_index, 
            description
        ) VALUES (
            ?, ?, ?, ?, ?
        )";

        // Exécution de la requête avec prepare_query
        Manager::prepare_query($sql, array(
            $numero_compteur,
            $longitude,
            $latitude,
            $derniers_index,
            $description
        ));

        // Pasha de la requête avec prepare_query
        return Manager::getBdd()->lastInsertId();

    } catch (PDOException $e) {
        // Gestion des erreurs
        throw new Exception("Erreur lors de l'ajout du compteur : " . $e->getMessage());
    }
}

/**
 * @param $bd
 * @param $nom
 * @param $abreviation
 * @param $date_creation
 * @param $description_reseau
 * @param $id_aep
 * @return mixed
 * @throws Exception
 */
function addReseauGetID($bd, $nom, $abreviation, $date_creation, $description_reseau, $id_aep)
{
    try {
        $req = Manager::prepare_query('select * from reseau where id_aep=? and nom=? limit 1', array($id_aep, $nom));
        $result = $req->fetchAll(PDO::FETCH_ASSOC);
        $id_reseau = empty($result) ? 0 : $result[0]["id"];
        if ($id_reseau)
            return $id_reseau;
        // Préparation de la requête d'insertion
        $sql = "INSERT INTO reseau (
            nom, 
            abreviation, 
            date_creation, 
            description_reseau, 
            id_aep
        ) VALUES (
            ?, ?, ?, ?, ?
        )";

        // Exécution de la requête avec prepare_query
        Manager::prepare_query($sql, array(
            $nom,
            $abreviation,
            $date_creation,
            $description_reseau,
            $id_aep
        ));

        // Retourner l'ID du réseau inséré
        return Manager::getBdd()->lastInsertId();

    } catch (PDOException $e) {
        // Gestion des erreurs
        throw new Exception("Erreur lors de l'ajout du réseau : " . $e->getMessage());
    }
}

/**
 * @param $bd
 * @param $id_abone
 * @param $id_compteur
 * @return array
 * @throws Exception
 */
function addCompteurAboneGetID($bd, $id_abone, $id_compteur)
{
    try {
        // Préparation de la requête d'insertion
        $sql = "INSERT INTO compteur_abone (
            id_abone, 
            id_compteur
        ) VALUES (
            ?, ?
        )";

        // Exécution de la requête avec prepare_query
        Manager::prepare_query($sql, array(
            $id_abone,
            $id_compteur
        ));

        // Retourner l'ID de la relation insérée (bien que la table n'ait pas de champ ID auto-incrémenté, on retourne les IDs fournis)
        return array('id_abone' => $id_abone, 'id_compteur' => $id_compteur);

    } catch (PDOException $e) {
        // Gestion des erreurs
        throw new Exception("Erreur lors de l'ajout de la relation compteur-abonné : " . $e->getMessage());
    }
}

/**
 * @param $bd
 * @param $nom
 * @param $numero_telephone
 * @param $numero_compte_anticipation
 * @param $etat
 * @param $rang
 * @param $id_reseau
 * @return mixed
 * @throws Exception
 */
function addAboneGetID($bd, $nom, $numero_telephone, $numero_compte_anticipation, $etat, $rang, $id_reseau)
{
    try {
        // Préparation de la requête d'insertion
        $sql = "INSERT INTO abone (
            nom, 
            numero_telephone, 
            numero_compte_anticipation, 
            etat, 
            rang, 
            id_reseau
        ) VALUES (
            ?, ?, ?, ?, ?, ?
        )";

        // Exécution de la requête avec prepare_query
        Manager::prepare_query($sql, array(
            $nom,
            $numero_telephone,
            $numero_compte_anticipation,
            $etat,
            $rang,
            $id_reseau
        ));

        // Retourner l'ID de l'abonné inséré
        return Manager::getBdd()->lastInsertId();

    } catch (PDOException $e) {
        // Gestion des erreurs
        throw new Exception("Erreur lors de l'ajout de l'abonné : " . $e->getMessage());
    }
}

function deposer_compteur($id_abone)
{
    $requete = "update compteur set etat = 'noo actif' where id_abone=?";
    Manager::prepare_query(
            $requete, array($id_abone)
    );
}

/**
 * @param $db
 * @param $nom_abone
 * @param $numero_compteur
 * @param $id_reseau
 * @param $rang
 * @return array|null
 * @throws Exception
 */
function findAboneCompteurRelation($db, $nom_abone, $numero_compteur, $id_reseau, $rang)
{
    try {
        // Requête pour trouver l'abonné par son nom et id_reseau
        $sqlAbone = "SELECT id, id_compteur 
                    FROM abone inner join compteur_abone on id_abone=id 
                    WHERE nom = ? AND id_reseau = ? and rang = ?";

        $req = Manager::prepare_query($sqlAbone, array($nom_abone, $id_reseau, $rang));
        $resultAbone = $req->fetch(PDO::FETCH_ASSOC);
        var_dump($resultAbone);
        $id_abone = $resultAbone ? $resultAbone['id'] : null;
        $id_compteur = $resultAbone ? $resultAbone['id_compteur'] : null;

        if ($id_abone) {
            return array(
                'id_abone' => $id_abone,
                'id_compteur' => $id_compteur,
                'relation' => array(
                    'id_abone' => $id_abone,
                    'id_compteur' => $id_compteur
                )
            );
        }
        return null; // Abonné non trouvé


    } catch (PDOException $e) {
        echo $e->getMessage();
        throw new Exception("Erreur lors de la recherche de l'abonné et du compteur : " . $e->getMessage());
    }
}

/**
 * @param $bd
 * @param $aboneData
 * @param $compteurData
 * @param $id_reseau
 * @return array
 * @throws Exception
 */
function addAboneCompteurRelation($bd, $aboneData, $compteurData, $id_reseau)
{
    try {

        //verifie si l'abone n'est pas deja present, il faut juste mettre a jour le dernier index et retourner l'objet relation
        $relation = findAboneCompteurRelation($bd, $aboneData['nom'], $compteurData['numero_compteur'], $id_reseau, $aboneData['rang']);
        var_dump($relation);
        if ($relation) {
            Manager::prepare_query("update compteur set derniers_index=? where id=?", array($compteurData['derniers_index'], $relation['id_compteur']));
            return $relation;
        }


        // Ajout de l'abonné
        $idAbone = addAboneGetID(
            $bd,
            $aboneData['nom'],
            $aboneData['numero_telephone'],
            $aboneData['numero_compte_anticipation'],
            $aboneData['etat'],
            $aboneData['rang'],
            $id_reseau
        );

        // Ajout du compteur
        $idCompteur = addCompteurGetID(
            $bd,
            $compteurData['numero_compteur'],
            $compteurData['longitude'],
            $compteurData['latitude'],
            $compteurData['derniers_index'],
            $compteurData['description']
        );

        // Ajout de la relation compteur-abonné
        $relation = addCompteurAboneGetID($bd, $idAbone, $idCompteur);

        // Valider la transaction
//        $bd->commit();

        // Retourner les IDs générés
        return array(
            'id_abone' => $idAbone,
            'id_compteur' => $idCompteur,
            'relation' => $relation
        );

    } catch (Exception $e) {
        // Annuler la transaction en cas d'erreur
//        $bd->rollBack();
        throw new Exception("Erreur lors de l'ajout de l'abonné, du compteur et de leur relation : " . $e->getMessage());
    }
}


$moisSocle = isset($_POST['moisSocle']) ? $_POST['moisSocle'] : '';
$moisActuel = isset($_POST['moisActuel']) ? $_POST['moisActuel'] : '';
$prixM3Socle = isset($_POST['prixM3Socle']) ? floatval($_POST['prixM3Socle']) : 0;
$entretienSocle = isset($_POST['entretienSocle']) ? floatval($_POST['entretienSocle']) : 0;
$tvaSocle = isset($_POST['tvaSocle']) ? floatval($_POST['tvaSocle']) : 0;
$prixM3Actuel = isset($_POST['prixM3Actuel']) ? floatval($_POST['prixM3Actuel']) : 0;
$entretienActuel = isset($_POST['entretienActuel']) ? floatval($_POST['entretienActuel']) : 0;
$tvaActuel = isset($_POST['tvaActuel']) ? floatval($_POST['tvaActuel']) : 0;
$id_aep = $_SESSION['id_aep'];

var_dump($_POST);

if (!file_exists($uploadDir)) {
    mkdir($uploadDir, 0777, true);
}

$db = Connexion::connect();


/**
 * @param $db
 * @param $data
 * @param $prix_metre_cube_eau
 * @param $prix_entretient_compteur
 * @param $prix_tva
 * @param $date_creation_constante
 * @param $est_actif_constante
 * @param $description_constante
 * @param $id_aep
 * @param $mois
 * @param $date_facturation
 * @param $date_depot
 * @param $description_mois
 * @param $est_actif_mois
 * @param $date_releve
 * @param $libele_aep
 * @param $nom_colonne_ancien_index
 * @param $nom_colonne_nouvel_index
 * @param $nom_colonne_anticipation_account
 * @param $nom_colonne_penalite
 * @return array
 * @throws Exception
 */
function createAllForMonth2(
    $db,
    $data,
    $prix_metre_cube_eau,
    $prix_entretient_compteur,
    $prix_tva,
    $date_creation_constante,
    $est_actif_constante,
    $description_constante,
    $id_aep,
    $mois,
    $date_facturation,
    $date_depot,
    $description_mois,
    $est_actif_mois,
    $date_releve,
    $libele_aep,
    $nom_colonne_ancien_index,
    $nom_colonne_nouvel_index,
    $nom_colonne_anticipation_account,
    $nom_colonne_penalite,
    $deposer=false

)
{
    try {
        // Démarrer une transaction
        $db->beginTransaction();

        // Cache des réseaux existants
        $existing_networks = array();

        // Ajout de la constante réseau
        $id_constante_reseau = addConstanceGetID(
            $db,
            $prix_metre_cube_eau,
            $prix_entretient_compteur,
            $prix_tva,
            $date_creation_constante,
            $est_actif_constante,
            $description_constante,
            $id_aep
        );

        // Ajout du mois de facturation
        $id_mois_facturation = addMoisFacturationGetID(
            $db,
            $mois,
            $date_facturation,
            $date_depot,
            $id_constante_reseau,
            $description_mois,
            $est_actif_mois,
            $date_releve,
            $id_aep
        );

        // Tableau pour stocker les données traitées
        $processed_data = array();

        // Traitement de chaque ligne de données
        foreach ($data as $row) {
            // Extraction des données de la ligne
            $row_data = array(
                'network' => isset($row['network']) ? $row['network'] : '',
                'client_name' => isset($row['client_name']) ? $row['client_name'] : '',
                'N' => isset($row['N']) ? $row['N'] : null,
                $nom_colonne_ancien_index => isset($row[$nom_colonne_ancien_index]) ? $row[$nom_colonne_ancien_index] : 0,
                $nom_colonne_nouvel_index => isset($row[$nom_colonne_nouvel_index]) ? $row[$nom_colonne_nouvel_index] : 0,
                'anticipation_account' => isset($row[$nom_colonne_anticipation_account]) ? $row[$nom_colonne_anticipation_account] : 0,
                'penalty' => isset($row[$nom_colonne_penalite]) ? $row[$nom_colonne_penalite] : 0,
                'meter_number' => isset($row['meter_number']) ? $row['meter_number'] : ''
            );

            // Vérification du réseau
            $id_reseau = array_search($row_data['network'], $existing_networks);
            if ($id_reseau === false) {
                $id_reseau = addReseauGetID(
                    $db,
                    $row_data['network'],
                    strtoupper(substr($row_data['network'], 0, 3)),
                    $date_creation_constante,
                    '',
                    $id_aep
                );
                $existing_networks[$id_reseau] = $row_data['network'];
            }

            // Données de l'abonné
            $abone_data = array(
                'nom' => $row_data['client_name'],
                'numero_telephone' => '',
                'numero_compte_anticipation' => '',
                'etat' => 'actif',
                'rang' => $row_data['N']
            );

            // Données du compteur
            $compteur_data = array(
                'numero_compteur' => $row_data['meter_number'],
                'longitude' => 0,
                'latitude' => 0,
                'derniers_index' => $row_data[$nom_colonne_nouvel_index],
                'description' => ''
            );

            // Ajout de l'abonné, du compteur et de leur relation
            $relation = addAboneCompteurRelation($db, $abone_data, $compteur_data, $id_reseau);

            // Ajout de l'index
            $id_index = addIndexGetID(
                $db,
                $relation['id_compteur'],
                $id_mois_facturation,
                $row_data[$nom_colonne_ancien_index],
                $row_data[$nom_colonne_nouvel_index],
                ''
            );

            // Ajout de la facture
            $id_facture = addFactureGetID(
                $db,
                $id_index,
                $row_data['anticipation_account'],
                $date_creation_constante,
                $row_data['penalty'],
                $relation['id_abone'],
                ''
            );
            var_dump($row_data);
            if($row_data['est_depose'] == 1 and $deposer){
                deposer_compteur($relation['id_abone']);
            }
            // Ajout des IDs générés aux données de la ligne
            $row_data['id_reseau'] = $id_reseau;
            $row_data['id_constante_reseau'] = $id_constante_reseau;
            $row_data['id_mois'] = $id_mois_facturation;
            $row_data['id_aep'] = $id_aep;
            $row_data['libele_aep'] = $libele_aep;
            $row_data['id_compteur'] = $relation['id_compteur'];
            $row_data['id_abone'] = $relation['id_abone'];
            $row_data['id_facture'] = $id_facture;
            $row_data['id_indexes'] = $id_index;


            $processed_data[] = $row_data;
        }

        // Valider la transaction
        $db->commit();

        // Retourner les données traitées
        return $processed_data;

    } catch (Exception $e) {
        // Annuler la transaction en cas d'erreur
        $db->rollBack();
        throw new Exception("Erreur lors de la création des données pour le mois : " . $e->getMessage());
    }
}


if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['file'])) {
    $file = $_FILES['file'];
    $fileName = basename($file['name']);
    $filePath = $uploadDir . $fileName;
    $fileExt = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

    // Vérifier si le fichier est un CSV
    if ($fileExt !== 'csv') {
        $message = '<div class="alert alert-danger">Seuls les fichiers CSV sont autorisés.</div>';
    } elseif ($file['error'] === UPLOAD_ERR_OK) {
        if (move_uploaded_file($file['tmp_name'], $filePath)) {
            // Lire le fichier CSV
            if (($handle = fopen($filePath, 'r')) !== false) {
                var_dump($db->beginTransaction());
                try {


                    $existing_networks = array();
                    $id_constnte = addConstanceGetID(null, $prixM3Socle, $entretienSocle, $tvaSocle, '2020-01-01', true, '', $id_aep);
                    $id_mois_facturation = addMoisFacturationGetID($db, $moisSocle, '0000-00-00', '0000-00-00', $id_constnte, '', true, '0000-00-00', '$id_aep');

                    var_dump($id_constnte);
                    var_dump($id_mois_facturation);
//                    $db->rollBack();
                    $headers = fgetcsv($handle, 1000, ';');
                    while (($row = fgetcsv($handle, 1000, ';')) !== false) {
                        $rowData = array();
                        foreach ($headers as $index => $header) {
                            $rowData[$header] = isset($row[$index]) ? $row[$index] : '';
                        }

//                        var_dump($rowData);
                        $network = $rowData['network'];
                        $nom_abone = $rowData['client_name'];
                        $rang = $rowData['N'];
                        $ancien_index_socle = $rowData['old_old_index'];
                        $ancien_index = $rowData['old_index'];
                        $montant_verse = $rowData['anticipation_account'];
                        $penalite = $rowData['penalty'];
                        $est_depose = $rowData['est_depose'];

                        $numero_compteur = $rowData['meter_number'];
                        $id_network = array_search($network, $existing_networks);
                        if (!$id_network) {
                            $id_network = addReseauGetID($db, $network, strtoupper(substr($network, 0, 3)), '2020-01-01', '', $id_aep);
                            $existing_networks[$id_network] = $network;
                        }
                        // Données de l'abonné
                        $aboneData = array(
                            'nom' => $nom_abone,
                            'numero_telephone' => '',
                            'numero_compte_anticipation' => '',
                            'etat' => 'actif',
                            'rang' => $rang
                        );

                        // Données du compteur
                        $compteurData = array(
                            'numero_compteur' => $numero_compteur,
                            'longitude' => 0,
                            'latitude' => 0,
                            'derniers_index' => $ancien_index_socle,
                            'description' => ''
                        );
                        $relation_c_a = addAboneCompteurRelation($db, $aboneData, $compteurData, $id_network);

                        $id_compteur = $relation_c_a['id_compteur'];
                        $id_abone = $relation_c_a['id_abone'];
                        $id_index = addIndexGetID($db, $id_compteur, $id_mois_facturation, $ancien_index_socle, $ancien_index, '');
                        $id_facture = addFactureGetID($db, $id_index, $montant_verse, '2020-01-01', $penalite, $id_abone, '');

                        $rowData['id_reseau'] = $id_network;
                        $rowData['id_constante_resau'] = $id_constnte;
                        $rowData['id_mois'] = $id_mois_facturation;
                        $rowData['id_aep'] = $id_aep;
                        $rowData['libele_aep'] = $_SESSION['libele_aep'];
                        $rowData['id_compteur'] = $id_compteur;
                        $rowData['id_abone'] = $id_abone;
                        $rowData['id_facture'] = $id_facture;
                        $rowData['id_indexes'] = $id_index;
//                        $rowData['est_depose'] = $id_index;
                        $data[] = $rowData;
                    }
                    fclose($handle);
                    var_dump($existing_networks);
                    $db->rollBack();
                    var_dump($data);
                    createAllForMonth2($db, $data, $prixM3Socle, $entretienSocle, $tvaSocle, '2020-01-01',
                        true, '', $id_aep, $moisSocle, '2020-01-29', '2020-01-30',
                        'ce mois ci contien des donnees bizarres', true, '2020-01-28', $_SESSION['libele_aep'],
                        'old_old_index', 'old_index', 'anticipation_account', 'penalty');
                    createAllForMonth2($db, $data, $prixM3Actuel, $entretienActuel, $tvaActuel, '2025-01-01',
                        true, '', $id_aep, $moisActuel, '2025-08-29', '2025-08-30',
                        '', true, '2025-08-28', $_SESSION['libele_aep'],
                        'old_index', 'c_new_index', 'vide', 'vide', true);

                } catch (Exception $e) {
                    $db->rollBack();
                    throw $e;

                }
            }
            $message = '<div class="alert alert-success">Fichier CSV envoyé avec succès : ' . htmlspecialchars($fileName) . '</div>';
        } else {
            $message = '<div class="alert alert-danger">Erreur lors de l\'enregistrement du fichier.</div>';
        }
    } else {
        $message = '<div class="alert alert-danger">Erreur lors de l\'upload : ' . $file['error'] . '</div>';
    }
} else {
    $message = '<div class="alert alert-danger">Aucun fichier envoyé.</div>';
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Résultat de l'upload</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<div class="container mt-5">
    <h1 class="text-center mb-4">Résultat de l'upload</h1>
    <div class="card p-4 shadow-sm">
        <?php echo $message; ?>
        <?php if (!empty($data)): ?>
            <table class="table table-striped table-bordered">
                <thead>
                <tr>
                    <?php foreach ($data[0] as $header => $value): ?>
                        <th><?php echo htmlspecialchars($header, ENT_QUOTES, 'UTF-8'); ?></th>
                    <?php endforeach; ?>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($data as $row): ?>
                    <tr>
                        <?php foreach ($row as $value): ?>
                            <td><?php echo htmlspecialchars($value); ?></td>
                        <?php endforeach; ?>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p>Aucune donnée à afficher.</p>
        <?php endif; ?>
        <a href="index.html" class="btn btn-secondary mt-3">Retour</a>
    </div>
</div>
</body>
</html>