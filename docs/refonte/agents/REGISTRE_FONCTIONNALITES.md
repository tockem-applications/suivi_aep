# Registre des fonctionnalités — coordination multi-agents

> **Fichier partagé** : chaque agent lit ce registre **avant** de commencer, réserve **une seule** fonctionnalité,
> puis met à jour le statut à la fin. Ne jamais travailler sur une fonctionnalité déjà `réservée`, `en cours` ou `terminée`.

## Protocole de réservation (obligatoire)

1. Lire l'intégralité de ce fichier.
2. Choisir la **première** ligne `disponible` dont les **dépendances** sont toutes `terminée`.
3. Générer un identifiant agent unique : `agent-<timestamp>-<4 lettres aléatoires>` (ex. `agent-20260702-kfmx`).
4. Remplacer sur la ligne choisie :
   - `Statut` → `réservée`
   - `Agent` → votre identifiant
   - `Date` → date ISO du jour
   - `Branche` → `feat/FUNC-xxx-<slug>` (ex. `feat/FUNC-010-aep`)
5. **Commiter immédiatement** ce seul fichier avec le message : `chore(agents): réserver FUNC-xxx`.
6. Si le commit échoue (conflit git), **ne pas insister** : relire le registre, choisir une autre fonctionnalité.
7. Au démarrage effectif du code : passer le statut à `en cours`.
8. À la fin (tests OK, livrables remis) : passer à `terminée` et remplir la colonne `Notes` (résumé + PR/commit).

**Règle d'or** : 1 agent = 1 fonctionnalité à la fois. Pas de modification du code d'une autre fonctionnalité.

---

## Légende des statuts

| Statut | Signification |
|--------|---------------|
| `disponible` | Personne ne travaille dessus — peut être réservée |
| `réservée` | Un agent a pris le relais, code pas encore commencé |
| `en cours` | Développement actif |
| `en revue` | Code terminé, en attente de validation humaine |
| `terminée` | Tests passent, livrables complets, intégré |
| `bloquée` | Dépendance manquante ou blocage documenté dans Notes |

---

## Registre

