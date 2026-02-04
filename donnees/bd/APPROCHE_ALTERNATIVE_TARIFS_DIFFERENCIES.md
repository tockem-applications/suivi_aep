# Approche Alternative pour les Tarifs Différenciés

## Problèmes de l'approche actuelle

1. **Vue `vue_indexes_tarifs` avec UNION ALL** : Peut retourner plusieurs lignes pour un même index si plusieurs tarifs différenciés matchent
2. **Sous-requête corrélée dans `vue_abones_facturation`** : Cause des problèmes de performance (O(n²))
3. **Logique complexe** : La sélection du tarif est dispersée entre plusieurs vues
4. **Pas de garantie d'unicité** : Un index peut avoir plusieurs tarifs

## Approche recommandée

### 1. Architecture en couches

```
┌─────────────────────────────────────┐
│   vue_indexes_tarifs_resolved       │  ← Vue finale garantissant 1 ligne par index
│   (1 ligne par index, tarif résolu) │
└─────────────────────────────────────┘
              ↓ utilise
┌─────────────────────────────────────┐
│   vue_indexes_tarifs                │  ← Vue intermédiaire (tous les tarifs possibles)
│   (peut avoir plusieurs lignes)      │
└─────────────────────────────────────┘
              ↓ utilise
┌─────────────────────────────────────┐
│   Tables: indexes, tarif_differencie│
│   constante_reseau, etc.           │
└─────────────────────────────────────┘
```

### 2. Vue `vue_indexes_tarifs_resolved` (garantit l'unicité)

Cette vue résout le tarif à appliquer pour chaque index en une seule ligne :

```sql
CREATE OR REPLACE VIEW `vue_indexes_tarifs_resolved` AS
SELECT 
    i.id AS id_indexes,
    i.id_compteur,
    i.id_mois_facturation,
    i.ancien_index,
    i.nouvel_index,
    i.message,
    (i.nouvel_index - i.ancien_index) AS consommation,
    
    -- Informations de constante_reseau
    cr.id AS id_constante_reseau,
    cr.id_aep,
    cr.date_creation,
    cr.est_actif,
    cr.description,
    
    -- Tarif résolu : différencié si disponible, sinon base
    COALESCE(
        td.prix_metre_cube_eau,
        cr.prix_metre_cube_eau
    ) AS prix_metre_cube_eau,
    
    COALESCE(
        td.prix_entretient_compteur,
        cr.prix_entretient_compteur
    ) AS prix_entretient_compteur,
    
    COALESCE(
        td.prix_tva,
        cr.prix_tva
    ) AS prix_tva,
    
    -- Métadonnées du tarif
    CASE 
        WHEN td.id IS NOT NULL THEN 'differencie'
        ELSE 'base'
    END AS type_tarif,
    
    td.id AS id_tarif_differencie,
    td.min_consommation,
    td.max_consommation
    
FROM 
    indexes i
    INNER JOIN mois_facturation mf ON mf.id = i.id_mois_facturation
    INNER JOIN constante_reseau cr ON cr.id = mf.id_constante
    LEFT JOIN (
        -- Prendre le tarif différencié avec le min_consommation le plus élevé qui match
        -- (priorité au tarif le plus spécifique)
        SELECT 
            td1.*
        FROM tarif_differencie td1
        WHERE td1.id_constante_reseau = cr.id
        AND (i.nouvel_index - i.ancien_index) >= td1.min_consommation
        AND (
            td1.max_consommation IS NULL 
            OR (i.nouvel_index - i.ancien_index) < td1.max_consommation
        )
        ORDER BY td1.min_consommation DESC
        LIMIT 1
    ) td ON TRUE;
```

**Problème** : MySQL n'autorise pas les sous-requêtes corrélées dans le FROM d'une vue.

### 3. Solution avec fonction SQL ou procédure stockée

**Option A : Utiliser une fonction SQL (si disponible)**

