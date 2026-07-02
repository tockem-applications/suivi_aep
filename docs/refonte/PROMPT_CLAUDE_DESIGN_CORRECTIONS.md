# Prompt Claude Design — Corrections des maquettes (zéro régression)

> À copier-coller dans Claude Design, dans **la même session/projet** que les maquettes déjà produites
> (Tockem SPE). Objectif : **compléter et corriger** les maquettes pour qu'elles couvrent **100 % des
> fonctionnalités de l'application actuelle**, sans en perdre aucune, tout en **conservant strictement
> la charte graphique existante**.
>
> Ce prompt a été établi après comparaison des 47 maquettes livrées avec l'inventaire fonctionnel complet
> de l'application v1. Il liste précisément ce qui manque.

---

## ▼▼▼ PROMPT À COPIER ▼▼▼

Tu as déjà produit les maquettes de **Tockem SPE** (application de gestion d'adduction en eau potable, AEP).
Le style est validé : **ne change rien** au design system existant (police Inter + JetBrains Mono pour les
chiffres, couleur primaire turquoise #0E7490, cartes blanches bord #E2E8F0 rayon 12px, badges de statut,
menu latéral 260px, barre supérieure avec sélecteur d'AEP + recherche + statut licence, fil d'Ariane, thème
clair/sombre, FCFA, dates JJ/MM/AAAA). Toutes les nouvelles pages doivent réutiliser **exactement** ces mêmes
composants, le même menu latéral et la même barre supérieure que les écrans existants.

Ta mission : **compléter les maquettes** pour couvrir toutes les fonctionnalités de l'application, **sans
aucune régression**. Voici précisément ce qui manque ou doit être corrigé.

### A. Modules entièrement absents — À CRÉER

1. **Interventions** (nouvel écran `Interventions`) — module de suivi des interventions techniques sur le réseau.
   - Liste des interventions avec colonnes : titre, type, localisation, statut (Planifiée / En cours / Terminée / Annulée),
     dates prévues et réelles, coût estimé, coût réel.
   - Cartes KPI en haut : interventions en cours, planifiées, terminées ce mois, coût total du mois.
   - Filtres : statut, type, période. Bouton « Nouvelle intervention ».
   - Écran/drawer de détail d'une intervention : informations générales + **affectation de ressources humaines**
     (avec rôle, heures prévues/réelles, coût) et **ressources matérielles** (quantité prévue/réelle, coût),
     changement de statut.

2. **Ressources** (nouvel écran `Ressources`) — avec deux onglets :
   - **Ressources humaines** : liste (nom, fonction, compétences, téléphone, statut disponible/occupé/indisponible,
     coût horaire, actif/inactif). Bouton « Ajouter une personne ».
   - **Ressources matérielles** : liste (libellé, catégorie, référence, quantité totale/disponible, unité,
     coût unitaire, statut disponible/occupé/panne/hors service, actif). Bouton « Ajouter un matériel ».

3. **Fiche borne fontaine** (nouvel écran `Fiche borne fontaine`) — **déjà liée depuis la liste des bornes
   fontaines mais l'écran n'existe pas**. À créer sur le modèle de la fiche abonné :
   - En-tête : code borne, localisation, numéro de borne, statut (Active/Inactive).
   - Cartes KPI : consommation mensuelle, montant facturé, gérant actuel.
   - Section **Gérants** avec **historique** : tableau (nom, téléphone, pièce d'identité + type CNI/Passeport,
     date de début, date de fin, actif/terminé). Actions : ajouter un gérant, modifier, **terminer le mandat**,
     supprimer. Le gérant actif est mis en évidence.
   - Section facturation/consommation de la borne.
   - Action « Convertir en abonné BP » (voir règle métier plus bas).

### B. Menu latéral — À CORRIGER

- Ajouter une section (ou compléter la section existante) donnant accès à **Interventions** et **Ressources**.
  Suggestion : créer un groupe **« Exploitation »** entre « Finances » et « Administration » contenant
  *Interventions* et *Ressources*. Répercuter ce menu identique sur **tous** les écrans.

### C. Fiche abonné — onglets à concevoir (seul « Aperçu » existe aujourd'hui)

Concevoir le **contenu de chaque onglet** de la fiche abonné (garder la même barre d'onglets) :

1. **Branchement** : détails du branchement (compteur lié n° + diamètre, date de branchement, réseau, rang,
   coordonnées GPS latitude/longitude, tarif différencié autorisé oui/non). Actions : modifier le branchement,
   changer/associer un compteur.
2. **Index / relevés** : historique des index de l'abonné (mois, ancien index, nouvel index, consommation),
   avec possibilité d'éditer un index (modal), et bouton d'historique du compteur.
3. **Factures** : liste des factures de l'abonné (mois, consommation, montant, statut payé/impayé/partiel),
   lien vers l'aperçu de facture.
4. **Recouvrement** : saisie d'un versement pour l'abonné (montant, date), solde/impayé courant, historique des paiements.
5. **Pénalités** : pénalités appliquées à l'abonné, application/annulation, montant.
6. **Factures manuelles** : liste des factures manuelles ajoutées à l'abonné, avec **ajout** et **suppression**
   d'une facture manuelle (libellé, montant).

### D. Formulaires de création/édition manquants — À CRÉER (mêmes drawers/modales que « Formulaire AEP »)

Actuellement seul « Formulaire AEP » existe. Créer les formulaires suivants (drawer latéral ou modale, style identique) :

1. **Formulaire abonné (BP)** : nom, numéro de téléphone, **numéro de compte anticipation**, réseau (select),
   **rang**, **type d'abonné BP/BF**, compteur associé (n° + option création), **tarif différencié autorisé** (interrupteur),
   **état actif/inactif**, coordonnées GPS.
2. **Formulaire réseau / sous-réseau** : nom, abréviation, réseau parent (pour la hiérarchie), date de création, description.
3. **Formulaire compteur** : numéro de compteur, type (production/distribution/réservoir pour un compteur réseau),
   dernier index, GPS, description. + **modal « Historique des index »** d'un compteur.
4. **Formulaire borne fontaine** : numéro de borne, localisation, description, réseau.
5. **Formulaire gérant de borne fontaine** : nom, téléphone, numéro et type de pièce d'identité, date de début, notes.
6. **Formulaire tarif (constante réseau)** : prix du m³, entretien compteur, TVA %, date, activation.
7. **Formulaire palier de tarif différencié** : consommation min, consommation max, prix du m³, entretien, TVA.
8. **Formulaire flux financier (entrée/sortie)** : date, mois, libellé, montant, type (recette/charge),
   catégorie (select), description.
9. **Formulaire catégorie de flux** : nom, type (recette/charge), **code budgétaire**, **activité associée**
   (vente d'eau / branchements / autre), actif, ordre.
10. **Formulaire redevance** : libellé, **base de calcul** (vente d'eau / branchements), **type de calcul**
    (pourcentage / montant fixe par m³), pourcentage OU montant par m³, mois de début, description, actif.
11. **Formulaire versement de redevance** + **écran de détail des versements** d'une redevance par mois (avec export CSV).
12. **Modal de saisie de versement** (recouvrement rapide) réutilisable.

### E. Actions et champs métier manquants sur des écrans existants — À COMPLÉTER

1. **Mois de facturation** : ajouter les actions par ligne : **Activer le mois**, **Marquer comme mois de base**,
   **Supprimer**, et l'édition des trois dates (date de relevé, date de dépôt, date de facturation). Distinguer
   visuellement le mois actif et le mois de base (badges déjà présents, ajouter les boutons d'action).
2. **Relevés d'index** : ajouter un bouton **« Exporter (JSON) »** à côté de « Importer depuis mobile »
   (l'application permet import ET export des relevés pour la saisie mobile). Garder la détection des valeurs aberrantes.
3. **Recouvrement** : compléter les filtres pour refléter tous les statuts de l'application :
   **Solvable, Insolvable, Paiement partiel, Anticipation, En règle, Pas en règle**. Conserver la saisie inline et l'export CSV par intervalle.
4. **Aperçu facture** : prévoir **les deux modèles** — **Fokoué** ET **Nkongzem** (le sélecteur existe déjà,
   montrer un aperçu distinct pour chaque modèle, car la mise en page diffère selon la commune).
5. **Liste des abonnés** : ajouter au filtre le **type (BP/BF)** et l'**état actif/inactif**, et une colonne
   consommation moyenne ; conserver export CSV et filtres réseau/impayés.
6. **Détail réseau** : ajouter les actions **Ajouter un compteur** (production/distribution/réservoir) et l'accès à
   l'**historique des index** d'un compteur du réseau.
7. **Redevances** : afficher explicitement, par redevance, la **base de calcul** et le **type de calcul**
   (déjà partiellement présent) et rendre cohérent avec le formulaire redevance ci-dessus.

### F. Règles métier à respecter dans les écrans (pour éviter toute perte de fonctionnalité)

- **Multi-AEP** : tout se fait dans le contexte de l'AEP sélectionné (barre supérieure). Les écrans d'administration
  et de synthèse peuvent être multi-AEP.
- **Conversion abonné ↔ borne fontaine** : un abonné peut être de type BP ou BF ; prévoir l'action de conversion
  (depuis la fiche abonné et/ou la fiche borne fontaine).
- **Tarif différencié** : appliqué selon des paliers de consommation, et seulement si l'abonné y est autorisé
  (interrupteur « tarif différencié autorisé »).
- **Calcul de facture** : consommation × prix du m³ + entretien compteur + TVA + impayés antérieurs + pénalités
  (déjà bien reflété dans l'aperçu de facture — le conserver).
- **Rôles** : Administrateur, Comptable, Recouvreur, Releveur, Visiteur. L'affichage s'adapte au rôle (masquer les
  actions non permises). La matrice de permissions (écran Rôles) doit lister **toutes** les ressources, y compris
  les nouvelles : Interventions, Ressources, Bornes fontaines, Gérants, Redevances, Flux, Tarifs, Sauvegarde,
  Licence, Mise à jour, Utilisateurs, Clés, Journal d'audit.

### G. Contraintes de cohérence

- Réutiliser **le même menu latéral complet** (avec la nouvelle section Exploitation) sur **toutes** les pages,
  y compris les nouvelles.
- Réutiliser la **même barre supérieure**, le **même fil d'Ariane**, les **mêmes composants** (cartes KPI, tableaux
  filtrables triables paginés, drawers de formulaire, badges, toasts, états vides, confirmations destructives).
- Respecter le **thème clair et sombre** et l'**accessibilité** (contrastes AA, navigation clavier).
- Colonnes de montants alignées à droite, séparateur de milliers, « FCFA ».
- Chaque nouvel écran doit présenter ses **états** : chargement (skeleton), vide, erreur.

Livre les **nouveaux écrans** (Interventions, Ressources, Fiche borne fontaine, tous les formulaires listés en D),
les **onglets complétés** de la fiche abonné (section C), et les **écrans existants mis à jour** (section E),
en conservant strictement la charte. Mets à jour le menu latéral partout (section B).

## ▲▲▲ FIN DU PROMPT ▲▲▲

---

## Récapitulatif des régressions corrigées par ce prompt

| # | Élément manquant dans les maquettes | Présent dans l'app v1 |
|---|--------------------------------------|-----------------------|
| 1 | Module **Interventions** (+ affectation RH/RM) | `interventions_page.php`, `intervention_t.php` |
| 2 | Module **Ressources** (humaines + matérielles) | `ressources_page.php`, `ressource_humaine_t.php`, `ressource_materielle_t.php` |
| 3 | **Fiche borne fontaine** + gestion des **gérants** (historique, fin de mandat) | `info_bf_page.php`, `bf_gerant.php` |
| 4 | **Onglets** de la fiche abonné (Branchement, Index, Factures, Recouvrement, Pénalités, Factures manuelles) | `info_abone_page.php` |
| 5 | **Formulaires** abonné, réseau, compteur, BF, gérant, tarif, palier différencié, flux, catégorie, redevance, versement | `fomulaire.php`, `*_t.php` |
| 6 | **Modal historique des index** d'un compteur | `compteur_component.php` |
| 7 | Actions **Mois de facturation** (activer, mois de base, supprimer, dates) | `mois_facturation_t.php`, `recouvrement_page.php` |
| 8 | **Export JSON** des relevés (saisie mobile) | `?page=download_index` |
| 9 | Filtres complets **Recouvrement** (solvable/insolvable/partiel/anticipation/en règle/pas en règle) | `facture_component.php`, `recouvrement_page_v2.php` |
| 10 | Aperçu facture **modèle Nkongzem** (en plus de Fokoué) | `facture_t.php` (`creerFactureNkongzem`) |
| 11 | Champs métier abonné (compte anticipation, rang, type BP/BF, GPS, conversion) | `Abones.php`, `abone_t.php` |
| 12 | Entrée menu **Interventions/Ressources** | menu `header.php` |

Les autres écrans (dashboard, listes, finances, administration, licence, sauvegarde, mise à jour, paramètres,
authentification) sont bien couverts par les maquettes existantes et ne nécessitent que la mise à jour du menu latéral.
