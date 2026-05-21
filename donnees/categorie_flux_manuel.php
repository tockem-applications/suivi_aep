<?php

@include_once("../donnees/manager.php");
@include_once("donnees/manager.php");
@include_once(__DIR__ . "/connexion.php");
@include_once("donnees/connexion.php");

class CategorieFluxManuel extends Manager
{
    public $id;
    public $nom;
    public $type_flux; // 'recette' ou 'charge'
    public $description;
    public $id_aep; // NULL pour global, ou ID spécifique d'un AEP
    public $est_actif;
    public $code_budgetaire; // Code budgétaire de la catégorie
    public $activite_associee; // 'branchements', 'vente_eau', ou 'autre'
    public $ordre_affichage;
    public $date_creation;

    private static $ordreColumnReady = false;

    function getConstraint()
    {
        return array('value' => $this->id, 'column' => 'id');
    }

    function getDonnee()
    {
        $donnees = array(
            'nom' => $this->nom,
            'type_flux' => $this->type_flux,
            'description' => $this->description,
            'id_aep' => $this->id_aep,
            'est_actif' => isset($this->est_actif) ? $this->est_actif : 1,
            'code_budgetaire' => isset($this->code_budgetaire) ? $this->code_budgetaire : null,
            'activite_associee' => isset($this->activite_associee) ? $this->activite_associee : 'autre',
            'ordre_affichage' => isset($this->ordre_affichage) ? (int) $this->ordre_affichage : 0,
        );
        
        // Ajouter date_creation seulement lors de la création
        if (empty($this->id)) {
            $donnees['date_creation'] = date('Y-m-d H:i:s');
        }
        
        return $donnees;
    }

    function getNomTable()
    {
        return "categorie_flux_manuel";
    }