```sql
DELIMITER $$

CREATE FUNCTION get_tarif_for_index(
    p_id_index INT,
    p_consommation DECIMAL(10,2)
) RETURNS INT
READS SQL DATA
DETERMINISTIC
BEGIN
    DECLARE v_id_constante INT;
    DECLARE v_id_tarif_diff INT;
    
    -- Récupérer l'id_constante pour cet index
    SELECT mf.id_constante INTO v_id_constante
    FROM indexes i
    INNER JOIN mois_facturation mf ON mf.id = i.id_mois_facturation
    WHERE i.id = p_id_index;
    
    -- Chercher le tarif différencié qui match
    SELECT td.id INTO v_id_tarif_diff
    FROM tarif_differencie td
    WHERE td.id_constante_reseau = v_id_constante
    AND p_consommation >= td.min_consommation
    AND (td.max_consommation IS NULL OR p_consommation < td.max_consommation)
    ORDER BY td.min_consommation DESC
    LIMIT 1;
    
    RETURN COALESCE(v_id_tarif_diff, 0);
END$$

DELIMITER ;
```

**Option B : Table temporaire matérialisée (meilleure performance)**

Créer une table `indexes_tarifs_cache` qui est mise à jour via un trigger ou un job :

```sql
CREATE TABLE `indexes_tarifs_cache` (
    `id_indexes` INT UNSIGNED NOT NULL PRIMARY KEY,
    `id_constante_reseau` INT UNSIGNED NOT NULL,
    `id_tarif_differencie` INT UNSIGNED NULL,
    `prix_metre_cube_eau` INT UNSIGNED NOT NULL,
    `prix_entretient_compteur` INT UNSIGNED NOT NULL,
    `prix_tva` DECIMAL(7,2) UNSIGNED NOT NULL,
    `type_tarif` ENUM('base', 'differencie') NOT NULL,
    `consommation` DECIMAL(10,2) UNSIGNED NOT NULL,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`id_indexes`) REFERENCES `indexes`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`id_tarif_differencie`) REFERENCES `tarif_differencie`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB;

-- Trigger pour mettre à jour automatiquement
DELIMITER $$

CREATE TRIGGER `trg_indexes_tarifs_cache_insert`
AFTER INSERT ON `indexes`
FOR EACH ROW
BEGIN
    CALL refresh_tarif_for_index(NEW.id);
END$$

CREATE TRIGGER `trg_indexes_tarifs_cache_update`
AFTER UPDATE ON `indexes`
FOR EACH ROW
BEGIN
    IF OLD.ancien_index != NEW.ancien_index OR OLD.nouvel_index != NEW.nouvel_index THEN
        CALL refresh_tarif_for_index(NEW.id);
    END IF;
END$$

DELIMITER ;
```

### 4. Solution recommandée : Vue avec LEFT JOIN optimisé

La meilleure approche pour MySQL est d'utiliser une vue qui garantit l'unicité via un LEFT JOIN avec une sous-requête optimisée :

```sql
CREATE OR REPLACE VIEW `vue_indexes_tarifs_resolved` AS
SELECT 
    i.id AS id_indexes,
    i.id_compteur,
    i.id_mois_facturation,
    i.ancien_index,
    i.nouvel_index,
    i.message,
    (i.nouvel_index - i.ancien_index) AS consommation,
    
    cr.id AS id_constante_reseau,
    cr.id_aep,
    cr.date_creation,
    cr.est_actif,
    cr.description,
    
    -- Tarif résolu : différencié si disponible, sinon base
    COALESCE(td.prix_metre_cube_eau, cr.prix_metre_cube_eau) AS prix_metre_cube_eau,
    COALESCE(td.prix_entretient_compteur, cr.prix_entretient_compteur) AS prix_entretient_compteur,
    COALESCE(td.prix_tva, cr.prix_tva) AS prix_tva,
    
    CASE 
        WHEN td.id IS NOT NULL THEN 'differencie'
        ELSE 'base'
    END AS type_tarif,
    
    td.id AS id_tarif_differencie,
    td.min_consommation,
    td.max_consommation
    
FROM 
    indexes i
    INNER JOIN mois_facturation mf ON mf.id = i.id_mois_facturation
    INNER JOIN constante_reseau cr ON cr.id = mf.id_constante
    LEFT JOIN tarif_differencie td ON (
        td.id_constante_reseau = cr.id
        AND (i.nouvel_index - i.ancien_index) >= td.min_consommation
        AND (td.max_consommation IS NULL OR (i.nouvel_index - i.ancien_index) < td.max_consommation)
    )
    -- Pour gérer le cas où plusieurs tarifs différenciés matchent,
    -- on prend celui avec le min_consommation le plus élevé (le plus spécifique)
    LEFT JOIN (
        SELECT 
            td2.id_constante_reseau,
            td2.min_consommation,
            MAX(td2.min_consommation) as max_min_consommation
        FROM tarif_differencie td2
        GROUP BY td2.id_constante_reseau, td2.min_consommation
    ) td_best ON (
        td_best.id_constante_reseau = cr.id
        AND td.min_consommation = td_best.max_min_consommation
    )
WHERE 
    td_best.max_min_consommation IS NOT NULL 
    OR td.id IS NULL;  -- Soit on a un tarif différencié (le meilleur), soit aucun
```

