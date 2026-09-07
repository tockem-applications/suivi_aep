<?php

//require_once("manager.php");
@include_once("../donnees/manager.php");
@include_once("donnees/manager.php");
@include_once(__DIR__ . "/compteur.php");
@include_once("donnees/compteur.php");


class MoisFacturation extends Manager
{

    //public $id;
    public $mois;
    public $date_facturation;
    public $date_depot;
    public $id_constante;
    public $est_actif;
    public $est_mois_base;
    public $description;

    public static function getAllMois($mois_debut, $mois_fin, $id_aep, $id_reseau = 0)
    {
        //        var_dump($id_reseau);
        if ($mois_debut == '') {
            $mois_debut = '1900-01';
        }
        if ($mois_fin == '') {
            $mois_fin = '2200-01';
        }
        $reseau_joining = '';
        //        var_dump($mois_fin);
        if ($id_reseau == 0) {
            // si l'id du reseau est null, il ne faut prendre toute les donnees sur les mois de facturation
            // mais aussi avec celle de tout les reseau
            $reseau_joining = 'left join';
        } else {
            //sinon on recupere les donnees des du reseau en question
            $reseau_joining = 'inner join';
        }

        return self::prepare_query(
            "select m.*, count(f.id) nombre, sum(f.montant_verse) montant_versee,

                           sum(nouvel_index-ancien_index) conso, prix_metre_cube_eau, prix_entretient_compteur, prix_tva, r.nom as reseau
                        from mois_facturation m 
                            inner join constante_reseau c on m.id_constante = c.id
                            inner join indexes id on m.id = id.id_mois_facturation
                            inner join facture f on id.id = f.id_indexes
                            inner join abone a on a.id = f.id_abone
                            $reseau_joining reseau r on a.id_reseau=r.id and ( r.id = ? and r.id!=0)
                        where m.mois>=? and m.mois <=? and c.id_aep=? and m.est_mois_base = 0
                        group by m.id order by mois desc ;",
            array($id_reseau, $mois_debut, $mois_fin, $id_aep)
        );
    }

    /**
     * Statistiques mensuelles réseau / AEP (montants via vue_abones_facturation).
     */
    public static function getStatsReseauParMois($mois_debut, $mois_fin, $id_aep, $id_reseau = 0)
    {
        if ($mois_debut == '') {
            $mois_debut = '1900-01';
        }
        if ($mois_fin == '') {
            $mois_fin = '2200-01';
        }
        $id_aep = (int) $id_aep;
        $id_reseau = (int) $id_reseau;

        $sql = "
            SELECT m.id AS id_mois, m.mois,
                   COUNT(vaf.id) AS nombre,
                   COALESCE(SUM(vaf.consommation), 0) AS conso,
                   COALESCE(SUM(vaf.montant_total), 0) AS montant_facture,
                   COALESCE(SUM(vaf.montant_verse), 0) AS montant_verse
            FROM mois_facturation m
            INNER JOIN constante_reseau cr ON m.id_constante = cr.id
            LEFT JOIN vue_abones_facturation vaf
                ON vaf.id_mois = m.id AND vaf.id_aep = ?
        ";
        $params = array($id_aep);

        if ($id_reseau > 0) {
            $sql .= " AND vaf.id_reseau = ?";
            $params[] = $id_reseau;
        }

        $sql .= "
            WHERE m.mois >= ? AND m.mois <= ? AND cr.id_aep = ? AND m.est_mois_base = 0
            GROUP BY m.id, m.mois
            ORDER BY m.mois DESC
        ";
        $params[] = $mois_debut;
        $params[] = $mois_fin;
        $params[] = $id_aep;

        return self::prepare_query($sql, $params);
    }

