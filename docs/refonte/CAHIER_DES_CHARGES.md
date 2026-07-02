# Cahier des charges — Refonte de Tockem SPE (v2)

> Document destiné à être exécuté par des agents IA de développement.
> Il décrit **quoi** construire, **avec quelles technologies**, sous **quelles contraintes**
> et selon **quels critères d'acceptation**. Chaque exigence est numérotée (`EX-xxx`)
> pour être traçable.

- **Produit** : Tockem SPE — Suivi technique et financier de l'adduction en eau potable (AEP)
- **Version cible** : 2.0 (réécriture complète sur nouvelle base)
- **Public** : régies communales de l'eau, comités de gestion AEP (Cameroun et pays similaires)
- **Langue de l'application** : Français (architecture i18n prévue pour extension future)
- **Statut** : spécification initiale validée (choix technologiques arrêtés)

---

## 1. Contexte et objectifs

### 1.1 Situation actuelle (v1)

L'application v1 est un monolithe **PHP 5.3 / Apache 2.2 / MySQL 5.1** (stack en fin de vie),
architecture 3 couches (`presentation/`, `traitement/`, `donnees/`), routage par query string,
UI Bootstrap + jQuery + Chart.js/CanvasJS, rendu côté serveur.

Elle est **fonctionnellement riche** mais souffre de :

- **Failles de sécurité critiques** : RCE par upload non validé, absence de `.htaccess`
  (dumps SQL et `.env` servis en HTTP), injections SQL par concaténation, XSS réfléchies,
  identifiants BD en dur, CSRF quasi absent, contrôle d'accès contournable, hash mot de passe faible.
- **Dette technique** : PHP 5.3 (fin de support 2014), code dupliqué (Recouvrement V1/V2,
  facturation legacy), permissions non centralisées, debug résiduel en production.
- **Distribution du code source en clair** chez le client (lisible et modifiable).

### 1.2 Objectifs de la v2

| Réf | Objectif |
|-----|----------|
| OBJ-1 | Reprendre **l'intégralité des fonctionnalités** de la v1, sans les doublons, avec les mêmes parcours métier. |
| OBJ-2 | Éliminer par conception **toutes** les classes de failles identifiées et formaliser des protocoles de vérification. |
| OBJ-3 | Livrer une application **installable en ligne (serveur) ou en local (poste)**, avec des mises à jour simples réalisables par l'utilisateur. |
| OBJ-4 | Distribuer un **code nativement illisible** chez le client (aucun code source livré). |
| OBJ-5 | Garantir que **tous les utilisateurs possèdent une licence** valide, via des mécanismes **hors-ligne et en ligne**. |
| OBJ-6 | Interfaces **modernes, intuitives, ergonomiques**, respectant une **charte graphique unique paramétrable** (couleurs, nom, logo, coordonnées — white-label). |
| OBJ-7 | Migrer **la totalité des données** existantes de la base MySQL v1. |

---

## 2. Pile technologique imposée

### 2.1 Vue d'ensemble

```
┌───────────────────────────────────────────────────────────────┐
│  Client (navigateur)                                            │
│  React + TypeScript (SPA)  —  minifié, aucune logique métier    │
│  Tailwind CSS + shadcn/ui  —  design system paramétrable        │
└───────────────────────────────────────────────────────────────┘
                    │ HTTPS / JSON REST (+ CSRF)
                    ▼
┌───────────────────────────────────────────────────────────────┐
│  Backend Go (binaire unique auto-contenu)                       │
│  - Serveur HTTP (assets SPA embarqués via go:embed)             │
│  - API REST + RBAC + validation + audit                         │
│  - Moteur métier (facturation, recouvrement, finances…)         │
│  - Moteur de licence (offline signé + heartbeat online)         │
│  - Migrations automatiques au démarrage                         │
└───────────────────────────────────────────────────────────────┘
                    │ ORM (même code)
                    ▼
┌───────────────────────────────────────────────────────────────┐
│  Base de données                                                │
│  - Local  : SQLite (embarquée, zéro installation)               │
│  - En ligne : PostgreSQL                                        │
└───────────────────────────────────────────────────────────────┘
```

### 2.2 Backend — Go

