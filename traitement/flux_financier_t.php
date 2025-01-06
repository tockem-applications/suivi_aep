<?php

@include_once("../donnees/flux_financier.php");
@include_once("donnees/flux_financier.php");


function create_delate_flux_financierModal($flux){
    return make_Modal('Supresssion ' . htmlspecialchars($flux['libele']), '
                                <p class="">voulez vous vraiment suprimer cette ' . htmlspecialchars($flux['type']) . ' ?</p>
                                <table class="table table-striped table-hover table-bordered">
                                <tr><td>Montant</td><td>' . htmlspecialchars($flux['prix']) . ' FCFA</td></tr>
                                <tr><td>date</td><td>' . htmlspecialchars(strftime('%d %B %Y', strtotime($flux['date']))) . '</td></tr>
                                <tr><td colspan="2">' . ($flux['description']) . '</td></tr>
                                </table>
                            ', -1, 'delete_flux' . $flux['id'],
        '<a class="btn btn-danger" href="traitement/flux_financier_t.php?delete=true&id_flux='.$flux['id'].'">Suprimer</a>',
        'success'
    );
}
class Flux_financier_t
{
    public static function recupererFluxFinancierDepuisFormulaire()
    {
        // Vérification de la méthode de requête
        if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_GET['ajout'])) {
            // Assainir et valider les données
//            $id = filter_var($_POST['id'], FILTER_SANITIZE_NUMBER_INT);
            $date = filter_var($_POST['date'], FILTER_SANITIZE_STRING);
            $mois = filter_var($_POST['mois'], FILTER_SANITIZE_STRING);
            $libele = filter_var($_POST['libele'], FILTER_SANITIZE_STRING);
            $prix = filter_var($_POST['prix'], FILTER_SANITIZE_NUMBER_INT);
            $type = filter_var($_POST['type'], FILTER_SANITIZE_STRING);
            $description = filter_var($_POST['description'], FILTER_SANITIZE_STRING);

            // Validation des données (ajoutez des règles selon vos besoins)
            if (!empty($date) && !empty($mois) && !empty($libele) && !empty($prix) && ($type === 'sortie' || $type === 'entree')) {
                // Créer l'objet FluxFinancier
                $fluxFinancier = new FluxFinancier('', $date, $libele, $prix, $type, $description, $mois, $_SESSION['id_aep']);
                $res = $fluxFinancier->ajouter();
//                exit();
                if ($res)
                    header("location: ../index.php?form=finance&operation=success");
                else
                    header("location: ../index.php?form=finance&operation=error&message=Echec de l'enregistrement");
//                return $fluxFinancier;
            } else {
                // Gérer les erreurs de validation
//                throw new Exception("Données du formulaire invalides.");
                header("location: ../index.php?form=finance&operation=error&message=Veuiller renseigner tout les champs obligatoires");
            }
        }
//        throw new Exception("Méthode non autorisée.");
    }
    public static function updateFluxFinancierDepuisFormulaire()
    {
        // Vérification de la méthode de requête
//        var_dump($_POST, $_GET);
//        exit();
        if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_GET['update'], $_GET['id_updates_flux'])) {
            // Assainir et valider les données
            $id = filter_var($_GET['id_updates_flux'], FILTER_SANITIZE_NUMBER_INT);
            $date = filter_var($_POST['date'], FILTER_SANITIZE_STRING);
            $mois = filter_var($_POST['mois'], FILTER_SANITIZE_STRING);
            $libele = filter_var($_POST['libele'], FILTER_SANITIZE_STRING);
            $prix = filter_var($_POST['prix'], FILTER_SANITIZE_NUMBER_INT);
            $type = filter_var($_POST['type'], FILTER_SANITIZE_STRING);
            $description = filter_var($_POST['description'], FILTER_SANITIZE_STRING);

            // Validation des données (ajoutez des règles selon vos besoins)
            if (!empty($id) && !empty($date) && !empty($mois) && !empty($libele) && !empty($prix) && ($type === 'sortie' || $type === 'entree')) {
                // Créer l'objet FluxFinancier
                $fluxFinancier = new FluxFinancier($id , $date, $libele, $prix, $type, $description, $mois, $_SESSION['id_aep']);
                $res = $fluxFinancier->update();
                var_dump($res);
//                exit();
                if ($res)
                    header("location: ../index.php?list=transaction&operation=succes");
                else
                    header("location: ../index.php?form=finance&id=$id&operation=error&message=Echec de modification");
//                return $fluxFinancier;
            } else {
                // Gérer les erreurs de validation
//                throw new Exception("Données du formulaire invalides.");
                header("location: ../index.php?form=finance&operation=error&message=Veuiller renseigner tout les champs obligatoires");
            }
        }
