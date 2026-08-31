<?php
require_once __DIR__ . '/_guard.php';
traitement_guard();
@include_once("../donnees/reseau.php");
@include_once("donnees/reseau.php");
@include_once("../traitement/compteur_t.php");
@include_once("traitement/compteur_t.php");


class Reseau_t
{
    private static function ensureHierarchyColumn()
    {
        try {
            $exists = Manager::prepare_query(
                "SELECT COUNT(*) AS c FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'reseau' AND column_name = 'id_reseau_parent'",
                array()
            )->fetch();

            if (!$exists || (int) $exists['c'] === 0) {
                Manager::prepare_query("ALTER TABLE `reseau` ADD COLUMN `id_reseau_parent` int(5) unsigned NULL DEFAULT NULL AFTER `id_aep`", array());
                Manager::prepare_query("CREATE INDEX `idx_reseau_parent` ON `reseau` (`id_reseau_parent`)", array());
            }
        } catch (Exception $e) {
            // Le fallback est de continuer sans planter la page.
        }
    }

    private static function normalizeParentIdFromPost()
    {
        if (!isset($_POST['id_reseau_parent']) || $_POST['id_reseau_parent'] === '') {
            return null;
        }
        $parentId = (int) $_POST['id_reseau_parent'];
        return $parentId > 0 ? $parentId : null;
    }

    private static function parentExistsInAep($parentId, $aepId)
    {
        if ($parentId === null || $aepId <= 0) {
            return true;
        }
        $row = Manager::prepare_query(
            "SELECT id FROM reseau WHERE id = ? AND id_aep = ?",
            array($parentId, $aepId)
        )->fetch();
        return (bool) $row;
    }

    private static function isDescendant($nodeId, $potentialAncestorId)
    {
        if ($nodeId <= 0 || $potentialAncestorId <= 0) {
            return false;
        }

        $visited = array();
        $current = $nodeId;
        while ($current > 0) {
            if (isset($visited[$current])) {
                return true;
            }
            $visited[$current] = true;
            $row = Manager::prepare_query(
                "SELECT id_reseau_parent FROM reseau WHERE id = ?",
                array($current)
            )->fetch();
            if (!$row || empty($row['id_reseau_parent'])) {
                return false;
            }
            $parent = (int) $row['id_reseau_parent'];
            if ($parent === $potentialAncestorId) {
                return true;
            }
            $current = $parent;
        }
        return false;
    }

    public static function ajout()
    {
        // Nouveau flux via POST action
        if (isset($_POST['action']) && $_POST['action'] === 'add_reseau') {
            try {
                if (!isset($_SESSION['id_aep'])) {
                    header('Location: ../?page=reseaux&error=no_aep');
                    exit;
                }
                self::ensureHierarchyColumn();
                $nom = isset($_POST['nom']) ? trim($_POST['nom']) : '';
                $abreviation = isset($_POST['abreviation']) ? trim($_POST['abreviation']) : '';
                $date_creation = isset($_POST['date_creation']) && $_POST['date_creation'] !== '' ? $_POST['date_creation'] : date('Y-m-d');
                $description_reseau = isset($_POST['description_reseau']) ? trim($_POST['description_reseau']) : '';
                $id_reseau_parent = self::normalizeParentIdFromPost();

                if ($nom === '') {
                    header('Location: ../?page=reseaux&error=invalid&message=' . urlencode('Nom requis'));
                    exit;
                }
                if (!self::parentExistsInAep($id_reseau_parent, (int) $_SESSION['id_aep'])) {
                    header('Location: ../?page=reseaux&error=invalid&message=' . urlencode('Le réseau parent sélectionné est invalide.'));
                    exit;
                }

                $res = Manager::prepare_query(
                    "INSERT INTO reseau (nom, abreviation, date_creation, description_reseau, id_aep, id_reseau_parent) VALUES (?, ?, ?, ?, ?, ?)",
                    array($nom, $abreviation, $date_creation, $description_reseau, (int) $_SESSION['id_aep'], $id_reseau_parent)
                );
                if (!$res) {
                    header('Location: ../?page=reseaux&error=add_failed');
                } else {
                    header('Location: ../?page=reseaux&success=reseau_added');
                }
                exit;
            } catch (Exception $e) {
                header('Location: ../?page=reseaux&error=exception&message=' . urlencode($e->getMessage()));
                exit;
            }
        }
        if (isset($_GET['ajout'])) {
            var_dump($_POST);
            if (isset($_POST['nom'], $_POST['abreviation'], $_POST['date_creation'], $_POST['description_reseau'])) {
                echo "ooooooooooo";
                $nom = htmlspecialchars($_POST['nom']);
                $abreviation = htmlspecialchars($_POST['abreviation']);
                $date_creation = $_POST['date_creation'] == '' ? date('d/m/Y') : htmlspecialchars($_POST['date_creation']);
                $description_reseau = htmlspecialchars($_POST['description_reseau']);

                $nouveau_reseau = new Reseau(0, $nom, $abreviation, $date_creation, $description_reseau, $_SESSION['id_aep']);
                var_dump($nouveau_reseau);
                //                $compteur = Compteur_t::createCompteurFromPost($_POST);
                $res = $nouveau_reseau->ajouter();
                if (!$res)
                    header("location: ../index.php?form=reseau&operation=error");
                else
                    header("location: ../index.php?page=reseau&operation=succes");

            }
        }
    }