- **EX-100** : Le backend est écrit en **Go** (version stable la plus récente au démarrage du projet, ≥ 1.22).
- **EX-101** : L'application est compilée en **binaire unique auto-contenu** par OS/arch cible
  (Windows amd64, Linux amd64, et Linux arm64 optionnel).
- **EX-102** : Le frontend (build React) est **embarqué dans le binaire** via `embed.FS` (`go:embed`).
  Aucun fichier de code source (Go ou logique métier) n'est présent sur la machine client.
- **EX-103** : Framework HTTP recommandé : `chi` ou `echo` (léger, middlewares standard). Justifier le choix.
- **EX-104** : Accès données via un ORM/couche unique supportant **SQLite et PostgreSQL** avec le
  même code applicatif. Recommandé : **GORM** ou **sqlc + migrations SQL** (choisir et justifier ;
  sqlc préféré pour la sécurité des requêtes typées).
- **EX-105** : Migrations de schéma versionnées et **appliquées automatiquement au démarrage**
  (outil recommandé : `golang-migrate` ou `goose`), idempotentes, avec verrou anti-concurrence.
- **EX-106** : Configuration par variables d'environnement + fichier `config` optionnel (jamais de secret en dur).
- **EX-107** : Logs structurés (JSON) via `slog`, niveaux configurables, **sans fuite de secrets ni de requêtes SQL** au client.

### 2.3 Frontend — React

