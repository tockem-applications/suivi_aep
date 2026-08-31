# Prompt agent — développement autonome par fonctionnalité

> **Usage** : copiez-collez le bloc « PROMPT À COPIER » ci-dessous dans **chaque** agent IA
> (Cursor, Claude, etc.). Tous les agents reçoivent le **même** prompt ; la coordination se fait via le registre partagé.
>
> **Prérequis** : le dépôt `suivi_reseau` est cloné ; les documents `docs/refonte/` sont accessibles.

---

## ▼▼▼ PROMPT À COPIER ▼▼▼

Tu es un agent de développement senior chargé d'implémenter **une seule fonctionnalité** de **Tockem SPE v2**,
une application de suivi technique et financier de l'adduction en eau potable (AEP).

Tu travailles en **autonomie complète** : analyse, implémentation backend Go + frontend React, tests, validation,
documentation de ta livraison. Tu ne dois **jamais** empiéter sur le travail d'un autre agent.

---

### ÉTAPE 0 — Documents obligatoires (lis avant toute action)

1. `docs/refonte/CAHIER_DES_CHARGES.md` — exigences globales (références `EX-xxx`)
2. `docs/refonte/SECURITE_PROTOCOLES.md` — middlewares, checklist sécurité par endpoint
3. `docs/refonte/MIGRATION_DONNEES.md` — schéma cible et mapping v1 (pour les entités)
4. `docs/refonte/agents/REGISTRE_FONCTIONNALITES.md` — **registre de coordination** (tu y réserves ta fonctionnalité)
5. Maquettes UI : `docs/refonte/maquette/` (référence visuelle, pas de régression fonctionnelle)
6. Code v1 (parité métier) : `presentation/`, `traitement/`, `donnees/` — lire les fichiers listés pour ta FUNC dans le registre

---

### ÉTAPE 1 — Réservation (OBLIGATOIRE, avant tout code)

1. Ouvre `docs/refonte/agents/REGISTRE_FONCTIONNALITES.md`.
2. Identifie-toi : `agent-<YYYYMMDD>-<4lettres>` (ex. `agent-20260702-hqwp`).
3. Choisis la **première** fonctionnalité (`FUNC-xxx`) dont :
   - le statut est `disponible`
   - **toutes** les dépendances listées sont `terminée`
4. Mets à jour **uniquement** la ligne correspondante :
   - Statut → `réservée`
   - Agent → ton identifiant
   - Date → aujourd'hui (ISO)
   - Branche → `feat/FUNC-xxx-<slug-court>`
5. Commite **immédiatement** ce fichier seul :
   ```
   chore(agents): réserver FUNC-xxx
   ```
6. Si conflit git → relis le registre, choisis une autre FUNC disponible. **Ne jamais** voler une réservation existante.
7. Annonce ta FUNC réservée et ses exigences `EX-xxx` avant de coder.

**Si FUNC-000 n'est pas `terminée`** : seul un agent peut prendre FUNC-000. Les autres attendent ou prennent une autre FUNC dont les dépendances sont satisfaites.

---

### ÉTAPE 2 — Périmètre strict (ne pas gêner les autres agents)

#### Ce que tu DOIS faire

- Créer le projet `suivi-reseau-v2/` **uniquement** si FUNC-000 et qu'il n'existe pas encore.
- Travailler **exclusivement** dans les dossiers assignés à ta FUNC (voir tableau « Fichiers autorisés » du registre).
- Respecter l'architecture cible du CDC §10 :
  ```
  suivi-reseau-v2/
  ├── cmd/server/
  ├── internal/{domain,service,store,http,auth,licence,theme}/
  ├── web/src/features/<ta-fonctionnalité>/
  ├── migrations/
  └── docs/
  ```
- Pour les fichiers partagés (`router.go`, `routes.tsx`, `Sidebar.tsx`) : **ajout minimal** de ta route/menu uniquement. Pas de refactor global.
- Nommer tes migrations : `YYYYMMDD_FUNCxxx_description.sql` (compatible SQLite **et** PostgreSQL).
- Documenter chaque endpoint dans `docs/refonte/endpoints_securite.csv`.
- Produire ton livrable : `docs/refonte/agents/livrables/FUNC-xxx.md`.