//        throw new Exception("Méthode non autorisée.");
    }

    public static function afficheFluxFinancier()
    {
        $montant_min = 0;
        $somme = 0;
        $mois = '';
        $type = '';
        $sortie_selected = '';
        $entree_selected = '';
        if (isset($_POST['montant_min']))
            $montant_min = (int)$_POST['montant_min'];
        if (isset($_POST['mois']))
            $mois = $_POST['mois'];
        if (isset($_POST['type'])) {
            $type = $_POST['type'];
            if ($type === 'sortie') {
                $sortie_selected = 'selected';
            } else
                $entree_selected = 'selected';
        }
        $res = FluxFinancier::getFinanceData($mois, $type, $montant_min, $_SESSION['id_aep']);
        $fluxFinanciers = $res->fetchAll();
        ?>
        <table class="table table-bordered">
            <div class="d-flex justify-content-center py-4"><h2>Liste des Flux Financiers</h2>
                <hr>
            </div>
            <thead class="thead-light">

            <form action="?list=transaction" method="post">
                <div class="input-group pb-3">
                    <label class="input-group-text w-25">Trier</label>
                    <input class="form-control" type="month" value="<?php echo $mois ?>" name="mois">
                    <select class="form-select" name="type">
                        <option value="">Selectionnez un type</option>
                        <option value="sortie" <?php echo $sortie_selected ?>>Sortie</option>
                        <option value="entree">Entrée <?php echo $entree_selected ?></option>
                    </select>
                    <input type="number" class="form-control" name="montant_min"
                           value="<?php echo $montant_min == 0 ? '' : $montant_min ?>" placeholder="montant min">
                    <button type="submit" class="btn btn-primary input-group-text">Rechercher</button>
                    <a href="?list=transaction" type="reset" class="btn btn-warning input-group-text">Vider</a>
                    <!--                    <input class="form-control">-->
                </div>
            </form>
            <a href="?form=finance">Nouvelle transaction</a>
            <tr>
                <th>ID</th>
                <th>Date</th>
                <th>Mois</th>
                <th>Libellé</th>
                <th>Prix</th>
                <th>Type</th>
<!--                <th>Description</th>-->
                <th>Action</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($fluxFinanciers as $flux) : ?>
                <tr class="<?php echo ($flux['type'] === 'sortie') ? 'table-danger' : 'table-success'; ?>">
                    <td><?php echo htmlspecialchars($flux['id']); ?></td>
                    <td><?php echo htmlspecialchars($flux['date']); ?></td>
                    <td><?php echo htmlspecialchars($flux['mois']); ?></td>
                    <td data-bs-toggle="tooltip" data-bs-placement="top" data-bs-title="<?php echo $flux['description']; ?>"><?php echo htmlspecialchars($flux['libele']); ?></td>
                    <td><?php echo htmlspecialchars($flux['prix']); $somme += ($flux['type'] == 'sortie'?-1:1)*(int)$flux['prix']?> FCFA</td>
                    <td><?php echo htmlspecialchars($flux['type']); ?></td>
<!--                    <td>--><?php //echo $flux['description']; ?><!--</td>-->
                    <td>
                        <div class="btn-group">
                            <?php echo  create_delate_flux_financierModal($flux)?>
                            <button class="btn btn-danger" data-bs-target="#delete_flux<?php echo $flux['id']; ?>"
                                    data-bs-toggle="modal">Suprimer
                            </button>
                            <a href="?form=finance&id=<?php echo $flux['id']; ?>" class="btn btn-success">modifier</a>

                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            <tr class="bg-white fs-5 "><td colspan="3" class="px-5">Total</td><td class="text-end px-5" colspan="5"><?php echo $somme;?> FCFA</td></tr>
            </tbody>
        </table>
        <?php

    }

    public static function delete()
    {
        if (!isset($_GET['delete']))
            return;
        if (isset($_GET['id_flux'])) {
            $id = $_GET['id_flux'];
            $res = FluxFinancier::delete_flux($id);
            if (!$res)
                header("location: ../index.php?list=transaction&operation=error");
            else
                header("location: ../index.php?list=transaction&operation=succes");
        }
    }

}

Flux_financier_t::recupererFluxFinancierDepuisFormulaire();
Flux_financier_t::updateFluxFinancierDepuisFormulaire();
Flux_financier_t::delete();
