# Scripts de mise à jour de la base de données

## Vue d'ensemble

Ce répertoire contient les scripts de mise à jour de la base de données. Ces scripts sont **idempotents**, c'est-à-dire qu'ils peuvent être exécutés plusieurs fois sans erreur.

## Script principal : `update_all.php`

Le script `update_all.php` exécute **toutes** les mises à jour nécessaires dans l'ordre approprié.

### Utilisation

#### Via navigateur (recommandé pour la première fois)
```
http://votre-site/donnees/bd/update_all.php?run_update=1
```

#### Via ligne de commande
```bash
php donnees/bd/update_all.php
```

### Fonctionnalités

- ✅ Exécute toutes les mises à jour dans l'ordre
- ✅ Affiche un résumé détaillé avec succès/erreurs
- ✅ Interface web sécurisée (demande confirmation)
- ✅ Compatible ligne de commande et navigateur

### Mises à jour incluses

1. **Tarifs différenciés** (`update_database.php`)
   - Création de la table `tarif_differencie`
   - Ajout du champ `tarif_differencie_autorise` à la table `abone`
   - Création/mise à jour des vues SQL

2. **Bornes Fontaines** (`update_database_borne_fontaine.php`)
   - Ajout du champ `type_abone` à la table `abone`
   - Création de la table `borne_fontaine`
   - Création de la table `bf_gerant`

## Scripts individuels

Si vous souhaitez exécuter une mise à jour spécifique :

### Tarifs différenciés
```bash
php donnees/bd/update_database.php
# Ou via navigateur
http://votre-site/donnees/bd/update_database.php?run_update=1
```

### Bornes Fontaines
```bash
php donnees/bd/update_database_borne_fontaine.php
# Ou via navigateur
http://votre-site/donnees/bd/update_database_borne_fontaine.php?run_update=1
```

## Sécurité

⚠️ **Important** : Avant d'exécuter les mises à jour, assurez-vous d'avoir :
- Une sauvegarde récente de votre base de données
- Testé les scripts sur un environnement de développement
- Vérifié que vous avez les droits d'administration sur la base de données

## Résolution de problèmes

### Erreur "Class 'Connexion' not found"
- Vérifiez que le fichier `donnees/connexion.php` existe
- Vérifiez les chemins d'inclusion dans le script

### Erreur de charset
- Les scripts utilisent `utf8` pour compatibilité avec les anciennes versions de MySQL
- Si vous avez MySQL 5.5.3+, vous pouvez modifier les scripts pour utiliser `utf8mb4`

### Erreur de TIMESTAMP
- Les scripts utilisent `DATETIME` au lieu de `TIMESTAMP` pour éviter les limitations
- Les dates sont gérées automatiquement par les classes PHP

## Logs

Les scripts affichent :
- ✅ Les opérations réussies
- ✗ Les erreurs rencontrées
- ⚠ Les avertissements
- Un résumé final avec le temps d'exécution

## Notes

- Tous les scripts sont idempotents : vous pouvez les exécuter plusieurs fois
- Les scripts vérifient l'existence des tables/colonnes avant de les créer
- Les données existantes sont préservées
- Les scripts sont rétrocompatibles avec les données existantes
