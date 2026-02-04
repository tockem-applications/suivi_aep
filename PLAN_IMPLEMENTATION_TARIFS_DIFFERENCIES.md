# Plan d'Implémentation des Tarifs Différenciés - Version 2

## Objectif

Implémenter les tarifs différenciés de manière rétrocompatible, en garantissant que :

- Les anciennes factures continuent de fonctionner avec les tarifs de base
- Le système fonctionne avec les données existantes
- Les nouvelles factures peuvent utiliser les tarifs différenciés si configuré

## Architecture

### Principe de Rétrocompatibilité

1. **Par défaut, tous les abonnés utilisent le tarif de base** (comportement actuel)
2. **Les tarifs différenciés sont optionnels** : ils ne s'appliquent que si :
   - Un tarif différencié existe pour la constante_reseau
   - La consommation de l'index est dans l'intervalle du tarif différencié
   - L'abonné a `tarif_differencie_autorise = 1` (par défaut à 1 pour compatibilité)
3. **Les anciennes factures** : continuent d'utiliser le tarif de base car elles sont déjà calculées

## Étapes d'Implémentation

### Phase 1 : Structure de Base de Données (Rétrocompatible)

#### 1.1 Créer la table `tarif_differencie`

- Table optionnelle : n'affecte pas le fonctionnement actuel
- Relation avec `constante_reseau` via clé étrangère
- Contraintes pour éviter les chevauchements d'intervalles

#### 1.2 Ajouter le champ `tarif_differencie_autorise` à `abone`

- Valeur par défaut : `1` (autorisé par défaut pour compatibilité)
- Permet de désactiver les tarifs différenciés par abonné

### Phase 2 : Vue Intermédiaire (Résolution des Tarifs)

#### 2.1 Créer `vue_indexes_tarifs_resolved`

- **Garantit une seule ligne par index**
- Résout automatiquement le tarif à appliquer :
  - Si un tarif différencié match ET est autorisé → tarif différencié
  - Sinon → tarif de base
- **Rétrocompatible** : les anciens index sans tarif différencié utilisent le tarif de base

### Phase 3 : Modification de `vue_abones_facturation`

#### 3.1 Modifier la vue pour utiliser `vue_indexes_tarifs_resolved`

- Remplace les références directes à `constante_reseau` par la vue résolue
- **Garantit la rétrocompatibilité** :
  - Les colonnes existantes restent identiques
  - Les calculs utilisent les prix résolus (base ou différencié)
  - Les anciennes factures continuent de fonctionner

### Phase 4 : Interface Utilisateur

#### 4.1 Gestion des tarifs différenciés

- Page de détail du tarif : afficher et gérer les tarifs différenciés
- Validation : empêcher les modifications si des mois de facturation existent

#### 4.2 Configuration par abonné

- Interface pour modifier `tarif_differencie_autorise` par abonné
- Affichage dans la page d'information de l'abonné

### Phase 5 : Tests et Validation

#### 5.1 Tests de rétrocompatibilité

- Vérifier que les anciennes factures affichent les mêmes montants
- Vérifier que les nouvelles factures peuvent utiliser les tarifs différenciés
- Vérifier que le système fonctionne même sans tarifs différenciés configurés

## Structure des Fichiers

```
donnees/
├── bd/
│   ├── create_tarif_differencie.sql          # Création de la table
│   ├── create_vue_indexes_tarifs_resolved.sql # Vue de résolution
│   ├── create_vue_abones_facturation.sql      # Vue modifiée
│   └── update_database.php                    # Script de mise à jour idempotent
├── tarif_differencie.php                     # Classe PHP pour gérer les tarifs différenciés
└── Abones.php                                # Ajout du champ tarif_differencie_autorise

traitement/
├── tarif_t.php                               # Gestion des tarifs différenciés
└── abone_t.php                               # Configuration par abonné

presentation/
└── detail_tarif_page.php                     # Interface de gestion
```

## Garanties de Rétrocompatibilité

1. **Valeurs par défaut** :

   - `tarif_differencie_autorise = 1` (autorisé par défaut)
   - Si aucun tarif différencié n'existe → utilisation du tarif de base

2. **Vue `vue_abones_facturation`** :

   - Même structure de colonnes
   - Mêmes noms de colonnes
   - Mêmes calculs (utilisent les prix résolus)

3. **Anciennes factures** :

   - Continuent d'utiliser le tarif de base (pas de tarif différencié configuré à l'époque)
   - Les montants restent identiques

4. **Nouvelles factures** :
   - Utilisent automatiquement les tarifs différenciés si configurés
   - Sinon, utilisent le tarif de base (comportement par défaut)

## Ordre d'Exécution

1. ✅ Créer la table `tarif_differencie` (idempotent)
2. ✅ Ajouter le champ `tarif_differencie_autorise` à `abone` (idempotent)
3. ✅ Créer la vue `vue_indexes_tarifs_resolved` (idempotent)
4. ✅ Modifier `vue_abones_facturation` pour utiliser la vue résolue
5. ✅ Créer l'interface utilisateur
6. ✅ Tester la rétrocompatibilité

## Notes Importantes

- **Tous les scripts doivent être idempotents** : peuvent être exécutés plusieurs fois sans erreur
- **Pas de migration de données** : les anciennes factures restent inchangées
- **Compatibilité descendante** : le système fonctionne même si les tarifs différenciés ne sont pas configurés