    public static function update()
    {
        // Nouveau flux via POST action
        if (isset($_POST['action']) && $_POST['action'] === 'update_reseau') {
            try {
                self::ensureHierarchyColumn();
                $id = isset($_POST['reseau_id']) ? (int) $_POST['reseau_id'] : 0;
                $nom = isset($_POST['nom']) ? trim($_POST['nom']) : '';
                $abreviation = isset($_POST['abreviation']) ? trim($_POST['abreviation']) : '';
                $date_creation = isset($_POST['date_creation']) && $_POST['date_creation'] !== '' ? $_POST['date_creation'] : date('Y-m-d');
                $description_reseau = isset($_POST['description_reseau']) ? trim($_POST['description_reseau']) : '';
                $id_reseau_parent = self::normalizeParentIdFromPost();
                $aepId = isset($_SESSION['id_aep']) ? (int) $_SESSION['id_aep'] : 0;

                if ($id <= 0 || $nom === '') {
                    header('Location: ../?page=reseaux&error=invalid');
                    exit;
                }
                if ($id_reseau_parent !== null && $id_reseau_parent === $id) {
                    header('Location: ../?page=reseaux&error=invalid&message=' . urlencode('Un réseau ne peut pas être son propre parent.'));
                    exit;
                }
                if (!self::parentExistsInAep($id_reseau_parent, $aepId)) {
                    header('Location: ../?page=reseaux&error=invalid&message=' . urlencode('Le réseau parent sélectionné est invalide.'));
                    exit;
                }
                if ($id_reseau_parent !== null && self::isDescendant($id_reseau_parent, $id)) {
                    header('Location: ../?page=reseaux&error=invalid&message=' . urlencode('Hiérarchie invalide: boucle détectée.'));
                    exit;
                }

                $res = Manager::prepare_query(
                    "UPDATE reseau SET nom = ?, abreviation = ?, date_creation = ?, description_reseau = ?, id_reseau_parent = ? WHERE id = ? AND id_aep = ?",
                    array($nom, $abreviation, $date_creation, $description_reseau, $id_reseau_parent, $id, $aepId)
                );
                if (!$res) {
                    header('Location: ../?page=reseaux&error=update_failed');
                } else {
                    header('Location: ../?page=reseaux&success=reseau_updated');
                }
                exit;
            } catch (Exception $e) {
                header('Location: ../?page=reseaux&error=exception&message=' . urlencode($e->getMessage()));
                exit;
            }
        }
        if (isset($_GET['update'])) {
            if (isset($_POST['prixsemHS'], $_GET['id_updates'], $_POST['prixSemBS'])) {
                $prixsemHS = htmlspecialchars($_POST['prixsemHS']);
                $id = htmlspecialchars($_GET['id_updates']);
                $prixSemBS = htmlspecialchars($_POST['prixSemBS']);
                $updatedReseau = new Reseau($id, '', '', '', '');
                $res = $updatedReseau->update();
                if (!$res)
                    header("location: ../presentation/index.php?form=reseau&operation=error");
                else
                    header("location: ../presentation/index.php?form=abone&operation=succes");

                // TODO verification d'erreur sur $res

            }
        }
    }
    public static function delete()
    {
        // Nouveau flux via POST action
        if (isset($_POST['action']) && $_POST['action'] === 'delete_reseau') {
            $id = isset($_POST['reseau_id']) ? (int) $_POST['reseau_id'] : 0;
            if ($id <= 0) {
                header('Location: ../?page=reseaux&error=invalid');
                exit;
            }
            $nbAbonnesRow = Manager::prepare_query(
                "SELECT COUNT(*) AS c FROM abone WHERE id_reseau = ?",
                array($id)
            )->fetch();
            $nbAbonnes = $nbAbonnesRow ? (int) $nbAbonnesRow['c'] : 0;
            $nbChildrenRow = Manager::prepare_query(
                "SELECT COUNT(*) AS c FROM reseau WHERE id_reseau_parent = ?",
                array($id)
            )->fetch();
            $nbChildren = $nbChildrenRow ? (int) $nbChildrenRow['c'] : 0;
            if ($nbAbonnes > 0 || $nbChildren > 0) {
                header('Location: ../?page=reseaux&error=invalid&message=' . urlencode('Suppression impossible: ce réseau contient des abonnés ou des réseaux fils.'));
                exit;
            }
            $reseau = new Reseau($id, '', '', '', '');
            $res = $reseau->deleteReseau();
            if (!$res)
                header('Location: ../?page=reseaux&error=delete_failed');
            else
                header('Location: ../?page=reseaux&success=reseau_deleted');
            exit;
        }
        if (isset($_GET['id_delete'])) {
            $id = $_GET['id_delete'];
            $reseau = new Reseau($id, '', '', '', '');
            $res = $reseau->deleteReseau();
            if (!$res)
                header("location: ../index.php?page=reseau&id_reseau=$id&operation=error");
            else
                header("location: ../index.php?page=reseau&operation=succes");
        }
    }
    public static function findUpadate()
    {
        if (isset($_GET['id_update'])) {
            $id = $_GET['id_update'];
            header("location: ../presentation/index.php?form=tarif&id=$id");
        }
    }