- **EX-120** : SPA **React + TypeScript** (Vite comme bundler).
- **EX-121** : **Tailwind CSS** + bibliothèque de composants **shadcn/ui** (Radix sous-jacent) pour l'accessibilité.
- **EX-122** : Gestion d'état serveur via **TanStack Query** ; formulaires via **React Hook Form + Zod**.
- **EX-123** : Routage via **React Router** ou TanStack Router.
- **EX-124** : Graphiques via **Recharts** (ou ECharts si besoins avancés) — une seule librairie de charts.
- **EX-125** : Tables de données avec tri, filtres, pagination, export CSV/PDF via **TanStack Table**.
- **EX-126** : Aucune logique métier sensible ni secret dans le bundle front ; le bundle est **minifié**
  (l'illisibilité forte repose sur le binaire Go, pas sur le JS).
- **EX-127** : Le thème (couleurs, logo, nom) est piloté par des **design tokens CSS** injectés au runtime
  depuis la configuration serveur (voir §7).

### 2.4 Base de données

- **EX-140** : **SQLite** pour le déploiement local (fichier unique, zéro installation, WAL activé).
- **EX-141** : **PostgreSQL** (≥ 15) pour le déploiement en ligne.
- **EX-142** : Le schéma et les migrations sont **compatibles avec les deux moteurs** (éviter le SQL spécifique ;
  abstraire via l'ORM/sqlc + dialectes).
- **EX-143** : Le choix du moteur se fait par configuration (`DB_DRIVER=sqlite|postgres`), sans changement de code.
- **EX-144** : Sauvegardes : export/restore natif pour chaque moteur (copie de fichier + `VACUUM INTO` pour SQLite,
  `pg_dump`/`pg_restore` pour PostgreSQL), déclenchables depuis l'UI d'administration.

---

## 3. Exigences de sécurité (OBJ-2)

Toutes les failles v1 doivent être **éliminées par conception**. Chaque exigence ci-dessous
correspond à une classe de vulnérabilité constatée.

### 3.1 Injection SQL

- **EX-200** : **100 % des requêtes** utilisent des paramètres liés (requêtes préparées / ORM / sqlc).
  Aucune concaténation de valeur utilisateur dans du SQL. Interdiction de toute API type `query(string)` libre.
- **EX-201** : Les identifiants dynamiques (colonne de tri, table) passent par une **liste blanche** stricte côté serveur.

### 3.2 XSS et injection de contenu

- **EX-210** : Le rendu React échappe par défaut ; interdiction de `dangerouslySetInnerHTML` sauf contenu assaini (DOMPurify).
- **EX-211** : En-tête **Content-Security-Policy** strict (pas de `unsafe-inline` en prod), plus `X-Content-Type-Options`,
  `X-Frame-Options`/`frame-ancestors`, `Referrer-Policy`, `HSTS` (en ligne).
- **EX-212** : Aucune donnée utilisateur reflétée dans une page sans encodage contextuel côté serveur (pages d'erreur incluses).

### 3.3 Authentification et sessions

- **EX-220** : Mots de passe hachés avec **argon2id** (ou bcrypt coût ≥ 12). Jamais SHA-256 nu.
- **EX-221** : Politique de mot de passe (longueur min 12, vérification contre listes courantes), **verrouillage après N échecs**
  et back-off, journalisation des tentatives.
- **EX-222** : Sessions : soit **cookies de session signés/chiffrés** (`HttpOnly`, `Secure`, `SameSite=Strict`), soit
  **JWT court (access) + refresh token** stocké en cookie `HttpOnly`. Choisir cookies de session côté serveur par défaut.
- **EX-223** : **Régénération de l'identifiant de session** à la connexion et au changement de privilège ;
  **destruction complète** à la déconnexion ; expiration d'inactivité configurable.
- **EX-224** : Support optionnel du **2FA (TOTP)** pour les rôles administrateurs.

### 3.4 CSRF, CORS, en-têtes

- **EX-230** : Protection **CSRF systématique** sur toutes les requêtes mutantes (token double-submit ou en-tête + cookie).
- **EX-231** : CORS restrictif (origines whitelistées). En mode local mono-origine, désactivé.
- **EX-232** : Toutes les réponses API sont `application/json` ; pas de HTML d'erreur exploitable.

### 3.5 Contrôle d'accès (RBAC)

- **EX-240** : Le contrôle d'accès est **centralisé côté serveur** via un middleware appliqué à **chaque** route.
  L'UI ne fait que masquer/afficher ; elle n'est **jamais** la source de vérité.
- **EX-241** : Modèle **RBAC** : rôles → permissions (ressource + action : `read`, `create`, `update`, `delete`, `execute`).
  Reprendre les rôles v1 (Administrateur, Releveur, Comptable, Recouvreur, Visiteur) mais avec permissions par ressource, pas par « page ».
- **EX-242** : Vérification de permission **par ressource ET par AEP** (isolation multi-AEP stricte : un utilisateur ne voit
  que les AEP autorisés).
- **EX-243** : Aucune route sensible accessible sans passer par le middleware (pas de « pages » directement atteignables hors routeur).

### 3.6 Upload de fichiers

- **EX-250** : Uploads validés par **liste blanche d'extensions ET de types MIME réels** (sniffing), taille max, nom **régénéré**.
- **EX-251** : Fichiers stockés **hors de la racine servie**, sans droit d'exécution ; accès uniquement via endpoint contrôlé.
- **EX-252** : Antivirus/validation de contenu pour les imports (CSV/SQL) ; parsing en lecture seule, jamais d'inclusion/exécution.

### 3.7 Secrets et exposition

- **EX-260** : **Aucun secret en dur** dans le code. Tout via variables d'environnement / fichier `.env` non versionné / coffre.
- **EX-261** : Aucun fichier sensible servi en HTTP (le binaire ne sert que la SPA + l'API ; pas de listing de répertoire).
- **EX-262** : `display_errors`/stack traces désactivés en prod ; erreurs génériques au client, détails en logs serveur.
- **EX-263** : Endpoints d'administration (backup, mise à jour, migration) protégés par rôle admin **et** re-authentification/2FA.

### 3.8 Journalisation et audit

- **EX-270** : **Journal d'audit** horodaté (utilisateur, action, ressource, avant/après pour les données sensibles),
  consultable par les admins, non modifiable par l'UI.
- **EX-271** : Journalisation des évènements de sécurité (connexions, échecs, changements de rôle, imports, restaurations).

### 3.9 Protocoles de vérification (livrables de sécurité)

- **EX-280** : Fournir une **checklist de sécurité par endpoint** (auth requise, permission, validation entrée, CSRF, audit) —
  document `docs/refonte/SECURITE_PROTOCOLES.md` généré/maintenu.
- **EX-281** : Tests automatisés de sécurité : tests unitaires RBAC, tests d'intégration CSRF/authz, fuzzing des entrées critiques.
- **EX-282** : Pipeline CI avec **SAST** (`gosec`, `govulncheck`), audit des dépendances (`nancy`/`osv-scanner`, `npm audit`),
  et lint (`golangci-lint`, `eslint`).
- **EX-283** : Validation d'entrée **au niveau frontière** via schémas (Zod côté front, structs validées côté Go) —
  ne jamais faire confiance au client.

---

## 4. Déploiement, installation et mises à jour (OBJ-3)

### 4.1 Modes de déploiement

- **EX-300** : **Mode local (poste)** : un **exécutable auto-contenu** (installeur Windows `.exe`/MSI, binaire Linux) qui
  démarre le serveur en local, ouvre le navigateur sur `http://localhost:PORT`, base **SQLite** dans un dossier de données utilisateur.
- **EX-301** : **Mode en ligne (serveur)** : **image Docker** (et/ou binaire Linux + service systemd), base **PostgreSQL**,
  derrière un reverse proxy TLS (fournir un exemple Caddy/Nginx + `docker-compose.yml`).
- **EX-302** : Un **même code** produit les deux modes ; le mode est déterminé par configuration.

### 4.2 Installation

- **EX-310** : Installation locale **sans dépendance externe** (pas de serveur web ni de SGBD à installer).
  L'exécutable embarque tout (serveur HTTP, SPA, moteur SQLite).
- **EX-311** : Premier démarrage : assistant d'initialisation (création admin, import licence, paramétrage charte, création 1er AEP).
- **EX-312** : Documentation d'installation pas-à-pas pour chaque mode (`docs/refonte/INSTALLATION.md`).

### 4.3 Mises à jour

- **EX-320** : **Mise à jour locale simple par l'utilisateur** : mécanisme d'**auto-update intégré** — vérification en ligne
  d'une nouvelle version, téléchargement d'un **paquet signé** (signature vérifiée avant application), remplacement du binaire,
  redémarrage automatique. Un bouton « Vérifier les mises à jour » dans l'UI d'administration.
- **EX-321** : **Migrations de base appliquées automatiquement** au redémarrage après mise à jour, avec sauvegarde préalable automatique.
- **EX-322** : Mode **maintenance** pendant la mise à jour (page dédiée), rollback possible en cas d'échec (conserver l'ancien binaire + backup).
- **EX-323** : Canal de mise à jour hors-ligne : possibilité d'appliquer un paquet signé **manuellement** (fichier fourni par l'éditeur)
  pour les sites sans Internet.
- **EX-324** : Le mécanisme d'update ne doit **jamais** exposer ou reconstituer le code source lisible.

### 4.4 Distribution du code (OBJ-4)

- **EX-330** : Seuls sont livrés au client : le **binaire compilé**, les assets embarqués (dans le binaire), la configuration, et le schéma de données.
  **Aucun fichier source** (`.go`, logique métier).
- **EX-331** : Le bundle React est **minifié** (source maps non distribuées).
- **EX-332** : Aucune fonctionnalité ne dépend de la présence du code source côté client.

---

## 5. Licence (OBJ-5)

Reprendre et renforcer le mécanisme existant (signature RSA, fichier `.lic`).

- **EX-400** : **Licence hors-ligne** : fichier `.lic` **signé cryptographiquement** (RSA/Ed25519) par l'éditeur,
  contenant : identifiant licence, client, produit, type, date de début, date d'expiration, périmètre (nb d'AEP/utilisateurs si applicable),
  et **empreinte machine** (liaison au poste/serveur pour empêcher la copie).
- **EX-401** : Vérification de signature **à chaque démarrage** et périodiquement ; l'application se bloque proprement
  (page dédiée) si la licence est absente, expirée ou invalide, avec **période de grâce** configurable.
- **EX-402** : **Licence en ligne** : heartbeat périodique optionnel vers un **serveur de licences** de l'éditeur
  (validation, révocation, renouvellement automatique), avec **tolérance hors-ligne** (fonctionne N jours sans réseau).
- **EX-403** : **Empreinte matérielle** : dérivée de caractéristiques stables (à définir : ID machine, CPU, disque),
  hachée, comparée à la licence — pour garantir 1 licence = 1 installation autorisée.
- **EX-404** : Import de licence via l'UI (upload `.lic`), affichage du statut (actif / expire bientôt / expiré),
  alerte à J-30, et rappels.
- **EX-405** : **Aucun bypass en production** (le `LICENCE_DEV_MODE` v1 est réservé au développement et forcé à `0` en release).
- **EX-406** : Le module licence ne doit pas être contournable côté client (vérification serveur, code dans le binaire).
- **EX-407** : Fournir l'outil éditeur (CLI) de **génération/signature de licences** (hors application client).

---

## 6. Charte graphique paramétrable et white-label (OBJ-6)

- **EX-500** : **Design system unique** (typographie, espacements, composants, états) appliqué à toutes les pages.
- **EX-501** : Paramètres personnalisables **sans recompilation**, via un écran d'administration :
  - **Nom de l'application** (marque, titre, favicon).
  - **Logo** (clair/sombre) et éventuellement image de connexion.
  - **Palette de couleurs** (couleur primaire, secondaire, accents) → propagée via design tokens.
  - **Coordonnées / pied de page** (éditeur, support, mentions).
  - Petits réglages : devise (FCFA par défaut), format de date, fuseau, langue.
- **EX-502** : Le thème est stocké en base (par instance) et **injecté au runtime** dans la SPA (variables CSS),
  aperçu en direct avant enregistrement.
- **EX-503** : Mode clair/sombre supporté ; respect des contrastes **WCAG AA**.
- **EX-504** : Les documents imprimés (factures, rapports) reprennent la charte (logo, couleurs, nom).

---

## 7. Périmètre fonctionnel (OBJ-1 — iso-fonctionnel modernisé)

Tous les modules de la v1 sont repris. Les **doublons sont fusionnés** (Recouvrement V1/V2 → une seule version ;
facturation legacy supprimée au profit du flux unique). Notation des rôles suggérés entre parenthèses.

### 7.1 Authentification et accès

- **EX-600** : Connexion / déconnexion sécurisées, mot de passe oublié (réinitialisation par l'admin ou par e-mail si configuré).
- **EX-601** : Inscription contrôlée par **clé d'accès** (comme v1) ; rôle par défaut restreint (Visiteur) ; validation admin.
- **EX-602** : Sélection de l'AEP de travail (contexte de session) ; un utilisateur peut être limité à certains AEP.
- **EX-603** : Page « accès refusé » claire lorsque la permission manque.

### 7.2 Structure du réseau (Admin, Comptable lecture)

- **EX-610** : **AEP** : CRUD ; attributs (libellé, dates, description, banque/compte, **modèle de facture** Fokoué/Nkongzem,
  **type de distribution** RDS/RDC) ; suppression avec sauvegarde préalable ; tableau de bord par AEP.
- **EX-611** : **Réseaux** : arborescence hiérarchique (réseau parent/enfant), CRUD, statistiques, rendement, export CSV.
- **EX-612** : **Compteurs réseau** : types production / distribution / réservoir ; historique des index.
- **EX-613** : **Abonnés (BP)** : CRUD, fiche détaillée (accueil, branchement, index, recouvrement, pénalités, factures manuelles),
  filtres (impayés, volume, réseau, état), export CSV ; lien compteur ; tarif différencié autorisé.
- **EX-614** : **Bornes fontaines (BF)** et **gérants** : CRUD BF, gestion des gérants (ajout, modification, fin de mandat, suppression),
  conversion abonné ↔ BF, statistiques de facturation.
- **EX-615** : **Branchements** (nouveaux raccordements) : suivi, montants paramétrables (côté réseau / côté opposé),
  filtres (période, quartier, réseau, statut, montant), agrégats mensuels.
- **EX-616** : **Import de données historiques** (type « Fokoue data ») : import CSV avec mois socle/actuel et tarifs, avec validation stricte.

### 7.3 Tarification (Admin, Comptable)

- **EX-620** : **Tarifs / constantes réseau** : prix m³, entretien compteur, TVA %, activation ; historique de tarifs.
- **EX-621** : **Tarifs différenciés** : paliers de consommation (min/max) avec prix par palier ; application automatique à la facturation.

### 7.4 Cycle mensuel : relevés → facturation → recouvrement

- **EX-630** : **Mois de facturation** : création, activation, **mois de base** (reprise historique), dates (relevé, dépôt, facturation).
- **EX-631** : **Relevés d'index** : saisie manuelle (ancien/nouvel index), **import** depuis application mobile (JSON), **export** JSON,
  contrôles de cohérence (index décroissant, valeurs aberrantes).
- **EX-632** : **Facturation** : génération de masse pour les abonnés actifs, **modèles Fokoué et Nkongzem**,
  calcul = `(nouvel_index − ancien_index) × prix_m³ + entretien + TVA + impayés antérieurs + pénalités`.
- **EX-633** : **Impression des factures** : génération **PDF** (remplace l'impression HTML v1), en lot et à l'unité, conforme charte.
- **EX-634** : **Recouvrement (version unique)** : saisie des versements (montant, date), filtres (solvable, insolvable,
  partiel, anticipation, en règle), totaux, **export CSV** par intervalle. Fusionne V1 et V2.
- **EX-635** : **Pénalités** : montant par défaut paramétrable, application/annulation unitaire et en lot, évaluation/score, KPIs.
- **EX-636** : **Impayés** : suivi du montant restant par abonné/mois, liste des insolvables.

### 7.5 Finances (Comptable, Admin)

- **EX-640** : **Flux financiers** (entrées/sorties manuelles) : CRUD, **catégories** paramétrables (type recette/charge, code budgétaire,
  activité, ordre d'affichage), totaux, impression.
- **EX-641** : **Redevances** : CRUD, base de calcul (vente d'eau / branchements), type (pourcentage / montant fixe par m³),
  estimation, **versements** liés aux mois, export CSV détaillé.
- **EX-642** : **Compte d'exploitation** : grille recettes/dépenses mensuelles par année.
- **EX-643** : **Synthèse compte d'exploitation** multi-AEP.
- **EX-644** : **Analyse financière** : graphiques et indicateurs par catégorie.
- **EX-645** : **Configuration du compte-rendu** : libellés et codes des lignes budgétaires, activités (recouvrements, branchements, redevances).
- **EX-646** : Agrégation automatique des recouvrements et branchements comme entrées dans les flux (comme v1).

### 7.6 Interventions et ressources (Admin)

- **EX-650** : **Interventions** : CRUD, changement de statut, affectation de ressources humaines/matérielles.
- **EX-651** : **Ressources humaines** (coût horaire) et **matérielles** (quantité, coût unitaire) : CRUD, liaisons N-N avec interventions.

### 7.7 Administration système (Admin)

- **EX-660** : **Rôles et permissions** : gestion des rôles, matrice permissions par ressource/action (voir §3.5).
- **EX-661** : **Utilisateurs** : gestion, activation/désactivation, réinitialisation, affectation de rôles et d'AEP.
- **EX-662** : **Clés d'inscription** : génération/suppression.
- **EX-663** : **Licence** : import, statut, alertes (voir §5).
- **EX-664** : **Sauvegarde / restauration** de la base depuis l'UI (export/import sécurisé, réservé admin + re-auth).
- **EX-665** : **Mise à jour de l'application** depuis l'UI (voir §4.3), en remplacement du mécanisme Git v1.
- **EX-666** : **À propos** : version, licence, éditeur.
- **EX-667** : **Journal d'audit** consultable (voir §3.8).

### 7.8 Tableau de bord et reporting (tous rôles, selon permissions)

- **EX-670** : Dashboard par AEP : KPIs (recouvrement, consommation, impayés, rendement distribution, redevances),
  période sélectionnable (3/6/12 mois), graphiques.
- **EX-671** : Tableau facturation BF/BP, statistiques par réseau, exports.
- **EX-672** : Accueil / synthèse globale multi-AEP pour les profils autorisés.

### 7.9 Paramétrage transverse

- **EX-680** : Écran de paramétrage : charte (voir §6), devise/format, montants par défaut (branchements, pénalités),
  modèles de facture, paramètres de licence et de mise à jour.

---

## 8. Migration des données (OBJ-7)

- **EX-700** : Fournir un **outil de migration** (commande du binaire ou utilitaire dédié) qui lit la base **MySQL v1**
  et charge la base v2 (SQLite ou PostgreSQL).
- **EX-701** : **Migration complète** : AEP, réseaux, abonnés, compteurs, index, mois de facturation, factures, impayés,
  versements, pénalités, tarifs et tarifs différenciés, redevances, flux financiers, catégories, branchements,
  bornes fontaines, gérants, interventions, ressources, utilisateurs, rôles, clés, logs.
- **EX-702** : **Mapping documenté** entre le schéma v1 et le schéma v2 (`docs/refonte/MIGRATION_DONNEES.md`),
  y compris la vue métier centrale `vue_abones_facturation` (recalculée ou matérialisée).
- **EX-703** : Migration **idempotente et vérifiable** : rapport de migration (comptes d'enregistrements source/cible,
  écarts, erreurs), exécution à blanc (dry-run).
- **EX-704** : **Remise à plat de la sécurité** : mots de passe v1 (SHA-256) ré-encodés au premier login (les utilisateurs
  définissent un nouveau mot de passe) ; pas de reprise des secrets en dur.
- **EX-705** : Validation post-migration : totaux financiers (recouvrements, impayés) cohérents entre v1 et v2.

---

## 9. Exigences non fonctionnelles

- **EX-800 (Performance)** : Réponse API < 300 ms pour les opérations courantes ; pagination serveur pour les grandes listes ;
  génération de factures en lot performante (des centaines d'abonnés).
- **EX-801 (Ergonomie)** : Navigation latérale claire, recherche globale, actions contextuelles, retours d'action (toasts),
  états de chargement, confirmation des actions destructrices, formulaires validés en direct.
- **EX-802 (Accessibilité)** : WCAG AA, navigation clavier, libellés ARIA, contrastes.
- **EX-803 (Responsive)** : Utilisable sur desktop et tablette (les relevés terrain peuvent être mobiles).
- **EX-804 (i18n)** : Chaînes externalisées (français par défaut), architecture prête pour d'autres langues.
- **EX-805 (Fiabilité)** : Sauvegarde automatique avant migration/mise à jour ; transactions sur les opérations financières.
- **EX-806 (Observabilité)** : Health-check, métriques de base, logs structurés.
- **EX-807 (Tests)** : Couverture unitaire du moteur métier (facturation, pénalités, redevances, compte d'exploitation) ;
  tests d'intégration API ; tests e2e des parcours critiques (Playwright).
- **EX-808 (Qualité)** : CI complète (build, lint, tests, SAST, audit dépendances) ; formatage automatique.
- **EX-809 (Documentation)** : Doc technique (architecture, déploiement, migration, sécurité) + doc utilisateur (reprendre `docs/utilisateur/`).

---

## 10. Architecture logique proposée (indicative)

```
suivi-reseau-v2/
├── cmd/
│   ├── server/         # point d'entrée du binaire (serveur + SPA embarquée)
│   ├── migrate/        # outil de migration MySQL v1 → v2
│   └── licence/        # CLI éditeur de génération/signature de licences
├── internal/
│   ├── domain/         # entités et règles métier (facturation, recouvrement, finances…)
│   ├── service/        # cas d'usage (orchestration)
│   ├── store/          # persistance (SQLite/PostgreSQL, migrations)
│   ├── http/           # routeur, handlers, middlewares (auth, RBAC, CSRF, audit)
│   ├── auth/           # sessions, mots de passe, RBAC, 2FA
│   ├── licence/        # vérification offline + heartbeat online
│   ├── theme/          # configuration white-label
│   └── update/         # auto-update signé
├── web/                # frontend React (source, non distribué au client)
│   └── dist/           # build embarqué via go:embed
├── migrations/         # SQL versionné (compatible SQLite + PostgreSQL)
├── deploy/             # Dockerfile, docker-compose, systemd, reverse proxy
└── docs/               # architecture, sécurité, migration, installation
```

Découpage métier suggéré (bounded contexts) : **Structure**, **Facturation & Recouvrement**,
**Finances**, **Administration & Sécurité**, **Licence**, **Reporting**.

---

## 11. Feuille de route (phases)

| Phase | Contenu | Sortie |
|-------|---------|--------|
| **P0 — Socle** | Squelette Go + React embarqué, config dual DB, migrations, CI, sécurité de base (auth, RBAC, CSRF, headers), thème paramétrable, licence offline | Binaire qui démarre, login, thème |
| **P1 — Structure** | AEP, réseaux, abonnés, compteurs, BF/gérants, tarifs, branchements | CRUD structure complet |
| **P2 — Cycle mensuel** | Mois de facturation, relevés (+ import mobile), facturation (modèles Fokoué/Nkongzem), PDF, recouvrement unifié, pénalités, impayés | Cycle métier complet |
| **P3 — Finances** | Flux, catégories, redevances/versements, compte d'exploitation, synthèse, analyse, config compte-rendu | Reporting financier |
| **P4 — Admin & compléments** | Rôles/utilisateurs/clés, sauvegarde/restauration, audit, licence en ligne (heartbeat), auto-update, interventions/ressources | Administration complète |
| **P5 — Migration & durcissement** | Outil de migration MySQL v1, validation des totaux, tests e2e, audit sécurité final, packaging (installeur + Docker) | Release 2.0 |

---

## 12. Critères d'acceptation globaux

- **AC-1** : Aucune des failles de l'audit v1 n'est reproductible (rejeu des scénarios d'exploitation → échec).
- **AC-2** : L'application démarre en local **sans installer** de serveur web ni de SGBD, et en ligne via Docker.
- **AC-3** : Une mise à jour peut être réalisée par un utilisateur non technique depuis l'UI, avec migration et rollback.
- **AC-4** : Aucun fichier de code source métier n'est présent sur la machine client.
- **AC-5** : L'application refuse de fonctionner sans licence valide (offline et online), avec période de grâce documentée.
- **AC-6** : Le nom, le logo et les couleurs sont modifiables sans recompilation et propagés à l'UI et aux PDF.
- **AC-7** : Tous les modules v1 sont présents et les parcours métier (Parcours A à D ci-dessous) fonctionnent.
- **AC-8** : La migration importe 100 % des données v1 avec un rapport de contrôle des totaux.
- **AC-9** : CI verte (build, tests, SAST, audit dépendances) et couverture du moteur métier ≥ objectif défini.

### Parcours métier de référence (à valider en recette)

- **Parcours A — Nouveau mois** : sélectionner AEP → créer/activer le mois → saisir relevés → facturer → imprimer PDF → recouvrer → tableau de bord.
- **Parcours B — Nouvel abonné** : créer réseau si besoin → créer abonné + compteur → tarif → premier relevé au mois suivant.
- **Parcours C — Reporting** : contrôler recouvrements/pénalités → saisir flux/redevances → générer compte d'exploitation + synthèse.
- **Parcours D — Mise en service** : installer → importer licence → créer admin → créer AEP → première sauvegarde.

---

## 13. Annexe — Correspondance failles v1 → exigences v2

| Faille v1 | Exigence(s) v2 |
|-----------|----------------|
| Upload arbitraire → RCE (`manager.php::uploadImage`) | EX-250, EX-251, EX-252 |
| Dumps SQL / `.env` servis en HTTP (pas de `.htaccess`) | EX-261, EX-330, EX-102 |
| Injections SQL (`Abones.php`, `mois_facturation.php`) | EX-200, EX-201 |
| XSS (`message.php`, `index.php`) | EX-210, EX-211, EX-212 |
| Identifiants BD en dur (`connexion.php`) | EX-260 |
| CSRF absent | EX-230 |
| Contrôle d'accès contournable | EX-240 à EX-243 |
| Hash SHA-256, pas de régénération de session | EX-220, EX-223 |
| Fuite d'erreurs/debug | EX-262, EX-107 |
| Open redirect via Referer | EX-212, EX-231 |
| Bypass licence en dev par défaut | EX-405 |

---

*Document de référence pour la génération assistée par IA. Toute déviation technique doit être justifiée
et validée. Les choix « à justifier » (framework HTTP, ORM/sqlc) sont laissés à l'implémentation dans le
respect des exigences ci-dessus.*
