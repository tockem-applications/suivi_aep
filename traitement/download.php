
<?php

@include_once("../donnees/Abones.php");
@include_once("donnees/Abones.php");

if(!isset($_GET['id_mois']))
    exit();
$id_mois = $_GET['id_mois'];


$req = Abones::getJsonDataFromIdMois($id_mois);
//var_dump($id_mois);
//var_dump($req);
//exit();
//var_dump($req);
$date_export = new DateTime();
$data =json_encode($req);
//exit();
//var_dump($_SESSION);
$all = array("releve"=> array("nom_feuille"=>"nom_aep", "data"=> $req), 
    "info_reseau"=> array(
        "nom_reseau"=> $_SESSION['libele_aep'],
        "agent_export"=> "Non Disponible",
        "date_export"=> $date_export->format('d/m/Y:H/i/s'))
    );

//$all = var_dump($all);
$data =json_encode($all, 128);
//echo $data;
//echo $date_export->format('d/m/Y:H/i/s');
//$boo = json_decode($data, true);
//var_dump($boo);
$fileName = '../donnees/exports/export_index_nom_AEP_'.$date_export->format('d-m-Y_H-i-s').'.json';
Abones::writeToFile($fileName, $data);
Abones::telecharger($fileName);
unlink($fileName);
exit;