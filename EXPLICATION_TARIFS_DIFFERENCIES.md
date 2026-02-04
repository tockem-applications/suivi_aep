# Explication de l'Implémentation des Tarifs Différenciés

## Vue d'ensemble

Le système de tarifs différenciés permet d'appliquer des tarifs différents selon la consommation d'eau d'un abonné. Par exemple :
- **0-10 m³** : 500 FCFA/m³
- **10-20 m³** : 600 FCFA/m³  
- **20+ m³** : 700 FCFA/m³

## Architecture en 3 couches

### 1. Base de données

#### Table `tarif_differencie`
```sql
CREATE TABLE tarif_differencie (
  id INT PRIMARY KEY,
  id_constante_reseau INT,  -- Lien vers le tarif de base
  prix_metre_cube_eau INT,
  prix_entretient_compteur INT,
  prix_tva DECIMAL,
  min_consommation DECIMAL,  -- Ex: 0
  max_consommation DECIMAL,  -- Ex: 10 (NULL = infini)
  date_creation DATE,
  description TEXT
)
```

**Caractéristiques :**
- Un tarif différencié est lié à un tarif de base (`constante_reseau`)
- Les intervalles sont définis comme `[min; max[` (max non inclus)
- Exemple : `[0; 10[` signifie de 0 inclus à 10 exclus

#### Colonne `tarif_differencie_autorise` dans `abone`
- `0` = L'abonné utilise toujours le tarif de base
- `1` = L'abonné peut utiliser les tarifs différenciés si sa consommation correspond

### 2. Vues SQL (le cœur du système)

#### Vue `vue_indexes_tarifs_resolved`
**Rôle :** Pour chaque index (lecture de compteur), détermine quel tarif appliquer.

**Logique :**
1. Calcule la consommation : `nouvel_index - ancien_index`
2. Cherche un tarif différencié qui correspond :
   - La consommation doit être `>= min_consommation`
   - La consommation doit être `< max_consommation` (ou `max_consommation IS NULL`)
3. Si plusieurs tarifs matchent, prend le plus spécifique (min_consommation le plus élevé)
4. Si aucun tarif différencié ne correspond, utilise le tarif de base

**Exemple :**
```sql
-- Consommation = 15 m³
-- Tarifs différenciés :
--   [0; 10[  → 500 FCFA
--   [10; 20[ → 600 FCFA
--   [20; ∞[  → 700 FCFA

-- Résultat : 600 FCFA (car 15 est dans [10; 20[)
```

**Code clé :**
```sql
LEFT JOIN tarif_differencie td ON (
    td.id_constante_reseau = cr.id
    AND consommation >= td.min_consommation
    AND (td.max_consommation IS NULL OR consommation < td.max_consommation)
    -- Prendre le plus spécifique
    AND NOT EXISTS (
        SELECT 1 FROM tarif_differencie td2
        WHERE td2.min_consommation > td.min_consommation
        AND consommation >= td2.min_consommation
        AND (td2.max_consommation IS NULL OR consommation < td2.max_consommation)
    )
)
```

#### Vue `vue_abones_facturation`
**Rôle :** Utilise `vue_indexes_tarifs_resolved` et applique la logique `tarif_differencie_autorise`.

**Logique :**
```sql
CASE 
    WHEN a.tarif_differencie_autorise = 0 THEN cr.prix_metre_cube_eau  -- Tarif de base
    ELSE vit.prix_metre_cube_eau  -- Tarif résolu (différencié ou base)
END AS prix_metre_cube_eau
```

**Résultat :**
- Si `tarif_differencie_autorise = 0` → toujours le tarif de base
- Si `tarif_differencie_autorise = 1` → tarif différencié si disponible, sinon base

### 3. Code PHP

#### Classe `TarifDifferencie`
**Méthodes principales :**

1. **`getTarifsByConstante($id_constante_reseau)`**
   - Récupère tous les tarifs différenciés d'un tarif de base
   - Triés par `min_consommation` croissant

