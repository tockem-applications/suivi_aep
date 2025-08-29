<?php
//session_start();
@include_once("../donnees/flux_financier.php");
@include_once("donnees/flux_financier.php");

class Flux_financier_t {
    public static function recupererFluxFinancierDepuisFormulaire() {
        if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_GET['ajout'])) {
            $date = filter_var($_POST['date'], FILTER_SANITIZE_STRING);
            $mois = filter_var($_POST['mois'], FILTER_SANITIZE_STRING);
            $libele = filter_var($_POST['libele'], FILTER_SANITIZE_STRING);
            $prix = filter_var($_POST['prix'], FILTER_SANITIZE_NUMBER_INT);
            $type = filter_var($_POST['type'], FILTER_SANITIZE_STRING);
            $description = filter_var($_POST['description'], FILTER_SANITIZE_STRING);

            if (!empty($date) && !empty($mois) && !empty($libele) && !empty($prix) && ($type === 'sortie' || $type === 'entree')) {
                $fluxFinancier = new FluxFinancier('', $date, $libele, $prix, $type, $description, $mois, $_SESSION['id_aep']);
                $res = $fluxFinancier->ajouter();
                if ($res) {
                    header("Location: ../index.php?list=transaction&operation=success");
                } else {
                    header("Location: ../index.php?list=transaction&operation=error&message=Echec de l'enregistrement");
                }
            } else {
                header("Location: ../index.php?form=finance&operation=error&message=Veuillez renseigner tous les champs obligatoires");
            }
        }
    }

    public static function updateFluxFinancierDepuisFormulaire() {
        if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_GET['update'], $_GET['id_updates_flux'])) {
            $id = filter_var($_GET['id_updates_flux'], FILTER_SANITIZE_NUMBER_INT);
            $date = filter_var($_POST['date'], FILTER_SANITIZE_STRING);
            $mois = filter_var($_POST['mois'], FILTER_SANITIZE_STRING);
            $libele = filter_var($_POST['libele'], FILTER_SANITIZE_STRING);
            $prix = filter_var($_POST['prix'], FILTER_SANITIZE_NUMBER_INT);
            $type = filter_var($_POST['type'], FILTER_SANITIZE_STRING);
            $description = filter_var($_POST['description'], FILTER_SANITIZE_STRING);

            if (!empty($id) && !empty($date) && !empty($mois) && !empty($libele) && !empty($prix) && ($type === 'sortie' || $type === 'entree')) {
                $fluxFinancier = new FluxFinancier($id, $date, $libele, $prix, $type, $description, $mois, $_SESSION['id_aep']);
                $res = $fluxFinancier->update();
                if ($res) {
                    header("Location: ../index.php?list=transaction&operation=succes");
                } else {
                    header("Location: ../index.php?form=finance&id=$id&operation=error&message=Echec de modification");
                }
            } else {
                header("Location: ../index.php?form=finance&operation=error&message=Veuillez renseigner tous les champs obligatoires");
            }
        }
    }

    public static function delete() {
        if (isset($_GET['delete']) && isset($_GET['id_flux'])) {
            $id = $_GET['id_flux'];
            $res = FluxFinancier::delete_flux($id);
            if ($res) {
                header("Location: ../index.php?list=transaction&operation=succes");
            } else {
                header("Location: ../index.php?list=transaction&operation=error");
            }
        }
    }
}

Flux_financier_t::recupererFluxFinancierDepuisFormulaire();
Flux_financier_t::updateFluxFinancierDepuisFormulaire();
Flux_financier_t::delete();
?>