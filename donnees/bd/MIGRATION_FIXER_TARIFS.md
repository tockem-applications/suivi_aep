# Migration : Figer les tarifs différenciés dans les factures

## Problème résolu

Avant cette migration, changer `tarif_differencie_autorise` d'un abonné modifiait toutes ses factures passées car les tarifs étaient calculés dynamiquement.

## Solution

Le tarif différencié utilisé est maintenant stocké dans la colonne `id_tarif_differencie` de la table `indexes` au moment de la création de la facture, garantissant que les factures passées ne changent jamais.

## Étapes d'exécution

### Étape 1 : Exécuter la migration via update_all.php

Accédez à la page de mise à jour via :
- **Via navigateur** : `http://votre-site/donnees/bd/update_all.php?run_update=1`
- **Via ligne de commande** : `php donnees/bd/update_all.php`

Cela exécutera automatiquement :
1. Ajout de la colonne `id_tarif_differencie` dans `indexes`
2. Peuplement de `id_tarif_differencie` pour les index existants
3. Mise à jour de la vue `vue_indexes_tarifs_resolved`

### Étape 2 : Mettre à jour la vue (si nécessaire)

Si la vue n'a pas été automatiquement mise à jour, exécutez :

```sql
-- Exécuter le contenu de create_vue_indexes_tarifs_resolved.sql
-- via votre outil SQL préféré (phpMyAdmin, MySQL Workbench, etc.)
```

Ou via ligne de commande :
```bash
mysql -u root -p votre_base < donnees/bd/create_vue_indexes_tarifs_resolved.sql
```

### Étape 3 : Vérification

Pour vérifier que la migration a réussi :

1. **Vérifier que la colonne existe** :
```sql
DESCRIBE indexes;
-- Vous devriez voir `id_tarif_differencie` dans la liste
```

2. **Vérifier que les index existants ont été mis à jour** :
```sql
SELECT COUNT(*) as total, 
       COUNT(id_tarif_differencie) as avec_tarif_diff,
       COUNT(*) - COUNT(id_tarif_differencie) as sans_tarif_diff
FROM indexes;
```

3. **Tester avec une nouvelle facture** :
   - Créer une nouvelle facture pour un abonné
   - Vérifier que `id_tarif_differencie` est automatiquement rempli dans `indexes`

4. **Tester la non-modification des factures passées** :
   - Changer `tarif_differencie_autorise` d'un abonné de 1 à 0
   - Vérifier que les factures passées de cet abonné ne changent pas

## Fichiers modifiés

- `donnees/bd/update_database_fixer_tarifs.php` : Script de migration
- `donnees/bd/create_vue_indexes_tarifs_resolved.sql` : Vue mise à jour
- `donnees/indexes.php` : Ajout des méthodes de calcul du tarif
- `donnees/facture.php` : Calcul automatique de `id_tarif_differencie` lors de la création

## Notes importantes

- Les factures existantes sont mises à jour automatiquement lors de la migration
- Les nouvelles factures stockent automatiquement leur tarif différencié
- Si un index n'a pas de facture associée (compteurs réseau/AEP), `id_tarif_differencie` reste NULL
- La vue utilise le tarif stocké s'il existe, sinon calcule dynamiquement (rétrocompatibilité)