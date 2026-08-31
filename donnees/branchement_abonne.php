<?php
@include_once("manager.php");
@include_once("../donnees/manager.php");

class BranchementAbonne
{
    const COTE_RESEAU = 'reseau';
    const COTE_OPPOSE = 'oppose';
    const MONTANT_COTE_RESEAU = 62500;
    const MONTANT_COTE_OPPOSE = 70000;

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
        self::ensureCoteReseauColumn();
        self::migrateCotesParMontant();
    }

    public static function ensureCoteReseauColumn()
    {
        try {
            $req = Manager::prepare_query(
                "SELECT COUNT(*) AS c FROM information_schema.columns
                 WHERE table_schema = DATABASE() AND table_name = 'branchement_abonne' AND column_name = 'cote_reseau'",
                array()
            );
            if ($req && (int) $req->fetchColumn() === 0) {
                Manager::prepare_query(
                    "ALTER TABLE branchement_abonne
                     ADD COLUMN cote_reseau ENUM('reseau','oppose') NULL DEFAULT NULL
                     COMMENT 'Côté réseau ou côté opposé (abonnement au service)'
                     AFTER versement_fcfa",
                    array()
                );
            }
        } catch (Exception $e) {
        }
    }

    /**
     * Initialise cote_reseau selon le montant pour les enregistrements sans côté défini.
     */
    public static function migrateCotesParMontant()
    {
        self::ensureCoteReseauColumn();
        try {
            Manager::prepare_query(
                "UPDATE branchement_abonne SET cote_reseau = ?
                 WHERE versement_fcfa = ? AND (cote_reseau IS NULL OR cote_reseau = '')",
                array(self::COTE_RESEAU, self::MONTANT_COTE_RESEAU)
            );
            Manager::prepare_query(
                "UPDATE branchement_abonne SET cote_reseau = ?
                 WHERE versement_fcfa = ? AND (cote_reseau IS NULL OR cote_reseau = '')",
                array(self::COTE_OPPOSE, self::MONTANT_COTE_OPPOSE)
            );
        } catch (Exception $e) {
        }
    }

    /**
     * @return string|null 'reseau' | 'oppose' | null
     */
    public static function normalizeCote($value)
    {
        if ($value === null || $value === '') {
            return null;
        }
        $v = strtolower(trim((string) $value));
        if ($v === self::COTE_RESEAU || $v === 'réseau' || $v === 'cote reseau' || $v === 'côté réseau') {
            return self::COTE_RESEAU;
        }
        if ($v === self::COTE_OPPOSE || $v === 'opposé' || $v === 'oppose' || $v === 'côté opposé') {
            return self::COTE_OPPOSE;
        }
        return null;
    }

    public static function libelleCote($cote)
    {
        if ($cote === self::COTE_RESEAU) {
            return 'Côté réseau';
        }
        if ($cote === self::COTE_OPPOSE) {
            return 'Côté opposé';
        }
        return '—';
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
        $cote = isset($data['cote_reseau']) ? self::normalizeCote($data['cote_reseau']) : null;
        return Manager::prepare_query(
            "INSERT INTO branchement_abonne (id_abone, quartier, code_abonne, telephone, statut, mois, versement_fcfa, cote_reseau)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
            array(
                (int) $data['id_abone'],
                isset($data['quartier']) ? trim($data['quartier']) : null,
                isset($data['code_abonne']) ? trim($data['code_abonne']) : null,
                isset($data['telephone']) ? trim($data['telephone']) : null,
                isset($data['statut']) ? trim($data['statut']) : null,
                isset($data['mois']) ? trim($data['mois']) : null,
                isset($data['versement_fcfa']) ? (int) $data['versement_fcfa'] : 0,
                $cote,
            )
        );
    }

    public static function update($id, $data)
    {
        self::ensureTable();
        $cote = isset($data['cote_reseau']) ? self::normalizeCote($data['cote_reseau']) : null;
        return Manager::prepare_query(
            "UPDATE branchement_abonne SET quartier = ?, code_abonne = ?, telephone = ?, statut = ?, mois = ?, versement_fcfa = ?, cote_reseau = ? WHERE id = ?",
            array(
                isset($data['quartier']) ? trim($data['quartier']) : null,
                isset($data['code_abonne']) ? trim($data['code_abonne']) : null,
                isset($data['telephone']) ? trim($data['telephone']) : null,
                isset($data['statut']) ? trim($data['statut']) : null,
                isset($data['mois']) ? trim($data['mois']) : null,
                isset($data['versement_fcfa']) ? (int) $data['versement_fcfa'] : 0,
                $cote,
                (int) $id,
            )
        );
    }

    public static function delete($id)
    {
        self::ensureTable();
        return Manager::prepare_query("DELETE FROM branchement_abonne WHERE id = ?", array((int) $id));
    }
}