    /**
     * Ajoute la colonne ordre_affichage si absente (idempotent).
     */
    public static function ensureOrdreAffichageColumn()
    {
        if (self::$ordreColumnReady) {
            return true;
        }
        self::$ordreColumnReady = true;
        try {
            $bd = Connexion::connect();
            $stmt = $bd->prepare(
                "SELECT COUNT(*) AS c FROM information_schema.columns
                 WHERE table_schema = DATABASE() AND table_name = 'categorie_flux_manuel' AND column_name = 'ordre_affichage'"
            );
            $stmt->execute();
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$row || (int) $row['c'] === 0) {
                $bd->exec(
                    "ALTER TABLE categorie_flux_manuel
                     ADD COLUMN ordre_affichage INT(11) NOT NULL DEFAULT 0
                     COMMENT 'Ordre affichage compte exploitation'"
                );
                $bd->exec(
                    "UPDATE categorie_flux_manuel SET ordre_affichage = id * 10 WHERE ordre_affichage = 0"
                );
            }
            return true;
        } catch (Exception $e) {
            return false;
        }
    }

    private static function orderByClause()
    {
        self::ensureOrdreAffichageColumn();
        return ' ORDER BY type_flux ASC, ordre_affichage ASC, nom ASC';
    }

    /**
     * Prochain ordre suggéré (incrément de 10).
     */
    public static function getProchainOrdreAffichage($id_aep, $type_flux)
    {
        self::ensureOrdreAffichageColumn();
        $id_aep = $id_aep !== null ? (int) $id_aep : 0;
        $params = array($type_flux);
        $sql = "SELECT COALESCE(MAX(ordre_affichage), 0) + 10 AS n FROM categorie_flux_manuel WHERE type_flux = ?";
        if ($id_aep > 0) {
            $sql .= " AND (id_aep = ? OR id_aep IS NULL)";
            $params[] = $id_aep;
        } else {
            $sql .= " AND id_aep IS NULL";
        }
        $row = self::prepare_query($sql, $params)->fetch(PDO::FETCH_ASSOC);
        return $row ? (int) $row['n'] : 10;
    }

    /**
     * Ordre à enregistrer à la création : valeur saisie ou dernière position du type.
     */
    public static function resolveOrdreAffichagePourCreation($posted_ordre, $id_aep, $type_flux)
    {
        if ($posted_ordre !== null && $posted_ordre !== '' && (int) $posted_ordre > 0) {
            return (int) $posted_ordre;
        }
        return self::getProchainOrdreAffichage($id_aep > 0 ? (int) $id_aep : null, $type_flux);
    }

    /**
     * Récupère toutes les catégories actives
     */
    public static function getAllActives($type_flux = null, $id_aep = null)
    {
        $query = "SELECT * FROM categorie_flux_manuel WHERE est_actif = 1";
        $params = array();
        
        if ($type_flux !== null) {
            $query .= " AND type_flux = ?";
            $params[] = $type_flux;
        }
        
        if ($id_aep !== null) {
            $query .= " AND (id_aep = ? OR id_aep IS NULL)";
            $params[] = $id_aep;
        } else {
            $query .= " AND id_aep IS NULL";
        }
        
        $query .= self::orderByClause();
        
        return self::prepare_query($query, $params);
    }

    /**
     * Récupère toutes les catégories (actives et inactives)
     */
    public static function getAll($type_flux = null, $id_aep = null)
    {
        $query = "SELECT * FROM categorie_flux_manuel WHERE 1=1";
        $params = array();
        
        if ($type_flux !== null) {
            $query .= " AND type_flux = ?";
            $params[] = $type_flux;
        }
        
        if ($id_aep !== null) {
            $query .= " AND (id_aep = ? OR id_aep IS NULL)";
            $params[] = $id_aep;
        } else {
            $query .= " AND id_aep IS NULL";
        }
        
        $query .= ' ORDER BY est_actif DESC, type_flux ASC, ordre_affichage ASC, nom ASC';
        
        return self::prepare_query($query, $params);
    }

    /**
     * Récupère une catégorie par son ID
     */
    public static function getById($id)
    {
        return self::prepare_query("SELECT * FROM categorie_flux_manuel WHERE id = ?", array($id))->fetch();
    }

    /**
     * Catégories rattachées à un AEP (exclut les catégories globales id_aep IS NULL).
     */
    public static function getByAepStrict($id_aep)
    {
        $id_aep = (int) $id_aep;
        return self::prepare_query(
            "SELECT * FROM categorie_flux_manuel WHERE id_aep = ? ORDER BY type_flux ASC, ordre_affichage ASC, nom ASC",
            array($id_aep)
        )->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Autres AEP (hors AEP courant).
     */
    public static function getAutresAeps($id_aep_courant)
    {
        $id_aep_courant = (int) $id_aep_courant;
        if ($id_aep_courant <= 0) {
            return array();
        }
        return self::prepare_query(
            "SELECT id, libele FROM aep WHERE id != ? ORDER BY libele ASC",
            array($id_aep_courant)
        )->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Vérifie si une catégorie (code + type) existe déjà pour un AEP.
     */
    public static function existsOnAep($id_aep, $code_budgetaire, $type_flux)
    {
        $id_aep = (int) $id_aep;
        if ($id_aep <= 0 || $code_budgetaire === null || $code_budgetaire === '') {
            return false;
        }
        $row = self::prepare_query(
            "SELECT id FROM categorie_flux_manuel
             WHERE id_aep = ? AND code_budgetaire = ? AND type_flux = ?
             LIMIT 1",
            array($id_aep, $code_budgetaire, $type_flux)
        )->fetch(PDO::FETCH_ASSOC);
        return $row ? true : false;
    }

    /**
     * Duplique des catégories sources vers l'AEP cible (ignore doublons code+type).
     *
     * @return array{created: int, skipped: int, errors: int}
     */
    public static function duplicateCategoriesToAep(array $source_ids, $id_aep_cible)
    {
        $id_aep_cible = (int) $id_aep_cible;
        $created = 0;
        $skipped = 0;
        $errors = 0;

        if ($id_aep_cible <= 0) {
            return array('created' => 0, 'skipped' => 0, 'errors' => 1);
        }

        foreach ($source_ids as $raw_id) {
            $source_id = (int) $raw_id;
            if ($source_id <= 0) {
                continue;
            }
            $src = self::getById($source_id);
            if (!$src || empty($src['code_budgetaire'])) {
                $errors++;
                continue;
            }
            if ((int) $src['id_aep'] === $id_aep_cible) {
                $skipped++;
                continue;
            }
            if (self::existsOnAep($id_aep_cible, $src['code_budgetaire'], $src['type_flux'])) {
                $skipped++;
                continue;
            }

            $categorie = new CategorieFluxManuel();
            $categorie->code_budgetaire = $src['code_budgetaire'];
            $categorie->nom = $src['nom'];
            $categorie->type_flux = $src['type_flux'];
            $categorie->description = isset($src['description']) ? $src['description'] : '';
            $categorie->est_actif = isset($src['est_actif']) ? (int) $src['est_actif'] : 1;
            $categorie->activite_associee = isset($src['activite_associee']) ? $src['activite_associee'] : 'autre';
            $categorie->ordre_affichage = isset($src['ordre_affichage']) ? (int) $src['ordre_affichage'] : 0;
            $categorie->id_aep = $id_aep_cible;
            $categorie->ajouter();
            $created++;
        }

        return array('created' => $created, 'skipped' => $skipped, 'errors' => $errors);
    }

    /**
     * Indique si chaque catégorie peut monter ou descendre (même type_flux, même périmètre AEP).
     *
     * @return array<int, array{can_up: bool, can_down: bool}>
     */
    public static function getMoveMetaForAep($id_aep)
    {
        self::ensureOrdreAffichageColumn();
        $params = array();
        $sql = "SELECT id, type_flux FROM categorie_flux_manuel WHERE 1=1";
        if ($id_aep > 0) {
            $sql .= " AND (id_aep = ? OR id_aep IS NULL)";
            $params[] = (int) $id_aep;
        } else {
            $sql .= " AND id_aep IS NULL";
        }
        $sql .= " ORDER BY type_flux ASC, ordre_affichage ASC, nom ASC";
        $rows = self::prepare_query($sql, $params)->fetchAll(PDO::FETCH_ASSOC);

        $by_type = array();
        foreach ($rows as $row) {
            $t = $row['type_flux'];
            if (!isset($by_type[$t])) {
                $by_type[$t] = array();
            }
            $by_type[$t][] = (int) $row['id'];
        }

        $meta = array();
        foreach ($by_type as $ids) {
            $n = count($ids);
            foreach ($ids as $i => $cid) {
                $meta[$cid] = array(
                    'can_up' => $i > 0,
                    'can_down' => $i < $n - 1,
                );
            }
        }
        return $meta;
    }

    /**
     * Échange l'ordre d'affichage avec la catégorie voisine (haut ou bas) du même type.
     *
     * @param string $direction 'up' ou 'down'
     */
    public static function deplacerOrdre($id_categorie, $direction, $id_aep)
    {
        self::ensureOrdreAffichageColumn();
        $id_categorie = (int) $id_categorie;
        $id_aep = (int) $id_aep;
        if ($id_categorie <= 0 || !in_array($direction, array('up', 'down'), true)) {
            return false;
        }

        $row = self::getById($id_categorie);
        if (!$row) {
            return false;
        }

        if ($id_aep > 0) {
            if (!empty($row['id_aep']) && (int) $row['id_aep'] !== $id_aep) {
                return false;
            }
        } else {
            if (!empty($row['id_aep'])) {
                return false;
            }
        }

        $type_flux = $row['type_flux'];
        $params = array($type_flux);
        $sql = "SELECT id, ordre_affichage FROM categorie_flux_manuel WHERE type_flux = ?";
        if ($id_aep > 0) {
            $sql .= " AND (id_aep = ? OR id_aep IS NULL)";
            $params[] = $id_aep;
        } else {
            $sql .= " AND id_aep IS NULL";
        }
        $sql .= " ORDER BY ordre_affichage ASC, nom ASC";
        $siblings = self::prepare_query($sql, $params)->fetchAll(PDO::FETCH_ASSOC);

        $idx = -1;
        foreach ($siblings as $i => $s) {
            if ((int) $s['id'] === $id_categorie) {
                $idx = $i;
                break;
            }
        }
        if ($idx < 0) {
            return false;
        }

        $swap_idx = ($direction === 'up') ? $idx - 1 : $idx + 1;
        if ($swap_idx < 0 || $swap_idx >= count($siblings)) {
            return false;
        }

        $ordre_a = (int) $siblings[$idx]['ordre_affichage'];
        $ordre_b = (int) $siblings[$swap_idx]['ordre_affichage'];
        $id_a = (int) $siblings[$idx]['id'];
        $id_b = (int) $siblings[$swap_idx]['id'];

        if ($ordre_a === $ordre_b) {
            if ($direction === 'up') {
                $ordre_a = max(0, $ordre_b - 1);
            } else {
                $ordre_a = $ordre_b + 1;
            }
            self::prepare_query(
                "UPDATE categorie_flux_manuel SET ordre_affichage = ? WHERE id = ?",
                array($ordre_a, $id_a)
            );
        } else {
            self::prepare_query(
                "UPDATE categorie_flux_manuel SET ordre_affichage = ? WHERE id = ?",
                array($ordre_b, $id_a)
            );
            self::prepare_query(
                "UPDATE categorie_flux_manuel SET ordre_affichage = ? WHERE id = ?",
                array($ordre_a, $id_b)
            );
        }
        return true;
    }

    /**
     * Réassigne ordre_affichage (10, 20, 30…) selon l'ordre des IDs fournis.
     *
     * @param int[] $ordered_ids
     */
    public static function reorderOrdreAffichage(array $ordered_ids, $type_flux, $id_aep)
    {
        self::ensureOrdreAffichageColumn();
        if (!in_array($type_flux, array('recette', 'charge'), true)) {
            return false;
        }

        $id_aep = (int) $id_aep;
        $params = array($type_flux);
        $sql = "SELECT id FROM categorie_flux_manuel WHERE type_flux = ?";
        if ($id_aep > 0) {
            $sql .= " AND (id_aep = ? OR id_aep IS NULL)";
            $params[] = $id_aep;
        } else {
            $sql .= " AND id_aep IS NULL";
        }
        $sql .= " ORDER BY ordre_affichage ASC, nom ASC";
        $valid_rows = self::prepare_query($sql, $params)->fetchAll(PDO::FETCH_COLUMN);
        $valid_ids = array();
        foreach ($valid_rows as $vid) {
            $valid_ids[] = (int) $vid;
        }

        $ordered_clean = array();
        foreach ($ordered_ids as $raw_id) {
            $oid = (int) $raw_id;
            if ($oid > 0 && !in_array($oid, $ordered_clean, true)) {
                $ordered_clean[] = $oid;
            }
        }

        sort($valid_ids);
        $sorted_input = $ordered_clean;
        sort($sorted_input);
        if ($sorted_input !== $valid_ids || empty($valid_ids)) {
            return false;
        }

        $ordre = 10;
        foreach ($ordered_clean as $id) {
            self::prepare_query(
                "UPDATE categorie_flux_manuel SET ordre_affichage = ? WHERE id = ?",
                array($ordre, $id)
            );
            $ordre += 10;
        }
        return true;
    }
}