2. **`checkOverlap($id_constante_reseau, $min, $max, $exclude_id)`**
   - Vérifie si un nouvel intervalle chevauche un intervalle existant
   - Logique : deux intervalles `[a; b[` et `[c; d[` se chevauchent si `a < d ET c < b`
   - Exemple : `[5; 10[` et `[10; 15[` ne se chevauchent PAS (10 n'est pas inclus dans le premier)

3. **`hasMoisFacturation($id_constante_reseau)`**
   - Vérifie si le tarif de base a déjà des mois de facturation associés
   - Empêche la modification des tarifs différenciés après facturation (intégrité des données)

## Flux de données

### Lors de la création d'une facture :

```
1. Index créé (ancien_index, nouvel_index)
   ↓
2. Consommation calculée = nouvel_index - ancien_index
   ↓
3. vue_indexes_tarifs_resolved :
   - Cherche tarif différencié correspondant
   - Si trouvé → utilise tarif différencié
   - Sinon → utilise tarif de base
   ↓
4. vue_abones_facturation :
   - Vérifie tarif_differencie_autorise
   - Si 0 → force tarif de base
   - Si 1 → utilise le tarif résolu (étape 3)
   ↓
5. Calculs financiers avec le bon tarif
```

### Exemple concret :

**Configuration :**
- Tarif de base : 500 FCFA/m³
- Tarifs différenciés :
  - [0; 10[ : 500 FCFA/m³
  - [10; 20[ : 600 FCFA/m³
  - [20; ∞[ : 700 FCFA/m³

**Scénario 1 : Abonné avec consommation = 15 m³**
- `tarif_differencie_autorise = 1`
- Résultat : 600 FCFA/m³ (car 15 ∈ [10; 20[)

**Scénario 2 : Même abonné, mais `tarif_differencie_autorise = 0`**
- Résultat : 500 FCFA/m³ (tarif de base forcé)

**Scénario 3 : Consommation = 5 m³**
- Résultat : 500 FCFA/m³ (car 5 ∈ [0; 10[)

## Rétrocompatibilité

### Pourquoi ça fonctionne avec les anciennes données ?

1. **Anciennes factures :**
   - N'ont pas de tarifs différenciés configurés
   - `vue_indexes_tarifs_resolved` retourne le tarif de base
   - Fonctionnent exactement comme avant

2. **Structure des colonnes :**
   - `vue_abones_facturation` garde la même structure
   - Les applications existantes continuent de fonctionner

3. **Valeur par défaut :**
   - `tarif_differencie_autorise = 1` par défaut
   - Les nouveaux abonnés peuvent utiliser les tarifs différenciés
   - Les anciens abonnés peuvent être mis à jour individuellement

## Sécurité et intégrité

### Protection contre les modifications après facturation

```php
if (TarifDifferencie::hasMoisFacturation($id_constante_reseau)) {
    throw new Exception('Impossible : ce tarif a déjà des mois de facturation');
}
```

**Pourquoi ?**
- Les factures historiques doivent garder leurs tarifs
- Modifier les tarifs différenciés après facturation casserait l'historique

### Vérification des chevauchements

```php
if (TarifDifferencie::checkOverlap($id_constante, $min, $max)) {
    throw new Exception('Les intervalles se chevauchent');
}
```

**Pourquoi ?**
- Un intervalle de consommation ne peut correspondre qu'à un seul tarif
- Évite les ambiguïtés dans le calcul

## Interface utilisateur

### Gestion des tarifs différenciés
- **Page de détail d'un tarif** (`detail_tarif_page.php`)
  - Liste des tarifs différenciés
  - Ajout/suppression (si pas de mois de facturation)
  - Affichage des intervalles et prix

### Configuration par abonné
- **Page info abonné** (`abone_t.php`)
  - Toggle `tarif_differencie_autorise`
  - Modification via modal

### Affichage dans les listes
- **Page de gestion des tarifs** (`tarif_page.php`)
  - Badge indiquant le nombre de tarifs différenciés
  - Menu contextuel pour les actions

## Points techniques importants

### 1. Performance
- Les vues SQL pré-calculent les tarifs
- Pas de sous-requêtes corrélées dans les requêtes principales
- Index sur `id_constante_reseau` pour les jointures rapides

### 2. Logique d'intervalles
- Format `[min; max[` (max non inclus)
- `max_consommation = NULL` signifie "infini"
- Exemple : `[20; NULL[` = "20 et plus"

### 3. Sélection du tarif le plus spécifique
- Si plusieurs tarifs matchent, on prend celui avec `min_consommation` le plus élevé
- Exemple : consommation = 15, tarifs [0; 20[ et [10; 20[ → on prend [10; 20[

## Avantages de cette architecture

1. **Rétrocompatible** : Les anciennes factures continuent de fonctionner
2. **Flexible** : Configuration par abonné et par tarif de base
3. **Performant** : Vues SQL optimisées
4. **Sécurisé** : Protection contre les modifications après facturation
5. **Maintenable** : Code organisé en couches claires

## Résumé en une phrase

Le système utilise des vues SQL en cascade pour résoudre automatiquement le tarif à appliquer (différencié ou base) selon la consommation et la configuration de l'abonné, tout en préservant la compatibilité avec les données existantes.