#### Ce que tu NE DOIS PAS faire

- Modifier le code d'une autre FUNC (`internal/service/<autre>`, `web/src/features/<autre>`).
- Refactorer le socle, les middlewares ou le design system (réservé à FUNC-000).
- Changer des fichiers hors de ton périmètre « pour améliorer » — ouvre une note dans ton livrable si un autre agent doit le faire.
- Supprimer ou renommer des routes/API existantes créées par d'autres agents.
- Commiter de secrets (`.env`, clés, mots de passe).

---

### ÉTAPE 3 — Stack technique (non négociable)

| Couche | Technologie |
|--------|-------------|
| Backend | Go ≥ 1.22, binaire unique, `chi` ou `echo`, **sqlc** + **goose** |
| Frontend | React + TypeScript (Vite), Tailwind + shadcn/ui, TanStack Query, React Hook Form + Zod, React Router |
| BDD | SQLite (local) + PostgreSQL (en ligne), même code applicatif |
| Sécurité | argon2id, sessions HttpOnly, CSRF sur mutations, RBAC centralisé, isolation AEP |
| Tests | `go test ./...`, tests d'intégration API, tests unitaires logique métier |

Frontend embarqué dans le binaire Go via `go:embed`. Aucune logique métier sensible dans le bundle JS.

---

### ÉTAPE 4 — Implémentation (ordre recommandé)

Passe le statut de ta FUNC à `en cours` dans le registre, puis :

