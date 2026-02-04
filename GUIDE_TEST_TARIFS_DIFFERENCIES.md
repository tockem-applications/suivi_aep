# Guide de Test des Tarifs Différenciés

## ✅ Tests Automatiques Réussis

Les tests automatiques ont été exécutés avec succès :
- ✓ Structure de la base de données : OK
- ✓ Données existantes : OK
- ✓ Rétrocompatibilité : OK (1056 factures existantes fonctionnent correctement)
- ✓ Classe TarifDifferencie : OK
- ✓ Configuration des abonnés : OK
- ✓ Vues SQL : OK

## 📋 Guide de Test Manuel

### Test 1 : Créer un tarif différencié

1. **Accéder à la page des tarifs**
   - URL : `?page=tarif_aep`
   - Vérifier que la liste des tarifs s'affiche correctement

2. **Voir les détails d'un tarif**
   - Cliquer sur le bouton "👁️" (Voir détails) d'un tarif
   - Vérifier que la page de détail s'affiche (`?page=detail_tarif&id=X`)
   - Vérifier que la section "Tarifs Différenciés" est visible

3. **Ajouter un tarif différencié**
   - Cliquer sur "Ajouter un tarif différencié"
   - Remplir le formulaire :
     - **Consommation minimale** : 0 (peut être 0)
     - **Consommation maximale** : 10 (ou laisser vide pour +∞)
     - **Prix par m³** : 600 FCFA (différent du tarif de base)
     - **Entretien compteur** : (valeur par défaut du tarif de base)
     - **TVA** : (valeur par défaut du tarif de base)
   - Cliquer sur "Ajouter le tarif différencié"
   - Vérifier que le tarif différencié apparaît dans le tableau

4. **Vérifier les validations**
   - Essayer d'ajouter un tarif avec un intervalle qui chevauche → doit afficher une erreur
   - Essayer d'ajouter un tarif avec max < min → doit afficher une erreur

### Test 2 : Modifier la configuration d'un abonné

1. **Accéder à la page d'un abonné**
   - URL : `?page=info_abone&id=X`
   - Vérifier que la page s'affiche correctement

2. **Vérifier l'affichage du statut**
   - Chercher la ligne "Tarif différencié" dans le tableau
   - Vérifier que le badge affiche "Autorisé" ou "Non autorisé"
   - Vérifier que le sélecteur est présent

3. **Modifier le statut**
   - Changer la valeur dans le sélecteur (Autorisé ↔ Non autorisé)
   - Vérifier que la page se recharge automatiquement
   - Vérifier que le nouveau statut est affiché

### Test 3 : Vérifier la rétrocompatibilité

1. **Vérifier les anciennes factures**
   - Accéder à une page qui affiche les factures (ex: `?page=recouvrement`)
   - Vérifier que toutes les factures existantes s'affichent correctement
   - Vérifier que les montants sont identiques à avant

2. **Vérifier que les anciennes factures utilisent le tarif de base**
   - Les factures créées avant l'ajout des tarifs différenciés doivent utiliser le tarif de base
   - Les montants doivent être calculés avec le tarif de base uniquement

3. **Créer une nouvelle facture avec tarif différencié**
   - Créer un nouvel index avec une consommation dans l'intervalle d'un tarif différencié
   - Vérifier que la facture utilise le tarif différencié si `tarif_differencie_autorise = 1`
   - Vérifier que la facture utilise le tarif de base si `tarif_differencie_autorise = 0`

## 🔍 Points de Vérification

### Vérification de la Vue `vue_abones_facturation`

Exécuter cette requête SQL pour vérifier que la vue fonctionne :
```sql
SELECT 
    id, 
    id_abone, 
    consommation, 
    prix_metre_cube_eau, 
    prix_entretient_compteur,
    montant_conso,
    montant_total,
    type_tarif
FROM vue_abones_facturation 
LIMIT 10;
```

Vérifier que :
- Toutes les colonnes sont présentes
- Les montants sont calculés correctement
- Le type_tarif est soit 'base' soit 'differencie'

### Vérification de la Vue `vue_indexes_tarifs_resolved`

Exécuter cette requête SQL :
```sql
SELECT 
    id_indexes,
    consommation,
    prix_metre_cube_eau,
    type_tarif,
    id_tarif_differencie
FROM vue_indexes_tarifs_resolved 
LIMIT 10;
```

Vérifier que :
- Chaque index a une seule ligne (pas de doublons)
- Le type_tarif est correct
- Les prix sont corrects (base ou différencié)

## 🐛 Dépannage

### Problème : Les tarifs différenciés ne s'appliquent pas

**Vérifications :**
1. Vérifier que `tarif_differencie_autorise = 1` pour l'abonné
2. Vérifier que la consommation est dans l'intervalle du tarif différencié
3. Vérifier que le tarif différencié existe pour la constante_reseau utilisée

### Problème : Erreur lors de l'ajout d'un tarif différencié

**Vérifications :**
1. Vérifier que le tarif de base n'a pas de mois de facturation associés
2. Vérifier que les intervalles ne se chevauchent pas
3. Vérifier que min < max (ou max est NULL)

### Problème : Les anciennes factures ne s'affichent plus

**Solution :**
- Exécuter à nouveau le script de mise à jour : `php donnees/bd/update_database.php`
- Vérifier que la vue `vue_abones_facturation` existe et fonctionne

## 📊 Résultats Attendus

### Avant l'implémentation
- Toutes les factures utilisent le tarif de base
- Pas de tarifs différenciés

### Après l'implémentation
- Les anciennes factures continuent d'utiliser le tarif de base ✓
- Les nouvelles factures peuvent utiliser les tarifs différenciés si configurés ✓
- La configuration par abonné fonctionne ✓

## ✅ Checklist de Validation

- [ ] La page de détail d'un tarif s'affiche correctement
- [ ] L'ajout d'un tarif différencié fonctionne
- [ ] La suppression d'un tarif différencié fonctionne
- [ ] Les validations (chevauchement, intervalles) fonctionnent
- [ ] La configuration par abonné fonctionne
- [ ] Les anciennes factures s'affichent correctement
- [ ] Les montants des anciennes factures sont identiques
- [ ] Les nouvelles factures peuvent utiliser les tarifs différenciés
