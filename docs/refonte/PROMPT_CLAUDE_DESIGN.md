# Prompt Claude Design — Interfaces de Tockem SPE v2

> Copiez-collez le bloc ci-dessous dans Claude Design (ou tout générateur d'interfaces) pour
> obtenir l'ensemble des écrans de la nouvelle version. Le prompt est autoportant : il décrit
> le produit, la charte, la navigation et **chaque écran** à produire.
>
> Astuce : si l'outil limite la longueur, générez par sections (§ Design system, puis chaque
> groupe d'écrans). Le §0 (contexte + charte) doit toujours être fourni en tête.

---

## ▼▼▼ PROMPT À COPIER ▼▼▼

Tu es un designer produit senior spécialisé en applications de gestion métier (SaaS B2B).
Conçois **l'intégralité des interfaces** d'une application web nommée **Tockem SPE**, un logiciel
de **suivi technique et financier de l'adduction en eau potable (AEP)** pour des régies communales
de l'eau (contexte Afrique francophone, Cameroun). Toute l'interface est en **français**.

Livre des maquettes haute-fidélité, modernes, intuitives et ergonomiques, prêtes à être intégrées
en **React + Tailwind CSS + shadcn/ui**. Application **web responsive** (desktop prioritaire, tablette
supportée pour le terrain). Devise **FCFA**, format de date `JJ/MM/AAAA`.

### 0. Principes de design et charte graphique

- **Style** : interface d'application de gestion claire et professionnelle, épurée, dense mais lisible,
  inspirée des bons dashboards SaaS (type Linear/Stripe pour la propreté, adaptée à de la donnée financière).
- **Layout général** : barre de navigation **latérale gauche** (rétractable) + barre supérieure
  (sélecteur d'AEP courant, recherche globale, cloche de notifications, menu utilisateur, statut licence).
  Contenu principal à droite. Fil d'Ariane sous la barre supérieure.
- **Charte paramétrable (white-label)** : le nom de l'app, le logo et la **couleur primaire** sont
  configurables. Conçois avec des **design tokens** : `--color-primary` (par défaut un bleu eau/turquoise
  professionnel, ex. #0E7490), `--color-secondary`, couleurs sémantiques (succès vert, alerte orange,
  danger rouge, info bleu), neutres (gris). Montre que changer la couleur primaire reteinte l'UI.
- **Thème clair et sombre**, contrastes **WCAG AA**.
- **Composants** : cartes KPI, tableaux de données triables/filtrables avec pagination, formulaires en
  panneaux latéraux (drawers) ou modales, onglets, badges de statut, toasts de confirmation, états de
  chargement (skeletons), états vides illustrés, confirmations pour actions destructrices.
- **Typographie** : sans-serif lisible (Inter ou équivalent), hiérarchie claire.
- **Graphiques** : style épuré (courbes, barres, jauges), cohérents avec la palette.
- **Iconographie** : jeu d'icônes cohérent (style Lucide).
- **Documents imprimables** (factures, rapports) : gabarits sobres reprenant logo et couleurs.

### 1. Vocabulaire métier (à respecter dans les libellés)

- **AEP** : système/organisme d'adduction en eau potable (l'app est multi-AEP, on travaille dans un AEP à la fois).
- **Réseau** : portion hiérarchique du réseau d'eau (peut avoir des sous-réseaux).
- **Abonné BP** : branchement privé (client particulier avec compteur).
- **Borne fontaine (BF)** : point d'eau public géré par un **gérant**.
- **Compteur** : de production, de distribution ou de réservoir (réseau), ou d'abonné.
- **Relevé / Index** : lecture du compteur (ancien index → nouvel index → consommation en m³).
- **Mois de facturation** : période mensuelle ; un « mois de base » sert de point de départ historique.
- **Facture** : consommation × prix du m³ + entretien compteur + TVA + impayés + pénalités.
- **Recouvrement** : encaissement des paiements (versements) des factures.
- **Pénalité** : montant ajouté en cas de retard.
- **Impayé** : reste à payer d'une facture.
- **Redevance** : reversement à un tiers, calculé sur la vente d'eau ou les branchements.
- **Flux financier** : entrée (recette) ou sortie (charge) financière.
- **Compte d'exploitation** : grille annuelle recettes/dépenses.
- **Tarif différencié** : prix par palier de consommation.

### 2. Rôles (adapter l'affichage selon le rôle)

Administrateur (tout), Comptable (finances + lecture structure), Recouvreur (recouvrement, paiements),
Releveur (saisie des relevés), Visiteur (lecture limitée). L'UI masque ce qui n'est pas permis.

### 3. Navigation (menu latéral, regroupé)

- **Tableau de bord** (accueil de l'AEP)
- **Structure** : Réseaux · Abonnés (BP) · Bornes fontaines · AEP · Import de données
- **Facturation** : Relevés · Mois de facturation · Factures · Recouvrement · Pénalités · Impayés · Statistiques réseau
- **Finances** : Entrées/Sorties · Tarifs · Tarifs différenciés · Branchements · Redevances · Compte d'exploitation · Synthèse · Analyse financière · Config. compte-rendu · Catégories de flux
- **Administration** : Utilisateurs · Rôles & permissions · Clés d'inscription · Sauvegarde & restauration · Licence · Mise à jour · Journal d'audit · Paramètres (charte) · À propos

### 4. Écrans à produire

Pour **chaque écran**, fournis : version desktop (et adaptation tablette si pertinent), états
(chargement, vide, erreur), et les interactions clés (filtres, tri, actions). Respecte une cohérence
totale entre écrans.

**A. Authentification & démarrage**
1. **Connexion** (logo, champs, mot de passe oublié, mention licence en pied).
2. **Inscription** (avec champ « clé d'accès »).
3. **Assistant de premier démarrage** (étapes : créer admin → importer licence → paramétrer la charte → créer le 1er AEP).
4. **Sélection de l'AEP** (liste/cartes des AEP autorisés, recherche, bouton « Tout fermer »).
5. **Accès refusé** (page claire quand permission manquante).
6. **Écran licence expirée / invalide** (blocage, message, bouton importer licence).

**B. Tableau de bord**
7. **Dashboard AEP** : cartes KPI (recouvrement du mois, consommation totale, impayés, rendement de distribution,
   redevances), sélecteur de période (3/6/12 mois), graphiques (consommation mensuelle, taux de recouvrement,
   évolution des impayés), tableau comparatif BF vs BP.

**C. Structure**
8. **Liste des réseaux** (arborescence parent/enfant, actions CRUD, stats de rendement, export CSV).
9. **Détail d'un réseau** (compteurs production/distribution/réservoir, statistiques, historique).
10. **Liste des abonnés (BP)** (tableau avec filtres : réseau, état, impayés, volume ; recherche ; export CSV ; création).
11. **Fiche abonné** (onglets : Aperçu, Branchement, Index/relevés, Factures, Recouvrement, Pénalités, Factures manuelles).
12. **Liste des bornes fontaines** (CRUD, statistiques).
13. **Fiche borne fontaine** (informations, gérants avec ajout/modification/fin de mandat, statistiques).
14. **Liste des AEP** (CRUD, sélection, tableau de bord par AEP).
15. **Formulaire AEP** (drawer/modale : libellé, dates, banque/compte, modèle de facture Fokoué/Nkongzem, type de distribution RDS/RDC).
16. **Import de données** (assistant d'import CSV avec aperçu, mapping, mois socle/actuel, tarifs, rapport de validation).

**D. Facturation & recouvrement**
17. **Relevés d'index** (sélection du mois, tableau de saisie par compteur : ancien index, nouvel index, consommation calculée,
    validation en direct des valeurs aberrantes ; import depuis mobile ; export).
18. **Mois de facturation** (liste des mois, création, activation, marquage « mois de base », dates ; graphiques d'historique).
19. **Assistant/action de facturation** (sélection du mois, date de dépôt, aperçu du nombre de factures à générer, lancement, résultat).
20. **Liste des factures d'un mois** (tableau, impression PDF en lot et à l'unité, filtres).
21. **Aperçu / gabarit de facture PDF** (modèles Fokoué et Nkongzem, charte, logo).
22. **Recouvrement** (écran unifié : liste des factures/abonnés, filtres solvable/insolvable/partiel/anticipation/en règle,
    saisie rapide des versements — montant + date, totaux, export CSV par intervalle).
23. **Pénalités** (vue d'ensemble KPIs, tableau des abonnés, application/annulation unitaire et en lot, montant paramétrable).
24. **Impayés** (liste des insolvables, montant restant, relances).
25. **Statistiques réseau** (tableaux + graphiques par réseau, export CSV).

**E. Finances**
26. **Entrées / Sorties** (flux financiers : tableau, création, catégories, totaux recettes/charges, impression).
27. **Catégories de flux** (liste ordonnable par glisser-déposer, type recette/charge, code budgétaire, activité).
28. **Tarifs** (constantes réseau : prix m³, entretien compteur, TVA %, activation, historique).
29. **Tarifs différenciés** (paliers de consommation min/max avec prix, aperçu du barème).
30. **Branchements** (suivi des raccordements, montants côté réseau/opposé paramétrables, filtres période/quartier/réseau/statut, agrégats mensuels).
31. **Redevances** (liste, création, base de calcul vente d'eau/branchements, type % ou montant fixe/m³, estimation).
32. **Versements de redevances** (liste par mois, détail, export CSV).
33. **Compte d'exploitation** (grille annuelle : lignes recettes/dépenses × mois, totaux).
34. **Synthèse compte d'exploitation** (multi-AEP, comparaison, période).
35. **Analyse financière** (graphiques par catégorie, indicateurs).
36. **Configuration du compte-rendu** (libellés et codes des lignes budgétaires, activités).

**F. Interventions & ressources**
37. **Interventions** (liste, création, statut, affectation de ressources RH/matériel).
38. **Ressources** (RH avec coût horaire ; matériel avec quantité et coût unitaire).

**G. Administration**
39. **Utilisateurs** (liste, création, activation/désactivation, affectation rôles + AEP, réinitialisation mot de passe).
40. **Rôles & permissions** (liste des rôles ; **matrice permissions** ressource × action read/create/update/delete/execute).
41. **Clés d'inscription** (génération, liste, suppression).
42. **Sauvegarde & restauration** (liste des sauvegardes, création, restauration avec double confirmation, réservé admin).
43. **Licence** (statut actif/expire bientôt/expiré avec badge, import de fichier `.lic`, informations client/éditeur, dates).
44. **Mise à jour** (version actuelle, bouton « Vérifier les mises à jour », progression, notes de version, mode maintenance).
45. **Journal d'audit** (tableau filtrable : date, utilisateur, action, ressource, détails).
46. **Paramètres / charte** (nom de l'app, logo clair/sombre, sélecteur de couleur primaire avec **aperçu en direct**,
    coordonnées/pied de page, devise, format de date, montants par défaut — pénalité, branchements ; bascule clair/sombre).
47. **À propos** (version, licence, éditeur, mentions).

### 5. Exigences transverses de design

- Cohérence stricte : mêmes composants, mêmes espacements, mêmes patterns de tableaux et de formulaires partout.
- Actions destructrices toujours confirmées ; feedback systématique (toasts succès/erreur).
- Tableaux : tri, filtres, recherche, pagination, export ; colonnes de montants alignées à droite avec séparateur de milliers et « FCFA ».
- Formulaires : validation en direct, messages d'erreur clairs, boutons d'action bien hiérarchisés (primaire/secondaire).
- États vides pédagogiques (illustration + explication + action).
- Responsive : le menu latéral se replie en tablette ; les tableaux deviennent défilables ou en cartes.
- Accessibilité : focus visibles, navigation clavier, contrastes AA.
- Montre au moins un écran en **thème sombre** et un exemple de **charte reteintée** (couleur primaire différente)
  pour prouver le caractère paramétrable.

Livre : un **design system** (couleurs, typo, composants, tokens) puis tous les écrans listés, organisés par section,
avec une brève annotation des interactions clés pour chacun.

## ▲▲▲ FIN DU PROMPT ▲▲▲

---

## Notes d'utilisation

- Ce prompt est aligné sur le `CAHIER_DES_CHARGES.md` (mêmes modules, mêmes rôles, même charte paramétrable).
- Si vous voulez cadrer davantage le rendu, ajoutez en tête : la **couleur primaire exacte**, le **nom réel**
  de l'app à afficher, et joignez le **logo** si vous en avez un.
- Pour un premier jet plus court, demandez d'abord le **§0 Design system + écrans A, B, D** (parcours cœur métier),
  puis itérez sur les autres sections.
