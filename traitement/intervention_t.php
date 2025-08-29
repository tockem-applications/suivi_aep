<?php

@include_once("../donnees/intervention.php");
@include_once("donnees/intervention.php");
@include_once("../donnees/manager.php");
@include_once("donnees/manager.php");

class Intervention_t
{
    public static function handlePost()
    {
        if (!isset($_POST['action']))
            return;
        $action = $_POST['action'];
        $aep_id = isset($_SESSION['id_aep']) ? (int) $_SESSION['id_aep'] : 0;
        if ($aep_id <= 0)
            return;

        $success = 'ok';
        if ($action == 'create' || $action == 'update') {
            $id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
            $interv = new Intervention(
                $id,
                $aep_id,
                trim($_POST['titre']),
                trim($_POST['type']),
                isset($_POST['description']) ? trim($_POST['description']) : '',
                isset($_POST['localisation']) ? trim($_POST['localisation']) : '',
                isset($_POST['date_debut_prevue']) ? $_POST['date_debut_prevue'] : null,
                isset($_POST['date_fin_prevue']) ? $_POST['date_fin_prevue'] : null,
                null,
                null,
                isset($_POST['statut']) ? $_POST['statut'] : 'planifiee',
                isset($_POST['cout_estime']) ? (float) $_POST['cout_estime'] : 0,
                0,
                isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null
            );
            try {
                if ($action == 'create') {
                    $interv->ajouter();
                    $id = $interv->id;
                    $success = 'created';
                } else {
                    $interv->update();
                    $success = 'updated';
                }
            } catch (Exception $e) {
                $success = $action == 'create' ? 'create_failed' : 'update_failed';
            }

            // Affectations RH
            if (isset($_POST['rh_ids']) && is_array($_POST['rh_ids'])) {
                Manager::prepare_query('DELETE FROM intervention_rh WHERE intervention_id = ?', array($id));
                foreach ($_POST['rh_ids'] as $rh_id) {
                    $rh_id = (int) $rh_id;
                    $heures_prevues = isset($_POST['heures_prevues'][$rh_id]) ? (float) $_POST['heures_prevues'][$rh_id] : 0;
                    $cout_prevu = isset($_POST['cout_horaire'][$rh_id]) ? (float) $_POST['cout_horaire'][$rh_id] * $heures_prevues : 0;
                    Manager::prepare_query('INSERT INTO intervention_rh (intervention_id, rh_id, heures_prevues, cout_prevu) VALUES (?, ?, ?, ?)', array($id, $rh_id, $heures_prevues, $cout_prevu));
                }
            }

            // Affectations RM
            if (isset($_POST['rm_ids']) && is_array($_POST['rm_ids'])) {
                Manager::prepare_query('DELETE FROM intervention_rm WHERE intervention_id = ?', array($id));
                foreach ($_POST['rm_ids'] as $rm_id) {
                    $rm_id = (int) $rm_id;
                    $qte_prev = isset($_POST['quantite_prevue'][$rm_id]) ? (float) $_POST['quantite_prevue'][$rm_id] : 0;
                    $cout_prevu = isset($_POST['cout_unitaire'][$rm_id]) ? (float) $_POST['cout_unitaire'][$rm_id] * $qte_prev : 0;
                    Manager::prepare_query('INSERT INTO intervention_rm (intervention_id, rm_id, quantite_prevue, cout_prevu) VALUES (?, ?, ?, ?)', array($id, $rm_id, $qte_prev, $cout_prevu));
                }
            }
        } elseif ($action == 'status') {
            $id = (int) $_POST['id'];
            $statut = $_POST['statut'];
            try {
                Manager::prepare_query('UPDATE interventions SET statut = ?, date_debut_reelle = IF(?="en_cours", NOW(), date_debut_reelle), date_fin_reelle = IF(?="terminee" OR ?="annulee", NOW(), date_fin_reelle) WHERE id = ?', array($statut, $statut, $statut, $statut, $id));
                $success = 'status_updated';
            } catch (Exception $e) {
                $success = 'status_failed';
            }
        } elseif ($action == 'delete') {
            $id = (int) $_POST['id'];
            try {
                Manager::delete_by_id('interventions', $id);
                $success = 'deleted';
            } catch (Exception $e) {
                $success = 'delete_failed';
            }
        }
        header('Location: ..?page=interventions&success=interv_' . $success);
        exit();
    }

    public static function getAll()
    {
        $aep_id = isset($_SESSION['id_aep']) ? (int) $_SESSION['id_aep'] : 0;
        if ($aep_id <= 0)
            return array();
        $req = Intervention::getAllByAep($aep_id);
        return $req ? $req->fetchAll() : array();
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Intervention_t::handlePost();
}