**Problème** : Cette approche est encore complexe et peut avoir des problèmes de performance.

### 5. Solution finale recommandée : Vue simple avec logique dans l'application

**Principe** : La vue retourne TOUS les tarifs possibles, et la logique de sélection se fait dans l'application PHP.

```sql
-- Vue simple qui retourne tous les tarifs possibles
CREATE OR REPLACE VIEW `vue_indexes_tarifs` AS
SELECT 
    i.id AS id_indexes,
    i.id_compteur,
    i.id_mois_facturation,
    i.ancien_index,
    i.nouvel_index,
    i.message,
    (i.nouvel_index - i.ancien_index) AS consommation,
    
    cr.id AS id_constante_reseau,
    cr.id_aep,
    cr.prix_metre_cube_eau AS prix_metre_cube_eau_base,
    cr.prix_entretient_compteur AS prix_entretient_compteur_base,
    cr.prix_tva AS prix_tva_base,
    
    td.id AS id_tarif_differencie,
    td.prix_metre_cube_eau AS prix_metre_cube_eau_diff,
    td.prix_entretient_compteur AS prix_entretient_compteur_diff,
    td.prix_tva AS prix_tva_diff,
    td.min_consommation,
    td.max_consommation,
    
    -- Indicateur : est-ce que ce tarif différencié match ?
    CASE 
        WHEN td.id IS NOT NULL 
        AND (i.nouvel_index - i.ancien_index) >= td.min_consommation
        AND (td.max_consommation IS NULL OR (i.nouvel_index - i.ancien_index) < td.max_consommation)
        THEN 1
        ELSE 0
    END AS tarif_diff_match,
    
    -- Priorité : plus le min_consommation est élevé, plus le tarif est spécifique
    COALESCE(td.min_consommation, 0) AS priorite
    
FROM 
    indexes i
    INNER JOIN mois_facturation mf ON mf.id = i.id_mois_facturation
    INNER JOIN constante_reseau cr ON cr.id = mf.id_constante
    LEFT JOIN tarif_differencie td ON td.id_constante_reseau = cr.id
ORDER BY 
    i.id, 
    tarif_diff_match DESC,  -- Les tarifs différenciés qui matchent en premier
    priorite DESC;          -- Puis par priorité (plus spécifique d'abord)
```

**Dans l'application PHP** :

```php
class TarifResolver {
    /**
     * Résout le tarif à appliquer pour un index donné
     * @param int $id_indexes
     * @param bool $tarif_differencie_autorise
     * @return array|null
     */
    public static function resolveTarifForIndex($id_indexes, $tarif_differencie_autorise = true) {
        $tarifs = Manager::prepare_query(
            "SELECT * FROM vue_indexes_tarifs WHERE id_indexes = ?",
            array($id_indexes)
        )->fetchAll();
        
        if (empty($tarifs)) {
            return null;
        }
        
        // Si tarif différencié autorisé, chercher le meilleur tarif différencié qui match
        if ($tarif_differencie_autorise) {
            foreach ($tarifs as $tarif) {
                if ($tarif['tarif_diff_match'] == 1) {
                    return array(
                        'prix_metre_cube_eau' => $tarif['prix_metre_cube_eau_diff'],
                        'prix_entretient_compteur' => $tarif['prix_entretient_compteur_diff'],
                        'prix_tva' => $tarif['prix_tva_diff'],
                        'type_tarif' => 'differencie',
                        'id_tarif_differencie' => $tarif['id_tarif_differencie']
                    );
                }
            }
        }
        
        // Sinon, utiliser le tarif de base (première ligne, toujours présente)
        $base = $tarifs[0];
        return array(
            'prix_metre_cube_eau' => $base['prix_metre_cube_eau_base'],
            'prix_entretient_compteur' => $base['prix_entretient_compteur_base'],
            'prix_tva' => $base['prix_tva_base'],
            'type_tarif' => 'base',
            'id_tarif_differencie' => null
        );
    }
}
```