| ID | Fonctionnalité | Exigences CDC | Dépend de | Statut | Agent | Date | Branche | Notes |
|----|----------------|---------------|-----------|--------|-------|------|---------|-------|
| FUNC-000 | **Socle technique** (squelette Go+React, config dual DB, migrations goose, CI, middlewares auth/RBAC/CSRF de base, embed SPA, healthcheck) | EX-100–144, EX-200–243, EX-300–302, EX-800–808 | — | terminée | agent-20260702-cursor | 2026-07-02 | feat/FUNC-000-socle | Socle initial — voir livrables/FUNC-000.md |
| FUNC-001 | Connexion / déconnexion / sessions / mot de passe oublié | EX-600, EX-220–223, EX-230 | FUNC-000 | réservée | agent-20260702-hqwp | 2026-07-02 | feat/FUNC-001-auth | |
| FUNC-002 | Inscription par clé d'accès + validation admin | EX-601 | FUNC-000, FUNC-001 | en cours | agent-20260702-func002 | 2026-07-02 | feat/FUNC-002-inscription | |
| FUNC-003 | Sélection AEP de travail + page accès refusé | EX-602, EX-603, EX-242 | FUNC-000, FUNC-001 | disponible | | | | |
| FUNC-004 | Charte graphique paramétrable (thème, logo, couleurs, devise, formats) | EX-500–504, EX-680 | FUNC-000 | disponible | | | | |
| FUNC-005 | Assistant premier démarrage | EX-311 | FUNC-000, FUNC-001, FUNC-004 | disponible | | | | |
| FUNC-010 | AEP — CRUD, attributs, suppression avec sauvegarde, dashboard AEP | EX-610 | FUNC-000, FUNC-003 | disponible | | | | |
| FUNC-011 | Réseaux — arborescence hiérarchique, CRUD, stats, export CSV | EX-611 | FUNC-010 | disponible | | | | |
| FUNC-012 | Compteurs réseau (production/distribution/réservoir) + historique index | EX-612 | FUNC-011 | disponible | | | | |
| FUNC-013 | Abonnés BP — CRUD, fiche complète (onglets), filtres, export CSV | EX-613 | FUNC-011 | disponible | | | | |
| FUNC-014 | Bornes fontaines + gérants (mandat, conversion BP↔BF) | EX-614 | FUNC-011 | disponible | | | | |
| FUNC-015 | Branchements — suivi, montants, filtres, agrégats mensuels | EX-615 | FUNC-013 | disponible | | | | |
| FUNC-016 | Import de données historiques (Fokoue data CSV) | EX-616 | FUNC-010, FUNC-011, FUNC-017 | disponible | | | | |
| FUNC-017 | Tarifs / constantes réseau + historique | EX-620 | FUNC-011 | disponible | | | | |
| FUNC-018 | Tarifs différenciés (paliers consommation) | EX-621 | FUNC-017 | disponible | | | | |
| FUNC-020 | Mois de facturation (création, activation, mois de base, dates) | EX-630 | FUNC-010 | disponible | | | | |
| FUNC-021 | Relevés d'index (saisie, import mobile JSON, export, contrôles) | EX-631 | FUNC-013, FUNC-020 | disponible | | | | |
| FUNC-022 | Facturation de masse (modèles Fokoué / Nkongzem) | EX-632 | FUNC-017, FUNC-018, FUNC-021 | disponible | | | | |
| FUNC-023 | Impression factures PDF (lot + unitaire, charte) | EX-633 | FUNC-022, FUNC-004 | disponible | | | | |
| FUNC-024 | Recouvrement unifié (versements, filtres, totaux, export CSV) | EX-634 | FUNC-022 | disponible | | | | |
| FUNC-025 | Pénalités (défaut, application/annulation lot, KPIs) | EX-635 | FUNC-022 | disponible | | | | |
| FUNC-026 | Impayés (suivi reste à payer, insolvables) | EX-636 | FUNC-022, FUNC-024 | disponible | | | | |
| FUNC-030 | Flux financiers + catégories de flux | EX-640 | FUNC-010 | disponible | | | | |
| FUNC-031 | Redevances + versements liés aux mois | EX-641 | FUNC-030 | disponible | | | | |
| FUNC-032 | Compte d'exploitation (grille annuelle) | EX-642 | FUNC-030 | disponible | | | | |
| FUNC-033 | Synthèse compte d'exploitation multi-AEP | EX-643 | FUNC-032 | disponible | | | | |
| FUNC-034 | Analyse financière (graphiques, indicateurs) | EX-644 | FUNC-030 | disponible | | | | |
| FUNC-035 | Config compte-rendu + agrégation auto recouvrements/branchements | EX-645, EX-646 | FUNC-024, FUNC-015, FUNC-030 | disponible | | | | |
| FUNC-040 | Interventions (CRUD, statuts, affectation ressources) | EX-650 | FUNC-000 | disponible | | | | |
| FUNC-041 | Ressources humaines et matérielles | EX-651 | FUNC-040 | disponible | | | | |
| FUNC-050 | Rôles et permissions (matrice RBAC) | EX-660, EX-240–243 | FUNC-000 | disponible | | | | |
| FUNC-051 | Utilisateurs (CRUD, rôles, AEP, reset mot de passe) | EX-661 | FUNC-050, FUNC-003 | disponible | | | | |
| FUNC-052 | Clés d'inscription (génération, suppression) | EX-662 | FUNC-002, FUNC-050 | disponible | | | | |
| FUNC-053 | Licence hors-ligne (import .lic, empreinte, statut, alertes) | EX-400–407 | FUNC-000 | disponible | | | | |
| FUNC-054 | Sauvegarde et restauration base (UI admin + re-auth) | EX-664, EX-144 | FUNC-000, FUNC-050 | disponible | | | | |
| FUNC-055 | Mise à jour application (auto-update signé, mode maintenance) | EX-665, EX-320–324 | FUNC-000, FUNC-053 | disponible | | | | |
| FUNC-056 | Journal d'audit (consultation, immuable) | EX-667, EX-270–271 | FUNC-000, FUNC-050 | disponible | | | | |
| FUNC-057 | À propos (version, licence, éditeur) | EX-666 | FUNC-053 | disponible | | | | |
| FUNC-060 | Dashboard AEP (KPIs, graphiques, période 3/6/12 mois) | EX-670 | FUNC-024, FUNC-026 | disponible | | | | |
| FUNC-061 | Statistiques réseau + tableau facturation BF/BP | EX-671 | FUNC-011, FUNC-022 | disponible | | | | |
| FUNC-062 | Accueil / synthèse globale multi-AEP | EX-672 | FUNC-060 | disponible | | | | |
| FUNC-070 | Outil migration MySQL v1 → v2 (dry-run, rapport, totaux) | EX-700–705 | FUNC-000 | disponible | | | | |

---

## Fichiers autorisés par fonctionnalité (périmètre strict)

Chaque agent ne modifie **que** les chemins listés pour sa FUNC + les fichiers partagés explicitement autorisés.

### Fichiers partagés (modification minimale uniquement)

