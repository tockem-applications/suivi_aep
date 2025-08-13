<?php
@include_once '../donnees/aepModel.php';
@include_once 'donnees/aepModel.php';

//header('Content-Type: application/json');

class AepController {
    private $model;

    public function __construct() {
        $this->model = new AepModel();
    }

    public function getAepDashboard($aepId) {
        if (!$aepId || !is_numeric($aepId)) {
            // http_response_code() n'est pas disponible en PHP 5.3.6 (introduit en PHP 5.4)
            // Utilisation de header() pour définir le code de réponse
            header('HTTP/1.1 400 Bad Request');
            echo json_encode(array('error' => 'ID d\'AEP invalide'));
            return;
        }

        $data = array(
            'id' => $aepId,
            'libele' => '',
            'description' => '',
            'date' => '',
            'numero_compte' => '',
            'nom_banque' => '',
            'reseaux' => array(),
            'abones_count' => 0,
            'facture_total' => 0,
            'impaye_total' => 0,
            'flux_financiers' => array('entrees' => 0, 'sorties' => 0),
            'redevances' => array(),
            'index_history' => array(),
            'recent_factures' => array(),
            'impayes' => array()
        );

        // Récupérer les informations de l'AEP
        $aepInfo = $this->model->getAepInfo($aepId);
        if (!$aepInfo) {
            header('HTTP/1.1 404 Not Found');
            echo json_encode(array('error' => 'AEP non trouvé'));
            return;
        }
        $data['libele'] = $aepInfo['libele'];
        $data['description'] = $aepInfo['description'];
        $data['date'] = $aepInfo['date'];
        $data['numero_compte'] = $aepInfo['numero_compte'];
        $data['nom_banque'] = $aepInfo['nom_banque'];

        // Récupérer les autres données
        $data['reseaux'] = $this->model->getReseaux($aepId);
        $data['abones_count'] = $this->model->getAbonesCount($aepId);
        $data['facture_total'] = $this->model->getFactureTotal($aepId);
        $data['impaye_total'] = $this->model->getImpayeTotal($aepId);
        $data['flux_financiers'] = $this->model->getFluxFinanciers($aepId);
        $data['redevances'] = $this->model->getRedevances($aepId);
        $data['index_history'] = $this->model->getIndexHistory($aepId);

        // Récupérer les factures (filtrées si un mois est spécifié)
        $mois = isset($_GET['mois']) ? $_GET['mois'] : null;
        $data['recent_factures'] = $this->model->getRecentFactures($aepId, $mois);
        $data['impayes'] = $this->model->getImpayes($aepId);

        return $data;
    }
}
//$data = null;
//// Gestion de la requête
//$controller = new AepController();
//if (isset($_GET['aep_id'])) {
//    $data = $controller->getAepDashboard($_GET['aep_id']);
//} else {
//    header('HTTP/1.1 400 Bad Request');
//    echo json_encode(array('error' => 'Aucun ID d\'AEP fourni'));
//}
?>