### 6. Vue `vue_abones_facturation` simplifiée

```sql
CREATE OR REPLACE VIEW `vue_abones_facturation` AS
SELECT
    i.id AS id,
    i.id_compteur,
    cr.id_aep,
    mf.id AS id_mois,
    a.id AS id_abone,
    a.nom AS nom_abone,
    a.id_reseau,
    a.numero_telephone,
    mf.mois,
    cr.id AS id_constante_reseau,
    i.id_mois_facturation,
    mf.date_facturation,
    mf.date_depot,
    i.ancien_index,
    i.nouvel_index,
    f.id AS id_facture,
    mf.date_releve,
    f.penalite,
    a.numero_compte_anticipation,
    a.tarif_differencie_autorise,
    
    -- Les prix seront résolus dans l'application PHP
    -- Ici on retourne les deux options (base et différencié si disponible)
    cr.prix_entretient_compteur AS prix_entretient_compteur_base,
    cr.prix_metre_cube_eau AS prix_metre_cube_eau_base,
    cr.prix_tva AS prix_tva_base,
    
    -- Tarif différencié (si disponible et match)
    td.prix_entretient_compteur AS prix_entretient_compteur_diff,
    td.prix_metre_cube_eau AS prix_metre_cube_eau_diff,
    td.prix_tva AS prix_tva_diff,
    td.id AS id_tarif_differencie,
    
    -- Consommation
    (i.nouvel_index - i.ancien_index) AS consommation,
    
    -- Calculs (utiliser les prix résolus dans l'application)
    -- Ces colonnes sont calculées dans l'application PHP après résolution du tarif
    f.montant_verse
    
FROM
    abone a
    JOIN facture f ON a.id = f.id_abone
    JOIN indexes i ON f.id_indexes = i.id
    JOIN mois_facturation mf ON i.id_mois_facturation = mf.id
    JOIN constante_reseau cr ON mf.id_constante = cr.id
    LEFT JOIN tarif_differencie td ON (
        td.id_constante_reseau = cr.id
        AND (i.nouvel_index - i.ancien_index) >= td.min_consommation
        AND (td.max_consommation IS NULL OR (i.nouvel_index - i.ancien_index) < td.max_consommation)
    )
    -- Prendre le tarif différencié le plus spécifique (min_consommation le plus élevé)
    LEFT JOIN (
        SELECT 
            td2.id_constante_reseau,
            MAX(td2.min_consommation) as max_min_consommation
        FROM tarif_differencie td2
        GROUP BY td2.id_constante_reseau
    ) td_best ON (
        td_best.id_constante_reseau = cr.id
        AND td.min_consommation = td_best.max_min_consommation
    )
WHERE
    (td_best.max_min_consommation IS NOT NULL AND td.id IS NOT NULL)
    OR td.id IS NULL;  -- Soit on a le meilleur tarif différencié, soit aucun
```

## Avantages de cette approche

1. **Performance** : Pas de sous-requêtes corrélées dans les vues
2. **Simplicité** : Logique claire et séparée
3. **Flexibilité** : Facile d'ajouter de nouveaux types de tarifs
4. **Maintenabilité** : Code plus facile à comprendre et déboguer
5. **Testabilité** : La logique de résolution peut être testée indépendamment

## Inconvénients

1. **Calculs dans l'application** : Les montants doivent être calculés en PHP
2. **Plus de requêtes** : Peut nécessiter des requêtes supplémentaires pour résoudre les tarifs

## Recommandation finale

**Utiliser l'approche 5 (Vue simple + logique PHP)** car :
- Compatible avec toutes les versions de MySQL
- Performance prévisible
- Facile à maintenir et déboguer
- Permet d'ajouter facilement de la logique métier complexe