| Fichier / zone | Règle |
|----------------|-------|
| `internal/http/router.go` | Ajouter **uniquement** l'enregistrement des routes de sa FUNC (1 import + 1 ligne `Mount`) |
| `internal/http/middleware/*` | Lecture seule sauf FUNC-000 |
| `migrations/` | Créer **uniquement** des fichiers préfixés `YYYYMMDD_FUNCxxx_*.sql` |
| `web/src/routes.tsx` | Ajouter **uniquement** la route de sa FUNC |
| `web/src/components/layout/Sidebar.tsx` | Ajouter **uniquement** l'entrée de menu de sa FUNC |
| `docs/refonte/endpoints_securite.csv` | Ajouter les lignes de ses endpoints |
| `docs/refonte/agents/livrables/FUNC-xxx.md` | Créer/mettre à jour **son** livrable |

**Interdit** : modifier le code d'une autre FUNC (`internal/service/<autre>`, `web/src/features/<autre>`).

### Périmètres par FUNC (à créer dans `suivi-reseau-v2/`)

| ID | Backend (`internal/`) | Frontend (`web/src/features/`) | Migration |
|----|----------------------|-------------------------------|-----------|
| FUNC-000 | `cmd/server`, `config`, `store`, `http` (tout le socle) | `app`, `shared`, `lib` | `0001_init.sql` |
| FUNC-001 | `auth`, `http/handlers/auth` | `auth/` | `FUNC001_auth.sql` |
| FUNC-002 | `auth/signup`, `http/handlers/signup` | `auth/signup/` | `FUNC002_signup.sql` |
| FUNC-003 | `aepcontext`, `http/handlers/aepcontext` | `aep-selection/` | — |
| FUNC-004 | `theme` | `settings/theme/` | `FUNC004_theme.sql` |
| FUNC-005 | `onboarding` | `onboarding/` | — |
| FUNC-010 | `domain/aep`, `service/aep`, `store/aep` | `structure/aep/` | `FUNC010_aep.sql` |
| FUNC-011 | `domain/reseau`, `service/reseau`, `store/reseau` | `structure/reseaux/` | `FUNC011_reseau.sql` |
| FUNC-012 | `domain/compteur`, `service/compteur` | `structure/compteurs/` | `FUNC012_compteur.sql` |
| FUNC-013 | `domain/abonne`, `service/abonne` | `structure/abonnes/` | `FUNC013_abonne.sql` |
| FUNC-014 | `domain/borne_fontaine`, `service/bf` | `structure/bornes-fontaines/` | `FUNC014_bf.sql` |
| FUNC-015 | `domain/branchement`, `service/branchement` | `finances/branchements/` | `FUNC015_branchement.sql` |
| FUNC-016 | `service/import_fokoue` | `structure/import/` | — |
| FUNC-017 | `domain/tarif`, `service/tarif` | `finances/tarifs/` | `FUNC017_tarif.sql` |
| FUNC-018 | `domain/tarif_diff`, `service/tarif_diff` | `finances/tarifs-differencies/` | `FUNC018_tarif_diff.sql` |
| FUNC-020 | `domain/mois_fact`, `service/mois_fact` | `facturation/mois/` | `FUNC020_mois.sql` |
| FUNC-021 | `domain/releve`, `service/releve` | `facturation/releves/` | `FUNC021_releve.sql` |
| FUNC-022 | `domain/facture`, `service/facturation` | `facturation/factures/` | `FUNC022_facture.sql` |
| FUNC-023 | `service/pdf` | `facturation/factures/print/` | — |
| FUNC-024 | `domain/recouvrement`, `service/recouvrement` | `facturation/recouvrement/` | `FUNC024_recouvrement.sql` |
| FUNC-025 | `domain/penalite`, `service/penalite` | `facturation/penalites/` | `FUNC025_penalite.sql` |
| FUNC-026 | `domain/impaye`, `service/impaye` | `facturation/impayes/` | — |
| FUNC-030 | `domain/flux`, `service/flux` | `finances/flux/` | `FUNC030_flux.sql` |
| FUNC-031 | `domain/redevance`, `service/redevance` | `finances/redevances/` | `FUNC031_redevance.sql` |
| FUNC-032 | `domain/compte_exploit`, `service/compte_exploit` | `finances/compte-exploitation/` | — |
| FUNC-033 | `service/synthese` | `finances/synthese/` | — |
| FUNC-034 | `service/analyse_fin` | `finances/analyse/` | — |
| FUNC-035 | `domain/config_cr`, `service/config_cr` | `finances/config-compte-rendu/` | `FUNC035_config_cr.sql` |
| FUNC-040 | `domain/intervention`, `service/intervention` | `interventions/` | `FUNC040_intervention.sql` |
| FUNC-041 | `domain/ressource`, `service/ressource` | `ressources/` | `FUNC041_ressource.sql` |
| FUNC-050 | `auth/rbac`, `service/rbac` | `admin/roles/` | `FUNC050_rbac.sql` |
| FUNC-051 | `service/user` | `admin/users/` | — |
| FUNC-052 | `service/signup_keys` | `admin/cles/` | `FUNC052_keys.sql` |
| FUNC-053 | `licence` | `admin/licence/` | `FUNC053_licence.sql` |
| FUNC-054 | `service/backup` | `admin/backup/` | — |
| FUNC-055 | `update` | `admin/update/` | — |
| FUNC-056 | `service/audit` | `admin/audit/` | `FUNC056_audit.sql` |
| FUNC-057 | `service/about` | `admin/about/` | — |
| FUNC-060 | `service/dashboard` | `dashboard/` | — |
| FUNC-061 | `service/stats_reseau` | `reporting/stats-reseau/` | — |
| FUNC-062 | `service/synthese_globale` | `reporting/synthese/` | — |
| FUNC-070 | `cmd/migrate`, `service/migration` | — | — |