    public static function mois_exist($mois, $id_aep)
    {
        $res = self::prepare_query("
                            select m.* 
                            from mois_facturation m 
                                inner join constante_reseau c on c.id = m.id_constante
                            where mois=? and c.id_aep=?", array($mois, $id_aep));
        if (!$res)
            return false;
        $number = count($res->fetchAll());
        return $number == 0;
    }

    public static function mois_est_dernier($mois, $id_aep)
    {
        $res = self::prepare_query("
                            select m.* 
                            from mois_facturation m 
                                inner join constante_reseau c on m.id_constante = c.id 
                            where mois>? and c.id_aep=?;",
            array($mois, $id_aep)
        );
        if (!$res)
            return false;
        $number = count($res->fetchAll());
        return $number == 0;
    }

    public static function getMoisById($selected_moi_id)
    {
        return self::prepare_query("select m.* from mois_facturation m where m.id = ?", array($selected_moi_id));
    }

    public static function createBackupBeforeDeletion($mois_id, $id_aep)
    {
        try {
            // Inclure la classe de sauvegarde
            @include_once("../traitement/backup_t.php");

            if (self::$bd == null)
                self::$bd = Connexion::connect();

            // Récupérer les informations du mois à supprimer avec le nom de l'AEP
            $mois = self::prepare_query("
                SELECT mf.*, cr.id_aep, a.libele as nom_aep
                FROM mois_facturation mf 
                INNER JOIN constante_reseau cr ON mf.id_constante = cr.id 
                INNER JOIN aep a ON cr.id_aep = a.id
                WHERE mf.id = ? AND cr.id_aep = ?
            ", array($mois_id, $id_aep))->fetch();

            if (!$mois) {
                return false;
            }

            // Convertir le mois en lettres
            $moisLettre = getLetterMonth($mois['mois']);

            // Nettoyer le nom de l'AEP pour le nom de fichier
            $nomAepClean = preg_replace('/[^a-zA-Z0-9_-]/', '_', $mois['nom_aep']);

            // Créer le nom du fichier de sauvegarde
            $date = date('Y-m-d_H-i-s');
            $filename = "avant_suppression_" . $nomAepClean . "_" . $moisLettre . "_" . $date . ".sql";
            $backupDir = realpath(__DIR__ . '/..') . DIRECTORY_SEPARATOR . 'backups';

            // Créer le dossier backups s'il n'existe pas
            if (!is_dir($backupDir)) {
                @mkdir($backupDir, 0777, true);
            }

            $backupPath = $backupDir . DIRECTORY_SEPARATOR . $filename;

            // Utiliser la fonction de sauvegarde existante
            Backup_t::phpSqlDump(self::$bd, 'suivi_aep_fokoue', $backupPath);

            return true;

        } catch (Exception $e) {
            return false;
        }
    }


    public static function deleteMonth($id, $id_aep)
    {
        //on fais que les anciens index deviennent les derniers pour le compteurs (mf.est_actif=1 and mf.id = ?)
        // si le mois est le mois actif. On suprime le mois
        // et definit le mois le plus rescent comme le moi actif dans le meme aep (id_constante = (SELECT id FROM constante_reseau WHERE id_aep=?);).
        try {

            // Créer une sauvegarde avant suppression
            self::createBackupBeforeDeletion($id, $id_aep);

            if (self::$bd == null)
                self::$bd = Connexion::connect();
            self::$bd->beginTransaction();
            self::prepare_query("
                update 
                    (
                        select ancien_index, id_compteur 
                         from indexes as i 
                             inner join mois_facturation as mf on i.id_mois_facturation= mf.id
                         where mf.est_actif=1 and mf.id = ?
                    ) as indexes, compteur as co
                 set derniers_index = ancien_index where co.id = indexes.id_compteur; ",
                array($id)
            );

            self::prepare_query("delete from mois_facturation where id=? and est_actif=1", array($id));

            // Récupérer d'abord le mois maximum pour éviter l'erreur 1093
            $maxMois = self::prepare_query("SELECT MAX(mf.mois) as max_mois FROM mois_facturation mf
                INNER JOIN constante_reseau cr ON mf.id_constante = cr.id
                WHERE cr.id_aep = ?", array($id_aep))->fetch();

            if ($maxMois && $maxMois['max_mois']) {
                self::prepare_query("UPDATE mois_facturation 
                                        SET est_actif = 1 
                                        WHERE mois = ? 
                                        and id_constante in (SELECT id FROM constante_reseau cr WHERE cr.id_aep=?);",
                    array($maxMois['max_mois'], $id_aep)
                );
            }
            self::$bd->commit();
            return true;
        } catch (Exception $e) {
            // Annuler la transaction en cas d'erreur
            self::$bd->rollBack();
            return false;
        }
    }

    public static function updateMois($id, $mois_input, $description, $id_aep)
    {
        // On verifie si il y'a pas deja un mois de facturation ayant cette meme valeur de mois. s'il y'en pas on effectue la modification
        $res = self::prepare_query("
                select id from mois_facturation
                where mois = ?  and id not in (
                    select mf.id from mois_facturation mf 
                        inner join constante_reseau as cr on cr.id=mf.id_constante 
                    where cr.id_aep=? and mf.id<=>?)", array($mois_input, $id_aep, $id));
        $res = $res->fetchAll();
        if (empty($res))
            return self::prepare_query("
                update mois_facturation 
                set mois=?, description=? 
                where id=?", array($mois_input, $description, $id));
        return false;
    }

    /**
     * @param array $data           le tableau 'releve' du fichier
     * @param int   $id_mois
     * @param array $photosRangees  chemin relatif dans l'archive => chemin sur disque
     */
    public static function updateIndexFronFile($data, $id_mois, $photosRangees = array())
    {
        try {
            self::ensureTableComplementReleve();
            foreach ($data as $value) {
                $aep = $value['data'];

                foreach ($aep as $ligne) {
                    $id_index = $ligne['id_index'];
                    $id_compteur = $ligne['id_compteur'];
                    $ancien_index = $ligne['ancien_index'];
                    $nouvel_index = $ligne['nouvel_index'];
                    $latitude = isset($ligne['latitude']) ? $ligne['latitude'] : null;
                    $longitude = isset($ligne['longitude']) ? $ligne['longitude'] : null;
                    self::updateOneIndexByIdIdMoisIdCompteur($id_mois, $id_index, $id_compteur, $ancien_index, $nouvel_index, $latitude, $longitude);

                    // Informations complémentaires rapportées du terrain. Elles
                    // n'entrent pas dans la facturation : un défaut ici ne doit
                    // jamais faire échouer l'import de l'index lui-même.
                    self::enregistrerComplementReleve($id_mois, $ligne, $photosRangees);
                }
            }
        } catch (Exception $e) {
            return false;
        }
        return true;
    }

    /**
     * Informations de terrain d'un mois, indexées par identifiant de relevé.
     *
     * Une seule requête pour toute la page : la liste des relevés compte
     * couramment plusieurs centaines de lignes.
     *
     * @return array id_index => array('telephone', 'observation', 'photos' => array)
     */
    public static function getComplementsReleve($id_mois)
    {
        self::ensureTableComplementReleve();
        $complements = array();
        try {
            $rows = self::prepare_query(
                "SELECT id_index, telephone, observation, photos
                 FROM releve_complement WHERE id_mois_facturation = ?",
                array((int) $id_mois)
            );
            if (!$rows) {
                return $complements;
            }
            foreach ($rows->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $photos = array();
                if (!empty($row['photos'])) {
                    foreach (explode('|', $row['photos']) as $chemin) {
                        if ($chemin !== '') {
                            $photos[] = $chemin;
                        }
                    }
                }
                $complements[(int) $row['id_index']] = array(
                    'telephone' => $row['telephone'],
                    'observation' => $row['observation'],
                    'photos' => $photos,
                );
            }
        } catch (Exception $e) {
        }
        return $complements;
    }

    /**
     * Table des informations complémentaires remontées par le mobile :
     * observation de l'agent, téléphone corrigé, photos du compteur.
     *
     * Une table à part plutôt que des colonnes sur `indexes` : ces données sont
     * facultatives, propres à la collecte mobile, et n'ont aucun rôle dans le
     * calcul des factures.
     */
    public static function ensureTableComplementReleve()
    {
        try {
            self::prepare_query(
                "CREATE TABLE IF NOT EXISTS releve_complement (
                    id INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
                    id_mois_facturation INT(10) UNSIGNED NOT NULL,
                    id_index INT(10) UNSIGNED NOT NULL DEFAULT 0,
                    id_compteur INT(10) UNSIGNED NOT NULL DEFAULT 0,
                    telephone VARCHAR(32) DEFAULT NULL,
                    observation TEXT,
                    photos TEXT,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    PRIMARY KEY (id),
                    UNIQUE KEY uniq_releve (id_mois_facturation, id_index, id_compteur),
                    KEY idx_mois (id_mois_facturation)
                ) ENGINE=InnoDB DEFAULT CHARSET=latin1",
                array()
            );
        } catch (Exception $e) {
        }
    }

    /**
     * Enregistre observation, téléphone et photos d'une ligne de relevé.
     *
     * Rien n'est écrit si l'agent n'a rien ajouté : la table ne doit pas se
     * remplir d'enregistrements vides à chaque import.
     */
    private static function enregistrerComplementReleve($id_mois, $ligne, $photosRangees)
    {
        $observation = isset($ligne['observation']) ? trim((string) $ligne['observation']) : '';
        $telephone = isset($ligne['numero_abone']) ? trim((string) $ligne['numero_abone']) : '';

        // Ne retenir que les photos réellement présentes dans l'archive : une
        // référence sans fichier ne sert à rien et induirait en erreur.
        $photos = array();
        if (isset($ligne['photos']) && is_array($ligne['photos'])) {
            foreach ($ligne['photos'] as $relatif) {
                $relatif = (string) $relatif;
                if (isset($photosRangees[$relatif])) {
                    $photos[] = $photosRangees[$relatif];
                }
            }
        }

        if ($observation === '' && count($photos) === 0) {
            return;
        }

        $id_index = (int) (isset($ligne['id_index']) ? $ligne['id_index'] : 0);
        $id_compteur = (int) (isset($ligne['id_compteur']) ? $ligne['id_compteur'] : 0);

        try {
            self::prepare_query(
                "INSERT INTO releve_complement
                    (id_mois_facturation, id_index, id_compteur, telephone, observation, photos)
                 VALUES (?, ?, ?, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE
                    telephone = VALUES(telephone),
                    observation = VALUES(observation),
                    photos = VALUES(photos)",
                array(
                    (int) $id_mois,
                    $id_index,
                    $id_compteur,
                    $telephone === '' ? null : substr($telephone, 0, 32),
                    $observation === '' ? null : $observation,
                    count($photos) === 0 ? null : implode('|', $photos),
                )
            );
        } catch (Exception $e) {
            // Voir plus haut : l'index prime, le complément est un bonus.
        }
    }

    /**
     * Écrit sur disque les photos extraites de l'archive.
     *
     * Le nom d'origine n'est jamais réutilisé tel quel : il vient d'un fichier
     * reçu, donc potentiellement construit pour sortir du dossier ou pour être
     * servi comme script. On ne garde que l'extension, validée, et on rebaptise.
     *
     * @return array chemin relatif dans l'archive => chemin relatif au projet
     */
    public static function rangerPhotosImportees($photos, $id_mois)
    {
        if (!is_array($photos) || count($photos) === 0) {
            return array();
        }

        $dossier = __DIR__ . '/photos_releve/' . (int) $id_mois;
        if (!is_dir($dossier) && !@mkdir($dossier, 0777, true)) {
            return array();
        }
        self::protegerDossierPhotos(__DIR__ . '/photos_releve');

        $extensionsValides = array('jpg' => 'jpg', 'jpeg' => 'jpg', 'png' => 'png');
        $ranges = array();
        $rang = 0;

        foreach ($photos as $relatif => $contenu) {
            $extension = strtolower(pathinfo($relatif, PATHINFO_EXTENSION));
            if (!isset($extensionsValides[$extension])) {
                continue;
            }
            // Le contenu doit vraiment être une image, pas seulement en porter
            // l'extension : getimagesize() lit l'en-tête réel du fichier.
            $temporaire = tempnam(sys_get_temp_dir(), 'rlv');
            if ($temporaire === false) {
                continue;
            }
            file_put_contents($temporaire, $contenu);
            $info = @getimagesize($temporaire);
            @unlink($temporaire);
            if ($info === false) {
                continue;
            }

            $rang++;
            $base = preg_replace('/[^A-Za-z0-9_-]/', '', basename($relatif, '.' . $extension));
            if ($base === '') {
                $base = 'photo';
            }
            $nom = $base . '_' . $rang . '.' . $extensionsValides[$extension];
            if (@file_put_contents($dossier . '/' . $nom, $contenu) === false) {
                continue;
            }
            $ranges[$relatif] = 'donnees/photos_releve/' . (int) $id_mois . '/' . $nom;
        }

        return $ranges;
    }

    /**
     * Interdit l'exécution de tout script dans le dossier des photos.
     *
     * Ceinture et bretelles : le contenu y est déjà validé comme image, mais le
     * dossier vit sous la racine web et une seule faille en amont suffirait.
     */
    private static function protegerDossierPhotos($dossier)
    {
        $htaccess = $dossier . '/.htaccess';
        if (file_exists($htaccess)) {
            return;
        }
        @file_put_contents(
            $htaccess,
            "# Photos de releve : contenu statique uniquement, jamais execute.\n"
            . "php_flag engine off\n"
            . "RemoveHandler .php .phtml .php3 .php4 .php5\n"
            . "AddType text/plain .php .phtml .php3 .php4 .php5\n"
        );
    }

    /**
     * Coordonnées GPS remontées par le mobile plausibles : non vides, pas (0,0)
     * (= non capturé côté mobile), et dans une emprise large couvrant le Cameroun
     * (garde-fou contre une position aberrante, pas une vérification précise par AEP).
     */
    private static function coordonneesMobilesValides($latitude, $longitude)
    {
        if ($latitude === null || $longitude === null || $latitude === '' || $longitude === '') {
            return false;
        }
        $lat = (float) $latitude;
        $lon = (float) $longitude;
        if ($lat === 0.0 && $lon === 0.0) {
            return false;
        }
        return $lat >= 1.0 && $lat <= 14.0 && $lon >= 7.0 && $lon <= 17.0;
    }

    public static function updateOneIndexByIdIdMoisIdCompteur($id_mois, $id_index, $id_compteur, $ancien_index, $nouvel_index, $latitude = null, $longitude = null)
    {
        $index = max($nouvel_index, $ancien_index);
        var_dump(array($id_mois, $id_index, $id_compteur, $ancien_index, $nouvel_index));
        try {
            $req = self::prepare_query("
                select i.* from indexes as i
                    inner join compteur as co on i.id_compteur=co.id
                where i.id =? and i.id_mois_facturation=? and co.id=? and i.nouvel_index = ?
            ", array($id_index, $id_mois, $id_compteur, $index));
            var_dump($req->fetchAll(PDO::FETCH_ASSOC));
            $toto = self::prepare_query("
                update indexes as i
                    inner join compteur as co on i.id_compteur=co.id
                set i.nouvel_index = ?, co.derniers_index = ?
                where i.id=? and co.id = ? and i.id_mois_facturation = ? and i.nouvel_index <> ? and i.ancien_index <> ?
            ", array($index, $index, $id_index, $id_compteur, $id_mois, $index, $index));
            if (self::coordonneesMobilesValides($latitude, $longitude)) {
                Compteur::updateCoordonnees($id_compteur, $latitude, $longitude);
            }
            //            var_dump();
            return true;
        } catch (Exception $e) {
            return false;
        }

    }


    function getconstraint()
    {
        return array('value' => $this->id, 'column' => 'id');
    }

    function getDonnee()
    {
        return array(
            'mois' => $this->mois
            ,
            'date_facturation' => $this->date_facturation
            ,
            'date_depot' => $this->date_depot
            ,
            'id_constante' => $this->id_constante
            ,
            'description' => $this->description
            ,
            'est_actif' => $this->est_actif
            ,
            'est_mois_base' => isset($this->est_mois_base) ? $this->est_mois_base : 0
        );
    }

    function getNomTable()
    {
        return "mois_facturation";
    }

    function ajouterEtActiver()
    {
        $res = 0;
        try {
            $this->est_actif = true;
            $this->connecter();
            self::$bd->beginTransaction();
            self::query("update mois_facturation set est_actif=false where est_actif=true");
            $res = $this->ajouter();
            if ($res) {
                self::$bd->commit();
            } else
                self::$bd->rollBack();
        } catch (Exception $e) {
            self::$bd->rollBack();
            $res = 0;
        }
        return $res;

    }


    public static function getOrderedMonthList($id_aep)
    {
        return self::prepare_query('
                        select m.id, m.id_constante, m.mois 
                        from mois_facturation m 
                            inner join constante_reseau c on c.id=m.id_constante 
                        where c.id_aep=?
                        order by mois desc;',
            array($id_aep)
        );
    }

    public static function updateDateDepot($id_mois, $date_depot, $date_releve)
    {
        return self::query("update mois_facturation set date_depot='$date_depot', date_releve='$date_releve' where id='$id_mois';");
    }

    public function ajouternouvelleListeFacture($tab_index, $id_aep)
    {
        try {
            require("../donnees/Abones.php");
            require("../donnees/facture.php");
            $this->connecter();
            self::$bd->beginTransaction();
            //            echo "kkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkk<br>";
            //on recupere la liste des ancienne factures pour avoir acces aux penalites et impayes
            $res = Facture::getAncienneFacture($id_aep);
            echo "oooooooooooooooooooooooooooooooooooooooo";
            $tab_ancienne_facture = $res->fetchAll();
            var_dump($tab_ancienne_facture);
            self::prepare_query("
                        update mois_facturation m 
                            inner join constante_reseau c on m.id_constante = c.id  
                            set m.est_actif=false  
                            where m.est_actif=true and c.id_aep=?",
                array($id_aep)
            );
            $res = $this->ajouter();
            echo "bobobobobobobobooboboobobobooboboboboobbobobobob";
            // si l'ajout du nouveau mois fais proble on annule tout et on sort
            if (!$res) {
                self::$bd->rollBack();
                return 0;
            }
            var_dump($tab_index);
            echo "111111111111111111111111111111111111111111111111111";
            //on recupere l'id du mois que l'on vient d'ajouter pour la mettre dans nos prochaines facture
            $id_mois_facturation = self::getIdMoisFacturationActive($id_aep);
            if (!$id_mois_facturation) {
                self::$bd->rollBack(); //si on ne la retrouve pas il vaut mieu tout arreter 
                return 0;
            }
            $id_ancienne_facture = '';

            //On parcours la liste des index venu du mobile et on recupere les element essentiels
            //nouvel index, ancien index, longitude, latitude, id  de l'abone etc
            foreach ($tab_index as $ligne_tab_index) {
                echo "22222222222222222222222222222222222222222222222222222222222<br>";
                $nouvel_index = (float) $ligne_tab_index['nouvel_index'];
                $ancien_index = (float) $ligne_tab_index['ancien_index'];
                $id_abone = (int) $ligne_tab_index['id'];
                $id_compteur = (int) $ligne_tab_index['id_compteur'];

                //une fois que l'on a le nouvel index de l'abone, on le place dans l'abone pour un acces facile
                //mais avant il faut se rassurer qu'il est au moins egal a l'ancien index. sinon on ne lui ajoute pas de facture
                if ($nouvel_index < $ancien_index) {
                    $nouvel_index = $ancien_index;
                }
                echo "33333333333333333333333333333333333333333333333<br>";

                //on initialise  quelques valeur utiles pour les nouvelles factures
                $nouvel_impaye = 0.00;
                $found = false;
                $nouvelle_penalite = 0.00;
                //                echo"*****************************************************************<br>";
//                echo"*****************************************************************<br>";
//                echo"*****************************************************************<br>";
                $impaye = 0;
                var_dump(count($tab_ancienne_facture));
                var_dump(count($tab_ancienne_facture));
                var_dump(count($tab_ancienne_facture));
                foreach ($tab_ancienne_facture as $ligne_ancienne_facture) {
                    echo "44444444444444444444444444444444444444444444444<br>";
                    if ((int) $ligne_ancienne_facture['id_compteur'] == $id_compteur) {

                        $prix_eau = $ligne_ancienne_facture['prix_metre_cube_eau'];
                        $id_ancienne_facture = $ligne_ancienne_facture['id'];
                        $prix_entretien = (int) $ligne_ancienne_facture['prix_entretient_compteur'];
                        $montant_verse = (int) $ligne_ancienne_facture['montant_verse'];
                        $impaye = (int) $ligne_ancienne_facture['impaye'];
                        //                        $impaye = 0;
                        $penalite = (int) $ligne_ancienne_facture['penalite'];
                        $prix_tva = (float) $ligne_ancienne_facture['prix_tva'];
                        $ex_nouvel_index = (float) $ligne_ancienne_facture['nouvel_index'];
                        $mois = $ligne_ancienne_facture['mois'];
                        $ex_ancien_index = (float) $ligne_ancienne_facture['ancien_index'];

                        if ($ex_nouvel_index != $ancien_index) {
                            $ancien_index = $ex_nouvel_index;
                        }
                        if ($ancien_index > $nouvel_index) {
                            $nouvel_index = $ancien_index;
                        }
                        $conso = $ex_nouvel_index - $ex_ancien_index;

                        //ici on calcule le nouvel impayer a partir de la consommation de l'ancien mois
                        $nouvel_impaye = Facture::calculeImpaye($ex_nouvel_index, $ex_ancien_index, $prix_tva, $prix_entretien, $prix_eau, $impaye, $montant_verse, $penalite);
                        echo "============= $impaye ================= $nouvel_impaye ====================== $montant_verse ============ $mois ========<br>";
                        break;
                    }
                    echo "5555555555555555555555555555555555555555555555555555555555<br>";
                    //maintenant on peut creer notre nouvelle facture et l'ajouter
                }

                echo "bonjour la famille <br>";
                // ace stade les index sont valides donc onsere le nouvel index dans la table de l'abone
                $res = Abones::updateIndexByCompteur_id($id_compteur, $nouvel_index);
                var_dump("eriidkddndndndndnndnd");
                if (!$res) {
                    self::$bd->rollBack();
                    return 0;
                }

                //                $estFacturable = Compteur::estFacturable($id_compteur);

                $nouvelle_facture = new Facture(
                    0,
                    $ancien_index
                    ,
                    $nouvel_index
                    ,
                    $impaye < 0 ? -$impaye : 0 // si l'ipaye est negatif on alors l'abonee avait verse plus du montant de la facture alors il fau reporter au mois suivant
                    ,
                    '00/00/0000'
                    ,
                    $nouvelle_penalite,
                    $id_mois_facturation
                    ,
                    $id_abone,
                    '',
                    $id_compteur
                );
                $res = $nouvelle_facture->save_facture();
                //maintenant, si on reporte l'impaye negatif au mois suivant alors il doit etre suprimee
                if ($impaye < 0) {
                    Impaye::deleteByIdCacture((int) $id_ancienne_facture);
                } else if ((int) $nouvel_impaye > 0) {
                    $impaye_object = new Impaye('', (int) $id_ancienne_facture, (int) $nouvel_impaye, 0, '00/00/0000');
                    $impaye_object->ajouter();
                }
                if (!$res) {
                    self::$bd->rollBack();
                    return 0;
                }
            }

        } catch (Exception $e) {
            self::$bd->rollBack();
            var_dump($e);
            $res = 0;
            return 0;
        }
        self::$bd->commit();
        return (int) $id_mois_facturation;
    }

    public static function getMoisFacturationActive($id_aep)
    {
        return Manager::prepare_query('
                        select m.* 
                        from mois_facturation m 
                            inner join constante_reseau c on c.id=m.id_constante 
                        where c.id_aep=? and m.est_actif=true', array($id_aep));
    }

    public static function getOneById($id_mois)
    {
        return Manager::query("select * from mois_facturation  where id=$id_mois");
    }

    public static function getIdMoisFacturationActive($id_aep)
    {
        $res = self::getMoisFacturationActive($id_aep);
        $data = $res->fetchAll();
        //var_dump( (int)$data[0]['id']);
        if ($res->rowCount() == 0) {
            return 0;
        }
        return (int) $data[0]['id'];
    }

    public function __construct($id, $mois, $date_facturation, $date_depot, $id_constante, $description, $est_actif)
    {
        $this->id = $id;
        $this->mois = $mois;
        $this->date_facturation = $date_facturation;
        $this->date_depot = $date_depot;
        $this->id_constante = $id_constante;
        $this->description = $description;
        $this->est_actif = $est_actif;
    }
}