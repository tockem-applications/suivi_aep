<?php
session_start();

@include_once("../donnees/manager.php");
@include_once("donnees/manager.php");

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['user_id'])) {
    header('Location: ../?page=login');
    exit;
}

// Vérifier si un AEP est sélectionné
if (!isset($_SESSION['id_aep'])) {
    header('Location: ../?page=abonne&error=no_aep');
    exit;
}

$aepId = (int) $_SESSION['id_aep'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = isset($_POST['action']) ? $_POST['action'] : '';

    switch ($action) {
        case 'add_abonne':
            ajouterAbonne();
            break;
        case 'delete_abonne':
            supprimerAbonne();
            break;
        default:
            header('Location: ../?page=abonne&error=invalid_request');
            exit;
    }
} else {
    header('Location: ../?page=abonne&error=invalid_request');
    exit;
}

function ajouterAbonne()
{
    global $aepId;

    try {
        // Validation des données
        $nom = trim(isset($_POST['nom']) ? $_POST['nom'] : '');
        $numeroTelephone = trim(isset($_POST['numero_telephone']) ? $_POST['numero_telephone'] : '');
        $etat = isset($_POST['etat']) ? $_POST['etat'] : '';
        $rang = isset($_POST['rang']) ? (int) $_POST['rang'] : null;
        $idReseau = (int) (isset($_POST['id_reseau']) ? $_POST['id_reseau'] : 0);

        // Validation
        if (empty($nom)) {
            throw new Exception('Le nom est requis');
        }
        if (strlen($nom) > 128) {
            throw new Exception('Le nom est trop long (max 128 caractères)');
        }

        if (empty($numeroTelephone)) {
            throw new Exception('Le numéro de téléphone est requis');
        }
        if (strlen($numeroTelephone) > 16) {
            throw new Exception('Le numéro de téléphone est trop long (max 16 caractères)');
        }

        if (!in_array($etat, array('actif', 'inactif', 'suspendu'))) {
            throw new Exception('État invalide');
        }

        if ($idReseau <= 0) {
            throw new Exception('Réseau invalide');
        }

        // Vérifier que le réseau appartient à l'AEP
        $reseau = Manager::prepare_query(
            'SELECT * FROM reseau WHERE id = ? AND id_aep = ?',
            array($idReseau, $aepId)
        )->fetch();

        if (!$reseau) {
            throw new Exception('Réseau introuvable ou non autorisé');
        }

        // Vérifier que le numéro de téléphone n'existe pas déjà
        $telephoneExistant = Manager::prepare_query(
            'SELECT * FROM abone WHERE numero_telephone = ?',
            array($numeroTelephone)
        )->fetch();

        if ($telephoneExistant) {
            throw new Exception('Un abonné avec ce numéro de téléphone existe déjà');
        }

        // Insérer le nouvel abonné
        $resultat = Manager::prepare_query(
            'INSERT INTO abone (nom, numero_telephone, etat, rang, id_reseau) 
             VALUES (?, ?, ?, ?, ?)',
            array($nom, $numeroTelephone, $etat, $rang, $idReseau)
        );

        if ($resultat) {
            header('Location: ../?page=abonne&success=abonne_added');
        } else {
            throw new Exception('Impossible d\'ajouter l\'abonné');
        }

    } catch (Exception $e) {
        header('Location: ../?page=abonne&error=add_failed&message=' . urlencode($e->getMessage()));
    }
    exit;
}

function supprimerAbonne()
{
    global $aepId;

    try {
        $abonneId = (int) (isset($_POST['abonne_id']) ? $_POST['abonne_id'] : 0);

        if ($abonneId <= 0) {
            throw new Exception('ID d\'abonné invalide');
        }

        // Vérifier que l'abonné appartient bien à l'AEP
        $abonne = Manager::prepare_query(
            'SELECT a.* FROM abone a 
             INNER JOIN reseau r ON a.id_reseau = r.id 
             WHERE a.id = ? AND r.id_aep = ?',
            array($abonneId, $aepId)
        )->fetch();

        if (!$abonne) {
            throw new Exception('Abonné introuvable ou non autorisé');
        }

        // Vérifier qu'il n'y a pas de factures liées
        $nbFactures = Manager::prepare_query(
            'SELECT COUNT(*) as nb FROM vue_abones_facturation WHERE id_abone = ?',
            array($abonneId)
        )->fetch()['nb'];

        if ($nbFactures > 0) {
            throw new Exception('Impossible de supprimer cet abonné : ' . $nbFactures . ' facture(s) y sont liées');
        }

        // Vérifier qu'il n'y a pas de compteurs liés
        $nbCompteurs = Manager::prepare_query(
            'SELECT COUNT(*) as nb FROM compteur_abone WHERE id_abone = ?',
            array($abonneId)
        )->fetch()['nb'];

        if ($nbCompteurs > 0) {
            throw new Exception('Impossible de supprimer cet abonné : ' . $nbCompteurs . ' compteur(s) y sont liés');
        }

        // Supprimer l'abonné
        $resultat = Manager::prepare_query(
            'DELETE FROM abone WHERE id = ?',
            array($abonneId)
        );

        if ($resultat) {
            header('Location: ../?page=abonne&success=abonne_deleted');
        } else {
            throw new Exception('Impossible de supprimer l\'abonné');
        }

    } catch (Exception $e) {
        header('Location: ../?page=abonne&error=delete_failed&message=' . urlencode($e->getMessage()));
    }
    exit;
}
?>