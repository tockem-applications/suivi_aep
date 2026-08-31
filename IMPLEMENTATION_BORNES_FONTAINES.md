# Implémentation des Bornes Fontaines (BF)

## Vue d'ensemble

Ce système permet de différencier les abonnés en deux catégories :
- **BP (Branchement Privé)** : Les abonnés classiques
- **BF (Borne Fontaine)** : Les bornes fontaines gérées par des gérants

## Installation

### 1. Mise à jour de la base de données

Exécutez le script de mise à jour pour créer les tables nécessaires :

```bash
# Via navigateur
http://votre-site/donnees/bd/update_database_borne_fontaine.php?run_update=1

# Ou via ligne de commande
php donnees/bd/update_database_borne_fontaine.php
```

Ce script va :
- Ajouter le champ `type_abone` à la table `abone` (BP par défaut)
- Créer la table `borne_fontaine`
- Créer la table `bf_gerant` pour l'historique des gérants

### 2. Structure des tables

#### Table `abone` (modifiée)
- Nouveau champ : `type_abone` VARCHAR(2) - 'BP' ou 'BF'

#### Table `borne_fontaine`
- `id` : ID de la BF
- `id_abone` : Référence à l'abonné (unique)
- `numero_borne` : Numéro d'identification
- `localisation` : Localisation de la borne
- `description` : Description

#### Table `bf_gerant`
- `id` : ID du gérant
- `id_borne_fontaine` : Référence à la BF
- `nom_gerant` : Nom du gérant
- `numero_telephone` : Téléphone
- `numero_piece_identite` : Numéro de pièce d'identité
- `type_piece_identite` : Type (CNI, Passeport, etc.)
- `date_debut` : Date de début de gestion
- `date_fin` : Date de fin (NULL si actif)
- `est_actif` : Indique si c'est le gérant actuel
- `notes` : Notes additionnelles

## Utilisation

### 1. Convertir un abonné en Borne Fontaine

1. Aller sur la page "Bornes Fontaines" dans le menu Structure
2. Cliquer sur "Convertir un abonné en BF"
3. Sélectionner un abonné (BP uniquement)
4. Remplir les informations de la BF (numéro, localisation, description)
5. Valider

**Note** : Un même abonné ne peut pas être converti deux fois. Si l'abonné est déjà une BF, le système détectera automatiquement.

### 2. Gérer les gérants d'une BF

1. Aller sur la page de détails d'une BF
2. Cliquer sur l'onglet "Gérants"
3. Cliquer sur "Ajouter un gérant"
4. Remplir les informations :
   - Nom du gérant (obligatoire)
   - Téléphone
   - Type et numéro de pièce d'identité
   - Date de début
   - Notes
5. Valider

**Fonctionnalités** :
- Un seul gérant actif par BF à la fois
- Lors de l'ajout d'un nouveau gérant actif, l'ancien est automatiquement désactivé
- Historique complet conservé
- Possibilité de terminer une période de gestion

### 3. Terminer une période de gestion

1. Dans l'onglet "Gérants" d'une BF
2. Cliquer sur "Terminer" pour le gérant actif
3. Spécifier la date de fin
4. Valider

## Pages disponibles

### Page principale des BF
- **URL** : `?page=borne_fontaine`
- **Fonctionnalités** :
  - Liste de toutes les BF
  - Filtres (réseau, nom, numéro de borne)
  - Tri par colonnes
  - Conversion d'abonné en BF

### Page de détails d'une BF
- **URL** : `?page=info_bf&id=[ID_BF]`
- **Onglets** :
  - **Détails** : Informations de la BF et de l'abonné associé
  - **Gérants** : Historique complet des gérants avec possibilité d'ajouter/terminer
  - **Facturation** : Informations de facturation (même format que les abonnés)

## Classes PHP

### `BorneFontaine`
- `convertirAboneEnBF()` : Convertit un abonné en BF
- `getAllBF()` : Récupère toutes les BF d'un AEP
- `getBFById()` : Récupère une BF par son ID
- `getGerantActif()` : Récupère le gérant actif
- `getHistoriqueGerants()` : Récupère l'historique complet

### `BFGerant`
- `ajouterGerant()` : Ajoute un nouveau gérant (désactive automatiquement l'ancien si actif)
- `terminerPeriodeGerant()` : Termine une période de gestion
- `getGerantsByBF()` : Récupère tous les gérants d'une BF
- `getGerantById()` : Récupère un gérant par son ID
- `gerantExiste()` : Vérifie si un gérant existe déjà

## Points importants

1. **Un même agent peut être associé à plusieurs périodes** : Le système ne duplique pas les agents, il crée simplement de nouveaux enregistrements avec des dates différentes.

2. **Historique complet** : Tous les gérants sont conservés dans l'historique, même après la fin de leur période.

3. **Un seul gérant actif** : Une BF ne peut avoir qu'un seul gérant actif à la fois. L'ajout d'un nouveau gérant actif désactive automatiquement l'ancien.

4. **Rétrocompatibilité** : Les abonnés existants sont automatiquement marqués comme BP. Le système continue de fonctionner normalement.

5. **Facturation** : Les BF utilisent le même système de facturation que les BP, mais sont identifiables par leur type.

## Fichiers créés/modifiés

### Nouveaux fichiers
- `donnees/bd/create_borne_fontaine_tables.sql` : Script SQL de création des tables
- `donnees/bd/update_database_borne_fontaine.php` : Script de mise à jour idempotent
- `donnees/borne_fontaine.php` : Classe pour gérer les BF
- `donnees/bf_gerant.php` : Classe pour gérer les gérants
- `presentation/borne_fontaine_page.php` : Page principale des BF
- `presentation/info_bf_page.php` : Page de détails d'une BF
- `traitement/borne_fontaine_t.php` : Traitement des actions sur les BF

### Fichiers modifiés
- `presentation/liste.php` : Ajout des routes pour les pages BF
- `presentation/header.php` : Ajout du lien dans le menu

## Prochaines étapes

1. Exécuter le script de mise à jour de la base de données
2. Tester la conversion d'un abonné en BF
3. Ajouter des gérants à une BF
4. Vérifier l'historique des gérants