    public static function getOne($id)
    {
        return Reseau::getOne('Reseau', $id);
    }

    public static function getAll($titre = "Liste", $id_name = 'Id', $debut = 0, $action = false)
    {

        $req = Reseau::getAll('Reseau');
        $req = $req->fetchAll();
        if (isset($req[0])) {
            ?>

            <!--            ceation de l'entete du tableau      -->
            <table class="table_searching table table-striped">
                <thead>
                    <h3 style="text-align: center; margin-top: 20px;">
                        <?= $titre ?>
                    </h3>
                </thead>
                <tr>
                    <?php
                    $i = 0;
                    foreach ($req[0] as $cle => $val) {
                        if ($i % 2 == 1) {
                            $i++;
                            continue;
                        }
                        $i++;
                        echo "<th>$cle</th>";
                        //<script>alert('$val')</script>
                    }
                    echo "</tr>";
                    foreach ($req as $donnees) {
                        $id = $donnees[$id_name];
                        echo "<tr onclick='affiche($id)'> <a href=?id='.$donnees[$id_name].'>";
                        $i = 0;
                        foreach ($donnees as $valeur) {
                            if ($i % 2 == 1) {
                                $i++;
                                continue;
                            }
                            $i++;
                            echo "<td>$valeur</td>";
                        }
                        if (!$action) {
                            ?>
                            <td><a href="../traitement/tarif_t.php?id_delete=<?= $id ?>">delete</a></td>
                            <td><a href="../traitement/tarif_t.php?id_update=<?= $id ?>">update</a></td>
                            <?php
                        }
                        //                echo "<td><a href='../presentation/traitement/t_news.php?id_new=$id'>Supprimer</a></td> <td><a href='?id_update=$id'>Modifier</a></td> <td><a alt='ajuter au panier' title='ajuter au panier' href='../presentation/traitement/produit.php?ajouter_panier=$id'>Add</a></td>";
                        echo '</a></tr>';
                    }
                    echo "</table>";
        } else {
            ?>
                    <div style="text-align: center">
                        <h3 style="text-align: center; margin-top: 20px;">
                            Fin de Liste
                        </h3>
                    </div>
                    <?php
                    return 0;
        }
        return 1;
    }

    public static function getoption($item = 0)
    {
        $req = Reseau::getAllByIdReseau($_SESSION['id_aep']);
        $req = $req->fetchAll();
        $taille = count($req) / 2;
        $i = 0;
        foreach ($req as $ligne) {
            $id = $ligne['id'];
            $nom = $ligne['nom'];
            $abreviation = $ligne['abreviation'];
            echo "<option value='$id' " . ($id == $item ? 'selected' : '') . " >$nom ($abreviation)</option>";
        }
    }

    public static function getoptionXml($item = 0)
    {

        $req = Reseau::getObject2('reseau');
        foreach ($req as $ligne) {
            $id = $ligne['id'];
            $nom = $ligne->PrixsemHS;
            $prenom = $ligne->PrixSemBS;
            //            if ($photot != null){
            echo "<option value='$id' " . ($id == $item ? 'selected' : '') . " >$prenom de $nom</option>";
        }
    }


    public static function getAllReseauFromAepId()
    {
        $req = Reseau::getAllByIdReseau($_SESSION['id_aep']);
        $tab = array();
        $req = $req->fetchAll();
        return $req;
    }
}

Reseau_t::ajout();
Reseau_t::update();
Reseau_t::delete();
Reseau_t::findUpadate();
//Reseau_t::getoption();
//tarif_t::getAll();