---

## Références v1 (code existant à auditer pour parité fonctionnelle)

| ID | Fichiers v1 principaux |
|----|------------------------|
| FUNC-001 | `index.php`, `donnees/user.php`, `traitement/user_t.php` |
| FUNC-002 | `presentation/nos_access_page.php`, `traitement/user_t.php` |
| FUNC-010 | `presentation/aep_page.php`, `donnees/aep.php`, `traitement/aep_t.php` |
| FUNC-011 | `presentation/reseaux_page.php`, `donnees/reseau.php`, `traitement/reseau_t.php` |
| FUNC-012 | `donnees/compteur.php`, `traitement/compteur_t.php` |
| FUNC-013 | `presentation/abonne_page.php`, `presentation/info_abone_page.php`, `donnees/Abones.php` |
| FUNC-014 | `presentation/borne_fontaine_page.php`, `donnees/borne_fontaine.php`, `donnees/bf_gerant.php` |
| FUNC-015 | `presentation/branchements_page.php`, `donnees/branchement_abonne.php` |
| FUNC-016 | `traitement/fokoue_data_t.php` |
| FUNC-017 | `presentation/tarif_page.php`, `donnees/constante_reseau.php` |
| FUNC-018 | `donnees/tarif_differencie.php`, `presentation/detail_tarif_page.php` |
| FUNC-020 | `donnees/mois_facturation.php`, `traitement/mois_facturation_t.php` |
| FUNC-021 | `presentation/releve_page.php`, `donnees/indexes.php` |
| FUNC-022 | `donnees/facture.php`, `traitement/facture_t.php` |
| FUNC-024 | `presentation/recouvrement_page.php`, `traitement/recouvrement_t.php`, `traitement/versement_t.php` |
| FUNC-025 | `presentation/penalites_page.php`, `traitement/penalite_t.php` |
| FUNC-026 | `donnees/impaye.php` |
| FUNC-030 | `donnees/flux_financier.php`, `donnees/categorie_flux_manuel.php` |
| FUNC-031 | `presentation/redevance_page.php`, `donnees/redevance.php` |
| FUNC-032 | `presentation/compte_rendu_financier_page.php`, `donnees/nouveau_compte_exploitation.php` |
| FUNC-033 | `presentation/synthese_compte_exploitation_page.php` |
| FUNC-034 | `presentation/analyse_financiere_page.php` |
| FUNC-035 | `presentation/config_compte_rendu_page.php`, `donnees/config_compte_rendu_financier.php` |
| FUNC-040 | `presentation/interventions_page.php`, `donnees/intervention.php` |
| FUNC-041 | `presentation/ressources_page.php`, `donnees/ressource_humaine.php`, `donnees/ressource_materielle.php` |
| FUNC-050 | `donnees/role.php`, `traitement/role_t.php` |
| FUNC-051 | `donnees/user.php`, `traitement/user_t.php` |
| FUNC-052 | `presentation/clef_page.php` |
| FUNC-053 | `donnees/app_licence.php`, `donnees/licence_crypto.php`, `presentation/licence_page.php` |
| FUNC-054 | `presentation/backup_page.php`, `traitement/backup_t.php` |
| FUNC-056 | `donnees/log.php` |

---

## Maquettes UI (référence visuelle)

Dossier : `docs/refonte/maquette/`. Voir le mapping détaillé dans `PROMPT_AGENT_FONCTIONNALITE.md` § Références maquettes.
