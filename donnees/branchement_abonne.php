<?php
@include_once("manager.php");
@include_once("../donnees/manager.php");

class BranchementAbonne
{
    public static function ensureTable()
    {
        try {
            Manager::prepare_query(
                "CREATE TABLE IF NOT EXISTS branchement_abonne (
                    id INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
                    id_abone INT(10) UNSIGNED NOT NULL,
                    quartier VARCHAR(64) DEFAULT NULL,
                    code_abonne VARCHAR(32) DEFAULT NULL,
                    telephone VARCHAR(32) DEFAULT NULL,
                    statut VARCHAR(16) DEFAULT NULL,
                    mois VARCHAR(16) DEFAULT NULL,
                    versement_fcfa INT(11) DEFAULT 0,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    PRIMARY KEY (id),
                    KEY idx_ba_abone (id_abone),
                    CONSTRAINT fk_ba_abone FOREIGN KEY (id_abone) REFERENCES abone(id) ON DELETE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=latin1",
                array()
            );
        } catch (Exception $e) {
        }
    }

    public static function getByAboneId($id_abone)
    {
        self::ensureTable();
        $q = Manager::prepare_query(
            "SELECT * FROM branchement_abonne WHERE id_abone = ? ORDER BY id DESC LIMIT 1",
            array($id_abone)
        );
        return $q ? $q->fetch() : false;
    }

    public static function create($data)
    {
        self::ensureTable();
        return Manager::prepare_query(
            "INSERT INTO branchement_abonne (id_abone, quartier, code_abonne, telephone, statut, mois, versement_fcfa)
             VALUES (?, ?, ?, ?, ?, ?, ?)",
            array(
                (int) $data['id_abone'],
                isset($data['quartier']) ? trim($data['quartier']) : null,
                isset($data['code_abonne']) ? trim($data['code_abonne']) : null,
                isset($data['telephone']) ? trim($data['telephone']) : null,
                isset($data['statut']) ? trim($data['statut']) : null,
                isset($data['mois']) ? trim($data['mois']) : null,
                isset($data['versement_fcfa']) ? (int) $data['versement_fcfa'] : 0
            )
        );
    }

    public static function update($id, $data)
    {
        self::ensureTable();
        return Manager::prepare_query(
            "UPDATE branchement_abonne SET quartier = ?, code_abonne = ?, telephone = ?, statut = ?, mois = ?, versement_fcfa = ? WHERE id = ?",
            array(
                isset($data['quartier']) ? trim($data['quartier']) : null,
                isset($data['code_abonne']) ? trim($data['code_abonne']) : null,
                isset($data['telephone']) ? trim($data['telephone']) : null,
                isset($data['statut']) ? trim($data['statut']) : null,
                isset($data['mois']) ? trim($data['mois']) : null,
                isset($data['versement_fcfa']) ? (int) $data['versement_fcfa'] : 0,
                (int) $id
            )
        );
    }

    public static function delete($id)
    {
        self::ensureTable();
        return Manager::prepare_query("DELETE FROM branchement_abonne WHERE id = ?", array((int) $id));
    }
}