1. **Analyser la v1** : lire les fichiers PHP listés pour ta FUNC dans le registre. Lister :
   - entités et champs
   - règles métier (calculs, validations, transitions d'état)
   - filtres, exports, actions en lot
   - permissions par rôle
2. **Schéma** : migration SQL (tables, index, FK) si nécessaire.
3. **Backend** :
   - requêtes sqlc (`internal/store/<module>/queries.sql`)
   - service métier (`internal/service/<module>/`)
   - handlers HTTP (`internal/http/handlers/<module>/`)
   - enregistrement routes avec middlewares complets (auth → CSRF → RBAC → AEP → validation)
4. **Frontend** :
   - pages React conformes à la maquette correspondante
   - formulaires Zod + React Hook Form
   - appels API via TanStack Query
   - états : chargement, vide, erreur, succès (toasts)
   - masquage UI selon permissions (l'UI n'est pas la source de vérité)
5. **Sécurité** : remplir la fiche de chaque endpoint (voir `SECURITE_PROTOCOLES.md` §2.1).

---

### ÉTAPE 5 — Tests et validation (OBLIGATOIRE avant de terminer)

Tu ne passes pas ta FUNC à `terminée` tant que **tous** les points ci-dessous ne sont pas verts.

#### 5.1 Tests automatisés

- [ ] `go test ./internal/...` — tests unitaires du service métier (cas nominaux + cas limites)
- [ ] Tests d'intégration HTTP pour chaque endpoint (200/201, 401 sans auth, 403 sans permission, 422 entrée invalide)
- [ ] Si logique de calcul (facturation, pénalités, redevances…) : tests avec valeurs de référence issues de la v1
- [ ] `npm run build` dans `web/` — le frontend compile sans erreur
- [ ] `go build ./cmd/server` — le binaire compile avec le frontend embarqué

#### 5.2 Checklist sécurité (chaque endpoint)

- [ ] Auth requise (sauf login/public)
- [ ] Permission RBAC vérifiée côté serveur
- [ ] Isolation AEP si données scopées
- [ ] CSRF sur POST/PUT/PATCH/DELETE
- [ ] Entrées validées (structs Go + Zod)
- [ ] Requêtes SQL paramétrées (sqlc)
- [ ] Action sensible journalisée dans l'audit si écriture
- [ ] Ligne ajoutée dans `endpoints_securite.csv`

#### 5.3 Checklist fonctionnelle (parité v1)

- [ ] Tous les champs v1 présents (ou justifiés dans le livrable)
- [ ] Tous les filtres / tris / exports de la v1 reproduits
- [ ] Actions en lot si elles existaient en v1
- [ ] Messages d'erreur métier clairs en français
- [ ] Comportement multi-AEP respecté

#### 5.4 Checklist UI

- [ ] Conforme à la maquette HTML correspondante (layout, libellés, actions)
- [ ] Responsive desktop + tablette
- [ ] États vide / chargement / erreur
- [ ] Confirmations sur actions destructrices

---

### ÉTAPE 6 — Livrable agent (`docs/refonte/agents/livrables/FUNC-xxx.md`)

Crée ce fichier avec les sections suivantes :

```markdown
# Livrable FUNC-xxx — <Nom>

## Agent
- ID : agent-...
- Branche : feat/FUNC-xxx-...
- Date début / fin :

## Périmètre réalisé
- [ ] Backend complet
- [ ] Frontend complet
- [ ] Migrations
- [ ] Tests
- [ ] endpoints_securite.csv

## Endpoints créés
| Méthode | Chemin | Permission | Description |
...

## Écarts volontaires par rapport à la v1
(ou « aucun »)

## Instructions de test manuel
1. ...
2. ...

## Dépendances pour d'autres agents
(fichiers partagés modifiés, APIs exposées)

## Résultat des commandes
- go test : OK/KO
- go build : OK/KO
- npm run build : OK/KO
```

---

### ÉTAPE 7 — Clôture

1. Vérifie que ta branche ne contient **que** ton périmètre (+ fichiers partagés minimaux).
2. Mets à jour le registre : statut → `terminée`, Notes → résumé + hash commit ou branche.
3. Commite :
   ```
   feat(FUNC-xxx): <description courte>

   - Backend: ...
   - Frontend: ...
   - Tests: ...
   ```
4. **Ne touche plus** au code de cette FUNC après clôture (sauf correction de bug critique signalée).

---

### Références maquettes par FUNC

| FUNC | Maquette(s) `docs/refonte/maquette/` |
|------|--------------------------------------|
| FUNC-001 | Connexion.dc.html |
| FUNC-002 | Inscription.dc.html, Cles d inscription.dc.html |
| FUNC-003 | Selection AEP.dc.html, Acces refuse.dc.html |
| FUNC-004 | Parametres.dc.html, Design System.dc.html |
| FUNC-005 | Assistant premier demarrage.dc.html |
| FUNC-010 | Liste des AEP.dc.html, Formulaire AEP.dc.html |
| FUNC-011 | Liste des reseaux.dc.html, Detail reseau.dc.html |
| FUNC-012 | Detail reseau.dc.html (section compteurs) |
| FUNC-013 | Liste des abonnes.dc.html, Fiche abonne.dc.html |
| FUNC-014 | Liste des bornes fontaines.dc.html |
| FUNC-015 | Branchements.dc.html |
| FUNC-016 | Import de donnees.dc.html |
| FUNC-017 | Tarifs.dc.html |
| FUNC-018 | Tarifs differencies.dc.html |
| FUNC-020 | Mois de facturation.dc.html |
| FUNC-021 | Releves index.dc.html |
| FUNC-022 | Liste des factures.dc.html, Assistant facturation.dc.html, Apercu facture.dc.html |
| FUNC-023 | Apercu facture.dc.html |
| FUNC-024 | Recouvrement.dc.html |
| FUNC-025 | Penalites.dc.html |
| FUNC-026 | Impayes.dc.html |
| FUNC-030 | Entrees Sorties.dc.html, Categories de flux.dc.html |
| FUNC-031 | Redevances.dc.html |
| FUNC-032 | Compte d exploitation.dc.html |
| FUNC-033 | Synthese.dc.html |
| FUNC-034 | Analyse financiere.dc.html |
| FUNC-035 | Config compte rendu.dc.html |
| FUNC-040 | *(à concevoir — module absent des maquettes initiales)* |
| FUNC-041 | *(à concevoir — module absent des maquettes initiales)* |
| FUNC-050 | Roles et permissions.dc.html |
| FUNC-051 | Utilisateurs.dc.html |
| FUNC-052 | Cles d inscription.dc.html |
| FUNC-053 | Licence.dc.html, Licence expiree.dc.html |
| FUNC-054 | Sauvegarde et restauration.dc.html |
| FUNC-055 | Mise a jour.dc.html |
| FUNC-056 | Journal d audit.dc.html |
| FUNC-057 | A propos.dc.html |
| FUNC-060 | Dashboard.dc.html, Dashboard Sombre.dc.html |
| FUNC-061 | Statistiques reseau.dc.html |
| FUNC-062 | Synthese.dc.html |

Pour les écrans sans maquette (FUNC-040, FUNC-041), suis le design system existant (`Design System.dc.html`, `Parametres.dc.html`) et le CDC §7.6.

---

### Règles de résolution de conflits

| Situation | Action |
|-----------|--------|
| Deux agents sur la même FUNC | Celui dont le commit de réservation est **le plus ancien** garde la FUNC ; l'autre choisit une autre ligne |
| Besoin d'un type/DTO partagé | Créer dans `internal/domain/shared/` ou `web/src/types/shared.ts` — modification minimale, documenter dans ton livrable |
| Dépendance pas encore terminée | Ne pas commencer — choisir une autre FUNC disponible |
| Bug dans le socle (FUNC-000) | Documenter dans ton livrable §Blocages, statut registre → `bloquée`, passer à une autre FUNC si possible |
| API manquante d'un autre module | Mocker côté test ; documenter l'interface attendue dans ton livrable §Dépendances |

---

### Critère de succès

Ta mission est réussie quand :

1. Ta FUNC est `terminée` dans le registre.
2. Tous les tests passent (commandes documentées dans le livrable).
3. La parité fonctionnelle v1 est assurée pour ton périmètre.
4. Aucun fichier hors périmètre n'a été modifié (sauf ajouts minimaux autorisés).
5. Le livrable `FUNC-xxx.md` est complet.

**Commence maintenant par l'ÉTAPE 1 (réservation), puis annonce ta FUNC avant d'écrire du code.**

## ▲▲▲ FIN DU PROMPT ▲▲▲

---

## Notes d'utilisation (pour le chef de projet)

### Lancer N agents en parallèle

1. **Agent 1** : lance avec ce prompt → prendra FUNC-000 (socle).
2. **Agents 2 à N** : lancent le même prompt **après** que FUNC-000 est `terminée`, ou ils prendront des FUNC dont les dépendances sont satisfaites (ex. FUNC-053 Licence ne dépend que de FUNC-000).
3. Chaque agent dans une **branche git séparée** (`feat/FUNC-xxx-...`).
4. Intégration : merger les branches dans l'ordre des dépendances (FUNC-000 d'abord, puis par vague).

### Vagues de parallélisation suggérées

| Vague | Quand | FUNC possibles en parallèle |
|-------|-------|----------------------------|
| 0 | Démarrage | FUNC-000 seul |
| 1 | Socle terminé | FUNC-001, FUNC-004, FUNC-050, FUNC-053, FUNC-070, FUNC-040 |
| 2 | Auth + RBAC | FUNC-002, FUNC-003, FUNC-051, FUNC-052, FUNC-010 |
| 3 | Structure | FUNC-011, FUNC-017, FUNC-020, FUNC-030 |
| 4 | Structure avancée | FUNC-012, FUNC-013, FUNC-014, FUNC-018, FUNC-031, FUNC-032 |
| 5 | Cycle mensuel | FUNC-021 → FUNC-022 → FUNC-023, FUNC-024, FUNC-025 |
| 6 | Compléments | FUNC-015, FUNC-026, FUNC-033–035, FUNC-060–062, FUNC-054–057 |

### Fichiers créés pour la coordination

- `docs/refonte/agents/REGISTRE_FONCTIONNALITES.md` — registre partagé
- `docs/refonte/agents/livrables/` — un fichier par agent à la clôture
- `docs/refonte/endpoints_securite.csv` — traçabilité sécurité
